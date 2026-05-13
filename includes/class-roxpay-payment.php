<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Class RoxPay_WPForms_Payment
 *
 * Intercepts WPForms form submissions that have RoxPay enabled,
 * creates a hosted checkout link via POST /api/v4/payments/link,
 * stores a pending record in the DB, and redirects the shopper.
 */
class RoxPay_WPForms_Payment {

	public function __construct() {
		add_filter( 'wpforms_payments_available',       [ $this, 'register_gateway' ] );
		add_action( 'wpforms_payments_settings_roxpay', [ $this, 'payment_settings_panel' ], 10, 2 );
		add_action( 'wpforms_process_complete',         [ $this, 'process_payment' ], 10, 4 );
		add_action( 'wpforms_frontend_output_after',    [ $this, 'show_payment_notice' ], 10, 2 );
	}

	// -------------------------------------------------------------------------
	// Gateway registration
	// -------------------------------------------------------------------------

	public function register_gateway( $gateways ) {
		$gateways['roxpay'] = [
			'name'        => esc_html__( 'RoxPay', 'roxpay-wpforms' ),
			'description' => esc_html__( 'Accept payments via RoxPay hosted checkout.', 'roxpay-wpforms' ),
		];
		return $gateways;
	}

	// -------------------------------------------------------------------------
	// Form-builder payment panel
	// -------------------------------------------------------------------------

	public function payment_settings_panel( $instance, $form_data ) {
		wpforms_panel_field(
			'toggle',
			'payments',
			'roxpay_enable',
			$form_data,
			esc_html__( 'Enable RoxPay Payments', 'roxpay-wpforms' ),
			[
				'name'  => 'payments[roxpay][enable]',
				'value' => ! empty( $form_data['payments']['roxpay']['enable'] ) ? '1' : '0',
			]
		);
		?>
		<div class="wpforms-alert wpforms-alert-info">
			<p><?php esc_html_e( 'After submission the shopper is redirected to the RoxPay hosted checkout. The order total is read automatically from the WPForms payment fields.', 'roxpay-wpforms' ); ?></p>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Payment processing
	// -------------------------------------------------------------------------

	/**
	 * Intercept a completed WPForms submission and initiate RoxPay checkout.
	 *
	 * @param array $fields    Submitted field values.
	 * @param array $entry     Raw entry data.
	 * @param array $form_data Form configuration.
	 * @param int   $entry_id  Saved WPForms entry ID.
	 */
	public function process_payment( $fields, $entry, $form_data, $entry_id ) {

		// Guard: RoxPay must be enabled for this form.
		if ( empty( $form_data['payments']['roxpay']['enable'] ) ) {
			return;
		}

		// Guard: credentials must exist.
		$settings = get_option( 'roxpay_wpforms_settings', [] );
		if ( empty( $settings['username'] ) || empty( $settings['password'] ) ) {
			wpforms()->process->errors[ $form_data['id'] ]['footer'] = esc_html__(
				'Payment could not be initiated — RoxPay credentials are not configured.',
				'roxpay-wpforms'
			);
			return;
		}

		// STEP 1 — Get order total from WPForms.
		$total = wpforms_get_total_payment( $fields );
		if ( empty( $total ) || (float) $total <= 0 ) {
			wpforms()->process->errors[ $form_data['id'] ]['footer'] = esc_html__(
				'Payment could not be initiated — order total is zero.',
				'roxpay-wpforms'
			);
			return;
		}

		// STEP 2 — Convert to integer cents.
		$amount_cents = (int) round( (float) $total * 100 );

		// STEP 3 — Human-readable title + description.
		$title       = $this->build_title( $form_data );
		$description = $this->build_description( $fields, $title, (float) $total );

		// STEP 4 — Unique internal reference.
		$internal_txn_id = 'WPF-' . absint( $entry_id ) . '-' . time();

		// STEP 5 — Redirect URLs.
		$success_url = $this->get_redirect_url( 'success', $entry_id, $form_data );
		$failure_url = $this->get_redirect_url( 'failure', $entry_id, $form_data );
		$cancel_url  = $this->get_redirect_url( 'cancel',  $entry_id, $form_data );

		// STEP 6 — Webhook URL.
		$webhook_url = rest_url( 'roxpay-wpforms/v1/webhook' );

		// STEP 7 — Call RoxPay API.
		$auth   = new RoxPay_Auth();
		$api    = new RoxPay_API( $auth );
		$result = $api->create_payment_link( [
			'amount'           => $amount_cents,
			'currency'         => 'EUR',
			'title'            => $title,
			'description'      => $description,
			'purpose'          => $title,
			'transaction_id'   => $internal_txn_id,
			'success_redirect' => $success_url,
			'failure_redirect' => $failure_url,
			'cancel_redirect'  => $cancel_url,
			'webhook_url'      => $webhook_url,
			'auto_capture'     => true,
			'metadata'         => [
				'wpforms_entry_id' => (string) $entry_id,
				'wpforms_form_id'  => (string) $form_data['id'],
				'internal_txn_id'  => $internal_txn_id,
			],
		] );

		// STEP 8 — Handle API errors.
		if ( is_wp_error( $result ) ) {
			error_log( '[RoxPay WPForms] Payment link creation failed for entry ' . $entry_id . ': ' . $result->get_error_message() );
			wpforms()->process->errors[ $form_data['id'] ]['footer'] = esc_html__(
				'Payment could not be initiated. Please try again or contact support.',
				'roxpay-wpforms'
			);
			return;
		}

		// STEP 9 — Store pending record in our DB table.
		$roxpay_txn_id  = sanitize_text_field( $result['TransactionId'] ?? '' );
		$field_snapshot = $this->build_field_snapshot( $fields );

		RoxPay_DB::insert( [
			'entry_id'        => $entry_id,
			'form_id'         => $form_data['id'],
			'transaction_id'  => $roxpay_txn_id,
			'internal_txn_id' => $internal_txn_id,
			'amount'          => $amount_cents,
			'currency'        => 'EUR',
			'status'          => 'pending',
			'form_snapshot'   => $field_snapshot,
		] );

		// STEP 9b — Also write to WPForms PRO entry meta if available.
		if ( ! empty( $roxpay_txn_id )
			&& function_exists( 'wpforms' )
			&& method_exists( wpforms()->entry_meta, 'add' ) ) {
			wpforms()->entry_meta->add( [
				'entry_id' => $entry_id,
				'type'     => 'roxpay_transaction_id',
				'data'     => $roxpay_txn_id,
			] );
		}

		// STEP 10 — Redirect to hosted checkout.
		$payment_url = esc_url_raw( $result['PaymentUrl'] );
		error_log( '[RoxPay WPForms] Redirecting entry ' . $entry_id . ' to: ' . $payment_url );

		wp_redirect( $payment_url );
		exit;
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Short checkout title from the form name or site name.
	 *
	 * @param array $form_data
	 * @return string
	 */
	private function build_title( $form_data ) {
		$form_name = $form_data['settings']['form_title'] ?? '';
		if ( ! empty( $form_name ) ) {
			return mb_substr( sanitize_text_field( $form_name ), 0, 100 );
		}
		return mb_substr( sanitize_text_field( get_bloginfo( 'name' ) ), 0, 100 );
	}

	/**
	 * Description built from field values (generic — works for any form).
	 *
	 * @param array  $fields
	 * @param string $fallback
	 * @param float  $total
	 * @return string
	 */
	private function build_description( $fields, $fallback, $total ) {
		$parts = [];
		if ( is_array( $fields ) ) {
			foreach ( $fields as $field ) {
				$value = $field['value'] ?? '';
				$name  = $field['name'] ?? '';
				if ( ! empty( $value ) && ! empty( $name ) ) {
					$parts[] = sanitize_text_field( $name ) . ': ' . sanitize_text_field( $value );
				}
			}
		}
		$desc = ! empty( $parts ) ? implode( ' | ', array_slice( $parts, 0, 4 ) ) : $fallback;
		return mb_substr( $desc, 0, 255 );
	}

	/**
	 * Build redirect URL for a given outcome type.
	 *
	 * Priority: plugin setting override → WPForms confirmation → home URL.
	 *
	 * @param string $type     'success' | 'failure' | 'cancel'
	 * @param int    $entry_id
	 * @param array  $form_data
	 * @return string
	 */
	private function get_redirect_url( $type, $entry_id, $form_data ) {
		$settings = get_option( 'roxpay_wpforms_settings', [] );

		// 1. Plugin-level override.
		$key = $type . '_redirect_url';
		if ( ! empty( $settings[ $key ] ) ) {
			return esc_url_raw( $settings[ $key ] );
		}

		// 2. WPForms form confirmation URL (success only).
		if ( $type === 'success' ) {
			$conf_type = $form_data['settings']['confirmation_type'] ?? '';
			if ( $conf_type === 'redirect' && ! empty( $form_data['settings']['confirmation_redirect'] ) ) {
				return esc_url_raw( $form_data['settings']['confirmation_redirect'] );
			}
			if ( $conf_type === 'page' && ! empty( $form_data['settings']['confirmation_page'] ) ) {
				return (string) get_permalink( (int) $form_data['settings']['confirmation_page'] );
			}
		}

		// 3. Home URL with outcome query params.
		return add_query_arg( [
			'roxpay_status' => $type,
			'entry_id'      => absint( $entry_id ),
		], home_url( '/' ) );
	}

	/**
	 * Build a sanitised array snapshot of form fields for DB storage.
	 *
	 * @param array $fields WPForms submitted fields.
	 * @return array [ { name, value }, ... ]
	 */
	private function build_field_snapshot( $fields ) {
		$snapshot = [];
		if ( ! is_array( $fields ) ) {
			return $snapshot;
		}
		foreach ( $fields as $field ) {
			$name  = sanitize_text_field( $field['name'] ?? $field['label'] ?? '' );
			$value = sanitize_textarea_field( $field['value'] ?? '' );
			if ( empty( $name ) ) {
				continue;
			}
			$snapshot[] = [ 'name' => $name, 'value' => $value ];
		}
		return $snapshot;
	}

	// -------------------------------------------------------------------------
	// Frontend notice (return from RoxPay)
	// -------------------------------------------------------------------------

	/**
	 * Show a notice when the shopper returns from RoxPay with an error/cancel.
	 *
	 * @param array  $form_data
	 * @param object $form
	 */
	public function show_payment_notice( $form_data, $form ) {
		$status = isset( $_GET['roxpay_status'] ) ? sanitize_text_field( $_GET['roxpay_status'] ) : '';

		if ( $status === 'failure' ) {
			echo '<div class="wpforms-error-container"><p>'
				. esc_html__( 'Your payment was unsuccessful. Please try again. If the issue persists, contact support.', 'roxpay-wpforms' )
				. '</p></div>';
		}

		if ( $status === 'cancel' ) {
			echo '<div class="wpforms-error-container"><p>'
				. esc_html__( 'You cancelled the payment. You can try again when ready.', 'roxpay-wpforms' )
				. '</p></div>';
		}

		// Legacy ?roxpay_error=1 support.
		if ( isset( $_GET['roxpay_error'] ) && '1' === sanitize_text_field( $_GET['roxpay_error'] ) ) {
			echo '<div class="wpforms-error-container"><p>'
				. esc_html__( 'There was a problem processing your payment. Please try again.', 'roxpay-wpforms' )
				. '</p></div>';
		}
	}
}

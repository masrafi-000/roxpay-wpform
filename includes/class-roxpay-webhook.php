<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Class RoxPay_Webhook
 *
 * Handles incoming webhook POST notifications from RoxPay.
 *
 * Register this URL in your RoxPay dashboard:
 *   POST {site_url}/wp-json/roxpay-wpforms/v1/webhook
 *
 * RoxPay POSTs a JSON body with PascalCase fields:
 *   TransactionId, Status, Amount, Currency, Metadata
 * Metadata carries the wpforms_entry_id we set when creating the link.
 */
class RoxPay_Webhook {

	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_route' ] );
	}

	// -------------------------------------------------------------------------
	// REST Route
	// -------------------------------------------------------------------------

	public function register_route() {
		register_rest_route( 'roxpay-wpforms/v1', '/webhook', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'handle' ],
			'permission_callback' => '__return_true', // Auth via HMAC below.
		] );
	}

	// -------------------------------------------------------------------------
	// Handler
	// -------------------------------------------------------------------------

	/**
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function handle( WP_REST_Request $request ) {
		$raw_body = $request->get_body();

		// ---- Optional HMAC signature verification ----
		$settings       = get_option( 'roxpay_wpforms_settings', [] );
		$webhook_secret = ! empty( $settings['webhook_secret'] )
			? base64_decode( $settings['webhook_secret'] )
			: '';

		if ( ! empty( $webhook_secret ) ) {
			$signature = $request->get_header( 'x-roxpay-signature' )
				?? $request->get_header( 'x-signature' )
				?? '';

			if ( ! empty( $signature ) ) {
				$expected = hash_hmac( 'sha256', $raw_body, $webhook_secret );
				if ( ! hash_equals( $expected, $signature ) ) {
					error_log( '[RoxPay WPForms] Webhook signature mismatch — rejected.' );
					return new WP_REST_Response( [ 'error' => 'Invalid signature' ], 401 );
				}
			} else {
				error_log( '[RoxPay WPForms] Webhook received without signature header.' );
			}
		}

		// ---- Parse body ----
		$data = json_decode( $raw_body, true );
		if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $data ) ) {
			error_log( '[RoxPay WPForms] Webhook: invalid JSON body.' );
			return new WP_REST_Response( [ 'error' => 'Invalid JSON' ], 400 );
		}

		$roxpay_txn_id = sanitize_text_field( $data['TransactionId'] ?? '' );
		$status        = sanitize_text_field( $data['Status']        ?? $data['status'] ?? '' );
		$metadata      = $data['Metadata'] ?? $data['metadata'] ?? [];
		$entry_id      = absint( $metadata['wpforms_entry_id'] ?? 0 );

		error_log( sprintf(
			'[RoxPay WPForms] Webhook — TxnId: %s  Status: %s  EntryId: %d',
			$roxpay_txn_id, $status, $entry_id
		) );

		if ( empty( $roxpay_txn_id ) || empty( $status ) ) {
			error_log( '[RoxPay WPForms] Webhook: missing required fields.' );
			return new WP_REST_Response( [ 'error' => 'Missing fields' ], 400 );
		}

		// ---- Dispatch ----
		if ( $this->is_success( $status ) ) {
			$this->mark_completed( $roxpay_txn_id, $data, $entry_id );
		} elseif ( $this->is_failed( $status ) ) {
			$this->mark_failed( $roxpay_txn_id, $entry_id );
		} else {
			error_log( '[RoxPay WPForms] Webhook: unhandled status "' . $status . '"' );
		}

		return new WP_REST_Response( [ 'received' => true ], 200 );
	}

	// -------------------------------------------------------------------------
	// Status helpers
	// -------------------------------------------------------------------------

	private function is_success( $status ) {
		static $ok = [ 'success', 'paid', 'completed', 'captured',
		               'Success', 'Paid', 'Completed', 'Captured' ];
		return in_array( $status, $ok, true );
	}

	private function is_failed( $status ) {
		static $bad = [ 'failed', 'failure', 'declined', 'error',
		                'Failed', 'Failure', 'Declined', 'Error' ];
		return in_array( $status, $bad, true );
	}

	// -------------------------------------------------------------------------
	// Entry state changes
	// -------------------------------------------------------------------------

	/**
	 * Mark a payment as completed (idempotent).
	 *
	 * @param string $roxpay_txn_id
	 * @param array  $webhook_data
	 * @param int    $entry_id
	 */
	private function mark_completed( $roxpay_txn_id, $webhook_data, $entry_id ) {
		if ( $this->already_completed( $roxpay_txn_id ) ) {
			error_log( '[RoxPay WPForms] Txn ' . $roxpay_txn_id . ' already completed — skipped.' );
			return;
		}

		// Update our DB record — this is the primary source of truth.
		RoxPay_DB::update_status( $roxpay_txn_id, 'completed' );

		// If this is a WPForms entry, update its meta too.
		if ( $entry_id > 0 && function_exists( 'wpforms' ) && method_exists( wpforms()->entry_meta, 'add' ) ) {
			wpforms()->entry_meta->add( [
				'entry_id' => $entry_id,
				'type'     => 'payment',
				'data'     => wp_json_encode( [
					'gateway'        => 'roxpay',
					'transaction_id' => $roxpay_txn_id,
					'status'         => 'completed',
					'amount'         => (int) ( $webhook_data['Amount'] ?? 0 ),
					'currency'       => sanitize_text_field( $webhook_data['Currency'] ?? 'EUR' ),
				] ),
			] );
			if ( method_exists( wpforms()->entry, 'update' ) ) {
				wpforms()->entry->update( $entry_id, [ 'status' => 'completed' ] );
			}
		}

		error_log( '[RoxPay WPForms] Txn ' . $roxpay_txn_id . ' marked completed. EntryId: ' . $entry_id );

		/**
		 * Fires after a RoxPay payment is confirmed completed.
		 *
		 * @param int    $entry_id
		 * @param string $roxpay_txn_id
		 * @param array  $webhook_data
		 */
		do_action( 'roxpay_payment_completed', $entry_id, $roxpay_txn_id, $webhook_data );
	}

	/**
	 * Mark a payment as failed.
	 *
	 * @param string $roxpay_txn_id
	 * @param int    $entry_id
	 */
	private function mark_failed( $roxpay_txn_id, $entry_id ) {
		RoxPay_DB::update_status( $roxpay_txn_id, 'failed' );

		if ( $entry_id > 0 && function_exists( 'wpforms' ) && method_exists( wpforms()->entry_meta, 'add' ) ) {
			wpforms()->entry_meta->add( [
				'entry_id' => $entry_id,
				'type'     => 'payment',
				'data'     => wp_json_encode( [
					'gateway'        => 'roxpay',
					'transaction_id' => $roxpay_txn_id,
					'status'         => 'failed',
				] ),
			] );
			if ( method_exists( wpforms()->entry, 'update' ) ) {
				wpforms()->entry->update( $entry_id, [ 'status' => 'failed' ] );
			}
		}

		error_log( '[RoxPay WPForms] Txn ' . $roxpay_txn_id . ' marked failed. EntryId: ' . $entry_id );

		/**
		 * Fires after a RoxPay payment is confirmed failed.
		 *
		 * @param int    $entry_id
		 * @param string $roxpay_txn_id
		 */
		do_action( 'roxpay_payment_failed', $entry_id, $roxpay_txn_id );
	}

	/**
	 * Check our DB record to prevent duplicate processing.
	 *
	 * @param string $roxpay_txn_id
	 * @return bool
	 */
	private function already_completed( $roxpay_txn_id ) {
		$record = RoxPay_DB::get_by_txn_id( $roxpay_txn_id );
		return ( $record && $record['status'] === 'completed' );
	}
}

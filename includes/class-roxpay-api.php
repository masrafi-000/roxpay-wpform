<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Class RoxPay_API
 *
 * Authenticated HTTP client for the RoxPay v4 API.
 *
 * Payment endpoints:
 *   POST   /api/v4/payments/link        create hosted checkout link
 *   POST   /api/v4/payments/refund      refund a transaction
 *   POST   /api/v4/payments/capture     capture a pre-authorisation
 *   POST   /api/v4/payments/release     release a pre-authorisation
 *
 * Webhook endpoints:
 *   GET    /api/v4/webhooks             list webhook configurations
 *   POST   /api/v4/webhooks/create      create a webhook
 *   PUT    /api/v4/webhooks/{id}        update a webhook
 *   DELETE /api/v4/webhooks/{id}        delete a webhook
 *   GET    /api/v4/webhooks/deliveries  delivery history
 */
class RoxPay_API {

	/** @var RoxPay_Auth */
	private $auth;

	public function __construct( RoxPay_Auth $auth ) {
		$this->auth = $auth;
	}

	// =========================================================================
	// Internal helpers
	// =========================================================================

	/**
	 * Build Authorization + content headers.
	 *
	 * @return array|WP_Error
	 */
	private function get_headers() {
		$token = $this->auth->get_token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}
		return [
			'Authorization' => 'Bearer ' . $token,
			'Content-Type'  => 'application/json',
			'Accept'        => 'application/json',
		];
	}

	/**
	 * Generic request dispatcher.
	 *
	 * @param string $method   GET | POST | PUT | DELETE
	 * @param string $path     Relative path, e.g. '/payments/link'
	 * @param array  $body     Body payload (POST / PUT only)
	 * @param array  $query    URL query parameters (GET / DELETE)
	 * @param int    $timeout  Seconds
	 * @return array|WP_Error Decoded JSON body or WP_Error
	 */
	private function request( $method, $path, array $body = [], array $query = [], $timeout = 20 ) {
		$headers = $this->get_headers();
		if ( is_wp_error( $headers ) ) {
			return $headers;
		}

		$url = $this->auth->get_base_url() . $path;
		if ( ! empty( $query ) ) {
			$url = add_query_arg( $query, $url );
		}

		$args = [
			'method'    => strtoupper( $method ),
			'timeout'   => $timeout,
			'sslverify' => ! ( strpos( home_url(), 'localhost' ) !== false ),
			'headers'   => $headers,
		];

		if ( in_array( strtoupper( $method ), [ 'POST', 'PUT' ], true ) && ! empty( $body ) ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'roxpay_api_error', esc_html__( 'Could not connect to RoxPay API: ', 'roxpay-wpforms' ) . $response->get_error_message() );
		}

		$code     = wp_remote_retrieve_response_code( $response );
		$raw      = wp_remote_retrieve_body( $response );

		error_log( '[RoxPay WPForms] ' . $method . ' ' . $path . ' → HTTP ' . $code );

		if ( $code < 200 || $code >= 300 ) {
			$err = json_decode( $raw, true );
			$msg = $err['Message'] ?? ( 'HTTP ' . $code );
			error_log( '[RoxPay WPForms] ' . $method . ' ' . $path . ' error body: ' . $raw );
			return new WP_Error( 'roxpay_api_error', esc_html( $msg ) );
		}

		if ( empty( $raw ) ) {
			return [ 'Result' => true, 'StatusCode' => $code ];
		}

		$data = json_decode( $raw, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			error_log( '[RoxPay WPForms] ' . $method . ' ' . $path . ' invalid JSON: ' . $raw );
			return new WP_Error( 'roxpay_invalid_json', esc_html__( 'Invalid response from RoxPay.', 'roxpay-wpforms' ) );
		}

		return $data;
	}

	// =========================================================================
	// Payment Link
	// =========================================================================

	/**
	 * Create a hosted payment link.
	 *
	 * POST /api/v4/payments/link
	 *
	 * @param array $args {
	 *   @type int    amount             Amount in cents. Required.
	 *   @type string currency           ISO 4217. Default 'EUR'.
	 *   @type string title              Short checkout title.
	 *   @type string description        Longer description.
	 *   @type string purpose            Purpose label.
	 *   @type string transaction_id     Your internal reference.
	 *   @type string success_redirect   URL for successful payment.
	 *   @type string failure_redirect   URL for failed payment.
	 *   @type string cancel_redirect    URL if shopper cancels.
	 *   @type string webhook_url        Per-link webhook endpoint.
	 *   @type bool   auto_capture       Capture immediately. Default true.
	 *   @type array  metadata           Key-value pairs forwarded in webhooks.
	 * }
	 * @return array|WP_Error
	 */
	public function create_payment_link( array $args ) {
		$payload = [
			'Amount'      => (int) ( $args['amount'] ?? 0 ),
			'Currency'    => sanitize_text_field( $args['currency'] ?? 'EUR' ),
			'AutoCapture' => isset( $args['auto_capture'] ) ? (bool) $args['auto_capture'] : true,
		];

		if ( ! empty( $args['title'] ) ) {
			$payload['Title'] = mb_substr( sanitize_text_field( $args['title'] ), 0, 255 );
		}
		if ( ! empty( $args['description'] ) ) {
			$payload['Description'] = mb_substr( sanitize_text_field( $args['description'] ), 0, 500 );
		}
		if ( ! empty( $args['purpose'] ) ) {
			$payload['Purpose'] = mb_substr( sanitize_text_field( $args['purpose'] ), 0, 255 );
		}
		if ( ! empty( $args['transaction_id'] ) ) {
			$payload['TransactionId'] = sanitize_text_field( $args['transaction_id'] );
		}
		if ( ! empty( $args['success_redirect'] ) ) {
			$payload['SuccessRedirectUrl'] = esc_url_raw( $args['success_redirect'] );
		}
		if ( ! empty( $args['failure_redirect'] ) ) {
			$payload['FailureRedirectUrl'] = esc_url_raw( $args['failure_redirect'] );
		}
		if ( ! empty( $args['cancel_redirect'] ) ) {
			$payload['CancelRedirectUrl'] = esc_url_raw( $args['cancel_redirect'] );
		}
		if ( ! empty( $args['webhook_url'] ) ) {
			$payload['WebhookUrl'] = esc_url_raw( $args['webhook_url'] );
		}
		if ( ! empty( $args['metadata'] ) && is_array( $args['metadata'] ) ) {
			$meta = [];
			foreach ( $args['metadata'] as $k => $v ) {
				$meta[ sanitize_key( $k ) ] = (string) $v;
			}
			$payload['Metadata'] = $meta;
		}

		$data = $this->request( 'POST', '/payments/link', $payload );
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		if ( empty( $data['PaymentUrl'] ) ) {
			error_log( '[RoxPay WPForms] create_payment_link: PaymentUrl missing. Response: ' . wp_json_encode( $data ) );
			return new WP_Error( 'roxpay_no_url', esc_html__( 'No payment URL returned by RoxPay.', 'roxpay-wpforms' ) );
		}

		return $data;
	}

	// =========================================================================
	// Refund / Capture / Release
	// =========================================================================

	/**
	 * Refund one or more transactions.
	 *
	 * @param array $items [ [ 'TransactionId' => '...', 'Amount' => int ], ... ]
	 * @return array|WP_Error
	 */
	public function refund( array $items ) {
		$clean = array_map( function( $item ) {
			$r = [ 'TransactionId' => sanitize_text_field( $item['TransactionId'] ?? '' ) ];
			if ( isset( $item['Amount'] ) ) {
				$r['Amount'] = (int) $item['Amount'];
			}
			return $r;
		}, $items );
		return $this->request( 'POST', '/payments/refund', [ 'Items' => $clean ] );
	}

	/**
	 * Capture a pre-authorisation.
	 *
	 * @param array $items [ [ 'TransactionId' => '...', 'Amount' => int ], ... ]
	 * @return array|WP_Error
	 */
	public function capture( array $items ) {
		$clean = array_map( function( $item ) {
			$r = [ 'TransactionId' => sanitize_text_field( $item['TransactionId'] ?? '' ) ];
			if ( isset( $item['Amount'] ) ) {
				$r['Amount'] = (int) $item['Amount'];
			}
			return $r;
		}, $items );
		return $this->request( 'POST', '/payments/capture', [ 'Items' => $clean ] );
	}

	/**
	 * Release a pre-authorisation.
	 *
	 * @param array $items [ [ 'TransactionId' => '...' ], ... ]
	 * @return array|WP_Error
	 */
	public function release( array $items ) {
		$clean = array_map( function( $item ) {
			return [ 'TransactionId' => sanitize_text_field( $item['TransactionId'] ?? '' ) ];
		}, $items );
		return $this->request( 'POST', '/payments/release', [ 'Items' => $clean ] );
	}

	// =========================================================================
	// Webhook Management
	// =========================================================================

	/**
	 * List all webhook configurations.
	 * GET /api/v4/webhooks
	 *
	 * @param int $page
	 * @param int $per_page
	 * @return array|WP_Error
	 */
	public function get_webhooks( $page = 1, $per_page = 50 ) {
		return $this->request( 'GET', '/webhooks', [], [
			'Page'        => (int) $page,
			'RowsPerPage' => (int) $per_page,
		] );
	}

	/**
	 * Create a new webhook configuration.
	 * POST /api/v4/webhooks/create
	 *
	 * @param string $url
	 * @param array  $event_type_ids Default [1,2,7]
	 * @param int    $type_id        Default 1
	 * @return array|WP_Error
	 */
	public function create_webhook( $url, array $event_type_ids = [ 1, 2, 7 ], $type_id = 1 ) {
		return $this->request( 'POST', '/webhooks/create', [
			'Url'           => esc_url_raw( $url ),
			'EventTypeIds'  => array_map( 'intval', $event_type_ids ),
			'WebhookTypeId' => (int) $type_id,
		] );
	}

	/**
	 * Update an existing webhook.
	 * PUT /api/v4/webhooks/{WebhookId}
	 *
	 * @param int   $webhook_id
	 * @param array $data { url, event_type_ids, type_id, active }
	 * @return array|WP_Error
	 */
	public function update_webhook( $webhook_id, array $data ) {
		$payload = [];
		if ( isset( $data['url'] ) ) {
			$payload['Url'] = esc_url_raw( $data['url'] );
		}
		if ( isset( $data['event_type_ids'] ) ) {
			$payload['EventTypeIds'] = array_map( 'intval', $data['event_type_ids'] );
		}
		if ( isset( $data['type_id'] ) ) {
			$payload['WebhookTypeId'] = (int) $data['type_id'];
		}
		if ( isset( $data['active'] ) ) {
			$payload['Active'] = (bool) $data['active'];
		}
		return $this->request( 'PUT', '/webhooks/' . (int) $webhook_id, $payload );
	}

	/**
	 * Delete a webhook.
	 * DELETE /api/v4/webhooks/{WebhookId}
	 *
	 * @param int $webhook_id
	 * @return array|WP_Error
	 */
	public function delete_webhook( $webhook_id ) {
		return $this->request( 'DELETE', '/webhooks/' . (int) $webhook_id );
	}

	/**
	 * Get delivery history.
	 * GET /api/v4/webhooks/deliveries
	 *
	 * @param int      $page
	 * @param int      $per_page
	 * @param int|null $webhook_id Filter by webhook.
	 * @return array|WP_Error
	 */
	public function get_webhook_deliveries( $page = 1, $per_page = 10, $webhook_id = null ) {
		$query = [
			'Page'        => (int) $page,
			'RowsPerPage' => (int) $per_page,
		];
		if ( ! empty( $webhook_id ) ) {
			$query['WebhookId'] = (int) $webhook_id;
		}
		return $this->request( 'GET', '/webhooks/deliveries', [], $query );
	}
}

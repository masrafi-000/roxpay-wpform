<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Class RoxPay_Auth
 *
 * Obtains and caches a Bearer token from the RoxPay v4 auth endpoint:
 *   POST /api/v4/auth/token
 * Body: { "Username": "...", "Password": "..." }
 *
 * The token is cached in a WordPress transient for 50 minutes
 * (RoxPay tokens typically expire after 60 minutes).
 */
class RoxPay_Auth {

	const TRANSIENT_KEY = 'roxpay_bearer_token';
	const TOKEN_TTL     = 50 * MINUTE_IN_SECONDS;

	/** @var array Plugin settings. */
	private $settings;

	public function __construct() {
		$this->settings = get_option( 'roxpay_wpforms_settings', [] );
	}

	// -------------------------------------------------------------------------
	// Public API
	// -------------------------------------------------------------------------

	/**
	 * Return a valid Bearer token, using the transient cache when possible.
	 *
	 * @return string|WP_Error Token string or WP_Error on failure.
	 */
	public function get_token() {
		$cached = get_transient( self::TRANSIENT_KEY );
		if ( ! empty( $cached ) ) {
			return $cached;
		}
		return $this->fetch_new_token();
	}

	/**
	 * Return the RoxPay API base URL (without trailing slash).
	 *
	 * @return string
	 */
	public function get_base_url() {
		return 'https://app.roxpay.eu/api/v4';
	}

	/**
	 * Force-clear the cached token (e.g. after credential change).
	 */
	public static function invalidate_token() {
		delete_transient( self::TRANSIENT_KEY );
	}

	// -------------------------------------------------------------------------
	// Internal
	// -------------------------------------------------------------------------

	/**
	 * Authenticate with RoxPay and cache the returned token.
	 *
	 * @return string|WP_Error
	 */
	private function fetch_new_token() {
		$username = ! empty( $this->settings['username'] )
			? base64_decode( $this->settings['username'] )
			: '';
		$password = ! empty( $this->settings['password'] )
			? base64_decode( $this->settings['password'] )
			: '';

		if ( empty( $username ) || empty( $password ) ) {
			return new WP_Error( 'roxpay_no_credentials', esc_html__( 'RoxPay credentials are not configured.', 'roxpay-wpforms' ) );
		}

		$url      = $this->get_base_url() . '/auth/token';
		$response = wp_remote_post( $url, [
			'timeout'   => 20,
			'sslverify' => ! ( strpos( home_url(), 'localhost' ) !== false ),
			'headers'   => [
				'Content-Type' => 'application/json',
				'Accept'       => 'application/json',
			],
			'body'      => wp_json_encode( [
				'Username' => $username,
				'Password' => $password,
			] ),
		] );

		if ( is_wp_error( $response ) ) {
			$err_msg = $response->get_error_message();
			error_log( '[RoxPay WPForms] Auth request failed: ' . $err_msg );
			return new WP_Error( 'roxpay_auth_failed', esc_html__( 'Could not connect to RoxPay: ', 'roxpay-wpforms' ) . esc_html( $err_msg ) );
		}

		$http_code = wp_remote_retrieve_response_code( $response );
		$raw_body  = wp_remote_retrieve_body( $response );
		$body      = json_decode( $raw_body, true );

		if ( $http_code !== 200 || empty( $body ) ) {
			$msg = $body['Message'] ?? ( 'HTTP ' . $http_code );
			error_log( '[RoxPay WPForms] Auth failed: ' . $raw_body );
			return new WP_Error( 'roxpay_auth_error', esc_html( $msg ) );
		}

		// RoxPay may return the token under different keys.
		$token = $body['Token'] ?? $body['access_token'] ?? $body['BearerToken'] ?? $body['token'] ?? '';

		if ( empty( $token ) ) {
			error_log( '[RoxPay WPForms] Auth response has no token key. Body: ' . $raw_body );
			return new WP_Error( 'roxpay_no_token', esc_html__( 'No token returned by RoxPay.', 'roxpay-wpforms' ) );
		}

		set_transient( self::TRANSIENT_KEY, $token, self::TOKEN_TTL );
		return $token;
	}
}

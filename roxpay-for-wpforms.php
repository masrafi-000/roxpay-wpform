<?php
/**
 * Plugin Name: RoxPay for WPForms
 * Plugin URI:  https://travelservice-thailand.com
 * Description: Integrates RoxPay payment gateway with WPForms.
 * Version:     1.1.0
 * Author:      Blue Buff GmbH
 * Text Domain: roxpay-wpforms
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'ROXPAY_WPFORMS_VERSION', '1.1.0' );
define( 'ROXPAY_WPFORMS_DIR',     plugin_dir_path( __FILE__ ) );
define( 'ROXPAY_WPFORMS_URL',     plugin_dir_url( __FILE__ ) );

// DB class must load before the activation hook fires.
require_once ROXPAY_WPFORMS_DIR . 'includes/class-roxpay-db.php';

/**
 * Create / upgrade the custom DB table on activation.
 */
function roxpay_wpforms_activate() {
	RoxPay_DB::create_table();
}
register_activation_hook( __FILE__, 'roxpay_wpforms_activate' );

/**
 * Auto-upgrade table when the stored version is outdated.
 * dbDelta is idempotent, so this is safe on every request.
 */
function roxpay_wpforms_maybe_upgrade_db() {
	if ( get_option( RoxPay_DB::VERSION_KEY ) !== RoxPay_DB::VERSION ) {
		RoxPay_DB::create_table();
	}
}
add_action( 'plugins_loaded', 'roxpay_wpforms_maybe_upgrade_db', 5 );

/**
 * Boot all plugin classes after WPForms is available.
 */
function roxpay_wpforms_init() {
	if ( ! function_exists( 'wpforms' ) ) {
		add_action( 'admin_notices', 'roxpay_wpforms_missing_notice' );
		return;
	}

	require_once ROXPAY_WPFORMS_DIR . 'includes/class-roxpay-auth.php';
	require_once ROXPAY_WPFORMS_DIR . 'includes/class-roxpay-api.php';
	require_once ROXPAY_WPFORMS_DIR . 'includes/class-roxpay-payment.php';
	require_once ROXPAY_WPFORMS_DIR . 'includes/class-roxpay-webhook.php';
	require_once ROXPAY_WPFORMS_DIR . 'admin/class-roxpay-settings.php';
	require_once ROXPAY_WPFORMS_DIR . 'admin/class-roxpay-transactions.php';
	require_once ROXPAY_WPFORMS_DIR . 'admin/class-roxpay-notifications.php';

	new RoxPay_Settings();
	new RoxPay_WPForms_Payment();
	new RoxPay_Webhook();
	new RoxPay_Transactions();
	new RoxPay_Notifications();
}
add_action( 'plugins_loaded', 'roxpay_wpforms_init', 20 );

/**
 * Admin notice when WPForms is not active.
 */
function roxpay_wpforms_missing_notice() {
	printf(
		'<div class="notice notice-error is-dismissible"><p>%s</p></div>',
		esc_html__( 'RoxPay for WPForms requires WPForms to be installed and activated.', 'roxpay-wpforms' )
	);
}

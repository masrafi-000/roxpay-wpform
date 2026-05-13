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


/**
 * ─────────────────────────────────────────────────────────────────────────────
 * THAILAND DIGITAL ARRIVAL FORM INTEGRATION
 * ─────────────────────────────────────────────────────────────────────────────
 */

define( 'TDAF_PLUGIN_URL', ROXPAY_WPFORMS_URL );
define( 'TDAF_PLUGIN_PATH', ROXPAY_WPFORMS_DIR );

add_action( 'wp_enqueue_scripts', 'tdaf_enqueue_assets' );
function tdaf_enqueue_assets() {
    wp_enqueue_style( 'tdaf-style', TDAF_PLUGIN_URL . 'assets/output.css', [], ROXPAY_WPFORMS_VERSION );
    wp_enqueue_script( 'tdaf-script', TDAF_PLUGIN_URL . 'assets/form.js', [ 'jquery' ], ROXPAY_WPFORMS_VERSION, true );

    wp_localize_script( 'tdaf-script', 'tdaf_vars', [
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nonce'    => wp_create_nonce( 'tdaf_nonce' ),
    ] );
}

add_shortcode( 'thailand_arrival_form', 'tdaf_render_form' );
function tdaf_render_form() {
    ob_start();
    include TDAF_PLUGIN_PATH . 'form.php';
    return ob_get_clean();
}

/**
 * Handle custom form submission and RoxPay checkout.
 */
add_action( 'wp_ajax_tdaf_submit_form',        'tdaf_handle_form_submission' );
add_action( 'wp_ajax_nopriv_tdaf_submit_form', 'tdaf_handle_form_submission' );

function tdaf_handle_form_submission() {
    check_ajax_referer( 'tdaf_nonce', 'nonce' );

    $data      = $_POST;
    $service   = sanitize_text_field( $data['service'] ?? 'standard' );
    $wants_esim = ! empty( $data['esim'] );
    $travelers = isset( $data['travelers'] ) ? (array) $data['travelers'] : [];
    $count     = 1 + count( $travelers );

    // Pricing (match form.js)
    $prices = [
        'standard' => 59.99,
        'express'  => 79.99,
        'priority' => 109.99,
        'esim'     => 4.00,
    ];

    $unit_price = $prices[ $service ] ?? 59.99;
    if ( $wants_esim ) {
        $unit_price += $prices['esim'];
    }

    $total_usd    = $unit_price * $count;
    $amount_cents = (int) round( $total_usd * 100 );

    // Build title/description
    $first_name = sanitize_text_field( $data['first_name'] ?? '' );
    $last_name  = sanitize_text_field( $data['last_name'] ?? '' );
    $title      = "Thailand Digital Arrival Card - $first_name $last_name";
    $desc       = ucfirst($service) . " Processing for $count traveler(s)";
    if ($wants_esim) $desc .= " (inc. eSIM)";

    // Call RoxPay API
    if ( ! class_exists( 'RoxPay_Auth' ) ) {
        wp_send_json_error( 'RoxPay core not found.' );
    }

    $auth = new RoxPay_Auth();
    $api  = new RoxPay_API( $auth );

    $internal_id = 'TDAF-' . time() . '-' . mt_rand(100, 999);
    
    // Redirects
    $success_url = add_query_arg( 'roxpay_status', 'success', home_url('/') );
    $cancel_url  = add_query_arg( 'roxpay_status', 'cancel', home_url('/') );
    $failure_url = add_query_arg( 'roxpay_status', 'failure', home_url('/') );

    $result = $api->create_payment_link( [
        'amount'           => $amount_cents,
        'currency'         => 'EUR', 
        'title'            => $title,
        'description'      => $desc,
        'purpose'          => 'Digital Arrival Card',
        'transaction_id'   => $internal_id,
        'success_redirect' => $success_url,
        'failure_redirect' => $failure_url,
        'cancel_redirect'  => $cancel_url,
        'webhook_url'      => rest_url( 'roxpay-wpforms/v1/webhook' ), 
        'metadata'         => [
            'source'     => 'ThailandDigitalArrivalForm',
            'first_name' => $first_name,
            'last_name'  => $last_name,
            'email'      => sanitize_email( $data['email'] ?? '' ),
        ],
    ] );

    if ( is_wp_error( $result ) ) {
        wp_send_json_error( $result->get_error_message() );
    }

    // Log to shared DB
    if ( class_exists( 'RoxPay_DB' ) ) {
        $snapshot = [
            [ 'name' => 'Full Name', 'value' => "$first_name $last_name" ],
            [ 'name' => 'Email',     'value' => sanitize_email( $data['email'] ?? '' ) ],
            [ 'name' => 'Arrival',   'value' => sanitize_text_field( $data['arrival_date'] ?? '' ) ],
            [ 'name' => 'Passport',  'value' => sanitize_text_field( $data['passport_no'] ?? '' ) ],
            [ 'name' => 'Service',   'value' => $desc ],
        ];

        RoxPay_DB::insert( [
            'entry_id'        => 0, 
            'form_id'         => 0,
            'transaction_id'  => $result['TransactionId'] ?? '',
            'internal_txn_id' => $internal_id,
            'amount'          => $amount_cents,
            'currency'        => 'EUR',
            'status'          => 'pending',
            'form_snapshot'   => $snapshot,
        ] );
    }

    wp_send_json_success( [
        'payment_url' => $result['PaymentUrl']
    ] );
}

/**
 * Get list of countries for the nationality dropdown
 */
function tdaf_get_countries() {
    return [
        'AF' => 'Afghanistan', 'AL' => 'Albania', 'DZ' => 'Algeria', 'AS' => 'American Samoa', 'AD' => 'Andorra',
        'AO' => 'Angola', 'AI' => 'Anguilla', 'AQ' => 'Antarctica', 'AG' => 'Antigua and Barbuda', 'AR' => 'Argentina',
        'AM' => 'Armenia', 'AW' => 'Aruba', 'AU' => 'Australia', 'AT' => 'Austria', 'AZ' => 'Azerbaijan',
        'BS' => 'Bahamas', 'BH' => 'Bahrain', 'BD' => 'Bangladesh', 'BB' => 'Barbados', 'BY' => 'Belarus',
        'BE' => 'Belgium', 'BZ' => 'Belize', 'BJ' => 'Benin', 'BM' => 'Bermuda', 'BT' => 'Bhutan',
        'BO' => 'Bolivia', 'BA' => 'Bosnia and Herzegovina', 'BW' => 'Botswana', 'BR' => 'Brazil', 'BN' => 'Brunei',
        'BG' => 'Bulgaria', 'BF' => 'Burkina Faso', 'BI' => 'Burundi', 'KH' => 'Cambodia', 'CM' => 'Cameroon',
        'CA' => 'Canada', 'CV' => 'Cape Verde', 'KY' => 'Cayman Islands', 'CF' => 'Central African Republic', 'TD' => 'Chad',
        'CL' => 'Chile', 'CN' => 'China', 'CO' => 'Colombia', 'KM' => 'Comoros', 'CG' => 'Congo',
        'CD' => 'Congo, Democratic Republic of the', 'CK' => 'Cook Islands', 'CR' => 'Costa Rica', 'CI' => 'Cote d\'Ivoire', 'HR' => 'Croatia',
        'CU' => 'Cuba', 'CY' => 'Cyprus', 'CZ' => 'Czech Republic', 'DK' => 'Denmark', 'DJ' => 'Djibouti',
        'DM' => 'Dominica', 'DO' => 'Dominican Republic', 'EC' => 'Ecuador', 'EG' => 'Egypt', 'SV' => 'El Salvador',
        'GQ' => 'Equatorial Guinea', 'ER' => 'Eritrea', 'EE' => 'Estonia', 'ET' => 'Ethiopia', 'FJ' => 'Fiji',
        'FI' => 'Finland', 'FR' => 'France', 'GF' => 'French Guiana', 'PF' => 'French Polynesia', 'GA' => 'Gabon',
        'GM' => 'Gambia', 'GE' => 'Georgia', 'DE' => 'Germany', 'GH' => 'Ghana', 'GI' => 'Gibraltar',
        'GR' => 'Greece', 'GL' => 'Greenland', 'GD' => 'Grenada', 'GP' => 'Guadeloupe', 'GU' => 'Guam',
        'GT' => 'Guatemala', 'GN' => 'Guinea', 'GW' => 'Guinea-Bissau', 'GY' => 'Guyana', 'HT' => 'Haiti',
        'VA' => 'Holy See (Vatican City State)', 'HN' => 'Honduras', 'HK' => 'Hong Kong', 'HU' => 'Hungary', 'IS' => 'Iceland',
        'IN' => 'India', 'ID' => 'Indonesia', 'IR' => 'Iran', 'IQ' => 'Iraq', 'IE' => 'Ireland',
        'IL' => 'Israel', 'IT' => 'Italy', 'JM' => 'Jamaica', 'JP' => 'Japan', 'JO' => 'Jordan',
        'KZ' => 'Kazakhstan', 'KE' => 'Kenya', 'KI' => 'Kiribati', 'KP' => 'Korea, North', 'KR' => 'Korea, South',
        'KW' => 'Kuwait', 'KG' => 'Kyrgyzstan', 'LA' => 'Laos', 'LV' => 'Latvia', 'LB' => 'Lebanon',
        'LS' => 'Lesotho', 'LR' => 'Liberia', 'LY' => 'Libya', 'LI' => 'Liechtenstein', 'LT' => 'Lithuania',
        'LU' => 'Luxembourg', 'MO' => 'Macao', 'MK' => 'Macedonia', 'MG' => 'Madagascar', 'MW' => 'Malawi',
        'MY' => 'Malaysia', 'MV' => 'Maldives', 'ML' => 'Mali', 'MT' => 'Malta', 'MH' => 'Marshall Islands',
        'MQ' => 'Martinique', 'MR' => 'Mauritania', 'MU' => 'Mauritius', 'YT' => 'Mayotte', 'MX' => 'Mexico',
        'FM' => 'Micronesia', 'MD' => 'Moldova', 'MC' => 'Monaco', 'MN' => 'Mongolia', 'ME' => 'Montenegro',
        'MS' => 'Montserrat', 'MA' => 'Morocco', 'MZ' => 'Mozambique', 'MM' => 'Myanmar', 'NA' => 'Namibia',
        'NR' => 'Nauru', 'NP' => 'Nepal', 'NL' => 'Netherlands', 'NC' => 'New Caledonia', 'NZ' => 'New Zealand',
        'NI' => 'Nicaragua', 'NE' => 'Niger', 'NG' => 'Nigeria', 'NU' => 'Niue', 'NF' => 'Norfolk Island',
        'MP' => 'Northern Mariana Islands', 'NO' => 'Norway', 'OM' => 'Oman', 'PK' => 'Pakistan', 'PW' => 'Palau',
        'PS' => 'Palestine', 'PA' => 'Panama', 'PG' => 'Papua New Guinea', 'PY' => 'Paraguay', 'PE' => 'Peru',
        'PH' => 'Philippines', 'PN' => 'Pitcairn', 'PL' => 'Poland', 'PT' => 'Portugal', 'PR' => 'Puerto Rico',
        'QA' => 'Qatar', 'RE' => 'Reunion', 'RO' => 'Romania', 'RU' => 'Russia', 'RW' => 'Rwanda',
        'SH' => 'Saint Helena', 'KN' => 'Saint Kitts and Nevis', 'LC' => 'Saint Lucia', 'PM' => 'Saint Pierre and Miquelon', 'VC' => 'Saint Vincent and the Grenadines',
        'WS' => 'Samoa', 'SM' => 'San Marino', 'ST' => 'Sao Tome and Principe', 'SA' => 'Saudi Arabia', 'SN' => 'Senegal',
        'RS' => 'Serbia', 'SC' => 'Seychelles', 'SL' => 'Sierra Leone', 'SG' => 'Singapore', 'SK' => 'Slovakia',
        'SI' => 'Slovenia', 'SB' => 'Solomon Islands', 'SO' => 'Somalia', 'ZA' => 'South Africa', 'GS' => 'South Georgia and the South Sandwich Islands',
        'ES' => 'Spain', 'LK' => 'Sri Lanka', 'SD' => 'Sudan', 'SR' => 'Suriname', 'SJ' => 'Svalbard and Jan Mayen',
        'SZ' => 'Swaziland', 'SE' => 'Sweden', 'CH' => 'Switzerland', 'SY' => 'Syria', 'TW' => 'Taiwan',
        'TJ' => 'Tajikistan', 'TZ' => 'Tanzania', 'TH' => 'Thailand', 'TL' => 'Timor-Leste', 'TG' => 'Togo',
        'TK' => 'Tokelau', 'TO' => 'Tonga', 'TT' => 'Trinidad and Tobago', 'TN' => 'Tunisia', 'TR' => 'Turkey',
        'TM' => 'Turkmenistan', 'TC' => 'Turks and Caicos Islands', 'TV' => 'Tuvalu', 'UG' => 'Uganda', 'UA' => 'Ukraine',
        'AE' => 'United Arab Emirates', 'GB' => 'United Kingdom', 'US' => 'United States', 'UM' => 'United States Minor Outlying Islands', 'UY' => 'Uruguay',
        'UZ' => 'Uzbekistan', 'VU' => 'Vanuatu', 'VE' => 'Venezuela', 'VN' => 'Vietnam', 'VG' => 'Virgin Islands, British',
        'VI' => 'Virgin Islands, U.S.', 'WF' => 'Wallis and Futuna', 'EH' => 'Western Sahara', 'YE' => 'Yemen', 'ZM' => 'Zambia',
        'ZW' => 'Zimbabwe',
    ];
}

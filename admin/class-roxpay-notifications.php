<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Class RoxPay_Notifications
 *
 * Sends a formatted HTML email to the site admin when a RoxPay payment
 * is confirmed as completed or failed.
 *
 * Hooks:
 *   roxpay_payment_completed( $entry_id, $transaction_id, $webhook_data )
 *   roxpay_payment_failed( $entry_id, $transaction_id )
 */
class RoxPay_Notifications {

	public function __construct() {
		add_action( 'roxpay_payment_completed', [ $this, 'on_completed' ], 10, 3 );
		add_action( 'roxpay_payment_failed',    [ $this, 'on_failed' ],    10, 2 );
	}

	// -------------------------------------------------------------------------
	// Hooks
	// -------------------------------------------------------------------------

	public function on_completed( $entry_id, $transaction_id, $webhook_data ) {
		$record   = RoxPay_DB::get_by_entry( $entry_id );
		$amount   = (int) ( $webhook_data['Amount']   ?? $record['amount']   ?? 0 );
		$currency = sanitize_text_field( $webhook_data['Currency'] ?? $record['currency'] ?? 'EUR' );

		$subject = sprintf(
			'[%s] %s — %s %s',
			get_bloginfo( 'name' ),
			esc_html__( 'Payment Received', 'roxpay-wpforms' ),
			$currency,
			number_format( $amount / 100, 2 )
		);

		$this->send( $subject, $this->build_email( 'completed', $entry_id, $transaction_id, $amount, $currency, $record ) );
	}

	public function on_failed( $entry_id, $transaction_id ) {
		$record  = RoxPay_DB::get_by_entry( $entry_id );
		$subject = sprintf(
			'[%s] %s — Entry #%d',
			get_bloginfo( 'name' ),
			esc_html__( 'Payment Failed', 'roxpay-wpforms' ),
			$entry_id
		);

		$this->send( $subject, $this->build_email( 'failed', $entry_id, $transaction_id, $record['amount'] ?? 0, $record['currency'] ?? 'EUR', $record ) );
	}

	// -------------------------------------------------------------------------
	// Email builder
	// -------------------------------------------------------------------------

	private function build_email( $status, $entry_id, $transaction_id, $amount_cents, $currency, $record ) {
		$site_name  = get_bloginfo( 'name' );
		$site_url   = home_url();
		$entry_url  = admin_url( 'admin.php?page=wpforms-entries&view=details&entry_id=' . (int) $entry_id );
		$txn_url    = admin_url( 'admin.php?page=roxpay-payments' );
		$amount_fmt = $currency . ' ' . number_format( $amount_cents / 100, 2 );
		$date_fmt   = current_time( 'F j, Y \a\t g:i A' );
		$form_name  = '';

		if ( ! empty( $record['form_id'] ) && function_exists( 'wpforms' ) ) {
			$form      = wpforms()->form->get( (int) $record['form_id'] );
			$form_name = $form ? $form->post_title : '#' . $record['form_id'];
		}

		$colour      = ( $status === 'completed' ) ? '#00a32a' : '#d63638';
		$status_label = ( $status === 'completed' )
			? esc_html__( '✓ Payment Successful', 'roxpay-wpforms' )
			: esc_html__( '✗ Payment Failed',     'roxpay-wpforms' );

		// Build field rows from snapshot.
		$field_rows = '';
		if ( ! empty( $record['form_snapshot'] ) ) {
			$snapshot = json_decode( $record['form_snapshot'], true );
			if ( is_array( $snapshot ) ) {
				foreach ( $snapshot as $f ) {
					$name  = esc_html( $f['name']  ?? '' );
					$value = esc_html( $f['value'] ?? '—' );
					if ( empty( $name ) ) continue;
					$field_rows .= '<tr>
						<td style="padding:8px 12px;font-weight:600;color:#555;border-bottom:1px solid #eee;width:40%;">' . $name . '</td>
						<td style="padding:8px 12px;color:#333;border-bottom:1px solid #eee;">' . $value . '</td>
					</tr>';
				}
			}
		}

		ob_start(); ?>
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><title><?php echo esc_html( $status_label ); ?></title></head>
<body style="margin:0;padding:0;background:#f0f0f0;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f0f0f0;padding:30px 0;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.1);">

	<!-- Header -->
	<tr>
		<td style="background:<?php echo esc_attr( $colour ); ?>;padding:24px 32px;text-align:center;">
			<h1 style="margin:0;color:#fff;font-size:20px;"><?php echo esc_html( $status_label ); ?></h1>
			<p style="margin:6px 0 0;color:rgba(255,255,255,.85);font-size:14px;"><?php echo esc_html( $site_name ); ?></p>
		</td>
	</tr>

	<!-- Amount -->
	<tr>
		<td style="padding:24px 32px 0;">
			<table width="100%" cellpadding="0" cellspacing="0">
				<tr>
					<td style="background:#f9f9f9;border:1px solid #e5e5e5;border-radius:6px;padding:20px;text-align:center;">
						<div style="font-size:32px;font-weight:700;color:<?php echo esc_attr( $colour ); ?>;"><?php echo esc_html( $amount_fmt ); ?></div>
						<div style="font-size:13px;color:#888;margin-top:4px;"><?php esc_html_e( 'Amount', 'roxpay-wpforms' ); ?></div>
					</td>
				</tr>
			</table>
		</td>
	</tr>

	<!-- Details table -->
	<tr>
		<td style="padding:24px 32px;">
			<h2 style="font-size:15px;color:#333;margin:0 0 12px;"><?php esc_html_e( 'Payment Details', 'roxpay-wpforms' ); ?></h2>
			<table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e5e5e5;border-radius:4px;">
				<tr><td style="padding:8px 12px;font-weight:600;color:#555;border-bottom:1px solid #eee;width:40%;"><?php esc_html_e( 'Entry ID', 'roxpay-wpforms' ); ?></td><td style="padding:8px 12px;color:#333;border-bottom:1px solid #eee;">#<?php echo (int) $entry_id; ?></td></tr>
				<tr><td style="padding:8px 12px;font-weight:600;color:#555;border-bottom:1px solid #eee;"><?php esc_html_e( 'Form', 'roxpay-wpforms' ); ?></td><td style="padding:8px 12px;color:#333;border-bottom:1px solid #eee;"><?php echo esc_html( $form_name ); ?></td></tr>
				<tr><td style="padding:8px 12px;font-weight:600;color:#555;border-bottom:1px solid #eee;"><?php esc_html_e( 'RoxPay Transaction ID', 'roxpay-wpforms' ); ?></td><td style="padding:8px 12px;color:#333;border-bottom:1px solid #eee;font-family:monospace;"><?php echo esc_html( $transaction_id ?: '—' ); ?></td></tr>
				<tr><td style="padding:8px 12px;font-weight:600;color:#555;border-bottom:1px solid #eee;"><?php esc_html_e( 'Internal Reference', 'roxpay-wpforms' ); ?></td><td style="padding:8px 12px;color:#333;border-bottom:1px solid #eee;font-family:monospace;"><?php echo esc_html( $record['internal_txn_id'] ?? '—' ); ?></td></tr>
				<tr><td style="padding:8px 12px;font-weight:600;color:#555;"><?php esc_html_e( 'Date', 'roxpay-wpforms' ); ?></td><td style="padding:8px 12px;color:#333;"><?php echo esc_html( $date_fmt ); ?></td></tr>
			</table>
		</td>
	</tr>

	<?php if ( ! empty( $field_rows ) ) : ?>
	<!-- Submitted fields -->
	<tr>
		<td style="padding:0 32px 24px;">
			<h2 style="font-size:15px;color:#333;margin:0 0 12px;"><?php esc_html_e( 'Submitted Form Data', 'roxpay-wpforms' ); ?></h2>
			<table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e5e5e5;border-radius:4px;">
				<?php echo $field_rows; // Already escaped above. ?>
			</table>
		</td>
	</tr>
	<?php endif; ?>

	<!-- Action buttons -->
	<tr>
		<td style="padding:0 32px 28px;">
			<a href="<?php echo esc_url( $entry_url ); ?>" style="display:inline-block;background:#2271b1;color:#fff;padding:10px 20px;border-radius:4px;text-decoration:none;font-size:13px;margin-right:10px;"><?php esc_html_e( 'View Entry in WPForms', 'roxpay-wpforms' ); ?></a>
			<a href="<?php echo esc_url( $txn_url ); ?>" style="display:inline-block;background:#f6f7f7;color:#333;padding:10px 20px;border-radius:4px;text-decoration:none;font-size:13px;border:1px solid #ddd;"><?php esc_html_e( 'View All Payments', 'roxpay-wpforms' ); ?></a>
		</td>
	</tr>

	<!-- Footer -->
	<tr>
		<td style="background:#f6f7f7;padding:14px 32px;border-top:1px solid #eee;text-align:center;">
			<p style="margin:0;font-size:12px;color:#999;">
				<?php echo esc_html( $site_name ); ?> &mdash;
				<a href="<?php echo esc_url( $site_url ); ?>" style="color:#2271b1;"><?php echo esc_url( $site_url ); ?></a><br>
				<?php esc_html_e( 'Sent automatically by RoxPay for WPForms.', 'roxpay-wpforms' ); ?>
			</p>
		</td>
	</tr>

</table>
</td></tr>
</table>
</body>
</html>
		<?php
		return ob_get_clean();
	}

	// -------------------------------------------------------------------------
	// Send
	// -------------------------------------------------------------------------

	private function send( $subject, $html_body ) {
		$to      = get_option( 'admin_email' );
		$headers = [
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . get_bloginfo( 'name' ) . ' <' . $to . '>',
		];
		$sent = wp_mail( $to, $subject, $html_body, $headers );
		if ( ! $sent ) {
			error_log( '[RoxPay WPForms] Failed to send notification email to ' . $to );
		}
	}
}

<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Class RoxPay_Transactions
 *
 * Admin page under WPForms > RoxPay Payments.
 * Displays all payment records with revenue summary cards,
 * status filter tabs, and a modal to view submitted form fields.
 */
class RoxPay_Transactions {

	public function __construct() {
		add_action( 'admin_menu',            [ $this, 'register_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	public function register_menu() {
		// Main Top-Level Menu
		add_menu_page(
			esc_html__( 'RoxPay', 'roxpay-wpforms' ),
			esc_html__( 'RoxPay', 'roxpay-wpforms' ),
			'manage_options',
			'roxpay-payments',
			[ $this, 'render_page' ],
			'dashicons-credit-card',
			58 // Position it near WPForms
		);

		// Submenu: Payments (defaults to same as main)
		add_submenu_page(
			'roxpay-payments',
			esc_html__( 'All Payments', 'roxpay-wpforms' ),
			esc_html__( 'All Payments', 'roxpay-wpforms' ),
			'manage_options',
			'roxpay-payments',
			[ $this, 'render_page' ]
		);
		
		// Submenu: Booking Details (Hidden from sidebar by using empty title)
		add_submenu_page(
			'roxpay-payments',
			esc_html__( 'Booking Details', 'roxpay-wpforms' ),
			'', 
			'manage_options',
			'roxpay-booking-details',
			[ $this, 'render_booking_details' ]
		);
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'roxpay-wpforms' ) );
		}

		$status_filter = sanitize_text_field( $_GET['status'] ?? '' );
		$page_num      = max( 1, absint( $_GET['paged'] ?? 1 ) );
		$per_page      = 20;

		$result      = RoxPay_DB::get_payments( [ 'page' => $page_num, 'per_page' => $per_page, 'status' => $status_filter ] );
		$payments    = $result['payments'];
		$total       = $result['total'];
		$total_pages = $result['total_pages'];
		$counts      = RoxPay_DB::get_status_counts();
		$revenue     = RoxPay_DB::get_total_revenue();
		$base_url    = admin_url( 'admin.php?page=roxpay-payments' );
		?>
		<div class="wrap roxpay-txn-wrap">

			<h1><?php esc_html_e( 'RoxPay Payments', 'roxpay-wpforms' ); ?></h1>

			<?php /* ── Summary cards ── */ ?>
			<div class="roxpay-summary-cards">
				<div class="roxpay-card">
					<span class="roxpay-card-value">€<?php echo number_format( $revenue / 100, 2 ); ?></span>
					<span class="roxpay-card-label"><?php esc_html_e( 'Total Revenue', 'roxpay-wpforms' ); ?></span>
				</div>
				<div class="roxpay-card">
					<span class="roxpay-card-value"><?php echo (int) $counts['completed']; ?></span>
					<span class="roxpay-card-label"><?php esc_html_e( 'Completed', 'roxpay-wpforms' ); ?></span>
				</div>
				<div class="roxpay-card warning">
					<span class="roxpay-card-value"><?php echo (int) $counts['pending']; ?></span>
					<span class="roxpay-card-label"><?php esc_html_e( 'Pending', 'roxpay-wpforms' ); ?></span>
				</div>
				<div class="roxpay-card danger">
					<span class="roxpay-card-value"><?php echo (int) $counts['failed']; ?></span>
					<span class="roxpay-card-label"><?php esc_html_e( 'Failed', 'roxpay-wpforms' ); ?></span>
				</div>
			</div>

			<?php /* ── Status filter tabs ── */ ?>
			<ul class="subsubsub">
				<?php
				$tabs     = [
					''          => esc_html__( 'All',       'roxpay-wpforms' ),
					'pending'   => esc_html__( 'Pending',   'roxpay-wpforms' ),
					'completed' => esc_html__( 'Completed', 'roxpay-wpforms' ),
					'failed'    => esc_html__( 'Failed',    'roxpay-wpforms' ),
				];
				$last_key = array_key_last( $tabs );
				foreach ( $tabs as $key => $label ) :
					$active = ( $status_filter === $key ) ? 'current' : '';
					$count  = ( $key === '' ) ? $counts['all'] : ( $counts[ $key ] ?? 0 );
					$url    = ( $key === '' ) ? $base_url : add_query_arg( 'status', $key, $base_url );
					$sep    = ( $key !== $last_key ) ? ' | ' : '';
					?>
					<li>
						<a href="<?php echo esc_url( $url ); ?>" class="<?php echo esc_attr( $active ); ?>">
							<?php echo esc_html( $label ); ?>
							<span class="count">(<?php echo (int) $count; ?>)</span>
						</a><?php echo esc_html( $sep ); ?>
					</li>
				<?php endforeach; ?>
			</ul>

			<?php /* ── Table ── */ ?>
			<table class="wp-list-table widefat fixed striped roxpay-payments-table">
				<thead>
					<tr>
						<th style="width:40px"><?php esc_html_e( '#', 'roxpay-wpforms' ); ?></th>
						<th><?php esc_html_e( 'Source', 'roxpay-wpforms' ); ?></th>
						<th><?php esc_html_e( 'Entry', 'roxpay-wpforms' ); ?></th>
						<th><?php esc_html_e( 'Form Name', 'roxpay-wpforms' ); ?></th>
						<th><?php esc_html_e( 'Amount', 'roxpay-wpforms' ); ?></th>
						<th><?php esc_html_e( 'RoxPay ID', 'roxpay-wpforms' ); ?></th>
						<th><?php esc_html_e( 'Internal Ref', 'roxpay-wpforms' ); ?></th>
						<th><?php esc_html_e( 'Status', 'roxpay-wpforms' ); ?></th>
						<th><?php esc_html_e( 'Date', 'roxpay-wpforms' ); ?></th>
						<th><?php esc_html_e( 'Data', 'roxpay-wpforms' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $payments ) ) : ?>
						<tr>
							<td colspan="10" style="text-align:center;padding:30px 0;">
								<?php esc_html_e( 'No payment records found.', 'roxpay-wpforms' ); ?>
							</td>
						</tr>
					<?php else : ?>
						<?php foreach ( $payments as $p ) :
							$is_wpf    = ( absint( $p['entry_id'] ) > 0 );
							$entry_url = $is_wpf ? admin_url( 'admin.php?page=wpforms-entries&view=details&entry_id=' . absint( $p['entry_id'] ) ) : '';
							
							$form_name = '—';
							if ( $is_wpf && function_exists( 'wpforms' ) ) {
								$form = wpforms()->form->get( (int) $p['form_id'] );
								$form_name = $form ? esc_html( $form->post_title ) : '#' . absint( $p['form_id'] );
							} else {
								$form_name = '<em>' . esc_html__( 'Digital Arrival Form', 'roxpay-wpforms' ) . '</em>';
							}

							$source_badge = $is_wpf 
								? '<span class="roxpay-badge" style="background:#e8f5fb;color:#0366d6;">WPForms</span>' 
								: '<span class="roxpay-badge" style="background:#f1f8e9;color:#2e7d32;">Direct</span>';

							$amount    = (int) $p['amount'] > 0
								? esc_html( $p['currency'] ) . ' ' . number_format( $p['amount'] / 100, 2 )
								: '—';
							$snapshot  = ! empty( $p['form_snapshot'] ) ? json_decode( $p['form_snapshot'], true ) : [];
						?>
						<tr>
							<td><?php echo absint( $p['id'] ); ?></td>
							<td><?php echo $source_badge; // phpcs:ignore ?></td>
							<td>
								<?php if ( $is_wpf ) : ?>
									<a href="<?php echo esc_url( $entry_url ); ?>">#<?php echo absint( $p['entry_id'] ); ?></a>
								<?php else : ?>
									<span class="roxpay-muted"><?php esc_html_e( 'N/A', 'roxpay-wpforms' ); ?></span>
								<?php endif; ?>
							</td>
							<td><?php echo $form_name; // phpcs:ignore ?></td>
							<td><strong><?php echo esc_html( $amount ); ?></strong></td>
							<td><?php echo ! empty( $p['transaction_id'] )
								? '<code>' . esc_html( $p['transaction_id'] ) . '</code>'
								: '<span class="roxpay-muted">—</span>'; ?></td>
							<td><small><?php echo esc_html( $p['internal_txn_id'] ?: '—' ); ?></small></td>
							<td><?php echo $this->status_badge( $p['status'] ); // phpcs:ignore ?></td>
							<td><small><?php echo esc_html( $p['created_at'] ); ?></small></td>
							<td>
								<?php if ( ! empty( $snapshot ) || $is_wpf ) : ?>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=roxpay-booking-details&id=' . absint( $p['id'] ) ) ); ?>" class="button button-small">
										<?php esc_html_e( 'View Booking', 'roxpay-wpforms' ); ?>
									</a>
								<?php endif; ?>
							</td>
						</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<?php /* ── Pagination ── */ ?>
			<?php if ( $total_pages > 1 ) : ?>
				<div class="tablenav bottom">
					<div class="tablenav-pages">
						<span class="displaying-num">
							<?php printf(
								esc_html( _n( '%s item', '%s items', $total, 'roxpay-wpforms' ) ),
								number_format_i18n( $total )
							); ?>
						</span>
						<span class="pagination-links">
							<?php
							$page_base = add_query_arg( array_filter( [ 'status' => $status_filter ] ), $base_url );
							if ( $page_num > 1 ) {
								echo '<a class="prev-page button" href="' . esc_url( add_query_arg( 'paged', $page_num - 1, $page_base ) ) . '">‹</a> ';
							}
							printf( esc_html__( 'Page %1$d of %2$d', 'roxpay-wpforms' ), $page_num, $total_pages );
							if ( $page_num < $total_pages ) {
								echo ' <a class="next-page button" href="' . esc_url( add_query_arg( 'paged', $page_num + 1, $page_base ) ) . '">›</a>';
							}
							?>
						</span>
					</div>
				</div>
			<?php endif; ?>

		</div><!-- .roxpay-txn-wrap -->

		<?php
	}

	/**
	 * Render the dedicated Booking Details page.
	 */
	public function render_booking_details() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'roxpay-wpforms' ) );
		}

		$id      = absint( $_GET['id'] ?? 0 );
		$payment = RoxPay_DB::get_by_id( $id );

		if ( ! $payment ) {
			wp_die( esc_html__( 'Booking not found.', 'roxpay-wpforms' ) );
		}

		$amount   = $payment['currency'] . ' ' . number_format( $payment['amount'] / 100, 2 );
		$snapshot = ! empty( $payment['form_snapshot'] ) ? json_decode( $payment['form_snapshot'], true ) : [];
		$date     = date_i18n( 'M j, Y g:i A', strtotime( $payment['created_at'] ) );
		$status   = ucfirst( $payment['status'] );
		$source   = $payment['entry_id'] > 0 ? 'WPForms' : 'Digital Arrival Form';
		?>
		<div class="wrap roxpay-details-wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Booking Details', 'roxpay-wpforms' ); ?></h1>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=roxpay-payments' ) ); ?>" class="page-title-action">
				<?php esc_html_e( '‹ Back to All Payments', 'roxpay-wpforms' ); ?>
			</a>
			<hr class="wp-header-end">

			<div style="margin-top:20px; display:grid; grid-template-columns: 1fr 300px; gap:20px; align-items: start;">
				
				<!-- Main Content: Form Data -->
				<div class="postbox">
					<div class="postbox-header">
						<h2 class="hndle"><?php esc_html_e( 'Submitted Form Information', 'roxpay-wpforms' ); ?></h2>
					</div>
					<div class="inside" style="padding:0; margin:0;">
						<table class="wp-list-table widefat striped" style="border:none; box-shadow:none;">
							<thead>
								<tr>
									<th style="width:30%; padding:12px 15px;"><?php esc_html_e( 'Field Name', 'roxpay-wpforms' ); ?></th>
									<th style="padding:12px 15px;"><?php esc_html_e( 'Value', 'roxpay-wpforms' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php if ( ! empty( $snapshot ) && is_array( $snapshot ) ) : ?>
									<?php foreach ( $snapshot as $field ) : ?>
										<tr>
											<td style="padding:12px 15px;"><strong><?php echo esc_html( $field['name'] ?? '—' ); ?></strong></td>
											<td style="padding:12px 15px;">
												<?php 
												$val = $field['value'] ?? '—';
												if ( ! empty( $field['is_image'] ) && strpos( $val, 'http' ) === 0 ) : ?>
													<a href="<?php echo esc_url( $val ); ?>" target="_blank">
														<img src="<?php echo esc_url( $val ); ?>" style="max-width:300px; max-height:400px; border-radius:8px; border:1px solid #ddd; box-shadow:0 2px 5px rgba(0,0,0,0.1);">
													</a>
												<?php else : ?>
													<?php echo nl2br( esc_html( $val ) ); ?>
												<?php endif; ?>
											</td>
										</tr>
									<?php endforeach; ?>
								<?php else : ?>
									<tr>
										<td colspan="2" style="padding:20px; text-align:center; color:#666;">
											<?php esc_html_e( 'No form data captured for this transaction.', 'roxpay-wpforms' ); ?>
										</td>
									</tr>
								<?php endif; ?>
							</tbody>
						</table>
					</div>
				</div>

				<!-- Sidebar: Payment Summary -->
				<div class="roxpay-sidebar-meta">
					<div class="postbox">
						<div class="postbox-header"><h2 class="hndle"><?php esc_html_e( 'Payment Summary', 'roxpay-wpforms' ); ?></h2></div>
						<div class="inside" style="padding:15px;">
							<div style="margin-bottom:15px;">
								<label style="font-size:11px; text-transform:uppercase; font-weight:bold; color:#666; display:block; margin-bottom:4px;"><?php esc_html_e( 'Total Amount', 'roxpay-wpforms' ); ?></label>
								<span style="font-size:24px; font-weight:bold; color:#2271b1;"><?php echo esc_html( $amount ); ?></span>
							</div>
							
							<div style="margin-bottom:15px; padding-top:15px; border-top:1px solid #eee;">
								<label style="font-size:11px; text-transform:uppercase; font-weight:bold; color:#666; display:block; margin-bottom:4px;"><?php esc_html_e( 'Status', 'roxpay-wpforms' ); ?></label>
								<?php echo $this->status_badge( $payment['status'] ); // phpcs:ignore ?>
							</div>

							<div style="margin-bottom:15px; padding-top:15px; border-top:1px solid #eee;">
								<label style="font-size:11px; text-transform:uppercase; font-weight:bold; color:#666; display:block; margin-bottom:4px;"><?php esc_html_e( 'Transaction ID', 'roxpay-wpforms' ); ?></label>
								<code style="word-break:break-all; font-size:12px;"><?php echo esc_html( $payment['transaction_id'] ?: '—' ); ?></code>
							</div>

							<div style="margin-bottom:15px; padding-top:15px; border-top:1px solid #eee;">
								<label style="font-size:11px; text-transform:uppercase; font-weight:bold; color:#666; display:block; margin-bottom:4px;"><?php esc_html_e( 'Internal Reference', 'roxpay-wpforms' ); ?></label>
								<span style="font-size:12px;"><?php echo esc_html( $payment['internal_txn_id'] ); ?></span>
							</div>

							<div style="margin-bottom:0; padding-top:15px; border-top:1px solid #eee;">
								<label style="font-size:11px; text-transform:uppercase; font-weight:bold; color:#666; display:block; margin-bottom:4px;"><?php esc_html_e( 'Source', 'roxpay-wpforms' ); ?></label>
								<span class="roxpay-badge" style="background:#f0f0f1; color:#2c3338; border:1px solid #c3c4c7;"><?php echo esc_html( $source ); ?></span>
							</div>
						</div>
					</div>

					<div class="postbox">
						<div class="postbox-header"><h2 class="hndle"><?php esc_html_e( 'Timeline', 'roxpay-wpforms' ); ?></h2></div>
						<div class="inside" style="padding:15px; font-size:12px; color:#555;">
							<p><strong><?php esc_html_e( 'Created:', 'roxpay-wpforms' ); ?></strong><br><?php echo esc_html( $date ); ?></p>
							<?php if ( $payment['updated_at'] !== $payment['created_at'] ) : ?>
								<p><strong><?php esc_html_e( 'Last Updated:', 'roxpay-wpforms' ); ?></strong><br><?php echo esc_html( date_i18n( 'M j, Y g:i A', strtotime( $payment['updated_at'] ) ) ); ?></p>
							<?php endif; ?>
						</div>
					</div>
				</div>

			</div>
		</div>
		<?php
	}

	/** Coloured status badge. */
	private function status_badge( $status ) {
		$map = [
			'completed' => [ 'active',   esc_html__( 'Completed', 'roxpay-wpforms' ) ],
			'pending'   => [ 'pending',  esc_html__( 'Pending',   'roxpay-wpforms' ) ],
			'failed'    => [ 'inactive', esc_html__( 'Failed',    'roxpay-wpforms' ) ],
		];
		[ $cls, $label ] = $map[ $status ] ?? [ 'pending', esc_html( ucfirst( $status ) ) ];
		return '<span class="roxpay-badge ' . esc_attr( $cls ) . '">' . $label . '</span>';
	}

	public function enqueue_assets( $hook ) {
		if ( strpos( $hook, 'roxpay-payments' ) === false ) {
			return;
		}
		wp_enqueue_style( 'roxpay-admin-style', ROXPAY_WPFORMS_URL . 'assets/admin.css', [], ROXPAY_WPFORMS_VERSION );
	}
}

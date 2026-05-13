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
		add_submenu_page(
			'wpforms-overview',
			esc_html__( 'RoxPay Payments', 'roxpay-wpforms' ),
			esc_html__( 'RoxPay Payments', 'roxpay-wpforms' ),
			'manage_options',
			'roxpay-payments',
			[ $this, 'render_page' ]
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
						<th><?php esc_html_e( 'Entry', 'roxpay-wpforms' ); ?></th>
						<th><?php esc_html_e( 'Form', 'roxpay-wpforms' ); ?></th>
						<th><?php esc_html_e( 'Amount', 'roxpay-wpforms' ); ?></th>
						<th><?php esc_html_e( 'RoxPay Transaction ID', 'roxpay-wpforms' ); ?></th>
						<th><?php esc_html_e( 'Internal Ref', 'roxpay-wpforms' ); ?></th>
						<th><?php esc_html_e( 'Status', 'roxpay-wpforms' ); ?></th>
						<th><?php esc_html_e( 'Date (UTC)', 'roxpay-wpforms' ); ?></th>
						<th><?php esc_html_e( 'Details', 'roxpay-wpforms' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $payments ) ) : ?>
						<tr>
							<td colspan="9" style="text-align:center;padding:30px 0;">
								<?php esc_html_e( 'No payment records found.', 'roxpay-wpforms' ); ?>
							</td>
						</tr>
					<?php else : ?>
						<?php foreach ( $payments as $p ) :
							$entry_url = admin_url( 'admin.php?page=wpforms-entries&view=details&entry_id=' . absint( $p['entry_id'] ) );
							$form      = function_exists( 'wpforms' ) ? wpforms()->form->get( (int) $p['form_id'] ) : null;
							$form_name = $form ? esc_html( $form->post_title ) : '#' . absint( $p['form_id'] );
							$amount    = (int) $p['amount'] > 0
								? esc_html( $p['currency'] ) . ' ' . number_format( $p['amount'] / 100, 2 )
								: '—';
							$snapshot  = ! empty( $p['form_snapshot'] ) ? json_decode( $p['form_snapshot'], true ) : [];
						?>
						<tr>
							<td><?php echo absint( $p['id'] ); ?></td>
							<td><a href="<?php echo esc_url( $entry_url ); ?>">#<?php echo absint( $p['entry_id'] ); ?></a></td>
							<td><?php echo esc_html( $form_name ); ?></td>
							<td><strong><?php echo esc_html( $amount ); ?></strong></td>
							<td><?php echo ! empty( $p['transaction_id'] )
								? '<code>' . esc_html( $p['transaction_id'] ) . '</code>'
								: '<span class="roxpay-muted">—</span>'; ?>
							</td>
							<td><small><?php echo esc_html( $p['internal_txn_id'] ?: '—' ); ?></small></td>
							<td><?php echo $this->status_badge( $p['status'] ); // phpcs:ignore ?></td>
							<td><small><?php echo esc_html( $p['created_at'] ); ?></small></td>
							<td>
								<?php if ( ! empty( $snapshot ) ) : ?>
									<button type="button" class="button button-small roxpay-view-snapshot"
										data-snapshot="<?php echo esc_attr( wp_json_encode( $snapshot ) ); ?>">
										<?php esc_html_e( 'View Fields', 'roxpay-wpforms' ); ?>
									</button>
								<?php else : ?>
									<a href="<?php echo esc_url( $entry_url ); ?>" class="button button-small">
										<?php esc_html_e( 'View Entry', 'roxpay-wpforms' ); ?>
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

		<?php /* ── Field snapshot modal ── */ ?>
		<div id="roxpay-snapshot-modal" style="display:none;">
			<div id="roxpay-snapshot-overlay"></div>
			<div id="roxpay-snapshot-dialog">
				<h2><?php esc_html_e( 'Submitted Form Fields', 'roxpay-wpforms' ); ?></h2>
				<table class="wp-list-table widefat striped" id="roxpay-snapshot-table"></table>
				<p style="margin-top:14px;">
					<button type="button" class="button button-primary" id="roxpay-snapshot-close">
						<?php esc_html_e( 'Close', 'roxpay-wpforms' ); ?>
					</button>
				</p>
			</div>
		</div>

		<script>
		(function($){
			$('.roxpay-view-snapshot').on('click', function(){
				var raw = $(this).data('snapshot');
				var fields = (typeof raw === 'string') ? (function(){ try{ return JSON.parse(raw); }catch(e){ return []; } })() : raw;
				var html = '<thead><tr><th><?php esc_html_e( 'Field', 'roxpay-wpforms' ); ?></th><th><?php esc_html_e( 'Value', 'roxpay-wpforms' ); ?></th></tr></thead><tbody>';
				if (Array.isArray(fields)) {
					$.each(fields, function(i, f){
						html += '<tr><td><strong>' + $('<span>').text(f.name||'').html() + '</strong></td><td>' + $('<span>').text(f.value||'—').html() + '</td></tr>';
					});
				} else {
					$.each(fields, function(k, v){
						html += '<tr><td><strong>' + $('<span>').text(k).html() + '</strong></td><td>' + $('<span>').text(v||'—').html() + '</td></tr>';
					});
				}
				html += '</tbody>';
				$('#roxpay-snapshot-table').html(html);
				$('#roxpay-snapshot-modal').show();
			});
			$('#roxpay-snapshot-close, #roxpay-snapshot-overlay').on('click', function(){
				$('#roxpay-snapshot-modal').hide();
			});
		}(jQuery));
		</script>
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

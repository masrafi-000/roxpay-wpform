<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Class RoxPay_Settings
 *
 * Registers the RoxPay tab inside WPForms > Settings.
 * Sections:
 *   1. API Credentials (username / password)
 *   2. Redirect URLs (success / failure / cancel)
 *   3. Test Connection button
 *   4. Webhook Management (list, auto-register, delete, deliveries)
 */
class RoxPay_Settings {

	public function __construct() {
		add_action( 'admin_menu',                        [ $this, 'register_submenu' ], 20 ); // Priority 20 to run AFTER main menu
		add_action( 'admin_enqueue_scripts',             [ $this, 'enqueue_assets' ] );
		add_action( 'admin_post_roxpay_test_connection', [ $this, 'handle_test_connection' ] );
		add_action( 'admin_post_roxpay_save_standalone', [ $this, 'handle_standalone_save' ] );
		// Webhook management AJAX.
		add_action( 'wp_ajax_roxpay_load_webhooks',    [ $this, 'ajax_load_webhooks' ] );
		add_action( 'wp_ajax_roxpay_register_webhook', [ $this, 'ajax_register_webhook' ] );
		add_action( 'wp_ajax_roxpay_delete_webhook',   [ $this, 'ajax_delete_webhook' ] );
		add_action( 'wp_ajax_roxpay_load_deliveries',  [ $this, 'ajax_load_deliveries' ] );
	}

	public function register_submenu() {
		add_submenu_page(
			'roxpay-payments',
			esc_html__( 'Settings', 'roxpay-wpforms' ),
			esc_html__( 'Settings', 'roxpay-wpforms' ),
			'manage_options',
			'roxpay-settings',
			[ $this, 'render_standalone_page' ]
		);
	}

	public function render_standalone_page() {
		$saved = isset( $_GET['saved'] ) && $_GET['saved'] === '1';
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'RoxPay Settings', 'roxpay-wpforms' ); ?></h1>
			
			<?php if ( $saved ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved successfully.', 'roxpay-wpforms' ); ?></p></div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'roxpay_standalone_settings', 'roxpay_standalone_nonce' ); ?>
				<input type="hidden" name="action" value="roxpay_save_standalone">
				<?php $this->render_fields( null ); ?>
				<p class="submit">
					<input type="submit" name="submit" id="submit" class="button button-primary" value="<?php esc_attr_e( 'Save Settings', 'roxpay-wpforms' ); ?>">
				</p>
			</form>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Render
	// -------------------------------------------------------------------------

	public function render_fields( $instance ) {
		// Render if we are on the WPForms tab OR the standalone page
		$is_wpf_tab    = ( isset( $_GET['page'] ) && $_GET['page'] === 'wpforms-settings' && isset( $_GET['view'] ) && $_GET['view'] === 'roxpay' );
		$is_standalone = ( isset( $_GET['page'] ) && $_GET['page'] === 'roxpay-settings' );

		if ( ! $is_wpf_tab && ! $is_standalone ) {
			return;
		}

		$settings    = get_option( 'roxpay_wpforms_settings', [] );
		$test_result = get_transient( 'roxpay_test_result' );
		if ( $test_result ) {
			delete_transient( 'roxpay_test_result' );
		}
		?>
		<div class="roxpay-settings-wrap">

			<?php /* ── Credentials ── */ ?>
			<div class="roxpay-field-group">
				<h3><?php esc_html_e( 'API Credentials', 'roxpay-wpforms' ); ?></h3>
				<p class="description"><?php esc_html_e( 'Your RoxPay merchant username and password.', 'roxpay-wpforms' ); ?></p>

				<table class="form-table" role="presentation">
					<tr>
						<th><?php esc_html_e( 'Username', 'roxpay-wpforms' ); ?></th>
						<td>
							<input type="text" id="roxpay_username" name="roxpay_settings[username_plain]"
								value="<?php echo esc_attr( ! empty( $settings['username'] ) ? base64_decode( $settings['username'] ) : '' ); ?>"
								class="regular-text" autocomplete="off">
							<p class="description"><?php esc_html_e( 'Your RoxPay merchant username.', 'roxpay-wpforms' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Password', 'roxpay-wpforms' ); ?></th>
						<td>
							<input type="password" id="roxpay_password" name="roxpay_settings[password_plain]"
								value="<?php echo esc_attr( ! empty( $settings['password'] ) ? base64_decode( $settings['password'] ) : '' ); ?>"
								class="regular-text" autocomplete="new-password">
							<p class="description"><?php esc_html_e( 'Your RoxPay merchant password.', 'roxpay-wpforms' ); ?></p>
						</td>
					</tr>
				</table>
			</div>

			<?php /* ── Redirect URLs ── */ ?>
			<div class="roxpay-field-group">
				<h3><?php esc_html_e( 'Redirect URLs', 'roxpay-wpforms' ); ?></h3>
				<p class="description"><?php esc_html_e( 'Override redirect destinations after payment. Leave blank to use the form\'s confirmation settings.', 'roxpay-wpforms' ); ?></p>

				<table class="form-table" role="presentation">
					<?php foreach ( [
						'success_redirect_url' => esc_html__( 'Success URL', 'roxpay-wpforms' ),
						'failure_redirect_url' => esc_html__( 'Failure URL', 'roxpay-wpforms' ),
						'cancel_redirect_url'  => esc_html__( 'Cancel URL',  'roxpay-wpforms' ),
					] as $key => $label ) : ?>
					<tr>
						<th><?php echo esc_html( $label ); ?></th>
						<td>
							<input type="url" name="roxpay_settings[<?php echo esc_attr( $key ); ?>]"
								value="<?php echo esc_attr( $settings[ $key ] ?? '' ); ?>"
								class="regular-text" placeholder="https://">
						</td>
					</tr>
					<?php endforeach; ?>
				</table>
			</div>

			<?php /* ── Test Connection ── */ ?>
			<div class="roxpay-field-group">
				<h3><?php esc_html_e( 'Test Connection', 'roxpay-wpforms' ); ?></h3>
				<div class="roxpay-test-connection-row">
					<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=roxpay_test_connection' ), 'roxpay_test_connection', 'roxpay_test_nonce' ) ); ?>" class="button">
						<?php esc_html_e( 'Test API Connection', 'roxpay-wpforms' ); ?>
					</a>

					<?php if ( $test_result === 'success' ) : ?>
						<span class="roxpay-test-result success"><?php esc_html_e( '✓ Connection successful!', 'roxpay-wpforms' ); ?></span>
					<?php elseif ( ! empty( $test_result ) && str_starts_with( $test_result, 'error:' ) ) : ?>
						<span class="roxpay-test-result error"><?php echo esc_html( substr( $test_result, 7 ) ); ?></span>
					<?php endif; ?>
				</div>
			</div>

			<hr>

			<?php /* ── Webhook Management ── */ ?>
			<div class="roxpay-field-group" id="roxpay-webhook-manager">
				<h3><?php esc_html_e( 'Webhook Management', 'roxpay-wpforms' ); ?></h3>
				<p class="description"><?php esc_html_e( 'Register this site\'s webhook endpoint with RoxPay so payment notifications are delivered automatically.', 'roxpay-wpforms' ); ?></p>

				<div class="roxpay-webhook-url-row">
					<strong><?php esc_html_e( 'Your Webhook URL:', 'roxpay-wpforms' ); ?></strong>
					<code id="roxpay-webhook-endpoint"><?php echo esc_html( rest_url( 'roxpay-wpforms/v1/webhook' ) ); ?></code>
					<button type="button" class="button" id="roxpay-copy-webhook-url"><?php esc_html_e( 'Copy', 'roxpay-wpforms' ); ?></button>
				</div>

				<div class="roxpay-webhook-actions">
					<button type="button" class="button button-primary" id="roxpay-register-webhook">
						<?php esc_html_e( 'Auto-Register Webhook with RoxPay', 'roxpay-wpforms' ); ?>
					</button>
					<button type="button" class="button" id="roxpay-refresh-webhooks">
						<?php esc_html_e( '↻ Refresh List', 'roxpay-wpforms' ); ?>
					</button>
					<span id="roxpay-webhook-action-msg" class="roxpay-inline-msg"></span>
				</div>

				<div id="roxpay-webhook-list-wrap">
					<p class="roxpay-loading-hint"><?php esc_html_e( 'Loading webhooks…', 'roxpay-wpforms' ); ?></p>
				</div>

				<div id="roxpay-deliveries-wrap" style="display:none;">
					<h4><?php esc_html_e( 'Recent Deliveries', 'roxpay-wpforms' ); ?> <span id="roxpay-deliveries-for"></span></h4>
					<div id="roxpay-deliveries-list"></div>
					<button type="button" class="button" id="roxpay-close-deliveries"><?php esc_html_e( '✕ Close', 'roxpay-wpforms' ); ?></button>
				</div>
			</div>

		</div><!-- .roxpay-settings-wrap -->

		<script>
		(function($){
			var nonce   = '<?php echo wp_create_nonce( 'roxpay_webhook_mgmt' ); ?>';
			var ajaxUrl = '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>';

			/* Copy URL */
			$('#roxpay-copy-webhook-url').on('click', function(){
				var url = $('#roxpay-webhook-endpoint').text();
				navigator.clipboard.writeText(url).then(function(){
					var btn = $('#roxpay-copy-webhook-url');
					btn.text('<?php esc_html_e( 'Copied!', 'roxpay-wpforms' ); ?>');
					setTimeout(function(){ btn.text('<?php esc_html_e( 'Copy', 'roxpay-wpforms' ); ?>'); }, 2000);
				});
			});

			/* Load webhooks */
			function loadWebhooks() {
				$('#roxpay-webhook-list-wrap').html('<p class="roxpay-loading-hint"><?php esc_html_e( 'Loading…', 'roxpay-wpforms' ); ?></p>');
				$.post(ajaxUrl, { action: 'roxpay_load_webhooks', nonce: nonce }, function(res){
					if (!res.success) {
						$('#roxpay-webhook-list-wrap').html('<p class="roxpay-error">' + res.data + '</p>');
						return;
					}
					var hooks = res.data;
					if (!hooks.length) {
						$('#roxpay-webhook-list-wrap').html('<p class="roxpay-loading-hint"><?php esc_html_e( 'No webhooks registered yet.', 'roxpay-wpforms' ); ?></p>');
						return;
					}
					var html = '<table class="wp-list-table widefat fixed striped roxpay-hook-table"><thead><tr>'
						+ '<th>#</th><th><?php esc_html_e( 'URL', 'roxpay-wpforms' ); ?></th>'
						+ '<th><?php esc_html_e( 'Events', 'roxpay-wpforms' ); ?></th>'
						+ '<th><?php esc_html_e( 'Status', 'roxpay-wpforms' ); ?></th>'
						+ '<th><?php esc_html_e( 'Actions', 'roxpay-wpforms' ); ?></th>'
						+ '</tr></thead><tbody>';
					$.each(hooks, function(i, h){
						var badge = h.IsActive
							? '<span class="roxpay-badge active"><?php esc_html_e( 'Active', 'roxpay-wpforms' ); ?></span>'
							: '<span class="roxpay-badge inactive"><?php esc_html_e( 'Inactive', 'roxpay-wpforms' ); ?></span>';
						html += '<tr>'
							+ '<td>' + h.WebhookId + '</td>'
							+ '<td class="roxpay-hook-url" title="' + $('<span>').text(h.Url).html() + '">' + $('<span>').text(h.Url).html() + '</td>'
							+ '<td>' + (h.EventTypeIds||[]).join(', ') + '</td>'
							+ '<td>' + badge + '</td>'
							+ '<td>'
							+ '<button type="button" class="button button-small roxpay-view-deliveries" data-id="' + h.WebhookId + '"><?php esc_html_e( 'Deliveries', 'roxpay-wpforms' ); ?></button> '
							+ '<button type="button" class="button button-small roxpay-del-hook" data-id="' + h.WebhookId + '" style="color:#d63638"><?php esc_html_e( 'Delete', 'roxpay-wpforms' ); ?></button>'
							+ '</td></tr>';
					});
					html += '</tbody></table>';
					$('#roxpay-webhook-list-wrap').html(html);
				});
			}

			/* Register webhook */
			$('#roxpay-register-webhook').on('click', function(){
				var $btn = $(this).prop('disabled', true);
				$('#roxpay-webhook-action-msg').text('').removeClass('success error');
				$.post(ajaxUrl, { action: 'roxpay_register_webhook', nonce: nonce }, function(res){
					$btn.prop('disabled', false);
					if (res.success) {
						$('#roxpay-webhook-action-msg').addClass('success').text('<?php esc_html_e( '✓ Webhook registered!', 'roxpay-wpforms' ); ?>');
						loadWebhooks();
					} else {
						$('#roxpay-webhook-action-msg').addClass('error').text(res.data);
					}
				});
			});

			/* Refresh */
			$('#roxpay-refresh-webhooks').on('click', loadWebhooks);

			/* Delete (delegated) */
			$('#roxpay-webhook-list-wrap').on('click', '.roxpay-del-hook', function(){
				if (!confirm('<?php esc_html_e( 'Delete this webhook?', 'roxpay-wpforms' ); ?>')) return;
				var id = $(this).data('id');
				var $row = $(this).closest('tr').css('opacity', .4);
				$.post(ajaxUrl, { action: 'roxpay_delete_webhook', nonce: nonce, webhook_id: id }, function(res){
					if (res.success) { $row.remove(); }
					else { $row.css('opacity', 1); alert(res.data); }
				});
			});

			/* Deliveries (delegated) */
			$('#roxpay-webhook-list-wrap').on('click', '.roxpay-view-deliveries', function(){
				var id = $(this).data('id');
				$('#roxpay-deliveries-for').text('(#' + id + ')');
				$('#roxpay-deliveries-list').html('<p class="roxpay-loading-hint"><?php esc_html_e( 'Loading…', 'roxpay-wpforms' ); ?></p>');
				$('#roxpay-deliveries-wrap').show();
				$.post(ajaxUrl, { action: 'roxpay_load_deliveries', nonce: nonce, webhook_id: id }, function(res){
					if (!res.success) {
						$('#roxpay-deliveries-list').html('<p class="roxpay-error">' + res.data + '</p>');
						return;
					}
					var items = res.data;
					if (!items.length) {
						$('#roxpay-deliveries-list').html('<p><?php esc_html_e( 'No deliveries found.', 'roxpay-wpforms' ); ?></p>');
						return;
					}
					var html = '<table class="wp-list-table widefat fixed striped"><thead><tr>'
						+ '<th>#</th><th><?php esc_html_e( 'HTTP Status', 'roxpay-wpforms' ); ?></th><th><?php esc_html_e( 'Date', 'roxpay-wpforms' ); ?></th>'
						+ '</tr></thead><tbody>';
					$.each(items, function(i, d){
						var sc = d.StatusCode || '—';
						var cls = (sc >= 200 && sc < 300) ? 'roxpay-badge active' : 'roxpay-badge inactive';
						html += '<tr><td>#' + d.DeliveryId + '</td><td><span class="' + cls + '">' + sc + '</span></td><td>' + (d.CreatedOn||'—') + '</td></tr>';
					});
					html += '</tbody></table>';
					$('#roxpay-deliveries-list').html(html);
				});
			});

			/* Close deliveries */
			$('#roxpay-close-deliveries').on('click', function(){
				$('#roxpay-deliveries-wrap').hide();
			});

			loadWebhooks();
		}(jQuery));
		</script>
		<?php
	}

	// -------------------------------------------------------------------------
	// Save
	// -------------------------------------------------------------------------

	public function save_settings( $skip_nonce = false ) {
		if ( ! isset( $_POST['roxpay_settings'] ) ) {
			return;
		}
		if ( ! $skip_nonce ) {
			check_admin_referer( 'wpforms-settings' );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'roxpay-wpforms' ) );
		}

		$posted  = wp_unslash( $_POST['roxpay_settings'] );
		$current = get_option( 'roxpay_wpforms_settings', [] );
		$new     = $current;

		// Credentials — store base64-encoded.
		if ( isset( $posted['username_plain'] ) ) {
			$new['username'] = base64_encode( sanitize_text_field( $posted['username_plain'] ) );
		}
		if ( isset( $posted['password_plain'] ) ) {
			$new['password'] = base64_encode( sanitize_text_field( $posted['password_plain'] ) );
			RoxPay_Auth::invalidate_token();
		}

		// Redirect URLs.
		foreach ( [ 'success_redirect_url', 'failure_redirect_url', 'cancel_redirect_url' ] as $key ) {
			$new[ $key ] = isset( $posted[ $key ] ) ? esc_url_raw( $posted[ $key ] ) : '';
		}

		update_option( 'roxpay_wpforms_settings', $new );
	}

	// -------------------------------------------------------------------------
	// Test connection
	// -------------------------------------------------------------------------

	public function handle_test_connection() {
		if ( ! check_admin_referer( 'roxpay_test_connection', 'roxpay_test_nonce' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'roxpay-wpforms' ) );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'roxpay-wpforms' ) );
		}

		RoxPay_Auth::invalidate_token();
		$auth  = new RoxPay_Auth();
		$token = $auth->get_token();

		if ( is_wp_error( $token ) ) {
			set_transient( 'roxpay_test_result', 'error: ' . $token->get_error_message(), 120 );
		} else {
			set_transient( 'roxpay_test_result', 'success', 120 );
		}

		// Redirect back to wherever we came from
		$url = wp_get_referer();
		if ( ! $url || strpos( $url, 'roxpay' ) === false ) {
			$url = add_query_arg( [ 'page' => 'roxpay-settings' ], admin_url( 'admin.php' ) );
		}
		
		wp_safe_redirect( $url );
		exit;
	}

	public function handle_standalone_save() {
		if ( ! check_admin_referer( 'roxpay_standalone_settings', 'roxpay_standalone_nonce' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'roxpay-wpforms' ) );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Unauthorized', 'roxpay-wpforms' ) );
		}

		$this->save_settings( true ); // skip wpforms nonce

		wp_safe_redirect( add_query_arg( [ 'page' => 'roxpay-settings', 'saved' => '1' ], admin_url( 'admin.php' ) ) );
		exit;
	}

	// -------------------------------------------------------------------------
	// Assets
	// -------------------------------------------------------------------------

	public function enqueue_assets( $hook ) {
		$allowed = [ 'wpforms_page_wpforms-settings', 'toplevel_page_roxpay-payments', 'roxpay_page_roxpay-settings' ];
		if ( ! in_array( $hook, $allowed, true ) ) {
			return;
		}

		if ( ( isset( $_GET['view'] ) && $_GET['view'] === 'roxpay' ) || strpos( $hook, 'roxpay' ) !== false ) {
			wp_enqueue_style( 'roxpay-admin-style', ROXPAY_WPFORMS_URL . 'assets/admin.css', [], ROXPAY_WPFORMS_VERSION );
		}
	}

	// -------------------------------------------------------------------------
	// AJAX — Webhook management
	// -------------------------------------------------------------------------

	/** Shared nonce + cap check. */
	private function verify_ajax() {
		if ( ! check_ajax_referer( 'roxpay_webhook_mgmt', 'nonce', false ) ) {
			wp_send_json_error( esc_html__( 'Security check failed.', 'roxpay-wpforms' ) );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'Unauthorized.', 'roxpay-wpforms' ) );
		}
	}

	public function ajax_load_webhooks() {
		$this->verify_ajax();
		$api    = new RoxPay_API( new RoxPay_Auth() );
		$result = $api->get_webhooks();
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}
		wp_send_json_success( $result['Data']['Webhooks'] ?? [] );
	}

	public function ajax_register_webhook() {
		$this->verify_ajax();
		$api    = new RoxPay_API( new RoxPay_Auth() );
		$result = $api->create_webhook( rest_url( 'roxpay-wpforms/v1/webhook' ), [ 1, 2, 7 ], 1 );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}
		wp_send_json_success( [
			'WebhookId' => $result['Data']['WebhookId'] ?? null,
			'Message'   => $result['Message'] ?? 'Created.',
		] );
	}

	public function ajax_delete_webhook() {
		$this->verify_ajax();
		$webhook_id = absint( $_POST['webhook_id'] ?? 0 );
		if ( ! $webhook_id ) {
			wp_send_json_error( esc_html__( 'Invalid webhook ID.', 'roxpay-wpforms' ) );
		}
		$api    = new RoxPay_API( new RoxPay_Auth() );
		$result = $api->delete_webhook( $webhook_id );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}
		wp_send_json_success( [ 'deleted' => $webhook_id ] );
	}

	public function ajax_load_deliveries() {
		$this->verify_ajax();
		$webhook_id = absint( $_POST['webhook_id'] ?? 0 );
		$api        = new RoxPay_API( new RoxPay_Auth() );
		$result     = $api->get_webhook_deliveries( 1, 20, $webhook_id ?: null );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}
		wp_send_json_success( $result['Data']['Deliveries'] ?? [] );
	}
}

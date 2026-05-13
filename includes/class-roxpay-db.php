<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Class RoxPay_DB
 *
 * Custom table: {prefix}roxpay_payments
 *
 * Columns:
 *   id              PK auto-increment
 *   entry_id        WPForms entry ID
 *   form_id         WPForms form ID
 *   transaction_id  RoxPay TransactionId (filled by webhook)
 *   internal_txn_id Our WPF-{entry}-{ts} reference
 *   amount          Integer cents
 *   currency        ISO 4217 e.g. EUR
 *   status          pending | completed | failed
 *   form_snapshot   JSON array of {name, value} pairs
 *   created_at      UTC datetime
 *   updated_at      UTC datetime
 */
class RoxPay_DB {

	const VERSION     = '1';
	const VERSION_KEY = 'roxpay_db_version';

	/** Full prefixed table name. */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'roxpay_payments';
	}

	/**
	 * Create or silently upgrade the table (dbDelta is idempotent).
	 * NOTE: TEXT/BLOB columns cannot have a DEFAULT value in MySQL < 8.0.13.
	 */
	public static function create_table() {
		global $wpdb;
		$table   = self::table();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			entry_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
			form_id bigint(20) UNSIGNED NOT NULL DEFAULT 0,
			transaction_id varchar(100) NOT NULL DEFAULT '',
			internal_txn_id varchar(100) NOT NULL DEFAULT '',
			amount int(11) NOT NULL DEFAULT 0,
			currency varchar(10) NOT NULL DEFAULT 'EUR',
			status varchar(30) NOT NULL DEFAULT 'pending',
			form_snapshot longtext NOT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY entry_id (entry_id),
			KEY transaction_id (transaction_id),
			KEY status (status)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
		update_option( self::VERSION_KEY, self::VERSION );
	}

	/**
	 * Insert a new payment record.
	 *
	 * @param array $data {
	 *   entry_id, form_id, transaction_id, internal_txn_id,
	 *   amount (int cents), currency, status, form_snapshot (array|string)
	 * }
	 * @return int|false New row ID or false.
	 */
	public static function insert( array $data ) {
		global $wpdb;

		$snapshot = $data['form_snapshot'] ?? [];
		if ( is_array( $snapshot ) ) {
			$snapshot = wp_json_encode( $snapshot );
		}

		$result = $wpdb->insert(
			self::table(),
			[
				'entry_id'        => absint( $data['entry_id'] ?? 0 ),
				'form_id'         => absint( $data['form_id'] ?? 0 ),
				'transaction_id'  => sanitize_text_field( $data['transaction_id'] ?? '' ),
				'internal_txn_id' => sanitize_text_field( $data['internal_txn_id'] ?? '' ),
				'amount'          => (int) ( $data['amount'] ?? 0 ),
				'currency'        => sanitize_text_field( $data['currency'] ?? 'EUR' ),
				'status'          => sanitize_text_field( $data['status'] ?? 'pending' ),
				'form_snapshot'   => (string) $snapshot,
				'created_at'      => current_time( 'mysql', true ),
				'updated_at'      => current_time( 'mysql', true ),
			],
			[ '%d', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s' ]
		);

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Update status (and optionally the RoxPay TransactionId) for an entry_id.
	 *
	 * @param int    $entry_id
	 * @param string $status         'pending' | 'completed' | 'failed'
	 * @param string $transaction_id Optional RoxPay TransactionId.
	 * @return int|false Rows affected or false.
	 */
	public static function update_status( $entry_id, $status, $transaction_id = '' ) {
		global $wpdb;

		$data = [
			'status'     => sanitize_text_field( $status ),
			'updated_at' => current_time( 'mysql', true ),
		];
		$formats = [ '%s', '%s' ];

		if ( ! empty( $transaction_id ) ) {
			$data['transaction_id'] = sanitize_text_field( $transaction_id );
			$formats[]              = '%s';
		}

		return $wpdb->update(
			self::table(),
			$data,
			[ 'entry_id' => absint( $entry_id ) ],
			$formats,
			[ '%d' ]
		);
	}

	/**
	 * Get a single record by WPForms entry ID.
	 *
	 * @param int $entry_id
	 * @return array|null
	 */
	public static function get_by_entry( $entry_id ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . self::table() . ' WHERE entry_id = %d LIMIT 1',
				absint( $entry_id )
			),
			ARRAY_A
		);
	}

	/**
	 * Paginated list of payment records.
	 *
	 * @param array $args { page, per_page, status }
	 * @return array { payments[], total, total_pages }
	 */
	public static function get_payments( array $args = [] ) {
		global $wpdb;
		$table    = self::table();
		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$per_page = max( 1, (int) ( $args['per_page'] ?? 20 ) );
		$status   = sanitize_text_field( $args['status'] ?? '' );
		$offset   = ( $page - 1 ) * $per_page;

		if ( ! empty( $status ) ) {
			$where = $wpdb->prepare( 'WHERE status = %s', $status );
		} else {
			$where = '';
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$payments = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d",
				$per_page,
				$offset
			),
			ARRAY_A
		);

		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} {$where}" );
		// phpcs:enable

		return [
			'payments'    => $payments ?: [],
			'total'       => $total,
			'total_pages' => (int) ceil( $total / max( 1, $per_page ) ),
		];
	}

	/**
	 * Status counts grouped by status value.
	 *
	 * @return array { all, pending, completed, failed }
	 */
	public static function get_status_counts() {
		global $wpdb;
		$table  = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows   = $wpdb->get_results( "SELECT status, COUNT(*) AS cnt FROM {$table} GROUP BY status", ARRAY_A );
		$counts = [ 'all' => 0, 'pending' => 0, 'completed' => 0, 'failed' => 0 ];

		foreach ( (array) $rows as $row ) {
			$s              = $row['status'];
			$n              = (int) $row['cnt'];
			$counts['all'] += $n;
			if ( array_key_exists( $s, $counts ) ) {
				$counts[ $s ] = $n;
			}
		}

		return $counts;
	}

	/**
	 * Sum of all completed payment amounts (in cents).
	 *
	 * @return int
	 */
	public static function get_total_revenue() {
		global $wpdb;
		return (int) $wpdb->get_var(
			$wpdb->prepare( 'SELECT COALESCE(SUM(amount),0) FROM ' . self::table() . ' WHERE status = %s', 'completed' )
		);
	}
}

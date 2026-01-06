<?php
/**
 * WP-CLI Commands for User Self Delete.
 *
 * @package UserSelfDelete
 * @since 2.0.0
 */

declare(strict_types=1);

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP-CLI commands for managing user deletions.
 *
 * @since 2.0.0
 */
class User_Self_Delete_CLI {

	/**
	 * View deletion statistics.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp user-self-delete stats
	 *     wp user-self-delete stats --format=json
	 *
	 * @when after_wp_load
	 */
	public function stats( array $args, array $assoc_args ): void {
		global $wpdb;

		$table_name = $wpdb->prefix . 'user_self_delete_log';

		// Check if table exists.
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) !== $table_name ) {
			WP_CLI::error( 'No deletion data available. Logging may be disabled.' );
			return;
		}

		// Get statistics.
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );

		$today = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table_name} WHERE DATE(deletion_date) = %s",
				gmdate( 'Y-m-d' )
			)
		);

		$this_week = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table_name} WHERE deletion_date >= %s",
				gmdate( 'Y-m-d', strtotime( 'monday this week' ) )
			)
		);

		$this_month = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table_name} WHERE deletion_date >= %s",
				gmdate( 'Y-m-01' )
			)
		);

		$stats = array(
			array(
				'period'  => 'Total',
				'count'   => $total,
			),
			array(
				'period'  => 'Today',
				'count'   => $today,
			),
			array(
				'period'  => 'This Week',
				'count'   => $this_week,
			),
			array(
				'period'  => 'This Month',
				'count'   => $this_month,
			),
		);

		$format = $assoc_args['format'] ?? 'table';
		WP_CLI\Utils\format_items( $format, $stats, array( 'period', 'count' ) );
	}

	/**
	 * View recent deletion log entries.
	 *
	 * ## OPTIONS
	 *
	 * [--limit=<number>]
	 * : Number of entries to show.
	 * ---
	 * default: 10
	 * ---
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp user-self-delete log
	 *     wp user-self-delete log --limit=20
	 *     wp user-self-delete log --format=json
	 *
	 * @when after_wp_load
	 */
	public function log( array $args, array $assoc_args ): void {
		global $wpdb;

		$table_name = $wpdb->prefix . 'user_self_delete_log';

		// Check if table exists.
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) !== $table_name ) {
			WP_CLI::error( 'No deletion log available. Logging may be disabled.' );
			return;
		}

		$limit = (int) ( $assoc_args['limit'] ?? 10 );

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT user_id, user_email, deletion_date, ip_address, status FROM {$table_name} ORDER BY deletion_date DESC LIMIT %d",
				$limit
			),
			ARRAY_A
		);

		if ( empty( $results ) ) {
			WP_CLI::warning( 'No deletion log entries found.' );
			return;
		}

		$format = $assoc_args['format'] ?? 'table';
		WP_CLI\Utils\format_items( $format, $results, array( 'user_id', 'user_email', 'deletion_date', 'ip_address', 'status' ) );
	}

	/**
	 * Export deletion log to CSV file.
	 *
	 * ## OPTIONS
	 *
	 * [<file>]
	 * : The name of the exported file.
	 * ---
	 * default: user-deletions.csv
	 * ---
	 *
	 * [--start-date=<date>]
	 * : Start date for export (Y-m-d format).
	 *
	 * [--end-date=<date>]
	 * : End date for export (Y-m-d format).
	 *
	 * ## EXAMPLES
	 *
	 *     wp user-self-delete export
	 *     wp user-self-delete export deletions-2024.csv
	 *     wp user-self-delete export --start-date=2024-01-01 --end-date=2024-12-31
	 *
	 * @when after_wp_load
	 */
	public function export( array $args, array $assoc_args ): void {
		global $wpdb;

		$table_name = $wpdb->prefix . 'user_self_delete_log';

		// Check if table exists.
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) !== $table_name ) {
			WP_CLI::error( 'No deletion data available. Logging may be disabled.' );
			return;
		}

		$filename = $args[0] ?? 'user-deletions.csv';

		// Build query.
		$query = "SELECT * FROM {$table_name}";
		$where = array();

		if ( isset( $assoc_args['start-date'] ) ) {
			$where[] = $wpdb->prepare( 'DATE(deletion_date) >= %s', $assoc_args['start-date'] );
		}

		if ( isset( $assoc_args['end-date'] ) ) {
			$where[] = $wpdb->prepare( 'DATE(deletion_date) <= %s', $assoc_args['end-date'] );
		}

		if ( ! empty( $where ) ) {
			$query .= ' WHERE ' . implode( ' AND ', $where );
		}

		$query .= ' ORDER BY deletion_date DESC';

		$results = $wpdb->get_results( $query, ARRAY_A );

		if ( empty( $results ) ) {
			WP_CLI::warning( 'No deletion log entries found for export.' );
			return;
		}

		// Create CSV.
		$file_handle = fopen( $filename, 'w' );

		if ( false === $file_handle ) {
			WP_CLI::error( "Could not create file: {$filename}" );
			return;
		}

		// Write headers.
		fputcsv( $file_handle, array_keys( $results[0] ) );

		// Write data.
		foreach ( $results as $row ) {
			fputcsv( $file_handle, $row );
		}

		fclose( $file_handle );

		WP_CLI::success( sprintf( 'Exported %d records to %s', count( $results ), $filename ) );
	}

	/**
	 * Clear deletion log entries.
	 *
	 * ## OPTIONS
	 *
	 * [--older-than=<days>]
	 * : Only delete entries older than specified days.
	 *
	 * [--yes]
	 * : Skip confirmation prompt.
	 *
	 * ## EXAMPLES
	 *
	 *     wp user-self-delete clear-log --yes
	 *     wp user-self-delete clear-log --older-than=90 --yes
	 *
	 * @when after_wp_load
	 */
	public function clear_log( array $args, array $assoc_args ): void {
		global $wpdb;

		$table_name = $wpdb->prefix . 'user_self_delete_log';

		// Check if table exists.
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) !== $table_name ) {
			WP_CLI::error( 'No deletion log available.' );
			return;
		}

		// Get count of entries to delete.
		$query = "SELECT COUNT(*) FROM {$table_name}";

		if ( isset( $assoc_args['older-than'] ) ) {
			$days  = (int) $assoc_args['older-than'];
			$date  = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );
			$query .= $wpdb->prepare( ' WHERE deletion_date < %s', $date );
		}

		$count = (int) $wpdb->get_var( $query );

		if ( 0 === $count ) {
			WP_CLI::warning( 'No log entries to delete.' );
			return;
		}

		// Confirm deletion.
		WP_CLI::confirm(
			sprintf( 'Are you sure you want to delete %d log entries?', $count ),
			$assoc_args
		);

		// Delete entries.
		if ( isset( $assoc_args['older-than'] ) ) {
			$days = (int) $assoc_args['older-than'];
			$date = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$table_name} WHERE deletion_date < %s", $date ) );
		} else {
			$wpdb->query( "TRUNCATE TABLE {$table_name}" );
		}

		WP_CLI::success( sprintf( 'Deleted %d log entries.', $count ) );
	}

	/**
	 * View plugin settings.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp user-self-delete settings
	 *     wp user-self-delete settings --format=json
	 *
	 * @when after_wp_load
	 */
	public function settings( array $args, array $assoc_args ): void {
		$settings = array(
			array(
				'setting'     => 'enable_logging',
				'value'       => get_option( 'user_self_delete_enable_logging', 1 ) ? 'enabled' : 'disabled',
				'description' => 'Log all account deletion attempts',
			),
			array(
				'setting'     => 'admin_notification',
				'value'       => get_option( 'user_self_delete_admin_notification', 1 ) ? 'enabled' : 'disabled',
				'description' => 'Send email to admin on deletions',
			),
			array(
				'setting'     => 'anonymize_orders',
				'value'       => get_option( 'user_self_delete_anonymize_orders', 1 ) ? 'yes' : 'no',
				'description' => 'Anonymize WooCommerce orders (vs delete)',
			),
			array(
				'setting'     => 'delete_posts',
				'value'       => get_option( 'user_self_delete_delete_posts', 0 ) ? 'yes' : 'no',
				'description' => 'Delete user posts (vs reassign to admin)',
			),
		);

		$format = $assoc_args['format'] ?? 'table';
		WP_CLI\Utils\format_items( $format, $settings, array( 'setting', 'value', 'description' ) );
	}
}

// Register WP-CLI commands if available.
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command( 'user-self-delete', 'User_Self_Delete_CLI' );
}

<?php
/**
 * Plugin Name: User Self Delete for WordPress
 * Plugin URI: https://github.com/Open-WP-Club/User-Self-Delete
 * Description: GDPR-compliant user self-delete functionality for WordPress/WooCommerce with modern security and performance features
 * Version: 2.0.0
 * Author: Open WP Club
 * Author URI: https://github.com/Open-WP-Club
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: user-self-delete
 * Domain Path: /languages
 * Requires at least: 6.4
 * Requires PHP: 8.2
 * WC requires at least: 7.0
 * WC tested up to: 9.5
 *
 * @package UserSelfDelete
 */

declare(strict_types=1);

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'USER_SELF_DELETE_VERSION', '2.0.0' );
define( 'USER_SELF_DELETE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'USER_SELF_DELETE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'USER_SELF_DELETE_PLUGIN_FILE', __FILE__ );
define( 'USER_SELF_DELETE_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'USER_SELF_DELETE_MIN_PHP', '8.2' );
define( 'USER_SELF_DELETE_MIN_WP', '6.4' );

/**
 * Main plugin class.
 *
 * @since 1.0.0
 */
final class User_Self_Delete_Plugin {

	/**
	 * Instance of this class.
	 *
	 * @var User_Self_Delete_Plugin|null
	 */
	private static ?User_Self_Delete_Plugin $instance = null;

	/**
	 * Get instance.
	 *
	 * @return User_Self_Delete_Plugin
	 */
	public static function get_instance(): User_Self_Delete_Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		// Check requirements.
		if ( ! $this->check_requirements() ) {
			return;
		}

		// Register activation/deactivation hooks.
		register_activation_hook( USER_SELF_DELETE_PLUGIN_FILE, array( $this, 'activate' ) );
		register_deactivation_hook( USER_SELF_DELETE_PLUGIN_FILE, array( $this, 'deactivate' ) );

		// Initialize plugin.
		add_action( 'plugins_loaded', array( $this, 'init' ), 10 );

		// Declare HPOS compatibility.
		add_action( 'before_woocommerce_init', array( $this, 'declare_hpos_compatibility' ) );
	}

	/**
	 * Check plugin requirements.
	 *
	 * @return bool
	 */
	private function check_requirements(): bool {
		// Check PHP version.
		if ( version_compare( PHP_VERSION, USER_SELF_DELETE_MIN_PHP, '<' ) ) {
			add_action( 'admin_notices', array( $this, 'php_version_notice' ) );
			return false;
		}

		// Check WordPress version.
		global $wp_version;
		if ( version_compare( $wp_version, USER_SELF_DELETE_MIN_WP, '<' ) ) {
			add_action( 'admin_notices', array( $this, 'wp_version_notice' ) );
			return false;
		}

		return true;
	}

	/**
	 * Display PHP version notice.
	 */
	public function php_version_notice(): void {
		?>
		<div class="notice notice-error">
			<p>
				<?php
				printf(
					/* translators: %s: Required PHP version */
					esc_html__( 'User Self Delete requires PHP version %s or higher.', 'user-self-delete' ),
					esc_html( USER_SELF_DELETE_MIN_PHP )
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Display WordPress version notice.
	 */
	public function wp_version_notice(): void {
		?>
		<div class="notice notice-error">
			<p>
				<?php
				printf(
					/* translators: %s: Required WordPress version */
					esc_html__( 'User Self Delete requires WordPress version %s or higher.', 'user-self-delete' ),
					esc_html( USER_SELF_DELETE_MIN_WP )
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Declare HPOS compatibility.
	 *
	 * @since 2.0.0
	 */
	public function declare_hpos_compatibility(): void {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				USER_SELF_DELETE_PLUGIN_FILE,
				true
			);
		}
	}

	/**
	 * Initialize plugin.
	 */
	public function init(): void {
		// Load text domain.
		load_plugin_textdomain(
			'user-self-delete',
			false,
			dirname( USER_SELF_DELETE_PLUGIN_BASENAME ) . '/languages/'
		);

		// Include required files.
		$this->includes();

		// Initialize components.
		$this->init_components();
	}

	/**
	 * Include required files.
	 */
	private function includes(): void {
		require_once USER_SELF_DELETE_PLUGIN_DIR . 'includes/retention-periods.php';
		require_once USER_SELF_DELETE_PLUGIN_DIR . 'includes/user-self-delete.php';
		require_once USER_SELF_DELETE_PLUGIN_DIR . 'includes/data-eraser.php';
		require_once USER_SELF_DELETE_PLUGIN_DIR . 'includes/admin-settings.php';

		// Load WP-CLI commands if available.
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			require_once USER_SELF_DELETE_PLUGIN_DIR . 'includes/wp-cli.php';
		}
	}

	/**
	 * Initialize components.
	 */
	private function init_components(): void {
		// Initialize main functionality.
		User_Self_Delete_Core::get_instance();

		// Initialize admin settings if in admin.
		if ( is_admin() ) {
			User_Self_Delete_Admin::get_instance();
		}
	}

	/**
	 * Plugin activation.
	 */
	public function activate(): void {
		// Check requirements before activation.
		if ( ! $this->check_requirements() ) {
			deactivate_plugins( USER_SELF_DELETE_PLUGIN_BASENAME );
			wp_die(
				esc_html__( 'Plugin activation failed. Please check the requirements.', 'user-self-delete' ),
				esc_html__( 'Plugin Activation Error', 'user-self-delete' ),
				array( 'back_link' => true )
			);
		}

		// Create log table and archive table.
		$this->create_log_table();

		// Migrate existing soft-deleted users to archive table.
		$this->migrate_soft_deleted_users();

		// Set default options.
		$default_options = array(
			'enable_logging'       => 1,
			'admin_notification'   => 1,
			'anonymize_orders'     => 1,
			'delete_posts'         => 0,
			'require_password'     => 1,
			'deletion_cooldown'    => 0,
			'cooldown_period'      => 24,
		);

		foreach ( $default_options as $key => $value ) {
			if ( false === get_option( 'user_self_delete_' . $key ) ) {
				update_option( 'user_self_delete_' . $key, $value, true );
			}
		}

		// Set activation flag.
		set_transient( 'user_self_delete_activated', true, 30 );

		// Flush rewrite rules.
		flush_rewrite_rules();
	}

	/**
	 * Plugin deactivation.
	 */
	public function deactivate(): void {
		// Flush rewrite rules.
		flush_rewrite_rules();

		// Clear scheduled events if any.
		wp_clear_scheduled_hook( 'user_self_delete_cleanup' );
	}

	/**
	 * Create log table.
	 */
	private function create_log_table(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// Create log table.
		$log_table = $wpdb->prefix . 'user_self_delete_log';
		$log_sql = "CREATE TABLE IF NOT EXISTS {$log_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			user_email varchar(100) NOT NULL DEFAULT '',
			deletion_date datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			ip_address varchar(45) NOT NULL DEFAULT '',
			user_agent text,
			status varchar(20) NOT NULL DEFAULT 'completed',
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY deletion_date (deletion_date),
			KEY status (status)
		) {$charset_collate};";

		// Create archive table for soft-deleted users.
		$archive_table = $wpdb->prefix . 'user_self_delete_archive';
		$archive_sql = "CREATE TABLE IF NOT EXISTS {$archive_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			original_user_id bigint(20) unsigned NOT NULL,
			original_email varchar(100) NOT NULL DEFAULT '',
			user_login varchar(60) NOT NULL DEFAULT '',
			user_nicename varchar(50) NOT NULL DEFAULT '',
			display_name varchar(250) NOT NULL DEFAULT '',
			deletion_date datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			scheduled_deletion_date datetime DEFAULT NULL,
			retention_years int(11) NOT NULL DEFAULT 0,
			retention_countries text,
			ip_address varchar(45) NOT NULL DEFAULT '',
			user_agent text,
			user_data longtext,
			PRIMARY KEY  (id),
			KEY original_user_id (original_user_id),
			KEY deletion_date (deletion_date),
			KEY scheduled_deletion_date (scheduled_deletion_date),
			KEY original_email (original_email)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $log_sql );
		dbDelta( $archive_sql );

		// Store database version.
		update_option( 'user_self_delete_db_version', USER_SELF_DELETE_VERSION, true );
	}

	/**
	 * Migrate existing soft-deleted users to archive table.
	 *
	 * This migrates users who were soft-deleted using the old method
	 * (user meta flags) to the new archive table system.
	 *
	 * @since 2.0.0
	 */
	private function migrate_soft_deleted_users(): void {
		// Check if migration has already run.
		if ( get_option( 'user_self_delete_archive_migration_done', false ) ) {
			return;
		}

		global $wpdb;

		// Find users with old soft-delete meta flags.
		$soft_deleted_users = $wpdb->get_col(
			"SELECT user_id FROM {$wpdb->usermeta}
			WHERE meta_key = 'user_self_delete_status'
			AND meta_value = 'deleted'"
		);

		if ( empty( $soft_deleted_users ) ) {
			// No users to migrate, mark as done.
			update_option( 'user_self_delete_archive_migration_done', true, false );
			return;
		}

		$archive_table = $wpdb->prefix . 'user_self_delete_archive';
		$migrated = 0;

		foreach ( $soft_deleted_users as $user_id ) {
			// Get user object.
			$user = get_userdata( $user_id );
			if ( ! $user ) {
				continue;
			}

			// Get deletion metadata.
			$deletion_date     = get_user_meta( $user_id, 'user_self_delete_date', true ) ?: current_time( 'mysql' );
			$scheduled_date    = get_user_meta( $user_id, 'user_self_delete_scheduled_for', true );
			$retention_years   = (int) get_user_meta( $user_id, 'user_self_delete_retention_years', true );
			$original_email    = get_user_meta( $user_id, 'user_self_delete_original_email', true );

			// If no scheduled date, calculate it.
			if ( ! $scheduled_date && $retention_years > 0 ) {
				$scheduled_date = gmdate( 'Y-m-d H:i:s', strtotime( $deletion_date . " +{$retention_years} years" ) );
			}

			// Get selected countries from settings.
			$selected_countries = get_option( 'user_self_delete_countries', array() );
			$retention_countries = is_array( $selected_countries ) ? wp_json_encode( $selected_countries ) : '';

			// Collect user meta.
			$user_meta = get_user_meta( $user_id );
			$user_data = wp_json_encode( array(
				'meta'       => $user_meta,
				'roles'      => $user->roles,
				'caps'       => $user->caps,
				'first_name' => $user->first_name,
				'last_name'  => $user->last_name,
			) );

			// Insert into archive table.
			$inserted = $wpdb->insert(
				$archive_table,
				array(
					'original_user_id'       => $user_id,
					'original_email'         => $original_email ?: $user->user_email,
					'user_login'             => $user->user_login,
					'user_nicename'          => $user->user_nicename,
					'display_name'           => $user->display_name,
					'deletion_date'          => $deletion_date,
					'scheduled_deletion_date' => $scheduled_date,
					'retention_years'        => $retention_years,
					'retention_countries'    => $retention_countries,
					'ip_address'             => '0.0.0.0', // Unknown for legacy data.
					'user_agent'             => '',
					'user_data'              => $user_data,
				),
				array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s' )
			);

			if ( $inserted ) {
				// Delete the user from wp_users.
				wp_delete_user( $user_id );
				$migrated++;
			}
		}

		// Mark migration as complete.
		update_option( 'user_self_delete_archive_migration_done', true, false );

		// Log migration if enabled.
		if ( get_option( 'user_self_delete_enable_logging', 1 ) && $migrated > 0 ) {
			error_log( sprintf( 'User Self Delete: Migrated %d soft-deleted users to archive table.', $migrated ) );
		}
	}
}

// Initialize plugin.
User_Self_Delete_Plugin::get_instance();

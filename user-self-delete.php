<?php
/**
 * Plugin Name: User Self Delete
 * Plugin URI: https://github.com/Open-WP-Club/User-Self-Delete
 * Description: GDPR-compliant user self-delete functionality for WordPress/WooCommerce with modern security and performance features
 * Version: 2.0.0
 * Author: Open WP Club
 * Author URI: https://github.com/Open-WP-Club
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: user-self-delete
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
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
define( 'USER_SELF_DELETE_MIN_PHP', '7.4' );
define( 'USER_SELF_DELETE_MIN_WP', '6.0' );

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
		require_once USER_SELF_DELETE_PLUGIN_DIR . 'includes/class-user-self-delete.php';
		require_once USER_SELF_DELETE_PLUGIN_DIR . 'includes/class-data-eraser.php';
		require_once USER_SELF_DELETE_PLUGIN_DIR . 'includes/class-admin-settings.php';
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

		// Create log table.
		$this->create_log_table();

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

		$table_name      = $wpdb->prefix . 'user_self_delete_log';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
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

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// Store database version.
		update_option( 'user_self_delete_db_version', USER_SELF_DELETE_VERSION, true );
	}
}

// Initialize plugin.
User_Self_Delete_Plugin::get_instance();

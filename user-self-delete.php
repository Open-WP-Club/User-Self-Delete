<?php

/**
 * Plugin Name: User Self Delete
 * Plugin URI: https://example.com
 * Description: GDPR-compliant user self-delete functionality for WordPress/WooCommerce
 * Version: 1.0.0
 * Author: Your Name
 * License: GPL v2 or later
 * Text Domain: user-self-delete
 * Domain Path: /languages
 */

// Prevent direct access
if (!defined('ABSPATH')) {
  exit;
}

// Define plugin constants
define('USER_SELF_DELETE_VERSION', '1.0.0');
define('USER_SELF_DELETE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('USER_SELF_DELETE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('USER_SELF_DELETE_PLUGIN_FILE', __FILE__);

/**
 * Main plugin class
 */
class User_Self_Delete_Plugin
{

  /**
   * Instance of this class
   */
  private static $instance = null;

  /**
   * Get instance
   */
  public static function get_instance()
  {
    if (null === self::$instance) {
      self::$instance = new self();
    }
    return self::$instance;
  }

  /**
   * Constructor
   */
  private function __construct()
  {
    add_action('plugins_loaded', array($this, 'init'));
    register_activation_hook(__FILE__, array($this, 'activate'));
    register_deactivation_hook(__FILE__, array($this, 'deactivate'));
  }

  /**
   * Initialize plugin
   */
  public function init()
  {
    // Load text domain
    load_plugin_textdomain('user-self-delete', false, dirname(plugin_basename(__FILE__)) . '/languages/');

    // Include required files
    $this->includes();

    // Initialize components
    $this->init_components();
  }

  /**
   * Include required files
   */
  private function includes()
  {
    require_once USER_SELF_DELETE_PLUGIN_DIR . 'includes/class-user-self-delete.php';
    require_once USER_SELF_DELETE_PLUGIN_DIR . 'includes/class-data-eraser.php';
    require_once USER_SELF_DELETE_PLUGIN_DIR . 'includes/class-admin-settings.php';
  }

  /**
   * Initialize components
   */
  private function init_components()
  {
    // Initialize main functionality
    User_Self_Delete_Core::get_instance();

    // Initialize admin settings if in admin
    if (is_admin()) {
      User_Self_Delete_Admin::get_instance();
    }
  }

  /**
   * Plugin activation
   */
  public function activate()
  {
    // Create log table
    $this->create_log_table();

    // Set default options
    $default_options = array(
      'enable_logging' => 1,
      'admin_notification' => 1,
      'anonymize_orders' => 1,
    );

    foreach ($default_options as $key => $value) {
      if (!get_option('user_self_delete_' . $key)) {
        update_option('user_self_delete_' . $key, $value);
      }
    }
  }

  /**
   * Plugin deactivation
   */
  public function deactivate()
  {
    // Clean up if needed
  }

  /**
   * Create log table
   */
  private function create_log_table()
  {
    global $wpdb;

    $table_name = $wpdb->prefix . 'user_self_delete_log';

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            user_email varchar(100) NOT NULL,
            deletion_date datetime DEFAULT CURRENT_TIMESTAMP,
            ip_address varchar(45) NOT NULL,
            user_agent text,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY deletion_date (deletion_date)
        ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
  }
}

// Initialize plugin
User_Self_Delete_Plugin::get_instance();

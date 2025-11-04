<?php

/**
 * Admin settings for User Self Delete
 */

if (!defined('ABSPATH')) {
  exit;
}

class User_Self_Delete_Admin
{

  private static $instance = null;

  public static function get_instance()
  {
    if (null === self::$instance) {
      self::$instance = new self();
    }
    return self::$instance;
  }

  private function __construct()
  {
    add_action('admin_menu', array($this, 'add_admin_menu'));
    add_action('admin_init', array($this, 'init_settings'));
    add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
  }

  /**
   * Add admin menu
   */
  public function add_admin_menu()
  {
    add_options_page(
      __('User Self Delete Settings', 'user-self-delete'),
      __('User Self Delete', 'user-self-delete'),
      'manage_options',
      'user-self-delete',
      array($this, 'admin_page')
    );
  }

  /**
   * Initialize settings
   */
  public function init_settings()
  {
    register_setting('user_self_delete_settings', 'user_self_delete_enable_logging');
    register_setting('user_self_delete_settings', 'user_self_delete_admin_notification');
    register_setting('user_self_delete_settings', 'user_self_delete_anonymize_orders');
    register_setting('user_self_delete_settings', 'user_self_delete_delete_posts');

    // General Settings Section
    add_settings_section(
      'user_self_delete_general',
      __('General Settings', 'user-self-delete'),
      array($this, 'general_section_callback'),
      'user_self_delete_settings'
    );

    // Logging setting
    add_settings_field(
      'enable_logging',
      __('Enable Logging', 'user-self-delete'),
      array($this, 'checkbox_field_callback'),
      'user_self_delete_settings',
      'user_self_delete_general',
      array(
        'name' => 'user_self_delete_enable_logging',
        'description' => __('Log all account deletion attempts for audit purposes.', 'user-self-delete')
      )
    );

    // Admin notification setting
    add_settings_field(
      'admin_notification',
      __('Admin Notifications', 'user-self-delete'),
      array($this, 'checkbox_field_callback'),
      'user_self_delete_settings',
      'user_self_delete_general',
      array(
        'name' => 'user_self_delete_admin_notification',
        'description' => __('Send email notification to admin when a user deletes their account.', 'user-self-delete')
      )
    );

    // WooCommerce Settings Section (if WooCommerce is active)
    if (class_exists('WooCommerce')) {
      add_settings_section(
        'user_self_delete_woocommerce',
        __('WooCommerce Settings', 'user-self-delete'),
        array($this, 'woocommerce_section_callback'),
        'user_self_delete_settings'
      );

      // Anonymize orders setting
      add_settings_field(
        'anonymize_orders',
        __('Handle Orders', 'user-self-delete'),
        array($this, 'radio_field_callback'),
        'user_self_delete_settings',
        'user_self_delete_woocommerce',
        array(
          'name' => 'user_self_delete_anonymize_orders',
          'options' => array(
            '1' => __('Anonymize orders (recommended for legal compliance)', 'user-self-delete'),
            '0' => __('Delete orders completely', 'user-self-delete')
          ),
          'description' => __('Anonymizing orders removes personal data but keeps order records for tax and legal purposes.', 'user-self-delete')
        )
      );
    }

    // Content Settings Section
    add_settings_section(
      'user_self_delete_content',
      __('Content Settings', 'user-self-delete'),
      array($this, 'content_section_callback'),
      'user_self_delete_settings'
    );

    // Delete posts setting
    add_settings_field(
      'delete_posts',
      __('User Posts', 'user-self-delete'),
      array($this, 'radio_field_callback'),
      'user_self_delete_settings',
      'user_self_delete_content',
      array(
        'name' => 'user_self_delete_delete_posts',
        'options' => array(
          '0' => __('Reassign to administrator (recommended)', 'user-self-delete'),
          '1' => __('Delete permanently', 'user-self-delete')
        ),
        'description' => __('Choose what happens to posts created by users who delete their accounts.', 'user-self-delete')
      )
    );
  }

  /**
   * Enqueue admin scripts
   */
  public function admin_scripts($hook)
  {
    if ($hook !== 'settings_page_user-self-delete') {
      return;
    }

    wp_enqueue_style(
      'user-self-delete-admin',
      USER_SELF_DELETE_PLUGIN_URL . 'assets/css/admin.css',
      array(),
      USER_SELF_DELETE_VERSION
    );
  }

  /**
   * Admin page
   */
  public function admin_page()
  {
?>
    <div class="wrap">
      <h1><?php _e('User Self Delete Settings', 'user-self-delete'); ?></h1>

      <div class="user-self-delete-admin">
        <form method="post" action="options.php">
          <?php
          settings_fields('user_self_delete_settings');
          do_settings_sections('user_self_delete_settings');
          submit_button();
          ?>
        </form>

        <div class="postbox" style="margin-top: 20px;">
          <h2 class="hndle"><span><?php _e('Deletion Statistics', 'user-self-delete'); ?></span></h2>
          <div class="inside">
            <?php $this->display_deletion_stats(); ?>
          </div>
        </div>

        <?php if (get_option('user_self_delete_enable_logging', 1)): ?>
          <div class="postbox" style="margin-top: 20px;">
            <h2 class="hndle"><span><?php _e('Recent Deletions', 'user-self-delete'); ?></span></h2>
            <div class="inside">
              <?php $this->display_deletion_log(); ?>
            </div>
          </div>
        <?php endif; ?>

        <div class="postbox" style="margin-top: 20px;">
          <h2 class="hndle"><span><?php _e('GDPR Compliance Information', 'user-self-delete'); ?></span></h2>
          <div class="inside">
            <?php $this->display_gdpr_info(); ?>
          </div>
        </div>
      </div>
    </div>
  <?php
  }

  /**
   * General section callback
   */
  public function general_section_callback()
  {
    echo '<p>' . __('Configure general settings for user account deletion.', 'user-self-delete') . '</p>';
  }

  /**
   * WooCommerce section callback
   */
  public function woocommerce_section_callback()
  {
    echo '<p>' . __('Configure how WooCommerce data is handled during account deletion.', 'user-self-delete') . '</p>';
  }

  /**
   * Content section callback
   */
  public function content_section_callback()
  {
    echo '<p>' . __('Configure how user-generated content is handled during account deletion.', 'user-self-delete') . '</p>';
  }

  /**
   * Checkbox field callback
   */
  public function checkbox_field_callback($args)
  {
    $value = get_option($args['name'], 1);
    $checked = checked(1, $value, false);

    echo "<input type='checkbox' name='{$args['name']}' value='1' {$checked} />";
    if (isset($args['description'])) {
      echo "<p class='description'>{$args['description']}</p>";
    }
  }

  /**
   * Radio field callback
   */
  public function radio_field_callback($args)
  {
    $value = get_option($args['name'], array_keys($args['options'])[0]);

    foreach ($args['options'] as $option_value => $option_label) {
      $checked = checked($option_value, $value, false);
      echo "<label><input type='radio' name='{$args['name']}' value='{$option_value}' {$checked} /> {$option_label}</label><br>";
    }

    if (isset($args['description'])) {
      echo "<p class='description'>{$args['description']}</p>";
    }
  }

  /**
   * Display deletion statistics
   */
  private function display_deletion_stats()
  {
    global $wpdb;

    if (!get_option('user_self_delete_enable_logging', 1)) {
      echo '<p>' . __('Logging is disabled. Enable logging to see statistics.', 'user-self-delete') . '</p>';
      return;
    }

    $table_name = $wpdb->prefix . 'user_self_delete_log';

    // Check if table exists
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
      echo '<p>' . __('No deletion data available.', 'user-self-delete') . '</p>';
      return;
    }

    // Total deletions
    $total = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");

    // This month
    $this_month = $wpdb->get_var($wpdb->prepare(
      "SELECT COUNT(*) FROM $table_name WHERE deletion_date >= %s",
      date('Y-m-01')
    ));

    // This week
    $this_week = $wpdb->get_var($wpdb->prepare(
      "SELECT COUNT(*) FROM $table_name WHERE deletion_date >= %s",
      date('Y-m-d', strtotime('monday this week'))
    ));

    echo '<table class="widefat">';
    echo '<tr><td><strong>' . __('Total Deletions:', 'user-self-delete') . '</strong></td><td>' . $total . '</td></tr>';
    echo '<tr><td><strong>' . __('This Month:', 'user-self-delete') . '</strong></td><td>' . $this_month . '</td></tr>';
    echo '<tr><td><strong>' . __('This Week:', 'user-self-delete') . '</strong></td><td>' . $this_week . '</td></tr>';
    echo '</table>';
  }

  /**
   * Display deletion log
   */
  private function display_deletion_log()
  {
    global $wpdb;

    $table_name = $wpdb->prefix . 'user_self_delete_log';

    // Check if table exists
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
      echo '<p>' . __('No deletion log available.', 'user-self-delete') . '</p>';
      return;
    }

    $results = $wpdb->get_results($wpdb->prepare(
      "SELECT * FROM $table_name ORDER BY deletion_date DESC LIMIT %d",
      10
    ));

    if (empty($results)) {
      echo '<p>' . __('No recent deletions.', 'user-self-delete') . '</p>';
      return;
    }

    echo '<table class="widefat">';
    echo '<thead>';
    echo '<tr>';
    echo '<th>' . __('User ID', 'user-self-delete') . '</th>';
    echo '<th>' . __('Email', 'user-self-delete') . '</th>';
    echo '<th>' . __('Date', 'user-self-delete') . '</th>';
    echo '<th>' . __('IP Address', 'user-self-delete') . '</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';

    foreach ($results as $row) {
      echo '<tr>';
      echo '<td>' . esc_html($row->user_id) . '</td>';
      echo '<td>' . esc_html($row->user_email) . '</td>';
      echo '<td>' . esc_html($row->deletion_date) . '</td>';
      echo '<td>' . esc_html($row->ip_address) . '</td>';
      echo '</tr>';
    }

    echo '</tbody>';
    echo '</table>';
  }

  /**
   * Display GDPR compliance information
   */
  private function display_gdpr_info()
  {
  ?>
    <div class="gdpr-compliance-info">
      <h4><?php _e('GDPR Compliance Features:', 'user-self-delete'); ?></h4>
      <ul>
        <li><strong><?php _e('Right to Erasure (Article 17):', 'user-self-delete'); ?></strong> <?php _e('Users can easily delete their personal data without unnecessary barriers.', 'user-self-delete'); ?></li>
        <li><strong><?php _e('Data Minimization:', 'user-self-delete'); ?></strong> <?php _e('Only essential data verification (password) is required.', 'user-self-delete'); ?></li>
        <li><strong><?php _e('Audit Trail:', 'user-self-delete'); ?></strong> <?php _e('Deletion requests are logged for compliance purposes.', 'user-self-delete'); ?></li>
        <li><strong><?php _e('Legal Basis Preservation:', 'user-self-delete'); ?></strong> <?php _e('Order data can be anonymized to maintain legal compliance while respecting privacy.', 'user-self-delete'); ?></li>
      </ul>

      <h4><?php _e('What Gets Deleted:', 'user-self-delete'); ?></h4>
      <ul>
        <li><?php _e('User account and profile information', 'user-self-delete'); ?></li>
        <li><?php _e('All user metadata', 'user-self-delete'); ?></li>
        <li><?php _e('Personal comments', 'user-self-delete'); ?></li>
        <li><?php _e('WooCommerce customer data (billing, shipping addresses)', 'user-self-delete'); ?></li>
        <li><?php _e('Integration with common plugins (BuddyPress, bbPress, Ultimate Member)', 'user-self-delete'); ?></li>
      </ul>

      <h4><?php _e('Legal Compliance:', 'user-self-delete'); ?></h4>
      <p><?php _e('This plugin helps meet GDPR requirements, but you should consult with legal counsel to ensure full compliance with applicable data protection laws in your jurisdiction.', 'user-self-delete'); ?></p>
    </div>
<?php
  }
}

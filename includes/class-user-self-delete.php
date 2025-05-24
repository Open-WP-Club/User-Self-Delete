<?php

/**
 * Core functionality for user self-delete
 */

if (!defined('ABSPATH')) {
  exit;
}

class User_Self_Delete_Core
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
    add_action('init', array($this, 'init'));
  }

  public function init()
  {
    // Only load for logged-in users
    if (!is_user_logged_in()) {
      return;
    }

    // Add hooks
    add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    add_action('wp_ajax_delete_user_account', array($this, 'handle_account_deletion'));

    // WooCommerce integration
    if (class_exists('WooCommerce')) {
      add_action('woocommerce_account_dashboard', array($this, 'add_delete_button_to_dashboard'));
      add_action('woocommerce_account_content', array($this, 'maybe_add_delete_section'));
    } else {
      // Standard WordPress My Account (if using a profile plugin or custom implementation)
      add_action('show_user_profile', array($this, 'add_delete_button_to_profile'));
      add_action('edit_user_profile', array($this, 'add_delete_button_to_profile'));
    }
  }

  /**
   * Enqueue scripts and styles
   */
  public function enqueue_scripts()
  {
    wp_enqueue_script(
      'user-self-delete',
      USER_SELF_DELETE_PLUGIN_URL . 'assets/js/delete-account.js',
      array('jquery'),
      USER_SELF_DELETE_VERSION,
      true
    );

    wp_enqueue_style(
      'user-self-delete',
      USER_SELF_DELETE_PLUGIN_URL . 'assets/css/style.css',
      array(),
      USER_SELF_DELETE_VERSION
    );

    // Localize script
    wp_localize_script('user-self-delete', 'userSelfDelete', array(
      'ajaxUrl' => admin_url('admin-ajax.php'),
      'nonce' => wp_create_nonce('delete_user_account'),
      'confirmText' => __('Are you sure you want to permanently delete your account? This action cannot be undone.', 'user-self-delete'),
      'passwordLabel' => __('Enter your password to confirm:', 'user-self-delete'),
      'deleteButton' => __('Yes, Delete My Account', 'user-self-delete'),
      'cancelButton' => __('Cancel', 'user-self-delete'),
      'processing' => __('Processing...', 'user-self-delete'),
      'error' => __('An error occurred. Please try again.', 'user-self-delete'),
      'invalidPassword' => __('Invalid password. Please try again.', 'user-self-delete')
    ));
  }

  /**
   * Add delete button to WooCommerce dashboard
   */
  public function add_delete_button_to_dashboard()
  {
    $current_user = wp_get_current_user();

    echo '<div class="user-self-delete-section woocommerce-MyAccount-content">';
    echo '<h3>' . __('Delete Account', 'user-self-delete') . '</h3>';
    echo '<div class="user-delete-info">';
    echo '<p>' . __('You can permanently delete your account and all associated data. This action cannot be undone.', 'user-self-delete') . '</p>';

    // Show what will be deleted
    echo '<div class="deletion-details">';
    echo '<h4>' . __('What will be deleted:', 'user-self-delete') . '</h4>';
    echo '<ul>';
    echo '<li>' . __('Your user account and profile information', 'user-self-delete') . '</li>';
    echo '<li>' . __('Personal data associated with your account', 'user-self-delete') . '</li>';

    if (class_exists('WooCommerce')) {
      if (get_option('user_self_delete_anonymize_orders', 1)) {
        echo '<li>' . __('Order history will be anonymized (required for tax/legal compliance)', 'user-self-delete') . '</li>';
      } else {
        echo '<li>' . __('Order history will be permanently deleted', 'user-self-delete') . '</li>';
      }
    }

    echo '</ul>';
    echo '</div>';

    echo '<button type="button" class="button delete-account-btn" id="delete-account-trigger">';
    echo __('Delete My Account', 'user-self-delete');
    echo '</button>';

    echo '</div>';
    echo '</div>';

    // Add modal HTML
    $this->render_confirmation_modal();
  }

  /**
   * Add delete button to user profile (fallback for non-WooCommerce sites)
   */
  public function add_delete_button_to_profile($user)
  {
    // Only show for users viewing their own profile
    if (get_current_user_id() !== $user->ID) {
      return;
    }

    echo '<h3>' . __('Delete Account', 'user-self-delete') . '</h3>';
    echo '<table class="form-table">';
    echo '<tr>';
    echo '<th><label>' . __('Account Deletion', 'user-self-delete') . '</label></th>';
    echo '<td>';
    echo '<p>' . __('You can permanently delete your account and all associated data.', 'user-self-delete') . '</p>';
    echo '<button type="button" class="button delete-account-btn" id="delete-account-trigger">';
    echo __('Delete My Account', 'user-self-delete');
    echo '</button>';
    echo '</td>';
    echo '</tr>';
    echo '</table>';

    $this->render_confirmation_modal();
  }

  /**
   * Render confirmation modal
   */
  private function render_confirmation_modal()
  {
?>
    <div id="delete-account-modal" class="user-delete-modal" style="display: none;">
      <div class="modal-content">
        <div class="modal-header">
          <h3><?php _e('Confirm Account Deletion', 'user-self-delete'); ?></h3>
          <span class="close-modal">&times;</span>
        </div>
        <div class="modal-body">
          <div class="warning-message">
            <p><strong><?php _e('Warning: This action is permanent and cannot be undone.', 'user-self-delete'); ?></strong></p>
          </div>

          <div class="deletion-info">
            <h4><?php _e('The following data will be permanently deleted:', 'user-self-delete'); ?></h4>
            <ul>
              <li><?php _e('Your user account and profile', 'user-self-delete'); ?></li>
              <li><?php _e('All personal information', 'user-self-delete'); ?></li>
              <li><?php _e('Account preferences and settings', 'user-self-delete'); ?></li>
              <?php if (class_exists('WooCommerce')): ?>
                <li><?php
                    if (get_option('user_self_delete_anonymize_orders', 1)) {
                      _e('Order data will be anonymized (billing/shipping info removed)', 'user-self-delete');
                    } else {
                      _e('All order history', 'user-self-delete');
                    }
                    ?></li>
              <?php endif; ?>
            </ul>
          </div>

          <div class="password-confirmation">
            <label for="confirm-password"><?php _e('Enter your password to confirm:', 'user-self-delete'); ?></label>
            <input type="password" id="confirm-password" name="confirm_password" required>
            <div class="password-error" style="display: none; color: red;"></div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="button" id="cancel-deletion"><?php _e('Cancel', 'user-self-delete'); ?></button>
          <button type="button" class="button button-primary button-delete" id="confirm-deletion" disabled>
            <?php _e('Yes, Delete My Account', 'user-self-delete'); ?>
          </button>
        </div>
      </div>
    </div>
<?php
  }

  /**
   * Handle AJAX account deletion request
   */
  public function handle_account_deletion()
  {
    // Verify nonce
    if (!wp_verify_nonce($_POST['nonce'], 'delete_user_account')) {
      wp_die(__('Security check failed', 'user-self-delete'));
    }

    // Verify user is logged in
    if (!is_user_logged_in()) {
      wp_send_json_error(__('You must be logged in', 'user-self-delete'));
    }

    $current_user = wp_get_current_user();
    $password = sanitize_text_field($_POST['password']);

    // Verify password
    if (!wp_check_password($password, $current_user->user_pass, $current_user->ID)) {
      wp_send_json_error(__('Invalid password', 'user-self-delete'));
    }

    // Log the deletion attempt
    $this->log_deletion_attempt($current_user);

    // Perform the deletion
    $data_eraser = new User_Self_Delete_Data_Eraser();
    $result = $data_eraser->delete_user_data($current_user->ID);

    if ($result['success']) {
      // Send admin notification if enabled
      if (get_option('user_self_delete_admin_notification', 1)) {
        $this->send_admin_notification($current_user);
      }

      wp_send_json_success(array(
        'message' => __('Your account has been successfully deleted.', 'user-self-delete'),
        'redirect' => home_url()
      ));
    } else {
      wp_send_json_error($result['message']);
    }
  }

  /**
   * Log deletion attempt
   */
  private function log_deletion_attempt($user)
  {
    if (!get_option('user_self_delete_enable_logging', 1)) {
      return;
    }

    global $wpdb;

    $table_name = $wpdb->prefix . 'user_self_delete_log';

    $wpdb->insert(
      $table_name,
      array(
        'user_id' => $user->ID,
        'user_email' => $user->user_email,
        'ip_address' => $this->get_user_ip(),
        'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
        'deletion_date' => current_time('mysql')
      ),
      array('%d', '%s', '%s', '%s', '%s')
    );
  }

  /**
   * Get user IP address
   */
  private function get_user_ip()
  {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
      return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
      return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
      return $_SERVER['REMOTE_ADDR'];
    }
  }

  /**
   * Send admin notification
   */
  private function send_admin_notification($user)
  {
    $admin_email = get_option('admin_email');
    $subject = sprintf(__('[%s] User Account Self-Deleted', 'user-self-delete'), get_bloginfo('name'));

    $message = sprintf(
      __("A user has deleted their account:\n\nUser ID: %d\nEmail: %s\nDate: %s\n\nThis is an automated notification.", 'user-self-delete'),
      $user->ID,
      $user->user_email,
      current_time('mysql')
    );

    wp_mail($admin_email, $subject, $message);
  }
}

<?php

/**
 * Handle user data deletion
 */

if (!defined('ABSPATH')) {
  exit;
}

class User_Self_Delete_Data_Eraser
{

  /**
   * Delete user data
   */
  public function delete_user_data($user_id)
  {
    try {
      // Get user data before deletion
      $user = get_user_by('ID', $user_id);
      if (!$user) {
        return array(
          'success' => false,
          'message' => __('User not found', 'user-self-delete')
        );
      }

      // Hook: Before user deletion
      do_action('user_self_delete_before_deletion', $user_id, $user);

      // Handle WooCommerce data if present
      if (class_exists('WooCommerce')) {
        $this->handle_woocommerce_data($user_id);
      }

      // Handle other plugin data
      $this->handle_plugin_data($user_id);

      // Delete WordPress user data
      $this->delete_wordpress_data($user_id);

      // Delete the user account
      if (!wp_delete_user($user_id)) {
        return array(
          'success' => false,
          'message' => __('Failed to delete user account', 'user-self-delete')
        );
      }

      // Hook: After user deletion
      do_action('user_self_delete_after_deletion', $user_id, $user);

      return array(
        'success' => true,
        'message' => __('Account successfully deleted', 'user-self-delete')
      );
    } catch (Exception $e) {
      error_log('User Self Delete Error: ' . $e->getMessage());
      return array(
        'success' => false,
        'message' => __('An error occurred during account deletion', 'user-self-delete')
      );
    }
  }

  /**
   * Handle WooCommerce specific data
   */
  private function handle_woocommerce_data($user_id)
  {
    // Check if we should anonymize or delete orders
    $anonymize_orders = get_option('user_self_delete_anonymize_orders', 1);

    if ($anonymize_orders) {
      $this->anonymize_woocommerce_orders($user_id);
    } else {
      $this->delete_woocommerce_orders($user_id);
    }

    // Delete other WooCommerce customer data
    $this->delete_woocommerce_customer_data($user_id);
  }

  /**
   * Anonymize WooCommerce orders (GDPR compliant)
   */
  private function anonymize_woocommerce_orders($user_id)
  {
    // Get all orders for this user
    $orders = wc_get_orders(array(
      'customer_id' => $user_id,
      'limit' => -1,
      'status' => 'any'
    ));

    foreach ($orders as $order) {
      // Anonymize billing information
      $order->set_billing_first_name('Anonymous');
      $order->set_billing_last_name('Customer');
      $order->set_billing_email('deleted-user@example.com');
      $order->set_billing_phone('');
      $order->set_billing_address_1('Deleted');
      $order->set_billing_address_2('');
      $order->set_billing_city('Deleted');
      $order->set_billing_state('');
      $order->set_billing_postcode('');
      $order->set_billing_country('');
      $order->set_billing_company('');

      // Anonymize shipping information
      $order->set_shipping_first_name('Anonymous');
      $order->set_shipping_last_name('Customer');
      $order->set_shipping_address_1('Deleted');
      $order->set_shipping_address_2('');
      $order->set_shipping_city('Deleted');
      $order->set_shipping_state('');
      $order->set_shipping_postcode('');
      $order->set_shipping_country('');
      $order->set_shipping_company('');

      // Remove customer ID association
      $order->set_customer_id(0);

      // Add note about anonymization
      $order->add_order_note(__('Customer data anonymized due to account deletion request.', 'user-self-delete'));

      // Save changes
      $order->save();
    }
  }

  /**
   * Delete WooCommerce orders completely
   */
  private function delete_woocommerce_orders($user_id)
  {
    // Get all orders for this user
    $orders = wc_get_orders(array(
      'customer_id' => $user_id,
      'limit' => -1,
      'status' => 'any'
    ));

    foreach ($orders as $order) {
      // Force delete the order
      $order->delete(true);
    }
  }

  /**
   * Delete WooCommerce customer data
   */
  private function delete_woocommerce_customer_data($user_id)
  {
    global $wpdb;

    // Delete customer sessions
    $wpdb->delete(
      $wpdb->prefix . 'woocommerce_sessions',
      array('session_key' => $user_id),
      array('%d')
    );

    // Delete customer lookup data
    $wpdb->delete(
      $wpdb->prefix . 'wc_customer_lookup',
      array('user_id' => $user_id),
      array('%d')
    );

    // Delete download permissions
    $wpdb->delete(
      $wpdb->prefix . 'woocommerce_downloadable_product_permissions',
      array('user_id' => $user_id),
      array('%d')
    );

    // Delete payment tokens
    $wpdb->delete(
      $wpdb->prefix . 'woocommerce_payment_tokens',
      array('user_id' => $user_id),
      array('%d')
    );
  }

  /**
   * Handle other plugin data
   */
  private function handle_plugin_data($user_id)
  {
    // Allow other plugins to clean up their data
    do_action('user_self_delete_cleanup_plugin_data', $user_id);

    // Common plugin cleanups
    $this->cleanup_common_plugins($user_id);
  }

  /**
   * Cleanup data from common plugins
   */
  private function cleanup_common_plugins($user_id)
  {
    global $wpdb;

    // BuddyPress
    if (function_exists('bp_is_active')) {
      // Delete activity stream items
      $wpdb->delete(
        $wpdb->prefix . 'bp_activity',
        array('user_id' => $user_id),
        array('%d')
      );

      // Delete profile data
      $wpdb->delete(
        $wpdb->prefix . 'bp_xprofile_data',
        array('user_id' => $user_id),
        array('%d')
      );
    }

    // bbPress
    if (class_exists('bbPress')) {
      // Get user's topics and replies
      $topics = get_posts(array(
        'post_type' => 'topic',
        'author' => $user_id,
        'posts_per_page' => -1
      ));

      $replies = get_posts(array(
        'post_type' => 'reply',
        'author' => $user_id,
        'posts_per_page' => -1
      ));

      // Delete topics and replies
      foreach (array_merge($topics, $replies) as $post) {
        wp_delete_post($post->ID, true);
      }
    }

    // Ultimate Member
    if (class_exists('UM')) {
      // Delete UM specific user meta
      $um_meta_keys = array(
        'um_user_profile_url_slug',
        'um_user_profile_url_slug_meta',
        'um_user_avatar',
        'um_user_cover_photo'
      );

      foreach ($um_meta_keys as $meta_key) {
        delete_user_meta($user_id, $meta_key);
      }
    }
  }

  /**
   * Delete WordPress core user data
   */
  private function delete_wordpress_data($user_id)
  {
    // Delete user posts (if configured to do so)
    if (get_option('user_self_delete_delete_posts', 0)) {
      $posts = get_posts(array(
        'author' => $user_id,
        'posts_per_page' => -1,
        'post_status' => 'any'
      ));

      foreach ($posts as $post) {
        wp_delete_post($post->ID, true);
      }
    } else {
      // Reassign posts to admin or anonymous user
      $admin_user = get_users(array('role' => 'administrator', 'number' => 1));
      if (!empty($admin_user)) {
        $reassign_to = $admin_user[0]->ID;

        $posts = get_posts(array(
          'author' => $user_id,
          'posts_per_page' => -1,
          'post_status' => 'any'
        ));

        foreach ($posts as $post) {
          wp_update_post(array(
            'ID' => $post->ID,
            'post_author' => $reassign_to
          ));
        }
      }
    }

    // Delete comments
    $comments = get_comments(array(
      'user_id' => $user_id,
      'number' => 0
    ));

    foreach ($comments as $comment) {
      wp_delete_comment($comment->comment_ID, true);
    }

    // Delete all user meta
    $all_meta = get_user_meta($user_id);
    foreach ($all_meta as $meta_key => $meta_values) {
      delete_user_meta($user_id, $meta_key);
    }
  }

  /**
   * Get user data summary (for confirmation modal)
   */
  public function get_user_data_summary($user_id)
  {
    $summary = array();

    // Count posts
    $post_count = count_user_posts($user_id);
    if ($post_count > 0) {
      $summary['posts'] = sprintf(
        _n('%d post', '%d posts', $post_count, 'user-self-delete'),
        $post_count
      );
    }

    // Count comments
    $comment_count = get_comments(array(
      'user_id' => $user_id,
      'count' => true
    ));
    if ($comment_count > 0) {
      $summary['comments'] = sprintf(
        _n('%d comment', '%d comments', $comment_count, 'user-self-delete'),
        $comment_count
      );
    }

    // WooCommerce orders
    if (class_exists('WooCommerce')) {
      $orders = wc_get_orders(array(
        'customer_id' => $user_id,
        'limit' => -1,
        'return' => 'ids'
      ));

      if (!empty($orders)) {
        $summary['orders'] = sprintf(
          _n('%d order', '%d orders', count($orders), 'user-self-delete'),
          count($orders)
        );
      }
    }

    return $summary;
  }
}

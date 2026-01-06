<?php
/**
 * Handle user data deletion.
 *
 * @package UserSelfDelete
 * @since 1.0.0
 */

declare(strict_types=1);

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Data Eraser class for handling user data deletion.
 *
 * @since 1.0.0
 */
final class User_Self_Delete_Data_Eraser {

	/**
	 * Delete user data.
	 *
	 * @param int $user_id User ID to delete.
	 * @return array{success: bool, message: string} Deletion result.
	 */
	public function delete_user_data( int $user_id ): array {
		try {
			// Get user data before deletion.
			$user = get_user_by( 'ID', $user_id );
			if ( ! $user instanceof WP_User ) {
				return array(
					'success' => false,
					'message' => __( 'User not found', 'user-self-delete' ),
				);
			}

			// Hook: Before user deletion.
			do_action( 'user_self_delete_before_deletion', $user_id, $user );

			// Handle WooCommerce data if present.
			if ( class_exists( 'WooCommerce' ) ) {
				$this->handle_woocommerce_data( $user_id );
			}

			// Handle other plugin data.
			$this->handle_plugin_data( $user_id );

			// Delete WordPress user data.
			$this->delete_wordpress_data( $user_id );

			// Delete the user account.
			if ( ! wp_delete_user( $user_id ) ) {
				return array(
					'success' => false,
					'message' => __( 'Failed to delete user account', 'user-self-delete' ),
				);
			}

			// Hook: After user deletion.
			do_action( 'user_self_delete_after_deletion', $user_id, $user );

			return array(
				'success' => true,
				'message' => __( 'Account successfully deleted', 'user-self-delete' ),
			);
		} catch ( Exception $e ) {
			error_log( 'User Self Delete Error: ' . $e->getMessage() );
			return array(
				'success' => false,
				'message' => __( 'An error occurred during account deletion', 'user-self-delete' ),
			);
		}
	}

	/**
	 * Handle WooCommerce specific data.
	 *
	 * @param int $user_id User ID.
	 */
	private function handle_woocommerce_data( int $user_id ): void {
		// Check if we should anonymize or delete orders.
		$anonymize_orders = (bool) get_option( 'user_self_delete_anonymize_orders', 1 );

		if ( $anonymize_orders ) {
			$this->anonymize_woocommerce_orders( $user_id );
		} else {
			$this->delete_woocommerce_orders( $user_id );
		}

		// Delete other WooCommerce customer data.
		$this->delete_woocommerce_customer_data( $user_id );
	}

	/**
	 * Anonymize WooCommerce orders (GDPR compliant).
	 *
	 * @param int $user_id User ID.
	 */
	private function anonymize_woocommerce_orders( int $user_id ): void {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return;
		}

		// Get all orders for this user.
		$orders = wc_get_orders(
			array(
				'customer_id' => $user_id,
				'limit'       => -1,
				'status'      => 'any',
			)
		);

		foreach ( $orders as $order ) {
			if ( ! $order instanceof WC_Order ) {
				continue;
			}

			// Anonymize billing information.
			$order->set_billing_first_name( 'Anonymous' );
			$order->set_billing_last_name( 'Customer' );
			$order->set_billing_email( 'deleted-user@example.com' );
			$order->set_billing_phone( '' );
			$order->set_billing_address_1( 'Deleted' );
			$order->set_billing_address_2( '' );
			$order->set_billing_city( 'Deleted' );
			$order->set_billing_state( '' );
			$order->set_billing_postcode( '' );
			$order->set_billing_country( '' );
			$order->set_billing_company( '' );

			// Anonymize shipping information.
			$order->set_shipping_first_name( 'Anonymous' );
			$order->set_shipping_last_name( 'Customer' );
			$order->set_shipping_address_1( 'Deleted' );
			$order->set_shipping_address_2( '' );
			$order->set_shipping_city( 'Deleted' );
			$order->set_shipping_state( '' );
			$order->set_shipping_postcode( '' );
			$order->set_shipping_country( '' );
			$order->set_shipping_company( '' );

			// Remove customer ID association.
			$order->set_customer_id( 0 );

			// Add note about anonymization.
			$order->add_order_note( __( 'Customer data anonymized due to account deletion request.', 'user-self-delete' ) );

			// Save changes.
			$order->save();
		}
	}

	/**
	 * Delete WooCommerce orders completely.
	 *
	 * @param int $user_id User ID.
	 */
	private function delete_woocommerce_orders( int $user_id ): void {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return;
		}

		// Get all orders for this user.
		$orders = wc_get_orders(
			array(
				'customer_id' => $user_id,
				'limit'       => -1,
				'status'      => 'any',
			)
		);

		foreach ( $orders as $order ) {
			if ( $order instanceof WC_Order ) {
				// Force delete the order.
				$order->delete( true );
			}
		}
	}

	/**
	 * Delete WooCommerce customer data.
	 *
	 * @param int $user_id User ID.
	 */
	private function delete_woocommerce_customer_data( int $user_id ): void {
		global $wpdb;

		// Delete customer sessions.
		$wpdb->delete(
			$wpdb->prefix . 'woocommerce_sessions',
			array( 'session_key' => (string) $user_id ),
			array( '%s' )
		);

		// Delete customer lookup data.
		$wpdb->delete(
			$wpdb->prefix . 'wc_customer_lookup',
			array( 'user_id' => $user_id ),
			array( '%d' )
		);

		// Delete download permissions.
		$wpdb->delete(
			$wpdb->prefix . 'woocommerce_downloadable_product_permissions',
			array( 'user_id' => $user_id ),
			array( '%d' )
		);

		// Delete payment tokens.
		$wpdb->delete(
			$wpdb->prefix . 'woocommerce_payment_tokens',
			array( 'user_id' => $user_id ),
			array( '%d' )
		);
	}

	/**
	 * Handle other plugin data.
	 *
	 * @param int $user_id User ID.
	 */
	private function handle_plugin_data( int $user_id ): void {
		// Allow other plugins to clean up their data.
		do_action( 'user_self_delete_cleanup_plugin_data', $user_id );

		// Common plugin cleanups.
		$this->cleanup_common_plugins( $user_id );
	}

	/**
	 * Cleanup data from common plugins.
	 *
	 * @param int $user_id User ID.
	 */
	private function cleanup_common_plugins( int $user_id ): void {
		global $wpdb;

		// BuddyPress.
		if ( function_exists( 'bp_is_active' ) ) {
			// Delete activity stream items.
			$wpdb->delete(
				$wpdb->prefix . 'bp_activity',
				array( 'user_id' => $user_id ),
				array( '%d' )
			);

			// Delete profile data.
			$wpdb->delete(
				$wpdb->prefix . 'bp_xprofile_data',
				array( 'user_id' => $user_id ),
				array( '%d' )
			);
		}

		// bbPress.
		if ( class_exists( 'bbPress' ) ) {
			// Get user's topics.
			$topics = get_posts(
				array(
					'post_type'      => 'topic',
					'author'         => $user_id,
					'posts_per_page' => -1,
					'post_status'    => 'any',
				)
			);

			// Get user's replies.
			$replies = get_posts(
				array(
					'post_type'      => 'reply',
					'author'         => $user_id,
					'posts_per_page' => -1,
					'post_status'    => 'any',
				)
			);

			// Delete topics and replies.
			foreach ( array_merge( $topics, $replies ) as $post ) {
				if ( $post instanceof WP_Post ) {
					wp_delete_post( $post->ID, true );
				}
			}
		}

		// Ultimate Member.
		if ( class_exists( 'UM' ) ) {
			$um_meta_keys = array(
				'um_user_profile_url_slug',
				'um_user_profile_url_slug_meta',
				'um_user_avatar',
				'um_user_cover_photo',
			);

			foreach ( $um_meta_keys as $meta_key ) {
				delete_user_meta( $user_id, $meta_key );
			}
		}
	}

	/**
	 * Delete WordPress core user data.
	 *
	 * @param int $user_id User ID.
	 */
	private function delete_wordpress_data( int $user_id ): void {
		$delete_posts = (bool) get_option( 'user_self_delete_delete_posts', 0 );

		if ( $delete_posts ) {
			// Delete all user posts.
			$this->delete_user_posts( $user_id );
		} else {
			// Reassign posts to admin.
			$this->reassign_user_posts( $user_id );
		}

		// Delete comments.
		$this->delete_user_comments( $user_id );

		// Delete all user meta.
		$this->delete_user_meta( $user_id );
	}

	/**
	 * Delete user posts.
	 *
	 * @param int $user_id User ID.
	 */
	private function delete_user_posts( int $user_id ): void {
		$posts = get_posts(
			array(
				'author'         => $user_id,
				'posts_per_page' => -1,
				'post_status'    => 'any',
				'post_type'      => 'any',
			)
		);

		foreach ( $posts as $post ) {
			if ( $post instanceof WP_Post ) {
				wp_delete_post( $post->ID, true );
			}
		}
	}

	/**
	 * Reassign user posts to admin.
	 *
	 * @param int $user_id User ID.
	 */
	private function reassign_user_posts( int $user_id ): void {
		$admin_user = get_users(
			array(
				'role'   => 'administrator',
				'number' => 1,
			)
		);

		if ( empty( $admin_user ) || ! isset( $admin_user[0] ) || ! $admin_user[0] instanceof WP_User ) {
			return;
		}

		$reassign_to = $admin_user[0]->ID;

		$posts = get_posts(
			array(
				'author'         => $user_id,
				'posts_per_page' => -1,
				'post_status'    => 'any',
				'post_type'      => 'any',
			)
		);

		foreach ( $posts as $post ) {
			if ( $post instanceof WP_Post ) {
				wp_update_post(
					array(
						'ID'          => $post->ID,
						'post_author' => $reassign_to,
					)
				);
			}
		}
	}

	/**
	 * Delete user comments.
	 *
	 * @param int $user_id User ID.
	 */
	private function delete_user_comments( int $user_id ): void {
		$comments = get_comments(
			array(
				'user_id' => $user_id,
				'number'  => 0,
			)
		);

		foreach ( $comments as $comment ) {
			if ( $comment instanceof WP_Comment ) {
				wp_delete_comment( $comment->comment_ID, true );
			}
		}
	}

	/**
	 * Delete all user metadata.
	 *
	 * @param int $user_id User ID.
	 */
	private function delete_user_meta( int $user_id ): void {
		$all_meta = get_user_meta( $user_id );

		if ( ! is_array( $all_meta ) ) {
			return;
		}

		foreach ( array_keys( $all_meta ) as $meta_key ) {
			delete_user_meta( $user_id, $meta_key );
		}
	}

	/**
	 * Get user data summary (for confirmation modal).
	 *
	 * @param int $user_id User ID.
	 * @return array<string, string> Data summary.
	 */
	public function get_user_data_summary( int $user_id ): array {
		$summary = array();

		// Count posts.
		$post_count = count_user_posts( $user_id );
		if ( $post_count > 0 ) {
			$summary['posts'] = sprintf(
				/* translators: %d: Number of posts */
				_n( '%d post', '%d posts', $post_count, 'user-self-delete' ),
				$post_count
			);
		}

		// Count comments.
		$comment_count = get_comments(
			array(
				'user_id' => $user_id,
				'count'   => true,
			)
		);

		if ( is_int( $comment_count ) && $comment_count > 0 ) {
			$summary['comments'] = sprintf(
				/* translators: %d: Number of comments */
				_n( '%d comment', '%d comments', $comment_count, 'user-self-delete' ),
				$comment_count
			);
		}

		// WooCommerce orders.
		if ( class_exists( 'WooCommerce' ) && function_exists( 'wc_get_orders' ) ) {
			$orders = wc_get_orders(
				array(
					'customer_id' => $user_id,
					'limit'       => -1,
					'return'      => 'ids',
				)
			);

			if ( is_array( $orders ) && ! empty( $orders ) ) {
				$summary['orders'] = sprintf(
					/* translators: %d: Number of orders */
					_n( '%d order', '%d orders', count( $orders ), 'user-self-delete' ),
					count( $orders )
				);
			}
		}

		return $summary;
	}
}

<?php
/**
 * Core functionality for user self-delete.
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
 * Core class for user self-delete functionality.
 *
 * @since 1.0.0
 */
final class User_Self_Delete_Core {

	/**
	 * Instance of this class.
	 *
	 * @var User_Self_Delete_Core|null
	 */
	private static ?User_Self_Delete_Core $instance = null;

	/**
	 * Get instance.
	 *
	 * @return User_Self_Delete_Core
	 */
	public static function get_instance(): User_Self_Delete_Core {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'init', array( $this, 'init' ) );

		// Schedule automatic cleanup cron job.
		add_action( 'user_self_delete_cleanup', array( $this, 'run_automatic_cleanup' ) );
	}

	/**
	 * Initialize.
	 */
	public function init(): void {
		// Schedule daily cleanup if not already scheduled.
		if ( ! wp_next_scheduled( 'user_self_delete_cleanup' ) ) {
			wp_schedule_event( time(), 'daily', 'user_self_delete_cleanup' );
		}

		// Prevent soft-deleted users from logging in.
		add_filter( 'wp_authenticate_user', array( $this, 'prevent_deleted_user_login' ), 10, 2 );

		// Only load for logged-in users.
		if ( ! is_user_logged_in() ) {
			return;
		}

		// Add hooks.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_ajax_delete_user_account', array( $this, 'handle_account_deletion' ) );

		// Register REST API endpoints.
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		// WooCommerce integration.
		if ( class_exists( 'WooCommerce' ) ) {
			add_action( 'woocommerce_after_edit_account_form', array( $this, 'add_delete_button_to_account_details' ) );
		} else {
			// Standard WordPress profile.
			add_action( 'show_user_profile', array( $this, 'add_delete_button_to_profile' ) );
			add_action( 'edit_user_profile', array( $this, 'add_delete_button_to_profile' ) );
		}
	}

	/**
	 * Prevent archived users from logging in.
	 *
	 * Note: Archived users are removed from wp_users table, so this is
	 * primarily for edge cases during deletion process or if someone tries
	 * to access a deleted account by ID.
	 *
	 * @param WP_User|WP_Error $user User object or error.
	 * @param string           $password Password being used for login.
	 * @return WP_User|WP_Error
	 */
	public function prevent_deleted_user_login( $user, string $password ) {
		if ( $user instanceof WP_User ) {
			global $wpdb;

			// Check if user exists in archive table.
			$archive_table = $wpdb->prefix . 'user_self_delete_archive';
			$is_archived = (bool) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$archive_table} WHERE original_user_id = %d",
					$user->ID
				)
			);

			if ( $is_archived ) {
				return new WP_Error(
					'deleted_user',
					__( 'This account has been deleted and is no longer accessible.', 'user-self-delete' )
				);
			}
		}

		return $user;
	}

	/**
	 * Register REST API routes.
	 *
	 * @since 2.0.0
	 */
	public function register_rest_routes(): void {
		register_rest_route(
			'user-self-delete/v1',
			'/delete-account',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_delete_account' ),
				'permission_callback' => array( $this, 'rest_permission_check' ),
				'args'                => array(
					'password' => array(
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => function( $param ) {
							return is_string( $param ) && ! empty( $param );
						},
					),
				),
			)
		);

		register_rest_route(
			'user-self-delete/v1',
			'/account-info',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'rest_get_account_info' ),
				'permission_callback' => array( $this, 'rest_permission_check' ),
			)
		);
	}

	/**
	 * REST API permission check.
	 *
	 * @return true|WP_Error
	 */
	public function rest_permission_check(): bool|WP_Error {
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'You must be logged in to access this endpoint.', 'user-self-delete' ),
				array( 'status' => 401 )
			);
		}

		return true;
	}

	/**
	 * REST API get account information.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function rest_get_account_info( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$current_user = wp_get_current_user();
		$data_eraser  = new User_Self_Delete_Data_Eraser();
		$summary      = $data_eraser->get_user_data_summary( $current_user->ID );

		return new WP_REST_Response(
			array(
				'user_id'      => $current_user->ID,
				'user_email'   => $current_user->user_email,
				'display_name' => $current_user->display_name,
				'data_summary' => $summary,
			),
			200
		);
	}

	/**
	 * REST API delete account handler.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function rest_delete_account( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$password     = $request->get_param( 'password' );
		$current_user = wp_get_current_user();

		// Verify password.
		if ( ! wp_check_password( $password, $current_user->user_pass, $current_user->ID ) ) {
			return new WP_Error(
				'invalid_password',
				__( 'Invalid password.', 'user-self-delete' ),
				array( 'status' => 403 )
			);
		}

		// Check if user is admin (prevent admin self-deletion).
		if ( user_can( $current_user, 'manage_options' ) ) {
			return new WP_Error(
				'admin_deletion_prevented',
				__( 'Administrator accounts cannot be self-deleted for security reasons.', 'user-self-delete' ),
				array( 'status' => 403 )
			);
		}

		// Log the deletion attempt.
		$this->log_deletion_attempt( $current_user );

		// Perform the deletion.
		$data_eraser = new User_Self_Delete_Data_Eraser();
		$result      = $data_eraser->delete_user_data( $current_user->ID );

		if ( $result['success'] ) {
			// Send admin notification if enabled.
			if ( get_option( 'user_self_delete_admin_notification', 1 ) ) {
				$this->send_admin_notification( $current_user );
			}

			return new WP_REST_Response(
				array(
					'success'  => true,
					'message'  => __( 'Your account has been successfully deleted.', 'user-self-delete' ),
					'redirect' => home_url(),
				),
				200
			);
		}

		return new WP_Error(
			'deletion_failed',
			$result['message'],
			array( 'status' => 500 )
		);
	}

	/**
	 * Enqueue scripts and styles.
	 */
	public function enqueue_scripts(): void {
		wp_enqueue_script(
			'user-self-delete',
			USER_SELF_DELETE_PLUGIN_URL . 'assets/js/delete-account.js',
			array(),
			USER_SELF_DELETE_VERSION,
			true
		);

		wp_enqueue_style(
			'user-self-delete',
			USER_SELF_DELETE_PLUGIN_URL . 'assets/css/style.css',
			array(),
			USER_SELF_DELETE_VERSION
		);

		// Localize script with security and modern data.
		wp_localize_script(
			'user-self-delete',
			'userSelfDelete',
			array(
				'restUrl'         => esc_url_raw( rest_url( 'user-self-delete/v1' ) ),
				'nonce'           => wp_create_nonce( 'wp_rest' ),
				'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
				'ajaxNonce'       => wp_create_nonce( 'delete_user_account' ),
				'passwordLabel'   => __( 'Please enter your password', 'user-self-delete' ),
				'deleteButton'    => __( 'Delete My Account', 'user-self-delete' ),
				'processing'      => __( 'Processing...', 'user-self-delete' ),
				'error'           => __( 'An error occurred. Please try again.', 'user-self-delete' ),
			)
		);
	}

	/**
	 * Add delete button to WooCommerce account details page.
	 */
	public function add_delete_button_to_account_details(): void {
		echo '<fieldset class="user-self-delete-fieldset">';
		echo '<legend>' . esc_html__( 'Delete Account', 'user-self-delete' ) . '</legend>';
		$this->render_delete_section( false );
		echo '</fieldset>';
	}

	/**
	 * Add delete button to user profile (fallback for non-WooCommerce sites).
	 *
	 * @param WP_User $user User object.
	 */
	public function add_delete_button_to_profile( WP_User $user ): void {
		// Only show for users viewing their own profile.
		if ( get_current_user_id() !== $user->ID ) {
			return;
		}

		echo '<h3>' . esc_html__( 'Delete Account', 'user-self-delete' ) . '</h3>';
		echo '<table class="form-table">';
		echo '<tr>';
		echo '<th><label>' . esc_html__( 'Account Deletion', 'user-self-delete' ) . '</label></th>';
		echo '<td>';
		$this->render_delete_section( false );
		echo '</td>';
		echo '</tr>';
		echo '</table>';
	}

	/**
	 * Render delete account section.
	 *
	 * @param bool $show_wrapper Whether to show the wrapper div.
	 */
	private function render_delete_section( bool $show_wrapper = true ): void {
		if ( $show_wrapper ) {
			echo '<div class="user-self-delete-section woocommerce-MyAccount-content">';
		}

		echo '<div class="user-delete-info">';
		echo '<p>' . esc_html__( 'If you no longer wish to use this account, you can permanently delete it along with all associated data.', 'user-self-delete' ) . '</p>';

		echo '<button type="button" class="button delete-account-btn" id="delete-account-trigger">';
		echo esc_html__( 'Delete My Account', 'user-self-delete' );
		echo '</button>';

		echo '</div>';

		if ( $show_wrapper ) {
			echo '</div>';
		}

		// Add modal HTML.
		$this->render_confirmation_modal();
	}

	/**
	 * Render confirmation modal.
	 */
	private function render_confirmation_modal(): void {
		?>
		<div id="delete-account-modal" class="user-delete-modal" style="display: none;">
			<div class="modal-content">
				<div class="modal-header">
					<h3><?php echo esc_html__( 'Delete Account', 'user-self-delete' ); ?></h3>
					<span class="close-modal">&times;</span>
				</div>
				<div class="modal-body">
					<div class="password-confirmation">
						<label for="confirm-password"><?php echo esc_html__( 'Enter your password to confirm deletion:', 'user-self-delete' ); ?></label>
						<input type="password" id="confirm-password" name="confirm_password" required autocomplete="current-password">
						<div class="password-error" style="display: none;" role="alert"></div>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="button" id="cancel-deletion"><?php echo esc_html__( 'Cancel', 'user-self-delete' ); ?></button>
					<button type="button" class="button button-primary button-delete" id="confirm-deletion" disabled>
						<?php echo esc_html__( 'Delete My Account', 'user-self-delete' ); ?>
					</button>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Handle AJAX account deletion request.
	 */
	public function handle_account_deletion(): void {
		// Verify nonce.
		check_ajax_referer( 'delete_user_account', 'nonce' );

		// Verify user is logged in.
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( __( 'You must be logged in', 'user-self-delete' ) );
		}

		$current_user = wp_get_current_user();
		$password     = isset( $_POST['password'] ) ? sanitize_text_field( wp_unslash( $_POST['password'] ) ) : '';

		// Validate password input.
		if ( empty( $password ) ) {
			wp_send_json_error( __( 'Password is required', 'user-self-delete' ) );
		}

		// Verify password.
		if ( ! wp_check_password( $password, $current_user->user_pass, $current_user->ID ) ) {
			wp_send_json_error( __( 'Invalid password', 'user-self-delete' ) );
		}

		// Check if user is admin (prevent admin self-deletion).
		if ( user_can( $current_user, 'manage_options' ) ) {
			wp_send_json_error( __( 'Administrator accounts cannot be self-deleted for security reasons.', 'user-self-delete' ) );
		}

		// Log the deletion attempt.
		$this->log_deletion_attempt( $current_user );

		// Perform the deletion.
		$data_eraser = new User_Self_Delete_Data_Eraser();
		$result      = $data_eraser->delete_user_data( $current_user->ID );

		if ( $result['success'] ) {
			// Send admin notification if enabled.
			if ( get_option( 'user_self_delete_admin_notification', 1 ) ) {
				$this->send_admin_notification( $current_user );
			}

			wp_send_json_success(
				array(
					'message'  => __( 'Your account has been successfully deleted.', 'user-self-delete' ),
					'redirect' => home_url(),
				)
			);
		} else {
			wp_send_json_error( $result['message'] );
		}
	}

	/**
	 * Log deletion attempt.
	 *
	 * @param WP_User $user User object.
	 */
	private function log_deletion_attempt( WP_User $user ): void {
		if ( ! get_option( 'user_self_delete_enable_logging', 1 ) ) {
			return;
		}

		global $wpdb;

		$table_name = $wpdb->prefix . 'user_self_delete_log';

		$wpdb->insert(
			$table_name,
			array(
				'user_id'       => $user->ID,
				'user_email'    => $user->user_email,
				'ip_address'    => $this->get_user_ip(),
				'user_agent'    => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
				'deletion_date' => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Get user IP address.
	 *
	 * @return string
	 */
	private function get_user_ip(): string {
		$ip_keys = array( 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' );

		foreach ( $ip_keys as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
				// Validate IP address.
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}

		return '0.0.0.0';
	}

	/**
	 * Run automatic cleanup of expired archived users.
	 *
	 * This method is called by the daily cron job to silently cleanup
	 * archived users whose retention period has expired.
	 *
	 * @since 2.0.0
	 */
	public function run_automatic_cleanup(): void {
		global $wpdb;

		// Find archived users with expired retention periods (limit 100 per run for safety).
		$archive_table = $wpdb->prefix . 'user_self_delete_archive';
		$archived_users = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$archive_table}
				WHERE scheduled_deletion_date <= %s
				LIMIT 100",
				gmdate( 'Y-m-d H:i:s' )
			)
		);

		// No expired archived users found, exit silently.
		if ( empty( $archived_users ) ) {
			return;
		}

		// Perform cleanup silently using the data eraser.
		$data_eraser = new User_Self_Delete_Data_Eraser();

		foreach ( $archived_users as $archived_user ) {
			// Force hard delete from archive without any notifications.
			$data_eraser->delete_user_data( $archived_user->original_user_id, true );
		}

		// Log cleanup run if logging is enabled.
		if ( get_option( 'user_self_delete_enable_logging', 1 ) ) {
			update_option( 'user_self_delete_last_cleanup', array(
				'date'  => current_time( 'mysql' ),
				'count' => count( $archived_users ),
			), false );
		}
	}

	/**
	 * Send admin notification.
	 *
	 * @param WP_User $user User object.
	 */
	private function send_admin_notification( WP_User $user ): void {
		$admin_email = get_option( 'admin_email' );
		$subject     = sprintf(
			/* translators: %s: Site name */
			__( '[%s] User Account Self-Deleted', 'user-self-delete' ),
			get_bloginfo( 'name' )
		);

		$message = sprintf(
			/* translators: 1: User ID, 2: User email, 3: Date/time */
			__( "A user has deleted their account:\n\nUser ID: %d\nEmail: %s\nDate: %s\n\nThis is an automated notification.", 'user-self-delete' ),
			$user->ID,
			$user->user_email,
			current_time( 'mysql' )
		);

		wp_mail( $admin_email, $subject, $message );
	}
}

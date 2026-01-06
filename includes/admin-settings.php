<?php
/**
 * Admin settings for User Self Delete.
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
 * Admin settings class.
 *
 * @since 1.0.0
 */
final class User_Self_Delete_Admin {

	/**
	 * Instance of this class.
	 *
	 * @var User_Self_Delete_Admin|null
	 */
	private static ?User_Self_Delete_Admin $instance = null;

	/**
	 * Get instance.
	 *
	 * @return User_Self_Delete_Admin
	 */
	public static function get_instance(): User_Self_Delete_Admin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'init_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );
	}

	/**
	 * Add admin menu.
	 */
	public function add_admin_menu(): void {
		add_options_page(
			__( 'User Self Delete Settings', 'user-self-delete' ),
			__( 'User Self Delete', 'user-self-delete' ),
			'manage_options',
			'user-self-delete',
			array( $this, 'admin_page' )
		);
	}

	/**
	 * Initialize settings.
	 */
	public function init_settings(): void {
		// Register settings.
		register_setting( 'user_self_delete_settings', 'user_self_delete_enable_logging', array(
			'type'              => 'boolean',
			'sanitize_callback' => 'rest_sanitize_boolean',
			'default'           => true,
		) );

		register_setting( 'user_self_delete_settings', 'user_self_delete_admin_notification', array(
			'type'              => 'boolean',
			'sanitize_callback' => 'rest_sanitize_boolean',
			'default'           => true,
		) );

		register_setting( 'user_self_delete_settings', 'user_self_delete_anonymize_orders', array(
			'type'              => 'boolean',
			'sanitize_callback' => 'rest_sanitize_boolean',
			'default'           => true,
		) );

		register_setting( 'user_self_delete_settings', 'user_self_delete_delete_posts', array(
			'type'              => 'boolean',
			'sanitize_callback' => 'rest_sanitize_boolean',
			'default'           => false,
		) );

		// General Settings Section.
		add_settings_section(
			'user_self_delete_general',
			__( 'General Settings', 'user-self-delete' ),
			array( $this, 'general_section_callback' ),
			'user_self_delete_settings'
		);

		// Logging setting.
		add_settings_field(
			'enable_logging',
			__( 'Enable Logging', 'user-self-delete' ),
			array( $this, 'checkbox_field_callback' ),
			'user_self_delete_settings',
			'user_self_delete_general',
			array(
				'name'        => 'user_self_delete_enable_logging',
				'description' => __( 'Log all account deletion attempts for audit purposes.', 'user-self-delete' ),
			)
		);

		// Admin notification setting.
		add_settings_field(
			'admin_notification',
			__( 'Admin Notifications', 'user-self-delete' ),
			array( $this, 'checkbox_field_callback' ),
			'user_self_delete_settings',
			'user_self_delete_general',
			array(
				'name'        => 'user_self_delete_admin_notification',
				'description' => __( 'Send email notification to admin when a user deletes their account.', 'user-self-delete' ),
			)
		);

		// WooCommerce Settings Section (if WooCommerce is active).
		if ( class_exists( 'WooCommerce' ) ) {
			add_settings_section(
				'user_self_delete_woocommerce',
				__( 'WooCommerce Settings', 'user-self-delete' ),
				array( $this, 'woocommerce_section_callback' ),
				'user_self_delete_settings'
			);

			// Anonymize orders setting.
			add_settings_field(
				'anonymize_orders',
				__( 'Handle Orders', 'user-self-delete' ),
				array( $this, 'radio_field_callback' ),
				'user_self_delete_settings',
				'user_self_delete_woocommerce',
				array(
					'name'        => 'user_self_delete_anonymize_orders',
					'options'     => array(
						'1' => __( 'Anonymize orders (recommended for legal compliance)', 'user-self-delete' ),
						'0' => __( 'Delete orders completely', 'user-self-delete' ),
					),
					'description' => __( 'Anonymizing orders removes personal data but keeps order records for tax and legal purposes.', 'user-self-delete' ),
				)
			);
		}

		// Content Settings Section.
		add_settings_section(
			'user_self_delete_content',
			__( 'Content Settings', 'user-self-delete' ),
			array( $this, 'content_section_callback' ),
			'user_self_delete_settings'
		);

		// Delete posts setting.
		add_settings_field(
			'delete_posts',
			__( 'User Posts', 'user-self-delete' ),
			array( $this, 'radio_field_callback' ),
			'user_self_delete_settings',
			'user_self_delete_content',
			array(
				'name'        => 'user_self_delete_delete_posts',
				'options'     => array(
					'0' => __( 'Reassign to administrator (recommended)', 'user-self-delete' ),
					'1' => __( 'Delete permanently', 'user-self-delete' ),
				),
				'description' => __( 'Choose what happens to posts created by users who delete their accounts.', 'user-self-delete' ),
			)
		);
	}

	/**
	 * Enqueue admin scripts.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function admin_scripts( string $hook ): void {
		if ( 'settings_page_user-self-delete' !== $hook ) {
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
	 * Admin page.
	 */
	public function admin_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'user-self-delete' ) );
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'User Self Delete Settings', 'user-self-delete' ); ?></h1>

			<div class="user-self-delete-admin">
				<form method="post" action="options.php">
					<?php
					settings_fields( 'user_self_delete_settings' );
					do_settings_sections( 'user_self_delete_settings' );
					submit_button();
					?>
				</form>

				<div class="postbox" style="margin-top: 20px;">
					<h2 class="hndle"><span><?php echo esc_html__( 'Deletion Statistics', 'user-self-delete' ); ?></span></h2>
					<div class="inside">
						<?php $this->display_deletion_stats(); ?>
					</div>
				</div>

				<?php if ( get_option( 'user_self_delete_enable_logging', 1 ) ) : ?>
					<div class="postbox" style="margin-top: 20px;">
						<h2 class="hndle"><span><?php echo esc_html__( 'Recent Deletions', 'user-self-delete' ); ?></span></h2>
						<div class="inside">
							<?php $this->display_deletion_log(); ?>
						</div>
					</div>
				<?php endif; ?>

				<div class="postbox" style="margin-top: 20px;">
					<h2 class="hndle"><span><?php echo esc_html__( 'GDPR Compliance Information', 'user-self-delete' ); ?></span></h2>
					<div class="inside">
						<?php $this->display_gdpr_info(); ?>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * General section callback.
	 */
	public function general_section_callback(): void {
		echo '<p>' . esc_html__( 'Configure general settings for user account deletion.', 'user-self-delete' ) . '</p>';
	}

	/**
	 * WooCommerce section callback.
	 */
	public function woocommerce_section_callback(): void {
		echo '<p>' . esc_html__( 'Configure how WooCommerce data is handled during account deletion.', 'user-self-delete' ) . '</p>';
	}

	/**
	 * Content section callback.
	 */
	public function content_section_callback(): void {
		echo '<p>' . esc_html__( 'Configure how user-generated content is handled during account deletion.', 'user-self-delete' ) . '</p>';
	}

	/**
	 * Checkbox field callback.
	 *
	 * @param array<string, mixed> $args Field arguments.
	 */
	public function checkbox_field_callback( array $args ): void {
		$value   = get_option( $args['name'], 1 );
		$checked = checked( 1, $value, false );

		printf(
			'<input type="checkbox" name="%s" value="1" %s />',
			esc_attr( $args['name'] ),
			$checked
		);

		if ( isset( $args['description'] ) ) {
			printf( '<p class="description">%s</p>', esc_html( $args['description'] ) );
		}
	}

	/**
	 * Radio field callback.
	 *
	 * @param array<string, mixed> $args Field arguments.
	 */
	public function radio_field_callback( array $args ): void {
		$options = $args['options'] ?? array();
		$value   = get_option( $args['name'], array_key_first( $options ) );

		foreach ( $options as $option_value => $option_label ) {
			$checked = checked( (string) $option_value, (string) $value, false );
			printf(
				'<label><input type="radio" name="%s" value="%s" %s /> %s</label><br>',
				esc_attr( $args['name'] ),
				esc_attr( (string) $option_value ),
				$checked,
				esc_html( $option_label )
			);
		}

		if ( isset( $args['description'] ) ) {
			printf( '<p class="description">%s</p>', esc_html( $args['description'] ) );
		}
	}

	/**
	 * Display deletion statistics.
	 */
	private function display_deletion_stats(): void {
		global $wpdb;

		if ( ! get_option( 'user_self_delete_enable_logging', 1 ) ) {
			echo '<p>' . esc_html__( 'Logging is disabled. Enable logging to see statistics.', 'user-self-delete' ) . '</p>';
			return;
		}

		$table_name = $wpdb->prefix . 'user_self_delete_log';

		// Check if table exists.
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) !== $table_name ) {
			echo '<p>' . esc_html__( 'No deletion data available.', 'user-self-delete' ) . '</p>';
			return;
		}

		// Total deletions.
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );

		// This month.
		$this_month = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table_name} WHERE deletion_date >= %s",
				gmdate( 'Y-m-01' )
			)
		);

		// This week.
		$this_week = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table_name} WHERE deletion_date >= %s",
				gmdate( 'Y-m-d', strtotime( 'monday this week' ) )
			)
		);

		echo '<table class="widefat">';
		printf(
			'<tr><td><strong>%s</strong></td><td>%d</td></tr>',
			esc_html__( 'Total Deletions:', 'user-self-delete' ),
			$total
		);
		printf(
			'<tr><td><strong>%s</strong></td><td>%d</td></tr>',
			esc_html__( 'This Month:', 'user-self-delete' ),
			$this_month
		);
		printf(
			'<tr><td><strong>%s</strong></td><td>%d</td></tr>',
			esc_html__( 'This Week:', 'user-self-delete' ),
			$this_week
		);
		echo '</table>';
	}

	/**
	 * Display deletion log.
	 */
	private function display_deletion_log(): void {
		global $wpdb;

		$table_name = $wpdb->prefix . 'user_self_delete_log';

		// Check if table exists.
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) !== $table_name ) {
			echo '<p>' . esc_html__( 'No deletion log available.', 'user-self-delete' ) . '</p>';
			return;
		}

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} ORDER BY deletion_date DESC LIMIT %d",
				10
			)
		);

		if ( empty( $results ) ) {
			echo '<p>' . esc_html__( 'No recent deletions.', 'user-self-delete' ) . '</p>';
			return;
		}

		echo '<table class="widefat">';
		echo '<thead>';
		echo '<tr>';
		echo '<th>' . esc_html__( 'User ID', 'user-self-delete' ) . '</th>';
		echo '<th>' . esc_html__( 'Email', 'user-self-delete' ) . '</th>';
		echo '<th>' . esc_html__( 'Date', 'user-self-delete' ) . '</th>';
		echo '<th>' . esc_html__( 'IP Address', 'user-self-delete' ) . '</th>';
		echo '</tr>';
		echo '</thead>';
		echo '<tbody>';

		foreach ( $results as $row ) {
			echo '<tr>';
			printf( '<td>%d</td>', (int) $row->user_id );
			printf( '<td>%s</td>', esc_html( $row->user_email ) );
			printf( '<td>%s</td>', esc_html( $row->deletion_date ) );
			printf( '<td>%s</td>', esc_html( $row->ip_address ) );
			echo '</tr>';
		}

		echo '</tbody>';
		echo '</table>';
	}

	/**
	 * Display GDPR compliance information.
	 */
	private function display_gdpr_info(): void {
		?>
		<div class="gdpr-compliance-info">
			<h4><?php echo esc_html__( 'GDPR Compliance Features:', 'user-self-delete' ); ?></h4>
			<ul>
				<li><strong><?php echo esc_html__( 'Right to Erasure (Article 17):', 'user-self-delete' ); ?></strong> <?php echo esc_html__( 'Users can easily delete their personal data without unnecessary barriers.', 'user-self-delete' ); ?></li>
				<li><strong><?php echo esc_html__( 'Data Minimization:', 'user-self-delete' ); ?></strong> <?php echo esc_html__( 'Only essential data verification (password) is required.', 'user-self-delete' ); ?></li>
				<li><strong><?php echo esc_html__( 'Audit Trail:', 'user-self-delete' ); ?></strong> <?php echo esc_html__( 'Deletion requests are logged for compliance purposes.', 'user-self-delete' ); ?></li>
				<li><strong><?php echo esc_html__( 'Legal Basis Preservation:', 'user-self-delete' ); ?></strong> <?php echo esc_html__( 'Order data can be anonymized to maintain legal compliance while respecting privacy.', 'user-self-delete' ); ?></li>
			</ul>

			<h4><?php echo esc_html__( 'What Gets Deleted:', 'user-self-delete' ); ?></h4>
			<ul>
				<li><?php echo esc_html__( 'User account and profile information', 'user-self-delete' ); ?></li>
				<li><?php echo esc_html__( 'All user metadata', 'user-self-delete' ); ?></li>
				<li><?php echo esc_html__( 'Personal comments', 'user-self-delete' ); ?></li>
				<li><?php echo esc_html__( 'WooCommerce customer data (billing, shipping addresses)', 'user-self-delete' ); ?></li>
				<li><?php echo esc_html__( 'Integration with common plugins (BuddyPress, bbPress, Ultimate Member)', 'user-self-delete' ); ?></li>
			</ul>

			<h4><?php echo esc_html__( 'Legal Compliance:', 'user-self-delete' ); ?></h4>
			<p><?php echo esc_html__( 'This plugin helps meet GDPR requirements, but you should consult with legal counsel to ensure full compliance with applicable data protection laws in your jurisdiction.', 'user-self-delete' ); ?></p>
		</div>
		<?php
	}
}

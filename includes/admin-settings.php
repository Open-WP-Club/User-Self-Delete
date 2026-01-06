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
		add_action( 'admin_notices', array( $this, 'activation_notice' ) );

		// Add settings link to plugins page.
		add_filter( 'plugin_action_links_' . USER_SELF_DELETE_PLUGIN_BASENAME, array( $this, 'add_settings_link' ) );

		// Add custom column to users list.
		add_filter( 'manage_users_columns', array( $this, 'add_deletion_status_column' ) );
		add_filter( 'manage_users_custom_column', array( $this, 'render_deletion_status_column' ), 10, 3 );

		// Add styles to user rows.
		add_action( 'admin_footer-users.php', array( $this, 'add_user_list_styles' ) );

		// Modify user display name in admin list.
		add_filter( 'user_row_actions', array( $this, 'modify_deleted_user_actions' ), 10, 2 );
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
	 * Display activation notice.
	 */
	public function activation_notice(): void {
		// Only show to admins.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Check if the activation transient is set.
		if ( ! get_transient( 'user_self_delete_activated' ) ) {
			return;
		}

		// Delete the transient so it only shows once.
		delete_transient( 'user_self_delete_activated' );

		$settings_url = admin_url( 'options-general.php?page=user-self-delete' );
		?>
		<div class="notice notice-success is-dismissible">
			<p>
				<strong><?php esc_html_e( 'User Self Delete is now active!', 'user-self-delete' ); ?></strong>
			</p>
			<p>
				<?php
				printf(
					/* translators: %s: Settings page URL */
					wp_kses_post( __( 'Please <a href="%s">configure your settings</a> to select countries where you have customers and set your data retention preferences.', 'user-self-delete' ) ),
					esc_url( $settings_url )
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Add settings link to plugins page.
	 *
	 * @param array $links Plugin action links.
	 * @return array
	 */
	public function add_settings_link( array $links ): array {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'options-general.php?page=user-self-delete' ) ),
			esc_html__( 'Settings', 'user-self-delete' )
		);

		array_unshift( $links, $settings_link );
		return $links;
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

		register_setting( 'user_self_delete_settings', 'user_self_delete_countries', array(
			'type'              => 'array',
			'sanitize_callback' => array( $this, 'sanitize_countries' ),
			'default'           => array(),
		) );

		register_setting( 'user_self_delete_settings', 'user_self_delete_use_custom_retention', array(
			'type'              => 'boolean',
			'sanitize_callback' => 'rest_sanitize_boolean',
			'default'           => false,
		) );

		register_setting( 'user_self_delete_settings', 'user_self_delete_custom_retention_years', array(
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'default'           => 0,
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

		// Data Retention Settings Section.
		add_settings_section(
			'user_self_delete_retention',
			__( 'Data Retention Settings', 'user-self-delete' ),
			array( $this, 'retention_section_callback' ),
			'user_self_delete_settings'
		);

		// Country selection setting.
		add_settings_field(
			'retention_countries',
			__( 'Countries Where You Sell', 'user-self-delete' ),
			array( $this, 'countries_field_callback' ),
			'user_self_delete_settings',
			'user_self_delete_retention',
			array(
				'name'        => 'user_self_delete_countries',
				'description' => __( 'Select all countries where you have customers. The system will automatically apply the maximum retention period required.', 'user-self-delete' ),
			)
		);

		// Custom retention override.
		add_settings_field(
			'custom_retention',
			__( 'Custom Retention Period', 'user-self-delete' ),
			array( $this, 'custom_retention_field_callback' ),
			'user_self_delete_settings',
			'user_self_delete_retention',
			array(
				'name'        => 'user_self_delete_use_custom_retention',
				'years_name'  => 'user_self_delete_custom_retention_years',
				'description' => __( 'Override automatic calculation with a custom retention period. Useful for compliance with specific regulations.', 'user-self-delete' ),
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
		<div class="wrap user-self-delete-wrap">
			<h1><?php echo esc_html__( 'User Self Delete Settings', 'user-self-delete' ); ?></h1>
			<p class="description" style="font-size: 14px; margin-bottom: 20px;">
				<?php echo esc_html__( 'Allow users to delete their own accounts while staying compliant with GDPR and local data retention laws.', 'user-self-delete' ); ?>
			</p>

			<div class="user-self-delete-admin">
				<!-- Quick Info Box -->
				<div class="notice notice-info" style="padding: 15px; margin: 20px 0;">
					<h3 style="margin-top: 0;"><?php echo esc_html__( 'Quick Start Guide', 'user-self-delete' ); ?></h3>
					<ol style="margin: 10px 0; padding-left: 20px;">
						<li><?php echo esc_html__( 'Select all countries where you have customers', 'user-self-delete' ); ?></li>
						<li><?php echo esc_html__( 'The plugin will automatically calculate the required data retention period', 'user-self-delete' ); ?></li>
						<li><?php echo esc_html__( 'Configure your preferences for handling orders and content', 'user-self-delete' ); ?></li>
						<li><?php echo esc_html__( 'Save your settings', 'user-self-delete' ); ?></li>
					</ol>
					<p style="margin-bottom: 0;">
						<?php
						if ( class_exists( 'WooCommerce' ) ) {
							echo esc_html__( 'Users can delete their accounts from: My Account > Account Details', 'user-self-delete' );
						} else {
							echo esc_html__( 'Users can delete their accounts from their profile page', 'user-self-delete' );
						}
						?>
					</p>
				</div>

				<!-- Settings Form -->
				<div class="postbox">
					<div class="inside">
						<form method="post" action="options.php">
							<?php
							settings_fields( 'user_self_delete_settings' );
							do_settings_sections( 'user_self_delete_settings' );
							submit_button( __( 'Save Settings', 'user-self-delete' ), 'primary large' );
							?>
						</form>
					</div>
				</div>

				<!-- Statistics Cards -->
				<h2 style="margin-top: 30px;"><?php echo esc_html__( 'Account Deletion Overview', 'user-self-delete' ); ?></h2>

				<div class="postbox">
					<h3 class="hndle" style="padding: 15px;"><span><?php echo esc_html__( 'Deletion Statistics', 'user-self-delete' ); ?></span></h3>
					<div class="inside">
						<?php $this->display_deletion_stats(); ?>
					</div>
				</div>

				<?php if ( get_option( 'user_self_delete_enable_logging', 1 ) ) : ?>
					<div class="postbox">
						<h3 class="hndle" style="padding: 15px;"><span><?php echo esc_html__( 'Recent Deletions', 'user-self-delete' ); ?></span></h3>
						<div class="inside">
							<?php $this->display_deletion_log(); ?>
						</div>
					</div>
				<?php endif; ?>

				<!-- GDPR Info -->
				<div class="postbox">
					<h3 class="hndle" style="padding: 15px;"><span><?php echo esc_html__( 'GDPR & Legal Compliance', 'user-self-delete' ); ?></span></h3>
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
		echo '<p class="description" style="font-size: 13px; margin-bottom: 15px;">' . esc_html__( 'Control logging, notifications, and other general behavior for account deletions.', 'user-self-delete' ) . '</p>';
	}

	/**
	 * WooCommerce section callback.
	 */
	public function woocommerce_section_callback(): void {
		echo '<p class="description" style="font-size: 13px; margin-bottom: 15px;">';
		echo esc_html__( 'Choose how to handle WooCommerce orders when users delete their accounts. ', 'user-self-delete' );
		echo '<strong>' . esc_html__( 'Tip:', 'user-self-delete' ) . '</strong> ';
		echo esc_html__( 'Anonymizing orders (recommended) preserves business records for tax compliance while removing personal data.', 'user-self-delete' );
		echo '</p>';
	}

	/**
	 * Content section callback.
	 */
	public function content_section_callback(): void {
		echo '<p class="description" style="font-size: 13px; margin-bottom: 15px;">';
		echo esc_html__( 'Decide what happens to posts, comments, and other content created by users when they delete their accounts.', 'user-self-delete' );
		echo '</p>';
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
	 * Retention section callback.
	 */
	public function retention_section_callback(): void {
		echo '<div class="notice notice-warning inline" style="margin: 0 0 15px 0; padding: 10px;">';
		echo '<p style="margin: 0;"><strong>' . esc_html__( 'Important:', 'user-self-delete' ) . '</strong> ';
		echo esc_html__( 'Select all countries where you have customers. The plugin will automatically use the longest required retention period to ensure legal compliance.', 'user-self-delete' );
		echo '</p>';
		echo '</div>';
		echo '<p class="description" style="font-size: 13px; margin-bottom: 15px;">';
		echo esc_html__( 'Different countries require businesses to keep financial and tax records for varying periods. When a user deletes their account, their data is archived and permanently deleted only after the required retention period expires.', 'user-self-delete' );
		echo '</p>';
	}

	/**
	 * Countries field callback.
	 *
	 * @param array<string, mixed> $args Field arguments.
	 */
	public function countries_field_callback( array $args ): void {
		$selected_countries = (array) get_option( $args['name'], array() );
		$countries_by_region = User_Self_Delete_Retention_Periods::get_countries_by_region();

		echo '<div class="user-self-delete-countries">';

		foreach ( $countries_by_region as $region => $countries ) {
			printf( '<h4>%s</h4>', esc_html( $region ) );
			echo '<div class="country-group">';

			foreach ( $countries as $code => $data ) {
				$checked = in_array( $code, $selected_countries, true ) ? 'checked' : '';
				printf(
					'<label style="display: inline-block; width: 280px; margin-bottom: 5px;"><input type="checkbox" name="%s[]" value="%s" %s /> %s <em>(%d %s)</em></label>',
					esc_attr( $args['name'] ),
					esc_attr( $code ),
					$checked,
					esc_html( $data['name'] ),
					$data['years'],
					esc_html( _n( 'year', 'years', $data['years'], 'user-self-delete' ) )
				);
			}

			echo '</div>';
		}

		echo '</div>';

		// Display current retention period calculation.
		if ( ! empty( $selected_countries ) ) {
			$max_years = User_Self_Delete_Retention_Periods::calculate_max_retention( $selected_countries );
			$max_countries = User_Self_Delete_Retention_Periods::get_max_retention_countries( $selected_countries );

			if ( $max_years > 0 ) {
				echo '<div class="notice notice-info inline" style="margin-top: 15px; padding: 10px;">';
				printf(
					'<p><strong>%s:</strong> %d %s</p>',
					esc_html__( 'Current retention period', 'user-self-delete' ),
					$max_years,
					esc_html( _n( 'year', 'years', $max_years, 'user-self-delete' ) )
				);

				if ( ! empty( $max_countries ) ) {
					printf(
						'<p class="description">%s: %s</p>',
						esc_html__( 'Defined by', 'user-self-delete' ),
						esc_html( implode( ', ', $max_countries ) )
					);
				}
				echo '</div>';
			}
		}

		if ( isset( $args['description'] ) ) {
			printf( '<p class="description">%s</p>', esc_html( $args['description'] ) );
		}
	}

	/**
	 * Custom retention field callback.
	 *
	 * @param array<string, mixed> $args Field arguments.
	 */
	public function custom_retention_field_callback( array $args ): void {
		$use_custom = get_option( $args['name'], false );
		$years      = get_option( $args['years_name'], 0 );
		$checked    = checked( 1, $use_custom, false );

		printf(
			'<label><input type="checkbox" name="%s" value="1" %s /> %s</label><br>',
			esc_attr( $args['name'] ),
			$checked,
			esc_html__( 'Use custom retention period instead of automatic calculation', 'user-self-delete' )
		);

		printf(
			'<input type="number" name="%s" value="%d" min="0" max="99" style="width: 80px; margin-left: 20px;" /> %s',
			esc_attr( $args['years_name'] ),
			$years,
			esc_html__( 'years', 'user-self-delete' )
		);

		if ( isset( $args['description'] ) ) {
			printf( '<p class="description">%s</p>', esc_html( $args['description'] ) );
		}

		echo '<div class="notice notice-warning inline" style="margin-top: 10px; padding: 10px;">';
		echo '<p><strong>' . esc_html__( 'Note:', 'user-self-delete' ) . '</strong> ';
		echo esc_html__( 'Setting this to 0 years will immediately hard-delete user accounts with no retention period. Make sure this complies with your legal obligations.', 'user-self-delete' );
		echo '</p></div>';
	}

	/**
	 * Sanitize countries array.
	 *
	 * @param mixed $input Input value.
	 * @return array<string> Sanitized country codes.
	 */
	public function sanitize_countries( mixed $input ): array {
		if ( ! is_array( $input ) ) {
			return array();
		}

		$valid_countries = array_keys( User_Self_Delete_Retention_Periods::get_countries() );
		$sanitized = array();

		foreach ( $input as $code ) {
			if ( is_string( $code ) && in_array( $code, $valid_countries, true ) ) {
				$sanitized[] = $code;
			}
		}

		return array_unique( $sanitized );
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

	/**
	 * Add deletion status column to users list.
	 *
	 * @param array<string, string> $columns Existing columns.
	 * @return array<string, string> Modified columns.
	 */
	public function add_deletion_status_column( array $columns ): array {
		$columns['deletion_status'] = __( 'Status', 'user-self-delete' );
		return $columns;
	}

	/**
	 * Render deletion status column content.
	 *
	 * @param string $output      Custom column output.
	 * @param string $column_name Column name.
	 * @param int    $user_id     User ID.
	 * @return string Column content.
	 */
	public function render_deletion_status_column( string $output, string $column_name, int $user_id ): string {
		if ( 'deletion_status' !== $column_name ) {
			return $output;
		}

		// Check if user is in archive table.
		global $wpdb;
		$archive_table = $wpdb->prefix . 'user_self_delete_archive';
		$archived_user = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$archive_table} WHERE original_user_id = %d",
				$user_id
			)
		);

		if ( $archived_user ) {
			$output = '<span class="user-deleted-badge" style="background: #dc3232; color: #fff; padding: 2px 8px; border-radius: 3px; font-size: 11px; font-weight: 600;">';
			$output .= esc_html__( 'DELETED', 'user-self-delete' );
			$output .= '</span>';

			if ( $archived_user->scheduled_deletion_date ) {
				$output .= '<br><small style="color: #999;">';
				$output .= sprintf(
					/* translators: %s: Date */
					esc_html__( 'Removed on: %s', 'user-self-delete' ),
					esc_html( gmdate( 'Y-m-d', strtotime( $archived_user->scheduled_deletion_date ) ) )
				);
				$output .= '</small>';
			}
		} else {
			$output = '<span style="color: #46b450;">●</span> ' . esc_html__( 'Active', 'user-self-delete' );
		}

		return $output;
	}

	/**
	 * Modify user row actions for deleted users.
	 *
	 * @param array<string, string> $actions Row actions.
	 * @param WP_User              $user    User object.
	 * @return array<string, string> Modified actions.
	 */
	public function modify_deleted_user_actions( array $actions, WP_User $user ): array {
		global $wpdb;
		$archive_table = $wpdb->prefix . 'user_self_delete_archive';

		// Check if user is archived.
		$is_archived = (bool) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$archive_table} WHERE original_user_id = %d",
				$user->ID
			)
		);

		if ( $is_archived ) {
			// Add info about deletion.
			$actions['deleted_info'] = '<span style="color: #999;">' . esc_html__( 'Archived (no longer in system)', 'user-self-delete' ) . '</span>';

			// Remove edit link for archived users.
			unset( $actions['edit'] );
		}

		return $actions;
	}

	/**
	 * Add styles for deleted users in the user list.
	 *
	 * Note: Archived users are no longer in wp_users table, so they won't appear
	 * in the standard user list. This method is kept for legacy compatibility.
	 */
	public function add_user_list_styles(): void {
		// Archived users are removed from wp_users and stored in archive table.
		// They won't appear in the standard user list, so no styling needed.
	}
}

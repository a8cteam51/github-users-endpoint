<?php
/**
 * Admin interface for GitHub Users Endpoint plugin
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin class for GitHub Users Endpoint
 */
class GitHub_Users_Endpoint_Admin {


	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'init_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		add_action( 'wp_ajax_test_github_connection', array( $this, 'ajax_test_github_connection' ) );
		add_action( 'wp_ajax_get_github_organizations', array( $this, 'ajax_get_github_organizations' ) );
	}

	/**
	 * Add admin menu
	 */
	public function add_admin_menu() {
		add_options_page(
			__( 'GitHub Users Endpoint', 'github-users-endpoint' ),
			__( 'GitHub Users', 'github-users-endpoint' ),
			'manage_options',
			'github-users-endpoint',
			array( $this, 'admin_page' )
		);
	}

	/**
	 * Initialize settings
	 */
	public function init_settings() {
		register_setting(
			'github_users_endpoint_settings',
			'github_users_endpoint_settings',
			array( $this, 'sanitize_settings' )
		);

		add_settings_section(
			'github_users_endpoint_main',
			__( 'GitHub Configuration', 'github-users-endpoint' ),
			array( $this, 'settings_section_callback' ),
			'github-users-endpoint'
		);

		add_settings_field(
			'github_token',
			__( 'GitHub Personal Access Token', 'github-users-endpoint' ),
			array( $this, 'github_token_callback' ),
			'github-users-endpoint',
			'github_users_endpoint_main'
		);

		add_settings_field(
			'organization',
			__( 'GitHub Organization', 'github-users-endpoint' ),
			array( $this, 'organization_callback' ),
			'github-users-endpoint',
			'github_users_endpoint_main'
		);

		add_settings_field(
			'cache_interval',
			__( 'Cache Update Interval (seconds)', 'github-users-endpoint' ),
			array( $this, 'cache_interval_callback' ),
			'github-users-endpoint',
			'github_users_endpoint_main'
		);
	}

	/**
	 * Settings section callback
	 */
	public function settings_section_callback() {
		echo '<p>' . esc_html__( 'Configure your GitHub integration settings below.', 'github-users-endpoint' ) . '</p>';
	}

	/**
	 * GitHub token field callback
	 */
	public function github_token_callback() {
		$options = get_option( 'github_users_endpoint_settings' );
		$token   = isset( $options['github_token'] ) ? $options['github_token'] : '';
		?>
		<input type="password" id="github_token" name="github_users_endpoint_settings[github_token]" value="<?php echo esc_attr( $token ); ?>" class="regular-text" />
		<button type="button" id="test-connection" class="button"><?php esc_html_e( 'Test Connection', 'github-users-endpoint' ); ?></button>
		<div id="connection-status"></div>
		<p class="description">
			<?php esc_html_e( 'Enter your GitHub Personal Access Token. This token requires the "read:org" scope to access organization member lists. You can save the token without testing it first, but testing is recommended to ensure proper functionality.', 'github-users-endpoint' ); ?>
		</p>
		<?php
	}

	/**
	 * Organization field callback
	 */
	public function organization_callback() {
		$options      = get_option( 'github_users_endpoint_settings' );
		$organization = isset( $options['organization'] ) ? $options['organization'] : '';
		$token        = isset( $options['github_token'] ) ? $options['github_token'] : '';
		?>
		<select id="organization" name="github_users_endpoint_settings[organization]" class="regular-text">
			<option value=""><?php esc_html_e( '-- Select Organization --', 'github-users-endpoint' ); ?></option>
			<?php
			// Load organizations if token is set
			if ( ! empty( $token ) ) {
				$api           = new GitHub_Users_Endpoint_API();
				$organizations = $api->get_organizations();

				if ( ! empty( $organizations ) ) {
					foreach ( $organizations as $org ) {
									$selected = ( $organization === $org['username'] ) ? 'selected' : '';
						echo '<option value="' . esc_attr( $org['username'] ) . '" ' . esc_attr( $selected ) . '>' . esc_html( $org['name'] ) . '</option>';
					}
				} else {
					echo '<option value="" disabled>' . esc_html__( 'No organizations found or access denied', 'github-users-endpoint' ) . '</option>';
				}
			}
			?>
		</select>
		<button type="button" id="refresh-organizations" class="button"><?php esc_html_e( 'Refresh Organizations', 'github-users-endpoint' ); ?></button>
		<div id="organizations-status"></div>
		<p class="description">
			<?php esc_html_e( 'Select the GitHub organization to fetch members from. Organizations are loaded from your GitHub account. You need "read:org" permission to access organization member lists.', 'github-users-endpoint' ); ?>
		</p>
		<?php
	}

	/**
	 * Cache interval field callback
	 */
	public function cache_interval_callback() {
		$options  = get_option( 'github_users_endpoint_settings' );
		$interval = isset( $options['cache_interval'] ) ? $options['cache_interval'] : 3600;
		?>
		<input type="number" id="cache_interval" name="github_users_endpoint_settings[cache_interval]" value="<?php echo esc_attr( $interval ); ?>" min="300" step="300" class="small-text" />
		<p class="description">
			<?php esc_html_e( 'How often to update the cache (in seconds). Minimum 300 seconds (5 minutes).', 'github-users-endpoint' ); ?>
		</p>
		<?php
	}

	/**
	 * Sanitize settings
	 */
	public function sanitize_settings( $input ) {
		// Get current settings to preserve existing values
		$current_settings = get_option( 'github_users_endpoint_settings', array() );
		$sanitized        = $current_settings;
		$errors           = array();

		if ( isset( $input['github_token'] ) ) {
			$token                     = sanitize_text_field( trim( $input['github_token'] ) );
			$sanitized['github_token'] = $token;
		}

		if ( isset( $input['organization'] ) ) {
			$organization = sanitize_text_field( trim( $input['organization'] ) );

			// Only validate if organization is not empty (allowing clearing the field)
			if ( ! empty( $organization ) && ! GitHub_Users_Endpoint_Settings::validate_organization( $organization ) ) {
				add_settings_error(
					'github_users_endpoint_settings',
					'invalid_organization',
					__( 'Invalid organization name. Organization names can only contain letters, numbers, hyphens, and underscores.', 'github-users-endpoint' ),
					'error'
				);
				$errors[] = 'invalid_organization';
			} else {
				$sanitized['organization'] = $organization;
			}
		}

		if ( isset( $input['cache_interval'] ) ) {
			$interval = intval( $input['cache_interval'] );

			// Validate cache interval
			if ( $interval < 300 ) {
				add_settings_error(
					'github_users_endpoint_settings',
					'invalid_interval',
					__( 'Cache interval must be at least 300 seconds (5 minutes).', 'github-users-endpoint' ),
					'error'
				);
				$errors[] = 'invalid_interval';
			} else {
				$sanitized['cache_interval'] = $interval;
			}
		}

		// If there are validation errors, return the current settings
		if ( ! empty( $errors ) ) {
			return $current_settings;
		}

		// Invalidate cache when settings change
		add_action( 'update_option_github_users_endpoint_settings', array( $this, 'invalidate_cache_on_settings_change' ), 10, 2 );

		return $sanitized;
	}

	/**
	 * Invalidate cache when settings change
	 */
	public function invalidate_cache_on_settings_change( $old_value, $new_value ) {
		$cache = new GitHub_Users_Endpoint_Cache();
		$cache->invalidate_cache_on_settings_change();
	}

	/**
	 * Enqueue admin scripts
	 */
	public function enqueue_admin_scripts( $hook ) {
		if ( 'settings_page_github-users-endpoint' !== $hook ) {
			return;
		}

		wp_enqueue_script(
			'github-users-endpoint-admin',
			GITHUB_USERS_ENDPOINT_PLUGIN_URL . 'admin/js/admin.js',
			array( 'jquery' ),
			GITHUB_USERS_ENDPOINT_VERSION,
			true
		);

		wp_localize_script(
			'github-users-endpoint-admin',
			'githubUsersEndpoint',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'github_users_endpoint_nonce' ),
				'strings' => array(
					'testing'        => __( 'Testing connection...', 'github-users-endpoint' ),
					'loading'        => __( 'Loading organizations...', 'github-users-endpoint' ),
					'connectionOk'   => __( 'Connection successful!', 'github-users-endpoint' ),
					'connectionFail' => __( 'Connection failed. Please check your token.', 'github-users-endpoint' ),
					'error'          => __( 'An error occurred.', 'github-users-endpoint' ),
				),
			)
		);
	}

	/**
	 * AJAX handler for testing GitHub connection
	 */
	public function ajax_test_github_connection() {
		check_ajax_referer( 'github_users_endpoint_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'github-users-endpoint' ) );
		}

		$api    = new GitHub_Users_Endpoint_API();
		$result = $api->test_connection();

		wp_send_json( $result );
	}

	/**
	 * AJAX handler for getting GitHub organizations
	 */
	public function ajax_get_github_organizations() {
		check_ajax_referer( 'github_users_endpoint_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'github-users-endpoint' ) );
		}

		$api           = new GitHub_Users_Endpoint_API();
		$organizations = $api->get_organizations();

		wp_send_json( $organizations );
	}

	/**
	 * Admin page content
	 */
	public function admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Get cache status
		$cache        = new GitHub_Users_Endpoint_Cache();
		$cache_status = $cache->get_cache_status();
		?>
		<style>
			.status-ok { color: #46b450; font-weight: bold; }
			.status-warning { color: #ffb900; font-weight: bold; }
			.status-error { color: #dc3232; font-weight: bold; }
			#cache-refresh-status { margin-top: 10px; }
			#organizations-status { margin-top: 10px; }
			.connection-status { margin-top: 10px; padding: 8px; border-radius: 3px; }
			.connection-status.success { background-color: #dff0d8; border: 1px solid #d6e9c6; color: #3c763d; }
			.connection-status.error { background-color: #f2dede; border: 1px solid #ebccd1; color: #a94442; }
			.connection-status.warning { background-color: #fcf8e3; border: 1px solid #faebcc; color: #8a6d3b; }
			.cache-status-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 15px 0; }
			.cache-status-item { background: #f9f9f9; padding: 10px; border-radius: 3px; border-left: 4px solid #0073aa; }
			.cache-status-item .label { font-weight: bold; color: #23282d; }
			.cache-status-item .value { margin-top: 5px; }
			.endpoint-info { background: #f7f7f7; padding: 15px; border-radius: 3px; border: 1px solid #ddd; }
			.endpoint-info code { background: #fff; padding: 5px 8px; border-radius: 2px; border: 1px solid #ddd; }
		</style>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			
			<?php
			// Show connection status
			$settings = get_option( 'github_users_endpoint_settings' );
			if ( ! empty( $settings['github_token'] ) ) {
				$api    = new GitHub_Users_Endpoint_API();
				$result = $api->test_connection();
				if ( $result['success'] ) {
					echo '<div class="connection-status success">';
					echo '<p><strong>' . esc_html__( 'GitHub Connection Status:', 'github-users-endpoint' ) . '</strong> ';
					/* translators: %s: GitHub username */
					echo esc_html( sprintf( __( 'Connected as %s', 'github-users-endpoint' ), $result['user'] ) ) . '</p>';
					echo '</div>';
				} else {
					echo '<div class="connection-status error">';
					echo '<p><strong>' . esc_html__( 'GitHub Connection Status:', 'github-users-endpoint' ) . '</strong> ';
					echo esc_html__( 'Connection failed. Please check your token and ensure it has the "read:org" scope.', 'github-users-endpoint' ) . '</p>';
					if ( isset( $result['error'] ) ) {
						echo '<p><em>' . esc_html( $result['error'] ) . '</em></p>';
					}
					echo '</div>';
				}
			} else {
				echo '<div class="connection-status warning">';
				echo '<p><strong>' . esc_html__( 'GitHub Connection Status:', 'github-users-endpoint' ) . '</strong> ';
				echo esc_html__( 'Not configured. Please enter your GitHub Personal Access Token to get started.', 'github-users-endpoint' ) . '</p>';
				echo '</div>';
			}
			?>
			
			<form action="options.php" method="post">
				<?php
				settings_fields( 'github_users_endpoint_settings' );
				do_settings_sections( 'github-users-endpoint' );
				submit_button( __( 'Save Settings', 'github-users-endpoint' ) );
				?>
			</form>
			
			<div class="card">
				<h2><?php esc_html_e( 'Cache Status', 'github-users-endpoint' ); ?></h2>
				<?php if ( $cache_status['has_cache'] ) : ?>
					<div class="cache-status-grid">
						<div class="cache-status-item">
							<div class="label"><?php esc_html_e( 'Status', 'github-users-endpoint' ); ?></div>
							<div class="value">
								<span class="<?php echo $cache_status['is_fresh'] ? 'status-ok' : 'status-warning'; ?>">
									<?php echo $cache_status['is_fresh'] ? esc_html__( 'Fresh', 'github-users-endpoint' ) : esc_html__( 'Stale', 'github-users-endpoint' ); ?>
								</span>
							</div>
						</div>
						<div class="cache-status-item">
							<div class="label"><?php esc_html_e( 'Users Cached', 'github-users-endpoint' ); ?></div>
							<div class="value"><?php echo esc_html( $cache_status['user_count'] ); ?></div>
						</div>
						<div class="cache-status-item">
							<div class="label"><?php esc_html_e( 'Last Updated', 'github-users-endpoint' ); ?></div>
							<div class="value">
								<?php echo $cache_status['last_update'] > 0 ? esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $cache_status['last_update'] ) ) : esc_html__( 'Never', 'github-users-endpoint' ); ?>
							</div>
						</div>
						<div class="cache-status-item">
							<div class="label"><?php esc_html_e( 'Next Update', 'github-users-endpoint' ); ?></div>
							<div class="value">
								<?php
								if ( $cache_status['next_update'] > 0 ) {
									$time_until = $cache_status['next_update'] - time();
									if ( $time_until > 0 ) {
										echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $cache_status['next_update'] ) );
										/* translators: %s: human readable time difference */
										echo '<br><small>' . esc_html( sprintf( __( 'in %s', 'github-users-endpoint' ), human_time_diff( time(), $cache_status['next_update'] ) ) ) . '</small>';
									} else {
										echo esc_html__( 'Due now', 'github-users-endpoint' );
									}
								} else {
									echo esc_html__( 'Not scheduled', 'github-users-endpoint' );
								}
								?>
							</div>
						</div>
					</div>
					<button type="button" id="refresh-cache" class="button button-secondary">
						<?php esc_html_e( 'Refresh Cache Now', 'github-users-endpoint' ); ?>
					</button>
					<div id="cache-refresh-status"></div>
				<?php else : ?>
					<div class="connection-status warning">
						<p><?php esc_html_e( 'No cache data available. Configure your GitHub settings and save to start caching.', 'github-users-endpoint' ); ?></p>
					</div>
				<?php endif; ?>
			</div>
			
			<div class="card">
				<h2><?php esc_html_e( 'API Endpoint', 'github-users-endpoint' ); ?></h2>
				<div class="endpoint-info">
					<p><strong><?php esc_html_e( 'Your GitHub users endpoint is available at:', 'github-users-endpoint' ); ?></strong></p>
					<input type="text" value="<?php echo esc_url( rest_url( 'github-users/v1/members' ) ); ?>" style="width: 100%;" readonly>
					<p><?php esc_html_e( 'This endpoint returns JSON data with GitHub organization member information including usernames, names, and avatar URLs.', 'github-users-endpoint' ); ?></p>
				</div>
			</div>
		</div>
		<?php
	}
}

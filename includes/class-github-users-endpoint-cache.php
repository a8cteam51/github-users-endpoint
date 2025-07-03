<?php
/**
 * Caching system for GitHub Users Endpoint plugin
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cache management class
 */
class GitHub_Users_Endpoint_Cache {

	/**
	 * Cache option name
	 */
	const CACHE_OPTION = 'github_users_endpoint_cache';

	/**
	 * Cron hook name
	 */
	const CRON_HOOK = 'github_users_endpoint_cache_update';

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'init_cron' ) );
		add_action( self::CRON_HOOK, array( $this, 'update_cache' ) );
		add_action( 'wp_ajax_manual_cache_refresh', array( $this, 'ajax_manual_cache_refresh' ) );
	}

	/**
	 * Initialize cron job
	 */
	public function init_cron() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			$settings = GitHub_Users_Endpoint_Settings::get_settings();
			if ( ! empty( $settings['github_token'] ) && ! empty( $settings['organization'] ) ) {
				$this->schedule_cache_update();
			}
		}
	}

	/**
	 * Schedule cache update
	 */
	public function schedule_cache_update() {
		$settings = GitHub_Users_Endpoint_Settings::get_settings();
		$interval = $settings['cache_interval'];

		// Clear any existing schedule
		wp_clear_scheduled_hook( self::CRON_HOOK );

		// Schedule new update using single event
		if ( $interval > 0 ) {
			wp_schedule_single_event( time() + $interval, self::CRON_HOOK );
		}
	}

	/**
	 * Get cached user data
	 */
	public function get_cached_users() {
		$cache = get_option( self::CACHE_OPTION, array() );

		if ( empty( $cache ) || ! isset( $cache['users'] ) ) {
			return array();
		}

		// Check if cache is still valid
		$settings    = GitHub_Users_Endpoint_Settings::get_settings();
		$last_update = isset( $cache['timestamp'] ) ? $cache['timestamp'] : 0;
		$interval    = $settings['cache_interval'];

		if ( ( time() - $last_update ) > $interval ) {
			// Cache is expired, try to update it
			$this->update_cache();

			// Get the updated cache
			$cache = get_option( self::CACHE_OPTION, array() );
		}

		return isset( $cache['users'] ) ? $cache['users'] : array();
	}

	/**
	 * Set cached user data
	 */
	public function set_cached_users( $users ) {
		$cache = array(
			'users'     => $users,
			'timestamp' => time(),
		);

		update_option( self::CACHE_OPTION, $cache );
		GitHub_Users_Endpoint_Settings::update_cache_timestamp();
	}

		/**
	 * Update cache with fresh data from GitHub API
	 */
	public function update_cache() {
		$settings = GitHub_Users_Endpoint_Settings::get_settings();

		if ( empty( $settings['github_token'] ) || empty( $settings['organization'] ) ) {
			$this->log_error( 'Cannot update cache: GitHub token or organization not configured' );
			return false;
		}

		// Validate settings before making API calls
		if ( ! GitHub_Users_Endpoint_Settings::validate_organization( $settings['organization'] ) ) {
			$this->log_error( 'Cannot update cache: Invalid organization name' );
			return false;
		}

		$api = new GitHub_Users_Endpoint_API();

		if ( ! $api->is_token_valid() ) {
			$this->log_error( 'Cannot update cache: Invalid GitHub token' );
			return false;
		}

		$users = $api->get_members_with_details( $settings['organization'] );

		if ( false === $users ) {
			$this->log_error( 'Failed to fetch users from GitHub API' );
			return false;
		}

		// Validate that we got an array of users
		if ( ! is_array( $users ) ) {
			$this->log_error( 'Invalid response format from GitHub API' );
			return false;
		}

		// Validate each user object and transform to output format
		$valid_users = array();
		foreach ( $users as $user ) {
			if ( is_array( $user ) && isset( $user['username'] ) && is_string( $user['username'] ) ) {
				$valid_users[] = array(
					'username' => sanitize_text_field( $user['username'] ),
					'name'     => isset( $user['name'] ) && is_string( $user['name'] ) ? sanitize_text_field( $user['name'] ) : '',
					'avatar'   => isset( $user['avatar'] ) && is_string( $user['avatar'] ) ? esc_url_raw( $user['avatar'] ) : '',
				);
			}
		}

		$this->set_cached_users( $valid_users );
		$this->log_info( 'Cache updated successfully with ' . count( $valid_users ) . ' users' );

		// Reschedule the next update
		$this->schedule_cache_update();

		return true;
	}

	/**
	 * Clear cache
	 */
	public function clear_cache() {
		delete_option( self::CACHE_OPTION );
		GitHub_Users_Endpoint_Settings::update_setting( 'last_cache_update', 0 );
		GitHub_Users_Endpoint_Settings::update_setting( 'next_cache_update', 0 );
		$this->log_info( 'Cache cleared' );
	}

	/**
	 * Get cache status
	 */
	public function get_cache_status() {
		$cache    = get_option( self::CACHE_OPTION, array() );
		$settings = GitHub_Users_Endpoint_Settings::get_settings();

		$status = array(
			'has_cache'       => ! empty( $cache ),
			'user_count'      => isset( $cache['users'] ) ? count( $cache['users'] ) : 0,
			'last_update'     => isset( $cache['timestamp'] ) ? $cache['timestamp'] : 0,
			'next_update'     => $settings['next_cache_update'],
			'interval'        => $settings['cache_interval'],
			'is_fresh'        => false,
			'time_until_next' => 0,
		);

		if ( $status['last_update'] > 0 ) {
			$status['is_fresh']        = ( time() - $status['last_update'] ) < $status['interval'];
			$status['time_until_next'] = max( 0, $status['next_update'] - time() );
		}

		return $status;
	}

	/**
	 * AJAX handler for manual cache refresh
	 */
	public function ajax_manual_cache_refresh() {
		check_ajax_referer( 'github_users_endpoint_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'github-users-endpoint' ) );
		}

		$result = $this->update_cache();

		if ( $result ) {
			$status = $this->get_cache_status();
			wp_send_json_success(
				array(
					'message' => sprintf(
						/* translators: %d: number of users cached */
						__( 'Cache refreshed successfully. %d users cached.', 'github-users-endpoint' ),
						$status['user_count']
					),
					'status'  => $status,
				)
			);
		} else {
			wp_send_json_error(
				array(
					'message' => __( 'Failed to refresh cache. Check your GitHub token and organization settings.', 'github-users-endpoint' ),
				)
			);
		}
	}

	/**
	 * Handle cache invalidation when settings change
	 */
	public function invalidate_cache_on_settings_change() {
		$this->clear_cache();
		$this->schedule_cache_update();
	}

	/**
	 * Log error messages
	 */
	private function log_error( $message ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[GitHub Users Endpoint Cache] ERROR: ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}

	/**
	 * Log info messages
	 */
	private function log_info( $message ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[GitHub Users Endpoint Cache] INFO: ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}

	/**
	 * Cleanup on plugin deactivation
	 */
	public function cleanup() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
		delete_option( self::CACHE_OPTION );
	}
}

// Initialize the cache system
new GitHub_Users_Endpoint_Cache();

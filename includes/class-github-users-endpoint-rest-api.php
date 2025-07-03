<?php
/**
 * REST API endpoint handler for GitHub Users Endpoint plugin
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST API endpoint handler class
 */
class GitHub_Users_Endpoint_REST_API {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST API routes
	 */
	public function register_routes() {
		register_rest_route(
			'github-users/v1',
			'/members',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_members' ),
					'permission_callback' => '__return_true',
					'args'                => array(),
				),
			)
		);
	}

	/**
	 * Get organization members
	 *
	 * @return WP_REST_Response|WP_Error The response object.
	 */
	public function get_members() {
		// Check if plugin is configured
		if ( ! GitHub_Users_Endpoint_Settings::is_configured() ) {
			return new WP_Error(
				'not_configured',
				__( 'GitHub Users Endpoint is not configured. Please configure the plugin in the admin settings.', 'github-users-endpoint' ),
				array( 'status' => 503 )
			);
		}

		// Validate settings
		$settings = GitHub_Users_Endpoint_Settings::get_settings();
		if ( ! GitHub_Users_Endpoint_Settings::validate_organization( $settings['organization'] ) ) {
			return new WP_Error(
				'invalid_organization',
				__( 'Invalid organization name. Please check your configuration.', 'github-users-endpoint' ),
				array( 'status' => 503 )
			);
		}

		$cache = new GitHub_Users_Endpoint_Cache();
		$users = $cache->get_cached_users();

		// If no cached data exists, try to populate cache on first run
		if ( empty( $users ) ) {
			$result = $this->populate_cache_on_first_run();

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			// Get the newly populated cache
			$users = $cache->get_cached_users();
		}

		// If still no users, return empty array with 200 status
		if ( empty( $users ) ) {
			return new WP_REST_Response(
				array(),
				200,
				array(
					'Content-Type'  => 'application/json',
					'Cache-Control' => 'public, max-age=3600',
				)
			);
		}

		// Return users with proper headers
		return new WP_REST_Response(
			$users,
			200,
			array(
				'Content-Type'  => 'application/json',
				'Cache-Control' => 'public, max-age=3600',
			)
		);
	}

	/**
	 * Populate cache on first run if no data exists
	 *
	 * @return true|WP_Error True on success, WP_Error on failure
	 */
	private function populate_cache_on_first_run() {
		$settings = GitHub_Users_Endpoint_Settings::get_settings();

		if ( empty( $settings['github_token'] ) || empty( $settings['organization'] ) ) {
			return new WP_Error(
				'not_configured',
				__( 'GitHub token or organization not configured.', 'github-users-endpoint' ),
				array( 'status' => 503 )
			);
		}

		$api = new GitHub_Users_Endpoint_API();

		// Check if token is valid
		if ( ! $api->is_token_valid() ) {
			return new WP_Error(
				'invalid_token',
				__( 'Invalid GitHub token. Please check your configuration.', 'github-users-endpoint' ),
				array( 'status' => 503 )
			);
		}

		// Try to fetch users from GitHub API
		$users = $api->get_members_with_details( $settings['organization'] );

		if ( false === $users ) {
			// Log the error for debugging
			error_log( 'GitHub Users Endpoint: Failed to fetch users on first run' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

			return new WP_Error(
				'api_error',
				__( 'Unable to fetch data from GitHub API. Please try again later.', 'github-users-endpoint' ),
				array( 'status' => 503 )
			);
		}

		// Cache the users
		$cache = new GitHub_Users_Endpoint_Cache();
		$cache->set_cached_users( $users );

		return true;
	}
}

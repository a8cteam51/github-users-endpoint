<?php
/**
 * Main plugin class for GitHub Users Endpoint
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin class
 */
class GitHub_Users_Endpoint {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'init' ) );
	}

	/**
	 * Initialize the plugin
	 */
	public function init() {
		// Load text domain for internationalization
		load_plugin_textdomain( 'github-users-endpoint', false, dirname( GITHUB_USERS_ENDPOINT_PLUGIN_BASENAME ) . '/languages' );

		// Initialize admin interface
		if ( is_admin() ) {
			new GitHub_Users_Endpoint_Admin();
		}

		// Initialize REST API endpoint
		new GitHub_Users_Endpoint_REST_API();
	}
}

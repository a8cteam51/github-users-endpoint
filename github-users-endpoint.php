<?php
/**
 * Plugin Name: GitHub Users Endpoint
 * Description: Provides a public REST API endpoint to expose GitHub organization member data (usernames, names, and avatars) in JSON format.
 * Version: 1.0.0
 * Author: WordPress.com Special Projects
 * Author URI: https://wpspecialprojects.wordpress.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: github-users-endpoint
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants
define( 'GITHUB_USERS_ENDPOINT_VERSION', '1.0.0' );
define( 'GITHUB_USERS_ENDPOINT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'GITHUB_USERS_ENDPOINT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'GITHUB_USERS_ENDPOINT_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Include required files
require_once GITHUB_USERS_ENDPOINT_PLUGIN_DIR . 'includes/plugin-hooks.php';
require_once GITHUB_USERS_ENDPOINT_PLUGIN_DIR . 'includes/class-github-users-endpoint-settings.php';
require_once GITHUB_USERS_ENDPOINT_PLUGIN_DIR . 'includes/class-github-users-endpoint-api.php';
require_once GITHUB_USERS_ENDPOINT_PLUGIN_DIR . 'includes/class-github-users-endpoint-cache.php';
require_once GITHUB_USERS_ENDPOINT_PLUGIN_DIR . 'includes/class-github-users-endpoint-rest-api.php';
require_once GITHUB_USERS_ENDPOINT_PLUGIN_DIR . 'includes/class-github-users-endpoint.php';
require_once GITHUB_USERS_ENDPOINT_PLUGIN_DIR . 'admin/class-github-users-endpoint-admin.php';

// Initialize the plugin
new GitHub_Users_Endpoint();

// Register activation and deactivation hooks
register_activation_hook( __FILE__, 'github_users_endpoint_activate' );
register_deactivation_hook( __FILE__, 'github_users_endpoint_deactivate' );

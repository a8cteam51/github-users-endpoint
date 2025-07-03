<?php
/**
 * Plugin activation and deactivation hooks
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin activation function
 */
function github_users_endpoint_activate() {
	// Set default options
	$default_options = array(
		'github_token'      => '',
		'organization'      => '',
		'cache_interval'    => 3600, // 1 hour in seconds
		'last_cache_update' => 0,
		'next_cache_update' => 0,
	);

	add_option( 'github_users_endpoint_settings', $default_options );

	// Clear any existing cron jobs
	wp_clear_scheduled_hook( 'github_users_endpoint_cache_update' );
}

/**
 * Plugin deactivation function
 */
function github_users_endpoint_deactivate() {
	// Clear scheduled cron jobs and cache data
	wp_clear_scheduled_hook( 'github_users_endpoint_cache_update' );
	delete_option( 'github_users_endpoint_cache' );
}

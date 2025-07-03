<?php
/**
 * Uninstall script for GitHub Users Endpoint plugin
 *
 * This file is executed when the plugin is deleted from WordPress admin.
 */

// If uninstall not called from WordPress, exit
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Delete plugin options
delete_option( 'github_users_endpoint_settings' );

// Clear any scheduled cron jobs
wp_clear_scheduled_hook( 'github_users_endpoint_cache_update' );

// Delete any cached data
delete_transient( 'github_users_endpoint_cache' );
delete_transient( 'github_users_endpoint_organizations' );

<?php
/**
 * Settings management for GitHub Users Endpoint plugin
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings management class
 */
class GitHub_Users_Endpoint_Settings {


	/**
	 * Get plugin settings
	 */
	public static function get_settings() {
		$defaults = array(
			'github_token'      => '',
			'organization'      => '',
			'cache_interval'    => 3600,
			'last_cache_update' => 0,
			'next_cache_update' => 0,
		);

		$settings = get_option( 'github_users_endpoint_settings', array() );
		return wp_parse_args( $settings, $defaults );
	}

		/**
	 * Get a specific setting
	 */
	public static function get_setting( $key, $default_value = '' ) {
		$settings = self::get_settings();
		return isset( $settings[ $key ] ) ? $settings[ $key ] : $default_value;
	}

	/**
	 * Update a specific setting
	 */
	public static function update_setting( $key, $value ) {
		$settings         = self::get_settings();
		$settings[ $key ] = $value;
		return update_option( 'github_users_endpoint_settings', $settings );
	}

	/**
	 * Update multiple settings
	 */
	public static function update_settings( $new_settings ) {
		$current_settings = self::get_settings();
		$settings         = wp_parse_args( $new_settings, $current_settings );
		return update_option( 'github_users_endpoint_settings', $settings );
	}

	/**
	 * Validate GitHub token format
	 */
	public static function validate_github_token( $token ) {
		if ( empty( $token ) ) {
			return false;
		}

		// Sanitize the token
		$token = sanitize_text_field( trim( $token ) );

		// GitHub tokens are typically 40 characters long (for classic tokens)
		// or start with 'ghp_' for fine-grained tokens
		// or start with 'github_pat_' for fine-grained tokens (newer format)
		if ( strlen( $token ) === 40 ||
			strpos( $token, 'ghp_' ) === 0 ||
			strpos( $token, 'github_pat_' ) === 0 ) {
			return true;
		}

		return false;
	}

	/**
	 * Validate organization name
	 */
	public static function validate_organization( $organization ) {
		if ( empty( $organization ) ) {
			return false;
		}

		// Sanitize the organization name
		$organization = sanitize_text_field( trim( $organization ) );

		// Organization names should be alphanumeric with hyphens and underscores
		// GitHub organization names are case-insensitive and can contain hyphens
		// but not spaces or special characters
		if ( preg_match( '/^[a-zA-Z0-9\-_]+$/', $organization ) && strlen( $organization ) <= 39 ) {
			return true;
		}

		return false;
	}

	/**
	 * Check if settings are complete
	 */
	public static function is_configured() {
		$settings = self::get_settings();
		return ! empty( $settings['github_token'] ) && ! empty( $settings['organization'] );
	}

	/**
	 * Get cache status information
	 */
	public static function get_cache_status() {
		$settings = self::get_settings();

		$last_update = $settings['last_cache_update'];
		$next_update = $settings['next_cache_update'];
		$interval    = $settings['cache_interval'];

		$status = array(
			'last_update'     => $last_update,
			'next_update'     => $next_update,
			'interval'        => $interval,
			'is_fresh'        => false,
			'time_until_next' => 0,
		);

		if ( $last_update > 0 ) {
			$status['is_fresh']        = ( time() - $last_update ) < $interval;
			$status['time_until_next'] = max( 0, $next_update - time() );
		}

		return $status;
	}

	/**
	 * Update cache timestamp
	 */
	public static function update_cache_timestamp() {
		$settings                      = self::get_settings();
		$settings['last_cache_update'] = time();
		$settings['next_cache_update'] = time() + $settings['cache_interval'];

		return self::update_settings( $settings );
	}
}

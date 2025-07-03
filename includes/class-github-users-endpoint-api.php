<?php
/**
 * GitHub API client for GitHub Users Endpoint plugin
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GitHub API client class
 */
class GitHub_Users_Endpoint_API {

	/**
	 * GitHub API base URL
	 */
	const API_BASE_URL = 'https://api.github.com';

	/**
	 * GitHub token for authentication
	 */
	private $token;

		/**
	 * Constructor
	 */
	public function __construct() {
		$this->token = GitHub_Users_Endpoint_Settings::get_setting( 'github_token' );
	}

	/**
	 * Make a request to the GitHub API
	 */
	private function make_request( $endpoint, $method = 'GET', $data = null ) {
		$url = self::API_BASE_URL . $endpoint;

		$args = array(
			'method'  => $method,
			'headers' => array(
				'Accept'        => 'application/vnd.github.v3+json',
				'User-Agent'    => 'WordPress-GitHub-Users-Endpoint-Plugin',
				'Authorization' => 'token ' . $this->token,
			),
			'timeout' => 30,
		);

		if ( $data && in_array( $method, array( 'POST', 'PUT', 'PATCH' ), true ) ) {
			$args['body']                    = wp_json_encode( $data );
			$args['headers']['Content-Type'] = 'application/json';
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			$this->log_error( 'GitHub API request failed: ' . $response->get_error_message() );
			return false;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$headers     = wp_remote_retrieve_headers( $response );

		// Check for rate limiting
		if ( 403 === $status_code && isset( $headers['x-ratelimit-remaining'] ) ) {
			$remaining = $headers['x-ratelimit-remaining'];
			if ( '0' === $remaining ) {
				$reset_time = isset( $headers['x-ratelimit-reset'] ) ? $headers['x-ratelimit-reset'] : 0;
				$this->log_error( 'GitHub API rate limit exceeded. Reset time: ' . gmdate( 'Y-m-d H:i:s', $reset_time ) );
				return false;
			}
		}

		// Handle different status codes
		switch ( $status_code ) {
			case 200:
			case 201:
				return json_decode( $body, true );
			case 401:
				$this->log_error( 'GitHub API authentication failed. Please check your token.' );
				return false;
			case 403:
				$this->log_error( 'GitHub API access forbidden. Please check your token permissions.' );
				return false;
			case 404:
				$this->log_error( 'GitHub API resource not found: ' . $endpoint );
				return false;
			case 422:
				$this->log_error( 'GitHub API validation error: ' . $body );
				return false;
			default:
				$this->log_error( 'GitHub API request failed with status ' . $status_code . ': ' . $body );
				return false;
		}
	}

	/**
	 * Test API connectivity
	 */
	public function test_connection() {
		$response = $this->make_request( '/user' );

		if ( $response && isset( $response['login'] ) ) {
			return array(
				'success' => true,
				'user'    => $response['login'],
				'name'    => isset( $response['name'] ) ? $response['name'] : '',
			);
		}

		return array(
			'success' => false,
			'error'   => 'Failed to authenticate with GitHub API',
		);
	}

	/**
	 * Get list of organizations the user can access with pagination
	 */
	public function get_organizations() {
		$response = $this->make_paginated_request( '/user/orgs' );

		if ( ! $response ) {
			return array();
		}

		$organizations = array();

		foreach ( $response as $org ) {
			// Include all organizations the user is a member of
			if ( isset( $org['login'] ) ) {
				$organizations[] = array(
					'username' => $org['login'],
					'name'     => isset( $org['name'] ) ? $org['name'] : $org['login'],
					'type'     => isset( $org['type'] ) ? $org['type'] : 'Organization',
				);
			}
		}

		$this->log_info( 'Fetched ' . count( $organizations ) . ' organizations' );

		return $organizations;
	}

	/**
	 * Make a paginated request to the GitHub API
	 */
	private function make_paginated_request( $endpoint, $per_page = 100 ) {
		$all_results = array();
		$page        = 1;
		$has_more    = true;

		while ( $has_more ) {
			$paged_endpoint = add_query_arg(
				array(
					'page'     => $page,
					'per_page' => $per_page,
				),
				$endpoint
			);

			$response = $this->make_request( $paged_endpoint );

			if ( false === $response ) {
				$this->log_error( 'Failed to fetch page ' . $page . ' for endpoint: ' . $endpoint );
				break;
			}

			if ( ! is_array( $response ) ) {
				$this->log_error( 'Invalid response format from GitHub API on page ' . $page );
				break;
			}

			// If we get an empty array, we've reached the end
			if ( empty( $response ) ) {
				$has_more = false;
			} else {
				$all_results = array_merge( $all_results, $response );

				// If we got fewer results than requested, we've reached the end
				if ( count( $response ) < $per_page ) {
					$has_more = false;
				} else {
					++$page;
				}
			}

			// Add a small delay to be respectful to the API
			if ( $has_more ) {
				usleep( 100000 ); // 0.1 second delay
			}
		}

		return $all_results;
	}

	/**
	 * Get organization members with pagination
	 */
	public function get_organization_members( $organization ) {
		if ( empty( $organization ) ) {
			$this->log_error( 'Organization name is required' );
			return false;
		}

		// Validate organization name
		if ( ! GitHub_Users_Endpoint_Settings::validate_organization( $organization ) ) {
			$this->log_error( 'Invalid organization name: ' . $organization );
			return false;
		}

		$endpoint = '/orgs/' . rawurlencode( $organization ) . '/members';
		$response = $this->make_paginated_request( $endpoint );

		if ( false === $response ) {
			return false;
		}

		$members = array();
		foreach ( $response as $member ) {
			if ( isset( $member['login'] ) && is_string( $member['login'] ) ) {
				$members[] = array(
					'login'      => sanitize_text_field( $member['login'] ),
					'name'       => isset( $member['name'] ) && is_string( $member['name'] ) ? sanitize_text_field( $member['name'] ) : '',
					'avatar_url' => isset( $member['avatar_url'] ) && is_string( $member['avatar_url'] ) ? esc_url_raw( $member['avatar_url'] ) : '',
				);
			}
		}

		$this->log_info( 'Fetched ' . count( $members ) . ' members from organization: ' . $organization );

		return $members;
	}

	/**
	 * Get detailed user information for each member
	 */
	public function get_members_with_details( $organization ) {
		$members = $this->get_organization_members( $organization );

		if ( false === $members ) {
			return false;
		}

		if ( empty( $members ) ) {
			$this->log_info( 'No members found in organization: ' . $organization );
			return array();
		}

		$this->log_info( 'Fetching detailed information for ' . count( $members ) . ' members from organization: ' . $organization );

		$detailed_members = array();
		$rate_limit_hit   = false;
		$processed_count  = 0;

		foreach ( $members as $member ) {
			++$processed_count;

			// Check if we're hitting rate limits
			if ( $rate_limit_hit ) {
				// Use basic member info if we hit rate limits
				$detailed_members[] = $member;
				continue;
			}

			$user_details = $this->get_user_details( $member['login'] );
			if ( $user_details ) {
				$detailed_members[] = array(
					'username' => $member['login'],
					'name'     => $user_details['name'] ? $user_details['name'] : $member['login'],
					'avatar'   => $user_details['avatar'] ? $user_details['avatar'] : $member['avatar_url'],
				);
			} else {
				// Check if we hit rate limits
				$response = $this->make_request( '/rate_limit' );
				if ( $response && isset( $response['resources']['core']['remaining'] ) && $response['resources']['core']['remaining'] <= 0 ) {
					$rate_limit_hit = true;
					$this->log_error( 'Rate limit hit while fetching user details at member ' . $processed_count . ' of ' . count( $members ) . '. Using basic member info for remaining users.' );
				}

				// Fallback to basic member info if detailed fetch fails
				$detailed_members[] = $member;
			}

			// Add a small delay between requests to be respectful to the API
			if ( 0 === $processed_count % 10 ) {
				usleep( 50000 ); // 0.05 second delay every 10 requests
			}
		}

		$this->log_info( 'Successfully processed ' . count( $detailed_members ) . ' members with details from organization: ' . $organization );

		return $detailed_members;
	}

	/**
	 * Get detailed user information
	 */
	private function get_user_details( $username ) {
		$response = $this->make_request( '/users/' . rawurlencode( $username ) );

		if ( $response && isset( $response['login'] ) ) {
			return array(
				'username' => $response['login'],
				'name'     => isset( $response['name'] ) ? $response['name'] : '',
				'avatar'   => isset( $response['avatar_url'] ) ? $response['avatar_url'] : '',
			);
		}

		return false;
	}

	/**
	 * Log error messages
	 */
	private function log_error( $message ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[GitHub Users Endpoint] ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}

	/**
	 * Log info messages
	 */
	private function log_info( $message ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[GitHub Users Endpoint] INFO: ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}

	/**
	 * Check if token is valid
	 */
	public function is_token_valid() {
		return ! empty( $this->token ) && $this->test_connection()['success'];
	}
}

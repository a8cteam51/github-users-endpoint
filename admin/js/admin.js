/**
 * Admin JavaScript for GitHub Users Endpoint plugin
 */
(function ($) {
	'use strict';

	$(document).ready(function () {
		// Test GitHub connection
		$('#test-connection').on('click', function (e) {
			e.preventDefault();
			testGitHubConnection();
		});

		// Refresh organizations
		$('#refresh-organizations').on('click', function (e) {
			e.preventDefault();
			refreshOrganizations();
		});

		// Note: Removed automatic testing on blur to allow saving without testing

		// Manual cache refresh
		$('#refresh-cache').on('click', function (e) {
			e.preventDefault();
			refreshCache();
		});
	});

	/**
	 * Test GitHub API connection
	 */
	function testGitHubConnection() {
		const $button = $('#test-connection');
		const $status = $('#connection-status');

		if (!$button.length) {
			return;
		}

		$button.prop('disabled', true).text(githubUsersEndpoint.strings.testing);
		$status.html('<div class="connection-status warning">Testing connection...</div>');

		$.ajax({
			url: githubUsersEndpoint.ajaxUrl,
			type: 'POST',
			data: {
				action: 'test_github_connection',
				nonce: githubUsersEndpoint.nonce
			},
			success: function (response) {
				if (response.success) {
					$status.html('<div class="connection-status success">✓ ' + githubUsersEndpoint.strings.connectionOk + ' (' + response.user + ')</div>');
					// Refresh organizations after successful connection
					refreshOrganizations();
				} else {
					$status.html('<div class="connection-status error">✗ ' + githubUsersEndpoint.strings.connectionFail + '</div>');
				}
			},
			error: function () {
				$status.html('<div class="connection-status error">✗ ' + githubUsersEndpoint.strings.error + '</div>');
			},
			complete: function () {
				$button.prop('disabled', false).text('Test Connection');
			}
		});
	}

	/**
	 * Refresh GitHub organizations
	 */
	function refreshOrganizations() {
		const $select = $('#organization');
		const $button = $('#refresh-organizations');
		const $status = $('#organizations-status');

		if (!$select.length) {
			return;
		}

		$button.prop('disabled', true).text(githubUsersEndpoint.strings.loading);
		$status.html('<div class="connection-status warning">Loading organizations...</div>');

		$.ajax({
			url: githubUsersEndpoint.ajaxUrl,
			type: 'POST',
			data: {
				action: 'get_github_organizations',
				nonce: githubUsersEndpoint.nonce
			},
			success: function (response) {
				$select.empty();
				$select.append('<option value="">-- Select Organization --</option>');

				if (response && response.length > 0) {
					$.each(response, function (index, org) {
						$select.append('<option value="' + org.username + '">' + org.name + '</option>');
					});
					$status.html('<div class="connection-status success">✓ ' + response.length + ' organization(s) loaded successfully</div>');
				} else {
					$select.append('<option value="" disabled>No organizations found or access denied</option>');
					$status.html('<div class="connection-status error">✗ No organizations found. Please check your GitHub token permissions.</div>');
				}
			},
			error: function () {
				$select.empty();
				$select.append('<option value="">-- Select Organization --</option>');
				$select.append('<option value="" disabled>Failed to load organizations</option>');
				$status.html('<div class="connection-status error">✗ Failed to load organizations. Please check your connection.</div>');
				console.error('Failed to load organizations');
			},
			complete: function () {
				$button.prop('disabled', false).text('Refresh Organizations');
			}
		});
	}

	/**
	 * Refresh cache manually
	 */
	function refreshCache() {
		const $button = $('#refresh-cache');
		const $status = $('#cache-refresh-status');

		if (!$button.length) {
			return;
		}

		$button.prop('disabled', true).text('Refreshing...');
		$status.html('<div class="connection-status warning">Refreshing cache...</div>');

		$.ajax({
			url: githubUsersEndpoint.ajaxUrl,
			type: 'POST',
			data: {
				action: 'manual_cache_refresh',
				nonce: githubUsersEndpoint.nonce
			},
			success: function (response) {
				if (response.success) {
					$status.html('<div class="connection-status success">✓ ' + response.data.message + '</div>');
					// Reload page to show updated cache status
					setTimeout(function () {
						location.reload();
					}, 2000);
				} else {
					$status.html('<div class="connection-status error">✗ ' + response.data.message + '</div>');
				}
			},
			error: function () {
				$status.html('<div class="connection-status error">✗ An error occurred while refreshing cache</div>');
			},
			complete: function () {
				$button.prop('disabled', false).text('Refresh Cache Now');
			}
		});
	}

})(jQuery); 
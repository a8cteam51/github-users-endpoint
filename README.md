# GitHub Users Endpoint WordPress Plugin

A WordPress plugin that provides a public REST API endpoint to expose GitHub organization member data (usernames, names, and avatars) in JSON format.

## Description

This plugin allows external applications or websites to easily access and display GitHub team member information without directly integrating with GitHub's API. It includes caching to avoid API rate limits and provides an admin interface for easy configuration.

## Features

- **Public REST API Endpoint**: Accessible at `/wp-json/github-users/v1/members`
- **GitHub Integration**: Uses GitHub Personal Access Token for authentication
- **Organization Selection**: Choose from organizations you have access to
- **Automatic Caching**: Configurable cache intervals to avoid API rate limits
- **Admin Interface**: Easy setup and management through WordPress admin
- **Error Handling**: Comprehensive error handling and user feedback
- **Rate Limit Protection**: Graceful handling of GitHub API rate limits

## Installation

1. Upload the plugin files to `/wp-content/plugins/github-users-endpoint/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Settings > GitHub Users to configure the plugin

## Configuration

### 1. Create a GitHub Personal Access Token

1. Go to GitHub Settings > Developer settings > Personal access tokens
2. Click "Generate new token (classic)"
3. Give it a descriptive name (e.g., "WordPress GitHub Users Endpoint")
4. Select the `read:org` scope (required to access organization member lists)
5. Click "Generate token" and copy the token

### 2. Configure the Plugin

1. In WordPress admin, go to Settings > GitHub Users
2. Enter your GitHub Personal Access Token
3. Click "Test Connection" to verify your token works
4. Select your organization from the dropdown
5. Configure the cache interval (default: 1 hour)
6. Save settings

## API Usage

### Endpoint

```
GET /wp-json/github-users/v1/members
```

### Response Format

The endpoint returns a JSON array of GitHub users:

```json
[
  {
    "username": "username",
    "name": "Full Name",
    "avatar": "https://avatars.githubusercontent.com/u/12345?v=4"
  }
]
```

## Caching

The plugin caches GitHub user data to avoid hitting API rate limits. The cache is automatically updated based on the configured interval (default: 1 hour).

## Troubleshooting

### Common Issues

#### "Connection failed" Error
- Verify your GitHub token is correct
- Ensure the token has the `read:org` scope
- Check if the token has expired

#### "No organizations found" Error
- Verify your GitHub account has access to organizations
- Ensure your token has the `read:org` scope
- Check if you're a member of any organizations

#### Empty Response from API
- Verify the organization name is correct
- Check if the organization has any public members
- Ensure your token has access to the organization

#### Rate Limit Errors
- The plugin automatically handles rate limits by using cached data
- Increase the cache interval to reduce API calls
- Check your GitHub API rate limit status

## Changelog

### Version 1.0.0
- Initial release
- GitHub API integration
- Caching system
- Admin interface
- REST API endpoint
- Comprehensive error handling
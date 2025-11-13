# 829 Studios WordPress Tools

A comprehensive WordPress security and management plugin designed to enhance site security, streamline authentication, and provide centralized control over critical WordPress features.

## Features

- Single Sign-On (SSO) with 829 Studios authentication
- Centralized 829 Settings page for all plugin configurations
- Just-in-Time (JIT) user provisioning
- Dynamic role assignment
- Ability to disable comments
- Ability to disable post passwords
- Security hardening features
- Environment indicator in admin toolbar
- Automatic environment detection (Production/Staging/Development)


## Security

This plugin provides multiple layers of security protection:

### Authentication & Access Control
- **829 Studios SSO**: Secure single sign-on integration for 829 Studios team members
- **Strong Password Enforcement**:
  - Validates password strength using the Zxcvbn library (medium strength or greater required)
  - Checks passwords against the Have I Been Pwned API to prevent compromised passwords
  - Prevents use of common weak passwords (123456, password, etc.)
  - Forces users with weak passwords to reset before accessing the site
- **Reserved Username Protection**: Blocks authentication with common/generic usernames (admin, root, test, etc.) to prevent brute force attacks
- **SSO-Only Account Enforcement**: Users created via SSO can only login through SSO, preventing password-based attacks

### Site Hardening
- **Disable File Modifications**: Dashboard option to set `DISALLOW_FILE_MODS` constant to prevent:
  - Plugin installations and updates
  - Theme installations and updates
  - File editing through the WordPress admin
- **Security Headers**: Automatic `X-Frame-Options` header set to `SAMEORIGIN` to prevent clickjacking attacks
- **Password Protection Control**: Disallow post passwords which inherently don't work with caching.
- **REST API Restriction**: Configurable REST API access control with three levels:
  - Restrict all REST API access to authenticated users only
  - Restrict only the users endpoint to authenticated users (default)
  - Allow public access to all REST API endpoints


### Access Management
- **Restricted Settings Access**: 829 Settings page only accessible to:
  - Users with @829llc.com email addresses (production)
  - Any administrator (local development environments)
- **Network/Multisite Support**: All settings work seamlessly in both single-site and network-activated configurations


## Admin Customizations

### Environment Indicator
The plugin automatically adds a color-coded environment indicator to the WordPress admin toolbar, making it easy to identify which environment you're working in:

- **🔴 Production** (Red): Live production sites
- **🟡 Staging** (Orange): Staging environments (automatically detected for *.829dev.com and *.wpenginepowered.com domains)
- **🟢 Development** (Green): Local development environments

The environment type is automatically detected based on domain or can be set via the `WP_ENVIRONMENT_TYPE` constant in `wp-config.php`.

### Other Customizations
- Custom admin footer text crediting WordPress and 829 Studios


## Requirements

- PHP 7.4 or higher
- WordPress 5.0 or higher
- Composer for dependency management

## Installation

1. Install dependencies: `composer install`
2. Activate the plugin through WordPress admin or network admin (for multisite)
3. Configure settings in **Settings → 829 Settings** (or **Network Admin → Settings → 829 Settings** for multisite)

## Configuration

All settings are managed through the centralized **829 Settings** page:

- **Allow 829 Studios SSO**: Enable/disable SSO authentication
- **Disable Comments**: Remove comment functionality site-wide
- **Require Strong Passwords**: Enforce strong password requirements for all users
- **Enable Password Protected Content**: Allow password protected posts/pages
- **Disable File Modifications**: Prevent plugin/theme installations and updates
- **REST API Availability**: Control access to WordPress REST API endpoints

## License

MIT

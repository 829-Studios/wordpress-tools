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
  - Users created via SSO can only login through SSO, preventing password-based attacks
  - Manually created @829llc.com accounts can still use password login
- **Two-Factor Authentication Enforcement**: Requires 2FA for non-829 users (when [Two-Factor plugin](https://wordpress.org/plugins/two-factor/) is active)
  - Non-SSO users without 2FA enabled have all capabilities restricted to read-only
  - Users are redirected to their profile page to set up 2FA
  - Only `read` capability is allowed until 2FA is configured
  - 829 Studios accounts (@829llc.com) are exempt as they use SSO
- **Strong Password Enforcement**:
  - Validates password strength using the Zxcvbn library (medium strength or greater required)
  - Checks passwords against the Have I Been Pwned API to prevent compromised passwords
  - Prevents use of common weak passwords (123456, password, etc.)
  - Forces users with weak passwords to reset before accessing the site
- **Reserved Username Protection**: Blocks authentication with common/generic usernames (admin, root, test, etc.) to prevent brute force attacks
- **Login Attempt Limiting**: Prevents brute force attacks by:
  - Limiting login attempts (default: 10) per IP address within a 5-minute window
  - Locking out IP addresses (default: 15 minutes) after exceeding the limit
  - Using transients for performance (no permanent database bloat)
  - Automatically clearing limits after successful login
  - Configurable via `WPT_LOGIN_ATTEMPT_LIMIT` and `WPT_LOGIN_LOCKOUT_DURATION` constants

### Site Hardening
- **Restrict Plugin/Theme Management**: Optional setting to limit plugin and theme management to 829 administrators only:
  - Only users with @829llc.com email addresses can install, update, or delete plugins/themes
  - Works on all hosts including WP Engine (uses WordPress capabilities, not constants)
  - Restricts: install, activate, delete, update, edit, and upload for both plugins and themes
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

### Optional
- [Two-Factor plugin](https://wordpress.org/plugins/two-factor/) - Required to enforce 2FA for non-SSO users

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
- **Restrict Plugin/Theme Management**: Limit plugin and theme management to 829 administrators only
- **REST API Availability**: Control access to WordPress REST API endpoints
- **Limit Login Attempts**: Enable/disable login attempt limiting (enabled by default)
- **Plugin Update Channel**: Which builds this site updates to — Stable, Beta, or a single branch (see [Releases](#releases))

## Constants

The following constants can be defined in `wp-config.php` to customize plugin behavior:

### Login Limiting
- `WPT_LOGIN_ATTEMPT_LIMIT` (default: `10`) - Maximum number of failed login attempts per IP address within the time window
- `WPT_LOGIN_LOCKOUT_DURATION` (default: `900`) - Lockout duration in seconds (default is 15 minutes)

### Settings Access
- `WPT_ALLOW_ADMIN_SETTINGS_ACCESS` (default: `false`) - If set to `true`, allows any administrator (or super admin on multisite) to access the 829 Settings page

### Updates
- `WPT_UPDATE_CHANNEL` (default: `stable`) - Which builds this site updates to. Overrides the settings page. See [Releases](#releases)

## WP-CLI Commands

This plugin provides WP-CLI commands for managing various features:

### Clear Login Attempts

Clear all login attempt transients, effectively unlocking any IP addresses that are currently locked out.

```bash
wp 829-tools clear-login-attempts
```

**Example output:**
```
Clearing login attempt transients...
Success: Cleared 5 login attempt transient(s).
```

## Releases

Sites update themselves from GitHub releases. Which release a site sees depends on its **update channel**.

### Update channels

Set per site, either in `wp-config.php` (wins, and survives staging refreshes) or under **829 Settings → Plugin Update Channel**:

| Channel | Site updates to |
| --- | --- |
| `stable` *(default)* | Normal releases only. **Use this on production.** |
| `beta` | Newest pre-release, whichever branch it came from |
| `branch:<slug>` | Pre-releases from one branch only |

```php
define( 'WPT_UPDATE_CHANNEL', 'beta' );
```

> Settings-page values live in the database, so refreshing a staging site from production wipes them. Use the constant for anything permanent.

### Testing a branch on a staging site

1. **Actions → Publish pre-release → Run workflow**, pick your branch.
2. Add the `define()` from the release notes to the staging site's `wp-config.php`.
3. **Dashboard → Updates → Check again**, then update as normal.

The pre-release is built from your branch, published as version `1.9.0-<branch-slug>.<date>.<run>`, and deleted automatically when the branch merges or is deleted.

> If the workflow fails with *"package.json is at X, which is not newer than the latest release"*, bump the version first — see below. A build at or below the current release sorts under stable and no site would be offered it.

### Shipping a stable release

```bash
npm run set-version 1.9.0    # updates the plugin header, WPT_VERSION and package.json
```

1. Commit the version bump and merge to `main`.
2. Create a GitHub release tagged `1.9.0` (`gh release create 1.9.0 --generate-notes`).
3. Actions builds the zip and attaches it. Sites on `stable` pick it up within a day.

Bump the version at the **start** of a release cycle — pre-release builds are numbered from it.

### When a branch merges

Move any site pinned to `branch:<slug>` back to `beta` or `stable`. Its pre-releases are deleted on merge, and a site left pinned silently stops seeing updates.

## License

MIT

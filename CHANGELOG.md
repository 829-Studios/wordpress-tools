# Changelog
All notable changes to 829 Studios WordPress Tools are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this
project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Add option to require login to view sites (staging/dev only). When on, logged-out visitors are sent to the login page and REST requires login. Application passwords still work, so MCP works without basic auth. Uploads stay public. `WPT_REQUIRE_LOGIN` overrides the setting on any environment.

## [1.14.0] - 2026-10-26

### Added

- `upload-media` MCP ability for adding files to the media library from a URL or base64 data; images require alt text.
- `list-terms`, `create-term`, and `assign-terms` MCP abilities for managing taxonomy terms on any post type.

### Changed

- The `terms` input on `create-post`, `update-post`, and `list-posts` now also accepts term names.

## [1.13.0] - 2026-10-22

### Changed

- **WPE Cache Clear Management** now always grants the **editor** role access to clear the WP Engine cache, and the setting is now a user picker instead of a role picker — add individual users to grant them the same access alongside editors. The editor role is automatically added to WP Engine's admin-bar visibility option, and configured users are also allowed to see the cache-clear toolbar link.

### Fixed

- **WPE Cache Clear Management** now works on multisite. WP Engine gates its cache-clear admin-bar link and network admin page on `manage_network` (not `manage_options`) on multisite, so qualifying users could see but not use the link; the scoped capability grant now covers whichever capability WP Engine checks.

## [1.12.0] - 2026-10-22

### Added

- **No-Index Banner**: a red warning banner on the frontend for logged-in 829 users when a page is set to no-index on what looks like a production domain. Known dev/staging domains (829dev, 829stage, WP Engine, `.local`) are skipped, and more can be excluded on the 829 Settings page.

## [1.11.2] - 2026-10-22

### Fixed

- Fixed the 2FA email confirmation form failing on WP Engine (Cloudflare 520). The form now posts to core's login form action, which WP Engine tags with `?wpe-login=true`.

## [1.11.1] - 2026-10-09

### Added

- **WPE Cache Clear Management** setting on the 829 Settings page (WP Engine hosts only): select existing user roles that can clear the WP Engine cache via the admin bar, without granting them `manage_options` or any other administrator-level access.

## [1.11.0] - 2026-09-21

Tooling and code style only. No functional changes.

### Added

- Added Husky and lint checks on pre-commit, pre-push, and a GitHub action to ensure code follows the PHP and JS linting rules.
- Added CLAUDE.md file for AI-assisted development guidelines.
- Added CHANGELOG.md file documenting current and all previous releases.

### Changed

- Bumped `@wordpress/scripts` `^31.4.0` → `^33.0.0`, the tooling behind `npm run zip`.

### Fixed
- Fixed all PHPCS errors including formatting and code style adjustments and a few minor adjustments.
- Fixed all JS linting errors - all just formatting inconsistencies.

## [1.10.0] - 2026-09-21

### Added

- 2FA activation now requires confirming the account's email address.
- Interstitial page blocking access until required 2FA setup is complete.
- **2FA Login Exceptions** setting, for integration accounts that authenticate with an
  application password and can't complete a 2FA challenge.

### Fixed

- Closed 2FA bypass routes: blocked `edit_users`/`edit_user`, restricted `admin-ajax`, and
  locked `user_email` changes until 2FA is enabled.

## [1.9.0] - 2026-09-15

### Added

- Opt-in release channels (`stable`, `beta`, `branch:<slug>`) via the **Plugin Update Channel**
  setting or the `WPT_UPDATE_CHANNEL` constant.

### Fixed

- Release channel edge cases: preserve the stored channel when the constant locks settings, tag
  the commit actually built, and lowercase channel values.

## [1.8.2] - 2026-08-13

### Added

- `Roles` class registering four dedicated MCP roles.

### Changed

- Only MCP Editor and MCP Site Manager can access every post; other MCP roles see only their own.

## [1.8.1] - 2026-07-27

### Added

- `get-block-info` MCP ability for core and custom React blocks.
- `allowed_blocks` attribute on MCP responses for ACF and `core/react` blocks.
- `wpt_mcp_block_list_entry` filter on `list-allowed-blocks`, plus `description`, `icon`, and
  `keywords` as default fields.
- Filter on `list-acf-blocks` so themes can attach extra data.

### Fixed

- PHPCS errors in the MCP class, including caching repeated queries on a property.

## [1.8.0] - 2026-06-22

### Added

- **Force No-Index on Staging and Dev Domains**.
- **Restrict 829 Credential Login**.
- Plugin version shown on the settings screen.

### Changed

- Renamed whitelist/blacklist to allow list / deny list.
- Sign-in redirects to wp-admin rather than the front-end homepage.
- `X-Robots-Tag` is sent on front-end requests only, skipping wp-admin, `admin-ajax`, and REST.

### Fixed

- No-index AJAX handlers return 403 with a translated message instead of 200 with a hardcoded
  string.

## [1.7.1] - 2026-06-08

### Added

- Smart Plugin Manager status in the Site Info API.
- Staging deploy workflow and `.wpe-push-ignore` rules.

## [1.7.0] - 2026-06-03

### Added

- `docs/WISHLIST.md`.

### Changed

- Split Plugin Management and Theme Management into separate settings.
- Dropped jQuery from the settings user-search assets.
- Cache-bust enqueued assets with `filemtime()` instead of the plugin version.

### Fixed

- Guard asset existence before calling `filemtime()`.

## [1.6.3] - 2026-05-13

### Added

- `RoleManagement` module extending Editor capabilities.

### Changed

- Anonymized user data in the activity log.

## [1.6.2] - 2026-04-17

### Changed

- **Restrict Plugin/Theme Management** enabled by default again.

### Fixed

- 2FA capability filter no longer assumes the capability argument is a string.

## [1.6.1] - 2026-04-09

### Added

- MCP support for posts.

## [1.6.0] - 2026-04-09

### Added

- MCP integration, exposing site content and abilities to MCP clients.

## [1.5.1] - 2026-03-18

### Fixed

- Multisite-aware API response shape.

## [1.5.0] - 2026-03-18

### Added

- Site Info API and Activity Log.
- Dashboard API key generation, storage, and read support.
- WP-CLI command for setting the API key.

## [1.4.6] - 2026-03-12

### Removed

- The `Headers` module and its settings.

## [1.4.5] - 2026-03-10

### Added

- Support for custom login URLs and post-login redirects in SSO.

## [1.4.4] - 2026-03-09

### Fixed

- PHP 7.4 compatibility.

## [1.4.3] - 2026-03-05

### Fixed

- On multisite, 829 users signing into a child site are added to it even when their account
  already exists network-wide.

## [1.4.2] - 2026-02-17

### Fixed

- Re-added `vendor/` so sites on older versions could still update.

## [1.4.1] - 2026-02-17

### Changed

- Update checker pulls the built release asset rather than repository source.

## [1.4.0] - 2026-02-11

### Added

- GitHub Actions workflow building the plugin zip, with `composer install` as part of the build.
- `package.json` with a `plugin-zip` script.

### Changed

- Renamed `plugin.php` to `wordpress-tools.php`.
- Version included in the zip filename.
- `vendor/` no longer committed; installed at build time.

### Fixed

- Linting: `wp_safe_redirect()` in place of `wp_redirect()`, and a suppressed
  `file_get_contents()` warning for a plugin-internal file.

## [1.3.2] - 2026-02-05

### Changed

- On multisite, users granted administrator are also granted super admin.

## [1.3.1] - 2025-12-23

### Changed

- Reverted 1.2.0's blanket block. Manually created @829llc.com accounts can use a password
  again; only SSO-created accounts are SSO-only.

## [1.3.0] - 2025-12-09

### Added

- 2FA required for non-829 users when the
  [Two-Factor plugin](https://wordpress.org/plugins/two-factor/) is active. Users without it are
  restricted to `read`.

## [1.2.0] - 2025-12-05

### Changed

- Password login blocked for all @829llc.com addresses. (Partially reverted in 1.3.1.)

## [1.1.0] - 2025-12-02

### Added

- Plugin Management module restricting plugin and theme management to 829 administrators, via
  capability filters so it works on WP Engine.

## [1.0.1] - 2025-11-24

### Fixed

- Settings checks and WP Engine environment detection.
- Update checker.

## [1.0.0] - 2025-11-17

Initial release.

### Added

- **SSO** with JIT user provisioning and dynamic role assignment. SSO-created accounts can't
  fall back to password login.
- **Strong password enforcement** via Zxcvbn and Have I Been Pwned, with a forced reset for weak
  passwords.
- **Reserved username protection** blocking generic usernames (`admin`, `root`, `test`).
- **Login attempt limiting** — per-IP cap and lockout window, configurable via
  `WPT_LOGIN_ATTEMPT_LIMIT` and `WPT_LOGIN_LOCKOUT_DURATION`, with a `clear-login-attempts`
  WP-CLI command.
- **REST API restriction**: authenticated-only, users-endpoint-only (default), or public.
- **Comment control**, **post password control**, and **author archive control**.
- **Security headers** module.
- **Admin customizations**: color-coded environment indicator and custom footer text.
- **829 Settings page**, restricted to @829llc.com in production, with multisite support.
- Self-updating from GitHub releases.

[1.11.1]: https://github.com/829-Studios/wordpress-tools/compare/1.11.0...HEAD
[1.11.0]: https://github.com/829-Studios/wordpress-tools/releases/tag/1.11.0
[1.10.0]: https://github.com/829-Studios/wordpress-tools/releases/tag/1.10.0
[1.9.0]: https://github.com/829-Studios/wordpress-tools/releases/tag/1.9.0
[1.8.2]: https://github.com/829-Studios/wordpress-tools/releases/tag/1.8.2
[1.8.1]: https://github.com/829-Studios/wordpress-tools/releases/tag/1.8.1
[1.8.0]: https://github.com/829-Studios/wordpress-tools/releases/tag/1.8.0
[1.7.1]: https://github.com/829-Studios/wordpress-tools/releases/tag/1.7.1
[1.7.0]: https://github.com/829-Studios/wordpress-tools/releases/tag/1.7.0
[1.6.3]: https://github.com/829-Studios/wordpress-tools/releases/tag/1.6.3
[1.6.2]: https://github.com/829-Studios/wordpress-tools/releases/tag/1.6.2
[1.6.1]: https://github.com/829-Studios/wordpress-tools/releases/tag/1.6.1
[1.6.0]: https://github.com/829-Studios/wordpress-tools/releases/tag/1.6.0
[1.5.1]: https://github.com/829-Studios/wordpress-tools/releases/tag/1.5.1
[1.5.0]: https://github.com/829-Studios/wordpress-tools/releases/tag/1.5.0
[1.4.6]: https://github.com/829-Studios/wordpress-tools/releases/tag/1.4.6
[1.4.5]: https://github.com/829-Studios/wordpress-tools/releases/tag/1.4.5
[1.4.4]: https://github.com/829-Studios/wordpress-tools/releases/tag/1.4.4
[1.4.3]: https://github.com/829-Studios/wordpress-tools/releases/tag/1.4.3
[1.4.2]: https://github.com/829-Studios/wordpress-tools/releases/tag/1.4.2
[1.4.1]: https://github.com/829-Studios/wordpress-tools/releases/tag/1.4.1
[1.4.0]: https://github.com/829-Studios/wordpress-tools/releases/tag/1.4.0
[1.3.2]: https://github.com/829-Studios/wordpress-tools/releases/tag/1.3.2
[1.3.1]: https://github.com/829-Studios/wordpress-tools/releases/tag/1.3.1
[1.3.0]: https://github.com/829-Studios/wordpress-tools/releases/tag/1.3.0
[1.2.0]: https://github.com/829-Studios/wordpress-tools/releases/tag/1.2.0
[1.1.0]: https://github.com/829-Studios/wordpress-tools/releases/tag/1.1.0
[1.0.1]: https://github.com/829-Studios/wordpress-tools/releases/tag/1.0.1
[1.0.0]: https://github.com/829-Studios/wordpress-tools/releases/tag/1.0.0

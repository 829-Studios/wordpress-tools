# Changelog

All notable changes to 829 Studios WordPress Tools are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this
project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

Pre-release builds (`1.10.0-<branch-slug>.<date>.<run>` and similar) are not listed here — see
[Releases](README.md#releases) for how those are published and consumed.

## [1.11.0] - 2026-09-21

Tooling and code-style release. No functional changes to the plugin: every change to shipped
PHP and JavaScript in this release is formatting, documentation, or a PHPCS annotation.

### Added

- ESLint configuration (`eslint.config.mjs`) built on `@wordpress/eslint-plugin`, with
  `npm run lint` and `npm run lint:fix` scripts covering `assets/js/`, `npm-scripts/`, and the
  config itself.
- Husky git hooks: `pre-commit` runs `lint-staged` over staged JS and PHP; `pre-push` runs the
  full `npm run lint` and `composer lint`, skipping automatically when a push contains no
  `.php`, `.js`, or `.mjs` changes.
- **Lint** GitHub Actions workflow running ESLint and PHPCS on pull requests, pushes to `main`,
  and on demand. It installs dev Composer dependencies, since PHPCS, WPCS, and the VIP sniffs
  live in `require-dev` and the release build installs with `--no-dev`.
- `CLAUDE.md` with repository conventions for AI coding assistants.

### Changed

- Bumped `@wordpress/scripts` from `^31.4.0` to `^33.0.0`. This is the tooling behind
  `npm run zip`, so the release build runs on the new major.
- Reformatted the JavaScript in `assets/js/` and `npm-scripts/` to the WordPress ESLint rules —
  `var` to `const`/`let`, object and method shorthand, and consistent wrapping. Behavior is
  unchanged.
- Reformatted PHP across `Settings`, `PluginManagement`, `SiteInfo`, `ActivityLog`, and
  `NoIndex` to satisfy PHPCS: array alignment, multi-line array arguments, and Yoda
  conditions.

### Fixed

- Documented the missing `$user` parameter on `ActivityLog::on_wp_login()`.
- Replaced blanket PHPCS ignores with scoped `disable`/`enable` pairs carrying justifications,
  covering the nonce checks on the emailed 2FA confirmation link, the `admin-ajax` action read
  in the 2FA redirect guard, the pre-escaped confirmation form passed to `wp_die()`, and the
  prepared activity-log query.

### Removed

- The `propel_array_to_link` auto-escape exemption from `phpcs.xml`, which belonged to the
  Propel theme rather than to this plugin.

## [1.10.0] - 2026-09-21

### Added

- 2FA email confirmation: activating two-factor authentication now requires confirming the
  account's email address first.
- Interstitial page that blocks access until a required 2FA setup is completed, replacing the
  silent capability downgrade as the primary enforcement path.
- **2FA Login Exceptions** setting (under **External Email Users**) to exempt individual
  accounts from the 2FA requirement — intended for third-party integration accounts, such as
  Tourcube, that authenticate with an application password over the REST API and can't complete
  a 2FA challenge.

### Fixed

- Closed several 2FA bypass routes: the `edit_users` / `edit_user` capabilities are now blocked
  for users who haven't completed 2FA, `admin-ajax` access is restricted, and `user_email`
  changes are locked until 2FA is enabled.

## [1.9.0] - 2026-09-15

### Added

- Opt-in release channels for pre-release testing. Sites choose `stable`, `beta`, or
  `branch:<slug>` via the **Plugin Update Channel** setting or the `WPT_UPDATE_CHANNEL`
  constant in `wp-config.php`.

### Fixed

- Release channel edge cases: the stored channel is preserved when the constant locks the
  settings, pre-releases tag the commit that was actually built, and channel values are
  lowercased before comparison.

## [1.8.2] - 2026-08-13

### Added

- New `Roles` class registering four dedicated roles for MCP access.

### Changed

- Only the MCP Editor and MCP Site Manager roles can access every post; the remaining MCP roles
  are limited to posts they own.

## [1.8.1] - 2026-07-27

### Added

- `get-block-info` MCP ability returning full block information for both core and custom React
  blocks.
- `allowed_blocks` attribute on MCP responses for ACF and `core/react` blocks.
- `wpt_mcp_block_list_entry` filter on the `list-allowed-blocks` ability, with `description`,
  `icon`, and `keywords` added as default fields.
- Filter on the `list-acf-blocks` ability so themes (Propel) can attach additional data.

### Fixed

- PHPCS linting errors in the MCP class, including caching repeated database queries on a
  property instead of re-querying.

## [1.8.0] - 2026-06-22

### Added

- **Force No-Index on Staging and Dev Domains** — sends `X-Robots-Tag` on non-production
  environments to keep them out of search indexes.
- **Restrict 829 Credential Login** feature.
- Plugin version number displayed on the plugin settings screen.

### Changed

- Renamed whitelist/blacklist terminology to allow list / deny list throughout.
- After sign-in, users are redirected to the wp-admin dashboard rather than the front-end
  homepage.
- The no-index `X-Robots-Tag` header is now sent on front-end requests only, skipping wp-admin,
  `admin-ajax`, and REST API endpoints, which crawlers don't index anyway.

### Fixed

- No-index AJAX handlers return HTTP 403 with a translated error message when rejecting an
  unauthorized request, instead of HTTP 200 with a hardcoded string.

## [1.7.1] - 2026-06-08

### Added

- Smart Plugin Manager status reported through the Site Info API.
- Staging deploy workflow and `.wpe-push-ignore` rules for WP Engine pushes.

## [1.7.0] - 2026-06-03

### Added

- Feature wishlist document (`docs/WISHLIST.md`).

### Changed

- Plugin Management and Theme Management are now two separate settings, so plugin management can
  be enabled without also enabling theme management.
- Settings user-search assets no longer depend on jQuery.
- Enqueued CSS/JS are cache-busted with `filemtime()` rather than the plugin version.

### Fixed

- Guard CSS/JS existence before calling `filemtime()` to avoid PHP errors when an asset is
  missing.

## [1.6.3] - 2026-05-13

### Added

- `RoleManagement` module that extends Editor capabilities.

### Changed

- User data in the activity log is anonymized.

## [1.6.2] - 2026-04-17

### Changed

- **Restrict Plugin/Theme Management** is enabled by default again.

### Fixed

- Capability check in the 2FA restriction filter: it no longer assumes the capability argument
  is a string, which could throw on some capability checks.

## [1.6.1] - 2026-04-09

### Added

- MCP support for posts.

## [1.6.0] - 2026-04-09

### Added

- MCP (Model Context Protocol) integration, exposing site content and abilities to MCP clients.

## [1.5.1] - 2026-03-18

### Fixed

- Multisite-aware API response shape.

## [1.5.0] - 2026-03-18

### Added

- Site Info API reporting site details back to 829 Studios.
- Activity Log recording site and user activity.
- Dashboard API key generation, storage, and read support.
- `wp 829-tools` WP-CLI command for setting the API key.

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

- On multisite, 829 users signing into a child site are added to that site even when their
  account already exists at the network level.

## [1.4.2] - 2026-02-17

### Fixed

- Re-added the `vendor` directory so sites still on older versions could update.

## [1.4.1] - 2026-02-17

### Changed

- The update checker now pulls the built release asset rather than the repository source.

## [1.4.0] - 2026-02-11

### Added

- GitHub Actions workflow that builds the plugin zip, both manually and on release, running
  `composer install` as part of the build.
- `package.json` with a `plugin-zip` script.

### Changed

- Renamed the main plugin file from `plugin.php` to `wordpress-tools.php`, matching WordPress
  plugin conventions.
- The plugin version is included in the zip filename.
- `vendor/` is no longer committed; dependencies are installed during the build.

### Fixed

- Linting: use `wp_safe_redirect()` in place of `wp_redirect()` where appropriate, and suppress
  the `file_get_contents()` warning for a plugin-internal file.

## [1.3.2] - 2026-02-05

### Changed

- On multisite, users granted administrator are also granted super admin.

## [1.3.1] - 2025-12-23

### Changed

- Reverted the blanket password-login block from 1.2.0. Manually created @829llc.com accounts
  can log in with a password again; only accounts created through SSO are restricted to SSO.

## [1.3.0] - 2025-12-09

### Added

- Two-factor authentication is required for non-829 users when the
  [Two-Factor plugin](https://wordpress.org/plugins/two-factor/) is active. Users without 2FA
  are restricted to `read` until they configure it.

## [1.2.0] - 2025-12-05

### Changed

- Password login is blocked for all @829llc.com email addresses; they must authenticate through
  SSO. (Partially reverted in 1.3.1.)

## [1.1.0] - 2025-12-02

### Added

- Plugin Management module restricting plugin and theme management to 829 administrators. Works
  on all hosts, including WP Engine, because it filters capabilities rather than relying on
  constants.

## [1.0.1] - 2025-11-24

### Fixed

- Settings checks and WP Engine environment detection.
- Update checker.

## [1.0.0] - 2025-11-17

Initial release.

### Added

- **SSO** with 829 Studios authentication, including Just-in-Time user provisioning and dynamic
  role assignment. Accounts created via SSO can't fall back to password login.
- **Strong password enforcement** using the Zxcvbn library, with Have I Been Pwned lookups and a
  forced reset for users holding weak passwords.
- **Reserved username protection** blocking authentication as common generic usernames
  (`admin`, `root`, `test`, and similar).
- **Login attempt limiting** — a configurable per-IP attempt cap and lockout window backed by
  transients, with `WPT_LOGIN_ATTEMPT_LIMIT` and `WPT_LOGIN_LOCKOUT_DURATION` constants and a
  `wp 829-tools clear-login-attempts` WP-CLI command.
- **REST API restriction** with three levels: authenticated-only, users-endpoint-only (default),
  or fully public.
- **Comment control** to disable comments site-wide.
- **Post password control**, since password-protected posts don't work with page caching.
- **Author archive control**, with the option to disable author archives.
- **Security headers** module.
- **Admin customizations**: a color-coded environment indicator in the admin toolbar
  (production / staging / development, auto-detected by domain or set via
  `WP_ENVIRONMENT_TYPE`) and custom admin footer text.
- **Centralized 829 Settings page**, restricted to @829llc.com accounts in production and to any
  administrator in local development, with full network/multisite support.
- Self-updating from GitHub releases via Plugin Update Checker.

[1.11.0]: https://github.com/829-Studios/wordpress-tools/compare/1.10.0...HEAD
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

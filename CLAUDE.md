# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

829 Studios WordPress Tools — a WordPress plugin providing SSO, security hardening, and admin customizations. Distributed as a plugin zip; updates are delivered from GitHub releases via `yahnis-elsts/plugin-update-checker`. There is no separate build step for PHP — the plugin runs from source once dependencies are installed.

## Commands

```bash
composer install          # PHP deps (required for the plugin to load)
npm install               # Dev deps (only needed to build the release zip)

composer run lint         # phpcs against phpcs.xml (WordPress + VIP standards, PHP 7.4 target)
composer run lint-fix     # phpcbf autofix

npm run zip               # Build distributable zip (used by release workflow)
```

There is no test suite. Do not invent one — verify changes by running lint and by exercising the plugin in a WordPress install.

### WP-CLI

The plugin registers commands under `wp 829-tools` (see `includes/classes/Commands.php`). Example:

```bash
wp 829-tools clear-login-attempts
```

## Architecture

### Bootstrap (`wordpress-tools.php`)

Single entry file. Responsibilities, in order:

1. Defines `WPT_*` constants — most are `if ( ! defined(...) )` guarded so `wp-config.php` can override before the plugin loads.
2. Auto-detects `WP_ENVIRONMENT_TYPE` (`staging` for `*.829dev.com` / `*.wpenginepowered.com`, `development` for local TLDs via `Utils\is_local_environment()`).
3. Detects WP Engine (`WPT_IS_WPE`) and network activation (`WPT_IS_NETWORK`).
4. Registers a custom PSR-4-ish autoloader mapping `WordPressTools\Foo\Bar` → `includes/classes/Foo/Bar.php`. The `composer.json` `autoload` block declares the same mapping but the runtime loader in `wordpress-tools.php` is what's actually used. Also hardcodes a loader for `ZxcvbnPhp`.
5. `PluginManagement::instance()` runs immediately (before `plugins_loaded`) because it needs to gate plugin/theme capabilities early. All other modules boot on the `plugins_loaded` action via `Module::instance()`.

### Module pattern

Every feature lives in `includes/classes/<Module>/<Module>.php` as a class using the `WordPressTools\Singleton` trait. `instance()` lazily constructs and calls `setup()`, which is where all `add_action`/`add_filter` hooks are registered. To add a new module: create the class, add its `use ...;` and `Module::instance();` line in `wordpress-tools.php`.

Current modules (all in `includes/classes/`):

- `SSO/` — 829 Studios SSO via proxy at `WPT_SSO_PROXY_URL`
- `Authentication/` — `Passwords` (zxcvbn + HIBP), `Usernames` (reserved-name block), `LimitLogin` (transient-based IP throttling), `TwoFactor` (integrates with the Two-Factor plugin)
- `Settings/` — centralized settings page; **the source of truth for feature toggles**. `Settings::get_settings()` returns all settings merged with defaults. Also owns the plugin's API key (auto-generated on activation and `admin_init`).
- `PluginManagement/`, `Comments/`, `PostPasswords/`, `AdminCustomizations/`, `RoleManagement/`, `NoIndex/`, `API/` (REST API gating), `MCP/`, `SiteInfo/` (+ `ActivityLog`)
- `Commands.php` — WP-CLI commands, registered under `829-tools`

### Multisite / network awareness

`WPT_IS_NETWORK` is set at bootstrap based on whether the plugin is network-activated. Two rules follow from this:

- Settings pages: register under `network_admin_menu` when networked, `admin_menu` otherwise (see `Settings::setup()`).
- Options: read/write via `WordPressTools\Utils\get_maybe_site_option()` which picks `get_site_option` vs `get_option` based on `WPT_IS_NETWORK`. Use this helper — don't call `get_option` directly for plugin settings.

### 829 identity helpers

`Utils\is_829_user()` and `Utils\is_829_admin()` (`includes/utils.php`) gate access to sensitive features by checking for an `@829llc.com` email. `is_829_admin` deliberately inspects `$user->roles` and `$user->allcaps` directly rather than calling `user_can()` to avoid infinite recursion inside `user_has_cap` filters — preserve that pattern in similar checks.

### MCP module

`MCP/MCP.php` no-ops unless both `WP\MCP\Core\McpAdapter` and `wp_register_ability()` (WordPress 6.9+) are available. Keep new abilities behind the same guards.

## Conventions

- **PHP 7.4 target.** `phpcs.xml` enforces this via `PHPCompatibilityWP`. Don't use 8.0+ syntax.
- **WordPress coding standards** (WPCS + VIP) enforced by phpcs. Tabs for indentation; short array syntax is allowed (the `DisallowShortArraySyntax` rule is excluded).
- **Namespaces:** all classes under `WordPressTools\`. Utility functions live in the `WordPressTools\Utils` namespace in `includes/utils.php` (not autoloaded — required directly at bootstrap).
- **Settings additions:** add the default in `Settings::get_settings()` and register/sanitize it inside `Settings` — that array is the contract every module reads from.
- **Version bumps:** the version appears in three places that must stay in sync: the plugin header in `wordpress-tools.php`, the `WPT_VERSION` constant, and `package.json`. The release zip filename is derived from `package.json` version (see `npm-scripts/add-zip-version.js` and `.github/workflows/plugin-zip.yml`).

## Release flow

Publishing a GitHub release triggers `.github/workflows/plugin-zip.yml`, which runs `composer install --no-dev`, `npm install`, `npm run zip`, and attaches `wordpress-tools-<version>.zip` to the release. Installed sites pick it up via the plugin-update-checker pointed at `https://github.com/829-studios/wordpress-tools/`.

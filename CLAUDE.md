# 829 Studios WordPress Tools — AI Coding Instructions

A WordPress security and site-management plugin. Plain PHP (no build step for PHP), vanilla JS/CSS assets, PSR-4-ish autoloading, WPCS linting. Self-updates from GitHub releases.

## Project Structure

```
wordpress-tools.php    # Bootstrap: constants, autoloader, module instantiation, CLI registration
includes/
	utils.php          # WordPressTools\Utils namespaced helper functions
	classes/           # One directory per module (SSO/, Settings/, MCP/, Authentication/, …)
		Singleton.php  # Singleton trait — instance() calls setup()
		Roles.php      # Custom 829_mcp_* roles/capabilities
		Updates.php    # Update-channel logic (stable / beta / branch:<slug>)
		Commands.php   # WP-CLI `wp 829-tools <command>`
assets/css|js|svg      # Admin assets — plain CSS and IIFE-style JS, no bundler
npm-scripts/           # CommonJS release tooling (version bumps, zip, branch slugs)
.github/workflows/     # Lint, plugin-zip, prerelease, staging deploy, prerelease cleanup
docs/ · plans/         # Wishlist and planning notes — not shipped
```

README.md covers features, constants, settings, and the release process. CHANGELOG.md follows Keep a Changelog.

## Comments

- Keep comments short — a line or two, not paragraphs.
- Only comment the non-obvious "why" (a constraint, a workaround, an invariant) — never restate the code.
- Same for PHPDoc/JSDoc bodies: a one-line summary is enough.

## Linters

Always run the relevant linter after changes and fix all errors before finishing.

```bash
composer lint        # PHPCS
composer lint-fix    # PHPCBF auto-fix
npm run lint         # ESLint (assets/js, npm-scripts, eslint.config.mjs)
npm run lint:fix     # ESLint auto-fix
```

Husky runs `lint-staged` pre-commit and the full `npm run lint` + `composer lint` pre-push. CI runs both on every PR.

---

## PHP Rules

### Standards
- WordPress Coding Standards + PHPCompatibilityWP + VIP security sniffs (see `phpcs.xml`), **PHP 7.4 compatible** — no typed properties, enums, constructor promotion, or `match`.
- Tabs for indentation (`.editorconfig`).
- Text domain: `wordpress-tools` (what all existing code uses).
- Both `array()` and `[]` are allowed — match the surrounding file.

### Naming & Namespaces
- Namespace `WordPressTools\{Module}`; class file at `includes/classes/{Module}/{Class}.php` (the custom autoloader maps namespace → path 1:1, so the file name must match the class name).
- Constants: `WPT_` prefix. Options, transients, filters, AJAX actions: `wpt_` prefix. Roles/capabilities and CLI/MCP names: `829_mcp_*` / `829-tools`.
- Every PHP file starts with a docblock and an `if ( ! defined( 'ABSPATH' ) ) { exit; }` guard.

### Module Pattern
Modules are singletons with a `setup()` method that registers hooks, instantiated from `plugins_loaded` in `wordpress-tools.php`:

```php
class MyModule {

	use Singleton;

	/**
	 * Setup module
	 */
	public function setup() {
		add_action( 'init', [ $this, 'do_thing' ] );
	}
}
```

Adding a module: create the class, `use WordPressTools\Singleton`, add the `use` statement and `MyModule::instance();` to the `plugins_loaded` callback.

### Settings
All settings live in a single `wpt_settings` option, read via `Settings::get_settings()` (defaults merged in, never read the option directly). Network-activated installs use `get_site_option`; use `Utils\get_maybe_site_option()` when writing new option reads.

Adding a setting means touching **all** of these in `Settings/Settings.php`:
1. A default in `get_settings()`
2. `add_settings_field()` + a `*_setting_callback()` render method in `register_settings()`
3. A branch in `sanitize_settings()` (the network save path reuses it)
4. The field markup in `render_network_settings_page()` — the network page is rendered by hand, not by `do_settings_sections()`

### MCP Abilities
`MCP/MCP.php` registers abilities on `wp_abilities_api_init` (gated on the MCP Adapter and WP 6.9's Abilities API being present, plus the `enable_mcp` setting). A new ability needs a `wp_register_ability()` block with `input_schema`, `output_schema`, a `check_*_permission` callback, an `execute_callback`, and `meta.annotations`. New capabilities must also be added to `Roles::get_role_definitions()` and to the `custom_capabilities` list in `phpcs.xml`.

### Security
- Escape all output: `esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses_post()`.
- Capability check on every admin/AJAX handler; nonce check (`check_admin_referer()` / `check_ajax_referer()`) on every write.
- `exit` after every redirect (enforced by `WordPressVIPMinimum.Security.ExitAfterRedirect`).
- Prepare every `$wpdb` query; `// phpcs:ignore` only for direct queries that genuinely have no API alternative.

---

## JavaScript Rules

- `assets/js/` — browser scripts, `sourceType: script`, wrapped in an IIFE with `'use strict'`. No modules, no bundler; declare globals with `/* globals wptThing */`.
- `npm-scripts/` — CommonJS Node scripts; `console` allowed.
- **Always use curly braces** for `if`, `for`, `while`, etc.
- Blank-line padding (`padding-line-between-statements`): blank line **before** `if`/`for`/`while`/`do`/`switch`/`try`/`return`/`function`/multiline expressions and **after** them; **no** blank line between consecutive single-line `const`/`let`/`var` or between single-line expressions.
- Data is passed from PHP with `wp_localize_script()` (`wptUserSearch`, `wptNoIndex`).

## CSS Rules

Plain CSS in `assets/css/`, admin-only, no preprocessor or linter. BEM-ish `wpt-` prefixed class names; match the WordPress admin palette already in use.

---

## Releases

- Never hand-edit a version. `npm run set-version 1.11.0` updates the plugin header, `WPT_VERSION`, and `package.json` together — a mismatch makes sites see a permanent pending update.
- Bump at the **start** of a release cycle; pre-release builds are numbered from it.
- Add a CHANGELOG entry for anything user-facing.
- Release mechanics (channels, pre-releases, staging tests) are in README.md § Releases.

## Key Conventions

| Concern | Rule |
|---|---|
| PHP version | 7.4-compatible syntax only |
| Module setup | `use Singleton` + `setup()`, instantiated in `wordpress-tools.php` |
| File/class naming | Path must mirror the namespace exactly |
| Prefixes | `WPT_` constants, `wpt_` options/filters/actions, `829-tools` CLI/MCP |
| Settings reads | `Settings::get_settings()`, never `get_option( 'wpt_settings' )` |
| New setting | Default + field + sanitize + network render — all four |
| Output | Always escaped; nonce + capability check on writes |
| Redirects | Always `exit` after |
| JS | IIFE scripts in `assets/js/`, CommonJS in `npm-scripts/`, braces always |
| Text domain | `wordpress-tools` |
| Versions | `npm run set-version`, never by hand |
| Comments | Short, non-obvious "why" only |

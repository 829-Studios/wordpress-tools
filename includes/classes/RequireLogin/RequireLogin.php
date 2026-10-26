<?php
/**
 * Require login to view the site.
 *
 * @package  WordPressTools
 */

namespace WordPressTools\RequireLogin;

use WordPressTools\Singleton;
use WordPressTools\Settings\Settings;
use function WordPressTools\Utils\is_staging_or_dev;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * RequireLogin class
 *
 * Sends logged-out visitors to the login page for everything WordPress serves:
 * pages, feeds, sitemaps, REST, XML-RPC, admin-ajax and admin-post. Application
 * passwords still authenticate REST requests, so MCP keeps working. Static files
 * (uploads, theme assets) are served by the web server and stay public.
 */
class RequireLogin {

	use Singleton;

	/**
	 * Setup hooks.
	 */
	public function setup() {
		add_action( 'update_option_wpt_settings', [ $this, 'on_settings_update' ], 10, 2 );
		add_action( 'update_site_option_wpt_settings', [ $this, 'on_network_settings_update' ], 10, 3 );

		if ( ! self::is_active() ) {
			return;
		}

		add_action( 'parse_request', [ $this, 'gate_frontend' ], 1 );
		add_action( 'admin_init', [ $this, 'gate_admin_requests' ], 1 );
		add_filter( 'robots_txt', [ $this, 'disallow_all_robots' ], PHP_INT_MAX );
		add_filter( 'xmlrpc_methods', '__return_empty_array', PHP_INT_MAX );
		add_filter( 'pre_option_users_can_register', '__return_zero' );
		add_filter( 'pre_site_option_registration', [ $this, 'disable_network_registration' ] );
	}

	/**
	 * Whether the WPT_REQUIRE_LOGIN constant overrides the setting.
	 *
	 * @return bool
	 */
	public static function is_locked(): bool {
		return defined( 'WPT_REQUIRE_LOGIN' );
	}

	/**
	 * Whether login is required on this site.
	 *
	 * @return bool
	 */
	public static function is_active(): bool {
		if ( self::is_locked() ) {
			return (bool) WPT_REQUIRE_LOGIN;
		}

		// The setting travels with the database, so a staging copy pushed live must not lock production.
		if ( ! is_staging_or_dev() ) {
			return false;
		}

		$settings = Settings::get_settings();

		return ! empty( $settings['require_login'] );
	}

	/**
	 * Whether the current user may view the site.
	 *
	 * Uses `read` rather than is_user_logged_in() so multisite users must belong to this site.
	 *
	 * @return bool
	 */
	private static function can_view(): bool {
		return current_user_can( 'read' );
	}

	/**
	 * Redirect logged-out visitors to the login page.
	 *
	 * @param \WP $wp Current WordPress environment.
	 */
	public function gate_frontend( $wp ) {
		// REST is gated by API::restrict_rest_api(). Checking the user here would cache a logged-out
		// user before REST_REQUEST is defined, which is what lets application passwords authenticate.
		if ( ! empty( $wp->query_vars['rest_route'] ) || ! empty( $wp->query_vars['robots'] ) ) {
			return;
		}

		if ( self::can_view() ) {
			return;
		}

		$redirect_to = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';

		nocache_headers();
		wp_safe_redirect( wp_login_url( $redirect_to ) );
		exit;
	}

	/**
	 * Block logged-out admin-ajax and admin-post requests.
	 *
	 * Core already sends logged-out wp-admin screens to the login page before admin_init.
	 */
	public function gate_admin_requests() {
		if ( self::can_view() ) {
			return;
		}

		// Heartbeat is how an expired admin session learns to show the re-login modal.
		if ( wp_doing_ajax() && 'heartbeat' === filter_input( INPUT_POST, 'action' ) ) {
			return;
		}

		wp_die( esc_html__( 'You must be logged in to view this site.', 'wordpress-tools' ), '', [ 'response' => 403 ] );
	}

	/**
	 * Tell crawlers to stay out, rather than redirecting robots.txt to the login page.
	 *
	 * @return string
	 */
	public function disallow_all_robots() {
		return "User-agent: *\nDisallow: /\n";
	}

	/**
	 * Turn off multisite signups.
	 *
	 * @return string
	 */
	public function disable_network_registration() {
		return 'none';
	}

	/**
	 * Purge the page cache when the setting is turned on.
	 *
	 * @param mixed $old_value Previous settings.
	 * @param mixed $value     New settings.
	 */
	public function on_settings_update( $old_value, $value ) {
		$this->maybe_purge_page_cache( $old_value, $value );
	}

	/**
	 * Purge the page cache when the network setting is turned on.
	 *
	 * @param string $option    Option name.
	 * @param mixed  $value     New settings.
	 * @param mixed  $old_value Previous settings.
	 */
	public function on_network_settings_update( $option, $value, $old_value ) {
		$this->maybe_purge_page_cache( $old_value, $value );
	}

	/**
	 * Purge WP Engine's page cache so pages cached before the lock aren't served to logged-out visitors.
	 *
	 * @param mixed $old_value Previous settings.
	 * @param mixed $value     New settings.
	 */
	private function maybe_purge_page_cache( $old_value, $value ) {
		$was_on = is_array( $old_value ) && ! empty( $old_value['require_login'] );
		$is_on  = is_array( $value ) && ! empty( $value['require_login'] );

		if ( $was_on || ! $is_on || ! self::is_active() ) {
			return;
		}

		if ( class_exists( 'WpeCommon' ) && method_exists( 'WpeCommon', 'purge_varnish_cache' ) ) {
			\WpeCommon::purge_varnish_cache();
		}
	}
}

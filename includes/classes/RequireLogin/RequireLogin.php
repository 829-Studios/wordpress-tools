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
 * Locks the site to logged-in users. Static files like uploads stay public.
 */
class RequireLogin {

	use Singleton;

	/**
	 * Setup hooks.
	 */
	public function setup() {
		add_action( 'add_option_wpt_settings', [ $this, 'on_settings_added' ], 10, 2 );
		add_action( 'add_site_option_wpt_settings', [ $this, 'on_settings_added' ], 10, 2 );
		add_action( 'update_option_wpt_settings', [ $this, 'on_settings_update' ], 10, 2 );
		add_action( 'update_site_option_wpt_settings', [ $this, 'on_network_settings_update' ], 10, 3 );

		if ( ! self::is_active() ) {
			return;
		}

		add_action( 'parse_request', [ $this, 'gate_frontend' ], 1 );
		add_action( 'pre_comment_on_post', [ $this, 'gate_comments' ] );
		add_filter( 'xmlrpc_methods', '__return_empty_array', PHP_INT_MAX );

		// Front end only, so saving Settings > General doesn't persist it.
		if ( ! is_admin() ) {
			add_filter( 'pre_option_users_can_register', '__return_zero' );
			add_filter( 'pre_site_option_registration', [ $this, 'disable_network_registration' ] );
		}
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
			// Parsed, not cast, so 'false' is false.
			return filter_var( WPT_REQUIRE_LOGIN, FILTER_VALIDATE_BOOLEAN );
		}

		// Ignored in production, where a staging database may have been pushed.
		if ( ! is_staging_or_dev() ) {
			return false;
		}

		$settings = Settings::get_settings();

		return ! empty( $settings['require_login'] );
	}

	/**
	 * Whether the current user may view the site; `read` requires multisite membership.
	 *
	 * @return bool
	 */
	public static function can_view(): bool {
		return current_user_can( 'read' );
	}

	/**
	 * Redirect logged-out visitors to the login page.
	 *
	 * @param \WP $wp Current WordPress environment.
	 */
	public function gate_frontend( $wp ) {
		// REST is gated in API; checking the user this early would break application passwords.
		if ( ! empty( $wp->query_vars['rest_route'] ) ) {
			return;
		}

		// Answered here so extra query vars (?robots=1&sitemap=1) can't skip the gate.
		if ( ! empty( $wp->query_vars['robots'] ) ) {
			header( 'Content-Type: text/plain; charset=utf-8' );
			echo "User-agent: *\nDisallow: /\n";
			exit;
		}

		if ( self::can_view() ) {
			return;
		}

		nocache_headers();

		// Already logged in, so redirecting would loop.
		if ( is_user_logged_in() ) {
			wp_die(
				esc_html__( 'Your account doesn\'t have access to this site.', 'wordpress-tools' ),
				'',
				[
					'response'  => 403,
					'link_url'  => esc_url( wp_logout_url() ),
					'link_text' => esc_html__( 'Log out', 'wordpress-tools' ),
				]
			);
		}

		$redirect_to = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';

		wp_safe_redirect( wp_login_url( $redirect_to ) );
		exit;
	}

	/**
	 * Block logged-out comments, which skip parse_request.
	 */
	public function gate_comments() {
		if ( self::can_view() ) {
			return;
		}

		wp_die( esc_html__( 'You must be logged in to comment.', 'wordpress-tools' ), '', [ 'response' => 403 ] );
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
	 * Purge the page cache on first save.
	 *
	 * @param string $option Option name.
	 * @param mixed  $value  New settings.
	 */
	public function on_settings_added( $option, $value ) {
		$this->maybe_purge_page_cache( [], $value );
	}

	/**
	 * Purge the page cache on update.
	 *
	 * @param mixed $old_value Previous settings.
	 * @param mixed $value     New settings.
	 */
	public function on_settings_update( $old_value, $value ) {
		$this->maybe_purge_page_cache( $old_value, $value );
	}

	/**
	 * Purge the page cache on network update.
	 *
	 * @param string $option    Option name.
	 * @param mixed  $value     New settings.
	 * @param mixed  $old_value Previous settings.
	 */
	public function on_network_settings_update( $option, $value, $old_value ) {
		$this->maybe_purge_page_cache( $old_value, $value );
	}

	/**
	 * Purge WP Engine's page cache when the setting is turned on.
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

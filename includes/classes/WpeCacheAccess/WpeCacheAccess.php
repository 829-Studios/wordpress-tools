<?php
/**
 * Let configured roles clear the WP Engine cache without granting manage_options.
 *
 * @package  WordPressTools
 */

namespace WordPressTools\WpeCacheAccess;

use WordPressTools\Singleton;
use WordPressTools\Settings\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * WPE Cache Access class
 *
 * The wpe-cache-plugin and wpengine-common mu-plugins gate their admin-bar link,
 * admin page, and REST endpoints behind hardcoded manage_options/has_cap() checks,
 * and their own Display tab only controls who can see the admin-bar menu, not who
 * can actually clear the cache. This class answers "yes" to those specific checks
 * for the roles configured in 829 Settings, scoped to only the three contexts that
 * matter — never anywhere else in wp-admin.
 */
class WpeCacheAccess {

	use Singleton;

	/**
	 * The two wpe-cache-plugin REST routes this feature is allowed to call.
	 *
	 * @var array
	 */
	protected $cache_rest_routes = [
		'/wpe/cache-plugin/v1/clear_all_caches',
		'/wpe/cache-plugin/v1/rate_limit_status',
	];

	/**
	 * Setup module
	 */
	public function setup() {
		if ( ! WPT_IS_WPE ) {
			return;
		}

		// Layer the configured roles onto WPE's own admin-bar-visibility option at
		// read time, rather than overwriting it — so manual edits on the WP Engine
		// Display tab and this setting never clobber each other.
		add_filter( 'option_wpe-adminbar-roles', [ $this, 'merge_admin_bar_roles' ] );

		add_filter( 'user_has_cap', [ $this, 'scope_manage_options_to_cache_clearing' ], 10, 4 );
		add_filter( 'rest_request_before_callbacks', [ $this, 'flag_cache_rest_request' ], 5, 3 );
		add_filter( 'rest_request_after_callbacks', [ $this, 'unflag_cache_rest_request' ], 20 );
	}

	/**
	 * The role slugs configured in 829 Settings as allowed to clear the WPE cache.
	 *
	 * @return array
	 */
	protected function get_allowed_roles() {
		$settings = Settings::get_settings();

		return ! empty( $settings['wpe_cache_clear_roles'] ) ? (array) $settings['wpe_cache_clear_roles'] : [];
	}

	/**
	 * Merges the configured roles into the WPE admin-bar-roles option at read time.
	 *
	 * @param mixed $value The stored option value.
	 * @return mixed
	 */
	public function merge_admin_bar_roles( $value ) {
		$allowed_roles = $this->get_allowed_roles();

		if ( empty( $allowed_roles ) ) {
			return $value;
		}

		return array_values( array_unique( array_merge( (array) $value, $allowed_roles ) ) );
	}

	/**
	 * Treat manage_options as granted for configured roles, scoped to the
	 * WP Engine cache-clearing admin-bar link, admin page tab, and REST routes.
	 *
	 * @param array    $allcaps All the capabilities of the user.
	 * @param array    $caps    Required primitive capabilities for the requested capability.
	 * @param array    $args    Arguments that accompany the requested capability check.
	 * @param \WP_User $user    The user object.
	 * @return array
	 */
	public function scope_manage_options_to_cache_clearing( $allcaps, $caps, $args, $user ) {
		if ( ! in_array( 'manage_options', $caps, true ) || empty( $user->roles ) ) {
			return $allcaps;
		}

		$allowed_roles = $this->get_allowed_roles();

		if ( empty( $allowed_roles ) || empty( array_intersect( (array) $user->roles, $allowed_roles ) ) ) {
			return $allcaps;
		}

		if ( $this->is_admin_bar_cache_link_context() || $this->is_cache_admin_page_context() || $this->is_cache_rest_context() ) {
			$allcaps['manage_options'] = true;
		}

		return $allcaps;
	}

	/**
	 * True only while WpeCommon::wpe_adminbar() is building the admin-bar menu,
	 * which is where the "Quick clear all cache" link's visibility is decided.
	 *
	 * @return bool
	 */
	protected function is_admin_bar_cache_link_context() {
		foreach ( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 8 ) as $frame ) { // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace
			if ( isset( $frame['class'], $frame['function'] ) && 'WpeCommon' === $frame['class'] && 'wpe_adminbar' === $frame['function'] ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * True only on the WP Engine admin page's "caching" tab — not its other tabs.
	 *
	 * @return bool
	 */
	protected function is_cache_admin_page_context() {
		return is_admin()
			&& isset( $_GET['page'], $_GET['tab'] ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			&& 'wpengine-common' === $_GET['page'] // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			&& 'caching' === $_GET['tab']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * True only while the two cache-plugin REST routes are handling the current request.
	 *
	 * @param bool|null $set When provided, updates whether the REST context is active.
	 * @return bool
	 */
	protected function is_cache_rest_context( $set = null ) {
		static $active = false;

		if ( null !== $set ) {
			$active = $set;
		}

		return $active;
	}

	/**
	 * Flags the cache REST routes as active before their permission_callback runs.
	 *
	 * @param mixed            $response Current response; null unless short-circuited.
	 * @param array            $handler  Route handler data, including callbacks.
	 * @param \WP_REST_Request $request  The request object.
	 * @return mixed
	 */
	public function flag_cache_rest_request( $response, $handler, $request ) {
		if ( in_array( $request->get_route(), $this->cache_rest_routes, true ) ) {
			$this->is_cache_rest_context( true );
		}

		return $response;
	}

	/**
	 * Clears the REST route flag once the request has been handled.
	 *
	 * @param mixed $response The response to pass through unchanged.
	 * @return mixed
	 */
	public function unflag_cache_rest_request( $response ) {
		$this->is_cache_rest_context( false );

		return $response;
	}
}

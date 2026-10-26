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
 * for the editor role (always) and any individually configured users in 829
 * Settings, scoped to only the three contexts that matter — never anywhere else in
 * wp-admin. On multisite WPE checks manage_network instead of manage_options for the
 * admin-bar link and network admin page, so the same scoping covers whichever
 * primitive cap the check asks for.
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
	 * The role that always gets WPE cache-clearing access.
	 *
	 * @var string
	 */
	const ALWAYS_ALLOWED_ROLE = 'editor';

	/**
	 * Setup module
	 */
	public function setup() {
		if ( ! WPT_IS_WPE ) {
			return;
		}

		// Persist the editor role into WPE's admin-bar-visibility option so the
		// cache-clear link shows for editors. Merged in, never overwriting, so
		// manual edits on the WP Engine Display tab are preserved.
		add_action( 'admin_init', [ $this, 'ensure_editor_adminbar_access' ] );

		add_filter( 'user_has_cap', [ $this, 'grant_wpe_cache_access' ], 10, 4 );
		add_filter( 'rest_request_before_callbacks', [ $this, 'flag_cache_rest_request' ], 5, 3 );
		add_filter( 'rest_request_after_callbacks', [ $this, 'unflag_cache_rest_request' ], 20 );
	}

	/**
	 * The individual user IDs configured in 829 Settings as allowed to clear the cache.
	 *
	 * @return int[]
	 */
	protected function get_allowed_user_ids() {
		$settings = Settings::get_settings();

		return ! empty( $settings['wpe_cache_clear_users'] )
			? array_map( 'intval', (array) $settings['wpe_cache_clear_users'] )
			: [];
	}

	/**
	 * Whether a user may clear the WPE cache: the editor role always qualifies, plus
	 * any individually configured users.
	 *
	 * @param \WP_User $user The user object.
	 * @return bool
	 */
	protected function user_can_clear_wpe_cache( $user ) {
		if ( empty( $user ) || empty( $user->ID ) ) {
			return false;
		}

		if ( in_array( self::ALWAYS_ALLOWED_ROLE, (array) $user->roles, true ) ) {
			return true;
		}

		return in_array( (int) $user->ID, $this->get_allowed_user_ids(), true );
	}

	/**
	 * Ensure the editor role is present in WPE's admin-bar-roles option so editors
	 * can see the cache-clear link. Merges rather than overwrites, and only writes
	 * when the role is missing.
	 */
	public function ensure_editor_adminbar_access() {
		$roles = get_option( 'wpe-adminbar-roles', [] );
		$roles = is_array( $roles ) ? $roles : [];

		if ( in_array( self::ALWAYS_ALLOWED_ROLE, $roles, true ) ) {
			return;
		}

		$roles[] = self::ALWAYS_ALLOWED_ROLE;
		update_option( 'wpe-adminbar-roles', array_values( array_unique( $roles ) ) );
	}

	/**
	 * Grant the capabilities WP Engine checks for cache clearing to qualifying
	 * users (the editor role and individually configured users), scoped to the
	 * admin-bar link, admin page tab, and REST routes.
	 *
	 * On multisite WPE gates the admin-bar link and network admin page on
	 * manage_network (see WpeCommon::get_required_admin_capability), while the
	 * caching tab view and REST routes still check manage_options — so we grant
	 * whichever primitive cap the current check is asking for. During admin-bar
	 * build WPE also gates link visibility on user_has_access(), which calls
	 * user_can( $user, 'editor' ) against the saved adminbar roles; we satisfy that
	 * for configured users whose own role wouldn't otherwise match.
	 *
	 * @param array    $allcaps All the capabilities of the user.
	 * @param array    $caps    Required primitive capabilities for the requested capability.
	 * @param array    $args    Arguments that accompany the requested capability check.
	 * @param \WP_User $user    The user object.
	 * @return array
	 */
	public function grant_wpe_cache_access( $allcaps, $caps, $args, $user ) {
		// Cheap relevance gate first — this filter fires on nearly every cap check,
		// but we only ever touch the admin caps WPE needs plus the 'editor' marker
		// its visibility check reads.
		$admin_caps = is_multisite() ? [ 'manage_options', 'manage_network' ] : [ 'manage_options' ];

		if ( empty( array_intersect( array_merge( $admin_caps, [ self::ALWAYS_ALLOWED_ROLE ] ), $caps ) ) ) {
			return $allcaps;
		}

		$in_admin_bar = $this->is_admin_bar_cache_link_context();

		if ( ! $in_admin_bar && ! $this->is_cache_admin_page_context() && ! $this->is_cache_rest_context() ) {
			return $allcaps;
		}

		if ( ! $this->user_can_clear_wpe_cache( $user ) ) {
			return $allcaps;
		}

		foreach ( array_intersect( $admin_caps, $caps ) as $cap ) {
			$allcaps[ $cap ] = true;
		}

		// Satisfy WPE's user_has_access() visibility check for configured users.
		// Only flags has_cap( 'editor' ) true — it does not grant the editor role's
		// other capabilities.
		if ( $in_admin_bar && in_array( self::ALWAYS_ALLOWED_ROLE, $caps, true ) ) {
			$allcaps[ self::ALWAYS_ALLOWED_ROLE ] = true;
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

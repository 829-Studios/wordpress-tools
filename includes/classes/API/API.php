<?php
/**
 * REST API functionality
 *
 * @package  WordPressTools
 */

namespace WordPressTools\API;

use WordPressTools\Singleton;
use WordPressTools\Settings\Settings;

/**
 * REST API customizations class
 */
class API {

	use Singleton;

	/**
	 * Setup module
	 *
	 * @since 1.7
	 */
	public function setup() {
		// Make sure this runs somewhat late but before core's cookie auth at 100
		add_filter( 'rest_authentication_errors', [ $this, 'restrict_rest_api' ], 99 );
		add_filter( 'rest_endpoints', [ $this, 'restrict_user_endpoints' ] );
	}

	/**
	 * Return a 403 status and corresponding error for unauthed REST API access.
	 *
	 * @param  WP_Error|null|bool $result Error from another authentication handler,
	 *                                    null if we should handle it, or another value
	 *                                    if not.
	 * @return WP_Error|null|bool
	 */
	public function restrict_rest_api( $result ) {
		// Respect other handlers
		if ( null !== $result ) {
			return $result;
		}

		$settings = Settings::get_settings();
		$restrict = $settings['restrict_rest_api'];

		if ( 'all' === $restrict && ! $this->can_access_rest_api() ) {
			return new \WP_Error( 'rest_api_restricted', esc_html__( 'Authentication Required', 'wordpress-tools' ), array( 'status' => rest_authorization_required_code() ) );
		}

		return $result;
	}

	/**
	 * Remove user endpoints for unauthed users.
	 *
	 * @param  array $endpoints Array of endpoints
	 * @return array
	 */
	public function restrict_user_endpoints( $endpoints ) {
		$settings = Settings::get_settings();
		$restrict = $settings['restrict_rest_api'];

		if ( 'none' === $restrict ) {
			return $endpoints;
		}

		if ( ! $this->can_access_rest_api() ) {
			$keys = preg_grep( '/\/wp\/v2\/users\b/', array_keys( $endpoints ) );

			foreach ( $keys as $key ) {
				unset( $endpoints[ $key ] );
			}

			return $endpoints;
		}

		return $endpoints;
	}

	/**
	 * Check if user can access REST API based on our criteria
	 *
	 * @param  int $user_id User ID
	 * @return bool         Whether the given user can access the REST API
	 */
	public function can_access_rest_api( $user_id = 0 ) {
		global $wp;

		$route = '';

		if ( isset( $wp->query_vars['rest_route'] ) ) {
			$route = $wp->query_vars['rest_route'];
		}

		$allowed_rest_routes_override = apply_filters( 'wpt_rest_api_allowlist', [] );

		return is_user_logged_in() || in_array( $route, $allowed_rest_routes_override, true );
	}
}

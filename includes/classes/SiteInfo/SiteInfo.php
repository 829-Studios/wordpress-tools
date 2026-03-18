<?php
/**
 * Site Info REST endpoint
 *
 * @package  WordPressTools
 */

namespace WordPressTools\SiteInfo;

use WordPressTools\Singleton;
use WordPressTools\Settings\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Site Info class
 *
 * Exposes comprehensive site information via a REST endpoint.
 */
class SiteInfo {

	use Singleton;

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	const REST_NAMESPACE = 'wpt/v1';

	/**
	 * REST route.
	 *
	 * @var string
	 */
	const REST_ROUTE = '/site-info';

	/**
	 * Setup module
	 */
	public function setup() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
		add_filter( 'wpt_rest_api_allowlist', [ $this, 'allowlist_route' ] );
	}

	/**
	 * Add our route to the REST API allowlist so unauthenticated
	 * requests are not blocked before our permission callback runs.
	 *
	 * @param array $allowlist Allowed routes.
	 * @return array
	 */
	public function allowlist_route( $allowlist ) {
		$allowlist[] = '/' . self::REST_NAMESPACE . self::REST_ROUTE;
		return $allowlist;
	}

	/**
	 * Register REST routes.
	 */
	public function register_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE,
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'get_site_info' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
	}

	/**
	 * Check permission via X-Wpt-Key header against WPT_DASHBOARD_API_KEY constant.
	 * Local environments bypass authentication entirely.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return true|\WP_Error
	 */
	public function check_permission( $request ) {
		if ( \WordPressTools\Utils\is_local_environment() ) {
			return true;
		}

		$api_key = Settings::get_dashboard_api_key();

		if ( empty( $api_key ) ) {
			return new \WP_Error(
				'rest_forbidden',
				'API key is not configured.',
				[ 'status' => 403 ]
			);
		}

		$provided_key = $request->get_header( 'X-Wpt-Key' );

		if ( empty( $provided_key ) || ! hash_equals( $api_key, $provided_key ) ) {
			return new \WP_Error(
				'rest_forbidden',
				'Invalid API key.',
				[ 'status' => 403 ]
			);
		}

		return true;
	}

	/**
	 * GET callback — return comprehensive site info.
	 * On multisite with network activation, returns an array of all sites.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_site_info() {
		if ( WPT_IS_NETWORK && is_multisite() ) {
			$sites = get_sites( [ 'number' => 0 ] );
			$data  = [];

			foreach ( $sites as $site ) {
				switch_to_blog( $site->blog_id );
				$data[] = $this->get_single_site_info();
				restore_current_blog();
			}

			return rest_ensure_response( $data );
		}

		return rest_ensure_response( $this->get_single_site_info() );
	}

	/**
	 * Get info for a single site.
	 *
	 * @return array
	 */
	private function get_single_site_info() {
		return [
			'system'        => $this->get_system_info(),
			'plugins'       => $this->get_plugins_info(),
			'themes'        => $this->get_themes_info(),
			'users'         => $this->get_users_info(),
			'activity_logs' => $this->get_activity_logs(),
		];
	}

	/**
	 * Get plugins info.
	 *
	 * @return array
	 */
	private function get_plugins_info() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all_plugins    = get_plugins();
		$active_plugins = (array) get_option( 'active_plugins', [] );

		$network_active = [];
		if ( is_multisite() ) {
			$network_active = array_keys( (array) get_site_option( 'active_sitewide_plugins', [] ) );
		}

		$plugins = [];

		foreach ( $all_plugins as $basename => $plugin_data ) {
			if ( in_array( $basename, $network_active, true ) ) {
				$status = 'active-network';
			} elseif ( in_array( $basename, $active_plugins, true ) ) {
				$status = 'active';
			} else {
				$status = 'inactive';
			}

			$slug = dirname( $basename );
			if ( '.' === $slug ) {
				$slug = basename( $basename, '.php' );
			}

			$plugins[] = [
				'slug'    => $slug,
				'name'    => $plugin_data['Name'],
				'status'  => $status,
				'version' => $plugin_data['Version'],
			];
		}

		return $plugins;
	}

	/**
	 * Get themes info.
	 *
	 * @return array
	 */
	private function get_themes_info() {
		$all_themes   = wp_get_themes();
		$active_theme = get_stylesheet();

		$themes = [];

		foreach ( $all_themes as $slug => $theme ) {
			$themes[] = [
				'slug'    => $slug,
				'name'    => $theme->get( 'Name' ),
				'status'  => ( $slug === $active_theme ) ? 'active' : 'inactive',
				'version' => $theme->get( 'Version' ),
			];
		}

		return $themes;
	}

	/**
	 * Get all users info.
	 *
	 * @return array
	 */
	private function get_users_info() {
		$users     = get_users( [ 'number' => -1 ] );
		$user_list = [];

		foreach ( $users as $user ) {
			$roles = ! empty( $user->roles ) ? implode( ', ', $user->roles ) : 'none';

			$user_list[] = [
				'email' => $user->user_email,
				'name'  => $user->display_name,
				'role'  => $roles,
				'id'    => $user->ID,
			];
		}

		return $user_list;
	}

	/**
	 * Get recent activity logs.
	 *
	 * @return array
	 */
	private function get_activity_logs() {
		return ActivityLog::instance()->get_recent_logs();
	}

	/**
	 * Get system info.
	 *
	 * @return array
	 */
	private function get_system_info() {
		global $wp_version, $wpdb;

		return [
			'blog_id'               => get_current_blog_id(),
			'site_url'              => site_url(),
			'home_url'              => home_url(),
			'wp_version'            => $wp_version,
			'php_version'           => PHP_VERSION,
			'db_version'            => $wpdb->db_version(),
			'is_multisite'          => is_multisite(),
			'external_object_cache' => wp_using_ext_object_cache(),
			'xmlrpc_enabled'        => (bool) apply_filters( 'xmlrpc_enabled', true ),
			'php_memory_limit'      => ini_get( 'memory_limit' ),
			'php_max_exec_time'     => (int) ini_get( 'max_execution_time' ),
		];
	}
}

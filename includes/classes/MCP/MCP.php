<?php
/**
 * MCP integration
 *
 * Registers 829 Tools abilities via the WordPress MCP Adapter.
 *
 * @package  WordPressTools
 */

namespace WordPressTools\MCP;

use WordPressTools\Singleton;
use WordPressTools\Settings\Settings;
use WP\MCP\Core\McpAdapter;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MCP class
 */
class MCP {

	use Singleton;

	/**
	 * Setup module
	 */
	public function setup() {
		if ( ! class_exists( McpAdapter::class ) ) {
			return;
		}

		McpAdapter::instance();

		add_action( 'wp_abilities_api_categories_init', [ $this, 'register_category' ] );
		add_action( 'wp_abilities_api_init', [ $this, 'register_abilities' ] );
	}

	/**
	 * Whether MCP is enabled via settings.
	 *
	 * @return bool
	 */
	private function is_enabled() {
		$settings = Settings::get_settings();
		return ! empty( $settings['enable_mcp'] );
	}

	/**
	 * Register the 829 Tools ability category.
	 */
	public function register_category() {
		if ( ! $this->is_enabled() ) {
			return;
		}

		wp_register_ability_category(
			'829-tools',
			array(
				'label'       => '829 Tools',
				'description' => 'Site management abilities for 829 Studios WordPress Tools.',
			)
		);
	}

	/**
	 * Register all MCP abilities.
	 */
	public function register_abilities() {
		if ( ! $this->is_enabled() ) {
			return;
		}

		wp_register_ability(
			'829-tools/get-site-info',
			array(
				'category'            => '829-tools',
				'label'               => 'Get Site Info',
				'description'         => 'Returns system information including WordPress version, PHP version, database version, and environment details.',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'site_info' => array( 'type' => 'object' ),
					),
				),
				'permission_callback' => [ $this, 'check_admin_permission' ],
				'execute_callback'    => [ $this, 'get_site_info' ],
				'meta'                => array(
					'mcp'         => array( 'public' => true ),
					'annotations' => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
				),
			)
		);

		wp_register_ability(
			'829-tools/list-plugins',
			array(
				'category'            => '829-tools',
				'label'               => 'List Plugins',
				'description'         => 'Returns all installed plugins with their name, version, and activation status.',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'plugins' => array( 'type' => 'array' ),
					),
				),
				'permission_callback' => [ $this, 'check_admin_permission' ],
				'execute_callback'    => [ $this, 'list_plugins' ],
				'meta'                => array(
					'mcp'         => array( 'public' => true ),
					'annotations' => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
				),
			)
		);

		wp_register_ability(
			'829-tools/list-themes',
			array(
				'category'            => '829-tools',
				'label'               => 'List Themes',
				'description'         => 'Returns all installed themes with their name, version, and activation status.',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'themes' => array( 'type' => 'array' ),
					),
				),
				'permission_callback' => [ $this, 'check_admin_permission' ],
				'execute_callback'    => [ $this, 'list_themes' ],
				'meta'                => array(
					'mcp'         => array( 'public' => true ),
					'annotations' => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
				),
			)
		);

		wp_register_ability(
			'829-tools/list-users',
			array(
				'category'            => '829-tools',
				'label'               => 'List Users',
				'description'         => 'Returns all users with their display name, email, and role.',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'users' => array( 'type' => 'array' ),
					),
				),
				'permission_callback' => [ $this, 'check_admin_permission' ],
				'execute_callback'    => [ $this, 'list_users' ],
				'meta'                => array(
					'mcp'         => array( 'public' => true ),
					'annotations' => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
				),
			)
		);
	}

	/**
	 * Permission callback: requires manage_options.
	 *
	 * @return true|WP_Error
	 */
	public function check_admin_permission() {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'not_authenticated', 'Authentication required.' );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'insufficient_permission', 'Administrator access required.' );
		}

		return true;
	}

	/**
	 * Execute callback: get site info.
	 *
	 * @return array
	 */
	public function get_site_info() {
		global $wp_version, $wpdb;

		return array(
			'site_info' => array(
				'blog_id'               => get_current_blog_id(),
				'site_url'              => site_url(),
				'home_url'              => home_url(),
				'site_name'             => get_bloginfo( 'name' ),
				'wp_version'            => $wp_version,
				'php_version'           => PHP_VERSION,
				'db_version'            => $wpdb->db_version(),
				'is_multisite'          => is_multisite(),
				'external_object_cache' => wp_using_ext_object_cache(),
				'php_memory_limit'      => ini_get( 'memory_limit' ),
				'php_max_exec_time'     => (int) ini_get( 'max_execution_time' ),
				'environment'           => defined( 'WP_ENVIRONMENT_TYPE' ) ? WP_ENVIRONMENT_TYPE : 'production',
			),
		);
	}

	/**
	 * Execute callback: list plugins.
	 *
	 * @return array
	 */
	public function list_plugins() {
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

			$plugins[] = array(
				'slug'    => $slug,
				'name'    => $plugin_data['Name'],
				'version' => $plugin_data['Version'],
				'status'  => $status,
			);
		}

		return array( 'plugins' => $plugins );
	}

	/**
	 * Execute callback: list themes.
	 *
	 * @return array
	 */
	public function list_themes() {
		$all_themes   = wp_get_themes();
		$active_theme = get_stylesheet();

		$themes = [];

		foreach ( $all_themes as $slug => $theme ) {
			$themes[] = array(
				'slug'    => $slug,
				'name'    => $theme->get( 'Name' ),
				'version' => $theme->get( 'Version' ),
				'status'  => ( $slug === $active_theme ) ? 'active' : 'inactive',
			);
		}

		return array( 'themes' => $themes );
	}

	/**
	 * Execute callback: list users.
	 *
	 * @return array
	 */
	public function list_users() {
		$users     = get_users( [ 'number' => -1 ] );
		$user_list = [];

		foreach ( $users as $user ) {
			$user_list[] = array(
				'id'    => $user->ID,
				'name'  => $user->display_name,
				'email' => $user->user_email,
				'role'  => ! empty( $user->roles ) ? implode( ', ', $user->roles ) : 'none',
			);
		}

		return array( 'users' => $user_list );
	}
}

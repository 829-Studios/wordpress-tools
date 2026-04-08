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

		// The Abilities API (wp_register_ability etc.) is added in WordPress 6.9.
		// Bail gracefully on older versions so we don't trigger fatal errors.
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		McpAdapter::instance();

		add_action( 'wp_abilities_api_categories_init', [ $this, 'register_category' ] );
		add_action( 'wp_abilities_api_init', [ $this, 'register_abilities' ] );

		// Boot Propel-specific abilities when the Propel theme is active.
		PropelMCP::instance();
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
				'description'         => 'Returns installed plugins with their name, version, and activation status. Filter by status to see only active plugins.',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'status' => array(
							'type'        => 'string',
							'description' => 'Filter by activation status: "active" (site-activated), "active-network" (network-activated), "inactive", or omit for all.',
							'enum'        => array( 'active', 'active-network', 'inactive' ),
						),
					),
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

		wp_register_ability(
			'829-tools/list-menus',
			array(
				'category'            => '829-tools',
				'label'               => 'List Menus',
				'description'         => 'Returns all registered WordPress menus with their assigned theme locations.',
				'input_schema'        => array( 'type' => 'object', 'properties' => array() ),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array( 'menus' => array( 'type' => 'array' ) ),
				),
				'permission_callback' => [ $this, 'check_admin_permission' ],
				'execute_callback'    => [ $this, 'list_menus' ],
				'meta'                => array(
					'mcp'         => array( 'public' => true ),
					'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ),
				),
			)
		);

		wp_register_ability(
			'829-tools/get-menu',
			array(
				'category'            => '829-tools',
				'label'               => 'Get Menu',
				'description'         => 'Returns a single menu with all its items, including hierarchy (parent_id), URLs, types, and link targets.',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id' => array( 'type' => 'integer', 'description' => 'The menu term ID.' ),
					),
					'required' => array( 'id' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array( 'menu' => array( 'type' => 'object' ) ),
				),
				'permission_callback' => [ $this, 'check_admin_permission' ],
				'execute_callback'    => [ $this, 'get_menu' ],
				'meta'                => array(
					'mcp'         => array( 'public' => true ),
					'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ),
				),
			)
		);

		wp_register_ability(
			'829-tools/create-menu',
			array(
				'category'            => '829-tools',
				'label'               => 'Create Menu',
				'description'         => 'Creates a new WordPress navigation menu. Optionally assigns it to a theme location and populates it with items.',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'name'     => array( 'type' => 'string', 'description' => 'The menu name.' ),
						'location' => array( 'type' => 'string', 'description' => 'Theme location slug to assign this menu to (e.g. "primary", "footer").' ),
						'items'    => array(
							'type'        => 'array',
							'description' => 'Menu items to add.',
							'items'       => array( 'type' => 'object' ),
						),
					),
					'required' => array( 'name' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array( 'menu_id' => array( 'type' => 'integer' ) ),
				),
				'permission_callback' => [ $this, 'check_admin_permission' ],
				'execute_callback'    => [ $this, 'create_menu' ],
				'meta'                => array(
					'mcp'         => array( 'public' => true ),
					'annotations' => array( 'destructive' => false, 'idempotent' => false ),
				),
			)
		);

		wp_register_ability(
			'829-tools/update-menu',
			array(
				'category'            => '829-tools',
				'label'               => 'Update Menu',
				'description'         => 'Updates a menu\'s name or location assignment. If items are provided they replace all existing items — call get-menu first to preserve existing ones.',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id'       => array( 'type' => 'integer', 'description' => 'The menu term ID.' ),
						'name'     => array( 'type' => 'string', 'description' => 'New menu name.' ),
						'location' => array( 'type' => 'string', 'description' => 'Theme location slug to assign this menu to.' ),
						'items'    => array(
							'type'        => 'array',
							'description' => 'Full replacement item list. Each item: title, url, type (custom/post_type/taxonomy), object, object_id, parent_id, position, target, classes.',
							'items'       => array( 'type' => 'object' ),
						),
					),
					'required' => array( 'id' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array( 'updated' => array( 'type' => 'boolean' ) ),
				),
				'permission_callback' => [ $this, 'check_admin_permission' ],
				'execute_callback'    => [ $this, 'update_menu' ],
				'meta'                => array(
					'mcp'         => array( 'public' => true ),
					'annotations' => array( 'destructive' => true, 'idempotent' => true ),
				),
			)
		);

		wp_register_ability(
			'829-tools/delete-menu',
			array(
				'category'            => '829-tools',
				'label'               => 'Delete Menu',
				'description'         => 'Permanently deletes a WordPress navigation menu and all its items.',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id' => array( 'type' => 'integer', 'description' => 'The menu term ID.' ),
					),
					'required' => array( 'id' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array( 'deleted' => array( 'type' => 'boolean' ) ),
				),
				'permission_callback' => [ $this, 'check_admin_permission' ],
				'execute_callback'    => [ $this, 'delete_menu' ],
				'meta'                => array(
					'mcp'         => array( 'public' => true ),
					'annotations' => array( 'destructive' => true, 'idempotent' => false ),
				),
			)
		);

		wp_register_ability(
			'829-tools/search-options',
			array(
				'category'            => '829-tools',
				'label'               => 'Search Options',
				'description'         => 'Searches the wp_options table by option name. Transients are excluded by default. Security keys and salts are always blocked.',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'search'            => array( 'type' => 'string', 'description' => 'Option name pattern to search for. Supports % as wildcard (e.g. "wpt_%").' ),
						'autoload'          => array( 'type' => 'string', 'description' => 'Filter by autoload: "yes", "no", or omit for all.' ),
						'include_transients' => array( 'type' => 'boolean', 'description' => 'Include transient options (those prefixed with _transient_ or _site_transient_). Default false.', 'default' => false ),
						'per_page'          => array( 'type' => 'integer', 'description' => 'Results per page. Default 50, max 200.', 'default' => 50 ),
						'page'              => array( 'type' => 'integer', 'description' => 'Page number. Default 1.', 'default' => 1 ),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'options' => array( 'type' => 'array' ),
						'total'   => array( 'type' => 'integer' ),
						'pages'   => array( 'type' => 'integer' ),
					),
				),
				'permission_callback' => [ $this, 'check_admin_permission' ],
				'execute_callback'    => [ $this, 'search_options' ],
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
			'829-tools/get-option',
			array(
				'category'            => '829-tools',
				'label'               => 'Get Option',
				'description'         => 'Returns a single WordPress option by exact name. Serialized values are automatically decoded.',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'name' => array( 'type' => 'string', 'description' => 'The option name.' ),
					),
					'required' => array( 'name' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'option' => array( 'type' => 'object' ),
					),
				),
				'permission_callback' => [ $this, 'check_admin_permission' ],
				'execute_callback'    => [ $this, 'get_option' ],
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
			'829-tools/create-option',
			array(
				'category'            => '829-tools',
				'label'               => 'Create Option',
				'description'         => 'Adds a new WordPress option. Fails if the option already exists — use update-option to overwrite.',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'name'     => array( 'type' => 'string', 'description' => 'The option name.' ),
						'value'    => array( 'description' => 'The option value. Objects and arrays will be serialized automatically.' ),
						'autoload' => array( 'type' => 'string', 'description' => 'Whether to autoload on every page load: "yes" or "no". Default "yes".', 'default' => 'yes' ),
					),
					'required' => array( 'name', 'value' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'created' => array( 'type' => 'boolean' ),
					),
				),
				'permission_callback' => [ $this, 'check_admin_permission' ],
				'execute_callback'    => [ $this, 'create_option' ],
				'meta'                => array(
					'mcp'         => array( 'public' => true ),
					'annotations' => array(
						'destructive' => false,
						'idempotent'  => false,
					),
				),
			)
		);

		wp_register_ability(
			'829-tools/update-option',
			array(
				'category'            => '829-tools',
				'label'               => 'Update Option',
				'description'         => 'Creates or updates a WordPress option. If the option does not exist it will be created.',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'name'     => array( 'type' => 'string', 'description' => 'The option name.' ),
						'value'    => array( 'description' => 'The new option value. Objects and arrays will be serialized automatically.' ),
						'autoload' => array( 'type' => 'string', 'description' => 'Whether to autoload: "yes" or "no". Omit to keep the existing autoload value.' ),
					),
					'required' => array( 'name', 'value' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'updated' => array( 'type' => 'boolean' ),
					),
				),
				'permission_callback' => [ $this, 'check_admin_permission' ],
				'execute_callback'    => [ $this, 'update_option' ],
				'meta'                => array(
					'mcp'         => array( 'public' => true ),
					'annotations' => array(
						'destructive' => true,
						'idempotent'  => true,
					),
				),
			)
		);

		wp_register_ability(
			'829-tools/delete-option',
			array(
				'category'            => '829-tools',
				'label'               => 'Delete Option',
				'description'         => 'Permanently deletes a WordPress option. This cannot be undone.',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'name' => array( 'type' => 'string', 'description' => 'The option name to delete.' ),
					),
					'required' => array( 'name' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'deleted' => array( 'type' => 'boolean' ),
					),
				),
				'permission_callback' => [ $this, 'check_admin_permission' ],
				'execute_callback'    => [ $this, 'delete_option' ],
				'meta'                => array(
					'mcp'         => array( 'public' => true ),
					'annotations' => array(
						'destructive' => true,
						'idempotent'  => false,
					),
				),
			)
		);

		wp_register_ability(
			'829-tools/search-media',
			array(
				'category'            => '829-tools',
				'label'               => 'Search Media',
				'description'         => 'Searches the media library by keyword (title, caption, alt text, description, filename), MIME type, and date range. Returns URLs for all registered image sizes.',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'search'     => array( 'type' => 'string', 'description' => 'Keyword to search across title, caption, alt text, description, and filename.' ),
						'mime_type'  => array( 'type' => 'string', 'description' => 'Filter by MIME type or prefix: "image", "image/jpeg", "image/png", "image/gif", "image/webp", "image/svg+xml", "video", "application/pdf", etc.' ),
						'after'      => array( 'type' => 'string', 'description' => 'Return items uploaded after this date (YYYY-MM-DD).' ),
						'before'     => array( 'type' => 'string', 'description' => 'Return items uploaded before this date (YYYY-MM-DD).' ),
						'per_page'   => array( 'type' => 'integer', 'description' => 'Results per page. Default 20, max 100.', 'default' => 20 ),
						'page'       => array( 'type' => 'integer', 'description' => 'Page number. Default 1.', 'default' => 1 ),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'media' => array( 'type' => 'array' ),
						'total' => array( 'type' => 'integer' ),
						'pages' => array( 'type' => 'integer' ),
					),
				),
				'permission_callback' => [ $this, 'check_admin_permission' ],
				'execute_callback'    => [ $this, 'search_media' ],
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
			'829-tools/get-media',
			array(
				'category'            => '829-tools',
				'label'               => 'Get Media',
				'description'         => 'Returns full detail for a single media attachment: alt text, caption, description, dimensions, file size, MIME type, and URLs for every registered image size.',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id' => array( 'type' => 'integer', 'description' => 'The attachment post ID.' ),
					),
					'required' => array( 'id' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'media' => array( 'type' => 'object' ),
					),
				),
				'permission_callback' => [ $this, 'check_admin_permission' ],
				'execute_callback'    => [ $this, 'get_media' ],
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
			'829-tools/list-allowed-blocks',
			array(
				'category'            => '829-tools',
				'label'               => 'List Allowed Blocks',
				'description'         => 'Returns all block types permitted in the block editor on this site, with their slug, title, and category. Reflects any theme or plugin restrictions (e.g. Propel\'s block allowlist).',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'blocks' => array( 'type' => 'array' ),
						'total'  => array( 'type' => 'integer' ),
					),
				),
				'permission_callback' => [ $this, 'check_admin_permission' ],
				'execute_callback'    => [ $this, 'list_allowed_blocks' ],
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
			'829-tools/get-content-schema',
			array(
				'category'            => '829-tools',
				'label'               => 'Get Content Schema',
				'description'         => 'Returns the content model for this site: registered post types with their supported features and taxonomies, all public taxonomies, ACF field groups per post type (when ACF is active), and block markup syntax with examples. Use this before creating or editing content to understand the available fields and block format. Optionally filter to a single post type.',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_type' => array( 'type' => 'string', 'description' => 'Limit the response to a single post type slug (e.g. "post", "page", "event"). Omit for all public post types.' ),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'post_types'   => array( 'type' => 'array' ),
						'taxonomies'   => array( 'type' => 'array' ),
						'block_markup' => array( 'type' => 'object' ),
					),
				),
				'permission_callback' => [ $this, 'check_admin_permission' ],
				'execute_callback'    => [ $this, 'get_content_schema' ],
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
	 * @param  array $input Ability input.
	 * @return array
	 */
	public function list_plugins( $input = array() ) {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all_plugins    = get_plugins();
		$active_plugins = (array) get_option( 'active_plugins', [] );
		$status_filter  = $input['status'] ?? null;

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

			if ( $status_filter && $status !== $status_filter ) {
				continue;
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

	/**
	 * Execute callback: list all menus.
	 *
	 * @return array
	 */
	public function list_menus() {
		$menus                = wp_get_nav_menus();
		$locations            = get_nav_menu_locations();
		$registered_locations = get_registered_nav_menus();

		// Build term_id => assigned locations map.
		$menu_locations = array();
		foreach ( $locations as $slug => $term_id ) {
			$menu_locations[ $term_id ][] = array(
				'slug'  => $slug,
				'label' => $registered_locations[ $slug ] ?? $slug,
			);
		}

		$result = array();
		foreach ( $menus as $menu ) {
			$result[] = array(
				'id'        => $menu->term_id,
				'name'      => $menu->name,
				'slug'      => $menu->slug,
				'count'     => $menu->count,
				'locations' => $menu_locations[ $menu->term_id ] ?? array(),
			);
		}

		return array( 'menus' => $result );
	}

	/**
	 * Execute callback: get a single menu with items.
	 *
	 * @param  array $input Ability input.
	 * @return array|WP_Error
	 */
	public function get_menu( $input = array() ) {
		$id   = intval( $input['id'] ?? 0 );
		$menu = wp_get_nav_menu_object( $id );

		if ( ! $menu ) {
			return new WP_Error( 'not_found', 'Menu not found.' );
		}

		return array( 'menu' => $this->format_menu( $menu ) );
	}

	/**
	 * Execute callback: create a menu.
	 *
	 * @param  array $input Ability input.
	 * @return array|WP_Error
	 */
	public function create_menu( $input = array() ) {
		$name = $input['name'] ?? '';

		if ( empty( $name ) ) {
			return new WP_Error( 'missing_name', 'Menu name is required.' );
		}

		$menu_id = wp_create_nav_menu( $name );

		if ( is_wp_error( $menu_id ) ) {
			return $menu_id;
		}

		if ( ! empty( $input['location'] ) ) {
			$this->assign_menu_location( $menu_id, $input['location'] );
		}

		if ( ! empty( $input['items'] ) && is_array( $input['items'] ) ) {
			$this->save_menu_items( $menu_id, $input['items'] );
		}

		return array( 'menu_id' => $menu_id );
	}

	/**
	 * Execute callback: update a menu.
	 *
	 * @param  array $input Ability input.
	 * @return array|WP_Error
	 */
	public function update_menu( $input = array() ) {
		$id   = intval( $input['id'] ?? 0 );
		$menu = wp_get_nav_menu_object( $id );

		if ( ! $menu ) {
			return new WP_Error( 'not_found', 'Menu not found.' );
		}

		if ( isset( $input['name'] ) ) {
			$result = wp_update_nav_menu_object( $id, array( 'menu-name' => $input['name'] ) );

			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		if ( isset( $input['location'] ) ) {
			$this->assign_menu_location( $id, $input['location'] );
		}

		if ( isset( $input['items'] ) && is_array( $input['items'] ) ) {
			// Delete all existing items first, then re-create.
			$existing = wp_get_nav_menu_items( $id );
			if ( $existing ) {
				foreach ( $existing as $item ) {
					wp_delete_post( $item->ID, true );
				}
			}

			$this->save_menu_items( $id, $input['items'] );
		}

		return array( 'updated' => true );
	}

	/**
	 * Execute callback: delete a menu.
	 *
	 * @param  array $input Ability input.
	 * @return array|WP_Error
	 */
	public function delete_menu( $input = array() ) {
		$id   = intval( $input['id'] ?? 0 );
		$menu = wp_get_nav_menu_object( $id );

		if ( ! $menu ) {
			return new WP_Error( 'not_found', 'Menu not found.' );
		}

		$result = wp_delete_nav_menu( $id );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array( 'deleted' => (bool) $result );
	}

	/**
	 * Build a structured menu array including items.
	 *
	 * @param  object $menu WP_Term menu object.
	 * @return array
	 */
	private function format_menu( $menu ) {
		$locations            = get_nav_menu_locations();
		$registered_locations = get_registered_nav_menus();

		$assigned = array();
		foreach ( $locations as $slug => $term_id ) {
			if ( (int) $term_id === (int) $menu->term_id ) {
				$assigned[] = array(
					'slug'  => $slug,
					'label' => $registered_locations[ $slug ] ?? $slug,
				);
			}
		}

		$raw_items = wp_get_nav_menu_items( $menu->term_id ) ?: array();
		$items     = array();

		foreach ( $raw_items as $item ) {
			$items[] = array(
				'id'          => $item->ID,
				'title'       => $item->title,
				'url'         => $item->url,
				'type'        => $item->type,
				'object'      => $item->object,
				'object_id'   => (int) $item->object_id,
				'parent_id'   => (int) $item->menu_item_parent,
				'position'    => (int) $item->menu_order,
				'target'      => $item->target,
				'classes'     => array_filter( (array) $item->classes ),
				'description' => $item->description,
				'attr_title'  => $item->attr_title,
			);
		}

		return array(
			'id'        => $menu->term_id,
			'name'      => $menu->name,
			'slug'      => $menu->slug,
			'count'     => $menu->count,
			'locations' => $assigned,
			'items'     => $items,
		);
	}

	/**
	 * Assign a menu to a theme location.
	 *
	 * @param int    $menu_id       Menu term ID.
	 * @param string $location_slug Theme location slug.
	 */
	private function assign_menu_location( $menu_id, $location_slug ) {
		$locations                  = get_nav_menu_locations();
		$locations[ $location_slug ] = $menu_id;
		set_theme_mod( 'nav_menu_locations', $locations );
	}

	/**
	 * Create menu items from an input array.
	 *
	 * Each item supports: title, url, type (custom/post_type/taxonomy),
	 * object, object_id, parent_id, position, target, classes, description, attr_title.
	 *
	 * @param int   $menu_id Menu term ID.
	 * @param array $items   Array of item definitions.
	 */
	private function save_menu_items( $menu_id, $items ) {
		foreach ( $items as $index => $item ) {
			wp_update_nav_menu_item(
				$menu_id,
				0,
				array(
					'menu-item-title'     => $item['title'] ?? '',
					'menu-item-url'       => $item['url'] ?? '',
					'menu-item-type'      => $item['type'] ?? 'custom',
					'menu-item-object'    => $item['object'] ?? '',
					'menu-item-object-id' => intval( $item['object_id'] ?? 0 ),
					'menu-item-parent-id' => intval( $item['parent_id'] ?? 0 ),
					'menu-item-position'  => intval( $item['position'] ?? ( $index + 1 ) ),
					'menu-item-target'    => $item['target'] ?? '',
					'menu-item-classes'   => $item['classes'] ?? '',
					'menu-item-description' => $item['description'] ?? '',
					'menu-item-attr-title'  => $item['attr_title'] ?? '',
					'menu-item-status'    => 'publish',
				)
			);
		}
	}

	/**
	 * Option names that are never readable or writable via MCP.
	 *
	 * @return string[]
	 */
	private function blocked_option_names() {
		return array(
			'auth_key',
			'secure_auth_key',
			'logged_in_key',
			'nonce_key',
			'auth_salt',
			'secure_auth_salt',
			'logged_in_salt',
			'nonce_salt',
		);
	}

	/**
	 * Check whether an option name is blocked.
	 *
	 * @param  string $name Option name.
	 * @return bool
	 */
	private function is_blocked_option( $name ) {
		return in_array( $name, $this->blocked_option_names(), true );
	}

	/**
	 * Execute callback: search options.
	 *
	 * @param  array $input Ability input.
	 * @return array
	 */
	public function search_options( $input = array() ) {
		global $wpdb;

		$per_page = min( intval( $input['per_page'] ?? 50 ), 200 );
		$page     = max( intval( $input['page'] ?? 1 ), 1 );
		$offset   = ( $page - 1 ) * $per_page;

		$where  = array( 'option_name NOT IN (' . implode( ', ', array_fill( 0, count( $this->blocked_option_names() ), '%s' ) ) . ')' );
		$params = $this->blocked_option_names();

		if ( ! empty( $input['search'] ) ) {
			$where[]  = 'option_name LIKE %s';
			$params[] = '%' . $wpdb->esc_like( $input['search'] ) . '%';
		}

		if ( empty( $input['include_transients'] ) ) {
			$where[]  = 'option_name NOT LIKE %s';
			$params[] = '\_transient\_%';
			$where[]  = 'option_name NOT LIKE %s';
			$params[] = '\_site\_transient\_%';
		}

		if ( isset( $input['autoload'] ) && in_array( $input['autoload'], array( 'yes', 'no' ), true ) ) {
			$where[]  = 'autoload = %s';
			$params[] = $input['autoload'];
		}

		$where_sql = 'WHERE ' . implode( ' AND ', $where );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} {$where_sql}", $params )
		);

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name, option_value, autoload FROM {$wpdb->options} {$where_sql} ORDER BY option_name ASC LIMIT %d OFFSET %d",
				array_merge( $params, array( $per_page, $offset ) )
			)
		);
		// phpcs:enable

		$options = array();
		foreach ( $rows as $row ) {
			$options[] = array(
				'name'     => $row->option_name,
				'value'    => maybe_unserialize( $row->option_value ),
				'autoload' => $row->autoload,
			);
		}

		return array(
			'options' => $options,
			'total'   => $total,
			'pages'   => $per_page > 0 ? (int) ceil( $total / $per_page ) : 1,
		);
	}

	/**
	 * Execute callback: get a single option.
	 *
	 * @param  array $input Ability input.
	 * @return array|WP_Error
	 */
	public function get_option( $input = array() ) {
		$name = $input['name'] ?? '';

		if ( empty( $name ) ) {
			return new WP_Error( 'missing_name', 'Option name is required.' );
		}

		if ( $this->is_blocked_option( $name ) ) {
			return new WP_Error( 'blocked', "The option '{$name}' cannot be read via MCP." );
		}

		$value = get_option( $name, null );

		if ( null === $value ) {
			return new WP_Error( 'not_found', "Option '{$name}' does not exist." );
		}

		global $wpdb;
		$autoload = $wpdb->get_var( $wpdb->prepare( "SELECT autoload FROM {$wpdb->options} WHERE option_name = %s", $name ) );

		return array(
			'option' => array(
				'name'     => $name,
				'value'    => $value,
				'autoload' => $autoload,
			),
		);
	}

	/**
	 * Execute callback: create an option (add_option — fails if already exists).
	 *
	 * @param  array $input Ability input.
	 * @return array|WP_Error
	 */
	public function create_option( $input = array() ) {
		$name = $input['name'] ?? '';

		if ( empty( $name ) ) {
			return new WP_Error( 'missing_name', 'Option name is required.' );
		}

		if ( $this->is_blocked_option( $name ) ) {
			return new WP_Error( 'blocked', "The option '{$name}' cannot be written via MCP." );
		}

		if ( get_option( $name, null ) !== null ) {
			return new WP_Error( 'already_exists', "Option '{$name}' already exists. Use update-option to overwrite it." );
		}

		$autoload = isset( $input['autoload'] ) && 'no' === $input['autoload'] ? 'no' : 'yes';
		$result   = add_option( $name, $input['value'] ?? '', '', $autoload );

		return array( 'created' => (bool) $result );
	}

	/**
	 * Execute callback: update (or create) an option.
	 *
	 * @param  array $input Ability input.
	 * @return array|WP_Error
	 */
	public function update_option( $input = array() ) {
		$name = $input['name'] ?? '';

		if ( empty( $name ) ) {
			return new WP_Error( 'missing_name', 'Option name is required.' );
		}

		if ( $this->is_blocked_option( $name ) ) {
			return new WP_Error( 'blocked', "The option '{$name}' cannot be written via MCP." );
		}

		$args = array( $name, $input['value'] ?? '' );

		if ( isset( $input['autoload'] ) && in_array( $input['autoload'], array( 'yes', 'no' ), true ) ) {
			$args[] = $input['autoload'];
		}

		$result = update_option( ...$args );

		return array( 'updated' => (bool) $result );
	}

	/**
	 * Execute callback: delete an option.
	 *
	 * @param  array $input Ability input.
	 * @return array|WP_Error
	 */
	public function delete_option( $input = array() ) {
		$name = $input['name'] ?? '';

		if ( empty( $name ) ) {
			return new WP_Error( 'missing_name', 'Option name is required.' );
		}

		if ( $this->is_blocked_option( $name ) ) {
			return new WP_Error( 'blocked', "The option '{$name}' cannot be deleted via MCP." );
		}

		if ( get_option( $name, null ) === null ) {
			return new WP_Error( 'not_found', "Option '{$name}' does not exist." );
		}

		$result = delete_option( $name );

		return array( 'deleted' => (bool) $result );
	}

	/**
	 * Execute callback: search media library.
	 *
	 * @param  array $input Ability input.
	 * @return array
	 */
	public function search_media( $input = array() ) {
		$per_page = min( intval( $input['per_page'] ?? 20 ), 100 );
		$page     = max( intval( $input['page'] ?? 1 ), 1 );

		$args = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		if ( ! empty( $input['search'] ) ) {
			$args['s'] = $input['search'];
		}

		if ( ! empty( $input['mime_type'] ) ) {
			$args['post_mime_type'] = $input['mime_type'];
		}

		if ( ! empty( $input['after'] ) || ! empty( $input['before'] ) ) {
			$args['date_query'] = array();

			if ( ! empty( $input['after'] ) ) {
				$args['date_query']['after'] = $input['after'];
			}

			if ( ! empty( $input['before'] ) ) {
				$args['date_query']['before'] = $input['before'];
			}
		}

		$query = new \WP_Query( $args );
		$media = array();

		foreach ( $query->posts as $post ) {
			$media[] = $this->format_attachment( $post );
		}

		return array(
			'media' => $media,
			'total' => $query->found_posts,
			'pages' => $query->max_num_pages ?: 1,
		);
	}

	/**
	 * Execute callback: get a single media attachment.
	 *
	 * @param  array $input Ability input.
	 * @return array|WP_Error
	 */
	public function get_media( $input = array() ) {
		$id   = intval( $input['id'] ?? 0 );
		$post = get_post( $id );

		if ( ! $post || 'attachment' !== $post->post_type ) {
			return new WP_Error( 'not_found', 'Attachment not found.' );
		}

		return array( 'media' => $this->format_attachment( $post, true ) );
	}

	/**
	 * Build a structured attachment array.
	 *
	 * @param  \WP_Post $post    The attachment post.
	 * @param  bool     $full    When true, include full metadata (dimensions, file size, all sizes).
	 * @return array
	 */
	private function format_attachment( $post, $full = false ) {
		$url       = wp_get_attachment_url( $post->ID );
		$mime_type = $post->post_mime_type;
		$is_image  = strpos( $mime_type, 'image/' ) === 0;
		$alt       = get_post_meta( $post->ID, '_wp_attachment_image_alt', true );

		$data = array(
			'id'          => $post->ID,
			'title'       => $post->post_title,
			'filename'    => basename( get_attached_file( $post->ID ) ),
			'url'         => $url,
			'mime_type'   => $mime_type,
			'alt'         => $alt,
			'caption'     => $post->post_excerpt,
			'description' => $post->post_content,
			'date'        => $post->post_date,
		);

		if ( $is_image ) {
			// Always include a thumbnail and the medium size for quick preview.
			$thumbnail = wp_get_attachment_image_src( $post->ID, 'thumbnail' );
			$medium    = wp_get_attachment_image_src( $post->ID, 'medium' );

			$data['thumbnail_url'] = $thumbnail ? $thumbnail[0] : $url;
			$data['medium_url']    = $medium ? $medium[0] : $url;
		}

		if ( $full ) {
			$meta = wp_get_attachment_metadata( $post->ID );

			if ( $is_image && $meta ) {
				$data['width']     = $meta['width'] ?? null;
				$data['height']    = $meta['height'] ?? null;
				$data['file_size'] = $meta['filesize'] ?? ( file_exists( get_attached_file( $post->ID ) ) ? filesize( get_attached_file( $post->ID ) ) : null );

				// All registered sizes.
				$sizes      = $meta['sizes'] ?? array();
				$upload_dir = wp_upload_dir();
				$base_url   = $upload_dir['baseurl'];
				$subdir     = dirname( $meta['file'] ?? '' );

				$data['sizes'] = array(
					'full' => array(
						'url'    => $url,
						'width'  => $meta['width'] ?? null,
						'height' => $meta['height'] ?? null,
					),
				);

				foreach ( $sizes as $size_name => $size_data ) {
					$data['sizes'][ $size_name ] = array(
						'url'    => trailingslashit( $base_url ) . trailingslashit( $subdir ) . $size_data['file'],
						'width'  => $size_data['width'],
						'height' => $size_data['height'],
					);
				}
			} elseif ( $meta ) {
				$data['file_size'] = $meta['filesize'] ?? null;
			}
		}

		return $data;
	}

	/**
	 * Execute callback: get content schema.
	 *
	 * Returns the full content model: post types, taxonomies, ACF fields (when available),
	 * and block markup syntax guidance.
	 *
	 * @param  array $input Ability input.
	 * @return array
	 */
	public function get_content_schema( $input = array() ) {
		$filter_post_type = $input['post_type'] ?? null;

		// Post types.
		$post_type_objects = get_post_types( array( 'public' => true ), 'objects' );
		$post_types        = array();

		foreach ( $post_type_objects as $type ) {
			if ( $filter_post_type && $type->name !== $filter_post_type ) {
				continue;
			}

			$supports = array_keys( get_all_post_type_supports( $type->name ) );

			$entry = array(
				'slug'           => $type->name,
				'label'          => $type->label,
				'singular_label' => $type->labels->singular_name ?? $type->label,
				'hierarchical'   => (bool) $type->hierarchical,
				'supports'       => $supports,
				'taxonomies'     => get_object_taxonomies( $type->name ),
			);

			// ACF field groups per post type.
			if ( function_exists( 'acf_get_field_groups' ) && function_exists( 'acf_get_fields' ) ) {
				$groups     = \acf_get_field_groups( array( 'post_type' => $type->name ) );
				$acf_groups = array();

				foreach ( $groups as $group ) {
					$fields     = \acf_get_fields( $group['key'] );
					$field_list = array();

					if ( $fields ) {
						foreach ( $fields as $field ) {
							$field_entry = array(
								'name'     => $field['name'],
								'label'    => $field['label'],
								'type'     => $field['type'],
								'required' => ! empty( $field['required'] ),
							);

							// For group/repeater fields, include sub-fields.
							if ( ! empty( $field['sub_fields'] ) ) {
								$field_entry['sub_fields'] = array_map(
									function ( $sf ) {
										return array(
											'name'     => $sf['name'],
											'label'    => $sf['label'],
											'type'     => $sf['type'],
											'required' => ! empty( $sf['required'] ),
										);
									},
									$field['sub_fields']
								);
							}

							$field_list[] = $field_entry;
						}
					}

					$acf_groups[] = array(
						'title'  => $group['title'],
						'key'    => $group['key'],
						'fields' => $field_list,
					);
				}

				if ( $acf_groups ) {
					$entry['acf_field_groups'] = $acf_groups;
				}
			}

			$post_types[] = $entry;
		}

		// Taxonomies.
		$taxonomy_objects = get_taxonomies( array( 'public' => true ), 'objects' );
		$taxonomies       = array();

		foreach ( $taxonomy_objects as $tax ) {
			if ( $filter_post_type && ! in_array( $filter_post_type, $tax->object_type, true ) ) {
				continue;
			}

			$taxonomies[] = array(
				'slug'         => $tax->name,
				'label'        => $tax->label,
				'hierarchical' => (bool) $tax->hierarchical,
				'post_types'   => $tax->object_type,
			);
		}

		// Block markup guidance.
		$block_markup = array(
			'description'   => 'WordPress block content is stored as HTML with special block comment delimiters. Attributes are JSON encoded in the opening comment.',
			'block_format'  => '<!-- wp:block-slug {"attr":"value"} --> optional inner HTML <!-- /wp:block-slug -->',
			'self_closing'  => '<!-- wp:block-slug {"attr":"value"} /-->',
			'core_example'  => '<!-- wp:paragraph --><p>Hello world</p><!-- /wp:paragraph -->',
			'acf_example'   => '<!-- wp:acf/my-block {"name":"acf/my-block","data":{"field_key":"value"},"mode":"preview"} /-->',
			'heading_example' => '<!-- wp:heading {"level":2} --><h2 class="wp-block-heading">My Heading</h2><!-- /wp:heading -->',
			'note'          => 'Use the list-allowed-blocks ability to see all blocks available on this site.',
		);

		return array(
			'post_types'   => $post_types,
			'taxonomies'   => $taxonomies,
			'block_markup' => $block_markup,
		);
	}

	/**
	 * Execute callback: list allowed blocks.
	 *
	 * Applies the allowed_block_types_all filter (which Propel and other themes/plugins hook into)
	 * then enriches each slug with its registered title and category.
	 *
	 * @return array
	 */
	public function list_allowed_blocks() {
		$registry       = \WP_Block_Type_Registry::get_instance();
		$all_registered = $registry->get_all_registered();
		$all_slugs      = array_keys( $all_registered );

		// Run through the filter so theme/plugin allowlists are respected.
		$allowed = apply_filters( 'allowed_block_types_all', $all_slugs, null );

		// If a filter returned true (all allowed) or something unexpected, use everything registered.
		if ( ! is_array( $allowed ) ) {
			$allowed = $all_slugs;
		}

		$blocks = array();

		foreach ( $allowed as $slug ) {
			$block_type = $all_registered[ $slug ] ?? $registry->get_registered( $slug );
			$blocks[]   = array(
				'slug'     => $slug,
				'title'    => $block_type ? ( $block_type->title ?? $slug ) : $slug,
				'category' => $block_type ? ( $block_type->category ?? '' ) : '',
			);
		}

		usort(
			$blocks,
			function ( $a, $b ) {
				$cat = strcmp( $a['category'], $b['category'] );
				return $cat !== 0 ? $cat : strcmp( $a['slug'], $b['slug'] );
			}
		);

		return array(
			'blocks' => $blocks,
			'total'  => count( $blocks ),
		);
	}
}

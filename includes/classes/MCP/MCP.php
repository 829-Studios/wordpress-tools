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
				'label'       => '829 Studios',
				'description' => 'Abilities for managing, writing content, and configuring WordPress sites built by 829 Studios.',
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
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array( 'menus' => array( 'type' => 'array' ) ),
				),
				'permission_callback' => [ $this, 'check_admin_permission' ],
				'execute_callback'    => [ $this, 'list_menus' ],
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
			'829-tools/get-menu',
			array(
				'category'            => '829-tools',
				'label'               => 'Get Menu',
				'description'         => 'Returns a single menu with all its items, including hierarchy (parent_id), URLs, types, and link targets.',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id' => array(
							'type'        => 'integer',
							'description' => 'The menu term ID.',
						),
					),
					'required'   => array( 'id' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array( 'menu' => array( 'type' => 'object' ) ),
				),
				'permission_callback' => [ $this, 'check_admin_permission' ],
				'execute_callback'    => [ $this, 'get_menu' ],
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
			'829-tools/create-menu',
			array(
				'category'            => '829-tools',
				'label'               => 'Create Menu',
				'description'         => 'Creates a new WordPress navigation menu. Optionally assigns it to a theme location and populates it with items.',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'name'     => array(
							'type'        => 'string',
							'description' => 'The menu name.',
						),
						'location' => array(
							'type'        => 'string',
							'description' => 'Theme location slug to assign this menu to (e.g. "primary", "footer").',
						),
						'items'    => array(
							'type'        => 'array',
							'description' => 'Menu items to add.',
							'items'       => array( 'type' => 'object' ),
						),
					),
					'required'   => array( 'name' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array( 'menu_id' => array( 'type' => 'integer' ) ),
				),
				'permission_callback' => [ $this, 'check_admin_permission' ],
				'execute_callback'    => [ $this, 'create_menu' ],
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
			'829-tools/update-menu',
			array(
				'category'            => '829-tools',
				'label'               => 'Update Menu',
				'description'         => 'Updates a menu\'s name or location assignment. If items are provided they replace all existing items — call get-menu first to preserve existing ones.',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id'       => array(
							'type'        => 'integer',
							'description' => 'The menu term ID.',
						),
						'name'     => array(
							'type'        => 'string',
							'description' => 'New menu name.',
						),
						'location' => array(
							'type'        => 'string',
							'description' => 'Theme location slug to assign this menu to.',
						),
						'items'    => array(
							'type'        => 'array',
							'description' => 'Full replacement item list. Each item: title, url, type (custom/post_type/taxonomy), object, object_id, parent_id, position, target, classes.',
							'items'       => array( 'type' => 'object' ),
						),
					),
					'required'   => array( 'id' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array( 'updated' => array( 'type' => 'boolean' ) ),
				),
				'permission_callback' => [ $this, 'check_admin_permission' ],
				'execute_callback'    => [ $this, 'update_menu' ],
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
			'829-tools/delete-menu',
			array(
				'category'            => '829-tools',
				'label'               => 'Delete Menu',
				'description'         => 'Permanently deletes a WordPress navigation menu and all its items.',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id' => array(
							'type'        => 'integer',
							'description' => 'The menu term ID.',
						),
					),
					'required'   => array( 'id' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array( 'deleted' => array( 'type' => 'boolean' ) ),
				),
				'permission_callback' => [ $this, 'check_admin_permission' ],
				'execute_callback'    => [ $this, 'delete_menu' ],
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
			'829-tools/search-options',
			array(
				'category'            => '829-tools',
				'label'               => 'Search Options',
				'description'         => 'Searches the wp_options table by option name. Transients are excluded by default. Security keys and salts are always blocked.',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'search'             => array(
							'type'        => 'string',
							'description' => 'Option name pattern to search for. Supports % as wildcard (e.g. "wpt_%").',
						),
						'autoload'           => array(
							'type'        => 'string',
							'description' => 'Filter by autoload: "yes", "no", or omit for all.',
						),
						'include_transients' => array(
							'type'        => 'boolean',
							'description' => 'Include transient options (those prefixed with _transient_ or _site_transient_). Default false.',
							'default'     => false,
						),
						'per_page'           => array(
							'type'        => 'integer',
							'description' => 'Results per page. Default 50, max 200.',
							'default'     => 50,
						),
						'page'               => array(
							'type'        => 'integer',
							'description' => 'Page number. Default 1.',
							'default'     => 1,
						),
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
						'name' => array(
							'type'        => 'string',
							'description' => 'The option name.',
						),
					),
					'required'   => array( 'name' ),
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
						'name'     => array(
							'type'        => 'string',
							'description' => 'The option name.',
						),
						'value'    => array( 'description' => 'The option value. Objects and arrays will be serialized automatically.' ),
						'autoload' => array(
							'type'        => 'string',
							'description' => 'Whether to autoload on every page load: "yes" or "no". Default "yes".',
							'default'     => 'yes',
						),
					),
					'required'   => array( 'name', 'value' ),
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
						'name'     => array(
							'type'        => 'string',
							'description' => 'The option name.',
						),
						'value'    => array( 'description' => 'The new option value. Objects and arrays will be serialized automatically.' ),
						'autoload' => array(
							'type'        => 'string',
							'description' => 'Whether to autoload: "yes" or "no". Omit to keep the existing autoload value.',
						),
					),
					'required'   => array( 'name', 'value' ),
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
						'name' => array(
							'type'        => 'string',
							'description' => 'The option name to delete.',
						),
					),
					'required'   => array( 'name' ),
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
						'search'    => array(
							'type'        => 'string',
							'description' => 'Keyword to search across title, caption, alt text, description, and filename.',
						),
						'mime_type' => array(
							'type'        => 'string',
							'description' => 'Filter by MIME type or prefix: "image", "image/jpeg", "image/png", "image/gif", "image/webp", "image/svg+xml", "video", "application/pdf", etc.',
						),
						'after'     => array(
							'type'        => 'string',
							'description' => 'Return items uploaded after this date (YYYY-MM-DD).',
						),
						'before'    => array(
							'type'        => 'string',
							'description' => 'Return items uploaded before this date (YYYY-MM-DD).',
						),
						'per_page'  => array(
							'type'        => 'integer',
							'description' => 'Results per page. Default 20, max 100.',
							'default'     => 20,
						),
						'page'      => array(
							'type'        => 'integer',
							'description' => 'Page number. Default 1.',
							'default'     => 1,
						),
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
						'id' => array(
							'type'        => 'integer',
							'description' => 'The attachment post ID.',
						),
					),
					'required'   => array( 'id' ),
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
				'description'         => 'Returns all block types permitted in the block editor on this site, with their slug, title, category, description, icon, and keywords. Reflects any theme or plugin restrictions. Use get-block-info for full attributes/supports detail on a specific block.',
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
			'829-tools/get-block-info',
			array(
				'category'            => '829-tools',
				'label'               => 'Get Block Info',
				'description'         => 'Returns full registration detail for a single block type (description, icon, keywords, supports, attributes, styles, example, etc). Use list-allowed-blocks first to find the slug.',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'slug' => array(
							'type'        => 'string',
							'description' => 'Block name/slug, e.g. "core/paragraph".',
						),
					),
					'required'   => array( 'slug' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'block' => array( 'type' => 'object' ),
					),
				),
				'permission_callback' => [ $this, 'check_admin_permission' ],
				'execute_callback'    => [ $this, 'get_block_info' ],
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

		// ── ACF ─────────────────────────────────────────────────────────────

		wp_register_ability(
			'829-tools/list-acf-blocks',
			array(
				'category'            => '829-tools',
				'label'               => 'List ACF Blocks',
				'description'         => 'Returns all registered ACF block types with their field definitions. Use this to understand what blocks are available and what data they accept before inserting block markup into post content.',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array( 'blocks' => array( 'type' => 'array' ) ),
				),
				'permission_callback' => [ $this, 'check_admin_permission' ],
				'execute_callback'    => [ $this, 'list_acf_blocks' ],
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
			'829-tools/get-acf-fields',
			array(
				'category'            => '829-tools',
				'label'               => 'Get ACF Fields',
				'description'         => 'Returns all ACF field values for a post, page, or custom post type. Also accepts "options" to read from the ACF options page.',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id' => array(
							'type'        => array( 'integer', 'string' ),
							'description' => 'Post ID, or "options" for the ACF options page.',
						),
					),
					'required'   => array( 'post_id' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array( 'fields' => array( 'type' => 'object' ) ),
				),
				'permission_callback' => [ $this, 'check_admin_permission' ],
				'execute_callback'    => [ $this, 'get_acf_fields' ],
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
			'829-tools/update-acf-fields',
			array(
				'category'            => '829-tools',
				'label'               => 'Update ACF Fields',
				'description'         => 'Updates one or more ACF fields on a post. Also accepts "options" to write to the ACF options page. Only the provided fields are changed.',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id' => array(
							'type'        => array( 'integer', 'string' ),
							'description' => 'Post ID, or "options" for the ACF options page.',
						),
						'fields'  => array(
							'type'        => 'object',
							'description' => 'Key/value map of field names to their new values.',
						),
					),
					'required'   => array( 'post_id', 'fields' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array( 'updated' => array( 'type' => 'boolean' ) ),
				),
				'permission_callback' => [ $this, 'check_admin_permission' ],
				'execute_callback'    => [ $this, 'update_acf_fields' ],
				'meta'                => array(
					'mcp'         => array( 'public' => true ),
					'annotations' => array(
						'destructive' => true,
						'idempotent'  => true,
					),
				),
			)
		);

		// ── Redirection plugin ───────────────────────────────────────────────

		wp_register_ability(
			'829-tools/search-redirects',
			array(
				'category'            => '829-tools',
				'label'               => 'Search Redirects',
				'description'         => 'Searches redirects managed by the Redirection plugin. Requires the Redirection plugin to be installed and active.',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'search'   => array(
							'type'        => 'string',
							'description' => 'Filter by source URL, target URL, or title.',
						),
						'status'   => array(
							'type'        => 'string',
							'description' => 'Filter by status: "enabled" or "disabled". Omit for all.',
						),
						'per_page' => array(
							'type'        => 'integer',
							'description' => 'Results per page. Default 50, max 200.',
							'default'     => 50,
						),
						'page'     => array(
							'type'        => 'integer',
							'description' => 'Page number. Default 1.',
							'default'     => 1,
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'redirects' => array( 'type' => 'array' ),
						'total'     => array( 'type' => 'integer' ),
						'pages'     => array( 'type' => 'integer' ),
					),
				),
				'permission_callback' => [ $this, 'check_admin_permission' ],
				'execute_callback'    => [ $this, 'search_redirects' ],
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
			'829-tools/get-redirect',
			array(
				'category'            => '829-tools',
				'label'               => 'Get Redirect',
				'description'         => 'Returns a single redirect by ID.',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id' => array(
							'type'        => 'integer',
							'description' => 'Redirect ID.',
						),
					),
					'required'   => array( 'id' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array( 'redirect' => array( 'type' => 'object' ) ),
				),
				'permission_callback' => [ $this, 'check_admin_permission' ],
				'execute_callback'    => [ $this, 'get_redirect' ],
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
			'829-tools/create-redirect',
			array(
				'category'            => '829-tools',
				'label'               => 'Create Redirect',
				'description'         => 'Creates a new redirect in the Redirection plugin.',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'source_url' => array(
							'type'        => 'string',
							'description' => 'Source URL path (e.g. "/old-page").',
						),
						'target_url' => array(
							'type'        => 'string',
							'description' => 'Target URL or path the redirect points to.',
						),
						'code'       => array(
							'type'        => 'integer',
							'description' => 'HTTP status code: 301, 302, 307, 308, 410, 404. Default 301.',
							'default'     => 301,
						),
						'regex'      => array(
							'type'        => 'boolean',
							'description' => 'Whether source_url is a regular expression. Default false.',
							'default'     => false,
						),
						'title'      => array(
							'type'        => 'string',
							'description' => 'Optional label for this redirect.',
						),
						'group_id'   => array(
							'type'        => 'integer',
							'description' => 'Redirection group ID. Default 1.',
							'default'     => 1,
						),
					),
					'required'   => array( 'source_url', 'target_url' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'id'       => array( 'type' => 'integer' ),
						'redirect' => array( 'type' => 'object' ),
					),
				),
				'permission_callback' => [ $this, 'check_admin_permission' ],
				'execute_callback'    => [ $this, 'create_redirect' ],
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
			'829-tools/update-redirect',
			array(
				'category'            => '829-tools',
				'label'               => 'Update Redirect',
				'description'         => 'Updates an existing redirect. Only provided fields are changed.',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id'         => array(
							'type'        => 'integer',
							'description' => 'Redirect ID.',
						),
						'source_url' => array(
							'type'        => 'string',
							'description' => 'New source URL.',
						),
						'target_url' => array(
							'type'        => 'string',
							'description' => 'New target URL.',
						),
						'code'       => array(
							'type'        => 'integer',
							'description' => 'New HTTP status code.',
						),
						'regex'      => array(
							'type'        => 'boolean',
							'description' => 'Whether source_url is a regex.',
						),
						'title'      => array(
							'type'        => 'string',
							'description' => 'New title/label.',
						),
						'status'     => array(
							'type'        => 'string',
							'description' => '"enabled" or "disabled".',
						),
					),
					'required'   => array( 'id' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array( 'updated' => array( 'type' => 'boolean' ) ),
				),
				'permission_callback' => [ $this, 'check_admin_permission' ],
				'execute_callback'    => [ $this, 'update_redirect' ],
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
			'829-tools/delete-redirect',
			array(
				'category'            => '829-tools',
				'label'               => 'Delete Redirect',
				'description'         => 'Permanently deletes a redirect from the Redirection plugin.',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id' => array(
							'type'        => 'integer',
							'description' => 'Redirect ID.',
						),
					),
					'required'   => array( 'id' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array( 'deleted' => array( 'type' => 'boolean' ) ),
				),
				'permission_callback' => [ $this, 'check_admin_permission' ],
				'execute_callback'    => [ $this, 'delete_redirect' ],
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
			'829-tools/list-posts',
			array(
				'category'            => '829-tools',
				'label'               => 'List Posts',
				'description'         => 'Returns posts of any post type. Supports filtering by status, search, taxonomy terms, and pagination.',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_type' => array(
							'type'        => 'string',
							'description' => 'Post type slug (e.g. "post", "page", "event"). Defaults to "post".',
						),
						'status'    => array(
							'type'        => 'string',
							'description' => 'Post status filter. Accepts "publish", "draft", "pending", "private", "trash", or "any". Defaults to "any".',
						),
						'search'    => array(
							'type'        => 'string',
							'description' => 'Keyword search across title and content.',
						),
						'per_page'  => array(
							'type'        => 'integer',
							'description' => 'Number of results per page (1–100). Defaults to 20.',
						),
						'page'      => array(
							'type'        => 'integer',
							'description' => 'Page number (1-based). Defaults to 1.',
						),
						'orderby'   => array(
							'type'        => 'string',
							'description' => 'Sort field. Accepts "date", "modified", "title", "ID", "menu_order". Defaults to "date".',
							'enum'        => array( 'date', 'modified', 'title', 'ID', 'menu_order' ),
						),
						'order'     => array(
							'type'        => 'string',
							'description' => 'Sort direction: "ASC" or "DESC". Defaults to "DESC".',
							'enum'        => array( 'ASC', 'DESC' ),
						),
						'author'    => array(
							'type'        => 'integer',
							'description' => 'Filter by author user ID.',
						),
						'terms'     => array(
							'type'        => 'object',
							'description' => 'Filter by taxonomy terms. Keys are taxonomy slugs, values are arrays of term slugs or IDs. E.g. {"category": ["news"], "post_tag": [1, 2]}.',
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'posts' => array( 'type' => 'array' ),
						'total' => array( 'type' => 'integer' ),
						'pages' => array( 'type' => 'integer' ),
					),
				),
				'permission_callback' => [ $this, 'check_posts_read_permission' ],
				'execute_callback'    => [ $this, 'list_posts' ],
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
			'829-tools/get-post',
			array(
				'category'            => '829-tools',
				'label'               => 'Get Post',
				'description'         => 'Returns full detail for a single post of any post type, including all meta fields and taxonomy terms.',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id' => array(
							'type'        => 'integer',
							'description' => 'Post ID.',
						),
					),
					'required'   => array( 'id' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'post' => array( 'type' => 'object' ),
					),
				),
				'permission_callback' => [ $this, 'check_posts_read_permission' ],
				'execute_callback'    => [ $this, 'get_post_item' ],
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
			'829-tools/create-post',
			array(
				'category'            => '829-tools',
				'label'               => 'Create Post',
				'description'         => 'Creates a new post of any post type. Supports setting title, content, excerpt, status, slug, date, author, parent, menu_order, meta fields, and taxonomy terms.',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_type'  => array(
							'type'        => 'string',
							'description' => 'Post type slug (e.g. "post", "page"). Defaults to "post".',
						),
						'title'      => array(
							'type'        => 'string',
							'description' => 'Post title.',
						),
						'content'    => array(
							'type'        => 'string',
							'description' => 'Post content (HTML or block markup).',
						),
						'excerpt'    => array(
							'type'        => 'string',
							'description' => 'Post excerpt.',
						),
						'status'     => array(
							'type'        => 'string',
							'description' => 'Post status. Accepts "publish", "draft", "pending", "private". Defaults to "draft".',
							'enum'        => array( 'publish', 'draft', 'pending', 'private' ),
						),
						'slug'       => array(
							'type'        => 'string',
							'description' => 'Post slug (URL-friendly name).',
						),
						'date'       => array(
							'type'        => 'string',
							'description' => 'Publish date in YYYY-MM-DD HH:MM:SS format.',
						),
						'author'     => array(
							'type'        => 'integer',
							'description' => 'Author user ID. Defaults to the current user.',
						),
						'parent'     => array(
							'type'        => 'integer',
							'description' => 'Parent post ID (used for pages and hierarchical post types).',
						),
						'menu_order' => array(
							'type'        => 'integer',
							'description' => 'Menu order for ordering posts.',
						),
						'meta'       => array(
							'type'        => 'object',
							'description' => 'Key-value pairs of post meta fields to set.',
						),
						'terms'      => array(
							'type'        => 'object',
							'description' => 'Taxonomy terms to assign. Keys are taxonomy slugs, values are arrays of term slugs or IDs. E.g. {"category": ["news"], "post_tag": ["featured"]}.',
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'post_id' => array( 'type' => 'integer' ),
					),
				),
				'permission_callback' => [ $this, 'check_posts_create_permission' ],
				'execute_callback'    => [ $this, 'create_post_item' ],
				'meta'                => array(
					'mcp'         => array( 'public' => true ),
					'annotations' => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => false,
					),
				),
			)
		);

		wp_register_ability(
			'829-tools/update-post',
			array(
				'category'            => '829-tools',
				'label'               => 'Update Post',
				'description'         => 'Updates an existing post. Only provided fields are changed. Supports updating title, content, excerpt, status, slug, date, author, parent, menu_order, meta fields, and taxonomy terms.',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id'         => array(
							'type'        => 'integer',
							'description' => 'Post ID to update.',
						),
						'title'      => array(
							'type'        => 'string',
							'description' => 'New post title.',
						),
						'content'    => array(
							'type'        => 'string',
							'description' => 'New post content (HTML or block markup).',
						),
						'excerpt'    => array(
							'type'        => 'string',
							'description' => 'New post excerpt.',
						),
						'status'     => array(
							'type'        => 'string',
							'description' => 'New post status.',
							'enum'        => array( 'publish', 'draft', 'pending', 'private', 'trash' ),
						),
						'slug'       => array(
							'type'        => 'string',
							'description' => 'New post slug.',
						),
						'date'       => array(
							'type'        => 'string',
							'description' => 'New publish date in YYYY-MM-DD HH:MM:SS format.',
						),
						'author'     => array(
							'type'        => 'integer',
							'description' => 'New author user ID.',
						),
						'parent'     => array(
							'type'        => 'integer',
							'description' => 'New parent post ID.',
						),
						'menu_order' => array(
							'type'        => 'integer',
							'description' => 'New menu order.',
						),
						'meta'       => array(
							'type'        => 'object',
							'description' => 'Meta fields to update. Only provided keys are changed.',
						),
						'terms'      => array(
							'type'        => 'object',
							'description' => 'Taxonomy terms to set. Keys are taxonomy slugs, values are arrays of term slugs or IDs. Replaces all existing terms for each provided taxonomy.',
						),
					),
					'required'   => array( 'id' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'updated' => array( 'type' => 'boolean' ),
					),
				),
				'permission_callback' => [ $this, 'check_posts_edit_permission' ],
				'execute_callback'    => [ $this, 'update_post_item' ],
				'meta'                => array(
					'mcp'         => array( 'public' => true ),
					'annotations' => array(
						'readonly'    => false,
						'destructive' => true,
						'idempotent'  => true,
					),
				),
			)
		);

		wp_register_ability(
			'829-tools/delete-post',
			array(
				'category'            => '829-tools',
				'label'               => 'Delete Post',
				'description'         => 'Deletes or trashes a post. By default moves the post to trash. Set force to true for permanent deletion.',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id'    => array(
							'type'        => 'integer',
							'description' => 'Post ID to delete.',
						),
						'force' => array(
							'type'        => 'boolean',
							'description' => 'Set to true to permanently delete. Defaults to false (moves to trash).',
						),
					),
					'required'   => array( 'id' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'deleted' => array( 'type' => 'boolean' ),
					),
				),
				'permission_callback' => [ $this, 'check_posts_delete_permission' ],
				'execute_callback'    => [ $this, 'delete_post_item' ],
				'meta'                => array(
					'mcp'         => array( 'public' => true ),
					'annotations' => array(
						'readonly'    => false,
						'destructive' => true,
						'idempotent'  => false,
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
						'post_type' => array(
							'type'        => 'string',
							'description' => 'Limit the response to a single post type slug (e.g. "post", "page", "event"). Omit for all public post types.',
						),
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
	 * Permission callback: site administration tools (plugins, themes, users,
	 * menus, options, media, redirects, ACF, blocks). Requires manage_options
	 * or the MCP site management capability.
	 *
	 * @return true|WP_Error
	 */
	public function check_admin_permission() {
		return $this->check_permission( array( '829_mcp_manage_site' ) );
	}

	/**
	 * Permission callback: read posts (list-posts, get-post). Execute
	 * callbacks scope non-managers to their own posts only.
	 *
	 * @return true|WP_Error
	 */
	public function check_posts_read_permission() {
		return $this->check_permission(
			array(
				'829_mcp_manage_site',
				'829_mcp_create_posts',
				'829_mcp_edit_posts',
				'829_mcp_publish_posts',
				'829_mcp_delete_posts',
			)
		);
	}

	/**
	 * Permission callback: create new posts. A user with only this capability
	 * can create drafts but not publish, edit existing posts, or delete.
	 *
	 * @return true|WP_Error
	 */
	public function check_posts_create_permission() {
		return $this->check_permission( array( '829_mcp_manage_site', '829_mcp_create_posts' ) );
	}

	/**
	 * Permission callback: edit existing posts. Non-managers may only edit
	 * their own posts.
	 *
	 * @return true|WP_Error
	 */
	public function check_posts_edit_permission() {
		return $this->check_permission( array( '829_mcp_manage_site', '829_mcp_edit_posts' ) );
	}

	/**
	 * Permission callback: delete or trash posts.
	 *
	 * @return true|WP_Error
	 */
	public function check_posts_delete_permission() {
		return $this->check_permission( array( '829_mcp_manage_site', '829_mcp_delete_posts' ) );
	}

	/**
	 * Checks that the current user is logged in and holds at least one of the
	 * given capabilities. Administrators (manage_options) always pass.
	 *
	 * @param  string[] $capabilities Capabilities, any one of which grants access.
	 * @return true|WP_Error
	 */
	private function check_permission( array $capabilities ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'not_authenticated', 'Authentication required.' );
		}

		if ( $this->current_user_can_any( $capabilities ) ) {
			return true;
		}

		return new WP_Error( 'insufficient_permission', 'You do not have permission to use this tool.' );
	}

	/**
	 * Whether the current user is an administrator or holds at least one of
	 * the given capabilities.
	 *
	 * @param  string[] $capabilities Capabilities to check.
	 * @return bool
	 */
	private function current_user_can_any( array $capabilities ) {
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		foreach ( $capabilities as $capability ) {
			if ( current_user_can( $capability ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether the current user may publish posts (set status to "publish"
	 * or "future") via MCP.
	 *
	 * @return bool
	 */
	private function current_user_can_publish_posts() {
		return $this->current_user_can_any( array( '829_mcp_manage_site', '829_mcp_publish_posts' ) );
	}

	/**
	 * Whether the current user may delete or trash posts via MCP.
	 *
	 * @return bool
	 */
	private function current_user_can_delete_posts() {
		return $this->current_user_can_any( array( '829_mcp_manage_site', '829_mcp_delete_posts' ) );
	}

	/**
	 * Whether the current user may act on posts belonging to other users.
	 *
	 * @return bool
	 */
	private function current_user_can_manage_any_post() {
		return $this->current_user_can_any( array( '829_mcp_manage_site', '829_mcp_delete_posts' ) );
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
		$locations                   = get_nav_menu_locations();
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
					'menu-item-title'       => $item['title'] ?? '',
					'menu-item-url'         => $item['url'] ?? '',
					'menu-item-type'        => $item['type'] ?? 'custom',
					'menu-item-object'      => $item['object'] ?? '',
					'menu-item-object-id'   => intval( $item['object_id'] ?? 0 ),
					'menu-item-parent-id'   => intval( $item['parent_id'] ?? 0 ),
					'menu-item-position'    => intval( $item['position'] ?? ( $index + 1 ) ),
					'menu-item-target'      => $item['target'] ?? '',
					'menu-item-classes'     => $item['classes'] ?? '',
					'menu-item-description' => $item['description'] ?? '',
					'menu-item-attr-title'  => $item['attr_title'] ?? '',
					'menu-item-status'      => 'publish',
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
			// Auth keys and salts — never expose these.
			'auth_key',
			'secure_auth_key',
			'logged_in_key',
			'nonce_key',
			'auth_salt',
			'secure_auth_salt',
			'logged_in_salt',
			'nonce_salt',

			// Core site URLs — changing these breaks the entire site immediately.
			'siteurl',
			'home',

			// Plugin and theme activation — changes take effect instantly.
			'active_plugins',
			'active_sitewide_plugins',
			'template',
			'stylesheet',
			'current_theme',

			// User roles and capabilities — security critical.
			'wp_user_roles',
			'default_role',

			// URL routing — breaks all page URLs if changed.
			'permalink_structure',
			'rewrite_rules',

			// Database version flags — could trigger unwanted upgrade routines.
			'db_version',
			'wp_db_version',
			'initial_db_version',

			// Upload configuration — breaks all media if changed.
			'upload_path',
			'upload_url_path',

			// Admin email — receives password resets and system alerts.
			'admin_email',

			// Multisite critical.
			'site_admins',
		);
	}

	/**
	 * Option name prefixes that are never readable or writable via MCP.
	 *
	 * @return string[]
	 */
	private function blocked_option_prefixes() {
		return array(
			'theme_mods_', // Per-theme customizer settings.
		);
	}

	/**
	 * Check whether an option name is blocked.
	 *
	 * @param  string $name Option name.
	 * @return bool
	 */
	private function is_blocked_option( $name ) {
		if ( in_array( $name, $this->blocked_option_names(), true ) ) {
			return true;
		}

		foreach ( $this->blocked_option_prefixes() as $prefix ) {
			if ( strncmp( $name, $prefix, strlen( $prefix ) ) === 0 ) {
				return true;
			}
		}

		return false;
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

		$cache_key = 'wpt_mcp_search_options_' . md5( wp_json_encode( array( $where_sql, $params, $per_page, $offset ) ) );
		$result    = wp_cache_get( $cache_key, 'options' );

		if ( false === $result ) {
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- no core API for pattern search; $where_sql is already prepared correctly.
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

			$result = array(
				'options' => $options,
				'total'   => $total,
				'pages'   => $per_page > 0 ? (int) ceil( $total / $per_page ) : 1,
			);

			wp_cache_set( $cache_key, $result, 'options', MINUTE_IN_SECONDS );
		}

		return $result;
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

		$cache_key = 'wpt_mcp_option_autoload_' . $name;
		$autoload  = wp_cache_get( $cache_key, 'options' );

		if ( false === $autoload ) {
			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- no core API for the autoload column.
			$autoload = $wpdb->get_var( $wpdb->prepare( "SELECT autoload FROM {$wpdb->options} WHERE option_name = %s", $name ) );
			wp_cache_set( $cache_key, $autoload, 'options', 5 * MINUTE_IN_SECONDS );
		}

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

		if ( $result ) {
			wp_cache_delete( 'wpt_mcp_option_autoload_' . $name, 'options' );
		}

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

		wp_cache_delete( 'wpt_mcp_option_autoload_' . $name, 'options' );

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

		if ( $result ) {
			wp_cache_delete( 'wpt_mcp_option_autoload_' . $name, 'options' );
		}

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
			'description'     => 'WordPress block content is stored as HTML with special block comment delimiters. Attributes are JSON encoded in the opening comment.',
			'block_format'    => '<!-- wp:block-slug {"attr":"value"} --> optional inner HTML <!-- /wp:block-slug -->',
			'self_closing'    => '<!-- wp:block-slug {"attr":"value"} /-->',
			'core_example'    => '<!-- wp:paragraph --><p>Hello world</p><!-- /wp:paragraph -->',
			'acf_example'     => '<!-- wp:acf/my-block {"name":"acf/my-block","data":{"field_key":"value"},"mode":"preview"} /-->',
			'heading_example' => '<!-- wp:heading {"level":2} --><h2 class="wp-block-heading">My Heading</h2><!-- /wp:heading -->',
			'note'            => 'Use the list-allowed-blocks ability to see all blocks available on this site.',
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
			$entry      = array(
				'slug'           => $slug,
				'title'          => $block_type ? ( $block_type->title ?? $slug ) : $slug,
				'category'       => $block_type ? ( $block_type->category ?? '' ) : '',
				'description'    => $block_type ? ( $block_type->description ?? '' ) : '',
				'keywords'       => $block_type ? ( $block_type->keywords ?? array() ) : array(),
				'allowed_blocks' => $block_type ? ( $block_type->allowed_blocks ?? array() ) : array(),
			);

			/**
			 * Filters the lightweight entry returned for a single block via list-allowed-blocks.
			 *
			 * @param array              $entry      slug, title, category, description, icon, keywords.
			 * @param WP_Block_Type|null $block_type The registered block type, or null if unregistered.
			 */
			$filtered = apply_filters( 'wpt_mcp_block_list_entry', $entry, $block_type );

			$blocks[] = is_array( $filtered ) ? $filtered : $entry;
		}

		usort(
			$blocks,
			function ( $a, $b ) {
				$cat = strcmp( $a['category'], $b['category'] );
				return 0 !== $cat ? $cat : strcmp( $a['slug'], $b['slug'] );
			}
		);

		return array(
			'blocks' => $blocks,
			'total'  => count( $blocks ),
		);
	}

	/**
	 * Execute callback: get full detail for a single registered block type.
	 *
	 * @param  array $input Ability input.
	 * @return array|WP_Error
	 */
	public function get_block_info( $input = array() ) {
		$slug = sanitize_text_field( $input['slug'] ?? '' );

		if ( empty( $slug ) ) {
			return new WP_Error( 'missing_slug', 'slug is required.' );
		}

		$registry   = \WP_Block_Type_Registry::get_instance();
		$block_type = $registry->get_registered( $slug );

		if ( ! $block_type ) {
			return new WP_Error( 'not_found', "Block '{$slug}' is not registered." );
		}

		// Respect the same allowed_block_types_all restrictions as list_allowed_blocks(),
		// so this ability can't be used to read detail on a block a theme has hidden.
		$all_slugs = array_keys( $registry->get_all_registered() );
		$allowed   = apply_filters( 'allowed_block_types_all', $all_slugs, null );
		$allowed   = is_array( $allowed ) ? $allowed : $all_slugs;

		if ( ! in_array( $slug, $allowed, true ) ) {
			return new WP_Error( 'not_found', "Block '{$slug}' is not registered." );
		}

		$block = array(
			'name'                => $block_type->name,
			'title'               => $block_type->title ?? '',
			'description'         => $block_type->description ?? '',
			'category'            => $block_type->category ?? '',
			'icon'                => is_string( $block_type->icon ?? null ) ? $block_type->icon : '',
			'keywords'            => $block_type->keywords ?? array(),
			'allowed_blocks'      => $block_type->allowed_blocks ?? array(),
			'parent'              => $block_type->parent ?? array(),
			'ancestor'            => $block_type->ancestor ?? array(),
			'api_version'         => $block_type->api_version ?? 1,
			'textdomain'          => $block_type->textdomain ?? '',
			'attributes'          => $block_type->attributes ?? array(),
			'supports'            => $block_type->supports ?? array(),
			'styles'              => $block_type->styles ?? array(),
			'variations'          => $block_type->variations ?? array(),
			'example'             => $block_type->example ?? null,
			'provides_context'    => $block_type->provides_context ?? array(),
			'uses_context'        => $block_type->uses_context ?? array(),
			'has_render_callback' => is_callable( $block_type->render_callback ?? null ),
		);

		/**
		 * Filters the detailed data returned for a single block via the get-block-info MCP ability.
		 *
		 * @param array         $block      The serialized block detail.
		 * @param WP_Block_Type $block_type The raw registered block type object.
		 */
		$block = apply_filters( 'wpt_mcp_block_info_data', $block, $block_type );

		return array( 'block' => $block );
	}

	// ── ACF ──────────────────────────────────────────────────────────────────

	/**
	 * Execute callback: list ACF block types with their fields.
	 *
	 * @return array|WP_Error
	 */
	public function list_acf_blocks() {
		if ( ! function_exists( 'acf_get_block_types' ) ) {
			return new WP_Error( 'acf_missing', 'ACF is not active or does not support blocks on this site.' );
		}

		$block_types = \acf_get_block_types();
		$blocks      = array();

		foreach ( $block_types as $block ) {
			$entry = array(
				'name'           => $block['name'],
				'title'          => $block['title'],
				'description'    => $block['description'] ?? '',
				'category'       => $block['category'] ?? '',
				'keywords'       => $block['keywords'] ?? array(),
				'allowed_blocks' => $block['allowed_blocks'] ?? array(),
				'fields'         => array(),
			);

			// Fetch the field group attached to this block.
			if ( function_exists( 'acf_get_field_groups' ) && function_exists( 'acf_get_fields' ) ) {
				$groups = \acf_get_field_groups( array( 'block' => $block['name'] ) );
				foreach ( $groups as $group ) {
					$fields = \acf_get_fields( $group['key'] );
					if ( $fields ) {
						foreach ( $fields as $field ) {
							$field_entry = array(
								'name'     => $field['name'],
								'label'    => $field['label'],
								'type'     => $field['type'],
								'required' => ! empty( $field['required'] ),
							);

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

							$entry['fields'][] = $field_entry;
						}
					}
				}
			}

			/**
			 * Filters the data returned for a single ACF block via the list-acf-blocks MCP ability.
			 *
			 * Allows a theme or plugin to append additional information (e.g. usage notes,
			 * example markup, or custom metadata) to each block entry before it's returned.
			 *
			 * @param array $entry The block entry (name, title, description, category, icon, keywords, fields).
			 * @param array $block The raw ACF block type definition from acf_get_block_types().
			 */
			$entry = apply_filters( 'wpt_mcp_acf_block_data', $entry, $block );

			$blocks[] = $entry;
		}

		usort(
			$blocks,
			function ( $a, $b ) {
				return strcmp( $a['name'], $b['name'] );
			}
		);

		return array( 'blocks' => $blocks );
	}

	/**
	 * Execute callback: get all ACF field values for a post.
	 *
	 * @param  array $input Ability input.
	 * @return array|WP_Error
	 */
	public function get_acf_fields( $input = array() ) {
		if ( ! function_exists( 'get_fields' ) ) {
			return new WP_Error( 'acf_missing', 'ACF is not active.' );
		}

		$post_id = $input['post_id'] ?? null;

		if ( null === $post_id ) {
			return new WP_Error( 'missing_post_id', 'post_id is required.' );
		}

		// Accept "options" string or integer post IDs.
		if ( 'options' !== $post_id ) {
			$post_id = intval( $post_id );

			if ( ! $post_id || ! get_post( $post_id ) ) {
				return new WP_Error( 'not_found', 'Post not found.' );
			}
		}

		$fields = \get_fields( $post_id );

		return array( 'fields' => $fields ?: array() );
	}

	/**
	 * Execute callback: update ACF field values on a post.
	 *
	 * @param  array $input Ability input.
	 * @return array|WP_Error
	 */
	public function update_acf_fields( $input = array() ) {
		if ( ! function_exists( 'update_field' ) ) {
			return new WP_Error( 'acf_missing', 'ACF is not active.' );
		}

		$post_id = $input['post_id'] ?? null;
		$fields  = $input['fields'] ?? array();

		if ( null === $post_id ) {
			return new WP_Error( 'missing_post_id', 'post_id is required.' );
		}

		if ( empty( $fields ) || ! is_array( $fields ) ) {
			return new WP_Error( 'missing_fields', 'fields must be a non-empty object.' );
		}

		if ( 'options' !== $post_id ) {
			$post_id = intval( $post_id );

			if ( ! $post_id || ! get_post( $post_id ) ) {
				return new WP_Error( 'not_found', 'Post not found.' );
			}
		}

		foreach ( $fields as $key => $value ) {
			\update_field( $key, $value, $post_id );
		}

		return array( 'updated' => true );
	}

	// ── Redirection plugin ───────────────────────────────────────────────────

	/**
	 * Return the Redirection items table name, or WP_Error if it doesn't exist.
	 *
	 * @return string|WP_Error
	 */
	private function redirection_table() {
		global $wpdb;
		$table = $wpdb->prefix . 'redirection_items';

		$exists = wp_cache_get( 'wpt_mcp_redirection_table_exists', 'wpt_mcp', false, $found );

		if ( ! $found ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- third-party table, no core API.
			$exists = ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table );
			wp_cache_set( 'wpt_mcp_redirection_table_exists', $exists, 'wpt_mcp', HOUR_IN_SECONDS );
		}

		if ( ! $exists ) {
			return new WP_Error( 'redirection_missing', 'The Redirection plugin is not installed or has not been set up yet.' );
		}

		return $table;
	}

	/**
	 * Format a redirection_items DB row as an array.
	 *
	 * @param  object $row DB row.
	 * @return array
	 */
	private function format_redirect( $row ) {
		return array(
			'id'          => (int) $row->id,
			'source_url'  => $row->url,
			'target_url'  => $row->action_data,
			'code'        => (int) $row->action_code,
			'status'      => $row->status,
			'regex'       => (bool) $row->regex,
			'group_id'    => (int) $row->group_id,
			'title'       => $row->title,
			'hits'        => (int) $row->hits,
			'last_access' => $row->last_access,
		);
	}

	/**
	 * Bust the Redirection plugin's redirect cache after a write operation, and our own
	 * per-redirect cache entry if an $id is given.
	 *
	 * @param int|null $id Redirect ID whose cached get_redirect() entry should be cleared.
	 */
	private function flush_redirection_cache( $id = null ) {
		// Use Red_Item::flush() if available (plugin is active).
		if ( class_exists( 'Red_Item' ) && method_exists( 'Red_Item', 'flush' ) ) {
			\Red_Item::flush();
		} else {
			// Fallback: delete transients with the red_ prefix.
			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- write, nothing to cache.
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
					'\_transient\_red\_%',
					'\_transient\_timeout\_red\_%'
				)
			);
		}

		if ( null !== $id ) {
			wp_cache_delete( 'wpt_mcp_redirect_' . $id, 'wpt_mcp' );
		}
	}

	/**
	 * Execute callback: search redirects.
	 *
	 * @param  array $input Ability input.
	 * @return array|WP_Error
	 */
	public function search_redirects( $input = array() ) {
		global $wpdb;

		$table = $this->redirection_table();
		if ( is_wp_error( $table ) ) {
			return $table;
		}

		$per_page = min( intval( $input['per_page'] ?? 50 ), 200 );
		$page     = max( intval( $input['page'] ?? 1 ), 1 );
		$offset   = ( $page - 1 ) * $per_page;

		$where  = array( '1=1' );
		$params = array();

		if ( ! empty( $input['search'] ) ) {
			$like     = '%' . $wpdb->esc_like( $input['search'] ) . '%';
			$where[]  = '( url LIKE %s OR action_data LIKE %s OR title LIKE %s )';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		if ( ! empty( $input['status'] ) && in_array( $input['status'], array( 'enabled', 'disabled' ), true ) ) {
			$where[]  = 'status = %s';
			$params[] = $input['status'];
		}

		$where_sql = implode( ' AND ', $where );

		$cache_key = 'wpt_mcp_search_redirects_' . md5( wp_json_encode( array( $where_sql, $params, $per_page, $offset ) ) );
		$result    = wp_cache_get( $cache_key, 'wpt_mcp' );

		if ( false === $result ) {
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- third-party table, no core API; $where_sql is already prepared correctly.
			if ( $params ) {
				$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}", $params ) );
				$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY id DESC LIMIT %d OFFSET %d", array_merge( $params, array( $per_page, $offset ) ) ) );
			} else {
				$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
				$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d OFFSET %d", array( $per_page, $offset ) ) );
			}
			// phpcs:enable

			$result = array(
				'redirects' => array_map( array( $this, 'format_redirect' ), $rows ),
				'total'     => $total,
				'pages'     => $per_page > 0 ? (int) ceil( $total / $per_page ) : 1,
			);

			wp_cache_set( $cache_key, $result, 'wpt_mcp', MINUTE_IN_SECONDS );
		}

		return $result;
	}

	/**
	 * Execute callback: get a single redirect.
	 *
	 * @param  array $input Ability input.
	 * @return array|WP_Error
	 */
	public function get_redirect( $input = array() ) {
		global $wpdb;

		$table = $this->redirection_table();
		if ( is_wp_error( $table ) ) {
			return $table;
		}

		$id        = intval( $input['id'] ?? 0 );
		$cache_key = 'wpt_mcp_redirect_' . $id;
		$row       = wp_cache_get( $cache_key, 'wpt_mcp' );

		if ( false === $row ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- third-party table, no core API.
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );

			if ( $row ) {
				wp_cache_set( $cache_key, $row, 'wpt_mcp', 5 * MINUTE_IN_SECONDS );
			}
		}

		if ( ! $row ) {
			return new WP_Error( 'not_found', "Redirect {$id} not found." );
		}

		return array( 'redirect' => $this->format_redirect( $row ) );
	}

	/**
	 * Execute callback: create a redirect.
	 *
	 * @param  array $input Ability input.
	 * @return array|WP_Error
	 */
	public function create_redirect( $input = array() ) {
		global $wpdb;

		$table = $this->redirection_table();
		if ( is_wp_error( $table ) ) {
			return $table;
		}

		$source = trim( $input['source_url'] ?? '' );
		$target = trim( $input['target_url'] ?? '' );

		if ( empty( $source ) || empty( $target ) ) {
			return new WP_Error( 'missing_urls', 'source_url and target_url are required.' );
		}

		$code     = intval( $input['code'] ?? 301 );
		$regex    = ! empty( $input['regex'] ) ? 1 : 0;
		$group_id = intval( $input['group_id'] ?? 1 );
		$title    = $input['title'] ?? '';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- third-party table, no core API.
		$result = $wpdb->insert(
			$table,
			array(
				'url'         => $source,
				'action_code' => $code,
				'action_type' => 'url',
				'action_data' => $target,
				'match_type'  => 'url',
				'status'      => 'enabled',
				'regex'       => $regex,
				'group_id'    => $group_id,
				'position'    => 0,
				'hits'        => 0,
				'title'       => $title,
				'updated'     => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%s' )
		);

		if ( ! $result ) {
			return new WP_Error( 'insert_failed', 'Failed to create redirect.' );
		}

		$new_id = $wpdb->insert_id;
		$this->flush_redirection_cache( $new_id );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- just inserted, nothing to cache yet.
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $new_id ) );

		wp_cache_set( 'wpt_mcp_redirect_' . $new_id, $row, 'wpt_mcp', 5 * MINUTE_IN_SECONDS );

		return array(
			'id'       => $new_id,
			'redirect' => $this->format_redirect( $row ),
		);
	}

	/**
	 * Execute callback: update a redirect.
	 *
	 * @param  array $input Ability input.
	 * @return array|WP_Error
	 */
	public function update_redirect( $input = array() ) {
		global $wpdb;

		$table = $this->redirection_table();
		if ( is_wp_error( $table ) ) {
			return $table;
		}

		$id        = intval( $input['id'] ?? 0 );
		$cache_key = 'wpt_mcp_redirect_' . $id;
		$existing  = wp_cache_get( $cache_key, 'wpt_mcp' );

		if ( false === $existing ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- third-party table, no core API.
			$existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );

			if ( $existing ) {
				wp_cache_set( $cache_key, $existing, 'wpt_mcp', 5 * MINUTE_IN_SECONDS );
			}
		}

		if ( ! $existing ) {
			return new WP_Error( 'not_found', "Redirect {$id} not found." );
		}

		$data    = array();
		$formats = array();

		if ( isset( $input['source_url'] ) ) {
			$data['url'] = trim( $input['source_url'] );
			$formats[]   = '%s';
		}

		if ( isset( $input['target_url'] ) ) {
			$data['action_data'] = trim( $input['target_url'] );
			$formats[]           = '%s';
		}

		if ( isset( $input['code'] ) ) {
			$data['action_code'] = intval( $input['code'] );
			$formats[]           = '%d';
		}

		if ( isset( $input['regex'] ) ) {
			$data['regex'] = ! empty( $input['regex'] ) ? 1 : 0;
			$formats[]     = '%d';
		}

		if ( isset( $input['title'] ) ) {
			$data['title'] = $input['title'];
			$formats[]     = '%s';
		}

		if ( isset( $input['status'] ) && in_array( $input['status'], array( 'enabled', 'disabled' ), true ) ) {
			$data['status'] = $input['status'];
			$formats[]      = '%s';
		}

		if ( empty( $data ) ) {
			return array( 'updated' => false );
		}

		$data['updated'] = current_time( 'mysql' );
		$formats[]       = '%s';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- write, nothing to cache.
		$wpdb->update( $table, $data, array( 'id' => $id ), $formats, array( '%d' ) );
		$this->flush_redirection_cache( $id );

		return array( 'updated' => true );
	}

	/**
	 * Execute callback: delete a redirect.
	 *
	 * @param  array $input Ability input.
	 * @return array|WP_Error
	 */
	public function delete_redirect( $input = array() ) {
		global $wpdb;

		$table = $this->redirection_table();
		if ( is_wp_error( $table ) ) {
			return $table;
		}

		$id        = intval( $input['id'] ?? 0 );
		$cache_key = 'wpt_mcp_redirect_' . $id;
		$existing  = wp_cache_get( $cache_key, 'wpt_mcp' );

		if ( false === $existing ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- id-only row, about to be deleted; don't cache it.
			$existing = $wpdb->get_row( $wpdb->prepare( "SELECT id FROM {$table} WHERE id = %d", $id ) );
		}

		if ( ! $existing ) {
			return new WP_Error( 'not_found', "Redirect {$id} not found." );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- write, nothing to cache.
		$result = $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );
		$this->flush_redirection_cache( $id );

		return array( 'deleted' => (bool) $result );
	}

	/**
	 * Execute callback: list posts.
	 *
	 * @param  array $input Ability input.
	 * @return array
	 */
	public function list_posts( $input = array() ) {
		$post_type = ! empty( $input['post_type'] ) ? sanitize_key( $input['post_type'] ) : 'post';
		$status    = ! empty( $input['status'] ) ? $input['status'] : 'any';
		$per_page  = min( max( intval( $input['per_page'] ?? 20 ), 1 ), 100 );
		$page      = max( intval( $input['page'] ?? 1 ), 1 );
		$orderby   = $input['orderby'] ?? 'date';
		$order     = strtoupper( $input['order'] ?? 'DESC' );

		$valid_orderby = array( 'date', 'modified', 'title', 'ID', 'menu_order' );
		if ( ! in_array( $orderby, $valid_orderby, true ) ) {
			$orderby = 'date';
		}

		if ( ! in_array( $order, array( 'ASC', 'DESC' ), true ) ) {
			$order = 'DESC';
		}

		$args = array(
			'post_type'      => $post_type,
			'post_status'    => $status,
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => $orderby,
			'order'          => $order,
			'no_found_rows'  => false,
		);

		if ( ! empty( $input['search'] ) ) {
			$args['s'] = sanitize_text_field( $input['search'] );
		}

		if ( ! empty( $input['author'] ) ) {
			$args['author'] = intval( $input['author'] );
		}

		// Non-managers may only list their own posts.
		if ( ! $this->current_user_can_manage_any_post() ) {
			$args['author'] = get_current_user_id();
		}

		if ( ! empty( $input['terms'] ) && is_array( $input['terms'] ) ) {
			$tax_query = array( 'relation' => 'AND' );
			foreach ( $input['terms'] as $taxonomy => $term_values ) {
				$taxonomy = sanitize_key( $taxonomy );
				if ( ! taxonomy_exists( $taxonomy ) ) {
					continue;
				}
				$term_ids = $this->resolve_term_ids( $taxonomy, (array) $term_values );
				if ( ! empty( $term_ids ) ) {
					$tax_query[] = array(
						'taxonomy' => $taxonomy,
						'field'    => 'term_id',
						'terms'    => $term_ids,
					);
				}
			}
			if ( count( $tax_query ) > 1 ) {
				$args['tax_query'] = $tax_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			}
		}

		$query = new \WP_Query( $args );

		$posts = array();
		foreach ( $query->posts as $post ) {
			$posts[] = $this->format_post_summary( $post );
		}

		return array(
			'posts' => $posts,
			'total' => (int) $query->found_posts,
			'pages' => (int) $query->max_num_pages,
		);
	}

	/**
	 * Execute callback: get a single post.
	 *
	 * @param  array $input Ability input.
	 * @return array|WP_Error
	 */
	public function get_post_item( $input = array() ) {
		$id   = intval( $input['id'] ?? 0 );
		$post = get_post( $id );

		if ( ! $post ) {
			return new WP_Error( 'not_found', "Post {$id} not found." );
		}

		if ( ! $this->current_user_can_manage_any_post() && get_current_user_id() !== (int) $post->post_author ) {
			return new WP_Error( 'insufficient_permission', 'You do not have permission to view this post.' );
		}

		return array( 'post' => $this->format_post_detail( $post ) );
	}

	/**
	 * Execute callback: create a post.
	 *
	 * @param  array $input Ability input.
	 * @return array|WP_Error
	 */
	public function create_post_item( $input = array() ) {
		$status = ! empty( $input['status'] ) ? $input['status'] : 'draft';

		if ( in_array( $status, array( 'publish', 'future' ), true ) && ! $this->current_user_can_publish_posts() ) {
			return new WP_Error( 'insufficient_permission', 'You do not have permission to publish posts.' );
		}

		$postarr = array(
			'post_type'   => ! empty( $input['post_type'] ) ? sanitize_key( $input['post_type'] ) : 'post',
			'post_status' => $status,
		);

		if ( isset( $input['title'] ) ) {
			$postarr['post_title'] = $input['title'];
		}

		if ( isset( $input['content'] ) ) {
			$postarr['post_content'] = $input['content'];
		}

		if ( isset( $input['excerpt'] ) ) {
			$postarr['post_excerpt'] = $input['excerpt'];
		}

		if ( ! empty( $input['slug'] ) ) {
			$postarr['post_name'] = sanitize_title( $input['slug'] );
		}

		if ( ! empty( $input['date'] ) ) {
			$postarr['post_date'] = $input['date'];
		}

		if ( ! empty( $input['author'] ) ) {
			$postarr['post_author'] = intval( $input['author'] );
		}

		if ( isset( $input['parent'] ) ) {
			$postarr['post_parent'] = intval( $input['parent'] );
		}

		if ( isset( $input['menu_order'] ) ) {
			$postarr['menu_order'] = intval( $input['menu_order'] );
		}

		$post_id = wp_insert_post( $postarr, true );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		if ( ! empty( $input['meta'] ) && is_array( $input['meta'] ) ) {
			foreach ( $input['meta'] as $key => $value ) {
				update_post_meta( $post_id, sanitize_key( $key ), $value );
			}
		}

		if ( ! empty( $input['terms'] ) && is_array( $input['terms'] ) ) {
			$this->set_post_terms( $post_id, $input['terms'] );
		}

		return array( 'post_id' => $post_id );
	}

	/**
	 * Execute callback: update a post.
	 *
	 * @param  array $input Ability input.
	 * @return array|WP_Error
	 */
	public function update_post_item( $input = array() ) {
		$id   = intval( $input['id'] ?? 0 );
		$post = get_post( $id );

		if ( ! $post ) {
			return new WP_Error( 'not_found', "Post {$id} not found." );
		}

		if ( ! $this->current_user_can_manage_any_post() && get_current_user_id() !== (int) $post->post_author ) {
			return new WP_Error( 'insufficient_permission', 'You do not have permission to edit this post.' );
		}

		$postarr = array( 'ID' => $id );

		if ( isset( $input['title'] ) ) {
			$postarr['post_title'] = $input['title'];
		}

		if ( isset( $input['content'] ) ) {
			$postarr['post_content'] = $input['content'];
		}

		if ( isset( $input['excerpt'] ) ) {
			$postarr['post_excerpt'] = $input['excerpt'];
		}

		if ( isset( $input['status'] ) ) {
			if ( in_array( $input['status'], array( 'publish', 'future' ), true ) && ! $this->current_user_can_publish_posts() ) {
				return new WP_Error( 'insufficient_permission', 'You do not have permission to publish posts.' );
			}

			if ( 'trash' === $input['status'] && ! $this->current_user_can_delete_posts() ) {
				return new WP_Error( 'insufficient_permission', 'You do not have permission to delete posts.' );
			}

			$postarr['post_status'] = $input['status'];
		}

		if ( isset( $input['slug'] ) ) {
			$postarr['post_name'] = sanitize_title( $input['slug'] );
		}

		if ( isset( $input['date'] ) ) {
			$postarr['post_date'] = $input['date'];
		}

		if ( isset( $input['author'] ) ) {
			$postarr['post_author'] = intval( $input['author'] );
		}

		if ( isset( $input['parent'] ) ) {
			$postarr['post_parent'] = intval( $input['parent'] );
		}

		if ( isset( $input['menu_order'] ) ) {
			$postarr['menu_order'] = intval( $input['menu_order'] );
		}

		if ( count( $postarr ) > 1 ) {
			$result = wp_update_post( $postarr, true );

			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		if ( isset( $input['meta'] ) && is_array( $input['meta'] ) ) {
			foreach ( $input['meta'] as $key => $value ) {
				update_post_meta( $id, sanitize_key( $key ), $value );
			}
		}

		if ( isset( $input['terms'] ) && is_array( $input['terms'] ) ) {
			$this->set_post_terms( $id, $input['terms'] );
		}

		return array( 'updated' => true );
	}

	/**
	 * Execute callback: delete a post.
	 *
	 * @param  array $input Ability input.
	 * @return array|WP_Error
	 */
	public function delete_post_item( $input = array() ) {
		$id    = intval( $input['id'] ?? 0 );
		$force = ! empty( $input['force'] );
		$post  = get_post( $id );

		if ( ! $post ) {
			return new WP_Error( 'not_found', "Post {$id} not found." );
		}

		$result = wp_delete_post( $id, $force );

		if ( ! $result ) {
			return new WP_Error( 'delete_failed', "Failed to delete post {$id}." );
		}

		return array( 'deleted' => true );
	}

	/**
	 * Format a post for summary listing.
	 *
	 * @param  \WP_Post $post Post object.
	 * @return array
	 */
	private function format_post_summary( $post ) {
		return array(
			'id'         => $post->ID,
			'post_type'  => $post->post_type,
			'status'     => $post->post_status,
			'title'      => $post->post_title,
			'slug'       => $post->post_name,
			'date'       => $post->post_date,
			'modified'   => $post->post_modified,
			'author'     => (int) $post->post_author,
			'parent'     => (int) $post->post_parent,
			'menu_order' => (int) $post->menu_order,
			'permalink'  => get_permalink( $post->ID ),
		);
	}

	/**
	 * Format a post with full detail including meta and terms.
	 *
	 * @param  \WP_Post $post Post object.
	 * @return array
	 */
	private function format_post_detail( $post ) {
		$data            = $this->format_post_summary( $post );
		$data['excerpt'] = $post->post_excerpt;
		$data['content'] = $post->post_content;

		// Meta.
		$raw_meta = get_post_meta( $post->ID );
		$meta     = array();
		foreach ( $raw_meta as $key => $values ) {
			$meta[ $key ] = count( $values ) === 1 ? maybe_unserialize( $values[0] ) : array_map( 'maybe_unserialize', $values );
		}
		$data['meta'] = $meta;

		// Taxonomy terms.
		$taxonomies = get_object_taxonomies( $post->post_type );
		$terms      = array();
		foreach ( $taxonomies as $taxonomy ) {
			$post_terms = wp_get_post_terms( $post->ID, $taxonomy );
			if ( ! is_wp_error( $post_terms ) ) {
				$terms[ $taxonomy ] = array_map(
					function ( $term ) {
						return array(
							'id'   => $term->term_id,
							'slug' => $term->slug,
							'name' => $term->name,
						);
					},
					$post_terms
				);
			}
		}
		$data['terms'] = $terms;

		return $data;
	}

	/**
	 * Set taxonomy terms on a post.
	 *
	 * @param  int   $post_id Post ID.
	 * @param  array $terms   Taxonomy => term slugs/IDs map.
	 */
	private function set_post_terms( $post_id, $terms ) {
		foreach ( $terms as $taxonomy => $term_values ) {
			$taxonomy = sanitize_key( $taxonomy );
			if ( ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}
			$term_ids = $this->resolve_term_ids( $taxonomy, (array) $term_values );
			wp_set_post_terms( $post_id, $term_ids, $taxonomy );
		}
	}

	/**
	 * Resolve an array of term slugs and/or IDs to term IDs.
	 *
	 * @param  string $taxonomy    Taxonomy slug.
	 * @param  array  $term_values Mix of term slugs and IDs.
	 * @return int[]
	 */
	private function resolve_term_ids( $taxonomy, $term_values ) {
		$ids = array();
		foreach ( $term_values as $value ) {
			if ( is_numeric( $value ) ) {
				$ids[] = intval( $value );
			} else {
				$term = get_term_by( 'slug', $value, $taxonomy );
				if ( $term ) {
					$ids[] = $term->term_id;
				}
			}
		}
		return $ids;
	}
}

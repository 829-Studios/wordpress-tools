<?php
/**
 * Role Management
 *
 * @package  WordPressTools
 */

namespace WordPressTools\RoleManagement;

use WordPressTools\Singleton;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Grants additional capabilities to the Editor role:
 * - All Yoast SEO settings and capabilities
 * - Full create/edit/delete access to all custom post types (except
 *   post types locked to core admin-only capabilities)
 */
class RoleManagement {

	use Singleton;

	/**
	 * Yoast SEO capabilities to grant editors.
	 *
	 * @var string[]
	 */
	private const YOAST_CAPS = [
		'wpseo_manage_options',
		'wpseo_bulk_edit',
		'wpseo_edit_advanced_metadata',
	];

	/**
	 * CPT capability keys that cover create/edit/delete.
	 *
	 * @var string[]
	 */
	private const CPT_CAP_KEYS = [
		'create_posts',
		'publish_posts',
		'edit_posts',
		'edit_others_posts',
		'edit_published_posts',
		'edit_private_posts',
		'delete_posts',
		'delete_others_posts',
		'delete_published_posts',
		'delete_private_posts',
		'read_private_posts',
	];

	/**
	 * Core admin-only capabilities never granted through a post type - plugins
	 * like ACF use manage_options to keep their internal post types admin-only.
	 *
	 * @var string[]
	 */
	private const ADMIN_ONLY_CAPS = [
		'manage_options',
		'activate_plugins',
		'install_plugins',
		'update_plugins',
		'delete_plugins',
		'edit_plugins',
		'switch_themes',
		'install_themes',
		'update_themes',
		'delete_themes',
		'edit_themes',
		'edit_theme_options',
		'customize',
		'edit_files',
		'update_core',
		'import',
		'export',
		'edit_dashboard',
		'list_users',
		'create_users',
		'add_users',
		'edit_users',
		'promote_users',
		'remove_users',
		'delete_users',
		'unfiltered_upload',
		'manage_network',
		'manage_sites',
		'manage_network_users',
		'manage_network_plugins',
		'manage_network_themes',
		'manage_network_options',
		'upgrade_network',
		'setup_network',
	];

	/**
	 * Setup module.
	 */
	public function setup() {
		add_filter( 'user_has_cap', [ $this, 'grant_editor_caps' ], 10, 4 );
	}

	/**
	 * Dynamically grant extra capabilities to Editor users.
	 *
	 * @param bool[]   $allcaps All caps currently assigned to the user.
	 * @param string[] $caps    Required primitive capabilities for the check.
	 * @param array    $args    Context: [0] => cap requested, [1] => user ID, ...
	 * @param \WP_User $user    The user object.
	 * @return bool[]
	 */
	public function grant_editor_caps( $allcaps, $caps, $args, $user ) {
		if ( ! in_array( 'editor', (array) $user->roles, true ) ) {
			return $allcaps;
		}

		foreach ( self::YOAST_CAPS as $cap ) {
			$allcaps[ $cap ] = true;
		}

		foreach ( $this->get_custom_post_type_caps() as $cap ) {
			$allcaps[ $cap ] = true;
		}

		return $allcaps;
	}

	/**
	 * Collect all capability strings across registered non-builtin post types.
	 *
	 * @return string[]
	 */
	private function get_custom_post_type_caps() {
		$caps       = [];
		$post_types = get_post_types( [ '_builtin' => false ], 'objects' );

		foreach ( $post_types as $post_type ) {
			$type_caps = (array) $post_type->cap;

			foreach ( self::CPT_CAP_KEYS as $key ) {
				if ( ! empty( $type_caps[ $key ] ) ) {
					$caps[] = $type_caps[ $key ];
				}
			}
		}

		return array_values( array_diff( array_unique( $caps ), self::ADMIN_ONLY_CAPS ) );
	}
}

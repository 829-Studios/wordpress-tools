<?php
/**
 * Role Management
 *
 * @package  WordPressTools
 */

namespace WordPressTools\RoleManagement;

use WordPressTools\Singleton;
use WordPressTools\Settings\Settings;
use function WordPressTools\Utils\is_829_admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Grants additional capabilities to the Editor role:
 * - All Yoast SEO settings and capabilities
 * - Full create/edit/delete access to all custom post types
 *
 * Optionally stops non-829 admins from changing the role of an 829 admin.
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
	 * Setup module.
	 */
	public function setup() {
		add_filter( 'user_has_cap', [ $this, 'grant_editor_caps' ], 10, 4 );

		// map_meta_cap also applies to super admins on multisite.
		add_filter( 'map_meta_cap', [ $this, 'protect_829_admin_roles' ], 10, 4 );
	}

	/**
	 * Block non-829 admins from changing the role of an 829 admin.
	 *
	 * @param string[] $caps    Required primitive capabilities.
	 * @param string   $cap     Capability being checked.
	 * @param int      $user_id User performing the action.
	 * @param array    $args    Context: [0] => target user ID.
	 * @return string[]
	 */
	public function protect_829_admin_roles( $caps, $cap, $user_id, $args ) {
		// remove_user strips the user's role on a multisite site.
		if ( ! in_array( $cap, [ 'promote_user', 'remove_user' ], true ) || empty( $args[0] ) ) {
			return $caps;
		}

		$settings = Settings::get_settings();

		if ( ! $settings['protect_829_admin_roles'] ) {
			return $caps;
		}

		if ( ! is_829_admin( (int) $args[0] ) || is_829_admin( $user_id ) ) {
			return $caps;
		}

		return [ 'do_not_allow' ];
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

		return array_unique( $caps );
	}
}

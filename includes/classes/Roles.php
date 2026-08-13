<?php
/**
 * Custom roles and capabilities used by this plugin.
 *
 * @package  WordPressTools
 */

namespace WordPressTools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Roles class
 */
class Roles {

	/**
	 * Option storing the version of the role definitions that were last
	 * written to the database. Bump self::VERSION to force a refresh.
	 *
	 * WordPress stores roles per site, so this is intentionally a site option
	 * rather than a network option — each site in a multisite install needs
	 * its own roles written.
	 *
	 * @var string
	 */
	private const VERSION_OPTION = 'wpt_roles_version';

	/**
	 * Current role definition version.
	 *
	 * @var string
	 */
	private const VERSION = '1';

	/**
	 * Role definitions, keyed by role slug.
	 *
	 * @return array<string, array{name: string, capabilities: string[]}>
	 */
	public static function get_role_definitions() {
		return array(
			'829_mcp_contributor' => array(
				'name'         => 'MCP Contributor',
				'capabilities' => array(
					'829_mcp_create_posts',
				),
			),
			'829_mcp_author'      => array(
				'name'         => 'MCP Author',
				'capabilities' => array(
					'829_mcp_create_posts',
					'829_mcp_edit_posts',
					'829_mcp_publish_posts',
				),
			),
			'829_mcp_editor'      => array(
				'name'         => 'MCP Editor',
				'capabilities' => array(
					'829_mcp_create_posts',
					'829_mcp_edit_posts',
					'829_mcp_publish_posts',
					'829_mcp_delete_posts',
				),
			),
			'829_mcp_manager'     => array(
				'name'         => 'MCP Site Manager',
				'capabilities' => array(
					'829_mcp_manage_site',
					'829_mcp_create_posts',
					'829_mcp_edit_posts',
					'829_mcp_publish_posts',
					'829_mcp_delete_posts',
				),
			),
		);
	}

	/**
	 * Register the custom roles if they are missing or out of date.
	 *
	 * Safe to call on every request; it only touches the database when the
	 * stored version doesn't match the current definitions.
	 */
	public static function maybe_register_roles() {
		if ( self::VERSION === get_option( self::VERSION_OPTION ) ) {
			return;
		}

		self::register_roles();

		update_option( self::VERSION_OPTION, self::VERSION );
	}

	/**
	 * Create or refresh the custom roles.
	 *
	 * Existing roles are updated in place rather than removed and recreated,
	 * so users already assigned to them keep their role.
	 */
	public static function register_roles() {
		foreach ( self::get_role_definitions() as $slug => $definition ) {
			// Every role needs `read` so the user can authenticate and load
			// the profile screen.
			$capabilities = array( 'read' => true );

			foreach ( $definition['capabilities'] as $capability ) {
				$capabilities[ $capability ] = true;
			}

			$role = get_role( $slug );

			if ( ! $role ) {
				add_role( $slug, $definition['name'], $capabilities );
				continue;
			}

			// Add anything missing and strip capabilities no longer in the definition.
			foreach ( array_keys( $capabilities ) as $capability ) {
				if ( ! $role->has_cap( $capability ) ) {
					$role->add_cap( $capability );
				}
			}

			foreach ( self::get_all_capabilities() as $capability ) {
				if ( ! isset( $capabilities[ $capability ] ) && $role->has_cap( $capability ) ) {
					$role->remove_cap( $capability );
				}
			}
		}
	}

	/**
	 * Remove the custom roles. Intended for uninstall.
	 */
	public static function remove_roles() {
		foreach ( array_keys( self::get_role_definitions() ) as $slug ) {
			remove_role( $slug );
		}

		delete_option( self::VERSION_OPTION );
	}

	/**
	 * All capabilities defined by this plugin, collected from the role definitions.
	 *
	 * @return string[]
	 */
	public static function get_all_capabilities() {
		$capabilities = array();

		foreach ( self::get_role_definitions() as $definition ) {
			$capabilities = array_merge( $capabilities, $definition['capabilities'] );
		}

		return array_values( array_unique( $capabilities ) );
	}
}

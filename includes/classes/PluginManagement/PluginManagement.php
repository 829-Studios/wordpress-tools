<?php
/**
 * Plugin/Theme Management Restrictions
 *
 * @package  WordPressTools
 */

namespace WordPressTools\PluginManagement;

use WordPressTools\Singleton;
use WordPressTools\Settings\Settings;
use function WordPressTools\Utils\is_829_admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Plugin Management class
 *
 * Restricts plugin and theme management to 829 administrators only.
 */
class PluginManagement {

	use Singleton;

	/**
	 * Capabilities to restrict for plugin management.
	 *
	 * @var array
	 */
	protected $plugin_caps = [
		'install_plugins',
		'activate_plugins',
		'delete_plugins',
		'update_plugins',
		'edit_plugins',
		'upload_plugins',
		'activate_plugin',
		'deactivate_plugin',
		'delete_plugin',
		'update_plugin',
		'manage_network_plugins',
	];

	/**
	 * Capabilities to restrict for theme management.
	 *
	 * @var array
	 */
	protected $theme_caps = [
		'install_themes',
		'switch_themes',
		'delete_themes',
		'update_themes',
		'edit_themes',
		'upload_themes',
		'delete_theme',
		'update_theme',
		'manage_network_themes',
		'enable_theme',
		'disable_theme',
	];

	/**
	 * Setup module
	 */
	public function setup() {
		// Use map_meta_cap which works for super admins in multisite
		add_filter( 'map_meta_cap', [ $this, 'restrict_plugin_caps' ], 10, 4 );

		// Filter action links in network admin
		add_filter( 'theme_action_links', [ $this, 'filter_theme_action_links' ], 10, 4 );
		add_filter( 'network_admin_plugin_action_links', [ $this, 'filter_plugin_action_links' ], 10, 4 );
		add_filter( 'plugin_action_links', [ $this, 'filter_plugin_action_links' ], 10, 4 );
	}

	/**
	 * Check if plugin management restrictions should be applied.
	 *
	 * @param int|null $user_id Optional user ID; defaults to current user.
	 * @return bool
	 */
	protected function should_restrict_plugins( $user_id = null ) {
		$settings = Settings::get_settings();

		if ( ! $settings['restrict_plugin_management'] ) {
			return false;
		}

		if ( is_829_admin( $user_id ) ) {
			return false;
		}

		$whitelist = ! empty( $settings['plugin_management_whitelist'] ) ? array_map( 'intval', (array) $settings['plugin_management_whitelist'] ) : [];
		if ( ! empty( $whitelist ) ) {
			$uid = $user_id ? (int) $user_id : get_current_user_id();
			if ( in_array( $uid, $whitelist, true ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Check if theme management restrictions should be applied.
	 *
	 * @param int|null $user_id Optional user ID; defaults to current user.
	 * @return bool
	 */
	protected function should_restrict_themes( $user_id = null ) {
		$settings = Settings::get_settings();

		if ( ! $settings['restrict_theme_management'] ) {
			return false;
		}

		if ( is_829_admin( $user_id ) ) {
			return false;
		}

		$whitelist = ! empty( $settings['theme_management_whitelist'] ) ? array_map( 'intval', (array) $settings['theme_management_whitelist'] ) : [];
		if ( ! empty( $whitelist ) ) {
			$uid = $user_id ? (int) $user_id : get_current_user_id();
			if ( in_array( $uid, $whitelist, true ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Filter theme action links to remove actions for non-829 admins.
	 *
	 * @param array  $actions Action links.
	 * @param string $theme   Theme slug.
	 * @param string $context Context (network or site).
	 * @param string $status  Theme status.
	 * @return array
	 */
	public function filter_theme_action_links( $actions, $theme, $context = '', $status = '' ) {
		if ( ! $this->should_restrict_themes() ) {
			return $actions;
		}

		// Remove all theme management actions
		$restricted_actions = [ 'enable', 'disable', 'delete', 'activate', 'update' ];
		foreach ( $restricted_actions as $action ) {
			unset( $actions[ $action ] );
		}

		return $actions;
	}

	/**
	 * Filter plugin action links to remove actions for non-829 admins.
	 *
	 * @param array  $actions     Action links.
	 * @param string $plugin_file Plugin file.
	 * @param array  $plugin_data Plugin data.
	 * @param string $context     Context.
	 * @return array
	 */
	public function filter_plugin_action_links( $actions, $plugin_file, $plugin_data = [], $context = '' ) {
		if ( ! $this->should_restrict_plugins() ) {
			return $actions;
		}

		// Remove all plugin management actions
		$restricted_actions = [ 'activate', 'deactivate', 'delete', 'network_activate', 'network_deactivate' ];
		foreach ( $restricted_actions as $action ) {
			unset( $actions[ $action ] );
		}

		return $actions;
	}

	/**
	 * Restrict plugin/theme management capabilities.
	 *
	 * @param array  $caps    Required primitive capabilities.
	 * @param string $cap     Capability being checked.
	 * @param int    $user_id User ID.
	 * @param array  $args    Additional arguments.
	 * @return array
	 */
	public function restrict_plugin_caps( $caps, $cap, $user_id, $args ) {
		$is_plugin_cap = in_array( $cap, $this->plugin_caps, true );
		$is_theme_cap  = ! $is_plugin_cap && in_array( $cap, $this->theme_caps, true );

		if ( ! $is_plugin_cap && ! $is_theme_cap ) {
			return $caps;
		}

		$settings  = Settings::get_settings();
		$restricted = $is_plugin_cap ? $settings['restrict_plugin_management'] : $settings['restrict_theme_management'];

		if ( ! $restricted ) {
			return $caps;
		}

		if ( is_829_admin( $user_id ) ) {
			return $caps;
		}

		$whitelist_key = $is_plugin_cap ? 'plugin_management_whitelist' : 'theme_management_whitelist';
		$whitelist     = ! empty( $settings[ $whitelist_key ] ) ? array_map( 'intval', (array) $settings[ $whitelist_key ] ) : [];
		$uid           = $user_id ? (int) $user_id : get_current_user_id();

		if ( ! empty( $whitelist ) && in_array( $uid, $whitelist, true ) ) {
			return []; // Empty caps array grants access regardless of user role.
		}

		return [ 'do_not_allow' ];
	}
}

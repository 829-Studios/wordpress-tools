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
	 * Capabilities to restrict for plugin/theme management.
	 *
	 * @var array
	 */
	protected $restricted_caps = [
		// Plugin capabilities
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
		// Theme capabilities
		'install_themes',
		'switch_themes',
		'delete_themes',
		'update_themes',
		'edit_themes',
		'upload_themes',
		'delete_theme',
		'update_theme',
		// Multisite network capabilities
		'manage_network_plugins',
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
	 * Check if restrictions should be applied.
	 *
	 * @return bool
	 */
	protected function should_restrict() {
		$settings = Settings::get_settings();

		if ( ! $settings['restrict_plugin_management'] ) {
			return false;
		}

		if ( is_829_admin() ) {
			return false;
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
		if ( ! $this->should_restrict() ) {
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
		if ( ! $this->should_restrict() ) {
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
		// Check if this is a restricted capability
		if ( ! in_array( $cap, $this->restricted_caps, true ) ) {
			return $caps;
		}

		$settings = Settings::get_settings();

		// If restriction is not enabled, return early
		if ( ! $settings['restrict_plugin_management'] ) {
			return $caps;
		}

		// Check if user is an 829 admin
		if ( is_829_admin( $user_id ) ) {
			return $caps;
		}

		// Deny the capability by requiring 'do_not_allow'
		return [ 'do_not_allow' ];
	}
}

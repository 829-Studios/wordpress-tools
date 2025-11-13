<?php
/**
 * Plugin Name: 829 Studios WordPress Tools
 * Plugin URI: https://www.829studios.com/
 * Description: WordPress tools for 829 Studios.
 * Version: 1.0.0
 * Author: 829 Studios
 * Author URI: https://www.829studios.com/
 * Text Domain: 829-wordpress-tools
 * Requires PHP: 7.4
 *
 * @package wordpress-tools
 */

namespace WordPressTools;

use WordPressTools\SSO\SSO;
use WordPressTools\PostPasswords\PostPasswords;
use WordPressTools\Headers\Headers;
use WordPressTools\Comments\Comments;
use WordPressTools\Settings\Settings;
use WordPressTools\Authentication\Passwords;
use WordPressTools\Authentication\Usernames;
use WordPressTools\AdminCustomizations\AdminCustomizations;
use WordPressTools\API\API;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPT_VERSION', '1.0.0' );
define( 'WPT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Load utility functions
require_once __DIR__ . '/includes/utils.php';

if ( ! defined( 'WPT_SSO_PROXY_URL' ) ) {
	define( 'WPT_SSO_PROXY_URL', 'https://x829-sso-proxy-ee756094fea9.herokuapp.com' );
}

if ( ! defined( 'WPT_SSO_DEFAULT_ROLE' ) ) {
	define( 'WPT_SSO_DEFAULT_ROLE', 'subscriber' );
}

if ( ! defined( 'WPT_SSO_DISABLE' ) ) {
	define( 'WPT_SSO_DISABLE', false );
}

if ( ! defined( 'WPT_SSO_GRANT_SUPER_ADMIN' ) ) {
	define( 'WPT_SSO_GRANT_SUPER_ADMIN', false );
}

if ( ! defined( 'WPT_SSO_DISALLOW_ALL_DIRECT_LOGIN' ) ) {
	define( 'WPT_SSO_DISALLOW_ALL_DIRECT_LOGIN', false );
}

// Define a constant if we're network activated to allow plugin to respond accordingly.
$network_activated = Utils\is_network_activated( plugin_basename( __FILE__ ) );

if ( ! defined( 'WPT_IS_NETWORK' ) ) {
	define( 'WPT_IS_NETWORK', (bool) $network_activated );
}

// Handle DISALLOW_FILE_MODS setting
if ( ! defined( 'DISALLOW_FILE_MODS' ) ) {
	$disallow_file_mods = Utils\get_maybe_site_option( 'wpt_disallow_file_mods', 'no' );
	if ( 'yes' === $disallow_file_mods ) {
		define( 'DISALLOW_FILE_MODS', true );
	}
}

spl_autoload_register(
	function ( $class_name ) {
		$path_parts = explode( '\\', $class_name );

		if ( ! empty( $path_parts ) ) {
			$package = $path_parts[0];

			unset( $path_parts[0] );

			if ( 'WordPressTools' === $package ) {
				require_once __DIR__ . '/includes/classes/' . implode( '/', $path_parts ) . '.php';
			}
		}
	}
);

add_action(
	'plugins_loaded',
	function () {
		SSO::instance();
		PostPasswords::instance();
		Headers::instance();
		Comments::instance();
		Settings::instance();
		Passwords::instance();
		Usernames::instance();
		AdminCustomizations::instance();
		API::instance();
	}
);

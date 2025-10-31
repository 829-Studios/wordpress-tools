<?php
/**
 * Plugin Name: WordPress Tools
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

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WORDPRESS_TOOLS_VERSION', '1.0.0' );
define( 'WORDPRESS_TOOLS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WORDPRESS_TOOLS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

if ( ! defined( 'WORDPRESS_TOOLS_SSO_PROXY_URL' ) ) {
	define( 'WORDPRESS_TOOLS_SSO_PROXY_URL', 'https://x829-sso-proxy-ee756094fea9.herokuapp.com/sso/login' );
}

if ( ! defined( 'WORDPRESS_TOOLS_SSO_DEFAULT_ROLE' ) ) {
	define( 'WORDPRESS_TOOLS_SSO_DEFAULT_ROLE', 'subscriber' );
}

if ( ! defined( 'WORDPRESS_TOOLS_SSO_DISABLE' ) ) {
	define( 'WORDPRESS_TOOLS_SSO_DISABLE', false );
}

if ( ! defined( 'WORDPRESS_TOOLS_SSO_GRANT_SUPER_ADMIN' ) ) {
	define( 'WORDPRESS_TOOLS_SSO_GRANT_SUPER_ADMIN', false );
}

if ( ! defined( 'WORDPRESS_TOOLS_SSO_DISALLOW_ALL_DIRECT_LOGIN' ) ) {
	define( 'WORDPRESS_TOOLS_SSO_DISALLOW_ALL_DIRECT_LOGIN', false );
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
		Plugin::get_instance();
	}
);

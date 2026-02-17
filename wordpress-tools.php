<?php
/**
 * Plugin Name: 829 Studios - WordPress Tools
 * Plugin URI: https://www.829studios.com/
 * Description: WordPress tools for 829 Studios.
 * Version: 1.4.2
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
use WordPressTools\Authentication\LimitLogin;
use WordPressTools\Authentication\TwoFactor;
use WordPressTools\AdminCustomizations\AdminCustomizations;
use WordPressTools\PluginManagement\PluginManagement;
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;
use WordPressTools\API\API;
use WP_CLI;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPT_VERSION', '1.4.2' );
define( 'WPT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Load utility functions
require_once __DIR__ . '/includes/utils.php';

if ( ! defined( 'WPT_IS_WPE' ) ) {
	$is_wpe = ( defined( 'WPE_APIKEY' ) || defined( 'WPE_FORCE_SSL_LOGIN' ) || defined( 'WPE_WHITELABEL' ) );

	define( 'WPT_IS_WPE', $is_wpe );
}

if ( ! defined( 'WPT_SSO_PROXY_URL' ) ) {
	define( 'WPT_SSO_PROXY_URL', 'https://x829-sso-proxy-ee756094fea9.herokuapp.com' );
}

if ( ! defined( 'DISALLOW_FILE_EDIT' ) ) {
	define( 'DISALLOW_FILE_EDIT', true );
}

if ( ! defined( 'WPT_SSO_DEFAULT_ROLE' ) ) {
	define( 'WPT_SSO_DEFAULT_ROLE', 'subscriber' );
}

if ( ! defined( 'WPT_SSO_DISABLE' ) ) {
	define( 'WPT_SSO_DISABLE', false );
}

if ( ! defined( 'WPT_SSO_GRANT_SUPER_ADMIN' ) ) {
	define( 'WPT_SSO_GRANT_SUPER_ADMIN', true );
}

if ( ! defined( 'WPT_SSO_DISALLOW_ALL_DIRECT_LOGIN' ) ) {
	define( 'WPT_SSO_DISALLOW_ALL_DIRECT_LOGIN', false );
}

if ( ! defined( 'WPT_ALLOW_ADMIN_SETTINGS_ACCESS' ) ) {
	define( 'WPT_ALLOW_ADMIN_SETTINGS_ACCESS', false );
}

if ( ! defined( 'WPT_LOGIN_ATTEMPT_LIMIT' ) ) {
	define( 'WPT_LOGIN_ATTEMPT_LIMIT', 10 );
}

if ( ! defined( 'WPT_LOGIN_LOCKOUT_DURATION' ) ) {
	define( 'WPT_LOGIN_LOCKOUT_DURATION', 900 ); // 15 minutes in seconds
}

if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

if ( ! defined( 'WP_ENVIRONMENT_TYPE' ) ) {
	$staging_domains = [ '829dev.com', 'wpenginepowered.com' ];
	$current_domain  = strtolower( wp_parse_url( site_url(), PHP_URL_HOST ) );
	$is_staging      = false;

	// Check if current domain matches or is a subdomain of any staging domain
	foreach ( $staging_domains as $staging_domain ) {
		$suffix = '.' . $staging_domain;
		if ( $current_domain === $staging_domain || substr( $current_domain, -strlen( $suffix ) ) === $suffix ) {
			$is_staging = true;
			break;
		}
	}

	if ( $is_staging ) {
		define( 'WP_ENVIRONMENT_TYPE', 'staging' );
	} elseif ( Utils\is_local_environment() ) {
		define( 'WP_ENVIRONMENT_TYPE', 'development' );
	}
}

// Define a constant if we're network activated to allow plugin to respond accordingly.
$network_activated = Utils\is_network_activated( plugin_basename( __FILE__ ) );

if ( ! defined( 'WPT_IS_NETWORK' ) ) {
	define( 'WPT_IS_NETWORK', (bool) $network_activated );
}

spl_autoload_register(
	function ( $class_name ) {
		$path_parts = explode( '\\', $class_name );

		if ( ! empty( $path_parts ) ) {
			$package = $path_parts[0];

			unset( $path_parts[0] );

			if ( 'WordPressTools' === $package ) {
				require_once __DIR__ . '/includes/classes/' . implode( '/', $path_parts ) . '.php';
			} elseif ( 'ZxcvbnPhp' === $package ) {
				require_once __DIR__ . '/vendor/bjeavons/zxcvbn-php/src/' . implode( '/', $path_parts ) . '.php';
			}
		}
	}
);

require_once __DIR__ . '/vendor/yahnis-elsts/plugin-update-checker/plugin-update-checker.php';

$plugin_updater = PucFactory::buildUpdateChecker(
	'https://github.com/829-studios/wordpress-tools/',
	__FILE__,
	'wordpress-tools'
);

$plugin_updater->getVcsApi()->enableReleaseAssets();

PluginManagement::instance();

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
		LimitLogin::instance();
		TwoFactor::instance();
		AdminCustomizations::instance();
		API::instance();
	}
);

// Register WP-CLI commands
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	\WP_CLI::add_command( '829-tools', 'WordPressTools\Commands' );
}

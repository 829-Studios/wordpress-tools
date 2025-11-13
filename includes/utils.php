<?php
/**
 * Utility functions
 *
 * @package  WordPressTools
 */

namespace WordPressTools\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Check if the current environment is local.
 *
 * @return bool
 */
function is_local_environment() {
	$test_tlds = array( 'test', 'local', 'localhost', '' );
	$host      = wp_parse_url( site_url(), PHP_URL_HOST );
	$tld       = preg_replace( '#^.*\.(.*)$#', '$1', $host );

	// Check if it's localhost or an IP address
	if ( 'localhost' === $host || filter_var( $host, FILTER_VALIDATE_IP ) ) {
		return true;
	}

	return in_array( $tld, $test_tlds, true );
}

/**
 * Check if the plugin is network activated
 *
 * @param string $plugin The plugin basename
 * @return bool
 */
function is_network_activated( $plugin ) {
	$plugins = get_site_option( 'active_sitewide_plugins' );

	return is_multisite() && isset( $plugins[ $plugin ] );
}

/**
 * Get a site option or a network option
 *
 * @param string $option The option name
 * @param mixed  $default The default value
 * @return mixed
 */
function get_maybe_site_option( $option, $default = null ) {
	if ( WPT_IS_NETWORK ) {
		return get_site_option( $option, $default );
	}

	return get_option( $option, $default );
}

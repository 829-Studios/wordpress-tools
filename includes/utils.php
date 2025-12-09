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
 * @param mixed  $default_value The default value
 * @return mixed
 */
function get_maybe_site_option( $option, $default_value = null ) {
	if ( WPT_IS_NETWORK ) {
		return get_site_option( $option, $default_value );
	}

	return get_option( $option, $default_value );
}

/**
 * Get the IP address of the current user
 *
 * @return string
 */
function get_ip_address() {
	$ip = '';

	// Check for proxy headers first
	if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
		$ips = explode( ',', $_SERVER['HTTP_X_FORWARDED_FOR'] );
		$ip  = trim( $ips[0] );
	} elseif ( ! empty( $_SERVER['HTTP_X_REAL_IP'] ) ) {
		$ip = $_SERVER['HTTP_X_REAL_IP'];
	} elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
		$ip = $_SERVER['REMOTE_ADDR'];
	}

	// Sanitize IP address
	$ip = filter_var( $ip, FILTER_VALIDATE_IP );

	return $ip ? $ip : '0.0.0.0';
}

/**
 * Check if a user has an @829llc.com email.
 *
 * @param WP_User|int|null $user Optional. User ID to check. Defaults to current user.
 * @return bool
 */
function is_829_user( $user = null ) {
	if ( null === $user ) {
		$user_obj = wp_get_current_user();
	} elseif ( is_numeric( $user ) ) {
		$user_obj = get_userdata( $user );
	} elseif ( $user instanceof \WP_User ) {
		$user_obj = $user;
	} else {
		return false;
	}

	if ( ! $user_obj || empty( $user_obj->user_email ) ) {
		return false;
	}

	return (bool) preg_match( '/@829llc\.com$/i', $user_obj->user_email );
}

/**
 * Check if a user is an 829 admin (admin with @829llc.com email).
 *
 * @param int|null $user_id Optional. User ID to check. Defaults to current user.
 * @return bool
 */
function is_829_admin( $user_id = null ) {
	if ( null === $user_id ) {
		$user = wp_get_current_user();
	} else {
		$user = get_userdata( $user_id );
	}

	if ( ! $user || ! $user->exists() ) {
		return false;
	}

	// Check if user has admin capabilities
	// Note: We check roles/caps directly to avoid infinite recursion with user_has_cap filter
	if ( WPT_IS_NETWORK ) {
		if ( ! is_super_admin( $user->ID ) ) {
			return false;
		}
	} else {
		// Check if user has administrator role or manage_options in their allcaps
		$is_admin = in_array( 'administrator', (array) $user->roles, true ) ||
					( isset( $user->allcaps['manage_options'] ) && $user->allcaps['manage_options'] );
		if ( ! $is_admin ) {
			return false;
		}
	}

	// Check if user has @829llc.com email
	return is_829_user( $user );
}

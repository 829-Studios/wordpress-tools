<?php
/**
 * WP CLI Commands
 *
 * @package  WordPressTools
 */

namespace WordPressTools;

use WP_CLI_Command;
use WP_CLI;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * CLI Commands for 829 Studios WordPress Tools
 */
class Commands extends WP_CLI_Command {

	/**
	 * Clear all login attempt transients
	 *
	 * Removes all transient data related to failed login attempts, effectively
	 * unlocking any IP addresses that are currently locked out.
	 *
	 * ## EXAMPLES
	 *
	 *     # Clear all login attempt transients
	 *     $ wp 829-tools clear-login-attempts
	 *     Success: Cleared 5 login attempt transient(s).
	 *
	 * @subcommand clear-login-attempts
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function clear_login_attempts( $args, $assoc_args ) {
		global $wpdb;

		WP_CLI::log( 'Clearing login attempt transients...' );

		// Query all transients that start with wpt_login_attempts_
		$transient_prefix = '_transient_wpt_login_attempts_';
		$like             = $wpdb->esc_like( $transient_prefix ) . '%';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$transients = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
				$like
			)
		);

		$count = 0;

		if ( empty( $transients ) ) {
			WP_CLI::success( 'No login attempt transients found.' );
			return;
		}

		foreach ( $transients as $transient_name ) {
			// Remove the _transient_ prefix to get the transient key
			$key = str_replace( '_transient_', '', $transient_name );
			if ( delete_transient( $key ) ) {
				++$count;
			}
		}

		WP_CLI::success( sprintf( 'Cleared %d login attempt transient(s).', $count ) );
	}

	/**
	 * Regenerate the read API key.
	 *
	 * Generates a new API key for the read-only REST endpoint and saves it
	 * to the database. Cannot be used when WPT_DASHBOARD_API_KEY is defined in
	 * wp-config.php.
	 *
	 * ## EXAMPLES
	 *
	 *     # Regenerate the API key
	 *     $ wp 829-tools regenerate-api-key
	 *     Success: New API key: abc123...
	 *
	 * @subcommand regenerate-api-key
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function regenerate_api_key( $args, $assoc_args ) {
		if ( defined( 'WPT_DASHBOARD_API_KEY' ) && ! empty( WPT_DASHBOARD_API_KEY ) ) {
			WP_CLI::error( 'Cannot regenerate API key — WPT_DASHBOARD_API_KEY is defined in wp-config.php.' );
		}

		$key = wp_generate_password( 40, false );

		if ( WPT_IS_NETWORK ) {
			update_site_option( 'wpt_dashboard_api_key', $key );
		} else {
			update_option( 'wpt_dashboard_api_key', $key );
		}

		WP_CLI::success( $key );
	}
}

<?php
/**
 * WP CLI Commands
 *
 * @package  WordPressTools
 */

namespace WordPressTools;

use WordPressTools\NotFound\NotFoundDigest;
use WordPressTools\NotFound\NotFoundLog;
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

	/**
	 * List the most-visited 404 URLs.
	 *
	 * ## OPTIONS
	 *
	 * [--days=<days>]
	 * : Days to include, counting today. Up to 30.
	 * ---
	 * default: 7
	 * ---
	 *
	 * [--limit=<limit>]
	 * : Max URLs to list.
	 * ---
	 * default: 50
	 * ---
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp 829-tools 404s --days=1
	 *
	 * @subcommand 404s
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function list_404s( $args, $assoc_args ) {
		$days   = max( 1, min( NotFoundLog::RETENTION_DAYS, (int) $assoc_args['days'] ) );
		$report = NotFoundLog::instance()->get_recent_report( $days, max( 1, (int) $assoc_args['limit'] ) );

		if ( empty( $report['urls'] ) ) {
			WP_CLI::success( sprintf( 'No 404s logged since %s.', $report['from'] ) );
			return;
		}

		\WP_CLI\Utils\format_items( $assoc_args['format'], $report['urls'], [ 'path', 'hits', 'first_seen', 'last_seen', 'referrer' ] );
	}

	/**
	 * Send yesterday's 404 report now.
	 *
	 * Unlike the daily email, this also sends from staging and dev.
	 *
	 * ## OPTIONS
	 *
	 * [--to=<emails>]
	 * : Comma-separated addresses. Defaults to the Daily Report Recipients in 829 Settings.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp 829-tools 404-digest --to=dev@example.com
	 *
	 * @subcommand 404-digest
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function send_404_digest( $args, $assoc_args ) {
		$recipients = isset( $assoc_args['to'] )
			? array_filter( array_map( 'trim', explode( ',', $assoc_args['to'] ) ), 'is_email' )
			: NotFoundDigest::get_recipients();

		if ( empty( $recipients ) ) {
			WP_CLI::error( 'No recipients. Pass --to or set Daily Report Recipients in 829 Settings.' );
		}

		$digest   = NotFoundDigest::instance();
		$sections = $digest->get_sections();

		if ( empty( $sections ) ) {
			WP_CLI::success( 'No 404s yesterday, so nothing was sent.' );
			return;
		}

		if ( ! $digest->send( $recipients, $sections ) ) {
			WP_CLI::error( 'wp_mail() failed to send the report.' );
		}

		WP_CLI::success( sprintf( 'Sent to %s.', implode( ', ', $recipients ) ) );
	}

	/**
	 * Set an api key
	 *
	 * @subcommand set-api-key <key>
	 * @param array $args Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function set_api_key( $args, $assoc_args ) {
		if ( defined( 'WPT_DASHBOARD_API_KEY' ) && ! empty( WPT_DASHBOARD_API_KEY ) ) {
			WP_CLI::error( 'Cannot set API key — WPT_DASHBOARD_API_KEY is defined in wp-config.php.' );
		}

		$key = $args[0];

		if ( empty( $key ) ) {
			WP_CLI::error( 'Must provide key' );
		}

		if ( WPT_IS_NETWORK ) {
			update_site_option( 'wpt_dashboard_api_key', $key );
		} else {
			update_option( 'wpt_dashboard_api_key', $key );
		}

		WP_CLI::success( $key );
	}
}

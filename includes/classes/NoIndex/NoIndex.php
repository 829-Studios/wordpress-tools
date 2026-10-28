<?php
/**
 * Force No-Index on staging and dev environments.
 *
 * @package  WordPressTools
 */

namespace WordPressTools\NoIndex;

use WordPressTools\Singleton;
use function WordPressTools\Utils\is_staging_or_dev;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * NoIndex class
 *
 * Injects noindex meta tags and X-Robots-Tag headers on staging/dev domains
 * or when WP_ENVIRONMENT_TYPE is not 'production'. Supports a timed disable
 * (max 1 hour) configurable from the settings page.
 */
class NoIndex {

	use Singleton;

	const TRANSIENT_KEY       = 'wpt_noindex_disabled_until';
	const MAX_DISABLE_SECONDS = 3600;

	/**
	 * Setup hooks.
	 */
	public function setup() {
		if ( ! is_staging_or_dev() ) {
			return;
		}

		add_filter( 'wp_robots', [ $this, 'add_noindex_robots' ] );
		add_action( 'send_headers', [ $this, 'send_noindex_header' ] );
	}

	/**
	 * Return true when enforcement is currently suspended via timed disable.
	 *
	 * @return bool
	 */
	public static function is_temporarily_disabled(): bool {
		return self::get_disabled_until() > 0;
	}

	/**
	 * Return the Unix timestamp when enforcement will resume, or 0 if active.
	 *
	 * @return int
	 */
	public static function get_disabled_until(): int {
		$val = WPT_IS_NETWORK
			? get_site_transient( self::TRANSIENT_KEY )
			: get_transient( self::TRANSIENT_KEY );

		if ( ! $val || time() >= (int) $val ) {
			return 0;
		}

		return (int) $val;
	}

	/**
	 * Suspend enforcement for a given number of seconds (capped at MAX_DISABLE_SECONDS).
	 *
	 * @param  int $duration_seconds Duration to suspend enforcement.
	 * @return int                   Unix timestamp when enforcement will resume.
	 */
	public static function set_timed_disable( int $duration_seconds ): int {
		$duration       = min( abs( $duration_seconds ), self::MAX_DISABLE_SECONDS );
		$disabled_until = time() + $duration;

		if ( WPT_IS_NETWORK ) {
			set_site_transient( self::TRANSIENT_KEY, $disabled_until, $duration );
		} else {
			set_transient( self::TRANSIENT_KEY, $disabled_until, $duration );
		}

		return $disabled_until;
	}

	/**
	 * Clear the timed disable and immediately resume enforcement.
	 *
	 * @return void
	 */
	public static function clear_timed_disable(): void {
		if ( WPT_IS_NETWORK ) {
			delete_site_transient( self::TRANSIENT_KEY );
		} else {
			delete_transient( self::TRANSIENT_KEY );
		}
	}

	/**
	 * Add noindex/nofollow directives to WordPress's robots meta tag.
	 *
	 * Hooks into wp_robots (WP 5.7+) so there is only ever one <meta name="robots"> tag.
	 *
	 * @param  array $robots Current robots directives.
	 * @return array
	 */
	public function add_noindex_robots( array $robots ): array {
		if ( self::is_temporarily_disabled() ) {
			return $robots;
		}
		$robots['noindex']  = true;
		$robots['nofollow'] = true;
		return $robots;
	}

	/**
	 * Send X-Robots-Tag HTTP header on frontend requests only.
	 *
	 * @return void
	 */
	public function send_noindex_header(): void {
		if ( self::is_temporarily_disabled() ) {
			return;
		}

		// Skip admin screens, AJAX, and REST API endpoints.
		if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		header( 'X-Robots-Tag: noindex, nofollow' );
	}
}

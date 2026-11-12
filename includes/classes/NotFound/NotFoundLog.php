<?php
/**
 * 404 Log
 *
 * @package  WordPressTools
 */

namespace WordPressTools\NotFound;

use WordPressTools\Singleton;
use WordPressTools\Settings\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 404 Log class
 *
 * Counts front-end 404s per URL per day in one network-wide table, skipping
 * common bot probes. Stores the path and last referrer only, no IPs or user agents.
 */
class NotFoundLog {

	use Singleton;

	/**
	 * DB version for schema migrations.
	 *
	 * @var string
	 */
	const DB_VERSION = '1.0';

	/**
	 * Site option holding the installed DB version.
	 *
	 * @var string
	 */
	const DB_VERSION_OPTION = 'wpt_404_log_db_version';

	/**
	 * Table name (without prefix).
	 *
	 * @var string
	 */
	const TABLE_NAME = 'wpt_404_log';

	/**
	 * Days of history to keep.
	 *
	 * @var int
	 */
	const RETENTION_DAYS = 30;

	/**
	 * Row cap per site, so a scan of thousands of unique URLs can't grow the table unchecked.
	 *
	 * @var int
	 */
	const MAX_ROWS_PER_SITE = 10000;

	/**
	 * Max stored length of a path or referrer.
	 *
	 * @var int
	 */
	const MAX_LENGTH = 2048;

	/**
	 * Setup module
	 */
	public function setup() {
		add_action( 'admin_init', [ $this, 'maybe_create_table' ] );
		add_action( 'init', [ $this, 'schedule_cleanup' ] );
		add_action( 'wpt_404_log_cleanup', [ $this, 'cleanup' ] );

		// Late, so canonical and plugin redirects (e.g. Redirection) have already had their chance.
		add_action( 'template_redirect', [ $this, 'maybe_log' ], 999 );
	}

	/**
	 * Whether 404 logging is turned on.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		$settings = Settings::get_settings();

		return ! empty( $settings['log_404s'] );
	}

	/**
	 * Get the full table name. One table for the whole network, scoped by blog_id.
	 *
	 * @return string
	 */
	private static function get_table_name() {
		global $wpdb;

		return $wpdb->base_prefix . self::TABLE_NAME;
	}

	/**
	 * Whether the table exists at the current schema version.
	 *
	 * @return bool
	 */
	private function is_table_ready() {
		return self::DB_VERSION === get_site_option( self::DB_VERSION_OPTION );
	}

	/**
	 * Create or upgrade the table if needed.
	 */
	public function maybe_create_table() {
		if ( $this->is_table_ready() ) {
			return;
		}

		global $wpdb;

		$table_name      = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			blog_id bigint(20) unsigned NOT NULL DEFAULT 1,
			log_date date NOT NULL,
			url_hash char(32) NOT NULL,
			path varchar(2048) NOT NULL,
			referrer varchar(2048) NOT NULL DEFAULT '',
			hits int(10) unsigned NOT NULL DEFAULT 0,
			last_seen datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY blog_url_date (blog_id,url_hash,log_date),
			KEY blog_date (blog_id,log_date)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_site_option( self::DB_VERSION_OPTION, self::DB_VERSION );
	}

	/**
	 * Schedule the daily cleanup cron event.
	 */
	public function schedule_cleanup() {
		// Cleanup covers every site's rows, so one event on the main site is enough.
		if ( WPT_IS_NETWORK && ! is_main_site() ) {
			return;
		}

		if ( ! wp_next_scheduled( 'wpt_404_log_cleanup' ) ) {
			wp_schedule_event( time(), 'daily', 'wpt_404_log_cleanup' );
		}
	}

	/**
	 * Record the current request if it's a 404 worth keeping.
	 */
	public function maybe_log() {
		if ( ! is_404() || ! self::is_enabled() ) {
			return;
		}

		// Plugins can serve the 404 template with another status, e.g. Redirection's "410 Gone" rules.
		if ( 404 !== http_response_code() ) {
			return;
		}

		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'GET' !== $_SERVER['REQUEST_METHOD'] ) {
			return;
		}

		// Browsers always send a user agent; many scanners don't.
		if ( empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
			return;
		}

		$path = self::normalize_path( isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '' );

		if ( '' === $path || self::is_ignored( $path ) ) {
			return;
		}

		$referrer = isset( $_SERVER['HTTP_REFERER'] ) ? self::normalize_referrer( $_SERVER['HTTP_REFERER'] ) : '';

		// Covers sites updated without an admin visit, which is when admin_init would create it.
		$this->maybe_create_table();

		global $wpdb;

		$table_name = self::get_table_name();
		$now        = current_time( 'mysql', true );

		// Values are repeated rather than using VALUES(), which MySQL 8 deprecates.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Upsert has no API equivalent; table name is built from $wpdb->base_prefix.
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$table_name} (blog_id, log_date, url_hash, path, referrer, hits, last_seen)
				VALUES (%d, %s, %s, %s, %s, 1, %s)
				ON DUPLICATE KEY UPDATE
					hits = hits + 1,
					last_seen = %s,
					referrer = IF(%s = '', referrer, %s)",
				get_current_blog_id(),
				current_time( 'Y-m-d' ),
				md5( $path ),
				$path,
				$referrer,
				$now,
				$now,
				$referrer,
				$referrer
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Reduce a request URI to its path, percent-encoding anything outside printable ASCII.
	 *
	 * @param string $uri Raw request URI.
	 * @return string Path, or '' if the URI isn't a path.
	 */
	public static function normalize_path( $uri ) {
		$parts = explode( '?', (string) $uri, 2 );
		$path  = $parts[0];

		if ( '' === $path || '/' !== $path[0] ) {
			return '';
		}

		$path = preg_replace_callback(
			'/[^\x21-\x7E]/',
			function ( $matches ) {
				return rawurlencode( $matches[0] );
			},
			$path
		);

		return substr( $path, 0, self::MAX_LENGTH );
	}

	/**
	 * Keep only an http(s) referrer, without its query string, which can carry personal data.
	 *
	 * @param string $referrer Raw referrer header.
	 * @return string
	 */
	public static function normalize_referrer( $referrer ) {
		$referrer = esc_url_raw( (string) $referrer, [ 'http', 'https' ] );
		$parts    = preg_split( '/[?#]/', $referrer, 2 );

		return substr( $parts[0], 0, self::MAX_LENGTH );
	}

	/**
	 * Whether a path matches a known bot-probe pattern.
	 *
	 * @param string $path Normalized path.
	 * @return bool
	 */
	public static function is_ignored( $path ) {
		$patterns = [
			'#/\.#',                                                            // Dotfiles and dot-directories: .env, .git/, .well-known/.
			'#\.(php\d?|phtml|aspx?|jsp|cgi|env|ini|log|sql|bak|old|orig|swp|ya?ml)$#i',
			'#/wp-(admin|includes)/#i',                                         // Including /blog/wp-includes/… style probes.
			'#/wp-content/(plugins|themes)/#i',
			'#/(cgi-bin|phpmyadmin|autodiscover)(/|$)#i',
			'#/(wlwmanifest\.xml|ads\.txt|app-ads\.txt|apple-touch-icon[^/]*\.png)$#i',
		];

		/**
		 * Filters the regexes for 404 paths that aren't logged.
		 *
		 * @param string[] $patterns PCRE patterns matched against the request path.
		 */
		$patterns = (array) apply_filters( 'wpt_404_ignore_patterns', $patterns );

		foreach ( $patterns as $pattern ) {
			if ( preg_match( $pattern, $path ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Delete rows past retention, then cap each site's row count.
	 */
	public function cleanup() {
		if ( ! $this->is_table_ready() ) {
			return;
		}

		global $wpdb;

		$table_name = self::get_table_name();
		$cutoff     = gmdate( 'Y-m-d', strtotime( '-' . self::RETENTION_DAYS . ' days' ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$blog_ids = $wpdb->get_col( "SELECT DISTINCT blog_id FROM {$table_name}" );

		foreach ( $blog_ids as $blog_id ) {
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$table_name} WHERE blog_id = %d AND log_date < %s",
					$blog_id,
					$cutoff
				)
			);

			$cutoff_id = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$table_name} WHERE blog_id = %d ORDER BY id DESC LIMIT %d, 1",
					$blog_id,
					self::MAX_ROWS_PER_SITE
				)
			);

			if ( $cutoff_id ) {
				$wpdb->query(
					$wpdb->prepare(
						"DELETE FROM {$table_name} WHERE blog_id = %d AND id <= %d",
						$blog_id,
						$cutoff_id
					)
				);
			}
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Most-hit 404 URLs for the current site over the last N days, including today.
	 *
	 * @param int $days  Days to include.
	 * @param int $limit Max URLs to return.
	 * @return array See get_report().
	 */
	public function get_recent_report( $days = self::RETENTION_DAYS, $limit = 100 ) {
		$today = new \DateTimeImmutable( 'today', wp_timezone() );
		$from  = $today->modify( '-' . ( max( 1, (int) $days ) - 1 ) . ' days' );

		return $this->get_report( $from->format( 'Y-m-d' ), $today->format( 'Y-m-d' ), $limit );
	}

	/**
	 * Most-hit 404 URLs for the current site between two site-local dates, inclusive.
	 *
	 * @param string $from  Start date, Y-m-d.
	 * @param string $to    End date, Y-m-d.
	 * @param int    $limit Max URLs to return.
	 * @return array {
	 *     @type string $from       Start date.
	 *     @type string $to         End date.
	 *     @type int    $total_urls Distinct URLs in the range.
	 *     @type int    $total_hits Total 404 hits in the range.
	 *     @type array  $urls       Rows from format_row(), most hits first.
	 * }
	 */
	public function get_report( $from, $to, $limit = 100 ) {
		$report = [
			'from'       => $from,
			'to'         => $to,
			'total_urls' => 0,
			'total_hits' => 0,
			'urls'       => [],
		];

		if ( ! $this->is_table_ready() ) {
			return $report;
		}

		global $wpdb;

		$table_name = self::get_table_name();
		$blog_id    = get_current_blog_id();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$totals = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT url_hash) AS urls, COALESCE(SUM(hits), 0) AS hits
				FROM {$table_name}
				WHERE blog_id = %d AND log_date BETWEEN %s AND %s",
				$blog_id,
				$from,
				$to
			),
			ARRAY_A
		);

		// first_seen looks across all retained days, so a URL that also 404'd before $from isn't "new".
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT MAX(d.path) AS path, SUM(d.hits) AS hits, MAX(d.last_seen) AS last_seen, MAX(d.referrer) AS referrer,
					(SELECT MIN(f.log_date) FROM {$table_name} f WHERE f.blog_id = %d AND f.url_hash = d.url_hash) AS first_seen
				FROM {$table_name} d
				WHERE d.blog_id = %d AND d.log_date BETWEEN %s AND %s
				GROUP BY d.url_hash
				ORDER BY hits DESC, last_seen DESC
				LIMIT %d",
				$blog_id,
				$blog_id,
				$from,
				$to,
				$limit
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$report['total_urls'] = (int) $totals['urls'];
		$report['total_hits'] = (int) $totals['hits'];
		$report['urls']       = array_map( [ __CLASS__, 'format_row' ], (array) $rows );

		return $report;
	}

	/**
	 * Shape a DB row for output.
	 *
	 * @param array $row Raw row.
	 * @return array
	 */
	private static function format_row( $row ) {
		return [
			'url'           => self::get_url( $row['path'] ),
			'path'          => $row['path'],
			'hits'          => (int) $row['hits'],
			'first_seen'    => $row['first_seen'],
			'last_seen'     => $row['last_seen'],
			'referrer'      => $row['referrer'],
			'referrer_type' => self::get_referrer_type( $row['referrer'] ),
		];
	}

	/**
	 * Full URL for a logged path on the current site.
	 *
	 * @param string $path Logged path.
	 * @return string
	 */
	public static function get_url( $path ) {
		$home   = wp_parse_url( home_url() );
		$origin = $home['scheme'] . '://' . $home['host'] . ( isset( $home['port'] ) ? ':' . $home['port'] : '' );

		return $origin . $path;
	}

	/**
	 * Classify a referrer as internal (a broken link on this site), external, or none.
	 *
	 * @param string $referrer Logged referrer.
	 * @return string internal|external|none
	 */
	public static function get_referrer_type( $referrer ) {
		if ( '' === $referrer ) {
			return 'none';
		}

		$strip_www = function ( $host ) {
			return preg_replace( '/^www\./', '', strtolower( (string) $host ) );
		};

		$referrer_host = $strip_www( wp_parse_url( $referrer, PHP_URL_HOST ) );
		$home_host     = $strip_www( wp_parse_url( home_url(), PHP_URL_HOST ) );

		return $referrer_host === $home_host ? 'internal' : 'external';
	}
}

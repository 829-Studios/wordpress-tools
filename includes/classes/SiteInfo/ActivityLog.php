<?php
/**
 * Activity Log
 *
 * @package  WordPressTools
 */

namespace WordPressTools\SiteInfo;

use WordPressTools\Singleton;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Activity Log class
 *
 * Tracks site changes in a custom DB table with daily cron cleanup.
 * On multisite, a single network-wide table stores all entries with
 * a blog_id column to scope logs per site.
 */
class ActivityLog {

	use Singleton;

	/**
	 * DB version for schema migrations.
	 *
	 * @var string
	 */
	const DB_VERSION = '1.1';

	/**
	 * Table name (without prefix).
	 *
	 * @var string
	 */
	const TABLE_NAME = 'wpt_activity_log';

	/**
	 * Setup module
	 */
	public function setup() {
		add_action( 'admin_init', [ $this, 'maybe_create_table' ] );
		add_action( 'init', [ $this, 'schedule_cleanup' ] );
		add_action( 'wpt_activity_log_cleanup', [ $this, 'cleanup' ] );

		// User hooks.
		add_action( 'user_register', [ $this, 'on_user_register' ] );
		add_action( 'profile_update', [ $this, 'on_profile_update' ] );
		add_action( 'set_user_role', [ $this, 'on_set_user_role' ], 10, 3 );
		add_action( 'deleted_user', [ $this, 'on_deleted_user' ], 10, 3 );
		add_action( 'wp_login', [ $this, 'on_wp_login' ] );

		// Plugin hooks.
		add_action( 'activated_plugin', [ $this, 'on_activated_plugin' ] );
		add_action( 'deactivated_plugin', [ $this, 'on_deactivated_plugin' ] );
		add_action( 'deleted_plugin', [ $this, 'on_deleted_plugin' ] );

		// Theme hooks.
		add_action( 'switch_theme', [ $this, 'on_switch_theme' ], 10, 3 );
		add_action( 'deleted_theme', [ $this, 'on_deleted_theme' ] );

		// Option hooks.
		add_action( 'updated_option', [ $this, 'on_updated_option' ], 10, 3 );
	}

	/**
	 * Get the full table name.
	 *
	 * Uses base_prefix so there is one table across the entire network
	 * on multisite, or the normal prefix on single-site.
	 *
	 * @return string
	 */
	private function get_table_name() {
		global $wpdb;
		return $wpdb->base_prefix . self::TABLE_NAME;
	}

	/**
	 * Create the activity log table if needed.
	 *
	 * Uses site options so the version is stored once network-wide on
	 * multisite (get_site_option falls back to get_option on single-site).
	 */
	public function maybe_create_table() {
		$option_key        = 'wpt_activity_log_db_version';
		$installed_version = get_site_option( $option_key );

		if ( self::DB_VERSION === $installed_version ) {
			return;
		}

		global $wpdb;

		$table_name      = $this->get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			blog_id bigint(20) unsigned NOT NULL DEFAULT 1,
			action varchar(255) NOT NULL,
			summary text NOT NULL,
			category varchar(50) NOT NULL,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY blog_id (blog_id),
			KEY category (category),
			KEY created_at (created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_site_option( $option_key, self::DB_VERSION );
	}

	/**
	 * Schedule the daily cleanup cron event.
	 */
	public function schedule_cleanup() {
		if ( ! wp_next_scheduled( 'wpt_activity_log_cleanup' ) ) {
			wp_schedule_event( time(), 'daily', 'wpt_activity_log_cleanup' );
		}
	}

	/**
	 * Cleanup old activity log records.
	 *
	 * 1. Delete records older than 60 days.
	 * 2. If still >1000 records per site, keep only the most recent 1000.
	 */
	public function cleanup() {
		global $wpdb;

		$table_name = $this->get_table_name();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// Delete records older than 60 days.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table_name} WHERE created_at < %s",
				gmdate( 'Y-m-d H:i:s', strtotime( '-60 days' ) )
			)
		);

		// Cap at 1000 records per blog_id.
		$blog_ids = $wpdb->get_col( "SELECT DISTINCT blog_id FROM {$table_name}" );

		foreach ( $blog_ids as $blog_id ) {
			$count = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table_name} WHERE blog_id = %d",
					$blog_id
				)
			);

			if ( $count > 1000 ) {
				$cutoff_id = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT id FROM {$table_name} WHERE blog_id = %d ORDER BY created_at DESC LIMIT 1000, 1",
						$blog_id
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
		}

		// phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Insert a log entry scoped to the current site.
	 *
	 * @param string $action   Action identifier.
	 * @param string $summary  Human-readable description.
	 * @param string $category Category (users, plugins, themes, options).
	 */
	private function log( $action, $summary, $category ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->insert(
			$this->get_table_name(),
			[
				'blog_id'    => get_current_blog_id(),
				'action'     => $action,
				'summary'    => $summary,
				'category'   => $category,
				'user_id'    => get_current_user_id(),
				'created_at' => current_time( 'mysql', true ),
			],
			[ '%d', '%s', '%s', '%s', '%d', '%s' ]
		);
	}

	/**
	 * Get recent log entries for the current site.
	 *
	 * @param int $limit Number of entries to return.
	 * @return array
	 */
	public function get_recent_logs( $limit = 100 ) {
		global $wpdb;

		$table_name = $this->get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, blog_id, action, summary, category, user_id, created_at FROM {$table_name} WHERE blog_id = %d ORDER BY created_at DESC LIMIT %d",
				get_current_blog_id(),
				$limit
			),
			ARRAY_A
		);
	}

	/**
	 * User registered.
	 *
	 * @param int $user_id User ID.
	 */
	public function on_user_register( $user_id ) {
		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return;
		}

		$this->log(
			'user_register',
			sprintf( 'New user registered: %s (%s)', $user->display_name, $user->user_email ),
			'users'
		);
	}

	/**
	 * User profile updated.
	 *
	 * @param int $user_id User ID.
	 */
	public function on_profile_update( $user_id ) {
		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return;
		}

		$this->log(
			'profile_update',
			sprintf( 'User profile updated: %s', $user->display_name ),
			'users'
		);
	}

	/**
	 * User role changed.
	 *
	 * @param int    $user_id   User ID.
	 * @param string $role      New role.
	 * @param array  $old_roles Old roles.
	 */
	public function on_set_user_role( $user_id, $role, $old_roles ) {
		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return;
		}

		$old_role = ! empty( $old_roles ) ? implode( ', ', $old_roles ) : 'none';

		$this->log(
			'set_user_role',
			sprintf( 'User role changed for %s: %s -> %s', $user->display_name, $old_role, $role ),
			'users'
		);
	}

	/**
	 * User deleted.
	 *
	 * @param int      $user_id  User ID.
	 * @param int|null $reassign Reassign user ID.
	 * @param \WP_User $user     User object.
	 */
	public function on_deleted_user( $user_id, $reassign, $user ) {
		$name  = $user instanceof \WP_User ? $user->display_name : "ID {$user_id}";
		$email = $user instanceof \WP_User ? $user->user_email : 'unknown';

		$this->log(
			'deleted_user',
			sprintf( 'User deleted: %s (%s)', $name, $email ),
			'users'
		);
	}

	/**
	 * User logged in.
	 *
	 * @param string $user_login Username.
	 */
	public function on_wp_login( $user_login ) {
		$this->log(
			'wp_login',
			sprintf( 'User logged in: %s', $user_login ),
			'users'
		);
	}

	/**
	 * Plugin activated.
	 *
	 * @param string $plugin Plugin basename.
	 */
	public function on_activated_plugin( $plugin ) {
		$this->log(
			'activated_plugin',
			sprintf( 'Plugin activated: %s', $plugin ),
			'plugins'
		);
	}

	/**
	 * Plugin deactivated.
	 *
	 * @param string $plugin Plugin basename.
	 */
	public function on_deactivated_plugin( $plugin ) {
		$this->log(
			'deactivated_plugin',
			sprintf( 'Plugin deactivated: %s', $plugin ),
			'plugins'
		);
	}

	/**
	 * Plugin deleted.
	 *
	 * @param string $plugin Plugin basename.
	 */
	public function on_deleted_plugin( $plugin ) {
		$this->log(
			'deleted_plugin',
			sprintf( 'Plugin deleted: %s', $plugin ),
			'plugins'
		);
	}

	/**
	 * Theme switched.
	 *
	 * @param string    $new_name  New theme name.
	 * @param \WP_Theme $new_theme New theme object.
	 * @param \WP_Theme $old_theme Old theme object.
	 */
	public function on_switch_theme( $new_name, $new_theme, $old_theme ) {
		$old_name = $old_theme instanceof \WP_Theme ? $old_theme->get( 'Name' ) : 'unknown';

		$this->log(
			'switch_theme',
			sprintf( 'Theme switched from %s to %s', $old_name, $new_name ),
			'themes'
		);
	}

	/**
	 * Theme deleted.
	 *
	 * @param string $stylesheet Theme stylesheet (slug).
	 */
	public function on_deleted_theme( $stylesheet ) {
		$this->log(
			'deleted_theme',
			sprintf( 'Theme deleted: %s', $stylesheet ),
			'themes'
		);
	}

	/**
	 * Option updated.
	 *
	 * @param string $option    Option name.
	 * @param mixed  $old_value Old value.
	 * @param mixed  $value     New value.
	 */
	public function on_updated_option( $option, $old_value, $value ) {
		$tracked_options = [
			'admin_email',
			'siteurl',
			'home',
			'permalink_structure',
			'blogname',
			'blogdescription',
		];

		if ( ! in_array( $option, $tracked_options, true ) ) {
			return;
		}

		$this->log(
			'updated_option',
			sprintf( 'Option "%s" updated', $option ),
			'options'
		);
	}
}

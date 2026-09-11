<?php
/**
 * Plugin update channels.
 *
 * @package  WordPressTools
 */

namespace WordPressTools;

use WordPressTools\Settings\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Updates class
 */
class Updates {

	/**
	 * Stable channel — released (non-prerelease) versions only.
	 *
	 * @var string
	 */
	public const CHANNEL_STABLE = 'stable';

	/**
	 * Beta channel — newest version including pre-releases.
	 *
	 * @var string
	 */
	public const CHANNEL_BETA = 'beta';

	/**
	 * Prefix for pinning a site to pre-releases from a single branch.
	 *
	 * @var string
	 */
	public const CHANNEL_BRANCH_PREFIX = 'branch:';

	/**
	 * Number of recent releases the channel filters examine.
	 *
	 * @var int
	 */
	private const FILTER_WINDOW = 20;

	/**
	 * Apply the site's update channel to the update checker.
	 *
	 * @param  object $update_checker Plugin update checker instance.
	 * @return void
	 */
	public static function apply_channel( $update_checker ) {
		$api = $update_checker->getVcsApi();

		$api->enableReleaseAssets();

		$channel = self::get_channel();

		if ( self::CHANNEL_STABLE === $channel ) {
			return;
		}

		// Read off the instance so a PUC version bump doesn't break a hardcoded namespace.
		$release_filter_all = constant( get_class( $api ) . '::RELEASE_FILTER_ALL' );

		if ( self::CHANNEL_BETA === $channel ) {
			$api->setReleaseFilter( '__return_true', $release_filter_all, self::FILTER_WINDOW );
			return;
		}

		$slug = self::get_channel_branch( $channel );

		if ( '' === $slug ) {
			return;
		}

		$api->setReleaseVersionFilter( self::branch_version_pattern( $slug ), $release_filter_all, self::FILTER_WINDOW );
	}

	/**
	 * Get the resolved update channel. Constant takes priority over DB value.
	 *
	 * @return string
	 */
	public static function get_channel() {
		if ( defined( 'WPT_UPDATE_CHANNEL' ) && ! empty( WPT_UPDATE_CHANNEL ) ) {
			return self::sanitize_channel( WPT_UPDATE_CHANNEL );
		}

		$settings = Settings::get_settings();

		return self::sanitize_channel( isset( $settings['update_channel'] ) ? $settings['update_channel'] : self::CHANNEL_STABLE );
	}

	/**
	 * Whether the channel is locked by the wp-config.php constant.
	 *
	 * @return bool
	 */
	public static function is_channel_locked() {
		return defined( 'WPT_UPDATE_CHANNEL' ) && ! empty( WPT_UPDATE_CHANNEL );
	}

	/**
	 * Normalize a channel value, falling back to stable when unrecognized.
	 *
	 * @param  mixed $channel Raw channel value.
	 * @return string
	 */
	public static function sanitize_channel( $channel ) {
		$channel = is_string( $channel ) ? trim( $channel ) : '';

		if ( self::CHANNEL_BETA === $channel ) {
			return self::CHANNEL_BETA;
		}

		$slug = self::branch_slug( self::get_channel_branch( $channel ) );

		if ( '' !== $slug ) {
			return self::CHANNEL_BRANCH_PREFIX . $slug;
		}

		return self::CHANNEL_STABLE;
	}

	/**
	 * Extract the branch portion of a `branch:<slug>` channel.
	 *
	 * @param  string $channel Channel value.
	 * @return string Branch slug, or an empty string for non-branch channels.
	 */
	public static function get_channel_branch( $channel ) {
		if ( ! is_string( $channel ) || 0 !== strpos( $channel, self::CHANNEL_BRANCH_PREFIX ) ) {
			return '';
		}

		return substr( $channel, strlen( self::CHANNEL_BRANCH_PREFIX ) );
	}

	/**
	 * Convert a branch name to a version-safe slug.
	 *
	 * Must stay in sync with npm-scripts/branch-slug.js, which builds the
	 * pre-release version strings this is matched against.
	 *
	 * @param  string $branch Branch name.
	 * @return string
	 */
	public static function branch_slug( $branch ) {
		if ( ! is_string( $branch ) ) {
			return '';
		}

		$slug = strtolower( $branch );
		$slug = preg_replace( '/[^a-z0-9]+/', '-', $slug );

		return trim( $slug, '-' );
	}

	/**
	 * Build the version pattern matching pre-releases from one branch.
	 *
	 * Anchored to the start so a slug can't match another branch that merely
	 * ends with the same characters (`updates` vs `mcp-updates`).
	 *
	 * @param  string $slug Branch slug.
	 * @return string
	 */
	public static function branch_version_pattern( $slug ) {
		return '/^\d+\.\d+\.\d+-' . preg_quote( $slug, '/' ) . '\./';
	}
}

<?php
/**
 * Limit Login Attempts functionality
 *
 * @package  WordPressTools
 */

namespace WordPressTools\Authentication;

use WordPressTools\Singleton;
use function WordPressTools\Utils\get_maybe_site_option;
use function WordPressTools\Utils\get_ip_address;

/**
 * Limit Login class
 */
class LimitLogin {

	use Singleton;

	/**
	 * Time window for attempts in seconds (5 minutes)
	 *
	 * @var int
	 */
	const ATTEMPT_WINDOW = 300;

	/**
	 * Setup module
	 */
	public function setup() {
		// Only run if limit login is enabled
		if ( ! $this->is_enabled() ) {
			return;
		}

		add_filter( 'authenticate', [ $this, 'check_login_attempts' ], 30, 3 );
		add_action( 'wp_login_failed', [ $this, 'record_failed_attempt' ] );
		add_action( 'wp_login', [ $this, 'clear_attempts' ], 10, 2 );
	}

	/**
	 * Check if limit login is enabled
	 *
	 * @return bool
	 */
	protected function is_enabled() {
		// Check if disabled via constant
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound
		if ( defined( 'WPT_DISABLE_LIMIT_LOGIN' ) && constant( 'WPT_DISABLE_LIMIT_LOGIN' ) ) {
			return false;
		}

		$enabled = get_maybe_site_option( 'wpt_limit_login', 'yes' );
		return 'yes' === $enabled;
	}

	/**
	 * Get transient key for IP address
	 *
	 * @param string $ip IP address.
	 * @return string
	 */
	protected function get_transient_key( $ip ) {
		return 'wpt_login_attempts_' . md5( $ip );
	}

	/**
	 * Get login attempts for IP
	 *
	 * @param string $ip IP address.
	 * @return array
	 */
	protected function get_attempts( $ip ) {
		$key      = $this->get_transient_key( $ip );
		$attempts = get_transient( $key );

		if ( ! is_array( $attempts ) ) {
			$attempts = [
				'count'        => 0,
				'first_time'   => time(),
				'locked_until' => 0,
			];
		}

		return $attempts;
	}

	/**
	 * Save login attempts for IP
	 *
	 * @param string $ip IP address.
	 * @param array  $attempts Attempts data.
	 */
	protected function save_attempts( $ip, $attempts ) {
		$key = $this->get_transient_key( $ip );
		set_transient( $key, $attempts, self::ATTEMPT_WINDOW + WPT_LOGIN_LOCKOUT_DURATION );
	}

	/**
	 * Check if IP is currently locked out
	 *
	 * @param \WP_User|\WP_Error|null $user User object or error.
	 * @param string                  $username Username.
	 * @param string                  $password Password.
	 * @return \WP_User|\WP_Error
	 */
	public function check_login_attempts( $user, $username, $password ) {
		// Skip if no username provided
		if ( empty( $username ) ) {
			return $user;
		}

		$ip       = get_ip_address();
		$attempts = $this->get_attempts( $ip );

		// Check if currently locked out
		if ( $attempts['locked_until'] > time() ) {
			$minutes_remaining = ceil( ( $attempts['locked_until'] - time() ) / 60 );

			return new \WP_Error(
				'too_many_attempts',
				sprintf(
					/* translators: %d: minutes remaining */
					__( '<strong>Error:</strong> Too many failed login attempts. Please try again in %d minutes.', 'wordpress-tools' ),
					$minutes_remaining
				)
			);
		}

		// Check if attempts are within the time window
		if ( 0 < $attempts['count'] && ( time() - $attempts['first_time'] ) > self::ATTEMPT_WINDOW ) {
			// Time window expired, reset attempts
			$attempts = [
				'count'        => 0,
				'first_time'   => time(),
				'locked_until' => 0,
			];
			$this->save_attempts( $ip, $attempts );
		}

		return $user;
	}

	/**
	 * Record a failed login attempt
	 *
	 * @param string $username Username.
	 */
	public function record_failed_attempt( $username ) {
		$ip       = get_ip_address();
		$attempts = $this->get_attempts( $ip );

		// If IP is locked out, don't record another attempt
		if ( ! empty( $attempts ) && ! empty( $attempts['locked_until'] ) && $attempts['locked_until'] > time() ) {
			return;
		}

		// Increment attempt count
		++$attempts['count'];

		// If first attempt in this window, set the start time
		if ( 1 === $attempts['count'] ) {
			$attempts['first_time'] = time();
		}

		// Check if we've exceeded the limit
		if ( $attempts['count'] >= WPT_LOGIN_ATTEMPT_LIMIT ) {
			$attempts['locked_until'] = time() + WPT_LOGIN_LOCKOUT_DURATION;

			// Log the lockout
			do_action( 'wpt_login_lockout', $ip, $username );
		}

		$this->save_attempts( $ip, $attempts );
	}

	/**
	 * Clear attempts after successful login
	 *
	 * @param string   $username Username.
	 * @param \WP_User $user User object.
	 */
	public function clear_attempts( $username, $user ) {
		$ip  = get_ip_address();
		$key = $this->get_transient_key( $ip );
		delete_transient( $key );
	}
}

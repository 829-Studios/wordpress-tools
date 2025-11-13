<?php
/**
 * Force strong password extension functionality
 *
 * @package  WordPressTools
 */

namespace WordPressTools\Authentication;

use WordPressTools\Singleton;
use ZxcvbnPhp\Zxcvbn;
use function WordPressTools\Utils\get_maybe_site_option;

/**
 * Password extension functionality
 */
class Passwords {

	use Singleton;

	/**
	 * Stores the Have I Been Pwned API URL
	 */
	const HIBP_API_URL   = 'https://api.pwnedpasswords.com/range/';
	const HIBP_CACHE_KEY = 'wpt_hibp';

	/**
	 * Setup hooks
	 *
	 * @since 1.7
	 */
	public function setup() {
		// If Force Strong Passwords plugin is active, bail.
		if ( function_exists( 'slt_fsp_init' ) ) {
			return;
		}

		if ( (bool) get_maybe_site_option( 'wpt_require_strong_passwords', 0 ) ) {
			add_action( 'user_profile_update_errors', [ $this, 'validate_profile_update' ], 0, 3 );
			add_action( 'validate_password_reset', [ $this, 'validate_strong_password' ], 10, 2 );
			add_action( 'resetpass_form', [ $this, 'validate_resetpass_form' ], 10 );
			add_filter( 'authenticate', [ $this, 'prevent_weak_password_auth' ], 30, 3 );
		}
	}

	/**
	 * Prevent users from authenticating if they are using a weak password
	 *
	 * @param WP_User $user User object
	 * @param string  $username Username
	 * @param string  $password Password
	 * @since 1.7
	 * @return \WP_User|\WP_Error
	 */
	public function prevent_weak_password_auth( $user, $username, $password ) {
		$test_tlds = array( 'test', 'local', '' );
		$tld       = preg_replace( '#^.*\.(.*)$#', '$1', wp_parse_url( site_url(), PHP_URL_HOST ) );

		if ( ! in_array( $tld, $test_tlds, true ) && in_array( strtolower( trim( $password ) ), $this->weak_passwords(), true ) ) {
			return new \WP_Error(
				'Auth Error',
				sprintf(
					'%s <a href="%s">%s</a> %s',
					esc_html__( 'Please', 'wordpress-tools' ),
					esc_url( wp_lostpassword_url() ),
					esc_html__( 'reset your password', 'wordpress-tools' ),
					esc_html__( 'in order to meet current security measures.', 'wordpress-tools' )
				)
			);
		}

		return $user;
	}

	/**
	 * List of popular weak passwords
	 *
	 * @since 1.7
	 * @return array
	 */
	public function weak_passwords() {
		return array(
			'123456',
			'Password',
			'password',
			'12345678',
			'qwerty',
			'12345',
			'123456789',
			'letmein',
			'1234567',
			'football',
			'iloveyou',
			'admin',
			'welcome',
			'monkey',
			'login',
			'abc123',
			'starwars',
			'123123',
			'dragon',
			'passw0rd',
			'master',
			'hello',
			'freedom',
			'whatever',
			'qazwsx',
			'trustno1',
			'654321',
			'jordan23',
			'harley',
			'password1',
			'1234',
			'robert',
			'matthew',
			'jordan',
			'daniel',
		);
	}



	/**
	 * Check user profile update and throw an error if the password isn't strong.
	 *
	 * @param WP_Error $errors Current potential password errors
	 * @param boolean  $update Whether PW update or not
	 * @param WP_User  $user_data User being handled
	 * @since 1.7
	 * @return WP_Error
	 */
	public function validate_profile_update( $errors, $update, $user_data ) {
		return $this->validate_strong_password( $errors, $user_data );
	}

	/**
	 * Check password reset form and throw an error if the password isn't strong.
	 *
	 * @param WP_User $user_data User being handled
	 * @since 1.7
	 * @return WP_Error
	 */
	public function validate_resetpass_form( $user_data ) {
		return $this->validate_strong_password( false, $user_data );
	}


	/**
	 * Functionality used by both user profile and reset password validation.
	 *
	 * @param WP_Error $errors Current potential password errors
	 * @param WP_User  $user_data User being handled
	 * @since 1.7
	 * @return WP_Error
	 */
	public function validate_strong_password( $errors, $user_data ) {
		$password_ok = true;
		$enforce     = true;
		// This is being sanitized later in the function, no need to sanitize for isset().
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$password = ( isset( $_POST['pass1'] ) && trim( $_POST['pass1'] ) ) ? sanitize_text_field( $_POST['pass1'] ) : false;
		$role     = isset( $_POST['role'] ) ? sanitize_text_field( $_POST['role'] ) : false;
		$user_id  = isset( $user_data->ID ) ? sanitize_text_field( $user_data->ID ) : false;
		$username = isset( $_POST['user_login'] ) ? sanitize_text_field( $_POST['user_login'] ) : $user_data->user_login;

		// No password set?
		// Already got a password error?
		if ( ( false === $password ) || ( is_wp_error( $errors ) && $errors->get_error_data( 'pass' ) ) ) {
			return $errors;
		}

		// Validate the password against the Have I Been Pwned API.
		if ( ! $this->is_password_secure( $password ) && is_wp_error( $errors ) ) {
			$errors->add( 'password_reset_error', __( '<strong>ERROR:</strong> The password entered may have been included in a data breach and is not considered safe to use. Please choose another.', 'wordpress-tools' ) );
		}

		// Should a strong password be enforced for this user?
		if ( $user_id ) {

			// User ID specified.
			$enforce = $this->enforce_for_user( $user_id );

		} elseif ( $role && in_array( $role, apply_filters( 'wpt_weak_roles', array( 'subscriber' ) ), true ) ) {
			$enforce = false;
		}

		// Enforce?
		if ( $enforce ) {
			// Zxcbn requires the mbstring PHP extension and min PHP 7.2, so we'll need to check for it before using
			if ( function_exists( 'mb_ord' ) && version_compare( PHP_VERSION, '7.2.0' ) >= 0 ) {
				$zxcvbn = new Zxcvbn();

				$pw = $zxcvbn->passwordStrength( $password );

				if ( 3 > (int) $pw['score'] ) {
					$password_ok = false;
				}
			}
		}

		if ( ! $password_ok && is_wp_error( $errors ) ) {
			$errors->add( 'pass', apply_filters( 'wpt_password_error_message', __( '<strong>ERROR</strong>: Password must be medium strength or greater.', 'wordpress-tools' ) ) );
		}

		return $errors;
	}


	/**
	 * Check whether the given WP user should be forced to have a strong password
	 *
	 * @since   1.7
	 * @param   int $user_id A user ID.
	 * @return  boolean
	 */
	public function enforce_for_user( $user_id ) {
		$enforce = true;

		// Force strong passwords from network admin screens.
		if ( is_network_admin() ) {
			return $enforce;
		}

		$check_caps = apply_filters(
			'wpt_strong_password_caps',
			[
				'edit_posts',
			]
		);

		if ( ! empty( $check_caps ) ) {
			$enforce = false; // Now we won't enforce unless the user has one of the caps specified.

			foreach ( $check_caps as $cap ) {
				if ( user_can( $user_id, $cap ) ) {
					$enforce = true;
					break;
				}
			}
		}

		return $enforce;
	}

	/**
	 * Check if password is secure by querying the Have I Been Pwned API.
	 *
	 * @param string $password Password to validate.
	 *
	 * @return bool True if password is ok, false if it shows up in a breach.
	 */
	protected function is_password_secure( $password ): bool {
		// Default
		$is_password_secure = true;

		// Allow opt-out of Have I Been Pwned check through a constant or filter.
		if (
			( defined( 'WPT_DISABLE_HIBP' ) && constant( 'WPT_DISABLE_HIBP' ) ) ||
			apply_filters( 'wpt_disable_hibp', false, $password )
		) {
			return true;
		}

		$hash   = strtoupper( sha1( $password ) );
		$prefix = substr( $hash, 0, 5 );
		$suffix = substr( $hash, 5 );

		$cached_result = wp_cache_get( $prefix . $suffix, self::HIBP_CACHE_KEY );

		if ( false !== $cached_result || false ) { // remove || false; only used for testing
			return $cached_result;
		}

		$response = wp_remote_get( self::HIBP_API_URL . $prefix, [ 'user-agent' => '829 Studios WordPress Tools' ] );

		// Allow for a failed request to the HIPB API.
		// Don't cache the result if the request failed.
		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
			return true;
		}

		$body = wp_remote_retrieve_body( $response );

		// Allow for a failed request to the HIPB API.
		// Don't cache the result if the request failed.
		if ( is_wp_error( $body ) ) {
			return true;
		}

		$lines = explode( "\r\n", $body );

		foreach ( $lines as $line ) {
			$parts = explode( ':', $line );

			// If the suffix is found in the response, the password may be in a breach.
			if ( $parts[0] === $suffix ) {
				$is_password_secure = false;
			}
		}

		// Cache the result for 4 hours.
		wp_cache_set( $prefix . $suffix, (int) $is_password_secure, self::HIBP_CACHE_KEY, 60 * 60 * 4 );

		return $is_password_secure;
	}
}

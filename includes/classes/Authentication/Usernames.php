<?php
/**
 * Username functionality
 *
 * @package  WordPressTools
 */

namespace WordPressTools\Authentication;

use function WordPressTools\Utils\is_local_environment;

use WordPressTools\Singleton;
/**
 * Username extension functionality
 */
class Usernames {

	use Singleton;

	/**
	 * Setup hooks
	 *
	 * @since 1.7
	 */
	public function setup() {
		add_filter( 'authenticate', [ $this, 'prevent_common_username' ], 30, 3 );
	}

	/**
	 * Prevent users from authenticating if they are using a generic username
	 *
	 * @param WP_User $user User object
	 * @param string  $username Username
	 *
	 * @return \WP_User|\WP_Error
	 */
	public function prevent_common_username( $user, $username ) {
		if ( ! is_local_environment() && in_array( strtolower( trim( $username ) ), $this->reserved_usernames(), true ) ) {
			return new \WP_Error(
				'Auth Error',
				__( 'Please have an administrator change your username in order to meet current security measures.', 'wordpress-tools' )
			);
		}

		return $user;
	}

	/**
	 * List of reserved usernames
	 *
	 * @return array
	 */
	public function reserved_usernames() {
		$common_usernames = array(
			'abuse',
			'admin',
			'billing',
			'compliance',
			'devnull',
			'dns',
			'ftp',
			'hostmaster',
			'inoc',
			'ispfeedback',
			'ispsupport',
			'list-request',
			'list',
			'maildaemon',
			'noc',
			'no-reply',
			'noreply',
			'null',
			'phish',
			'phishing',
			'postmaster',
			'privacy',
			'registrar',
			'root',
			'security',
			'spam',
			'support',
			'sysadmin',
			'tech',
			'undisclosed-recipients',
			'unsubscribe',
			'usenet',
			'uucp',
			'webmaster',
			'www',
			'administrator',
			'user',
			'username',
			'demo',
			'sql',
			'guest',
			'test',
			'mysql',
			'client',
			'access',
			'backup',
			'blog',
			'business',
			'contact',
			'data',
			'doctor',
			'info',
			'information',
			'internet',
			'login',
			'manager',
			'marketing',
			'master',
			'number',
			'office',
			'pass',
			'password',
			'public',
			'sales',
			'server',
			'service',
			'tester',
			'user2',
		);

		return apply_filters( 'wpt_reserved_usernames', $common_usernames );
	}
}

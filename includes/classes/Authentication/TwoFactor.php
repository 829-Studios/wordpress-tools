<?php
/**
 * Two-Factor Authentication enforcement for non-SSO accounts.
 *
 * Requires the Two-Factor plugin: https://github.com/WordPress/two-factor
 *
 * @package  wordpress-tools
 */

namespace WordPressTools\Authentication;

use WordPressTools\Singleton;
use function WordPressTools\Utils\is_829_user;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * TwoFactor class
 *
 * Enforces 2FA requirement for non-@829llc.com email accounts.
 * 829 Studios accounts use SSO which has its own authentication security.
 */
class TwoFactor {

	use Singleton;

	/**
	 * Capabilities ALLOWED for users without 2FA.
	 * Everything else is blocked.
	 *
	 * edit_user/edit_users deliberately excluded - allow-listing them by
	 * name would let a 2FA-less admin edit ANY user, not just themselves.
	 *
	 * @var array
	 */
	const ALLOWED_CAPABILITIES = [
		'read',
		'level_0',
		'exist', // Internal WP capability.
	];

	/**
	 * admin-ajax.php actions ALLOWED for users without 2FA.
	 *
	 * @var array
	 */
	const ALLOWED_AJAX_ACTIONS = [
		'heartbeat',
		'destroy-sessions', // "Log Out Everywhere Else" button on profile.php.
	];

	/**
	 * Two-Factor plugin's own usermeta key for enabled providers.
	 *
	 * @var string
	 */
	const ENABLED_PROVIDERS_META_KEY = '_two_factor_enabled_providers';

	/**
	 * Usermeta keys used to hold a 2FA setup pending email confirmation.
	 *
	 * @var string
	 */
	const PENDING_PROVIDERS_META_KEY = '_two_factor_pending_providers';
	const PENDING_TOKEN_META_KEY     = '_two_factor_pending_token';
	const PENDING_EXPIRES_META_KEY   = '_two_factor_pending_expires';

	/**
	 * Query var marking a 2FA confirmation link.
	 *
	 * @var string
	 */
	const CONFIRM_QUERY_VAR = 'wpt_confirm_2fa';

	/**
	 * Setup 2FA enforcement.
	 */
	public function __construct() {
		// Only run if Two-Factor plugin is active.
		if ( ! $this->is_two_factor_plugin_active() ) {
			return;
		}

		add_filter( 'map_meta_cap', [ $this, 'restrict_capabilities_without_2fa' ], 10, 4 );
		add_filter( 'wp_pre_insert_user_data', [ $this, 'prevent_email_change_without_2fa' ], 10, 4 );
		add_action( 'user_profile_update_errors', [ $this, 'block_email_change_on_profile_save' ], 10, 3 );
		add_filter( 'update_user_metadata', [ $this, 'require_email_confirmation_for_2fa_setup' ], 10, 5 );
		add_action( 'init', [ $this, 'maybe_confirm_2fa_setup' ] );
		add_action( 'admin_notices', [ $this, 'show_2fa_required_notice' ] );
		add_action( 'admin_init', [ $this, 'redirect_to_profile_if_no_2fa' ] );
	}

	/**
	 * Check if the Two-Factor plugin is active.
	 *
	 * @return bool
	 */
	public function is_two_factor_plugin_active(): bool {
		return class_exists( 'Two_Factor_Core' );
	}

	/**
	 * Check if a user requires 2FA (non-SSO users).
	 *
	 * SSO users (@829llc.com) don't need 2FA - they use SSO authentication.
	 *
	 * @param int $user_id The user ID.
	 * @return bool
	 */
	public function user_requires_2fa( int $user_id ): bool {
		return ! is_829_user( $user_id );
	}

	/**
	 * Check if a user has 2FA enabled via the Two-Factor plugin.
	 *
	 * @param int $user_id The user ID.
	 * @return bool
	 */
	public function user_has_2fa_enabled( int $user_id ): bool {
		if ( ! class_exists( 'Two_Factor_Core' ) ) {
			return true; // If plugin isn't available, don't restrict.
		}

		// Get enabled providers for the user.
		$enabled_providers = get_user_meta( $user_id, '_two_factor_enabled_providers', true );

		// Check if user has any enabled providers (excluding backup codes as primary).
		if ( ! is_array( $enabled_providers ) || empty( $enabled_providers ) ) {
			return false;
		}

		// Filter out backup codes - they shouldn't count as the only 2FA method.
		$primary_providers = array_filter(
			$enabled_providers,
			function ( $provider ) {
				return 'Two_Factor_Backup_Codes' !== $provider;
			}
		);

		return ! empty( $primary_providers );
	}

	/**
	 * Check if the current user needs 2FA setup.
	 *
	 * @return bool
	 */
	public function current_user_needs_2fa(): bool {
		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			return false;
		}

		// User needs 2FA if they require it AND don't have it enabled.
		return $this->user_requires_2fa( $user_id ) && ! $this->user_has_2fa_enabled( $user_id );
	}

	/**
	 * Restrict capabilities for non-SSO users without 2FA using map_meta_cap.
	 *
	 * Uses an allow-list approach: only allowed capabilities pass through,
	 * everything else is blocked.
	 *
	 * @param array  $caps    The required primitive capabilities.
	 * @param string $cap     The capability being checked.
	 * @param int    $user_id The user ID.
	 * @param array  $args    Additional arguments passed to the capability check.
	 * @return array
	 */
	public function restrict_capabilities_without_2fa( $caps, $cap, $user_id, $args ) {
		// Skip if no user ID or capability.
		if ( ! $user_id || ! is_string( $cap ) ) {
			return $caps;
		}

		// Skip restriction if user doesn't require 2FA or already has it enabled.
		if ( ! $this->user_requires_2fa( $user_id ) || $this->user_has_2fa_enabled( $user_id ) ) {
			return $caps;
		}

		// Allow editing own profile only (needed to set up 2FA).
		if ( 'edit_user' === $cap && ! empty( $args[0] ) && (int) $args[0] === $user_id ) {
			return $caps;
		}

		// Only allow listed capabilities, block everything else.
		if ( ! in_array( $cap, self::ALLOWED_CAPABILITIES, true ) ) {
			return [ 'do_not_allow' ];
		}

		return $caps;
	}

	/**
	 * Block a user from changing their OWN email while 2FA is required
	 * and not enabled - enforced at the data layer since is_829_user()
	 * (and thus the 2FA requirement itself) keys off user_email, and
	 * REST/ajax/CLI can all reach wp_update_user() directly.
	 *
	 * Must return an array, not a WP_Error - core doesn't check for one
	 * here - so a blocked change is silently reverted instead of erroring.
	 *
	 * @param array    $data     User data about to be saved to the DB.
	 * @param bool     $update   Whether this is an update (vs a new user).
	 * @param int|null $user_id  ID of the user being updated, or null on create.
	 * @param array    $userdata Raw data passed to wp_insert_user().
	 * @return array
	 */
	public function prevent_email_change_without_2fa( $data, $update, $user_id, $userdata ) {
		if ( ! $update || ! $user_id || empty( $data['user_email'] ) ) {
			return $data;
		}

		// Editing another user's email is already blocked at the capability layer.
		if ( get_current_user_id() !== (int) $user_id ) {
			return $data;
		}

		if ( ! $this->user_requires_2fa( $user_id ) || $this->user_has_2fa_enabled( $user_id ) ) {
			return $data;
		}

		$existing_user = get_userdata( $user_id );

		if ( $existing_user && strtolower( $existing_user->user_email ) !== strtolower( $data['user_email'] ) ) {
			$data['user_email'] = $existing_user->user_email;
		}

		return $data;
	}

	/**
	 * Surface a visible error on wp-admin profile saves (REST/ajax/CLI
	 * still fall back to the silent revert above).
	 *
	 * @param \WP_Error $errors Validation errors, added to by reference.
	 * @param bool      $update Whether this is an update.
	 * @param \stdClass $user   The user object being saved (by reference).
	 * @return void
	 */
	public function block_email_change_on_profile_save( $errors, $update, $user ) {
		if ( ! $update || empty( $user->ID ) ) {
			return;
		}

		if ( get_current_user_id() !== (int) $user->ID ) {
			return;
		}

		if ( ! $this->user_requires_2fa( $user->ID ) || $this->user_has_2fa_enabled( $user->ID ) ) {
			return;
		}

		$existing_user = get_userdata( $user->ID );

		if ( $existing_user && ! empty( $user->user_email ) && strtolower( $existing_user->user_email ) !== strtolower( $user->user_email ) ) {
			$errors->add(
				'2fa_required_email_locked',
				__( 'You must set up Two-Factor Authentication before you can change your email address.', 'wordpress-tools' )
			);
		}
	}

	/**
	 * Hold first-time 2FA setup for email confirmation, so a password
	 * compromise alone can't let someone else lock in their own 2FA.
	 *
	 * @param mixed  $check     Short-circuit value; non-null skips the real write.
	 * @param int    $object_id User being updated.
	 * @param string $meta_key  Meta key being written.
	 * @param mixed  $meta_value New value.
	 * @param mixed  $prev_value Previous value filter arg (unused).
	 * @return mixed
	 */
	public function require_email_confirmation_for_2fa_setup( $check, $object_id, $meta_key, $meta_value, $prev_value ) {
		if ( self::ENABLED_PROVIDERS_META_KEY !== $meta_key || ! $object_id ) {
			return $check;
		}

		// Only gate first-time setup - not disabling, or an already-confirmed user changing providers.
		if ( ! $this->user_requires_2fa( $object_id ) || $this->user_has_2fa_enabled( $object_id ) ) {
			return $check;
		}

		$new_providers = is_array( $meta_value ) ? array_filter( $meta_value ) : [];

		if ( empty( $new_providers ) ) {
			return $check;
		}

		$token = wp_generate_password( 32, false );

		update_user_meta( $object_id, self::PENDING_PROVIDERS_META_KEY, $new_providers );
		update_user_meta( $object_id, self::PENDING_TOKEN_META_KEY, hash_hmac( 'sha256', $token, wp_salt() ) );
		update_user_meta( $object_id, self::PENDING_EXPIRES_META_KEY, time() + DAY_IN_SECONDS );

		$this->send_2fa_confirmation_email( $object_id, $token );

		// Pretend the write succeeded (profile.php just shows "Profile updated") without actually enabling 2FA yet.
		return true;
	}

	/**
	 * Email the account's on-file address a link to confirm the pending 2FA setup.
	 *
	 * @param int    $user_id User ID.
	 * @param string $token   Raw confirmation token.
	 * @return void
	 */
	private function send_2fa_confirmation_email( int $user_id, string $token ): void {
		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return;
		}

		$confirm_url = add_query_arg(
			[
				self::CONFIRM_QUERY_VAR => 1,
				'user_id'               => $user_id,
				'token'                 => $token,
			],
			wp_login_url()
		);

		wp_mail(
			$user->user_email,
			__( 'Confirm Your Two-Factor Authentication Setup', 'wordpress-tools' ),
			sprintf(
				/* translators: 1: site name, 2: confirmation URL */
				__( "Two-Factor Authentication was just set up on your %1\$s account.\n\nIf this was you, confirm it here (expires in 24 hours):\n%2\$s\n\nIf this wasn't you, your password may be compromised - change it immediately and contact your site administrator.", 'wordpress-tools' ),
				get_bloginfo( 'name' ),
				$confirm_url
			)
		);
	}

	/**
	 * Handle a clicked 2FA confirmation link.
	 *
	 * @return void
	 */
	public function maybe_confirm_2fa_setup(): void {
		if ( empty( $_GET[ self::CONFIRM_QUERY_VAR ] ) || empty( $_GET['user_id'] ) || empty( $_GET['token'] ) ) {
			return;
		}

		$user_id = absint( $_GET['user_id'] );
		$token   = sanitize_text_field( wp_unslash( $_GET['token'] ) );

		$stored_hash = get_user_meta( $user_id, self::PENDING_TOKEN_META_KEY, true );

		if ( ! $stored_hash || ! hash_equals( $stored_hash, hash_hmac( 'sha256', $token, wp_salt() ) ) ) {
			wp_die( esc_html__( 'This confirmation link is invalid.', 'wordpress-tools' ) );
		}

		if ( time() > (int) get_user_meta( $user_id, self::PENDING_EXPIRES_META_KEY, true ) ) {
			$this->clear_pending_2fa_setup( $user_id );
			wp_die( esc_html__( 'This confirmation link has expired. Please set up 2FA again from your profile.', 'wordpress-tools' ) );
		}

		$providers = get_user_meta( $user_id, self::PENDING_PROVIDERS_META_KEY, true );

		if ( empty( $providers ) || ! is_array( $providers ) ) {
			wp_die( esc_html__( 'Nothing to confirm.', 'wordpress-tools' ) );
		}

		// Bypass our own gate above to actually write the real, now-confirmed value.
		remove_filter( 'update_user_metadata', [ $this, 'require_email_confirmation_for_2fa_setup' ], 10 );
		update_user_meta( $user_id, self::ENABLED_PROVIDERS_META_KEY, $providers );
		add_filter( 'update_user_metadata', [ $this, 'require_email_confirmation_for_2fa_setup' ], 10, 5 );

		$this->clear_pending_2fa_setup( $user_id );

		wp_die(
			esc_html__( 'Two-Factor Authentication has been confirmed and is now active on your account.', 'wordpress-tools' ),
			esc_html__( '2FA Confirmed', 'wordpress-tools' ),
			[ 'response' => 200 ]
		);
	}

	/**
	 * Clear any pending (unconfirmed) 2FA setup for a user.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	private function clear_pending_2fa_setup( int $user_id ): void {
		delete_user_meta( $user_id, self::PENDING_PROVIDERS_META_KEY );
		delete_user_meta( $user_id, self::PENDING_TOKEN_META_KEY );
		delete_user_meta( $user_id, self::PENDING_EXPIRES_META_KEY );
	}

	/**
	 * Show admin notice for users who need to set up 2FA.
	 *
	 * @return void
	 */
	public function show_2fa_required_notice(): void {
		if ( ! $this->current_user_needs_2fa() ) {
			return;
		}

		$user_id = get_current_user_id();

		if ( get_user_meta( $user_id, self::PENDING_TOKEN_META_KEY, true ) ) {
			?>
			<div class="notice notice-warning">
				<p>
					<strong><?php esc_html_e( 'Two-Factor Authentication Pending Confirmation', 'wordpress-tools' ); ?></strong>
				</p>
				<p>
					<?php esc_html_e( 'A confirmation email has been sent to your account\'s email address. Click the link in that email to activate 2FA - your account capabilities remain restricted until then.', 'wordpress-tools' ); ?>
				</p>
			</div>
			<?php
			return;
		}

		$profile_url = admin_url( 'profile.php#two-factor-options' );
		?>
		<div class="notice notice-error">
			<p>
				<strong><?php esc_html_e( 'Two-Factor Authentication Required', 'wordpress-tools' ); ?></strong>
			</p>
			<p>
				<?php
				printf(
					/* translators: %s: profile URL */
					wp_kses(
						__( 'This site requires Two-Factor Authentication. Your account capabilities are restricted until you <a href="%s">set up 2FA in your profile</a>.', 'wordpress-tools' ),
						[ 'a' => [ 'href' => [] ] ]
					),
					esc_url( $profile_url )
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Redirect users without 2FA to their profile page.
	 *
	 * @return void
	 */
	public function redirect_to_profile_if_no_2fa(): void {
		if ( ! $this->current_user_needs_2fa() ) {
			return;
		}

		global $pagenow;

		// Allow access to the profile page so the user can set up 2FA.
		if ( 'profile.php' === $pagenow ) {
			return;
		}

		// Only allow the specific ajax actions profile.php needs - not all of admin-ajax.php.
		if ( 'admin-ajax.php' === $pagenow ) {
			$action = isset( $_REQUEST['action'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['action'] ) ) : '';

			if ( in_array( $action, self::ALLOWED_AJAX_ACTIONS, true ) ) {
				return;
			}
		}

		// Redirect to profile page.
		wp_safe_redirect( admin_url( 'profile.php' ) );
		exit;
	}
}


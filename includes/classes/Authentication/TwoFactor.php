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
	 * @var array
	 */
	const ALLOWED_CAPABILITIES = [
		'read',
		'level_0',
		'exist', // Internal WP capability.
		// Profile editing - needed so user can set up 2FA.
		'edit_user',
		'edit_users', // Required for editing own profile in some contexts.
	];

	/**
	 * Setup 2FA enforcement.
	 */
	public function __construct() {
		// Only run if Two-Factor plugin is active.
		if ( ! $this->is_two_factor_plugin_active() ) {
			return;
		}

		add_filter( 'map_meta_cap', [ $this, 'restrict_capabilities_without_2fa' ], 10, 4 );
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
	 * Uses a whitelist approach: only allowed capabilities pass through,
	 * everything else is blocked.
	 *
	 * @param array  $caps    The required primitive capabilities.
	 * @param string $cap     The capability being checked.
	 * @param int    $user_id The user ID.
	 * @param array  $args    Additional arguments passed to the capability check.
	 * @return array
	 */
	public function restrict_capabilities_without_2fa( array $caps, string $cap, int $user_id, array $args ): array {
		// Skip if no user ID.
		if ( ! $user_id ) {
			return $caps;
		}

		// Skip restriction if user doesn't require 2FA or already has it enabled.
		if ( ! $this->user_requires_2fa( $user_id ) || $this->user_has_2fa_enabled( $user_id ) ) {
			return $caps;
		}

		// Allow user to edit their own profile (needed to set up 2FA).
		if ( 'edit_user' === $cap && ! empty( $args[0] ) && (int) $args[0] === $user_id ) {
			return $caps;
		}

		// Only allow whitelisted capabilities, block everything else.
		if ( ! in_array( $cap, self::ALLOWED_CAPABILITIES, true ) ) {
			return [ 'do_not_allow' ];
		}

		return $caps;
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

		$profile_url = admin_url( 'profile.php' );
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

		// Allow access to profile page so they can set up 2FA.
		$allowed_pages = [ 'profile.php', 'admin-ajax.php' ];

		if ( in_array( $pagenow, $allowed_pages, true ) ) {
			return;
		}

		// Redirect to profile page.
		wp_safe_redirect( admin_url( 'profile.php' ) );
		exit;
	}
}


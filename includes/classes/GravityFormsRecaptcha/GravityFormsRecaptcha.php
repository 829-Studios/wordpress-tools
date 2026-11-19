<?php
/**
 * Hide other clients' reCAPTCHA projects and keys from non-829 users.
 *
 * @package  WordPressTools
 */

namespace WordPressTools\GravityFormsRecaptcha;

use WordPressTools\Singleton;
use WordPressTools\Settings\Settings;
use function WordPressTools\Utils\is_829_user;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Keeps non-829 users to this site's saved reCAPTCHA setup. The add-on lists every
 * project and key on 829's Google account and has no filter, so its API responses are filtered.
 */
class GravityFormsRecaptcha {

	use Singleton;

	const PROJECTS_URL = 'https://cloudresourcemanager.googleapis.com/v1/projects';

	const KEYS_URL_PATTERN = '#^https://recaptchaenterprise\.googleapis\.com/v1/projects/([^/?]+)/keys#';

	const ADDON_SLUG = 'gravityformsrecaptcha';

	const SECRET_KEY_FIELDS = [ 'secret_key_v3', 'secret_key_v2' ];

	const SECRET_MASK = '••••••••••••••••';

	/**
	 * Setup module
	 */
	public function setup() {
		if ( ! function_exists( 'gf_recaptcha' ) || ! Settings::get_settings()['restrict_gf_recaptcha'] ) {
			return;
		}

		add_filter( 'http_response', [ $this, 'filter_google_lists' ], 10, 3 );

		// Priority 9: before the add-on's handlers.
		add_action( 'wp_ajax_get_enterprise_site_keys', [ $this, 'reject_other_project_keys' ], 9 );
		add_action( 'wp_ajax_save_recaptcha_enterprise_data', [ $this, 'reject_other_project_save' ], 9 );
		add_action( 'wp_ajax_disconnect_recaptcha', [ $this, 'reject_setup_change' ], 9 );
		add_action( 'wp_ajax_update_reload_settings', [ $this, 'reject_setup_change' ], 9 );
		add_action( 'wp_ajax_perform_enterprise_oauth', [ $this, 'reject_setup_change' ], 9 );

		// Priority 0: before the verify_secret_key AJAX handler and the settings save read $_POST.
		add_action( 'admin_init', [ $this, 'lock_posted_secret_keys' ], 0 );
		add_action( 'admin_init', [ $this, 'mask_secret_key_fields' ] );
	}

	/**
	 * Whether this request is for the reCAPTCHA add-on's settings page.
	 *
	 * @return bool
	 */
	protected function is_settings_page() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$page    = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		$subview = isset( $_GET['subview'] ) ? sanitize_key( wp_unslash( $_GET['subview'] ) ) : '';
		// phpcs:enable

		return 'gf_settings' === $page && self::ADDON_SLUG === $subview;
	}

	/**
	 * Overwrite any posted Classic/v2 secret with the saved one for non-829 users. The add-on
	 * saves whatever is posted, including from the verify_secret_key call on every page load.
	 */
	public function lock_posted_secret_keys() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Only replaces values; the add-on verifies its own nonces.
		if ( empty( $_POST ) || ! $this->should_restrict() ) {
			return;
		}

		if ( wp_doing_ajax() ) {
			$action = isset( $_POST['action'] ) ? sanitize_key( wp_unslash( $_POST['action'] ) ) : '';

			if ( 'verify_secret_key' !== $action ) {
				return;
			}

			$settings = gf_recaptcha()->get_plugin_settings();

			if ( '' !== (string) rgar( $settings, 'secret_key_v3' ) ) {
				$_POST['secret_key_v3'] = wp_slash( (string) rgar( $settings, 'secret_key_v3' ) );
			}

			return;
		}

		if ( ! $this->is_settings_page() ) {
			return;
		}

		$settings = gf_recaptcha()->get_plugin_settings();

		// Only a saved secret is locked, so a site with none can still be set up.
		foreach ( self::SECRET_KEY_FIELDS as $name ) {
			if ( '' !== (string) rgar( $settings, $name ) ) {
				$_POST[ '_gform_setting_' . $name ] = wp_slash( (string) rgar( $settings, $name ) );
			}
		}
		// phpcs:enable
	}

	/**
	 * Swap the Classic/v2 secret key inputs for disabled, masked ones for non-829 users,
	 * so the real secret is never sent to their browser.
	 */
	public function mask_secret_key_fields() {
		if ( wp_doing_ajax() || ! $this->is_settings_page() || ! $this->should_restrict() ) {
			return;
		}

		$renderer = gf_recaptcha()->get_settings_renderer();

		if ( ! $renderer ) {
			return;
		}

		$this->replace_secret_key_fields( $renderer );

		// A save on a site with no saved connection type rebuilds the fields unmasked, so mask again
		// after the save. This callback runs between the save and the render; the add-on only uses it in Enterprise.
		if ( 'enterprise' !== gf_recaptcha()->get_connection_type() ) {
			$renderer->set_postback_message_callback(
				function ( $message ) use ( $renderer ) {
					$this->replace_secret_key_fields( $renderer );

					return $message;
				}
			);
		}
	}

	/**
	 * Replace the Classic/v2 secret key fields with disabled, masked ones.
	 *
	 * @param \Gravity_Forms\Gravity_Forms\Settings\Settings $renderer Settings renderer.
	 */
	protected function replace_secret_key_fields( $renderer ) {
		$settings = gf_recaptcha()->get_plugin_settings();

		foreach ( self::SECRET_KEY_FIELDS as $name ) {
			$field = $renderer->get_field( $name );

			// No saved secret: leave the field editable so the site can be set up. An html
			// field is already masked; GF calls the postback callback on every render, not only saves.
			if ( ! $field || 'html' === rgar( $field, 'type' ) || '' === (string) rgar( $settings, $name ) ) {
				continue;
			}

			// Same id/name as the original input: the add-on's JS looks it up by both.
			// after_input holds the add-on's "defined using the constant" note.
			$html = sprintf(
				'<span class="gform-settings-input__container"><input type="text" id="%1$s" name="_gform_setting_%1$s" value="%2$s" disabled="disabled" /></span>%3$s<div class="gform-settings-description">%4$s</div>',
				esc_attr( $name ),
				esc_attr( self::SECRET_MASK ),
				wp_kses_post( (string) rgar( $field, 'after_input' ) ),
				esc_html__( 'Managed by 829 Studios.', 'wordpress-tools' )
			);

			// Field objects implement ArrayAccess, which also reaches their protected properties.
			$renderer->replace_field(
				$name,
				[
					'name'       => $name,
					'label'      => rgar( $field, 'label' ),
					'tooltip'    => rgar( $field, 'tooltip' ),
					'type'       => 'html',
					'html'       => $html,
					'dependency' => rgar( $field, 'dependency' ),
				]
			);
		}
	}

	/**
	 * Whether the current request should be restricted to the saved project.
	 *
	 * @return bool
	 */
	protected function should_restrict() {
		return ! is_829_user();
	}

	/**
	 * A saved add-on setting, preferring its constant. Read from storage, not the
	 * add-on's get_recaptcha_key(), which prefers $_POST values in wp-admin.
	 *
	 * @param string $name     Setting name.
	 * @param string $constant Constant that overrides it.
	 * @return string
	 */
	protected function get_saved_setting( $name, $constant ) {
		if ( defined( $constant ) && constant( $constant ) ) {
			return (string) constant( $constant );
		}

		return (string) gf_recaptcha()->get_plugin_setting( $name );
	}

	/**
	 * The saved Enterprise project number.
	 *
	 * @return string
	 */
	protected function get_saved_project_number() {
		return $this->get_saved_setting( 'project_number', 'GF_RECAPTCHA_PROJECT_NUMBER' );
	}

	/**
	 * The saved Enterprise site key.
	 *
	 * @return string
	 */
	protected function get_saved_site_key() {
		return $this->get_saved_setting( 'site_key_v3_enterprise', 'GF_RECAPTCHA_V3_SITE_KEY_ENTERPRISE' );
	}

	/**
	 * Trim the projects and site-keys list responses down to the saved project and key.
	 *
	 * @param array|\WP_Error $response    HTTP response.
	 * @param array           $parsed_args Request arguments.
	 * @param string          $url         Request URL.
	 * @return array|\WP_Error
	 */
	public function filter_google_lists( $response, $parsed_args, $url ) {
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( 0 === strpos( $url, self::PROJECTS_URL ) && $this->should_restrict() ) {
			$saved = $this->get_saved_project_number();

			// Paging isn't needed once the list is down to one project.
			return $this->filter_list(
				$response,
				'projects',
				function ( $project ) use ( $saved ) {
					return isset( $project['projectNumber'] ) && (string) $project['projectNumber'] === $saved;
				},
				true
			);
		}

		if ( preg_match( self::KEYS_URL_PATTERN, $url ) && $this->should_restrict() ) {
			$saved = $this->get_saved_site_key();

			// Keep nextPageToken: the saved key may be on a later page, which the add-on fetches and this filters too.
			return $this->filter_list(
				$response,
				'keys',
				function ( $key ) use ( $saved ) {
					return isset( $key['name'] ) && basename( $key['name'] ) === $saved;
				},
				false
			);
		}

		return $response;
	}

	/**
	 * Keep only the items in a JSON list response that pass the callback.
	 *
	 * @param array    $response   HTTP response.
	 * @param string   $list_key   Key of the list in the decoded body.
	 * @param callable $keep       Returns true for items to keep.
	 * @param bool     $drop_paging Whether to remove nextPageToken.
	 * @return array
	 */
	protected function filter_list( $response, $list_key, $keep, $drop_paging ) {
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $data ) || ! isset( $data[ $list_key ] ) || ! is_array( $data[ $list_key ] ) ) {
			return $response;
		}

		$data[ $list_key ] = array_values( array_filter( $data[ $list_key ], $keep ) );

		if ( $drop_paging ) {
			unset( $data['nextPageToken'] );
		}

		$response['body'] = wp_json_encode( $data );

		return $response;
	}

	/**
	 * Refuse site-key lookups for any project but the saved one, matching the posted
	 * value exactly so paths like "SAVED/../OTHER" can't reach other projects.
	 */
	public function reject_other_project_keys() {
		$project = $this->get_posted( 'project' );

		if ( ! $this->should_restrict() || ( '' !== $project && $project === $this->get_saved_project_number() ) ) {
			return;
		}

		$this->send_restricted_error();
	}

	/**
	 * Refuse Enterprise saves that change the project or site key, so a known
	 * project number can't be saved to make it pass the checks above.
	 */
	public function reject_other_project_save() {
		$project = $this->get_posted( 'project_number' );

		if ( ! $this->should_restrict() || ( '' !== $project && $project === $this->get_saved_project_number() && $this->get_posted( 'site_key_v3_enterprise' ) === $this->get_saved_site_key() ) ) {
			return;
		}

		$this->send_restricted_error();
	}

	/**
	 * Refuse disconnecting, switching the connection type or connecting another
	 * Google account once reCAPTCHA is configured, so 829's setup can't be replaced.
	 */
	public function reject_setup_change() {
		if ( ! $this->should_restrict() || ! $this->is_configured() ) {
			return;
		}

		$this->send_restricted_error();
	}

	/**
	 * Whether any reCAPTCHA credentials are saved, including Gravity Forms core's
	 * v2 keys, which the add-on's disconnect also deletes.
	 *
	 * @return bool
	 */
	protected function is_configured() {
		$settings = gf_recaptcha()->get_plugin_settings();

		foreach ( [ 'access_token', 'secret_key_v3', 'secret_key_v2' ] as $name ) {
			if ( '' !== (string) rgar( $settings, $name ) ) {
				return true;
			}
		}

		return '' !== $this->get_saved_project_number() || '' !== (string) get_option( 'rg_gforms_captcha_private_key' );
	}

	/**
	 * A sanitized $_POST value.
	 *
	 * @param string $name Field name.
	 * @return string
	 */
	protected function get_posted( $name ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Only used to reject requests; the add-on verifies its own nonces.
		return isset( $_POST[ $name ] ) ? sanitize_text_field( wp_unslash( $_POST[ $name ] ) ) : '';
	}

	/**
	 * End the AJAX request with a restricted-access error.
	 */
	protected function send_restricted_error() {
		wp_send_json_error( new \WP_Error( 'wpt_recaptcha_restricted', __( 'Access to this reCAPTCHA project is restricted.', 'wordpress-tools' ) ) );
	}
}

<?php
/**
 * Hide other clients' reCAPTCHA Enterprise projects from non-829 users.
 *
 * @package  WordPressTools
 */

namespace WordPressTools\GravityFormsRecaptcha;

use WordPressTools\Singleton;
use function WordPressTools\Utils\is_829_user;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Gravity Forms reCAPTCHA class
 *
 * The add-on's v3 Enterprise settings list every Google Cloud project on the
 * connected (829) Google account, which exposes other clients' projects to client
 * admins. The add-on has no filter for this, so the Google API responses are
 * filtered instead: non-829 users only see the project already saved on this site.
 */
class GravityFormsRecaptcha {

	use Singleton;

	const PROJECTS_URL = 'https://cloudresourcemanager.googleapis.com/v1/projects';

	const KEYS_URL_PATTERN = '#^https://recaptchaenterprise\.googleapis\.com/v1/projects/([^/?]+)/keys#';

	/**
	 * Setup module
	 */
	public function setup() {
		if ( ! function_exists( 'gf_recaptcha' ) ) {
			return;
		}

		add_filter( 'pre_http_request', [ $this, 'block_other_project_keys' ], 10, 3 );
		add_filter( 'http_response', [ $this, 'filter_projects_list' ], 10, 3 );
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
	 * The project number saved on this site, read from storage rather than the
	 * add-on's get_recaptcha_key(), which prefers $_POST values in wp-admin.
	 *
	 * @return string
	 */
	protected function get_saved_project_number() {
		if ( defined( 'GF_RECAPTCHA_PROJECT_NUMBER' ) && GF_RECAPTCHA_PROJECT_NUMBER ) {
			return (string) GF_RECAPTCHA_PROJECT_NUMBER;
		}

		$addon = gf_recaptcha();

		return $addon ? (string) $addon->get_plugin_setting( 'project_number' ) : '';
	}

	/**
	 * Drop every project except the saved one from the projects list response.
	 *
	 * @param array|\WP_Error $response    HTTP response.
	 * @param array           $parsed_args Request arguments.
	 * @param string          $url         Request URL.
	 * @return array|\WP_Error
	 */
	public function filter_projects_list( $response, $parsed_args, $url ) {
		if ( is_wp_error( $response ) || 0 !== strpos( $url, self::PROJECTS_URL ) || ! $this->should_restrict() ) {
			return $response;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $data ) || ! isset( $data['projects'] ) || ! is_array( $data['projects'] ) ) {
			return $response;
		}

		$saved = $this->get_saved_project_number();

		$data['projects'] = array_values(
			array_filter(
				$data['projects'],
				function ( $project ) use ( $saved ) {
					return '' !== $saved && isset( $project['projectNumber'] ) && (string) $project['projectNumber'] === $saved;
				}
			)
		);

		unset( $data['nextPageToken'] );

		$response['body'] = wp_json_encode( $data );

		return $response;
	}

	/**
	 * Refuse site-key lookups for any project other than the saved one, so the
	 * get_enterprise_site_keys AJAX action can't be used to probe other projects.
	 *
	 * @param false|array|\WP_Error $preempt     Short-circuit response.
	 * @param array                 $parsed_args Request arguments.
	 * @param string                $url         Request URL.
	 * @return false|array|\WP_Error
	 */
	public function block_other_project_keys( $preempt, $parsed_args, $url ) {
		if ( false !== $preempt || ! preg_match( self::KEYS_URL_PATTERN, $url, $matches ) || ! $this->should_restrict() ) {
			return $preempt;
		}

		if ( rawurldecode( $matches[1] ) === $this->get_saved_project_number() ) {
			return $preempt;
		}

		return [
			'headers'  => [],
			'body'     => wp_json_encode( [ 'error' => [ 'message' => 'Access to this reCAPTCHA project is restricted.' ] ] ),
			'response' => [
				'code'    => 403,
				'message' => 'Forbidden',
			],
			'cookies'  => [],
			'filename' => null,
		];
	}
}

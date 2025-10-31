<?php

namespace WordPressTools;

class SSOCallbackHandler {

	public function __construct() {
		$this->init();
	}

	private function init() {
		add_action( 'init', array( $this, 'handle_callback' ) );
	}

	private function get_token_payload( $token ) {
		$parts = explode( '.', $token );
		if ( count( $parts ) === 3 ) {
			return json_decode( base64_decode( strtr( $parts[1], '-_', '+/' ) ), true );
		}
		$this->invalid_jwt_error();
		return null;
	}

	private function verify_nonce( $nonce ) {
		$expected_nonce = get_transient( 'auth829_nonce_' . $nonce );
		if ( ! $expected_nonce ) {
			$this->missing_nonce_error();
		}
		delete_transient( 'auth829_nonce_' . $nonce );
	}

	private function get_or_create_user( $payload ) {
		$email      = $payload['email'] ?? null;
		$role       = $payload['role'] ?? 'subscriber';
		$first_name = $payload['first_name'] ?? '';
		$last_name  = $payload['last_name'] ?? '';

		if ( ! $email ) {
			$this->missing_email_error();
		}

		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			$userdata = array(
				'user_login'   => $email,
				'user_email'   => $email,
				'first_name'   => $first_name,
				'last_name'    => $last_name,
				'display_name' => $first_name . ' ' . $last_name,
				'user_pass'    => wp_generate_password(),
				'role'         => $role,
			);
			$user_id  = wp_insert_user( $userdata );
			if ( is_wp_error( $user_id ) ) {
				$this->user_creation_failed_error( $user_id->get_error_message() );
			}
			$user = get_user_by( 'id', $user_id );
		}

		return $user;
	}

	public function handle_callback() {

		if ( ! isset( $_GET['sso_token'] ) ) {
			return;
		}

		$sso_token = isset( $_GET['sso_token'] ) ? $_GET['sso_token'] : null;

		if ( $sso_token ) {
			$payload = $this->get_token_payload( $sso_token );
			$nonce   = isset( $payload['nonce'] ) ? $payload['nonce'] : null;

			$this->verify_nonce( $nonce );

			$user = $this->get_or_create_user( $payload );

			wp_set_current_user( $user->ID );
			wp_set_auth_cookie( $user->ID );
			$redirect_url = user_can( $user, 'manage_options' ) ? admin_url() : home_url();
			wp_redirect( $redirect_url );
			exit;
		}

		$this->invalid_callback_error();
	}

	private function missing_nonce_error() {
		echo 'Invalid or missing nonce';
		echo '<br><a href="/wp-admin">Return to login</a>';
		die();
	}

	private function invalid_jwt_error() {
		echo 'Invalid JWT format';
		echo '<br><a href="/wp-admin">Return to login</a>';
		die();
	}

	private function user_creation_failed_error( $error_message ) {
		echo 'User creation failed: ' . $error_message;
		echo '<br><a href="/wp-admin">Return to login</a>';
		die();
	}

	private function invalid_callback_error() {
		echo 'Invalid callback or code';
		echo '<br><a href="/wp-admin">Return to login</a>';
		die();
	}

	private function missing_email_error() {
		echo 'No email in JWT payload';
		echo '<br><a href="/wp-admin">Return to login</a>';
		die();
	}
}

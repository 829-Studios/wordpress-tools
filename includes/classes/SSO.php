<?php
/**
 * WordPress Tools SSO client.
 *
 * @package  wordpress-tools
 */

namespace WordPressTools;

use WP_Error;

/**
 * SSO class
 */
class SSO {

	/**
	 * Errors array
	 *
	 * @var array
	 */
	protected $errors = [];

	/**
	 * Setup SSO
	 */
	public function __construct() {

		if ( defined( 'WORDPRESS_TOOLS_SSO_DISABLE' ) && WORDPRESS_TOOLS_SSO_DISABLE ) {
			return;
		}

		if ( is_multisite() ) {
			add_action( 'wpmu_options', [ $this, 'ms_settings' ] );
			add_action( 'admin_init', [ $this, 'ms_save_settings' ] );
		} else {
			add_action( 'admin_init', [ $this, 'single_site_setting' ] );
		}

		if ( 'yes' !== $this->get_setting() ) {
			return;
		}

		if ( defined( 'WORDPRESS_TOOLS_SSO_DISALLOW_ALL_DIRECT_LOGIN' ) && WORDPRESS_TOOLS_SSO_DISALLOW_ALL_DIRECT_LOGIN ) {
			add_filter( 'allow_password_reset', '__return_false' );
		}

		add_filter( 'wp_login_errors', [ $this, 'add_login_errors' ] );
		add_action( 'login_form_wpt-start-login', [ $this, 'start_login' ] );
		add_action( 'init', [ $this, 'process_login' ] );
		add_action( 'login_form', [ $this, 'update_login_form' ] );
		add_action( 'login_head', [ $this, 'render_login_form_styles' ] );
		add_filter( 'authenticate', [ $this, 'prevent_standard_login_for_sso_user' ], 999 );
		add_action( 'admin_page_access_denied', [ $this, 'check_user_blog' ] );
	}

	/**
	 * Set options in multisite
	 */
	public function ms_save_settings() {
		global $pagenow;
		if ( ! is_network_admin() ) {
			return;
		}

		if ( 'settings.php' !== $pagenow ) {
			return;
		}

		if ( ! is_super_admin() ) {
			return;
		}

		// We're only checking if the nonce exists here, so no need to sanitize.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( empty( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'siteoptions' ) ) {
			return;
		}

		// We're only checking if the var exists here, so no need to sanitize.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( ! isset( $_POST['wpt_allow_sso'] ) ) {
			return;
		}

		$setting = $this->validate_sso_setting( sanitize_text_field( $_POST['wpt_allow_sso'] ) );

		update_site_option( 'wpt_allow_sso', $setting );
	}

	/**
	 * Output multisite settings
	 */
	public function ms_settings() {
		$setting = $this->get_setting();
		?>
		<h2><?php esc_html_e( '829 Studios SSO', 'wordpress-tools' ); ?></h2>
		<p><?php esc_html_e( 'This allows members of 829 Studios to log in via SSO. This is extremely important to streamline maintenance of your website.', 'wordpress-tools' ); ?></p>
		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row"><?php esc_html_e( 'Allow 829 Studios SSO', 'wordpress-tools' ); ?></th>
					<td>
						<input name="wpt_allow_sso" <?php checked( 'yes', $setting ); ?> type="radio" id="wpt_allow_sso_yes" value="yes"> <label for="wpt_allow_sso_yes"><?php esc_html_e( 'Yes', 'wordpress-tools' ); ?></label><br>
						<input name="wpt_allow_sso" <?php checked( 'no', $setting ); ?> type="radio" id="wpt_allow_sso_no" value="no"> <label for="wpt_allow_sso_no"><?php esc_html_e( 'No', 'wordpress-tools' ); ?></label>
					</td>
				</tr>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Get setting
	 *
	 * @return array
	 */
	public function get_setting() {
		$setting = ( is_multisite() ) ? get_site_option( 'wpt_allow_sso', 'yes' ) : get_option( 'wpt_allow_sso', 'yes' );

		return $setting;
	}

	/**
	 * Register restrict REST API setting.
	 */
	public function single_site_setting() {

		$settings_args = array(
			'type'              => 'string',
			'sanitize_callback' => [ $this, 'validate_sso_setting' ],
		);

		register_setting( 'general', 'wpt_allow_sso', $settings_args );
		add_settings_field( 'wpt_allow_sso', esc_html__( 'Allow 829 Studios SSO', 'wordpress-tools' ), [ $this, 'sso_setting_field_output' ], 'general' );
	}

	/**
	 * Validate sso setting.
	 *
	 * @param  string $value Current restriction.
	 * @return string
	 */
	public function validate_sso_setting( $value ) {
		if ( in_array( $value, array( 'yes', 'no' ), true ) ) {
			return $value;
		}

		return 'yes';
	}

	/**
	 * Display UI for restrict REST API setting.
	 *
	 * @return void
	 */
	public function sso_setting_field_output() {
		$allow_sso = $this->get_setting();
		?>

		<input id="wpt-allow-sso-yes" name="wpt_allow_sso" type="radio" value="yes"<?php checked( $allow_sso, 'yes' ); ?> />
		<label for="wpt-allow-sso-yes">
			<?php esc_html_e( 'Yes', 'wordpress-tools' ); ?>
		</label><br>

		<input id="wpt-allow-sso-no" name="wpt_allow_sso" type="radio" value="no"<?php checked( $allow_sso, 'no' ); ?> />
		<label for="wpt-allow-sso-no">
			<?php esc_html_e( 'No', 'wordpress-tools' ); ?>
		</label>
		<p class="description"><?php esc_html_e( 'This allows members of 829 Studios to log in via SSO. This is extremely important to streamline maintenance of your website.', 'wordpress-tools' ); ?></p>
		<?php
	}

	/**
	 * Show login errors on form if any exist
	 *
	 * @param WP_Error $errors Current errors
	 * @return WP_Error
	 */
	public function add_login_errors( WP_Error $errors ) {

		foreach ( $this->errors as $error ) {
			$errors->add( 'wpt-sso-login', $error );
		}

		return $errors;
	}

	public function validate_sso_token( string $sso_token ) {
		$url = WORDPRESS_TOOLS_SSO_PROXY_URL . '/sso/token-revalidation';

		$response = wp_remote_post( $url, array(
			'body' => array(
				'sso_token' => $sso_token,
			),
		) );

		if ( wp_remote_retrieve_response_code( $response ) !== 200 ) {
			return new WP_Error( 'wpt-sso-login', esc_html__( 'Failed to validate SSO token.', 'wordpress-tools' ) );
		}

		try {
			$body = json_decode( wp_remote_retrieve_body( $response ), true );
		} catch ( \Exception $e ) {
			return new WP_Error( 'wpt-sso-login', esc_html__( 'Failed to decode SSO token response.', 'wordpress-tools' ) );
		}

		return [
			'email'      => $body['data']['email'],
			'role'       => $body['data']['role'],
			'first_name' => $body['data']['first_name'],
			'last_name'  => $body['data']['last_name'],
			'nonce'      => $body['data']['nonce'],
		];
	}

	public function start_login() {
		$redirect_to = filter_input( INPUT_GET, 'redirect_to' );

		if ( empty( $redirect_to ) ) {
			$redirect_to = home_url();
		}

		$site_url = home_url();
		$nonce = wp_create_nonce();

		$proxy_url = add_query_arg( 'site', $site_url, WORDPRESS_TOOLS_SSO_PROXY_URL . '/sso/login' );
		$proxy_url = add_query_arg( 'nonce', $nonce, $proxy_url );

		set_transient(
			'auth_session_' . $nonce,
			[
				'redirect_to' => $redirect_to,
			],
			300
		);

		wp_redirect( $proxy_url );
		exit;
	}

	/**
	 * Process a login request
	 */
	public function process_login() {
		global $wpt_login_failed;

		$sso_token = filter_input( INPUT_GET, 'sso_token' );

		if ( empty( $sso_token ) ) {
			return;
		}

		$payload = $this->validate_sso_token( $sso_token );

		if ( is_wp_error( $payload ) ) {
			$this->errors[] = $payload->get_error_message();
			return;
		}

		$session = get_transient( 'auth_session_' . $payload['nonce'] );
		if ( empty( $session ) ) {
			$this->errors[] = esc_html__( 'Invalid session.', 'wordpress-tools' );
			return;
		}

		delete_transient( 'auth_session_' . $payload['nonce'] );

		if ( empty( $payload['email'] ) || empty( $payload['role'] ) ) {
			$this->errors[] = esc_html__( 'Login failed.', 'wordpress-tools' );
			return;
		}

		$user = get_user_by( 'email', $payload['email'] );

		if ( ! $user ) {
			$username = current( explode( '@', $payload['email'] ) );

			if ( username_exists( $username ) ) {
				// Turn periods into dashes.
				$username = str_replace( '.', '-', $username );
				// Add the domain onto the end, so it's more unique.
				$username = sprintf(
					'%s-%s',
					$username,
					explode( '.', explode( '@', $payload['email'] )[1], 2 )[0]
				);
			}

			$user_id = wp_insert_user(
				array(
					'user_login'   => $username,
					'user_pass'    => wp_generate_password(),
					'user_email'   => $payload['email'],
					'display_name' => $payload['first_name'] . ' ' . $payload['last_name'],
					'first_name'   => $payload['first_name'],
					'last_name'    => $payload['last_name'],
					'role'         => $payload['role'],
				)
			);

			if ( ! is_wp_error( $user_id ) ) {
				add_user_meta( $user_id, 'wpt-sso', 1 );

				if ( is_multisite() ) {
					add_user_to_blog( get_current_blog_id(), $user_id, $payload['role'] );
					if ( 'administrator' === $payload['role'] && defined( 'WORDPRESS_TOOLS_SSO_GRANT_SUPER_ADMIN' ) && filter_var( WORDPRESS_TOOLS_SSO_GRANT_SUPER_ADMIN, FILTER_VALIDATE_BOOLEAN ) ) {
						require_once ABSPATH . 'wp-admin/includes/ms.php';
						grant_super_admin( $user_id );
					}
				}

				$user = get_user_by( 'id', $user_id );
			}
		} else {
			$user_id = $user->ID;
		}

		if ( empty( $user_id ) ) {
			$this->errors[] = esc_html__( 'Failed to create user.', 'wordpress-tools' );
			return;
		}

		add_filter( 'auth_cookie_expiration', [ $this, 'change_cookie_expiration' ], 1000 );
		wp_set_auth_cookie( $user_id );
		remove_filter( 'auth_cookie_expiration', [ $this, 'change_cookie_expiration' ], 1000 );

		$redirect_to = $session['redirect_to'] ?? home_url();

		wp_safe_redirect( $redirect_to );
		exit;
	}

	/**
	 * Insert login button into login form
	 */
	public function update_login_form() {
		$login_url = home_url( '/wp-login.php?action=wpt-start-login');

		$buttons_html = '<div class="sso"><div class="buttons">';

		$svg = file_get_contents( WORDPRESS_TOOLS_PLUGIN_DIR . 'assets/svg/logo.svg' );
		$svg = str_replace( "\n", '', $svg );

		$buttons_html .= '<a href="' . esc_url( $login_url ) . '" class="wpt-button button"> ' . $svg . ' ' .
		'<span>Sign in with 829 Studios</span></a>';

		$buttons_html .= '</div><span class="or"><span>or</span></span>';
		$buttons_html .= '</div>';

		?>
		<script type="text/javascript">
			(function() {
				document.getElementById('loginform').insertAdjacentHTML(
					'beforebegin',
					'<?php echo $buttons_html; // phpcs:ignore ?>'
				);
			})();
		</script>
		<?php
	}

	/**
	 * Render login form styles
	 */
	public function render_login_form_styles() {
		?>
		<style>
			.sso {
				background: #fff;
				border: 1px solid #c3c4c7;
				box-shadow: 0 1px 3px rgb(0 0 0 / 4%);
				font-weight: 400;
				font-weight: normal;
				margin-left: 0;
				margin-top: 20px;
				overflow: hidden;
				overflow: hidden;
				padding: 26px 24px 26px;
			}

			.sso .buttons {
				align-items: center;
				display: flex;
				justify-content: center;
			}

			.sso .button {
				background-color: #fff;
				display: flex;
				align-items: center;
				justify-content: center;
				gap: 8px;
				padding: .4375rem 1.4375rem;
				border-width: .0625rem;
				border-radius: 1.5rem;
				border: 1px solid #0a0028;
				color: #0a0028;
				font-size: 14px;
				transition: all 0.2s;
			}

			.sso .button:hover {
				background-color: #2246fa;
				border-color: #2246fa;
				color: #fff;
			}

			.sso .button:hover path {
				fill: #fff;
			}

			.sso .button svg {
				height: 26px;
				width: 26px;
			}

			.sso .button img {
				height: 32px;
				width: 32px;
			}

			.sso .or {
				border-bottom: 1px solid rgba(0,0,0,0.13);
				display: block;
				line-height: 1;
				margin: .8em 0 2em 0;
				text-align: center;
				width: 100%;
			}

			.sso .or span {
				background: white;
				color: #72777c;
				padding: 0 1em;
				position: relative;
				top: 0.5em;
			}

			#loginform {
				border-top: 0;
				margin-top: 0;
				padding-top: 0;
				position: relative;
				top: -17px;
			}

			<?php if ( defined( 'WORDPRESS_TOOLS_SSO_DISALLOW_ALL_DIRECT_LOGIN' ) && WORDPRESS_TOOLS_SSO_DISALLOW_ALL_DIRECT_LOGIN ) : ?>
				#loginform,
				#nav,
				.sso .or {
					display: none;
				}
			<?php endif; ?>
		</style>
		<?php
	}

	/**
	 * If a user account was created via SSO, don't let them
	 * login via password.
	 *
	 * @param WP_User $user User object
	 * @return WP_User
	 */
	public function prevent_standard_login_for_sso_user( $user ) {
		if ( ! is_wp_error( $user ) && defined( 'WORDPRESS_TOOLS_SSO_DISALLOW_ALL_DIRECT_LOGIN' ) && WORDPRESS_TOOLS_SSO_DISALLOW_ALL_DIRECT_LOGIN ) {
			return new WP_Error( 'wpt-sso', esc_html__( 'Username/password authentication is disabled', 'wordpress-tools' ) );
		}

		// Check if user was created with SSO. If so, they must use SSO.
		if ( ! is_wp_error( $user ) ) {
			$is_sso = get_user_meta( $user->ID, 'wpt-sso', true );
			if ( filter_var( $is_sso, FILTER_VALIDATE_BOOLEAN ) ) {
				return new WP_Error( 'wpt-sso', esc_html__( 'This account can only be logged into using 829 Studios SSO.', 'wordpress-tools' ) );
			}
		}

		return $user;
	}

	/**
	 * New cookie expiration time
	 *
	 * @return integer
	 */
	public function change_cookie_expiration() {
		return DAY_IN_SECONDS;
	}
}

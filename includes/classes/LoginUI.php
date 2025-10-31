<?php

namespace WordPressTools;

/**
 * Login UI class
 *
 * @package WordPressTools
 */
class LoginUI {

	public function __construct() {
		$this->init();
	}

	private function init() {
		add_action( 'login_footer', array( $this, 'add_sso_button' ) );
		add_action( 'login_enqueue_scripts', array( $this, 'enqueue_login_styles' ) );
	}

	public function enqueue_login_styles() {
		wp_enqueue_style(
			'auth829-sso-styles',
			plugins_url( 'assets/css/auth829-sso.css', __DIR__ ),
			array(),
			'1.0.0'
		);
	}

	public function add_sso_button() {

		$nonce = wp_generate_uuid4();
		set_transient( 'auth829_nonce_' . $nonce, true, 300 ); // 5 minutes
		$site_url = get_site_url();

		$proxyurl = esc_url( "https://x829-sso-proxy-ee756094fea9.herokuapp.com/sso/login?site={$site_url}&nonce={$nonce}" );

		?>
			<div class="auth829-sso-container">
				<div class="auth829-sso-separator"><?php _e( 'or', '829-authentication' ); ?></div>
				<a href="<?php echo esc_url( $proxyurl ); ?>" class="auth829-sso-button" rel="noopener noreferrer">
					<?php _e( 'Sign in with SSO', '829-authentication' ); ?>
				</a>
			</div>
			<div class="auth829-sso-spacer"></div>
		<?php
	}
}

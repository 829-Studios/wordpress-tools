<?php
/**
 * 829 Settings
 *
 * @package  WordPressTools
 */

namespace WordPressTools\Settings;

use WordPressTools\Singleton;
use function WordPressTools\Utils\is_local_environment;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Settings class
 */
class Settings {

	use Singleton;

	/**
	 * Setup module
	 *
	 * @since 1.0
	 */
	public function setup() {
		if ( WPT_IS_NETWORK ) {
			add_action( 'network_admin_menu', [ $this, 'register_network_settings_page' ] );
			add_action( 'network_admin_edit_wpt_829_settings', [ $this, 'save_network_settings' ] );
		} else {
			add_action( 'admin_init', [ $this, 'register_settings' ] );
			add_action( 'admin_menu', [ $this, 'register_settings_page' ] );
		}
	}

	/**
	 * Check if the current user can access 829 Settings.
	 *
	 * @return bool
	 */
	protected function can_access_settings() {
		// Check if we're on a local environment
		// phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid
		if ( is_local_environment() ) {
			return true;
		}

		// Get current user
		$current_user = wp_get_current_user();

		// Check if user has an 829llc.com email
		if ( $current_user && $current_user->user_email ) {
			$email_domain = substr( strrchr( $current_user->user_email, '@' ), 1 );
			if ( '829llc.com' === $email_domain ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Register the network settings page.
	 */
	public function register_network_settings_page() {
		// Only show menu if user can access settings
		if ( ! $this->can_access_settings() ) {
			return;
		}

		add_submenu_page(
			'settings.php',
			esc_html__( '829 Settings', 'wordpress-tools' ),
			esc_html__( '829 Settings', 'wordpress-tools' ),
			'manage_network_options',
			'wpt-829-settings',
			[ $this, 'render_network_settings_page' ]
		);
	}

	/**
	 * Register the settings page.
	 */
	public function register_settings_page() {
		// Only show menu if user can access settings
		if ( ! $this->can_access_settings() ) {
			return;
		}

		add_options_page(
			esc_html__( '829 Settings', 'wordpress-tools' ),
			esc_html__( '829 Settings', 'wordpress-tools' ),
			'manage_options',
			'wpt-829-settings',
			[ $this, 'render_settings_page' ]
		);
	}

	/**
	 * Register settings.
	 */
	public function register_settings() {
		// Register SSO setting
		register_setting(
			'wpt_829_settings',
			'wpt_allow_sso',
			[
				'type'              => 'string',
				'sanitize_callback' => [ $this, 'validate_yes_no_setting' ],
				'default'           => 'yes',
			]
		);

		// Register Comments setting
		register_setting(
			'wpt_829_settings',
			'wpt_disable_comments',
			[
				'type'              => 'string',
				'sanitize_callback' => [ $this, 'validate_yes_no_setting' ],
				'default'           => 'no',
			]
		);

		// Register Strong Passwords setting
		register_setting(
			'wpt_829_settings',
			'wpt_require_strong_passwords',
			[
				'type'              => 'integer',
				'sanitize_callback' => 'intval',
				'default'           => 1,
			]
		);

		// Register Password Protected Content setting
		register_setting(
			'wpt_829_settings',
			'wpt_password_protect',
			[
				'type'              => 'integer',
				'sanitize_callback' => 'intval',
				'default'           => 0,
			]
		);

		// Register Disable File Modifications setting
		register_setting(
			'wpt_829_settings',
			'wpt_disallow_file_mods',
			[
				'type'              => 'string',
				'sanitize_callback' => [ $this, 'validate_yes_no_setting' ],
				'default'           => 'no',
			]
		);

		// Register REST API Restriction setting
		register_setting(
			'wpt_829_settings',
			'wpt_restrict_rest_api',
			[
				'type'              => 'string',
				'sanitize_callback' => [ $this, 'validate_rest_api_setting' ],
				'default'           => 'users',
			]
		);

		add_settings_section(
			'wpt_829_general_section',
			esc_html__( 'General Settings', 'wordpress-tools' ),
			[ $this, 'general_section_callback' ],
			'wpt-829-settings'
		);

		// SSO setting field
		add_settings_field(
			'wpt_allow_sso',
			esc_html__( 'Allow 829 Studios SSO', 'wordpress-tools' ),
			[ $this, 'sso_setting_callback' ],
			'wpt-829-settings',
			'wpt_829_general_section'
		);

		// Comments setting field
		add_settings_field(
			'wpt_disable_comments',
			esc_html__( 'Disable Comments', 'wordpress-tools' ),
			[ $this, 'comments_setting_callback' ],
			'wpt-829-settings',
			'wpt_829_general_section'
		);

		// Strong Passwords setting field
		add_settings_field(
			'wpt_require_strong_passwords',
			esc_html__( 'Require Strong Passwords', 'wordpress-tools' ),
			[ $this, 'passwords_setting_callback' ],
			'wpt-829-settings',
			'wpt_829_general_section'
		);

		// Password Protected Content setting field
		add_settings_field(
			'wpt_password_protect',
			esc_html__( 'Enable Password Protected Content', 'wordpress-tools' ),
			[ $this, 'password_protect_setting_callback' ],
			'wpt-829-settings',
			'wpt_829_general_section'
		);

		// Disable File Modifications setting field
		add_settings_field(
			'wpt_disallow_file_mods',
			esc_html__( 'Disable File Modifications', 'wordpress-tools' ),
			[ $this, 'disallow_file_mods_setting_callback' ],
			'wpt-829-settings',
			'wpt_829_general_section'
		);

		// REST API Restriction setting field
		add_settings_field(
			'wpt_restrict_rest_api',
			esc_html__( 'REST API Availability', 'wordpress-tools' ),
			[ $this, 'restrict_rest_api_setting_callback' ],
			'wpt-829-settings',
			'wpt_829_general_section'
		);
	}

	/**
	 * General section callback.
	 */
	public function general_section_callback() {
		echo '<p>' . esc_html__( 'Configure 829 Studios settings.', 'wordpress-tools' ) . '</p>';
	}

	/**
	 * SSO setting callback.
	 */
	public function sso_setting_callback() {
		$allow_sso = WPT_IS_NETWORK ? get_site_option( 'wpt_allow_sso', 'yes' ) : get_option( 'wpt_allow_sso', 'yes' );
		?>
		<fieldset>
			<input id="wpt-allow-sso-yes" name="wpt_allow_sso" type="radio" value="yes"<?php checked( $allow_sso, 'yes' ); ?> />
			<label for="wpt-allow-sso-yes">
				<?php esc_html_e( 'Yes', 'wordpress-tools' ); ?>
			</label><br>

			<input id="wpt-allow-sso-no" name="wpt_allow_sso" type="radio" value="no"<?php checked( $allow_sso, 'no' ); ?> />
			<label for="wpt-allow-sso-no">
				<?php esc_html_e( 'No', 'wordpress-tools' ); ?>
			</label>
			<p class="description"><?php esc_html_e( 'This allows members of 829 Studios to log in via SSO. This is extremely important to streamline maintenance of your website.', 'wordpress-tools' ); ?></p>
		</fieldset>
		<?php
	}

	/**
	 * Comments setting callback.
	 */
	public function comments_setting_callback() {
		$disable_comments = WPT_IS_NETWORK ? get_site_option( 'wpt_disable_comments', 'no' ) : get_option( 'wpt_disable_comments', 'no' );
		$is_disabled      = defined( 'WPT_DISABLE_COMMENTS' ) || has_filter( 'wpt_disable_comments' );
		?>
		<fieldset>
			<input id="wpt-disable-comments-yes" name="wpt_disable_comments" type="radio" value="yes"<?php checked( $disable_comments, 'yes' ); ?><?php disabled( $is_disabled ); ?> />
			<label for="wpt-disable-comments-yes">
				<?php esc_html_e( 'Yes', 'wordpress-tools' ); ?>
			</label><br>

			<input id="wpt-disable-comments-no" name="wpt_disable_comments" type="radio" value="no"<?php checked( $disable_comments, 'no' ); ?><?php disabled( $is_disabled ); ?> />
			<label for="wpt-disable-comments-no">
				<?php esc_html_e( 'No', 'wordpress-tools' ); ?>
			</label>
			<p class="description"><?php esc_html_e( 'This will remove all the comments related UI from the admin and frontend.', 'wordpress-tools' ); ?></p>
		</fieldset>
		<?php
	}

	/**
	 * Passwords setting callback.
	 */
	public function passwords_setting_callback() {
		$require_strong_passwords = WPT_IS_NETWORK ? get_site_option( 'wpt_require_strong_passwords', 1 ) : get_option( 'wpt_require_strong_passwords', 1 );
		?>
		<fieldset>
			<input id="wpt-require-strong-passwords-yes" name="wpt_require_strong_passwords" type="radio" value="1"<?php checked( 1, $require_strong_passwords ); ?> />
			<label for="wpt-require-strong-passwords-yes">
				<?php esc_html_e( 'Yes', 'wordpress-tools' ); ?>
			</label><br>

			<input id="wpt-require-strong-passwords-no" name="wpt_require_strong_passwords" type="radio" value="0"<?php checked( 0, $require_strong_passwords ); ?> />
			<label for="wpt-require-strong-passwords-no">
				<?php esc_html_e( 'No', 'wordpress-tools' ); ?>
			</label>
			<p class="description"><?php esc_html_e( 'Require all users to use strong passwords.', 'wordpress-tools' ); ?></p>
		</fieldset>
		<?php
	}

	/**
	 * Password Protected Content setting callback.
	 */
	public function password_protect_setting_callback() {
		$password_protect = WPT_IS_NETWORK ? get_site_option( 'wpt_password_protect', 0 ) : get_option( 'wpt_password_protect', 0 );
		?>
		<fieldset>
			<input id="wpt-password-protect" name="wpt_password_protect" type="checkbox" value="1"<?php checked( 1, $password_protect ); ?> />
			<label for="wpt-password-protect">
				<?php esc_html_e( 'Enable password protected content', 'wordpress-tools' ); ?>
			</label>
			<p class="description"><?php esc_html_e( 'Enables password protected content. WordPress default password protected post functionality is insecure and does not work with page caching.', 'wordpress-tools' ); ?></p>
		</fieldset>
		<?php
	}

	/**
	 * Disable File Modifications setting callback.
	 */
	public function disallow_file_mods_setting_callback() {
		$disallow_file_mods = WPT_IS_NETWORK ? get_site_option( 'wpt_disallow_file_mods', 'no' ) : get_option( 'wpt_disallow_file_mods', 'no' );
		?>
		<fieldset>
			<input id="wpt-disallow-file-mods-yes" name="wpt_disallow_file_mods" type="radio" value="yes"<?php checked( $disallow_file_mods, 'yes' ); ?> />
			<label for="wpt-disallow-file-mods-yes">
				<?php esc_html_e( 'Yes', 'wordpress-tools' ); ?>
			</label><br>

			<input id="wpt-disallow-file-mods-no" name="wpt_disallow_file_mods" type="radio" value="no"<?php checked( $disallow_file_mods, 'no' ); ?> />
			<label for="wpt-disallow-file-mods-no">
				<?php esc_html_e( 'No', 'wordpress-tools' ); ?>
			</label>
			<p class="description"><?php esc_html_e( 'Disables plugin and theme uploads, updates, and file editing. This sets the DISALLOW_FILE_MODS constant.', 'wordpress-tools' ); ?></p>
		</fieldset>
		<?php
	}

	/**
	 * REST API Restriction setting callback.
	 */
	public function restrict_rest_api_setting_callback() {
		$restrict = WPT_IS_NETWORK ? get_site_option( 'wpt_restrict_rest_api', 'users' ) : get_option( 'wpt_restrict_rest_api', 'users' );
		?>
		<fieldset>
			<p>
				<input id="wpt-restrict-rest-api-all" name="wpt_restrict_rest_api" type="radio" value="all"<?php checked( $restrict, 'all' ); ?> />
				<label for="wpt-restrict-rest-api-all">
					<?php esc_html_e( 'Restrict all access to authenticated users', 'wordpress-tools' ); ?>
				</label>
			</p>
			<p>
				<input id="wpt-restrict-rest-api-users" name="wpt_restrict_rest_api" type="radio" value="users"<?php checked( $restrict, 'users' ); ?> />
				<label for="wpt-restrict-rest-api-users">
					<?php
						echo wp_kses_post(
							sprintf(
								// translators: %s is a link to the developer reference for the users endpoint
								__( "Restrict access to the <code><a href='%s'>users</a></code> endpoint to authenticated users", 'wordpress-tools' ),
								esc_url( 'https://developer.wordpress.org/rest-api/reference/users/' )
							)
						);
					?>
				</label>
			</p>
			<p>
				<input id="wpt-restrict-rest-api-none" name="wpt_restrict_rest_api" type="radio" value="none"<?php checked( $restrict, 'none' ); ?> />
				<label for="wpt-restrict-rest-api-none">
					<?php esc_html_e( 'Publicly accessible', 'wordpress-tools' ); ?>
				</label>
			</p>
		</fieldset>
		<?php
	}

	/**
	 * Validate yes/no setting.
	 *
	 * @param  string $value Current value.
	 * @return string
	 */
	public function validate_yes_no_setting( $value ) {
		if ( in_array( $value, [ 'yes', 'no' ], true ) ) {
			return $value;
		}

		return 'yes';
	}

	/**
	 * Validate REST API restriction setting.
	 *
	 * @param  string $value Current restriction.
	 * @return string
	 */
	public function validate_rest_api_setting( $value ) {
		if ( in_array( $value, [ 'all', 'users', 'none' ], true ) ) {
			return $value;
		}

		// Default to 'users' in case something wrong gets sent
		return 'users';
	}

	/**
	 * Save network settings.
	 */
	public function save_network_settings() {
		if ( ! current_user_can( 'manage_network_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wordpress-tools' ) );
		}

		// Check if user can access 829 Settings
		if ( ! $this->can_access_settings() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wordpress-tools' ) );
		}

		check_admin_referer( 'wpt_829_settings-options' );

		// Save SSO setting
		if ( isset( $_POST['wpt_allow_sso'] ) ) {
			$sso_value = $this->validate_yes_no_setting( sanitize_text_field( $_POST['wpt_allow_sso'] ) );
			update_site_option( 'wpt_allow_sso', $sso_value );
		}

		// Save Comments setting
		if ( isset( $_POST['wpt_disable_comments'] ) ) {
			$comments_value = $this->validate_yes_no_setting( sanitize_text_field( $_POST['wpt_disable_comments'] ) );
			update_site_option( 'wpt_disable_comments', $comments_value );
		}

		// Save Strong Passwords setting
		if ( isset( $_POST['wpt_require_strong_passwords'] ) ) {
			$passwords_value = intval( $_POST['wpt_require_strong_passwords'] );
			update_site_option( 'wpt_require_strong_passwords', $passwords_value );
		}

		// Save Password Protected Content setting
		if ( isset( $_POST['wpt_password_protect'] ) ) {
			$password_protect_value = intval( $_POST['wpt_password_protect'] );
			update_site_option( 'wpt_password_protect', $password_protect_value );
		} else {
			// Checkbox not checked, set to 0
			update_site_option( 'wpt_password_protect', 0 );
		}

		// Save Disable File Modifications setting
		if ( isset( $_POST['wpt_disallow_file_mods'] ) ) {
			$disallow_file_mods_value = $this->validate_yes_no_setting( sanitize_text_field( $_POST['wpt_disallow_file_mods'] ) );
			update_site_option( 'wpt_disallow_file_mods', $disallow_file_mods_value );
		}

		// Save REST API Restriction setting
		if ( isset( $_POST['wpt_restrict_rest_api'] ) ) {
			$restrict_rest_api_value = $this->validate_rest_api_setting( sanitize_text_field( $_POST['wpt_restrict_rest_api'] ) );
			update_site_option( 'wpt_restrict_rest_api', $restrict_rest_api_value );
		}

		wp_safe_redirect(
			add_query_arg(
				[
					'page'    => 'wpt-829-settings',
					'updated' => 'true',
				],
				network_admin_url( 'settings.php' )
			)
		);
		exit;
	}

	/**
	 * Render the network settings page.
	 */
	public function render_network_settings_page() {
		if ( ! current_user_can( 'manage_network_options' ) ) {
			return;
		}

		// Check if user can access 829 Settings
		if ( ! $this->can_access_settings() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wordpress-tools' ) );
		}

		$allow_sso                = get_site_option( 'wpt_allow_sso', 'yes' );
		$disable_comments         = get_site_option( 'wpt_disable_comments', 'no' );
		$require_strong_passwords = get_site_option( 'wpt_require_strong_passwords', 1 );
		$password_protect         = get_site_option( 'wpt_password_protect', 0 );
		$disallow_file_mods       = get_site_option( 'wpt_disallow_file_mods', 'no' );
		$restrict_rest_api        = get_site_option( 'wpt_restrict_rest_api', 'users' );
		$is_disabled              = defined( 'WPT_DISABLE_COMMENTS' ) || has_filter( 'wpt_disable_comments' );
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<?php if ( isset( $_GET['updated'] ) ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Settings saved.', 'wordpress-tools' ); ?></p>
				</div>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( network_admin_url( 'edit.php?action=wpt_829_settings' ) ); ?>">
				<?php wp_nonce_field( 'wpt_829_settings-options' ); ?>

				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row">
								<?php esc_html_e( 'Allow 829 Studios SSO', 'wordpress-tools' ); ?>
							</th>
							<td>
								<fieldset>
									<input id="wpt-allow-sso-yes" name="wpt_allow_sso" type="radio" value="yes"<?php checked( $allow_sso, 'yes' ); ?> />
									<label for="wpt-allow-sso-yes">
										<?php esc_html_e( 'Yes', 'wordpress-tools' ); ?>
									</label><br>

									<input id="wpt-allow-sso-no" name="wpt_allow_sso" type="radio" value="no"<?php checked( $allow_sso, 'no' ); ?> />
									<label for="wpt-allow-sso-no">
										<?php esc_html_e( 'No', 'wordpress-tools' ); ?>
									</label>
									<p class="description"><?php esc_html_e( 'This allows members of 829 Studios to log in via SSO. This is extremely important to streamline maintenance of your website.', 'wordpress-tools' ); ?></p>
								</fieldset>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<?php esc_html_e( 'Disable Comments', 'wordpress-tools' ); ?>
							</th>
							<td>
								<fieldset>
									<input id="wpt-disable-comments-yes" name="wpt_disable_comments" type="radio" value="yes"<?php checked( $disable_comments, 'yes' ); ?><?php disabled( $is_disabled ); ?> />
									<label for="wpt-disable-comments-yes">
										<?php esc_html_e( 'Yes', 'wordpress-tools' ); ?>
									</label><br>

									<input id="wpt-disable-comments-no" name="wpt_disable_comments" type="radio" value="no"<?php checked( $disable_comments, 'no' ); ?><?php disabled( $is_disabled ); ?> />
									<label for="wpt-disable-comments-no">
										<?php esc_html_e( 'No', 'wordpress-tools' ); ?>
									</label>
									<p class="description"><?php esc_html_e( 'This will remove all the comments related UI from the admin and frontend.', 'wordpress-tools' ); ?></p>
								</fieldset>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<?php esc_html_e( 'Require Strong Passwords', 'wordpress-tools' ); ?>
							</th>
							<td>
								<fieldset>
									<input id="wpt-require-strong-passwords-yes" name="wpt_require_strong_passwords" type="radio" value="1"<?php checked( 1, $require_strong_passwords ); ?> />
									<label for="wpt-require-strong-passwords-yes">
										<?php esc_html_e( 'Yes', 'wordpress-tools' ); ?>
									</label><br>

									<input id="wpt-require-strong-passwords-no" name="wpt_require_strong_passwords" type="radio" value="0"<?php checked( 0, $require_strong_passwords ); ?> />
									<label for="wpt-require-strong-passwords-no">
										<?php esc_html_e( 'No', 'wordpress-tools' ); ?>
									</label>
									<p class="description"><?php esc_html_e( 'Require all users to use strong passwords.', 'wordpress-tools' ); ?></p>
								</fieldset>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<?php esc_html_e( 'Enable Password Protected Content', 'wordpress-tools' ); ?>
							</th>
							<td>
								<fieldset>
									<input id="wpt-password-protect" name="wpt_password_protect" type="checkbox" value="1"<?php checked( 1, $password_protect ); ?> />
									<label for="wpt-password-protect">
										<?php esc_html_e( 'Enable password protected content', 'wordpress-tools' ); ?>
									</label>
									<p class="description"><?php esc_html_e( 'Enables password protected content. WordPress default password protected post functionality is insecure and does not work with page caching.', 'wordpress-tools' ); ?></p>
								</fieldset>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<?php esc_html_e( 'Disable File Modifications', 'wordpress-tools' ); ?>
							</th>
							<td>
								<fieldset>
									<input id="wpt-disallow-file-mods-yes" name="wpt_disallow_file_mods" type="radio" value="yes"<?php checked( $disallow_file_mods, 'yes' ); ?> />
									<label for="wpt-disallow-file-mods-yes">
										<?php esc_html_e( 'Yes', 'wordpress-tools' ); ?>
									</label><br>

									<input id="wpt-disallow-file-mods-no" name="wpt_disallow_file_mods" type="radio" value="no"<?php checked( $disallow_file_mods, 'no' ); ?> />
									<label for="wpt-disallow-file-mods-no">
										<?php esc_html_e( 'No', 'wordpress-tools' ); ?>
									</label>
									<p class="description"><?php esc_html_e( 'Disables plugin and theme uploads, updates, and file editing. This sets the DISALLOW_FILE_MODS constant.', 'wordpress-tools' ); ?></p>
								</fieldset>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<?php esc_html_e( 'REST API Availability', 'wordpress-tools' ); ?>
							</th>
							<td>
								<fieldset>
									<p>
										<input id="wpt-restrict-rest-api-all" name="wpt_restrict_rest_api" type="radio" value="all"<?php checked( $restrict_rest_api, 'all' ); ?> />
										<label for="wpt-restrict-rest-api-all">
											<?php esc_html_e( 'Restrict all access to authenticated users', 'wordpress-tools' ); ?>
										</label>
									</p>
									<p>
										<input id="wpt-restrict-rest-api-users" name="wpt_restrict_rest_api" type="radio" value="users"<?php checked( $restrict_rest_api, 'users' ); ?> />
										<label for="wpt-restrict-rest-api-users">
											<?php
												echo wp_kses_post(
													sprintf(
														// translators: %s is a link to the developer reference for the users endpoint
														__( "Restrict access to the <code><a href='%s'>users</a></code> endpoint to authenticated users", 'wordpress-tools' ),
														esc_url( 'https://developer.wordpress.org/rest-api/reference/users/' )
													)
												);
											?>
										</label>
									</p>
									<p>
										<input id="wpt-restrict-rest-api-none" name="wpt_restrict_rest_api" type="radio" value="none"<?php checked( $restrict_rest_api, 'none' ); ?> />
										<label for="wpt-restrict-rest-api-none">
											<?php esc_html_e( 'Publicly accessible', 'wordpress-tools' ); ?>
										</label>
									</p>
								</fieldset>
							</td>
						</tr>
					</tbody>
				</table>

				<?php submit_button( esc_html__( 'Save Settings', 'wordpress-tools' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render the settings page.
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Check if user can access 829 Settings
		if ( ! $this->can_access_settings() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wordpress-tools' ) );
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<form action="options.php" method="post">
				<?php
				settings_fields( 'wpt_829_settings' );
				do_settings_sections( 'wpt-829-settings' );
				submit_button( esc_html__( 'Save Settings', 'wordpress-tools' ) );
				?>
			</form>
		</div>
		<?php
	}
}

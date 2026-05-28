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

		add_action( 'admin_post_wpt_regenerate_api_key', [ $this, 'handle_api_key_regenerate' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_settings_assets' ] );
		add_action( 'network_admin_enqueue_scripts', [ $this, 'enqueue_settings_assets' ] );
		add_action( 'wp_ajax_wpt_search_users', [ $this, 'ajax_search_users' ] );
	}

	/**
	 * Get all plugin settings with defaults.
	 *
	 * @since 1.0
	 * @return array Array of all settings with default values applied.
	 */
	public static function get_settings() {
		$defaults = [
			'allow_sso'                    => 1,
			'disable_comments'             => 0,
			'require_strong_passwords'     => 1,
			'password_protect'             => 0,
			'restrict_plugin_management'   => 1,
			'plugin_management_whitelist'  => [],
			'restrict_theme_management'    => 1,
			'theme_management_whitelist'   => [],
			'restrict_rest_api'            => 'users',
			'limit_login'                  => 1,
			'enable_mcp'                   => 1,
		];

		// Get settings from single option
		if ( WPT_IS_NETWORK ) {
			$settings = get_site_option( 'wpt_settings', [] );
		} else {
			$settings = get_option( 'wpt_settings', [] );
		}

		// Merge with defaults to ensure all keys exist
		return wp_parse_args( $settings, $defaults );
	}

	/**
	 * Get the read API key. Constant takes priority over DB value.
	 *
	 * @return string
	 */
	public static function get_dashboard_api_key() {
		if ( defined( 'WPT_DASHBOARD_API_KEY' ) && ! empty( WPT_DASHBOARD_API_KEY ) ) {
			return WPT_DASHBOARD_API_KEY;
		}

		if ( WPT_IS_NETWORK ) {
			return (string) get_site_option( 'wpt_dashboard_api_key', '' );
		}

		return (string) get_option( 'wpt_dashboard_api_key', '' );
	}

	/**
	 * Check if the current user can access 829 Settings.
	 *
	 * @return bool
	 */
	protected function can_access_settings() {
		// Check proper capability based on network activation
		if ( WPT_IS_NETWORK ) {
			if ( ! current_user_can( 'manage_network_options' ) ) {
				return false;
			}
		} elseif ( ! current_user_can( 'manage_options' ) ) {
				return false;
		}

		// If WPT_ALLOW_ADMIN_SETTINGS_ACCESS is true, allow any admin/super admin
		if ( defined( 'WPT_ALLOW_ADMIN_SETTINGS_ACCESS' ) && WPT_ALLOW_ADMIN_SETTINGS_ACCESS ) {
			return true;
		}

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
		// Register all settings as a single option
		register_setting(
			'wpt_829_settings',
			'wpt_settings',
			[
				'type'              => 'array',
				'sanitize_callback' => [ $this, 'sanitize_settings' ],
				'default'           => [
					'allow_sso'                   => 1,
					'disable_comments'            => 0,
					'require_strong_passwords'    => 1,
					'password_protect'            => 0,
					'restrict_plugin_management'  => 0,
					'plugin_management_whitelist' => [],
					'restrict_theme_management'   => 0,
					'theme_management_whitelist'  => [],
					'restrict_rest_api'           => 'users',
					'limit_login'                 => 1,
					'enable_mcp'                  => 1,
				],
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

		// Restrict Plugin Management setting field
		add_settings_field(
			'wpt_restrict_plugin_management',
			esc_html__( 'Restrict Plugin Management', 'wordpress-tools' ),
			[ $this, 'restrict_plugin_management_setting_callback' ],
			'wpt-829-settings',
			'wpt_829_general_section'
		);

		// Restrict Theme Management setting field
		add_settings_field(
			'wpt_restrict_theme_management',
			esc_html__( 'Restrict Theme Management', 'wordpress-tools' ),
			[ $this, 'restrict_theme_management_setting_callback' ],
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

		// Limit Login Attempts setting field
		add_settings_field(
			'wpt_limit_login',
			esc_html__( 'Limit Login Attempts', 'wordpress-tools' ),
			[ $this, 'limit_login_setting_callback' ],
			'wpt-829-settings',
			'wpt_829_general_section'
		);

		// MCP setting field
		add_settings_field(
			'wpt_enable_mcp',
			esc_html__( 'Enable MCP', 'wordpress-tools' ),
			[ $this, 'mcp_setting_callback' ],
			'wpt-829-settings',
			'wpt_829_general_section'
		);

		// API Key setting field
		add_settings_field(
			'wpt_dashboard_api_key',
			esc_html__( 'Dashboard Read API Key', 'wordpress-tools' ),
			[ $this, 'api_key_setting_callback' ],
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
		$settings  = self::get_settings();
		$allow_sso = $settings['allow_sso'];
		?>
		<fieldset>
			<input id="wpt-allow-sso-yes" name="wpt_settings[allow_sso]" type="radio" value="1"<?php checked( 1, $allow_sso ); ?> />
			<label for="wpt-allow-sso-yes">
				<?php esc_html_e( 'Yes', 'wordpress-tools' ); ?>
			</label><br>

			<input id="wpt-allow-sso-no" name="wpt_settings[allow_sso]" type="radio" value="0"<?php checked( 0, $allow_sso ); ?> />
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
		$settings         = self::get_settings();
		$disable_comments = $settings['disable_comments'];
		$is_disabled      = defined( 'WPT_DISABLE_COMMENTS' ) || has_filter( 'wpt_disable_comments' );
		?>
		<fieldset>
			<input id="wpt-disable-comments-yes" name="wpt_settings[disable_comments]" type="radio" value="1"<?php checked( 1, $disable_comments ); ?><?php disabled( $is_disabled ); ?> />
			<label for="wpt-disable-comments-yes">
				<?php esc_html_e( 'Yes', 'wordpress-tools' ); ?>
			</label><br>

			<input id="wpt-disable-comments-no" name="wpt_settings[disable_comments]" type="radio" value="0"<?php checked( 0, $disable_comments ); ?><?php disabled( $is_disabled ); ?> />
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
		$settings                 = self::get_settings();
		$require_strong_passwords = $settings['require_strong_passwords'];
		?>
		<fieldset>
			<input id="wpt-require-strong-passwords-yes" name="wpt_settings[require_strong_passwords]" type="radio" value="1"<?php checked( 1, $require_strong_passwords ); ?> />
			<label for="wpt-require-strong-passwords-yes">
				<?php esc_html_e( 'Yes', 'wordpress-tools' ); ?>
			</label><br>

			<input id="wpt-require-strong-passwords-no" name="wpt_settings[require_strong_passwords]" type="radio" value="0"<?php checked( 0, $require_strong_passwords ); ?> />
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
		$settings         = self::get_settings();
		$password_protect = $settings['password_protect'];
		?>
		<fieldset>
			<input id="wpt-password-protect" name="wpt_settings[password_protect]" type="checkbox" value="1"<?php checked( 1, $password_protect ); ?> />
			<label for="wpt-password-protect">
				<?php esc_html_e( 'Enable password protected content', 'wordpress-tools' ); ?>
			</label>
			<p class="description"><?php esc_html_e( 'Enables password protected content. WordPress default password protected post functionality is insecure and does not work with page caching.', 'wordpress-tools' ); ?></p>
		</fieldset>
		<?php
	}

	/**
	 * Restrict Plugin Management setting callback.
	 */
	public function restrict_plugin_management_setting_callback() {
		$settings                   = self::get_settings();
		$restrict_plugin_management = $settings['restrict_plugin_management'];
		$whitelist                  = isset( $settings['plugin_management_whitelist'] ) ? $settings['plugin_management_whitelist'] : [];
		?>
		<fieldset>
			<input id="wpt-restrict-plugin-management-yes" name="wpt_settings[restrict_plugin_management]" type="radio" value="1"<?php checked( 1, $restrict_plugin_management ); ?> />
			<label for="wpt-restrict-plugin-management-yes">
				<?php esc_html_e( 'Yes', 'wordpress-tools' ); ?>
			</label><br>

			<input id="wpt-restrict-plugin-management-no" name="wpt_settings[restrict_plugin_management]" type="radio" value="0"<?php checked( 0, $restrict_plugin_management ); ?> />
			<label for="wpt-restrict-plugin-management-no">
				<?php esc_html_e( 'No', 'wordpress-tools' ); ?>
			</label>
			<p class="description"><?php esc_html_e( 'Only allows administrators with @829llc.com emails to install, update, or delete plugins.', 'wordpress-tools' ); ?></p>

			<div class="wpt-whitelist-container" data-restriction="restrict_plugin_management"<?php echo $restrict_plugin_management ? '' : ' style="display:none"'; ?>>
				<p class="description" style="margin: 0 0 4px;"><strong><?php esc_html_e( 'Whitelisted Users', 'wordpress-tools' ); ?></strong></p>
				<p class="description" style="margin: 0 0 8px;"><?php esc_html_e( 'These users can manage plugins even when restriction is enabled.', 'wordpress-tools' ); ?></p>
				<?php $this->render_user_search_field( 'plugin_management_whitelist', $whitelist ); ?>
			</div>
		</fieldset>
		<?php
	}

	/**
	 * Restrict Theme Management setting callback.
	 */
	public function restrict_theme_management_setting_callback() {
		$settings                  = self::get_settings();
		$restrict_theme_management = $settings['restrict_theme_management'];
		$whitelist                 = isset( $settings['theme_management_whitelist'] ) ? $settings['theme_management_whitelist'] : [];
		?>
		<fieldset>
			<input id="wpt-restrict-theme-management-yes" name="wpt_settings[restrict_theme_management]" type="radio" value="1"<?php checked( 1, $restrict_theme_management ); ?> />
			<label for="wpt-restrict-theme-management-yes">
				<?php esc_html_e( 'Yes', 'wordpress-tools' ); ?>
			</label><br>

			<input id="wpt-restrict-theme-management-no" name="wpt_settings[restrict_theme_management]" type="radio" value="0"<?php checked( 0, $restrict_theme_management ); ?> />
			<label for="wpt-restrict-theme-management-no">
				<?php esc_html_e( 'No', 'wordpress-tools' ); ?>
			</label>
			<p class="description"><?php esc_html_e( 'Only allows administrators with @829llc.com emails to install, update, or delete themes.', 'wordpress-tools' ); ?></p>

			<div class="wpt-whitelist-container" data-restriction="restrict_theme_management"<?php echo $restrict_theme_management ? '' : ' style="display:none"'; ?>>
				<p class="description" style="margin: 0 0 4px;"><strong><?php esc_html_e( 'Whitelisted Users', 'wordpress-tools' ); ?></strong></p>
				<p class="description" style="margin: 0 0 8px;"><?php esc_html_e( 'These users can manage themes even when restriction is enabled.', 'wordpress-tools' ); ?></p>
				<?php $this->render_user_search_field( 'theme_management_whitelist', $whitelist ); ?>
			</div>
		</fieldset>
		<?php
	}

	/**
	 * Render a user search field with tag-style selected users.
	 *
	 * @param string $setting_key Settings key for the whitelist array.
	 * @param array  $user_ids    Currently saved user IDs.
	 */
	protected function render_user_search_field( $setting_key, $user_ids ) {
		$user_ids = array_filter( array_map( 'intval', (array) $user_ids ) );
		$users    = ! empty( $user_ids ) ? get_users( [ 'include' => $user_ids ] ) : [];
		?>
		<div class="wpt-user-search" data-setting-key="<?php echo esc_attr( $setting_key ); ?>">
			<div class="wpt-user-tags">
				<?php foreach ( $users as $user ) : ?>
					<span class="wpt-user-tag">
						<span class="wpt-user-tag-label"><?php echo esc_html( $user->display_name . ' (' . $user->user_email . ')' ); ?></span>
						<button type="button" class="wpt-user-tag-remove" data-user-id="<?php echo esc_attr( $user->ID ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Remove %s', 'wordpress-tools' ), $user->display_name ) ); ?>">&times;</button>
					</span>
				<?php endforeach; ?>
			</div>
			<div class="wpt-user-ids">
				<?php foreach ( $user_ids as $uid ) : ?>
					<input type="hidden" name="wpt_settings[<?php echo esc_attr( $setting_key ); ?>][]" value="<?php echo esc_attr( $uid ); ?>" />
				<?php endforeach; ?>
			</div>
			<input type="text" class="wpt-user-search-input regular-text" placeholder="<?php esc_attr_e( 'Search users…', 'wordpress-tools' ); ?>" autocomplete="off" />
		</div>
		<?php
	}

	/**
	 * REST API Restriction setting callback.
	 */
	public function restrict_rest_api_setting_callback() {
		$settings = self::get_settings();
		$restrict = $settings['restrict_rest_api'];
		?>
		<fieldset>
			<p>
				<input id="wpt-restrict-rest-api-all" name="wpt_settings[restrict_rest_api]" type="radio" value="all"<?php checked( $restrict, 'all' ); ?> />
				<label for="wpt-restrict-rest-api-all">
					<?php esc_html_e( 'Restrict all access to authenticated users', 'wordpress-tools' ); ?>
				</label>
			</p>
			<p>
				<input id="wpt-restrict-rest-api-users" name="wpt_settings[restrict_rest_api]" type="radio" value="users"<?php checked( $restrict, 'users' ); ?> />
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
				<input id="wpt-restrict-rest-api-none" name="wpt_settings[restrict_rest_api]" type="radio" value="none"<?php checked( $restrict, 'none' ); ?> />
				<label for="wpt-restrict-rest-api-none">
					<?php esc_html_e( 'Publicly accessible', 'wordpress-tools' ); ?>
				</label>
			</p>
		</fieldset>
		<?php
	}

	/**
	 * Limit Login Attempts setting callback.
	 */
	public function limit_login_setting_callback() {
		$settings        = self::get_settings();
		$limit_login     = $settings['limit_login'];
		$attempt_limit   = defined( 'WPT_LOGIN_ATTEMPT_LIMIT' ) ? WPT_LOGIN_ATTEMPT_LIMIT : 10;
		$lockout_minutes = defined( 'WPT_LOGIN_LOCKOUT_DURATION' ) ? ceil( WPT_LOGIN_LOCKOUT_DURATION / 60 ) : 15;
		?>
		<fieldset>
			<input id="wpt-limit-login-yes" name="wpt_settings[limit_login]" type="radio" value="1"<?php checked( 1, $limit_login ); ?> />
			<label for="wpt-limit-login-yes">
				<?php esc_html_e( 'Yes', 'wordpress-tools' ); ?>
			</label><br>

			<input id="wpt-limit-login-no" name="wpt_settings[limit_login]" type="radio" value="0"<?php checked( 0, $limit_login ); ?> />
			<label for="wpt-limit-login-no">
				<?php esc_html_e( 'No', 'wordpress-tools' ); ?>
			</label>
			<p class="description">
				<?php
					echo esc_html(
						sprintf(
							/* translators: 1: attempt limit, 2: lockout minutes */
							__( 'Limits login attempts to %1$d per IP address within 5 minutes. After %1$d failed attempts, the IP is locked out for %2$d minutes.', 'wordpress-tools' ),
							$attempt_limit,
							$lockout_minutes
						)
					);
				?>
			</p>
		</fieldset>
		<?php
	}

	/**
	 * MCP setting callback.
	 */
	public function mcp_setting_callback() {
		$settings   = self::get_settings();
		$enable_mcp = $settings['enable_mcp'];
		?>
		<fieldset>
			<input id="wpt-enable-mcp-yes" name="wpt_settings[enable_mcp]" type="radio" value="1"<?php checked( 1, $enable_mcp ); ?> />
			<label for="wpt-enable-mcp-yes">
				<?php esc_html_e( 'Yes', 'wordpress-tools' ); ?>
			</label><br>

			<input id="wpt-enable-mcp-no" name="wpt_settings[enable_mcp]" type="radio" value="0"<?php checked( 0, $enable_mcp ); ?> />
			<label for="wpt-enable-mcp-no">
				<?php esc_html_e( 'No', 'wordpress-tools' ); ?>
			</label>
			<p class="description"><?php esc_html_e( 'Exposes site management tools (plugins, themes, users, system info) via the WordPress MCP Adapter.', 'wordpress-tools' ); ?></p>
		</fieldset>
		<?php
	}

	/**
	 * API Key setting callback.
	 */
	public function api_key_setting_callback() {
		if ( defined( 'WPT_DASHBOARD_API_KEY' ) && ! empty( WPT_DASHBOARD_API_KEY ) ) {
			?>
			<input type="text" value="<?php echo esc_attr( WPT_DASHBOARD_API_KEY ); ?>" class="regular-text" readonly />
			<p class="description"><?php esc_html_e( 'Defined in wp-config.php', 'wordpress-tools' ); ?></p>
			<?php
			return;
		}

		$key     = self::get_dashboard_api_key();
		$has_key = ! empty( $key );

		if ( $has_key ) {
			?>
			<input type="text" value="<?php echo esc_attr( $key ); ?>" class="regular-text" readonly />
			<?php
		} else {
			?>
			<input type="text" value="<?php esc_attr_e( 'No key generated', 'wordpress-tools' ); ?>" class="regular-text" disabled />
			<?php
		}
		?>
		<?php
		$regenerate_url = wp_nonce_url(
			admin_url( 'admin-post.php?action=wpt_regenerate_api_key' ),
			'wpt_regenerate_api_key'
		);
		?>
		<a href="<?php echo esc_url( $regenerate_url ); ?>" class="button button-secondary">
			<?php echo $has_key ? esc_html__( 'Regenerate Key', 'wordpress-tools' ) : esc_html__( 'Generate Key', 'wordpress-tools' ); ?>
		</a>
		<?php
	}

	/**
	 * Handle API key regeneration.
	 */
	public function handle_api_key_regenerate() {
		check_admin_referer( 'wpt_regenerate_api_key' );

		if ( WPT_IS_NETWORK ) {
			if ( ! current_user_can( 'manage_network_options' ) ) {
				wp_die( esc_html__( 'You do not have permission to perform this action.', 'wordpress-tools' ) );
			}
		} elseif ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'wordpress-tools' ) );
		}

		if ( defined( 'WPT_DASHBOARD_API_KEY' ) && ! empty( WPT_DASHBOARD_API_KEY ) ) {
			wp_die( esc_html__( 'API key is defined in wp-config.php and cannot be regenerated.', 'wordpress-tools' ) );
		}

		$key = wp_generate_password( 40, false );

		if ( WPT_IS_NETWORK ) {
			update_site_option( 'wpt_dashboard_api_key', $key );
		} else {
			update_option( 'wpt_dashboard_api_key', $key );
		}

		$redirect = WPT_IS_NETWORK
			? add_query_arg(
				[
					'page'    => 'wpt-829-settings',
					'updated' => 'true',
				],
				network_admin_url( 'settings.php' )
			)
			: admin_url( 'options-general.php?page=wpt-829-settings' );

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Ensure an API key exists in the database.
	 * Called on activation and plugin update.
	 */
	public static function maybe_generate_api_key() {
		if ( ! empty( self::get_dashboard_api_key() ) ) {
			return;
		}

		$key = wp_generate_password( 40, false );

		if ( WPT_IS_NETWORK ) {
			update_site_option( 'wpt_dashboard_api_key', $key );
		} else {
			update_option( 'wpt_dashboard_api_key', $key );
		}
	}

	/**
	 * Sanitize all settings.
	 *
	 * @param  array $input Raw input values.
	 * @return array Sanitized settings.
	 */
	public function sanitize_settings( $input ) {
		$sanitized = [];

		// Sanitize allow_sso
		$sanitized['allow_sso'] = isset( $input['allow_sso'] ) ? intval( $input['allow_sso'] ) : 1;

		// Sanitize disable_comments
		$sanitized['disable_comments'] = isset( $input['disable_comments'] ) ? intval( $input['disable_comments'] ) : 0;

		// Sanitize require_strong_passwords
		$sanitized['require_strong_passwords'] = isset( $input['require_strong_passwords'] ) ? intval( $input['require_strong_passwords'] ) : 1;

		// Sanitize password_protect
		$sanitized['password_protect'] = isset( $input['password_protect'] ) ? intval( $input['password_protect'] ) : 0;

		// Sanitize restrict_plugin_management
		$sanitized['restrict_plugin_management'] = isset( $input['restrict_plugin_management'] ) ? intval( $input['restrict_plugin_management'] ) : 0;

		// Sanitize plugin_management_whitelist
		$sanitized['plugin_management_whitelist'] = isset( $input['plugin_management_whitelist'] )
			? array_values( array_filter( array_map( 'intval', (array) $input['plugin_management_whitelist'] ) ) )
			: [];

		// Sanitize restrict_theme_management
		$sanitized['restrict_theme_management'] = isset( $input['restrict_theme_management'] ) ? intval( $input['restrict_theme_management'] ) : 0;

		// Sanitize theme_management_whitelist
		$sanitized['theme_management_whitelist'] = isset( $input['theme_management_whitelist'] )
			? array_values( array_filter( array_map( 'intval', (array) $input['theme_management_whitelist'] ) ) )
			: [];

		// Sanitize restrict_rest_api
		if ( isset( $input['restrict_rest_api'] ) && in_array( $input['restrict_rest_api'], [ 'all', 'users', 'none' ], true ) ) {
			$sanitized['restrict_rest_api'] = $input['restrict_rest_api'];
		} else {
			$sanitized['restrict_rest_api'] = 'users';
		}

		// Sanitize limit_login
		$sanitized['limit_login'] = isset( $input['limit_login'] ) ? intval( $input['limit_login'] ) : 1;

		// Sanitize enable_mcp
		$sanitized['enable_mcp'] = isset( $input['enable_mcp'] ) ? intval( $input['enable_mcp'] ) : 1;

		return $sanitized;
	}

	/**
	 * Enqueue assets on the settings page.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_settings_assets( $hook ) {
		if ( false === strpos( $hook, 'wpt-829-settings' ) ) {
			return;
		}

		wp_enqueue_script(
			'wpt-settings-user-search',
			WPT_PLUGIN_URL . 'assets/js/settings-user-search.js',
			[],
			filemtime( WPT_PLUGIN_DIR . 'assets/js/settings-user-search.js' ),
			true
		);

		wp_enqueue_style(
			'wpt-settings-user-search',
			WPT_PLUGIN_URL . 'assets/css/settings-user-search.css',
			[],
			filemtime( WPT_PLUGIN_DIR . 'assets/css/settings-user-search.css' )
		);

		wp_localize_script(
			'wpt-settings-user-search',
			'wptUserSearch',
			[
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( 'wpt_search_users' ),
				'removeLabel' => __( 'Remove', 'wordpress-tools' ),
			]
		);
	}

	/**
	 * AJAX handler: search users by name or email.
	 */
	public function ajax_search_users() {
		check_ajax_referer( 'wpt_search_users', 'nonce' );

		if ( ! $this->can_access_settings() ) {
			wp_send_json_error( 'Unauthorized' );
			return;
		}

		$search  = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
		$exclude = isset( $_POST['exclude'] ) ? array_filter( array_map( 'intval', (array) wp_unslash( $_POST['exclude'] ) ) ) : [];

		$users = get_users(
			[
				'search'         => '*' . $search . '*',
				'search_columns' => [ 'user_login', 'user_email', 'display_name', 'user_nicename' ],
				'exclude'        => $exclude,
				'number'         => 10,
			]
		);

		$results = array_values(
			array_map(
				function ( $user ) {
					return [
						'id'    => $user->ID,
						'label' => $user->display_name . ' (' . $user->user_email . ')',
						'value' => $user->display_name,
					];
				},
				$users
			)
		);

		wp_send_json_success( $results );
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

		// Get settings from POST data
		$input = isset( $_POST['wpt_settings'] ) ? $_POST['wpt_settings'] : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		// Sanitize all settings
		$sanitized_settings = $this->sanitize_settings( $input );

		// Save settings as a single option
		update_site_option( 'wpt_settings', $sanitized_settings );

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

		$settings                    = self::get_settings();
		$allow_sso                   = $settings['allow_sso'];
		$disable_comments            = $settings['disable_comments'];
		$require_strong_passwords    = $settings['require_strong_passwords'];
		$password_protect            = $settings['password_protect'];
		$restrict_plugin_management  = $settings['restrict_plugin_management'];
		$plugin_management_whitelist = isset( $settings['plugin_management_whitelist'] ) ? $settings['plugin_management_whitelist'] : [];
		$restrict_theme_management   = $settings['restrict_theme_management'];
		$theme_management_whitelist  = isset( $settings['theme_management_whitelist'] ) ? $settings['theme_management_whitelist'] : [];
		$restrict_rest_api           = $settings['restrict_rest_api'];
		$limit_login                = $settings['limit_login'];
		$enable_mcp                 = $settings['enable_mcp'];
		$attempt_limit              = defined( 'WPT_LOGIN_ATTEMPT_LIMIT' ) ? WPT_LOGIN_ATTEMPT_LIMIT : 10;
		$lockout_minutes            = defined( 'WPT_LOGIN_LOCKOUT_DURATION' ) ? ceil( WPT_LOGIN_LOCKOUT_DURATION / 60 ) : 15;
		$is_disabled                = defined( 'WPT_DISABLE_COMMENTS' ) || has_filter( 'wpt_disable_comments' );
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<?php
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( isset( $_GET['updated'] ) ) :
				?>

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
									<input id="wpt-allow-sso-yes" name="wpt_settings[allow_sso]" type="radio" value="1"<?php checked( 1, $allow_sso ); ?> />
									<label for="wpt-allow-sso-yes">
										<?php esc_html_e( 'Yes', 'wordpress-tools' ); ?>
									</label><br>

									<input id="wpt-allow-sso-no" name="wpt_settings[allow_sso]" type="radio" value="0"<?php checked( 0, $allow_sso ); ?> />
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
									<input id="wpt-disable-comments-yes" name="wpt_settings[disable_comments]" type="radio" value="1"<?php checked( 1, $disable_comments ); ?><?php disabled( $is_disabled ); ?> />
									<label for="wpt-disable-comments-yes">
										<?php esc_html_e( 'Yes', 'wordpress-tools' ); ?>
									</label><br>

									<input id="wpt-disable-comments-no" name="wpt_settings[disable_comments]" type="radio" value="0"<?php checked( 0, $disable_comments ); ?><?php disabled( $is_disabled ); ?> />
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
									<input id="wpt-require-strong-passwords-yes" name="wpt_settings[require_strong_passwords]" type="radio" value="1"<?php checked( 1, $require_strong_passwords ); ?> />
									<label for="wpt-require-strong-passwords-yes">
										<?php esc_html_e( 'Yes', 'wordpress-tools' ); ?>
									</label><br>

									<input id="wpt-require-strong-passwords-no" name="wpt_settings[require_strong_passwords]" type="radio" value="0"<?php checked( 0, $require_strong_passwords ); ?> />
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
									<input id="wpt-password-protect" name="wpt_settings[password_protect]" type="checkbox" value="1"<?php checked( 1, $password_protect ); ?> />
									<label for="wpt-password-protect">
										<?php esc_html_e( 'Enable password protected content', 'wordpress-tools' ); ?>
									</label>
									<p class="description"><?php esc_html_e( 'Enables password protected content. WordPress default password protected post functionality is insecure and does not work with page caching.', 'wordpress-tools' ); ?></p>
								</fieldset>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<?php esc_html_e( 'Restrict Plugin Management', 'wordpress-tools' ); ?>
							</th>
							<td>
								<fieldset>
									<input id="wpt-restrict-plugin-management-yes" name="wpt_settings[restrict_plugin_management]" type="radio" value="1"<?php checked( 1, $restrict_plugin_management ); ?> />
									<label for="wpt-restrict-plugin-management-yes">
										<?php esc_html_e( 'Yes', 'wordpress-tools' ); ?>
									</label><br>

									<input id="wpt-restrict-plugin-management-no" name="wpt_settings[restrict_plugin_management]" type="radio" value="0"<?php checked( 0, $restrict_plugin_management ); ?> />
									<label for="wpt-restrict-plugin-management-no">
										<?php esc_html_e( 'No', 'wordpress-tools' ); ?>
									</label>
									<p class="description"><?php esc_html_e( 'Only allows administrators with @829llc.com emails to install, update, or delete plugins.', 'wordpress-tools' ); ?></p>

									<div class="wpt-whitelist-container" data-restriction="restrict_plugin_management"<?php echo $restrict_plugin_management ? '' : ' style="display:none"'; ?>>
										<p class="description" style="margin: 0 0 4px;"><strong><?php esc_html_e( 'Whitelisted Users', 'wordpress-tools' ); ?></strong></p>
										<p class="description" style="margin: 0 0 8px;"><?php esc_html_e( 'These users can manage plugins even when restriction is enabled.', 'wordpress-tools' ); ?></p>
										<?php $this->render_user_search_field( 'plugin_management_whitelist', $plugin_management_whitelist ); ?>
									</div>
								</fieldset>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<?php esc_html_e( 'Restrict Theme Management', 'wordpress-tools' ); ?>
							</th>
							<td>
								<fieldset>
									<input id="wpt-restrict-theme-management-yes" name="wpt_settings[restrict_theme_management]" type="radio" value="1"<?php checked( 1, $restrict_theme_management ); ?> />
									<label for="wpt-restrict-theme-management-yes">
										<?php esc_html_e( 'Yes', 'wordpress-tools' ); ?>
									</label><br>

									<input id="wpt-restrict-theme-management-no" name="wpt_settings[restrict_theme_management]" type="radio" value="0"<?php checked( 0, $restrict_theme_management ); ?> />
									<label for="wpt-restrict-theme-management-no">
										<?php esc_html_e( 'No', 'wordpress-tools' ); ?>
									</label>
									<p class="description"><?php esc_html_e( 'Only allows administrators with @829llc.com emails to install, update, or delete themes.', 'wordpress-tools' ); ?></p>

									<div class="wpt-whitelist-container" data-restriction="restrict_theme_management"<?php echo $restrict_theme_management ? '' : ' style="display:none"'; ?>>
										<p class="description" style="margin: 0 0 4px;"><strong><?php esc_html_e( 'Whitelisted Users', 'wordpress-tools' ); ?></strong></p>
										<p class="description" style="margin: 0 0 8px;"><?php esc_html_e( 'These users can manage themes even when restriction is enabled.', 'wordpress-tools' ); ?></p>
										<?php $this->render_user_search_field( 'theme_management_whitelist', $theme_management_whitelist ); ?>
									</div>
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
										<input id="wpt-restrict-rest-api-all" name="wpt_settings[restrict_rest_api]" type="radio" value="all"<?php checked( $restrict_rest_api, 'all' ); ?> />
										<label for="wpt-restrict-rest-api-all">
											<?php esc_html_e( 'Restrict all access to authenticated users', 'wordpress-tools' ); ?>
										</label>
									</p>
									<p>
										<input id="wpt-restrict-rest-api-users" name="wpt_settings[restrict_rest_api]" type="radio" value="users"<?php checked( $restrict_rest_api, 'users' ); ?> />
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
										<input id="wpt-restrict-rest-api-none" name="wpt_settings[restrict_rest_api]" type="radio" value="none"<?php checked( $restrict_rest_api, 'none' ); ?> />
										<label for="wpt-restrict-rest-api-none">
											<?php esc_html_e( 'Publicly accessible', 'wordpress-tools' ); ?>
										</label>
									</p>
								</fieldset>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<?php esc_html_e( 'Limit Login Attempts', 'wordpress-tools' ); ?>
							</th>
							<td>
								<fieldset>
									<input id="wpt-limit-login-yes" name="wpt_settings[limit_login]" type="radio" value="1"<?php checked( 1, $limit_login ); ?> />
									<label for="wpt-limit-login-yes">
										<?php esc_html_e( 'Yes', 'wordpress-tools' ); ?>
									</label><br>

									<input id="wpt-limit-login-no" name="wpt_settings[limit_login]" type="radio" value="0"<?php checked( 0, $limit_login ); ?> />
									<label for="wpt-limit-login-no">
										<?php esc_html_e( 'No', 'wordpress-tools' ); ?>
									</label>
									<p class="description">
										<?php
											echo esc_html(
												sprintf(
													/* translators: 1: attempt limit, 2: lockout minutes */
													__( 'Limits login attempts to %1$d per IP address within 5 minutes. After %1$d failed attempts, the IP is locked out for %2$d minutes.', 'wordpress-tools' ),
													$attempt_limit,
													$lockout_minutes
												)
											);
										?>
									</p>
								</fieldset>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<?php esc_html_e( 'Enable MCP', 'wordpress-tools' ); ?>
							</th>
							<td>
								<fieldset>
									<input id="wpt-enable-mcp-yes" name="wpt_settings[enable_mcp]" type="radio" value="1"<?php checked( 1, $enable_mcp ); ?> />
									<label for="wpt-enable-mcp-yes">
										<?php esc_html_e( 'Yes', 'wordpress-tools' ); ?>
									</label><br>

									<input id="wpt-enable-mcp-no" name="wpt_settings[enable_mcp]" type="radio" value="0"<?php checked( 0, $enable_mcp ); ?> />
									<label for="wpt-enable-mcp-no">
										<?php esc_html_e( 'No', 'wordpress-tools' ); ?>
									</label>
									<p class="description"><?php esc_html_e( 'Exposes site management tools (plugins, themes, users, system info) via the WordPress MCP Adapter.', 'wordpress-tools' ); ?></p>
								</fieldset>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<?php esc_html_e( 'Dashboard Read API Key', 'wordpress-tools' ); ?>
							</th>
							<td>
								<?php $this->api_key_setting_callback(); ?>
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

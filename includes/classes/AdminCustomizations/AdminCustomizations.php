<?php
/**
 * Admin customizations
 *
 * @package  WordPressTools
 */

namespace WordPressTools\AdminCustomizations;

use WordPressTools\Singleton;
use function WordPressTools\Utils\is_local_environment;
/**
 * Admin Customizations class
 */
class AdminCustomizations {

	use Singleton;

	/**
	 * Setup module
	 */
	public function setup() {
		add_filter( 'admin_footer_text', [ $this, 'filter_admin_footer_text' ] );
		add_action( 'admin_bar_menu', [ $this, 'add_toolbar_item' ], 7 );
		add_action( 'admin_print_styles', [ $this, 'print_admin_styles' ] );
		add_action( 'wp_print_styles', [ $this, 'print_admin_styles' ] );
	}

	/**
	 * Print admin styles
	 */
	public function print_admin_styles() {
		if ( ! is_user_logged_in() ) {
			return;
		}

		?>
		<style>
		.wpt-environment-indicator {
			color: #fff;
			pointer-events: none;
		}

		.wpt-environment-indicator--production {
			background-color: #b92a2a !important;
		}

		.wpt-environment-indicator--staging {
			background-color: #d79d00 !important;
		}

		.wpt-environment-indicator--local,
		.wpt-environment-indicator--development {
			background-color: #34863b !important;
		}

		</style>
		<?php
	}

	/**
	 * Filter admin footer text "Thank you for creating..."
	 *
	 * @return string
	 */
	public function filter_admin_footer_text() {
		$new_text = sprintf( __( 'Thank you for creating with <a href="https://wordpress.org">WordPress</a> and <a href="https://829studios.com">829 Studios</a>.', 'wordpress-tools' ) );
		return $new_text;
	}

	/**
	 * Add environment indicator to admin bar
	 *
	 * @param WP_Admin_Bar $admin_bar Admin bar instance
	 */
	public function add_toolbar_item( $admin_bar ) {
		$environment = wp_get_environment_type();

		$admin_bar->add_menu(
			[
				'id'     => 'wpt-environment-indicator',
				'parent' => 'top-secondary',
				'title'  => '<span class="ab-icon" aria-hidden="true"></span><span class="ab-label">' . esc_html( $this->get_environment_label( $environment ) ) . '</span>',
				'meta'   => [
					'class' => esc_attr( "wpt-environment-indicator wpt-environment-indicator--$environment" ),
				],
			]
		);
	}

	/**
	 * Get human readable label for environment
	 *
	 * @param string $environment Environment type
	 *
	 * @return string
	 */
	public function get_environment_label( $environment ) {
		switch ( $environment ) {
			case 'development':
			case 'local':
				$label = __( 'Development', 'wordpress-tools' );
				break;
			case 'staging':
				$label = __( 'Staging', 'wordpress-tools' );
				break;
			default:
				$label = __( 'Production', 'wordpress-tools' );
				break;
		}

		return $label;
	}
}

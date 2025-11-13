<?php
/**
 * Admin customizations
 *
 * @package  WordPressTools
 */

namespace WordPressTools\AdminCustomizations;

use WordPressTools\Singleton;

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
}

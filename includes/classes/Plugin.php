<?php
/**
 * Plugin setup and initialization class
 *
 * @package WordPressTools
 */

namespace WordPressTools;

/**
 * Plugin
 */
class Plugin {

	/**
	 * @var Plugin|null Instance of this class.
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance of this class
	 *
	 * @return Plugin
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		new SSO();
	}
}

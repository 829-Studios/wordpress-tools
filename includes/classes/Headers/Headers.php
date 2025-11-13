<?php
/**
 * Header customizations
 *
 * @package  WordPressTools
 */

namespace WordPressTools\Headers;

use WordPressTools\Singleton;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Headers class
 */
class Headers {

	use Singleton;

	/**
	 * Setup module
	 */
	public function setup() {
		add_action( 'wp_headers', [ $this, 'maybe_set_frame_option_header' ], 99, 1 );
	}

	/**
	 * Set the X-Frame-Options header to 'SAMEORIGIN' to prevent clickjacking attacks
	 *
	 * @param string $headers Headers
	 */
	public function maybe_set_frame_option_header( $headers ) {

		// Allow omission of this header
		if ( true === apply_filters( 'wpt_disable_x_frame_options', false ) ) {
			return $headers;
		}

		// Valid header values are `SAMEORIGIN` (allow iframe on same domain) | `DENY` (do not allow anywhere)
		$header_value               = apply_filters( 'wpt_x_frame_options', 'SAMEORIGIN' );
		$headers['X-Frame-Options'] = $header_value;
		return $headers;
	}
}

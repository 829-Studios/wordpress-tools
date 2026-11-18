<?php
/**
 * Random content redirect
 *
 * @package  WordPressTools
 */

namespace WordPressTools\RandomContent;

use WordPressTools\Singleton;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Random Content class
 *
 * Turns off the /random/ redirect to a random post added in WordPress 7.2.
 */
class RandomContent {

	use Singleton;

	/**
	 * Setup module
	 */
	public function setup() {
		// Each hit runs an uncacheable ORDER BY RAND() query, and it can surface posts meant to stay out of search.
		add_filter( 'wp_enable_random_content_redirect', '__return_false' );
	}
}

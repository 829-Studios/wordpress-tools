<?php
/**
 * Optionally disable post password protection
 *
 * @package  WordPressTools
 */

namespace WordPressTools\PostPasswords;

use WordPressTools\Singleton;
use function WordPressTools\Utils\get_maybe_site_option;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Post passwords class
 */
class PostPasswords {

	use Singleton;

	/**
	 * Setup module
	 *
	 * @since 1.7
	 */
	public function setup() {
		add_action( 'admin_print_footer_scripts', [ $this, 'print_admin_css' ] );
	}

	/**
	 * Disable password protect optionally
	 */
	public function print_admin_css() {
		global $pagenow, $post;

		if ( empty( $pagenow ) || ( 'post.php' !== $pagenow && 'post-new.php' !== $pagenow ) ) {
			return;
		}

		$password_protect = (bool) get_maybe_site_option( 'wpt_password_protect', 0 );

		if ( ! empty( $password_protect ) || ! empty( $post->post_password ) ) {
			return;
		}

		?>
		<style type="text/css">
		#visibility-radio-password,
		label[for="visibility-radio-password"] {
			display: none;
		}

		#editor-post-password-0,
		.editor-change-status__password-fieldset,
		label[for="editor-post-password-0"],
		#editor-post-password-0-description,
		#editor-post-password-1,
		label[for="editor-post-password-1"],
		#editor-post-password-1-description {
			display: none;
		}
		</style>
		<?php
	}
}

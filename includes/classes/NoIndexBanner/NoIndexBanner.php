<?php
/**
 * NoIndex Banner — frontend alert for 829 users on no-indexed production pages.
 *
 * @package  WordPressTools
 */

namespace WordPressTools\NoIndexBanner;

use WordPressTools\Singleton;
use WordPressTools\Settings\Settings;
use function WordPressTools\Utils\is_829_user;
use function WordPressTools\Utils\is_domain_or_subdomain;
use function WordPressTools\Utils\is_local_environment;
use function WordPressTools\Utils\is_staging_domain;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * NoIndexBanner class
 *
 * Displays a thin warning banner on the frontend when a page has noindex set
 * on what appears to be a production domain. Only visible to logged-in 829 users.
 */
class NoIndexBanner {

	use Singleton;

	/**
	 * Whether the current page has noindex set after all filters have run.
	 *
	 * @var bool
	 */
	private $page_is_noindex = false;

	/**
	 * Setup hooks.
	 */
	public function setup() {
		$settings = Settings::get_settings();

		// Domains only, not WP_ENVIRONMENT_TYPE, so a live site misconfigured as staging still gets the warning.
		if ( ! $settings['noindex_banner_enabled'] || is_admin() || is_staging_domain() || is_local_environment() || $this->is_excluded_domain( $settings ) ) {
			return;
		}

		// Capture the final noindex state after every plugin has had its say.
		add_filter( 'wp_robots', [ $this, 'capture_noindex_status' ], PHP_INT_MAX );
		add_action( 'wp_footer', [ $this, 'maybe_render_banner' ] );
	}

	/**
	 * Whether the site is on a user-excluded domain.
	 *
	 * @param  array $settings Plugin settings.
	 * @return bool
	 */
	private function is_excluded_domain( array $settings ): bool {
		$current_domain = wp_parse_url( site_url(), PHP_URL_HOST );
		$excluded       = array_filter( array_map( 'trim', (array) ( $settings['noindex_banner_excluded_domains'] ?? [] ) ) );

		foreach ( $excluded as $domain ) {
			if ( is_domain_or_subdomain( $current_domain, $domain ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Record whether noindex is set after all wp_robots filters have run.
	 *
	 * @param  array $robots Robots directives.
	 * @return array
	 */
	public function capture_noindex_status( array $robots ): array {
		if ( ! empty( $robots['noindex'] ) ) {
			$this->page_is_noindex = true;
		}

		return $robots;
	}

	/**
	 * Render the banner if the page is noindex and the current user is a 829 employee.
	 *
	 * @return void
	 */
	public function maybe_render_banner(): void {
		if ( ! $this->page_is_noindex || ! is_user_logged_in() || ! is_829_user() ) {
			return;
		}

		$admin_bar = is_admin_bar_showing();
		?>
		<style>
			#wpt-noindex-banner {
				position: fixed;
				top: 0;
				left: 0;
				right: 0;
				z-index: 99998;
				background: #b32d2e;
				color: #fff;
				font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, sans-serif;
				font-size: 13px;
				font-weight: 500;
				line-height: 1;
				text-align: center;
				padding: 9px 16px;
				box-shadow: 0 1px 4px rgba(0, 0, 0, 0.25);
			}
			<?php if ( $admin_bar ) : ?>
			body.admin-bar #wpt-noindex-banner {
				top: 32px;
			}
			@media screen and (max-width: 782px) {
				body.admin-bar #wpt-noindex-banner {
					top: 46px;
				}
			}
			<?php endif; ?>
		</style>
		<div id="wpt-noindex-banner" role="alert">
			<?php esc_html_e( 'This page is set to no-index. This is believed to be a production domain.', 'wordpress-tools' ); ?>
		</div>
		<?php
	}
}

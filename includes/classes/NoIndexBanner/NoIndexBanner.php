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
	private bool $page_is_noindex = false;

	/**
	 * Built-in domains (and the .local TLD) treated as staging/dev.
	 * These are always excluded regardless of settings.
	 *
	 * @var string[]
	 */
	private const EXCLUDED_DOMAINS = [
		'829dev.com',
		'829stage.com',
		'pec-dev.com',
		'pec-stage.com',
		'wpengine.com',
		'wpenginepowered.com',
		'local', // matches any *.local domain
	];

	/**
	 * Setup hooks.
	 */
	public function setup() {
		$settings = Settings::get_settings();

		if ( ! $settings['noindex_banner_enabled'] || is_admin() || $this->is_excluded_domain( $settings ) ) {
			return;
		}

		// Capture the final noindex state after every plugin has had its say.
		add_filter( 'wp_robots', [ $this, 'capture_noindex_status' ], PHP_INT_MAX );
		add_action( 'wp_footer', [ $this, 'maybe_render_banner' ] );
	}

	/**
	 * Return true when the current domain is a known or user-defined staging/dev environment.
	 *
	 * @param  array $settings Plugin settings.
	 * @return bool
	 */
	private function is_excluded_domain( array $settings ): bool {
		$current_domain = strtolower( (string) wp_parse_url( site_url(), PHP_URL_HOST ) );

		$all_excluded = array_merge(
			self::EXCLUDED_DOMAINS,
			array_filter( array_map( 'trim', (array) ( $settings['noindex_banner_excluded_domains'] ?? [] ) ) )
		);

		foreach ( $all_excluded as $domain ) {
			$domain = strtolower( $domain );
			$suffix = '.' . $domain;
			if ( $current_domain === $domain || substr( $current_domain, -strlen( $suffix ) ) === $suffix ) {
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

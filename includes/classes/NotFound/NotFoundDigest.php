<?php
/**
 * Daily 404 report email
 *
 * @package  WordPressTools
 */

namespace WordPressTools\NotFound;

use WordPressTools\Singleton;
use WordPressTools\Settings\Settings;
use function WordPressTools\Utils\is_staging_or_dev;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 404 Digest class
 *
 * Emails each calendar day's 404s just after midnight to the recipients set in 829 Settings.
 * Network installs send one email covering every site.
 */
class NotFoundDigest {

	use Singleton;

	/**
	 * Cron hook.
	 *
	 * @var string
	 */
	const CRON_HOOK = 'wpt_404_digest';

	/**
	 * Max URLs listed per site.
	 *
	 * @var int
	 */
	const MAX_URLS = 50;

	/**
	 * Setup module
	 */
	public function setup() {
		add_action( 'init', [ $this, 'schedule' ] );
		add_action( self::CRON_HOOK, [ $this, 'send_scheduled' ] );
	}

	/**
	 * Whether the email covers every site in the network.
	 *
	 * @return bool
	 */
	private static function is_network_report() {
		return WPT_IS_NETWORK && is_multisite();
	}

	/**
	 * Schedule the next send for site-local midnight.
	 */
	public function schedule() {
		// The main site sends the network-wide email, so subsites don't each send a copy.
		if ( self::is_network_report() && ! is_main_site() ) {
			return;
		}

		if ( wp_next_scheduled( self::CRON_HOOK ) ) {
			return;
		}

		// A one-off event, rescheduled on the next request after it runs. A 'daily' recurrence would drift an hour at DST changes.
		$midnight = new \DateTimeImmutable( 'tomorrow', wp_timezone() );

		wp_schedule_single_event( $midnight->getTimestamp(), self::CRON_HOOK );
	}

	/**
	 * Configured recipients.
	 *
	 * @return string[]
	 */
	public static function get_recipients() {
		$settings = Settings::get_settings();

		return (array) $settings['digest_404_emails'];
	}

	/**
	 * Cron callback.
	 */
	public function send_scheduled() {
		// A staging copy of a production database would otherwise email the client about staging traffic.
		if ( is_staging_or_dev() ) {
			return;
		}

		$recipients = self::get_recipients();

		if ( empty( $recipients ) ) {
			return;
		}

		$sections = $this->get_sections();

		if ( empty( $sections ) ) {
			return;
		}

		$this->send( $recipients, $sections );
	}

	/**
	 * Yesterday's 404s, one section per site that had any.
	 *
	 * @return array[] Reports from NotFoundLog::get_report() plus site_name, site_url and date_label.
	 */
	public function get_sections() {
		if ( ! self::is_network_report() ) {
			$section = $this->get_site_section();

			return $section ? [ $section ] : [];
		}

		$sections = [];
		$site_ids = get_sites(
			[
				'number'   => 0,
				'fields'   => 'ids',
				'archived' => 0,
				'deleted'  => 0,
				'spam'     => 0,
			]
		);

		foreach ( $site_ids as $site_id ) {
			switch_to_blog( $site_id );
			$section = $this->get_site_section();
			restore_current_blog();

			if ( $section ) {
				$sections[] = $section;
			}
		}

		return $sections;
	}

	/**
	 * Yesterday's 404s for the current site, or null if there were none.
	 *
	 * @return array|null
	 */
	private function get_site_section() {
		$yesterday = new \DateTimeImmutable( 'yesterday', wp_timezone() );
		$date      = $yesterday->format( 'Y-m-d' );
		$report    = NotFoundLog::instance()->get_report( $date, $date, self::MAX_URLS );

		if ( empty( $report['urls'] ) ) {
			return null;
		}

		$report['site_name']  = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$report['site_url']   = home_url( '/' );
		$report['date_label'] = wp_date( get_option( 'date_format' ), $yesterday->getTimestamp() );

		return $report;
	}

	/**
	 * Email the report.
	 *
	 * @param string[] $recipients Email addresses.
	 * @param array[]  $sections   From get_sections().
	 * @return bool Whether wp_mail() accepted it.
	 */
	public function send( $recipients, $sections ) {
		$name = self::is_network_report()
			? wp_specialchars_decode( get_network()->site_name, ENT_QUOTES )
			: $sections[0]['site_name'];

		$subject = sprintf(
			/* translators: 1: site or network name, 2: date */
			__( '[%1$s] 404 report for %2$s', 'wordpress-tools' ),
			$name,
			$sections[0]['date_label']
		);

		return wp_mail(
			$recipients,
			$subject,
			$this->render( $sections ),
			[ 'Content-Type: text/html; charset=UTF-8' ]
		);
	}

	/**
	 * Render the email body.
	 *
	 * @param array[] $sections From get_sections().
	 * @return string
	 */
	private function render( $sections ) {
		$cell = 'padding: 6px 8px; border-bottom: 1px solid #f0f0f1; vertical-align: top;';

		ob_start();
		?>
		<div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; font-size: 14px; line-height: 1.5; color: #1d2327; max-width: 760px;">
			<?php foreach ( $sections as $section ) : ?>
				<?php if ( self::is_network_report() ) : ?>
					<h2 style="font-size: 18px; margin: 24px 0 4px;">
						<a href="<?php echo esc_url( $section['site_url'] ); ?>" style="color: #1d2327;"><?php echo esc_html( $section['site_name'] ); ?></a>
					</h2>
				<?php endif; ?>

				<p style="margin: 8px 0 12px;"><?php echo esc_html( $this->get_summary_text( $section ) ); ?></p>

				<table cellspacing="0" style="border-collapse: collapse; width: 100%;">
					<thead>
						<tr style="text-align: left; color: #50575e;">
							<th style="<?php echo esc_attr( $cell ); ?> border-bottom-color: #c3c4c7;"><?php esc_html_e( 'URL', 'wordpress-tools' ); ?></th>
							<th style="<?php echo esc_attr( $cell ); ?> border-bottom-color: #c3c4c7; text-align: right;"><?php esc_html_e( 'Visits', 'wordpress-tools' ); ?></th>
							<th style="<?php echo esc_attr( $cell ); ?> border-bottom-color: #c3c4c7;"><?php esc_html_e( 'Linked from', 'wordpress-tools' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $section['urls'] as $row ) : ?>
							<tr>
								<td style="<?php echo esc_attr( $cell ); ?> word-break: break-all;">
									<a href="<?php echo esc_url( $row['url'] ); ?>"><?php echo esc_html( self::get_display_path( $row['path'] ) ); ?></a>
									<?php if ( $row['first_seen'] === $section['from'] ) : ?>
										<span style="display: inline-block; padding: 0 6px; border-radius: 3px; background: #fcf0f1; color: #8a2424; font-size: 12px;"><?php esc_html_e( 'New', 'wordpress-tools' ); ?></span>
									<?php endif; ?>
								</td>
								<td style="<?php echo esc_attr( $cell ); ?> text-align: right;"><?php echo esc_html( number_format_i18n( $row['hits'] ) ); ?></td>
								<td style="<?php echo esc_attr( $cell ); ?> word-break: break-all;">
									<?php if ( 'none' === $row['referrer_type'] ) : ?>
										<span style="color: #8c8f94;">&mdash;</span>
									<?php else : ?>
										<a href="<?php echo esc_url( $row['referrer'] ); ?>"><?php echo esc_html( self::get_referrer_label( $row ) ); ?></a>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<?php $more = $section['total_urls'] - count( $section['urls'] ); ?>
				<?php if ( $more > 0 ) : ?>
					<p style="margin: 8px 0 0; color: #50575e;">
						<?php
						echo esc_html(
							sprintf(
								/* translators: %s: number of URLs not listed */
								_n( '…and %s more URL.', '…and %s more URLs.', $more, 'wordpress-tools' ),
								number_format_i18n( $more )
							)
						);
						?>
					</p>
				<?php endif; ?>
			<?php endforeach; ?>

			<p style="margin-top: 24px; color: #646970; font-size: 12px;">
				<?php esc_html_e( '"New" means the URL hadn\'t returned a 404 in the previous 30 days. "Linked from" is the last page a visitor came from, when their browser shared it.', 'wordpress-tools' ); ?><br>
				<?php esc_html_e( 'Sent daily by 829 Studios. To change who receives this report, contact 829 Studios.', 'wordpress-tools' ); ?>
			</p>
		</div>
		<?php

		return (string) ob_get_clean();
	}

	/**
	 * One-line summary for a site's section.
	 *
	 * @param array $section From get_sections().
	 * @return string
	 */
	private function get_summary_text( $section ) {
		$urls = sprintf(
			/* translators: %s: number of URLs */
			_n( '%s URL', '%s URLs', $section['total_urls'], 'wordpress-tools' ),
			number_format_i18n( $section['total_urls'] )
		);

		$visits = sprintf(
			/* translators: %s: number of visits */
			_n( '%s visit', '%s visits', $section['total_hits'], 'wordpress-tools' ),
			number_format_i18n( $section['total_hits'] )
		);

		return sprintf(
			/* translators: 1: number of URLs, 2: date, 3: number of visits */
			__( '%1$s returned a 404 (page not found) error on %2$s, across %3$s.', 'wordpress-tools' ),
			$urls,
			$section['date_label'],
			$visits
		);
	}

	/**
	 * Human-readable path, decoded unless that would produce invalid UTF-8.
	 *
	 * @param string $path Logged path.
	 * @return string
	 */
	private static function get_display_path( $path ) {
		$decoded = wp_check_invalid_utf8( rawurldecode( $path ) );

		return '' !== $decoded ? $decoded : $path;
	}

	/**
	 * Referrer label: the path for internal links, host and path for external ones.
	 *
	 * @param array $row Report row.
	 * @return string
	 */
	private static function get_referrer_label( $row ) {
		$host = (string) wp_parse_url( $row['referrer'], PHP_URL_HOST );
		$path = (string) wp_parse_url( $row['referrer'], PHP_URL_PATH );
		$path = self::get_display_path( '' !== $path ? $path : '/' );

		return 'internal' === $row['referrer_type'] ? $path : $host . $path;
	}
}

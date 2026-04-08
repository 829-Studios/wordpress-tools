<?php
/**
 * Propel MCP integration
 *
 * Registers Propel-specific abilities via the WordPress MCP Adapter.
 * Only loads when the Propel theme is active.
 *
 * @package  WordPressTools
 */

namespace WordPressTools\MCP;

use WordPressTools\Singleton;
use WordPressTools\Settings\Settings;
use WP_Error;
use WP_Query;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * PropelMCP class
 */
class PropelMCP {

	use Singleton;

	/**
	 * Setup module — bail early if Propel is not active.
	 */
	public function setup() {
		if ( ! function_exists( 'propel_get_theme_setting' ) ) {
			return;
		}

		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		add_action( 'wp_abilities_api_categories_init', [ $this, 'register_category' ] );
		add_action( 'wp_abilities_api_init', [ $this, 'register_abilities' ] );
	}

	/**
	 * Register the Propel ability category.
	 */
	public function register_category() {
		wp_register_ability_category(
			'propel',
			array(
				'label'       => 'Propel',
				'description' => 'Site management abilities for Propel-powered WordPress sites.',
			)
		);
	}

	/**
	 * Register all Propel MCP abilities.
	 */
	public function register_abilities() {
		// ── Settings ────────────────────────────────────────────────────────
		wp_register_ability(
			'propel/get-settings',
			array(
				'category'            => 'propel',
				'label'               => 'Get Propel Settings',
				'description'         => 'Returns the parsed Propel settings.json: registered post types, theme block locations, image sizes, fonts, and column constraints.',
				'input_schema'        => array( 'type' => 'object', 'properties' => array() ),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array( 'settings' => array( 'type' => 'object' ) ),
				),
				'permission_callback' => [ $this, 'check_admin_permission' ],
				'execute_callback'    => [ $this, 'get_settings' ],
				'meta'                => array(
					'mcp'         => array( 'public' => true ),
					'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ),
				),
			)
		);

		// ── Theme Blocks ────────────────────────────────────────────────────
		wp_register_ability(
			'propel/list-theme-blocks',
			array(
				'category'            => 'propel',
				'label'               => 'List Theme Blocks',
				'description'         => 'Returns all Theme Block posts with their assigned display locations (header, footer, alerts, sidebars, etc.).',
				'input_schema'        => array( 'type' => 'object', 'properties' => array() ),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array( 'theme_blocks' => array( 'type' => 'array' ) ),
				),
				'permission_callback' => [ $this, 'check_admin_permission' ],
				'execute_callback'    => [ $this, 'list_theme_blocks' ],
				'meta'                => array(
					'mcp'         => array( 'public' => true ),
					'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ),
				),
			)
		);

		// ── Events ──────────────────────────────────────────────────────────
		wp_register_ability(
			'propel/list-events',
			array(
				'category'            => 'propel',
				'label'               => 'List Events',
				'description'         => 'Queries the Propel events occurrence table for accurate recurring-event data. Supports date range, taxonomy, and pagination filters.',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'start_date'     => array( 'type' => 'string', 'description' => 'Filter occurrences from this date (YYYY-MM-DD). Defaults to today when upcoming_only is true.' ),
						'end_date'       => array( 'type' => 'string', 'description' => 'Filter occurrences up to and including this date (YYYY-MM-DD).' ),
						'upcoming_only'  => array( 'type' => 'boolean', 'description' => 'Only return future occurrences. Default true.', 'default' => true ),
						'event_category' => array( 'type' => 'integer', 'description' => 'Filter by event_category term ID.' ),
						'event_age'      => array( 'type' => 'integer', 'description' => 'Filter by event_age term ID.' ),
						'event_type'     => array( 'type' => 'integer', 'description' => 'Filter by event_type term ID.' ),
						'event_location' => array( 'type' => 'integer', 'description' => 'Filter by event_location term ID.' ),
						'per_page'       => array( 'type' => 'integer', 'description' => 'Results per page. Default 50, max 100.', 'default' => 50 ),
						'page'           => array( 'type' => 'integer', 'description' => 'Page number. Default 1.', 'default' => 1 ),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'events' => array( 'type' => 'array' ),
						'total'  => array( 'type' => 'integer' ),
						'pages'  => array( 'type' => 'integer' ),
					),
				),
				'permission_callback' => [ $this, 'check_admin_permission' ],
				'execute_callback'    => [ $this, 'list_events' ],
				'meta'                => array(
					'mcp'         => array( 'public' => true ),
					'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ),
				),
			)
		);

		wp_register_ability(
			'propel/get-event',
			array(
				'category'            => 'propel',
				'label'               => 'Get Event',
				'description'         => 'Returns a single event with all ACF fields: dates, recurrence rule, price, age range, and taxonomy terms.',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id' => array( 'type' => 'integer', 'description' => 'The event post ID.' ),
					),
					'required'   => array( 'post_id' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array( 'event' => array( 'type' => 'object' ) ),
				),
				'permission_callback' => [ $this, 'check_admin_permission' ],
				'execute_callback'    => [ $this, 'get_event' ],
				'meta'                => array(
					'mcp'         => array( 'public' => true ),
					'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ),
				),
			)
		);

		wp_register_ability(
			'propel/create-event',
			array(
				'category'            => 'propel',
				'label'               => 'Create Event',
				'description'         => 'Creates a new event post with ACF date/recurrence fields and triggers occurrence re-indexing.',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'title'           => array( 'type' => 'string', 'description' => 'Event title.' ),
						'content'         => array( 'type' => 'string', 'description' => 'Event content (HTML).' ),
						'status'          => array( 'type' => 'string', 'description' => 'Post status: draft or publish. Default draft.', 'default' => 'draft' ),
						'start_date'      => array( 'type' => 'string', 'description' => 'Start date (YYYY-MM-DD).' ),
						'start_time'      => array( 'type' => 'string', 'description' => 'Start time (HH:MM, 24-hour).' ),
						'end_date'        => array( 'type' => 'string', 'description' => 'End date (YYYY-MM-DD). Defaults to start_date.' ),
						'end_time'        => array( 'type' => 'string', 'description' => 'End time (HH:MM, 24-hour).' ),
						'all_day'         => array( 'type' => 'boolean', 'description' => 'Whether the event is all day.', 'default' => false ),
						'repeats'         => array( 'type' => 'boolean', 'description' => 'Whether the event recurs.', 'default' => false ),
						'frequency'       => array( 'type' => 'string', 'description' => 'Recurrence frequency: daily, weekly, monthly, yearly.' ),
						'interval'        => array( 'type' => 'integer', 'description' => 'Recurrence interval. Default 1.', 'default' => 1 ),
						'ends'            => array( 'type' => 'string', 'description' => 'When recurrence ends: never, on, after.' ),
						'repeat_end_date' => array( 'type' => 'string', 'description' => 'Recurrence end date (YYYY-MM-DD). Used when ends is "on".' ),
						'repeat_on_day'   => array( 'type' => 'array', 'description' => 'Days of week for weekly recurrence: monday, tuesday, etc.', 'items' => array( 'type' => 'string' ) ),
						'price'           => array( 'type' => 'string', 'description' => 'Event price.' ),
						'age_minimum'     => array( 'type' => 'number', 'description' => 'Minimum age.' ),
						'age_maximum'     => array( 'type' => 'number', 'description' => 'Maximum age.' ),
						'external_link'   => array( 'type' => 'string', 'description' => 'External registration/info URL.' ),
						'no_event_page'   => array( 'type' => 'boolean', 'description' => 'Disable individual event page.', 'default' => false ),
						'registration_closed' => array( 'type' => 'boolean', 'description' => 'Whether registration is closed.', 'default' => false ),
						'taxonomies'      => array( 'type' => 'object', 'description' => 'Taxonomy terms. Keys: event_category, event_age, event_type, event_location. Values: arrays of term IDs.' ),
					),
					'required' => array( 'title', 'start_date' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'  => array( 'type' => 'integer' ),
						'url'      => array( 'type' => 'string' ),
						'indexed'  => array( 'type' => 'boolean' ),
					),
				),
				'permission_callback' => [ $this, 'check_admin_permission' ],
				'execute_callback'    => [ $this, 'create_event' ],
				'meta'                => array(
					'mcp'         => array( 'public' => true ),
					'annotations' => array( 'destructive' => false, 'idempotent' => false ),
				),
			)
		);

		wp_register_ability(
			'propel/update-event',
			array(
				'category'            => 'propel',
				'label'               => 'Update Event',
				'description'         => 'Updates an existing event. Only provided fields are changed. Triggers occurrence re-indexing automatically.',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'         => array( 'type' => 'integer', 'description' => 'The event post ID.' ),
						'title'           => array( 'type' => 'string', 'description' => 'New event title.' ),
						'content'         => array( 'type' => 'string', 'description' => 'New event content (HTML).' ),
						'status'          => array( 'type' => 'string', 'description' => 'New post status.' ),
						'start_date'      => array( 'type' => 'string', 'description' => 'Start date (YYYY-MM-DD).' ),
						'start_time'      => array( 'type' => 'string', 'description' => 'Start time (HH:MM, 24-hour).' ),
						'end_date'        => array( 'type' => 'string', 'description' => 'End date (YYYY-MM-DD).' ),
						'end_time'        => array( 'type' => 'string', 'description' => 'End time (HH:MM, 24-hour).' ),
						'all_day'         => array( 'type' => 'boolean', 'description' => 'Whether the event is all day.' ),
						'repeats'         => array( 'type' => 'boolean', 'description' => 'Whether the event recurs.' ),
						'frequency'       => array( 'type' => 'string', 'description' => 'Recurrence frequency: daily, weekly, monthly, yearly.' ),
						'interval'        => array( 'type' => 'integer', 'description' => 'Recurrence interval.' ),
						'ends'            => array( 'type' => 'string', 'description' => 'When recurrence ends: never, on, after.' ),
						'repeat_end_date' => array( 'type' => 'string', 'description' => 'Recurrence end date (YYYY-MM-DD).' ),
						'repeat_on_day'   => array( 'type' => 'array', 'description' => 'Days of week for weekly recurrence.', 'items' => array( 'type' => 'string' ) ),
						'price'           => array( 'type' => 'string', 'description' => 'Event price.' ),
						'age_minimum'     => array( 'type' => 'number', 'description' => 'Minimum age.' ),
						'age_maximum'     => array( 'type' => 'number', 'description' => 'Maximum age.' ),
						'external_link'   => array( 'type' => 'string', 'description' => 'External registration/info URL.' ),
						'no_event_page'   => array( 'type' => 'boolean', 'description' => 'Disable individual event page.' ),
						'registration_closed' => array( 'type' => 'boolean', 'description' => 'Whether registration is closed.' ),
						'taxonomies'      => array( 'type' => 'object', 'description' => 'Taxonomy terms. Keys: event_category, event_age, event_type, event_location. Values: arrays of term IDs.' ),
					),
					'required' => array( 'post_id' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'post_id' => array( 'type' => 'integer' ),
						'url'     => array( 'type' => 'string' ),
						'indexed' => array( 'type' => 'boolean' ),
					),
				),
				'permission_callback' => [ $this, 'check_admin_permission' ],
				'execute_callback'    => [ $this, 'update_event' ],
				'meta'                => array(
					'mcp'         => array( 'public' => true ),
					'annotations' => array( 'destructive' => true, 'idempotent' => true ),
				),
			)
		);

		// ── Block Library ───────────────────────────────────────────────────
		wp_register_ability(
			'propel/list-block-library',
			array(
				'category'            => 'propel',
				'label'               => 'List Block Library',
				'description'         => 'Returns all Block Library posts (reusable block patterns) with their titles and URLs.',
				'input_schema'        => array( 'type' => 'object', 'properties' => array() ),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array( 'blocks' => array( 'type' => 'array' ) ),
				),
				'permission_callback' => [ $this, 'check_admin_permission' ],
				'execute_callback'    => [ $this, 'list_block_library' ],
				'meta'                => array(
					'mcp'         => array( 'public' => true ),
					'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ),
				),
			)
		);

		// ── Options ─────────────────────────────────────────────────────────
		wp_register_ability(
			'propel/get-options',
			array(
				'category'            => 'propel',
				'label'               => 'Get Propel Options',
				'description'         => 'Returns Propel ACF options page values: general settings and event settings.',
				'input_schema'        => array( 'type' => 'object', 'properties' => array() ),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array( 'options' => array( 'type' => 'object' ) ),
				),
				'permission_callback' => [ $this, 'check_admin_permission' ],
				'execute_callback'    => [ $this, 'get_options' ],
				'meta'                => array(
					'mcp'         => array( 'public' => true ),
					'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ),
				),
			)
		);

		wp_register_ability(
			'propel/update-options',
			array(
				'category'            => 'propel',
				'label'               => 'Update Propel Options',
				'description'         => 'Updates Propel ACF options: contact_page, allow_non_ajax_gravity_forms, registration_closed_message.',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'contact_page'                  => array( 'type' => 'integer', 'description' => 'Post ID of the contact page.' ),
						'allow_non_ajax_gravity_forms'  => array( 'type' => 'boolean', 'description' => 'Allow non-AJAX Gravity Forms.' ),
						'registration_closed_message'   => array( 'type' => 'string', 'description' => 'Default registration closed message (HTML).' ),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array( 'updated' => array( 'type' => 'boolean' ) ),
				),
				'permission_callback' => [ $this, 'check_admin_permission' ],
				'execute_callback'    => [ $this, 'update_options' ],
				'meta'                => array(
					'mcp'         => array( 'public' => true ),
					'annotations' => array( 'destructive' => true, 'idempotent' => true ),
				),
			)
		);

		// ── llms.txt ────────────────────────────────────────────────────────
		wp_register_ability(
			'propel/get-llms-txt',
			array(
				'category'            => 'propel',
				'label'               => 'Get llms.txt Settings',
				'description'         => 'Returns the Propel llms.txt configuration: visibility, site info, post type sections, custom sections, and custom content.',
				'input_schema'        => array( 'type' => 'object', 'properties' => array() ),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array( 'llms_txt' => array( 'type' => 'object' ) ),
				),
				'permission_callback' => [ $this, 'check_admin_permission' ],
				'execute_callback'    => [ $this, 'get_llms_txt' ],
				'meta'                => array(
					'mcp'         => array( 'public' => true ),
					'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ),
				),
			)
		);

		wp_register_ability(
			'propel/update-llms-txt',
			array(
				'category'            => 'propel',
				'label'               => 'Update llms.txt Settings',
				'description'         => 'Updates the Propel llms.txt configuration fields.',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'visible'                   => array( 'type' => 'boolean', 'description' => 'Whether llms.txt is publicly visible.' ),
						'site_name'                 => array( 'type' => 'string', 'description' => 'Override for site name.' ),
						'site_tagline'              => array( 'type' => 'string', 'description' => 'Override for site tagline.' ),
						'site_description'          => array( 'type' => 'string', 'description' => 'Site description.' ),
						'include_sitemap_from_yoast' => array( 'type' => 'boolean', 'description' => 'Include Yoast sitemap link.' ),
						'custom_content'            => array( 'type' => 'string', 'description' => 'Custom content to append.' ),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array( 'updated' => array( 'type' => 'boolean' ) ),
				),
				'permission_callback' => [ $this, 'check_admin_permission' ],
				'execute_callback'    => [ $this, 'update_llms_txt' ],
				'meta'                => array(
					'mcp'         => array( 'public' => true ),
					'annotations' => array( 'destructive' => true, 'idempotent' => true ),
				),
			)
		);
	}

	// ── Permission callbacks ─────────────────────────────────────────────────

	/**
	 * Require manage_options.
	 *
	 * @return true|WP_Error
	 */
	public function check_admin_permission() {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'not_authenticated', 'Authentication required.' );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'insufficient_permission', 'Administrator access required.' );
		}

		return true;
	}

	// ── Execute callbacks ────────────────────────────────────────────────────

	/**
	 * Get Propel settings.json configuration.
	 *
	 * @return array
	 */
	public function get_settings() {
		$post_types = propel_get_theme_setting( 'post_types' ) ?: array();
		$locations  = propel_get_theme_setting( 'theme_block_locations' ) ?: array();
		$thumbnails = propel_get_theme_setting( 'thumbnails' ) ?: array();
		$fonts      = propel_get_theme_setting( 'fonts' ) ?: array();

		// Simplify post types to a readable summary.
		$post_type_summary = array();
		foreach ( $post_types as $slug => $config ) {
			$post_type_summary[ $slug ] = array(
				'singular'   => $config['singular'] ?? $slug,
				'plural'     => $config['plural'] ?? $slug,
				'taxonomies' => array_keys( $config['taxonomies'] ?? array() ),
			);
		}

		return array(
			'settings' => array(
				'post_types'            => $post_type_summary,
				'theme_block_locations' => $locations,
				'image_sizes'           => $thumbnails,
				'fonts'                 => $fonts,
				'columns_max_width'     => propel_get_theme_setting( 'columns_max_width' ),
				'columns_desktop_gap'   => propel_get_theme_setting( 'columns_desktop_gap' ),
			),
		);
	}

	/**
	 * List theme block posts with their display locations.
	 *
	 * @return array
	 */
	public function list_theme_blocks() {
		$locations      = propel_get_theme_setting( 'theme_block_locations' ) ?: array();
		$location_label = array_flip( $locations );

		$query = new WP_Query(
			array(
				'post_type'      => 'theme_block',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		$blocks = array();

		foreach ( $query->posts as $post ) {
			$raw_locations    = get_post_meta( $post->ID, 'display_location', true );
			$assigned         = is_array( $raw_locations ) ? $raw_locations : array();
			$labeled_locations = array();

			foreach ( $assigned as $loc_slug ) {
				$labeled_locations[] = array(
					'slug'  => $loc_slug,
					'label' => $location_label[ $loc_slug ] ?? $loc_slug,
				);
			}

			$blocks[] = array(
				'id'        => $post->ID,
				'title'     => $post->post_title,
				'url'       => get_edit_post_link( $post->ID, 'raw' ),
				'locations' => $labeled_locations,
			);
		}

		return array( 'theme_blocks' => $blocks );
	}

	/**
	 * List event occurrences from the Propel events table.
	 *
	 * @param  array $input Ability input.
	 * @return array|WP_Error
	 */
	public function list_events( $input = array() ) {
		global $wpdb;

		$input = $this->normalize_input( $input );
		$table = $wpdb->prefix . 'propel_events';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			return new WP_Error( 'no_events_table', 'Propel events table not found. Events may not have been indexed yet.' );
		}

		$where  = array( 'e.event_post_status = %s' );
		$params = array( 'publish' );

		$upcoming_only = ! isset( $input['upcoming_only'] ) || ! empty( $input['upcoming_only'] );

		if ( ! empty( $input['start_date'] ) ) {
			$where[]  = 'e.start >= %s';
			$params[] = $input['start_date'] . ' 00:00:00';
		} elseif ( $upcoming_only ) {
			$where[]  = 'e.start >= %s';
			$params[] = current_time( 'mysql' );
		}

		if ( ! empty( $input['end_date'] ) ) {
			$where[]  = 'e.start <= %s';
			$params[] = $input['end_date'] . ' 23:59:59';
		}

		foreach ( array( 'event_category', 'event_age', 'event_type', 'event_location' ) as $col ) {
			if ( ! empty( $input[ $col ] ) ) {
				$where[]  = "FIND_IN_SET(%d, e.{$col})";
				$params[] = intval( $input[ $col ] );
			}
		}

		$where_sql = implode( ' AND ', $where );
		$per_page  = min( intval( $input['per_page'] ?? 50 ), 100 );
		$page      = max( intval( $input['page'] ?? 1 ), 1 );
		$offset    = ( $page - 1 ) * $per_page;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table} e WHERE {$where_sql}", $params )
		);

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT e.*, p.post_title FROM {$table} e
				INNER JOIN {$wpdb->posts} p ON e.event_post_id = p.ID
				WHERE {$where_sql}
				ORDER BY e.start ASC
				LIMIT %d OFFSET %d",
				array_merge( $params, array( $per_page, $offset ) )
			)
		);
		// phpcs:enable

		$events = array();
		foreach ( $rows as $row ) {
			$events[] = array(
				'occurrence_id'  => (int) $row->occurrence_id,
				'post_id'        => (int) $row->event_post_id,
				'title'          => $row->post_title,
				'url'            => get_permalink( (int) $row->event_post_id ),
				'start'          => $row->start,
				'end'            => $row->end,
				'duration'       => (int) $row->duration,
				'event_category' => $row->event_category,
				'event_age'      => $row->event_age,
				'event_type'     => $row->event_type,
				'event_location' => $row->event_location,
				'age_minimum'    => $row->age_minimum,
				'age_maximum'    => $row->age_maximum,
			);
		}

		return array(
			'events' => $events,
			'total'  => $total,
			'pages'  => $per_page > 0 ? (int) ceil( $total / $per_page ) : 1,
		);
	}

	/**
	 * Get a single event with full ACF field data.
	 *
	 * @param  array $input Ability input.
	 * @return array|WP_Error
	 */
	public function get_event( $input = array() ) {
		$input   = $this->normalize_input( $input );
		$post_id = intval( $input['post_id'] ?? 0 );
		$post    = get_post( $post_id );

		if ( ! $post || 'event' !== $post->post_type ) {
			return new WP_Error( 'not_found', 'Event not found.' );
		}

		return array( 'event' => $this->get_event_data( $post ) );
	}

	/**
	 * Create a new event post.
	 *
	 * @param  array $input Ability input.
	 * @return array|WP_Error
	 */
	public function create_event( $input = array() ) {
		$input = $this->normalize_input( $input );

		if ( empty( $input['title'] ) ) {
			return new WP_Error( 'missing_title', 'Event title is required.' );
		}

		if ( empty( $input['start_date'] ) ) {
			return new WP_Error( 'missing_start_date', 'start_date is required.' );
		}

		$post_id = wp_insert_post(
			array(
				'post_type'    => 'event',
				'post_title'   => $input['title'],
				'post_content' => $input['content'] ?? '',
				'post_status'  => $input['status'] ?? 'draft',
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$this->save_event_acf_fields( $post_id, $input );
		$indexed = $this->reindex_event( $post_id );

		return array(
			'post_id' => $post_id,
			'url'     => get_permalink( $post_id ),
			'indexed' => $indexed,
		);
	}

	/**
	 * Update an existing event post.
	 *
	 * @param  array $input Ability input.
	 * @return array|WP_Error
	 */
	public function update_event( $input = array() ) {
		$input   = $this->normalize_input( $input );
		$post_id = intval( $input['post_id'] ?? 0 );
		$post    = get_post( $post_id );

		if ( ! $post || 'event' !== $post->post_type ) {
			return new WP_Error( 'not_found', 'Event not found.' );
		}

		$post_data = array( 'ID' => $post_id );

		if ( isset( $input['title'] ) ) {
			$post_data['post_title'] = $input['title'];
		}

		if ( isset( $input['content'] ) ) {
			$post_data['post_content'] = $input['content'];
		}

		if ( isset( $input['status'] ) ) {
			$post_data['post_status'] = $input['status'];
		}

		if ( count( $post_data ) > 1 ) {
			$result = wp_update_post( $post_data, true );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		$this->save_event_acf_fields( $post_id, $input, true );
		$indexed = $this->reindex_event( $post_id );

		return array(
			'post_id' => $post_id,
			'url'     => get_permalink( $post_id ),
			'indexed' => $indexed,
		);
	}

	/**
	 * List block library posts.
	 *
	 * @return array
	 */
	public function list_block_library() {
		$query = new WP_Query(
			array(
				'post_type'      => 'library_block',
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => -1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		$blocks = array();

		foreach ( $query->posts as $post ) {
			$blocks[] = array(
				'id'     => $post->ID,
				'title'  => $post->post_title,
				'slug'   => $post->post_name,
				'status' => $post->post_status,
				'url'    => get_permalink( $post->ID ),
			);
		}

		return array( 'blocks' => $blocks );
	}

	/**
	 * Get Propel ACF options page values.
	 *
	 * @return array
	 */
	public function get_options() {
		if ( ! function_exists( 'get_field' ) ) {
			return new WP_Error( 'acf_missing', 'ACF is not active.' );
		}

		$contact_page_id = get_field( 'contact_page', 'option' );

		return array(
			'options' => array(
				'general' => array(
					'contact_page'                 => $contact_page_id ? array(
						'id'    => $contact_page_id,
						'title' => get_the_title( $contact_page_id ),
						'url'   => get_permalink( $contact_page_id ),
					) : null,
					'allow_non_ajax_gravity_forms' => (bool) get_field( 'allow_non_ajax_gravity_forms', 'option' ),
				),
				'events'  => array(
					'registration_closed_message' => get_field( 'registration_closed_message', 'option' ),
				),
			),
		);
	}

	/**
	 * Update Propel ACF options page values.
	 *
	 * @param  array $input Ability input.
	 * @return array|WP_Error
	 */
	public function update_options( $input = array() ) {
		$input = $this->normalize_input( $input );

		if ( ! function_exists( 'update_field' ) ) {
			return new WP_Error( 'acf_missing', 'ACF is not active.' );
		}

		if ( isset( $input['contact_page'] ) ) {
			update_field( 'contact_page', intval( $input['contact_page'] ), 'option' );
		}

		if ( isset( $input['allow_non_ajax_gravity_forms'] ) ) {
			update_field( 'allow_non_ajax_gravity_forms', (bool) $input['allow_non_ajax_gravity_forms'], 'option' );
		}

		if ( isset( $input['registration_closed_message'] ) ) {
			update_field( 'registration_closed_message', wp_kses_post( $input['registration_closed_message'] ), 'option' );
		}

		return array( 'updated' => true );
	}

	/**
	 * Get Propel llms.txt settings.
	 *
	 * @return array|WP_Error
	 */
	public function get_llms_txt() {
		if ( ! function_exists( 'get_field' ) ) {
			return new WP_Error( 'acf_missing', 'ACF is not active.' );
		}

		return array(
			'llms_txt' => array(
				'visible'                    => (bool) get_field( 'llmstxt_visibilty', 'option' ),
				'site_name'                  => get_field( 'site_name', 'option' ) ?: get_bloginfo( 'name' ),
				'site_tagline'               => get_field( 'site_tagline', 'option' ) ?: get_bloginfo( 'description' ),
				'site_description'           => get_field( 'site_description', 'option' ),
				'include_sitemap_from_yoast' => (bool) get_field( 'include_sitemap_from_yoast', 'option' ),
				'post_type_sections'         => get_field( 'post_type_sections', 'option' ) ?: array(),
				'custom_sections'            => get_field( 'custom_sections', 'option' ) ?: array(),
				'custom_content'             => get_field( 'custom_content', 'option' ),
			),
		);
	}

	/**
	 * Update Propel llms.txt settings.
	 *
	 * @param  array $input Ability input.
	 * @return array|WP_Error
	 */
	public function update_llms_txt( $input = array() ) {
		$input = $this->normalize_input( $input );

		if ( ! function_exists( 'update_field' ) ) {
			return new WP_Error( 'acf_missing', 'ACF is not active.' );
		}

		// Check for a static llms.txt file — if one exists Propel won't use the options.
		if ( file_exists( ABSPATH . 'llms.txt' ) ) {
			return new WP_Error(
				'static_file_exists',
				'A static llms.txt file exists at the site root. Remove it for Propel\'s options to take effect.'
			);
		}

		$field_map = array(
			'visible'                    => 'llmstxt_visibilty',
			'site_name'                  => 'site_name',
			'site_tagline'               => 'site_tagline',
			'site_description'           => 'site_description',
			'include_sitemap_from_yoast' => 'include_sitemap_from_yoast',
			'custom_content'             => 'custom_content',
		);

		foreach ( $field_map as $input_key => $acf_key ) {
			if ( ! isset( $input[ $input_key ] ) ) {
				continue;
			}

			$value = $input[ $input_key ];

			if ( in_array( $input_key, array( 'visible', 'include_sitemap_from_yoast' ), true ) ) {
				$value = (bool) $value;
			} else {
				$value = sanitize_textarea_field( $value );
			}

			update_field( $acf_key, $value, 'option' );
		}

		return array( 'updated' => true );
	}

	// ── Private helpers ──────────────────────────────────────────────────────

	/**
	 * Normalize input that may arrive as a JSON string.
	 *
	 * @param  mixed $input Raw input.
	 * @return array
	 */
	private function normalize_input( $input ) {
		if ( is_string( $input ) ) {
			$decoded = json_decode( $input, true );
			if ( is_array( $decoded ) ) {
				return $decoded;
			}
		}

		return is_array( $input ) ? $input : array();
	}

	/**
	 * Build a structured event data array from a post.
	 *
	 * @param  \WP_Post $post The event post.
	 * @return array
	 */
	private function get_event_data( $post ) {
		$post_id = $post->ID;

		$date      = function_exists( 'get_field' ) ? ( get_field( 'date', $post_id ) ?: array() ) : array();
		$repeating = $date['repeating'] ?? array();

		$taxonomies = array();
		foreach ( array( 'event_category', 'event_age', 'event_type', 'event_location' ) as $tax ) {
			$terms = get_the_terms( $post_id, $tax );
			if ( $terms && ! is_wp_error( $terms ) ) {
				$taxonomies[ $tax ] = array_map(
					function ( $t ) {
						return array( 'id' => $t->term_id, 'name' => $t->name, 'slug' => $t->slug );
					},
					$terms
				);
			} else {
				$taxonomies[ $tax ] = array();
			}
		}

		return array(
			'id'      => $post_id,
			'title'   => $post->post_title,
			'content' => $post->post_content,
			'status'  => $post->post_status,
			'url'     => get_permalink( $post_id ),
			'date'    => array(
				'start_date' => $date['start_date'] ?? '',
				'start_time' => $date['start_time'] ?? '',
				'end_date'   => $date['end_date'] ?? '',
				'end_time'   => $date['end_time'] ?? '',
				'all_day'    => ! empty( $date['all_day'] ),
			),
			'recurrence' => array(
				'repeats'         => ! empty( $repeating['repeats'] ),
				'frequency'       => $repeating['frequency'] ?? '',
				'interval'        => intval( $repeating['interval'] ?? 1 ),
				'ends'            => $repeating['ends'] ?? '',
				'repeat_end_date' => $repeating['repeat_end_date'] ?? '',
				'repeat_on_day'   => $repeating['repeat_on_day'] ?? array(),
				'excluded_dates'  => $repeating['excluded_dates'] ?? array(),
			),
			'price'               => function_exists( 'get_field' ) ? get_field( 'price', $post_id ) : '',
			'age_minimum'         => function_exists( 'get_field' ) ? get_field( 'age_minimum', $post_id ) : null,
			'age_maximum'         => function_exists( 'get_field' ) ? get_field( 'age_maximum', $post_id ) : null,
			'external_link'       => function_exists( 'get_field' ) ? get_field( 'external_link', $post_id ) : '',
			'no_event_page'       => function_exists( 'get_field' ) ? (bool) get_field( 'no_event_page', $post_id ) : false,
			'registration_closed' => function_exists( 'get_field' ) ? (bool) get_field( 'registration_closed', $post_id ) : false,
			'taxonomies'          => $taxonomies,
		);
	}

	/**
	 * Save event ACF fields from input. Skips fields not present in input on partial updates.
	 *
	 * @param int   $post_id  Event post ID.
	 * @param array $input    Ability input.
	 * @param bool  $partial  When true, only update fields that are explicitly set.
	 */
	private function save_event_acf_fields( $post_id, $input, $partial = false ) {
		if ( ! function_exists( 'update_field' ) ) {
			return;
		}

		$date_fields = array( 'start_date', 'start_time', 'end_date', 'end_time', 'all_day' );
		$repeating_fields = array( 'repeats', 'frequency', 'interval', 'ends', 'repeat_end_date', 'repeat_on_day' );

		$has_date_input      = ! $partial || array_intersect( array_keys( $input ), array_merge( $date_fields, $repeating_fields ) );
		$has_repeating_input = ! $partial || array_intersect( array_keys( $input ), $repeating_fields );

		if ( $has_date_input ) {
			$existing  = get_field( 'date', $post_id ) ?: array();
			$repeating = $existing['repeating'] ?? array();

			if ( $has_repeating_input ) {
				$repeating = array(
					'repeats'         => isset( $input['repeats'] ) ? (bool) $input['repeats'] : ( $repeating['repeats'] ?? false ),
					'frequency'       => $input['frequency'] ?? ( $repeating['frequency'] ?? 'weekly' ),
					'interval'        => intval( $input['interval'] ?? ( $repeating['interval'] ?? 1 ) ),
					'ends'            => $input['ends'] ?? ( $repeating['ends'] ?? 'never' ),
					'repeat_end_date' => $input['repeat_end_date'] ?? ( $repeating['repeat_end_date'] ?? '' ),
					'repeat_on_day'   => $input['repeat_on_day'] ?? ( $repeating['repeat_on_day'] ?? array() ),
				);
			}

			update_field(
				'date',
				array(
					'start_date' => $input['start_date'] ?? ( $existing['start_date'] ?? '' ),
					'start_time' => $input['start_time'] ?? ( $existing['start_time'] ?? '' ),
					'end_date'   => $input['end_date'] ?? ( $existing['end_date'] ?? ( $input['start_date'] ?? '' ) ),
					'end_time'   => $input['end_time'] ?? ( $existing['end_time'] ?? '' ),
					'all_day'    => isset( $input['all_day'] ) ? (bool) $input['all_day'] : ( $existing['all_day'] ?? false ),
					'repeating'  => $repeating,
				),
				$post_id
			);
		}

		if ( ! $partial || isset( $input['price'] ) ) {
			update_field( 'price', $input['price'] ?? '', $post_id );
		}

		if ( ! $partial || isset( $input['age_minimum'] ) ) {
			update_field( 'age_minimum', $input['age_minimum'] ?? null, $post_id );
		}

		if ( ! $partial || isset( $input['age_maximum'] ) ) {
			update_field( 'age_maximum', $input['age_maximum'] ?? null, $post_id );
		}

		if ( ! $partial || isset( $input['external_link'] ) ) {
			update_field( 'external_link', $input['external_link'] ?? '', $post_id );
		}

		if ( ! $partial || isset( $input['no_event_page'] ) ) {
			update_field( 'no_event_page', (bool) ( $input['no_event_page'] ?? false ), $post_id );
		}

		if ( ! $partial || isset( $input['registration_closed'] ) ) {
			update_field( 'registration_closed', (bool) ( $input['registration_closed'] ?? false ), $post_id );
		}

		if ( ! empty( $input['taxonomies'] ) && is_array( $input['taxonomies'] ) ) {
			$allowed_taxonomies = array( 'event_category', 'event_age', 'event_type', 'event_location' );
			foreach ( $input['taxonomies'] as $taxonomy => $term_ids ) {
				if ( in_array( $taxonomy, $allowed_taxonomies, true ) && taxonomy_exists( $taxonomy ) ) {
					wp_set_object_terms( $post_id, array_map( 'intval', (array) $term_ids ), $taxonomy );
				}
			}
		}
	}

	/**
	 * Re-index a single event's occurrences via Propel's indexer.
	 *
	 * @param  int $post_id Event post ID.
	 * @return bool True on success.
	 */
	private function reindex_event( $post_id ) {
		if ( function_exists( 'Propel\\Events\\SaveHooks\\save_rrule_and_occurrences' ) ) {
			$result = \Propel\Events\SaveHooks\save_rrule_and_occurrences( $post_id );
			return true === $result;
		}

		// Fallback: trigger via internal REST request.
		$request  = new \WP_REST_Request( 'GET', "/propel/v1/events/index/{$post_id}" );
		$response = rest_do_request( $request );

		return 200 === $response->get_status();
	}
}

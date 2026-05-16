<?php
/**
 * Content & Appearance check category — 5 checks per Brief §3.3.
 *
 * @package PreFlight
 * @since   0.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Check category: Content & Appearance.
 *
 * Detects missing favicon, leftover sample content, empty sites, absent 404
 * templates, and blank site titles. All detection uses internal WordPress APIs
 * and filesystem checks — no loopback HTTP requests (Brief §5.2).
 *
 * @since 0.3.0
 */
class PreFlight_Checks_Content implements PreFlight_Check_Category {

	// -------------------------------------------------------------------------
	// PreFlight_Check_Category interface
	// -------------------------------------------------------------------------

	public function get_category_id(): string {
		return 'content';
	}

	public function get_category_label(): string {
		return __( 'Content & Appearance', 'preflight-wp' );
	}

	/**
	 * Return the ordered list of check IDs in this category.
	 *
	 * Order matches Brief §3.3: severity descending (blockers first, then
	 * warnings), then alphabetical within each severity tier.
	 *
	 * @return string[]
	 */
	public function get_check_ids(): array {
		return array_column( self::get_spec(), 0 );
	}

	/**
	 * Return a check object for the given ID, or null if the ID is unknown.
	 *
	 * @param string $id Namespaced check ID.
	 * @return PreFlight_Check|null
	 */
	public function get_check( string $id ): ?PreFlight_Check {
		foreach ( self::get_spec() as $entry ) {
			if ( $entry[0] !== $id ) {
				continue;
			}
			return new PreFlight_Configurable_Check(
				$entry[0],
				$entry[1],
				$entry[2],
				array( $this, $entry[3] )
			);
		}
		return null;
	}

	// -------------------------------------------------------------------------
	// Check specification table
	// -------------------------------------------------------------------------

	/**
	 * Return the static check specification table.
	 *
	 * Each entry: [ id, label, severity_constant, method_name ].
	 * Ordered blockers → warnings (matches get_check_ids() contract).
	 *
	 * @return array[]
	 */
	private static function get_spec(): array {
		return array(
			array( 'content.favicon-missing',        __( 'Favicon not set',                   'preflight-wp' ), PREFLIGHT_SEVERITY_BLOCKER, 'check_favicon_missing' ),
			array( 'content.site-title-missing',     __( 'Site title is blank',               'preflight-wp' ), PREFLIGHT_SEVERITY_BLOCKER, 'check_site_title_missing' ),
			array( 'content.no-404-template',        __( 'No 404 template found',             'preflight-wp' ), PREFLIGHT_SEVERITY_WARNING, 'check_no_404_template' ),
			array( 'content.no-published-content',   __( 'Site has no published content',     'preflight-wp' ), PREFLIGHT_SEVERITY_WARNING, 'check_no_published_content' ),
			array( 'content.sample-content-present', __( 'Default sample content still live', 'preflight-wp' ), PREFLIGHT_SEVERITY_WARNING, 'check_sample_content_present' ),
		);
	}

	// -------------------------------------------------------------------------
	// Detection methods
	// -------------------------------------------------------------------------

	/**
	 * Fail when no site icon has been set via the Customizer / Site Identity.
	 *
	 * get_site_icon_url() returns an empty string when no icon is configured
	 * (WordPress 4.3+, safe at the WP 6.0 baseline).
	 *
	 * @since 0.3.0
	 * @return PreFlight_Check_Result
	 */
	public function check_favicon_missing(): PreFlight_Check_Result {
		if ( '' === get_site_icon_url() ) {
			return PreFlight_Check_Result::fail(
				__( 'No site icon has been configured.', 'preflight-wp' ),
				__( 'Go to Appearance → Customize → Site Identity and upload a Site Icon (at least 512 × 512 px).', 'preflight-wp' )
			);
		}
		return PreFlight_Check_Result::pass();
	}

	/**
	 * Fail when the site title (blogname option) is empty or whitespace-only.
	 *
	 * @since 0.3.0
	 * @return PreFlight_Check_Result
	 */
	public function check_site_title_missing(): PreFlight_Check_Result {
		if ( '' === trim( get_bloginfo( 'name' ) ) ) {
			return PreFlight_Check_Result::fail(
				__( 'The site title is blank.', 'preflight-wp' ),
				__( 'Go to Settings → General and enter a Site Title.', 'preflight-wp' )
			);
		}
		return PreFlight_Check_Result::pass();
	}

	/**
	 * Fail when no 404 template exists in the active theme.
	 *
	 * Block themes store templates as HTML files under templates/; classic
	 * themes use 404.php. Both the active child theme and parent theme are
	 * checked via the standard WordPress template hierarchy functions.
	 *
	 * wp_is_block_theme() is available since WP 5.9; safe at WP 6.0 baseline.
	 *
	 * @since 0.3.0
	 * @return PreFlight_Check_Result
	 */
	public function check_no_404_template(): PreFlight_Check_Result {
		if ( wp_is_block_theme() ) {
			$found = file_exists( get_stylesheet_directory() . '/templates/404.html' )
				|| file_exists( get_template_directory() . '/templates/404.html' );
		} else {
			$found = (bool) locate_template( '404.php' );
		}

		if ( ! $found ) {
			return PreFlight_Check_Result::fail(
				__( 'The active theme does not include a 404 template.', 'preflight-wp' ),
				__( 'Add a 404.php (classic themes) or templates/404.html (block themes) to your theme, or install a theme that includes one.', 'preflight-wp' )
			);
		}
		return PreFlight_Check_Result::pass();
	}

	/**
	 * Fail when the site has zero published posts and zero published pages.
	 *
	 * Uses wp_count_posts() which reads from a cached object; does not issue
	 * an extra SQL query on sites with object caching enabled.
	 *
	 * @since 0.3.0
	 * @return PreFlight_Check_Result
	 */
	public function check_no_published_content(): PreFlight_Check_Result {
		$post_count = (int) wp_count_posts( 'post' )->publish;
		$page_count = (int) wp_count_posts( 'page' )->publish;

		if ( 0 === $post_count && 0 === $page_count ) {
			return PreFlight_Check_Result::fail(
				__( 'The site has no published posts or pages.', 'preflight-wp' ),
				__( 'Publish at least one page (e.g. a homepage) before launch.', 'preflight-wp' )
			);
		}
		return PreFlight_Check_Result::pass();
	}

	/**
	 * Fail when WordPress default sample content is still published.
	 *
	 * Searches for the exact default titles WordPress creates on install:
	 *   - Post:  "Hello world!"
	 *   - Page:  "Sample Page"
	 *
	 * WP_Query 'title' parameter is supported since WP 4.4; safe at WP 6.0.
	 * Two separate get_posts() calls keep the logic readable and avoid a
	 * cross-post-type query that would require a UNION or post__in workaround.
	 *
	 * @since 0.3.0
	 * @return PreFlight_Check_Result
	 */
	public function check_sample_content_present(): PreFlight_Check_Result {
		$found_items = array();

		$hello_posts = get_posts(
			array(
				'post_type'              => 'post',
				'post_status'            => 'publish',
				'title'                  => 'Hello world!',
				'numberposts'            => 1,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);
		if ( ! empty( $hello_posts ) ) {
			$found_items[] = __( '"Hello world!" post', 'preflight-wp' );
		}

		$sample_pages = get_posts(
			array(
				'post_type'              => 'page',
				'post_status'            => 'publish',
				'title'                  => 'Sample Page',
				'numberposts'            => 1,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);
		if ( ! empty( $sample_pages ) ) {
			$found_items[] = __( '"Sample Page" page', 'preflight-wp' );
		}

		if ( ! empty( $found_items ) ) {
			return PreFlight_Check_Result::fail(
				sprintf(
					/* translators: %s: comma-separated list of found sample content items */
					__( 'Default WordPress sample content is still published: %s.', 'preflight-wp' ),
					implode( _x( ', ', 'list separator', 'preflight-wp' ), $found_items )
				),
				__( 'Delete or unpublish the sample post and/or sample page before launch.', 'preflight-wp' )
			);
		}
		return PreFlight_Check_Result::pass();
	}
}

add_action(
	'preflight_register_categories',
	static function ( PreFlight_Core $core ): void {
		$core->register_category( new PreFlight_Checks_Content() );
	}
);

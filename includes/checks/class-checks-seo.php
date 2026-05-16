<?php
/**
 * SEO Readiness check category — 3 checks per Brief §3.2.
 *
 * @package PreFlight
 * @since   0.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Check category: SEO Readiness.
 *
 * Detects absent sitemap generator, unrecognised SEO plugin, and missing Open
 * Graph tag support. "Search engine visibility disabled" (Brief §3.2 first row)
 * is cross-listed: it lives in the wp-config category as
 * wp-config.search-engine-discouraged and is not duplicated here.
 *
 * All detection uses class_exists(), defined(), and apply_filters() — no
 * loopback HTTP requests (Brief §5.2).
 *
 * Signal verification: every class name and constant in the two detection
 * constants below was verified against current published plugin source at the
 * time of writing (2026-05-16). Two SEO signals and all three original OG
 * signals from the spec required correction — see SEO_PLUGIN_SIGNALS and
 * OG_DEDICATED_PLUGIN_SIGNALS docblocks for details.
 *
 * @since 0.5.0
 */
class PreFlight_Checks_SEO implements PreFlight_Check_Category {

	/**
	 * Plugin label → detection signal map for recognised SEO plugins.
	 *
	 * Each signal is evaluated with:  class_exists($signal) || defined($signal)
	 *
	 * Signal corrections vs. original spec (verified 2026-05-16):
	 *   - The SEO Framework: spec had 'The_SEO_Framework\THE_SEO_FRAMEWORK_VERSION'
	 *     (a namespaced constant that does not exist). Actual signal is the
	 *     global constant THE_SEO_FRAMEWORK_VERSION defined in autodescription.php.
	 *     Source: github.com/sybrew/the-seo-framework autodescription.php
	 *   - Slim SEO: spec had 'SlimSEO\Plugin' (class does not exist; namespace is
	 *     SlimSEO but classes are Activator/Deactivator/Container). Actual signal
	 *     is the SLIM_SEO_VER constant.
	 *     Source: github.com/elightup/slim-seo slim-seo.php
	 *
	 * Extension point (v1.1): a filter hook 'preflight_seo_plugin_signals' will
	 * allow site-specific additions. Do not implement the filter now.
	 *
	 * @var array<string, string>
	 */
	const SEO_PLUGIN_SIGNALS = array(
		'Yoast SEO'         => 'WPSEO_VERSION',
		'Rank Math'         => 'RANK_MATH_VERSION',
		'All in One SEO'    => 'AIOSEO_VERSION',
		'SEOPress'          => 'SEOPRESS_VERSION',
		'The SEO Framework' => 'THE_SEO_FRAMEWORK_VERSION',
		'Slim SEO'          => 'SLIM_SEO_VER',
		'Squirrly SEO'      => 'SQ_VERSION',
	);

	/**
	 * Plugin label → detection signal map for dedicated Open Graph plugins.
	 *
	 * Used only when no SEO plugin (which natively includes OG support) is found.
	 * Each signal is evaluated with:  class_exists($signal) || defined($signal)
	 *
	 * Signal corrections vs. original spec (verified 2026-05-16):
	 *   - WP Open Graph (slug: wp-open-graph): spec had 'WPOpenGraph' class,
	 *     which does not exist. Actual classes are NY_OG_Output / NY_OG_Main_Admin.
	 *     Using NY_OG_Output (instantiated on every page load).
	 *     Source: plugins.svn.wordpress.org/wp-open-graph/trunk/wp-open-graph.php
	 *   - Webdados FB Open Graph (wonderm00ns-simple-facebook-open-graph-tags):
	 *     spec had 'fb_og_meta' function (does not exist). Actual constant is
	 *     WEBDADOS_FB_VERSION.
	 *     Source: plugins.svn.wordpress.org/wonderm00ns-simple-facebook-open-graph-tags/trunk/
	 *   - 'OGFB' (Open Graph for Facebook): could not be verified against any
	 *     current published plugin. Dropped from signal list.
	 *
	 * Extension point (v1.1): a filter hook 'preflight_og_plugin_signals' will
	 * allow additions. Do not implement the filter now.
	 *
	 * @var array<string, string>
	 */
	const OG_DEDICATED_PLUGIN_SIGNALS = array(
		'WP Open Graph'          => 'NY_OG_Output',
		'Webdados FB Open Graph' => 'WEBDADOS_FB_VERSION',
	);

	// -------------------------------------------------------------------------
	// PreFlight_Check_Category interface
	// -------------------------------------------------------------------------

	/**
	 * @return string
	 */
	public function get_category_id(): string {
		return 'seo';
	}

	/**
	 * @return string
	 */
	public function get_category_label(): string {
		return __( 'SEO Readiness', 'preflight-wp' );
	}

	/**
	 * Return the ordered list of check IDs in this category.
	 *
	 * Ordered warning → info (matches Brief §3.2 severity ordering).
	 *
	 * @return string[]
	 */
	public function get_check_ids(): array {
		return array_column( self::get_spec(), 0 );
	}

	/**
	 * Return a check object for the given ID, or null if unknown.
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
	 *
	 * @return array[]
	 */
	private static function get_spec(): array {
		return array(
			array( 'seo.no-sitemap-generator', __( 'XML sitemap generator',    'preflight-wp' ), PREFLIGHT_SEVERITY_WARNING, 'check_sitemap_generator' ),
			array( 'seo.no-seo-plugin',        __( 'SEO plugin detection',     'preflight-wp' ), PREFLIGHT_SEVERITY_INFO,    'check_seo_plugin' ),
			array( 'seo.no-og-plugin',         __( 'Open Graph tag generator', 'preflight-wp' ), PREFLIGHT_SEVERITY_INFO,    'check_og_plugin' ),
		);
	}

	// -------------------------------------------------------------------------
	// Detection methods (stubs — implemented in subsequent commits)
	// -------------------------------------------------------------------------

	/**
	 * Fail when no XML sitemap generator is active.
	 *
	 * Brief §3.2's original wording referenced "WP < 5.5" as the condition for
	 * checking sitemap generators. At the WP 6.0+ baseline, core sitemaps are
	 * always available via wp-sitemap.xml unless explicitly disabled. Corrected
	 * detection logic:
	 *   1. Pass if any SEO plugin in SEO_PLUGIN_SIGNALS is active — all listed
	 *      SEO plugins generate sitemaps.
	 *   2. Else pass if apply_filters('wp_sitemaps_enabled', true) === true —
	 *      core sitemap generator is not disabled.
	 *   3. Else fail.
	 *
	 * Loopback forbidden (Brief §5.2) — cannot verify the sitemap URL actually
	 * returns content. HTTP accessibility is deferred to Phase 5.
	 *
	 * @since 0.5.0
	 * @return PreFlight_Check_Result
	 */
	public function check_sitemap_generator(): PreFlight_Check_Result {
		// SEO plugin present — all listed plugins generate sitemaps.
		if ( null !== $this->matches_any_signal( self::SEO_PLUGIN_SIGNALS ) ) {
			return PreFlight_Check_Result::pass();
		}

		// Core sitemap generator enabled (default WordPress 5.5+ behaviour).
		if ( true === apply_filters( 'wp_sitemaps_enabled', true ) ) {
			return PreFlight_Check_Result::pass();
		}

		return PreFlight_Check_Result::fail(
			__( 'No sitemap generator is active. The site is on WordPress 6.0+ but core sitemaps appear to be disabled via the wp_sitemaps_enabled filter, and no SEO plugin that generates sitemaps is detected.', 'preflight-wp' ),
			__( 'Either remove the filter that disables core sitemaps, or install an SEO plugin that generates sitemaps (Yoast SEO, Rank Math, All in One SEO, or SEOPress).', 'preflight-wp' )
		);
	}

	/**
	 * Fail when no recognised SEO plugin is active.
	 *
	 * @since 0.5.0
	 * @return PreFlight_Check_Result
	 */
	public function check_seo_plugin(): PreFlight_Check_Result {
		return PreFlight_Check_Result::skip( 'not yet implemented' );
	}

	/**
	 * Fail when no plugin that generates Open Graph tags is detected.
	 *
	 * @since 0.5.0
	 * @return PreFlight_Check_Result
	 */
	public function check_og_plugin(): PreFlight_Check_Result {
		return PreFlight_Check_Result::skip( 'not yet implemented' );
	}

	// -------------------------------------------------------------------------
	// Private helpers (stubs)
	// -------------------------------------------------------------------------

	/**
	 * Return the first matched plugin label for the given signal map, or null.
	 *
	 * @param array<string, string> $signals Plugin label => detection signal.
	 * @return string|null Matched plugin label, or null if none matched.
	 */
	private function matches_any_signal( array $signals ): ?string {
		foreach ( $signals as $label => $signal ) {
			if ( class_exists( $signal ) || defined( $signal ) ) {
				return $label;
			}
		}
		return null;
	}
}

add_action(
	'preflight_register_categories',
	static function ( PreFlight_Core $core ): void {
		$core->register_category( new PreFlight_Checks_SEO() );
	}
);

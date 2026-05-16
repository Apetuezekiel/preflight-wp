<?php
/**
 * Performance Basics check category — 4 checks per Brief §3.2.
 *
 * @package PreFlight
 * @since   0.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Check category: Performance Basics.
 *
 * Detects absent caching plugin, PHP version below 8.0, WordPress not on
 * latest major version, and absent object cache. All detection uses internal
 * WordPress APIs, PHP built-ins, and constant checks — no loopback HTTP
 * requests (Brief §5.2).
 *
 * All CACHING_PLUGIN_SIGNALS were verified against current published plugin
 * source (2026-05-16). See class constant docblock for per-signal source paths.
 *
 * @since 0.7.0
 */
class PreFlight_Checks_Performance implements PreFlight_Check_Category {

	/**
	 * Plugin label → detection signal map for recognised caching plugins.
	 *
	 * Each signal is evaluated with:  class_exists($signal) || defined($signal)
	 *
	 * Verified sources (2026-05-16):
	 *   WP Super Cache   : WPSC_VERSION_ID — define() in wp-cache.php
	 *                      plugins.svn.wordpress.org/wp-super-cache/trunk/wp-cache.php
	 *   W3 Total Cache   : W3TC_VERSION — define() in w3-total-cache-api.php (always
	 *                      required by main file unless W3TC_IN_MINIFY is defined)
	 *                      plugins.svn.wordpress.org/w3-total-cache/trunk/w3-total-cache-api.php
	 *   LiteSpeed Cache  : LSCWP_V — define() in litespeed-cache.php
	 *                      plugins.svn.wordpress.org/litespeed-cache/trunk/litespeed-cache.php
	 *   WP Rocket        : WP_ROCKET_VERSION — define() in wp-rocket.php (commercial)
	 *                      verified via github.com/wp-media/wp-rocket (official mirror)
	 *   WP Fastest Cache : WpFastestCache (class) — no version constant in main file;
	 *                      class always instantiated on load
	 *                      plugins.svn.wordpress.org/wp-fastest-cache/trunk/wpFastestCache.php
	 *   Autoptimize      : AUTOPTIMIZE_PLUGIN_VERSION — define() in autoptimize.php
	 *                      plugins.svn.wordpress.org/autoptimize/trunk/autoptimize.php
	 *
	 * Extension point (v1.1): a filter hook 'preflight_caching_plugin_signals'
	 * will allow site-specific additions. Do not implement the filter now.
	 *
	 * @var array<string, string>
	 */
	const CACHING_PLUGIN_SIGNALS = array(
		'WP Super Cache'   => 'WPSC_VERSION_ID',
		'W3 Total Cache'   => 'W3TC_VERSION',
		'LiteSpeed Cache'  => 'LSCWP_V',
		'WP Rocket'        => 'WP_ROCKET_VERSION',
		'WP Fastest Cache' => 'WpFastestCache',
		'Autoptimize'      => 'AUTOPTIMIZE_PLUGIN_VERSION',
	);

	// -------------------------------------------------------------------------
	// PreFlight_Check_Category interface
	// -------------------------------------------------------------------------

	/**
	 * @return string
	 */
	public function get_category_id(): string {
		return 'performance';
	}

	/**
	 * @return string
	 */
	public function get_category_label(): string {
		return __( 'Performance Basics', 'preflight-wp' );
	}

	/**
	 * Return the ordered list of check IDs in this category.
	 *
	 * Ordered warning → info (PHP version is Warning; rest are Info).
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
	 * Ordered warning → info.
	 *
	 * @return array[]
	 */
	private static function get_spec(): array {
		return array(
			array( 'performance.php-version-outdated', __( 'PHP version',              'preflight-wp' ), PREFLIGHT_SEVERITY_WARNING, 'check_php_version' ),
			array( 'performance.no-caching-plugin',    __( 'Caching plugin',           'preflight-wp' ), PREFLIGHT_SEVERITY_INFO,    'check_caching_plugin' ),
			array( 'performance.no-object-cache',      __( 'Object caching',           'preflight-wp' ), PREFLIGHT_SEVERITY_INFO,    'check_object_cache' ),
			array( 'performance.wp-version-outdated',  __( 'WordPress major version',  'preflight-wp' ), PREFLIGHT_SEVERITY_INFO,    'check_wp_version' ),
		);
	}

	// -------------------------------------------------------------------------
	// Detection methods
	// -------------------------------------------------------------------------

	/**
	 * Fail when the installed PHP version is below 8.0.
	 *
	 * PHP 7.4 reached end-of-life November 2022. Production sites running 7.4
	 * carry unpatched security vulnerabilities. version_compare() handles
	 * all three-part version strings correctly.
	 *
	 * @since 0.7.0
	 * @return PreFlight_Check_Result
	 */
	public function check_php_version(): PreFlight_Check_Result {
		$current = phpversion();

		if ( version_compare( $current, '8.0', '<' ) ) {
			return PreFlight_Check_Result::fail(
				sprintf(
					/* translators: %s: current PHP version string */
					__( 'PHP %s is installed. PHP 7.4 reached end-of-life in November 2022 and no longer receives security updates.', 'preflight-wp' ),
					$current
				),
				__( 'Contact your host to upgrade PHP to 8.2 or later. Test the site after upgrading — some plugins have PHP 8.x compatibility issues.', 'preflight-wp' )
			);
		}

		return PreFlight_Check_Result::pass();
	}

	/**
	 * Fail when no recognised caching plugin is active.
	 *
	 * Note: many managed hosts provide server-level caching without a WordPress
	 * plugin. The fail message acknowledges this so the developer can make an
	 * informed decision about suppressing the check.
	 *
	 * @since 0.7.0
	 * @return PreFlight_Check_Result
	 */
	public function check_caching_plugin(): PreFlight_Check_Result {
		if ( null !== $this->matches_any_signal( self::CACHING_PLUGIN_SIGNALS ) ) {
			return PreFlight_Check_Result::pass();
		}

		return PreFlight_Check_Result::fail(
			__( 'No recognised caching plugin is active.', 'preflight-wp' ),
			__( 'If the site is on a host with server-level caching (e.g., Kinsta, WP Engine, or any host running NGINX FastCGI or Varnish), this check can be suppressed. Otherwise, install a caching plugin: LiteSpeed Cache, WP Rocket, W3 Total Cache, or WP Super Cache.', 'preflight-wp' )
		);
	}

	/**
	 * Fail when the WordPress persistent object cache is not active.
	 *
	 * wp_using_ext_object_cache() returns true when a persistent object cache
	 * drop-in (object-cache.php) is installed and active. Standard WP installs
	 * use a non-persistent in-memory cache that is discarded between requests.
	 *
	 * @since 0.7.0
	 * @return PreFlight_Check_Result
	 */
	public function check_object_cache(): PreFlight_Check_Result {
		if ( wp_using_ext_object_cache() ) {
			return PreFlight_Check_Result::pass();
		}

		return PreFlight_Check_Result::fail(
			__( 'No persistent object cache (e.g., Redis or Memcached) is active. WordPress is using a per-request in-memory cache that is discarded on every page load.', 'preflight-wp' ),
			__( 'Object caching is most impactful on sites with many database queries (WooCommerce, membership plugins, heavy custom queries). Install a Redis or Memcached drop-in plugin and verify your host supports the backend.', 'preflight-wp' )
		);
	}

	/**
	 * Fail when WordPress is not on the latest major version.
	 *
	 * Reads the cached core-update transient populated by WordPress's background
	 * update check — no external HTTP request is made by this check (Brief §5.2).
	 * If the transient is absent or expired (update check hasn't run yet), the
	 * check is skipped rather than failed to avoid false positives on fresh installs.
	 *
	 * Compares major.minor version only (e.g., 6.5 vs 6.6) — patch releases
	 * (6.5.5 → 6.5.6) are not flagged since WordPress auto-updates patches.
	 *
	 * @since 0.7.0
	 * @return PreFlight_Check_Result
	 */
	public function check_wp_version(): PreFlight_Check_Result {
		$update_data = get_site_transient( 'update_core' );

		if ( ! $update_data instanceof stdClass || empty( $update_data->updates ) ) {
			return PreFlight_Check_Result::skip(
				__( 'WordPress update data is not available — the background update check has not run yet. Re-scan after the update check completes.', 'preflight-wp' )
			);
		}

		$current = get_bloginfo( 'version' );

		foreach ( $update_data->updates as $update ) {
			if ( 'upgrade' !== $update->response ) {
				continue;
			}

			// Compare major.minor only — ignore patch component.
			$current_parts  = explode( '.', $current );
			$latest_parts   = explode( '.', $update->current );
			$current_major  = (int) ( $current_parts[0] ?? 0 ) . '.' . (int) ( $current_parts[1] ?? 0 );
			$latest_major   = (int) ( $latest_parts[0] ?? 0 ) . '.' . (int) ( $latest_parts[1] ?? 0 );

			if ( version_compare( $current_major, $latest_major, '<' ) ) {
				return PreFlight_Check_Result::fail(
					sprintf(
						/* translators: 1: current WP version, 2: latest WP version */
						__( 'WordPress %1$s is installed. The latest major version is %2$s.', 'preflight-wp' ),
						$current,
						$update->current
					),
					__( 'Update WordPress via Dashboard → Updates. Test the site after updating — backup first if no automated backup is in place.', 'preflight-wp' )
				);
			}
		}

		return PreFlight_Check_Result::pass();
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Return the first matched plugin label for the given signal map, or null.
	 *
	 * Intentionally duplicated from PreFlight_Checks_SEO and PreFlight_Checks_Forms
	 * — no shared extraction until a third category needs it beyond these two (YAGNI).
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
		$core->register_category( new PreFlight_Checks_Performance() );
	}
);

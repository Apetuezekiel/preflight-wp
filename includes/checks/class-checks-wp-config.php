<?php
/**
 * WordPress Configuration check category — 12 checks per Brief §3.2.
 *
 * @package Prelaunch_Checker
 * @since   0.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Check category: WordPress Configuration.
 *
 * Detects common pre-launch misconfigurations in WordPress core settings,
 * debug flags, URL alignment, and update configuration. All detection uses
 * internal WordPress APIs and file existence checks — no loopback HTTP
 * requests (Brief §5.2).
 *
 * @since 0.2.0
 */
class Prelaunch_Checker_Checks_WP_Config implements Prelaunch_Checker_Check_Category {

	/**
	 * Known staging and local-development host patterns.
	 *
	 * Exact matches are compared case-insensitively after lowercasing the host.
	 * Suffix matches require the host to end with the listed value (anchored,
	 * leading dot included so '.local' does not match 'mylocal.example.com').
	 *
	 * Extension point (v1.1): a filter hook 'prelaunch_checker_staging_hosts' will be
	 * added here when custom host overrides are implemented. Do not add the
	 * filter now; document the intent only.
	 *
	 * @var array{ exact: string[], suffix: string[] }
	 */
	const STAGING_HOSTS = array(
		'exact'  => array( 'localhost', '127.0.0.1', '::1' ),
		'suffix' => array(
			'.wpengine.com',
			'.wpenginepowered.com',
			'.kinsta.cloud',
			'.flywheelsites.com',
			'.flywheel.local',
			'.pantheonsite.io',
			'.cloudwaysapps.com',
			'.test',
			'.local',
		),
	);

	// -------------------------------------------------------------------------
	// Prelaunch_Checker_Check_Category interface
	// -------------------------------------------------------------------------

	/**
	 * @return string
	 */
	public function get_category_id(): string {
		return 'wp-config';
	}

	/**
	 * @return string
	 */
	public function get_category_label(): string {
		return __( 'WordPress Configuration', 'prelaunch-checker' );
	}

	/**
	 * Return all namespaced check IDs in this category.
	 *
	 * Order matches Brief §3.2 table order and determines display order in the
	 * results dashboard when the admin UI renders checks top-to-bottom.
	 *
	 * @return string[]
	 */
	public function get_check_ids(): array {
		return array_keys( self::get_spec() );
	}

	/**
	 * Instantiate a check by ID (lazy — called only during an active scan).
	 *
	 * @param  string $id Namespaced check ID.
	 * @return Prelaunch_Checker_Check|null
	 */
	public function get_check( string $id ): ?Prelaunch_Checker_Check {
		$spec = self::get_spec();
		if ( ! isset( $spec[ $id ] ) ) {
			return null;
		}

		list( $label, $severity, $method ) = $spec[ $id ];

		return new Prelaunch_Checker_Configurable_Check(
			$id,
			$label,
			$severity,
			array( $this, $method )
		);
	}

	// -------------------------------------------------------------------------
	// Spec map
	// -------------------------------------------------------------------------

	/**
	 * Static spec map: check ID → [label, severity, method_name].
	 *
	 * Kept in a static method (not a constant) so translation functions are
	 * called at runtime, not at parse time.
	 *
	 * @return array<string, array{0: string, 1: string, 2: string}>
	 */
	private static function get_spec(): array {
		return array(
			'wp-config.default-tagline'           => array( __( 'Default tagline',            'prelaunch-checker' ), PRELAUNCH_CHECKER_SEVERITY_BLOCKER, 'check_default_tagline' ),
			'wp-config.search-engine-discouraged' => array( __( 'Search engine visibility',   'prelaunch-checker' ), PRELAUNCH_CHECKER_SEVERITY_BLOCKER, 'check_search_engine_visibility' ),
			'wp-config.debug-enabled'             => array( __( 'WP_DEBUG enabled',           'prelaunch-checker' ), PRELAUNCH_CHECKER_SEVERITY_BLOCKER, 'check_wp_debug' ),
			'wp-config.debug-display-enabled'     => array( __( 'WP_DEBUG_DISPLAY enabled',   'prelaunch-checker' ), PRELAUNCH_CHECKER_SEVERITY_BLOCKER, 'check_wp_debug_display' ),
			'wp-config.debug-log-exposed'         => array( __( 'Debug log file present',     'prelaunch-checker' ), PRELAUNCH_CHECKER_SEVERITY_WARNING, 'check_debug_log_file' ),
			'wp-config.timezone-not-set'          => array( __( 'Timezone configuration',     'prelaunch-checker' ), PRELAUNCH_CHECKER_SEVERITY_WARNING, 'check_timezone' ),
			'wp-config.permalink-plain'           => array( __( 'Permalink structure',        'prelaunch-checker' ), PRELAUNCH_CHECKER_SEVERITY_WARNING, 'check_permalink_structure' ),
			'wp-config.url-mismatch'              => array( __( 'Site/Home URL alignment',    'prelaunch-checker' ), PRELAUNCH_CHECKER_SEVERITY_WARNING, 'check_url_mismatch' ),
			'wp-config.staging-host'              => array( __( 'Known staging host',         'prelaunch-checker' ), PRELAUNCH_CHECKER_SEVERITY_BLOCKER, 'check_staging_host' ),
			'wp-config.dev-substring'             => array( __( 'Dev/staging substring',      'prelaunch-checker' ), PRELAUNCH_CHECKER_SEVERITY_WARNING, 'check_dev_substring' ),
			'wp-config.scheme-inconsistent'       => array( __( 'URL scheme consistency',     'prelaunch-checker' ), PRELAUNCH_CHECKER_SEVERITY_WARNING, 'check_scheme_consistency' ),
			'wp-config.auto-updates-disabled'     => array( __( 'Core auto-updates',          'prelaunch-checker' ), PRELAUNCH_CHECKER_SEVERITY_INFO,    'check_auto_updates' ),
		);
	}

	// -------------------------------------------------------------------------
	// Detection methods — implemented in subsequent commits
	// -------------------------------------------------------------------------

	/**
	 * Check: default tagline still set. (Blocker)
	 *
	 * Compares the current tagline against the locale-translated default string.
	 * Locale note: a locale change *after* install may cause a false negative if
	 * the translated default differs from the stored value. Document limitation;
	 * no runtime workaround at MVP.
	 *
	 * @return Prelaunch_Checker_Check_Result
	 */
	public function check_default_tagline(): Prelaunch_Checker_Check_Result {
		$current = trim( get_bloginfo( 'description' ) );
		$default = trim( __( 'Just another WordPress site' ) ); // phpcs:ignore WordPress.WP.I18n.MissingArgDomain -- intentionally matches WP core string, no plugin domain.

		if ( $current === $default ) {
			return Prelaunch_Checker_Check_Result::fail(
				__( 'Site tagline is the WordPress default.', 'prelaunch-checker' ),
				__( 'Settings → General → Tagline: replace with text describing this specific site.', 'prelaunch-checker' )
			);
		}

		return Prelaunch_Checker_Check_Result::pass();
	}

	/**
	 * Check: search engine indexing discouraged. (Blocker)
	 *
	 * @return Prelaunch_Checker_Check_Result
	 */
	public function check_search_engine_visibility(): Prelaunch_Checker_Check_Result {
		if ( (int) get_option( 'blog_public' ) === 0 ) {
			return Prelaunch_Checker_Check_Result::fail(
				__( 'Search engine indexing is set to Discouraged.', 'prelaunch-checker' ),
				__( 'Settings → Reading: uncheck "Discourage search engines from indexing this site" before DNS cutover.', 'prelaunch-checker' )
			);
		}

		return Prelaunch_Checker_Check_Result::pass();
	}

	/**
	 * Check: WP_DEBUG enabled. (Blocker)
	 *
	 * @return Prelaunch_Checker_Check_Result
	 */
	public function check_wp_debug(): Prelaunch_Checker_Check_Result {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true ) {
			return Prelaunch_Checker_Check_Result::fail(
				__( 'WP_DEBUG is enabled.', 'prelaunch-checker' ),
				__( 'wp-config.php: set define(\'WP_DEBUG\', false); for production.', 'prelaunch-checker' )
			);
		}

		return Prelaunch_Checker_Check_Result::pass();
	}

	/**
	 * Check: WP_DEBUG_DISPLAY enabled. (Blocker)
	 *
	 * Reported even when WP_DEBUG is false — enabling WP_DEBUG later would
	 * immediately leak errors to page output if WP_DEBUG_DISPLAY is already true.
	 *
	 * @return Prelaunch_Checker_Check_Result
	 */
	public function check_wp_debug_display(): Prelaunch_Checker_Check_Result {
		if ( defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY === true ) {
			return Prelaunch_Checker_Check_Result::fail(
				__( 'WP_DEBUG_DISPLAY is enabled; PHP errors will print to page output when WP_DEBUG is also enabled.', 'prelaunch-checker' ),
				__( 'wp-config.php: set define(\'WP_DEBUG_DISPLAY\', false); before any WP_DEBUG definition.', 'prelaunch-checker' )
			);
		}

		return Prelaunch_Checker_Check_Result::pass();
	}

	/**
	 * Check: debug log file exists. (Warning)
	 *
	 * Resolves the log path from WP_DEBUG_LOG (string path, true, or undefined)
	 * and falls back to checking WP_CONTENT_DIR/debug.log regardless of the
	 * constant — a log file can persist after the constant is removed.
	 * HTTP accessibility cannot be verified without loopback (Phase 5).
	 *
	 * @return Prelaunch_Checker_Check_Result
	 */
	public function check_debug_log_file(): Prelaunch_Checker_Check_Result {
		$log_path = $this->resolve_debug_log_path();

		if ( null !== $log_path && file_exists( $log_path ) ) {
			$relative = str_replace( ABSPATH, '', $log_path );
			return Prelaunch_Checker_Check_Result::fail(
				sprintf(
					/* translators: %s: relative file path */
					__( 'Debug log file exists at %s. HTTP accessibility cannot be verified without loopback (Phase 5).', 'prelaunch-checker' ),
					$relative
				),
				__( 'Delete the log file, or move it outside the webroot, or block public access at the server level. Set WP_DEBUG_LOG to false in wp-config.php.', 'prelaunch-checker' )
			);
		}

		return Prelaunch_Checker_Check_Result::pass();
	}

	/**
	 * Resolve the debug log file path from constants and fallback.
	 *
	 * @return string|null Absolute path, or null if no path can be determined.
	 */
	private function resolve_debug_log_path(): ?string {
		if ( defined( 'WP_DEBUG_LOG' ) ) {
			if ( is_string( WP_DEBUG_LOG ) ) {
				return WP_DEBUG_LOG;
			}
			if ( WP_DEBUG_LOG === true ) {
				return WP_CONTENT_DIR . '/debug.log';
			}
			// WP_DEBUG_LOG === false: still check default location.
		}
		return WP_CONTENT_DIR . '/debug.log';
	}

	/**
	 * Check: timezone configured as UTC offset only. (Warning)
	 *
	 * @return Prelaunch_Checker_Check_Result
	 */
	public function check_timezone(): Prelaunch_Checker_Check_Result {
		if ( '' === get_option( 'timezone_string' ) ) {
			return Prelaunch_Checker_Check_Result::fail(
				__( 'Timezone is set as a UTC offset only; DST transitions will not be applied automatically.', 'prelaunch-checker' ),
				__( 'Settings → General → Timezone: select a named city (e.g., "America/New_York") instead of a UTC offset.', 'prelaunch-checker' )
			);
		}

		return Prelaunch_Checker_Check_Result::pass();
	}

	/**
	 * Check: permalink structure set to Plain. (Warning)
	 *
	 * @return Prelaunch_Checker_Check_Result
	 */
	public function check_permalink_structure(): Prelaunch_Checker_Check_Result {
		if ( '' === get_option( 'permalink_structure' ) ) {
			return Prelaunch_Checker_Check_Result::fail(
				__( 'Permalinks are set to Plain; URLs use ?p=123 query strings.', 'prelaunch-checker' ),
				__( 'Settings → Permalinks: select any structure other than Plain (recommended: Post name).', 'prelaunch-checker' )
			);
		}

		return Prelaunch_Checker_Check_Result::pass();
	}

	/**
	 * Check: Site URL and Home URL differ in scheme or host. (Warning)
	 *
	 * Legitimate installs exist where WP lives in a subdirectory while the
	 * public URL is the domain root — this is a Warning, not a Blocker, to
	 * acknowledge that use case. Suppressible per Brief §3.2.
	 *
	 * @return Prelaunch_Checker_Check_Result
	 */
	public function check_url_mismatch(): Prelaunch_Checker_Check_Result {
		$site = wp_parse_url( site_url() );
		$home = wp_parse_url( home_url() );

		$site_scheme = isset( $site['scheme'] ) ? strtolower( $site['scheme'] ) : '';
		$home_scheme = isset( $home['scheme'] ) ? strtolower( $home['scheme'] ) : '';
		$site_host   = isset( $site['host'] ) ? strtolower( $site['host'] ) : '';
		$home_host   = isset( $home['host'] ) ? strtolower( $home['host'] ) : '';

		if ( $site_scheme !== $home_scheme || $site_host !== $home_host ) {
			return Prelaunch_Checker_Check_Result::fail(
				__( 'Site URL and Home URL differ in scheme or host.', 'prelaunch-checker' ),
				__( 'If WordPress is intentionally installed at a separate location from the public site root, suppress this check. Otherwise, Settings → General: align both URLs to the production domain.', 'prelaunch-checker' )
			);
		}

		return Prelaunch_Checker_Check_Result::pass();
	}

	/**
	 * Check: site URL host matches a known staging/local pattern. (Blocker)
	 *
	 * Uses STAGING_HOSTS class constant. Exact matches are full-host comparisons;
	 * suffix matches use str_ends_with on a lowercased host.
	 *
	 * Extension point: a 'prelaunch_checker_staging_hosts' filter will be added in v1.1
	 * to allow custom host lists. Not wired here — intent documented only.
	 *
	 * @return Prelaunch_Checker_Check_Result
	 */
	public function check_staging_host(): Prelaunch_Checker_Check_Result {
		$host = strtolower( (string) wp_parse_url( site_url(), PHP_URL_HOST ) );

		if ( in_array( $host, self::STAGING_HOSTS['exact'], true ) ) {
			return $this->staging_fail( $host );
		}

		foreach ( self::STAGING_HOSTS['suffix'] as $suffix ) {
			if ( str_ends_with( $host, $suffix ) ) {
				return $this->staging_fail( $host );
			}
		}

		return Prelaunch_Checker_Check_Result::pass();
	}

	/**
	 * Build the fail result for a staging host match.
	 *
	 * @param  string $host
	 * @return Prelaunch_Checker_Check_Result
	 */
	private function staging_fail( string $host ): Prelaunch_Checker_Check_Result {
		return Prelaunch_Checker_Check_Result::fail(
			sprintf(
				/* translators: %s: site URL hostname */
				__( 'Site URL host "%s" matches a known staging or local-development pattern.', 'prelaunch-checker' ),
				$host
			),
			__( 'Update Site URL and Home URL to the production domain before launch. If this IS the production site, suppress this check.', 'prelaunch-checker' )
		);
	}

	/**
	 * Check: site URL host contains a generic dev/staging substring. (Warning)
	 *
	 * Runs independently of check_staging_host — both can fire together.
	 * Suppressible per Brief §3.2 for legitimate production domains that
	 * contain one of these substrings.
	 *
	 * @return Prelaunch_Checker_Check_Result
	 */
	public function check_dev_substring(): Prelaunch_Checker_Check_Result {
		$host     = strtolower( (string) wp_parse_url( site_url(), PHP_URL_HOST ) );
		$patterns = array( 'dev.', 'staging.', '-staging', '-dev', '.dev-', '.staging-' );

		foreach ( $patterns as $pattern ) {
			if ( strpos( $host, $pattern ) !== false ) {
				return Prelaunch_Checker_Check_Result::fail(
					sprintf(
						/* translators: %s: site URL hostname */
						__( 'Site URL host "%s" contains a generic development/staging substring.', 'prelaunch-checker' ),
						$host
					),
					__( 'If this substring is part of the legitimate production domain, suppress this check. Otherwise, update Site URL and Home URL to the production domain.', 'prelaunch-checker' )
				);
			}
		}

		return Prelaunch_Checker_Check_Result::pass();
	}

	/**
	 * Check: URL scheme inconsistency across Site URL, Home URL, WP_CONTENT_URL. (Warning)
	 *
	 * WP_CONTENT_URL is only inspected when explicitly defined — the default
	 * value (site_url() . '/wp-content') would always match site_url()'s scheme.
	 *
	 * @return Prelaunch_Checker_Check_Result
	 */
	public function check_scheme_consistency(): Prelaunch_Checker_Check_Result {
		$schemes = array();
		$sources = array();

		$site_scheme = strtolower( (string) wp_parse_url( site_url(), PHP_URL_SCHEME ) );
		$home_scheme = strtolower( (string) wp_parse_url( home_url(), PHP_URL_SCHEME ) );

		$schemes[] = $site_scheme;
		$sources[]  = 'Site URL';
		$schemes[] = $home_scheme;
		$sources[]  = 'Home URL';

		if ( defined( 'WP_CONTENT_URL' ) ) {
			$content_scheme = strtolower( (string) wp_parse_url( WP_CONTENT_URL, PHP_URL_SCHEME ) );
			$schemes[]      = $content_scheme;
			$sources[]      = 'WP_CONTENT_URL';
		}

		if ( count( array_unique( $schemes ) ) > 1 ) {
			return Prelaunch_Checker_Check_Result::fail(
				sprintf(
					/* translators: %s: comma-separated list of URL sources checked */
					__( 'URL schemes are not consistent across %s.', 'prelaunch-checker' ),
					implode( ', ', $sources )
				),
				__( 'Align all URL schemes to https for production. Update wp-config.php if WP_CONTENT_URL is explicitly defined.', 'prelaunch-checker' )
			);
		}

		return Prelaunch_Checker_Check_Result::pass();
	}

	/**
	 * Check: automatic updates for WordPress core disabled. (Info)
	 *
	 * Evaluation order (Brief v1.5 §3.2, §5.2):
	 *   1. AUTOMATIC_UPDATER_DISABLED constant — unconditional.
	 *   2. WP_AUTO_UPDATE_CORE === false constant — unconditional.
	 *   3. wp_is_auto_update_enabled_for_type('core') — guarded by update_core transient.
	 *      The function reads the transient internally; when the transient transitions
	 *      from absent to populated between two scans, it returns different values for
	 *      identical site state, violating §5.2 idempotency. Guard: skip if transient absent.
	 *
	 * @return Prelaunch_Checker_Check_Result
	 */
	public function check_auto_updates(): Prelaunch_Checker_Check_Result {
		if ( defined( 'AUTOMATIC_UPDATER_DISABLED' ) && AUTOMATIC_UPDATER_DISABLED === true ) {
			return $this->auto_updates_fail();
		}

		if ( defined( 'WP_AUTO_UPDATE_CORE' ) && WP_AUTO_UPDATE_CORE === false ) {
			return $this->auto_updates_fail();
		}

		// Brief §5.2 idempotency guard (v1.5): wp_is_auto_update_enabled_for_type() reads
		// the update_core transient internally. Skip filter-based detection when the transient
		// is absent to prevent the check flipping between consecutive scans as WordPress
		// populates the transient during its background update check.
		// The function lives in wp-admin/includes/update.php (admin-only file); require
		// explicitly so it is available in non-browser contexts (WP-CLI, test scripts).
		require_once ABSPATH . 'wp-admin/includes/update.php';
		if ( function_exists( 'wp_is_auto_update_enabled_for_type' ) ) {
			$transient = get_site_transient( 'update_core' );
			if ( false === $transient || ! isset( $transient->updates ) ) {
				return Prelaunch_Checker_Check_Result::skip(
					__( 'Cannot evaluate auto-update filters without the update_core transient. Visit Dashboard → Updates to trigger WordPress\'s update check, then re-run the scan.', 'prelaunch-checker' )
				);
			}

			if ( ! wp_is_auto_update_enabled_for_type( 'core' ) ) {
				return $this->auto_updates_fail();
			}
		}

		return Prelaunch_Checker_Check_Result::pass();
	}

	/**
	 * @return Prelaunch_Checker_Check_Result
	 */
	private function auto_updates_fail(): Prelaunch_Checker_Check_Result {
		return Prelaunch_Checker_Check_Result::fail(
			__( 'Automatic updates for WordPress core are disabled.', 'prelaunch-checker' ),
			__( 'If updates are managed manually (e.g., via a deployment pipeline), this is informational only. Otherwise, remove AUTOMATIC_UPDATER_DISABLED or WP_AUTO_UPDATE_CORE constants from wp-config.php.', 'prelaunch-checker' )
		);
	}
}

// Self-registration: hooks onto 'prelaunch_checker_register_categories' — no
// modification to scanner, Core, or admin UI code required (Brief §11).
add_action(
	'prelaunch_checker_register_categories',
	function ( Prelaunch_Checker_Core $core ) {
		$core->register_category( new Prelaunch_Checker_Checks_WP_Config() );
	}
);

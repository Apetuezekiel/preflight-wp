<?php
/**
 * WordPress Configuration check category — 12 checks per Brief §3.2.
 *
 * @package PreFlight
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
class PreFlight_Checks_WP_Config implements PreFlight_Check_Category {

	/**
	 * Known staging and local-development host patterns.
	 *
	 * Exact matches are compared case-insensitively after lowercasing the host.
	 * Suffix matches require the host to end with the listed value (anchored,
	 * leading dot included so '.local' does not match 'mylocal.example.com').
	 *
	 * Extension point (v1.1): a filter hook 'preflight_staging_hosts' will be
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
	// PreFlight_Check_Category interface
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
		return __( 'WordPress Configuration', 'preflight-wp' );
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
	 * @return PreFlight_Check|null
	 */
	public function get_check( string $id ): ?PreFlight_Check {
		$spec = self::get_spec();
		if ( ! isset( $spec[ $id ] ) ) {
			return null;
		}

		list( $label, $severity, $method ) = $spec[ $id ];

		return new PreFlight_Configurable_Check(
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
			'wp-config.default-tagline'           => array( __( 'Default tagline',            'preflight-wp' ), PREFLIGHT_SEVERITY_BLOCKER, 'check_default_tagline' ),
			'wp-config.search-engine-discouraged' => array( __( 'Search engine visibility',   'preflight-wp' ), PREFLIGHT_SEVERITY_BLOCKER, 'check_search_engine_visibility' ),
			'wp-config.debug-enabled'             => array( __( 'WP_DEBUG enabled',           'preflight-wp' ), PREFLIGHT_SEVERITY_BLOCKER, 'check_wp_debug' ),
			'wp-config.debug-display-enabled'     => array( __( 'WP_DEBUG_DISPLAY enabled',   'preflight-wp' ), PREFLIGHT_SEVERITY_BLOCKER, 'check_wp_debug_display' ),
			'wp-config.debug-log-exposed'         => array( __( 'Debug log file present',     'preflight-wp' ), PREFLIGHT_SEVERITY_WARNING, 'check_debug_log_file' ),
			'wp-config.timezone-not-set'          => array( __( 'Timezone configuration',     'preflight-wp' ), PREFLIGHT_SEVERITY_WARNING, 'check_timezone' ),
			'wp-config.permalink-plain'           => array( __( 'Permalink structure',        'preflight-wp' ), PREFLIGHT_SEVERITY_WARNING, 'check_permalink_structure' ),
			'wp-config.url-mismatch'              => array( __( 'Site/Home URL alignment',    'preflight-wp' ), PREFLIGHT_SEVERITY_WARNING, 'check_url_mismatch' ),
			'wp-config.staging-host'              => array( __( 'Known staging host',         'preflight-wp' ), PREFLIGHT_SEVERITY_BLOCKER, 'check_staging_host' ),
			'wp-config.dev-substring'             => array( __( 'Dev/staging substring',      'preflight-wp' ), PREFLIGHT_SEVERITY_WARNING, 'check_dev_substring' ),
			'wp-config.scheme-inconsistent'       => array( __( 'URL scheme consistency',     'preflight-wp' ), PREFLIGHT_SEVERITY_WARNING, 'check_scheme_consistency' ),
			'wp-config.auto-updates-disabled'     => array( __( 'Core auto-updates',          'preflight-wp' ), PREFLIGHT_SEVERITY_INFO,    'check_auto_updates' ),
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
	 * @return PreFlight_Check_Result
	 */
	public function check_default_tagline(): PreFlight_Check_Result {
		$current = trim( get_bloginfo( 'description' ) );
		$default = trim( __( 'Just another WordPress site' ) ); // Intentionally no text domain — matches WP core string.

		if ( $current === $default ) {
			return PreFlight_Check_Result::fail(
				__( 'Site tagline is the WordPress default.', 'preflight-wp' ),
				__( 'Settings → General → Tagline — replace with text describing this specific site.', 'preflight-wp' )
			);
		}

		return PreFlight_Check_Result::pass();
	}

	/**
	 * Check: search engine indexing discouraged. (Blocker)
	 *
	 * @return PreFlight_Check_Result
	 */
	public function check_search_engine_visibility(): PreFlight_Check_Result {
		if ( (int) get_option( 'blog_public' ) === 0 ) {
			return PreFlight_Check_Result::fail(
				__( 'Search engine indexing is set to Discouraged.', 'preflight-wp' ),
				__( 'Settings → Reading — uncheck "Discourage search engines from indexing this site" before DNS cutover.', 'preflight-wp' )
			);
		}

		return PreFlight_Check_Result::pass();
	}

	/**
	 * Check: WP_DEBUG enabled. (Blocker)
	 *
	 * @return PreFlight_Check_Result
	 */
	public function check_wp_debug(): PreFlight_Check_Result {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG === true ) {
			return PreFlight_Check_Result::fail(
				__( 'WP_DEBUG is enabled.', 'preflight-wp' ),
				__( 'wp-config.php — set define(\'WP_DEBUG\', false); for production.', 'preflight-wp' )
			);
		}

		return PreFlight_Check_Result::pass();
	}

	/**
	 * Check: WP_DEBUG_DISPLAY enabled. (Blocker)
	 *
	 * Reported even when WP_DEBUG is false — enabling WP_DEBUG later would
	 * immediately leak errors to page output if WP_DEBUG_DISPLAY is already true.
	 *
	 * @return PreFlight_Check_Result
	 */
	public function check_wp_debug_display(): PreFlight_Check_Result {
		if ( defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY === true ) {
			return PreFlight_Check_Result::fail(
				__( 'WP_DEBUG_DISPLAY is enabled — PHP errors will print to page output when WP_DEBUG is also enabled.', 'preflight-wp' ),
				__( 'wp-config.php — set define(\'WP_DEBUG_DISPLAY\', false); before any WP_DEBUG definition.', 'preflight-wp' )
			);
		}

		return PreFlight_Check_Result::pass();
	}

	/** @return PreFlight_Check_Result */
	public function check_debug_log_file(): PreFlight_Check_Result {
		return PreFlight_Check_Result::skip( __( 'Not yet implemented.', 'preflight-wp' ) );
	}

	/** @return PreFlight_Check_Result */
	public function check_timezone(): PreFlight_Check_Result {
		return PreFlight_Check_Result::skip( __( 'Not yet implemented.', 'preflight-wp' ) );
	}

	/** @return PreFlight_Check_Result */
	public function check_permalink_structure(): PreFlight_Check_Result {
		return PreFlight_Check_Result::skip( __( 'Not yet implemented.', 'preflight-wp' ) );
	}

	/** @return PreFlight_Check_Result */
	public function check_url_mismatch(): PreFlight_Check_Result {
		return PreFlight_Check_Result::skip( __( 'Not yet implemented.', 'preflight-wp' ) );
	}

	/** @return PreFlight_Check_Result */
	public function check_staging_host(): PreFlight_Check_Result {
		return PreFlight_Check_Result::skip( __( 'Not yet implemented.', 'preflight-wp' ) );
	}

	/** @return PreFlight_Check_Result */
	public function check_dev_substring(): PreFlight_Check_Result {
		return PreFlight_Check_Result::skip( __( 'Not yet implemented.', 'preflight-wp' ) );
	}

	/** @return PreFlight_Check_Result */
	public function check_scheme_consistency(): PreFlight_Check_Result {
		return PreFlight_Check_Result::skip( __( 'Not yet implemented.', 'preflight-wp' ) );
	}

	/** @return PreFlight_Check_Result */
	public function check_auto_updates(): PreFlight_Check_Result {
		return PreFlight_Check_Result::skip( __( 'Not yet implemented.', 'preflight-wp' ) );
	}
}

// Self-registration: hooks onto 'preflight_register_categories' — no
// modification to scanner, Core, or admin UI code required (Brief §11).
add_action(
	'preflight_register_categories',
	function ( PreFlight_Core $core ) {
		$core->register_category( new PreFlight_Checks_WP_Config() );
	}
);

<?php
/**
 * Security Basics check category — 6 checks per Brief §3.2.
 *
 * @package PreFlight
 * @since   0.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Check category: Security Basics.
 *
 * Detects common pre-launch security misconfigurations: missing SSL, exposed
 * WordPress files, wp-config backup files, XML-RPC state, in-dashboard file
 * editing, and the default database table prefix.
 *
 * All detection uses internal WordPress APIs, constant checks, file existence
 * checks, and $wpdb property reads — no loopback HTTP requests (Brief §5.2).
 *
 * @since 0.4.0
 */
class PreFlight_Checks_Security implements PreFlight_Check_Category {

	/**
	 * Filenames checked for wp-config backup copies in the webroot.
	 *
	 * Extension point (v1.1): a filter hook 'preflight_config_backup_filenames'
	 * will be added here when custom filename overrides are implemented. Do not
	 * add the filter now; document the intent only.
	 *
	 * @var string[]
	 */
	const CONFIG_BACKUP_FILENAMES = array(
		'wp-config.php.bak',
		'wp-config.php.old',
		'wp-config.php~',
		'wp-config.php.save',
		'wp-config.php.orig',
		'wp-config.bak',
	);

	// -------------------------------------------------------------------------
	// PreFlight_Check_Category interface
	// -------------------------------------------------------------------------

	/**
	 * @return string
	 */
	public function get_category_id(): string {
		return 'security';
	}

	/**
	 * @return string
	 */
	public function get_category_label(): string {
		return __( 'Security Basics', 'preflight-wp' );
	}

	/**
	 * Return the ordered list of check IDs in this category.
	 *
	 * Order matches Brief §3.2: severity descending (blocker first, then
	 * warnings, then info), then alphabetical within each severity tier.
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
	 * Ordered blocker → warning → info (matches get_check_ids() contract).
	 *
	 * @return array[]
	 */
	private static function get_spec(): array {
		return array(
			array( 'security.ssl-not-active',            __( 'SSL / HTTPS configuration',     'preflight-wp' ), PREFLIGHT_SEVERITY_BLOCKER, 'check_ssl' ),
			array( 'security.config-backups-in-webroot', __( 'wp-config backup files',        'preflight-wp' ), PREFLIGHT_SEVERITY_WARNING, 'check_config_backups' ),
			array( 'security.default-table-prefix',      __( 'Database table prefix',         'preflight-wp' ), PREFLIGHT_SEVERITY_INFO,    'check_table_prefix' ),
			array( 'security.file-editing-enabled',      __( 'Theme/plugin file editing',     'preflight-wp' ), PREFLIGHT_SEVERITY_INFO,    'check_file_editing' ),
			array( 'security.readme-html-accessible',    __( 'readme.html in webroot',        'preflight-wp' ), PREFLIGHT_SEVERITY_INFO,    'check_readme_html' ),
			array( 'security.xmlrpc-enabled',            __( 'XML-RPC enabled',               'preflight-wp' ), PREFLIGHT_SEVERITY_INFO,    'check_xmlrpc' ),
		);
	}

	// -------------------------------------------------------------------------
	// Detection methods (stubs — implemented in subsequent commits)
	// -------------------------------------------------------------------------

	/**
	 * Fail when the site's Home URL is not configured for HTTPS.
	 *
	 * Uses parse_url( home_url() ) rather than is_ssl() — the latter is a
	 * per-request runtime check that would always return false during a CLI
	 * or non-HTTPS admin scan, masking a correctly-configured HTTPS site.
	 * The pre-launch signal is whether the stored Home URL uses https.
	 *
	 * @since 0.4.0
	 * @return PreFlight_Check_Result
	 */
	public function check_ssl(): PreFlight_Check_Result {
		$scheme = parse_url( home_url(), PHP_URL_SCHEME );

		if ( 'https' !== $scheme ) {
			return PreFlight_Check_Result::fail(
				__( 'Site is not configured for HTTPS — Site Address uses an http scheme.', 'preflight-wp' ),
				__( 'Install an SSL certificate (most hosts offer free Let\'s Encrypt). Then go to Settings → General and update both Site URL and Home URL to https://. Update any hardcoded http URLs in content via a search-replace tool such as WP-CLI\'s `wp search-replace`.', 'preflight-wp' )
			);
		}

		return PreFlight_Check_Result::pass();
	}

	/**
	 * Fail when readme.html exists in the WordPress webroot.
	 *
	 * Loopback HTTP requests are forbidden (Brief §5.2), so PreFlight cannot
	 * verify the file is HTTP-accessible — the message notes this limitation.
	 * The file existence check is sufficient as a deployment gate: on a
	 * correctly configured server the file will be publicly reachable if it
	 * exists.
	 *
	 * @since 0.4.0
	 * @return PreFlight_Check_Result
	 */
	public function check_readme_html(): PreFlight_Check_Result {
		if ( file_exists( ABSPATH . 'readme.html' ) ) {
			return PreFlight_Check_Result::fail(
				__( 'readme.html exists in the WordPress root. PreFlight cannot verify HTTP accessibility without loopback (Phase 5); this file exposes the WordPress version to anyone who fetches it.', 'preflight-wp' ),
				__( 'Delete readme.html from the WordPress root, or block public access at the server level (e.g., an Nginx location block or .htaccess deny rule).', 'preflight-wp' )
			);
		}

		return PreFlight_Check_Result::pass();
	}

	/**
	 * Fail when wp-config backup files are found in the webroot.
	 *
	 * @since 0.4.0
	 * @return PreFlight_Check_Result
	 */
	public function check_config_backups(): PreFlight_Check_Result {
		return PreFlight_Check_Result::skip( 'not yet implemented' );
	}

	/**
	 * Fail when XML-RPC is enabled via the xmlrpc_enabled filter.
	 *
	 * @since 0.4.0
	 * @return PreFlight_Check_Result
	 */
	public function check_xmlrpc(): PreFlight_Check_Result {
		return PreFlight_Check_Result::skip( 'not yet implemented' );
	}

	/**
	 * Fail when in-dashboard theme/plugin file editing is not disabled.
	 *
	 * @since 0.4.0
	 * @return PreFlight_Check_Result
	 */
	public function check_file_editing(): PreFlight_Check_Result {
		return PreFlight_Check_Result::skip( 'not yet implemented' );
	}

	/**
	 * Fail when the database table prefix is the WordPress default 'wp_'.
	 *
	 * @since 0.4.0
	 * @return PreFlight_Check_Result
	 */
	public function check_table_prefix(): PreFlight_Check_Result {
		return PreFlight_Check_Result::skip( 'not yet implemented' );
	}
}

add_action(
	'preflight_register_categories',
	static function ( PreFlight_Core $core ): void {
		$core->register_category( new PreFlight_Checks_Security() );
	}
);

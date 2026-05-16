<?php
/**
 * Plugin Hygiene check category — 3 checks per Brief §3.2.
 *
 * @package PreFlight
 * @since   0.8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Check category: Plugin Hygiene.
 *
 * Detects inactive plugins, plugins with pending updates, and active
 * development/debugging plugins. All detection uses internal WordPress APIs —
 * no loopback HTTP requests (Brief §5.2).
 *
 * get_plugin_updates() requires wp-admin/includes/update.php to be loaded
 * explicitly (Brief §5.2 note). This file loads it in check_plugin_updates()
 * rather than at boot, per the lazy-instantiation pattern (Brief §5.5).
 *
 * DEV_PLUGIN_SIGNALS verified against current published plugin source (2026-05-16).
 * See class constant docblock for per-signal source paths and one escalation.
 *
 * @since 0.8.0
 */
class PreFlight_Checks_Plugins implements PreFlight_Check_Category {

	/**
	 * Plugin label → detection signal for known development/debugging plugins.
	 *
	 * Each signal is evaluated with:  class_exists($signal) || defined($signal)
	 *
	 * ESCALATION — Brief §3.2 lists 'QM_Plugin' for Query Monitor. That class
	 * does not exist. The actual detection signal is the QM_VERSION constant.
	 * Source: plugins.svn.wordpress.org/query-monitor/trunk/query-monitor.php
	 *
	 * All other signals verified (2026-05-16):
	 *   Query Monitor  : QM_VERSION — define() in query-monitor.php
	 *                    plugins.svn.wordpress.org/query-monitor/trunk/query-monitor.php
	 *   Debug Bar      : Debug_Bar (class) — no define() in main file
	 *                    plugins.svn.wordpress.org/debug-bar/trunk/debug-bar.php
	 *   WP Crontrol    : Crontrol\WP_CRONTROL_VERSION — namespace constant (const, not define())
	 *                    in namespace Crontrol; defined('Crontrol\\WP_CRONTROL_VERSION') resolves it
	 *                    plugins.svn.wordpress.org/wp-crontrol/trunk/wp-crontrol.php
	 *   Log Deprecated : Deprecated_Log (class) — no define() in main file
	 *                    plugins.svn.wordpress.org/log-deprecated-notices/trunk/log-deprecated-notices.php
	 *   Developer      : Automattic_Developer (class) — no define(); version is a class constant
	 *                    plugins.svn.wordpress.org/developer/trunk/developer.php
	 *
	 * Extension point (v1.1): a filter hook 'preflight_dev_plugin_signals' will
	 * allow additions. Do not implement the filter now.
	 *
	 * @var array<string, string>
	 */
	const DEV_PLUGIN_SIGNALS = array(
		'Query Monitor'        => 'QM_VERSION',
		'Debug Bar'            => 'Debug_Bar',
		'WP Crontrol'          => 'Crontrol\\WP_CRONTROL_VERSION',
		'Log Deprecated Notices' => 'Deprecated_Log',
		'Developer'            => 'Automattic_Developer',
	);

	// -------------------------------------------------------------------------
	// PreFlight_Check_Category interface
	// -------------------------------------------------------------------------

	/**
	 * @return string
	 */
	public function get_category_id(): string {
		return 'plugins';
	}

	/**
	 * @return string
	 */
	public function get_category_label(): string {
		return __( 'Plugin Hygiene', 'preflight-wp' );
	}

	/**
	 * Return the ordered list of check IDs in this category.
	 *
	 * Ordered warning → info (plugin updates is Warning; rest are Info).
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
			array( 'plugins.updates-available', __( 'Plugin updates available', 'preflight-wp' ), PREFLIGHT_SEVERITY_WARNING, 'check_plugin_updates' ),
			array( 'plugins.dev-plugins-active', __( 'Development plugins active', 'preflight-wp' ), PREFLIGHT_SEVERITY_INFO,    'check_dev_plugins' ),
			array( 'plugins.inactive-plugins',   __( 'Inactive plugins present',   'preflight-wp' ), PREFLIGHT_SEVERITY_INFO,    'check_inactive_plugins' ),
		);
	}

	// -------------------------------------------------------------------------
	// Detection methods
	// -------------------------------------------------------------------------

	/**
	 * Fail when any installed plugin has an available update.
	 *
	 * Requires wp-admin/includes/update.php for get_plugin_updates(). Loaded
	 * here rather than at boot — the function is only needed during a scan
	 * and should not be loaded on every admin page.
	 *
	 * Reads from the plugin_updates transient populated by the WordPress
	 * background update check — no external HTTP request (Brief §5.2).
	 * If the transient is absent, the check is skipped rather than failed.
	 *
	 * @since 0.8.0
	 * @return PreFlight_Check_Result
	 */
	public function check_plugin_updates(): PreFlight_Check_Result {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/update.php';

		$updates = get_plugin_updates();

		if ( false === $updates ) {
			return PreFlight_Check_Result::skip(
				__( 'Plugin update data is not available — the background update check has not run yet.', 'preflight-wp' )
			);
		}

		if ( empty( $updates ) ) {
			return PreFlight_Check_Result::pass();
		}

		$names = array();
		foreach ( $updates as $plugin_data ) {
			if ( isset( $plugin_data->Name ) ) {
				$names[] = $plugin_data->Name;
			}
		}

		return PreFlight_Check_Result::fail(
			sprintf(
				/* translators: %s: comma-separated list of plugin names with available updates */
				__( '%d plugin(s) have updates available: %s.', 'preflight-wp' ),
				count( $updates ),
				implode( ', ', $names )
			),
			__( 'Update plugins via Dashboard → Updates before launch. Test the site after updating — especially WooCommerce, page builders, and security plugins.', 'preflight-wp' )
		);
	}

	/**
	 * Fail when a recognised development or debugging plugin is active.
	 *
	 * Query Monitor is widely used in production by experienced developers.
	 * Info severity (not Warning) reflects its legitimate production use case
	 * as noted in Brief §3.2.
	 *
	 * @since 0.8.0
	 * @return PreFlight_Check_Result
	 */
	public function check_dev_plugins(): PreFlight_Check_Result {
		$found = array();

		foreach ( self::DEV_PLUGIN_SIGNALS as $label => $signal ) {
			if ( class_exists( $signal ) || defined( $signal ) ) {
				$found[] = $label;
			}
		}

		if ( ! empty( $found ) ) {
			return PreFlight_Check_Result::fail(
				sprintf(
					/* translators: %s: comma-separated list of active development plugin names */
					__( 'Development or debugging plugin(s) are active: %s.', 'preflight-wp' ),
					implode( ', ', $found )
				),
				__( 'Deactivate development plugins before launch. Query Monitor and Debug Bar may be left active by experienced developers who monitor production sites, but should be removed for client handoffs.', 'preflight-wp' )
			);
		}

		return PreFlight_Check_Result::pass();
	}

	/**
	 * Fail when inactive plugins are present on the site.
	 *
	 * Uses get_plugins() (loads all installed plugin headers) minus the active
	 * plugin list. Info severity reflects that inactive plugins are common for
	 * legitimate reasons (rotation, seasonal, backup, dependency plugins).
	 *
	 * get_plugins() requires wp-admin/includes/plugin.php.
	 *
	 * @since 0.8.0
	 * @return PreFlight_Check_Result
	 */
	public function check_inactive_plugins(): PreFlight_Check_Result {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		$all_plugins    = get_plugins();
		$active_plugins = (array) get_option( 'active_plugins', array() );

		$inactive = array_diff_key( $all_plugins, array_flip( $active_plugins ) );

		if ( empty( $inactive ) ) {
			return PreFlight_Check_Result::pass();
		}

		$names = array_column( array_values( $inactive ), 'Name' );
		sort( $names );

		return PreFlight_Check_Result::fail(
			sprintf(
				/* translators: 1: count, 2: comma-separated plugin names */
				__( '%1$d inactive plugin(s) are installed: %2$s.', 'preflight-wp' ),
				count( $inactive ),
				implode( ', ', $names )
			),
			__( 'Delete plugins that are not in use. Inactive plugins still represent attack surface if they contain vulnerabilities, and they slow down plugin update scanning.', 'preflight-wp' )
		);
	}
}

add_action(
	'preflight_register_categories',
	static function ( PreFlight_Core $core ): void {
		$core->register_category( new PreFlight_Checks_Plugins() );
	}
);

<?php
/**
 * Scan orchestrator: iterates registered categories, runs checks, returns result envelope.
 *
 * @package PreFlight
 * @since   0.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PreFlight_Scanner {

	/**
	 * Signals that a scan is currently in progress.
	 *
	 * Set to true at scan start, false on clean exit. Used by the fatal
	 * shutdown handler to distinguish a scan-induced fatal from any other
	 * fatal that might occur during an unrelated page load.
	 *
	 * @var bool
	 */
	private static $scan_in_progress = false;

	/**
	 * Results collected so far in the current scan.
	 *
	 * Kept in sync row-by-row so the fatal handler can persist whatever
	 * completed before the fatal occurred.
	 *
	 * @var array
	 */
	private static $partial_results = array();

	/**
	 * Core instance providing category registry and settings access.
	 *
	 * @var PreFlight_Core
	 */
	private $core;

	/**
	 * @param PreFlight_Core $core Injected — scanner never calls instance() internally.
	 */
	public function __construct( PreFlight_Core $core ) {
		$this->core = $core;
	}

	// -------------------------------------------------------------------------
	// Public API
	// -------------------------------------------------------------------------

	/**
	 * Execute a full scan across all registered categories.
	 *
	 * Iterates categories → checks, skips disabled check IDs, collects result
	 * rows, and returns the envelope defined by Brief §5.3.
	 *
	 * Failure contract (§5.2) is applied in subsequent commits:
	 * - try/catch per check (commit 3)
	 * - shutdown fatal handler (commit 4)
	 * - memory/time limit guards (commit 5)
	 *
	 * @since  0.2.0
	 * @return array Scan envelope {timestamp, wp_version, php_version, site_url, summary, results}.
	 */
	public function run(): array {
		$scan_start = time();

		$this->raise_resource_limits();

		self::$scan_in_progress = true;
		self::$partial_results  = array();

		$this->register_fatal_handler( $scan_start );

		$results = array();

		foreach ( $this->core->get_categories() as $category ) {
			$category_id = $category->get_category_id();

			foreach ( $category->get_check_ids() as $check_id ) {
				if ( $this->is_disabled( $check_id ) ) {
					continue;
				}

				$check = $category->get_check( $check_id );
				if ( null === $check ) {
					continue;
				}

				$row                   = $this->run_single_check( $check, $category_id );
				$results[]             = $row;
				self::$partial_results = $results;
			}
		}

		$this->clear_scan_flag();
		self::$partial_results = array(); // Free memory; clean scan needs no partial state.

		return array(
			'timestamp'   => gmdate( 'c' ),
			'wp_version'  => get_bloginfo( 'version' ),
			'php_version' => phpversion(),
			'site_url'    => site_url(),
			'summary'     => $this->compute_summary( $results ),
			'results'     => $results,
		);
	}

	// -------------------------------------------------------------------------
	// Protected — overridable by subclasses / tests
	// -------------------------------------------------------------------------

	/**
	 * Execute a single check and return a result row.
	 *
	 * Any \Throwable thrown by check->run() is caught and converted to a skip
	 * result containing the exception class name and message. This guarantees
	 * that a misbehaving check never crashes the scan (§5.2).
	 *
	 * @since  0.2.0
	 * @param  PreFlight_Check $check
	 * @param  string          $category_id
	 * @return array {id, category, label, severity, status, message, fix_hint}
	 */
	protected function run_single_check( PreFlight_Check $check, string $category_id ): array {
		try {
			$result = $check->run();
		} catch ( \Throwable $e ) {
			$result = PreFlight_Check_Result::skip(
				get_class( $e ) . ': ' . $e->getMessage()
			);
		}

		$severity = $this->apply_severity( $check->get_id(), $check->get_default_severity() );

		return array(
			'id'       => $check->get_id(),
			'category' => $category_id,
			'label'    => $check->get_label(),
			'severity' => $severity,
			'status'   => $result->status,
			'message'  => $result->message,
			'fix_hint' => $result->fix_hint,
		);
	}

	/**
	 * Resolve effective severity for a check.
	 *
	 * Currently returns the check's declared default. In v1.1, when severity
	 * overrides ship (Brief §3.6), this method reads from preflight_settings —
	 * the upgrade is a one-line change here, no other method changes.
	 *
	 * @since  0.2.0
	 * @param  string $check_id        Namespaced check ID (unused until v1.1).
	 * @param  string $default_severity One of PREFLIGHT_SEVERITY_*.
	 * @return string
	 */
	protected function apply_severity( string $check_id, string $default_severity ): string {
		return $default_severity;
	}

	/**
	 * Return true if the given check ID appears in the disabled_checks setting.
	 *
	 * @since  0.2.0
	 * @param  string $check_id
	 * @return bool
	 */
	protected function is_disabled( string $check_id ): bool {
		$disabled = $this->core->get_setting( 'disabled_checks', array() );
		return in_array( $check_id, (array) $disabled, true );
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Register a shutdown function that persists partial results on fatal error.
	 *
	 * The transient key encodes the scan start timestamp to avoid collisions if
	 * two scans somehow overlap (e.g., two admin users triggering a scan at the
	 * same second — unlikely but possible).
	 *
	 * Manual verification path for the fatal handler:
	 *   Register a stub check whose get_check() (not run()) causes a fatal — e.g.
	 *   a category class that calls an undefined static method on itself inside
	 *   get_check(). Because get_check() is called *outside* the run_single_check
	 *   try/catch, a fatal there bypasses the per-check guard and reaches this
	 *   handler. After the fatal, get_transient('preflight_partial_scan_<ts>')
	 *   should return the results collected before the bad check was reached.
	 *   Note: \Throwable in run() *is* caught per-check, so only code between
	 *   the try/catch blocks (i.e. category->get_check()) triggers this path.
	 *
	 * @since  0.2.0
	 * @param  int $scan_start_ts Unix timestamp captured at scan start.
	 */
	private function register_fatal_handler( int $scan_start_ts ): void {
		register_shutdown_function(
			function () use ( $scan_start_ts ) {
				$this->handle_fatal( $scan_start_ts );
			}
		);
	}

	/**
	 * Shutdown callback: write partial results transient if a fatal ended the scan.
	 *
	 * Acts only when both conditions are true:
	 *   1. self::$scan_in_progress === true (scan did not exit cleanly).
	 *   2. error_get_last() reports a fatal-class error type.
	 *
	 * Fatal types checked: E_ERROR | E_PARSE | E_COMPILE_ERROR | E_USER_ERROR |
	 * E_CORE_ERROR. Does not call exit() — WordPress may have additional shutdown
	 * handlers that must still run.
	 *
	 * Transient TTL: HOUR_IN_SECONDS (1 hour). The admin UI checks for this
	 * transient on page load and surfaces a partial-results notice (§5.2).
	 *
	 * @since  0.2.0
	 * @param  int $scan_start_ts
	 */
	private function handle_fatal( int $scan_start_ts ): void {
		if ( ! self::$scan_in_progress ) {
			return;
		}

		$error = error_get_last();
		if ( null === $error ) {
			return;
		}

		$fatal_mask = E_ERROR | E_PARSE | E_COMPILE_ERROR | E_USER_ERROR | E_CORE_ERROR;
		if ( ! ( $error['type'] & $fatal_mask ) ) {
			return;
		}

		set_transient(
			'preflight_partial_scan_' . $scan_start_ts,
			array(
				'partial' => true,
				'fatal'   => $error,
				'results' => self::$partial_results,
				'summary' => $this->compute_summary( self::$partial_results ),
			),
			HOUR_IN_SECONDS
		);
	}

	/**
	 * Count result rows into the summary buckets defined by Brief §5.3.
	 *
	 * Fail rows are sorted into severity buckets (blockers, warnings, info).
	 * Pass rows increment 'passed'. Skip rows increment 'skipped'.
	 *
	 * @since  0.2.0
	 * @param  array $results
	 * @return array {blockers: int, warnings: int, info: int, passed: int, skipped: int}
	 */
	private function compute_summary( array $results ): array {
		$summary = array(
			'blockers' => 0,
			'warnings' => 0,
			'info'     => 0,
			'passed'   => 0,
			'skipped'  => 0,
		);

		foreach ( $results as $row ) {
			if ( PREFLIGHT_STATUS_PASS === $row['status'] ) {
				$summary['passed']++;
			} elseif ( PREFLIGHT_STATUS_SKIP === $row['status'] ) {
				$summary['skipped']++;
			} elseif ( PREFLIGHT_STATUS_FAIL === $row['status'] ) {
				switch ( $row['severity'] ) {
					case PREFLIGHT_SEVERITY_BLOCKER:
						$summary['blockers']++;
						break;
					case PREFLIGHT_SEVERITY_WARNING:
						$summary['warnings']++;
						break;
					case PREFLIGHT_SEVERITY_INFO:
						$summary['info']++;
						break;
				}
			}
		}

		return $summary;
	}

	/**
	 * Raise PHP memory and execution time limits before the scan begins.
	 *
	 * wp_raise_memory_limit('admin') bumps the limit to WP_MAX_MEMORY_LIMIT
	 * (default 256 MB) for admin contexts. set_time_limit(60) gives the scan
	 * a 60-second ceiling; @ suppresses the warning on hosts where the function
	 * is callable but the time-limit ini setting is locked.
	 *
	 * Both calls are guarded by function_exists() in case they are listed in
	 * PHP's disable_functions (§5.2).
	 *
	 * @since 0.2.0
	 */
	private function raise_resource_limits(): void {
		if ( function_exists( 'wp_raise_memory_limit' ) ) {
			wp_raise_memory_limit( 'admin' );
		}
		if ( function_exists( 'set_time_limit' ) ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			@set_time_limit( 60 );
		}
	}

	/**
	 * Mark the scan as complete on clean exit.
	 *
	 * Prevents the fatal handler from firing for unrelated page-load fatals
	 * that happen after the scan has already finished.
	 *
	 * @since 0.2.0
	 */
	private function clear_scan_flag(): void {
		self::$scan_in_progress = false;
	}
}

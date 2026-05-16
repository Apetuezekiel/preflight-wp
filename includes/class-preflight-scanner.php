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

		self::$scan_in_progress = true;
		self::$partial_results  = array();

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
	 * try/catch wrapping is added in the next commit. This baseline calls
	 * check->run() directly so the envelope shape is testable independently.
	 *
	 * @since  0.2.0
	 * @param  PreFlight_Check $check
	 * @param  string          $category_id
	 * @return array {id, category, label, severity, status, message, fix_hint}
	 */
	protected function run_single_check( PreFlight_Check $check, string $category_id ): array {
		$result   = $check->run();
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

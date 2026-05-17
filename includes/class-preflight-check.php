<?php
/**
 * Concrete check implementation backed by an injected callable runner.
 *
 * Every check category uses this class rather than implementing PreFlight_Check
 * directly on a per-check class. This keeps check definitions compact: the
 * category class holds the detection logic as private methods and wires each
 * one up via [$this, 'method_name'] in the spec map.
 *
 * @package PreFlight
 * @since   0.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A PreFlight_Check whose detection logic is supplied at construction time.
 *
 * @since 0.2.0
 */
class PreFlight_Configurable_Check implements PreFlight_Check {

	/** @var string Namespaced check ID: '{category-id}.{check-slug}'. */
	private $id;

	/** @var string Human-readable label. */
	private $label;

	/** @var string One of PREFLIGHT_SEVERITY_*. */
	private $severity;

	/** @var callable Invoked by run(). Must return PreFlight_Check_Result. */
	private $runner;

	/**
	 * @param string   $id       Namespaced check ID.
	 * @param string   $label    Human-readable label shown in results dashboard.
	 * @param string   $severity One of PREFLIGHT_SEVERITY_BLOCKER, _WARNING, _INFO.
	 * @param callable $runner   Returns PreFlight_Check_Result; receives no arguments.
	 */
	public function __construct( string $id, string $label, string $severity, callable $runner ) {
		$this->id       = $id;
		$this->label    = $label;
		$this->severity = $severity;
		$this->runner   = $runner;
	}

	/** @return string */
	public function get_id(): string {
		return $this->id;
	}

	/** @return string */
	public function get_label(): string {
		return $this->label;
	}

	/** @return string */
	public function get_default_severity(): string {
		return $this->severity;
	}

	/**
	 * Invoke the runner and return its result.
	 *
	 * If the runner returns something that is not a PreFlight_Check_Result,
	 * a TypeError is thrown so the scanner's try/catch wraps it into a skip
	 * result rather than silently passing bad data through the envelope.
	 *
	 * @since  0.2.0
	 * @return PreFlight_Check_Result
	 * @throws \TypeError When the runner does not return PreFlight_Check_Result.
	 */
	public function run(): PreFlight_Check_Result {
		$result = ( $this->runner )();
		if ( ! ( $result instanceof PreFlight_Check_Result ) ) {
			// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- args are internal check ID and PHP type name, not user input.
			throw new \TypeError(
				sprintf(
					'Check runner for "%s" must return PreFlight_Check_Result, got %s.',
					$this->id,
					gettype( $result )
				)
			);
			// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
		return $result;
	}
}

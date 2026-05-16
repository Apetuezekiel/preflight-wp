<?php
/**
 * Severity/status constants, result value object, and check contracts.
 *
 * @package PreFlight
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Severity constants (§3.1).
define( 'PREFLIGHT_SEVERITY_BLOCKER', 'blocker' );
define( 'PREFLIGHT_SEVERITY_WARNING', 'warning' );
define( 'PREFLIGHT_SEVERITY_INFO',    'info' );

// Status constants (§3.3).
define( 'PREFLIGHT_STATUS_PASS', 'pass' );
define( 'PREFLIGHT_STATUS_FAIL', 'fail' );
define( 'PREFLIGHT_STATUS_SKIP', 'skip' );

/**
 * Immutable result value object returned by every PreFlight_Check::run() call.
 *
 * Properties are public for array serialisation into scan history but must not
 * be mutated after construction. Use the static factory methods to create instances.
 * PHP 7.4 does not support readonly properties (added in 8.1); immutability is
 * enforced via the private constructor.
 */
final class PreFlight_Check_Result {

	/** @var string pass|fail|skip */
	public $status;

	/** @var string What was detected; empty string on pass. */
	public $message;

	/** @var string Actionable fix instruction; empty string on pass or skip. */
	public $fix_hint;

	private function __construct( string $status, string $message, string $fix_hint ) {
		$this->status   = $status;
		$this->message  = $message;
		$this->fix_hint = $fix_hint;
	}

	public static function pass(): self {
		return new self( PREFLIGHT_STATUS_PASS, '', '' );
	}

	public static function fail( string $message, string $fix_hint ): self {
		return new self( PREFLIGHT_STATUS_FAIL, $message, $fix_hint );
	}

	public static function skip( string $message ): self {
		return new self( PREFLIGHT_STATUS_SKIP, $message, '' );
	}
}

/**
 * Contract for a single executable check.
 *
 * Check IDs must follow the namespacing rule defined in §5.3:
 *   '{category-id}.{check-slug}'  e.g. 'wp-config.default-tagline'
 *
 * Category prefixes are stable. Recategorising a check requires migrating its ID
 * so that scan history comparison (§3.5) remains valid across the rename.
 */
interface PreFlight_Check {

	/**
	 * Namespaced check identifier: '{category-id}.{check-slug}'.
	 *
	 * @return string
	 */
	public function get_id(): string;

	public function get_label(): string;

	/**
	 * One of PREFLIGHT_SEVERITY_BLOCKER, PREFLIGHT_SEVERITY_WARNING, or PREFLIGHT_SEVERITY_INFO.
	 *
	 * @return string
	 */
	public function get_default_severity(): string;

	/**
	 * Execute the check and return a result.
	 *
	 * Must never throw — all exceptions are caught by the scanner (§5.2).
	 *
	 * @return PreFlight_Check_Result
	 */
	public function run(): PreFlight_Check_Result;
}

/**
 * Contract for a group of related checks.
 *
 * Lazy instantiation pattern (§5.5): get_check_ids() returns string IDs without
 * constructing check objects. get_check() instantiates on demand — only during
 * an active scan, never during admin page rendering.
 */
interface PreFlight_Check_Category {

	/** @return string e.g. 'wp-config' */
	public function get_category_id(): string;

	public function get_category_label(): string;

	/**
	 * Return all namespaced check IDs this category owns.
	 *
	 * Must not instantiate PreFlight_Check objects.
	 *
	 * @return string[]
	 */
	public function get_check_ids(): array;

	/**
	 * Instantiate and return the check for the given ID, or null if unknown.
	 *
	 * @param string $id Namespaced check ID.
	 * @return PreFlight_Check|null
	 */
	public function get_check( string $id ): ?PreFlight_Check;
}

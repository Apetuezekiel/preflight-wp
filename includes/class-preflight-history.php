<?php
/**
 * Scan history persistence and delta computation service.
 *
 * @package PreFlight
 * @since   0.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pure data service for reading and writing the preflight_scan_history option.
 *
 * No UI concerns. No WordPress hook registration. Stateless — every method
 * reads from or writes to the database directly and returns a value.
 *
 * @since 0.3.0
 */
class PreFlight_History {

	/** Maximum number of scan envelopes retained in history (Brief §3.5). */
	const MAX_HISTORY = 10;

	/**
	 * Prepend a scan envelope to history and prune to MAX_HISTORY entries.
	 *
	 * @since  0.3.0
	 * @param  array $envelope Scan envelope returned by PreFlight_Scanner::run().
	 * @return void
	 */
	public function persist( array $envelope ): void {
		$history = $this->get_all();
		array_unshift( $history, $envelope );
		$history = array_slice( $history, 0, self::MAX_HISTORY );
		update_option( 'preflight_scan_history', $history, false );
	}

	/**
	 * Return the full history array (most recent first).
	 *
	 * @since  0.3.0
	 * @return array
	 */
	public function get_all(): array {
		return (array) get_option( 'preflight_scan_history', array() );
	}

	/**
	 * Return the most recent scan envelope, or null if no history exists.
	 *
	 * @since  0.3.0
	 * @return array|null
	 */
	public function get_latest(): ?array {
		$history = $this->get_all();
		return ! empty( $history ) ? $history[0] : null;
	}

	/**
	 * Return the second-most-recent envelope (the delta baseline), or null.
	 *
	 * @since  0.3.0
	 * @return array|null
	 */
	public function get_previous(): ?array {
		$history = $this->get_all();
		return isset( $history[1] ) ? $history[1] : null;
	}

	/**
	 * Compute a delta between the current and a previous scan.
	 *
	 * Checks are matched by their namespaced ID (Brief §5.3). Results are
	 * classified as new (fail now, not fail before), resolved (not fail now,
	 * fail before), or unchanged (fail in both scans).
	 *
	 * When $previous is null — i.e. this is the first-ever scan — all failing
	 * checks in $current are classified as 'new' with no 'resolved' or
	 * 'unchanged' entries.
	 *
	 * @since  0.3.0
	 * @param  array      $current  Current scan envelope.
	 * @param  array|null $previous Previous scan envelope, or null.
	 * @return array{new: array, resolved: array, unchanged: array}
	 */
	public function compute_delta( array $current, ?array $previous ): array {
		$delta = array(
			'new'       => array(),
			'resolved'  => array(),
			'unchanged' => array(),
		);

		$current_results = $current['results'] ?? array();

		if ( null === $previous ) {
			foreach ( $current_results as $row ) {
				if ( PREFLIGHT_STATUS_FAIL === $row['status'] ) {
					$delta['new'][] = $row;
				}
			}
			return $delta;
		}

		$prev_by_id = array();
		foreach ( ( $previous['results'] ?? array() ) as $row ) {
			$prev_by_id[ $row['id'] ] = $row;
		}

		$current_by_id = array();
		foreach ( $current_results as $row ) {
			$current_by_id[ $row['id'] ] = $row;
		}

		// New: failing now, not failing before.
		foreach ( $current_results as $row ) {
			if ( PREFLIGHT_STATUS_FAIL !== $row['status'] ) {
				continue;
			}
			$prev = $prev_by_id[ $row['id'] ] ?? null;
			if ( null === $prev || PREFLIGHT_STATUS_FAIL !== $prev['status'] ) {
				$delta['new'][] = $row;
			} else {
				$delta['unchanged'][] = $row;
			}
		}

		// Resolved: was failing before, not failing now.
		foreach ( $prev_by_id as $id => $prev_row ) {
			if ( PREFLIGHT_STATUS_FAIL !== $prev_row['status'] ) {
				continue;
			}
			$curr = $current_by_id[ $id ] ?? null;
			if ( null === $curr || PREFLIGHT_STATUS_FAIL !== $curr['status'] ) {
				$delta['resolved'][] = $prev_row;
			}
		}

		return $delta;
	}

	/**
	 * Delete the entire scan history.
	 *
	 * Called by uninstall.php (option deletion) and available for a future
	 * "Clear History" admin button.
	 *
	 * @since  0.3.0
	 * @return void
	 */
	public function clear(): void {
		delete_option( 'preflight_scan_history' );
	}
}

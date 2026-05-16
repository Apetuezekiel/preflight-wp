<?php
/**
 * Dashboard tab template.
 *
 * Receives in scope:
 *   $envelope        (array|null) — latest scan envelope, or null if no scan run yet.
 *   $delta           (array)      — {new:[], resolved:[], unchanged:[]}.
 *   $is_first_scan   (bool)       — true when there is no previous scan to compare.
 *   $scan_action_url (string)     — form action URL.
 *   $rescan_nonce    (string)     — nonce value for the scan trigger form.
 *
 * @package PreFlight
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div id="preflight-dashboard">

<?php if ( null === $envelope ) : ?>

	<?php /* Empty state — no scan has been run yet. */ ?>
	<div class="preflight-empty-state">
		<p class="preflight-empty-state__lead">
			<?php esc_html_e( 'No scan results yet. Click "Run Scan" to check this site for pre-launch issues.', 'preflight-wp' ); ?>
		</p>
		<form method="post" action="<?php echo esc_url( $scan_action_url ); ?>">
			<?php wp_nonce_field( 'preflight_run_scan', 'preflight_scan_nonce' ); ?>
			<button type="submit" class="button button-primary button-hero">
				<?php esc_html_e( 'Run Scan', 'preflight-wp' ); ?>
			</button>
		</form>
	</div>

<?php else : ?>

	<?php
	$summary   = $envelope['summary'];
	$results   = $envelope['results'];
	$timestamp = $envelope['timestamp'];
	?>

	<?php /* Summary bar */ ?>
	<div class="preflight-summary" role="region" aria-label="<?php esc_attr_e( 'Scan summary', 'preflight-wp' ); ?>">
		<div class="preflight-summary__meta">
			<?php
			echo esc_html(
				sprintf(
					/* translators: %s: scan timestamp */
					__( 'Last scanned: %s', 'preflight-wp' ),
					wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $timestamp ) )
				)
			);
			?>
		</div>
		<ul class="preflight-summary__counts" aria-label="<?php esc_attr_e( 'Issue counts by severity', 'preflight-wp' ); ?>">
			<li class="preflight-summary__count preflight-summary__count--blocker">
				<span class="preflight-severity-pill preflight-severity-pill--blocker" aria-hidden="true"><?php esc_html_e( 'BLOCKER', 'preflight-wp' ); ?></span>
				<span class="preflight-count-value"><?php echo absint( $summary['blockers'] ); ?></span>
			</li>
			<li class="preflight-summary__count preflight-summary__count--warning">
				<span class="preflight-severity-pill preflight-severity-pill--warning" aria-hidden="true"><?php esc_html_e( 'WARNING', 'preflight-wp' ); ?></span>
				<span class="preflight-count-value"><?php echo absint( $summary['warnings'] ); ?></span>
			</li>
			<li class="preflight-summary__count preflight-summary__count--info">
				<span class="preflight-severity-pill preflight-severity-pill--info" aria-hidden="true"><?php esc_html_e( 'INFO', 'preflight-wp' ); ?></span>
				<span class="preflight-count-value"><?php echo absint( $summary['info'] ); ?></span>
			</li>
			<li class="preflight-summary__count preflight-summary__count--pass">
				<span class="preflight-severity-pill preflight-severity-pill--pass" aria-hidden="true"><?php esc_html_e( 'PASS', 'preflight-wp' ); ?></span>
				<span class="preflight-count-value"><?php echo absint( $summary['passed'] ); ?></span>
			</li>
		</ul>
	</div>

	<?php /* Delta section — shown after first scan */ ?>
	<?php if ( ! $is_first_scan ) : ?>
		<?php
		$new_count      = count( $delta['new'] );
		$resolved_count = count( $delta['resolved'] );
		?>
		<div class="preflight-delta" role="region" aria-label="<?php esc_attr_e( 'Changes since last scan', 'preflight-wp' ); ?>">
			<?php if ( $new_count > 0 ) : ?>
				<span class="preflight-delta__new">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %d: number of new issues */
							_n( '%d new issue', '%d new issues', $new_count, 'preflight-wp' ),
							$new_count
						)
					);
					?>
				</span>
			<?php endif; ?>
			<?php if ( $resolved_count > 0 ) : ?>
				<span class="preflight-delta__resolved">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %d: number of resolved issues */
							_n( '%d resolved', '%d resolved', $resolved_count, 'preflight-wp' ),
							$resolved_count
						)
					);
					?>
				</span>
			<?php endif; ?>
			<?php if ( 0 === $new_count && 0 === $resolved_count ) : ?>
				<span class="preflight-delta__unchanged"><?php esc_html_e( 'No changes since last scan.', 'preflight-wp' ); ?></span>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php /* Re-scan button */ ?>
	<div class="preflight-actions">
		<form method="post" action="<?php echo esc_url( $scan_action_url ); ?>" id="preflight-rescan-form">
			<?php wp_nonce_field( 'preflight_run_scan', 'preflight_scan_nonce' ); ?>
			<button type="submit" id="preflight-rescan-btn" class="button button-primary">
				<?php esc_html_e( 'Re-scan', 'preflight-wp' ); ?>
			</button>
		</form>
	</div>

	<?php /* Results grouped by category */ ?>
	<?php
	// Group results by category.
	$by_category = array();
	foreach ( $results as $row ) {
		$by_category[ $row['category'] ][] = $row;
	}

	// Category label map (displayed label per category-id).
	$category_labels = array();
	foreach ( $this->core->get_categories() as $cat ) {
		$category_labels[ $cat->get_category_id() ] = $cat->get_category_label();
	}

	// Severity sort order.
	$severity_order = array(
		PREFLIGHT_SEVERITY_BLOCKER => 0,
		PREFLIGHT_SEVERITY_WARNING => 1,
		PREFLIGHT_SEVERITY_INFO    => 2,
	);

	foreach ( $by_category as $cat_id => $cat_results ) :
		// Count failures in this category.
		$fail_count = 0;
		foreach ( $cat_results as $row ) {
			if ( PREFLIGHT_STATUS_FAIL === $row['status'] ) {
				$fail_count++;
			}
		}

		// Sort by severity then status.
		usort( $cat_results, function( $a, $b ) use ( $severity_order ) {
			$ord_a = $severity_order[ $a['severity'] ] ?? 9;
			$ord_b = $severity_order[ $b['severity'] ] ?? 9;
			if ( $ord_a !== $ord_b ) {
				return $ord_a - $ord_b;
			}
			// Fail before pass/skip within same severity.
			$status_order = array( PREFLIGHT_STATUS_FAIL => 0, PREFLIGHT_STATUS_PASS => 1, PREFLIGHT_STATUS_SKIP => 2 );
			return ( $status_order[ $a['status'] ] ?? 9 ) - ( $status_order[ $b['status'] ] ?? 9 );
		} );

		$section_id = 'preflight-cat-' . esc_attr( $cat_id );
		$label      = isset( $category_labels[ $cat_id ] ) ? $category_labels[ $cat_id ] : $cat_id;
	?>
		<section class="preflight-category is-expanded" id="<?php echo esc_attr( $section_id . '-section' ); ?>">
			<h2
				class="preflight-category__header"
				id="<?php echo esc_attr( $section_id . '-header' ); ?>"
				tabindex="0"
				role="button"
				aria-expanded="true"
				aria-controls="<?php echo esc_attr( $section_id . '-body' ); ?>"
			>
				<span class="preflight-category__chevron" aria-hidden="true"></span>
				<span class="preflight-category__label"><?php echo esc_html( $label ); ?></span>
				<?php if ( $fail_count > 0 ) : ?>
					<span class="preflight-category__badge" aria-label="<?php echo esc_attr( sprintf( _n( '%d issue', '%d issues', $fail_count, 'preflight-wp' ), $fail_count ) ); ?>">
						<?php echo absint( $fail_count ); ?>
					</span>
				<?php endif; ?>
			</h2>
			<div
				class="preflight-category__body"
				id="<?php echo esc_attr( $section_id . '-body' ); ?>"
				role="region"
				aria-labelledby="<?php echo esc_attr( $section_id . '-header' ); ?>"
			>
				<table class="wp-list-table widefat fixed striped preflight-results-table">
					<thead>
						<tr>
							<th scope="col" class="preflight-col-severity"><?php esc_html_e( 'Severity', 'preflight-wp' ); ?></th>
							<th scope="col" class="preflight-col-check"><?php esc_html_e( 'Check', 'preflight-wp' ); ?></th>
							<th scope="col" class="preflight-col-result"><?php esc_html_e( 'Result', 'preflight-wp' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $cat_results as $row ) : ?>
						<tr class="preflight-result preflight-result--<?php echo esc_attr( $row['status'] ); ?>">
							<td class="preflight-col-severity">
								<?php if ( PREFLIGHT_STATUS_FAIL === $row['status'] ) : ?>
									<span class="preflight-severity-pill preflight-severity-pill--<?php echo esc_attr( $row['severity'] ); ?>">
										<?php echo esc_html( strtoupper( $row['severity'] ) ); ?>
									</span>
								<?php elseif ( PREFLIGHT_STATUS_PASS === $row['status'] ) : ?>
									<span class="preflight-severity-pill preflight-severity-pill--pass"><?php esc_html_e( 'PASS', 'preflight-wp' ); ?></span>
								<?php else : ?>
									<span class="preflight-severity-pill preflight-severity-pill--skip"><?php esc_html_e( 'SKIP', 'preflight-wp' ); ?></span>
								<?php endif; ?>
							</td>
							<td class="preflight-col-check">
								<?php echo esc_html( $row['label'] ); ?>
							</td>
							<td class="preflight-col-result">
								<?php if ( PREFLIGHT_STATUS_FAIL === $row['status'] ) : ?>
									<strong><?php echo esc_html( $row['message'] ); ?></strong>
									<?php if ( ! empty( $row['fix_hint'] ) ) : ?>
										<p class="preflight-fix-hint"><?php echo esc_html( $row['fix_hint'] ); ?></p>
									<?php endif; ?>
								<?php elseif ( PREFLIGHT_STATUS_SKIP === $row['status'] ) : ?>
									<span class="preflight-skip-message"><?php echo esc_html( $row['message'] ); ?></span>
								<?php else : ?>
									<span class="preflight-pass-message"><?php esc_html_e( 'Passed', 'preflight-wp' ); ?></span>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</section>
	<?php endforeach; ?>

<?php endif; ?>
</div>

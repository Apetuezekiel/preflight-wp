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
 *   $is_historical   (bool)       — true when displaying a historical scan (not latest).
 *
 * @package Prelaunch_Checker
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- template-scoped variables injected by render_dashboard_partial().
$is_historical = isset( $is_historical ) ? (bool) $is_historical : false;
?>
<div id="prelaunch-checker-dashboard">

<?php if ( null === $envelope ) : ?>

	<?php /* Empty state — no scan has been run yet. */ ?>
	<div class="prelaunch-checker-empty-state">
		<p class="prelaunch-checker-empty-state__lead">
			<?php esc_html_e( 'No scan results yet. Click "Run Scan" to check this site for pre-launch issues.', 'prelaunch-checker' ); ?>
		</p>
		<form method="post" action="<?php echo esc_url( $scan_action_url ); ?>">
			<?php wp_nonce_field( 'prelaunch_checker_run_scan', 'prelaunch_checker_scan_nonce' ); ?>
			<button type="submit" class="button button-primary button-hero">
				<?php esc_html_e( 'Run Scan', 'prelaunch-checker' ); ?>
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
	<div class="prelaunch-checker-summary" role="region" aria-label="<?php esc_attr_e( 'Scan summary', 'prelaunch-checker' ); ?>">
		<div class="prelaunch-checker-summary__meta">
			<?php
			echo esc_html(
				sprintf(
					/* translators: %s: scan timestamp */
					__( 'Last scanned: %s', 'prelaunch-checker' ),
					wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $timestamp ) )
				)
			);
			?>
		</div>
		<ul class="prelaunch-checker-summary__counts" aria-label="<?php esc_attr_e( 'Issue counts by severity', 'prelaunch-checker' ); ?>">
			<li class="prelaunch-checker-summary__count prelaunch-checker-summary__count--blocker">
				<span class="prelaunch-checker-severity-pill prelaunch-checker-severity-pill--blocker" aria-hidden="true"><?php esc_html_e( 'BLOCKER', 'prelaunch-checker' ); ?></span>
				<span class="prelaunch-checker-count-value"><?php echo absint( $summary['blockers'] ); ?></span>
			</li>
			<li class="prelaunch-checker-summary__count prelaunch-checker-summary__count--warning">
				<span class="prelaunch-checker-severity-pill prelaunch-checker-severity-pill--warning" aria-hidden="true"><?php esc_html_e( 'WARNING', 'prelaunch-checker' ); ?></span>
				<span class="prelaunch-checker-count-value"><?php echo absint( $summary['warnings'] ); ?></span>
			</li>
			<li class="prelaunch-checker-summary__count prelaunch-checker-summary__count--info">
				<span class="prelaunch-checker-severity-pill prelaunch-checker-severity-pill--info" aria-hidden="true"><?php esc_html_e( 'INFO', 'prelaunch-checker' ); ?></span>
				<span class="prelaunch-checker-count-value"><?php echo absint( $summary['info'] ); ?></span>
			</li>
			<li class="prelaunch-checker-summary__count prelaunch-checker-summary__count--pass">
				<span class="prelaunch-checker-severity-pill prelaunch-checker-severity-pill--pass" aria-hidden="true"><?php esc_html_e( 'PASS', 'prelaunch-checker' ); ?></span>
				<span class="prelaunch-checker-count-value"><?php echo absint( $summary['passed'] ); ?></span>
			</li>
		</ul>
	</div>

	<?php /* Historical scan banner */ ?>
	<?php if ( $is_historical ) : ?>
		<div class="notice notice-info prelaunch-checker-historical-notice">
			<p>
				<?php
				echo esc_html(
					sprintf(
						/* translators: %s: scan timestamp */
						__( 'Viewing historical scan from %s.', 'prelaunch-checker' ),
						wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $timestamp ) )
					)
				);
				?>
				<a href="<?php echo esc_url( admin_url( 'tools.php?page=prelaunch-checker&tab=dashboard' ) ); ?>">
					<?php esc_html_e( 'View latest scan', 'prelaunch-checker' ); ?>
				</a>
			</p>
		</div>
	<?php endif; ?>

	<?php /* Delta section — shown after first scan */ ?>
	<?php if ( ! $is_first_scan ) : ?>
		<?php
		$new_count      = count( $delta['new'] );
		$resolved_count = count( $delta['resolved'] );
		?>
		<div class="prelaunch-checker-delta" role="region" aria-label="<?php esc_attr_e( 'Changes since last scan', 'prelaunch-checker' ); ?>">
			<?php if ( $new_count > 0 ) : ?>
				<span class="prelaunch-checker-delta__new">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %d: number of new issues */
							_n( '%d new issue', '%d new issues', $new_count, 'prelaunch-checker' ),
							$new_count
						)
					);
					?>
				</span>
			<?php endif; ?>
			<?php if ( $resolved_count > 0 ) : ?>
				<span class="prelaunch-checker-delta__resolved">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %d: number of resolved issues */
							_n( '%d resolved', '%d resolved', $resolved_count, 'prelaunch-checker' ),
							$resolved_count
						)
					);
					?>
				</span>
			<?php endif; ?>
			<?php if ( 0 === $new_count && 0 === $resolved_count ) : ?>
				<span class="prelaunch-checker-delta__unchanged"><?php esc_html_e( 'No changes since last scan.', 'prelaunch-checker' ); ?></span>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php /* Re-scan button — hidden when browsing a historical scan */ ?>
	<?php if ( ! $is_historical ) : ?>
	<div class="prelaunch-checker-actions">
		<form method="post" action="<?php echo esc_url( $scan_action_url ); ?>" id="prelaunch-checker-rescan-form">
			<?php wp_nonce_field( 'prelaunch_checker_run_scan', 'prelaunch_checker_scan_nonce' ); ?>
			<button type="submit" id="prelaunch-checker-rescan-btn" class="button button-primary">
				<?php esc_html_e( 'Re-scan', 'prelaunch-checker' ); ?>
			</button>
		</form>
	</div>
	<?php endif; ?>

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
		PRELAUNCH_CHECKER_SEVERITY_BLOCKER => 0,
		PRELAUNCH_CHECKER_SEVERITY_WARNING => 1,
		PRELAUNCH_CHECKER_SEVERITY_INFO    => 2,
	);

	foreach ( $by_category as $cat_id => $cat_results ) :
		// Count failures in this category.
		$fail_count = 0;
		foreach ( $cat_results as $row ) {
			if ( PRELAUNCH_CHECKER_STATUS_FAIL === $row['status'] ) {
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
			$status_order = array( PRELAUNCH_CHECKER_STATUS_FAIL => 0, PRELAUNCH_CHECKER_STATUS_PASS => 1, PRELAUNCH_CHECKER_STATUS_SKIP => 2 );
			return ( $status_order[ $a['status'] ] ?? 9 ) - ( $status_order[ $b['status'] ] ?? 9 );
		} );

		$section_id = 'prelaunch-checker-cat-' . esc_attr( $cat_id );
		$label      = isset( $category_labels[ $cat_id ] ) ? $category_labels[ $cat_id ] : $cat_id;
	?>
		<section class="prelaunch-checker-category is-expanded" id="<?php echo esc_attr( $section_id . '-section' ); ?>">
			<h2 class="prelaunch-checker-category__header">
				<button
					type="button"
					class="prelaunch-checker-category__toggle"
					id="<?php echo esc_attr( $section_id . '-header' ); ?>"
					aria-expanded="true"
					aria-controls="<?php echo esc_attr( $section_id . '-body' ); ?>"
				>
					<span class="prelaunch-checker-category__chevron" aria-hidden="true"></span>
					<span class="prelaunch-checker-category__label"><?php echo esc_html( $label ); ?></span>
					<?php if ( $fail_count > 0 ) : ?>
						<span class="prelaunch-checker-category__badge" aria-label="<?php
						/* translators: %d: number of failing checks in this category */
						echo esc_attr( sprintf( _n( '%d issue', '%d issues', $fail_count, 'prelaunch-checker' ), $fail_count ) ); ?>">
							<?php echo absint( $fail_count ); ?>
						</span>
					<?php endif; ?>
				</button>
			</h2>
			<div
				class="prelaunch-checker-category__body"
				id="<?php echo esc_attr( $section_id . '-body' ); ?>"
				role="region"
				aria-labelledby="<?php echo esc_attr( $section_id . '-header' ); ?>"
			>
				<table class="wp-list-table widefat fixed striped prelaunch-checker-results-table">
					<thead>
						<tr>
							<th scope="col" class="prelaunch-checker-col-severity"><?php esc_html_e( 'Severity', 'prelaunch-checker' ); ?></th>
							<th scope="col" class="prelaunch-checker-col-check"><?php esc_html_e( 'Check', 'prelaunch-checker' ); ?></th>
							<th scope="col" class="prelaunch-checker-col-result"><?php esc_html_e( 'Result', 'prelaunch-checker' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $cat_results as $row ) : ?>
						<tr class="prelaunch-checker-result prelaunch-checker-result--<?php echo esc_attr( $row['status'] ); ?>">
							<td class="prelaunch-checker-col-severity">
								<?php if ( PRELAUNCH_CHECKER_STATUS_FAIL === $row['status'] ) : ?>
									<span class="prelaunch-checker-severity-pill prelaunch-checker-severity-pill--<?php echo esc_attr( $row['severity'] ); ?>">
										<?php echo esc_html( strtoupper( $row['severity'] ) ); ?>
									</span>
								<?php elseif ( PRELAUNCH_CHECKER_STATUS_PASS === $row['status'] ) : ?>
									<span class="prelaunch-checker-severity-pill prelaunch-checker-severity-pill--pass"><?php esc_html_e( 'PASS', 'prelaunch-checker' ); ?></span>
								<?php else : ?>
									<span class="prelaunch-checker-severity-pill prelaunch-checker-severity-pill--skip"><?php esc_html_e( 'SKIP', 'prelaunch-checker' ); ?></span>
								<?php endif; ?>
							</td>
							<td class="prelaunch-checker-col-check">
								<?php echo esc_html( $row['label'] ); ?>
							</td>
							<td class="prelaunch-checker-col-result">
								<?php if ( PRELAUNCH_CHECKER_STATUS_FAIL === $row['status'] ) : ?>
									<strong><?php echo esc_html( $row['message'] ); ?></strong>
									<?php if ( ! empty( $row['fix_hint'] ) ) : ?>
										<p class="prelaunch-checker-fix-hint"><?php echo esc_html( $row['fix_hint'] ); ?></p>
									<?php endif; ?>
								<?php elseif ( PRELAUNCH_CHECKER_STATUS_SKIP === $row['status'] ) : ?>
									<span class="prelaunch-checker-skip-message"><?php echo esc_html( $row['message'] ); ?></span>
								<?php else : ?>
									<span class="prelaunch-checker-pass-message"><?php esc_html_e( 'Passed', 'prelaunch-checker' ); ?></span>
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

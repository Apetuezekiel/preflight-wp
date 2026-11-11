<?php
/**
 * History tab template.
 *
 * Receives in scope:
 *   $history (array) — scan envelopes, most recent first. Empty array if none.
 *
 * @package Prelaunch_Checker
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- template-scoped variables injected by the calling render method.
?>
<div class="prelaunch-checker-history">

<?php if ( empty( $history ) ) : ?>

	<div class="prelaunch-checker-empty-state">
		<p><?php esc_html_e( 'No scan history yet. Run a scan from the Dashboard tab to get started.', 'prelaunch-checker' ); ?></p>
	</div>

<?php else : ?>

	<p class="description">
		<?php esc_html_e( 'Showing the last 10 scans. Click a row to view detailed results.', 'prelaunch-checker' ); ?>
	</p>

	<table class="wp-list-table widefat fixed striped prelaunch-checker-history-table">
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Scan time', 'prelaunch-checker' ); ?></th>
				<th scope="col" class="prelaunch-checker-col-severity"><?php esc_html_e( 'Blockers', 'prelaunch-checker' ); ?></th>
				<th scope="col" class="prelaunch-checker-col-severity"><?php esc_html_e( 'Warnings', 'prelaunch-checker' ); ?></th>
				<th scope="col" class="prelaunch-checker-col-severity"><?php esc_html_e( 'Info', 'prelaunch-checker' ); ?></th>
				<th scope="col" class="prelaunch-checker-col-severity"><?php esc_html_e( 'Passed', 'prelaunch-checker' ); ?></th>
				<th scope="col" class="prelaunch-checker-col-severity"><?php esc_html_e( 'Skipped', 'prelaunch-checker' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ( $history as $index => $scan ) : ?>
			<?php
			$summary   = $scan['summary'];
			$timestamp = $scan['timestamp'];
			$date_str  = wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $timestamp ) );
			$drill_url = add_query_arg(
				array(
					'page'       => 'prelaunch-checker',
					'tab'        => 'dashboard',
					'scan_index' => absint( $index ),
				),
				admin_url( 'tools.php' )
			);
			?>
			<tr
				class="prelaunch-checker-history-row<?php echo ( 0 === $index ) ? ' prelaunch-checker-history-row--latest' : ''; ?>"
				data-drill-url="<?php echo esc_url( $drill_url ); ?>"
				tabindex="0"
				role="link"
				aria-label="<?php
				/* translators: %s: scan date and time string */
				echo esc_attr( sprintf( __( 'View scan from %s', 'prelaunch-checker' ), $date_str ) ); ?>"
			>
				<td><?php echo esc_html( $date_str ); ?><?php if ( 0 === $index ) : ?> <em>(<?php esc_html_e( 'latest', 'prelaunch-checker' ); ?>)</em><?php endif; ?></td>
				<td class="prelaunch-checker-col-severity">
					<span class="prelaunch-checker-severity-pill prelaunch-checker-severity-pill--blocker" aria-label="<?php
					/* translators: %d: number of blocker-severity issues in this scan */
					echo esc_attr( sprintf( __( '%d blockers', 'prelaunch-checker' ), $summary['blockers'] ) ); ?>">
						<?php echo absint( $summary['blockers'] ); ?>
					</span>
				</td>
				<td class="prelaunch-checker-col-severity">
					<span class="prelaunch-checker-severity-pill prelaunch-checker-severity-pill--warning" aria-label="<?php
					/* translators: %d: number of warning-severity issues in this scan */
					echo esc_attr( sprintf( __( '%d warnings', 'prelaunch-checker' ), $summary['warnings'] ) ); ?>">
						<?php echo absint( $summary['warnings'] ); ?>
					</span>
				</td>
				<td class="prelaunch-checker-col-severity">
					<span class="prelaunch-checker-severity-pill prelaunch-checker-severity-pill--info" aria-label="<?php
					/* translators: %d: number of info-severity issues in this scan */
					echo esc_attr( sprintf( __( '%d info', 'prelaunch-checker' ), $summary['info'] ) ); ?>">
						<?php echo absint( $summary['info'] ); ?>
					</span>
				</td>
				<td class="prelaunch-checker-col-severity"><?php echo absint( $summary['passed'] ); ?></td>
				<td class="prelaunch-checker-col-severity"><?php echo absint( $summary['skipped'] ); ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>

<?php endif; ?>
</div>

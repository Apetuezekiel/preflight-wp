<?php
/**
 * History tab template.
 *
 * Receives in scope:
 *   $history (array) — scan envelopes, most recent first. Empty array if none.
 *
 * @package PreFlight
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="preflight-history">

<?php if ( empty( $history ) ) : ?>

	<div class="preflight-empty-state">
		<p><?php esc_html_e( 'No scan history yet. Run a scan from the Dashboard tab to get started.', 'preflight-wp' ); ?></p>
	</div>

<?php else : ?>

	<p class="description">
		<?php esc_html_e( 'Showing the last 10 scans. Click a row to view detailed results.', 'preflight-wp' ); ?>
	</p>

	<table class="wp-list-table widefat fixed striped preflight-history-table">
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Scan time', 'preflight-wp' ); ?></th>
				<th scope="col" class="preflight-col-severity"><?php esc_html_e( 'Blockers', 'preflight-wp' ); ?></th>
				<th scope="col" class="preflight-col-severity"><?php esc_html_e( 'Warnings', 'preflight-wp' ); ?></th>
				<th scope="col" class="preflight-col-severity"><?php esc_html_e( 'Info', 'preflight-wp' ); ?></th>
				<th scope="col" class="preflight-col-severity"><?php esc_html_e( 'Passed', 'preflight-wp' ); ?></th>
				<th scope="col" class="preflight-col-severity"><?php esc_html_e( 'Skipped', 'preflight-wp' ); ?></th>
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
					'page'       => 'preflight',
					'tab'        => 'dashboard',
					'scan_index' => absint( $index ),
				),
				admin_url( 'tools.php' )
			);
			?>
			<tr
				class="preflight-history-row<?php echo ( 0 === $index ) ? ' preflight-history-row--latest' : ''; ?>"
				data-drill-url="<?php echo esc_url( $drill_url ); ?>"
				tabindex="0"
				role="link"
				aria-label="<?php echo esc_attr( sprintf( __( 'View scan from %s', 'preflight-wp' ), $date_str ) ); ?>"
			>
				<td><?php echo esc_html( $date_str ); ?><?php if ( 0 === $index ) : ?> <em>(<?php esc_html_e( 'latest', 'preflight-wp' ); ?>)</em><?php endif; ?></td>
				<td class="preflight-col-severity">
					<span class="preflight-severity-pill preflight-severity-pill--blocker" aria-label="<?php echo esc_attr( sprintf( __( '%d blockers', 'preflight-wp' ), $summary['blockers'] ) ); ?>">
						<?php echo absint( $summary['blockers'] ); ?>
					</span>
				</td>
				<td class="preflight-col-severity">
					<span class="preflight-severity-pill preflight-severity-pill--warning" aria-label="<?php echo esc_attr( sprintf( __( '%d warnings', 'preflight-wp' ), $summary['warnings'] ) ); ?>">
						<?php echo absint( $summary['warnings'] ); ?>
					</span>
				</td>
				<td class="preflight-col-severity">
					<span class="preflight-severity-pill preflight-severity-pill--info" aria-label="<?php echo esc_attr( sprintf( __( '%d info', 'preflight-wp' ), $summary['info'] ) ); ?>">
						<?php echo absint( $summary['info'] ); ?>
					</span>
				</td>
				<td class="preflight-col-severity"><?php echo absint( $summary['passed'] ); ?></td>
				<td class="preflight-col-severity"><?php echo absint( $summary['skipped'] ); ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>

<?php endif; ?>
</div>

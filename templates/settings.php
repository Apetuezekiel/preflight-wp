<?php
/**
 * Settings tab template.
 *
 * Receives in scope:
 *   $settings   (array)                      — current preflight_settings option.
 *   $categories (PreFlight_Check_Category[]) — all registered categories.
 *
 * Checkbox logic: checked = check is ENABLED (intuitive UX).
 * The save handler (PreFlight_Admin::handle_settings_save) collects enabled_checks[]
 * and computes disabled = all_check_ids - enabled_checks, storing the result in
 * preflight_settings['disabled_checks'] (Brief §3.6, §5.3).
 *
 * @package PreFlight
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$disabled_checks = isset( $settings['disabled_checks'] ) ? (array) $settings['disabled_checks'] : array();
$dev_info        = isset( $settings['developer_info'] ) ? (array) $settings['developer_info'] : array();
$form_action     = admin_url( 'tools.php?page=preflight&tab=settings' );
?>
<div class="preflight-settings">
	<form method="post" action="<?php echo esc_url( $form_action ); ?>">
		<?php wp_nonce_field( 'preflight_save_settings', 'preflight_settings_nonce' ); ?>

		<?php /* Check toggles section */ ?>
		<h2><?php esc_html_e( 'Check Toggles', 'preflight-wp' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Uncheck a box to disable that check. Disabled checks are skipped during scans.', 'preflight-wp' ); ?>
		</p>

		<?php foreach ( $categories as $category ) : ?>
			<h3><?php echo esc_html( $category->get_category_label() ); ?></h3>
			<fieldset>
				<legend class="screen-reader-text">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s: category label */
							__( 'Checks in category: %s', 'preflight-wp' ),
							$category->get_category_label()
						)
					);
					?>
				</legend>
				<ul class="preflight-check-list">
				<?php foreach ( $category->get_check_ids() as $check_id ) : ?>
					<?php
					// get_check() instantiates the check for label/severity metadata only.
					// Detection (run()) is NOT called here — safe in settings context.
					$check = $category->get_check( $check_id );
					if ( null === $check ) {
						continue;
					}
					$is_enabled = ! in_array( $check_id, $disabled_checks, true );
					$field_id   = 'preflight-check-' . esc_attr( str_replace( '.', '-', $check_id ) );
					$severity   = $check->get_default_severity();
					?>
					<li class="preflight-check-list__item">
						<label for="<?php echo esc_attr( $field_id ); ?>" class="preflight-check-list__label">
							<input
								type="checkbox"
								id="<?php echo esc_attr( $field_id ); ?>"
								name="enabled_checks[]"
								value="<?php echo esc_attr( $check_id ); ?>"
								<?php checked( $is_enabled ); ?>
							>
							<span class="preflight-check-list__name"><?php echo esc_html( $check->get_label() ); ?></span>
							<span class="preflight-severity-pill preflight-severity-pill--<?php echo esc_attr( $severity ); ?> preflight-severity-pill--sm">
								<?php echo esc_html( strtoupper( $severity ) ); ?>
							</span>
							<code class="preflight-check-list__id"><?php echo esc_html( $check_id ); ?></code>
						</label>
					</li>
				<?php endforeach; ?>
				</ul>
			</fieldset>
		<?php endforeach; ?>

		<?php /* Developer info section */ ?>
		<h2><?php esc_html_e( 'Developer Info', 'preflight-wp' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Optional. Included in scan reports (Phase 3).', 'preflight-wp' ); ?>
		</p>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">
					<label for="developer-name"><?php esc_html_e( 'Your name', 'preflight-wp' ); ?></label>
				</th>
				<td>
					<input
						type="text"
						id="developer-name"
						name="developer_name"
						value="<?php echo esc_attr( $dev_info['name'] ?? '' ); ?>"
						class="regular-text"
						autocomplete="name"
					>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="developer-email"><?php esc_html_e( 'Your email', 'preflight-wp' ); ?></label>
				</th>
				<td>
					<input
						type="email"
						id="developer-email"
						name="developer_email"
						value="<?php echo esc_attr( $dev_info['email'] ?? '' ); ?>"
						class="regular-text"
						autocomplete="email"
					>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="developer-url"><?php esc_html_e( 'Your URL', 'preflight-wp' ); ?></label>
				</th>
				<td>
					<input
						type="url"
						id="developer-url"
						name="developer_url"
						value="<?php echo esc_attr( $dev_info['url'] ?? '' ); ?>"
						class="regular-text"
						autocomplete="url"
					>
				</td>
			</tr>
		</table>

		<p class="submit">
			<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Settings', 'preflight-wp' ); ?></button>
		</p>
	</form>
</div>

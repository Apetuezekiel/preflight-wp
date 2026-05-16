<?php
/**
 * Forms & Communication check category — 2 checks per Brief §3.2.
 *
 * @package PreFlight
 * @since   0.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Check category: Forms & Communication.
 *
 * Detects absent contact form plugin and missing transactional email
 * (SMTP) configuration. All detection uses class_exists() and defined() —
 * no loopback HTTP requests, no test emails sent (Brief §5.2).
 *
 * All detection signals were verified against current published plugin source
 * (2026-05-16). See CONTACT_FORM_SIGNALS and SMTP_PLUGIN_SIGNALS docblocks
 * for per-signal source references.
 *
 * @since 0.6.0
 */
class PreFlight_Checks_Forms implements PreFlight_Check_Category {

	/**
	 * Plugin label → detection signal map for recognised contact form plugins.
	 *
	 * Each signal is evaluated with:  class_exists($signal) || defined($signal)
	 *
	 * Verified sources (2026-05-16):
	 *   Contact Form 7   : WPCF7_VERSION — define() in wp-contact-form-7.php
	 *                      github.com/rocklobster-in/contact-form-7
	 *   Gravity Forms    : GFForms (class) — class GFForms defined in gravityforms.php
	 *                      github.com/pronamic/gravityforms (mirror)
	 *   WPForms          : WPFORMS_VERSION — define() in wpforms.php (lite + pro)
	 *                      plugins.svn.wordpress.org/wpforms-lite/trunk/wpforms.php
	 *   Ninja Forms      : Ninja_Forms (class) — final class defined in ninja-forms.php
	 *                      plugins.svn.wordpress.org/ninja-forms/trunk/ninja-forms.php
	 *   Fluent Forms     : FLUENTFORM_VERSION — define() in fluentform.php
	 *                      plugins.svn.wordpress.org/fluentform/trunk/fluentform.php
	 *   Formidable Forms : FrmAppHelper (class) — global class, loaded via SPL autoloader
	 *                      on every request; no define() in main file
	 *                      plugins.svn.wordpress.org/formidable/trunk/classes/helpers/FrmAppHelper.php
	 *   Elementor Pro    : ELEMENTOR_PRO_VERSION — define() in elementor-pro.php
	 *                      verified via unofficial GitHub mirror (yangm97/elementor-pro v3.6.4);
	 *                      no official public source — constant name is stable across versions
	 *
	 * Extension point (v1.1): a filter hook 'preflight_contact_form_signals' will
	 * allow site-specific additions. Do not implement the filter now.
	 *
	 * @var array<string, string>
	 */
	const CONTACT_FORM_SIGNALS = array(
		'Contact Form 7'   => 'WPCF7_VERSION',
		'Gravity Forms'    => 'GFForms',
		'WPForms'          => 'WPFORMS_VERSION',
		'Ninja Forms'      => 'Ninja_Forms',
		'Fluent Forms'     => 'FLUENTFORM_VERSION',
		'Formidable Forms' => 'FrmAppHelper',
		'Elementor Pro'    => 'ELEMENTOR_PRO_VERSION',
	);

	/**
	 * Plugin label → detection signal map for recognised SMTP / transactional email plugins.
	 *
	 * Each signal is evaluated with:  class_exists($signal) || defined($signal)
	 *
	 * Verified sources (2026-05-16):
	 *   WP Mail SMTP  : WPMS_PLUGIN_VER — define() in wp_mail_smtp.php
	 *                   plugins.svn.wordpress.org/wp-mail-smtp/trunk/wp_mail_smtp.php
	 *   FluentSMTP    : FLUENTMAIL_PLUGIN_FILE — define() in fluent-smtp.php
	 *                   plugins.svn.wordpress.org/fluent-smtp/trunk/fluent-smtp.php
	 *                   (no version constant in main file; FLUENTMAIL_PLUGIN_FILE is
	 *                   defined on every load and is stable)
	 *   Post SMTP     : POST_SMTP_VER — define() in postman-smtp.php
	 *                   plugins.svn.wordpress.org/post-smtp/trunk/postman-smtp.php
	 *   Easy WP SMTP  : EasyWPSMTP_PLUGIN_VERSION — define() in easy-wp-smtp.php
	 *                   plugins.svn.wordpress.org/easy-wp-smtp/trunk/easy-wp-smtp.php
	 *   SMTP Mailer   : SMTP_MAILER_VERSION — define() in main.php
	 *                   plugins.svn.wordpress.org/smtp-mailer/trunk/main.php
	 *
	 * Extension point (v1.1): a filter hook 'preflight_smtp_plugin_signals' will
	 * allow additions. Do not implement the filter now.
	 *
	 * @var array<string, string>
	 */
	const SMTP_PLUGIN_SIGNALS = array(
		'WP Mail SMTP' => 'WPMS_PLUGIN_VER',
		'FluentSMTP'   => 'FLUENTMAIL_PLUGIN_FILE',
		'Post SMTP'    => 'POST_SMTP_VER',
		'Easy WP SMTP' => 'EasyWPSMTP_PLUGIN_VERSION',
		'SMTP Mailer'  => 'SMTP_MAILER_VERSION',
	);

	// -------------------------------------------------------------------------
	// PreFlight_Check_Category interface
	// -------------------------------------------------------------------------

	/**
	 * @return string
	 */
	public function get_category_id(): string {
		return 'forms';
	}

	/**
	 * @return string
	 */
	public function get_category_label(): string {
		return __( 'Forms & Communication', 'preflight-wp' );
	}

	/**
	 * Return the ordered list of check IDs in this category.
	 *
	 * Ordered warning → info (matches Brief §3.2 severity ordering).
	 *
	 * @return string[]
	 */
	public function get_check_ids(): array {
		return array_column( self::get_spec(), 0 );
	}

	/**
	 * Return a check object for the given ID, or null if unknown.
	 *
	 * @param string $id Namespaced check ID.
	 * @return PreFlight_Check|null
	 */
	public function get_check( string $id ): ?PreFlight_Check {
		foreach ( self::get_spec() as $entry ) {
			if ( $entry[0] !== $id ) {
				continue;
			}
			return new PreFlight_Configurable_Check(
				$entry[0],
				$entry[1],
				$entry[2],
				array( $this, $entry[3] )
			);
		}
		return null;
	}

	// -------------------------------------------------------------------------
	// Check specification table
	// -------------------------------------------------------------------------

	/**
	 * Return the static check specification table.
	 *
	 * Each entry: [ id, label, severity_constant, method_name ].
	 * Ordered warning → info (SMTP is Warning; contact form is Info).
	 *
	 * @return array[]
	 */
	private static function get_spec(): array {
		return array(
			array( 'forms.no-smtp-configured', __( 'Transactional email delivery', 'preflight-wp' ), PREFLIGHT_SEVERITY_WARNING, 'check_smtp_plugin' ),
			array( 'forms.no-contact-form',    __( 'Contact form plugin',          'preflight-wp' ), PREFLIGHT_SEVERITY_INFO,    'check_contact_form' ),
		);
	}

	// -------------------------------------------------------------------------
	// Detection methods (stubs — implemented in commit 2)
	// -------------------------------------------------------------------------

	/**
	 * Fail when no recognised SMTP / transactional email plugin is active.
	 *
	 * @since 0.6.0
	 * @return PreFlight_Check_Result
	 */
	public function check_smtp_plugin(): PreFlight_Check_Result {
		return PreFlight_Check_Result::skip( 'not yet implemented' );
	}

	/**
	 * Fail when no recognised contact form plugin is active.
	 *
	 * @since 0.6.0
	 * @return PreFlight_Check_Result
	 */
	public function check_contact_form(): PreFlight_Check_Result {
		return PreFlight_Check_Result::skip( 'not yet implemented' );
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Return the first matched plugin label for the given signal map, or null.
	 *
	 * Intentionally duplicated from PreFlight_Checks_SEO — no shared extraction
	 * until a third category needs it (YAGNI).
	 *
	 * @param array<string, string> $signals Plugin label => detection signal.
	 * @return string|null Matched plugin label, or null if none matched.
	 */
	private function matches_any_signal( array $signals ): ?string {
		foreach ( $signals as $label => $signal ) {
			if ( class_exists( $signal ) || defined( $signal ) ) {
				return $label;
			}
		}
		return null;
	}
}

add_action(
	'preflight_register_categories',
	static function ( PreFlight_Core $core ): void {
		$core->register_category( new PreFlight_Checks_Forms() );
	}
);

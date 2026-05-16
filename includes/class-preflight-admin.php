<?php
/**
 * Admin UI orchestrator: menu registration, tab routing, form handlers, AJAX.
 *
 * @package PreFlight
 * @since   0.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wires all WordPress admin hooks required by the PreFlight admin UI.
 *
 * Instantiated only inside is_admin() so no admin code runs on the frontend.
 * All state-changing actions (settings save, scan trigger, AJAX rescan) are
 * protected by nonce verification and manage_options capability checks.
 *
 * @since 0.3.0
 */
class PreFlight_Admin {

	/** @var PreFlight_Core */
	private $core;

	/** @var PreFlight_History */
	private $history;

	/**
	 * @param PreFlight_Core    $core    Injected scanner/registry access.
	 * @param PreFlight_History $history Injected history service.
	 */
	public function __construct( PreFlight_Core $core, PreFlight_History $history ) {
		$this->core    = $core;
		$this->history = $history;

		add_action( 'admin_menu',             array( $this, 'register_menu' ) );
		add_action( 'admin_init',             array( $this, 'handle_settings_save' ) );
		add_action( 'admin_init',             array( $this, 'handle_scan_trigger' ) );
		add_action( 'admin_enqueue_scripts',  array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_preflight_rescan', array( $this, 'ajax_rescan' ) );
	}

	// -------------------------------------------------------------------------
	// Hook callbacks — public for WordPress to invoke
	// -------------------------------------------------------------------------

	/**
	 * Register the PreFlight submenu under Tools.
	 *
	 * @since  0.3.0
	 */
	public function register_menu(): void {
		add_submenu_page(
			'tools.php',
			__( 'PreFlight', 'preflight-wp' ),
			__( 'PreFlight', 'preflight-wp' ),
			'manage_options',
			'preflight',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Render the full tabbed admin page.
	 *
	 * @since  0.3.0
	 */
	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'preflight-wp' ) );
		}

		$active_tab = $this->get_active_tab();
		$page_url   = admin_url( 'tools.php?page=preflight' );

		echo '<div class="wrap preflight-wrap">';
		echo '<h1>' . esc_html__( 'PreFlight', 'preflight-wp' ) . '</h1>';

		// Inline ARIA live region — updated by JS during AJAX rescan.
		echo '<div id="preflight-status" class="screen-reader-text" aria-live="polite" aria-atomic="true"></div>';

		$this->render_tab_nav( $active_tab );

		echo '<div class="preflight-tab-content">';

		switch ( $active_tab ) {
			case 'history':
				$this->render_history_tab();
				break;
			case 'settings':
				$this->render_settings_tab();
				break;
			default:
				$this->render_dashboard_tab();
				break;
		}

		echo '</div>';
		echo '</div>';
	}

	/**
	 * Save settings when the settings form is submitted.
	 *
	 * Runs on admin_init. Exits silently unless this is a settings form POST.
	 * Checkbox logic: checked = enabled. Collects enabled_checks[], computes
	 * disabled = all_check_ids - enabled_checks (Brief §3.6).
	 *
	 * @since  0.3.0
	 */
	public function handle_settings_save(): void {
		if ( ! isset( $_POST['preflight_settings_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_key( $_POST['preflight_settings_nonce'] ), 'preflight_save_settings' ) ) {
			wp_die( esc_html__( 'Nonce verification failed.', 'preflight-wp' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'preflight-wp' ) );
		}

		// Collect all known check IDs from the registry.
		$all_check_ids = array();
		foreach ( $this->core->get_categories() as $category ) {
			foreach ( $category->get_check_ids() as $check_id ) {
				$all_check_ids[] = sanitize_key( $check_id );
			}
		}

		// Collect checked (enabled) check IDs from the POST.
		$raw_enabled = isset( $_POST['enabled_checks'] ) ? (array) $_POST['enabled_checks'] : array();
		$enabled     = array_map( 'sanitize_key', $raw_enabled );

		// Disabled = all known - enabled (unchecked boxes are not submitted by browser).
		$disabled = array_values( array_diff( $all_check_ids, $enabled ) );

		// Sanitize developer_info fields.
		$dev_info = array(
			'name'  => sanitize_text_field( $_POST['developer_name'] ?? '' ),
			'email' => sanitize_email( $_POST['developer_email'] ?? '' ),
			'url'   => esc_url_raw( $_POST['developer_url'] ?? '' ),
		);

		$settings = array(
			'disabled_checks' => $disabled,
			'developer_info'  => $dev_info,
		);

		update_option( 'preflight_settings', $settings );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'preflight',
					'tab'     => 'settings',
					'updated' => '1',
				),
				admin_url( 'tools.php' )
			)
		);
		exit;
	}

	/**
	 * Run a synchronous scan when the "Run Scan" form is submitted.
	 *
	 * @since  0.3.0
	 */
	public function handle_scan_trigger(): void {
		if ( ! isset( $_POST['preflight_scan_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_key( $_POST['preflight_scan_nonce'] ), 'preflight_run_scan' ) ) {
			wp_die( esc_html__( 'Nonce verification failed.', 'preflight-wp' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'preflight-wp' ) );
		}

		$envelope = $this->core->get_scanner()->run();
		$this->history->persist( $envelope );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => 'preflight',
					'tab'     => 'dashboard',
					'scanned' => '1',
				),
				admin_url( 'tools.php' )
			)
		);
		exit;
	}

	/**
	 * Enqueue admin CSS and JS, scoped to the PreFlight page only.
	 *
	 * @since  0.3.0
	 * @param  string $hook Current admin page hook.
	 */
	public function enqueue_assets( string $hook ): void {
		if ( 'tools_page_preflight' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'preflight-admin',
			PREFLIGHT_URL . 'assets/css/admin.css',
			array(),
			PREFLIGHT_VERSION
		);

		wp_enqueue_script(
			'preflight-admin',
			PREFLIGHT_URL . 'assets/js/admin.js',
			array(),
			PREFLIGHT_VERSION,
			true
		);

		wp_localize_script(
			'preflight-admin',
			'preflight',
			array(
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'rescanNonce' => wp_create_nonce( 'preflight_rescan' ),
				'scanning'  => __( 'Scanning\xe2\x80\xa6', 'preflight-wp' ),
				'rescan'    => __( 'Re-scan', 'preflight-wp' ),
				'scanError' => __( 'Scan failed. Please try again.', 'preflight-wp' ),
			)
		);
	}

	/**
	 * AJAX handler: run a scan and return updated dashboard markup as JSON.
	 *
	 * @since  0.3.0
	 */
	public function ajax_rescan(): void {
		check_ajax_referer( 'preflight_rescan', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'code' => 'forbidden', 'message' => __( 'Insufficient permissions.', 'preflight-wp' ) ) );
		}

		$envelope = $this->core->get_scanner()->run();
		$this->history->persist( $envelope );

		$previous     = $this->history->get_previous();
		$delta        = $this->history->compute_delta( $envelope, $previous );
		$html_partial = $this->render_dashboard_partial( $envelope, $delta, false );

		wp_send_json_success(
			array(
				'envelope'     => $envelope,
				'delta'        => $delta,
				'html_partial' => $html_partial,
			)
		);
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Return the active tab slug, validated against the whitelist.
	 *
	 * @since  0.3.0
	 * @return string One of 'dashboard', 'history', 'settings'.
	 */
	private function get_active_tab(): string {
		$allowed = array( 'dashboard', 'history', 'settings' );
		$tab     = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'dashboard';
		return in_array( $tab, $allowed, true ) ? $tab : 'dashboard';
	}

	/**
	 * Output the tab navigation bar.
	 *
	 * @since  0.3.0
	 * @param  string $active Currently active tab slug.
	 */
	private function render_tab_nav( string $active ): void {
		$base = admin_url( 'tools.php?page=preflight' );
		$tabs = array(
			'dashboard' => __( 'Dashboard', 'preflight-wp' ),
			'history'   => __( 'History', 'preflight-wp' ),
			'settings'  => __( 'Settings', 'preflight-wp' ),
		);

		echo '<nav class="nav-tab-wrapper" aria-label="' . esc_attr__( 'PreFlight tabs', 'preflight-wp' ) . '">';
		foreach ( $tabs as $slug => $label ) {
			$class = ( $slug === $active ) ? 'nav-tab nav-tab-active' : 'nav-tab';
			$url   = add_query_arg( 'tab', $slug, $base );
			echo '<a href="' . esc_url( $url ) . '" class="' . esc_attr( $class ) . '">' . esc_html( $label ) . '</a>';
		}
		echo '</nav>';
	}

	/**
	 * Render the dashboard tab.
	 *
	 * @since  0.3.0
	 */
	private function render_dashboard_tab(): void {
		$envelope      = $this->history->get_latest();
		$previous      = $this->history->get_previous();
		$is_first_scan = ( null !== $envelope && null === $previous );
		$delta         = ( null !== $envelope )
			? $this->history->compute_delta( $envelope, $previous )
			: array( 'new' => array(), 'resolved' => array(), 'unchanged' => array() );

		// Success notice.
		if ( isset( $_GET['scanned'] ) && '1' === $_GET['scanned'] ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Scan complete.', 'preflight-wp' ) . '</p></div>';
		}

		$scan_action_url = admin_url( 'tools.php?page=preflight&tab=dashboard' );
		$rescan_nonce    = wp_create_nonce( 'preflight_run_scan' );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $this->render_dashboard_partial( $envelope, $delta, $is_first_scan, $scan_action_url, $rescan_nonce );
	}

	/**
	 * Render dashboard HTML as a string (used by both full-page and AJAX responses).
	 *
	 * @since  0.3.0
	 * @param  array|null $envelope        Latest scan envelope or null.
	 * @param  array      $delta           Delta array from compute_delta().
	 * @param  bool       $is_first_scan   True when there is no previous scan to compare.
	 * @param  string     $scan_action_url Form action URL.
	 * @param  string     $rescan_nonce    Nonce value for the scan trigger form.
	 * @return string
	 */
	private function render_dashboard_partial(
		?array $envelope,
		array $delta,
		bool $is_first_scan,
		string $scan_action_url = '',
		string $rescan_nonce = ''
	): string {
		if ( '' === $scan_action_url ) {
			$scan_action_url = admin_url( 'tools.php?page=preflight&tab=dashboard' );
		}
		if ( '' === $rescan_nonce ) {
			$rescan_nonce = wp_create_nonce( 'preflight_run_scan' );
		}

		ob_start();
		$template = PREFLIGHT_PATH . 'templates/dashboard.php';
		if ( file_exists( $template ) ) {
			include $template;
		}
		return ob_get_clean();
	}

	/**
	 * Render the history tab.
	 *
	 * @since  0.3.0
	 */
	private function render_history_tab(): void {
		$history  = $this->history->get_all();
		$template = PREFLIGHT_PATH . 'templates/history.php';
		if ( file_exists( $template ) ) {
			include $template;
		}
	}

	/**
	 * Render the settings tab.
	 *
	 * @since  0.3.0
	 */
	private function render_settings_tab(): void {
		if ( isset( $_GET['updated'] ) && '1' === $_GET['updated'] ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'preflight-wp' ) . '</p></div>';
		}

		$settings   = (array) get_option( 'preflight_settings', array() );
		$categories = $this->core->get_categories();
		$template   = PREFLIGHT_PATH . 'templates/settings.php';
		if ( file_exists( $template ) ) {
			include $template;
		}
	}
}

<?php
/**
 * Plugin Name:        PreFlight
 * Plugin URI:         https://github.com/Apetuezekiel/preflight-wp
 * Description:        Automated pre-launch QA scanner for WordPress. Runs 30+ checks and produces a pass/fail report before go-live.
 * Version:            0.1.0
 * Requires at least:  6.0
 * Requires PHP:       7.4
 * Author:             Ezekiel / Zicstack
 * License:            GPL v2 or later
 * License URI:        https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:        preflight-wp
 * Domain Path:        /languages
 *
 * @package PreFlight
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PREFLIGHT_VERSION', '0.1.0' );
define( 'PREFLIGHT_FILE', __FILE__ );
define( 'PREFLIGHT_PATH', plugin_dir_path( __FILE__ ) );
define( 'PREFLIGHT_URL', plugin_dir_url( __FILE__ ) );

require_once PREFLIGHT_PATH . 'includes/interface-check.php';
require_once PREFLIGHT_PATH . 'includes/class-preflight-core.php';

add_action(
	'plugins_loaded',
	function () {
		load_plugin_textdomain(
			'preflight-wp',
			false,
			dirname( plugin_basename( PREFLIGHT_FILE ) ) . '/languages'
		);
		PreFlight_Core::instance()->boot();
	}
);

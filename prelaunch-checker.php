<?php
/**
 * Plugin Name:        Prelaunch Checker
 * Plugin URI:         https://github.com/Apetuezekiel/preflight-wp
 * Description:        Automated pre-launch QA scanner for WordPress. Runs 30+ checks and produces a pass/fail report before go-live.
 * Version:            1.0.0
 * Requires at least:  6.0
 * Requires PHP:       7.4
 * Author:             Ezekiel Apetu
 * Author URI:         https://github.com/Apetuezekiel
 * License:            GPL v2 or later
 * License URI:        https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:        prelaunch-checker
 * Domain Path:        /languages
 *
 * @package Prelaunch_Checker
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PRELAUNCH_CHECKER_VERSION', '1.0.0' );
define( 'PRELAUNCH_CHECKER_FILE', __FILE__ );
define( 'PRELAUNCH_CHECKER_PATH', plugin_dir_path( __FILE__ ) );
define( 'PRELAUNCH_CHECKER_URL', plugin_dir_url( __FILE__ ) );

require_once PRELAUNCH_CHECKER_PATH . 'includes/interface-check.php';
require_once PRELAUNCH_CHECKER_PATH . 'includes/class-prelaunch-checker-core.php';

add_action(
	'plugins_loaded',
	function () {
		Prelaunch_Checker_Core::instance()->boot();
	}
);

<?php
/**
 * Plugin uninstall handler.
 *
 * Removes all data Prelaunch Checker has written to the database. Multisite-aware:
 * iterates all sites and removes both option rows from each.
 *
 * @package Prelaunch_Checker
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( is_multisite() ) {
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
	$sites = get_sites( array( 'number' => 0 ) );
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
	foreach ( $sites as $site ) {
		switch_to_blog( $site->blog_id );
		delete_option( 'prelaunch_checker_settings' );
		delete_option( 'prelaunch_checker_scan_history' );
		restore_current_blog();
	}
} else {
	delete_option( 'prelaunch_checker_settings' );
	delete_option( 'prelaunch_checker_scan_history' );
}

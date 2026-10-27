<?php
/**
 * Uninstall cleanup for Oli Checkout Captcha.
 *
 * @package Oli_OCC_Checkout_Captcha
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$oli_occ_options = array(
	'oli_occ_provider',
	'oli_occ_site_key',
	'oli_occ_secret_key',
	'oli_occ_theme',
	'oli_occ_score_threshold',
	'oli_occ_fail_closed',
	'oli_occ_rate_limit_enabled',
	'oli_occ_rate_limit_max',
	'oli_occ_rate_limit_window',
	'oli_occ_db_version',
	// Option names used up to 1.2.5, in case the migration never ran.
	'olic_provider',
	'olic_site_key',
	'olic_secret_key',
	'olic_theme',
	'olic_score_threshold',
	'olic_fail_closed',
	'olic_rate_limit_enabled',
	'olic_rate_limit_max',
	'olic_rate_limit_window',
	'olic_db_version',
	'rbc_site_key',
	'rbc_secret_key',
	'rbc_theme',
);

foreach ( $oli_occ_options as $oli_occ_option ) {
	delete_option( $oli_occ_option );
}

global $wpdb;

// Rate-limit counters are stored with set_transient() as oli_occ_rl_* keys (olic_rl_* up to 1.2.5).
// Persistent object caches may keep these entries until they expire on their own.
foreach ( array( 'oli_occ_rl_', 'olic_rl_' ) as $oli_occ_rl_prefix ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall must delete leftover rate-limit transients from the options table.
	$wpdb->query(
		$wpdb->prepare(
			'DELETE FROM %i WHERE option_name LIKE %s OR option_name LIKE %s',
			$wpdb->options,
			$wpdb->esc_like( '_transient_' . $oli_occ_rl_prefix ) . '%',
			$wpdb->esc_like( '_transient_timeout_' . $oli_occ_rl_prefix ) . '%'
		)
	);
}

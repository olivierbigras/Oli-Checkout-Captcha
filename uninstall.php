<?php
/**
 * Uninstall cleanup for Oli Checkout Captcha.
 *
 * @package Olic_Checkout_Captcha
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$olic_options = array(
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

foreach ( $olic_options as $olic_option ) {
	delete_option( $olic_option );
}

global $wpdb;

// Rate-limit counters are stored with set_transient() as olic_rl_* keys.
// Persistent object caches may keep these entries until they expire on their own.
$olic_rl_like         = $wpdb->esc_like( '_transient_olic_rl_' ) . '%';
$olic_rl_timeout_like = $wpdb->esc_like( '_transient_timeout_olic_rl_' ) . '%';

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Uninstall must delete leftover rate-limit transients from the options table.
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$olic_rl_like,
		$olic_rl_timeout_like
	)
);

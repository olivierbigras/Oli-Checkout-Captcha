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

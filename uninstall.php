<?php
/**
 * Removes plugin options on uninstall, including 1.0.0 (rbc_*) names.
 *
 * @package Oli_Checkout_Captcha
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$oli_cc_options = array(
	'oli_cc_site_key',
	'oli_cc_secret_key',
	'oli_cc_theme',
	'rbc_site_key',
	'rbc_secret_key',
	'rbc_theme',
);

foreach ( $oli_cc_options as $oli_cc_option ) {
	delete_option( $oli_cc_option );
	delete_site_option( $oli_cc_option );
}

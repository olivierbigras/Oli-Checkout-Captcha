<?php
/**
 * Uninstall script for reCAPTCHA for WooCommerce Block Checkout
 *
 * @package Recaptcha_Block_Checkout
 */

// If uninstall not called from WordPress, exit.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete plugin options
delete_option('rbc_site_key');
delete_option('rbc_secret_key');
delete_option('rbc_theme');

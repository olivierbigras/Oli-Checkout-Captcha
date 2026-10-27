<?php
/**
 * Legacy bootstrap for in-place updates from the previous main file.
 *
 * WordPress keeps loading this basename after a folder-preserving update.
 * This file is not a second plugin: it must not contain a plugin header.
 *
 * @package Oli_OCC_Checkout_Captcha
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'OLI_OCC_PLUGIN_LOADED' ) ) {
	require_once __DIR__ . '/oli-checkout-captcha.php';
}

if ( function_exists( 'oli_occ_maybe_rewrite_active_plugin_file' ) ) {
	oli_occ_maybe_rewrite_active_plugin_file();
}

<?php
/**
 * Legacy bootstrap for in-place updates from the previous main file.
 *
 * WordPress keeps loading this basename after a folder-preserving update.
 * This file is not a second plugin: it must not contain a plugin header.
 *
 * @package Olic_Checkout_Captcha
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'OLIC_PLUGIN_LOADED' ) ) {
	require_once __DIR__ . '/oli-checkout-captcha.php';
}

if ( function_exists( 'olic_maybe_rewrite_active_plugin_file' ) ) {
	olic_maybe_rewrite_active_plugin_file();
}

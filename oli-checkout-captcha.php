<?php
/**
 * Plugin Name: Oli Checkout Captcha
 * Plugin URI: https://github.com/olivierbigras/Oli-Checkout-Captcha
 * Description: Blocks bots and card testing at WooCommerce checkout with Google reCAPTCHA v2/v3 or Cloudflare Turnstile. Works with classic and block checkout.
 * Version: 1.2.7
 * Author: Olivier Bigras
 * Author URI: https://github.com/olivierbigras
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * WC requires at least: 8.0
 * WC tested up to: 11.2
 * Text Domain: oli-checkout-captcha
 * Domain Path: /languages
 *
 * @package Oli_OCC_Checkout_Captcha
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'OLI_OCC_PLUGIN_LOADED' ) ) {
	define( 'OLI_OCC_PLUGIN_LOADED', true );
	define( 'OLI_OCC_VERSION', '1.2.7' );
	define( 'OLI_OCC_PLUGIN_FILE', __FILE__ );
	define( 'OLI_OCC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
	define( 'OLI_OCC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

	require_once OLI_OCC_PLUGIN_DIR . 'includes/oli-occ-upgrade.php';
	require_once OLI_OCC_PLUGIN_DIR . 'includes/class-oli-occ-verifier.php';
	require_once OLI_OCC_PLUGIN_DIR . 'includes/class-oli-occ-rate-limiter.php';
	require_once OLI_OCC_PLUGIN_DIR . 'includes/class-oli-occ-checkout-captcha.php';

	oli_occ_register_option_fallbacks();

	/**
	 * Run activation tasks, including option migration.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	function oli_occ_activate() {
		oli_occ_maybe_migrate_options();
	}
	register_activation_hook( __FILE__, 'oli_occ_activate' );

	/**
	 * Boot the plugin after all plugins are loaded.
	 *
	 * Translations are loaded automatically by WordPress.org (WP 4.6+).
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	function oli_occ_init() {
		oli_occ_maybe_migrate_options();
		Oli_OCC_Checkout_Captcha::get_instance();
	}
	add_action( 'plugins_loaded', 'oli_occ_init' );
}

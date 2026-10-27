<?php
/**
 * Plugin Name: Oli Checkout Captcha
 * Plugin URI: https://github.com/olivierbigras/Oli-Checkout-Captcha
 * Description: Blocks bots and card testing at WooCommerce checkout with Google reCAPTCHA v2/v3 or Cloudflare Turnstile. Works with classic and block checkout.
 * Version: 1.2.5
 * Author: Olivier Bigras
 * Author URI: https://github.com/olivierbigras
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * WC requires at least: 8.0
 * WC tested up to: 11.2
 * Text Domain: oli-checkout-captcha
 * Domain Path: /languages
 *
 * @package Olic_Checkout_Captcha
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'OLIC_PLUGIN_LOADED' ) ) {
	define( 'OLIC_PLUGIN_LOADED', true );
	define( 'OLIC_VERSION', '1.2.5' );
	define( 'OLIC_PLUGIN_FILE', __FILE__ );
	define( 'OLIC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
	define( 'OLIC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

	require_once OLIC_PLUGIN_DIR . 'includes/olic-upgrade.php';
	require_once OLIC_PLUGIN_DIR . 'includes/class-olic-verifier.php';
	require_once OLIC_PLUGIN_DIR . 'includes/class-olic-rate-limiter.php';
	require_once OLIC_PLUGIN_DIR . 'includes/class-olic-checkout-captcha.php';

	/**
	 * Run activation tasks, including option migration.
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	function olic_activate() {
		olic_maybe_migrate_options();
	}
	register_activation_hook( __FILE__, 'olic_activate' );

	/**
	 * Boot the plugin after all plugins are loaded.
	 *
	 * Translations are loaded automatically by WordPress.org (WP 4.6+).
	 *
	 * @since 1.1.0
	 *
	 * @return void
	 */
	function olic_init() {
		olic_maybe_migrate_options();
		Olic_Checkout_Captcha::get_instance();
	}
	add_action( 'plugins_loaded', 'olic_init' );
}

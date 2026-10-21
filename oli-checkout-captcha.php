<?php
/**
 * Plugin Name: Oli Checkout Captcha
 * Plugin URI: https://github.com/olivierbigras/Oli-Checkout-Captcha
 * Description: Adds Google reCAPTCHA v2 protection to WooCommerce block-based checkout to prevent spam orders.
 * Version: 1.1.0
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

define( 'OLIC_VERSION', '1.1.0' );
define( 'OLIC_PLUGIN_FILE', __FILE__ );
define( 'OLIC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'OLIC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once OLIC_PLUGIN_DIR . 'includes/class-olic-checkout-captcha.php';

/**
 * Map of legacy option names to the prefixed olic_ options.
 *
 * @since 1.1.0
 *
 * @return array<string, string>
 */
function olic_get_legacy_option_map() {
	return array(
		'rbc_site_key'   => 'olic_site_key',
		'rbc_secret_key' => 'olic_secret_key',
		'rbc_theme'      => 'olic_theme',
	);
}

/**
 * Migrate options from the previous rbc_ prefix and plugin slug.
 *
 * @since 1.1.0
 *
 * @return void
 */
function olic_maybe_migrate_options() {
	foreach ( olic_get_legacy_option_map() as $legacy_name => $new_name ) {
		if ( false !== get_option( $new_name, false ) ) {
			continue;
		}

		$legacy_value = get_option( $legacy_name, false );
		if ( false === $legacy_value ) {
			continue;
		}

		add_option( $new_name, $legacy_value );
		delete_option( $legacy_name );
	}
}

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
 * @since 1.1.0
 *
 * @return void
 */
function olic_init() {
	olic_maybe_migrate_options();
	Olic_Checkout_Captcha::get_instance();
}
add_action( 'plugins_loaded', 'olic_init' );

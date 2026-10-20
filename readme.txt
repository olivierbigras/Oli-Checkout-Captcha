=== reCAPTCHA for WooCommerce Block Checkout ===
Contributors: olivierbigras
Tags: recaptcha, woocommerce, checkout, block checkout, spam protection
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Adds Google reCAPTCHA v2 protection to WooCommerce block-based checkout to prevent spam orders.

== Description ==

**reCAPTCHA for WooCommerce Block Checkout** adds Google reCAPTCHA v2 ("I'm not a robot" checkbox) to your WooCommerce block-based checkout page.

This plugin is specifically designed for the new WooCommerce Block Checkout, which uses a React-based interface that traditional reCAPTCHA plugins don't support properly.

= Features =

* Works with WooCommerce Block Checkout
* Google reCAPTCHA v2 Checkbox ("I'm not a robot")
* Light and Dark theme options
* Seamless integration with WooCommerce Store API
* HPOS (High-Performance Order Storage) compatible
* Lightweight and fast

= Requirements =

* WordPress 6.0 or higher
* WooCommerce 8.0 or higher
* PHP 7.4 or higher
* Google reCAPTCHA v2 API keys

= How to Get reCAPTCHA Keys =

1. Go to [Google reCAPTCHA Admin](https://www.google.com/recaptcha/admin/create)
2. Register a new site
3. Choose **reCAPTCHA v2** → **"I'm not a robot" Checkbox**
4. Add your domain(s)
5. Copy the Site Key and Secret Key

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/recaptcha-block-checkout/` or install via the WordPress plugin screen.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Go to **Settings → reCAPTCHA Checkout** to configure.
4. Enter your Google reCAPTCHA v2 Site Key and Secret Key.
5. Save changes.

== Frequently Asked Questions ==

= Does this work with the classic WooCommerce checkout? =

No, this plugin is specifically designed for the WooCommerce Block Checkout. For the classic shortcode-based checkout, use a different reCAPTCHA plugin.

= Which reCAPTCHA version is supported? =

This plugin supports **reCAPTCHA v2 Checkbox** ("I'm not a robot"). reCAPTCHA v3 is not currently supported.

= Is this compatible with WooCommerce HPOS? =

Yes, this plugin is fully compatible with WooCommerce High-Performance Order Storage (HPOS).

= The reCAPTCHA doesn't appear on checkout =

1. Make sure you're using the WooCommerce Block Checkout, not the classic shortcode checkout.
2. Verify your API keys are correct.
3. Clear any caching plugins (WP Rocket, W3 Total Cache, etc.).
4. Check browser console for JavaScript errors.

= Can I customize the position of the reCAPTCHA? =

Currently, the reCAPTCHA appears after the payment methods section. Custom positioning may be added in future versions.

== Screenshots ==

1. Settings page
2. reCAPTCHA on block checkout

== Changelog ==

= 1.0.0 =
* Initial release
* Google reCAPTCHA v2 support
* WooCommerce Block Checkout integration
* Light and Dark theme options
* HPOS compatibility

== Upgrade Notice ==

= 1.0.0 =
Initial release.

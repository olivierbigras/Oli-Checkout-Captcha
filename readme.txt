=== Oli Checkout Captcha ===
Contributors: olivierbigras
Tags: recaptcha, woocommerce, checkout, block checkout, spam protection
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.1.1
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Adds Google reCAPTCHA v2 protection to WooCommerce block-based checkout to prevent spam orders.

== Description ==

**Oli Checkout Captcha** adds Google reCAPTCHA v2 ("I'm not a robot" checkbox) to your WooCommerce block-based checkout page.

This plugin is specifically designed for the WooCommerce Block Checkout, which uses a React-based interface that traditional reCAPTCHA plugins do not support properly.

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

1. Upload the plugin files to `/wp-content/plugins/oli-checkout-captcha/` or install via the WordPress plugin screen.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Go to **Settings → Oli Checkout Captcha** to configure.
4. Enter your Google reCAPTCHA v2 Site Key and Secret Key.
5. Save changes.

Existing sites that used the previous plugin slug or `rbc_` options are migrated automatically to the `olic_` options on `plugins_loaded`, without requiring a reactivation. In-place updates that keep the previous folder name continue to load through a legacy bootstrap file.

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

= What data is sent to Google? =

See the **External services** section below for what is sent, when it is sent, and links to Google's terms and privacy policy.

== External services ==

This plugin relies on Google reCAPTCHA, a service operated by Google LLC, to tell human shoppers from automated checkout spam.

= Google reCAPTCHA widget script =

* What it is used for: loads the "I'm not a robot" checkbox on the WooCommerce block checkout so the shopper can complete the challenge.
* When: only on the checkout page, and only after a Site Key and Secret Key have been saved in **Settings → Oli Checkout Captcha**.
* What is sent: the shopper's browser requests `https://www.google.com/recaptcha/api.js`. Google may receive the visitor IP address, user agent, and interaction data needed to render and score the widget. This plugin does not send order contents or customer account data with that script request.
* Service: [Google reCAPTCHA](https://www.google.com/recaptcha/about/)
* Terms of use: [https://policies.google.com/terms](https://policies.google.com/terms) and [https://www.google.com/recaptcha/about/](https://www.google.com/recaptcha/about/)
* Privacy policy: [https://policies.google.com/privacy](https://policies.google.com/privacy)

= Google reCAPTCHA siteverify API =

* What it is used for: server-side confirmation that the checkout request includes a valid reCAPTCHA token before WooCommerce creates the order.
* When: each time a shopper submits the WooCommerce block checkout (Store API POST) while the plugin keys are configured.
* What is sent: the plugin posts the reCAPTCHA response token, your Secret Key, and (when available) the sanitized visitor IP address (`REMOTE_ADDR`) to `https://www.google.com/recaptcha/api/siteverify`. Order line items, customer names, and emails are not included in that request.
* Service: [Google reCAPTCHA](https://www.google.com/recaptcha/about/)
* Terms of use: [https://policies.google.com/terms](https://policies.google.com/terms) and [https://www.google.com/recaptcha/about/](https://www.google.com/recaptcha/about/)
* Privacy policy: [https://policies.google.com/privacy](https://policies.google.com/privacy)

You are responsible for mentioning Google reCAPTCHA in your own site privacy policy when this plugin is active.

== Screenshots ==

1. Settings page
2. reCAPTCHA on block checkout

== Changelog ==

= 1.1.1 =
* Kept a legacy bootstrap file so in-place updates from recaptcha-block-checkout.php stay active.
* Rewrites only the stored active plugin path for this plugin, using the raw option value.
* Migrates rbc_ options to olic_ on plugins_loaded with a version marker, without reactivation.

= 1.1.0 =
* Renamed the plugin to Oli Checkout Captcha (slug and text domain: oli-checkout-captcha).
* Prefixed functions, options, and classes with olic (4+ characters).
* Migrated existing rbc_ options to olic_ options.
* Added capability checks on settings registration and the settings screen.
* Sanitized all input and escaped all output.
* Moved checkout styles into an enqueued CSS file (no inline CSS or JavaScript).
* Documented Google reCAPTCHA in the External services section.
* Updated Tested up to to WordPress 7.1 and WooCommerce 11.2.

= 1.0.0 =
* Initial release
* Google reCAPTCHA v2 support
* WooCommerce Block Checkout integration
* Light and Dark theme options
* HPOS compatibility

== Upgrade Notice ==

= 1.1.1 =
Keeps checkout protection active during in-place updates from the previous main file and migrates settings without reactivation.

= 1.1.0 =
Renames the plugin to Oli Checkout Captcha and migrates existing settings. Update recommended before submitting to WordPress.org.

= 1.0.0 =
Initial release.

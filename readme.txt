=== Oli Checkout Captcha – reCAPTCHA v2 for WooCommerce Block Checkout ===
Contributors: bigrat95
Tags: checkout, captcha, spam, recaptcha, woocommerce
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Adds Google reCAPTCHA v2 (“I'm not a robot”) to WooCommerce block checkout to block spam orders.

== Description ==

Oli Checkout Captcha adds Google reCAPTCHA v2 ("I'm not a robot" checkbox) to the WooCommerce block-based checkout.

The WooCommerce block checkout is a React interface. Classic-checkout captcha plugins do not hook its Store API, so spam orders can still get through. This plugin renders the widget on the block checkout and verifies the token on the server before the order is created.

= Features =

* Works with WooCommerce Block Checkout
* Google reCAPTCHA v2 Checkbox ("I'm not a robot")
* Light and Dark theme options
* Server-side verification via the WooCommerce Store API
* HPOS (High-Performance Order Storage) compatible
* Lightweight; no keys are hard-coded in the plugin

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

= External services =

This plugin connects to the **Google reCAPTCHA** service to decide whether a checkout submission is likely from a human.

* What is sent: the reCAPTCHA response token from the shopper's browser, your reCAPTCHA secret key, and the shopper's IP address (`remoteip`).
* When: only when a customer places an order on the WooCommerce block checkout and the plugin keys are configured.
* Scripts loaded: `https://www.google.com/recaptcha/api.js` on the checkout page.
* Verification endpoint: `https://www.google.com/recaptcha/api/siteverify` (via `wp_remote_post`).
* Service site: [https://www.google.com/recaptcha/](https://www.google.com/recaptcha/)
* Terms of Service: [https://policies.google.com/terms](https://policies.google.com/terms)
* Privacy Policy: [https://policies.google.com/privacy](https://policies.google.com/privacy)

Google's terms and privacy policy apply to data processed by reCAPTCHA. The secret key is stored in the WordPress options table and is never printed in storefront HTML or JavaScript.

= Privacy =

Checkout visitors load Google's reCAPTCHA script and complete the challenge. Google may set cookies and collect device and interaction data as described in their privacy policy. This plugin does not send the token to any other third party.

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/oli-checkout-captcha/` or install via the WordPress plugin screen.
2. Activate the plugin through the Plugins screen in WordPress.
3. Go to **Settings → Oli Checkout Captcha**.
4. Enter your Google reCAPTCHA v2 Site Key and Secret Key.
5. Save changes.

= Upgrading from 1.0.0 ("reCAPTCHA for WooCommerce Block Checkout") =

Version 1.1.0 reads and copies the old option names (`rbc_site_key`, `rbc_secret_key`, `rbc_theme`) into `oli_cc_*` automatically. You do not need to re-enter keys.

If you replace files inside the old `recaptcha-block-checkout` folder, a legacy bootstrap keeps that active path working. Prefer activating `oli-checkout-captcha` and removing the old folder when convenient.

== Frequently Asked Questions ==

= Does this work with the classic WooCommerce checkout? =

No. This plugin is built for the WooCommerce Block Checkout. For the classic shortcode checkout, use a different captcha plugin.

= Which reCAPTCHA version is supported? =

reCAPTCHA v2 Checkbox ("I'm not a robot") only. reCAPTCHA v3 is not supported.

= Is this compatible with WooCommerce HPOS? =

Yes. The plugin declares compatibility with High-Performance Order Storage (HPOS).

= The reCAPTCHA doesn't appear on checkout =

1. Confirm you are using the WooCommerce Block Checkout, not the classic shortcode checkout.
2. Verify the API keys on Settings → Oli Checkout Captcha.
3. Clear page/cache plugins.
4. Check the browser console for JavaScript errors.

= Can I customize the position of the reCAPTCHA? =

The widget is inserted after the payment methods section. Custom positioning may be added in a future version.

= Where should I put the secret key? =

Only in the plugin settings screen. Never commit secret keys to the plugin code or a public repository.

== Changelog ==

= 1.1.0 =
* Renamed for wordpress.org trademark rules (slug `oli-checkout-captcha`, prefix `oli_cc`).
* Migrates 1.0.0 `rbc_*` settings automatically.
* Server-side token verification uses `wp_remote_post` with sanitized input and escaped errors.
* Settings API nonces, capability checks, and stricter sanitization/escaping.
* Full GPLv2+ license, uninstall cleanup (old and new option names), and i18n text domain update.
* Documents the Google reCAPTCHA external service, terms, and privacy policy.

= 1.0.0 =
* Initial release
* Google reCAPTCHA v2 support
* WooCommerce Block Checkout integration
* Light and Dark theme options
* HPOS compatibility

== Upgrade Notice ==

= 1.1.0 =
Renames the plugin for wordpress.org. Existing 1.0.0 keys (`rbc_*`) are migrated automatically. After install, activate Oli Checkout Captcha if WordPress lists it as a new plugin.

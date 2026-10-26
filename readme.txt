=== Oli Checkout Captcha ===
Contributors: bigrat95
Tags: recaptcha, turnstile, woocommerce, checkout, spam
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.2.4
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Blocks bots and card testing at WooCommerce checkout with Google reCAPTCHA v2/v3 or Cloudflare Turnstile. Works with classic and block checkout.

== Description ==

**Oli Checkout Captcha** protects WooCommerce checkout from bots and card testing.

Choose one captcha provider:

* Google reCAPTCHA v2 ("I'm not a robot" checkbox)
* Google reCAPTCHA v3 (invisible score)
* Cloudflare Turnstile

The plugin works with both the classic shortcode checkout and the WooCommerce checkout block (Store API). Provider scripts load only on the checkout page. Express payment buttons (Apple Pay, Google Pay, Payment Request) are not intercepted.

Developed by Olivier Bigras. Support: oli@olivierbigras.com

This plugin does not track shoppers and does not send analytics or telemetry.

= Features =

* Classic checkout and block checkout
* Google reCAPTCHA v2, reCAPTCHA v3, and optional Cloudflare Turnstile
* Server-side token verification on checkout, including Store API requests
* Configurable reCAPTCHA v3 score threshold
* Fail-closed option when the provider cannot be reached
* Optional rate limiting of failed verification attempts
* Scripts load only on the checkout page
* Does not attach to express payment buttons
* HPOS (High-Performance Order Storage) compatible
* No tracking

= Requirements =

* WordPress 6.0 or higher
* WooCommerce 8.0 or higher
* PHP 7.4 or higher
* API keys from Google reCAPTCHA and/or Cloudflare Turnstile

= How to Get Keys =

1. reCAPTCHA: [Google reCAPTCHA Admin](https://www.google.com/recaptcha/admin/create)
2. Turnstile: [Cloudflare Turnstile](https://dash.cloudflare.com/?to=/:account/turnstile)
3. Copy the Site Key and Secret Key for the provider you want to use

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/oli-checkout-captcha/` or install via the WordPress plugin screen.
2. Activate the plugin through the Plugins screen in WordPress.
3. Go to **Settings → Oli Checkout Captcha**.
4. Choose a provider and enter the Site Key and Secret Key.
5. Optionally set the reCAPTCHA v3 score threshold, fail-closed behavior, and failed-attempt rate limiting.
6. Save changes.

Existing sites that used the previous plugin slug or `rbc_` options are migrated automatically to the `olic_` options on `plugins_loaded`, without requiring a reactivation. In-place updates that keep the previous folder name continue to load through a legacy bootstrap file.

== Frequently Asked Questions ==

= Does this work with classic and block checkout? =

Yes. Classic shortcode checkout is validated on `woocommerce_checkout_process`. Block checkout is validated on the WooCommerce Store API place-order request.

= Which captcha versions are supported? =

Google reCAPTCHA v2 checkbox, Google reCAPTCHA v3, and Cloudflare Turnstile.

= Will this block Apple Pay or Google Pay? =

No. The widget is not injected into express payment areas, and server-side verification is skipped for recognized express/wallet payments.

= Where are scripts loaded? =

Only on the WooCommerce checkout page, and only after both API keys are saved. Scripts are not loaded on the order-pay or order-received pages.

= What is the score threshold? =

For reCAPTCHA v3, Google returns a score from 0.0 to 1.0. Checkout is rejected when the score is below the threshold (default 0.5).

= What does fail closed mean? =

When enabled (default), checkout is blocked if Google or Cloudflare cannot be reached. When disabled, a provider outage allows checkout to continue.

= Does this plugin track users? =

No. The only outbound requests are the documented captcha provider scripts and siteverify calls.

= Is this compatible with WooCommerce HPOS? =

Yes.

= What data is sent to Google or Cloudflare? =

See the **External services** section below.

== External services ==

This plugin can use Google reCAPTCHA and/or Cloudflare Turnstile, depending on the provider selected in **Settings → Oli Checkout Captcha**. Only the selected provider is contacted. The plugin does not contact any other third-party service and does not include tracking.

= Google reCAPTCHA widget script =

* What it is used for: loads the reCAPTCHA v2 checkbox or the reCAPTCHA v3 script on checkout so the shopper can be challenged or scored.
* When: only on the checkout page, after a Site Key and Secret Key have been saved, and only when a reCAPTCHA provider is selected.
* What is sent: the shopper's browser requests `https://www.google.com/recaptcha/api.js`. Google may receive the visitor IP address, user agent, and interaction data needed to render and score the widget. This plugin does not send order contents or customer account data with that script request.
* Service: [Google reCAPTCHA](https://www.google.com/recaptcha/about/)
* Terms of use: [https://policies.google.com/terms](https://policies.google.com/terms) and [https://www.google.com/recaptcha/about/](https://www.google.com/recaptcha/about/)
* Privacy policy: [https://policies.google.com/privacy](https://policies.google.com/privacy)

= Google reCAPTCHA siteverify API =

* What it is used for: server-side confirmation that the checkout request includes a valid reCAPTCHA token before WooCommerce creates the order.
* When: each time a shopper submits classic checkout or the WooCommerce block checkout (Store API POST) while reCAPTCHA keys are configured.
* What is sent: the plugin posts the reCAPTCHA response token, your Secret Key, and (when available) the sanitized visitor IP address (`REMOTE_ADDR`) to `https://www.google.com/recaptcha/api/siteverify`. Order line items, customer names, and emails are not included in that request.
* Service: [Google reCAPTCHA](https://www.google.com/recaptcha/about/)
* Terms of use: [https://policies.google.com/terms](https://policies.google.com/terms) and [https://www.google.com/recaptcha/about/](https://www.google.com/recaptcha/about/)
* Privacy policy: [https://policies.google.com/privacy](https://policies.google.com/privacy)

= Cloudflare Turnstile widget script =

* What it is used for: loads the Turnstile widget on checkout so the shopper can complete the challenge.
* When: only on the checkout page, after a Site Key and Secret Key have been saved, and only when Turnstile is the selected provider.
* What is sent: the shopper's browser requests `https://challenges.cloudflare.com/turnstile/v0/api.js`. Cloudflare may receive the visitor IP address, user agent, and interaction data needed to render the widget. This plugin does not send order contents or customer account data with that script request.
* Service: [Cloudflare Turnstile](https://www.cloudflare.com/products/turnstile/)
* Terms of use: [https://www.cloudflare.com/website-terms/](https://www.cloudflare.com/website-terms/)
* Privacy policy: [https://www.cloudflare.com/privacypolicy/](https://www.cloudflare.com/privacypolicy/)
* Turnstile privacy addendum: [https://www.cloudflare.com/turnstile-privacy-policy/](https://www.cloudflare.com/turnstile-privacy-policy/)

= Cloudflare Turnstile siteverify API =

* What it is used for: server-side confirmation that the checkout request includes a valid Turnstile token before WooCommerce creates the order.
* When: each time a shopper submits classic checkout or the WooCommerce block checkout (Store API POST) while Turnstile is selected and keys are configured.
* What is sent: the plugin posts the Turnstile response token, your Secret Key, and (when available) the sanitized visitor IP address (`REMOTE_ADDR`) to `https://challenges.cloudflare.com/turnstile/v0/siteverify`. Order line items, customer names, and emails are not included in that request.
* Service: [Cloudflare Turnstile](https://www.cloudflare.com/products/turnstile/)
* Terms of use: [https://www.cloudflare.com/website-terms/](https://www.cloudflare.com/website-terms/)
* Privacy policy: [https://www.cloudflare.com/privacypolicy/](https://www.cloudflare.com/privacypolicy/)
* Turnstile privacy addendum: [https://www.cloudflare.com/turnstile-privacy-policy/](https://www.cloudflare.com/turnstile-privacy-policy/)

You are responsible for mentioning the selected captcha provider in your own site privacy policy when this plugin is active.

== Screenshots ==

1. Settings page
2. Captcha on checkout
3. Captcha on the block checkout.
4. Checkout blocked with "Please complete the captcha verification." when the captcha is not completed.

== Changelog ==

= 1.2.4 =
* Send the block-checkout captcha token only when the order is placed, so WooCommerce order-attribution does not warn about wc.wcBlocksData on every page load.
* Enqueue Google reCAPTCHA api.js without a ?ver= query argument.
* Ship a complete languages/oli-checkout-captcha.pot generated with wp i18n make-pot.

= 1.2.3 =
* Use WooCommerce's public setExtensionData on block checkout, with a fallback to __internalSetExtensionData on older WooCommerce.
* Declare wp-data and wc-blocks-data-store as script dependencies on block checkout only when those handles are registered.
* Enqueue Cloudflare Turnstile api.js without a ?ver= query argument.
* Document four screenshots in the readme.

= 1.2.2 =
* Return HTTP 400 (not 500) when captcha verification fails on block / Store API checkout.

= 1.2.1 =
* Re-render reCAPTCHA and Turnstile after WooCommerce classic checkout replaces the payment fragment.
* Reset the captcha token after a classic checkout error so a fresh token can be submitted.

= 1.2.0 =
* Added classic checkout support alongside the checkout block.
* Added reCAPTCHA v3 and optional Cloudflare Turnstile.
* Added server-side verification for classic checkout and Store API place-order requests.
* Added a reCAPTCHA v3 score threshold setting.
* Added a fail-closed option for provider outages.
* Added optional rate limiting of failed verification attempts.
* Load provider scripts only on the checkout page.
* Skip captcha for express payment buttons.
* Removed load_plugin_textdomain() (WordPress.org loads translations automatically).
* Admin notices now appear only on the plugin settings screen.

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

= 1.2.4 =
Sends the block checkout captcha token only when placing the order, avoiding a WooCommerce wc.wcBlocksData console warning on page load.

= 1.2.3 =
Uses the public WooCommerce checkout store API, declares block checkout script dependencies only when registered, and loads Turnstile without a version query argument.

= 1.2.2 =
Returns HTTP 400 instead of 500 when captcha verification fails on the checkout block.

= 1.2.1 =
Fixes an empty captcha widget on classic shortcode checkout after WooCommerce refreshes the payment section.

= 1.2.0 =
Adds classic checkout, reCAPTCHA v3, Turnstile, score threshold, fail-closed, and optional rate limiting.

= 1.1.1 =
Keeps checkout protection active during in-place updates from the previous main file and migrates settings without reactivation.

= 1.1.0 =
Renames the plugin to Oli Checkout Captcha and migrates existing settings. Update recommended before submitting to WordPress.org.

= 1.0.0 =
Initial release.

# Changelog

All notable changes to Oli Checkout Captcha are documented here.

## 1.1.0 – 2026-10-20

Prepared the 1.0.0 Dimex plugin for wordpress.org. Display name no longer starts with “reCAPTCHA” or “WooCommerce”.

### Security

- Admin settings use the Settings API (`settings_fields()` nonce, `manage_options`).
- Input is sanitized (`sanitize_text_field`, theme whitelist). Output is escaped.
- Checkout tokens are verified server-side with `wp_remote_post()` to Google’s siteverify API.
- Visitor IP is sanitized before it is sent as `remoteip`.
- Secret key stays in `wp_options` and is never localized to JavaScript.
- No API secrets are shipped in the plugin code.

### WordPress.org / coding

- Slug and text domain: `oli-checkout-captcha`. Function/option prefix: `oli_cc`.
- Display name: **Oli Checkout Captcha – reCAPTCHA v2 for WooCommerce Block Checkout**.
- Author Olivier Bigras, Author URI https://olivierbigras.com, Plugin URI https://github.com/olivierbigras/Oli-Checkout-Captcha, contributor `bigrat95`.
- `readme.txt` in wordpress.org format, including the required Google reCAPTCHA external-service / privacy / terms documentation.
- Full GNU GPLv2 (or later) `LICENSE`.
- `uninstall.php` deletes both `oli_cc_*` and legacy `rbc_*` options.
- Translation template: `languages/oli-checkout-captcha.pot`.

### Migration (Dimex / 1.0.0)

- Reads `rbc_site_key`, `rbc_secret_key`, and `rbc_theme` when the new options are not stored yet.
- Copies those values into `oli_cc_site_key`, `oli_cc_secret_key`, and `oli_cc_theme`.
- Still accepts a Store API token posted under the old `recaptcha-block-checkout` namespace (cached checkout.js).
- `recaptcha-block-checkout.php` remains as a header-less bootstrap so an in-place overwrite of the old plugin folder keeps loading.

### Other

- Admin notice if WooCommerce is not active.
- Settings screen title is **Oli Checkout Captcha** (no longer Settings → reCAPTCHA Checkout).
- GitHub Actions: `php -l` on PHP 7.4–8.3 and WordPress Plugin Check.

## 1.0.0 – 2026-03-23

Initial release as “reCAPTCHA for WooCommerce Block Checkout”:

- Google reCAPTCHA v2 checkbox on WooCommerce block checkout
- Light and Dark theme options
- Store API validation
- HPOS compatibility

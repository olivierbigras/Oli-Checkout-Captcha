# Oli Checkout Captcha

WordPress plugin: **Oli Checkout Captcha – reCAPTCHA v2 for WooCommerce Block Checkout**.

Adds Google reCAPTCHA v2 (“I'm not a robot”) to the WooCommerce block checkout and verifies the token on the server with `wp_remote_post()`.

- **Author:** [Olivier Bigras](https://olivierbigras.com)
- **WordPress.org contributor:** `bigrat95`
- **License:** GPLv2 or later
- **Requires:** WordPress 6.0+, PHP 7.4+, WooCommerce 8.0+

This repository is the source for the plugin. It is not published to wordpress.org from this repo automatically.

## Install

1. Copy the plugin folder to `wp-content/plugins/oli-checkout-captcha`.
2. Activate **Oli Checkout Captcha**.
3. Open **Settings → Oli Checkout Captcha** and enter reCAPTCHA v2 keys from [Google reCAPTCHA Admin](https://www.google.com/recaptcha/admin/create).

## Upgrading from 1.0.0

Version 1.0.0 shipped as `recaptcha-block-checkout` with options `rbc_site_key`, `rbc_secret_key`, and `rbc_theme` (the build running on dimex360.com).

1.1.0 reads those options and copies them to `oli_cc_*` on load. Keys do not need to be re-entered.

If WordPress lists a new plugin after the folder/file rename, activate **Oli Checkout Captcha**. A legacy `recaptcha-block-checkout.php` bootstrap is included so an in-place overwrite of the old folder still loads.

## External service

Checkout uses Google reCAPTCHA (`api.js` and `siteverify`). See `readme.txt` for data sent, timing, [Terms](https://policies.google.com/terms), and [Privacy Policy](https://policies.google.com/privacy).

## Development

GitHub Actions run `php -l` on PHP 7.4–8.3 and [WordPress Plugin Check](https://github.com/WordPress/plugin-check-action).

# Changelog

All notable changes to this project are documented in this file and in `readme.txt`.

## 1.1.1 - 2026-10-21

### Fixed

- Restored a legacy bootstrap file (`recaptcha-block-checkout.php`) without a plugin header so in-place updates that keep the previous folder stay active.
- Rewrites only this plugin's entry in `active_plugins` and `active_sitewide_plugins`, reading the raw option value rather than the filtered value.
- Runs `rbc_*` to `olic_*` option migration on `plugins_loaded` with an `olic_db_version` marker, without requiring reactivation.

## 1.1.0 - 2026-10-21

### Changed

- Renamed the plugin to Oli Checkout Captcha (slug and text domain: `oli-checkout-captcha`).
- Prefixed functions, options, and classes with `olic` (at least four characters).
- Moved checkout styles to `css/checkout.css` and enqueue them with `wp_enqueue_style` (no inline CSS or JavaScript).
- Updated WordPress "Tested up to" to 7.1 and WooCommerce "tested up to" to 11.2.

### Security

- Capability checks (`manage_options`) on the settings screen, settings registration, and sanitization callbacks.
- Settings API nonces via `settings_fields()`.
- Sanitization of all input and escaping of all output, including every `echo`.
- Sanitized visitor IP before it is sent to Google reCAPTCHA siteverify.

### Added

- Migration of existing `rbc_*` options to `olic_*` on activation and `plugins_loaded`.
- `Requires PHP: 7.4` in the plugin header and readme.
- readme "External services" section describing Google reCAPTCHA data flows, timing, terms, and privacy policy.

## 1.0.0

- Initial release.
- Google reCAPTCHA v2 support.
- WooCommerce Block Checkout integration.
- Light and Dark theme options.
- HPOS compatibility.

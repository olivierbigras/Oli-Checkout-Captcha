# Oli Checkout Captcha

WordPress plugin that adds Google reCAPTCHA v2 ("I'm not a robot") to the WooCommerce block checkout.

- Plugin slug / text domain: `oli-checkout-captcha`
- Requires WordPress 6.0+, PHP 7.4+, and WooCommerce 8.0+
- Repository: [github.com/olivierbigras/Oli-Checkout-Captcha](https://github.com/olivierbigras/Oli-Checkout-Captcha)

## Setup

1. Install and activate the plugin.
2. Open **Settings → Oli Checkout Captcha**.
3. Enter Google reCAPTCHA v2 Site Key and Secret Key.
4. Save changes.

Existing `rbc_*` options from earlier versions are migrated automatically to `olic_*` on `plugins_loaded`. In-place updates that keep the previous folder name stay active through `recaptcha-block-checkout.php`.

## Development

```bash
php -l oli-checkout-captcha.php
php -l recaptcha-block-checkout.php
php -l includes/olic-upgrade.php
php -l includes/class-olic-checkout-captcha.php
php -l uninstall.php
```

If [Composer](https://getcomposer.org/) dependencies are installed:

```bash
composer phpcs
composer test
```

## License

GPL-2.0-or-later. See `LICENSE`.

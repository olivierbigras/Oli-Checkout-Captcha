# Oli Checkout Captcha

WordPress plugin that blocks bots and card testing at WooCommerce checkout with Google reCAPTCHA v2/v3 or Cloudflare Turnstile. Works with classic and block checkout.

- Plugin slug / text domain: `oli-checkout-captcha`
- Requires WordPress 6.0+, PHP 7.4+, and WooCommerce 8.0+
- Author: Olivier Bigras — [github.com/olivierbigras](https://github.com/olivierbigras) — oli@olivierbigras.com
- Repository: [github.com/olivierbigras/Oli-Checkout-Captcha](https://github.com/olivierbigras/Oli-Checkout-Captcha)

## Setup

1. Install and activate the plugin.
2. Open **Settings → Oli Checkout Captcha**.
3. Choose Google reCAPTCHA v2, reCAPTCHA v3, or Cloudflare Turnstile.
4. Enter the Site Key and Secret Key.
5. Optionally set the v3 score threshold, fail-closed behavior, and failed-attempt rate limiting.
6. Save changes.

Existing `rbc_*` options from earlier versions are migrated automatically to `oli_occ_*` on `plugins_loaded`. In-place updates that keep the previous folder name stay active through `recaptcha-block-checkout.php`.

This plugin does not track shoppers.

## Development

```bash
composer install
composer phpcs
composer test
```

## License

GPL-2.0-or-later. See `LICENSE`.

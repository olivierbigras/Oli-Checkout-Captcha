# Changelog

All notable changes to this project are documented in this file and in `readme.txt`.

## 1.2.7 - 2026-10-27

### Corrigé

- Le filtre de capacité de la page de réglages est accroché à `option_page_capability_oli_occ_settings` (groupe `oli_occ_settings`). En 1.2.6, il restait sur `option_page_capability_olic_settings`, que WordPress ne déclenche plus depuis le renommage du groupe : le rappel de capacité du plugin ne s'appliquait plus à l'enregistrement (repli sur le contrôle par défaut d'`options.php`, `manage_options`). Aucun alias : c'est un filtre du cœur dont le nom suit le groupe de réglages, l'ancien nom ne se déclenche jamais.

### Tests

- `LegacyPrefixTest` : échoue si `olic` apparaît hors des exceptions documentées (alias dépréciés des deux filtres publics, migration, désinstallation, sel `'olic|'` du limiteur, historique du readme et du CHANGELOG) ; vérifie que le filtre de capacité suit le groupe de réglages.

## 1.2.6 - 2026-10-27

### Modifié

- Préfixe interne renommé de `olic` à `oli_occ` (fonctions, classes, constantes, options, transients, hooks, handles de scripts, noms de champs et globales JavaScript). Fichiers renommés : `includes/class-oli-occ-*.php` et `includes/oli-occ-upgrade.php`.
- Filtres renommés : `oli_occ_skip_express_payments`, `oli_occ_express_payment_ids`. Anciens noms `olic_*` dépréciés, toujours honorés (appliqués avant les nouveaux via `apply_filters_deprecated`, seulement s'ils ont un callback).
- Code d'erreur Store API du captcha : `oli_occ_captcha_failed` (anciennement `olic_captcha_failed`).

### Ajouté

- Migration unique (option `oli_occ_db_version`) à l'activation ou à la mise à jour : copie des options `olic_*` et des compteurs `olic_rl_*` (avec leur durée restante) vers `oli_occ_*`, sans écraser une valeur existante ; les anciennes lignes ne sont supprimées qu'après vérification de la copie. Lecture de repli sur l'ancienne option tant qu'elle existe.
- Tests PHPUnit de la migration, de la vérification du nonce et des alias de filtres dépréciés.

### Sécurité

- Le paiement classique ne lit `$_POST` qu'après vérification du nonce WooCommerce `woocommerce-process_checkout`.
- Requêtes SQL avec `%i` pour les identifiants ; la désinstallation supprime les anciens et les nouveaux noms.
- WordPress 6.2 minimum (`%i`), déjà exigé par WooCommerce 8.0.

## 1.2.5 - 2026-10-26

### Corrigé

- La désinstallation supprime désormais les transients de limitation de débit.

## 1.2.4 - 2026-10-26

### Fixed

- Keep the captcha token in memory and the classic hidden field; write it to the Store API only when the order is placed (`wp.apiFetch` checkout POST, with an `isBeforeProcessing` + `setExtensionData` fallback). This stops WooCommerce order-attribution from accessing `wc.wcBlocksData` on every token refresh.
- Enqueue Google reCAPTCHA `api.js` with a `null` version so WordPress does not append `?ver=`.
- Regenerate `languages/oli-checkout-captcha.pot` with WP-CLI `i18n make-pot` so the release ZIP ships the plugin's translatable strings.

## 1.2.3 - 2026-10-26

### Fixed

- Use WooCommerce's public `setExtensionData` on block checkout, falling back to `__internalSetExtensionData` on older WooCommerce.
- Declare `wp-data` and `wc-blocks-data-store` as `olic-checkout` dependencies on block checkout only when those handles are registered, so classic checkout still loads without WooCommerce Blocks.
- Enqueue Cloudflare Turnstile `api.js` with a `null` version so WordPress does not append `?ver=`.

### Changed

- readme.txt now lists four screenshot captions.

## 1.2.2 - 2026-10-26

### Fixed

- Return HTTP 400 via the WooCommerce Store API `RouteException` when captcha verification fails on block checkout, instead of a generic 500.

## 1.2.1 - 2026-10-26

### Fixed

- Re-render the reCAPTCHA and Turnstile widgets after WooCommerce classic checkout replaces the payment fragment (`updated_checkout`).
- Reset the captcha token after a classic checkout error so a fresh token can be submitted.

## 1.2.0 - 2026-10-26

### Added

- Classic shortcode checkout validation alongside WooCommerce block / Store API checkout.
- Google reCAPTCHA v3 and optional Cloudflare Turnstile.
- Server-side token verification for classic checkout and Store API place-order requests.
- reCAPTCHA v3 score threshold setting.
- Fail-closed option when the captcha provider cannot be reached.
- Optional rate limiting of failed verification attempts (hashed IP, transients).
- PHPUnit coverage for token verification with HTTP mocked.

### Changed

- Provider scripts load only on the checkout page (not order-pay or order-received).
- Express / wallet payments are not intercepted by the captcha widget or server check.
- Admin notices appear only on the plugin settings screen.
- Removed `load_plugin_textdomain()` so WordPress.org can load translations automatically.
- Documented Google reCAPTCHA and Cloudflare Turnstile in the readme External services section.

## 2026-10-23

### Changed

- readme.txt: Contributors limited to bigrat95.

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

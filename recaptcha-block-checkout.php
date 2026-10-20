<?php
/**
 * Legacy bootstrap for 1.0.0 installs activated as
 * recaptcha-block-checkout/recaptcha-block-checkout.php (for example Dimex).
 *
 * This file intentionally has no Plugin Name header so WordPress lists only
 * oli-checkout-captcha.php. If this path is already in active_plugins, the
 * 1.1.0 code still loads after an in-place file replacement.
 *
 * @package Oli_Checkout_Captcha
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/oli-checkout-captcha.php';

/**
 * Renders Google reCAPTCHA v2 on WooCommerce block checkout and sends the
 * token through the Store API extensions payload.
 *
 * Global oliCcConfig is localized from PHP (siteKey, theme, namespace).
 */
(function () {
	'use strict';

	var rendered = false;
	var widgetId = null;
	var currentToken = '';

	function getNamespace() {
		if (typeof oliCcConfig !== 'undefined' && oliCcConfig.namespace) {
			return oliCcConfig.namespace;
		}
		return 'oli-checkout-captcha';
	}

	window.oliCcRecaptchaCallback = function (token) {
		currentToken = token;
		updateStoreData(token);
		var hiddenInput = document.getElementById('oli-cc-token');
		if (hiddenInput) {
			hiddenInput.value = token;
		}
	};

	window.oliCcRecaptchaExpired = function () {
		currentToken = '';
		updateStoreData('');
	};

	window.oliCcRecaptchaError = function () {
		currentToken = '';
		updateStoreData('');
	};

	function updateStoreData(token) {
		try {
			if (typeof wp !== 'undefined' && wp.data && wp.data.dispatch) {
				var dispatch = wp.data.dispatch('wc/store/checkout');
				if (dispatch && dispatch.__internalSetExtensionData) {
					dispatch.__internalSetExtensionData(getNamespace(), { token: token });
				}
			}
		} catch (e) {
			console.error('Oli Checkout Captcha: Failed to update store data', e);
		}
	}

	function renderRecaptcha() {
		if (rendered) {
			return;
		}
		if (typeof grecaptcha === 'undefined' || typeof grecaptcha.render !== 'function') {
			return;
		}
		if (typeof oliCcConfig === 'undefined') {
			return;
		}

		var targetSelectors = [
			'.wc-block-components-checkout-step--payment-methods',
			'.wp-block-woocommerce-checkout-payment-block',
			'.wc-block-checkout__payment-method'
		];

		var target = null;
		for (var i = 0; i < targetSelectors.length; i++) {
			target = document.querySelector(targetSelectors[i]);
			if (target) {
				break;
			}
		}

		if (!target) {
			return;
		}

		var container = document.getElementById('oli-cc-recaptcha-container');
		if (!container) {
			container = document.createElement('div');
			container.id = 'oli-cc-recaptcha-container';
			container.style.cssText = 'margin: 20px 0; padding: 15px 0;';

			var recaptchaDiv = document.createElement('div');
			recaptchaDiv.id = 'oli-cc-recaptcha-widget';
			container.appendChild(recaptchaDiv);

			target.parentNode.insertBefore(container, target.nextSibling);
		}

		var widget = document.getElementById('oli-cc-recaptcha-widget');
		if (!widget) {
			return;
		}

		if (widget.querySelector('iframe')) {
			rendered = true;
			return;
		}

		try {
			widgetId = grecaptcha.render(widget, {
				sitekey: oliCcConfig.siteKey,
				theme: oliCcConfig.theme || 'light',
				callback: 'oliCcRecaptchaCallback',
				'expired-callback': 'oliCcRecaptchaExpired',
				'error-callback': 'oliCcRecaptchaError'
			});
			rendered = true;
		} catch (e) {
			if (e.message && e.message.indexOf('already been rendered') > -1) {
				rendered = true;
			} else {
				console.error('Oli Checkout Captcha: Render failed', e);
			}
		}
	}

	window.oliCcRecaptchaOnLoad = function () {
		setTimeout(function () {
			renderRecaptcha();
		}, 500);
	};

	function init() {
		var attempts = 0;
		var maxAttempts = 30;

		function tryRender() {
			if (rendered || attempts >= maxAttempts) {
				return;
			}
			attempts++;
			renderRecaptcha();
			if (!rendered) {
				setTimeout(tryRender, 500);
			}
		}

		setTimeout(tryRender, 1500);

		if (typeof MutationObserver !== 'undefined') {
			var observer = new MutationObserver(function () {
				if (!rendered) {
					renderRecaptcha();
				}
				if (rendered) {
					observer.disconnect();
				}
			});

			var checkoutBlock = document.querySelector('.wp-block-woocommerce-checkout, .wc-block-checkout');
			if (checkoutBlock) {
				observer.observe(checkoutBlock, { childList: true, subtree: true });
			} else {
				observer.observe(document.body, { childList: true, subtree: true });
			}
		}

		if (typeof wp !== 'undefined' && wp.data && wp.data.subscribe) {
			var unsubscribe = wp.data.subscribe(function () {
				if (!rendered) {
					renderRecaptcha();
				}
				if (rendered && unsubscribe) {
					unsubscribe();
				}
			});
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();

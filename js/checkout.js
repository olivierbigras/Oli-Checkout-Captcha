(function () {
	'use strict';

	var rendered = false;
	var widgetId = null;

	function getConfig() {
		return typeof olicConfig !== 'undefined' ? olicConfig : null;
	}

	function getNamespace() {
		var config = getConfig();
		return config && config.namespace ? config.namespace : 'oli-checkout-captcha';
	}

	window.olicRecaptchaCallback = function (token) {
		updateStoreData(token);
	};

	window.olicRecaptchaExpired = function () {
		updateStoreData('');
	};

	window.olicRecaptchaError = function () {
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
			return;
		}
	}

	function renderRecaptcha() {
		if (rendered) {
			return;
		}

		if (typeof grecaptcha === 'undefined' || typeof grecaptcha.render !== 'function') {
			return;
		}

		var config = getConfig();
		if (!config || !config.siteKey) {
			return;
		}

		var targetSelectors = [
			'.wc-block-components-checkout-step--payment-methods',
			'.wp-block-woocommerce-checkout-payment-block',
			'.wc-block-checkout__payment-method'
		];

		var target = null;
		var i;
		for (i = 0; i < targetSelectors.length; i++) {
			target = document.querySelector(targetSelectors[i]);
			if (target) {
				break;
			}
		}

		if (!target) {
			return;
		}

		var container = document.getElementById('olic-recaptcha-container');
		if (!container) {
			container = document.createElement('div');
			container.id = 'olic-recaptcha-container';
			container.className = 'olic-recaptcha-container';

			var recaptchaDiv = document.createElement('div');
			recaptchaDiv.id = 'olic-recaptcha-widget';
			container.appendChild(recaptchaDiv);

			if (target.parentNode) {
				target.parentNode.insertBefore(container, target.nextSibling);
			}
		}

		var widget = document.getElementById('olic-recaptcha-widget');
		if (!widget) {
			return;
		}

		if (widget.querySelector('iframe')) {
			rendered = true;
			return;
		}

		try {
			widgetId = grecaptcha.render(widget, {
				sitekey: config.siteKey,
				theme: config.theme || 'light',
				callback: 'olicRecaptchaCallback',
				'expired-callback': 'olicRecaptchaExpired',
				'error-callback': 'olicRecaptchaError'
			});
			rendered = true;
		} catch (e) {
			if (e.message && e.message.indexOf('already been rendered') > -1) {
				rendered = true;
			}
		}
	}

	window.olicRecaptchaOnLoad = function () {
		window.setTimeout(function () {
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
			attempts += 1;
			renderRecaptcha();
			if (!rendered) {
				window.setTimeout(tryRender, 500);
			}
		}

		window.setTimeout(tryRender, 1500);

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

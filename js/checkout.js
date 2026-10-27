(function () {
	'use strict';

	var rendered = false;
	var widgetId = null;
	var renderedWidgetEl = null;
	var tokenRefreshTimer = null;
	var wooEventsBound = false;
	var latestToken = '';
	var blockTokenTransportBound = false;
	var flushingTokenToStore = false;
	var EXPRESS_SELECTORS = [
		'.wc-block-components-express-payment',
		'.wc-block-components-express-payment-continue-rule',
		'#wc-stripe-payment-request-wrapper',
		'.woocommerce-checkout-payment-request-button',
		'#wcpay-payment-request-wrapper'
	];

	function getConfig() {
		return typeof oliOccConfig !== 'undefined' ? oliOccConfig : null;
	}

	function getNamespace() {
		var config = getConfig();
		return config && config.namespace ? config.namespace : 'oli-checkout-captcha';
	}

	function getProvider() {
		var config = getConfig();
		return config && config.provider ? config.provider : 'recaptcha_v2';
	}

	function persistToken(token) {
		latestToken = token || '';

		var field = document.getElementById('oli-occ-captcha-token');
		if (field) {
			field.value = latestToken;
		}
	}

	function getPendingToken() {
		if (latestToken) {
			return latestToken;
		}

		var field = document.getElementById('oli-occ-captcha-token');
		return field && field.value ? field.value : '';
	}

	function setCheckoutExtensionData(namespace, data) {
		if (typeof wp === 'undefined' || !wp.data || !wp.data.dispatch) {
			return;
		}

		var dispatch = wp.data.dispatch('wc/store/checkout');
		if (!dispatch) {
			return;
		}

		if (typeof dispatch.setExtensionData === 'function') {
			dispatch.setExtensionData(namespace, data);
			return;
		}

		if (typeof dispatch.__internalSetExtensionData === 'function') {
			dispatch.__internalSetExtensionData(namespace, data);
		}
	}

	function flushTokenToCheckoutStore() {
		if (flushingTokenToStore) {
			return;
		}

		flushingTokenToStore = true;
		try {
			setCheckoutExtensionData(getNamespace(), { token: getPendingToken() });
		} catch (e) {
			return;
		} finally {
			flushingTokenToStore = false;
		}
	}

	function isStoreApiCheckoutPost(options) {
		if (!options) {
			return false;
		}

		var path = options.path || options.url || '';
		if (path.indexOf('/wc/store') === -1 || path.indexOf('/checkout') === -1) {
			return false;
		}

		var method = (options.method || 'GET').toUpperCase();
		return method === 'POST';
	}

	function withCaptchaExtension(options) {
		var data = options.data;

		if (data == null) {
			data = {};
		} else if (typeof data === 'string') {
			try {
				data = JSON.parse(data);
			} catch (e) {
				return options;
			}
		} else if (typeof data !== 'object') {
			return options;
		}

		if (!data.extensions || typeof data.extensions !== 'object') {
			data.extensions = {};
		}

		data.extensions[getNamespace()] = { token: getPendingToken() };
		options.data = data;
		return options;
	}

	function bindApiFetchTokenMiddleware() {
		if (typeof wp === 'undefined' || !wp.apiFetch || typeof wp.apiFetch.use !== 'function') {
			return false;
		}

		wp.apiFetch.use(function (options, next) {
			if (isStoreApiCheckoutPost(options)) {
				options = withCaptchaExtension(options);
			}
			return next(options);
		});

		return true;
	}

	function bindBeforeProcessingFlush() {
		if (typeof wp === 'undefined' || !wp.data || !wp.data.subscribe || !wp.data.select) {
			return;
		}

		var wasBeforeProcessing = false;

		wp.data.subscribe(function () {
			var select = wp.data.select('wc/store/checkout');
			if (!select || typeof select.isBeforeProcessing !== 'function') {
				return;
			}

			var isBefore = !!select.isBeforeProcessing();
			if (isBefore && !wasBeforeProcessing) {
				flushTokenToCheckoutStore();
			}
			wasBeforeProcessing = isBefore;
		});
	}

	function bindBlockCheckoutTokenTransport() {
		if (blockTokenTransportBound) {
			return;
		}

		blockTokenTransportBound = true;

		if (bindApiFetchTokenMiddleware()) {
			return;
		}

		bindBeforeProcessingFlush();
	}

	window.oliOccCaptchaCallback = function (token) {
		persistToken(token);
	};

	window.oliOccCaptchaExpired = function () {
		persistToken('');
	};

	window.oliOccCaptchaError = function () {
		persistToken('');
	};

	window.oliOccRecaptchaCallback = window.oliOccCaptchaCallback;
	window.oliOccRecaptchaExpired = window.oliOccCaptchaExpired;
	window.oliOccRecaptchaError = window.oliOccCaptchaError;

	function isInsideExpress(node) {
		if (!node || !node.closest) {
			return false;
		}

		var i;
		for (i = 0; i < EXPRESS_SELECTORS.length; i++) {
			if (node.closest(EXPRESS_SELECTORS[i])) {
				return true;
			}
		}

		return false;
	}

	function isBlockCheckout() {
		return !!document.querySelector('.wp-block-woocommerce-checkout, .wc-block-checkout');
	}

	function isClassicCheckout() {
		return !!document.querySelector('form.checkout');
	}

	function getWidgetEl() {
		return document.getElementById('oli-occ-captcha-widget');
	}

	function isSameRenderedWidget() {
		var widget = getWidgetEl();
		return !!(rendered && widget && widget === renderedWidgetEl);
	}

	function markRendered(widget, id) {
		rendered = true;
		widgetId = id;
		renderedWidgetEl = widget;
		if (widget && widget.setAttribute) {
			widget.setAttribute('data-oli-occ-rendered', '1');
		}
	}

	function isContainerUsed(widget) {
		if (!widget) {
			return false;
		}

		if (widget === renderedWidgetEl && rendered) {
			return true;
		}

		if (widget.getAttribute && widget.getAttribute('data-oli-occ-rendered') === '1') {
			return true;
		}

		return !!(widget.querySelector && widget.querySelector('iframe'));
	}

	function findBlockTarget() {
		var placeOrder = document.querySelector('.wc-block-components-checkout-place-order-button, .wc-block-checkout__actions');
		if (placeOrder && !isInsideExpress(placeOrder)) {
			return placeOrder;
		}

		var selectors = [
			'.wc-block-components-checkout-step--payment-methods',
			'.wp-block-woocommerce-checkout-payment-block',
			'.wc-block-checkout__payment-method'
		];
		var i;
		var target;

		for (i = 0; i < selectors.length; i++) {
			target = document.querySelector(selectors[i]);
			if (target && !isInsideExpress(target)) {
				return target;
			}
		}

		return null;
	}

	function ensureBlockContainer() {
		var container = document.getElementById('oli-occ-captcha-container');
		if (container) {
			return container;
		}

		var target = findBlockTarget();
		if (!target || !target.parentNode) {
			return null;
		}

		container = document.createElement('div');
		container.id = 'oli-occ-captcha-container';
		container.className = 'oli-occ-captcha-container';
		if (getProvider() === 'recaptcha_v3') {
			container.className += ' oli-occ-captcha-container--v3';
		}

		var widget = document.createElement('div');
		widget.id = 'oli-occ-captcha-widget';
		container.appendChild(widget);

		if (target.classList && target.classList.contains('wc-block-components-checkout-place-order-button')) {
			target.parentNode.insertBefore(container, target);
		} else if (target.classList && target.classList.contains('wc-block-checkout__actions')) {
			target.parentNode.insertBefore(container, target);
		} else {
			target.parentNode.insertBefore(container, target.nextSibling);
		}

		return container;
	}

	function unmountWidget() {
		var provider = getProvider();

		if (widgetId !== null) {
			try {
				if (provider === 'turnstile' && typeof turnstile !== 'undefined' && typeof turnstile.remove === 'function') {
					turnstile.remove(widgetId);
				} else if (provider !== 'recaptcha_v3' && typeof grecaptcha !== 'undefined' && typeof grecaptcha.reset === 'function') {
					grecaptcha.reset(widgetId);
				}
			} catch (e) {
				// The previous widget node may already have been replaced by WooCommerce.
			}
		}

		widgetId = null;
		rendered = false;
		renderedWidgetEl = null;
		persistToken('');
	}

	function renderVisibleWidget() {
		var config = getConfig();
		if (!config || !config.siteKey) {
			return;
		}

		var provider = getProvider();
		var widget = getWidgetEl();
		if (!widget) {
			if (isBlockCheckout()) {
				ensureBlockContainer();
				widget = getWidgetEl();
			}
		}

		if (!widget) {
			return;
		}

		if (isInsideExpress(widget)) {
			return;
		}

		if (isContainerUsed(widget)) {
			rendered = true;
			renderedWidgetEl = widget;
			return;
		}

		try {
			if (provider === 'turnstile') {
				if (typeof turnstile === 'undefined' || typeof turnstile.render !== 'function') {
					return;
				}
				markRendered(
					widget,
					turnstile.render(widget, {
						sitekey: config.siteKey,
						theme: config.theme || 'auto',
						callback: window.oliOccCaptchaCallback,
						'expired-callback': window.oliOccCaptchaExpired,
						'error-callback': window.oliOccCaptchaError
					})
				);
				return;
			}

			if (typeof grecaptcha === 'undefined' || typeof grecaptcha.render !== 'function') {
				return;
			}

			markRendered(
				widget,
				grecaptcha.render(widget, {
					sitekey: config.siteKey,
					theme: config.theme === 'auto' ? 'light' : (config.theme || 'light'),
					callback: 'oliOccCaptchaCallback',
					'expired-callback': 'oliOccCaptchaExpired',
					'error-callback': 'oliOccCaptchaError'
				})
			);
		} catch (e) {
			if (e.message && e.message.indexOf('already been rendered') > -1) {
				markRendered(widget, widgetId);
			}
		}
	}

	function remountVisibleWidget() {
		if (isSameRenderedWidget()) {
			return;
		}

		unmountWidget();
		renderVisibleWidget();
	}

	function resetAfterError() {
		persistToken('');

		if (getProvider() === 'recaptcha_v3') {
			refreshV3Token();
			return;
		}

		if (isSameRenderedWidget() && widgetId !== null) {
			try {
				if (getProvider() === 'turnstile' && typeof turnstile !== 'undefined' && typeof turnstile.reset === 'function') {
					turnstile.reset(widgetId);
					return;
				}
				if (typeof grecaptcha !== 'undefined' && typeof grecaptcha.reset === 'function') {
					grecaptcha.reset(widgetId);
					return;
				}
			} catch (e) {
				// Fall through to a full remount when the live widget is gone.
			}
		}

		remountVisibleWidget();
	}

	function refreshV3Token() {
		var config = getConfig();
		if (!config || !config.siteKey || getProvider() !== 'recaptcha_v3') {
			return;
		}

		if (typeof grecaptcha === 'undefined' || typeof grecaptcha.execute !== 'function') {
			return;
		}

		grecaptcha.ready(function () {
			grecaptcha.execute(config.siteKey, { action: config.action || 'checkout' }).then(function (token) {
				persistToken(token);
			}).catch(function () {
				persistToken('');
			});
		});
	}

	function startV3() {
		if (rendered && isSameRenderedWidget()) {
			return;
		}

		if (typeof grecaptcha === 'undefined' || typeof grecaptcha.ready !== 'function') {
			return;
		}

		if (isBlockCheckout()) {
			ensureBlockContainer();
		}

		refreshV3Token();
		rendered = true;
		renderedWidgetEl = getWidgetEl();

		if (tokenRefreshTimer) {
			window.clearInterval(tokenRefreshTimer);
		}
		tokenRefreshTimer = window.setInterval(refreshV3Token, 90 * 1000);
	}

	function onUpdatedCheckout() {
		if (isBlockCheckout() && !isClassicCheckout()) {
			return;
		}

		persistToken('');

		if (getProvider() === 'recaptcha_v3') {
			refreshV3Token();
			return;
		}

		remountVisibleWidget();
	}

	function bindClassicCheckoutEvents() {
		if (wooEventsBound || typeof jQuery === 'undefined') {
			return;
		}

		wooEventsBound = true;

		jQuery(document.body).on('updated_checkout', onUpdatedCheckout);
		jQuery(document.body).on('checkout_error', resetAfterError);
	}

	window.oliOccCaptchaOnLoad = function () {
		window.setTimeout(function () {
			if (getProvider() === 'recaptcha_v3') {
				startV3();
				return;
			}
			if (isClassicCheckout() && !isSameRenderedWidget()) {
				remountVisibleWidget();
				return;
			}
			renderVisibleWidget();
		}, 300);
	};

	window.oliOccRecaptchaOnLoad = window.oliOccCaptchaOnLoad;

	function bindClassicSubmit() {
		var form = document.querySelector('form.checkout');
		if (!form || form.getAttribute('data-oli-occ-bound') === '1') {
			return;
		}

		form.setAttribute('data-oli-occ-bound', '1');
		form.addEventListener('submit', function () {
			if (getProvider() === 'recaptcha_v3') {
				refreshV3Token();
			}
		});
	}

	function maybeRender() {
		bindClassicSubmit();
		bindClassicCheckoutEvents();
		bindBlockCheckoutTokenTransport();

		if (getProvider() === 'recaptcha_v3') {
			startV3();
			return;
		}

		if (isSameRenderedWidget()) {
			return;
		}

		if (isClassicCheckout()) {
			remountVisibleWidget();
			return;
		}

		renderVisibleWidget();
	}

	function init() {
		bindBlockCheckoutTokenTransport();

		var attempts = 0;
		var maxAttempts = 30;

		function tryRender() {
			if (attempts >= maxAttempts) {
				return;
			}
			attempts += 1;

			maybeRender();

			if (!rendered) {
				window.setTimeout(tryRender, 500);
			}
		}

		window.setTimeout(tryRender, 800);

		if (typeof MutationObserver !== 'undefined') {
			var observer = new MutationObserver(function () {
				maybeRender();
				if (rendered && isBlockCheckout() && !isClassicCheckout()) {
					observer.disconnect();
				}
			});

			var root = document.querySelector(
				'.wp-block-woocommerce-checkout, .wc-block-checkout, form.checkout, .woocommerce-checkout'
			);
			if (root && !isInsideExpress(root)) {
				observer.observe(root, { childList: true, subtree: true });
			}
		}

		if (typeof wp !== 'undefined' && wp.data && wp.data.subscribe) {
			var unsubscribe = wp.data.subscribe(function () {
				if (!rendered) {
					maybeRender();
				}
				if (rendered && unsubscribe && isBlockCheckout() && !isClassicCheckout()) {
					unsubscribe();
				}
			});
		}

		bindClassicCheckoutEvents();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();

'use strict';

const assert = require('node:assert/strict');
const fs = require('fs');
const path = require('path');
const vm = require('vm');
const { test } = require('node:test');

const SCRIPT = fs.readFileSync(path.join(__dirname, '../../js/checkout.js'), 'utf8');

function createElement(id) {
	return {
		id: id || '',
		value: '',
		className: '',
		classList: { contains: () => false },
		children: [],
		setAttribute: function () {},
		getAttribute: function () { return null; },
		appendChild: function (child) { this.children.push(child); return child; },
		querySelector: function () { return null; },
		closest: function () { return null; }
	};
}

function loadCheckout(options) {
	options = options || {};

	const tokenField = createElement('oli-occ-captcha-token');
	const extensionCalls = [];
	const apiFetchMiddlewares = [];
	const wcBlocksDataAccesses = [];
	const subscribers = [];
	let beforeProcessing = false;

	const checkoutDispatch = {
		setExtensionData: function (namespace, data) {
			extensionCalls.push({ api: 'setExtensionData', namespace: namespace, data: data });
			subscribers.slice().forEach(function (fn) { fn(); });
		},
		__internalSetExtensionData: function (namespace, data) {
			extensionCalls.push({ api: '__internalSetExtensionData', namespace: namespace, data: data });
			subscribers.slice().forEach(function (fn) { fn(); });
		}
	};

	const wcBlocksData = new Proxy(
		{ CHECKOUT_STORE_KEY: 'wc/store/checkout' },
		{
			get: function (target, prop) {
				wcBlocksDataAccesses.push(String(prop));
				return target[prop];
			}
		}
	);

	const wp = {
		data: {
			dispatch: function (store) {
				return store === 'wc/store/checkout' ? checkoutDispatch : {};
			},
			select: function () {
				return {
					isBeforeProcessing: function () {
						return beforeProcessing;
					}
				};
			},
			subscribe: function (fn) {
				subscribers.push(fn);
				return function () {};
			}
		}
	};

	if (options.withApiFetch !== false) {
		wp.apiFetch = {
			use: function (middleware) {
				apiFetchMiddlewares.push(middleware);
			}
		};
	}

	const documentRef = {
		readyState: 'complete',
		getElementById: function (id) {
			return id === 'oli-occ-captcha-token' ? tokenField : null;
		},
		querySelector: function () { return null; },
		querySelectorAll: function () { return []; },
		createElement: createElement,
		addEventListener: function () {}
	};

	const windowRef = {
		oliOccConfig: {
			namespace: 'oli-checkout-captcha',
			provider: 'turnstile',
			siteKey: '1x00000000000000000000AA'
		},
		wp: wp,
		wc: { wcBlocksData: wcBlocksData },
		document: documentRef,
		setTimeout: function (fn) { return 0; },
		setInterval: function () { return 0; },
		clearInterval: function () {},
		addEventListener: function () {},
		MutationObserver: function () {
			this.observe = function () {};
			this.disconnect = function () {};
		}
	};
	windowRef.window = windowRef;

	// WooCommerce order-attribution.js updateCheckoutBlockData: on the first
	// checkout-store mutation it reads window.wc.wcBlocksData.CHECKOUT_STORE_KEY.
	if (options.withOrderAttribution) {
		const originalSet = checkoutDispatch.setExtensionData;
		let oaDone = false;
		checkoutDispatch.setExtensionData = function (namespace, data) {
			originalSet(namespace, data);
			if (oaDone) {
				return;
			}
			oaDone = true;
			void windowRef.wc.wcBlocksData.CHECKOUT_STORE_KEY;
			originalSet.call(checkoutDispatch, 'woocommerce/order-attribution', { source_type: 'typein' });
		};
	}

	vm.runInNewContext(SCRIPT, {
		window: windowRef,
		document: documentRef,
		oliOccConfig: windowRef.oliOccConfig,
		wp: wp,
		turnstile: undefined,
		grecaptcha: undefined,
		jQuery: undefined,
		console: console
	});

	return {
		window: windowRef,
		tokenField: tokenField,
		extensionCalls: extensionCalls,
		apiFetchMiddlewares: apiFetchMiddlewares,
		wcBlocksDataAccesses: wcBlocksDataAccesses,
		setBeforeProcessing: function (value) {
			beforeProcessing = !!value;
			subscribers.slice().forEach(function (fn) { fn(); });
		},
		runCheckoutPost: function (data) {
			assert.ok(apiFetchMiddlewares.length > 0, 'apiFetch middleware should be registered');
			const options = {
				path: '/wp-json/wc/store/v1/checkout',
				method: 'POST',
				data: data || { billing_address: {} }
			};
			let received = null;
			apiFetchMiddlewares[0](options, function (nextOptions) {
				received = nextOptions;
				return Promise.resolve({ ok: true });
			});
			return received;
		}
	};
}

test('persistToken keeps the token locally and does not write the checkout store', function () {
	const env = loadCheckout({ withApiFetch: true, withOrderAttribution: true });

	env.window.oliOccCaptchaCallback('tok-load-1');

	assert.equal(env.tokenField.value, 'tok-load-1');
	assert.deepEqual(env.extensionCalls, []);
	assert.deepEqual(env.wcBlocksDataAccesses, []);
});

test('apiFetch checkout POST injects the captcha token without touching wc.wcBlocksData', function () {
	const env = loadCheckout({ withApiFetch: true, withOrderAttribution: true });

	env.window.oliOccCaptchaCallback('tok-place-order');
	const request = env.runCheckoutPost({ billing_address: { first_name: 'Ada' } });

	assert.equal(request.data.billing_address.first_name, 'Ada');
	assert.equal(request.data.extensions['oli-checkout-captcha'].token, 'tok-place-order');
	assert.deepEqual(env.extensionCalls, []);
	assert.deepEqual(env.wcBlocksDataAccesses, []);
});

test('without apiFetch, isBeforeProcessing flushes via setExtensionData', function () {
	const env = loadCheckout({ withApiFetch: false, withOrderAttribution: false });

	env.window.oliOccCaptchaCallback('tok-fallback');
	assert.deepEqual(env.extensionCalls, []);

	env.setBeforeProcessing(true);

	assert.equal(env.extensionCalls.length, 1);
	assert.equal(env.extensionCalls[0].api, 'setExtensionData');
	assert.equal(env.extensionCalls[0].namespace, 'oli-checkout-captcha');
	assert.equal(env.extensionCalls[0].data.token, 'tok-fallback');
});

test('setExtensionData on the stack makes WooCommerce order-attribution access wc.wcBlocksData', function () {
	const env = loadCheckout({ withApiFetch: false, withOrderAttribution: true });

	env.window.oliOccCaptchaCallback('tok-oa');
	assert.deepEqual(env.wcBlocksDataAccesses, [], 'token refresh must not poke order-attribution');

	env.setBeforeProcessing(true);

	assert.ok(env.wcBlocksDataAccesses.includes('CHECKOUT_STORE_KEY'));
	assert.ok(
		env.extensionCalls.some(function (call) {
			return call.namespace === 'woocommerce/order-attribution';
		})
	);
});

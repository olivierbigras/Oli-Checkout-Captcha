(function() {
    'use strict';

    var rendered = false;
    var widgetId = null;
    var currentToken = '';

    // Callback when reCAPTCHA is solved
    window.rbcRecaptchaCallback = function(token) {
        currentToken = token;
        updateStoreData(token);
        // Also store in hidden input as fallback
        var hiddenInput = document.getElementById('rbc-token');
        if (hiddenInput) hiddenInput.value = token;
    };

    // Callback when reCAPTCHA expires
    window.rbcRecaptchaExpired = function() {
        currentToken = '';
        updateStoreData('');
    };

    // Callback when reCAPTCHA errors
    window.rbcRecaptchaError = function() {
        currentToken = '';
        updateStoreData('');
    };

    // Update WooCommerce Store API data
    function updateStoreData(token) {
        try {
            if (typeof wp !== 'undefined' && wp.data && wp.data.dispatch) {
                var dispatch = wp.data.dispatch('wc/store/checkout');
                if (dispatch && dispatch.__internalSetExtensionData) {
                    dispatch.__internalSetExtensionData('recaptcha-block-checkout', { token: token });
                }
            }
        } catch (e) {
            console.error('reCAPTCHA Block Checkout: Failed to update store data', e);
        }
    }

    // Create and render the reCAPTCHA widget
    function renderRecaptcha() {
        if (rendered) return;
        if (typeof grecaptcha === 'undefined' || typeof grecaptcha.render !== 'function') return;
        if (typeof rbcConfig === 'undefined') return;

        // Find the place to insert reCAPTCHA (after payment methods)
        var targetSelectors = [
            '.wc-block-components-checkout-step--payment-methods',
            '.wp-block-woocommerce-checkout-payment-block',
            '.wc-block-checkout__payment-method'
        ];

        var target = null;
        for (var i = 0; i < targetSelectors.length; i++) {
            target = document.querySelector(targetSelectors[i]);
            if (target) break;
        }

        if (!target) return;

        // Check if container already exists
        var container = document.getElementById('rbc-recaptcha-container');
        if (!container) {
            // Create container
            container = document.createElement('div');
            container.id = 'rbc-recaptcha-container';
            container.style.cssText = 'margin: 20px 0; padding: 15px 0;';
            
            // Create inner div for reCAPTCHA
            var recaptchaDiv = document.createElement('div');
            recaptchaDiv.id = 'rbc-recaptcha-widget';
            container.appendChild(recaptchaDiv);
            
            // Insert after payment block
            target.parentNode.insertBefore(container, target.nextSibling);
        }

        var widget = document.getElementById('rbc-recaptcha-widget');
        if (!widget) return;

        // Check if already has iframe (already rendered)
        if (widget.querySelector('iframe')) {
            rendered = true;
            return;
        }

        try {
            widgetId = grecaptcha.render(widget, {
                sitekey: rbcConfig.siteKey,
                theme: rbcConfig.theme || 'light',
                callback: 'rbcRecaptchaCallback',
                'expired-callback': 'rbcRecaptchaExpired',
                'error-callback': 'rbcRecaptchaError'
            });
            rendered = true;
        } catch (e) {
            if (e.message && e.message.indexOf('already been rendered') > -1) {
                rendered = true;
            } else {
                console.error('reCAPTCHA Block Checkout: Render failed', e);
            }
        }
    }

    // Called when Google reCAPTCHA API loads
    window.rbcRecaptchaOnLoad = function() {
        // Wait a bit for React to hydrate checkout blocks
        setTimeout(function() {
            renderRecaptcha();
        }, 500);
    };

    // Initialize
    function init() {
        // Multiple strategies to ensure reCAPTCHA renders

        // 1. Polling with delays
        var attempts = 0;
        var maxAttempts = 30;
        
        function tryRender() {
            if (rendered || attempts >= maxAttempts) return;
            attempts++;
            renderRecaptcha();
            if (!rendered) {
                setTimeout(tryRender, 500);
            }
        }

        // Start after initial delay for React hydration
        setTimeout(tryRender, 1500);

        // 2. MutationObserver for dynamic content
        if (typeof MutationObserver !== 'undefined') {
            var observer = new MutationObserver(function() {
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
                // Observe body until checkout block appears
                observer.observe(document.body, { childList: true, subtree: true });
            }
        }

        // 3. Listen to WooCommerce store changes
        if (typeof wp !== 'undefined' && wp.data && wp.data.subscribe) {
            var unsubscribe = wp.data.subscribe(function() {
                if (!rendered) {
                    renderRecaptcha();
                }
                if (rendered && unsubscribe) {
                    unsubscribe();
                }
            });
        }
    }

    // Start when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();

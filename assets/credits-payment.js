(function () {
    'use strict';

    var CFG = window.jankxCreditsPayment || { restUrl: '', nonce: '', i18n: {} };

    function api(path, body) {
        return fetch(CFG.restUrl + path, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': CFG.nonce || ''
            },
            credentials: 'same-origin',
            body: JSON.stringify(body || {})
        }).then(function (res) {
            return res.json().catch(function () {
                return { success: false, message: CFG.i18n.error || 'Request failed.' };
            });
        });
    }

    function setRow(rowSelector, valueSelector, show, formatted) {
        document.querySelectorAll(rowSelector).forEach(function (row) {
            row.hidden = !show;
            var value = row.querySelector(valueSelector);
            if (value && typeof formatted === 'string') {
                value.textContent = '-' + formatted;
            }
        });
    }

    function setText(selector, text) {
        if (typeof text !== 'string') {
            return;
        }
        document.querySelectorAll(selector).forEach(function (el) {
            el.textContent = text;
        });
    }

    function syncToggles(checked, disabled) {
        document.querySelectorAll('.jankx-credits-toggle').forEach(function (toggle) {
            toggle.checked = !!checked;
            toggle.disabled = !!disabled;
        });
    }

    function showMessage(text, isError) {
        document.querySelectorAll('.jankx-credits-message').forEach(function (el) {
            el.textContent = text || '';
            el.classList.toggle('is-error', !!isError);
        });
    }

    function updateTotals(res) {
        var cart = res.cart || {};
        var creditDiscount = parseFloat(res.credit_discount) || 0;
        var couponDiscount = parseFloat(res.coupon_discount) || 0;

        // Cart totals block
        setText('.jankx-total-grand-value', cart.formatted_total);
        setRow('.jankx-discount-row', '.jankx-discount-value', couponDiscount > 0, res.formatted_coupon_discount);
        setRow('.jankx-credit-discount-row', '.jankx-credit-discount-value', creditDiscount > 0, res.formatted_credit_discount);

        // Checkout summary
        setText('.jankx-review-total-value', cart.formatted_total);
        setRow('.jankx-review-discount-row', '.jankx-review-discount-value', couponDiscount > 0, res.formatted_coupon_discount);
        setRow('.jankx-review-credit-row', '.jankx-review-credit-value', creditDiscount > 0, res.formatted_credit_discount);

        // Keep the subtotal row visible whenever any discount applies.
        document.querySelectorAll('.jankx-subtotal-row').forEach(function (row) {
            row.hidden = (creditDiscount + couponDiscount) <= 0;
        });
    }

    document.addEventListener('change', function (event) {
        var toggle = event.target.closest('.jankx-credits-toggle');
        if (!toggle) {
            return;
        }

        var use = !!toggle.checked;
        showMessage('');
        syncToggles(use, true);

        api('credits/cart/apply', { use: use }).then(function (res) {
            if (!res.success) {
                syncToggles(!use, false);
                showMessage(res.message || CFG.i18n.error || 'Error.', true);
                return;
            }

            syncToggles(res.applied, false);
            showMessage(res.message || '');
            updateTotals(res);
        }).catch(function () {
            syncToggles(!use, false);
            showMessage(CFG.i18n.error || 'Request failed.', true);
        });
    });
})();

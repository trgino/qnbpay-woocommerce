/**
 * QNBPay front-end JS (classic checkout + order-pay).
 *
 * Fail-safe: every entry point guards against missing globals, missing DOM
 * nodes and failed AJAX so a problem here can never block the checkout UI.
 */
(function () {
    'use strict';

    if (typeof window.jQuery === 'undefined') {
        return;
    }
    if (typeof window.qnbpay_ajax === 'undefined' || !window.qnbpay_ajax.ajax_url) {
        return;
    }

    var $ = window.jQuery;
    var cfg = window.qnbpay_ajax;

    $(function () {
        try {
            initRecheck();
            initBinLookup();
        } catch (e) {
            if (window.console && window.console.warn) {
                window.console.warn('QNBPay init error:', e);
            }
        }
    });

    /**
     * Auto-recheck a pending payment on the order-pay page.
     */
    function initRecheck() {
        var $recheck = $('[data-qnbpayrecheck]');
        if (!$recheck.length) {
            return;
        }

        $.post(cfg.ajax_url, {
            action: 'qnbpay_ajax',
            method: 'recheckpayment',
            nonce: cfg.nonce,
            orderid: $recheck.data('orderid')
        }, null, 'json').done(function (response) {
            if (!response) {
                return;
            }
            if (response.status === true && response.url) {
                window.location.href = response.url;
                return;
            }
            if (response.retry === true) {
                window.setTimeout(function () {
                    window.location.reload();
                }, 3000);
                return;
            }
            if (response.url) {
                window.location.href = response.url;
            } else if (response.message) {
                $recheck.text(response.message);
            }
        }).fail(function () {
            // Leave the page as-is; the reconciliation cron will still finalize.
        });
    }

    /**
     * BIN -> installment lookup on the card-number field.
     */
    function initBinLookup() {
        var binTimeout = null;
        var lastBin = '';
        var currentXhr = null;
        var requestSeq = 0;

        function requestBin(cardNumber) {
            var digits = String(cardNumber || '').replace(/\D+/g, '');
            if (digits.length < 8) {
                return;
            }
            var bin = digits.substring(0, 8);
            if (bin === lastBin) {
                return;
            }
            lastBin = bin;

            $('#qnbpay-installment-table').html('');

            window.clearTimeout(binTimeout);
            binTimeout = window.setTimeout(function () {
                var state = $('#qnbpay-current-order-state').val() || 'cart';

                // Cancel any in-flight lookup so an older BIN can never win the
                // race and overwrite the table with stale installments.
                if (currentXhr && currentXhr.readyState !== 4) {
                    currentXhr.abort();
                }

                var seq = ++requestSeq;
                currentXhr = $.post(cfg.ajax_url, {
                    action: 'qnbpay_ajax',
                    method: 'validate_bin',
                    nonce: cfg.nonce,
                    binNumber: bin,
                    state: state
                }, null, 'json').done(function (response) {
                    // Ignore responses that are no longer the latest request.
                    if (seq !== requestSeq) {
                        return;
                    }
                    if (response && response.status === true) {
                        $('#qnbpay-installment-table').html(response.html || '');
                    }
                }).fail(function () {
                    // Aborted or failed request: leave the table cleared.
                });
            }, 250);
        }

        $(document.body).on('input', '.wc-credit-card-form-card-number', function () {
            requestBin(this.value);
        });
    }
})();

/**
 * QNBPay Admin JS — connection test + link to WooCommerce logs.
 *
 * Fail-safe: guards missing globals and failed AJAX.
 */
(function () {
    'use strict';

    if (typeof window.jQuery === 'undefined' || typeof window.qnbpay_ajax === 'undefined') {
        return;
    }

    var $ = window.jQuery;
    var cfg = window.qnbpay_ajax;

    $(function () {
        $('.qnbpay-admin-dotest').on('click', function () {
            var $results = $('.qnbpay-admin-test-results');
            $results.addClass('active').html('<p>' + cfg.remote_test + '…</p>');

            $.ajax({
                url: cfg.ajax_url,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'qnbpay_ajax',
                    method: 'qnbpay_test',
                    nonce: cfg.nonce
                }
            }).done(function (response) {
                response = response || {};

                function row(name, ok) {
                    var cls = ok ? 'success' : 'failed';
                    var label = ok ? cfg.success : cfg.failed;
                    return '<div class="test-item"><span class="test-name">' + name + '</span>' +
                        '<span class="test-status ' + cls + '">' + label + '</span></div>';
                }

                var html = '';
                html += row(cfg.remote_test, !!response.remote);
                html += row(cfg.installment_test, !!response.commissioncheck);
                html += row(cfg.bin_test, !!response.bincheck);

                if (response.message) {
                    html += '<div class="test-item"><em>' + response.message + '</em></div>';
                }

                if (cfg.logs_url) {
                    html += '<div class="test-item"><a class="button" target="_blank" rel="noopener" href="' +
                        cfg.logs_url + '">' + cfg.view_logs + '</a></div>';
                }

                $results.html(html);
            }).fail(function () {
                $results.html('<div class="test-item"><span class="test-status failed">' + cfg.failed + '</span></div>');
            });
        });
    });
})();

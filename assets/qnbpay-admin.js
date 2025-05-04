/**
 * QNBPay Admin JS
 */
jQuery(document).ready(function ($) {
    // Test button
    $('.qnbpay-admin-dotest').on('click', function () {
        var $results = $('.qnbpay-admin-test-results');
        $results.html('<p>Testing connection...</p>');
        $results.addClass('active');

        $.ajax({
            url: qnbpay_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'qnbpay_ajax',
                method: 'qnbpay_test',
                nonce: qnbpay_ajax.nonce,
            },
            success: function (response) {
                var html = '';

                // Remote test
                html += '<div class="test-item">';
                html += '<span class="test-name">' + qnbpay_ajax.remote_test + '</span>';
                if (response.remote) {
                    html += '<span class="test-status success">' + qnbpay_ajax.success + '</span>';
                } else {
                    html += '<span class="test-status failed">' + qnbpay_ajax.failed + '</span>';
                }
                html += '</div>';

                // Commission test
                html += '<div class="test-item">';
                html += '<span class="test-name">' + qnbpay_ajax.installment_test + '</span>';
                if (response.commissioncheck) {
                    html += '<span class="test-status success">' + qnbpay_ajax.success + '</span>';
                } else {
                    html += '<span class="test-status failed">' + qnbpay_ajax.failed + '</span>';
                }
                html += '</div>';

                // Bin test
                html += '<div class="test-item">';
                html += '<span class="test-name">' + qnbpay_ajax.bin_test + '</span>';
                if (response.bincheck) {
                    html += '<span class="test-status success">' + qnbpay_ajax.success + '</span>';
                } else {
                    html += '<span class="test-status failed">' + qnbpay_ajax.failed + '</span>';
                }
                html += '</div>';

                // Debug tools
                html += '<div class="test-item">';
                html += '<button type="button" class="qnbpay-debug-download">' + qnbpay_ajax.download_debug + '</button>';
                html += '<button type="button" class="qnbpay-debug-clear">' + qnbpay_ajax.clear_debug + '</button>';
                html += '</div>';

                $results.html(html);

                // Debug download button
                $('.qnbpay-debug-download').on('click', function () {
                    $.ajax({
                        url: qnbpay_ajax.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'qnbpay_ajax',
                            method: 'debug_download',
                            nonce: qnbpay_ajax.nonce,
                        },
                        success: function (response) {
                            if (response.status === true) {
                                window.location.href = response.file;
                            } else {
                                alert(qnbpay_ajax.debug_notfound);
                            }
                        }
                    });
                });

                // Debug clear button
                $('.qnbpay-debug-clear').on('click', function () {
                    $.ajax({
                        url: qnbpay_ajax.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'qnbpay_ajax',
                            method: 'debug_clear',
                            nonce: qnbpay_ajax.nonce,
                        },
                        success: function (response) {
                            if (response.status === true) {
                                alert(response.message);
                            } else {
                                alert(qnbpay_ajax.debug_notfound);
                            }
                        }
                    });
                });
            }
        });
    });
});

/**
 * QNBPay JS
 */

jQuery(document).ready(function ($) {

    if ($('.woocomerce-message[data-qnypayrecheck]').length) {
        $.post(qnbpay_ajax.ajax_url, {
            action: 'qnbpay_ajax',
            method: 'recheckpayment',
            nonce: qnbpay_ajax.nonce,
            orderid: $('.woocomerce-message[data-qnypayrecheck]').data('orderid'),
        }, function (response) {
            if (response.status === true) {
                $('.woocomerce-message[data-qnypayrecheck]').html(data.msg);
                if (response.url === true) {
                    window.location.href = response.url;
                }
            } else {
                if (response.retry === true) {
                    window.location.reload(true);
                } else {
                    $('.woocomerce-message[data-qnypayrecheck]').html(data.msg);
                }
            }
        }, 'json');
    }

    // Card type detection
    $('body').on('input', '.wc-credit-card-form-card-number', function () {
        let card_number = this.value;
        // Remove spaces
        card_number = card_number.replace(/\s+/g, '');

        // Bin request
        if (card_number.length >= 8) {
            qnbpayBinRequest(card_number);
        }
    });

    // Bin request function
    var qnbpayBinRequestTimeout;
    var qnbpayLastBin = '';

    function qnbpayBinRequest(card_number) {
        var bin = card_number.substring(0, 8);

        // Check if bin is the same as last request
        if (bin === qnbpayLastBin) {
            return;
        }

        qnbpayLastBin = bin;

        // Clear timeout
        clearTimeout(qnbpayBinRequestTimeout);

        // Set timeout
        qnbpayBinRequestTimeout = setTimeout(function () {
            var state = $('#qnbpay-current-order-state').val();

            $.post(qnbpay_ajax.ajax_url, {
                action: 'qnbpay_ajax',
                method: 'validate_bin',
                nonce: qnbpay_ajax.nonce,
                binNumber: bin,
                state: state
            }, function (response) {
                if (response.status === true) {
                    $('#qnbpay-installment-table').html(response.html);
                }
            }, 'json');
        }, 500);
    }
});

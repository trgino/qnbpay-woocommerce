/**
 * QNBPay — WooCommerce Cart/Checkout Blocks integration.
 *
 * Registers the QNBPay payment method for the block-based checkout, renders the
 * credit-card fields and forwards them to the server as paymentMethodData using
 * the SAME field names the classic gateway expects, so process_payment() works
 * unchanged. No card data is stored client-side beyond the component state.
 */
(function () {
    'use strict';

    var registry = window.wc && window.wc.wcBlocksRegistry;
    var settings = window.wc && window.wc.wcSettings;
    var element = window.wp && window.wp.element;
    var i18n = window.wp && window.wp.i18n;
    var htmlEntities = window.wp && window.wp.htmlEntities;

    if (!registry || !settings || !element) {
        return;
    }

    var createElement = element.createElement;
    var useState = element.useState;
    var useEffect = element.useEffect;
    var Fragment = element.Fragment;
    var __ = i18n ? i18n.__ : function (s) { return s; };
    var decodeEntities = htmlEntities ? htmlEntities.decodeEntities : function (s) { return s; };

    var data = settings.getSetting('qnbpay_data', {});
    var PREFIX = 'qnbpay-';

    /**
     * Label component.
     */
    function Label() {
        var title = decodeEntities(data.title || __('Credit Card', 'qnbpay-woocommerce'));
        return createElement('span', null, title);
    }

    /**
     * Card fields + installment table.
     */
    function Content(props) {
        var eventRegistration = props.eventRegistration || {};
        var onPaymentSetup = eventRegistration.onPaymentSetup;
        var emitResponse = props.emitResponse || {};

        var holderState = useState('');
        var numberState = useState('');
        var expiryState = useState('');
        var cvcState = useState('');
        var installmentState = useState('1');
        var installmentHtmlState = useState('');

        var holder = holderState[0], setHolder = holderState[1];
        var number = numberState[0], setNumber = numberState[1];
        var expiry = expiryState[0], setExpiry = expiryState[1];
        var cvc = cvcState[0], setCvc = cvcState[1];
        var installment = installmentState[0], setInstallment = installmentState[1];
        var installmentHtml = installmentHtmlState[0], setInstallmentHtml = installmentHtmlState[1];

        // BIN → installment lookup.
        useEffect(function () {
            var digits = (number || '').replace(/\D+/g, '');
            if (!data.installment || digits.length < 8) {
                setInstallmentHtml('');
                return;
            }
            if (!window.jQuery) {
                return;
            }
            var bin = digits.substring(0, 8);
            var xhr = null;
            // Debounce, then fire. The cleanup cancels a pending timer and
            // aborts any in-flight request so an older BIN can never overwrite
            // the table with stale installments.
            var timer = window.setTimeout(function () {
                xhr = window.jQuery.post(data.ajax_url, {
                    action: 'qnbpay_ajax',
                    method: 'validate_bin',
                    nonce: data.nonce,
                    binNumber: bin,
                    state: 'cart'
                }, function (response) {
                    if (response && response.status === true) {
                        setInstallmentHtml(response.html || '');
                    }
                }, 'json');
            }, 250);
            return function () {
                window.clearTimeout(timer);
                if (xhr && xhr.readyState !== 4) {
                    xhr.abort();
                }
            };
        }, [number]);

        // Provide card data to the server on checkout submission.
        useEffect(function () {
            if (typeof onPaymentSetup !== 'function') {
                return;
            }
            var unsubscribe = onPaymentSetup(function () {
                var payment_data = {};
                payment_data[PREFIX + 'name-oncard'] = holder;
                payment_data[PREFIX + 'card-number'] = number;
                payment_data[PREFIX + 'card-expiry'] = expiry;
                payment_data[PREFIX + 'card-cvc'] = cvc;
                payment_data[PREFIX + 'installment'] = installment;
                payment_data['qnbpay-installment'] = installment;

                return {
                    type: emitResponse.responseTypes ? emitResponse.responseTypes.SUCCESS : 'success',
                    meta: {
                        paymentMethodData: payment_data
                    }
                };
            });
            return unsubscribe;
        }, [onPaymentSetup, holder, number, expiry, cvc, installment]);

        // Sync selected installment from the injected radio HTML.
        function onInstallmentContainerChange(e) {
            if (e.target && e.target.name === 'qnbpay-installment') {
                setInstallment(e.target.value);
            }
        }

        var description = data.description
            ? createElement('p', { className: 'qnbpay-blocks-description' }, decodeEntities(data.description))
            : null;

        var testNotice = data.testmode
            ? createElement('p', { className: 'qnbpay-blocks-testmode' }, __('TEST MODE ENABLED.', 'qnbpay-woocommerce'))
            : null;

        return createElement(Fragment, null,
            description,
            testNotice,
            createElement('div', { className: 'qnbpay-blocks-fields' },
                createElement('p', { className: 'form-row form-row-wide' },
                    createElement('label', null, __('Name On Card', 'qnbpay-woocommerce'), ' ', createElement('span', { className: 'required' }, '*')),
                    createElement('input', {
                        type: 'text', className: 'input-text', autoComplete: 'off',
                        value: holder, onChange: function (e) { setHolder(e.target.value); }
                    })
                ),
                createElement('p', { className: 'form-row form-row-wide' },
                    createElement('label', null, __('Card Number', 'qnbpay-woocommerce'), ' ', createElement('span', { className: 'required' }, '*')),
                    createElement('input', {
                        type: 'tel', inputMode: 'numeric', className: 'input-text', autoComplete: 'cc-number',
                        maxLength: 24, placeholder: '•••• •••• •••• ••••',
                        value: number, onChange: function (e) { setNumber(e.target.value); }
                    })
                ),
                createElement('p', { className: 'form-row form-row-first' },
                    createElement('label', null, __('Expiry (MM/YY)', 'qnbpay-woocommerce'), ' ', createElement('span', { className: 'required' }, '*')),
                    createElement('input', {
                        type: 'tel', inputMode: 'numeric', className: 'input-text', autoComplete: 'cc-exp',
                        placeholder: 'MM / YY',
                        value: expiry, onChange: function (e) { setExpiry(e.target.value); }
                    })
                ),
                createElement('p', { className: 'form-row form-row-last' },
                    createElement('label', null, __('Card Code', 'qnbpay-woocommerce'), ' ', createElement('span', { className: 'required' }, '*')),
                    createElement('input', {
                        type: 'tel', inputMode: 'numeric', className: 'input-text', autoComplete: 'off',
                        maxLength: 4, placeholder: 'CVC',
                        value: cvc, onChange: function (e) { setCvc(e.target.value); }
                    })
                ),
                createElement('div', {
                    className: 'qnbpay-blocks-installments',
                    onChange: onInstallmentContainerChange,
                    dangerouslySetInnerHTML: { __html: installmentHtml }
                })
            )
        );
    }

    registry.registerPaymentMethod({
        name: 'qnbpay',
        label: createElement(Label, null),
        content: createElement(Content, null),
        edit: createElement(Content, null),
        canMakePayment: function () { return true; },
        ariaLabel: decodeEntities(data.title || 'QNBPay'),
        supports: {
            features: (data.supports) || ['products', 'refunds']
        }
    });
})();

define(['jquery'], function ($) {
    'use strict';

    return function (paymentData) {
        var choices = {};
        $('.payment-method._active [data-kkkonrad-checkout-consent]').each(function () {
            choices[$(this).data('version-id')] = this.checked ? 1 : 0;
        });
        paymentData.extension_attributes = paymentData.extension_attributes || {};
        paymentData.extension_attributes.kkkonrad_gdpr_consents = JSON.stringify(choices);
    };
});

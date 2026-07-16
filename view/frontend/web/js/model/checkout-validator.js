define(['jquery', 'mage/validation'], function ($) {
    'use strict';
    return {
        validate: function () {
            var valid = true;
            $('.payment-method._active [data-kkkonrad-checkout-consent][required]').each(function () {
                if (!this.checked) {
                    valid = false;
                    $.validator.validateSingleElement(this);
                }
            });
            return valid;
        }
    };
});

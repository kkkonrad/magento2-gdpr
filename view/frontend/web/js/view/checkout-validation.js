define([
    'uiComponent',
    'Magento_Checkout/js/model/payment/additional-validators',
    'Kkkonrad_Gdpr/js/model/checkout-validator'
], function (Component, additionalValidators, validator) {
    'use strict';
    additionalValidators.registerValidator(validator);
    return Component.extend({});
});

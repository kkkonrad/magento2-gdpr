define(['uiComponent'], function (Component) {
    'use strict';
    return Component.extend({
        defaults: {
            template: 'Kkkonrad_Gdpr/checkout/consents'
        },
        getConsents: function () {
            return (window.checkoutConfig.kkkonradGdpr || {}).consents || [];
        },
        getInputId: function (consent) {
            return 'kkkonrad-checkout-consent-' + consent.version_id;
        },
        getRequiredMessage: function () {
            return (window.checkoutConfig.kkkonradGdpr || {}).requiredMessage || '';
        }
    });
});

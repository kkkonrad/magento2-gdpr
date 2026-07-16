define(['Kkkonrad_Gdpr/js/model/consent-assigner'], function (assign) {
    'use strict';
    return function (originalAction) {
        return function (paymentData, messageContainer) {
            assign(paymentData);
            return originalAction(paymentData, messageContainer);
        };
    };
});

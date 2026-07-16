define(['Kkkonrad_Gdpr/js/model/consent-assigner'], function (assign) {
    'use strict';
    return function (originalAction) {
        return function (messageContainer, paymentData) {
            assign(paymentData);
            return originalAction(messageContainer, paymentData);
        };
    };
});

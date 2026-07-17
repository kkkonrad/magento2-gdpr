define([
    'jquery',
    'Magento_Ui/js/modal/confirm'
], function ($, confirm) {
    'use strict';

    return function (config, element) {
        $(element).on('click', function (event) {
            var form = element.form;
            event.preventDefault();
            if (!form || (typeof form.reportValidity === 'function' && !form.reportValidity())) {
                return;
            }
            confirm({
                title: config.title,
                content: config.content,
                actions: {
                    confirm: function () {
                        form.submit();
                    }
                }
            });
        });
    };
});

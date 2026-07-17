(function () {
    'use strict';

    var selectors = {
        registration: 'form.form-create-account',
        newsletter: 'form#newsletter-validate-detail',
        contact: 'form#contact-form, form#contact',
        checkout: 'form[data-kkkonrad-gdpr-location="checkout"]'
    };

    function appendDefinitions(form, location, definitions, requiredMessage) {
        if (!form || form.dataset.kkkonradGdprReady === '1') {
            return;
        }
        var container = document.createElement('fieldset');
        container.className = 'fieldset kkkonrad-gdpr-form-consents';
        container.setAttribute('data-location', location);
        definitions.forEach(function (definition) {
            var wrapper = document.createElement('div');
            wrapper.className = 'field choice kkkonrad-gdpr-form-consent';
            var input = document.createElement('input');
            var id = 'kkkonrad-gdpr-consent-' + definition.version_id;
            input.type = 'checkbox';
            input.id = id;
            input.name = 'kkkonrad_gdpr_consent[' + definition.version_id + ']';
            input.value = '1';
            input.required = Boolean(definition.is_required);
            if (definition.is_required) {
                input.setAttribute('data-msg-required', requiredMessage);
            }
            var label = document.createElement('label');
            label.className = 'label';
            label.setAttribute('for', id);
            var content = document.createElement('span');
            content.innerHTML = definition.content;
            label.appendChild(content);
            wrapper.appendChild(input);
            wrapper.appendChild(label);
            container.appendChild(wrapper);
        });
        var submit = form.querySelector('[type="submit"]');
        if (submit && submit.parentNode) {
            submit.parentNode.insertBefore(container, submit);
        } else {
            form.appendChild(container);
        }
        form.dataset.kkkonradGdprReady = '1';
    }

    function init() {
        var root = document.querySelector('[data-kkkonrad-gdpr-form-consents]');
        if (!root) {
            return;
        }
        var config;
        try {
            config = JSON.parse(root.dataset.config || '{}');
        } catch (error) {
            return;
        }
        Object.keys(config.locations || {}).forEach(function (location) {
            document.querySelectorAll(selectors[location] || '').forEach(function (form) {
                appendDefinitions(form, location, config.locations[location], config.requiredMessage);
            });
        });
        var observer = new MutationObserver(function () {
            Object.keys(config.locations || {}).forEach(function (location) {
                document.querySelectorAll(selectors[location] || '').forEach(function (form) {
                    appendDefinitions(form, location, config.locations[location], config.requiredMessage);
                });
            });
        });
        observer.observe(document.body, {childList: true, subtree: true});
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, {once: true});
    } else {
        init();
    }
}());

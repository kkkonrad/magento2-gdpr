(function () {
    'use strict';

    var COOKIE_NAME = 'kkkonrad_gdpr_consent';

    function readCookie(name) {
        var prefix = name + '=';
        var parts = document.cookie ? document.cookie.split('; ') : [];
        for (var i = 0; i < parts.length; i += 1) {
            if (parts[i].indexOf(prefix) === 0) {
                return decodeURIComponent(parts[i].substring(prefix.length));
            }
        }
        return null;
    }

    function readUnsignedPayload(token) {
        if (!token || token.indexOf('.') < 0) {
            return null;
        }
        try {
            var encoded = token.split('.')[0].replace(/-/g, '+').replace(/_/g, '/');
            while (encoded.length % 4) {
                encoded += '=';
            }
            return JSON.parse(decodeURIComponent(escape(window.atob(encoded))));
        } catch (error) {
            return null;
        }
    }

    function createElement(tag, attributes, text) {
        var element = document.createElement(tag);
        Object.keys(attributes || {}).forEach(function (name) {
            element.setAttribute(name, attributes[name]);
        });
        if (typeof text === 'string') {
            element.textContent = text;
        }
        return element;
    }

    function init(root) {
        var config;
        try {
            config = JSON.parse(root.dataset.config || '{}');
        } catch (error) {
            return;
        }
        var choices = {};
        var dialog = root.querySelector('[data-role="dialog"]');
        var banner = root.querySelector('[data-role="banner"]');
        var live = root.querySelector('[data-role="live"]');
        var groupsContainer = root.querySelector('[data-role="groups"]');
        var savedPayload = readUnsignedPayload(readCookie(COOKIE_NAME));
        var hasCurrentDecision = savedPayload
            && savedPayload.policy === config.policy
            && savedPayload.expires_at > Math.floor(Date.now() / 1000);

        (config.groups || []).forEach(function (group) {
            choices[group.code] = group.is_required
                ? true
                : Boolean(hasCurrentDecision && savedPayload.choices[group.code]);
        });

        root.querySelector('[data-role="banner-text"]').textContent = config.text.banner;
        root.querySelector('[data-role="modal-title"]').textContent = config.text.modalTitle;
        root.querySelector('[data-role="modal-text"]').textContent = config.text.modalText;
        root.querySelector('[data-action="accept-all"]').textContent = config.text.acceptAll;
        root.querySelector('[data-action="reject-optional"]').textContent = config.text.rejectOptional;
        root.querySelector('[data-action="customize"]').textContent = config.text.customize;
        root.querySelector('[data-action="save"]').textContent = config.text.save;
        root.querySelector('[data-action="open-settings"]').textContent = config.text.settings;
        root.querySelector('[data-action="close"] .screen-reader-text').textContent = config.text.close;

        function renderGroups() {
            groupsContainer.replaceChildren();
            (config.groups || []).forEach(function (group) {
                var fieldset = createElement('fieldset', {'class': 'kkkonrad-gdpr-group'});
                var label = createElement('label');
                var checkbox = createElement('input', {
                    type: 'checkbox',
                    'data-group-code': group.code
                });
                checkbox.checked = Boolean(choices[group.code]);
                checkbox.disabled = Boolean(group.is_required);
                checkbox.addEventListener('change', function () {
                    choices[group.code] = checkbox.checked;
                });
                label.appendChild(checkbox);
                label.appendChild(document.createTextNode(' ' + group.name));
                fieldset.appendChild(label);
                fieldset.appendChild(createElement('p', {}, group.description || ''));
                var list = createElement('ul');
                (group.cookies || []).forEach(function (cookie) {
                    list.appendChild(createElement('li', {}, cookie.name + (cookie.description ? ' — ' + cookie.description : '')));
                });
                fieldset.appendChild(list);
                groupsContainer.appendChild(fieldset);
            });
        }

        function openDialog() {
            renderGroups();
            if (typeof dialog.showModal === 'function') {
                dialog.showModal();
            } else {
                dialog.setAttribute('open', 'open');
            }
        }

        function closeDialog() {
            if (typeof dialog.close === 'function') {
                dialog.close();
            } else {
                dialog.removeAttribute('open');
            }
        }

        function deleteCookie(name) {
            document.cookie = encodeURIComponent(name) + '=; Max-Age=0; path=/; SameSite=Lax';
            document.cookie = encodeURIComponent(name) + '=; Max-Age=0; path=' + window.location.pathname + '; SameSite=Lax';
        }

        function patternMatches(pattern, name) {
            return pattern.slice(-1) === '*'
                ? name.indexOf(pattern.slice(0, -1)) === 0
                : name === pattern;
        }

        function cleanupRejected() {
            var visibleNames = (document.cookie || '').split('; ').map(function (item) {
                return decodeURIComponent(item.split('=')[0]);
            });
            (config.groups || []).forEach(function (group) {
                if (group.is_required || choices[group.code]) {
                    return;
                }
                (group.cookies || []).forEach(function (entry) {
                    if (entry.storage_type === 'local_storage') {
                        Object.keys(window.localStorage || {}).forEach(function (name) {
                            if (patternMatches(entry.code_pattern, name)) {
                                window.localStorage.removeItem(name);
                            }
                        });
                    } else if (entry.storage_type === 'session_storage') {
                        Object.keys(window.sessionStorage || {}).forEach(function (name) {
                            if (patternMatches(entry.code_pattern, name)) {
                                window.sessionStorage.removeItem(name);
                            }
                        });
                    } else {
                        visibleNames.forEach(function (name) {
                            if (patternMatches(entry.code_pattern, name)) {
                                deleteCookie(name);
                            }
                        });
                    }
                });
            });
        }

        function executeAllowedScripts() {
            document.querySelectorAll('script[type="text/plain"][data-kkkonrad-consent]').forEach(function (source) {
                var group = source.dataset.kkkonradConsent;
                if (!choices[group] || source.dataset.kkkonradExecuted === '1') {
                    return;
                }
                var script = document.createElement('script');
                Array.prototype.slice.call(source.attributes).forEach(function (attribute) {
                    if (attribute.name !== 'type' && attribute.name !== 'data-kkkonrad-consent') {
                        script.setAttribute(attribute.name, attribute.value);
                    }
                });
                script.text = source.text;
                source.dataset.kkkonradExecuted = '1';
                source.parentNode.insertBefore(script, source.nextSibling);
            });
        }

        function updateGoogleConsent() {
            if (!config.googleConsentEnabled || typeof window.gtag !== 'function') {
                return;
            }
            var analytics = Boolean(choices.statistical || choices.analytics);
            var advertising = Boolean(choices.marketing);
            var functionality = Boolean(choices.functionality);
            window.gtag('consent', 'update', {
                ad_storage: advertising ? 'granted' : 'denied',
                analytics_storage: analytics ? 'granted' : 'denied',
                functionality_storage: functionality ? 'granted' : 'denied',
                personalization_storage: functionality ? 'granted' : 'denied',
                security_storage: 'granted',
                ad_user_data: advertising ? 'granted' : 'denied',
                ad_personalization: advertising ? 'granted' : 'denied'
            });
            if (config.googleConsentDebug) {
                window.console.info('Kkkonrad GDPR Consent Mode preferences applied.');
            }
        }

        function applyDecision() {
            cleanupRejected();
            executeAllowedScripts();
            updateGoogleConsent();
            reportRejectedCookies();
            window.dispatchEvent(new CustomEvent('kkkonrad:consent-changed', {detail: {choices: choices}}));
        }

        function reportRejectedCookies() {
            if (!config.rejectedTrackingEnabled) {
                return;
            }
            var names = (document.cookie || '').split('; ').map(function (item) {
                return decodeURIComponent(item.split('=')[0]);
            }).filter(function (name) {
                if (!name || name === COOKIE_NAME) {
                    return false;
                }
                var matchedGroup = null;
                (config.groups || []).some(function (group) {
                    return (group.cookies || []).some(function (entry) {
                        if (entry.storage_type === 'cookie' && patternMatches(entry.code_pattern, name)) {
                            matchedGroup = group;
                            return true;
                        }
                        return false;
                    });
                });
                return matchedGroup === null || !choices[matchedGroup.code];
            });
            window.fetch(config.rejectedEndpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {'Content-Type': 'application/json', 'X-Form-Key': config.formKey},
                body: JSON.stringify({names: names, domain: window.location.hostname})
            }).catch(function () {
                // Diagnostics never block the storefront or the user's decision.
            });
        }

        function save() {
            live.textContent = '';
            window.fetch(config.endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {'Content-Type': 'application/json', 'X-Form-Key': config.formKey},
                body: JSON.stringify({choices: choices})
            }).then(function (response) {
                if (!response.ok) {
                    throw new Error('save_failed');
                }
                return response.json();
            }).then(function (response) {
                choices = response.choices;
                banner.hidden = true;
                closeDialog();
                applyDecision();
            }).catch(function () {
                live.textContent = config.text.error;
            });
        }

        root.querySelector('[data-action="accept-all"]').addEventListener('click', function () {
            Object.keys(choices).forEach(function (code) { choices[code] = true; });
            save();
        });
        root.querySelector('[data-action="reject-optional"]').addEventListener('click', function () {
            (config.groups || []).forEach(function (group) { choices[group.code] = Boolean(group.is_required); });
            save();
        });
        root.querySelector('[data-action="customize"]').addEventListener('click', openDialog);
        root.querySelector('[data-action="open-settings"]').addEventListener('click', openDialog);
        root.querySelector('[data-action="save"]').addEventListener('click', save);
        root.hidden = false;
        banner.hidden = Boolean(hasCurrentDecision || !config.showBanner);
        window.kkkonradConsent = {
            has: function (group) { return Boolean(choices[group]); },
            open: openDialog
        };
        if (hasCurrentDecision) {
            applyDecision();
        }
    }

    function boot() {
        document.querySelectorAll('[data-kkkonrad-gdpr-cmp]').forEach(init);
        if (document.querySelector('[data-kkkonrad-gdpr-allow-unmanaged]')) {
            document.querySelectorAll('script[type="text/plain"][data-kkkonrad-consent]').forEach(function (source) {
                var script = document.createElement('script');
                Array.prototype.slice.call(source.attributes).forEach(function (attribute) {
                    if (attribute.name !== 'type' && attribute.name !== 'data-kkkonrad-consent') {
                        script.setAttribute(attribute.name, attribute.value);
                    }
                });
                script.text = source.text;
                source.parentNode.insertBefore(script, source.nextSibling);
            });
            window.kkkonradConsent = {
                has: function () { return true; },
                open: function () {}
            };
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot, {once: true});
    } else {
        boot();
    }
}());

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

    function currentFormKey() {
        return readCookie('form_key') || '';
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
        var lockOverlay = root.querySelector('[data-role="lock-overlay"]');
        var settings = root.querySelector('[data-action="open-settings"]');
        var modalTitle = root.querySelector('[data-role="modal-title"]');
        var restoreFocusTo = null;
        var saving = false;
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
        root.querySelector('[data-action="close"] .kkkonrad-gdpr-visually-hidden').textContent = config.text.close;
        root.querySelector('[data-role="privacy-link"]').textContent = config.text.privacy;
        root.querySelector('[data-role="privacy-link"]').setAttribute('href', config.privacyUrl);

        function updateLockScreen(locked) {
            var shouldLock = Boolean(config.lockScreen && locked);
            lockOverlay.hidden = !shouldLock;
            document.documentElement.classList.toggle('kkkonrad-gdpr-page-locked', shouldLock);
        }

        function renderGroups() {
            groupsContainer.replaceChildren();
            (config.groups || []).forEach(function (group) {
                var fieldset = createElement('fieldset', {'class': 'kkkonrad-gdpr-group'});
                var legend = createElement('legend');
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
                label.appendChild(createElement('span', {}, group.name));
                if (group.is_required) {
                    label.appendChild(createElement('span', {'class': 'kkkonrad-gdpr-required'}, config.text.required));
                }
                legend.appendChild(label);
                fieldset.appendChild(legend);
                if (group.description) {
                    fieldset.appendChild(createElement('p', {}, group.description));
                }
                if ((group.cookies || []).length) {
                    var list = createElement('ul');
                    group.cookies.forEach(function (cookie) {
                        list.appendChild(createElement('li', {}, cookie.name + (cookie.description ? ' — ' + cookie.description : '')));
                    });
                    fieldset.appendChild(list);
                }
                groupsContainer.appendChild(fieldset);
            });
        }

        function restoreDialogFocus() {
            if (restoreFocusTo && restoreFocusTo.isConnected && !restoreFocusTo.closest('[hidden]')) {
                restoreFocusTo.focus();
            }
            restoreFocusTo = null;
        }

        function openDialog() {
            restoreFocusTo = document.activeElement;
            renderGroups();
            if (typeof dialog.showModal === 'function') {
                dialog.showModal();
            } else {
                dialog.setAttribute('open', 'open');
            }
            modalTitle.focus();
        }

        function closeDialog() {
            if (typeof dialog.close === 'function') {
                dialog.close();
            } else {
                dialog.removeAttribute('open');
                restoreDialogFocus();
            }
        }

        dialog.addEventListener('close', restoreDialogFocus);

        function deleteCookie(name) {
            var paths = ['/'];
            var segments = window.location.pathname.split('/').filter(Boolean);
            var current = '';
            segments.forEach(function (segment) {
                current += '/' + segment;
                paths.push(current);
            });
            var hostParts = window.location.hostname.split('.');
            var domains = ['', window.location.hostname, '.' + window.location.hostname];
            for (var i = 1; i < hostParts.length - 1; i += 1) {
                domains.push('.' + hostParts.slice(i).join('.'));
            }
            paths.forEach(function (path) {
                domains.forEach(function (domain) {
                    document.cookie = encodeURIComponent(name)
                        + '=; Max-Age=0; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=' + path
                        + (domain ? '; domain=' + domain : '')
                        + '; SameSite=Lax';
                });
            });
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
                        try {
                            Object.keys(window.localStorage).forEach(function (name) {
                                if (patternMatches(entry.code_pattern, name)) {
                                    window.localStorage.removeItem(name);
                                }
                            });
                        } catch (error) {
                            // Browser privacy settings can make Web Storage unavailable.
                        }
                    } else if (entry.storage_type === 'session_storage') {
                        try {
                            Object.keys(window.sessionStorage).forEach(function (name) {
                                if (patternMatches(entry.code_pattern, name)) {
                                    window.sessionStorage.removeItem(name);
                                }
                            });
                        } catch (error) {
                            // Browser privacy settings can make Web Storage unavailable.
                        }
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
            function isSemanticTypeAllowed(type) {
                return (config.groups || []).some(function (group) {
                    return group.type === type && Boolean(choices[group.code]);
                });
            }
            var analytics = isSemanticTypeAllowed('statistical');
            var advertising = isSemanticTypeAllowed('marketing');
            var functionality = isSemanticTypeAllowed('functionality');
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
                headers: {'Content-Type': 'application/json', 'X-Form-Key': currentFormKey()},
                body: JSON.stringify({names: names, domain: window.location.hostname})
            }).catch(function () {
                // Diagnostics never block the storefront or the user's decision.
            });
        }

        function setSaving(isSaving) {
            saving = isSaving;
            root.setAttribute('aria-busy', isSaving ? 'true' : 'false');
            ['accept-all', 'reject-optional', 'save'].forEach(function (action) {
                root.querySelector('[data-action="' + action + '"]').disabled = isSaving;
            });
        }

        function save() {
            if (saving) {
                return;
            }
            live.textContent = '';
            live.hidden = true;
            setSaving(true);
            window.fetch(config.endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {'Content-Type': 'application/json', 'X-Form-Key': currentFormKey()},
                body: JSON.stringify({choices: choices})
            }).then(function (response) {
                if (!response.ok) {
                    throw new Error('save_failed');
                }
                return response.json();
            }).then(function (response) {
                choices = response.choices;
                banner.hidden = true;
                settings.hidden = false;
                updateLockScreen(false);
                closeDialog();
                setSaving(false);
                applyDecision();
                settings.focus();
            }).catch(function () {
                setSaving(false);
                live.textContent = config.text.error;
                live.hidden = false;
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
        function setBannerVisibility(visible) {
            var shouldShow = Boolean(visible && !hasCurrentDecision && config.showBanner);
            banner.hidden = !shouldShow;
            settings.hidden = shouldShow;
            updateLockScreen(shouldShow);
        }

        function matchesConfiguredRegion(region) {
            if (!region) {
                return true;
            }
            return (config.regions || []).some(function (configured) {
                return region === configured || region.indexOf(configured + '-') === 0;
            });
        }

        banner.hidden = true;
        root.hidden = false;
        if (config.regionMode === 'selected' && !hasCurrentDecision && config.showBanner) {
            window.fetch(config.regionEndpoint, {
                method: 'GET',
                credentials: 'same-origin',
                headers: {'Accept': 'application/json'}
            }).then(function (response) {
                if (!response.ok) {
                    throw new Error('region_failed');
                }
                return response.json();
            }).then(function (resolved) {
                setBannerVisibility(matchesConfiguredRegion(resolved.region));
            }).catch(function () {
                setBannerVisibility(true);
            });
        } else {
            setBannerVisibility(true);
        }
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

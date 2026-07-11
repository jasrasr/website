/*
 * Project: Family GPS Tracker
 * File: assets/js/pwa-ui.js
 * Revision: 1.5.3
 * Description: PWA registration, install guidance, offline banner, and saved appearance preferences.
 * Author: Jason Lamb / ChatGPT scaffold
 * Created: 2026-07-11
 * Modified: 2026-07-11
 */
(function () {
    'use strict';

    var installPrompt = null;

    function $(id) { return document.getElementById(id); }

    function savedAppearance() {
        try { return window.localStorage.getItem('family-tracker-appearance') || 'dark'; }
        catch (ignore) { return 'dark'; }
    }

    function savedDensity() {
        try { return window.localStorage.getItem('family-tracker-density') || 'comfortable'; }
        catch (ignore) { return 'comfortable'; }
    }

    function applyPreferences() {
        var appearance = savedAppearance();
        var density = savedDensity();
        document.documentElement.dataset.appearance = appearance;
        document.documentElement.dataset.density = density;
        var appearanceSelect = $('appearanceModeSelect');
        var densitySelect = $('densityModeSelect');
        if (appearanceSelect) appearanceSelect.value = appearance;
        if (densitySelect) densitySelect.value = density;
    }

    function savePreference(key, value) {
        try { window.localStorage.setItem(key, value); } catch (ignore) { }
        applyPreferences();
    }

    function updateOfflineBanner() {
        var banner = $('offlineBanner');
        if (!banner) return;
        if (navigator.onLine) banner.classList.add('hidden');
        else banner.classList.remove('hidden');
    }

    function createUi() {
        if ($('appearancePwaCard')) return;
        var main = $('trackerApp');
        if (!main) return;

        var offline = document.createElement('section');
        offline.id = 'offlineBanner';
        offline.className = 'card warning-card hidden';
        offline.setAttribute('aria-live', 'polite');
        offline.innerHTML = '<strong>Offline:</strong> cached pages may still open, but location updates and account changes require a connection.';
        main.insertBefore(offline, main.firstChild);

        var card = document.createElement('section');
        card.id = 'appearancePwaCard';
        card.className = 'card profile-edit';
        card.innerHTML = '<div><p class="eyebrow">App Experience</p><h2>Install & Appearance</h2><p class="muted">Install the tracker on your home screen and choose a saved display preference for this device.</p></div><div class="settings-grid"><label>Appearance<select id="appearanceModeSelect"><option value="dark">Dark</option><option value="light">Light</option><option value="contrast">High contrast</option></select></label><label>Layout density<select id="densityModeSelect"><option value="comfortable">Comfortable</option><option value="compact">Compact</option></select></label></div><div class="button-row"><button id="installAppBtn" type="button" class="secondary">Install App</button><button id="refreshAppCacheBtn" type="button" class="secondary">Refresh Cached App</button></div><div id="installHelpText" class="profile-edit-note">On iPhone, use Share → Add to Home Screen. Android/desktop browsers may show an Install button.</div>';
        var settings = $('accountSettingsCard');
        if (settings && settings.nextSibling) settings.parentNode.insertBefore(card, settings.nextSibling);
        else main.insertBefore(card, main.firstChild);

        $('appearanceModeSelect').addEventListener('change', function (event) {
            savePreference('family-tracker-appearance', event.target.value);
        });
        $('densityModeSelect').addEventListener('change', function (event) {
            savePreference('family-tracker-density', event.target.value);
        });
        $('installAppBtn').addEventListener('click', installApp);
        $('refreshAppCacheBtn').addEventListener('click', refreshCache);
        applyPreferences();
        updateInstallButton();
        updateOfflineBanner();
    }

    function updateInstallButton() {
        var button = $('installAppBtn');
        var help = $('installHelpText');
        if (!button) return;
        var standalone = window.matchMedia && window.matchMedia('(display-mode: standalone)').matches;
        if (standalone || window.navigator.standalone === true) {
            button.disabled = true;
            button.textContent = 'App Installed';
            if (help) help.textContent = 'This tracker is currently running as an installed web app.';
            return;
        }
        if (installPrompt) {
            button.disabled = false;
            button.textContent = 'Install App';
            if (help) help.textContent = 'This browser is ready to install the tracker.';
        } else {
            button.disabled = false;
            button.textContent = 'Install Instructions';
        }
    }

    function installApp() {
        var help = $('installHelpText');
        if (!installPrompt) {
            if (help) help.textContent = 'iPhone/iPad: tap Share, then Add to Home Screen. Android/desktop: open the browser menu and choose Install App or Add to Home Screen.';
            return;
        }
        installPrompt.prompt();
        installPrompt.userChoice.finally(function () {
            installPrompt = null;
            updateInstallButton();
        });
    }

    function refreshCache() {
        var status = $('statusText');
        if (!('serviceWorker' in navigator)) {
            if (status) status.textContent = 'Service workers are not supported by this browser.';
            return;
        }
        navigator.serviceWorker.getRegistrations().then(function (registrations) {
            return Promise.all(registrations.map(function (registration) { return registration.update(); }));
        }).then(function () {
            if (status) status.textContent = 'Cached app files checked for updates.';
        }).catch(function () {
            if (status) status.textContent = 'Could not refresh cached app files.';
        });
    }

    function registerPwa() {
        var manifest = document.createElement('link');
        manifest.rel = 'manifest';
        manifest.href = 'manifest.webmanifest';
        document.head.appendChild(manifest);

        var icon = document.createElement('link');
        icon.rel = 'apple-touch-icon';
        icon.href = 'assets/icons/family-tracker.svg';
        document.head.appendChild(icon);

        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function () {
                navigator.serviceWorker.register('service-worker.js').catch(function () { });
            });
        }
    }

    window.addEventListener('beforeinstallprompt', function (event) {
        event.preventDefault();
        installPrompt = event;
        updateInstallButton();
    });
    window.addEventListener('appinstalled', function () {
        installPrompt = null;
        updateInstallButton();
    });
    window.addEventListener('online', updateOfflineBanner);
    window.addEventListener('offline', updateOfflineBanner);

    function boot() {
        registerPwa();
        applyPreferences();
        createUi();
        window.setInterval(createUi, 3000);
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
    else boot();
}());

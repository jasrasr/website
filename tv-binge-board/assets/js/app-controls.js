/**
 * File: assets/js/app-controls.js
 * Project: TV Binge Board
 * Description: Manual app reload controls for refreshing cached PWA shell files without showing large update banners.
 * Author: Jason Lamb / ChatGPT
 * Created: 2026-07-05
 * Modified: 2026-07-05
 * Revision: 1.0.0
 */

(function () {
    'use strict';

    function setStatus(message) {
        document.querySelectorAll('[data-app-update-status]').forEach(function (element) {
            element.textContent = message || '';
        });
    }

    function reloadLatestApp() {
        setStatus('Reloading latest app files...');
        if (!('serviceWorker' in navigator)) {
            window.location.reload();
            return;
        }
        navigator.serviceWorker.getRegistration().then(function (registration) {
            if (registration && registration.waiting) {
                registration.waiting.postMessage({ type: 'SKIP_WAITING' });
                window.setTimeout(function () { window.location.reload(); }, 900);
                return;
            }
            if (registration) {
                registration.update().finally(function () { window.location.reload(); });
                return;
            }
            window.location.reload();
        }).catch(function () { window.location.reload(); });
    }

    document.querySelectorAll('[data-app-reload]').forEach(function (button) {
        button.addEventListener('click', reloadLatestApp);
    });
}());

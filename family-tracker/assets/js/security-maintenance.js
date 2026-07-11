/**
 * Project: Family GPS Tracker
 * File: assets/js/security-maintenance.js
 * Revision: 1.5.4
 * Description: Consent review overlay, security cleanup controls, and clearer disabled-access messaging.
 * Author: Jason Lamb / ChatGPT scaffold
 * Created: 2026-07-11
 * Modified: 2026-07-11
 */
(function () {
    'use strict';

    var csrfToken = '';

    function status(text) {
        var node = document.getElementById('statusText');
        if (node) node.textContent = text;
    }

    function loadStyles() {
        if (document.getElementById('securityMaintenanceStyles')) return;
        var link = document.createElement('link');
        link.id = 'securityMaintenanceStyles';
        link.rel = 'stylesheet';
        link.href = 'assets/css/security-maintenance.css?v=1.5.4';
        document.head.appendChild(link);
    }

    function request(payload) {
        var options = { credentials: 'same-origin' };
        if (payload) {
            options.method = 'POST';
            options.headers = { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken };
            options.body = JSON.stringify(payload);
        }
        return fetch('security-maintenance.php', options).then(function (response) {
            return response.json().then(function (data) {
                if (!response.ok || !data.ok) throw new Error(data.error || 'Security request failed.');
                if (data.csrfToken) csrfToken = data.csrfToken;
                return data;
            });
        });
    }

    function removeConsentOverlay() {
        var overlay = document.getElementById('consentReviewOverlay');
        if (overlay) overlay.remove();
    }

    function showConsentOverlay(data) {
        if (!data.consentReviewRequired || document.getElementById('consentReviewOverlay')) return;
        var overlay = document.createElement('div');
        overlay.id = 'consentReviewOverlay';
        overlay.className = 'consent-overlay';
        overlay.innerHTML = '<section class="card consent-dialog"><p class="eyebrow">Privacy Review</p><h2>Review Location Sharing</h2><p>The tracker requests location when the signed-in app launches. Location updates may continue about every minute while the page remains open. Group members can see the latest saved location, city label, and retained trail data for the active group.</p><p class="muted">The browser controls the native permission prompt. You can revoke browser location permission later through browser or device settings.</p><label class="check-row"><input id="consentReviewCheckbox" type="checkbox"><span>I understand and consent to this location-sharing behavior.</span></label><button id="acceptConsentReviewBtn" type="button" disabled>Accept & Continue</button></section>';
        document.body.appendChild(overlay);
        var checkbox = document.getElementById('consentReviewCheckbox');
        var button = document.getElementById('acceptConsentReviewBtn');
        checkbox.addEventListener('change', function () { button.disabled = !checkbox.checked; });
        button.addEventListener('click', function () {
            button.disabled = true;
            request({ action: 'accept_consent' }).then(function (result) {
                removeConsentOverlay();
                status(result.message || 'Consent review accepted.');
            }).catch(function (error) {
                button.disabled = false;
                status(error.message || 'Could not save consent review.');
            });
        });
    }

    function addCleanupButton(data) {
        var privacyCard = document.getElementById('privacyLifecycleCard');
        if (!privacyCard || document.getElementById('cleanupSecurityRecordsBtn')) return;
        var button = document.createElement('button');
        button.id = 'cleanupSecurityRecordsBtn';
        button.type = 'button';
        button.className = 'secondary';
        button.textContent = 'Clean Security & Audit Records';
        var note = document.createElement('div');
        note.className = 'profile-edit-note';
        note.id = 'securityCleanupNote';
        note.textContent = 'Deletes expired remembered-device records, stale login-throttle files, and audit files older than ' + data.auditRetentionDays + ' days.';
        privacyCard.append(button, note);
        button.addEventListener('click', function () {
            if (!window.confirm('Run security and audit record cleanup now?')) return;
            button.disabled = true;
            request({ action: 'cleanup_records' }).then(function (result) {
                var deleted = result.deleted || {};
                status('Cleanup completed: ' + (deleted.expiredDevices || 0) + ' device records, ' + (deleted.throttleRecords || 0) + ' throttle records, ' + (deleted.auditFiles || 0) + ' audit files.');
            }).catch(function (error) {
                status(error.message || 'Cleanup failed.');
            }).finally(function () { button.disabled = false; });
        });
    }

    function improveAccessMessage() {
        var node = document.getElementById('statusText');
        if (!node) return;
        var text = node.textContent || '';
        if (text.indexOf('not a member of that group') >= 0 || text.indexOf('Active group not found') >= 0) {
            node.textContent = 'You no longer have access to the selected group. Choose another group, rejoin with an invite, or contact the group owner.';
        }
        if (text.indexOf('account is inactive') >= 0 || text.indexOf('account is inactive') >= 0) {
            node.textContent = 'This account is inactive. Contact the account administrator before trying again.';
        }
    }

    function boot() {
        loadStyles();
        request(null).then(function (data) {
            addCleanupButton(data);
            showConsentOverlay(data);
        }).catch(function () { });
        window.setInterval(improveAccessMessage, 1500);
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
    else boot();
}());

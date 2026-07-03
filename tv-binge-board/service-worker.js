/**
 * File: service-worker.js
 * Project: TV Binge Board
 * Description: PWA service worker for app-shell caching, offline fallback navigation, static asset cache hits, and user-triggered update activation.
 * Author: Jason Lamb / ChatGPT
 * Created: 2026-07-02
 * Modified: 2026-07-03
 * Revision: 1.5.4
 */

const CACHE_NAME = 'tv-binge-board-rev-1.5.4';
const OFFLINE_URL = 'offline.php';
const SHELL_ASSETS = [
    './',
    'index.php',
    'login.php',
    OFFLINE_URL,
    'install.php',
    'manifest.webmanifest',
    'app-icon.php?size=32',
    'app-icon.php?size=180',
    'app-icon.php?size=192',
    'app-icon.php?size=512',
    'assets/css/app.css',
    'assets/js/app.js',
    'assets/img/poster-placeholder.svg'
];
const STATIC_ASSETS = SHELL_ASSETS.filter(function (asset) { return asset !== './' && !asset.endsWith('.php') && !asset.includes('.php?'); });

self.addEventListener('install', function (event) {
    event.waitUntil(caches.open(CACHE_NAME).then(function (cache) {
        return cache.addAll(SHELL_ASSETS).catch(function () {});
    }));
});

self.addEventListener('activate', function (event) {
    event.waitUntil(caches.keys().then(function (keys) {
        return Promise.all(keys.filter(function (key) { return key !== CACHE_NAME; }).map(function (key) { return caches.delete(key); }));
    }).then(function () { return self.clients.claim(); }));
});

self.addEventListener('message', function (event) {
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
});

self.addEventListener('fetch', function (event) {
    if (event.request.method !== 'GET') { return; }

    const requestUrl = new URL(event.request.url);
    const isSameOrigin = requestUrl.origin === self.location.origin;

    if (event.request.mode === 'navigate') {
        event.respondWith(fetch(event.request).catch(function () { return caches.match(OFFLINE_URL); }));
        return;
    }

    if (isSameOrigin && STATIC_ASSETS.some(function (asset) { return requestUrl.pathname.endsWith(asset); })) {
        event.respondWith(caches.match(event.request).then(function (cached) {
            return cached || fetch(event.request).then(function (response) {
                const copy = response.clone();
                caches.open(CACHE_NAME).then(function (cache) { cache.put(event.request, copy); });
                return response;
            });
        }));
    }
});

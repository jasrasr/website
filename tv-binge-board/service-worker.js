/**
 * File: service-worker.js
 * Project: TV Binge Board
 * Description: PWA service worker for app-shell caching, offline fallback navigation, static asset cache hits, PWA screenshot asset caching, and user-triggered update activation.
 * Author: Jason Lamb / ChatGPT
 * Created: 2026-07-02
 * Modified: 2026-07-05
 * Revision: 1.5.23
 */

const CACHE_NAME = 'tv-binge-board-rev-1.5.23';
const OFFLINE_URL = 'offline.php';
const SHELL_ASSETS = [
    './',
    'index.php',
    'login.php',
    OFFLINE_URL,
    'install.php',
    'compare.php',
    'smart-import.php',
    'lists.php',
    'recommendations.php',
    'suggestions.php',
    'manifest.webmanifest',
    'assets/css/app.css',
    'assets/js/app.js',
    'assets/img/poster-placeholder.svg',
    'assets/icons/apple-touch-icon.png',
    'assets/icons/apple-touch-icon-180.png',
    'assets/icons/icon-jl-192.png',
    'assets/icons/icon-jl-512.png',
    'assets/icons/icon-192.png',
    'assets/icons/icon-512.png',
    'assets/screenshots/pwa-mobile-list.svg',
    'assets/screenshots/pwa-mobile-search-import.svg',
    'assets/screenshots/pwa-desktop-dashboard.svg'
];
const STATIC_ASSETS = SHELL_ASSETS.filter(function (asset) { return asset !== './' && !asset.endsWith('.php'); });

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

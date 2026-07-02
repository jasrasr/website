/**
 * File: service-worker.js
 * Project: TV Binge Board
 * Description: Minimal service worker for PWA installability and shell asset caching.
 * Author: Jason Lamb / ChatGPT
 * Created: 2026-07-02
 * Modified: 2026-07-02
 * Revision: 1.4.2
 */


const CACHE_NAME = 'tv-binge-board-rev-1.4.2';
const SHELL_ASSETS = ['index.php', 'login.php', 'assets/css/app.css', 'assets/js/app.js', 'assets/img/poster-placeholder.svg', 'assets/icons/icon-192.png', 'assets/icons/icon-512.png'];
self.addEventListener('install', function (event) {
    event.waitUntil(caches.open(CACHE_NAME).then(function (cache) { return cache.addAll(SHELL_ASSETS).catch(function () {}); }).then(function () { return self.skipWaiting(); }));
});
self.addEventListener('activate', function (event) {
    event.waitUntil(caches.keys().then(function (keys) { return Promise.all(keys.filter(function (key) { return key !== CACHE_NAME; }).map(function (key) { return caches.delete(key); })); }).then(function () { return self.clients.claim(); }));
});
self.addEventListener('fetch', function (event) {
    if (event.request.method !== 'GET') return;
    event.respondWith(fetch(event.request).catch(function () { return caches.match(event.request); }));
});

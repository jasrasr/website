/*
 * Project: Family GPS Tracker
 * File: service-worker.js
 * Revision: 1.6.5
 * Description: Conservative app-shell caching and offline fallback for static tracker assets.
 * Author: Jason Lamb / ChatGPT scaffold
 * Created: 2026-07-11
 * Modified: 2026-07-14
 */

const CACHE_NAME = 'family-tracker-shell-v1.6.5';
const APP_SHELL = [
  './',
  './index.php',
  './assets/css/style.css',
  './assets/css/security-maintenance.css',
  './assets/js/pwa-ui.js',
  './assets/js/dashboard-layout.js',
  './assets/js/security-maintenance.js',
  './assets/js/geofences.js',
  './assets/js/member-badges.js',
  './assets/js/member-sections.js',
  './assets/js/account-security.js',
  './assets/js/status-banners.js',
  './assets/icons/family-tracker.svg',
  './manifest.webmanifest'
];

self.addEventListener('install', event => {
  event.waitUntil(caches.open(CACHE_NAME).then(cache => cache.addAll(APP_SHELL)).catch(() => undefined));
  self.skipWaiting();
});

self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys => Promise.all(keys.filter(key => key !== CACHE_NAME).map(key => caches.delete(key))))
  );
  self.clients.claim();
});

self.addEventListener('fetch', event => {
  const request = event.request;
  if (request.method !== 'GET') return;

  const url = new URL(request.url);
  if (url.origin !== self.location.origin) return;

  const isApiLike = url.pathname.endsWith('.php') && !url.pathname.endsWith('/index.php');
  if (isApiLike) {
    event.respondWith(fetch(request));
    return;
  }

  event.respondWith(
    fetch(request)
      .then(response => {
        const copy = response.clone();
        caches.open(CACHE_NAME).then(cache => cache.put(request, copy)).catch(() => undefined);
        return response;
      })
      .catch(() => caches.match(request).then(match => match || caches.match('./index.php')))
  );
});



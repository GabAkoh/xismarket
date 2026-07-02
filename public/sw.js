// Minimal service worker — enables PWA installability ("Add to home screen").
// Network passthrough only; no offline caching (the app needs the server anyway).
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (event) => event.waitUntil(self.clients.claim()));
self.addEventListener('fetch', () => { /* passthrough */ });

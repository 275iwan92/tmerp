// Service worker MINIMAL -- syarat teknis "installable" (Chrome/Android
// butuh SW aktif dgn fetch handler utk memunculkan prompt install; iOS
// Safari sebenarnya tidak butuh ini sama sekali). SENGAJA tidak melakukan
// caching agresif -- aplikasi ini Livewire (server-rendered + AJAX per
// interaksi), cache-first bisa menyajikan HTML/state basi & merusak CSRF
// token. Cuma pass-through ke network apa adanya.
self.addEventListener('install', (event) => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('fetch', (event) => {
    event.respondWith(fetch(event.request));
});

const CACHE_NAME = 'avenpay-api-v2';
const ASSETS = ['/', '/login', '/icon.png'];

// Install: Cache core files
self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE_NAME).then((cache) => cache.addAll(ASSETS)));
});

// Activate: Clean up old versions
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => Promise.all(
            keys.map((key) => { if (key !== CACHE_NAME) return caches.delete(key); })
        ))
    );
});

// Fetch: Serve from cache, update in background
self.addEventListener('fetch', (event) => {
    event.respondWith(
        caches.match(event.request).then((cachedResponse) => {
            const fetchPromise = fetch(event.request).then((networkResponse) => {
                caches.open(CACHE_NAME).then((cache) => cache.put(event.request, networkResponse.clone()));
                return networkResponse;
            }).catch(() => {
                // If network fails (e.g. "Site can't be reached"), return cached version
                return cachedResponse;
            });
            return cachedResponse || fetchPromise;
        })
    );
});

// Push Notifications
self.addEventListener('push', function(event) {
    let title = 'EarnLix Digital'; 
    let body = 'You have a new update!';
    if (event.data) {
        try {
            const data = event.data.json();
            title = data.title || title;
            body = data.body || body;
        } catch (e) { body = event.data.text(); }
    }
    const options = { body, icon: '/icon.png', badge: '/icon.png', requireInteraction: true };
    event.waitUntil(self.registration.showNotification(title, options));
});
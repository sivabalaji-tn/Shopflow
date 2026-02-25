// sw.js — ShopFlow Service Worker
// Handles: offline caching, network-first strategy, offline fallback

const CACHE_VERSION = 'shopflow-v1.2';
const STATIC_CACHE  = `${CACHE_VERSION}-static`;
const DYNAMIC_CACHE = `${CACHE_VERSION}-dynamic`;
const IMAGE_CACHE   = `${CACHE_VERSION}-images`;

// Assets to cache immediately on install
const STATIC_ASSETS = [
    '/shopflow/offline.php',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css',
    'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js',
];

// ── Install ──────────────────────────────────────────────────
self.addEventListener('install', event => {
    console.log('[SW] Installing…');
    event.waitUntil(
        caches.open(STATIC_CACHE)
            .then(cache => cache.addAll(STATIC_ASSETS))
            .then(() => self.skipWaiting())
            .catch(err => console.warn('[SW] Static cache failed:', err))
    );
});

// ── Activate ─────────────────────────────────────────────────
self.addEventListener('activate', event => {
    console.log('[SW] Activating…');
    event.waitUntil(
        caches.keys().then(keys =>
            Promise.all(
                keys
                    .filter(k => k.startsWith('shopflow-') && k !== STATIC_CACHE && k !== DYNAMIC_CACHE && k !== IMAGE_CACHE)
                    .map(k => { console.log('[SW] Deleting old cache:', k); return caches.delete(k); })
            )
        ).then(() => self.clients.claim())
    );
});

// ── Fetch Strategy ───────────────────────────────────────────
self.addEventListener('fetch', event => {
    const { request } = event;
    const url = new URL(request.url);

    // Skip non-GET, chrome-extension, and POST requests
    if (request.method !== 'GET') return;
    if (url.protocol === 'chrome-extension:') return;

    // Cart actions — always network only (never cache)
    if (url.pathname.includes('cart_action.php')) return;

    // Images — Cache First, then network
    if (request.destination === 'image' || url.pathname.match(/\.(jpg|jpeg|png|gif|webp|svg|ico)$/i)) {
        event.respondWith(cacheFirst(request, IMAGE_CACHE));
        return;
    }

    // Static assets (CSS, JS, fonts) — Cache First
    if (
        request.destination === 'style' ||
        request.destination === 'script' ||
        request.destination === 'font' ||
        url.pathname.match(/\.(css|js|woff|woff2|ttf)$/i)
    ) {
        event.respondWith(cacheFirst(request, STATIC_CACHE));
        return;
    }

    // PHP pages — Network First, fallback to cache, then offline page
    event.respondWith(networkFirst(request));
});

// ── Strategy: Cache First ────────────────────────────────────
async function cacheFirst(request, cacheName) {
    const cache    = await caches.open(cacheName);
    const cached   = await cache.match(request);
    if (cached) return cached;
    try {
        const response = await fetch(request);
        if (response.ok) cache.put(request, response.clone());
        return response;
    } catch {
        return new Response('', { status: 404 });
    }
}

// ── Strategy: Network First ──────────────────────────────────
async function networkFirst(request) {
    const cache = await caches.open(DYNAMIC_CACHE);
    try {
        const response = await fetch(request);
        if (response.ok) {
            cache.put(request, response.clone());
        }
        return response;
    } catch {
        // Offline: try cache first
        const cached = await cache.match(request);
        if (cached) return cached;
        // Check static cache too
        const staticCached = await caches.match(request);
        if (staticCached) return staticCached;
        // Last resort: offline page
        const offlinePage = await caches.match('/shopflow/offline.php');
        return offlinePage || new Response('<h2 style="text-align:center;padding:40px;font-family:sans-serif;">You are offline</h2>', {
            headers: { 'Content-Type': 'text/html' }
        });
    }
}

// ── Background Sync (future-ready) ──────────────────────────
self.addEventListener('sync', event => {
    if (event.tag === 'sync-cart') {
        console.log('[SW] Background sync: cart');
    }
});

// ── Push Notifications (future-ready) ───────────────────────
self.addEventListener('push', event => {
    const data = event.data?.json() || {};
    const title   = data.title   || 'ShopFlow';
    const options = {
        body:    data.body    || 'You have a new notification',
        icon:    data.icon    || '/shopflow/assets/icons/icon-192.png',
        badge:   '/shopflow/assets/icons/icon-72.png',
        vibrate: [100, 50, 100],
        data:    { url: data.url || '/shopflow/' }
    };
    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', event => {
    event.notification.close();
    const url = event.notification.data?.url || '/shopflow/';
    event.waitUntil(clients.openWindow(url));
});

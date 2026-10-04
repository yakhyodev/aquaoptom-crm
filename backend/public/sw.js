/**
 * AquaOptom Wholesale Beverage CRM — PWA Service Worker (App Shell & Offline Caching)
 * Version: 1.0.0
 */

const CACHE_NAME = 'aquaoptom-pos-v1';

const PRECACHE_ASSETS = [
    '/pos',
    '/offline.html',
    '/manifest.json',
    '/icons/icon.svg',
    '/icons/icon-192.png',
    '/icons/icon-512.png'
];

// 1. Install event — Cache core App Shell
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            return cache.addAll(PRECACHE_ASSETS);
        }).then(() => {
            return self.skipWaiting();
        })
    );
});

// 2. Activate event — Clean up old caches & take immediate control
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((name) => {
                    if (name !== CACHE_NAME) {
                        return caches.delete(name);
                    }
                })
            );
        }).then(() => {
            return self.clients.claim();
        })
    );
});

// 3. Fetch event — Intelligent offline routing strategy
self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);

    // Faqat bir xil domen (same-origin) so'rovlarini boshqaramiz
    if (url.origin !== self.location.origin) {
        return;
    }

    // A. API so'rovlari (/api/*)
    // Offline bo'lganda to'xtatib qolmay, xavfsiz JSON offline javob qaytarish
    if (url.pathname.startsWith('/api/')) {
        event.respondWith(
            fetch(event.request).catch(() => {
                return new Response(
                    JSON.stringify({
                        success: false,
                        offline: true,
                        error: 'NETWORK_DISCONNECTED',
                        message: "Internet bilan aloqa mavjud emas. Operatsiya lokal IndexedDB navbatida saqlanadi."
                    }),
                    {
                        status: 503,
                        headers: { 'Content-Type': 'application/json' }
                    }
                );
            })
        );
        return;
    }

    // B. Sahifaga o'tish (HTML navigation requests)
    if (event.request.mode === 'navigate') {
        event.respondWith(
            fetch(event.request).then((networkResponse) => {
                // Tarmoq bor bo'lsa, /pos sahifasining yangi keshini saqlab qo'yamiz
                if (networkResponse.status === 200 && url.pathname.startsWith('/pos')) {
                    const responseClone = networkResponse.clone();
                    caches.open(CACHE_NAME).then((cache) => {
                        cache.put(event.request, responseClone);
                    });
                }
                return networkResponse;
            }).catch(async () => {
                // Tarmoq uzilganda keshdan qidiramiz
                const cachedPage = await caches.match(event.request);
                if (cachedPage) {
                    return cachedPage;
                }
                // Agar aynan o'sha sahifa bo'lmasa, /pos keshini yoki /offline.html ni beramiz
                const posShell = await caches.match('/pos');
                if (posShell) {
                    return posShell;
                }
                return caches.match('/offline.html');
            })
        );
        return;
    }

    // C. Statik resurslar (CSS, JS, Fonts, Images)
    // Stale-While-Revalidate yoki Cache-First
    event.respondWith(
        caches.match(event.request).then((cachedResponse) => {
            const fetchPromise = fetch(event.request).then((networkResponse) => {
                if (networkResponse.status === 200) {
                    const responseClone = networkResponse.clone();
                    caches.open(CACHE_NAME).then((cache) => {
                        cache.put(event.request, responseClone);
                    });
                }
                return networkResponse;
            }).catch(() => {
                // Tarmoq xatosi bo'lsa, mavjud kesh qaytadi
                return null;
            });

            // Agar keshda bo'lsa darhol beramiz, aks holda tarmoqdan kutamiz
            return cachedResponse || fetchPromise;
        })
    );
});

// 4. Background Sync event (agar brauzer qo'llab-quvvatlasa)
// "mavjud bo‘lsa background sync, yopiq appda barcha platforma uchun darhol yuborish va’dasi yo‘q"
self.addEventListener('sync', (event) => {
    if (event.tag === 'aqua-sync') {
        event.waitUntil(
            self.clients.matchAll({ type: 'window' }).then((clientList) => {
                for (const client of clientList) {
                    client.postMessage({ type: 'BACKGROUND_SYNC_TRIGGERED' });
                }
            })
        );
    }
});

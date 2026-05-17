const CACHE_NAME = 'zynearn-v1';
const CORE_ASSETS = [
  '/zyn-earn/index.php',
  '/zyn-earn/assets/css/style.css',
  '/zyn-earn/assets/js/app.js',
  '/zyn-earn/assets/js/auth.js',
  '/zyn-earn/assets/images/logo.png',
  '/zyn-earn/assets/images/icon-192.png',
  '/zyn-earn/offline.php',
  'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css',
  'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap',
  'https://fonts.gstatic.com/s/inter/v12/UcCO3FwrK3iLTeHuS_fvQtMwCp50KnMw2boKoduKmMEVuLyfAZ9hjpQ.woff2'
];

const API_CACHE = 'zynearn-api-v1';
const STATIC_CACHE = 'zynearn-static-v1';
const MAX_API_CACHE_AGE = 300;
const MAX_STATIC_CACHE_AGE = 86400;

self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        return cache.addAll(CORE_ASSETS);
      })
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', event => {
  const cacheWhitelist = [CACHE_NAME, API_CACHE, STATIC_CACHE];

  event.waitUntil(
    caches.keys().then(cacheNames => {
      return Promise.all(
        cacheNames.map(cacheName => {
          if (!cacheWhitelist.includes(cacheName)) {
            return caches.delete(cacheName);
          }
        })
      );
    }).then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', event => {
  const { request } = event;
  const url = new URL(request.url);

  if (url.origin !== self.location.origin && !url.href.includes('cdnjs.cloudflare.com') && !url.href.includes('fonts.googleapis.com') && !url.href.includes('fonts.gstatic.com')) {
    return;
  }

  if (url.pathname.startsWith('/zyn-earn/api/')) {
    event.respondWith(networkFirstWithTimeout(request, API_CACHE, MAX_API_CACHE_AGE));
    return;
  }

  if (request.destination === 'style' || request.destination === 'font' || request.destination === 'image' || request.url.match(/\.(css|js|png|jpg|jpeg|gif|webp|svg|woff|woff2|ico)$/)) {
    event.respondWith(cacheFirstWithRefresh(request, STATIC_CACHE, MAX_STATIC_CACHE_AGE));
    return;
  }

  if (request.mode === 'navigate') {
    event.respondWith(networkFirstWithFallback(request));
    return;
  }

  event.respondWith(caches.match(request).then(response => response || fetch(request)));
});

async function networkFirstWithTimeout(request, cacheName, maxAge) {
  const cache = await caches.open(cacheName);
  const cachedResponse = await cache.match(request);

  if (cachedResponse) {
    const age = (Date.now() - new Date(cachedResponse.headers.get('sw-cached-at') || 0).getTime()) / 1000;
    if (age < maxAge) {
      return cachedResponse;
    }
  }

  try {
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 5000);
    const networkResponse = await fetch(request, { signal: controller.signal });
    clearTimeout(timeoutId);

    if (networkResponse && networkResponse.ok) {
      const headers = new Headers(networkResponse.headers);
      headers.set('sw-cached-at', new Date().toISOString());
      const responseToCache = new Response(networkResponse.clone().body, {
        status: networkResponse.status,
        statusText: networkResponse.statusText,
        headers: headers
      });
      cache.put(request, responseToCache);
    }

    return networkResponse;
  } catch (error) {
    if (cachedResponse) {
      return cachedResponse;
    }
    return new Response(JSON.stringify({ error: 'Network error', offline: true }), {
      status: 503,
      headers: { 'Content-Type': 'application/json' }
    });
  }
}

async function cacheFirstWithRefresh(request, cacheName, maxAge) {
  const cache = await caches.open(cacheName);
  const cachedResponse = await cache.match(request);

  if (cachedResponse) {
    const age = (Date.now() - new Date(cachedResponse.headers.get('sw-cached-at') || 0).getTime()) / 1000;
    if (age < maxAge) {
      fetchAndUpdateCache(request, cache);
      return cachedResponse;
    }
  }

  return fetchAndUpdateCache(request, cache).catch(() => cachedResponse || fetch(request));
}

async function fetchAndUpdateCache(request, cache) {
  const networkResponse = await fetch(request);
  if (networkResponse && networkResponse.ok) {
    const headers = new Headers(networkResponse.headers);
    headers.set('sw-cached-at', new Date().toISOString());
    const responseToCache = new Response(networkResponse.clone().body, {
      status: networkResponse.status,
      statusText: networkResponse.statusText,
      headers: headers
    });
    cache.put(request, responseToCache);
  }
  return networkResponse;
}

async function networkFirstWithFallback(request) {
  try {
    const networkResponse = await fetch(request);
    if (networkResponse && networkResponse.ok) {
      const cache = await caches.open(CACHE_NAME);
      cache.put(request, networkResponse.clone());
      return networkResponse;
    }
    throw new Error('Network response not ok');
  } catch (error) {
    const cachedResponse = await caches.match(request);
    if (cachedResponse) {
      return cachedResponse;
    }
    const offlineResponse = await caches.match('/zyn-earn/offline.php');
    if (offlineResponse) {
      return offlineResponse;
    }
    return new Response(
      '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Offline - ZynEarn</title><style>body{font-family:sans-serif;display:flex;flex-direction:column;align-items:center;justify-content:center;height:100vh;background:#0a0a1a;color:#fff;text-align:center;padding:2em} h1{font-size:2em;margin-bottom:.5em} p{color:#888;margin-bottom:2em} .icon{font-size:4em;margin-bottom:1em;color:#6C5CE7}</style></head><body><div class="icon">📡</div><h1>You\'re Offline</h1><p>Please check your internet connection and try again.</p></body></html>',
      { status: 503, headers: { 'Content-Type': 'text/html; charset=UTF-8' } }
    );
  }
}

self.addEventListener('sync', event => {
  if (event.tag === 'sync-pending-actions') {
    event.waitUntil(syncPendingActions());
  } else if (event.tag === 'sync-withdrawals') {
    event.waitUntil(syncWithdrawals());
  }
});

async function syncPendingActions() {
  try {
    const db = await openActionDB();
    const tx = db.transaction('pendingActions', 'readwrite');
    const store = tx.objectStore('pendingActions');
    const allActions = await store.getAll();

    for (const action of allActions) {
      try {
        const response = await fetch('/zyn-earn/api/sync.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(action)
        });
        if (response.ok) {
          await store.delete(action.id);
        }
      } catch (e) {
        console.error('Sync failed for action:', action.id, e);
      }
    }
  } catch (e) {
    console.error('Sync error:', e);
  }
}

async function syncWithdrawals() {
  try {
    const db = await openActionDB();
    const tx = db.transaction('pendingWithdrawals', 'readwrite');
    const store = tx.objectStore('pendingWithdrawals');
    const pending = await store.getAll();

    for (const w of pending) {
      try {
        const response = await fetch('/zyn-earn/api/payments.php?action=check-status', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ withdrawal_id: w.id })
        });
        if (response.ok) {
          const data = await response.json();
          if (data.status === 'completed' || data.status === 'failed') {
            await store.delete(w.id);
          }
        }
      } catch (e) {
        console.error('Withdrawal sync failed:', w.id, e);
      }
    }
  } catch (e) {
    console.error('Withdrawal sync error:', e);
  }
}

function openActionDB() {
  return new Promise((resolve, reject) => {
    const request = indexedDB.open('ZynEarnSync', 1);
    request.onupgradeneeded = event => {
      const db = event.target.result;
      if (!db.objectStoreNames.contains('pendingActions')) {
        db.createObjectStore('pendingActions', { keyPath: 'id' });
      }
      if (!db.objectStoreNames.contains('pendingWithdrawals')) {
        db.createObjectStore('pendingWithdrawals', { keyPath: 'id' });
      }
    };
    request.onsuccess = event => resolve(event.target.result);
    request.onerror = event => reject(event.target.error);
  });
}

self.addEventListener('push', event => {
  if (!event.data) return;

  try {
    const data = event.data.json();

    const options = {
      body: data.body || 'New notification from ZynEarn',
      icon: '/zyn-earn/assets/images/icon-192.png',
      badge: '/zyn-earn/assets/images/icon-96.png',
      vibrate: [200, 100, 200],
      data: {
        url: data.url || '/zyn-earn/index.php',
        id: data.id || null
      },
      actions: [
        { action: 'view', title: 'View' },
        { action: 'dismiss', title: 'Dismiss' }
      ],
      tag: data.tag || 'default',
      renotify: true,
      requireInteraction: true
    };

    event.waitUntil(
      self.registration.showNotification(data.title || 'ZynEarn', options)
    );
  } catch (e) {
    console.error('Push notification error:', e);
  }
});

self.addEventListener('notificationclick', event => {
  event.notification.close();

  if (event.action === 'dismiss') return;

  const urlToOpen = event.notification.data?.url || '/zyn-earn/index.php';

  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then(windowClients => {
      for (const client of windowClients) {
        if (client.url === urlToOpen && 'focus' in client) {
          return client.focus();
        }
      }
      if (clients.openWindow) {
        return clients.openWindow(urlToOpen);
      }
    })
  );
});

self.addEventListener('message', event => {
  if (event.data && event.data.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }

  if (event.data && event.data.type === 'CLEAR_CACHE') {
    caches.delete(CACHE_NAME);
    caches.delete(API_CACHE);
    caches.delete(STATIC_CACHE);
  }

  if (event.data && event.data.type === 'ADD_TO_SYNC') {
    const action = event.data.action;
    if (action) {
      openActionDB().then(db => {
        const tx = db.transaction('pendingActions', 'readwrite');
        const store = tx.objectStore('pendingActions');
        store.put(action);
        return self.registration.sync.register('sync-pending-actions');
      });
    }
  }
});

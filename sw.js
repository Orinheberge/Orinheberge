/**
 * OrinHeberge — Service Worker Optimisé (v2)
 * Stratégies multiples : Cache First, Network First, Stale-While-Revalidate
 * Gestion intelligente du cache avec limites et expiration
 */

'use strict';

// ============================================
// CONFIGURATION
// ============================================
const CACHE_VERSION = '0.1.0';
const CACHE_NAME = `orinstone-cache-v${CACHE_VERSION}`;
const STATIC_CACHE = `orinstone-static-v${CACHE_VERSION}`;
const DYNAMIC_CACHE = `orinstone-dynamic-v${CACHE_VERSION}`;

// Limites du cache dynamique
const CACHE_LIMITS = {
    dynamic: 50, // Max 50 entrées dans le cache dynamique
    maxAge: 7 * 24 * 60 * 60 * 1000, // 7 jours en ms
};

// Assets statiques à pré-cacher (same-origin uniquement)
const STATIC_ASSETS = [
    '/',
    '/index.php',
    '/manifest.json',
    './inc/clients_sidebar.js',
    './inc/navbar.js',
    './inc/admin_sidebar.js',
    './inc/notifications.js',
    './inc/lang_switcher.js',
    './inc/accueil.js',
    './inc/clients_sidebar.css',
    './inc/admin_sidebar.css',
    './inc/chat.js',
    './inc/chat.css',
    './inc/navbar.css',
    './favicon.ico',
    './favicon.png',
];

// Patterns pour stratégies spécifiques
const STRATEGIES = {
    // Cache First : Pour assets statiques rarement modifiés
    cacheFirst: [
        /\.(?:js|css|woff2?|ttf|eot|svg|png|jpg|jpeg|gif|webp|ico)$/i,
    ],
    
    // Network First : Pour contenu dynamique critique
    networkFirst: [
        /^\/api\//,
        /^\/client\//,
        /^\/shop\//,
        /^\/profil/,
        /nocache/i,
    ],
    
    // Jamais cacher : Pages admin, requêtes POST, etc.
    neverCache: [
        /^\/admin\//,
        /^\/logout/,
        /^\/login/,
        /^\/register/,
    ],
};

// ============================================
// UTILITAIRES
// ============================================

/**
 * Vérifie si une URL correspond à un pattern
 */
function matchesPattern(url, patterns) {
    return patterns.some(pattern => pattern.test(url));
}

/**
 * Limite la taille du cache dynamique
 */
async function trimCache(cacheName, maxItems) {
    const cache = await caches.open(cacheName);
    const keys = await cache.keys();
    
    if (keys.length > maxItems) {
        // Supprime les entrées les plus anciennes
        const toDelete = keys.slice(0, keys.length - maxItems);
        await Promise.all(toDelete.map(key => cache.delete(key)));
        console.log(`[SW] Cache ${cacheName} trimmé : ${toDelete.length} entrées supprimées`);
    }
}

/**
 * Nettoie les caches expirés
 */
async function cleanupExpiredCaches() {
    const cacheNames = await caches.keys();
    const now = Date.now();
    
    for (const cacheName of cacheNames) {
        if (cacheName.startsWith('orinstone-') && cacheName !== CACHE_NAME && 
            cacheName !== STATIC_CACHE && cacheName !== DYNAMIC_CACHE) {
            await caches.delete(cacheName);
            console.log(`[SW] Ancien cache supprimé : ${cacheName}`);
        }
    }
}

// ============================================
// INSTALLATION
// ============================================
self.addEventListener('install', (event) => {
    console.log('[SW] Installation en cours...');
    
    event.waitUntil(
        caches.open(STATIC_CACHE)
            .then((cache) => {
                console.log('[SW] Mise en cache des assets statiques');
                // Utilise addAll mais gère les erreurs individuellement
                return Promise.allSettled(
                    STATIC_ASSETS.map(url => 
                        cache.add(url).catch(err => {
                            console.warn(`[SW] Échec du cache pour ${url}:`, err.message);
                            return null; // Continue même si un asset échoue
                        })
                    )
                );
            })
            .then(() => {
                console.log('[SW] Installation terminée');
                return self.skipWaiting();
            })
    );
});

// ============================================
// ACTIVATION
// ============================================
self.addEventListener('activate', (event) => {
    console.log('[SW] Activation en cours...');
    
    event.waitUntil(
        Promise.all([
            // Nettoyer les anciens caches
            caches.keys().then((cacheNames) => {
                return Promise.all(
                    cacheNames.map((cache) => {
                        if (cache !== CACHE_NAME && cache !== STATIC_CACHE && cache !== DYNAMIC_CACHE) {
                            console.log('[SW] Suppression de l\'ancien cache :', cache);
                            return caches.delete(cache);
                        }
                    })
                );
            }),
            
            // Nettoyer les caches expirés
            cleanupExpiredCaches(),
        ])
        .then(() => {
            console.log('[SW] Activation terminée');
            return self.clients.claim();
        })
    );
});

// ============================================
// STRATÉGIES DE CACHE
// ============================================

/**
 * Cache First : Sert du cache, fallback réseau
 * Idéal pour : JS, CSS, images, fonts
 */
async function cacheFirst(request) {
    const cache = await caches.open(STATIC_CACHE);
    const cachedResponse = await cache.match(request);
    
    if (cachedResponse) {
        // Met à jour en arrière-plan si nécessaire
        fetch(request).then(networkResponse => {
            if (networkResponse.status === 200) {
                cache.put(request, networkResponse.clone());
            }
        }).catch(() => {});
        
        return cachedResponse;
    }
    
    // Fallback réseau
    try {
        const networkResponse = await fetch(request);
        if (networkResponse.status === 200) {
            cache.put(request, networkResponse.clone());
        }
        return networkResponse;
    } catch (error) {
        console.warn('[SW] CacheFirst échec réseau:', request.url);
        throw error;
    }
}

/**
 * Network First : Tente le réseau, fallback cache
 * Idéal pour : API, pages dynamiques critiques
 */
async function networkFirst(request) {
    const cache = await caches.open(DYNAMIC_CACHE);
    
    try {
        const networkResponse = await fetch(request);
        
        if (networkResponse.status === 200) {
            cache.put(request, networkResponse.clone());
            // Limite la taille du cache dynamique
            await trimCache(DYNAMIC_CACHE, CACHE_LIMITS.dynamic);
        }
        
        return networkResponse;
    } catch (error) {
        console.warn('[SW] NetworkFirst échec réseau, utilisation du cache:', request.url);
        const cachedResponse = await cache.match(request);
        
        if (cachedResponse) {
            return cachedResponse;
        }
        
        // Page offline personnalisée si disponible
        const offlinePage = await cache.match('/offline.html');
        if (offlinePage) {
            return offlinePage;
        }
        
        throw error;
    }
}

/**
 * Stale-While-Revalidate : Cache immédiat + mise à jour en arrière-plan
 * Idéal pour : Pages HTML, contenu semi-statique
 */
async function staleWhileRevalidate(request) {
    const cache = await caches.open(DYNAMIC_CACHE);
    const cachedResponse = await cache.match(request);
    
    // Lance la mise à jour en arrière-plan
    const fetchPromise = fetch(request).then(networkResponse => {
        if (networkResponse.status === 200) {
            cache.put(request, networkResponse.clone());
            trimCache(DYNAMIC_CACHE, CACHE_LIMITS.dynamic);
        }
        return networkResponse;
    }).catch(err => {
        console.warn('[SW] SWR échec réseau:', request.url);
        return null;
    });
    
    // Retourne le cache immédiatement ou attend le réseau
    return cachedResponse || await fetchPromise;
}

/**
 * Network Only : Jamais de cache
 * Idéal pour : Admin, logout, requêtes sensibles
 */
async function networkOnly(request) {
    return fetch(request);
}

// ============================================
// FETCH HANDLER
// ============================================
self.addEventListener('fetch', (event) => {
    // Ignorer les requêtes non-GET
    if (event.request.method !== 'GET') {
        return;
    }
    
    const url = new URL(event.request.url);
    
    // ⚠️ Ne JAMAIS intercepter les requêtes cross-origin
    // (Stripe, CDN Tailwind, Font Awesome, etc.)
    if (url.origin !== self.location.origin) {
        return;
    }
    
    const pathname = url.pathname;
    
    // 1. Jamais cacher : Admin, logout, etc.
    if (matchesPattern(pathname, STRATEGIES.neverCache)) {
        event.respondWith(networkOnly(event.request));
        return;
    }
    
    // 2. Network First : API, pages dynamiques critiques
    if (matchesPattern(pathname, STRATEGIES.networkFirst)) {
        event.respondWith(networkFirst(event.request));
        return;
    }
    
    // 3. Cache First : Assets statiques (JS, CSS, images, fonts)
    if (matchesPattern(pathname, STRATEGIES.cacheFirst)) {
        event.respondWith(cacheFirst(event.request));
        return;
    }
    
    // 4. Stale-While-Revalidate : Tout le reste (pages HTML)
    event.respondWith(staleWhileRevalidate(event.request));
});

// ============================================
// MESSAGING (Communication avec le client)
// ============================================
self.addEventListener('message', (event) => {
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
    
    if (event.data && event.data.type === 'CLEAN_CACHE') {
        event.waitUntil(
            caches.keys().then(names => 
                Promise.all(names.map(name => caches.delete(name)))
            )
        );
    }
    
    if (event.data && event.data.type === 'CACHE_STATUS') {
        event.waitUntil(
            caches.keys().then(async names => {
                const stats = {};
                for (const name of names) {
                    const cache = await caches.open(name);
                    const keys = await cache.keys();
                    stats[name] = keys.length;
                }
                
                event.ports[0].postMessage({
                    type: 'CACHE_STATUS_RESPONSE',
                    stats: stats,
                });
            })
        );
    }
});

// ============================================
// BACKGROUND SYNC (Optionnel - pour requêtes offline)
// ============================================
self.addEventListener('sync', (event) => {
    if (event.tag === 'sync-notifications') {
        event.waitUntil(syncNotifications());
    }
});

async function syncNotifications() {
    try {
        const response = await fetch('/api/notifications/sync');
        if (response.ok) {
            console.log('[SW] Notifications synchronisées');
        }
    } catch (error) {
        console.warn('[SW] Échec sync notifications:', error);
    }
}

// ============================================
// PUSH NOTIFICATIONS (Optionnel)
// ============================================
self.addEventListener('push', (event) => {
    if (!event.data) return;
    
    const data = event.data.json();
    const options = {
        body: data.body || 'Nouvelle notification',
        icon: '/favicon.ico',
        badge: '/favicon.ico',
        vibrate: [100, 50, 100],
        data: {
            url: data.url || '/',
        },
    };
    
    event.waitUntil(
        self.registration.showNotification(data.title || 'OrinHeberge', options)
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    
    const urlToOpen = event.notification.data?.url || '/';
    
    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true })
            .then(clientList => {
                // Si une fenêtre est déjà ouverte, focus dessus
                for (const client of clientList) {
                    if (client.url.includes(self.location.origin) && 'focus' in client) {
                        return client.focus();
                    }
                }
                // Sinon, ouvre une nouvelle fenêtre
                if (clients.openWindow) {
                    return clients.openWindow(urlToOpen);
                }
            })
    );
});

console.log(`[SW] Service Worker v${CACHE_VERSION} chargé`);
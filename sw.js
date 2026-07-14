// Incrémentez ce numéro de version à chaque modification majeure de vos fichiers statiques
const CACHE_NAME = 'orinstone-cache-v1.1'; 

const ASSETS_TO_CACHE = [
  '/',
  '/index.php',
  '/manifest.json',
  'https://cdn.tailwindcss.com'
];

// Installation : Mise en cache initiale des composants indispensables
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      console.log('[Service Worker] Mise en cache des assets initiaux');
      return cache.addAll(ASSETS_TO_CACHE);
    })
  );
  self.skipWaiting();
});

// Activation : Nettoyage des anciens caches et prise de contrôle immédiate
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.map((cache) => {
          if (cache !== CACHE_NAME) {
            console.log('[Service Worker] Suppression de l\'ancien cache :', cache);
            return caches.delete(cache);
          }
        })
      );
    })
  );
  self.clients.claim();
});

// Stratégie : Stale-While-Revalidate (Sert le cache instantanément, puis télécharge la mise à jour en arrière-plan)
self.addEventListener('fetch', (event) => {
  // Ignorer les requêtes non-GET (connexions, formulaires, requêtes API POST)
  if (event.request.method !== 'GET') return;

  // Optionnel : Ne pas mettre en cache les pages d'administration ou dynamiques pour éviter les bugs d'affichage
  const url = new URL(event.request.url);
  if (url.pathname.startsWith('/admin/') || url.pathname.includes('nocache')) {
    event.respondWith(fetch(event.request));
    return;
  }

  event.respondWith(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.match(event.request).then((cachedResponse) => {
        // Lance la requête réseau en arrière-plan pour obtenir la version la plus récente
        const fetchPromise = fetch(event.request).then((networkResponse) => {
          if (networkResponse.status === 200) {
            // Met à jour le cache avec la nouvelle version fraîche du serveur
            cache.put(event.request, networkResponse.clone());
          }
          return networkResponse;
        }).catch((err) => {
          console.warn('[Service Worker] Échec de la récupération réseau (Hors-ligne) :', event.request.url);
        });

        // Retourne la version du cache immédiatement si elle existe, sinon attend le réseau
        return cachedResponse || fetchPromise;
      });
    })
  );
});
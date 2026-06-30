const CACHE_NAME = 'orinstone-cache-v1';

// Fichiers de base à mettre en cache immédiatement
const ASSETS_TO_CACHE = [
  '/',
  '/index.php',
  '/manifest.json',
  'https://cdn.tailwindcss.com'
];

// Installation du Service Worker et mise en cache des composants statiques
self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => {
      return cache.addAll(ASSETS_TO_CACHE);
    })
  );
  self.skipWaiting();
});

// Nettoyage des anciens caches si une nouvelle version est déployée
self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.map((cache) => {
          if (cache !== CACHE_NAME) {
            return caches.delete(cache);
          }
        })
      );
    })
  );
  self.clients.claim();
});

// Stratégie : Network First (Réseau en priorité, Cache en secours pour le dynamique)
self.addEventListener('fetch', (event) => {
  // On ne gère pas les requêtes POST (formulaire de profil, mot de passe) via le cache
  if (event.request.method !== 'GET') return;

  event.respondWith(
    fetch(event.request)
      .then((response) => {
        // Si la requête fonctionne, on enregistre ou met à jour le fichier dans le cache
        if (response.status === 200) {
          const responseClone = response.clone();
          caches.open(CACHE_NAME).then((cache) => {
            cache.put(event.request, responseClone);
          });
        }
        return response;
      })
      .catch(() => {
        // Si le réseau échoue (mode hors-ligne), on cherche dans le cache
        return caches.match(event.request);
      })
  );
});
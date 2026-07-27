// Incrémentez ce numéro de version à chaque modification majeure de vos fichiers statiques
const CACHE_NAME = 'orinstone-cache-v0.0.98';

// ⚠️ On ne précache QUE des ressources same-origin.
// https://cdn.tailwindcss.com a été retiré : étant cross-origin sans en-tête
// CORS, il faisait échouer cache.addAll() dans son ENSEMBLE (un seul échec
// suffit à faire planter tout le tableau), donc AUCUN asset n'était réellement
// mis en cache tant que cette ligne était présente.
const ASSETS_TO_CACHE = [
  '/',
  '/index.php',
  '/manifest.json',
  './inc/clients_sidebar.js',
  './inc/navbar.js',
  './inc/admin_sidebar.js',
  './inc/notifications.js',
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

  const url = new URL(event.request.url);

  // ⚠️ Ne JAMAIS intercepter les requêtes cross-origin (Stripe, CDN Tailwind,
  // Font Awesome, etc.) :
  //  - Stripe demande explicitement que js.stripe.com soit TOUJOURS chargé
  //    depuis le réseau, jamais servi depuis un cache (détection de fraude,
  //    mises à jour de sécurité poussées côté Stripe)
  //  - Les CDN cross-origin sans en-tête CORS renvoient des réponses
  //    "opaques" que cache.put() gère mal et qui provoquent les erreurs
  //    "blocked by CORS policy" vues précédemment
  // On laisse simplement le navigateur gérer ces requêtes nativement, sans
  // passer par cet event.respondWith().
  if (url.origin !== self.location.origin) {
    return;
  }

  // Ne pas mettre en cache les pages d'administration ou dynamiques pour éviter les bugs d'affichage
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
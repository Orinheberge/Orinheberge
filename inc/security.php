<?php
/**
 * security.php
 * Définit les en-têtes de sécurité HTTP, dont la Content-Security-Policy (CSP).
 *
 * IMPORTANT : à inclure tout en haut de chaque page, AVANT tout affichage
 * (avant session_start(), avant le moindre echo/HTML) car les header()
 * doivent partir avant que la moindre sortie ait été envoyée.
 *
 * Exemple d'utilisation dans index.php, webhook.php, etc. :
 *   require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/security.php';
 *
 * La policy ci-dessous autorise ce qui est réellement utilisé sur le site :
 *   - Stripe.js / Payment Element (js.stripe.com, m.stripe.network, blob:)
 *   - Tailwind CDN (cdn.tailwindcss.com)
 *   - Font Awesome CDN (cdnjs.cloudflare.com)
 * Si vous ajoutez un autre CDN/service plus tard, il faudra l'ajouter ici
 * aussi, sinon il sera bloqué par cette policy.
 */

$csp = [
    // Bloque tout par défaut sauf ce qui est explicitement autorisé plus bas
    "default-src" => "'self'",

    // Scripts : Stripe + Tailwind CDN. blob: est nécessaire pour les workers
    // internes de Stripe.js (détection de fraude, 3D Secure, Payment Element)
    "script-src" => "'self' https://js.stripe.com https://m.stripe.network https://cdn.tailwindcss.com blob:",

    // Web Workers utilisés par Stripe.js
    "worker-src" => "'self' blob:",

    // Styles : Font Awesome + Tailwind CDN injecte du CSS inline au runtime,
    // d'où le 'unsafe-inline' (Tailwind CDN ne fonctionne pas sans)
    "style-src" => "'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://cdn.tailwindcss.com",

    // Polices utilisées par Font Awesome
    "font-src" => "'self' https://cdnjs.cloudflare.com data:",

    // Images : local + logos/icônes Stripe + logos de paiement du footer
    "img-src" => "'self' data: https://*.stripe.com https://azurhosts.com https://heberge.orinstone.deepstone.fr",

    // Appels réseau (fetch/XHR) : votre API + Stripe
    "connect-src" => "'self' https://api.stripe.com https://m.stripe.network https://m.stripe.com",

    // Iframes : Stripe (3D Secure, Payment Element) + webhooks Stripe
    "frame-src" => "'self' https://js.stripe.com https://hooks.stripe.com",

    // Durcissement standard
    "object-src" => "'none'",
    "base-uri"   => "'self'",
    "form-action" => "'self'",
];

$policy = '';
foreach ($csp as $directive => $value) {
    $policy .= $directive . ' ' . $value . '; ';
}

header("Content-Security-Policy: " . trim($policy));

// Autres en-têtes de sécurité recommandés (sans lien avec Stripe, bonus)
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("Referrer-Policy: strict-origin-when-cross-origin");
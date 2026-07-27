<?php
/**
 * security.php - En-têtes de sécurité HTTP avec CSP optimisée pour Stripe
 */

if (!defined('CSP_NONCE')) {
    define('CSP_NONCE', base64_encode(random_bytes(16)));
}

$csp = [
    "default-src" => "'self'",

    // ✅ CORRECTION CRITIQUE : Ajout des domaines manquants pour Stripe
    "script-src" => "'self' 'nonce-" . CSP_NONCE . "' https://js.stripe.com https://m.stripe.network https://m.stripe.com https://cdn.tailwindcss.com blob:",

    "worker-src" => "'self' blob: https://js.stripe.com",

    // ✅ CORRECTION : Ajout de cdnjs.cloudflare.com pour Font Awesome CSS
    "style-src" => "'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://cdn.tailwindcss.com",

    "font-src" => "'self' https://cdnjs.cloudflare.com data:",

    // ✅ CORRECTION : Ajout de m.stripe.com et hooks.stripe.com
    "img-src" => "'self' data: https://*.stripe.com https://m.stripe.com https://azurhosts.com https://heberge.orinstone.deepstone.fr",

    // ✅ CORRECTION : Ajout de m.stripe.com (nécessaire pour les appels internes Stripe)
    "connect-src" => "'self' https://api.stripe.com https://m.stripe.network https://m.stripe.com",

    // ✅ CORRECTION : Ajout de hooks.stripe.com (nécessaire pour 3D Secure et redirections)
    "frame-src" => "'self' https://js.stripe.com https://hooks.stripe.com",

    "object-src" => "'none'",
    "base-uri"   => "'self'",
    "form-action" => "'self'",
];

$policy = '';
foreach ($csp as $directive => $value) {
    $policy .= $directive . ' ' . $value . '; ';
}

header("Content-Security-Policy: " . trim($policy));
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("Referrer-Policy: strict-origin-when-cross-origin");
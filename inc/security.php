<?php
/**
 * security.php - CSP optimisée et testée pour Stripe Elements
 */

if (!defined('CSP_NONCE')) {
    define('CSP_NONCE', base64_encode(random_bytes(16)));
}

$csp = [
    "default-src" => "'self'",

    // ✅ CRUCIAL : 'unsafe-inline' est requis pour que votre script avec nonce fonctionne, 
    // et 'blob:' est OBLIGATOIRE pour les scripts internes de Stripe.
    "script-src" => "'self' 'unsafe-inline' 'nonce-" . CSP_NONCE . "' https://js.stripe.com https://m.stripe.network https://m.stripe.com https://cdn.tailwindcss.com blob:",

    // ✅ CRUCIAL : Stripe utilise des Web Workers via blob:
    "worker-src" => "'self' blob: https://js.stripe.com",

    // ✅ CRUCIAL : Tailwind CDN a besoin de 'unsafe-inline' pour injecter ses styles au runtime
    "style-src" => "'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://cdn.tailwindcss.com",

    "font-src" => "'self' https://cdnjs.cloudflare.com data:",

    // ✅ CRUCIAL : Ajout de flagcdn.com (drapeaux des cartes) et m.stripe.com (logos)
    "img-src" => "'self' data: https://*.stripe.com https://m.stripe.com https://flagcdn.com https://azurhosts.com https://heberge.orinstone.deepstone.fr",

    // ✅ CRUCIAL : m.stripe.com est utilisé par Stripe pour la télémétrie et la validation
    "connect-src" => "'self' https://api.stripe.com https://m.stripe.network https://m.stripe.com",

    // ✅ CRUCIAL : hooks.stripe.com est OBLIGATOIRE pour les iframes de 3D Secure et redirections
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
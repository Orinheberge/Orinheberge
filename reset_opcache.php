<?php
/**
 * Script de reset OPcache — à appeler après chaque déploiement
 * URL: https://heberge.orinstone.deepstone.fr/reset_opcache.php?secret=VOTRE_SECRET
 */

$SECRET = 'RESET_OPCACHE_2025_ORINSTONE'; // À changer !

// Sécurité : vérifier le secret + origine locale
if (($_GET['secret'] ?? '') !== $SECRET) {
    http_response_code(403);
    die('Forbidden');
}

header('Content-Type: application/json');

$result = [
    'opcache_reset' => function_exists('opcache_reset') ? opcache_reset() : false,
    'opcache_status' => function_exists('opcache_get_status') ? opcache_get_status(false) : null,
    'realpath_cache_size' => realpath_cache_size(),
    'cleared_at' => date('c'),
];

echo json_encode($result, JSON_PRETTY_PRINT);
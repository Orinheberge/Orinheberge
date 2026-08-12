<?php
/*
|--------------------------------------------------------------------------
| PROXY WEBHOOK DISCORD
| Ce fichier relaie les notifications vers Discord
| depuis l'extérieur du conteneur Pterodactyl
|--------------------------------------------------------------------------
*/

// ⚠️ CE SECRET DOIT ÊTRE IDENTIQUE dans discord.php
define('PROXY_SECRET', 'orin_proxy_2026_secret');

// Autoriser uniquement les requêtes POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Method Not Allowed']);
    exit();
}

// Vérification de la clé secrète
$secret = $_SERVER['HTTP_X_PROXY_SECRET'] ?? '';
if ($secret !== PROXY_SECRET) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Forbidden - Invalid proxy secret']);
    exit();
}

// Récupération du corps de la requête
$body = file_get_contents('php://input');
if (empty($body)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Empty body']);
    exit();
}

// Validation de l'URL du webhook (sécurité : uniquement Discord)
$webhook_url = $_SERVER['HTTP_X_WEBHOOK_URL'] ?? '';
if (empty($webhook_url) || !str_starts_with($webhook_url, 'https://discord.com/api/webhooks/')) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid webhook URL - must be a Discord webhook']);
    exit();
}

// Relai vers Discord
$ch = curl_init($webhook_url);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $body,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
    CURLOPT_TIMEOUT        => 15,
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

// Log en cas d'erreur
if ($http_code < 200 || $http_code >= 300) {
    error_log("[Discord Proxy] Échec envoi vers Discord - HTTP {$http_code}: {$curl_error} | Réponse: {$response}");
}

// Retourner la réponse de Discord
http_response_code($http_code);
header('Content-Type: application/json');
echo $response;
<?php
/*
|--------------------------------------------------------------------------
| PROXY WEBHOOK DISCORD
| Ce fichier relaie les notifications vers Discord
| depuis l'extérieur du conteneur Pterodactyl
|--------------------------------------------------------------------------
*/

// Clé secrète pour sécuriser le proxy (à garder privée)
define('PROXY_SECRET', 'https://discord.com/api/webhooks/1505677242527649872/jFoANIv3OKNtGMib4bViJ79ltRDsf0LJviq59yXwW5hrqZ0uTyU1Yx3nV88yy6rG2eA4');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die("Method Not Allowed");
}

// Vérification clé secrète
$secret = $_SERVER['HTTP_X_PROXY_SECRET'] ?? '';
if ($secret !== PROXY_SECRET) {
    http_response_code(403);
    die("Forbidden");
}

$body = file_get_contents('php://input');
if (empty($body)) {
    http_response_code(400);
    die("Empty body");
}

$webhook_url = $_SERVER['HTTP_X_WEBHOOK_URL'] ?? '';
if (empty($webhook_url) || !str_starts_with($webhook_url, 'https://discord.com/api/webhooks/')) {
    http_response_code(400);
    die("Invalid webhook URL");
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
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

http_response_code($http_code);
echo $response;

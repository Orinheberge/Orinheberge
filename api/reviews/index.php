<?php
/**
 * OrinHeberge - API de gestion des avis clients
 * Endpoint: /api/reviews/index.php
 * Méthode: POST
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
    exit();
}

// ═══════════════════════════════════════════════════════════════
// CONFIGURATION
// ═══════════════════════════════════════════════════════════════

$reviews_file = __DIR__ . '/reviews.json';
$discord_webhook_url = "https://discord.com/api/webhooks/1533077645800112149/1tzAydW3IdVz_PgSL13YpZYDQbhSeLQTcyMzJBQw5lQEGeWDEtGaIiXNyFPhLHz5yeRo";

$max_name_length = 50;
$max_comment_length = 500;
$min_comment_length = 10;

$rate_limit_file = __DIR__ . '/rate_limit.json';
$rate_limit_seconds = 60;

// ═══════════════════════════════════════════════════════════════
// RATE LIMITING
// ═══════════════════════════════════════════════════════════════

function checkRateLimit($ip, $file, $limit_seconds) {
    if (!file_exists($file)) {
        @file_put_contents($file, json_encode([]));
    }
    
    $rate_data = json_decode(@file_get_contents($file), true);
    if (!is_array($rate_data)) $rate_data = [];
    
    $current_time = time();
    
    foreach ($rate_data as $stored_ip => $timestamp) {
        if ($current_time - $timestamp > $limit_seconds) {
            unset($rate_data[$stored_ip]);
        }
    }
    
    if (isset($rate_data[$ip]) && ($current_time - $rate_data[$ip]) < $limit_seconds) {
        return false;
    }
    
    $rate_data[$ip] = $current_time;
    @file_put_contents($file, json_encode($rate_data));
    
    return true;
}

// ═══════════════════════════════════════════════════════════════
// RÉCUPÉRATION ET VALIDATION
// ═══════════════════════════════════════════════════════════════

$raw_input = file_get_contents('php://input');
$input = json_decode($raw_input, true);

if (!$input || !is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Données JSON invalides']);
    exit();
}

$name = isset($input['name']) ? trim(strip_tags($input['name'])) : '';
$rating = isset($input['rating']) ? intval($input['rating']) : 0;
$comment = isset($input['comment']) ? trim(strip_tags($input['comment'])) : '';

$errors = [];

if (empty($name)) {
    $errors[] = 'Le nom est requis';
} elseif (strlen($name) > $max_name_length) {
    $errors[] = "Le nom ne peut pas dépasser $max_name_length caractères";
}

if ($rating < 1 || $rating > 5) {
    $errors[] = 'La note doit être entre 1 et 5';
}

if (empty($comment)) {
    $errors[] = 'Le commentaire est requis';
} elseif (strlen($comment) < $min_comment_length) {
    $errors[] = "Le commentaire doit faire au moins $min_comment_length caractères";
} elseif (strlen($comment) > $max_comment_length) {
    $errors[] = "Le commentaire ne peut pas dépasser $max_comment_length caractères";
}

$client_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
if (!checkRateLimit($client_ip, $rate_limit_file, $rate_limit_seconds)) {
    http_response_code(429);
    echo json_encode([
        'success' => false, 
        'error' => "Veuillez attendre $rate_limit_seconds secondes entre chaque avis"
    ]);
    exit();
}

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit();
}

// ═══════════════════════════════════════════════════════════════
// STOCKAGE
// ═══════════════════════════════════════════════════════════════

$review = [
    'id' => uniqid('review_', true),
    'name' => $name,
    'rating' => $rating,
    'comment' => $comment,
    'ip' => $client_ip,
    'created_at' => date('Y-m-d H:i:s'),
    'timestamp' => time()
];

$reviews = [];
if (file_exists($reviews_file)) {
    $decoded = json_decode(@file_get_contents($reviews_file), true);
    if (is_array($decoded)) $reviews = $decoded;
}

array_unshift($reviews, $review);

if (count($reviews) > 100) {
    $reviews = array_slice($reviews, 0, 100);
}

if (@file_put_contents($reviews_file, json_encode($reviews, JSON_UNESCAPED_UNICODE)) === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erreur lors de la sauvegarde']);
    exit();
}

// ═══════════════════════════════════════════════════════════════
// DISCORD
// ═══════════════════════════════════════════════════════════════

$discord_sent = false;

if (!empty($discord_webhook_url) && strpos($discord_webhook_url, 'VOTRE_URL') === false) {
    $stars = str_repeat('⭐', $rating);
    
    $payload = [
        'embeds' => [[
            'title' => '🌟 Nouvel Avis Client',
            'color' => 0xec4899,
            'fields' => [
                ['name' => '👤 Pseudo', 'value' => $name, 'inline' => true],
                ['name' => '⭐ Note', 'value' => $stars, 'inline' => true],
                ['name' => '💬 Commentaire', 'value' => substr($comment, 0, 200)]
            ],
            'footer' => ['text' => 'OrinHeberge - Système d\'avis'],
            'timestamp' => date('c')
        ]]
    ];
    
    $ch = curl_init($discord_webhook_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $discord_sent = ($http_code === 204 || $http_code === 200);
}

// ═══════════════════════════════════════════════════════════════
// RÉPONSE
// ═══════════════════════════════════════════════════════════════

http_response_code(201);
echo json_encode([
    'success' => true,
    'message' => 'Avis enregistré avec succès',
    'review_id' => $review['id'],
    'discord_notification' => $discord_sent
], JSON_UNESCAPED_UNICODE);
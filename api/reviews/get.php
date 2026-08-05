<?php
/**
 * OrinHeberge - API de récupération des avis clients
 * Endpoint: /api/reviews/get.php
 * Méthode: GET
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
    exit();
}

// ═══════════════════════════════════════════════════════════════
// CONFIGURATION
// ═══════════════════════════════════════════════════════════════

$reviews_file = __DIR__ . '/reviews.json';

$limit = isset($_GET['limit']) ? max(1, min(50, intval($_GET['limit']))) : 10;
$page  = isset($_GET['page'])  ? max(1, intval($_GET['page'])) : 1;
$min_rating = isset($_GET['min_rating']) ? max(1, min(5, intval($_GET['min_rating']))) : 0;

// ═══════════════════════════════════════════════════════════════
// LECTURE DES AVIS
// ═══════════════════════════════════════════════════════════════

$reviews = [];

if (file_exists($reviews_file)) {
    $raw = @file_get_contents($reviews_file);
    if ($raw !== false) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $reviews = $decoded;
        }
    }
}

// TRI : toujours du plus récent au plus ancien
usort($reviews, function($a, $b) {
    $tA = $a['timestamp'] ?? strtotime($a['created_at'] ?? 'now');
    $tB = $b['timestamp'] ?? strtotime($b['created_at'] ?? 'now');
    return $tB <=> $tA;
});

// ═══════════════════════════════════════════════════════════════
// FILTRAGE
// ═══════════════════════════════════════════════════════════════

if ($min_rating > 0) {
    $reviews = array_filter($reviews, function($review) use ($min_rating) {
        return isset($review['rating']) && $review['rating'] >= $min_rating;
    });
    $reviews = array_values($reviews);
}

// ═══════════════════════════════════════════════════════════════
// STATISTIQUES
// ═══════════════════════════════════════════════════════════════

$total_reviews = count($reviews);
$average_rating = 0;
$rating_distribution = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];

if ($total_reviews > 0) {
    $total_stars = 0;
    foreach ($reviews as $r) {
        $rating = isset($r['rating']) ? intval($r['rating']) : 0;
        if ($rating >= 1 && $rating <= 5) {
            $total_stars += $rating;
            $rating_distribution[$rating]++;
        }
    }
    $average_rating = round($total_stars / $total_reviews, 1);
}

// ═══════════════════════════════════════════════════════════════
// PAGINATION
// ═══════════════════════════════════════════════════════════════

$total_pages = max(1, ceil($total_reviews / $limit));
$offset = ($page - 1) * $limit;
$paginated_reviews = array_slice($reviews, $offset, $limit);

// ═══════════════════════════════════════════════════════════════
// NETTOYAGE (pas d'IP exposée)
// ═══════════════════════════════════════════════════════════════

$safe_reviews = [];
foreach ($paginated_reviews as $review) {
    $safe_reviews[] = [
        'id'         => $review['id'] ?? '',
        'name'       => htmlspecialchars($review['name'] ?? 'Anonyme', ENT_QUOTES, 'UTF-8'),
        'rating'     => intval($review['rating'] ?? 0),
        'comment'    => htmlspecialchars($review['comment'] ?? '', ENT_QUOTES, 'UTF-8'),
        'created_at' => $review['created_at'] ?? '',
    ];
}

// ═══════════════════════════════════════════════════════════════
// RÉPONSE JSON (sans JSON_PRETTY_PRINT pour éviter les problèmes)
// ═══════════════════════════════════════════════════════════════

http_response_code(200);
echo json_encode([
    'success' => true,
    'data' => $safe_reviews,
    'stats' => [
        'total_reviews'       => $total_reviews,
        'average_rating'      => $average_rating,
        'rating_distribution' => $rating_distribution
    ],
    'pagination' => [
        'current_page' => $page,
        'total_pages'  => $total_pages,
        'per_page'     => $limit,
        'has_next'     => $page < $total_pages,
        'has_prev'     => $page > 1
    ]
], JSON_UNESCAPED_UNICODE);
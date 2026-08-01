<?php
/**
 * OrinHeberge - API de récupération des avis clients
 * Endpoint: /api/reviews/get.php
 * Méthode: GET
 * 
 * Paramètres optionnels (query string):
 *   ?limit=10    → Nombre d'avis à retourner (défaut: 10, max: 50)
 *   ?page=1      → Numéro de page (défaut: 1)
 *   ?min_rating=4 → Filtrer par note minimum (1-5)
 */

// Headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Cache-Control: public, max-age=60'); // Cache 60 secondes

// Gestion CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Vérifier que c'est bien une requête GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
    exit();
}

// ═══════════════════════════════════════════════════════════════
// CONFIGURATION
// ═══════════════════════════════════════════════════════════════

$reviews_file = __DIR__ . '/reviews.json';

// Paramètres de pagination
$limit = isset($_GET['limit']) ? max(1, min(50, intval($_GET['limit']))) : 10;
$page  = isset($_GET['page'])  ? max(1, intval($_GET['page'])) : 1;
$min_rating = isset($_GET['min_rating']) ? max(1, min(5, intval($_GET['min_rating']))) : 0;

// ═══════════════════════════════════════════════════════════════
// LECTURE DES AVIS
// ═══════════════════════════════════════════════════════════════

$reviews = [];

if (file_exists($reviews_file)) {
    $raw = file_get_contents($reviews_file);
    $reviews = json_decode($raw, true);
    
    if (!is_array($reviews)) {
        $reviews = [];
    }
}

// ═══════════════════════════════════════════════════════════════
// FILTRAGE
// ═══════════════════════════════════════════════════════════════

// Filtrer par note minimum si demandé
if ($min_rating > 0) {
    $reviews = array_filter($reviews, function($review) use ($min_rating) {
        return isset($review['rating']) && $review['rating'] >= $min_rating;
    });
    $reviews = array_values($reviews); // Réindexer
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
// NETTOYAGE DES DONNÉES (Sécurité : ne pas exposer les IPs)
// ═══════════════════════════════════════════════════════════════

$safe_reviews = [];
foreach ($paginated_reviews as $review) {
    $safe_reviews[] = [
        'id'         => $review['id'] ?? '',
        'name'       => htmlspecialchars($review['name'] ?? 'Anonyme', ENT_QUOTES, 'UTF-8'),
        'rating'     => intval($review['rating'] ?? 0),
        'comment'    => htmlspecialchars($review['comment'] ?? '', ENT_QUOTES, 'UTF-8'),
        'created_at' => $review['created_at'] ?? '',
        // ⚠️ L'IP n'est PAS envoyée au client (sécurité)
    ];
}

// ═══════════════════════════════════════════════════════════════
// RÉPONSE JSON
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
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
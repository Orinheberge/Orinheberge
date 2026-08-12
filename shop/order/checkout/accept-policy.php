<?php
/**
 * Enregistre la preuve d'acceptation de la Politique de Paiement.
 * Appelé en AJAX dès que l'utilisateur coche la case sur la page de paiement.
 *
 * Une simple case cochée côté navigateur ne vaut pas grand-chose en cas de
 * litige : on garde donc une trace serveur (commande, utilisateur, IP, date).
 */
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Non authentifié']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$order_id = $data['order_id'] ?? null;

if (!$order_id) {
    http_response_code(400);
    echo json_encode(['error' => 'ID de commande manquant']);
    exit();
}

// Vérifier que la commande appartient bien à l'utilisateur (évite de logger
// n'importe quel order_id fourni arbitrairement)
$stmt = $pdo->prepare("SELECT order_id FROM orders WHERE order_id = ? AND user_id = ? LIMIT 1");
$stmt->execute([$order_id, $_SESSION['user_id']]);
if (!$stmt->fetch()) {
    http_response_code(404);
    echo json_encode(['error' => 'Commande introuvable']);
    exit();
}

$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;
// Si derrière un proxy/CDN, HTTP_X_FORWARDED_FOR peut contenir plusieurs IP
// séparées par des virgules : on garde la première (celle du client réel).
if ($ip && strpos($ip, ',') !== false) {
    $ip = trim(explode(',', $ip)[0]);
}

try {
    $pdo->prepare("
        INSERT INTO policy_acceptances (order_id, user_id, ip_address, accepted_at)
        VALUES (?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE ip_address = VALUES(ip_address), accepted_at = NOW()
    ")->execute([$order_id, $_SESSION['user_id'], $ip]);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    error_log('[Accept Policy] Erreur: ' . $e->getMessage());
    // Non bloquant : on ne veut pas empêcher le paiement si cette table a
    // un souci, mais on log pour investigation.
    http_response_code(200);
    echo json_encode(['success' => false]);
}

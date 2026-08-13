<?php
/**
 * OrinHeberge - API Typing Status
 * POST { channel: string, is_typing: bool }
 */

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Non authentifié']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        throw new Exception('Données invalides');
    }

    $userId   = (int)$_SESSION['user_id'];
    $channel  = isset($input['channel']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', $input['channel']) : 'general';
    $isTyping = isset($input['is_typing']) ? (bool)$input['is_typing'] : true;

    $allowedChannels = ['general', 'support', 'offtopic'];
    if (!in_array($channel, $allowedChannels)) {
        throw new Exception('Canal invalide');
    }

    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Nettoyage des entrées expirées (> 10s)
    $pdo->exec("DELETE FROM chat_typing WHERE updated_at < DATE_SUB(NOW(), INTERVAL 10 SECOND)");

    if ($isTyping) {
        $stmt = $pdo->prepare("
            INSERT INTO chat_typing (user_id, channel, updated_at)
            VALUES (?, ?, NOW())
            ON DUPLICATE KEY UPDATE updated_at = NOW()
        ");
        $stmt->execute([$userId, $channel]);
    } else {
        $stmt = $pdo->prepare("DELETE FROM chat_typing WHERE user_id = ? AND channel = ?");
        $stmt->execute([$userId, $channel]);
    }

    echo json_encode([
        'success' => true,
        'message' => $isTyping ? 'Typing activé' : 'Typing désactivé'
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
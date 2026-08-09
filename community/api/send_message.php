<?php
header('Content-Type: application/json');
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/db.php';

// Vérifier connexion
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Non autorisé']);
    exit;
}

// Récupérer données
$data = json_decode(file_get_contents('php://input'), true);
$message = trim($data['message'] ?? '');
$channel = $data['channel'] ?? 'general';

// Validation
if (empty($message)) {
    http_response_code(400);
    echo json_encode(['error' => 'Message vide']);
    exit;
}

if (strlen($message) > 2000) {
    http_response_code(400);
    echo json_encode(['error' => 'Message trop long (max 2000 caractères)']);
    exit;
}

// Anti-spam : max 1 message par seconde
$lastMessageTime = $_SESSION['last_message_time'] ?? 0;
if (time() - $lastMessageTime < 1) {
    http_response_code(429);
    echo json_encode(['error' => 'Trop rapide, attendez 1 seconde']);
    exit;
}
$_SESSION['last_message_time'] = time();

try {
    // Insérer message
    $stmt = $pdo->prepare("
        INSERT INTO chat_messages (user_id, channel, message, created_at)
        VALUES (?, ?, ?, NOW())
    ");
    $stmt->execute([$_SESSION['user_id'], $channel, $message]);
    
    $messageId = $pdo->lastInsertId();
    
    // Récupérer le message complet
    $stmt = $pdo->prepare("
        SELECT m.id, m.message, m.created_at,
               u.username, u.avatar
        FROM chat_messages m
        JOIN users u ON m.user_id = u.id
        WHERE m.id = ?
    ");
    $stmt->execute([$messageId]);
    $newMessage = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'message' => $newMessage
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur serveur']);
    error_log('[Chat] Erreur send_message: ' . $e->getMessage());
}
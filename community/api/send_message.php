<?php
header('Content-Type: application/json');
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/db.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/lang.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Non autorisé']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$message = trim($data['message'] ?? '');
$channel = $data['channel'] ?? 'general';

if (empty($message)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Message vide']);
    exit;
}

if (strlen($message) > 2000) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Message trop long']);
    exit;
}

// Anti-spam
$lastMessageTime = $_SESSION['last_chat_message'] ?? 0;
if (time() - $lastMessageTime < 1) {
    http_response_code(429);
    echo json_encode(['success' => false, 'error' => 'Trop rapide, attendez 1 seconde']);
    exit;
}
$_SESSION['last_chat_message'] = time();

try {
    $stmt = $pdo->prepare("
        INSERT INTO chat_messages (user_id, channel, message, created_at)
        VALUES (?, ?, ?, NOW())
    ");
    $stmt->execute([$_SESSION['user_id'], $channel, $message]);
    
    $messageId = $pdo->lastInsertId();
    
    $stmt = $pdo->prepare("
        SELECT m.id, m.message, m.created_at, u.username, u.avatar
        FROM chat_messages m
        JOIN users u ON m.user_id = u.id
        WHERE m.id = ?
    ");
    $stmt->execute([$messageId]);
    $newMessage = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'message' => $newMessage]);
} catch (Exception $e) {
    error_log('[Chat] send_message error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
}
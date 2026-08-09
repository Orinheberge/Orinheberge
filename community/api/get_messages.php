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

$channel = $_GET['channel'] ?? 'general';
$lastId = (int)($_GET['last_id'] ?? 0);

try {
    if ($lastId > 0) {
        $stmt = $pdo->prepare("
            SELECT m.id, m.message, m.created_at, u.username, u.avatar
            FROM chat_messages m
            JOIN users u ON m.user_id = u.id
            WHERE m.channel = ? AND m.id > ?
            ORDER BY m.id ASC
            LIMIT 50
        ");
        $stmt->execute([$channel, $lastId]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = $pdo->prepare("
            SELECT m.id, m.message, m.created_at, u.username, u.avatar
            FROM chat_messages m
            JOIN users u ON m.user_id = u.id
            WHERE m.channel = ?
            ORDER BY m.id DESC
            LIMIT 50
        ");
        $stmt->execute([$channel]);
        $messages = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
    }
    
    echo json_encode(['success' => true, 'messages' => $messages]);
} catch (Exception $e) {
    error_log('[Chat] get_messages error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
}
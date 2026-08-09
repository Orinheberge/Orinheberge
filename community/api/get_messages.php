<?php
header('Content-Type: application/json');
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Non autorisé']);
    exit;
}

$channel = $_GET['channel'] ?? 'general';
$lastId = (int)($_GET['last_id'] ?? 0);

try {
    if ($lastId > 0) {
        // Mode polling : seulement les nouveaux messages
        $stmt = $pdo->prepare("
            SELECT m.id, m.message, m.created_at,
                   u.username, u.avatar
            FROM chat_messages m
            JOIN users u ON m.user_id = u.id
            WHERE m.channel = ? AND m.id > ?
            ORDER BY m.id ASC
            LIMIT 50
        ");
        $stmt->execute([$channel, $lastId]);
    } else {
        // Premier chargement : derniers 50 messages
        $stmt = $pdo->prepare("
            SELECT m.id, m.message, m.created_at,
                   u.username, u.avatar
            FROM chat_messages m
            JOIN users u ON m.user_id = u.id
            WHERE m.channel = ?
            ORDER BY m.id DESC
            LIMIT 50
        ");
        $stmt->execute([$channel]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $messages = array_reverse($messages); // Ordre chronologique
    }
    
    if ($lastId > 0) {
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    echo json_encode([
        'success' => true,
        'messages' => $messages
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur serveur']);
    error_log('[Chat] Erreur get_messages: ' . $e->getMessage());
}
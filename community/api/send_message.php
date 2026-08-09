<?php
if (ob_get_level()) ob_end_clean();
ob_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (ob_get_length()) ob_clean();
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'PHP Fatal: ' . $error['message'],
            'file' => basename($error['file']),
            'line' => $error['line']
        ]);
    }
});

try {
    session_start();
    
    if (!isset($_SESSION['user_id'])) {
        if (ob_get_length()) ob_clean();
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Non autorisé']);
        exit;
    }
    
    require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/db.php';
    
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        throw new Exception('PDO non initialisé');
    }
    
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);
    
    if (!$data) {
        throw new Exception('JSON invalide: ' . substr($rawInput, 0, 100));
    }
    
    $message = trim($data['message'] ?? '');
    $channel = preg_replace('/[^a-z0-9_-]/', '', $data['channel'] ?? 'general');
    
    if (empty($message)) {
        if (ob_get_length()) ob_clean();
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Message vide']);
        exit;
    }
    
    if (strlen($message) > 2000) {
        if (ob_get_length()) ob_clean();
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Message trop long (max 2000)']);
        exit;
    }
    
    // Anti-spam
    $lastMessageTime = $_SESSION['last_chat_message'] ?? 0;
    if (time() - $lastMessageTime < 1) {
        if (ob_get_length()) ob_clean();
        http_response_code(429);
        echo json_encode(['success' => false, 'error' => 'Trop rapide, attendez 1 seconde']);
        exit;
    }
    $_SESSION['last_chat_message'] = time();
    
    $stmt = $pdo->prepare("
        INSERT INTO chat_messages (user_id, channel, message, created_at)
        VALUES (?, ?, ?, NOW())
    ");
    $stmt->execute([$_SESSION['user_id'], $channel, $message]);
    
    $messageId = $pdo->lastInsertId();
    
    $stmt = $pdo->prepare("
        SELECT m.id, m.message, m.created_at, u.pseudo as username, u.avatar
        FROM chat_messages m
        JOIN users u ON m.user_id = u.id
        WHERE m.id = ?
    ");
    $stmt->execute([$messageId]);
    $newMessage = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$newMessage) {
        throw new Exception('Message créé mais non récupérable (ID: ' . $messageId . ')');
    }
    
    if (ob_get_length()) ob_clean();
    echo json_encode(['success' => true, 'message' => $newMessage]);
    
} catch (Throwable $e) {
    if (ob_get_length()) ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ]);
}
exit;
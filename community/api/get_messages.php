<?php
// 🔒 Nettoyer tout output avant JSON
if (ob_get_level()) ob_end_clean();
ob_start();

// 🔒 Headers AVANT tout include
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

// 🔒 Handler d'erreurs fatal → JSON
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
    
    $channel = preg_replace('/[^a-z0-9_-]/', '', $_GET['channel'] ?? 'general');
    $lastId = (int)($_GET['last_id'] ?? 0);
    
    if ($lastId > 0) {
        $stmt = $pdo->prepare("
            SELECT m.id, m.message, m.created_at, u.pseudo as username, u.avatar
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
            SELECT m.id, m.message, m.created_at, u.pseudo as username, u.avatar
            FROM chat_messages m
            JOIN users u ON m.user_id = u.id
            WHERE m.channel = ?
            ORDER BY m.id DESC
            LIMIT 50
        ");
        $stmt->execute([$channel]);
        $messages = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
    }
    
    if (ob_get_length()) ob_clean();
    echo json_encode([
        'success' => true, 
        'messages' => $messages,
        'count' => count($messages)
    ]);
    
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
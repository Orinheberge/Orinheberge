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
            'error' => 'PHP Fatal: ' . $error['message']
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
    
    $channel = preg_replace('/[^a-z0-9_-]/', '', $_GET['channel'] ?? 'general');
    $lastId = (int)($_GET['last_id'] ?? 0);
    
    if ($lastId > 0) {
        $stmt = $pdo->prepare("
            SELECT m.id, m.message, m.created_at, m.user_id,
                   u.pseudo as username, u.avatar
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
            SELECT m.id, m.message, m.created_at, m.user_id,
                   u.pseudo as username, u.avatar
            FROM chat_messages m
            JOIN users u ON m.user_id = u.id
            WHERE m.channel = ?
            ORDER BY m.id DESC
            LIMIT 50
        ");
        $stmt->execute([$channel]);
        $messages = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
    }
    
    // Récupérer réactions pour tous les messages
    if (!empty($messages)) {
        $messageIds = array_column($messages, 'id');
        $placeholders = implode(',', array_fill(0, count($messageIds), '?'));
        
        $stmt = $pdo->prepare("
            SELECT message_id, emoji, COUNT(*) as count,
                   GROUP_CONCAT(u.pseudo ORDER BY u.pseudo SEPARATOR ', ') as users
            FROM message_reactions r
            JOIN users u ON r.user_id = u.id
            WHERE r.message_id IN ($placeholders)
            GROUP BY message_id, emoji
            ORDER BY count DESC
        ");
        $stmt->execute($messageIds);
        $reactionsRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Grouper par message_id
        $reactionsByMessage = [];
        foreach ($reactionsRaw as $r) {
            $reactionsByMessage[$r['message_id']][] = [
                'emoji' => $r['emoji'],
                'count' => (int)$r['count'],
                'users' => $r['users']
            ];
        }
        
        // Récupérer les messages épinglés
        $stmt = $pdo->prepare("
            SELECT message_id FROM pinned_messages 
            WHERE message_id IN ($placeholders)
        ");
        $stmt->execute($messageIds);
        $pinnedIds = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'message_id');
        
        // Ajouter réactions et pin status à chaque message
        foreach ($messages as &$msg) {
            $msg['reactions'] = $reactionsByMessage[$msg['id']] ?? [];
            $msg['is_pinned'] = in_array($msg['id'], $pinnedIds);
            $msg['is_own'] = $msg['user_id'] == $_SESSION['user_id'];
            $msg['is_admin'] = !empty($_SESSION['is_admin']);
        }
        unset($msg);
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
        'error' => $e->getMessage()
    ]);
}
exit;
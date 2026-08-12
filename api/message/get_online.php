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
    
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        throw new Exception('PDO non initialisé');
    }
    
    // 🔹 Marquer l'utilisateur courant comme actif (heartbeat)
    $stmt = $pdo->prepare("
        INSERT INTO user_activity (user_id, last_seen, current_page)
        VALUES (?, NOW(), ?)
        ON DUPLICATE KEY UPDATE 
            last_seen = NOW(),
            current_page = VALUES(current_page)
    ");
    $stmt->execute([$_SESSION['user_id'], '/community/']);
    
    // 🔹 Récupérer les utilisateurs actifs (dernière activité < 3 minutes)
    $timeoutMinutes = 3;
    $stmt = $pdo->prepare("
        SELECT 
            u.id,
            u.pseudo as username,
            u.firstname,
            u.avatar,
            u.oauth_provider,
            ua.last_seen,
            ua.current_page,
            TIMESTAMPDIFF(SECOND, ua.last_seen, NOW()) as seconds_ago
        FROM user_activity ua
        JOIN users u ON ua.user_id = u.id
        WHERE ua.last_seen >= DATE_SUB(NOW(), INTERVAL ? MINUTE)
        ORDER BY 
            CASE WHEN u.id = ? THEN 0 ELSE 1 END,  -- User courant en premier
            ua.last_seen DESC
        LIMIT 50
    ");
    $stmt->execute([$timeoutMinutes, $_SESSION['user_id']]);
    $onlineUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 🔹 Nettoyer les anciennes entrées (> 30 min) pour éviter la croissance infinie
    $pdo->prepare("DELETE FROM user_activity WHERE last_seen < DATE_SUB(NOW(), INTERVAL 30 MINUTE)")
        ->execute();
    
    if (ob_get_length()) ob_clean();
    echo json_encode([
        'success' => true,
        'users' => $onlineUsers,
        'count' => count($onlineUsers),
        'timeout_minutes' => $timeoutMinutes
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
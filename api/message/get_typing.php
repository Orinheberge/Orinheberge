<?php
/**
 * OrinHeberge - API Get Typing Users
 * GET ?channel=general
 */

session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Non authentifié']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
    exit;
}

try {
    $channel       = isset($_GET['channel']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['channel']) : 'general';
    $currentUserId = (int)$_SESSION['user_id'];

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

    // Nettoyage des entrées expirées
    $pdo->exec("DELETE FROM chat_typing WHERE updated_at < DATE_SUB(NOW(), INTERVAL 10 SECOND)");

    // Récupérer les utilisateurs en train d'écrire (sauf soi-même)
    $stmt = $pdo->prepare("
        SELECT 
            u.id,
            u.username,
            u.avatar,
            ct.updated_at
        FROM chat_typing ct
        INNER JOIN users u ON ct.user_id = u.id
        WHERE ct.channel = ?
          AND ct.user_id != ?
          AND ct.updated_at >= DATE_SUB(NOW(), INTERVAL 10 SECOND)
        ORDER BY ct.updated_at DESC
        LIMIT 5
    ");
    $stmt->execute([$channel, $currentUserId]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($users as &$user) {
        $diff = time() - strtotime($user['updated_at']);
        $user['seconds_ago'] = max(0, $diff);
    }
    unset($user);

    echo json_encode([
        'success' => true,
        'channel' => $channel,
        'count'   => count($users),
        'users'   => $users
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
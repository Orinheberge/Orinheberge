<?php
session_start();

// DB config (copié du root index.php)
$db_config = [
    'host' => 'pma.orinstone.deepstone.fr',
    'port' => '3306',
    'name' => 's43_orinheberge',
    'user' => 'root',
    'pass' => '1504'
];

try {
    $dsn = "mysql:host={$db_config['host']};port={$db_config['port']};dbname={$db_config['name']};charset=utf8mb4";
    $pdo = new PDO($dsn, $db_config['user'], $db_config['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'DB connection failed']);
    exit();
}

function create_notification($pdo, $user_id, $title, $message, $link = null, $type = null, $meta = null) {
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, link, type, meta, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
    $stmt->execute([$user_id, $title, $message, $link, $type, $meta ? json_encode($meta) : null]);
    return $pdo->lastInsertId();
}

function get_notifications($pdo, $user_id, $limit = 20) {
    $stmt = $pdo->prepare("SELECT id, title, message, link, is_read, type, meta, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?");
    $stmt->bindValue(1, $user_id, PDO::PARAM_INT);
    $stmt->bindValue(2, (int)$limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function count_unread($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    return (int)$stmt->fetchColumn();
}

function mark_read($pdo, $id, $user_id) {
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    return $stmt->execute([$id, $user_id]);
}

function mark_all_read($pdo, $user_id) {
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    return $stmt->execute([$user_id]);
}

function delete_notification($pdo, $id, $user_id) {
    $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
    return $stmt->execute([$id, $user_id]);
}

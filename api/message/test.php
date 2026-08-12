<?php
if (ob_get_level()) ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

$results = [
    'timestamp' => date('Y-m-d H:i:s'),
    'php_version' => PHP_VERSION,
    'session_status' => session_status(),
    'document_root' => $_SERVER['DOCUMENT_ROOT'],
    'checks' => []
];

// Test 1 : Session
session_start();
$results['checks']['session_started'] = session_status() === PHP_SESSION_ACTIVE;
$results['checks']['user_logged_in'] = isset($_SESSION['user_id']);
$results['checks']['user_id'] = $_SESSION['user_id'] ?? null;

// Test 2 : DB
try {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/db.php';
    $results['checks']['pdo_loaded'] = isset($pdo) && ($pdo instanceof PDO);
    
    if ($results['checks']['pdo_loaded']) {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM chat_messages");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $results['checks']['chat_messages_count'] = $row['count'];
        
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $results['checks']['users_count'] = $row['count'];
    }
} catch (Throwable $e) {
    $results['checks']['pdo_error'] = $e->getMessage();
}

// Test 3 : Fichiers API
$apiFiles = [
    'get_messages.php' => __DIR__ . '/get_messages.php',
    'send_message.php' => __DIR__ . '/send_message.php',
    'get_emojis.php' => __DIR__ . '/get_emojis.php',
];
foreach ($apiFiles as $name => $path) {
    $results['checks']['file_' . $name] = file_exists($path);
}

// Test 4 : Dossier emojis
$emojiDir = $_SERVER['DOCUMENT_ROOT'] . '/assets/emoji-orinheberge/';
$results['checks']['emoji_dir_exists'] = is_dir($emojiDir);
if (is_dir($emojiDir)) {
    $results['checks']['emoji_files'] = count(glob($emojiDir . '*.{png,jpg,jpeg,gif,webp}', GLOB_BRACE));
}

echo json_encode($results, JSON_PRETTY_PRINT);
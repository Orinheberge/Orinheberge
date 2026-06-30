<?php
// Activation des sessions avant toute chose
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/notifications.php';

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';
$user_id = $_SESSION['user_id'] ?? null;

// CRUCIAL : On ferme le verrou de la session immédiatement.
// Cela empêche cette API en arrière-plan de bloquer les autres pages du site !
session_write_close();

if (!$user_id) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}

try {
    if ($action === 'list') {
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
        $rows = get_notifications($pdo, $user_id, $limit);
        echo json_encode(['notifications' => $rows]);
        exit();
    }

    if ($action === 'count') {
        $c = count_unread($pdo, $user_id);
        echo json_encode(['unread' => $c]);
        exit();
    }

    if ($action === 'mark_read') {
        $id = $_POST['id'] ?? null;
        if (!$id) { http_response_code(400); echo json_encode(['error' => 'Missing id']); exit(); }
        $ok = mark_read($pdo, (int)$id, $user_id);
        echo json_encode(['ok' => (bool)$ok]);
        exit();
    }

    if ($action === 'mark_all') {
        $ok = mark_all_read($pdo, $user_id);
        echo json_encode(['ok' => (bool)$ok]);
        exit();
    }

    if ($action === 'create') {
        $title = $_POST['title'] ?? '';
        $message = $_POST['message'] ?? '';
        $link = $_POST['link'] ?? null;
        $type = $_POST['type'] ?? null;
        $meta = $_POST['meta'] ?? null;
        if (!$title || !$message) { http_response_code(400); echo json_encode(['error' => 'Missing fields']); exit(); }
        $id = create_notification($pdo, $user_id, $title, $message, $link, $type, $meta);
        echo json_encode(['id' => $id]);
        exit();
    }

    http_response_code(400);
    echo json_encode(['error' => 'Unknown action']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}
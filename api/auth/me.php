<?php
header('Content-Type: application/json');
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/AuthService.php';

$auth = new AuthService($pdo);
$user = $auth->getCurrentUser();

if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'not_authenticated']);
    exit;
}

echo json_encode(['success' => true, 'user' => $user]);
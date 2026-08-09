<?php
header('Content-Type: application/json');
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/AuthService.php';

$auth = new AuthService($pdo);
$auth->logout();

echo json_encode(['success' => true, 'message' => 'Déconnecté']);
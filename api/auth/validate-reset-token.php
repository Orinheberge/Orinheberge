<?php
header('Content-Type: application/json');
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/AuthService.php';

$token = $_GET['token'] ?? '';

$auth = new AuthService($pdo);
$result = $auth->validateResetToken($token);

echo json_encode($result);
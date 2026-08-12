<?php
/**
 * POST /api/auth/login.php
 * Body: { "email": "...", "password": "..." }
 */

header('Content-Type: application/json');
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/AuthService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$email = trim($data['email'] ?? '');
$password = $data['password'] ?? '';

if (!$email || !$password) {
    http_response_code(400);
    echo json_encode(['error' => 'missing_fields']);
    exit;
}

$auth = new AuthService($pdo);
$result = $auth->login($email, $password);

if (!$result['success']) {
    $errors = [
        'invalid_credentials' => 'Email ou mot de passe incorrect',
        'oauth_account'       => "Ce compte utilise la connexion " . ($result['provider'] ?? 'OAuth'),
    ];
    http_response_code(401);
    echo json_encode(['error' => $errors[$result['error']] ?? 'Erreur']);
    exit;
}

echo json_encode([
    'success' => true,
    'user'    => $result['user'],
    'message' => 'Connexion réussie'
]);
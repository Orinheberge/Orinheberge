<?php
header('Content-Type: application/json');
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/AuthService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$auth = new AuthService($pdo);

$result = $auth->register(
    trim($data['firstname'] ?? ''),
    trim($data['lastname'] ?? ''),
    trim($data['email'] ?? ''),
    $data['password'] ?? ''
);

if (!$result['success']) {
    $errors = [
        'missing_fields'      => 'Tous les champs sont obligatoires',
        'invalid_email'       => 'Email invalide',
        'password_too_short'  => 'Mot de passe trop court (min 8 caractères)',
        'email_exists'        => 'Cet email est déjà utilisé',
        'db_error'            => 'Erreur serveur',
    ];
    http_response_code(400);
    echo json_encode(['error' => $errors[$result['error']] ?? 'Erreur']);
    exit;
}

unset($result['plain_password']); // Ne pas renvoyer en JSON
echo json_encode($result);
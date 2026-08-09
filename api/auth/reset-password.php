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
$token = $data['token'] ?? '';
$password = $data['password'] ?? '';

$auth = new AuthService($pdo);
$result = $auth->resetPassword($token, $password);

if (!$result['success']) {
    $errors = [
        'invalid_token'      => 'Lien invalide ou expiré',
        'token_used'         => 'Ce lien a déjà été utilisé',
        'token_expired'      => 'Ce lien a expiré (1 heure max)',
        'password_too_short' => 'Le mot de passe doit contenir au moins 8 caractères',
        'password_too_weak'  => 'Le mot de passe doit contenir des lettres et des chiffres',
        'db_error'           => 'Erreur serveur, réessayez'
    ];
    
    http_response_code(400);
    echo json_encode(['error' => $errors[$result['error']] ?? 'Erreur inconnue']);
    exit;
}

echo json_encode(['success' => true, 'message' => 'Mot de passe modifié avec succès']);
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
$email = trim($data['email'] ?? '');

$auth = new AuthService($pdo);
$result = $auth->requestPasswordReset($email);

// Toujours retourner success pour éviter l'énumération d'emails
// SAUF cas spéciaux (OAuth, rate limit)
if (!$result['success']) {
    if ($result['error'] === 'oauth_account') {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'oauth_account',
            'provider' => $result['provider'],
            'message' => "Ce compte utilise la connexion " . ucfirst($result['provider']) . ". Connectez-vous avec " . ucfirst($result['provider']) . "."
        ]);
        exit;
    }
    
    if ($result['error'] === 'too_many_attempts') {
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'error' => 'too_many_attempts',
            'message' => 'Trop de demandes. Réessayez dans 1 heure.',
            'retry_after' => $result['retry_after']
        ]);
        exit;
    }
}

echo json_encode([
    'success' => true,
    'message' => 'Si cet email existe dans notre base, un lien de réinitialisation a été envoyé.'
]);
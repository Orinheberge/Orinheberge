<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/settings.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Non authentifié']);
    exit();
}

$stripe_secret_key = get_setting('stripe_secret_key');
if (empty($stripe_secret_key)) {
    http_response_code(500);
    echo json_encode(['error' => 'Configuration Stripe manquante']);
    exit();
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';
\Stripe\Stripe::setApiKey($stripe_secret_key);

header('Content-Type: application/json');

try {
    $pdo = new PDO('mysql:host=localhost;dbname=s43_orinheberge;charset=utf8mb4', 'root', '1504', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    $stmt = $pdo->prepare('SELECT stripe_customer_id FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (empty($user['stripe_customer_id'])) {
        throw new Exception('Customer Stripe introuvable');
    }

    $setupIntent = \Stripe\SetupIntent::create([
        'customer' => $user['stripe_customer_id'],
        'payment_method_types' => ['card'],
        'usage' => 'off_session',
    ]);

    echo json_encode(['client_secret' => $setupIntent->client_secret]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
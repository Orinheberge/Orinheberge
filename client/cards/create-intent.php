<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/settings.php.php'; 
$stripe_secret_key = get_setting('stripe_secret_key');
// Votre config avec la clé Stripe

\Stripe\Stripe::setApiKey($stripe_secret_key);

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Non authentifié']);
    exit();
}

if (empty($stripe_secret_key)) {
    http_response_code(500);
    echo json_encode(['error' => 'Configuration Stripe manquante']);
    exit();
}

try {
    $pdo = new PDO('mysql:host=localhost;dbname=s43_orinheberge;charset=utf8mb4', root, 1504, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    // Récupérer le customer ID
    $stmt = $pdo->prepare('SELECT stripe_customer_id FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (empty($user['stripe_customer_id'])) {
        throw new Exception('Customer Stripe introuvable');
    }

    // Créer un SetupIntent pour authentifier la carte (0€)
    $setupIntent = \Stripe\SetupIntent::create([
        'customer' => $user['stripe_customer_id'],
        'payment_method_types' => ['card'],
        'usage' => 'off_session', // Permet les paiements futurs sans présence du client
    ]);

    echo json_encode([
        'client_secret' => $setupIntent->client_secret
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
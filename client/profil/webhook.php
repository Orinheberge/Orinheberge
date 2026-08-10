<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/settings.php';

$stripe_secret_key = get_setting('stripe_secret_key');
$webhook_secret = get_setting('stripe_webhook_secret');

if (empty($stripe_secret_key) || empty($webhook_secret)) {
    http_response_code(500);
    exit('Configuration Stripe manquante');
}

\Stripe\Stripe::setApiKey($stripe_secret_key);

$payload = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

try {
    $event = \Stripe\Webhook::constructEvent($payload, $sig_header, $webhook_secret);
} catch (\UnexpectedValueException $e) {
    http_response_code(400);
    exit();
} catch (\Stripe\Exception\SignatureVerificationException $e) {
    http_response_code(400);
    exit();
}

try {
    $pdo = new PDO('mysql:host=localhost;dbname=s43_orinheberge;charset=utf8mb4', 'root', '1504', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    error_log('[Webhook] Erreur BDD: ' . $e->getMessage());
    http_response_code(200);
    exit();
}

switch ($event->type) {
    case 'setup_intent.succeeded':
        $setupIntent = $event->data->object;
        $pm_id = $setupIntent->payment_method;
        $customer_id = $setupIntent->customer;
        
        $stmt = $pdo->prepare('SELECT id FROM users WHERE stripe_customer_id = ?');
        $stmt->execute([$customer_id]);
        $user = $stmt->fetch();
        
        if ($user) {
            $pm = \Stripe\PaymentMethod::retrieve($pm_id);
            $stmt = $pdo->prepare('INSERT INTO user_stripe_cards (user_id, payment_method_id, card_brand, card_last4, card_exp_month, card_exp_year) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE updated_at=NOW()');
            $stmt->execute([$user['id'], $pm_id, $pm->card->brand, $pm->card->last4, $pm->card->exp_month, $pm->card->exp_year]);
        }
        break;
        
    case 'payment_method.detached':
        $pm_id = $event->data->object->id;
        $pdo->prepare('DELETE FROM user_stripe_cards WHERE payment_method_id = ?')->execute([$pm_id]);
        break;
}

http_response_code(200);
exit();
?>
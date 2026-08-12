<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/settings.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Non authentifié']);
    exit();
}

$stripe_secret_key = get_setting('stripe_secret_key');
if (empty($stripe_secret_key)) {
    echo json_encode(['success' => false, 'error' => 'Configuration Stripe manquante']);
    exit();
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';
\Stripe\Stripe::setApiKey($stripe_secret_key);

header('Content-Type: application/json');

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $payment_method_id = $data['payment_method_id'] ?? null;

    if (!$payment_method_id) {
        throw new Exception('Payment method ID manquant');
    }

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

    $pm = \Stripe\PaymentMethod::retrieve($payment_method_id);
    
    if ($pm->customer !== $user['stripe_customer_id']) {
        $pm->attach(['customer' => $user['stripe_customer_id']]);
    }

    $stmt = $pdo->prepare('
        INSERT INTO user_stripe_cards (user_id, payment_method_id, card_brand, card_last4, card_exp_month, card_exp_year) 
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            card_brand = VALUES(card_brand),
            card_last4 = VALUES(card_last4),
            card_exp_month = VALUES(card_exp_month),
            card_exp_year = VALUES(card_exp_year),
            updated_at = NOW()
    ');
    
    $stmt->execute([
        $_SESSION['user_id'],
        $payment_method_id,
        $pm->card->brand,
        $pm->card->last4,
        $pm->card->exp_month,
        $pm->card->exp_year
    ]);

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    error_log('[Stripe Save Card] Erreur: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
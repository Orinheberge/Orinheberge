<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/settings.php';

$stripe_secret_key = get_setting('stripe_secret_key');

if (empty($stripe_secret_key)) {
    echo json_encode(['success' => false, 'error' => 'Configuration Stripe manquante']);
    exit();
}

\Stripe\Stripe::setApiKey($stripe_secret_key);

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Non authentifié']);
    exit();
}

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

    // Récupérer les infos de la carte depuis Stripe
    $pm = \Stripe\PaymentMethod::retrieve($payment_method_id);
    
    // Attacher la carte au customer (si pas déjà fait)
    if ($pm->customer !== $_SESSION['stripe_customer_id']) {
        $pm->attach(['customer' => $_SESSION['stripe_customer_id']]);
    }

    // Sauvegarder en base locale (juste l'ID Stripe, pas les données sensibles!)
    $stmt = $pdo->prepare('
        INSERT INTO user_stripe_cards (user_id, payment_method_id, card_brand, card_last4, card_exp_month, card_exp_year) 
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE updated_at = NOW()
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
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Non authentifié']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$order_id = $data['order_id'] ?? null;
$payment_method_id = $data['payment_method_id'] ?? null;

if (!$order_id) {
    echo json_encode(['error' => 'ID de commande manquant']);
    exit();
}

// Récupérer les clés Stripe
$ext_settings_raw = $pdo->query("SELECT e.slug, es.key, es.value FROM extension_settings es JOIN extensions e ON e.id = es.extension_id")->fetchAll();
$ext_cfg = [];
foreach ($ext_settings_raw as $r) $ext_cfg[$r['slug']][$r['key']] = $r['value'];
$stripe_secret_key = $ext_cfg['stripe']['secret_key'] ?? '';

// VÉRIFICATION DE SÉCURITÉ CRITIQUE : Récupérer le montant et le customer_id depuis la BDD
$stmt = $pdo->prepare("SELECT o.renewal_price, u.stripe_customer_id FROM orders o JOIN users u ON o.user_id = u.id WHERE o.order_id = ? AND o.user_id = ? AND o.status = 'pending'");
$stmt->execute([$order_id, $_SESSION['user_id']]);
$order_data = $stmt->fetch();

if (!$order_data) {
    echo json_encode(['error' => 'Commande invalide ou déjà traitée']);
    exit();
}

$amount_cents = (int)round((float)$order_data['renewal_price'] * 100);
$customer_id = $order_data['stripe_customer_id'];

try {
    $params = [
        'amount' => $amount_cents,
        'currency' => 'eur',
        'description' => 'Commande OrinHeberge #' . $order_id,
        'automatic_payment_methods' => ['enabled' => true],
        'metadata' => [
            'order_id' => $order_id,
            'user_id' => $_SESSION['user_id']
        ]
    ];
    
    if ($customer_id) $params['customer'] = $customer_id;
    if ($payment_method_id) {
        $params['payment_method'] = $payment_method_id;
        $params['confirm'] = true; // Confirmer immédiatement pour les cartes enregistrées
    }
    
    $ch = curl_init("https://api.stripe.com/v1/payment_intents");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_USERPWD        => $stripe_secret_key . ":",
        CURLOPT_POSTFIELDS     => http_build_query($params),
        CURLOPT_HTTPHEADER     => ["Content-Type: application/x-www-form-urlencoded"],
    ]);
    
    $raw = curl_exec($ch);
    $result = json_decode($raw, true);
    curl_close($ch);
    
    if (!empty($result['error'])) {
        echo json_encode(['error' => $result['error']['message']]);
        exit();
    }
    
    echo json_encode([
        'client_secret' => $result['client_secret'],
        'payment_intent_id' => $result['id'],
    ]);
    
} catch (Exception $e) {
    echo json_encode(['error' => 'Erreur serveur lors de la création du paiement']);
}
?>
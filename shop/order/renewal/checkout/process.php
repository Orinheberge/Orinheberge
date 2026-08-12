<?php
/**
 * /shop/order/renewal/checkout/process.php
 * Endpoint backend : crée le PaymentIntent pour le renouvellement.
 * Appelée en AJAX depuis la page de checkout.
 */
header('Content-Type: application/json; charset=utf-8');

ini_set('display_errors', 0);
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/security.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Non authentifié']);
    exit();
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/db.php';

// Lecture du body JSON
$input = json_decode(file_get_contents('php://input'), true);
$order_row_id = (int)($input['order_id'] ?? 0);

if (!$order_row_id) {
    http_response_code(400);
    echo json_encode(['error' => 'ID de commande manquant']);
    exit();
}

// ═══════════════════════════════════════════
// 1. RÉCUPÉRATION DES SETTINGS STRIPE
// ═══════════════════════════════════════════
$stripe_secret_key = '';
try {
    $ext_cfg = [];
    $ext_settings_raw = $pdo->query("SELECT e.slug, es.key, es.value FROM extension_settings es JOIN extensions e ON e.id = es.extension_id WHERE e.slug = 'stripe'")->fetchAll();
    foreach ($ext_settings_raw as $r) {
        $ext_cfg[$r['key']] = $r['value'];
    }
    $stripe_secret_key = $ext_cfg['secret_key'] ?? '';
} catch (Exception $e) {
    error_log('[Renewal/Process] Error loading stripe settings: ' . $e->getMessage());
}

if (empty($stripe_secret_key)) {
    http_response_code(500);
    echo json_encode(['error' => 'Configuration Stripe manquante.']);
    exit();
}

// ═══════════════════════════════════════════
// 2. RÉCUPÉRATION DE LA COMMANDE
// ═══════════════════════════════════════════
try {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ? LIMIT 1");
    $stmt->execute([$order_row_id, $_SESSION['user_id']]);
    $order = $stmt->fetch();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur serveur']);
    exit();
}

if (!$order) {
    http_response_code(404);
    echo json_encode(['error' => 'Commande introuvable']);
    exit();
}

$price = (float)$order['renewal_price'];
if ($price <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Montant invalide']);
    exit();
}

$amount_cents = (int)round($price * 100);

// ═══════════════════════════════════════════
// 3. RÉCUPÉRATION DU STRIPE CUSTOMER
// ═══════════════════════════════════════════
$stripe_customer_id = null;
try {
    $u_stmt = $pdo->prepare('SELECT stripe_customer_id FROM users WHERE id = ? LIMIT 1');
    $u_stmt->execute([$_SESSION['user_id']]);
    $stripe_customer_id = $u_stmt->fetchColumn() ?: null;
} catch (Exception $e) { /* silencieux */ }

// ═══════════════════════════════════════════
// 4. CRÉATION DU PAYMENTINTENT
// ═══════════════════════════════════════════
try {
    $ch = curl_init('https://api.stripe.com/v1/payment_intents');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_USERPWD        => $stripe_secret_key . ':',
        CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_POSTFIELDS     => http_build_query(array_filter([
            'amount'                 => $amount_cents,
            'currency'               => 'eur',
            'automatic_payment_methods' => ['enabled' => 'true'],
            'customer'               => $stripe_customer_id,
            'description'            => 'Renouvellement : ' . $order['service_name'],
            'metadata[user_id]'      => $_SESSION['user_id'],
            'metadata[order_id]'     => $order_row_id,
            'metadata[order_uuid]'   => $order['order_id'] ?? '',
            'metadata[type]'         => 'renewal',
            'metadata[service_name]' => $order['service_name'],
            'metadata[amount]'       => $price,
        ])),
    ]);

    $response  = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code < 200 || $http_code >= 300) {
        $err = json_decode($response, true);
        error_log('[Renewal/Process] Stripe error: ' . ($err['error']['message'] ?? 'unknown'));
        http_response_code(502);
        echo json_encode(['error' => 'Erreur Stripe : ' . ($err['error']['message'] ?? 'inconnue')]);
        exit();
    }

    $pi = json_decode($response, true);

    echo json_encode([
        'client_secret'     => $pi['client_secret'],
        'payment_intent_id' => $pi['id'],
        'amount'            => $price,
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Exception : ' . $e->getMessage()]);
}
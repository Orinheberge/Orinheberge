<?php
/**
 * /client/servers/upgrade/checkout/process.php
 * Endpoint backend : crée le PaymentIntent pour l'upgrade.
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
$uuid          = trim($input['uuid'] ?? '');
$new_product_id = (int)($input['product_id'] ?? 0);
$billing_type  = $input['billing'] ?? 'diff';

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
    error_log('[Upgrade/Process] Error loading stripe settings: ' . $e->getMessage());
}

if (empty($stripe_secret_key)) {
    http_response_code(500);
    echo json_encode(['error' => 'Configuration Stripe manquante.']);
    exit();
}

// ═══════════════════════════════════════════
// 2. VALIDATION DES PARAMÈTRES
// ═══════════════════════════════════════════
if (!$uuid || !$new_product_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Paramètres invalides.']);
    exit();
}

// ═══════════════════════════════════════════
// 3. RÉCUPÉRATION DU SERVEUR
// ═══════════════════════════════════════════
try {
    $srv_stmt = $pdo->prepare('
        SELECT o.*, p.name AS current_product_name
        FROM orders o
        LEFT JOIN products p ON p.id = o.product_id
        WHERE o.uuid = ? AND o.user_id = ?
        LIMIT 1
    ');
    $srv_stmt->execute([$uuid, $_SESSION['user_id']]);
    $server = $srv_stmt->fetch();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur serveur.']);
    exit();
}

if (!$server || ($server['status'] ?? '') !== 'paid') {
    http_response_code(400);
    echo json_encode(['error' => 'Serveur introuvable ou non éligible.']);
    exit();
}

// Nouveau produit
try {
    $np_stmt = $pdo->prepare('SELECT * FROM products WHERE id = ? AND is_active = 1 LIMIT 1');
    $np_stmt->execute([$new_product_id]);
    $new_product = $np_stmt->fetch();
} catch (Exception $e) {
    $new_product = null;
}

if (!$new_product) {
    http_response_code(400);
    echo json_encode(['error' => 'Offre invalide.']);
    exit();
}

$current_price = (float)($server['renewal_price'] ?? 0);
$new_price     = (float)$new_product['price'];

if ($new_price <= $current_price) {
    http_response_code(400);
    echo json_encode(['error' => 'Le nouveau produit doit être plus cher.']);
    exit();
}

// ═══════════════════════════════════════════
// 4. CALCUL DU MONTANT
// ═══════════════════════════════════════════
$diff_price = $new_price - $current_price;

if ($billing_type === 'prorata') {
    $billing_cycle_days = 30;
    $next_billing = !empty($server['next_due_date']) ? strtotime($server['next_due_date']) : strtotime('+30 days');
    $days_remaining = max(1, (int)ceil(($next_billing - time()) / 86400));
    $daily_old = $current_price / $billing_cycle_days;
    $daily_new = $new_price / $billing_cycle_days;
    $diff_price = ($daily_new - $daily_old) * $days_remaining;
}

$diff_price = max(0.50, round($diff_price, 2));
$amount_cents = (int)round($diff_price * 100);

// ═══════════════════════════════════════════
// 5. RÉCUPÉRATION DU STRIPE CUSTOMER
// ═══════════════════════════════════════════
$stripe_customer_id = null;
try {
    $u_stmt = $pdo->prepare('SELECT stripe_customer_id FROM users WHERE id = ? LIMIT 1');
    $u_stmt->execute([$_SESSION['user_id']]);
    $stripe_customer_id = $u_stmt->fetchColumn() ?: null;
} catch (Exception $e) {
    // silencieux
}

// ═══════════════════════════════════════════
// 6. CRÉATION DU PAYMENTINTENT
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
            'description'            => 'Upgrade serveur : ' . $new_product['name'],
            'metadata[user_id]'      => $_SESSION['user_id'],
            'metadata[order_uuid]'   => $uuid,
            'metadata[type]'         => 'upgrade',
            'metadata[new_product_id]'    => $new_product_id,
            'metadata[current_product_id]' => $server['product_id'],
            'metadata[old_price]'    => $current_price,
            'metadata[new_price]'    => $new_price,
            'metadata[diff_price]'   => $diff_price,
            'metadata[billing_type]' => $billing_type,
        ])),
    ]);

    $response  = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code < 200 || $http_code >= 300) {
        $err = json_decode($response, true);
        error_log('[Upgrade/Process] Stripe error: ' . ($err['error']['message'] ?? 'unknown'));
        http_response_code(502);
        echo json_encode(['error' => 'Erreur Stripe : ' . ($err['error']['message'] ?? 'inconnue')]);
        exit();
    }

    $pi = json_decode($response, true);

    // Enregistrer la transaction en attente
    try {
        $pdo->prepare("
            INSERT INTO pending_upgrades 
            (pending_uuid, user_id, order_uuid, from_product_id, to_product_id, 
             old_price, new_price, diff_amount, stripe_payment_intent_id, created_at, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'pending')
            ON DUPLICATE KEY UPDATE status = 'pending', updated_at = NOW()
        ")->execute([
            'upg_' . bin2hex(random_bytes(8)),
            $_SESSION['user_id'],
            $uuid,
            $server['product_id'],
            $new_product_id,
            $current_price,
            $new_price,
            $diff_price,
            $pi['id']
        ]);
    } catch (Exception $e) {
        error_log('[Upgrade/Process] Pending insert skipped: ' . $e->getMessage());
    }

    echo json_encode([
        'client_secret' => $pi['client_secret'],
        'payment_intent_id' => $pi['id'],
        'amount' => $diff_price,
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Exception : ' . $e->getMessage()]);
}
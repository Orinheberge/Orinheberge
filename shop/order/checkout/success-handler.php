<?php
/**
 * Page de retour après paiement Stripe Elements.
 *
 * IMPORTANT : cette page NE crée PLUS le serveur Pterodactyl, la facture,
 * ni n'envoie les notifications. Tout ça est fait par webhook.php, qui est
 * la SEULE source de vérité (Stripe garantit son appel côté serveur, même
 * si l'utilisateur ferme l'onglet avant la redirection).
 *
 * Ici on se contente de :
 *   1. Vérifier que le paiement est bien "succeeded" côté Stripe
 *   2. Attendre (polling court) que le webhook ait fini de traiter la commande
 *   3. Récupérer le mot de passe du panel (stocké temporairement par le webhook)
 *   4. Préparer la session pour la page /shop/order/success/
 */
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/db.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['payment_intent'])) {
    header('Location: /shop/cart/');
    exit();
}

$user_id = $_SESSION['user_id'];
$payment_intent_id = $_GET['payment_intent'];

// Récupérer la clé Stripe pour vérifier le PaymentIntent
$ext_settings_raw = $pdo->query("SELECT e.slug, es.key, es.value FROM extension_settings es JOIN extensions e ON e.id = es.extension_id")->fetchAll();
$ext_cfg = [];
foreach ($ext_settings_raw as $r) $ext_cfg[$r['slug']][$r['key']] = $r['value'];
$stripe_secret_key = $ext_cfg['stripe']['secret_key'] ?? '';

// 1. Vérifier le paiement auprès de Stripe (ne JAMAIS faire confiance au seul paramètre GET)
$ch = curl_init("https://api.stripe.com/v1/payment_intents/" . urlencode($payment_intent_id));
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_USERPWD => $stripe_secret_key . ":",
]);
$pi_raw = json_decode(curl_exec($ch), true);
curl_close($ch);

if (($pi_raw['status'] ?? '') !== 'succeeded') {
    die("❌ Paiement non confirmé.");
}

$order_id   = $pi_raw['metadata']['order_id'] ?? null;
$pi_user_id = $pi_raw['metadata']['user_id'] ?? null;

if (!$order_id || $pi_user_id != $user_id) {
    die("❌ Erreur de vérification.");
}

// 2. Attendre que webhook.php ait traité la commande (poll court, ~5s max)
//    Le webhook Stripe arrive quasiment toujours en < 1s, mais on laisse une marge.
$paid_order = null;
$max_attempts = 10;

for ($i = 0; $i < $max_attempts; $i++) {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE paypal_order_id = ? AND user_id = ? AND status = 'paid' LIMIT 1");
    $stmt->execute([$payment_intent_id, $user_id]);
    $paid_order = $stmt->fetch();

    if ($paid_order) {
        break;
    }
    usleep(500000); // 0.5s
}

// Le webhook n'est pas encore passé -> page d'attente qui se rafraîchit toute seule
if (!$paid_order) {
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta http-equiv="refresh" content="3">
        <title>Finalisation en cours... | OrinHeberge</title>
        <style>
            body { background:#070a13; color:#e2e8f0; font-family:sans-serif; display:flex; align-items:center; justify-content:center; height:100vh; margin:0; }
            .box { text-align:center; }
            .spinner { width:40px; height:40px; border:3px solid rgba(255,255,255,0.1); border-top-color:#38bdf8; border-radius:50%; animation:spin 0.8s linear infinite; margin:0 auto 16px; }
            @keyframes spin { to { transform: rotate(360deg); } }
        </style>
    </head>
    <body>
        <div class="box">
            <div class="spinner"></div>
            <h2>Finalisation de votre commande...</h2>
            <p style="color:#94a3b8;font-size:14px;">Votre paiement est confirmé, on prépare votre serveur. Merci de patienter quelques secondes.</p>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// 3. Récupérer le mot de passe du panel, stocké temporairement par le webhook (relais one-shot)
$panel_password = null;
$cred_stmt = $pdo->prepare("SELECT password FROM pending_credentials WHERE order_id = ?");
$cred_stmt->execute([$paid_order['order_id']]);
$cred = $cred_stmt->fetch();

if ($cred) {
    $panel_password = $cred['password'];
    // On supprime immédiatement : le mot de passe ne doit être affiché qu'une seule fois
    $pdo->prepare("DELETE FROM pending_credentials WHERE order_id = ?")->execute([$paid_order['order_id']]);
}

// Récupérer l'id de la facture créée par le webhook
$inv_stmt = $pdo->prepare("SELECT id AS invoice_id FROM invoices WHERE order_id = ? AND status = 'paid' LIMIT 1");
$inv_stmt->execute([$paid_order['order_id']]);
$invoice = $inv_stmt->fetch();

// 4. Préparer la session pour la page de succès
$_SESSION['success_order_id']       = $paid_order['order_id'];
$_SESSION['success_server_id']      = $paid_order['server_id'];
$_SESSION['success_offer']          = $paid_order['service_name'];
$_SESSION['success_panel_password'] = $panel_password;
$_SESSION['success_invoice_id']     = $invoice['invoice_id'] ?? null;
$_SESSION['success_orders'] = [[
    'order_id'   => $paid_order['order_id'],
    'server_id'  => $paid_order['server_id'],
    'offer_name' => $paid_order['service_name'],
]];

header("Location: /shop/order/success/");
exit();
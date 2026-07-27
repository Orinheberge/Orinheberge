<?php
/**
 * Gestionnaire de succès de paiement
 * Crée le serveur Pterodactyl + Facture + Notifications
 */
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/db.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/api/Facture.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/smtp.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/webhook/discord.php';

// Récupérer les configs
$ext_settings_raw = $pdo->query("SELECT e.slug, es.key, es.value FROM extension_settings es JOIN extensions e ON e.id = es.extension_id")->fetchAll();
$ext_cfg = [];
foreach ($ext_settings_raw as $r) $ext_cfg[$r['slug']][$r['key']] = $r['value'];

$stripe_secret_key = $ext_cfg['stripe']['secret_key'] ?? '';
$discord_webhook_url = $ext_cfg['discord']['webhook_url'] ?? '';
$panel_url = $ext_cfg['pterodactyl']['panel_url'] ?? '';
$api_key_admin = $ext_cfg['pterodactyl']['api_key_admin'] ?? '';

$headers_admin = [
    "Authorization: Bearer $api_key_admin",
    "Accept: application/vnd.pterodactyl.v1+json",
    "Content-Type: application/json",
];

if (!isset($_SESSION['user_id']) || !isset($_GET['payment_intent'])) {
    header('Location: /shop/cart/');
    exit();
}

$payment_intent_id = $_GET['payment_intent'];

// 1. Vérifier le paiement auprès de Stripe
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

// 2. Récupérer les métadonnées
$order_id = $pi_raw['metadata']['order_id'] ?? null;
$user_id = $pi_raw['metadata']['user_id'] ?? null;

if (!$order_id || $user_id != $_SESSION['user_id']) {
    die("❌ Erreur de vérification.");
}

// 3. Vérifier si déjà traité (idempotence)
$already = $pdo->prepare("SELECT order_id FROM orders WHERE paypal_order_id = ? AND status = 'paid' LIMIT 1");
$already->execute([$payment_intent_id]);
if ($already->fetch()) {
    $_SESSION['success_order_id'] = $order_id;
    header("Location: /shop/order/success/");
    exit();
}

// 4. Récupérer la commande pending
$stmt = $pdo->prepare("SELECT * FROM orders WHERE order_id = ? AND user_id = ? AND status = 'pending' LIMIT 1");
$stmt->execute([$order_id, $user_id]);
$pending_order = $stmt->fetch();

if (!$pending_order) {
    die("❌ Commande introuvable.");
}

// 5. Récupérer l'utilisateur
$user_stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$user_stmt->execute([$user_id]);
$user = $user_stmt->fetch();

// 6. Créer le serveur Pterodactyl
$username_display = !empty($user['pseudo']) ? $user['pseudo'] : $user['firstname'];

// Fonction pour créer/get user panel
function getOrCreatePanelUser($panel_url, $headers, $user, $pdo) {
    // Chercher l'utilisateur sur le panel
    $ch = curl_init($panel_url . '/api/application/users?filter[email]=' . urlencode($user['email']));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
    ]);
    $result = json_decode(curl_exec($ch), true);
    curl_close($ch);
    
    if (!empty($result['data'][0]['attributes']['id'])) {
        return ['id' => $result['data'][0]['attributes']['id'], 'pass' => null];
    }
    
    // Créer l'utilisateur
    $password = bin2hex(random_bytes(8));
    $ch = curl_init($panel_url . '/api/application/users');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => json_encode([
            'email' => $user['email'],
            'username' => $user['pseudo'] ?? ('user_' . $user['id']),
            'first_name' => $user['firstname'] ?? 'User',
            'last_name' => $user['lastname'] ?? 'Account',
            'password' => $password,
        ]),
    ]);
    $result = json_decode(curl_exec($ch), true);
    curl_close($ch);
    
    return ['id' => $result['attributes']['id'], 'pass' => $password];
}

// Fonction simplifiée pour créer le serveur (à adapter selon votre egg/node)
function createPanelServer($panel_url, $headers, $offer, $panelUserId, $pdo) {
    // Récupérer le node et l'egg par défaut (à adapter)
    $node_id = $offer['panel_node_id'] ?? 1;
    $egg_id = 1; // Minecraft par défaut, à changer selon votre produit
    
    $ch = curl_init($panel_url . '/api/application/servers');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => json_encode([
            'name' => $offer['name'] . ' - ' . substr(md5(uniqid()), 0, 6),
            'user' => $panelUserId,
            'egg' => $egg_id,
            'docker_image' => 'ghcr.io/pterodactyl/yolks:java_17', // À adapter
            'startup' => 'java -Xms128M -Xmx' . $offer['ram'] . 'M -jar server.jar',
            'environment' => [],
            'limits' => [
                'memory' => $offer['ram'],
                'swap' => 0,
                'disk' => $offer['disk'],
                'io' => 500,
                'cpu' => $offer['cpu'],
            ],
            'feature_limits' => [
                'databases' => 2,
                'backups' => 3,
                'allocations' => 1,
            ],
            'allocation' => ['default' => 0],
        ]),
    ]);
    $result = json_decode(curl_exec($ch), true);
    curl_close($ch);
    
    return [
        'id' => $result['attributes']['id'],
        'uuid' => $result['attributes']['identifier'],
        'identifier' => $result['attributes']['identifier'],
    ];
}

$panelUser = getOrCreatePanelUser($panel_url, $headers_admin, $user, $pdo);
$pass = $panelUser['pass'];

$next_pay = date("Y-m-01", strtotime("+1 month"));
$created_orders = [];

// Créer le serveur
$srv = createPanelServer($panel_url, $headers_admin, [
    'name' => $pending_order['service_name'],
    'ram' => $pending_order['ram'],
    'disk' => $pending_order['disk'],
    'cpu' => $pending_order['cpu'],
    'panel_node_id' => 1, // À adapter
], $panelUser['id'], $pdo);

$new_order_id = strtoupper(substr(md5(uniqid('', true)), 0, 8));

// Insérer la nouvelle commande "paid"
$pdo->prepare("
    INSERT INTO orders (user_id, product_id, order_id, service_name, ram, disk, cpu,
        server_id, uuid, id_server_panel, status, paypal_order_id,
        renewal_price, next_payment_date, created_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'paid', ?, ?, ?, NOW())
")->execute([
    $user_id, 0, $new_order_id, $pending_order['service_name'],
    $pending_order['ram'], $pending_order['disk'], $pending_order['cpu'],
    $srv['id'], $srv['uuid'], $srv['identifier'],
    $payment_intent_id, $pending_order['renewal_price'], $next_pay
]);

// 7. Créer la facture payée
$created_invoice = createInvoice($pdo, [
    'user_id' => $user_id,
    'order_id' => $new_order_id,
    'service_name' => $pending_order['service_name'],
    'amount' => $pending_order['renewal_price'],
    'type' => 'purchase',
    'status' => 'paid',
    'payment_method' => 'stripe',
    'payment_ref' => $payment_intent_id,
    'paid_at' => date('Y-m-d H:i:s'),
]);

// 8. Notifications
send_order_confirmation_email(
    $pdo, $user['email'], $username_display,
    $new_order_id, $pending_order['service_name'], $pending_order['renewal_price'],
    $srv['identifier'], $pass, $panel_url
);

if ($discord_webhook_url) {
    sendDiscordWebhook(
        $discord_webhook_url, $new_order_id, $pending_order['service_name'],
        $pending_order['renewal_price'], $user['email'], $srv['uuid'], $srv['identifier']
    );
}

// 9. Supprimer la commande pending
$pdo->prepare("DELETE FROM orders WHERE order_id = ? AND status = 'pending'")->execute([$order_id]);
$pdo->prepare("DELETE FROM invoices WHERE order_id = ? AND status = 'pending'")->execute([$order_id]);

// 10. Sauvegarder en session pour la page de succès
$_SESSION['success_order_id'] = $new_order_id;
$_SESSION['success_email'] = $user['email'];
$_SESSION['success_server_id'] = $srv['id'];
$_SESSION['success_offer'] = $pending_order['service_name'];
$_SESSION['success_panel_password'] = $pass;
$_SESSION['success_invoice_id'] = $created_invoice['invoice_id'] ?? null;
$_SESSION['success_orders'] = [[
    'order_id' => $new_order_id,
    'server_id' => $srv['id'],
    'offer_name' => $pending_order['service_name'],
]];

// 11. Rediriger vers la page de succès
header("Location: /shop/order/success/");
exit();
?>
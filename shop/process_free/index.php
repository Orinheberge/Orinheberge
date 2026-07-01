<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: /login/');
    exit();
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/db.php';

// ─── Produit depuis BDD ───────────────────────────────────────
$slug = strtolower(trim($_GET['type'] ?? ''));
if (!$slug) die('Offre non spécifiée.');

$product = getProductBySlug($pdo, $slug);
if (!$product) die('Offre invalide ou inactive : ' . htmlspecialchars($slug));
if ($product['type'] !== 'free') die('Cette offre n\'est pas gratuite.');

// ─── Utilisateur ─────────────────────────────────────────────
$stmt = $pdo->prepare('SELECT * FROM users WHERE id=? LIMIT 1');
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
if (!$user) die('Utilisateur introuvable.');

// ─── Limite 5 serveurs par offre ─────────────────────────────
$check = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE user_id=? AND service_name=?');
$check->execute([$_SESSION['user_id'], $product['name']]);
if ($check->fetchColumn() >= 5) die('❌ Limite de 5 serveurs atteinte pour cette offre.');

// ─── Créer/récupérer user panel ──────────────────────────────
$panelUser = getOrCreatePanelUser($panel_url, $headers_admin, $user, $pdo);
$pass      = $panelUser['pass'];
if ($pass) $_SESSION['panel_password'] = $pass;

// ─── Créer le serveur ────────────────────────────────────────
$srv      = createPanelServer($panel_url, $headers_admin, $product, $panelUser['id']);
$order_id = strtoupper(substr(md5(uniqid('', true)), 0, 8));

// ─── Sauvegarder la commande ─────────────────────────────────
$pdo->prepare('
    INSERT INTO orders
      (user_id, product_id, order_id, service_name, ram, disk, cpu,
       server_id, uuid, id_server_panel, status, renewal_price, created_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'pending\', 0, NOW())
')->execute([
    $_SESSION['user_id'],
    $product['id'],
    $order_id,
    $product['name'],
    $product['ram'],
    $product['disk'],
    $product['cpu'],
    $srv['id'],
    $srv['uuid'],
    $srv['identifier'],
]);

// ─── Email de confirmation ───────────────────────────────────
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/smtp.php';
$username_display = !empty($user['pseudo']) ? $user['pseudo'] : $user['firstname'];
send_order_confirmation_email(
    $pdo, $user['email'], $username_display,
    $order_id, $product['name'], 0.0,
    $srv['identifier'], $pass ?? null, $panel_url
);

// ─── Session succès ──────────────────────────────────────────
$_SESSION['success_order_id']       = $order_id;
$_SESSION['success_email']          = $user['email'];
$_SESSION['success_server_id']      = $srv['id'];
$_SESSION['success_offer']          = $product['name'];
$_SESSION['success_panel_password'] = $pass ?? ($user['panel_password'] ?? null);

// ─── Notification Discord (si extension activée) ─────────────
$discord_ext = $pdo->query("
    SELECT es.value FROM extension_settings es
    JOIN extensions e ON e.id = es.extension_id
    WHERE e.slug='discord' AND e.is_enabled=1 AND es.key='webhook_url'
    LIMIT 1
")->fetchColumn();

if ($discord_ext) {
    $wh_data = [
        'username' => 'OrinHeberge',
        'embeds'   => [[
            'title'  => '📦 Nouvelle commande gratuite',
            'color'  => 3066993,
            'fields' => [
                ['name'=>'Commande',      'value'=>'`'.$order_id.'`',    'inline'=>true],
                ['name'=>'Offre',         'value'=>$product['name'],      'inline'=>true],
                ['name'=>'Utilisateur',   'value'=>$user['email'],        'inline'=>false],
                ['name'=>'Ressources',    'value'=>'RAM: '.$product['ram'].'MB | Disk: '.$product['disk'].'MB | CPU: '.$product['cpu'].'%', 'inline'=>false],
            ],
            'timestamp' => date('c'),
        ]],
    ];
    $ch = curl_init($discord_ext);
    curl_setopt_array($ch, [CURLOPT_HTTPHEADER=>['Content-Type: application/json'], CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>json_encode($wh_data), CURLOPT_RETURNTRANSFER=>true, CURLOPT_SSL_VERIFYPEER=>false]);
    curl_exec($ch); curl_close($ch);
}

header('Location: /shop/success/?type=free');
exit();

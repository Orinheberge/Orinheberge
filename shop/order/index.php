<?php
ini_set('display_errors', 0); // Désactiver l'affichage des erreurs en prod
error_reporting(E_ALL);
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: /login/");
    exit();
}

// ─── Config centrale depuis BDD ──────────────────────────────
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/db.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/api/Facture.php';
require_once __DIR__ . '/lib/stripe/stripe.php';
require_once __DIR__ . '/lib/paypal/paypal.php';
require_once __DIR__ . '/lib/promo/promo.php';
require_once __DIR__ . '/webhook/discord.php';
require_once __DIR__ . '/inc/lang.php';

// ─── Clés extensions depuis BDD ──────────────────────────────
$ext_settings_raw = $pdo->query("
    SELECT e.slug, es.key, es.value
    FROM extension_settings es
    JOIN extensions e ON e.id = es.extension_id
")->fetchAll();
$ext_cfg = [];
foreach ($ext_settings_raw as $r) $ext_cfg[$r['slug']][$r['key']] = $r['value'];

$stripe_secret_key = $ext_cfg['stripe']['secret_key'] ?? '';
$stripe_public_key = $ext_cfg['stripe']['public_key'] ?? '';
$paypalme_username = $ext_cfg['paypal']['username']   ?? 'metal544002009';
$discord_webhook_url = $ext_cfg['discord']['webhook_url'] ?? '';

// ─── Annulation de commande ──────────────────────────────────
if (isset($_GET['cancel']) && ($_GET['cancel'] === '1' || $_GET['cancel'] === 'true')) {
    $pending_order_id = $_SESSION['current_pending_order_id'] ?? null;
    if ($pending_order_id) {
        $cancel_stmt = $pdo->prepare("DELETE FROM orders WHERE order_id = ? AND user_id = ? AND status = 'pending' LIMIT 1");
        $cancel_stmt->execute([$pending_order_id, $_SESSION['user_id']]);
    }

    if (!empty($_SESSION['current_pending_invoice_id'])) {
        $pdo->prepare("DELETE FROM invoices WHERE invoice_id = ? AND user_id = ? AND status = 'pending' LIMIT 1")
            ->execute([$_SESSION['current_pending_invoice_id'], $_SESSION['user_id']]);
    }

    unset($_SESSION['current_pending_order_id'], $_SESSION['current_pending_invoice_id'], $_SESSION['checkout_bundle']);
    $_SESSION['order_cancelled'] = true;
    header('Location: /shop/cart/');
    exit();
}

// ─── Produit depuis BDD ─────────────────────────────────────
$bundle_items = [];
$bundle_total = 0.0;
$bundle_param = '';
$bundle_label = '';

$plan_param = trim($_GET['plan'] ?? $_GET['type'] ?? '');
$selected_slugs = [];
if ($plan_param !== '') {
    $raw_parts = array_filter(array_map('trim', explode(',', $plan_param)), 'strlen');
    foreach ($raw_parts as $raw_slug) {
        $selected_slugs[] = strtolower($raw_slug);
    }
}

if (empty($selected_slugs) && !empty($_SESSION['checkout_bundle']['items']) && is_array($_SESSION['checkout_bundle']['items'])) {
    foreach ($_SESSION['checkout_bundle']['items'] as $entry) {
        $slug = trim((string)($entry['slug'] ?? ''));
        if ($slug !== '') {
            $selected_slugs[] = strtolower($slug);
        }
    }
}

if (empty($selected_slugs)) {
    header('Location: /shop/cart/');
    exit();
}

function findSlugQuantity(string $slug): int {
    if (!empty($_SESSION['checkout_bundle']['items']) && is_array($_SESSION['checkout_bundle']['items'])) {
        foreach ($_SESSION['checkout_bundle']['items'] as $entry) {
            if (strtolower((string)($entry['slug'] ?? '')) === strtolower($slug)) {
                return max(1, (int)($entry['quantity'] ?? 1));
            }
        }
    }
    return 1;
}

$free_bundle_items = [];

foreach ($selected_slugs as $slug) {
    $product = getProductBySlug($pdo, $slug);
    if (!$product) continue;

    $quantity = findSlugQuantity($slug);

    if ((string)($product['type'] ?? '') === 'free') {
        $free_bundle_items[] = ['product' => $product, 'quantity' => $quantity];
        continue;
    }

    $bundle_items[] = ['product' => $product, 'quantity' => $quantity];
    $bundle_total += (float)($product['price'] ?? 0) * $quantity;
}

/*
|--------------------------------------------------------------------------
| TRAITEMENT IMMÉDIAT DES OFFRES GRATUITES DU BUNDLE
|--------------------------------------------------------------------------
*/
if (!empty($free_bundle_items)) {
    $free_key = md5(implode('|', array_map(
        static fn($e) => $e['product']['slug'] . ':' . $e['quantity'],
        $free_bundle_items
    )));

    if (($_SESSION['processed_free_bundle_key'] ?? null) !== $free_key) {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id=? LIMIT 1');
        $stmt->execute([$_SESSION['user_id']]);
        $free_user = $stmt->fetch();

        if ($free_user) {
            require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/smtp.php';
            $free_username_display = !empty($free_user['pseudo']) ? $free_user['pseudo'] : $free_user['firstname'];
            $free_created = [];

            foreach ($free_bundle_items as $free_entry) {
                $free_product = $free_entry['product'];

                $limit_check = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE user_id=? AND service_name=?');
                $limit_check->execute([$_SESSION['user_id'], $free_product['name']]);
                if ($limit_check->fetchColumn() >= 5) {
                    $_SESSION['checkout_error'] = "❌ Limite de 5 serveurs atteinte pour l'offre : " . $free_product['name'];
                    continue;
                }

                for ($i = 0; $i < $free_entry['quantity']; $i++) {
                    $free_panelUser = getOrCreatePanelUser($panel_url, $headers_admin, $free_user, $pdo);
                    $free_pass      = $free_panelUser['pass'];
                    if ($free_pass) $_SESSION['panel_password'] = $free_pass;

                    $free_srv = createPanelServerWithAutoTransfer($panel_url, $headers_admin, $free_product, $free_panelUser['id']);
                    $free_order_id = strtoupper(substr(md5(uniqid('', true)), 0, 8));

                    $pdo->prepare('
                        INSERT INTO orders
                          (user_id, product_id, order_id, service_name, ram, disk, cpu,
                           server_id, uuid, id_server_panel, status, renewal_price, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'paid\', 0, NOW())
                    ')->execute([
                        $_SESSION['user_id'], $free_product['id'], $free_order_id, $free_product['name'],
                        $free_product['ram'], $free_product['disk'], $free_product['cpu'],
                        $free_srv['id'], $free_srv['uuid'], $free_srv['identifier'],
                    ]);

                    $free_invoice = createInvoice($pdo, [
                        'user_id'        => $_SESSION['user_id'],
                        'order_id'       => $free_order_id,
                        'service_name'   => $free_product['name'],
                        'amount'         => 0.00,
                        'type'           => 'purchase',
                        'status'         => 'paid',
                        'payment_method' => 'free',
                        'payment_ref'    => 'FREE-OFFER',
                        'paid_at'        => date('Y-m-d H:i:s'),
                    ]);

                    send_order_confirmation_email(
                        $pdo, $free_user['email'], $free_username_display,
                        $free_order_id, $free_product['name'], 0.0,
                        $free_srv['identifier'], $free_pass ?? null, $panel_url
                    );

                    if ($discord_webhook_url) {
                        sendDiscordWebhook(
                            $discord_webhook_url, $free_order_id, $free_product['name'],
                            0.0, $free_user['email'], $free_srv['uuid'], $free_srv['identifier']
                        );
                    }

                    $free_created[] = [
                        'order_id'   => $free_order_id,
                        'server_id'  => $free_srv['id'],
                        'offer_name' => $free_product['name'],
                        'invoice_id' => $free_invoice['invoice_id'] ?? null,
                    ];
                }
            }

            if (!empty($free_created)) {
                $_SESSION['processed_free_bundle_key'] = $free_key;
                $_SESSION['success_orders'] = array_merge($_SESSION['success_orders'] ?? [], $free_created);
                $_SESSION['success_email']  = $free_user['email'];
                $last_free = end($free_created);
                $_SESSION['success_order_id'] = $last_free['order_id'];
                $_SESSION['success_offer']    = $last_free['offer_name'];
                $_SESSION['success_server_id'] = $last_free['server_id'];
                $_SESSION['success_panel_password'] = $free_pass ?? ($free_user['panel_password'] ?? null);
                if (!empty($last_free['invoice_id'])) {
                    $_SESSION['success_invoice_id'] = $last_free['invoice_id'];
                }
            }
        }
    }

    if (empty($bundle_items)) {
        if (!empty($_SESSION['success_orders']) || !empty($_SESSION['success_order_id'])) {
            unset($_SESSION['checkout_bundle']);
            header('Location: /shop/order/success/');
        } else {
            header('Location: /shop/cart/');
        }
        exit();
    }
}

if (empty($bundle_items)) {
    header('Location: /shop/cart/');
    exit();
}

$offer = $bundle_items[0]['product'];
$bundle_param = implode(',', $selected_slugs);
$bundle_label = count($bundle_items) > 1 ? implode(' + ', array_map(static function ($entry) {
    return $entry['product']['name'];
}, $bundle_items)) : $offer['name'];
$type = strtolower(trim($offer['slug'] ?? $selected_slugs[0] ?? ''));

$stmt = $pdo->prepare("SELECT * FROM users WHERE id=?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
if (!$user) die("Utilisateur introuvable.");

// ─── Nodes disponibles pour cette offre ──────────────────────
$avail_nodes_stmt = $pdo->prepare("
    SELECT n.id, n.name, n.fqdn, n.location_id
    FROM product_nodes pn
    JOIN nodes n ON n.id = pn.node_id
    WHERE pn.product_id = ? AND n.is_active = 1
    ORDER BY n.id
");
$avail_nodes_stmt->execute([$offer['id']]);
$avail_nodes = $avail_nodes_stmt->fetchAll();

if (empty($avail_nodes)) {
    $fn = $pdo->prepare("SELECT id, name, fqdn, location_id FROM nodes WHERE id=? AND is_active=1");
    $fn->execute([$offer['node_id']]);
    $avail_nodes = array_filter([$fn->fetch()]);
}

$chosen_node_id = (int)($_POST['chosen_node_id'] ?? $_GET['node'] ?? ($avail_nodes[0]['id'] ?? $offer['node_id']));
$valid_node_ids = array_column($avail_nodes, 'id');
if (!in_array($chosen_node_id, $valid_node_ids)) {
    $chosen_node_id = $avail_nodes[0]['id'] ?? $offer['node_id'];
}

$cn_stmt = $pdo->prepare("SELECT * FROM nodes WHERE id=?");
$cn_stmt->execute([$chosen_node_id]);
$chosen_node = $cn_stmt->fetch();

if ($chosen_node) {
    $offer['location_id']  = $chosen_node['location_id'];
    $offer['panel_node_id'] = $chosen_node['panel_node_id'] ?? $offer['panel_node_id'];
}

$check = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id=? AND service_name=?");
$check->execute([$_SESSION['user_id'], $offer['name']]);
if ($check->fetchColumn() >= 5) die("❌ Limite de 5 serveurs atteinte.");

if (isset($_GET['clear_promo'])) {
    unset($_SESSION['promo_code']);
    header("Location: ?plan=" . urlencode($bundle_param ?: $type));
    exit();
}

/*
|--------------------------------------------------------------------------
| LOGIQUE CODE PROMO
|--------------------------------------------------------------------------
*/
$promo_context  = count($bundle_items) > 1 ? 'cart' : $type;
$active_promo   = getActiveAutoPromo($promos);
$promo_error    = null;
$applied_promo  = null;

if (isset($_POST['promo_code']) && !empty($_POST['promo_code'])) {
    $input_code = preg_replace('/\s+/u', '', $_POST['promo_code']);
    $manual = checkPromoCode($promos, $input_code, $promo_context);
    
    if ($manual) {
        $applied_promo = $manual;
        $_SESSION['promo_code'] = $manual['code'];
    } else {
        $promo_error = "Code invalide ou expiré.";
    }
} elseif (isset($_SESSION['promo_code'])) {
    $applied_promo = checkPromoCode($promos, $_SESSION['promo_code'], $promo_context);
}

$promo = $applied_promo ?? $active_promo;
$prices = $promo ? applyPromo((float)$bundle_total, $promo) : [
    'original_price' => (float)$bundle_total,
    'reduction'      => 0,
    'final_price'    => (float)$bundle_total,
    'label'          => null,
];
$final_price = $prices['final_price'];

/*
|--------------------------------------------------------------------------
| SUIVI ET GESTION DE LA COMMANDE EN ATTENTE (PENDING)
|--------------------------------------------------------------------------
*/
$order_id = $_SESSION['current_pending_order_id'] ?? null;

if (!$order_id) {
    $order_id = strtoupper(substr(md5(uniqid('', true)), 0, 8));
    $next_pay = date("Y-m-01", strtotime("+1 month"));

    $pdo->prepare("
        INSERT INTO orders (user_id, order_id, service_name, ram, disk, cpu,
            server_id, uuid, id_server_panel, status, paypal_order_id,
            renewal_price, next_payment_date, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NULL, NULL, NULL, 'pending', NULL, ?, ?, NOW())
    ")->execute([
        $_SESSION['user_id'], $order_id, $bundle_label,
        $offer['ram'], $offer['disk'], $offer['cpu'],
        $final_price, $next_pay
    ]);
    
    $_SESSION['current_pending_order_id'] = $order_id;

    $pending_invoice = createInvoice($pdo, [
        'user_id'      => $_SESSION['user_id'],
        'order_id'     => $order_id,
        'service_name' => $bundle_label,
        'amount'       => $final_price,
        'type'         => 'purchase',
        'status'       => 'pending',
        'due_date'     => date('Y-m-d', strtotime('+3 days')),
    ]);

    if ($pending_invoice) {
        $_SESSION['current_pending_invoice_id'] = $pending_invoice['invoice_id'];
    }
} else {
    $pdo->prepare("UPDATE orders SET renewal_price = ?, service_name = ? WHERE order_id = ? AND status = 'pending'")
        ->execute([$final_price, $bundle_label, $order_id]);
}

// ─── REDIRECTION VERS LE CHECKOUT STRIPE ELEMENTS ────────────
header("Location: /shop/order/checkout/?order_id=" . urlencode($order_id));
exit();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orinheberge | Paiement</title>
    <link class="rounded-full" rel="icon" type="image/png" href="https://heberge.orinstone.deepstone.fr/favicon.ico">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #070a13; scroll-behavior: smooth; }
        .glass {
            background: rgba(255,255,255,0.03);
            backdrop-filter: blur(14px);
            border: 1px solid rgba(255,255,255,0.06);
        }
    </style>
    <link rel="manifest" href="/manifest.json">
</head>
<body class="text-gray-200 font-sans min-h-screen flex flex-col justify-between">

<?php $active_nav = 'order'; include $_SERVER['DOCUMENT_ROOT'] . '/inc/navbar.php'; ?>

<div class="flex-grow flex items-center justify-center px-4 py-4 mb-12">
    <div class="glass p-8 sm:p-10 rounded-2xl w-full max-w-xl text-center border border-white/[0.05] shadow-2xl">
        <div class="w-16 h-16 bg-sky-500/10 border border-sky-500/30 text-sky-400 rounded-full flex items-center justify-center mx-auto mb-6 text-2xl animate-pulse">
            <i class="fas fa-spinner fa-spin"></i>
        </div>
        <h1 class="text-2xl font-black tracking-tight mb-2">Préparation du paiement...</h1>
        <p class="text-gray-500 text-sm mb-6">
            Commande <span class="text-sky-400 font-bold">#<?= htmlspecialchars($order_id, ENT_QUOTES, 'UTF-8') ?></span>
        </p>
        
        <?php if (!empty($_SESSION['order_cancelled'])): ?>
        <div class="mb-4 p-3 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-300 text-sm">
            <i class="fas fa-check-circle mr-2"></i> La commande en attente a bien été annulée.
        </div>
        <?php unset($_SESSION['order_cancelled']); ?>
        <?php endif; ?>

        <div class="bg-white/5 border border-white/[0.05] p-4 rounded-xl text-left mb-4">
            <p class="text-xs text-gray-400 uppercase font-bold tracking-wider mb-2">Récapitulatif</p>
            <?php foreach ($bundle_items as $bundle_entry): ?>
            <div class="text-sm text-white flex justify-between">
                <span><?= htmlspecialchars($bundle_entry['product']['name'], ENT_QUOTES, 'UTF-8') ?></span>
                <?php if ((int)$bundle_entry['quantity'] > 1): ?>
                <span class="text-gray-400">×<?= (int)$bundle_entry['quantity'] ?></span>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <div class="border-t border-white/10 my-2"></div>
            <div class="flex justify-between text-lg font-bold">
                <span class="text-white">Total</span>
                <?php if ($promo): ?>
                    <span class="text-green-400"><?= number_format($final_price, 2, '.', '') ?>€</span>
                <?php else: ?>
                    <span class="text-sky-400"><?= number_format($final_price, 2, '.', '') ?>€</span>
                <?php endif; ?>
            </div>
        </div>

        <div class="mt-4 p-3 rounded-xl bg-sky-500/5 border border-sky-500/10 text-xs text-gray-400 text-left flex gap-2">
            <i class="fas fa-circle-info text-sky-400 mt-0.5 shrink-0"></i>
            <span>Redirection automatique vers le paiement sécurisé Stripe Elements...</span>
        </div>
    </div>
</div>

<?php $active_nav = 'order'; include $_SERVER['DOCUMENT_ROOT'] . '/inc/footer.php'; ?>
<script src="/inc/navbar.js"></script>
</body>
</html>
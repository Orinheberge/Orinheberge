<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: /login/");
    exit();
}

// ─── Config centrale depuis BDD ──────────────────────────────
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/db.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/api/Facture.php'; // ✅ Fonction createInvoice()
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

    // 🔵 SUPPRESSION de la facture pending associée
    if (!empty($_SESSION['current_pending_invoice_id'])) {
        $pdo->prepare("DELETE FROM invoices WHERE invoice_id = ? AND user_id = ? AND status = 'pending' LIMIT 1")
            ->execute([$_SESSION['current_pending_invoice_id'], $_SESSION['user_id']]);
    }

    unset($_SESSION['current_pending_order_id'], $_SESSION['current_pending_invoice_id'], $_SESSION['checkout_bundle']);
    $_SESSION['order_cancelled'] = true;
    header('Location: /shop/cart/');
    exit();
}

// ─── Produit depuis BDD ──────────────────────────────────────
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
    if (!$product) {
        continue;
    }

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

                    $free_srv      = createPanelServer($panel_url, $headers_admin, $free_product, $free_panelUser['id']);
                    $free_order_id = strtoupper(substr(md5(uniqid('', true)), 0, 8));

                    $pdo->prepare('
                        INSERT INTO orders
                          (user_id, product_id, order_id, service_name, ram, disk, cpu,
                           server_id, uuid, id_server_panel, status, renewal_price, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'pending\', 0, NOW())
                    ')->execute([
                        $_SESSION['user_id'], $free_product['id'], $free_order_id, $free_product['name'],
                        $free_product['ram'], $free_product['disk'], $free_product['cpu'],
                        $free_srv['id'], $free_srv['uuid'], $free_srv['identifier'],
                    ]);

                    // 🔵 MODIFICATION 1 : Création de la facture gratuite
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

    // Le bundle ne contient QUE des offres gratuites -> pas de paiement à faire
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
| TRAITEMENT DU RETOUR PAIEMENT (STRIPE SUCCESS)
|--------------------------------------------------------------------------
*/
if (isset($_GET['session_id'])) {
    $session = getStripeSession($stripe_secret_key, $_GET['session_id']);

    if (($session['payment_status'] ?? '') !== 'paid') {
        die("❌ Paiement non confirmé. Statut : " . htmlspecialchars($session['payment_status'] ?? 'inconnu', ENT_QUOTES, 'UTF-8'));
    }

    $already = $pdo->prepare("SELECT order_id FROM orders WHERE paypal_order_id = ? LIMIT 1");
    $already->execute([$_GET['session_id']]);
    if ($already_row = $already->fetch()) {
        $_SESSION['success_order_id'] = $already_row['order_id'];
        $_SESSION['success_offer']    = $bundle_label;
        $_SESSION['success_email']    = $user['email'];
        header("Location: /shop/order/success/");
        exit();
    }

    require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/smtp.php';
    $username_display = !empty($user['pseudo']) ? $user['pseudo'] : $user['firstname'];

    $panelUser = getOrCreatePanelUser($panel_url, $headers_admin, $user, $pdo);
    $pass      = $panelUser['pass'];
    if ($pass) $_SESSION['panel_password'] = $pass;

    $next_pay      = date("Y-m-01", strtotime("+1 month"));
    $created_orders = [];

    foreach ($bundle_items as $bundle_entry) {
        $item_product = $bundle_entry['product'];
        $item_qty     = max(1, (int)$bundle_entry['quantity']);

        $server_offer = $item_product;
        if ($item_product['id'] === $offer['id'] && $chosen_node) {
            $server_offer['location_id']   = $chosen_node['location_id'];
            $server_offer['panel_node_id'] = $chosen_node['panel_node_id'] ?? $server_offer['panel_node_id'];
        }

        $item_share = $bundle_total > 0
            ? ((float)$item_product['price'] * $item_qty) / $bundle_total
            : 0;
        $item_renewal_price = round($final_price * $item_share / $item_qty, 2);

        for ($i = 0; $i < $item_qty; $i++) {
            $srv      = createPanelServer($panel_url, $headers_admin, $server_offer, $panelUser['id']);
            $order_id = strtoupper(substr(md5(uniqid('', true)), 0, 8));

            $pdo->prepare("
                INSERT INTO orders (user_id, product_id, order_id, service_name, ram, disk, cpu,
                    server_id, uuid, id_server_panel, status, paypal_order_id,
                    renewal_price, next_payment_date, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'paid', ?, ?, ?, NOW())
            ")->execute([
                $_SESSION['user_id'], $item_product['id'], $order_id, $item_product['name'],
                $item_product['ram'], $item_product['disk'], $item_product['cpu'],
                $srv['id'], $srv['uuid'], $srv['identifier'],
                $_GET['session_id'], $item_renewal_price, $next_pay
            ]);

            sendDiscordWebhook(
                $discord_webhook_url, $order_id, $item_product['name'],
                $item_renewal_price, $user['email'], $srv['uuid'], $srv['identifier']
            );

            send_order_confirmation_email(
                $pdo, $user['email'], $username_display,
                $order_id, $item_product['name'], $item_renewal_price,
                $srv['identifier'], $pass ?? null, $panel_url
            );

            $created_orders[] = [
                'order_id'    => $order_id,
                'server_id'   => $srv['id'],
                'offer_name'  => $item_product['name'],
            ];
        }
    }

    // 🔵 MODIFICATION 2 : Remplacement du bloc INSERT INTO invoices par createInvoice()
    $first_order_id = $created_orders[0]['order_id'];
    
    $created_invoice = createInvoice($pdo, [
        'user_id'        => $_SESSION['user_id'],
        'order_id'       => $first_order_id,
        'service_name'   => $bundle_label,
        'amount'         => $final_price,
        'type'           => 'purchase',
        'status'         => 'paid',
        'payment_method' => 'stripe',
        'payment_ref'    => $_GET['session_id'],
        'paid_at'        => date('Y-m-d H:i:s'),
    ]);

    if ($created_invoice) {
        $_SESSION['success_invoice_id'] = $created_invoice['invoice_id'];
    }

    // La commande "pending" créée avant paiement n'a plus lieu d'être
    if (!empty($_SESSION['current_pending_order_id'])) {
        $pdo->prepare("DELETE FROM orders WHERE order_id = ? AND status = 'pending'")
            ->execute([$_SESSION['current_pending_order_id']]);
    }

    // 🔵 SUPPRESSION de la facture pending associée (si elle existe)
    if (!empty($_SESSION['current_pending_invoice_id'])) {
        $pdo->prepare("DELETE FROM invoices WHERE invoice_id = ? AND status = 'pending'")
            ->execute([$_SESSION['current_pending_invoice_id']]);
    }

    $_SESSION['success_order_id']       = $first_order_id;
    $_SESSION['success_email']          = $user['email'];
    $_SESSION['success_server_id']      = $created_orders[0]['server_id'];
    $_SESSION['success_offer']          = $bundle_label;
    $_SESSION['success_panel_password'] = $pass ?? ($user['panel_password'] ?? null);
    $_SESSION['success_orders']         = $created_orders;

    unset($_SESSION['promo_code'], $_SESSION['current_pending_order_id'], $_SESSION['current_pending_invoice_id'], $_SESSION['checkout_bundle']);

    header("Location: /shop/order/success/");
    exit();
}

/*
|--------------------------------------------------------------------------
| SUIVI ET GESTION DE LA COMMANDE EN ATTENTE (PENDING)
|--------------------------------------------------------------------------
*/
if (!isset($_SESSION['current_pending_order_id'])) {
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

    // 🔵 MODIFICATION 3 : Création d'une facture en attente
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
    $order_id = $_SESSION['current_pending_order_id'];
    
    $pdo->prepare("UPDATE orders SET renewal_price = ?, service_name = ? WHERE order_id = ? AND status = 'pending'")
        ->execute([$final_price, $bundle_label, $order_id]);
}

$checkout_offer = array_merge($offer, [
    'name' => $bundle_label,
    'price' => $final_price,
    'slug' => $bundle_param ?: $type,
]);
$stripe_url = '';
try {
    $stripe_session = createStripeSession(
        $stripe_secret_key,
        $checkout_offer,
        $bundle_param ?: $type,
        "https://heberge.orinstone.deepstone.fr/shop/order/?plan=" . urlencode($bundle_param ?: $type) . "&session_id={CHECKOUT_SESSION_ID}",
        "https://heberge.orinstone.deepstone.fr/shop/"
    );
    $stripe_url = $stripe_session['checkout_url'] ?? '';
    if (empty($stripe_url)) {
        throw new Exception('Stripe did not return a checkout URL');
    }
} catch (Throwable $e) {
    error_log('Stripe session creation failed: ' . $e->getMessage());
    $_SESSION['checkout_error'] = 'Impossible de démarrer le paiement. Veuillez réessayer plus tard.';
    header('Location: /shop/cart/');
    exit();
}
$paypalme_url = getPaypalMeLink($paypalme_username, $final_price);
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
        .gradient-text {
            background: linear-gradient(90deg, #38bdf8, #818cf8);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
    </style>
    <link rel="manifest" href="/manifest.json">
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/sw.js')
                    .then(reg => console.log('Service Worker enregistré avec succès ! Scope:', reg.scope))
                    .catch(err => console.log('Échec de l\'enregistrement du Service Worker:', err));
            });
        }
    </script>
</head>
<body class="text-gray-200 font-sans min-h-screen flex flex-col justify-between">

<!-- ═══════════════════════════════════════════════════════════════ -->
<!-- NAVIGATION (inchangée) -->
<!-- ═══════════════════════════════════════════════════════════════ -->
<nav class="sticky top-0 z-50 glass p-5 border-b border-white/5">
    <div class="max-w-7xl mx-auto flex items-center gap-4">
        <h1 class="text-3xl font-black gradient-text tracking-tight shrink-0">
            <a href="/">OrinHeberge</a>
        </h1>
        <div class="hidden md:flex items-center gap-2 lg:gap-3 flex-1 justify-end flex-wrap">
            <a href="/" class="<?php echo $active_nav === 'home' ? 'bg-sky-600/30 text-sky-400 border-sky-500/50 font-bold' : 'bg-sky-600/5 text-sky-400/70 hover:text-sky-300 border-sky-500/10 hover:bg-sky-600/20'; ?> px-4 py-2 rounded-full text-xs flex items-center gap-2 transition font-medium shadow-md border whitespace-nowrap">
                <i class="fas fa-home"></i> <?php echo t('nav.home'); ?>
            </a>
            <a href="/client/servers/" class="<?php echo $active_nav === 'servers' ? 'bg-slate-600/40 text-slate-300 border-slate-500/60 font-bold' : 'bg-slate-600/10 text-slate-400 hover:text-slate-200 border-slate-500/15 hover:bg-slate-600/30'; ?> px-4 py-2 rounded-full text-xs flex items-center gap-2 transition font-medium shadow-md border whitespace-nowrap">
                <i class="fas fa-server"></i> <?php echo t('nav.servers'); ?>
            </a>
            <div class="relative group">
                <button class="text-gray-300 hover:text-sky-400 font-bold flex items-center gap-2.5 transition bg-white/5 hover:bg-white/10 px-4 py-2 rounded-full border border-white/5 focus:outline-none text-xs whitespace-nowrap">
                    <i class="fas fa-tags"></i> Boutique
                </button>
                <div class="absolute right-0 mt-2 w-56 rounded-2xl border border-white/10 bg-[#11151d] shadow-2xl shadow-black/30 py-2 hidden group-hover:block group-focus-within:block">
                    <a href="/shop/" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-300 hover:bg-white/5 hover:text-white">
                        <i class="fas fa-tags w-4"></i> <?php echo t('nav.offers'); ?>
                    </a>
                    <a href="/shop/cart/" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-300 hover:bg-white/5 hover:text-white">
                        <i class="fas fa-shopping-cart w-4"></i> Mon panier
                    </a>
                </div>
            </div>
            <a href="/support/" class="<?php echo $active_nav === 'support' ? 'bg-purple-600/30 text-purple-400 border-purple-500/50 font-bold' : 'bg-purple-600/5 text-purple-400/70 hover:text-purple-300 border-purple-500/10 hover:bg-purple-600/20'; ?> px-4 py-2 rounded-full text-xs flex items-center gap-2 transition font-medium shadow-md border whitespace-nowrap">
                <i class="fas fa-headset"></i> <?php echo t('nav.support'); ?>
            </a>
            <?php if (isset($_SESSION['user_id'])): ?>
                <?php if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/inc/notifications.php')): ?>
                    <?php include $_SERVER['DOCUMENT_ROOT'] . '/inc/notifications.php'; ?>
                <?php endif; ?>
                <div class="relative group">
                    <button class="text-gray-300 hover:text-sky-400 font-bold flex items-center gap-2.5 transition bg-white/5 hover:bg-white/10 px-4 py-2 rounded-full border border-white/5 focus:outline-none text-xs whitespace-nowrap">
                        <?php if (!empty($_SESSION['avatar']) && file_exists($_SERVER['DOCUMENT_ROOT'] . '/' . $_SESSION['avatar'])): ?>
                            <img src="/<?php echo htmlspecialchars($_SESSION['avatar']); ?>" alt="Avatar" class="w-5 h-5 rounded-full object-cover border border-sky-500/30 shrink-0">
                        <?php else: ?>
                            <i class="fas fa-user-circle text-lg text-sky-400 shrink-0"></i>
                        <?php endif; ?>
                        <span><?php echo htmlspecialchars($_SESSION['username'] ?? t('nav.profile')); ?></span>
                        <i class="fas fa-chevron-down text-[10px] opacity-70"></i>
                    </button>
                    <div class="absolute right-0 mt-2 w-56 rounded-2xl border border-white/10 bg-[#11151d] shadow-2xl shadow-black/30 py-2 hidden group-hover:block group-focus-within:block">
                        <a href="/profil/" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-300 hover:bg-white/5 hover:text-white">
                            <i class="fas fa-user w-4"></i> Profil
                        </a>
                        <a href="/client/servers/" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-300 hover:bg-white/5 hover:text-white">
                            <i class="fas fa-server w-4"></i> Mes serveurs
                        </a>
                        <?php if (!empty($_SESSION['is_admin'])): ?>
                            <a href="/admin/" class="flex items-center gap-2 px-4 py-2 text-sm text-amber-400 hover:bg-white/5 hover:text-amber-300">
                                <i class="fas fa-shield-halved"></i> Administration
                            </a>
                        <?php endif; ?>
                        <hr class="my-2 border-white/10">
                        <a href="/logout/" class="flex items-center gap-2 px-4 py-2 text-sm text-red-400 hover:bg-red-500/10 hover:text-red-300">
                            <i class="fas fa-sign-out-alt w-4"></i> Déconnexion
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <a href="/login/" class="bg-sky-600/10 border border-sky-500/20 text-sky-400 hover:text-white hover:bg-sky-600 px-4 py-2 rounded-full text-xs flex items-center gap-2 transition font-medium whitespace-nowrap">
                    <i class="fas fa-sign-in-alt"></i> <?php echo t('nav.login'); ?>
                </a>
                <a href="/register/" class="bg-sky-600 hover:bg-sky-500 text-white px-4 py-2 rounded-full text-xs flex items-center gap-2 transition font-medium whitespace-nowrap shadow-md shadow-sky-900/20">
                    <i class="fas fa-user-plus"></i> <?php echo t('nav.register'); ?>
                </a>
            <?php endif; ?>
            <a href="/status/" class="bg-emerald-600/20 hover:bg-emerald-600 border border-emerald-500/30 text-emerald-400 hover:text-white px-4 py-2 rounded-full text-xs flex items-center gap-2 transition font-medium shadow-md shadow-emerald-900/20 whitespace-nowrap">
                <i class="fas fa-signal"></i> <?php echo t('status.nav'); ?>
            </a>
            <?php include __DIR__ . '/inc/lang_switcher.php'; ?>
        </div>
        <button onclick="toggleMenu()" class="md:hidden text-2xl text-gray-400 hover:text-white transition shrink-0 ml-auto">
            <i class="fas fa-bars"></i>
        </button>
    </div>
    <div id="mobileMenu" class="md:hidden mt-4 px-4 space-y-3 glass rounded-2xl p-4 hidden">
        <a href="/" class="block py-2 px-4 rounded-xl flex items-center gap-2 text-sm font-medium border <?php echo $active_nav === 'home' ? 'bg-sky-600/20 border-sky-500/40 text-sky-400' : 'bg-white/[0.02] border-white/5 text-gray-300'; ?>">
            <i class="fas fa-home w-5 text-center"></i> <?php echo t('nav.home'); ?>
        </a>
        <a href="/client/servers/" class="block py-2 px-4 rounded-xl flex items-center gap-2 text-sm font-medium border <?php echo $active_nav === 'servers' ? 'bg-slate-600/20 border-slate-500/40 text-slate-300' : 'bg-white/[0.02] border-white/5 text-gray-300'; ?>">
            <i class="fas fa-server w-5 text-center"></i> <?php echo t('nav.servers'); ?>
        </a>
        <a href="/shop/" class="block py-2 px-4 rounded-xl flex items-center gap-2 text-sm font-medium border <?php echo $active_nav === 'offers' ? 'bg-amber-600/20 border-amber-500/40 text-amber-400' : 'bg-white/[0.02] border-white/5 text-gray-300'; ?>">
            <i class="fas fa-tags w-5 text-center"></i> <?php echo t('nav.offers'); ?>
        </a>
        <a href="/shop/cart/" class="block py-2 px-4 rounded-xl flex items-center gap-2 text-sm font-medium border bg-white/[0.02] border-white/5 text-gray-300">
            <i class="fas fa-shopping-cart w-5 text-center"></i> Mon panier
        </a>
        <a href="/support/" class="block py-2 px-4 rounded-xl flex items-center gap-2 text-sm font-medium border <?php echo $active_nav === 'support' ? 'bg-purple-600/20 border-purple-500/40 text-purple-400' : 'bg-white/[0.02] border-white/5 text-gray-300'; ?>">
            <i class="fas fa-headset w-5 text-center"></i> <?php echo t('nav.support'); ?>
        </a>
        <a href="/status/" class="block py-2 px-4 rounded-xl flex items-center gap-2 text-sm font-medium border bg-emerald-600/10 border-emerald-500/30 text-emerald-400">
            <i class="fas fa-signal w-5 text-center"></i> <?php echo t('status.nav'); ?>
        </a>
        <hr class="border-white/10">
        <?php if (isset($_SESSION['user_id'])): ?>
            <a href="/profil/" class="block py-2 px-4 rounded-xl flex items-center gap-2 text-sm font-medium bg-white/[0.02] border border-white/5 text-gray-300">
                <i class="fas fa-user w-5 text-center"></i> Profil
            </a>
            <?php if (!empty($_SESSION['is_admin'])): ?>
                <a href="/admin/" class="block py-2 px-4 rounded-xl flex items-center gap-2 text-sm font-medium bg-amber-600/10 border border-amber-500/30 text-amber-400">
                    <i class="fas fa-shield-halved w-5 text-center"></i> Administration
                </a>
            <?php endif; ?>
            <a href="/logout/" class="block py-2 px-4 rounded-xl flex items-center gap-2 text-sm font-medium bg-red-600/10 border border-red-500/30 text-red-400">
                <i class="fas fa-sign-out-alt w-5 text-center"></i> Déconnexion
            </a>
        <?php else: ?>
            <a href="/login/" class="bg-white/5 border border-white/5 text-gray-300 block py-2 px-4 rounded-xl flex items-center gap-2 text-sm font-medium">
                <i class="fas fa-sign-in-alt w-5 text-center"></i> <?php echo t('nav.login'); ?>
            </a>
            <a href="/register/" class="bg-white/5 border border-white/5 text-gray-300 block py-2 px-4 rounded-xl flex items-center gap-2 text-sm font-medium">
                <i class="fas fa-user-plus w-5 text-center"></i> <?php echo t('nav.register'); ?>
            </a>
        <?php endif; ?>
        <hr class="border-white/10">
        <div class="pt-1">
            <?php include __DIR__ . '/lang_switcher.php'; ?>
        </div>
    </div>
</nav>

<!-- ═══════════════════════════════════════════════════════════════ -->
<!-- CONTENU PRINCIPAL (inchangé) -->
<!-- ═══════════════════════════════════════════════════════════════ -->
<div class="flex-grow flex items-center justify-center px-4 py-4 mb-12">
    <div class="glass p-8 sm:p-10 rounded-2xl w-full max-w-xl text-center border border-white/[0.05] shadow-2xl">
        <div class="w-16 h-16 bg-amber-500/10 border border-amber-500/30 text-amber-400 rounded-full flex items-center justify-center mx-auto mb-6 text-2xl">
            <i class="fas fa-crown"></i>
        </div>
        <h1 class="text-3xl font-black tracking-tight mb-2">Paiement Premium requis</h1>
        <p class="text-gray-500 font-mono text-sm mb-6">
            Commande <span class="text-amber-400 font-bold">#<?= htmlspecialchars($order_id, ENT_QUOTES, 'UTF-8') ?></span>
        </p>
        <?php if (!empty($_SESSION['order_cancelled'])): ?>
        <div class="mb-4 p-3 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-300 text-sm">
            <i class="fas fa-check-circle mr-2"></i> La commande en attente a bien été annulée.
        </div>
        <?php unset($_SESSION['order_cancelled']); ?>
        <?php endif; ?>
        <div class="mb-4 flex justify-end">
            <a href="?plan=<?= urlencode($bundle_param) ?>&cancel=1" class="inline-flex items-center gap-2 rounded-xl border border-red-500/20 bg-red-500/10 px-3 py-2 text-sm text-red-400 transition hover:bg-red-500/20">
                <i class="fas fa-trash"></i> Annuler la commande
            </a>
        </div>
        <div class="bg-white/5 border border-white/[0.05] p-4 rounded-xl text-left mb-4 flex justify-between items-center">
            <div>
                <p class="text-xs text-gray-400 uppercase font-bold tracking-wider">Offres sélectionnées</p>
                <div class="mt-2 space-y-1">
                    <?php foreach ($bundle_items as $bundle_entry): ?>
                    <div class="text-sm text-white">
                        <span class="font-semibold"><?= htmlspecialchars($bundle_entry['product']['name'], ENT_QUOTES, 'UTF-8') ?></span>
                        <?php if ((int)$bundle_entry['quantity'] > 1): ?>
                        <span class="text-gray-400">×<?= (int)$bundle_entry['quantity'] ?></span>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="text-right">
                <p class="text-xs text-gray-400 uppercase font-bold tracking-wider">Total combiné</p>
                <?php if ($promo): ?>
                    <p class="text-sm line-through text-gray-500"><?= number_format($prices['original_price'], 2, '.', '') ?>€</p>
                    <p class="text-xl font-black text-green-400"><?= number_format($final_price, 2, '.', '') ?>€ <span class="text-xs bg-green-500/20 text-green-400 px-2 py-0.5 rounded-full"><?= htmlspecialchars($prices['label'], ENT_QUOTES, 'UTF-8') ?></span></p>
                <?php else: ?>
                    <p class="text-xl font-black text-amber-400"><?= number_format($final_price, 2, '.', '') ?>€</p>
                <?php endif; ?>
            </div>
        </div>
        <?php if ($promo): ?>
        <div class="mb-4 p-3 rounded-xl bg-green-500/10 border border-green-500/20 text-green-400 text-sm flex items-center gap-2">
            <i class="fas fa-tag"></i>
            <span><strong><?= htmlspecialchars($promo['name'], ENT_QUOTES, 'UTF-8') ?></strong> — <?= htmlspecialchars($prices['label'], ENT_QUOTES, 'UTF-8') ?> appliqué !</span>
        </div>
        <?php endif; ?>
        <?php if (count($avail_nodes) > 1): ?>
        <div class="mb-5">
            <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">
                <i class="fas fa-network-wired text-sky-400 mr-1"></i> Choisir votre datacenter
            </label>
            <div class="grid grid-cols-1 gap-2">
                <?php foreach ($avail_nodes as $an): ?>
                <a href="?plan=<?= urlencode($type) ?>&node=<?= $an['id'] ?>"
                   class="flex items-center gap-3 px-4 py-3 rounded-xl border transition <?= $chosen_node_id === (int)$an['id'] ? 'bg-sky-500/10 border-sky-500/40 text-white' : 'bg-white/[0.02] border-white/[0.07] text-gray-400 hover:border-white/20 hover:bg-white/[0.05]' ?>">
                    <div class="w-8 h-8 rounded-lg <?= $chosen_node_id === (int)$an['id'] ? 'bg-sky-500/20' : 'bg-white/5' ?> flex items-center justify-center shrink-0">
                        <i class="fas fa-server text-xs <?= $chosen_node_id === (int)$an['id'] ? 'text-sky-400' : 'text-gray-500' ?>"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="text-sm font-semibold"><?= htmlspecialchars($an['name']) ?></div>
                        <?php if ($an['fqdn']): ?>
                        <div class="text-[11px] text-gray-600 font-mono truncate"><?= htmlspecialchars($an['fqdn']) ?></div>
                        <?php endif; ?>
                    </div>
                    <?php if ($chosen_node_id === (int)$an['id']): ?>
                    <i class="fas fa-check-circle text-sky-400 text-sm shrink-0"></i>
                    <?php endif; ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        <?php if ($promo_error): ?>
        <div class="mb-4 p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-400 text-sm">
            ❌ <?= htmlspecialchars($promo_error, ENT_QUOTES, 'UTF-8') ?>
        </div>
        <?php endif; ?>
        <div class="mb-5">
            <?php if ($applied_promo): ?>
            <div class="flex items-center justify-between gap-2 bg-white/[0.02] border border-white/[0.07] rounded-xl px-4 py-3">
                <span class="text-sm text-gray-300">
                    <i class="fas fa-tag text-green-400 mr-1"></i>
                    Code <strong class="text-white"><?= htmlspecialchars($applied_promo['code'], ENT_QUOTES, 'UTF-8') ?></strong> appliqué
                </span>
                <a href="?plan=<?= urlencode($bundle_param) ?>&clear_promo=1" class="text-xs text-red-400 hover:text-red-300 transition">
                    <i class="fas fa-times"></i> Retirer
                </a>
            </div>
            <?php else: ?>
            <form method="POST" action="?plan=<?= urlencode($bundle_param) ?>" class="flex gap-2">
                <input type="text" name="promo_code" placeholder="Code promo (optionnel)"
                       class="flex-1 bg-white/[0.02] border border-white/[0.07] rounded-xl px-4 py-3 text-sm text-white placeholder-gray-500 focus:outline-none focus:border-sky-500/40 transition">
                <button type="submit"
                        class="bg-sky-600/20 hover:bg-sky-600/40 border border-sky-500/30 text-sky-400 px-5 py-3 rounded-xl text-sm font-semibold transition">
                    Appliquer
                </button>
            </form>
            <?php endif; ?>
        </div>
        <div class="space-y-3">
            <a href="<?= htmlspecialchars($stripe_url, ENT_QUOTES, 'UTF-8') ?>"
               class="flex items-center justify-center gap-3 bg-[#635BFF] hover:bg-[#4F46E5] text-white p-4 rounded-xl font-bold transition shadow-lg transform hover:-translate-y-0.5">
                <i class="fas fa-credit-card text-xl"></i>
                Payer par carte — Stripe (<?= number_format((float)$final_price, 2, '.', '') ?>€)
            </a>
            <div class="flex items-center gap-3 text-gray-600 text-xs">
                <div class="flex-1 h-px bg-white/10"></div>
                <span>ou</span>
                <div class="flex-1 h-px bg-white/10"></div>
            </div>
            <a href="<?= htmlspecialchars($paypalme_url, ENT_QUOTES, 'UTF-8') ?>"
               target="_blank"
               class="flex items-center justify-center gap-3 bg-[#003087] hover:bg-[#001f5a] text-white p-4 rounded-xl font-bold transition shadow-lg transform hover:-translate-y-0.5">
                <i class="fab fa-paypal text-xl"></i>
                Payer par PayPal.me (<?= number_format((float)$final_price, 2, '.', '') ?>€)
            </a>
        </div>
        <div class="mt-4 p-3 rounded-xl bg-blue-500/5 border border-blue-500/10 text-xs text-gray-400 text-left flex gap-2">
            <i class="fas fa-circle-info text-blue-400 mt-0.5 shrink-0"></i>
            <span>Pour le paiement PayPal.me, indiquez votre email de commande <strong class="text-white"><?= htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') ?></strong> en note, et votre serveur sera activé manuellement sous 24h.</span>
        </div>
        <div class="mt-6 pt-5 border-t border-white/[0.05] flex items-center justify-center gap-2 text-xs text-gray-400 bg-amber-500/5 p-3 rounded-xl border border-amber-500/10">
            <i class="fas fa-circle-info text-amber-400"></i>
            <span>Stripe active automatiquement votre instance. PayPal.me nécessite une validation manuelle sous 24h.</span>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════ -->
<!-- FOOTER (inchangé) -->
<!-- ═══════════════════════════════════════════════════════════════ -->
<footer class="w-full bg-[#05070d] text-gray-400 py-12 px-6 border-t border-white/5 font-sans">
    <div class="max-w-7xl mx-auto">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-8 mb-10">
            <div class="flex flex-col gap-4">
                <h3 class="text-white font-bold text-base tracking-wide"><?php echo t('footer.nav'); ?></h3>
                <div class="flex flex-col gap-2.5 text-sm">
                    <a href="/" class="hover:text-sky-400 transition"><?php echo t('nav.home'); ?></a>
                    <a href="/client/servers/" class="hover:text-sky-400 transition"><?php echo t('nav.servers'); ?></a>
                    <a href="/shop/" class="hover:text-sky-400 transition"><?php echo t('nav.offers'); ?></a>
                    <a href="/support/" class="hover:text-sky-400 transition"><?php echo t('nav.support'); ?></a>
                </div>
            </div>
            <div class="flex flex-col gap-4">
                <h3 class="text-white font-bold text-base tracking-wide"><?php echo t('footer.network'); ?></h3>
                <div class="flex flex-col gap-2.5 text-sm">
                    <a href="/discord/" class="hover:text-sky-400 transition"><?php echo t('footer.discord'); ?></a>
                    <a href="https://status.deepstone.fr/" class="hover:text-sky-400 transition"><?php echo t('footer.status'); ?></a>
                </div>
            </div>
            <div class="flex flex-col gap-4">
                <h3 class="text-white font-bold text-base tracking-wide"><?php echo t('footer.links'); ?></h3>
                <div class="flex flex-col gap-2.5 text-sm">
                    <a href="https://php.orinstone.deepstone.fr" class="hover:text-sky-400 transition"><?php echo t('nav.phpmyadmin'); ?></a>
                    <a href="https://panel.orinstone.deepstone.fr" class="hover:text-sky-400 transition"><?php echo t('nav.panel'); ?></a>
                </div>
            </div>
            <div class="flex flex-col justify-end gap-3 items-start md:items-end">
                <span class="text-xs text-gray-400 font-semibold tracking-wider uppercase">
                    <?php echo t('footer.payments'); ?>
                </span>
                <div class="flex flex-wrap items-center gap-3 bg-white/[0.02] border border-white/5 p-3 rounded-xl">
                    <img src="https://azurhosts.com/assets/images/logos/psrl/card-icons/card_cb.svg" alt="CB" class="h-8 object-contain" />
                    <img src="https://azurhosts.com/assets/images/logos/psrl/card-icons/card_visa.svg" alt="Visa" class="h-8 object-contain" />
                    <img src="https://azurhosts.com/assets/images/logos/psrl/card-icons/card_mastercard.svg" alt="Mastercard" class="h-8 object-contain" />
                    <img src="https://azurhosts.com/assets/images/logos/psrl/card-icons/card_paypal.svg" alt="PayPal" class="h-8 object-contain" />
                    <img src="https://heberge.orinstone.deepstone.fr/img/moyen_pay/google_pay.png" alt="Google Pay" class="h-8 object-contain" />
                    <img src="https://heberge.orinstone.deepstone.fr/img/moyen_pay/revolut_pay.png" alt="Revolut Pay" class="h-8 object-contain" />
                    <img src="https://heberge.orinstone.deepstone.fr/img/moyen_pay/apple_pay.png" alt="Apple Pay" class="h-8 object-contain" />
                </div>
            </div>
        </div>
        <hr class="border-white/10 mb-8">
        <div class="flex flex-col md:flex-row items-start justify-between gap-6 text-xs text-gray-500">
            <div class="flex items-center gap-2">
                <span class="text-2xl font-black tracking-tighter text-white">Orin<span class="text-sky-500">Heberge</span></span>
            </div>
            <div class="flex flex-col gap-2 md:text-left">
                <div class="flex flex-wrap gap-x-4 gap-y-1 text-gray-400 font-medium">
                    <a href="/mentions-legales/" class="hover:text-sky-400 transition"><?php echo t('footer.legal'); ?></a>
                    <span class="text-white/10">|</span>
                    <a href="/cgu/" class="hover:text-sky-400 transition"><?php echo t('footer.cgu'); ?></a>
                    <span class="text-white/10">|</span>
                    <a href="/politique-confidentialite/" class="hover:text-sky-400 transition"><?php echo t('footer.privacy'); ?></a>
                </div>
                <div class="flex flex-col gap-0.5">
                    <div><?php echo t('footer.copyright'); ?></div>
                    <div class="text-[10px] text-gray-600 mt-1">
                        <?php echo t('footer.powered'); ?> <span class="text-sky-500/70 font-medium hover:text-sky-400 transition">Orinstone Studio</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</footer>
</body>
</html>
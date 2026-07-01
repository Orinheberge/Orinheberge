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
require_once __DIR__ . '/lib/stripe/stripe.php';
require_once __DIR__ . '/lib/paypal/paypal.php';
require_once __DIR__ . '/lib/promo/promo.php';
require_once __DIR__ . '/webhook/discord.php';

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

    unset($_SESSION['current_pending_order_id'], $_SESSION['checkout_bundle']);
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

foreach ($selected_slugs as $slug) {
    $product = getProductBySlug($pdo, $slug);
    if (!$product || (string)($product['type'] ?? '') !== 'paid') {
        continue;
    }

    $quantity = 1;
    if (!empty($_SESSION['checkout_bundle']['items']) && is_array($_SESSION['checkout_bundle']['items'])) {
        foreach ($_SESSION['checkout_bundle']['items'] as $entry) {
            if (strtolower((string)($entry['slug'] ?? '')) === strtolower($slug)) {
                $quantity = max(1, (int)($entry['quantity'] ?? 1));
                break;
            }
        }
    }

    $bundle_items[] = ['product' => $product, 'quantity' => $quantity];
    $bundle_total += (float)($product['price'] ?? 0) * $quantity;
}

if (empty($bundle_items)) {
    header('Location: /shop/cart/');
    exit();
}

$offer = $bundle_items[0]['product'];
$bundle_param = implode($selected_slugs, ',');
$bundle_label = count($bundle_items) > 1 ? implode(array_map(static function ($entry) {
    return $entry['product']['name'];
}, $bundle_items), ' + ') : $offer['name'];
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

// Si pas de pivot, fallback sur node_id du produit
if (empty($avail_nodes)) {
    $fn = $pdo->prepare("SELECT id, name, fqdn, location_id FROM nodes WHERE id=? AND is_active=1");
    $fn->execute([$offer['node_id']]);
    $avail_nodes = array_filter([$fn->fetch()]);
}

// Node choisi par le client (via POST ou GET)
$chosen_node_id = (int)($_POST['chosen_node_id'] ?? $_GET['node'] ?? ($avail_nodes[0]['id'] ?? $offer['node_id']));
// Valider que le node choisi est bien autorisé
$valid_node_ids = array_column($avail_nodes, 'id');
if (!in_array($chosen_node_id, $valid_node_ids)) {
    $chosen_node_id = $avail_nodes[0]['id'] ?? $offer['node_id'];
}
// Charger les infos du node choisi pour createPanelServer
$cn_stmt = $pdo->prepare("SELECT * FROM nodes WHERE id=?");
$cn_stmt->execute([$chosen_node_id]);
$chosen_node = $cn_stmt->fetch();
// Surcharger offer avec le node choisi
if ($chosen_node) {
    $offer['location_id']  = $chosen_node['location_id'];
    $offer['panel_node_id'] = $chosen_node['panel_node_id'] ?? $offer['panel_node_id'];
}

$check = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id=? AND service_name=?");
$check->execute([$_SESSION['user_id'], $offer['name']]);
if ($check->fetchColumn() >= 5) die("❌ Limite de 5 serveurs atteinte.");

// Nettoyage de la promotion en session si demandé
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
    // Nettoyage agressif des espaces invisibles et insécables
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

    $panelUser = getOrCreatePanelUser($panel_url, $headers_admin, $user, $pdo);
    $pass      = $panelUser['pass'];
    if ($pass) $_SESSION['panel_password'] = $pass;

    $srv      = createPanelServer($panel_url, $headers_admin, $offer, $panelUser['id']);
    $order_id = strtoupper(substr(md5(uniqid('', true)), 0, 8));
    $next_pay = date("Y-m-01", strtotime("+1 month"));

    // Validation et passage du statut en 'paid'
    $pdo->prepare("
        INSERT INTO orders (user_id, order_id, service_name, ram, disk, cpu,
            server_id, uuid, id_server_panel, status, paypal_order_id,
            renewal_price, next_payment_date, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'paid', ?, ?, ?, NOW())
    ")->execute([
        $_SESSION['user_id'], $order_id, $bundle_label,
        $offer['ram'], $offer['disk'], $offer['cpu'],
        $srv['id'], $srv['uuid'], $srv['identifier'],
        $_GET['session_id'], $final_price, $next_pay
    ]);

    // ── Générer la facture ────────────────────────────────────────────────────
    $invoice_count = (int)$pdo->query("SELECT COUNT(*)+1 FROM invoices")->fetchColumn();
    $invoice_id    = 'INV-' . date('Y') . '-' . str_pad($invoice_count, 5, '0', STR_PAD_LEFT);
    $pdo->prepare("
        INSERT INTO invoices (invoice_id, user_id, order_id, service_name, amount, type,
            status, payment_method, payment_ref, paid_at, created_at)
        VALUES (?, ?, ?, ?, ?, 'purchase', 'paid', 'stripe', ?, NOW(), NOW())
    ")->execute([
        $invoice_id, $_SESSION['user_id'], $order_id, $bundle_label,
        $final_price, $_GET['session_id']
    ]);

    sendDiscordWebhook(
        $discord_webhook_url, $order_id, $bundle_label,
        $final_price, $user['email'], $srv['uuid'], $srv['identifier']
    );

    // ── Email de confirmation ─────────────────────────────────────────────────
    require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/smtp.php';
    $username_display = !empty($user['pseudo']) ? $user['pseudo'] : $user['firstname'];
    send_order_confirmation_email(
        $pdo, $user['email'], $username_display,
        $order_id, $bundle_label, (float)$final_price,
        $srv['identifier'], $pass ?? null, $panel_url
    );

    $_SESSION['success_order_id']       = $order_id;
    $_SESSION['success_email']          = $user['email'];
    $_SESSION['success_server_id']      = $srv['id'];
    $_SESSION['success_offer']          = $bundle_label;
    $_SESSION['success_panel_password'] = $pass ?? ($user['panel_password'] ?? null);

    // Nettoyage de la promo après un achat réussi
    unset($_SESSION['promo_code']);

    header("Location: /shop/order/success/");
    exit();
}

/*
|--------------------------------------------------------------------------
| SUIVI ET GESTION DE LA COMMANDE EN ATTENTE (PENDING)
|--------------------------------------------------------------------------
*/

// On vérifie si une session de commande pending existe pour éviter l'engorgement de la bdd
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
} else {
    $order_id = $_SESSION['current_pending_order_id'];
    
    // Mise à jour optionnelle du prix de renouvellement si le prix ou la promo change
    $pdo->prepare("UPDATE orders SET renewal_price = ?, service_name = ? WHERE order_id = ? AND status = 'pending'")
        ->execute([$final_price, $bundle_label, $order_id]);
}

$checkout_offer = array_merge($offer, [
    'name' => $bundle_label,
    'price' => $final_price,
    'slug' => $bundle_param ?: $type,
]);

$stripe_session = createStripeSession(
    $stripe_secret_key,
    $checkout_offer,
    $bundle_param ?: $type,
    "https://heberge.orinstone.deepstone.fr/shop/order/?plan=" . urlencode($bundle_param ?: $type) . "&session_id={CHECKOUT_SESSION_ID}",
    "https://heberge.orinstone.deepstone.fr/shop/"
);
$stripe_url = $stripe_session['checkout_url'];
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

    <nav class="sticky top-0 z-50 glass p-5 border-b border-white/5">
        <div class="max-w-7xl mx-auto flex justify-between items-center gap-4">
            
            <h1 class="text-3xl font-black gradient-text tracking-tight shrink-0">
                <a href="/">OrinHeberge</a>
            </h1>

            <div class="hidden md:flex items-center gap-3 whitespace-nowrap">
                <a href="/" class="bg-sky-600/20 hover:bg-sky-600 border border-sky-500/30 text-sky-400 hover:text-white px-4 py-2 rounded-full text-xs flex items-center gap-2 transition font-medium shadow-md shadow-sky-900/20">
                    <i class="fas fa-home"></i> Accueil
                </a>
                <a href="/client/servers/" class="bg-slate-600/20 hover:bg-slate-600 border border-slate-500/30 text-slate-400 hover:text-white px-4 py-2 rounded-full text-xs flex items-center gap-2 transition font-medium shadow-md shadow-slate-900/20">
                    <i class="fas fa-server"></i> Mes serveurs
                </a>
                <a href="/shop/" class="bg-amber-600/20 hover:bg-amber-600 border border-amber-500/30 text-amber-400 hover:text-white px-4 py-2 rounded-full text-xs flex items-center gap-2 transition font-medium shadow-md shadow-amber-900/20">
                    <i class="fas fa-tags"></i> Offres
                </a>
                <a href="/support/" class="bg-purple-600/20 hover:bg-purple-600 border border-purple-500/30 text-purple-400 hover:text-white px-4 py-2 rounded-full text-xs flex items-center gap-2 transition font-medium shadow-md shadow-purple-900/20">
                    <i class="fas fa-headset"></i> Support
                </a>

                <?php if(isset($_SESSION['user_id'])): ?>
                    <?php include $_SERVER['DOCUMENT_ROOT'] . '/inc/notifications.php'; ?>
                    
                    <a href="/profil/" class="text-gray-300 hover:text-sky-400 font-bold flex items-center gap-2.5 transition bg-white/5 hover:bg-white/10 px-4 py-2 rounded-full border border-white/5 focus:outline-none text-xs">
                        <?php if(!empty($_SESSION['avatar']) && file_exists($_SERVER['DOCUMENT_ROOT'] . '/' . $_SESSION['avatar'])): ?>
                            <img src="/<?php echo htmlspecialchars($_SESSION['avatar']); ?>" alt="Avatar" class="w-5 h-5 rounded-full object-cover border border-sky-500/30 shrink-0">
                        <?php else: ?>
                            <i class="fas fa-user-circle text-lg text-sky-400 shrink-0 flex items-center justify-center"></i>
                        <?php endif; ?>
                        <span class="block"><?php echo htmlspecialchars($_SESSION['username'] ?? 'Mon Profil'); ?></span>
                    </a>

                    <a href="/logout/" class="bg-red-600/10 hover:bg-red-600 border border-red-500/20 text-red-400 hover:text-white px-4 py-2 rounded-full text-xs flex items-center gap-2 transition font-medium">
                        <i class="fas fa-sign-out-alt"></i> Déconnexion
                    </a>
                <?php else: ?>
                    <a href="/login/" class="bg-slate-600/20 hover:bg-slate-600 border border-slate-500/30 text-slate-400 hover:text-white px-4 py-2 rounded-full text-xs flex items-center gap-2 transition font-medium">
                        <i class="fas fa-sign-in-alt"></i> Connexion
                    </a>
                    <a href="/register/" class="bg-slate-600/20 hover:bg-slate-600 border border-slate-500/30 text-slate-400 hover:text-white px-4 py-2 rounded-full text-xs flex items-center gap-2 transition font-medium">
                        <i class="fas fa-user-plus"></i> Inscription
                    </a>
                <?php endif; ?>
            </div>

            <div class="hidden lg:flex gap-2.5 items-center shrink-0">
                <a href="/status/" class="bg-emerald-600/20 hover:bg-emerald-600 border border-emerald-500/30 text-emerald-400 hover:text-white px-4 py-2 rounded-full text-xs flex items-center gap-2 transition font-medium shadow-md shadow-emerald-900/20 whitespace-nowrap">
                    <i class="fas fa-signal"></i> Statut
                </a>

                <a href="https://php.orinstone.deepstone.fr" class="glass text-gray-300 hover:text-white hover:bg-white/10 px-4 py-2 rounded-full text-xs flex items-center gap-2 transition font-medium border border-white/5 whitespace-nowrap">
                    <i class="fas fa-database text-sky-400"></i> phpMyAdmin
                </a>
                
                <a href="https://panel.orinstone.deepstone.fr" class="bg-sky-600 hover:bg-sky-500 px-4 py-2 rounded-full text-xs flex items-center gap-2 transition font-medium shadow-md shadow-sky-900/20 whitespace-nowrap text-white">
                    <i class="fas fa-cogs"></i> Panel
                </a>

                <div class="relative inline-block text-left group">
                    <button type="button" class="inline-flex items-center gap-2 bg-white/5 border border-white/10 hover:border-sky-500/50 rounded-full px-3 py-1.5 text-xs font-semibold text-gray-200 transition focus:outline-none">
                        <img src="https://flagcdn.com/w20/fr.png" id="current-flag" alt="Français" class="w-4 h-auto rounded-sm object-contain">
                        <span id="current-lang-text">FR</span>
                        <i class="fas fa-chevron-down text-[10px] text-gray-400 group-hover:text-sky-400 transition duration-200"></i>
                    </button>
                    <div class="absolute right-0 mt-2 w-36 rounded-xl glass border border-white/10 shadow-xl opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-50 overflow-hidden">
                        <div class="py-1">
                            <a href="?lang=fr" onclick="changeLanguage('fr', 'FR', 'https://flagcdn.com/w20/fr.png', event)" class="flex items-center gap-3 px-4 py-2 text-xs text-gray-300 hover:bg-sky-600/20 hover:text-white transition">
                                <img src="https://flagcdn.com/w20/fr.png" alt="Français" class="w-4 h-auto rounded-sm">
                                <span>Français</span>
                            </a>
                            <a href="?lang=en" onclick="changeLanguage('en', 'EN', 'https://flagcdn.com/w20/gb.png', event)" class="flex items-center gap-3 px-4 py-2 text-xs text-gray-300 hover:bg-sky-600/20 hover:text-white transition">
                                <img src="https://flagcdn.com/w20/gb.png" alt="English" class="w-4 h-auto rounded-sm">
                                <span>English</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <button onclick="toggleMenu()" class="md:hidden text-2xl text-gray-400 hover:text-white transition shrink-0">
                <i class="fas fa-bars"></i>
            </button>
        </div>

        <div id="mobileMenu" class="md:hidden mt-4 px-4 space-y-3 glass rounded-2xl p-4 hidden">
            <a href="/" class="bg-sky-600/20 border border-sky-500/30 text-sky-400 block py-2 px-4 rounded-xl flex items-center gap-2 text-sm font-medium"><i class="fas fa-home w-5 text-center"></i> Accueil</a>
            <a href="/client/servers/" class="bg-white/[0.02] border border-white/5 text-gray-300 block py-2 px-4 rounded-xl flex items-center gap-2 text-sm font-medium"><i class="fas fa-server w-5 text-center"></i> Mes serveurs</a>
            <a href="/shop/" class="bg-white/[0.02] border border-white/5 text-gray-300 block py-2 px-4 rounded-xl flex items-center gap-2 text-sm font-medium"><i class="fas fa-tags w-5 text-center"></i> Offres</a>
            <a href="/support/" class="bg-white/[0.02] border border-white/5 text-gray-300 block py-2 px-4 rounded-xl flex items-center gap-2 text-sm font-medium"><i class="fas fa-headset w-5 text-center"></i> Support</a>
            <a href="/status/" class="bg-emerald-600/20 border border-emerald-500/30 text-emerald-400 block py-2 px-4 rounded-xl flex items-center gap-2 text-sm font-medium"><i class="fas fa-signal w-5 text-center"></i> Statut</a>
            
            <hr class="border-white/10">

            <?php if(isset($_SESSION['user_id'])): ?>
                <a href="/profil/" class="bg-white/5 text-gray-200 block py-2 px-4 rounded-xl flex items-center gap-2.5 text-sm font-bold border border-white/5">
                    <?php if(!empty($_SESSION['avatar']) && file_exists($_SERVER['DOCUMENT_ROOT'] . '/' . $_SESSION['avatar'])): ?>
                        <img src="/<?php echo htmlspecialchars($_SESSION['avatar']); ?>" alt="Avatar" class="w-5 h-5 rounded-full object-cover border border-sky-500/30 shrink-0">
                    <?php else: ?>
                        <i class="fas fa-user-circle text-lg text-sky-400 shrink-0"></i>
                    <?php endif; ?>
                    <span><?php echo htmlspecialchars($_SESSION['username'] ?? 'Mon Profil'); ?></span>
                </a>
                <a href="/logout/" class="bg-red-600/10 border border-red-500/20 text-red-400 block py-2 px-4 rounded-xl flex items-center gap-2 text-sm font-medium">
                    <i class="fas fa-sign-out-alt w-5 text-center"></i> Déconnexion
                </a>
            <?php else: ?>
                <a href="/login/" class="bg-white/5 text-gray-300 block py-2 px-4 rounded-xl flex items-center gap-2 text-sm font-medium border border-white/5"><i class="fas fa-sign-in-alt w-5 text-center"></i> Connexion</a>
                <a href="/register/" class="bg-white/5 text-gray-300 block py-2 px-4 rounded-xl flex items-center gap-2 text-sm font-medium border border-white/5"><i class="fas fa-user-plus w-5 text-center"></i> Inscription</a>
            <?php endif; ?>

            <hr class="border-white/10">

            <div class="grid grid-cols-2 gap-2 pt-1">
                <a href="https://php.orinstone.deepstone.fr" class="glass text-gray-300 px-4 py-2.5 rounded-xl text-xs flex items-center gap-2 justify-center border border-white/5 font-medium">
                    <i class="fas fa-database text-sky-400"></i> phpMyAdmin
                </a>
                <a href="https://panel.orinstone.deepstone.fr" class="bg-sky-600 text-white px-4 py-2.5 rounded-xl text-xs flex items-center gap-2 justify-center font-medium">
                    <i class="fas fa-cogs"></i> Panel
                </a>
            </div>

            <div class="relative inline-block text-left group w-full pt-1">
                <button type="button" class="inline-flex items-center justify-between w-full gap-2 bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-sm font-semibold text-gray-200 transition focus:outline-none">
                    <div class="flex items-center gap-2">
                        <img src="https://flagcdn.com/w20/fr.png" alt="Français" class="w-5 h-auto rounded-sm object-contain">
                        <span>FR</span>
                    </div>
                    <i class="fas fa-chevron-down text-xs text-gray-400 group-hover:text-sky-400 transition duration-200"></i>
                </button>
                <div class="absolute right-0 mt-2 w-full rounded-xl glass border border-white/10 shadow-xl opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-50 overflow-hidden">
                    <div class="py-1">
                        <a href="?lang=fr" onclick="changeLanguage('fr', 'FR', 'https://flagcdn.com/w20/fr.png', event)" class="flex items-center gap-3 px-4 py-2.5 text-sm text-gray-300 hover:bg-sky-600/20 hover:text-white transition">
                            <img src="https://flagcdn.com/w20/fr.png" alt="Français" class="w-5 h-auto rounded-sm">
                            <span>Français</span>
                        </a>
                        <a href="?lang=en" onclick="changeLanguage('en', 'EN', 'https://flagcdn.com/w20/gb.png', event)" class="flex items-center gap-3 px-4 py-2.5 text-sm text-gray-300 hover:bg-sky-600/20 hover:text-white transition">
                            <img src="https://flagcdn.com/w20/gb.png" alt="English" class="w-5 h-auto rounded-sm">
                            <span>English</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </nav>

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
            <!-- Sélecteur de node -->
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

            <form method="POST" action="?plan=<?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8') ?>" class="flex gap-2 mb-6">
                <input type="text" name="promo_code"
                    placeholder="Code promo (ex: ETE2026)"
                    value="<?= htmlspecialchars($_SESSION['promo_code'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                    class="flex-1 bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-sm text-white placeholder-gray-500 focus:outline-none focus:border-sky-500 transition">
                <button type="submit"
                    class="bg-sky-600 hover:bg-sky-500 text-white px-4 py-2.5 rounded-xl text-sm font-bold transition whitespace-nowrap">
                    Appliquer
                </button>
                <?php if (isset($_SESSION['promo_code'])): ?>
                <a href="?plan=<?= htmlspecialchars($type, ENT_QUOTES, 'UTF-8') ?>&clear_promo=1"
                   class="bg-red-600/20 hover:bg-red-600 border border-red-500/30 text-red-400 hover:text-white px-3 py-2.5 rounded-xl text-sm transition">
                    <i class="fas fa-times"></i>
                </a>
                <?php endif; ?>
            </form>

            <?php if ($promo_error): ?>
            <div class="mb-4 p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-400 text-sm">
                ❌ <?= htmlspecialchars($promo_error, ENT_QUOTES, 'UTF-8') ?>
            </div>
            <?php endif; ?>

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

    <footer class="w-full bg-[#05070d] text-gray-400 py-12 px-6 border-t border-white/5 font-sans">
    <div class="max-w-7xl mx-auto">
        
        <div class="grid grid-cols-1 md:grid-cols-4 gap-8 mb-10">
            
            <div class="flex flex-col gap-4">
                <h3 class="text-white font-bold text-base tracking-wide">Navigation</h3>
                <div class="flex flex-col gap-2.5 text-sm">
                    <a href="/" class="hover:text-sky-400 transition">Accueil</a>
                    <a href="/client/servers/" class="hover:text-sky-400 transition">Mes serveurs</a>
                    <a href="/shop/" class="hover:text-sky-400 transition">Offres</a>
                    <a href="/support/" class="hover:text-sky-400 transition">Support</a>
                </div>
            </div>

            <div class="flex flex-col gap-4">
                <h3 class="text-white font-bold text-base tracking-wide">Notre Réseau</h3>
                <div class="flex flex-col gap-2.5 text-sm">
                    <a href="/discord/" class="hover:text-sky-400 transition">Notre Discord</a>
                    <a href="https://status.deepstone.fr/" class="hover:text-sky-400 transition">Statut des Services</a>
                </div>
            </div>

            <div class="flex flex-col gap-4">
                <h3 class="text-white font-bold text-base tracking-wide">Liens Utiles</h3>
                <div class="flex flex-col gap-2.5 text-sm">
                    <a href="https://php.orinstone.deepstone.fr" class="hover:text-sky-400 transition">phpMyAdmin</a>
                    <a href="https://panel.orinstone.deepstone.fr" class="hover:text-sky-400 transition">Panel</a>
                </div>
            </div>

            <div class="flex flex-col justify-end gap-3 items-start md:items-end">
                <span class="text-xs text-gray-400 font-semibold tracking-wider uppercase">Moyens de Paiements Acceptés</span>
                <div class="flex items-center gap-3 bg-white/[0.02] border border-white/5 p-3 rounded-xl">
                    <img src="https://azurhosts.com/assets/images/logos/psrl/card-icons/card_cb.svg" alt="CB" class="h-8 object-contain" />
                    <img src="https://azurhosts.com/assets/images/logos/psrl/card-icons/card_visa.svg" alt="Visa" class="h-8 object-contain" />
                    <img src="https://azurhosts.com/assets/images/logos/psrl/card-icons/card_mastercard.svg" alt="Mastercard" class="h-8 object-contain" />
                    <img src="https://azurhosts.com/assets/images/logos/psrl/card-icons/card_paypal.svg" alt="PayPal" class="h-8 object-contain" />
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
                    <a href="/mentions-legales/" class="hover:text-sky-400 transition">Mentions Légales</a>
                    <span class="text-white/10">|</span>
                    <a href="/cgu/" class="hover:text-sky-400 transition">Conditions Générales d'Utilisation</a>
                    <span class="text-white/10">|</span>
                    <a href="/politique-confidentialite/" class="hover:text-sky-400 transition">Politique de Confidentialité</a>
                </div>
                <div class="flex flex-col gap-0.5">
                    <div>© 2026-2029 OrinHeberge — Infrastructure OrinStone. Tous droits réservés.</div>
                    <div class="text-[10px] text-gray-600 mt-1">
                        Propulsé par <span class="text-sky-500/70 font-medium hover:text-sky-400 transition">Orinstone Studio</span>
                    </div>
                </div>
            </div>

        </div>
    </div>
</footer>
</body>
</html>
<?php
/**
 * OrinHeberge — Checkout / Préparation paiement
 * Gère : bundles, offres gratuites, codes promo, redirection Stripe
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);
session_start();

// ═══════════════════════════════════════════
// 1. VÉRIFICATION AUTHENTIFICATION
// ═══════════════════════════════════════════
if (!isset($_SESSION['user_id'])) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    header("Location: /login/");
    exit();
}

// ═══════════════════════════════════════════
// 2. CHARGEMENT DES DÉPENDANCES
// ═══════════════════════════════════════════
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/db.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/lang.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/api/billing/Facture.php';
require_once __DIR__ . '/lib/stripe/stripe.php';
require_once __DIR__ . '/lib/paypal/paypal.php';
require_once __DIR__ . '/lib/promo/promo.php';
require_once __DIR__ . '/webhook/discord.php';

// ═══════════════════════════════════════════
// 3. CONFIGURATION EXTENSIONS
// ═══════════════════════════════════════════
try {
    $ext_settings_raw = $pdo->query("
        SELECT e.slug, es.key, es.value
        FROM extension_settings es
        JOIN extensions e ON e.id = es.extension_id
    ")->fetchAll();
    
    $ext_cfg = [];
    foreach ($ext_settings_raw as $r) {
        $ext_cfg[$r['slug']][$r['key']] = $r['value'];
    }
    
    $stripe_secret_key   = $ext_cfg['stripe']['secret_key'] ?? '';
    $stripe_public_key   = $ext_cfg['stripe']['public_key'] ?? '';
    $paypalme_username   = $ext_cfg['paypal']['username']   ?? 'metal544002009';
    $discord_webhook_url = $ext_cfg['discord']['webhook_url'] ?? '';
    
} catch (Exception $e) {
    error_log('[Checkout] Config error: ' . $e->getMessage());
    die("Erreur de configuration. Contactez le support.");
}

// ═══════════════════════════════════════════
// 4. ANNULATION DE COMMANDE
// ═══════════════════════════════════════════
if (isset($_GET['cancel']) && in_array($_GET['cancel'], ['1', 'true'])) {
    try {
        $pdo->beginTransaction();
        
        // Supprimer commande pending
        if (!empty($_SESSION['current_pending_order_id'])) {
            $pdo->prepare("DELETE FROM orders WHERE order_id = ? AND user_id = ? AND status = 'pending' LIMIT 1")
                ->execute([$_SESSION['current_pending_order_id'], $_SESSION['user_id']]);
        }
        
        // Supprimer facture pending
        if (!empty($_SESSION['current_pending_invoice_id'])) {
            $pdo->prepare("DELETE FROM invoices WHERE invoice_id = ? AND user_id = ? AND status = 'pending' LIMIT 1")
                ->execute([$_SESSION['current_pending_invoice_id'], $_SESSION['user_id']]);
        }
        
        $pdo->commit();
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('[Checkout] Cancel error: ' . $e->getMessage());
    }
    
    unset(
        $_SESSION['current_pending_order_id'],
        $_SESSION['current_pending_invoice_id'],
        $_SESSION['checkout_bundle']
    );
    
    $_SESSION['flash_message'] = [
        'type' => 'success',
        'text' => '✅ Commande annulée avec succès.'
    ];
    
    header('Location: /shop/cart/');
    exit();
}

// ═══════════════════════════════════════════
// 5. RÉCUPÉRATION DU BUNDLE / PRODUIT
// ═══════════════════════════════════════════
$bundle_items   = [];
$bundle_total   = 0.0;
$bundle_param   = '';
$bundle_label   = '';
$selected_slugs = [];

// Parser les slugs depuis l'URL
$plan_param = trim($_GET['plan'] ?? $_GET['type'] ?? '');
if ($plan_param !== '') {
    $raw_parts = array_filter(array_map('trim', explode(',', $plan_param)), 'strlen');
    foreach ($raw_parts as $raw_slug) {
        $selected_slugs[] = strtolower($raw_slug);
    }
}

// Fallback : utiliser la session
if (empty($selected_slugs) && !empty($_SESSION['checkout_bundle']['items']) && is_array($_SESSION['checkout_bundle']['items'])) {
    foreach ($_SESSION['checkout_bundle']['items'] as $entry) {
        $slug = trim((string)($entry['slug'] ?? ''));
        if ($slug !== '') {
            $selected_slugs[] = strtolower($slug);
        }
    }
}

// Rediriger si aucun produit
if (empty($selected_slugs)) {
    $_SESSION['flash_message'] = [
        'type' => 'error',
        'text' => '❌ Aucun produit sélectionné.'
    ];
    header('Location: /shop/cart/');
    exit();
}

// Helper : trouver la quantité d'un slug
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

// Séparer offres gratuites et payantes
$free_bundle_items = [];

foreach ($selected_slugs as $slug) {
    $product = getProductBySlug($pdo, $slug);
    if (!$product) continue;
    
    $quantity = findSlugQuantity($slug);
    
    if ((string)($product['type'] ?? '') === 'free') {
        $free_bundle_items[] = ['product' => $product, 'quantity' => $quantity];
    } else {
        $bundle_items[] = ['product' => $product, 'quantity' => $quantity];
        $bundle_total += (float)($product['price'] ?? 0) * $quantity;
    }
}

// ═══════════════════════════════════════════
// 6. TRAITEMENT IMMÉDIAT DES OFFRES GRATUITES
// ═══════════════════════════════════════════
if (!empty($free_bundle_items)) {
    $free_key = md5(implode('|', array_map(
        static fn($e) => $e['product']['slug'] . ':' . $e['quantity'],
        $free_bundle_items
    )));
    
    // Éviter le double traitement
    if (($_SESSION['processed_free_bundle_key'] ?? null) !== $free_key) {
        try {
            $stmt = $pdo->prepare('SELECT * FROM users WHERE id=? LIMIT 1');
            $stmt->execute([$_SESSION['user_id']]);
            $free_user = $stmt->fetch();
            
            if ($free_user) {
                require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/smtp.php';
                $free_username_display = !empty($free_user['pseudo']) ? $free_user['pseudo'] : $free_user['firstname'];
                $free_created = [];
                
                foreach ($free_bundle_items as $free_entry) {
                    $free_product = $free_entry['product'];
                    
                    // Vérifier limite de 5 serveurs
                    $limit_check = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE user_id=? AND service_name=?');
                    $limit_check->execute([$_SESSION['user_id'], $free_product['name']]);
                    
                    if ($limit_check->fetchColumn() >= 5) {
                        $_SESSION['checkout_error'] = "❌ Limite de 5 serveurs atteinte pour : " . $free_product['name'];
                        continue;
                    }
                    
                    for ($i = 0; $i < $free_entry['quantity']; $i++) {
                        try {
                            $free_panelUser = getOrCreatePanelUser($panel_url, $headers_admin, $free_user, $pdo);
                            $free_pass      = $free_panelUser['pass'] ?? null;
                            if ($free_pass) $_SESSION['panel_password'] = $free_pass;
                            
                            $free_srv = createPanelServerWithAutoTransfer($panel_url, $headers_admin, $free_product, $free_panelUser['id']);
                            $free_order_id = strtoupper(substr(md5(uniqid('', true)), 0, 8));
                            
                            // Insérer commande
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
                            
                            // Créer facture
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
                            
                            // Email de confirmation
                            send_order_confirmation_email(
                                $pdo, $free_user['email'], $free_username_display,
                                $free_order_id, $free_product['name'], 0.0,
                                $free_srv['identifier'], $free_pass, $panel_url
                            );
                            
                            // Webhook Discord
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
                            
                        } catch (Exception $e) {
                            error_log('[Checkout] Free order error: ' . $e->getMessage());
                            $_SESSION['checkout_error'] = "❌ Erreur lors de la création du serveur gratuit.";
                        }
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
        } catch (Exception $e) {
            error_log('[Checkout] Free bundle error: ' . $e->getMessage());
        }
    }
    
    // Si uniquement des offres gratuites → rediriger vers succès
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

// Vérifier qu'il reste des items payants
if (empty($bundle_items)) {
    $_SESSION['flash_message'] = [
        'type' => 'error',
        'text' => '❌ Aucun produit payant dans le panier.'
    ];
    header('Location: /shop/cart/');
    exit();
}

// ═══════════════════════════════════════════
// 7. PRÉPARATION DES DONNÉES
// ═══════════════════════════════════════════
$offer = $bundle_items[0]['product'];
$bundle_param = implode(',', $selected_slugs);
$bundle_label = count($bundle_items) > 1 
    ? implode(' + ', array_map(static fn($e) => $e['product']['name'], $bundle_items))
    : $offer['name'];
$type = strtolower(trim($offer['slug'] ?? $selected_slugs[0] ?? ''));

// Récupérer utilisateur
$stmt = $pdo->prepare("SELECT * FROM users WHERE id=?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user) {
    die("❌ Utilisateur introuvable. Contactez le support.");
}

// ═══════════════════════════════════════════
// 8. SÉLECTION DU NODE
// ═══════════════════════════════════════════
try {
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
} catch (Exception $e) {
    error_log('[Checkout] Node error: ' . $e->getMessage());
    $avail_nodes = [];
}

$chosen_node_id = (int)($_POST['chosen_node_id'] ?? $_GET['node'] ?? ($avail_nodes[0]['id'] ?? $offer['node_id']));
$valid_node_ids = array_column($avail_nodes, 'id');

if (!in_array($chosen_node_id, $valid_node_ids) && !empty($avail_nodes)) {
    $chosen_node_id = $avail_nodes[0]['id'];
}

$chosen_node = null;
if (!empty($avail_nodes)) {
    $cn_stmt = $pdo->prepare("SELECT * FROM nodes WHERE id=?");
    $cn_stmt->execute([$chosen_node_id]);
    $chosen_node = $cn_stmt->fetch();
}

if ($chosen_node) {
    $offer['location_id']   = $chosen_node['location_id'];
    $offer['panel_node_id'] = $chosen_node['panel_node_id'] ?? $offer['panel_node_id'];
}

// ═══════════════════════════════════════════
// 9. VÉRIFICATION LIMITE DE SERVEURS
// ═══════════════════════════════════════════
$check = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id=? AND service_name=?");
$check->execute([$_SESSION['user_id'], $offer['name']]);

if ($check->fetchColumn() >= 5) {
    $_SESSION['flash_message'] = [
        'type' => 'error',
        'text' => "❌ Limite de 5 serveurs atteinte pour cette offre."
    ];
    header('Location: /shop/cart/');
    exit();
}

// ═══════════════════════════════════════════
// 10. GESTION CODE PROMO
// ═══════════════════════════════════════════
if (isset($_GET['clear_promo'])) {
    unset($_SESSION['promo_code']);
    header("Location: ?plan=" . urlencode($bundle_param ?: $type));
    exit();
}

$promo_context = count($bundle_items) > 1 ? 'cart' : $type;
$active_promo  = getActiveAutoPromo($promos);
$promo_error   = null;
$applied_promo = null;

if (isset($_POST['promo_code']) && !empty($_POST['promo_code'])) {
    $input_code = preg_replace('/\s+/u', '', $_POST['promo_code']);
    $manual = checkPromoCode($promos, $input_code, $promo_context);
    
    if ($manual) {
        $applied_promo = $manual;
        $_SESSION['promo_code'] = $manual['code'];
    } else {
        $promo_error = "❌ Code invalide ou expiré.";
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

// ═══════════════════════════════════════════
// 11. CRÉATION / MISE À JOUR COMMANDE PENDING
// ═══════════════════════════════════════════
$order_id = $_SESSION['current_pending_order_id'] ?? null;

try {
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
} catch (Exception $e) {
    error_log('[Checkout] Order creation error: ' . $e->getMessage());
    $_SESSION['flash_message'] = [
        'type' => 'error',
        'text' => '❌ Erreur lors de la création de la commande.'
    ];
    header('Location: /shop/cart/');
    exit();
}

// ═══════════════════════════════════════════
// 12. REDIRECTION VERS CHECKOUT STRIPE
// ═══════════════════════════════════════════
header("Location: /shop/order/checkout/?order_id=" . urlencode($order_id));
exit();
?>

<!DOCTYPE html>
<html lang="<?php echo $lang ?? 'fr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paiement en cours... | OrinHeberge</title>
    <link rel="icon" type="image/png" href="https://heberge.orinstone.deepstone.fr/favicon.ico">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="manifest" href="/manifest.json">
    
    <style>
        body { 
            background: #070a13; 
            background-image: radial-gradient(at 50% 0%, rgba(56, 189, 248, 0.08) 0, transparent 50%);
            scroll-behavior: smooth; 
        }
        .glass {
            background: rgba(255,255,255,0.03);
            backdrop-filter: blur(14px);
            border: 1px solid rgba(255,255,255,0.06);
        }
        
        /* Animation spinner personnalisée */
        @keyframes spin-slow {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        .spinner-custom {
            animation: spin-slow 2s linear infinite;
        }
        
        /* Animation pulse pour le cercle */
        @keyframes pulse-ring {
            0% { box-shadow: 0 0 0 0 rgba(56, 189, 248, 0.4); }
            70% { box-shadow: 0 0 0 20px rgba(56, 189, 248, 0); }
            100% { box-shadow: 0 0 0 0 rgba(56, 189, 248, 0); }
        }
        .pulse-ring {
            animation: pulse-ring 2s infinite;
        }
        
        /* Barre de progression */
        @keyframes progress {
            0% { width: 0%; }
            50% { width: 70%; }
            100% { width: 100%; }
        }
        .progress-bar {
            animation: progress 3s ease-in-out infinite;
        }
        
        /* Fade in */
        @keyframes fade-in-up {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .fade-in-up {
            animation: fade-in-up 0.5s ease-out;
        }
    </style>
</head>
<body class="text-gray-200 font-sans min-h-screen flex flex-col">

<?php $active_nav = 'order'; include $_SERVER['DOCUMENT_ROOT'] . '/inc/navbar.php'; ?>

<div class="flex-grow flex items-center justify-center px-4 py-8">
    <div class="glass p-8 sm:p-10 rounded-2xl w-full max-w-xl text-center border border-white/[0.05] shadow-2xl fade-in-up">
        
        <!-- Spinner animé -->
        <div class="relative w-20 h-20 mx-auto mb-6">
            <div class="absolute inset-0 rounded-full bg-sky-500/10 border border-sky-500/30 pulse-ring"></div>
            <div class="absolute inset-2 rounded-full bg-sky-500/5 border border-sky-500/20 flex items-center justify-center">
                <i class="fas fa-credit-card text-sky-400 text-2xl spinner-custom"></i>
            </div>
        </div>
        
        <!-- Titre -->
        <h1 class="text-2xl sm:text-3xl font-black tracking-tight mb-2 text-white">
            Préparation du paiement...
        </h1>
        <p class="text-gray-400 text-sm mb-6">
            Commande <span class="text-sky-400 font-bold">#<?= htmlspecialchars($order_id, ENT_QUOTES, 'UTF-8') ?></span>
        </p>
        
        <!-- Barre de progression -->
        <div class="w-full h-1 bg-white/5 rounded-full overflow-hidden mb-6">
            <div class="h-full bg-gradient-to-r from-sky-500 to-sky-400 progress-bar rounded-full"></div>
        </div>
        
        <!-- Message flash -->
        <?php if (!empty($_SESSION['flash_message'])): ?>
        <div class="mb-4 p-3 rounded-xl <?= $_SESSION['flash_message']['type'] === 'success' ? 'bg-green-500/10 border border-green-500/20 text-green-300' : 'bg-red-500/10 border border-red-500/20 text-red-300' ?> text-sm">
            <?= htmlspecialchars($_SESSION['flash_message']['text']) ?>
        </div>
        <?php unset($_SESSION['flash_message']); ?>
        <?php endif; ?>
        
        <?php if (!empty($_SESSION['order_cancelled'])): ?>
        <div class="mb-4 p-3 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-300 text-sm">
            <i class="fas fa-check-circle mr-2"></i> La commande en attente a bien été annulée.
        </div>
        <?php unset($_SESSION['order_cancelled']); ?>
        <?php endif; ?>
        
        <?php if (!empty($promo_error)): ?>
        <div class="mb-4 p-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-300 text-sm">
            <?= htmlspecialchars($promo_error) ?>
        </div>
        <?php endif; ?>
        
        <!-- Récapitulatif -->
        <div class="bg-white/5 border border-white/[0.05] p-5 rounded-xl text-left mb-4">
            <p class="text-xs text-gray-400 uppercase font-bold tracking-wider mb-3 flex items-center gap-2">
                <i class="fas fa-receipt text-sky-400"></i>
                Récapitulatif
            </p>
            
            <?php foreach ($bundle_items as $bundle_entry): ?>
            <div class="flex justify-between items-center py-1.5 text-sm">
                <span class="text-white"><?= htmlspecialchars($bundle_entry['product']['name'], ENT_QUOTES, 'UTF-8') ?></span>
                <div class="flex items-center gap-2">
                    <?php if ((int)$bundle_entry['quantity'] > 1): ?>
                    <span class="text-gray-500 text-xs">×<?= (int)$bundle_entry['quantity'] ?></span>
                    <?php endif; ?>
                    <span class="text-gray-300 font-mono"><?= number_format((float)$bundle_entry['product']['price'] * $bundle_entry['quantity'], 2, ',', '') ?>€</span>
                </div>
            </div>
            <?php endforeach; ?>
            
            <?php if ($promo && $prices['reduction'] > 0): ?>
            <div class="flex justify-between items-center py-1.5 text-sm">
                <span class="text-green-400 flex items-center gap-1">
                    <i class="fas fa-tag text-xs"></i>
                    <?= htmlspecialchars($promo['code'] ?? 'Promo') ?>
                </span>
                <span class="text-green-400 font-mono">-<?= number_format($prices['reduction'], 2, ',', '') ?>€</span>
            </div>
            <?php endif; ?>
            
            <div class="border-t border-white/10 my-3"></div>
            
            <div class="flex justify-between items-center">
                <span class="text-white font-bold">Total</span>
                <span class="text-2xl font-black <?= $promo ? 'text-green-400' : 'text-sky-400' ?>">
                    <?= number_format($final_price, 2, ',', '') ?>€
                </span>
            </div>
        </div>
        
        <!-- Info sécurité -->
        <div class="mt-4 p-3 rounded-xl bg-sky-500/5 border border-sky-500/10 text-xs text-gray-400 text-left flex gap-3">
            <i class="fas fa-shield-halved text-sky-400 mt-0.5 shrink-0 text-base"></i>
            <div>
                <p class="font-semibold text-sky-300 mb-1">Paiement 100% sécurisé</p>
                <p>Redirection automatique vers Stripe Elements. Vos données bancaires ne transitent jamais par nos serveurs.</p>
            </div>
        </div>
        
        <!-- Bouton annuler -->
        <div class="mt-6">
            <a href="/shop/order/?cancel=1" 
               onclick="return confirm('Annuler cette commande ?')"
               class="inline-flex items-center gap-2 text-xs text-gray-500 hover:text-red-400 transition">
                <i class="fas fa-times"></i>
                Annuler la commande
            </a>
        </div>
    </div>
</div>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/inc/footer.php'; ?>
<script src="/inc/navbar.js"></script>

<script>
// Auto-refresh au bout de 10 secondes si bloqué
setTimeout(() => {
    const msg = document.createElement('div');
    msg.className = 'fixed bottom-4 right-4 bg-amber-500/10 border border-amber-500/30 text-amber-300 px-4 py-3 rounded-xl text-sm shadow-lg fade-in-up';
    msg.innerHTML = '<i class="fas fa-clock mr-2"></i> Chargement long ? <a href="javascript:location.reload()" class="underline font-bold">Recharger</a>';
    document.body.appendChild(msg);
}, 10000);
</script>

</body>
</html>
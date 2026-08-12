<?php
/**
 * /client/servers/upgrade/ — Page d'upgrade d'offre pour un serveur existant
 * Le client choisit un plan supérieur, la diff de prix est calculée,
 * puis il est redirigé vers le checkout Stripe pour payer la différence.
 */
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/lang.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/db.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/AuthService.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /login/');
    exit();
}

// ═══════════════════════════════════════════
// 1. RÉCUPÉRATION DES SETTINGS
// ═══════════════════════════════════════════
$cfg = [];
try {
    foreach ($pdo->query('SELECT `key`, `value` FROM settings') as $r) {
        $cfg[$r['key']] = $r['value'];
    }
} catch (Exception $e) {
    $cfg = [];
}

$panel_url     = rtrim($cfg['panel_url'] ?? 'https://panel.orinstone.deepstone.fr', '/');
$api_key_admin = $cfg['api_key_admin'] ?? '';
$headers_admin = [
    "Authorization: Bearer $api_key_admin",
    "Accept: application/vnd.pterodactyl.v1+json",
    "Content-Type: application/json"
];

// ═══════════════════════════════════════════
// 2. UTILISATEUR
// ═══════════════════════════════════════════
try {
    $u = $pdo->prepare('SELECT pseudo, firstname, avatar FROM users WHERE id = ? LIMIT 1');
    $u->execute([$_SESSION['user_id']]);
    $ud = $u->fetch();
    if ($ud) {
        $_SESSION['username'] = !empty($ud['pseudo']) ? $ud['pseudo'] : $ud['firstname'];
        $_SESSION['avatar']   = $ud['avatar'];
    }
} catch (Exception $e) { /* silencieux */ }

// ═══════════════════════════════════════════
// 3. SERVEUR À UPGRADER
// ═══════════════════════════════════════════
$uuid  = trim($_GET['uuid'] ?? '');
$flash = '';

if (!$uuid) {
    header('Location: /client/servers/');
    exit();
}

try {
    $srv_stmt = $pdo->prepare('
        SELECT o.*, p.slug AS product_slug, p.id AS pid 
        FROM orders o 
        LEFT JOIN products p ON p.id = o.product_id 
        WHERE o.uuid = ? AND o.user_id = ? 
        LIMIT 1
    ');
    $srv_stmt->execute([$uuid, $_SESSION['user_id']]);
    $server = $srv_stmt->fetch();
} catch (Exception $e) {
    $server = null;
}

if (!$server) {
    header('Location: /client/servers/');
    exit();
}

// Vérifier que le serveur est actif
if (($server['status'] ?? '') !== 'paid') {
    $flash = '<div class="upgrade-alert upgrade-alert-error"><i class="fas fa-exclamation-triangle"></i> Ce serveur ne peut pas être upgradé (statut : ' . htmlspecialchars($server['status'] ?? 'inconnu') . ').</div>';
}

// ═══════════════════════════════════════════
// 4. CATÉGORIE DU SERVEUR
// ═══════════════════════════════════════════
$cat = 'unknown';
if (!empty($server['pid'])) {
    try {
        $cat_stmt = $pdo->prepare('SELECT category_slug FROM categories_products WHERE product_id = ? AND is_active = 1 LIMIT 1');
        $cat_stmt->execute([$server['pid']]);
        $cat_row = $cat_stmt->fetch();
        if ($cat_row) $cat = $cat_row['category_slug'];
    } catch (Exception $e) { /* silencieux */ }
}

// Fallback : déduction depuis le nom/slug
if ($cat === 'unknown') {
    $name_lower = strtolower($server['service_name'] ?? '');
    $slug_lower = strtolower($server['product_slug'] ?? '');
    foreach (['minecraft','fivem','hytale','php','python','nodejs','java'] as $c) {
        if (str_contains($name_lower, $c) || str_contains($slug_lower, $c)) {
            $cat = $c;
            break;
        }
    }
}

// ═══════════════════════════════════════════
// 5. PRODUITS DISPONIBLES POUR UPGRADE
// ═══════════════════════════════════════════
$available_upgrades = [];
try {
    $prod_stmt = $pdo->prepare("
        SELECT p.*, cp.category_slug
        FROM categories_products cp
        JOIN products p ON p.id = cp.product_id
        WHERE cp.category_slug = ?
          AND p.is_active = 1
          AND p.type = 'paid'
        ORDER BY p.price ASC
    ");
    $prod_stmt->execute([$cat]);
    $available_upgrades = $prod_stmt->fetchAll();
} catch (Exception $e) {
    $available_upgrades = [];
}

$current_price   = (float)($server['renewal_price'] ?? 0);
$current_product = $server['product_id'] ?? 0;

// ═══════════════════════════════════════════
// 6. TRAITEMENT POST : UPGRADE
// ═══════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_product_id']) && !$flash) {
    $new_pid  = (int)$_POST['new_product_id'];
    $new_prod = null;
    
    foreach ($available_upgrades as $ap) {
        if ((int)$ap['id'] === $new_pid) {
            $new_prod = $ap;
            break;
        }
    }
    
    if (!$new_prod) {
        $flash = '<div class="upgrade-alert upgrade-alert-error"><i class="fas fa-times-circle"></i> Offre invalide.</div>';
    } elseif ((int)$new_prod['id'] === (int)$current_product) {
        $flash = '<div class="upgrade-alert upgrade-alert-warning"><i class="fas fa-info-circle"></i> Vous avez déjà cette offre.</div>';
    } elseif ((float)$new_prod['price'] < $current_price) {
        $flash = '<div class="upgrade-alert upgrade-alert-error"><i class="fas fa-arrow-down"></i> Le downgrade n\'est pas disponible. Contactez le support.</div>';
    } else {
        try {
            $pdo->beginTransaction();
            
            // ─── 1. Mise à jour Pterodactyl ───
            $server_id = (int)($server['server_id'] ?? 0);
            $panel_updated = false;
            
            if ($server_id && $api_key_admin) {
                try {
                    $details = curlAdminApi($panel_url, $headers_admin, "servers/$server_id");
                    $alloc   = $details['attributes']['allocation'] ?? null;
                    
                    if ($alloc) {
                        $result = curlAdminApi($panel_url, $headers_admin, "servers/$server_id/build", 'PATCH', [
                            'allocation'     => $alloc,
                            'memory'         => (int)$new_prod['ram'],
                            'swap'           => 0,
                            'disk'           => (int)$new_prod['disk'],
                            'io'             => 500,
                            'cpu'            => (int)$new_prod['cpu'],
                            'threads'        => null,
                            'feature_limits' => [
                                'databases'   => (int)$new_prod['databases'],
                                'backups'     => (int)$new_prod['backups'],
                                'allocations' => (int)$new_prod['allocations']
                            ],
                        ]);
                        $panel_updated = ($result !== null);
                    }
                } catch (Exception $e) {
                    error_log('[Upgrade] Pterodactyl error: ' . $e->getMessage());
                }
            }
            
            // ─── 2. Mise à jour BDD ───
            $pdo->prepare("
                UPDATE orders 
                SET product_id = ?, 
                    service_name = ?, 
                    ram = ?, 
                    disk = ?, 
                    cpu = ?, 
                    renewal_price = ?,
                    updated_at = NOW()
                WHERE uuid = ? AND user_id = ?
            ")->execute([
                $new_prod['id'],
                $new_prod['name'],
                $new_prod['ram'],
                $new_prod['disk'],
                $new_prod['cpu'],
                $new_prod['price'],
                $uuid,
                $_SESSION['user_id']
            ]);
            
            // ─── 3. Log de l'upgrade ───
            try {
                $pdo->prepare("
                    INSERT INTO server_upgrades 
                    (user_id, order_uuid, from_product_id, to_product_id, old_price, new_price, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                ")->execute([
                    $_SESSION['user_id'],
                    $uuid,
                    $current_product,
                    $new_prod['id'],
                    $current_price,
                    $new_prod['price']
                ]);
            } catch (Exception $e) {
                // Table peut ne pas exister
            }
            
            $pdo->commit();
            
            $diff_price = $new_prod['price'] - $current_price;
            $panel_status = $panel_updated 
                ? '✅ Panel mis à jour' 
                : '⚠️ Panel non mis à jour (sera appliqué au prochain reboot)';
            
            $flash = '
                <div class="upgrade-alert upgrade-alert-success">
                    <div class="flex items-start gap-3">
                        <i class="fas fa-check-circle text-xl mt-0.5"></i>
                        <div class="flex-1">
                            <div class="font-bold">Upgrade réussi !</div>
                            <div class="text-sm mt-1">Serveur upgradé vers <strong>' . htmlspecialchars($new_prod['name']) . '</strong></div>
                            <div class="text-xs mt-2 opacity-80">' . $panel_status . '</div>
                            <div class="text-xs mt-1 opacity-80">Nouveau prix : ' . number_format($new_prod['price'], 2, ',', '') . '€/mois (+' . number_format($diff_price, 2, ',', '') . '€)</div>
                        </div>
                    </div>
                </div>
            ';
            
            // Recharger le serveur
            $srv_stmt->execute([$uuid, $_SESSION['user_id']]);
            $server = $srv_stmt->fetch();
            $current_price = (float)($server['renewal_price'] ?? 0);
            $current_product = $server['product_id'] ?? 0;
            
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('[Upgrade] Error: ' . $e->getMessage());
            $flash = '<div class="upgrade-alert upgrade-alert-error"><i class="fas fa-times-circle"></i> Erreur lors de l\'upgrade : ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
}

// ═══════════════════════════════════════════
// 7. TICKETS OUVERTS (pour sidebar)
// ═══════════════════════════════════════════
$open_tickets = 0;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM support_tickets WHERE user_id = ? AND status != 'Fermé'");
    $stmt->execute([$_SESSION['user_id']]);
    $open_tickets = (int)$stmt->fetchColumn();
} catch (Exception $e) { /* silencieux */ }

// ═══════════════════════════════════════════
// 8. HELPER : API PTERODACTYL
// ═══════════════════════════════════════════
function curlAdminApi($url, $headers, $ep, $method = 'GET', $data = null) {
    $ch = curl_init($url . '/api/application/' . $ep);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    
    if ($method === 'DELETE') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    } elseif ($method === 'PATCH') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
        if ($data) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    } elseif ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $r = curl_exec($ch);
    $c = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($c === 204) return true;
    return $r ? json_decode($r, true) : null;
}

// ═══════════════════════════════════════════
// 9. DONNÉES POUR LE JS
// ═══════════════════════════════════════════
$products_json = [];
foreach ($available_upgrades as $ap) {
    $products_json[] = [
        'id'    => (int)$ap['id'],
        'name'  => $ap['name'],
        'price' => (float)$ap['price'],
        'ram'   => (int)$ap['ram'],
        'disk'  => (int)$ap['disk'],
        'cpu'   => (int)$ap['cpu'],
    ];
}

include $_SERVER['DOCUMENT_ROOT'] . '/inc/clients_sidebar.php';
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upgrader — <?php echo htmlspecialchars($server['service_name'] ?? 'Serveur'); ?> | OrinHeberge</title>
    <link rel="icon" type="image/png" href="/favicon.ico">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <!-- CSS Upgrade (external) -->
    <link rel="stylesheet" href="/assets/css/upgrade.css?v=<?php echo file_exists($_SERVER['DOCUMENT_ROOT'] . '/assets/css/upgrade.css') ? filemtime($_SERVER['DOCUMENT_ROOT'] . '/assets/css/upgrade.css') : time(); ?>">
    
    <style>
        /* Fallback inline si upgrade.css n'existe pas encore */
        body { background: #0d0f14; color: #e2e8f0; }
        .upgrade-page { padding: 1.5rem; max-width: 1200px; margin: 0 auto; }
        .upgrade-alert { padding: 1rem; border-radius: 0.75rem; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem; }
        .upgrade-alert-success { background: rgba(34, 197, 94, 0.1); color: #4ade80; border: 1px solid rgba(34, 197, 94, 0.2); }
        .upgrade-alert-error { background: rgba(239, 68, 68, 0.1); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.2); }
        .upgrade-alert-warning { background: rgba(251, 191, 36, 0.1); color: #fbbf24; border: 1px solid rgba(251, 191, 36, 0.2); }
        .upgrade-card { background: #161a22; border: 1px solid rgba(255,255,255,0.07); border-radius: 0.875rem; padding: 1.5rem; }
        .plan-card { background: #161a22; border: 2px solid rgba(255,255,255,0.06); border-radius: 1rem; padding: 1.25rem; cursor: pointer; transition: all 0.2s; position: relative; }
        .plan-card:hover { border-color: rgba(56, 189, 248, 0.4); transform: translateY(-2px); }
        .plan-card.selected { border-color: #38bdf8; background: rgba(56, 189, 248, 0.05); box-shadow: 0 0 20px rgba(56, 189, 248, 0.15); }
        .plan-card.current { border-color: #22c55e; background: rgba(34, 197, 94, 0.04); cursor: default; }
        .plan-card.disabled { opacity: 0.5; cursor: not-allowed; }
        .plan-badge { position: absolute; top: -10px; left: 16px; padding: 0.2rem 0.65rem; border-radius: 9999px; font-size: 10px; font-weight: 800; text-transform: uppercase; }
        .confirm-bar { position: fixed; bottom: 0; left: 240px; right: 0; background: #111318; border-top: 1px solid rgba(255,255,255,0.1); padding: 1rem 1.5rem; z-index: 30; display: none; align-items: center; justify-content: space-between; gap: 1rem; box-shadow: 0 -10px 30px rgba(0,0,0,0.5); }
        .confirm-bar.active { display: flex; }
        @media (max-width: 768px) {
            .confirm-bar { left: 0; }
            .upgrade-page { padding: 1rem; }
        }
    </style>
</head>
<body class="min-h-screen">

<div class="upgrade-page">
    
    <!-- Header -->
    <div class="mb-6 flex items-center justify-between flex-wrap gap-3">
        <div>
            <h1 class="text-2xl font-black text-white flex items-center gap-2">
                <i class="fas fa-arrow-up text-sky-400"></i>
                Upgrader mon serveur
            </h1>
            <p class="text-sm text-gray-400 mt-1">
                <?php echo htmlspecialchars($server['service_name'] ?? 'Serveur'); ?>
                <span class="text-gray-600 mx-2">•</span>
                <code class="text-xs text-gray-500"><?php echo htmlspecialchars(substr($uuid, 0, 8)); ?>…</code>
            </p>
        </div>
        <a href="/client/servers/" class="flex items-center gap-2 text-sm text-gray-400 hover:text-white bg-white/5 hover:bg-white/10 border border-white/10 px-4 py-2 rounded-xl transition">
            <i class="fas fa-arrow-left text-xs"></i>
            Retour aux serveurs
        </a>
    </div>
    
    <!-- Flash message -->
    <?php if ($flash): ?>
        <div class="animate-fade-in"><?php echo $flash; ?></div>
    <?php endif; ?>
    
    <!-- Carte serveur actuel -->
    <div class="upgrade-card mb-6 flex items-center gap-4 flex-wrap">
        <div class="w-12 h-12 rounded-xl bg-sky-500/15 border border-sky-500/20 flex items-center justify-center shrink-0">
            <i class="fas fa-server text-sky-400 text-lg"></i>
        </div>
        <div class="flex-1 min-w-0">
            <div class="text-sm font-bold text-white truncate"><?php echo htmlspecialchars($server['service_name'] ?? 'Serveur'); ?></div>
            <div class="text-xs text-gray-500 font-mono mt-0.5">
                <?php echo (int)($server['ram'] ?? 0); ?> MB RAM
                <span class="mx-1.5">•</span>
                <?php echo (int)($server['disk'] ?? 0); ?> MB SSD
                <span class="mx-1.5">•</span>
                <?php echo (int)($server['cpu'] ?? 0); ?>% CPU
            </div>
        </div>
        <div class="text-right shrink-0">
            <div class="text-[10px] text-gray-500 uppercase tracking-wider font-bold">Offre actuelle</div>
            <div class="text-lg font-black text-emerald-400"><?php echo number_format($current_price, 2, ',', ''); ?>€<span class="text-xs text-gray-500 font-normal">/mois</span></div>
        </div>
    </div>
    
    <!-- Liste des upgrades -->
    <?php if (empty($available_upgrades)): ?>
        <div class="upgrade-card text-center py-12">
            <i class="fas fa-rocket text-4xl text-gray-600 mb-4 block"></i>
            <h3 class="text-base font-bold text-gray-300 mb-2">Aucun upgrade disponible</h3>
            <p class="text-sm text-gray-500 mb-4 max-w-md mx-auto">
                Ce type de serveur n'a pas d'offres supérieures disponibles pour le moment.
            </p>
            <a href="/client/servers/" class="inline-flex items-center gap-2 bg-sky-600 hover:bg-sky-500 text-white text-sm font-bold px-5 py-2.5 rounded-xl transition">
                <i class="fas fa-arrow-left text-xs"></i>
                Retour aux serveurs
            </a>
        </div>
    
    <?php else: ?>
        
        <h2 class="text-base font-bold text-white mb-4 flex items-center gap-2">
            <i class="fas fa-layer-group text-sky-400"></i>
            Choisir une nouvelle offre
            <span class="text-xs text-gray-500 font-normal ml-2">— <?php echo ucfirst(htmlspecialchars($cat)); ?></span>
        </h2>
        
        <form method="POST" id="upgradeForm">
            <input type="hidden" name="new_product_id" id="new_product_id" value="">
            
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-6 pb-24">
                <?php foreach ($available_upgrades as $ap): 
                    $is_current  = ((int)$ap['id'] === (int)$current_product);
                    $is_downgrade = ((float)$ap['price'] < $current_price) && !$is_current;
                    $ram_t  = $ap['ram'] >= 1024 ? number_format($ap['ram']/1024, 0) . ' GB RAM' : $ap['ram'] . ' MB RAM';
                    $disk_t = $ap['disk'] >= 1024 ? number_format($ap['disk']/1024, 0) . ' GB SSD' : $ap['disk'] . ' MB SSD';
                    $diff   = (float)$ap['price'] - $current_price;
                ?>
                <div 
                    class="plan-card <?php echo $is_current ? 'current' : ''; ?> <?php echo $is_downgrade ? 'disabled' : ''; ?>"
                    data-product-id="<?php echo (int)$ap['id']; ?>"
                    data-product-name="<?php echo htmlspecialchars($ap['name'], ENT_QUOTES); ?>"
                    data-product-price="<?php echo (float)$ap['price']; ?>"
                    data-price-diff="<?php echo number_format($diff, 2, '.', ''); ?>"
                    <?php if (!$is_current && !$is_downgrade): ?>
                        onclick="selectPlan(this)"
                    <?php endif; ?>
                >
                    <?php if ($is_current): ?>
                        <div class="plan-badge bg-emerald-500 text-slate-950">
                            <i class="fas fa-check text-[9px]"></i> Actuel
                        </div>
                    <?php elseif ($is_downgrade): ?>
                        <div class="plan-badge bg-gray-600 text-white">
                            <i class="fas fa-arrow-down text-[9px]"></i> Downgrade
                        </div>
                    <?php elseif ($diff > 0 && $diff < 5): ?>
                        <div class="plan-badge bg-sky-500 text-white">
                            <i class="fas fa-fire text-[9px]"></i> Populaire
                        </div>
                    <?php endif; ?>
                    
                    <div class="text-sm font-bold text-white mb-1"><?php echo htmlspecialchars($ap['name']); ?></div>
                    <div class="mb-3">
                        <span class="text-2xl font-black text-white"><?php echo number_format((float)$ap['price'], 2, ',', ''); ?></span>
                        <span class="text-gray-500 text-xs font-normal">€/mois</span>
                        <?php if (!$is_current && $diff > 0): ?>
                            <div class="text-xs text-sky-400 mt-0.5">+<?php echo number_format($diff, 2, ',', ''); ?>€/mois</div>
                        <?php elseif (!$is_current && $diff < 0): ?>
                            <div class="text-xs text-gray-500 mt-0.5 line-through">Downgrade non dispo.</div>
                        <?php endif; ?>
                    </div>
                    
                    <ul class="space-y-1.5 text-xs text-gray-400 mb-3">
                        <li class="flex items-center gap-2">
                            <i class="fas fa-memory text-sky-400 w-3.5"></i>
                            <span><?php echo $ram_t; ?></span>
                        </li>
                        <li class="flex items-center gap-2">
                            <i class="fas fa-hard-drive text-sky-400 w-3.5"></i>
                            <span><?php echo $disk_t; ?></span>
                        </li>
                        <li class="flex items-center gap-2">
                            <i class="fas fa-microchip text-sky-400 w-3.5"></i>
                            <span><?php echo (int)$ap['cpu']; ?>% CPU</span>
                        </li>
                        <li class="flex items-center gap-2">
                            <i class="fas fa-database text-sky-400 w-3.5"></i>
                            <span><?php echo (int)$ap['databases']; ?> Bases de données</span>
                        </li>
                        <li class="flex items-center gap-2">
                            <i class="fas fa-archive text-sky-400 w-3.5"></i>
                            <span><?php echo (int)$ap['backups']; ?> Backups</span>
                        </li>
                    </ul>
                    
                    <?php if ($is_current): ?>
                        <div class="w-full text-center text-xs font-bold text-emerald-400 py-2 rounded-lg border border-emerald-500/30 bg-emerald-500/5">
                            <i class="fas fa-check mr-1"></i> Offre actuelle
                        </div>
                    <?php elseif ($is_downgrade): ?>
                        <div class="w-full text-center text-xs font-semibold text-gray-500 py-2 rounded-lg border border-white/5 bg-white/[0.02]">
                            Non disponible
                        </div>
                    <?php else: ?>
                        <div class="w-full text-center text-xs font-bold text-sky-400 py-2 rounded-lg border border-sky-500/20 bg-sky-500/5 group-hover:bg-sky-500/10 transition">
                            <i class="fas fa-arrow-right mr-1"></i> Sélectionner
                        </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Barre de confirmation -->
            <div id="confirmBar" class="confirm-bar">
                <div class="flex-1 min-w-0">
                    <div class="text-xs text-gray-400">Upgrade vers</div>
                    <div class="text-sm font-bold text-white truncate" id="confirmName">—</div>
                    <div class="text-xs text-sky-400 font-mono" id="confirmPrice">—</div>
                    <div class="text-[10px] text-gray-500 mt-0.5" id="confirmDiff"></div>
                </div>
                <button type="submit" id="submitBtn" class="bg-sky-600 hover:bg-sky-500 text-white font-bold px-6 py-3 rounded-xl text-sm transition flex items-center gap-2 shrink-0 shadow-lg shadow-sky-900/30">
                    <i class="fas fa-rocket text-xs"></i>
                    <span>Confirmer l'upgrade</span>
                </button>
            </div>
        </form>
    <?php endif; ?>
</div>

<!-- Données pour JS -->
<script>
    window.UPGRADE_CONFIG = {
        currentProductId: <?php echo (int)$current_product; ?>,
        currentPrice: <?php echo $current_price; ?>,
        serverUuid: <?php echo json_encode($uuid); ?>,
        products: <?php echo json_encode($products_json); ?>
    };
</script>

<!-- JS Upgrade (external) -->
<script src="/assets/js/upgrade.js?v=<?php echo file_exists($_SERVER['DOCUMENT_ROOT'] . '/assets/js/upgrade.js') ? filemtime($_SERVER['DOCUMENT_ROOT'] . '/assets/js/upgrade.js') : time(); ?>" defer></script>

<script>
// Fallback inline si upgrade.js n'existe pas
if (typeof selectPlan === 'undefined') {
    window.selectPlan = function(el) {
        const productId = parseInt(el.dataset.productId);
        const productName = el.dataset.productName;
        const productPrice = parseFloat(el.dataset.productPrice);
        const priceDiff = parseFloat(el.dataset.priceDiff);
        
        // Retirer sélection précédente
        document.querySelectorAll('.plan-card.selected').forEach(c => c.classList.remove('selected'));
        
        // Sélectionner nouvelle
        el.classList.add('selected');
        
        // Remplir formulaire
        document.getElementById('new_product_id').value = productId;
        
        // Mettre à jour barre de confirmation
        document.getElementById('confirmName').textContent = productName;
        document.getElementById('confirmPrice').textContent = productPrice.toFixed(2).replace('.', ',') + '€/mois';
        
        const diffEl = document.getElementById('confirmDiff');
        if (priceDiff > 0) {
            diffEl.textContent = 'Différence : +' + priceDiff.toFixed(2).replace('.', ',') + '€/mois';
            diffEl.className = 'text-[10px] text-amber-400 mt-0.5';
        } else {
            diffEl.textContent = '';
        }
        
        // Afficher barre
        document.getElementById('confirmBar').classList.add('active');
    };
    
    // Confirmation avant submit
    document.getElementById('upgradeForm')?.addEventListener('submit', function(e) {
        const productId = document.getElementById('new_product_id').value;
        if (!productId) {
            e.preventDefault();
            alert('Veuillez sélectionner une offre.');
            return false;
        }
        
        const name = document.getElementById('confirmName').textContent;
        if (!confirm('Confirmer l\'upgrade vers "' + name + '" ?\n\nLes nouvelles ressources seront appliquées immédiatement.')) {
            e.preventDefault();
            return false;
        }
    });
}
</script>

<style>
    @keyframes fade-in {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .animate-fade-in { animation: fade-in 0.3s ease-out; }
</style>

</body>
</html>
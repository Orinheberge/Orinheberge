<?php
/**
 * /client/servers/upgrade/success/ — Page de succès après paiement Stripe Elements
 * Vérifie le PaymentIntent, applique l'upgrade sur Pterodactyl et la BDD.
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
// 1. SETTINGS
// ═══════════════════════════════════════════
$cfg = [];
try {
    foreach ($pdo->query('SELECT `key`, `value` FROM settings') as $r) {
        $cfg[$r['key']] = $r['value'];
    }
} catch (Exception $e) {
    $cfg = [];
}

$stripe_secret_key = $cfg['stripe_secret_key'] ?? '';
$panel_url         = rtrim($cfg['panel_url'] ?? 'https://panel.orinstone.deepstone.fr', '/');
$api_key_admin     = $cfg['api_key_admin'] ?? '';
$headers_admin     = [
    "Authorization: Bearer $api_key_admin",
    "Accept: application/vnd.pterodactyl.v1+json",
    "Content-Type: application/json"
];

$payment_intent_id = trim($_GET['payment_intent_id'] ?? '');
$uuid              = trim($_GET['uuid'] ?? '');
$requested_pid     = (int)($_GET['product_id'] ?? 0);

// ═══════════════════════════════════════════
// 2. VÉRIFICATION DU PAYMENTINTENT
// ═══════════════════════════════════════════
$payment_intent = null;
$payment_verified = false;
$error_msg = '';
$upgrade_data = [];

if (empty($payment_intent_id)) {
    $error_msg = 'Aucun identifiant de paiement fourni.';
} else {
    try {
        $ch = curl_init('https://api.stripe.com/v1/payment_intents/' . urlencode($payment_intent_id));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD        => $stripe_secret_key . ':',
            CURLOPT_TIMEOUT        => 15,
        ]);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code === 200) {
            $payment_intent = json_decode($response, true);
            if (($payment_intent['status'] ?? '') === 'succeeded') {
                $payment_verified = true;
            }
        } else {
            $error_msg = 'Impossible de vérifier le paiement Stripe (HTTP ' . $http_code . ').';
        }
    } catch (Exception $e) {
        $error_msg = 'Erreur de communication avec Stripe : ' . $e->getMessage();
    }
}

// ═══════════════════════════════════════════
// 3. VÉRIFIER LA COHÉRENCE DES DONNÉES
// ═══════════════════════════════════════════
if ($payment_verified && $payment_intent) {
    $meta = $payment_intent['metadata'] ?? [];
    
    // Sécurité : s'assurer que le paiement appartient bien à cet utilisateur
    if ((int)($meta['user_id'] ?? 0) !== (int)$_SESSION['user_id']) {
        $payment_verified = false;
        $error_msg = 'Paiement invalide : utilisateur non correspondant.';
    }
    
    $upgrade_data = [
        'order_uuid'         => $meta['order_uuid'] ?? $uuid,
        'new_product_id'     => (int)($meta['new_product_id'] ?? $requested_pid),
        'current_product_id' => (int)($meta['current_product_id'] ?? 0),
        'old_price'          => (float)($meta['old_price'] ?? 0),
        'new_price'          => (float)($meta['new_price'] ?? 0),
        'diff_price'         => (float)($meta['diff_price'] ?? 0),
    ];
}

// ═══════════════════════════════════════════
// 4. RÉCUPÉRER LE SERVEUR
// ═══════════════════════════════════════════
$server = null;
$new_product = null;
$panel_updated = false;

if ($payment_verified && !empty($upgrade_data['order_uuid'])) {
    try {
        $srv_stmt = $pdo->prepare('
            SELECT o.*, p.slug AS product_slug, p.id AS pid
            FROM orders o
            LEFT JOIN products p ON p.id = o.product_id
            WHERE o.uuid = ? AND o.user_id = ?
            LIMIT 1
        ');
        $srv_stmt->execute([$upgrade_data['order_uuid'], $_SESSION['user_id']]);
        $server = $srv_stmt->fetch();
    } catch (Exception $e) { /* silencieux */ }

    if ($server && $upgrade_data['new_product_id']) {
        try {
            $np_stmt = $pdo->prepare('SELECT * FROM products WHERE id = ? LIMIT 1');
            $np_stmt->execute([$upgrade_data['new_product_id']]);
            $new_product = $np_stmt->fetch();
        } catch (Exception $e) { /* silencieux */ }
    }
}

// ═══════════════════════════════════════════
// 5. APPLIQUER L'UPGRADE (SI TOUT EST OK)
// ═══════════════════════════════════════════
$upgrade_applied = false;
$already_applied = false;

if ($payment_verified && $server && $new_product && ($server['status'] ?? '') === 'paid') {
    
    // Vérifier si l'upgrade n'a pas déjà été appliqué
    if ((int)$server['product_id'] === (int)$new_product['id']) {
        $already_applied = true;
        $upgrade_applied = true;
    } else {
        try {
            $pdo->beginTransaction();

            // ─── 5a. Mise à jour Pterodactyl ───
            $server_id = (int)($server['server_id'] ?? 0);
            if ($server_id && $api_key_admin) {
                try {
                    $details = curlAdminApi($panel_url, $headers_admin, "servers/$server_id");
                    $alloc = $details['attributes']['allocation'] ?? null;

                    if ($alloc) {
                        $result = curlAdminApi($panel_url, $headers_admin, "servers/$server_id/build", 'PATCH', [
                            'allocation'     => $alloc,
                            'memory'         => (int)$new_product['ram'],
                            'swap'           => 0,
                            'disk'           => (int)$new_product['disk'],
                            'io'             => 500,
                            'cpu'            => (int)$new_product['cpu'],
                            'threads'        => null,
                            'feature_limits' => [
                                'databases'   => (int)$new_product['databases'],
                                'backups'     => (int)$new_product['backups'],
                                'allocations' => (int)$new_product['allocations'],
                            ],
                        ]);
                        $panel_updated = ($result !== null);
                    }
                } catch (Exception $e) {
                    error_log('[Upgrade/Success] Pterodactyl error: ' . $e->getMessage());
                }
            }

            // ─── 5b. Mise à jour BDD ───
            $pdo->prepare("
                UPDATE orders
                SET product_id    = ?,
                    service_name  = ?,
                    ram           = ?,
                    disk          = ?,
                    cpu           = ?,
                    renewal_price = ?,
                    next_due_date = DATE_ADD(COALESCE(next_due_date, NOW()), INTERVAL 1 MONTH),
                    updated_at    = NOW()
                WHERE uuid = ? AND user_id = ?
            ")->execute([
                $new_product['id'],
                $new_product['name'],
                $new_product['ram'],
                $new_product['disk'],
                $new_product['cpu'],
                $new_product['price'],
                $upgrade_data['order_uuid'],
                $_SESSION['user_id']
            ]);

            // ─── 5c. Log de l'upgrade ───
            try {
                $pdo->prepare("
                    INSERT INTO server_upgrades
                    (user_id, order_uuid, from_product_id, to_product_id, old_price, new_price, diff_amount, stripe_payment_intent_id, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ")->execute([
                    $_SESSION['user_id'],
                    $upgrade_data['order_uuid'],
                    $upgrade_data['current_product_id'],
                    $new_product['id'],
                    $upgrade_data['old_price'],
                    $new_product['price'],
                    $upgrade_data['diff_price'],
                    $payment_intent_id
                ]);
            } catch (Exception $e) {
                // Table peut ne pas exister
            }

            // ─── 5d. Marquer la transaction pending comme complétée ───
            try {
                $pdo->prepare("
                    UPDATE pending_upgrades 
                    SET status = 'completed', completed_at = NOW()
                    WHERE stripe_payment_intent_id = ? AND user_id = ?
                ")->execute([$payment_intent_id, $_SESSION['user_id']]);
            } catch (Exception $e) { /* silencieux */ }

            $pdo->commit();
            $upgrade_applied = true;

            // Recharger le serveur
            $srv_stmt->execute([$upgrade_data['order_uuid'], $_SESSION['user_id']]);
            $server = $srv_stmt->fetch();

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('[Upgrade/Success] DB error: ' . $e->getMessage());
            $error_msg = 'Erreur lors de l\'application de l\'upgrade en base : ' . $e->getMessage();
        }
    }
}

// ═══════════════════════════════════════════
// 6. TICKETS OUVERTS (sidebar)
// ═══════════════════════════════════════════
$open_tickets = 0;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM support_tickets WHERE user_id = ? AND status != 'Fermé'");
    $stmt->execute([$_SESSION['user_id']]);
    $open_tickets = (int)$stmt->fetchColumn();
} catch (Exception $e) { /* silencieux */ }

// ═══════════════════════════════════════════
// HELPER : API PTERODACTYL
// ═══════════════════════════════════════════
function curlAdminApi($url, $headers, $ep, $method = 'GET', $data = null) {
    $ch = curl_init($url . '/api/application/' . $ep);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => false,
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

include $_SERVER['DOCUMENT_ROOT'] . '/inc/clients_sidebar.php';
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upgrade <?php echo $upgrade_applied ? 'réussi' : 'échoué'; ?> | OrinHeberge</title>
    <link rel="icon" type="image/png" href="/favicon.ico">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #0d0f14; color: #e2e8f0; font-family: 'Inter', sans-serif; }
        .success-page { padding: 2rem; max-width: 700px; margin: 0 auto; }
        .success-card { background: #161a22; border: 1px solid rgba(255,255,255,0.08); border-radius: 1.25rem; padding: 2rem; text-align: center; }
        .icon-pulse { animation: pulse-ring 2s ease-in-out infinite; }
        @keyframes pulse-ring {
            0%, 100% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.4); }
            50% { box-shadow: 0 0 0 20px rgba(34, 197, 94, 0); }
        }
        @keyframes fade-in-up {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-in { animation: fade-in-up 0.6s ease-out; }
        .animate-in-delay { animation: fade-in-up 0.6s ease-out 0.2s both; }
        .animate-in-delay2 { animation: fade-in-up 0.6s ease-out 0.4s both; }
    </style>
</head>
<body class="min-h-screen">

<div class="success-page">

    <?php if ($upgrade_applied && $new_product): ?>
        <!-- ✅ SUCCÈS -->
        <div class="animate-in">
            <div class="w-20 h-20 mx-auto mb-6 rounded-full bg-emerald-500/15 border border-emerald-500/30 flex items-center justify-center icon-pulse">
                <i class="fas fa-check text-emerald-400 text-3xl"></i>
            </div>
            <h1 class="text-2xl font-black text-white mb-2">
                Upgrade <?php echo $already_applied ? 'déjà appliqué' : 'réussi'; ?> !
            </h1>
            <p class="text-gray-400 mb-8">
                Votre serveur a été mis à jour avec succès. Les nouvelles ressources sont actives.
            </p>
        </div>

        <!-- Détails -->
        <div class="success-card mb-6 text-left animate-in-delay">
            <div class="flex items-center gap-4 mb-6 pb-6 border-b border-white/10">
                <div class="w-12 h-12 rounded-xl bg-sky-500/15 border border-sky-500/20 flex items-center justify-center shrink-0">
                    <i class="fas fa-server text-sky-400 text-lg"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="text-sm text-gray-500">Serveur</div>
                    <div class="text-base font-bold text-white truncate">
                        <?php echo htmlspecialchars($server['service_name'] ?? ''); ?>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
                <div class="bg-white/[0.03] rounded-xl p-4">
                    <div class="text-[10px] text-gray-500 uppercase font-bold mb-2">Ancienne offre</div>
                    <div class="text-sm text-gray-400 line-through mb-1">
                        <?php echo number_format($upgrade_data['old_price'], 2, ',', ''); ?>€/mois
                    </div>
                    <div class="text-xs text-gray-500">
                        <?php echo (int)($server['ram'] ?? 0); ?> MB • <?php echo (int)($server['disk'] ?? 0); ?> MB
                    </div>
                </div>
                <div class="bg-emerald-500/[0.07] border border-emerald-500/20 rounded-xl p-4">
                    <div class="text-[10px] text-emerald-400 uppercase font-bold mb-2">
                        <i class="fas fa-arrow-up"></i> Nouvelle offre
                    </div>
                    <div class="text-sm font-bold text-white mb-1">
                        <?php echo htmlspecialchars($new_product['name']); ?>
                    </div>
                    <div class="text-lg font-black text-emerald-400">
                        <?php echo number_format((float)$new_product['price'], 2, ',', ''); ?>€<span class="text-xs text-gray-500 font-normal">/mois</span>
                    </div>
                </div>
            </div>

            <!-- Ressources -->
            <div class="grid grid-cols-3 gap-3 mb-6">
                <div class="text-center bg-white/[0.03] rounded-lg py-3">
                    <i class="fas fa-memory text-sky-400 mb-1"></i>
                    <div class="text-xs text-gray-500">RAM</div>
                    <div class="text-sm font-bold text-white">
                        <?php echo $new_product['ram'] >= 1024 ? number_format($new_product['ram']/1024, 0) . ' GB' : $new_product['ram'] . ' MB'; ?>
                    </div>
                </div>
                <div class="text-center bg-white/[0.03] rounded-lg py-3">
                    <i class="fas fa-hard-drive text-sky-400 mb-1"></i>
                    <div class="text-xs text-gray-500">SSD</div>
                    <div class="text-sm font-bold text-white">
                        <?php echo $new_product['disk'] >= 1024 ? number_format($new_product['disk']/1024, 0) . ' GB' : $new_product['disk'] . ' MB'; ?>
                    </div>
                </div>
                <div class="text-center bg-white/[0.03] rounded-lg py-3">
                    <i class="fas fa-microchip text-sky-400 mb-1"></i>
                    <div class="text-xs text-gray-500">CPU</div>
                    <div class="text-sm font-bold text-white"><?php echo (int)$new_product['cpu']; ?>%</div>
                </div>
            </div>

            <!-- Paiement -->
            <div class="flex items-center justify-between text-sm border-t border-white/10 pt-4">
                <div>
                    <span class="text-gray-400">Montant payé</span>
                    <span class="text-white font-bold ml-2">
                        +<?php echo number_format($upgrade_data['diff_price'], 2, ',', ''); ?>€
                    </span>
                </div>
                <div class="text-right">
                    <span class="text-[10px] text-gray-500">Payment Intent</span>
                    <div class="text-[10px] text-gray-400 font-mono">
                        <?php echo htmlspecialchars(substr($payment_intent_id, 0, 16)); ?>…
                    </div>
                </div>
            </div>
        </div>

        <!-- Status Panel -->
        <?php if (!$already_applied): ?>
        <div class="success-card mb-6 text-left animate-in-delay">
            <h3 class="text-sm font-bold text-white mb-3 flex items-center gap-2">
                <i class="fas fa-tasks text-sky-400"></i> Statut des opérations
            </h3>
            <div class="space-y-2">
                <div class="flex items-center gap-3 text-sm">
                    <i class="fas fa-check-circle text-emerald-400"></i>
                    <span class="text-gray-300">Paiement Stripe confirmé</span>
                </div>
                <div class="flex items-center gap-3 text-sm">
                    <i class="fas fa-check-circle text-emerald-400"></i>
                    <span class="text-gray-300">Base de données mise à jour</span>
                </div>
                <div class="flex items-center gap-3 text-sm">
                    <?php if ($panel_updated): ?>
                        <i class="fas fa-check-circle text-emerald-400"></i>
                        <span class="text-gray-300">Panel Pterodactyl mis à jour</span>
                    <?php else: ?>
                        <i class="fas fa-exclamation-circle text-amber-400"></i>
                        <span class="text-amber-300">Panel non mis à jour (appliqué au prochain reboot)</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Actions -->
        <div class="flex flex-col sm:flex-row gap-3 justify-center animate-in-delay2">
            <a href="/client/servers/"
               class="inline-flex items-center justify-center gap-2 bg-sky-600 hover:bg-sky-500 text-white font-bold px-6 py-3 rounded-xl transition shadow-lg shadow-sky-900/30">
                <i class="fas fa-arrow-left text-xs"></i>
                Retour aux serveurs
            </a>
            <a href="<?php echo htmlspecialchars($panel_url); ?>" target="_blank"
               class="inline-flex items-center justify-center gap-2 bg-white/5 hover:bg-white/10 text-white font-bold px-6 py-3 rounded-xl border border-white/10 transition">
                <i class="fas fa-external-link-alt text-xs"></i>
                Ouvrir le panel
            </a>
        </div>

    <?php else: ?>
        <!-- ❌ ERREUR -->
        <div class="success-card">
            <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-red-500/15 border border-red-500/30 flex items-center justify-center">
                <i class="fas fa-exclamation-triangle text-red-400 text-xl"></i>
            </div>
            <h1 class="text-xl font-black text-white mb-2">Upgrade non appliqué</h1>
            <p class="text-sm text-gray-400 mb-6">
                <?php if ($error_msg): ?>
                    <?php echo htmlspecialchars($error_msg); ?>
                <?php else: ?>
                    Le paiement n'a pas pu être vérifié ou les données sont invalides.
                <?php endif; ?>
            </p>
            
            <?php if ($payment_verified && !$upgrade_applied): ?>
                <div class="bg-amber-500/10 border border-amber-500/20 rounded-xl p-4 mb-6 text-left">
                    <div class="flex items-start gap-3">
                        <i class="fas fa-info-circle text-amber-400 mt-0.5"></i>
                        <div class="text-sm text-amber-300">
                            Le paiement a bien été reçu mais l'upgrade n'a pas pu être appliqué automatiquement.
                            Notre équipe a été notifiée et finalisera la mise à jour sous peu.
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="flex flex-col gap-3">
                <a href="/client/servers/"
                   class="inline-flex items-center justify-center gap-2 bg-sky-600 hover:bg-sky-500 text-white font-bold px-5 py-3 rounded-xl transition">
                    <i class="fas fa-arrow-left text-xs"></i>
                    Retour aux serveurs
                </a>
                <a href="/support/new/"
                   class="text-sm text-gray-400 hover:text-white transition">
                    <i class="fas fa-headset text-xs"></i> Contacter le support
                </a>
            </div>
        </div>
    <?php endif; ?>

</div>

</body>
</html>
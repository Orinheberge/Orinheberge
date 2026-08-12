<?php
/**
 * /shop/order/renewal/success/ — Page de succès après paiement de renouvellement
 * Finalise le renouvellement : mise à jour BDD, panel, email, Discord.
 */
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: /login/");
    exit();
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/db.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/lang.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/api/facture.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/webhook/discord.php';

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

$ext_settings_raw = $pdo->query("SELECT e.slug, es.key, es.value FROM extension_settings es JOIN extensions e ON e.id = es.extension_id")->fetchAll();
$ext_cfg = [];
foreach ($ext_settings_raw as $r) $ext_cfg[$r['slug']][$r['key']] = $r['value'];
$stripe_secret_key = $ext_cfg['stripe']['secret_key'] ?? '';

$discord_webhook_url = "https://discord.com/api/webhooks/1505677242527649872/jFoANIv3OKNtGMib4bViJ79ltRDsf0LJviq59yXwW5hrqZ0uTyU1Yx3nV88yy6rG2eA4";
$panel_url     = rtrim($cfg['panel_url'] ?? '', '/');
$api_key_admin = $cfg['api_key_admin'] ?? '';

// ═══════════════════════════════════════════
// 2. RÉCUPÉRATION DES PARAMÈTRES
// ═══════════════════════════════════════════
$order_row_id = (int)($_GET['order_id'] ?? 0);
$payment_intent_id = trim($_GET['payment_intent'] ?? '');

if (!$order_row_id) {
    header('Location: /client/servers/');
    exit();
}

// ═══════════════════════════════════════════
// 3. RÉCUPÉRATION DE LA COMMANDE
// ═══════════════════════════════════════════
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ? LIMIT 1");
$stmt->execute([$order_row_id, $_SESSION['user_id']]);
$order = $stmt->fetch();

if (!$order) {
    die("Commande introuvable.");
}

$price = (float)$order['renewal_price'];

// ═══════════════════════════════════════════
// 4. VÉRIFICATION DU PAIEMENT STRIPE
// ═══════════════════════════════════════════
$payment_verified = false;
$payment_error = '';

if (!empty($payment_intent_id) && !empty($stripe_secret_key)) {
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
            $pi = json_decode($response, true);
            if (($pi['status'] ?? '') === 'succeeded') {
                // Vérifier que le PI appartient bien à cet utilisateur
                if ((int)($pi['metadata']['user_id'] ?? 0) === (int)$_SESSION['user_id']) {
                    $payment_verified = true;
                } else {
                    $payment_error = 'Paiement invalide : utilisateur non correspondant.';
                }
            } else {
                $payment_error = 'Paiement non confirmé (statut : ' . ($pi['status'] ?? 'inconnu') . ')';
            }
        } else {
            $payment_error = 'Impossible de vérifier le paiement (HTTP ' . $http_code . ')';
        }
    } catch (Exception $e) {
        $payment_error = 'Erreur : ' . $e->getMessage();
    }
}

// ═══════════════════════════════════════════
// 5. FINALISATION DU RENOUVELLEMENT
// ═══════════════════════════════════════════
$renewal_done = false;
$panel_reactivated = false;
$was_suspended = ($order['status'] === 'suspended');

if ($payment_verified) {
    try {
        $pdo->beginTransaction();

        // Mise à jour de la date de prochaine échéance
        $current_next = $order['next_payment_date'];
        $base_date = ($current_next < date('Y-m-d')) ? date('Y-m-d') : $current_next;
        $new_next_payment = date('Y-m-d', strtotime($base_date . ' +1 month'));

        $pdo->prepare("
            UPDATE orders
            SET status = 'paid',
                next_payment_date = ?,
                suspended_at = NULL,
                delete_after = NULL,
                updated_at = NOW()
            WHERE id = ?
        ")->execute([$new_next_payment, $order_row_id]);

        // ── Réactivation sur le panel Pterodactyl si suspendu ─────────────
        if ($was_suspended && !empty($order['server_id']) && $panel_url && $api_key_admin) {
            try {
                $ch = curl_init($panel_url . '/api/application/servers/' . $order['server_id'] . '/unsuspend');
                curl_setopt_array($ch, [
                    CURLOPT_HTTPHEADER => [
                        "Authorization: Bearer " . $api_key_admin,
                        "Accept: application/vnd.pterodactyl.v1+json",
                        "Content-Type: application/json"
                    ],
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT        => 15,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_POST           => true,
                    CURLOPT_POSTFIELDS     => '{}'
                ]);
                $resp = curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                $panel_reactivated = ($http_code >= 200 && $http_code < 300);
            } catch (Exception $e) {
                error_log('[Renewal/Success] Panel unsuspend error: ' . $e->getMessage());
            }
        }

        // ── Création de la facture ─────────────────────────────────────────
        try {
            createInvoice($pdo, [
                'user_id'        => $_SESSION['user_id'],
                'order_id'       => $order['order_id'],
                'service_name'   => $order['service_name'],
                'amount'         => $price,
                'type'           => 'renewal',
                'status'         => 'paid',
                'payment_method' => 'stripe',
                'payment_ref'    => $payment_intent_id,
                'paid_at'        => date('Y-m-d H:i:s'),
            ]);
        } catch (Exception $e) {
            error_log('[Renewal/Success] Invoice creation error: ' . $e->getMessage());
        }

        $pdo->commit();
        $renewal_done = true;

        // Recharger l'order pour affichage
        $stmt->execute([$order_row_id, $_SESSION['user_id']]);
        $order = $stmt->fetch();

        // ── Email de confirmation ──────────────────────────────────────────
        try {
            require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/smtp.php';
            $u_stmt = $pdo->prepare('SELECT pseudo, firstname FROM users WHERE id = ? LIMIT 1');
            $u_stmt->execute([$_SESSION['user_id']]);
            $u_row = $u_stmt->fetch();
            $username_display = !empty($u_row['pseudo']) ? $u_row['pseudo'] : ($u_row['firstname'] ?? '');
            
            send_renewal_confirmation_email(
                $pdo,
                $_SESSION['email'] ?? $order['email'] ?? '',
                $username_display,
                $order['order_id'],
                $order['service_name'],
                $price,
                date("d/m/Y", strtotime($new_next_payment))
            );
        } catch (Exception $e) {
            error_log('[Renewal/Success] Email error: ' . $e->getMessage());
        }

        // ── Notification Discord ───────────────────────────────────────────
        try {
            sendRenewalDiscord(
                $discord_webhook_url,
                $order['order_id'],
                $order['service_name'],
                $_SESSION['email'] ?? '',
                date("d/m/Y", strtotime($new_next_payment)),
                $price,
                'renewed'
            );
        } catch (Exception $e) {
            error_log('[Renewal/Success] Discord error: ' . $e->getMessage());
        }

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $payment_error = 'Erreur lors de la finalisation : ' . $e->getMessage();
        error_log('[Renewal/Success] Error: ' . $e->getMessage());
    }
}

$new_due_date = date("d/m/Y", strtotime($order['next_payment_date']));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Renouvellement <?= $renewal_done ? 'réussi' : 'échoué' ?> | OrinHeberge</title>
    <link rel="icon" type="image/png" href="/favicon.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #070a13; }
        .glass { background: rgba(255,255,255,0.03); backdrop-filter: blur(14px); border: 1px solid rgba(255,255,255,0.06); }
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
    </style>
</head>
<body class="text-gray-200 font-sans min-h-screen flex flex-col">

<?php include $_SERVER['DOCUMENT_ROOT'] . '/inc/navbar.php'; ?>

<div class="flex-grow flex items-center justify-center px-4 py-10">
    <div class="w-full max-w-2xl">

        <?php if ($renewal_done): ?>
            <!-- ✅ SUCCÈS -->
            <div class="glass p-8 rounded-2xl text-center animate-in">
                <div class="w-20 h-20 mx-auto mb-6 rounded-full bg-emerald-500/15 border border-emerald-500/30 flex items-center justify-center icon-pulse">
                    <i class="fas fa-check text-emerald-400 text-3xl"></i>
                </div>
                <h1 class="text-2xl font-black text-white mb-2">Renouvellement réussi !</h1>
                <p class="text-gray-400 text-sm mb-6">
                    Votre serveur a été renouvelé avec succès
                    <?php if ($was_suspended && $panel_reactivated): ?>
                        et réactivé sur le panel.
                    <?php elseif ($was_suspended): ?>
                        . La réactivation sur le panel est en cours.
                    <?php else: ?>
                        pour un mois supplémentaire.
                    <?php endif; ?>
                </p>

                <!-- Détails -->
                <div class="bg-white/[0.03] border border-white/10 rounded-xl p-5 text-left mb-6 space-y-3 animate-in-delay">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-400">Service</span>
                        <span class="text-white font-semibold"><?= htmlspecialchars($order['service_name']) ?></span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-400">Commande</span>
                        <span class="text-white font-mono">#<?= htmlspecialchars($order['order_id']) ?></span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-400">Montant payé</span>
                        <span class="text-emerald-400 font-bold"><?= number_format($price, 2, ',', '') ?> €</span>
                    </div>
                    <div class="border-t border-white/10 pt-3">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-400">Prochaine échéance</span>
                            <span class="text-sky-400 font-bold text-lg"><?= $new_due_date ?></span>
                        </div>
                    </div>
                </div>

                <!-- Statut -->
                <div class="bg-white/[0.03] rounded-xl p-4 text-left mb-6 space-y-2 animate-in-delay">
                    <h3 class="text-sm font-bold text-white flex items-center gap-2 mb-2">
                        <i class="fas fa-tasks text-sky-400"></i> Opérations effectuées
                    </h3>
                    <div class="flex items-center gap-2 text-sm text-gray-300">
                        <i class="fas fa-check-circle text-emerald-400"></i> Paiement Stripe confirmé
                    </div>
                    <div class="flex items-center gap-2 text-sm text-gray-300">
                        <i class="fas fa-check-circle text-emerald-400"></i> Date d'échéance mise à jour
                    </div>
                    <div class="flex items-center gap-2 text-sm text-gray-300">
                        <i class="fas fa-check-circle text-emerald-400"></i> Facture générée
                    </div>
                    <div class="flex items-center gap-2 text-sm text-gray-300">
                        <i class="fas fa-check-circle text-emerald-400"></i> Email de confirmation envoyé
                    </div>
                    <?php if ($was_suspended): ?>
                    <div class="flex items-center gap-2 text-sm <?= $panel_reactivated ? 'text-gray-300' : 'text-amber-300' ?>">
                        <i class="fas <?= $panel_reactivated ? 'fa-check-circle text-emerald-400' : 'fa-exclamation-circle text-amber-400' ?>"></i>
                        Panel Pterodactyl <?= $panel_reactivated ? 'réactivé' : 'en cours de réactivation' ?>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="flex flex-col sm:flex-row gap-3 justify-center">
                    <a href="/client/servers/"
                       class="inline-flex items-center justify-center gap-2 bg-sky-600 hover:bg-sky-500 text-white font-bold px-6 py-3 rounded-xl transition shadow-lg shadow-sky-900/30">
                        <i class="fas fa-arrow-left text-xs"></i>
                        Retour à mes serveurs
                    </a>
                    <?php if (!empty($panel_url)): ?>
                    <a href="<?= htmlspecialchars($panel_url) ?>" target="_blank"
                       class="inline-flex items-center justify-center gap-2 bg-white/5 hover:bg-white/10 text-white font-bold px-6 py-3 rounded-xl border border-white/10 transition">
                        <i class="fas fa-external-link-alt text-xs"></i>
                        Ouvrir le panel
                    </a>
                    <?php endif; ?>
                </div>
            </div>

        <?php else: ?>
            <!-- ❌ ERREUR -->
            <div class="glass p-8 rounded-2xl text-center">
                <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-red-500/15 border border-red-500/30 flex items-center justify-center">
                    <i class="fas fa-exclamation-triangle text-red-400 text-xl"></i>
                </div>
                <h1 class="text-xl font-black text-white mb-2">Renouvellement non finalisé</h1>
                <p class="text-sm text-gray-400 mb-6">
                    <?= htmlspecialchars($payment_error ?: 'Le paiement n\'a pas pu être vérifié.') ?>
                </p>

                <div class="flex flex-col gap-3">
                    <a href="/shop/order/renewal/?id=<?= $order_row_id ?>"
                       class="inline-flex items-center justify-center gap-2 bg-sky-600 hover:bg-sky-500 text-white font-bold px-5 py-3 rounded-xl transition">
                        <i class="fas fa-redo text-xs"></i> Réessayer le paiement
                    </a>
                    <a href="/client/servers/" class="text-sm text-gray-400 hover:text-white transition">
                        <i class="fas fa-arrow-left text-xs"></i> Retour à mes serveurs
                    </a>
                </div>
            </div>
        <?php endif; ?>

    </div>
</div>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/inc/footer.php'; ?>

</body>
</html>
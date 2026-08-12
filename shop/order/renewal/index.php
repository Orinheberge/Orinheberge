<?php
/**
 * /shop/order/renewal/ — Page de validation avant paiement
 * Affiche le récapitulatif et propose le choix du moyen de paiement.
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
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/security.php';

// Récupérer la commande à renouveler
$order_row_id = (int)($_GET['id'] ?? 0);
if (!$order_row_id) {
    header('Location: /client/servers/');
    exit();
}

$stmt = $pdo->prepare("SELECT * FROM orders WHERE id=? AND user_id=?");
$stmt->execute([$order_row_id, $_SESSION['user_id']]);
$order = $stmt->fetch();

if (!$order) {
    die("Commande introuvable ou accès refusé.");
}

$price = (float)$order['renewal_price'];

// PayPal.me config
$ext_settings_raw = $pdo->query("SELECT e.slug, es.key, es.value FROM extension_settings es JOIN extensions e ON e.id = es.extension_id")->fetchAll();
$ext_cfg = [];
foreach ($ext_settings_raw as $r) $ext_cfg[$r['slug']][$r['key']] = $r['value'];
$paypalme_username = $ext_cfg['paypal']['username'] ?? 'metal544002009';
$paypalme_url = "https://paypal.me/" . $paypalme_username . "/" . number_format($price, 2, '.', '');

$due_date   = date("d/m/Y", strtotime($order['next_payment_date']));
$is_expired = $order['next_payment_date'] < date("Y-m-d");

// Stocker l'ID pour le checkout
$_SESSION['current_renewal_order_id'] = $order_row_id;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Renouvellement — <?= htmlspecialchars($order['service_name']) ?> | OrinHeberge</title>
    <link rel="icon" type="image/png" href="/favicon.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #070a13; }
        .glass {
            background: rgba(255,255,255,0.03);
            backdrop-filter: blur(14px);
            border: 1px solid rgba(255,255,255,0.06);
        }
    </style>
</head>
<body class="text-gray-200 font-sans min-h-screen flex flex-col">

<?php include $_SERVER['DOCUMENT_ROOT'] . '/inc/navbar.php'; ?>

<div class="flex-grow flex items-center justify-center px-4 py-10">
    <div class="w-full max-w-2xl">

        <!-- En-tête -->
        <div class="text-center mb-8">
            <div class="w-16 h-16 <?= $is_expired ? 'bg-red-500/10 border-red-500/30 text-red-400' : 'bg-amber-500/10 border-amber-500/30 text-amber-400' ?> border rounded-full flex items-center justify-center mx-auto mb-4 text-2xl">
                <i class="fas <?= $is_expired ? 'fa-circle-xmark' : 'fa-rotate' ?>"></i>
            </div>
            <h1 class="text-3xl font-black tracking-tight text-white mb-2">
                <?= $is_expired ? 'Serveur expiré' : 'Renouvellement requis' ?>
            </h1>
            <p class="text-gray-500 text-sm">
                <?= $is_expired
                    ? 'Votre serveur a expiré le <span class="text-red-400 font-bold">' . $due_date . '</span>. Renouvelez pour le réactiver.'
                    : 'Votre serveur expire le <span class="text-amber-400 font-bold">' . $due_date . '</span>.' ?>
            </p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            <!-- Récap -->
            <div class="glass p-6 rounded-2xl">
                <h2 class="text-sm font-bold text-white mb-4 flex items-center gap-2">
                    <i class="fas fa-receipt text-sky-400"></i> Récapitulatif
                </h2>
                <div class="space-y-2.5 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-400">Service</span>
                        <span class="font-bold text-white"><?= htmlspecialchars($order['service_name'], ENT_QUOTES) ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">Commande</span>
                        <span class="font-mono text-sky-400">#<?= htmlspecialchars($order['order_id']) ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">Identifiant panel</span>
                        <span class="font-mono text-gray-300"><?= htmlspecialchars($order['id_server_panel'] ?? '—') ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">Échéance</span>
                        <span class="<?= $is_expired ? 'text-red-400' : 'text-amber-400' ?> font-bold"><?= $due_date ?></span>
                    </div>
                    <div class="border-t border-white/10 pt-2.5 mt-2.5">
                        <div class="flex justify-between items-baseline">
                            <span class="text-gray-400">Montant</span>
                            <div>
                                <span class="text-2xl font-black text-white"><?= number_format($price, 2, ',', '') ?>€</span>
                                <span class="text-xs text-gray-500">/mois</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Choix du moyen de paiement -->
            <div class="glass p-6 rounded-2xl">
                <h2 class="text-sm font-bold text-white mb-4 flex items-center gap-2">
                    <i class="fas fa-wallet text-sky-400"></i> Mode de paiement
                </h2>

                <div class="space-y-3">
                    <!-- Stripe -->
                    <a href="/shop/order/renewal/checkout/?id=<?= $order_row_id ?>"
                       class="flex items-center gap-3 bg-[#635BFF] hover:bg-[#4F46E5] text-white p-4 rounded-xl font-bold transition shadow-lg transform hover:-translate-y-0.5">
                        <i class="fas fa-credit-card text-xl"></i>
                        <div class="flex-1 text-left">
                            <div>Carte bancaire</div>
                            <div class="text-xs font-normal opacity-80">Stripe • Renouvellement immédiat</div>
                        </div>
                        <i class="fas fa-arrow-right"></i>
                    </a>

                    <!-- PayPal -->
                    <a href="<?= htmlspecialchars($paypalme_url) ?>"
                       target="_blank"
                       class="flex items-center gap-3 bg-[#003087] hover:bg-[#001f5a] text-white p-4 rounded-xl font-bold transition shadow-lg transform hover:-translate-y-0.5">
                        <i class="fab fa-paypal text-xl"></i>
                        <div class="flex-1 text-left">
                            <div>PayPal.me</div>
                            <div class="text-xs font-normal opacity-80">Réactivation manuelle sous 24h</div>
                        </div>
                        <i class="fas fa-external-link-alt text-sm"></i>
                    </a>
                </div>

                <div class="mt-4 p-3 rounded-xl bg-blue-500/5 border border-blue-500/10 text-xs text-gray-400 flex gap-2">
                    <i class="fas fa-circle-info text-blue-400 mt-0.5 shrink-0"></i>
                    <span>Pour PayPal.me, indiquez <strong class="text-white">#<?= htmlspecialchars($order['order_id']) ?></strong> en référence du paiement.</span>
                </div>
            </div>
        </div>

        <div class="text-center mt-6">
            <a href="/client/servers/" class="inline-flex items-center gap-2 text-sm text-gray-500 hover:text-gray-300 transition">
                <i class="fas fa-arrow-left text-xs"></i> Retour à mes serveurs
            </a>
        </div>

    </div>
</div>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/inc/footer.php'; ?>

</body>
</html>
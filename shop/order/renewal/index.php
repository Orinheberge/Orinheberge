<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: /login/");
    exit();
}

require_once __DIR__ . '/../lib/panel/panel.php';
require_once __DIR__ . '/../lib/stripe/stripe.php';
require_once __DIR__ . '/../lib/paypal/paypal.php';
require_once __DIR__ . '/../lib/renewal/renewal.php';
require_once __DIR__ . '/../webhook/discord.php';

$discord_webhook_url = "https://discord.com/api/webhooks/1505677242527649872/jFoANIv3OKNtGMib4bViJ79ltRDsf0LJviq59yXwW5hrqZ0uTyU1Yx3nV88yy6rG2eA4";
$stripe_secret_key   = "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";
$paypalme_username   = "metal544002009";

$pdo = new PDO(
    "mysql:host=85.9.203.227;dbname=s43_orinheberge;charset=utf8mb4",
    "orinstone", "1504",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

// Récupérer la commande à renouveler
$order_row_id = (int)($_GET['id'] ?? 0);
if (!$order_row_id) die("Commande introuvable.");

$stmt = $pdo->prepare("SELECT * FROM orders WHERE id=? AND user_id=?");
$stmt->execute([$order_row_id, $_SESSION['user_id']]);
$order = $stmt->fetch();
if (!$order) die("Commande introuvable ou accès refusé.");

$price = (float)$order['renewal_price'];

/*
|--------------------------------------------------------------------------
| RETOUR STRIPE
|--------------------------------------------------------------------------
*/

if (isset($_GET['session_id'])) {
    $session = getStripeSession($stripe_secret_key, $_GET['session_id']);

    if (($session['payment_status'] ?? '') !== 'paid') {
        die("❌ Paiement non confirmé.");
    }

    renewOrder($pdo, $order_row_id, $_GET['session_id']);

    sendRenewalDiscord(
        $discord_webhook_url,
        $order['order_id'],
        $order['service_name'],
        $_SESSION['email'] ?? '',
        date("d/m/Y", strtotime($order['next_payment_date'])),
        $price,
        'renewed'
    );

    $_SESSION['renewal_success'] = true;
    header("Location: /client/servers/");
    exit();
}

/*
|--------------------------------------------------------------------------
| GÉNÉRATION LIENS PAIEMENT
|--------------------------------------------------------------------------
*/

$stripe_session = createStripeSession(
    $stripe_secret_key,
    ['name' => "Renouvellement " . $order['service_name'], 'price' => $price],
    'renewal',
    "https://heberge.orinstone.deepstone.fr/shop/order/renewal/?id=" . $order_row_id . "&session_id={CHECKOUT_SESSION_ID}",
    "https://heberge.orinstone.deepstone.fr/client/servers/"
);
$stripe_url   = $stripe_session['checkout_url'];
$paypalme_url = getPaypalMeLink($paypalme_username, $price);

$due_date     = date("d/m/Y", strtotime($order['next_payment_date']));
$is_expired   = $order['next_payment_date'] < date("Y-m-d");
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orinheberge | Renouvellement</title>
    <link rel="icon" type="image/png" href="https://heberge.orinstone.deepstone.fr/favicon.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #070a13; }
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
</head>
<body class="text-gray-200 font-sans min-h-screen flex flex-col items-center justify-center px-4 py-10">

    <div class="glass p-8 sm:p-10 rounded-2xl w-full max-w-xl text-center shadow-2xl">

        <!-- Icône -->
        <div class="w-16 h-16 <?= $is_expired ? 'bg-red-500/10 border-red-500/30 text-red-400' : 'bg-amber-500/10 border-amber-500/30 text-amber-400' ?> border rounded-full flex items-center justify-center mx-auto mb-6 text-2xl">
            <i class="fas <?= $is_expired ? 'fa-circle-xmark' : 'fa-rotate' ?>"></i>
        </div>

        <h1 class="text-3xl font-black tracking-tight mb-1">
            <?= $is_expired ? 'Serveur expiré' : 'Renouvellement requis' ?>
        </h1>
        <p class="text-gray-500 text-sm mb-6">
            <?= $is_expired
                ? 'Votre serveur a expiré le <span class="text-red-400 font-bold">' . $due_date . '</span>. Renouvelez pour le réactiver.'
                : 'Votre serveur expire le <span class="text-amber-400 font-bold">' . $due_date . '</span>.' ?>
        </p>

        <!-- Récap -->
        <div class="bg-white/5 border border-white/[0.05] p-4 rounded-xl text-left mb-6 space-y-2">
            <div class="flex justify-between text-sm">
                <span class="text-gray-400">Service</span>
                <span class="font-bold"><?= htmlspecialchars($order['service_name'], ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-gray-400">Identifiant</span>
                <span class="font-mono text-sky-400"><?= htmlspecialchars($order['id_server_panel'] ?? '—', ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-gray-400">Échéance</span>
                <span class="<?= $is_expired ? 'text-red-400' : 'text-amber-400' ?> font-bold"><?= $due_date ?></span>
            </div>
            <hr class="border-white/10">
            <div class="flex justify-between">
                <span class="text-gray-400 text-sm">Montant</span>
                <span class="text-xl font-black text-white"><?= number_format($price, 2, '.', '') ?>€<span class="text-xs text-gray-500">/mois</span></span>
            </div>
        </div>

        <!-- Boutons paiement -->
        <div class="space-y-3">
            <a href="<?= htmlspecialchars($stripe_url, ENT_QUOTES, 'UTF-8') ?>"
               class="flex items-center justify-center gap-3 bg-[#635BFF] hover:bg-[#4F46E5] text-white p-4 rounded-xl font-bold transition shadow-lg transform hover:-translate-y-0.5">
                <i class="fas fa-credit-card text-xl"></i>
                Renouveler par carte — Stripe (<?= number_format($price, 2, '.', '') ?>€)
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
                Renouveler par PayPal.me (<?= number_format($price, 2, '.', '') ?>€)
            </a>
        </div>

        <div class="mt-6 p-3 rounded-xl bg-blue-500/5 border border-blue-500/10 text-xs text-gray-400 text-left flex gap-2">
            <i class="fas fa-circle-info text-blue-400 mt-0.5 shrink-0"></i>
            <span>Pour PayPal.me, indiquez <strong class="text-white">#<?= htmlspecialchars($order['order_id'], ENT_QUOTES, 'UTF-8') ?></strong> en référence. Réactivation manuelle sous 24h.</span>
        </div>

        <a href="/client/servers/" class="mt-4 inline-flex items-center gap-2 text-sm text-gray-500 hover:text-gray-300 transition">
            <i class="fas fa-arrow-left text-xs"></i> Retour à mes serveurs
        </a>
    </div>

</body>
</html>

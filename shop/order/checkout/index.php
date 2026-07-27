<?php
ini_set('display_errors', 0); // Désactiver l'affichage des erreurs en prod
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/security.php'; // En-têtes de sécurité (CSP) — doit être avant tout output
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: /login/");
    exit();
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/db.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/lang.php';

// Récupérer les clés Stripe via extension_settings
$ext_settings_raw = $pdo->query("SELECT e.slug, es.key, es.value FROM extension_settings es JOIN extensions e ON e.id = es.extension_id")->fetchAll();
$ext_cfg = [];
foreach ($ext_settings_raw as $r) $ext_cfg[$r['slug']][$r['key']] = $r['value'];
$stripe_secret_key = $ext_cfg['stripe']['secret_key'] ?? '';
$stripe_public_key = $ext_cfg['stripe']['public_key'] ?? '';

if (empty($stripe_secret_key) || empty($stripe_public_key)) {
    die("Configuration Stripe manquante.");
}

// Vérifier que l'utilisateur a bien une commande en attente
$order_id = $_GET['order_id'] ?? $_SESSION['current_pending_order_id'] ?? null;
if (!$order_id) {
    header("Location: /shop/cart/");
    exit();
}

$stmt = $pdo->prepare("SELECT * FROM orders WHERE order_id = ? AND user_id = ? AND status = 'pending' LIMIT 1");
$stmt->execute([$order_id, $_SESSION['user_id']]);
$order = $stmt->fetch();

if (!$order) {
    unset($_SESSION['current_pending_order_id']);
    header("Location: /shop/cart/");
    exit();
}

// URL de retour absolue, utilisée par Stripe pour les méthodes à redirection (PayPal, Revolut Pay...)
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$return_url = $scheme . '://' . $_SERVER['HTTP_HOST'] . '/shop/order/success/';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paiement Sécurisé | OrinHeberge</title>
    <script src="https://js.stripe.com/v3/"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #070a13; }
        .glass { background: rgba(255,255,255,0.03); backdrop-filter: blur(14px); border: 1px solid rgba(255,255,255,0.06); }
        #payment-element { min-height: 220px; }
        #payment-loading { display:flex; align-items:center; justify-content:center; min-height:220px; color:#64748b; font-size:13px; gap:10px; }
        .spinner-sm { width:18px; height:18px; border:2px solid rgba(255,255,255,0.1); border-top-color:#38bdf8; border-radius:50%; animation:spin 0.8s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body class="text-gray-200 font-sans min-h-screen flex flex-col">

<?php include $_SERVER['DOCUMENT_ROOT'] . '/inc/navbar.php'; ?>

<div class="flex-grow flex items-center justify-center px-4 py-8">
    <div class="w-full max-w-4xl grid grid-cols-1 lg:grid-cols-5 gap-6">

        <!-- Récapitulatif de la commande -->
        <div class="lg:col-span-2 glass p-6 rounded-2xl h-fit">
            <h2 class="text-lg font-bold text-white mb-4 flex items-center gap-2">
                <i class="fas fa-receipt text-sky-400"></i> Récapitulatif
            </h2>
            <div class="space-y-3 mb-4">
                <div class="flex justify-between text-sm">
                    <span class="text-gray-400">Commande</span>
                    <span class="text-white font-mono">#<?= htmlspecialchars($order['order_id']) ?></span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-400">Service</span>
                    <span class="text-white text-right"><?= htmlspecialchars($order['service_name']) ?></span>
                </div>
                <div class="border-t border-white/10 my-3"></div>
                <div class="flex justify-between text-lg font-bold">
                    <span class="text-white">Total à payer</span>
                    <span class="text-sky-400"><?= number_format((float)$order['renewal_price'], 2, '.', '') ?> €</span>
                </div>
            </div>
            <a href="/shop/order/?cancel=1" class="block text-center text-xs text-gray-500 hover:text-red-400 transition mt-4">
                <i class="fas fa-times mr-1"></i> Annuler et retourner au panier
            </a>
        </div>

        <!-- Formulaire de paiement -->
        <div class="lg:col-span-3 glass p-6 rounded-2xl">
            <h2 class="text-lg font-bold text-white mb-6 flex items-center gap-2">
                <i class="fas fa-lock text-emerald-400"></i> Paiement Sécurisé
            </h2>

            <form id="checkout-form" class="space-y-5">
                <input type="hidden" name="order_id" value="<?= htmlspecialchars($order['order_id']) ?>">

                <!-- Stripe monte ici carte / Apple Pay / Google Pay / Revolut Pay / PayPal automatiquement -->
                <div id="payment-loading">
                    <div class="spinner-sm"></div>
                    <span>Chargement des moyens de paiement...</span>
                </div>
                <div id="payment-element" class="hidden"></div>

                <div id="card-errors" role="alert" class="text-red-400 text-xs mt-2 hidden">
                    <i class="fas fa-exclamation-circle mr-1"></i><span class="error-message"></span>
                </div>

                <button type="submit" id="pay-btn" disabled class="w-full flex items-center justify-center gap-3 bg-gradient-to-r from-sky-600 to-indigo-600 hover:from-sky-500 hover:to-indigo-500 text-white p-4 rounded-xl font-bold transition shadow-lg shadow-sky-600/20 disabled:opacity-50 disabled:cursor-not-allowed">
                    <i class="fas fa-shield-alt"></i>
                    <span id="pay-btn-label">Payer <?= number_format((float)$order['renewal_price'], 2, '.', '') ?> €</span>
                </button>
            </form>
        </div>
    </div>
</div>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/inc/footer.php'; ?>

<script>
const stripe = Stripe('<?= htmlspecialchars($stripe_public_key) ?>');
const orderId = document.querySelector('input[name="order_id"]').value;
const returnUrl = '<?= htmlspecialchars($return_url, ENT_QUOTES) ?>';
const payAmountLabel = '<?= number_format((float)$order["renewal_price"], 2, ".", "") ?> €';

let elements;

function showError(msg) {
    const errorDiv = document.getElementById('card-errors');
    errorDiv.querySelector('.error-message').textContent = msg;
    errorDiv.classList.remove('hidden');
}

function setLoading(isLoading) {
    const btn = document.getElementById('pay-btn');
    const label = document.getElementById('pay-btn-label');
    btn.disabled = isLoading;
    label.innerHTML = isLoading
        ? '<i class="fas fa-spinner fa-spin"></i> Traitement en cours...'
        : 'Payer ' + payAmountLabel;
}

async function initialize() {
    try {
        // Création du PaymentIntent côté serveur (active automatiquement carte,
        // Apple Pay, Google Pay, Revolut Pay et PayPal selon ce qui est activé
        // sur le compte Stripe)
        const resp = await fetch('/shop/order/checkout/process.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ order_id: orderId })
        });
        const data = await resp.json();
        if (data.error) {
            showError(data.error);
            return;
        }

        elements = stripe.elements({
            clientSecret: data.client_secret,
            appearance: {
                theme: 'night',
                variables: {
                    colorBackground: 'rgba(255,255,255,0.02)',
                    colorPrimary: '#38bdf8',
                    colorText: '#e2e8f0',
                    colorDanger: '#f87171',
                    borderRadius: '12px',
                }
            }
        });

        const paymentElement = elements.create('payment', {
            layout: 'tabs'
        });
        paymentElement.mount('#payment-element');

        paymentElement.on('ready', () => {
            document.getElementById('payment-loading').classList.add('hidden');
            document.getElementById('payment-element').classList.remove('hidden');
            document.getElementById('pay-btn').disabled = false;
        });

        paymentElement.on('change', (event) => {
            const errorDiv = document.getElementById('card-errors');
            if (event.error) {
                showError(event.error.message);
            } else {
                errorDiv.classList.add('hidden');
            }
        });
    } catch (err) {
        showError('Impossible de charger le paiement. Merci de réessayer.');
    }
}

initialize();

document.getElementById('checkout-form').addEventListener('submit', async function (e) {
    e.preventDefault();
    if (!elements) return;
    setLoading(true);

    // Stripe redirige automatiquement vers returnUrl à la fin, que ce soit
    // pour une carte (parfois pas de redirection nécessaire) ou pour une
    // méthode type PayPal/Revolut Pay (redirection obligatoire).
    const { error } = await stripe.confirmPayment({
        elements,
        confirmParams: {
            return_url: returnUrl,
        },
    });

    // On n'arrive ici que si une erreur immédiate survient (ex: carte refusée
    // avant même la redirection). Sinon le navigateur a déjà quitté la page.
    if (error) {
        showError(error.message);
        setLoading(false);
    }
});
</script>
</body>
</html>
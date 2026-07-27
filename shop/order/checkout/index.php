<?php
ini_set('display_errors', 0);
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: /login/");
    exit();
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/db.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/lang.php';

$ext_settings_raw = $pdo->query("SELECT e.slug, es.key, es.value FROM extension_settings es JOIN extensions e ON e.id = es.extension_id")->fetchAll();
$ext_cfg = [];
foreach ($ext_settings_raw as $r) $ext_cfg[$r['slug']][$r['key']] = $r['value'];

$stripe_secret_key = $ext_cfg['stripe']['secret_key'] ?? '';
$stripe_public_key = $ext_cfg['stripe']['public_key'] ?? '';
$paypalme_username = $ext_cfg['paypal']['username'] ?? 'metal544002009';

if (empty($stripe_secret_key) || empty($stripe_public_key)) {
    die("Configuration Stripe manquante.");
}

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

$user_stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$user_stmt->execute([$_SESSION['user_id']]);
$user = $user_stmt->fetch();

$amount_cents = (int)round((float)$order['renewal_price'] * 100);
$paypal_amount = number_format((float)$order['renewal_price'], 2, '.', '');
$paypal_link = "https://paypal.me/{$paypalme_username}/{$paypal_amount}";
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Security-Policy" content="default-src 'self'; script-src 'self' https://js.stripe.com https://m.stripe.network blob:; connect-src 'self' https://api.stripe.com https://m.stripe.network; frame-src 'self' https://js.stripe.com https://m.stripe.network; img-src 'self' https://*.stripe.com data:; style-src 'self' 'unsafe-inline';">
    <title>Paiement Sécurisé | OrinHeberge</title>
    <script src="https://js.stripe.com/v3/"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #070a13; }
        .glass { background: rgba(255,255,255,0.03); backdrop-filter: blur(14px); border: 1px solid rgba(255,255,255,0.06); }
        /* Style personnalisé pour le Payment Element de Stripe */
        .StripeElement { background: rgba(255,255,255,0.02); border-radius: 0.75rem; padding: 1rem; }
    </style>
</head>
<body class="text-gray-200 font-sans min-h-screen flex flex-col">

<?php include $_SERVER['DOCUMENT_ROOT'] . '/inc/navbar.php'; ?>

<div class="flex-grow flex items-center justify-center px-4 py-8">
    <div class="w-full max-w-4xl grid grid-cols-1 lg:grid-cols-5 gap-6">
        
        <!-- Récapitulatif -->
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

        <!-- Formulaire de paiement Stripe Payment Element -->
        <div class="lg:col-span-3 glass p-6 rounded-2xl">
            <h2 class="text-lg font-bold text-white mb-6 flex items-center gap-2">
                <i class="fas fa-lock text-emerald-400"></i> Moyen de paiement
            </h2>

            <form id="payment-form" class="space-y-6">
                <div id="payment-element">
                    <!-- Le Payment Element de Stripe sera injecté ici (Cartes, Apple Pay, Google Pay, Revolut) -->
                </div>
                
                <div id="payment-message" class="hidden text-red-400 text-sm bg-red-500/10 border border-red-500/20 p-3 rounded-lg"></div>

                <button type="submit" id="submit-btn" disabled class="w-full flex items-center justify-center gap-3 bg-gradient-to-r from-sky-600 to-indigo-600 hover:from-sky-500 hover:to-indigo-500 text-white p-4 rounded-xl font-bold transition shadow-lg shadow-sky-600/20 disabled:opacity-50 disabled:cursor-not-allowed">
                    <span id="button-text">Payer <?= number_format((float)$order['renewal_price'], 2, '.', '') ?> €</span>
                    <span id="spinner" class="hidden"><i class="fas fa-spinner fa-spin"></i></span>
                </button>
            </form>

            <!-- PayPal.me (Alternative externe) -->
            <div class="mt-6 pt-6 border-t border-white/10">
                <div class="relative flex items-center justify-center my-4">
                    <div class="border-t border-white/10 w-full"></div>
                    <span class="bg-[#070a13] px-3 text-[10px] text-gray-500 absolute">OU</span a>
                </div>
                <a href="<?= htmlspecialchars($paypal_link) ?>" target="_blank"
                   class="flex items-center justify-center gap-3 bg-[#003087] hover:bg-[#001f5a] text-white p-4 rounded-xl font-bold transition shadow-lg transform hover:-translate-y-0.5">
                    <i class="fab fa-paypal text-xl"></i>
                    Payer par PayPal.me (<?= $paypal_amount ?> €)
                </a>
                <div class="mt-3 p-3 rounded-xl bg-blue-500/5 border border-blue-500/10 text-xs text-gray-400 text-left flex gap-2">
                    <i class="fas fa-circle-info text-blue-400 mt-0.5 shrink-0"></i>
                    <span>Pour PayPal.me, indiquez votre email <strong class="text-white"><?= htmlspecialchars($user['email']) ?></strong> ou le n° de commande <strong class="text-white">#<?= htmlspecialchars($order['order_id']) ?></strong> en note. Activation manuelle sous 24h.</span>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/inc/footer.php'; ?>

<script>
const stripe = Stripe('<?= htmlspecialchars($stripe_public_key) ?>');
const options = {
    clientSecret: null, // Sera défini après le fetch
    appearance: {
        theme: 'night',
        variables: { colorPrimary: '#38bdf8', colorBackground: 'rgba(255,255,255,0.02)', colorText: '#ffffff', colorDanger: '#f87171', fontFamily: 'system-ui, sans-serif', spacingUnit: '4px', borderRadius: '12px' }
    }
};

let elements = null;

// 1. Récupérer le PaymentIntent depuis le serveur
fetch('/shop/order/checkout/process.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ order_id: '<?= htmlspecialchars($order['order_id']) ?>' })
})
.then(res => res.json())
.then(data => {
    if (data.error) {
        showMessage(data.error);
        document.getElementById('submit-btn').disabled = true;
        return;
    }
    
    options.clientSecret = data.client_secret;
    elements = stripe.elements(options);
    
    // Monter le Payment Element (il affichera automatiquement Carte, Apple Pay, Google Pay, Revolut Pay)
    const paymentElement = elements.create('payment', {
        layout: { type: 'tabs', defaultCollapsed: false },
        fields: { billingDetails: 'auto' }
    });
    paymentElement.mount('#payment-element');
    
    // Activer le bouton une fois l'élément chargé
    document.getElementById('submit-btn').disabled = false;
})
.catch(error => {
    console.error('Erreur:', error);
    showMessage("Erreur de connexion au serveur de paiement.");
});

// 2. Gestion de la soumission du formulaire
document.getElementById('payment-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    if (!elements) return;
    
    setLoading(true);
    
    // confirmPayment gère automatiquement la redirection 3D Secure pour TOUTES les méthodes (Carte, Apple Pay, Google Pay, Revolut)
    const { error } = await stripe.confirmPayment({
        elements,
        confirmParams: {
            // Stripe remplacera {PAYMENT_INTENT_ID} automatiquement par le vrai ID
            return_url: window.location.origin + '/shop/order/success/?payment_intent={PAYMENT_INTENT_ID}'
        },
    });
    
    if (error) {
        showMessage(error.message);
        setLoading(false);
    }
    // En cas de succès, Stripe redirige automatiquement vers le return_url ci-dessus
});

function showMessage(messageText) {
    const messageContainer = document.getElementById('payment-message');
    messageContainer.textContent = messageText;
    messageContainer.classList.remove('hidden');
}

function setLoading(isLoading) {
    const submitBtn = document.getElementById('submit-btn');
    const spinner = document.getElementById('spinner');
    const buttonText = document.getElementById('button-text');
    
    if (isLoading) {
        submitBtn.disabled = true;
        spinner.classList.remove('hidden');
        buttonText.textContent = 'Traitement en cours...';
    } else {
        submitBtn.disabled = false;
        spinner.classList.add('hidden');
        buttonText.textContent = 'Payer <?= number_format((float)$order['renewal_price'], 2, '.', '') ?> €';
    }
}
</script>
</body>
</html>
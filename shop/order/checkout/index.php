<?php
ini_set('display_errors', 0); // Désactiver l'affichage des erreurs en prod
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: /login/");
    exit();
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/db.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/lang.php';

// Récupérer les clés Stripe via extension_settings (comme dans order/index.php)
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

// Récupérer les infos utilisateur
$user_stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$user_stmt->execute([$_SESSION['user_id']]);
$user = $user_stmt->fetch();

// Récupérer les cartes enregistrées via Stripe API (style cURL comme votre lib)
$saved_cards = [];
if (!empty($user['stripe_customer_id'])) {
    $ch = curl_init("https://api.stripe.com/v1/payment_methods?customer=" . urlencode($user['stripe_customer_id']) . "&type=card&limit=10");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD        => $stripe_secret_key . ":",
    ]);
    $pm_raw = json_decode(curl_exec($ch), true);
    curl_close($ch);
    if (!empty($pm_raw['data'])) {
        $saved_cards = $pm_raw['data'];
    }
}
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
        .StripeElement { background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.1); border-radius: 0.75rem; padding: 0.75rem 1rem; color: white; transition: all 0.2s; }
        .StripeElement--focus { border-color: #38bdf8; box-shadow: 0 0 0 2px rgba(56, 189, 248, 0.2); }
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
                
                <?php if (!empty($saved_cards)): ?>
                <div>
                    <label class="block text-xs font-bold text-gray-400 uppercase tracking-wider mb-3">Cartes enregistrées</label>
                    <div class="space-y-2">
                        <?php foreach ($saved_cards as $index => $pm): 
                            $card = $pm['card'];
                            $is_default = ($index === 0);
                        ?>
                        <label class="flex items-center gap-3 p-3 rounded-xl border transition cursor-pointer <?= $is_default ? 'bg-sky-500/10 border-sky-500/40' : 'bg-white/[0.02] border-white/[0.07] hover:border-white/20' ?>">
                            <input type="radio" name="payment_method" value="<?= htmlspecialchars($pm['id']) ?>" <?= $is_default ? 'checked' : '' ?> class="w-4 h-4 text-sky-500 bg-transparent border-gray-600">
                            <div class="w-8 h-8 rounded-lg bg-white/5 flex items-center justify-center shrink-0">
                                <i class="fab fa-cc-<?= strtolower($card['brand']) ?> text-lg <?= $card['brand'] === 'Visa' ? 'text-blue-400' : ($card['brand'] === 'Mastercard' ? 'text-red-400' : 'text-gray-400') ?>"></i>
                            </div>
                            <div class="flex-1">
                                <div class="text-sm font-semibold text-white">•••• •••• •••• <?= htmlspecialchars($card['last4']) ?></div>
                                <div class="text-[10px] text-gray-500"><?= htmlspecialchars($card['brand']) ?> • Exp: <?= str_pad($card['exp_month'], 2, '0', STR_PAD_LEFT) ?>/<?= substr($card['exp_year'], -2) ?></div>
                            </div>
                        </label>
                        <?php endforeach; ?>
                    </div>
                    <div class="relative flex items-center justify-center my-4">
                        <div class="border-t border-white/10 w-full"></div>
                        <span class="bg-[#070a13] px-3 text-[10px] text-gray-500 absolute">OU</span>
                    </div>
                </div>
                <?php endif; ?>

                <div>
                    <label class="flex items-center gap-3 cursor-pointer mb-3">
                        <input type="radio" name="payment_method" value="new_card" <?= empty($saved_cards) ? 'checked' : '' ?> class="w-4 h-4 text-sky-500 bg-transparent border-gray-600">
                        <span class="text-sm font-semibold text-white"><i class="fas fa-plus-circle text-sky-400 mr-1"></i> Nouvelle carte bancaire</span>
                    </label>
                    <div id="new-card-container" class="<?= !empty($saved_cards) ? 'hidden' : '' ?>">
                        <div id="card-element" class="StripeElement"></div>
                        <div id="card-errors" role="alert" class="text-red-400 text-xs mt-2 hidden">
                            <i class="fas fa-exclamation-circle mr-1"></i><span class="error-message"></span>
                        </div>
                    </div>
                </div>

                <button type="submit" id="pay-btn" class="w-full flex items-center justify-center gap-3 bg-gradient-to-r from-sky-600 to-indigo-600 hover:from-sky-500 hover:to-indigo-500 text-white p-4 rounded-xl font-bold transition shadow-lg shadow-sky-600/20 disabled:opacity-50 disabled:cursor-not-allowed">
                    <i class="fas fa-shield-alt"></i>
                    Payer <?= number_format((float)$order['renewal_price'], 2, '.', '') ?> €
                </button>
            </form>
        </div>
    </div>
</div>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/inc/footer.php'; ?>

<script>
const stripe = Stripe('<?= htmlspecialchars($stripe_public_key) ?>');
const elements = stripe.elements();

const cardElement = elements.create('card', {
    style: { base: { color: '#ffffff', fontSize: '14px', '::placeholder': { color: '#64748b' }, backgroundColor: 'transparent' }, invalid: { color: '#f87171' } },
    hidePostalCode: true
});

const paymentRadios = document.querySelectorAll('input[name="payment_method"]');
const newCardContainer = document.getElementById('new-card-container');
let cardMounted = false;

paymentRadios.forEach(radio => {
    radio.addEventListener('change', function() {
        if (this.value === 'new_card') {
            newCardContainer.classList.remove('hidden');
            if (!cardMounted) { cardElement.mount('#card-element'); cardMounted = true; }
        } else {
            newCardContainer.classList.add('hidden');
        }
    });
});

<?php if (empty($saved_cards)): ?>
cardElement.mount('#card-element');
cardMounted = true;
<?php endif; ?>

if (cardMounted) {
    cardElement.on('change', function(event) {
        const errorDiv = document.getElementById('card-errors');
        if (event.error) {
            errorDiv.querySelector('.error-message').textContent = event.error.message;
            errorDiv.classList.remove('hidden');
        } else {
            errorDiv.classList.add('hidden');
        }
    });
}

document.getElementById('checkout-form').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = document.getElementById('pay-btn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Traitement en cours...';
    
    const selectedMethod = document.querySelector('input[name="payment_method"]:checked').value;
    const orderId = document.querySelector('input[name="order_id"]').value;
    
    try {
        // 1. Créer le PaymentIntent côté serveur
        const intentResp = await fetch('/shop/order/checkout/process.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ order_id: orderId, payment_method_id: selectedMethod !== 'new_card' ? selectedMethod : null })
        });
        const intentData = await intentResp.json();
        if (intentData.error) throw new Error(intentData.error);
        
        // 2. Confirmer le paiement
        let result;
        if (selectedMethod === 'new_card') {
            result = await stripe.confirmCardPayment(intentData.client_secret, { payment_method: { card: cardElement } });
        } else {
            result = await stripe.confirmCardPayment(intentData.client_secret, { payment_method: selectedMethod });
        }
        
        if (result.error) throw new Error(result.error.message);
        
        // 3. Succès → Redirection vers la page de succès existante
        if (result.paymentIntent.status === 'succeeded') {
            window.location.href = '/shop/order/success/?payment_intent=' + result.paymentIntent.id;
        }
    } catch (err) {
        const errorDiv = document.getElementById('card-errors');
        errorDiv.querySelector('.error-message').textContent = err.message;
        errorDiv.classList.remove('hidden');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-shield-alt"></i> Payer <?= number_format((float)$order["renewal_price"], 2, ".", "") ?> €';
    }
});
</script>
</body>
</html>
<?php
ini_set('display_errors', 0);
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/security.php'; // Doit être AVANT tout output
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

// ✅ AJOUT : Récupérer l'utilisateur pour obtenir son stripe_customer_id
$user_stmt = $pdo->prepare("SELECT id, stripe_customer_id FROM users WHERE id = ? LIMIT 1");
$user_stmt->execute([$_SESSION['user_id']]);
$user = $user_stmt->fetch();

// ✅ AJOUT : Récupérer le moyen de paiement par défaut enregistré
$default_payment_method_id = '';
if (!empty($user['stripe_customer_id'])) {
    try {
        $ch = curl_init("https://api.stripe.com/v1/payment_methods?customer=" . urlencode($user['stripe_customer_id']) . "&type=card&limit=1");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD        => $stripe_secret_key . ":",
        ]);
        $pm_raw = json_decode(curl_exec($ch), true);
        curl_close($ch);
        
        if (!empty($pm_raw['data'][0]['id'])) {
            $default_payment_method_id = $pm_raw['data'][0]['id'];
        }
    } catch (Exception $e) {
        error_log('[Checkout] Erreur récupération PM: ' . $e->getMessage());
    }
}

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

        <div class="lg:col-span-3 glass p-6 rounded-2xl">
            <h2 class="text-lg font-bold text-white mb-6 flex items-center gap-2">
                <i class="fas fa-lock text-emerald-400"></i> Paiement Sécurisé
            </h2>

            <form id="checkout-form" class="space-y-5">
                <input type="hidden" name="order_id" value="<?= htmlspecialchars($order['order_id']) ?>">

                <div id="payment-loading">
                    <div class="spinner-sm"></div>
                    <span>Chargement des moyens de paiement...</span>
                </div>
                <div id="payment-element" class="hidden"></div>

                <div id="card-errors" role="alert" class="text-red-400 text-xs mt-2 hidden bg-red-500/10 border border-red-500/20 p-3 rounded-lg">
                    <i class="fas fa-exclamation-circle mr-1"></i><span class="error-message"></span>
                </div>

                <label class="flex items-start gap-3 cursor-pointer text-xs text-gray-400 select-none">
                    <input type="checkbox" id="accept-policy" class="mt-0.5 w-4 h-4 rounded border-gray-600 bg-transparent text-sky-500 focus:ring-sky-500 focus:ring-offset-0">
                    <span>
                        J'ai lu et j'accepte la
                        <a href="/politique-paiement/" target="_blank" class="text-sky-400 hover:underline">Politique de Paiement</a>
                        d'OrinHeberge.
                    </span>
                </label>

                <button type="submit" id="pay-btn" disabled class="w-full flex items-center justify-center gap-3 bg-gradient-to-r from-sky-600 to-indigo-600 hover:from-sky-500 hover:to-indigo-500 text-white p-4 rounded-xl font-bold transition shadow-lg shadow-sky-600/20 disabled:opacity-50 disabled:cursor-not-allowed">
                    <i class="fas fa-shield-alt"></i>
                    <span id="pay-btn-label">Payer <?= number_format((float)$order['renewal_price'], 2, '.', '') ?> €</span>
                </button>
            </form>
        </div>
    </div>
</div>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/inc/footer.php'; ?>

<script nonce="<?= CSP_NONCE ?>">
document.addEventListener('DOMContentLoaded', async function() {
    const stripe = Stripe('<?= htmlspecialchars($stripe_public_key) ?>');
    const orderId = document.querySelector('input[name="order_id"]').value;
    const returnUrl = '<?= htmlspecialchars($return_url, ENT_QUOTES) ?>';
    const payAmountLabel = '<?= number_format((float)$order["renewal_price"], 2, ".", "") ?> €';
    
    // ✅ AJOUT : Passer l'ID du moyen de paiement par défaut depuis le PHP
    const defaultPMId = '<?= htmlspecialchars($default_payment_method_id) ?>';

    let elements;
    let paymentReady = false;

    const policyCheckbox = document.getElementById('accept-policy');
    const payBtn = document.getElementById('pay-btn');
    const payBtnLabel = document.getElementById('pay-btn-label');
    const errorDiv = document.getElementById('card-errors');
    const loadingDiv = document.getElementById('payment-loading');
    const elementDiv = document.getElementById('payment-element');

    function updatePayButtonState() {
        payBtn.disabled = !(paymentReady && policyCheckbox.checked);
    }

    policyCheckbox.addEventListener('change', function () {
        updatePayButtonState();
        if (this.checked) {
            fetch('/shop/order/checkout/accept-policy.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ order_id: orderId })
            }).catch(() => {});
        }
    });

    function showError(msg) {
        errorDiv.querySelector('.error-message').textContent = msg;
        errorDiv.classList.remove('hidden');
    }

    function setLoading(isLoading) {
        payBtn.disabled = isLoading;
        payBtnLabel.innerHTML = isLoading
            ? '<i class="fas fa-spinner fa-spin"></i> Traitement en cours...'
            : 'Payer ' + payAmountLabel;
    }

    async function initialize() {
        try {
            const resp = await fetch('/shop/order/checkout/process.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ order_id: orderId })
            });
            
            if (!resp.ok) throw new Error(`Erreur HTTP: ${resp.status}`);
            
            const data = await resp.json();
            if (data.error) {
                showError(data.error);
                loadingDiv.style.display = 'none';
                return;
            }

            // ✅ AJOUT : Utiliser defaultValues pour pré-sélectionner la carte enregistrée
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
                },
                defaultValues: {
                    paymentMethod: defaultPMId || undefined // Ne définit rien si aucune carte n'est enregistrée
                }
            });

            const paymentElement = elements.create('payment', {
                layout: 'tabs'
            });
            
            paymentElement.mount('#payment-element');

            paymentElement.on('ready', () => {
                loadingDiv.style.display = 'none';
                elementDiv.classList.remove('hidden');
                paymentReady = true;
                updatePayButtonState();
            });

            paymentElement.on('change', (event) => {
                if (event.error) {
                    showError(event.error.message);
                } else {
                    errorDiv.classList.add('hidden');
                }
            });
        } catch (err) {
            console.error('Stripe Init Error:', err);
            showError('Impossible de charger le paiement. Vérifiez votre connexion ou réessayez.');
            loadingDiv.style.display = 'none';
        }
    }

    await initialize();

    document.getElementById('checkout-form').addEventListener('submit', async function (e) {
        e.preventDefault();
        if (!elements || !policyCheckbox.checked) {
            showError('Merci d\'accepter la Politique de Paiement avant de continuer.');
            return;
        }

        setLoading(true);

        const { error } = await stripe.confirmPayment({
            elements,
            confirmParams: { return_url: returnUrl },
        });

        if (error) {
            showError(error.message);
            setLoading(false);
        }
    });
});
</script>
</body>
</html>
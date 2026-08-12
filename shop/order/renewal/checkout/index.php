<?php
/**
 * /shop/order/renewal/checkout/ — Page de paiement renouvellement avec Stripe Payment Element
 */
ini_set('display_errors', 0);
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/security.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: /login/");
    exit();
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/db.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/lang.php';

// ═══════════════════════════════════════════
// 1. RÉCUPÉRATION DES CLÉS STRIPE
// ═══════════════════════════════════════════
$ext_settings_raw = $pdo->query("SELECT e.slug, es.key, es.value FROM extension_settings es JOIN extensions e ON e.id = es.extension_id")->fetchAll();
$ext_cfg = [];
foreach ($ext_settings_raw as $r) $ext_cfg[$r['slug']][$r['key']] = $r['value'];
$stripe_secret_key = $ext_cfg['stripe']['secret_key'] ?? '';
$stripe_public_key = $ext_cfg['stripe']['public_key'] ?? '';

if (empty($stripe_secret_key) || empty($stripe_public_key)) {
    die("Configuration Stripe manquante.");
}

// ═══════════════════════════════════════════
// 2. RÉCUPÉRATION DE LA COMMANDE
// ═══════════════════════════════════════════
$order_row_id = (int)($_GET['id'] ?? $_SESSION['current_renewal_order_id'] ?? 0);

if (!$order_row_id) {
    header('Location: /client/servers/');
    exit();
}

$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ? LIMIT 1");
$stmt->execute([$order_row_id, $_SESSION['user_id']]);
$order = $stmt->fetch();

if (!$order) {
    header('Location: /client/servers/');
    exit();
}

$price = (float)$order['renewal_price'];
$due_date = date("d/m/Y", strtotime($order['next_payment_date']));
$is_expired = $order['next_payment_date'] < date("Y-m-d");

// ═══════════════════════════════════════════
// 3. RÉCUPÉRATION MOYEN DE PAIEMENT PAR DÉFAUT
// ═══════════════════════════════════════════
$user_stmt = $pdo->prepare("SELECT id, stripe_customer_id FROM users WHERE id = ? LIMIT 1");
$user_stmt->execute([$_SESSION['user_id']]);
$user = $user_stmt->fetch();

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
        error_log('[Renewal/Checkout] Erreur récupération PM: ' . $e->getMessage());
    }
}

// URLs de retour
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$base_url = $scheme . '://' . $_SERVER['HTTP_HOST'];
$success_url = $base_url . '/shop/order/renewal/success/?order_id=' . $order_row_id;
$cancel_url  = $base_url . '/shop/order/renewal/?id=' . $order_row_id;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paiement renouvellement — <?= htmlspecialchars($order['service_name']) ?> | OrinHeberge</title>
    <script src="https://js.stripe.com/v3/"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
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

        <!-- RÉCAPITULATIF -->
        <div class="lg:col-span-2 glass p-6 rounded-2xl h-fit">
            <h2 class="text-lg font-bold text-white mb-4 flex items-center gap-2">
                <i class="fas fa-rotate text-amber-400"></i> Renouvellement
            </h2>

            <div class="space-y-3 mb-4 text-sm">
                <div class="flex justify-between">
                    <span class="text-gray-400">Commande</span>
                    <span class="text-white font-mono">#<?= htmlspecialchars($order['order_id']) ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-400">Service</span>
                    <span class="text-white font-semibold text-right truncate ml-2"><?= htmlspecialchars($order['service_name']) ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-400">Identifiant</span>
                    <span class="text-gray-300 font-mono text-xs"><?= htmlspecialchars($order['id_server_panel'] ?? '—') ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-400">Échéance</span>
                    <span class="<?= $is_expired ? 'text-red-400' : 'text-amber-400' ?> font-bold"><?= $due_date ?></span>
                </div>

                <div class="border-t border-white/10 my-3"></div>

                <?php if ($order['status'] === 'suspended'): ?>
                <div class="bg-red-500/10 border border-red-500/20 rounded-lg p-3 text-xs text-red-300 flex gap-2">
                    <i class="fas fa-pause-circle mt-0.5"></i>
                    <span>Serveur suspendu. Il sera réactivé automatiquement après paiement.</span>
                </div>
                <?php endif; ?>

                <div class="flex justify-between items-center pt-2">
                    <div>
                        <div class="text-white font-semibold">Total à payer</div>
                        <div class="text-xs text-gray-500">Pour 1 mois supplémentaire</div>
                    </div>
                    <span class="text-2xl font-black text-sky-400"><?= number_format($price, 2, ',', '') ?> €</span>
                </div>
            </div>

            <a href="<?= htmlspecialchars($cancel_url) ?>" class="block text-center text-xs text-gray-500 hover:text-red-400 transition mt-4">
                <i class="fas fa-times mr-1"></i> Annuler et revenir
            </a>
        </div>

        <!-- PAIEMENT -->
        <div class="lg:col-span-3 glass p-6 rounded-2xl">
            <h2 class="text-lg font-bold text-white mb-6 flex items-center gap-2">
                <i class="fas fa-lock text-emerald-400"></i> Paiement Sécurisé
            </h2>

            <form id="checkout-form" class="space-y-5">
                <input type="hidden" name="order_id" value="<?= $order_row_id ?>">

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
                        J'accepte la
                        <a href="/politique-paiement/" target="_blank" class="text-sky-400 hover:underline">Politique de Paiement</a>
                        et les conditions de renouvellement.
                    </span>
                </label>

                <button type="submit" id="pay-btn" disabled class="w-full flex items-center justify-center gap-3 bg-gradient-to-r from-sky-600 to-indigo-600 hover:from-sky-500 hover:to-indigo-500 text-white p-4 rounded-xl font-bold transition shadow-lg shadow-sky-600/20 disabled:opacity-50 disabled:cursor-not-allowed">
                    <i class="fas fa-shield-alt"></i>
                    <span id="pay-btn-label">Payer <?= number_format($price, 2, ',', '') ?> €</span>
                </button>
            </form>

            <div class="mt-6 pt-4 border-t border-white/10 text-center">
                <div class="flex items-center justify-center gap-4 text-gray-500 text-xl mb-2">
                    <i class="fab fa-cc-visa"></i>
                    <i class="fab fa-cc-mastercard"></i>
                    <i class="fab fa-cc-amex"></i>
                    <i class="fab fa-apple-pay"></i>
                    <i class="fab fa-google-pay"></i>
                </div>
                <div class="text-xs text-gray-600 flex items-center justify-center gap-2">
                    <i class="fas fa-shield-alt text-emerald-500"></i>
                    Paiement 100% sécurisé par Stripe
                </div>
            </div>
        </div>
    </div>
</div>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/inc/footer.php'; ?>

<script nonce="<?= defined('CSP_NONCE') ? CSP_NONCE : '' ?>">
document.addEventListener('DOMContentLoaded', async function() {
    const stripe = Stripe('<?= htmlspecialchars($stripe_public_key) ?>');
    const orderId = document.querySelector('input[name="order_id"]').value;
    const returnUrl = <?= json_encode($success_url) ?>;
    const payAmountLabel = '<?= number_format($price, 2, ',', '') ?> €';
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

    policyCheckbox.addEventListener('change', updatePayButtonState);

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
            const resp = await fetch('/shop/order/renewal/checkout/process.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ order_id: orderId })
            });
            
            if (!resp.ok) {
                const errData = await resp.json().catch(() => ({}));
                throw new Error(errData.error || `Erreur HTTP: ${resp.status}`);
            }
            
            const data = await resp.json();
            if (data.error) {
                showError(data.error);
                loadingDiv.style.display = 'none';
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
                },
                defaultValues: {
                    paymentMethod: defaultPMId || undefined
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
            showError(err.message || 'Impossible de charger le paiement.');
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
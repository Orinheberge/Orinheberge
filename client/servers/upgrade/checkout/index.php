<?php
/**
 * /client/servers/upgrade/checkout/ — Checkout avec Stripe Elements
 * Paiement directement sur le site pour la différence de prix de l'upgrade.
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

$stripe_secret_key = $cfg['stripe_secret_key'] ?? '';
$stripe_publishable = $cfg['stripe_publishable_key'] ?? '';
$success_url_base  = rtrim($cfg['site_url'] ?? 'https://orinstone.deepstone.fr', '/');
$panel_url         = rtrim($cfg['panel_url'] ?? 'https://panel.orinstone.deepstone.fr', '/');
$api_key_admin     = $cfg['api_key_admin'] ?? '';

if (empty($stripe_secret_key) || empty($stripe_publishable)) {
    die('<div style="background:#1a1a2e;color:#f87171;padding:2rem;font-family:monospace;border-radius:12px;max-width:600px;margin:4rem auto;">
        <h2>⚠️ Configuration Stripe manquante</h2>
        <p>Contactez le support : les clés Stripe ne sont pas configurées.</p>
    </div>');
}

// ═══════════════════════════════════════════
// 2. RÉCUPÉRATION DU SERVEUR & PRODUIT CIBLE
// ═══════════════════════════════════════════
$uuid         = trim($_GET['uuid'] ?? '');
$new_product_id = (int)($_GET['product_id'] ?? 0);
$billing_type = $_GET['billing'] ?? 'diff';

if (!$uuid || !$new_product_id) {
    header('Location: /client/servers/');
    exit();
}

try {
    $srv_stmt = $pdo->prepare('
        SELECT o.*, p.slug AS product_slug, p.id AS pid, p.name AS current_product_name
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

if (!$server || ($server['status'] ?? '') !== 'paid') {
    header('Location: /client/servers/upgrade/?uuid=' . urlencode($uuid));
    exit();
}

// Récupérer le nouveau produit
try {
    $np_stmt = $pdo->prepare('SELECT * FROM products WHERE id = ? AND is_active = 1 LIMIT 1');
    $np_stmt->execute([$new_product_id]);
    $new_product = $np_stmt->fetch();
} catch (Exception $e) {
    $new_product = null;
}

if (!$new_product) {
    header('Location: /client/servers/upgrade/?uuid=' . urlencode($uuid));
    exit();
}

$current_price = (float)($server['renewal_price'] ?? 0);
$new_price     = (float)$new_product['price'];

if ($new_price <= $current_price) {
    header('Location: /client/servers/upgrade/?uuid=' . urlencode($uuid));
    exit();
}

// ═══════════════════════════════════════════
// 3. CALCUL DU MONTANT À FACTURER
// ═══════════════════════════════════════════
$diff_price = $new_price - $current_price;

$billing_cycle_days = 30;
$next_billing       = !empty($server['next_due_date']) ? strtotime($server['next_due_date']) : strtotime('+30 days');
$days_remaining     = max(1, (int)ceil(($next_billing - time()) / 86400));

if ($billing_type === 'prorata') {
    $daily_old = $current_price / $billing_cycle_days;
    $daily_new = $new_price / $billing_cycle_days;
    $diff_price = ($daily_new - $daily_old) * $days_remaining;
}

$diff_price = max(0.50, round($diff_price, 2));
$amount_cents = (int)round($diff_price * 100);

// ═══════════════════════════════════════════
// 4. CRÉATION DU PAYMENTINTENT
// ═══════════════════════════════════════════
$payment_intent = null;
$error_msg = '';

try {
    $ch = curl_init('https://api.stripe.com/v1/payment_intents');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_USERPWD        => $stripe_secret_key . ':',
        CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_POSTFIELDS     => http_build_query([
            'amount'               => $amount_cents,
            'currency'             => 'eur',
            'payment_method_types' => ['card'],
            'metadata[user_id]'    => $_SESSION['user_id'],
            'metadata[order_uuid]' => $uuid,
            'metadata[new_product_id]' => $new_product_id,
            'metadata[current_product_id]' => $server['product_id'],
            'metadata[old_price]'  => $current_price,
            'metadata[new_price]'  => $new_price,
            'metadata[diff_price]' => $diff_price,
            'metadata[billing_type]' => $billing_type,
            'description'          => 'Upgrade serveur : ' . $new_product['name'],
            'receipt_email'        => $_SESSION['email'] ?? '',
        ]),
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code >= 200 && $http_code < 300) {
        $payment_intent = json_decode($response, true);
    } else {
        $err_data = json_decode($response, true);
        $error_msg = $err_data['error']['message'] ?? 'Erreur Stripe (HTTP ' . $http_code . ')';
        error_log('[Stripe PaymentIntent Error] ' . $response);
    }
} catch (Exception $e) {
    $error_msg = 'Exception : ' . $e->getMessage();
    error_log('[Stripe PaymentIntent Exception] ' . $e->getMessage());
}

// ═══════════════════════════════════════════
// 5. ENREGISTRER LA TRANSACTION EN ATTENTE
// ═══════════════════════════════════════════
$pending_uuid = null;
if ($payment_intent && !empty($payment_intent['id'])) {
    try {
        $pending_uuid = 'upg_' . bin2hex(random_bytes(8));
        $pdo->prepare("
            INSERT INTO pending_upgrades 
            (pending_uuid, user_id, order_uuid, from_product_id, to_product_id, 
             old_price, new_price, diff_amount, stripe_payment_intent_id, created_at, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 'pending')
            ON DUPLICATE KEY UPDATE status = 'pending', updated_at = NOW()
        ")->execute([
            $pending_uuid,
            $_SESSION['user_id'],
            $uuid,
            $server['product_id'],
            $new_product_id,
            $current_price,
            $new_price,
            $diff_price,
            $payment_intent['id']
        ]);
    } catch (Exception $e) {
        error_log('[Pending Upgrade] Table insert skipped: ' . $e->getMessage());
    }
}

// ═══════════════════════════════════════════
// 6. AFFICHAGE
// ═══════════════════════════════════════════
if (!$payment_intent) {
    ?>
    <!DOCTYPE html>
    <html lang="<?php echo $lang; ?>">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Erreur de paiement | OrinHeberge</title>
        <link rel="icon" type="image/png" href="/favicon.ico">
        <script src="https://cdn.tailwindcss.com"></script>
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
        <style>
            body { background: #0d0f14; color: #e2e8f0; font-family: 'Inter', sans-serif; }
            .error-container {
                max-width: 520px; margin: 6rem auto; padding: 2rem;
                background: #161a22; border: 1px solid rgba(255,255,255,0.08);
                border-radius: 1.25rem; text-align: center;
            }
        </style>
    </head>
    <body class="min-h-screen">
        <div class="error-container">
            <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-red-500/15 border border-red-500/30 flex items-center justify-center">
                <i class="fas fa-exclamation-triangle text-red-400 text-xl"></i>
            </div>
            <h1 class="text-xl font-black text-white mb-2">Impossible d'initialiser le paiement</h1>
            <p class="text-sm text-gray-400 mb-6">
                Une erreur est survenue lors de la préparation du paiement.
            </p>
            <?php if ($error_msg): ?>
                <div class="text-xs text-red-400 bg-red-500/10 border border-red-500/20 rounded-lg p-3 mb-6 text-left font-mono break-all">
                    <?php echo htmlspecialchars($error_msg); ?>
                </div>
            <?php endif; ?>
            
            <div class="bg-white/[0.03] border border-white/10 rounded-xl p-4 mb-6 text-left">
                <div class="text-xs text-gray-500 uppercase font-bold mb-2">Récapitulatif</div>
                <div class="flex justify-between text-sm mb-1">
                    <span class="text-gray-400">Serveur</span>
                    <span class="text-white font-semibold"><?php echo htmlspecialchars($server['service_name'] ?? ''); ?></span>
                </div>
                <div class="flex justify-between text-sm mb-1">
                    <span class="text-gray-400">Nouvelle offre</span>
                    <span class="text-white font-semibold"><?php echo htmlspecialchars($new_product['name']); ?></span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-400">Montant</span>
                    <span class="text-sky-400 font-bold"><?php echo number_format($diff_price, 2, ',', ''); ?>€</span>
                </div>
            </div>

            <div class="flex flex-col gap-3">
                <a href="/client/servers/upgrade/?uuid=<?php echo urlencode($uuid); ?>"
                   class="inline-flex items-center justify-center gap-2 bg-sky-600 hover:bg-sky-500 text-white font-bold px-5 py-3 rounded-xl transition">
                    <i class="fas fa-redo text-xs"></i> Réessayer
                </a>
                <a href="/client/servers/" class="text-sm text-gray-400 hover:text-white transition">
                    <i class="fas fa-arrow-left text-xs"></i> Retour aux serveurs
                </a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// Données pour le frontend
$payment_intent_id = $payment_intent['id'];
$client_secret = $payment_intent['client_secret'];
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paiement upgrade — <?php echo htmlspecialchars($server['service_name'] ?? ''); ?> | OrinHeberge</title>
    <link rel="icon" type="image/png" href="/favicon.ico">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://js.stripe.com/v3/"></script>
    <style>
        body { background: #0d0f14; color: #e2e8f0; font-family: 'Inter', sans-serif; }
        .checkout-page { padding: 2rem; max-width: 600px; margin: 0 auto; }
        .checkout-card { background: #161a22; border: 1px solid rgba(255,255,255,0.08); border-radius: 1.25rem; padding: 2rem; }
        
        /* Stripe Elements styling */
        .stripe-element {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.15);
            border-radius: 0.75rem;
            padding: 1rem;
            transition: all 0.2s;
        }
        .stripe-element.StripeElement--focus {
            border-color: #38bdf8;
            box-shadow: 0 0 0 3px rgba(56, 189, 248, 0.1);
        }
        .stripe-element.StripeElement--invalid {
            border-color: #f87171;
        }
        .stripe-element.StripeElement--complete {
            border-color: #4ade80;
        }
        
        #card-errors {
            color: #f87171;
            font-size: 0.875rem;
            margin-top: 0.5rem;
        }
        
        .btn-primary {
            background: #0ea5e9;
            color: white;
            font-weight: 700;
            padding: 1rem 1.5rem;
            border-radius: 0.75rem;
            width: 100%;
            transition: all 0.2s;
            border: none;
            cursor: pointer;
        }
        .btn-primary:hover:not(:disabled) {
            background: #38bdf8;
            transform: translateY(-1px);
            box-shadow: 0 10px 30px rgba(14, 165, 233, 0.3);
        }
        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255,255,255,0.3);
            border-top-color: white;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        @keyframes fade-in {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in { animation: fade-in 0.3s ease-out; }
    </style>
</head>
<body class="min-h-screen">

<div class="checkout-page">
    
    <!-- Header -->
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-black text-white flex items-center gap-2">
                <i class="fas fa-credit-card text-sky-400"></i>
                Paiement sécurisé
            </h1>
            <p class="text-sm text-gray-400 mt-1">
                Upgrade de votre serveur
            </p>
        </div>
        <a href="/client/servers/upgrade/?uuid=<?php echo urlencode($uuid); ?>" 
           class="text-sm text-gray-400 hover:text-white transition">
            <i class="fas fa-times"></i>
        </a>
    </div>
    
    <!-- Récapitulatif -->
    <div class="checkout-card mb-6">
        <div class="text-xs text-gray-500 uppercase font-bold mb-3">Récapitulatif</div>
        
        <div class="space-y-3 mb-6">
            <div class="flex justify-between text-sm">
                <span class="text-gray-400">Serveur</span>
                <span class="text-white font-semibold"><?php echo htmlspecialchars($server['service_name'] ?? ''); ?></span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-gray-400">Ancienne offre</span>
                <span class="text-gray-300"><?php echo htmlspecialchars($server['current_product_name'] ?? ''); ?></span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-gray-400">Nouvelle offre</span>
                <span class="text-white font-semibold"><?php echo htmlspecialchars($new_product['name']); ?></span>
            </div>
            
            <div class="border-t border-white/10 pt-3 mt-3">
                <div class="flex justify-between items-center">
                    <div>
                        <div class="text-xs text-gray-500">Différence à payer</div>
                        <div class="text-xs text-gray-400 mt-0.5">
                            <?php echo $billing_type === 'prorata' ? 'Prorata (' . $days_remaining . 'j restants)' : 'Prochain cycle'; ?>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-2xl font-black text-sky-400">
                            <?php echo number_format($diff_price, 2, ',', ''); ?>€
                        </div>
                        <div class="text-xs text-gray-500">
                            puis <?php echo number_format($new_price, 2, ',', ''); ?>€/mois
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Ressources -->
        <div class="grid grid-cols-3 gap-2 pt-4 border-t border-white/10">
            <div class="text-center bg-white/[0.03] rounded-lg py-2">
                <i class="fas fa-memory text-sky-400 text-sm mb-1"></i>
                <div class="text-xs text-gray-500">RAM</div>
                <div class="text-sm font-bold text-white">
                    <?php echo $new_product['ram'] >= 1024 ? number_format($new_product['ram']/1024, 0) . ' GB' : $new_product['ram'] . ' MB'; ?>
                </div>
            </div>
            <div class="text-center bg-white/[0.03] rounded-lg py-2">
                <i class="fas fa-hard-drive text-sky-400 text-sm mb-1"></i>
                <div class="text-xs text-gray-500">SSD</div>
                <div class="text-sm font-bold text-white">
                    <?php echo $new_product['disk'] >= 1024 ? number_format($new_product['disk']/1024, 0) . ' GB' : $new_product['disk'] . ' MB'; ?>
                </div>
            </div>
            <div class="text-center bg-white/[0.03] rounded-lg py-2">
                <i class="fas fa-microchip text-sky-400 text-sm mb-1"></i>
                <div class="text-xs text-gray-500">CPU</div>
                <div class="text-sm font-bold text-white"><?php echo (int)$new_product['cpu']; ?>%</div>
            </div>
        </div>
    </div>
    
    <!-- Formulaire de paiement -->
    <div class="checkout-card">
        <div class="text-xs text-gray-500 uppercase font-bold mb-4 flex items-center gap-2">
            <i class="fas fa-lock text-emerald-400"></i>
            Informations de paiement
        </div>
        
        <form id="payment-form">
            <div class="mb-4">
                <label class="block text-sm text-gray-300 mb-2">Email</label>
                <input type="email" id="email" value="<?php echo htmlspecialchars($_SESSION['email'] ?? ''); ?>" 
                       class="w-full bg-white/[0.05] border border-white/15 rounded-xl px-4 py-3 text-white focus:border-sky-400 focus:outline-none"
                       required>
            </div>
            
            <div class="mb-6">
                <label class="block text-sm text-gray-300 mb-2">Carte bancaire</label>
                <div id="card-element" class="stripe-element"></div>
                <div id="card-errors" role="alert"></div>
            </div>
            
            <button type="submit" id="submit-btn" class="btn-primary">
                <span id="button-text">
                    <i class="fas fa-lock text-xs"></i> Payer <?php echo number_format($diff_price, 2, ',', ''); ?>€
                </span>
            </button>
        </form>
        
        <div class="mt-6 pt-6 border-t border-white/10 text-center">
            <div class="flex items-center justify-center gap-4 text-xs text-gray-500">
                <i class="fab fa-cc-visa text-2xl"></i>
                <i class="fab fa-cc-mastercard text-2xl"></i>
                <i class="fab fa-cc-amex text-2xl"></i>
                <i class="fab fa-cc-discover text-2xl"></i>
            </div>
            <div class="text-xs text-gray-600 mt-2">
                <i class="fas fa-shield-alt"></i> Paiement sécurisé par Stripe
            </div>
        </div>
    </div>
    
</div>

<script>
// Configuration Stripe
const stripe = Stripe('<?php echo htmlspecialchars($stripe_publishable); ?>');
const elements = stripe.elements();
const card = elements.create('card', {
    style: {
        base: {
            color: '#e2e8f0',
            fontFamily: 'Inter, sans-serif',
            fontSize: '16px',
            '::placeholder': {
                color: '#64748b'
            }
        },
        invalid: {
            color: '#f87171'
        }
    }
});
card.mount('#card-element');

// Gestion des erreurs
card.addEventListener('change', function(event) {
    const displayError = document.getElementById('card-errors');
    if (event.error) {
        displayError.textContent = event.error.message;
    } else {
        displayError.textContent = '';
    }
});

// Soumission du formulaire
const form = document.getElementById('payment-form');
const submitBtn = document.getElementById('submit-btn');
const buttonText = document.getElementById('button-text');

form.addEventListener('submit', async function(event) {
    event.preventDefault();
    
    submitBtn.disabled = true;
    buttonText.innerHTML = '<span class="spinner"></span> Traitement en cours...';
    
    const {paymentIntent, error} = await stripe.confirmCardPayment(
        '<?php echo htmlspecialchars($client_secret); ?>',
        {
            payment_method: {
                card: card,
                billing_details: {
                    email: document.getElementById('email').value
                }
            }
        }
    );
    
    if (error) {
        // Erreur de paiement
        const displayError = document.getElementById('card-errors');
        displayError.textContent = error.message;
        submitBtn.disabled = false;
        buttonText.innerHTML = '<i class="fas fa-lock text-xs"></i> Payer <?php echo number_format($diff_price, 2, ',', ''); ?>€';
    } else if (paymentIntent.status === 'succeeded') {
        // Paiement réussi - redirection vers la page de succès
        window.location.href = '<?php echo $success_url_base; ?>/client/servers/upgrade/success/?payment_intent_id=<?php echo urlencode($payment_intent_id); ?>&uuid=<?php echo urlencode($uuid); ?>&product_id=<?php echo $new_product_id; ?>';
    }
});
</script>

</body>
</html>
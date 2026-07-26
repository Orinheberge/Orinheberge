<?php
session_start();

// Affichage des erreurs pour debug (à retirer en prod)
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/lang.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/settings.php';

// Vérifier si Stripe est configuré
$stripe_pub_key = get_setting('stripe_public_key');
$stripe_secret_key = get_setting('stripe_secret_key');

if (empty($stripe_pub_key) || empty($stripe_secret_key)) {
    die('<div style="color:red;padding:20px;">Configuration Stripe manquante. Contactez l\'administrateur.</div>');
}

// Charger Stripe
if (!file_exists($_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php')) {
    die('<div style="color:red;padding:20px;">Erreur: vendor/autoload.php introuvable. Exécutez "composer install".</div>');
}
require_once $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';

\Stripe\Stripe::setApiKey($stripe_secret_key);

// Vérifier connexion utilisateur
if (!isset($_SESSION['user_id'])) {
    header('Location: /login/');
    exit();
}

$message = '';
$message_type = 'info';

try {
    $pdo = new PDO('mysql:host=localhost;dbname=s43_orinheberge;charset=utf8mb4', 'root', '1504', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    // Récupérer user
    $stmt = $pdo->prepare('SELECT id, firstname, lastname, pseudo, email, avatar, stripe_customer_id FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user) {
        session_destroy();
        header('Location: /login/');
        exit();
    }

    // Créer customer Stripe si inexistant
    if (empty($user['stripe_customer_id'])) {
        $customer = \Stripe\Customer::create([
            'email' => $user['email'],
            'name' => trim($user['firstname'] . ' ' . $user['lastname']),
            'metadata' => ['user_id' => $user['id']]
        ]);
        $pdo->prepare('UPDATE users SET stripe_customer_id = ? WHERE id = ?')->execute([$customer->id, $user['id']]);
        $user['stripe_customer_id'] = $customer->id;
    }

    // Récupérer les cartes
    $payment_methods = [];
    try {
        $pm_list = \Stripe\PaymentMethod::all([
            'customer' => $user['stripe_customer_id'],
            'type' => 'card',
        ]);
        $payment_methods = $pm_list->data;
    } catch (Exception $e) {
        error_log('[Stripe] Erreur récupération cartes: ' . $e->getMessage());
    }

    // Suppression carte
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_card'])) {
        $pm_id = trim($_POST['payment_method_id'] ?? '');
        if ($pm_id) {
            try {
                $pm = \Stripe\PaymentMethod::retrieve($pm_id);
                $pm->detach();
                $pdo->prepare('DELETE FROM user_stripe_cards WHERE user_id = ? AND payment_method_id = ?')->execute([$_SESSION['user_id'], $pm_id]);
                $message = 'Carte supprimée avec succès.';
                $message_type = 'success';
                // Recharger les cartes
                $pm_list = \Stripe\PaymentMethod::all(['customer' => $user['stripe_customer_id'], 'type' => 'card']);
                $payment_methods = $pm_list->data;
            } catch (Exception $e) {
                $message = 'Erreur lors de la suppression.';
                $message_type = 'error';
            }
        }
    }

} catch (Exception $e) {
    error_log('[Stripe Cards] Erreur: ' . $e->getMessage());
    $message = 'Une erreur est survenue.';
    $message_type = 'error';
}
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Mes Cartes</title>
    <script src="https://js.stripe.com/v3/"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #0f172a; background-image: radial-gradient(at 0% 0%, hsla(253,16%,7%,1) 0, transparent 50%), radial-gradient(at 50% 0%, hsla(225,39%,30%,1) 0, transparent 50%), radial-gradient(at 100% 0%, hsla(339,49%,30%,1) 0, transparent 50%); }
        .page-layout { display: flex; min-height: 100vh; width: 100%; }
        .main-content-area { flex: 1; display: flex; flex-direction: column; min-height: 100vh; margin-left: 0; }
        @media (min-width: 1024px) { .main-content-area { margin-left: 16rem; } }
        .glass-panel { background: rgba(30, 41, 59, 0.7); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.08); }
        .StripeElement { background: rgba(15, 23, 42, 0.6); border: 1px solid rgba(255,255,255,0.1); border-radius: 0.5rem; padding: 0.75rem 1rem; color: white; }
        .StripeElement--focus { border-color: #38bdf8; box-shadow: 0 0 0 2px rgba(56, 189, 248, 0.2); }
    </style>
</head>
<body class="text-gray-300 font-sans">
<div class="page-layout">
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/inc/clients_sidebar.php'; ?>
    <div class="main-content-area relative w-full">
        <header class="lg:hidden glass-panel p-4 flex justify-between items-center sticky top-0 z-30 backdrop-blur-md bg-[#0f172a]/90 border-b border-white/5">
            <span class="font-bold text-white">Mes Cartes</span>
        </header>
        <main class="flex-grow p-6 lg:p-10 w-full max-w-5xl mx-auto">
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-white mb-1">Moyens de Paiement</h1>
                <p class="text-gray-400 text-sm">Ajoutez une carte sécurisée via Stripe (3D Secure).</p>
            </div>
            <?php if ($message): ?>
            <div class="mb-6 p-4 rounded-xl border <?php echo $message_type === 'success' ? 'bg-green-500/10 border-green-500/30 text-green-400' : 'bg-red-500/10 border-red-500/30 text-red-400'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
            <?php endif; ?>
            <div class="glass-panel rounded-2xl p-6 lg:p-8 mb-8">
                <h2 class="text-xl font-bold text-white mb-6 flex items-center gap-2"><i class="fas fa-plus-circle text-sky-500"></i> Ajouter une carte</h2>
                <form id="payment-form" class="space-y-5">
                    <div id="card-element" class="StripeElement"></div>
                    <div id="card-errors" role="alert" class="text-red-400 text-sm hidden"><i class="fas fa-exclamation-circle mr-1"></i><span class="error-message"></span></div>
                    <button type="submit" id="submit-button" class="bg-sky-600 hover:bg-sky-500 text-white px-6 py-3 rounded-xl font-bold shadow-lg shadow-sky-600/20 transition"><i class="fas fa-shield-alt mr-2"></i>Valider avec ma banque</button>
                    <p class="text-xs text-gray-500"><i class="fas fa-lock text-emerald-400 mr-2"></i>Paiement sécurisé • 3D Secure • 0,00€</p>
                </form>
            </div>
            <h2 class="text-xl font-bold text-white mb-4"><i class="fas fa-wallet text-sky-500 mr-2"></i>Cartes enregistrées (<?php echo count($payment_methods); ?>)</h2>
            <?php if (empty($payment_methods)): ?>
            <div class="glass-panel rounded-2xl p-12 text-center border-dashed border-2 border-white/5">
                <i class="fas fa-credit-card text-4xl text-gray-500 mb-4"></i>
                <p class="text-gray-400">Aucune carte enregistrée</p>
            </div>
            <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <?php foreach ($payment_methods as $pm): 
                    $card = $pm->card;
                ?>
                <div class="glass-panel rounded-xl p-5">
                    <div class="flex items-start justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 rounded-lg bg-white/5 flex items-center justify-center">
                                <i class="fab fa-cc-<?php echo strtolower($card->brand); ?> text-2xl <?php echo $card->brand === 'Visa' ? 'text-blue-400' : ($card->brand === 'Mastercard' ? 'text-red-400' : 'text-gray-400'); ?>"></i>
                            </div>
                            <div>
                                <div class="text-white font-bold text-sm">•••• •••• •••• <?php echo htmlspecialchars($card->last4); ?></div>
                                <div class="text-gray-500 text-xs"><?php echo htmlspecialchars($card->brand); ?> • Exp: <?php echo str_pad($card->exp_month, 2, '0', STR_PAD_LEFT); ?>/<?php echo substr($card->exp_year, -2); ?></div>
                            </div>
                        </div>
                        <form method="post" onsubmit="return confirm('Supprimer cette carte ?')">
                            <input type="hidden" name="payment_method_id" value="<?php echo htmlspecialchars($pm->id); ?>">
                            <button type="submit" name="delete_card" class="text-gray-500 hover:text-red-400"><i class="fas fa-trash"></i></button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </main>
        <?php require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/footer.php'; ?>
    </div>
</div>
<script>
const stripe = Stripe('<?php echo htmlspecialchars($stripe_pub_key); ?>');
const elements = stripe.elements();
const cardElement = elements.create('card', {style: {base: {color: '#ffffff', fontSize: '14px', '::placeholder': {color: '#64748b'}}, invalid: {color: '#f87171'}}, hidePostalCode: true});
cardElement.mount('#card-element');

cardElement.on('change', function(event) {
    const errorDiv = document.getElementById('card-errors');
    if (event.error) {
        errorDiv.querySelector('.error-message').textContent = event.error.message;
        errorDiv.classList.remove('hidden');
    } else {
        errorDiv.classList.add('hidden');
    }
});

document.getElementById('payment-form').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = document.getElementById('submit-button');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Validation...';
    
    try {
        const resp = await fetch('/client/cards/create-intent.php', {method: 'POST', headers: {'Content-Type': 'application/json'}});
        const data = await resp.json();
        if (data.error) throw new Error(data.error);
        
        const result = await stripe.confirmCardSetup(data.client_secret, {
            payment_method: {card: cardElement, billing_details: {name: '<?php echo addslashes(trim($user['firstname'] . ' ' . $user['lastname'])); ?>'}}
        });
        
        if (result.error) throw new Error(result.error.message);
        
        if (result.setupIntent.status === 'requires_action') {
            window.location.href = '/client/cards/verify.php?setup_intent=' + result.setupIntent.id;
            return;
        }
        
        const saveResp = await fetch('/client/cards/save-card.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({payment_method_id: result.setupIntent.payment_method})
        });
        const saveData = await saveResp.json();
        
        if (saveData.success) {
            window.location.href = '/client/cards/?success=1';
        } else {
            throw new Error(saveData.error || 'Erreur sauvegarde');
        }
    } catch (err) {
        document.getElementById('card-errors').querySelector('.error-message').textContent = err.message;
        document.getElementById('card-errors').classList.remove('hidden');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-shield-alt mr-2"></i>Valider avec ma banque';
    }
});
</script>
</body>
</html>
<?php
ini_set('display_errors', 1); error_reporting(E_ALL);
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/lang.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/settings.php'; // ← AJOUT CRITIQUE
require_once $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';

// Configuration Stripe
//$stripe_pub_key = get_setting('stripe_public_key');
//$stripe_secret_key = get_setting('stripe_secret_key');
$stripe_pub_key = 'pk_live_51TYsYg2f2egcuUT4obSIMXsBBAVpzw0Gk18niYNgWQ5vhvV8nX5aAI6nZqEZ12RfHg1nmP2qjczVfPuX8Eb0ePzk00qDqtPro2'; 
$stripe_secret_key = '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';

if (empty($stripe_pub_key) || empty($stripe_secret_key)) {
    die('Configuration Stripe manquante. Contactez l\'administrateur.');
}

\Stripe\Stripe::setApiKey($stripe_secret_key);

$panel_url = 'https://panel.orinstone.deepstone.fr';
$phpmyadmin_url = 'https://php.orinstone.deepstone.fr';
$open_tickets = 0;

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

    $stmt = $pdo->prepare('SELECT id, firstname, lastname, pseudo, email, avatar, is_admin, stripe_customer_id FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) { session_destroy(); header('Location: /login/'); exit(); }

    $_SESSION['username'] = !empty($user['pseudo']) ? $user['pseudo'] : $user['firstname'];
    $_SESSION['avatar'] = $user['avatar'];
    $_SESSION['is_admin'] = $user['is_admin'] ?? 0;

    // Créer un Customer Stripe si inexistant
    if (empty($user['stripe_customer_id'])) {
        $customer = \Stripe\Customer::create([
            'email' => $user['email'],
            'name' => trim($user['firstname'] . ' ' . $user['lastname']),
            'metadata' => ['user_id' => $user['id']]
        ]);
        $pdo->prepare('UPDATE users SET stripe_customer_id = ? WHERE id = ?')
            ->execute([$customer->id, $user['id']]);
        $user['stripe_customer_id'] = $customer->id;
    }

    // Récupérer les cartes enregistrées (PaymentMethods)
    $payment_methods = [];
    try {
        $pm_list = \Stripe\PaymentMethod::all([
            'customer' => $user['stripe_customer_id'],
            'type' => 'card',
        ]);
        $payment_methods = $pm_list->data;
    } catch (Exception $e) {
        error_log('[Stripe Cards] Erreur récupération PM: ' . $e->getMessage());
    }

    // Traitement suppression
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_card'])) {
        $pm_id = trim($_POST['payment_method_id'] ?? '');
        if ($pm_id) {
            try {
                $pm = \Stripe\PaymentMethod::retrieve($pm_id);
                $pm->detach();

                $pdo->prepare('DELETE FROM user_stripe_cards WHERE user_id = ? AND payment_method_id = ?')
                    ->execute([$_SESSION['user_id'], $pm_id]);

                $message = 'Carte supprimée avec succès.';
                $message_type = 'success';
            } catch (Exception $e) {
                error_log('[Stripe Cards] Erreur suppression: ' . $e->getMessage());
                $message = 'Erreur lors de la suppression.';
                $message_type = 'error';
            }
        }
    }

} catch (Exception $e) {
    error_log('[Stripe Cards] Erreur critique: ' . $e->getMessage());
    $message = 'Une erreur est survenue. Veuillez réessayer plus tard.';
    $message_type = 'error';
}
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Mes Cartes | Dashboard</title>
    <script src="https://js.stripe.com/v3/"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #0f172a;
            background-image: radial-gradient(at 0% 0%, hsla(253,16%,7%,1) 0, transparent 50%), radial-gradient(at 50% 0%, hsla(225,39%,30%,1) 0, transparent 50%), radial-gradient(at 100% 0%, hsla(339,49%,30%,1) 0, transparent 50%);
        }
        .page-layout { display: flex; min-height: 100vh; width: 100%; }
        .main-content-area { flex: 1; display: flex; flex-direction: column; min-height: 100vh; margin-left: 0; }
        @media (min-width: 1024px) { .main-content-area { margin-left: 16rem; } }
        .glass-panel { background: rgba(30, 41, 59, 0.7); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.08); }
        .StripeElement { background: rgba(15, 23, 42, 0.6); border: 1px solid rgba(255,255,255,0.1); border-radius: 0.5rem; padding: 0.75rem 1rem; color: white; }
        .StripeElement--focus { border-color: #38bdf8; box-shadow: 0 0 0 2px rgba(56, 189, 248, 0.2); }
        .card-item { transition: all 0.2s; }
        .card-item:hover { transform: translateY(-2px); }
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

            <!-- Formulaire Stripe Elements -->
            <div class="glass-panel rounded-2xl p-6 lg:p-8 mb-8">
                <h2 class="text-xl font-bold text-white mb-6 flex items-center gap-2">
                    <i class="fas fa-plus-circle text-sky-500"></i> Ajouter une carte
                </h2>

                <form id="payment-form" class="space-y-5">
                    <div id="card-element" class="StripeElement">
                        <!-- Stripe Elements sera injecté ici -->
                    </div>

                    <div id="card-errors" role="alert" class="text-red-400 text-sm hidden">
                        <i class="fas fa-exclamation-circle mr-1"></i>
                        <span class="error-message"></span>
                    </div>

                    <button type="submit" id="submit-button" class="bg-sky-600 hover:bg-sky-500 text-white px-6 py-3 rounded-xl font-bold shadow-lg shadow-sky-600/20 transition transform hover:-translate-y-0.5 active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed">
                        <i class="fas fa-shield-alt mr-2"></i>Valider avec ma banque
                    </button>

                    <p class="text-xs text-gray-500 flex items-center gap-2">
                        <i class="fas fa-lock text-emerald-400"></i>
                        Paiement sécurisé par Stripe • Validation 3D Secure requise • 0,00€
                    </p>
                </form>
            </div>

            <!-- Liste des cartes -->
            <h2 class="text-xl font-bold text-white mb-4 flex items-center gap-2">
                <i class="fas fa-wallet text-sky-500"></i> Cartes enregistrées (<?php echo count($payment_methods); ?>)
            </h2>

            <?php if (empty($payment_methods)): ?>
                <div class="glass-panel rounded-2xl p-12 text-center border-dashed border-2 border-white/5">
                    <div class="w-16 h-16 bg-white/5 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-credit-card text-2xl text-gray-500"></i>
                    </div>
                    <h3 class="text-white font-bold text-lg mb-1">Aucune carte enregistrée</h3>
                    <p class="text-gray-500 text-sm">Ajoutez votre première carte via le formulaire Stripe ci-dessus.</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <?php foreach ($payment_methods as $pm):
                        $card = $pm->card;
                        $date_added = isset($pm->created) ? date('d/m/Y', $pm->created) : 'Inconnu';
                    ?>
                    <div class="glass-panel rounded-xl p-5 card-item relative group">
                        <div class="flex items-start justify-between mb-4">
                            <div class="flex items-center gap-3">
                                <div class="w-12 h-12 rounded-lg bg-white/5 border border-white/10 flex items-center justify-center">
                                    <i class="fab fa-cc-<?php echo strtolower($card->brand); ?> text-2xl
                                        <?php
                                        if ($card->brand === 'Visa') echo 'text-blue-400';
                                        elseif ($card->brand === 'Mastercard') echo 'text-red-400';
                                        elseif ($card->brand === 'American Express') echo 'text-cyan-400';
                                        else echo 'text-gray-400';
                                        ?>"></i>
                                </div>
                                <div>
                                    <div class="text-white font-bold text-sm">
                                        <?php echo !empty($pm->billing_details->name) ? htmlspecialchars($pm->billing_details->name) : 'Carte ' . ucfirst($card->brand); ?>
                                    </div>
                                    <div class="text-gray-500 text-xs font-mono">
                                        •••• •••• •••• <?php echo htmlspecialchars($card->last4); ?>
                                    </div>
                                    <?php if ($card->exp_month && $card->exp_year): ?>
                                    <div class="text-gray-500 text-[10px] mt-1">
                                        Exp: <?php echo str_pad($card->exp_month, 2, '0', STR_PAD_LEFT); ?>/<?php echo substr($card->exp_year, -2); ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <form method="post" onsubmit="return confirm('Supprimer cette carte définitivement ?')">
                                <input type="hidden" name="payment_method_id" value="<?php echo htmlspecialchars($pm->id); ?>">
                                <button type="submit" name="delete_card" class="text-gray-500 hover:text-red-400 transition p-2 rounded-lg hover:bg-red-500/10" title="Supprimer">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </form>
                        </div>
                        <div class="flex items-center justify-between pt-3 border-t border-white/5">
                            <span class="text-[10px] text-gray-500 uppercase tracking-wider">
                                <?php echo ucfirst($card->brand); ?>
                            </span>
                            <span class="text-[10px] text-gray-600">Ajoutée le <?php echo $date_added; ?></span>
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

const style = {
    base: {
        color: '#ffffff',
        fontFamily: '"Inter", sans-serif',
        fontSmoothing: 'antialiased',
        fontSize: '14px',
        '::placeholder': { color: '#64748b' },
        backgroundColor: 'transparent',
    },
    invalid: {
        color: '#f87171',
        iconColor: '#f87171'
    }
};

const cardElement = elements.create('card', {
    style: style,
    hidePostalCode: true,
    iconStyle: 'solid'
});
cardElement.mount('#card-element');

cardElement.on('change', function(event) {
    const errorDiv = document.getElementById('card-errors');
    const errorMessage = errorDiv.querySelector('.error-message');

    if (event.error) {
        errorMessage.textContent = event.error.message;
        errorDiv.classList.remove('hidden');
    } else {
        errorDiv.classList.add('hidden');
    }
});

const form = document.getElementById('payment-form');
const submitButton = document.getElementById('submit-button');

form.addEventListener('submit', async function(event) {
    event.preventDefault();

    submitButton.disabled = true;
    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Validation en cours...';

    try {
        const response = await fetch('/client/cards/create-intent.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({})
        });

        const data = await response.json();

        if (data.error) {
            throw new Error(data.error);
        }

        const result = await stripe.confirmCardSetup(data.client_secret, {
            payment_method: {
                card: cardElement,
                billing_details: {
                    name: '<?php echo addslashes(trim($user['firstname'] . ' ' . $user['lastname'])); ?>'
                }
            }
        });

        if (result.error) {
            throw new Error(result.error.message);
        }

        if (result.setupIntent.status === 'requires_action') {
            window.location.href = '/client/cards/verify.php?setup_intent=' + result.setupIntent.id;
            return;
        }

        const saveResponse = await fetch('/client/cards/save-card.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                payment_method_id: result.setupIntent.payment_method
            })
        });

        const saveData = await saveResponse.json();

        if (saveData.success) {
            window.location.href = '/client/cards/?success=1';
        } else {
            throw new Error(saveData.error || 'Erreur sauvegarde');
        }

    } catch (error) {
        const errorDiv = document.getElementById('card-errors');
        const errorMessage = errorDiv.querySelector('.error-message');
        errorMessage.textContent = error.message;
        errorDiv.classList.remove('hidden');
        submitButton.disabled = false;
        submitButton.innerHTML = '<i class="fas fa-shield-alt mr-2"></i>Valider avec ma banque';
    }
});
</script>

</body>
</html>
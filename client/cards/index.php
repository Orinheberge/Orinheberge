<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/lang.php';

// Configuration requise par clients_sidebar.php
$panel_url = 'https://panel.orinstone.deepstone.fr';
$phpmyadmin_url = 'https://php.orinstone.deepstone.fr';
$open_tickets = 0;

// Clé de chiffrement (À METTRE DANS UN FICHIER CONFIG HORS RACINE WEB)
define('CARD_ENCRYPTION_KEY', 'UneVraieCleSecreteDe32CaracteresIci!!');

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

    // Récupération utilisateur pour la sidebar
    $stmt = $pdo->prepare('SELECT id, firstname, lastname, pseudo, email, avatar, is_admin FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) { session_destroy(); header('Location: /login/'); exit(); }

    $_SESSION['username'] = !empty($user['pseudo']) ? $user['pseudo'] : $user['firstname'];
    $_SESSION['avatar'] = $user['avatar'];
    $_SESSION['is_admin'] = $user['is_admin'] ?? 0;

    // Fonctions de chiffrement/déchiffrement
    function encrypt_card($data) {
        return openssl_encrypt($data, 'AES-256-CBC', CARD_ENCRYPTION_KEY, 0, substr(hash('sha256', CARD_ENCRYPTION_KEY), 0, 16));
    }

    function decrypt_card($data) {
        return openssl_decrypt($data, 'AES-256-CBC', CARD_ENCRYPTION_KEY, 0, substr(hash('sha256', CARD_ENCRYPTION_KEY), 0, 16));
    }

    // Traitement POST
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Ajouter une carte
        if (isset($_POST['add_card'])) {
            $card_number = preg_replace('/\s+/', '', trim($_POST['card_number'] ?? ''));
            $card_holder = strtoupper(trim($_POST['card_holder'] ?? ''));
            $card_expiry = trim($_POST['card_expiry'] ?? '');
            $card_cvv = trim($_POST['card_cvv'] ?? '');

            // Validation
            if (empty($card_number) || empty($card_holder) || empty($card_expiry)) {
                throw new Exception('Tous les champs sont obligatoires.');
            }
            if (!preg_match('/^\d{13,19}$/', $card_number)) {
                throw new Exception('Numéro de carte invalide.');
            }
            if (!preg_match('/^\d{2}\/\d{2}$/', $card_expiry)) {
                throw new Exception('Format date invalide (MM/AA).');
            }
            if (!preg_match('/^\d{3,4}$/', $card_cvv)) {
                throw new Exception('CVV invalide.');
            }

            // Vérifier expiration
            [$month, $year] = explode('/', $card_expiry);
            $exp_date = mktime(0, 0, 0, (int)$month, 1, 2000 + (int)$year);
            if ($exp_date < time()) {
                throw new Exception('Cette carte est expirée.');
            }

            // Déterminer le type
            $card_type = '';
            if (preg_match('/^4/', $card_number)) $card_type = 'visa';
            elseif (preg_match('/^5[1-5]/', $card_number)) $card_type = 'mastercard';
            elseif (preg_match('/^3[47]/', $card_number)) $card_type = 'amex';

            $encrypted = encrypt_card($card_number);

            $pdo->prepare('INSERT INTO user_cards (user_id, card_number, card_holder, card_expiry, card_type) VALUES (?, ?, ?, ?, ?)')
                ->execute([$_SESSION['user_id'], $encrypted, $card_holder, $card_expiry, $card_type]);

            $message = 'Carte ajoutée avec succès.';
            $message_type = 'success';
        }

        // Supprimer une carte
        if (isset($_POST['delete_card'])) {
            $card_id = (int)$_POST['card_id'];
            $pdo->prepare('DELETE FROM user_cards WHERE id = ? AND user_id = ?')
                ->execute([$card_id, $_SESSION['user_id']]);
            $message = 'Carte supprimée.';
            $message_type = 'success';
        }
    }

    // Récupérer toutes les cartes
    $stmt_cards = $pdo->prepare('SELECT * FROM user_cards WHERE user_id = ? ORDER BY created_at DESC');
    $stmt_cards->execute([$_SESSION['user_id']]);
    $cards = $stmt_cards->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $message = $e->getMessage();
    $message_type = 'error';
    $cards = [];
}

function get_card_icon($type) {
    switch ($type) {
        case 'visa': return ['icon' => 'fa-cc-visa', 'color' => 'text-blue-400'];
        case 'mastercard': return ['icon' => 'fa-cc-mastercard', 'color' => 'text-red-400'];
        case 'amex': return ['icon' => 'fa-cc-amex', 'color' => 'text-cyan-400'];
        default: return ['icon' => 'fa-credit-card', 'color' => 'text-gray-400'];
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Mes Cartes | Dashboard</title>
    <link rel="icon" type="image/png" href="https://heberge.orinstone.deepstone.fr/favicon.ico">
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
        .input-field { background: rgba(15, 23, 42, 0.6); border: 1px solid rgba(255,255,255,0.1); transition: all 0.3s; }
        .input-field:focus { border-color: #38bdf8; box-shadow: 0 0 0 2px rgba(56, 189, 248, 0.2); outline: none; }
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
                <p class="text-gray-400 text-sm">Gérez vos cartes bancaires enregistrées.</p>
            </div>

            <?php if ($message): ?>
            <div class="mb-6 p-4 rounded-xl border <?php echo $message_type === 'success' ? 'bg-green-500/10 border-green-500/30 text-green-400' : 'bg-red-500/10 border-red-500/30 text-red-400'; ?>">
                <?php echo $message; ?>
            </div>
            <?php endif; ?>

            <!-- Formulaire d'ajout -->
            <div class="glass-panel rounded-2xl p-6 lg:p-8 mb-8">
                <h2 class="text-xl font-bold text-white mb-6 flex items-center gap-2">
                    <i class="fas fa-plus-circle text-sky-500"></i> Ajouter une carte
                </h2>
                <form method="post" class="space-y-5">
                    <div>
                        <label class="block text-xs font-bold uppercase text-gray-500 mb-2">Numéro de carte</label>
                        <div class="relative">
                            <i class="fab fa-cc-visa absolute left-3 top-3.5 text-gray-500 text-lg"></i>
                            <input type="text" name="card_number" placeholder="0000 0000 0000 0000" maxlength="19" required class="input-field w-full rounded-lg pl-10 pr-4 py-3 text-white font-mono tracking-wider">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-bold uppercase text-gray-500 mb-2">Titulaire</label>
                            <input type="text" name="card_holder" placeholder="M JOHN DOE" required class="input-field w-full rounded-lg px-4 py-3 text-white uppercase">
                        </div>
                        <div>
                            <label class="block text-xs font-bold uppercase text-gray-500 mb-2">Expiration</label>
                            <input type="text" name="card_expiry" placeholder="MM/AA" maxlength="5" required class="input-field w-full rounded-lg px-4 py-3 text-white text-center">
                        </div>
                    </div>
                    <div class="max-w-[140px]">
                        <label class="block text-xs font-bold uppercase text-gray-500 mb-2">CVV</label>
                        <input type="password" name="card_cvv" placeholder="123" maxlength="4" required class="input-field w-full rounded-lg px-4 py-3 text-white text-center">
                    </div>
                    <button type="submit" name="add_card" class="bg-sky-600 hover:bg-sky-500 text-white px-6 py-3 rounded-xl font-bold shadow-lg shadow-sky-600/20 transition transform hover:-translate-y-0.5 active:scale-95">
                        <i class="fas fa-save mr-2"></i>Enregistrer la carte
                    </button>
                </form>
            </div>

            <!-- Liste des cartes -->
            <h2 class="text-xl font-bold text-white mb-4 flex items-center gap-2">
                <i class="fas fa-wallet text-sky-500"></i> Cartes enregistrées (<?php echo count($cards); ?>)
            </h2>

            <?php if (empty($cards)): ?>
                <div class="glass-panel rounded-2xl p-12 text-center border-dashed border-2 border-white/5">
                    <div class="w-16 h-16 bg-white/5 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-credit-card text-2xl text-gray-500"></i>
                    </div>
                    <h3 class="text-white font-bold text-lg mb-1">Aucune carte enregistrée</h3>
                    <p class="text-gray-500 text-sm">Ajoutez votre première carte via le formulaire ci-dessus.</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <?php foreach ($cards as $card):
                        $style = get_card_icon($card['card_type']);
                        $decrypted = decrypt_card($card['card_number']);
                        $last4 = $decrypted ? substr($decrypted, -4) : '????';
                        $date_added = date('d/m/Y', strtotime($card['created_at']));
                    ?>
                    <div class="glass-panel rounded-xl p-5 card-item relative group">
                        <div class="flex items-start justify-between mb-4">
                            <div class="flex items-center gap-3">
                                <div class="w-12 h-12 rounded-lg bg-white/5 border border-white/10 flex items-center justify-center">
                                    <i class="fab <?php echo $style['icon'] . ' ' . $style['color'] . ' text-2xl'; ?>"></i>
                                </div>
                                <div>
                                    <div class="text-white font-bold text-sm"><?php echo htmlspecialchars($card['card_holder']); ?></div>
                                    <div class="text-gray-500 text-xs font-mono">•••• •••• •••• <?php echo $last4; ?></div>
                                </div>
                            </div>
                            <form method="post" onsubmit="return confirm('Supprimer cette carte définitivement ?')">
                                <input type="hidden" name="card_id" value="<?php echo $card['id']; ?>">
                                <button type="submit" name="delete_card" class="text-gray-500 hover:text-red-400 transition p-2 rounded-lg hover:bg-red-500/10" title="Supprimer">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </form>
                        </div>
                        <div class="flex items-center justify-between pt-3 border-t border-white/5">
                            <span class="text-[10px] text-gray-500 uppercase tracking-wider">Expire : <?php echo htmlspecialchars($card['card_expiry']); ?></span>
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
// Formatage automatique du numéro de carte
document.querySelector('input[name="card_number"]')?.addEventListener('input', function(e) {
    let v = this.value.replace(/\D/g, '').substring(0, 19);
    this.value = v.replace(/(\d{4})(?=\d)/g, '$1 ');
});

// Formatage automatique de la date d'expiration
document.querySelector('input[name="card_expiry"]')?.addEventListener('input', function(e) {
    let v = this.value.replace(/\D/g, '').substring(0, 4);
    if (v.length >= 3) v = v.substring(0, 2) + '/' + v.substring(2);
    this.value = v;
});
</script>

</body>
</html>
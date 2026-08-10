<?php
session_start();

ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/lang.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/AuthService.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/settings.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /login/');
    exit();
}

define('ENCRYPTION_KEY', 'UneVraieCleSecreteDe32CaracteresIci!!');

$active_nav = 'profile';
$message = '';
$message_type = 'info';
$active_section = $_GET['section'] ?? 'identity';

// ── Connexion DB ──
try {
    $pdo = new PDO('mysql:host=localhost;dbname=s43_orinheberge;charset=utf8mb4', 'root', '1504', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (Exception $e) {
    die('Erreur DB');
}

// ── Récupérer user ──
$stmt = $pdo->prepare('SELECT id, firstname, lastname, pseudo, email, avatar FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    header('Location: /login/');
    exit();
}

// ── Auth Service ──
$auth = new AuthService($pdo);
$oauth_providers = $auth->getUserOAuthProviders($_SESSION['user_id']);
$stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$hasPassword = !empty($stmt->fetch()['password']);

// ── Stripe ──
$stripe_pub_key = get_setting('stripe_public_key');
$stripe_secret_key = get_setting('stripe_secret_key');
$stripe_enabled = !empty($stripe_pub_key) && !empty($stripe_secret_key);
$payment_methods = [];
$stripe_customer_id = null;

if ($stripe_enabled) {
    try {
        if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php')) {
            require_once $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';
            \Stripe\Stripe::setApiKey($stripe_secret_key);

            $stmt = $pdo->prepare('SELECT stripe_customer_id FROM users WHERE id = ?');
            $stmt->execute([$_SESSION['user_id']]);
            $stripe_customer_id = $stmt->fetch()['stripe_customer_id'] ?? null;

            if (empty($stripe_customer_id)) {
                $customer = \Stripe\Customer::create([
                    'email' => $user['email'],
                    'name' => trim($user['firstname'] . ' ' . $user['lastname']),
                    'metadata' => ['user_id' => $user['id']]
                ]);
                $pdo->prepare('UPDATE users SET stripe_customer_id = ? WHERE id = ?')
                    ->execute([$customer->id, $user['id']]);
                $stripe_customer_id = $customer->id;
            }

            if ($stripe_customer_id) {
                $pm_list = \Stripe\PaymentMethod::all([
                    'customer' => $stripe_customer_id,
                    'type' => 'card',
                ]);
                $payment_methods = $pm_list->data;
            }
        }
    } catch (Exception $e) {
        error_log('[Stripe] Erreur: ' . $e->getMessage());
    }
}

// ── Notifications preferences ──
$notif_prefs = method_exists($auth, 'getNotificationPreferences') 
    ? $auth->getNotificationPreferences($_SESSION['user_id']) 
    : [];

// ── Login history ──
$login_history = method_exists($auth, 'getLoginHistory')
    ? $auth->getLoginHistory($_SESSION['user_id'], 15)
    : [];

// ── Helpers ──
function encrypt_data($data) {
    return openssl_encrypt($data, 'AES-256-CBC', ENCRYPTION_KEY, 0, substr(hash('sha256', ENCRYPTION_KEY), 0, 16));
}
function decrypt_data($data) {
    return openssl_decrypt($data, 'AES-256-CBC', ENCRYPTION_KEY, 0, substr(hash('sha256', ENCRYPTION_KEY), 0, 16));
}
function parseUserAgent($ua) {
    $result = ['browser' => 'Unknown', 'os' => 'Unknown', 'icon' => 'fa-question-circle'];
    if (preg_match('/Firefox/i', $ua)) { $result['browser'] = 'Firefox'; $result['icon'] = 'fa-firefox-browser'; }
    elseif (preg_match('/Edg/i', $ua)) { $result['browser'] = 'Edge'; $result['icon'] = 'fa-edge'; }
    elseif (preg_match('/Chrome/i', $ua)) { $result['browser'] = 'Chrome'; $result['icon'] = 'fa-chrome'; }
    elseif (preg_match('/Safari/i', $ua)) { $result['browser'] = 'Safari'; $result['icon'] = 'fa-safari'; }
    elseif (preg_match('/Opera|OPR/i', $ua)) { $result['browser'] = 'Opera'; $result['icon'] = 'fa-opera'; }
    if (preg_match('/Windows/i', $ua)) $result['os'] = 'Windows';
    elseif (preg_match('/Macintosh|Mac OS/i', $ua)) $result['os'] = 'macOS';
    elseif (preg_match('/Linux/i', $ua)) $result['os'] = 'Linux';
    elseif (preg_match('/Android/i', $ua)) $result['os'] = 'Android';
    elseif (preg_match('/iPhone|iPad/i', $ua)) $result['os'] = 'iOS';
    return $result;
}
function timeAgo($datetime) {
    $now = new DateTime();
    $past = new DateTime($datetime);
    $diff = $now->diff($past);
    if ($diff->y > 0) return 'il y a ' . $diff->y . ' an' . ($diff->y > 1 ? 's' : '');
    if ($diff->m > 0) return 'il y a ' . $diff->m . ' mois';
    if ($diff->d > 0) return 'il y a ' . $diff->d . ' jour' . ($diff->d > 1 ? 's' : '');
    if ($diff->h > 0) return 'il y a ' . $diff->h . 'h';
    if ($diff->i > 0) return 'il y a ' . $diff->i . 'min';
    return "à l'instant";
}

// ── Traitement POST ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        // ═══════════════════════════════════════════
        // 1. MISE À JOUR PROFIL
        // ═══════════════════════════════════════════
        if ($action === 'update_profile') {
            $firstname = trim($_POST['firstname'] ?? '');
            $lastname  = trim($_POST['lastname']  ?? '');
            $pseudo    = trim($_POST['pseudo']    ?? '');
            $email     = trim($_POST['email']     ?? '');
            $password  = $_POST['password']         ?? '';
            $password_confirm = $_POST['password_confirm'] ?? '';
            $new_avatar_path = $user['avatar'];

            if ($firstname === '' || $lastname === '' || $email === '') {
                throw new Exception('Le prénom, le nom et l\'email sont obligatoires.');
            }

            if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                $file_tmp = $_FILES['avatar']['tmp_name'];
                $file_ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
                if (!in_array($file_ext, ['jpg','jpeg','png','webp'])) {
                    throw new Exception('Format invalide. JPG, PNG, WEBP seulement.');
                }
                if ($_FILES['avatar']['size'] > 2 * 1024 * 1024) {
                    throw new Exception('Image trop lourde (max 2 Mo).');
                }

                $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/inc/uploads/avatars/';
                if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

                $new_file_name = uniqid('avatar_', true) . '.' . $file_ext;
                if (move_uploaded_file($file_tmp, $upload_dir . $new_file_name)) {
                    if (!empty($user['avatar']) && file_exists($_SERVER['DOCUMENT_ROOT'] . '/' . $user['avatar'])) {
                        @unlink($_SERVER['DOCUMENT_ROOT'] . '/' . $user['avatar']);
                    }
                    $new_avatar_path = 'inc/uploads/avatars/' . $new_file_name;
                } else {
                    throw new Exception("Échec de l'enregistrement de l'image.");
                }
            }

            $password_sql = '';
            $params = [$firstname, $lastname, $pseudo, $email, $new_avatar_path];
            if ($password !== '') {
                if ($password !== $password_confirm) throw new Exception('Les mots de passe ne correspondent pas.');
                if (strlen($password) < 8) throw new Exception('Minimum 8 caractères.');
                if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
                    throw new Exception('Le mot de passe doit contenir des lettres et des chiffres.');
                }
                $password_sql = ', password = ?';
                $params[] = password_hash($password, PASSWORD_BCRYPT);
            }
            $params[] = $user['id'];
            $pdo->prepare("UPDATE users SET firstname=?,lastname=?,pseudo=?,email=?,avatar=? {$password_sql} WHERE id=?")->execute($params);

            $_SESSION['username'] = !empty($pseudo) ? $pseudo : $firstname;
            $_SESSION['avatar'] = $new_avatar_path;
            $_SESSION['name'] = $firstname;

            $stmt = $pdo->prepare('SELECT id, firstname, lastname, pseudo, email, avatar FROM users WHERE id = ?');
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();

            $message = '✅ Profil mis à jour avec succès.';
            $message_type = 'success';
        }

        // ═══════════════════════════════════════════
        // 2. SUPPRESSION CARTE STRIPE
        // ═══════════════════════════════════════════
        if ($action === 'delete_card') {
            $pm_id = trim($_POST['payment_method_id'] ?? '');
            if ($pm_id && $stripe_enabled) {
                $pm = \Stripe\PaymentMethod::retrieve($pm_id);
                $pm->detach();
                try {
                    $pdo->prepare('DELETE FROM user_stripe_cards WHERE user_id = ? AND payment_method_id = ?')
                        ->execute([$_SESSION['user_id'], $pm_id]);
                } catch (Exception $e) { /* table peut ne pas exister */ }
                
                $pm_list = \Stripe\PaymentMethod::all(['customer' => $stripe_customer_id, 'type' => 'card']);
                $payment_methods = $pm_list->data;
                
                $message = '✅ Carte supprimée avec succès.';
                $message_type = 'success';
            }
        }

        // ═══════════════════════════════════════════
        // 3. PRÉFÉRENCES NOTIFICATIONS
        // ═══════════════════════════════════════════
        if ($action === 'update_notifications') {
            $prefs = [
                'newsletter' => isset($_POST['newsletter']),
                'security_alerts' => isset($_POST['security_alerts']),
                'payment_notifications' => isset($_POST['payment_notifications']),
                'support_tickets' => isset($_POST['support_tickets']),
                'maintenance_alerts' => isset($_POST['maintenance_alerts']),
                'marketing_emails' => isset($_POST['marketing_emails']),
                'product_updates' => isset($_POST['product_updates']),
                'email_digest' => $_POST['email_digest'] ?? 'none'
            ];
            
            if (method_exists($auth, 'saveNotificationPreferences')) {
                $result = $auth->saveNotificationPreferences($_SESSION['user_id'], $prefs);
                if (!empty($result['success'])) {
                    $notif_prefs = $prefs;
                    $message = '✅ Préférences de notifications mises à jour.';
                    $message_type = 'success';
                } else {
                    throw new Exception('Erreur lors de la sauvegarde des préférences.');
                }
            } else {
                throw new Exception('Fonction saveNotificationPreferences non disponible.');
            }
        }

        // ═══════════════════════════════════════════
        // 4. DÉCONNEXION TOUS LES APPAREILS
        // ═══════════════════════════════════════════
        if ($action === 'logout_all') {
            try {
                $pdo->prepare("DELETE FROM remember_tokens WHERE user_id = ?")->execute([$_SESSION['user_id']]);
            } catch (Exception $e) { /* table peut ne pas exister */ }
            
            $message = '✅ Tous les autres appareils ont été déconnectés.';
            $message_type = 'success';
        }

    } catch (Exception $e) {
        $message = '❌ ' . $e->getMessage();
        $message_type = 'error';
    }
}

// Avatar URL
$avatar_url = !empty($user['avatar']) 
    ? '/' . $user['avatar'] 
    : 'https://ui-avatars.com/api/?name=' . urlencode($user['firstname']) . '&background=0284c7&color=fff&size=150';

// Vérifier providers
$discordLinked = false;
$googleLinked = false;
foreach ($oauth_providers as $p) {
    if ($p['provider'] === 'discord') $discordLinked = true;
    if ($p['provider'] === 'google') $googleLinked = true;
}

// Messages flash
if (isset($_GET['success'])) {
    $message = '✅ Carte ajoutée avec succès.';
    $message_type = 'success';
}
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?php echo t('profil.title'); ?> - OrinHeberge</title>
    <link rel="icon" type="image/png" href="https://heberge.orinstone.deepstone.fr/favicon.ico">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <?php if ($stripe_enabled): ?>
    <script src="https://js.stripe.com/v3/"></script>
    <?php endif; ?>
    <style>
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: #0f172a; }
        ::-webkit-scrollbar-thumb { background: #334155; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #475569; }

        body {
            background-color: #0f172a;
            background-image: radial-gradient(at 0% 0%, hsla(253,16%,7%,1) 0, transparent 50%), 
                              radial-gradient(at 50% 0%, hsla(225,39%,30%,1) 0, transparent 50%), 
                              radial-gradient(at 100% 0%, hsla(339,49%,30%,1) 0, transparent 50%);
        }
        .glass-panel { 
            background: rgba(30, 41, 59, 0.7); 
            backdrop-filter: blur(12px); 
            border: 1px solid rgba(255, 255, 255, 0.08); 
        }
        .sidebar-link { transition: all 0.2s ease; border-left: 3px solid transparent; }
        .sidebar-link:hover, .sidebar-link.active { 
            background: rgba(56, 189, 248, 0.1); 
            border-left-color: #38bdf8; 
            color: #38bdf8; 
        }
        .input-field { 
            background: rgba(15, 23, 42, 0.6); 
            border: 1px solid rgba(255,255,255,0.1); 
            transition: all 0.3s; 
        }
        .input-field:focus { 
            border-color: #38bdf8; 
            box-shadow: 0 0 0 2px rgba(56, 189, 248, 0.2); 
            outline: none; 
        }
        .StripeElement { 
            background: rgba(15, 23, 42, 0.6); 
            border: 1px solid rgba(255,255,255,0.1); 
            border-radius: 0.5rem; 
            padding: 0.75rem 1rem; 
            color: white; 
        }
        .StripeElement--focus { 
            border-color: #38bdf8; 
            box-shadow: 0 0 0 2px rgba(56, 189, 248, 0.2); 
        }
        .section-anchor { scroll-margin-top: 100px; }
        @media (min-width: 1024px) {
            .profile-sidebar { position: sticky; top: 100px; }
        }
        .custom-scrollbar::-webkit-scrollbar { width: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 3px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.2); }
        @keyframes fade-in {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in { animation: fade-in 0.3s ease-out; }
    </style>
</head>
<body class="min-h-screen text-gray-300 font-sans flex flex-col">

    <?php include $_SERVER['DOCUMENT_ROOT'] . '/inc/navbar.php'; ?>

    <div class="flex-1 w-full">
        <main class="p-6 lg:p-10 w-full max-w-7xl mx-auto">
            
            <!-- HEADER PROFIL -->
            <div class="mb-8 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="flex items-center gap-4">
                    <img src="<?php echo htmlspecialchars($avatar_url); ?>" 
                         class="w-16 h-16 rounded-full border-4 border-white/10 object-cover shadow-xl"
                         onerror="this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($user['firstname']); ?>'">
                    <div>
                        <h1 class="text-3xl font-bold text-white mb-1">
                            <?php echo htmlspecialchars($user['pseudo'] ?: $user['firstname']); ?>
                        </h1>
                        <p class="text-gray-400 text-sm">
                            <i class="fas fa-envelope mr-1"></i>
                            <?php echo htmlspecialchars($user['email']); ?>
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <?php if ($discordLinked): ?>
                        <span class="bg-[#5865F2]/20 border border-[#5865F2]/30 text-[#5865F2] px-3 py-1 rounded-full text-xs font-bold">
                            <i class="fab fa-discord mr-1"></i>Discord
                        </span>
                    <?php endif; ?>
                    <?php if ($googleLinked): ?>
                        <span class="bg-white/10 border border-white/20 text-white px-3 py-1 rounded-full text-xs font-bold">
                            <i class="fab fa-google mr-1"></i>Google
                        </span>
                    <?php endif; ?>
                    <?php if ($hasPassword): ?>
                        <span class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 px-3 py-1 rounded-full text-xs font-bold">
                            <i class="fas fa-key mr-1"></i>Local
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- MESSAGE GLOBAL -->
            <?php if ($message): ?>
            <div id="globalMessage" class="mb-6 p-4 rounded-xl border animate-fade-in <?php echo $message_type === 'success' ? 'bg-green-500/10 border-green-500/30 text-green-400' : 'bg-red-500/10 border-red-500/30 text-red-400'; ?>">
                <i class="fas <?php echo $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?> mr-2"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
            <?php endif; ?>

            <!-- LAYOUT : SIDEBAR + CONTENU -->
            <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
                
                <!-- SIDEBAR -->
                <aside class="lg:col-span-1">
                    <div class="profile-sidebar glass-panel rounded-2xl p-4 space-y-1">
                        <a href="#identity" data-section="identity" class="sidebar-link active flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium text-gray-300">
                            <i class="fas fa-id-card w-5"></i> Identité
                        </a>
                        <a href="#security" data-section="security" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium text-gray-300">
                            <i class="fas fa-shield-alt w-5"></i> Sécurité
                        </a>
                        <a href="#oauth" data-section="oauth" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium text-gray-300">
                            <i class="fas fa-link w-5"></i> Comptes connectés
                            <?php if (count($oauth_providers) > 0): ?>
                                <span class="ml-auto bg-sky-500/20 text-sky-400 text-xs px-2 py-0.5 rounded-full"><?php echo count($oauth_providers); ?></span>
                            <?php endif; ?>
                        </a>
                        <?php if ($stripe_enabled): ?>
                        <a href="#payment" data-section="payment" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium text-gray-300">
                            <i class="fas fa-credit-card w-5"></i> Paiement
                            <?php if (count($payment_methods) > 0): ?>
                                <span class="ml-auto bg-sky-500/20 text-sky-400 text-xs px-2 py-0.5 rounded-full"><?php echo count($payment_methods); ?></span>
                            <?php endif; ?>
                        </a>
                        <?php endif; ?>
                        <a href="#notifications" data-section="notifications" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium text-gray-300">
                            <i class="fas fa-bell w-5"></i> Notifications
                        </a>
                        <a href="#activity" data-section="activity" class="sidebar-link flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium text-gray-300">
                            <i class="fas fa-history w-5"></i> Activité
                            <?php if (count($login_history) > 0): ?>
                                <span class="ml-auto bg-sky-500/20 text-sky-400 text-xs px-2 py-0.5 rounded-full"><?php echo count($login_history); ?></span>
                            <?php endif; ?>
                        </a>
                        
                        <hr class="border-white/10 my-3">
                        
                        <a href="/client/" class="flex items-center gap-3 px-4 py-3 rounded-lg text-sm font-medium text-gray-400 hover:text-white hover:bg-white/5 transition">
                            <i class="fas fa-arrow-left w-5"></i> Retour Dashboard
                        </a>
                    </div>
                </aside>

                <!-- CONTENU -->
                <div class="lg:col-span-3 space-y-6 pb-10">
                    
                    <!-- SECTION 1 : IDENTITÉ -->
                    <section id="identity" class="section-anchor">
                        <form method="post" enctype="multipart/form-data" class="glass-panel rounded-2xl p-6 lg:p-8">
                            <input type="hidden" name="action" value="update_profile">
                            
                            <h2 class="text-xl font-bold text-white mb-6 flex items-center gap-2">
                                <i class="fas fa-id-card text-sky-500"></i> Informations Personnelles
                            </h2>

                            <div class="flex flex-col md:flex-row gap-8 items-start">
                                <div class="w-full md:w-auto flex flex-col items-center gap-3">
                                    <div class="relative group cursor-pointer">
                                        <img id="avatarPreview" src="<?php echo htmlspecialchars($avatar_url); ?>" 
                                             class="w-28 h-28 rounded-full object-cover border-4 border-white/5 shadow-xl">
                                        <div class="absolute inset-0 bg-black/60 rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition duration-300">
                                            <i class="fas fa-camera text-white text-xl"></i>
                                        </div>
                                        <input type="file" name="avatar" accept="image/*" id="avatarInput"
                                               class="absolute inset-0 opacity-0 cursor-pointer">
                                    </div>
                                    <span class="text-xs text-gray-500">Max 2MB • JPG/PNG/WEBP</span>
                                </div>

                                <div class="flex-1 grid grid-cols-1 sm:grid-cols-2 gap-5 w-full">
                                    <div class="col-span-2 sm:col-span-1">
                                        <label class="block text-xs font-bold uppercase text-gray-500 mb-2">Prénom</label>
                                        <input type="text" name="firstname" required 
                                               value="<?php echo htmlspecialchars($user['firstname']); ?>" 
                                               class="input-field w-full rounded-lg px-4 py-3 text-white">
                                    </div>
                                    <div class="col-span-2 sm:col-span-1">
                                        <label class="block text-xs font-bold uppercase text-gray-500 mb-2">Nom</label>
                                        <input type="text" name="lastname" required 
                                               value="<?php echo htmlspecialchars($user['lastname']); ?>" 
                                               class="input-field w-full rounded-lg px-4 py-3 text-white">
                                    </div>
                                    <div class="col-span-2">
                                        <label class="block text-xs font-bold uppercase text-gray-500 mb-2">Pseudo</label>
                                        <input type="text" name="pseudo" 
                                               value="<?php echo htmlspecialchars($user['pseudo']); ?>" 
                                               class="input-field w-full rounded-lg px-4 py-3 text-white"
                                               placeholder="Affiché publiquement">
                                    </div>
                                    <div class="col-span-2">
                                        <label class="block text-xs font-bold uppercase text-gray-500 mb-2">Email</label>
                                        <input type="email" name="email" required 
                                               value="<?php echo htmlspecialchars($user['email']); ?>" 
                                               class="input-field w-full rounded-lg px-4 py-3 text-white">
                                    </div>
                                </div>
                            </div>

                            <div class="flex justify-end mt-6 pt-6 border-t border-white/5">
                                <button type="submit" class="bg-sky-600 hover:bg-sky-500 text-white px-8 py-3 rounded-xl font-bold shadow-lg shadow-sky-600/20 transition transform hover:-translate-y-1 active:scale-95">
                                    <i class="fas fa-save mr-2"></i>Enregistrer les modifications
                                </button>
                            </div>
                        </form>
                    </section>

                    <!-- SECTION 2 : SÉCURITÉ -->
                    <section id="security" class="section-anchor">
                        <div class="glass-panel rounded-2xl p-6 lg:p-8">
                            <h2 class="text-xl font-bold text-white mb-6 flex items-center gap-2">
                                <i class="fas fa-shield-alt text-sky-500"></i> Sécurité
                            </h2>
                            
                            <p class="text-sm text-gray-400 mb-6">
                                Modifiez votre mot de passe pour sécuriser votre compte.
                                <?php if (!$hasPassword): ?>
                                    <span class="text-amber-400 font-bold">⚠️ Votre compte n'a pas encore de mot de passe local.</span>
                                <?php endif; ?>
                            </p>

                            <form method="post" class="space-y-5">
                                <input type="hidden" name="action" value="update_profile">
                                <input type="hidden" name="firstname" value="<?php echo htmlspecialchars($user['firstname']); ?>">
                                <input type="hidden" name="lastname" value="<?php echo htmlspecialchars($user['lastname']); ?>">
                                <input type="hidden" name="pseudo" value="<?php echo htmlspecialchars($user['pseudo']); ?>">
                                <input type="hidden" name="email" value="<?php echo htmlspecialchars($user['email']); ?>">

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                                    <div>
                                        <label class="block text-xs font-bold uppercase text-gray-500 mb-2">Nouveau mot de passe</label>
                                        <div class="relative">
                                            <input type="password" name="password" placeholder="••••••••" 
                                                   class="input-field w-full rounded-lg px-4 py-3 text-white pr-10"
                                                   minlength="8">
                                            <button type="button" onclick="togglePassword('password', this)" 
                                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-300">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold uppercase text-gray-500 mb-2">Confirmation</label>
                                        <input type="password" name="password_confirm" placeholder="••••••••" 
                                               class="input-field w-full rounded-lg px-4 py-3 text-white">
                                    </div>
                                </div>

                                <div class="h-1 bg-white/5 rounded-full overflow-hidden">
                                    <div id="strengthBar" class="h-full transition-all duration-300" style="width: 0%; background: #ef4444;"></div>
                                </div>
                                <p id="strengthText" class="text-xs text-gray-500">Minimum 8 caractères avec lettres et chiffres</p>

                                <div class="flex items-center justify-between pt-4 border-t border-white/5">
                                    <a href="/forgotpassword/" class="text-sm text-sky-400 hover:text-sky-300 transition">
                                        <i class="fas fa-key mr-1"></i>Mot de passe oublié ?
                                    </a>
                                    <button type="submit" class="bg-sky-600 hover:bg-sky-500 text-white px-6 py-2.5 rounded-xl font-bold transition">
                                        <i class="fas fa-lock mr-2"></i>Changer le mot de passe
                                    </button>
                                </div>
                            </form>
                        </div>
                    </section>

                    <!-- SECTION 3 : COMPTES CONNECTÉS -->
                    <section id="oauth" class="section-anchor">
                        <div class="glass-panel rounded-2xl p-6 lg:p-8">
                            <h2 class="text-xl font-bold text-white mb-2 flex items-center gap-2">
                                <i class="fas fa-link text-sky-500"></i> Comptes Connectés
                            </h2>
                            <p class="text-sm text-gray-400 mb-6">
                                Liez vos comptes externes pour une connexion rapide.
                            </p>

                            <div id="oauthMessageBox" class="hidden mb-4 p-3 rounded-xl text-sm"></div>

                            <div class="space-y-3">
                                <!-- Discord -->
                                <div class="flex items-center justify-between p-4 rounded-xl border transition 
                                    <?= $discordLinked ? 'bg-[#5865F2]/10 border-[#5865F2]/30' : 'bg-white/5 border-white/10 hover:bg-white/10' ?>">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-lg bg-[#5865F2]/20 flex items-center justify-center">
                                            <i class="fab fa-discord text-xl text-[#5865F2]"></i>
                                        </div>
                                        <div>
                                            <div class="font-bold text-white">Discord</div>
                                            <div class="text-xs text-gray-400">
                                                <?php if ($discordLinked): ?>
                                                    <span class="text-green-400">✓ Connecté</span>
                                                    <?php 
                                                    foreach ($oauth_providers as $p) {
                                                        if ($p['provider'] === 'discord') {
                                                            echo ' • ' . htmlspecialchars($p['provider_email'] ?? '');
                                                            break;
                                                        }
                                                    }
                                                    ?>
                                                <?php else: ?>
                                                    Non connecté
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <?php if ($discordLinked): ?>
                                        <button onclick="unlinkProvider('discord')" 
                                                class="text-xs bg-red-500/20 hover:bg-red-500/30 text-red-400 px-3 py-1.5 rounded-lg transition">
                                            <i class="fas fa-unlink mr-1"></i>Dissocier
                                        </button>
                                    <?php else: ?>
                                        <a href="/api/auth/discord.php" 
                                           class="text-xs bg-[#5865F2] hover:bg-[#4752C4] text-white px-4 py-1.5 rounded-lg transition font-bold">
                                            <i class="fas fa-link mr-1"></i>Connecter
                                        </a>
                                    <?php endif; ?>
                                </div>

                                <!-- Google -->
                                <div class="flex items-center justify-between p-4 rounded-xl border transition 
                                    <?= $googleLinked ? 'bg-white/10 border-white/30' : 'bg-white/5 border-white/10 hover:bg-white/10' ?>">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-lg bg-white/10 flex items-center justify-center">
                                            <i class="fab fa-google text-xl text-white"></i>
                                        </div>
                                        <div>
                                            <div class="font-bold text-white">Google</div>
                                            <div class="text-xs text-gray-400">
                                                <?php if ($googleLinked): ?>
                                                    <span class="text-green-400">✓ Connecté</span>
                                                    <?php 
                                                    foreach ($oauth_providers as $p) {
                                                        if ($p['provider'] === 'google') {
                                                            echo ' • ' . htmlspecialchars($p['provider_email'] ?? '');
                                                            break;
                                                        }
                                                    }
                                                    ?>
                                                <?php else: ?>
                                                    Non connecté
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <?php if ($googleLinked): ?>
                                        <button onclick="unlinkProvider('google')" 
                                                class="text-xs bg-red-500/20 hover:bg-red-500/30 text-red-400 px-3 py-1.5 rounded-lg transition">
                                            <i class="fas fa-unlink mr-1"></i>Dissocier
                                        </button>
                                    <?php else: ?>
                                        <a href="/api/auth/google.php" 
                                           class="text-xs bg-white hover:bg-gray-100 text-gray-800 px-4 py-1.5 rounded-lg transition font-bold">
                                            <i class="fas fa-link mr-1"></i>Connecter
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="mt-6 bg-sky-500/10 border border-sky-500/30 rounded-xl p-4 text-sm text-sky-300">
                                <i class="fas fa-info-circle mr-2"></i>
                                <strong>Sécurité :</strong> Gardez au moins un moyen de connexion actif.
                                <?php if (!$hasPassword && count($oauth_providers) <= 1): ?>
                                    <br><span class="text-amber-400">⚠️ Attention : vous n'avez qu'un seul moyen de connexion !</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </section>

                    <!-- SECTION 4 : PAIEMENT -->
                    <?php if ($stripe_enabled): ?>
                    <section id="payment" class="section-anchor">
                        <div class="glass-panel rounded-2xl p-6 lg:p-8">
                            <h2 class="text-xl font-bold text-white mb-2 flex items-center gap-2">
                                <i class="fas fa-credit-card text-sky-500"></i> Moyens de Paiement
                            </h2>
                            <p class="text-sm text-gray-400 mb-6">
                                <i class="fas fa-lock text-emerald-400 mr-1"></i>
                                Cartes sécurisées via Stripe avec 3D Secure.
                            </p>

                            <div class="bg-white/5 border border-white/10 rounded-xl p-5 mb-6">
                                <h3 class="text-sm font-bold text-white mb-4 flex items-center gap-2">
                                    <i class="fas fa-plus-circle text-sky-400"></i> Ajouter une carte
                                </h3>
                                <form id="payment-form" class="space-y-4">
                                    <div id="card-element" class="StripeElement"></div>
                                    <div id="card-errors" role="alert" class="text-red-400 text-sm hidden">
                                        <i class="fas fa-exclamation-circle mr-1"></i>
                                        <span class="error-message"></span>
                                    </div>
                                    <div class="flex items-center justify-between pt-2">
                                        <p class="text-xs text-gray-500">
                                            <i class="fas fa-shield-alt text-emerald-400 mr-1"></i>
                                            Paiement 0,00€ pour validation
                                        </p>
                                        <button type="submit" id="submit-button" 
                                                class="bg-sky-600 hover:bg-sky-500 text-white px-5 py-2 rounded-lg font-bold text-sm transition">
                                            <i class="fas fa-shield-alt mr-2"></i>Valider avec ma banque
                                        </button>
                                    </div>
                                </form>
                            </div>

                            <h3 class="text-sm font-bold text-white mb-4 flex items-center gap-2">
                                <i class="fas fa-wallet text-sky-400"></i>
                                Cartes enregistrées (<?php echo count($payment_methods); ?>)
                            </h3>

                            <?php if (empty($payment_methods)): ?>
                            <div class="rounded-xl p-8 text-center border-dashed border-2 border-white/5">
                                <i class="fas fa-credit-card text-3xl text-gray-600 mb-3"></i>
                                <p class="text-gray-500 text-sm">Aucune carte enregistrée</p>
                            </div>
                            <?php else: ?>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <?php foreach ($payment_methods as $pm): 
                                    $card = $pm->card;
                                    $brand = strtolower($card->brand);
                                    $brandIcon = [
                                        'visa' => 'cc-visa text-blue-400',
                                        'mastercard' => 'cc-mastercard text-red-400',
                                        'amex' => 'cc-amex text-blue-300',
                                    ][$brand] ?? 'fa-credit-card text-gray-400';
                                ?>
                                <div class="flex items-center justify-between p-4 bg-white/5 border border-white/10 rounded-xl hover:bg-white/10 transition">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-lg bg-white/5 flex items-center justify-center">
                                            <i class="fab <?php echo $brandIcon; ?> text-xl"></i>
                                        </div>
                                        <div>
                                            <div class="text-white font-bold text-sm">
                                                •••• <?php echo htmlspecialchars($card->last4); ?>
                                            </div>
                                            <div class="text-gray-500 text-xs">
                                                <?php echo htmlspecialchars($card->brand); ?> • 
                                                Exp: <?php echo str_pad($card->exp_month, 2, '0', STR_PAD_LEFT); ?>/<?php echo substr($card->exp_year, -2); ?>
                                            </div>
                                        </div>
                                    </div>
                                    <form method="post" onsubmit="return confirm('Supprimer cette carte ?')">
                                        <input type="hidden" name="action" value="delete_card">
                                        <input type="hidden" name="payment_method_id" value="<?php echo htmlspecialchars($pm->id); ?>">
                                        <button type="submit" class="text-gray-500 hover:text-red-400 transition p-2">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </section>
                    <?php endif; ?>

                    <!-- ═══════════════════════════════════════════ -->
                    <!-- SECTION 5 : NOTIFICATIONS -->
                    <!-- ═══════════════════════════════════════════ -->
                    <section id="notifications" class="section-anchor">
                        <form method="post" class="glass-panel rounded-2xl p-6 lg:p-8">
                            <input type="hidden" name="action" value="update_notifications">
                            
                            <div class="flex items-start justify-between mb-6">
                                <div>
                                    <h2 class="text-xl font-bold text-white mb-1 flex items-center gap-2">
                                        <i class="fas fa-bell text-sky-500"></i> Préférences de Notifications
                                    </h2>
                                    <p class="text-sm text-gray-400">
                                        Choisissez les emails que vous souhaitez recevoir.
                                    </p>
                                </div>
                                <div class="hidden sm:flex items-center gap-2 text-xs text-gray-500">
                                    <i class="fas fa-envelope text-sky-400"></i>
                                    <span><?php echo htmlspecialchars($user['email']); ?></span>
                                </div>
                            </div>

                            <!-- Toggle tous -->
                            <div class="flex items-center justify-between p-4 bg-sky-500/5 border border-sky-500/20 rounded-xl mb-6">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 rounded-lg bg-sky-500/20 flex items-center justify-center">
                                        <i class="fas fa-power-off text-sky-400"></i>
                                    </div>
                                    <div>
                                        <div class="font-bold text-white text-sm">Activer toutes les notifications</div>
                                        <div class="text-xs text-gray-400">Interrupteur général</div>
                                    </div>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" id="toggleAll" class="sr-only peer" checked>
                                    <div class="w-11 h-6 bg-gray-700 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-sky-500"></div>
                                </label>
                            </div>

                            <div class="space-y-3">
                                
                                <!-- Sécurité (OBLIGATOIRE) -->
                                <div class="p-4 bg-emerald-500/5 border border-emerald-500/20 rounded-xl">
                                    <div class="flex items-start justify-between gap-4">
                                        <div class="flex items-start gap-3 flex-1">
                                            <div class="w-9 h-9 rounded-lg bg-emerald-500/20 flex items-center justify-center shrink-0">
                                                <i class="fas fa-shield-alt text-emerald-400 text-sm"></i>
                                            </div>
                                            <div class="flex-1">
                                                <div class="font-bold text-white text-sm flex items-center gap-2">
                                                    Alertes de sécurité
                                                    <span class="text-[10px] bg-emerald-500/20 text-emerald-400 px-2 py-0.5 rounded-full uppercase font-bold">Obligatoire</span>
                                                </div>
                                                <div class="text-xs text-gray-400 mt-0.5">
                                                    Connexions suspectes, changements de mot de passe
                                                </div>
                                            </div>
                                        </div>
                                        <label class="relative inline-flex items-center cursor-not-allowed opacity-60">
                                            <input type="checkbox" name="security_alerts" checked disabled class="sr-only">
                                            <div class="w-11 h-6 bg-emerald-500 rounded-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:translate-x-full"></div>
                                        </label>
                                    </div>
                                </div>

                                <!-- Paiement -->
                                <div class="p-4 bg-white/5 border border-white/10 rounded-xl hover:bg-white/[0.07] transition">
                                    <div class="flex items-start justify-between gap-4">
                                        <div class="flex items-start gap-3 flex-1">
                                            <div class="w-9 h-9 rounded-lg bg-sky-500/20 flex items-center justify-center shrink-0">
                                                <i class="fas fa-credit-card text-sky-400 text-sm"></i>
                                            </div>
                                            <div class="flex-1">
                                                <div class="font-bold text-white text-sm">Paiements & Facturation</div>
                                                <div class="text-xs text-gray-400 mt-0.5">Confirmations, factures, renouvellements</div>
                                            </div>
                                        </div>
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" name="payment_notifications" class="sr-only peer notif-toggle" <?php echo !empty($notif_prefs['payment_notifications']) ? 'checked' : ''; ?>>
                                            <div class="w-11 h-6 bg-gray-700 rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-sky-500"></div>
                                        </label>
                                    </div>
                                </div>

                                <!-- Support -->
                                <div class="p-4 bg-white/5 border border-white/10 rounded-xl hover:bg-white/[0.07] transition">
                                    <div class="flex items-start justify-between gap-4">
                                        <div class="flex items-start gap-3 flex-1">
                                            <div class="w-9 h-9 rounded-lg bg-purple-500/20 flex items-center justify-center shrink-0">
                                                <i class="fas fa-headset text-purple-400 text-sm"></i>
                                            </div>
                                            <div class="flex-1">
                                                <div class="font-bold text-white text-sm">Tickets Support</div>
                                                <div class="text-xs text-gray-400 mt-0.5">Réponses à vos tickets</div>
                                            </div>
                                        </div>
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" name="support_tickets" class="sr-only peer notif-toggle" <?php echo !empty($notif_prefs['support_tickets']) ? 'checked' : ''; ?>>
                                            <div class="w-11 h-6 bg-gray-700 rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-sky-500"></div>
                                        </label>
                                    </div>
                                </div>

                                <!-- Maintenance -->
                                <div class="p-4 bg-white/5 border border-white/10 rounded-xl hover:bg-white/[0.07] transition">
                                    <div class="flex items-start justify-between gap-4">
                                        <div class="flex items-start gap-3 flex-1">
                                            <div class="w-9 h-9 rounded-lg bg-amber-500/20 flex items-center justify-center shrink-0">
                                                <i class="fas fa-wrench text-amber-400 text-sm"></i>
                                            </div>
                                            <div class="flex-1">
                                                <div class="font-bold text-white text-sm">Maintenance & Incidents</div>
                                                <div class="text-xs text-gray-400 mt-0.5">Mises à jour, pannes, retours à la normale</div>
                                            </div>
                                        </div>
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" name="maintenance_alerts" class="sr-only peer notif-toggle" <?php echo !empty($notif_prefs['maintenance_alerts']) ? 'checked' : ''; ?>>
                                            <div class="w-11 h-6 bg-gray-700 rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-sky-500"></div>
                                        </label>
                                    </div>
                                </div>

                                <!-- Newsletter -->
                                <div class="p-4 bg-white/5 border border-white/10 rounded-xl hover:bg-white/[0.07] transition">
                                    <div class="flex items-start justify-between gap-4">
                                        <div class="flex items-start gap-3 flex-1">
                                            <div class="w-9 h-9 rounded-lg bg-indigo-500/20 flex items-center justify-center shrink-0">
                                                <i class="fas fa-newspaper text-indigo-400 text-sm"></i>
                                            </div>
                                            <div class="flex-1">
                                                <div class="font-bold text-white text-sm">Newsletter</div>
                                                <div class="text-xs text-gray-400 mt-0.5">Actualités, conseils, tutoriels</div>
                                            </div>
                                        </div>
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" name="newsletter" class="sr-only peer notif-toggle" <?php echo !empty($notif_prefs['newsletter']) ? 'checked' : ''; ?>>
                                            <div class="w-11 h-6 bg-gray-700 rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-sky-500"></div>
                                        </label>
                                    </div>
                                </div>

                                <!-- Marketing -->
                                <div class="p-4 bg-white/5 border border-white/10 rounded-xl hover:bg-white/[0.07] transition">
                                    <div class="flex items-start justify-between gap-4">
                                        <div class="flex items-start gap-3 flex-1">
                                            <div class="w-9 h-9 rounded-lg bg-rose-500/20 flex items-center justify-center shrink-0">
                                                <i class="fas fa-gift text-rose-400 text-sm"></i>
                                            </div>
                                            <div class="flex-1">
                                                <div class="font-bold text-white text-sm">Offres promotionnelles</div>
                                                <div class="text-xs text-gray-400 mt-0.5">Promotions, codes promo, événements</div>
                                            </div>
                                        </div>
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" name="marketing_emails" class="sr-only peer notif-toggle" <?php echo !empty($notif_prefs['marketing_emails']) ? 'checked' : ''; ?>>
                                            <div class="w-11 h-6 bg-gray-700 rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-sky-500"></div>
                                        </label>
                                    </div>
                                </div>

                                <!-- Product updates -->
                                <div class="p-4 bg-white/5 border border-white/10 rounded-xl hover:bg-white/[0.07] transition">
                                    <div class="flex items-start justify-between gap-4">
                                        <div class="flex items-start gap-3 flex-1">
                                            <div class="w-9 h-9 rounded-lg bg-teal-500/20 flex items-center justify-center shrink-0">
                                                <i class="fas fa-rocket text-teal-400 text-sm"></i>
                                            </div>
                                            <div class="flex-1">
                                                <div class="font-bold text-white text-sm">Mises à jour produit</div>
                                                <div class="text-xs text-gray-400 mt-0.5">Nouvelles fonctionnalités, annonces</div>
                                            </div>
                                        </div>
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" name="product_updates" class="sr-only peer notif-toggle" <?php echo !empty($notif_prefs['product_updates']) ? 'checked' : ''; ?>>
                                            <div class="w-11 h-6 bg-gray-700 rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-sky-500"></div>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- Fréquence digest -->
                            <div class="mt-6 pt-6 border-t border-white/5">
                                <h3 class="text-sm font-bold text-white mb-3 flex items-center gap-2">
                                    <i class="fas fa-calendar-alt text-sky-400"></i> Récapitulatif périodique
                                </h3>
                                <p class="text-xs text-gray-400 mb-4">Recevez un résumé de votre activité par email.</p>
                                <div class="grid grid-cols-3 gap-2">
                                    <label class="cursor-pointer">
                                        <input type="radio" name="email_digest" value="none" class="sr-only peer" <?php echo ($notif_prefs['email_digest'] ?? 'none') === 'none' ? 'checked' : ''; ?>>
                                        <div class="p-3 bg-white/5 border border-white/10 rounded-lg text-center peer-checked:bg-sky-500/20 peer-checked:border-sky-500/50 peer-checked:text-sky-400 hover:bg-white/10 transition">
                                            <i class="fas fa-ban mb-1"></i>
                                            <div class="text-xs font-bold">Aucun</div>
                                        </div>
                                    </label>
                                    <label class="cursor-pointer">
                                        <input type="radio" name="email_digest" value="daily" class="sr-only peer" <?php echo ($notif_prefs['email_digest'] ?? '') === 'daily' ? 'checked' : ''; ?>>
                                        <div class="p-3 bg-white/5 border border-white/10 rounded-lg text-center peer-checked:bg-sky-500/20 peer-checked:border-sky-500/50 peer-checked:text-sky-400 hover:bg-white/10 transition">
                                            <i class="fas fa-sun mb-1"></i>
                                            <div class="text-xs font-bold">Quotidien</div>
                                        </div>
                                    </label>
                                    <label class="cursor-pointer">
                                        <input type="radio" name="email_digest" value="weekly" class="sr-only peer" <?php echo ($notif_prefs['email_digest'] ?? '') === 'weekly' ? 'checked' : ''; ?>>
                                        <div class="p-3 bg-white/5 border border-white/10 rounded-lg text-center peer-checked:bg-sky-500/20 peer-checked:border-sky-500/50 peer-checked:text-sky-400 hover:bg-white/10 transition">
                                            <i class="fas fa-calendar-week mb-1"></i>
                                            <div class="text-xs font-bold">Hebdomadaire</div>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <div class="flex justify-end mt-6 pt-6 border-t border-white/5">
                                <button type="submit" class="bg-sky-600 hover:bg-sky-500 text-white px-8 py-3 rounded-xl font-bold shadow-lg shadow-sky-600/20 transition transform hover:-translate-y-1 active:scale-95">
                                    <i class="fas fa-bell mr-2"></i>Enregistrer les préférences
                                </button>
                            </div>
                        </form>
                    </section>

                    <!-- ═══════════════════════════════════════════ -->
                    <!-- SECTION 6 : ACTIVITÉ -->
                    <!-- ═══════════════════════════════════════════ -->
                    <section id="activity" class="section-anchor">
                        <div class="glass-panel rounded-2xl p-6 lg:p-8">
                            <div class="flex items-start justify-between mb-6">
                                <div>
                                    <h2 class="text-xl font-bold text-white mb-1 flex items-center gap-2">
                                        <i class="fas fa-history text-sky-500"></i> Historique des Connexions
                                    </h2>
                                    <p class="text-sm text-gray-400">
                                        Consultez les appareils connectés à votre compte.
                                    </p>
                                </div>
                                <form method="post" onsubmit="return confirm('⚠️ Déconnecter tous les autres appareils ?\n\nVous resterez connecté sur cet appareil.')">
                                    <input type="hidden" name="action" value="logout_all">
                                    <button type="submit" class="text-xs bg-red-500/20 hover:bg-red-500/30 text-red-400 px-3 py-1.5 rounded-lg transition font-bold">
                                        <i class="fas fa-sign-out-alt mr-1"></i>Déconnecter tout
                                    </button>
                                </form>
                            </div>

                            <?php if (empty($login_history)): ?>
                            <div class="rounded-xl p-8 text-center border-dashed border-2 border-white/5">
                                <i class="fas fa-history text-3xl text-gray-600 mb-3"></i>
                                <p class="text-gray-500 text-sm">Aucun historique disponible</p>
                                <p class="text-xs text-gray-600 mt-2">L'historique apparaîtra après vos prochaines connexions.</p>
                            </div>
                            <?php else: ?>
                            
                            <?php 
                            $currentIp = $_SERVER['REMOTE_ADDR'] ?? '';
                            $currentUA = $_SERVER['HTTP_USER_AGENT'] ?? '';
                            $currentSession = null;
                            $otherSessions = [];
                            
                            foreach ($login_history as $entry) {
                                if ($entry['ip_address'] === $currentIp && $entry['user_agent'] === $currentUA && !$currentSession) {
                                    $currentSession = $entry;
                                } else {
                                    $otherSessions[] = $entry;
                                }
                            }
                            ?>
                            
                            <?php if ($currentSession): ?>
                            <div class="mb-6">
                                <h3 class="text-xs font-bold uppercase text-gray-500 mb-3 flex items-center gap-2">
                                    <span class="w-2 h-2 bg-emerald-400 rounded-full animate-pulse"></span>
                                    Session actuelle
                                </h3>
                                <?php $ua = parseUserAgent($currentSession['user_agent'] ?? ''); ?>
                                <div class="p-4 bg-emerald-500/5 border border-emerald-500/30 rounded-xl">
                                    <div class="flex items-center justify-between flex-wrap gap-3">
                                        <div class="flex items-center gap-3">
                                            <div class="w-12 h-12 rounded-lg bg-emerald-500/20 flex items-center justify-center">
                                                <i class="fab <?php echo $ua['icon']; ?> text-xl text-emerald-400"></i>
                                            </div>
                                            <div>
                                                <div class="font-bold text-white flex items-center gap-2">
                                                    <?php echo htmlspecialchars($ua['browser']); ?> sur <?php echo htmlspecialchars($ua['os']); ?>
                                                    <span class="text-[10px] bg-emerald-500/20 text-emerald-400 px-2 py-0.5 rounded-full uppercase font-bold">Actif</span>
                                                </div>
                                                <div class="text-xs text-gray-400 mt-0.5">
                                                    <i class="fas fa-network-wired mr-1"></i>
                                                    <?php echo htmlspecialchars($currentSession['ip_address']); ?>
                                                </div>
                                                <div class="text-xs text-gray-500 mt-0.5">
                                                    <i class="fas fa-clock mr-1"></i>
                                                    <?php echo timeAgo($currentSession['created_at']); ?>
                                                    • Via <?php echo ucfirst($currentSession['auth_method'] ?? 'local'); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($otherSessions)): ?>
                            <div>
                                <h3 class="text-xs font-bold uppercase text-gray-500 mb-3 flex items-center gap-2">
                                    <i class="fas fa-desktop"></i>
                                    Autres connexions (<?php echo count($otherSessions); ?>)
                                </h3>
                                <div class="space-y-2 max-h-[400px] overflow-y-auto custom-scrollbar pr-2">
                                    <?php foreach ($otherSessions as $entry): 
                                        $ua = parseUserAgent($entry['user_agent'] ?? '');
                                        $statusColor = [
                                            'success' => 'text-emerald-400',
                                            'failed' => 'text-red-400',
                                            'blocked' => 'text-amber-400'
                                        ][$entry['status'] ?? 'success'] ?? 'text-gray-400';
                                        
                                        $statusIcon = [
                                            'success' => 'fa-check-circle',
                                            'failed' => 'fa-times-circle',
                                            'blocked' => 'fa-ban'
                                        ][$entry['status'] ?? 'success'] ?? 'fa-question-circle';
                                    ?>
                                    <div class="p-3 bg-white/5 border border-white/10 rounded-xl hover:bg-white/[0.07] transition">
                                        <div class="flex items-center justify-between flex-wrap gap-2">
                                            <div class="flex items-center gap-3 min-w-0 flex-1">
                                                <div class="w-10 h-10 rounded-lg bg-white/5 flex items-center justify-center shrink-0">
                                                    <i class="fab <?php echo $ua['icon']; ?> text-lg text-gray-400"></i>
                                                </div>
                                                <div class="min-w-0 flex-1">
                                                    <div class="font-bold text-white text-sm flex items-center gap-2 truncate">
                                                        <?php echo htmlspecialchars($ua['browser']); ?> / <?php echo htmlspecialchars($ua['os']); ?>
                                                        <i class="fas <?php echo $statusIcon; ?> <?php echo $statusColor; ?> text-xs"></i>
                                                    </div>
                                                    <div class="text-xs text-gray-500 mt-0.5 truncate">
                                                        <i class="fas fa-network-wired mr-1"></i>
                                                        <?php echo htmlspecialchars($entry['ip_address']); ?>
                                                        • <?php echo timeAgo($entry['created_at']); ?>
                                                    </div>
                                                    <?php if (($entry['status'] ?? '') !== 'success' && !empty($entry['failure_reason'])): ?>
                                                    <div class="text-xs text-red-400 mt-1">
                                                        <i class="fas fa-exclamation-triangle mr-1"></i>
                                                        <?php echo htmlspecialchars($entry['failure_reason']); ?>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="text-xs text-gray-500">
                                                <span class="px-2 py-0.5 bg-white/5 rounded uppercase font-bold">
                                                    <?php echo htmlspecialchars($entry['auth_method'] ?? 'local'); ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php endif; ?>
                            
                            <div class="mt-6 pt-6 border-t border-white/5 bg-amber-500/5 border border-amber-500/20 rounded-xl p-4 text-sm text-amber-300">
                                <i class="fas fa-shield-alt mr-2"></i>
                                <strong>Connexion suspecte ?</strong> 
                                Changez immédiatement votre mot de passe pour sécuriser votre compte.
                                <div class="mt-2 flex gap-2 flex-wrap">
                                    <a href="#security" class="text-xs bg-amber-500/20 hover:bg-amber-500/30 text-amber-200 px-3 py-1 rounded-lg transition font-bold">
                                        <i class="fas fa-key mr-1"></i>Changer mot de passe
                                    </a>
                                </div>
                            </div>
                        </div>
                    </section>

                </div>
            </div>

        </main>
    </div>

    <?php include $_SERVER['DOCUMENT_ROOT'] . '/inc/footer.php'; ?>

    <script>
    // AVATAR PREVIEW
    document.getElementById('avatarInput')?.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = (e) => document.getElementById('avatarPreview').src = e.target.result;
            reader.readAsDataURL(file);
        }
    });

    // TOGGLE PASSWORD
    function togglePassword(inputName, btn) {
        const input = document.querySelector(`input[name="${inputName}"]`);
        if (input.type === 'password') {
            input.type = 'text';
            btn.innerHTML = '<i class="fas fa-eye-slash"></i>';
        } else {
            input.type = 'password';
            btn.innerHTML = '<i class="fas fa-eye"></i>';
        }
    }

    // PASSWORD STRENGTH
    const passwordInput = document.querySelector('input[name="password"]');
    const strengthBar = document.getElementById('strengthBar');
    const strengthText = document.getElementById('strengthText');
    
    if (passwordInput && strengthBar) {
        passwordInput.addEventListener('input', function() {
            const pw = this.value;
            let score = 0;
            if (pw.length >= 8) score++;
            if (pw.length >= 12) score++;
            if (/[A-Z]/.test(pw)) score++;
            if (/[0-9]/.test(pw)) score++;
            if (/[^A-Za-z0-9]/.test(pw)) score++;

            const levels = [
                { width: '0%', color: '#ef4444', text: 'Minimum 8 caractères avec lettres et chiffres' },
                { width: '20%', color: '#ef4444', text: '⚠️ Très faible' },
                { width: '40%', color: '#f59e0b', text: '⚠️ Faible' },
                { width: '60%', color: '#f59e0b', text: '🔶 Moyen' },
                { width: '80%', color: '#10b981', text: '✅ Fort' },
                { width: '100%', color: '#10b981', text: '🛡️ Très fort' }
            ];
            const level = levels[pw.length === 0 ? 0 : score];
            strengthBar.style.width = level.width;
            strengthBar.style.background = level.color;
            if (strengthText) strengthText.textContent = level.text;
        });
    }

    // SCROLL SPY
    const sections = document.querySelectorAll('.section-anchor');
    const navLinks = document.querySelectorAll('.sidebar-link[data-section]');
    
    if (sections.length > 0 && navLinks.length > 0) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const id = entry.target.id;
                    navLinks.forEach(link => {
                        link.classList.remove('active');
                        if (link.dataset.section === id) {
                            link.classList.add('active');
                        }
                    });
                }
            });
        }, { rootMargin: '-30% 0px -60% 0px' });
        
        sections.forEach(section => observer.observe(section));
    }

    // UNLINK OAUTH
    async function unlinkProvider(provider) {
        if (!confirm(`Dissocier ${provider} de votre compte ?`)) return;
        
        const response = await fetch('/api/auth/unlink-provider.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ provider })
        });
        
        const data = await response.json();
        const msgBox = document.getElementById('oauthMessageBox');
        
        if (data.success) {
            msgBox.className = 'mb-4 p-3 rounded-xl text-sm bg-green-500/10 border border-green-500/50 text-green-400';
            msgBox.textContent = '✅ Compte dissocié avec succès. Rechargement...';
            msgBox.classList.remove('hidden');
            setTimeout(() => location.reload(), 1200);
        } else {
            msgBox.className = 'mb-4 p-3 rounded-xl text-sm bg-red-500/10 border border-red-500/50 text-red-400';
            msgBox.textContent = data.error === 'cannot_unlink_last_provider' 
                ? '❌ Impossible : vous devez garder au moins un moyen de connexion'
                : '❌ Erreur : ' + (data.error || 'Inconnue');
            msgBox.classList.remove('hidden');
        }
    }

    // TOGGLE ALL NOTIFICATIONS
    const toggleAll = document.getElementById('toggleAll');
    if (toggleAll) {
        toggleAll.addEventListener('change', function() {
            document.querySelectorAll('.notif-toggle').forEach(t => t.checked = this.checked);
        });
    }

    // SMOOTH SCROLL
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            const targetId = this.getAttribute('href').substring(1);
            const target = document.getElementById(targetId);
            if (target) {
                e.preventDefault();
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                history.pushState(null, null, '#' + targetId);
            }
        });
    });

    // AUTO SCROLL ON LOAD
    if (window.location.hash) {
        setTimeout(() => {
            const target = document.querySelector(window.location.hash);
            if (target) target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }, 300);
    }

    // STRIPE
    <?php if ($stripe_enabled): ?>
    const stripe = Stripe('<?php echo htmlspecialchars($stripe_pub_key); ?>');
    const elements = stripe.elements();
    const cardElement = elements.create('card', {
        style: {
            base: { 
                color: '#ffffff', 
                fontSize: '14px', 
                '::placeholder': { color: '#64748b' } 
            },
            invalid: { color: '#f87171' }
        },
        hidePostalCode: true
    });
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
            const resp = await fetch('/client/profil/create-intent.php', {
                method: 'POST', 
                headers: {'Content-Type': 'application/json'}
            });
            const data = await resp.json();
            if (data.error) throw new Error(data.error);

            const result = await stripe.confirmCardSetup(data.client_secret, {
                payment_method: {
                    card: cardElement, 
                    billing_details: {
                        name: '<?php echo addslashes(trim($user['firstname'] . ' ' . $user['lastname'])); ?>'
                    }
                }
            });

            if (result.error) throw new Error(result.error.message);

            if (result.setupIntent.status === 'requires_action') {
                window.location.href = '/client/profil/verify.php?setup_intent=' + result.setupIntent.id;
                return;
            }

            const saveResp = await fetch('/client/profil/save-card.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({payment_method_id: result.setupIntent.payment_method})
            });
            const saveData = await saveResp.json();

            if (saveData.success) {
                window.location.href = '/client/profil/?success=1#payment';
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
    <?php endif; ?>

    <?php if (isset($_GET['success'])): ?>
    setTimeout(() => {
        document.getElementById('payment')?.scrollIntoView({ behavior: 'smooth' });
    }, 300);
    <?php endif; ?>
    </script>
</body>
</html>
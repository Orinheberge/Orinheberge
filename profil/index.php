<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/lang.php';

// --- CONFIGURATION SÉCURITÉ ---
define('ENCRYPTION_KEY', 'UneVraieCleSecreteDe32CaracteresIci!!'); 

if (!isset($_SESSION['user_id'])) { 
    header('Location: /login/'); 
    exit(); 
}

$is_logged_in = true;
$message = '';
$message_type = 'info';
$active_page = 'profile';

try {
    $pdo = new PDO('mysql:host=localhost;dbname=s43_orinheberge;charset=utf8mb4', 'root', '1504', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, 
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    $stmt = $pdo->prepare('SELECT id, firstname, lastname, pseudo, email, avatar FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) { 
        session_destroy(); 
        header('Location: /login/'); 
        exit(); 
    }
    
    $card_info = null;
    try {
        $stmt_card = $pdo->prepare('SELECT card_number, card_holder, card_expiry, card_type FROM user_cards WHERE user_id = ? LIMIT 1');
        $stmt_card->execute([$_SESSION['user_id']]);
        $card_info = $stmt_card->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) { /* Ignore */ }

} catch (Exception $e) {
    $message = t('profil.db_error') . " : " . $e->getMessage();
    $message_type = 'error';
}

function encrypt_data($data) {
    return openssl_encrypt($data, 'AES-256-CBC', ENCRYPTION_KEY, 0, substr(hash('sha256', ENCRYPTION_KEY), 0, 16));
}

function decrypt_data($data) {
    return openssl_decrypt($data, 'AES-256-CBC', ENCRYPTION_KEY, 0, substr(hash('sha256', ENCRYPTION_KEY), 0, 16));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstname = trim($_POST['firstname'] ?? '');
    $lastname  = trim($_POST['lastname']  ?? '');
    $pseudo    = trim($_POST['pseudo']    ?? '');
    $email     = trim($_POST['email']     ?? '');
    $password  = $_POST['password']         ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    $new_avatar_path = $user['avatar'];

    try {
        if ($firstname === '' || $lastname === '' || $email === '') throw new Exception('Le prénom, le nom et l\'adresse email sont obligatoires.');

        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $file_tmp = $_FILES['avatar']['tmp_name'];
            $file_ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
            if (!in_array($file_ext, ['jpg','jpeg','png','webp'])) throw new Exception('Format invalide. JPG, PNG, WEBP seulement.');
            if ($_FILES['avatar']['size'] > 2 * 1024 * 1024) throw new Exception('Image trop lourde (max 2 Mo).');
            
            $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/inc/uploads/avatars/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            
            $new_file_name = uniqid('avatar_', true) . '.' . $file_ext;
            if (move_uploaded_file($file_tmp, $upload_dir . $new_file_name)) {
                if (!empty($user['avatar']) && file_exists($_SERVER['DOCUMENT_ROOT'] . '/' . $user['avatar'])) @unlink($_SERVER['DOCUMENT_ROOT'] . '/' . $user['avatar']);
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
            $password_sql = ', password = ?';
            $params[] = password_hash($password, PASSWORD_BCRYPT);
        }
        $params[] = $user['id'];
        $pdo->prepare("UPDATE users SET firstname=?,lastname=?,pseudo=?,email=?,avatar=? {$password_sql} WHERE id=?")->execute($params);

        if (isset($_POST['save_card']) && $_POST['save_card'] == '1') {
            $card_number = preg_replace('/\s+/', '', trim($_POST['card_number'] ?? ''));
            $card_holder = trim($_POST['card_holder'] ?? '');
            $card_expiry = trim($_POST['card_expiry'] ?? '');
            
            if (!empty($card_number) && !empty($card_holder) && !empty($card_expiry)) {
                 $card_type = '';
                if (preg_match('/^4/', $card_number)) $card_type = 'visa';
                elseif (preg_match('/^5[1-5]/', $card_number)) $card_type = 'mastercard';
                elseif (preg_match('/^3[47]/', $card_number)) $card_type = 'amex';

                $encrypted_number = encrypt_data($card_number);
                
                $stmt_check = $pdo->prepare('SELECT id FROM user_cards WHERE user_id = ?');
                $stmt_check->execute([$_SESSION['user_id']]);
                
                if ($stmt_check->fetch()) {
                    $pdo->prepare('UPDATE user_cards SET card_number=?, card_holder=?, card_expiry=?, card_type=? WHERE user_id=?')
                        ->execute([$encrypted_number, $card_holder, $card_expiry, $card_type, $_SESSION['user_id']]);
                } else {
                    $pdo->prepare('INSERT INTO user_cards (user_id, card_number, card_holder, card_expiry, card_type) VALUES (?, ?, ?, ?, ?)')
                        ->execute([$_SESSION['user_id'], $encrypted_number, $card_holder, $card_expiry, $card_type]);
                }
            }
        }

        $user['firstname'] = $firstname; 
        $user['lastname'] = $lastname;
        $user['pseudo'] = $pseudo; 
        $user['email'] = $email; 
        $user['avatar'] = $new_avatar_path;
        $_SESSION['username'] = !empty($pseudo) ? $pseudo : $firstname;

        $message = t('profil.success');
        $message_type = 'success';
        
        $stmt_card_refresh = $pdo->prepare('SELECT * FROM user_cards WHERE user_id = ?');
        $stmt_card_refresh->execute([$_SESSION['user_id']]);
        $card_info = $stmt_card_refresh->fetch(PDO::FETCH_ASSOC);

    } catch (Exception $e) {
        $message = $e->getMessage();
        $message_type = 'error';
    }
}

$avatar_url = !empty($user['avatar']) ? '/' . $user['avatar'] : 'https://www.gravatar.com/avatar/' . md5(strtolower(trim($user['email']))) . '?d=mp&s=150';

$display_card_number = '';
if (!empty($card_info['card_number'])) {
    $decrypted_number = decrypt_data($card_info['card_number']);
    if ($decrypted_number) {
        $type_label = strtoupper($card_info['card_type'] ?: 'CARD');
        $last4 = substr($decrypted_number, -4);
        $display_card_number = "$type_label •••• $last4";
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?php echo t('profil.title'); ?> | Dashboard</title>
    <link rel="icon" type="image/png" href="https://heberge.orinstone.deepstone.fr/favicon.ico">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Scrollbar personnalisée pour un look plus propre */
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: #0f172a; }
        ::-webkit-scrollbar-thumb { background: #334155; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #475569; }
        
        body { 
            background-color: #0f172a; 
            background-image: radial-gradient(at 0% 0%, hsla(253,16%,7%,1) 0, transparent 50%), radial-gradient(at 50% 0%, hsla(225,39%,30%,1) 0, transparent 50%), radial-gradient(at 100% 0%, hsla(339,49%,30%,1) 0, transparent 50%); 
        }
        .glass-panel { background: rgba(30, 41, 59, 0.7); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.08); }
        .sidebar-link { transition: all 0.2s ease; border-left: 3px solid transparent; }
        .sidebar-link:hover, .sidebar-link.active { background: rgba(56, 189, 248, 0.1); border-left-color: #38bdf8; color: #38bdf8; }
        .input-field { background: rgba(15, 23, 42, 0.6); border: 1px solid rgba(255,255,255,0.1); transition: all 0.3s; }
        .input-field:focus { border-color: #38bdf8; box-shadow: 0 0 0 2px rgba(56, 189, 248, 0.2); outline: none; }
    </style>
</head>
<!-- Suppression de overflow-hidden pour permettre le scroll -->
<body class="min-h-screen text-gray-300 font-sans flex flex-col">

    <!-- WRAPPER PRINCIPAL POUR LE LAYOUT -->
    <div class="flex flex-1 w-full">
        
        <!-- SIDEBAR (Sticky pour suivre le scroll) -->
        <aside class="w-64 glass-panel hidden md:flex flex-col fixed h-screen top-0 left-0 z-40 border-r border-white/5 overflow-y-auto">
            <div class="p-6 flex items-center gap-3 border-b border-white/5 shrink-0">
                <a href="/" class="flex items-center gap-3 group">
                    <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-sky-500 to-indigo-600 flex items-center justify-center text-white font-bold shadow-lg group-hover:shadow-sky-500/50 transition">O</div>
                    <span class="font-bold text-lg tracking-wide text-white group-hover:text-sky-400 transition">OrinStone</span>
                </a>
            </div>

            <nav class="flex-1 py-6 space-y-1 px-3">
                <a href="/client/" class="sidebar-link active flex items-center px-3 py-3 text-sm font-medium rounded-r-lg">
                    <i class="fas fa-user-circle w-6"></i> <?php echo t('profil.heading'); ?>
                </a>
                <a href="/client/servers/" class="sidebar-link flex items-center px-3 py-3 text-sm font-medium hover:text-white rounded-r-lg">
                    <i class="fas fa-server w-6"></i> Mes Serveurs
                </a>
                <a href="/billing/" class="sidebar-link flex items-center px-3 py-3 text-sm font-medium hover:text-white rounded-r-lg">
                    <i class="fas fa-file-invoice-dollar w-6"></i> Facturation
                </a>
                <a href="/support/" class="sidebar-link flex items-center px-3 py-3 text-sm font-medium hover:text-white rounded-r-lg">
                    <i class="fas fa-ticket-alt w-6"></i> Support
                </a>
            </nav>

            <div class="p-4 border-t border-white/5 shrink-0">
                <a href="/logout/" class="flex items-center gap-3 px-4 py-2 text-sm text-red-400 hover:bg-red-500/10 rounded-lg transition">
                    <i class="fas fa-sign-out-alt"></i> Déconnexion
                </a>
            </div>
        </aside>

        <!-- CONTENU PRINCIPAL -->
        <div class="flex-1 md:ml-64 flex flex-col min-h-screen relative w-full">
            
            <!-- Mobile Header -->
            <header class="md:hidden glass-panel p-4 flex justify-between items-center sticky top-0 z-30 backdrop-blur-md bg-[#0f172a]/80">
                <span class="font-bold text-white">OrinStone</span>
                <button class="text-gray-400"><i class="fas fa-bars text-xl"></i></button>
            </header>

            <main class="flex-grow p-6 lg:p-10 w-full max-w-7xl mx-auto">
                
                <!-- Header Page -->
                <div class="mb-8 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <h1 class="text-3xl font-bold text-white mb-1"><?php echo t('profil.heading'); ?></h1>
                        <p class="text-gray-400 text-sm">Gérez vos informations personnelles et méthodes de paiement.</p>
                    </div>
                    <div class="flex items-center gap-3 glass-panel px-4 py-2 rounded-full cursor-default self-start sm:self-auto">
                        <img src="<?php echo htmlspecialchars($avatar_url, ENT_QUOTES, 'UTF-8'); ?>" class="w-8 h-8 rounded-full border border-white/20">
                        <span class="text-sm font-semibold text-white"><?php echo htmlspecialchars($user['pseudo'] ?: $user['firstname']); ?></span>
                    </div>
                </div>

                <?php if ($message): ?>
                <div class="mb-6 p-4 rounded-xl border <?php echo $message_type === 'success' ? 'bg-green-500/10 border-green-500/30 text-green-400' : 'bg-red-500/10 border-red-500/30 text-red-400'; ?>">
                    <i class="fas <?php echo $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?> mr-2"></i>
                    <?php echo $message; ?>
                </div>
                <?php endif; ?>

                <form method="post" enctype="multipart/form-data" class="space-y-8 pb-10">
                    
                    <!-- Section Identité -->
                    <div class="glass-panel rounded-2xl p-6 lg:p-8">
                        <h2 class="text-xl font-bold text-white mb-6 flex items-center gap-2">
                            <i class="fas fa-id-card text-sky-500"></i> Informations Personnelles
                        </h2>
                        
                        <div class="flex flex-col md:flex-row gap-8 items-start">
                            <!-- Avatar Upload -->
                            <div class="w-full md:w-auto flex flex-col items-center gap-3">
                                <div class="relative group cursor-pointer">
                                    <img src="<?php echo htmlspecialchars($avatar_url, ENT_QUOTES, 'UTF-8'); ?>" class="w-28 h-28 rounded-full object-cover border-4 border-white/5 shadow-xl">
                                    <div class="absolute inset-0 bg-black/60 rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition duration-300">
                                        <i class="fas fa-camera text-white text-xl"></i>
                                    </div>
                                    <input type="file" name="avatar" accept="image/*" class="absolute inset-0 opacity-0 cursor-pointer">
                                </div>
                                <span class="text-xs text-gray-500">Max 2MB</span>
                            </div>

                            <!-- Champs -->
                            <div class="flex-1 grid grid-cols-1 sm:grid-cols-2 gap-5 w-full">
                                <div class="col-span-2 sm:col-span-1">
                                    <label class="block text-xs font-bold uppercase text-gray-500 mb-2">Prénom</label>
                                    <input type="text" name="firstname" required value="<?php echo htmlspecialchars($user['firstname']); ?>" class="input-field w-full rounded-lg px-4 py-3 text-white">
                                </div>
                                <div class="col-span-2 sm:col-span-1">
                                    <label class="block text-xs font-bold uppercase text-gray-500 mb-2">Nom</label>
                                    <input type="text" name="lastname" required value="<?php echo htmlspecialchars($user['lastname']); ?>" class="input-field w-full rounded-lg px-4 py-3 text-white">
                                </div>
                                <div class="col-span-2">
                                    <label class="block text-xs font-bold uppercase text-gray-500 mb-2">Pseudo</label>
                                    <input type="text" name="pseudo" value="<?php echo htmlspecialchars($user['pseudo']); ?>" class="input-field w-full rounded-lg px-4 py-3 text-white">
                                </div>
                                <div class="col-span-2">
                                    <label class="block text-xs font-bold uppercase text-gray-500 mb-2">Email</label>
                                    <input type="email" name="email" required value="<?php echo htmlspecialchars($user['email']); ?>" class="input-field w-full rounded-lg px-4 py-3 text-white">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section Sécurité -->
                    <div class="glass-panel rounded-2xl p-6 lg:p-8">
                        <h2 class="text-xl font-bold text-white mb-6 flex items-center gap-2">
                            <i class="fas fa-shield-alt text-sky-500"></i> Sécurité
                        </h2>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <div>
                                <label class="block text-xs font-bold uppercase text-gray-500 mb-2">Nouveau mot de passe</label>
                                <input type="password" name="password" placeholder="••••••••" class="input-field w-full rounded-lg px-4 py-3 text-white">
                            </div>
                            <div>
                                <label class="block text-xs font-bold uppercase text-gray-500 mb-2">Confirmation</label>
                                <input type="password" name="password_confirm" placeholder="••••••••" class="input-field w-full rounded-lg px-4 py-3 text-white">
                            </div>
                        </div>
                    </div>

                    <!-- Section Paiement -->
                    <div class="glass-panel rounded-2xl p-6 lg:p-8 border-l-4 border-l-sky-500/50">
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-xl font-bold text-white flex items-center gap-2">
                                <i class="fas fa-credit-card text-sky-500"></i> Moyens de Paiement
                            </h2>
                            <?php if ($display_card_number): ?>
                                <span class="bg-green-500/20 text-green-400 text-xs px-3 py-1 rounded-full border border-green-500/30">
                                    <i class="fas fa-check mr-1"></i> <?php echo $display_card_number; ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <div class="bg-[#0f172a]/50 rounded-xl p-5 border border-white/5 space-y-4">
                            <div>
                                <label class="block text-xs font-bold uppercase text-gray-500 mb-2">Numéro de carte</label>
                                <div class="relative">
                                    <i class="fab fa-cc-visa absolute left-3 top-3.5 text-gray-500 text-lg"></i>
                                    <input type="text" name="card_number" placeholder="0000 0000 0000 0000" maxlength="19" class="input-field w-full rounded-lg pl-10 pr-4 py-3 text-white font-mono tracking-wider">
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-bold uppercase text-gray-500 mb-2">Titulaire</label>
                                    <input type="text" name="card_holder" placeholder="M JOHN DOE" class="input-field w-full rounded-lg px-4 py-3 text-white uppercase">
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label class="block text-xs font-bold uppercase text-gray-500 mb-2">Exp.</label>
                                        <input type="text" name="card_expiry" placeholder="MM/AA" maxlength="5" class="input-field w-full rounded-lg px-4 py-3 text-white text-center">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-bold uppercase text-gray-500 mb-2">CVV</label>
                                        <input type="password" name="card_cvv" placeholder="123" maxlength="3" class="input-field w-full rounded-lg px-4 py-3 text-white text-center">
                                    </div>
                                </div>
                            </div>

                            <div class="pt-2">
                                <label class="flex items-center gap-3 cursor-pointer group">
                                    <input type="checkbox" name="save_card" value="1" class="w-5 h-5 rounded border-gray-600 bg-gray-700 text-sky-500 focus:ring-sky-500/50">
                                    <span class="text-sm text-gray-400 group-hover:text-white transition">Enregistrer cette carte pour les prochains paiements</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end pt-4">
                        <button type="submit" class="bg-sky-600 hover:bg-sky-500 text-white px-8 py-3 rounded-xl font-bold shadow-lg shadow-sky-600/20 transition transform hover:-translate-y-1 active:scale-95">
                            <?php echo t('profil.save'); ?>
                        </button>
                    </div>

                </form>
            </main>
            
            <!-- Footer inclus ici pour qu'il soit poussé vers le bas par flex-grow -->
            <?php require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/footer.php'; ?>
        </div>
    </div>

</body>
</html>
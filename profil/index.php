<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/lang.php';

if (!isset($_SESSION['user_id'])) { header('Location: /login/'); exit(); }

$is_logged_in = true;
$message = '';
$message_type = 'info';

try {
    $pdo = new PDO('mysql:host=localhost;dbname=s43_orinheberge;charset=utf8mb4', 'root', '1504', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $stmt = $pdo->prepare('SELECT id, firstname, lastname, pseudo, email, avatar FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) { session_destroy(); header('Location: /login/'); exit(); }
} catch (Exception $e) {
    $message = t('profil.db_error');
    $message_type = 'error';
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

        $user['firstname'] = $firstname; $user['lastname'] = $lastname;
        $user['pseudo'] = $pseudo; $user['email'] = $email; $user['avatar'] = $new_avatar_path;
        $_SESSION['username'] = !empty($pseudo) ? $pseudo : $firstname;

        $message = t('profil.success');
        $message_type = 'success';
    } catch (Exception $e) {
        $message = $e->getMessage();
        $message_type = 'error';
    }
}

$avatar_url = !empty($user['avatar']) ? '/' . $user['avatar'] : 'https://www.gravatar.com/avatar/' . md5(strtolower(trim($user['email']))) . '?d=mp&s=150';
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?php echo t('profil.title'); ?></title>
    <link rel="icon" type="image/png" href="https://heberge.orinstone.deepstone.fr/favicon.ico">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="manifest" href="/manifest.json">
    <style>
        body { background: radial-gradient(circle at top left, #1e293b, #020617); }
        .glass { background: rgba(255,255,255,0.03); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.1); }
        .gradient-text { background: linear-gradient(90deg, #38bdf8, #818cf8); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
    </style>
</head>
<body class="min-h-screen text-gray-200 flex flex-col justify-between font-sans">

<main class="flex-grow flex items-center justify-center p-6 my-8">
    <div class="w-full max-w-xl glass p-8 rounded-3xl shadow-2xl">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-black gradient-text uppercase tracking-tighter mb-2"><?php echo t('profil.heading'); ?></h1>
            <p class="text-gray-400 text-sm">Gérer vos informations personnelles</p>
        </div>

        <?php if ($message): ?>
        <?php
            $ac = 'bg-sky-500/10 border-sky-500/50 text-sky-400';
            if ($message_type === 'success') $ac = 'bg-green-500/10 border-green-500/50 text-green-400';
            if ($message_type === 'error')   $ac = 'bg-red-500/10 border-red-500/50 text-red-400';
        ?>
        <div class="mb-6 p-4 rounded-xl text-center text-sm border <?php echo $ac; ?>"><?php echo $message; ?></div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data" class="space-y-6">
            <div class="flex flex-col sm:flex-row items-center gap-6 p-4 rounded-2xl bg-white/[0.02] border border-white/5">
                <div class="relative group">
                    <img src="<?php echo htmlspecialchars($avatar_url, ENT_QUOTES, 'UTF-8'); ?>" alt="Avatar" class="w-24 h-24 rounded-full object-cover border-2 border-sky-500/30 group-hover:border-sky-500 transition duration-300">
                    <div class="absolute inset-0 bg-black/50 rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition duration-300 pointer-events-none"><i class="fas fa-camera text-white text-lg"></i></div>
                </div>
                <div class="flex-1 text-center sm:text-left">
                    <label class="block text-xs font-bold uppercase tracking-widest text-gray-400 mb-2">Photo de profil</label>
                    <input type="file" name="avatar" accept="image/png,image/jpeg,image/jpg,image/webp" class="text-xs text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-bold file:bg-sky-600/10 file:text-sky-400 hover:file:bg-sky-600/20 file:cursor-pointer">
                    <p class="text-[10px] text-gray-500 mt-1">PNG, JPG, WEBP. Max : 2 Mo.</p>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-widest text-gray-500 mb-2"><?php echo t('profil.pseudo'); ?></label>
                <input type="text" name="pseudo" value="<?php echo htmlspecialchars($user['pseudo'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" placeholder="Ex: Mathéo_Web" class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 focus:outline-none focus:border-sky-500 transition text-white text-sm">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-widest text-gray-500 mb-2"><?php echo t('profil.firstname'); ?></label>
                    <input type="text" name="firstname" required value="<?php echo htmlspecialchars($user['firstname'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 focus:outline-none focus:border-sky-500 transition text-white text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold uppercase tracking-widest text-gray-500 mb-2"><?php echo t('profil.lastname'); ?></label>
                    <input type="text" name="lastname" required value="<?php echo htmlspecialchars($user['lastname'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 focus:outline-none focus:border-sky-500 transition text-white text-sm">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-widest text-gray-500 mb-2">Adresse email</label>
                <input type="email" name="email" required value="<?php echo htmlspecialchars($user['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 focus:outline-none focus:border-sky-500 transition text-white text-sm">
            </div>

            <hr class="border-white/5 my-2">

            <div class="bg-white/[0.01] p-4 rounded-2xl border border-white/5 space-y-4">
                <h3 class="text-sm font-bold tracking-wide text-gray-400"><i class="fas fa-lock mr-2 text-sky-500/70"></i><?php echo t('profil.change_pw'); ?></h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-widest text-gray-500 mb-2">Nouveau mot de passe</label>
                        <input type="password" name="password" placeholder="••••••••" class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 focus:outline-none focus:border-sky-500 transition text-white text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-bold uppercase tracking-widest text-gray-500 mb-2">Confirmation</label>
                        <input type="password" name="password_confirm" placeholder="••••••••" class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 focus:outline-none focus:border-sky-500 transition text-white text-sm">
                    </div>
                </div>
            </div>

            <button class="w-full bg-sky-600 hover:bg-sky-500 py-4 rounded-xl font-black uppercase tracking-widest transition shadow-lg shadow-sky-600/20 active:scale-95 text-slate-950">
                <?php echo t('profil.save'); ?>
            </button>
        </form>

        <div class="mt-6 pt-4 border-t border-white/5 text-center">
            <button onclick="history.back();" class="text-gray-400 hover:text-sky-400 text-sm font-semibold transition">
                <i class="fas fa-arrow-left mr-2"></i><?php echo t('ui.back_home'); ?>
            </button>
        </div>
    </div>
</main>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/inc/footer.php'; ?>
   <div class="fixed bottom-6 right-6 z-50">
    <a href="https://heberge.orinstone.deepstone.fr/discord/" target="_blank"
       class="bg-[#5865F2] hover:bg-[#4752C4] transition text-white px-5 py-4 rounded-full font-bold flex items-center gap-2 shadow-2xl hover:scale-105 transform duration-200">
        <i class="fab fa-discord text-xl"></i>
        <span class="hidden sm:inline text-sm"><?php echo t('discord.help'); ?></span>
    </a>
</div>
</body>
</html>

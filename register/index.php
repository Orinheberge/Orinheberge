<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/lang.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/smtp.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/db.php';

$is_logged_in = isset($_SESSION['user_id']);
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstname = htmlspecialchars(trim($_POST['firstname'] ?? ''));
    $lastname  = htmlspecialchars(trim($_POST['lastname']  ?? ''));
    $email     = trim($_POST['email']    ?? '');
    $password  = $_POST['password'] ?? '';

    if (!$firstname || !$lastname || !$email || !$password) {
        $message = "<div class='bg-red-500/10 border border-red-500/50 text-red-400 p-4 rounded-xl mb-6 text-center text-sm'>Tous les champs sont obligatoires.</div>";
    } else {
        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            // Générer un pseudo unique depuis le prénom
            $base_pseudo = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $firstname)) ?: 'user';
            $pseudo = $base_pseudo;
            $i = 1;
            while ($pdo->query("SELECT id FROM users WHERE pseudo=" . $pdo->quote($pseudo))->fetch()) {
                $pseudo = $base_pseudo . $i++;
            }

            $stmt = $pdo->prepare("INSERT INTO users (firstname, lastname, email, password, pseudo) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$firstname, $lastname, $email, $hash, $pseudo]);
            $new_user_id = (int)$pdo->lastInsertId();

            // ── Créer le compte Pterodactyl avec le même mot de passe ──
            $panel_created = false;
            $panel_error   = null;
            if ($panel_url && $api_key_admin) {
                try {
                    // Vérifier si le compte existe déjà sur le panel
                    $search = pterodactylApi($panel_url, $headers_admin, 'users?filter[email]=' . urlencode($email));
                    $panel_uid = $search['data'][0]['attributes']['id'] ?? null;

                    if (!$panel_uid) {
                        $created = pterodactylApi($panel_url, $headers_admin, 'users', [
                            'email'      => $email,
                            'username'   => $pseudo,
                            'first_name' => $firstname,
                            'last_name'  => $lastname,
                            'password'   => $password,
                        ]);
                        $panel_uid = $created['attributes']['id'] ?? null;
                    }

                    if ($panel_uid) {
                        // Stocker le mot de passe en clair pour affichage/email (non haché)
                        $pdo->prepare('UPDATE users SET panel_password=? WHERE id=?')->execute([$password, $new_user_id]);
                        $panel_created = true;
                    } else {
                        $panel_error = 'Compte panel non créé : ' . json_encode($created ?? []);
                        error_log($panel_error);
                    }
                } catch (Throwable $e) {
                    error_log('Panel user creation at register: ' . $e->getMessage());
                }
            }

            // ── Email de bienvenue avec identifiants ──
            $panel_section = $panel_created
                ? '<div class="box">
                    <div class="row"><span class="label">Panel URL</span><span class="val mono">' . htmlspecialchars(rtrim($panel_url, '/')) . '</span></div>
                    <div class="row"><span class="label">Identifiant</span><span class="val mono">' . htmlspecialchars($email) . '</span></div>
                    <div class="row"><span class="label">Mot de passe</span><span class="val mono">' . htmlspecialchars($password) . '</span></div>
                  </div>
                  <p style="color:#f59e0b;font-size:13px;">⚠️ Notez vos identifiants panel — ils ne seront plus affichés.</p>'
                : '<p style="font-size:13px;color:#6b7280;">Le compte panel sera créé automatiquement lors de votre première commande.</p>';

            $body = '
                <p>Bonjour <strong>' . htmlspecialchars($firstname . ' ' . $lastname) . '</strong>,</p>
                <p>Votre compte OrinHeberge a été créé avec succès. Voici vos informations de connexion :</p>
                <div class="box">
                    <div class="row"><span class="label">Prénom</span><span class="val">' . htmlspecialchars($firstname) . '</span></div>
                    <div class="row"><span class="label">Nom</span><span class="val">' . htmlspecialchars($lastname) . '</span></div>
                    <div class="row"><span class="label">Email</span><span class="val mono">' . htmlspecialchars($email) . '</span></div>
                    <div class="row"><span class="label">Mot de passe</span><span class="val mono">' . htmlspecialchars($password) . '</span></div>
                </div>
                ' . ($panel_created ? '<p><strong>Identifiants Pterodactyl (panel de gestion des serveurs) :</strong></p>' . $panel_section : '') . '
                <p><a href="https://heberge.orinstone.deepstone.fr/login/" class="btn">Se connecter →</a></p>
                <p style="font-size:12px;color:#4b5563;">Pour votre sécurité, nous vous recommandons de changer votre mot de passe après votre première connexion.</p>';

            send_smtp_mail($email, '🎉 Bienvenue sur OrinHeberge — vos identifiants', email_layout('Bienvenue !', $body));

            $message = "<div class='bg-green-500/10 border border-green-500/50 text-green-400 p-4 rounded-xl mb-6 text-center text-sm'>" . th('register.success') . "</div>";
        } catch (Exception $e) {
            $message = "<div class='bg-red-500/10 border border-red-500/50 text-red-400 p-4 rounded-xl mb-6 text-center text-sm'>" . t('register.error_dup') . "</div>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('register.title'); ?></title>
    <link rel="icon" type="image/png" href="https://heberge.orinstone.deepstone.fr/favicon.ico">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="manifest" href="/manifest.json">
    
        <meta name="keywords" content="inscription OrinHeberge, créer compte, signup, register, hébergement gratuit, inscription VPS, ouverture compte client">
    <meta name="author" content="OrinHeberge">
    <meta name="robots" content="noindex, nofollow">
    <link rel="canonical" href="https://heberge.orinstone.deepstone.fr/register/">

    <!-- Open Graph / Facebook -->
    <meta property="og:locale" content="fr_FR">
    <meta property="og:type" content="website">
    <meta property="og:title" content="Inscription - OrinHeberge | Rejoignez-nous">
    <meta property="og:description" content="Rejoignez OrinHeberge ! Créez votre compte gratuitement en quelques secondes pour accéder à nos hébergements VPS, Minecraft, PHP et Node.js performants.">
    <meta property="og:url" content="https://heberge.orinstone.deepstone.fr/register/">
    <meta property="og:site_name" content="OrinHeberge">
    <meta property="og:image" content="https://heberge.orinstone.deepstone.fr/favicon.png">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="OrinHeberge - Inscription gratuite">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:site" content="@OrinHeberge">
    <meta name="twitter:creator" content="@OrinHeberge">
    <meta name="twitter:title" content="Inscription - OrinHeberge | Rejoignez-nous">
    <meta name="twitter:description" content="Rejoignez OrinHeberge ! Créez votre compte gratuitement en quelques secondes pour accéder à nos hébergements VPS, Minecraft, PHP et Node.js performants.">
    <meta name="twitter:image" content="https://heberge.orinstone.deepstone.fr/favicon.png">
    <meta name="twitter:image:alt" content="OrinHeberge - Inscription gratuite">

    <!-- Autres balises SEO -->
    <meta name="theme-color" content="#6366f1">
    <meta name="msapplication-TileColor" content="#6366f1">
    <link rel="apple-touch-icon" href="https://heberge.orinstone.deepstone.fr/favicon.ico">

    <!-- Schema.org JSON-LD (SEO avancé) -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "RegisterAction",
      "name": "Inscription OrinHeberge",
      "url": "https://heberge.orinstone.deepstone.fr/register/",
      "description": "Page d'inscription pour créer un compte client chez OrinHeberge.",
      "agent": {
        "@type": "Person",
        "name": "Nouveau Client"
      },
      "result": {
        "@type": "Organization",
        "name": "OrinHeberge",
        "url": "https://heberge.orinstone.deepstone.fr/"
      }
    }
    </script>
    
    <style>
        body { background: radial-gradient(circle at top left, #1e293b, #020617); }
        .glass { background: rgba(255,255,255,0.03); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.1); }
        .gradient-text { background: linear-gradient(90deg, #38bdf8, #818cf8); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
    </style>
</head>
<body class="text-gray-200 flex flex-col justify-between min-h-screen font-sans">

    <main class="flex-grow flex items-center justify-center p-6 my-8">
        <div class="glass w-full max-w-lg p-10 rounded-3xl shadow-2xl">
            <div class="text-center mb-8">
                <h1 class="text-3xl font-black gradient-text uppercase tracking-tighter mb-2"><?php echo t('register.title'); ?></h1>
                <p class="text-gray-400 text-sm italic"><?php echo t('register.subtitle'); ?></p>
            </div>

            <?php echo $message; ?>

            <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div class="md:col-span-1">
                    <label class="block text-xs font-bold uppercase tracking-widest text-gray-500 mb-2"><?php echo t('register.firstname'); ?></label>
                    <input type="text" name="firstname" required class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 focus:outline-none focus:border-sky-500 transition text-white" placeholder="Jean">
                </div>
                <div class="md:col-span-1">
                    <label class="block text-xs font-bold uppercase tracking-widest text-gray-500 mb-2"><?php echo t('register.lastname'); ?></label>
                    <input type="text" name="lastname" required class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 focus:outline-none focus:border-sky-500 transition text-white" placeholder="Dupont">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-bold uppercase tracking-widest text-gray-500 mb-2"><?php echo t('register.email'); ?></label>
                    <input type="email" name="email" required class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 focus:outline-none focus:border-sky-500 transition text-white" placeholder="jean@exemple.fr">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-bold uppercase tracking-widest text-gray-500 mb-2"><?php echo t('register.password'); ?></label>
                    <input type="password" name="password" required class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 focus:outline-none focus:border-sky-500 transition text-white" placeholder="••••••••">
                </div>
                <button type="submit" class="md:col-span-2 bg-sky-600 hover:bg-sky-500 py-4 rounded-xl font-black uppercase tracking-widest transition shadow-lg shadow-sky-600/20 active:scale-95">
                    <?php echo t('register.submit'); ?>
                </button>
            </form>

            <div class="mt-8 pt-6 border-t border-white/5 text-center">
                <p class="text-gray-500 text-sm"><?php echo t('register.have_account'); ?> <a href="/login/" class="text-sky-400 font-bold hover:text-sky-300 transition"><?php echo t('register.login_link'); ?></a></p>
            </div>

            <a href="/" class="block text-center mt-6 text-xs text-gray-600 hover:text-gray-400 transition">
                <i class="fas fa-arrow-left mr-2"></i> <?php echo t('register.back'); ?>
            </a>
        </div>
    </main>

    <div class="fixed bottom-6 right-6 z-50">
        <a href="https://heberge.orinstone.deepstone.fr/discord/" target="_blank" class="bg-[#5865F2] hover:bg-[#4752C4] transition text-white px-5 py-3.5 rounded-full font-bold flex items-center gap-2 shadow-2xl hover:scale-105 transform duration-200">
            <i class="fab fa-discord text-xl"></i>
            <span class="hidden sm:inline text-sm"><?php echo t('discord.help'); ?></span>
        </a>
    </div>

    <?php include $_SERVER['DOCUMENT_ROOT'] . '/inc/footer.php'; ?>
</body>
</html>

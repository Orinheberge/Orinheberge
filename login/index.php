<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/auth.php';

if ($is_logged_in) {
    header('Location: /client/');
    exit;
}

$error = $_GET['error'] ?? null;
$errorMessages = [
    'oauth_cancelled' => 'Connexion OAuth annulée',
    'oauth_failed'    => 'Échec de la connexion OAuth',
    'oauth_error'     => 'Erreur technique OAuth',
    'oauth_missing'   => 'Données OAuth manquantes',
];
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <title><?php echo t('login.title'); ?> - OrinHeberge</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: radial-gradient(circle at top left, #1e293b, #020617); }
        .glass { background: rgba(255,255,255,0.03); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.1); }
        .gradient-text { background: linear-gradient(90deg, #38bdf8, #818cf8); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
    </style>
</head>
<body class="text-gray-200 min-h-screen flex items-center justify-center p-6">
    <div class="glass w-full max-w-md p-8 rounded-3xl shadow-2xl">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-black gradient-text uppercase tracking-tighter mb-2">OrinHeberge</h1>
            <p class="text-gray-400 text-sm"><?php echo t('login.subtitle'); ?></p>
        </div>

        <?php if ($error && isset($errorMessages[$error])): ?>
            <div class="bg-red-500/10 border border-red-500/50 text-red-400 p-3 rounded-xl mb-4 text-center text-sm">
                <?php echo $errorMessages[$error]; ?>
            </div>
        <?php endif; ?>

        <div id="errorBox" class="hidden bg-red-500/10 border border-red-500/50 text-red-400 p-3 rounded-xl mb-4 text-center text-sm"></div>

        <!-- Boutons OAuth -->
        <div class="space-y-2 mb-6">
            <a href="/api/auth/discord.php" class="flex items-center justify-center gap-2 bg-[#5865F2] hover:bg-[#4752C4] text-white py-3 rounded-xl font-bold transition">
                <i class="fab fa-discord"></i> Continuer avec Discord
            </a>
            <a href="/api/auth/google.php" class="flex items-center justify-center gap-2 bg-white hover:bg-gray-100 text-gray-800 py-3 rounded-xl font-bold transition">
                <i class="fab fa-google"></i> Continuer avec Google
            </a>
        </div>

        <div class="flex items-center gap-3 my-4">
            <div class="flex-1 h-px bg-white/10"></div>
            <span class="text-xs text-gray-500 uppercase">ou</span>
            <div class="flex-1 h-px bg-white/10"></div>
        </div>

        <!-- Formulaire classique -->
        <form id="loginForm" class="space-y-5">
            <div>
                <label class="block text-xs font-bold uppercase tracking-widest text-gray-500 mb-2"><?php echo t('login.email'); ?></label>
                <input type="email" name="email" required class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 focus:outline-none focus:border-sky-500 text-white">
            </div>
            <div>
                <label class="block text-xs font-bold uppercase tracking-widest text-gray-500 mb-2"><?php echo t('login.password'); ?></label>
                <input type="password" name="password" required class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 focus:outline-none focus:border-sky-500 text-white">
            </div>
            <button type="submit" class="w-full bg-sky-600 hover:bg-sky-500 py-4 rounded-xl font-black uppercase tracking-widest transition">
                <?php echo t('login.submit'); ?>
            </button>
        </form>

        <div class="mt-6 text-center text-sm">
            <a href="/forgotpassword/" class="text-sky-400 hover:underline"><?php echo t('login.forgot'); ?></a>
        </div>
        <div class="mt-4 text-center text-sm text-gray-500">
            <?php echo t('login.no_account'); ?> 
            <a href="/register/" class="text-sky-400 font-bold"><?php echo t('login.create'); ?></a>
        </div>
    </div>

    <script>
    document.getElementById('loginForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const form = e.target;
        const errorBox = document.getElementById('errorBox');
        errorBox.classList.add('hidden');

        const response = await fetch('/api/auth/login.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                email: form.email.value,
                password: form.password.value
            })
        });

        const data = await response.json();

        if (data.success) {
            window.location.href = '/client/';
        } else {
            errorBox.textContent = data.error;
            errorBox.classList.remove('hidden');
        }
    });
    </script>
</body>
</html>
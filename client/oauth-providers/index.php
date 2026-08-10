<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/AuthService.php';

if (!$is_logged_in) {
    header('Location: /login/');
    exit;
}

$auth = new AuthService($pdo);
$providers = $auth->getUserOAuthProviders($_SESSION['user_id']);

// Vérifier si mot de passe local existe
$stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$hasPassword = !empty($stmt->fetch()['password']);

$active_nav = 'profile';
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <title>Comptes connectés - OrinHeberge</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: radial-gradient(circle at top left, #1e293b, #020617); }
        .glass { background: rgba(255,255,255,0.03); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.1); }
    </style>
</head>
<body class="text-gray-200 min-h-screen">
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/inc/navbar.php'; ?>

    <main class="max-w-2xl mx-auto px-4 py-8">
        <div class="glass rounded-3xl p-8 shadow-2xl">
            <h1 class="text-2xl font-black text-white mb-2">
                <i class="fas fa-link mr-2 text-sky-400"></i>Comptes connectés
            </h1>
            <p class="text-gray-400 text-sm mb-6">
                Gérez les comptes externes liés à votre profil OrinHeberge.
            </p>

            <!-- Message succès/erreur -->
            <div id="messageBox" class="hidden mb-4 p-3 rounded-xl text-sm"></div>

            <!-- Liste des providers -->
            <div class="space-y-3 mb-6">
                <!-- Discord -->
                <?php 
                $discordLinked = false;
                foreach ($providers as $p) {
                    if ($p['provider'] === 'discord') $discordLinked = true;
                }
                ?>
                <div class="flex items-center justify-between p-4 rounded-xl border <?= $discordLinked ? 'bg-[#5865F2]/10 border-[#5865F2]/30' : 'bg-white/5 border-white/10' ?>">
                    <div class="flex items-center gap-3">
                        <i class="fab fa-discord text-2xl <?= $discordLinked ? 'text-[#5865F2]' : 'text-gray-500' ?>"></i>
                        <div>
                            <div class="font-bold">Discord</div>
                            <div class="text-xs text-gray-500">
                                <?= $discordLinked ? '✅ Connecté' : '❌ Non connecté' ?>
                            </div>
                        </div>
                    </div>
                    <?php if ($discordLinked): ?>
                        <button onclick="unlinkProvider('discord')" class="text-xs bg-red-500/20 hover:bg-red-500/30 text-red-400 px-3 py-1.5 rounded-lg transition">
                            <i class="fas fa-unlink mr-1"></i>Dissocier
                        </button>
                    <?php else: ?>
                        <a href="/api/auth/discord.php" class="text-xs bg-[#5865F2] hover:bg-[#4752C4] text-white px-3 py-1.5 rounded-lg transition">
                            <i class="fas fa-link mr-1"></i>Connecter
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Google -->
                <?php 
                $googleLinked = false;
                foreach ($providers as $p) {
                    if ($p['provider'] === 'google') $googleLinked = true;
                }
                ?>
                <div class="flex items-center justify-between p-4 rounded-xl border <?= $googleLinked ? 'bg-white/10 border-white/30' : 'bg-white/5 border-white/10' ?>">
                    <div class="flex items-center gap-3">
                        <i class="fab fa-google text-2xl <?= $googleLinked ? 'text-white' : 'text-gray-500' ?>"></i>
                        <div>
                            <div class="font-bold">Google</div>
                            <div class="text-xs text-gray-500">
                                <?= $googleLinked ? '✅ Connecté' : '❌ Non connecté' ?>
                            </div>
                        </div>
                    </div>
                    <?php if ($googleLinked): ?>
                        <button onclick="unlinkProvider('google')" class="text-xs bg-red-500/20 hover:bg-red-500/30 text-red-400 px-3 py-1.5 rounded-lg transition">
                            <i class="fas fa-unlink mr-1"></i>Dissocier
                        </button>
                    <?php else: ?>
                        <a href="/api/auth/google.php" class="text-xs bg-white hover:bg-gray-100 text-gray-800 px-3 py-1.5 rounded-lg transition">
                            <i class="fas fa-link mr-1"></i>Connecter
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Info sécurité -->
            <div class="bg-sky-500/10 border border-sky-500/30 rounded-xl p-4 text-sm text-sky-300">
                <i class="fas fa-info-circle mr-2"></i>
                <strong>Sécurité :</strong> Vous devez garder au moins un moyen de connexion actif 
                (mot de passe local ou un provider OAuth).
                <?php if (!$hasPassword && count($providers) <= 1): ?>
                    <br><span class="text-amber-400">⚠️ Attention : vous n'avez qu'un seul moyen de connexion !</span>
                <?php endif; ?>
            </div>

            <a href="/profil/" class="block text-center mt-6 text-sm text-gray-500 hover:text-gray-300 transition">
                <i class="fas fa-arrow-left mr-2"></i>Retour au profil
            </a>
        </div>
    </main>

    <script>
    async function unlinkProvider(provider) {
        if (!confirm(`Dissocier ${provider} de votre compte ?`)) return;
        
        const response = await fetch('/api/auth/unlink-provider.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ provider })
        });
        
        const data = await response.json();
        const msgBox = document.getElementById('messageBox');
        
        if (data.success) {
            msgBox.className = 'mb-4 p-3 rounded-xl text-sm bg-green-500/10 border border-green-500/50 text-green-400';
            msgBox.textContent = '✅ Compte dissocié avec succès';
            setTimeout(() => location.reload(), 1500);
        } else {
            msgBox.className = 'mb-4 p-3 rounded-xl text-sm bg-red-500/10 border border-red-500/50 text-red-400';
            msgBox.textContent = data.error === 'cannot_unlink_last_provider' 
                ? '❌ Impossible : vous devez garder au moins un moyen de connexion'
                : '❌ Erreur : ' + (data.error || 'Inconnue');
        }
        msgBox.classList.remove('hidden');
    }
    </script>
</body>
</html>
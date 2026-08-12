<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/auth.php';

if ($is_logged_in) {
    header('Location: /client/');
    exit;
}
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?php echo t('forgot.title'); ?> - OrinHeberge</title>
    <link rel="icon" type="image/png" href="https://heberge.orinstone.deepstone.fr/favicon.ico">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: radial-gradient(circle at top left, #1e293b, #020617); }
        .glass { background: rgba(255,255,255,0.03); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.1); }
        .gradient-text { background: linear-gradient(90deg,#38bdf8,#818cf8); -webkit-background-clip:text; -webkit-text-fill-color:transparent; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .spinner { animation: spin 1s linear infinite; }
    </style>
</head>
<body class="min-h-screen text-gray-200 flex flex-col justify-between font-sans">

<?php $active_nav = ''; include $_SERVER['DOCUMENT_ROOT'] . '/inc/navbar.php'; ?>

<main class="flex-grow flex items-center justify-center p-6 my-8">
    <div class="glass w-full max-w-md p-8 rounded-3xl shadow-2xl">
        <div class="text-center mb-8">
            <div class="w-16 h-16 bg-sky-500/10 border border-sky-500/30 rounded-2xl flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-key text-2xl text-sky-400"></i>
            </div>
            <h1 class="text-3xl font-black gradient-text uppercase tracking-tighter mb-2"><?php echo t('forgot.heading'); ?></h1>
            <p class="text-gray-400 text-sm"><?php echo t('forgot.subtitle'); ?></p>
        </div>

        <!-- Zone messages -->
        <div id="messageBox" class="hidden mb-6 p-4 rounded-xl text-center text-sm border"></div>

        <!-- Success state -->
        <div id="successState" class="hidden text-center">
            <div class="w-20 h-20 bg-green-500/10 border border-green-500/30 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-envelope-open-text text-3xl text-green-400"></i>
            </div>
            <h2 class="text-xl font-bold text-white mb-2">Email envoyé !</h2>
            <p class="text-gray-400 text-sm mb-6">
                Si cet email existe dans notre base, vous recevrez un lien de réinitialisation dans quelques instants.
            </p>
            <p class="text-xs text-gray-500 mb-6">
                <i class="fas fa-info-circle mr-1"></i>
                Pensez à vérifier votre dossier spam.
            </p>
            <a href="/login/" class="inline-block bg-sky-600 hover:bg-sky-500 px-6 py-3 rounded-xl font-bold transition">
                Retour à la connexion
            </a>
        </div>

        <!-- Formulaire -->
        <form id="forgotForm" class="space-y-5">
            <div>
                <label class="block text-xs font-bold uppercase tracking-widest text-gray-500 mb-2">
                    <?php echo t('forgot.email'); ?>
                </label>
                <div class="relative">
                    <i class="fas fa-envelope absolute left-4 top-1/2 -translate-y-1/2 text-gray-500"></i>
                    <input type="email" name="email" required placeholder="votre@email.com"
                           class="w-full bg-white/5 border border-white/10 rounded-xl pl-11 pr-4 py-3 focus:outline-none focus:border-sky-500 transition text-white">
                </div>
            </div>
            <button type="submit" id="submitBtn" class="w-full bg-sky-600 hover:bg-sky-500 py-4 rounded-xl font-black uppercase tracking-widest transition shadow-lg active:scale-95 flex items-center justify-center gap-2">
                <span id="submitText"><?php echo t('forgot.submit'); ?></span>
                <i id="submitSpinner" class="fas fa-spinner spinner hidden"></i>
            </button>
        </form>

        <!-- Séparateur -->
        <div class="flex items-center gap-3 my-6">
            <div class="flex-1 h-px bg-white/10"></div>
            <span class="text-xs text-gray-500 uppercase tracking-widest">ou</span>
            <div class="flex-1 h-px bg-white/10"></div>
        </div>

        <!-- OAuth hint -->
        <div class="bg-sky-500/5 border border-sky-500/20 rounded-xl p-4 mb-4">
            <p class="text-xs text-sky-300 text-center mb-3">
                <i class="fas fa-info-circle mr-1"></i>
                Vous vous connectez avec Discord ou Google ?
            </p>
            <div class="grid grid-cols-2 gap-2">
                <a href="/api/auth/discord.php" class="flex items-center justify-center gap-2 bg-[#5865F2] hover:bg-[#4752C4] text-white py-2 rounded-lg text-xs font-bold transition">
                    <i class="fab fa-discord"></i> Discord
                </a>
                <a href="/api/auth/google.php" class="flex items-center justify-center gap-2 bg-white hover:bg-gray-100 text-gray-800 py-2 rounded-lg text-xs font-bold transition">
                    <i class="fab fa-google"></i> Google
                </a>
            </div>
        </div>

        <a href="/login/" class="block text-center mt-4 text-xs text-gray-600 hover:text-gray-400 transition">
            <i class="fas fa-arrow-left mr-2"></i><?php echo t('forgot.back'); ?>
        </a>
    </div>
</main>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/inc/footer.php'; ?>

<script>
const form = document.getElementById('forgotForm');
const messageBox = document.getElementById('messageBox');
const successState = document.getElementById('successState');
const submitBtn = document.getElementById('submitBtn');
const submitText = document.getElementById('submitText');
const submitSpinner = document.getElementById('submitSpinner');

function showMessage(text, type = 'error') {
    messageBox.innerHTML = text;
    messageBox.className = 'mb-6 p-4 rounded-xl text-center text-sm border ' + (
        type === 'success' ? 'bg-green-500/10 border-green-500/50 text-green-400' :
        type === 'info'    ? 'bg-sky-500/10 border-sky-500/50 text-sky-400' :
                             'bg-red-500/10 border-red-500/50 text-red-400'
    );
    messageBox.classList.remove('hidden');
}

function setLoading(loading) {
    submitBtn.disabled = loading;
    submitText.textContent = loading ? 'Envoi...' : '<?php echo addslashes(t('forgot.submit')); ?>';
    submitSpinner.classList.toggle('hidden', !loading);
    submitBtn.classList.toggle('opacity-50', loading);
    submitBtn.classList.toggle('cursor-not-allowed', loading);
}

form.addEventListener('submit', async (e) => {
    e.preventDefault();
    messageBox.classList.add('hidden');
    
    const email = form.email.value.trim();
    if (!email) {
        showMessage('Veuillez saisir votre email.');
        return;
    }

    setLoading(true);

    try {
        const response = await fetch('/api/auth/forgot-password.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email })
        });

        const data = await response.json();

        if (data.success) {
            form.classList.add('hidden');
            successState.classList.remove('hidden');
        } else if (data.error === 'oauth_account') {
            showMessage(`
                <i class="fas fa-exclamation-triangle mr-2"></i>
                Ce compte utilise la connexion <strong>${data.provider}</strong>.
                <br><small>Cliquez sur le bouton ${data.provider} ci-dessous pour vous connecter.</small>
            `, 'info');
            setLoading(false);
        } else if (data.error === 'too_many_attempts') {
            showMessage('Trop de demandes. Veuillez réessayer dans 1 heure.');
            setLoading(false);
        } else {
            showMessage(data.message || 'Une erreur est survenue.');
            setLoading(false);
        }
    } catch (error) {
        console.error('[Forgot] Erreur:', error);
        showMessage('Erreur de connexion au serveur.');
        setLoading(false);
    }
});
</script>
</body>
</html>
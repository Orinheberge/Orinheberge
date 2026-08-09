<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/auth.php';

// Rediriger si déjà connecté
if ($is_logged_in) {
    header('Location: /client/');
    exit;
}
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('register.title'); ?> - OrinHeberge</title>
    <link rel="icon" type="image/png" href="https://heberge.orinstone.deepstone.fr/favicon.ico">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="manifest" href="/manifest.json">
    
    <meta name="keywords" content="inscription OrinHeberge, créer compte, signup, register, hébergement gratuit, inscription VPS, ouverture compte client, connexion Discord, connexion Google">
    <meta name="author" content="OrinHeberge">
    <link rel="canonical" href="https://heberge.orinstone.deepstone.fr/register/">

    <!-- Open Graph / Facebook -->
    <meta property="og:locale" content="fr_FR">
    <meta property="og:type" content="website">
    <meta property="og:title" content="Inscription - OrinHeberge | Rejoignez-nous">
    <meta property="og:description" content="Rejoignez OrinHeberge ! Créez votre compte gratuitement en quelques secondes via email, Discord ou Google.">
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
    <meta name="twitter:description" content="Rejoignez OrinHeberge ! Créez votre compte gratuitement via email, Discord ou Google.">
    <meta name="twitter:image" content="https://heberge.orinstone.deepstone.fr/favicon.png">
    <meta name="twitter:image:alt" content="OrinHeberge - Inscription gratuite">

    <meta name="theme-color" content="#6366f1">
    <meta name="msapplication-TileColor" content="#6366f1">
    <link rel="apple-touch-icon" href="https://heberge.orinstone.deepstone.fr/favicon.ico">

    <!-- Schema.org JSON-LD -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "RegisterAction",
      "name": "Inscription OrinHeberge",
      "url": "https://heberge.orinstone.deepstone.fr/register/",
      "description": "Page d'inscription pour créer un compte client chez OrinHeberge.",
      "agent": { "@type": "Person", "name": "Nouveau Client" },
      "result": { "@type": "Organization", "name": "OrinHeberge", "url": "https://heberge.orinstone.deepstone.fr/" }
    }
    </script>
    
    <style>
        body { background: radial-gradient(circle at top left, #1e293b, #020617); }
        .glass { background: rgba(255,255,255,0.03); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.1); }
        .gradient-text { background: linear-gradient(90deg, #38bdf8, #818cf8); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        
        @keyframes spin { to { transform: rotate(360deg); } }
        .spinner { animation: spin 1s linear infinite; }
    </style>
</head>
<body class="text-gray-200 flex flex-col justify-between min-h-screen font-sans">

    <main class="flex-grow flex items-center justify-center p-6 my-8">
        <div class="glass w-full max-w-lg p-10 rounded-3xl shadow-2xl">
            <div class="text-center mb-8">
                <h1 class="text-3xl font-black gradient-text uppercase tracking-tighter mb-2"><?php echo t('register.title'); ?></h1>
                <p class="text-gray-400 text-sm italic"><?php echo t('register.subtitle'); ?></p>
            </div>

            <!-- Zone messages (succès/erreur) -->
            <div id="messageBox" class="hidden mb-6 p-4 rounded-xl text-center text-sm"></div>

            <!-- 🔐 Boutons OAuth -->
            <div class="space-y-3 mb-6">
                <a href="/api/auth/discord.php" class="flex items-center justify-center gap-3 bg-[#5865F2] hover:bg-[#4752C4] text-white py-3.5 rounded-xl font-bold transition shadow-lg shadow-indigo-900/20 active:scale-95">
                    <i class="fab fa-discord text-xl"></i>
                    <span>Continuer avec Discord</span>
                </a>
                <a href="/api/auth/google.php" class="flex items-center justify-center gap-3 bg-white hover:bg-gray-100 text-gray-800 py-3.5 rounded-xl font-bold transition shadow-lg shadow-gray-900/20 active:scale-95">
                    <i class="fab fa-google text-xl"></i>
                    <span>Continuer avec Google</span>
                </a>
            </div>

            <!-- Séparateur -->
            <div class="flex items-center gap-3 my-6">
                <div class="flex-1 h-px bg-white/10"></div>
                <span class="text-xs text-gray-500 uppercase tracking-widest">ou par email</span>
                <div class="flex-1 h-px bg-white/10"></div>
            </div>

            <!-- 📝 Formulaire classique -->
            <form id="registerForm" class="grid grid-cols-1 md:grid-cols-2 gap-5">
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
                    <input type="password" name="password" required minlength="8" class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 focus:outline-none focus:border-sky-500 transition text-white" placeholder="•••••••• (min 8 caractères)">
                    <p class="text-xs text-gray-500 mt-1">Minimum 8 caractères</p>
                </div>
                <button type="submit" id="submitBtn" class="md:col-span-2 bg-sky-600 hover:bg-sky-500 py-4 rounded-xl font-black uppercase tracking-widest transition shadow-lg shadow-sky-600/20 active:scale-95 flex items-center justify-center gap-2">
                    <span id="submitText"><?php echo t('register.submit'); ?></span>
                    <i id="submitSpinner" class="fas fa-spinner spinner hidden"></i>
                </button>
            </form>

            <div class="mt-8 pt-6 border-t border-white/5 text-center">
                <p class="text-gray-500 text-sm">
                    <?php echo t('register.have_account'); ?> 
                    <a href="/login/" class="text-sky-400 font-bold hover:text-sky-300 transition"><?php echo t('register.login_link'); ?></a>
                </p>
            </div>

            <a href="/" class="block text-center mt-6 text-xs text-gray-600 hover:text-gray-400 transition">
                <i class="fas fa-arrow-left mr-2"></i> <?php echo t('register.back'); ?>
            </a>
        </div>
    </main>

    <!-- Bouton Discord flottant -->
    <div class="fixed bottom-6 right-6 z-50">
        <a href="https://heberge.orinstone.deepstone.fr/discord/" target="_blank" class="bg-[#5865F2] hover:bg-[#4752C4] transition text-white px-5 py-3.5 rounded-full font-bold flex items-center gap-2 shadow-2xl hover:scale-105 transform duration-200">
            <i class="fab fa-discord text-xl"></i>
            <span class="hidden sm:inline text-sm"><?php echo t('discord.help'); ?></span>
        </a>
    </div>

    <?php include $_SERVER['DOCUMENT_ROOT'] . '/inc/footer.php'; ?>

    <script>
    /**
     * Gestion du formulaire d'inscription via API
     */
    const form = document.getElementById('registerForm');
    const messageBox = document.getElementById('messageBox');
    const submitBtn = document.getElementById('submitBtn');
    const submitText = document.getElementById('submitText');
    const submitSpinner = document.getElementById('submitSpinner');

    function showMessage(text, type = 'error') {
        messageBox.textContent = text;
        messageBox.className = 'mb-6 p-4 rounded-xl text-center text-sm ' + (
            type === 'success' 
                ? 'bg-green-500/10 border border-green-500/50 text-green-400'
                : 'bg-red-500/10 border border-red-500/50 text-red-400'
        );
        messageBox.classList.remove('hidden');
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function setLoading(loading) {
        submitBtn.disabled = loading;
        submitText.textContent = loading ? 'Création...' : '<?php echo addslashes(t('register.submit')); ?>';
        submitSpinner.classList.toggle('hidden', !loading);
        submitBtn.classList.toggle('opacity-50', loading);
        submitBtn.classList.toggle('cursor-not-allowed', loading);
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        messageBox.classList.add('hidden');

        const formData = {
            firstname: form.firstname.value.trim(),
            lastname: form.lastname.value.trim(),
            email: form.email.value.trim(),
            password: form.password.value
        };

        // Validation côté client
        if (!formData.firstname || !formData.lastname || !formData.email || !formData.password) {
            showMessage('Tous les champs sont obligatoires.');
            return;
        }

        if (formData.password.length < 8) {
            showMessage('Le mot de passe doit contenir au moins 8 caractères.');
            return;
        }

        setLoading(true);

        try {
            const response = await fetch('/api/auth/register.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(formData)
            });

            const data = await response.json();

            if (data.success) {
                showMessage('✅ Compte créé avec succès ! Un email de bienvenue vous a été envoyé. Redirection...', 'success');
                
                // Redirection après 2 secondes
                setTimeout(() => {
                    window.location.href = '/client/';
                }, 2000);
            } else {
                showMessage(data.error || 'Une erreur est survenue.');
                setLoading(false);
            }
        } catch (error) {
            console.error('[Register] Erreur:', error);
            showMessage('Erreur de connexion au serveur. Veuillez réessayer.');
            setLoading(false);
        }
    });

    // Gérer les erreurs venant de l'URL (?error=...)
    const urlParams = new URLSearchParams(window.location.search);
    const urlError = urlParams.get('error');
    const errorMessages = {
        'oauth_cancelled': 'Connexion OAuth annulée.',
        'oauth_failed': 'Échec de la connexion OAuth. Veuillez réessayer.',
        'oauth_error': 'Erreur technique lors de la connexion OAuth.',
        'oauth_missing': 'Données OAuth manquantes.'
    };
    if (urlError && errorMessages[urlError]) {
        showMessage(errorMessages[urlError]);
    }
    </script>
</body>
</html>
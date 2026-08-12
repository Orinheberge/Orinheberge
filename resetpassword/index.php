<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/AuthService.php';

$token = $_GET['token'] ?? '';
$auth = new AuthService($pdo);

// Valider le token avant d'afficher le formulaire
$validation = $auth->validateResetToken($token);
$tokenValid = $validation['valid'] ?? false;
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?php echo t('reset.title'); ?> - OrinHeberge</title>
    <link rel="icon" type="image/png" href="https://heberge.orinstone.deepstone.fr/favicon.ico">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: radial-gradient(circle at top left, #1e293b, #020617); }
        .glass { background: rgba(255,255,255,0.03); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.1); }
        .gradient-text { background: linear-gradient(90deg,#38bdf8,#818cf8); -webkit-background-clip:text; -webkit-text-fill-color:transparent; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .spinner { animation: spin 1s linear infinite; }
        
        /* Indicateur de force du mot de passe */
        .strength-bar { height: 4px; border-radius: 2px; transition: all 0.3s; }
        .strength-weak { background: #ef4444; width: 33%; }
        .strength-medium { background: #f59e0b; width: 66%; }
        .strength-strong { background: #10b981; width: 100%; }
    </style>
</head>
<body class="min-h-screen text-gray-200 flex flex-col justify-between font-sans">

<?php $active_nav = ''; include $_SERVER['DOCUMENT_ROOT'] . '/inc/navbar.php'; ?>

<main class="flex-grow flex items-center justify-center p-6 my-8">
    <div class="glass w-full max-w-md p-8 rounded-3xl shadow-2xl">
        
        <?php if (!$tokenValid): ?>
            <!-- Token invalide -->
            <div class="text-center">
                <div class="w-20 h-20 bg-red-500/10 border border-red-500/30 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-exclamation-triangle text-3xl text-red-400"></i>
                </div>
                <h1 class="text-2xl font-black text-white mb-2">Lien invalide</h1>
                <p class="text-gray-400 text-sm mb-6">
                    <?php
                    $errorMessages = [
                        'invalid_token' => 'Ce lien est invalide ou a déjà été utilisé.',
                        'token_used'    => 'Ce lien a déjà été utilisé. Demandez-en un nouveau.',
                        'token_expired' => 'Ce lien a expiré. Les liens sont valides 1 heure seulement.'
                    ];
                    echo $errorMessages[$validation['error'] ?? 'invalid_token'];
                    ?>
                </p>
                <a href="/forgotpassword/" class="inline-block bg-sky-600 hover:bg-sky-500 px-6 py-3 rounded-xl font-bold transition">
                    <i class="fas fa-redo mr-2"></i>Demander un nouveau lien
                </a>
                <a href="/login/" class="block mt-4 text-xs text-gray-600 hover:text-gray-400 transition">
                    <i class="fas fa-arrow-left mr-2"></i>Retour à la connexion
                </a>
            </div>

        <?php else: ?>
            <!-- Formulaire de reset -->
            <div class="text-center mb-8">
                <div class="w-16 h-16 bg-sky-500/10 border border-sky-500/30 rounded-2xl flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-lock text-2xl text-sky-400"></i>
                </div>
                <h1 class="text-3xl font-black gradient-text uppercase tracking-tighter mb-2"><?php echo t('reset.heading'); ?></h1>
                <p class="text-gray-400 text-sm">Choisissez un nouveau mot de passe sécurisé</p>
            </div>

            <div id="messageBox" class="hidden mb-6 p-4 rounded-xl text-center text-sm border"></div>

            <!-- Success state -->
            <div id="successState" class="hidden text-center">
                <div class="w-20 h-20 bg-green-500/10 border border-green-500/30 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-check text-3xl text-green-400"></i>
                </div>
                <h2 class="text-xl font-bold text-white mb-2">Mot de passe modifié !</h2>
                <p class="text-gray-400 text-sm mb-6">
                    Vous pouvez maintenant vous connecter avec votre nouveau mot de passe.
                </p>
                <a href="/login/" class="inline-block bg-sky-600 hover:bg-sky-500 px-6 py-3 rounded-xl font-bold transition">
                    Se connecter maintenant
                </a>
            </div>

            <form id="resetForm" class="space-y-5">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                
                <div>
                    <label class="block text-xs font-bold uppercase tracking-widest text-gray-500 mb-2">
                        <?php echo t('reset.new_pw'); ?>
                    </label>
                    <div class="relative">
                        <i class="fas fa-lock absolute left-4 top-1/2 -translate-y-1/2 text-gray-500"></i>
                        <input type="password" name="password" id="password" required minlength="8" placeholder="••••••••"
                               class="w-full bg-white/5 border border-white/10 rounded-xl pl-11 pr-11 py-3 focus:outline-none focus:border-sky-500 transition text-white">
                        <button type="button" id="togglePassword1" class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-300">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <!-- Indicateur de force -->
                    <div class="mt-2 h-1 bg-white/5 rounded-full overflow-hidden">
                        <div id="strengthBar" class="strength-bar strength-weak" style="width: 0%"></div>
                    </div>
                    <p id="strengthText" class="text-xs text-gray-500 mt-1">Minimum 8 caractères avec lettres et chiffres</p>
                </div>
                
                <div>
                    <label class="block text-xs font-bold uppercase tracking-widest text-gray-500 mb-2">
                        <?php echo t('reset.confirm_pw'); ?>
                    </label>
                    <div class="relative">
                        <i class="fas fa-lock absolute left-4 top-1/2 -translate-y-1/2 text-gray-500"></i>
                        <input type="password" name="password_confirm" id="passwordConfirm" required placeholder="••••••••"
                               class="w-full bg-white/5 border border-white/10 rounded-xl pl-11 pr-11 py-3 focus:outline-none focus:border-sky-500 transition text-white">
                        <button type="button" id="togglePassword2" class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-300">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <p id="matchText" class="text-xs mt-1"></p>
                </div>

                <button type="submit" id="submitBtn" class="w-full bg-sky-600 hover:bg-sky-500 py-4 rounded-xl font-black uppercase tracking-widest transition shadow-lg active:scale-95 flex items-center justify-center gap-2">
                    <span id="submitText"><?php echo t('reset.submit'); ?></span>
                    <i id="submitSpinner" class="fas fa-spinner spinner hidden"></i>
                </button>
            </form>
        <?php endif; ?>
    </div>
</main>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/inc/footer.php'; ?>

<?php if ($tokenValid): ?>
<script>
const form = document.getElementById('resetForm');
const messageBox = document.getElementById('messageBox');
const successState = document.getElementById('successState');
const submitBtn = document.getElementById('submitBtn');
const submitText = document.getElementById('submitText');
const submitSpinner = document.getElementById('submitSpinner');
const passwordInput = document.getElementById('password');
const confirmInput = document.getElementById('passwordConfirm');
const strengthBar = document.getElementById('strengthBar');
const strengthText = document.getElementById('strengthText');
const matchText = document.getElementById('matchText');

// Toggle password visibility
document.getElementById('togglePassword1').addEventListener('click', function() {
    const type = passwordInput.type === 'password' ? 'text' : 'password';
    passwordInput.type = type;
    this.querySelector('i').classList.toggle('fa-eye');
    this.querySelector('i').classList.toggle('fa-eye-slash');
});
document.getElementById('togglePassword2').addEventListener('click', function() {
    const type = confirmInput.type === 'password' ? 'text' : 'password';
    confirmInput.type = type;
    this.querySelector('i').classList.toggle('fa-eye');
    this.querySelector('i').classList.toggle('fa-eye-slash');
});

// Indicateur de force du mot de passe
passwordInput.addEventListener('input', function() {
    const pw = this.value;
    let score = 0;
    if (pw.length >= 8) score++;
    if (pw.length >= 12) score++;
    if (/[A-Z]/.test(pw)) score++;
    if (/[0-9]/.test(pw)) score++;
    if (/[^A-Za-z0-9]/.test(pw)) score++;

    strengthBar.className = 'strength-bar';
    if (pw.length === 0) {
        strengthBar.style.width = '0%';
        strengthText.textContent = 'Minimum 8 caractères avec lettres et chiffres';
        strengthText.className = 'text-xs text-gray-500 mt-1';
    } else if (score <= 2) {
        strengthBar.classList.add('strength-weak');
        strengthText.textContent = '⚠️ Faible - ajoutez majuscules, chiffres ou symboles';
        strengthText.className = 'text-xs text-red-400 mt-1';
    } else if (score <= 3) {
        strengthBar.classList.add('strength-medium');
        strengthText.textContent = '🔶 Moyen';
        strengthText.className = 'text-xs text-amber-400 mt-1';
    } else {
        strengthBar.classList.add('strength-strong');
        strengthText.textContent = '✅ Fort';
        strengthText.className = 'text-xs text-green-400 mt-1';
    }

    checkMatch();
});

// Vérifier correspondance
confirmInput.addEventListener('input', checkMatch);
function checkMatch() {
    if (confirmInput.value === '') {
        matchText.textContent = '';
        confirmInput.classList.remove('border-red-500', 'border-green-500');
    } else if (confirmInput.value === passwordInput.value) {
        matchText.innerHTML = '<span class="text-green-400">✓ Les mots de passe correspondent</span>';
        confirmInput.classList.remove('border-red-500');
        confirmInput.classList.add('border-green-500');
    } else {
        matchText.innerHTML = '<span class="text-red-400">✗ Les mots de passe ne correspondent pas</span>';
        confirmInput.classList.remove('border-green-500');
        confirmInput.classList.add('border-red-500');
    }
}

function showMessage(text, type = 'error') {
    messageBox.innerHTML = text;
    messageBox.className = 'mb-6 p-4 rounded-xl text-center text-sm border ' + (
        type === 'success' ? 'bg-green-500/10 border-green-500/50 text-green-400' :
                             'bg-red-500/10 border-red-500/50 text-red-400'
    );
    messageBox.classList.remove('hidden');
}

function setLoading(loading) {
    submitBtn.disabled = loading;
    submitText.textContent = loading ? 'Modification...' : '<?php echo addslashes(t('reset.submit')); ?>';
    submitSpinner.classList.toggle('hidden', !loading);
    submitBtn.classList.toggle('opacity-50', loading);
    submitBtn.classList.toggle('cursor-not-allowed', loading);
}

form.addEventListener('submit', async (e) => {
    e.preventDefault();
    messageBox.classList.add('hidden');

    const token = form.token.value;
    const password = passwordInput.value;
    const confirm = confirmInput.value;

    if (password.length < 8) {
        showMessage('Le mot de passe doit contenir au moins 8 caractères.');
        return;
    }
    if (!/[A-Za-z]/.test(password) || !/[0-9]/.test(password)) {
        showMessage('Le mot de passe doit contenir des lettres et des chiffres.');
        return;
    }
    if (password !== confirm) {
        showMessage('Les mots de passe ne correspondent pas.');
        return;
    }

    setLoading(true);

    try {
        const response = await fetch('/api/auth/reset-password.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ token, password })
        });

        const data = await response.json();

        if (data.success) {
            form.classList.add('hidden');
            successState.classList.remove('hidden');
        } else {
            showMessage(data.error || 'Erreur lors de la modification.');
            setLoading(false);
        }
    } catch (error) {
        console.error('[Reset] Erreur:', error);
        showMessage('Erreur de connexion au serveur.');
        setLoading(false);
    }
});
</script>
<?php endif; ?>
</body>
</html>
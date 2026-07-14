<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/lang.php';

// ── Détection du code d'erreur ─────────────────────────────────────────────────
$error_code = (int)($_GET['code'] ?? $_GET['e'] ?? ($_SERVER['REDIRECT_STATUS'] ?? 0));

// Messages selon le code d'erreur
$errors = [
    400 => [
        'title'       => 'Requête invalide',
        'description' => 'La requête envoyée au serveur est mal formée ou incompréhensible.',
        'icon'        => 'fa-triangle-exclamation',
        'color'       => 'amber',
        'suggestions' => [
            'Vérifiez l\'URL saisie',
            'Videz le cache de votre navigateur',
            'Réessayez dans quelques instants',
        ],
    ],
    401 => [
        'title'       => 'Non autorisé',
        'description' => 'Vous devez vous connecter pour accéder à cette page.',
        'icon'        => 'fa-lock',
        'color'       => 'orange',
        'suggestions' => [
            'Connectez-vous à votre compte',
            'Vérifiez vos identifiants',
            'Réinitialisez votre mot de passe si nécessaire',
        ],
        'actions' => [
            ['label' => 'Se connecter', 'url' => '/login/', 'icon' => 'fa-sign-in-alt', 'style' => 'primary'],
        ],
    ],
    403 => [
        'title'       => 'Accès refusé',
        'description' => 'Vous n\'avez pas les permissions nécessaires pour accéder à cette ressource.',
        'icon'        => 'fa-shield-halved',
        'color'       => 'red',
        'suggestions' => [
            'Vérifiez que vous êtes connecté',
            'Contactez un administrateur si vous pensez avoir les droits',
            'Retournez à la page d\'accueil',
        ],
        'actions' => [
            ['label' => 'Retour à l\'accueil', 'url' => '/', 'icon' => 'fa-home', 'style' => 'primary'],
            ['label' => 'Contacter le support', 'url' => '/support/', 'icon' => 'fa-headset', 'style' => 'secondary'],
        ],
    ],
    404 => [
        'title'       => 'Page introuvable',
        'description' => 'La page que vous recherchez n\'existe pas, a été déplacée ou supprimée.',
        'icon'        => 'fa-ghost',
        'color'       => 'sky',
        'suggestions' => [
            'Vérifiez l\'orthographe de l\'URL',
            'Utilisez le menu de navigation',
            'Recherchez dans notre boutique',
        ],
        'actions' => [
            ['label' => 'Retour à l\'accueil', 'url' => '/', 'icon' => 'fa-home', 'style' => 'primary'],
            ['label' => 'Voir la boutique', 'url' => '/shop/', 'icon' => 'fa-tags', 'style' => 'secondary'],
            ['label' => 'Mes serveurs', 'url' => '/client/servers/', 'icon' => 'fa-server', 'style' => 'secondary'],
        ],
    ],
    405 => [
        'title'       => 'Méthode non autorisée',
        'description' => 'La méthode HTTP utilisée n\'est pas supportée pour cette ressource.',
        'icon'        => 'fa-ban',
        'color'       => 'rose',
        'suggestions' => [
            'Rechargez la page',
            'Utilisez un lien valide',
        ],
    ],
    429 => [
        'title'       => 'Trop de requêtes',
        'description' => 'Vous avez effectué trop de requêtes en peu de temps. Veuillez patienter.',
        'icon'        => 'fa-hourglass-half',
        'color'       => 'purple',
        'suggestions' => [
            'Attendez quelques minutes avant de réessayer',
            'Vérifiez si un script tourne en boucle',
        ],
    ],
    500 => [
        'title'       => 'Erreur interne du serveur',
        'description' => 'Un problème inattendu s\'est produit côté serveur. Nos équipes ont été notifiées.',
        'icon'        => 'fa-bug',
        'color'       => 'red',
        'suggestions' => [
            'Réessayez dans quelques instants',
            'Videz le cache de votre navigateur',
            'Contactez le support si le problème persiste',
        ],
        'actions' => [
            ['label' => 'Retour à l\'accueil', 'url' => '/', 'icon' => 'fa-home', 'style' => 'primary'],
            ['label' => 'Contacter le support', 'url' => '/support/', 'icon' => 'fa-headset', 'style' => 'secondary'],
            ['label' => 'Statut des services', 'url' => '/status/', 'icon' => 'fa-signal', 'style' => 'secondary'],
        ],
    ],
    502 => [
        'title'       => 'Passerelle invalide',
        'description' => 'Le serveur a reçu une réponse invalide d\'un serveur en amont.',
        'icon'        => 'fa-network-wired',
        'color'       => 'orange',
        'suggestions' => [
            'Réessayez dans quelques instants',
            'Vérifiez le statut de nos services',
        ],
        'actions' => [
            ['label' => 'Statut des services', 'url' => '/status/', 'icon' => 'fa-signal', 'style' => 'primary'],
            ['label' => 'Retour à l\'accueil', 'url' => '/', 'icon' => 'fa-home', 'style' => 'secondary'],
        ],
    ],
    503 => [
        'title'       => 'Service temporairement indisponible',
        'description' => 'Le site est en maintenance ou surchargé. Nous reviendrons bientôt !',
        'icon'        => 'fa-wrench',
        'color'       => 'amber',
        'suggestions' => [
            'Revenez dans quelques minutes',
            'Consultez notre page de statut',
            'Suivez-nous sur Discord pour les annonces',
        ],
        'actions' => [
            ['label' => 'Statut des services', 'url' => '/status/', 'icon' => 'fa-signal', 'style' => 'primary'],
            ['label' => 'Rejoindre Discord', 'url' => '/discord/', 'icon' => 'fa-brands fa-discord', 'style' => 'secondary'],
        ],
    ],
    504 => [
        'title'       => 'Délai d\'attente dépassé',
        'description' => 'Le serveur n\'a pas répondu dans le temps imparti.',
        'icon'        => 'fa-clock',
        'color'       => 'orange',
        'suggestions' => [
            'Réessayez dans quelques instants',
            'Vérifiez votre connexion internet',
        ],
    ],
];

// Erreur par défaut si code non reconnu
if (!isset($errors[$error_code]) || $error_code === 0) {
    $error_code = 404;
}

$error = $errors[$error_code];
$color = $error['color'];

// Actions par défaut si non définies
if (!isset($error['actions'])) {
    $error['actions'] = [
        ['label' => 'Retour à l\'accueil', 'url' => '/', 'icon' => 'fa-home', 'style' => 'primary'],
        ['label' => 'Contacter le support', 'url' => '/support/', 'icon' => 'fa-headset', 'style' => 'secondary'],
    ];
}

$is_logged_in = isset($_SESSION['user_id']);
$active_nav = '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Erreur <?= $error_code ?> — <?= htmlspecialchars($error['title']) ?> | OrinHeberge</title>
    <link rel="icon" type="image/png" href="https://heberge.orinstone.deepstone.fr/favicon.ico">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="manifest" href="/manifest.json">
    <style>
        body { 
            background: #070a13; 
            scroll-behavior: smooth;
            font-family: -apple-system, 'Segoe UI', system-ui, sans-serif;
        }
        .glass {
            background: rgba(255,255,255,0.03);
            backdrop-filter: blur(14px);
            border: 1px solid rgba(255,255,255,0.06);
        }
        .gradient-text {
            background: linear-gradient(90deg, #38bdf8, #818cf8);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        /* Animation de l'icône */
        .error-icon {
            animation: float 3s ease-in-out infinite;
        }
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
        
        /* Effet de glitch sur le code */
        .error-code {
            position: relative;
            display: inline-block;
        }
        .error-code::before,
        .error-code::after {
            content: attr(data-code);
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0.8;
        }
        .error-code::before {
            animation: glitch-1 2s infinite;
            color: #f43f5e;
            z-index: -1;
        }
        .error-code::after {
            animation: glitch-2 2s infinite;
            color: #38bdf8;
            z-index: -2;
        }
        @keyframes glitch-1 {
            0%, 100% { transform: translate(0); }
            20% { transform: translate(-2px, 2px); }
            40% { transform: translate(-2px, -2px); }
            60% { transform: translate(2px, 2px); }
            80% { transform: translate(2px, -2px); }
        }
        @keyframes glitch-2 {
            0%, 100% { transform: translate(0); }
            20% { transform: translate(2px, -2px); }
            40% { transform: translate(2px, 2px); }
            60% { transform: translate(-2px, -2px); }
            80% { transform: translate(-2px, 2px); }
        }
        
        /* Particules d'arrière-plan */
        .bg-particles {
            position: fixed;
            inset: 0;
            overflow: hidden;
            pointer-events: none;
            z-index: 0;
        }
        .particle {
            position: absolute;
            width: 2px;
            height: 2px;
            background: rgba(56, 189, 248, 0.3);
            border-radius: 50%;
            animation: drift 20s infinite linear;
        }
        @keyframes drift {
            from { transform: translateY(100vh) translateX(0); opacity: 0; }
            10% { opacity: 1; }
            90% { opacity: 1; }
            to { transform: translateY(-100vh) translateX(100px); opacity: 0; }
        }
    </style>
</head>
<body class="text-gray-200 min-h-screen flex flex-col">

<!-- Particules d'arrière-plan -->
<div class="bg-particles">
    <?php for ($i = 0; $i < 30; $i++): ?>
    <div class="particle" style="
        left: <?= rand(0, 100) ?>%;
        animation-delay: <?= rand(0, 20) ?>s;
        animation-duration: <?= rand(15, 30) ?>s;
    "></div>
    <?php endfor; ?>
</div>

<!-- Navigation -->
<?php include __DIR__ . $_SERVER['DOCUMENT_ROOT'] . '/inc/navbar.php'; ?>

<!-- Contenu principal -->
<main class="flex-grow flex items-center justify-center px-4 py-16 relative z-10">
    <div class="glass p-10 sm:p-12 rounded-3xl w-full max-w-2xl text-center shadow-2xl border border-white/[0.05]">
        
        <!-- Code d'erreur avec effet glitch -->
        <div class="mb-6">
            <div class="error-code text-8xl sm:text-9xl font-black tracking-tighter text-white" data-code="<?= $error_code ?>">
                <?= $error_code ?>
            </div>
        </div>

        <!-- Icône animée -->
        <div class="error-icon w-20 h-20 bg-<?= $color ?>-500/10 border border-<?= $color ?>-500/30 text-<?= $color ?>-400 rounded-full flex items-center justify-center mx-auto mb-6 text-3xl">
            <i class="fas <?= $error['icon'] ?>"></i>
        </div>

        <!-- Titre et description -->
        <h1 class="text-3xl sm:text-4xl font-black tracking-tight mb-3 text-white">
            <?= htmlspecialchars($error['title']) ?>
        </h1>
        <p class="text-gray-400 text-base mb-8 max-w-md mx-auto leading-relaxed">
            <?= htmlspecialchars($error['description']) ?>
        </p>

        <!-- Suggestions -->
        <div class="bg-white/[0.02] border border-white/[0.05] rounded-2xl p-5 mb-8 text-left">
            <div class="text-xs font-bold uppercase tracking-wider text-gray-500 mb-3 flex items-center gap-2">
                <i class="fas fa-lightbulb text-amber-400"></i>
                Que pouvez-vous faire ?
            </div>
            <ul class="space-y-2">
                <?php foreach ($error['suggestions'] as $suggestion): ?>
                <li class="flex items-start gap-2 text-sm text-gray-300">
                    <i class="fas fa-chevron-right text-<?= $color ?>-400 text-xs mt-1 shrink-0"></i>
                    <span><?= htmlspecialchars($suggestion) ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <!-- Boutons d'action -->
        <div class="flex flex-wrap gap-3 justify-center">
            <?php foreach ($error['actions'] as $action): ?>
                <?php 
                $btn_class = $action['style'] === 'primary' 
                    ? 'bg-sky-600 hover:bg-sky-500 text-white shadow-lg shadow-sky-900/20' 
                    : 'bg-white/5 hover:bg-white/10 border border-white/10 text-gray-200';
                ?>
                <a href="<?= htmlspecialchars($action['url']) ?>" 
                   class="<?= $btn_class ?> px-6 py-3 rounded-xl font-bold transition text-sm flex items-center gap-2 no-underline">
                    <i class="fas <?= $action['icon'] ?>"></i>
                    <?= htmlspecialchars($action['label']) ?>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Info technique (optionnel) -->
        <?php if ($error_code >= 500): ?>
        <div class="mt-8 pt-6 border-t border-white/[0.05]">
            <p class="text-xs text-gray-500">
                <i class="fas fa-info-circle mr-1"></i>
                Référence : <code class="text-gray-400 bg-black/30 px-2 py-0.5 rounded"><?= strtoupper(substr(md5(uniqid()), 0, 12)) ?></code>
            </p>
            <p class="text-xs text-gray-600 mt-2">
                Si le problème persiste, contactez le support avec cette référence.
            </p>
        </div>
        <?php endif; ?>

    </div>
</main>

<!-- Footer -->
<?php include __DIR__ . $_SERVER['DOCUMENT_ROOT'] . '/inc/footer.php'; ?>

</body>
</html>
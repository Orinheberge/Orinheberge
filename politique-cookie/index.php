<?php
/**
 * OrinHeberge — Politique relative aux Cookies
 */

// Simulation / chargement du système de langue si nécessaire
if (!function_exists('t')) {
    require_once __DIR__ . '/inc/lang.php';
}

$lang = $_SESSION['lang'] ?? 'fr';
$pageTitle = "Politique relative aux Cookies — OrinHeberge";
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($lang); ?>" class="h-full bg-[#05070d]">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="flex flex-col min-h-screen text-gray-300 font-sans antialiased bg-[#05070d] selection:bg-sky-500 selection:text-white">

    <header class="relative border-b border-white/5 py-16 overflow-hidden">
        <div class="absolute inset-0 bg-gradient-to-b from-sky-500/10 via-transparent to-transparent pointer-events-none"></div>
        <div class="max-w-5xl mx-auto px-6 relative z-10 text-center">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-sky-500/10 border border-sky-500/20 text-sky-400 text-xs font-semibold uppercase tracking-wider mb-4">
                <i class="fas fa-cookie-bite text-xs"></i> Respect de la vie privée
            </div>
            <h1 class="text-3xl md:text-5xl font-black text-white tracking-tight mb-4">
                Politique relative aux <span class="text-sky-500">Cookies</span>
            </h1>
            <p class="text-gray-400 text-sm md:text-base max-w-2xl mx-auto leading-relaxed">
                Transparence totale sur l'utilisation des traceurs et la gestion de vos données de navigation sur OrinHeberge.
            </p>
        </div>
    </header>

    <main class="flex-1 max-w-5xl mx-auto px-6 py-12 w-full">
        
        <div class="bg-white/[0.02] border border-white/5 rounded-2xl p-6 md:p-10 backdrop-blur-xl space-y-10">

            <section class="space-y-3">
                <h2 class="text-xl font-bold text-white flex items-center gap-3">
                    <span class="w-8 h-8 rounded-lg bg-sky-500/10 border border-sky-500/20 text-sky-400 flex items-center justify-center text-xs">01</span>
                    Qu'est-ce qu'un cookie ?
                </h2>
                <p class="text-gray-400 text-sm leading-relaxed pl-11">
                    Un cookie est un petit fichier texte déposé sur votre terminal (ordinateur, tablette ou mobile) lors de la visite d'un site web. Il permet au site de mémoriser vos actions et préférences (identifiant de connexion, langue, taille de police et autres paramètres d'affichage) pendant un temps donné.
                </p>
            </section>

            <section class="space-y-4">
                <h2 class="text-xl font-bold text-white flex items-center gap-3">
                    <span class="w-8 h-8 rounded-lg bg-sky-500/10 border border-sky-500/20 text-sky-400 flex items-center justify-center text-xs">02</span>
                    Les cookies que nous utilisons
                </h2>
                <p class="text-gray-400 text-sm leading-relaxed pl-11 mb-4">
                    OrinHeberge utilise un nombre très limité de cookies afin de garantir la sécurité, la stabilité et le bon fonctionnement de la plateforme.
                </p>

                <div class="pl-11 overflow-x-auto">
                    <table class="w-full text-left text-xs text-gray-400 border border-white/5 rounded-xl overflow-hidden">
                        <thead class="bg-white/[0.03] text-white uppercase font-semibold">
                            <tr>
                                <th class="p-3 border-b border-white/5">Nom du cookie</th>
                                <th class="p-3 border-b border-white/5">Type</th>
                                <th class="p-3 border-b border-white/5">Finalité</th>
                                <th class="p-3 border-b border-white/5">Durée</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            <tr class="hover:bg-white/[0.01]">
                                <td class="p-3 font-mono text-sky-400">PHPSESSID</td>
                                <td class="p-3"><span class="px-2 py-0.5 rounded bg-emerald-500/10 text-emerald-400 font-semibold text-[10px]">Essentiel</span></td>
                                <td class="p-3">Maintient votre session utilisateur ouverte et votre panier d'achat.</td>
                                <td class="p-3">Fin de session</td>
                            </tr>
                            <tr class="hover:bg-white/[0.01]">
                                <td class="p-3 font-mono text-sky-400">lang</td>
                                <td class="p-3"><span class="px-2 py-0.5 rounded bg-emerald-500/10 text-emerald-400 font-semibold text-[10px]">Préférence</span></td>
                                <td class="p-3">Conserve votre préférence de langue (Français / Anglais).</td>
                                <td class="p-3">1 an</td>
                            </tr>
                            <tr class="hover:bg-white/[0.01]">
                                <td class="p-3 font-mono text-sky-400">__cf_bm / cf_clearance</td>
                                <td class="p-3"><span class="px-2 py-0.5 rounded bg-purple-500/10 text-purple-400 font-semibold text-[10px]">Sécurité</span></td>
                                <td class="p-3">Protection anti-DDoS et vérification de sécurité Cloudflare.</td>
                                <td class="p-3">30 min à 1 an</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="space-y-3">
                <h2 class="text-xl font-bold text-white flex items-center gap-3">
                    <span class="w-8 h-8 rounded-lg bg-sky-500/10 border border-sky-500/20 text-sky-400 flex items-center justify-center text-xs">03</span>
                    Cookies tiers & Prestataires
                </h2>
                <div class="pl-11 text-gray-400 text-sm leading-relaxed space-y-3">
                    <p>
                        Certains services intégrés sur notre plateforme peuvent déposer des cookies techniques nécessaires à leur fonctionnement :
                    </p>
                    <ul class="list-disc list-inside space-y-1 text-gray-400">
                        <strong class="text-white">Stripe / PayPal :</strong> Sécurisation des transactions bancaires et prévention de la fraude lors du paiement.
                        <strong class="text-white">Cloudflare :</strong> Sécurité du réseau et filtrage des attaques informatiques.
                    </ul>
                    <blockquote class="bg-sky-500/5 border-l-2 border-sky-500 p-3 rounded-r-lg text-xs text-sky-200 mt-2">
                        <i class="fas fa-info-circle mr-1"></i> OrinHeberge ne dépose <strong>aucun cookie publicitaire</strong> ni cookie de suivi ou de ciblage commercial tiers.
                    </blockquote>
                </div>
            </section>

            <section class="space-y-3">
                <h2 class="text-xl font-bold text-white flex items-center gap-3">
                    <span class="w-8 h-8 rounded-lg bg-sky-500/10 border border-sky-500/20 text-sky-400 flex items-center justify-center text-xs">04</span>
                    Comment gérer ou tout bloquer ?
                </h2>
                <div class="pl-11 text-gray-400 text-sm leading-relaxed space-y-3">
                    <p>
                        Vous pouvez à tout moment configurer votre navigateur pour refuser ou supprimer les cookies. Notez toutefois que la désactivation complète des cookies strictement nécessaires peut perturber l'accès à votre espace client et l'achat de services.
                    </p>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 pt-2">
                        <a href="https://support.google.com/chrome/answer/95647" target="_blank" class="p-3 rounded-lg bg-white/[0.03] border border-white/5 hover:border-sky-500/30 text-center transition group">
                            <i class="fab fa-chrome text-lg text-gray-400 group-hover:text-sky-400 mb-1 block"></i>
                            <span class="text-xs text-gray-300 font-semibold">Google Chrome</span>
                        </a>
                        <a href="https://support.mozilla.org/fr/kb/protection-renforcee-contre-le-pistage-firefox-pour-ordinateur" target="_blank" class="p-3 rounded-lg bg-white/[0.03] border border-white/5 hover:border-sky-500/30 text-center transition group">
                            <i class="fab fa-firefox-browser text-lg text-gray-400 group-hover:text-sky-400 mb-1 block"></i>
                            <span class="text-xs text-gray-300 font-semibold">Mozilla Firefox</span>
                        </a>
                        <a href="https://support.apple.com/fr-fr/guide/safari/sfri11471/mac" target="_blank" class="p-3 rounded-lg bg-white/[0.03] border border-white/5 hover:border-sky-500/30 text-center transition group">
                            <i class="fab fa-safari text-lg text-gray-400 group-hover:text-sky-400 mb-1 block"></i>
                            <span class="text-xs text-gray-300 font-semibold">Apple Safari</span>
                        </a>
                        <a href="https://support.microsoft.com/fr-fr/microsoft-edge/supprimer-les-cookies-dans-microsoft-edge-63947406-400c-57f2-438e-d0dd99992b37" target="_blank" class="p-3 rounded-lg bg-white/[0.03] border border-white/5 hover:border-sky-500/30 text-center transition group">
                            <i class="fab fa-edge text-lg text-gray-400 group-hover:text-sky-400 mb-1 block"></i>
                            <span class="text-xs text-gray-300 font-semibold">Microsoft Edge</span>
                        </a>
                    </div>
                </div>
            </section>

            <section class="space-y-3 pt-4 border-t border-white/5">
                <h2 class="text-xl font-bold text-white flex items-center gap-3">
                    <span class="w-8 h-8 rounded-lg bg-sky-500/10 border border-sky-500/20 text-sky-400 flex items-center justify-center text-xs">05</span>
                    Contact & Questions
                </h2>
                <p class="text-gray-400 text-sm leading-relaxed pl-11">
                    Pour toute question relative à cette politique ou à la gestion de vos données personnelles, vous pouvez nous contacter à l'adresse email : 
                    <a href="mailto:deepstone@deepstone.fr" class="text-sky-400 hover:underline font-mono">deepstone@deepstone.fr</a>.
                </p>
            </section>

        </div>

    </main>

    <?php 
    // Inclusion du footer partagé
    include_once __DIR__ . '/inc/footer.php'; 
    ?>

</body>
</html>
<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/lang.php';

$is_logged_in = isset($_SESSION['user_id']);

// Liste des partenaires (à gérer depuis la BDD plus tard si besoin)
$partners = [
    [
        'name'        => 'NitroHebergeur',
        'logo'        => '/img/partners/nitrohebergeur.png', // À ajouter
        'url'         => 'https://www.nitrohebergeur.fr/',
        'description' => 'Hébergement web haute performance et infrastructure cloud professionnelle. Solutions adaptées aux besoins des développeurs et entreprises.',
        'services'    => ['Hébergement Web', 'Serveurs Cloud', 'VPS'],
        'color'       => '#3b82f6', // Bleu
    ],
    // Ajouter d'autres partenaires ici
];
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Partenaires — OrinHeberge</title>
    <meta name="description" content="Découvrez nos partenaires de confiance pour l'hébergement et l'infrastructure cloud.">
    <link rel="icon" type="image/png" href="/favicon.ico">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="manifest" href="/manifest.json">
    <style>
        body { background: radial-gradient(circle at top left, #1e293b, #020617); }
        .glass { background: rgba(255,255,255,0.03); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.1); }
        .gradient-text { background: linear-gradient(90deg, #38bdf8, #818cf8); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .partner-card {
            background: linear-gradient(135deg, rgba(255,255,255,.04), rgba(255,255,255,.02));
            border: 1px solid rgba(255,255,255,.08);
            transition: all .3s ease;
        }
        .partner-card:hover {
            transform: translateY(-4px);
            border-color: rgba(56, 189, 248, .3);
            box-shadow: 0 20px 40px rgba(56, 189, 248, .1);
        }
        .badge-service {
            background: rgba(56, 189, 248, .1);
            border: 1px solid rgba(56, 189, 248, .2);
            color: #38bdf8;
            font-size: 11px;
            padding: 4px 10px;
            border-radius: 999px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .hero-gradient {
            background: linear-gradient(135deg, rgba(56, 189, 248, .1), rgba(129, 140, 248, .1));
            border: 1px solid rgba(56, 189, 248, .2);
        }
    </style>
    <script>
        function toggleMenu() { document.getElementById('mobileMenu').classList.toggle('hidden'); }
    </script>
</head>
<body class="text-gray-200 flex flex-col justify-between min-h-screen font-sans">

    <?php $active_nav = 'partners'; include $_SERVER['DOCUMENT_ROOT'] . '/inc/navbar.php'; ?>

    <main class="flex-grow">
        <!-- Hero Section -->
        <section class="py-20 px-6">
            <div class="max-w-6xl mx-auto text-center">
                <div class="inline-flex items-center gap-2 px-4 py-2 rounded-full mb-6 text-xs font-bold uppercase tracking-wider hero-gradient">
                    <i class="fas fa-handshake text-sky-400"></i>
                    <span class="text-sky-400">Nos Partenaires</span>
                </div>
                <h1 class="text-4xl md:text-6xl font-black gradient-text mb-6 leading-tight">
                    Ensemble pour<br>votre réussite
                </h1>
                <p class="text-gray-400 text-lg md:text-xl max-w-2xl mx-auto leading-relaxed">
                    Découvrez nos partenaires de confiance qui partagent notre vision d'un hébergement de qualité et accessible à tous.
                </p>
            </div>
        </section>

        <!-- Partenaires Grid -->
        <section class="py-12 px-6">
            <div class="max-w-6xl mx-auto">
                <?php if (empty($partners)): ?>
                <div class="text-center py-20">
                    <i class="fas fa-users text-6xl text-gray-700 mb-4"></i>
                    <p class="text-gray-500">Aucun partenaire pour le moment.</p>
                </div>
                <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($partners as $p): ?>
                    <div class="partner-card rounded-3xl p-8 flex flex-col">
                        <!-- Logo/Nom -->
                        <div class="mb-6">
                            <?php if (!empty($p['logo']) && file_exists($_SERVER['DOCUMENT_ROOT'] . $p['logo'])): ?>
                            <img src="<?php echo htmlspecialchars($p['logo']); ?>" 
                                 alt="<?php echo htmlspecialchars($p['name']); ?>" 
                                 class="h-12 object-contain brightness-110">
                            <?php else: ?>
                            <div class="flex items-center gap-3">
                                <div class="w-12 h-12 rounded-xl flex items-center justify-center text-2xl font-black text-white" 
                                     style="background: linear-gradient(135deg, <?php echo $p['color'] ?? '#38bdf8'; ?>, <?php echo $p['color'] ?? '#818cf8'; ?>);">
                                    <?php echo strtoupper(substr($p['name'], 0, 1)); ?>
                                </div>
                                <h3 class="text-xl font-black text-white"><?php echo htmlspecialchars($p['name']); ?></h3>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Description -->
                        <p class="text-gray-400 text-sm leading-relaxed mb-6 flex-grow">
                            <?php echo htmlspecialchars($p['description']); ?>
                        </p>

                        <!-- Services -->
                        <?php if (!empty($p['services'])): ?>
                        <div class="flex flex-wrap gap-2 mb-6">
                            <?php foreach ($p['services'] as $service): ?>
                            <span class="badge-service">
                                <i class="fas fa-check-circle text-[10px]"></i>
                                <?php echo htmlspecialchars($service); ?>
                            </span>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                        <!-- CTA -->
                        <a href="<?php echo htmlspecialchars($p['url']); ?>" 
                           target="_blank" 
                           rel="noopener noreferrer"
                           class="flex items-center justify-center gap-2 bg-gradient-to-r from-sky-600 to-blue-600 hover:from-sky-500 hover:to-blue-500 text-white font-bold py-3 px-6 rounded-xl transition shadow-lg shadow-sky-900/30 text-sm">
                            <i class="fas fa-external-link-alt text-xs"></i>
                            Visiter le site
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- Section Devenir Partenaire -->
        <section class="py-20 px-6">
            <div class="max-w-4xl mx-auto">
                <div class="glass rounded-3xl p-10 md:p-12 text-center">
                    <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-sky-500 to-purple-500 flex items-center justify-center mx-auto mb-6">
                        <i class="fas fa-rocket text-white text-2xl"></i>
                    </div>
                    <h2 class="text-3xl md:text-4xl font-black text-white mb-4">
                        Vous souhaitez devenir partenaire ?
                    </h2>
                    <p class="text-gray-400 text-lg mb-8 max-w-2xl mx-auto">
                        Rejoignez notre réseau de partenaires et développons ensemble des synergies pour offrir le meilleur à nos clients.
                    </p>
                    <div class="flex flex-col sm:flex-row gap-4 justify-center">
                        <a href="https://heberge.orinstone.deepstone.fr/discord/" 
                           target="_blank"
                           class="bg-[#5865F2] hover:bg-[#4752C4] text-white font-bold py-4 px-8 rounded-xl transition shadow-lg shadow-[#5865F2]/20 flex items-center justify-center gap-3">
                            <i class="fab fa-discord text-xl"></i>
                            <span>Contactez-nous sur Discord</span>
                        </a>
                        <a href="/support/" 
                           class="bg-white/5 hover:bg-white/10 border border-white/10 text-white font-bold py-4 px-8 rounded-xl transition flex items-center justify-center gap-3">
                            <i class="fas fa-envelope"></i>
                            <span>Ouvrir un ticket</span>
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <!-- Stats Section -->
        <section class="py-12 px-6 mb-20">
            <div class="max-w-6xl mx-auto">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="glass rounded-2xl p-6 text-center">
                        <div class="text-3xl md:text-4xl font-black gradient-text mb-2"><?php echo count($partners); ?></div>
                        <div class="text-gray-400 text-sm">Partenaire<?php echo count($partners) > 1 ? 's' : ''; ?></div>
                    </div>
                    <div class="glass rounded-2xl p-6 text-center">
                        <div class="text-3xl md:text-4xl font-black text-sky-400 mb-2">
                            <i class="fas fa-infinity"></i>
                        </div>
                        <div class="text-gray-400 text-sm">Possibilités</div>
                    </div>
                    <div class="glass rounded-2xl p-6 text-center">
                        <div class="text-3xl md:text-4xl font-black text-purple-400 mb-2">100%</div>
                        <div class="text-gray-400 text-sm">Confiance</div>
                    </div>
                    <div class="glass rounded-2xl p-6 text-center">
                        <div class="text-3xl md:text-4xl font-black text-green-400 mb-2">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <div class="text-gray-400 text-sm">Qualité</div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- Bouton Discord flottant -->
    <div class="fixed bottom-6 right-6 z-50">
        <a href="https://heberge.orinstone.deepstone.fr/discord/" target="_blank" class="bg-[#5865F2] hover:bg-[#4752C4] transition text-white px-5 py-3.5 rounded-full font-bold flex items-center gap-2 shadow-2xl hover:scale-105 transform duration-200">
            <i class="fab fa-discord text-xl"></i>
            <span class="hidden sm:inline text-sm">Besoin d'aide ?</span>
        </a>
    </div>

    <?php include $_SERVER['DOCUMENT_ROOT'] . '/inc/footer.php'; ?>
</body>
</html>

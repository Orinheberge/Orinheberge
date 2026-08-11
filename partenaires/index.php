<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/lang.php';

$is_logged_in = isset($_SESSION['user_id']);

// Liste des partenaires (à gérer depuis la BDD plus tard si besoin)
$partners = [
    [
        'name'        => 'NitroHebergeur',
        'logo'        => '/assets/img/partners/nitrohebergeur.png', // À ajouter
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
            background: linear-gradient(135deg, rgba(15,23,42,.8), rgba(30,41,59,.6));
            border: 1px solid rgba(56,189,248,.15);
            transition: all .4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        .partner-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #38bdf8, #818cf8, #c084fc);
            opacity: 0;
            transition: opacity .4s;
        }
        .partner-card:hover {
            transform: translateY(-8px) scale(1.02);
            border-color: rgba(56, 189, 248, .5);
            box-shadow: 0 30px 60px rgba(56, 189, 248, .2), 0 0 80px rgba(129, 140, 248, .15);
            background: linear-gradient(135deg, rgba(15,23,42,.95), rgba(30,41,59,.8));
        }
        .partner-card:hover::before {
            opacity: 1;
        }
        .partner-logo-area {
            min-height: 180px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, rgba(56,189,248,.08), rgba(129,140,248,.05));
            border-radius: 20px;
            border: 1px solid rgba(56,189,248,.1);
            transition: all .3s;
        }
        .partner-card:hover .partner-logo-area {
            background: linear-gradient(135deg, rgba(56,189,248,.15), rgba(129,140,248,.1));
            border-color: rgba(56,189,248,.25);
            transform: scale(1.05);
        }
        .badge-service {
            background: rgba(56, 189, 248, .12);
            border: 1px solid rgba(56, 189, 248, .25);
            color: #38bdf8;
            font-size: 12px;
            padding: 6px 14px;
            border-radius: 999px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all .2s;
        }
        .badge-service:hover {
            background: rgba(56, 189, 248, .2);
            border-color: rgba(56, 189, 248, .4);
            transform: scale(1.05);
        }
        .hero-gradient {
            background: linear-gradient(135deg, rgba(56, 189, 248, .12), rgba(129, 140, 248, .12));
            border: 1px solid rgba(56, 189, 248, .25);
        }
        .cta-button {
            background: linear-gradient(135deg, #0ea5e9, #6366f1);
            transition: all .3s;
            position: relative;
            overflow: hidden;
        }
        .cta-button::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, #38bdf8, #818cf8);
            opacity: 0;
            transition: opacity .3s;
        }
        .cta-button:hover::before {
            opacity: 1;
        }
        .cta-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 40px rgba(14, 165, 233, .3);
        }
        .cta-button span {
            position: relative;
            z-index: 1;
        }
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
        .partner-card:hover .partner-icon {
            animation: float 2s ease-in-out infinite;
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
            <div class="max-w-7xl mx-auto">
                <?php if (empty($partners)): ?>
                <div class="text-center py-20">
                    <i class="fas fa-users text-6xl text-gray-700 mb-4"></i>
                    <p class="text-gray-500">Aucun partenaire pour le moment.</p>
                </div>
                <?php else: ?>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <?php foreach ($partners as $p): ?>
                    <div class="partner-card rounded-3xl overflow-hidden">
                        <!-- Zone Logo/Nom (grande) -->
                        <div class="partner-logo-area p-10">
                            <?php if (!empty($p['logo']) && file_exists($_SERVER['DOCUMENT_ROOT'] . $p['logo'])): ?>
                            <img src="<?php echo htmlspecialchars($p['logo']); ?>" 
                                 alt="<?php echo htmlspecialchars($p['name']); ?>" 
                                 class="max-h-32 object-contain brightness-110 drop-shadow-2xl partner-icon">
                            <?php else: ?>
                            <div class="partner-icon">
                                <div class="w-28 h-28 rounded-2xl flex items-center justify-center text-6xl font-black text-white shadow-2xl" 
                                     style="background: linear-gradient(135deg, <?php echo $p['color'] ?? '#38bdf8'; ?>, <?php echo $p['color'] ?? '#818cf8'; ?>20);">
                                    <?php echo strtoupper(substr($p['name'], 0, 1)); ?>
                                </div>
                                <h3 class="text-3xl font-black text-white mt-6 tracking-tight"><?php echo htmlspecialchars($p['name']); ?></h3>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Contenu -->
                        <div class="p-10 space-y-6">
                            <!-- Description -->
                            <p class="text-gray-300 text-base leading-relaxed">
                                <?php echo htmlspecialchars($p['description']); ?>
                            </p>

                            <!-- Services avec icônes -->
                            <?php if (!empty($p['services'])): ?>
                            <div class="flex flex-wrap gap-3">
                                <?php 
                                $icons = [
                                    'Hébergement Web' => 'fa-globe',
                                    'Serveurs Cloud' => 'fa-cloud',
                                    'VPS' => 'fa-server',
                                    'Base de données' => 'fa-database',
                                    'Email' => 'fa-envelope',
                                ];
                                foreach ($p['services'] as $service): 
                                    $icon = $icons[$service] ?? 'fa-check-circle';
                                ?>
                                <span class="badge-service">
                                    <i class="fas <?php echo $icon; ?>"></i>
                                    <?php echo htmlspecialchars($service); ?>
                                </span>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>

                            <!-- Séparateur -->
                            <div class="border-t border-white/10"></div>

                            <!-- CTA grand format -->
                            <a href="<?php echo htmlspecialchars($p['url']); ?>" 
                               target="_blank" 
                               rel="noopener noreferrer"
                               class="cta-button flex items-center justify-center gap-3 text-white font-black py-5 px-8 rounded-2xl shadow-2xl text-lg group">
                                <span class="flex items-center gap-3">
                                    <i class="fas fa-rocket text-xl group-hover:rotate-12 transition-transform"></i>
                                    <span>Découvrir <?php echo htmlspecialchars($p['name']); ?></span>
                                    <i class="fas fa-arrow-right text-sm group-hover:translate-x-2 transition-transform"></i>
                                </span>
                            </a>

                            <!-- Infos complémentaires -->
                            <div class="flex items-center justify-center gap-6 text-xs text-gray-500">
                                <div class="flex items-center gap-2">
                                    <i class="fas fa-shield-alt text-green-400"></i>
                                    <span>Partenaire certifié</span>
                                </div>
                                <div class="flex items-center gap-2">
                                    <i class="fas fa-star text-yellow-400"></i>
                                    <span>Recommandé</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- Message si 1 seul partenaire -->
                <?php if (count($partners) === 1): ?>
                <div class="mt-12 text-center">
                    <div class="inline-flex items-center gap-3 px-6 py-4 rounded-2xl bg-white/5 border border-white/10">
                        <i class="fas fa-info-circle text-sky-400 text-lg"></i>
                        <span class="text-gray-400">
                            D'autres partenaires arrivent bientôt ! 
                            <a href="https://heberge.orinstone.deepstone.fr/discord/" target="_blank" class="text-sky-400 hover:text-sky-300 font-bold ml-1">
                                Rejoignez notre Discord pour les découvrir en avant-première →
                            </a>
                        </span>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- Section Devenir Partenaire -->
        <section class="py-20 px-6">
            <div class="max-w-5xl mx-auto">
                <div class="relative overflow-hidden rounded-3xl p-12 md:p-16 text-center" 
                     style="background: linear-gradient(135deg, rgba(14,165,233,.12), rgba(99,102,241,.12)); border: 2px solid rgba(56,189,248,.3);">
                    <!-- Background pattern -->
                    <div class="absolute inset-0 opacity-10">
                        <div class="absolute top-10 left-10 w-32 h-32 bg-sky-400 rounded-full blur-3xl"></div>
                        <div class="absolute bottom-10 right-10 w-40 h-40 bg-purple-400 rounded-full blur-3xl"></div>
                    </div>
                    
                    <div class="relative z-10">
                        <div class="w-24 h-24 rounded-3xl bg-gradient-to-br from-sky-500 via-blue-500 to-purple-500 flex items-center justify-center mx-auto mb-8 shadow-2xl" style="animation: float 3s ease-in-out infinite;">
                            <i class="fas fa-rocket text-white text-4xl"></i>
                        </div>
                        <h2 class="text-4xl md:text-5xl font-black text-white mb-6 leading-tight">
                            Devenez notre<br>prochain partenaire
                        </h2>
                        <p class="text-gray-300 text-xl mb-10 max-w-2xl mx-auto leading-relaxed">
                            Rejoignez notre écosystème et développons ensemble des <strong class="text-sky-400">synergies gagnantes</strong> pour offrir le meilleur à nos communautés respectives.
                        </p>
                        
                        <!-- Avantages -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-12 max-w-3xl mx-auto">
                            <div class="bg-white/5 backdrop-blur rounded-2xl p-6 border border-white/10">
                                <i class="fas fa-users text-3xl text-sky-400 mb-3"></i>
                                <div class="text-white font-bold mb-1">Visibilité accrue</div>
                                <div class="text-gray-400 text-sm">Exposition à notre communauté</div>
                            </div>
                            <div class="bg-white/5 backdrop-blur rounded-2xl p-6 border border-white/10">
                                <i class="fas fa-handshake text-3xl text-purple-400 mb-3"></i>
                                <div class="text-white font-bold mb-1">Collaboration</div>
                                <div class="text-gray-400 text-sm">Projets communs et synergies</div>
                            </div>
                            <div class="bg-white/5 backdrop-blur rounded-2xl p-6 border border-white/10">
                                <i class="fas fa-chart-line text-3xl text-green-400 mb-3"></i>
                                <div class="text-white font-bold mb-1">Croissance</div>
                                <div class="text-gray-400 text-sm">Développement mutuel</div>
                            </div>
                        </div>

                        <div class="flex flex-col sm:flex-row gap-4 justify-center">
                            <a href="https://heberge.orinstone.deepstone.fr/discord/" 
                               target="_blank"
                               class="bg-[#5865F2] hover:bg-[#4752C4] text-white font-black py-5 px-10 rounded-2xl transition shadow-2xl flex items-center justify-center gap-3 text-lg group">
                                <i class="fab fa-discord text-2xl group-hover:scale-110 transition-transform"></i>
                                <span>Contactez-nous sur Discord</span>
                            </a>
                            <a href="/support/" 
                               class="bg-white/10 hover:bg-white/20 border-2 border-white/20 hover:border-white/40 text-white font-black py-5 px-10 rounded-2xl transition flex items-center justify-center gap-3 text-lg backdrop-blur">
                                <i class="fas fa-ticket-alt"></i>
                                <span>Ouvrir un ticket</span>
                            </a>
                        </div>
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

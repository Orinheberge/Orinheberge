<?php
// 1. Inclusion des fichiers de configuration et de langue
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/lang.php'; 
// Ce fichier est censé fournir $pdo (connexion partagée) et potentiellement $db_status

session_start();
$is_logged_in = isset($_SESSION['user_id']);

// 2. Initialisation sécurisée ou fallback de la connexion si non définie
if (!isset($pdo)) {
    try {
        $pdo = new PDO('mysql:host=localhost;port=3306;dbname=s43_orinheberge;charset=utf8mb4', 'root', '1504', [
            PDO::ATTR_TIMEOUT => 3,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
        $db_status = true;
    } catch (PDOException $e) {
        $db_status = false;
    }
} else {
    $db_status = true;
}

// 3. Récupération des données utilisateur si connecté
if ($is_logged_in && $db_status) {
    try {
        $stmt = $pdo->prepare("SELECT pseudo, firstname, avatar FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$_SESSION['user_id']]);
        $user_data = $stmt->fetch();
        
        if ($user_data) {
            $_SESSION['username'] = !empty($user_data['pseudo']) ? $user_data['pseudo'] : $user_data['firstname'];
            $_SESSION['avatar']   = $user_data['avatar'];
        }
    } catch (PDOException $e) {
        // Optionnel : logger l'erreur discrètement
    }
}

// 4. Chargement dynamique des offres (Pterodactyl / Hébergement)
$all_products = [];
if ($db_status) {
    try {
        // Ajout d'une colonne 'tier' générique ou tri par défaut si 'tier' n'est pas une ENUM
        $all_products = $pdo->query("
            SELECT p.*
            FROM products p
            WHERE p.is_active = 1
            ORDER BY p.sort_order ASC, p.id ASC
        ")->fetchAll();
    } catch (PDOException $e) {
        $all_products = [];
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OrinHeberge | Boutique & Offres</title>
    <link rel="icon" type="image/png" href="https://heberge.orinstone.deepstone.fr/favicon.ico">
    <meta name="description" content="Boutique OrinHeberge - Découvrez nos offres VPS, hébergement Minecraft, Node.js et PHP premium.">
    <meta name="keywords" content="shop, boutique, vps, minecraft, nodejs, php, hébergement premium">
    <meta name="author" content="OrinHeberge">
    <meta property="og:title" content="Boutique - OrinHeberge">
    <meta property="og:description" content="Choisissez une offre performante et sécurisée chez OrinHeberge.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://heberge.orinstone.deepstone.fr/shop/">

    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<link rel="manifest" href="/manifest.json">

<script>
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
      navigator.serviceWorker.register('/sw.js')
        .then(reg => console.log('Service Worker enregistré avec succès ! Scope:', reg.scope))
        .catch(err => console.log('Échec de l\'enregistrement du Service Worker:', err));
    });
  }
</script>
    <style>
        body { background: #0b0f19; scroll-behavior: smooth; }
        .glass {
            background: rgba(255,255,255,0.04);
            backdrop-filter: blur(14px);
            border: 1px solid rgba(255,255,255,0.08);
        }
        .gradient-text {
            background: linear-gradient(90deg, #38bdf8, #818cf8);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .card-hover { transition: transform .3s, box-shadow .3s; }
        .card-hover:hover { transform: translateY(-6px); box-shadow: 0 20px 40px rgba(0,0,0,0.3); }
        #mobileMenu { display: none; }
        #mobileMenu.active { display: block; }
        .tab-btn {
            padding: 0.6rem 1.5rem;
            border-radius: 9999px;
            font-size: 0.85rem;
            font-weight: 600;
            transition: all .2s;
            border: 1px solid rgba(255,255,255,0.1);
            background: rgba(255,255,255,0.04);
            color: #9ca3af;
            cursor: pointer;
            white-space: nowrap;
        }
        .tab-btn:hover { background: rgba(255,255,255,0.08); color: #e5e7eb; }
        .tab-btn.active {
            background: rgba(56,189,248,0.15);
            border-color: rgba(56,189,248,0.4);
            color: #38bdf8;
            box-shadow: 0 0 15px rgba(56,189,248,0.1);
        }
        #cat-view { display: none; }
        #cat-view .cat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.5rem;
        }
    </style>

    <script>
        function toggleMenu() {
            document.getElementById("mobileMenu").classList.toggle("active");
        }

        function filterCategory(catId) {
            // Mise à jour onglets
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.getElementById('tab-' + catId).classList.add('active');

            const catView   = document.getElementById('cat-view');
            const catTitle  = document.getElementById('cat-view-title');
            const catGrid   = document.getElementById('cat-view-grid');
            const allSections = document.getElementById('all-sections');

            if (catId === 'all') {
                // Mode normal : toutes les sections par tier
                catView.style.display    = 'none';
                allSections.style.display = 'block';
                return;
            }

            // Mode catégorie : on masque les sections et on affiche la vue regroupée
            allSections.style.display = 'none';
            catView.style.display     = 'block';

            // Nom affiché dans le titre
            const labels = {
                minecraft: 'Minecraft', fivem: 'FiveM', hytale: 'Hytale',
                php: 'Web / PHP', python: 'Python',
                nodejs: 'Node.js', java: 'Java'
            };
            catTitle.textContent = labels[catId] || catId;

            // Récupère toutes les cartes de cette catégorie depuis le DOM caché
            const cards = Array.from(document.querySelectorAll('#all-sections .offer-card[data-category="' + catId + '"]'));

            // Tri par prix numérique (0€ en premier)
            cards.sort((a, b) => {
                const priceOf = el => parseFloat(el.dataset.price) || 0;
                return priceOf(a) - priceOf(b);
            });

            // Vide et remplit la grille
            catGrid.innerHTML = '';
            cards.forEach(card => {
                const clone = card.cloneNode(true);
                clone.style.display = 'flex';
                catGrid.appendChild(clone);
            });
        }

        window.addEventListener('DOMContentLoaded', () => filterCategory('all'));
    </script>
</head>
<body class="text-gray-200 font-sans min-h-screen flex flex-col justify-between">

    <?php if (!$db_status): ?>
        <div class="bg-red-600/80 text-white text-center py-2 text-sm font-semibold backdrop-blur-sm sticky top-0 z-50">
            <i class="fas fa-exclamation-triangle mr-2"></i> Connexion aux services de la boutique momentanément perturbée.
        </div>
    <?php endif; ?>

    <!-- NAV -->
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/inc/navbar.php'; ?>

    <main class="flex-grow">

        <!-- Offers header & tabs -->
    <section class="py-16 px-6 text-center">
        <h2 class="text-5xl md:text-6xl font-black uppercase tracking-wider mb-3 gradient-text"><?php echo t('offers.title'); ?></h2>
        <p class="text-gray-400 text-lg max-w-xl mx-auto mb-10"><?php echo t('offers.subtitle'); ?></p>
        <div class="max-w-4xl mx-auto flex flex-wrap justify-center gap-3 px-4">
            <button onclick="filterCategory('all')"       id="tab-all"       class="tab-btn active"><?php echo t('offers.tab.all'); ?></button>
            <button onclick="filterCategory('minecraft')" id="tab-minecraft" class="tab-btn">Minecraft</button>
            <button onclick="filterCategory('fivem')"     id="tab-fivem"     class="tab-btn">FiveM</button>
            <button onclick="filterCategory('hytale')"    id="tab-hytale"    class="tab-btn">Hytale</button>
            <button onclick="filterCategory('php')"       id="tab-php"       class="tab-btn">Web / PHP</button>
            <button onclick="filterCategory('python')"    id="tab-python"    class="tab-btn">Python</button>
            <button onclick="filterCategory('nodejs')"    id="tab-nodejs"    class="tab-btn">Node.js</button>
            <button onclick="filterCategory('java')"      id="tab-java"      class="tab-btn">Java</button>
        </div>
    </section>

    <?php
    // ── Présentation (statique, ne dépend pas de la BDD) ────────────────────
    $images = [
        'minecraft' => 'https://www.4netplayers.com/images/minecraft/blog/teaser-image.jpg',
        'fivem'     => 'https://images.unsplash.com/photo-1542751371-adc38448a05e?q=80&w=600&auto=format&fit=crop',
        'hytale'    => 'https://cdn.minestrator.com/blog/articles/155/thumbnail.webp',
        'php'       => 'https://images.unsplash.com/photo-1555066931-4365d14bab8c?q=80&w=600&auto=format&fit=crop',
        'nodejs'    => 'https://images.unsplash.com/photo-1607799279861-4dd421887fb3?q=80&w=600&auto=format&fit=crop',
        'java'      => 'https://images.unsplash.com/photo-1607799279861-4dd421887fb3?q=80&w=600&auto=format&fit=crop',
        'python'    => 'https://images.unsplash.com/photo-1542831371-29b0f74f9713?q=80&w=600&auto=format&fit=crop',
    ];
    $icons = [
        'minecraft' => 'fas fa-cube',    'fivem'  => 'fas fa-car',       'hytale' => 'fas fa-gamepad',
        'php'       => 'fas fa-code',    'nodejs' => 'fab fa-node-js',
        'java'      => 'fab fa-java',    'python' => 'fab fa-python',
    ];
    $tier_styles = [
        'free'    => ['badge_bg'=>'bg-green-500/20',  'badge_text'=>'text-green-400',  'badge_border'=>'border-green-500/30',  'icon_color'=>'text-green-400',  'card_border'=>'border-white/10',      'btn'=>'bg-green-500 hover:bg-green-400'],
        'basic'   => ['badge_bg'=>'bg-blue-500/20',   'badge_text'=>'text-blue-400',   'badge_border'=>'border-blue-500/30',   'icon_color'=>'text-blue-400',   'card_border'=>'border-blue-400/20',   'btn'=>'bg-blue-500 hover:bg-blue-400'],
        'medium'  => ['badge_bg'=>'bg-purple-500/20', 'badge_text'=>'text-purple-400', 'badge_border'=>'border-purple-500/30', 'icon_color'=>'text-purple-400', 'card_border'=>'border-purple-400/20', 'btn'=>'bg-purple-500 hover:bg-purple-400'],
        'premium' => ['badge_bg'=>'bg-yellow-500/20', 'badge_text'=>'text-yellow-400', 'badge_border'=>'border-yellow-500/30', 'icon_color'=>'text-yellow-400', 'card_border'=>'border-yellow-400/20', 'btn'=>'bg-yellow-500 hover:bg-yellow-400'],
    ];
    $tier_meta = [
        'free'    => ['title_key' => 'tier.free.title',    'subtitle_key' => 'tier.free.subtitle',    'label_key' => 'tier.free.label',    'accent' => 'bg-green-500',  'bg' => 'bg-white/[0.01] border-y border-white/5'],
        'basic'   => ['title_key' => 'tier.basic.title',   'subtitle_key' => 'tier.basic.subtitle',   'label_key' => 'tier.basic.label',   'accent' => 'bg-blue-500',   'bg' => 'bg-black/10'],
        'medium'  => ['title_key' => 'tier.medium.title',  'subtitle_key' => 'tier.medium.subtitle',  'label_key' => 'tier.medium.label',  'accent' => 'bg-purple-500', 'bg' => 'bg-white/[0.02] border-y border-white/5'],
        'premium' => ['title_key' => 'tier.premium.title', 'subtitle_key' => 'tier.premium.subtitle', 'label_key' => 'tier.premium.label', 'accent' => 'bg-yellow-500', 'bg' => 'bg-black/20'],
    ];

    // ── Regroupement des produits BDD par tier ──────────────────────────────
    // Tout produit actif dans `products` (colonnes ajoutées par
    // products_shop_migration.sql : category, tier, plan_slug, is_free,
    // is_popular, name_key, desc_key, period_key, features) apparaît ici
    // automatiquement, sans toucher ce fichier.
    $sections = [];
    foreach ($all_products as $p) {
        $tier = $p['tier'] ?: 'basic';
        if (!isset($tier_meta[$tier])) continue; // tier inconnu : on ignore proprement

        if (!isset($sections[$tier])) {
            $sections[$tier] = $tier_meta[$tier] + ['offers' => []];
        }

        $features = [];
        if (!empty($p['features'])) {
            $decoded = json_decode($p['features'], true);
            if (is_array($decoded)) $features = $decoded;
        }

        $sections[$tier]['offers'][] = [
            'category'   => $p['category'],
            'name_key'   => $p['name_key'],
            'desc_key'   => $p['desc_key'],
            'price'      => $p['is_free'] ? '0€' : number_format((float)$p['price'], 2, ',', '') . '€',
            'period_key' => $p['period_key'] ?: 'offers.period.month',
            'plan'       => $p['plan_slug'],
            'free'       => (bool)$p['is_free'],
            'popular'    => (bool)$p['is_popular'],
            'features'   => $features,
        ];
    }

    // Réordonne selon l'ordre d'affichage fixe des tiers (les tiers sans offre active sont sautés)
    $tier_order = ['free', 'basic', 'medium', 'premium'];
    $ordered_sections = [];
    foreach ($tier_order as $t) {
        if (!empty($sections[$t])) $ordered_sections[$t] = $sections[$t];
    }
    ?>

    <!-- Category view -->
    <section id="cat-view" class="py-20 px-6">
        <div class="text-center mb-12">
            <h2 class="text-4xl md:text-5xl font-black uppercase tracking-wider mb-3 gradient-text" id="cat-view-title"></h2>
            <div class="h-1 w-20 bg-sky-500 mx-auto rounded-full"></div>
            <p class="text-gray-400 mt-4"><?php echo t('offers.tab.cat_subtitle'); ?></p>
        </div>
        <div class="max-w-7xl mx-auto cat-grid" id="cat-view-grid"></div>
    </section>

    <!-- All sections -->
    <div id="all-sections">
    <?php if (empty($ordered_sections)): ?>
        <div class="text-center py-20 text-gray-500">
            <i class="fas fa-box-open text-3xl mb-3"></i>
            <p>Aucune offre disponible pour le moment.</p>
        </div>
    <?php endif; ?>
    <?php foreach ($ordered_sections as $tier_key => $section): ?>
    <?php $s = $tier_styles[$tier_key]; ?>
    <section class="offers-section py-20 px-6 <?php echo $section['bg']; ?>">
        <div class="text-center mb-16">
            <h2 class="text-4xl md:text-5xl font-black uppercase tracking-wider mb-3"><?php echo t($section['title_key']); ?></h2>
            <div class="h-1 w-20 <?php echo $section['accent']; ?> mx-auto rounded-full"></div>
            <p class="text-gray-400 mt-4 text-base md:text-lg"><?php echo t($section['subtitle_key']); ?></p>
        </div>

        <div class="max-w-7xl mx-auto grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($section['offers'] as $offer): ?>
            <?php
                $cat      = $offer['category'];
                $img      = $images[$cat] ?? $images['php'];
                $icon     = $icons[$cat] ?? 'fas fa-server';
                $popular  = !empty($offer['popular']);
                $border_class = $popular ? 'border-2 border-sky-500 bg-sky-500/[0.02]' : "border {$s['card_border']}";
                $route    = $offer['free'] ? '/shop/process_free/?type=' . $offer['plan'] : '/shop/order/?plan=' . $offer['plan'];
                $btn_text = $offer['free']
                    ? ($is_logged_in ? ($cat === 'php' ? t('btn.host_site') : t('btn.deploy')) : t('btn.login_to_buy'))
                    : ($is_logged_in ? t('btn.buy') : t('btn.login_to_buy'));
                $link      = $is_logged_in ? $route : '/login/';
                $price_num = $offer['free'] ? 0 : (float) str_replace([',', '€'], ['.', ''], $offer['price']);
            ?>
            <div data-category="<?php echo $cat; ?>" data-price="<?php echo $price_num; ?>"
                 class="offer-card glass rounded-3xl <?php echo $border_class; ?> flex flex-col card-hover overflow-hidden relative">

                <?php if ($popular): ?>
                    <div class="absolute top-3 right-3 z-10 bg-sky-500 text-slate-950 px-3 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider shadow-lg"><?php echo t('btn.popular'); ?></div>
                <?php endif; ?>

                <div class="h-44 w-full bg-cover bg-center relative" style="background-image: url('<?php echo htmlspecialchars($img); ?>');">
                    <div class="absolute inset-0 bg-gradient-to-t from-[#070a13] to-transparent"></div>
                    <div class="absolute top-4 left-4 right-4 flex justify-between items-center">
                        <span class="<?php echo $s['badge_bg'].' '.$s['badge_text'].' '.$s['badge_border']; ?> px-3 py-1 rounded-full text-xs font-bold backdrop-blur-md border uppercase tracking-wide">
                            <?php echo t($section['label_key']); ?>
                        </span>
                        <i class="<?php echo $icon.' '.$s['icon_color']; ?> text-2xl drop-shadow"></i>
                    </div>
                </div>

                <div class="p-6 flex flex-col flex-grow">
                    <h3 class="text-xl font-bold text-white"><?php echo t($offer['name_key']); ?></h3>
                    <p class="text-gray-400 mt-2 mb-6 text-sm flex-grow"><?php echo t($offer['desc_key']); ?></p>

                    <div class="flex items-baseline mb-6">
                        <span class="text-3xl font-black text-white"><?php echo $offer['price']; ?></span>
                        <span class="text-gray-500 text-xs ml-1"><?php echo t($offer['period_key']); ?></span>
                    </div>

                    <ul class="space-y-3 text-gray-300 text-sm border-t border-white/5 pt-4">
                        <?php foreach ($offer['features'] as $feat): ?>
                        <li>
                            <i class="<?php echo ($feat['icon'] ?? 'fas fa-check').' '.$s['icon_color']; ?> mr-2 w-4"></i><?php echo isset($feat['text_key']) ? t($feat['text_key']) : ($feat['text'] ?? ''); ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>

                    <a href="<?php echo $link; ?>" class="mt-6 w-full <?php echo $s['btn']; ?> text-slate-950 transition py-3 text-center rounded-xl font-bold block text-sm shadow-lg">
                        <?php echo $btn_text; ?>
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endforeach; ?>
    </div>


    </main>

	<?php include $_SERVER['DOCUMENT_ROOT'] . '/inc/footer.php'; ?>

    <div class="fixed bottom-6 right-6 z-50">
    <a href="https://heberge.orinstone.deepstone.fr/discord/" target="_blank"
       class="bg-[#5865F2] hover:bg-[#4752C4] transition text-white px-5 py-4 rounded-full font-bold flex items-center gap-2 shadow-2xl hover:scale-105 transform duration-200">
        <i class="fab fa-discord text-xl"></i>
        <span class="hidden sm:inline text-sm"><?php echo t('discord.help'); ?></span>
    </a>
</div>

</body>
</html>

<?php
/**
 * OrinHeberge — Page Unifiée : Catégories & Offres
 */
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/lang.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/db.php';

$active_nav = 'offers';
$page_title = 'Nos Offres';
$is_logged_in = isset($_SESSION['user_id']);

// ============================================
// 1. MACRO-CATÉGORIES (Navigation visuelle)
// ============================================
$main_categories = [
    'gaming' => [
        'title_key'       => 'categories.gaming.title',
        'description_key' => 'categories.gaming.description',
        'subtitle_key'    => 'categories.gaming.subtitle',
        'icon'            => 'fas fa-gamepad',
        'color'           => 'from-blue-500 to-purple-600',
        'subcategories'   => ['minecraft', 'fivem', 'terraria', 'hytale'],
        'link'            => '/offres/gaming/' // <--- AJOUT DU LIEN ICI
    ],
    'web' => [
        'title_key'       => 'categories.web.title',
        'description_key' => 'categories.web.description',
        'subtitle_key'    => 'categories.web.subtitle',
        'icon'            => 'fas fa-code',
        'color'           => 'from-green-500 to-blue-500',
        'subcategories'   => ['php', 'nodejs', 'python', 'java'],
        'link'            => '/offres/Web-&-Applications/'
    ],
    'database' => [
        'title_key'       => 'categories.database.title',
        'description_key' => 'categories.database.description',
        'subtitle_key'    => 'categories.database.subtitle',
        'icon'            => 'fas fa-database',
        'color'           => 'from-yellow-500 to-orange-500',
        'subcategories'   => ['mysql', 'mongodb', 'postgresql']
    ],
    'storage' => [
        'title_key'       => 'categories.storage.title',
        'description_key' => 'categories.storage.description',
        'subtitle_key'    => 'categories.storage.subtitle',
        'icon'            => 'fas fa-cloud',
        'color'           => 'from-purple-500 to-pink-500',
        'subcategories'   => ['files', 'backup', 'cdn']
    ]
];

// ============================================
// 2. TIERS D'OFFRES
// ============================================
$sections = [
    'free'    => ['title_key'=>'tier.free.title',    'subtitle_key'=>'tier.free.subtitle',    'label_key'=>'tier.free.label',    'accent'=>'bg-green-500',  'bg'=>'bg-white/[0.01] border-y border-white/5', 'offers'=>[]],
    'basic'   => ['title_key'=>'tier.basic.title',   'subtitle_key'=>'tier.basic.subtitle',   'label_key'=>'tier.basic.label',   'accent'=>'bg-blue-500',   'bg'=>'bg-black/10', 'offers'=>[]],
    'medium'  => ['title_key'=>'tier.medium.title',  'subtitle_key'=>'tier.medium.subtitle',  'label_key'=>'tier.medium.label',  'accent'=>'bg-purple-500', 'bg'=>'bg-white/[0.02] border-y border-white/5', 'offers'=>[]],
    'premium' => ['title_key'=>'tier.premium.title', 'subtitle_key'=>'tier.premium.subtitle', 'label_key'=>'tier.premium.label', 'accent'=>'bg-yellow-500', 'bg'=>'bg-black/20', 'offers'=>[]],
    'mythic'  => ['title_key'=>'tier.mythic.title',  'subtitle_key'=>'tier.mythic.subtitle',  'label_key'=>'tier.mythic.label',  'accent'=>'bg-rose-500',   'bg'=>'bg-black/20', 'offers'=>[]],
];

$dynamic_categories = [];

// ============================================
// 3. DONNÉES BDD
// ============================================
try {
    if ($is_logged_in) {
        $u = $pdo->prepare('SELECT pseudo,firstname,avatar FROM users WHERE id=? LIMIT 1');
        $u->execute([$_SESSION['user_id']]);
        $ud = $u->fetch();
        if ($ud) {
            $_SESSION['username'] = !empty($ud['pseudo']) ? $ud['pseudo'] : $ud['firstname'];
            $_SESSION['avatar']   = $ud['avatar'];
        }
    }

    // Récupération des catégories actives
    $cq = $pdo->query('
        SELECT DISTINCT category_slug, name_key, icon, image_url, description_key
        FROM categories_products 
        WHERE is_active=1 
        GROUP BY category_slug, name_key, icon, image_url, description_key
        ORDER BY sort_order ASC
    ');
    while ($r = $cq->fetch()) {
        $dynamic_categories[$r['category_slug']] = [
            'name_key'        => $r['name_key'],
            'icon'            => $r['icon'],
            'image_url'       => $r['image_url'],
            'description_key' => $r['description_key'] ?? null,
        ];
    }

    // Compteur de produits
    $count_stmt = $pdo->query("
        SELECT cp.category_slug, COUNT(cp.product_id) as product_count
        FROM categories_products cp
        JOIN products p ON p.id = cp.product_id
        WHERE cp.is_active = 1 AND p.is_active = 1
        GROUP BY cp.category_slug
    ");
    while ($row = $count_stmt->fetch()) {
        if (isset($dynamic_categories[$row['category_slug']])) {
            $dynamic_categories[$row['category_slug']]['product_count'] = $row['product_count'];
        }
    }

    // Récupération des produits
    $stmt = $pdo->query("
        SELECT p.*, cp.category_slug, cp.icon AS cat_icon, cp.image_url AS cat_image
        FROM categories_products cp
        JOIN products p ON p.id = cp.product_id
        WHERE cp.is_active=1 AND p.is_active=1
        ORDER BY p.sort_order, p.id
    ");

    foreach ($stmt->fetchAll() as $pr) {
        $slug = $pr['slug'];
        $cat  = strtolower($pr['category_slug']);
        
        // Détermination du Tier
        $tier = match(true) {
            str_contains($slug, 'free')   => 'free',
            str_contains($slug, 'basic')  => 'basic',
            str_contains($slug, 'medium') => 'medium',
            str_contains($slug, 'mythic') => 'mythic',
            default => 'premium',
        };

        $rt = $pr['ram']  >= 1024 ? number_format($pr['ram']/1024, 0).' GB RAM DDR5' : $pr['ram'].' MB RAM';
        $dt = $pr['disk'] >= 1024 ? number_format($pr['disk']/1024, 0).' GB SSD NVMe' : $pr['disk'].' MB SSD';

        $sections[$tier]['offers'][] = [
            'category'    => $cat,
            'slug'        => $slug,
            'name'        => $pr['name'],
            'desc'        => $pr['description'] ?? '',
            'price'       => $pr['type']==='free' ? '0€' : number_format($pr['price'], 2, ',', '').'€',
            'price_value' => (float)$pr['price'],
            'period_key'  => $pr['type']==='free' ? 'offers.period.free' : 'offers.period.month',
            'free'        => $pr['type']==='free',
            'icon'        => $pr['cat_icon'] ?? 'fas fa-server',
            'image_url'   => $pr['cat_image'] ?? '',
            'features'    => [
                ['icon'=>'fas fa-memory',     'text'=>$rt],
                ['icon'=>'fas fa-hard-drive', 'text'=>$dt],
                ['icon'=>'fas fa-microchip',  'text'=>$pr['cpu'].'% CPU'],
                ['icon'=>'fas fa-database',   'text'=>$pr['databases'].' BDD'],
            ],
        ];
    }
} catch (PDOException $e) {
    error_log('Error: ' . $e->getMessage());
}

// Helpers
function tierStyle(string $t): array {
    $styles = [
        'free'    => ['bb'=>'bg-green-500/20',  'bt'=>'text-green-400',  'bbd'=>'border-green-500/30',  'ic'=>'text-green-400',  'cb'=>'border-white/10',      'btn'=>'bg-green-500 hover:bg-green-400'],
        'basic'   => ['bb'=>'bg-blue-500/20',   'bt'=>'text-blue-400',   'bbd'=>'border-blue-500/30',   'ic'=>'text-blue-400',   'cb'=>'border-blue-400/20',   'btn'=>'bg-blue-500 hover:bg-blue-400'],
        'medium'  => ['bb'=>'bg-purple-500/20', 'bt'=>'text-purple-400', 'bbd'=>'border-purple-500/30', 'ic'=>'text-purple-400', 'cb'=>'border-purple-400/20', 'btn'=>'bg-purple-500 hover:bg-purple-400'],
        'premium' => ['bb'=>'bg-yellow-500/20', 'bt'=>'text-yellow-400', 'bbd'=>'border-yellow-500/30', 'ic'=>'text-yellow-400', 'cb'=>'border-yellow-400/20', 'btn'=>'bg-yellow-500 hover:bg-yellow-400'],
        'mythic'  => ['bb'=>'bg-rose-500/20',   'bt'=>'text-rose-400',   'bbd'=>'border-rose-500/30',   'ic'=>'text-rose-400',   'cb'=>'border-rose-400/20',   'btn'=>'bg-rose-500 hover:bg-rose-400'],
    ];
    return $styles[$t] ?? ['bb'=>'bg-gray-500/20','bt'=>'text-gray-400','bbd'=>'border-gray-500/30','ic'=>'text-gray-400','cb'=>'border-white/10','btn'=>'bg-gray-500 hover:bg-gray-400'];
}
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nos Offres - OrinHeberge</title>
    <link rel="icon" type="image/png" href="/favicon.ico">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <style>
        body { background: #0b0f19; scroll-behavior: smooth; }
        .glass { background: rgba(255, 255, 255, .04); backdrop-filter: blur(14px); border: 1px solid rgba(255, 255, 255, .08); }
        .gradient-text { background: linear-gradient(90deg, #38bdf8, #818cf8); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
        .card-hover { transition: transform .3s, box-shadow .3s; }
        .card-hover:hover { transform: translateY(-6px); box-shadow: 0 20px 40px rgba(0, 0, 0, .3); }
        .tab-btn { padding: .55rem 1.25rem; border-radius: 9999px; font-size: .82rem; font-weight: 600; transition: all .2s; border: 1px solid rgba(255, 255, 255, .1); background: rgba(255, 255, 255, .04); color: #9ca3af; cursor: pointer; white-space: nowrap; display: inline-flex; align-items: center; gap: .4rem; }
        .tab-btn:hover { background: rgba(255, 255, 255, .08); color: #e5e7eb; }
        .tab-btn.active { background: rgba(56, 189, 248, .15); border-color: rgba(56, 189, 248, .4); color: #38bdf8; box-shadow: 0 0 15px rgba(56, 189, 248, .1); }
        
        /* Styles pour la vue filtrée */
        #cat-view { display: none; }
        .cat-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1.5rem; }
    </style>

    <script>
    const categoryLabels = <?php echo json_encode(array_map(fn($cat) => t($cat['name_key']), $dynamic_categories), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

    function filterCategory(catId) {
        // 1. Gestion des onglets actifs
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        const activeTab = document.getElementById('tab-' + catId);
        if (activeTab) activeTab.classList.add('active');

        const catView = document.getElementById('cat-view');
        const catTitle = document.getElementById('cat-view-title');
        const catGrid = document.getElementById('cat-view-grid');
        const allSections = document.getElementById('all-sections');

        // 2. Logique d'affichage
        if (catId === 'all') { 
            catView.style.display = 'none'; 
            allSections.style.display = 'block'; 
            window.scrollTo({ top: document.querySelector('header').offsetHeight, behavior: 'smooth' });
            return; 
        }
        
        // Si on filtre, on affiche la vue dédiée
        allSections.style.display = 'none'; 
        catView.style.display = 'block';
        
        // Mise à jour du titre
        catTitle.textContent = categoryLabels[catId] || catId.toUpperCase();
        
        // 3. Collecte des cartes correspondantes
        // On cherche TOUTES les cartes qui ont data-category="xyz" peu importe où elles sont
        const allCards = document.querySelectorAll('.offer-card[data-category="' + catId + '"]');
        
        catGrid.innerHTML = '';
        
        if (allCards.length === 0) {
            catGrid.innerHTML = '<div class="col-span-full py-12 text-center text-gray-500 text-lg bg-slate-800/50 rounded-xl border border-slate-700"><i class="fas fa-search mb-4 text-3xl block opacity-50"></i>Aucune offre disponible pour le moment dans cette catégorie.</div>';
        } else {
            allCards.forEach(card => { 
                // Clone la carte
                const clone = card.cloneNode(true); 
                // Force l'affichage flex car le clone peut perdre certains styles calculés
                clone.style.display = 'flex'; 
                // Ajoute à la grille
                catGrid.appendChild(clone); 
            });
        }
        
        // Scroll vers le haut de la vue résultats
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function scrollToMacro(key) {
        const section = document.getElementById('macro-' + key);
        if (section) {
            // Reset filter to 'all' first so the section is visible
            filterCategory('all');
            setTimeout(() => {
                section.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }, 100);
        }
    }

    window.addEventListener('DOMContentLoaded', () => filterCategory('all'));
    </script>
</head>
<body class="text-gray-200 font-sans min-h-screen flex flex-col justify-between antialiased">

<?php include $_SERVER['DOCUMENT_ROOT'] . '/inc/navbar.php'; ?>

<main class="flex-grow">

    <!-- HERO -->
    <header class="text-center py-16 px-6 relative overflow-hidden">
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[500px] h-[500px] bg-sky-500/10 rounded-full blur-[120px] pointer-events-none"></div>
        <div class="relative z-10">
            <div class="inline-flex items-center gap-2 bg-sky-500/10 text-sky-400 border border-sky-500/20 px-4 py-1.5 rounded-full text-xs font-semibold mb-5">
                <i class="fas fa-tags"></i> <?php echo t('offers.badge', 'Nos Solutions'); ?>
            </div>
            <h1 class="text-5xl md:text-7xl font-black tracking-tight leading-none gradient-text mb-4">
                <?php echo t('offers.title'); ?>
            </h1>
            <p class="text-gray-400 max-w-xl mx-auto text-lg">
                <?php echo t('offers.subtitle'); ?>
            </p>
        </div>
    </header>

    <!-- NAVIGATION MACRO-CATÉGORIES -->
    <section class="max-w-7xl mx-auto px-6 pb-8">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <?php foreach ($main_categories as $key => $category): 
                // Vérifie s'il y a un lien spécifique (ex: /gaming/)
                $targetLink = $category['link'] ?? null;
            ?>
            
            <?php if ($targetLink): ?>
                <!-- Version Lien (pour Gaming) -->
                <a href="<?php echo htmlspecialchars($targetLink); ?>" class="group block bg-white/5 border border-white/10 rounded-2xl p-6 hover:bg-white/10 transition-all duration-300 hover:-translate-y-2 hover:shadow-2xl hover:shadow-blue-900/20">
                    <div class="w-14 h-14 rounded-xl bg-gradient-to-r <?php echo $category['color']; ?> flex items-center justify-center mb-4 shadow-lg group-hover:scale-110 transition-transform">
                        <i class="<?php echo $category['icon']; ?> text-2xl text-white"></i>
                    </div>
                    <h3 class="text-xl font-bold mb-2 text-white"><?php echo t($category['title_key']); ?></h3>
                    <p class="text-slate-400 text-sm mb-4 line-clamp-2"><?php echo t($category['description_key']); ?></p>
                    <div class="inline-flex items-center gap-2 text-sm font-semibold text-blue-400 group-hover:text-blue-300">
                        <?php echo t('categories.explore', 'Voir les offres'); ?>
                        <i class="fas fa-arrow-right transform group-hover:translate-x-1 transition-transform"></i>
                    </div>
                </a>
            <?php else: ?>
                <!-- Version Scroll (pour les autres) -->
                <div class="group bg-white/5 border border-white/10 rounded-2xl p-6 hover:bg-white/10 cursor-pointer transition-all duration-300 hover:-translate-y-2 hover:shadow-2xl hover:shadow-blue-900/20"
                     onclick="scrollToMacro('<?php echo $key; ?>')">
                    <div class="w-14 h-14 rounded-xl bg-gradient-to-r <?php echo $category['color']; ?> flex items-center justify-center mb-4 shadow-lg group-hover:scale-110 transition-transform">
                        <i class="<?php echo $category['icon']; ?> text-2xl text-white"></i>
                    </div>
                    <h3 class="text-xl font-bold mb-2 text-white"><?php echo t($category['title_key']); ?></h3>
                    <p class="text-slate-400 text-sm mb-4 line-clamp-2"><?php echo t($category['description_key']); ?></p>
                    <div class="inline-flex items-center gap-2 text-sm font-semibold text-blue-400 group-hover:text-blue-300">
                        <?php echo t('categories.explore', 'Voir les offres'); ?>
                        <i class="fas fa-arrow-right transform group-hover:translate-x-1 transition-transform"></i>
                    </div>
                </div>
            <?php endif; ?>

            <?php endforeach; ?>
        </div>
    </section>

    <!-- FILTRES RAPIDES -->
    <section class="sticky top-0 z-40 bg-[#0b0f19]/90 backdrop-blur-md border-b border-white/5 py-4 mb-8">
        <div class="max-w-7xl mx-auto px-6">
            <div class="flex flex-wrap justify-center gap-2.5">
                <button onclick="filterCategory('all')" id="tab-all" class="tab-btn active">
                    <i class="fas fa-th-large text-xs"></i> <?php echo t('offers.tab.all', 'Tout voir'); ?>
                </button>
                <?php foreach ($dynamic_categories as $slug => $ci): ?>
                <button onclick="filterCategory('<?php echo htmlspecialchars($slug); ?>')"
                        id="tab-<?php echo htmlspecialchars($slug); ?>" class="tab-btn">
                    <i class="<?php echo htmlspecialchars($ci['icon']); ?> text-xs"></i>
                    <?php echo t($ci['name_key']); ?>
                    <?php if (!empty($ci['product_count'])): ?>
                        <span class="text-[10px] bg-white/10 px-1.5 py-0.5 rounded-full ml-1">
                            <?php echo $ci['product_count']; ?>
                        </span>
                    <?php endif; ?>
                </button>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- VUE FILTRÉE (Cachée par défaut) -->
    <section id="cat-view" class="py-12 px-6 min-h-[50vh]">
        <div class="text-center mb-12">
            <h2 class="text-4xl md:text-5xl font-black uppercase tracking-wider mb-3 gradient-text" id="cat-view-title"></h2>
            <div class="h-1 w-20 bg-sky-500 mx-auto rounded-full"></div>
        </div>
        <div class="max-w-7xl mx-auto cat-grid" id="cat-view-grid">
            <!-- Les cartes seront injectées ici par JS -->
        </div>
    </section>

    <!-- LISTE COMPLÈTE DES OFFRES (Organisée par Macro) -->
    <div id="all-sections">
        <?php foreach ($main_categories as $macro_key => $macro): ?>
            <?php
                // Vérifie s'il y a des offres pour les sous-catégories de cette macro
                $sub_slugs = $macro['subcategories'];
                $has_sub_offers = false;
                foreach ($sub_slugs as $s) {
                    foreach ($sections as $t) {
                        foreach ($t['offers'] as $o) {
                            if ($o['category'] === $s) { $has_sub_offers = true; break 3; }
                        }
                    }
                }
                if (!$has_sub_offers) continue;
            ?>
            
            <div id="macro-<?php echo $macro_key; ?>" class="mb-16 scroll-mt-24">
                <!-- En-tête de Section Macro -->
                <div class="max-w-7xl mx-auto px-6 pt-8 pb-6 flex items-center gap-4 border-b border-white/5">
                    <div class="w-12 h-12 rounded-lg bg-gradient-to-r <?php echo $macro['color']; ?> flex items-center justify-center shadow-lg">
                        <i class="<?php echo $macro['icon']; ?> text-xl text-white"></i>
                    </div>
                    <div>
                        <h2 class="text-3xl font-bold text-white"><?php echo t($macro['title_key']); ?></h2>
                        <p class="text-slate-400 text-sm"><?php echo t($macro['subtitle_key'] ?? ''); ?></p>
                    </div>
                </div>

                <!-- Boucle sur les Tiers (Free -> Mythic) -->
                <?php foreach ($sections as $tier_key => $tier):
                    // Filtre les offres du tier actuel pour ne garder que celles de la macro actuelle
                    $tier_offers = array_filter($tier['offers'], function($o) use ($sub_slugs) {
                        return in_array($o['category'], $sub_slugs, true);
                    });
                    
                    if (empty($tier_offers)) continue;
                    $s = tierStyle($tier_key);
                ?>
                <section class="py-8 px-6 <?php echo $tier['bg']; ?>">
                    <div class="max-w-7xl mx-auto mb-6">
                        <h3 class="text-xl font-bold <?php echo $s['bt']; ?> uppercase tracking-wide flex items-center gap-2">
                            <i class="fas fa-layer-group opacity-70"></i> <?php echo t($tier['title_key']); ?>
                        </h3>
                    </div>

                    <div class="max-w-7xl mx-auto grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
                        <?php foreach ($tier_offers as $offer):
                            $is_free = $offer['free'];
                            $btn_text = $is_logged_in
                                ? ($is_free ? t('btn.deploy', 'Déployer') : t('btn.buy', 'Commander'))
                                : t('btn.login_to_buy', 'Se connecter');
                            $price_num = $is_free ? 0 : $offer['price_value'];
                        ?>
                        <!-- CARTE OFFRE -->
                        <div data-category="<?php echo htmlspecialchars($offer['category']); ?>"
                             data-price="<?php echo $price_num; ?>"
                             class="offer-card glass rounded-2xl border <?php echo $s['cb']; ?> flex flex-col card-hover overflow-hidden relative group">
                            
                            <!-- Image Header -->
                            <div class="h-32 w-full bg-cover bg-center relative"
                                 style="background-image:url('<?php echo htmlspecialchars($offer['image_url']); ?>')">
                                <div class="absolute inset-0 bg-gradient-to-t from-[#0b0f19] via-transparent to-transparent"></div>
                                <div class="absolute top-3 left-3 right-3 flex justify-between items-center">
                                    <span class="<?php echo $s['bb'].' '.$s['bt'].' '.$s['bbd']; ?> px-2.5 py-0.5 rounded-full text-[10px] font-bold border uppercase tracking-wide backdrop-blur-sm">
                                        <?php echo t($tier['label_key']); ?>
                                    </span>
                                    <i class="<?php echo htmlspecialchars($offer['icon']).' '.$s['ic']; ?> text-xl drop-shadow-lg"></i>
                                </div>
                            </div>

                            <!-- Contenu -->
                            <div class="p-5 flex flex-col flex-grow">
                                <h4 class="text-lg font-bold text-white mb-1 group-hover:text-sky-400 transition-colors">
                                    <?php echo htmlspecialchars($offer['name']); ?>
                                </h4>
                                <p class="text-gray-400 text-xs flex-grow mb-4 leading-relaxed h-10 overflow-hidden">
                                    <?php echo htmlspecialchars($offer['desc']); ?>
                                </p>

                                <div class="flex items-baseline mb-4">
                                    <span class="text-2xl font-black text-white"><?php echo $offer['price']; ?></span>
                                    <span class="text-gray-500 text-xs ml-1"><?php echo t($offer['period_key']); ?></span>
                                </div>

                                <ul class="space-y-2 text-xs text-gray-300 border-t border-white/5 pt-3 mb-4">
                                    <?php foreach ($offer['features'] as $f): ?>
                                    <li class="flex items-center gap-2">
                                        <i class="<?php echo $f['icon'].' '.$s['ic']; ?> w-4 text-center"></i>
                                        <span><?php echo htmlspecialchars($f['text']); ?></span>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>

                                <!-- Bouton Action -->
                                <?php if ($is_logged_in): ?>
                                <form method="post" action="/shop/cart/">
                                    <input type="hidden" name="action" value="add_item">
                                    <input type="hidden" name="slug"   value="<?php echo htmlspecialchars($offer['slug']); ?>">
                                    <input type="hidden" name="name"   value="<?php echo htmlspecialchars($offer['name']); ?>">
                                    <input type="hidden" name="price"  value="<?php echo $offer['price_value']; ?>">
                                    <button type="submit"
                                            class="w-full <?php echo $s['btn']; ?> text-slate-950 font-bold py-2.5 rounded-xl text-sm transition hover:brightness-110">
                                        <?php echo $btn_text; ?>
                                    </button>
                                </form>
                                <?php else: ?>
                                <a href="/login/"
                                   class="w-full <?php echo $s['btn']; ?> text-slate-950 font-bold py-2.5 rounded-xl text-sm text-center block transition hover:brightness-110">
                                    <?php echo $btn_text; ?>
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </section>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- SERVICES COMPLÉMENTAIRES -->
    <section class="max-w-7xl mx-auto px-6 py-16">
        <div class="bg-slate-800/30 border border-slate-700 rounded-2xl p-8">
            <div class="text-center mb-8">
                <h2 class="text-2xl font-bold mb-2 text-white"><?php echo t('categories.additional_services', 'Services complémentaires'); ?></h2>
                <p class="text-slate-400 text-sm">Optimisez votre infrastructure avec nos services additionnels.</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="text-center p-4 hover:bg-white/5 rounded-xl transition">
                    <div class="w-12 h-12 rounded-full bg-gradient-to-r from-yellow-500 to-orange-500 flex items-center justify-center mx-auto mb-3">
                        <i class="fas fa-database text-xl text-white"></i>
                    </div>
                    <h3 class="font-bold text-white mb-1">Bases de données</h3>
                    <p class="text-xs text-slate-400">MySQL, PostgreSQL & MongoDB managés</p>
                </div>
                <div class="text-center p-4 hover:bg-white/5 rounded-xl transition">
                    <div class="w-12 h-12 rounded-full bg-gradient-to-r from-purple-500 to-pink-500 flex items-center justify-center mx-auto mb-3">
                        <i class="fas fa-cloud text-xl text-white"></i>
                    </div>
                    <h3 class="font-bold text-white mb-1">Stockage Objet</h3>
                    <p class="text-xs text-slate-400">S3 Compatible pour vos backups</p>
                </div>
                <div class="text-center p-4 hover:bg-white/5 rounded-xl transition">
                    <div class="w-12 h-12 rounded-full bg-gradient-to-r from-blue-500 to-cyan-500 flex items-center justify-center mx-auto mb-3">
                        <i class="fas fa-shield-alt text-xl text-white"></i>
                    </div>
                    <h3 class="font-bold text-white mb-1">Protection DDoS</h3>
                    <p class="text-xs text-slate-400">Inclus sur toutes les offres</p>
                </div>
            </div>
        </div>
    </section>

</main>

<!-- DISCORD FLOATING BTN -->
<div class="fixed bottom-6 right-6 z-50">
    <a href="/discord/" target="_blank"
       class="bg-[#5865F2] hover:bg-[#4752C4] text-white px-5 py-3.5 rounded-full font-bold flex items-center gap-2 shadow-2xl hover:scale-105 transform duration-200 transition">
        <i class="fab fa-discord text-xl"></i>
        <span class="hidden sm:inline text-sm">Support Discord</span>
    </a>
</div>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/inc/footer.php'; ?>
<script src="https://<?php echo $_SERVER['HTTP_HOST']; ?>/inc/navbar.js?v=<?php echo @filemtime($_SERVER['DOCUMENT_ROOT'] . '/inc/navbar.js') ?: time(); ?>"></script>
</body>
</html>
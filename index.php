<?php
session_start();
require_once __DIR__ . '/inc/lang.php';

$db_status   = false;
$is_logged_in = isset($_SESSION['user_id']);

try {
    $pdo = new PDO('mysql:host=localhost;dbname=s43_orinheberge;charset=utf8mb4','root','1504',[PDO::ATTR_TIMEOUT=>3,PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $db_status = true;
    if ($is_logged_in) {
        $stmt = $pdo->prepare("SELECT pseudo, firstname, avatar FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$_SESSION['user_id']]);
        $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user_data) {
            $_SESSION['username'] = !empty($user_data['pseudo']) ? $user_data['pseudo'] : $user_data['firstname'];
            $_SESSION['avatar']   = $user_data['avatar'];
        }
    }
} catch (PDOException $e) { $db_status = false; }

$sections = [
    'free'    => [
        'title_key'    => 'tier.free.title',    
        'subtitle_key' => 'tier.free.subtitle',    
        'label_key'    => 'tier.free.label',    
        'accent'       => 'bg-green-500',  
        'bg'           => 'bg-white/[0.01] border-y border-white/5',
        'offers'       => []
    ],
    'basic'   => [
        'title_key'    => 'tier.basic.title',   
        'subtitle_key' => 'tier.basic.subtitle',   
        'label_key'    => 'tier.basic.label',   
        'accent'       => 'bg-blue-500',   
        'bg'           => 'bg-black/10',
        'offers'       => []
    ],
    'medium'  => [
        'title_key'    => 'tier.medium.title',  
        'subtitle_key' => 'tier.medium.subtitle',  
        'label_key'    => 'tier.medium.label',  
        'accent'       => 'bg-purple-500', 
        'bg'           => 'bg-white/[0.02] border-y border-white/5',
        'offers'       => []
    ],
    'premium' => [
        'title_key'    => 'tier.premium.title', 
        'subtitle_key' => 'tier.premium.subtitle', 
        'label_key'    => 'tier.premium.label', 
        'accent'       => 'bg-yellow-500', 
        'bg'           => 'bg-black/20',
        'offers'       => []
    ],
    'mythic' => [
        'title_key'    => 'tier.mythic.title', 
        'subtitle_key' => 'tier.mythic.subtitle', 
        'label_key'    => 'tier.mythic.label', 
        'accent'       => 'bg-red-500', 
        'bg'           => 'bg-black/30',
        'offers'       => []
    ],
];

// Conteneurs pour les données dynamiques des catégories
$dynamic_categories = [];

// ── 2. REMPLISSAGE DYNAMIQUE DEPUIS LA BASE DE DONNÉES ──
if ($db_status) {
    try {
        // A. Récupérer uniquement les catégories ACTIVES configurées par l'admin
        $cat_stmt = $pdo->query("SELECT category_slug, name_key, icon, image_url FROM categories_products WHERE is_active = 1 GROUP BY category_slug ORDER BY sort_order ASC");
        while ($c_row = $cat_stmt->fetch()) {
            $dynamic_categories[$c_row['category_slug']] = [
                'name_key'  => $c_row['name_key'],
                'icon'      => $c_row['icon'],
                'image_url' => $c_row['image_url']
            ];
        }
        $stmt_lang = $pdo->query("SELECT translation_key, fr, en FROM lang_boutique ORDER BY translation_key");

        // B. Récupérer uniquement les produits reliés à des catégories ACTIVES et dont le produit lui-même est actif
        $stmt = $pdo->query("
            SELECT p.*, cp.category_slug, cp.name_key AS cat_name_key, cp.icon AS cat_icon, cp.image_url AS cat_image
            FROM categories_products cp
            LEFT JOIN products p ON p.id = cp.product_id
            WHERE cp.is_active = 1 AND (p.is_active = 1 OR p.id IS NULL)
            ORDER BY p.sort_order ASC, p.id ASC
        ");
        $all_rows = $stmt->fetchAll();

        foreach ($all_rows as $product) {
            // Si la catégorie n'a pas encore de produit associé, on passe à la ligne suivante
            if (empty($product['id'])) {
                continue;
            }

            $slug = $product['slug'];
            $category = strtolower($product['category_slug']); 

            // Détermination du bon palier d'affichage (tier) d'après le mot-clé présent dans le slug
            $tier_found = 'premium'; // Par défaut
            if (strpos($slug, 'free') !== false) {
                $tier_found = 'free';
            } elseif (strpos($slug, 'basic') !== false) {
                $tier_found = 'basic';
            } elseif (strpos($slug, 'medium') !== false) {
                $tier_found = 'medium';
            }

            // Reconstruction des clés de traduction pour les textes descriptifs des offres
            $short_cat = ($category === 'minecraft') ? 'mc' : (($category === 'python') ? 'py' : (($category === 'nodejs') ? 'node' : $category));
            $name_key = "offer.{$short_cat}_{$tier_found}.name";
            $desc_key = "offer.{$short_cat}_{$tier_found}.desc";

            // Formatage propre des specs RAM et NVMe
            $ram_text = ($product['ram'] >= 1024) ? number_format($product['ram'] / 1024, 0) . ' GB' : $product['ram'] . ' MB';
            $disk_text = ($product['disk'] >= 1024) ? number_format($product['disk'] / 1024, 0) . ' GB' : $product['disk'] . ' MB';

            // Ajout de l'offre BDD formatée dans le tableau $sections
            $sections[$tier_found]['offers'][] = [
                'category'   => $category,
                'slug'       => $slug,
                'name_key'   => $name_key,
                'desc_key'   => $desc_key,
                'price'      => ($product['type'] === 'free') ? '0€' : number_format($product['price'], 2, ',', '') . '€',
                'price_value'=> ($product['type'] === 'free') ? 0.0 : (float)$product['price'],
                'period_key' => ($product['type'] === 'free') ? 'offers.period.free' : 'offers.period.month',
                'plan'       => $slug,
                'free'       => ($product['type'] === 'free'),
                'icon'       => $product['cat_icon'] ?: 'fas fa-server',
                'image_url'  => $product['cat_image'] ?: 'https://www.4netplayers.com/images/minecraft/blog/teaser-image.jpg',
                'features'   => [
                    ['icon' => 'fas fa-memory',     'text' => $ram_text . ' RAM'],
                    ['icon' => 'fas fa-hard-drive', 'text' => $disk_text . ' SSD NVMe'],
                    ['icon' => 'fas fa-microchip',  'text' => $product['cpu'] . '% CPU'],
                    ['icon' => 'fas fa-database',   'text' => $product['databases'] . ' Database(s)']
                ]
            ];
        }
    } catch (PDOException $e) {
        // Fallback
    }
}

function getCardStyle($tier_key) {
    if ($tier_key === 'free') {
        return ['label' => 'Offre Gratuite', 'badge_bg'=>'bg-green-500/20', 'badge_text'=>'text-green-400', 'badge_border'=>'border-green-500/30', 'icon_color'=>'text-green-400', 'card_border'=>'border-white/10', 'btn'=>'bg-green-500 hover:bg-green-400'];
    } elseif ($tier_key === 'basic') {
        return ['label' => 'Offre Basic', 'badge_bg'=>'bg-blue-500/20', 'badge_text'=>'text-blue-400', 'badge_border'=>'border-blue-500/30', 'icon_color'=>'text-blue-400', 'card_border'=>'border-blue-400/20', 'btn'=>'bg-blue-500 hover:bg-blue-400'];
    } elseif ($tier_key === 'medium') {
        return ['label' => 'Offre Medium', 'badge_bg'=>'bg-purple-500/20', 'badge_text'=>'text-purple-400', 'badge_border'=>'border-purple-500/30', 'icon_color'=>'text-purple-400', 'card_border'=>'border-purple-400/20', 'btn'=>'bg-purple-500 hover:bg-purple-400'];
    } else {
        return ['label' => 'Offre Premium', 'badge_bg'=>'bg-yellow-500/20', 'badge_text'=>'text-yellow-400', 'badge_border'=>'border-yellow-500/30', 'icon_color'=>'text-yellow-400', 'card_border'=>'border-yellow-400/20', 'btn'=>'bg-yellow-500 hover:bg-yellow-400'];
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OrinHeberge | Hébergeur</title>
    <link rel="icon" type="image/png" href="/favicon.ico">
   
    <!-- Balises de base -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OrinHeberge - Hébergeur VPS, Minecraft, PHP et Node.js | Gratuit & Premium</title>
    <meta name="description" content="OrinHeberge - Hébergement VPS, Minecraft, PHP et Node.js ultra rapide, gratuit et premium. Des serveurs rapides, sécurisés et performants pour tous vos projets.">
    <meta name="keywords" content="hébergement VPS, serveur Minecraft, hébergement PHP, Node.js, VPS gratuit, serveur dédié, hosting, cloud, hébergeur français">
    <meta name="author" content="OrinHeberge">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="https://heberge.orinstone.deepstone.fr/">

    <!-- Open Graph / Facebook -->
    <meta property="og:locale" content="fr_FR">
    <meta property="og:type" content="website">
    <meta property="og:title" content="OrinHeberge - Hébergeur VPS, Minecraft, PHP et Node.js">
    <meta property="og:description" content="Hébergement VPS, Minecraft, PHP et Node.js ultra rapide, gratuit et premium. Serveurs rapides, sécurisés et performants.">
    <meta property="og:url" content="https://heberge.orinstone.deepstone.fr/">
    <meta property="og:site_name" content="OrinHeberge">
    <meta property="og:image" content="https://heberge.orinstone.deepstone.fr/favicon.png">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="OrinHeberge - Hébergement VPS, Minecraft, PHP et Node.js">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:site" content="@OrinHeberge">
    <meta name="twitter:creator" content="@OrinHeberge">
    <meta name="twitter:title" content="OrinHeberge - Hébergeur VPS, Minecraft, PHP et Node.js">
    <meta name="twitter:description" content="Hébergement VPS, Minecraft, PHP et Node.js ultra rapide, gratuit et premium. Serveurs rapides, sécurisés et performants.">
    <meta name="twitter:image" content="https://heberge.orinstone.deepstone.fr/favicon.png">
    <meta name="twitter:image:alt" content="OrinHeberge - Hébergement VPS, Minecraft, PHP et Node.js">

    <!-- Autres balises SEO -->
    <meta name="theme-color" content="#6366f1">
    <meta name="msapplication-TileColor" content="#6366f1">
    <link rel="icon" type="image/png" href="https://heberge.orinstone.deepstone.fr/favicon.png">
    <link rel="apple-touch-icon" href="https://heberge.orinstone.deepstone.fr/favicon.png">

    <!-- Schema.org JSON-LD (SEO avancé) -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "WebSite",
      "name": "OrinHeberge",
      "url": "https://heberge.orinstone.deepstone.fr/",
      "description": "Hébergement VPS, Minecraft, PHP et Node.js ultra rapide, gratuit et premium.",
      "potentialAction": {
        "@type": "SearchAction",
        "target": "https://heberge.orinstone.deepstone.fr/search?q={search_term_string}",
        "query-input": "required name=search_term_string"
      }
    }
    </script>

    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="manifest" href="/manifest.json">
    <style>
        *{box-sizing:border-box;}
        body{background:#060911;scroll-behavior:smooth;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',system-ui,sans-serif;}
        .glass{background:rgba(255,255,255,0.03);backdrop-filter:blur(20px);border:1px solid rgba(255,255,255,0.06);}
        .glass-heavy{background:rgba(255,255,255,0.05);backdrop-filter:blur(20px);border:1px solid rgba(255,255,255,0.08);}
        .gradient-text{background:linear-gradient(135deg,#38bdf8 0%,#a78bfa 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;}
        .gradient-bg{background:linear-gradient(135deg,#38bdf8,#a78bfa);}
        .card-hover{transition:transform .3s cubic-bezier(.4,0,.2,1),box-shadow .3s,border-color .3s;}
        .card-hover:hover{transform:translateY(-6px);box-shadow:0 32px 64px rgba(0,0,0,.4);}
        .tab-btn{padding:.5rem 1.25rem;border-radius:9999px;font-size:.78rem;font-weight:700;transition:all .2s;border:1px solid rgba(255,255,255,.07);background:rgba(255,255,255,.025);color:#6b7280;cursor:pointer;white-space:nowrap;letter-spacing:.02em;}
        .tab-btn:hover{background:rgba(255,255,255,.06);color:#d1d5db;}
        .tab-btn.active{background:rgba(56,189,248,.1);border-color:rgba(56,189,248,.3);color:#38bdf8;}
        #mobileMenu{display:none;}#mobileMenu.active{display:block;}
        #cat-view { display: none; }
        #cat-view .cat-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1.5rem; }
        .hero-glow {position:absolute;border-radius:50%;filter:blur(140px);pointer-events:none;animation:pulse-orb 8s ease-in-out infinite;z-index: 1;}
        @keyframes pulse-orb{0%,100%{opacity:.4;transform:translate(-50%, -50%) scale(1);}50%{opacity:.7;transform:translate(-50%, -50%) scale(1.1);}}
        
        .offer-badge{position:absolute;top:.875rem;right:.875rem;z-index:10;padding:.2rem .7rem;border-radius:9999px;font-size:.65rem;font-weight:800;letter-spacing:.06em;text-transform:uppercase;}
    </style>
    <script>
        if('serviceWorker' in navigator){window.addEventListener('load',()=>{navigator.serviceWorker.register('/sw.js').catch(()=>{});});}
        function toggleMenu(){document.getElementById('mobileMenu').classList.toggle('active');}
    </script>
</head>
<body class="text-gray-200 font-sans min-h-screen flex flex-col antialiased">

<?php $active_nav = 'home'; include __DIR__ . '/inc/navbar.php'; ?>

<main class="flex-grow">

    <section class="relative text-center py-28 md:py-40 px-6 overflow-hidden flex items-center justify-center">
        <div class="hero-glow w-[500px] h-[500px] bg-sky-500/10 top-1/2 left-1/2"></div>
        <div class="hero-glow w-[300px] h-[300px] bg-purple-500/10 top-1/3 left-1/3"></div>

        <div class="relative z-10 max-w-4xl mx-auto">
            <div class="inline-flex items-center gap-2 bg-sky-500/10 text-sky-400 border border-sky-500/20 px-4 py-1.5 rounded-full text-xs font-semibold mb-8 tracking-wide">
                <span class="h-2 w-2 rounded-full <?php echo $db_status ? 'bg-green-400 animate-pulse' : 'bg-red-400'; ?>"></span>
                <?php echo $db_status ? 'Tous les systèmes opérationnels' : 'Connexion BDD indisponible'; ?>
            </div>

            <h1 class="text-6xl md:text-8xl font-black tracking-tight leading-[1.1] mb-6">
                <span class="gradient-text">Orin</span>Heberge
            </h1>
            <p class="text-gray-400 text-lg md:text-xl max-w-2xl mx-auto leading-relaxed mb-10">
                Hébergement nouvelle génération — Minecraft, PHP, Node.js, Python.<br>
                <span class="text-gray-500 text-base">Gratuit pour démarrer. Premium pour aller plus loin.</span>
            </p>

            <div class="flex flex-col sm:flex-row justify-center items-center gap-4">
                <a href="#offres" class="bg-sky-600 hover:bg-sky-500 text-white px-8 py-4 rounded-2xl font-bold transition shadow-xl shadow-sky-900/30 text-sm flex items-center gap-2">
                    <i class="fas fa-rocket"></i> Voir les offres
                </a>
                <a href="<?php echo $is_logged_in ? '/client/servers/' : '/register/'; ?>" class="glass hover:bg-white/[0.07] px-8 py-4 rounded-2xl font-bold transition text-sm flex items-center gap-2">
                    <?php if ($is_logged_in): ?>
                        <i class="fas fa-server text-sky-400"></i> Mon espace client
                    <?php else: ?>
                        <i class="fas fa-user-plus text-sky-400"></i> Créer un compte gratuit
                    <?php endif; ?>
                </a>
            </div>
        </div>
    </section>

    <section class="py-8 px-6 border-y border-white/[0.04]">
        <div class="max-w-4xl mx-auto grid grid-cols-2 md:grid-cols-4 gap-6 text-center">
            <div><p class="text-3xl font-black gradient-text">100%</p><p class="text-gray-500 text-xs mt-1">SSD NVMe</p></div>
            <div><p class="text-3xl font-black gradient-text">0€</p><p class="text-gray-500 text-xs mt-1">Pour commencer</p></div>
            <div><p class="text-3xl font-black gradient-text">24/7</p><p class="text-gray-500 text-xs mt-1">Support Discord</p></div>
            <div><p class="text-3xl font-black gradient-text">DDoS</p><p class="text-gray-500 text-xs mt-1">Protection incluse</p></div>
        </div>
    </section>

    <section id="offres" class="py-20 px-6 max-w-7xl mx-auto scroll-mt-10">
        <script>
const categoryLabels = <?php echo json_encode(array_map(fn($cat) => t($cat['name_key']), $dynamic_categories), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

function filterCategory(catId) {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    if(document.getElementById('tab-' + catId)) {
        document.getElementById('tab-' + catId).classList.add('active');
    }
    
    const catView = document.getElementById('cat-view');
    const catTitle = document.getElementById('cat-view-title');
    const catGrid = document.getElementById('cat-view-grid');
    const allSections = document.getElementById('all-sections');
    
    if (catId === 'all') { 
        catView.style.display = 'none'; 
        allSections.style.display = 'block'; 
        return; 
    }
    
    allSections.style.display = 'none'; 
    catView.style.display = 'block';
    
    catTitle.textContent = categoryLabels[catId] || catId.toUpperCase();
    
    const cards = Array.from(document.querySelectorAll('#all-sections .offer-card[data-category="' + catId + '"]'));
    
    catGrid.innerHTML = '';
    if (cards.length === 0) {
        catGrid.innerHTML = '<div class="col-span-full py-12 text-center text-gray-500 text-sm">Aucune offre disponible pour le moment dans cette catégorie.</div>';
    } else {
        cards.forEach(card => { 
            const clone = card.cloneNode(true); 
            clone.style.display = 'flex'; 
            catGrid.appendChild(clone); 
        });
    }
}
window.addEventListener('DOMContentLoaded', () => filterCategory('all'));
</script>
<header class="text-center py-16 px-6 relative overflow-hidden">
        <h1 class="text-5xl md:text-7xl font-black tracking-tight leading-none gradient-text"><?php echo t('offers.title'); ?></h1>
        
        <div class="max-w-4xl mx-auto flex flex-wrap justify-center gap-3 px-4 mt-10">
            <button onclick="filterCategory('all')" id="tab-all" class="tab-btn active"><?php echo t('offers.tab.all'); ?></button>
            <?php foreach ($dynamic_categories as $slug => $cat_info): ?>
                <button onclick="filterCategory('<?= htmlspecialchars($slug) ?>')" id="tab-<?= htmlspecialchars($slug) ?>" class="tab-btn">
                    <?php echo t($cat_info['name_key']); ?>
                </button>
            <?php endforeach; ?>
        </div>
    </header>

    <section id="cat-view" class="py-20 px-6">
        <div class="text-center mb-12">
            <h2 class="text-4xl md:text-5xl font-black uppercase tracking-wider mb-3 gradient-text" id="cat-view-title"></h2>
            <div class="h-1 w-20 bg-sky-500 mx-auto rounded-full"></div>
        </div>
        <div class="max-w-7xl mx-auto cat-grid" id="cat-view-grid"></div>
    </section>

    <div id="all-sections">
    <?php foreach ($sections as $tier_key => $tier_data): ?>
        <?php if (empty($tier_data['offers'])) continue; ?>
        <section class="offers-section py-20 px-6 <?php echo $tier_data['bg']; ?>">
            <div class="text-center mb-16">
                <h2 class="text-4xl md:text-5xl font-black uppercase tracking-wider mb-3"><?php echo t($tier_data['title_key']); ?></h2>
                <div class="h-1 w-20 <?php echo $tier_data['accent']; ?> mx-auto rounded-full"></div>
            </div>
            
            <div class="max-w-7xl mx-auto grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($tier_data['offers'] as $offer): ?>
            <?php
                $cat = $offer['category'];
                $style = getCardStyle($tier_key);

                $cart_route = '/shop/cart/';
                if ($offer['free']) {
                    $btn_text = $is_logged_in ? t('btn.deploy') : t('btn.login_to_buy');
                } else {
                    $btn_text = $is_logged_in ? t('btn.buy') : t('btn.login_to_buy');
                }
                $link = $is_logged_in ? $cart_route : '/login/';
            ?>
            <div data-category="<?php echo $cat; ?>" 
                 class="offer-card glass rounded-3xl border <?php echo $style['card_border']; ?> flex flex-col card-hover overflow-hidden relative">
                
                <div class="h-44 w-full bg-cover bg-center relative" style="background-image: url('<?php echo htmlspecialchars($offer['image_url']); ?>');">
                    <div class="absolute inset-0 bg-gradient-to-t from-[#070a13] to-transparent"></div>
                    <div class="absolute top-4 left-4 right-4 flex justify-between items-center">
                        <span class="<?php echo $style['badge_bg'].' '.$style['badge_text'].' '.$style['badge_border']; ?> px-3 py-1 rounded-full text-xs font-bold backdrop-blur-md border uppercase tracking-wide">
                            <?php echo t($tier_data['label_key']); ?>
                        </span>
                        <i class="<?php echo htmlspecialchars($offer['icon']).' '.$style['icon_color']; ?> text-2xl drop-shadow"></i>
                    </div>
                </div>
                
                <div class="p-6 flex flex-col flex-grow">
                    <h3 class="text-xl font-bold text-white"><?php echo t($offer['name_key']); ?></h3>
                    <p class="text-gray-400 mt-2 mb-6 text-sm flex-grow">
                        <?php echo t($offer['desc_key']); ?>
                    </p>
                    
                    <div class="flex items-baseline mb-6">
                        <span class="text-3xl font-black text-white"><?php echo $offer['price']; ?></span>
                        <span class="text-gray-500 text-xs ml-1"><?php echo t($offer['period_key']); ?></span>
                    </div>
                    
                    <ul class="space-y-3 text-gray-300 text-sm border-t border-white/5 pt-4">
                        <?php foreach ($offer['features'] as $feat): ?>
                        <li>
                            <i class="<?php echo $feat['icon'].' '.$style['icon_color']; ?> mr-2 w-4"></i>
                            <?php echo $feat['text']; ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    
                    <?php if ($is_logged_in): ?>
                        <form method="post" action="/shop/cart/" class="mt-6 w-full">
                            <input type="hidden" name="action" value="add_item">
                            <input type="hidden" name="slug" value="<?php echo htmlspecialchars($offer['slug']); ?>">
                            <input type="hidden" name="name" value="<?php echo htmlspecialchars(t($offer['name_key'])); ?>">
                            <input type="hidden" name="price" value="<?php echo htmlspecialchars((string)$offer['price_value']); ?>">
                            <input type="hidden" name="period" value="<?php echo htmlspecialchars(t($offer['period_key'])); ?>">
                            <button type="submit" class="w-full <?php echo $style['btn']; ?> text-slate-950 font-bold py-3 rounded-2xl transition text-sm text-center block">
                                <?php echo $btn_text; ?>
                            </button>
                        </form>
                    <?php else: ?>
                        <a href="<?php echo $link; ?>" class="mt-6 w-full <?php echo $style['btn']; ?> text-slate-950 font-bold py-3 rounded-2xl transition text-sm text-center block">
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

       
    </section>

    <section class="py-16 px-6 bg-white/[0.01] border-y border-white/[0.03]">
        <div class="max-w-7xl mx-auto grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
            <div>
                <span class="text-xs font-bold text-sky-400 tracking-widest uppercase mb-2 block">Performances Brutes</span>
                <h3 class="text-3xl md:text-4xl font-black mb-6 leading-tight">Une infrastructure optimisée pour le <span class="gradient-text">zéro-lenteur</span>.</h3>
                <p class="text-gray-400 text-sm leading-relaxed mb-6">
                    Nous n'hébergeons pas vos projets sur du matériel obsolète. Nos machines physiques exploitent la puissance des coeurs AMD Ryzen couplée à une architecture réseau ultra redondante pour garantir stabilité et vitesse de traitement.
                </p>
                <div class="grid grid-cols-2 gap-4">
                    <div class="p-4 rounded-xl border border-white/[0.03] bg-white/[0.01]">
                        <div class="text-white font-bold text-sm mb-1"><i class="fas fa-network-wired text-sky-400 mr-1.5"></i> Réseau 10 Gbps</div>
                        <p class="text-gray-500 text-xs">Uplink haut débit pour une latence minimale en Europe.</p>
                    </div>
                    <div class="p-4 rounded-xl border border-white/[0.03] bg-white/[0.01]">
                        <div class="text-white font-bold text-sm mb-1"><i class="fas fa-memory text-purple-400 mr-1.5"></i> RAM DDR5 ECC</div>
                        <p class="text-gray-500 text-xs">Correction d'erreurs intégrée pour éviter tout crash applicatif.</p>
                    </div>
                </div>
            </div>
            <div class="relative flex justify-center">
                <div class="absolute w-72 h-72 bg-indigo-500/5 filter blur-3xl rounded-full top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2"></div>
                <div class="glass p-8 rounded-2xl border border-white/[0.06] max-w-md w-full font-mono text-xs text-gray-400 space-y-3">
                    <div class="flex items-center justify-between border-b border-white/[0.05] pb-2">
                        <span class="text-gray-500">Node Status</span>
                        <span class="text-green-400 flex items-center gap-1"><span class="h-1.5 w-1.5 rounded-full bg-green-400 animate-ping"></span> ONLINE</span>
                    </div>
                    <p><span class="text-purple-400">~ $</span> screen -r orin-node-01</p>
                    <p class="text-gray-500">[OS]: Ubuntu 24.04 LTS (Noble Numbat)</p>
                    <p class="text-gray-500">[CPU]: AMD Ryzen 9 7950X @ 4.5 GHz</p>
                    <p class="text-gray-500">[Disk]: NVMe PCIe Gen4 x4 Read: 7000MB/s</p>
                    <p class="text-sky-400">>> Anti-DDoS Mitigation Layers ACTIVE (Game-Shield)</p>
                </div>
            </div>
        </div>
    </section>

    <section class="py-20 px-6 max-w-7xl mx-auto">
        <div class="text-center mb-12">
            <h2 class="text-3xl md:text-4xl font-black mb-3">L'<span class="gradient-text">Équipe</span> OrinHeberge</h2>
            <p class="text-gray-500 max-w-lg mx-auto text-sm">Les passionnés qui travaillent chaque jour pour maintenir vos serveurs en ligne.</p>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            <div class="glass p-6 rounded-2xl border border-white/[0.05] text-center flex flex-col items-center">
                <div class="relative mb-4">
                    <div class="w-20 h-20 bg-gradient-to-tr from-sky-400 to-purple-500 rounded-full p-0.5 shadow-xl">
                        <img src="/img/staff/Mathéo-Favier.jpg" alt="Avatar" class="w-full h-full object-cover rounded-full bg-[#060911]">
                    </div>
                    <span class="absolute bottom-0 right-1 h-3 w-3 rounded-full bg-green-400 border-2 border-[#060911]"></span>
                </div>
                <h4 class="font-bold text-white text-lg">Mathéo Favier</h4>
                <span class="text-xs font-semibold px-2.5 py-0.5 rounded-full bg-sky-500/10 text-sky-400 border border-sky-500/20 mt-1 mb-3">Fondateur & Dev SysAdmin</span>
                <p class="text-gray-500 text-xs leading-relaxed max-w-xs">Garant de l'architecture serveur et du développement global du panel.</p>
                <div class="flex gap-3 mt-4 text-gray-400 text-sm">
                    <a href="https://github.com/metal54400" class="hover:text-white transition"><i class="fab fa-github"></i></a>
                    <a href="https://portfolio.deepstone.fr" class="hover:text-sky-400 transition"><i class="fab fa-avatar"></i></a>
                </div>
            </div>

             <div class="glass p-6 rounded-2xl border border-white/[0.05] text-center flex flex-col items-center">
                <div class="relative mb-4">
                    <div class="w-20 h-20 bg-gradient-to-tr from-purple-500 to-pink-500 rounded-full p-0.5 shadow-xl">
                        <img src="/img/staff/WixyMc.png" alt="Avatar" class="w-full h-full object-cover rounded-full bg-[#060911]">
                    </div>
                    <span class="absolute bottom-0 right-1 h-3 w-3 rounded-full bg-green-400 border-2 border-[#060911]"></span>
                </div>
                <h4 class="font-bold text-white text-lg">WixyMc</h4>
                <span class="text-xs font-semibold px-2.5 py-0.5 rounded-full bg-purple-500/10 text-purple-400 border border-purple-500/20 mt-1 mb-3">Co Fondateur & Dev SysAdmin</span>
                <p class="text-gray-500 text-xs leading-relaxed max-w-xs">Conçoit l'interface utilisateur pour la rendre fluide et accessible à tous.</p>
                <div class="flex gap-3 mt-4 text-gray-400 text-sm">
                    <a href="#" class="hover:text-white transition"><i class="fab fa-github"></i></a>
                    <a href="#" class="hover:text-purple-400 transition"><i class="fab fa-discord"></i></a>
                </div>
            </div>

            <div class="glass p-6 rounded-2xl border border-white/[0.05] text-center flex flex-col items-center sm:col-span-2 lg:col-span-1">
                <div class="relative mb-4">
                    <div class="w-20 h-20 bg-gradient-to-tr from-amber-400 to-orange-500 rounded-full p-0.5 shadow-xl">
                        <img src="/img/staff/Nexium.webp" alt="Avatar" class="w-full h-full object-cover rounded-full bg-[#060911]">
                    </div>
                    <span class="absolute bottom-0 right-1 h-3 w-3 rounded-full bg-green-400 border-2 border-[#060911]"></span>
                </div>
                <h4 class="font-bold text-white text-lg">Nexium</h4>
                <span class="text-xs font-semibold px-2.5 py-0.5 rounded-full bg-amber-500/10 text-amber-400 border border-amber-500/20 mt-1 mb-3">Responsable Support</span>
                <p class="text-gray-500 text-xs leading-relaxed max-w-xs">Supervise la communauté sur Discord et s'assure de l'aide technique 24/7.</p>
                <div class="flex gap-3 mt-4 text-gray-400 text-sm">
                    <a href="#" class="hover:text-amber-400 transition"><i class="fab fa-discord"></i></a>
                </div>
            </div>
        </div>
    </section>


    <section class="py-20 px-6 max-w-7xl mx-auto">
        <div class="text-center mb-12">
            <h2 class="text-3xl md:text-4xl font-black mb-3">Pourquoi <span class="gradient-text">OrinHeberge</span> ?</h2>
            <p class="text-gray-500 max-w-lg mx-auto text-sm">Une infrastructure pensée pour la performance, la simplicité et la fiabilité.</p>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="glass card-hover p-6 rounded-2xl border border-white/[0.05]">
                <div class="w-10 h-10 bg-sky-500/10 rounded-xl flex items-center justify-center mb-4"><i class="fas fa-microchip text-sky-400"></i></div>
                <h4 class="font-bold text-white mb-2">CPU Ryzen HF</h4>
                <p class="text-gray-500 text-xs leading-relaxed">Processeurs Ryzen haute fréquence et stockage SSD NVMe ultra-rapide.</p>
            </div>
            <div class="glass card-hover p-6 rounded-2xl border border-white/[0.05]">
                <div class="w-10 h-10 bg-purple-500/10 rounded-xl flex items-center justify-center mb-4"><i class="fas fa-layer-group text-purple-400"></i></div>
                <h4 class="font-bold text-white mb-2">Multi-environnements</h4>
                <p class="text-gray-500 text-xs leading-relaxed">Minecraft, PHP, Node.js, Python, Java — tout en un seul endroit.</p>
            </div>
            <div class="glass card-hover p-6 rounded-2xl border border-white/[0.05]">
                <div class="w-10 h-10 bg-green-500/10 rounded-xl flex items-center justify-center mb-4"><i class="fas fa-hand-holding-dollar text-green-400"></i></div>
                <h4 class="font-bold text-white mb-2">Gratuit & Abordable</h4>
                <p class="text-gray-500 text-xs leading-relaxed">Commencez gratuitement, évoluez selon vos besoins avec des tarifs compétitifs.</p>
            </div>
            <div class="glass card-hover p-6 rounded-2xl border border-white/[0.05]">
                <div class="w-10 h-10 bg-amber-500/10 rounded-xl flex items-center justify-center mb-4"><i class="fas fa-shield-halved text-amber-400"></i></div>
                <h4 class="font-bold text-white mb-2">Protection DDoS</h4>
                <p class="text-gray-500 text-xs leading-relaxed">Anti-DDoS inclus sur toutes les offres, même gratuites.</p>
            </div>
        </div>
    </section>

    <section class="py-20 px-6 max-w-4xl mx-auto border-t border-white/[0.03]">
        <div class="text-center mb-12">
            <h2 class="text-3xl font-black mb-3">Questions <span class="gradient-text">Fréquentes</span></h2>
            <p class="text-gray-500 text-sm">Tout ce que vous devez savoir pour démarrer sereinement.</p>
        </div>

        <div class="space-y-4">
            <details class="glass p-5 rounded-xl border border-white/[0.05] group transition-all duration-300">
                <summary class="font-bold text-white text-sm flex justify-between items-center cursor-pointer list-none select-none">
                    <span>Comment l'offre gratuite fonctionne-t-elle ?</span>
                    <span class="transition group-open:rotate-180"><i class="fas fa-chevron-down text-gray-500 text-xs"></i></span>
                </summary>
                <p class="text-gray-400 text-xs leading-relaxed mt-3 pt-3 border-t border-white/[0.03]">
                    Notre offre gratuite est financée par nos utilisateurs Premium. Elle vous permet de concevoir, tester et faire tourner de petits projets ou serveurs de jeux entre amis sans aucune limite de temps ni coûts cachés.
                </p>
            </details>

            <details class="glass p-5 rounded-xl border border-white/[0.05] group transition-all duration-300">
                <summary class="font-bold text-white text-sm flex justify-between items-center cursor-pointer list-none select-none">
                    <span>Puis-je changer d'offre ou migrer plus tard ?</span>
                    <span class="transition group-open:rotate-180"><i class="fas fa-chevron-down text-gray-500 text-xs"></i></span>
                </summary>
                <p class="text-gray-400 text-xs leading-relaxed mt-3 pt-3 border-t border-white/[0.03]">
                    Tout à fait ! Vous pouvez passer d'une formule gratuite à une version Premium ou modifier vos ressources à tout moment depuis votre console de gestion client sans aucune perte de vos données existantes.
                </p>
            </details>

            <details class="glass p-5 rounded-xl border border-white/[0.05] group transition-all duration-300">
                <summary class="font-bold text-white text-sm flex justify-between items-center cursor-pointer list-none select-none">
                    <span>Où sont situés vos serveurs ?</span>
                    <span class="transition group-open:rotate-180"><i class="fas fa-chevron-down text-gray-500 text-xs"></i></span>
                </summary>
                <p class="text-gray-400 text-xs leading-relaxed mt-3 pt-3 border-t border-white/[0.03]">
                    Nos infrastructures physiques principales sont hébergées dans des centres de données hautement sécurisés situés en Europe (notamment en France et en Allemagne), assurant des temps de réponse ultra courts.
                </p>
            </details>
        </div>
    </section>

</main>

<?php include __DIR__ . '/inc/footer.php'; ?>

<div class="fixed bottom-6 right-6 z-50">
    <a href="/discord/" target="_blank" class="bg-[#5865F2] hover:bg-[#4752C4] transition text-white px-5 py-4 rounded-full font-bold flex items-center gap-2 shadow-2xl hover:scale-105 transform duration-200">
        <i class="fab fa-discord text-xl"></i>
        <span class="hidden sm:inline text-sm"><?php echo t('discord.help'); ?></span>
    </a>
</div>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/inc/cookie.php'; ?>
</body>
</html>
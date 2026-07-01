<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/lang.php';

$is_logged_in = isset($_SESSION['user_id']);
$db_status = false;

try {
    $pdo = new PDO('mysql:host=localhost;port=3306;dbname=s43_orinheberge;charset=utf8mb4', 'root', '1504', [
        PDO::ATTR_TIMEOUT => 3,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    $db_status = true;

    if ($is_logged_in) {
        $stmt = $pdo->prepare("SELECT pseudo, firstname, avatar FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$_SESSION['user_id']]);
        $user_data = $stmt->fetch();
        if ($user_data) {
            $_SESSION['username'] = !empty($user_data['pseudo']) ? $user_data['pseudo'] : $user_data['firstname'];
            $_SESSION['avatar']   = $user_data['avatar'];
        }
    }
} catch (PDOException $e) {
    $db_status = false;
}

// ── 1. RÉCUPÉRATION ET REGROUPEMENT DES OFFRES DEPUIS LA BDD ──
$products_by_tier = [
    'free' => [],
    'paid' => []
];

if ($db_status) {
    try {
        $stmt = $pdo->query("SELECT * FROM products WHERE is_active = 1 ORDER BY sort_order ASC, id ASC");
        $all_products = $stmt->fetchAll();

        foreach ($all_products as $product) {
            $db_type = strtolower($product['type']); // 'free' ou 'paid' d'après ta BDD
            if (array_key_exists($db_type, $products_by_tier)) {
                $products_by_tier[$db_type][] = $product;
            }
        }
    } catch (PDOException $e) {
        // Sécurité
    }
}

// Images et Icônes associées aux catégories (extraites du début du slug)
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
    'minecraft' => 'fas fa-cube', 'fivem' => 'fas fa-car', 'hytale' => 'fas fa-gamepad',
    'php' => 'fas fa-code', 'nodejs' => 'fab fa-node-js', 'java' => 'fab fa-java', 'python' => 'fab fa-python'
];

// Configuration visuelle des cartes selon le type de plan détecté dans le slug
function getCardStyle($slug) {
    if (strpos($slug, 'free') !== false) {
        return ['label' => 'Offre Gratuite', 'badge_bg'=>'bg-green-500/20', 'badge_text'=>'text-green-400', 'badge_border'=>'border-green-500/30', 'icon_color'=>'text-green-400', 'card_border'=>'border-white/10', 'btn'=>'bg-green-500 hover:bg-green-400', 'period_key'=>'offers.period.free'];
    } elseif (strpos($slug, 'basic') !== false) {
        return ['label' => 'Offre Basic', 'badge_bg'=>'bg-blue-500/20', 'badge_text'=>'text-blue-400', 'badge_border'=>'border-blue-500/30', 'icon_color'=>'text-blue-400', 'card_border'=>'border-blue-400/20', 'btn'=>'bg-blue-500 hover:bg-blue-400', 'period_key'=>'offers.period.month'];
    } elseif (strpos($slug, 'medium') !== false) {
        return ['label' => 'Offre Medium', 'badge_bg'=>'bg-purple-500/20', 'badge_text'=>'text-purple-400', 'badge_border'=>'border-purple-500/30', 'icon_color'=>'text-purple-400', 'card_border'=>'border-purple-400/20', 'btn'=>'bg-purple-500 hover:bg-purple-400', 'period_key'=>'offers.period.month'];
    } else {
        return ['label' => 'Offre Premium', 'badge_bg'=>'bg-yellow-500/20', 'badge_text'=>'text-yellow-400', 'badge_border'=>'border-yellow-500/30', 'icon_color'=>'text-yellow-400', 'card_border'=>'border-yellow-400/20', 'btn'=>'bg-yellow-500 hover:bg-yellow-400', 'period_key'=>'offers.period.month'];
    }
}

$sections_meta = [
    'free' => ['title_key'=>'tier.free.title', 'subtitle_key'=>'tier.free.subtitle', 'accent'=>'bg-green-500', 'bg'=>'bg-white/[0.01] border-y border-white/5'],
    'paid' => ['title_key'=>'tier.paid.title', 'subtitle_key'=>'tier.paid.subtitle', 'accent'=>'bg-blue-500', 'bg'=>'bg-black/10']
];
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OrinHeberge | <?php echo t('offers.title'); ?></title>
    <link rel="icon" type="image/png" href="https://heberge.orinstone.deepstone.fr/favicon.ico">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #0b0f19; scroll-behavior: smooth; }
        .glass { background: rgba(255,255,255,0.04); backdrop-filter: blur(14px); border: 1px solid rgba(255,255,255,0.08); }
        .gradient-text { background: linear-gradient(90deg, #38bdf8, #818cf8); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .card-hover { transition: transform .3s, box-shadow .3s; }
        .card-hover:hover { transform: translateY(-6px); box-shadow: 0 20px 40px rgba(0,0,0,0.3); }
        #mobileMenu { display: none; } #mobileMenu.active { display: block; }
        .tab-btn { padding: 0.6rem 1.5rem; border-radius: 9999px; font-size: 0.85rem; font-weight: 600; transition: all .2s; border: 1px solid rgba(255,255,255,0.1); background: rgba(255,255,255,0.04); color: #9ca3af; cursor: pointer; white-space: nowrap; }
        .tab-btn:hover { background: rgba(255,255,255,0.08); color: #e5e7eb; }
        .tab-btn.active { background: rgba(56,189,248,0.15); border-color: rgba(56,189,248,0.4); color: #38bdf8; box-shadow: 0 0 15px rgba(56,189,248,0.1); }
        #cat-view { display: none; }
        #cat-view .cat-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1.5rem; }
    </style>
</head>
<body class="text-gray-200 font-sans min-h-screen flex flex-col justify-between antialiased">

<script>
function filterCategory(catId) {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + catId).classList.add('active');
    
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
    
    const labels = { minecraft:'Minecraft', fivem:'FiveM', hytale:'Hytale', php:'Web / PHP', python:'Python', nodejs:'Node.js', java:'Java' };
    catTitle.textContent = labels[catId] || catId;
    
    const cards = Array.from(document.querySelectorAll('#all-sections .offer-card[data-category="' + catId + '"]'));
    cards.sort((a,b) => (parseFloat(a.dataset.price)||0) - (parseFloat(b.dataset.price)||0));
    
    catGrid.innerHTML = '';
    cards.forEach(card => { 
        const clone = card.cloneNode(true); 
        clone.style.display = 'flex'; 
        catGrid.appendChild(clone); 
    });
}
window.addEventListener('DOMContentLoaded', () => filterCategory('all'));
</script>

<?php $active_nav = 'offers'; include $_SERVER['DOCUMENT_ROOT'] . '/inc/navbar.php'; ?>

<main class="flex-grow">
    <header class="text-center py-16 px-6 relative overflow-hidden">
        <h1 class="text-5xl md:text-7xl font-black tracking-tight leading-none gradient-text"><?php echo t('offers.title'); ?></h1>
        
        <div class="max-w-4xl mx-auto flex flex-wrap justify-center gap-3 px-4 mt-10">
            <button onclick="filterCategory('all')"       id="tab-all"       class="tab-btn active"><?php echo t('offers.tab.all'); ?></button>
            <button onclick="filterCategory('minecraft')" id="tab-minecraft" class="tab-btn">Minecraft</button>
            <button onclick="filterCategory('fivem')"     id="tab-fivem"     class="tab-btn">FiveM</button>
            <button onclick="filterCategory('hytale')"    id="tab-hytale"    class="tab-btn">Hytale</button>
            <button onclick="filterCategory('php')"       id="tab-php"       class="tab-btn">Web / PHP</button>
            <button onclick="filterCategory('python')"    id="tab-python"    class="tab-btn">Python</button>
            <button onclick="filterCategory('nodejs')"    id="tab-nodejs"    class="tab-btn">Node.js</button>
            <button onclick="filterCategory('java')"      id="tab-java"      class="tab-btn">Java</button>
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
    <?php foreach ($products_by_tier as $tier_key => $products): ?>
        <?php if (empty($products)) continue; $meta = $sections_meta[$tier_key]; ?>
        <section class="offers-section py-20 px-6 <?php echo $meta['bg']; ?>">
            <div class="text-center mb-16">
                <h2 class="text-4xl md:text-5xl font-black uppercase tracking-wider mb-3"><?php echo t($meta['title_key']); ?></h2>
                <div class="h-1 w-20 <?php echo $meta['accent']; ?> mx-auto rounded-full"></div>
            </div>
            
            <div class="max-w-7xl mx-auto grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($products as $product): ?>
            <?php
                // On isole la catégorie d'après le début du slug (ex: 'minecraft' depuis 'minecraft-free')
                $slug_parts = explode('-', $product['slug']);
                $cat = strtolower($slug_parts[0]); 
                
                $img = isset($images[$cat]) ? $images[$cat] : $images['minecraft'];
                $icon = isset($icons[$cat]) ? $icons[$cat] : 'fas fa-server';
                
                $style = getCardStyle($product['slug']);
                $is_free = ($product['type'] === 'free');
                $price_display = $is_free ? '0€' : number_format($product['price'], 2, ',', '') . '€';
                
                // ── 2. RÉCUPÉRATION DU SLUG POUR LA GENERATION DU LIEN DE COMMANDE ──
                if ($is_free) {
                    $route = '/shop/process_free/?type=' . urlencode($product['slug']);
                    $btn_text = $is_logged_in ? t('btn.deploy') : t('btn.login_to_buy');
                } else {
                    $route = '/shop/order/?plan=' . urlencode($product['slug']);
                    $btn_text = $is_logged_in ? t('btn.buy') : t('btn.login_to_buy');
                }
                
                $link = $is_logged_in ? $route : '/login/';
            ?>
            <div data-category="<?php echo $cat; ?>" data-price="<?php echo $product['price']; ?>"
                 class="offer-card glass rounded-3xl border <?php echo $style['card_border']; ?> flex flex-col card-hover overflow-hidden relative">
                
                <div class="h-44 w-full bg-cover bg-center relative" style="background-image: url('<?php echo $img; ?>');">
                    <div class="absolute inset-0 bg-gradient-to-t from-[#070a13] to-transparent"></div>
                    <div class="absolute top-4 left-4 right-4 flex justify-between items-center">
                        <span class="<?php echo $style['badge_bg'].' '.$style['badge_text'].' '.$style['badge_border']; ?> px-3 py-1 rounded-full text-xs font-bold backdrop-blur-md border uppercase tracking-wide">
                            <?php echo $style['label']; ?>
                        </span>
                        <i class="<?php echo $icon.' '.$style['icon_color']; ?> text-2xl drop-shadow"></i>
                    </div>
                </div>
                
                <div class="p-6 flex flex-col flex-grow">
                    <h3 class="text-xl font-bold text-white"><?php echo htmlspecialchars($product['name']); ?></h3>
                    <p class="text-gray-400 mt-2 mb-6 text-sm flex-grow">
                        <?php echo !empty($product['description']) ? htmlspecialchars($product['description']) : "Aucune description fournie."; ?>
                    </p>
                    
                    <div class="flex items-baseline mb-6">
                        <span class="text-3xl font-black text-white"><?php echo $price_display; ?></span>
                        <span class="text-gray-500 text-xs ml-1"><?php echo t($style['period_key']); ?></span>
                    </div>
                    
                    <ul class="space-y-3 text-gray-300 text-sm border-t border-white/5 pt-4">
                        <li><i class="fas fa-memory <?php echo $style['icon_color']; ?> mr-2 w-4"></i><?php echo ($product['ram'] >= 1024) ? number_format($product['ram'] / 1024, 1) . ' GB' : $product['ram'] . ' MB'; ?> RAM</li>
                        <li><i class="fas fa-hard-drive <?php echo $style['icon_color']; ?> mr-2 w-4"></i><?php echo ($product['disk'] >= 1024) ? number_format($product['disk'] / 1024, 0) . ' GB' : $product['disk'] . ' MB'; ?> NVMe</li>
                        <li><i class="fas fa-microchip <?php echo $style['icon_color']; ?> mr-2 w-4"></i><?php echo $product['cpu']; ?>% CPU</li>
                    </ul>
                    
                    <a href="<?php echo $link; ?>" class="mt-6 w-full <?php echo $style['btn']; ?> text-slate-950 font-bold py-3 rounded-2xl transition text-sm text-center block">
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
</body>
</html>

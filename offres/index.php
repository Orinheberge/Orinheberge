<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/lang.php';

$is_logged_in = isset($_SESSION['user_id']);

try {
    $pdo = new PDO('mysql:host=localhost;port=3306;dbname=s43_orinheberge;charset=utf8mb4', 'root', '1504', [
        PDO::ATTR_TIMEOUT => 3,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    if ($is_logged_in) {
        $stmt = $pdo->prepare("SELECT pseudo, firstname, avatar FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$_SESSION['user_id']]);
        $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user_data) {
            $_SESSION['username'] = !empty($user_data['pseudo']) ? $user_data['pseudo'] : $user_data['firstname'];
            $_SESSION['avatar']   = $user_data['avatar'];
        }
    }
} catch (PDOException $e) {}
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OrinHeberge | <?php echo t('offers.title'); ?></title>
    <link rel="icon" type="image/png" href="https://heberge.orinstone.deepstone.fr/favicon.ico">
    <meta name="description" content="Boutique OrinHeberge - Hébergement Minecraft, PHP, Node.js, Python, Java et Hytale gratuit et premium.">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="manifest" href="/manifest.json">
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
function toggleMenu() { document.getElementById('mobileMenu').classList.toggle('active'); }
function filterCategory(catId) {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + catId).classList.add('active');
    const catView = document.getElementById('cat-view');
    const catTitle = document.getElementById('cat-view-title');
    const catGrid = document.getElementById('cat-view-grid');
    const allSections = document.getElementById('all-sections');
    if (catId === 'all') { catView.style.display = 'none'; allSections.style.display = 'block'; return; }
    allSections.style.display = 'none'; catView.style.display = 'block';
    const labels = { minecraft:'Minecraft', fivem:'FiveM', hytale:'Hytale', php:'Web / PHP', python:'Python', nodejs:'Node.js', java:'Java' };
    catTitle.textContent = labels[catId] || catId;
    const cards = Array.from(document.querySelectorAll('#all-sections .offer-card[data-category="' + catId + '"]'));
    cards.sort((a,b) => (parseFloat(a.dataset.price)||0) - (parseFloat(b.dataset.price)||0));
    catGrid.innerHTML = '';
    cards.forEach(card => { const clone = card.cloneNode(true); clone.style.display = 'flex'; catGrid.appendChild(clone); });
}
window.addEventListener('DOMContentLoaded', () => filterCategory('all'));
</script>

<?php $active_nav = 'offers'; include $_SERVER['DOCUMENT_ROOT'] . '/inc/navbar.php'; ?>

<main class="flex-grow">
    <!-- Hero offres -->
    <header class="text-center py-16 px-6 relative overflow-hidden">
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[400px] h-[400px] bg-amber-500/10 rounded-full blur-[100px] pointer-events-none"></div>
        <div class="inline-flex items-center gap-2 bg-amber-500/10 text-amber-400 border border-amber-500/20 px-4 py-1.5 rounded-full text-xs font-semibold mb-6">
            <i class="fas fa-tags"></i> <?php echo t('offers.title'); ?>
        </div>
        <h1 class="text-5xl md:text-7xl font-black tracking-tight leading-none gradient-text"><?php echo t('offers.title'); ?></h1>
        <p class="text-gray-400 mt-4 max-w-xl mx-auto text-lg"><?php echo t('offers.subtitle'); ?></p>
        <!-- Tabs -->
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

    <?php
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
        'minecraft'=>'fas fa-cube','fivem'=>'fas fa-car','hytale'=>'fas fa-gamepad',
        'php'=>'fas fa-code','nodejs'=>'fab fa-node-js',
        'java'=>'fab fa-java','python'=>'fab fa-python',
    ];
    $tier_styles = [
        'free'    => ['badge_bg'=>'bg-green-500/20',  'badge_text'=>'text-green-400',  'badge_border'=>'border-green-500/30',  'icon_color'=>'text-green-400',  'card_border'=>'border-white/10',      'btn'=>'bg-green-500 hover:bg-green-400'],
        'basic'   => ['badge_bg'=>'bg-blue-500/20',   'badge_text'=>'text-blue-400',   'badge_border'=>'border-blue-500/30',   'icon_color'=>'text-blue-400',   'card_border'=>'border-blue-400/20',   'btn'=>'bg-blue-500 hover:bg-blue-400'],
        'medium'  => ['badge_bg'=>'bg-purple-500/20', 'badge_text'=>'text-purple-400', 'badge_border'=>'border-purple-500/30', 'icon_color'=>'text-purple-400', 'card_border'=>'border-purple-400/20', 'btn'=>'bg-purple-500 hover:bg-purple-400'],
        'premium' => ['badge_bg'=>'bg-yellow-500/20', 'badge_text'=>'text-yellow-400', 'badge_border'=>'border-yellow-500/30', 'icon_color'=>'text-yellow-400', 'card_border'=>'border-yellow-400/20', 'btn'=>'bg-yellow-500 hover:bg-yellow-400'],
    ];
    $sections = [
        'free'    => ['title_key'=>'tier.free.title',    'subtitle_key'=>'tier.free.subtitle',    'label_key'=>'tier.free.label',    'accent'=>'bg-green-500',  'bg'=>'bg-white/[0.01] border-y border-white/5',
          'offers'=>[
            ['category'=>'minecraft','name_key'=>'offer.mc_free.name',   'desc_key'=>'offer.mc_free.desc',   'price'=>'0€','period_key'=>'offers.period.free','plan'=>'minecraft', 'free'=>true, 'features'=>[['icon'=>'fas fa-memory','text'=>'6 GB RAM DDR5'],['icon'=>'fas fa-hard-drive','text'=>'30 GB SSD NVMe'],['icon'=>'fas fa-microchip','text'=>'400% CPU Ryzen'],['icon'=>'fas fa-database','text_key'=>'feat.mysql_1']]],
            ['category'=>'fivem',   'name_key'=>'offer.fivem_free.name', 'desc_key'=>'offer.fivem_free.desc','price'=>'0€','period_key'=>'offers.period.free','plan'=>'fivemfree', 'free'=>true, 'features'=>[['icon'=>'fas fa-memory','text'=>'3 GB RAM DDR5'],['icon'=>'fas fa-hard-drive','text'=>'15 GB SSD NVMe'],['icon'=>'fas fa-microchip','text'=>'300% CPU Ryzen'],['icon'=>'fas fa-shield-alt','text_key'=>'feat.ddos']]],
            ['category'=>'hytale',  'name_key'=>'offer.hy_free.name',   'desc_key'=>'offer.hy_free.desc',   'price'=>'0€','period_key'=>'offers.period.free','plan'=>'hytale',    'free'=>true, 'features'=>[['icon'=>'fas fa-memory','text'=>'6 GB RAM DDR5'],['icon'=>'fas fa-hard-drive','text'=>'30 GB SSD NVMe'],['icon'=>'fas fa-microchip','text'=>'400% CPU Ryzen'],['icon'=>'fas fa-shield-alt','text_key'=>'feat.ddos']]],
            ['category'=>'php',     'name_key'=>'offer.php_free.name',  'desc_key'=>'offer.php_free.desc',  'price'=>'0€','period_key'=>'offers.period.free','plan'=>'php',       'free'=>true, 'popular'=>true,'features'=>[['icon'=>'fas fa-memory','text'=>'1 GB RAM'],['icon'=>'fas fa-hard-drive','text'=>'10 GB SSD NVMe'],['icon'=>'fas fa-database','text_key'=>'feat.mysql_1'],['icon'=>'fas fa-lock','text_key'=>'feat.ssl_free']]],
            ['category'=>'python',  'name_key'=>'offer.py_free.name',   'desc_key'=>'offer.py_free.desc',   'price'=>'0€','period_key'=>'offers.period.free','plan'=>'python',    'free'=>true, 'features'=>[['icon'=>'fas fa-memory','text'=>'2 GB RAM DDR5'],['icon'=>'fas fa-hard-drive','text'=>'10 GB SSD NVMe'],['icon'=>'fas fa-database','text_key'=>'feat.mysql_1'],['icon'=>'fas fa-shield-alt','text_key'=>'feat.ddos']]],
            ['category'=>'nodejs',  'name_key'=>'offer.node_free.name', 'desc_key'=>'offer.node_free.desc', 'price'=>'0€','period_key'=>'offers.period.free','plan'=>'nodejs',    'free'=>true, 'features'=>[['icon'=>'fas fa-memory','text'=>'2 GB RAM'],['icon'=>'fas fa-hard-drive','text'=>'10 GB SSD NVMe'],['icon'=>'fas fa-database','text_key'=>'feat.mysql_1'],['icon'=>'fas fa-bolt','text_key'=>'feat.fast_install']]],
            ['category'=>'java',    'name_key'=>'offer.java_free.name', 'desc_key'=>'offer.java_free.desc', 'price'=>'0€','period_key'=>'offers.period.free','plan'=>'java',      'free'=>true, 'features'=>[['icon'=>'fas fa-memory','text'=>'2 GB RAM'],['icon'=>'fas fa-hard-drive','text'=>'10 GB SSD NVMe'],['icon'=>'fas fa-database','text_key'=>'feat.mysql_1'],['icon'=>'fas fa-bolt','text_key'=>'feat.fast_install']]],
          ]],
        'basic'   => ['title_key'=>'tier.basic.title',   'subtitle_key'=>'tier.basic.subtitle',   'label_key'=>'tier.basic.label',   'accent'=>'bg-blue-500',   'bg'=>'bg-black/10',
          'offers'=>[
            ['category'=>'minecraft','name_key'=>'offer.mc_basic.name',   'desc_key'=>'offer.mc_basic.desc',   'price'=>'1,49€','period_key'=>'offers.period.month','plan'=>'minecraftbasic', 'free'=>false,'features'=>[['icon'=>'fas fa-memory','text'=>'4 GB RAM DDR5'],['icon'=>'fas fa-hard-drive','text'=>'20 GB SSD NVMe'],['icon'=>'fas fa-microchip','text'=>'400% CPU Ryzen'],['icon'=>'fas fa-shield-alt','text_key'=>'feat.ddos']]],
            ['category'=>'fivem',   'name_key'=>'offer.fivem_basic.name', 'desc_key'=>'offer.fivem_basic.desc','price'=>'2,99€','period_key'=>'offers.period.month','plan'=>'fivembasic',     'free'=>false,'features'=>[['icon'=>'fas fa-memory','text'=>'4 GB RAM DDR5'],['icon'=>'fas fa-hard-drive','text'=>'20 GB SSD NVMe'],['icon'=>'fas fa-microchip','text'=>'400% CPU Ryzen'],['icon'=>'fas fa-shield-alt','text_key'=>'feat.ddos']]],
            ['category'=>'hytale',  'name_key'=>'offer.hy_basic.name',   'desc_key'=>'offer.hy_basic.desc',   'price'=>'7,99€','period_key'=>'offers.period.month','plan'=>'hytalebasic',    'free'=>false,'features'=>[['icon'=>'fas fa-memory','text'=>'4 GB RAM DDR5'],['icon'=>'fas fa-hard-drive','text'=>'20 GB SSD NVMe'],['icon'=>'fas fa-microchip','text'=>'400% CPU Ryzen'],['icon'=>'fas fa-shield-alt','text_key'=>'feat.ddos']]],
            ['category'=>'php',     'name_key'=>'offer.php_basic.name',  'desc_key'=>'offer.php_basic.desc',  'price'=>'1,99€','period_key'=>'offers.period.month','plan'=>'phpbasic',       'free'=>false,'features'=>[['icon'=>'fas fa-memory','text'=>'512 MB RAM'],['icon'=>'fas fa-hard-drive','text'=>'5 GB SSD NVMe'],['icon'=>'fas fa-database','text_key'=>'feat.mysql_1'],['icon'=>'fas fa-lock','text_key'=>'feat.ssl_free']]],
            ['category'=>'python',  'name_key'=>'offer.py_basic.name',   'desc_key'=>'offer.py_basic.desc',   'price'=>'2,49€','period_key'=>'offers.period.month','plan'=>'pythonbasic',    'free'=>false,'features'=>[['icon'=>'fas fa-memory','text'=>'512 MB RAM DDR5'],['icon'=>'fas fa-hard-drive','text'=>'5 GB SSD NVMe'],['icon'=>'fas fa-microchip','text'=>'100% CPU'],['icon'=>'fas fa-shield-alt','text_key'=>'feat.ddos']]],
            ['category'=>'nodejs',  'name_key'=>'offer.node_basic.name', 'desc_key'=>'offer.node_basic.desc', 'price'=>'1,49€','period_key'=>'offers.period.month','plan'=>'nodejsbasic',    'free'=>false,'features'=>[['icon'=>'fas fa-memory','text'=>'512 MB RAM'],['icon'=>'fas fa-hard-drive','text'=>'5 GB SSD NVMe'],['icon'=>'fas fa-microchip','text'=>'100% CPU'],['icon'=>'fas fa-bolt','text_key'=>'feat.fast_install']]],
            ['category'=>'java',    'name_key'=>'offer.java_basic.name', 'desc_key'=>'offer.java_basic.desc', 'price'=>'3,99€','period_key'=>'offers.period.month','plan'=>'javabasic',      'free'=>false,'features'=>[['icon'=>'fas fa-memory','text'=>'512 MB RAM'],['icon'=>'fas fa-hard-drive','text'=>'5 GB SSD NVMe'],['icon'=>'fas fa-microchip','text'=>'100% CPU'],['icon'=>'fas fa-bolt','text_key'=>'feat.fast_install']]],
          ]],
        'medium'  => ['title_key'=>'tier.medium.title',  'subtitle_key'=>'tier.medium.subtitle',  'label_key'=>'tier.medium.label',  'accent'=>'bg-purple-500', 'bg'=>'bg-white/[0.02] border-y border-white/5',
          'offers'=>[
            ['category'=>'minecraft','name_key'=>'offer.mc_medium.name',   'desc_key'=>'offer.mc_medium.desc',   'price'=>'2,99€', 'period_key'=>'offers.period.month','plan'=>'minecraftmedium','free'=>false,'features'=>[['icon'=>'fas fa-memory','text'=>'8 GB RAM DDR5'],['icon'=>'fas fa-hard-drive','text'=>'50 GB SSD NVMe'],['icon'=>'fas fa-microchip','text'=>'800% CPU Ryzen'],['icon'=>'fas fa-shield-alt','text_key'=>'feat.ddos']]],
            ['category'=>'fivem',   'name_key'=>'offer.fivem_medium.name', 'desc_key'=>'offer.fivem_medium.desc','price'=>'6,99€', 'period_key'=>'offers.period.month','plan'=>'fivemmedium',    'free'=>false,'features'=>[['icon'=>'fas fa-memory','text'=>'8 GB RAM DDR5'],['icon'=>'fas fa-hard-drive','text'=>'50 GB SSD NVMe'],['icon'=>'fas fa-microchip','text'=>'800% CPU Ryzen'],['icon'=>'fas fa-shield-alt','text_key'=>'feat.ddos']]],
            ['category'=>'hytale',  'name_key'=>'offer.hy_medium.name',   'desc_key'=>'offer.hy_medium.desc',   'price'=>'14,99€','period_key'=>'offers.period.month','plan'=>'hytalemedium',   'free'=>false,'features'=>[['icon'=>'fas fa-memory','text'=>'6 GB RAM DDR5'],['icon'=>'fas fa-hard-drive','text'=>'50 GB SSD NVMe'],['icon'=>'fas fa-microchip','text'=>'800% CPU Ryzen'],['icon'=>'fas fa-shield-alt','text_key'=>'feat.ddos']]],
            ['category'=>'php',     'name_key'=>'offer.php_medium.name',  'desc_key'=>'offer.php_medium.desc',  'price'=>'4,99€', 'period_key'=>'offers.period.month','plan'=>'phpmedium',      'free'=>false,'features'=>[['icon'=>'fas fa-memory','text'=>'2 GB RAM'],['icon'=>'fas fa-hard-drive','text'=>'10 GB SSD NVMe'],['icon'=>'fas fa-database','text_key'=>'feat.mysql_unlim'],['icon'=>'fas fa-lock','text_key'=>'feat.ssl_le']]],
            ['category'=>'python',  'name_key'=>'offer.py_medium.name',   'desc_key'=>'offer.py_medium.desc',   'price'=>'4,99€', 'period_key'=>'offers.period.month','plan'=>'pythonmedium',   'free'=>false,'features'=>[['icon'=>'fas fa-memory','text'=>'2 GB RAM DDR5'],['icon'=>'fas fa-hard-drive','text'=>'20 GB SSD NVMe'],['icon'=>'fas fa-microchip','text'=>'500% CPU'],['icon'=>'fas fa-shield-alt','text_key'=>'feat.ddos']]],
            ['category'=>'nodejs',  'name_key'=>'offer.node_medium.name', 'desc_key'=>'offer.node_medium.desc', 'price'=>'2,99€', 'period_key'=>'offers.period.month','plan'=>'nodejsmedium',   'free'=>false,'features'=>[['icon'=>'fas fa-memory','text'=>'2 GB RAM'],['icon'=>'fas fa-hard-drive','text'=>'20 GB SSD NVMe'],['icon'=>'fas fa-microchip','text'=>'500% CPU'],['icon'=>'fas fa-bolt','text_key'=>'feat.git_auto']]],
            ['category'=>'java',    'name_key'=>'offer.java_medium.name', 'desc_key'=>'offer.java_medium.desc', 'price'=>'7,99€', 'period_key'=>'offers.period.month','plan'=>'javamedium',     'free'=>false,'features'=>[['icon'=>'fas fa-memory','text'=>'2 GB RAM'],['icon'=>'fas fa-hard-drive','text'=>'20 GB SSD NVMe'],['icon'=>'fas fa-microchip','text'=>'500% CPU'],['icon'=>'fas fa-bolt','text_key'=>'feat.fast_install']]],
          ]],
        'premium' => ['title_key'=>'tier.premium.title', 'subtitle_key'=>'tier.premium.subtitle', 'label_key'=>'tier.premium.label', 'accent'=>'bg-yellow-500', 'bg'=>'bg-black/20',
          'offers'=>[
            ['category'=>'minecraft','name_key'=>'offer.mc_premium.name',   'desc_key'=>'offer.mc_premium.desc',   'price'=>'24,99€','period_key'=>'offers.period.month','plan'=>'minecraft','free'=>false,'features'=>[['icon'=>'fas fa-check','text'=>'20 GB RAM DDR5'],['icon'=>'fas fa-check','text'=>'150 GB SSD NVMe'],['icon'=>'fas fa-check','text'=>'2000% CPU dédié'],['icon'=>'fas fa-check','text_key'=>'feat.support247']]],
            ['category'=>'fivem',   'name_key'=>'offer.fivem_premium.name', 'desc_key'=>'offer.fivem_premium.desc','price'=>'19,99€','period_key'=>'offers.period.month','plan'=>'fivem',    'free'=>false,'features'=>[['icon'=>'fas fa-check','text'=>'16 GB RAM DDR5'],['icon'=>'fas fa-check','text'=>'100 GB SSD NVMe'],['icon'=>'fas fa-check','text'=>'1500% CPU dédié'],['icon'=>'fas fa-check','text_key'=>'feat.support247']]],
            ['category'=>'hytale',  'name_key'=>'offer.hy_premium.name',   'desc_key'=>'offer.hy_premium.desc',   'price'=>'29,99€','period_key'=>'offers.period.month','plan'=>'hytale',   'free'=>false,'features'=>[['icon'=>'fas fa-check','text'=>'10 GB RAM DDR5'],['icon'=>'fas fa-check','text'=>'100 GB SSD NVMe'],['icon'=>'fas fa-check','text'=>'1400% CPU HF'],['icon'=>'fas fa-check','text_key'=>'feat.priority_sup']]],
            ['category'=>'php',     'name_key'=>'offer.php_premium.name',  'desc_key'=>'offer.php_premium.desc',  'price'=>'19,99€','period_key'=>'offers.period.month','plan'=>'php',      'free'=>false,'features'=>[['icon'=>'fas fa-check','text'=>'8 GB RAM DDR5'],['icon'=>'fas fa-check','text'=>'30 GB SSD NVMe'],['icon'=>'fas fa-check','text_key'=>'feat.php8'],['icon'=>'fas fa-check','text_key'=>'feat.cron']]],
            ['category'=>'python',  'name_key'=>'offer.py_premium.name',   'desc_key'=>'offer.py_premium.desc',   'price'=>'9,99€', 'period_key'=>'offers.period.month','plan'=>'python',   'free'=>false,'features'=>[['icon'=>'fas fa-check','text'=>'4 GB RAM DDR5'],['icon'=>'fas fa-check','text'=>'40 GB SSD NVMe'],['icon'=>'fas fa-check','text'=>'1000% CPU dédié'],['icon'=>'fas fa-check','text_key'=>'feat.support247']]],
            ['category'=>'nodejs',  'name_key'=>'offer.node_premium.name', 'desc_key'=>'offer.node_premium.desc', 'price'=>'5,99€', 'period_key'=>'offers.period.month','plan'=>'nodejs',   'free'=>false,'features'=>[['icon'=>'fas fa-check','text'=>'4 GB RAM DDR5'],['icon'=>'fas fa-check','text'=>'40 GB SSD NVMe'],['icon'=>'fas fa-check','text'=>'1000% CPU dédié'],['icon'=>'fas fa-check','text_key'=>'feat.support247']]],
            ['category'=>'java',    'name_key'=>'offer.java_premium.name', 'desc_key'=>'offer.java_premium.desc', 'price'=>'15,99€','period_key'=>'offers.period.month','plan'=>'java',     'free'=>false,'features'=>[['icon'=>'fas fa-check','text'=>'4 GB RAM DDR5'],['icon'=>'fas fa-check','text'=>'40 GB SSD NVMe'],['icon'=>'fas fa-check','text'=>'1000% CPU dédié'],['icon'=>'fas fa-check','text_key'=>'feat.support247']]],
          ]],
    ];
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
    <?php foreach ($sections as $tier_key => $section): ?>
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
            $cat = $offer['category']; $img = $images[$cat]; $icon = $icons[$cat];
            $popular = !empty($offer['popular']);
            $border_class = $popular ? 'border-2 border-sky-500 bg-sky-500/[0.02]' : "border {$s['card_border']}";
            $route = $offer['free'] ? '/shop/process_free/?type=' . $offer['plan'] : '/shop/order/?plan=' . $offer['plan'];
            $btn_text = $offer['free']
                ? ($is_logged_in ? ($cat === 'php' ? t('btn.host_site') : t('btn.deploy')) : t('btn.login_to_buy'))
                : ($is_logged_in ? t('btn.buy') : t('btn.login_to_buy'));
            $link = $is_logged_in ? $route : '/login/';
            $price_num = $offer['free'] ? 0 : (float) str_replace([',','€'], ['.',''], $offer['price']);
        ?>
        <div data-category="<?php echo $cat; ?>" data-price="<?php echo $price_num; ?>"
             class="offer-card glass rounded-3xl <?php echo $border_class; ?> flex flex-col card-hover overflow-hidden relative">
            <?php if ($popular): ?>
                <div class="absolute top-3 right-3 z-10 bg-sky-500 text-slate-950 px-3 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider shadow-lg"><?php echo t('btn.popular'); ?></div>
            <?php endif; ?>
            <div class="h-44 w-full bg-cover bg-center relative" style="background-image: url('<?php echo $img; ?>');">
                <div class="absolute inset-0 bg-gradient-to-t from-[#070a13] to-transparent"></div>
                <div class="absolute top-4 left-4 right-4 flex justify-between items-center">
                    <span class="<?php echo $s['badge_bg'].' '.$s['badge_text'].' '.$s['badge_border']; ?> px-3 py-1 rounded-full text-xs font-bold backdrop-blur-md border uppercase tracking-wide"><?php echo t($section['label_key']); ?></span>
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
                    <li><i class="<?php echo $feat['icon'].' '.$s['icon_color']; ?> mr-2 w-4"></i><?php echo isset($feat['text_key']) ? t($feat['text_key']) : $feat['text']; ?></li>
                    <?php endforeach; ?>
                </ul>
                <a href="<?php echo $link; ?>" class="mt-6 w-full <?php echo $s['btn']; ?> text-slate-950 font-bold py-3 rounded-2xl transition text-sm text-center block"><?php echo $btn_text; ?></a>
            </div>
        </div>
        <?php endforeach; ?>
        </div>
    </section>
    <?php endforeach; ?>
    </div>
</main>

<div class="fixed bottom-6 right-6 z-50">
    <a href="/discord/" target="_blank" class="bg-[#5865F2] hover:bg-[#4752C4] transition text-white px-5 py-4 rounded-full font-bold flex items-center gap-2 shadow-2xl hover:scale-105 transform duration-200">
        <i class="fab fa-discord text-xl"></i>
        <span class="hidden sm:inline text-sm"><?php echo t('discord.help'); ?></span>
    </a>
</div>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/inc/footer.php'; ?>
</body>
</html>

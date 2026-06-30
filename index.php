<?php
session_start();
require_once __DIR__ . '/inc/lang.php';

$db_status   = false;
$is_logged_in = isset($_SESSION['user_id']);

try {
    $pdo = new PDO('mysql:host=localhost;port=3306;dbname=s43_orinheberge;charset=utf8mb4','root','1504',[PDO::ATTR_TIMEOUT=>3,PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
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
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OrinHeberge | Hébergement Premium</title>
    <link rel="icon" type="image/png" href="/favicon.ico">
    <meta name="description" content="OrinHeberge - Hébergement VPS, Minecraft, PHP et Node.js ultra rapide, gratuit et premium.">
    <meta property="og:title" content="OrinHeberge - Hébergement Premium">
    <meta property="og:description" content="Serveurs rapides, sécurisés et performants.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://heberge.orinstone.deepstone.fr">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="manifest" href="/manifest.json">
    <style>
        body{background:#080c14;scroll-behavior:smooth;}
        .glass{background:rgba(255,255,255,0.035);backdrop-filter:blur(16px);border:1px solid rgba(255,255,255,0.07);}
        .gradient-text{background:linear-gradient(135deg,#38bdf8 0%,#818cf8 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent;}
        .card-hover{transition:transform .25s,box-shadow .25s,border-color .25s;}
        .card-hover:hover{transform:translateY(-5px);box-shadow:0 24px 48px rgba(0,0,0,.35);border-color:rgba(56,189,248,.25);}
        .tab-btn{padding:.5rem 1.25rem;border-radius:9999px;font-size:.8rem;font-weight:600;transition:all .2s;border:1px solid rgba(255,255,255,.08);background:rgba(255,255,255,.03);color:#9ca3af;cursor:pointer;white-space:nowrap;}
        .tab-btn:hover{background:rgba(255,255,255,.07);color:#e5e7eb;}
        .tab-btn.active{background:rgba(56,189,248,.12);border-color:rgba(56,189,248,.35);color:#38bdf8;}
        #cat-view{display:none;}
        #mobileMenu{display:none;}#mobileMenu.active{display:block;}
        .hero-glow{position:absolute;border-radius:50%;filter:blur(120px);pointer-events:none;}
        .feat-card{transition:background .2s,border-color .2s;}
        .feat-card:hover{background:rgba(255,255,255,.05);border-color:rgba(255,255,255,.12);}
        .stat-num{font-variant-numeric:tabular-nums;}
    </style>
    <script>
        if('serviceWorker' in navigator){window.addEventListener('load',()=>{navigator.serviceWorker.register('/sw.js').catch(()=>{});});}
        function toggleMenu(){document.getElementById('mobileMenu').classList.toggle('active');}
        function filterCategory(id){
            document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));
            document.getElementById('tab-'+id).classList.add('active');
            const cv=document.getElementById('cat-view');
            const al=document.getElementById('all-sections');
            if(id==='all'){cv.style.display='none';al.style.display='block';return;}
            al.style.display='none';cv.style.display='block';
            const labels={minecraft:'Minecraft',hytale:'Hytale',php:'Web / PHP',python:'Python',nodejs:'Node.js',java:'Java'};
            document.getElementById('cat-view-title').textContent=labels[id]||id;
            const grid=document.getElementById('cat-view-grid');
            const cards=Array.from(document.querySelectorAll('#all-sections .offer-card[data-category="'+id+'"]'));
            cards.sort((a,b)=>(parseFloat(a.dataset.price)||0)-(parseFloat(b.dataset.price)||0));
            grid.innerHTML='';cards.forEach(c=>{const cl=c.cloneNode(true);cl.style.display='flex';grid.appendChild(cl);});
        }
        window.addEventListener('DOMContentLoaded',()=>filterCategory('all'));
    </script>
</head>
<body class="text-gray-200 font-sans min-h-screen flex flex-col antialiased">

<?php $active_nav = 'home'; include __DIR__ . '/inc/navbar.php'; ?>

<main class="flex-grow">

    <!-- ══ HERO ══ -->
    <section class="relative text-center py-28 md:py-40 px-6 overflow-hidden">
        <div class="hero-glow w-[600px] h-[600px] bg-sky-500/8 top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2"></div>
        <div class="hero-glow w-[300px] h-[300px] bg-purple-500/8 top-1/4 left-1/4"></div>

        <div class="relative z-10 max-w-4xl mx-auto">
            <div class="inline-flex items-center gap-2 bg-sky-500/10 text-sky-400 border border-sky-500/20 px-4 py-1.5 rounded-full text-xs font-semibold mb-8 tracking-wide">
                <span class="h-2 w-2 rounded-full <?php echo $db_status ? 'bg-green-400 animate-pulse' : 'bg-red-400'; ?>"></span>
                <?php echo $db_status ? 'Tous les systèmes opérationnels' : 'Connexion BDD indisponible'; ?>
            </div>

            <h1 class="text-6xl md:text-8xl font-black tracking-tight leading-[.9] mb-6">
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

    <!-- ══ STATS ══ -->
    <section class="py-8 px-6 border-y border-white/[0.04]">
        <div class="max-w-4xl mx-auto grid grid-cols-2 md:grid-cols-4 gap-6 text-center">
            <div><p class="text-3xl font-black gradient-text stat-num">100%</p><p class="text-gray-500 text-xs mt-1">SSD NVMe</p></div>
            <div><p class="text-3xl font-black gradient-text stat-num">0€</p><p class="text-gray-500 text-xs mt-1">Pour commencer</p></div>
            <div><p class="text-3xl font-black gradient-text stat-num">24/7</p><p class="text-gray-500 text-xs mt-1">Support Discord</p></div>
            <div><p class="text-3xl font-black gradient-text stat-num">DDoS</p><p class="text-gray-500 text-xs mt-1">Protection incluse</p></div>
        </div>
    </section>

    <!-- ══ FEATURES ══ -->
    <section class="py-20 px-6 max-w-7xl mx-auto">
        <div class="text-center mb-12">
            <h2 class="text-3xl md:text-4xl font-black mb-3">Pourquoi <span class="gradient-text">OrinHeberge</span> ?</h2>
            <p class="text-gray-500 max-w-lg mx-auto text-sm">Une infrastructure pensée pour la performance, la simplicité et la fiabilité.</p>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="glass feat-card p-6 rounded-2xl border border-white/[0.05]">
                <div class="w-10 h-10 bg-sky-500/10 rounded-xl flex items-center justify-center mb-4"><i class="fas fa-microchip text-sky-400"></i></div>
                <h4 class="font-bold text-white mb-2">CPU Ryzen HF</h4>
                <p class="text-gray-500 text-xs leading-relaxed">Processeurs Ryzen haute fréquence et stockage SSD NVMe ultra-rapide.</p>
            </div>
            <div class="glass feat-card p-6 rounded-2xl border border-white/[0.05]">
                <div class="w-10 h-10 bg-purple-500/10 rounded-xl flex items-center justify-center mb-4"><i class="fas fa-layer-group text-purple-400"></i></div>
                <h4 class="font-bold text-white mb-2">Multi-environnements</h4>
                <p class="text-gray-500 text-xs leading-relaxed">Minecraft, PHP, Node.js, Python, Java — tout en un seul endroit.</p>
            </div>
            <div class="glass feat-card p-6 rounded-2xl border border-white/[0.05]">
                <div class="w-10 h-10 bg-green-500/10 rounded-xl flex items-center justify-center mb-4"><i class="fas fa-hand-holding-dollar text-green-400"></i></div>
                <h4 class="font-bold text-white mb-2">Gratuit & Abordable</h4>
                <p class="text-gray-500 text-xs leading-relaxed">Commencez gratuitement, évoluez selon vos besoins avec des tarifs compétitifs.</p>
            </div>
            <div class="glass feat-card p-6 rounded-2xl border border-white/[0.05]">
                <div class="w-10 h-10 bg-amber-500/10 rounded-xl flex items-center justify-center mb-4"><i class="fas fa-shield-halved text-amber-400"></i></div>
                <h4 class="font-bold text-white mb-2">Protection DDoS</h4>
                <p class="text-gray-500 text-xs leading-relaxed">Anti-DDoS inclus sur toutes les offres, même gratuites.</p>
            </div>
        </div>
    </section>

    <!-- ══ OFFRES ══ -->
    <section id="offres" class="py-10 px-6 text-center">
        <h2 class="text-4xl md:text-5xl font-black uppercase tracking-wider mb-3 gradient-text">Nos Offres</h2>
        <p class="text-gray-500 text-base max-w-xl mx-auto mb-10">Du gratuit au premium — choisissez ce qui vous convient.</p>
        <div class="max-w-4xl mx-auto flex flex-wrap justify-center gap-2.5 px-4">
            <button onclick="filterCategory('all')"       id="tab-all"       class="tab-btn active">Tout</button>
            <button onclick="filterCategory('minecraft')" id="tab-minecraft" class="tab-btn">Minecraft</button>
            <button onclick="filterCategory('hytale')"    id="tab-hytale"    class="tab-btn">Hytale</button>
            <button onclick="filterCategory('php')"       id="tab-php"       class="tab-btn">Web / PHP</button>
            <button onclick="filterCategory('python')"    id="tab-python"    class="tab-btn">Python</button>
            <button onclick="filterCategory('nodejs')"    id="tab-nodejs"    class="tab-btn">Node.js</button>
            <button onclick="filterCategory('java')"      id="tab-java"      class="tab-btn">Java</button>
        </div>
    </section>

    <?php
    $images=['minecraft'=>'https://www.4netplayers.com/images/minecraft/blog/teaser-image.jpg','hytale'=>'https://cdn.minestrator.com/blog/articles/155/thumbnail.webp','php'=>'https://images.unsplash.com/photo-1555066931-4365d14bab8c?q=80&w=600&auto=format&fit=crop','nodejs'=>'https://images.unsplash.com/photo-1607799279861-4dd421887fb3?q=80&w=600&auto=format&fit=crop','java'=>'https://images.unsplash.com/photo-1607799279861-4dd421887fb3?q=80&w=600&auto=format&fit=crop','python'=>'https://images.unsplash.com/photo-1542831371-29b0f74f9713?q=80&w=600&auto=format&fit=crop'];
    $icons=['minecraft'=>'fas fa-cube','hytale'=>'fas fa-gamepad','php'=>'fas fa-code','nodejs'=>'fab fa-node-js','java'=>'fab fa-java','python'=>'fab fa-python'];
    $tier_styles=['free'=>['badge_bg'=>'bg-green-500/20','badge_text'=>'text-green-400','badge_border'=>'border-green-500/30','icon_color'=>'text-green-400','card_border'=>'border-white/10','btn'=>'bg-green-500 hover:bg-green-400'],'basic'=>['badge_bg'=>'bg-blue-500/20','badge_text'=>'text-blue-400','badge_border'=>'border-blue-500/30','icon_color'=>'text-blue-400','card_border'=>'border-blue-400/20','btn'=>'bg-blue-500 hover:bg-blue-400'],'medium'=>['badge_bg'=>'bg-purple-500/20','badge_text'=>'text-purple-400','badge_border'=>'border-purple-500/30','icon_color'=>'text-purple-400','card_border'=>'border-purple-400/20','btn'=>'bg-purple-500 hover:bg-purple-400'],'premium'=>['badge_bg'=>'bg-yellow-500/20','badge_text'=>'text-yellow-400','badge_border'=>'border-yellow-500/30','icon_color'=>'text-yellow-400','card_border'=>'border-yellow-400/20','btn'=>'bg-yellow-500 hover:bg-yellow-400']];
    $sections=['free'=>['title_key'=>'tier.free.title','subtitle_key'=>'tier.free.subtitle','label_key'=>'tier.free.label','accent'=>'bg-green-500','bg'=>'bg-white/[0.01] border-y border-white/[0.04]','offers'=>[['category'=>'minecraft','name_key'=>'offer.mc_free.name','desc_key'=>'offer.mc_free.desc','price'=>'0€','period_key'=>'offers.period.free','plan'=>'minecraft','free'=>true,'features'=>[['icon'=>'fas fa-memory','text'=>'6 GB RAM DDR5'],['icon'=>'fas fa-hard-drive','text'=>'30 GB SSD NVMe'],['icon'=>'fas fa-microchip','text'=>'400% CPU Ryzen'],['icon'=>'fas fa-database','text_key'=>'feat.mysql_1']]],['category'=>'hytale','name_key'=>'offer.hy_free.name','desc_key'=>'offer.hy_free.desc','price'=>'0€','period_key'=>'offers.period.free','plan'=>'hytale','free'=>true,'features'=>[['icon'=>'fas fa-memory','text'=>'6 GB RAM DDR5'],['icon'=>'fas fa-hard-drive','text'=>'30 GB SSD NVMe'],['icon'=>'fas fa-microchip','text'=>'400% CPU Ryzen'],['icon'=>'fas fa-shield-alt','text_key'=>'feat.ddos']]],['category'=>'php','name_key'=>'offer.php_free.name','desc_key'=>'offer.php_free.desc','price'=>'0€','period_key'=>'offers.period.free','plan'=>'php','free'=>true,'popular'=>true,'features'=>[['icon'=>'fas fa-memory','text'=>'1 GB RAM'],['icon'=>'fas fa-hard-drive','text'=>'10 GB SSD NVMe'],['icon'=>'fas fa-database','text_key'=>'feat.mysql_1'],['icon'=>'fas fa-lock','text_key'=>'feat.ssl_free']]],['category'=>'python','name_key'=>'offer.py_free.name','desc_key'=>'offer.py_free.desc','price'=>'0€','period_key'=>'offers.period.free','plan'=>'python','free'=>true,'features'=>[['icon'=>'fas fa-memory','text'=>'2 GB RAM DDR5'],['icon'=>'fas fa-hard-drive','text'=>'10 GB SSD NVMe'],['icon'=>'fas fa-database','text_key'=>'feat.mysql_1'],['icon'=>'fas fa-shield-alt','text_key'=>'feat.ddos']]],['category'=>'nodejs','name_key'=>'offer.node_free.name','desc_key'=>'offer.node_free.desc','price'=>'0€','period_key'=>'offers.period.free','plan'=>'nodejs','free'=>true,'features'=>[['icon'=>'fas fa-memory','text'=>'2 GB RAM'],['icon'=>'fas fa-hard-drive','text'=>'10 GB SSD NVMe'],['icon'=>'fas fa-database','text_key'=>'feat.mysql_1'],['icon'=>'fas fa-bolt','text_key'=>'feat.fast_install']]],['category'=>'java','name_key'=>'offer.java_free.name','desc_key'=>'offer.java_free.desc','price'=>'0€','period_key'=>'offers.period.free','plan'=>'java','free'=>true,'features'=>[['icon'=>'fas fa-memory','text'=>'2 GB RAM'],['icon'=>'fas fa-hard-drive','text'=>'10 GB SSD NVMe'],['icon'=>'fas fa-database','text_key'=>'feat.mysql_1'],['icon'=>'fas fa-bolt','text_key'=>'feat.fast_install']]]]],'basic'=>['title_key'=>'tier.basic.title','subtitle_key'=>'tier.basic.subtitle','label_key'=>'tier.basic.label','accent'=>'bg-blue-500','bg'=>'bg-black/10','offers'=>[['category'=>'minecraft','name_key'=>'offer.mc_basic.name','desc_key'=>'offer.mc_basic.desc','price'=>'1,49€','period_key'=>'offers.period.month','plan'=>'minecraftbasic','free'=>false,'features'=>[['icon'=>'fas fa-memory','text'=>'4 GB RAM DDR5'],['icon'=>'fas fa-hard-drive','text'=>'20 GB SSD NVMe'],['icon'=>'fas fa-microchip','text'=>'400% CPU Ryzen'],['icon'=>'fas fa-shield-alt','text_key'=>'feat.ddos']]],['category'=>'hytale','name_key'=>'offer.hy_basic.name','desc_key'=>'offer.hy_basic.desc','price'=>'7,99€','period_key'=>'offers.period.month','plan'=>'hytalebasic','free'=>false,'features'=>[['icon'=>'fas fa-memory','text'=>'4 GB RAM DDR5'],['icon'=>'fas fa-hard-drive','text'=>'20 GB SSD NVMe'],['icon'=>'fas fa-microchip','text'=>'400% CPU Ryzen'],['icon'=>'fas fa-shield-alt','text_key'=>'feat.ddos']]],['category'=>'php','name_key'=>'offer.php_basic.name','desc_key'=>'offer.php_basic.desc','price'=>'1,99€','period_key'=>'offers.period.month','plan'=>'phpbasic','free'=>false,'features'=>[['icon'=>'fas fa-memory','text'=>'512 MB RAM'],['icon'=>'fas fa-hard-drive','text'=>'5 GB SSD NVMe'],['icon'=>'fas fa-database','text_key'=>'feat.mysql_1'],['icon'=>'fas fa-lock','text_key'=>'feat.ssl_free']]],['category'=>'python','name_key'=>'offer.py_basic.name','desc_key'=>'offer.py_basic.desc','price'=>'2,49€','period_key'=>'offers.period.month','plan'=>'pythonbasic','free'=>false,'features'=>[['icon'=>'fas fa-memory','text'=>'512 MB RAM DDR5'],['icon'=>'fas fa-hard-drive','text'=>'5 GB SSD NVMe'],['icon'=>'fas fa-microchip','text'=>'100% CPU'],['icon'=>'fas fa-shield-alt','text_key'=>'feat.ddos']]],['category'=>'nodejs','name_key'=>'offer.node_basic.name','desc_key'=>'offer.node_basic.desc','price'=>'1,49€','period_key'=>'offers.period.month','plan'=>'nodejsbasic','free'=>false,'features'=>[['icon'=>'fas fa-memory','text'=>'512 MB RAM'],['icon'=>'fas fa-hard-drive','text'=>'5 GB SSD NVMe'],['icon'=>'fas fa-microchip','text'=>'100% CPU'],['icon'=>'fas fa-bolt','text_key'=>'feat.fast_install']]],['category'=>'java','name_key'=>'offer.java_basic.name','desc_key'=>'offer.java_basic.desc','price'=>'3,99€','period_key'=>'offers.period.month','plan'=>'javabasic','free'=>false,'features'=>[['icon'=>'fas fa-memory','text'=>'512 MB RAM'],['icon'=>'fas fa-hard-drive','text'=>'5 GB SSD NVMe'],['icon'=>'fas fa-microchip','text'=>'100% CPU'],['icon'=>'fas fa-bolt','text_key'=>'feat.fast_install']]]]],'medium'=>['title_key'=>'tier.medium.title','subtitle_key'=>'tier.medium.subtitle','label_key'=>'tier.medium.label','accent'=>'bg-purple-500','bg'=>'bg-white/[0.015] border-y border-white/[0.04]','offers'=>[['category'=>'minecraft','name_key'=>'offer.mc_medium.name','desc_key'=>'offer.mc_medium.desc','price'=>'2,99€','period_key'=>'offers.period.month','plan'=>'minecraftmedium','free'=>false,'features'=>[['icon'=>'fas fa-memory','text'=>'8 GB RAM DDR5'],['icon'=>'fas fa-hard-drive','text'=>'50 GB SSD NVMe'],['icon'=>'fas fa-microchip','text'=>'800% CPU Ryzen'],['icon'=>'fas fa-shield-alt','text_key'=>'feat.ddos']]],['category'=>'hytale','name_key'=>'offer.hy_medium.name','desc_key'=>'offer.hy_medium.desc','price'=>'14,99€','period_key'=>'offers.period.month','plan'=>'hytalemedium','free'=>false,'features'=>[['icon'=>'fas fa-memory','text'=>'6 GB RAM DDR5'],['icon'=>'fas fa-hard-drive','text'=>'50 GB SSD NVMe'],['icon'=>'fas fa-microchip','text'=>'800% CPU Ryzen'],['icon'=>'fas fa-shield-alt','text_key'=>'feat.ddos']]],['category'=>'php','name_key'=>'offer.php_medium.name','desc_key'=>'offer.php_medium.desc','price'=>'4,99€','period_key'=>'offers.period.month','plan'=>'phpmedium','free'=>false,'features'=>[['icon'=>'fas fa-memory','text'=>'2 GB RAM'],['icon'=>'fas fa-hard-drive','text'=>'10 GB SSD NVMe'],['icon'=>'fas fa-database','text_key'=>'feat.mysql_unlim'],['icon'=>'fas fa-lock','text_key'=>'feat.ssl_le']]],['category'=>'python','name_key'=>'offer.py_medium.name','desc_key'=>'offer.py_medium.desc','price'=>'4,99€','period_key'=>'offers.period.month','plan'=>'pythonmedium','free'=>false,'features'=>[['icon'=>'fas fa-memory','text'=>'2 GB RAM DDR5'],['icon'=>'fas fa-hard-drive','text'=>'20 GB SSD NVMe'],['icon'=>'fas fa-microchip','text'=>'500% CPU'],['icon'=>'fas fa-shield-alt','text_key'=>'feat.ddos']]],['category'=>'nodejs','name_key'=>'offer.node_medium.name','desc_key'=>'offer.node_medium.desc','price'=>'2,99€','period_key'=>'offers.period.month','plan'=>'nodejsmedium','free'=>false,'features'=>[['icon'=>'fas fa-memory','text'=>'2 GB RAM'],['icon'=>'fas fa-hard-drive','text'=>'20 GB SSD NVMe'],['icon'=>'fas fa-microchip','text'=>'500% CPU'],['icon'=>'fas fa-bolt','text_key'=>'feat.git_auto']]],['category'=>'java','name_key'=>'offer.java_medium.name','desc_key'=>'offer.java_medium.desc','price'=>'7,99€','period_key'=>'offers.period.month','plan'=>'javamedium','free'=>false,'features'=>[['icon'=>'fas fa-memory','text'=>'2 GB RAM'],['icon'=>'fas fa-hard-drive','text'=>'20 GB SSD NVMe'],['icon'=>'fas fa-microchip','text'=>'500% CPU'],['icon'=>'fas fa-bolt','text_key'=>'feat.fast_install']]]]],'premium'=>['title_key'=>'tier.premium.title','subtitle_key'=>'tier.premium.subtitle','label_key'=>'tier.premium.label','accent'=>'bg-yellow-500','bg'=>'bg-black/15','offers'=>[['category'=>'minecraft','name_key'=>'offer.mc_premium.name','desc_key'=>'offer.mc_premium.desc','price'=>'24,99€','period_key'=>'offers.period.month','plan'=>'minecraft','free'=>false,'features'=>[['icon'=>'fas fa-check','text'=>'20 GB RAM DDR5'],['icon'=>'fas fa-check','text'=>'150 GB SSD NVMe'],['icon'=>'fas fa-check','text'=>'2000% CPU dédié'],['icon'=>'fas fa-check','text_key'=>'feat.support247']]],['category'=>'hytale','name_key'=>'offer.hy_premium.name','desc_key'=>'offer.hy_premium.desc','price'=>'29,99€','period_key'=>'offers.period.month','plan'=>'hytale','free'=>false,'features'=>[['icon'=>'fas fa-check','text'=>'10 GB RAM DDR5'],['icon'=>'fas fa-check','text'=>'100 GB SSD NVMe'],['icon'=>'fas fa-check','text'=>'1400% CPU HF'],['icon'=>'fas fa-check','text_key'=>'feat.priority_sup']]],['category'=>'php','name_key'=>'offer.php_premium.name','desc_key'=>'offer.php_premium.desc','price'=>'19,99€','period_key'=>'offers.period.month','plan'=>'php','free'=>false,'features'=>[['icon'=>'fas fa-check','text'=>'8 GB RAM DDR5'],['icon'=>'fas fa-check','text'=>'30 GB SSD NVMe'],['icon'=>'fas fa-check','text_key'=>'feat.php8'],['icon'=>'fas fa-check','text_key'=>'feat.cron']]],['category'=>'python','name_key'=>'offer.py_premium.name','desc_key'=>'offer.py_premium.desc','price'=>'9,99€','period_key'=>'offers.period.month','plan'=>'python','free'=>false,'features'=>[['icon'=>'fas fa-check','text'=>'4 GB RAM DDR5'],['icon'=>'fas fa-check','text'=>'40 GB SSD NVMe'],['icon'=>'fas fa-check','text'=>'1000% CPU dédié'],['icon'=>'fas fa-check','text_key'=>'feat.support247']]],['category'=>'nodejs','name_key'=>'offer.node_premium.name','desc_key'=>'offer.node_premium.desc','price'=>'5,99€','period_key'=>'offers.period.month','plan'=>'nodejs','free'=>false,'features'=>[['icon'=>'fas fa-check','text'=>'4 GB RAM DDR5'],['icon'=>'fas fa-check','text'=>'40 GB SSD NVMe'],['icon'=>'fas fa-check','text'=>'1000% CPU dédié'],['icon'=>'fas fa-check','text_key'=>'feat.support247']]],['category'=>'java','name_key'=>'offer.java_premium.name','desc_key'=>'offer.java_premium.desc','price'=>'15,99€','period_key'=>'offers.period.month','plan'=>'java','free'=>false,'features'=>[['icon'=>'fas fa-check','text'=>'4 GB RAM DDR5'],['icon'=>'fas fa-check','text'=>'40 GB SSD NVMe'],['icon'=>'fas fa-check','text'=>'1000% CPU dédié'],['icon'=>'fas fa-check','text_key'=>'feat.support247']]]]]];
    ?>

    <!-- category view -->
    <section id="cat-view" class="py-20 px-6">
        <div class="text-center mb-12">
            <h2 class="text-4xl md:text-5xl font-black uppercase tracking-wider mb-3 gradient-text" id="cat-view-title"></h2>
            <div class="h-1 w-20 bg-sky-500 mx-auto rounded-full"></div>
        </div>
        <div class="max-w-7xl mx-auto grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" id="cat-view-grid"></div>
    </section>

    <!-- all sections -->
    <div id="all-sections">
    <?php foreach ($sections as $tier_key => $section): ?>
    <?php $s = $tier_styles[$tier_key]; ?>
    <section class="py-20 px-6 <?php echo $section['bg']; ?>">
        <div class="text-center mb-14">
            <h2 class="text-4xl md:text-5xl font-black uppercase tracking-wider mb-3"><?php echo t($section['title_key']); ?></h2>
            <div class="h-1 w-20 <?php echo $section['accent']; ?> mx-auto rounded-full"></div>
            <p class="text-gray-500 mt-4 text-sm md:text-base"><?php echo t($section['subtitle_key']); ?></p>
        </div>
        <div class="max-w-7xl mx-auto grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($section['offers'] as $offer):
            $cat=$offer['category'];$img=$images[$cat];$icon=$icons[$cat];$popular=!empty($offer['popular']);
            $border_class=$popular?'border-2 border-sky-500 bg-sky-500/[0.02]':"border {$s['card_border']}";
            $route=$offer['free']?'/shop/process_free/?type='.$offer['plan']:'/shop/order/?plan='.$offer['plan'];
            $btn_text=$offer['free']?($is_logged_in?($cat==='php'?t('btn.host_site'):t('btn.deploy')):t('btn.login_to_buy')):($is_logged_in?t('btn.buy'):t('btn.login_to_buy'));
            $link=$is_logged_in?$route:'/login/';
            $price_num=$offer['free']?0:(float)str_replace([',','€'],['.',  ''],$offer['price']);
        ?>
        <div data-category="<?php echo $cat; ?>" data-price="<?php echo $price_num; ?>" class="offer-card glass rounded-3xl <?php echo $border_class; ?> flex flex-col card-hover overflow-hidden relative">
            <?php if($popular): ?><div class="absolute top-3 right-3 z-10 bg-sky-500 text-slate-950 px-3 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider shadow-lg"><?php echo t('btn.popular'); ?></div><?php endif; ?>
            <div class="h-44 w-full bg-cover bg-center relative" style="background-image:url('<?php echo $img; ?>');">
                <div class="absolute inset-0 bg-gradient-to-t from-[#080c14] to-transparent"></div>
                <div class="absolute top-4 left-4 right-4 flex justify-between items-center">
                    <span class="<?php echo $s['badge_bg'].' '.$s['badge_text'].' '.$s['badge_border']; ?> px-3 py-1 rounded-full text-xs font-bold backdrop-blur-md border uppercase tracking-wide"><?php echo t($section['label_key']); ?></span>
                    <i class="<?php echo $icon.' '.$s['icon_color']; ?> text-2xl drop-shadow"></i>
                </div>
            </div>
            <div class="p-6 flex flex-col flex-grow">
                <h3 class="text-xl font-bold text-white"><?php echo t($offer['name_key']); ?></h3>
                <p class="text-gray-500 mt-2 mb-5 text-sm flex-grow"><?php echo t($offer['desc_key']); ?></p>
                <div class="flex items-baseline mb-5">
                    <span class="text-3xl font-black text-white"><?php echo $offer['price']; ?></span>
                    <span class="text-gray-600 text-xs ml-1"><?php echo t($offer['period_key']); ?></span>
                </div>
                <ul class="space-y-2.5 text-gray-400 text-xs border-t border-white/[0.05] pt-4">
                    <?php foreach($offer['features'] as $feat): ?>
                    <li class="flex items-center gap-2"><i class="<?php echo $feat['icon'].' '.$s['icon_color']; ?> w-4 text-center"></i><?php echo isset($feat['text_key'])?t($feat['text_key']):$feat['text']; ?></li>
                    <?php endforeach; ?>
                </ul>
                <a href="<?php echo $link; ?>" class="mt-5 w-full <?php echo $s['btn']; ?> text-slate-950 transition py-3 text-center rounded-xl font-bold block text-sm shadow-lg"><?php echo $btn_text; ?></a>
            </div>
        </div>
        <?php endforeach; ?>
        </div>
    </section>
    <?php endforeach; ?>
    </div>

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

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
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OrinHeberge | Hébergeur</title>
    <link rel="icon" type="image/png" href="/favicon.ico">
    <meta name="description" content="OrinHeberge - Hébergement VPS, Minecraft, PHP et Node.js ultra rapide, gratuit et premium.">
    <meta property="og:title" content="OrinHeberge - Hébergeur VPS, Minecraft, PHP et Node.js">
    <meta property="og:description" content="Serveurs rapides, sécurisés et performants.">
    <meta property="og:image" content="https://heberge.orinstone.deepstone.fr/favicon.png">
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://heberge.orinstone.deepstone.fr">
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
        #cat-view{display:none;}
        #mobileMenu{display:none;}#mobileMenu.active{display:block;}
        
        /* Correction et amélioration des Orbes d'ambiance */
        .hero-glow {position:absolute;border-radius:50%;filter:blur(140px);pointer-events:none;animation:pulse-orb 8s ease-in-out infinite;z-index: 1;}
        @keyframes pulse-orb{0%,100%{opacity:.4;transform:translate(-50%, -50%) scale(1);}50%{opacity:.7;transform:translate(-50%, -50%) scale(1.1);}}
        
        .feat-icon{width:2.5rem;height:2.5rem;border-radius:.875rem;display:flex;align-items:center;justify-content:center;margin-bottom:1.25rem;}
        .offer-badge{position:absolute;top:.875rem;right:.875rem;z-index:10;padding:.2rem .7rem;border-radius:9999px;font-size:.65rem;font-weight:800;letter-spacing:.06em;text-transform:uppercase;}
        .section-divider{height:1px;background:linear-gradient(90deg,transparent,rgba(255,255,255,.06),transparent);}
        .btn-primary{background:linear-gradient(135deg,#0ea5e9,#6366f1);color:#fff;font-weight:700;border-radius:.875rem;transition:opacity .2s,transform .15s;}
        .btn-primary:hover{opacity:.9;transform:translateY(-1px);}
        .trust-badge{display:inline-flex;align-items:center;gap:.5rem;padding:.35rem .9rem;border-radius:9999px;border:1px solid rgba(255,255,255,.08);background:rgba(255,255,255,.03);font-size:.72rem;font-weight:600;color:#9ca3af;}
    </style>
    <script>
        if('serviceWorker' in navigator){window.addEventListener('load',()=>{navigator.serviceWorker.register('/sw.js').catch(()=>{});});}
        function toggleMenu(){document.getElementById('mobileMenu').classList.toggle('active');}
        function filterCategory(id){
            document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));
            document.getElementById('tab-'+id).classList.add('active');
            const cv=document.getElementById('cat-view');
            const al=document.getElementById('all-sections');
            if(!cv || !al) return;
            if(id==='all'){cv.style.display='none';al.style.display='block';return;}
            al.style.display='none';cv.style.display='block';
            const labels={minecraft:'Minecraft',hytale:'Hytale',php:'Web / PHP',python:'Python',nodejs:'Node.js',java:'Java'};
            document.getElementById('cat-view-title').textContent=labels[id]||id;
            const grid=document.getElementById('cat-view-grid');
            if(!grid) return;
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
                <a href="#offres" class="bg-sky-600 hover:bg-sky-500 text-white px-8 py-4 rounded-2xl font-bold transition shadow-xl shadow-sky-900/30 text-sm flex items-center gap-2 shared-btn">
                    <i class="fas fa-rocket"></i> Voir les offres
                </a>
                <a href="<?php echo $is_logged_in ? '/client/servers/' : '/register/'; ?>" class="glass hover:bg-white/[0.07] px-8 py-4 rounded-2xl font-bold transition text-sm flex items-center gap-2 shared-btn">
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
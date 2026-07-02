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
        #mobileMenu{display:none;}#mobileMenu.active{display:block;}
        
        .hero-glow {position:absolute;border-radius:50%;filter:blur(140px);pointer-events:none;animation:pulse-orb 8s ease-in-out infinite;z-index: 1;}
        @keyframes pulse-orb{0%,100%{opacity:.4;transform:translate(-50%, -50%) scale(1);}50%{opacity:.7;transform:translate(-50%, -50%) scale(1.1);}}
        
        .offer-badge{position:absolute;top:.875rem;right:.875rem;z-index:10;padding:.2rem .7rem;border-radius:9999px;font-size:.65rem;font-weight:800;letter-spacing:.06em;text-transform:uppercase;}
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
            const labels={minecraft:'Minecraft',php:'Web / PHP',python:'Python',nodejs:'Node.js'};
            document.getElementById('cat-view-title').textContent=labels[id]||id;
            const grid=document.getElementById('cat-view-grid');
            if(!grid) return;
            const cards=Array.from(document.querySelectorAll('#all-sections .offer-card[data-category="'+id+'"]'));
            grid.innerHTML='';
            cards.forEach(c=>{
                const cl=c.cloneNode(true);
                cl.style.display='flex';
                grid.appendChild(cl);
            });
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
        <div class="text-center mb-10">
            <h2 class="text-3xl md:text-4xl font-black mb-3">Choisissez votre <span class="gradient-text">Environnement</span></h2>
            <p class="text-gray-500 text-sm">Déployez votre projet instantanément sur l'une de nos configurations.</p>
        </div>

        <div class="flex justify-center gap-2 overflow-x-auto pb-6 mb-8 mask-gradient">
            <button id="tab-all" onclick="filterCategory('all')" class="tab-btn active">Tout voir</button>
            <button id="tab-minecraft" onclick="filterCategory('minecraft')" class="tab-btn"><i class="text-green-400 fab fa-cube mr-1"></i> Minecraft</button>
            <button id="tab-php" onclick="filterCategory('php')" class="tab-btn"><i class="text-blue-400 fab fa-php mr-1"></i> Web / PHP</button>
            <button id="tab-nodejs" onclick="filterCategory('nodejs')" class="tab-btn"><i class="text-green-500 fab fa-node-js mr-1"></i> Node.js</button>
            <button id="tab-python" onclick="filterCategory('python')" class="tab-btn"><i class="text-yellow-400 fab fa-python mr-1"></i> Python</button>
        </div>

        <div id="cat-view">
            <h3 class="text-xl font-bold mb-6 flex items-center gap-2"><span class="h-2 w-2 rounded-full bg-sky-400"></span> Offres <span id="cat-view-title" class="text-sky-400"></span></h3>
            <div id="cat-view-grid" class="grid grid-cols-1 md:grid-cols-3 gap-6"></div>
        </div>

        <div id="all-sections" class="space-y-12">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="offer-card glass card-hover p-6 rounded-2xl border border-white/[0.05] relative flex flex-col justify-between" data-category="minecraft" data-price="0">
                    <span class="offer-badge bg-green-500/20 text-green-400 border border-green-500/30">Découverte</span>
                    <div>
                        <h4 class="text-xl font-bold text-white mb-1">Minecraft Free</h4>
                        <div class="text-3xl font-black mb-4">0€ <span class="text-xs font-normal text-gray-500">/mois</span></div>
                        <ul class="space-y-2.5 text-xs text-gray-400 mb-6">
                            <li><i class="fas fa-check text-green-400 mr-2"></i> 2 Go RAM DDR5</li>
                            <li><i class="fas fa-check text-green-400 mr-2"></i> 1 vCPU (Ryzen)</li>
                            <li><i class="fas fa-check text-green-400 mr-2"></i> 10 Go Stockage NVMe</li>
                            <li><i class="fas fa-check text-green-400 mr-2"></i> Protection Anti-DDoS</li>
                        </ul>
                    </div>
                    <a href="/register/" class="w-full text-center py-3 rounded-xl bg-white/[0.04] hover:bg-white/[0.08] border border-white/[0.05] text-xs font-bold transition">Commander</a>
                </div>

                <div class="offer-card glass card-hover p-6 rounded-2xl border border-white/[0.05] relative flex flex-col justify-between" data-category="nodejs" data-price="2">
                    <span class="offer-badge bg-sky-500/20 text-sky-400 border border-sky-500/30">Populaire</span>
                    <div>
                        <h4 class="text-xl font-bold text-white mb-1">Node.js Starter</h4>
                        <div class="text-3xl font-black mb-4">2.49€ <span class="text-xs font-normal text-gray-500">/mois</span></div>
                        <ul class="space-y-2.5 text-xs text-gray-400 mb-6">
                            <li><i class="fas fa-check text-sky-400 mr-2"></i> 4 Go RAM DDR5</li>
                            <li><i class="fas fa-check text-sky-400 mr-2"></i> 2 vCPU Haute Fréquence</li>
                            <li><i class="fas fa-check text-sky-400 mr-2"></i> 30 Go Stockage NVMe</li>
                            <li><i class="fas fa-check text-sky-400 mr-2"></i> Bases de données MySQL illimitées</li>
                        </ul>
                    </div>
                    <a href="/register/" class="w-full text-center py-3 rounded-xl bg-gradient-to-r from-sky-600 to-indigo-600 text-white text-xs font-bold transition shadow-lg shadow-sky-900/20">Acheter maintenant</a>
                </div>

                <div class="offer-card glass card-hover p-6 rounded-2xl border border-white/[0.05] relative flex flex-col justify-between" data-category="php" data-price="0">
                    <span class="offer-badge bg-purple-500/20 text-purple-400 border border-purple-500/30">Web Cloud</span>
                    <div>
                        <h4 class="text-xl font-bold text-white mb-1">Hébergement Web</h4>
                        <div class="text-3xl font-black mb-4">0€ <span class="text-xs font-normal text-gray-500">/sans limite</span></div>
                        <ul class="space-y-2.5 text-xs text-gray-400 mb-6">
                            <li><i class="fas fa-check text-purple-400 mr-2"></i> PHP 8.1 / 8.2 / 8.3 supportés</li>
                            <li><i class="fas fa-check text-purple-400 mr-2"></i> Certificats SSL Let's Encrypt</li>
                            <li><i class="fas fa-check text-purple-400 mr-2"></i> Accès FTP / Panel complet</li>
                            <li><i class="fas fa-check text-purple-400 mr-2"></i> Sous-domaine gratuit fourni</li>
                        </ul>
                    </div>
                    <a href="/register/" class="w-full text-center py-3 rounded-xl bg-white/[0.04] hover:bg-white/[0.08] border border-white/[0.05] text-xs font-bold transition">Lancer mon site</a>
                </div>
            </div>
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
                        <img src="https://images.unsplash.com/photo-1535713875002-d1d0cf377fde?auto=format&fit=crop&w=150&h=150&q=80" alt="Avatar" class="w-full h-full object-cover rounded-full bg-[#060911]">
                    </div>
                    <span class="absolute bottom-0 right-1 h-3 w-3 rounded-full bg-green-400 border-2 border-[#060911]"></span>
                </div>
                <h4 class="font-bold text-white text-lg">Alexandre</h4>
                <span class="text-xs font-semibold px-2.5 py-0.5 rounded-full bg-sky-500/10 text-sky-400 border border-sky-500/20 mt-1 mb-3">Fondateur & Dev SysAdmin</span>
                <p class="text-gray-500 text-xs leading-relaxed max-w-xs">Garant de l'architecture serveur et du développement global du panel.</p>
                <div class="flex gap-3 mt-4 text-gray-400 text-sm">
                    <a href="#" class="hover:text-white transition"><i class="fab fa-github"></i></a>
                    <a href="#" class="hover:text-sky-400 transition"><i class="fab fa-twitter"></i></a>
                </div>
            </div>

            <div class="glass p-6 rounded-2xl border border-white/[0.05] text-center flex flex-col items-center">
                <div class="relative mb-4">
                    <div class="w-20 h-20 bg-gradient-to-tr from-purple-500 to-pink-500 rounded-full p-0.5 shadow-xl">
                        <img src="https://images.unsplash.com/photo-1570295999919-56ceb5ecca61?auto=format&fit=crop&w=150&h=150&q=80" alt="Avatar" class="w-full h-full object-cover rounded-full bg-[#060911]">
                    </div>
                    <span class="absolute bottom-0 right-1 h-3 w-3 rounded-full bg-green-400 border-2 border-[#060911]"></span>
                </div>
                <h4 class="font-bold text-white text-lg">Maxime</h4>
                <span class="text-xs font-semibold px-2.5 py-0.5 rounded-full bg-purple-500/10 text-purple-400 border border-purple-500/20 mt-1 mb-3">Développeur UI/UX</span>
                <p class="text-gray-500 text-xs leading-relaxed max-w-xs">Conçoit l'interface utilisateur pour la rendre fluide et accessible à tous.</p>
                <div class="flex gap-3 mt-4 text-gray-400 text-sm">
                    <a href="#" class="hover:text-white transition"><i class="fab fa-github"></i></a>
                    <a href="#" class="hover:text-purple-400 transition"><i class="fab fa-discord"></i></a>
                </div>
            </div>

            <div class="glass p-6 rounded-2xl border border-white/[0.05] text-center flex flex-col items-center sm:col-span-2 lg:col-span-1">
                <div class="relative mb-4">
                    <div class="w-20 h-20 bg-gradient-to-tr from-amber-400 to-orange-500 rounded-full p-0.5 shadow-xl">
                        <img src="https://images.unsplash.com/photo-1580489944761-15a19d654956?auto=format&fit=crop&w=150&h=150&q=80" alt="Avatar" class="w-full h-full object-cover rounded-full bg-[#060911]">
                    </div>
                    <span class="absolute bottom-0 right-1 h-3 w-3 rounded-full bg-green-400 border-2 border-[#060911]"></span>
                </div>
                <h4 class="font-bold text-white text-lg">Sarah</h4>
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
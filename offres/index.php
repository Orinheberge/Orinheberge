<?php
ini_set('display_errors', 1); error_reporting(E_ALL);
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/lang.php';

$is_logged_in = isset($_SESSION['user_id']);
$sections = [
    'free'    => ['title_key'=>'tier.free.title',    'subtitle_key'=>'tier.free.subtitle',    'label_key'=>'tier.free.label',    'accent'=>'bg-green-500',  'bg'=>'bg-white/[0.01] border-y border-white/5', 'offers'=>[]],
    'basic'   => ['title_key'=>'tier.basic.title',   'subtitle_key'=>'tier.basic.subtitle',   'label_key'=>'tier.basic.label',   'accent'=>'bg-blue-500',   'bg'=>'bg-black/10', 'offers'=>[]],
    'medium'  => ['title_key'=>'tier.medium.title',  'subtitle_key'=>'tier.medium.subtitle',  'label_key'=>'tier.medium.label',  'accent'=>'bg-purple-500', 'bg'=>'bg-white/[0.02] border-y border-white/5', 'offers'=>[]],
    'premium' => ['title_key'=>'tier.premium.title', 'subtitle_key'=>'tier.premium.subtitle', 'label_key'=>'tier.premium.label', 'accent'=>'bg-yellow-500', 'bg'=>'bg-black/20', 'offers'=>[]],
    'mythic' => ['title_key'=>'tier.mythic.title', 'subtitle_key'=>'tier.mythic.subtitle', 'label_key'=>'tier.mythic.label', 'accent'=>'bg-rose-500', 'bg'=>'bg-black/20', 'offers'=>[]],

];
$dynamic_categories = [];

try {
    $pdo = new PDO('mysql:host=localhost;dbname=s43_orinheberge;charset=utf8mb4','root','1504',[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT=>3
    ]);

    if ($is_logged_in) {
        $u = $pdo->prepare('SELECT pseudo,firstname,avatar FROM users WHERE id=? LIMIT 1');
        $u->execute([$_SESSION['user_id']]);
        $ud = $u->fetch();
        if ($ud) {
            $_SESSION['username'] = !empty($ud['pseudo']) ? $ud['pseudo'] : $ud['firstname'];
            $_SESSION['avatar']   = $ud['avatar'];
        }
    }

    // Catégories actives
    $cq = $pdo->query('SELECT DISTINCT category_slug,name_key,icon,image_url FROM categories_products WHERE is_active=1 GROUP BY category_slug ORDER BY sort_order ASC');
    while ($r = $cq->fetch()) {
        $dynamic_categories[$r['category_slug']] = ['name_key'=>$r['name_key'],'icon'=>$r['icon'],'image_url'=>$r['image_url']];
    }

    // Produits liés aux catégories actives
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
        $tier = strpos($slug,'free')!==false ? 'free' : (strpos($slug,'basic')!==false ? 'basic' : (strpos($slug,'medium')!==false ? 'medium' : 'premium'));
        $rt   = $pr['ram']  >= 1024 ? number_format($pr['ram']/1024,0).' GB RAM DDR5' : $pr['ram'].' MB RAM';
        $dt   = $pr['disk'] >= 1024 ? number_format($pr['disk']/1024,0).' GB SSD NVMe' : $pr['disk'].' MB SSD';
        $sections[$tier]['offers'][] = [
            'category'    => $cat,
            'slug'        => $slug,
            'name'        => $pr['name'],
            'desc'        => $pr['description'] ?? '',
            'price'       => $pr['type']==='free' ? '0€' : number_format($pr['price'],2,',','').'€',
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
} catch (PDOException $e) {}

function tierStyle(string $t): array {
    $styles = [
        'free'    => ['bb'=>'bg-green-500/20',  'bt'=>'text-green-400',  'bbd'=>'border-green-500/30',  'ic'=>'text-green-400',  'cb'=>'border-white/10',      'btn'=>'bg-green-500 hover:bg-green-400'],
        'basic'   => ['bb'=>'bg-blue-500/20',   'bt'=>'text-blue-400',   'bbd'=>'border-blue-500/30',   'ic'=>'text-blue-400',   'cb'=>'border-blue-400/20',   'btn'=>'bg-blue-500 hover:bg-blue-400'],
        'medium'  => ['bb'=>'bg-purple-500/20', 'bt'=>'text-purple-400', 'bbd'=>'border-purple-500/30', 'ic'=>'text-purple-400', 'cb'=>'border-purple-400/20', 'btn'=>'bg-purple-500 hover:bg-purple-400'],
        'premium' => ['bb'=>'bg-yellow-500/20', 'bt'=>'text-yellow-400', 'bbd'=>'border-yellow-500/30', 'ic'=>'text-yellow-400', 'cb'=>'border-yellow-400/20', 'btn'=>'bg-yellow-500 hover:bg-yellow-400'],
        'mythic'  => [
        'bb'  => 'bg-rose-500/20',   
        'bt'  => 'text-rose-400',   
        'bbd' => 'border-rose-500/30',   
        'ic'  => 'text-rose-400',   
        'cb'  => 'border-rose-400/20',   
        'btn' => 'bg-rose-500 hover:bg-rose-400 text-white'
    ],
    ];
    return $styles[$t] ?? ['bb'=>'bg-gray-500/20','bt'=>'text-gray-400','bbd'=>'border-gray-500/30','ic'=>'text-gray-400','cb'=>'border-white/10','btn'=>'bg-gray-500 hover:bg-gray-400'];
}
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>OrinHeberge | Nos Offres</title>
<link rel="icon" type="image/png" href="/favicon.ico">
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

<!-- Balises de base -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nos Offres - OrinHeberge | Hébergement VPS, Minecraft, PHP et Node.js</title>
    <meta name="description" content="Découvrez nos offres d'hébergement VPS, Minecraft, PHP et Node.js. Des solutions gratuites et premium adaptées à tous vos projets.">
    <meta name="keywords" content="hébergement VPS, serveur Minecraft, hébergement PHP, Node.js, VPS gratuit, serveur dédié, hosting, cloud">
    <meta name="author" content="OrinHeberge">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="https://heberge.orinstone.deepstone.fr/offres/">

    <!-- Open Graph / Facebook -->
    <meta property="og:locale" content="fr_FR">
    <meta property="og:type" content="website">
    <meta property="og:title" content="Nos Offres - OrinHeberge | Hébergement VPS, Minecraft, PHP et Node.js">
    <meta property="og:description" content="Trouvez l'offre idéale pour vos projets. Des serveurs rapides, sécurisés et à prix compétitifs.">
    <meta property="og:url" content="https://heberge.orinstone.deepstone.fr/offres/">
    <meta property="og:site_name" content="OrinHeberge">
    <meta property="og:image" content="https://heberge.orinstone.deepstone.fr/favicon.png">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="OrinHeberge - Nos offres d'hébergement">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:site" content="@OrinHeberge">
    <meta name="twitter:creator" content="@OrinHeberge">
    <meta name="twitter:title" content="Nos Offres - OrinHeberge | Hébergement VPS, Minecraft, PHP et Node.js">
    <meta name="twitter:description" content="Trouvez l'offre idéale pour vos projets. Des serveurs rapides, sécurisés et à prix compétitifs.">
    <meta name="twitter:image" content="https://heberge.orinstone.deepstone.fr/favicon.png">
    <meta name="twitter:image:alt" content="OrinHeberge - Nos offres d'hébergement">

    <!-- Autres balises SEO -->
    <meta name="theme-color" content="#6366f1">
    <meta name="msapplication-TileColor" content="#6366f1">
    <link rel="icon" type="image/png" href="https://heberge.orinstone.deepstone.fr/favicon.png">
    <link rel="apple-touch-icon" href="https://heberge.orinstone.deepstone.fr/favicon.png">

<link rel="manifest" href="/manifest.json">
<style>
body{background:#0b0f19;scroll-behavior:smooth;}
.glass{background:rgba(255,255,255,.04);backdrop-filter:blur(14px);border:1px solid rgba(255,255,255,.08);}
.gradient-text{background:linear-gradient(90deg,#38bdf8,#818cf8);-webkit-background-clip:text;-webkit-text-fill-color:transparent;}
.card-hover{transition:transform .3s,box-shadow .3s;}
.card-hover:hover{transform:translateY(-6px);box-shadow:0 20px 40px rgba(0,0,0,.3);}
.tab-btn{padding:.55rem 1.25rem;border-radius:9999px;font-size:.82rem;font-weight:600;transition:all .2s;border:1px solid rgba(255,255,255,.1);background:rgba(255,255,255,.04);color:#9ca3af;cursor:pointer;white-space:nowrap;display:inline-flex;align-items:center;gap:.4rem;}
.tab-btn:hover{background:rgba(255,255,255,.08);color:#e5e7eb;}
.tab-btn.active{background:rgba(56,189,248,.15);border-color:rgba(56,189,248,.4);color:#38bdf8;box-shadow:0 0 15px rgba(56,189,248,.1);}
#cat-view{display:none;}
.cat-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:1.5rem;}
</style>
<script>
const catLabels = <?php echo json_encode(array_map(fn($c)=>t($c['name_key']),$dynamic_categories),JSON_HEX_TAG|JSON_HEX_AMP); ?>
function filterCategory(id){
    document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));
    document.getElementById('tab-'+id)?.classList.add('active');
    const cv=document.getElementById('cat-view'),as=document.getElementById('all-sections');
    if(id==='all'){cv.style.display='none';as.style.display='block';return;}
    as.style.display='none';cv.style.display='block';
    document.getElementById('cat-view-title').textContent=catLabels[id]||id;
    const grid=document.getElementById('cat-view-grid');grid.innerHTML='';
    const cards=[...document.querySelectorAll('#all-sections .offer-card[data-category="'+id+'"]')];
    if(!cards.length){grid.innerHTML='<div class="col-span-full py-16 text-center text-gray-500"><i class="fas fa-box-open text-3xl mb-3 block opacity-30"></i>Aucune offre disponible pour le moment.</div>';return;}
    cards.forEach(c=>{const cl=c.cloneNode(true);cl.style.display='flex';grid.appendChild(cl);});
}
window.addEventListener('DOMContentLoaded',()=>filterCategory('all'));
</script>
</head>
<body class="text-gray-200 font-sans min-h-screen flex flex-col justify-between antialiased">

<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/navbar.php'; ?>

<main class="flex-grow">
  <header class="text-center py-16 px-6 relative overflow-hidden">
    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[500px] h-[500px] bg-sky-500/10 rounded-full blur-[120px] pointer-events-none"></div>
    <div class="inline-flex items-center gap-2 bg-sky-500/10 text-sky-400 border border-sky-500/20 px-4 py-1.5 rounded-full text-xs font-semibold mb-5">
      <i class="fas fa-tags"></i> Nos offres
    </div>
    <h1 class="text-5xl md:text-7xl font-black tracking-tight leading-none gradient-text mb-4"><?php echo t('offers.title'); ?></h1>
    <p class="text-gray-400 max-w-xl mx-auto text-lg"><?php echo t('offers.subtitle'); ?></p>
    <div class="max-w-5xl mx-auto flex flex-wrap justify-center gap-2.5 px-4 mt-10">
      <button onclick="filterCategory('all')" id="tab-all" class="tab-btn active">
        <i class="fas fa-th-large text-xs"></i> <?php echo t('offers.tab.all'); ?>
      </button>
      <?php foreach($dynamic_categories as $slug => $ci): ?>
      <button onclick="filterCategory('<?php echo htmlspecialchars($slug); ?>')" id="tab-<?php echo htmlspecialchars($slug); ?>" class="tab-btn">
        <i class="<?php echo htmlspecialchars($ci['icon']); ?> text-xs"></i> <?php echo t($ci['name_key']); ?>
      </button>
      <?php endforeach; ?>
    </div>
  </header>

  <section id="cat-view" class="py-16 px-6">
    <div class="text-center mb-12">
      <h2 class="text-4xl md:text-5xl font-black uppercase tracking-wider mb-3 gradient-text" id="cat-view-title"></h2>
      <div class="h-1 w-20 bg-sky-500 mx-auto rounded-full"></div>
    </div>
    <div class="max-w-7xl mx-auto cat-grid" id="cat-view-grid"></div>
  </section>

  <div id="all-sections">
  <?php foreach($sections as $tier_key => $tier):
    if(empty($tier['offers'])) continue;
    $s = tierStyle($tier_key);
  ?>
  <section class="py-20 px-6 <?php echo $tier['bg']; ?>">
    <div class="text-center mb-14">
      <h2 class="text-4xl md:text-5xl font-black uppercase tracking-wider mb-3"><?php echo t($tier['title_key']); ?></h2>
      <div class="h-1 w-20 <?php echo $tier['accent']; ?> mx-auto rounded-full"></div>
      <p class="text-gray-400 mt-4"><?php echo t($tier['subtitle_key']); ?></p>
    </div>
    <div class="max-w-7xl mx-auto grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
    <?php foreach($tier['offers'] as $offer):
      $is_free  = $offer['free'];
      $btn_text = $is_logged_in ? ($is_free ? t('btn.deploy') : t('btn.buy')) : t('btn.login_to_buy');
      $price_num = $is_free ? 0 : $offer['price_value'];
    ?>
    <div data-category="<?php echo htmlspecialchars($offer['category']); ?>" data-price="<?php echo $price_num; ?>"
         class="offer-card glass rounded-2xl border <?php echo $s['cb']; ?> flex flex-col card-hover overflow-hidden relative">
      <div class="h-36 w-full bg-cover bg-center relative" style="background-image:url('<?php echo htmlspecialchars($offer['image_url']); ?>')">
        <div class="absolute inset-0 bg-gradient-to-t from-[#070a13] via-transparent to-transparent"></div>
        <div class="absolute top-3 left-3 right-3 flex justify-between items-center">
          <span class="<?php echo $s['bb'].' '.$s['bt'].' '.$s['bbd']; ?> px-2.5 py-0.5 rounded-full text-[11px] font-bold border uppercase tracking-wide">
            <?php echo t($tier['label_key']); ?>
          </span>
          <i class="<?php echo htmlspecialchars($offer['icon']).' '.$s['ic']; ?> text-xl drop-shadow"></i>
        </div>
      </div>
      <div class="p-5 flex flex-col flex-grow">
        <h3 class="text-base font-bold text-white mb-1"><?php echo htmlspecialchars($offer['name']); ?></h3>
        <p class="text-gray-400 text-xs flex-grow mb-4 leading-relaxed"><?php echo htmlspecialchars($offer['desc']); ?></p>
        <div class="flex items-baseline mb-4">
          <span class="text-2xl font-black text-white"><?php echo $offer['price']; ?></span>
          <span class="text-gray-500 text-xs ml-1"><?php echo t($offer['period_key']); ?></span>
        </div>
        <ul class="space-y-2 text-xs text-gray-300 border-t border-white/5 pt-3 mb-4">
          <?php foreach($offer['features'] as $f): ?>
          <li><i class="<?php echo $f['icon'].' '.$s['ic']; ?> mr-2 w-3"></i><?php echo htmlspecialchars($f['text']); ?></li>
          <?php endforeach; ?>
        </ul>
        <?php if($is_logged_in): ?>
        <form method="post" action="/shop/cart/">
          <input type="hidden" name="action" value="add_item">
          <input type="hidden" name="slug"   value="<?php echo htmlspecialchars($offer['slug']); ?>">
          <input type="hidden" name="name"   value="<?php echo htmlspecialchars($offer['name']); ?>">
          <input type="hidden" name="price"  value="<?php echo $offer['price_value']; ?>">
          <button type="submit" class="w-full <?php echo $s['btn']; ?> text-slate-950 font-bold py-2.5 rounded-xl text-sm">
            <?php echo $btn_text; ?>
          </button>
        </form>
        <?php else: ?>
        <a href="/login/" class="w-full <?php echo $s['btn']; ?> text-slate-950 font-bold py-2.5 rounded-xl text-sm text-center block">
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
</main>

<div class="fixed bottom-6 right-6 z-50">
  <a href="/discord/" target="_blank" class="bg-[#5865F2] hover:bg-[#4752C4] text-white px-5 py-3.5 rounded-full font-bold flex items-center gap-2 shadow-2xl hover:scale-105 transform duration-200 transition">
    <i class="fab fa-discord text-xl"></i>
    <span class="hidden sm:inline text-sm"><?php echo t('discord.help'); ?></span>
  </a>
</div>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/inc/footer.php'; ?>
</body>
<script src="/inc/navbar.js"></script>
<script src="https://<?php echo $_SERVER['HTTP_HOST']; ?>/inc/navbar.js?v=<?php echo filemtime($_SERVER['DOCUMENT_ROOT'] . '/inc/navbar.js'); ?>"></script>

</html>

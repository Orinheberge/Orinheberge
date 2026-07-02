<?php
ini_set('display_errors', 1); error_reporting(E_ALL);
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/lang.php';

$is_logged_in = isset($_SESSION['user_id']);
$db_status    = false;
$sections     = ['free'=>['title_key'=>'tier.free.title','subtitle_key'=>'tier.free.subtitle','label_key'=>'tier.free.label','accent'=>'bg-green-500','bg'=>'bg-white/[0.01] border-y border-white/5','offers'=>[]],'basic'=>['title_key'=>'tier.basic.title','subtitle_key'=>'tier.basic.subtitle','label_key'=>'tier.basic.label','accent'=>'bg-blue-500','bg'=>'bg-black/10','offers'=>[]],'medium'=>['title_key'=>'tier.medium.title','subtitle_key'=>'tier.medium.subtitle','label_key'=>'tier.medium.label','accent'=>'bg-purple-500','bg'=>'bg-white/[0.02] border-y border-white/5','offers'=>[]],'premium'=>['title_key'=>'tier.premium.title','subtitle_key'=>'tier.premium.subtitle','label_key'=>'tier.premium.label','accent'=>'bg-yellow-500','bg'=>'bg-black/20','offers'=>[]]];
$dynamic_categories = [];

try {
    $pdo = new PDO('mysql:host=localhost;dbname=s43_orinheberge;charset=utf8mb4','root','1504',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_TIMEOUT=>3]);
    $db_status = true;

    if ($is_logged_in) {
        $u = $pdo->prepare('SELECT pseudo,firstname,avatar FROM users WHERE id=? LIMIT 1');
        $u->execute([$_SESSION['user_id']]);
        $ud = $u->fetch();
        if ($ud) { $_SESSION['username']=!empty($ud['pseudo'])?$ud['pseudo']:$ud['firstname']; $_SESSION['avatar']=$ud['avatar']; }
    }

    // Catégories actives
    $cq = $pdo->query('SELECT DISTINCT category_slug,name_key,icon,image_url FROM categories_products WHERE is_active=1 GROUP BY category_slug ORDER BY sort_order ASC');
    while ($r = $cq->fetch()) $dynamic_categories[$r['category_slug']] = ['name_key'=>$r['name_key'],'icon'=>$r['icon'],'image_url'=>$r['image_url']];

    // Produits
    $stmt = $pdo->query("SELECT p.*,cp.category_slug,cp.icon AS cat_icon,cp.image_url AS cat_image FROM categories_products cp JOIN products p ON p.id=cp.product_id WHERE cp.is_active=1 AND p.is_active=1 ORDER BY p.sort_order,p.id");
    foreach ($stmt->fetchAll() as $pr) {
        $slug = $pr['slug']; $cat = strtolower($pr['category_slug']);
        $tier = strpos($slug,'free')!==false?'free':(strpos($slug,'basic')!==false?'basic':(strpos($slug,'medium')!==false?'medium':'premium'));
        $sc   = ['minecraft'=>'mc','python'=>'py','nodejs'=>'node','fivem'=>'fivem','hytale'=>'hytale','php'=>'php','java'=>'java'][$cat] ?? $cat;
        $ram_text  = $pr['ram']>=1024?number_format($pr['ram']/1024,0).' GB RAM DDR5':$pr['ram'].' MB RAM';
        $disk_text = $pr['disk']>=1024?number_format($pr['disk']/1024,0).' GB SSD NVMe':$pr['disk'].' MB SSD';
        $sections[$tier]['offers'][] = ['category'=>$cat,'slug'=>$slug,'name'=>$pr['name'],'desc'=>$pr['description']??'','price'=>$pr['type']==='free'?'0€':number_format($pr['price'],2,',','').'€','price_value'=>(float)$pr['price'],'period_key'=>$pr['type']==='free'?'offers.period.free':'offers.period.month','free'=>$pr['type']==='free','icon'=>$pr['cat_icon']??'fas fa-server','image_url'=>$pr['cat_image']??'','features'=>[['icon'=>'fas fa-memory','text'=>$ram_text],['icon'=>'fas fa-hard-drive','text'=>$disk_text],['icon'=>'fas fa-microchip','text'=>$pr['cpu'].'% CPU'],['icon'=>'fas fa-database','text'=>$pr['databases'].' Database(s)']]];
    }
} catch (PDOException $e) {}

function tierStyle($t) {
    return ['free'=>['bg_badge'=>'bg-green-500/20','text_badge'=>'text-green-400','border_badge'=>'border-green-500/30','icon_color'=>'text-green-400','card_border'=>'border-white/10','btn'=>'bg-green-500 hover:bg-green-400'],'basic'=>['bg_badge'=>'bg-blue-500/20','text_badge'=>'text-blue-400','border_badge'=>'border-blue-500/30','icon_color'=>'text-blue-400','card_border'=>'border-blue-400/20','btn'=>'bg-blue-500 hover:bg-blue-400'],'medium'=>['bg_badge'=>'bg-purple-500/20','text_badge'=>'text-purple-400','border_badge'=>'border-purple-500/30','icon_color'=>'text-purple-400','card_border'=>'border-purple-400/20','btn'=>'bg-purple-500 hover:bg-purple-400'],'premium'=>['bg_badge'=>'bg-yellow-500/20','text_badge'=>'text-yellow-400','border_badge'=>'border-yellow-500/30','icon_color'=>'text-yellow-400','card_border'=>'border-yellow-400/20','btn'=>'bg-yellow-500 hover:bg-yellow-400']][$t] ?? [];
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
<link rel="manifest" href="/manifest.json">
<style>
body{background:#0b0f19;scroll-behavior:smooth;}
.glass{background:rgba(255,255,255,.04);backdrop-filter:blur(14px);border:1px solid rgba(255,255,255,.08);}
.gradient-text{background:linear-gradient(90deg,#38bdf8,#818cf8);-webkit-background-clip:text;-webkit-text-fill-color:transparent;}
.card-hover{transition:transform .3s,box-shadow .3s;}
.card-hover:hover{transform:translateY(-6px);box-shadow:0 20px 40px rgba(0,0,0,.3);}
#mobileMenu{display:none;}#mobileMenu.active{display:block;}
.tab-btn{padding:.55rem 1.25rem;border-radius:9999px;font-size:.82rem;font-weight:600;transition:all .2s;border:1px solid rgba(255,255,255,.1);background:rgba(255,255,255,.04);color:#9ca3af;cursor:pointer;white-space:nowrap;display:inline-flex;align-items:center;gap:.4rem;}
.tab-btn:hover{background:rgba(255,255,255,.08);color:#e5e7eb;}
.tab-btn.active{background:rgba(56,189,248,.15);border-color:rgba(56,189,248,.4);color:#38bdf8;box-shadow:0 0 15px rgba(56,189,248,.1);}
#cat-view{display:none;}
.cat-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:1.5rem;}
</style>
<script>
const catLabels = <?php echo json_encode(array_map(fn($c)=>t($c['name_key']),$dynamic_categories),JSON_HEX_TAG|JSON_HEX_AMP); ?>;
function toggleMenu(){document.getElementById('mobileMenu').classList.toggle('active');}
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

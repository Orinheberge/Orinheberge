<?php
/**
 * /client/servers/upgrade/ — Page d'upgrade d'offre pour un serveur existant
 * Le client choisit un plan supérieur, la diff de prix est calculée,
 * puis il est redirigé vers le checkout Stripe pour payer la différence.
 */
ini_set('display_errors', 1); error_reporting(E_ALL);
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/lang.php';
if (!isset($_SESSION['user_id'])) { header('Location: /login/'); exit(); }

try {
    $pdo = new PDO('mysql:host=localhost;dbname=s43_orinheberge;charset=utf8mb4','root','1504',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
} catch(PDOException $e){ die('Erreur BDD'); }

$cfg = [];
foreach($pdo->query('SELECT `key`,`value` FROM settings') as $r) $cfg[$r['key']]=$r['value'];
$panel_url      = $cfg['panel_url']     ?? 'https://panel.orinstone.deepstone.fr';
$api_key_admin  = $cfg['api_key_admin'] ?? '';
$headers_admin  = ["Authorization: Bearer $api_key_admin","Accept: application/vnd.pterodactyl.v1+json","Content-Type: application/json"];

$u = $pdo->prepare('SELECT pseudo,firstname,avatar FROM users WHERE id=? LIMIT 1');
$u->execute([$_SESSION['user_id']]);
$ud=$u->fetch();
if($ud){ $_SESSION['username']=!empty($ud['pseudo'])?$ud['pseudo']:$ud['firstname']; $_SESSION['avatar']=$ud['avatar']; }

// Serveur à upgrader
$uuid   = trim($_GET['uuid'] ?? '');
$flash  = '';

if(!$uuid){ header('Location: /client/servers/'); exit(); }

$srv_stmt = $pdo->prepare('SELECT o.*,p.slug AS product_slug,p.id AS pid FROM orders o LEFT JOIN products p ON p.id=o.product_id WHERE o.uuid=? AND o.user_id=? LIMIT 1');
$srv_stmt->execute([$uuid, $_SESSION['user_id']]);
$server = $srv_stmt->fetch();
if(!$server){ header('Location: /client/servers/'); exit(); }

// Catégorie du serveur (on déduit depuis le nom ou le slug du produit)
$name_lower = strtolower($server['service_name'] ?? '');
$cat = 'unknown';
foreach(['minecraft','fivem','hytale','php','python','nodejs','java'] as $c){
    if(str_contains($name_lower,$c) || str_contains($server['product_slug']??'',$c)){ $cat=$c; break; }
}

// Produits disponibles pour cette catégorie, plus chers que l'actuel
$prod_stmt = $pdo->query("
    SELECT p.*,cp.category_slug
    FROM categories_products cp
    JOIN products p ON p.id=cp.product_id
    WHERE cp.category_slug=".  $pdo->quote($cat)  ."
      AND p.is_active=1
      AND p.type='paid'
    ORDER BY p.price ASC
");
$available_upgrades = $prod_stmt->fetchAll();
$current_price = (float)($server['renewal_price'] ?? 0);

// Traitement POST : upgrader
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['new_product_id'])){
    $new_pid = (int)$_POST['new_product_id'];
    $new_prod = null;
    foreach($available_upgrades as $ap){ if((int)$ap['id']===$new_pid){ $new_prod=$ap; break; } }
    if(!$new_prod){ $flash='<div class="bg-red-500/15 text-red-400 border border-red-500/25 p-4 rounded-xl text-sm mb-4">❌ Offre invalide.</div>'; goto render; }

    // Mettre à jour les ressources sur le panel Pterodactyl
    $server_id = (int)$server['server_id'];
    if($server_id && $api_key_admin){
        $details = curlAdminApi($panel_url,$headers_admin,'servers/'.$server_id);
        $alloc   = $details['attributes']['allocation'] ?? null;
        if($alloc){
            curlAdminApi($panel_url,$headers_admin,"servers/$server_id/build",'PATCH',[
                'allocation'    => $alloc,
                'memory'        => $new_prod['ram'],
                'swap'          => 0,
                'disk'          => $new_prod['disk'],
                'io'            => 500,
                'cpu'           => $new_prod['cpu'],
                'threads'       => null,
                'feature_limits'=> ['databases'=>$new_prod['databases'],'backups'=>$new_prod['backups'],'allocations'=>$new_prod['allocations']],
            ]);
        }
    }

    // Mettre à jour la commande en BDD
    $pdo->prepare("UPDATE orders SET product_id=?,service_name=?,ram=?,disk=?,cpu=?,renewal_price=? WHERE uuid=? AND user_id=?")
        ->execute([$new_prod['id'],$new_prod['name'],$new_prod['ram'],$new_prod['disk'],$new_prod['cpu'],$new_prod['price'],$uuid,$_SESSION['user_id']]);

    $flash='<div class="bg-green-500/15 text-green-400 border border-green-500/25 p-4 rounded-xl text-sm mb-4">✅ Serveur upgradé vers <strong>'.htmlspecialchars($new_prod['name']).'</strong> avec succès !</div>';
    // Recharger le serveur
    $srv_stmt->execute([$uuid,$_SESSION['user_id']]);
    $server=$srv_stmt->fetch();
    $current_price=(float)($server['renewal_price']??0);
}

render:

$open_tickets = (int)$pdo->prepare("SELECT COUNT(*) FROM support_tickets WHERE user_id=? AND status != 'Fermé'")->execute([$_SESSION['user_id']]) ? (int)$pdo->query("SELECT COUNT(*) FROM support_tickets WHERE user_id=".(int)$_SESSION['user_id']." AND status != 'Fermé'")->fetchColumn() : 0;

function curlAdminApi($url,$headers,$ep,$method='GET',$data=null){
    $ch=curl_init($url.'/api/application/'.$ep);
    curl_setopt_array($ch,[CURLOPT_HTTPHEADER=>$headers,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>15,CURLOPT_SSL_VERIFYPEER=>false]);
    if($method==='DELETE') curl_setopt($ch,CURLOPT_CUSTOMREQUEST,'DELETE');
    if($method==='PATCH'){ curl_setopt($ch,CURLOPT_CUSTOMREQUEST,'PATCH'); curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($data)); }
    if($method==='POST'){ curl_setopt($ch,CURLOPT_POST,true); if($data) curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($data)); }
    $r=curl_exec($ch);$c=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
    if($c===204) return true;
    return $r?json_decode($r,true):null;
}

include $_SERVER['DOCUMENT_ROOT'] . '/inc/clients_sidebar.php';
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>OrinHeberge — Upgrader mon serveur</title>
<link rel="icon" type="image/png" href="/favicon.ico">
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
:root{--sidebar:240px;}*{box-sizing:border-box;}
body{background:#0d0f14;color:#e2e8f0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',system-ui,sans-serif;min-height:100vh;}
.sidebar{position:fixed;top:0;left:0;width:var(--sidebar);height:100vh;background:#111318;border-right:1px solid rgba(255,255,255,.06);display:flex;flex-direction:column;z-index:40;overflow-y:auto;}
.sidebar-logo{padding:1.5rem 1.25rem 1rem;border-bottom:1px solid rgba(255,255,255,.05);}
.sidebar-nav{padding:.75rem;flex:1;}
.nav-item{display:flex;align-items:center;gap:.75rem;padding:.625rem .875rem;border-radius:.625rem;font-size:.82rem;font-weight:500;color:#6b7280;transition:all .15s;text-decoration:none;margin-bottom:.15rem;border:1px solid transparent;}
.nav-item:hover{background:rgba(255,255,255,.04);color:#d1d5db;}
.nav-item.active{background:rgba(56,189,248,.08);color:#38bdf8;border-color:rgba(56,189,248,.15);}
.nav-item .icon{width:1.1rem;text-align:center;font-size:.85rem;flex-shrink:0;}
.nav-section{font-size:.65rem;font-weight:700;letter-spacing:.1em;color:#374151;text-transform:uppercase;padding:.75rem .875rem .35rem;}
.nav-separator{height:1px;background:rgba(255,255,255,.05);margin:.5rem .75rem;}
.sidebar-footer{padding:.875rem 1rem;border-top:1px solid rgba(255,255,255,.05);}
.main-content{margin-left:var(--sidebar);min-height:100vh;display:flex;flex-direction:column;}
.topbar{background:#111318;border-bottom:1px solid rgba(255,255,255,.06);padding:.875rem 1.75rem;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:30;}
.content{padding:1.75rem;flex:1;}
.card{background:#161a22;border:1px solid rgba(255,255,255,.07);border-radius:.875rem;}
.plan-card{background:#161a22;border:2px solid rgba(255,255,255,.06);border-radius:1rem;cursor:pointer;transition:all .2s;position:relative;}
.plan-card:hover,.plan-card.selected{border-color:#38bdf8;background:rgba(56,189,248,.04);}
.plan-card.current{border-color:#22c55e;background:rgba(34,197,94,.04);}
.badge{display:inline-flex;align-items:center;gap:.35rem;padding:.2rem .65rem;border-radius:9999px;font-size:.72rem;font-weight:600;}
.badge-green{background:rgba(34,197,94,.1);color:#22c55e;border:1px solid rgba(34,197,94,.2);}
.badge-sky{background:rgba(56,189,248,.1);color:#38bdf8;border:1px solid rgba(56,189,248,.2);}
.mobile-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:39;}
@media(max-width:768px){.sidebar{transform:translateX(-100%);transition:transform .25s;}.sidebar.open{transform:translateX(0);}.mobile-overlay.open{display:block;}.main-content{margin-left:0;}.topbar,.content{padding:.875rem 1rem;}}
</style>
<script>
function toggleSidebar(){document.getElementById('sidebar').classList.toggle('open');document.getElementById('overlay').classList.toggle('open');}
function selectPlan(el,pid,price,name){
    document.querySelectorAll('.plan-card').forEach(c=>c.classList.remove('selected'));
    el.classList.add('selected');
    document.getElementById('new_product_id').value=pid;
    document.getElementById('confirm-name').textContent=name;
    document.getElementById('confirm-price').textContent=price+'€/mois';
    document.getElementById('confirm-bar').classList.remove('hidden');
}
</script>
</head>
<body>
<div id="overlay" class="mobile-overlay" onclick="toggleSidebar()"></div>

<div class="main-content">
<div class="topbar">
    <div class="flex items-center gap-3">
        <button onclick="toggleSidebar()" class="md:hidden text-gray-400 hover:text-white text-lg w-8"><i class="fas fa-bars"></i></button>
        <div>
            <div class="text-sm font-bold text-white">Upgrader mon serveur</div>
            <div class="text-xs text-gray-500"><?php echo htmlspecialchars($server['service_name'] ?? ''); ?></div>
        </div>
    </div>
    <a href="/client/servers/" class="flex items-center gap-2 text-xs text-gray-400 hover:text-white font-semibold px-3 py-1.5 rounded-lg hover:bg-white/5 transition">
        <i class="fas fa-arrow-left text-[10px]"></i> Retour
    </a>
</div>

<div class="content max-w-4xl">
    <?php echo $flash; ?>

    <!-- Serveur actuel -->
    <div class="card p-5 mb-6 flex items-center gap-4">
        <div class="w-10 h-10 rounded-xl bg-sky-500/15 flex items-center justify-center shrink-0">
            <i class="fas fa-server text-sky-400"></i>
        </div>
        <div class="flex-1 min-w-0">
            <div class="text-sm font-bold text-white"><?php echo htmlspecialchars($server['service_name'] ?? 'Serveur'); ?></div>
            <div class="text-xs text-gray-500 font-mono"><?php echo htmlspecialchars(substr($uuid, 0, 8)); ?>… · <?php echo $server['ram']; ?> MB RAM · <?php echo $server['cpu']; ?>% CPU</div>
        </div>
        <div>
            <div class="text-xs text-gray-500 mb-0.5">Offre actuelle</div>
            <span class="badge badge-green"><?php echo number_format($current_price, 2, ',', ''); ?>€/mois</span>
        </div>
    </div>

    <?php if(empty($available_upgrades)): ?>
    <div class="card p-12 text-center">
        <i class="fas fa-rocket text-3xl text-gray-600 mb-4 block"></i>
        <div class="text-sm font-semibold text-gray-300 mb-1">Aucun upgrade disponible</div>
        <div class="text-xs text-gray-500 mb-4">Ce type de serveur n'a pas d'offres supérieures disponibles pour le moment.</div>
        <a href="/client/servers/" class="inline-flex items-center gap-2 bg-sky-600 hover:bg-sky-500 text-white text-xs font-bold px-4 py-2 rounded-lg transition">Retour aux serveurs</a>
    </div>
    <?php else: ?>

    <h2 class="text-sm font-bold text-white mb-4 flex items-center gap-2">
        <i class="fas fa-arrow-up text-sky-400 text-xs"></i>
        Choisir une nouvelle offre — <?php echo ucfirst($cat); ?>
    </h2>

    <form method="POST">
        <input type="hidden" name="new_product_id" id="new_product_id" value="">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
        <?php foreach($available_upgrades as $ap):
            $is_current = ($ap['id'] == ($server['product_id'] ?? -1));
            $ram_t = $ap['ram']>=1024?number_format($ap['ram']/1024,0).' GB RAM':$ap['ram'].' MB RAM';
            $disk_t= $ap['disk']>=1024?number_format($ap['disk']/1024,0).' GB SSD':$ap['disk'].' MB SSD';
        ?>
        <div onclick="<?php echo $is_current ? '' : "selectPlan(this,{$ap['id']},'{$ap['price']}',".json_encode($ap['name']).")"; ?>"
             class="plan-card p-5 <?php echo $is_current ? 'current' : ''; ?>">
            <?php if($is_current): ?>
            <div class="absolute -top-2.5 left-4 bg-green-500 text-slate-950 text-[10px] font-black px-2.5 py-0.5 rounded-full uppercase">Actuel</div>
            <?php endif; ?>
            <div class="text-sm font-bold text-white mb-1"><?php echo htmlspecialchars($ap['name']); ?></div>
            <div class="text-xl font-black text-white mb-3"><?php echo number_format((float)$ap['price'],2,',',''); ?><span class="text-gray-500 text-xs font-normal">€/mois</span></div>
            <ul class="space-y-1.5 text-xs text-gray-400">
                <li><i class="fas fa-memory text-sky-400 mr-1.5 w-3"></i><?php echo $ram_t; ?></li>
                <li><i class="fas fa-hard-drive text-sky-400 mr-1.5 w-3"></i><?php echo $disk_t; ?></li>
                <li><i class="fas fa-microchip text-sky-400 mr-1.5 w-3"></i><?php echo $ap['cpu']; ?>% CPU</li>
                <li><i class="fas fa-database text-sky-400 mr-1.5 w-3"></i><?php echo $ap['databases']; ?> BDD</li>
            </ul>
            <?php if(!$is_current): ?>
            <div class="mt-3 w-full text-center text-xs font-semibold text-sky-400 py-1.5 rounded-lg border border-sky-500/20 bg-sky-500/5">
                Sélectionner
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        </div>

        <!-- Barre de confirmation -->
        <div id="confirm-bar" class="hidden fixed bottom-0 left-0 md:left-[240px] right-0 z-30 bg-[#111318] border-t border-white/10 px-6 py-4 flex items-center justify-between gap-4">
            <div>
                <div class="text-xs text-gray-400">Upgrade vers</div>
                <div class="text-sm font-bold text-white" id="confirm-name"></div>
                <div class="text-xs text-sky-400 font-mono" id="confirm-price"></div>
            </div>
            <button type="submit" class="bg-sky-600 hover:bg-sky-500 text-white font-bold px-8 py-3 rounded-xl text-sm transition flex items-center gap-2 shrink-0">
                <i class="fas fa-rocket text-xs"></i> Confirmer l'upgrade
            </button>
        </div>
    </form>
    <?php endif; ?>
</div>
</div>
</body>
</html>

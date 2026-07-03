<?php
ini_set('display_errors', 1); error_reporting(E_ALL);
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/lang.php';
if (!isset($_SESSION['user_id'])) { header('Location: /login/'); exit(); }

try {
    $pdo = new PDO('mysql:host=localhost;dbname=s43_orinheberge;charset=utf8mb4','root','1504',[
        PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC
    ]);
} catch(PDOException $e){ die(t('login.db_error')); }

$cfg = [];
foreach($pdo->query('SELECT `key`,`value` FROM settings') as $r) $cfg[$r['key']]=$r['value'];
$panel_url      = $cfg['panel_url']      ?? 'https://panel.orinstone.deepstone.fr';
$api_key_client = $cfg['api_key_client'] ?? '';
$api_key_admin  = $cfg['api_key_admin']  ?? '';
$phpmyadmin_url = $cfg['phpmyadmin_url'] ?? 'https://php.orinstone.deepstone.fr';

// Rafraîchir session
$stmt = $pdo->prepare('SELECT pseudo,firstname,avatar,is_admin FROM users WHERE id=? LIMIT 1');
$stmt->execute([$_SESSION['user_id']]);
$user_data = $stmt->fetch();
if($user_data){
    $_SESSION['username'] = !empty($user_data['pseudo']) ? $user_data['pseudo'] : $user_data['firstname'];
    $_SESSION['avatar']   = $user_data['avatar'];
    $_SESSION['is_admin'] = (bool)$user_data['is_admin'];
}

// Mes services
$stmt = $pdo->prepare('SELECT * FROM orders WHERE user_id=? ORDER BY created_at DESC');
$stmt->execute([$_SESSION['user_id']]);
$services = $stmt->fetchAll();

// Tickets ouverts
$open_tickets = (int)$pdo->prepare("SELECT COUNT(*) FROM support_tickets WHERE user_id=? AND status != 'Fermé'")->execute([$_SESSION['user_id']]) ?
    (int)$pdo->query("SELECT COUNT(*) FROM support_tickets WHERE user_id=".(int)$_SESSION['user_id']." AND status!='Fermé'")->fetchColumn() : 0;

include $_SERVER['DOCUMENT_ROOT'] . '/inc/clients_sidebar.php';
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>OrinHeberge — Espace Client</title>
<link rel="icon" type="image/png" href="/favicon.ico">
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<link rel="manifest" href="/manifest.json">
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
.stat-card{background:linear-gradient(135deg,#161a22,#1a1f2a);border:1px solid rgba(255,255,255,.07);border-radius:.875rem;padding:1.25rem;}
.card{background:#161a22;border:1px solid rgba(255,255,255,.07);border-radius:.875rem;}
.badge{display:inline-flex;align-items:center;gap:.35rem;padding:.2rem .65rem;border-radius:9999px;font-size:.72rem;font-weight:600;}
.badge-green{background:rgba(34,197,94,.1);color:#22c55e;border:1px solid rgba(34,197,94,.2);}
.badge-orange{background:rgba(249,115,22,.1);color:#f97316;border:1px solid rgba(249,115,22,.2);}
.badge-red{background:rgba(239,68,68,.1);color:#ef4444;border:1px solid rgba(239,68,68,.2);}
.badge-gray{background:rgba(107,114,128,.1);color:#9ca3af;border:1px solid rgba(107,114,128,.2);}
.badge-blue{background:rgba(56,189,248,.1);color:#38bdf8;border:1px solid rgba(56,189,248,.2);}
.svc-row{display:flex;align-items:center;gap:1rem;padding:.9rem 1.25rem;border-bottom:1px solid rgba(255,255,255,.04);transition:background .15s;}
.svc-row:last-child{border-bottom:none;}
.svc-row:hover{background:rgba(255,255,255,.02);}
.svc-icon{width:2.25rem;height:2.25rem;border-radius:.625rem;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:.9rem;}
.qlink{display:flex;align-items:center;gap:.75rem;padding:.75rem 1rem;border-radius:.75rem;border:1px solid rgba(255,255,255,.06);background:#161a22;transition:all .15s;text-decoration:none;}
.qlink:hover{border-color:rgba(56,189,248,.25);background:rgba(56,189,248,.04);}
.qlink-icon{width:2rem;height:2rem;border-radius:.5rem;display:flex;align-items:center;justify-content:center;font-size:.8rem;flex-shrink:0;}
.mobile-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:39;}
@media(max-width:768px){.sidebar{transform:translateX(-100%);transition:transform .25s;}.sidebar.open{transform:translateX(0);}.mobile-overlay.open{display:block;}.main-content{margin-left:0;}.topbar,.content{padding:.75rem 1rem;}}
</style>
<script>
function toggleSidebar(){document.getElementById('sidebar').classList.toggle('open');document.getElementById('overlay').classList.toggle('open');}
</script>
</head>
<body>
<div id="overlay" class="mobile-overlay" onclick="toggleSidebar()"></div>

<div class="main-content">
  <!-- Topbar -->
  <div class="topbar">
    <div class="flex items-center gap-3">
      <button onclick="toggleSidebar()" class="md:hidden text-gray-400 hover:text-white text-lg w-8"><i class="fas fa-bars"></i></button>
      <div>
        <div class="text-sm font-bold text-white">Tableau de bord</div>
        <div class="text-xs text-gray-500">Bienvenue, <?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?></div>
      </div>
    </div>
    <div class="flex items-center gap-3">
      <a href="/offres/" class="hidden sm:flex items-center gap-2 bg-sky-600 hover:bg-sky-500 text-white text-xs font-bold px-4 py-2 rounded-lg transition">
        <i class="fas fa-plus text-[10px]"></i> Nouveau service
      </a>
      <a href="/profil/" class="w-8 h-8 rounded-full overflow-hidden border border-white/10 flex items-center justify-center bg-sky-500/10 shrink-0">
        <?php if(!empty($_SESSION['avatar']) && file_exists($_SERVER['DOCUMENT_ROOT'].'/'.$_SESSION['avatar'])): ?>
          <img src="/<?php echo htmlspecialchars($_SESSION['avatar']); ?>" class="w-full h-full object-cover">
        <?php else: ?>
          <span class="text-sky-400 text-xs font-bold"><?php echo strtoupper(substr($_SESSION['username']??'U',0,1)); ?></span>
        <?php endif; ?>
      </a>
    </div>
  </div>

  <div class="content">
    <?php
    $paid  = array_filter($services, fn($s)=>($s['status']??'')==='paid');
    $free  = array_filter($services, fn($s)=>($s['renewal_price']??0)==0);
    $susp  = array_filter($services, fn($s)=>($s['status']??'')==='suspended');
    ?>

    <!-- Stats -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
      <div class="stat-card">
        <div class="flex items-center justify-between mb-3"><span class="text-xs text-gray-500 font-medium">Services</span><div class="w-7 h-7 rounded-lg bg-sky-500/15 flex items-center justify-center"><i class="fas fa-server text-sky-400 text-xs"></i></div></div>
        <div class="text-2xl font-black text-white"><?php echo count($services); ?></div>
        <div class="text-xs text-gray-500 mt-1">Total déployés</div>
      </div>
      <div class="stat-card">
        <div class="flex items-center justify-between mb-3"><span class="text-xs text-gray-500 font-medium">Actifs</span><div class="w-7 h-7 rounded-lg bg-green-500/15 flex items-center justify-center"><i class="fas fa-check text-green-400 text-xs"></i></div></div>
        <div class="text-2xl font-black text-white"><?php echo count($paid)+count($free); ?></div>
        <div class="text-xs text-gray-500 mt-1">En ligne</div>
      </div>
      <div class="stat-card">
        <div class="flex items-center justify-between mb-3"><span class="text-xs text-gray-500 font-medium">Tickets</span><div class="w-7 h-7 rounded-lg bg-purple-500/15 flex items-center justify-center"><i class="fas fa-headset text-purple-400 text-xs"></i></div></div>
        <div class="text-2xl font-black text-white"><?php echo $open_tickets; ?></div>
        <div class="text-xs text-gray-500 mt-1">Ouverts</div>
      </div>
      <a href="/profil/" class="stat-card hover:border-sky-500/30 transition block">
        <div class="flex items-center justify-between mb-3"><span class="text-xs text-gray-500 font-medium">Compte</span><div class="w-7 h-7 rounded-lg bg-amber-500/15 flex items-center justify-center"><i class="fas fa-user text-amber-400 text-xs"></i></div></div>
        <div class="text-sm font-bold text-white truncate"><?php echo htmlspecialchars($_SESSION['username']??''); ?></div>
        <div class="text-xs text-sky-400 mt-1">Modifier →</div>
      </a>
    </div>

    <!-- Content grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

      <!-- Liste services -->
      <div class="lg:col-span-2 card">
        <div class="flex items-center justify-between px-5 py-4 border-b border-white/[0.05]">
          <h2 class="text-sm font-bold text-white flex items-center gap-2"><i class="fas fa-server text-sky-400 text-xs"></i> Mes services</h2>
          <a href="/client/servers/" class="text-xs text-sky-400 hover:text-sky-300 font-semibold">Tout gérer →</a>
        </div>
        <?php if(empty($services)): ?>
        <div class="px-5 py-14 text-center">
          <div class="w-12 h-12 rounded-xl bg-sky-500/10 flex items-center justify-center mx-auto mb-3"><i class="fas fa-server text-sky-400"></i></div>
          <div class="text-sm font-semibold text-gray-300 mb-1">Aucun service</div>
          <div class="text-xs text-gray-500 mb-4">Déployez votre premier serveur gratuitement.</div>
          <a href="/offres/" class="inline-flex items-center gap-2 bg-sky-600 hover:bg-sky-500 text-white text-xs font-bold px-4 py-2.5 rounded-lg transition"><i class="fas fa-rocket text-[10px]"></i> Voir les offres</a>
        </div>
        <?php else: ?>
        <?php
        $icons_cat = ['minecraft'=>['fas fa-cube','bg-green-500/15','text-green-400'],'fivem'=>['fas fa-car','bg-red-500/15','text-red-400'],'php'=>['fas fa-code','bg-blue-500/15','text-blue-400'],'nodejs'=>['fab fa-node-js','bg-green-500/15','text-green-400'],'python'=>['fab fa-python','bg-yellow-500/15','text-yellow-400'],'java'=>['fab fa-java','bg-orange-500/15','text-orange-400'],'hytale'=>['fas fa-gamepad','bg-purple-500/15','text-purple-400']];
        foreach(array_slice($services,0,8) as $svc):
            $sl = strtolower($svc['service_name']??'');
            $ico = ['fas fa-server','bg-sky-500/15','text-sky-400'];
            foreach($icons_cat as $k=>$v){ if(str_contains($sl,$k)){ $ico=$v; break; } }
            $st=$svc['status']??'unknown';
            $is_free=($svc['renewal_price']??0)==0;
            $bdg=match($st){'paid'=>'badge-green','suspended'=>'badge-orange','expired'=>'badge-red',default=>$is_free?'badge-blue':'badge-gray'};
            $lbl=match($st){'paid'=>'Actif','suspended'=>'Suspendu','expired'=>'Expiré',default=>$is_free?'Gratuit':'En attente'};
        ?>
        <div class="svc-row">
          <div class="svc-icon <?php echo $ico[1]; ?>"><i class="<?php echo $ico[0].' '.$ico[2]; ?>"></i></div>
          <div class="flex-1 min-w-0">
            <div class="text-sm font-semibold text-white truncate"><?php echo htmlspecialchars($svc['service_name']??'Serveur'); ?></div>
            <div class="text-[10px] text-gray-500 font-mono"><?php echo htmlspecialchars(substr($svc['uuid']??'',0,8)); ?>…</div>
          </div>
          <div class="flex items-center gap-2.5 shrink-0">
            <span class="badge <?php echo $bdg; ?>"><?php echo $lbl; ?></span>
            <?php if(!$is_free): ?>
              <span class="text-[10px] text-gray-500 font-mono"><?php echo number_format((float)$svc['renewal_price'],2,',',''); ?>€</span>
            <?php endif; ?>
            <?php if(!empty($svc['uuid'])): ?>
            <a href="<?php echo htmlspecialchars($panel_url); ?>/server/<?php echo htmlspecialchars($svc['uuid']); ?>" target="_blank" class="text-gray-500 hover:text-sky-400 transition"><i class="fas fa-external-link-alt text-xs"></i></a>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
        <?php if(count($services)>8): ?>
        <div class="px-5 py-3 text-center border-t border-white/[0.04]">
          <a href="/client/servers/" class="text-xs text-gray-500 hover:text-sky-400 transition">Voir <?php echo count($services)-8; ?> service(s) de plus →</a>
        </div>
        <?php endif; ?>
        <?php endif; ?>
      </div>

      <!-- Accès rapide -->
      <div class="space-y-2.5">
        <h3 class="text-xs font-bold text-gray-500 uppercase tracking-wider px-1 mb-3">Accès rapide</h3>
        <a href="/offres/" class="qlink"><div class="qlink-icon bg-sky-500/15"><i class="fas fa-tags text-sky-400"></i></div><div><div class="text-xs font-semibold text-white">Nos offres</div><div class="text-[10px] text-gray-500">Plans & tarifs</div></div></a>
        <a href="/client/servers/" class="qlink"><div class="qlink-icon bg-green-500/15"><i class="fas fa-server text-green-400"></i></div><div><div class="text-xs font-semibold text-white">Gérer serveurs</div><div class="text-[10px] text-gray-500">Console, démarrage…</div></div></a>
        <a href="/support/" class="qlink"><div class="qlink-icon bg-purple-500/15"><i class="fas fa-headset text-purple-400"></i></div><div><div class="text-xs font-semibold text-white">Support</div><div class="text-[10px] text-gray-500">Ouvrir un ticket</div></div></a>
        <a href="<?php echo htmlspecialchars($panel_url); ?>" target="_blank" class="qlink"><div class="qlink-icon bg-amber-500/15"><i class="fas fa-cogs text-amber-400"></i></div><div><div class="text-xs font-semibold text-white">Panel Pterodactyl</div><div class="text-[10px] text-gray-500">Accès direct</div></div></a>
        <a href="<?php echo htmlspecialchars($phpmyadmin_url); ?>" target="_blank" class="qlink"><div class="qlink-icon bg-sky-500/15"><i class="fas fa-database text-sky-400"></i></div><div><div class="text-xs font-semibold text-white">phpMyAdmin</div><div class="text-[10px] text-gray-500">Base de données</div></div></a>
        <a href="/client/billing/" class="qlink"><div class="qlink-icon bg-emerald-500/15"><i class="fas fa-file-invoice-dollar text-emerald-400"></i></div><div><div class="text-xs font-semibold text-white">Facturation</div><div class="text-[10px] text-gray-500">Factures & reçus</div></div></a>
        <?php if(!empty($_SESSION['is_admin'])): ?>
        <a href="/admin/" class="qlink" style="border-color:rgba(251,146,60,.2)"><div class="qlink-icon bg-orange-500/15"><i class="fas fa-user-tie"></i></div><div><div class="text-xs font-semibold text-orange-400"></div><div class="text-[10px] text-gray-500">Panel admin</div></div></a>
        <?php endif; ?>
      </div>

    </div>
  </div><!-- /content -->
</div><!-- /main-content -->
</body>
</html>

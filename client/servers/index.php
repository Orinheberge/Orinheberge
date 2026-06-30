<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/lang.php';

if (!isset($_SESSION['user_id'])) { header('Location: /login/'); exit(); }

$is_logged_in = true;
$panel_url       = 'https://panel.orinstone.deepstone.fr';
$api_key_client  = 'ptlc_MfJSOUID0bnTgFCmm5VvYMML2jKUUA5RFZ2n2MeZCSU';
$headers_client  = ["Authorization: Bearer $api_key_client","Accept: application/vnd.pterodactyl.v1+json","Content-Type: application/json"];
$api_key_admin   = 'ptla_YKix8PexQDCZ7nIeexST3NXC2sFwQAoefDtOQBvJkbx';
$headers_admin   = ["Authorization: Bearer $api_key_admin","Accept: application/vnd.pterodactyl.v1+json","Content-Type: application/json"];

try {
    $pdo = new PDO('mysql:host=pma.orinstone.deepstone.fr;dbname=s43_orinheberge;charset=utf8mb4','root','1504',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) { die(t('login.db_error')); }

$stmt = $pdo->prepare('SELECT * FROM orders WHERE user_id=? ORDER BY created_at DESC');
$stmt->execute([$_SESSION['user_id']]);
$servers = $stmt->fetchAll(PDO::FETCH_ASSOC);

function clientApi($panel_url,$headers,$endpoint,$method='GET',$data=null){
    $ch=curl_init($panel_url.'/api/client/'.$endpoint);
    curl_setopt_array($ch,[CURLOPT_HTTPHEADER=>$headers,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>15,CURLOPT_SSL_VERIFYPEER=>false,CURLOPT_SSL_VERIFYHOST=>false]);
    if($method==='POST'){curl_setopt($ch,CURLOPT_POST,true);curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($data));}
    $res=curl_exec($ch);$http_code=curl_getinfo($ch,CURLINFO_HTTP_CODE);
    if(curl_errno($ch))return['error'=>'Erreur cURL','http_code'=>$http_code];
    return $res?json_decode($res,true):['error'=>t('servers.no_response'),'http_code'=>$http_code];
}
function adminApi($panel_url,$headers,$endpoint,$method='GET',$data=null){
    $ch=curl_init($panel_url.'/api/application/'.$endpoint);
    curl_setopt_array($ch,[CURLOPT_HTTPHEADER=>$headers,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>15,CURLOPT_SSL_VERIFYPEER=>false,CURLOPT_SSL_VERIFYHOST=>false]);
    if($method==='DELETE')curl_setopt($ch,CURLOPT_CUSTOMREQUEST,'DELETE');
    $res=curl_exec($ch);$http_code=curl_getinfo($ch,CURLINFO_HTTP_CODE);
    if(curl_errno($ch))return['error'=>'Erreur cURL','http_code'=>$http_code];
    if($http_code===204)return true;
    return $res?json_decode($res,true):['error'=>t('servers.no_response'),'http_code'=>$http_code];
}
function getResources($pu,$h,$id){$s=substr($id,0,8);return clientApi($pu,$h,"servers/$s/resources");}
function getServerDetails($pu,$h,$id){$s=substr($id,0,8);return clientApi($pu,$h,"servers/$s");}

$api_message = '';
if (isset($_GET['action'], $_GET['uuid'])) {
    $target_uuid = $_GET['uuid'];
    $action = $_GET['action'];
    $stmt = $pdo->prepare('SELECT uuid, server_id FROM orders WHERE user_id=? AND uuid=?');
    $stmt->execute([$_SESSION['user_id'], $target_uuid]);
    $sv = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($sv) {
        $id = $sv['uuid']; $short = substr($id,0,8); $num = $sv['server_id'];
        switch($action) {
            case 'start':   clientApi($panel_url,$headers_client,"servers/$short/power",'POST',['signal'=>'start']); break;
            case 'stop':    clientApi($panel_url,$headers_client,"servers/$short/power",'POST',['signal'=>'stop']); break;
            case 'restart': clientApi($panel_url,$headers_client,"servers/$short/power",'POST',['signal'=>'restart']); break;
            case 'delete':
                $r=adminApi($panel_url,$headers_admin,"servers/$num",'DELETE');
                if($r===true||!isset($r['errors']))$pdo->prepare('DELETE FROM orders WHERE uuid=?')->execute([$id]);
                break;
        }
        $_SESSION['api_success'] = "L'action '".strtoupper($action)."' a été transmise !";
    } else {
        $_SESSION['api_error'] = 'Sécurité : Ce serveur ne correspond pas à votre compte.';
    }
    header('Location: /client/servers/'); exit();
}
if (isset($_SESSION['api_error']))   { $api_message = "<div class='bg-red-500/20 text-red-400 border border-red-500/30 p-4 rounded-xl mb-6 text-sm'>".$_SESSION['api_error']."</div>"; unset($_SESSION['api_error']); }
elseif (isset($_SESSION['api_success'])) { $api_message = "<div class='bg-green-500/20 text-green-400 border border-green-500/30 p-4 rounded-xl mb-6 text-sm'>".$_SESSION['api_success']."</div>"; unset($_SESSION['api_success']); }
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?php echo t('servers.title'); ?></title>
  <link rel="icon" type="image/png" href="https://heberge.orinstone.deepstone.fr/favicon.ico">
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <link rel="manifest" href="/manifest.json">
  <style>
    body{background:#0b0f19;scroll-behavior:smooth;}
    .glass{background:rgba(255,255,255,0.04);backdrop-filter:blur(14px);border:1px solid rgba(255,255,255,0.08);}
    .gradient-text{background:linear-gradient(90deg,#38bdf8,#818cf8);-webkit-background-clip:text;-webkit-text-fill-color:transparent;}
    .card-hover{transition:all .3s ease;}.card-hover:hover{transform:translateY(-4px);border-color:rgba(56,189,248,.2);}
    #mobileMenu{display:none;}#mobileMenu.active{display:block;}
  </style>
  <script>
    function toggleMenu(){document.getElementById('mobileMenu').classList.toggle('active');}
    function confirmDelete(){return confirm('⚠️ <?php echo ($lang==='en') ? 'Are you sure you want to permanently delete this server? This action is irreversible.' : 'Êtes-vous sûr de vouloir supprimer définitivement ce serveur ? Cette action est irréversible.'; ?>');}
  </script>
</head>
<body class="text-gray-200 font-sans min-h-screen flex flex-col justify-between">

<?php $active_nav = 'servers'; include $_SERVER['DOCUMENT_ROOT'] . '/inc/navbar.php'; ?>

<main class="max-w-7xl mx-auto px-4 sm:px-6 mb-16 flex-grow pt-8">
  <?php echo $api_message; ?>

  <div class="flex items-center justify-between mb-8 gap-4 flex-wrap">
    <h1 class="text-4xl font-black tracking-tight flex items-center gap-3"><?php echo t('servers.heading'); ?></h1>
    <div class="flex gap-3 items-center">
      <span class="glass px-4 py-1.5 rounded-full text-xs text-gray-400 font-medium"><?php echo count($servers); ?> machine(s)</span>
      <a href="/shop/" class="bg-sky-600 hover:bg-sky-500 px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-2">
        <i class="fas fa-plus"></i> <?php echo t('servers.order_new'); ?>
      </a>
    </div>
  </div>

  <?php if (empty($servers)): ?>
    <div class="glass p-12 rounded-2xl text-center text-gray-500 italic">
      <?php echo t('servers.no_server'); ?>
      <div class="mt-4"><a href="/shop/" class="bg-sky-600 hover:bg-sky-500 text-white px-6 py-3 rounded-xl font-bold transition inline-block"><?php echo t('servers.order_new'); ?></a></div>
    </div>
  <?php else: ?>
  <div class="grid md:grid-cols-2 xl:grid-cols-3 gap-6">
  <?php foreach ($servers as $server):
    $identifier = $server['uuid'] ?? '';
    $short_id   = $server['id_server_panel'] ?? 'N/A';
    $status = 'offline'; $ram_mb = 0; $cpu = 0; $disk_gb = 0; $ports_count = 0; $allocations_list = [];

    if (!empty($identifier)) {
        $res = getResources($panel_url, $headers_client, $identifier);
        if (isset($res['attributes'])) {
            $status    = $res['attributes']['current_state'] ?? 'offline';
            $ram       = $res['attributes']['resources']['memory_bytes'] ?? 0;
            $cpu       = $res['attributes']['resources']['cpu_absolute'] ?? 0;
            $disk_bytes= $res['attributes']['resources']['disk_bytes'] ?? 0;
            $ram_mb    = round($ram/1024/1024);
            $disk_gb   = round($disk_bytes/1024/1024/1024,2);
        }
        $details = getServerDetails($panel_url, $headers_client, $identifier);
        if (isset($details['attributes']['relationships']['allocations']['data'])) {
            $allocs = $details['attributes']['relationships']['allocations']['data'];
            $ports_count = count($allocs);
            foreach ($allocs as $alloc) {
                if (isset($alloc['attributes']['port']))
                    $allocations_list[] = $alloc['attributes']['port'].($alloc['attributes']['is_default']?' (Principal)':'');
            }
        }
    }

    $status_map = [
        'running' => ['text'=>($lang==='en'?'Online':'En ligne'),   'class'=>'bg-green-500/10 text-green-400 border-green-500/20',  'dot'=>'bg-green-400'],
        'starting'=> ['text'=>($lang==='en'?'Starting':'En cours'), 'class'=>'bg-amber-500/10 text-amber-400 border-amber-500/20',  'dot'=>'bg-amber-400 animate-pulse'],
        'stopping'=> ['text'=>($lang==='en'?'Stopping':'Extinction'),'class'=>'bg-amber-500/10 text-amber-400 border-amber-500/20', 'dot'=>'bg-amber-400 animate-pulse'],
        'offline' => ['text'=>($lang==='en'?'Offline':'Hors ligne'), 'class'=>'bg-red-500/10 text-red-400 border-red-500/20',       'dot'=>'bg-red-400'],
    ];
    $sm = $status_map[$status] ?? $status_map['offline'];
  ?>
  <div class="glass p-6 rounded-2xl flex flex-col justify-between card-hover border border-white/[0.05]">
    <div>
      <div class="flex justify-between items-start gap-4">
        <div>
          <h2 class="text-xl font-bold text-white tracking-tight line-clamp-1"><?php echo htmlspecialchars($server['service_name']); ?></h2>
          <div class="flex items-center gap-2 mt-1">
            <p class="text-gray-500 text-xs font-mono">ID Panel: #<?php echo htmlspecialchars($short_id); ?></p>
            <a href="<?php echo $panel_url; ?>/server/<?php echo htmlspecialchars($short_id); ?>" target="_blank"
               class="bg-sky-600/20 hover:bg-sky-600 border border-sky-500/30 text-sky-400 hover:text-white px-2 py-0.5 rounded-md text-[10px] font-bold transition flex items-center gap-1">
              <i class="fas fa-arrow-up-right-from-square text-[9px]"></i> <?php echo t('servers.manage'); ?>
            </a>
          </div>
        </div>
        <span class="px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wider flex items-center gap-1.5 border <?php echo $sm['class']; ?>">
          <span class="h-2 w-2 rounded-full <?php echo $sm['dot']; ?>"></span><?php echo $sm['text']; ?>
        </span>
      </div>

      <div class="space-y-4 mt-6">
        <div>
          <div class="flex justify-between text-xs mb-1">
            <span class="text-gray-400 font-medium flex items-center gap-1"><i class="fas fa-microchip text-sky-400 w-4"></i> CPU</span>
            <span class="font-mono text-gray-300"><?php echo $cpu; ?>%</span>
          </div>
          <div class="w-full bg-white/5 rounded-full h-1.5"><div class="bg-sky-500 h-1.5 rounded-full" style="width:<?php echo min(100,$cpu); ?>%"></div></div>
        </div>
        <div>
          <div class="flex justify-between text-xs mb-1">
            <span class="text-gray-400 font-medium flex items-center gap-1"><i class="fas fa-memory text-purple-400 w-4"></i> <?php echo ($lang==='en'?'Memory':'Mémoire'); ?></span>
            <span class="font-mono text-gray-300"><?php echo $ram_mb; ?> MB</span>
          </div>
          <div class="w-full bg-white/5 rounded-full h-1.5"><div class="bg-purple-500 h-1.5 rounded-full" style="width:<?php echo min(100,($ram_mb/10240)*100); ?>%"></div></div>
        </div>
        <div>
          <div class="flex justify-between text-xs mb-1">
            <span class="text-gray-400 font-medium flex items-center gap-1"><i class="fas fa-hdd text-emerald-400 w-4"></i> Disque SSD</span>
            <span class="font-mono text-gray-300"><?php echo $disk_gb; ?> GB</span>
          </div>
          <div class="w-full bg-white/5 rounded-full h-1.5"><div class="bg-emerald-500 h-1.5 rounded-full" style="width:<?php echo min(100,($disk_gb/40)*100); ?>%"></div></div>
        </div>
        <div class="pt-2 border-t border-white/[0.03]">
          <div class="flex justify-between items-center text-xs">
            <span class="text-gray-400 font-medium flex items-center gap-1"><i class="fas fa-network-wired text-amber-400 w-4"></i> Ports</span>
            <span class="px-2 py-0.5 bg-amber-500/10 text-amber-400 border border-amber-500/20 font-mono font-bold rounded-md text-[11px]"><?php echo $ports_count; ?> allocation(s)</span>
          </div>
          <?php if (!empty($allocations_list)): ?>
            <p class="text-[11px] text-gray-500 font-mono mt-1.5 truncate"><?php echo implode(', ',$allocations_list); ?></p>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <?php if (!empty($identifier)): ?>
    <div class="grid grid-cols-2 gap-2 mt-6 pt-4 border-t border-white/[0.05]">
      <a href="?action=start&uuid=<?php echo urlencode($identifier); ?>"   class="bg-green-600 hover:bg-green-500 text-white py-2 px-3 rounded-xl text-sm font-semibold text-center flex items-center justify-center gap-1.5 transition"><i class="fas fa-play text-xs"></i> Start</a>
      <a href="?action=stop&uuid=<?php echo urlencode($identifier); ?>"    class="bg-red-600 hover:bg-red-500 text-white py-2 px-3 rounded-xl text-sm font-semibold text-center flex items-center justify-center gap-1.5 transition"><i class="fas fa-stop text-xs"></i> Stop</a>
      <a href="?action=restart&uuid=<?php echo urlencode($identifier); ?>" class="bg-amber-600 hover:bg-amber-500 text-white py-2 px-3 rounded-xl text-sm font-semibold text-center flex items-center justify-center gap-1.5 transition"><i class="fas fa-rotate text-xs"></i> Restart</a>
      <a href="?action=delete&uuid=<?php echo urlencode($identifier); ?>" onclick="return confirmDelete();" class="bg-rose-950/40 hover:bg-rose-900 border border-rose-500/30 text-rose-400 py-2 px-3 rounded-xl text-sm font-semibold text-center flex items-center justify-center gap-1.5 transition"><i class="fas fa-trash text-xs"></i> Delete</a>
      <a href="/client/servers/gérer/websftp/?uuid=<?php echo urlencode($identifier); ?>&dir=/" class="text-xs bg-emerald-600/20 hover:bg-emerald-600 text-emerald-300 hover:text-white px-3 py-2 rounded-xl flex items-center justify-center gap-2 font-semibold transition col-span-2"><i class="fas fa-code"></i> File Manager</a>
    </div>
    <a href="/client/servers/gérer/?uuid=<?php echo urlencode($identifier); ?>" class="mt-2 block bg-slate-700 hover:bg-slate-600 text-white py-2 px-3 rounded-xl text-sm font-semibold text-center transition"><?php echo t('servers.manage_full'); ?></a>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
  </div>
  <?php endif; ?>
</main>

<div class="fixed bottom-6 right-6 z-50">
  <a href="https://heberge.orinstone.deepstone.fr/discord/" target="_blank" class="bg-[#5865F2] hover:bg-[#4752C4] transition text-white px-5 py-4 rounded-full font-bold flex items-center gap-2 shadow-2xl hover:scale-105 transform duration-200">
    <i class="fab fa-discord text-xl"></i>
    <span class="hidden sm:inline text-sm"><?php echo t('discord.help'); ?></span>
  </a>
</div>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/inc/footer.php'; ?>
</body>
</html>

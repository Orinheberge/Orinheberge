<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/lang.php';
if (!isset($_SESSION['user_id'])) { header('Location: /login/'); exit(); }

$is_logged_in = true;
$panel_url      = 'https://panel.orinstone.deepstone.fr';
$api_key_client = 'ptlc_MfJSOUID0bnTgFCmm5VvYMML2jKUUA5RFZ2n2MeZCSU';
$headers_client = ["Authorization: Bearer $api_key_client","Accept: application/vnd.pterodactyl.v1+json","Content-Type: application/json"];
$api_key_admin  = 'ptla_YKix8PexQDCZ7nIeexST3NXC2sFwQAoefDtOQBvJkbx';
$headers_admin  = ["Authorization: Bearer $api_key_admin","Accept: application/vnd.pterodactyl.v1+json","Content-Type: application/json"];

try {
    $pdo = new PDO('mysql:host=pma.orinstone.deepstone.fr;dbname=s43_orinheberge;charset=utf8mb4','root','1504',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) { die('Erreur BDD'); }

// Infos utilisateur
$stmt = $pdo->prepare('SELECT * FROM users WHERE id=? LIMIT 1');
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$_SESSION['username'] = !empty($user['pseudo']) ? $user['pseudo'] : ($user['firstname'] ?? 'Utilisateur');
$_SESSION['avatar']   = $user['avatar'] ?? '';

// Serveurs
$stmt = $pdo->prepare('SELECT * FROM orders WHERE user_id=? ORDER BY created_at DESC');
$stmt->execute([$_SESSION['user_id']]);
$servers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Tickets ouverts
$stmt2 = $pdo->prepare("SELECT COUNT(*) FROM support_tickets WHERE user_id=? AND status='open'");
$stmt2->execute([$_SESSION['user_id']]);
$open_tickets = (int)$stmt2->fetchColumn();

function clientApi($pu,$h,$ep,$m='GET',$d=null){$ch=curl_init($pu.'/api/client/'.$ep);curl_setopt_array($ch,[CURLOPT_HTTPHEADER=>$h,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>10,CURLOPT_SSL_VERIFYPEER=>false,CURLOPT_SSL_VERIFYHOST=>false]);if($m==='POST'){curl_setopt($ch,CURLOPT_POST,true);curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($d));}$r=curl_exec($ch);$c=curl_getinfo($ch,CURLINFO_HTTP_CODE);if(curl_errno($ch))return['error'=>true,'http_code'=>$c];return $r?json_decode($r,true):['error'=>true,'http_code'=>$c];}
function adminApi($pu,$h,$ep,$m='GET',$d=null){$ch=curl_init($pu.'/api/application/'.$ep);curl_setopt_array($ch,[CURLOPT_HTTPHEADER=>$h,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>10,CURLOPT_SSL_VERIFYPEER=>false,CURLOPT_SSL_VERIFYHOST=>false]);if($m==='DELETE')curl_setopt($ch,CURLOPT_CUSTOMREQUEST,'DELETE');$r=curl_exec($ch);$c=curl_getinfo($ch,CURLINFO_HTTP_CODE);if(curl_errno($ch))return['error'=>true];if($c===204)return true;return $r?json_decode($r,true):['error'=>true];}

$api_message = '';
if (isset($_GET['action'], $_GET['uuid'])) {
    $uuid=$_GET['uuid'];$action=$_GET['action'];
    $s=$pdo->prepare('SELECT uuid,server_id FROM orders WHERE user_id=? AND uuid=?');
    $s->execute([$_SESSION['user_id'],$uuid]);$sv=$s->fetch(PDO::FETCH_ASSOC);
    if ($sv) {
        $id=$sv['uuid'];$short=substr($id,0,8);$num=$sv['server_id'];
        switch($action){
            case 'start':   clientApi($panel_url,$headers_client,"servers/$short/power",'POST',['signal'=>'start']); break;
            case 'stop':    clientApi($panel_url,$headers_client,"servers/$short/power",'POST',['signal'=>'stop']); break;
            case 'restart': clientApi($panel_url,$headers_client,"servers/$short/power",'POST',['signal'=>'restart']); break;
            case 'delete':  $r=adminApi($panel_url,$headers_admin,"servers/$num",'DELETE');if($r===true||!isset($r['errors']))$pdo->prepare('DELETE FROM orders WHERE uuid=?')->execute([$id]);break;
        }
        $_SESSION['api_success']="Action '".strtoupper($action)."' transmise !";
    } else {
        $_SESSION['api_error']='Sécurité : ce serveur ne vous appartient pas.';
    }
    header('Location: /client/servers/'); exit();
}
if (isset($_SESSION['api_error']))   { $api_message="<div class='bg-red-500/10 text-red-400 border border-red-500/20 p-4 rounded-2xl mb-6 text-sm flex items-center gap-2'><i class='fas fa-triangle-exclamation'></i>".$_SESSION['api_error']."</div>"; unset($_SESSION['api_error']); }
elseif (isset($_SESSION['api_success'])) { $api_message="<div class='bg-green-500/10 text-green-400 border border-green-500/20 p-4 rounded-2xl mb-6 text-sm flex items-center gap-2'><i class='fas fa-check-circle'></i>".$_SESSION['api_success']."</div>"; unset($_SESSION['api_success']); }

// Stats serveurs
$total   = count($servers);
$running = 0;
$server_data = [];
foreach ($servers as $srv) {
    $id = $srv['uuid'] ?? '';
    $entry = ['server' => $srv, 'status' => 'offline', 'ram_mb' => 0, 'cpu' => 0, 'disk_gb' => 0, 'ports' => [], 'ports_count' => 0];
    if (!empty($id)) {
        $res = clientApi($panel_url,$headers_client,"servers/".substr($id,0,8)."/resources");
        if (isset($res['attributes'])) {
            $entry['status']  = $res['attributes']['current_state'] ?? 'offline';
            $entry['ram_mb']  = round(($res['attributes']['resources']['memory_bytes']??0)/1024/1024);
            $entry['cpu']     = round($res['attributes']['resources']['cpu_absolute']??0,1);
            $entry['disk_gb'] = round(($res['attributes']['resources']['disk_bytes']??0)/1024/1024/1024,2);
        }
        $det = clientApi($panel_url,$headers_client,"servers/".substr($id,0,8));
        if (isset($det['attributes']['relationships']['allocations']['data'])) {
            foreach ($det['attributes']['relationships']['allocations']['data'] as $al) {
                if (isset($al['attributes']['port'])) $entry['ports'][]=$al['attributes']['port'].($al['attributes']['is_default']?' ★':'');
            }
            $entry['ports_count']=count($entry['ports']);
        }
    }
    if ($entry['status']==='running') $running++;
    $server_data[] = $entry;
}
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Espace Client — OrinHeberge</title>
    <link rel="icon" type="image/png" href="/favicon.ico">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="manifest" href="/manifest.json">
    <style>
        body{background:#080c14;scroll-behavior:smooth;}
        .glass{background:rgba(255,255,255,0.035);backdrop-filter:blur(16px);border:1px solid rgba(255,255,255,0.07);}
        .gradient-text{background:linear-gradient(135deg,#38bdf8,#818cf8);-webkit-background-clip:text;-webkit-text-fill-color:transparent;}
        .card-hover{transition:transform .2s,border-color .2s,box-shadow .2s;}
        .card-hover:hover{transform:translateY(-3px);box-shadow:0 16px 40px rgba(0,0,0,.3);border-color:rgba(56,189,248,.2);}
        #mobileMenu{display:none;}#mobileMenu.active{display:block;}
        .sidebar-link{display:flex;align-items:center;gap:.75rem;padding:.6rem 1rem;border-radius:.75rem;font-size:.8rem;font-weight:600;transition:all .15s;color:#6b7280;}
        .sidebar-link:hover,.sidebar-link.active{background:rgba(56,189,248,.08);color:#38bdf8;}
        .sidebar-link.active{border-left:2px solid #38bdf8;padding-left:.875rem;}
    </style>
    <script>
        function toggleMenu(){document.getElementById('mobileMenu').classList.toggle('active');}
        function confirmDelete(){return confirm('⚠️ Supprimer définitivement ce serveur ? Action irréversible.');}
    </script>
</head>
<body class="text-gray-200 font-sans min-h-screen flex flex-col antialiased">

<?php $active_nav = 'servers'; include $_SERVER['DOCUMENT_ROOT'] . '/inc/navbar.php'; ?>

<div class="flex flex-1 max-w-7xl mx-auto w-full px-4 sm:px-6 py-8 gap-6">

    <!-- ══ SIDEBAR ══ -->
    <aside class="hidden lg:flex flex-col w-56 shrink-0 gap-2">
        <!-- Avatar card -->
        <div class="glass rounded-2xl p-5 flex flex-col items-center text-center mb-2">
            <?php if (!empty($_SESSION['avatar']) && file_exists($_SERVER['DOCUMENT_ROOT'].'/'.$_SESSION['avatar'])): ?>
                <img src="/<?php echo htmlspecialchars($_SESSION['avatar']); ?>" class="w-16 h-16 rounded-full object-cover border-2 border-sky-500/40 mb-3">
            <?php else: ?>
                <div class="w-16 h-16 rounded-full bg-sky-500/10 border-2 border-sky-500/30 flex items-center justify-center mb-3">
                    <i class="fas fa-user text-sky-400 text-2xl"></i>
                </div>
            <?php endif; ?>
            <p class="font-bold text-white text-sm truncate w-full"><?php echo htmlspecialchars($_SESSION['username']); ?></p>
            <p class="text-gray-500 text-xs mt-0.5"><?php echo htmlspecialchars($user['email'] ?? ''); ?></p>
        </div>

        <!-- Nav -->
        <nav class="glass rounded-2xl p-3 flex flex-col gap-1">
            <p class="text-[10px] font-bold text-gray-600 uppercase tracking-widest px-3 mb-1">Menu</p>
            <a href="/client/servers/" class="sidebar-link active"><i class="fas fa-server w-4 text-center"></i> Mes serveurs</a>
            <a href="/shop/" class="sidebar-link"><i class="fas fa-plus w-4 text-center"></i> Nouveau serveur</a>
            <a href="/profil/" class="sidebar-link"><i class="fas fa-user-cog w-4 text-center"></i> Mon profil</a>
            <a href="/support/" class="sidebar-link"><i class="fas fa-headset w-4 text-center"></i> Support
                <?php if ($open_tickets > 0): ?><span class="ml-auto bg-sky-500 text-slate-950 text-[10px] font-black px-1.5 py-0.5 rounded-full"><?php echo $open_tickets; ?></span><?php endif; ?>
            </a>
            <hr class="border-white/5 my-1">
            <a href="<?php echo $panel_url; ?>" target="_blank" class="sidebar-link"><i class="fas fa-cogs w-4 text-center"></i> Panel Pterodactyl</a>
            <a href="https://php.orinstone.deepstone.fr" target="_blank" class="sidebar-link"><i class="fas fa-database w-4 text-center"></i> phpMyAdmin</a>
            <hr class="border-white/5 my-1">
            <a href="/logout/" class="sidebar-link text-red-400 hover:text-red-300 hover:bg-red-500/10"><i class="fas fa-sign-out-alt w-4 text-center"></i> Déconnexion</a>
        </nav>
    </aside>

    <!-- ══ MAIN CONTENT ══ -->
    <main class="flex-1 min-w-0">
        <?php echo $api_message; ?>

        <!-- Welcome bar -->
        <div class="flex items-center justify-between gap-4 mb-6 flex-wrap">
            <div>
                <h1 class="text-2xl font-black">Bonjour, <span class="gradient-text"><?php echo htmlspecialchars($_SESSION['username']); ?></span> 👋</h1>
                <p class="text-gray-500 text-sm mt-0.5">Bienvenue dans votre espace client OrinHeberge.</p>
            </div>
            <a href="/shop/" class="bg-sky-600 hover:bg-sky-500 text-white px-4 py-2.5 rounded-xl text-xs font-bold transition flex items-center gap-2 shadow-lg shadow-sky-900/20">
                <i class="fas fa-plus"></i> Nouveau serveur
            </a>
        </div>

        <!-- Stats rapides -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
            <div class="glass rounded-2xl p-4">
                <p class="text-gray-500 text-xs mb-1">Total serveurs</p>
                <p class="text-2xl font-black text-white"><?php echo $total; ?></p>
            </div>
            <div class="glass rounded-2xl p-4">
                <p class="text-gray-500 text-xs mb-1">En ligne</p>
                <p class="text-2xl font-black text-green-400"><?php echo $running; ?></p>
            </div>
            <div class="glass rounded-2xl p-4">
                <p class="text-gray-500 text-xs mb-1">Hors ligne</p>
                <p class="text-2xl font-black text-red-400"><?php echo $total - $running; ?></p>
            </div>
            <div class="glass rounded-2xl p-4">
                <p class="text-gray-500 text-xs mb-1">Tickets ouverts</p>
                <p class="text-2xl font-black <?php echo $open_tickets > 0 ? 'text-sky-400' : 'text-gray-600'; ?>"><?php echo $open_tickets; ?></p>
            </div>
        </div>

        <!-- Titre section -->
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-black flex items-center gap-2"><i class="fas fa-server text-sky-400"></i> Mes serveurs</h2>
            <span class="glass px-3 py-1 rounded-full text-xs text-gray-500"><?php echo $total; ?> machine<?php echo $total > 1 ? 's' : ''; ?></span>
        </div>

        <?php if (empty($servers)): ?>
        <div class="glass p-16 rounded-2xl text-center">
            <div class="w-16 h-16 bg-sky-500/10 rounded-2xl flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-server text-sky-400 text-2xl"></i>
            </div>
            <h3 class="font-bold text-white mb-2">Aucun serveur pour l'instant</h3>
            <p class="text-gray-500 text-sm mb-6">Déployez votre premier serveur gratuitement en quelques secondes.</p>
            <a href="/shop/" class="bg-sky-600 hover:bg-sky-500 text-white px-6 py-3 rounded-xl font-bold transition inline-flex items-center gap-2 text-sm">
                <i class="fas fa-rocket"></i> Voir les offres
            </a>
        </div>
        <?php else: ?>
        <div class="grid md:grid-cols-2 xl:grid-cols-3 gap-4">
        <?php foreach ($server_data as $entry):
            $server = $entry['server'];
            $status = $entry['status'];
            $id = $server['uuid'] ?? '';
            $short_id = $server['id_server_panel'] ?? 'N/A';
            $sm = [
                'running'  => ['text'=>'En ligne',   'class'=>'bg-green-500/10 text-green-400 border-green-500/20',  'dot'=>'bg-green-400 animate-pulse'],
                'starting' => ['text'=>'Démarrage',  'class'=>'bg-amber-500/10 text-amber-400 border-amber-500/20',  'dot'=>'bg-amber-400 animate-pulse'],
                'stopping' => ['text'=>'Extinction', 'class'=>'bg-amber-500/10 text-amber-400 border-amber-500/20',  'dot'=>'bg-amber-400'],
                'offline'  => ['text'=>'Hors ligne', 'class'=>'bg-red-500/10 text-red-400 border-red-500/20',        'dot'=>'bg-red-400'],
            ][$status] ?? ['text'=>'Inconnu','class'=>'bg-gray-500/10 text-gray-400 border-gray-500/20','dot'=>'bg-gray-400'];
        ?>
        <div class="glass rounded-2xl p-5 flex flex-col gap-4 card-hover border border-white/[0.05]">

            <!-- Header -->
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <h3 class="font-bold text-white truncate"><?php echo htmlspecialchars($server['service_name']); ?></h3>
                    <div class="flex items-center gap-2 mt-1">
                        <span class="text-gray-600 text-[11px] font-mono">#<?php echo htmlspecialchars($short_id); ?></span>
                        <?php if (!empty($id)): ?>
                        <a href="<?php echo $panel_url; ?>/server/<?php echo htmlspecialchars($short_id); ?>" target="_blank"
                           class="text-[10px] bg-sky-600/15 hover:bg-sky-600 border border-sky-500/25 text-sky-400 hover:text-white px-2 py-0.5 rounded-md font-bold transition flex items-center gap-1">
                            <i class="fas fa-arrow-up-right-from-square text-[9px]"></i> Gérer
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <span class="px-2.5 py-1 rounded-full text-[11px] font-semibold flex items-center gap-1.5 border shrink-0 <?php echo $sm['class']; ?>">
                    <span class="h-1.5 w-1.5 rounded-full <?php echo $sm['dot']; ?>"></span><?php echo $sm['text']; ?>
                </span>
            </div>

            <!-- Ressources -->
            <div class="space-y-3">
                <div>
                    <div class="flex justify-between text-[11px] mb-1 text-gray-500">
                        <span class="flex items-center gap-1"><i class="fas fa-microchip text-sky-400 w-3"></i> CPU</span>
                        <span class="font-mono text-gray-300"><?php echo $entry['cpu']; ?>%</span>
                    </div>
                    <div class="h-1 bg-white/5 rounded-full"><div class="h-1 bg-sky-500 rounded-full" style="width:<?php echo min(100,$entry['cpu']); ?>%"></div></div>
                </div>
                <div>
                    <div class="flex justify-between text-[11px] mb-1 text-gray-500">
                        <span class="flex items-center gap-1"><i class="fas fa-memory text-purple-400 w-3"></i> RAM</span>
                        <span class="font-mono text-gray-300"><?php echo $entry['ram_mb']; ?> MB</span>
                    </div>
                    <div class="h-1 bg-white/5 rounded-full"><div class="h-1 bg-purple-500 rounded-full" style="width:<?php echo min(100,($entry['ram_mb']/8192)*100); ?>%"></div></div>
                </div>
                <div>
                    <div class="flex justify-between text-[11px] mb-1 text-gray-500">
                        <span class="flex items-center gap-1"><i class="fas fa-hdd text-emerald-400 w-3"></i> Disque</span>
                        <span class="font-mono text-gray-300"><?php echo $entry['disk_gb']; ?> GB</span>
                    </div>
                    <div class="h-1 bg-white/5 rounded-full"><div class="h-1 bg-emerald-500 rounded-full" style="width:<?php echo min(100,($entry['disk_gb']/40)*100); ?>%"></div></div>
                </div>
                <?php if (!empty($entry['ports'])): ?>
                <div class="flex items-start gap-2 text-[11px] text-gray-500 pt-1 border-t border-white/[0.04]">
                    <i class="fas fa-network-wired text-amber-400 w-3 mt-0.5"></i>
                    <span class="font-mono text-gray-400"><?php echo implode(', ', $entry['ports']); ?></span>
                </div>
                <?php endif; ?>
            </div>

            <!-- Actions -->
            <?php if (!empty($id)): ?>
            <div class="grid grid-cols-3 gap-2 pt-3 border-t border-white/[0.04]">
                <a href="?action=start&uuid=<?php echo urlencode($id); ?>" class="bg-green-600/20 hover:bg-green-600 border border-green-500/25 text-green-400 hover:text-white py-2 rounded-xl text-xs font-bold text-center flex items-center justify-center gap-1.5 transition">
                    <i class="fas fa-play text-[10px]"></i> Start
                </a>
                <a href="?action=stop&uuid=<?php echo urlencode($id); ?>" class="bg-red-600/15 hover:bg-red-600 border border-red-500/20 text-red-400 hover:text-white py-2 rounded-xl text-xs font-bold text-center flex items-center justify-center gap-1.5 transition">
                    <i class="fas fa-stop text-[10px]"></i> Stop
                </a>
                <a href="?action=restart&uuid=<?php echo urlencode($id); ?>" class="bg-amber-600/15 hover:bg-amber-600 border border-amber-500/20 text-amber-400 hover:text-white py-2 rounded-xl text-xs font-bold text-center flex items-center justify-center gap-1.5 transition">
                    <i class="fas fa-rotate text-[10px]"></i> Restart
                </a>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <a href="/client/servers/gérer/websftp/?uuid=<?php echo urlencode($id); ?>&dir=/" class="bg-white/[0.03] hover:bg-emerald-600/20 border border-white/[0.06] hover:border-emerald-500/25 text-gray-400 hover:text-emerald-300 py-2 rounded-xl text-xs font-bold text-center flex items-center justify-center gap-1.5 transition">
                    <i class="fas fa-folder-open text-[10px]"></i> Fichiers
                </a>
                <a href="/client/servers/gérer/?uuid=<?php echo urlencode($id); ?>" class="bg-white/[0.03] hover:bg-sky-600/20 border border-white/[0.06] hover:border-sky-500/25 text-gray-400 hover:text-sky-300 py-2 rounded-xl text-xs font-bold text-center flex items-center justify-center gap-1.5 transition">
                    <i class="fas fa-sliders text-[10px]"></i> Gérer
                </a>
            </div>
            <a href="?action=delete&uuid=<?php echo urlencode($id); ?>" onclick="return confirmDelete();" class="text-[11px] text-center text-gray-600 hover:text-red-400 transition py-1">
                <i class="fas fa-trash text-[10px] mr-1"></i> Supprimer ce serveur
            </a>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </main>
</div>

<div class="fixed bottom-6 right-6 z-50">
    <a href="/discord/" target="_blank" class="bg-[#5865F2] hover:bg-[#4752C4] transition text-white px-5 py-4 rounded-full font-bold flex items-center gap-2 shadow-2xl hover:scale-105 transform duration-200">
        <i class="fab fa-discord text-xl"></i>
        <span class="hidden sm:inline text-sm">Support Discord</span>
    </a>
</div>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/inc/footer.php'; ?>
</body>
</html>

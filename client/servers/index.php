<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/lang.php';
if (!isset($_SESSION['user_id'])) { header('Location: /login/'); exit(); }

// ── DB ────────────────────────────────────────────────────────────────────────
try {
    $pdo = new PDO('mysql:host=localhost;dbname=s43_orinheberge;charset=utf8mb4', 'root', '1504', [
        PDO::ATTR_ERRMODE           => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) { die('Erreur BDD'); }

// ── Settings depuis BDD ───────────────────────────────────────────────────────
$cfg = [];
foreach ($pdo->query('SELECT `key`, `value` FROM settings') as $row) $cfg[$row['key']] = $row['value'];
$panel_url      = $cfg['panel_url']      ?? 'https://panel.orinstone.deepstone.fr';
$api_key_client = $cfg['api_key_client'] ?? '';
$api_key_admin  = $cfg['api_key_admin']  ?? '';
$phpmyadmin_url = $cfg['phpmyadmin_url'] ?? 'https://php.orinstone.deepstone.fr';
$headers_client = ["Authorization: Bearer $api_key_client","Accept: application/vnd.pterodactyl.v1+json","Content-Type: application/json"];
$headers_admin  = ["Authorization: Bearer $api_key_admin","Accept: application/vnd.pterodactyl.v1+json","Content-Type: application/json"];

// ── Utilisateur ───────────────────────────────────────────────────────────────
$stmt = $pdo->prepare('SELECT * FROM users WHERE id=? LIMIT 1');
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
$_SESSION['username'] = !empty($user['pseudo']) ? $user['pseudo'] : ($user['firstname'] ?? 'Utilisateur');
$_SESSION['avatar']   = $user['avatar'] ?? '';
$is_admin = (bool)($user['is_admin'] ?? false);

// ── Serveurs ──────────────────────────────────────────────────────────────────
$stmt = $pdo->prepare('SELECT * FROM orders WHERE user_id=? ORDER BY created_at DESC');
$stmt->execute([$_SESSION['user_id']]);
$servers = $stmt->fetchAll();

// ── Tickets ouverts ───────────────────────────────────────────────────────────
$stmt2 = $pdo->prepare("SELECT COUNT(*) FROM support_tickets WHERE user_id=? AND status != 'Fermé'");
$stmt2->execute([$_SESSION['user_id']]);
$open_tickets = (int)$stmt2->fetchColumn();

// ── Helpers API ───────────────────────────────────────────────────────────────
function clientApi($pu, $h, $ep, $m = 'GET', $d = null) {
    $ch = curl_init($pu . '/api/client/' . $ep);
    curl_setopt_array($ch, [CURLOPT_HTTPHEADER=>$h, CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>10, CURLOPT_SSL_VERIFYPEER=>false, CURLOPT_SSL_VERIFYHOST=>false]);
    if ($m === 'POST') { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($d)); }
    $r = curl_exec($ch); $c = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if (curl_errno($ch)) return ['error' => true];
    return $r ? json_decode($r, true) : ['error' => true];
}
function adminApi($pu, $h, $ep, $m = 'GET') {
    $ch = curl_init($pu . '/api/application/' . $ep);
    curl_setopt_array($ch, [CURLOPT_HTTPHEADER=>$h, CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>10, CURLOPT_SSL_VERIFYPEER=>false, CURLOPT_SSL_VERIFYHOST=>false]);
    if ($m === 'DELETE') curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    $r = curl_exec($ch); $c = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if ($c === 204) return true;
    return $r ? json_decode($r, true) : ['error' => true];
}

// ── Actions power/delete ──────────────────────────────────────────────────────
$api_message = '';
if (isset($_GET['action'], $_GET['uuid'])) {
    $uuid   = $_GET['uuid'];
    $action = $_GET['action'];
    $s = $pdo->prepare('SELECT uuid, server_id FROM orders WHERE user_id=? AND uuid=?');
    $s->execute([$_SESSION['user_id'], $uuid]);
    $sv = $s->fetch();
    if ($sv) {
        $short = substr($sv['uuid'], 0, 8);
        switch ($action) {
            case 'start':   clientApi($panel_url, $headers_client, "servers/$short/power", 'POST', ['signal' => 'start']); break;
            case 'stop':    clientApi($panel_url, $headers_client, "servers/$short/power", 'POST', ['signal' => 'stop']); break;
            case 'restart': clientApi($panel_url, $headers_client, "servers/$short/power", 'POST', ['signal' => 'restart']); break;
            case 'delete':
                $r = adminApi($panel_url, $headers_admin, "servers/{$sv['server_id']}", 'DELETE');
                if ($r === true || !isset($r['errors'])) {
                    $pdo->prepare('DELETE FROM orders WHERE uuid=? AND user_id=?')->execute([$uuid, $_SESSION['user_id']]);
                    $_SESSION['api_success'] = 'Serveur supprimé avec succès.';
                } else {
                    $_SESSION['api_error'] = 'Échec de la suppression. Réessayez.';
                }
                break;
        }
        if ($action !== 'delete') $_SESSION['api_success'] = "Action '" . strtoupper($action) . "' transmise.";
    } else {
        $_SESSION['api_error'] = 'Sécurité : ce serveur ne vous appartient pas.';
    }
    header('Location: /client/servers/'); exit();
}
if (isset($_SESSION['api_error']))   { $api_message = "<div style='background:rgba(239,68,68,.1);color:#ef4444;border:1px solid rgba(239,68,68,.2);border-radius:.75rem;padding:.875rem 1.25rem;margin-bottom:1.25rem;font-size:.82rem;display:flex;align-items:center;gap:.5rem'><i class='fas fa-triangle-exclamation'></i> ".$_SESSION['api_error']."</div>"; unset($_SESSION['api_error']); }
elseif (isset($_SESSION['api_success'])) { $api_message = "<div style='background:rgba(34,197,94,.1);color:#22c55e;border:1px solid rgba(34,197,94,.2);border-radius:.75rem;padding:.875rem 1.25rem;margin-bottom:1.25rem;font-size:.82rem;display:flex;align-items:center;gap:.5rem'><i class='fas fa-check-circle'></i> ".$_SESSION['api_success']."</div>"; unset($_SESSION['api_success']); }

// ── Stats live via API Pterodactyl ────────────────────────────────────────────
$server_data = [];
$running = 0;
foreach ($servers as $srv) {
    $id    = $srv['uuid'] ?? '';
    $short = substr($id, 0, 8);
    $entry = ['server' => $srv, 'status' => 'offline', 'ram_mb' => 0, 'cpu' => 0, 'disk_gb' => 0, 'ports' => []];
    if (!empty($id) && !empty($api_key_client)) {
        $res = clientApi($panel_url, $headers_client, "servers/$short/resources");
        if (isset($res['attributes'])) {
            $entry['status']  = $res['attributes']['current_state'] ?? 'offline';
            $entry['ram_mb']  = round(($res['attributes']['resources']['memory_bytes'] ?? 0) / 1024 / 1024);
            $entry['cpu']     = round($res['attributes']['resources']['cpu_absolute'] ?? 0, 1);
            $entry['disk_gb'] = round(($res['attributes']['resources']['disk_bytes'] ?? 0) / 1024 / 1024 / 1024, 2);
        }
        $det = clientApi($panel_url, $headers_client, "servers/$short");
        if (isset($det['attributes']['relationships']['allocations']['data'])) {
            foreach ($det['attributes']['relationships']['allocations']['data'] as $al) {
                if (isset($al['attributes']['port'])) {
                    $entry['ports'][] = ($al['attributes']['ip_alias'] ?? $al['attributes']['ip']) . ':' . $al['attributes']['port'] . ($al['attributes']['is_default'] ? ' ★' : '');
                }
            }
        }
    }
    if ($entry['status'] === 'running') $running++;
    $server_data[] = $entry;
}
$total = count($servers);
?>
<!DOCTYPE html>
<html lang="<?php echo $lang ?? 'fr'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OrinHeberge — Mes serveurs</title>
    <link rel="icon" type="image/png" href="/favicon.ico">
    <link rel="manifest" href="/manifest.json">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root{--sidebar:240px;}
        *{box-sizing:border-box;}
        body{background:#0d0f14;color:#e2e8f0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',system-ui,sans-serif;min-height:100vh;}
        .sidebar{position:fixed;top:0;left:0;width:var(--sidebar);height:100vh;background:#111318;border-right:1px solid rgba(255,255,255,.06);display:flex;flex-direction:column;z-index:40;overflow-y:auto;}
        .sidebar-logo{padding:1.5rem 1.25rem 1rem;border-bottom:1px solid rgba(255,255,255,.05);}
        .sidebar-nav{padding:.75rem .75rem;flex:1;}
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
        .stat-card{background:linear-gradient(135deg,#161a22,#1a1f2a);border:1px solid rgba(255,255,255,.07);border-radius:.875rem;padding:1.25rem;}
        .badge{display:inline-flex;align-items:center;gap:.35rem;padding:.2rem .65rem;border-radius:9999px;font-size:.72rem;font-weight:600;}
        .badge-green{background:rgba(34,197,94,.1);color:#22c55e;border:1px solid rgba(34,197,94,.2);}
        .badge-orange{background:rgba(249,115,22,.1);color:#f97316;border:1px solid rgba(249,115,22,.2);}
        .badge-red{background:rgba(239,68,68,.1);color:#ef4444;border:1px solid rgba(239,68,68,.2);}
        .badge-gray{background:rgba(107,114,128,.1);color:#9ca3af;border:1px solid rgba(107,114,128,.2);}
        .badge-blue{background:rgba(56,189,248,.1);color:#38bdf8;border:1px solid rgba(56,189,248,.2);}
        .service-row{display:flex;align-items:center;gap:1rem;padding:.9rem 1.25rem;border-bottom:1px solid rgba(255,255,255,.04);transition:background .15s;}
        .service-row:last-child{border-bottom:none;}
        .service-row:hover{background:rgba(255,255,255,.02);}
        .service-icon{width:2.25rem;height:2.25rem;border-radius:.625rem;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:.9rem;}
        .btn-sm{display:inline-flex;align-items:center;gap:.35rem;padding:.3rem .75rem;border-radius:.5rem;font-size:.74rem;font-weight:600;transition:all .15s;border:1px solid transparent;text-decoration:none;}
        .btn-primary{background:rgba(56,189,248,.12);color:#38bdf8;border-color:rgba(56,189,248,.2);}
        .btn-primary:hover{background:#0ea5e9;color:#fff;border-color:#0ea5e9;}
        .btn-green{background:rgba(34,197,94,.1);color:#22c55e;border-color:rgba(34,197,94,.2);}
        .btn-green:hover{background:#16a34a;color:#fff;border-color:#16a34a;}
        .btn-red{background:rgba(239,68,68,.08);color:#ef4444;border-color:rgba(239,68,68,.15);}
        .btn-red:hover{background:#dc2626;color:#fff;border-color:#dc2626;}
        .btn-amber{background:rgba(245,158,11,.08);color:#f59e0b;border-color:rgba(245,158,11,.15);}
        .btn-amber:hover{background:#d97706;color:#fff;border-color:#d97706;}
        .mobile-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:39;}
        @media(max-width:768px){
            .sidebar{transform:translateX(-100%);transition:transform .25s;}
            .sidebar.open{transform:translateX(0);}
            .mobile-overlay.open{display:block;}
            .main-content{margin-left:0;}
            .topbar{padding:.75rem 1rem;}
            .content{padding:1rem;}
            .res-cols{display:none;}
        }
    </style>
    <script>
        function toggleSidebar(){document.getElementById('sidebar').classList.toggle('open');document.getElementById('overlay').classList.toggle('open');}
        function confirmDelete(name){return confirm('Supprimer le serveur "'+name+'" ?\nCette action est irréversible.');}
    </script>
</head>
<body>

<div id="overlay" class="mobile-overlay" onclick="toggleSidebar()"></div>

<!-- ══ SIDEBAR ══ -->
<aside id="sidebar" class="sidebar">
    <div class="sidebar-logo">
        <a href="/" class="flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-lg bg-sky-500/20 flex items-center justify-center">
                <i class="fas fa-server text-sky-400 text-sm"></i>
            </div>
            <span class="font-black text-white text-base tracking-tight">OrinHeberge</span>
        </a>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section">Principal</div>
        <a href="/client/" class="nav-item">
            <i class="fas fa-home icon"></i> Tableau de bord
        </a>
        <a href="/client/servers/" class="nav-item active">
            <i class="fas fa-server icon"></i> Mes serveurs
        </a>
        <a href="/offres/" class="nav-item">
            <i class="fas fa-tags icon"></i> Nos offres
        </a>

        <div class="nav-separator"></div>
        <div class="nav-section">Compte</div>
        <a href="/profil/" class="nav-item">
            <i class="fas fa-user icon"></i> Mon profil
        </a>
        <a href="/client/billing/" class="nav-item">
            <i class="fas fa-file-invoice-dollar icon"></i> Facturation
        </a>
        <a href="/support/" class="nav-item">
            <i class="fas fa-headset icon"></i> Support
            <?php if ($open_tickets > 0): ?>
            <span class="ml-auto text-[10px] bg-purple-500/20 text-purple-400 border border-purple-500/20 px-1.5 py-0.5 rounded-full font-bold"><?php echo $open_tickets; ?></span>
            <?php endif; ?>
        </a>
        <a href="/status/" class="nav-item">
            <i class="fas fa-signal icon"></i> Statut
        </a>


        <div class="nav-separator"></div>
        <div class="nav-section">Outils</div>
        <a href="<?php echo htmlspecialchars($panel_url); ?>" target="_blank" class="nav-item">
            <i class="fas fa-cogs icon"></i> Panel Pterodactyl
        </a>
        <a href="<?php echo htmlspecialchars($phpmyadmin_url); ?>" target="_blank" class="nav-item">
            <i class="fas fa-database icon"></i> phpMyAdmin
        </a>
    </nav>

    <div class="sidebar-footer">
        <a href="/profil/" class="flex items-center gap-2.5 group mb-2">
            <?php if (!empty($_SESSION['avatar']) && file_exists($_SERVER['DOCUMENT_ROOT'].'/'.$_SESSION['avatar'])): ?>
                <img src="/<?php echo htmlspecialchars($_SESSION['avatar']); ?>" class="w-8 h-8 rounded-full object-cover border border-white/10">
            <?php else: ?>
                <div class="w-8 h-8 rounded-full bg-sky-500/20 flex items-center justify-center text-sky-400 text-xs font-bold border border-white/10">
                    <?php echo strtoupper(substr($_SESSION['username'] ?? 'U', 0, 1)); ?>
                </div>
            <?php endif; ?>
            <div class="flex-1 min-w-0">
                <div class="text-xs font-semibold text-white truncate"><?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?></div>
                <div class="text-[10px] text-gray-500">Mon profil</div>
            </div>
        </a>
        <a href="/logout/" class="nav-item" style="color:#ef4444;">
            <i class="fas fa-sign-out-alt icon"></i> Déconnexion
        </a>
    </div>
</aside>

<!-- ══ MAIN ══ -->
<div class="main-content">

    <!-- Topbar -->
    <div class="topbar">
        <div class="flex items-center gap-3">
            <button onclick="toggleSidebar()" class="md:hidden text-gray-400 hover:text-white text-lg w-8">
                <i class="fas fa-bars"></i>
            </button>
            <div>
                <div class="text-sm font-bold text-white">Mes serveurs</div>
                <div class="text-xs text-gray-500"><?php echo $total; ?> machine<?php echo $total !== 1 ? 's' : ''; ?> déployée<?php echo $total !== 1 ? 's' : ''; ?></div>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <a href="/offres/" class="hidden sm:flex items-center gap-2 bg-sky-600 hover:bg-sky-500 text-white text-xs font-bold px-4 py-2 rounded-lg transition">
                <i class="fas fa-plus text-[10px]"></i> Nouveau serveur
            </a>
            <a href="/profil/" class="w-8 h-8 rounded-full overflow-hidden border border-white/10 flex items-center justify-center bg-sky-500/10 shrink-0">
                <?php if (!empty($_SESSION['avatar']) && file_exists($_SERVER['DOCUMENT_ROOT'].'/'.$_SESSION['avatar'])): ?>
                    <img src="/<?php echo htmlspecialchars($_SESSION['avatar']); ?>" class="w-full h-full object-cover">
                <?php else: ?>
                    <span class="text-sky-400 text-xs font-bold"><?php echo strtoupper(substr($_SESSION['username'] ?? 'U', 0, 1)); ?></span>
                <?php endif; ?>
            </a>
        </div>
    </div>

    <!-- Content -->
    <div class="content">
        <?php echo $api_message; ?>

        <!-- Stats rapides -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="stat-card">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs text-gray-500 font-medium">Total</span>
                    <div class="w-7 h-7 rounded-lg bg-sky-500/15 flex items-center justify-center"><i class="fas fa-server text-sky-400 text-xs"></i></div>
                </div>
                <div class="text-2xl font-black text-white"><?php echo $total; ?></div>
                <div class="text-xs text-gray-500 mt-1">Serveurs</div>
            </div>
            <div class="stat-card">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs text-gray-500 font-medium">En ligne</span>
                    <div class="w-7 h-7 rounded-lg bg-green-500/15 flex items-center justify-center"><i class="fas fa-circle text-green-400 text-xs"></i></div>
                </div>
                <div class="text-2xl font-black text-green-400"><?php echo $running; ?></div>
                <div class="text-xs text-gray-500 mt-1">Actifs</div>
            </div>
            <div class="stat-card">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs text-gray-500 font-medium">Hors ligne</span>
                    <div class="w-7 h-7 rounded-lg bg-red-500/15 flex items-center justify-center"><i class="fas fa-circle text-red-400 text-xs"></i></div>
                </div>
                <div class="text-2xl font-black text-red-400"><?php echo $total - $running; ?></div>
                <div class="text-xs text-gray-500 mt-1">Inactifs</div>
            </div>
            <div class="stat-card">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs text-gray-500 font-medium">Tickets</span>
                    <div class="w-7 h-7 rounded-lg bg-purple-500/15 flex items-center justify-center"><i class="fas fa-headset text-purple-400 text-xs"></i></div>
                </div>
                <div class="text-2xl font-black <?php echo $open_tickets > 0 ? 'text-purple-400' : 'text-gray-600'; ?>"><?php echo $open_tickets; ?></div>
                <div class="text-xs text-gray-500 mt-1">Ouverts</div>
            </div>
        </div>

        <!-- Table serveurs -->
        <div class="card">
            <div class="flex items-center justify-between px-5 py-4 border-b border-white/[0.05]">
                <h2 class="text-sm font-bold text-white flex items-center gap-2">
                    <i class="fas fa-server text-sky-400 text-xs"></i> Mes serveurs
                </h2>
                <a href="/offres/" class="btn-sm btn-primary">
                    <i class="fas fa-plus text-[10px]"></i> Nouveau
                </a>
            </div>

            <?php if (empty($server_data)): ?>
            <div class="px-5 py-14 text-center">
                <div class="w-12 h-12 rounded-xl bg-sky-500/10 flex items-center justify-center mx-auto mb-3">
                    <i class="fas fa-server text-sky-400"></i>
                </div>
                <div class="text-sm font-semibold text-gray-300 mb-1">Aucun serveur</div>
                <div class="text-xs text-gray-500 mb-4">Déployez votre premier serveur gratuitement en quelques secondes.</div>
                <a href="/offres/" class="btn-sm btn-primary"><i class="fas fa-rocket text-[10px]"></i> Voir les offres</a>
            </div>
            <?php else: ?>

            <!-- En-têtes table (masqués sur mobile) -->
            <div class="res-cols grid border-b border-white/[0.05] px-5 py-2.5 text-[10px] font-bold text-gray-600 uppercase tracking-wider" style="grid-template-columns:2fr 1fr 1fr 1fr 1fr auto;">
                <span>Serveur</span><span>Statut</span><span>CPU</span><span>RAM</span><span>Disque</span><span>Actions</span>
            </div>

            <?php
            $status_map = [
                'running'  => ['text' => 'En ligne',   'badge' => 'badge-green',  'dot' => 'bg-green-400'],
                'starting' => ['text' => 'Démarrage',  'badge' => 'badge-orange', 'dot' => 'bg-amber-400'],
                'stopping' => ['text' => 'Extinction', 'badge' => 'badge-orange', 'dot' => 'bg-amber-400'],
                'offline'  => ['text' => 'Hors ligne', 'badge' => 'badge-red',    'dot' => 'bg-red-400'],
            ];
            foreach ($server_data as $entry):
                $server   = $entry['server'];
                $status   = $entry['status'];
                $id       = $server['uuid'] ?? '';
                $short_id = $server['id_server_panel'] ?? substr($id, 0, 8);
                $sm       = $status_map[$status] ?? ['text' => 'Inconnu', 'badge' => 'badge-gray', 'dot' => 'bg-gray-400'];
                $name     = htmlspecialchars($server['service_name'] ?? 'Serveur');

                // Icône selon type
                $sname_low  = strtolower($server['service_name'] ?? '');
                $icon_class = 'fas fa-server text-sky-400';
                $icon_bg    = 'bg-sky-500/15';
                if (str_contains($sname_low, 'minecraft') || str_contains($sname_low, 'mc'))   { $icon_class = 'fas fa-cube text-green-400';  $icon_bg = 'bg-green-500/15'; }
                elseif (str_contains($sname_low, 'node') || str_contains($sname_low, 'js'))    { $icon_class = 'fab fa-node-js text-green-400'; $icon_bg = 'bg-green-500/15'; }
                elseif (str_contains($sname_low, 'php'))                                        { $icon_class = 'fas fa-code text-blue-400';   $icon_bg = 'bg-blue-500/15'; }
                elseif (str_contains($sname_low, 'python'))                                     { $icon_class = 'fab fa-python text-yellow-400'; $icon_bg = 'bg-yellow-500/15'; }
            ?>
            <div class="service-row flex-wrap gap-y-3">
                <!-- Icône + nom -->
                <div class="flex items-center gap-3 min-w-0" style="flex:2">
                    <div class="service-icon <?php echo $icon_bg; ?>">
                        <i class="<?php echo $icon_class; ?> text-sm"></i>
                    </div>
                    <div class="min-w-0">
                        <div class="text-sm font-semibold text-white truncate"><?php echo $name; ?></div>
                        <div class="text-[10px] text-gray-500 font-mono"><?php echo htmlspecialchars($short_id); ?></div>
                        <?php if (!empty($entry['ports'])): ?>
                        <div class="text-[10px] text-gray-600 font-mono mt-0.5 truncate"><?php echo implode(' · ', array_slice($entry['ports'], 0, 2)); ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <!-- Statut -->
                <div class="flex items-center" style="flex:1">
                    <span class="badge <?php echo $sm['badge']; ?>">
                        <span class="w-1.5 h-1.5 rounded-full <?php echo $sm['dot']; ?><?php echo in_array($status, ['running','starting']) ? ' animate-pulse' : ''; ?>"></span>
                        <?php echo $sm['text']; ?>
                    </span>
                </div>
                <!-- Ressources -->
                <div class="res-cols flex items-center text-xs font-mono text-gray-400" style="flex:1"><?php echo $entry['cpu']; ?>%</div>
                <div class="res-cols flex items-center text-xs font-mono text-gray-400" style="flex:1"><?php echo $entry['ram_mb']; ?> MB</div>
                <div class="res-cols flex items-center text-xs font-mono text-gray-400" style="flex:1"><?php echo $entry['disk_gb']; ?> GB</div>
                <!-- Actions -->
                <?php if (!empty($id)): ?>
                <div class="flex items-center gap-2 flex-wrap shrink-0">
                    <a href="?action=start&uuid=<?php echo urlencode($id); ?>" class="btn-sm btn-green" title="Démarrer"><i class="fas fa-play text-[10px]"></i></a>
                    <a href="?action=restart&uuid=<?php echo urlencode($id); ?>" class="btn-sm btn-amber" title="Redémarrer"><i class="fas fa-rotate text-[10px]"></i></a>
                    <a href="?action=stop&uuid=<?php echo urlencode($id); ?>" class="btn-sm btn-red" title="Arrêter"><i class="fas fa-stop text-[10px]"></i></a>
                    <a href="/client/servers/gérer/?uuid=<?php echo urlencode($id); ?>" class="btn-sm btn-primary"><i class="fas fa-terminal text-[10px]"></i> Console</a>
                    <a href="<?php echo htmlspecialchars($panel_url); ?>/server/<?php echo htmlspecialchars($short_id); ?>" target="_blank" class="btn-sm" style="background:rgba(255,255,255,.04);color:#9ca3af;border-color:rgba(255,255,255,.08);" title="Panel Pterodactyl"><i class="fas fa-external-link-alt text-[10px]"></i></a>
                    <a href="?action=delete&uuid=<?php echo urlencode($id); ?>" onclick="return confirmDelete('<?php echo addslashes(htmlspecialchars($server['service_name'] ?? '')); ?>')" class="btn-sm" style="background:rgba(239,68,68,.06);color:#6b7280;border-color:rgba(239,68,68,.1);" onmouseover="this.style.color='#ef4444'" onmouseout="this.style.color='#6b7280'" title="Supprimer"><i class="fas fa-trash text-[10px]"></i></a>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div><!-- /content -->
</div><!-- /main-content -->

</body>
</html>

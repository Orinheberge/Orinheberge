<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/lang.php';

if (!isset($_SESSION['user_id'])) { header('Location: /login/'); exit(); }
$is_logged_in = true;

try {
    $pdo = new PDO('mysql:host=localhost;dbname=s43_orinheberge;charset=utf8mb4', 'root', '1504', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) { die(t('login.db_error')); }

// Charger la config depuis la BDD
$cfg = [];
foreach ($pdo->query('SELECT `key`, `value` FROM settings') as $row) $cfg[$row['key']] = $row['value'];
$panel_url      = $cfg['panel_url']      ?? 'https://panel.orinstone.deepstone.fr';
$api_key_client = $cfg['api_key_client'] ?? '';
$api_key_admin  = $cfg['api_key_admin']  ?? '';
$phpmyadmin_url = $cfg['phpmyadmin_url'] ?? 'https://php.orinstone.deepstone.fr';
$headers_client = ["Authorization: Bearer $api_key_client","Accept: application/vnd.pterodactyl.v1+json","Content-Type: application/json"];
$headers_admin  = ["Authorization: Bearer $api_key_admin","Accept: application/vnd.pterodactyl.v1+json","Content-Type: application/json"];

// Rafraîchir session user
$stmt = $pdo->prepare('SELECT pseudo, firstname, avatar FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$_SESSION['user_id']]);
$user_data = $stmt->fetch();
if ($user_data) {
    $_SESSION['username'] = !empty($user_data['pseudo']) ? $user_data['pseudo'] : $user_data['firstname'];
    $_SESSION['avatar']   = $user_data['avatar'];
}

// Gestion action suppression
$api_message = '';
if (isset($_GET['action'], $_GET['uuid']) && $_GET['action'] === 'delete') {
    $target_uuid = $_GET['uuid'];
    $stmt = $pdo->prepare('SELECT uuid, server_id FROM orders WHERE user_id=? AND uuid=?');
    $stmt->execute([$_SESSION['user_id'], $target_uuid]);
    $sv = $stmt->fetch();
    if ($sv) {
        $ch = curl_init($panel_url . '/api/application/servers/' . $sv['server_id']);
        curl_setopt_array($ch, [CURLOPT_HTTPHEADER=>$headers_admin, CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>15, CURLOPT_SSL_VERIFYPEER=>false, CURLOPT_CUSTOMREQUEST=>'DELETE']);
        curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($http_code === 204 || $http_code === 200) {
            $pdo->prepare('DELETE FROM orders WHERE uuid=? AND user_id=?')->execute([$target_uuid, $_SESSION['user_id']]);
            $_SESSION['api_success'] = $lang === 'en' ? 'Server deleted successfully.' : 'Serveur supprimé avec succès.';
        } else {
            $_SESSION['api_error'] = $lang === 'en' ? 'Deletion failed. Try again.' : 'Échec de la suppression. Réessayez.';
        }
    }
    header('Location: /client/'); exit();
}
if (isset($_SESSION['api_error']))   { $api_message = "<div class='bg-red-500/20 text-red-400 border border-red-500/30 p-4 rounded-xl mb-6 text-sm'>".$_SESSION['api_error']."</div>"; unset($_SESSION['api_error']); }
elseif (isset($_SESSION['api_success'])) { $api_message = "<div class='bg-green-500/20 text-green-400 border border-green-500/30 p-4 rounded-xl mb-6 text-sm'>".$_SESSION['api_success']."</div>"; unset($_SESSION['api_success']); }

// Récupérer tous les services
$stmt = $pdo->prepare('SELECT * FROM orders WHERE user_id=? ORDER BY created_at DESC');
$stmt->execute([$_SESSION['user_id']]);
$services = $stmt->fetchAll();

// Compter les tickets ouverts
$stmt2 = $pdo->prepare("SELECT COUNT(*) FROM support_tickets WHERE user_id=? AND status != 'Fermé'");
$stmt2->execute([$_SESSION['user_id']]);
$open_tickets = $stmt2->fetchColumn();

// Vérifier si admin
$stmt3 = $pdo->prepare('SELECT is_admin FROM users WHERE id=? LIMIT 1');
$stmt3->execute([$_SESSION['user_id']]);
$is_admin = (bool)($stmt3->fetchColumn());
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OrinHeberge — Espace Client</title>
    <link rel="icon" type="image/png" href="/favicon.ico">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="manifest" href="/manifest.json">
    <style>
        :root{--sidebar:240px;}
        *{box-sizing:border-box;}
        body{background:#0d0f14;color:#e2e8f0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',system-ui,sans-serif;min-height:100vh;}
        /* Sidebar */
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
        /* Main */
        .main-content{margin-left:var(--sidebar);min-height:100vh;display:flex;flex-direction:column;}
        .topbar{background:#111318;border-bottom:1px solid rgba(255,255,255,.06);padding:.875rem 1.75rem;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:30;}
        .content{padding:1.75rem;flex:1;}
        /* Cards */
        .card{background:#161a22;border:1px solid rgba(255,255,255,.07);border-radius:.875rem;}
        .card-sm{background:#161a22;border:1px solid rgba(255,255,255,.07);border-radius:.75rem;}
        .stat-card{background:linear-gradient(135deg,#161a22,#1a1f2a);border:1px solid rgba(255,255,255,.07);border-radius:.875rem;padding:1.25rem;}
        /* Badges */
        .badge{display:inline-flex;align-items:center;gap:.35rem;padding:.2rem .65rem;border-radius:9999px;font-size:.72rem;font-weight:600;}
        .badge-green{background:rgba(34,197,94,.1);color:#22c55e;border:1px solid rgba(34,197,94,.2);}
        .badge-orange{background:rgba(249,115,22,.1);color:#f97316;border:1px solid rgba(249,115,22,.2);}
        .badge-red{background:rgba(239,68,68,.1);color:#ef4444;border:1px solid rgba(239,68,68,.2);}
        .badge-gray{background:rgba(107,114,128,.1);color:#9ca3af;border:1px solid rgba(107,114,128,.2);}
        .badge-blue{background:rgba(56,189,248,.1);color:#38bdf8;border:1px solid rgba(56,189,248,.2);}
        /* Service row */
        .service-row{display:flex;align-items:center;gap:1rem;padding:1rem 1.25rem;border-bottom:1px solid rgba(255,255,255,.04);transition:background .15s;}
        .service-row:last-child{border-bottom:none;}
        .service-row:hover{background:rgba(255,255,255,.02);}
        .service-icon{width:2.25rem;height:2.25rem;border-radius:.625rem;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:.9rem;}
        /* Admin bar */
        .admin-bar{background:linear-gradient(135deg,rgba(225,29,72,.08),rgba(249,115,22,.06));border:1px solid rgba(225,29,72,.2);border-radius:.875rem;padding:1rem 1.25rem;}
        /* Quick links */
        .qlink{display:flex;align-items:center;gap:.75rem;padding:.75rem 1rem;border-radius:.75rem;border:1px solid rgba(255,255,255,.06);background:#161a22;transition:all .15s;text-decoration:none;}
        .qlink:hover{border-color:rgba(56,189,248,.25);background:rgba(56,189,248,.04);}
        .qlink-icon{width:2rem;height:2rem;border-radius:.5rem;display:flex;align-items:center;justify-content:center;font-size:.8rem;flex-shrink:0;}
        /* Mobile */
        .mobile-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:39;}
        @media(max-width:768px){
            .sidebar{transform:translateX(-100%);transition:transform .25s;}
            .sidebar.open{transform:translateX(0);}
            .mobile-overlay.open{display:block;}
            .main-content{margin-left:0;}
            .topbar{padding:.75rem 1rem;}
            .content{padding:1rem;}
        }
    </style>
    <script>
        function toggleSidebar(){
            document.getElementById('sidebar').classList.toggle('open');
            document.getElementById('overlay').classList.toggle('open');
        }
        function confirmDelete(name){return confirm('Supprimer le serveur "'+name+'" ?\nCette action est irréversible.');}
    </script>
</head>
<body>

<!-- Mobile overlay -->
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
        <a href="/client/" class="nav-item active">
            <i class="fas fa-home icon"></i> Tableau de bord
        </a>
        <a href="/client/servers/" class="nav-item">
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
        <a href="/support/" class="nav-item">
            <i class="fas fa-headset icon"></i> Support
            <?php if ($open_tickets > 0): ?>
            <span class="ml-auto text-[10px] bg-purple-500/20 text-purple-400 border border-purple-500/20 px-1.5 py-0.5 rounded-full font-bold"><?php echo $open_tickets; ?></span>
            <?php endif; ?>
        </a>
        <a href="/status/" class="nav-item">
            <i class="fas fa-signal icon"></i> Statut
        </a>

        <?php if ($is_admin): ?>
        <div class="nav-separator"></div>
        <div class="nav-section">Administration</div>
        <a href="/admin/" class="nav-item" style="color:#f43f5e;border-color:rgba(244,63,94,.1);">
            <i class="fas fa-shield-alt icon"></i> Vue d'ensemble
        </a>
        <a href="/admin/nodes/" class="nav-item">
            <i class="fas fa-network-wired icon"></i> Nodes
        </a>
        <a href="/admin/eggs/" class="nav-item">
            <i class="fas fa-egg icon"></i> Eggs
        </a>
        <a href="/admin/products/" class="nav-item">
            <i class="fas fa-box icon"></i> Produits
        </a>
        <a href="/admin/extensions/" class="nav-item">
            <i class="fas fa-puzzle-piece icon"></i> Extensions
        </a>
        <a href="/admin/?view=clients" class="nav-item">
            <i class="fas fa-users icon"></i> Clients
        </a>
        <a href="/support/admin_tickets/" class="nav-item">
            <i class="fas fa-ticket-alt icon"></i> Tickets
        </a>
        <a href="/admin/?view=settings" class="nav-item">
            <i class="fas fa-sliders-h icon"></i> Paramètres
        </a>
        <?php endif; ?>

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
                <div class="text-sm font-bold text-white">Tableau de bord</div>
                <div class="text-xs text-gray-500">Bienvenue, <?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?></div>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <a href="/offres/" class="hidden sm:flex items-center gap-2 bg-sky-600 hover:bg-sky-500 text-white text-xs font-bold px-4 py-2 rounded-lg transition">
                <i class="fas fa-plus text-[10px]"></i> Nouveau service
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

        <?php
        $paid = array_filter($services, fn($s) => ($s['status'] ?? '') === 'paid');
        $free = array_filter($services, fn($s) => ($s['renewal_price'] ?? 0) == 0);
        $active_count = count($paid) + count($free);
        ?>

        <!-- Stats -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="stat-card">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-xs text-gray-500 font-medium">Services</span>
                    <div class="w-7 h-7 rounded-lg bg-sky-500/15 flex items-center justify-center"><i class="fas fa-server text-sky-400 text-xs"></i></div>
                </div>
                <div class="text-2xl font-black text-white"><?php echo count($services); ?></div>
                <div class="text-xs text-gray-500 mt-1">Total déployés</div>
            </div>
            <div class="stat-card">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-xs text-gray-500 font-medium">Actifs</span>
                    <div class="w-7 h-7 rounded-lg bg-green-500/15 flex items-center justify-center"><i class="fas fa-check text-green-400 text-xs"></i></div>
                </div>
                <div class="text-2xl font-black text-white"><?php echo $active_count; ?></div>
                <div class="text-xs text-gray-500 mt-1">En ligne</div>
            </div>
            <div class="stat-card">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-xs text-gray-500 font-medium">Tickets</span>
                    <div class="w-7 h-7 rounded-lg bg-purple-500/15 flex items-center justify-center"><i class="fas fa-headset text-purple-400 text-xs"></i></div>
                </div>
                <div class="text-2xl font-black text-white"><?php echo $open_tickets; ?></div>
                <div class="text-xs text-gray-500 mt-1">Ouverts</div>
            </div>
            <a href="/profil/" class="stat-card hover:border-sky-500/30 transition block">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-xs text-gray-500 font-medium">Compte</span>
                    <div class="w-7 h-7 rounded-lg bg-amber-500/15 flex items-center justify-center"><i class="fas fa-user text-amber-400 text-xs"></i></div>
                </div>
                <div class="text-sm font-bold text-white truncate"><?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?></div>
                <div class="text-xs text-sky-400 mt-1">Modifier le profil →</div>
            </a>
        </div>

        <?php if ($is_admin): ?>
        <!-- Admin bar -->
        <div class="admin-bar mb-6">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-lg bg-rose-500/20 flex items-center justify-center shrink-0">
                        <i class="fas fa-shield-alt text-rose-400 text-sm"></i>
                    </div>
                    <div>
                        <div class="text-sm font-bold text-white">Accès Administrateur</div>
                        <div class="text-xs text-gray-500">Panneau de gestion complet</div>
                    </div>
                </div>
                <a href="/admin/" class="flex items-center gap-2 bg-rose-600 hover:bg-rose-500 text-white text-xs font-bold px-4 py-2 rounded-lg transition">
                    <i class="fas fa-cogs text-[10px]"></i> Ouvrir l'Admin Panel
                </a>
            </div>
        </div>
        <?php endif; ?>

        <!-- Services list -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2">
                <div class="card">
                    <div class="flex items-center justify-between px-5 py-4 border-b border-white/[0.05]">
                        <h2 class="text-sm font-bold text-white flex items-center gap-2">
                            <i class="fas fa-server text-sky-400 text-xs"></i> Mes services
                        </h2>
                        <a href="/client/servers/" class="text-xs text-sky-400 hover:text-sky-300 font-semibold transition">Tout gérer →</a>
                    </div>
                    <?php if (empty($services)): ?>
                    <div class="px-5 py-12 text-center">
                        <div class="w-12 h-12 rounded-xl bg-sky-500/10 flex items-center justify-center mx-auto mb-3"><i class="fas fa-server text-sky-400"></i></div>
                        <div class="text-sm font-semibold text-gray-300 mb-1">Aucun service</div>
                        <div class="text-xs text-gray-500 mb-4">Déployez votre premier serveur gratuitement.</div>
                        <a href="/offres/" class="inline-flex items-center gap-2 bg-sky-600 hover:bg-sky-500 text-white text-xs font-bold px-4 py-2 rounded-lg transition"><i class="fas fa-plus text-[10px]"></i> Voir les offres</a>
                    </div>
                    <?php else: ?>
                    <?php
                    $icons_map = ['minecraft'=>['fas fa-cube','bg-green-500/15','text-green-400'],'php'=>['fas fa-code','bg-blue-500/15','text-blue-400'],'nodejs'=>['fab fa-node-js','bg-green-500/15','text-green-400'],'python'=>['fab fa-python','bg-yellow-500/15','text-yellow-400'],'java'=>['fab fa-java','bg-orange-500/15','text-orange-400'],'hytale'=>['fas fa-gamepad','bg-purple-500/15','text-purple-400']];
                    foreach (array_slice($services, 0, 6) as $svc):
                        $cat = strtolower($svc['service_name'] ?? 'server');
                        $icon_data = $icons_map[$cat] ?? ['fas fa-server','bg-sky-500/15','text-sky-400'];
                        $st = $svc['status'] ?? 'unknown';
                        $is_free_svc = ($svc['renewal_price'] ?? 0) == 0;
                        $badge = match($st) { 'paid'=>'badge-green', 'suspended'=>'badge-orange', 'expired'=>'badge-red', default=>($is_free_svc?'badge-blue':'badge-gray') };
                        $badge_label = match($st) { 'paid'=>'Actif', 'suspended'=>'Suspendu', 'expired'=>'Expiré', default=>($is_free_svc?'Gratuit':'En attente') };
                    ?>
                    <div class="service-row">
                        <div class="service-icon <?php echo $icon_data[1]; ?>">
                            <i class="<?php echo $icon_data[0].' '.$icon_data[2]; ?>"></i>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-semibold text-white truncate"><?php echo htmlspecialchars($svc['service_name'] ?? 'Serveur'); ?></div>
                            <div class="text-xs text-gray-500 font-mono"><?php echo htmlspecialchars(substr($svc['uuid'] ?? '', 0, 8)); ?>…</div>
                        </div>
                        <div class="flex items-center gap-3 shrink-0">
                            <span class="badge <?php echo $badge; ?>"><?php echo $badge_label; ?></span>
                            <a href="<?php echo htmlspecialchars($panel_url); ?>/server/<?php echo htmlspecialchars($svc['uuid'] ?? ''); ?>" target="_blank" class="text-gray-500 hover:text-sky-400 transition text-xs"><i class="fas fa-external-link-alt"></i></a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php if (count($services) > 6): ?>
                    <div class="px-5 py-3 text-center border-t border-white/[0.04]">
                        <a href="/client/servers/" class="text-xs text-gray-500 hover:text-sky-400 transition">Voir <?php echo count($services)-6; ?> service(s) supplémentaire(s) →</a>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick actions sidebar -->
            <div class="space-y-3">
                <h2 class="text-xs font-bold text-gray-500 uppercase tracking-wider px-1">Accès rapide</h2>
                <a href="/offres/" class="qlink">
                    <div class="qlink-icon bg-sky-500/15"><i class="fas fa-tags text-sky-400"></i></div>
                    <div><div class="text-xs font-semibold text-white">Voir les offres</div><div class="text-[10px] text-gray-500">Plans & tarifs</div></div>
                </a>
                <a href="/client/servers/" class="qlink">
                    <div class="qlink-icon bg-green-500/15"><i class="fas fa-server text-green-400"></i></div>
                    <div><div class="text-xs font-semibold text-white">Gérer les serveurs</div><div class="text-[10px] text-gray-500">Console & paramètres</div></div>
                </a>
                <a href="/support/" class="qlink">
                    <div class="qlink-icon bg-purple-500/15"><i class="fas fa-headset text-purple-400"></i></div>
                    <div><div class="text-xs font-semibold text-white">Ouvrir un ticket</div><div class="text-[10px] text-gray-500">Support technique</div></div>
                </a>
                <a href="<?php echo htmlspecialchars($panel_url); ?>" target="_blank" class="qlink">
                    <div class="qlink-icon bg-amber-500/15"><i class="fas fa-cogs text-amber-400"></i></div>
                    <div><div class="text-xs font-semibold text-white">Panel Pterodactyl</div><div class="text-[10px] text-gray-500">Accès direct panel</div></div>
                </a>
                <a href="<?php echo htmlspecialchars($phpmyadmin_url); ?>" target="_blank" class="qlink">
                    <div class="qlink-icon bg-sky-500/15"><i class="fas fa-database text-sky-400"></i></div>
                    <div><div class="text-xs font-semibold text-white">phpMyAdmin</div><div class="text-[10px] text-gray-500">Gestion base de données</div></div>
                </a>
                <a href="/profil/" class="qlink">
                    <div class="qlink-icon bg-gray-500/15"><i class="fas fa-user text-gray-400"></i></div>
                    <div><div class="text-xs font-semibold text-white">Mon profil</div><div class="text-[10px] text-gray-500">Informations & sécurité</div></div>
                </a>
                <a href="/logout/" class="qlink" style="border-color:rgba(239,68,68,.15);">
                    <div class="qlink-icon bg-red-500/15"><i class="fas fa-sign-out-alt text-red-400"></i></div>
                    <div><div class="text-xs font-semibold text-red-400">Déconnexion</div><div class="text-[10px] text-gray-500">Fermer la session</div></div>
                </a>
            </div>
        </div>

    </div><!-- /content -->
</div><!-- /main-content -->

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
    <title>OrinHeberge | Mon Espace Client</title>
    <link rel="icon" type="image/png" href="https://heberge.orinstone.deepstone.fr/favicon.ico">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="manifest" href="/manifest.json">
    <style>
        body { background: #0b0f19; scroll-behavior: smooth; }
        .glass { background: rgba(255,255,255,0.04); backdrop-filter: blur(14px); border: 1px solid rgba(255,255,255,0.08); }
        .gradient-text { background: linear-gradient(90deg, #38bdf8, #818cf8); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .card-hover { transition: all .3s ease; } .card-hover:hover { transform: translateY(-4px); border-color: rgba(56,189,248,.2); }
        #mobileMenu { display: none; } #mobileMenu.active { display: block; }
    </style>
    <script>
        function toggleMenu() { document.getElementById('mobileMenu').classList.toggle('active'); }
        function confirmDelete(name) { return confirm('⚠️ <?php echo $lang==="en" ? "Delete server" : "Supprimer le serveur"; ?> "' + name + '" ?\n<?php echo $lang==="en" ? "This action is irreversible." : "Cette action est irréversible."; ?>'); }
    </script>
</head>
<body class="text-gray-200 font-sans min-h-screen flex flex-col justify-between antialiased">

<?php $active_nav = 'servers'; include $_SERVER['DOCUMENT_ROOT'] . '/inc/navbar.php'; ?>

<main class="max-w-7xl mx-auto px-4 sm:px-6 mb-16 flex-grow pt-8 w-full">
    <?php echo $api_message; ?>

    <!-- Header dashboard -->
    <div class="flex flex-wrap items-center justify-between gap-4 mb-10">
        <div>
            <h1 class="text-4xl font-black tracking-tight gradient-text">
                <?php echo $lang === 'en' ? 'My Dashboard' : 'Mon Espace Client'; ?>
            </h1>
            <p class="text-gray-400 text-sm mt-1">
                <?php echo $lang === 'en' ? 'Manage all your services from one place.' : 'Gérez tous vos services depuis un seul endroit.'; ?>
            </p>
        </div>
        <a href="/offres/" class="bg-sky-600 hover:bg-sky-500 px-5 py-3 rounded-xl text-sm font-bold transition flex items-center gap-2 shadow-lg shadow-sky-900/20">
            <i class="fas fa-plus"></i> <?php echo $lang === 'en' ? 'Buy a service' : 'Acheter un service'; ?>
        </a>
    </div>

    <!-- Stats cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-10">
        <div class="glass p-5 rounded-2xl border border-white/[0.05] flex items-center gap-4">
            <div class="w-10 h-10 bg-sky-500/15 rounded-xl flex items-center justify-center shrink-0"><i class="fas fa-server text-sky-400"></i></div>
            <div><div class="text-2xl font-black text-white"><?php echo count($services); ?></div><div class="text-xs text-gray-400"><?php echo $lang==='en' ? 'Services' : 'Services'; ?></div></div>
        </div>
        <div class="glass p-5 rounded-2xl border border-white/[0.05] flex items-center gap-4">
            <div class="w-10 h-10 bg-green-500/15 rounded-xl flex items-center justify-center shrink-0"><i class="fas fa-check-circle text-green-400"></i></div>
            <div>
                <?php
                $paid = array_filter($services, fn($s) => ($s['status'] ?? '') === 'paid');
                $free = array_filter($services, fn($s) => ($s['renewal_price'] ?? 0) == 0);
                ?>
                <div class="text-2xl font-black text-white"><?php echo count($paid) + count($free); ?></div>
                <div class="text-xs text-gray-400"><?php echo $lang==='en' ? 'Active' : 'Actifs'; ?></div>
            </div>
        </div>
        <div class="glass p-5 rounded-2xl border border-white/[0.05] flex items-center gap-4">
            <div class="w-10 h-10 bg-purple-500/15 rounded-xl flex items-center justify-center shrink-0"><i class="fas fa-headset text-purple-400"></i></div>
            <div><div class="text-2xl font-black text-white"><?php echo $open_tickets; ?></div><div class="text-xs text-gray-400"><?php echo $lang==='en' ? 'Open tickets' : 'Tickets ouverts'; ?></div></div>
        </div>
        <a href="/profil/" class="glass p-5 rounded-2xl border border-white/[0.05] flex items-center gap-4 hover:border-sky-500/30 transition card-hover">
            <div class="w-10 h-10 bg-amber-500/15 rounded-xl flex items-center justify-center shrink-0"><i class="fas fa-user text-amber-400"></i></div>
            <div><div class="text-sm font-bold text-white"><?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?></div><div class="text-xs text-gray-400"><?php echo $lang==='en' ? 'My profile' : 'Mon profil'; ?></div></div>
        </a>
    </div>

    <!-- Bloc Admin (visible uniquement pour les admins) -->
    <?php if ($is_admin): ?>
    <div class="mb-8 rounded-2xl border border-rose-500/30 bg-rose-500/5 p-1">
        <div class="flex flex-wrap items-center justify-between gap-4 px-5 py-4 border-b border-rose-500/10">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 bg-rose-500/20 rounded-xl flex items-center justify-center shrink-0">
                    <i class="fas fa-shield-alt text-rose-400 text-sm"></i>
                </div>
                <div>
                    <div class="text-sm font-black text-rose-300">Espace Administrateur</div>
                    <div class="text-xs text-rose-400/60">Accès rapide aux outils de gestion</div>
                </div>
            </div>
            <a href="/admin/" class="bg-rose-600 hover:bg-rose-500 text-white px-4 py-2 rounded-xl text-xs font-bold transition flex items-center gap-2 shadow-lg shadow-rose-900/30">
                <i class="fas fa-cogs"></i> Ouvrir l'Admin Panel
            </a>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 p-3">
            <a href="/admin/?view=clients" class="flex items-center gap-3 p-3 rounded-xl bg-white/[0.02] hover:bg-rose-500/10 border border-white/[0.04] hover:border-rose-500/20 transition group">
                <i class="fas fa-users text-rose-400 text-base w-5 text-center group-hover:scale-110 transition"></i>
                <div><div class="text-xs font-bold text-gray-200">Clients</div><div class="text-[10px] text-gray-500">Gérer les comptes</div></div>
            </a>
            <a href="/admin/?view=servers" class="flex items-center gap-3 p-3 rounded-xl bg-white/[0.02] hover:bg-rose-500/10 border border-white/[0.04] hover:border-rose-500/20 transition group">
                <i class="fas fa-server text-rose-400 text-base w-5 text-center group-hover:scale-110 transition"></i>
                <div><div class="text-xs font-bold text-gray-200">Serveurs</div><div class="text-[10px] text-gray-500">Suspendre / Suppr.</div></div>
            </a>
            <a href="/support/admin_tickets/" class="flex items-center gap-3 p-3 rounded-xl bg-white/[0.02] hover:bg-rose-500/10 border border-white/[0.04] hover:border-rose-500/20 transition group">
                <i class="fas fa-headset text-rose-400 text-base w-5 text-center group-hover:scale-110 transition"></i>
                <div><div class="text-xs font-bold text-gray-200">Tickets</div><div class="text-[10px] text-gray-500">Support clients</div></div>
            </a>
            <a href="/admin/?view=settings" class="flex items-center gap-3 p-3 rounded-xl bg-white/[0.02] hover:bg-rose-500/10 border border-white/[0.04] hover:border-rose-500/20 transition group">
                <i class="fas fa-sliders-h text-rose-400 text-base w-5 text-center group-hover:scale-110 transition"></i>
                <div><div class="text-xs font-bold text-gray-200">Paramètres</div><div class="text-[10px] text-gray-500">API, Panel, SMTP</div></div>
            </a>
        </div>
    </div>
    <?php endif; ?>

    <!-- Quick actions -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-10">
        <a href="/offres/" class="glass p-4 rounded-2xl border border-white/[0.05] hover:border-sky-500/30 transition card-hover flex flex-col items-center gap-2 text-center">
            <i class="fas fa-tags text-sky-400 text-xl"></i>
            <span class="text-xs font-semibold text-gray-300"><?php echo $lang==='en' ? 'Browse plans' : 'Voir les offres'; ?></span>
        </a>
        <a href="/client/servers/" class="glass p-4 rounded-2xl border border-white/[0.05] hover:border-green-500/30 transition card-hover flex flex-col items-center gap-2 text-center">
            <i class="fas fa-server text-green-400 text-xl"></i>
            <span class="text-xs font-semibold text-gray-300"><?php echo $lang==='en' ? 'Manage servers' : 'Gérer serveurs'; ?></span>
        </a>
        <a href="/support/" class="glass p-4 rounded-2xl border border-white/[0.05] hover:border-purple-500/30 transition card-hover flex flex-col items-center gap-2 text-center">
            <i class="fas fa-headset text-purple-400 text-xl"></i>
            <span class="text-xs font-semibold text-gray-300"><?php echo $lang==='en' ? 'Open a ticket' : 'Ouvrir un ticket'; ?></span>
        </a>
        <a href="<?php echo htmlspecialchars($panel_url); ?>" target="_blank" class="glass p-4 rounded-2xl border border-white/[0.05] hover:border-amber-500/30 transition card-hover flex flex-col items-center gap-2 text-center">
            <i class="fas fa-cogs text-amber-400 text-xl"></i>
            <span class="text-xs font-semibold text-gray-300">Panel Pterodactyl</span>
        </a>
        <a href="<?php echo htmlspecialchars($phpmyadmin_url); ?>" target="_blank" class="glass p-4 rounded-2xl border border-white/[0.05] hover:border-sky-500/30 transition card-hover flex flex-col items-center gap-2 text-center">
            <i class="fas fa-database text-sky-400 text-xl"></i>
            <span class="text-xs font-semibold text-gray-300">phpMyAdmin</span>
        </a>
        <a href="/logout/" class="glass p-4 rounded-2xl border border-white/[0.05] hover:border-red-500/30 transition card-hover flex flex-col items-center gap-2 text-center">
            <i class="fas fa-sign-out-alt text-red-400 text-xl"></i>
            <span class="text-xs font-semibold text-gray-300"><?php echo $lang==='en' ? 'Logout' : 'Déconnexion'; ?></span>
        </a>
    </div>

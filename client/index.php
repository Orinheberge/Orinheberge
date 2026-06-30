<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/lang.php';

if (!isset($_SESSION['user_id'])) { header('Location: /login/'); exit(); }
$is_logged_in = true;

$panel_url      = 'https://panel.orinstone.deepstone.fr';
$api_key_client = 'ptlc_MfJSOUID0bnTgFCmm5VvYMML2jKUUA5RFZ2n2MeZCSU';
$api_key_admin  = 'ptla_YKix8PexQDCZ7nIeexST3NXC2sFwQAoefDtOQBvJkbx';
$headers_client = ["Authorization: Bearer $api_key_client","Accept: application/vnd.pterodactyl.v1+json","Content-Type: application/json"];
$headers_admin  = ["Authorization: Bearer $api_key_admin","Accept: application/vnd.pterodactyl.v1+json","Content-Type: application/json"];

try {
    $pdo = new PDO('mysql:host=localhost;dbname=s43_orinheberge;charset=utf8mb4', 'root', '1504', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) { die(t('login.db_error')); }

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
        <a href="<?php echo $panel_url; ?>" target="_blank" class="glass p-4 rounded-2xl border border-white/[0.05] hover:border-amber-500/30 transition card-hover flex flex-col items-center gap-2 text-center">
            <i class="fas fa-cogs text-amber-400 text-xl"></i>
            <span class="text-xs font-semibold text-gray-300">Panel Pterodactyl</span>
        </a>
        <a href="https://php.orinstone.deepstone.fr" target="_blank" class="glass p-4 rounded-2xl border border-white/[0.05] hover:border-sky-500/30 transition card-hover flex flex-col items-center gap-2 text-center">
            <i class="fas fa-database text-sky-400 text-xl"></i>
            <span class="text-xs font-semibold text-gray-300">phpMyAdmin</span>
        </a>
        <a href="/logout/" class="glass p-4 rounded-2xl border border-white/[0.05] hover:border-red-500/30 transition card-hover flex flex-col items-center gap-2 text-center">
            <i class="fas fa-sign-out-alt text-red-400 text-xl"></i>
            <span class="text-xs font-semibold text-gray-300"><?php echo $lang==='en' ? 'Logout' : 'Déconnexion'; ?></span>
        </a>
    </div>

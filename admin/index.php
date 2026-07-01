<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/lang.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/smtp.php';

// ─── Sécurité admin ────────────────────────────────────────────────────────
if (!isset($_SESSION['user_id'])) { header('Location: /login/'); exit(); }

try {
    $pdo = new PDO('mysql:host=localhost;dbname=s43_orinheberge;charset=utf8mb4', 'root', '1504', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) { die('Erreur BDD.'); }

// Vérifie is_admin
$stmt = $pdo->prepare('SELECT id, pseudo, firstname, lastname, email, avatar, is_admin FROM users WHERE id=? LIMIT 1');
$stmt->execute([$_SESSION['user_id']]);
$admin = $stmt->fetch();
if (!$admin || !$admin['is_admin']) {
    http_response_code(403);
    die('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>403</title><script src="https://cdn.tailwindcss.com"></script></head><body class="bg-[#0b0f19] text-white flex items-center justify-center h-screen"><div class="text-center"><div class="text-7xl font-black text-red-500 mb-4">403</div><p class="text-gray-400 text-lg mb-6">Accès refusé — vous n\'êtes pas administrateur.</p><a href="/" class="bg-sky-600 hover:bg-sky-500 px-6 py-3 rounded-xl font-bold text-sm">Retour à l\'accueil</a></div></body></html>');
}

$_SESSION['username'] = !empty($admin['pseudo']) ? $admin['pseudo'] : $admin['firstname'];
$_SESSION['avatar']   = $admin['avatar'];

// ─── Charger config depuis BDD ───────────────────────────────────────────────
$cfg = [];
foreach ($pdo->query('SELECT `key`, `value` FROM settings') as $row) $cfg[$row['key']] = $row['value'];
$panel_url     = $cfg['panel_url']     ?? 'https://panel.orinstone.deepstone.fr';
$api_key_admin = $cfg['api_key_admin'] ?? '';
$headers_admin = ["Authorization: Bearer $api_key_admin","Accept: application/vnd.pterodactyl.v1+json","Content-Type: application/json"];

function adminApiCall($url, $headers, $endpoint, $method = 'GET', $data = null) {
    $ch = curl_init($url . '/api/application/' . $endpoint);
    curl_setopt_array($ch, [CURLOPT_HTTPHEADER => $headers, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_SSL_VERIFYPEER => false]);
    if ($method === 'DELETE') curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    if ($data) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data)); }
    $res = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    if ($code === 204) return true;
    return $res ? json_decode($res, true) : null;
}

$flash = '';

// ─── Actions POST ───────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Sauvegarder les paramètres
    if ($action === 'save_settings') {
        $keys = ['panel_url','api_key_admin','api_key_client','phpmyadmin_url','site_name','smtp_host','smtp_port','smtp_user','smtp_pass','smtp_from','smtp_from_name'];
        $stmt = $pdo->prepare('INSERT INTO settings (`key`,`value`) VALUES (?,?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)');
        foreach ($keys as $k) {
            if (isset($_POST[$k])) $stmt->execute([$k, trim($_POST[$k])]);
        }
        // Recharger la config
        foreach ($pdo->query('SELECT `key`, `value` FROM settings') as $row) $cfg[$row['key']] = $row['value'];
        $panel_url     = $cfg['panel_url']     ?? $panel_url;
        $api_key_admin = $cfg['api_key_admin'] ?? $api_key_admin;
        $headers_admin = ["Authorization: Bearer $api_key_admin","Accept: application/vnd.pterodactyl.v1+json","Content-Type: application/json"];
        $flash = "<div class='bg-green-500/20 text-green-400 border border-green-500/30 p-4 rounded-xl text-sm'>✅ Paramètres sauvegardés.</div>";
        header('Location: /admin/?view=settings'); exit();
    }

    // Envoyer un email à un client
    if ($action === 'send_email') {
        $to      = trim($_POST['email_to'] ?? '');
        $subject = trim($_POST['email_subject'] ?? '');
        $body    = nl2br(htmlspecialchars(trim($_POST['email_body'] ?? '')));
        if ($to && $subject && $body) {
            $html = '<div style="font-family:sans-serif;max-width:600px;margin:0 auto;background:#0b0f19;color:#e5e7eb;padding:32px;border-radius:12px;">'
                  . '<h2 style="color:#38bdf8;margin-top:0;">Message de l\'équipe OrinHeberge</h2>'
                  . '<div style="line-height:1.7;">' . $body . '</div>'
                  . '<hr style="border-color:#ffffff20;margin:24px 0;">'
                  . '<p style="color:#6b7280;font-size:12px;">OrinHeberge — Infrastructure OrinStone</p>'
                  . '</div>';
            $ok = send_smtp_mail($to, $subject, $html, 'OrinHeberge', 'no-reply@deepstone.fr');
            $flash = $ok
                ? "<div class='bg-green-500/20 text-green-400 border border-green-500/30 p-4 rounded-xl text-sm'>✅ Email envoyé à <strong>" . htmlspecialchars($to) . "</strong>.</div>"
                : "<div class='bg-red-500/20 text-red-400 border border-red-500/30 p-4 rounded-xl text-sm'>❌ Échec de l'envoi SMTP.</div>";
        } else {
            $flash = "<div class='bg-yellow-500/20 text-yellow-400 border border-yellow-500/30 p-4 rounded-xl text-sm'>⚠️ Remplissez tous les champs.</div>";
        }
    }

    // Supprimer un utilisateur et ses serveurs
    if ($action === 'delete_user') {
        $uid = (int)($_POST['user_id'] ?? 0);
        if ($uid && $uid !== (int)$_SESSION['user_id']) {
            // Supprimer ses serveurs du panel
            $servers = $pdo->prepare('SELECT server_id FROM orders WHERE user_id=?');
            $servers->execute([$uid]);
            foreach ($servers->fetchAll() as $sv) {
                adminApiCall($panel_url, $headers_admin, 'servers/' . $sv['server_id'], 'DELETE');
            }
            $pdo->prepare('DELETE FROM orders WHERE user_id=?')->execute([$uid]);
            $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$uid]);
            $flash = "<div class='bg-green-500/20 text-green-400 border border-green-500/30 p-4 rounded-xl text-sm'>✅ Utilisateur #$uid supprimé avec ses serveurs.</div>";
        }
    }

    // Supprimer un serveur spécifique
    if ($action === 'delete_server') {
        $uuid      = trim($_POST['server_uuid'] ?? '');
        $server_id = (int)($_POST['server_id'] ?? 0);
        if ($uuid && $server_id) {
            adminApiCall($panel_url, $headers_admin, 'servers/' . $server_id, 'DELETE');
            $pdo->prepare('DELETE FROM orders WHERE uuid=?')->execute([$uuid]);
            $flash = "<div class='bg-green-500/20 text-green-400 border border-green-500/30 p-4 rounded-xl text-sm'>✅ Serveur supprimé.</div>";
        }
    }

    // Renouveler un serveur (repousser l'expiration de 30 jours)
    if ($action === 'renew_server') {
        $uuid = trim($_POST['server_uuid'] ?? '');
        if ($uuid) {
            $sv = $pdo->prepare('SELECT expires_at FROM orders WHERE uuid=?');
            $sv->execute([$uuid]);
            $row = $sv->fetch();
            $current = $row && $row['expires_at'] ? strtotime($row['expires_at']) : time();
            $base = max($current, time());
            $new_expiry = date('Y-m-d H:i:s', strtotime('+30 days', $base));
            $pdo->prepare('UPDATE orders SET expires_at=?, status=? WHERE uuid=?')->execute([$new_expiry, 'paid', $uuid]);
            $flash = "<div class='bg-green-500/20 text-green-400 border border-green-500/30 p-4 rounded-xl text-sm'>✅ Serveur renouvelé jusqu'au <strong>" . htmlspecialchars($new_expiry) . "</strong>.</div>";
        }
    }

    // Suspendre / Unsuspend un serveur sur le panel
    if ($action === 'suspend_server' || $action === 'unsuspend_server') {
        $server_id = (int)($_POST['server_id'] ?? 0);
        if ($server_id) {
            $ep = $action === 'suspend_server' ? "servers/$server_id/suspend" : "servers/$server_id/unsuspend";
            $ch = curl_init($panel_url . '/api/application/' . $ep);
            curl_setopt_array($ch, [CURLOPT_HTTPHEADER => $headers_admin, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_POST => true, CURLOPT_POSTFIELDS => '{}']);
            curl_exec($ch); curl_close($ch);
            $flash = "<div class='bg-green-500/20 text-green-400 border border-green-500/30 p-4 rounded-xl text-sm'>✅ Action '" . htmlspecialchars($action) . "' effectuée sur serveur #$server_id.</div>";
        }
    }
}

// ─── Récupérer données ───────────────────────────────────────────────────────
$view = $_GET['view'] ?? 'dashboard';

// Action toggle admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_admin') {
    $uid = (int)($_POST['user_id'] ?? 0);
    if ($uid && $uid !== (int)$_SESSION['user_id']) {
        $cur = $pdo->prepare('SELECT is_admin FROM users WHERE id=?');
        $cur->execute([$uid]);
        $was_admin = (int)$cur->fetchColumn();
        $pdo->prepare('UPDATE users SET is_admin=? WHERE id=?')->execute([$was_admin ? 0 : 1, $uid]);
        $flash = "<div class='bg-green-500/20 text-green-400 border border-green-500/30 p-4 rounded-xl text-sm'>✅ Rôle mis à jour.</div>";
        header('Location: /admin/?view=clients'); exit();
    }
}

$all_users = $pdo->query('SELECT u.id, u.pseudo, u.firstname, u.lastname, u.email, u.is_admin, u.avatar, u.created_at,
    (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id) AS server_count
    FROM users u ORDER BY u.id DESC')->fetchAll();

$all_servers = $pdo->query('SELECT o.*, u.email AS user_email, u.pseudo, u.firstname FROM orders o
    LEFT JOIN users u ON u.id = o.user_id ORDER BY o.created_at DESC')->fetchAll();

$total_revenue   = (float)$pdo->query('SELECT COALESCE(SUM(renewal_price),0) FROM orders WHERE status="paid"')->fetchColumn();
$invoice_revenue = (float)$pdo->query('SELECT COALESCE(SUM(amount),0) FROM invoices WHERE status="paid"')->fetchColumn();
$active_servers  = $pdo->query('SELECT COUNT(*) FROM orders WHERE status="paid" OR renewal_price=0')->fetchColumn();
$open_tickets    = $pdo->query("SELECT COUNT(*) FROM support_tickets WHERE status != 'Fermé'")->fetchColumn();
$total_invoices  = $pdo->query("SELECT COUNT(*) FROM invoices")->fetchColumn();

$is_logged_in = true;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OrinHeberge — Admin Panel</title>
    <link rel="icon" type="image/png" href="/favicon.ico">
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
        .nav-item.active{background:rgba(244,63,94,.08);color:#f43f5e;border-color:rgba(244,63,94,.15);}
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
        .badge-rose{background:rgba(244,63,94,.1);color:#f43f5e;border:1px solid rgba(244,63,94,.2);}
        table{width:100%;border-collapse:collapse;}
        th{text-align:left;padding:.625rem 1rem;font-size:.7rem;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:#4b5563;border-bottom:1px solid rgba(255,255,255,.05);}
        td{padding:.875rem 1rem;font-size:.83rem;border-bottom:1px solid rgba(255,255,255,.04);}
        tr:last-child td{border-bottom:none;}
        tr:hover td{background:rgba(255,255,255,.015);}
        input,textarea,select{background:#1e2330 !important;border:1px solid rgba(255,255,255,.08) !important;color:#e2e8f0 !important;border-radius:.625rem;padding:.6rem .875rem;font-size:.83rem;width:100%;outline:none;transition:border-color .15s;}
        input:focus,textarea:focus,select:focus{border-color:rgba(244,63,94,.4) !important;}
        .btn-action{display:inline-flex;align-items:center;gap:.35rem;padding:.3rem .75rem;border-radius:.5rem;font-size:.75rem;font-weight:600;transition:all .15s;border:1px solid transparent;cursor:pointer;}
        .btn-red{background:rgba(239,68,68,.1);color:#ef4444;border-color:rgba(239,68,68,.2);}
        .btn-red:hover{background:rgba(239,68,68,.2);}
        .btn-orange{background:rgba(249,115,22,.1);color:#f97316;border-color:rgba(249,115,22,.2);}
        .btn-orange:hover{background:rgba(249,115,22,.2);}
        .btn-blue{background:rgba(56,189,248,.1);color:#38bdf8;border-color:rgba(56,189,248,.2);}
        .btn-blue:hover{background:rgba(56,189,248,.2);}
        .btn-sky{background:rgba(14,165,233,.1);color:#0ea5e9;border-color:rgba(14,165,233,.2);}
        .btn-sky:hover{background:rgba(14,165,233,.2);}
        .mobile-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:39;}
        @media(max-width:768px){
            .sidebar{transform:translateX(-100%);transition:transform .25s;}
            .sidebar.open{transform:translateX(0);}
            .mobile-overlay.open{display:block;}
            .main-content{margin-left:0;}
            .topbar,.content{padding:.875rem 1rem;}
        }
    </style>
    <script>
        function toggleSidebar(){document.getElementById('sidebar').classList.toggle('open');document.getElementById('overlay').classList.toggle('open');}
        function confirmDel(msg){return confirm('⚠️ '+msg+'\nCette action est irréversible.');}
        function openEmail(email){document.getElementById('modal-email').classList.remove('hidden');document.getElementById('email-to').value=email;}
        function closeEmail(){document.getElementById('modal-email').classList.add('hidden');}
    </script>
</head>
<body>

<div id="overlay" class="mobile-overlay" onclick="toggleSidebar()"></div>

<!-- Modal Email -->
<div id="modal-email" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4" style="backdrop-filter:blur(8px)">
    <div class="card w-full max-w-lg p-7">
        <div class="flex justify-between items-center mb-5">
            <h2 class="text-base font-bold text-white flex items-center gap-2"><i class="fas fa-envelope text-sky-400 text-sm"></i> Envoyer un email</h2>
            <button onclick="closeEmail()" class="text-gray-500 hover:text-white text-xl leading-none">&times;</button>
        </div>
        <form method="POST" action="/admin/" class="space-y-4">
            <input type="hidden" name="action" value="send_email">
            <div><label class="block text-xs text-gray-500 mb-1 font-semibold uppercase tracking-wide">Destinataire</label><input type="email" name="email_to" id="email-to" required></div>
            <div><label class="block text-xs text-gray-500 mb-1 font-semibold uppercase tracking-wide">Sujet</label><input type="text" name="email_subject" required placeholder="Objet..."></div>
            <div><label class="block text-xs text-gray-500 mb-1 font-semibold uppercase tracking-wide">Message</label><textarea name="email_body" rows="4" required placeholder="Votre message..." style="resize:none"></textarea></div>
            <div class="flex gap-3 pt-1">
                <button type="submit" class="flex-1 bg-sky-600 hover:bg-sky-500 text-white font-bold py-2.5 rounded-lg text-sm transition"><i class="fas fa-paper-plane mr-1.5"></i>Envoyer</button>
                <button type="button" onclick="closeEmail()" class="flex-1 font-bold py-2.5 rounded-lg text-sm transition text-gray-400 hover:text-white" style="background:rgba(255,255,255,.05)">Annuler</button>
            </div>
        </form>
    </div>
</div>

<?php
// Passer le bon $active_nav selon le $view courant
$active_nav = match($view) {
    'clients'  => 'clients',
    'servers'  => 'servers',
    'invoices' => 'invoices',
    'email'    => 'email',
    'settings' => 'settings',
    default    => 'dashboard',
};
include $_SERVER['DOCUMENT_ROOT'] . '/inc/admin_sidebar.php';
?>

<!-- ══ MAIN ══ -->
<div class="main-content">

    <!-- Topbar -->
    <div class="topbar">
        <div class="flex items-center gap-3">
            <button onclick="toggleSidebar()" class="md:hidden text-gray-400 hover:text-white text-lg w-8"><i class="fas fa-bars"></i></button>
            <div>
                <div class="text-sm font-bold text-white">
                    <?php $titles = ['clients'=>'Clients','servers'=>'Serveurs','invoices'=>'Factures','email'=>'Emails','settings'=>'Paramètres','dashboard'=>'Vue d\'ensemble']; echo $titles[$view] ?? 'Vue d\'ensemble'; ?>
                </div>
                <div class="text-xs text-gray-500">Administration OrinHeberge</div>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <a href="/client/" class="hidden sm:flex items-center gap-2 text-gray-400 hover:text-white text-xs font-semibold transition px-3 py-1.5 rounded-lg hover:bg-white/5">
                <i class="fas fa-arrow-left text-[10px]"></i> Espace client
            </a>
            <div class="w-8 h-8 rounded-full bg-rose-500/20 flex items-center justify-center text-rose-400 text-xs font-bold border border-rose-500/20 shrink-0">
                <?php echo strtoupper(substr($admin['pseudo'] ?: $admin['firstname'], 0, 1)); ?>
            </div>
        </div>
    </div>

    <div class="content">
        <?php if ($flash): echo "<div class='mb-5 p-4 rounded-xl text-sm font-medium' style='background:rgba(34,197,94,.08);border:1px solid rgba(34,197,94,.2);color:#22c55e;'>$flash</div>"; endif; ?>

        <!-- Stats toujours visibles -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <a href="/admin/?view=clients" class="stat-card hover:border-sky-500/30 transition block">
                <div class="flex items-center justify-between mb-3"><span class="text-xs text-gray-500 font-medium">Clients</span><div class="w-7 h-7 rounded-lg bg-sky-500/15 flex items-center justify-center"><i class="fas fa-users text-sky-400 text-xs"></i></div></div>
                <div class="text-2xl font-black text-white"><?php echo count($all_users); ?></div>
                <div class="text-xs text-sky-400 mt-1">Gérer →</div>
            </a>
            <a href="/admin/?view=servers" class="stat-card hover:border-green-500/30 transition block">
                <div class="flex items-center justify-between mb-3"><span class="text-xs text-gray-500 font-medium">Serveurs</span><div class="w-7 h-7 rounded-lg bg-green-500/15 flex items-center justify-center"><i class="fas fa-server text-green-400 text-xs"></i></div></div>
                <div class="text-2xl font-black text-white"><?php echo count($all_servers); ?></div>
                <div class="text-xs text-green-400 mt-1">Voir tous →</div>
            </a>
            <a href="/admin/?view=invoices" class="stat-card hover:border-yellow-500/30 transition block">
                <div class="flex items-center justify-between mb-3"><span class="text-xs text-gray-500 font-medium">Revenus</span><div class="w-7 h-7 rounded-lg bg-yellow-500/15 flex items-center justify-center"><i class="fas fa-euro-sign text-yellow-400 text-xs"></i></div></div>
                <div class="text-2xl font-black text-white"><?php echo number_format($invoice_revenue,2,',',''); ?>€</div>
                <div class="text-xs text-yellow-400 mt-1"><?php echo $total_invoices; ?> facture(s) →</div>
            </a>
            <a href="/support/admin_tickets/" class="stat-card hover:border-rose-500/30 transition block">
                <div class="flex items-center justify-between mb-3"><span class="text-xs text-gray-500 font-medium">Tickets</span><div class="w-7 h-7 rounded-lg bg-rose-500/15 flex items-center justify-center"><i class="fas fa-headset text-rose-400 text-xs"></i></div></div>
                <div class="text-2xl font-black text-white"><?php echo $open_tickets; ?></div>
                <div class="text-xs text-rose-400 mt-1">Voir les tickets →</div>
            </a>
        </div>

    <!-- ═══════════════════════════════════════════════════
         VUE DASHBOARD
    ════════════════════════════════════════════════════ -->
    <?php if ($view === 'dashboard'): ?>

        <!-- Activité récente : derniers clients + derniers serveurs -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">

            <!-- Derniers clients -->
            <div class="card overflow-hidden">
                <div class="px-5 py-4 border-b border-white/[0.05] flex items-center justify-between">
                    <h2 class="text-sm font-bold text-white flex items-center gap-2"><i class="fas fa-users text-sky-400 text-xs"></i> Derniers clients</h2>
                    <a href="/admin/?view=clients" class="text-xs text-sky-400 hover:text-sky-300 font-semibold">Tous →</a>
                </div>
                <?php foreach (array_slice($all_users, 0, 6) as $u): ?>
                <div class="flex items-center gap-3 px-5 py-3 border-b border-white/[0.03] last:border-0 hover:bg-white/[0.02] transition">
                    <?php if (!empty($u['avatar']) && file_exists($_SERVER['DOCUMENT_ROOT'].'/'.$u['avatar'])): ?>
                        <img src="/<?= htmlspecialchars($u['avatar']) ?>" class="w-7 h-7 rounded-full object-cover border border-white/10 shrink-0">
                    <?php else: ?>
                        <div class="w-7 h-7 rounded-full bg-sky-500/15 flex items-center justify-center shrink-0 text-sky-400 text-xs font-bold"><?= strtoupper(substr($u['pseudo'] ?: $u['firstname'], 0, 1)) ?></div>
                    <?php endif; ?>
                    <div class="flex-1 min-w-0">
                        <div class="text-xs font-semibold text-white truncate"><?= htmlspecialchars($u['pseudo'] ?: $u['firstname'].' '.$u['lastname']) ?></div>
                        <div class="text-[10px] text-gray-500 truncate"><?= htmlspecialchars($u['email']) ?></div>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <span class="badge <?= $u['is_admin'] ? 'badge-rose' : 'badge-gray' ?>"><?= $u['is_admin'] ? 'Admin' : 'Client' ?></span>
                        <span class="text-[10px] text-gray-500"><?= $u['server_count'] ?> srv</span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Derniers serveurs -->
            <div class="card overflow-hidden">
                <div class="px-5 py-4 border-b border-white/[0.05] flex items-center justify-between">
                    <h2 class="text-sm font-bold text-white flex items-center gap-2"><i class="fas fa-server text-green-400 text-xs"></i> Derniers serveurs</h2>
                    <a href="/admin/?view=servers" class="text-xs text-green-400 hover:text-green-300 font-semibold">Tous →</a>
                </div>
                <?php foreach (array_slice($all_servers, 0, 6) as $sv):
                    $st = $sv['status'] ?? 'unknown';
                    $st_badge = match($st) { 'paid'=>'badge-green','suspended'=>'badge-orange','expired'=>'badge-red',default=>'badge-gray' };
                    $st_label = match($st) { 'paid'=>'Actif','suspended'=>'Suspendu','expired'=>'Expiré',default=>'Autre' };
                ?>
                <div class="flex items-center gap-3 px-5 py-3 border-b border-white/[0.03] last:border-0 hover:bg-white/[0.02] transition">
                    <div class="w-7 h-7 rounded-lg bg-sky-500/10 flex items-center justify-center shrink-0"><i class="fas fa-server text-sky-400 text-xs"></i></div>
                    <div class="flex-1 min-w-0">
                        <div class="text-xs font-semibold text-white truncate"><?= htmlspecialchars($sv['service_name'] ?? '—') ?></div>
                        <div class="text-[10px] text-gray-500 truncate"><?= htmlspecialchars($sv['pseudo'] ?: ($sv['firstname'] ?? '')) ?> · <?= htmlspecialchars($sv['user_email'] ?? '') ?></div>
                    </div>
                    <div class="shrink-0 flex items-center gap-2">
                        <span class="badge <?= $st_badge ?>"><?= $st_label ?></span>
                        <?php if (($sv['renewal_price'] ?? 0) > 0): ?>
                        <span class="text-[10px] text-gray-400 font-mono"><?= number_format((float)$sv['renewal_price'],2,',','') ?>€</span>
                        <?php else: ?>
                        <span class="text-[10px] text-green-400">Gratuit</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

        </div>

        <!-- Raccourcis admin -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
            <?php
            $shortcuts = [
                ['/admin/nodes/',         'fa-network-wired', 'bg-sky-500/10',    'text-sky-400',    'Nodes'],
                ['/admin/eggs/',          'fa-egg',           'bg-purple-500/10', 'text-purple-400', 'Eggs'],
                ['/admin/products/',      'fa-box',           'bg-amber-500/10',  'text-amber-400',  'Produits'],
                ['/admin/extensions/',    'fa-puzzle-piece',  'bg-green-500/10',  'text-green-400',  'Extensions'],
                ['/admin/?view=invoices', 'fa-file-invoice-dollar', 'bg-yellow-500/10','text-yellow-400','Factures'],
                ['/admin/?view=settings', 'fa-sliders-h',    'bg-rose-500/10',   'text-rose-400',   'Paramètres'],
            ];
            foreach ($shortcuts as [$href,$icon,$bg,$color,$label]):
            ?>
            <a href="<?= $href ?>" class="card p-4 flex flex-col items-center gap-2.5 hover:border-white/20 transition text-center">
                <div class="w-9 h-9 rounded-xl <?= $bg ?> flex items-center justify-center">
                    <i class="fas <?= $icon ?> <?= $color ?> text-sm"></i>
                </div>
                <span class="text-xs font-semibold text-gray-300"><?= $label ?></span>
            </a>
            <?php endforeach; ?>
        </div>

    <!-- ═══════════════════════════════════════════════════
         VUE CLIENTS
    ════════════════════════════════════════════════════ -->
    <?php elseif ($view === 'clients'): ?>
    <div class="card overflow-hidden">
        <div class="px-5 py-4 border-b border-white/[0.05] flex items-center justify-between">
            <h2 class="text-sm font-bold text-white flex items-center gap-2"><i class="fas fa-users text-sky-400 text-xs"></i> Clients (<?php echo count($all_users); ?>)</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-white/5">
                        <th class="text-left px-6 py-3 text-xs text-gray-400 font-semibold uppercase tracking-wide">#</th>
                        <th class="text-left px-6 py-3 text-xs text-gray-400 font-semibold uppercase tracking-wide">Client</th>
                        <th class="text-left px-6 py-3 text-xs text-gray-400 font-semibold uppercase tracking-wide">Email</th>
                        <th class="text-left px-6 py-3 text-xs text-gray-400 font-semibold uppercase tracking-wide">Serveurs</th>
                        <th class="text-left px-6 py-3 text-xs text-gray-400 font-semibold uppercase tracking-wide">Inscrit le</th>
                        <th class="text-left px-6 py-3 text-xs text-gray-400 font-semibold uppercase tracking-wide">Rôle</th>
                        <th class="text-left px-6 py-3 text-xs text-gray-400 font-semibold uppercase tracking-wide">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($all_users as $u): ?>
                <tr>
                    <td class="text-gray-500 text-xs"><?php echo $u['id']; ?></td>
                    <td>
                        <div class="flex items-center gap-2.5">
                            <?php if (!empty($u['avatar']) && file_exists($_SERVER['DOCUMENT_ROOT'].'/'.$u['avatar'])): ?>
                                <img src="/<?php echo htmlspecialchars($u['avatar']); ?>" class="w-7 h-7 rounded-full object-cover border border-white/10 shrink-0">
                            <?php else: ?>
                                <div class="w-7 h-7 rounded-full bg-sky-500/15 flex items-center justify-center shrink-0 text-sky-400 text-xs font-bold"><?php echo strtoupper(substr($u['pseudo'] ?: $u['firstname'], 0, 1)); ?></div>
                            <?php endif; ?>
                            <div>
                                <div class="text-sm font-semibold text-white"><?php echo htmlspecialchars($u['pseudo'] ?: $u['firstname'].' '.$u['lastname']); ?></div>
                                <div class="text-xs text-gray-500"><?php echo htmlspecialchars($u['email']); ?></div>
                            </div>
                        </div>
                    </td>
                    <td><span class="badge badge-blue"><?php echo $u['server_count']; ?> serveur(s)</span></td>
                    <td class="text-gray-500 text-xs"><?php echo $u['created_at'] ? date('d/m/Y', strtotime($u['created_at'])) : '—'; ?></td>
                    <td><span class="badge <?php echo $u['is_admin'] ? 'badge-rose' : 'badge-gray'; ?>"><?php echo $u['is_admin'] ? 'Admin' : 'Client'; ?></span></td>
                    <td>
                        <div class="flex items-center gap-1.5">
                            <button onclick="openEmail('<?php echo htmlspecialchars($u['email']); ?>')" class="btn-action btn-sky"><i class="fas fa-envelope"></i> Email</button>
                            <?php if ($u['id'] !== (int)$_SESSION['user_id']): ?>
                            <form method="POST" action="/admin/?view=clients" style="display:inline">
                                <input type="hidden" name="action" value="toggle_admin">
                                <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                <button type="submit" class="btn-action <?php echo $u['is_admin'] ? 'btn-orange' : 'btn-blue'; ?>" title="<?php echo $u['is_admin'] ? 'Rétrograder' : 'Promouvoir admin'; ?>">
                                    <i class="fas <?php echo $u['is_admin'] ? 'fa-user-minus' : 'fa-user-shield'; ?>"></i>
                                </button>
                            </form>
                            <form method="POST" action="/admin/" onsubmit="return confirmDel('Supprimer #<?php echo $u['id']; ?> et ses serveurs ?')" style="display:inline">
                                <input type="hidden" name="action" value="delete_user">
                                <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                <button type="submit" class="btn-action btn-red"><i class="fas fa-trash"></i></button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════
         VUE SERVEURS
    ════════════════════════════════════════════════════ -->
    <?php elseif ($view === 'servers'): ?>
    <div class="card overflow-hidden">
        <div class="px-5 py-4 border-b border-white/[0.05] flex items-center justify-between">
            <h2 class="text-sm font-bold text-white flex items-center gap-2"><i class="fas fa-server text-green-400 text-xs"></i> Serveurs (<?php echo count($all_servers); ?>)</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-white/5">
                        <th class="text-left px-5 py-3 text-xs text-gray-400 font-semibold uppercase tracking-wide">Serveur</th>
                        <th class="text-left px-5 py-3 text-xs text-gray-400 font-semibold uppercase tracking-wide">Client</th>
                        <th class="text-left px-5 py-3 text-xs text-gray-400 font-semibold uppercase tracking-wide">Plan</th>
                        <th class="text-left px-5 py-3 text-xs text-gray-400 font-semibold uppercase tracking-wide">Prix</th>
                        <th class="text-left px-5 py-3 text-xs text-gray-400 font-semibold uppercase tracking-wide">Statut</th>
                        <th class="text-left px-5 py-3 text-xs text-gray-400 font-semibold uppercase tracking-wide">Expire</th>
                        <th class="text-left px-5 py-3 text-xs text-gray-400 font-semibold uppercase tracking-wide">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($all_servers as $sv): ?>
                <?php
                    $status = $sv['status'] ?? 'unknown';
                    $is_free = ($sv['renewal_price'] ?? 0) == 0;
                    $status_badge = match($status) {
                        'paid'      => 'bg-green-500/15 text-green-400 border-green-500/25',
                        'suspended' => 'bg-orange-500/15 text-orange-400 border-orange-500/25',
                        'expired'   => 'bg-red-500/15 text-red-400 border-red-500/25',
                        default     => 'bg-white/5 text-gray-400 border-white/10',
                    };
                ?>
                <tr class="border-b border-white/[0.03]">
                    <td class="px-5 py-4">
                        <div class="font-semibold text-white"><?php echo htmlspecialchars($sv['service_name'] ?? '—'); ?></div>
                        <div class="text-xs text-gray-500 font-mono"><?php echo htmlspecialchars(substr($sv['uuid'] ?? '', 0, 8)); ?></div>
                    </td>
                    <td class="px-5 py-4">
                        <div class="text-white text-xs font-semibold"><?php echo htmlspecialchars($sv['pseudo'] ?: $sv['firstname'] ?? '—'); ?></div>
                        <div class="text-xs text-gray-500"><?php echo htmlspecialchars($sv['user_email'] ?? '—'); ?></div>
                    </td>
                    <td class="px-5 py-4 text-gray-300 text-xs font-mono"><?php echo htmlspecialchars($sv['plan'] ?? '—'); ?></td>
                    <td class="px-5 py-4">
                        <?php if ($is_free): ?>
                            <span class="bg-green-500/10 text-green-400 border border-green-500/20 px-2 py-0.5 rounded-full text-xs font-bold">Gratuit</span>
                        <?php else: ?>
                            <span class="text-white font-bold"><?php echo number_format((float)($sv['renewal_price'] ?? 0), 2, ',', ''); ?>€</span><span class="text-gray-500 text-xs">/mois</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-5 py-4">
                        <span class="border px-2.5 py-0.5 rounded-full text-xs font-bold <?php echo $status_badge; ?>"><?php echo htmlspecialchars(ucfirst($status)); ?></span>
                    </td>
                    <td class="px-5 py-4 text-xs <?php echo $is_free ? 'text-gray-500' : 'text-gray-300'; ?>">
                        <?php echo $is_free ? '∞ À vie' : htmlspecialchars($sv['expires_at'] ? date('d/m/Y', strtotime($sv['expires_at'])) : '—'); ?>
                    </td>
                    <td class="px-5 py-4">
                        <div class="flex flex-wrap gap-1.5">
                            <?php if (!$is_free): ?>
                            <form method="POST" action="/admin/?view=servers">
                                <input type="hidden" name="action" value="renew_server">
                                <input type="hidden" name="server_uuid" value="<?php echo htmlspecialchars($sv['uuid']); ?>">
                                <button type="submit" class="bg-blue-500/15 hover:bg-blue-500/30 text-blue-400 border border-blue-500/20 px-2.5 py-1 rounded-lg text-xs font-semibold transition whitespace-nowrap">
                                    <i class="fas fa-redo mr-1"></i>+30j
                                </button>
                            </form>
                            <?php endif; ?>
                            <form method="POST" action="/admin/?view=servers">
                                <input type="hidden" name="action" value="<?php echo $status === 'suspended' ? 'unsuspend_server' : 'suspend_server'; ?>">
                                <input type="hidden" name="server_id" value="<?php echo htmlspecialchars($sv['server_id']); ?>">
                                <button type="submit" class="bg-orange-500/15 hover:bg-orange-500/30 text-orange-400 border border-orange-500/20 px-2.5 py-1 rounded-lg text-xs font-semibold transition whitespace-nowrap">
                                    <i class="fas fa-<?php echo $status === 'suspended' ? 'play' : 'pause'; ?> mr-1"></i><?php echo $status === 'suspended' ? 'Réactiver' : 'Suspendre'; ?>
                                </button>
                            </form>
                            <form method="POST" action="/admin/?view=servers" onsubmit="return confirmDel('Supprimer ce serveur définitivement ?')">
                                <input type="hidden" name="action" value="delete_server">
                                <input type="hidden" name="server_uuid" value="<?php echo htmlspecialchars($sv['uuid']); ?>">
                                <input type="hidden" name="server_id" value="<?php echo htmlspecialchars($sv['server_id']); ?>">
                                <button type="submit" class="bg-red-500/15 hover:bg-red-500/30 text-red-400 border border-red-500/20 px-2.5 py-1 rounded-lg text-xs font-semibold transition whitespace-nowrap">
                                    <i class="fas fa-trash mr-1"></i>Suppr.
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════
         VUE FACTURES (ADMIN)
    ════════════════════════════════════════════════════ -->
    <?php elseif ($view === 'invoices'): ?>
    <?php
        // Actions factures
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $inv_action = $_POST['inv_action'] ?? '';
            $inv_id     = trim($_POST['inv_id'] ?? '');
            if ($inv_id) {
                if ($inv_action === 'mark_paid') {
                    $pdo->prepare("UPDATE invoices SET status='paid', paid_at=NOW() WHERE invoice_id=?")->execute([$inv_id]);
                } elseif ($inv_action === 'mark_refunded') {
                    $pdo->prepare("UPDATE invoices SET status='refunded' WHERE invoice_id=?")->execute([$inv_id]);
                } elseif ($inv_action === 'delete_invoice') {
                    $pdo->prepare("DELETE FROM invoices WHERE invoice_id=?")->execute([$inv_id]);
                }
                header('Location: /admin/?view=invoices'); exit();
            }
        }
        $inv_page    = max(1,(int)($_GET['p'] ?? 1));
        $inv_perpage = 20;
        $inv_total   = (int)$pdo->query("SELECT COUNT(*) FROM invoices")->fetchColumn();
        $inv_pages   = max(1,(int)ceil($inv_total / $inv_perpage));
        $inv_offset  = ($inv_page - 1) * $inv_perpage;
        $inv_filter  = $_GET['status'] ?? 'all';
        if ($inv_filter !== 'all') {
            $inv_stmt = $pdo->prepare("SELECT i.*, u.email AS user_email, u.pseudo, u.firstname FROM invoices i LEFT JOIN users u ON u.id=i.user_id WHERE i.status=? ORDER BY i.created_at DESC LIMIT ? OFFSET ?");
            $inv_stmt->execute([$inv_filter, $inv_perpage, $inv_offset]);
        } else {
            $inv_stmt = $pdo->prepare("SELECT i.*, u.email AS user_email, u.pseudo, u.firstname FROM invoices i LEFT JOIN users u ON u.id=i.user_id ORDER BY i.created_at DESC LIMIT ? OFFSET ?");
            $inv_stmt->execute([$inv_perpage, $inv_offset]);
        }
        $all_invoices = $inv_stmt->fetchAll();
        $inv_stats = $pdo->query("SELECT
            SUM(CASE WHEN status='paid' THEN amount ELSE 0 END) AS revenue,
            COUNT(CASE WHEN status='paid' THEN 1 END) AS paid_count,
            COUNT(CASE WHEN status='pending' THEN 1 END) AS pending_count,
            COUNT(CASE WHEN status='refunded' THEN 1 END) AS refunded_count
            FROM invoices")->fetch();
    ?>
    <!-- Sous-stats -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-5">
        <div class="stat-card"><div class="flex items-center justify-between mb-2"><span class="text-xs text-gray-500">Total factures</span><div class="w-7 h-7 rounded-lg bg-sky-500/15 flex items-center justify-center"><i class="fas fa-file-invoice text-sky-400 text-xs"></i></div></div><div class="text-2xl font-black text-white"><?= $inv_total ?></div><div class="text-xs text-gray-500 mt-1">Émises</div></div>
        <div class="stat-card"><div class="flex items-center justify-between mb-2"><span class="text-xs text-gray-500">Revenus</span><div class="w-7 h-7 rounded-lg bg-green-500/15 flex items-center justify-center"><i class="fas fa-euro-sign text-green-400 text-xs"></i></div></div><div class="text-2xl font-black text-green-400"><?= number_format((float)($inv_stats['revenue']??0),2,',','') ?>€</div><div class="text-xs text-gray-500 mt-1"><?= (int)$inv_stats['paid_count'] ?> payées</div></div>
        <div class="stat-card"><div class="flex items-center justify-between mb-2"><span class="text-xs text-gray-500">En attente</span><div class="w-7 h-7 rounded-lg bg-amber-500/15 flex items-center justify-center"><i class="fas fa-clock text-amber-400 text-xs"></i></div></div><div class="text-2xl font-black text-amber-400"><?= (int)$inv_stats['pending_count'] ?></div><div class="text-xs text-gray-500 mt-1">À encaisser</div></div>
        <div class="stat-card"><div class="flex items-center justify-between mb-2"><span class="text-xs text-gray-500">Remboursées</span><div class="w-7 h-7 rounded-lg bg-blue-500/15 flex items-center justify-center"><i class="fas fa-rotate-left text-blue-400 text-xs"></i></div></div><div class="text-2xl font-black text-blue-400"><?= (int)$inv_stats['refunded_count'] ?></div><div class="text-xs text-gray-500 mt-1">Refunds</div></div>
    </div>
    <!-- Filtres -->
    <div class="flex gap-2 mb-4 flex-wrap">
        <?php foreach (['all'=>'Toutes','paid'=>'Payées','pending'=>'En attente','refunded'=>'Remboursées'] as $k=>$lbl): ?>
        <a href="?view=invoices&status=<?= $k ?>" class="text-xs font-semibold px-3 py-1.5 rounded-lg border transition <?= $inv_filter===$k ? 'bg-rose-500/15 text-rose-400 border-rose-500/25' : 'bg-white/5 text-gray-400 border-white/10 hover:bg-white/10' ?>"><?= $lbl ?></a>
        <?php endforeach; ?>
    </div>
    <div class="card overflow-hidden">
        <div class="px-5 py-4 border-b border-white/[0.05] flex items-center justify-between">
            <h2 class="text-sm font-bold text-white flex items-center gap-2"><i class="fas fa-file-invoice-dollar text-yellow-400 text-xs"></i> Factures (<?= $inv_total ?>)</h2>
        </div>
        <?php if (empty($all_invoices)): ?>
        <div class="px-5 py-12 text-center text-gray-500 text-sm">Aucune facture trouvée.</div>
        <?php else: ?>
        <div class="overflow-x-auto">
        <table>
            <thead><tr>
                <th>N° Facture</th><th>Client</th><th>Service</th><th>Type</th>
                <th>Montant</th><th>Méthode</th><th>Date</th><th>Statut</th><th>Actions</th>
            </tr></thead>
            <tbody>
            <?php foreach ($all_invoices as $inv):
                $ist = match($inv['status']) { 'paid'=>['badge-green','Payée'],'pending'=>['badge-orange','En attente'],'refunded'=>['badge-blue','Remboursée'],default=>['badge-gray','Inconnu'] };
                $itype = match($inv['type']) { 'renewal'=>'Renouvellement','purchase'=>'Achat',default=>ucfirst($inv['type']) };
                $ipay = match($inv['payment_method']??'') { 'stripe'=>'<i class="fas fa-credit-card text-indigo-400 mr-1"></i>Stripe','paypal'=>'<i class="fab fa-paypal text-blue-400 mr-1"></i>PayPal',default=>'Manuel' };
            ?>
            <tr>
                <td class="font-mono text-xs text-sky-400"><?= htmlspecialchars($inv['invoice_id']) ?></td>
                <td>
                    <div class="text-xs font-semibold text-white"><?= htmlspecialchars($inv['pseudo'] ?: ($inv['firstname']??'')) ?></div>
                    <div class="text-[10px] text-gray-500"><?= htmlspecialchars($inv['user_email']??'') ?></div>
                </td>
                <td class="text-xs text-gray-300 max-w-[140px] truncate"><?= htmlspecialchars($inv['service_name']) ?></td>
                <td><span class="badge badge-gray text-[10px]"><?= $itype ?></span></td>
                <td class="font-bold text-white"><?= number_format((float)$inv['amount'],2,',','') ?>€</td>
                <td class="text-xs text-gray-400"><?= $ipay ?></td>
                <td class="text-xs text-gray-500"><?= date('d/m/Y',strtotime($inv['created_at'])) ?></td>
                <td><span class="badge <?= $ist[0] ?>"><?= $ist[1] ?></span></td>
                <td>
                    <div class="flex items-center gap-1.5">
                        <a href="/client/billing/invoice/?id=<?= urlencode($inv['invoice_id']) ?>" target="_blank" class="btn-action btn-sky" title="Voir"><i class="fas fa-eye"></i></a>
                        <a href="/client/billing/invoice/print/?id=<?= urlencode($inv['invoice_id']) ?>" target="_blank" class="btn-action btn-blue" title="Imprimer"><i class="fas fa-print"></i></a>
                        <?php if ($inv['status'] === 'pending'): ?>
                        <form method="POST" action="/admin/?view=invoices" style="display:inline">
                            <input type="hidden" name="inv_action" value="mark_paid">
                            <input type="hidden" name="inv_id" value="<?= htmlspecialchars($inv['invoice_id']) ?>">
                            <button class="btn-action btn-sky" title="Marquer payée"><i class="fas fa-check"></i></button>
                        </form>
                        <?php endif; ?>
                        <?php if ($inv['status'] === 'paid'): ?>
                        <form method="POST" action="/admin/?view=invoices" style="display:inline">
                            <input type="hidden" name="inv_action" value="mark_refunded">
                            <input type="hidden" name="inv_id" value="<?= htmlspecialchars($inv['invoice_id']) ?>">
                            <button class="btn-action btn-orange" title="Rembourser" onclick="return confirm('Marquer comme remboursée ?')"><i class="fas fa-rotate-left"></i></button>
                        </form>
                        <?php endif; ?>
                        <form method="POST" action="/admin/?view=invoices" style="display:inline" onsubmit="return confirmDel('Supprimer cette facture ?')">
                            <input type="hidden" name="inv_action" value="delete_invoice">
                            <input type="hidden" name="inv_id" value="<?= htmlspecialchars($inv['invoice_id']) ?>">
                            <button class="btn-action btn-red"><i class="fas fa-trash"></i></button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php if ($inv_pages > 1): ?>
        <div class="flex items-center justify-center gap-2 px-5 py-4 border-t border-white/[0.04]">
            <?php if ($inv_page > 1): ?>
            <a href="?view=invoices&status=<?= $inv_filter ?>&p=<?= $inv_page-1 ?>" class="btn-action btn-blue"><i class="fas fa-chevron-left"></i></a>
            <?php endif; ?>
            <span class="text-xs text-gray-500">Page <?= $inv_page ?> / <?= $inv_pages ?></span>
            <?php if ($inv_page < $inv_pages): ?>
            <a href="?view=invoices&status=<?= $inv_filter ?>&p=<?= $inv_page+1 ?>" class="btn-action btn-blue"><i class="fas fa-chevron-right"></i></a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- ═══════════════════════════════════════════════════
         VUE EMAIL BROADCAST
    ════════════════════════════════════════════════════ -->
    <?php elseif ($view === 'email'): ?>
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Email to 1 client -->
        <div class="glass rounded-3xl p-6">
            <h2 class="text-lg font-black text-white mb-5 flex items-center gap-2"><i class="fas fa-envelope text-sky-400"></i> Email ciblé</h2>
            <form method="POST" action="/admin/?view=email">
                <input type="hidden" name="action" value="send_email">
                <div class="space-y-4">
                    <div>
                        <label class="block text-xs text-gray-400 mb-1 font-semibold uppercase tracking-wide">Destinataire</label>
                        <input type="email" name="email_to" required list="clients-list" placeholder="client@example.com" class="w-full rounded-xl px-4 py-2.5 text-sm">
                        <datalist id="clients-list">
                            <?php foreach ($all_users as $u): ?>
                                <option value="<?php echo htmlspecialchars($u['email']); ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-400 mb-1 font-semibold uppercase tracking-wide">Sujet</label>
                        <input type="text" name="email_subject" required placeholder="Objet de l'email..." class="w-full rounded-xl px-4 py-2.5 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-400 mb-1 font-semibold uppercase tracking-wide">Message</label>
                        <textarea name="email_body" rows="6" required placeholder="Votre message..." class="w-full rounded-xl px-4 py-2.5 text-sm resize-none"></textarea>
                    </div>
                    <button type="submit" class="w-full bg-sky-600 hover:bg-sky-500 font-bold py-3 rounded-xl text-sm transition flex items-center justify-center gap-2">
                        <i class="fas fa-paper-plane"></i> Envoyer l'email
                    </button>
                </div>
            </form>
        </div>

        <!-- Quick client email list -->
        <div class="glass rounded-3xl p-6">
            <h2 class="text-lg font-black text-white mb-5 flex items-center gap-2"><i class="fas fa-address-book text-purple-400"></i> Clients rapides</h2>
            <div class="space-y-2 max-h-[480px] overflow-y-auto pr-2">
                <?php foreach ($all_users as $u): ?>
                <div class="flex items-center justify-between py-2.5 px-3 rounded-xl bg-white/[0.02] border border-white/[0.03] hover:bg-white/[0.05] transition">
                    <div>
                        <div class="font-semibold text-white text-sm"><?php echo htmlspecialchars($u['pseudo'] ?: $u['firstname'] . ' ' . $u['lastname']); ?></div>
                        <div class="text-xs text-gray-500"><?php echo htmlspecialchars($u['email']); ?></div>
                    </div>
                    <button onclick="openEmail('<?php echo htmlspecialchars($u['email']); ?>')"
                        class="bg-sky-500/15 hover:bg-sky-500/30 text-sky-400 border border-sky-500/20 px-3 py-1 rounded-lg text-xs font-semibold transition">
                        <i class="fas fa-envelope mr-1"></i> Email
                    </button>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <!-- ═══════════════════════════════════════════════════
         VUE PARAMÈTRES
    ════════════════════════════════════════════════════ -->
    <?php elseif ($view === 'settings'): ?>
    <form method="POST" action="/admin/?view=settings">
        <input type="hidden" name="action" value="save_settings">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            <!-- Pterodactyl Panel -->
            <div class="glass rounded-3xl p-6 space-y-5">
                <h2 class="text-lg font-black text-white flex items-center gap-2 pb-3 border-b border-white/5">
                    <i class="fas fa-cogs text-amber-400"></i> Panel Pterodactyl
                </h2>
                <div>
                    <label class="block text-xs text-gray-400 mb-1.5 font-semibold uppercase tracking-wide">URL du Panel</label>
                    <input type="url" name="panel_url" value="<?php echo htmlspecialchars($cfg['panel_url'] ?? ''); ?>" placeholder="https://panel.example.com" class="w-full rounded-xl px-4 py-2.5 text-sm">
                    <p class="text-[11px] text-gray-500 mt-1">URL de base de votre instance Pterodactyl.</p>
                </div>
                <div>
                    <label class="block text-xs text-gray-400 mb-1.5 font-semibold uppercase tracking-wide">API Key Admin <span class="text-rose-400 normal-case font-normal">(ptla_…)</span></label>
                    <input type="text" name="api_key_admin" value="<?php echo htmlspecialchars($cfg['api_key_admin'] ?? ''); ?>" placeholder="ptla_xxxxxxxxxxxx" class="w-full rounded-xl px-4 py-2.5 text-sm font-mono">
                    <p class="text-[11px] text-gray-500 mt-1">Clé admin pour créer/supprimer des serveurs. Panel → Compte → API Keys.</p>
                </div>
                <div>
                    <label class="block text-xs text-gray-400 mb-1.5 font-semibold uppercase tracking-wide">API Key Client <span class="text-sky-400 normal-case font-normal">(ptlc_…)</span></label>
                    <input type="text" name="api_key_client" value="<?php echo htmlspecialchars($cfg['api_key_client'] ?? ''); ?>" placeholder="ptlc_xxxxxxxxxxxx" class="w-full rounded-xl px-4 py-2.5 text-sm font-mono">
                    <p class="text-[11px] text-gray-500 mt-1">Clé client pour les actions côté utilisateur.</p>
                </div>
            </div>

            <!-- Site & phpMyAdmin -->
            <div class="glass rounded-3xl p-6 space-y-5">
                <h2 class="text-lg font-black text-white flex items-center gap-2 pb-3 border-b border-white/5">
                    <i class="fas fa-globe text-sky-400"></i> Site & Outils
                </h2>
                <div>
                    <label class="block text-xs text-gray-400 mb-1.5 font-semibold uppercase tracking-wide">Nom du site</label>
                    <input type="text" name="site_name" value="<?php echo htmlspecialchars($cfg['site_name'] ?? 'OrinHeberge'); ?>" class="w-full rounded-xl px-4 py-2.5 text-sm">
                </div>
                <div>
                    <label class="block text-xs text-gray-400 mb-1.5 font-semibold uppercase tracking-wide">URL phpMyAdmin</label>
                    <input type="url" name="phpmyadmin_url" value="<?php echo htmlspecialchars($cfg['phpmyadmin_url'] ?? ''); ?>" placeholder="https://php.example.com" class="w-full rounded-xl px-4 py-2.5 text-sm">
                </div>
            </div>

            <!-- SMTP -->
            <div class="glass rounded-3xl p-6 space-y-5 lg:col-span-2">
                <h2 class="text-lg font-black text-white flex items-center gap-2 pb-3 border-b border-white/5">
                    <i class="fas fa-envelope text-purple-400"></i> Configuration SMTP (emails)
                </h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs text-gray-400 mb-1.5 font-semibold uppercase tracking-wide">Serveur SMTP</label>
                        <input type="text" name="smtp_host" value="<?php echo htmlspecialchars($cfg['smtp_host'] ?? ''); ?>" placeholder="smtp.gmail.com" class="w-full rounded-xl px-4 py-2.5 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-400 mb-1.5 font-semibold uppercase tracking-wide">Port</label>
                        <input type="number" name="smtp_port" value="<?php echo htmlspecialchars($cfg['smtp_port'] ?? '587'); ?>" placeholder="587" class="w-full rounded-xl px-4 py-2.5 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-400 mb-1.5 font-semibold uppercase tracking-wide">Utilisateur SMTP</label>
                        <input type="text" name="smtp_user" value="<?php echo htmlspecialchars($cfg['smtp_user'] ?? ''); ?>" placeholder="user@example.com" class="w-full rounded-xl px-4 py-2.5 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-400 mb-1.5 font-semibold uppercase tracking-wide">Mot de passe SMTP</label>
                        <input type="password" name="smtp_pass" value="<?php echo htmlspecialchars($cfg['smtp_pass'] ?? ''); ?>" placeholder="••••••••" class="w-full rounded-xl px-4 py-2.5 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-400 mb-1.5 font-semibold uppercase tracking-wide">Email expéditeur</label>
                        <input type="email" name="smtp_from" value="<?php echo htmlspecialchars($cfg['smtp_from'] ?? ''); ?>" placeholder="no-reply@example.com" class="w-full rounded-xl px-4 py-2.5 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-400 mb-1.5 font-semibold uppercase tracking-wide">Nom expéditeur</label>
                        <input type="text" name="smtp_from_name" value="<?php echo htmlspecialchars($cfg['smtp_from_name'] ?? ''); ?>" placeholder="OrinHeberge" class="w-full rounded-xl px-4 py-2.5 text-sm">
                    </div>
                </div>
            </div>

        </div>

        <!-- Bouton save -->
        <div class="mt-6 flex justify-end">
            <button type="submit" class="bg-rose-600 hover:bg-rose-500 text-white font-bold px-8 py-3 rounded-xl text-sm transition flex items-center gap-2 shadow-lg shadow-rose-900/30">
                <i class="fas fa-save"></i> Sauvegarder les paramètres
            </button>
        </div>
    </form>

    <?php endif; ?>

    </div><!-- /content -->
</div><!-- /main-content -->
</body>
</html>

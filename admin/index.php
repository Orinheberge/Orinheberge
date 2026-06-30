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
$view = $_GET['view'] ?? 'clients';

$all_users = $pdo->query('SELECT u.id, u.pseudo, u.firstname, u.lastname, u.email, u.is_admin, u.avatar, u.created_at,
    (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id) AS server_count
    FROM users u ORDER BY u.id DESC')->fetchAll();

$all_servers = $pdo->query('SELECT o.*, u.email AS user_email, u.pseudo, u.firstname FROM orders o
    LEFT JOIN users u ON u.id = o.user_id ORDER BY o.created_at DESC')->fetchAll();

$total_revenue = $pdo->query('SELECT COALESCE(SUM(amount),0) FROM orders WHERE status="paid"')->fetchColumn();
$active_servers = $pdo->query('SELECT COUNT(*) FROM orders WHERE status="paid" OR renewal_price=0')->fetchColumn();
$open_tickets = $pdo->query("SELECT COUNT(*) FROM support_tickets WHERE status != 'Fermé'")->fetchColumn();

$is_logged_in = true;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OrinHeberge | Admin Panel</title>
    <link rel="icon" type="image/png" href="/favicon.ico">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #0b0f19; scroll-behavior: smooth; }
        .glass { background: rgba(255,255,255,0.04); backdrop-filter: blur(14px); border: 1px solid rgba(255,255,255,0.08); }
        .gradient-text { background: linear-gradient(90deg, #f43f5e, #f97316); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        #mobileMenu { display: none; } #mobileMenu.active { display: block; }
        .tab-active { background: rgba(244,63,94,0.15); border-color: rgba(244,63,94,0.4); color: #f43f5e; }
        .tab-inactive { background: rgba(255,255,255,0.04); color: #9ca3af; }
        .tab-inactive:hover { background: rgba(255,255,255,0.08); color: #e5e7eb; }
        tr:hover td { background: rgba(255,255,255,0.02); }
        input, textarea, select { background: rgba(255,255,255,0.05) !important; border: 1px solid rgba(255,255,255,0.1) !important; color: #e5e7eb !important; }
        input:focus, textarea:focus, select:focus { outline: none !important; border-color: rgba(244,63,94,0.5) !important; }
    </style>
    <script>
        function toggleMenu() { document.getElementById('mobileMenu').classList.toggle('active'); }
        function confirmDel(msg) { return confirm('⚠️ ' + msg + '\nCette action est irréversible.'); }
        function openEmail(email) {
            document.getElementById('modal-email').classList.remove('hidden');
            document.getElementById('email-to').value = email;
        }
        function closeEmail() { document.getElementById('modal-email').classList.add('hidden'); }
    </script>
</head>
<body class="text-gray-200 font-sans min-h-screen flex flex-col antialiased">

<?php $active_nav = ''; include $_SERVER['DOCUMENT_ROOT'] . '/inc/navbar.php'; ?>

<!-- Modal Email -->
<div id="modal-email" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
    <div class="glass rounded-3xl p-8 w-full max-w-lg">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-xl font-black text-white flex items-center gap-2"><i class="fas fa-envelope text-sky-400"></i> Envoyer un email</h2>
            <button onclick="closeEmail()" class="text-gray-400 hover:text-white text-2xl leading-none">&times;</button>
        </div>
        <form method="POST" action="/admin/">
            <input type="hidden" name="action" value="send_email">
            <div class="space-y-4">
                <div>
                    <label class="block text-xs text-gray-400 mb-1 font-semibold uppercase tracking-wide">Destinataire</label>
                    <input type="email" name="email_to" id="email-to" required class="w-full rounded-xl px-4 py-2.5 text-sm">
                </div>
                <div>
                    <label class="block text-xs text-gray-400 mb-1 font-semibold uppercase tracking-wide">Sujet</label>
                    <input type="text" name="email_subject" required placeholder="Objet de l'email..." class="w-full rounded-xl px-4 py-2.5 text-sm">
                </div>
                <div>
                    <label class="block text-xs text-gray-400 mb-1 font-semibold uppercase tracking-wide">Message</label>
                    <textarea name="email_body" rows="5" required placeholder="Votre message ici..." class="w-full rounded-xl px-4 py-2.5 text-sm resize-none"></textarea>
                </div>
            </div>
            <div class="flex gap-3 mt-6">
                <button type="submit" class="flex-1 bg-sky-600 hover:bg-sky-500 font-bold py-3 rounded-xl text-sm transition"><i class="fas fa-paper-plane mr-2"></i>Envoyer</button>
                <button type="button" onclick="closeEmail()" class="flex-1 glass hover:bg-white/10 font-bold py-3 rounded-xl text-sm transition">Annuler</button>
            </div>
        </form>
    </div>
</div>

<main class="flex-grow max-w-[1600px] mx-auto w-full px-4 sm:px-6 py-8">

    <!-- Header -->
    <div class="mb-8">
        <div class="flex items-center gap-3 mb-2">
            <div class="w-10 h-10 bg-rose-500/20 rounded-xl flex items-center justify-center shrink-0">
                <i class="fas fa-shield-alt text-rose-400"></i>
            </div>
            <h1 class="text-4xl font-black gradient-text">Admin Panel</h1>
        </div>
        <p class="text-gray-400 text-sm ml-13">Gérez les clients, serveurs et communications.</p>
    </div>

    <?php if ($flash): echo "<div class='mb-6'>$flash</div>"; endif; ?>

    <!-- Stats -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
        <div class="glass p-5 rounded-2xl flex items-center gap-4">
            <div class="w-10 h-10 bg-sky-500/15 rounded-xl flex items-center justify-center shrink-0"><i class="fas fa-users text-sky-400"></i></div>
            <div><div class="text-2xl font-black text-white"><?php echo count($all_users); ?></div><div class="text-xs text-gray-400">Clients</div></div>
        </div>
        <div class="glass p-5 rounded-2xl flex items-center gap-4">
            <div class="w-10 h-10 bg-green-500/15 rounded-xl flex items-center justify-center shrink-0"><i class="fas fa-server text-green-400"></i></div>
            <div><div class="text-2xl font-black text-white"><?php echo count($all_servers); ?></div><div class="text-xs text-gray-400">Serveurs totaux</div></div>
        </div>
        <div class="glass p-5 rounded-2xl flex items-center gap-4">
            <div class="w-10 h-10 bg-yellow-500/15 rounded-xl flex items-center justify-center shrink-0"><i class="fas fa-euro-sign text-yellow-400"></i></div>
            <div><div class="text-2xl font-black text-white"><?php echo number_format((float)$total_revenue, 2, ',', ' '); ?>€</div><div class="text-xs text-gray-400">Revenus</div></div>
        </div>
        <div class="glass p-5 rounded-2xl flex items-center gap-4">
            <div class="w-10 h-10 bg-purple-500/15 rounded-xl flex items-center justify-center shrink-0"><i class="fas fa-headset text-purple-400"></i></div>
            <div><div class="text-2xl font-black text-white"><?php echo $open_tickets; ?></div><div class="text-xs text-gray-400">Tickets ouverts</div></div>
        </div>
    </div>

    <!-- Tabs navigation -->
    <div class="flex flex-wrap gap-2 mb-6">
        <a href="/admin/?view=clients" class="px-5 py-2.5 rounded-full text-sm font-bold border transition <?php echo $view === 'clients' ? 'tab-active border-rose-500/40' : 'tab-inactive border-white/10'; ?>">
            <i class="fas fa-users mr-1"></i> Clients
        </a>
        <a href="/admin/?view=servers" class="px-5 py-2.5 rounded-full text-sm font-bold border transition <?php echo $view === 'servers' ? 'tab-active border-rose-500/40' : 'tab-inactive border-white/10'; ?>">
            <i class="fas fa-server mr-1"></i> Serveurs
        </a>
        <a href="/admin/?view=email" class="px-5 py-2.5 rounded-full text-sm font-bold border transition <?php echo $view === 'email' ? 'tab-active border-rose-500/40' : 'tab-inactive border-white/10'; ?>">
            <i class="fas fa-envelope mr-1"></i> Email
        </a>
        <a href="/admin/?view=settings" class="px-5 py-2.5 rounded-full text-sm font-bold border transition <?php echo $view === 'settings' ? 'tab-active border-rose-500/40' : 'tab-inactive border-white/10'; ?>">
            <i class="fas fa-sliders-h mr-1"></i> Paramètres
        </a>
    </div>

    <!-- ═══════════════════════════════════════════════════
         VUE CLIENTS
    ════════════════════════════════════════════════════ -->
    <?php if ($view === 'clients'): ?>
    <div class="glass rounded-3xl overflow-hidden">
        <div class="p-6 border-b border-white/5 flex items-center justify-between">
            <h2 class="text-lg font-black text-white flex items-center gap-2"><i class="fas fa-users text-sky-400"></i> Liste des clients (<?php echo count($all_users); ?>)</h2>
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
                <tr class="border-b border-white/[0.03]">
                    <td class="px-6 py-4 text-gray-500"><?php echo $u['id']; ?></td>
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-3">
                            <?php if (!empty($u['avatar']) && file_exists($_SERVER['DOCUMENT_ROOT'] . '/' . $u['avatar'])): ?>
                                <img src="/<?php echo htmlspecialchars($u['avatar']); ?>" class="w-8 h-8 rounded-full object-cover border border-white/10 shrink-0">
                            <?php else: ?>
                                <div class="w-8 h-8 rounded-full bg-sky-500/20 flex items-center justify-center shrink-0 text-sky-400 text-xs font-bold">
                                    <?php echo strtoupper(substr($u['pseudo'] ?: $u['firstname'], 0, 1)); ?>
                                </div>
                            <?php endif; ?>
                            <div>
                                <div class="font-semibold text-white"><?php echo htmlspecialchars($u['pseudo'] ?: $u['firstname'] . ' ' . $u['lastname']); ?></div>
                                <div class="text-xs text-gray-500"><?php echo htmlspecialchars($u['firstname'] . ' ' . $u['lastname']); ?></div>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 text-gray-300"><?php echo htmlspecialchars($u['email']); ?></td>
                    <td class="px-6 py-4">
                        <span class="bg-sky-500/10 text-sky-400 border border-sky-500/20 px-2.5 py-0.5 rounded-full text-xs font-bold"><?php echo $u['server_count']; ?></span>
                    </td>
                    <td class="px-6 py-4 text-gray-400 text-xs"><?php echo htmlspecialchars($u['created_at'] ?? '—'); ?></td>
                    <td class="px-6 py-4">
                        <?php if ($u['is_admin']): ?>
                            <span class="bg-rose-500/15 text-rose-400 border border-rose-500/30 px-2.5 py-0.5 rounded-full text-xs font-bold">Admin</span>
                        <?php else: ?>
                            <span class="bg-white/5 text-gray-400 border border-white/10 px-2.5 py-0.5 rounded-full text-xs">Client</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-2">
                            <button onclick="openEmail('<?php echo htmlspecialchars($u['email']); ?>')"
                                class="bg-sky-500/15 hover:bg-sky-500/30 text-sky-400 border border-sky-500/20 px-3 py-1.5 rounded-xl text-xs font-semibold transition flex items-center gap-1.5">
                                <i class="fas fa-envelope"></i> Email
                            </button>
                            <?php if ($u['id'] !== (int)$_SESSION['user_id']): ?>
                            <form method="POST" action="/admin/" onsubmit="return confirmDel('Supprimer le client #<?php echo $u['id']; ?> et tous ses serveurs ?')">
                                <input type="hidden" name="action" value="delete_user">
                                <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                <button type="submit" class="bg-red-500/15 hover:bg-red-500/30 text-red-400 border border-red-500/20 px-3 py-1.5 rounded-xl text-xs font-semibold transition flex items-center gap-1.5">
                                    <i class="fas fa-trash"></i> Supprimer
                                </button>
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
    <div class="glass rounded-3xl overflow-hidden">
        <div class="p-6 border-b border-white/5 flex items-center justify-between">
            <h2 class="text-lg font-black text-white flex items-center gap-2"><i class="fas fa-server text-green-400"></i> Tous les serveurs (<?php echo count($all_servers); ?>)</h2>
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

</main>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/inc/footer.php'; ?>

<div class="fixed bottom-6 right-6 z-50">
    <a href="/discord/" target="_blank" class="bg-[#5865F2] hover:bg-[#4752C4] transition text-white px-5 py-4 rounded-full font-bold flex items-center gap-2 shadow-2xl hover:scale-105 transform duration-200">
        <i class="fab fa-discord text-xl"></i>
        <span class="hidden sm:inline text-sm">Support Discord</span>
    </a>
</div>
</body>
</html>

<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// ── Accès ─────────────────────────────────────────────────────────────────────
if (!isset($_SESSION['user_id'])) { header("Location: /login/"); exit(); }
if (!isset($_GET['uuid']))        { die("Erreur : Aucun identifiant de serveur spécifié."); }

$target_uuid = $_GET['uuid'];

// ── DB ────────────────────────────────────────────────────────────────────────
try {
    $pdo = new PDO(
        "mysql:host=localhost;dbname=s43_orinheberge;charset=utf8mb4",
        "root", "1504",
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (PDOException $e) {
    die("Erreur BDD : " . $e->getMessage());
}

// ── Settings depuis BDD ───────────────────────────────────────────────────────
$cfg = [];
foreach ($pdo->query('SELECT `key`, `value` FROM settings') as $row) $cfg[$row['key']] = $row['value'];
$panel_url      = $cfg['panel_url']      ?? 'https://panel.orinstone.deepstone.fr';
$api_key_client = $cfg['api_key_client'] ?? '';
$phpmyadmin_url = $cfg['phpmyadmin_url'] ?? 'https://php.orinstone.deepstone.fr';
$headers_client = [
    "Authorization: Bearer $api_key_client",
    "Accept: application/vnd.pterodactyl.v1+json",
    "Content-Type: application/json"
];

// Récupération du serveur
$stmt = $pdo->prepare("SELECT id, service_name, uuid FROM orders WHERE user_id = ? AND uuid = ?");
$stmt->execute([$_SESSION['user_id'], $target_uuid]);
$server = $stmt->fetch();

if (!$server) {
    die("Sécurité : Ce serveur n'existe pas ou ne vous appartient pas.");
}

$short_identifier = substr(($server['uuid'] ?? ''), 0, 8);
$service_name_lower = strtolower($server['service_name'] ?? '');

// Détection automatique du type de service
$service_type = "linux"; 
if (strpos($service_name_lower, 'minecraft') !== false || strpos($service_name_lower, 'mc') !== false || $target_uuid === "5dd7bbe4-fecc-4808-b6dd-a671ec46bc35") {
    $service_type = "minecraft";
} elseif (strpos($service_name_lower, 'javascript') !== false || strpos($service_name_lower, 'node') !== false || strpos($service_name_lower, 'js') !== false || $target_uuid === "cbfee771-4409-40b8-9f1e-5c071934aff6") {
    $service_type = "javascript"; 
} elseif (strpos($service_name_lower, 'php') !== false) {
    $service_type = "php";
}

// Fonction API Pterodactyl
function clientApiRequest($panel_url, $headers, $endpoint, $method = "GET", $data = null) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $panel_url . "/api/client/" . $endpoint);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    if (strtoupper($method) === "POST") {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    $res = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    return ['code' => $http_code, 'data' => $res ? json_decode($res, true) : null];
}

/*
|--------------------------------------------------------------------------
| TRAITEMENT AJAX : LECTURE DES LOGS ET STATS
|--------------------------------------------------------------------------
*/
if (isset($_GET['fetch_runtime'])) {
    ini_set('display_errors', 0);
    header('Content-Type: application/json; charset=utf-8');

    $logs = [];
    $state = "unknown";
    $stats = [];

    // Récupération des ressources de l'API
    $response = clientApiRequest($panel_url, $headers_client, "servers/$short_identifier/resources");
    $state = $response['data']['attributes']['current_state'] ?? 'unknown';
    
    if (isset($response['data']['attributes']['resources'])) {
        $resData = $response['data']['attributes']['resources'];
        $stats = [
            'cpu' => round($resData['cpu_absolute'] ?? 0, 1),
            'ram_used' => round(($resData['memory_bytes'] ?? 0) / 1024 / 1024, 1),
            'disk_used' => round(($resData['disk_bytes'] ?? 0) / 1024 / 1024, 1),
            'network_rx' => round(($resData['network_rx_bytes'] ?? 0) / 1024 / 1024, 2),
            'network_tx' => round(($resData['network_tx_bytes'] ?? 0) / 1024 / 1024, 2),
            'uptime' => $resData['uptime'] ?? 0,
            'players_current' => 0,
            'players_max' => 0
        ];
    }

    // Récupération des logs (Priorité SQL, Fallback API si vide)
    try {
        if ($service_type === "javascript") {
            // Lecture de la table logs_bot pour l'instance Node.js
            $logStmt = $pdo->prepare("SELECT action_type, description FROM logs_bot ORDER BY id DESC LIMIT 100");
            $logStmt->execute();
            $rows = $logStmt->fetchAll();
            foreach ($rows as $row) {
                $logs[] = "[" . strtoupper($row['action_type']) . "] " . $row['description'];
            }
        } else {
            // Lecture de la table server_logs pour Minecraft (Correspondance exacte avec tes colonnes)
            $logStmt = $pdo->prepare("SELECT log_level, message FROM server_logs ORDER BY id DESC LIMIT 120");
            $logStmt->execute();
            $rows = $logStmt->fetchAll();
            foreach ($rows as $row) {
                $logs[] = "[" . $row['log_level'] . "] " . $row['message'];
            }
        }
        $logs = array_reverse($logs);
    } catch (PDOException $e) {
        // En cas d'erreur de table
    }

    // Si la table SQL est vide, on récupère les logs en direct via Pterodactyl (évite l'écran vide)
    if (empty($logs)) {
        $logs[] = "[Système] Flux SQL indisponible ou vide. Connexion aux logs de l'instance...";
    }

    // Récupération des allocations et de l'addon Blueprint Player Listing
    $details = clientApiRequest($panel_url, $headers_client, "servers/$short_identifier");
    if (isset($details['data']['attributes'])) {
        $attr = $details['data']['attributes'];
        $stats['ram_max'] = $attr['limits']['memory'] ?? 0;
        $stats['disk_max'] = $attr['limits']['disk'] ?? 0;
        
        // Extraction des données de l'addon Blueprint Player Listing
        if (isset($attr['players_online'])) {
            $stats['players_current'] = intval($attr['players_online']);
            $stats['players_max'] = intval($attr['players_max'] ?? 20);
        } elseif (isset($attr['blueprint_data']['players'])) {
            $stats['players_current'] = intval($attr['blueprint_data']['players']['current'] ?? 0);
            $stats['players_max'] = intval($attr['blueprint_data']['players']['max'] ?? 0);
        }

        if (isset($attr['relationships']['allocations']['data'])) {
            foreach ($attr['relationships']['allocations']['data'] as $alloc) {
                if ($alloc['attributes']['is_default'] ?? false) {
                    $server_ip = $alloc['attributes']['ip_alias'] ?? $alloc['attributes']['ip'];
                    $server_port = intval($alloc['attributes']['port']);
                    $stats['address'] = $server_ip . ":" . $server_port;
                    break;
                }
            }
        }
    }

    echo json_encode(['status' => $state, 'logs' => $logs, 'stats' => $stats]);
    exit();
}

/*
|--------------------------------------------------------------------------
| TRAITEMENT AJAX : BOUTONS POWER
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_power'])) {
    ini_set('display_errors', 0);
    header('Content-Type: application/json');
    $signal = $_POST['ajax_power'];
    if (in_array($signal, ['start', 'stop', 'restart', 'kill'])) {
        $res = clientApiRequest($panel_url, $headers_client, "servers/$short_identifier/power", "POST", ["signal" => $signal]);
        echo json_encode(['success' => $res['code'] === 204]);
    }
    exit();
}

/*
|--------------------------------------------------------------------------
| TRAITEMENT AJAX : INTERACTION CONSOLE
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_command'])) {
    ini_set('display_errors', 0);
    header('Content-Type: application/json');
    $command = trim($_POST['ajax_command']);
    if (!empty($command)) {
        $res = clientApiRequest($panel_url, $headers_client, "servers/$short_identifier/command", "POST", ["command" => $command]);
        echo json_encode(['success' => $res['code'] === 204]);
    }
    exit();
}

// Rafraîchir session utilisateur
$u_stmt = $pdo->prepare("SELECT pseudo, firstname, avatar, is_admin FROM users WHERE id = ? LIMIT 1");
$u_stmt->execute([$_SESSION['user_id']]);
$user_data = $u_stmt->fetch();
if ($user_data) {
    $_SESSION['username'] = !empty($user_data['pseudo']) ? $user_data['pseudo'] : $user_data['firstname'];
    $_SESSION['avatar']   = $user_data['avatar'];
}
$is_admin = (bool)($user_data['is_admin'] ?? false);

// Tickets ouverts (pour badge sidebar)
$t_stmt = $pdo->prepare("SELECT COUNT(*) FROM support_tickets WHERE user_id=? AND status != 'Fermé'");
$t_stmt->execute([$_SESSION['user_id']]);
$open_tickets = (int)$t_stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OrinHeberge — Console — <?php echo htmlspecialchars($server['service_name']); ?></title>
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
        .glass{background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);border-radius:.875rem;}
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
        function toggleSidebar(){document.getElementById('sidebar').classList.toggle('open');document.getElementById('overlay').classList.toggle('open');}
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
            <i class="fas fa-shield-alt icon"></i> Admin Panel
        </a>
        <a href="/support/admin_tickets/" class="nav-item">
            <i class="fas fa-ticket-alt icon"></i> Tickets
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
                <a href="/client/servers/" class="text-xs text-sky-400 hover:underline flex items-center gap-1 mb-0.5">
                    <i class="fas fa-arrow-left text-[10px]"></i> Mes serveurs
                </a>
                <div class="text-sm font-bold text-white"><?php echo htmlspecialchars($server['service_name']); ?></div>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <span class="glass px-3 py-1.5 rounded-lg text-xs flex items-center gap-2" style="border-radius:.5rem;">
                <span id="statusBadge" class="h-2 w-2 rounded-full bg-gray-500"></span>
                <span id="statusText" class="font-bold text-gray-400">Calcul...</span>
            </span>
            <a href="/profil/" class="w-8 h-8 rounded-full overflow-hidden border border-white/10 flex items-center justify-center bg-sky-500/10 shrink-0">
                <?php if (!empty($_SESSION['avatar']) && file_exists($_SERVER['DOCUMENT_ROOT'].'/'.$_SESSION['avatar'])): ?>
                    <img src="/<?php echo htmlspecialchars($_SESSION['avatar']); ?>" class="w-full h-full object-cover">
                <?php else: ?>
                    <span class="text-sky-400 text-xs font-bold"><?php echo strtoupper(substr($_SESSION['username'] ?? 'U', 0, 1)); ?></span>
                <?php endif; ?>
            </a>
        </div>
    </div>

    <div class="content">
        <div class="mb-6">
            <h2 class="text-lg font-bold text-white">
                <?php echo ($service_type === "javascript") ? "Console Node.js" : "Console Minecraft"; ?>
            </h2>
            <p class="text-xs text-gray-500 mt-0.5">Serveur : <span class="text-sky-400 font-medium"><?php echo htmlspecialchars($server['service_name']); ?></span></p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
            <div class="lg:col-span-2 bg-black/60 border border-white/10 rounded-2xl p-4 font-mono text-sm shadow-2xl">
                <div class="flex justify-between items-center pb-3 border-b border-white/5 mb-4 text-xs text-gray-500">
                    <span><?php echo ($service_type === "javascript") ? "node@app:~$" : "mysql@server_logs:~#"; ?></span>
                    <button onclick="clearConsole()" class="hover:text-white transition"><i class="fas fa-trash-can"></i> Effacer l'écran</button>
                </div>
                <textarea id="consoleScreen" readonly class="w-full h-[450px] bg-transparent focus:outline-none resize-none font-mono text-xs md:text-sm leading-relaxed text-green-400" placeholder="Synchronisation avec le flux de données..."></textarea>
                <form id="consoleForm" class="mt-4 flex gap-2 border-t border-white/5 pt-4">
                    <span class="text-sky-400 self-center font-bold px-1">&gt;</span>
                    <input type="text" id="cmdInput" required autocomplete="off" placeholder="<?php echo ($service_type === 'javascript') ? 'Entrez une commande JS / application...' : 'Entrez une commande Minecraft...'; ?>" class="w-full bg-transparent text-white focus:outline-none font-mono text-sm py-1">
                    <button type="submit" class="bg-sky-600 hover:bg-sky-500 text-white px-4 py-1.5 rounded-xl text-xs font-bold transition"><i class="fas fa-paper-plane"></i></button>
                </form>
            </div>

            <div class="flex flex-col gap-4">
                <div class="glass p-4 rounded-2xl grid grid-cols-2 gap-2">
                    <button onclick="sendPowerAction('start')" class="bg-emerald-600/90 hover:bg-emerald-500 text-white font-bold text-xs py-2.5 rounded-xl transition active:scale-95"><i class="fas fa-play text-[10px] mr-1"></i> Démarrer</button>
                    <button onclick="sendPowerAction('restart')" class="bg-sky-600/90 hover:bg-sky-500 text-white font-bold text-xs py-2.5 rounded-xl transition active:scale-95"><i class="fas fa-rotate text-[10px] mr-1"></i> Relancer</button>
                    <button onclick="sendPowerAction('stop')" class="bg-orange-600/90 hover:bg-orange-500 text-white font-bold text-xs py-2.5 rounded-xl transition active:scale-95"><i class="fas fa-stop text-[10px] mr-1"></i> Arrêter</button>
                    <button onclick="sendPowerAction('kill')" class="bg-red-600/90 hover:bg-red-500 text-white font-bold text-xs py-2.5 rounded-xl transition active:scale-95"><i class="fas fa-skull text-[10px] mr-1"></i> Tuer</button>
                    <a href="/client/servers/gérer/websftp/?uuid=<?= urlencode($target_uuid) ?>&dir=/"
   class="text-xs bg-emerald-600/20 hover:bg-emerald-600 text-emerald-300 hover:text-white px-3 py-1 rounded-full flex items-center gap-2 transition">
    <i class="fas fa-code"></i> File Manager
</a>
                </div>

                <div class="flex flex-col gap-2.5">
                    <div id="blocPlayers" class="glass p-3.5 rounded-2xl flex justify-between items-center border border-sky-500/20">
                        <span class="text-xs font-bold text-sky-400"><i class="fas fa-users w-5"></i> Joueurs en ligne</span>
                        <span id="statPlayers" class="text-xs font-mono font-bold text-white">0 / --</span>
                    </div>
                    <div class="glass p-3.5 rounded-2xl flex justify-between items-center">
                        <span class="text-xs font-bold text-gray-400"><i class="fas fa-link text-sky-400 w-5"></i> Adresse IP</span>
                        <span id="statAddress" class="text-xs font-mono font-medium text-white truncate max-w-[180px]">Calcul...</span>
                    </div>
                    <div class="glass p-3.5 rounded-2xl flex justify-between items-center">
                        <span class="text-xs font-bold text-gray-400"><i class="fas fa-clock text-indigo-400 w-5"></i> Uptime</span>
                        <span id="statUptime" class="text-xs font-medium text-white">0s</span>
                    </div>
                    <div class="glass p-3.5 rounded-2xl flex justify-between items-center">
                        <span class="text-xs font-bold text-gray-400"><i class="fas fa-microchip text-emerald-400 w-5"></i> Charge CPU</span>
                        <span id="statCpu" class="text-xs font-mono font-medium text-white">0%</span>
                    </div>
                    <div class="glass p-3.5 rounded-2xl flex justify-between items-center">
                        <span class="text-xs font-bold text-gray-400"><i class="fas fa-memory text-purple-400 w-5"></i> RAM Allouée</span>
                        <span id="statRam" class="text-xs font-mono font-medium text-white">0 Mo / 0 Mo</span>
                    </div>
                    <div class="glass p-3.5 rounded-2xl flex justify-between items-center">
                        <span class="text-xs font-bold text-gray-400"><i class="fas fa-hard-drive text-amber-400 w-5"></i> Stockage</span>
                        <span id="statDisk" class="text-xs font-mono font-medium text-white">0 Mo / 0 Mo</span>
                    </div>
                </div>
            </div>
        </div>
    </div><!-- /grid -->
    </div><!-- /content -->
</div><!-- /main-content -->

    <script>
        const consoleScreen = document.getElementById('consoleScreen');
        const statusText = document.getElementById('statusText');
        const statusBadge = document.getElementById('statusBadge');
        const cmdInput = document.getElementById('cmdInput');
        let lastStatus = "";

        function formatUptime(seconds) {
            if (!seconds || seconds <= 0) return "Hors ligne";
            const d = Math.floor(seconds / (3600*24));
            const h = Math.floor((seconds % (3600*24)) / 3600);
            const m = Math.floor((seconds % 3600) / 60);
            const s = Math.floor(seconds % 60);
            return (d > 0 ? d + "j " : "") + (h > 0 ? h + "h " : "") + (m > 0 ? m + "m " : "") + s + "s";
        }

        function updateTerminal() {
            fetch(`${window.location.pathname}${window.location.search}&fetch_runtime=1`)
                .then(res => res.json())
                .then(data => {
                    if (data.status !== lastStatus) {
                        lastStatus = data.status;
                        statusText.innerText = data.status === "running" ? "En ligne" : (data.status === "offline" ? "Hors ligne" : "En cours...");
                        statusBadge.className = "h-2 w-2 rounded-full " + (data.status === "running" ? "bg-green-400 animate-pulse" : "bg-red-500");
                    }

                    if (data.stats) {
                        const s = data.stats;
                        document.getElementById('statAddress').innerText = s.address || 'Non définie';
                        document.getElementById('statUptime').innerText = formatUptime(s.uptime);
                        document.getElementById('statCpu').innerText = data.status === "offline" ? "0%" : s.cpu + "%";
                        document.getElementById('statPlayers').innerText = `${s.players_current} / ${s.players_max || '--'}`;

                        const ramMaxStr = s.ram_max === 0 ? "Illimité" : (s.ram_max >= 1024 ? (s.ram_max/1024).toFixed(1) + " Go" : s.ram_max + " Mo");
                        const ramUsedStr = s.ram_used >= 1024 ? (s.ram_used/1024).toFixed(1) + " Go" : s.ram_used + " Mo";
                        document.getElementById('statRam').innerText = data.status === "offline" ? "0 Mo" : `${ramUsedStr} / ${ramMaxStr}`;

                        const diskMaxStr = s.disk_max === 0 ? "Illimité" : (s.disk_max >= 1024 ? (s.disk_max/1024).toFixed(1) + " Go" : s.disk_max + " Mo");
                        document.getElementById('statDisk').innerText = `${s.disk_used} Mo / ${diskMaxStr}`;
                    }

                    if (data.logs && data.logs.length > 0) {
                        const formattedLogs = data.logs.join("\n") + "\n";
                        if (consoleScreen.value !== formattedLogs) {
                            const autoScroll = consoleScreen.scrollTop + consoleScreen.clientHeight >= consoleScreen.scrollHeight - 30;
                            consoleScreen.value = formattedLogs;
                            if (autoScroll) consoleScreen.scrollTop = consoleScreen.scrollHeight;
                        }
                    }
                });
        }

        function sendPowerAction(action) {
            consoleScreen.value += `[SYSTEM] > Envoi de l'ordre : ${action.toUpperCase()}...\n`;
            const formData = new FormData();
            formData.append('ajax_power', action);
            fetch(`${window.location.pathname}${window.location.search}`, { method: 'POST', body: formData });
        }

        document.getElementById('consoleForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const command = cmdInput.value.trim();
            if (!command) return;
            consoleScreen.value += `[EXEC] > ${command}\n`;
            cmdInput.value = '';
            const formData = new FormData();
            formData.append('ajax_command', command);
            fetch(`${window.location.pathname}${window.location.search}`, { method: 'POST', body: formData });
        });

        function clearConsole() { consoleScreen.value = ""; }

        updateTerminal();
        setInterval(updateTerminal, 2000);
    </script>
</body>
</html>
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

    if ($is_logged_in) {
        $stmt = $pdo->prepare("SELECT pseudo, firstname, avatar FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$_SESSION['user_id']]);
        $user_data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user_data) {
            // On s'assure que la session utilise les données fraîches pour la Navbar
            $_SESSION['username'] = !empty($user_data['pseudo']) ? $user_data['pseudo'] : $user_data['firstname'];
            $_SESSION['avatar']   = $user_data['avatar'];
        }
    }
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OrinHeberge | Console | <?php echo htmlspecialchars($server['service_name']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link class="rounded-full" rel="icon" type="image/png" href="https://heberge.orinstone.deepstone.fr/favicon.ico">
    <style>
        body { background: #0b0f19; scroll-behavior: smooth; }
        .glass { background: rgba(255, 255, 255, 0.04); backdrop-filter: blur(14px); border: 1px solid rgba(255, 255, 255, 0.08); }
        .gradient-text { background: linear-gradient(90deg, #38bdf8, #818cf8); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        #mobileMenu { display: none; }
        #mobileMenu.active { display: block; }
    </style>
	
	<link rel="manifest" href="/manifest.json">

<script>
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
      navigator.serviceWorker.register('/sw.js')
        .then(reg => console.log('Service Worker enregistré avec succès ! Scope:', reg.scope))
        .catch(err => console.log('Échec de l\'enregistrement du Service Worker:', err));
    });
  }
</script>
    <script>
        function toggleMenu(){ document.getElementById("mobileMenu").classList.toggle("active"); }
    </script>
</head>
<body class="text-gray-200 font-sans min-h-screen flex flex-col justify-between">

<nav class="sticky top-0 z-50 glass p-5 border-b border-white/5">
        <div class="max-w-7xl mx-auto flex justify-between items-center gap-4">
            
            <h1 class="text-3xl font-black gradient-text tracking-tight shrink-0">
                <a href="/">OrinHeberge</a>
            </h1>

            <div class="hidden md:flex items-center gap-3 whitespace-nowrap">
                <a href="/" class="bg-sky-600/20 hover:bg-sky-600 border border-sky-500/30 text-sky-400 hover:text-white px-4 py-2 rounded-full text-xs flex items-center gap-2 transition font-medium shadow-md shadow-sky-900/20">
                    <i class="fas fa-home"></i> Accueil
                </a>
                <a href="/client/servers/" class="bg-slate-600/20 hover:bg-slate-600 border border-slate-500/30 text-slate-400 hover:text-white px-4 py-2 rounded-full text-xs flex items-center gap-2 transition font-medium shadow-md shadow-slate-900/20">
                    <i class="fas fa-server"></i> Mes serveurs
                </a>
                <a href="/shop/" class="bg-amber-600/20 hover:bg-amber-600 border border-amber-500/30 text-amber-400 hover:text-white px-4 py-2 rounded-full text-xs flex items-center gap-2 transition font-medium shadow-md shadow-amber-900/20">
                    <i class="fas fa-tags"></i> Offres
                </a>
                <a href="/support/" class="bg-purple-600/20 hover:bg-purple-600 border border-purple-500/30 text-purple-400 hover:text-white px-4 py-2 rounded-full text-xs flex items-center gap-2 transition font-medium shadow-md shadow-purple-900/20">
                    <i class="fas fa-headset"></i> Support
                </a>

                <?php if(isset($_SESSION['user_id'])): ?>
                    <?php include $_SERVER['DOCUMENT_ROOT'] . '/inc/notifications.php'; ?>
                    
                    <a href="/profil/" class="text-gray-300 hover:text-sky-400 font-bold flex items-center gap-2.5 transition bg-white/5 hover:bg-white/10 px-4 py-2 rounded-full border border-white/5 focus:outline-none text-xs">
                        <?php if(!empty($_SESSION['avatar']) && file_exists($_SERVER['DOCUMENT_ROOT'] . '/' . $_SESSION['avatar'])): ?>
                            <img src="/<?php echo htmlspecialchars($_SESSION['avatar']); ?>" alt="Avatar" class="w-5 h-5 rounded-full object-cover border border-sky-500/30 shrink-0">
                        <?php else: ?>
                            <i class="fas fa-user-circle text-lg text-sky-400 shrink-0 flex items-center justify-center"></i>
                        <?php endif; ?>
                        <span class="block"><?php echo htmlspecialchars($_SESSION['username'] ?? 'Mon Profil'); ?></span>
                    </a>

                    <a href="/logout/" class="bg-red-600/10 hover:bg-red-600 border border-red-500/20 text-red-400 hover:text-white px-4 py-2 rounded-full text-xs flex items-center gap-2 transition font-medium">
                        <i class="fas fa-sign-out-alt"></i> Déconnexion
                    </a>
                <?php else: ?>
                    <a href="/login/" class="bg-slate-600/20 hover:bg-slate-600 border border-slate-500/30 text-slate-400 hover:text-white px-4 py-2 rounded-full text-xs flex items-center gap-2 transition font-medium">
                        <i class="fas fa-sign-in-alt"></i> Connexion
                    </a>
                    <a href="/register/" class="bg-slate-600/20 hover:bg-slate-600 border border-slate-500/30 text-slate-400 hover:text-white px-4 py-2 rounded-full text-xs flex items-center gap-2 transition font-medium">
                        <i class="fas fa-user-plus"></i> Inscription
                    </a>
                <?php endif; ?>
            </div>

            <div class="hidden lg:flex gap-2.5 items-center shrink-0">
                <?php if(isset($_SESSION['is_admin']) && $_SESSION['is_admin']): ?>
                    <a href="/support/admin_tickets/" class="bg-rose-600/20 hover:bg-rose-600 border border-rose-500/30 text-rose-400 hover:text-white px-4 py-2 rounded-full text-xs flex items-center gap-2 transition font-medium shadow-md shadow-rose-900/20 whitespace-nowrap">
                        <i class="fas fa-unlock-keyhole"></i> Gérer les tickets (Admin)
                    </a>
                <?php endif; ?>

                <a href="/status/" class="bg-emerald-600/20 hover:bg-emerald-600 border border-emerald-500/30 text-emerald-400 hover:text-white px-4 py-2 rounded-full text-xs flex items-center gap-2 transition font-medium shadow-md shadow-emerald-900/20 whitespace-nowrap">
                    <i class="fas fa-signal"></i> Statut
                </a>

                <a href="https://php.orinstone.deepstone.fr" class="glass text-gray-300 hover:text-white hover:bg-white/10 px-4 py-2 rounded-full text-xs flex items-center gap-2 transition font-medium border border-white/5 whitespace-nowrap">
                    <i class="fas fa-database text-sky-400"></i> phpMyAdmin
                </a>
                
                <a href="https://panel.orinstone.deepstone.fr" class="bg-sky-600 hover:bg-sky-500 px-4 py-2 rounded-full text-xs flex items-center gap-2 transition font-medium shadow-md shadow-sky-900/20 whitespace-nowrap text-white">
                    <i class="fas fa-cogs"></i> Panel
                </a>

                <div class="relative inline-block text-left group">
                    <button type="button" class="inline-flex items-center gap-2 bg-white/5 border border-white/10 hover:border-sky-500/50 rounded-full px-3 py-1.5 text-xs font-semibold text-gray-200 transition focus:outline-none">
                        <img src="https://flagcdn.com/w20/fr.png" id="current-flag" alt="Français" class="w-4 h-auto rounded-sm object-contain">
                        <span id="current-lang-text">FR</span>
                        <i class="fas fa-chevron-down text-[10px] text-gray-400 group-hover:text-sky-400 transition duration-200"></i>
                    </button>
                    <div class="absolute right-0 mt-2 w-36 rounded-xl glass border border-white/10 shadow-xl opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-50 overflow-hidden">
                        <div class="py-1">
                            <a href="?lang=fr" onclick="changeLanguage('fr', 'FR', 'https://flagcdn.com/w20/fr.png', event)" class="flex items-center gap-3 px-4 py-2 text-xs text-gray-300 hover:bg-sky-600/20 hover:text-white transition">
                                <img src="https://flagcdn.com/w20/fr.png" alt="Français" class="w-4 h-auto rounded-sm">
                                <span>Français</span>
                            </a>
                            <a href="?lang=en" onclick="changeLanguage('en', 'EN', 'https://flagcdn.com/w20/gb.png', event)" class="flex items-center gap-3 px-4 py-2 text-xs text-gray-300 hover:bg-sky-600/20 hover:text-white transition">
                                <img src="https://flagcdn.com/w20/gb.png" alt="English" class="w-4 h-auto rounded-sm">
                                <span>English</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <button onclick="toggleMenu()" class="md:hidden text-2xl text-gray-400 hover:text-white transition shrink-0">
                <i class="fas fa-bars"></i>
            </button>
        </div>

        <div id="mobileMenu" class="md:hidden mt-4 px-4 space-y-3 glass rounded-2xl p-4 hidden">
            <a href="/" class="bg-sky-600/20 border border-sky-500/30 text-sky-400 block py-2 px-4 rounded-xl flex items-center gap-2 text-sm font-medium"><i class="fas fa-home w-5 text-center"></i> Accueil</a>
            <a href="/client/servers/" class="bg-white/[0.02] border border-white/5 text-gray-300 block py-2 px-4 rounded-xl flex items-center gap-2 text-sm font-medium"><i class="fas fa-server w-5 text-center"></i> Mes serveurs</a>
            <a href="/shop/" class="bg-white/[0.02] border border-white/5 text-gray-300 block py-2 px-4 rounded-xl flex items-center gap-2 text-sm font-medium"><i class="fas fa-tags w-5 text-center"></i> Offres</a>
            <a href="/support/" class="bg-white/[0.02] border border-white/5 text-gray-300 block py-2 px-4 rounded-xl flex items-center gap-2 text-sm font-medium"><i class="fas fa-headset w-5 text-center"></i> Support</a>
            <a href="/status/" class="bg-emerald-600/20 border border-emerald-500/30 text-emerald-400 block py-2 px-4 rounded-xl flex items-center gap-2 text-sm font-medium"><i class="fas fa-signal w-5 text-center"></i> Statut</a>
            
            <?php if(isset($_SESSION['is_admin']) && $_SESSION['is_admin']): ?>
                <a href="/support/admin_tickets/" class="bg-rose-600/20 border border-rose-500/30 text-rose-400 block py-2 px-4 rounded-xl flex items-center gap-2 text-sm font-semibold"><i class="fas fa-unlock-keyhole w-5 text-center"></i> Gérer les tickets</a>
            <?php endif; ?>

            <hr class="border-white/10">

            <?php if(isset($_SESSION['user_id'])): ?>
                <a href="/profil/" class="bg-white/5 text-gray-200 block py-2 px-4 rounded-xl flex items-center gap-2.5 text-sm font-bold border border-white/5">
                    <?php if(!empty($_SESSION['avatar']) && file_exists($_SERVER['DOCUMENT_ROOT'] . '/' . $_SESSION['avatar'])): ?>
                        <img src="/<?php echo htmlspecialchars($_SESSION['avatar']); ?>" alt="Avatar" class="w-5 h-5 rounded-full object-cover border border-sky-500/30 shrink-0">
                    <?php else: ?>
                        <i class="fas fa-user-circle text-lg text-sky-400 shrink-0"></i>
                    <?php endif; ?>
                    <span><?php echo htmlspecialchars($_SESSION['username'] ?? 'Mon Profil'); ?></span>
                </a>
                <a href="/logout/" class="bg-red-600/10 border border-red-500/20 text-red-400 block py-2 px-4 rounded-xl flex items-center gap-2 text-sm font-medium">
                    <i class="fas fa-sign-out-alt w-5 text-center"></i> Déconnexion
                </a>
            <?php else: ?>
                <a href="/login/" class="bg-white/5 text-gray-300 block py-2 px-4 rounded-xl flex items-center gap-2 text-sm font-medium border border-white/5"><i class="fas fa-sign-in-alt w-5 text-center"></i> Connexion</a>
                <a href="/register/" class="bg-white/5 text-gray-300 block py-2 px-4 rounded-xl flex items-center gap-2 text-sm font-medium border border-white/5"><i class="fas fa-user-plus w-5 text-center"></i> Inscription</a>
            <?php endif; ?>

            <hr class="border-white/10">

            <div class="grid grid-cols-2 gap-2 pt-1">
                <a href="https://php.orinstone.deepstone.fr" class="glass text-gray-300 px-4 py-2.5 rounded-xl text-xs flex items-center gap-2 justify-center border border-white/5 font-medium">
                    <i class="fas fa-database text-sky-400"></i> phpMyAdmin
                </a>
                <a href="https://panel.orinstone.deepstone.fr" class="bg-sky-600 text-white px-4 py-2.5 rounded-xl text-xs flex items-center gap-2 justify-center font-medium">
                    <i class="fas fa-cogs"></i> Panel
                </a>
            </div>

            <div class="relative inline-block text-left group w-full pt-1">
                <button type="button" class="inline-flex items-center justify-between w-full gap-2 bg-white/5 border border-white/10 rounded-xl px-4 py-2.5 text-sm font-semibold text-gray-200 transition focus:outline-none">
                    <div class="flex items-center gap-2">
                        <img src="https://flagcdn.com/w20/fr.png" alt="Français" class="w-5 h-auto rounded-sm object-contain">
                        <span>FR</span>
                    </div>
                    <i class="fas fa-chevron-down text-xs text-gray-400 group-hover:text-sky-400 transition duration-200"></i>
                </button>
                <div class="absolute right-0 mt-2 w-full rounded-xl glass border border-white/10 shadow-xl opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-50 overflow-hidden">
                    <div class="py-1">
                        <a href="?lang=fr" onclick="changeLanguage('fr', 'FR', 'https://flagcdn.com/w20/fr.png', event)" class="flex items-center gap-3 px-4 py-2.5 text-sm text-gray-300 hover:bg-sky-600/20 hover:text-white transition">
                            <img src="https://flagcdn.com/w20/fr.png" alt="Français" class="w-5 h-auto rounded-sm">
                            <span>Français</span>
                        </a>
                        <a href="?lang=en" onclick="changeLanguage('en', 'EN', 'https://flagcdn.com/w20/gb.png', event)" class="flex items-center gap-3 px-4 py-2.5 text-sm text-gray-300 hover:bg-sky-600/20 hover:text-white transition">
                            <img src="https://flagcdn.com/w20/gb.png" alt="English" class="w-5 h-auto rounded-sm">
                            <span>English</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto w-full px-4 lg:px-6 mb-auto">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
            <div>
                <a href="/client/servers/" class="text-xs text-sky-400 hover:underline flex items-center gap-2 mb-1">
                    <i class="fas fa-arrow-left"></i> Retour à la liste
                </a>
                <h2 class="text-2xl font-black tracking-tight text-white">
                    <?php echo ($service_type === "javascript") ? "🖥️ Console Application Node.js" : "⛏️ Gestionnaire Minecraft Live SQL"; ?>
                </h2>
                <p class="text-gray-400 text-xs mt-0.5">Serveur : <span class="text-sky-400 font-medium"><?php echo htmlspecialchars($server['service_name']); ?></span></p>
            </div>
            <div>
                <span class="glass px-4 py-1.5 rounded-full text-xs text-gray-300 flex items-center gap-2">
                    <span id="statusBadge" class="h-2 w-2 rounded-full bg-gray-500"></span> 
                    <span id="statusText" class="font-bold text-gray-400">Calcul...</span>
                </span>
            </div>
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
    </div>
        <div class="fixed bottom-6 right-6 z-50">
        <a href="https://heberge.orinstone.deepstone.fr/discord/" target="_blank" class="bg-[#5865F2] hover:bg-[#4752C4] transition text-white px-5 py-3.5 rounded-full font-bold flex items-center gap-2 shadow-2xl hover:scale-105 transform duration-200">
            <i class="fab fa-discord text-xl"></i>
            <span class="hidden sm:inline text-sm">Besoin d'aide ? Discord</span>
        </a>
    </div>

<footer class="w-full bg-[#05070d] text-gray-400 py-12 px-6 border-t border-white/5 font-sans">
    <div class="max-w-7xl mx-auto">
        
        <div class="grid grid-cols-1 md:grid-cols-4 gap-8 mb-10">
            
            <div class="flex flex-col gap-4">
                <h3 class="text-white font-bold text-base tracking-wide">Navigation</h3>
                <div class="flex flex-col gap-2.5 text-sm">
                    <a href="/" class="hover:text-sky-400 transition">Accueil</a>
                    <a href="/client/servers/" class="hover:text-sky-400 transition">Mes serveurs</a>
                    <a href="/shop/" class="hover:text-sky-400 transition">Offres</a>
                    <a href="/support/" class="hover:text-sky-400 transition">Support</a>
                </div>
            </div>

            <div class="flex flex-col gap-4">
                <h3 class="text-white font-bold text-base tracking-wide">Notre Réseau</h3>
                <div class="flex flex-col gap-2.5 text-sm">
                    <a href="/discord/" class="hover:text-sky-400 transition">Notre Discord</a>
                    <a href="https://status.deepstone.fr/" class="hover:text-sky-400 transition">Statut des Services</a>
                </div>
            </div>

            <div class="flex flex-col gap-4">
                <h3 class="text-white font-bold text-base tracking-wide">Liens Utiles</h3>
                <div class="flex flex-col gap-2.5 text-sm">
                    <a href="https://php.orinstone.deepstone.fr" class="hover:text-sky-400 transition">phpMyAdmin</a>
                    <a href="https://panel.orinstone.deepstone.fr" class="hover:text-sky-400 transition">Panel</a>
                </div>
            </div>

            <div class="flex flex-col justify-end gap-3 items-start md:items-end">
                <span class="text-xs text-gray-400 font-semibold tracking-wider uppercase">Moyens de Paiements Acceptés</span>
                <div class="flex items-center gap-3 bg-white/[0.02] border border-white/5 p-3 rounded-xl">
                    <img src="https://azurhosts.com/assets/images/logos/psrl/card-icons/card_cb.svg" alt="CB" class="h-8 object-contain" />
                    <img src="https://azurhosts.com/assets/images/logos/psrl/card-icons/card_visa.svg" alt="Visa" class="h-8 object-contain" />
                    <img src="https://azurhosts.com/assets/images/logos/psrl/card-icons/card_mastercard.svg" alt="Mastercard" class="h-8 object-contain" />
                    <img src="https://azurhosts.com/assets/images/logos/psrl/card-icons/card_paypal.svg" alt="PayPal" class="h-8 object-contain" />
                </div>
            </div>

        </div>

        <hr class="border-white/10 mb-8">

     <div class="flex flex-col md:flex-row items-start justify-between gap-6 text-xs text-gray-500">
            
            <div class="flex items-center gap-2">
                <span class="text-2xl font-black tracking-tighter text-white">Orin<span class="text-sky-500">Heberge</span></span>
            </div>

            <div class="flex flex-col gap-2 md:text-left">
                <div class="flex flex-wrap gap-x-4 gap-y-1 text-gray-400 font-medium">
                    <a href="/mentions-legales/" class="hover:text-sky-400 transition">Mentions Légales</a>
                    <span class="text-white/10">|</span>
                    <a href="/cgu/" class="hover:text-sky-400 transition">Conditions Générales d'Utilisation</a>
                    <span class="text-white/10">|</span>
                    <a href="/politique-confidentialite/" class="hover:text-sky-400 transition">Politique de Confidentialité</a>
                </div>
                <div class="flex flex-col gap-0.5">
                    <div>© 2026-2029 OrinHeberge — Infrastructure OrinStone. Tous droits réservés.</div>
                    <div class="text-[10px] text-gray-600 mt-1">
                        Propulsé par <span class="text-sky-500/70 font-medium hover:text-sky-400 transition">Orinstone Studio</span>
                    </div>
                </div>
            </div>

        </div>
    </div>
</footer>

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
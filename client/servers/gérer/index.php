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
| PROXY WEBSOCKET TOKEN (Correction : utilise l'UUID complet)
|--------------------------------------------------------------------------
*/
if (isset($_GET['get_ws_token'])) {
    header('Content-Type: application/json');
    
    // IMPORTANT : Utiliser l'UUID COMPLET ($target_uuid) pour l'endpoint websocket
    $endpoint = "servers/$target_uuid/websocket";
    $wsResponse = clientApiRequest($panel_url, $headers_client, $endpoint);
    
    if ($wsResponse['code'] !== 200) {
        echo json_encode([
            'error' => true, 
            'message' => 'Erreur API Pterodactyl', 
            'code' => $wsResponse['code'],
            'response' => $wsResponse['data']
        ]);
        exit();
    }

    // Gestion de la structure de réponse imbriquée de Pterodactyl
    $payload = $wsResponse['data'];
    if (isset($payload['data']['data'])) {
        echo json_encode($payload['data']['data']);
    } elseif (isset($payload['data'])) {
        echo json_encode($payload['data']);
    } else {
        echo json_encode($payload);
    }
    exit();
}

/*
|--------------------------------------------------------------------------
| TRAITEMENT AJAX : BOUTONS POWER (REST API)
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_power'])) {
    header('Content-Type: application/json');
    $signal = $_POST['ajax_power'];
    if (in_array($signal, ['start', 'stop', 'restart', 'kill'])) {
        $res = clientApiRequest($panel_url, $headers_client, "servers/$short_identifier/power", "POST", ["signal" => $signal]);
        echo json_encode(['success' => $res['code'] === 204]);
    }
    exit();
}

// Récupération des détails du serveur pour les limites et l'IP (chargement initial)
$details = clientApiRequest($panel_url, $headers_client, "servers/$short_identifier");
$ram_max = 0;
$disk_max = 0;
$server_address = 'Non définie';
$players_max = 0;

if (isset($details['data']['attributes'])) {
    $attr = $details['data']['attributes'];
    $ram_max = $attr['limits']['memory'] ?? 0;
    $disk_max = $attr['limits']['disk'] ?? 0;
    
    if (isset($attr['players_max'])) {
        $players_max = intval($attr['players_max']);
    } elseif (isset($attr['blueprint_data']['players']['max'])) {
        $players_max = intval($attr['blueprint_data']['players']['max']);
    }

    if (isset($attr['relationships']['allocations']['data'])) {
        foreach ($attr['relationships']['allocations']['data'] as $alloc) {
            if ($alloc['attributes']['is_default'] ?? false) {
                $server_ip = $alloc['attributes']['ip_alias'] ?? $alloc['attributes']['ip'];
                $server_port = intval($alloc['attributes']['port']);
                $server_address = $server_ip . ":" . $server_port;
                break;
            }
        }
    }
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

include $_SERVER['DOCUMENT_ROOT'] . '/inc/clients_sidebar.php';
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
        .main-content{margin-left:var(--sidebar);min-height:100vh;display:flex;flex-direction:column;}
        .topbar{background:#111318;border-bottom:1px solid rgba(255,255,255,.06);padding:.875rem 1.75rem;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:30;}
        .content{padding:1.75rem;flex:1;}
        .glass{background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);border-radius:.875rem;}
        .mobile-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:39;}
        
        /* Style spécifique pour la console */
        .console-output {
            font-family: 'Courier New', Courier, monospace;
            white-space: pre-wrap;
            word-wrap: break-word;
            scrollbar-width: thin;
            scrollbar-color: #38bdf8 #161a22;
        }
        .console-output::-webkit-scrollbar { width: 8px; }
        .console-output::-webkit-scrollbar-track { background: #161a22; }
        .console-output::-webkit-scrollbar-thumb { background: #38bdf8; border-radius: 4px; }

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

<!-- ══ MAIN ══ -->
<div class="main-content">

    <!-- Topbar -->
    <div class="topbar">
        <div class="flex items-center gap-3">
            <button id="sidebar-toggle" class="md:hidden text-gray-400 hover:text-white text-lg w-8" aria-label="Ouvrir le menu">
                <i class="fas fa-bars" id="sidebar-toggle-icon"></i>
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
                <span id="statusText" class="font-bold text-gray-400">Connexion...</span>
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
                <?php echo ($service_type === "javascript") ? "Console Node.js" : "Console " . ucfirst($service_type); ?>
            </h2>
            <p class="text-xs text-gray-500 mt-0.5">Serveur : <span class="text-sky-400 font-medium"><?php echo htmlspecialchars($server['service_name']); ?></span></p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
            <!-- ZONE CONSOLE -->
            <div class="lg:col-span-2 bg-black/60 border border-white/10 rounded-2xl p-4 shadow-2xl flex flex-col">
                <div class="flex justify-between items-center pb-3 border-b border-white/5 mb-4 text-xs text-gray-500">
                    <span><?php echo ($service_type === "javascript") ? "node@app:~$" : "user@server:~#"; ?></span>
                    <button onclick="clearConsole()" class="hover:text-white transition"><i class="fas fa-trash-can"></i> Effacer l'écran</button>
                </div>
                
                <!-- Div optimisé pour les logs en temps réel -->
                <div id="consoleScreen" class="console-output w-full h-[450px] bg-transparent focus:outline-none resize-none text-xs md:text-sm leading-relaxed text-green-400 overflow-y-auto mb-4 p-2 rounded">
                    <div class="text-gray-500">[SYSTÈME] Initialisation de la connexion WebSocket...</div>
                </div>
                
                <form id="consoleForm" class="flex gap-2 border-t border-white/5 pt-4">
                    <span class="text-sky-400 self-center font-bold px-1">&gt;</span>
                    <input type="text" id="cmdInput" required autocomplete="off" placeholder="<?php echo ($service_type === 'javascript') ? 'Entrez une commande JS...' : 'Entrez une commande...'; ?>" class="w-full bg-transparent text-white focus:outline-none font-mono text-sm py-1">
                    <button type="submit" class="bg-sky-600 hover:bg-sky-500 text-white px-4 py-1.5 rounded-xl text-xs font-bold transition"><i class="fas fa-paper-plane"></i></button>
                </form>
            </div>

            <!-- ZONE STATS & CONTRÔLES -->
            <div class="flex flex-col gap-4">
                <div class="glass p-4 rounded-2xl grid grid-cols-2 gap-2">
                    <button onclick="sendPowerAction('start')" class="bg-emerald-600/90 hover:bg-emerald-500 text-white font-bold text-xs py-2.5 rounded-xl transition active:scale-95"><i class="fas fa-play text-[10px] mr-1"></i> Démarrer</button>
                    <button onclick="sendPowerAction('restart')" class="bg-sky-600/90 hover:bg-sky-500 text-white font-bold text-xs py-2.5 rounded-xl transition active:scale-95"><i class="fas fa-rotate text-[10px] mr-1"></i> Relancer</button>
                    <button onclick="sendPowerAction('stop')" class="bg-orange-600/90 hover:bg-orange-500 text-white font-bold text-xs py-2.5 rounded-xl transition active:scale-95"><i class="fas fa-stop text-[10px] mr-1"></i> Arrêter</button>
                    <button onclick="sendPowerAction('kill')" class="bg-red-600/90 hover:bg-red-500 text-white font-bold text-xs py-2.5 rounded-xl transition active:scale-95"><i class="fas fa-skull text-[10px] mr-1"></i> Tuer</button>
                    <a href="/client/servers/gérer/websftp/?uuid=<?= urlencode($target_uuid) ?>&dir=/" class="col-span-2 text-xs bg-emerald-600/20 hover:bg-emerald-600 text-emerald-300 hover:text-white px-3 py-2 rounded-xl flex items-center justify-center gap-2 transition mt-1">
                        <i class="fas fa-code"></i> Gestionnaire de fichiers
                    </a>
                </div>

                <div class="flex flex-col gap-2.5">
                    <div class="glass p-3.5 rounded-2xl flex justify-between items-center border border-sky-500/20">
                        <span class="text-xs font-bold text-sky-400"><i class="fas fa-users w-5"></i> Joueurs en ligne</span>
                        <span id="statPlayers" class="text-xs font-mono font-bold text-white">0 / --</span>
                    </div>
                    <div class="glass p-3.5 rounded-2xl flex justify-between items-center">
                        <span class="text-xs font-bold text-gray-400"><i class="fas fa-link text-sky-400 w-5"></i> Adresse IP</span>
                        <span id="statAddress" class="text-xs font-mono font-medium text-white truncate max-w-[180px]">Chargement...</span>
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
                        <span class="text-xs font-bold text-gray-400"><i class="fas fa-memory text-purple-400 w-5"></i> RAM</span>
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
</div>

<script>
    const consoleScreen = document.getElementById('consoleScreen');
    const statusText = document.getElementById('statusText');
    const statusBadge = document.getElementById('statusBadge');
    const cmdInput = document.getElementById('cmdInput');
    
    let socket = null;
    
    // Données initiales injectées par PHP
    const initialRamMax = <?php echo $ram_max; ?>;
    const initialDiskMax = <?php echo $disk_max; ?>;
    const initialAddress = "<?php echo addslashes($server_address); ?>";
    const initialPlayersMax = <?php echo $players_max; ?>;

    function formatUptime(seconds) {
        if (!seconds || seconds <= 0) return "Hors ligne";
        const d = Math.floor(seconds / (3600*24));
        const h = Math.floor((seconds % (3600*24)) / 3600);
        const m = Math.floor((seconds % 3600) / 60);
        return (d > 0 ? d + "j " : "") + (h > 0 ? h + "h " : "") + (m > 0 ? m + "m " : "") + Math.floor(seconds % 60) + "s";
    }

    function stripAnsi(str) {
        if (!str) return '';
        return str.replace(/[\u001b\u009b][[()#;?]*(?:[0-9]{1,4}(?:;[0-9]{0,4})*)?[0-9A-ORZcf-nqry=><]/g, '');
    }

    function appendLog(text) {
        const cleanText = stripAnsi(text);
        const div = document.createElement('div');
        div.textContent = cleanText;
        consoleScreen.appendChild(div);
        
        // Limiter à 300 lignes pour éviter de saturer la mémoire du navigateur
        if (consoleScreen.children.length > 300) {
            consoleScreen.removeChild(consoleScreen.firstChild);
        }
        // Auto-scroll vers le bas
        consoleScreen.scrollTop = consoleScreen.scrollHeight;
    }

    function updateStatusUI(status) {
        const statusMap = {
            'running': { text: 'En ligne', color: 'bg-green-400 animate-pulse' },
            'offline': { text: 'Hors ligne', color: 'bg-red-500' },
            'starting': { text: 'Démarrage...', color: 'bg-yellow-400 animate-pulse' },
            'stopping': { text: 'Arrêt...', color: 'bg-orange-400 animate-pulse' },
            'connecting': { text: 'Connexion...', color: 'bg-blue-400 animate-pulse' }
        };
        const info = statusMap[status] || { text: status, color: 'bg-gray-500' };
        statusText.innerText = info.text;
        statusBadge.className = `h-2 w-2 rounded-full ${info.color}`;
    }

    async function initSocket() {
        appendLog("[SYSTÈME] Récupération des identifiants de connexion...");
        
        try {
            const urlParams = new URLSearchParams(window.location.search);
            const uuid = urlParams.get('uuid');
            
            const tokenRes = await fetch(`?uuid=${uuid}&get_ws_token=1`);
            const tokenData = await tokenRes.json();
            
            if (tokenData.error) {
                appendLog(`[ERREUR] ${tokenData.message}`);
                if(tokenData.response) {
                    appendLog(`[DÉTAIL] ${JSON.stringify(tokenData.response)}`);
                }
                updateStatusUI('offline');
                return;
            }
            
            if (!tokenData || !tokenData.token || !tokenData.socket) {
                appendLog("[ERREUR] Réponse API invalide (Token ou Socket manquant).");
                console.log("Réponse brute:", tokenData);
                updateStatusUI('offline');
                return;
            }

            const wsUrl = tokenData.socket;
            const token = tokenData.token;

            appendLog("[SYSTÈME] Connexion au démon Docker en temps réel...");
            socket = new WebSocket(wsUrl);

            socket.onopen = function() {
                appendLog("[SYSTÈME] Connecté ! Authentification en cours...");
                updateStatusUI('connecting');
                
                socket.send(JSON.stringify({
                    "event": "auth",
                    "args": [token]
                }));
            };

            socket.onmessage = function(msg) {
                const data = JSON.parse(msg.data);
                
                if (data.event === 'console output') {
                    appendLog(data.args[0]);
                } else if (data.event === 'status') {
                    updateStatusUI(data.args[0]);
                } else if (data.event === 'stats') {
                    const stats = data.args[0];
                    if(stats) {
                        document.getElementById('statCpu').innerText = stats.cpu_absolute.toFixed(1) + "%";
                        
                        const ramUsed = (stats.memory_bytes / 1024 / 1024).toFixed(1);
                        const ramMaxStr = initialRamMax >= 1024 ? (initialRamMax/1024).toFixed(1) + " Go" : initialRamMax + " Mo";
                        const ramUsedStr = ramUsed >= 1024 ? (ramUsed/1024).toFixed(1) + " Go" : ramUsed + " Mo";
                        document.getElementById('statRam').innerText = `${ramUsedStr} / ${ramMaxStr}`;
                        
                        document.getElementById('statUptime').innerText = formatUptime(stats.uptime || 0);
                    }
                } else if (data.event === 'daemon error') {
                    appendLog(`[ERREUR DÉMON] ${data.args[0]}`);
                }
            };

            socket.onclose = function() {
                appendLog("[SYSTÈME] Déconnecté. Tentative de reconnexion dans 5 secondes...");
                updateStatusUI('offline');
                setTimeout(initSocket, 5000);
            };

            socket.onerror = function(err) {
                console.error("WebSocket Error:", err);
                appendLog("[ERREUR] Problème de connexion WebSocket.");
            };

        } catch (e) {
            appendLog("[ERREUR JS] " + e.message);
            setTimeout(initSocket, 5000);
        }
    }

    function sendCommand(cmd) {
        if (socket && socket.readyState === WebSocket.OPEN) {
            socket.send(JSON.stringify({
                "event": "send command",
                "args": [cmd]
            }));
        } else {
            appendLog("[ERREUR] Console non connectée. Impossible d'envoyer la commande.");
        }
    }

    function sendPowerAction(action) {
        appendLog(`[SYSTÈME] > Envoi de l'ordre : ${action.toUpperCase()}...`);
        const formData = new FormData();
        formData.append('ajax_power', action);
        fetch(window.location.pathname + window.location.search, { method: 'POST', body: formData })
        .then(r => r.json())
        .then(d => {
            if(d.success) {
                appendLog(`[SYSTÈME] Ordre ${action} accepté par le panneau.`);
            } else {
                appendLog(`[ERREUR] Échec de l'ordre ${action}.`);
            }
        });
    }

    function clearConsole() { 
        consoleScreen.innerHTML = '<div class="text-gray-500">[SYSTÈME] Console effacée.</div>'; 
    }

    // Gestion du formulaire d'envoi de commande
    document.getElementById('consoleForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const command = cmdInput.value.trim();
        if (!command) return;
        
        appendLog(`> ${command}`);
        sendCommand(command);
        cmdInput.value = '';
    });

    // Chargement initial des données statiques (Limites & IP)
    function loadInitialData() {
        document.getElementById('statAddress').innerText = initialAddress;
        
        const ramMaxStr = initialRamMax >= 1024 ? (initialRamMax/1024).toFixed(1) + " Go" : initialRamMax + " Mo";
        document.getElementById('statRam').innerText = `0 Mo / ${ramMaxStr}`;
        
        const diskMaxStr = initialDiskMax >= 1024 ? (initialDiskMax/1024).toFixed(1) + " Go" : initialDiskMax + " Mo";
        document.getElementById('statDisk').innerText = `0 Mo / ${diskMaxStr}`;
        
        document.getElementById('statPlayers').innerText = `0 / ${initialPlayersMax || '--'}`;
    }

    // Démarrage
    loadInitialData();
    initSocket();
</script>
</body>
</html>
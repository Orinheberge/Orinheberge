<?php
// client/console/index.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../../../api/config.php';

// Redirection si non connecté
if (!isset($_SESSION['user'])) {
    header('Location: /login/');
    exit;
}

$userId = (int)$_SESSION['user']['id'];
$message = '';
$error = '';

// 1. Récupérer les serveurs de l'utilisateur
$servers = [];
try {
    $stmt = $pdo->prepare("
        SELECT id, product_name, order_number, pterodactyl_identifier, status 
        FROM orders 
        WHERE user_id = ? AND pterodactyl_identifier IS NOT NULL AND pterodactyl_identifier != ''
        ORDER BY id DESC
    ");
    $stmt->execute([$userId]);
    $servers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Erreur BDD : " . $e->getMessage();
}

// 2. Déterminer le serveur actif (sélectionné par paramètre GET ou le 1er de la liste)
$selectedIdentifier = $_GET['identifier'] ?? ($servers[0]['pterodactyl_identifier'] ?? null);
$currentServer = null;

foreach ($servers as $srv) {
    if ($srv['pterodactyl_identifier'] === $selectedIdentifier) {
        $currentServer = $srv;
        break;
    }
}

// 3. Traitement des actions Power (Start, Stop, Restart, Kill) via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_power']) && $currentServer) {
    $signal = trim($_POST['action_power']);
    $allowedSignals = ['start', 'stop', 'restart', 'kill'];

    if (in_array($signal, $allowedSignals)) {
        $res = callPterodactylClientAPI('servers/' . $currentServer['pterodactyl_identifier'] . '/power', 'POST', [
            'signal' => $signal
        ]);

        if (isset($res['code']) && $res['code'] === 204) {
            $message = "Action <strong>" . strtoupper($signal) . "</strong> envoyée avec succès au serveur !";
        } else {
            $error = "Échec de l'action power. Code HTTP : " . ($res['code'] ?? 'Inconnu');
        }
    }
}

// 4. Récupération des détails du serveur (limites RAM/Disk, adresse, joueurs max) pour le panneau de stats
$ram_max = 0;
$disk_max = 0;
$players_max = 0;
$server_address = 'Non définie';

if ($currentServer) {
    $detailsRes = callPterodactylClientAPI('servers/' . $currentServer['pterodactyl_identifier']);
    if (isset($detailsRes['code']) && $detailsRes['code'] === 200 && isset($detailsRes['data']['attributes'])) {
        $attr = $detailsRes['data']['attributes'];
        $ram_max  = $attr['limits']['memory'] ?? 0;
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
}

// 5. Récupération des accès WebSocket pour la console du serveur sélectionné
$wsData = null;
if ($currentServer) {
    $wsRes = callPterodactylClientAPI('servers/' . $currentServer['pterodactyl_identifier'] . '/websocket');
    if (isset($wsRes['code']) && $wsRes['code'] === 200 && isset($wsRes['data']['data'])) {
        $wsData = $wsRes['data']['data'];
    }
}
?>
<!DOCTYPE html>
<html lang="fr" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Console & Gestion - <?= defined('SITE_NAME') ? htmlspecialchars(SITE_NAME) : 'Wixy' ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Bibliothèque pour parser les couleurs et séquences ANSI -->
    <script src="https://cdn.jsdelivr.net/npm/ansi_up@5.1.0/ansi_up.min.js"></script>
    
    <style>
        /* Style personnalisé pour le Scrollbar de la console */
        #console-logs::-webkit-scrollbar {
            width: 8px;
        }

        #console-logs::-webkit-scrollbar-track {
            background: #020617; /* Slate 950 */
            border-left: 1px solid #1e293b; /* Slate 800 */
        }

        #console-logs::-webkit-scrollbar-thumb {
            background: #334155; /* Slate 700 */
            border-radius: 9999px;
        }

        #console-logs::-webkit-scrollbar-thumb:hover {
            background: #8b5cf6; /* Violet 500 */
        }

        /* Support Firefox */
        #console-logs {
            scrollbar-width: thin;
            scrollbar-color: #334155 #020617;
        }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex flex-col font-sans">

    <!-- Header -->
    <header class="bg-slate-900 border-b border-slate-800">
        <div class="max-w-7xl mx-auto px-4 h-16 flex items-center justify-between">
            <a href="/" class="text-xl font-bold text-slate-100">
                <span class="text-violet-500"><?= defined('SITE_NAME') ? htmlspecialchars(SITE_NAME) : 'Wixy' ?></span> Panel
            </a>
            <a href="/dashboard/" class="text-sm text-slate-400 hover:text-white transition">← Retour</a>
        </div>
    </header>

    <main class="flex-1 max-w-7xl w-full mx-auto px-4 py-8 space-y-6">

        <!-- Sélection du serveur -->
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 bg-slate-900 p-4 rounded-xl border border-slate-800">
            <div>
                <h1 class="text-lg font-bold">Gestion du Serveur</h1>
                <p class="text-xs text-slate-400">Sélectionne un serveur actif pour voir sa console et contrôler son état.</p>
            </div>
            
            <form method="GET" class="w-full sm:w-auto">
                <select name="identifier" onchange="this.form.submit()" class="w-full sm:w-64 bg-slate-950 border border-slate-800 text-xs rounded-xl p-2.5 text-slate-200 focus:border-violet-500 focus:outline-none">
                    <?php if (empty($servers)): ?>
                        <option value="">Aucun serveur actif</option>
                    <?php else: ?>
                        <?php foreach ($servers as $srv): ?>
                            <option value="<?= htmlspecialchars($srv['pterodactyl_identifier']) ?>" <?= ($currentServer && $currentServer['pterodactyl_identifier'] === $srv['pterodactyl_identifier']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($srv['product_name']) ?> (<?= htmlspecialchars($srv['pterodactyl_identifier']) ?>)
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </form>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-500/10 border border-red-500/50 text-red-400 p-4 rounded-xl text-sm"><?= $error ?></div>
        <?php endif; ?>
        <?php if ($message): ?>
            <div class="bg-emerald-500/10 border border-emerald-500/50 text-emerald-400 p-4 rounded-xl text-sm"><?= $message ?></div>
        <?php endif; ?>

        <?php if ($currentServer): ?>
            <!-- Contrôles Power & Navigation -->
            <div class="bg-slate-900 border border-slate-800 p-4 rounded-xl flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center space-x-2">
                    <span class="text-xs text-slate-400">Identifiant :</span>
                    <span class="font-mono text-xs bg-slate-950 px-2 py-1 rounded border border-slate-800 text-violet-400">
                        <?= htmlspecialchars($currentServer['pterodactyl_identifier']) ?>
                    </span>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <!-- Bouton Accès Gestionnaire de fichiers -->
                    <a href="/dashboard/clients/files/?identifier=<?= htmlspecialchars($currentServer['pterodactyl_identifier']) ?>" class="bg-slate-800 hover:bg-slate-700 border border-slate-700 text-slate-200 text-xs font-semibold px-4 py-2 rounded-lg transition flex items-center space-x-1.5">
                        <i class="fa-solid fa-folder-open text-violet-400"></i>
                        <span>Fichiers</span>
                    </a>

                    <!-- Formulaire d'actions Power -->
                    <form id="power-form" method="POST" class="flex items-center space-x-2">
                        <button type="submit" name="action_power" value="start" class="bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold px-4 py-2 rounded-lg transition flex items-center space-x-1.5">
                            <i class="fa-solid fa-play"></i> <span>Démarrer</span>
                        </button>

                        <button type="submit" name="action_power" value="restart" class="bg-amber-600 hover:bg-amber-700 text-white text-xs font-semibold px-4 py-2 rounded-lg transition flex items-center space-x-1.5">
                            <i class="fa-solid fa-rotate-right"></i> <span>Redémarrer</span>
                        </button>

                        <button type="submit" name="action_power" value="stop" class="bg-red-600 hover:bg-red-700 text-white text-xs font-semibold px-4 py-2 rounded-lg transition flex items-center space-x-1.5">
                            <i class="fa-solid fa-power-off"></i> <span>Éteindre</span>
                        </button>

                        <button type="button" id="open-kill-modal" class="bg-rose-900 hover:bg-rose-950 text-rose-200 border border-rose-700 text-xs font-semibold px-3 py-2 rounded-lg transition flex items-center space-x-1.5">
                            <i class="fa-solid fa-skull"></i> <span>Kill</span>
                        </button>
                    </form>
                </div>
            </div>

            <!-- Console + Panneau de stats -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">

                <!-- Moniteur Console WebSocket -->
                <div class="lg:col-span-2 bg-slate-900 border border-slate-800 rounded-xl overflow-hidden shadow-2xl">
                    <div class="bg-slate-950 px-4 py-3 border-b border-slate-800 flex items-center justify-between">
                        <span class="text-xs font-mono text-slate-400"><i class="fa-solid fa-terminal mr-2"></i>Console en direct</span>
                        <div class="flex items-center gap-3">
                            <button id="clear-console-btn" class="text-xs text-slate-500 hover:text-white transition">
                                <i class="fa-solid fa-trash-can mr-1"></i>Effacer l'écran
                            </button>
                            <span id="socket-status" class="text-xs px-2 py-0.5 rounded bg-amber-500/10 text-amber-400 border border-amber-500/20">Connexion...</span>
                        </div>
                    </div>

                    <!-- Zone d'affichage des logs -->
                    <div id="console-logs" class="p-4 h-96 overflow-y-auto font-mono text-xs bg-black text-slate-300 space-y-1 whitespace-pre-wrap leading-relaxed"></div>

                    <!-- Input commande -->
                    <div class="p-3 bg-slate-950 border-t border-slate-800 flex items-center space-x-2">
                        <input type="text" id="command-input" placeholder="Saisir une commande..." class="flex-1 bg-slate-900 border border-slate-800 text-xs text-slate-200 rounded-lg px-3 py-2 focus:outline-none focus:border-violet-500 font-mono">
                        <button id="send-btn" class="bg-violet-600 hover:bg-violet-700 text-white text-xs font-semibold px-4 py-2 rounded-lg transition">
                            Envoyer
                        </button>
                    </div>
                </div>

                <!-- Panneau de stats -->
                <div class="flex flex-col gap-2.5">
                    <?php if ($players_max > 0): ?>
                    <div class="bg-slate-900 border border-violet-500/20 p-3.5 rounded-xl flex justify-between items-center">
                        <span class="text-xs font-bold text-violet-400"><i class="fa-solid fa-users w-5"></i> Joueurs</span>
                        <span id="statPlayers" class="text-xs font-mono font-bold text-white">0 / <?= $players_max ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="bg-slate-900 border border-slate-800 p-3.5 rounded-xl flex justify-between items-center">
                        <span class="text-xs font-bold text-slate-400"><i class="fa-solid fa-link text-violet-400 w-5"></i> Adresse</span>
                        <span id="statAddress" class="text-xs font-mono font-medium text-white truncate max-w-[160px]"><?= htmlspecialchars($server_address) ?></span>
                    </div>
                    <div class="bg-slate-900 border border-slate-800 p-3.5 rounded-xl flex justify-between items-center">
                        <span class="text-xs font-bold text-slate-400"><i class="fa-solid fa-clock text-violet-400 w-5"></i> Uptime</span>
                        <span id="statUptime" class="text-xs font-medium text-white">Hors ligne</span>
                    </div>
                    <div class="bg-slate-900 border border-slate-800 p-3.5 rounded-xl flex justify-between items-center">
                        <span class="text-xs font-bold text-slate-400"><i class="fa-solid fa-microchip text-violet-400 w-5"></i> Charge CPU</span>
                        <span id="statCpu" class="text-xs font-mono font-medium text-white">0%</span>
                    </div>
                    <div class="bg-slate-900 border border-slate-800 p-3.5 rounded-xl flex justify-between items-center">
                        <span class="text-xs font-bold text-slate-400"><i class="fa-solid fa-memory text-violet-400 w-5"></i> RAM</span>
                        <span id="statRam" class="text-xs font-mono font-medium text-white">0 Mo / <?= $ram_max >= 1024 ? round($ram_max/1024,1) . " Go" : $ram_max . " Mo" ?></span>
                    </div>
                    <div class="bg-slate-900 border border-slate-800 p-3.5 rounded-xl flex justify-between items-center">
                        <span class="text-xs font-bold text-slate-400"><i class="fa-solid fa-hard-drive text-violet-400 w-5"></i> Stockage</span>
                        <span id="statDisk" class="text-xs font-mono font-medium text-white">0 Mo / <?= $disk_max >= 1024 ? round($disk_max/1024,1) . " Go" : $disk_max . " Mo" ?></span>
                    </div>
                </div>
            </div>

            <!-- Modal sur-mesure de confirmation Kill -->
            <div id="kill-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm">
                <div class="bg-slate-900 border border-slate-800 w-full max-w-md rounded-2xl p-6 shadow-2xl space-y-5 transform transition-all">
                    <div class="flex items-center space-x-3 text-rose-500">
                        <div class="w-10 h-10 rounded-xl bg-rose-500/10 border border-rose-500/20 flex items-center justify-center text-lg">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-slate-100">Forcer l'arrêt (Kill)</h3>
                            <p class="text-xs text-slate-400">Confirmation requise</p>
                        </div>
                    </div>

                    <p class="text-xs text-slate-300 leading-relaxed bg-slate-950 p-3 rounded-xl border border-slate-800">
                        Forcer l'arrêt du serveur interrompt immédiatement tous les processus. <strong class="text-rose-400">Cela peut entraîner une perte de données ou corrompre vos fichiers.</strong>
                    </p>

                    <div class="flex items-center justify-end space-x-3 pt-2">
                        <button type="button" id="close-kill-modal" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold rounded-lg transition">
                            Annuler
                        </button>
                        <form method="POST">
                            <button type="submit" name="action_power" value="kill" class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white text-xs font-semibold rounded-lg transition shadow-lg shadow-rose-600/20 flex items-center space-x-1.5">
                                <i class="fa-solid fa-skull"></i>
                                <span>Forcer l'arrêt</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="bg-slate-900 border border-slate-800 rounded-xl p-8 text-center text-slate-400 text-sm">
                Aucun serveur sélectionné ou disponible.
            </div>
        <?php endif; ?>

    </main>

    <script>
    // Gestion du Modal personnalisé pour l'action Kill
    const killModal = document.getElementById('kill-modal');
    const openKillBtn = document.getElementById('open-kill-modal');
    const closeKillBtn = document.getElementById('close-kill-modal');

    if (openKillBtn && killModal && closeKillBtn) {
        openKillBtn.addEventListener('click', () => {
            killModal.classList.remove('hidden');
        });

        closeKillBtn.addEventListener('click', () => {
            killModal.classList.add('hidden');
        });

        killModal.addEventListener('click', (e) => {
            if (e.target === killModal) {
                killModal.classList.add('hidden');
            }
        });
    }
    </script>

    <?php if ($wsData): ?>
    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const wsData = <?= json_encode($wsData) ?>;
        const logsContainer = document.getElementById('console-logs');
        const statusBadge = document.getElementById('socket-status');
        const commandInput = document.getElementById('command-input');
        const sendBtn = document.getElementById('send-btn');
        const clearBtn = document.getElementById('clear-console-btn');

        const ansiUp = new AnsiUp();
        ansiUp.use_classes = false;

        let socket = null;
        let reconnectTimeout = null;

        function cleanAnsiLine(rawText) {
            if (!rawText) return '';
            return rawText.replace(/\u001b\[\d+[GK]/g, '');
        }

        function linkify(html) {
            const urlRegex = /(https?:\/\/[^\s<]+|wss?:\/\/[^\s<]+)/g;
            return html.replace(urlRegex, (url) => {
                let cleanUrl = url.replace(/[.,;)]+$/, '');
                return `<a href="${cleanUrl}" target="_blank" rel="noopener noreferrer" class="text-violet-400 underline hover:text-violet-300 break-all">${cleanUrl}</a>`;
            });
        }

        function appendLog(rawMessage) {
            if (rawMessage === null || rawMessage === undefined) return;
            const cleaned = cleanAnsiLine(rawMessage);
            if (!cleaned.trim() && cleaned !== "") return;

            let htmlContent = ansiUp.ansi_to_html(cleaned);
            htmlContent = linkify(htmlContent);

            const line = document.createElement('div');
            line.innerHTML = htmlContent;

            logsContainer.appendChild(line);

            // Limiter à 300 lignes pour éviter de saturer la mémoire du navigateur
            if (logsContainer.children.length > 300) {
                logsContainer.removeChild(logsContainer.firstChild);
            }
            logsContainer.scrollTop = logsContainer.scrollHeight;
        }

        function formatUptime(seconds) {
            if (!seconds || seconds <= 0) return "Hors ligne";
            const d = Math.floor(seconds / (3600 * 24));
            const h = Math.floor((seconds % (3600 * 24)) / 3600);
            const m = Math.floor((seconds % 3600) / 60);
            return (d > 0 ? d + "j " : "") + (h > 0 ? h + "h " : "") + (m > 0 ? m + "m " : "") + Math.floor(seconds % 60) + "s";
        }

        function connect() {
            socket = new WebSocket(wsData.socket);

            socket.onopen = () => {
                statusBadge.className = "text-xs px-2 py-0.5 rounded bg-emerald-500/10 text-emerald-400 border border-emerald-500/20";
                statusBadge.textContent = "Connecté";

                socket.send(JSON.stringify({
                    event: 'auth',
                    args: [wsData.token]
                }));

                socket.send(JSON.stringify({
                    event: 'send logs',
                    args: []
                }));

                socket.send(JSON.stringify({
                    event: 'send stats',
                    args: []
                }));
            };

            socket.onmessage = (event) => {
                try {
                    const data = JSON.parse(event.data);

                    if (data.event === 'console output') {
                        data.args.forEach(arg => appendLog(arg));
                    } else if (data.event === 'status') {
                        appendLog(`\x1b[35m[Statut Serveur] Statut actuel : ${data.args[0]}\x1b[0m`);
                    } else if (data.event === 'stats') {
                        const stats = JSON.parse(data.args[0]);
                        const cpuEl = document.getElementById('statCpu');
                        const ramEl = document.getElementById('statRam');
                        const uptimeEl = document.getElementById('statUptime');
                        const playersEl = document.getElementById('statPlayers');

                        if (cpuEl) cpuEl.innerText = stats.cpu_absolute.toFixed(1) + "%";

                        if (ramEl) {
                            const ramUsed = (stats.memory_bytes / 1024 / 1024).toFixed(1);
                            const ramUsedStr = ramUsed >= 1024 ? (ramUsed / 1024).toFixed(1) + " Go" : ramUsed + " Mo";
                            const currentMax = ramEl.innerText.split(' / ')[1] || '';
                            ramEl.innerText = `${ramUsedStr} / ${currentMax}`;
                        }

                        if (uptimeEl) uptimeEl.innerText = formatUptime((stats.uptime || 0) / 1000);

                        if (playersEl && stats.players_max) {
                            playersEl.innerText = `${stats.players_current || 0} / ${stats.players_max}`;
                        }
                    }
                } catch (e) {
                    console.error("Erreur parsing WebSocket message:", e);
                }
            };

            socket.onclose = () => {
                statusBadge.className = "text-xs px-2 py-0.5 rounded bg-red-500/10 text-red-400 border border-red-500/20";
                statusBadge.textContent = "Déconnecté";
                appendLog("\x1b[31m[SYSTÈME] Connexion perdue. Reconnexion dans 5 secondes...\x1b[0m");
                clearTimeout(reconnectTimeout);
                reconnectTimeout = setTimeout(connect, 5000);
            };

            socket.onerror = () => {
                appendLog("\x1b[31m[SYSTÈME] Erreur de connexion WebSocket.\x1b[0m");
            };
        }

        function sendCommand() {
            const cmd = commandInput.value.trim();
            if (cmd && socket && socket.readyState === WebSocket.OPEN) {
                socket.send(JSON.stringify({
                    event: 'send command',
                    args: [cmd]
                }));
                commandInput.value = '';
            }
        }

        sendBtn.addEventListener('click', sendCommand);
        commandInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') sendCommand();
        });

        if (clearBtn) {
            clearBtn.addEventListener('click', () => {
                logsContainer.innerHTML = '';
            });
        }

        connect();
    });
    </script>
    <?php endif; ?>
</body>
</html>
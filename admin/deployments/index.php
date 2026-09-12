<?php
/**
 * Page d'historique des déploiements - Admin Only
 * Stack: Tailwind + FontAwesome + Custom CSS
 */

// ═══════════════════════════════════════════
// 1. CONFIGURATION & CONNEXION BDD
// ═══════════════════════════════════════════
// Adaptez ce chemin vers votre fichier qui contient la variable $pdo
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/config.php';

// Fallback si pas de config centralisé (à adapter si nécessaire)
/*if (!isset($pdo)) {
    try {
        $pdo = new PDO(
            "mysql:host=localhost;dbname=s43_orinheberge;charset=utf8mb4",
            'root', 
            '1504', 
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    } catch (PDOException $e) {
        die("Erreur critique : Connexion BDD impossible.");
    }
}*/

session_start();

// ═══════════════════════════════════════════
// 2. SÉCURITÉ (Votre code exact)
// ═══════════════════════════════════════════
if (!isset($_SESSION['user_id'])) { 
    header('Location: /login/'); 
    exit(); 
}

$stmt = $pdo->prepare('SELECT id, pseudo, firstname, lastname, email, avatar, is_admin FROM users WHERE id=? LIMIT 1');
$stmt->execute([$_SESSION['user_id']]);
$admin = $stmt->fetch();

if (!$admin || !$admin['is_admin']) {
    http_response_code(403);
    die('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>403</title><script src="https://cdn.tailwindcss.com"></script></head><body class="bg-[#0b0f19] text-white flex items-center justify-center h-screen"><div class="text-center"><div class="text-7xl font-black text-red-500 mb-4">403</div><p class="text-gray-400 text-lg mb-6">Accès refusé.</p><a href="/" class="bg-sky-600 hover:bg-sky-500 px-6 py-3 rounded-xl font-bold text-sm">Retour</a></div></body></html>');
}

// ═══════════════════════════════════════════
// 3. LOGIQUE MÉTIER
// ═══════════════════════════════════════════

$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 15;
$offset = ($page - 1) * $per_page;

try {
    // Total
    $stmt = $pdo->query("SELECT COUNT(*) FROM notifications WHERE type = 'system_deploy'");
    $total = (int)$stmt->fetchColumn();
    $total_pages = max(1, ceil($total / $per_page));

    // Données
    $stmt = $pdo->prepare("
        SELECT id, title, message, link, is_read, meta, created_at
        FROM notifications
        WHERE type = 'system_deploy'
        ORDER BY created_at DESC
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $deployments = $stmt->fetchAll();

} catch (PDOException $e) {
    $deployments = [];
    $error_log = $e->getMessage();
}

// Actions POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'mark_read' && !empty($_POST['id'])) {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
        $stmt->execute([(int)$_POST['id']]);
        header('Location: /admin/deployments/');
        exit;
    }
    if ($_POST['action'] === 'mark_all_read') {
        $pdo->exec("UPDATE notifications SET is_read = 1 WHERE type = 'system_deploy'");
        header('Location: /admin/deployments/');
        exit;
    }
}

// Helpers
function timeAgo($datetime) {
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    if ($diff->d > 0) return $diff->d . "j";
    if ($diff->h > 0) return $diff->h . "h";
    if ($diff->i > 0) return $diff->i . "min";
    return "maintenant";
}

function extractMeta($json) {
    $data = json_decode($json, true);
    return $data ?: ['commit' => 'unknown', 'version' => '0.0.0'];
}
?>
<!DOCTYPE html>
<html lang="fr" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Déploiements | Admin Dashboard</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Custom CSS -->
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #0b0f19;
            background-image: 
                radial-gradient(at 0% 0%, rgba(56, 189, 248, 0.1) 0px, transparent 50%),
                radial-gradient(at 100% 0%, rgba(139, 92, 246, 0.15) 0px, transparent 50%);
            background-attachment: fixed;
            color: #e2e8f0;
        }

        /* Glassmorphism Card */
        .glass-card {
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.1);
        }

        .glass-card:hover {
            border-color: rgba(56, 189, 248, 0.3);
            box-shadow: 0 0 20px rgba(56, 189, 248, 0.1);
            transform: translateY(-2px);
        }

        /* Scrollbar Custom */
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: #0f172a; }
        ::-webkit-scrollbar-thumb { background: #334155; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #475569; }

        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in {
            animation: fadeIn 0.4s ease-out forwards;
        }
        
        .badge-glow {
            box-shadow: 0 0 10px rgba(56, 189, 248, 0.2);
        }
    </style>
</head>
<body class="min-h-screen pb-10">

    <!-- Navbar simple -->
    <nav class="border-b border-slate-800 bg-slate-900/50 backdrop-blur-md sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-sky-500 to-violet-600 flex items-center justify-center text-white font-bold shadow-lg shadow-sky-500/20">
                        <i class="fa-solid fa-rocket"></i>
                    </div>
                    <span class="font-bold text-xl tracking-tight text-white">Admin<span class="text-sky-400">Panel</span></span>
                </div>
                <div class="flex items-center gap-4">
                    <a href="/admin/" class="text-slate-400 hover:text-white transition-colors text-sm font-medium">
                        <i class="fa-solid fa-arrow-left mr-2"></i>Dashboard
                    </a>
                    <div class="h-8 w-px bg-slate-700"></div>
                    <div class="flex items-center gap-2">
                        <img src="<?= htmlspecialchars($admin['avatar'] ?? 'https://ui-avatars.com/api/?name='.urlencode($admin['pseudo'])) ?>" class="w-8 h-8 rounded-full border border-slate-600" alt="Avatar">
                        <span class="text-sm font-medium text-slate-200"><?= htmlspecialchars($admin['pseudo']) ?></span>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <main class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 mt-10">
        
        <!-- Header Section -->
        <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-8 animate-fade-in">
            <div>
                <h1 class="text-3xl font-bold text-white mb-2">Historique des déploiements</h1>
                <p class="text-slate-400">Suivi automatisé des mises en production et versions.</p>
            </div>
            <div class="flex gap-3">
                <?php if($total > 0): ?>
                <form method="POST">
                    <input type="hidden" name="action" value="mark_all_read">
                    <button type="submit" class="group relative px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white rounded-lg border border-slate-700 transition-all text-sm font-medium overflow-hidden">
                        <span class="relative z-10 flex items-center gap-2">
                            <i class="fa-solid fa-check-double text-emerald-400 group-hover:scale-110 transition-transform"></i>
                            Tout marquer lu
                        </span>
                    </button>
                </form>
                <?php endif; ?>
                <button onclick="window.location.reload()" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-lg border border-slate-700 transition-all text-sm">
                    <i class="fa-solid fa-sync-alt mr-2"></i> Actualiser
                </button>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8 animate-fade-in" style="animation-delay: 0.1s;">
            <!-- Stat 1 -->
            <div class="glass-card p-5 rounded-xl flex items-center gap-4">
                <div class="w-12 h-12 rounded-full bg-blue-500/10 flex items-center justify-center text-blue-400 text-xl">
                    <i class="fa-solid fa-server"></i>
                </div>
                <div>
                    <p class="text-slate-400 text-xs uppercase font-semibold tracking-wider">Total Deploys</p>
                    <p class="text-2xl font-bold text-white"><?= $total ?></p>
                </div>
            </div>
            
            <!-- Stat 2 -->
            <div class="glass-card p-5 rounded-xl flex items-center gap-4">
                <div class="w-12 h-12 rounded-full bg-violet-500/10 flex items-center justify-center text-violet-400 text-xl">
                    <i class="fa-solid fa-code-branch"></i>
                </div>
                <div>
                    <p class="text-slate-400 text-xs uppercase font-semibold tracking-wider">Version Actuelle</p>
                    <p class="text-2xl font-bold text-white">
                        <?= !empty($deployments) ? htmlspecialchars(extractMeta($deployments[0]['meta'])['version']) : '-' ?>
                    </p>
                </div>
            </div>

            <!-- Stat 3 -->
            <div class="glass-card p-5 rounded-xl flex items-center gap-4">
                <div class="w-12 h-12 rounded-full bg-emerald-500/10 flex items-center justify-center text-emerald-400 text-xl">
                    <i class="fa-solid fa-clock-rotate-left"></i>
                </div>
                <div>
                    <p class="text-slate-400 text-xs uppercase font-semibold tracking-wider">Dernier Activity</p>
                    <p class="text-lg font-bold text-white">
                        <?= !empty($deployments) ? timeAgo($deployments[0]['created_at']) : '-' ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Timeline List -->
        <div class="space-y-4">
            <?php if (empty($deployments)): ?>
                <div class="glass-card rounded-xl p-10 text-center animate-fade-in">
                    <div class="w-16 h-16 bg-slate-800 rounded-full flex items-center justify-center mx-auto mb-4 text-slate-500 text-2xl">
                        <i class="fa-solid fa-box-open"></i>
                    </div>
                    <h3 class="text-xl font-bold text-white mb-2">Aucun historique</h3>
                    <p class="text-slate-400 max-w-md mx-auto">Les déploiements effectués via le pipeline CI/CD apparaîtront automatiquement ici.</p>
                </div>
            <?php else: ?>
                <?php foreach ($deployments as $index => $deploy): 
                    $meta = extractMeta($deploy['meta']);
                    $isUnread = !$deploy['is_read'];
                ?>
                <div class="glass-card rounded-xl p-6 relative group animate-fade-in transition-all duration-300 hover:-translate-y-1" style="animation-delay: <?= ($index * 0.05) + 0.2 ?>s;">
                    
                    <!-- Indicateur Non-Lu -->
                    <?php if($isUnread): ?>
                        <div class="absolute top-6 right-6">
                            <span class="flex h-3 w-3">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-sky-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-3 w-3 bg-sky-500"></span>
                            </span>
                        </div>
                    <?php endif; ?>

                    <div class="flex flex-col md:flex-row md:items-start justify-between gap-4 mb-4">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-lg bg-gradient-to-br from-slate-700 to-slate-800 border border-slate-600 flex items-center justify-center text-slate-300 shadow-inner">
                                <i class="fa-solid fa-rocket"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-white leading-tight"><?= htmlspecialchars($deploy['title']) ?></h3>
                                <div class="flex items-center gap-2 text-xs text-slate-400 mt-1">
                                    <span><i class="fa-regular fa-calendar mr-1"></i> <?= date('d M Y, H:i', strtotime($deploy['created_at'])) ?></span>
                                    <span class="w-1 h-1 bg-slate-600 rounded-full"></span>
                                    <span><?= timeAgo($deploy['created_at']) ?></span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex items-center gap-2">
                            <span class="px-3 py-1 rounded-full bg-sky-500/10 text-sky-400 border border-sky-500/20 text-xs font-mono font-bold badge-glow">
                                <?= htmlspecialchars($meta['version']) ?>
                            </span>
                            <span class="px-3 py-1 rounded-full bg-violet-500/10 text-violet-400 border border-violet-500/20 text-xs font-mono">
                                <i class="fa-solid fa-code-commit mr-1 opacity-70"></i><?= htmlspecialchars(substr($meta['commit'], 0, 7)) ?>
                            </span>
                        </div>
                    </div>

                    <!-- Message Body -->
                    <div class="bg-slate-900/50 rounded-lg p-4 border border-slate-700/50 mb-4 font-mono text-sm text-slate-300">
                        <div class="flex items-start gap-3">
                            <i class="fa-solid fa-terminal text-slate-500 mt-1"></i>
                            <div class="break-all">
                                <?= nl2br(htmlspecialchars($deploy['message'])) ?>
                            </div>
                        </div>
                    </div>

                    <!-- Footer Actions -->
                    <div class="flex items-center justify-between pt-2 border-t border-slate-700/50">
                        <div class="text-xs text-slate-500">
                            ID: #<?= $deploy['id'] ?> • Type: system_deploy
                        </div>
                        
                        <?php if($isUnread): ?>
                            <form method="POST">
                                <input type="hidden" name="action" value="mark_read">
                                <input type="hidden" name="id" value="<?= $deploy['id'] ?>">
                                <button type="submit" class="text-xs font-medium text-sky-400 hover:text-sky-300 flex items-center gap-1 transition-colors">
                                    <i class="fa-solid fa-check"></i> Marquer comme lu
                                </button>
                            </form>
                        <?php else: ?>
                            <span class="text-xs font-medium text-emerald-500/70 flex items-center gap-1">
                                <i class="fa-solid fa-check-circle"></i> Lu
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="mt-8 flex justify-center animate-fade-in">
                <nav class="flex items-center gap-2">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?>" class="w-10 h-10 flex items-center justify-center rounded-lg bg-slate-800 border border-slate-700 text-slate-400 hover:text-white hover:border-sky-500 transition-all">
                            <i class="fa-solid fa-chevron-left"></i>
                        </a>
                    <?php endif; ?>

                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <a href="?page=<?= $i ?>" class="w-10 h-10 flex items-center justify-center rounded-lg border transition-all font-medium <?= $i == $page ? 'bg-sky-600 border-sky-500 text-white shadow-lg shadow-sky-500/20' : 'bg-slate-800 border-slate-700 text-slate-400 hover:bg-slate-700 hover:text-white' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?= $page + 1 ?>" class="w-10 h-10 flex items-center justify-center rounded-lg bg-slate-800 border border-slate-700 text-slate-400 hover:text-white hover:border-sky-500 transition-all">
                            <i class="fa-solid fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </nav>
            </div>
        <?php endif; ?>

    </main>

</body>
</html>
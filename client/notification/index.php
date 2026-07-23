<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/lang.php';

// Sécurité
if (!isset($_SESSION['user_id'])) { 
    header('Location: /login/'); 
    exit(); 
}

$message = '';
$message_type = 'info';
$active_page = 'notifications'; // Pour la sidebar

try {
    $pdo = new PDO('mysql:host=localhost;dbname=s43_orinheberge;charset=utf8mb4', 'root', '1504', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, 
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    // Récupération utilisateur (pour l'avatar dans le header)
    $stmt = $pdo->prepare('SELECT id, firstname, lastname, pseudo, email, avatar FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) { session_destroy(); header('Location: /login/'); exit(); }

    // Gestion des actions (Marquer comme lu / Tout marquer)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['mark_read'])) {
            $notif_id = (int)$_POST['notif_id'];
            $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?')->execute([$notif_id, $_SESSION['user_id']]);
            $message = "Notification marquée comme lue.";
            $message_type = 'success';
        } elseif (isset($_POST['mark_all_read'])) {
            $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0')->execute([$_SESSION['user_id']]);
            $message = "Toutes les notifications ont été marquées comme lues.";
            $message_type = 'success';
        }
    }

    // Filtres
    $filter = $_GET['filter'] ?? 'all'; // all, unread, read
    $sql = 'SELECT * FROM notifications WHERE user_id = ?';
    $params = [$_SESSION['user_id']];

    if ($filter === 'unread') {
        $sql .= ' AND is_read = 0';
    } elseif ($filter === 'read') {
        $sql .= ' AND is_read = 1';
    }

    $sql .= ' ORDER BY created_at DESC LIMIT 50'; // Limite à 50 dernières
    
    $stmt_notifs = $pdo->prepare($sql);
    $stmt_notifs->execute($params);
    $notifications = $stmt_notifs->fetchAll(PDO::FETCH_ASSOC);

    // Compteur pour le badge
    $stmt_count = $pdo->prepare('SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0');
    $stmt_count->execute([$_SESSION['user_id']]);
    $unread_count = $stmt_count->fetch()['count'];

} catch (Exception $e) {
    $message = "Erreur de base de données : " . $e->getMessage();
    $message_type = 'error';
    $notifications = [];
    $unread_count = 0;
}

// Fonction helper pour les icônes/couleurs
function get_notification_style($type) {
    switch ($type) {
        case 'success': return ['icon' => 'fa-check-circle', 'color' => 'text-emerald-400', 'bg' => 'bg-emerald-500/10', 'border' => 'border-emerald-500/20'];
        case 'error':   return ['icon' => 'fa-exclamation-triangle', 'color' => 'text-red-400', 'bg' => 'bg-red-500/10', 'border' => 'border-red-500/20'];
        case 'warning': return ['icon' => 'fa-exclamation-circle', 'color' => 'text-amber-400', 'bg' => 'bg-amber-500/10', 'border' => 'border-amber-500/20'];
        default:        return ['icon' => 'fa-info-circle', 'color' => 'text-sky-400', 'bg' => 'bg-sky-500/10', 'border' => 'border-sky-500/20'];
    }
}

$avatar_url = !empty($user['avatar']) ? '/' . $user['avatar'] : 'https://www.gravatar.com/avatar/' . md5(strtolower(trim($user['email']))) . '?d=mp&s=150';
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Mes Notifications | Dashboard</title>
    <link rel="icon" type="image/png" href="https://heberge.orinstone.deepstone.fr/favicon.ico">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: #0f172a; }
        ::-webkit-scrollbar-thumb { background: #334155; border-radius: 4px; }
        body { background-color: #0f172a; background-image: radial-gradient(at 0% 0%, hsla(253,16%,7%,1) 0, transparent 50%), radial-gradient(at 50% 0%, hsla(225,39%,30%,1) 0, transparent 50%), radial-gradient(at 100% 0%, hsla(339,49%,30%,1) 0, transparent 50%); }
        .glass-panel { background: rgba(30, 41, 59, 0.7); backdrop-filter: blur(12px); border: 1px solid rgba(255, 255, 255, 0.08); }
        .sidebar-link { transition: all 0.2s ease; border-left: 3px solid transparent; }
        .sidebar-link:hover, .sidebar-link.active { background: rgba(56, 189, 248, 0.1); border-left-color: #38bdf8; color: #38bdf8; }
        .notif-item { transition: all 0.2s; }
        .notif-item:hover { transform: translateX(4px); }
    </style>
</head>
<body class="min-h-screen text-gray-300 font-sans flex flex-col">

    <div class="flex flex-1 w-full">
        
        <!-- SIDEBAR -->
       <?php require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/clients_sidebar.php'; ?>

        <!-- MAIN CONTENT -->
        <div class="flex-1 md:ml-64 flex flex-col min-h-screen relative w-full">
            
            <header class="md:hidden glass-panel p-4 flex justify-between items-center sticky top-0 z-30">
                <span class="font-bold text-white">OrinStone</span>
                <button class="text-gray-400"><i class="fas fa-bars text-xl"></i></button>
            </header>

            <main class="flex-grow p-6 lg:p-10 w-full max-w-5xl mx-auto">
                
                <!-- Header Page -->
                <div class="mb-8 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <h1 class="text-3xl font-bold text-white mb-1">Centre de Notifications</h1>
                        <p class="text-gray-400 text-sm">Restez informé de l'activité de votre compte.</p>
                    </div>
                    
                    <div class="flex items-center gap-3">
                         <?php if ($unread_count > 0): ?>
                        <form method="post">
                            <button type="submit" name="mark_all_read" class="text-xs font-bold uppercase tracking-wider text-sky-400 hover:text-sky-300 border border-sky-500/30 hover:bg-sky-500/10 px-4 py-2 rounded-lg transition">
                                <i class="fas fa-check-double mr-2"></i>Tout marquer lu
                            </button>
                        </form>
                        <?php endif; ?>
                        
                        <div class="flex items-center gap-3 glass-panel px-4 py-2 rounded-full">
                            <img src="<?php echo htmlspecialchars($avatar_url, ENT_QUOTES, 'UTF-8'); ?>" class="w-8 h-8 rounded-full border border-white/20">
                            <span class="text-sm font-semibold text-white hidden sm:inline"><?php echo htmlspecialchars($user['pseudo'] ?: $user['firstname']); ?></span>
                        </div>
                    </div>
                </div>

                <?php if ($message): ?>
                <div class="mb-6 p-4 rounded-xl border <?php echo $message_type === 'success' ? 'bg-green-500/10 border-green-500/30 text-green-400' : 'bg-red-500/10 border-red-500/30 text-red-400'; ?>">
                    <?php echo $message; ?>
                </div>
                <?php endif; ?>

                <!-- Filtres -->
                <div class="flex gap-2 mb-6 overflow-x-auto pb-2">
                    <a href="?filter=all" class="px-4 py-2 rounded-lg text-sm font-medium transition <?php echo $filter === 'all' ? 'bg-sky-600 text-white' : 'bg-white/5 text-gray-400 hover:bg-white/10 hover:text-white'; ?>">
                        Toutes
                    </a>
                    <a href="?filter=unread" class="px-4 py-2 rounded-lg text-sm font-medium transition <?php echo $filter === 'unread' ? 'bg-sky-600 text-white' : 'bg-white/5 text-gray-400 hover:bg-white/10 hover:text-white'; ?>">
                        Non lues <?php if($unread_count > 0) echo "<span class='ml-1 bg-white/20 px-1.5 rounded text-[10px]'>$unread_count</span>"; ?>
                    </a>
                    <a href="?filter=read" class="px-4 py-2 rounded-lg text-sm font-medium transition <?php echo $filter === 'read' ? 'bg-sky-600 text-white' : 'bg-white/5 text-gray-400 hover:bg-white/10 hover:text-white'; ?>">
                        Lues
                    </a>
                </div>

                <!-- Liste des notifications -->
                <div class="space-y-4">
                    <?php if (empty($notifications)): ?>
                        <div class="glass-panel rounded-2xl p-12 text-center">
                            <div class="w-16 h-16 bg-white/5 rounded-full flex items-center justify-center mx-auto mb-4">
                                <i class="fas fa-bell-slash text-2xl text-gray-500"></i>
                            </div>
                            <h3 class="text-white font-bold text-lg mb-1">Aucune notification</h3>
                            <p class="text-gray-500 text-sm">Vous êtes à jour ! Aucune nouvelle activité à signaler.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($notifications as $notif): 
                            $style = get_notification_style($notif['type']);
                            $date = date('d/m/Y à H:i', strtotime($notif['created_at']));
                        ?>
                        <div class="glass-panel rounded-xl p-5 notif-item relative group <?php echo $notif['is_read'] ? 'opacity-60 hover:opacity-100' : 'border-l-4 border-l-sky-500'; ?>">
                            
                            <div class="flex items-start gap-4">
                                <!-- Icone -->
                                <div class="w-10 h-10 rounded-lg <?php echo $style['bg'] . ' ' . $style['border']; ?> flex items-center justify-center shrink-0">
                                    <i class="fas <?php echo $style['icon'] . ' ' . $style['color']; ?>"></i>
                                </div>

                                <!-- Contenu -->
                                <div class="flex-1 min-w-0">
                                    <div class="flex justify-between items-start mb-1">
                                        <h4 class="text-white font-bold text-sm truncate pr-4"><?php echo htmlspecialchars($notif['title']); ?></h4>
                                        <span class="text-[10px] text-gray-500 whitespace-nowrap"><?php echo $date; ?></span>
                                    </div>
                                    <p class="text-gray-400 text-sm leading-relaxed mb-2"><?php echo nl2br(htmlspecialchars($notif['message'])); ?></p>
                                    
                                    <!-- Actions -->
                                    <div class="flex items-center gap-3 mt-2">
                                        <?php if (!empty($notif['link'])): ?>
                                            <a href="<?php echo htmlspecialchars($notif['link']); ?>" class="text-xs font-bold text-sky-400 hover:text-sky-300 flex items-center gap-1">
                                                Voir les détails <i class="fas fa-arrow-right text-[10px]"></i>
                                            </a>
                                        <?php endif; ?>

                                        <?php if (!$notif['is_read']): ?>
                                            <form method="post" class="inline">
                                                <input type="hidden" name="notif_id" value="<?php echo $notif['id']; ?>">
                                                <button type="submit" name="mark_read" class="text-xs text-gray-500 hover:text-white underline decoration-dotted">
                                                    Marquer comme lu
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-[10px] text-gray-600 uppercase tracking-wider font-bold"><i class="fas fa-check mr-1"></i>Lu</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

            </main>
            
            <?php require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/footer.php'; ?>
        </div>
    </div>

</body>
</html>
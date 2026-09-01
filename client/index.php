<?php
/**
 * OrinHeberge — Tableau de Bord Client Fusionné
 * Design moderne + Logique robuste
 */

ini_set('display_errors', 1); 
error_reporting(E_ALL);
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/lang.php';

if (!isset($_SESSION['user_id'])) { 
    header('Location: /login/'); 
    exit(); 
}

try {
    $pdo = new PDO('mysql:host=localhost;dbname=s43_orinheberge;charset=utf8mb4','root','1504',[
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, 
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch(PDOException $e) { 
    die(t('login.db_error')); 
}

// Configuration globale
$cfg = [];
foreach($pdo->query('SELECT `key`,`value` FROM settings') as $r) {
    $cfg[$r['key']] = $r['value'];
}

$panel_url      = $cfg['panel_url'] ?? 'https://panel.orinstone.deepstone.fr';
$api_key_client = $cfg['api_key_client'] ?? '';
$phpmyadmin_url = $cfg['phpmyadmin_url'] ?? 'https://php.orinstone.deepstone.fr';

// Mise à jour des données utilisateur (Session Refresh)
$stmt = $pdo->prepare('SELECT pseudo, firstname, avatar, is_admin, email FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$_SESSION['user_id']]);
$user_data = $stmt->fetch();

if ($user_data) {
    $_SESSION['username'] = !empty($user_data['pseudo']) ? $user_data['pseudo'] : $user_data['firstname'];
    $_SESSION['avatar']   = $user_data['avatar'];
    $_SESSION['is_admin'] = (bool)$user_data['is_admin'];
    $_SESSION['email']    = $user_data['email'];
}

// Récupération des services
$stmt = $pdo->prepare('SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC');
$stmt->execute([$_SESSION['user_id']]);
$services = $stmt->fetchAll();

// Statistiques rapides
$stmt = $pdo->prepare("SELECT COUNT(*) FROM support_tickets WHERE user_id = ? AND status != 'Fermé'");
$stmt->execute([$_SESSION['user_id']]);
$open_tickets = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
$stmt->execute([$_SESSION['user_id']]);
$unread_notifications = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM invoices WHERE user_id = ? AND status = 'pending'");
$stmt->execute([$_SESSION['user_id']]);
$pending_invoices = (int)$stmt->fetchColumn();

// Calculs statistiques
$active_services = array_filter($services, fn($s) => ($s['status'] ?? '') === 'paid');
$free_services   = array_filter($services, fn($s) => ($s['renewal_price'] ?? 0) == 0);
$suspended_services = array_filter($services, fn($s) => ($s['status'] ?? '') === 'suspended');
$total_monthly_cost = array_sum(array_map(fn($s) => (float)($s['renewal_price'] ?? 0), $active_services));

// Récupération des dernières activités (CORRECTION SQL ICI)
// Utilisation de COALESSE pour gérer 'subject', 'title' ou 'sujet' automatiquement
$stmt = $pdo->prepare("
    SELECT 'ticket' as type, COALESCE(subject, title, sujet, 'Nouveau ticket') as description, created_at, status
    FROM support_tickets 
    WHERE user_id = ? 
    UNION ALL
    SELECT 'service' as type, service_name as description, created_at, status
    FROM orders 
    WHERE user_id = ?
    ORDER BY created_at DESC 
    LIMIT 8
");
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
$recent_activities = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('client.dashboard'); ?> — OrinHeberge</title>
    <link rel="icon" type="image/png" href="/favicon.ico">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="/assets/css/clients_sidebar.css?v=<?php echo filemtime($_SERVER['DOCUMENT_ROOT'] . '/assets/css/clients_sidebar.css'); ?>" rel="stylesheet">
    
    <style>
        body { 
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); 
        }
        .dashboard-card {
            backdrop-filter: blur(10px);
            background: rgba(30, 41, 59, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
        }
        .dashboard-card:hover {
            background: rgba(30, 41, 59, 0.6);
            border-color: rgba(56, 189, 248, 0.3);
            transform: translateY(-2px);
        }
        .stat-badge {
            position: relative;
            overflow: hidden;
        }
        .stat-badge::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
            transition: left 0.5s;
        }
        .stat-badge:hover::before {
            left: 100%;
        }
        /* Status Colors */
        .service-status-active { color: #10b981; background-color: rgba(16, 185, 129, 0.1); }
        .service-status-suspended { color: #f59e0b; background-color: rgba(245, 158, 11, 0.1); }
        .service-status-expired { color: #ef4444; background-color: rgba(239, 68, 68, 0.1); }
        .service-status-free { color: #06b6d4; background-color: rgba(6, 182, 212, 0.1); }
        
        .activity-item {
            transition: all 0.2s ease;
            border-left: 3px solid transparent;
        }
        .activity-item:hover {
            border-left-color: #38bdf8;
            background-color: rgba(56, 189, 248, 0.05);
        }
    </style>
</head>
<body class="min-h-screen text-white">
    
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/inc/clients_sidebar.php'; ?>
    
    <div class="main-content">
        <!-- En-tête du tableau de bord -->
        <div class="bg-slate-800/50 border-b border-slate-700 px-6 py-6">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-black text-white mb-2">
                        <?php echo t('client.welcome'); ?>, 
                        <span class="text-sky-400"><?php echo htmlspecialchars($_SESSION['username']); ?></span> 👋
                    </h1>
                    <p class="text-slate-400"><?php echo t('client.dashboard_subtitle'); ?></p>
                </div>
                
                <div class="flex items-center gap-3">
                    <!-- Heure actuelle -->
                    <div class="hidden sm:flex items-center gap-2 bg-sky-500/10 border border-sky-500/20 text-sky-400 px-4 py-2 rounded-xl text-sm font-semibold">
                        <i class="fas fa-clock"></i>
                        <span id="current-time"><?php echo date('H:i'); ?></span>
                    </div>
                    
                    <!-- Notifications -->
                    <a href="/client/notifications/" class="relative bg-slate-700/50 hover:bg-slate-600/50 border border-slate-600 text-white p-3 rounded-xl transition group">
                        <i class="fas fa-bell text-lg group-hover:text-sky-400"></i>
                        <?php if($unread_notifications > 0): ?>
                        <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-6 w-6 flex items-center justify-center font-bold animate-pulse">
                            <?php echo min($unread_notifications, 9); ?><?php echo $unread_notifications > 9 ? '+' : ''; ?>
                        </span>
                        <?php endif; ?>
                    </a>
                    
                    <!-- Profil utilisateur -->
                    <a href="/client/profile/" class="flex items-center gap-3 bg-slate-700/50 hover:bg-slate-600/50 border border-slate-600 px-4 py-2 rounded-xl transition group">
                        <?php if(!empty($_SESSION['avatar'])): ?>
                        <img src="<?php echo htmlspecialchars($_SESSION['avatar']); ?>" alt="Avatar" class="w-8 h-8 rounded-full border-2 border-sky-400">
                        <?php else: ?>
                        <div class="w-8 h-8 bg-gradient-to-r from-sky-500 to-blue-500 rounded-full flex items-center justify-center text-sm font-bold text-white">
                            <?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?>
                        </div>
                        <?php endif; ?>
                        <div class="hidden md:block text-left">
                            <div class="text-sm font-semibold text-white group-hover:text-sky-400">
                                <?php echo htmlspecialchars($_SESSION['username']); ?>
                            </div>
                            <div class="text-xs text-slate-400"><?php echo t('client.view_profile'); ?></div>
                        </div>
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Contenu principal -->
        <div class="p-6">
            <!-- Statistiques principales -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                
                <!-- Total Services -->
                <div class="dashboard-card stat-badge rounded-2xl p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 rounded-xl bg-blue-500/20 border border-blue-500/30 flex items-center justify-center">
                            <i class="fas fa-server text-blue-400 text-lg"></i>
                        </div>
                        <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider"><?php echo t('client.services'); ?></span>
                    </div>
                    <div class="text-3xl font-black text-white mb-1"><?php echo count($services); ?></div>
                    <p class="text-sm text-slate-400"><?php echo t('client.total_deployed'); ?></p>
                </div>
                
                <!-- Services Actifs -->
                <div class="dashboard-card stat-badge rounded-2xl p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 rounded-xl bg-green-500/20 border border-green-500/30 flex items-center justify-center">
                            <i class="fas fa-check-circle text-green-400 text-lg"></i>
                        </div>
                        <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider"><?php echo t('client.active'); ?></span>
                    </div>
                    <div class="text-3xl font-black text-white mb-1"><?php echo count($active_services) + count($free_services); ?></div>
                    <p class="text-sm text-slate-400"><?php echo t('client.online'); ?></p>
                </div>
                
                <!-- Tickets Support -->
                <div class="dashboard-card stat-badge rounded-2xl p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 rounded-xl bg-purple-500/20 border border-purple-500/30 flex items-center justify-center">
                            <i class="fas fa-headset text-purple-400 text-lg"></i>
                        </div>
                        <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider"><?php echo t('client.support'); ?></span>
                    </div>
                    <div class="text-3xl font-black text-white mb-1"><?php echo $open_tickets; ?></div>
                    <p class="text-sm text-slate-400"><?php echo t('client.open_tickets'); ?></p>
                </div>
                
                <!-- Coût Mensuel -->
                <div class="dashboard-card stat-badge rounded-2xl p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 rounded-xl bg-yellow-500/20 border border-yellow-500/30 flex items-center justify-center">
                            <i class="fas fa-euro-sign text-yellow-400 text-lg"></i>
                        </div>
                        <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider"><?php echo t('client.monthly_cost'); ?></span>
                    </div>
                    <div class="text-3xl font-black text-white mb-1"><?php echo number_format($total_monthly_cost, 2); ?>€</div>
                    <p class="text-sm text-slate-400"><?php echo t('client.per_month'); ?></p>
                </div>
                
            </div>
            
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                
                <!-- Services récents -->
                <div class="lg:col-span-2 dashboard-card rounded-2xl p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-xl font-bold text-white flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg bg-sky-500/20 border border-sky-500/30 flex items-center justify-center">
                                <i class="fas fa-server text-sky-400 text-sm"></i>
                            </div>
                            <?php echo t('client.my_services'); ?>
                        </h2>
                        <a href="/client/servers/" class="text-sm text-sky-400 hover:text-sky-300 font-semibold flex items-center gap-2">
                            <?php echo t('client.manage_all'); ?>
                            <i class="fas fa-arrow-right text-xs"></i>
                        </a>
                    </div>
                    
                    <?php if(empty($services)): ?>
                    <div class="text-center py-12">
                        <div class="w-16 h-16 rounded-2xl bg-sky-500/10 border border-sky-500/20 flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-server text-sky-400 text-2xl"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-slate-300 mb-2"><?php echo t('client.no_services'); ?></h3>
                        <p class="text-slate-500 mb-6"><?php echo t('client.deploy_first_server'); ?></p>
                        <a href="/offres/" class="inline-flex items-center gap-2 bg-sky-600 hover:bg-sky-500 text-white font-semibold px-6 py-3 rounded-xl transition">
                            <i class="fas fa-rocket"></i>
                            <?php echo t('client.view_offers'); ?>
                        </a>
                    </div>
                    <?php else: ?>
                    
                    <div class="space-y-3">
                        <?php
                        // Icônes étendues (fusion des deux codes)
                        $service_icons = [
                            'minecraft' => ['fas fa-cube', 'bg-green-500/20', 'text-green-400', 'border-green-500/30'],
                            'fivem' => ['fas fa-car', 'bg-red-500/20', 'text-red-400', 'border-red-500/30'],
                            'hytale' => ['fas fa-gamepad', 'bg-purple-500/20', 'text-purple-400', 'border-purple-500/30'],
                            'php' => ['fas fa-code', 'bg-blue-500/20', 'text-blue-400', 'border-blue-500/30'],
                            'nodejs' => ['fab fa-node-js', 'bg-green-500/20', 'text-green-400', 'border-green-500/30'],
                            'python' => ['fab fa-python', 'bg-yellow-500/20', 'text-yellow-400', 'border-yellow-500/30'],
                            'java' => ['fab fa-java', 'bg-orange-500/20', 'text-orange-400', 'border-orange-500/30']
                        ];
                        
                        foreach(array_slice($services, 0, 6) as $service):
                            $service_name = strtolower($service['service_name'] ?? '');
                            $icon_config = ['fas fa-server', 'bg-slate-500/20', 'text-slate-400', 'border-slate-500/30'];
                            
                            foreach($service_icons as $key => $config) {
                                if(str_contains($service_name, $key)) {
                                    $icon_config = $config;
                                    break;
                                }
                            }
                            
                            $status = $service['status'] ?? 'unknown';
                            $is_free = ($service['renewal_price'] ?? 0) == 0;
                            
                            $status_config = match($status) {
                                'paid' => ['service-status-active', t('client.status_active')],
                                'suspended' => ['service-status-suspended', t('client.status_suspended')],
                                'expired' => ['service-status-expired', t('client.status_expired')],
                                default => $is_free ? ['service-status-free', t('client.status_free')] : ['service-status-expired', t('client.status_pending')]
                            };
                        ?>
                        
                        <div class="flex items-center gap-4 p-4 bg-slate-700/30 rounded-xl border border-slate-600 hover:bg-slate-700/50 transition">
                            <!-- Icône du service -->
                            <div class="w-12 h-12 rounded-xl <?php echo $icon_config[1]; ?> border <?php echo $icon_config[3]; ?> flex items-center justify-center flex-shrink-0">
                                <i class="<?php echo $icon_config[0]; ?> <?php echo $icon_config[2]; ?> text-lg"></i>
                            </div>
                            
                            <!-- Informations -->
                            <div class="flex-1 min-w-0">
                                <h3 class="font-semibold text-white truncate"><?php echo htmlspecialchars($service['service_name'] ?? 'Serveur'); ?></h3>
                                <div class="flex items-center gap-2 mt-1">
                                    <span class="text-xs text-slate-500 font-mono"><?php echo htmlspecialchars(substr($service['uuid'] ?? '', 0, 8)); ?>…</span>
                                    <span class="text-xs px-2 py-1 rounded-full <?php echo $status_config[0]; ?> font-medium">
                                        <?php echo $status_config[1]; ?>
                                    </span>
                                </div>
                            </div>
                            
                            <!-- Prix et actions -->
                            <div class="flex items-center gap-3 flex-shrink-0">
                                <?php if (!$is_free): ?>
                                <span class="text-sm font-bold text-white"><?php echo number_format((float)$service['renewal_price'], 2); ?>€</span>
                                <?php endif; ?>
                                
                                <?php if (!empty($service['uuid'])): ?>
                                <a href="<?php echo htmlspecialchars($panel_url); ?>/server/<?php echo htmlspecialchars($service['uuid']); ?>" 
                                   target="_blank" 
                                   class="w-8 h-8 rounded-lg bg-sky-500/20 border border-sky-500/30 flex items-center justify-center text-sky-400 hover:bg-sky-500/30 transition">
                                    <i class="fas fa-external-link-alt text-xs"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <?php endforeach; ?>
                        
                        <?php if (count($services) > 6): ?>
                        <div class="text-center pt-4 border-t border-slate-600">
                            <a href="/client/servers/" class="text-sm text-sky-400 hover:text-sky-300 font-semibold">
                                <?php echo t('client.view_more_services', ['count' => count($services) - 6]); ?>
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php endif; ?>
                </div>
                
                <!-- Accès rapide et informations -->
                <div class="space-y-6">
                    
                    <!-- Accès rapide -->
                    <div class="dashboard-card rounded-2xl p-6">
                        <h3 class="text-lg font-bold text-white mb-4 flex items-center gap-2">
                            <i class="fas fa-rocket text-sky-400"></i>
                            <?php echo t('client.quick_access'); ?>
                        </h3>
                        
                        <div class="space-y-3">
                            <a href="/offres/categories.php" class="flex items-center gap-3 p-3 bg-slate-700/30 hover:bg-slate-700/50 rounded-xl border border-slate-600 hover:border-sky-500/50 transition group">
                                <div class="w-8 h-8 rounded-lg bg-sky-500/20 border border-sky-500/30 flex items-center justify-center">
                                    <i class="fas fa-tags text-sky-400 text-sm"></i>
                                </div>
                                <div>
                                    <div class="text-sm font-semibold text-white group-hover:text-sky-400"><?php echo t('client.browse_offers'); ?></div>
                                    <div class="text-xs text-slate-500"><?php echo t('client.plans_pricing'); ?></div>
                                </div>
                            </a>
                            
                            <a href="/client/support/" class="flex items-center gap-3 p-3 bg-slate-700/30 hover:bg-slate-700/50 rounded-xl border border-slate-600 hover:border-purple-500/50 transition group">
                                <div class="w-8 h-8 rounded-lg bg-purple-500/20 border border-purple-500/30 flex items-center justify-center">
                                    <i class="fas fa-headset text-purple-400 text-sm"></i>
                                </div>
                                <div>
                                    <div class="text-sm font-semibold text-white group-hover:text-purple-400"><?php echo t('client.support'); ?></div>
                                    <div class="text-xs text-slate-500"><?php echo t('client.open_ticket'); ?></div>
                                </div>
                            </a>

                            <a href="<?php echo htmlspecialchars($panel_url); ?>" target="_blank" class="flex items-center gap-3 p-3 bg-slate-700/30 hover:bg-slate-700/50 rounded-xl border border-slate-600 hover:border-amber-500/50 transition group">
                                <div class="w-8 h-8 rounded-lg bg-amber-500/20 border border-amber-500/30 flex items-center justify-center">
                                    <i class="fas fa-cogs text-amber-400 text-sm"></i>
                                </div>
                                <div>
                                    <div class="text-sm font-semibold text-white group-hover:text-amber-400">Panel Pterodactyl</div>
                                    <div class="text-xs text-slate-500">Accès direct console</div>
                                </div>
                            </a>
                            
                            <a href="/client/billing/" class="flex items-center gap-3 p-3 bg-slate-700/30 hover:bg-slate-700/50 rounded-xl border border-slate-600 hover:border-green-500/50 transition group">
                                <div class="w-8 h-8 rounded-lg bg-green-500/20 border border-green-500/30 flex items-center justify-center">
                                    <i class="fas fa-file-invoice-dollar text-green-400 text-sm"></i>
                                </div>
                                <div>
                                    <div class="text-sm font-semibold text-white group-hover:text-green-400"><?php echo t('client.billing'); ?></div>
                                    <div class="text-xs text-slate-500"><?php echo t('client.invoices_receipts'); ?></div>
                                </div>
                            </a>
                        </div>
                    </div>
                    
                    <!-- Activité récente -->
                    <div class="dashboard-card rounded-2xl p-6">
                        <h3 class="text-lg font-bold text-white mb-4 flex items-center gap-2">
                            <i class="fas fa-history text-yellow-400"></i>
                            <?php echo t('client.recent_activity'); ?>
                        </h3>
                        
                        <?php if (empty($recent_activities)): ?>
                        <p class="text-sm text-slate-500 text-center py-4"><?php echo t('client.no_recent_activity'); ?></p>
                        <?php else: ?>
                        
                        <div class="space-y-2">
                            <?php foreach(array_slice($recent_activities, 0, 5) as $activity): ?>
                            <div class="activity-item p-3 rounded-lg bg-slate-700/20 hover:bg-slate-700/40 transition">
                                <div class="flex items-start gap-3">
                                    <div class="w-6 h-6 rounded-full bg-sky-500/20 flex items-center justify-center flex-shrink-0 mt-0.5">
                                        <i class="fas fa-<?php echo $activity['type'] === 'ticket' ? 'ticket-alt' : 'server'; ?> text-sky-400 text-xs"></i>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm text-white truncate"><?php echo htmlspecialchars($activity['description']); ?></p>
                                        <p class="text-xs text-slate-500"><?php echo date('d/m/Y H:i', strtotime($activity['created_at'])); ?></p>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <?php endif; ?>
                    </div>
                    
                </div>
                
            </div>
        </div>
    </div>
    
    <script>
        // Mise à jour de l'heure en temps réel
        function updateTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('fr-FR', {
                hour: '2-digit',
                minute: '2-digit'
            });
            const timeElement = document.getElementById('current-time');
            if (timeElement) {
                timeElement.textContent = timeString;
            }
        }
        
        // Mise à jour toutes les minutes
        setInterval(updateTime, 60000);
        updateTime(); // Initial call
        
        // Fonction pour la sidebar mobile
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            if (sidebar && overlay) {
                sidebar.classList.toggle('open');
                overlay.classList.toggle('open');
            }
        }
    </script>
</body>
</html>
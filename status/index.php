<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/lang.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/db.php';

$is_logged_in = isset($_SESSION['user_id']);

// ── Liste des services à surveiller ────────────────────────────────────────────
$my_services = [
    'Site Web'              => 'heberge.orinstone.deepstone.fr',
    'Panel de gestion'      => 'panel.orinstone.deepstone.fr',
    'Panel de Plesk'        => 'plesk.orinstone.deepstone.fr',
    'phpMyAdmin'            => 'php.orinstone.deepstone.fr',
    'Node OrinStone'        => 'node.orinstone.deepstone.fr',
    'Node DeepStone Global' => 'node.deepstone.fr'
];

$history_days = 90;
$status_data  = [];

foreach ($my_services as $name => $host) {
    $stmt = $pdo->prepare("SELECT check_date, is_online FROM service_uptime WHERE service_name = ? AND check_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY) ORDER BY check_date ASC");
    $stmt->execute([$name, $history_days]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $status_data[$name][$row['check_date']] = $row['is_online'];
    }
}

// ── Récupération des maintenances ──────────────────────────────────────────────
// Maintenances en cours
$ongoing_maintenances = $pdo->query("
    SELECT * FROM maintenance 
    WHERE is_active = 1 
      AND is_public = 1
      AND status IN ('scheduled', 'in_progress')
      AND NOW() BETWEEN start_date AND end_date
    ORDER BY 
        CASE severity 
            WHEN 'critical' THEN 1 
            WHEN 'warning' THEN 2 
            ELSE 3 
        END,
        start_date ASC
")->fetchAll();

// Maintenances à venir (prochains 14 jours)
$upcoming_maintenances = $pdo->query("
    SELECT * FROM maintenance 
    WHERE is_active = 1 
      AND is_public = 1
      AND status = 'scheduled'
      AND start_date > NOW()
      AND start_date <= DATE_ADD(NOW(), INTERVAL 14 DAY)
    ORDER BY start_date ASC
")->fetchAll();

// Maintenances récentes (terminées dans les 7 derniers jours)
$recent_maintenances = $pdo->query("
    SELECT * FROM maintenance 
    WHERE is_active = 1 
      AND is_public = 1
      AND status = 'completed'
      AND end_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ORDER BY end_date DESC
")->fetchAll();

// ── Calcul du statut global ────────────────────────────────────────────────────
$today_str = date('Y-m-d');
$total_services = count($my_services);
$services_online = 0;
$services_degraded = 0;

foreach ($my_services as $name => $host) {
    $current_online = isset($status_data[$name][$today_str]) ? (int)$status_data[$name][$today_str] : 1;
    if ($current_online === 1) {
        $services_online++;
    } else {
        $services_degraded++;
    }
}

// Déterminer le statut global
if ($services_degraded > 0) {
    $global_status = 'degraded';
    $global_status_label = t('status.global_degraded') ?? 'Certains services sont dégradés';
    $global_status_color = 'amber';
    $global_status_icon = 'fa-exclamation-triangle';
} elseif (!empty($ongoing_maintenances)) {
    $global_status = 'maintenance';
    $global_status_label = t('status.global_maintenance') ?? 'Maintenance en cours';
    $global_status_color = 'sky';
    $global_status_icon = 'fa-wrench';
} else {
    $global_status = 'operational';
    $global_status_label = t('status.global_operational') ?? 'Tous les systèmes sont opérationnels';
    $global_status_color = 'emerald';
    $global_status_icon = 'fa-check-circle';
}

// ── Fonction utilitaire ────────────────────────────────────────────────────────
function getLocalizedDate($date_str, $lang) {
    $timestamp = strtotime($date_str);
    $day = date('d', $timestamp);
    $month_lower = strtolower(date('M', $timestamp));
    $year = date('Y', $timestamp);
    
    $month_translated = t('month.' . $month_lower);
    
    if ($lang === 'en') {
        return $month_translated . ' ' . $day . ', ' . $year;
    } else {
        return $day . ' ' . $month_translated . ' ' . $year;
    }
}

function getSeverityConfig($severity) {
    $configs = [
        'info'     => ['color' => 'sky',     'icon' => 'fa-info-circle',         'label' => 'Information'],
        'warning'  => ['color' => 'amber',   'icon' => 'fa-exclamation-triangle','label' => 'Attention'],
        'critical' => ['color' => 'red',     'icon' => 'fa-radiation',           'label' => 'Critique'],
    ];
    return $configs[$severity] ?? $configs['info'];
}

function getTypeConfig($type) {
    $configs = [
        'planned'     => ['icon' => 'fa-calendar-check',  'label' => 'Planifiée'],
        'emergency'   => ['icon' => 'fa-bolt',            'label' => 'Urgence'],
        'improvement' => ['icon' => 'fa-arrow-up',        'label' => 'Amélioration'],
        'security'    => ['icon' => 'fa-shield-halved',   'label' => 'Sécurité'],
    ];
    return $configs[$type] ?? $configs['planned'];
}

function timeAgo($datetime, $lang) {
    $now = new DateTime();
    $target = new DateTime($datetime);
    $diff = $now->diff($target);
    
    if ($target > $now) {
        // Futur
        if ($diff->d > 0) return t('status.in_days') . ' ' . $diff->d . ' ' . t('status.days');
        if ($diff->h > 0) return t('status.in_hours') . ' ' . $diff->h . 'h';
        return t('status.soon') ?? 'Bientôt';
    } else {
        // Passé
        if ($diff->d > 0) return 'il y a ' . $diff->d . ' jour' . ($diff->d > 1 ? 's' : '');
        if ($diff->h > 0) return 'il y a ' . $diff->h . 'h';
        return 'à l\'instant';
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('status.title'); ?></title>
    
        <!-- Balises de base -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statut des Services - OrinHeberge | Monitoring en temps réel</title>
    <meta name="description" content="Consultez l'état en temps réel des services OrinHeberge. Monitoring 24/7 de nos serveurs VPS, Minecraft, PHP et Node.js.">
    <meta name="keywords" content="statut serveur, monitoring, état des services, uptime, disponibilité, OrinHeberge status, monitoring VPS, état Minecraft">
    <meta name="author" content="OrinHeberge">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="https://heberge.orinstone.deepstone.fr/status/">

    <!-- Open Graph / Facebook -->
    <meta property="og:locale" content="fr_FR">
    <meta property="og:type" content="website">
    <meta property="og:title" content="Statut des Services - OrinHeberge">
    <meta property="og:description" content="Consultez l'état en temps réel des services OrinHeberge. Monitoring 24/7 de nos serveurs VPS, Minecraft, PHP et Node.js.">
    <meta property="og:url" content="https://heberge.orinstone.deepstone.fr/status/">
    <meta property="og:site_name" content="OrinHeberge">
    <meta property="og:image" content="https://heberge.orinstone.deepstone.fr/favicon.png">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="OrinHeberge - Statut des services en temps réel">

    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:site" content="@OrinHeberge">
    <meta name="twitter:creator" content="@OrinHeberge">
    <meta name="twitter:title" content="Statut des Services - OrinHeberge">
    <meta name="twitter:description" content="Consultez l'état en temps réel des services OrinHeberge. Monitoring 24/7 de nos serveurs VPS, Minecraft, PHP et Node.js.">
    <meta name="twitter:image" content="https://heberge.orinstone.deepstone.fr/favicon.png">
    <meta name="twitter:image:alt" content="OrinHeberge - Statut des services en temps réel">

    <!-- Autres balises SEO -->
    <meta name="theme-color" content="#6366f1">
    <meta name="msapplication-TileColor" content="#6366f1">
    <link rel="icon" type="image/png" href="https://heberge.orinstone.deepstone.fr/favicon.png">
    <link rel="apple-touch-icon" href="https://heberge.orinstone.deepstone.fr/favicon.png">

    <!-- Schema.org JSON-LD (SEO avancé) -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "Service",
      "name": "OrinHeberge - Statut des Services",
      "provider": {
        "@type": "Organization",
        "name": "OrinHeberge",
        "url": "https://heberge.orinstone.deepstone.fr/"
      },
      "url": "https://heberge.orinstone.deepstone.fr/status/",
      "description": "Page de statut et monitoring en temps réel des services d'hébergement OrinHeberge."
    }
    </script>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" type="image/png" href="https://heberge.orinstone.deepstone.fr/favicon.ico">
    <style>
        :root { --sidebar: 240px; }
        * { box-sizing: border-box; }
        body {
            background-color: #0b0f19;
            color: #e2e8f0;
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            min-height: 100vh;
        }
        
        .sidebar {
            position: fixed; top: 0; left: 0; width: var(--sidebar); height: 100vh;
            background: #111318; border-right: 1px solid rgba(255,255,255,.06);
            display: flex; flex-direction: column; z-index: 40; overflow-y: auto;
        }
        .sidebar-logo { padding: 1.5rem 1.25rem 1rem; border-bottom: 1px solid rgba(255,255,255,.05); }
        .sidebar-nav { padding: .75rem; flex: 1; }
        .nav-item {
            display: flex; align-items: center; gap: .75rem; padding: .625rem .875rem;
            border-radius: .625rem; font-size: .82rem; font-weight: 500; color: #6b7280;
            transition: all .15s; text-decoration: none; margin-bottom: .15rem; border: 1px solid transparent;
        }
        .nav-item:hover { background: rgba(255,255,255,.04); color: #d1d5db; }
        .nav-item.active { background: rgba(56,189,248,.08); color: #38bdf8; border-color: rgba(56,189,248,.15); }
        .nav-item .icon { width: 1.1rem; text-align: center; font-size: .85rem; flex-shrink: 0; }
        .nav-section { font-size: .65rem; font-weight: 700; letter-spacing: .1em; color: #374151; text-transform: uppercase; padding: .75rem .875rem .35rem; }
        .nav-separator { height: 1px; background: rgba(255,255,255,.05); margin: .5rem .75rem; }
        .sidebar-footer { padding: .875rem 1rem; border-top: 1px solid rgba(255,255,255,.05); }

        .main-content { margin-left: var(--sidebar); min-height: 100vh; display: flex; flex-direction: column; }
        .topbar { background: #111318; border-bottom: 1px solid rgba(255,255,255,.06); padding: .875rem 1.75rem; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 30; }
        
        .glass { background: rgba(255, 255, 255, 0.03); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); }
        .gradient-text { background: linear-gradient(135deg, #38bdf8 0%, #0284c7 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        
        .tooltip { position: relative; }
        .tooltip .tooltip-text {
            visibility: hidden; position: absolute; bottom: 130%; left: 50%; transform: translateX(-50%);
            background-color: rgba(15, 23, 42, 0.95); border: 1px solid rgba(255, 255, 255, 0.08);
            padding: 8px 12px; border-radius: 8px; white-space: nowrap; z-index: 50; opacity: 0; transition: opacity 0.2s, bottom 0.2s;
        }
        .tooltip:hover .tooltip-text { visibility: visible; opacity: 1; bottom: 145%; }

        .mobile-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.6); z-index: 39; }

        @media(max-width:768px){
            .sidebar { transform: translateX(-100%); transition: transform .25s; }
            .sidebar.open { transform: translateX(0); }
            .mobile-overlay.open { display: block; }
            .main-content { margin-left: 0; }
        }

        /* Animation pulse pour le statut global */
        @keyframes pulse-ring {
            0% { transform: scale(0.8); opacity: 1; }
            100% { transform: scale(2); opacity: 0; }
        }
        .pulse-ring {
            position: absolute;
            inset: 0;
            border-radius: 50%;
            animation: pulse-ring 2s cubic-bezier(0.215, 0.61, 0.355, 1) infinite;
        }

        /* Timeline maintenance */
        .timeline-item { position: relative; padding-left: 2rem; }
        .timeline-item::before {
            content: '';
            position: absolute;
            left: 0.5rem;
            top: 1.5rem;
            bottom: -1rem;
            width: 2px;
            background: rgba(255,255,255,0.05);
        }
        .timeline-item:last-child::before { display: none; }
        .timeline-dot {
            position: absolute;
            left: 0;
            top: 0.25rem;
            width: 1.25rem;
            height: 1.25rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.6rem;
        }
    </style>
</head>
<body class="text-gray-200 min-h-screen">

    <?php include $_SERVER['DOCUMENT_ROOT'] . '/inc/clients_sidebar.php'; ?>
    <div id="mobileOverlay" class="mobile-overlay" onclick="toggleMenu()"></div>

    <div class="main-content">
        
        <header class="topbar md:hidden flex items-center justify-between px-4 py-3">
            <button onclick="toggleMenu()" class="text-gray-400 hover:text-white text-xl">
                <i class="fas fa-bars"></i>
            </button>
            <span class="font-bold text-white text-sm">OrinHeberge Status</span>
            <div class="w-5"></div>
        </header>

        <main class="max-w-5xl mx-auto w-full px-4 py-12 flex-grow">
            
            <!-- ═══════════════════════════════════════════════════════════════ -->
            <!-- EN-TÊTE -->
            <!-- ═══════════════════════════════════════════════════════════════ -->
            <div class="text-center mb-10">
                <h2 class="text-4xl font-extrabold tracking-tight text-white mb-3">
                    <?php echo t('status.heading'); ?> <span class="gradient-text"><?php echo t('status.heading2'); ?></span>
                </h2>
                <p class="text-gray-400 max-w-xl mx-auto text-sm sm:text-base leading-relaxed">
                    <?php echo t('status.subtitle'); ?>
                </p>
            </div>

            <!-- ═══════════════════════════════════════════════════════════════ -->
            <!-- STATUT GLOBAL -->
            <!-- ═══════════════════════════════════════════════════════════════ -->
            <div class="glass border border-<?php echo $global_status_color; ?>-500/20 rounded-2xl p-6 sm:p-8 mb-8 shadow-xl relative overflow-hidden">
                <div class="absolute inset-0 bg-gradient-to-r from-<?php echo $global_status_color; ?>-500/5 to-transparent pointer-events-none"></div>
                <div class="relative flex items-center gap-5">
                    <div class="relative shrink-0">
                        <div class="w-16 h-16 rounded-full bg-<?php echo $global_status_color; ?>-500/10 border-2 border-<?php echo $global_status_color; ?>-500/30 flex items-center justify-center">
                            <i class="fas <?php echo $global_status_icon; ?> text-<?php echo $global_status_color; ?>-400 text-2xl"></i>
                        </div>
                        <div class="pulse-ring bg-<?php echo $global_status_color; ?>-500/20"></div>
                    </div>
                    <div class="flex-1">
                        <h3 class="text-xl sm:text-2xl font-bold text-white mb-1">
                            <?php echo $global_status_label; ?>
                        </h3>
                        <p class="text-sm text-gray-400">
                            <?php echo $services_online; ?>/<?php echo $total_services; ?> <?php echo t('status.services_online') ?? 'services en ligne'; ?>
                            <?php if ($services_degraded > 0): ?>
                                · <span class="text-amber-400"><?php echo $services_degraded; ?> <?php echo t('status.degraded') ?? 'dégradé(s)' ?></span>
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="hidden sm:block text-right">
                        <div class="text-xs text-gray-500 mb-1"><?php echo t('status.last_check') ?? 'Dernière vérification' ?></div>
                        <div class="text-sm font-mono text-gray-300"><?php echo date('H:i'); ?></div>
                    </div>
                </div>
            </div>

            <!-- ═══════════════════════════════════════════════════════════════ -->
            <!-- MAINTENANCES EN COURS -->
            <!-- ═══════════════════════════════════════════════════════════════ -->
            <?php if (!empty($ongoing_maintenances)): ?>
            <div class="mb-8">
                <h3 class="flex items-center gap-2 text-lg font-bold text-white mb-4">
                    <span class="w-8 h-8 rounded-lg bg-sky-500/10 border border-sky-500/20 flex items-center justify-center">
                        <i class="fas fa-wrench text-sky-400 text-sm"></i>
                    </span>
                    <?php echo t('status.ongoing_maintenance') ?? 'Maintenance en cours'; ?>
                    <span class="ml-2 px-2 py-0.5 rounded-full text-xs font-semibold bg-sky-500/10 text-sky-400 border border-sky-500/20">
                        <?php echo count($ongoing_maintenances); ?>
                    </span>
                </h3>
                
                <div class="space-y-3">
                    <?php foreach ($ongoing_maintenances as $m): 
                        $sev = getSeverityConfig($m['severity']);
                        $type = getTypeConfig($m['type']);
                        $end_time = new DateTime($m['end_date']);
                        $now = new DateTime();
                        $remaining = $now->diff($end_time);
                    ?>
                    <div class="glass border border-<?php echo $sev['color']; ?>-500/20 rounded-xl p-5 shadow-lg">
                        <div class="flex items-start gap-4">
                            <div class="shrink-0 w-10 h-10 rounded-lg bg-<?php echo $sev['color']; ?>-500/10 border border-<?php echo $sev['color']; ?>-500/20 flex items-center justify-center">
                                <i class="fas <?php echo $sev['icon']; ?> text-<?php echo $sev['color']; ?>-400"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex flex-wrap items-center gap-2 mb-1">
                                    <h4 class="font-bold text-white"><?php echo htmlspecialchars($m['title']); ?></h4>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-<?php echo $sev['color']; ?>-500/10 text-<?php echo $sev['color']; ?>-400 border border-<?php echo $sev['color']; ?>-500/20">
                                        <?php echo $sev['label']; ?>
                                    </span>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-white/5 text-gray-400 border border-white/10">
                                        <i class="fas <?php echo $type['icon']; ?> mr-1"></i><?php echo $type['label']; ?>
                                    </span>
                                </div>
                                <p class="text-sm text-gray-400 mb-3"><?php echo htmlspecialchars($m['description']); ?></p>
                                <div class="flex flex-wrap items-center gap-4 text-xs text-gray-500">
                                    <span><i class="fas fa-clock mr-1"></i>
                                        <?php echo date('H:i', strtotime($m['start_date'])); ?> — <?php echo date('H:i', strtotime($m['end_date'])); ?>
                                    </span>
                                    <span class="text-<?php echo $sev['color']; ?>-400 font-semibold">
                                        <i class="fas fa-hourglass-half mr-1"></i>
                                        <?php 
                                        if ($remaining->h > 0) echo $remaining->h . 'h ' . $remaining->i . 'min restantes';
                                        else echo $remaining->i . 'min restantes';
                                        ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- ═══════════════════════════════════════════════════════════════ -->
            <!-- MAINTENANCES À VENIR -->
            <!-- ═══════════════════════════════════════════════════════════════ -->
            <?php if (!empty($upcoming_maintenances)): ?>
            <div class="mb-8">
                <h3 class="flex items-center gap-2 text-lg font-bold text-white mb-4">
                    <span class="w-8 h-8 rounded-lg bg-amber-500/10 border border-amber-500/20 flex items-center justify-center">
                        <i class="fas fa-calendar-alt text-amber-400 text-sm"></i>
                    </span>
                    <?php echo t('status.upcoming_maintenance') ?? 'Maintenances planifiées'; ?>
                    <span class="ml-2 px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-500/10 text-amber-400 border border-amber-500/20">
                        <?php echo count($upcoming_maintenances); ?>
                    </span>
                </h3>
                
                <div class="space-y-3">
                    <?php foreach ($upcoming_maintenances as $m): 
                        $sev = getSeverityConfig($m['severity']);
                        $type = getTypeConfig($m['type']);
                    ?>
                    <div class="glass border border-white/5 rounded-xl p-5 shadow-lg hover:border-<?php echo $sev['color']; ?>-500/20 transition">
                        <div class="flex items-start gap-4">
                            <div class="shrink-0 w-10 h-10 rounded-lg bg-<?php echo $sev['color']; ?>-500/10 border border-<?php echo $sev['color']; ?>-500/20 flex items-center justify-center">
                                <i class="fas <?php echo $sev['icon']; ?> text-<?php echo $sev['color']; ?>-400"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex flex-wrap items-center gap-2 mb-1">
                                    <h4 class="font-bold text-white"><?php echo htmlspecialchars($m['title']); ?></h4>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-white/5 text-gray-400 border border-white/10">
                                        <i class="fas <?php echo $type['icon']; ?> mr-1"></i><?php echo $type['label']; ?>
                                    </span>
                                </div>
                                <p class="text-sm text-gray-400 mb-3"><?php echo htmlspecialchars($m['description']); ?></p>
                                <div class="flex flex-wrap items-center gap-4 text-xs text-gray-500">
                                    <span>
                                        <i class="fas fa-calendar mr-1"></i>
                                        <?php echo getLocalizedDate($m['start_date'], $lang); ?>
                                    </span>
                                    <span>
                                        <i class="fas fa-clock mr-1"></i>
                                        <?php echo date('H:i', strtotime($m['start_date'])); ?> — <?php echo date('H:i', strtotime($m['end_date'])); ?>
                                    </span>
                                    <span class="text-amber-400 font-semibold">
                                        <i class="fas fa-hourglass-start mr-1"></i>
                                        <?php echo timeAgo($m['start_date'], $lang); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- ═══════════════════════════════════════════════════════════════ -->
            <!-- STATUT DES SERVICES -->
            <!-- ═══════════════════════════════════════════════════════════════ -->
            <div class="mb-8">
                <h3 class="flex items-center gap-2 text-lg font-bold text-white mb-4">
                    <span class="w-8 h-8 rounded-lg bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center">
                        <i class="fas fa-server text-emerald-400 text-sm"></i>
                    </span>
                    <?php echo t('status.services_status') ?? 'État des services'; ?>
                </h3>

                <div class="space-y-4">
                    <?php foreach ($my_services as $name => $host): 
                        $days_recorded = isset($status_data[$name]) ? count($status_data[$name]) : 0;
                        $days_online   = 0;
                        if ($days_recorded > 0) {
                            foreach ($status_data[$name] as $date => $online) {
                                if ((int)$online === 1) $days_online++;
                            }
                            $uptime_pct = round(($days_online / $days_recorded) * 100, 2);
                        } else {
                            $uptime_pct = 100.00;
                        }

                        $current_online = isset($status_data[$name][$today_str]) ? (int)$status_data[$name][$today_str] : 1;
                    ?>
                    <div class="glass border border-white/5 rounded-2xl p-5 sm:p-6 shadow-xl">
                        
                        <div class="flex justify-between items-start gap-4 mb-4">
                            <div>
                                <h4 class="text-lg font-bold text-white flex items-center gap-2">
                                    <?php echo htmlspecialchars($name); ?>
                                </h4>
                                <p class="text-xs text-gray-500 font-mono mt-0.5"><?php echo htmlspecialchars($host); ?></p>
                            </div>
                            
                            <div class="flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold tracking-wide shrink-0 <?php echo $current_online === 1 ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-red-500/10 text-red-400 border border-red-500/20'; ?>">
                                <span class="relative flex w-2 h-2">
                                    <?php if ($current_online === 1): ?>
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                    <?php endif; ?>
                                    <span class="relative inline-flex rounded-full h-2 w-2 <?php echo $current_online === 1 ? 'bg-emerald-400' : 'bg-red-400'; ?>"></span>
                                </span>
                                <?php echo $current_online === 1 ? t('status.operational') : t('status.disruption'); ?>
                            </div>
                        </div>

                        <div class="flex items-center gap-[3px] h-7 w-full my-3">
                            <?php for ($i = $history_days - 1; $i >= 0; $i--):
                                $date_str = date('Y-m-d', strtotime("-$i days"));
                                $display_date = getLocalizedDate($date_str, $lang);
                                
                                $day_status  = isset($status_data[$name][$date_str]) ? (int)$status_data[$name][$date_str] : 1;
                                $bar_color   = $day_status === 1 ? 'bg-emerald-500 hover:bg-emerald-400' : 'bg-red-500 hover:bg-red-400';
                                $log_text    = $day_status === 1 ? t('status.log_ok') : t('status.log_ko');
                                $log_color   = $day_status === 1 ? 'text-emerald-400' : 'text-red-400';
                            ?>
                            <div class="tooltip flex-1 h-full <?php echo $bar_color; ?> rounded-[2px] transition cursor-pointer">
                                <div class="tooltip-text text-xs space-y-1 shadow-2xl">
                                    <div class="font-bold text-white"><?php echo $display_date; ?></div>
                                    <div class="<?php echo $log_color; ?>"><?php echo $log_text; ?></div>
                                </div>
                            </div>
                            <?php endfor; ?>
                        </div>

                        <div class="flex justify-between items-center text-[11px] text-gray-500 font-medium">
                            <span>90 <?php echo t('status.days'); ?> <?php echo t('status.days_ago'); ?></span>
                            <span class="w-16 h-[1px] bg-white/5 flex-grow mx-4 hidden sm:inline-block"></span>
                            <span class="text-gray-400 font-semibold bg-white/[0.02] border border-white/5 px-2 py-0.5 rounded-md">
                                <?php echo $uptime_pct; ?>% <?php echo t('status.uptime_pct'); ?>
                            </span>
                            <span class="w-16 h-[1px] bg-white/5 flex-grow mx-4 hidden sm:inline-block"></span>
                            <span><?php echo t('status.today'); ?></span>
                        </div>

                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- ═══════════════════════════════════════════════════════════════ -->
            <!-- MAINTENANCES RÉCENTES -->
            <!-- ═══════════════════════════════════════════════════════════════ -->
            <?php if (!empty($recent_maintenances)): ?>
            <div class="mb-8">
                <h3 class="flex items-center gap-2 text-lg font-bold text-white mb-4">
                    <span class="w-8 h-8 rounded-lg bg-gray-500/10 border border-gray-500/20 flex items-center justify-center">
                        <i class="fas fa-history text-gray-400 text-sm"></i>
                    </span>
                    <?php echo t('status.recent_maintenance') ?? 'Maintenances récentes'; ?>
                </h3>
                
                <div class="glass border border-white/5 rounded-xl p-5">
                    <div class="space-y-4">
                        <?php foreach ($recent_maintenances as $m): 
                            $type = getTypeConfig($m['type']);
                        ?>
                        <div class="timeline-item">
                            <div class="timeline-dot bg-emerald-500/10 border border-emerald-500/30 text-emerald-400">
                                <i class="fas fa-check"></i>
                            </div>
                            <div>
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="font-semibold text-white text-sm"><?php echo htmlspecialchars($m['title']); ?></span>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-white/5 text-gray-400 border border-white/10">
                                        <i class="fas <?php echo $type['icon']; ?> mr-1"></i><?php echo $type['label']; ?>
                                    </span>
                                </div>
                                <p class="text-xs text-gray-500">
                                    <?php echo getLocalizedDate($m['end_date'], $lang); ?> · <?php echo date('H:i', strtotime($m['end_date'])); ?>
                                </p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- ═══════════════════════════════════════════════════════════════ -->
            <!-- RSS / ABONNEMENT -->
            <!-- ═══════════════════════════════════════════════════════════════ -->
            <div class="glass border border-white/5 rounded-2xl p-6 text-center">
                <div class="w-12 h-12 mx-auto mb-3 rounded-full bg-sky-500/10 border border-sky-500/20 flex items-center justify-center">
                    <i class="fas fa-bell text-sky-400"></i>
                </div>
                <h4 class="font-bold text-white mb-1"><?php echo t('status.subscribe_title') ?? 'Restez informé'; ?></h4>
                <p class="text-sm text-gray-400 mb-4"><?php echo t('status.subscribe_desc') ?? 'Rejoignez notre Discord pour recevoir les notifications en temps réel.'; ?></p>
                <a href="https://heberge.orinstone.deepstone.fr/discord/" target="_blank"
                   class="inline-flex items-center gap-2 bg-[#5865F2] hover:bg-[#4752C4] text-white px-5 py-2.5 rounded-xl font-semibold text-sm transition">
                    <i class="fab fa-discord"></i>
                    <?php echo t('discord.join') ?? 'Rejoindre Discord'; ?>
                </a>
            </div>

        </main>

        <?php include $_SERVER['DOCUMENT_ROOT'] . '/inc/footer.php'; ?>
    </div>

</body>
</html>
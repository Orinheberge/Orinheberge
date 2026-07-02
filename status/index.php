<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/lang.php';

$db_config = ['host' => 'localhost', 'name' => 's43_orinheberge', 'user' => 'root', 'pass' => '1504'];
try {
    $pdo = new PDO("mysql:host={$db_config['host']};dbname={$db_config['name']};charset=utf8mb4", $db_config['user'], $db_config['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (Exception $e) {
    die('Erreur serveur.');
}

$is_logged_in = isset($_SESSION['user_id']);

$my_services = [
    'Site Web'              => 'heberge.orinstone.deepstone.fr',
    'Panel de gestion'      => 'panel.orinstone.deepstone.fr',
    'Panel de Plesk'      => 'plesk.orinstone.deepstone.fr',
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

// Fonction utilitaire pour formater la date avec les mois traduits
function getLocalizedDate($date_str, $lang) {
    $timestamp = strtotime($date_str);
    $day = date('d', $timestamp);
    $month_lower = strtolower(date('M', $timestamp)); // ex: jan, feb, mar...
    $year = date('Y', $timestamp);
    
    // Clé de traduction correspondante (ex: month.jan)
    $month_translated = t('month.' . $month_lower);
    
    if ($lang === 'en') {
        return $month_translated . ' ' . $day . ', ' . $year;
    } else {
        return $day . ' ' . $month_translated . ' ' . $year;
    }
}

include $_SERVER['DOCUMENT_ROOT'] . '/inc/clients_sidebar.php';
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('status.title'); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
        .badge{display:inline-flex;align-items:center;gap:.3rem;padding:.2rem .65rem;border-radius:9999px;font-size:.72rem;font-weight:600;}
        .badge-green{background:rgba(34,197,94,.1);color:#22c55e;border:1px solid rgba(34,197,94,.2);}
        .badge-orange{background:rgba(249,115,22,.1);color:#f97316;border:1px solid rgba(249,115,22,.2);}
        .badge-red{background:rgba(239,68,68,.1);color:#ef4444;border:1px solid rgba(239,68,68,.2);}
        .badge-gray{background:rgba(107,114,128,.1);color:#9ca3af;border:1px solid rgba(107,114,128,.2);}
        .badge-blue{background:rgba(56,189,248,.1);color:#38bdf8;border:1px solid rgba(56,189,248,.2);}
        .ticket-row{display:flex;align-items:center;gap:1rem;padding:1rem 1.25rem;border-bottom:1px solid rgba(255,255,255,.04);cursor:pointer;transition:background .15s;text-decoration:none;}
        .ticket-row:last-child{border-bottom:none;}
        .ticket-row:hover{background:rgba(255,255,255,.02);}
        .ticket-dot{width:.5rem;height:.5rem;border-radius:50%;flex-shrink:0;}
        input,textarea,select{background:#1e2330;border:1px solid rgba(255,255,255,.08);color:#e2e8f0;border-radius:.625rem;padding:.6rem .875rem;font-size:.83rem;width:100%;outline:none;transition:border-color .15s;}
        input:focus,textarea:focus,select:focus{border-color:rgba(56,189,248,.4);}
        .filter-btn{padding:.35rem .875rem;border-radius:9999px;font-size:.75rem;font-weight:600;border:1px solid rgba(255,255,255,.07);color:#6b7280;cursor:pointer;transition:all .15s;text-decoration:none;white-space:nowrap;}
        .filter-btn:hover{background:rgba(255,255,255,.05);color:#d1d5db;}
        .filter-btn.active{background:rgba(56,189,248,.1);border-color:rgba(56,189,248,.3);color:#38bdf8;}
        .mobile-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:39;}
        @media(max-width:768px){
            .sidebar{transform:translateX(-100%);transition:transform .25s;}
            .sidebar.open{transform:translateX(0);}
            .mobile-overlay.open{display:block;}
            .main-content{margin-left:0;}
            .topbar,.content{padding:.875rem 1rem;}
        }
        body {
            background-color: #0b0f19;
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif;
        }
        .glass {
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
        }
        .gradient-text {
            background: linear-gradient(135deg, #38bdf8 0%, #0284c7 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .tooltip {
            position: relative;
        }
        .tooltip .tooltip-text {
            visibility: hidden;
            position: absolute;
            bottom: 130%;
            left: 50%;
            transform: translateX(-50%);
            background-color: rgba(15, 23, 42, 0.95);
            border: 1px solid rgba(255, 255, 255, 0.08);
            padding: 8px 12px;
            border-radius: 8px;
            white-space: nowrap;
            z-index: 50;
            opacity: 0;
            transition: opacity 0.2s, bottom 0.2s;
        }
        .tooltip:hover .tooltip-text {
            visibility: visible;
            opacity: 1;
            bottom: 145%;
        }
    </style>
</head>
<body class="text-gray-200 min-h-screen flex flex-col justify-between">

    <?php 
    $active_nav = 'status'; 
    include $_SERVER['DOCUMENT_ROOT'] . '/inc/navbar.php'; 
    ?>

    <main class="max-w-4xl mx-auto w-full px-4 py-12 flex-grow">
        
        <div class="text-center mb-12">
            <h2 class="text-4xl font-extrabold tracking-tight text-white mb-3">
                <?php echo t('status.heading'); ?> <span class="gradient-text"><?php echo t('status.heading2'); ?></span>
            </h2>
            <p class="text-gray-400 max-w-xl mx-auto text-sm sm:text-base leading-relaxed">
                <?php echo t('status.subtitle'); ?>
            </p>
        </div>

        <div class="space-y-5">
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

                $today_str = date('Y-m-d');
                $current_online = isset($status_data[$name][$today_str]) ? (int)$status_data[$name][$today_str] : 1;
            ?>
            <div class="glass border border-white/5 rounded-2xl p-5 sm:p-6 shadow-xl">
                
                <div class="flex justify-between items-start gap-4 mb-4">
                    <div>
                        <h3 class="text-lg font-bold text-white flex items-center gap-2">
                            <?php echo htmlspecialchars($name); ?>
                        </h3>
                        <p class="text-xs text-gray-500 font-mono mt-0.5"><?php echo htmlspecialchars($host); ?></p>
                    </div>
                    
                    <div class="flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold tracking-wide shrink-0 <?php echo $current_online === 1 ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-red-500/10 text-red-400 border border-red-500/20'; ?>">
                        <span class="w-1.5 h-1.5 rounded-full animate-pulse <?php echo $current_online === 1 ? 'bg-emerald-400' : 'bg-red-400'; ?>"></span>
                        <?php echo $current_online === 1 ? t('status.operational') : t('status.disruption'); ?>
                    </div>
                </div>

                <div class="flex items-center gap-[3px] h-7 w-full my-3">
                    <?php for ($i = $history_days - 1; $i >= 0; $i--):
                        $date_str = date('Y-m-d', strtotime("-$i days"));
                        
                        // Utilisation de la fonction personnalisée pour traduire le mois
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
    </main>

    <?php include $_SERVER['DOCUMENT_ROOT'] . '/inc/footer.php'; ?>
   <div class="fixed bottom-6 right-6 z-50">
    <a href="https://heberge.orinstone.deepstone.fr/discord/" target="_blank"
       class="bg-[#5865F2] hover:bg-[#4752C4] transition text-white px-5 py-4 rounded-full font-bold flex items-center gap-2 shadow-2xl hover:scale-105 transform duration-200">
        <i class="fab fa-discord text-xl"></i>
        <span class="hidden sm:inline text-sm"><?php echo t('discord.help'); ?></span>
    </a>
</div>

    <script>
        function toggleMenu() {
            const menu = document.getElementById('mobileMenu');
            menu.classList.toggle('hidden');
        }
    </script>
</body>
</html>

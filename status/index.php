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

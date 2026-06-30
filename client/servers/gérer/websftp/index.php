<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

/*
|--------------------------------------------------------------------------
| SECURITY
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['user_id'])) {
    header("Location: /login/");
    exit();
}

if (!isset($_GET['uuid'])) {
    die("UUID manquant");
}

$uuid = $_GET['uuid'];

$panel = "https://panel.orinstone.deepstone.fr";

/*
|--------------------------------------------------------------------------
| API KEY
|--------------------------------------------------------------------------
*/

$api_key = "ptlc_MfJSOUID0bnTgFCmm5VvYMML2jKUUA5RFZ2n2MeZCSU";

$headers = [
    "Authorization: Bearer $api_key",
    "Accept: application/vnd.pterodactyl.v1+json",
    "Content-Type: application/json"
];

/*
|--------------------------------------------------------------------------
| DATABASE
|--------------------------------------------------------------------------
*/

try {
    $pdo = new PDO(
        "mysql:host=127.0.0.1;port=3306;dbname=s43_orinheberge;charset=utf8mb4",
        "root",
        "1504",
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]
    );
} catch(PDOException $e) {
    die($e->getMessage());
}

/*
|--------------------------------------------------------------------------
| SERVER CHECK
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT *
    FROM orders
    WHERE user_id = ?
    AND uuid = ?
");

$stmt->execute([
    $_SESSION['user_id'],
    $uuid
]);

$server = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$server) {
    die("Serveur introuvable");
}

$short = substr($uuid, 0, 8);
$directory = $_GET['dir'] ?? "/";

/*
|--------------------------------------------------------------------------
| CREATE FOLDER
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_folder'])) {
    $folder = trim($_POST['folder_name']);

    if ($folder !== "") {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $panel . "/api/client/servers/" . $short . "/files/create-folder",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode([
                "root" => $directory,
                "name" => $folder
            ]),
            CURLOPT_HTTPHEADER => $headers
        ]);
        curl_exec($ch);
    }

    header("Location: ?uuid=$uuid&dir=" . urlencode($directory));
    exit();
}

/*
|--------------------------------------------------------------------------
| DELETE FILE
|--------------------------------------------------------------------------
*/

if (isset($_GET['delete'])) {
    $target = $_GET['delete'];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $panel . "/api/client/servers/" . $short . "/files/delete",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            "root" => dirname($target),
            "files" => [
                basename($target)
            ]
        ]),
        CURLOPT_HTTPHEADER => $headers
    ]);
    curl_exec($ch);

    header("Location: ?uuid=$uuid&dir=" . urlencode($directory));
    exit();
}

/*
|--------------------------------------------------------------------------
| RENAME
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rename_file'])) {
    $from = $_POST['old_name'];
    $to = trim($_POST['new_name']);

    if ($to !== "") {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $panel . "/api/client/servers/" . $short . "/files/rename",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode([
                "root" => dirname($from),
                "files" => [
                    [
                        "from" => basename($from),
                        "to" => $to
                    ]
                ]
            ]),
            CURLOPT_HTTPHEADER => $headers
        ]);
        curl_exec($ch);
    }

    header("Location: ?uuid=$uuid&dir=" . urlencode($directory));
    exit();
}

/*
|--------------------------------------------------------------------------
| SAVE FILE
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_file'])) {
    $file = $_POST['file_path'];
    $content = $_POST['content'];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $panel . "/api/client/servers/" . $short . "/files/write?file=" . urlencode($file),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => $content,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer $api_key",
            "Content-Type: text/plain",
            "Accept: application/vnd.pterodactyl.v1+json"
        ]
    ]);
    curl_exec($ch);

    header("Location: ?uuid=$uuid&dir=" . urlencode(dirname($file)));
    exit();
}

/*
|--------------------------------------------------------------------------
| UPLOAD
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['upload_file'])) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $panel . "/api/client/servers/" . $short . "/files/upload",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers
    ]);

    $uploadData = json_decode(curl_exec($ch), true);

    if (isset($uploadData['attributes']['url'])) {
        $uploadUrl = $uploadData['attributes']['url'];

        $file = new CURLFile(
            $_FILES['upload_file']['tmp_name'],
            $_FILES['upload_file']['type'],
            $_FILES['upload_file']['name']
        );

        $post = [
            "files" => $file
        ];

        $up = curl_init();
        curl_setopt_array($up, [
            CURLOPT_URL => $uploadUrl . "&directory=" . urlencode($directory),
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $post,
            CURLOPT_RETURNTRANSFER => true
        ]);
        curl_exec($up);
    }

    header("Location: ?uuid=$uuid&dir=" . urlencode($directory));
    exit();
}

/*
|--------------------------------------------------------------------------
| FILE CONTENT
|--------------------------------------------------------------------------
*/

$fileContent = "";

if (isset($_GET['edit'])) {
    $file = $_GET['edit'];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $panel . "/api/client/servers/" . $short . "/files/contents?file=" . urlencode($file),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers
    ]);
    $fileContent = curl_exec($ch);
}

/*
|--------------------------------------------------------------------------
| FILE LIST
|--------------------------------------------------------------------------
*/

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $panel . "/api/client/servers/" . $short . "/files/list?directory=" . urlencode($directory),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => $headers
]);
$response = curl_exec($ch);

$data = json_decode($response, true);

/*
|--------------------------------------------------------------------------
| CODEMIRROR MODE
|--------------------------------------------------------------------------
*/

$editFile = $_GET['edit'] ?? '';
$extension = strtolower(pathinfo($editFile, PATHINFO_EXTENSION));
$mode = "text/plain";

switch($extension){
    case "js":
        $mode = "javascript";
        break;
    case "html":
    case "htm":
        $mode = "htmlmixed";
        break;
    case "css":
        $mode = "css";
        break;
    case "php":
        $mode = "application/x-httpd-php";
        break;
    case "json":
        $mode = "application/json";
        break;
    case "yml":
    case "yaml":
        $mode = "yaml";
        break;
    case "properties":
        $mode = "properties";
        break;
    case "xml":
        $mode = "xml";
        break;
    case "toml":
        $mode = "toml";
        break;
    case "sh":
    case "bash":
        $mode = "shell";
        break;
    default:
        $mode = "text/plain";
}



?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WebSFTP | OrinHeberge</title>
    
    <link class="rounded-full" rel="icon" type="image/png" href="https://heberge.orinstone.deepstone.fr/favicon.ico">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/theme/material-darker.min.css">
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/javascript/javascript.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/xml/xml.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/css/css.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/htmlmixed/htmlmixed.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/php/php.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/yaml/yaml.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/properties/properties.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/toml/toml.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/shell/shell.min.js"></script>

    <style>
        body {
            background: radial-gradient(circle at top left, #1e293b, #020617);
            scroll-behavior: smooth;
        }
        .glass {
            background: rgba(255,255,255,0.04);
            backdrop-filter: blur(14px);
            border: 1px solid rgba(255,255,255,0.08);
        }
        .gradient-text {
            background: linear-gradient(90deg, #38bdf8, #818cf8);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .mobile-menu {
            display: none;
        }
        .mobile-menu.active {
            display: block;
        }
        .CodeMirror {
            height: 650px;
            border-radius: 16px;
            font-size: 14px;
            border: 1px solid rgba(255,255,255,.08);
        }
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

    <div class="max-w-7xl mx-auto py-10 px-6 flex-grow w-full">
        <div class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-4xl font-black text-sky-400">WebSFTP</h1>
                <p class="text-gray-400 text-sm mt-1">
                    <?= htmlspecialchars($server['service_name']) ?>
                </p>
            </div>
            <a href="/client/servers/" class="bg-sky-600 hover:bg-sky-500 px-5 py-2 rounded-xl font-bold transition">
                Retour
            </a>
        </div>

        <div class="glass rounded-2xl p-6 mb-5">
            <div class="text-sm text-gray-300 mb-4 font-mono">
                📂 <?= htmlspecialchars($directory) ?>
            </div>

            <form method="POST" enctype="multipart/form-data" class="flex flex-wrap items-center gap-3">
                <input type="file" name="upload_file" required class="text-sm block text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-white/5 file:text-white hover:file:bg-white/10 file:transition">
                <button class="bg-emerald-600 hover:bg-emerald-500 px-4 py-2 rounded-xl text-sm font-bold transition">
                    Upload
                </button>
            </form>

            <form method="POST" class="mt-4 flex gap-3">
                <input type="text" name="folder_name" placeholder="Nom du dossier" required class="bg-black/40 border border-white/10 px-4 py-2 rounded-xl text-sm w-full focus:outline-none focus:border-sky-500">
                <button name="create_folder" class="bg-sky-600 hover:bg-sky-500 px-4 py-2 rounded-xl text-sm font-bold transition whitespace-nowrap">
                    Créer dossier
                </button>
            </form>
        </div>

        <div class="glass rounded-2xl overflow-hidden">
            <?php
            if (!isset($data['data'])) {
                echo '<div class="p-6 text-red-400">Impossible de charger les fichiers du serveur.</div>';
            } else {
                foreach ($data['data'] as $file) {
                    $attr = $file['attributes'];
                    $name = $attr['name'];
                    $isFile = $attr['is_file'];
                    $size = $attr['size'];
                    $path = rtrim($directory, '/') . '/' . $name;

                    echo '<div class="flex flex-wrap items-center justify-between p-4 border-b border-white/5 hover:bg-white/5 transition gap-4">';
                        echo '<div class="flex items-center gap-3">';
                            if ($isFile) {
                                echo '<span>📄</span>';
                                echo '<a href="?uuid=' . urlencode($uuid) . '&dir=' . urlencode($directory) . '&edit=' . urlencode($path) . '" class="hover:text-sky-400 font-medium transition">' . htmlspecialchars($name) . '</a>';
                            } else {
                                echo '<span>📁</span>';
                                echo '<a href="?uuid=' . urlencode($uuid) . '&dir=' . urlencode($path) . '" class="hover:text-sky-400 font-medium transition">' . htmlspecialchars($name) . '</a>';
                            }
                        echo '</div>';

                        echo '<div class="flex items-center gap-3 ml-auto flex-wrap sm:flex-nowrap">';
                            echo '<div class="text-xs text-gray-500 min-w-[70px] text-right">';
                                if ($isFile) {
                                    echo round($size / 1024, 2) . ' KB';
                                } else {
                                    echo 'Dossier';
                                }
                            echo '</div>';

                            echo '<form method="POST" class="flex gap-2">';
                                echo '<input type="hidden" name="old_name" value="' . htmlspecialchars($path) . '">';
                                echo '<input type="text" name="new_name" placeholder="Renommer" required class="bg-black/30 border border-white/5 px-2 py-1 rounded text-xs w-28 focus:outline-none focus:border-yellow-500">';
                                echo '<button name="rename_file" class="bg-yellow-600/80 hover:bg-yellow-500 px-2 py-1 rounded text-xs font-bold transition">Rename</button>';
                            echo '</form>';

                            echo '<a href="?uuid=' . urlencode($uuid) . '&dir=' . urlencode($directory) . '&delete=' . urlencode($path) . '" onclick="return confirm(\'Supprimer définitivement ?\')" class="bg-red-600/80 hover:bg-red-500 px-2 py-1 rounded text-xs font-bold transition">Delete</a>';
                        echo '</div>';
                    echo '</div>';
                }
            }
            ?>
        </div>

        <?php if(isset($_GET['edit'])): ?>
            <div class="glass rounded-2xl p-5 mt-6 animate-fadeIn">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-2xl font-black text-sky-400">Éditeur de Code</h2>
                    <div class="text-xs font-mono bg-black/30 px-3 py-1.5 rounded-lg text-gray-400">
                        <?= htmlspecialchars(basename($_GET['edit'])) ?>
                    </div>
                </div>

                <form method="POST">
                    <input type="hidden" name="file_path" value="<?= htmlspecialchars($_GET['edit']) ?>">
                    <textarea id="editor" name="content"><?= htmlspecialchars($fileContent) ?></textarea>

                    <div class="flex gap-3 mt-4">
                        <button name="save_file" class="bg-emerald-600 hover:bg-emerald-500 px-5 py-2 rounded-xl font-bold transition">
                            💾 Sauvegarder
                        </button>
                        <a href="?uuid=<?= urlencode($uuid) ?>&dir=<?= urlencode($directory) ?>" class="bg-red-600 hover:bg-red-500 px-5 py-2 rounded-xl font-bold transition">
                            Fermer
                        </a>
                    </div>
                </form>
            </div>

            <script>
                const editor = CodeMirror.fromTextArea(
                    document.getElementById("editor"),
                    {
                        mode: "<?= $mode ?>",
                        theme: "material-darker",
                        lineNumbers: true,
                        lineWrapping: false,
                        indentUnit: 4,
                        tabSize: 4,
                        autoCloseTags: true,
                        matchBrackets: true,
                        styleActiveLine: true
                    }
                );
            </script>
        <?php endif; ?>
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

    <div class="fixed bottom-6 right-6 z-50">
        <a href="https://heberge.orinstone.deepstone.fr/discord/" target="_blank" class="bg-[#5865F2] hover:bg-[#4752C4] transition text-white px-5 py-3.5 rounded-full font-bold flex items-center gap-2 shadow-2xl hover:scale-105 transform duration-200">
            <i class="fab fa-discord text-xl"></i>
            <span class="hidden sm:inline text-sm">Besoin d'aide ? Discord</span>
        </a>
    </div>

    <script>
        function toggleMenu() {
            const menu = document.getElementById('mobileMenu');
            menu.classList.toggle('active');
        }
    </script>
</body>
</html>
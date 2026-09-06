<?php
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/lang.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| SYSTEME DE TRADUCTION
|--------------------------------------------------------------------------
*/
$translations = [
    'websftp.title'                 => ['fr' => 'Gestionnaire de Fichiers', 'en' => 'File Manager', 'de' => 'Dateimanager'],
    'websftp.breadcrumb.home'       => ['fr' => 'Accueil', 'en' => 'Home', 'de' => 'Startseite'],
    'websftp.upload.placeholder'    => ['fr' => 'Cliquer pour sélectionner...', 'en' => 'Click to select...', 'de' => 'Auswählen...'],
    'websftp.upload.btn'            => ['fr' => 'Envoyer', 'en' => 'Upload', 'de' => 'Hochladen'],
    'websftp.folder.placeholder'    => ['fr' => 'Nom du dossier', 'en' => 'Folder name', 'de' => 'Ordnername'],
    'websftp.action.compress'       => ['fr' => 'Archiver (.tar.gz)', 'en' => 'Compress (.tar.gz)', 'de' => 'Komprimieren'],
    'websftp.table.name'            => ['fr' => 'Nom', 'en' => 'Name', 'de' => 'Name'],
    'websftp.table.size'            => ['fr' => 'Taille', 'en' => 'Size', 'de' => 'Größe'],
    'websftp.table.actions'         => ['fr' => 'Actions', 'en' => 'Actions', 'de' => 'Aktionen'],
    'websftp.empty'                 => ['fr' => 'Ce dossier est vide.', 'en' => 'This folder is empty.', 'de' => 'Dieser Ordner ist leer.'],
    'websftp.action.download'       => ['fr' => 'Télécharger', 'en' => 'Download', 'de' => 'Herunterladen'],
    'websftp.action.rename'         => ['fr' => 'Renommer', 'en' => 'Rename', 'de' => 'Umbenennen'],
    'websftp.action.delete'         => ['fr' => 'Supprimer', 'en' => 'Delete', 'de' => 'Löschen'],
    'websftp.editor.title'          => ['fr' => 'Édition', 'en' => 'Editing', 'de' => 'Bearbeiten'],
    'websftp.editor.save'           => ['fr' => 'Sauvegarder', 'en' => 'Save', 'de' => 'Speichern'],
    'websftp.editor.close'          => ['fr' => 'Fermer', 'en' => 'Close', 'de' => 'Schließen'],
];

$current_lang = $_SESSION['lang'] ?? 'fr';

function __t(string $key): string {
    global $translations, $current_lang;
    if (isset($translations[$key])) {
        $entry = $translations[$key];
        if (is_array($entry)) {
            return $entry[$current_lang] ?? $entry['en'] ?? $key;
        }
        return (string)$entry;
    }
    return $key;
}

/*
|--------------------------------------------------------------------------
| SECURITY & INIT
|--------------------------------------------------------------------------
*/
if (!isset($_SESSION['user_id'])) {
    header("Location: /login/");
    exit();
}

$uuid = $_GET['uuid'] ?? null;
if (!$uuid) {
    http_response_code(400);
    die("UUID manquant");
}

try {
    $pdo = new PDO(
        "mysql:host=localhost;dbname=s43_orinheberge;charset=utf8mb4",
        "root", "1504",
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (PDOException $e) {
    http_response_code(500);
    die("Erreur critique BDD.");
}

$cfg = [];
try {
    foreach ($pdo->query('SELECT `key`, `value` FROM settings') as $row) {
        $cfg[$row['key']] = $row['value'];
    }
} catch (Exception $e) { $cfg = []; }

$panel   = $cfg['panel_url'] ?? 'https://panel.orinstone.deepstone.fr';
$api_key = $cfg['api_key_client'] ?? '';
if (empty($api_key)) die("Configuration API manquante.");

$headers = [
    "Authorization: Bearer $api_key",
    "Accept: application/vnd.pterodactyl.v1+json",
    "Content-Type: application/json"
];

$stmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? AND uuid = ? LIMIT 1");
$stmt->execute([$_SESSION['user_id'], $uuid]);
$server = $stmt->fetch();
if (!$server) { http_response_code(403); die("Accès refusé."); }

$short     = substr($uuid, 0, 8);
$directory = $_GET['dir'] ?? "/";
$redirectUrl = "?uuid=" . urlencode($uuid) . "&dir=" . urlencode($directory);

/*
|--------------------------------------------------------------------------
| ACTIONS
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_folder') {
    $folder = trim($_POST['folder_name'] ?? '');
    if ($folder !== "") {
        $ch = curl_init();
        curl_setopt_array($ch, [CURLOPT_URL => "$panel/api/client/servers/$short/files/create-folder", CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode(["root" => $directory, "name" => $folder]), CURLOPT_HTTPHEADER => $headers]);
        curl_exec($ch); curl_close($ch);
    }
    header("Location: $redirectUrl"); exit();
}

if (isset($_GET['delete'])) {
    $target = $_GET['delete'];
    $ch = curl_init();
    curl_setopt_array($ch, [CURLOPT_URL => "$panel/api/client/servers/$short/files/delete", CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode(["root" => dirname($target), "files" => [basename($target)]]), CURLOPT_HTTPHEADER => $headers]);
    curl_exec($ch); curl_close($ch);
    header("Location: $redirectUrl"); exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'rename') {
    $from = $_POST['old_name'] ?? ''; $to = trim($_POST['new_name'] ?? '');
    if ($to !== "" && $from !== "") {
        $ch = curl_init();
        curl_setopt_array($ch, [CURLOPT_URL => "$panel/api/client/servers/$short/files/rename", CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode(["root" => dirname($from), "files" => [["from" => basename($from), "to" => $to]]]), CURLOPT_HTTPHEADER => $headers]);
        curl_exec($ch); curl_close($ch);
    }
    header("Location: $redirectUrl"); exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_file') {
    $file = $_POST['file_path'] ?? ''; $content = $_POST['content'] ?? '';
    if ($file) {
        $ch = curl_init();
        curl_setopt_array($ch, [CURLOPT_URL => "$panel/api/client/servers/$short/files/write?file=" . urlencode($file), CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => "POST", CURLOPT_POSTFIELDS => $content, CURLOPT_HTTPHEADER => ["Authorization: Bearer $api_key", "Content-Type: text/plain", "Accept: application/vnd.pterodactyl.v1+json"]]);
        curl_exec($ch); curl_close($ch);
    }
    header("Location: ?uuid=" . urlencode($uuid) . "&dir=" . urlencode(dirname($file))); exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['upload_file'])) {
    $ch = curl_init();
    curl_setopt_array($ch, [CURLOPT_URL => "$panel/api/client/servers/$short/files/upload", CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $headers]);
    $response = curl_exec($ch); curl_close($ch);
    $uploadData = json_decode($response, true);
    if (isset($uploadData['attributes']['url'])) {
        $up = curl_init();
        curl_setopt_array($up, [CURLOPT_URL => $uploadData['attributes']['url'] . "&directory=" . urlencode($directory), CURLOPT_POST => true, CURLOPT_POSTFIELDS => ["files" => new CURLFile($_FILES['upload_file']['tmp_name'], $_FILES['upload_file']['type'], $_FILES['upload_file']['name'])], CURLOPT_RETURNTRANSFER => true]);
        curl_exec($up); curl_close($up);
    }
    header("Location: $redirectUrl"); exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'compress') {
    $ch = curl_init();
    curl_setopt_array($ch, [CURLOPT_URL => "$panel/api/client/servers/$short/files/compress", CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode(["root" => $directory, "files" => ["."]]), CURLOPT_HTTPHEADER => $headers]);
    curl_exec($ch); curl_close($ch);
    header("Location: $redirectUrl"); exit();
}

/*
|--------------------------------------------------------------------------
| DATA FETCHING
|--------------------------------------------------------------------------
*/
$fileContent = "";
$editFile = $_GET['edit'] ?? null;
if ($editFile) {
    $ch = curl_init();
    curl_setopt_array($ch, [CURLOPT_URL => "$panel/api/client/servers/$short/files/contents?file=" . urlencode($editFile), CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $headers]);
    $fileContent = curl_exec($ch); curl_close($ch);
}

$ch = curl_init();
curl_setopt_array($ch, [CURLOPT_URL => "$panel/api/client/servers/$short/files/list?directory=" . urlencode($directory), CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $headers]);
$response = curl_exec($ch); curl_close($ch);
$data = json_decode($response, true);
$files = $data['data'] ?? [];

$extension = strtolower(pathinfo($editFile ?? '', PATHINFO_EXTENSION));
$modeMap = ['js'=>'javascript','html'=>'htmlmixed','htm'=>'htmlmixed','css'=>'css','php'=>'application/x-httpd-php','json'=>'application/json','yml'=>'yaml','yaml'=>'yaml','xml'=>'xml','toml'=>'toml','sh'=>'shell','bash'=>'shell'];
$mode = $modeMap[$extension] ?? 'text/plain';
?>
<!DOCTYPE html>
<html lang="<?= $current_lang ?>" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= __t('websftp.title') ?> - <?= htmlspecialchars($server['service_name']) ?></title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/theme/dracula.min.css">
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/codemirror.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/javascript/javascript.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/xml/xml.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/css/css.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/htmlmixed/htmlmixed.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/php/php.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/yaml/yaml.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/toml/toml.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.16/mode/shell/shell.min.js"></script>

    <style>
        /* === CUSTOM CSS COMPLÉMENTAIRE TAILWIND === */
        :root {
            --sidebar-width: 260px;
            --glass-bg: rgba(17, 24, 39, 0.85);
            --glass-border: rgba(75, 85, 99, 0.3);
        }

        body { 
            background: #0f172a; 
            overflow-x: hidden;
            -webkit-tap-highlight-color: transparent;
        }

        /* Scrollbar personnalisée */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #374151; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #4b5563; }

        /* Glass Panel Custom */
        .glass-panel {
            background: var(--glass-bg);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid var(--glass-border);
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.3);
        }

        /* CodeMirror Responsive */
        .CodeMirror {
            height: 50vh !important;
            min-height: 300px;
            max-height: 700px;
            border-radius: 0.75rem;
            font-family: 'JetBrains Mono', 'Fira Code', monospace;
            font-size: 13px;
            line-height: 1.6;
        }
        @media (min-width: 1024px) {
            .CodeMirror { height: 600px !important; font-size: 14px; }
        }

        /* Table responsive mobile */
        .file-table-wrapper {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        .file-table { min-width: 600px; }

        /* Upload zone drag effect */
        .upload-zone { transition: all 0.2s ease; }
        .upload-zone:active { transform: scale(0.98); }

        /* Boutons d'action hover smooth */
        .action-btn {
            transition: all 0.15s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .action-btn:active { transform: scale(0.92); }

        /* Breadcrumb scroll horizontal mobile */
        .breadcrumb-nav {
            scrollbar-width: none;
            -ms-overflow-style: none;
        }
        .breadcrumb-nav::-webkit-scrollbar { display: none; }

        /* Animation fade in */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(12px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in-up { animation: fadeInUp 0.3s ease-out forwards; }

        /* Mobile bottom padding pour éviter overlap footer */
        @media (max-width: 768px) {
            main { padding-bottom: 2rem; }
            .CodeMirror { height: 40vh !important; min-height: 250px; }
        }

        /* Safe area pour iPhone notch */
        @supports (padding: env(safe-area-inset-top)) {
            body { padding-top: env(safe-area-inset-top); }
        }
    </style>
</head>

<body class="text-gray-100 font-sans antialiased min-h-screen flex flex-col">

    <!-- Sidebar -->
    <?php 
    try {
        if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/inc/clients_sidebar.php')) {
            include $_SERVER['DOCUMENT_ROOT'] . '/inc/clients_sidebar.php';
        }
    } catch (Throwable $e) {
        echo '<div class="bg-red-600 text-white p-4 fixed top-0 left-0 right-0 z-50">Sidebar Error</div>';
    }
    ?>

    <!-- Main Content -->
    <main class="flex-1 w-full px-4 sm:px-6 lg:px-8 py-6 md:py-8 overflow-y-auto">
        
        <!-- Header -->
        <div class="mb-6 md:mb-8">
            <div class="flex items-center justify-between mb-4 gap-3">
                <h1 class="text-xl sm:text-2xl lg:text-3xl font-bold text-white flex items-center gap-2 sm:gap-3 truncate">
                    <i class="fas fa-folder-open text-blue-500 shrink-0"></i>
                    <span class="truncate"><?= __t('websftp.title') ?></span>
                </h1>
                <a href="/client/servers/" class="shrink-0 text-xs sm:text-sm text-gray-400 hover:text-white transition flex items-center gap-1.5 sm:gap-2 bg-gray-800/80 hover:bg-gray-700 px-3 sm:px-4 py-2 rounded-lg border border-gray-700 whitespace-nowrap action-btn">
                    <i class="fas fa-arrow-left text-[10px] sm:text-xs"></i> <span class="hidden xs:inline">Retour</span>
                </a>
            </div>

            <!-- Breadcrumb -->
            <nav class="breadcrumb-nav flex items-center text-xs sm:text-sm text-gray-400 bg-gray-800/50 p-2.5 sm:p-3 rounded-xl border border-gray-700/50 overflow-x-auto whitespace-nowrap shadow-sm">
                <a href="?uuid=<?= urlencode($uuid) ?>&dir=/" class="hover:text-blue-400 transition flex items-center gap-1.5 shrink-0">
                    <i class="fas fa-home text-[10px]"></i> <?= __t('websftp.breadcrumb.home') ?>
                </a>
                <?php 
                $parts = explode('/', trim($directory, '/'));
                $pathBuild = "";
                foreach($parts as $part): 
                    if(empty($part)) continue;
                    $pathBuild .= "/" . $part;
                ?>
                    <span class="mx-1.5 sm:mx-2 text-gray-600 shrink-0"><i class="fas fa-chevron-right text-[8px]"></i></span>
                    <a href="?uuid=<?= urlencode($uuid) ?>&dir=<?= urlencode($pathBuild) ?>" class="hover:text-blue-400 transition truncate max-w-[100px] sm:max-w-[150px] font-medium shrink-0">
                        <?= htmlspecialchars($part) ?>
                    </a>
                <?php endforeach; ?>
            </nav>
        </div>

        <!-- Toolbar Actions - Responsive Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3 sm:gap-4 md:gap-6 mb-6 md:mb-8">
            
            <!-- Upload Zone -->
            <div class="sm:col-span-2 lg:col-span-6 glass-panel p-3 sm:p-5 rounded-2xl">
                <form method="POST" enctype="multipart/form-data" class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 h-full">
                    <div class="flex-1 relative group upload-zone">
                        <input type="file" name="upload_file" id="fileInput" class="hidden" onchange="this.parentElement.querySelector('span').innerText = this.files[0]?.name || '<?= __t('websftp.upload.placeholder') ?>'" required>
                        <label for="fileInput" class="cursor-pointer flex items-center justify-center w-full h-11 sm:h-12 px-3 sm:px-4 border-2 border-dashed border-gray-600 rounded-xl hover:border-blue-500 hover:bg-blue-500/10 transition">
                            <span class="text-gray-400 group-hover:text-blue-300 text-xs sm:text-sm truncate"><?= __t('websftp.upload.placeholder') ?></span>
                            <i class="fas fa-cloud-upload-alt ml-2 sm:ml-3 text-gray-500 group-hover:text-blue-400 text-base sm:text-lg shrink-0"></i>
                        </label>
                    </div>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-500 text-white px-4 sm:px-6 py-2.5 sm:py-3 rounded-xl font-bold transition shadow-lg shadow-blue-900/20 whitespace-nowrap text-xs sm:text-sm action-btn shrink-0">
                        <?= __t('websftp.upload.btn') ?>
                    </button>
                </form>
            </div>

            <!-- Create Folder -->
            <div class="sm:col-span-1 lg:col-span-3 glass-panel p-3 sm:p-5 rounded-2xl">
                <form method="POST" class="flex gap-2 h-full">
                    <input type="hidden" name="action" value="create_folder">
                    <input type="text" name="folder_name" placeholder="<?= __t('websftp.folder.placeholder') ?>" required class="flex-1 bg-gray-900/50 border border-gray-700 text-white text-xs sm:text-sm rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-transparent block w-full px-3 sm:px-4 py-2.5 sm:py-3 outline-none transition min-w-0">
                    <button type="submit" class="bg-gray-700 hover:bg-gray-600 text-white w-10 sm:w-12 rounded-xl transition flex items-center justify-center text-sm sm:text-lg shrink-0 action-btn" title="<?= __t('websftp.folder.placeholder') ?>">
                        <i class="fas fa-plus"></i>
                    </button>
                </form>
            </div>

            <!-- Compress -->
            <div class="sm:col-span-1 lg:col-span-3 glass-panel p-3 sm:p-5 rounded-2xl flex items-center justify-between gap-2">
                <span class="text-xs sm:text-sm text-gray-400 font-medium hidden xl:block truncate"><?= __t('websftp.action.compress') ?></span>
                <form method="POST" class="w-full xl:w-auto">
                    <input type="hidden" name="action" value="compress">
                    <button type="submit" class="bg-purple-600 hover:bg-purple-500 text-white px-3 sm:px-4 py-2.5 sm:py-3 rounded-xl font-bold transition shadow-lg shadow-purple-900/20 flex items-center justify-center gap-2 w-full xl:w-auto text-xs sm:text-sm action-btn">
                        <i class="fas fa-file-archive"></i> <span class="xl:hidden sm:inline">Archive</span><span class="hidden sm:inline xl:hidden">(.tar.gz)</span>
                    </button>
                </form>
            </div>
        </div>

        <!-- File List Table -->
        <div class="glass-panel rounded-2xl overflow-hidden shadow-xl border border-gray-700/50 mb-6 md:mb-8">
            <div class="file-table-wrapper">
                <table class="file-table w-full text-xs sm:text-sm text-left text-gray-400">
                    <thead class="text-[10px] sm:text-xs text-gray-300 uppercase bg-gray-800/80 border-b border-gray-700">
                        <tr>
                            <th scope="col" class="px-4 sm:px-6 py-3 sm:py-4 font-bold tracking-wider whitespace-nowrap"><?= __t('websftp.table.name') ?></th>
                            <th scope="col" class="px-4 sm:px-6 py-3 sm:py-4 text-right font-bold tracking-wider whitespace-nowrap"><?= __t('websftp.table.size') ?></th>
                            <th scope="col" class="px-4 sm:px-6 py-3 sm:py-4 text-right font-bold tracking-wider whitespace-nowrap"><?= __t('websftp.table.actions') ?></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-700/50">
                        <?php if (empty($files)): ?>
                            <tr>
                                <td colspan="3" class="px-4 sm:px-6 py-10 sm:py-12 text-center text-gray-500">
                                    <div class="flex flex-col items-center justify-center">
                                        <i class="fas fa-folder-open text-4xl sm:text-5xl mb-3 sm:mb-4 opacity-20 text-gray-600"></i>
                                        <p class="text-sm sm:text-base font-medium"><?= __t('websftp.empty') ?></p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($files as $file): 
                                $attr = $file['attributes'];
                                $name = $attr['name'];
                                $isFile = (bool) $attr['is_file'];
                                $size = $attr['size'];
                                $path = rtrim($directory, '/') . '/' . $name;
                                if ($name === '..') continue;
                            ?>
                            <tr class="bg-transparent hover:bg-gray-800/60 transition duration-150 group">
                                <td class="px-4 sm:px-6 py-3 sm:py-4 font-medium text-white flex items-center gap-2 sm:gap-4">
                                    <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-gray-800 flex items-center justify-center shrink-0 border border-gray-700">
                                        <?php if ($isFile): ?>
                                            <i class="fas fa-file-code text-blue-400 text-sm sm:text-lg"></i>
                                        <?php else: ?>
                                            <i class="fas fa-folder text-yellow-400 text-sm sm:text-lg"></i>
                                        <?php endif; ?>
                                    </div>
                                    <a href="<?= $isFile ? "?uuid=".urlencode($uuid)."&dir=".urlencode($directory)."&edit=".urlencode($path) : "?uuid=".urlencode($uuid)."&dir=".urlencode($path) ?>" class="hover:text-blue-400 transition truncate max-w-[120px] sm:max-w-[300px] block text-xs sm:text-base">
                                        <?= htmlspecialchars($name) ?>
                                    </a>
                                </td>
                                <td class="px-4 sm:px-6 py-3 sm:py-4 text-right font-mono text-[10px] sm:text-xs text-gray-500 whitespace-nowrap">
                                    <?= $isFile ? round($size / 1024, 2) . ' KB' : '-' ?>
                                </td>
                                <td class="px-4 sm:px-6 py-3 sm:py-4 text-right">
                                    <div class="flex items-center justify-end gap-1 opacity-100 sm:opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                                        
                                        <?php if ($isFile): ?>
                                        <a href="<?= "$panel/api/client/servers/$short/files/download?file=" . urlencode($path) ?>" target="_blank" class="w-7 h-7 sm:w-8 sm:h-8 rounded-lg flex items-center justify-center text-gray-400 hover:text-green-400 hover:bg-green-400/10 transition action-btn" title="<?= __t('websftp.action.download') ?>">
                                            <i class="fas fa-download text-[10px] sm:text-xs"></i>
                                        </a>
                                        <?php endif; ?>

                                        <form method="POST" class="flex items-center gap-1" onsubmit="return confirm('<?= __t('websftp.action.rename') ?> ?')">
                                            <input type="hidden" name="action" value="rename">
                                            <input type="hidden" name="old_name" value="<?= htmlspecialchars($path) ?>">
                                            <input type="text" name="new_name" value="<?= htmlspecialchars($name) ?>" class="bg-gray-900 border border-gray-600 text-white text-[10px] sm:text-xs rounded-lg px-1.5 sm:px-2 py-1 sm:py-1.5 w-16 sm:w-24 focus:border-blue-500 outline-none transition min-w-0">
                                            <button type="submit" class="w-7 h-7 sm:w-8 sm:h-8 rounded-lg flex items-center justify-center text-gray-400 hover:text-yellow-400 hover:bg-yellow-400/10 transition action-btn" title="<?= __t('websftp.action.rename') ?>">
                                                <i class="fas fa-pen text-[8px] sm:text-[10px]"></i>
                                            </button>
                                        </form>

                                        <a href="?uuid=<?= urlencode($uuid) ?>&dir=<?= urlencode($directory) ?>&delete=<?= urlencode($path) ?>" onclick="return confirm('<?= __t('websftp.action.delete') ?> ?')" class="w-7 h-7 sm:w-8 sm:h-8 rounded-lg flex items-center justify-center text-gray-400 hover:text-red-500 hover:bg-red-500/10 transition action-btn" title="<?= __t('websftp.action.delete') ?>">
                                            <i class="fas fa-trash text-[8px] sm:text-[10px]"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Code Editor Section -->
        <?php if ($editFile): ?>
        <div class="mt-6 md:mt-8 animate-fade-in-up">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between mb-3 sm:mb-4 gap-3">
                <h2 class="text-base sm:text-xl font-bold text-white flex items-center gap-2 flex-wrap">
                    <i class="fas fa-code text-purple-400 shrink-0"></i>
                    <?= __t('websftp.editor.title') ?> : <span class="text-gray-400 font-normal break-all"><?= htmlspecialchars(basename($editFile)) ?></span>
                </h2>
                <a href="?uuid=<?= urlencode($uuid) ?>&dir=<?= urlencode($directory) ?>" class="text-xs sm:text-sm text-red-400 hover:text-red-300 font-medium bg-red-400/10 hover:bg-red-400/20 px-3 py-1.5 sm:py-2 rounded-lg transition whitespace-nowrap action-btn shrink-0">
                    <i class="fas fa-times"></i> <?= __t('websftp.editor.close') ?>
                </a>
            </div>

            <div class="glass-panel p-1 rounded-2xl shadow-2xl border-purple-500/20">
                <form method="POST">
                    <input type="hidden" name="action" value="save_file">
                    <input type="hidden" name="file_path" value="<?= htmlspecialchars($editFile) ?>">
                    <textarea id="codeEditor" name="content"><?= htmlspecialchars($fileContent) ?></textarea>
                    <div class="p-3 sm:p-4 flex justify-end bg-gray-800/50 rounded-b-xl border-t border-gray-700 mt-1">
                        <button type="submit" class="bg-green-600 hover:bg-green-500 text-white px-4 sm:px-6 py-2 sm:py-2.5 rounded-xl font-bold transition flex items-center gap-2 shadow-lg shadow-green-900/20 text-xs sm:text-sm action-btn">
                            <i class="fas fa-save"></i> <?= __t('websftp.editor.save') ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <script>
            var editor = CodeMirror.fromTextArea(document.getElementById("codeEditor"), {
                mode: "<?= $mode ?>", theme: "dracula", lineNumbers: true,
                autoCloseBrackets: true, matchBrackets: true, lineWrapping: true,
                indentUnit: 4, tabSize: 4, indentWithTabs: false
            });
        </script>
        <?php endif; ?>

    </main>

    <!-- Footer -->
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/inc/footer.php'; ?>

</body>
</html>
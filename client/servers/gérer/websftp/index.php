<?php
declare(strict_types=1);
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/lang.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

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

/*
|--------------------------------------------------------------------------
| DATABASE & CONFIG
|--------------------------------------------------------------------------
*/

try {
    $pdo = new PDO(
        "mysql:host=localhost;dbname=s43_orinheberge;charset=utf8mb4",
        "root",
        "1504",
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    die("Erreur critique BDD.");
}

// Récupération config
$cfg = [];
try {
    foreach ($pdo->query('SELECT `key`, `value` FROM settings') as $row) {
        $cfg[$row['key']] = $row['value'];
    }
} catch (Exception $e) {
    $cfg = [];
}

$panel   = $cfg['panel_url'] ?? 'https://panel.orinstone.deepstone.fr';
$api_key = $cfg['api_key_client'] ?? '';

if (empty($api_key)) {
    die("Configuration API manquante.");
}

$headers = [
    "Authorization: Bearer $api_key",
    "Accept: application/vnd.pterodactyl.v1+json",
    "Content-Type: application/json"
];

/*
|--------------------------------------------------------------------------
| SERVER VERIFICATION
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? AND uuid = ? LIMIT 1");
$stmt->execute([$_SESSION['user_id'], $uuid]);
$server = $stmt->fetch();

if (!$server) {
    http_response_code(403);
    die("Accès refusé ou serveur introuvable.");
}

$short     = substr($uuid, 0, 8);
$directory = $_GET['dir'] ?? "/";

/*
|--------------------------------------------------------------------------
| ACTIONS (POST/GET)
|--------------------------------------------------------------------------
*/

$redirectUrl = "?uuid=" . urlencode($uuid) . "&dir=" . urlencode($directory);

// 1. Create Folder
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_folder') {
    $folder = trim($_POST['folder_name'] ?? '');
    if ($folder !== "") {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => "$panel/api/client/servers/$short/files/create-folder",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode(["root" => $directory, "name" => $folder]),
            CURLOPT_HTTPHEADER => $headers
        ]);
        curl_exec($ch);
        curl_close($ch);
    }
    header("Location: $redirectUrl");
    exit();
}

// 2. Delete File
if (isset($_GET['delete'])) {
    $target = $_GET['delete'];
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "$panel/api/client/servers/$short/files/delete",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            "root" => dirname($target),
            "files" => [basename($target)]
        ]),
        CURLOPT_HTTPHEADER => $headers
    ]);
    curl_exec($ch);
    curl_close($ch);
    header("Location: $redirectUrl");
    exit();
}

// 3. Rename File
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'rename') {
    $from = $_POST['old_name'] ?? '';
    $to   = trim($_POST['new_name'] ?? '');
    if ($to !== "" && $from !== "") {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => "$panel/api/client/servers/$short/files/rename",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode([
                "root" => dirname($from),
                "files" => [["from" => basename($from), "to" => $to]]
            ]),
            CURLOPT_HTTPHEADER => $headers
        ]);
        curl_exec($ch);
        curl_close($ch);
    }
    header("Location: $redirectUrl");
    exit();
}

// 4. Save File Content
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_file') {
    $file    = $_POST['file_path'] ?? '';
    $content = $_POST['content'] ?? '';
    
    if ($file) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => "$panel/api/client/servers/$short/files/write?file=" . urlencode($file),
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
        curl_close($ch);
    }
    header("Location: ?uuid=" . urlencode($uuid) . "&dir=" . urlencode(dirname($file)));
    exit();
}

// 5. Upload File
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['upload_file'])) {
    // Step 1: Get Signed URL
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "$panel/api/client/servers/$short/files/upload",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    
    $uploadData = json_decode($response, true);
    
    if (isset($uploadData['attributes']['url'])) {
        $uploadUrl = $uploadData['attributes']['url'];
        $fileObj   = new CURLFile(
            $_FILES['upload_file']['tmp_name'],
            $_FILES['upload_file']['type'],
            $_FILES['upload_file']['name']
        );

        // Step 2: Upload to Signed URL
        $up = curl_init();
        curl_setopt_array($up, [
            CURLOPT_URL => $uploadUrl . "&directory=" . urlencode($directory),
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => ["files" => $fileObj],
            CURLOPT_RETURNTRANSFER => true
        ]);
        curl_exec($up);
        curl_close($up);
    }
    header("Location: $redirectUrl");
    exit();
}

/*
|--------------------------------------------------------------------------
| DATA FETCHING
|--------------------------------------------------------------------------
*/

$fileContent = "";
$editFile    = $_GET['edit'] ?? null;

// Fetch File Content for Editor
if ($editFile) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "$panel/api/client/servers/$short/files/contents?file=" . urlencode($editFile),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers
    ]);
    $fileContent = curl_exec($ch);
    curl_close($ch);
}

// Fetch File List
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => "$panel/api/client/servers/$short/files/list?directory=" . urlencode($directory),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => $headers
]);
$response = curl_exec($ch);
curl_close($ch);

$data  = json_decode($response, true);
$files = $data['data'] ?? [];

// Determine CodeMirror Mode
$extension = strtolower(pathinfo($editFile ?? '', PATHINFO_EXTENSION));
$modeMap = [
    'js' => 'javascript', 'html' => 'htmlmixed', 'htm' => 'htmlmixed',
    'css' => 'css', 'php' => 'application/x-httpd-php', 'json' => 'application/json',
    'yml' => 'yaml', 'yaml' => 'yaml', 'xml' => 'xml', 'toml' => 'toml',
    'sh' => 'shell', 'bash' => 'shell'
];
$mode = $modeMap[$extension] ?? 'text/plain';

?>
<!DOCTYPE html>
<html lang="fr" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionnaire de Fichiers - <?= htmlspecialchars($server['service_name']) ?></title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- CodeMirror -->
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
        /* Custom Scrollbar */
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: #1f2937; }
        ::-webkit-scrollbar-thumb { background: #4b5563; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #6b7280; }
        
        .CodeMirror {
            height: 600px;
            border-radius: 0.5rem;
            font-family: 'JetBrains Mono', 'Fira Code', monospace;
            font-size: 14px;
        }
        
        /* Glass Effect Utilities */
        .glass-panel {
            background: rgba(31, 41, 55, 0.7);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(75, 85, 99, 0.4);
        }
    </style>
</head>

<body class="bg-gray-900 text-gray-100 font-sans antialiased min-h-screen flex flex-col">

    <!-- Sidebar Inclusion -->
    <?php 
    try {
        if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/inc/clients_sidebar.php')) {
            include $_SERVER['DOCUMENT_ROOT'] . '/inc/clients_sidebar.php';
        }
    } catch (Throwable $e) {
        echo '<div class="bg-red-600 text-white p-4">Sidebar Error</div>';
    }
    ?>

    <!-- Main Content Area -->
    <main class="flex-1 p-4 md:p-8 overflow-y-auto">
        
        <!-- Header & Breadcrumb -->
        <div class="mb-6">
            <div class="flex items-center justify-between mb-4">
                <h1 class="text-2xl font-bold text-white flex items-center gap-3">
                    <i class="fas fa-folder-tree text-blue-500"></i>
                    Gestionnaire de Fichiers
                </h1>
                <a href="/client/servers/" class="text-sm text-gray-400 hover:text-white transition flex items-center gap-2">
                    <i class="fas fa-arrow-left"></i> Retour aux serveurs
                </a>
            </div>

            <!-- Breadcrumb Navigation -->
            <nav class="flex items-center text-sm text-gray-400 bg-gray-800/50 p-3 rounded-lg border border-gray-700 overflow-x-auto whitespace-nowrap">
                <a href="?uuid=<?= urlencode($uuid) ?>&dir=/" class="hover:text-blue-400 transition"><i class="fas fa-home"></i></a>
                <?php 
                $parts = explode('/', trim($directory, '/'));
                $pathBuild = "";
                foreach($parts as $part): 
                    if(empty($part)) continue;
                    $pathBuild .= "/" . $part;
                ?>
                    <span class="mx-2 text-gray-600">/</span>
                    <a href="?uuid=<?= urlencode($uuid) ?>&dir=<?= urlencode($pathBuild) ?>" class="hover:text-blue-400 transition truncate max-w-[150px]">
                        <?= htmlspecialchars($part) ?>
                    </a>
                <?php endforeach; ?>
            </nav>
        </div>

        <!-- Toolbar: Upload & Create -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            <!-- Upload Box -->
            <div class="glass-panel p-4 rounded-xl col-span-2">
                <form method="POST" enctype="multipart/form-data" class="flex items-center gap-4">
                    <div class="flex-1 relative">
                        <input type="file" name="upload_file" id="fileInput" class="hidden" onchange="this.parentElement.querySelector('span').innerText = this.files[0]?.name || 'Aucun fichier choisi'" required>
                        <label for="fileInput" class="cursor-pointer flex items-center justify-center w-full h-12 px-4 border-2 border-dashed border-gray-600 rounded-lg hover:border-blue-500 hover:bg-gray-800/50 transition group">
                            <span class="text-gray-400 group-hover:text-gray-200 text-sm truncate">Cliquer pour sélectionner un fichier...</span>
                            <i class="fas fa-cloud-upload-alt ml-2 text-gray-500 group-hover:text-blue-400"></i>
                        </label>
                    </div>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-500 text-white px-6 py-3 rounded-lg font-medium transition shadow-lg shadow-blue-900/20">
                        Uploader
                    </button>
                </form>
            </div>

            <!-- Create Folder Box -->
            <div class="glass-panel p-4 rounded-xl">
                <form method="POST" class="flex gap-2 h-full">
                    <input type="hidden" name="action" value="create_folder">
                    <input type="text" name="folder_name" placeholder="Nom du dossier" required class="flex-1 bg-gray-900 border border-gray-700 text-white text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                    <button type="submit" class="bg-gray-700 hover:bg-gray-600 text-white px-4 rounded-lg transition" title="Créer le dossier">
                        <i class="fas fa-plus"></i>
                    </button>
                </form>
            </div>
        </div>

        <!-- File List -->
        <div class="glass-panel rounded-xl overflow-hidden shadow-xl">
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left text-gray-400">
                    <thead class="text-xs text-gray-300 uppercase bg-gray-800/80 border-b border-gray-700">
                        <tr>
                            <th scope="col" class="px-6 py-4">Nom</th>
                            <th scope="col" class="px-6 py-4 text-right">Taille</th>
                            <th scope="col" class="px-6 py-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-700/50">
                        <?php if (empty($files)): ?>
                            <tr>
                                <td colspan="3" class="px-6 py-8 text-center text-gray-500">
                                    <i class="fas fa-folder-open text-4xl mb-3 opacity-20"></i>
                                    <p>Ce dossier est vide.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($files as $file): 
                                $attr = $file['attributes'];
                                $name = $attr['name'];
                                $isFile = (bool) $attr['is_file'];
                                $size = $attr['size'];
                                $path = rtrim($directory, '/') . '/' . $name;
                                
                                // Skip parent directory link if it's the root logic handled by breadcrumb usually, but keeping simple here
                                if ($name === '..') continue;
                            ?>
                            <tr class="bg-transparent hover:bg-gray-800/40 transition group">
                                <td class="px-6 py-4 font-medium text-white flex items-center gap-3">
                                    <?php if ($isFile): ?>
                                        <i class="fas fa-file-code text-blue-400 text-lg"></i>
                                    <?php else: ?>
                                        <i class="fas fa-folder text-yellow-400 text-lg"></i>
                                    <?php endif; ?>
                                    
                                    <a href="<?= $isFile 
                                        ? "?uuid=".urlencode($uuid)."&dir=".urlencode($directory)."&edit=".urlencode($path) 
                                        : "?uuid=".urlencode($uuid)."&dir=".urlencode($path) 
                                    ?>" class="hover:text-blue-400 transition truncate max-w-[300px] block">
                                        <?= htmlspecialchars($name) ?>
                                    </a>
                                </td>
                                <td class="px-6 py-4 text-right font-mono text-xs">
                                    <?= $isFile ? round($size / 1024, 2) . ' KB' : '-' ?>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="flex items-center justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                        
                                        <!-- Rename Button (Triggers small JS prompt or inline form - simplified here to inline form hidden by default) -->
                                        <form method="POST" class="flex items-center gap-1" onsubmit="return confirm('Renommer ce fichier ?')">
                                            <input type="hidden" name="action" value="rename">
                                            <input type="hidden" name="old_name" value="<?= htmlspecialchars($path) ?>">
                                            <input type="text" name="new_name" value="<?= htmlspecialchars($name) ?>" class="bg-gray-900 border border-gray-600 text-white text-xs rounded px-2 py-1 w-24 focus:border-blue-500 outline-none">
                                            <button type="submit" class="text-gray-400 hover:text-yellow-400 p-1"><i class="fas fa-pen"></i></button>
                                        </form>

                                        <!-- Delete Button -->
                                        <a href="?uuid=<?= urlencode($uuid) ?>&dir=<?= urlencode($directory) ?>&delete=<?= urlencode($path) ?>" 
                                           onclick="return confirm('Êtes-vous sûr de vouloir supprimer définitivement cet élément ?')"
                                           class="text-gray-400 hover:text-red-500 p-1 transition">
                                            <i class="fas fa-trash"></i>
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
        <div class="mt-8 animate-fade-in-up">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-bold text-white flex items-center gap-2">
                    <i class="fas fa-code text-purple-400"></i>
                    Édition : <span class="text-gray-400 font-normal"><?= htmlspecialchars(basename($editFile)) ?></span>
                </h2>
                <a href="?uuid=<?= urlencode($uuid) ?>&dir=<?= urlencode($directory) ?>" class="text-sm text-red-400 hover:text-red-300 font-medium">
                    <i class="fas fa-times"></i> Fermer l'éditeur
                </a>
            </div>

            <div class="glass-panel p-1 rounded-xl shadow-2xl border-blue-500/20">
                <form method="POST">
                    <input type="hidden" name="action" value="save_file">
                    <input type="hidden" name="file_path" value="<?= htmlspecialchars($editFile) ?>">
                    
                    <textarea id="codeEditor" name="content"><?= htmlspecialchars($fileContent) ?></textarea>
                    
                    <div class="p-4 flex justify-end bg-gray-800/50 rounded-b-lg border-t border-gray-700 mt-1">
                        <button type="submit" class="bg-green-600 hover:bg-green-500 text-white px-6 py-2 rounded-lg font-bold transition flex items-center gap-2 shadow-lg shadow-green-900/20">
                            <i class="fas fa-save"></i> Sauvegarder les modifications
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <script>
            var editor = CodeMirror.fromTextArea(document.getElementById("codeEditor"), {
                mode: "<?= $mode ?>",
                theme: "dracula",
                lineNumbers: true,
                autoCloseBrackets: true,
                matchBrackets: true,
                lineWrapping: true,
                indentUnit: 4,
                tabSize: 4,
                indentWithTabs: false
            });
        </script>
        <?php endif; ?>

    </main>

    <!-- Footer Inclusion -->
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/inc/footer.php'; ?>

</body>
</html>
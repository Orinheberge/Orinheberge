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
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch(PDOException $e) {
    die($e->getMessage());
}

// Récupération de la configuration (Panel URL et API Key) depuis la BDD
$cfg = [];
foreach ($pdo->query('SELECT `key`, `value` FROM settings') as $row) {
    $cfg[$row['key']] = $row['value'];
}

$panel   = $cfg['panel_url'] ?? 'https://panel.orinstone.deepstone.fr';
$api_key = $cfg['api_key_client'] ?? ''; // Clé API liée à la database

if (empty($api_key)) {
    die("Erreur de configuration : Clé API manquante dans la base de données.");
}

$headers = [
    "Authorization: Bearer $api_key",
    "Accept: application/vnd.pterodactyl.v1+json",
    "Content-Type: application/json"
];

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

    <!-- Inclusion de la Sidebar -->
    <?php 
    try {
        if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/inc/clients_sidebar.php')) {
            include $_SERVER['DOCUMENT_ROOT'] . '/inc/clients_sidebar.php';
        }
    } catch (Throwable $e) {
        echo '<div style="background:#ef4444;color:white;padding:20px;">❌ Sidebar error : ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
    ?>

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

    <!-- Inclusion du footer externe -->
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/inc/footer.php'; ?>

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
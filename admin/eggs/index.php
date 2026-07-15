<?php
ini_set('display_errors',1); error_reporting(E_ALL);
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: /login/'); exit(); }

$pdo = new PDO('mysql:host=localhost;dbname=s43_orinheberge;charset=utf8mb4','root','1504',
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);

$stmt = $pdo->prepare('SELECT id,pseudo,firstname,avatar,is_admin FROM users WHERE id=? LIMIT 1');
$stmt->execute([$_SESSION['user_id']]); $admin = $stmt->fetch();
if (!$admin || !$admin['is_admin']) { http_response_code(403); die('403 Forbidden'); }

$cfg = [];
foreach ($pdo->query('SELECT `key`,`value` FROM settings') as $r) $cfg[$r['key']] = $r['value'];
$panel_url     = $cfg['panel_url']     ?? '';
$api_key_admin = $cfg['api_key_admin'] ?? '';
$headers_admin = ["Authorization: Bearer $api_key_admin","Accept: application/vnd.pterodactyl.v1+json","Content-Type: application/json"];

$flash = '';

// ── Actions POST ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $id            = (int)($_POST['id'] ?? 0);
        $name          = trim($_POST['name'] ?? '');
        $panel_egg_id  = (int)($_POST['panel_egg_id'] ?? 0);
        $panel_nest_id = (int)($_POST['panel_nest_id'] ?? 0);
        $docker_image  = trim($_POST['docker_image'] ?? '');
        $startup       = trim($_POST['startup'] ?? '');
        $icon          = trim($_POST['icon'] ?? 'fas fa-server');
        $env_vars      = trim($_POST['env_vars'] ?? '{}');

        // Validation json
        json_decode($env_vars);
        if (json_last_error() !== JSON_ERROR_NONE) $env_vars = '{}';

        if (empty($name)) {
            $flash = '<div class="alert alert-error">Le nom est requis.</div>';
        } else {
            if ($action === 'add') {
                $st = $pdo->prepare('INSERT INTO eggs (name, panel_egg_id, panel_nest_id, docker_image, startup, icon, env_vars) VALUES (?,?,?,?,?,?,?)');
                $st->execute([$name, $panel_egg_id, $panel_nest_id, $docker_image, $startup, $icon, $env_vars]);
                $flash = '<div class="alert alert-success">Egg ajouté !</div>';
            } else {
                $st = $pdo->prepare('UPDATE eggs SET name=?, panel_egg_id=?, panel_nest_id=?, docker_image=?, startup=?, icon=?, env_vars=? WHERE id=?');
                $st->execute([$name, $panel_egg_id, $panel_nest_id, $docker_image, $startup, $icon, $env_vars, $id]);
                $flash = '<div class="alert alert-success">Egg mis à jour !</div>';
            }
        }
    }
    elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $st = $pdo->prepare('DELETE FROM eggs WHERE id=?');
        $st->execute([$id]);
        $flash = '<div class="alert alert-success">Egg supprimé !</div>';
    }
    elseif ($action === 'import') {
        $import_nest_id = (int)($_POST['import_nest_id'] ?? 0);
        $import_egg_id  = (int)($_POST['import_egg_id'] ?? 0);

        if (!$import_nest_id || !$import_egg_id) {
            $flash = '<div class="alert alert-error">IDs incorrects.</div>';
        } else {
            $url_api = rtrim($panel_url,'/') . "/api/application/nests/$import_nest_id/eggs/$import_egg_id?include=variables";
            $ch = curl_init($url_api);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => $headers_admin,
                CURLOPT_TIMEOUT        => 10
            ]);
            $res = curl_exec($ch);
            
            // 🟢 CORRECTIF : Suppression ou conditionnement de curl_close() pour PHP 8.5+
            if (PHP_VERSION_ID < 80000) {
                curl_close($ch);
            }

            $data = json_decode($res, true);
            if (isset($data['attributes'])) {
                $attr = $data['attributes'];
                $name = $attr['name'] ?? 'Egg Importé';
                $docker_image = $attr['docker_image'] ?? '';
                $startup = $attr['startup'] ?? '';

                $vars_arr = [];
                if (isset($attr['relationships']['variables']['data'])) {
                    foreach ($attr['relationships']['variables']['data'] as $v) {
                        if (isset($v['attributes']['env_variable'])) {
                            $va = $v['attributes'];
                            $vars_arr[$va['env_variable']] = [
                                'name' => $va['name'],
                                'rules' => $va['rules'],
                                'default' => $va['default_value']
                            ];
                        }
                    }
                }
                $env_json = json_encode($vars_arr, JSON_UNESCAPED_UNICODE);

                $st = $pdo->prepare('INSERT INTO eggs (name, panel_egg_id, panel_nest_id, docker_image, startup, icon, env_vars) VALUES (?,?,?,?,?,?,?)');
                $st->execute([$name, $import_egg_id, $import_nest_id, $docker_image, $startup, 'fas fa-download', $env_json]);
                $flash = '<div class="alert alert-success">Egg "'.$name.'" importé du panel avec succès !</div>';
            } else {
                $flash = '<div class="alert alert-error">Impossible d\'importer l\'egg (Erreur API).</div>';
            }
        }
    }
}

$eggs = $pdo->query('SELECT * FROM eggs ORDER BY id DESC')->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr" class="bg-slate-950 text-slate-100">
<head>
  <meta charset="UTF-8">
  <title>Eggs Manager - Admin - OrinHeberge</title>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen flex flex-col">

<?php $active_nav = ''; include $_SERVER['DOCUMENT_ROOT'] . '/inc/navbar.php'; ?>

<main class="flex-1 max-w-7xl w-full mx-auto p-6">
  <div class="flex items-center justify-between mb-6">
    <div>
      <h1 class="text-3xl font-black">Gestion des Eggs</h1>
      <p class="text-sm text-slate-400">Configurez et importez les configurations de serveurs Pterodactyl</p>
    </div>
    <div class="flex gap-2">
      <button onclick="document.getElementById('modalImport').classList.remove('hidden')" class="bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-2 rounded-lg text-sm font-bold flex items-center gap-2">
        <i class="fas fa-download"></i> Importer
      </button>
      <button onclick="openAddModal()" class="bg-sky-600 hover:bg-sky-500 text-white px-4 py-2 rounded-lg text-sm font-bold flex items-center gap-2">
        <i class="fas fa-plus"></i> Nouveau
      </button>
    </div>
  </div>

  <?php echo $flash; ?>

  <div class="bg-slate-900 border border-white/5 rounded-2xl overflow-hidden">
    <table class="w-full text-left text-sm border-collapse">
      <thead>
        <tr class="bg-white/[0.02] border-b border-white/5 text-slate-400 uppercase text-[11px] font-bold tracking-wider">
          <th class="p-4">Egg</th>
          <th class="p-4">IDs Panel</th>
          <th class="p-4">Docker</th>
          <th class="p-4">Startup</th>
          <th class="p-4 text-right">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-white/5">
        <?php if (empty($eggs)): ?>
        <tr>
          <td colspan="5" class="p-8 text-center text-slate-500">Aucun Egg pour le moment.</td>
        </tr>
        <?php endif; ?>
        <?php foreach ($eggs as $e): ?>
        <tr class="hover:bg-white/[0.01] transition-colors">
          <td class="p-4 flex items-center gap-3">
            <span class="w-10 h-10 rounded-xl bg-sky-500/10 border border-sky-500/20 flex items-center justify-center text-sky-400 text-base">
              <i class="<?php echo htmlspecialchars($e['icon']); ?>"></i>
            </span>
            <div>
              <div class="font-bold text-white"><?php echo htmlspecialchars($e['name']); ?></div>
              <div class="text-xs text-slate-500">Variables : <?php echo count(json_decode($e['env_vars'] ?? '{}', true)); ?></div>
            </div>
          </td>
          <td class="p-4">
            <span class="bg-slate-800 text-slate-300 text-xs px-2 py-1 rounded">Nest: <?php echo $e['panel_nest_id']; ?></span>
            <span class="bg-slate-800 text-slate-300 text-xs px-2 py-1 rounded">Egg: <?php echo $e['panel_egg_id']; ?></span>
          </td>
          <td class="p-4 font-mono text-xs text-slate-400 max-w-xs truncate" title="<?php echo htmlspecialchars($e['docker_image']); ?>">
            <?php echo htmlspecialchars($e['docker_image']); ?>
          </td>
          <td class="p-4 font-mono text-xs text-slate-400 max-w-xs truncate" title="<?php echo htmlspecialchars($e['startup']); ?>">
            <?php echo htmlspecialchars($e['startup']); ?>
          </td>
          <td class="p-4 text-right">
            <div class="inline-flex gap-1">
              <button onclick='openEditModal(<?php echo json_encode($e, JSON_HEX_APOS|JSON_HEX_QUOT); ?>)' class="bg-white/5 hover:bg-white/10 text-white p-2 rounded-lg text-xs" title="Modifier">
                <i class="fas fa-edit"></i>
              </button>
              <form method="POST" onsubmit="return confirm('Confirmer la suppression de cet Egg ?')" class="inline">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?php echo $e['id']; ?>">
                <button type="submit" class="bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 p-2 rounded-lg text-xs" title="Supprimer">
                  <i class="fas fa-trash"></i>
                </button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</main>

<div id="modalEgg" class="fixed inset-0 bg-black/80 backdrop-blur-sm flex items-center justify-center p-4 z-50 hidden">
  <div class="bg-slate-900 border border-white/10 rounded-2xl max-w-2xl w-full p-6 shadow-2xl overflow-y-auto max-h-[90vh]">
    <h2 id="modalEggTitle" class="text-xl font-bold mb-4 text-white">Ajouter un Egg</h2>
    
    <form method="POST" class="space-y-4">
      <input type="hidden" id="eggAction" name="action" value="add">
      <input type="hidden" id="eggId" name="id" value="">

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <label class="block text-xs font-bold uppercase text-slate-400 mb-1">Nom d'affichage</label>
          <input type="text" id="eggName" name="name" required class="w-full bg-slate-950 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-sky-500">
        </div>
        <div>
          <label class="block text-xs font-bold uppercase text-slate-400 mb-1">Icône (FontAwesome class)</label>
          <input type="text" id="eggIcon" name="icon" placeholder="fas fa-server" class="w-full bg-slate-950 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-sky-500">
        </div>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
          <label class="block text-xs font-bold uppercase text-slate-400 mb-1">ID Nest (Panel)</label>
          <input type="number" id="eggNestId" name="panel_nest_id" required class="w-full bg-slate-950 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-sky-500">
        </div>
        <div>
          <label class="block text-xs font-bold uppercase text-slate-400 mb-1">ID Egg (Panel)</label>
          <input type="number" id="eggPanelId" name="panel_egg_id" required class="w-full bg-slate-950 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-sky-500">
        </div>
      </div>

      <div>
        <label class="block text-xs font-bold uppercase text-slate-400 mb-1">Image Docker par défaut</label>
        <input type="text" id="eggDocker" name="docker_image" required class="w-full bg-slate-950 border border-white/10 rounded-lg px-3 py-2 text-sm font-mono text-white focus:outline-none focus:border-sky-500">
      </div>

      <div>
        <label class="block text-xs font-bold uppercase text-slate-400 mb-1">Commande de démarrage (Startup command)</label>
        <textarea id="eggStartup" name="startup" rows="2" required class="w-full bg-slate-950 border border-white/10 rounded-lg px-3 py-2 text-sm font-mono text-white focus:outline-none focus:border-sky-500"></textarea>
      </div>

      <div>
        <label class="block text-xs font-bold uppercase text-slate-400 mb-1">Variables d'environnement (JSON)</label>
        <textarea id="eggEnv" name="env_vars" rows="4" class="w-full bg-slate-950 border border-white/10 rounded-lg px-3 py-2 text-sm font-mono text-white focus:outline-none focus:border-sky-500" placeholder='{"PORT":{"name":"Server Port","rules":"required|numeric","default":25565}}'></textarea>
      </div>

      <div class="flex gap-2 pt-2">
        <button type="submit" class="bg-sky-600 hover:bg-sky-500 text-white font-bold px-4 py-2 rounded-lg text-sm flex-1">Enregistrer</button>
        <button type="button" onclick="document.getElementById('modalEgg').classList.add('hidden')" class="bg-white/5 hover:bg-white/10 text-white font-bold px-4 py-2 rounded-lg text-sm flex-1">Annuler</button>
      </div>
    </form>
  </div>
</div>

<div id="modalImport" class="fixed inset-0 bg-black/80 backdrop-blur-sm flex items-center justify-center p-4 z-50 hidden">
  <div class="bg-slate-900 border border-white/10 rounded-2xl max-w-md w-full p-6 shadow-2xl">
    <h2 class="text-xl font-bold mb-2 text-white">Importer depuis le Panel</h2>
    <p class="text-xs text-slate-400 mb-4">Indiquez les identifiants d'API du panel pour copier un egg existant.</p>
    
    <form method="POST" class="space-y-4">
      <input type="hidden" name="action" value="import">
      
      <div>
        <label class="block text-xs font-bold uppercase text-slate-400 mb-1">ID Nest (sur le panel)</label>
        <input type="number" name="import_nest_id" required class="w-full bg-slate-950 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-indigo-500">
      </div>

      <div>
        <label class="block text-xs font-bold uppercase text-slate-400 mb-1">ID Egg (sur le panel)</label>
        <input type="number" name="import_egg_id" required class="w-full bg-slate-950 border border-white/10 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-indigo-500">
      </div>

      <div class="flex gap-2 pt-2">
        <button type="submit" class="bg-indigo-600 hover:bg-indigo-500 text-white font-bold px-4 py-2 rounded-lg text-sm flex-1">Importer</button>
        <button type="button" onclick="document.getElementById('modalImport').classList.add('hidden')" class="bg-white/5 hover:bg-white/10 text-white font-bold px-4 py-2 rounded-lg text-sm flex-1">Annuler</button>
      </div>
    </form>
  </div>
</div>

<script>
function openAddModal() {
    document.getElementById('modalEggTitle').textContent = 'Ajouter un Egg';
    document.getElementById('eggAction').value = 'add';
    document.getElementById('eggId').value = '';
    ['eggName','eggDocker','eggStartup','eggEnv'].forEach(id => document.getElementById(id).value = id === 'eggEnv' ? '{}' : '');
    document.getElementById('eggIcon').value = 'fas fa-server';
    document.getElementById('modalEgg').classList.remove('hidden');
}
function openEditModal(e) {
    document.getElementById('modalEggTitle').textContent = 'Modifier l\'Egg';
    document.getElementById('eggAction').value = 'edit';
    document.getElementById('eggId').value = e.id;
    document.getElementById('eggName').value = e.name;
    document.getElementById('eggPanelId').value = e.panel_egg_id;
    document.getElementById('eggNestId').value = e.panel_nest_id;
    document.getElementById('eggDocker').value = e.docker_image;
    document.getElementById('eggStartup').value = e.startup;
    document.getElementById('eggIcon').value = e.icon || 'fas fa-server';
    document.getElementById('eggEnv').value = e.env_vars || '{}';
    document.getElementById('modalEgg').classList.remove('hidden');
}
</script>

</body>
</html>
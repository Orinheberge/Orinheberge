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
        $id            = (int)($_POST['id']            ?? 0);
        $name          = trim($_POST['name']           ?? '');
        $panel_egg_id  = (int)($_POST['panel_egg_id']  ?? 0);
        $panel_nest_id = (int)($_POST['panel_nest_id'] ?? 0);
        $docker_image  = trim($_POST['docker_image']   ?? '');
        $startup       = trim($_POST['startup']        ?? '');
        $env_vars      = trim($_POST['env_vars']       ?? '{}');
        $icon          = trim($_POST['icon']           ?? 'fas fa-server');

        // Valider JSON
        if (!json_decode($env_vars)) $env_vars = '{}';

        if ($name && $panel_egg_id > 0 && $docker_image) {
            if ($action === 'add') {
                $pdo->prepare('INSERT INTO eggs (name,panel_egg_id,panel_nest_id,docker_image,startup,env_vars,icon) VALUES (?,?,?,?,?,?,?)')
                    ->execute([$name,$panel_egg_id,$panel_nest_id,$docker_image,$startup,$env_vars,$icon]);
                $flash = '<div class="bg-green-500/15 text-green-400 border border-green-500/25 p-3 rounded-xl text-sm mb-4">✅ Egg ajouté.</div>';
            } else {
                $pdo->prepare('UPDATE eggs SET name=?,panel_egg_id=?,panel_nest_id=?,docker_image=?,startup=?,env_vars=?,icon=? WHERE id=?')
                    ->execute([$name,$panel_egg_id,$panel_nest_id,$docker_image,$startup,$env_vars,$icon,$id]);
                $flash = '<div class="bg-green-500/15 text-green-400 border border-green-500/25 p-3 rounded-xl text-sm mb-4">✅ Egg modifié.</div>';
            }
        }
    }

    if ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare('UPDATE eggs SET is_active = 1 - is_active WHERE id=?')->execute([$id]);
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $used = $pdo->prepare('SELECT COUNT(*) FROM products WHERE egg_id=?');
        $used->execute([$id]);
        if ($used->fetchColumn() > 0) {
            $flash = '<div class="bg-red-500/15 text-red-400 border border-red-500/25 p-3 rounded-xl text-sm mb-4">❌ Cet egg est utilisé par des produits.</div>';
        } else {
            $pdo->prepare('DELETE FROM eggs WHERE id=?')->execute([$id]);
            $flash = '<div class="bg-green-500/15 text-green-400 border border-green-500/25 p-3 rounded-xl text-sm mb-4">✅ Egg supprimé.</div>';
        }
    }

    // Import automatique depuis le panel
    if ($action === 'import_from_panel') {
        $nest_id = (int)($_POST['nest_id'] ?? 0);
        if ($nest_id > 0 && $panel_url) {
            $ch = curl_init($panel_url . '/api/application/nests/' . $nest_id . '/eggs?include=variables');
            curl_setopt_array($ch,[CURLOPT_HTTPHEADER=>$headers_admin,CURLOPT_RETURNTRANSFER=>true,CURLOPT_SSL_VERIFYPEER=>false,CURLOPT_TIMEOUT=>10]);
            $res = json_decode(curl_exec($ch), true); curl_close($ch);
            $imported = 0;
            foreach (($res['data'] ?? []) as $egg) {
                $a = $egg['attributes'];
                $env = [];
                foreach (($a['relationships']['variables']['data'] ?? []) as $v) {
                    $env[$v['attributes']['env_variable']] = $v['attributes']['default_value'];
                }
                try {
                    $pdo->prepare('INSERT IGNORE INTO eggs (name,panel_egg_id,panel_nest_id,docker_image,startup,env_vars,icon) VALUES (?,?,?,?,?,?,?)')
                        ->execute([$a['name'],$a['id'],$nest_id,$a['docker_image'],$a['startup'],json_encode($env),'fas fa-server']);
                    $imported++;
                } catch(PDOException $e) {}
            }
            $flash = "<div class='bg-green-500/15 text-green-400 border border-green-500/25 p-3 rounded-xl text-sm mb-4'>✅ $imported egg(s) importé(s) depuis nest #$nest_id.</div>";
        }
    }
}

$eggs = $pdo->query('SELECT e.*, (SELECT COUNT(*) FROM products p WHERE p.egg_id=e.id) AS product_count FROM eggs e ORDER BY e.id')->fetchAll();

// Fetch nests from panel
$panel_nests = [];
if ($panel_url && $api_key_admin) {
    $ch = curl_init($panel_url . '/api/application/nests');
    curl_setopt_array($ch,[CURLOPT_HTTPHEADER=>$headers_admin,CURLOPT_RETURNTRANSFER=>true,CURLOPT_SSL_VERIFYPEER=>false,CURLOPT_TIMEOUT=>8]);
    $res = json_decode(curl_exec($ch), true); curl_close($ch);
    foreach (($res['data'] ?? []) as $nest) {
        $a = $nest['attributes'];
        $panel_nests[$a['id']] = '#'.$a['id'].' — '.$a['name'];
    }
}

$active_nav = 'eggs';
include $_SERVER['DOCUMENT_ROOT'] . '/inc/admin_layout.php';
?>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<div class="main-content">
  <div class="topbar">
    <div class="flex items-center gap-3">
    <button id="adminSidebarToggle" class="md:hidden text-gray-400 hover:text-white text-lg w-8" aria-label="Ouvrir le menu admin">
    <i class="fas fa-bars"></i>
</button>
      <div>
        <div class="text-sm font-bold text-white flex items-center gap-2"><i class="fas fa-egg text-amber-400 text-xs"></i> Gestion des Eggs</div>
        <div class="text-xs text-gray-500"><?= count($eggs) ?> egg(s) configuré(s)</div>
      </div>
    </div>
    <div class="flex gap-2">
      <?php if (!empty($panel_nests)): ?>
      <button onclick="document.getElementById('modalImport').classList.remove('hidden')" class="btn btn-ghost">
        <i class="fas fa-download text-xs"></i> Import depuis Panel
      </button>
      <?php endif; ?>
      <button onclick="openAddModal()" class="btn btn-primary">
        <i class="fas fa-plus text-xs"></i> Ajouter un Egg
      </button>
    </div>
  </div>

  <div class="content">
    <?= $flash ?>

    <div class="card overflow-hidden">
      <div class="px-5 py-4 border-b border-white/[0.05]">
        <span class="text-sm font-bold text-white flex items-center gap-2"><i class="fas fa-egg text-amber-400 text-xs"></i> Eggs configurés</span>
      </div>
      <?php if (empty($eggs)): ?>
        <div class="px-5 py-12 text-center text-gray-500 text-sm">Aucun egg. Ajoutez-en un ou importez depuis le panel.</div>
      <?php else: ?>
      <table class="tbl">
        <thead>
          <tr><th>ID</th><th>Nom</th><th>Egg Panel</th><th>Nest Panel</th><th>Image Docker</th><th>Produits</th><th>Statut</th><th>Actions</th></tr>
        </thead>
        <tbody>
          <?php foreach ($eggs as $e): ?>
          <tr>
            <td class="text-gray-500 font-mono text-xs">#<?= $e['id'] ?></td>
            <td>
              <div class="flex items-center gap-2">
                <i class="<?= htmlspecialchars($e['icon'] ?? 'fas fa-server') ?> text-sky-400 text-sm w-4 text-center"></i>
                <span class="font-semibold text-white"><?= htmlspecialchars($e['name']) ?></span>
              </div>
            </td>
            <td><span class="font-mono text-amber-400 bg-amber-500/10 px-2 py-0.5 rounded text-xs">egg:<?= $e['panel_egg_id'] ?></span></td>
            <td><span class="font-mono text-purple-400 bg-purple-500/10 px-2 py-0.5 rounded text-xs">nest:<?= $e['panel_nest_id'] ?></span></td>
            <td class="text-gray-400 font-mono text-xs max-w-[180px] truncate"><?= htmlspecialchars($e['docker_image']) ?></td>
            <td><span class="badge badge-blue"><?= $e['product_count'] ?></span></td>
            <td><?= $e['is_active'] ? '<span class="badge badge-green">Actif</span>' : '<span class="badge badge-gray">Inactif</span>' ?></td>
            <td>
              <div class="flex items-center gap-2">
                <button onclick='openEditModal(<?= htmlspecialchars(json_encode($e), ENT_QUOTES) ?>)' class="btn btn-ghost text-xs"><i class="fas fa-edit"></i></button>
                <form method="POST" class="inline">
                  <input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= $e['id'] ?>">
                  <button class="btn btn-ghost text-xs"><?= $e['is_active'] ? '<i class="fas fa-eye-slash"></i>' : '<i class="fas fa-eye"></i>' ?></button>
                </form>
                <form method="POST" class="inline" onsubmit="return confirm('Supprimer cet egg ?')">
                  <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $e['id'] ?>">
                  <button class="btn btn-danger text-xs"><i class="fas fa-trash"></i></button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Modal Ajouter/Editer Egg -->
<div id="modalEgg" class="hidden fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4">
  <div class="bg-[#161a22] border border-white/10 rounded-2xl w-full max-w-lg p-6 max-h-[90vh] overflow-y-auto">
    <div class="flex items-center justify-between mb-5">
      <h3 id="modalEggTitle" class="text-base font-bold text-white">Ajouter un Egg</h3>
      <button onclick="document.getElementById('modalEgg').classList.add('hidden')" class="text-gray-500 hover:text-white"><i class="fas fa-times"></i></button>
    </div>
    <form method="POST" class="space-y-4">
      <input type="hidden" name="action" id="eggAction" value="add">
      <input type="hidden" name="id"     id="eggId"     value="">
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-semibold text-gray-400 mb-1.5">Nom de l'egg</label>
          <input name="name" id="eggName" class="input" placeholder="ex: Minecraft Paper" required>
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-400 mb-1.5">Icône FontAwesome</label>
          <input name="icon" id="eggIcon" class="input" placeholder="fas fa-cube" value="fas fa-server">
        </div>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-semibold text-gray-400 mb-1.5">Egg ID (panel) <span class="text-red-400">*</span></label>
          <input name="panel_egg_id" id="eggPanelId" type="number" min="1" class="input" placeholder="ex: 2" required>
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-400 mb-1.5">Nest ID (panel) <span class="text-red-400">*</span></label>
          <input name="panel_nest_id" id="eggNestId" type="number" min="1" class="input" placeholder="ex: 1">
        </div>
      </div>
      <div>
        <label class="block text-xs font-semibold text-gray-400 mb-1.5">Image Docker</label>
        <input name="docker_image" id="eggDocker" class="input" placeholder="ghcr.io/pterodactyl/yolks:java_25" required>
      </div>
      <div>
        <label class="block text-xs font-semibold text-gray-400 mb-1.5">Commande de démarrage</label>
        <textarea name="startup" id="eggStartup" class="input font-mono text-xs" rows="3" placeholder="java -jar {{SERVER_JARFILE}} nogui"></textarea>
      </div>
      <div>
        <label class="block text-xs font-semibold text-gray-400 mb-1.5">Variables d'environnement <span class="text-gray-600">(JSON)</span></label>
        <textarea name="env_vars" id="eggEnv" class="input font-mono text-xs" rows="4" placeholder='{"SERVER_JARFILE":"server.jar"}'>{}</textarea>
      </div>
      <div class="flex gap-3 pt-2">
        <button type="submit" class="btn btn-primary flex-1">Sauvegarder</button>
        <button type="button" onclick="document.getElementById('modalEgg').classList.add('hidden')" class="btn btn-ghost flex-1">Annuler</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Import depuis Panel -->
<div id="modalImport" class="hidden fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4">
  <div class="bg-[#161a22] border border-white/10 rounded-2xl w-full max-w-sm p-6">
    <div class="flex items-center justify-between mb-5">
      <h3 class="text-base font-bold text-white">Importer depuis le Panel</h3>
      <button onclick="document.getElementById('modalImport').classList.add('hidden')" class="text-gray-500 hover:text-white"><i class="fas fa-times"></i></button>
    </div>
    <form method="POST" class="space-y-4">
      <input type="hidden" name="action" value="import_from_panel">
      <div>
        <label class="block text-xs font-semibold text-gray-400 mb-1.5">Choisir un Nest</label>
        <select name="nest_id" class="input">
          <?php foreach ($panel_nests as $id => $label): ?>
            <option value="<?= $id ?>"><?= htmlspecialchars($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="bg-amber-500/10 border border-amber-500/20 rounded-xl p-3 text-xs text-amber-300">
        ⚠ Les eggs déjà importés (même panel_egg_id) seront ignorés.
      </div>
      <div class="flex gap-3 pt-2">
        <button type="submit" class="btn btn-primary flex-1">Importer</button>
        <button type="button" onclick="document.getElementById('modalImport').classList.add('hidden')" class="btn btn-ghost flex-1">Annuler</button>
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
</body></html>

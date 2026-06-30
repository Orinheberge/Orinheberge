<?php
ini_set('display_errors',1); error_reporting(E_ALL);
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: /login/'); exit(); }

$pdo = new PDO('mysql:host=localhost;dbname=s43_orinheberge;charset=utf8mb4','root','1504',
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);

$stmt = $pdo->prepare('SELECT id,pseudo,firstname,lastname,avatar,is_admin FROM users WHERE id=? LIMIT 1');
$stmt->execute([$_SESSION['user_id']]); $admin = $stmt->fetch();
if (!$admin || !$admin['is_admin']) { http_response_code(403); die('403 Forbidden'); }

$cfg = [];
foreach ($pdo->query('SELECT `key`,`value` FROM settings') as $r) $cfg[$r['key']] = $r['value'];
$panel_url     = $cfg['panel_url']     ?? '';
$api_key_admin = $cfg['api_key_admin'] ?? '';
$headers_admin = ["Authorization: Bearer $api_key_admin","Accept: application/vnd.pterodactyl.v1+json","Content-Type: application/json"];

$flash = '';

// ── Actions POST ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name          = trim($_POST['name'] ?? '');
        $panel_node_id = (int)($_POST['panel_node_id'] ?? 0);
        $location_id   = (int)($_POST['location_id']   ?? 1);
        $fqdn          = trim($_POST['fqdn'] ?? '');
        if ($name && $panel_node_id > 0) {
            try {
                $pdo->prepare('INSERT INTO nodes (name,panel_node_id,location_id,fqdn) VALUES (?,?,?,?)')
                    ->execute([$name, $panel_node_id, $location_id, $fqdn]);
                $flash = '<div class="bg-green-500/15 text-green-400 border border-green-500/25 p-3 rounded-xl text-sm mb-4">✅ Node ajouté.</div>';
            } catch (PDOException $e) {
                $flash = '<div class="bg-red-500/15 text-red-400 border border-red-500/25 p-3 rounded-xl text-sm mb-4">❌ Erreur : panel_node_id déjà existant.</div>';
            }
        }
    }

    if ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare('UPDATE nodes SET is_active = 1 - is_active WHERE id=?')->execute([$id]);
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        // Vérifier qu'aucun produit n'utilise ce node
        $used = $pdo->prepare('SELECT COUNT(*) FROM products WHERE node_id=?');
        $used->execute([$id]);
        if ($used->fetchColumn() > 0) {
            $flash = '<div class="bg-red-500/15 text-red-400 border border-red-500/25 p-3 rounded-xl text-sm mb-4">❌ Ce node est utilisé par des produits. Modifiez-les d\'abord.</div>';
        } else {
            $pdo->prepare('DELETE FROM nodes WHERE id=?')->execute([$id]);
            $flash = '<div class="bg-green-500/15 text-green-400 border border-green-500/25 p-3 rounded-xl text-sm mb-4">✅ Node supprimé.</div>';
        }
    }
}

// Charger les nodes et les nodes du panel
$nodes = $pdo->query('SELECT n.*, (SELECT COUNT(*) FROM products p WHERE p.node_id=n.id) AS product_count FROM nodes n ORDER BY n.id')->fetchAll();

// Fetch nodes from panel for reference
$panel_nodes_raw = [];
if ($panel_url && $api_key_admin) {
    $ch = curl_init($panel_url . '/api/application/nodes');
    curl_setopt_array($ch,[CURLOPT_HTTPHEADER=>$headers_admin,CURLOPT_RETURNTRANSFER=>true,CURLOPT_SSL_VERIFYPEER=>false,CURLOPT_TIMEOUT=>8]);
    $res = curl_exec($ch); curl_close($ch);
    $panel_nodes_data = json_decode($res, true);
    foreach (($panel_nodes_data['data'] ?? []) as $pn) {
        $a = $pn['attributes'];
        $panel_nodes_raw[$a['id']] = $a['name'] . ' (id:'.$a['id'].', '.$a['fqdn'].')';
    }
}

$active_nav = 'nodes';
include $_SERVER['DOCUMENT_ROOT'] . '/inc/admin_layout.php';
?>
<div class="main-content">
  <div class="topbar">
    <div class="flex items-center gap-3">
      <button onclick="document.getElementById('sidebar').classList.toggle('open')" class="md:hidden text-gray-400 text-lg"><i class="fas fa-bars"></i></button>
      <div>
        <div class="text-sm font-bold text-white flex items-center gap-2"><i class="fas fa-network-wired text-sky-400 text-xs"></i> Gestion des Nodes</div>
        <div class="text-xs text-gray-500"><?= count($nodes) ?> node(s) configuré(s)</div>
      </div>
    </div>
    <button onclick="document.getElementById('modalAdd').classList.remove('hidden')" class="btn btn-primary">
      <i class="fas fa-plus text-xs"></i> Ajouter un Node
    </button>
  </div>

  <div class="content">
    <?= $flash ?>

    <!-- Info panel nodes -->
    <?php if (!empty($panel_nodes_raw)): ?>
    <div class="bg-sky-500/5 border border-sky-500/15 rounded-xl p-4 mb-6 text-sm text-sky-300">
      <strong><i class="fas fa-info-circle mr-2"></i>Nodes disponibles sur le panel :</strong>
      <ul class="mt-2 space-y-1 font-mono text-xs text-gray-400">
        <?php foreach ($panel_nodes_raw as $id => $label): ?>
          <li>• ID <b class="text-sky-400"><?= $id ?></b> — <?= htmlspecialchars($label) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>

    <!-- Table des nodes -->
    <div class="card overflow-hidden">
      <div class="px-5 py-4 border-b border-white/[0.05] flex items-center gap-2">
        <i class="fas fa-network-wired text-sky-400 text-xs"></i>
        <span class="text-sm font-bold text-white">Nodes configurés</span>
      </div>
      <?php if (empty($nodes)): ?>
        <div class="px-5 py-12 text-center text-gray-500 text-sm">Aucun node configuré. Ajoutez-en un via le bouton ci-dessus.</div>
      <?php else: ?>
      <table class="tbl">
        <thead>
          <tr>
            <th>ID</th><th>Nom</th><th>Node Panel ID</th><th>FQDN</th><th>Produits</th><th>Statut</th><th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($nodes as $n): ?>
          <tr>
            <td class="text-gray-500 font-mono text-xs">#<?= $n['id'] ?></td>
            <td class="font-semibold text-white"><?= htmlspecialchars($n['name']) ?></td>
            <td><span class="font-mono text-sky-400 bg-sky-500/10 px-2 py-0.5 rounded text-xs"><?= $n['panel_node_id'] ?></span></td>
            <td class="text-gray-400 text-xs font-mono"><?= htmlspecialchars($n['fqdn'] ?? '—') ?></td>
            <td><span class="badge badge-blue"><?= $n['product_count'] ?> produit(s)</span></td>
            <td>
              <?php if ($n['is_active']): ?>
                <span class="badge badge-green"><i class="fas fa-circle text-[8px]"></i> Actif</span>
              <?php else: ?>
                <span class="badge badge-gray"><i class="fas fa-circle text-[8px]"></i> Inactif</span>
              <?php endif; ?>
            </td>
            <td>
              <div class="flex items-center gap-2">
                <form method="POST" class="inline">
                  <input type="hidden" name="action" value="toggle">
                  <input type="hidden" name="id" value="<?= $n['id'] ?>">
                  <button class="btn btn-ghost text-xs"><?= $n['is_active'] ? 'Désactiver' : 'Activer' ?></button>
                </form>
                <form method="POST" class="inline" onsubmit="return confirm('Supprimer ce node ?')">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= $n['id'] ?>">
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

<!-- Modal Ajouter Node -->
<div id="modalAdd" class="hidden fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4">
  <div class="bg-[#161a22] border border-white/10 rounded-2xl w-full max-w-md p-6">
    <div class="flex items-center justify-between mb-5">
      <h3 class="text-base font-bold text-white">Ajouter un Node</h3>
      <button onclick="document.getElementById('modalAdd').classList.add('hidden')" class="text-gray-500 hover:text-white"><i class="fas fa-times"></i></button>
    </div>
    <form method="POST" class="space-y-4">
      <input type="hidden" name="action" value="add">
      <div>
        <label class="block text-xs font-semibold text-gray-400 mb-1.5">Nom du node</label>
        <input name="name" class="input" placeholder="ex: Node 1 — Web/Bot" required>
      </div>
      <div>
        <label class="block text-xs font-semibold text-gray-400 mb-1.5">ID du node sur le Panel <span class="text-sky-400">(voir liste ci-dessus)</span></label>
        <input name="panel_node_id" type="number" min="1" class="input" placeholder="ex: 1" required>
      </div>
      <div>
        <label class="block text-xs font-semibold text-gray-400 mb-1.5">Location ID <span class="text-gray-600">(défaut: 1)</span></label>
        <input name="location_id" type="number" min="1" value="1" class="input">
      </div>
      <div>
        <label class="block text-xs font-semibold text-gray-400 mb-1.5">FQDN / IP</label>
        <input name="fqdn" class="input" placeholder="ex: node1.orinstone.deepstone.fr">
      </div>
      <div class="flex gap-3 pt-2">
        <button type="submit" class="btn btn-primary flex-1">Ajouter</button>
        <button type="button" onclick="document.getElementById('modalAdd').classList.add('hidden')" class="btn btn-ghost flex-1">Annuler</button>
      </div>
    </form>
  </div>
</div>
</body></html>

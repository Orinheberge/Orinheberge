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
$panel_url = $cfg['panel_url'] ?? '';

$flash = '';

// ── Actions POST ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $id          = (int)($_POST['id']          ?? 0);
        $slug        = preg_replace('/[^a-z0-9\-]/', '', strtolower(trim($_POST['slug'] ?? '')));
        $name        = trim($_POST['name']         ?? '');
        $description = trim($_POST['description']  ?? '');
        $type        = ($_POST['type'] ?? 'paid') === 'free' ? 'free' : 'paid';
        $price       = (float)($_POST['price']     ?? 0);
        $node_id     = (int)($_POST['node_id']     ?? 0);   // node principal (compat)
        $extra_nodes = array_map('intval', (array)($_POST['extra_nodes'] ?? []));
        $egg_id      = (int)($_POST['egg_id']      ?? 0);
        $ram         = (int)($_POST['ram']         ?? 512);
        $disk        = (int)($_POST['disk']        ?? 5000);
        $cpu         = (int)($_POST['cpu']         ?? 100);
        $databases   = (int)($_POST['databases']   ?? 1);
        $backups     = (int)($_POST['backups']     ?? 1);
        $allocations = (int)($_POST['allocations'] ?? 1);
        $env_override= trim($_POST['env_override'] ?? '');
        $sort_order  = (int)($_POST['sort_order']  ?? 0);
        $is_active   = isset($_POST['is_active']) ? 1 : 0;

        if (!$env_override || !json_decode($env_override)) $env_override = null;

        if ($name && $slug && $node_id > 0 && $egg_id > 0) {
            try {
                if ($action === 'add') {
                    $pdo->prepare('INSERT INTO products (slug,name,description,type,price,node_id,egg_id,ram,disk,cpu,`databases`,backups,allocations,env_override,sort_order,is_active) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
                        ->execute([$slug,$name,$description,$type,$price,$node_id,$egg_id,$ram,$disk,$cpu,$databases,$backups,$allocations,$env_override,$sort_order,$is_active]);
                    $new_id = (int)$pdo->lastInsertId();
                } else {
                    $pdo->prepare('UPDATE products SET slug=?,name=?,description=?,type=?,price=?,node_id=?,egg_id=?,ram=?,disk=?,cpu=?,`databases`=?,backups=?,allocations=?,env_override=?,sort_order=?,is_active=? WHERE id=?')
                        ->execute([$slug,$name,$description,$type,$price,$node_id,$egg_id,$ram,$disk,$cpu,$databases,$backups,$allocations,$env_override,$sort_order,$is_active,$id]);
                    $new_id = $id;
                }

                // Synchroniser product_nodes
                $pdo->prepare('DELETE FROM product_nodes WHERE product_id=?')->execute([$new_id]);
                // Toujours inclure le node principal + les extra_nodes
                $all_nodes = array_unique(array_filter(array_merge([$node_id], $extra_nodes)));
                $ins = $pdo->prepare('INSERT IGNORE INTO product_nodes (product_id,node_id) VALUES (?,?)');
                foreach ($all_nodes as $nid) { if ($nid > 0) $ins->execute([$new_id, $nid]); }

                $flash = '<div class="bg-green-500/15 text-green-400 border border-green-500/25 p-3 rounded-xl text-sm mb-4">✅ Produit ' . ($action === 'add' ? 'créé' : 'modifié') . '.</div>';
            } catch (PDOException $e) {
                $flash = '<div class="bg-red-500/15 text-red-400 border border-red-500/25 p-3 rounded-xl text-sm mb-4">❌ Erreur : ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
        } else {
            $flash = '<div class="bg-red-500/15 text-red-400 border border-red-500/25 p-3 rounded-xl text-sm mb-4">❌ Champs obligatoires manquants.</div>';
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare('DELETE FROM products WHERE id=?')->execute([$id]);
        $flash = '<div class="bg-green-500/15 text-green-400 border border-green-500/25 p-3 rounded-xl text-sm mb-4">✅ Produit supprimé.</div>';
    }

    if ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare('UPDATE products SET is_active = 1 - is_active WHERE id=?')->execute([$id]);
    }
}

// Charger données
$products = $pdo->query('
    SELECT p.*, n.name AS node_name, e.name AS egg_name, e.icon AS egg_icon
    FROM products p
    LEFT JOIN nodes n ON n.id = p.node_id
    LEFT JOIN eggs  e ON e.id = p.egg_id
    ORDER BY p.sort_order, p.id
')->fetchAll();

// Charger nodes liés par produit
$pn_rows = $pdo->query('SELECT product_id, node_id FROM product_nodes')->fetchAll();
$product_nodes_map = [];
foreach ($pn_rows as $r) $product_nodes_map[$r['product_id']][] = (int)$r['node_id'];

$nodes = $pdo->query('SELECT id,name,fqdn FROM nodes WHERE is_active=1 ORDER BY id')->fetchAll();
$eggs  = $pdo->query('SELECT id,name,icon FROM eggs  WHERE is_active=1 ORDER BY id')->fetchAll();

$active_nav = 'products';
include $_SERVER['DOCUMENT_ROOT'] . '/inc/admin_layout.php';
?>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<div class="main-content">
  <div class="topbar">
    <div class="flex items-center gap-3">
      <button onclick="document.getElementById('sidebar').classList.toggle('open')" class="md:hidden text-gray-400 text-lg"><i class="fas fa-bars"></i></button>
      <div>
        <div class="text-sm font-bold text-white flex items-center gap-2"><i class="fas fa-box text-green-400 text-xs"></i> Gestion des Produits</div>
        <div class="text-xs text-gray-500"><?= count($products) ?> produit(s)</div>
      </div>
    </div>
    <button onclick="openAddModal()" class="btn btn-primary">
      <i class="fas fa-plus text-xs"></i> Ajouter un Produit
    </button>
  </div>

  <div class="content">
    <?= $flash ?>

    <!-- Stats rapides -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
      <?php
        $free_count   = count(array_filter($products, fn($p) => $p['type'] === 'free'));
        $paid_count   = count(array_filter($products, fn($p) => $p['type'] === 'paid'));
        $active_count = count(array_filter($products, fn($p) => $p['is_active']));
      ?>
      <div class="card p-4"><div class="text-xs text-gray-500 mb-1">Total</div><div class="text-2xl font-black text-white"><?= count($products) ?></div></div>
      <div class="card p-4"><div class="text-xs text-gray-500 mb-1">Payants</div><div class="text-2xl font-black text-amber-400"><?= $paid_count ?></div></div>
      <div class="card p-4"><div class="text-xs text-gray-500 mb-1">Gratuits</div><div class="text-2xl font-black text-blue-400"><?= $free_count ?></div></div>
      <div class="card p-4"><div class="text-xs text-gray-500 mb-1">Actifs</div><div class="text-2xl font-black text-green-400"><?= $active_count ?></div></div>
    </div>

    <div class="card overflow-hidden">
      <div class="px-5 py-4 border-b border-white/[0.05]">
        <span class="text-sm font-bold text-white flex items-center gap-2"><i class="fas fa-box text-green-400 text-xs"></i> Liste des produits</span>
      </div>
      <?php if (empty($products)): ?>
        <div class="px-5 py-12 text-center text-gray-500 text-sm">Aucun produit. Ajoutez-en un via le bouton ci-dessus.</div>
      <?php else: ?>
      <div class="overflow-x-auto">
      <table class="tbl">
        <thead>
          <tr><th>Slug</th><th>Nom</th><th>Type</th><th>Prix</th><th>Nodes disponibles</th><th>Egg</th><th>RAM</th><th>CPU</th><th>Statut</th><th>Actions</th></tr>
        </thead>
        <tbody>
          <?php foreach ($products as $p):
            $p_nodes = $product_nodes_map[$p['id']] ?? [$p['node_id']];
            // Noms des nodes liés
            $node_names = [];
            foreach ($nodes as $n) {
                if (in_array($n['id'], $p_nodes)) $node_names[] = $n['name'];
            }
          ?>
          <tr>
            <td class="font-mono text-xs text-gray-400"><?= htmlspecialchars($p['slug']) ?></td>
            <td class="font-semibold text-white"><?= htmlspecialchars($p['name']) ?></td>
            <td><?= $p['type'] === 'free' ? '<span class="badge badge-blue">Gratuit</span>' : '<span class="badge badge-amber">Payant</span>' ?></td>
            <td class="font-mono text-green-400 font-bold"><?= $p['type'] === 'free' ? '—' : number_format($p['price'],2).'€' ?></td>
            <td>
              <div class="flex flex-wrap gap-1">
                <?php foreach ($node_names as $nn): ?>
                  <span class="text-[10px] bg-sky-500/10 text-sky-400 border border-sky-500/20 px-2 py-0.5 rounded-full font-semibold"><?= htmlspecialchars($nn) ?></span>
                <?php endforeach; ?>
                <?php if (empty($node_names)): ?>
                  <span class="text-[10px] text-gray-600">—</span>
                <?php endif; ?>
              </div>
            </td>
            <td>
              <div class="flex items-center gap-1.5">
                <i class="<?= htmlspecialchars($p['egg_icon'] ?? 'fas fa-server') ?> text-sky-400 text-xs"></i>
                <span class="text-gray-300 text-xs"><?= htmlspecialchars($p['egg_name'] ?? '?') ?></span>
              </div>
            </td>
            <td class="text-gray-400 text-xs"><?= number_format($p['ram']).'MB' ?></td>
            <td class="text-gray-400 text-xs"><?= $p['cpu'] ?>%</td>
            <td><?= $p['is_active'] ? '<span class="badge badge-green">Actif</span>' : '<span class="badge badge-gray">Inactif</span>' ?></td>
            <td>
              <div class="flex items-center gap-1.5">
                <button onclick='openEditModal(<?= htmlspecialchars(json_encode(array_merge($p,['product_nodes'=>$product_nodes_map[$p['id']] ?? []])), ENT_QUOTES) ?>)' class="btn btn-ghost text-xs"><i class="fas fa-edit"></i></button>
                <form method="POST" class="inline">
                  <input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= $p['id'] ?>">
                  <button class="btn btn-ghost text-xs"><?= $p['is_active'] ? '<i class="fas fa-eye-slash"></i>' : '<i class="fas fa-eye"></i>' ?></button>
                </form>
                <form method="POST" class="inline" onsubmit="return confirm('Supprimer « <?= htmlspecialchars($p['name'],ENT_QUOTES) ?> » ?')">
                  <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $p['id'] ?>">
                  <button class="btn btn-danger text-xs"><i class="fas fa-trash"></i></button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Modal Produit -->
<div id="modalProduct" class="hidden fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4" style="backdrop-filter:blur(6px)">
  <div class="bg-[#161a22] border border-white/10 rounded-2xl w-full max-w-2xl p-6 max-h-[90vh] overflow-y-auto">
    <div class="flex items-center justify-between mb-5">
      <h3 id="modalProductTitle" class="text-base font-bold text-white">Ajouter un Produit</h3>
      <button onclick="document.getElementById('modalProduct').classList.add('hidden')" class="text-gray-500 hover:text-white"><i class="fas fa-times"></i></button>
    </div>
    <form method="POST" class="space-y-4">
      <input type="hidden" name="action" id="pAction" value="add">
      <input type="hidden" name="id"     id="pId"     value="">

      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-semibold text-gray-400 mb-1.5">Nom <span class="text-red-400">*</span></label>
          <input name="name" id="pName" class="input" required>
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-400 mb-1.5">Slug (URL) <span class="text-red-400">*</span></label>
          <input name="slug" id="pSlug" class="input font-mono" placeholder="ex: minecraft-basic" required>
        </div>
      </div>

      <div>
        <label class="block text-xs font-semibold text-gray-400 mb-1.5">Description</label>
        <input name="description" id="pDesc" class="input" placeholder="Courte description affichée en boutique">
      </div>

      <div class="grid grid-cols-3 gap-3">
        <div>
          <label class="block text-xs font-semibold text-gray-400 mb-1.5">Type</label>
          <select name="type" id="pType" class="input" onchange="togglePrice(this.value)">
            <option value="paid">Payant</option>
            <option value="free">Gratuit</option>
          </select>
        </div>
        <div id="priceField">
          <label class="block text-xs font-semibold text-gray-400 mb-1.5">Prix (€/mois)</label>
          <input name="price" id="pPrice" type="number" step="0.01" min="0" class="input" value="0.00">
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-400 mb-1.5">Ordre d'affichage</label>
          <input name="sort_order" id="pSort" type="number" class="input" value="0">
        </div>
      </div>

      <!-- Node principal -->
      <div>
        <label class="block text-xs font-semibold text-gray-400 mb-1.5">Node par défaut <span class="text-red-400">*</span></label>
        <select name="node_id" id="pNodeId" class="input">
          <?php foreach ($nodes as $n): ?>
            <option value="<?= $n['id'] ?>"><?= htmlspecialchars($n['name']) ?><?= $n['fqdn'] ? ' — '.$n['fqdn'] : '' ?></option>
          <?php endforeach; ?>
        </select>
        <p class="text-[11px] text-gray-600 mt-1">Le serveur sera déployé sur ce node si le client ne choisit pas.</p>
      </div>

      <!-- Nodes supplémentaires (choix client) -->
      <div>
        <label class="block text-xs font-semibold text-gray-400 mb-2">Nodes disponibles au choix du client</label>
        <div id="pNodesCheckboxes" class="grid grid-cols-2 gap-2">
          <?php foreach ($nodes as $n): ?>
          <label class="flex items-center gap-2 bg-white/[0.03] border border-white/[0.07] rounded-lg px-3 py-2 cursor-pointer hover:bg-white/[0.06] transition">
            <input type="checkbox" name="extra_nodes[]" value="<?= $n['id'] ?>" class="node-cb w-4 h-4 accent-sky-500" data-nid="<?= $n['id'] ?>">
            <span class="text-xs text-gray-300 font-medium"><?= htmlspecialchars($n['name']) ?></span>
            <?php if ($n['fqdn']): ?>
            <span class="text-[10px] text-gray-600 font-mono truncate"><?= htmlspecialchars($n['fqdn']) ?></span>
            <?php endif; ?>
          </label>
          <?php endforeach; ?>
        </div>
        <p class="text-[11px] text-gray-600 mt-1.5">Cochez tous les nodes sur lesquels cette offre peut être déployée. Le client choisira au moment de la commande.</p>
      </div>

      <div>
        <label class="block text-xs font-semibold text-gray-400 mb-1.5">Egg <span class="text-red-400">*</span></label>
        <select name="egg_id" id="pEggId" class="input">
          <?php foreach ($eggs as $e): ?>
            <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="grid grid-cols-3 gap-3">
        <div>
          <label class="block text-xs font-semibold text-gray-400 mb-1.5">RAM (MB)</label>
          <input name="ram" id="pRam" type="number" min="128" class="input" value="512">
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-400 mb-1.5">Disk (MB)</label>
          <input name="disk" id="pDisk" type="number" min="512" class="input" value="5000">
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-400 mb-1.5">CPU (%)</label>
          <input name="cpu" id="pCpu" type="number" min="10" class="input" value="100">
        </div>
      </div>

      <div class="grid grid-cols-3 gap-3">
        <div>
          <label class="block text-xs font-semibold text-gray-400 mb-1.5">Bases de données</label>
          <input name="databases" id="pDb" type="number" min="0" class="input" value="1">
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-400 mb-1.5">Backups</label>
          <input name="backups" id="pBk" type="number" min="0" class="input" value="1">
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-400 mb-1.5">Allocations</label>
          <input name="allocations" id="pAlloc" type="number" min="1" class="input" value="1">
        </div>
      </div>

      <div>
        <label class="block text-xs font-semibold text-gray-400 mb-1.5">Surcharge variables env <span class="text-gray-600">(JSON optionnel)</span></label>
        <textarea name="env_override" id="pEnv" class="input font-mono text-xs" rows="3" placeholder='{"MINECRAFT_VERSION":"1.21"}'></textarea>
      </div>

      <div class="flex items-center gap-2">
        <input type="checkbox" name="is_active" id="pActive" value="1" checked class="w-4 h-4 accent-sky-500">
        <label for="pActive" class="text-xs text-gray-300 font-medium">Produit actif (visible en boutique)</label>
      </div>

      <div class="flex gap-3 pt-2">
        <button type="submit" class="btn btn-primary flex-1">Sauvegarder</button>
        <button type="button" onclick="document.getElementById('modalProduct').classList.add('hidden')" class="btn btn-ghost flex-1">Annuler</button>
      </div>
    </form>
  </div>
</div>

<script>
function togglePrice(v) {
    document.getElementById('priceField').style.opacity = v === 'free' ? '0.3' : '1';
}
function resetCheckboxes() {
    document.querySelectorAll('.node-cb').forEach(cb => cb.checked = false);
}
function openAddModal() {
    document.getElementById('modalProductTitle').textContent = 'Ajouter un Produit';
    document.getElementById('pAction').value = 'add';
    document.getElementById('pId').value = '';
    ['pName','pSlug','pDesc','pEnv'].forEach(id => document.getElementById(id).value = '');
    document.getElementById('pType').value  = 'paid';
    document.getElementById('pPrice').value = '0.00';
    document.getElementById('pSort').value  = '0';
    document.getElementById('pRam').value   = '512';
    document.getElementById('pDisk').value  = '5000';
    document.getElementById('pCpu').value   = '100';
    document.getElementById('pDb').value    = '1';
    document.getElementById('pBk').value    = '1';
    document.getElementById('pAlloc').value = '1';
    document.getElementById('pActive').checked = true;
    resetCheckboxes();
    togglePrice('paid');
    document.getElementById('modalProduct').classList.remove('hidden');
}
function openEditModal(p) {
    document.getElementById('modalProductTitle').textContent = 'Modifier le Produit';
    document.getElementById('pAction').value = 'edit';
    document.getElementById('pId').value     = p.id;
    document.getElementById('pName').value   = p.name;
    document.getElementById('pSlug').value   = p.slug;
    document.getElementById('pDesc').value   = p.description || '';
    document.getElementById('pType').value   = p.type;
    document.getElementById('pPrice').value  = p.price;
    document.getElementById('pSort').value   = p.sort_order;
    document.getElementById('pNodeId').value = p.node_id;
    document.getElementById('pEggId').value  = p.egg_id;
    document.getElementById('pRam').value    = p.ram;
    document.getElementById('pDisk').value   = p.disk;
    document.getElementById('pCpu').value    = p.cpu;
    document.getElementById('pDb').value     = p.databases;
    document.getElementById('pBk').value     = p.backups;
    document.getElementById('pAlloc').value  = p.allocations;
    document.getElementById('pEnv').value    = p.env_override || '';
    document.getElementById('pActive').checked = p.is_active == 1;
    // Restaurer les checkboxes des nodes
    resetCheckboxes();
    const pn = p.product_nodes || [];
    document.querySelectorAll('.node-cb').forEach(cb => {
        cb.checked = pn.includes(parseInt(cb.dataset.nid));
    });
    togglePrice(p.type);
    document.getElementById('modalProduct').classList.remove('hidden');
}
</script>
</body></html>

<?php
ini_set('display_errors', 1); error_reporting(E_ALL);
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: /login/'); exit(); }

$pdo = new PDO('mysql:host=localhost;dbname=s43_orinheberge;charset=utf8mb4','root','1504',
    [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);

$stmt = $pdo->prepare('SELECT id,pseudo,firstname,avatar,is_admin FROM users WHERE id=? LIMIT 1');
$stmt->execute([$_SESSION['user_id']]); $admin = $stmt->fetch();
if (!$admin || !$admin['is_admin']) { http_response_code(403); die('403 Forbidden'); }

$flash = '';

// ── Actions POST ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {

        $id            = (int)($_POST['id'] ?? 0);
        $product_id    = !empty($_POST['product_id']) ? (int)$_POST['product_id'] : null;
        $category_slug = strtolower(trim($_POST['category_slug'] ?? ''));
        $name_key      = trim($_POST['name_key'] ?? '');
        $icon          = trim($_POST['icon'] ?? 'fas fa-server');
        $image_url     = trim($_POST['image_url'] ?? null);
        $sort_order    = (int)($_POST['sort_order'] ?? 0);
        $is_active     = isset($_POST['is_active']) ? 1 : 0;

        if ($category_slug && $name_key) {
            try {
                if ($action == 'add') {
                    $pdo->prepare("
                        INSERT INTO categories_products
                        (product_id, category_slug, name_key, icon, image_url, sort_order, is_active)
                        VALUES (?,?,?,?,?,?,?)
                    ")->execute([$product_id, $category_slug, $name_key, $icon, $image_url, $sort_order, $is_active]);

                    $flash = '<div class="bg-green-500/15 text-green-400 border border-green-500/25 p-3 rounded-xl text-sm mb-4">
                    ✅ Catégorie créée avec succès.
                    </div>';
                } else {
                    $pdo->prepare("
                        UPDATE categories_products
                        SET product_id=?, category_slug=?, name_key=?, icon=?, image_url=?, sort_order=?, is_active=?
                        WHERE id=?
                    ")->execute([$product_id, $category_slug, $name_key, $icon, $image_url, $sort_order, $is_active, $id]);

                    $flash = '<div class="bg-green-500/15 text-green-400 border border-green-500/25 p-3 rounded-xl text-sm mb-4">
                    ✅ Catégorie modifiée avec succès.
                    </div>';
                }
            } catch(PDOException $e){
                $flash = '<div class="bg-red-500/15 text-red-400 border border-red-500/25 p-3 rounded-xl text-sm mb-4">
                ❌ '.$e->getMessage().'
                </div>';
            }
        } else {
            $flash = '<div class="bg-red-500/15 text-red-400 border border-red-500/25 p-3 rounded-xl text-sm mb-4">
            ❌ Tous les champs obligatoires doivent être remplis.
            </div>';
        }
    }

    if ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("UPDATE categories_products SET is_active = NOT is_active WHERE id = ?")->execute([$id]);
        $flash = '<div class="bg-green-500/15 text-green-400 border border-green-500/25 p-3 rounded-xl text-sm mb-4">
        🔄 Statut de la catégorie mis à jour.
        </div>';
    }

    if ($action == 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("DELETE FROM categories_products WHERE id=?")->execute([$id]);
        $flash = '<div class="bg-green-500/15 text-green-400 border border-green-500/25 p-3 rounded-xl text-sm mb-4">
        ✅ Catégorie supprimée.
        </div>';
    }
}

// Charger les catégories de produits
$categories = $pdo->query('
    SELECT cp.*, p.name AS product_name 
    FROM categories_products cp
    LEFT JOIN products p ON p.id = cp.product_id
    ORDER BY cp.sort_order, cp.id
')->fetchAll();

// Charger les produits existants pour la liste déroulante du modal
$available_products = $pdo->query('SELECT id, name FROM products ORDER BY name')->fetchAll();

$active_nav = 'categories';
include $_SERVER['DOCUMENT_ROOT'] . '/inc/admin_layout.php';
?>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<div class="main-content">
  <div class="topbar">
    <div>
      <div class="text-sm font-bold text-white flex items-center gap-2"><i class="fas fa-tags text-green-400 text-xs"></i> Gestion des Catégories</div>
      <div class="text-xs text-gray-500"><?= count($categories) ?> catégorie(s) configurée(s)</div>
    </div>
    <button onclick="openAddModal()" class="btn btn-primary">
      <i class="fas fa-plus text-xs"></i> Ajouter une Catégorie
    </button>
  </div>

  <div class="content">
    <?= $flash ?>

    <div class="card overflow-hidden">
      <div class="px-5 py-4 border-b border-white/[0.05]">
        <span class="text-sm font-bold text-white flex items-center gap-2"><i class="fas fa-list text-green-400 text-xs"></i> Liste des catégories</span>
      </div>
      <?php if (empty($categories)): ?>
        <div class="px-5 py-12 text-center text-gray-500 text-sm">Aucune catégorie trouvée.</div>
      <?php else: ?>
      <div class="overflow-x-auto">
      <table class="tbl">
        <thead>
          <tr><th>Slug</th><th>Clé de Nom</th><th>Icône</th><th>Produit Lié</th><th>Ordre</th><th>Statut</th><th>Actions</th></tr>
        </thead>
        <tbody>
          <?php foreach ($categories as $c): ?>
          <tr>
            <td class="font-mono text-xs text-gray-400"><?= htmlspecialchars($c['category_slug']) ?></td>
            <td class="font-semibold text-white"><?= htmlspecialchars($c['name_key']) ?></td>
            <td><i class="<?= htmlspecialchars($c['icon']) ?> text-sky-400 mr-2"></i><?= htmlspecialchars($c['icon']) ?></td>
            <td>
              <?php if ($c['product_id']): ?>
                <span class="text-xs bg-green-500/10 text-green-400 border border-green-500/20 px-2 py-0.5 rounded-full font-medium">
                  <?= htmlspecialchars($c['product_name']) ?> (ID: <?= $c['product_id'] ?>)
                </span>
              <?php else: ?>
                <span class="text-xs text-gray-600">Aucun (Générique)</span>
              <?php endif; ?>
            </td>
            <td class="text-gray-400 text-xs"><?= $c['sort_order'] ?></td>
            <td><?= !empty($c['is_active']) ? '<span class="badge badge-green">Actif</span>' : '<span class="badge badge-gray">Inactif</span>' ?></td>
            <td>
              <div class="flex items-center gap-1.5">
                <button onclick='openEditModal(<?= htmlspecialchars(json_encode($c), ENT_QUOTES) ?>)' class="btn btn-ghost text-xs"><i class="fas fa-edit"></i></button>
                
                <form method="POST" class="inline">
                  <input type="hidden" name="action" value="toggle">
                  <input type="hidden" name="id" value="<?= $c['id'] ?>">
                  <button type="submit" class="btn btn-ghost text-xs" title="<?= !empty($c['is_active']) ? 'Désactiver' : 'Activer' ?>">
                    <?= !empty($c['is_active']) ? '<i class="fas fa-eye-slash text-amber-500"></i>' : '<i class="fas fa-eye text-green-400"></i>' ?>
                  </button>
                </form>

                <form method="POST" class="inline" onsubmit="return confirm('Supprimer la catégorie « <?= htmlspecialchars($c['category_slug'], ENT_QUOTES) ?> » ?')">
                  <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $c['id'] ?>">
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

<div id="modalCategory" class="hidden fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4" style="backdrop-filter:blur(6px)">
  <div class="bg-[#161a22] border border-white/10 rounded-2xl w-full max-w-xl p-6 max-h-[90vh] overflow-y-auto">
    <div class="flex items-center justify-between mb-5">
      <h3 id="modalCategoryTitle" class="text-base font-bold text-white">Ajouter une Catégorie</h3>
      <button onclick="document.getElementById('modalCategory').classList.add('hidden')" class="text-gray-500 hover:text-white"><i class="fas fa-times"></i></button>
    </div>
    <form method="POST" class="space-y-4">
      <input type="hidden" name="action" id="cAction" value="add">
      <input type="hidden" name="id"     id="cId"     value="">

      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-semibold text-gray-400 mb-1.5">Slug Catégorie <span class="text-red-400">*</span></label>
          <input name="category_slug" id="cSlug" class="input font-mono" placeholder="ex: minecraft" required>
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-400 mb-1.5">Clé de Traduction <span class="text-red-400">*</span></label>
          <input name="name_key" id="cNameKey" class="input" placeholder="ex: cat.minecraft.name" required>
        </div>
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-semibold text-gray-400 mb-1.5">Icône FontAwesome</label>
          <input name="icon" id="cIcon" class="input" value="fas fa-server" placeholder="ex: fas fa-cube">
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-400 mb-1.5">Ordre d'affichage</label>
          <input name="sort_order" id="cSort" type="number" class="input" value="0">
        </div>
      </div>

      <div>
        <label class="block text-xs font-semibold text-gray-400 mb-1.5">Produit Lié <span class="text-gray-600">(Optionnel)</span></label>
        <select name="product_id" id="cProductId" class="input">
          <option value="">-- Laisser vide (Catégorie parente / globale) --</option>
          <?php foreach ($available_products as $prod): ?>
            <option value="<?= $prod['id'] ?>"><?= htmlspecialchars($prod['name']) ?> (ID: <?= $prod['id'] ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <label class="block text-xs font-semibold text-gray-400 mb-1.5">URL de l'image d'illustration</label>
        <input name="image_url" id="cImageUrl" class="input" placeholder="https://...">
      </div>

      <div class="flex items-center gap-2">
        <input type="checkbox" name="is_active" id="cActive" value="1" checked class="w-4 h-4 accent-sky-500">
        <label for="cActive" class="text-xs text-gray-300 font-medium">Catégorie active (visible en boutique)</label>
      </div>

      <div class="flex gap-3 pt-2">
        <button type="submit" class="btn btn-primary flex-1">Sauvegarder</button>
        <button type="button" onclick="document.getElementById('modalCategory').classList.add('hidden')" class="btn btn-ghost flex-1">Annuler</button>
      </div>
    </form>
  </div>
</div>

<script>
function openAddModal() {
    document.getElementById('modalCategoryTitle').textContent = 'Ajouter une Catégorie';
    document.getElementById('cAction').value = 'add';
    document.getElementById('cId').value = '';
    document.getElementById('cSlug').value = '';
    document.getElementById('cNameKey').value = '';
    document.getElementById('cIcon').value = 'fas fa-server';
    document.getElementById('cSort').value = '0';
    document.getElementById('cProductId').value = '';
    document.getElementById('cImageUrl').value = '';
    document.getElementById('cActive').checked = true;
    document.getElementById('modalCategory').classList.remove('hidden');
}

function openEditModal(c) {
    document.getElementById('modalCategoryTitle').textContent = 'Modifier la Catégorie';
    document.getElementById('cAction').value = 'edit';
    document.getElementById('cId').value = c.id;
    document.getElementById('cSlug').value = c.category_slug;
    document.getElementById('cNameKey').value = c.name_key;
    document.getElementById('cIcon').value = c.icon;
    document.getElementById('cSort').value = c.sort_order;
    document.getElementById('cProductId').value = c.product_id || '';
    document.getElementById('cImageUrl').value = c.image_url || '';
    document.getElementById('cActive').checked = c.is_active == 1;
    document.getElementById('modalCategory').classList.remove('hidden');
}
</script>
</body></html>

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

    // Ajouter / Modifier
    if ($action === 'add' || $action === 'edit') {
        $id          = (int)($_POST['id'] ?? 0);
        $titre       = trim($_POST['titre'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $categorie   = trim($_POST['categorie'] ?? '');
        $priorite    = trim($_POST['priorite'] ?? 'medium');
        $statut      = trim($_POST['statut'] ?? 'todo');
        
        if ($titre) {
            try {
                if ($action === 'add') {
                    $pdo->prepare("
                        INSERT INTO ideas (titre, description, categorie, priorite, statut, created_by)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ")->execute([$titre, $description ?: null, $categorie ?: null, $priorite, $statut, $_SESSION['user_id']]);
                    $flash = '<div class="bg-green-500/15 text-green-400 border border-green-500/25 p-3 rounded-xl text-sm mb-4">✅ Idée ajoutée.</div>';
                } else {
                    $pdo->prepare("
                        UPDATE ideas SET titre=?, description=?, categorie=?, priorite=?, statut=? WHERE id=?
                    ")->execute([$titre, $description ?: null, $categorie ?: null, $priorite, $statut, $id]);
                    $flash = '<div class="bg-green-500/15 text-green-400 border border-green-500/25 p-3 rounded-xl text-sm mb-4">✅ Idée modifiée.</div>';
                }
            } catch(Exception $e){
                $flash = '<div class="bg-red-500/15 text-red-400 border border-red-500/25 p-3 rounded-xl text-sm mb-4">❌ '.$e->getMessage().'</div>';
            }
        }
    }

    // Toggle statut (case à cocher rapide)
    if ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $newStatut = $_POST['new_statut'] ?? 'done';
        $pdo->prepare("UPDATE ideas SET statut=? WHERE id=?")->execute([$newStatut, $id]);
        exit(json_encode(['ok' => true])); // Réponse AJAX
    }

    // Supprimer
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("DELETE FROM ideas WHERE id=?")->execute([$id]);
        $flash = '<div class="bg-green-500/15 text-green-400 border border-green-500/25 p-3 rounded-xl text-sm mb-4">✅ Idée supprimée.</div>';
    }
}

// Filtrage
$filter = $_GET['filter'] ?? 'all';
$where = '';
$params = [];
if ($filter === 'todo') { $where = "WHERE statut = 'todo'"; }
elseif ($filter === 'in_progress') { $where = "WHERE statut = 'in_progress'"; }
elseif ($filter === 'done') { $where = "WHERE statut = 'done'"; }

// Charger les idées
$ideas = $pdo->prepare("SELECT * FROM ideas $where ORDER BY 
    CASE priorite WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 ELSE 4 END,
    created_at DESC");
$ideas->execute();
$ideas = $ideas->fetchAll();

// Compteurs
$countTodo = $pdo->query("SELECT COUNT(*) FROM ideas WHERE statut='todo'")->fetchColumn();
$countInProgress = $pdo->query("SELECT COUNT(*) FROM ideas WHERE statut='in_progress'")->fetchColumn();
$countDone = $pdo->query("SELECT COUNT(*) FROM ideas WHERE statut='done'")->fetchColumn();

$priorites = [
    'low'    => ['label' => 'Basse',   'color' => 'gray',  'icon' => 'fa-arrow-down'],
    'medium' => ['label' => 'Moyenne', 'color' => 'blue',  'icon' => 'fa-minus'],
    'high'   => ['label' => 'Haute',   'color' => 'orange','icon' => 'fa-arrow-up'],
    'urgent' => ['label' => 'Urgent',  'color' => 'red',   'icon' => 'fa-bolt'],
];

$statuts = [
    'todo'        => ['label' => 'À faire',    'color' => 'gray',  'icon' => 'fa-circle'],
    'in_progress' => ['label' => 'En cours',   'color' => 'blue',  'icon' => 'fa-spinner'],
    'done'        => ['label' => 'Terminé',    'color' => 'green', 'icon' => 'fa-check-circle'],
    'cancelled'   => ['label' => 'Annulé',     'color' => 'red',   'icon' => 'fa-times-circle'],
];

$active_nav = 'ideas';
include $_SERVER['DOCUMENT_ROOT'] . '/inc/admin_layout.php';
?>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

<div class="main-content">
  <div class="topbar">
    <div>
      <div class="text-sm font-bold text-white flex items-center gap-2">
        <i class="fas fa-lightbulb text-yellow-400 text-xs"></i> Mes idées
      </div>
      <div class="text-xs text-gray-500">
        <?= $countTodo ?> à faire · <?= $countInProgress ?> en cours · <?= $countDone ?> terminées
      </div>
    </div>
    <button onclick="openAddModal()" class="btn btn-primary">
      <i class="fas fa-plus text-xs"></i> Nouvelle idée
    </button>
  </div>

  <div class="content">
    <?= $flash ?>

    <!-- Filtres -->
    <div class="flex gap-2 mb-4">
      <a href="?filter=all" class="px-3 py-1.5 rounded-lg text-xs font-semibold <?= $filter==='all' ? 'bg-indigo-500/20 text-indigo-400 border border-indigo-500/30' : 'bg-white/5 text-gray-400 hover:bg-white/10' ?>">
        Toutes <span class="opacity-60">(<?= count($ideas) ?>)</span>
      </a>
      <a href="?filter=todo" class="px-3 py-1.5 rounded-lg text-xs font-semibold <?= $filter==='todo' ? 'bg-gray-500/20 text-gray-300 border border-gray-500/30' : 'bg-white/5 text-gray-400 hover:bg-white/10' ?>">
        À faire <span class="opacity-60">(<?= $countTodo ?>)</span>
      </a>
      <a href="?filter=in_progress" class="px-3 py-1.5 rounded-lg text-xs font-semibold <?= $filter==='in_progress' ? 'bg-blue-500/20 text-blue-400 border border-blue-500/30' : 'bg-white/5 text-gray-400 hover:bg-white/10' ?>">
        En cours <span class="opacity-60">(<?= $countInProgress ?>)</span>
      </a>
      <a href="?filter=done" class="px-3 py-1.5 rounded-lg text-xs font-semibold <?= $filter==='done' ? 'bg-green-500/20 text-green-400 border border-green-500/30' : 'bg-white/5 text-gray-400 hover:bg-white/10' ?>">
        Terminées <span class="opacity-60">(<?= $countDone ?>)</span>
      </a>
    </div>

    <!-- Liste des idées -->
    <div class="space-y-2">
      <?php if (empty($ideas)): ?>
        <div class="card px-5 py-12 text-center text-gray-500 text-sm">
          Aucune idée pour le moment. Clique sur "Nouvelle idée" pour commencer ! 💡
        </div>
      <?php else: ?>
        <?php foreach ($ideas as $idea): 
          $p = $priorites[$idea['priorite']] ?? $priorites['medium'];
          $s = $statuts[$idea['statut']] ?? $statuts['todo'];
          $isDone = $idea['statut'] === 'done';
        ?>
        <div class="card p-4 flex items-start gap-3 group hover:border-white/10 transition <?= $isDone ? 'opacity-60' : '' ?>">
          
          <!-- Case à cocher -->
          <button onclick="toggleIdea(<?= $idea['id'] ?>, '<?= $isDone ? 'todo' : 'done' ?>')" 
                  class="mt-0.5 w-5 h-5 rounded-md border-2 flex items-center justify-center shrink-0 transition
                  <?= $isDone ? 'bg-green-500 border-green-500 text-white' : 'border-gray-600 hover:border-indigo-400' ?>">
            <?php if ($isDone): ?><i class="fas fa-check text-[10px]"></i><?php endif; ?>
          </button>

          <!-- Contenu -->
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 flex-wrap">
              <div class="font-semibold text-white text-sm <?= $isDone ? 'line-through text-gray-500' : '' ?>">
                <?= htmlspecialchars($idea['titre']) ?>
              </div>
              <span class="badge badge-<?= $p['color'] ?> text-[10px]">
                <i class="fas <?= $p['icon'] ?> text-[8px] mr-1"></i><?= $p['label'] ?>
              </span>
              <?php if ($idea['categorie']): ?>
                <span class="text-[10px] bg-white/5 text-gray-400 px-2 py-0.5 rounded-full">
                  #<?= htmlspecialchars($idea['categorie']) ?>
                </span>
              <?php endif; ?>
            </div>
            <?php if ($idea['description']): ?>
              <div class="text-xs text-gray-400 mt-1 whitespace-pre-line"><?= nl2br(htmlspecialchars($idea['description'])) ?></div>
            <?php endif; ?>
            <div class="text-[10px] text-gray-600 mt-1.5">
              <i class="far fa-clock"></i> <?= date('d/m/Y à H:i', strtotime($idea['created_at'])) ?>
            </div>
          </div>

          <!-- Actions -->
          <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition">
            <button onclick='openEditModal(<?= json_encode($idea) ?>)' class="btn btn-ghost text-xs" title="Modifier">
              <i class="fas fa-edit"></i>
            </button>
            <form method="POST" class="inline" onsubmit="return confirm('Supprimer cette idée ?')">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= $idea['id'] ?>">
              <button class="btn btn-danger text-xs"><i class="fas fa-trash"></i></button>
            </form>
          </div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Modal Ajouter/Modifier -->
<div id="modalIdea" class="hidden fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4" style="backdrop-filter:blur(6px)">
  <div class="bg-[#161a22] border border-white/10 rounded-2xl w-full max-w-xl p-6">
    <div class="flex items-center justify-between mb-5">
      <h3 id="modalTitle" class="text-base font-bold text-white">💡 Nouvelle idée</h3>
      <button onclick="document.getElementById('modalIdea').classList.add('hidden')" class="text-gray-500 hover:text-white"><i class="fas fa-times"></i></button>
    </div>
    <form method="POST" class="space-y-4">
      <input type="hidden" name="action" id="iAction" value="add">
      <input type="hidden" name="id" id="iId" value="">

      <div>
        <label class="block text-xs font-semibold text-gray-400 mb-1.5">Titre <span class="text-red-400">*</span></label>
        <input name="titre" id="iTitre" class="input" placeholder="Ex: Ajouter un thème sombre" required maxlength="255">
      </div>

      <div>
        <label class="block text-xs font-semibold text-gray-400 mb-1.5">Description</label>
        <textarea name="description" id="iDescription" class="input" rows="4" placeholder="Détails, notes, liens, etc..."></textarea>
      </div>

      <div class="grid grid-cols-3 gap-3">
        <div>
          <label class="block text-xs font-semibold text-gray-400 mb-1.5">Catégorie</label>
          <input name="categorie" id="iCategorie" class="input" placeholder="feature, bug...">
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-400 mb-1.5">Priorité</label>
          <select name="priorite" id="iPriorite" class="input">
            <option value="low">Basse</option>
            <option value="medium" selected>Moyenne</option>
            <option value="high">Haute</option>
            <option value="urgent">Urgent</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-400 mb-1.5">Statut</label>
          <select name="statut" id="iStatut" class="input">
            <option value="todo">À faire</option>
            <option value="in_progress">En cours</option>
            <option value="done">Terminé</option>
            <option value="cancelled">Annulé</option>
          </select>
        </div>
      </div>

      <div class="flex gap-3 pt-2">
        <button type="submit" class="btn btn-primary flex-1">
          <i class="fas fa-save mr-2"></i>Enregistrer
        </button>
        <button type="button" onclick="document.getElementById('modalIdea').classList.add('hidden')" class="btn btn-ghost flex-1">Annuler</button>
      </div>
    </form>
  </div>
</div>

<script>
function openAddModal() {
    document.getElementById('modalTitle').textContent = '💡 Nouvelle idée';
    document.getElementById('iAction').value = 'add';
    document.getElementById('iId').value = '';
    document.getElementById('iTitre').value = '';
    document.getElementById('iDescription').value = '';
    document.getElementById('iCategorie').value = '';
    document.getElementById('iPriorite').value = 'medium';
    document.getElementById('iStatut').value = 'todo';
    document.getElementById('modalIdea').classList.remove('hidden');
}

function openEditModal(idea) {
    document.getElementById('modalTitle').textContent = '✏️ Modifier l\'idée';
    document.getElementById('iAction').value = 'edit';
    document.getElementById('iId').value = idea.id;
    document.getElementById('iTitre').value = idea.titre;
    document.getElementById('iDescription').value = idea.description || '';
    document.getElementById('iCategorie').value = idea.categorie || '';
    document.getElementById('iPriorite').value = idea.priorite;
    document.getElementById('iStatut').value = idea.statut;
    document.getElementById('modalIdea').classList.remove('hidden');
}

function toggleIdea(id, newStatut) {
    const formData = new FormData();
    formData.append('action', 'toggle');
    formData.append('id', id);
    formData.append('new_statut', newStatut);
    
    fetch(window.location.href, { method: 'POST', body: formData })
        .then(() => window.location.reload());
}
</script>
</body></html>
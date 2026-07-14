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

        $id              = (int)($_POST['id'] ?? 0);
        $titre           = trim($_POST['titre'] ?? '');
        $description     = trim($_POST['description'] ?? '');
        $client_nom      = trim($_POST['client_nom'] ?? '');
        $client_email    = trim($_POST['client_email'] ?? '');
        $client_telephone = trim($_POST['client_telephone'] ?? '');
        $statut          = trim($_POST['statut'] ?? 'draft');
        $priorite        = trim($_POST['priorite'] ?? 'medium');
        $budget          = !empty($_POST['budget']) ? (float)$_POST['budget'] : null;
        $date_limite     = !empty($_POST['date_limite']) ? $_POST['date_limite'] : null;
        $progression     = (int)($_POST['progression'] ?? 0);
        $notes_admin     = trim($_POST['notes_admin'] ?? '');
        
        // Validation
        if ($titre && $description) {
            try {
                $pdo->beginTransaction();
                
                if ($action == 'add') {
                    $stmt = $pdo->prepare("
                        INSERT INTO cahier_charges 
                        (titre, description, client_nom, client_email, client_telephone, 
                         statut, priorite, budget, date_limite, progression, notes_admin, created_by)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    
                    $stmt->execute([
                        $titre, $description, $client_nom ?: null, $client_email ?: null, 
                        $client_telephone ?: null, $statut, $priorite, $budget, 
                        $date_limite, $progression, $notes_admin ?: null, $_SESSION['user_id']
                    ]);
                    
                    $newId = $pdo->lastInsertId();
                    $pdo->commit();
                    
                    $flash = '<div class="bg-green-500/15 text-green-400 border border-green-500/25 p-3 rounded-xl text-sm mb-4">
                    ✅ Cahier des charges créé avec succès.
                    </div>';
                    
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE cahier_charges
                        SET titre=?, description=?, client_nom=?, client_email=?, client_telephone=?,
                            statut=?, priorite=?, budget=?, date_limite=?, progression=?, notes_admin=?
                        WHERE id=?
                    ");
                    
                    $stmt->execute([
                        $titre, $description, $client_nom ?: null, $client_email ?: null, 
                        $client_telephone ?: null, $statut, $priorite, $budget, 
                        $date_limite, $progression, $notes_admin ?: null, $id
                    ]);
                    
                    $pdo->commit();
                    
                    $flash = '<div class="bg-green-500/15 text-green-400 border border-green-500/25 p-3 rounded-xl text-sm mb-4">
                    ✅ Cahier des charges modifié avec succès.
                    </div>';
                }
                
            } catch(Exception $e){
                $pdo->rollBack();
                $flash = '<div class="bg-red-500/15 text-red-400 border border-red-500/25 p-3 rounded-xl text-sm mb-4">
                ❌ '.$e->getMessage().'
                </div>';
            }
        } else {
            $flash = '<div class="bg-red-500/15 text-red-400 border border-red-500/25 p-3 rounded-xl text-sm mb-4">
            ❌ Le titre et la description sont obligatoires.
            </div>';
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        
        try {
            $pdo->prepare("DELETE FROM cahier_charges WHERE id=?")->execute([$id]);
            
            $flash = '<div class="bg-green-500/15 text-green-400 border border-green-500/25 p-3 rounded-xl text-sm mb-4">
            ✅ Cahier des charges supprimé.
            </div>';
        } catch(Exception $e){
            $flash = '<div class="bg-red-500/15 text-red-400 border-red-500/25 p-3 rounded-xl text-sm mb-4">
            ❌ Erreur lors de la suppression: '.$e->getMessage().'
            </div>';
        }
    }
}

// Charger les cahiers des charges
$cahiers = $pdo->query('
    SELECT 
        c.*,
        u.pseudo as creator_pseudo,
        u.firstname as creator_firstname,
        COUNT(DISTINCT f.id) as files_count,
        COUNT(DISTINCT com.id) as comments_count
    FROM cahier_charges c
    LEFT JOIN users u ON c.created_by = u.id
    LEFT JOIN cahier_charges_files f ON c.id = f.cahier_id
    LEFT JOIN cahier_charges_comments com ON c.id = com.cahier_id
    GROUP BY c.id
    ORDER BY c.created_at DESC
')->fetchAll();

// Statuts disponibles
$statuts = [
    'draft' => ['label' => 'Brouillon', 'icon' => 'fa-file-alt', 'color' => 'gray'],
    'in_progress' => ['label' => 'En cours', 'icon' => 'fa-spinner', 'color' => 'blue'],
    'review' => ['label' => 'En révision', 'icon' => 'fa-search', 'color' => 'amber'],
    'completed' => ['label' => 'Terminé', 'icon' => 'fa-check-circle', 'color' => 'green'],
    'archived' => ['label' => 'Archivé', 'icon' => 'fa-archive', 'color' => 'slate'],
];

// Priorités disponibles
$priorites = [
    'low' => ['label' => 'Basse', 'icon' => 'fa-arrow-down', 'color' => 'green'],
    'medium' => ['label' => 'Moyenne', 'icon' => 'fa-minus', 'color' => 'yellow'],
    'high' => ['label' => 'Haute', 'icon' => 'fa-arrow-up', 'color' => 'orange'],
    'urgent' => ['label' => 'Urgent', 'icon' => 'fa-exclamation-circle', 'color' => 'red'],
];

$active_nav = 'cahier_charges';
include $_SERVER['DOCUMENT_ROOT'] . '/inc/admin_layout.php';
?>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<div class="main-content">
  <div class="topbar">
    <div>
      <div class="flex items-center gap-3">
    <button id="adminSidebarToggle" class="md:hidden text-gray-400 hover:text-white text-lg w-8" aria-label="Ouvrir le menu admin">
    <i class="fas fa-bars"></i>
</button>
      <div>
      <div class="text-sm font-bold text-white flex items-center gap-2"><i class="fas fa-file-contract text-indigo-400 text-xs"></i> Cahiers des Charges</div>
      <div class="text-xs text-gray-500"><?= count($cahiers) ?> cahier(s) des charges</div>
    </div>
    <button onclick="openAddModal()" class="btn btn-primary">
      <i class="fas fa-plus text-xs"></i> Nouveau Cahier
    </button>
  </div>

  <div class="content">
    <?= $flash ?>

    <div class="card overflow-hidden">
      <div class="px-5 py-4 border-b border-white/[0.05]">
        <span class="text-sm font-bold text-white flex items-center gap-2"><i class="fas fa-list text-indigo-400 text-xs"></i> Liste des cahiers des charges</span>
      </div>
      <?php if (empty($cahiers)): ?>
        <div class="px-5 py-12 text-center text-gray-500 text-sm">Aucun cahier des charges créé.</div>
      <?php else: ?>
      <div class="overflow-x-auto">
      <table class="tbl">
        <thead>
          <tr><th>Date</th><th>Titre</th><th>Client</th><th>Statut</th><th>Priorité</th><th>Progression</th><th>Échéance</th><th>Actions</th></tr>
        </thead>
        <tbody>
          <?php foreach ($cahiers as $c): 
            $statutInfo = $statuts[$c['statut']] ?? $statuts['draft'];
            $prioriteInfo = $priorites[$c['priorite']] ?? $priorites['medium'];
            $creator = $c['creator_pseudo'] ?? $c['creator_firstname'] ?? 'Inconnu';
          ?>
          <tr>
            <td class="text-xs text-gray-400 whitespace-nowrap">
              <div><?= date('d/m/Y', strtotime($c['created_at'])) ?></div>
              <div class="text-[10px] text-gray-600"><?= date('H:i', strtotime($c['created_at'])) ?></div>
            </td>
            <td>
              <div class="font-semibold text-white text-sm"><?= htmlspecialchars($c['titre']) ?></div>
              <div class="text-xs text-gray-500 mt-0.5 line-clamp-1"><?= htmlspecialchars(substr($c['description'], 0, 80)) ?>...</div>
              <?php if ($c['files_count'] > 0 || $c['comments_count'] > 0): ?>
                <div class="flex items-center gap-2 mt-1">
                  <?php if ($c['files_count'] > 0): ?>
                    <span class="text-[10px] text-gray-500"><i class="fas fa-paperclip"></i> <?= $c['files_count'] ?></span>
                  <?php endif; ?>
                  <?php if ($c['comments_count'] > 0): ?>
                    <span class="text-[10px] text-gray-500"><i class="fas fa-comment"></i> <?= $c['comments_count'] ?></span>
                  <?php endif; ?>
                </div>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($c['client_nom']): ?>
                <div class="text-sm text-white"><?= htmlspecialchars($c['client_nom']) ?></div>
                <?php if ($c['client_email']): ?>
                  <div class="text-[10px] text-gray-500"><?= htmlspecialchars($c['client_email']) ?></div>
                <?php endif; ?>
              <?php else: ?>
                <span class="text-xs text-gray-600">Non spécifié</span>
              <?php endif; ?>
            </td>
            <td>
              <span class="badge badge-<?= $statutInfo['color'] ?>">
                <i class="fas <?= $statutInfo['icon'] ?> text-[10px] mr-1"></i>
                <?= $statutInfo['label'] ?>
              </span>
            </td>
            <td>
              <span class="badge badge-<?= $prioriteInfo['color'] ?>">
                <i class="fas <?= $prioriteInfo['icon'] ?> text-[10px] mr-1"></i>
                <?= $prioriteInfo['label'] ?>
              </span>
            </td>
            <td>
              <div class="flex items-center gap-2">
                <div class="flex-1 bg-white/5 rounded-full h-1.5 w-16">
                  <div class="bg-indigo-500 h-1.5 rounded-full" style="width: <?= (int)$c['progression'] ?>%"></div>
                </div>
                <span class="text-xs text-gray-400"><?= (int)$c['progression'] ?>%</span>
              </div>
            </td>
            <td class="text-xs text-gray-400 whitespace-nowrap">
              <?php if ($c['date_limite']): ?>
                <?php 
                  $daysLeft = (strtotime($c['date_limite']) - time()) / 86400;
                  $isOverdue = $daysLeft < 0 && !in_array($c['statut'], ['completed', 'archived']);
                ?>
                <div class="<?= $isOverdue ? 'text-red-400' : '' ?>">
                  <?= date('d/m/Y', strtotime($c['date_limite'])) ?>
                </div>
                <?php if ($isOverdue): ?>
                  <div class="text-[10px] text-red-500">En retard</div>
                <?php elseif ($daysLeft <= 7): ?>
                  <div class="text-[10px] text-amber-500"><?= ceil($daysLeft) ?>j restants</div>
                <?php endif; ?>
              <?php else: ?>
                <span class="text-gray-600">Non définie</span>
              <?php endif; ?>
            </td>
            <td>
              <div class="flex items-center gap-1.5">
                <button onclick='openEditModal(<?= htmlspecialchars(json_encode($c), ENT_QUOTES) ?>)' class="btn btn-ghost text-xs" title="Voir/Modifier">
                  <i class="fas fa-edit"></i>
                </button>
                
                <form method="POST" class="inline" onsubmit="return confirm('Supprimer ce cahier des charges ?')">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= $c['id'] ?>">
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

<!-- Modal Ajouter/Modifier -->
<div id="modalCahier" class="hidden fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4" style="backdrop-filter:blur(6px)">
  <div class="bg-[#161a22] border border-white/10 rounded-2xl w-full max-w-3xl p-6 max-h-[90vh] overflow-y-auto">
    <div class="flex items-center justify-between mb-5">
      <h3 id="modalTitle" class="text-base font-bold text-white">Nouveau Cahier des Charges</h3>
      <button onclick="document.getElementById('modalCahier').classList.add('hidden')" class="text-gray-500 hover:text-white"><i class="fas fa-times"></i></button>
    </div>
    <form method="POST" class="space-y-4">
      <input type="hidden" name="action" id="cAction" value="add">
      <input type="hidden" name="id" id="cId" value="">

      <!-- Titre -->
      <div>
        <label class="block text-xs font-semibold text-gray-400 mb-1.5">Titre <span class="text-red-400">*</span></label>
        <input name="titre" id="cTitre" class="input" placeholder="Ex: Refonte site web client XYZ" required maxlength="255">
      </div>

      <!-- Description -->
      <div>
        <label class="block text-xs font-semibold text-gray-400 mb-1.5">Description <span class="text-red-400">*</span></label>
        <textarea name="description" id="cDescription" class="input" rows="6" placeholder="Description détaillée du projet, objectifs, fonctionnalités attendues..." required></textarea>
      </div>

      <!-- Informations client -->
      <div class="grid grid-cols-3 gap-3">
        <div>
          <label class="block text-xs font-semibold text-gray-400 mb-1.5">Nom du client</label>
          <input name="client_nom" id="cClientNom" class="input" placeholder="Ex: Entreprise ABC">
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-400 mb-1.5">Email client</label>
          <input name="client_email" id="cClientEmail" class="input" type="email" placeholder="client@example.com">
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-400 mb-1.5">Téléphone</label>
          <input name="client_telephone" id="cClientTelephone" class="input" placeholder="+33 6 12 34 56 78">
        </div>
      </div>

      <!-- Statut et Priorité -->
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-semibold text-gray-400 mb-1.5">Statut</label>
          <select name="statut" id="cStatut" class="input">
            <?php foreach ($statuts as $key => $s): ?>
              <option value="<?= $key ?>"><?= $s['label'] ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-400 mb-1.5">Priorité</label>
          <select name="priorite" id="cPriorite" class="input">
            <?php foreach ($priorites as $key => $p): ?>
              <option value="<?= $key ?>" <?= $key === 'medium' ? 'selected' : '' ?>><?= $p['label'] ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <!-- Budget et Date limite -->
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-semibold text-gray-400 mb-1.5">Budget (€)</label>
          <input name="budget" id="cBudget" class="input" type="number" step="0.01" placeholder="5000.00">
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-400 mb-1.5">Date limite</label>
          <input name="date_limite" id="cDateLimite" class="input" type="date">
        </div>
      </div>

      <!-- Progression -->
      <div>
        <label class="block text-xs font-semibold text-gray-400 mb-1.5">Progression : <span id="progressionValue">0</span>%</label>
        <input type="range" name="progression" id="cProgression" min="0" max="100" value="0" class="w-full" oninput="document.getElementById('progressionValue').textContent = this.value">
      </div>

      <!-- Notes admin -->
      <div>
        <label class="block text-xs font-semibold text-gray-400 mb-1.5">Notes internes</label>
        <textarea name="notes_admin" id="cNotesAdmin" class="input" rows="3" placeholder="Notes privées (non visibles par le client)..."></textarea>
      </div>

      <div class="flex gap-3 pt-2">
        <button type="submit" class="btn btn-primary flex-1">
          <i class="fas fa-save mr-2"></i>Enregistrer
        </button>
        <button type="button" onclick="document.getElementById('modalCahier').classList.add('hidden')" class="btn btn-ghost flex-1">Annuler</button>
      </div>
    </form>
  </div>
</div>

<script>
function openAddModal() {
    document.getElementById('modalTitle').textContent = 'Nouveau Cahier des Charges';
    document.getElementById('cAction').value = 'add';
    document.getElementById('cId').value = '';
    document.getElementById('cTitre').value = '';
    document.getElementById('cDescription').value = '';
    document.getElementById('cClientNom').value = '';
    document.getElementById('cClientEmail').value = '';
    document.getElementById('cClientTelephone').value = '';
    document.getElementById('cStatut').value = 'draft';
    document.getElementById('cPriorite').value = 'medium';
    document.getElementById('cBudget').value = '';
    document.getElementById('cDateLimite').value = '';
    document.getElementById('cProgression').value = 0;
    document.getElementById('progressionValue').textContent = '0';
    document.getElementById('cNotesAdmin').value = '';
    document.getElementById('modalCahier').classList.remove('hidden');
}

function openEditModal(c) {
    document.getElementById('modalTitle').textContent = 'Modifier le Cahier des Charges';
    document.getElementById('cAction').value = 'edit';
    document.getElementById('cId').value = c.id;
    document.getElementById('cTitre').value = c.titre;
    document.getElementById('cDescription').value = c.description;
    document.getElementById('cClientNom').value = c.client_nom || '';
    document.getElementById('cClientEmail').value = c.client_email || '';
    document.getElementById('cClientTelephone').value = c.client_telephone || '';
    document.getElementById('cStatut').value = c.statut;
    document.getElementById('cPriorite').value = c.priorite;
    document.getElementById('cBudget').value = c.budget || '';
    document.getElementById('cDateLimite').value = c.date_limite || '';
    document.getElementById('cProgression').value = c.progression || 0;
    document.getElementById('progressionValue').textContent = c.progression || 0;
    document.getElementById('cNotesAdmin').value = c.notes_admin || '';
    document.getElementById('modalCahier').classList.remove('hidden');
}
</script>
</body></html>
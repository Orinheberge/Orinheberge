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
        $title         = trim($_POST['title'] ?? '');
        $message       = trim($_POST['message'] ?? '');
        $link          = trim($_POST['link'] ?? null);
        $type          = trim($_POST['type'] ?? 'info');
        $target_type   = trim($_POST['target_type'] ?? 'all'); // all, specific
        $specific_ids  = trim($_POST['specific_ids'] ?? '');
        
        // Validation
        if ($title && $message) {
            try {
                $pdo->beginTransaction();
                
                // Déterminer les destinataires
                $userIds = [];
                
                if ($target_type === 'all') {
                    // Tous les utilisateurs
                    $usersStmt = $pdo->query('SELECT id FROM users');
                    $userIds = $usersStmt->fetchAll(PDO::FETCH_COLUMN);
                } elseif ($target_type === 'specific' && !empty($specific_ids)) {
                    // Utilisateurs spécifiques
                    $idsArray = array_filter(array_map('intval', explode(',', $specific_ids)));
                    
                    if (!empty($idsArray)) {
                        $placeholders = implode(',', array_fill(0, count($idsArray), '?'));
                        $stmt = $pdo->prepare("SELECT id FROM users WHERE id IN ($placeholders)");
                        $stmt->execute($idsArray);
                        $userIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    }
                }
                
                if (empty($userIds)) {
                    throw new Exception('Aucun utilisateur cible trouvé.');
                }
                
                // Préparer les métadonnées
                $meta = json_encode([
                    'broadcast' => ($target_type === 'all'),
                    'created_by' => $_SESSION['user_id'],
                    'target_type' => $target_type
                ]);
                
                if ($action == 'add') {
                    // Insertion en masse
                    $insertStmt = $pdo->prepare("
                        INSERT INTO notifications 
                        (user_id, title, message, link, type, meta, is_read)
                        VALUES (?, ?, ?, ?, ?, ?, 0)
                    ");
                    
                    $count = 0;
                    foreach ($userIds as $userId) {
                        $insertStmt->execute([$userId, $title, $message, $link ?: null, $type, $meta]);
                        $count++;
                    }
                    
                    $pdo->commit();
                    
                    $flash = '<div class="bg-green-500/15 text-green-400 border border-green-500/25 p-3 rounded-xl text-sm mb-4">
                    ✅ Annonce envoyée avec succès à <strong>' . $count . '</strong> utilisateur(s).
                    </div>';
                    
                } else {
                    // Mode édition : mettre à jour une notification spécifique
                    $pdo->prepare("
                        UPDATE notifications
                        SET title=?, message=?, link=?, type=?, meta=?
                        WHERE id=?
                    ")->execute([$title, $message, $link ?: null, $type, $meta, $id]);
                    
                    $pdo->commit();
                    
                    $flash = '<div class="bg-green-500/15 text-green-400 border border-green-500/25 p-3 rounded-xl text-sm mb-4">
                    ✅ Annonce modifiée avec succès.
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
            ❌ Le titre et le message sont obligatoires.
            </div>';
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        // Supprimer toutes les notifications liées (même titre/message créé ensemble)
        $notif = $pdo->prepare('SELECT title, message, created_at FROM notifications WHERE id=? LIMIT 1');
        $notif->execute([$id]);
        $data = $notif->fetch();
        
        if ($data) {
            // Supprimer les notifications similaires créées au même moment (broadcast)
            $pdo->prepare("
                DELETE FROM notifications 
                WHERE title=? AND message=? AND created_at=?
            ")->execute([$data['title'], $data['message'], $data['created_at']]);
            
            $flash = '<div class="bg-green-500/15 text-green-400 border border-green-500/25 p-3 rounded-xl text-sm mb-4">
            ✅ Annonce supprimée.
            </div>';
        }
    }
}

// Charger les annonces (regroupées par titre pour éviter les doublons visuels)
$announcements = $pdo->query('
    SELECT 
        n.id,
        n.title,
        n.message,
        n.link,
        n.type,
        n.meta,
        n.is_read,
        n.created_at,
        COUNT(*) as recipient_count,
        u.pseudo as creator_pseudo,
        u.firstname as creator_firstname
    FROM notifications n
    LEFT JOIN users u ON JSON_EXTRACT(n.meta, "$.created_by") = u.id
    GROUP BY n.title, n.message, n.created_at
    ORDER BY n.created_at DESC
')->fetchAll();

// Types de notifications disponibles
$notification_types = [
    'info' => ['label' => 'Information', 'icon' => 'fa-info-circle', 'color' => 'sky'],
    'warning' => ['label' => 'Avertissement', 'icon' => 'fa-exclamation-triangle', 'color' => 'amber'],
    'success' => ['label' => 'Succès', 'icon' => 'fa-check-circle', 'color' => 'green'],
    'error' => ['label' => 'Erreur', 'icon' => 'fa-times-circle', 'color' => 'red'],
    'announcement' => ['label' => 'Annonce', 'icon' => 'fa-bullhorn', 'color' => 'rose'],
];

$active_nav = 'announcements';
include $_SERVER['DOCUMENT_ROOT'] . '/inc/admin_layout.php';
?>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<div class="main-content">
  <div class="topbar">
    <div>
      <div class="text-sm font-bold text-white flex items-center gap-2"><i class="fas fa-bullhorn text-rose-400 text-xs"></i> Gestion des Annonces</div>
      <div class="text-xs text-gray-500"><?= count($announcements) ?> annonce(s) envoyée(s)</div>
    </div>
    <button onclick="openAddModal()" class="btn btn-primary">
      <i class="fas fa-plus text-xs"></i> Créer une Annonce
    </button>
  </div>

  <div class="content">
    <?= $flash ?>

    <div class="card overflow-hidden">
      <div class="px-5 py-4 border-b border-white/[0.05]">
        <span class="text-sm font-bold text-white flex items-center gap-2"><i class="fas fa-list text-rose-400 text-xs"></i> Historique des annonces</span>
      </div>
      <?php if (empty($announcements)): ?>
        <div class="px-5 py-12 text-center text-gray-500 text-sm">Aucune annonce envoyée.</div>
      <?php else: ?>
      <div class="overflow-x-auto">
      <table class="tbl">
        <thead>
          <tr><th>Date</th><th>Titre</th><th>Type</th><th>Destinataires</th><th>Créé par</th><th>Actions</th></tr>
        </thead>
        <tbody>
          <?php foreach ($announcements as $a): 
            $typeInfo = $notification_types[$a['type']] ?? $notification_types['info'];
            $creator = $a['creator_pseudo'] ?? $a['creator_firstname'] ?? 'Inconnu';
            $recipientCount = (int)$a['recipient_count'];
          ?>
          <tr>
            <td class="text-xs text-gray-400 whitespace-nowrap">
              <div><?= date('d/m/Y', strtotime($a['created_at'])) ?></div>
              <div class="text-[10px] text-gray-600"><?= date('H:i', strtotime($a['created_at'])) ?></div>
            </td>
            <td>
              <div class="font-semibold text-white text-sm"><?= htmlspecialchars($a['title']) ?></div>
              <div class="text-xs text-gray-500 mt-0.5 line-clamp-1"><?= htmlspecialchars(substr($a['message'], 0, 80)) ?>...</div>
              <?php if ($a['link']): ?>
                <a href="<?= htmlspecialchars($a['link']) ?>" target="_blank" class="text-xs text-sky-400 hover:text-sky-300 inline-flex items-center gap-1 mt-1">
                  <i class="fas fa-external-link-alt text-[10px]"></i> Lien
                </a>
              <?php endif; ?>
            </td>
            <td>
              <span class="badge badge-<?= $typeInfo['color'] ?>">
                <i class="fas <?= $typeInfo['icon'] ?> text-[10px] mr-1"></i>
                <?= $typeInfo['label'] ?>
              </span>
            </td>
            <td>
              <span class="text-xs bg-white/5 text-gray-300 border border-white/10 px-2 py-0.5 rounded-full font-medium">
                <i class="fas fa-users text-[10px] mr-1"></i><?= $recipientCount ?> utilisateur(s)
              </span>
            </td>
            <td class="text-xs text-gray-400"><?= htmlspecialchars($creator) ?></td>
            <td>
              <div class="flex items-center gap-1.5">
                <button onclick='openEditModal(<?= htmlspecialchars(json_encode($a), ENT_QUOTES) ?>)' class="btn btn-ghost text-xs" title="Voir les détails">
                  <i class="fas fa-eye"></i>
                </button>
                
                <form method="POST" class="inline" onsubmit="return confirm('Supprimer cette annonce et toutes ses copies ?')">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= $a['id'] ?>">
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
<div id="modalAnnouncement" class="hidden fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4" style="backdrop-filter:blur(6px)">
  <div class="bg-[#161a22] border border-white/10 rounded-2xl w-full max-w-2xl p-6 max-h-[90vh] overflow-y-auto">
    <div class="flex items-center justify-between mb-5">
      <h3 id="modalTitle" class="text-base font-bold text-white">Créer une Annonce</h3>
      <button onclick="document.getElementById('modalAnnouncement').classList.add('hidden')" class="text-gray-500 hover:text-white"><i class="fas fa-times"></i></button>
    </div>
    <form method="POST" class="space-y-4">
      <input type="hidden" name="action" id="aAction" value="add">
      <input type="hidden" name="id" id="aId" value="">

      <!-- Titre -->
      <div>
        <label class="block text-xs font-semibold text-gray-400 mb-1.5">Titre de l'annonce <span class="text-red-400">*</span></label>
        <input name="title" id="aTitle" class="input" placeholder="Ex: Maintenance prévue ce weekend" required maxlength="255">
      </div>

      <!-- Message -->
      <div>
        <label class="block text-xs font-semibold text-gray-400 mb-1.5">Message <span class="text-red-400">*</span></label>
        <textarea name="message" id="aMessage" class="input" rows="5" placeholder="Détails de l'annonce..." required></textarea>
        <div class="text-[10px] text-gray-600 mt-1">Le message peut contenir du texte simple. Évitez le HTML.</div>
      </div>

      <!-- Lien optionnel -->
      <div>
        <label class="block text-xs font-semibold text-gray-400 mb-1.5">Lien (optionnel)</label>
        <input name="link" id="aLink" class="input" placeholder="https://example.com/page" type="url">
      </div>

      <!-- Type d'annonce -->
      <div>
        <label class="block text-xs font-semibold text-gray-400 mb-1.5">Type d'annonce</label>
        <div class="grid grid-cols-5 gap-2">
          <?php foreach ($notification_types as $key => $type): ?>
          <label class="cursor-pointer">
            <input type="radio" name="type" value="<?= $key ?>" class="peer hidden" <?= $key === 'announcement' ? 'checked' : '' ?>>
            <div class="border border-white/10 rounded-lg p-2 text-center transition-all peer-checked:border-<?= $type['color'] ?>-500 peer-checked:bg-<?= $type['color'] ?>-500/10 hover:bg-white/5">
              <i class="fas <?= $type['icon'] ?> text-<?= $type['color'] ?>-400 text-lg mb-1 block"></i>
              <div class="text-[10px] text-gray-400"><?= $type['label'] ?></div>
            </div>
          </label>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Destinataires -->
      <div>
        <label class="block text-xs font-semibold text-gray-400 mb-1.5">Destinataires</label>
        <select name="target_type" id="aTargetType" class="input" onchange="toggleSpecificIds()">
          <option value="all">Tous les utilisateurs</option>
          <option value="specific">Utilisateurs spécifiques</option>
        </select>
      </div>

      <!-- IDs spécifiques (caché par défaut) -->
      <div id="specificIdsGroup" class="hidden">
        <label class="block text-xs font-semibold text-gray-400 mb-1.5">IDs des utilisateurs</label>
        <input name="specific_ids" id="aSpecificIds" class="input font-mono" placeholder="Ex: 1, 5, 12, 45">
        <div class="text-[10px] text-gray-600 mt-1">Séparez les IDs par des virgules. Exemple: 1, 5, 12</div>
      </div>

      <div class="flex gap-3 pt-2">
        <button type="submit" class="btn btn-primary flex-1">
          <i class="fas fa-paper-plane mr-2"></i>Envoyer l'annonce
        </button>
        <button type="button" onclick="document.getElementById('modalAnnouncement').classList.add('hidden')" class="btn btn-ghost flex-1">Annuler</button>
      </div>
    </form>
  </div>
</div>

<script>
function openAddModal() {
    document.getElementById('modalTitle').textContent = 'Créer une Annonce';
    document.getElementById('aAction').value = 'add';
    document.getElementById('aId').value = '';
    document.getElementById('aTitle').value = '';
    document.getElementById('aMessage').value = '';
    document.getElementById('aLink').value = '';
    document.querySelector('input[name="type"][value="announcement"]').checked = true;
    document.getElementById('aTargetType').value = 'all';
    document.getElementById('aSpecificIds').value = '';
    document.getElementById('specificIdsGroup').classList.add('hidden');
    document.getElementById('modalAnnouncement').classList.remove('hidden');
}

function openEditModal(a) {
    document.getElementById('modalTitle').textContent = 'Détails de l\'Annonce';
    document.getElementById('aAction').value = 'edit';
    document.getElementById('aId').value = a.id;
    document.getElementById('aTitle').value = a.title;
    document.getElementById('aMessage').value = a.message;
    document.getElementById('aLink').value = a.link || '';
    
    // Sélectionner le type
    const typeRadio = document.querySelector(`input[name="type"][value="${a.type}"]`);
    if (typeRadio) typeRadio.checked = true;
    
    // Désactiver la modification des destinataires en mode édition
    document.getElementById('aTargetType').disabled = true;
    document.getElementById('specificIdsGroup').classList.add('hidden');
    
    document.getElementById('modalAnnouncement').classList.remove('hidden');
}

function toggleSpecificIds() {
    const select = document.getElementById('aTargetType');
    const group = document.getElementById('specificIdsGroup');
    if (select.value === 'specific') {
        group.classList.remove('hidden');
    } else {
        group.classList.add('hidden');
    }
}

// Réinitialiser le disabled quand on ouvre en mode ajout
document.querySelector('button[onclick="openAddModal()"]').addEventListener('click', function() {
    document.getElementById('aTargetType').disabled = false;
});
</script>
</body></html>
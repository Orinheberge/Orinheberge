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

<div class="main-content p-6">
  <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
    <div class="flex items-center gap-3">
      <button id="adminSidebarToggle" class="md:hidden text-gray-400 hover:text-white text-lg w-8" aria-label="Ouvrir le menu admin">
        <i class="fas fa-bars"></i>
      </button>
      <div>
        <div class="text-sm font-bold text-white flex items-center gap-2">
          <i class="fas fa-bullhorn text-rose-400 text-xs"></i> 
          Gestion des Annonces
        </div>
        <div class="text-xs text-gray-500 mt-1">
          <?= count($announcements) ?> annonce(s) envoyée(s)
        </div>
      </div>
    </div>
    
    <div>
      <button onclick="openAddModal()" class="w-full md:w-auto px-4 py-2 bg-rose-600 hover:bg-rose-500 text-white rounded-xl text-xs font-semibold flex items-center justify-center gap-2 transition-all">
        <i class="fas fa-plus text-[10px]"></i> Créer une Annonce
      </button>
    </div>
  </div>

  <div class="content space-y-4">
    <?= $flash ?>

    <div class="card bg-[#161a22] border border-white/10 rounded-2xl overflow-hidden">
      <div class="px-5 py-4 border-b border-white/[0.05]">
        <span class="text-sm font-bold text-white flex items-center gap-2">
          <i class="fas fa-list text-rose-400 text-xs"></i> 
          Historique des annonces
        </span>
      </div>

      <?php if (empty($announcements)): ?>
        <div class="px-5 py-12 text-center text-gray-500 text-sm">Aucune annonce envoyée.</div>
      <?php else: ?>
        <div class="overflow-x-auto">
          <table class="w-full text-left border-collapse">
            <thead>
              <tr class="border-b border-white/[0.05] text-xs text-gray-400 uppercase tracking-wider">
                <th class="px-5 py-3 font-semibold">Date</th>
                <th class="px-5 py-3 font-semibold">Titre</th>
                <th class="px-5 py-3 font-semibold">Type</th>
                <th class="px-5 py-3 font-semibold">Destinataires</th>
                <th class="px-5 py-3 font-semibold">Créé par</th>
                <th class="px-5 py-3 font-semibold text-right">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-white/[0.02]">
              <?php foreach ($announcements as $a): 
                $typeInfo = $notification_types[$a['type']] ?? $notification_types['info'];
                $creator = $a['creator_pseudo'] ?? $a['creator_firstname'] ?? 'Inconnu';
                $recipientCount = (int)$a['recipient_count'];
              ?>
              <tr class="hover:bg-white/[0.01] transition-colors">
                <td class="px-5 py-4 text-xs text-gray-400 whitespace-nowrap">
                  <div class="font-medium text-white"><?= date('d/m/Y', strtotime($a['created_at'])) ?></div>
                  <div class="text-[10px] text-gray-600 mt-0.5"><?= date('H:i', strtotime($a['created_at'])) ?></div>
                </td>
                <td class="px-5 py-4">
                  <div class="font-semibold text-white text-sm"><?= htmlspecialchars($a['title']) ?></div>
                  <div class="text-xs text-gray-500 mt-0.5 line-clamp-1 max-w-xs md:max-w-md"><?= htmlspecialchars(substr($a['message'], 0, 80)) ?>...</div>
                  <?php if ($a['link']): ?>
                    <a href="<?= htmlspecialchars($a['link']) ?>" target="_blank" class="text-xs text-sky-400 hover:text-sky-300 inline-flex items-center gap-1 mt-1">
                      <i class="fas fa-external-link-alt text-[10px]"></i> Lien
                    </a>
                  <?php endif; ?>
                </td>
                <td class="px-5 py-4 whitespace-nowrap">
                  <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-semibold bg-<?= $typeInfo['color'] ?>-500/10 text-<?= $typeInfo['color'] ?>-400 border border-<?= $typeInfo['color'] ?>-500/20">
                    <i class="fas <?= $typeInfo['icon'] ?> text-[9px]"></i>
                    <?= $typeInfo['label'] ?>
                  </span>
                </td>
                <td class="px-5 py-4 whitespace-nowrap">
                  <span class="text-xs bg-white/5 text-gray-300 border border-white/10 px-2.5 py-1 rounded-full font-medium inline-flex items-center">
                    <i class="fas fa-users text-[10px] mr-1.5 text-gray-400"></i><?= $recipientCount ?> utilisateur(s)
                  </span>
                </td>
                <td class="px-5 py-4 text-xs text-gray-400 whitespace-nowrap"><?= htmlspecialchars($creator) ?></td>
                <td class="px-5 py-4 text-right whitespace-nowrap">
                  <div class="flex items-center justify-end gap-2">
                    <button onclick='openEditModal(<?= htmlspecialchars(json_encode($a), ENT_QUOTES) ?>)' class="p-2 text-gray-400 hover:text-white hover:bg-white/5 rounded-lg transition-colors text-xs" title="Voir les détails">
                      <i class="fas fa-eye"></i>
                    </button>
                    
                    <form method="POST" class="inline" onsubmit="return confirm('Supprimer cette annonce et toutes ses copies ?')">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="id" value="<?= $a['id'] ?>">
                      <button class="p-2 text-red-400 hover:text-red-300 hover:bg-red-500/10 rounded-lg transition-colors text-xs">
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
      <?php endif; ?>
    </div>
  </div>
</div>

<div id="modalAnnouncement" class="hidden fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4" style="backdrop-filter:blur(6px)">
  <div class="bg-[#161a22] border border-white/10 rounded-2xl w-full max-w-2xl p-6 max-h-[90vh] overflow-y-auto">
    <div class="flex items-center justify-between mb-5">
      <h3 id="modalTitle" class="text-base font-bold text-white">Créer une Annonce</h3>
      <button onclick="document.getElementById('modalAnnouncement').classList.add('hidden')" class="text-gray-500 hover:text-white transition-colors">
        <i class="fas fa-times"></i>
      </button>
    </div>
    
    <form method="POST" class="space-y-4">
      <input type="hidden" name="action" id="aAction" value="add">
      <input type="hidden" name="id" id="aId" value="">

      <div>
        <label class="block text-xs font-semibold text-gray-400 mb-1.5">Titre de l'annonce <span class="text-red-400">*</span></label>
        <input name="title" id="aTitle" class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-white text-sm focus:outline-none focus:border-rose-500 transition-colors" placeholder="Ex: Maintenance prévue ce weekend" required maxlength="255">
      </div>

      <div>
        <label class="block text-xs font-semibold text-gray-400 mb-1.5">Message <span class="text-red-400">*</span></label>
        <textarea name="message" id="aMessage" class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-white text-sm focus:outline-none focus:border-rose-500 transition-colors" rows="5" placeholder="Détails de l'annonce..." required></textarea>
        <div class="text-[10px] text-gray-600 mt-1">Le message peut contenir du texte simple. Évitez le HTML.</div>
      </div>

      <div>
        <label class="block text-xs font-semibold text-gray-400 mb-1.5">Lien (optionnel)</label>
        <input name="link" id="aLink" class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-white text-sm focus:outline-none focus:border-rose-500 transition-colors" placeholder="https://example.com/page" type="url">
      </div>

      <div>
        <label class="block text-xs font-semibold text-gray-400 mb-1.5">Type d'annonce</label>
        <div class="grid grid-cols-2 sm:grid-cols-5 gap-2">
          <?php foreach ($notification_types as $key => $type): ?>
          <label class="cursor-pointer">
            <input type="radio" name="type" value="<?= $key ?>" class="peer hidden" <?= $key === 'announcement' ? 'checked' : '' ?>>
            <div class="border border-white/10 rounded-xl p-2.5 text-center transition-all peer-checked:border-rose-500 peer-checked:bg-rose-500/10 hover:bg-white/5">
              <i class="fas <?= $type['icon'] ?> text-gray-400 peer-checked:text-rose-400 text-lg mb-1 block"></i>
              <div class="text-[10px] font-medium text-gray-400"><?= $type['label'] ?></div>
            </div>
          </label>
          <?php endforeach; ?>
        </div>
      </div>

      <div>
        <label class="block text-xs font-semibold text-gray-400 mb-1.5">Destinataires</label>
        <select name="target_type" id="aTargetType" class="w-full bg-[#161a22] border border-white/10 rounded-xl px-3 py-2 text-white text-sm focus:outline-none focus:border-rose-500 transition-colors" onchange="toggleSpecificIds()">
          <option value="all">Tous les utilisateurs</option>
          <option value="specific">Utilisateurs spécifiques</option>
        </select>
      </div>

      <div id="specificIdsGroup" class="hidden">
        <label class="block text-xs font-semibold text-gray-400 mb-1.5">IDs des utilisateurs</label>
        <input name="specific_ids" id="aSpecificIds" class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-white text-sm font-mono focus:outline-none focus:border-rose-500 transition-colors" placeholder="Ex: 1, 5, 12, 45">
        <div class="text-[10px] text-gray-600 mt-1">Séparez les IDs par des virgules. Exemple: 1, 5, 12</div>
      </div>

      <div class="flex flex-col sm:flex-row gap-3 pt-4">
        <button type="submit" class="w-full px-4 py-2.5 bg-rose-600 hover:bg-rose-500 text-white rounded-xl text-xs font-semibold flex items-center justify-center gap-2 transition-all order-1 sm:order-2">
          <i class="fas fa-paper-plane text-[10px]"></i> Envoyer l'annonce
        </button>
        <button type="button" onclick="document.getElementById('modalAnnouncement').classList.add('hidden')" class="w-full px-4 py-2.5 bg-white/5 hover:bg-white/10 text-gray-400 hover:text-white rounded-xl text-xs font-semibold transition-all order-2 sm:order-1">
          Annuler
        </button>
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
    
    const defaultRadio = document.querySelector('input[name="type"][value="announcement"]');
    if (defaultRadio) defaultRadio.checked = true;
    
    document.getElementById('aTargetType').value = 'all';
    document.getElementById('aTargetType').disabled = false;
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
    
    const typeRadio = document.querySelector(`input[name="type"][value="${a.type}"]`);
    if (typeRadio) typeRadio.checked = true;
    
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
</script>
</body></html>
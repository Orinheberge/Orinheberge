<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: /login/'); exit(); }

$pdo = new PDO('mysql:host=localhost;dbname=s43_orinheberge;charset=utf8mb4', 'root', '1504', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

$stmt = $pdo->prepare('SELECT id,pseudo,firstname,avatar,is_admin FROM users WHERE id=? LIMIT 1');
$stmt->execute([$_SESSION['user_id']]);
$admin = $stmt->fetch();
if (!$admin || !$admin['is_admin']) { http_response_code(403); die('403 Forbidden'); }

$flash = '';

// ── Actions POST ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $id               = (int)($_POST['id']               ?? 0);
        $title            = trim($_POST['title']             ?? '');
        $slug             = preg_replace('/[^a-z0-9\-]/', '', strtolower(trim($_POST['slug'] ?? '')));
        $description      = trim($_POST['description']      ?? '');
        $type             = $_POST['type']                   ?? 'planned';
        $status           = $_POST['status']                 ?? 'scheduled';
        $severity         = $_POST['severity']               ?? 'info';
        $start_date       = trim($_POST['start_date']        ?? '');
        $end_date         = trim($_POST['end_date']          ?? '');
        $affects_all      = isset($_POST['affects_all'])     ? 1 : 0;
        $affected_services = json_encode(array_filter(array_map('trim', explode(',', $_POST['affected_services'] ?? ''))));
        $is_public        = isset($_POST['is_public'])       ? 1 : 0;
        $is_active        = isset($_POST['is_active'])       ? 1 : 0;
        $show_banner      = isset($_POST['show_banner'])     ? 1 : 0;
        $block_access     = isset($_POST['block_access'])    ? 1 : 0;

        // Générer le slug automatiquement si vide
        if (empty($slug) && !empty($title)) {
            $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($title));
            $slug = trim($slug, '-');
        }

        // Validation
        $valid_types     = ['planned', 'emergency', 'improvement', 'security'];
        $valid_statuses  = ['scheduled', 'in_progress', 'completed', 'cancelled', 'postponed'];
        $valid_severities = ['info', 'warning', 'critical'];

        if (!in_array($type, $valid_types)) $type = 'planned';
        if (!in_array($status, $valid_statuses)) $status = 'scheduled';
        if (!in_array($severity, $valid_severities)) $severity = 'info';

        if ($title && $start_date && $end_date) {
            try {
                if ($action === 'add') {
                    $pdo->prepare('
                        INSERT INTO maintenance 
                        (title, slug, description, type, status, severity, 
                         start_date, end_date, affects_all, affected_services,
                         is_public, is_active, show_banner, block_access, created_by)
                        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
                    ')->execute([
                        $title, $slug, $description, $type, $status, $severity,
                        $start_date, $end_date, $affects_all, $affected_services,
                        $is_public, $is_active, $show_banner, $block_access, $_SESSION['user_id']
                    ]);
                    $action_label = 'créée';
                } else {
                    $pdo->prepare('
                        UPDATE maintenance 
                        SET title=?, slug=?, description=?, type=?, status=?, severity=?,
                            start_date=?, end_date=?, affects_all=?, affected_services=?,
                            is_public=?, is_active=?, show_banner=?, block_access=?, updated_by=?
                        WHERE id=?
                    ')->execute([
                        $title, $slug, $description, $type, $status, $severity,
                        $start_date, $end_date, $affects_all, $affected_services,
                        $is_public, $is_active, $show_banner, $block_access, $_SESSION['user_id'], $id
                    ]);
                    $action_label = 'modifiée';
                }
                $flash = '<div class="bg-green-500/15 text-green-400 border border-green-500/25 p-3 rounded-xl text-sm mb-4">✅ Maintenance ' . $action_label . ' avec succès.</div>';
            } catch (PDOException $e) {
                $flash = '<div class="bg-red-500/15 text-red-400 border border-red-500/25 p-3 rounded-xl text-sm mb-4">❌ Erreur : ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
        } else {
            $flash = '<div class="bg-red-500/15 text-red-400 border border-red-500/25 p-3 rounded-xl text-sm mb-4">❌ Champs obligatoires manquants (titre, date début, date fin).</div>';
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare('DELETE FROM maintenance WHERE id=?')->execute([$id]);
        $flash = '<div class="bg-green-500/15 text-green-400 border border-green-500/25 p-3 rounded-xl text-sm mb-4">✅ Maintenance supprimée.</div>';
    }

    if ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare('UPDATE maintenance SET is_active = 1 - is_active WHERE id=?')->execute([$id]);
    }

    if ($action === 'status') {
        $id = (int)($_POST['id'] ?? 0);
        $new_status = $_POST['new_status'] ?? '';
        $valid_statuses = ['scheduled', 'in_progress', 'completed', 'cancelled', 'postponed'];
        if (in_array($new_status, $valid_statuses)) {
            $pdo->prepare('UPDATE maintenance SET status = ? WHERE id=?')->execute([$new_status, $id]);
            
            // Mettre à jour les dates réelles
            if ($new_status === 'in_progress') {
                $pdo->prepare('UPDATE maintenance SET actual_start = NOW() WHERE id=? AND actual_start IS NULL')->execute([$id]);
            }
            if ($new_status === 'completed') {
                $pdo->prepare('UPDATE maintenance SET actual_end = NOW() WHERE id=? AND actual_end IS NULL')->execute([$id]);
            }
        }
    }
}

// ── Charger les maintenances ──────────────────────────────────
$maintenances = $pdo->query('
    SELECT m.*, u.pseudo AS creator_pseudo, u.firstname AS creator_firstname
    FROM maintenance m
    LEFT JOIN users u ON u.id = m.created_by
    ORDER BY m.start_date DESC
')->fetchAll();

// ── Stats ─────────────────────────────────────────────────────
$stats = [
    'total'      => count($maintenances),
    'ongoing'    => count(array_filter($maintenances, fn($m) => $m['status'] === 'in_progress' && strtotime($m['start_date']) <= time() && strtotime($m['end_date']) >= time())),
    'scheduled'  => count(array_filter($maintenances, fn($m) => $m['status'] === 'scheduled' && strtotime($m['start_date']) > time())),
    'completed'  => count(array_filter($maintenances, fn($m) => $m['status'] === 'completed')),
    'critical'   => count(array_filter($maintenances, fn($m) => $m['severity'] === 'critical' && $m['status'] !== 'completed' && $m['status'] !== 'cancelled')),
];

// ── Configurations pour affichage ─────────────────────────────
$type_config = [
    'planned'     => ['icon' => 'fa-calendar-check', 'color' => 'sky',     'label' => 'Planifiée'],
    'emergency'   => ['icon' => 'fa-bolt',           'color' => 'red',     'label' => 'Urgence'],
    'improvement' => ['icon' => 'fa-arrow-up',       'color' => 'emerald', 'label' => 'Amélioration'],
    'security'    => ['icon' => 'fa-shield-halved',  'color' => 'purple',  'label' => 'Sécurité'],
];

$status_config = [
    'scheduled'   => ['icon' => 'fa-clock',          'color' => 'amber',   'label' => 'Planifiée'],
    'in_progress' => ['icon' => 'fa-spinner',        'color' => 'sky',     'label' => 'En cours'],
    'completed'   => ['icon' => 'fa-check',          'color' => 'green',   'label' => 'Terminée'],
    'cancelled'   => ['icon' => 'fa-ban',            'color' => 'gray',    'label' => 'Annulée'],
    'postponed'   => ['icon' => 'fa-hourglass-half', 'color' => 'orange',  'label' => 'Reportée'],
];

$severity_config = [
    'info'     => ['icon' => 'fa-info-circle',         'color' => 'sky',   'label' => 'Info'],
    'warning'  => ['icon' => 'fa-exclamation-triangle','color' => 'amber', 'label' => 'Attention'],
    'critical' => ['icon' => 'fa-radiation',           'color' => 'red',   'label' => 'Critique'],
];

$active_nav = 'maintenance';
include $_SERVER['DOCUMENT_ROOT'] . '/inc/admin_layout.php';
?>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

<div class="main-content p-6 bg-[#0d1117] min-h-screen text-gray-100">
  <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6 pb-6 border-b border-white/[0.05]">
    <div class="flex items-center gap-3">
      <button id="adminSidebarToggle" class="md:hidden text-gray-400 hover:text-white text-lg w-8" aria-label="Ouvrir le menu admin">
        <i class="fas fa-bars"></i>
      </button>
      <div>
        <div class="text-sm font-bold text-white flex items-center gap-2">
          <i class="fas fa-wrench text-sky-400 text-xs"></i> Gestion des Maintenances
        </div>
        <div class="text-xs text-gray-500 mt-1"><?= $stats['total'] ?> maintenance(s) · <?= $stats['ongoing'] ?> en cours</div>
      </div>
    </div>
    <button onclick="openAddModal()" class="px-4 py-2 bg-sky-600 hover:bg-sky-500 text-white rounded-xl text-xs font-semibold flex items-center gap-2 transition-colors">
      <i class="fas fa-plus text-[10px]"></i> Planifier une Maintenance
    </button>
  </div>

  <div class="content space-y-6">
    <?= $flash ?>

    <div class="grid grid-cols-2 sm:grid-cols-5 gap-4">
      <div class="bg-[#161a22] border border-white/10 rounded-2xl p-4">
        <div class="text-xs text-gray-500 mb-1">Total</div>
        <div class="text-2xl font-black text-white"><?= $stats['total'] ?></div>
      </div>
      <div class="bg-[#161a22] border border-white/10 border-l-2 border-l-sky-500 rounded-2xl p-4">
        <div class="text-xs text-gray-500 mb-1">En cours</div>
        <div class="text-2xl font-black text-sky-400"><?= $stats['ongoing'] ?></div>
      </div>
      <div class="bg-[#161a22] border border-white/10 border-l-2 border-l-amber-500 rounded-2xl p-4">
        <div class="text-xs text-gray-500 mb-1">Planifiées</div>
        <div class="text-2xl font-black text-amber-400"><?= $stats['scheduled'] ?></div>
      </div>
      <div class="bg-[#161a22] border border-white/10 border-l-2 border-l-green-500 rounded-2xl p-4">
        <div class="text-xs text-gray-500 mb-1">Terminées</div>
        <div class="text-2xl font-black text-green-400"><?= $stats['completed'] ?></div>
      </div>
      <div class="bg-[#161a22] border border-white/10 border-l-2 border-l-red-500 rounded-2xl p-4">
        <div class="text-xs text-gray-500 mb-1">Critiques</div>
        <div class="text-2xl font-black text-red-400"><?= $stats['critical'] ?></div>
      </div>
    </div>

    <?php
    $active_maintenances = array_filter($maintenances, fn($m) => 
        ($m['status'] === 'in_progress' || $m['status'] === 'scheduled') &&
        strtotime($m['end_date']) >= time() &&
        $m['is_active'] == 1
    );
    ?>
    <?php if (!empty($active_maintenances)): ?>
    <div class="space-y-3">
      <?php foreach ($active_maintenances as $m): 
        $sev = $severity_config[$m['severity']] ?? $severity_config['info'];
        $sta = $status_config[$m['status']] ?? $status_config['scheduled'];
        $end_time = strtotime($m['end_date']);
        $now = time();
        $remaining = $end_time - $now;
        $hours = floor($remaining / 3600);
        $mins = floor(($remaining % 3600) / 60);
      ?>
      <div class="bg-[#161a22] border border-white/10 border-l-4 border-l-<?= $sev['color'] ?>-500 rounded-2xl p-4 flex items-center justify-between gap-4">
        <div class="flex items-center gap-4 min-w-0">
          <div class="w-10 h-10 rounded-lg bg-<?= $sev['color'] ?>-500/10 border border-<?= $sev['color'] ?>-500/20 flex items-center justify-center shrink-0">
            <i class="fas <?= $sev['icon'] ?> text-<?= $sev['color'] ?>-400"></i>
          </div>
          <div class="min-w-0">
            <div class="flex items-center gap-2 flex-wrap">
              <span class="font-bold text-white text-sm truncate"><?= htmlspecialchars($m['title']) ?></span>
              <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase bg-<?= $sta['color'] ?>-500/10 text-<?= $sta['color'] ?>-400 border border-<?= $sta['color'] ?>-500/20 whitespace-nowrap">
                <?= $sta['label'] ?>
              </span>
            </div>
            <div class="text-xs text-gray-500 mt-0.5">
              <?= date('d/m/Y H:i', strtotime($m['start_date'])) ?> — <?= date('H:i', strtotime($m['end_date'])) ?>
              <?php if ($remaining > 0): ?>
              · <span class="text-<?= $sev['color'] ?>-400 font-semibold">
                <?= $hours > 0 ? $hours . 'h ' : '' ?><?= $mins ?>min restantes
              </span>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <div class="flex items-center gap-1.5 shrink-0">
          <button onclick='openEditModal(<?= htmlspecialchars(json_encode($m), ENT_QUOTES) ?>)' class="p-2 text-gray-400 hover:text-white hover:bg-white/5 rounded-lg transition-colors text-xs">
            <i class="fas fa-edit"></i>
          </button>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="bg-[#161a22] border border-white/10 rounded-2xl overflow-hidden">
      <div class="px-5 py-4 border-b border-white/[0.05] flex items-center justify-between">
        <span class="text-sm font-bold text-white flex items-center gap-2">
          <i class="fas fa-list text-sky-400 text-xs"></i> Historique des maintenances
        </span>
      </div>
      <?php if (empty($maintenances)): ?>
        <div class="px-5 py-12 text-center text-gray-500 text-sm">
          <i class="fas fa-calendar-xmark text-4xl text-gray-700 mb-3 block"></i>
          Aucune maintenance. Planifiez-en une via le bouton ci-dessus.
        </div>
      <?php else: ?>
      <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="border-b border-white/[0.05] text-[11px] text-gray-400 uppercase tracking-wider">
              <th class="px-5 py-3.5 font-semibold">Titre</th>
              <th class="px-4 py-3.5 font-semibold">Type</th>
              <th class="px-4 py-3.5 font-semibold">Sévérité</th>
              <th class="px-4 py-3.5 font-semibold">Statut</th>
              <th class="px-4 py-3.5 font-semibold">Période</th>
              <th class="px-4 py-3.5 font-semibold">Portée</th>
              <th class="px-4 py-3.5 font-semibold">Visibilité</th>
              <th class="px-5 py-3.5 font-semibold text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-white/[0.02]">
            <?php foreach ($maintenances as $m): 
              $typ = $type_config[$m['type']] ?? $type_config['planned'];
              $sta = $status_config[$m['status']] ?? $status_config['scheduled'];
              $sev = $severity_config[$m['severity']] ?? $severity_config['info'];
              $services = json_decode($m['affected_services'] ?? '[]', true) ?: [];
            ?>
            <tr class="hover:bg-white/[0.01] transition-colors">
              <td class="px-5 py-4">
                <div class="font-semibold text-white text-sm"><?= htmlspecialchars($m['title']) ?></div>
                <div class="text-[10px] text-gray-600 font-mono mt-0.5"><?= htmlspecialchars($m['slug']) ?></div>
              </td>
              <td class="px-4 py-4">
                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase bg-<?= $typ['color'] ?>-500/10 text-<?= $typ['color'] ?>-400 border border-<?= $typ['color'] ?>-500/20 whitespace-nowrap">
                  <i class="fas <?= $typ['icon'] ?> text-[10px]"></i>
                  <?= $typ['label'] ?>
                </span>
              </td>
              <td class="px-4 py-4">
                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase bg-<?= $sev['color'] ?>-500/10 text-<?= $sev['color'] ?>-400 border border-<?= $sev['color'] ?>-500/20 whitespace-nowrap">
                  <i class="fas <?= $sev['icon'] ?> text-[10px]"></i>
                  <?= $sev['label'] ?>
                </span>
              </td>
              <td class="px-4 py-4">
                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase bg-<?= $sta['color'] ?>-500/10 text-<?= $sta['color'] ?>-400 border border-<?= $sta['color'] ?>-500/20 whitespace-nowrap">
                  <i class="fas <?= $sta['icon'] ?> text-[10px] <?= $m['status'] === 'in_progress' ? 'fa-spin' : '' ?>"></i>
                  <?= $sta['label'] ?>
                </span>
              </td>
              <td class="px-4 py-4">
                <div class="text-xs text-gray-300">
                  <i class="fas fa-calendar text-gray-600 text-[10px] mr-1"></i>
                  <?= date('d/m/Y', strtotime($m['start_date'])) ?>
                </div>
                <div class="text-[10px] text-gray-500 font-mono mt-0.5">
                  <?= date('H:i', strtotime($m['start_date'])) ?> → <?= date('H:i', strtotime($m['end_date'])) ?>
                </div>
              </td>
              <td class="px-4 py-4">
                <?php if ($m['affects_all']): ?>
                  <span class="text-xs text-amber-400 flex items-center gap-1"><i class="fas fa-globe text-[10px]"></i> Tous</span>
                <?php elseif (!empty($services)): ?>
                  <div class="flex flex-wrap gap-1 max-w-[150px]">
                    <?php foreach (array_slice($services, 0, 3) as $s): ?>
                      <span class="text-[10px] bg-sky-500/10 text-sky-400 px-1.5 py-0.5 rounded border border-sky-500/20"><?= htmlspecialchars($s) ?></span>
                    <?php endforeach; ?>
                    <?php if (count($services) > 3): ?>
                      <span class="text-[10px] text-gray-500 self-center">+<?= count($services) - 3 ?></span>
                    <?php endif; ?>
                  </div>
                <?php else: ?>
                  <span class="text-xs text-gray-600">—</span>
                <?php endif; ?>
              </td>
              <td class="px-4 py-4">
                <div class="flex flex-col gap-0.5">
                  <?php if ($m['is_public']): ?><span class="text-[10px] text-green-400"><i class="fas fa-eye text-[9px] mr-1"></i> Public</span><?php endif; ?>
                  <?php if ($m['show_banner']): ?><span class="text-[10px] text-sky-400"><i class="fas fa-bullhorn text-[9px] mr-1"></i> Bandeau</span><?php endif; ?>
                  <?php if ($m['block_access']): ?><span class="text-[10px] text-red-400"><i class="fas fa-lock text-[9px] mr-1"></i> Bloqué</span><?php endif; ?>
                  <?php if (!$m['is_public'] && !$m['show_banner'] && !$m['block_access']): ?>
                    <span class="text-[10px] text-gray-600">Masquée</span>
                  <?php endif; ?>
                </div>
              </td>
              <td class="px-5 py-4 text-right whitespace-nowrap">
                <div class="flex items-center justify-end gap-1.5">
                  <div class="relative group">
                    <button class="p-2 text-gray-400 hover:text-white hover:bg-white/5 rounded-lg transition-colors text-xs" aria-label="Statut">
                      <i class="fas fa-exchange-alt"></i>
                    </button>
                    <div class="absolute right-0 top-full mt-1 w-40 bg-[#1a1f2a] border border-white/10 rounded-xl shadow-2xl py-1.5 hidden group-hover:block z-50">
                      <?php foreach ($status_config as $st_key => $st_cfg): ?>
                        <form method="POST" class="inline">
                          <input type="hidden" name="action" value="status">
                          <input type="hidden" name="id" value="<?= $m['id'] ?>">
                          <input type="hidden" name="new_status" value="<?= $st_key ?>">
                          <button class="w-full text-left px-3 py-1.5 text-xs text-gray-300 hover:bg-white/5 hover:text-white flex items-center gap-2">
                            <i class="fas <?= $st_cfg['icon'] ?> text-<?= $st_cfg['color'] ?>-400 text-[10px]"></i>
                            <?= $st_cfg['label'] ?>
                          </button>
                        </form>
                      <?php endforeach; ?>
                    </div>
                  </div>
                  
                  <button onclick='openEditModal(<?= htmlspecialchars(json_encode($m), ENT_QUOTES) ?>)' class="p-2 text-gray-400 hover:text-white hover:bg-white/5 rounded-lg transition-colors text-xs" title="Modifier">
                    <i class="fas fa-edit"></i>
                  </button>
                  
                  <form method="POST" class="inline">
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="id" value="<?= $m['id'] ?>">
                    <button class="p-2 text-gray-400 hover:text-white hover:bg-white/5 rounded-lg transition-colors text-xs" title="<?= $m['is_active'] ? 'Désactiver' : 'Activer' ?>">
                      <?= $m['is_active'] ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>' ?>
                    </button>
                  </form>
                  
                  <form method="POST" class="inline" onsubmit="return confirm('Supprimer cette maintenance ?')">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= $m['id'] ?>">
                    <button class="p-2 text-red-400 hover:text-red-300 hover:bg-red-500/5 rounded-lg transition-colors text-xs" title="Supprimer">
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

<div id="modalMaintenance" class="hidden fixed inset-0 bg-black/60 z-50 flex items-center justify-center p-4" style="backdrop-filter:blur(6px)">
  <div class="bg-[#161a22] border border-white/10 rounded-2xl w-full max-w-3xl p-6 max-h-[90vh] overflow-y-auto">
    <div class="flex items-center justify-between mb-5 border-b border-white/[0.05] pb-4">
      <h3 id="modalTitle" class="text-base font-bold text-white flex items-center gap-2">
        <i class="fas fa-wrench text-sky-400"></i>
        <span>Planifier une Maintenance</span>
      </h3>
      <button onclick="document.getElementById('modalMaintenance').classList.add('hidden')" class="text-gray-500 hover:text-white"><i class="fas fa-times"></i></button>
    </div>
    
    <form method="POST" class="space-y-4">
      <input type="hidden" name="action" id="mAction" value="add">
      <input type="hidden" name="id" id="mId" value="">

      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-semibold text-gray-400 mb-1.5">Titre <span class="text-red-400">*</span></label>
          <input name="title" id="mTitle" required placeholder="Ex: Mise à jour système" class="w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-sm text-white focus:border-sky-500 focus:outline-none transition-colors">
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-400 mb-1.5">Slug (URL) <span class="text-gray-600">(auto)</span></label>
          <input name="slug" id="mSlug" placeholder="Généré automatiquement" class="w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-sm text-white font-mono focus:border-sky-500 focus:outline-none transition-colors">
        </div>
      </div>

      <div>
        <label class="block text-xs font-semibold text-gray-400 mb-1.5">Description</label>
        <textarea name="description" id="mDesc" rows="2" placeholder="Expliquez l'objet de la maintenance aux utilisateurs..." class="w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-sm text-white focus:border-sky-500 focus:outline-none transition-colors"></textarea>
      </div>

      <div class="grid grid-cols-3 gap-3">
        <div>
          <label class="block text-xs font-semibold text-gray-400 mb-1.5">Type</label>
          <select name="type" id="mType" class="w-full rounded-xl border border-white/10 bg-[#161a22] px-3 py-2 text-sm text-white focus:border-sky-500 focus:outline-none transition-colors">
            <option value="planned">📅 Planifiée</option>
            <option value="emergency">⚡ Urgence</option>
            <option value="improvement">📈 Amélioration</option>
            <option value="security">🛡️ Sécurité</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-400 mb-1.5">Statut</label>
          <select name="status" id="mStatus" class="w-full rounded-xl border border-white/10 bg-[#161a22] px-3 py-2 text-sm text-white focus:border-sky-500 focus:outline-none transition-colors">
            <option value="scheduled">🕐 Planifiée</option>
            <option value="in_progress">⚙️ En cours</option>
            <option value="completed">✅ Terminée</option>
            <option value="cancelled">❌ Annulée</option>
            <option value="postponed">⏸️ Reportée</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-400 mb-1.5">Sévérité</label>
          <select name="severity" id="mSeverity" class="w-full rounded-xl border border-white/10 bg-[#161a22] px-3 py-2 text-sm text-white focus:border-sky-500 focus:outline-none transition-colors">
            <option value="info">ℹ️ Information</option>
            <option value="warning">⚠️ Attention</option>
            <option value="critical">☢️ Critique</option>
          </select>
        </div>
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-semibold text-gray-400 mb-1.5">Date de début <span class="text-red-400">*</span></label>
          <input name="start_date" id="mStart" type="datetime-local" required class="w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-sm text-white focus:border-sky-500 focus:outline-none transition-colors">
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-400 mb-1.5">Date de fin <span class="text-red-400">*</span></label>
          <input name="end_date" id="mEnd" type="datetime-local" required class="w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-sm text-white focus:border-sky-500 focus:outline-none transition-colors">
        </div>
      </div>

      <div class="border border-white/5 rounded-xl p-4 bg-white/[0.02]">
        <div class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3 flex items-center gap-2">
          <i class="fas fa-bullseye text-sky-400"></i> Portée de la maintenance
        </div>
        
        <div class="flex items-center gap-2 mb-3">
          <input type="checkbox" name="affects_all" id="mAffectsAll" value="1" class="w-4 h-4 rounded border-white/10 bg-white/5 text-sky-600 focus:ring-sky-500 focus:ring-offset-0">
          <label for="mAffectsAll" class="text-xs text-gray-300 font-medium">Impacte tous les services</label>
        </div>

        <div id="servicesField">
          <label class="block text-xs font-semibold text-gray-400 mb-1.5">Services concernés <span class="text-gray-600">(séparés par des virgules)</span></label>
          <input name="affected_services" id="mServices" placeholder="panel, billing, api, node-paris" class="w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-xs text-white font-mono focus:border-sky-500 focus:outline-none transition-colors">
          <div class="flex flex-wrap gap-1 mt-2">
            <?php foreach (['panel', 'billing', 'api', 'node-orin', 'node-deepstone', 'plesk', 'phpmyadmin'] as $s): ?>
            <button type="button" onclick="addService('<?= $s ?>')" class="text-[10px] bg-white/5 hover:bg-white/10 border border-white/10 text-gray-400 hover:text-white px-2 py-0.5 rounded transition">
              + <?= $s ?>
            </button>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <div class="border border-white/5 rounded-xl p-4 bg-white/[0.02]">
        <div class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-3 flex items-center gap-2">
          <i class="fas fa-eye text-sky-400"></i> Options d'affichage
        </div>
        
        <div class="grid grid-cols-2 gap-3">
          <label class="flex items-center gap-3 bg-white/[0.03] border border-white/[0.07] rounded-xl px-3 py-2 cursor-pointer hover:bg-white/[0.06] transition">
            <input type="checkbox" name="is_public" id="mPublic" value="1" checked class="w-4 h-4 rounded border-white/10 bg-white/5 text-sky-600 focus:ring-sky-500 focus:ring-offset-0">
            <div>
              <div class="text-xs text-gray-300 font-semibold">Visible publiquement</div>
              <div class="text-[10px] text-gray-600 mt-0.5">Affichée sur la page statut</div>
            </div>
          </label>

          <label class="flex items-center gap-3 bg-white/[0.03] border border-white/[0.07] rounded-xl px-3 py-2 cursor-pointer hover:bg-white/[0.06] transition">
            <input type="checkbox" name="is_active" id="mActive" value="1" checked class="w-4 h-4 rounded border-white/10 bg-white/5 text-sky-600 focus:ring-sky-500 focus:ring-offset-0">
            <div>
              <div class="text-xs text-gray-300 font-semibold">Active</div>
              <div class="text-[10px] text-gray-600 mt-0.5">Prise en compte par le système</div>
            </div>
          </label>

          <label class="flex items-center gap-3 bg-white/[0.03] border border-white/[0.07] rounded-xl px-3 py-2 cursor-pointer hover:bg-white/[0.06] transition">
            <input type="checkbox" name="show_banner" id="mBanner" value="1" checked class="w-4 h-4 rounded border-white/10 bg-white/5 text-sky-600 focus:ring-sky-500 focus:ring-offset-0">
            <div>
              <div class="text-xs text-gray-300 font-semibold">Afficher un bandeau</div>
              <div class="text-[10px] text-gray-600 mt-0.5">Bandeau d'alerte en haut du site</div>
            </div>
          </label>

          <label class="flex items-center gap-3 bg-red-500/5 border border-red-500/20 rounded-xl px-3 py-2 cursor-pointer hover:bg-red-500/10 transition">
            <input type="checkbox" name="block_access" id="mBlock" value="1" class="w-4 h-4 rounded border-red-500/20 bg-red-500/5 text-red-600 focus:ring-red-500 focus:ring-offset-0">
            <div>
              <div class="text-xs text-red-300 font-semibold">⚠️ Bloquer l'accès</div>
              <div class="text-[10px] text-red-500/50 mt-0.5">Mode maintenance total</div>
            </div>
          </label>
        </div>
      </div>

      <div class="flex gap-3 pt-4 border-t border-white/[0.05]">
        <button type="submit" class="flex-1 py-2.5 bg-sky-600 hover:bg-sky-500 text-white text-xs font-semibold rounded-xl transition-colors">
          <i class="fas fa-save mr-1"></i> Sauvegarder
        </button>
        <button type="button" onclick="document.getElementById('modalMaintenance').classList.add('hidden')" class="flex-1 py-2.5 bg-white/5 hover:bg-white/10 text-gray-300 hover:text-white text-xs font-semibold rounded-xl transition-colors">
          Annuler
        </button>
      </div>
    </form>
  </div>
</div>

<script>
function addService(s) {
    const input = document.getElementById('mServices');
    const current = input.value.split(',').map(x => x.trim()).filter(x => x);
    if (!current.includes(s)) {
        current.push(s);
        input.value = current.join(', ');
    }
}

function openAddModal() {
    document.getElementById('modalTitle').querySelector('span').textContent = 'Planifier une Maintenance';
    document.getElementById('mAction').value = 'add';
    document.getElementById('mId').value = '';
    document.getElementById('mTitle').value = '';
    document.getElementById('mSlug').value = '';
    document.getElementById('mDesc').value = '';
    document.getElementById('mType').value = 'planned';
    document.getElementById('mStatus').value = 'scheduled';
    document.getElementById('mSeverity').value = 'warning';
    document.getElementById('mStart').value = '';
    document.getElementById('mEnd').value = '';
    document.getElementById('mServices').value = '';
    document.getElementById('mAffectsAll').checked = true;
    document.getElementById('mPublic').checked = true;
    document.getElementById('mActive').checked = true;
    document.getElementById('mBanner').checked = true;
    document.getElementById('mBlock').checked = false;
    
    // Date par défaut : demain 2h du matin → 6h du matin
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    tomorrow.setHours(2, 0, 0, 0);
    const end = new Date(tomorrow);
    end.setHours(6, 0, 0, 0);
    
    document.getElementById('mStart').value = formatDateTimeLocal(tomorrow);
    document.getElementById('mEnd').value = formatDateTimeLocal(end);
    
    document.getElementById('modalMaintenance').classList.remove('hidden');
}

function openEditModal(m) {
    document.getElementById('modalTitle').querySelector('span').textContent = 'Modifier la Maintenance';
    document.getElementById('mAction').value = 'edit';
    document.getElementById('mId').value = m.id;
    document.getElementById('mTitle').value = m.title || '';
    document.getElementById('mSlug').value = m.slug || '';
    document.getElementById('mDesc').value = m.description || '';
    document.getElementById('mType').value = m.type || 'planned';
    document.getElementById('mStatus').value = m.status || 'scheduled';
    document.getElementById('mSeverity').value = m.severity || 'info';
    
    // Formater les dates pour datetime-local
    document.getElementById('mStart').value = (m.start_date || '').replace(' ', 'T').substring(0, 16);
    document.getElementById('mEnd').value = (m.end_date || '').replace(' ', 'T').substring(0, 16);
    
    // Services
    const services = JSON.parse(m.affected_services || '[]');
    document.getElementById('mServices').value = services.join(', ');
    
    // Checkboxes
    document.getElementById('mAffectsAll').checked = m.affects_all == 1;
    document.getElementById('mPublic').checked = m.is_public == 1;
    document.getElementById('mActive').checked = m.is_active == 1;
    document.getElementById('mBanner').checked = m.show_banner == 1;
    document.getElementById('mBlock').checked = m.block_access == 1;
    
    document.getElementById('modalMaintenance').classList.remove('hidden');
}

function formatDateTimeLocal(date) {
    const pad = n => String(n).padStart(2, '0');
    return date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate()) + 
           'T' + pad(date.getHours()) + ':' + pad(date.getMinutes());
}

// Auto-générer le slug depuis le titre
document.getElementById('mTitle').addEventListener('input', function() {
    const slugInput = document.getElementById('mSlug');
    if (!slugInput.dataset.manual) {
        slugInput.value = this.value.toLowerCase()
            .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');
    }
});
document.getElementById('mSlug').addEventListener('input', function() {
    this.dataset.manual = '1';
});
</script>
</body>
</html>
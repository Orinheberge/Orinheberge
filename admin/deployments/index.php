<?php
ini_set('display_errors', 1); error_reporting(E_ALL);
session_start();

if (!isset($_SESSION['user_id'])) { header('Location: /login/'); exit(); }


require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/config.php';


$stmt = $pdo->prepare('SELECT id,pseudo,firstname,avatar,is_admin FROM users WHERE id=? LIMIT 1');
$stmt->execute([$_SESSION['user_id']]);
$admin = $stmt->fetch();

if (!$admin || !$admin['is_admin']) { http_response_code(403); die('403 Forbidden'); }

// Générer un token CSRF pour sécuriser les POST
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$flash = '';
$message_type = '';

// ── Pagination ──────────────────────────────────────────────
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 15;
$offset = ($page - 1) * $per_page;

try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM notifications WHERE type = 'system_deploy'");
    $total = (int)$stmt->fetchColumn();
    $total_pages = max(1, ceil($total / $per_page));
} catch (PDOException $e) {
    $total = 0;
    $total_pages = 1;
}

// ── Actions POST ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérification CSRF
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        die('Token CSRF invalide.');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'mark_read') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND type = "system_deploy"')->execute([$id]);
            $flash = '<div class="bg-sky-500/15 text-sky-400 border border-sky-500/25 p-3 rounded-xl text-sm mb-4"><i class="fas fa-check-circle mr-2"></i>Déploiement marqué comme lu.</div>';
        }
    }

    if ($action === 'mark_all_read') {
        $pdo->exec("UPDATE notifications SET is_read = 1 WHERE type = 'system_deploy' AND is_read = 0");
        $flash = '<div class="bg-green-500/15 text-green-400 border border-green-500/25 p-3 rounded-xl text-sm mb-4"><i class="fas fa-check-double mr-2"></i>Tous les déploiements ont été marqués comme lus.</div>';
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $pdo->prepare('DELETE FROM notifications WHERE id = ? AND type = "system_deploy"')->execute([$id]);
            $flash = '<div class="bg-red-500/15 text-red-400 border border-red-500/25 p-3 rounded-xl text-sm mb-4"><i class="fas fa-trash mr-2"></i>Déploiement supprimé.</div>';
        }
    }

    if ($action === 'clear_all') {
        $pdo->exec("DELETE FROM notifications WHERE type = 'system_deploy'");
        $flash = '<div class="bg-red-500/15 text-red-400 border border-red-500/25 p-3 rounded-xl text-sm mb-4"><i class="fas fa-broom mr-2"></i>Historique entièrement vidé.</div>';
    }

    // Régénérer le token CSRF après un POST
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ── Chargement des données ──────────────────────────────────
$unread_count = 0;
$latest_version = '-';
$deployments = [];

try {
    $stmt_unread = $pdo->query("SELECT COUNT(*) FROM notifications WHERE type = 'system_deploy' AND is_read = 0");
    $unread_count = (int)$stmt_unread->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT id, title, message, link, is_read, meta, created_at
        FROM notifications
        WHERE type = 'system_deploy'
        ORDER BY created_at DESC
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $deployments = $stmt->fetchAll();

    if (!empty($deployments)) {
        $first_meta = json_decode($deployments[0]['meta'], true);
        if (isset($first_meta['version'])) {
            $latest_version = $first_meta['version'];
        }
    }
} catch (PDOException $e) {
    $flash = '<div class="bg-red-500/15 text-red-400 border border-red-500/25 p-3 rounded-xl text-sm mb-4"><i class="fas fa-exclamation-triangle mr-2"></i>Erreur chargement : ' . htmlspecialchars($e->getMessage()) . '</div>';
}

// ── Helpers ──────────────────────────────────────────────────
function timeAgo($datetime) {
    if (empty($datetime)) return '-';
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    if ($diff->y > 0) return 'il y a ' . $diff->y . ' an' . ($diff->y > 1 ? 's' : '');
    if ($diff->m > 0) return 'il y a ' . $diff->m . ' mois';
    if ($diff->d > 0) return 'il y a ' . $diff->d . ' jour' . ($diff->d > 1 ? 's' : '');
    if ($diff->h > 0) return 'il y a ' . $diff->h . 'h';
    if ($diff->i > 0) return 'il y a ' . $diff->i . 'min';
    return 'à l\'instant';
}

function extractMeta($json) {
    $data = json_decode($json, true);
    return $data ?: ['commit' => 'unknown', 'version' => '0.0.0'];
}

$active_nav = 'deployments';
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
                <div class="text-sm font-bold text-white flex items-center gap-2">
                    <i class="fas fa-rocket text-purple-400 text-xs"></i> Déploiements
                </div>
                <div class="text-xs text-gray-500">
                    <?= $total ?> entrée(s) • <?= $unread_count ?> non lue(s)
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <?= $flash ?>

        <!-- Stats -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="card p-5 flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-sky-500/10 flex items-center justify-center text-sky-400 text-xl shrink-0">
                    <i class="fas fa-server"></i>
                </div>
                <div>
                    <div class="text-xs text-gray-400 uppercase font-semibold tracking-wider">Total Deploys</div>
                    <div class="text-2xl font-bold text-white"><?= $total ?></div>
                </div>
            </div>

            <div class="card p-5 flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-violet-500/10 flex items-center justify-center text-violet-400 text-xl shrink-0">
                    <i class="fas fa-code-branch"></i>
                </div>
                <div>
                    <div class="text-xs text-gray-400 uppercase font-semibold tracking-wider">Version Actuelle</div>
                    <div class="text-xl font-bold text-white font-mono"><?= htmlspecialchars($latest_version) ?></div>
                </div>
            </div>

            <div class="card p-5 flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-amber-500/10 flex items-center justify-center text-amber-400 text-xl shrink-0">
                    <i class="fas fa-bell"></i>
                </div>
                <div>
                    <div class="text-xs text-gray-400 uppercase font-semibold tracking-wider">Non lus</div>
                    <div class="text-2xl font-bold text-white"><?= $unread_count ?></div>
                </div>
            </div>
        </div>

        <!-- Actions globales -->
        <?php if ($total > 0): ?>
        <div class="flex flex-wrap gap-3 mb-6">
            <form method="POST" class="inline">
                <input type="hidden" name="action" value="mark_all_read">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <button type="submit" class="btn bg-slate-700 hover:bg-slate-600 text-white px-4 py-2 rounded-lg text-xs font-medium transition">
                    <i class="fas fa-check-double mr-1"></i> Tout marquer comme lu
                </button>
            </form>
            <form method="POST" class="inline" onsubmit="return confirm('Voulez-vous vraiment supprimer tout l\'historique des déploiements ?');">
                <input type="hidden" name="action" value="clear_all">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <button type="submit" class="btn bg-red-500/10 hover:bg-red-500/20 text-red-400 border border-red-500/20 px-4 py-2 rounded-lg text-xs font-medium transition">
                    <i class="fas fa-trash mr-1"></i> Vider l'historique
                </button>
            </form>
        </div>
        <?php endif; ?>

        <!-- Liste des déploiements -->
        <?php if (empty($deployments)): ?>
            <div class="card p-10 text-center">
                <div class="w-16 h-16 bg-slate-800 rounded-full flex items-center justify-center mx-auto mb-4 text-slate-500 text-2xl">
                    <i class="fas fa-box-open"></i>
                </div>
                <h3 class="text-lg font-bold text-white mb-2">Aucun déploiement</h3>
                <p class="text-sm text-gray-400">Les déploiements effectués via GitHub Actions apparaîtront automatiquement ici.</p>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 gap-3">
                <?php foreach ($deployments as $deploy):
                    $meta = extractMeta($deploy['meta']);
                    $isUnread = !$deploy['is_read'];
                ?>
                <div class="card overflow-hidden <?= $isUnread ? 'border-l-4 border-l-sky-500' : '' ?>">
                    <div class="p-5">
                        <div class="flex flex-col md:flex-row md:items-start justify-between gap-4 mb-3">
                            <div class="flex items-start gap-3 flex-1">
                                <div class="w-10 h-10 rounded-xl <?= $isUnread ? 'bg-sky-500/15 text-sky-400' : 'bg-white/5 text-gray-400' ?> flex items-center justify-center text-lg shrink-0">
                                    <i class="fas fa-rocket"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <div class="font-bold text-white text-sm"><?= htmlspecialchars($deploy['title']) ?></div>
                                        <?php if ($isUnread): ?>
                                            <span class="badge badge-blue text-[10px]"><i class="fas fa-circle text-[6px]"></i> Nouveau</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex items-center gap-2 text-[11px] text-gray-500 mt-1 flex-wrap">
                                        <span><i class="far fa-calendar mr-1"></i><?= date('d/m/Y à H:i', strtotime($deploy['created_at'])) ?></span>
                                        <span class="w-1 h-1 bg-gray-600 rounded-full"></span>
                                        <span><?= timeAgo($deploy['created_at']) ?></span>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center gap-2 shrink-0">
                                <span class="badge badge-blue text-[10px] font-mono">
                                    <i class="fas fa-tag mr-1"></i><?= htmlspecialchars($meta['version']) ?>
                                </span>
                                <span class="badge badge-gray text-[10px] font-mono">
                                    <i class="fas fa-code-commit mr-1"></i><?= htmlspecialchars(substr($meta['commit'], 0, 7)) ?>
                                </span>
                            </div>
                        </div>

                        <div class="bg-black/20 rounded-lg p-3 border border-white/[0.03] mb-4 font-mono text-xs text-gray-300">
                            <div class="flex items-start gap-2">
                                <i class="fas fa-terminal text-gray-500 mt-0.5"></i>
                                <div class="break-all flex-1">
                                    <?= nl2br(htmlspecialchars($deploy['message'])) ?>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center justify-between pt-3 border-t border-white/[0.05]">
                            <div class="text-[11px] text-gray-500">
                                ID #<?= $deploy['id'] ?>
                                <?php if (!empty($meta['commit'])): ?>
                                    • <a href="https://github.com" target="_blank" class="hover:text-sky-400 transition">
                                        <i class="fab fa-github mr-1"></i>Voir commit
                                    </a>
                                <?php endif; ?>
                            </div>

                            <div class="flex items-center gap-2">
                                <?php if ($isUnread): ?>
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="action" value="mark_read">
                                        <input type="hidden" name="id" value="<?= $deploy['id'] ?>">
                                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                        <button type="submit" class="text-[11px] text-sky-400 hover:text-sky-300 font-medium transition">
                                            <i class="fas fa-check mr-1"></i>Marquer comme lu
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-[11px] text-emerald-500/70 font-medium">
                                        <i class="fas fa-check-circle mr-1"></i>Lu
                                    </span>
                                <?php endif; ?>

                                <form method="POST" class="inline" onsubmit="return confirm('Supprimer ce déploiement ?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $deploy['id'] ?>">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                    <button type="submit" class="text-[11px] text-red-400 hover:text-red-300 font-medium transition">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="mt-6 flex justify-center">
                    <nav class="flex items-center gap-1">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?= $page - 1 ?>" class="w-9 h-9 flex items-center justify-center rounded-lg bg-white/5 border border-white/10 text-gray-400 hover:text-white hover:border-sky-500/50 transition-all text-sm">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        <?php endif; ?>

                        <?php 
                        $start = max(1, $page - 2);
                        $end = min($total_pages, $page + 2);
                        if ($start > 1): ?>
                            <a href="?page=1" class="w-9 h-9 flex items-center justify-center rounded-lg bg-white/5 border border-white/10 text-gray-400 hover:text-white hover:border-sky-500/50 transition-all text-sm">1</a>
                            <?php if ($start > 2): ?>
                                <span class="px-2 text-gray-500">...</span>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php for ($i = $start; $i <= $end; $i++): ?>
                            <a href="?page=<?= $i ?>" class="w-9 h-9 flex items-center justify-center rounded-lg border transition-all text-sm font-medium <?= $i == $page ? 'bg-sky-600 border-sky-500 text-white shadow-lg shadow-sky-500/20' : 'bg-white/5 border-white/10 text-gray-400 hover:text-white hover:border-sky-500/50' ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($end < $total_pages): ?>
                            <?php if ($end < $total_pages - 1): ?>
                                <span class="px-2 text-gray-500">...</span>
                            <?php endif; ?>
                            <a href="?page=<?= $total_pages ?>" class="w-9 h-9 flex items-center justify-center rounded-lg bg-white/5 border border-white/10 text-gray-400 hover:text-white hover:border-sky-500/50 transition-all text-sm"><?= $total_pages ?></a>
                        <?php endif; ?>

                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?= $page + 1 ?>" class="w-9 h-9 flex items-center justify-center rounded-lg bg-white/5 border border-white/10 text-gray-400 hover:text-white hover:border-sky-500/50 transition-all text-sm">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </nav>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
</body></html>
<?php
/**
 * Page d'historique des déploiements
 * Affiche les notifications de type 'system_deploy'
 */

// ═══════════════════════════════════════════
// CONFIGURATION & CONNEXION BDD
// ═══════════════════════════════════════════
require_once __DIR__ . '/../inc/db.php'; // Adaptez ce chemin selon votre structure

// Si vous n'avez pas de fichier config centralisé, décommentez ci-dessous :
/*
$db_host = 'localhost';
$db_name = 's43_orinheberge';
$db_user = 'root'; // ou votre user BDD
$db_pass = '1504';

try {
    $pdo = new PDO(
        "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}
*/

// ═══════════════════════════════════════════
// SÉCURITÉ (À adapter selon votre système d'auth)
// ═══════════════════════════════════════════
session_start();

 //Exemple : vérifier que l'utilisateur est admin
 if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: /login.php');
    exit;
     }

// ═══════════════════════════════════════════
// RÉCUPÉRATION DES DONNÉES
// ═══════════════════════════════════════════
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

try {
    // Total des déploiements
    $stmt = $pdo->query("SELECT COUNT(*) FROM notifications WHERE type = 'system_deploy'");
    $total = (int)$stmt->fetchColumn();
    $total_pages = max(1, ceil($total / $per_page));

    // Récupération des déploiements
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

} catch (PDOException $e) {
    $deployments = [];
    $total = 0;
    $total_pages = 1;
    $error = "Erreur lors du chargement : " . $e->getMessage();
}

// ═══════════════════════════════════════════
// ACTIONS (marquer comme lu, etc.)
// ═══════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'mark_read' && !empty($_POST['id'])) {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND type = 'system_deploy'");
        $stmt->execute([(int)$_POST['id']]);
        header('Location: /admin/deployments.php');
        exit;
    }
    if ($_POST['action'] === 'mark_all_read') {
        $pdo->exec("UPDATE notifications SET is_read = 1 WHERE type = 'system_deploy' AND is_read = 0");
        header('Location: /admin/deployments.php');
        exit;
    }
}

// ═══════════════════════════════════════════
// HELPER FUNCTIONS
// ═══════════════════════════════════════════
function timeAgo($datetime) {
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    
    if ($diff->y > 0) return "il y a " . $diff->y . " an" . ($diff->y > 1 ? 's' : '');
    if ($diff->m > 0) return "il y a " . $diff->m . " mois";
    if ($diff->d > 0) return "il y a " . $diff->d . " jour" . ($diff->d > 1 ? 's' : '');
    if ($diff->h > 0) return "il y a " . $diff->h . " heure" . ($diff->h > 1 ? 's' : '');
    if ($diff->i > 0) return "il y a " . $diff->i . " minute" . ($diff->i > 1 ? 's' : '');
    return "à l'instant";
}

function extractMeta($meta_json) {
    if (empty($meta_json)) return ['commit' => 'N/A', 'version' => 'N/A'];
    $data = json_decode($meta_json, true);
    return $data ?: ['commit' => 'N/A', 'version' => 'N/A'];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historique des déploiements - Admin</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #0f172a;
            color: #e2e8f0;
            min-height: 100vh;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #1e293b;
        }
        
        .header h1 {
            font-size: 1.8rem;
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .stats {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            flex: 1;
            background: #1e293b;
            padding: 1.5rem;
            border-radius: 12px;
            border: 1px solid #334155;
        }
        
        .stat-card .label {
            font-size: 0.85rem;
            color: #94a3b8;
            margin-bottom: 0.5rem;
        }
        
        .stat-card .value {
            font-size: 2rem;
            font-weight: 700;
            color: #f1f5f9;
        }
        
        .actions-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .btn {
            padding: 0.6rem 1.2rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary {
            background: #3b82f6;
            color: white;
        }
        
        .btn-primary:hover {
            background: #2563eb;
        }
        
        .btn-secondary {
            background: #334155;
            color: #e2e8f0;
        }
        
        .btn-secondary:hover {
            background: #475569;
        }
        
        .deployments-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .deployment-card {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 12px;
            padding: 1.5rem;
            transition: all 0.2s;
            position: relative;
        }
        
        .deployment-card:hover {
            border-color: #3b82f6;
            transform: translateY(-2px);
        }
        
        .deployment-card.unread {
            border-left: 4px solid #3b82f6;
        }
        
        .deployment-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }
        
        .deployment-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #f1f5f9;
            margin-bottom: 0.3rem;
        }
        
        .deployment-time {
            font-size: 0.85rem;
            color: #94a3b8;
        }
        
        .badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-right: 0.5rem;
        }
        
        .badge-version {
            background: rgba(59, 130, 246, 0.15);
            color: #60a5fa;
            border: 1px solid rgba(59, 130, 246, 0.3);
        }
        
        .badge-commit {
            background: rgba(139, 92, 246, 0.15);
            color: #a78bfa;
            border: 1px solid rgba(139, 92, 246, 0.3);
            font-family: 'Courier New', monospace;
        }
        
        .deployment-message {
            color: #cbd5e1;
            font-size: 0.95rem;
            line-height: 1.5;
            margin: 1rem 0;
            padding: 0.75rem;
            background: #0f172a;
            border-radius: 6px;
            border-left: 3px solid #334155;
        }
        
        .deployment-meta {
            display: flex;
            gap: 1.5rem;
            font-size: 0.85rem;
            color: #94a3b8;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #334155;
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }
        
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #64748b;
        }
        
        .empty-state svg {
            width: 80px;
            height: 80px;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 2rem;
        }
        
        .pagination a, .pagination span {
            padding: 0.5rem 1rem;
            border-radius: 6px;
            text-decoration: none;
            background: #1e293b;
            color: #e2e8f0;
            border: 1px solid #334155;
        }
        
        .pagination a:hover {
            background: #334155;
        }
        
        .pagination .current {
            background: #3b82f6;
            border-color: #3b82f6;
        }
        
        @media (max-width: 768px) {
            .stats { flex-direction: column; }
            .deployment-meta { flex-wrap: wrap; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚀 Historique des déploiements</h1>
            <a href="/admin/" class="btn btn-secondary">← Retour admin</a>
        </div>

        <!-- Statistiques -->
        <div class="stats">
            <div class="stat-card">
                <div class="label">Total déploiements</div>
                <div class="value"><?= $total ?></div>
            </div>
            <div class="stat-card">
                <div class="label">Dernière version</div>
                <div class="value" style="font-size: 1.5rem;">
                    <?= !empty($deployments) ? htmlspecialchars(extractMeta($deployments[0]['meta'])['version']) : 'N/A' ?>
                </div>
            </div>
            <div class="stat-card">
                <div class="label">Page actuelle</div>
                <div class="value"><?= $page ?> / <?= $total_pages ?></div>
            </div>
        </div>

        <!-- Barre d'actions -->
        <div class="actions-bar">
            <div>
                <strong><?= $total ?></strong> déploiement(s) au total
            </div>
            <form method="POST" style="display: inline;">
                <input type="hidden" name="action" value="mark_all_read">
                <button type="submit" class="btn btn-primary">✓ Tout marquer comme lu</button>
            </form>
        </div>

        <!-- Liste des déploiements -->
        <div class="deployments-list">
            <?php if (empty($deployments)): ?>
                <div class="empty-state">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                              d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
                    </svg>
                    <h3>Aucun déploiement enregistré</h3>
                    <p>Les déploiements apparaîtront ici automatiquement.</p>
                </div>
            <?php else: ?>
                <?php foreach ($deployments as $deploy): 
                    $meta = extractMeta($deploy['meta']);
                ?>
                    <div class="deployment-card <?= $deploy['is_read'] ? '' : 'unread' ?>">
                        <div class="deployment-header">
                            <div>
                                <div class="deployment-title"><?= htmlspecialchars($deploy['title']) ?></div>
                                <div class="deployment-time"><?= timeAgo($deploy['created_at']) ?></div>
                            </div>
                            <div>
                                <span class="badge badge-version"><?= htmlspecialchars($meta['version']) ?></span>
                                <span class="badge badge-commit"><?= htmlspecialchars(substr($meta['commit'], 0, 7)) ?></span>
                            </div>
                        </div>

                        <div class="deployment-message">
                            <?= nl2br(htmlspecialchars($deploy['message'])) ?>
                        </div>

                        <div class="deployment-meta">
                            <div class="meta-item">
                                📅 <?= date('d/m/Y H:i', strtotime($deploy['created_at'])) ?>
                            </div>
                            <?php if (!$deploy['is_read']): ?>
                                <form method="POST" style="margin: 0;">
                                    <input type="hidden" name="action" value="mark_read">
                                    <input type="hidden" name="id" value="<?= $deploy['id'] ?>">
                                    <button type="submit" class="btn btn-secondary" style="padding: 0.3rem 0.8rem; font-size: 0.8rem;">
                                        ✓ Marquer comme lu
                                    </button>
                                </form>
                            <?php else: ?>
                                <div class="meta-item" style="color: #22c55e;">✓ Lu</div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>">← Précédent</a>
                <?php endif; ?>
                
                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <?php if ($i == $page): ?>
                        <span class="current"><?= $i ?></span>
                    <?php else: ?>
                        <a href="?page=<?= $i ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?= $page + 1 ?>">Suivant →</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
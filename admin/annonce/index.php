<?php
session_start();
include $_SERVER['DOCUMENT_ROOT'] . '/inc/admin_sidebar.php';
include $_SERVER['DOCUMENT_ROOT'] . '/inc/db.php';
include $_SERVER['DOCUMENT_ROOT'] . '/inc/admin_layout.php';

require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/lang.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/db.php';

if (!isset($_SESSION['user_id'])) { 
    header('Location: /login/'); 
    exit(); 
}


$chk = $pdo->prepare('SELECT is_admin, pseudo, firstname, avatar FROM users WHERE id=? LIMIT 1');
$chk->execute([$_SESSION['user_id']]);
$admin = $chk->fetch();

if (!$admin || !$admin['is_admin']) { 
    http_response_code(403); 
    die('403 — Accès refusé.'); 
}

$_SESSION['username'] = !empty($admin['pseudo']) ? $admin['pseudo'] : $admin['firstname'];

// Traitement du formulaire
$message_success = '';
$message_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $message_content = trim($_POST['message'] ?? '');
    $link = trim($_POST['link'] ?? '');
    $type = trim($_POST['type'] ?? 'info');
    $target_users = $_POST['target_users'] ?? 'all'; // all, specific, role
    $specific_user_ids = $_POST['specific_user_ids'] ?? '';
    
    // Validation
    if (empty($title)) {
        $message_error = 'Le titre est obligatoire.';
    } elseif (empty($message_content)) {
        $message_error = 'Le message est obligatoire.';
    } else {
        try {
            $pdo->beginTransaction();
            
            if ($target_users === 'all') {
                // Envoyer à tous les utilisateurs
                $stmt = $pdo->prepare('SELECT id FROM users');
                $stmt->execute();
                $users = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                $insertStmt = $pdo->prepare('
                    INSERT INTO notifications (user_id, title, message, link, type, meta) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ');
                
                $meta = json_encode(['broadcast' => true, 'created_by' => $_SESSION['user_id']]);
                
                foreach ($users as $userId) {
                    $insertStmt->execute([
                        $userId,
                        $title,
                        $message_content,
                        $link ?: null,
                        $type,
                        $meta
                    ]);
                }
                
                $count = count($users);
                $message_success = "Annonce envoyée avec succès à {$count} utilisateur(s).";
                
            } elseif ($target_users === 'specific' && !empty($specific_user_ids)) {
                // Envoyer à des utilisateurs spécifiques
                $userIds = array_filter(array_map('intval', explode(',', $specific_user_ids)));
                
                if (empty($userIds)) {
                    throw new Exception('Aucun ID utilisateur valide spécifié.');
                }
                
                $insertStmt = $pdo->prepare('
                    INSERT INTO notifications (user_id, title, message, link, type, meta) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ');
                
                $meta = json_encode(['broadcast' => false, 'created_by' => $_SESSION['user_id']]);
                $count = 0;
                
                foreach ($userIds as $userId) {
                    // Vérifier que l'utilisateur existe
                    $checkUser = $pdo->prepare('SELECT id FROM users WHERE id = ?');
                    $checkUser->execute([$userId]);
                    
                    if ($checkUser->fetch()) {
                        $insertStmt->execute([
                            $userId,
                            $title,
                            $message_content,
                            $link ?: null,
                            $type,
                            $meta
                        ]);
                        $count++;
                    }
                }
                
                $message_success = "Annonce envoyée avec succès à {$count} utilisateur(s).";
                
            } else {
                throw new Exception('Sélectionnez une option de destination valide.');
            }
            
            $pdo->commit();
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $message_error = 'Erreur lors de l\'envoi de l\'annonce : ' . $e->getMessage();
        }
    }
}

// Récupérer les types disponibles
$notification_types = [
    'info' => ['label' => 'Information', 'icon' => 'fa-info-circle', 'color' => 'blue'],
    'warning' => ['label' => 'Avertissement', 'icon' => 'fa-exclamation-triangle', 'color' => 'orange'],
    'success' => ['label' => 'Succès', 'icon' => 'fa-check-circle', 'color' => 'green'],
    'error' => ['label' => 'Erreur', 'icon' => 'fa-times-circle', 'color' => 'red'],
    'announcement' => ['label' => 'Annonce', 'icon' => 'fa-bullhorn', 'color' => 'rose'],
];

$active_nav = 'annonce';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Créer une Annonce - Administration</title>
    <style>
        :root{--sidebar:240px;}
        *{box-sizing:border-box;}
        body{background:#0d0f14;color:#e2e8f0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',system-ui,sans-serif;min-height:100vh;}
        .sidebar{position:fixed;top:0;left:0;width:var(--sidebar);height:100vh;background:#111318;border-right:1px solid rgba(255,255,255,.06);display:flex;flex-direction:column;z-index:40;overflow-y:auto;}
        .sidebar-logo{padding:1.5rem 1.25rem 1rem;border-bottom:1px solid rgba(255,255,255,.05);}
        .sidebar-nav{padding:.75rem .75rem;flex:1;}
        .nav-item{display:flex;align-items:center;gap:.75rem;padding:.625rem .875rem;border-radius:.625rem;font-size:.82rem;font-weight:500;color:#6b7280;transition:all .15s;text-decoration:none;margin-bottom:.15rem;border:1px solid transparent;}
        .nav-item:hover{background:rgba(255,255,255,.04);color:#d1d5db;}
        .nav-item.active{background:rgba(244,63,94,.08);color:#f43f5e;border-color:rgba(244,63,94,.15);}
        .nav-item .icon{width:1.1rem;text-align:center;font-size:.85rem;flex-shrink:0;}
        .nav-section{font-size:.65rem;font-weight:700;letter-spacing:.1em;color:#374151;text-transform:uppercase;padding:.75rem .875rem .35rem;}
        .nav-separator{height:1px;background:rgba(255,255,255,.05);margin:.5rem .75rem;}
        .sidebar-footer{padding:.875rem 1rem;border-top:1px solid rgba(255,255,255,.05);}
        .main-content{margin-left:var(--sidebar);min-height:100vh;display:flex;flex-direction:column;}
        .topbar{background:#111318;border-bottom:1px solid rgba(255,255,255,.06);padding:.875rem 1.75rem;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:30;}
        .content{padding:1.75rem;flex:1;}
        .card{background:#161a22;border:1px solid rgba(255,255,255,.07);border-radius:.875rem;padding:1.5rem;margin-bottom:1.5rem;}
        .stat-card{background:linear-gradient(135deg,#161a22,#1a1f2a);border:1px solid rgba(255,255,255,.07);border-radius:.875rem;padding:1.25rem;}
        .badge{display:inline-flex;align-items:center;gap:.35rem;padding:.2rem .65rem;border-radius:9999px;font-size:.72rem;font-weight:600;}
        .badge-green{background:rgba(34,197,94,.1);color:#22c55e;border:1px solid rgba(34,197,94,.2);}
        .badge-orange{background:rgba(249,115,22,.1);color:#f97316;border:1px solid rgba(249,115,22,.2);}
        .badge-red{background:rgba(239,68,68,.1);color:#ef4444;border:1px solid rgba(239,68,68,.2);}
        .badge-gray{background:rgba(107,114,128,.1);color:#9ca3af;border:1px solid rgba(107,114,128,.2);}
        .badge-blue{background:rgba(56,189,248,.1);color:#38bdf8;border:1px solid rgba(56,189,248,.2);}
        .badge-rose{background:rgba(244,63,94,.1);color:#f43f5e;border:1px solid rgba(244,63,94,.2);}
        table{width:100%;border-collapse:collapse;}
        th{text-align:left;padding:.625rem 1rem;font-size:.7rem;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:#4b5563;border-bottom:1px solid rgba(255,255,255,.05);}
        td{padding:.875rem 1rem;font-size:.83rem;border-bottom:1px solid rgba(255,255,255,.04);}
        tr:last-child td{border-bottom:none;}
        tr:hover td{background:rgba(255,255,255,.015);}
        input,textarea,select{background:#1e2330 !important;border:1px solid rgba(255,255,255,.08) !important;color:#e2e8f0 !important;border-radius:.625rem;padding:.6rem .875rem;font-size:.83rem;width:100%;outline:none;transition:border-color .15s;}
        input:focus,textarea:focus,select:focus{border-color:rgba(244,63,94,.4) !important;}
        .btn-action{display:inline-flex;align-items:center;gap:.35rem;padding:.5rem 1rem;border-radius:.5rem;font-size:.8rem;font-weight:600;transition:all .15s;border:1px solid transparent;cursor:pointer;text-decoration:none;}
        .btn-red{background:rgba(239,68,68,.1);color:#ef4444;border-color:rgba(239,68,68,.2);}
        .btn-red:hover{background:rgba(239,68,68,.2);}
        .btn-orange{background:rgba(249,115,22,.1);color:#f97316;border-color:rgba(249,115,22,.2);}
        .btn-orange:hover{background:rgba(249,115,22,.2);}
        .btn-blue{background:rgba(56,189,248,.1);color:#38bdf8;border-color:rgba(56,189,248,.2);}
        .btn-blue:hover{background:rgba(56,189,248,.2);}
        .btn-sky{background:rgba(14,165,233,.1);color:#0ea5e9;border-color:rgba(14,165,233,.2);}
        .btn-sky:hover{background:rgba(14,165,233,.2);}
        .btn-green{background:rgba(34,197,94,.1);color:#22c55e;border-color:rgba(34,197,94,.2);}
        .btn-green:hover{background:rgba(34,197,94,.2);}
        .mobile-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:39;}
        
        /* Styles spécifiques pour la page */
        .form-group{margin-bottom:1.25rem;}
        .form-label{display:block;font-size:.78rem;font-weight:600;color:#9ca3af;margin-bottom:.5rem;text-transform:uppercase;letter-spacing:.05em;}
        .form-hint{font-size:.72rem;color:#6b7280;margin-top:.35rem;}
        .alert{padding:1rem 1.25rem;border-radius:.625rem;margin-bottom:1.25rem;font-size:.83rem;}
        .alert-success{background:rgba(34,197,94,.1);border:1px solid rgba(34,197,94,.2);color:#22c55e;}
        .alert-error{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.2);color:#ef4444;}
        .type-selector{display:grid;grid-template-columns:repeat(auto-fit, minmax(150px, 1fr));gap:.75rem;margin-top:.5rem;}
        .type-option{padding:.75rem;border:2px solid rgba(255,255,255,.08);border-radius:.625rem;cursor:pointer;transition:all .15s;text-align:center;}
        .type-option:hover{border-color:rgba(255,255,255,.15);background:rgba(255,255,255,.02);}
        .type-option.selected{border-color:currentColor;}
        .type-option input[type="radio"]{display:none;}
        .page-header{margin-bottom:1.5rem;}
        .page-title{font-size:1.5rem;font-weight:700;margin-bottom:.5rem;}
        .page-subtitle{font-size:.875rem;color:#6b7280;}
        
        @media(max-width:768px){
            .sidebar{transform:translateX(-100%);transition:transform .25s;}
            .sidebar.open{transform:translateX(0);}
            .mobile-overlay.open{display:block;}
            .main-content{margin-left:0;}
            .topbar,.content{padding:.875rem 1rem;}
            .type-selector{grid-template-columns:1fr;}
        }
    </style>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/inc/admin_sidebar.php'; ?>
    
    <div class="mobile-overlay" id="overlay" onclick="toggleSidebar()"></div>
    
    <div class="main-content">
        <div class="topbar">
            <button class="btn-action btn-blue" onclick="toggleSidebar()" style="display:none;" id="menu-toggle">
                <i class="fas fa-bars"></i>
            </button>
            <div style="font-weight:600;">Créer une Annonce</div>
            <div style="display:flex;align-items:center;gap:.75rem;">
                <span style="font-size:.83rem;color:#9ca3af;"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
            </div>
        </div>
        
        <div class="content">
            <div class="page-header">
                <h1 class="page-title"><i class="fas fa-bullhorn" style="color:#f43f5e;margin-right:.5rem;"></i>Créer une Nouvelle Annonce</h1>
                <p class="page-subtitle">Envoyez une notification à un ou plusieurs utilisateurs</p>
            </div>
            
            <?php if ($message_success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle" style="margin-right:.5rem;"></i>
                    <?php echo htmlspecialchars($message_success); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($message_error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle" style="margin-right:.5rem;"></i>
                    <?php echo htmlspecialchars($message_error); ?>
                </div>
            <?php endif; ?>
            
            <div class="card">
                <form method="POST" action="">
                    <!-- Titre -->
                    <div class="form-group">
                        <label class="form-label" for="title">
                            <i class="fas fa-heading" style="margin-right:.35rem;"></i>Titre de l'annonce *
                        </label>
                        <input 
                            type="text" 
                            id="title" 
                            name="title" 
                            placeholder="Ex: Maintenance prévue ce weekend"
                            required
                            maxlength="255"
                            value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>"
                        >
                    </div>
                    
                    <!-- Message -->
                    <div class="form-group">
                        <label class="form-label" for="message">
                            <i class="fas fa-comment-alt" style="margin-right:.35rem;"></i>Message *
                        </label>
                        <textarea 
                            id="message" 
                            name="message" 
                            rows="6" 
                            placeholder="Détails de l'annonce..."
                            required
                        ><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
                        <div class="form-hint">Le message peut contenir du texte simple. Évitez le HTML.</div>
                    </div>
                    
                    <!-- Lien optionnel -->
                    <div class="form-group">
                        <label class="form-label" for="link">
                            <i class="fas fa-link" style="margin-right:.35rem;"></i>Lien (optionnel)
                        </label>
                        <input 
                            type="url" 
                            id="link" 
                            name="link" 
                            placeholder="https://heberge.orinstone.deepstone.fr/"
                            value="<?php echo htmlspecialchars($_POST['link'] ?? ''); ?>"
                        >
                        <div class="form-hint">Les utilisateurs pourront cliquer sur ce lien depuis la notification</div>
                    </div>
                    
                    <!-- Type de notification -->
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-tag" style="margin-right:.35rem;"></i>Type d'annonce
                        </label>
                        <div class="type-selector">
                            <?php foreach ($notification_types as $key => $type): ?>
                                <label class="type-option badge-<?php echo $type['color']; ?> <?php echo ($_POST['type'] ?? 'announcement') === $key ? 'selected' : ''; ?>">
                                    <input 
                                        type="radio" 
                                        name="type" 
                                        value="<?php echo $key; ?>" 
                                        <?php echo ($_POST['type'] ?? 'announcement') === $key ? 'checked' : ''; ?>
                                    >
                                    <i class="fas <?php echo $type['icon']; ?>" style="font-size:1.25rem;margin-bottom:.35rem;display:block;"></i>
                                    <div style="font-size:.78rem;font-weight:600;"><?php echo $type['label']; ?></div>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Destinataires -->
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-users" style="margin-right:.35rem;"></i>Destinataires
                        </label>
                        <select name="target_users" id="target_users" onchange="toggleSpecificUsers()">
                            <option value="all" <?php echo ($_POST['target_users'] ?? 'all') === 'all' ? 'selected' : ''; ?>>
                                Tous les utilisateurs
                            </option>
                            <option value="specific" <?php echo ($_POST['target_users'] ?? '') === 'specific' ? 'selected' : ''; ?>>
                                Utilisateurs spécifiques
                            </option>
                        </select>
                    </div>
                    
                    <!-- IDs utilisateurs spécifiques (caché par défaut) -->
                    <div class="form-group" id="specific_users_group" style="<?php echo ($_POST['target_users'] ?? '') !== 'specific' ? 'display:none;' : ''; ?>">
                        <label class="form-label" for="specific_user_ids">
                            <i class="fas fa-id-badge" style="margin-right:.35rem;"></i>IDs des utilisateurs
                        </label>
                        <input 
                            type="text" 
                            id="specific_user_ids" 
                            name="specific_user_ids" 
                            placeholder="Ex: 1, 5, 12, 45"
                            value="<?php echo htmlspecialchars($_POST['specific_user_ids'] ?? ''); ?>"
                        >
                        <div class="form-hint">Séparez les IDs par des virgules. Exemple: 1, 5, 12</div>
                    </div>
                    
                    <!-- Boutons -->
                    <div style="display:flex;gap:.75rem;margin-top:1.5rem;">
                        <button type="submit" class="btn-action btn-green" style="flex:1;justify-content:center;padding:.75rem;">
                            <i class="fas fa-paper-plane"></i>
                            Envoyer l'annonce
                        </button>
                        <a href="/admin/" class="btn-action btn-blue" style="justify-content:center;padding:.75rem;">
                            <i class="fas fa-arrow-left"></i>
                            Retour
                        </a>
                    </div>
                </form>
            </div>
            
            <!-- Historique des dernières annonces -->
            <div class="card">
                <h2 style="font-size:1.1rem;font-weight:700;margin-bottom:1rem;">
                    <i class="fas fa-history" style="color:#38bdf8;margin-right:.5rem;"></i>
                    Dernières annonces envoyées
                </h2>
                
                <?php
                // Récupérer les 10 dernières annonces broadcast
                $recentStmt = $pdo->prepare('
                    SELECT n.*, u.pseudo, u.firstname 
                    FROM notifications n
                    LEFT JOIN users u ON n.meta->>"$.created_by" = u.id
                    WHERE JSON_EXTRACT(n.meta, "$.broadcast") = true
                    ORDER BY n.created_at DESC
                    LIMIT 10
                ');
                $recentStmt->execute();
                $recentAnnouncements = $recentStmt->fetchAll();
                
                if (count($recentAnnouncements) > 0):
                ?>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Titre</th>
                            <th>Type</th>
                            <th>Créé par</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentAnnouncements as $ann): ?>
                        <tr>
                            <td><?php echo date('d/m/Y H:i', strtotime($ann['created_at'])); ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($ann['title']); ?></strong><br>
                                <small style="color:#6b7280;"><?php echo htmlspecialchars(substr($ann['message'], 0, 80)); ?>...</small>
                            </td>
                            <td>
                                <?php 
                                $typeInfo = $notification_types[$ann['type']] ?? $notification_types['info'];
                                echo '<span class="badge badge-' . $typeInfo['color'] . '">';
                                echo '<i class="fas ' . $typeInfo['icon'] . '" style="margin-right:.25rem;"></i>';
                                echo $typeInfo['label'];
                                echo '</span>';
                                ?>
                            </td>
                            <td><?php echo htmlspecialchars($ann['pseudo'] ?? $ann['firstname'] ?? 'Inconnu'); ?></td>
                            <td>
                                <?php if ($ann['link']): ?>
                                    <a href="<?php echo htmlspecialchars($ann['link']); ?>" target="_blank" class="btn-action btn-blue" style="padding:.25rem .5rem;font-size:.7rem;">
                                        <i class="fas fa-external-link-alt"></i> Lien
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div style="text-align:center;padding:2rem;color:#6b7280;">
                    <i class="fas fa-inbox" style="font-size:2rem;margin-bottom:.75rem;display:block;"></i>
                    Aucune annonce récente
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        function toggleSidebar(){
            document.getElementById('sidebar').classList.toggle('open');
            document.getElementById('overlay').classList.toggle('open');
        }
        
        function toggleSpecificUsers(){
            const select = document.getElementById('target_users');
            const group = document.getElementById('specific_users_group');
            if (select.value === 'specific') {
                group.style.display = 'block';
            } else {
                group.style.display = 'none';
            }
        }
        
        // Gestion de la sélection visuelle des types
        document.querySelectorAll('.type-option').forEach(option => {
            option.addEventListener('click', function() {
                document.querySelectorAll('.type-option').forEach(opt => opt.classList.remove('selected'));
                this.classList.add('selected');
            });
        });
        
        // Afficher le bouton menu sur mobile
        if (window.innerWidth <= 768) {
            document.getElementById('menu-toggle').style.display = 'inline-flex';
        }
        
        window.addEventListener('resize', function() {
            if (window.innerWidth <= 768) {
                document.getElementById('menu-toggle').style.display = 'inline-flex';
            } else {
                document.getElementById('menu-toggle').style.display = 'none';
                document.getElementById('sidebar').classList.remove('open');
                document.getElementById('overlay').classList.remove('open');
            }
        });
    </script>
</body>
</html>
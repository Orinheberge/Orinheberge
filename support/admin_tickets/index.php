<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/lang.php';

try {
    $pdo = new PDO('mysql:host=localhost;dbname=s43_orinheberge;charset=utf8mb4','root','1504',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
} catch (PDOException $e) { die('Erreur BDD.'); }

// Vérifier is_admin depuis la BDD
if (!isset($_SESSION['user_id'])) { header('Location: /login/'); exit(); }
$chk = $pdo->prepare('SELECT is_admin, pseudo, firstname, avatar FROM users WHERE id=? LIMIT 1');
$chk->execute([$_SESSION['user_id']]);
$me = $chk->fetch();
if (!$me || !$me['is_admin']) { http_response_code(403); die('<meta charset="UTF-8"><style>body{background:#0d0f14;color:#e2e8f0;font-family:system-ui;display:flex;align-items:center;justify-content:center;min-height:100vh;}</style><div style="text-align:center"><div style="font-size:4rem;font-weight:900;color:#f43f5e">403</div><p>Accès refusé.</p><a href="/" style="color:#38bdf8">Retour</a></div>'); }

$_SESSION['username'] = !empty($me['pseudo']) ? $me['pseudo'] : $me['firstname'];
$_SESSION['avatar']   = $me['avatar'];

$flash = '';
$view = $_GET['view'] ?? 'list';
$ticket_id = (int)($_GET['id'] ?? 0);
$filter = $_GET['filter'] ?? 'all';

// ── Actions POST ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $tid = (int)($_POST['ticket_id'] ?? 0);

    if ($action === 'reply' && $tid) {
        $reply  = trim($_POST['reply'] ?? '');
        $status = $_POST['status'] ?? 'Traité';
        if (!empty($reply)) {
            $pdo->prepare('UPDATE support_tickets SET reply=?, status=? WHERE id=?')->execute([$reply, $status, $tid]);
            $flash = 'ok:Réponse envoyée.';
        } else {
            $flash = 'err:La réponse ne peut pas être vide.';
        }
        if ($view === 'detail') {
            header("Location: /support/admin_tickets/?view=detail&id=$tid"); exit();
        }
        header('Location: /support/admin_tickets/?view=list'); exit();
    }

    if ($action === 'close' && $tid) {
        $pdo->prepare("UPDATE support_tickets SET status='Fermé' WHERE id=?")->execute([$tid]);
        header('Location: /support/admin_tickets/?view=list'); exit();
    }

    if ($action === 'reopen' && $tid) {
        $pdo->prepare("UPDATE support_tickets SET status='En attente' WHERE id=?")->execute([$tid]);
        header("Location: /support/admin_tickets/?view=detail&id=$tid"); exit();
    }

    if ($action === 'delete' && $tid) {
        $pdo->prepare("DELETE FROM support_tickets WHERE id=?")->execute([$tid]);
        header('Location: /support/admin_tickets/?view=list'); exit();
    }

    if ($action === 'set_priority' && $tid) {
        $prio = $_POST['priority'] ?? 'normale';
        $pdo->prepare("UPDATE support_tickets SET priority=? WHERE id=?")->execute([$prio, $tid]);
        header("Location: /support/admin_tickets/?view=detail&id=$tid"); exit();
    }
}

// ── Données ────────────────────────────────────────────────────────────────────
$all = $pdo->query('SELECT st.*, u.pseudo, u.firstname, u.email, u.avatar FROM support_tickets st LEFT JOIN users u ON u.id=st.user_id ORDER BY st.updated_at DESC, st.created_at DESC')->fetchAll();

$counts = ['all'=>count($all),'open'=>0,'replied'=>0,'closed'=>0];
foreach ($all as $t) {
    if ($t['status']==='Fermé') $counts['closed']++;
    elseif ($t['status']==='Traité') $counts['replied']++;
    else $counts['open']++;
}

$displayed = $filter==='all' ? $all : array_filter($all, fn($t) => match($filter) {
    'open'    => !in_array($t['status'],['Traité','Fermé']),
    'replied' => $t['status']==='Traité',
    'closed'  => $t['status']==='Fermé',
    default   => true,
});

$current = null;
if ($view==='detail' && $ticket_id) {
    $ct = $pdo->prepare('SELECT st.*, u.pseudo, u.firstname, u.email, u.avatar FROM support_tickets st LEFT JOIN users u ON u.id=st.user_id WHERE st.id=? LIMIT 1');
    $ct->execute([$ticket_id]);
    $current = $ct->fetch();
    if (!$current) { header('Location: /support/admin_tickets/'); exit(); }
}

function tbadge(string $s): array {
    return match($s){
        'Traité'  =>['badge-green','Répondu'],
        'Fermé'   =>['badge-gray','Fermé'],
        'Ouvert'  =>['badge-orange','Ouvert'],
        default   =>['badge-blue','En attente'],
    };
}
function pbadge(string $p): array {
    return match($p){
        'haute'  =>['badge-red','Haute'],
        default  =>['badge-blue','Normale'],
    };
}
function tcolor(string $t): string {
    return match($t){'Bug'=>'text-red-400','Réclamation'=>'text-orange-400',default=>'text-sky-400'};
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Support — OrinHeberge</title>
    <link rel="icon" href="/favicon.ico">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
        .card{background:#161a22;border:1px solid rgba(255,255,255,.07);border-radius:.875rem;}
        .badge{display:inline-flex;align-items:center;gap:.3rem;padding:.2rem .65rem;border-radius:9999px;font-size:.72rem;font-weight:600;}
        .badge-green{background:rgba(34,197,94,.1);color:#22c55e;border:1px solid rgba(34,197,94,.2);}
        .badge-orange{background:rgba(249,115,22,.1);color:#f97316;border:1px solid rgba(249,115,22,.2);}
        .badge-red{background:rgba(239,68,68,.1);color:#ef4444;border:1px solid rgba(239,68,68,.2);}
        .badge-gray{background:rgba(107,114,128,.1);color:#9ca3af;border:1px solid rgba(107,114,128,.2);}
        .badge-blue{background:rgba(56,189,248,.1);color:#38bdf8;border:1px solid rgba(56,189,248,.2);}
        .badge-rose{background:rgba(244,63,94,.1);color:#f43f5e;border:1px solid rgba(244,63,94,.2);}
        .ticket-row{display:flex;align-items:center;gap:1rem;padding:1rem 1.25rem;border-bottom:1px solid rgba(255,255,255,.04);cursor:pointer;transition:background .15s;text-decoration:none;}
        .ticket-row:last-child{border-bottom:none;}
        .ticket-row:hover{background:rgba(255,255,255,.02);}
        .ticket-dot{width:.5rem;height:.5rem;border-radius:50%;flex-shrink:0;}
        input,textarea,select{background:#1e2330;border:1px solid rgba(255,255,255,.08);color:#e2e8f0;border-radius:.625rem;padding:.6rem .875rem;font-size:.83rem;width:100%;outline:none;transition:border-color .15s;}
        input:focus,textarea:focus,select:focus{border-color:rgba(244,63,94,.4);}
        .filter-btn{padding:.35rem .875rem;border-radius:9999px;font-size:.75rem;font-weight:600;border:1px solid rgba(255,255,255,.07);color:#6b7280;cursor:pointer;transition:all .15s;text-decoration:none;white-space:nowrap;}
        .filter-btn:hover{background:rgba(255,255,255,.05);color:#d1d5db;}
        .filter-btn.active{background:rgba(244,63,94,.08);border-color:rgba(244,63,94,.25);color:#f43f5e;}
        .btn-act{display:inline-flex;align-items:center;gap:.35rem;padding:.3rem .75rem;border-radius:.5rem;font-size:.75rem;font-weight:600;transition:all .15s;border:1px solid transparent;cursor:pointer;}
        .btn-rose{background:rgba(244,63,94,.1);color:#f43f5e;border-color:rgba(244,63,94,.2);}
        .btn-rose:hover{background:rgba(244,63,94,.2);}
        .btn-gray{background:rgba(107,114,128,.1);color:#9ca3af;border-color:rgba(107,114,128,.2);}
        .btn-gray:hover{background:rgba(107,114,128,.2);}
        .btn-red{background:rgba(239,68,68,.1);color:#ef4444;border-color:rgba(239,68,68,.2);}
        .btn-red:hover{background:rgba(239,68,68,.2);}
        .mobile-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:39;}
        @media(max-width:768px){
            .sidebar{transform:translateX(-100%);transition:transform .25s;}
            .sidebar.open{transform:translateX(0);}
            .mobile-overlay.open{display:block;}
            .main-content{margin-left:0;}
            .topbar,.content{padding:.875rem 1rem;}
        }
    </style>
    <script>
        function toggleSidebar(){document.getElementById('sidebar').classList.toggle('open');document.getElementById('overlay').classList.toggle('open');}
    </script>
</head>
<body>
<div id="overlay" class="mobile-overlay" onclick="toggleSidebar()"></div>

<!-- ══ SIDEBAR ══ -->
<aside id="sidebar" class="sidebar">
    <div class="sidebar-logo">
        <a href="/" class="flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-lg bg-rose-500/20 flex items-center justify-center">
                <i class="fas fa-shield-alt text-rose-400 text-sm"></i>
            </div>
            <div>
                <span class="font-black text-white text-sm tracking-tight block leading-tight">OrinHeberge</span>
                <span class="text-[10px] text-rose-400 font-semibold">Admin Support</span>
            </div>
        </a>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-section">Tickets</div>
        <a href="/support/admin_tickets/?view=list" class="nav-item <?php echo ($view==='list'||$view==='detail')?'active':''; ?>">
            <i class="fas fa-ticket-alt icon"></i> Tous les tickets
            <?php if ($counts['open']>0): ?><span class="ml-auto text-[10px] bg-orange-500/15 text-orange-400 border border-orange-500/20 px-1.5 py-0.5 rounded-full font-bold"><?php echo $counts['open']; ?></span><?php endif; ?>
        </a>
        <div class="nav-separator"></div>
        <div class="nav-section">Filtres rapides</div>
        <a href="?filter=open" class="nav-item <?php echo $filter==='open'?'active':''; ?>"><i class="fas fa-clock icon text-orange-400"></i> En attente <span class="ml-auto text-[10px] text-gray-600"><?php echo $counts['open']; ?></span></a>
        <a href="?filter=replied" class="nav-item <?php echo $filter==='replied'?'active':''; ?>"><i class="fas fa-check icon text-green-400"></i> Répondus <span class="ml-auto text-[10px] text-gray-600"><?php echo $counts['replied']; ?></span></a>
        <a href="?filter=closed" class="nav-item <?php echo $filter==='closed'?'active':''; ?>"><i class="fas fa-lock icon text-gray-500"></i> Fermés <span class="ml-auto text-[10px] text-gray-600"><?php echo $counts['closed']; ?></span></a>
        <div class="nav-separator"></div>
        <div class="nav-section">Admin</div>
        <a href="/admin/" class="nav-item"><i class="fas fa-chart-bar icon"></i> Admin Panel</a>
        <a href="/admin/?view=clients" class="nav-item"><i class="fas fa-users icon"></i> Clients</a>
        <a href="/admin/?view=settings" class="nav-item"><i class="fas fa-sliders-h icon"></i> Paramètres</a>
        <div class="nav-separator"></div>
        <a href="/client/" class="nav-item"><i class="fas fa-home icon"></i> Dashboard</a>
        <a href="/support/" class="nav-item"><i class="fas fa-headset icon"></i> Vue client</a>
    </nav>
    <div class="sidebar-footer">
        <a href="/profil/" class="flex items-center gap-2.5 mb-2">
            <?php if (!empty($me['avatar']) && file_exists($_SERVER['DOCUMENT_ROOT'].'/'.$me['avatar'])): ?>
                <img src="/<?php echo htmlspecialchars($me['avatar']); ?>" class="w-8 h-8 rounded-full object-cover border border-white/10">
            <?php else: ?>
                <div class="w-8 h-8 rounded-full bg-rose-500/20 flex items-center justify-center text-rose-400 text-xs font-bold border border-rose-500/20"><?php echo strtoupper(substr($me['pseudo']??$me['firstname']??'A',0,1)); ?></div>
            <?php endif; ?>
            <div class="flex-1 min-w-0">
                <div class="text-xs font-semibold text-white truncate"><?php echo htmlspecialchars($me['pseudo']??$me['firstname']); ?></div>
                <div class="text-[10px] text-rose-400 font-semibold">Administrateur</div>
            </div>
        </a>
        <a href="/logout/" class="nav-item" style="color:#ef4444;"><i class="fas fa-sign-out-alt icon"></i> Déconnexion</a>
    </div>
</aside>

<!-- ══ MAIN ══ -->
<div class="main-content">
    <div class="topbar">
        <div class="flex items-center gap-3">
            <button onclick="toggleSidebar()" class="md:hidden text-gray-400 hover:text-white text-lg w-8"><i class="fas fa-bars"></i></button>
            <div>
                <div class="text-sm font-bold text-white"><?php echo $view==='detail'&&$current?htmlspecialchars($current['subject']):'Gestion des tickets'; ?></div>
                <div class="text-xs text-gray-500"><?php echo $counts['all']; ?> ticket(s) — <?php echo $counts['open']; ?> en attente</div>
            </div>
        </div>
        <a href="/client/" class="hidden sm:flex items-center gap-2 text-gray-500 hover:text-white text-xs font-semibold px-3 py-1.5 rounded-lg hover:bg-white/5 transition">
            <i class="fas fa-arrow-left text-[10px]"></i> Espace client
        </a>
    </div>

    <div class="content">
        <?php if ($flash): [$ft,$fm]=explode(':',$flash,2); ?>
        <div class="mb-5 p-3.5 rounded-xl text-sm font-medium flex items-center gap-2" style="<?php echo $ft==='ok'?'background:rgba(34,197,94,.08);border:1px solid rgba(34,197,94,.2);color:#22c55e;':'background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.2);color:#ef4444;'; ?>">
            <i class="fas fa-<?php echo $ft==='ok'?'check-circle':'exclamation-circle'; ?>"></i> <?php echo htmlspecialchars($fm); ?>
        </div>
        <?php endif; ?>

        <?php if ($view==='detail' && $current): ?>
        <!-- ── VUE DÉTAIL ── -->
        <?php [$sb,$sl]=tbadge($current['status']); [$pb,$pl]=pbadge($current['priority']??'normale');
              $author = !empty($current['pseudo']) ? $current['pseudo'] : ($current['firstname']??'Inconnu'); ?>
        <div class="max-w-3xl">
            <!-- Header -->
            <div class="flex items-start gap-3 mb-5">
                <a href="/support/admin_tickets/" class="text-gray-500 hover:text-white transition text-sm mt-1"><i class="fas fa-arrow-left"></i></a>
                <div class="flex-1">
                    <h1 class="text-base font-bold text-white mb-1"><?php echo htmlspecialchars($current['subject']); ?></h1>
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="badge <?php echo $sb; ?>"><?php echo $sl; ?></span>
                        <span class="badge <?php echo $pb; ?>"><?php echo $pl; ?></span>
                        <span class="text-[11px] text-gray-600">Ticket #<?php echo $current['id']; ?></span>
                        <span class="text-[11px] text-gray-600">•</span>
                        <span class="text-[11px] <?php echo tcolor($current['ticket_type']); ?>"><?php echo htmlspecialchars($current['ticket_type']); ?></span>
                        <span class="text-[11px] text-gray-600">•</span>
                        <span class="text-[11px] text-gray-600"><?php echo date('d/m/Y H:i', strtotime($current['created_at'])); ?></span>
                    </div>
                </div>
                <!-- Actions rapides -->
                <div class="flex gap-2 shrink-0">
                    <?php if ($current['status']==='Fermé'): ?>
                    <form method="POST"><input type="hidden" name="action" value="reopen"><input type="hidden" name="ticket_id" value="<?php echo $current['id']; ?>">
                        <button class="btn-act btn-gray"><i class="fas fa-lock-open"></i> Réouvrir</button>
                    </form>
                    <?php else: ?>
                    <form method="POST" onsubmit="return confirm('Fermer ce ticket ?')"><input type="hidden" name="action" value="close"><input type="hidden" name="ticket_id" value="<?php echo $current['id']; ?>">
                        <button class="btn-act btn-gray"><i class="fas fa-lock"></i> Fermer</button>
                    </form>
                    <?php endif; ?>
                    <form method="POST" onsubmit="return confirm('Supprimer définitivement ce ticket ?')"><input type="hidden" name="action" value="delete"><input type="hidden" name="ticket_id" value="<?php echo $current['id']; ?>">
                        <button class="btn-act btn-red"><i class="fas fa-trash"></i></button>
                    </form>
                </div>
            </div>

            <!-- Info client -->
            <div class="card p-4 mb-4 flex items-center gap-3">
                <div class="w-9 h-9 rounded-full bg-sky-500/15 flex items-center justify-center text-sky-400 text-sm font-bold shrink-0"><?php echo strtoupper(substr($author,0,1)); ?></div>
                <div class="flex-1 min-w-0">
                    <div class="text-xs font-semibold text-white"><?php echo htmlspecialchars($author); ?></div>
                    <div class="text-[11px] text-gray-500"><?php echo htmlspecialchars($current['email']??'—'); ?></div>
                </div>
                <form method="POST" class="flex items-center gap-2">
                    <input type="hidden" name="action" value="set_priority">
                    <input type="hidden" name="ticket_id" value="<?php echo $current['id']; ?>">
                    <select name="priority" style="width:auto;padding:.3rem .6rem;font-size:.72rem;" onchange="this.form.submit()">
                        <option value="normale" <?php echo ($current['priority']??'normale')==='normale'?'selected':''; ?> style="background:#1e2330">Priorité normale</option>
                        <option value="haute" <?php echo ($current['priority']??'')==='haute'?'selected':''; ?> style="background:#1e2330">Priorité haute</option>
                    </select>
                </form>
            </div>

            <!-- Message client -->
            <div class="card mb-4">
                <div class="px-5 py-3 border-b border-white/[0.05] flex items-center gap-2">
                    <div class="w-6 h-6 rounded-full bg-sky-500/15 flex items-center justify-center text-sky-400 text-[10px] font-bold shrink-0"><?php echo strtoupper(substr($author,0,1)); ?></div>
                    <span class="text-xs font-semibold text-white"><?php echo htmlspecialchars($author); ?></span>
                    <span class="text-[11px] text-gray-600 ml-1"><?php echo date('d/m/Y H:i', strtotime($current['created_at'])); ?></span>
                </div>
                <div class="p-5"><p class="text-sm text-gray-300 whitespace-pre-wrap leading-relaxed"><?php echo htmlspecialchars($current['message']); ?></p></div>
            </div>

            <!-- Réponse existante -->
            <?php if (!empty($current['reply'])): ?>
            <div class="card mb-4" style="border-color:rgba(34,197,94,.15);background:rgba(34,197,94,.03);">
                <div class="px-5 py-3 border-b flex items-center gap-2" style="border-color:rgba(34,197,94,.1);">
                    <div class="w-6 h-6 rounded-full bg-green-500/20 flex items-center justify-center shrink-0"><i class="fas fa-shield-alt text-green-400 text-[10px]"></i></div>
                    <span class="text-xs font-semibold text-green-300">OrinHeberge Staff</span>
                    <span class="text-[11px] text-gray-600 ml-1"><?php echo $current['updated_at']?date('d/m/Y H:i',strtotime($current['updated_at'])):''; ?></span>
                    <span class="ml-auto badge badge-green">Envoyé</span>
                </div>
                <div class="p-5"><p class="text-sm text-gray-200 whitespace-pre-wrap leading-relaxed"><?php echo htmlspecialchars($current['reply']); ?></p></div>
            </div>
            <?php endif; ?>

            <!-- Formulaire de réponse -->
            <?php if ($current['status']!=='Fermé'): ?>
            <div class="card p-5">
                <h3 class="text-xs font-bold text-white mb-4 flex items-center gap-2"><i class="fas fa-reply text-rose-400 text-xs"></i> <?php echo empty($current['reply'])?'Répondre':'Modifier la réponse'; ?></h3>
                <form method="POST" class="space-y-3">
                    <input type="hidden" name="action" value="reply">
                    <input type="hidden" name="ticket_id" value="<?php echo $current['id']; ?>">
                    <textarea name="reply" rows="5" placeholder="Rédigez votre réponse…" style="resize:vertical"><?php echo htmlspecialchars($current['reply']??''); ?></textarea>
                    <div class="flex items-center gap-3">
                        <div class="flex items-center gap-2">
                            <label class="text-xs text-gray-500 font-semibold">Statut :</label>
                            <select name="status" style="width:auto;padding:.35rem .75rem;font-size:.78rem;">
                                <option value="Traité" <?php echo $current['status']==='Traité'?'selected':''; ?> style="background:#1e2330">Traité</option>
                                <option value="Ouvert" <?php echo $current['status']==='Ouvert'?'selected':''; ?> style="background:#1e2330">Ouvert / En cours</option>
                                <option value="En attente" <?php echo $current['status']==='En attente'?'selected':''; ?> style="background:#1e2330">En attente</option>
                            </select>
                        </div>
                        <button type="submit" class="ml-auto flex items-center gap-2 bg-rose-600 hover:bg-rose-500 text-white font-bold px-5 py-2.5 rounded-lg text-sm transition">
                            <i class="fas fa-paper-plane text-xs"></i> Envoyer la réponse
                        </button>
                    </div>
                </form>
            </div>
            <?php else: ?>
            <div class="text-center py-4 text-xs text-gray-600"><i class="fas fa-lock mr-2"></i>Ticket fermé.</div>
            <?php endif; ?>
        </div>

        <?php else: ?>
        <!-- ── LISTE TICKETS ── -->
        <!-- Stats rapides -->
        <div class="grid grid-cols-3 gap-4 mb-5">
            <div style="background:linear-gradient(135deg,#161a22,#1a1f2a);border:1px solid rgba(255,255,255,.07);border-radius:.875rem;padding:1rem 1.25rem;">
                <div class="text-[10px] text-gray-500 font-medium mb-1">En attente</div>
                <div class="text-xl font-black text-orange-400"><?php echo $counts['open']; ?></div>
            </div>
            <div style="background:linear-gradient(135deg,#161a22,#1a1f2a);border:1px solid rgba(255,255,255,.07);border-radius:.875rem;padding:1rem 1.25rem;">
                <div class="text-[10px] text-gray-500 font-medium mb-1">Répondus</div>
                <div class="text-xl font-black text-green-400"><?php echo $counts['replied']; ?></div>
            </div>
            <div style="background:linear-gradient(135deg,#161a22,#1a1f2a);border:1px solid rgba(255,255,255,.07);border-radius:.875rem;padding:1rem 1.25rem;">
                <div class="text-[10px] text-gray-500 font-medium mb-1">Fermés</div>
                <div class="text-xl font-black text-gray-400"><?php echo $counts['closed']; ?></div>
            </div>
        </div>

        <!-- Filtres -->
        <div class="flex gap-2 flex-wrap mb-4">
            <a href="?filter=all" class="filter-btn <?php echo $filter==='all'?'active':''; ?>">Tous <span class="opacity-60 ml-1"><?php echo $counts['all']; ?></span></a>
            <a href="?filter=open" class="filter-btn <?php echo $filter==='open'?'active':''; ?>">En attente <span class="opacity-60 ml-1"><?php echo $counts['open']; ?></span></a>
            <a href="?filter=replied" class="filter-btn <?php echo $filter==='replied'?'active':''; ?>">Répondus <span class="opacity-60 ml-1"><?php echo $counts['replied']; ?></span></a>
            <a href="?filter=closed" class="filter-btn <?php echo $filter==='closed'?'active':''; ?>">Fermés <span class="opacity-60 ml-1"><?php echo $counts['closed']; ?></span></a>
        </div>

        <?php if (empty($displayed)): ?>
        <div class="card p-12 text-center">
            <i class="fas fa-check-circle text-green-400 text-3xl mb-3 block"></i>
            <div class="text-sm font-semibold text-gray-300 mb-1">Aucun ticket dans cette catégorie</div>
        </div>
        <?php else: ?>
        <div class="card overflow-hidden">
            <div class="px-5 py-3.5 border-b border-white/[0.05]">
                <span class="text-xs font-bold text-white"><?php echo count($displayed); ?> ticket(s)</span>
            </div>
            <?php foreach ($displayed as $t):
                [$sb,$sl]=tbadge($t['status']); [$pb,$pl]=pbadge($t['priority']??'normale');
                $author = !empty($t['pseudo'])?$t['pseudo']:($t['firstname']??'?');
            ?>
            <a href="/support/admin_tickets/?view=detail&id=<?php echo $t['id']; ?>" class="ticket-row">
                <div class="ticket-dot <?php echo $t['status']==='Fermé'?'bg-gray-600':($t['status']==='Traité'?'bg-green-500':'bg-orange-500'); ?>"></div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 mb-0.5">
                        <span class="text-sm font-semibold text-white truncate"><?php echo htmlspecialchars($t['subject']); ?></span>
                        <?php if (($t['priority']??'')==='haute'): ?><span class="badge badge-red" style="font-size:.65rem;padding:.1rem .5rem">Haute</span><?php endif; ?>
                    </div>
                    <div class="text-xs text-gray-500 flex items-center gap-2">
                        <span class="font-medium text-gray-400"><?php echo htmlspecialchars($author); ?></span>
                        <span>•</span>
                        <span class="<?php echo tcolor($t['ticket_type']); ?>"><?php echo htmlspecialchars($t['ticket_type']); ?></span>
                        <span>•</span>
                        <span>#<?php echo $t['id']; ?></span>
                        <span>•</span>
                        <span><?php echo date('d/m/Y H:i', strtotime($t['created_at'])); ?></span>
                    </div>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <span class="badge <?php echo $sb; ?>"><?php echo $sl; ?></span>
                    <i class="fas fa-chevron-right text-gray-600 text-xs"></i>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
</body>
</html>

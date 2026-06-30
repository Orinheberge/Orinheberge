<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/lang.php';
if (!isset($_SESSION['user_id'])) { header('Location: /login/'); exit(); }

try {
    $pdo = new PDO('mysql:host=localhost;dbname=s43_orinheberge;charset=utf8mb4', 'root', '1504', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) { die('Erreur BDD.'); }

// Charger infos user
$u = $pdo->prepare('SELECT pseudo, firstname, avatar, is_admin FROM users WHERE id=? LIMIT 1');
$u->execute([$_SESSION['user_id']]);
$me = $u->fetch();
$_SESSION['username'] = !empty($me['pseudo']) ? $me['pseudo'] : $me['firstname'];
$_SESSION['avatar']   = $me['avatar'];
$is_admin = (bool)($me['is_admin'] ?? false);

$flash = '';
$view = $_GET['view'] ?? 'list'; // list | new | detail
$ticket_id = (int)($_GET['id'] ?? 0);

// ── Actions POST ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'new_ticket') {
        $type     = trim($_POST['ticket_type'] ?? '');
        $priority = trim($_POST['priority'] ?? 'normale');
        $subject  = trim($_POST['subject'] ?? '');
        $message  = trim($_POST['message'] ?? '');
        if ($type && $subject && $message) {
            $pdo->prepare("INSERT INTO support_tickets (user_id, ticket_type, priority, subject, message, status) VALUES (?,?,?,?,?,'En attente')")
                ->execute([$_SESSION['user_id'], $type, $priority, $subject, $message]);
            $flash = 'ok:Ticket créé avec succès. Notre équipe vous répondra dans les plus brefs délais.';
            header('Location: /support/?view=list'); exit();
        } else {
            $flash = 'err:Veuillez remplir tous les champs obligatoires.';
        }
    }

    if ($action === 'close_ticket') {
        $tid = (int)($_POST['ticket_id'] ?? 0);
        $pdo->prepare("UPDATE support_tickets SET status='Fermé' WHERE id=? AND user_id=?")->execute([$tid, $_SESSION['user_id']]);
        header('Location: /support/?view=list'); exit();
    }
}

// ── Données ────────────────────────────────────────────────────────────────────
$filter = $_GET['filter'] ?? 'all';

$all_tickets = $pdo->prepare('SELECT * FROM support_tickets WHERE user_id=? ORDER BY updated_at DESC, created_at DESC');
$all_tickets->execute([$_SESSION['user_id']]);
$tickets = $all_tickets->fetchAll();

$counts = ['all' => count($tickets), 'open' => 0, 'replied' => 0, 'closed' => 0];
foreach ($tickets as $t) {
    if ($t['status'] === 'Fermé') $counts['closed']++;
    elseif ($t['status'] === 'Traité') $counts['replied']++;
    else $counts['open']++;
}

$current_ticket = null;
if ($view === 'detail' && $ticket_id) {
    $ct = $pdo->prepare('SELECT * FROM support_tickets WHERE id=? AND user_id=? LIMIT 1');
    $ct->execute([$ticket_id, $_SESSION['user_id']]);
    $current_ticket = $ct->fetch();
    if (!$current_ticket) { header('Location: /support/'); exit(); }
}

$displayed = $filter === 'all' ? $tickets : array_filter($tickets, function($t) use ($filter) {
    return match($filter) {
        'open'    => !in_array($t['status'], ['Traité','Fermé']),
        'replied' => $t['status'] === 'Traité',
        'closed'  => $t['status'] === 'Fermé',
        default   => true,
    };
});

function ticket_badge(string $status): array {
    return match($status) {
        'Traité'    => ['badge-green',  'Répondu'],
        'Fermé'     => ['badge-gray',   'Fermé'],
        'Ouvert'    => ['badge-orange', 'Ouvert'],
        default     => ['badge-blue',   'En attente'],
    };
}
function priority_badge(string $p): array {
    return match($p) {
        'haute'   => ['badge-red',    'Haute'],
        'normale' => ['badge-blue',   'Normale'],
        default   => ['badge-gray',   ucfirst($p)],
    };
}
function type_color(string $type): string {
    return match($type) {
        'Bug'        => 'text-red-400',
        'Réclamation'=> 'text-orange-400',
        default      => 'text-sky-400',
    };
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support — OrinHeberge</title>
    <link rel="icon" href="/favicon.ico">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="manifest" href="/manifest.json">
    <style>
        :root{--sidebar:240px;}
        *{box-sizing:border-box;}
        body{background:#0d0f14;color:#e2e8f0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',system-ui,sans-serif;min-height:100vh;}
        .sidebar{position:fixed;top:0;left:0;width:var(--sidebar);height:100vh;background:#111318;border-right:1px solid rgba(255,255,255,.06);display:flex;flex-direction:column;z-index:40;overflow-y:auto;}
        .sidebar-logo{padding:1.5rem 1.25rem 1rem;border-bottom:1px solid rgba(255,255,255,.05);}
        .sidebar-nav{padding:.75rem .75rem;flex:1;}
        .nav-item{display:flex;align-items:center;gap:.75rem;padding:.625rem .875rem;border-radius:.625rem;font-size:.82rem;font-weight:500;color:#6b7280;transition:all .15s;text-decoration:none;margin-bottom:.15rem;border:1px solid transparent;}
        .nav-item:hover{background:rgba(255,255,255,.04);color:#d1d5db;}
        .nav-item.active{background:rgba(168,85,247,.08);color:#a855f7;border-color:rgba(168,85,247,.15);}
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
        .ticket-row{display:flex;align-items:center;gap:1rem;padding:1rem 1.25rem;border-bottom:1px solid rgba(255,255,255,.04);cursor:pointer;transition:background .15s;text-decoration:none;}
        .ticket-row:last-child{border-bottom:none;}
        .ticket-row:hover{background:rgba(255,255,255,.02);}
        .ticket-dot{width:.5rem;height:.5rem;border-radius:50%;flex-shrink:0;}
        input,textarea,select{background:#1e2330;border:1px solid rgba(255,255,255,.08);color:#e2e8f0;border-radius:.625rem;padding:.6rem .875rem;font-size:.83rem;width:100%;outline:none;transition:border-color .15s;}
        input:focus,textarea:focus,select:focus{border-color:rgba(168,85,247,.4);}
        .filter-btn{padding:.35rem .875rem;border-radius:9999px;font-size:.75rem;font-weight:600;border:1px solid rgba(255,255,255,.07);color:#6b7280;cursor:pointer;transition:all .15s;text-decoration:none;white-space:nowrap;}
        .filter-btn:hover{background:rgba(255,255,255,.05);color:#d1d5db;}
        .filter-btn.active{background:rgba(168,85,247,.1);border-color:rgba(168,85,247,.3);color:#a855f7;}
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
            <div class="w-8 h-8 rounded-lg bg-purple-500/20 flex items-center justify-center">
                <i class="fas fa-headset text-purple-400 text-sm"></i>
            </div>
            <div>
                <span class="font-black text-white text-sm tracking-tight block leading-tight">OrinHeberge</span>
                <span class="text-[10px] text-purple-400 font-semibold">Support</span>
            </div>
        </a>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-section">Support</div>
        <a href="/support/?view=list" class="nav-item <?php echo ($view==='list'||$view==='detail')?'active':''; ?>">
            <i class="fas fa-ticket-alt icon"></i> Mes tickets
            <?php if ($counts['open'] > 0): ?><span class="ml-auto text-[10px] bg-orange-500/15 text-orange-400 border border-orange-500/20 px-1.5 py-0.5 rounded-full font-bold"><?php echo $counts['open']; ?></span><?php endif; ?>
        </a>
        <a href="/support/?view=new" class="nav-item <?php echo $view==='new'?'active':''; ?>">
            <i class="fas fa-plus icon"></i> Nouveau ticket
        </a>
        <div class="nav-separator"></div>
        <div class="nav-section">Ressources</div>
        <a href="https://discord.gg/rnM2fngc7Z" target="_blank" class="nav-item">
            <i class="fab fa-discord icon" style="color:#5865F2"></i> Discord
        </a>
        <a href="/status/" class="nav-item">
            <i class="fas fa-signal icon"></i> Statut
        </a>
        <?php if ($is_admin): ?>
        <div class="nav-separator"></div>
        <div class="nav-section">Admin</div>
        <a href="/support/admin_tickets/" class="nav-item" style="color:#f43f5e;">
            <i class="fas fa-shield-alt icon"></i> Gérer les tickets
        </a>
        <?php endif; ?>
        <div class="nav-separator"></div>
        <div class="nav-section">Navigation</div>
        <a href="/client/" class="nav-item"><i class="fas fa-home icon"></i> Dashboard</a>
        <a href="/client/servers/" class="nav-item"><i class="fas fa-server icon"></i> Mes serveurs</a>
    </nav>
    <div class="sidebar-footer">
        <a href="/profil/" class="flex items-center gap-2.5 mb-2">
            <?php if (!empty($_SESSION['avatar']) && file_exists($_SERVER['DOCUMENT_ROOT'].'/'.$_SESSION['avatar'])): ?>
                <img src="/<?php echo htmlspecialchars($_SESSION['avatar']); ?>" class="w-8 h-8 rounded-full object-cover border border-white/10">
            <?php else: ?>
                <div class="w-8 h-8 rounded-full bg-purple-500/20 flex items-center justify-center text-purple-400 text-xs font-bold border border-white/10"><?php echo strtoupper(substr($_SESSION['username']??'U',0,1)); ?></div>
            <?php endif; ?>
            <div class="flex-1 min-w-0">
                <div class="text-xs font-semibold text-white truncate"><?php echo htmlspecialchars($_SESSION['username']??''); ?></div>
                <div class="text-[10px] text-gray-500">Mon profil</div>
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
                <div class="text-sm font-bold text-white"><?php echo $view==='new'?'Nouveau ticket':($view==='detail'&&$current_ticket?htmlspecialchars($current_ticket['subject']):'Mes tickets'); ?></div>
                <div class="text-xs text-gray-500"><?php echo $counts['all']; ?> ticket(s) au total</div>
            </div>
        </div>
        <a href="/support/?view=new" class="flex items-center gap-2 bg-purple-600 hover:bg-purple-500 text-white text-xs font-bold px-4 py-2 rounded-lg transition">
            <i class="fas fa-plus text-[10px]"></i> Nouveau ticket
        </a>
    </div>

    <div class="content">
        <?php if ($flash): [$ft,$fm] = explode(':', $flash, 2); ?>
        <div class="mb-5 p-3.5 rounded-xl text-sm font-medium flex items-center gap-2" style="<?php echo $ft==='ok'?'background:rgba(34,197,94,.08);border:1px solid rgba(34,197,94,.2);color:#22c55e;':'background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.2);color:#ef4444;'; ?>">
            <i class="fas fa-<?php echo $ft==='ok'?'check-circle':'exclamation-circle'; ?>"></i> <?php echo htmlspecialchars($fm); ?>
        </div>
        <?php endif; ?>

        <?php if ($view === 'new'): ?>
        <!-- ── NOUVEAU TICKET ── -->
        <div class="max-w-2xl">
            <div class="card p-6">
                <h2 class="text-base font-bold text-white mb-5 flex items-center gap-2"><i class="fas fa-plus-circle text-purple-400 text-sm"></i> Ouvrir un ticket</h2>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="new_ticket">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs text-gray-500 mb-1.5 font-semibold uppercase tracking-wide">Type <span class="text-red-400">*</span></label>
                            <select name="ticket_type">
                                <option value="Bug" style="background:#1e2330">🐛 Bug / Problème technique</option>
                                <option value="Réclamation" style="background:#1e2330">⚠️ Réclamation</option>
                                <option value="Facturation" style="background:#1e2330">💳 Facturation</option>
                                <option value="Autre" style="background:#1e2330">💬 Autre demande</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1.5 font-semibold uppercase tracking-wide">Priorité</label>
                            <select name="priority">
                                <option value="normale" style="background:#1e2330">🔵 Normale</option>
                                <option value="haute" style="background:#1e2330">🔴 Haute</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1.5 font-semibold uppercase tracking-wide">Sujet <span class="text-red-400">*</span></label>
                        <input type="text" name="subject" required placeholder="Ex: Mon serveur Minecraft ne démarre plus…">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1.5 font-semibold uppercase tracking-wide">Description <span class="text-red-400">*</span></label>
                        <textarea name="message" rows="6" required placeholder="Décrivez votre problème en détail. Plus vous donnez d'informations, plus vite nous pourrons vous aider." style="resize:vertical"></textarea>
                        <p class="text-[11px] text-gray-600 mt-1">Incluez : version du jeu, messages d'erreur, étapes pour reproduire le problème.</p>
                    </div>
                    <div class="flex gap-3 pt-2">
                        <button type="submit" class="flex-1 bg-purple-600 hover:bg-purple-500 text-white font-bold py-2.5 rounded-lg text-sm transition flex items-center justify-center gap-2">
                            <i class="fas fa-paper-plane text-xs"></i> Envoyer le ticket
                        </button>
                        <a href="/support/" class="px-5 py-2.5 rounded-lg text-sm font-semibold text-gray-400 hover:text-white transition" style="background:rgba(255,255,255,.05)">Annuler</a>
                    </div>
                </form>
            </div>

            <!-- Info box -->
            <div class="mt-4 card p-4 flex items-start gap-3">
                <div class="w-7 h-7 rounded-lg bg-blue-500/15 flex items-center justify-center shrink-0 mt-0.5"><i class="fas fa-info text-blue-400 text-xs"></i></div>
                <div>
                    <div class="text-xs font-semibold text-white mb-1">Besoin d'aide rapide ?</div>
                    <div class="text-xs text-gray-500">Pour les questions urgentes, rejoignez notre <a href="https://discord.gg/rnM2fngc7Z" target="_blank" class="text-purple-400 hover:underline">serveur Discord</a>. Temps de réponse moyen : 1h en semaine.</div>
                </div>
            </div>
        </div>

        <?php elseif ($view === 'detail' && $current_ticket): ?>
        <!-- ── DÉTAIL TICKET ── -->
        <?php [$sb, $sl] = ticket_badge($current_ticket['status']); [$pb, $pl] = priority_badge($current_ticket['priority'] ?? 'normale'); ?>
        <div class="max-w-3xl">
            <div class="flex items-center gap-3 mb-5">
                <a href="/support/" class="text-gray-500 hover:text-white transition text-sm"><i class="fas fa-arrow-left"></i></a>
                <div class="flex-1">
                    <h1 class="text-base font-bold text-white"><?php echo htmlspecialchars($current_ticket['subject']); ?></h1>
                    <div class="flex items-center gap-2 mt-1 flex-wrap">
                        <span class="badge <?php echo $sb; ?>"><?php echo $sl; ?></span>
                        <span class="badge <?php echo $pb; ?>"><?php echo $pl; ?> priorité</span>
                        <span class="text-[11px] text-gray-600">Ticket #<?php echo $current_ticket['id']; ?> • <?php echo date('d/m/Y à H:i', strtotime($current_ticket['created_at'])); ?></span>
                    </div>
                </div>
                <?php if ($current_ticket['status'] !== 'Fermé'): ?>
                <form method="POST" onsubmit="return confirm('Fermer ce ticket ?')">
                    <input type="hidden" name="action" value="close_ticket">
                    <input type="hidden" name="ticket_id" value="<?php echo $current_ticket['id']; ?>">
                    <button type="submit" class="text-xs font-semibold px-3 py-1.5 rounded-lg text-gray-500 hover:text-red-400 transition" style="background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.06)"><i class="fas fa-times mr-1"></i>Fermer</button>
                </form>
                <?php endif; ?>
            </div>

            <!-- Message original -->
            <div class="card mb-4">
                <div class="px-5 py-3 border-b border-white/[0.05] flex items-center gap-2.5">
                    <div class="w-7 h-7 rounded-full bg-purple-500/20 flex items-center justify-center text-purple-400 text-xs font-bold shrink-0"><?php echo strtoupper(substr($_SESSION['username']??'U',0,1)); ?></div>
                    <div>
                        <span class="text-xs font-semibold text-white"><?php echo htmlspecialchars($_SESSION['username']??''); ?></span>
                        <span class="text-[11px] text-gray-600 ml-2"><?php echo date('d/m/Y H:i', strtotime($current_ticket['created_at'])); ?></span>
                    </div>
                    <span class="ml-auto text-[11px] text-gray-600 <?php echo type_color($current_ticket['ticket_type']); ?>"><?php echo htmlspecialchars($current_ticket['ticket_type']); ?></span>
                </div>
                <div class="p-5">
                    <p class="text-sm text-gray-300 whitespace-pre-wrap leading-relaxed"><?php echo htmlspecialchars($current_ticket['message']); ?></p>
                </div>
            </div>

            <!-- Réponse de l'équipe -->
            <?php if (!empty($current_ticket['reply'])): ?>
            <div class="card mb-4" style="border-color:rgba(34,197,94,.15);background:rgba(34,197,94,.03);">
                <div class="px-5 py-3 border-b flex items-center gap-2.5" style="border-color:rgba(34,197,94,.1);">
                    <div class="w-7 h-7 rounded-full bg-green-500/20 flex items-center justify-center shrink-0"><i class="fas fa-shield-alt text-green-400 text-xs"></i></div>
                    <div>
                        <span class="text-xs font-semibold text-green-300">Équipe OrinHeberge</span>
                        <span class="text-[11px] text-gray-600 ml-2"><?php echo $current_ticket['updated_at'] ? date('d/m/Y H:i', strtotime($current_ticket['updated_at'])) : ''; ?></span>
                    </div>
                    <span class="ml-auto badge badge-green">Réponse officielle</span>
                </div>
                <div class="p-5">
                    <p class="text-sm text-gray-200 whitespace-pre-wrap leading-relaxed"><?php echo htmlspecialchars($current_ticket['reply']); ?></p>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($current_ticket['status'] === 'Fermé'): ?>
            <div class="text-center py-6 text-xs text-gray-600"><i class="fas fa-lock mr-2"></i>Ce ticket est fermé.</div>
            <?php elseif (!empty($current_ticket['reply'])): ?>
            <div class="card p-4 text-center">
                <p class="text-xs text-gray-500 mb-3">Ce ticket a reçu une réponse. Si le problème persiste, <a href="/support/?view=new" class="text-purple-400 hover:underline">ouvrez un nouveau ticket</a>.</p>
            </div>
            <?php else: ?>
            <div class="card p-4 text-xs text-gray-600 flex items-center gap-2"><i class="fas fa-clock text-yellow-400"></i> En attente de réponse de notre équipe…</div>
            <?php endif; ?>
        </div>

        <?php else: ?>
        <!-- ── LISTE TICKETS ── -->
        <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
            <div class="flex gap-2 flex-wrap">
                <a href="?filter=all" class="filter-btn <?php echo $filter==='all'?'active':''; ?>">Tous <span class="ml-1 opacity-60"><?php echo $counts['all']; ?></span></a>
                <a href="?filter=open" class="filter-btn <?php echo $filter==='open'?'active':''; ?>">En attente <span class="ml-1 opacity-60"><?php echo $counts['open']; ?></span></a>
                <a href="?filter=replied" class="filter-btn <?php echo $filter==='replied'?'active':''; ?>">Répondus <span class="ml-1 opacity-60"><?php echo $counts['replied']; ?></span></a>
                <a href="?filter=closed" class="filter-btn <?php echo $filter==='closed'?'active':''; ?>">Fermés <span class="ml-1 opacity-60"><?php echo $counts['closed']; ?></span></a>
            </div>
        </div>

        <?php if (empty($displayed)): ?>
        <div class="card p-12 text-center">
            <div class="w-12 h-12 rounded-xl bg-purple-500/10 flex items-center justify-center mx-auto mb-3"><i class="fas fa-ticket-alt text-purple-400"></i></div>
            <div class="text-sm font-semibold text-gray-300 mb-1">Aucun ticket</div>
            <div class="text-xs text-gray-500 mb-4">Vous n'avez pas encore ouvert de ticket de support.</div>
            <a href="/support/?view=new" class="inline-flex items-center gap-2 bg-purple-600 hover:bg-purple-500 text-white text-xs font-bold px-4 py-2 rounded-lg transition"><i class="fas fa-plus text-[10px]"></i> Ouvrir un ticket</a>
        </div>
        <?php else: ?>
        <div class="card overflow-hidden">
            <div class="px-5 py-3.5 border-b border-white/[0.05] flex items-center justify-between">
                <span class="text-xs font-bold text-white"><?php echo count($displayed); ?> ticket(s)</span>
            </div>
            <?php foreach ($displayed as $t): [$sb,$sl] = ticket_badge($t['status']); [$pb,$pl] = priority_badge($t['priority']??'normale'); ?>
            <a href="/support/?view=detail&id=<?php echo $t['id']; ?>" class="ticket-row">
                <div class="ticket-dot <?php echo $t['status']==='Fermé'?'bg-gray-600':($t['status']==='Traité'?'bg-green-500':'bg-orange-500'); ?>"></div>
                <div class="flex-1 min-w-0">
                    <div class="text-sm font-semibold text-white truncate"><?php echo htmlspecialchars($t['subject']); ?></div>
                    <div class="text-xs text-gray-500 mt-0.5 flex items-center gap-2">
                        <span class="<?php echo type_color($t['ticket_type']); ?>"><?php echo htmlspecialchars($t['ticket_type']); ?></span>
                        <span>•</span>
                        <span>Ticket #<?php echo $t['id']; ?></span>
                        <span>•</span>
                        <span><?php echo date('d/m/Y', strtotime($t['created_at'])); ?></span>
                    </div>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <span class="badge <?php echo $pb; ?> hidden sm:inline-flex"><?php echo $pl; ?></span>
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

<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/lang.php';
if (!isset($_SESSION['user_id'])) { header('Location: /login/'); exit(); }

require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/db.php';

// Rafraîchir session user
$stmt = $pdo->prepare('SELECT pseudo, firstname, avatar, is_admin FROM users WHERE id=? LIMIT 1');
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
$_SESSION['username'] = !empty($user['pseudo']) ? $user['pseudo'] : ($user['firstname'] ?? 'Utilisateur');
$_SESSION['avatar']   = $user['avatar'] ?? '';
$is_admin = (bool)($user['is_admin'] ?? false);

// Tickets ouverts
$stmt2 = $pdo->prepare("SELECT COUNT(*) FROM support_tickets WHERE user_id=? AND status != 'Fermé'");
$stmt2->execute([$_SESSION['user_id']]);
$open_tickets = (int)$stmt2->fetchColumn();

// ── Récupérer les factures ────────────────────────────────────────────────────
$page     = max(1, (int)($_GET['page'] ?? 1));
$per_page = 10;
$offset   = ($page - 1) * $per_page;

$total_stmt = $pdo->prepare("SELECT COUNT(*) FROM invoices WHERE user_id=?");
$total_stmt->execute([$_SESSION['user_id']]);
$total_invoices = (int)$total_stmt->fetchColumn();
$total_pages    = max(1, (int)ceil($total_invoices / $per_page));

$inv_stmt = $pdo->prepare("
    SELECT * FROM invoices
    WHERE user_id=?
    ORDER BY created_at DESC
    LIMIT ? OFFSET ?
");
$inv_stmt->bindValue(1, (int)$_SESSION['user_id'], PDO::PARAM_INT);
$inv_stmt->bindValue(2, $per_page,                 PDO::PARAM_INT);
$inv_stmt->bindValue(3, $offset,                   PDO::PARAM_INT);
$inv_stmt->execute();
$invoices = $inv_stmt->fetchAll();

// Totaux
$totals = $pdo->prepare("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN status='paid' THEN amount ELSE 0 END) AS total_paid,
        SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) AS pending_count
    FROM invoices WHERE user_id=?
");
$totals->execute([$_SESSION['user_id']]);
$stats = $totals->fetch();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OrinHeberge — Facturation</title>
    <link rel="icon" type="image/png" href="/favicon.ico">
    <link rel="manifest" href="/manifest.json">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { --sidebar: 240px; }
        * { box-sizing: border-box; }
        body { background: #0d0f14; color: #e2e8f0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif; min-height: 100vh; }
        .sidebar { position: fixed; top: 0; left: 0; width: var(--sidebar); height: 100vh; background: #111318; border-right: 1px solid rgba(255,255,255,.06); display: flex; flex-direction: column; z-index: 40; overflow-y: auto; }
        .sidebar-logo { padding: 1.5rem 1.25rem 1rem; border-bottom: 1px solid rgba(255,255,255,.05); }
        .sidebar-nav { padding: .75rem .75rem; flex: 1; }
        .nav-item { display: flex; align-items: center; gap: .75rem; padding: .625rem .875rem; border-radius: .625rem; font-size: .82rem; font-weight: 500; color: #6b7280; transition: all .15s; text-decoration: none; margin-bottom: .15rem; border: 1px solid transparent; }
        .nav-item:hover { background: rgba(255,255,255,.04); color: #d1d5db; }
        .nav-item.active { background: rgba(56,189,248,.08); color: #38bdf8; border-color: rgba(56,189,248,.15); }
        .nav-item .icon { width: 1.1rem; text-align: center; font-size: .85rem; flex-shrink: 0; }
        .nav-section { font-size: .65rem; font-weight: 700; letter-spacing: .1em; color: #374151; text-transform: uppercase; padding: .75rem .875rem .35rem; }
        .nav-separator { height: 1px; background: rgba(255,255,255,.05); margin: .5rem .75rem; }
        .sidebar-footer { padding: .875rem 1rem; border-top: 1px solid rgba(255,255,255,.05); }
        .main-content { margin-left: var(--sidebar); min-height: 100vh; display: flex; flex-direction: column; }
        .topbar { background: #111318; border-bottom: 1px solid rgba(255,255,255,.06); padding: .875rem 1.75rem; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 30; }
        .content { padding: 1.75rem; flex: 1; }
        .card { background: #161a22; border: 1px solid rgba(255,255,255,.07); border-radius: .875rem; }
        .stat-card { background: linear-gradient(135deg,#161a22,#1a1f2a); border: 1px solid rgba(255,255,255,.07); border-radius: .875rem; padding: 1.25rem; }
        .badge { display: inline-flex; align-items: center; gap: .35rem; padding: .2rem .65rem; border-radius: 9999px; font-size: .72rem; font-weight: 600; }
        .badge-green  { background: rgba(34,197,94,.1);   color: #22c55e; border: 1px solid rgba(34,197,94,.2); }
        .badge-orange { background: rgba(249,115,22,.1);  color: #f97316; border: 1px solid rgba(249,115,22,.2); }
        .badge-red    { background: rgba(239,68,68,.1);   color: #ef4444; border: 1px solid rgba(239,68,68,.2); }
        .badge-gray   { background: rgba(107,114,128,.1); color: #9ca3af; border: 1px solid rgba(107,114,128,.2); }
        .badge-blue   { background: rgba(56,189,248,.1);  color: #38bdf8; border: 1px solid rgba(56,189,248,.2); }
        .invoice-row { display: flex; align-items: center; gap: 1rem; padding: 1rem 1.25rem; border-bottom: 1px solid rgba(255,255,255,.04); transition: background .15s; }
        .invoice-row:last-child { border-bottom: none; }
        .invoice-row:hover { background: rgba(255,255,255,.02); }
        .btn-sm { display: inline-flex; align-items: center; gap: .35rem; padding: .3rem .75rem; border-radius: .5rem; font-size: .74rem; font-weight: 600; transition: all .15s; border: 1px solid transparent; text-decoration: none; }
        .btn-primary { background: rgba(56,189,248,.12); color: #38bdf8; border-color: rgba(56,189,248,.2); }
        .btn-primary:hover { background: #0ea5e9; color: #fff; border-color: #0ea5e9; }
        .mobile-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.6); z-index: 39; }
        @media(max-width:768px) {
            .sidebar { transform: translateX(-100%); transition: transform .25s; }
            .sidebar.open { transform: translateX(0); }
            .mobile-overlay.open { display: block; }
            .main-content { margin-left: 0; }
            .topbar { padding: .75rem 1rem; }
            .content { padding: 1rem; }
            .hide-mobile { display: none; }
        }
    </style>
    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('open');
            document.getElementById('overlay').classList.toggle('open');
        }
    </script>
</head>
<body>

<div id="overlay" class="mobile-overlay" onclick="toggleSidebar()"></div>

<!-- ══ SIDEBAR ══ -->
<aside id="sidebar" class="sidebar">
    <div class="sidebar-logo">
        <a href="/" class="flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-lg bg-sky-500/20 flex items-center justify-center">
                <i class="fas fa-server text-sky-400 text-sm"></i>
            </div>
            <span class="font-black text-white text-base tracking-tight">OrinHeberge</span>
        </a>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section">Principal</div>
        <a href="/client/" class="nav-item">
            <i class="fas fa-home icon"></i> Tableau de bord
        </a>
        <a href="/client/servers/" class="nav-item">
            <i class="fas fa-server icon"></i> Mes serveurs
        </a>
        <a href="/offres/" class="nav-item">
            <i class="fas fa-tags icon"></i> Nos offres
        </a>

        <div class="nav-separator"></div>
        <div class="nav-section">Compte</div>
        <a href="/profil/" class="nav-item">
            <i class="fas fa-user icon"></i> Mon profil
        </a>
        <a href="/client/billing/" class="nav-item active">
            <i class="fas fa-file-invoice-dollar icon"></i> Facturation
        </a>
        <a href="/support/" class="nav-item">
            <i class="fas fa-headset icon"></i> Support
            <?php if ($open_tickets > 0): ?>
            <span class="ml-auto text-[10px] bg-purple-500/20 text-purple-400 border border-purple-500/20 px-1.5 py-0.5 rounded-full font-bold"><?php echo $open_tickets; ?></span>
            <?php endif; ?>
        </a>
        <a href="/status/" class="nav-item">
            <i class="fas fa-signal icon"></i> Statut
        </a>


        <div class="nav-separator"></div>
        <div class="nav-section">Outils</div>
        <a href="<?php echo htmlspecialchars($panel_url); ?>" target="_blank" class="nav-item">
            <i class="fas fa-cogs icon"></i> Panel Pterodactyl
        </a>
        <a href="<?php echo htmlspecialchars($cfg['phpmyadmin_url'] ?? 'https://php.orinstone.deepstone.fr'); ?>" target="_blank" class="nav-item">
            <i class="fas fa-database icon"></i> phpMyAdmin
        </a>
    </nav>

    <div class="sidebar-footer">
        <a href="/profil/" class="flex items-center gap-2.5 group mb-2">
            <?php if (!empty($_SESSION['avatar']) && file_exists($_SERVER['DOCUMENT_ROOT'].'/'.$_SESSION['avatar'])): ?>
                <img src="/<?php echo htmlspecialchars($_SESSION['avatar']); ?>" class="w-8 h-8 rounded-full object-cover border border-white/10">
            <?php else: ?>
                <div class="w-8 h-8 rounded-full bg-sky-500/20 flex items-center justify-center text-sky-400 text-xs font-bold border border-white/10">
                    <?php echo strtoupper(substr($_SESSION['username'] ?? 'U', 0, 1)); ?>
                </div>
            <?php endif; ?>
            <div class="flex-1 min-w-0">
                <div class="text-xs font-semibold text-white truncate"><?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?></div>
                <div class="text-[10px] text-gray-500">Mon profil</div>
            </div>
        </a>
        <a href="/logout/" class="nav-item" style="color:#ef4444;">
            <i class="fas fa-sign-out-alt icon"></i> Déconnexion
        </a>
    </div>
</aside>

<!-- ══ MAIN ══ -->
<div class="main-content">

    <!-- Topbar -->
    <div class="topbar">
        <div class="flex items-center gap-3">
            <button onclick="toggleSidebar()" class="md:hidden text-gray-400 hover:text-white text-lg w-8">
                <i class="fas fa-bars"></i>
            </button>
            <div>
                <div class="text-sm font-bold text-white">Facturation</div>
                <div class="text-xs text-gray-500">Historique de vos paiements</div>
            </div>
        </div>
        <a href="/profil/" class="w-8 h-8 rounded-full overflow-hidden border border-white/10 flex items-center justify-center bg-sky-500/10 shrink-0">
            <?php if (!empty($_SESSION['avatar']) && file_exists($_SERVER['DOCUMENT_ROOT'].'/'.$_SESSION['avatar'])): ?>
                <img src="/<?php echo htmlspecialchars($_SESSION['avatar']); ?>" class="w-full h-full object-cover">
            <?php else: ?>
                <span class="text-sky-400 text-xs font-bold"><?php echo strtoupper(substr($_SESSION['username'] ?? 'U', 0, 1)); ?></span>
            <?php endif; ?>
        </a>
    </div>

    <!-- Content -->
    <div class="content">

        <!-- Stats -->
        <div class="grid grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
            <div class="stat-card">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-xs text-gray-500 font-medium">Total factures</span>
                    <div class="w-7 h-7 rounded-lg bg-sky-500/15 flex items-center justify-center">
                        <i class="fas fa-file-invoice text-sky-400 text-xs"></i>
                    </div>
                </div>
                <div class="text-2xl font-black text-white"><?php echo (int)($stats['total'] ?? 0); ?></div>
                <div class="text-xs text-gray-500 mt-1">Émises</div>
            </div>
            <div class="stat-card">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-xs text-gray-500 font-medium">Total dépensé</span>
                    <div class="w-7 h-7 rounded-lg bg-green-500/15 flex items-center justify-center">
                        <i class="fas fa-euro-sign text-green-400 text-xs"></i>
                    </div>
                </div>
                <div class="text-2xl font-black text-green-400"><?php echo number_format((float)($stats['total_paid'] ?? 0), 2, '.', ''); ?>€</div>
                <div class="text-xs text-gray-500 mt-1">Payés</div>
            </div>
            <div class="stat-card col-span-2 lg:col-span-1">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-xs text-gray-500 font-medium">En attente</span>
                    <div class="w-7 h-7 rounded-lg bg-amber-500/15 flex items-center justify-center">
                        <i class="fas fa-clock text-amber-400 text-xs"></i>
                    </div>
                </div>
                <div class="text-2xl font-black text-amber-400"><?php echo (int)($stats['pending_count'] ?? 0); ?></div>
                <div class="text-xs text-gray-500 mt-1">À régler</div>
            </div>
        </div>

        <!-- Table factures -->
        <div class="card">
            <div class="flex items-center justify-between px-5 py-4 border-b border-white/[0.05]">
                <h2 class="text-sm font-bold text-white flex items-center gap-2">
                    <i class="fas fa-file-invoice-dollar text-sky-400 text-xs"></i> Mes factures
                </h2>
                <?php if ($total_invoices > 0): ?>
                <span class="text-xs text-gray-500"><?php echo $total_invoices; ?> facture<?php echo $total_invoices > 1 ? 's' : ''; ?></span>
                <?php endif; ?>
            </div>

            <?php if (empty($invoices)): ?>
            <div class="px-5 py-14 text-center">
                <div class="w-14 h-14 rounded-xl bg-sky-500/10 flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-file-invoice text-sky-400 text-lg"></i>
                </div>
                <div class="text-sm font-semibold text-gray-300 mb-1">Aucune facture</div>
                <div class="text-xs text-gray-500 mb-5">Vos factures apparaîtront ici après votre premier achat.</div>
                <a href="/offres/" class="btn-sm btn-primary"><i class="fas fa-tags text-[10px]"></i> Voir les offres</a>
            </div>

            <?php else: ?>

            <!-- En-têtes (masqués mobile) -->
            <div class="hide-mobile grid border-b border-white/[0.05] px-5 py-2.5 text-[10px] font-bold text-gray-600 uppercase tracking-wider"
                 style="grid-template-columns:1.5fr 2fr 1fr 1fr 1fr 1fr auto;">
                <span>N° Facture</span>
                <span>Service</span>
                <span>Type</span>
                <span>Montant</span>
                <span>Date</span>
                <span>Statut</span>
                <span></span>
            </div>

            <?php foreach ($invoices as $inv):
                $status_cfg = match($inv['status']) {
                    'paid'     => ['badge' => 'badge-green',  'label' => 'Payée',    'icon' => 'fa-check-circle'],
                    'pending'  => ['badge' => 'badge-orange', 'label' => 'En cours', 'icon' => 'fa-clock'],
                    'refunded' => ['badge' => 'badge-blue',   'label' => 'Remboursée','icon' => 'fa-rotate-left'],
                    default    => ['badge' => 'badge-gray',   'label' => 'Inconnu',  'icon' => 'fa-question'],
                };
                $type_cfg = match($inv['type']) {
                    'renewal'  => ['label' => 'Renouvellement', 'badge' => 'badge-blue',   'icon' => 'fa-rotate'],
                    'purchase' => ['label' => 'Achat',          'badge' => 'badge-green',  'icon' => 'fa-rocket'],
                    default    => ['label' => $inv['type'],     'badge' => 'badge-gray',   'icon' => 'fa-receipt'],
                };
                $pay_icon = match($inv['payment_method'] ?? '') {
                    'stripe' => '<i class="fas fa-credit-card text-indigo-400" title="Stripe"></i>',
                    'paypal' => '<i class="fab fa-paypal text-blue-400" title="PayPal"></i>',
                    default  => '<i class="fas fa-receipt text-gray-500"></i>',
                };
            ?>
            <div class="invoice-row flex-wrap gap-y-2">
                <!-- N° facture -->
                <div class="flex items-center gap-2.5 min-w-0" style="flex:1.5">
                    <div class="w-8 h-8 rounded-lg bg-sky-500/10 flex items-center justify-center shrink-0">
                        <i class="fas fa-file-invoice text-sky-400 text-xs"></i>
                    </div>
                    <div class="min-w-0">
                        <div class="text-xs font-bold text-white font-mono"><?php echo htmlspecialchars($inv['invoice_id']); ?></div>
                        <div class="text-[10px] text-gray-600 flex items-center gap-1 mt-0.5"><?php echo $pay_icon; ?></div>
                    </div>
                </div>
                <!-- Service -->
                <div class="flex items-center text-xs text-gray-300 min-w-0" style="flex:2">
                    <span class="truncate"><?php echo htmlspecialchars($inv['service_name']); ?></span>
                </div>
                <!-- Type -->
                <div class="flex items-center hide-mobile" style="flex:1">
                    <span class="badge <?php echo $type_cfg['badge']; ?>">
                        <i class="fas <?php echo $type_cfg['icon']; ?> text-[10px]"></i>
                        <?php echo $type_cfg['label']; ?>
                    </span>
                </div>
                <!-- Montant -->
                <div class="flex items-center" style="flex:1">
                    <span class="text-sm font-black text-white"><?php echo number_format((float)$inv['amount'], 2, '.', ''); ?>€</span>
                </div>
                <!-- Date -->
                <div class="flex items-center text-xs text-gray-400 hide-mobile" style="flex:1">
                    <?php echo date('d/m/Y', strtotime($inv['created_at'])); ?>
                </div>
                <!-- Statut -->
                <div class="flex items-center" style="flex:1">
                    <span class="badge <?php echo $status_cfg['badge']; ?>">
                        <i class="fas <?php echo $status_cfg['icon']; ?> text-[10px]"></i>
                        <?php echo $status_cfg['label']; ?>
                    </span>
                </div>
                <!-- Actions -->
                <div class="flex items-center gap-2 shrink-0">
                    <a href="/client/billing/invoice/?id=<?php echo urlencode($inv['invoice_id']); ?>" class="btn-sm btn-primary">
                        <i class="fas fa-eye text-[10px]"></i> Voir
                    </a>
                    <a href="/client/billing/invoice/print/?id=<?php echo urlencode($inv['invoice_id']); ?>" target="_blank"
                       class="btn-sm" style="background:rgba(255,255,255,.04);color:#9ca3af;border-color:rgba(255,255,255,.08);" title="Imprimer / PDF">
                        <i class="fas fa-print text-[10px]"></i>
                    </a>
                </div>
            </div>
            <?php endforeach; ?>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="flex items-center justify-center gap-2 px-5 py-4 border-t border-white/[0.04]">
                <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page - 1; ?>" class="btn-sm" style="background:rgba(255,255,255,.04);color:#9ca3af;border-color:rgba(255,255,255,.08);">
                    <i class="fas fa-chevron-left text-[10px]"></i> Préc.
                </a>
                <?php endif; ?>
                <span class="text-xs text-gray-500">Page <?php echo $page; ?> / <?php echo $total_pages; ?></span>
                <?php if ($page < $total_pages): ?>
                <a href="?page=<?php echo $page + 1; ?>" class="btn-sm" style="background:rgba(255,255,255,.04);color:#9ca3af;border-color:rgba(255,255,255,.08);">
                    Suiv. <i class="fas fa-chevron-right text-[10px]"></i>
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php endif; ?>
        </div>

    </div><!-- /content -->
</div><!-- /main-content -->

</body>
</html>

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

// ── Récupérer la facture ──────────────────────────────────────────────────────
$invoice_id = trim($_GET['id'] ?? '');
if (!$invoice_id) { header('Location: /client/billing/'); exit(); }

$inv_stmt = $pdo->prepare("SELECT * FROM invoices WHERE invoice_id=? AND user_id=?");
$inv_stmt->execute([$invoice_id, $_SESSION['user_id']]);
$inv = $inv_stmt->fetch();
if (!$inv) { header('Location: /client/billing/'); exit(); }

// Récupérer la commande associée
$ord_stmt = $pdo->prepare("SELECT * FROM orders WHERE order_id=?");
$ord_stmt->execute([$inv['order_id']]);
$order = $ord_stmt->fetch();

// Infos user pour la facture
$usr_stmt = $pdo->prepare("SELECT firstname, lastname, email FROM users WHERE id=?");
$usr_stmt->execute([$_SESSION['user_id']]);
$usr_info = $usr_stmt->fetch();

$status_cfg = match($inv['status']) {
    'paid'     => ['badge' => 'badge-green',  'label' => 'Payée',     'color' => '#22c55e'],
    'pending'  => ['badge' => 'badge-orange', 'label' => 'En cours',  'color' => '#f97316'],
    'refunded' => ['badge' => 'badge-blue',   'label' => 'Remboursée','color' => '#38bdf8'],
    default    => ['badge' => 'badge-gray',   'label' => 'Inconnu',   'color' => '#9ca3af'],
};
$type_label = match($inv['type']) {
    'renewal'  => 'Renouvellement',
    'purchase' => 'Achat initial',
    default    => ucfirst($inv['type']),
};
$pay_method_label = match($inv['payment_method'] ?? '') {
    'stripe' => 'Carte bancaire (Stripe)',
    'paypal' => 'PayPal.me',
    default  => 'Manuel / Autre',
};

include $_SERVER['DOCUMENT_ROOT'] . '/inc/clients_sidebar.php';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OrinHeberge — Facture <?php echo htmlspecialchars($inv['invoice_id']); ?></title>
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
        .badge { display: inline-flex; align-items: center; gap: .35rem; padding: .2rem .65rem; border-radius: 9999px; font-size: .72rem; font-weight: 600; }
        .badge-green  { background: rgba(34,197,94,.1);   color: #22c55e; border: 1px solid rgba(34,197,94,.2); }
        .badge-orange { background: rgba(249,115,22,.1);  color: #f97316; border: 1px solid rgba(249,115,22,.2); }
        .badge-blue   { background: rgba(56,189,248,.1);  color: #38bdf8; border: 1px solid rgba(56,189,248,.2); }
        .badge-gray   { background: rgba(107,114,128,.1); color: #9ca3af; border: 1px solid rgba(107,114,128,.2); }
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
        }
    </style>
    <script>function toggleSidebar(){document.getElementById('sidebar').classList.toggle('open');document.getElementById('overlay').classList.toggle('open');}</script>
</head>
<body>

<div id="overlay" class="mobile-overlay" onclick="toggleSidebar()"></div>



<!-- ══ MAIN ══ -->
<div class="main-content">
    <div class="topbar">
        <div class="flex items-center gap-3">
            <button onclick="toggleSidebar()" class="md:hidden text-gray-400 hover:text-white text-lg w-8"><i class="fas fa-bars"></i></button>
            <div>
                <div class="text-sm font-bold text-white">Facture <?php echo htmlspecialchars($inv['invoice_id']); ?></div>
                <div class="text-xs text-gray-500">Détail du paiement</div>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <a href="/client/billing/invoice/print/?id=<?php echo urlencode($inv['invoice_id']); ?>" target="_blank"
               class="btn-sm" style="background:rgba(255,255,255,.05);color:#9ca3af;border-color:rgba(255,255,255,.08);">
                <i class="fas fa-print text-[10px]"></i> Imprimer / PDF
            </a>
            <a href="/client/billing/" class="btn-sm" style="background:rgba(255,255,255,.04);color:#6b7280;border-color:rgba(255,255,255,.06);">
                <i class="fas fa-arrow-left text-[10px]"></i> Retour
            </a>
        </div>
    </div>

    <div class="content">
        <div class="max-w-2xl mx-auto">

            <!-- Invoice card -->
            <div class="card overflow-hidden">

                <!-- Header facture -->
                <div class="p-6 border-b border-white/[0.06]" style="background: linear-gradient(135deg, #161a22, #1a1f2a);">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <div class="flex items-center gap-2.5 mb-2">
                                <div class="w-9 h-9 rounded-xl bg-sky-500/15 border border-sky-500/20 flex items-center justify-center">
                                    <i class="fas fa-file-invoice text-sky-400 text-sm"></i>
                                </div>
                                <div>
                                    <div class="text-base font-black text-white font-mono"><?php echo htmlspecialchars($inv['invoice_id']); ?></div>
                                    <div class="text-[11px] text-gray-500">Facture OrinHeberge</div>
                                </div>
                            </div>
                            <span class="badge <?php echo $status_cfg['badge']; ?>">
                                <i class="fas fa-circle text-[6px]"></i> <?php echo $status_cfg['label']; ?>
                            </span>
                        </div>
                        <div class="text-right">
                            <div class="text-3xl font-black text-white"><?php echo number_format((float)$inv['amount'], 2, '.', ''); ?>€</div>
                            <div class="text-xs text-gray-500 mt-1">
                                Émise le <?php echo date('d/m/Y', strtotime($inv['created_at'])); ?>
                            </div>
                            <?php if ($inv['paid_at']): ?>
                            <div class="text-[11px] text-green-400 mt-0.5">
                                <i class="fas fa-check text-[9px]"></i> Payée le <?php echo date('d/m/Y', strtotime($inv['paid_at'])); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Corps -->
                <div class="p-6 space-y-5">

                    <!-- Parties -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <!-- Émetteur -->
                        <div class="bg-white/[0.02] border border-white/[0.05] p-4 rounded-xl">
                            <div class="text-[10px] font-bold text-gray-600 uppercase tracking-wider mb-2">Émetteur</div>
                            <div class="text-sm font-bold text-white">OrinHeberge</div>
                            <div class="text-xs text-gray-500 mt-1">Infrastructure OrinStone</div>
                            <div class="text-xs text-gray-500">heberge.orinstone.deepstone.fr</div>
                        </div>
                        <!-- Client -->
                        <div class="bg-white/[0.02] border border-white/[0.05] p-4 rounded-xl">
                            <div class="text-[10px] font-bold text-gray-600 uppercase tracking-wider mb-2">Client</div>
                            <div class="text-sm font-bold text-white"><?php echo htmlspecialchars(trim(($usr_info['firstname'] ?? '') . ' ' . ($usr_info['lastname'] ?? ''))); ?></div>
                            <div class="text-xs text-gray-500 mt-1"><?php echo htmlspecialchars($usr_info['email'] ?? ''); ?></div>
                            <div class="text-xs text-gray-600 font-mono mt-0.5">ID #<?php echo $_SESSION['user_id']; ?></div>
                        </div>
                    </div>

                    <!-- Détail ligne -->
                    <div>
                        <div class="text-[10px] font-bold text-gray-600 uppercase tracking-wider mb-2">Détail de la commande</div>
                        <div class="bg-white/[0.02] border border-white/[0.05] rounded-xl overflow-hidden">
                            <!-- En-tête -->
                            <div class="grid px-4 py-2.5 border-b border-white/[0.05] text-[10px] font-bold text-gray-600 uppercase tracking-wider"
                                 style="grid-template-columns:3fr 1fr 1fr">
                                <span>Service</span><span class="text-center">Qté</span><span class="text-right">Prix</span>
                            </div>
                            <!-- Ligne -->
                            <div class="grid px-4 py-3 text-sm items-center" style="grid-template-columns:3fr 1fr 1fr">
                                <div>
                                    <div class="font-semibold text-white"><?php echo htmlspecialchars($inv['service_name']); ?></div>
                                    <div class="text-[11px] text-gray-500 mt-0.5"><?php echo $type_label; ?> · Commande #<?php echo htmlspecialchars($inv['order_id']); ?></div>
                                </div>
                                <div class="text-center text-gray-400">1</div>
                                <div class="text-right font-bold text-white"><?php echo number_format((float)$inv['amount'], 2, '.', ''); ?>€</div>
                            </div>
                            <!-- Total -->
                            <div class="border-t border-white/[0.05] px-4 py-3">
                                <div class="flex justify-between items-center">
                                    <span class="text-xs text-gray-500">Total HT</span>
                                    <span class="text-sm text-gray-300"><?php echo number_format((float)$inv['amount'], 2, '.', ''); ?>€</span>
                                </div>
                                <div class="flex justify-between items-center mt-1">
                                    <span class="text-xs text-gray-500">TVA (0%)</span>
                                    <span class="text-sm text-gray-500">0,00€</span>
                                </div>
                                <div class="flex justify-between items-center mt-2 pt-2 border-t border-white/[0.05]">
                                    <span class="text-sm font-bold text-white">Total TTC</span>
                                    <span class="text-lg font-black text-white"><?php echo number_format((float)$inv['amount'], 2, '.', ''); ?>€</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Informations paiement -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="bg-white/[0.02] border border-white/[0.05] p-3.5 rounded-xl">
                            <div class="text-[10px] font-bold text-gray-600 uppercase tracking-wider mb-2">Moyen de paiement</div>
                            <div class="flex items-center gap-2 text-sm">
                                <?php if (($inv['payment_method'] ?? '') === 'stripe'): ?>
                                    <i class="fas fa-credit-card text-indigo-400"></i>
                                <?php elseif (($inv['payment_method'] ?? '') === 'paypal'): ?>
                                    <i class="fab fa-paypal text-blue-400"></i>
                                <?php else: ?>
                                    <i class="fas fa-receipt text-gray-400"></i>
                                <?php endif; ?>
                                <span class="text-gray-300"><?php echo $pay_method_label; ?></span>
                            </div>
                            <?php if ($inv['payment_ref']): ?>
                            <div class="text-[10px] text-gray-600 font-mono mt-1.5 truncate"><?php echo htmlspecialchars(substr($inv['payment_ref'], 0, 40)) . (strlen($inv['payment_ref']) > 40 ? '…' : ''); ?></div>
                            <?php endif; ?>
                        </div>
                        <?php if ($order): ?>
                        <div class="bg-white/[0.02] border border-white/[0.05] p-3.5 rounded-xl">
                            <div class="text-[10px] font-bold text-gray-600 uppercase tracking-wider mb-2">Serveur associé</div>
                            <div class="text-sm font-semibold text-white"><?php echo htmlspecialchars($order['service_name']); ?></div>
                            <?php if ($order['id_server_panel']): ?>
                            <div class="text-[11px] text-sky-400 font-mono mt-1"><?php echo htmlspecialchars($order['id_server_panel']); ?></div>
                            <?php endif; ?>
                            <?php if ($order['next_payment_date']): ?>
                            <div class="text-[11px] text-gray-500 mt-1">Prochain paiement : <?php echo date('d/m/Y', strtotime($order['next_payment_date'])); ?></div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Note légale -->
                    <div class="text-[10px] text-gray-600 text-center pt-2 border-t border-white/[0.04]">
                        OrinHeberge — Infrastructure OrinStone · Ce document est une facture électronique générée automatiquement.
                    </div>
                </div>
            </div>

            <!-- Actions bas de page -->
            <div class="flex flex-wrap gap-3 mt-5 justify-center">
                <a href="/client/billing/invoice/print/?id=<?php echo urlencode($inv['invoice_id']); ?>" target="_blank"
                   class="btn-sm btn-primary text-sm px-5 py-2.5">
                    <i class="fas fa-download"></i> Télécharger / PDF
                </a>
                <a href="/client/billing/" class="btn-sm text-sm px-5 py-2.5" style="background:rgba(255,255,255,.04);color:#6b7280;border-color:rgba(255,255,255,.08);">
                    <i class="fas fa-arrow-left"></i> Retour aux factures
                </a>
            </div>

        </div>
    </div>
</div>

</body>
</html>

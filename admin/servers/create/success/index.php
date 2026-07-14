<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/lang.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/db.php';

if (!isset($_SESSION['user_id'])) { header('Location: /login/'); exit(); }
$chk = $pdo->prepare('SELECT is_admin, pseudo, firstname, avatar FROM users WHERE id=? LIMIT 1');
$chk->execute([$_SESSION['user_id']]);
$admin = $chk->fetch();
if (!$admin || !$admin['is_admin']) { http_response_code(403); die('403 — Accès refusé.'); }
$_SESSION['username'] = !empty($admin['pseudo']) ? $admin['pseudo'] : $admin['firstname'];

// ── Récupération des infos du serveur créé ─────────────────────────────────────
$order_id     = $_GET['order']    ?? ($_SESSION['last_created_order']    ?? null);
$invoice_id   = $_GET['invoice']  ?? ($_SESSION['last_created_invoice']  ?? null);
$server_uuid  = $_GET['uuid']     ?? ($_SESSION['last_created_uuid']     ?? null);
$client_id    = (int)($_GET['client'] ?? ($_SESSION['last_created_client'] ?? 0));
$product_id   = (int)($_GET['product'] ?? ($_SESSION['last_created_product'] ?? 0));

// Nettoyer la session après lecture
unset($_SESSION['last_created_order'], $_SESSION['last_created_invoice'], 
      $_SESSION['last_created_uuid'], $_SESSION['last_created_client'], 
      $_SESSION['last_created_product']);

if (!$order_id) {
    header('Location: /admin/?view=servers');
    exit();
}

// Charger les détails
$stmt = $pdo->prepare("
    SELECT o.*, u.email, u.pseudo, u.firstname, u.lastname,
           p.name AS product_name, p.ram, p.disk, p.cpu, p.databases, p.allocations, p.backups,
           n.name AS node_name, n.fqdn AS node_fqdn
    FROM orders o
    LEFT JOIN users u ON u.id = o.user_id
    LEFT JOIN products p ON p.id = o.product_id
    LEFT JOIN nodes n ON n.id = p.node_id
    WHERE o.order_id = ? AND o.created_by_admin = 1
    LIMIT 1
");
$stmt->execute([$order_id]);
$order = $stmt->fetch();

if (!$order) {
    header('Location: /admin/?view=servers&error=not_found');
    exit();
}

// Charger la facture si elle existe
$invoice = null;
if ($invoice_id) {
    $inv_stmt = $pdo->prepare("SELECT * FROM invoices WHERE invoice_id = ? LIMIT 1");
    $inv_stmt->execute([$invoice_id]);
    $invoice = $inv_stmt->fetch();
}

$client_name = !empty($order['pseudo']) ? $order['pseudo'] : trim($order['firstname'] . ' ' . $order['lastname']);
$panel_url   = $cfg['panel_url'] ?? 'https://panel.orinstone.deepstone.fr';
$panel_pass  = $order['panel_password'] ?? null;

$active_nav = 'servers';
include $_SERVER['DOCUMENT_ROOT'] . '/inc/admin_sidebar.php';
?>

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
    .stat-card{background:linear-gradient(135deg,#161a22,#1a1f2a);border:1px solid rgba(255,255,255,.07);border-radius:.875rem;padding:1.25rem;}
    .badge{display:inline-flex;align-items:center;gap:.35rem;padding:.2rem .65rem;border-radius:9999px;font-size:.72rem;font-weight:600;}
    .badge-green{background:rgba(34,197,94,.1);color:#22c55e;border:1px solid rgba(34,197,94,.2);}
    .badge-rose{background:rgba(244,63,94,.1);color:#f43f5e;border:1px solid rgba(244,63,94,.2);}
    .badge-blue{background:rgba(56,189,248,.1);color:#38bdf8;border:1px solid rgba(56,189,248,.2);}
    .badge-amber{background:rgba(245,158,11,.1);color:#f59e0b;border:1px solid rgba(245,158,11,.2);}
    .mobile-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:39;}
    @media(max-width:768px){
        .sidebar{transform:translateX(-100%);transition:transform .25s;}
        .sidebar.open{transform:translateX(0);}
        .mobile-overlay.open{display:block;}
        .main-content{margin-left:0;}
        .topbar,.content{padding:.875rem 1rem;}
    }
    .info-row{display:flex;justify-content:space-between;align-items:center;padding:.75rem 0;border-bottom:1px solid rgba(255,255,255,.04);}
    .info-row:last-child{border-bottom:none;}
    .info-label{font-size:.75rem;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:#6b7280;}
    .info-value{font-size:.875rem;font-weight:500;color:#e2e8f0;text-align:right;}
    .info-mono{font-family:'SF Mono','Monaco','Consolas',monospace;font-size:.8rem;color:#38bdf8;}
    .copy-btn{background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);color:#9ca3af;padding:.25rem .5rem;border-radius:.375rem;font-size:.7rem;cursor:pointer;transition:all .15s;margin-left:.5rem;}
    .copy-btn:hover{background:rgba(255,255,255,.1);color:#e2e8f0;}
    .copy-btn.copied{background:rgba(34,197,94,.1);color:#22c55e;border-color:rgba(34,197,94,.3);}
    .success-icon{width:80px;height:80px;background:rgba(34,197,94,.1);border:2px solid rgba(34,197,94,.3);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 1.5rem;font-size:2.5rem;color:#22c55e;animation:successPulse 2s ease-in-out infinite;}
    @keyframes successPulse{0%,100%{transform:scale(1);opacity:1;}50%{transform:scale(1.05);opacity:.8;}}
    .action-btn{display:inline-flex;align-items:center;gap:.5rem;padding:.75rem 1.5rem;border-radius:.625rem;font-size:.875rem;font-weight:600;text-decoration:none;transition:all .15s;border:1px solid transparent;cursor:pointer;}
    .action-btn-primary{background:#f43f5e;color:#fff;}
    .action-btn-primary:hover{background:#e11d48;}
    .action-btn-secondary{background:rgba(255,255,255,.05);color:#e2e8f0;border-color:rgba(255,255,255,.1);}
    .action-btn-secondary:hover{background:rgba(255,255,255,.1);}
    .action-btn-blue{background:rgba(56,189,248,.1);color:#38bdf8;border-color:rgba(56,189,248,.2);}
    .action-btn-blue:hover{background:rgba(56,189,248,.2);}
    .action-btn-green{background:rgba(34,197,94,.1);color:#22c55e;border-color:rgba(34,197,94,.2);}
    .action-btn-green:hover{background:rgba(34,197,94,.2);}
</style>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<script>
    function toggleSidebar(){document.getElementById('sidebar').classList.toggle('open');document.getElementById('overlay').classList.toggle('open');}
    function copyText(text, btn){
        navigator.clipboard.writeText(text).then(()=>{
            btn.classList.add('copied');
            btn.innerHTML='<i class="fas fa-check"></i> Copié';
            setTimeout(()=>{btn.classList.remove('copied');btn.innerHTML='<i class="fas fa-copy"></i>';},2000);
        });
    }
</script>

<!-- ══ MAIN ══ -->
<div class="main-content">
    <div class="sticky top-0 z-30 flex items-center justify-between border-b border-white/[.06] bg-[#111318] px-7 py-3.5">
        <div class="flex items-center gap-3">
            <<button id="adminSidebarToggle" class="md:hidden text-gray-400 hover:text-white text-lg w-8" aria-label="Ouvrir le menu admin">
    <i class="fas fa-bars"></i>
</button>
            <div>
                <div class="text-sm font-bold text-white">Serveur créé avec succès</div>
                <div class="text-xs text-gray-500">Provisionnement terminé</div>
            </div>
        </div>
        <a href="/admin/?view=servers" class="flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold text-gray-400 hover:bg-white/5 hover:text-white">
            <i class="fas fa-arrow-left text-[10px]"></i> Liste des serveurs
        </a>
    </div>

    <div class="max-w-[900px] mx-auto p-7">
        
        <!-- En-tête succès -->
        <div class="text-center mb-8">
            <div class="success-icon">
                <i class="fas fa-check"></i>
            </div>
            <h1 class="text-2xl font-black text-white mb-2">Serveur déployé avec succès !</h1>
            <p class="text-gray-400 text-sm">Le serveur a été créé et est prêt à être utilisé par le client.</p>
        </div>

        <!-- Grille d'infos -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            
            <!-- Carte 1 : Informations commande -->
            <div class="card p-6">
                <h3 class="flex items-center gap-2 text-sm font-bold text-white mb-4">
                    <i class="fas fa-receipt text-rose-500"></i> Informations commande
                </h3>
                <div class="info-row">
                    <span class="info-label">N° Commande</span>
                    <span class="info-value info-mono">
                        #<?= htmlspecialchars($order['order_id']) ?>
                        <button class="copy-btn" onclick="copyText('<?= htmlspecialchars($order['order_id']) ?>', this)"><i class="fas fa-copy"></i></button>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">Statut</span>
                    <span class="info-value">
                        <span class="badge badge-green"><i class="fas fa-check-circle"></i> Payé</span>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">Date de création</span>
                    <span class="info-value"><?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></span>
                </div>
                <?php if ($order['next_payment_date']): ?>
                <div class="info-row">
                    <span class="info-label">Prochain paiement</span>
                    <span class="info-value text-amber-400"><?= date('d/m/Y', strtotime($order['next_payment_date'])) ?></span>
                </div>
                <?php endif; ?>
                <?php if ($order['expires_at']): ?>
                <div class="info-row">
                    <span class="info-label">Expiration</span>
                    <span class="info-value text-rose-400"><?= date('d/m/Y', strtotime($order['expires_at'])) ?></span>
                </div>
                <?php endif; ?>
            </div>

            <!-- Carte 2 : Informations client -->
            <div class="card p-6">
                <h3 class="flex items-center gap-2 text-sm font-bold text-white mb-4">
                    <i class="fas fa-user text-sky-500"></i> Client
                </h3>
                <div class="info-row">
                    <span class="info-label">Nom</span>
                    <span class="info-value"><?= htmlspecialchars($client_name) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Email</span>
                    <span class="info-value">
                        <?= htmlspecialchars($order['email']) ?>
                        <button class="copy-btn" onclick="copyText('<?= htmlspecialchars($order['email']) ?>', this)"><i class="fas fa-copy"></i></button>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">ID Client</span>
                    <span class="info-value info-mono">#<?= $order['user_id'] ?></span>
                </div>
                <?php if ($invoice): ?>
                <div class="info-row">
                    <span class="info-label">Facture</span>
                    <span class="info-value">
                        <a href="/client/billing/invoice/?id=<?= urlencode($invoice['invoice_id']) ?>" target="_blank" class="text-emerald-400 hover:text-emerald-300 flex items-center gap-1">
                            <i class="fas fa-file-invoice"></i> <?= htmlspecialchars($invoice['invoice_id']) ?>
                        </a>
                    </span>
                </div>
                <?php endif; ?>
            </div>

            <!-- Carte 3 : Informations serveur -->
            <div class="card p-6">
                <h3 class="flex items-center gap-2 text-sm font-bold text-white mb-4">
                    <i class="fas fa-server text-purple-500"></i> Serveur
                </h3>
                <div class="info-row">
                    <span class="info-label">Produit</span>
                    <span class="info-value font-bold"><?= htmlspecialchars($order['product_name'] ?? $order['service_name']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Node</span>
                    <span class="info-value"><?= htmlspecialchars($order['node_name'] ?? 'N/A') ?></span>
                </div>
                <?php if ($order['node_fqdn']): ?>
                <div class="info-row">
                    <span class="info-label">Adresse</span>
                    <span class="info-value info-mono text-xs"><?= htmlspecialchars($order['node_fqdn']) ?></span>
                </div>
                <?php endif; ?>
                <div class="info-row">
                    <span class="info-label">UUID</span>
                    <span class="info-value info-mono text-xs">
                        <?= htmlspecialchars(substr($order['uuid'] ?? '', 0, 8)) ?>...
                        <button class="copy-btn" onclick="copyText('<?= htmlspecialchars($order['uuid']) ?>', this)"><i class="fas fa-copy"></i></button>
                    </span>
                </div>
                <?php if ($order['id_server_panel']): ?>
                <div class="info-row">
                    <span class="info-label">ID Panel</span>
                    <span class="info-value info-mono">
                        <?= htmlspecialchars($order['id_server_panel']) ?>
                        <button class="copy-btn" onclick="copyText('<?= htmlspecialchars($order['id_server_panel']) ?>', this)"><i class="fas fa-copy"></i></button>
                    </span>
                </div>
                <?php endif; ?>
            </div>

            <!-- Carte 4 : Ressources -->
            <div class="card p-6">
                <h3 class="flex items-center gap-2 text-sm font-bold text-white mb-4">
                    <i class="fas fa-microchip text-amber-500"></i> Ressources allouées
                </h3>
                <div class="info-row">
                    <span class="info-label">RAM</span>
                    <span class="info-value"><?= number_format($order['ram'] / 1024, 1) ?> GB</span>
                </div>
                <div class="info-row">
                    <span class="info-label">CPU</span>
                    <span class="info-value"><?= $order['cpu'] ?>%</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Disque</span>
                    <span class="info-value"><?= number_format($order['disk'] / 1024, 1) ?> GB</span>
                </div>
                <?php if ($order['databases']): ?>
                <div class="info-row">
                    <span class="info-label">Bases de données</span>
                    <span class="info-value"><?= $order['databases'] ?></span>
                </div>
                <?php endif; ?>
                <?php if ($order['backups']): ?>
                <div class="info-row">
                    <span class="info-label">Backups</span>
                    <span class="info-value"><?= $order['backups'] ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Carte mot de passe (si disponible) -->
        <?php if ($panel_pass): ?>
        <div class="card p-6 mb-6 border-amber-500/20 bg-amber-500/[.03]">
            <h3 class="flex items-center gap-2 text-sm font-bold text-amber-400 mb-4">
                <i class="fas fa-key"></i> Mot de passe Panel (à transmettre au client)
            </h3>
            <div class="flex items-center justify-between bg-black/30 rounded-lg p-4 border border-amber-500/10">
                <code class="text-lg font-mono text-amber-300"><?= htmlspecialchars($panel_pass) ?></code>
                <button class="copy-btn" onclick="copyText('<?= htmlspecialchars($panel_pass) ?>', this)"><i class="fas fa-copy"></i> Copier</button>
            </div>
            <p class="text-xs text-gray-500 mt-3">
                <i class="fas fa-info-circle"></i> Communiquez ce mot de passe au client par email ou messagerie sécurisée.
            </p>
        </div>
        <?php endif; ?>

        <!-- Actions -->
        <div class="card p-6">
            <h3 class="flex items-center gap-2 text-sm font-bold text-white mb-4">
                <i class="fas fa-bolt text-rose-500"></i> Actions rapides
            </h3>
            <div class="flex flex-wrap gap-3">
                <a href="<?= htmlspecialchars($panel_url) ?>/admin/servers/view/<?= $order['server_id'] ?>" target="_blank" class="action-btn action-btn-primary">
                    <i class="fas fa-external-link-alt"></i> Voir dans le Panel
                </a>
                <a href="/admin/?view=servers" class="action-btn action-btn-secondary">
                    <i class="fas fa-list"></i> Liste des serveurs
                </a>
                <a href="/admin/servers/create/" class="action-btn action-btn-blue">
                    <i class="fas fa-plus"></i> Créer un autre serveur
                </a>
                <?php if ($invoice): ?>
                <a href="/client/billing/invoice/?id=<?= urlencode($invoice['invoice_id']) ?>" target="_blank" class="action-btn action-btn-green">
                    <i class="fas fa-file-invoice"></i> Voir la facture
                </a>
                <?php endif; ?>
                <a href="mailto:<?= htmlspecialchars($order['email']) ?>?subject=Votre serveur OrinHeberge est prêt&body=Bonjour,%0D%0A%0D%0AVotre serveur <?= htmlspecialchars($order['service_name']) ?> a été créé avec succès.%0D%0A%0D%0AVoici vos identifiants de connexion au panel :%0D%0AURL : <?= htmlspecialchars($panel_url) ?>%0D%0AEmail : <?= htmlspecialchars($order['email']) ?>%0D%0AMot de passe : <?= htmlspecialchars($panel_pass ?? '[à définir]') ?>%0D%0A%0D%0ACordialement,%0D%0AL'équipe OrinHeberge" class="action-btn action-btn-secondary">
                    <i class="fas fa-envelope"></i> Email au client
                </a>
            </div>
        </div>

        <!-- Retour -->
        <div class="text-center mt-6">
            <a href="/admin/?view=servers" class="text-sm text-gray-500 hover:text-gray-300 transition">
                <i class="fas fa-arrow-left"></i> Retour à la liste des serveurs
            </a>
        </div>

    </div>
</div>

</body>
</html>
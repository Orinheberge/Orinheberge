<?php
/**
 * inc/admin_layout.php
 * Inclure en tête de chaque page admin après avoir défini $active_nav
 * Requiert : $pdo, $_SESSION['user_id'], $admin (array), $panel_url
 */
$active_nav = $active_nav ?? '';
$admin_username = !empty($admin['pseudo']) ? $admin['pseudo'] : $admin['firstname'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin — OrinHeberge</title>
<link rel="icon" type="image/png" href="/favicon.ico">
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
:root{--sidebar:240px;}
*{box-sizing:border-box;}
body{background:#0d0f14;color:#e2e8f0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',system-ui,sans-serif;min-height:100vh;}
.sidebar{position:fixed;top:0;left:0;width:var(--sidebar);height:100vh;background:#111318;border-right:1px solid rgba(255,255,255,.06);display:flex;flex-direction:column;z-index:40;overflow-y:auto;}
.sidebar-logo{padding:1.5rem 1.25rem 1rem;border-bottom:1px solid rgba(255,255,255,.05);}
.sidebar-nav{padding:.75rem;flex:1;}
.nav-item{display:flex;align-items:center;gap:.75rem;padding:.6rem .875rem;border-radius:.6rem;font-size:.82rem;font-weight:500;color:#6b7280;transition:all .15s;text-decoration:none;margin-bottom:.15rem;border:1px solid transparent;}
.nav-item:hover{background:rgba(255,255,255,.04);color:#d1d5db;}
.nav-item.active{background:rgba(56,189,248,.08);color:#38bdf8;border-color:rgba(56,189,248,.15);}
.nav-item.danger{color:#f43f5e;}.nav-item.danger:hover{background:rgba(244,63,94,.08);}
.nav-item .icon{width:1.1rem;text-align:center;font-size:.85rem;flex-shrink:0;}
.nav-section{font-size:.63rem;font-weight:700;letter-spacing:.1em;color:#374151;text-transform:uppercase;padding:.75rem .875rem .3rem;}
.nav-sep{height:1px;background:rgba(255,255,255,.05);margin:.5rem .75rem;}
.sidebar-footer{padding:.875rem 1rem;border-top:1px solid rgba(255,255,255,.05);}
.main-content{margin-left:var(--sidebar);min-height:100vh;display:flex;flex-direction:column;}
.topbar{background:#111318;border-bottom:1px solid rgba(255,255,255,.06);padding:.875rem 1.75rem;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:30;}
.content{padding:1.75rem;flex:1;}
.card{background:#161a22;border:1px solid rgba(255,255,255,.07);border-radius:.875rem;}
.badge{display:inline-flex;align-items:center;gap:.3rem;padding:.2rem .65rem;border-radius:9999px;font-size:.7rem;font-weight:600;}
.badge-green{background:rgba(34,197,94,.1);color:#22c55e;border:1px solid rgba(34,197,94,.2);}
.badge-red{background:rgba(239,68,68,.1);color:#ef4444;border:1px solid rgba(239,68,68,.2);}
.badge-gray{background:rgba(107,114,128,.1);color:#9ca3af;border:1px solid rgba(107,114,128,.2);}
.badge-blue{background:rgba(56,189,248,.1);color:#38bdf8;border:1px solid rgba(56,189,248,.2);}
.badge-amber{background:rgba(245,158,11,.1);color:#f59e0b;border:1px solid rgba(245,158,11,.2);}
.btn{display:inline-flex;align-items:center;gap:.4rem;padding:.45rem 1rem;border-radius:.5rem;font-size:.8rem;font-weight:600;cursor:pointer;border:none;transition:all .15s;}
.btn-primary{background:#0ea5e9;color:#fff;}.btn-primary:hover{background:#38bdf8;}
.btn-danger{background:rgba(239,68,68,.15);color:#ef4444;border:1px solid rgba(239,68,68,.3);}.btn-danger:hover{background:#ef4444;color:#fff;}
.btn-ghost{background:rgba(255,255,255,.05);color:#9ca3af;border:1px solid rgba(255,255,255,.08);}.btn-ghost:hover{background:rgba(255,255,255,.1);color:#fff;}
.input{background:#0d0f14;border:1px solid rgba(255,255,255,.1);border-radius:.5rem;padding:.5rem .875rem;font-size:.85rem;color:#e2e8f0;width:100%;transition:border-color .15s;outline:none;}
.input:focus{border-color:#38bdf8;}
.tbl{width:100%;border-collapse:collapse;}
.tbl th{font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#4b5563;padding:.75rem 1.25rem;text-align:left;border-bottom:1px solid rgba(255,255,255,.05);}
.tbl td{padding:.875rem 1.25rem;font-size:.84rem;border-bottom:1px solid rgba(255,255,255,.035);vertical-align:middle;}
.tbl tr:last-child td{border-bottom:none;}
.tbl tr:hover td{background:rgba(255,255,255,.015);}
@media(max-width:768px){.sidebar{transform:translateX(-100%);transition:transform .25s;}.sidebar.open{transform:translateX(0);}.main-content{margin-left:0;}.content{padding:1rem;}}
</style>
</head>
<body>
<aside id="sidebar" class="sidebar">
  <div class="sidebar-logo">
    <a href="/admin/" class="flex items-center gap-2.5">
      <div class="w-8 h-8 rounded-lg bg-rose-500/20 flex items-center justify-center"><i class="fas fa-shield-alt text-rose-400 text-sm"></i></div>
      <div><div class="font-black text-white text-sm tracking-tight">OrinHeberge</div><div class="text-[10px] text-rose-400 font-semibold">Administration</div></div>
    </a>
  </div>
  <nav class="sidebar-nav">
    <div class="nav-section">Tableau de bord</div>
    <a href="/admin/" class="nav-item <?= $active_nav==='dashboard'?'active':'' ?>"><i class="fas fa-home icon"></i> Vue d'ensemble</a>

    <div class="nav-sep"></div>
    <div class="nav-section">Infrastructure</div>
    <a href="/admin/nodes/" class="nav-item <?= $active_nav==='nodes'?'active':'' ?>"><i class="fas fa-network-wired icon"></i> Nodes</a>
    <a href="/admin/eggs/" class="nav-item <?= $active_nav==='eggs'?'active':'' ?>"><i class="fas fa-egg icon"></i> Eggs</a>

    <div class="nav-sep"></div>
    <div class="nav-section">Boutique</div>
    <a href="/admin/products/" class="nav-item <?= $active_nav==='products'?'active':'' ?>"><i class="fas fa-box icon"></i> Produits</a>
    <a href="/admin/extensions/" class="nav-item <?= $active_nav==='extensions'?'active':'' ?>"><i class="fas fa-puzzle-piece icon"></i> Extensions</a>

    <div class="nav-sep"></div>
    <div class="nav-section">Clients</div>
    <a href="/admin/?view=clients" class="nav-item <?= $active_nav==='clients'?'active':'' ?>"><i class="fas fa-users icon"></i> Clients</a>
    <a href="/admin/?view=servers" class="nav-item <?= $active_nav==='servers'?'active':'' ?>"><i class="fas fa-server icon"></i> Serveurs</a>
    <a href="/support/admin_tickets/" class="nav-item <?= $active_nav==='tickets'?'active':'' ?>"><i class="fas fa-ticket-alt icon"></i> Tickets</a>

    <div class="nav-sep"></div>
    <div class="nav-section">Système</div>
    <a href="/admin/?view=settings" class="nav-item <?= $active_nav==='settings'?'active':'' ?>"><i class="fas fa-sliders-h icon"></i> Paramètres</a>
    <a href="/admin/?view=email" class="nav-item <?= $active_nav==='email'?'active':'' ?>"><i class="fas fa-envelope icon"></i> Envoyer Email</a>

    <div class="nav-sep"></div>
    <a href="/client/" class="nav-item"><i class="fas fa-arrow-left icon"></i> Espace Client</a>
  </nav>
  <div class="sidebar-footer">
    <div class="flex items-center gap-2.5 mb-2">
      <div class="w-8 h-8 rounded-full bg-rose-500/20 flex items-center justify-center text-rose-400 text-xs font-bold border border-white/10">
        <?= strtoupper(substr($admin_username, 0, 1)) ?>
      </div>
      <div class="flex-1 min-w-0">
        <div class="text-xs font-semibold text-white truncate"><?= htmlspecialchars($admin_username) ?></div>
        <div class="text-[10px] text-rose-400">Administrateur</div>
      </div>
    </div>
    <a href="/logout/" class="nav-item danger"><i class="fas fa-sign-out-alt icon"></i> Déconnexion</a>
  </div>
</aside>
<?php // La page inclut le topbar et le contenu elle-même ?>

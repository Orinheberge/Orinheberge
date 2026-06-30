<?php
/**
 * inc/admin_layout.php — Layout HTML complet pour les pages admin
 * Requiert avant l'inclusion : $admin, $active_nav, $page_title (optionnel)
 */
$active_nav  = $active_nav  ?? '';
$page_title  = $page_title  ?? 'Admin';
$admin_username = !empty($admin['pseudo']) ? $admin['pseudo'] : ($admin['firstname'] ?? 'Admin');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($page_title) ?> — OrinHeberge Admin</title>
<link rel="icon" type="image/png" href="/favicon.ico">
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<style>
:root{--sidebar:240px;}
*{box-sizing:border-box;}
body{background:#0d0f14;color:#e2e8f0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',system-ui,sans-serif;min-height:100vh;}
/* Sidebar */
.sidebar{position:fixed;top:0;left:0;width:var(--sidebar);height:100vh;background:#111318;border-right:1px solid rgba(255,255,255,.06);display:flex;flex-direction:column;z-index:40;overflow-y:auto;}
.sidebar-logo{padding:1.5rem 1.25rem 1rem;border-bottom:1px solid rgba(255,255,255,.05);}
.sidebar-nav{padding:.75rem;flex:1;}
.nav-item{display:flex;align-items:center;gap:.75rem;padding:.625rem .875rem;border-radius:.625rem;font-size:.82rem;font-weight:500;color:#6b7280;transition:all .15s;text-decoration:none;margin-bottom:.15rem;border:1px solid transparent;}
.nav-item:hover{background:rgba(255,255,255,.04);color:#d1d5db;}
.nav-item.active{background:rgba(244,63,94,.08);color:#f43f5e;border-color:rgba(244,63,94,.15);}
.nav-item .icon{width:1.1rem;text-align:center;font-size:.85rem;flex-shrink:0;}
.nav-section{font-size:.65rem;font-weight:700;letter-spacing:.1em;color:#374151;text-transform:uppercase;padding:.75rem .875rem .35rem;}
.nav-separator{height:1px;background:rgba(255,255,255,.05);margin:.5rem .75rem;}
.sidebar-footer{padding:.875rem 1rem;border-top:1px solid rgba(255,255,255,.05);}
/* Main */
.main-content{margin-left:var(--sidebar);min-height:100vh;display:flex;flex-direction:column;}
.topbar{background:#111318;border-bottom:1px solid rgba(255,255,255,.06);padding:.875rem 1.75rem;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:30;}
.content{padding:1.75rem;flex:1;}
/* Cards */
.card{background:#161a22;border:1px solid rgba(255,255,255,.07);border-radius:.875rem;}
.stat-card{background:linear-gradient(135deg,#161a22,#1a1f2a);border:1px solid rgba(255,255,255,.07);border-radius:.875rem;padding:1.25rem;}
/* Badges */
.badge{display:inline-flex;align-items:center;gap:.35rem;padding:.2rem .65rem;border-radius:9999px;font-size:.72rem;font-weight:600;}
.badge-green{background:rgba(34,197,94,.1);color:#22c55e;border:1px solid rgba(34,197,94,.2);}
.badge-orange{background:rgba(249,115,22,.1);color:#f97316;border:1px solid rgba(249,115,22,.2);}
.badge-red{background:rgba(239,68,68,.1);color:#ef4444;border:1px solid rgba(239,68,68,.2);}
.badge-gray{background:rgba(107,114,128,.1);color:#9ca3af;border:1px solid rgba(107,114,128,.2);}
.badge-blue{background:rgba(56,189,248,.1);color:#38bdf8;border:1px solid rgba(56,189,248,.2);}
.badge-rose{background:rgba(244,63,94,.1);color:#f43f5e;border:1px solid rgba(244,63,94,.2);}
.badge-amber{background:rgba(245,158,11,.1);color:#f59e0b;border:1px solid rgba(245,158,11,.2);}
/* Buttons */
.btn{display:inline-flex;align-items:center;gap:.4rem;padding:.45rem 1rem;border-radius:.5rem;font-size:.8rem;font-weight:600;cursor:pointer;border:none;transition:all .15s;text-decoration:none;}
.btn-primary{background:#0ea5e9;color:#fff;}.btn-primary:hover{background:#38bdf8;}
.btn-danger{background:rgba(239,68,68,.15);color:#ef4444;border:1px solid rgba(239,68,68,.3);}.btn-danger:hover{background:#ef4444;color:#fff;}
.btn-ghost{background:rgba(255,255,255,.05);color:#9ca3af;border:1px solid rgba(255,255,255,.08);}.btn-ghost:hover{background:rgba(255,255,255,.1);color:#fff;}
.btn-action{display:inline-flex;align-items:center;gap:.35rem;padding:.3rem .75rem;border-radius:.5rem;font-size:.75rem;font-weight:600;transition:all .15s;border:1px solid transparent;cursor:pointer;text-decoration:none;}
.btn-red{background:rgba(239,68,68,.1);color:#ef4444;border-color:rgba(239,68,68,.2);}.btn-red:hover{background:rgba(239,68,68,.25);}
.btn-blue{background:rgba(56,189,248,.1);color:#38bdf8;border-color:rgba(56,189,248,.2);}.btn-blue:hover{background:rgba(56,189,248,.25);}
.btn-sky{background:rgba(14,165,233,.1);color:#0ea5e9;border-color:rgba(14,165,233,.2);}.btn-sky:hover{background:rgba(14,165,233,.25);}
.btn-orange{background:rgba(249,115,22,.1);color:#f97316;border-color:rgba(249,115,22,.2);}.btn-orange:hover{background:rgba(249,115,22,.25);}
/* Inputs */
.input{background:#0d0f14;border:1px solid rgba(255,255,255,.1);border-radius:.5rem;padding:.5rem .875rem;font-size:.85rem;color:#e2e8f0;width:100%;transition:border-color .15s;outline:none;}
.input:focus{border-color:#f43f5e;}
input[type=text],input[type=email],input[type=password],input[type=url],input[type=number],textarea,select{background:#1e2330 !important;border:1px solid rgba(255,255,255,.08) !important;color:#e2e8f0 !important;border-radius:.625rem;padding:.6rem .875rem;font-size:.83rem;width:100%;outline:none;transition:border-color .15s;}
input:focus,textarea:focus,select:focus{border-color:rgba(244,63,94,.4) !important;}
/* Table */
.tbl{width:100%;border-collapse:collapse;}
.tbl th{font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#4b5563;padding:.75rem 1.25rem;text-align:left;border-bottom:1px solid rgba(255,255,255,.05);}
.tbl td{padding:.875rem 1.25rem;font-size:.84rem;border-bottom:1px solid rgba(255,255,255,.035);vertical-align:middle;}
.tbl tr:last-child td{border-bottom:none;}
.tbl tr:hover td{background:rgba(255,255,255,.015);}
/* Mobile */
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
function confirmDel(msg){return confirm('⚠️ '+msg+'\nCette action est irréversible.');}
</script>
</head>
<body>
<div id="overlay" class="mobile-overlay" onclick="toggleSidebar()"></div>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/inc/admin_sidebar.php'; ?>

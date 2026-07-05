<?php
session_start();
include $_SERVER['DOCUMENT_ROOT'] . '/inc/admin_sidebar.php';
include $_SERVER['DOCUMENT_ROOT'] . '/inc/db.php';

require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/lang.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/db.php';

if (!isset($_SESSION['user_id'])) { header('Location: /login/'); exit(); }
$chk = $pdo->prepare('SELECT is_admin, pseudo, firstname, avatar FROM users WHERE id=? LIMIT 1');
$chk->execute([$_SESSION['user_id']]);
$admin = $chk->fetch();
if (!$admin || !$admin['is_admin']) { http_response_code(403); die('403 — Accès refusé.'); }
$_SESSION['username'] = !empty($admin['pseudo']) ? $admin['pseudo'] : $admin['firstname'];
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
        .btn-action{display:inline-flex;align-items:center;gap:.35rem;padding:.3rem .75rem;border-radius:.5rem;font-size:.75rem;font-weight:600;transition:all .15s;border:1px solid transparent;cursor:pointer;}
        .btn-red{background:rgba(239,68,68,.1);color:#ef4444;border-color:rgba(239,68,68,.2);}
        .btn-red:hover{background:rgba(239,68,68,.2);}
        .btn-orange{background:rgba(249,115,22,.1);color:#f97316;border-color:rgba(249,115,22,.2);}
        .btn-orange:hover{background:rgba(249,115,22,.2);}
        .btn-blue{background:rgba(56,189,248,.1);color:#38bdf8;border-color:rgba(56,189,248,.2);}
        .btn-blue:hover{background:rgba(56,189,248,.2);}
        .btn-sky{background:rgba(14,165,233,.1);color:#0ea5e9;border-color:rgba(14,165,233,.2);}
        .btn-sky:hover{background:rgba(14,165,233,.2);}
        .mobile-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:39;}
        @media(max-width:768px){
            .sidebar{transform:translateX(-100%);transition:transform .25s;}
            .sidebar.open{transform:translateX(0);}
            .mobile-overlay.open{display:block;}
            .main-content{margin-left:0;}
            .topbar,.content{padding:.875rem 1rem;}
        }
    </style>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script>
        function toggleSidebar(){document.getElementById('sidebar').classList.toggle('open');document.getElementById('overlay').classList.toggle('open');}
        function confirmDel(msg){return confirm('⚠️ '+msg+'\nCette action est irréversible.');}
        function openEmail(email){document.getElementById('modal-email').classList.remove('hidden');document.getElementById('email-to').value=email;}
        function closeEmail(){document.getElementById('modal-email').classList.add('hidden');}
    </script>
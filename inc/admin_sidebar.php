<?php
/**
 * inc/admin_sidebar.php — Sidebar partagée pour TOUTES les pages admin
 */

$_ideas_todo = $pdo->query("SELECT COUNT(*) FROM ideas WHERE statut='todo'")->fetchColumn();
$admin_username  = !empty($admin['pseudo']) ? $admin['pseudo'] : ($admin['firstname'] ?? 'Admin');
$_users_count    = isset($all_users)   ? count($all_users)   : ($pdo->query('SELECT COUNT(*) FROM users')->fetchColumn());
$_servers_count  = isset($all_servers) ? count($all_servers) : ($pdo->query("SELECT COUNT(*) FROM orders WHERE status='paid'")->fetchColumn());
$_tickets_count  = isset($open_tickets) ? $open_tickets      : ($pdo->query("SELECT COUNT(*) FROM support_tickets WHERE status != 'Fermé'")->fetchColumn());

// 🔵 Compteur de maintenances actives
$_maintenance_count = $pdo->query("
    SELECT COUNT(*) FROM maintenance 
    WHERE is_active = 1 
      AND status IN ('scheduled', 'in_progress')
      AND end_date >= NOW()
")->fetchColumn();

$_cahier_count = $pdo->query("
    SELECT COUNT(*) FROM cahier_charges 
    WHERE statut IN ('draft', 'in_progress', 'review')
")->fetchColumn();

// 🔵 Compteur de cahiers en retard
$_cahier_overdue = $pdo->query("
    SELECT COUNT(*) FROM cahier_charges 
    WHERE date_limite < CURDATE() 
      AND statut NOT IN ('completed', 'archived')
")->fetchColumn();

// 🔵 Compteur de factures en attente
$_inv_pending_count = $pdo->query("SELECT COUNT(*) FROM invoices WHERE status='pending'")->fetchColumn();

// 🔵 Compteur d'utilisateurs inscrits récemment (dernières 24h)
// Utilise created_at au lieu de last_login (qui n'existe pas)
$_recent_users_count = $pdo->query("
    SELECT COUNT(*) FROM users 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
")->fetchColumn();

// 🔵 Vérifier si une maintenance critique est en cours
$_critical_maintenance = $pdo->query("
    SELECT id, title FROM maintenance 
    WHERE is_active = 1 
      AND severity = 'critical'
      AND status = 'in_progress'
      AND NOW() BETWEEN start_date AND end_date
    LIMIT 1
")->fetch();
?>

<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>

<style>
    @keyframes pulse-critical {
        0%, 100% { opacity: 1; transform: scale(1); }
        50% { opacity: 0.6; transform: scale(1.1); }
    }
    .critical-pulse {
        animation: pulse-critical 1.5s infinite;
    }
</style>

<aside id="sidebar" class="sidebar">
  
  <div class="sidebar-logo">
    <a href="/admin/" class="flex items-center gap-2.5">
      <div class="w-8 h-8 rounded-lg bg-rose-500/20 flex items-center justify-center shrink-0">
        <i class="fas fa-shield-alt text-rose-400 text-sm"></i>
      </div>
      <div>
        <div class="font-black text-white text-sm tracking-tight leading-tight">OrinHeberge</div>
        <div class="text-[10px] text-rose-400 font-semibold">Admin Panel</div>
      </div>
    </a>
  </div>

  <?php if ($_critical_maintenance): ?>
  <div class="mx-3 mb-3 p-3 rounded-xl bg-red-500/10 border border-red-500/30">
    <div class="flex items-center gap-2 text-xs">
      <i class="fas fa-radiation text-red-400 critical-pulse"></i>
      <span class="text-red-400 font-semibold truncate flex-1"><?php echo htmlspecialchars($_critical_maintenance['title']); ?></span>
    </div>
    <div class="text-[10px] text-gray-500 mt-1 ml-5">Maintenance critique en cours</div>
  </div>
  <?php endif; ?>

  <nav class="sidebar-nav">

    <div class="nav-section">Dashboard</div>
    
    <a href="/admin/" class="nav-item <?= ($active_nav === 'dashboard' || $active_nav === '') ? 'active' : '' ?>">
      <i class="fas fa-chart-bar icon"></i> Vue d'ensemble
    </a>
    
    <a href="/admin/annonce/" class="nav-item <?= $active_nav === 'announcements' ? 'active' : '' ?>">
      <i class="fas fa-bell icon"></i> Annonces
    </a>

    <div class="nav-separator"></div>

    <div class="nav-section">Infrastructure</div>

    <a href="/admin/nodes/" class="nav-item <?= $active_nav === 'nodes' ? 'active' : '' ?>">
      <i class="fas fa-network-wired icon"></i> Nodes
    </a>

    <a href="/admin/eggs/" class="nav-item <?= $active_nav === 'eggs' ? 'active' : '' ?>">
      <i class="fas fa-egg icon"></i> Eggs
    </a>

    <a href="/admin/maintenance/" class="nav-item <?= $active_nav === 'maintenance' ? 'active' : '' ?>">
      <i class="fas fa-wrench icon"></i> Maintenances
      <?php if ($_maintenance_count > 0): ?>
        <span class="ml-auto text-[10px] bg-sky-500/15 text-sky-400 border border-sky-500/20 px-1.5 py-0.5 rounded-full font-bold"><?= $_maintenance_count ?></span>
      <?php endif; ?>
    </a>

    <div class="nav-separator"></div>

    <div class="nav-section">Boutique</div>

    <a href="/admin/products/" class="nav-item <?= $active_nav === 'products' ? 'active' : '' ?>">
      <i class="fas fa-box icon"></i> Produits
    </a>

    <a href="/admin/extensions/" class="nav-item <?= $active_nav === 'extensions' ? 'active' : '' ?>">
      <i class="fas fa-puzzle-piece icon"></i> Extensions
    </a>

    <a href="/admin/categories/" class="nav-item <?= $active_nav === 'categories' ? 'active' : '' ?>">
      <i class="fas fa-folder icon"></i> Catégories
    </a>

    <a href="/admin/lang/" class="nav-item <?= $active_nav === 'lang' ? 'active' : '' ?>">
      <i class="fas fa-language icon"></i> Langues boutique
    </a>

    <div class="nav-separator"></div>

    <div class="nav-section">Gestion</div>

    <a href="/admin/servers/create/" class="nav-item <?= $active_nav === 'create_server' ? 'active' : '' ?>" style="<?= $active_nav === 'create_server' ? '' : 'color:#f43f5e;' ?>">
      <i class="fas fa-plus-circle icon"></i> Créer un serveur
    </a>

    <a href="/admin/?view=clients" class="nav-item <?= $active_nav === 'clients' ? 'active' : '' ?>">
      <i class="fas fa-users icon"></i> Clients
      <span class="ml-auto text-[10px] bg-white/5 text-gray-500 px-1.5 py-0.5 rounded-full"><?= $_users_count ?></span>
    </a>

    <a href="/admin/?view=servers" class="nav-item <?= $active_nav === 'servers' ? 'active' : '' ?>">
      <i class="fas fa-server icon"></i> Serveurs
      <span class="ml-auto text-[10px] bg-white/5 text-gray-500 px-1.5 py-0.5 rounded-full"><?= $_servers_count ?></span>
    </a>

    <a href="/admin/?view=invoices" class="nav-item <?= $active_nav === 'invoices' ? 'active' : '' ?>">
      <i class="fas fa-file-invoice-dollar icon"></i> Factures
      <?php if ($_inv_pending_count > 0): ?>
        <span class="ml-auto text-[10px] bg-amber-500/15 text-amber-400 border border-amber-500/20 px-1.5 py-0.5 rounded-full font-bold"><?= $_inv_pending_count ?></span>
      <?php endif; ?>
    </a>

    <a href="/support/admin_tickets/" class="nav-item <?= $active_nav === 'tickets' ? 'active' : '' ?>">
      <i class="fas fa-ticket-alt icon"></i> Tickets
      <?php if ($_tickets_count > 0): ?>
        <span class="ml-auto text-[10px] bg-rose-500/15 text-rose-400 border border-rose-500/20 px-1.5 py-0.5 rounded-full font-bold"><?= $_tickets_count ?></span>
      <?php endif; ?>
    </a>

    <a href="/admin/?view=email" class="nav-item <?= $active_nav === 'email' ? 'active' : '' ?>">
      <i class="fas fa-envelope icon"></i> Emails
    </a>

    <div class="nav-separator"></div>

    <div class="nav-section">Configuration</div>

    <a href="/admin/?view=settings" class="nav-item <?= $active_nav === 'settings' ? 'active' : '' ?>">
      <i class="fas fa-sliders-h icon"></i> Paramètres
    </a>

    <div class="nav-separator"></div>

    <div class="nav-section">Espace Client</div>

    <a href="/client/" class="nav-item">
      <i class="fas fa-home icon"></i> Mon tableau de bord
    </a>

    <a href="/client/servers/" class="nav-item">
      <i class="fas fa-server icon"></i> Mes serveurs
    </a>

    <a href="/client/billing/" class="nav-item">
      <i class="fas fa-file-invoice-dollar icon"></i> Facturation
    </a>

    <a href="/admin/ideas/" class="nav-item <?= $active_nav === 'ideas' ? 'active' : '' ?>">
      <i class="fas fa-lightbulb icon" style="color:#facc15;"></i> Mes idées
      <?php if ($_ideas_todo > 0): ?>
        <span class="ml-auto text-[10px] bg-yellow-500/15 text-yellow-400 border border-yellow-500/20 px-1.5 py-0.5 rounded-full font-bold"><?= $_ideas_todo ?></span>
      <?php endif; ?>
    </a>

    <a href="/admin/cahier-charges/" class="nav-item <?= $active_nav === 'cahier_charges' ? 'active' : '' ?>">
      <i class="fas fa-file-contract icon" style="color:#818cf8;"></i> Cahier des charges
      <?php if ($_cahier_overdue > 0): ?>
        <span class="ml-auto text-[10px] bg-red-500/15 text-red-400 border border-red-500/20 px-1.5 py-0.5 rounded-full font-bold" title="<?= $_cahier_overdue ?> cahier(s) en retard">
          <i class="fas fa-exclamation-triangle text-[8px] mr-0.5"></i><?= $_cahier_overdue ?>
        </span>
      <?php elseif ($_cahier_count > 0): ?>
        <span class="ml-auto text-[10px] bg-indigo-500/15 text-indigo-400 border border-indigo-500/20 px-1.5 py-0.5 rounded-full font-bold"><?= $_cahier_count ?></span>
      <?php endif; ?>
    </a>


  </nav>

  <div class="sidebar-footer">
    
    <div class="mb-3 px-1">
      <?php if (file_exists(__DIR__ . '/lang_switcher.php')): ?>
        <?php include __DIR__ . '/lang_switcher.php'; ?>
      <?php else: ?>
        <div class="flex items-center gap-2 text-xs text-gray-500">
          <i class="fas fa-globe"></i>
          <span>Langue : <?php echo strtoupper($lang ?? 'FR'); ?></span>
        </div>
      <?php endif; ?>
    </div>

    <a href="/profil/" class="flex items-center gap-2.5 mb-2 group">
      <?php if (!empty($admin['avatar']) && file_exists($_SERVER['DOCUMENT_ROOT'].'/'.$admin['avatar'])): ?>
        <img src="/<?= htmlspecialchars($admin['avatar']) ?>" class="w-8 h-8 rounded-full object-cover border border-white/10">
      <?php else: ?>
        <div class="w-8 h-8 rounded-full bg-rose-500/20 flex items-center justify-center text-rose-400 text-xs font-bold border border-rose-500/20">
          <?= strtoupper(substr($admin_username, 0, 1)) ?>
        </div>
      <?php endif; ?>
      <div class="flex-1 min-w-0">
        <div class="text-xs font-semibold text-white truncate"><?= htmlspecialchars($admin_username) ?></div>
        <div class="text-[10px] text-rose-400 font-semibold">Administrateur</div>
      </div>
      <i class="fas fa-chevron-right text-[9px] text-gray-600 group-hover:text-gray-400 transition"></i>
    </a>
    
    <a href="/logout/" class="nav-item" style="color:#ef4444;">
      <i class="fas fa-sign-out-alt icon"></i> Déconnexion
    </a>

    <div class="mt-3 pt-3 border-t border-white/5 grid grid-cols-2 gap-2 text-center">
      <div class="bg-white/[0.02] rounded-lg p-2">
        <div class="text-[10px] text-gray-500">Inscrits 24h</div>
        <div class="text-sm font-bold text-emerald-400"><?= $_recent_users_count ?></div>
      </div>
      <div class="bg-white/[0.02] rounded-lg p-2">
        <div class="text-[10px] text-gray-500">Tickets</div>
        <div class="text-sm font-bold text-rose-400"><?= $_tickets_count ?></div>
      </div>
    </div>

    <div class="mt-3 pt-3 border-t border-white/5 flex items-center justify-center gap-3 text-[10px] text-gray-600">
      <span>v2.0.0</span>
      <span>·</span>
      <a href="/" target="_blank" class="hover:text-gray-400 transition">
        <i class="fas fa-external-link-alt"></i> Site
      </a>
      <span>·</span>
      <a href="/status/" target="_blank" class="hover:text-gray-400 transition">
        <i class="fas fa-signal"></i> Statut
      </a>
    </div>
    <div class="mt-3 pt-3 border-t border-white/5 flex items-center justify-center gap-3 text-[10px] text-gray-600">
      <a href="/mentions-legales/" class="hover:text-gray-400 transition">Mentions</a>
      <span>·</span>
      <a href="/cgu/" class="hover:text-gray-400 transition">CGU</a>
      <span>·</span>
      <a href="/politique-confidentialite/" class="hover:text-gray-400 transition">Confidentialité</a>
    </div>
  </div>
</aside>

<!-- Bouton toggle mobile (à placer en dehors du sidebar) -->
<button id="adminSidebarToggle" class="admin-sidebar-toggle md:hidden" aria-label="Toggle admin sidebar">
    <i class="fas fa-bars"></i>
</button>

<!-- Styles supplémentaires pour le toggle et le sidebar mobile -->
<style>
    /* Bouton toggle mobile */
    .admin-sidebar-toggle {
        position: fixed;
        top: 20px;
        left: 20px;
        z-index: 50;
        width: 44px;
        height: 44px;
        border-radius: 12px;
        background: rgba(7, 10, 19, 0.9);
        backdrop-filter: blur(14px);
        border: 1px solid rgba(244, 63, 94, 0.2);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.3s ease;
    }
    
    .admin-sidebar-toggle:hover {
        background: rgba(244, 63, 94, 0.2);
        border-color: rgba(244, 63, 94, 0.4);
    }
    
    /* Sidebar mobile */
    @media (max-width: 767px) {
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            width: 280px;
            transform: translateX(-100%);
            transition: transform 0.3s ease;
            z-index: 45;
        }
        
        .sidebar.open {
            transform: translateX(0);
        }
        
        /* Empêcher le scroll du body quand sidebar ouvert */
        body.admin-sidebar-open {
            overflow: hidden;
        }
    }
    
    /* Animation des items */
    .nav-item {
        transition: all 0.2s ease;
    }
    
    .nav-item:hover {
        transform: translateX(4px);
    }
</style>

<!-- Inclusion du script -->
<script src="/inc/admin_sidebar.js"></script>
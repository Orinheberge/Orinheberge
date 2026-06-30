<?php
/**
 * inc/admin_sidebar.php — Sidebar partagée pour TOUTES les pages admin
 * Requiert avant l'inclusion :
 *   - $admin (array avec pseudo/firstname/avatar/is_admin)
 *   - $active_nav (string: 'dashboard','nodes','eggs','products','extensions','clients','servers','tickets','settings','email')
 *   - Variables de comptage optionnelles : $all_users, $all_servers, $open_tickets
 */
$admin_username  = !empty($admin['pseudo']) ? $admin['pseudo'] : ($admin['firstname'] ?? 'Admin');
$_users_count    = isset($all_users)   ? count($all_users)   : ($pdo->query('SELECT COUNT(*) FROM users')->fetchColumn());
$_servers_count  = isset($all_servers) ? count($all_servers) : ($pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn());
$_tickets_count  = isset($open_tickets) ? $open_tickets      : ($pdo->query("SELECT COUNT(*) FROM support_tickets WHERE status != 'Fermé'")->fetchColumn());
?>
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

  <nav class="sidebar-nav">

    <div class="nav-section">Dashboard</div>
    <a href="/admin/" class="nav-item <?= ($active_nav === 'dashboard' || $active_nav === '') ? 'active' : '' ?>">
      <i class="fas fa-chart-bar icon"></i> Vue d'ensemble
    </a>

    <div class="nav-separator"></div>
    <div class="nav-section">Infrastructure</div>
    <a href="/admin/nodes/" class="nav-item <?= $active_nav === 'nodes' ? 'active' : '' ?>">
      <i class="fas fa-network-wired icon"></i> Nodes
    </a>
    <a href="/admin/eggs/" class="nav-item <?= $active_nav === 'eggs' ? 'active' : '' ?>">
      <i class="fas fa-egg icon"></i> Eggs
    </a>

    <div class="nav-separator"></div>
    <div class="nav-section">Boutique</div>
    <a href="/admin/products/" class="nav-item <?= $active_nav === 'products' ? 'active' : '' ?>">
      <i class="fas fa-box icon"></i> Produits
    </a>
    <a href="/admin/extensions/" class="nav-item <?= $active_nav === 'extensions' ? 'active' : '' ?>">
      <i class="fas fa-puzzle-piece icon"></i> Extensions
    </a>

    <div class="nav-separator"></div>
    <div class="nav-section">Gestion</div>
    <a href="/admin/?view=clients" class="nav-item <?= $active_nav === 'clients' ? 'active' : '' ?>">
      <i class="fas fa-users icon"></i> Clients
      <span class="ml-auto text-[10px] bg-white/5 text-gray-500 px-1.5 py-0.5 rounded-full"><?= $_users_count ?></span>
    </a>
    <a href="/admin/?view=servers" class="nav-item <?= $active_nav === 'servers' ? 'active' : '' ?>">
      <i class="fas fa-server icon"></i> Serveurs
      <span class="ml-auto text-[10px] bg-white/5 text-gray-500 px-1.5 py-0.5 rounded-full"><?= $_servers_count ?></span>
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

  </nav>

  <div class="sidebar-footer">
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
    </a>
    <a href="/logout/" class="nav-item" style="color:#ef4444;">
      <i class="fas fa-sign-out-alt icon"></i> Déconnexion
    </a>
  </div>
</aside>

<?php
/**
 * inc/clients_sidebar.php — Sidebar espace client améliorée
 * Requiert : $panel_url, $phpmyadmin_url, $open_tickets, $_SESSION['username'/'avatar']
 */
$current_path = $_SERVER['REQUEST_URI'] ?? '';

function cs_active(string $path): string {
    global $current_path;
    return str_starts_with($current_path, $path) ? 'active' : '';
}

// 🔵 Compteur de serveurs actifs
$_client_servers_count = 0;
if (isset($_SESSION['user_id']) && isset($pdo)) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ? AND status = 'paid'");
        $stmt->execute([$_SESSION['user_id']]);
        $_client_servers_count = (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        $_client_servers_count = 0;
    }
}

// 🔵 Compteur de factures impayées
$_pending_invoices_count = 0;
if (isset($_SESSION['user_id']) && isset($pdo)) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM invoices WHERE user_id = ? AND status = 'pending'");
        $stmt->execute([$_SESSION['user_id']]);
        $_pending_invoices_count = (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        $_pending_invoices_count = 0;
    }
}

// 🔵 Compteur de notifications non lues
$_notif_count = 0;
if (isset($_SESSION['user_id']) && isset($pdo)) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$_SESSION['user_id']]);
        $_notif_count = (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        $_notif_count = 0;
    }
}

// 🔵 Vérifier si une maintenance est en cours
$_maintenance_active = null;
if (isset($pdo)) {
    try {
        $_maintenance_active = $pdo->query("
            SELECT id, title, severity FROM maintenance 
            WHERE is_active = 1 AND is_public = 1
              AND status IN ('scheduled', 'in_progress')
              AND NOW() BETWEEN start_date AND end_date
            LIMIT 1
        ")->fetch();
    } catch (Exception $e) {
        $_maintenance_active = null;
    }
}
?>

<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

<link href="/inc/clients_sidebar.css" rel="stylesheet">

<!-- Bouton burger mobile -->
<button id="sidebar-toggle" aria-label="Ouvrir le menu">
    <i class="fas fa-bars" id="sidebar-toggle-icon"></i>
</button>

<!-- Overlay sombre -->
<div id="sidebar-overlay"></div>

<aside id="sidebar" class="sidebar">
    <!-- Logo -->
    <div class="sidebar-logo">
        <a href="/" class="flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-lg bg-sky-500/20 flex items-center justify-center">
                <i class="fas fa-server text-sky-400 text-sm"></i>
            </div>
            <div>
                <span class="font-black text-white text-base tracking-tight block leading-tight">OrinHeberge</span>
                <span class="text-[9px] text-sky-400/70 font-semibold">Espace Client</span>
            </div>
        </a>
    </div>

    <nav class="sidebar-nav">
        
        <!-- 🔵 Bandeau maintenance (si actif) -->
        <?php if ($_maintenance_active): 
            $sev_colors = [
                'info'     => 'sky',
                'warning'  => 'amber',
                'critical' => 'red',
            ];
            $sev_color = $sev_colors[$_maintenance_active['severity']] ?? 'amber';
        ?>
        <a href="/status/" class="block mb-3 p-3 rounded-xl bg-<?php echo $sev_color; ?>-500/10 border border-<?php echo $sev_color; ?>-500/20 hover:bg-<?php echo $sev_color; ?>-500/15 transition">
            <div class="flex items-center gap-2 text-xs">
                <i class="fas fa-wrench text-<?php echo $sev_color; ?>-400 maintenance-pulse"></i>
                <span class="text-<?php echo $sev_color; ?>-400 font-semibold truncate"><?php echo htmlspecialchars($_maintenance_active['title']); ?></span>
            </div>
            <div class="text-[10px] text-gray-500 mt-1 ml-5">Maintenance en cours — Plus d'infos</div>
        </a>
        <?php endif; ?>

        <!-- PRINCIPAL -->
        <div class="nav-section">Principal</div>
        
        <a href="/client/" class="nav-item <?php echo $current_path === '/client/' || $current_path === '/client' ? 'active' : ''; ?>">
            <i class="fas fa-home icon"></i> Tableau de bord
        </a>
        
        <a href="/client/servers/" class="nav-item <?php echo cs_active('/client/servers'); ?>">
            <i class="fas fa-server icon"></i> Mes serveurs
            <?php if ($_client_servers_count > 0): ?>
            <span class="ml-auto text-[10px] bg-sky-500/15 text-sky-400 border border-sky-500/20 px-1.5 py-0.5 rounded-full font-bold"><?php echo $_client_servers_count; ?></span>
            <?php endif; ?>
        </a>

        <?php if ($_notif_count > 0): ?>
        <a href="/notifications/" class="nav-item <?php echo cs_active('/notifications'); ?>">
            <i class="fas fa-bell icon"></i> Notifications
            <span class="ml-auto text-[10px] bg-rose-500/15 text-rose-400 border border-rose-500/20 px-1.5 py-0.5 rounded-full font-bold"><?php echo $_notif_count; ?></span>
        </a>
        <?php endif; ?>

        <div class="nav-separator"></div>

        <!-- BOUTIQUE -->
        <div class="nav-section">Boutique</div>
        
        <a href="/offres/" class="nav-item <?php echo cs_active('/offres'); ?>">
            <i class="fas fa-tags icon"></i> Nos offres
        </a>
        
        <a href="/shop/cart/" class="nav-item <?php echo cs_active('/shop/cart'); ?>">
            <i class="fas fa-shopping-cart icon"></i> Mon panier 
        </a>

        <div class="nav-separator"></div>

        <!-- COMPTE -->
        <div class="nav-section">Compte</div>
        
        <a href="/profil/" class="nav-item <?php echo cs_active('/profil'); ?>">
            <i class="fas fa-user icon"></i> Mon profil
        </a>
        
        <a href="/client/billing/" class="nav-item <?php echo cs_active('/client/billing'); ?>">
            <i class="fas fa-file-invoice-dollar icon"></i> Facturation
            <?php if ($_pending_invoices_count > 0): ?>
            <span class="ml-auto text-[10px] bg-amber-500/15 text-amber-400 border border-amber-500/20 px-1.5 py-0.5 rounded-full font-bold"><?php echo $_pending_invoices_count; ?></span>
            <?php endif; ?>
        </a>
        
        <a href="/support/" class="nav-item <?php echo cs_active('/support'); ?>">
            <i class="fas fa-headset icon"></i> Support
            <?php if (!empty($open_tickets) && $open_tickets > 0): ?>
            <span class="ml-auto text-[10px] bg-purple-500/20 text-purple-400 border border-purple-500/20 px-1.5 py-0.5 rounded-full font-bold"><?php echo (int)$open_tickets; ?></span>
            <?php endif; ?>
        </a>
        
        <a href="/status/" class="nav-item <?php echo cs_active('/status'); ?>">
            <i class="fas fa-signal icon"></i> Statut
            <?php if ($_maintenance_active): ?>
            <span class="ml-auto w-2 h-2 rounded-full bg-amber-400 maintenance-pulse"></span>
            <?php endif; ?>
        </a>

        <div class="nav-separator"></div>

        <!-- OUTILS EXTERNES -->
        <div class="nav-section">Outils externes</div>
        
        <a href="<?php echo htmlspecialchars($panel_url ?? '#'); ?>" target="_blank" class="nav-item group">
            <i class="fas fa-cogs icon"></i> Panel Pterodactyl
            <i class="fas fa-external-link-alt text-[9px] ml-auto external-link-hint"></i>
        </a>
        
        <a href="<?php echo htmlspecialchars($phpmyadmin_url ?? 'https://php.orinstone.deepstone.fr'); ?>" target="_blank" class="nav-item group">
            <i class="fas fa-database icon"></i> phpMyAdmin
            <i class="fas fa-external-link-alt text-[9px] ml-auto external-link-hint"></i>
        </a>

        <a href="/discord/" target="_blank" class="nav-item group">
            <i class="fab fa-discord icon" style="color:#5865F2;"></i> Discord
            <i class="fas fa-external-link-alt text-[9px] ml-auto external-link-hint"></i>
        </a>

    </nav>

    <!-- FOOTER -->
    <div class="sidebar-footer">
        
        <!-- Sélecteur de langue -->
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

        <?php if (!empty($_SESSION['is_admin'])): ?>
        <a href="/admin/" class="nav-item mb-2" style="color:#fb923c;border-color:rgba(251,146,60,.15);background:rgba(251,146,60,.05);">
            <i class="fas fa-user-tie icon"></i> Administration
        </a>
        <?php endif; ?>
        
        <!-- Profil utilisateur -->
        <a href="/profil/" class="flex items-center gap-2.5 group mb-2 px-1 py-1 rounded-xl hover:bg-white/5 transition">
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
            <i class="fas fa-chevron-right text-[9px] text-gray-600 group-hover:text-gray-400 transition"></i>
        </a>
        
        <!-- Déconnexion -->
        <a href="/logout/" class="nav-item" style="color:#ef4444;">
            <i class="fas fa-sign-out-alt icon"></i> Déconnexion
        </a>

        <!-- Liens légaux -->
        <div class="mt-3 pt-3 border-t border-white/5 flex items-center justify-center gap-3 text-[10px] text-gray-600">
            <a href="/mentions-legales/" class="hover:text-gray-400 transition">Mentions</a>
            <span>·</span>
            <a href="/cgu/" class="hover:text-gray-400 transition">CGU</a>
            <span>·</span>
            <a href="/politique-confidentialite/" class="hover:text-gray-400 transition">Confidentialité</a>
        </div>
    </div>
</aside>

<!-- Script externe pour le burger menu -->
<script src="/inc/client_sidebar.js" defer></script>
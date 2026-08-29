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

// 🔵 Compteur utilisateurs en ligne (chat)
$_online_users_count = 0;
if (isset($_SESSION['user_id']) && isset($pdo)) {
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM user_activity 
            WHERE last_seen >= DATE_SUB(NOW(), INTERVAL 3 MINUTE)
        ");
        $stmt->execute();
        $_online_users_count = (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        $_online_users_count = 0;
    }
}

// 🔵 Compteur messages non lus (optionnel - si table existe)
$_unread_messages_count = 0;
if (isset($_SESSION['user_id']) && isset($pdo)) {
    try {
        // Compter les messages des 5 dernières minutes (approximation "non lus")
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM chat_messages 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
              AND user_id != ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $_unread_messages_count = (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        $_unread_messages_count = 0;
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

// Variables d'outils externes sécurisées
$panel_url = $panel_url ?? '#';
$phpmyadmin_url = $phpmyadmin_url ?? 'https://php.orinstone.deepstone.fr';
$open_tickets = $open_tickets ?? 0;
?>

<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<link href="/assets/css/clients_sidebar.css?v=<?php echo time(); ?>" rel="stylesheet">

<button id="sidebar-toggle" aria-label="Ouvrir le menu">
    <i class="fas fa-bars" id="sidebar-toggle-icon"></i>
</button>

<div id="sidebar-overlay"></div>

<aside id="sidebar" class="sidebar">
    <div class="sidebar-logo">
        <a href="/" class="flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-lg bg-sky-500/20 flex items-center justify-center shrink-0">
                <i class="fas fa-server text-sky-400 text-sm"></i>
            </div>
            <div class="min-w-0">
                <span class="font-black text-white text-base tracking-tight block leading-tight truncate">OrinHeberge</span>
                <span class="text-[9px] text-sky-400/70 font-semibold block">Espace Client</span>
            </div>
        </a>
    </div>

    <nav class="sidebar-nav">
        
        <?php if ($_maintenance_active): 
            $sev_classes = [
                'info'     => ['bg' => 'bg-sky-500/10', 'border' => 'border-sky-500/20', 'text' => 'text-sky-400', 'hover' => 'hover:bg-sky-500/15'],
                'warning'  => ['bg' => 'bg-amber-500/10', 'border' => 'border-amber-500/20', 'text' => 'text-amber-400', 'hover' => 'hover:bg-amber-500/15'],
                'critical' => ['bg' => 'bg-red-500/10', 'border' => 'border-red-500/20', 'text' => 'text-red-400', 'hover' => 'hover:bg-red-500/15'],
            ];
            $sev_style = $sev_classes[$_maintenance_active['severity']] ?? $sev_classes['warning'];
        ?>
        <a href="/status/" class="block mb-3 p-2.5 rounded-xl border <?php echo $sev_style['bg'] . ' ' . $sev_style['border'] . ' ' . $sev_style['hover']; ?> transition">
            <div class="flex items-center gap-2 text-xs">
                <i class="fas fa-wrench <?php echo $sev_style['text']; ?> maintenance-pulse shrink-0"></i>
                <span class="<?php echo $sev_style['text']; ?> font-semibold truncate"><?php echo htmlspecialchars($_maintenance_active['title']); ?></span>
            </div>
            <div class="text-[10px] text-gray-400 mt-1 ml-5">Maintenance en cours — En savoir plus</div>
        </a>
        <?php endif; ?>

        <!-- ═══════════════════════════════════════════ -->
        <!-- 🏠 PRINCIPAL -->
        <!-- ═══════════════════════════════════════════ -->
        <div class="nav-section">Principal</div>
        
        <a href="/client/" class="nav-item nav-dashboard <?php echo $current_path === '/client/' || $current_path === '/client' ? 'active' : ''; ?>">
            <i class="fas fa-home icon"></i> 
            <span>Tableau de bord</span>
        </a>
        
        <a href="/client/servers/" class="nav-item nav-servers <?php echo cs_active('/client/servers'); ?>">
            <i class="fas fa-server icon"></i> 
            <span>Mes serveurs</span>
            <?php if ($_client_servers_count > 0): ?>
            <span class="ml-auto text-[10px] bg-sky-500/15 text-sky-400 border border-sky-500/20 px-2 py-0.5 rounded-full font-bold"><?php echo $_client_servers_count; ?></span>
            <?php endif; ?>
        </a>

        <?php if ($_notif_count > 0): ?>
        <a href="/client/notifications/" class="nav-item nav-notif <?php echo cs_active('/client/notifications'); ?>">
            <i class="fas fa-bell icon"></i> 
            <span>Notifications</span>
            <span class="ml-auto text-[10px] bg-rose-500/15 text-rose-400 border border-rose-500/20 px-2 py-0.5 rounded-full font-bold"><?php echo $_notif_count; ?></span>
        </a>
        <?php endif; ?>

        <div class="nav-separator"></div>

        <!-- ═══════════════════════════════════════════ -->
        <!-- 💬 COMMUNAUTÉ -->
        <!-- ═══════════════════════════════════════════ -->
        <div class="nav-section">Communauté</div>
        
        <a href="/client/community/" class="nav-item nav-community <?php echo cs_active('/client/community'); ?>">
            <i class="fas fa-comments icon"></i> 
            <span>Chat communautaire</span>
            <?php if ($_online_users_count > 0): ?>
            <span class="ml-auto flex items-center gap-1 text-[10px] bg-emerald-500/15 text-emerald-400 border border-emerald-500/20 px-2 py-0.5 rounded-full font-bold">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                <?php echo $_online_users_count; ?> en ligne
            </span>
            <?php elseif ($_unread_messages_count > 0): ?>
            <span class="ml-auto text-[10px] bg-sky-500/15 text-sky-400 border border-sky-500/20 px-2 py-0.5 rounded-full font-bold">
                <?php echo $_unread_messages_count; ?>
            </span>
            <?php endif; ?>
        </a>

        <a href="/client/partners/" class="nav-item nav-partners <?php echo cs_active('/client/partners'); ?>">
            <i class="fas fa-handshake icon"></i> 
            <span>Partenaires</span>
        </a>

        <div class="nav-separator"></div>

        <!-- ═══════════════════════════════════════════ -->
        <!-- 🛒 BOUTIQUE -->
        <!-- ═══════════════════════════════════════════ -->
        <div class="nav-section">Boutique</div>
        
        <a href="/offres/" class="nav-item nav-offers <?php echo cs_active('/offres'); ?>">
            <i class="fas fa-tags icon"></i> 
            <span>Nos offres</span>
        </a>
        
        <a href="/shop/cart/" class="nav-item nav-cart <?php echo cs_active('/shop/cart'); ?>">
            <i class="fas fa-shopping-cart icon"></i> 
            <span>Mon panier</span>
        </a>

        <div class="nav-separator"></div>

        <!-- ═══════════════════════════════════════════ -->
        <!-- 👤 COMPTE -->
        <!-- ═══════════════════════════════════════════ -->
        <div class="nav-section">Compte</div>
        
        <a href="/client/profil/" class="nav-item nav-profile <?php echo cs_active('/client/profil'); ?>">
            <i class="fas fa-user icon"></i> 
            <span>Mon profil</span>
        </a>
        
        <a href="/client/profil/#payment" class="nav-item nav-cards <?php echo cs_active('/client/cards'); ?>">
            <i class="fas fa-credit-card icon"></i> 
            <span>Moyens de paiement</span>
        </a>
        
        <a href="/client/profil/#oauth" class="nav-item nav-oauth <?php echo cs_active('/client/oauth-providers'); ?>">
            <i class="fas fa-link icon"></i> 
            <span>Comptes connectés</span>
        </a>
        
        <a href="/client/billing/" class="nav-item nav-billing <?php echo cs_active('/client/billing'); ?>">
            <i class="fas fa-file-invoice-dollar icon"></i> 
            <span>Facturation</span>
            <?php if ($_pending_invoices_count > 0): ?>
            <span class="ml-auto text-[10px] bg-amber-500/15 text-amber-400 border border-amber-500/20 px-2 py-0.5 rounded-full font-bold"><?php echo $_pending_invoices_count; ?></span>
            <?php endif; ?>
        </a>
        
        <a href="/client/support/" class="nav-item nav-support <?php echo cs_active('/support'); ?>">
            <i class="fas fa-headset icon"></i> 
            <span>Support</span>
            <?php if ((int)$open_tickets > 0): ?>
            <span class="ml-auto text-[10px] bg-purple-500/20 text-purple-400 border border-purple-500/20 px-2 py-0.5 rounded-full font-bold"><?php echo (int)$open_tickets; ?></span>
            <?php endif; ?>
        </a>
        
        <a href="/status/" class="nav-item nav-status <?php echo cs_active('/status'); ?>">
            <i class="fas fa-signal icon"></i> 
            <span>Statut</span>
            <?php if ($_maintenance_active): ?>
            <span class="ml-auto w-2 h-2 rounded-full bg-amber-400 maintenance-pulse"></span>
            <?php endif; ?>
        </a>

        <div class="nav-separator"></div>

        <!-- ═══════════════════════════════════════════ -->
        <!-- 🔧 OUTILS EXTERNES -->
        <!-- ═══════════════════════════════════════════ -->
        <div class="nav-section">Outils externes</div>
        
        <a href="<?php echo htmlspecialchars($panel_url); ?>" target="_blank" rel="noopener noreferrer" class="nav-item nav-external group">
            <i class="fas fa-cogs icon"></i> 
            <span>Panel Pterodactyl</span>
            <i class="fas fa-external-link-alt text-[9px] ml-auto opacity-50 group-hover:opacity-100 transition"></i>
        </a>
        
        <a href="<?php echo htmlspecialchars($phpmyadmin_url); ?>" target="_blank" rel="noopener noreferrer" class="nav-item nav-external group">
            <i class="fas fa-database icon"></i> 
            <span>phpMyAdmin</span>
            <i class="fas fa-external-link-alt text-[9px] ml-auto opacity-50 group-hover:opacity-100 transition"></i>
        </a>

        <a href="/discord/" target="_blank" rel="noopener noreferrer" class="nav-item nav-discord group">
            <i class="fab fa-discord icon" style="color:#5865F2;"></i> 
            <span>Discord</span>
            <i class="fas fa-external-link-alt text-[9px] ml-auto opacity-50 group-hover:opacity-100 transition"></i>
        </a>
        <?php if (!empty($_SESSION['is_admin'])): ?>
        <a href="https://portainer.deepstone.fr" target="_blank" rel="noopener noreferrer" class="nav-item nav-external group">
            <i class="fab fa-docker icon" style="color:#13B6F4;"></i>
            <span>Portainer</span>
            <i class="fas fa-external-link-alt text-[9px] ml-auto opacity-50 group-hover:opacity-100 transition"></i>
        </a>
        <?php endif; ?>

    </nav>

    <!-- ═══════════════════════════════════════════ -->
    <!-- 👤 FOOTER SIDEBAR -->
    <!-- ═══════════════════════════════════════════ -->
    <div class="sidebar-footer">
        
        <div class="mb-3 px-1">
            <?php if (file_exists(__DIR__ . '/lang_switcher.php')): ?>
                <?php include __DIR__ . '/lang_switcher.php'; ?>
            <?php else: ?>
                <div class="flex items-center gap-2 text-xs text-gray-500">
                    <i class="fas fa-globe"></i>
                    <span>Langue : <?php echo strtoupper(htmlspecialchars($lang ?? 'FR')); ?></span>
                </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($_SESSION['is_admin'])): ?>
        <a href="/admin/" class="nav-item nav-admin mb-2">
            <i class="fas fa-user-tie icon"></i> 
            <span>Administration</span>
        </a>
        <?php endif; ?>
        
        <a href="/client/profil/" class="flex items-center gap-2.5 group mb-2 px-2 py-1.5 rounded-xl hover:bg-white/5 transition">
            <?php if (!empty($_SESSION['avatar']) && file_exists($_SERVER['DOCUMENT_ROOT'].'/'.$_SESSION['avatar'])): ?>
                <img src="/<?php echo htmlspecialchars($_SESSION['avatar']); ?>" class="w-8 h-8 rounded-full object-cover border border-white/10 shrink-0" alt="Avatar">
            <?php else: ?>
                <div class="w-8 h-8 rounded-full bg-sky-500/20 flex items-center justify-center text-sky-400 text-xs font-bold border border-white/10 shrink-0">
                    <?php echo strtoupper(substr($_SESSION['username'] ?? 'U', 0, 1)); ?>
                </div>
            <?php endif; ?>
            <div class="flex-1 min-w-0">
                <div class="text-xs font-semibold text-white truncate"><?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?></div>
                <div class="text-[10px] text-gray-500">Mon profil</div>
            </div>
            <i class="fas fa-chevron-right text-[9px] text-gray-600 group-hover:text-gray-400 transition shrink-0"></i>
        </a>
        
        <a href="/logout/" class="nav-item nav-logout">
            <i class="fas fa-sign-out-alt icon"></i> 
            <span>Déconnexion</span>
        </a>

        <div class="mt-3 pt-3 border-t border-white/5 flex items-center justify-center gap-2 text-[10px] text-gray-500 flex-wrap">
            <a href="/mentions-legales/" class="hover:text-gray-300 transition">Mentions</a>
            <span>·</span>
            <a href="/cgu/" class="hover:text-gray-300 transition">CGU</a>
            <span>·</span>
            <a href="/politique-confidentialite/" class="hover:text-gray-300 transition">Confidentialité</a>
        </div>
    </div>
</aside>

<script src="/assets/js/clients_sidebar.js" defer></script>
<script src="/assets/js/lang_switcher.js"></script>

<style>
/* ═══════════════════════════════════════════ */
/* 🎨 STYLES POUR LE LIEN COMMUNITY */
/* ═══════════════════════════════════════════ */
.nav-community {
    position: relative;
    background: linear-gradient(90deg, rgba(16, 185, 129, 0.05) 0%, transparent 100%);
    border: 1px solid rgba(16, 185, 129, 0.1);
}

.nav-community:hover {
    background: linear-gradient(90deg, rgba(16, 185, 129, 0.1) 0%, transparent 100%);
    border-color: rgba(16, 185, 129, 0.3);
}

.nav-community.active {
    background: linear-gradient(90deg, rgba(16, 185, 129, 0.15) 0%, transparent 100%);
    border-color: rgba(16, 185, 129, 0.4);
    color: #10b981 !important;
}

.nav-community .icon {
    color: #10b981;
}

/* Animation pulse pour indicateur en ligne */
@keyframes pulse-online {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.6; transform: scale(1.2); }
}

.nav-community .animate-pulse {
    animation: pulse-online 2s ease-in-out infinite;
}

/* Séparateurs de section visuels */
.nav-separator {
    height: 1px;
    background: linear-gradient(90deg, transparent 0%, rgba(255,255,255,0.05) 50%, transparent 100%);
    margin: 12px 8px;
}

/* Badges compteurs */
.nav-item .ml-auto {
    transition: all 0.2s;
}

.nav-item:hover .ml-auto {
    transform: scale(1.05);
}
</style>
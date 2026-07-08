<?php
/**
 * OrinHeberge — Navbar partagée améliorée
 * Nécessite que inc/lang.php soit déjà chargé (donc $lang et t() disponibles).
 * Nécessite que $is_logged_in soit défini.
 *
 * Variables optionnelles :
 * $active_nav  — 'home'|'servers'|'offers'|'support' pour surligner le lien actif
 */
$active_nav = $active_nav ?? '';

// 🔵 AJOUT : Récupérer les maintenances actives pour afficher un bandeau
$maintenance_banner = null;
if (isset($pdo)) {
    try {
        $maintenance_banner = $pdo->query("
            SELECT * FROM maintenance 
            WHERE is_active = 1 
              AND is_public = 1
              AND show_banner = 1
              AND status IN ('scheduled', 'in_progress')
              AND NOW() BETWEEN start_date AND end_date
            ORDER BY 
                CASE severity 
                    WHEN 'critical' THEN 1 
                    WHEN 'warning' THEN 2 
                    ELSE 3 
                END
            LIMIT 1
        ")->fetch();
    } catch (Exception $e) {
        // Silencieux si la table n'existe pas encore
    }
}

// 🔵 AJOUT : Compteur de notifications non lues
$notif_count = 0;
if (isset($_SESSION['user_id']) && file_exists($_SERVER['DOCUMENT_ROOT'] . '/inc/notifications.php')) {
    try {
        $notif_stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $notif_stmt->execute([$_SESSION['user_id']]);
        $notif_count = (int)$notif_stmt->fetchColumn();
    } catch (Exception $e) {
        $notif_count = 0;
    }
}
?>

<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>

<style>
    /* Animation du menu mobile */
    #mobileMenu {
        transition: max-height 0.3s ease-in-out, opacity 0.2s ease-in-out;
    }
    
    /* Rotation de l'icône */
    .rotate-icon {
        transition: transform 0.3s ease;
    }
    
    /* Badge notification pulse */
    @keyframes pulse-badge {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.1); }
    }
    .notif-badge {
        animation: pulse-badge 2s infinite;
    }
    
    /* Bandeau maintenance */
    .maintenance-banner {
        animation: slideDown 0.5s ease-out;
    }
    @keyframes slideDown {
        from { transform: translateY(-100%); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }
</style>

<nav class="sticky top-0 z-50 border-b border-white/5" style="background: rgba(7, 10, 19, 0.8); backdrop-filter: blur(14px);">
    
    <?php if ($maintenance_banner): 
        // Correction Tailwind : Classes écrites en entier pour éviter le problème du compilateur
        $sev_colors = [
            'info'     => ['bg' => 'bg-sky-500/10',     'border' => 'border-sky-500/20',     'text' => 'text-sky-400',     'icon' => 'fa-info-circle'],
            'warning'  => ['bg' => 'bg-amber-500/10',   'border' => 'border-amber-500/20',   'text' => 'text-amber-400',   'icon' => 'fa-exclamation-triangle'],
            'critical' => ['bg' => 'bg-red-500/10',     'border' => 'border-red-500/20',     'text' => 'text-red-400',     'icon' => 'fa-radiation'],
        ];
        $sev = $sev_colors[$maintenance_banner['severity']] ?? $sev_colors['info'];
    ?>
    <div class="maintenance-banner <?php echo $sev['bg']; ?> <?php echo $sev['border']; ?> border-b px-4 py-2">
        <div class="max-w-7xl mx-auto flex items-center justify-between gap-3">
            <div class="flex items-center gap-2 text-xs <?php echo $sev['text']; ?>">
                <i class="fas <?php echo $sev['icon']; ?>"></i>
                <span class="font-semibold"><?php echo htmlspecialchars($maintenance_banner['title']); ?></span>
                <span class="hidden sm:inline text-gray-500">—</span>
                <span class="hidden sm:inline text-gray-400 text-[11px]">
                    <?php echo date('H:i', strtotime($maintenance_banner['start_date'])); ?> → <?php echo date('H:i', strtotime($maintenance_banner['end_date'])); ?>
                </span>
            </div>
            <a href="/status/" class="text-[11px] <?php echo $sev['text']; ?> hover:underline font-semibold whitespace-nowrap">
                Plus d'infos <i class="fas fa-arrow-right text-[9px] ml-0.5"></i>
            </a>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($_SESSION['admin_impersonating'])): ?>
    <div style="background:rgba(244,63,94,.15);border-bottom:1px solid rgba(244,63,94,.3);" class="px-5 py-2 flex items-center justify-between text-xs">
        <span class="text-rose-400 font-semibold flex items-center gap-2">
            <i class="fas fa-user-secret"></i>
            Vous êtes connecté en tant que <strong class="text-white ml-1"><?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?></strong>
        </span>
        <form method="POST" action="/admin/stop_impersonate.php" class="m-0">
            <button type="submit" class="bg-rose-500/20 hover:bg-rose-500/40 border border-rose-500/40 text-rose-300 px-3 py-1 rounded-lg font-bold transition cursor-pointer">
                <i class="fas fa-arrow-left mr-1"></i> Retour admin
            </button>
        </form>
    </div>
    <?php endif; ?>

    <div class="max-w-7xl mx-auto flex items-center gap-4 p-5">

        <h1 class="text-3xl font-black text-white tracking-tight shrink-0">
            <a href="/">OrinHeberge</a>
        </h1>

        <div class="hidden md:flex items-center gap-2 lg:gap-3 flex-1 justify-end flex-wrap">
            <a href="/" class="<?php echo $active_nav === 'home' ? 'bg-sky-600/30 text-sky-400 border-sky-500/50 font-bold' : 'bg-sky-600/5 text-sky-400/70 hover:text-sky-300 border-sky-500/10 hover:bg-sky-600/20'; ?> px-4 py-2 rounded-full text-xs flex items-center gap-2 transition font-medium shadow-md border whitespace-nowrap">
                <i class="fas fa-home"></i> <?php echo t('nav.home'); ?>
            </a>

            <?php if (isset($_SESSION['user_id'])): ?>
            <a href="/client/servers/" class="<?php echo $active_nav === 'servers' ? 'bg-slate-600/40 text-slate-300 border-slate-500/60 font-bold' : 'bg-slate-600/10 text-slate-400 hover:text-slate-200 border-slate-500/15 hover:bg-slate-600/30'; ?> px-4 py-2 rounded-full text-xs flex items-center gap-2 transition font-medium shadow-md border whitespace-nowrap">
                <i class="fas fa-server"></i> <?php echo t('nav.servers'); ?>
            </a>
            <?php endif; ?>

            <div class="relative group">
                <button class="text-gray-300 hover:text-sky-400 font-bold flex items-center gap-2.5 transition bg-white/5 hover:bg-white/10 px-4 py-2 rounded-full border border-white/5 focus:outline-none text-xs whitespace-nowrap cursor-pointer">
                    <i class="fas fa-tags"></i> Boutique
                    <i class="fas fa-chevron-down text-[9px] opacity-50"></i>
                </button>
                <div class="dropdown-menu absolute right-0 mt-2 w-56 rounded-2xl border border-white/10 bg-[#11151d] shadow-2xl shadow-black/30 py-2 hidden group-hover:block group-focus-within:block z-50">
                    <a href="/shop/" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-300 hover:bg-white/5 hover:text-white">
                        <i class="fas fa-tags w-4"></i> <?php echo t('nav.offers'); ?>
                    </a>
                    <a href="/shop/cart/" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-300 hover:bg-white/5 hover:text-white">
                        <i class="fas fa-shopping-cart w-4"></i> Mon panier
                    </a>
                    <?php if (isset($_SESSION['user_id'])): ?>
                    <hr class="my-2 border-white/10">
                    <a href="/client/billing/" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-300 hover:bg-white/5 hover:text-white">
                        <i class="fas fa-file-invoice-dollar w-4"></i> Facturation
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <a href="/support/" class="<?php echo $active_nav === 'support' ? 'bg-purple-600/30 text-purple-400 border-purple-500/50 font-bold' : 'bg-purple-600/5 text-purple-400/70 hover:text-purple-300 border-purple-500/10 hover:bg-purple-600/20'; ?> px-4 py-2 rounded-full text-xs flex items-center gap-2 transition font-medium shadow-md border whitespace-nowrap">
                <i class="fas fa-headset"></i> <?php echo t('nav.support'); ?>
            </a>

            <?php if (isset($_SESSION['user_id'])): ?>
                <?php if ($notif_count > 0): ?>
                <a href="/notifications/" class="relative bg-rose-600/10 hover:bg-rose-600/20 border border-rose-500/20 text-rose-400 px-3 py-2 rounded-full text-xs flex items-center gap-2 transition font-medium whitespace-nowrap">
                    <i class="fas fa-bell"></i>
                    <span class="notif-badge absolute -top-1 -right-1 w-4 h-4 bg-rose-500 text-white text-[9px] font-bold rounded-full flex items-center justify-center border border-rose-900">
                        <?= $notif_count ?>
                    </span>
                </a>
                <?php endif; ?>

                <div class="relative group">
                    <button class="text-gray-300 hover:text-sky-400 font-bold flex items-center gap-2.5 transition bg-white/5 hover:bg-white/10 px-4 py-2 rounded-full border border-white/5 focus:outline-none text-xs whitespace-nowrap cursor-pointer">
                        <?php if (!empty($_SESSION['avatar']) && file_exists($_SERVER['DOCUMENT_ROOT'] . '/' . $_SESSION['avatar'])): ?>
                            <img src="/<?php echo htmlspecialchars($_SESSION['avatar']); ?>" alt="Avatar" class="w-5 h-5 rounded-full object-cover border border-sky-500/30 shrink-0">
                        <?php else: ?>
                            <i class="fas fa-user-circle text-lg text-sky-400 shrink-0"></i>
                        <?php endif; ?>
                        <span><?php echo htmlspecialchars($_SESSION['username'] ?? t('nav.profile')); ?></span>
                        <i class="fas fa-chevron-down text-[10px] opacity-70"></i>
                    </button>

                    <div class="dropdown-menu absolute right-0 mt-2 w-56 rounded-2xl border border-white/10 bg-[#11151d] shadow-2xl shadow-black/30 py-2 hidden group-hover:block group-focus-within:block z-50">
                        <a href="/profil/" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-300 hover:bg-white/5 hover:text-white">
                            <i class="fas fa-user w-4"></i> Profil
                        </a>
                        <a href="/client/servers/" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-300 hover:bg-white/5 hover:text-white">
                            <i class="fas fa-server w-4"></i> Mes serveurs
                        </a>
                        <a href="/client/billing/" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-300 hover:bg-white/5 hover:text-white">
                            <i class="fas fa-file-invoice-dollar w-4"></i> Facturation
                        </a>
                        
                        <?php if (!empty($_SESSION['is_admin'])): ?>
                            <hr class="my-2 border-white/10">
                            <a href="/admin/" class="flex items-center gap-2 px-4 py-2 text-sm text-amber-400 hover:bg-white/5 hover:text-amber-300">
                                <i class="fas fa-user-tie"></i> Administration
                            </a>
                        <?php endif; ?>
                        <hr class="my-2 border-white/10">
                        <a href="/logout/" class="flex items-center gap-2 px-4 py-2 text-sm text-red-400 hover:bg-red-500/10 hover:text-red-300">
                            <i class="fas fa-sign-out-alt w-4"></i> Déconnexion
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <a href="/login/" class="bg-sky-600/10 border border-sky-500/20 text-sky-400 hover:text-white hover:bg-sky-600 px-4 py-2 rounded-full text-xs flex items-center gap-2 transition font-medium whitespace-nowrap">
                    <i class="fas fa-sign-in-alt"></i> <?php echo t('nav.login'); ?>
                </a>
                <a href="/register/" class="bg-sky-600 hover:bg-sky-500 text-white px-4 py-2 rounded-full text-xs flex items-center gap-2 transition font-medium whitespace-nowrap shadow-md shadow-sky-900/20">
                    <i class="fas fa-user-plus"></i> <?php echo t('nav.register'); ?>
                </a>
            <?php endif; ?>

            <a href="/status/" class="bg-emerald-600/20 hover:bg-emerald-600 border border-emerald-500/30 text-emerald-400 hover:text-white px-4 py-2 rounded-full text-xs flex items-center gap-2 transition font-medium shadow-md shadow-emerald-900/20 whitespace-nowrap">
                <i class="fas fa-signal"></i> <?php echo t('status.nav'); ?>
            </a>

            <?php include __DIR__ . '/lang_switcher.php'; ?>
        </div>

        <button onclick="toggleMobileMenu()" class="md:hidden text-2xl text-gray-400 hover:text-white transition shrink-0 ml-auto cursor-pointer" aria-label="Menu">
            <i class="fas fa-bars" id="menuIcon"></i>
        </button>
    </div>

    <div id="mobileMenu" class="md:hidden opacity-0" style="max-height: 0px; overflow: hidden;">
        <div class="px-4 pb-4 space-y-2">
            
            <a href="/" class="block py-2.5 px-4 rounded-xl flex items-center gap-3 text-sm font-medium border <?php echo $active_nav === 'home' ? 'bg-sky-600/20 border-sky-500/40 text-sky-400' : 'bg-white/[0.02] border-white/5 text-gray-300'; ?>">
                <i class="fas fa-home w-5 text-center"></i> <?php echo t('nav.home'); ?>
            </a>
            
            <?php if (isset($_SESSION['user_id'])): ?>
            <a href="/client/servers/" class="block py-2.5 px-4 rounded-xl flex items-center gap-3 text-sm font-medium border <?php echo $active_nav === 'servers' ? 'bg-slate-600/20 border-slate-500/40 text-slate-300' : 'bg-white/[0.02] border-white/5 text-gray-300'; ?>">
                <i class="fas fa-server w-5 text-center"></i> <?php echo t('nav.servers'); ?>
            </a>
            <?php endif; ?>

            <div class="bg-white/[0.02] border border-white/5 rounded-xl">
                <button type="button" onclick="toggleMobileDropdown('shopDropdown')" class="w-full py-2.5 px-4 flex items-center justify-between text-sm font-medium text-gray-300 hover:bg-white/5 transition cursor-pointer">
                    <span class="flex items-center gap-3">
                        <i class="fas fa-tags w-5 text-center"></i> Boutique
                    </span>
                    <i class="fas fa-chevron-down text-xs transition-transform duration-300" id="shopDropdownIcon"></i>
                </button>
                <div id="shopDropdown" class="transition-all duration-300 ease-in-out overflow-hidden" style="max-height: 0px;">
                    <div class="border-t border-white/5 bg-black/20 py-2">
                        <a href="/shop/" class="block py-2 px-4 pl-12 text-sm text-gray-400 hover:bg-white/5 hover:text-white">
                            <i class="fas fa-tags w-4 mr-2"></i> <?php echo t('nav.offers'); ?>
                        </a>
                        <a href="/shop/cart/" class="block py-2 px-4 pl-12 text-sm text-gray-400 hover:bg-white/5 hover:text-white">
                            <i class="fas fa-shopping-cart w-4 mr-2"></i> Mon panier
                        </a>
                        <?php if (isset($_SESSION['user_id'])): ?>
                        <a href="/client/billing/" class="block py-2 px-4 pl-12 text-sm text-gray-400 hover:bg-white/5 hover:text-white">
                            <i class="fas fa-file-invoice-dollar w-4 mr-2"></i> Facturation
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <a href="/support/" class="block py-2.5 px-4 rounded-xl flex items-center gap-3 text-sm font-medium border <?php echo $active_nav === 'support' ? 'bg-purple-600/20 border-purple-500/40 text-purple-400' : 'bg-white/[0.02] border-white/5 text-gray-300'; ?>">
                <i class="fas fa-headset w-5 text-center"></i> <?php echo t('nav.support'); ?>
            </a>

            <a href="/status/" class="block py-2.5 px-4 rounded-xl flex items-center gap-3 text-sm font-medium border bg-emerald-600/10 border-emerald-500/30 text-emerald-400">
                <i class="fas fa-signal w-5 text-center"></i> <?php echo t('status.nav'); ?>
            </a>

            <hr class="border-white/10">

            <?php if (isset($_SESSION['user_id']) && $notif_count > 0): ?>
            <a href="/notifications/" class="block py-2.5 px-4 rounded-xl flex items-center gap-3 text-sm font-medium border bg-rose-600/10 border-rose-500/30 text-rose-400">
                <i class="fas fa-bell w-5 text-center"></i> 
                <span>Notifications</span>
                <span class="ml-auto bg-rose-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full"><?= $notif_count ?></span>
            </a>
            <?php endif; ?>

            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="/profil/" class="block py-2.5 px-4 rounded-xl flex items-center gap-3 text-sm font-medium bg-white/[0.02] border border-white/5 text-gray-300">
                    <i class="fas fa-user w-5 text-center"></i> Profil
                </a>
                <a href="/client/servers/" class="block py-2.5 px-4 rounded-xl flex items-center gap-3 text-sm font-medium bg-white/[0.02] border border-white/5 text-gray-300">
                    <i class="fas fa-server w-5 text-center"></i> Mes serveurs
                </a>
                <a href="/client/billing/" class="block py-2.5 px-4 rounded-xl flex items-center gap-3 text-sm font-medium bg-white/[0.02] border border-white/5 text-gray-300">
                    <i class="fas fa-file-invoice-dollar w-5 text-center"></i> Facturation
                </a>
                <?php if (!empty($_SESSION['is_admin'])): ?>
                    <a href="/admin/" class="block py-2.5 px-4 rounded-xl flex items-center gap-3 text-sm font-medium bg-amber-600/10 border border-amber-500/30 text-amber-400">
                        <i class="fas fa-user-tie w-5 text-center"></i> Administration
                    </a>
                <?php endif; ?>
                <a href="/logout/" class="block py-2.5 px-4 rounded-xl flex items-center gap-3 text-sm font-medium bg-red-600/10 border border-red-500/30 text-red-400">
                    <i class="fas fa-sign-out-alt w-5 text-center"></i> Déconnexion
                </a>
            <?php else: ?>
                <a href="/login/" class="block py-2.5 px-4 rounded-xl flex items-center gap-3 text-sm font-medium bg-white/5 border border-white/5 text-gray-300">
                    <i class="fas fa-sign-in-alt w-5 text-center"></i> <?php echo t('nav.login'); ?>
                </a>
                <a href="/register/" class="block py-2.5 px-4 rounded-xl flex items-center gap-3 text-sm font-medium bg-sky-600/20 border border-sky-500/30 text-sky-400">
                    <i class="fas fa-user-plus w-5 text-center"></i> <?php echo t('nav.register'); ?>
                </a>
            <?php endif; ?>

            <hr class="border-white/10">

            <div class="pt-1">
                <?php include __DIR__ . '/lang_switcher.php'; ?>
            </div>
        </div>
    </div>
</nav>

<script>
// Menu mobile synchrone
function toggleMobileMenu() {
    const menu = document.getElementById('mobileMenu');
    const icon = document.getElementById('menuIcon');
    
    if (menu.style.maxHeight === '0px' || menu.style.maxHeight === '') {
        menu.style.maxHeight = menu.scrollHeight + "px";
        menu.style.opacity = '1';
        icon.className = 'fas fa-times';
    } else {
        menu.style.maxHeight = '0px';
        menu.style.opacity = '0';
        icon.className = 'fas fa-bars';
    }
}

// Dropdown interne mobile
function toggleMobileDropdown(id) {
    const dropdown = document.getElementById(id);
    const icon = document.getElementById(id + 'Icon');
    const menu = document.getElementById('mobileMenu');
    
    if (dropdown.style.maxHeight === '0px' || dropdown.style.maxHeight === '') {
        dropdown.style.maxHeight = dropdown.scrollHeight + "px";
        icon.style.transform = 'rotate(180deg)';
        // On réajuste la hauteur du conteneur parent pour éviter que ça coupe
        setTimeout(() => {
            menu.style.maxHeight = menu.scrollHeight + "px";
        }, 50);
    } else {
        dropdown.style.maxHeight = '0px';
        icon.style.transform = 'rotate(0deg)';
        setTimeout(() => {
            menu.style.maxHeight = menu.scrollHeight + "px";
        }, 50);
    }
}

// Nettoyage lors du redimensionnement
window.addEventListener('resize', () => {
    if (window.innerWidth >= 768) {
        const mobileMenu = document.getElementById('mobileMenu');
        mobileMenu.style.maxHeight = '0px';
        mobileMenu.style.opacity = '0';
        document.getElementById('menuIcon').className = 'fas fa-bars';
    }
});
</script>
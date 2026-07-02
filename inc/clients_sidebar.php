<?php

?>
<aside id="sidebar" class="sidebar">
    <div class="sidebar-logo">
        <a href="/" class="flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-lg bg-sky-500/20 flex items-center justify-center">
                <i class="fas fa-server text-sky-400 text-sm"></i>
            </div>
            <span class="font-black text-white text-base tracking-tight">OrinHeberge</span>
        </a>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section">Principal</div>
        <a href="/client/" class="nav-item">
            <i class="fas fa-home icon"></i> Tableau de bord
        </a>
        <a href="/client/servers/" class="nav-item active">
            <i class="fas fa-server icon"></i> Mes serveurs
        </a>
        <a href="/offres/" class="nav-item">
            <i class="fas fa-tags icon"></i> Nos offres
        </a>
        <a href="/shop/cart/" class="nav-item">
            <i class="fas fa-tags icon"></i> Panier
        </a>

        <div class="nav-separator"></div>
        <div class="nav-section">Compte</div>
        <a href="/profil/" class="nav-item">
            <i class="fas fa-user icon"></i> Mon profil
        </a>
        <a href="/client/billing/" class="nav-item">
            <i class="fas fa-file-invoice-dollar icon"></i> Facturation
        </a>
        <a href="/support/" class="nav-item">
            <i class="fas fa-headset icon"></i> Support
            <?php if ($open_tickets > 0): ?>
            <span class="ml-auto text-[10px] bg-purple-500/20 text-purple-400 border border-purple-500/20 px-1.5 py-0.5 rounded-full font-bold"><?php echo $open_tickets; ?></span>
            <?php endif; ?>
        </a>
        <a href="/status/" class="nav-item">
            <i class="fas fa-signal icon"></i> Statut
        </a>


        <div class="nav-separator"></div>
        <div class="nav-section">Outils</div>
        <a href="<?php echo htmlspecialchars($panel_url); ?>" target="_blank" class="nav-item">
            <i class="fas fa-cogs icon"></i> Panel Pterodactyl
        </a>
        <a href="<?php echo htmlspecialchars($phpmyadmin_url); ?>" target="_blank" class="nav-item">
            <i class="fas fa-database icon"></i> phpMyAdmin
        </a>
    </nav>

    <div class="sidebar-footer">
                <?php if (!empty($_SESSION['is_admin'])): ?>
                            <a href="/admin/" class="flex items-center gap-2 px-4 py-2 text-sm text-amber-400 hover:bg-white/5 hover:text-amber-300">
                                <i class="fas fa-shield-halved text-sky-400 w-4"></i> Administration
                            </a>
                             <?php endif; ?>
        <a href="/profil/" class="flex items-center gap-2.5 group mb-2">
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
        </a>
        <a href="/logout/" class="nav-item" style="color:#ef4444;">
            <i class="fas fa-sign-out-alt icon"></i> Déconnexion
        </a>
    </div>
</aside>
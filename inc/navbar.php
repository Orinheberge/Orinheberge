<?php
/**
 * OrinHeberge — Navbar partagée
 * Nécessite que inc/lang.php soit déjà chargé (donc $lang et t() disponibles).
 * Nécessite que $is_logged_in soit défini.
 *
 * Variables optionnelles :
 *   $active_nav  — 'home'|'servers'|'offers'|'support' pour surligner le lien actif
 */
$active_nav = $active_nav ?? '';
?>
<nav class="sticky top-0 z-50 glass p-5 border-b border-white/5">
    <div class="max-w-7xl mx-auto flex items-center gap-4">

        <h1 class="text-3xl font-black gradient-text tracking-tight shrink-0">
            <a href="/">OrinHeberge</a>
        </h1>

        <div class="hidden md:flex items-center gap-2 lg:gap-3 flex-1 justify-end flex-wrap">
            <a href="/" class="<?php echo $active_nav === 'home' ? 'bg-sky-600/30 text-sky-400 border-sky-500/50 font-bold' : 'bg-sky-600/5 text-sky-400/70 hover:text-sky-300 border-sky-500/10 hover:bg-sky-600/20'; ?> px-4 py-2 rounded-full text-xs flex items-center gap-2 transition font-medium shadow-md border whitespace-nowrap">
                <i class="fas fa-home"></i> <?php echo t('nav.home'); ?>
            </a>

            <a href="/client/servers/" class="<?php echo $active_nav === 'servers' ? 'bg-slate-600/40 text-slate-300 border-slate-500/60 font-bold' : 'bg-slate-600/10 text-slate-400 hover:text-slate-200 border-slate-500/15 hover:bg-slate-600/30'; ?> px-4 py-2 rounded-full text-xs flex items-center gap-2 transition font-medium shadow-md border whitespace-nowrap">
                <i class="fas fa-server"></i> <?php echo t('nav.servers'); ?>
            </a>

            <a href="/shop/" class="<?php echo $active_nav === 'offers' ? 'bg-amber-600/30 text-amber-400 border-amber-500/50 font-bold' : 'bg-amber-600/5 text-amber-400/70 hover:text-amber-300 border-amber-500/10 hover:bg-amber-600/20'; ?> px-4 py-2 rounded-full text-xs flex items-center gap-2 transition font-medium shadow-md border whitespace-nowrap">
                <i class="fas fa-tags"></i> <?php echo t('nav.offers'); ?>
            </a>

            <a href="/support/" class="<?php echo $active_nav === 'support' ? 'bg-purple-600/30 text-purple-400 border-purple-500/50 font-bold' : 'bg-purple-600/5 text-purple-400/70 hover:text-purple-300 border-purple-500/10 hover:bg-purple-600/20'; ?> px-4 py-2 rounded-full text-xs flex items-center gap-2 transition font-medium shadow-md border whitespace-nowrap">
                <i class="fas fa-headset"></i> <?php echo t('nav.support'); ?>
            </a>

            <?php if (isset($_SESSION['user_id'])): ?>
                <?php if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/inc/notifications.php')): ?>
                    <?php include $_SERVER['DOCUMENT_ROOT'] . '/inc/notifications.php'; ?>
                <?php endif; ?>

                <a href="/client/servers/" class="text-gray-300 hover:text-sky-400 font-bold flex items-center gap-2.5 transition bg-white/5 hover:bg-white/10 px-4 py-2 rounded-full border border-white/5 focus:outline-none text-xs whitespace-nowrap">
                    <?php if (!empty($_SESSION['avatar']) && file_exists($_SERVER['DOCUMENT_ROOT'] . '/' . $_SESSION['avatar'])): ?>
                        <img src="/<?php echo htmlspecialchars($_SESSION['avatar']); ?>" alt="Avatar" class="w-5 h-5 rounded-full object-cover border border-sky-500/30 shrink-0">
                    <?php else: ?>
                        <i class="fas fa-user-circle text-lg text-sky-400 shrink-0"></i>
                    <?php endif; ?>
                    <span><?php echo htmlspecialchars($_SESSION['username'] ?? t('nav.profile')); ?></span>
                </a>

                <a href="/logout/" class="bg-red-600/10 hover:bg-red-600/20 border border-red-500/20 text-red-400 hover:text-red-300 px-4 py-2 rounded-full text-xs flex items-center gap-2 transition font-medium shadow-md shadow-red-950/10 whitespace-nowrap">
                    <i class="fas fa-sign-out-alt"></i> <?php echo t('nav.logout'); ?>
                </a>
            <?php else: ?>
                <a href="/login/" class="bg-sky-600/10 border border-sky-500/20 text-sky-400 hover:text-white hover:bg-sky-600 px-4 py-2 rounded-full text-xs flex items-center gap-2 transition font-medium whitespace-nowrap">
                    <i class="fas fa-sign-in-alt"></i> <?php echo t('nav.login'); ?>
                </a>
                <a href="/register/" class="bg-sky-600 hover:bg-sky-500 text-white px-4 py-2 rounded-full text-xs flex items-center gap-2 transition font-medium whitespace-nowrap shadow-md shadow-sky-900/20">
                    <i class="fas fa-user-plus"></i> <?php echo t('nav.register'); ?>
                </a>
            <?php endif; ?>

            <?php if (isset($_SESSION['is_admin']) && $_SESSION['is_admin']): ?>
                <a href="/admin/" class="bg-rose-600/20 hover:bg-rose-600 border border-rose-500/30 text-rose-400 hover:text-white px-4 py-2 rounded-full text-xs flex items-center gap-2 transition font-medium shadow-md shadow-rose-900/20 whitespace-nowrap">
                    <i class="fas fa-shield-alt"></i> Admin Panel
                </a>
                <a href="/support/admin_tickets/" class="bg-orange-600/20 hover:bg-orange-600 border border-orange-500/30 text-orange-400 hover:text-white px-4 py-2 rounded-full text-xs flex items-center gap-2 transition font-medium shadow-md shadow-orange-900/20 whitespace-nowrap">
                    <i class="fas fa-unlock-keyhole"></i> <?php echo t('nav.admin_tickets'); ?>
                </a>
            <?php endif; ?>

            <a href="/status/" class="bg-emerald-600/20 hover:bg-emerald-600 border border-emerald-500/30 text-emerald-400 hover:text-white px-4 py-2 rounded-full text-xs flex items-center gap-2 transition font-medium shadow-md shadow-emerald-900/20 whitespace-nowrap">
                <i class="fas fa-signal"></i> <?php echo t('status.nav'); ?>
            </a>

            <a href="https://php.orinstone.deepstone.fr" class="glass px-4 py-2 rounded-full text-xs flex items-center gap-2 border border-white/5 text-gray-300 hover:text-white hover:bg-white/10 transition whitespace-nowrap">
                <i class="fas fa-database text-sky-400"></i> phpMyAdmin
            </a>

            <a href="https://panel.orinstone.deepstone.fr" class="bg-sky-600 hover:bg-sky-500 px-4 py-2 rounded-full text-xs flex items-center gap-2 transition font-medium shadow-md shadow-sky-900/20 whitespace-nowrap text-white">
                <i class="fas fa-cogs"></i> Panel
            </a>

            <?php include __DIR__ . '/lang_switcher.php'; ?>
        </div>

        <button onclick="toggleMenu()" class="md:hidden text-2xl text-gray-400 hover:text-white transition shrink-0 ml-auto">
            <i class="fas fa-bars"></i>
        </button>
    </div>

    <div id="mobileMenu" class="md:hidden mt-4 px-4 space-y-3 glass rounded-2xl p-4 hidden">
        <a href="/" class="block py-2 px-4 rounded-xl flex items-center gap-2 text-sm font-medium border <?php echo $active_nav === 'home' ? 'bg-sky-600/20 border-sky-500/40 text-sky-400' : 'bg-white/[0.02] border-white/5 text-gray-300'; ?>">
            <i class="fas fa-home w-5 text-center"></i> <?php echo t('nav.home'); ?>
        </a>
        <a href="/client/servers/" class="block py-2 px-4 rounded-xl flex items-center gap-2 text-sm font-medium border <?php echo $active_nav === 'servers' ? 'bg-slate-600/20 border-slate-500/40 text-slate-300' : 'bg-white/[0.02] border-white/5 text-gray-300'; ?>">
            <i class="fas fa-server w-5 text-center"></i> <?php echo t('nav.servers'); ?>
        </a>
        <a href="/shop/" class="block py-2 px-4 rounded-xl flex items-center gap-2 text-sm font-medium border <?php echo $active_nav === 'offers' ? 'bg-amber-600/20 border-amber-500/40 text-amber-400' : 'bg-white/[0.02] border-white/5 text-gray-300'; ?>">
            <i class="fas fa-tags w-5 text-center"></i> <?php echo t('nav.offers'); ?>
        </a>
        <a href="/support/" class="block py-2 px-4 rounded-xl flex items-center gap-2 text-sm font-medium border <?php echo $active_nav === 'support' ? 'bg-purple-600/20 border-purple-500/40 text-purple-400' : 'bg-white/[0.02] border-white/5 text-gray-300'; ?>">
            <i class="fas fa-headset w-5 text-center"></i> <?php echo t('nav.support'); ?>
        </a>
        <a href="/status/" class="block py-2 px-4 rounded-xl flex items-center gap-2 text-sm font-medium border bg-emerald-600/10 border-emerald-500/30 text-emerald-400">
            <i class="fas fa-signal w-5 text-center"></i> <?php echo t('status.nav'); ?>
        </a>

        <?php if (isset($_SESSION['is_admin']) && $_SESSION['is_admin']): ?>
            <a href="/admin/" class="block py-2 px-4 rounded-xl bg-rose-600/20 border border-rose-500/30 text-rose-400 flex items-center gap-2 text-sm font-semibold">
                <i class="fas fa-shield-alt w-5 text-center"></i> Admin Panel
            </a>
            <a href="/support/admin_tickets/" class="block py-2 px-4 rounded-xl bg-orange-600/20 border border-orange-500/30 text-orange-400 flex items-center gap-2 text-sm font-semibold">
                <i class="fas fa-unlock-keyhole w-5 text-center"></i> <?php echo t('nav.admin_tickets_short'); ?>
            </a>
        <?php endif; ?>

        <hr class="border-white/10">

        <?php if (isset($_SESSION['user_id'])): ?>
            <a href="/profil/" class="bg-white/5 text-gray-200 block py-2 px-4 rounded-xl flex items-center gap-2.5 text-sm font-bold border border-white/5">
                <?php if (!empty($_SESSION['avatar']) && file_exists($_SERVER['DOCUMENT_ROOT'] . '/' . $_SESSION['avatar'])): ?>
                    <img src="/<?php echo htmlspecialchars($_SESSION['avatar']); ?>" alt="Avatar" class="w-5 h-5 rounded-full object-cover border border-sky-500/30 shrink-0">
                <?php else: ?>
                    <i class="fas fa-user-circle text-lg text-sky-400 shrink-0"></i>
                <?php endif; ?>
                <span><?php echo htmlspecialchars($_SESSION['username'] ?? t('nav.profile')); ?></span>
            </a>
            <a href="/logout/" class="bg-red-600/10 border border-red-500/20 text-red-400 block py-2 px-4 rounded-xl flex items-center gap-2 text-sm font-medium">
                <i class="fas fa-sign-out-alt w-5 text-center"></i> <?php echo t('nav.logout'); ?>
            </a>
        <?php else: ?>
            <a href="/login/" class="bg-white/5 border border-white/5 text-gray-300 block py-2 px-4 rounded-xl flex items-center gap-2 text-sm font-medium"><i class="fas fa-sign-in-alt w-5 text-center"></i> <?php echo t('nav.login'); ?></a>
            <a href="/register/" class="bg-white/5 border border-white/5 text-gray-300 block py-2 px-4 rounded-xl flex items-center gap-2 text-sm font-medium"><i class="fas fa-user-plus w-5 text-center"></i> <?php echo t('nav.register'); ?></a>
        <?php endif; ?>

        <hr class="border-white/10">

        <div class="grid grid-cols-2 gap-2 pt-1">
            <a href="https://php.orinstone.deepstone.fr" class="glass text-gray-300 px-4 py-2.5 rounded-xl text-xs flex items-center gap-2 justify-center border border-white/5 font-medium">
                <i class="fas fa-database text-sky-400"></i> phpMyAdmin
            </a>
            <a href="https://panel.orinstone.deepstone.fr" class="bg-sky-600 text-white px-4 py-2.5 rounded-xl text-xs flex items-center gap-2 justify-center font-medium">
                <i class="fas fa-cogs"></i> Panel
            </a>
        </div>
        
        <div class="pt-1">
            <?php include __DIR__ . '/lang_switcher.php'; ?>
        </div>
    </div>
</nav>


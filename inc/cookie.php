<?php
/**
 * OrinHeberge — Gestion du bandeau de cookies
 * Inclure ce fichier tout en bas de vos pages principales, juste avant la fermeture de </body>
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/lang.php';
// Si l'utilisateur a déjà fait son choix, on n'affiche rien
if (isset($_COOKIE['orinheberge_cookies'])) {
    return;
}
?>

<div id="cookie-banner" class="fixed bottom-8 left-1/2 -translate-x-1/2 w-[calc(100%-2rem)] max-w-3xl z-50 transform translate-y-20 opacity-0 transition-all duration-500 ease-out">
    <div class="bg-[#0f172a]/95 backdrop-blur-2xl border border-white/10 rounded-2xl p-6 md:p-8 shadow-2xl shadow-black/80">
        
        <div class="flex justify-between items-center border-b border-white/5 pb-4 mb-4">
            <div class="flex items-center gap-3">
                <div class="text-sky-400 text-2xl shrink-0">
                    <i class="fas fa-cookie-bite"></i>
                </div>
                <h4 class="text-white font-extrabold text-lg tracking-tight">
                    <?php echo t('cookie.title'); ?>
                </h4>
            </div>
            
            <div class="flex items-center gap-1 bg-white/5 rounded-xl p-1 border border-white/5 text-xs font-bold">
                <a href="?lang=fr" class="px-3 py-1.5 rounded-lg transition <?php echo $lang === 'fr' ? 'bg-sky-600 text-white shadow' : 'text-gray-400 hover:text-white'; ?>">FR</a>
                <a href="?lang=en" class="px-3 py-1.5 rounded-lg transition <?php echo $lang === 'en' ? 'bg-sky-600 text-white shadow' : 'text-gray-400 hover:text-white'; ?>">EN</a>
            </div>
        </div>

        <div class="flex flex-col lg:flex-row items-start lg:items-center gap-6 justify-between">
            <div class="flex-1">
                <p class="text-gray-300 text-sm leading-relaxed">
                    <?php echo t('cookie.text'); ?>
                    <a href="/mentions-legales/" class="text-sky-400 hover:underline font-medium"><?php echo t('cookie.ml'); ?></a>, 
                    <a href="/cgu/" class="text-sky-400 hover:underline font-medium"><?php echo t('cookie.cgu'); ?></a>
                    <?php echo t('cookie.text_and'); ?>
                    <a href="/politique-confidentialite/" class="text-sky-400 hover:underline font-medium"><?php echo t('cookie.pp'); ?></a>.
                </p>
            </div>
            
            <div class="flex items-center gap-3 shrink-0 w-full lg:w-auto justify-end border-t border-white/5 lg:border-0 pt-4 lg:pt-0">
                <button onclick="setCookieConsent('denied')" class="text-gray-300 hover:text-white border border-white/10 hover:bg-white/10 px-6 py-3 rounded-xl text-sm font-bold transition bg-white/5 flex-1 lg:flex-initial text-center whitespace-nowrap">
                    <?php echo t('cookie.deny'); ?>
                </button>
                <button onclick="setCookieConsent('accepted')" class="bg-sky-600 hover:bg-sky-500 text-white px-7 py-3 rounded-xl text-sm font-bold transition shadow-xl shadow-sky-900/30 flex-1 lg:flex-initial text-center whitespace-nowrap">
                    <?php echo t('cookie.accept'); ?>
                </button>
            </div>
        </div>

    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const banner = document.getElementById('cookie-banner');
    if (banner) {
        // Apparition fluide à l'écran
        setTimeout(() => {
            banner.classList.remove('translate-y-20', 'opacity-0');
        }, 800);
    }
});

function setCookieConsent(status) {
    const banner = document.getElementById('cookie-banner');
    
    if (banner) {
        banner.classList.add('translate-y-20', 'opacity-0');
        setTimeout(() => banner.remove(), 500);
    }

    const date = new Date();
    date.setTime(date.getTime() + (365 * 24 * 60 * 60 * 1000)); // Enregistre le choix pour 1 an
    const expires = "; expires=" + date.toUTCString();
    
    document.cookie = "orinheberge_cookies=" + status + expires + "; path=/; SameSite=Lax; Secure";
}
</script>
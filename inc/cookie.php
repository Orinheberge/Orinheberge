<?php
/**
 * OrinHeberge — Gestion du bandeau de cookies (version améliorée)
 * Inclure ce fichier tout en bas de vos pages principales, juste avant la fermeture de </body>
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/lang.php';

// Si l'utilisateur a déjà fait son choix, on n'affiche rien
if (isset($_COOKIE['orinheberge_cookies'])) {
    return;
}
?>

<!-- Bandeau de cookies -->
<div id="cookie-banner" class="fixed bottom-8 left-1/2 -translate-x-1/2 w-[calc(100%-2rem)] max-w-4xl z-50 transform translate-y-20 opacity-0 transition-all duration-500 ease-out" role="dialog" aria-labelledby="cookie-title" aria-describedby="cookie-description">
    <div class="glass border border-white/10 rounded-2xl p-6 md:p-8 shadow-2xl shadow-black/80">
        
        <!-- En-tête -->
        <div class="flex justify-between items-center border-b border-white/5 pb-4 mb-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center shrink-0">
                    <i class="fas fa-cookie-bite text-amber-400 text-lg"></i>
                </div>
                <div>
                    <h4 id="cookie-title" class="text-white font-extrabold text-lg tracking-tight">
                        <?php echo t('cookie.title') ?? 'Respect de votre vie privée'; ?>
                    </h4>
                    <p class="text-xs text-gray-500 mt-0.5">Nous utilisons des cookies pour améliorer votre expérience</p>
                </div>
            </div>
            
            <!-- Sélecteur de langue -->
            <div class="flex items-center gap-1 bg-white/5 rounded-xl p-1 border border-white/5 text-xs font-bold">
                <a href="?lang=fr" class="px-3 py-1.5 rounded-lg transition <?php echo $lang === 'fr' ? 'bg-sky-600 text-white shadow' : 'text-gray-400 hover:text-white'; ?>">FR</a>
                <a href="?lang=en" class="px-3 py-1.5 rounded-lg transition <?php echo $lang === 'en' ? 'bg-sky-600 text-white shadow' : 'text-gray-400 hover:text-white'; ?>">EN</a>
            </div>
        </div>

        <!-- Description -->
        <div class="mb-6">
            <p id="cookie-description" class="text-gray-300 text-sm leading-relaxed mb-4">
                <?php echo t('cookie.text') ?? 'Ce site utilise des cookies pour fonctionner correctement et améliorer votre expérience. Vous pouvez accepter tous les cookies, les refuser ou personnaliser vos préférences.'; ?>
            </p>
            <div class="flex flex-wrap gap-x-3 gap-y-1 text-xs">
                <a href="/mentions-legales/" class="text-sky-400 hover:text-sky-300 transition flex items-center gap-1">
                    <i class="fas fa-file-contract text-[10px]"></i>
                    <?php echo t('cookie.ml') ?? 'Mentions légales'; ?>
                </a>
                <span class="text-gray-600">•</span>
                <a href="/cgu/" class="text-sky-400 hover:text-sky-300 transition flex items-center gap-1">
                    <i class="fas fa-file-alt text-[10px]"></i>
                    <?php echo t('cookie.cgu') ?? 'CGU'; ?>
                </a>
                <span class="text-gray-600">•</span>
                <a href="/politique-confidentialite/" class="text-sky-400 hover:text-sky-300 transition flex items-center gap-1">
                    <i class="fas fa-shield-alt text-[10px]"></i>
                    <?php echo t('cookie.pp') ?? 'Confidentialité'; ?>
                </a>
                <span class="text-gray-600">•</span>
                <a href="/cookies/" class="text-sky-400 hover:text-sky-300 transition flex items-center gap-1">
                    <i class="fas fa-cookie text-[10px]"></i>
                    <?php echo t('cookie.policy') ?? 'Politique cookies'; ?>
                </a>
            </div>
        </div>

        <!-- Types de cookies (détails) -->
        <div id="cookie-details" class="hidden mb-6 space-y-3">
            <div class="bg-white/[0.02] border border-white/5 rounded-xl p-4">
                <div class="flex items-center justify-between mb-2">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" id="cookie-essential" checked disabled class="w-4 h-4 accent-sky-500 rounded">
                        <span class="text-sm font-semibold text-white">Cookies essentiels</span>
                        <span class="text-[10px] bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 px-2 py-0.5 rounded-full font-bold">Obligatoire</span>
                    </label>
                </div>
                <p class="text-xs text-gray-500 ml-6">Nécessaires au fonctionnement du site (session, panier, préférences). Ne peuvent pas être désactivés.</p>
            </div>

            <div class="bg-white/[0.02] border border-white/5 rounded-xl p-4">
                <div class="flex items-center justify-between mb-2">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" id="cookie-analytics" class="w-4 h-4 accent-sky-500 rounded">
                        <span class="text-sm font-semibold text-white">Cookies analytiques</span>
                    </label>
                </div>
                <p class="text-xs text-gray-500 ml-6">Nous aident à comprendre comment les visiteurs utilisent le site pour l'améliorer.</p>
            </div>

            <div class="bg-white/[0.02] border border-white/5 rounded-xl p-4">
                <div class="flex items-center justify-between mb-2">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" id="cookie-marketing" class="w-4 h-4 accent-sky-500 rounded">
                        <span class="text-sm font-semibold text-white">Cookies marketing</span>
                    </label>
                </div>
                <p class="text-xs text-gray-500 ml-6">Utilisés pour afficher des publicités pertinentes (nous n'en utilisons pas actuellement).</p>
            </div>
        </div>

        <!-- Actions -->
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
            <button onclick="toggleCookieDetails()" id="cookie-customize-btn" class="text-gray-400 hover:text-white border border-white/10 hover:bg-white/10 px-4 py-3 rounded-xl text-sm font-semibold transition bg-white/5 text-center whitespace-nowrap flex items-center justify-center gap-2">
                <i class="fas fa-sliders-h text-xs"></i>
                <span><?php echo t('cookie.customize') ?? 'Personnaliser'; ?></span>
                <i class="fas fa-chevron-down text-[10px] transition" id="cookie-chevron"></i>
            </button>
            
            <div class="flex items-center gap-3 flex-1 justify-end">
                <button onclick="setCookieConsent('denied')" class="text-gray-300 hover:text-white border border-white/10 hover:bg-white/10 px-5 py-3 rounded-xl text-sm font-bold transition bg-white/5 text-center whitespace-nowrap">
                    <?php echo t('cookie.deny') ?? 'Tout refuser'; ?>
                </button>
                <button onclick="setCookieConsent('accepted')" class="bg-sky-600 hover:bg-sky-500 text-white px-6 py-3 rounded-xl text-sm font-bold transition shadow-xl shadow-sky-900/30 text-center whitespace-nowrap">
                    <?php echo t('cookie.accept') ?? 'Tout accepter'; ?>
                </button>
            </div>
        </div>

        <!-- Bouton de sauvegarde personnalisé (caché par défaut) -->
        <div id="cookie-save-section" class="hidden mt-4 pt-4 border-t border-white/5">
            <button onclick="setCustomCookieConsent()" class="w-full bg-emerald-600 hover:bg-emerald-500 text-white px-6 py-3 rounded-xl text-sm font-bold transition shadow-xl">
                <i class="fas fa-check mr-2"></i>
                <?php echo t('cookie.save_preferences') ?? 'Enregistrer mes préférences'; ?>
            </button>
        </div>

    </div>
</div>

<!-- Fallback sans JavaScript -->
<noscript>
    <div class="fixed bottom-8 left-1/2 -translate-x-1/2 w-[calc(100%-2rem)] max-w-4xl z-50">
        <div class="glass border border-white/10 rounded-2xl p-6 shadow-2xl">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center">
                    <i class="fas fa-cookie-bite text-amber-400"></i>
                </div>
                <h4 class="text-white font-extrabold text-lg">Cookies</h4>
            </div>
            <p class="text-gray-300 text-sm mb-4">
                Ce site utilise des cookies pour fonctionner. Veuillez activer JavaScript pour personnaliser vos préférences, ou continuer votre navigation en acceptant tous les cookies.
            </p>
            <a href="/" class="inline-block bg-sky-600 hover:bg-sky-500 text-white px-6 py-3 rounded-xl text-sm font-bold transition">
                Continuer
            </a>
        </div>
    </div>
</noscript>

<script>
// ═══════════════════════════════════════════════════════════════
// GESTION DU BANDEAU DE COOKIES
// ═══════════════════════════════════════════════════════════════

document.addEventListener("DOMContentLoaded", function () {
    const banner = document.getElementById('cookie-banner');
    if (banner) {
        // Apparition fluide à l'écran
        setTimeout(() => {
            banner.classList.remove('translate-y-20', 'opacity-0');
        }, 800);
    }
});

// Afficher/masquer les détails
function toggleCookieDetails() {
    const details = document.getElementById('cookie-details');
    const saveSection = document.getElementById('cookie-save-section');
    const chevron = document.getElementById('cookie-chevron');
    
    if (details.classList.contains('hidden')) {
        details.classList.remove('hidden');
        saveSection.classList.remove('hidden');
        chevron.style.transform = 'rotate(180deg)';
    } else {
        details.classList.add('hidden');
        saveSection.classList.add('hidden');
        chevron.style.transform = 'rotate(0deg)';
    }
}

// Accepter tous les cookies
function setCookieConsent(status) {
    const banner = document.getElementById('cookie-banner');
    
    if (banner) {
        banner.classList.add('translate-y-20', 'opacity-0');
        setTimeout(() => banner.remove(), 500);
    }

    const consent = {
        essential: true,
        analytics: status === 'accepted',
        marketing: status === 'accepted',
        timestamp: new Date().toISOString()
    };

    saveCookiePreferences(consent);
    
    // Trigger custom event pour les autres scripts
    window.dispatchEvent(new CustomEvent('cookieConsent', { detail: consent }));
}

// Sauvegarder les préférences personnalisées
function setCustomCookieConsent() {
    const banner = document.getElementById('cookie-banner');
    
    const consent = {
        essential: true,
        analytics: document.getElementById('cookie-analytics').checked,
        marketing: document.getElementById('cookie-marketing').checked,
        timestamp: new Date().toISOString()
    };

    if (banner) {
        banner.classList.add('translate-y-20', 'opacity-0');
        setTimeout(() => banner.remove(), 500);
    }

    saveCookiePreferences(consent);
    
    // Trigger custom event pour les autres scripts
    window.dispatchEvent(new CustomEvent('cookieConsent', { detail: consent }));
}

// Sauvegarder les préférences dans un cookie
function saveCookiePreferences(consent) {
    const date = new Date();
    date.setTime(date.getTime() + (365 * 24 * 60 * 60 * 1000)); // 1 an
    const expires = "; expires=" + date.toUTCString();
    
    // Cookie principal (simple)
    document.cookie = "orinheberge_cookies=" + (consent.analytics ? 'accepted' : 'denied') + expires + "; path=/; SameSite=Lax; Secure";
    
    // Cookie détaillé (JSON)
    document.cookie = "orinheberge_cookies_prefs=" + encodeURIComponent(JSON.stringify(consent)) + expires + "; path=/; SameSite=Lax; Secure";
    
    console.log('🍪 Préférences cookies enregistrées:', consent);
}

// Vérifier les préférences au chargement (pour autres scripts)
function getCookiePreferences() {
    const prefs = document.cookie.split('; ').find(row => row.startsWith('orinheberge_cookies_prefs='));
    if (prefs) {
        try {
            return JSON.parse(decodeURIComponent(prefs.split('=')[1]));
        } catch (e) {
            return null;
        }
    }
    return null;
}

// Exemple d'utilisation dans d'autres scripts :
// window.addEventListener('cookieConsent', function(e) {
//     if (e.detail.analytics) {
//         // Initialiser Google Analytics
//     }
// });
</script>
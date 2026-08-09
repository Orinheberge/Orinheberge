/**
 * OrinHeberge — Navigation Mobile Interactive (v2)
 * Gère le menu burger, l'overlay, les dropdowns et les animations
 * Compatible avec la navbar améliorée (double icône + overlay)
 */

'use strict';

console.log('[Navbar Console Debug] Script navbar.js v2 initialisé.');

// ============================================
// INITIALISATION UNIQUE DES ÉVÉNEMENTS
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    console.log('[Navbar Console Debug] DOM chargé.');

    const menu = document.getElementById('mobileMenu');
    const burgerBtn = document.getElementById('mobileMenuBtn');
    const shopDropdownBtn = document.getElementById('mobileShopDropdownBtn');
    const mobileOverlay = document.getElementById('mobileOverlay');

    // 1. Bouton burger
    if (burgerBtn) {
        burgerBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            toggleMobileMenu();
        });
        console.log('[Navbar Console Debug] Écouteur attaché sur le bouton burger.');
    } else {
        console.warn('[Navbar Console Debug] #mobileMenuBtn non détecté dans le DOM.');
    }

    // 2. Overlay (fermeture au clic extérieur)
    if (mobileOverlay) {
        mobileOverlay.addEventListener('click', function() {
            if (isMenuOpen()) {
                closeMobileMenu();
            }
        });
        console.log('[Navbar Console Debug] Écouteur attaché sur l\'overlay mobile.');
    }

    // 3. Dropdown Boutique Mobile
    if (shopDropdownBtn) {
        shopDropdownBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            toggleMobileDropdown('shopDropdown');
        });
        console.log('[Navbar Console Debug] Écouteur attaché sur le dropdown boutique mobile.');
    }

    // 4. Initialisation de l'état fermé
    if (menu) {
        menu.style.maxHeight = '0px';

        // Ferme le menu au clic sur un lien normal
        menu.querySelectorAll('a').forEach(function(link) {
            link.addEventListener('click', function() {
                if (window.innerWidth < 768) {
                    const hrefAttr = link.getAttribute('href') || '';
                    if (hrefAttr === '#' || hrefAttr.startsWith('javascript:')) {
                        return;
                    }
                    closeMobileMenu();
                }
            });
        });
    }
});

// ============================================
// FONCTIONS UTILITAIRES
// ============================================

function isMenuOpen() {
    const menu = document.getElementById('mobileMenu');
    if (!menu) return false;
    return menu.style.maxHeight !== '0px' && menu.style.maxHeight !== '';
}

function setIconsState(open) {
    const iconBars = document.getElementById('menuIconBars');
    const iconX = document.getElementById('menuIconX');

    if (!iconBars || !iconX) return;

    if (open) {
        // Afficher X, cacher bars
        iconBars.classList.add('opacity-0', 'scale-50');
        iconBars.classList.remove('opacity-100', 'scale-100');
        iconX.classList.remove('opacity-0', 'scale-50');
        iconX.classList.add('opacity-100', 'scale-100');
    } else {
        // Afficher bars, cacher X
        iconBars.classList.remove('opacity-0', 'scale-50');
        iconBars.classList.add('opacity-100', 'scale-100');
        iconX.classList.add('opacity-0', 'scale-50');
        iconX.classList.remove('opacity-100', 'scale-100');
    }
}

function setOverlayState(open) {
    const overlay = document.getElementById('mobileOverlay');
    if (!overlay) return;

    if (open) {
        overlay.classList.remove('opacity-0', 'pointer-events-none');
        overlay.classList.add('opacity-100', 'pointer-events-auto');
    } else {
        overlay.classList.add('opacity-0', 'pointer-events-none');
        overlay.classList.remove('opacity-100', 'pointer-events-auto');
    }
}

function setBodyScroll(locked) {
    document.body.style.overflow = locked ? 'hidden' : '';
}

// ============================================
// FONCTIONS PRINCIPALES
// ============================================

function closeMobileMenu() {
    console.log('[Navbar Console Debug] Fermeture du menu.');
    const menu = document.getElementById('mobileMenu');

    if (menu) {
        menu.style.maxHeight = '0px';
    }

    setIconsState(false);
    setOverlayState(false);
    setBodyScroll(false);

    // Fermeture automatique des sous-menus
    const dropdown = document.getElementById('shopDropdown');
    const dropdownIcon = document.getElementById('shopDropdownIcon');
    if (dropdown) dropdown.style.maxHeight = '0px';
    if (dropdownIcon) dropdownIcon.style.transform = 'rotate(0deg)';
}

function openMobileMenu() {
    console.log('[Navbar Console Debug] Ouverture du menu.');
    const menu = document.getElementById('mobileMenu');

    if (!menu) return;

    menu.style.maxHeight = menu.scrollHeight + 'px';
    setIconsState(true);
    setOverlayState(true);
    setBodyScroll(true);
}

function toggleMobileMenu() {
    if (isMenuOpen()) {
        closeMobileMenu();
    } else {
        openMobileMenu();
    }
}

function toggleMobileDropdown(id) {
    const dropdown = document.getElementById(id);
    const icon = document.getElementById(id + 'Icon');
    const menu = document.getElementById('mobileMenu');

    if (!dropdown || !menu) return;

    const isClosed = dropdown.style.maxHeight === '0px' || dropdown.style.maxHeight === '';

    if (isClosed) {
        dropdown.style.maxHeight = dropdown.scrollHeight + 'px';
        if (icon) icon.style.transform = 'rotate(180deg)';

        // Ajuste la hauteur du menu mobile parent pour contenir le dropdown ouvert
        setTimeout(function() {
            if (isMenuOpen()) {
                menu.style.maxHeight = menu.scrollHeight + 'px';
            }
        }, 310);
    } else {
        dropdown.style.maxHeight = '0px';
        if (icon) icon.style.transform = 'rotate(0deg)';

        setTimeout(function() {
            if (isMenuOpen()) {
                menu.style.maxHeight = menu.scrollHeight + 'px';
            }
        }, 310);
    }
}

// ============================================
// ÉVÉNEMENTS GLOBAUX
// ============================================

// Événement clavier (Échap)
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && isMenuOpen()) {
        closeMobileMenu();
    }
});

// Événement Redimensionnement fenêtre
window.addEventListener('resize', function() {
    if (window.innerWidth >= 768 && isMenuOpen()) {
        closeMobileMenu();
    }

    // Recalculer la hauteur si le menu est ouvert (orientation, etc.)
    if (window.innerWidth < 768 && isMenuOpen()) {
        const menu = document.getElementById('mobileMenu');
        if (menu) {
            menu.style.maxHeight = menu.scrollHeight + 'px';
        }
    }
});

// Empêcher le scroll "bounce" iOS quand le menu est ouvert
document.addEventListener('touchmove', function(e) {
    if (isMenuOpen()) {
        const menu = document.getElementById('mobileMenu');
        if (menu && !menu.contains(e.target)) {
            e.preventDefault();
        }
    }
}, { passive: false });
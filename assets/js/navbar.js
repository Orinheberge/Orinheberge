/**
 * OrinHeberge — Navigation Mobile Interactive (v4 FINAL)
 * Gère le menu burger, l'overlay, les dropdowns, l'accessibilité et les gestes tactiles
 * Compatible avec la navbar v4 (scroll forcé + z-index fix)
 */

'use strict';

// ============================================
// CONFIGURATION
// ============================================
const CONFIG = {
    debug: false, // true en dev pour les logs
    breakpoint: 768,
    swipeThreshold: 50, // px pour swipe
    resizeDebounce: 150, // ms
};

const log = (...args) => {
    if (CONFIG.debug) console.log('[Navbar]', ...args);
};
const warn = (...args) => console.warn('[Navbar]', ...args);

log('Script navbar.js v4 initialisé.');

// ============================================
// INITIALISATION
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    log('DOM chargé.');

    const elements = {
        menu: document.getElementById('mobileMenu'),
        burgerBtn: document.getElementById('mobileMenuBtn'),
        shopDropdownBtn: document.getElementById('mobileShopDropdownBtn'),
        overlay: document.getElementById('mobileOverlay'),
        nav: document.querySelector('nav'),
    };

    if (!elements.menu) {
        warn('#mobileMenu non détecté dans le DOM.');
        return;
    }

    const state = {
        menuOpen: false,
        shopDropdownOpen: false,
        touchStartX: 0,
        touchStartY: 0,
    };

    // ============================================
    // 1. BOUTON BURGER
    // ============================================
    if (elements.burgerBtn) {
        elements.burgerBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            toggleMobileMenu();
        });
        log('Écouteur attaché sur le bouton burger.');
    } else {
        warn('#mobileMenuBtn non détecté dans le DOM.');
    }

    // ============================================
    // 2. OVERLAY
    // ============================================
    if (elements.overlay) {
        elements.overlay.addEventListener('click', function() {
            if (state.menuOpen) closeMobileMenu();
        });
        log('Écouteur attaché sur l\'overlay mobile.');
    }

    // ============================================
    // 3. DROPDOWN BOUTIQUE MOBILE
    // ============================================
    if (elements.shopDropdownBtn) {
        elements.shopDropdownBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            toggleMobileDropdown('shopDropdown');
        });
        log('Écouteur attaché sur le dropdown boutique mobile.');
    }

    // ============================================
    // 4. ÉTAT INITIAL FERMÉ
    // ============================================
    elements.menu.style.maxHeight = '0px';

    elements.menu.querySelectorAll('a').forEach(function(link) {
        link.addEventListener('click', function() {
            if (window.innerWidth < CONFIG.breakpoint) {
                const hrefAttr = link.getAttribute('href') || '';
                if (hrefAttr === '#' || hrefAttr.startsWith('javascript:')) {
                    return;
                }
                closeMobileMenu();
            }
        });
    });

    // ============================================
    // 5. FERMETURE DROPDOWNS AU CLIC EXTÉRIEUR
    // ============================================
    document.addEventListener('click', function(e) {
        // Fermer dropdown boutique
        if (state.shopDropdownOpen) {
            const shopDropdown = document.getElementById('shopDropdown');
            const shopBtn = document.getElementById('mobileShopDropdownBtn');
            if (shopDropdown && !shopDropdown.contains(e.target) && 
                shopBtn && !shopBtn.contains(e.target)) {
                closeMobileDropdown('shopDropdown');
            }
        }

        // Fermer sélecteurs de langue ouverts
        const openLangSwitcher = document.querySelector('.lang-switcher-dropdown.opacity-100');
        if (openLangSwitcher && !openLangSwitcher.contains(e.target)) {
            const switcher = openLangSwitcher.closest('[data-lang-switcher]');
            if (switcher && !switcher.contains(e.target)) {
                const btn = switcher.querySelector('[data-lang-toggle]');
                openLangSwitcher.classList.add('opacity-0', 'invisible');
                openLangSwitcher.classList.remove('opacity-100', 'visible');
                if (btn) btn.setAttribute('aria-expanded', 'false');
            }
        }
    });

    // ============================================
    // 6. GESTES TACTILES (swipe)
    // ============================================
    if ('ontouchstart' in window) {
        elements.menu.addEventListener('touchstart', function(e) {
            state.touchStartX = e.touches[0].clientX;
            state.touchStartY = e.touches[0].clientY;
        }, { passive: true });

        elements.menu.addEventListener('touchend', function(e) {
            const touchEndX = e.changedTouches[0].clientX;
            const touchEndY = e.changedTouches[0].clientY;
            const diffX = touchEndX - state.touchStartX;
            const diffY = touchEndY - state.touchStartY;

            // Swipe vers la gauche = fermer
            if (state.menuOpen && diffX < -CONFIG.swipeThreshold && Math.abs(diffX) > Math.abs(diffY)) {
                closeMobileMenu();
            }
        }, { passive: true });
    }

    // ============================================
    // FONCTIONS INTERNES
    // ============================================

    function isMenuOpen() {
        return state.menuOpen;
    }

    function setIconsState(open) {
        const iconBars = document.getElementById('menuIconBars');
        const iconX = document.getElementById('menuIconX');

        if (!iconBars || !iconX) return;

        if (open) {
            iconBars.classList.add('opacity-0', 'scale-50');
            iconBars.classList.remove('opacity-100', 'scale-100');
            iconX.classList.remove('opacity-0', 'scale-50');
            iconX.classList.add('opacity-100', 'scale-100');
            elements.burgerBtn?.setAttribute('aria-expanded', 'true');
        } else {
            iconBars.classList.remove('opacity-0', 'scale-50');
            iconBars.classList.add('opacity-100', 'scale-100');
            iconX.classList.add('opacity-0', 'scale-50');
            iconX.classList.remove('opacity-100', 'scale-100');
            elements.burgerBtn?.setAttribute('aria-expanded', 'false');
        }
    }

    function setOverlayState(open) {
        if (!elements.overlay) return;

        if (open) {
            elements.overlay.classList.remove('opacity-0', 'pointer-events-none');
            elements.overlay.classList.add('opacity-100', 'pointer-events-auto');
        } else {
            elements.overlay.classList.add('opacity-0', 'pointer-events-none');
            elements.overlay.classList.remove('opacity-100', 'pointer-events-auto');
        }
    }

    function setBodyScroll(locked) {
        document.body.classList.toggle('menu-open', locked);
        
        if (locked) {
            document.body.dataset.scrollPosition = window.scrollY;
            document.body.style.top = `-${window.scrollY}px`;
            document.body.style.position = 'fixed';
            document.body.style.width = '100%';
        } else {
            const scrollPosition = parseInt(document.body.dataset.scrollPosition || '0');
            document.body.style.top = '';
            document.body.style.position = '';
            document.body.style.width = '';
            window.scrollTo(0, scrollPosition);
        }
    }

    function closeMobileDropdown(id) {
        const dropdown = document.getElementById(id);
        const icon = document.getElementById(id + 'Icon');

        if (dropdown) dropdown.style.maxHeight = '0px';
        if (icon) icon.style.transform = 'rotate(0deg)';
        
        if (id === 'shopDropdown') state.shopDropdownOpen = false;
    }

    function closeMobileMenu() {
        log('Fermeture du menu.');
        
        // Remettre maxHeight à scrollHeight pour la transition
        elements.menu.style.maxHeight = elements.menu.scrollHeight + 'px';
        
        // Force reflow
        elements.menu.offsetHeight;
        
        // Puis fermer
        elements.menu.style.maxHeight = '0px';
        state.menuOpen = false;

        setIconsState(false);
        setOverlayState(false);
        setBodyScroll(false);

        closeMobileDropdown('shopDropdown');

        // Fermer tous les sélecteurs de langue
        document.querySelectorAll('.lang-switcher-dropdown.opacity-100').forEach(dropdown => {
            dropdown.classList.add('opacity-0', 'invisible');
            dropdown.classList.remove('opacity-100', 'visible');
            const btn = dropdown.closest('[data-lang-switcher]')?.querySelector('[data-lang-toggle]');
            if (btn) btn.setAttribute('aria-expanded', 'false');
        });
    }

function openMobileMenu() {
    log('Ouverture du menu.');

    // 🔑 Calculer la hauteur réelle
    const navbarHeight = elements.nav?.offsetHeight || 80;
    const maxHeight = window.innerHeight - navbarHeight - 20;
    const menuHeight = Math.min(elements.menu.scrollHeight, maxHeight);
    
    // Forcer la hauteur avec scroll
    elements.menu.style.maxHeight = menuHeight + 'px';
    elements.menu.style.overflowY = 'auto';
    
    state.menuOpen = true;

    setIconsState(true);
    setOverlayState(true);
    setBodyScroll(true);

    // Focus accessibilité
    setTimeout(() => {
        const firstLink = elements.menu.querySelector('a');
        if (firstLink) firstLink.focus();
    }, 300);
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

        if (!dropdown) return;

        const isClosed = dropdown.style.maxHeight === '0px' || dropdown.style.maxHeight === '';

        if (isClosed) {
            dropdown.style.maxHeight = dropdown.scrollHeight + 'px';
            if (icon) icon.style.transform = 'rotate(180deg)';
            if (id === 'shopDropdown') state.shopDropdownOpen = true;
        } else {
            closeMobileDropdown(id);
        }
        
        // 🔑 Recalculer après ouverture/fermeture du dropdown
        setTimeout(() => {
            if (state.menuOpen) {
                // Retirer le style inline pour laisser le CSS gérer
                elements.menu.style.maxHeight = '';
            }
        }, 350);
    }

    // ============================================
    // 7. ÉVÉNEMENTS GLOBAUX
    // ============================================

    // Touche Échap
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            if (state.menuOpen) {
                closeMobileMenu();
                elements.burgerBtn?.focus();
            }
        }
    });

    // Redimensionnement avec debounce
    let resizeTimeout;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(function() {
            if (window.innerWidth >= CONFIG.breakpoint && state.menuOpen) {
                closeMobileMenu();
            } else if (window.innerWidth < CONFIG.breakpoint && state.menuOpen) {
                // Retirer le style inline pour laisser le CSS gérer
                elements.menu.style.maxHeight = '';
            }
        }, CONFIG.resizeDebounce);
    });

    // Orientation change
    window.addEventListener('orientationchange', function() {
        setTimeout(() => {
            if (state.menuOpen) {
                elements.menu.style.maxHeight = '';
            }
        }, 200);
    });

    // Scroll bounce iOS
    if ('ontouchstart' in window) {
        document.addEventListener('touchmove', function(e) {
            if (state.menuOpen) {
                const target = e.target;
                if (elements.menu.contains(target)) {
                    const isScrollable = target.scrollHeight > target.clientHeight;
                    if (!isScrollable) {
                        e.preventDefault();
                    }
                } else {
                    e.preventDefault();
                }
            }
        }, { passive: false });
    }

    // API publique
    window.OrinNavbar = {
        open: openMobileMenu,
        close: closeMobileMenu,
        toggle: toggleMobileMenu,
        isOpen: () => state.menuOpen,
    };

    log('Initialisation terminée.');
});
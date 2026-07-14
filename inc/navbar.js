/**
 * OrinHeberge — Navigation Mobile Interactive
 * Gère le menu burger, les dropdowns et les animations
 */

(function() {
    'use strict';

    // ============================================
    // INITIALISATION DES ÉVÉNEMENTS
    // ============================================
    document.addEventListener('DOMContentLoaded', function() {
        console.log('[Navbar Console Debug] DOM fully loaded and parsed.');
        const menu = document.getElementById('mobileMenu');
        const burgerBtn = document.getElementById('mobileMenuBtn');
        
        // 1. Gestion du clic sur le bouton burger
        if (burgerBtn) {
            console.log('[Navbar Console Debug] Target burger button #mobileMenuBtn found. Binding event listener.');
            burgerBtn.addEventListener('click', function(e) {
                console.log('[Navbar Console Debug] Burger button clicked.');
                toggleMobileMenu();
            });
        } else {
            console.error('[Navbar Console Debug] Error: Target burger button with ID #mobileMenuBtn was not found in DOM!');
        }

        // 2. Initialisation de l'état du menu mobile
        if (menu) {
            menu.style.maxHeight = '0px';
            menu.style.opacity = '0';
            menu.style.overflow = 'hidden';
            
            // Ferme le menu au clic sur un lien (sauf les boutons de dropdown)
            menu.querySelectorAll('a').forEach(function(link) {
                link.addEventListener('click', function(e) {
                    if (window.innerWidth < 768) {
                        const onclickAttr = link.getAttribute('onclick') || '';
                        const hrefAttr = link.getAttribute('href') || '';
                        
                        if (onclickAttr.includes('toggleMobileDropdown') || hrefAttr === '#' || hrefAttr.startsWith('javascript:')) {
                            return;
                        }
                        closeMobileMenu();
                    }
                });
            });
        } else {
            console.warn('[Navbar Console Debug] Warning: Mobile menu wrapper #mobileMenu not found.');
        }
        
        console.log('[Navbar Console Debug] Script navbar.js loaded & bound successfully.');
    });

    // ============================================
    // FERMETURE DU MENU MOBILE
    // ============================================
    function closeMobileMenu() {
        console.log('[Navbar Console Debug] Closing mobile menu.');
        const menu = document.getElementById('mobileMenu');
        const icon = document.getElementById('menuIcon');
        
        if (menu) {
            menu.style.maxHeight = '0px';
            menu.style.opacity = '0';
        }
        if (icon) {
            icon.className = 'fas fa-bars';
        }
        
        // Ferme aussi tous les dropdowns internes
        document.querySelectorAll('[id^="shopDropdown"]').forEach(function(dropdown) {
            dropdown.style.maxHeight = '0px';
            const icon = document.getElementById(dropdown.id + 'Icon');
            if (icon) icon.style.transform = 'rotate(0deg)';
        });
    }

    // ============================================
    // TOGGLE MENU MOBILE (BURGER)
    // ============================================
    function toggleMobileMenu() {
        console.log('[Navbar Console Debug] Toggling mobile menu...');
        const menu = document.getElementById('mobileMenu');
        const icon = document.getElementById('menuIcon');
        
        if (!menu || !icon) {
            console.error('[Navbar Console Debug] Missing menu or icon elements for toggling.', { menu, icon });
            return;
        }

        const isClosed = menu.style.maxHeight === '0px' || menu.style.maxHeight === '' || menu.style.opacity === '0';
        
        if (isClosed) {
            console.log('[Navbar Console Debug] Opening menu.');
            menu.style.opacity = '1';
            menu.style.maxHeight = menu.scrollHeight + 'px';
            icon.className = 'fas fa-times';
        } else {
            console.log('[Navbar Console Debug] Closing menu via toggle.');
            closeMobileMenu();
        }
    }

    // ============================================
    // DROPDOWN INTERNE MOBILE (BOUTIQUE)
    // ============================================
    function toggleMobileDropdown(id) {
        console.log(`[Navbar Console Debug] Toggling dropdown for ID: ${id}`);
        const dropdown = document.getElementById(id);
        const icon = document.getElementById(id + 'Icon');
        const menu = document.getElementById('mobileMenu');
        
        if (!dropdown || !menu) return;

        const isClosed = dropdown.style.maxHeight === '0px' || dropdown.style.maxHeight === '';
        
        if (isClosed) {
            dropdown.style.maxHeight = dropdown.scrollHeight + 'px';
            if (icon) icon.style.transform = 'rotate(180deg)';
            
            setTimeout(function() {
                menu.style.maxHeight = menu.scrollHeight + 'px';
            }, 310);
        } else {
            dropdown.style.maxHeight = '0px';
            if (icon) icon.style.transform = 'rotate(0deg)';
            
            menu.style.maxHeight = (menu.scrollHeight - dropdown.scrollHeight) + 'px';
        }
    }

    // ============================================
    // ÉVÉNEMENTS GLOBAUX
    // ============================================
    window.addEventListener('resize', function() {
        if (window.innerWidth >= 768) {
            closeMobileMenu();
        }
    });

    document.addEventListener('click', function(e) {
        const menu = document.getElementById('mobileMenu');
        const burgerBtn = document.getElementById('mobileMenuBtn');
        const icon = document.getElementById('menuIcon');
        
        if (menu && !menu.contains(e.target) && (!burgerBtn || !burgerBtn.contains(e.target)) && e.target !== icon) {
            if (menu.style.opacity === '1') {
                console.log('[Navbar Console Debug] Clicking outside active menu, closing.');
                closeMobileMenu();
            }
        }
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeMobileMenu();
        }
    });

    // Exposition globale ciblée et sécurisée
    window.toggleMobileMenu = toggleMobileMenu;
    window.toggleMobileDropdown = toggleMobileDropdown;

    // API Publique externe au besoin
    window.Navbar = {
        open: function() {
            const menu = document.getElementById('mobileMenu');
            const icon = document.getElementById('menuIcon');
            if (menu && icon) {
                menu.style.opacity = '1';
                menu.style.maxHeight = menu.scrollHeight + 'px';
                icon.className = 'fas fa-times';
            }
        },
        close: closeMobileMenu,
        toggle: toggleMobileMenu,
        isOpen: function() {
            const menu = document.getElementById('mobileMenu');
            return menu && menu.style.opacity === '1';
        }
    };

})();
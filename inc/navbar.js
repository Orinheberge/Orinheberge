/**
 * OrinHeberge — Navigation Mobile Interactive
 * Gère le menu burger, les dropdowns et les animations
 */

'use strict';

console.log('[Navbar Console Debug] Script navbar.js initialisé.');

// ============================================
// INITIALISATION UNIQUE DES ÉVÉNEMENTS
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    console.log('[Navbar Console Debug] DOM chargé.');

    const menu = document.getElementById('mobileMenu');
    const burgerBtn = document.getElementById('mobileMenuBtn');
    const shopDropdownBtn = document.getElementById('mobileShopDropdownBtn');

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

    // 2. Dropdown Boutique Mobile
    if (shopDropdownBtn) {
        shopDropdownBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            toggleMobileDropdown('shopDropdown');
        });
        console.log('[Navbar Console Debug] Écouteur attaché sur le dropdown boutique mobile.');
    }

    // 3. Initialisation de l'état fermé
    if (menu) {
        menu.style.maxHeight = '0px';
        menu.style.opacity = '0';
        menu.style.overflow = 'hidden';

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
// FONCTIONS GLOBALES
// ============================================

function closeMobileMenu() {
    console.log('[Navbar Console Debug] Fermeture du menu.');
    const menu = document.getElementById('mobileMenu');
    const icon = document.getElementById('menuIcon');
    
    if (menu) {
        menu.style.maxHeight = '0px';
        menu.style.opacity = '0';
    }
    if (icon) {
        icon.className = 'fas fa-bars';
    }
    
    // Fermeture automatique des sous-menus
    const dropdown = document.getElementById('shopDropdown');
    const dropdownIcon = document.getElementById('shopDropdownIcon');
    if (dropdown) dropdown.style.maxHeight = '0px';
    if (dropdownIcon) dropdownIcon.style.transform = 'rotate(0deg)';
}

function toggleMobileMenu() {
    const menu = document.getElementById('mobileMenu');
    const icon = document.getElementById('menuIcon');
    
    if (!menu || !icon) return;

    const isClosed = menu.style.maxHeight === '0px' || menu.style.maxHeight === '' || menu.style.opacity === '0';
    
    if (isClosed) {
        console.log('[Navbar Console Debug] Ouverture du menu.');
        menu.style.opacity = '1';
        menu.style.maxHeight = menu.scrollHeight + 'px';
        icon.className = 'fas fa-times';
    } else {
        closeMobileMenu();
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
            menu.style.maxHeight = menu.scrollHeight + 'px';
        }, 310);
    } else {
        dropdown.style.maxHeight = '0px';
        if (icon) icon.style.transform = 'rotate(0deg)';
        
        menu.style.maxHeight = (menu.scrollHeight - dropdown.scrollHeight) + 'px';
    }
}

// Événement clic extérieur
document.addEventListener('click', function(e) {
    const menu = document.getElementById('mobileMenu');
    const burgerBtn = document.getElementById('mobileMenuBtn');
    
    if (menu && !menu.contains(e.target) && (!burgerBtn || !burgerBtn.contains(e.target))) {
        if (menu.style.opacity === '1') {
            closeMobileMenu();
        }
    }
});

// Événement clavier (Échap)
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeMobileMenu();
    }
});

// Événement Redimensionnement fenêtre
window.addEventListener('resize', function() {
    if (window.innerWidth >= 768) {
        closeMobileMenu();
    }
});
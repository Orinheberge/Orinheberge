/**
 * OrinHeberge — Navigation Mobile Interactive
 * Gère le menu burger, les dropdowns et les animations
 */

'use strict';

// ============================================
// EXPOSITION GLOBALE IMMÉDIATE (Pour l'attribut onclick HTML)
// ============================================
window.toggleMobileMenu = toggleMobileMenu;
window.toggleMobileDropdown = toggleMobileDropdown;

// ============================================
// INITIALISATION DES ÉVÉNEMENTS
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    const menu = document.getElementById('mobileMenu');
    
    if (menu) {
        // Force l'état initial
        menu.style.maxHeight = '0px';
        menu.style.opacity = '0';
        menu.style.overflow = 'hidden';
        
        // Ferme le menu au clic sur un lien (sauf les boutons de dropdown)
        menu.querySelectorAll('a').forEach(function(link) {
            link.addEventListener('click', function(e) {
                if (window.innerWidth < 768) {
                    const onclickAttr = link.getAttribute('onclick') || '';
                    const hrefAttr = link.getAttribute('href') || '';
                    
                    // Si le lien est un déclencheur de dropdown ou un lien factice, on ne ferme pas le menu
                    if (onclickAttr.includes('toggleMobileDropdown') || hrefAttr === '#' || hrefAttr.startsWith('javascript:')) {
                        return;
                    }
                    closeMobileMenu();
                }
            });
        });
    }
    
    console.log('[Navbar] Initialisée avec succès');
});

// ============================================
// FERMETURE DU MENU MOBILE
// ============================================
function closeMobileMenu() {
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
    const menu = document.getElementById('mobileMenu');
    const icon = document.getElementById('menuIcon');
    
    if (!menu || !icon) return;

    const isClosed = menu.style.maxHeight === '0px' || menu.style.maxHeight === '' || menu.style.opacity === '0';
    
    if (isClosed) {
        // Ouvrir le menu
        menu.style.opacity = '1';
        menu.style.maxHeight = menu.scrollHeight + 'px';
        icon.className = 'fas fa-times';
    } else {
        // Fermer le menu
        closeMobileMenu();
    }
}

// ============================================
// DROPDOWN INTERNE MOBILE (BOUTIQUE)
// ============================================
function toggleMobileDropdown(id) {
    const dropdown = document.getElementById(id);
    const icon = document.getElementById(id + 'Icon');
    const menu = document.getElementById('mobileMenu');
    
    if (!dropdown || !menu) return;

    const isClosed = dropdown.style.maxHeight === '0px' || dropdown.style.maxHeight === '';
    
    if (isClosed) {
        // Ouvrir le dropdown
        dropdown.style.maxHeight = dropdown.scrollHeight + 'px';
        if (icon) icon.style.transform = 'rotate(180deg)';
        
        // Recalcule la hauteur du menu parent après la transition
        setTimeout(function() {
            menu.style.maxHeight = menu.scrollHeight + 'px';
        }, 310);
    } else {
        // Fermer le dropdown
        dropdown.style.maxHeight = '0px';
        if (icon) icon.style.transform = 'rotate(0deg)';
        
        // Recalcule immédiatement la hauteur du menu parent
        menu.style.maxHeight = (menu.scrollHeight - dropdown.scrollHeight) + 'px';
    }
}

// ============================================
// FERMETURE AU REDIMENSIONNEMENT
// ============================================
window.addEventListener('resize', function() {
    if (window.innerWidth >= 768) {
        closeMobileMenu();
    }
});

// ============================================
// FERMETURE AU CLIC EN DEHORS
// ============================================
document.addEventListener('click', function(e) {
    const menu = document.getElementById('mobileMenu');
    const burger = e.target.closest('[onclick*="toggleMobileMenu"]') || e.target.closest('#menuIcon');
    
    if (menu && !menu.contains(e.target) && (!burger || !burger.contains(e.target))) {
        if (menu.style.opacity === '1') {
            closeMobileMenu();
        }
    }
});

// ============================================
// FERMETURE AVEC TOUCHE ESCAPE
// ============================================
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeMobileMenu();
    }
});

// ============================================
// API PUBLIQUE (Optionnel)
// ============================================
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
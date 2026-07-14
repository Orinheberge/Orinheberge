/**
 * OrinHeberge — Navigation Mobile Interactive
 * Gère le menu burger, les dropdowns et les animations
 */

'use strict';

console.log('[Navbar Console] Script navbar.js chargé !');

// ============================================
// INITIALISATION DES ÉVÉNEMENTS DOM
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    console.log('[Navbar Console] DOM chargé.');
    const menu = document.getElementById('mobileMenu');
    const burgerBtn = document.getElementById('mobileMenuBtn');
    
    // Attache l'événement au bouton burger s'il existe (id="mobileMenuBtn")
    if (burgerBtn) {
        console.log('[Navbar Console] Bouton #mobileMenuBtn trouvé, écouteur attaché.');
        burgerBtn.addEventListener('click', toggleMobileMenu);
    } else {
        console.warn('[Navbar Console] Bouton #mobileMenuBtn introuvable. Tentative de secours via l\'icône.');
        const backupIcon = document.getElementById('menuIcon');
        if (backupIcon) {
            backupIcon.parentElement.addEventListener('click', toggleMobileMenu);
        }
    }

    if (menu) {
        // Force l'état initial fermé
        menu.style.maxHeight = '0px';
        menu.style.opacity = '0';
        menu.style.overflow = 'hidden';
        
        // Ferme le menu lors du clic sur un lien normal
        menu.querySelectorAll('a').forEach(function(link) {
            link.addEventListener('click', function() {
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
    }
});

// ============================================
// FONCTIONS GLOBALES (Accessibles partout)
// ============================================

function closeMobileMenu() {
    console.log('[Navbar Console] Fermeture du menu.');
    const menu = document.getElementById('mobileMenu');
    const icon = document.getElementById('menuIcon');
    
    if (menu) {
        menu.style.maxHeight = '0px';
        menu.style.opacity = '0';
    }
    if (icon) {
        icon.className = 'fas fa-bars';
    }
    
    // Ferme aussi tous les dropdowns ouverts
    document.querySelectorAll('[id^="shopDropdown"]').forEach(function(dropdown) {
        dropdown.style.maxHeight = '0px';
        const dropIcon = document.getElementById(dropdown.id + 'Icon');
        if (dropIcon) dropIcon.style.transform = 'rotate(0deg)';
    });
}

function toggleMobileMenu() {
    console.log('[Navbar Console] Clic détecté sur le burger.');
    const menu = document.getElementById('mobileMenu');
    const icon = document.getElementById('menuIcon');
    
    if (!menu || !icon) {
        console.error('[Navbar Console] Impossible de trouver le menu ou l\'icône dans le DOM !');
        return;
    }

    const isClosed = menu.style.maxHeight === '0px' || menu.style.maxHeight === '' || menu.style.opacity === '0';
    
    if (isClosed) {
        console.log('[Navbar Console] Ouverture du menu.');
        menu.style.opacity = '1';
        menu.style.maxHeight = menu.scrollHeight + 'px';
        icon.className = 'fas fa-times';
    } else {
        closeMobileMenu();
    }
}

function toggleMobileDropdown(id) {
    console.log(`[Navbar Console] Toggle dropdown mobile: ${id}`);
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
// LIENS GLOBAUX SUR WINDOW
// ============================================
window.toggleMobileMenu = toggleMobileMenu;
window.toggleMobileDropdown = toggleMobileDropdown;
window.closeMobileMenu = closeMobileMenu;
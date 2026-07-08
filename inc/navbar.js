// Initialisation au chargement de la page
document.addEventListener('DOMContentLoaded', () => {
    const menu = document.getElementById('mobileMenu');
    
    if (menu) {
        // Force l'état initial
        menu.style.maxHeight = '0px';
        menu.style.opacity = '0';
        menu.style.overflow = 'hidden';
        
        // Ferme le menu au clic sur un lien (sauf les boutons dropdown)
        menu.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth < 768) {
                    closeMobileMenu();
                }
            });
        });
    }
});

// Fonction pour fermer le menu mobile
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
    document.querySelectorAll('[id^="shopDropdown"]').forEach(dropdown => {
        dropdown.style.maxHeight = '0px';
        const icon = document.getElementById(dropdown.id + 'Icon');
        if (icon) icon.style.transform = 'rotate(0deg)';
    });
}

// Menu mobile principal (Burger)
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

// Dropdown interne mobile (Boutique)
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
        requestAnimationFrame(() => {
            setTimeout(() => {
                menu.style.maxHeight = menu.scrollHeight + 'px';
            }, 310); // Attend la fin de la transition du dropdown (300ms + 10ms)
        });
    } else {
        // Fermer le dropdown
        dropdown.style.maxHeight = '0px';
        if (icon) icon.style.transform = 'rotate(0deg)';
        
        // Recalcule immédiatement la hauteur du menu parent
        menu.style.maxHeight = menu.scrollHeight - dropdown.scrollHeight + 'px';
    }
}

// Sécurité : Ferme le menu si la fenêtre s'agrandit en mode PC
window.addEventListener('resize', () => {
    if (window.innerWidth >= 768) {
        closeMobileMenu();
    }
});

// Ferme le menu si on clique en dehors (optionnel)
document.addEventListener('click', (e) => {
    const menu = document.getElementById('mobileMenu');
    const burger = document.querySelector('[onclick="toggleMobileMenu()"]');
    
    if (menu && burger && !menu.contains(e.target) && !burger.contains(e.target)) {
        if (menu.style.opacity === '1') {
            closeMobileMenu();
        }
    }
});
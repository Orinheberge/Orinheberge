document.addEventListener('DOMContentLoaded', () => {
    const menu = document.getElementById('mobileMenu');
    const icon = document.getElementById('menuIcon');

    if (menu) {
        // Force l'état initial pour éviter le bug du premier clic
        menu.style.maxHeight = '0px';
        menu.style.opacity = '0';
    }
});

// Menu mobile principal (Burger)
function toggleMobileMenu() {
    const menu = document.getElementById('mobileMenu');
    const icon = document.getElementById('menuIcon');
    
    if (!menu || !icon) return;

    if (menu.style.maxHeight === '0px' || menu.classList.contains('opacity-0')) {
        menu.classList.remove('opacity-0');
        menu.style.maxHeight = menu.scrollHeight + "px";
        menu.style.opacity = '1';
        icon.className = 'fas fa-times';
    } else {
        menu.style.maxHeight = '0px';
        menu.style.opacity = '0';
        icon.className = 'fas fa-bars';
    }
}

// Dropdown interne mobile (Boutique)
function toggleMobileDropdown(id) {
    const dropdown = document.getElementById(id);
    const icon = document.getElementById(id + 'Icon');
    const menu = document.getElementById('mobileMenu');
    
    if (!dropdown || !menu) return;

    if (dropdown.style.maxHeight === '0px' || !dropdown.style.maxHeight) {
        dropdown.style.maxHeight = dropdown.scrollHeight + "px";
        if (icon) icon.style.transform = 'rotate(180deg)';
        
        // Réajuste la hauteur du parent pour ne pas masquer le sous-menu
        setTimeout(() => {
            menu.style.maxHeight = menu.scrollHeight + "px";
        }, 50);
    } else {
        dropdown.style.maxHeight = '0px';
        if (icon) icon.style.transform = 'rotate(0deg)';
        
        setTimeout(() => {
            menu.style.maxHeight = menu.scrollHeight + "px";
        }, 50);
    }
}

// Sécurité : Ferme le menu si la fenêtre s'agrandit en mode PC
window.addEventListener('resize', () => {
    if (window.innerWidth >= 768) {
        const menu = document.getElementById('mobileMenu');
        const icon = document.getElementById('menuIcon');
        
        if (menu) {
            menu.style.maxHeight = '0px';
            menu.style.opacity = '0';
        }
        if (icon) {
            icon.className = 'fas fa-bars';
        }
    }
});
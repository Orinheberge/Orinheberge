function toggleMobileMenu() {
    const menu = document.getElementById('mobileMenu');
    const icon = document.getElementById('menuIcon');
    
    // On vérifie si le menu est caché (soit 0px, soit vide, soit contenant opacity 0)
    if (menu.style.maxHeight === '0px' || menu.style.maxHeight === '' || menu.classList.contains('opacity-0')) {
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
    
    if (dropdown.style.maxHeight === '0px' || dropdown.style.maxHeight === '') {
        dropdown.style.maxHeight = dropdown.scrollHeight + "px";
        icon.style.transform = 'rotate(180deg)';
        // On réajuste la hauteur globale du menu parent pour inclure le sous-menu
        setTimeout(() => {
            menu.style.maxHeight = menu.scrollHeight + "px";
        }, 50);
    } else {
        dropdown.style.maxHeight = '0px';
        icon.style.transform = 'rotate(0deg)';
        setTimeout(() => {
            menu.style.maxHeight = (menu.scrollHeight - dropdown.scrollHeight) + "px";
        }, 50);
    }
}

// Sécurité : Fermeture du menu si basculement en mode PC
window.addEventListener('resize', () => {
    if (window.innerWidth >= 768) {
        const mobileMenu = document.getElementById('mobileMenu');
        mobileMenu.style.maxHeight = '0px';
        mobileMenu.style.opacity = '0';
        document.getElementById('menuIcon').className = 'fas fa-bars';
    }
});
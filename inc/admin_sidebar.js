/**
 * OrinHeberge — Admin Sidebar Interactive
 * Gère le toggle mobile, les animations et les interactions
 */

(function() {
    'use strict';

    // ============================================
    // CONFIGURATION
    // ============================================
    const CONFIG = {
        mobileBreakpoint: 768,
        animationDuration: 300,
        sidebarId: 'sidebar',
        toggleButtonId: 'adminSidebarToggle',
        overlayId: 'adminSidebarOverlay',
        storageKey: 'admin_sidebar_state'
    };

    // ============================================
    // VARIABLES GLOBALES
    // ============================================
    let sidebar = null;
    let toggleButton = null;
    let overlay = null;
    let isMobile = false;
    let isOpen = false;

    // ============================================
    // INITIALISATION
    // ============================================
    function init() {
        sidebar = document.getElementById(CONFIG.sidebarId);
        toggleButton = document.getElementById(CONFIG.toggleButtonId);
        
        if (!sidebar) {
            console.warn('[AdminSidebar] Élément sidebar non trouvé');
            return;
        }

        // Créer l'overlay si nécessaire
        createOverlay();
        
        // Détecter le mode mobile
        checkMobile();
        
        // Restaurer l'état précédent
        restoreState();
        
        // Attacher les événements
        attachEvents();
        
        // Écouter les changements de taille
        window.addEventListener('resize', handleResize);
        
        console.log('[AdminSidebar] Initialisée avec succès');
    }

    // ============================================
    // CRÉATION DE L'OVERLAY
    // ============================================
    function createOverlay() {
        const existing = document.getElementById(CONFIG.overlayId);
        if (existing) {
            overlay = existing;
            return;
        }

        overlay = document.createElement('div');
        overlay.id = CONFIG.overlayId;
        overlay.style.cssText = `
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(4px);
            z-index: 40;
            opacity: 0;
            pointer-events: none;
            transition: opacity ${CONFIG.animationDuration}ms ease;
        `;
        document.body.appendChild(overlay);
    }

    // ============================================
    // DÉTECTION MOBILE
    // ============================================
    function checkMobile() {
        isMobile = window.innerWidth < CONFIG.mobileBreakpoint;
        
        if (isMobile) {
            document.body.classList.add('admin-mobile-view');
        } else {
            document.body.classList.remove('admin-mobile-view');
        }
    }

    // ============================================
    // GESTION DU REDIMENSIONNEMENT
    // ============================================
    function handleResize() {
        const wasMobile = isMobile;
        checkMobile();
        
        // Si on passe de mobile à desktop, fermer le sidebar
        if (wasMobile && !isMobile && isOpen) {
            close();
        }
    }

    // ============================================
    // ATTACHEMENT DES ÉVÉNEMENTS
    // ============================================
    function attachEvents() {
        // Bouton toggle
        if (toggleButton) {
            toggleButton.addEventListener('click', toggle);
        }

        // Overlay (fermeture au clic)
        if (overlay) {
            overlay.addEventListener('click', close);
        }

        // Fermeture au clic sur un lien (mobile uniquement)
        const navLinks = sidebar.querySelectorAll('.nav-item[href]');
        navLinks.forEach(link => {
            link.addEventListener('click', () => {
                if (isMobile) {
                    setTimeout(close, 150); // Petit délai pour l'animation
                }
            });
        });

        // Fermeture avec Escape
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && isOpen && isMobile) {
                close();
            }
        });

        // Effet hover sur les items (uniquement actif hors mode mobile pour éviter le hover persistant au tactile)
        const navItems = sidebar.querySelectorAll('.nav-item');
        navItems.forEach(item => {
            item.addEventListener('mouseenter', () => {
                if (!isMobile) {
                    item.style.transform = 'translateX(4px)';
                }
            });
            item.addEventListener('mouseleave', () => {
                item.style.transform = 'translateX(0)';
            });
        });
    }

    // ============================================
    // OUVERTURE
    // ============================================
    function open() {
        if (isOpen) return;
        
        isOpen = true;
        sidebar.classList.add('open');
        document.body.classList.add('admin-sidebar-open');
        
        if (overlay) {
            overlay.style.opacity = '1';
            overlay.style.pointerEvents = 'auto';
        }
        
        // Sauvegarder l'état
        saveState(true);
        
        // Animation d'entrée des items
        animateItemsIn();
    }

    // ============================================
    // FERMETURE
    // ============================================
    function close() {
        if (!isOpen) return;
        
        isOpen = false;
        sidebar.classList.remove('open');
        document.body.classList.remove('admin-sidebar-open');
        
        if (overlay) {
            overlay.style.opacity = '0';
            overlay.style.pointerEvents = 'none';
        }
        
        // Sauvegarder l'état
        saveState(false);
    }

    // ============================================
    // TOGGLE
    // ============================================
    function toggle() {
        if (isOpen) {
            close();
        } else {
            open();
        }
    }

    // ============================================
    // ANIMATION DES ITEMS
    // ============================================
    function animateItemsIn() {
        const items = sidebar.querySelectorAll('.nav-item');
        items.forEach((item, index) => {
            item.style.opacity = '0';
            item.style.transform = 'translateX(-20px)';
            
            setTimeout(() => {
                item.style.transition = 'all 0.3s ease';
                item.style.opacity = '1';
                item.style.transform = 'translateX(0)';
            }, index * 30);
        });
    }

    // ============================================
    // PERSISTANCE DE L'ÉTAT
    // ============================================
    function saveState(state) {
        try {
            localStorage.setItem(CONFIG.storageKey, JSON.stringify({
                isOpen: state,
                timestamp: Date.now()
            }));
        } catch (e) {
            // localStorage non disponible
        }
    }

    function restoreState() {
        try {
            const saved = localStorage.getItem(CONFIG.storageKey);
            if (saved) {
                const data = JSON.parse(saved);
                // Restaurer seulement si moins de 24h
                if (Date.now() - data.timestamp < 86400000) {
                    if (data.isOpen && !isMobile) {
                        open();
                    }
                }
            }
        } catch (e) {
            // localStorage non disponible
        }
    }

    // ============================================
    // API PUBLIQUE
    // ============================================
    window.AdminSidebar = {
        open: open,
        close: close,
        toggle: toggle,
        isOpen: () => isOpen
    };

    // ============================================
    // DÉMARRAGE
    // ============================================
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
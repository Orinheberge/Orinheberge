/**
 * CART.JS — Interactions pour la page panier
 * OrinHeberge © 2024
 */

(function() {
    'use strict';

    // ═══════════════════════════════════════════
    // CONFIGURATION
    // ═══════════════════════════════════════════
    const CONFIG = window.CART_CONFIG || {
        itemCount: 0,
        subtotal: 0,
        total: 0,
        discount: 0,
        currency: '€'
    };

    // ═══════════════════════════════════════════
    // UTILITAIRES
    // ═══════════════════════════════════════════
    function formatPrice(amount) {
        return amount.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' ' + CONFIG.currency;
    }

    function $(selector) {
        return document.querySelector(selector);
    }

    function $$(selector) {
        return document.querySelectorAll(selector);
    }

    // ═══════════════════════════════════════════
    // GESTION DES QUANTITÉS
    // ═══════════════════════════════════════════
    function initQuantityControls() {
        // Boutons +/-
        $$('.qty-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const container = this.closest('.quantity-control');
                const input = container.querySelector('.qty-input');
                const min = parseInt(input.min) || 0;
                const max = parseInt(input.max) || 99;
                let value = parseInt(input.value) || 0;

                if (this.classList.contains('qty-minus')) {
                    value = Math.max(min, value - 1);
                } else if (this.classList.contains('qty-plus')) {
                    value = Math.min(max, value + 1);
                }

                input.value = value;
                updateLineTotal(this.closest('.cart-item'));
                updateTotals();
                updateButtonStates(container);
            });
        });

        // Input direct
        $$('.qty-input').forEach(input => {
            input.addEventListener('change', function() {
                const min = parseInt(this.min) || 0;
                const max = parseInt(this.max) || 99;
                let value = parseInt(this.value) || 0;
                value = Math.max(min, Math.min(max, value));
                this.value = value;
                updateLineTotal(this.closest('.cart-item'));
                updateTotals();
                updateButtonStates(this.closest('.quantity-control'));
            });

            // Bloquer les caractères non numériques
            input.addEventListener('keydown', function(e) {
                const allowed = ['Backspace', 'Delete', 'ArrowLeft', 'ArrowRight', 'Tab', 'Home', 'End'];
                if (allowed.includes(e.key)) return;
                if (e.ctrlKey || e.metaKey) return;
                if (!/^\d$/.test(e.key)) {
                    e.preventDefault();
                }
            });
        });

        // État initial des boutons
        $$('.quantity-control').forEach(updateButtonStates);
    }

    function updateButtonStates(container) {
        const input = container.querySelector('.qty-input');
        const minusBtn = container.querySelector('.qty-minus');
        const plusBtn = container.querySelector('.qty-plus');
        const min = parseInt(input.min) || 0;
        const max = parseInt(input.max) || 99;
        const value = parseInt(input.value) || 0;

        minusBtn.disabled = value <= min;
        plusBtn.disabled = value >= max;
    }

    function updateLineTotal(cartItem) {
        const input = cartItem.querySelector('.qty-input');
        const price = parseFloat(cartItem.querySelector('.line-total').dataset.price);
        const quantity = parseInt(input.value) || 0;
        const total = price * quantity;

        const totalEl = cartItem.querySelector('.line-total');
        totalEl.textContent = formatPrice(total);
        totalEl.classList.add('updating');
        setTimeout(() => totalEl.classList.remove('updating'), 400);
    }

    // ═══════════════════════════════════════════
    // MISE À JOUR DES TOTAUX
    // ═══════════════════════════════════════════
    function updateTotals() {
        let subtotal = 0;
        let itemCount = 0;

        $$('.cart-item').forEach(item => {
            const input = item.querySelector('.qty-input');
            const price = parseFloat(item.querySelector('.line-total').dataset.price);
            const quantity = parseInt(input.value) || 0;

            subtotal += price * quantity;
            itemCount += quantity;
        });

        // Mise à jour affichage
        const subtotalEl = $('#subtotal-display');
        const totalEl = $('#total-display');

        if (subtotalEl) {
            subtotalEl.textContent = formatPrice(subtotal);
        }

        if (totalEl) {
            // Note : le total avec promo est calculé côté serveur
            // Ici on affiche le sous-total, la promo reste fixe
            const discount = CONFIG.discount || 0;
            const finalTotal = Math.max(0, subtotal - discount);
            totalEl.textContent = formatPrice(finalTotal);
        }

        // Mise à jour du texte "X article(s)"
        const countText = itemCount > 0 ? `${itemCount} article${itemCount > 1 ? 's' : ''}` : '';
        const headerP = $('main p.text-gray-400');
        if (headerP && countText) {
            const existingSpan = headerP.querySelector('.text-sky-400');
            if (existingSpan) {
                existingSpan.textContent = countText;
            }
        }
    }

    // ═══════════════════════════════════════════
    // MESSAGE FLASH
    // ═══════════════════════════════════════════
    function initFlashMessage() {
        const flash = $('#flash-message');
        if (!flash) return;

        // Auto-hide après 5 secondes
        setTimeout(() => {
            flash.style.transition = 'all 0.3s ease-out';
            flash.style.opacity = '0';
            flash.style.transform = 'translateY(-10px)';
            setTimeout(() => flash.remove(), 300);
        }, 5000);
    }

    // ═══════════════════════════════════════════
    // CODE PROMO
    // ═══════════════════════════════════════════
    function initPromoCode() {
        const promoInput = $('#promo_code');
        if (!promoInput) return;

        // Auto-uppercase
        promoInput.addEventListener('input', function() {
            this.value = this.value.toUpperCase();
        });

        // Submit avec Entrée
        promoInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                this.closest('form').submit();
            }
        });
    }

    // ═══════════════════════════════════════════
    // CONFIRMATION CHECKOUT
    // ═══════════════════════════════════════════
    function initCheckoutButton() {
        const checkoutBtn = $('.checkout-btn');
        if (!checkoutBtn) return;

        checkoutBtn.addEventListener('click', function(e) {
            const btn = this;

            // Petite animation avant soumission
            btn.style.transform = 'scale(0.98)';
            setTimeout(() => {
                btn.style.transform = '';
            }, 150);

            // IMPORTANT : ne PAS désactiver le bouton de façon synchrone ici.
            // Un bouton submit désactivé (disabled = true) DANS son propre
            // handler "click" peut faire annuler la soumission du formulaire
            // par le navigateur (comportement observé notamment sur Safari,
            // et de façon inconsistante ailleurs), car l'élément qui a
            // déclenché l'action par défaut n'est plus "actionnable" au
            // moment où le navigateur traite le submit.
            // → C'est très probablement la cause du bug "la redirection ne
            //   se fait pas" : le formulaire ne partait jamais en POST.
            // On repousse donc le disabled au tick suivant (après que le
            // submit natif a déjà été déclenché par le navigateur).
            setTimeout(() => {
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Redirection...';
                btn.disabled = true;
            }, 0);
        });
    }

    // ═══════════════════════════════════════════
    // SAUVEGARDE AUTO (DEBOUNCE)
    // ═══════════════════════════════════════════
    let saveTimeout = null;
    
    function debouncedSave() {
        if (saveTimeout) clearTimeout(saveTimeout);
        saveTimeout = setTimeout(() => {
            const form = $('#cart-form');
            if (form) {
                // On pourrait faire un fetch AJAX ici pour sauvegarder sans reload
                // Pour l'instant, le formulaire HTML gère la soumission
                console.log('[Cart] Auto-save triggered');
            }
        }, 1000);
    }

    // ═══════════════════════════════════════════
    // KEYBOARD SHORTCUTS
    // ═══════════════════════════════════════════
    function initKeyboardShortcuts() {
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + Enter = Checkout
            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                const checkoutBtn = $('.checkout-btn');
                if (checkoutBtn && !checkoutBtn.disabled) {
                    checkoutBtn.click();
                }
            }

            // Escape = Fermer flash
            if (e.key === 'Escape') {
                const flash = $('#flash-message');
                if (flash) {
                    flash.style.opacity = '0';
                    setTimeout(() => flash.remove(), 300);
                }
            }
        });
    }

    // ═══════════════════════════════════════════
    // ANIMATIONS AU SCROLL (Intersection Observer)
    // ═══════════════════════════════════════════
    function initScrollAnimations() {
        if (!('IntersectionObserver' in window)) return;

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('animate-in');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1 });

        $$('.cart-item').forEach(item => observer.observe(item));
    }

    // ═══════════════════════════════════════════
    // INITIALISATION
    // ═══════════════════════════════════════════
    function init() {
        initQuantityControls();
        initFlashMessage();
        initPromoCode();
        initCheckoutButton();
        initKeyboardShortcuts();
        initScrollAnimations();

        // Écouter les changements de quantité pour sauvegarde auto
        $$('.qty-input').forEach(input => {
            input.addEventListener('change', debouncedSave);
        });

        console.log('[Cart] Initialisé avec', CONFIG.itemCount, 'article(s)');
    }

    // Démarrage
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
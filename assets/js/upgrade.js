/**
 * OrinHeberge — Upgrade Page Logic
 */
(function() {
    'use strict';
    
    const config = window.UPGRADE_CONFIG || {};
    
    // ═══════════════════════════════════════════
    // SÉLECTION D'UN PLAN
    // ═══════════════════════════════════════════
    window.selectPlan = function(el) {
        const productId = parseInt(el.dataset.productId);
        const productName = el.dataset.productName;
        const productPrice = parseFloat(el.dataset.productPrice);
        const priceDiff = parseFloat(el.dataset.priceDiff);
        
        // Retirer sélection précédente
        document.querySelectorAll('.plan-card.selected').forEach(c => c.classList.remove('selected'));
        
        // Nouvelle sélection
        el.classList.add('selected');
        
        // Remplir formulaire
        const input = document.getElementById('new_product_id');
        if (input) input.value = productId;
        
        // Mettre à jour barre de confirmation
        const nameEl = document.getElementById('confirmName');
        const priceEl = document.getElementById('confirmPrice');
        const diffEl = document.getElementById('confirmDiff');
        
        if (nameEl) nameEl.textContent = productName;
        if (priceEl) priceEl.textContent = formatPrice(productPrice) + '€/mois';
        
        if (diffEl) {
            if (priceDiff > 0) {
                diffEl.textContent = 'Différence : +' + formatPrice(priceDiff) + '€/mois';
                diffEl.className = 'text-[10px] text-amber-400 mt-0.5';
            } else if (priceDiff < 0) {
                diffEl.textContent = 'Remboursement : ' + formatPrice(priceDiff) + '€/mois';
                diffEl.className = 'text-[10px] text-emerald-400 mt-0.5';
            } else {
                diffEl.textContent = '';
            }
        }
        
        // Afficher barre
        const bar = document.getElementById('confirmBar');
        if (bar) bar.classList.add('active');
        
        // Haptic feedback sur mobile
        if ('vibrate' in navigator) {
            navigator.vibrate(10);
        }
    };
    
    // ═══════════════════════════════════════════
    // CONFIRMATION SUBMIT
    // ═══════════════════════════════════════════
    const form = document.getElementById('upgradeForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            const productId = document.getElementById('new_product_id')?.value;
            
            if (!productId) {
                e.preventDefault();
                alert('⚠️ Veuillez sélectionner une offre.');
                return false;
            }
            
            const name = document.getElementById('confirmName')?.textContent || 'cette offre';
            const price = document.getElementById('confirmPrice')?.textContent || '';
            
            const message = `Confirmer l'upgrade ?\n\n` +
                           `🎯 Nouvelle offre : ${name}\n` +
                           `💰 Nouveau prix : ${price}\n\n` +
                           `Les nouvelles ressources seront appliquées immédiatement.\n` +
                           `Cette action est irréversible.`;
            
            if (!confirm(message)) {
                e.preventDefault();
                return false;
            }
            
            // Loading state
            const btn = document.getElementById('submitBtn');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin text-xs"></i><span>Upgrade en cours...</span>';
            }
        });
    }
    
    // ═══════════════════════════════════════════
    // UTILS
    // ═══════════════════════════════════════════
    function formatPrice(price) {
        return Math.abs(price).toFixed(2).replace('.', ',');
    }
    
    // ═══════════════════════════════════════════
    // RACCOURCIS CLAVIER
    // ═══════════════════════════════════════════
    document.addEventListener('keydown', function(e) {
        // ESC pour désélectionner
        if (e.key === 'Escape') {
            document.querySelectorAll('.plan-card.selected').forEach(c => c.classList.remove('selected'));
            const bar = document.getElementById('confirmBar');
            if (bar) bar.classList.remove('active');
            const input = document.getElementById('new_product_id');
            if (input) input.value = '';
        }
    });
    
    console.log('[Upgrade] ✅ Script chargé', config);
    
})();
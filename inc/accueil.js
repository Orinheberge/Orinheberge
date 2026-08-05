// ═══════════════════════════════════════════════════════════════
// SYSTÈME D'AVIS CLIENTS
// ═══════════════════════════════════════════════════════════════

let currentPage = 1;
const reviewsPerPage = 6;
const avatarColors = ['from-sky-400 to-blue-600', 'from-purple-400 to-pink-600', 'from-emerald-400 to-teal-600', 'from-amber-400 to-orange-600', 'from-red-400 to-rose-600', 'from-indigo-400 to-violet-600'];

async function loadReviews(page = 1, append = false) {
    const grid = document.getElementById('reviews-grid');
    const noReviews = document.getElementById('no-reviews');
    const loadMoreWrap = document.getElementById('load-more-wrap');

    if (!grid) {
        console.error('[Reviews] Grid element not found');
        return;
    }

    if (!append) {
        grid.innerHTML = `
            <div class="glass p-5 rounded-xl border border-white/[0.05] animate-pulse">
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-10 h-10 rounded-full bg-white/10"></div>
                    <div class="flex-1 space-y-2">
                        <div class="h-3 bg-white/10 rounded w-24"></div>
                        <div class="h-2 bg-white/10 rounded w-16"></div>
                    </div>
                </div>
                <div class="h-2 bg-white/10 rounded w-full mb-1"></div>
                <div class="h-2 bg-white/10 rounded w-3/4"></div>
            </div>`;
    }

    try {
        const response = await fetch(`/api/reviews/get.php?limit=${reviewsPerPage}&page=${page}&_t=${Date.now()}`);
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            const text = await response.text();
            console.error('[Reviews] Réponse non-JSON:', text.substring(0, 200));
            throw new Error('Le serveur ne renvoie pas de JSON valide');
        }

        const data = await response.json();

        if (!data.success) {
            throw new Error(data.error || 'Erreur API');
        }

        const reviews = data.data || [];
        const stats = data.stats || {};
        const pagination = data.pagination || {};

        const statsEl = document.getElementById('review-stats');
        if (statsEl && stats.total_reviews > 0) {
            statsEl.classList.remove('hidden');
            statsEl.classList.add('flex');
            document.getElementById('avg-rating').textContent = stats.average_rating;
            document.getElementById('avg-stars').innerHTML = generateStars(Math.round(stats.average_rating));
            document.getElementById('total-count').textContent = stats.total_reviews + ' avis';
        }

        if (reviews.length === 0 && !append) {
            grid.innerHTML = '';
            noReviews?.classList.remove('hidden');
            loadMoreWrap?.classList.add('hidden');
            return;
        }
        noReviews?.classList.add('hidden');

        let html = '';
        reviews.forEach((review, index) => {
            const colorClass = avatarColors[(review.name.charCodeAt(0) + index) % avatarColors.length];
            const initial = review.name.charAt(0).toUpperCase();
            const stars = generateStars(review.rating);
            const date = formatDate(review.created_at);

            html += `
                <div class="glass p-5 rounded-xl border border-white/[0.05] hover:border-white/[0.1] transition ${append ? 'animate-fade-in' : ''}">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-10 h-10 rounded-full bg-gradient-to-br ${colorClass} flex items-center justify-center text-white font-bold text-sm shrink-0">${initial}</div>
                        <div class="flex-1 min-w-0">
                            <h4 class="font-bold text-white text-sm truncate">${review.name}</h4>
                            <div class="flex items-center gap-2">
                                <div class="text-yellow-400 text-xs">${stars}</div>
                                <span class="text-gray-600 text-[10px]">${date}</span>
                            </div>
                        </div>
                    </div>
                    <p class="text-gray-400 text-xs italic leading-relaxed">"${review.comment}"</p>
                </div>`;
        });

        if (append) grid.insertAdjacentHTML('beforeend', html);
        else grid.innerHTML = html;

        if (pagination.has_next) {
            loadMoreWrap?.classList.remove('hidden');
        } else {
            loadMoreWrap?.classList.add('hidden');
        }

    } catch (err) {
        console.error('[Reviews] Erreur chargement:', err);
        if (!append) {
            grid.innerHTML = `
                <div class="col-span-full text-center text-gray-500 text-sm py-8">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    Impossible de charger les avis. <br>
                    <span class="text-xs opacity-60">${err.message}</span>
                </div>`;
        }
    }
}

function generateStars(rating) {
    let html = '';
    for (let i = 1; i <= 5; i++) {
        if (i <= rating) html += '<i class="fas fa-star"></i>';
        else if (i - 0.5 <= rating) html += '<i class="fas fa-star-half-alt"></i>';
        else html += '<i class="far fa-star text-gray-600"></i>';
    }
    return html;
}

function formatDate(dateStr) {
    if (!dateStr) return '';
    const date = new Date(dateStr.replace(' ', 'T'));
    const diff = Math.floor((new Date() - date) / 1000);
    if (diff < 60) return 'À l\'instant';
    if (diff < 3600) return Math.floor(diff / 60) + ' min';
    if (diff < 86400) return Math.floor(diff / 3600) + 'h';
    if (diff < 604800) return Math.floor(diff / 86400) + 'j';
    return date.toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' });
}

function loadMoreReviews() {
    const btn = document.getElementById('load-more-btn');
    if (!btn) return;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Chargement...';
    btn.disabled = true;
    currentPage++;
    loadReviews(currentPage, true).finally(() => {
        btn.innerHTML = '<i class="fas fa-chevron-down"></i> Voir plus d\'avis';
        btn.disabled = false;
    });
}

// ═══════════════════════════════════════════════════════════════
// FILTRAGE PAR CATÉGORIE
// ═══════════════════════════════════════════════════════════════

function filterCategory(catId) {
    // Utiliser window.categoryLabels défini dans index.php
    const categoryLabels = window.categoryLabels || {};
    
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    if(document.getElementById('tab-' + catId)) document.getElementById('tab-' + catId).classList.add('active');
    
    const catView = document.getElementById('cat-view'), 
          catTitle = document.getElementById('cat-view-title'), 
          catGrid = document.getElementById('cat-view-grid'), 
          allSections = document.getElementById('all-sections');
    
    if (catId === 'all') { 
        catView.style.display = 'none'; 
        allSections.style.display = 'block'; 
        return; 
    }
    
    allSections.style.display = 'none'; 
    catView.style.display = 'block';
    catTitle.textContent = categoryLabels[catId] || catId.toUpperCase();
    
    const cards = Array.from(document.querySelectorAll('#all-sections .offer-card[data-category="' + catId + '"]'));
    catGrid.innerHTML = '';
    
    if (cards.length === 0) {
        catGrid.innerHTML = '<div class="col-span-full py-12 text-center text-gray-500 text-sm"><i class="fas fa-box-open text-4xl mb-3 opacity-50"></i><br>Aucune offre disponible.</div>';
    } else {
        cards.forEach(card => { 
            const clone = card.cloneNode(true); 
            clone.style.display = 'flex'; 
            catGrid.appendChild(clone); 
        });
    }
}

// ═══════════════════════════════════════════════════════════════
// ANALYTICS (RGPD)
// ═══════════════════════════════════════════════════════════════

window.loadAnalytics = function() {
    (function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start': new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
    j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
    })(window,document,'script','dataLayer','GTM-W5ZSVXHK');

    var gaScript = document.createElement('script');
    gaScript.async = true;
    gaScript.src = "https://www.googletagmanager.com/gtag/js?id=G-YKZCE0SNDG";
    document.head.appendChild(gaScript);
    gaScript.onload = function() {
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', 'G-YKZCE0SNDG');
    };
};

function checkAnalyticsConsent() {
    const cookies = document.cookie.split('; ');
    const hasConsent = cookies.find(row => row.startsWith('orinheberge_cookies=accepted'));
    if (hasConsent) {
        window.loadAnalytics();
    }
}

// ═══════════════════════════════════════════════════════════════
// TRUSTPILOT WIDGET CHECK
// ═══════════════════════════════════════════════════════════════

function checkTrustpilotWidget() {
    setTimeout(function() {
        const widget = document.querySelector('.trustpilot-widget');
        const fallback = document.getElementById('tp-fallback');
        
        if (widget && fallback) {
            const checkWidget = setInterval(function() {
                const iframe = widget.querySelector('iframe');
                if (iframe) {
                    setTimeout(function() {
                        const widgetHeight = widget.offsetHeight;
                        const hasContent = widget.innerHTML.trim().length > 200;
                        
                        if (!hasContent || widgetHeight < 100) {
                            fallback.classList.remove('hidden');
                        }
                    }, 2000);
                    clearInterval(checkWidget);
                }
            }, 500);
            
            setTimeout(function() {
                const hasIframe = widget.querySelector('iframe');
                const hasContent = widget.innerHTML.trim().length > 200;
                if (!hasIframe || !hasContent) {
                    fallback.classList.remove('hidden');
                }
                clearInterval(checkWidget);
            }, 5000);
        }
    }, 1000);
}

// ═══════════════════════════════════════════════════════════════
// INITIALISATION AU CHARGEMENT
// ═══════════════════════════════════════════════════════════════

document.addEventListener('DOMContentLoaded', function() {
    // Initialiser les avis
    loadReviews(1, false);
    
    // Initialiser le formulaire d'avis
    const commentInput = document.getElementById('review-comment');
    if (commentInput) {
        commentInput.addEventListener('input', function() {
            const count = document.getElementById('char-count');
            if (count) count.textContent = this.value.length;
        });
    }

    const form = document.getElementById('review-form');
    if (form) {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            const name = document.getElementById('review-name').value.trim();
            const rating = parseInt(document.getElementById('review-rating').value);
            const comment = document.getElementById('review-comment').value.trim();
            const submitBtn = document.getElementById('review-submit-btn');
            const successMsg = document.getElementById('review-success');
            const errorMsg = document.getElementById('review-error');
            const errorText = document.getElementById('review-error-text');

            successMsg.classList.add('hidden');
            errorMsg.classList.add('hidden');

            if (comment.length < 10) {
                errorText.textContent = 'Le commentaire doit faire au moins 10 caractères.';
                errorMsg.classList.remove('hidden');
                return;
            }

            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span>Envoi...</span>';

            try {
                const response = await fetch('/api/reviews/index.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ name, rating, comment })
                });

                const data = await response.json();

                if (data.success) {
                    document.getElementById('review-form').reset();
                    document.getElementById('char-count').textContent = '0';
                    successMsg.classList.remove('hidden');
                    submitBtn.innerHTML = '<i class="fas fa-check"></i> <span>Envoyé !</span>';
                    currentPage = 1;
                    await loadReviews(1, false);
                    setTimeout(() => {
                        successMsg.classList.add('hidden');
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> <span>Envoyer l\'avis</span>';
                    }, 5000);
                } else {
                    const errMsg = data.error || (data.errors ? data.errors.join(', ') : 'Erreur inconnue');
                    errorText.textContent = errMsg;
                    errorMsg.classList.remove('hidden');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> <span>Envoyer l\'avis</span>';
                }
            } catch (err) {
                errorText.textContent = 'Erreur de connexion. Veuillez réessayer.';
                errorMsg.classList.remove('hidden');
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> <span>Envoyer l\'avis</span>';
            }
        });
    }
    
    // Initialiser le filtrage par catégorie
    filterCategory('all');
    
    // Vérifier le consentement analytics
    checkAnalyticsConsent();
    
    // Vérifier le widget Trustpilot
    checkTrustpilotWidget();
});
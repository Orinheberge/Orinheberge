/**
 * script.js - Logique de paiement Stripe Elements pour OrinHeberge
 * Ce fichier remplace le script inline pour respecter la CSP sans 'unsafe-inline'.
 */

document.addEventListener('DOMContentLoaded', async function() {
    // Récupération des variables PHP injectées via data-attributes (plus sûr que les globals)
    const checkoutContainer = document.getElementById('checkout-container');
    if (!checkoutContainer) return;

    const stripePublicKey = checkoutContainer.dataset.stripeKey;
    const orderId = checkoutContainer.dataset.orderId;
    const returnUrl = checkoutContainer.dataset.returnUrl;
    const payAmountLabel = checkoutContainer.dataset.payAmount;

    const stripe = Stripe(stripePublicKey);
    let elements = null;
    let paymentReady = false;

    // Éléments DOM
    const policyCheckbox = document.getElementById('accept-policy');
    const payBtn = document.getElementById('pay-btn');
    const payBtnLabel = document.getElementById('pay-btn-label');
    const errorDiv = document.getElementById('card-errors');
    const loadingDiv = document.getElementById('payment-loading');
    const elementDiv = document.getElementById('payment-element');

    // Mise à jour de l'état du bouton payer
    function updatePayButtonState() {
        payBtn.disabled = !(paymentReady && policyCheckbox.checked);
    }

    // Affichage des erreurs
    function showError(msg) {
        errorDiv.querySelector('.error-message').textContent = msg;
        errorDiv.classList.remove('hidden');
    }

    // Gestion du chargement
    function setLoading(isLoading) {
        payBtn.disabled = isLoading;
        payBtnLabel.innerHTML = isLoading
            ? '<i class="fas fa-spinner fa-spin"></i> Traitement en cours...'
            : 'Payer ' + payAmountLabel;
    }

    // Écouteur sur la checkbox de politique
    policyCheckbox.addEventListener('change', function () {
        updatePayButtonState();

        // Enregistrement de l'acceptation (optionnel, non bloquant)
        if (this.checked) {
            fetch('/shop/order/checkout/accept-policy.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ order_id: orderId })
            }).catch(() => {});
        }
    });

    // Initialisation de Stripe Elements
    async function initialize() {
        try {
            // 1. Création du PaymentIntent côté serveur
            const resp = await fetch('/shop/order/checkout/process.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ order_id: orderId })
            });

            if (!resp.ok) throw new Error('Erreur serveur lors de la création du paiement');
            
            const data = await resp.json();
            if (data.error) {
                showError(data.error);
                loadingDiv.style.display = 'none';
                return;
            }

            // 2. Configuration de l'apparence (thème sombre Tailwind)
            const appearance = {
                theme: 'night',
                variables: {
                    colorBackground: 'rgba(255,255,255,0.02)',
                    colorPrimary: '#38bdf8',
                    colorText: '#e2e8f0',
                    colorDanger: '#f87171',
                    borderRadius: '12px',
                }
            };

            elements = stripe.elements({
                clientSecret: data.client_secret,
                appearance: appearance
            });

            // 3. Création et montage du Payment Element
            const paymentElement = elements.create('payment', {
                layout: { type: 'tabs', defaultCollapsed: false }
            });

            paymentElement.mount('#payment-element');

            // 4. Gestion des événements
            paymentElement.on('ready', () => {
                loadingDiv.style.display = 'none';
                elementDiv.classList.remove('hidden');
                paymentReady = true;
                updatePayButtonState();
            });

            paymentElement.on('change', (event) => {
                if (event.error) {
                    showError(event.error.message);
                } else {
                    errorDiv.classList.add('hidden');
                }
            });

        } catch (err) {
            console.error('Erreur initialisation Stripe:', err);
            showError('Impossible de charger le paiement. Merci de réessayer.');
            loadingDiv.style.display = 'none';
        }
    }

    // Lancement de l'initialisation
    await initialize();

    // Soumission du formulaire
    document.getElementById('checkout-form').addEventListener('submit', async function (e) {
        e.preventDefault();
        
        if (!elements) return;

        if (!policyCheckbox.checked) {
            showError('Merci d\'accepter la Politique de Paiement avant de continuer.');
            return;
        }

        setLoading(true);

        // Confirmation du paiement avec redirection automatique
        const { error } = await stripe.confirmPayment({
            elements,
            confirmParams: {
                return_url: returnUrl,
            },
        });

        // Si on arrive ici, c'est qu'il y a eu une erreur immédiate
        if (error) {
            showError(error.message);
            setLoading(false);
        }
    });
});
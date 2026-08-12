<?php
/**
 * /shop/order/payment-choice/ — Choix du moyen de paiement
 * L'utilisateur choisit entre PayPal.me et Carte bancaire.
 *
 * Supporte deux modes :
 *  - Mode "commande unique" : ?id=X ou $_SESSION['current_pending_order_id']
 *    pointant vers une ligne existante dans `orders` (ex: achat direct
 *    depuis /offres/).
 *  - Mode "bundle panier" : $_SESSION['checkout_bundle'] posé par
 *    /shop/cart/ lors du clic sur "Finaliser ma commande". C'est le mode
 *    utilisé par le panier multi-articles.
 */
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: /login/");
    exit();
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/db.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/lang.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/security.php';

$order = null;
$bundle = null;
$is_bundle_mode = false;

// ═══════════════════════════════════════════
// MODE 1 : commande unique déjà en base
// ═══════════════════════════════════════════
$order_row_id = (int)($_GET['id'] ?? $_SESSION['current_pending_order_id'] ?? 0);

if ($order_row_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT o.*, p.name AS product_name, p.slug AS product_slug
            FROM orders o
            LEFT JOIN products p ON p.id = o.product_id
            WHERE o.id = ? AND o.user_id = ? AND o.status = 'pending'
            LIMIT 1
        ");
        $stmt->execute([$order_row_id, $_SESSION['user_id']]);
        $order = $stmt->fetch();
    } catch (Exception $e) {
        $order = null;
    }

    if ($order) {
        $_SESSION['current_pending_order_id'] = $order_row_id;
    } else {
        unset($_SESSION['current_pending_order_id']);
    }
}

// ═══════════════════════════════════════════
// MODE 2 : bundle venant du panier (/shop/cart/)
// ═══════════════════════════════════════════
if (!$order) {
    $candidate = $_SESSION['checkout_bundle'] ?? null;
    if (is_array($candidate) && !empty($candidate['items'])) {
        $bundle = $candidate;
        $is_bundle_mode = true;
    }
}

// Si vraiment rien d'exploitable, retour au panier.
if (!$order && !$is_bundle_mode) {
    header('Location: /shop/cart/');
    exit();
}

// ═══════════════════════════════════════════
// PRIX & RÉFÉRENCE (unifiés pour les deux modes)
// ═══════════════════════════════════════════
if ($is_bundle_mode) {
    $price       = (float)($bundle['total'] ?? 0);
    $subtotal    = (float)($bundle['subtotal'] ?? $price);
    $discount    = (float)($bundle['discount'] ?? 0);
    $promo_label = $bundle['promo_label'] ?? null;
    $order_ref   = $bundle['ref'] ?? ('CMD-' . strtoupper(substr(session_id(), 0, 8)));
    $is_renewal  = false;
} else {
    $price       = (float)($order['renewal_price'] ?? 0);
    $subtotal    = $price;
    $discount    = 0.0;
    $promo_label = null;
    $order_ref   = $order['order_id'] ?? $order_row_id;
    $is_renewal  = ($order['type'] ?? 'new') === 'renewal';
}

// ═══════════════════════════════════════════
// RÉCUPÉRATION CONFIG PAYPAL.ME
// ═══════════════════════════════════════════
$paypalme_username = 'metal544002009'; // fallback
try {
    $ext_settings_raw = $pdo->query("
        SELECT e.slug, es.key, es.value 
        FROM extension_settings es 
        JOIN extensions e ON e.id = es.extension_id 
        WHERE e.slug = 'paypal'
    ")->fetchAll();
    $ext_cfg = [];
    foreach ($ext_settings_raw as $r) {
        $ext_cfg[$r['key']] = $r['value'];
    }
    $paypalme_username = $ext_cfg['username'] ?? $paypalme_username;
} catch (Exception $e) {
    // silencieux
}

$paypalme_url = "https://paypal.me/" . $paypalme_username . "/" . number_format($price, 2, '.', '');

// ═══════════════════════════════════════════
// LIEN "CARTE BANCAIRE" (Stripe)
// ═══════════════════════════════════════════
// ⚠️ En mode bundle, /shop/order/checkout/ doit être capable de lire
// $_SESSION['checkout_bundle'] via ?ref=... — ce fichier n'a pas été fourni,
// il faudra probablement l'adapter en cohérence avec ce nouveau flux.
$checkout_url = $is_bundle_mode
    ? '/shop/order/checkout/?ref=' . urlencode($order_ref)
    : '/shop/order/checkout/?id=' . $order_row_id;

$page_title = $is_renewal ? 'Renouvellement' : 'Finaliser la commande';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> — Choix du paiement | OrinHeberge</title>
    <link rel="icon" type="image/png" href="/favicon.png">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #070a13; }
        .glass { 
            background: rgba(255,255,255,0.03); 
            backdrop-filter: blur(14px); 
            border: 1px solid rgba(255,255,255,0.06); 
        }
        .payment-option {
            background: rgba(255,255,255,0.02);
            border: 2px solid rgba(255,255,255,0.08);
            border-radius: 1rem;
            padding: 1.5rem;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }
        .payment-option::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: linear-gradient(135deg, transparent, rgba(255,255,255,0.02));
            opacity: 0;
            transition: opacity 0.3s;
        }
        .payment-option:hover {
            border-color: rgba(56, 189, 248, 0.4);
            transform: translateY(-3px);
            box-shadow: 0 10px 40px rgba(56, 189, 248, 0.1);
        }
        .payment-option:hover::before {
            opacity: 1;
        }
        .payment-option.stripe:hover {
            border-color: rgba(99, 91, 255, 0.5);
            box-shadow: 0 10px 40px rgba(99, 91, 255, 0.15);
        }
        .payment-option.paypal:hover {
            border-color: rgba(0, 112, 243, 0.5);
            box-shadow: 0 10px 40px rgba(0, 112, 243, 0.15);
        }
        .payment-icon {
            width: 3.5rem;
            height: 3.5rem;
            border-radius: 0.875rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            flex-shrink: 0;
        }
        .recommended-badge {
            position: absolute;
            top: -1px;
            right: 1rem;
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            font-size: 10px;
            font-weight: 700;
            padding: 0.25rem 0.75rem;
            border-radius: 0 0 0.5rem 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        @keyframes fade-in-up {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-in { animation: fade-in-up 0.5s ease-out; }
        .animate-in-delay { animation: fade-in-up 0.5s ease-out 0.1s both; }
        .animate-in-delay2 { animation: fade-in-up 0.5s ease-out 0.2s both; }
    </style>
</head>
<body class="text-gray-200 font-sans min-h-screen flex flex-col">

<?php include $_SERVER['DOCUMENT_ROOT'] . '/inc/navbar.php'; ?>

<div class="flex-grow flex items-center justify-center px-4 py-10">
    <div class="w-full max-w-4xl grid grid-cols-1 lg:grid-cols-5 gap-6">

        <!-- ═══ RÉCAPITULATIF ═══ -->
        <div class="lg:col-span-2 glass p-6 rounded-2xl h-fit animate-in">
            <h2 class="text-sm font-bold text-white mb-4 flex items-center gap-2">
                <i class="fas fa-receipt text-sky-400"></i> 
                Récapitulatif
            </h2>

            <div class="space-y-3 mb-4">
                <div class="flex justify-between text-sm">
                    <span class="text-gray-400">Commande</span>
                    <span class="text-white font-mono">#<?= htmlspecialchars((string)$order_ref) ?></span>
                </div>

                <?php if ($is_bundle_mode): ?>
                    <?php foreach ($bundle['items'] as $bi):
                        $bi_product = $bi['product'];
                        $bi_qty     = (int)$bi['quantity'];
                        $bi_line    = (float)$bi_product['price'] * $bi_qty;
                    ?>
                    <div class="flex justify-between text-sm gap-3">
                        <span class="text-gray-400 truncate">
                            <?= htmlspecialchars($bi_product['name']) ?>
                            <?php if ($bi_qty > 1): ?><span class="text-gray-500">×<?= $bi_qty ?></span><?php endif; ?>
                        </span>
                        <span class="text-white shrink-0"><?= number_format($bi_line, 2, ',', '') ?>€</span>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <?php if (!empty($order['service_name'])): ?>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-400">Service</span>
                        <span class="text-white font-semibold text-right truncate ml-2">
                            <?= htmlspecialchars($order['service_name']) ?>
                        </span>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($order['id_server_panel'])): ?>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-400">Identifiant</span>
                        <span class="text-sky-400 font-mono text-xs">
                            <?= htmlspecialchars($order['id_server_panel']) ?>
                        </span>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($order['next_payment_date'])): ?>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-400">Échéance</span>
                        <span class="text-amber-400 font-bold">
                            <?= date("d/m/Y", strtotime($order['next_payment_date'])) ?>
                        </span>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>

                <div class="border-t border-white/10 my-3"></div>

                <?php if ($is_bundle_mode && $discount > 0): ?>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-400">Sous-total</span>
                    <span class="text-gray-400"><?= number_format($subtotal, 2, ',', '') ?>€</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-emerald-400">
                        <i class="fas fa-tag text-xs"></i>
                        Réduction<?= $promo_label ? ' (' . htmlspecialchars($promo_label) . ')' : '' ?>
                    </span>
                    <span class="text-emerald-400 font-mono text-xs">-<?= number_format($discount, 2, ',', '') ?>€</span>
                </div>
                <?php elseif (!$is_bundle_mode && !empty($order['coupon_code'])): ?>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-400">Sous-total</span>
                    <span class="text-gray-400 line-through">
                        <?= number_format((float)($order['original_price'] ?? $price), 2, ',', '') ?>€
                    </span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-emerald-400">
                        <i class="fas fa-tag text-xs"></i> Code promo
                    </span>
                    <span class="text-emerald-400 font-mono text-xs">
                        <?= htmlspecialchars($order['coupon_code']) ?>
                    </span>
                </div>
                <?php endif; ?>

                <div class="flex justify-between items-baseline pt-2">
                    <span class="text-white font-semibold">Total à payer</span>
                    <div class="text-right">
                        <span class="text-2xl font-black text-sky-400">
                            <?= number_format($price, 2, ',', '') ?>€
                        </span>
                        <?php if ($is_renewal): ?>
                        <span class="text-xs text-gray-500 block">/mois</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Info sécurité -->
            <div class="bg-emerald-500/5 border border-emerald-500/10 rounded-xl p-3 flex items-start gap-2 text-xs text-emerald-300">
                <i class="fas fa-shield-alt mt-0.5 shrink-0"></i>
                <span>Paiement 100% sécurisé. Vos données bancaires ne transitent jamais par nos serveurs.</span>
            </div>

            <a href="/shop/cart/" class="block text-center text-xs text-gray-500 hover:text-red-400 transition mt-4">
                <i class="fas fa-arrow-left mr-1"></i> Retour au panier
            </a>
        </div>

        <!-- ═══ CHOIX DU PAIEMENT ═══ -->
        <div class="lg:col-span-3 space-y-4">
            
            <div class="animate-in">
                <h1 class="text-2xl font-black text-white mb-1">
                    Choisissez votre mode de paiement
                </h1>
                <p class="text-gray-500 text-sm mb-6">
                    Sélectionnez la méthode qui vous convient le mieux.
                </p>
            </div>

            <!-- Option 1 : Carte bancaire (Stripe) -->
            <a href="<?= htmlspecialchars($checkout_url) ?>" 
               class="payment-option stripe block animate-in-delay">
                <div class="recommended-badge">
                    <i class="fas fa-bolt text-[8px]"></i> Recommandé
                </div>
                <div class="flex items-start gap-4">
                    <div class="payment-icon bg-[#635BFF]/15 text-[#635BFF] border border-[#635BFF]/20">
                        <i class="fas fa-credit-card"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-1">
                            <h3 class="text-lg font-bold text-white">Carte bancaire</h3>
                        </div>
                        <p class="text-sm text-gray-400 mb-3">
                            Paiement instantané et sécurisé via Stripe. Votre serveur est activé immédiatement après paiement.
                        </p>
                        <div class="flex items-center gap-3 text-gray-500 text-lg">
                            <i class="fab fa-cc-visa"></i>
                            <i class="fab fa-cc-mastercard"></i>
                            <i class="fab fa-cc-amex"></i>
                            <i class="fab fa-apple-pay"></i>
                            <i class="fab fa-google-pay"></i>
                        </div>
                        <div class="flex items-center gap-2 mt-3 text-xs text-emerald-400">
                            <i class="fas fa-check-circle"></i>
                            <span>Activation immédiate • Sans frais supplémentaires</span>
                        </div>
                    </div>
                    <div class="text-gray-500 shrink-0">
                        <i class="fas fa-chevron-right text-lg"></i>
                    </div>
                </div>
            </a>

            <!-- Option 2 : PayPal.me -->
            <a href="<?= htmlspecialchars($paypalme_url) ?>" 
               target="_blank"
               class="payment-option paypal block animate-in-delay2">
                <div class="flex items-start gap-4">
                    <div class="payment-icon bg-[#003087]/15 text-[#0070f3] border border-[#0070f3]/20">
                        <i class="fab fa-paypal"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-1">
                            <h3 class="text-lg font-bold text-white">PayPal.me</h3>
                        </div>
                        <p class="text-sm text-gray-400 mb-3">
                            Payez via PayPal.me en indiquant votre numéro de commande en référence. 
                            <span class="text-amber-400 font-semibold">Activation manuelle sous 24h.</span>
                        </p>
                        <div class="bg-white/[0.03] border border-white/10 rounded-lg p-3 text-xs text-gray-300">
                            <div class="flex items-center gap-2 mb-1">
                                <i class="fas fa-info-circle text-sky-400"></i>
                                <span class="font-semibold">Instructions :</span>
                            </div>
                            <ol class="list-decimal ml-4 space-y-0.5 text-gray-400">
                                <li>Cliquez sur le lien PayPal.me ci-dessous</li>
                                <li>Indiquez <strong class="text-white">#<?= htmlspecialchars((string)$order_ref) ?></strong> dans la note</li>
                                <li>Validez le paiement de <strong class="text-white"><?= number_format($price, 2, ',', '') ?>€</strong></li>
                            </ol>
                        </div>
                        <div class="flex items-center gap-2 mt-3 text-xs text-amber-400">
                            <i class="fas fa-clock"></i>
                            <span>Réactivation manuelle • Délai jusqu'à 24h</span>
                        </div>
                    </div>
                    <div class="text-gray-500 shrink-0">
                        <i class="fas fa-external-link-alt text-lg"></i>
                    </div>
                </div>
            </a>

            <!-- FAQ rapide -->
            <div class="glass p-5 rounded-xl animate-in-delay2">
                <h3 class="text-sm font-bold text-white mb-3 flex items-center gap-2">
                    <i class="fas fa-question-circle text-sky-400"></i>
                    Questions fréquentes
                </h3>
                <div class="space-y-2 text-xs">
                    <details class="group">
                        <summary class="flex items-center justify-between cursor-pointer text-gray-300 hover:text-white transition py-1">
                            <span>Quel moyen de paiement choisir ?</span>
                            <i class="fas fa-chevron-down text-gray-500 group-open:rotate-180 transition-transform text-[10px]"></i>
                        </summary>
                        <p class="text-gray-400 mt-2 pl-1">
                            La <strong class="text-white">carte bancaire</strong> est recommandée pour une activation immédiate. 
                            PayPal.me est utile si vous n'avez pas de carte, mais nécessite une vérification manuelle.
                        </p>
                    </details>
                    <details class="group">
                        <summary class="flex items-center justify-between cursor-pointer text-gray-300 hover:text-white transition py-1">
                            <span>Mes données bancaires sont-elles sécurisées ?</span>
                            <i class="fas fa-chevron-down text-gray-500 group-open:rotate-180 transition-transform text-[10px]"></i>
                        </summary>
                        <p class="text-gray-400 mt-2 pl-1">
                            Oui. Nous utilisons <strong class="text-white">Stripe</strong>, un processeur de paiement certifié PCI-DSS niveau 1. 
                            Vos informations bancaires ne transitent jamais par nos serveurs.
                        </p>
                    </details>
                    <details class="group">
                        <summary class="flex items-center justify-between cursor-pointer text-gray-300 hover:text-white transition py-1">
                            <span>Puis-je annuler ma commande ?</span>
                            <i class="fas fa-chevron-down text-gray-500 group-open:rotate-180 transition-transform text-[10px]"></i>
                        </summary>
                        <p class="text-gray-400 mt-2 pl-1">
                            Oui, tant que le paiement n'a pas été effectué. Retournez simplement au panier pour annuler.
                        </p>
                    </details>
                </div>
            </div>

        </div>
    </div>
</div>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/inc/footer.php'; ?>

</body>
</html>
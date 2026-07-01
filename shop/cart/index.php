<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/lang.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/shop/order/lib/promo/promo.php';

$active_nav = 'cart';
$page_title = 'Panier';

if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_item') {
        $slug = trim($_POST['slug'] ?? '');
        $name = trim($_POST['name'] ?? 'Produit');
        $price = (float)($_POST['price'] ?? 0);
        $period = trim($_POST['period'] ?? '');

        if ($slug !== '') {
            if (!isset($_SESSION['cart'][$slug])) {
                $_SESSION['cart'][$slug] = [
                    'slug' => $slug,
                    'name' => $name,
                    'price' => $price,
                    'period' => $period,
                    'quantity' => 0,
                ];
            }
            $_SESSION['cart'][$slug]['quantity'] += 1;
        }
    } elseif ($action === 'update_cart') {
        foreach ($_POST['items'] ?? [] as $slug => $quantity) {
            $quantity = max(0, (int)$quantity);
            if ($quantity <= 0) {
                unset($_SESSION['cart'][$slug]);
            } elseif (isset($_SESSION['cart'][$slug])) {
                $_SESSION['cart'][$slug]['quantity'] = $quantity;
            }
        }
    } elseif ($action === 'remove_item') {
        $slug = trim($_POST['slug'] ?? '');
        if ($slug !== '') {
            unset($_SESSION['cart'][$slug]);
        }
    } elseif ($action === 'clear_cart') {
        $_SESSION['cart'] = [];
    } elseif ($action === 'apply_promo') {
        $promo_code = trim($_POST['promo_code'] ?? '');
        $_SESSION['promo_code'] = $promo_code;
    }

    header('Location: /shop/cart/');
    exit();
}

$cart = $_SESSION['cart'];
$subtotal = 0;
foreach ($cart as $item) {
    $subtotal += (float)$item['price'] * (int)$item['quantity'];
}

$promo_code = trim($_SESSION['promo_code'] ?? '');
$promo = null;
$promo_error = null;
$promo_amount = 0;
$discount_label = '';

if ($promo_code !== '') {
    $promo = checkPromoCode($promos, $promo_code, 'cart');
    if ($promo) {
        $promo_amount = $promo['type'] === 'percent'
            ? round($subtotal * $promo['discount'] / 100, 2)
            : min((float)$promo['discount'], $subtotal);
        $discount_label = $promo['type'] === 'percent'
            ? '-' . $promo['discount'] . '%'
            : '-' . number_format($promo['discount'], 2, '.', '') . '€';
    } else {
        $promo_error = 'Code promo invalide ou expiré.';
    }
}

$shipping = 0;
$discount_amount = min($promo_amount, max(0, $subtotal));
$total = max(0.50, $subtotal - $discount_amount + $shipping);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OrinHeberge | Panier</title>
    <link rel="icon" type="image/png" href="https://heberge.orinstone.deepstone.fr/favicon.ico">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #0b0f19; scroll-behavior: smooth; }
        .glass { background: rgba(255,255,255,0.04); backdrop-filter: blur(14px); border: 1px solid rgba(255,255,255,0.08); }
        .gradient-text { background: linear-gradient(90deg, #38bdf8, #818cf8); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
    </style>
</head>
<body class="text-gray-200 font-sans min-h-screen flex flex-col justify-between antialiased">
<?php $active_nav = 'cart'; include $_SERVER['DOCUMENT_ROOT'] . '/inc/navbar.php'; ?>

<main class="flex-grow px-4 py-10 md:px-6 lg:px-8">
    <div class="max-w-7xl mx-auto">
        <div class="mb-8 flex flex-col md:flex-row md:items-end md:justify-between gap-4">
            <div>
                <div class="inline-flex items-center gap-2 rounded-full border border-sky-500/30 bg-sky-500/10 px-3 py-1 text-xs uppercase tracking-[0.3em] text-sky-400">
                    <i class="fas fa-shopping-cart"></i> Votre panier
                </div>
                <h1 class="mt-4 text-4xl md:text-5xl font-black tracking-tight gradient-text">Panier</h1>
                <p class="mt-3 max-w-2xl text-sm md:text-base text-gray-400">Gérez vos offres sélectionnées, ajustez les quantités et passez à la validation.</p>
            </div>
            <a href="/offres/" class="inline-flex items-center justify-center gap-2 rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm font-semibold text-gray-200 transition hover:bg-white/10">
                <i class="fas fa-arrow-left"></i> Continuer les achats
            </a>
        </div>

        <?php if (empty($cart)): ?>
            <div class="glass rounded-3xl border border-white/10 p-10 text-center">
                <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-sky-500/10 text-3xl text-sky-400">
                    <i class="fas fa-shopping-basket"></i>
                </div>
                <h2 class="text-2xl font-bold text-white">Votre panier est vide</h2>
                <p class="mt-3 text-sm text-gray-400">Ajoutez une offre depuis la page des offres pour la retrouver ici.</p>
                <a href="/offres/" class="mt-6 inline-flex items-center gap-2 rounded-2xl bg-sky-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-sky-500">
                    <i class="fas fa-tags"></i> Découvrir les offres
                </a>
            </div>
        <?php else: ?>
            <div class="grid gap-6 xl:grid-cols-[1.6fr_0.8fr]">
                <div class="glass rounded-3xl border border-white/10 p-4 md:p-6">
                    <form method="post" class="space-y-4">
                        <input type="hidden" name="action" value="update_cart">
                        <div class="flex items-center justify-between border-b border-white/10 pb-4">
                            <div>
                                <h2 class="text-xl font-bold text-white">Produits sélectionnés</h2>
                                <p class="text-sm text-gray-400">Modifiez les quantités selon vos besoins.</p>
                            </div>
                            <button type="submit" class="rounded-2xl border border-white/10 bg-white/5 px-3 py-2 text-sm text-gray-300 transition hover:bg-white/10">
                                <i class="fas fa-save"></i> Mettre à jour
                            </button>
                        </div>

                        <div class="space-y-3">
                            <?php foreach ($cart as $slug => $item):
                                $line_total = (float)$item['price'] * (int)$item['quantity'];
                            ?>
                                <div class="rounded-2xl border border-white/10 bg-[#0d1321] p-4">
                                    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                                        <div class="flex-1">
                                            <div class="flex items-center gap-2">
                                                <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-sky-500/10 text-sky-400">
                                                    <i class="fas fa-box"></i>
                                                </div>
                                                <div>
                                                    <h3 class="font-semibold text-white"><?= htmlspecialchars($item['name']) ?></h3>
                                                    <p class="text-sm text-gray-400"><?= htmlspecialchars($item['period'] ?: 'Offre') ?></p>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="flex flex-wrap items-center gap-3">
                                            <div class="flex items-center rounded-2xl border border-white/10 bg-white/5">
                                                <button type="button" onclick="this.parentElement.querySelector('input').stepDown(); this.parentElement.querySelector('input').dispatchEvent(new Event('change'))" class="px-3 py-2 text-gray-300 transition hover:text-white">
                                                    <i class="fas fa-minus"></i>
                                                </button>
                                                <input type="number" name="items[<?= htmlspecialchars($slug) ?>]" min="0" value="<?= (int)$item['quantity'] ?>" class="w-14 border-0 bg-transparent text-center text-sm text-white outline-none">
                                                <button type="button" onclick="this.parentElement.querySelector('input').stepUp(); this.parentElement.querySelector('input').dispatchEvent(new Event('change'))" class="px-3 py-2 text-gray-300 transition hover:text-white">
                                                    <i class="fas fa-plus"></i>
                                                </button>
                                            </div>

                                            <div class="min-w-[90px] text-right">
                                                <div class="text-sm text-gray-400">Total</div>
                                                <div class="font-semibold text-white"><?= number_format($line_total, 2, ',', ' ') ?> €</div>
                                            </div>

                                            <form method="post" class="inline-block">
                                                <input type="hidden" name="action" value="remove_item">
                                                <input type="hidden" name="slug" value="<?= htmlspecialchars($slug) ?>">
                                                <button type="submit" class="rounded-2xl border border-red-500/20 bg-red-500/10 px-3 py-2 text-sm text-red-400 transition hover:bg-red-500/20">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </form>

                    <form method="post" class="mt-4">
                        <input type="hidden" name="action" value="clear_cart">
                        <button type="submit" class="rounded-2xl border border-white/10 bg-white/5 px-4 py-2 text-sm text-gray-300 transition hover:bg-white/10">
                            <i class="fas fa-broom"></i> Vider le panier
                        </button>
                    </form>
                </div>

                <aside class="space-y-4">
                    <div class="glass rounded-3xl border border-white/10 p-6">
                        <h2 class="text-xl font-bold text-white">Résumé</h2>
                        <div class="mt-5 space-y-3 text-sm text-gray-300">
                            <div class="flex items-center justify-between">
                                <span>Sous-total</span>
                                <span><?= number_format($subtotal, 2, ',', ' ') ?> €</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span>Livraison</span>
                                <span class="text-emerald-400">Gratuite</span>
                            </div>
                            <div class="border-t border-white/10 pt-3 mt-3 flex items-center justify-between text-base font-semibold text-white">
                                <span>Total</span>
                                <span><?= number_format($total, 2, ',', ' ') ?> €</span>
                            </div>
                        </div>

                        <form method="post" class="mt-6 space-y-3">
                            <input type="hidden" name="action" value="apply_promo">
                            <label for="promo_code" class="text-sm font-medium text-gray-300">Code promo</label>
                            <div class="flex gap-2">
                                <input id="promo_code" name="promo_code" value="<?= htmlspecialchars($promo_code) ?>" placeholder="Ex. AMOUR14" class="w-full rounded-2xl border border-white/10 bg-[#0d1321] px-3 py-2 text-sm text-white outline-none ring-0">
                                <button type="submit" class="rounded-2xl border border-sky-500/20 bg-sky-500/10 px-3 py-2 text-sm font-semibold text-sky-300 transition hover:bg-sky-500/20">
                                    Appliquer
                                </button>
                            </div>
                            <?php if ($promo_error): ?>
                                <p class="text-sm text-red-400"><?= htmlspecialchars($promo_error) ?></p>
                            <?php elseif ($promo): ?>
                                <p class="text-sm text-emerald-400">Promo appliquée : <?= htmlspecialchars($promo['name']) ?></p>
                            <?php endif; ?>
                        </form>

                        <?php if ($promo && $discount_amount > 0): ?>
                            <div class="mt-4 flex items-center justify-between text-sm text-gray-300">
                                <span>Réduction</span>
                                <span class="text-emerald-400">-<?= number_format($discount_amount, 2, ',', ' ') ?> €</span>
                            </div>
                        <?php endif; ?>

                        <div class="mt-6 rounded-2xl border border-sky-500/20 bg-sky-500/10 p-4 text-sm text-sky-300">
                            <i class="fas fa-info-circle mr-2"></i> Le paiement sera poursuivi depuis la suite du tunnel de commande.
                        </div>

                        <a href="/offres/" class="mt-6 inline-flex w-full items-center justify-center gap-2 rounded-2xl bg-sky-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-sky-500">
                            <i class="fas fa-credit-card"></i> Finaliser ma commande
                        </a>
                    </div>
                </aside>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/inc/footer.php'; ?>
</body>
</html>

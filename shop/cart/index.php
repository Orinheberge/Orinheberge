<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/lang.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/db.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/shop/order/lib/promo/promo.php';
// Le panier ne crée plus aucun serveur lui-même : gratuit et payant sont
// tous les deux entièrement gérés par /shop/order/ (voir checkout ci-dessous).

$active_nav = 'cart';
$page_title = 'Panier';

function loadCartProduct(PDO $pdo, string $slug): ?array {
    $product = getProductBySlug($pdo, $slug);
    if ($product) {
        return $product;
    }

    $stmt = $pdo->prepare('SELECT * FROM products WHERE slug = ? AND is_active = 1 LIMIT 1');
    $stmt->execute([$slug]);
    return $stmt->fetch() ?: null;
}

function ensureCartTable(PDO $pdo): void {
    try {
        $check = $pdo->query("SHOW TABLES LIKE 'carts'");
        if ($check && $check->fetch()) {
            return;
        }
    } catch (PDOException $e) {
        error_log('Cart table check failed: ' . $e->getMessage());
    }

    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS carts (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                cart_data JSON NOT NULL,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_carts_user (user_id),
                KEY idx_carts_updated_at (updated_at),
                CONSTRAINT fk_carts_user
                    FOREIGN KEY (user_id) REFERENCES users(id)
                    ON DELETE CASCADE
                    ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    } catch (PDOException $e) {
        error_log('Cart table creation failed: ' . $e->getMessage());
    }
}

function loadCartFromDatabase(PDO $pdo, int $userId): array {
    try {
        ensureCartTable($pdo);
        $stmt = $pdo->prepare('SELECT cart_data FROM carts WHERE user_id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        if (!$row || empty($row['cart_data'])) {
            return [];
        }

        $data = json_decode($row['cart_data'], true);
        return is_array($data) ? $data : [];
    } catch (Throwable $e) {
        error_log('Cart load failed: ' . $e->getMessage());
        return [];
    }
}

function saveCartToDatabase(PDO $pdo, int $userId, array $cart): void {
    if ($userId <= 0) {
        return;
    }

    try {
        ensureCartTable($pdo);
        $payload = json_encode($cart, JSON_UNESCAPED_UNICODE);
        $stmt = $pdo->prepare('INSERT INTO carts (user_id, cart_data, updated_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE cart_data = VALUES(cart_data), updated_at = NOW()');
        $stmt->execute([$userId, $payload]);
    } catch (Throwable $e) {
        error_log('Cart save failed: ' . $e->getMessage());
    }
}

function syncCartWithStorage(PDO $pdo, array $cart): void {
    if (!empty($_SESSION['user_id'])) {
        saveCartToDatabase($pdo, (int)$_SESSION['user_id'], $cart);
    }
}

if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

if (!empty($_SESSION['user_id']) && empty($_SESSION['cart'])) {
    $_SESSION['cart'] = loadCartFromDatabase($pdo, (int)$_SESSION['user_id']);
}

if (!is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_item') {
        $slug = trim($_POST['slug'] ?? '');

        // Le prix et le nom ne doivent JAMAIS venir du formulaire (POST) :
        // un visiteur peut modifier ces champs dans son navigateur et
        // ajouter un produit à 0,01€ ou avec un faux libellé.
        // On regarde uniquement le slug et on va chercher le vrai produit en base.
        if ($slug !== '') {
            $product = loadCartProduct($pdo, $slug);

            if ($product) {
                if (!isset($_SESSION['cart'][$slug])) {
                    $_SESSION['cart'][$slug] = [
                        'slug' => $slug,
                        'name' => $product['name'],
                        'price' => (float)$product['price'],
                        'period' => trim($_POST['period'] ?? ''),
                        'quantity' => 0,
                    ];
                }
                $_SESSION['cart'][$slug]['quantity'] += 1;
            }
        }
        syncCartWithStorage($pdo, $_SESSION['cart']);
    } elseif ($action === 'update_cart') {
        foreach ($_POST['items'] ?? [] as $slug => $quantity) {
            $quantity = max(0, (int)$quantity);
            if ($quantity <= 0) {
                unset($_SESSION['cart'][$slug]);
            } elseif (isset($_SESSION['cart'][$slug])) {
                $_SESSION['cart'][$slug]['quantity'] = $quantity;
            }
        }
        syncCartWithStorage($pdo, $_SESSION['cart']);
    } elseif ($action === 'remove_item') {
        $slug = trim($_POST['slug'] ?? '');
        if ($slug !== '') {
            unset($_SESSION['cart'][$slug]);
        }
        syncCartWithStorage($pdo, $_SESSION['cart']);
    } elseif ($action === 'clear_cart') {
        $_SESSION['cart'] = [];
        syncCartWithStorage($pdo, $_SESSION['cart']);
    } elseif ($action === 'apply_promo') {
        $promo_code = trim($_POST['promo_code'] ?? '');
        if ($promo_code === '') {
            unset($_SESSION['promo_code']);
        } else {
            $_SESSION['promo_code'] = $promo_code;
        }
        syncCartWithStorage($pdo, $_SESSION['cart']);
    } elseif ($action === 'clear_promo') {
        unset($_SESSION['promo_code']);
        syncCartWithStorage($pdo, $_SESSION['cart']);
    } elseif ($action === 'checkout') {
        try {
            if (!isset($_SESSION['user_id'])) {
                header('Location: /login/');
                exit();
            }

            if (empty($_SESSION['cart'])) {
                header('Location: /shop/cart/');
                exit();
            }

            $stmt = $pdo->prepare('SELECT * FROM users WHERE id=? LIMIT 1');
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
            if (!$user) {
                header('Location: /login/');
                exit();
            }

            // Tout (gratuit + payant) est désormais géré par /shop/order/ :
            // il crée immédiatement les serveurs des offres gratuites du bundle,
            // et démarre le paiement Stripe pour le reste. On ne fait plus
            // aucun traitement ici, on transmet juste tout le panier.
            // Le code promo (s'il y en a un en session) est repris tel quel
            // par order.php, qui applique exactement la même logique promo
            // (checkPromoCode + applyPromo) que celle utilisée ici pour l'aperçu.
            $bundle_items = [];
            $bundle_slugs = [];
            foreach ($_SESSION['cart'] as $slug => $item) {
                $quantity = (int)($item['quantity'] ?? 0);
                if ($quantity <= 0) {
                    continue;
                }

                $product = loadCartProduct($pdo, $slug);
                if (!$product) {
                    continue;
                }

                $bundle_items[] = ['slug' => $slug, 'product' => $product, 'quantity' => $quantity];
                $bundle_slugs[] = $slug;
            }

            if (empty($bundle_items)) {
                header('Location: /shop/cart/');
                exit();
            }

            $_SESSION['checkout_bundle'] = [
                'items' => $bundle_items,
            ];
            $_SESSION['cart'] = [];
            syncCartWithStorage($pdo, $_SESSION['cart']);
            header('Location: /shop/order/?plan=' . urlencode(implode(',', $bundle_slugs)));
            exit();
        } catch (Throwable $e) {
            error_log('Cart checkout error: ' . $e->getMessage());
            $_SESSION['checkout_error'] = 'La finalisation de la commande a échoué. Veuillez réessayer.';
            header('Location: /shop/cart/');
            exit();
        }
    }

    header('Location: /shop/cart/');
    exit();
}

$cart = $_SESSION['cart'];
$subtotal = 0;
foreach ($cart as $item) {
    $subtotal += (float)$item['price'] * (int)$item['quantity'];
}

/*
|--------------------------------------------------------------------------
| LOGIQUE CODE PROMO — alignée sur /shop/order/ (mêmes fonctions promo.php)
|--------------------------------------------------------------------------
| Le panier n'affiche qu'un APERÇU du prix : le calcul définitif et la
| création des commandes se font dans order.php, qui applique exactement
| la même règle (code manuel en session, sinon promo automatique la plus
| avantageuse) via getActiveAutoPromo() / checkPromoCode() / applyPromo().
*/

$promo_code   = trim($_SESSION['promo_code'] ?? '');
$active_promo = getActiveAutoPromo($promos);
$applied_promo = null;
$promo_error   = null;

if ($promo_code !== '') {
    $applied_promo = checkPromoCode($promos, $promo_code, 'cart');
    if (!$applied_promo) {
        $promo_error = 'Code promo invalide ou expiré.';
    }
}

$promo = $applied_promo ?? $active_promo;
$prices = $promo ? applyPromo((float)$subtotal, $promo) : [
    'original_price' => (float)$subtotal,
    'reduction'       => 0,
    'final_price'     => (float)$subtotal,
    'label'           => null,
];

$shipping = 0;
$discount_amount = $prices['reduction'];
$total = $prices['final_price'] + $shipping;
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

        <?php if (!empty($_SESSION['checkout_error'])): ?>
            <div class="mb-6 rounded-2xl border border-red-500/20 bg-red-500/10 p-4 text-sm text-red-300">
                <i class="fas fa-exclamation-triangle mr-2"></i> <?= htmlspecialchars($_SESSION['checkout_error'], ENT_QUOTES) ?>
            </div>
            <?php unset($_SESSION['checkout_error']); ?>
        <?php endif; ?>

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
                                                    <h3 class="font-semibold text-white"><?= htmlspecialchars($item['name'], ENT_QUOTES) ?></h3>
                                                    <p class="text-sm text-gray-400"><?= htmlspecialchars($item['period'] ?: 'Offre', ENT_QUOTES) ?></p>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="flex flex-wrap items-center gap-3">
                                            <div class="flex items-center rounded-2xl border border-white/10 bg-white/5">
                                                <button type="button" onclick="this.parentElement.querySelector('input').stepDown(); this.parentElement.querySelector('input').dispatchEvent(new Event('change'))" class="px-3 py-2 text-gray-300 transition hover:text-white">
                                                    <i class="fas fa-minus"></i>
                                                </button>
                                                <input type="number" name="items[<?= htmlspecialchars($slug, ENT_QUOTES) ?>]" min="0" value="<?= (int)$item['quantity'] ?>" class="w-14 border-0 bg-transparent text-center text-sm text-white outline-none">
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
                                                <input type="hidden" name="slug" value="<?= htmlspecialchars($slug, ENT_QUOTES) ?>">
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

                            <?php if ($promo && $discount_amount > 0): ?>
                            <div class="flex items-center justify-between">
                                <span>Réduction <?= $applied_promo ? '(code)' : '(auto)' ?></span>
                                <span class="text-emerald-400">-<?= number_format($discount_amount, 2, ',', ' ') ?> €</span>
                            </div>
                            <?php endif; ?>

                            <div class="border-t border-white/10 pt-3 mt-3 flex items-center justify-between text-base font-semibold text-white">
                                <span>Total</span>
                                <span><?= number_format($total, 2, ',', ' ') ?> €</span>
                            </div>
                        </div>

                        <div class="mt-6 space-y-3">
                            <label for="promo_code" class="text-sm font-medium text-gray-300">Code promo</label>

                            <?php if ($applied_promo): ?>
                            <div class="flex items-center justify-between gap-2 rounded-2xl border border-white/10 bg-[#0d1321] px-3 py-2">
                                <span class="text-sm text-gray-300">
                                    <i class="fas fa-tag text-emerald-400 mr-1"></i>
                                    <strong class="text-white"><?= htmlspecialchars($applied_promo['code'], ENT_QUOTES) ?></strong>
                                </span>
                                <form method="post">
                                    <input type="hidden" name="action" value="clear_promo">
                                    <button type="submit" class="text-xs text-red-400 transition hover:text-red-300">
                                        <i class="fas fa-times"></i> Retirer
                                    </button>
                                </form>
                            </div>
                            <?php else: ?>
                            <form method="post" class="flex gap-2">
                                <input type="hidden" name="action" value="apply_promo">
                                <input id="promo_code" name="promo_code" value="<?= htmlspecialchars($promo_code, ENT_QUOTES) ?>" placeholder="Ex. AMOUR14" class="w-full rounded-2xl border border-white/10 bg-[#0d1321] px-3 py-2 text-sm text-white outline-none ring-0">
                                <button type="submit" class="rounded-2xl border border-sky-500/20 bg-sky-500/10 px-3 py-2 text-sm font-semibold text-sky-300 transition hover:bg-sky-500/20">
                                    Appliquer
                                </button>
                            </form>
                            <?php endif; ?>

                            <?php if ($promo_error): ?>
                                <p class="text-sm text-red-400"><?= htmlspecialchars($promo_error, ENT_QUOTES) ?></p>
                            <?php elseif ($promo): ?>
                                <p class="text-sm text-emerald-400">
                                    Promo appliquée : <?= htmlspecialchars($promo['name'], ENT_QUOTES) ?>
                                    (<?= htmlspecialchars($prices['label'], ENT_QUOTES) ?>)
                                    <?= $applied_promo ? '' : ' — automatique' ?>
                                </p>
                            <?php endif; ?>
                        </div>

                        <div class="mt-6 rounded-2xl border border-sky-500/20 bg-sky-500/10 p-4 text-sm text-sky-300">
                            <i class="fas fa-info-circle mr-2"></i> Le paiement sera poursuivi depuis la suite du tunnel de commande.
                        </div>

                        <form method="post" class="mt-6">
                            <input type="hidden" name="action" value="checkout">
                            <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-2xl bg-sky-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-sky-500">
                                <i class="fas fa-credit-card"></i> Finaliser ma commande
                            </button>
                        </form>
                    </div>
                </aside>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/inc/footer.php'; ?>
</body>

<script src="https://<?php echo $_SERVER['HTTP_HOST']; ?>/inc/navbar.js?v=<?php echo filemtime($_SERVER['DOCUMENT_ROOT'] . '/inc/navbar.js'); ?>"></script>

</html>
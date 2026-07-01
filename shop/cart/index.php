<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/lang.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/db.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/shop/order/lib/promo/promo.php';

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
        $pdo->exec("""
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
        """);
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

function processFreeOrder(PDO $pdo, array $user, array $product, array $headers_admin, string $panel_url): array {
    $panelUser = getOrCreatePanelUser($panel_url, $headers_admin, $user, $pdo);
    $pass = $panelUser['pass'];
    if ($pass) {
        $_SESSION['panel_password'] = $pass;
    }

    $srv = createPanelServer($panel_url, $headers_admin, $product, $panelUser['id']);
    $order_id = strtoupper(substr(md5(uniqid('', true)), 0, 8));

    $pdo->prepare('
        INSERT INTO orders
          (user_id, product_id, order_id, service_name, ram, disk, cpu,
           server_id, uuid, id_server_panel, status, renewal_price, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'pending\', 0, NOW())
    ')->execute([
        $_SESSION['user_id'],
        $product['id'],
        $order_id,
        $product['name'],
        $product['ram'],
        $product['disk'],
        $product['cpu'],
        $srv['id'],
        $srv['uuid'],
        $srv['identifier'],
    ]);

    require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/smtp.php';
    $username_display = !empty($user['pseudo']) ? $user['pseudo'] : $user['firstname'];
    send_order_confirmation_email(
        $pdo, $user['email'], $username_display,
        $order_id, $product['name'], 0.0,
        $srv['identifier'], $pass ?? null, $panel_url
    );

    return [
        'order_id' => $order_id,
        'server_id' => $srv['id'],
        'offer_name' => $product['name'],
        'panel_password' => $pass ?? ($user['panel_password'] ?? null),
    ];
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
        $_SESSION['promo_code'] = $promo_code;
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

            $free_items = [];
            $paid_items = [];
            $paid_total = 0.0;
            foreach ($_SESSION['cart'] as $slug => $item) {
                if ((int)($item['quantity'] ?? 0) <= 0) {
                    continue;
                }

                $product = loadCartProduct($pdo, $slug);
                if (!$product) {
                    continue;
                }

                $product_type = strtolower((string)($product['type'] ?? ''));
                $product_price = (float)($product['price'] ?? 0);
                $is_free = $product_type === 'free' || $product_price <= 0;

                if ($is_free) {
                    $free_items[] = ['product' => $product, 'quantity' => (int)($item['quantity'] ?? 0)];
                } else {
                    $quantity = (int)($item['quantity'] ?? 0);
                    $paid_items[] = ['slug' => $slug, 'product' => $product, 'quantity' => $quantity];
                    $paid_total += $product_price * $quantity;
                }
            }

            if (!empty($free_items)) {
                foreach ($free_items as $entry) {
                    for ($i = 0; $i < $entry['quantity']; $i++) {
                        $result = processFreeOrder($pdo, $user, $entry['product'], $headers_admin, $panel_url);
                        $_SESSION['success_order_id'] = $result['order_id'];
                        $_SESSION['success_email'] = $user['email'];
                        $_SESSION['success_server_id'] = $result['server_id'];
                        $_SESSION['success_offer'] = $result['offer_name'];
                        $_SESSION['success_panel_password'] = $result['panel_password'];
                    }
                }
            }

            if (!empty($paid_items)) {
                $bundle_slugs = [];
                foreach ($paid_items as $entry) {
                    $bundle_slugs[] = $entry['slug'];
                }

                $_SESSION['checkout_bundle'] = [
                    'items' => $paid_items,
                    'total' => round($paid_total, 2),
                    'label' => count($paid_items) > 1 ? 'Offres combinées' : ($paid_items[0]['product']['name'] ?? 'Offre')
                ];
                $_SESSION['cart'] = [];
                syncCartWithStorage($pdo, $_SESSION['cart']);
                header('Location: /shop/order/?plan=' . urlencode(implode($bundle_slugs, ',')));
                exit();
            }

            if (!empty($free_items)) {
                $_SESSION['cart'] = [];
                syncCartWithStorage($pdo, $_SESSION['cart']);
                header('Location: /shop/success/?type=free');
                exit();
            }

            header('Location: /shop/cart/');
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

        <?php if (!empty($_SESSION['checkout_error'])): ?>
            <div class="mb-6 rounded-2xl border border-red-500/20 bg-red-500/10 p-4 text-sm text-red-300">
                <i class="fas fa-exclamation-triangle mr-2"></i> <?= htmlspecialchars($_SESSION['checkout_error']) ?>
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
</html>

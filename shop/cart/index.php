<?php
/**
 * /shop/cart/ — Panier utilisateur
 * Gestion des offres sélectionnées, codes promo et redirection vers le tunnel de commande.
 */

// ═══════════════════════════════════════════
// ⚡ BUFFER OUTPUT — doit être AVANT tout include
// ═══════════════════════════════════════════
if (ob_get_level() === 0) {
    ob_start();
}

// ⚡ Fonction de redirection robuste (fallback JS si headers déjà envoyés)
function safeRedirect(string $url): void {
    // Nettoyer tous les buffers accumulés
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    if (headers_sent($file, $line)) {
        // Fallback : meta refresh + lien cliquable + debug info
        $safeUrl = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
        echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8">';
        echo '<meta http-equiv="refresh" content="0;url=' . $safeUrl . '">';
        echo '<title>Redirection…</title>';
        echo '<style>body{background:#0b0f19;color:#e2e8f0;font-family:system-ui,sans-serif;padding:3rem;text-align:center;}</style>';
        echo '</head><body>';
        echo '<h2 style="color:#38bdf8;">⏳ Redirection en cours…</h2>';
        echo '<p>Si la redirection ne démarre pas, <a href="' . $safeUrl . '" style="color:#38bdf8;text-decoration:underline;">cliquez ici pour continuer</a>.</p>';
        echo '<hr style="border-color:#1e293b;margin:2rem 0;">';
        echo '<p style="color:#f87171;font-size:12px;">Debug: Headers already sent in <code>' . htmlspecialchars($file) . '</code> on line <strong>' . $line . '</strong></p>';
        echo '</body></html>';
        exit();
    }
    
    header('Location: ' . $url);
    exit();
}

session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/lang.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/db.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/shop/order/lib/promo/promo.php';

$active_nav = 'cart';
$page_title = 'Panier';

// ═══════════════════════════════════════════
// HELPERS
// ═══════════════════════════════════════════
function loadCartProduct(PDO $pdo, string $slug): ?array {
    $product = getProductBySlug($pdo, $slug);
    if ($product) return $product;

    $stmt = $pdo->prepare('SELECT * FROM products WHERE slug = ? AND is_active = 1 LIMIT 1');
    $stmt->execute([$slug]);
    return $stmt->fetch() ?: null;
}

function ensureCartTable(PDO $pdo): void {
    try {
        $check = $pdo->query("SHOW TABLES LIKE 'carts'");
        if ($check && $check->fetch()) return;
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
                CONSTRAINT fk_carts_user FOREIGN KEY (user_id) REFERENCES users(id)
                    ON DELETE CASCADE ON UPDATE CASCADE
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
        if (!$row || empty($row['cart_data'])) return [];
        $data = json_decode($row['cart_data'], true);
        return is_array($data) ? $data : [];
    } catch (Throwable $e) {
        error_log('Cart load failed: ' . $e->getMessage());
        return [];
    }
}

function saveCartToDatabase(PDO $pdo, int $userId, array $cart): void {
    if ($userId <= 0) return;
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

// ═══════════════════════════════════════════
// INITIALISATION PANIER
// ═══════════════════════════════════════════
if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

if (!empty($_SESSION['user_id']) && empty($_SESSION['cart'])) {
    $_SESSION['cart'] = loadCartFromDatabase($pdo, (int)$_SESSION['user_id']);
}

if (!is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// ═══════════════════════════════════════════
// TRAITEMENT DES ACTIONS POST
// ═══════════════════════════════════════════
$flash_message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_item') {
        $slug = trim($_POST['slug'] ?? '');
        if ($slug !== '') {
            $product = loadCartProduct($pdo, $slug);
            if ($product) {
                if (!isset($_SESSION['cart'][$slug])) {
                    $_SESSION['cart'][$slug] = [
                        'slug'     => $slug,
                        'name'     => $product['name'],
                        'price'    => (float)$product['price'],
                        'period'   => trim($_POST['period'] ?? ''),
                        'quantity' => 0,
                    ];
                }
                $_SESSION['cart'][$slug]['quantity'] += 1;
                $flash_message = ['type' => 'success', 'text' => '"' . $product['name'] . '" ajouté au panier'];
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
        if ($slug !== '' && isset($_SESSION['cart'][$slug])) {
            $name = $_SESSION['cart'][$slug]['name'] ?? 'Produit';
            unset($_SESSION['cart'][$slug]);
            $flash_message = ['type' => 'info', 'text' => '"' . $name . '" retiré du panier'];
        }
        syncCartWithStorage($pdo, $_SESSION['cart']);

    } elseif ($action === 'clear_cart') {
        $_SESSION['cart'] = [];
        syncCartWithStorage($pdo, $_SESSION['cart']);
        $flash_message = ['type' => 'info', 'text' => 'Panier vidé'];

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
                safeRedirect('/login/');
            }

            if (empty($_SESSION['cart'])) {
                safeRedirect('/shop/cart/');
            }

            $stmt = $pdo->prepare('SELECT * FROM users WHERE id=? LIMIT 1');
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch();
            if (!$user) {
                safeRedirect('/login/');
            }

            $bundle_items = [];
            $bundle_slugs = [];
            foreach ($_SESSION['cart'] as $slug => $item) {
                $quantity = (int)($item['quantity'] ?? 0);
                if ($quantity <= 0) continue;

                $product = loadCartProduct($pdo, $slug);
                if (!$product) {
                    // Produit introuvable/désactivé : on le signale au lieu de
                    // le faire disparaître silencieusement (c'était la cause
                    // du "la redirection ne fait rien" quand TOUT le panier
                    // finissait par être ignoré ici).
                    error_log('[Cart] Checkout: produit introuvable pour slug=' . $slug);
                    continue;
                }

                $bundle_items[] = ['slug' => $slug, 'product' => $product, 'quantity' => $quantity];
                $bundle_slugs[] = $slug;
            }

            if (empty($bundle_items)) {
                $_SESSION['checkout_error'] = 'Aucun des articles de votre panier n\'est disponible actuellement. Veuillez le vider et réessayer.';
                safeRedirect('/shop/cart/');
            }

            // ─── Calcul du total du bundle (avec promo éventuelle) ───
            $bundle_subtotal = 0.0;
            foreach ($bundle_items as $bi) {
                $bundle_subtotal += (float)$bi['product']['price'] * (int)$bi['quantity'];
            }

            $checkout_promo_code   = trim($_SESSION['promo_code'] ?? '');
            $checkout_active_promo = getActiveAutoPromo($promos);
            $checkout_applied_promo = null;
            if ($checkout_promo_code !== '') {
                $checkout_applied_promo = checkPromoCode($promos, $checkout_promo_code, 'cart');
            }
            $checkout_promo = $checkout_applied_promo ?? $checkout_active_promo;
            $checkout_prices = $checkout_promo
                ? applyPromo($bundle_subtotal, $checkout_promo)
                : [
                    'original_price' => $bundle_subtotal,
                    'reduction'      => 0,
                    'final_price'    => $bundle_subtotal,
                    'label'          => null,
                ];
            $bundle_total = (float)$checkout_prices['final_price'];

            // On garde le détail par article en session, uniquement pour
            // l'affichage du récap ligne par ligne sur payment-choice.
            // La source de vérité pour le PAIEMENT reste désormais la table
            // `orders` (créée juste en dessous), car c'est ce que lit
            // /shop/order/checkout/.
            $_SESSION['checkout_bundle'] = [
                'items'       => $bundle_items,
                'promo_code'  => $checkout_applied_promo ? $checkout_promo_code : null,
                'promo_label' => $checkout_prices['label'],
                'subtotal'    => $bundle_subtotal,
                'discount'    => $checkout_prices['reduction'],
                'total'       => $bundle_total,
                'created_at'  => time(),
            ];

            // ─── Nom de service lisible pour la ligne de commande ───
            $first_name = $bundle_items[0]['product']['name'] ?? 'Produit';
            $extra_count = count($bundle_items) - 1;
            $service_name = $extra_count > 0
                ? $first_name . ' + ' . $extra_count . ' autre' . ($extra_count > 1 ? 's' : '') . ' article' . ($extra_count > 1 ? 's' : '')
                : $first_name;

            // ⚠️ LIMITATION CONNUE : la table `orders` (telle qu'on la voit
            // utilisée dans payment-choice.php / checkout/) ne semble avoir
            // qu'un seul `product_id` par ligne. Un panier multi-produits ne
            // peut donc pas être représenté fidèlement sans une table
            // `order_items` séparée. En attendant, on rattache la commande
            // au PREMIER produit du panier (product_id), le prix total réel
            // du panier étant lui bien stocké dans `renewal_price` /
            // `original_price`. Le récap affiché à l'utilisateur reste
            // correct (basé sur $_SESSION['checkout_bundle']['items']),
            // seule la ligne `product_id` en base est simplifiée.
            $first_product_id = $bundle_items[0]['product']['id'] ?? null;

            $order_ref = 'CMD-' . strtoupper(bin2hex(random_bytes(4)));

            try {
                $insert = $pdo->prepare("
                    INSERT INTO orders
                        (order_id, user_id, product_id, service_name, renewal_price, original_price, coupon_code, type, status)
                    VALUES
                        (:order_id, :user_id, :product_id, :service_name, :renewal_price, :original_price, :coupon_code, 'new', 'pending')
                ");
                $insert->execute([
                    ':order_id'      => $order_ref,
                    ':user_id'       => $_SESSION['user_id'],
                    ':product_id'    => $first_product_id,
                    ':service_name'  => $service_name,
                    ':renewal_price' => $bundle_total,
                    ':original_price'=> $bundle_subtotal,
                    ':coupon_code'   => $checkout_applied_promo ? $checkout_promo_code : null,
                ]);

                $new_order_row_id = (int)$pdo->lastInsertId();

                if ($new_order_row_id <= 0) {
                    throw new RuntimeException('Insertion de la commande sans ID retourné.');
                }

                $_SESSION['current_pending_order_id'] = $new_order_row_id;

                $target_url = '/shop/order/payment-choice/?id=' . $new_order_row_id;

                error_log('[Cart] Checkout redirect → ' . $target_url . ' (order_id: ' . $order_ref . ', bundle: ' . implode(',', $bundle_slugs) . ')');

                safeRedirect($target_url);

            } catch (Throwable $e) {
                error_log('[Cart] Échec création commande: ' . $e->getMessage());
                $_SESSION['checkout_error'] = 'La création de votre commande a échoué. Veuillez réessayer ou contacter le support.';
                safeRedirect('/shop/cart/');
            }

        } catch (Throwable $e) {
            error_log('Cart checkout error: ' . $e->getMessage());
            $_SESSION['checkout_error'] = 'La finalisation de la commande a échoué. Veuillez réessayer.';
            safeRedirect('/shop/cart/');
        }
    }

    // Redirection PRG (Post/Redirect/Get) pour éviter les doubles soumissions
    safeRedirect('/shop/cart/');
}

// ═══════════════════════════════════════════
// CALCUL DES PRIX
// ═══════════════════════════════════════════
$cart = $_SESSION['cart'];
$subtotal = 0;
$item_count = 0;
foreach ($cart as $item) {
    $subtotal += (float)$item['price'] * (int)$item['quantity'];
    $item_count += (int)$item['quantity'];
}

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

// Récupérer le message flash s'il y en a un
if (!empty($_SESSION['checkout_error'])) {
    $flash_message = ['type' => 'error', 'text' => $_SESSION['checkout_error']];
    unset($_SESSION['checkout_error']);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> | OrinHeberge</title>
    <link rel="icon" type="image/png" href="/favicon.ico">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/cart.css?v=<?= file_exists($_SERVER['DOCUMENT_ROOT'] . '/assets/css/cart.css') ? filemtime($_SERVER['DOCUMENT_ROOT'] . '/assets/css/cart.css') : time() ?>">
</head>
<body class="text-gray-200 font-sans min-h-screen flex flex-col justify-between antialiased">

<?php $active_nav = 'cart'; include $_SERVER['DOCUMENT_ROOT'] . '/inc/navbar.php'; ?>

<main class="flex-grow px-4 py-10 md:px-6 lg:px-8">
    <div class="max-w-7xl mx-auto">

        <!-- ═══ EN-TÊTE ═══ -->
        <div class="mb-8 flex flex-col md:flex-row md:items-end md:justify-between gap-4 animate-in">
            <div>
                <div class="inline-flex items-center gap-2 rounded-full border border-sky-500/30 bg-sky-500/10 px-3 py-1 text-xs uppercase tracking-[0.3em] text-sky-400">
                    <i class="fas fa-shopping-cart"></i> Votre panier
                </div>
                <h1 class="mt-4 text-4xl md:text-5xl font-black tracking-tight gradient-text">Panier</h1>
                <p class="mt-3 max-w-2xl text-sm md:text-base text-gray-400">
                    Gérez vos offres sélectionnées, ajustez les quantités et passez à la validation.
                    <?php if ($item_count > 0): ?>
                        <span class="text-sky-400 font-semibold"><?= $item_count ?> article<?= $item_count > 1 ? 's' : '' ?></span>
                    <?php endif; ?>
                </p>
            </div>
            <a href="/offres/" class="inline-flex items-center justify-center gap-2 rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm font-semibold text-gray-200 transition hover:bg-white/10">
                <i class="fas fa-arrow-left"></i> Continuer les achats
            </a>
        </div>

        <!-- ═══ MESSAGE FLASH ═══ -->
        <?php if ($flash_message): ?>
            <div id="flash-message" class="flash-message flash-<?= $flash_message['type'] ?> mb-6">
                <div class="flash-icon">
                    <?php if ($flash_message['type'] === 'success'): ?>
                        <i class="fas fa-check-circle"></i>
                    <?php elseif ($flash_message['type'] === 'error'): ?>
                        <i class="fas fa-exclamation-triangle"></i>
                    <?php else: ?>
                        <i class="fas fa-info-circle"></i>
                    <?php endif; ?>
                </div>
                <span class="flash-text"><?= htmlspecialchars($flash_message['text'], ENT_QUOTES) ?></span>
                <button type="button" class="flash-close" onclick="this.parentElement.remove()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        <?php endif; ?>

        <!-- ═══ PANIER VIDE ═══ -->
        <?php if (empty($cart)): ?>
            <div class="glass rounded-3xl border border-white/10 p-10 text-center animate-in">
                <div class="mx-auto mb-4 flex h-20 w-20 items-center justify-center rounded-full bg-sky-500/10 text-4xl text-sky-400 cart-empty-icon">
                    <i class="fas fa-shopping-basket"></i>
                </div>
                <h2 class="text-2xl font-bold text-white">Votre panier est vide</h2>
                <p class="mt-3 text-sm text-gray-400 max-w-md mx-auto">
                    Ajoutez une offre depuis la page des offres pour la retrouver ici. 
                    Nos serveurs sont prêts à être déployés en quelques clics.
                </p>
                <a href="/offres/" class="mt-6 inline-flex items-center gap-2 rounded-2xl bg-sky-600 px-6 py-3.5 text-sm font-semibold text-white transition hover:bg-sky-500 shadow-lg shadow-sky-600/20">
                    <i class="fas fa-tags"></i> Découvrir les offres
                </a>
            </div>

        <?php else: ?>
            <!-- ═══ PANIER AVEC ARTICLES ═══ -->
            <div class="grid gap-6 xl:grid-cols-[1.6fr_0.8fr]">

                <!-- ─── LISTE DES PRODUITS ─── -->
                <div class="glass rounded-3xl border border-white/10 p-4 md:p-6 animate-in">
                    <form method="post" id="cart-form" class="space-y-4">
                        <input type="hidden" name="action" value="update_cart">

                        <div class="flex items-center justify-between border-b border-white/10 pb-4">
                            <div>
                                <h2 class="text-xl font-bold text-white flex items-center gap-2">
                                    <i class="fas fa-box-open text-sky-400"></i>
                                    Produits sélectionnés
                                </h2>
                                <p class="text-sm text-gray-400">Modifiez les quantités selon vos besoins.</p>
                            </div>
                            <button type="submit" class="rounded-2xl border border-white/10 bg-white/5 px-3 py-2 text-sm text-gray-300 transition hover:bg-white/10 flex items-center gap-2">
                                <i class="fas fa-save"></i>
                                <span class="hidden sm:inline">Mettre à jour</span>
                            </button>
                        </div>

                        <div class="space-y-3" id="cart-items">
                            <?php foreach ($cart as $slug => $item):
                                $line_total = (float)$item['price'] * (int)$item['quantity'];
                            ?>
                                <div class="cart-item rounded-2xl border border-white/10 bg-[#0d1321] p-4 transition-all" data-slug="<?= htmlspecialchars($slug, ENT_QUOTES) ?>">
                                    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">

                                        <!-- Info produit -->
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center gap-3">
                                                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-sky-500/20 to-indigo-500/20 text-sky-400 border border-sky-500/20 shrink-0">
                                                    <i class="fas fa-server text-lg"></i>
                                                </div>
                                                <div class="min-w-0">
                                                    <h3 class="font-semibold text-white truncate"><?= htmlspecialchars($item['name'], ENT_QUOTES) ?></h3>
                                                    <p class="text-sm text-gray-400 flex items-center gap-2">
                                                        <?= htmlspecialchars($item['period'] ?: 'Offre standard', ENT_QUOTES) ?>
                                                        <span class="text-sky-400 font-mono"><?= number_format((float)$item['price'], 2, ',', '') ?>€/u</span>
                                                    </p>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Contrôles -->
                                        <div class="flex flex-wrap items-center gap-3">

                                            <!-- Quantité -->
                                            <div class="quantity-control flex items-center rounded-2xl border border-white/10 bg-white/5 overflow-hidden">
                                                <button type="button" class="qty-btn qty-minus px-3 py-2 text-gray-300 transition hover:text-white hover:bg-white/5" aria-label="Diminuer">
                                                    <i class="fas fa-minus text-xs"></i>
                                                </button>
                                                <input type="number" 
                                                       name="items[<?= htmlspecialchars($slug, ENT_QUOTES) ?>]" 
                                                       min="0" 
                                                       max="99"
                                                       value="<?= (int)$item['quantity'] ?>" 
                                                       class="qty-input w-12 border-0 bg-transparent text-center text-sm text-white outline-none"
                                                       aria-label="Quantité">
                                                <button type="button" class="qty-btn qty-plus px-3 py-2 text-gray-300 transition hover:text-white hover:bg-white/5" aria-label="Augmenter">
                                                    <i class="fas fa-plus text-xs"></i>
                                                </button>
                                            </div>

                                            <!-- Total ligne -->
                                            <div class="min-w-[100px] text-right">
                                                <div class="text-[10px] uppercase tracking-wider text-gray-500 font-bold">Total</div>
                                                <div class="font-bold text-white text-lg line-total" data-price="<?= (float)$item['price'] ?>">
                                                    <?= number_format($line_total, 2, ',', ' ') ?> €
                                                </div>
                                            </div>

                                            <!-- Supprimer -->
                                            <form method="post" class="inline-block">
                                                <input type="hidden" name="action" value="remove_item">
                                                <input type="hidden" name="slug" value="<?= htmlspecialchars($slug, ENT_QUOTES) ?>">
                                                <button type="submit" 
                                                        class="remove-btn rounded-2xl border border-red-500/20 bg-red-500/10 px-3 py-2 text-sm text-red-400 transition hover:bg-red-500/20 hover:border-red-500/40"
                                                        onclick="return confirm('Retirer &quot;<?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?>&quot; du panier ?')">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </form>

                    <!-- Actions secondaires -->
                    <div class="mt-6 flex items-center justify-between pt-4 border-t border-white/10">
                        <form method="post">
                            <input type="hidden" name="action" value="clear_cart">
                            <button type="submit" 
                                    class="rounded-2xl border border-white/10 bg-white/5 px-4 py-2 text-sm text-gray-300 transition hover:bg-red-500/10 hover:border-red-500/20 hover:text-red-400 flex items-center gap-2"
                                    onclick="return confirm('Vider complètement le panier ?')">
                                <i class="fas fa-broom"></i> Vider le panier
                            </button>
                        </form>
                        <div class="text-xs text-gray-500 flex items-center gap-2">
                            <i class="fas fa-save text-emerald-400"></i>
                            Sauvegardé automatiquement
                        </div>
                    </div>
                </div>

                <!-- ─── RÉSUMÉ / SIDEBAR ─── -->
                <aside class="space-y-4">

                    <!-- Résumé des prix -->
                    <div class="glass rounded-3xl border border-white/10 p-6 animate-in-delay">
                        <h2 class="text-xl font-bold text-white flex items-center gap-2">
                            <i class="fas fa-receipt text-sky-400"></i> Résumé
                        </h2>

                        <div class="mt-5 space-y-3 text-sm text-gray-300">
                            <div class="flex items-center justify-between">
                                <span>Sous-total (<?= $item_count ?> article<?= $item_count > 1 ? 's' : '' ?>)</span>
                                <span id="subtotal-display"><?= number_format($subtotal, 2, ',', ' ') ?> €</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span>Livraison</span>
                                <span class="text-emerald-400 font-semibold">
                                    <i class="fas fa-check text-xs"></i> Gratuite
                                </span>
                            </div>

                            <?php if ($promo && $discount_amount > 0): ?>
                            <div class="flex items-center justify-between text-emerald-400">
                                <span>
                                    <i class="fas fa-tag text-xs"></i>
                                    Réduction <?= $applied_promo ? '(code)' : '(auto)' ?>
                                </span>
                                <span class="font-bold">-<?= number_format($discount_amount, 2, ',', ' ') ?> €</span>
                            </div>
                            <?php endif; ?>

                            <div class="border-t border-white/10 pt-3 mt-3 flex items-center justify-between text-lg font-black text-white">
                                <span>Total</span>
                                <span id="total-display" class="gradient-text text-2xl"><?= number_format($total, 2, ',', ' ') ?> €</span>
                            </div>

                            <?php if ($promo && $discount_amount > 0): ?>
                            <div class="text-center text-xs text-gray-500 pt-1">
                                Vous économisez <strong class="text-emerald-400"><?= number_format($discount_amount, 2, ',', ' ') ?> €</strong>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Code promo -->
                        <div class="mt-6 space-y-3">
                            <label for="promo_code" class="text-sm font-medium text-gray-300 flex items-center gap-2">
                                <i class="fas fa-ticket-alt text-sky-400"></i> Code promo
                            </label>

                            <?php if ($applied_promo): ?>
                            <div class="flex items-center justify-between gap-2 rounded-2xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3">
                                <span class="text-sm text-emerald-300 flex items-center gap-2">
                                    <i class="fas fa-check-circle text-emerald-400"></i>
                                    <strong class="text-white"><?= htmlspecialchars($applied_promo['code'], ENT_QUOTES) ?></strong>
                                    <span class="text-xs text-emerald-400">(<?= htmlspecialchars($prices['label'], ENT_QUOTES) ?>)</span>
                                </span>
                                <form method="post">
                                    <input type="hidden" name="action" value="clear_promo">
                                    <button type="submit" class="text-xs text-red-400 transition hover:text-red-300 flex items-center gap-1">
                                        <i class="fas fa-times"></i> Retirer
                                    </button>
                                </form>
                            </div>
                            <?php else: ?>
                            <form method="post" class="flex gap-2">
                                <input type="hidden" name="action" value="apply_promo">
                                <input id="promo_code" 
                                       name="promo_code" 
                                       value="<?= htmlspecialchars($promo_code, ENT_QUOTES) ?>" 
                                       placeholder="Ex. BIENVENUE10" 
                                       class="flex-1 rounded-2xl border border-white/10 bg-[#0d1321] px-4 py-2.5 text-sm text-white placeholder-gray-500 outline-none focus:border-sky-500/50 transition">
                                <button type="submit" class="rounded-2xl border border-sky-500/20 bg-sky-500/10 px-4 py-2.5 text-sm font-semibold text-sky-300 transition hover:bg-sky-500/20">
                                    Appliquer
                                </button>
                            </form>
                            <?php endif; ?>

                            <?php if ($promo_error): ?>
                                <p class="text-sm text-red-400 flex items-center gap-2">
                                    <i class="fas fa-exclamation-circle"></i>
                                    <?= htmlspecialchars($promo_error, ENT_QUOTES) ?>
                                </p>
                            <?php elseif ($promo && !$applied_promo): ?>
                                <p class="text-xs text-emerald-400 flex items-center gap-2">
                                    <i class="fas fa-sparkles"></i>
                                    Promo automatique : <?= htmlspecialchars($promo['name'], ENT_QUOTES) ?>
                                </p>
                            <?php endif; ?>
                        </div>

                        <!-- Info sécurité -->
                        <div class="mt-6 rounded-2xl border border-sky-500/20 bg-sky-500/10 p-4 text-sm text-sky-300 flex items-start gap-2">
                            <i class="fas fa-shield-alt mt-0.5"></i>
                            <span>Paiement 100% sécurisé via Stripe. Vos données bancaires ne transitent jamais par nos serveurs.</span>
                        </div>

                        <!-- Bouton checkout -->
                        <form method="post" class="mt-6">
                            <input type="hidden" name="action" value="checkout">
                            <button type="submit" class="checkout-btn inline-flex w-full items-center justify-center gap-2 rounded-2xl bg-gradient-to-r from-sky-600 to-indigo-600 px-4 py-4 text-base font-bold text-white transition hover:from-sky-500 hover:to-indigo-500 shadow-lg shadow-sky-600/20 hover:shadow-sky-600/40">
                                <i class="fas fa-lock text-sm"></i>
                                <span>Finaliser ma commande</span>
                                <span class="text-sky-200">—</span>
                                <span><?= number_format($total, 2, ',', ' ') ?> €</span>
                            </button>
                        </form>

                        <!-- Moyens de paiement -->
                        <div class="mt-4 flex items-center justify-center gap-4 text-gray-500 text-lg">
                            <i class="fab fa-cc-visa" title="Visa"></i>
                            <i class="fab fa-cc-mastercard" title="Mastercard"></i>
                            <i class="fab fa-cc-amex" title="American Express"></i>
                            <i class="fab fa-apple-pay" title="Apple Pay"></i>
                            <i class="fab fa-google-pay" title="Google Pay"></i>
                            <i class="fab fa-paypal" title="PayPal"></i>
                        </div>
                    </div>

                    <!-- Garanties -->
                    <div class="glass rounded-3xl border border-white/10 p-5 animate-in-delay2">
                        <h3 class="text-sm font-bold text-white mb-3 flex items-center gap-2">
                            <i class="fas fa-star text-amber-400"></i> Nos garanties
                        </h3>
                        <div class="space-y-2.5 text-xs text-gray-400">
                            <div class="flex items-start gap-2">
                                <i class="fas fa-bolt text-sky-400 mt-0.5 w-4 text-center"></i>
                                <span>Déploiement instantané après paiement</span>
                            </div>
                            <div class="flex items-start gap-2">
                                <i class="fas fa-headset text-sky-400 mt-0.5 w-4 text-center"></i>
                                <span>Support technique 7j/7</span>
                            </div>
                            <div class="flex items-start gap-2">
                                <i class="fas fa-undo text-sky-400 mt-0.5 w-4 text-center"></i>
                                <span>Satisfait ou remboursé sous 48h</span>
                            </div>
                            <div class="flex items-start gap-2">
                                <i class="fas fa-server text-sky-400 mt-0.5 w-4 text-center"></i>
                                <span>Infrastructure européenne haute dispo</span>
                            </div>
                        </div>
                    </div>

                </aside>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/inc/footer.php'; ?>

<!-- Données pour JS -->
<script>
    window.CART_CONFIG = {
        itemCount: <?= $item_count ?>,
        subtotal: <?= $subtotal ?>,
        total: <?= $total ?>,
        discount: <?= $discount_amount ?>,
        currency: '€'
    };
</script>
<script src="/assets/js/cart.js?v=<?= file_exists($_SERVER['DOCUMENT_ROOT'] . '/assets/js/cart.js') ? filemtime($_SERVER['DOCUMENT_ROOT'] . '/assets/js/cart.js') : time() ?>"></script>
<script src="/inc/navbar.js"></script>

</body>
</html>
<?php
/**
 * OrinHeberge — Offres Minecraft
 */
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/lang.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/db.php';

$active_nav = 'offers';
$current_game = 'minecraft';
$is_logged_in = isset($_SESSION['user_id']);

// Récupérer les infos de la catégorie Minecraft depuis la BDD
try {
    $stmt = $pdo->prepare('
        SELECT name_key, description_key, icon, image_url 
        FROM categories_products 
        WHERE category_slug = ? AND is_active = 1 
        LIMIT 1
    ');
    $stmt->execute(['minecraft']);
    $category = $stmt->fetch();
    
    if (!$category) {
        die("Catégorie Minecraft non trouvée ou inactive.");
    }
    
    // Récupérer les offres Minecraft
    $offersStmt = $pdo->prepare('
        SELECT p.*, cp.category_slug, cp.icon AS cat_icon, cp.image_url AS cat_image
        FROM categories_products cp
        JOIN products p ON p.id = cp.product_id
        WHERE cp.category_slug = ? AND cp.is_active = 1 AND p.is_active = 1
        ORDER BY p.sort_order, p.id
    ');
    $offersStmt->execute(['minecraft']);
    $offers = $offersStmt->fetchAll();
    
} catch (PDOException $e) {
    error_log('Minecraft offers error: ' . $e->getMessage());
    die("Erreur de chargement des offres.");
}

// Fonction pour déterminer le tier
function getTierClass($slug) {
    if (str_contains($slug, 'free')) return 'free';
    if (str_contains($slug, 'basic')) return 'basic';
    if (str_contains($slug, 'medium')) return 'medium';
    if (str_contains($slug, 'mythic')) return 'mythic';
    return 'premium';
}

function tierStyle($tier) {
    $styles = [
        'free'    => ['bg'=>'bg-green-500/20', 'text'=>'text-green-400', 'border'=>'border-green-500/30', 'btn'=>'bg-green-500 hover:bg-green-400'],
        'basic'   => ['bg'=>'bg-blue-500/20',  'text'=>'text-blue-400',  'border'=>'border-blue-500/30',  'btn'=>'bg-blue-500 hover:bg-blue-400'],
        'medium'  => ['bg'=>'bg-purple-500/20','text'=>'text-purple-400','border'=>'border-purple-500/30','btn'=>'bg-purple-500 hover:bg-purple-400'],
        'premium' => ['bg'=>'bg-yellow-500/20','text'=>'text-yellow-400','border'=>'border-yellow-500/30','btn'=>'bg-yellow-500 hover:bg-yellow-400'],
        'mythic'  => ['bg'=>'bg-rose-500/20',  'text'=>'text-rose-400',  'border'=>'border-rose-500/30',  'btn'=>'bg-rose-500 hover:bg-rose-400'],
    ];
    return $styles[$tier] ?? $styles['basic'];
}

// Grouper les offres par tier
$groupedOffers = [];
foreach ($offers as $offer) {
    $tier = getTierClass($offer['slug']);
    $groupedOffers[$tier][] = $offer;
}
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t($category['name_key']); ?> - OrinHeberge</title>
    <link rel="icon" type="image/png" href="/favicon.ico">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #0b0f19; }
        .glass { background: rgba(255, 255, 255, .04); backdrop-filter: blur(14px); border: 1px solid rgba(255, 255, 255, .08); }
        .gradient-text { background: linear-gradient(90deg, #22c55e, #16a34a); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .card-hover { transition: transform .3s, box-shadow .3s; }
        .card-hover:hover { transform: translateY(-6px); box-shadow: 0 20px 40px rgba(0, 0, 0, .3); }
    </style>
</head>
<body class="text-gray-200 font-sans min-h-screen flex flex-col">

<?php include $_SERVER['DOCUMENT_ROOT'] . '/inc/navbar.php'; ?>

<main class="flex-grow">
    <!-- HERO -->
    <header class="text-center py-20 px-6 relative overflow-hidden">
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] bg-green-500/10 rounded-full blur-[150px] pointer-events-none"></div>
        <div class="relative z-10">
            <div class="inline-flex items-center gap-2 bg-green-500/10 text-green-400 border border-green-500/20 px-4 py-1.5 rounded-full text-xs font-semibold mb-5">
                <i class="<?php echo htmlspecialchars($category['icon'] ?? 'fas fa-cube'); ?>"></i>
                <?php echo t('categories.gaming.title'); ?>
            </div>
            <h1 class="text-5xl md:text-7xl font-black tracking-tight leading-none gradient-text mb-4">
                <?php echo t($category['name_key']); ?>
            </h1>
            <p class="text-gray-400 max-w-2xl mx-auto text-lg">
                <?php echo t($category['description_key'] ?? 'Hébergement Minecraft haute performance'); ?>
            </p>
            <div class="mt-8">
                <a href="/offres/" class="inline-flex items-center gap-2 text-gray-400 hover:text-white transition">
                    <i class="fas fa-arrow-left"></i> <?php echo t('back_to_offers', 'Retour aux offres'); ?>
                </a>
            </div>
        </div>
    </header>

    <!-- OFFRES PAR TIER -->
    <section class="max-w-7xl mx-auto px-6 py-12">
        <?php foreach ($groupedOffers as $tier => $tierOffers): 
            $style = tierStyle($tier);
        ?>
        <div class="mb-16">
            <h2 class="text-3xl font-bold <?php echo $style['text']; ?> mb-8 uppercase tracking-wide flex items-center gap-3">
                <i class="fas fa-layer-group"></i> <?php echo t('tier.' . $tier . '.title', ucfirst($tier)); ?>
            </h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($tierOffers as $offer): 
                    $price = $offer['type'] === 'free' ? '0€' : number_format($offer['price'], 2, ',', '') . '€';
                    $btnText = $is_logged_in ? ($offer['type'] === 'free' ? 'Déployer' : 'Commander') : 'Se connecter';
                ?>
                <div class="glass rounded-2xl border <?php echo $style['border']; ?> overflow-hidden card-hover">
                    <div class="h-40 bg-cover bg-center relative" style="background-image: url('<?php echo htmlspecialchars($offer['cat_image'] ?? $category['image_url']); ?>')">
                        <div class="absolute inset-0 bg-gradient-to-t from-[#0b0f19] to-transparent"></div>
                        <div class="absolute top-4 left-4">
                            <span class="<?php echo $style['bg']; ?> <?php echo $style['text']; ?> px-3 py-1 rounded-full text-xs font-bold border <?php echo $style['border']; ?>">
                                <?php echo t('tier.' . $tier . '.label', ucfirst($tier)); ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="p-6">
                        <h3 class="text-xl font-bold text-white mb-2"><?php echo htmlspecialchars($offer['name']); ?></h3>
                        <p class="text-gray-400 text-sm mb-4"><?php echo htmlspecialchars($offer['description'] ?? ''); ?></p>
                        
                        <div class="flex items-baseline mb-4">
                            <span class="text-3xl font-black text-white"><?php echo $price; ?></span>
                            <span class="text-gray-500 text-sm ml-2">/mois</span>
                        </div>
                        
                        <ul class="space-y-2 text-sm text-gray-300 mb-6">
                            <li class="flex items-center gap-2">
                                <i class="fas fa-memory <?php echo $style['text']; ?>"></i>
                                <?php echo $offer['ram'] >= 1024 ? number_format($offer['ram']/1024, 0) . ' GB RAM' : $offer['ram'] . ' MB RAM'; ?>
                            </li>
                            <li class="flex items-center gap-2">
                                <i class="fas fa-hard-drive <?php echo $style['text']; ?>"></i>
                                <?php echo $offer['disk'] >= 1024 ? number_format($offer['disk']/1024, 0) . ' GB SSD' : $offer['disk'] . ' MB SSD'; ?>
                            </li>
                            <li class="flex items-center gap-2">
                                <i class="fas fa-microchip <?php echo $style['text']; ?>"></i>
                                <?php echo $offer['cpu']; ?>% CPU
                            </li>
                        </ul>
                        
                        <?php if ($is_logged_in): ?>
                        <form method="post" action="/shop/cart/">
                            <input type="hidden" name="action" value="add_item">
                            <input type="hidden" name="slug" value="<?php echo htmlspecialchars($offer['slug']); ?>">
                            <input type="hidden" name="name" value="<?php echo htmlspecialchars($offer['name']); ?>">
                            <input type="hidden" name="price" value="<?php echo $offer['price']; ?>">
                            <button type="submit" class="w-full <?php echo $style['btn']; ?> text-slate-950 font-bold py-3 rounded-xl transition">
                                <?php echo $btnText; ?>
                            </button>
                        </form>
                        <?php else: ?>
                        <a href="/login/" class="block w-full <?php echo $style['btn']; ?> text-slate-950 font-bold py-3 rounded-xl text-center transition">
                            <?php echo $btnText; ?>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </section>
</main>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/inc/footer.php'; ?>
</body>
</html>
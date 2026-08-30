<?php
/**
 * OrinHeberge — Gestionnaire de Catégories de Jeux Professionnel
 * Séparation claire des catégories avec navigation améliorée
 */

session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/lang.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/db.php';

$active_nav = 'offers';
$page_title = 'Catégories de Services';

// Configuration des catégories principales
$main_categories = [
    'gaming' => [
        'title_key' => 'categories.gaming.title',
        'description_key' => 'categories.gaming.description',
        'icon' => 'fas fa-gamepad',
        'color' => 'from-blue-500 to-purple-600',
        'subcategories' => ['minecraft', 'fivem', 'terraria', 'hytale']
    ],
    'web' => [
        'title_key' => 'categories.web.title', 
        'description_key' => 'categories.web.description',
        'icon' => 'fas fa-code',
        'color' => 'from-green-500 to-blue-500',
        'subcategories' => ['php', 'nodejs', 'python', 'java']
    ],
    'database' => [
        'title_key' => 'categories.database.title',
        'description_key' => 'categories.database.description', 
        'icon' => 'fas fa-database',
        'color' => 'from-yellow-500 to-orange-500',
        'subcategories' => ['mysql', 'mongodb', 'postgresql']
    ],
    'storage' => [
        'title_key' => 'categories.storage.title',
        'description_key' => 'categories.storage.description',
        'icon' => 'fas fa-cloud',
        'color' => 'from-purple-500 to-pink-500', 
        'subcategories' => ['files', 'backup', 'cdn']
    ]
];

// Récupération des catégories actives depuis la base de données
try {
    $active_categories = [];
    $stmt = $pdo->query("
        SELECT DISTINCT category_slug, name_key, icon, image_url, description_key,
               COUNT(cp.product_id) as product_count
        FROM categories_products cp
        JOIN products p ON p.id = cp.product_id  
        WHERE cp.is_active = 1 AND p.is_active = 1
        GROUP BY category_slug, name_key, icon, image_url, description_key
        ORDER BY cp.sort_order ASC
    ");
    
    while ($cat = $stmt->fetch()) {
        $active_categories[$cat['category_slug']] = $cat;
    }
} catch (Exception $e) {
    error_log('Categories query error: ' . $e->getMessage());
    $active_categories = [];
}

// Fonction pour obtenir la couleur d'une catégorie
function getCategoryColor($slug) {
    $colors = [
        'minecraft' => 'from-green-600 to-green-700',
        'fivem' => 'from-blue-600 to-blue-700', 
        'php' => 'from-purple-600 to-purple-700',
        'nodejs' => 'from-green-500 to-green-600',
        'python' => 'from-yellow-500 to-yellow-600',
        'java' => 'from-red-500 to-red-600',
        'terraria' => 'from-blue-500 to-cyan-500',
        'hytale' => 'from-orange-500 to-red-500'
    ];
    return $colors[$slug] ?? 'from-gray-500 to-gray-600';
}
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('categories.page_title'); ?> — OrinHeberge</title>
    <link rel="icon" type="image/png" href="/favicon.ico">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); }
        .category-card {
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }
        .category-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }
        .gradient-text {
            background: linear-gradient(135deg, #38bdf8, #a855f7);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
    </style>
</head>
<body class="min-h-screen text-white">
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/inc/navbar.php'; ?>

    <!-- En-tête principal -->
    <div class="bg-gradient-to-r from-slate-900 via-slate-800 to-slate-900 py-20">
        <div class="max-w-7xl mx-auto px-6 text-center">
            <h1 class="text-5xl font-black mb-6">
                <?php echo t('categories.main_title'); ?>
                <span class="gradient-text"><?php echo t('categories.main_subtitle'); ?></span>
            </h1>
            <p class="text-xl text-slate-300 max-w-3xl mx-auto leading-relaxed">
                <?php echo t('categories.main_description'); ?>
            </p>
        </div>
    </div>

    <!-- Navigation par types -->
    <div class="max-w-7xl mx-auto px-6 py-16">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8 mb-16">
            <?php foreach ($main_categories as $key => $category): ?>
            <div class="category-card bg-white/5 border border-white/10 rounded-2xl p-6 hover:bg-white/10">
                <div class="w-16 h-16 rounded-xl bg-gradient-to-r <?php echo $category['color']; ?> flex items-center justify-center mb-4">
                    <i class="<?php echo $category['icon']; ?> text-2xl text-white"></i>
                </div>
                <h3 class="text-xl font-bold mb-2"><?php echo t($category['title_key']); ?></h3>
                <p class="text-slate-400 text-sm mb-4"><?php echo t($category['description_key']); ?></p>
                <a href="#<?php echo $key; ?>" class="inline-flex items-center gap-2 text-sm font-semibold text-blue-400 hover:text-blue-300">
                    <?php echo t('categories.explore'); ?>
                    <i class="fas fa-arrow-right"></i>
                </a>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Catégories de Gaming -->
        <section id="gaming" class="mb-16">
            <div class="flex items-center gap-4 mb-8">
                <div class="w-12 h-12 rounded-lg bg-gradient-to-r from-blue-500 to-purple-600 flex items-center justify-center">
                    <i class="fas fa-gamepad text-xl text-white"></i>
                </div>
                <div>
                    <h2 class="text-3xl font-bold"><?php echo t('categories.gaming.title'); ?></h2>
                    <p class="text-slate-400"><?php echo t('categories.gaming.subtitle'); ?></p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php 
                $gaming_categories = ['minecraft', 'fivem', 'terraria', 'hytale'];
                foreach ($gaming_categories as $slug):
                    if (!isset($active_categories[$slug])) continue;
                    $cat = $active_categories[$slug];
                ?>
                <a href="/offres/?category=<?php echo $slug; ?>" class="category-card block bg-slate-800/50 border border-slate-700 rounded-xl p-6 hover:border-blue-500/50">
                    <div class="flex items-start gap-4">
                        <?php if (!empty($cat['image_url'])): ?>
                        <img src="<?php echo htmlspecialchars($cat['image_url']); ?>" 
                             alt="<?php echo htmlspecialchars($cat['name_key']); ?>" 
                             class="w-16 h-16 rounded-lg object-cover flex-shrink-0">
                        <?php else: ?>
                        <div class="w-16 h-16 rounded-lg bg-gradient-to-r <?php echo getCategoryColor($slug); ?> flex items-center justify-center flex-shrink-0">
                            <i class="<?php echo htmlspecialchars($cat['icon'] ?: 'fas fa-server'); ?> text-xl text-white"></i>
                        </div>
                        <?php endif; ?>
                        
                        <div class="flex-1">
                            <h3 class="font-bold text-lg mb-1"><?php echo t($cat['name_key']); ?></h3>
                            <p class="text-sm text-slate-400 mb-3"><?php echo t($cat['description_key'] ?? 'categories.default_description'); ?></p>
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-blue-400 font-semibold">
                                    <?php echo $cat['product_count']; ?> <?php echo t('categories.products_available'); ?>
                                </span>
                                <i class="fas fa-arrow-right text-slate-500"></i>
                            </div>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Catégories Web & Applications -->
        <section id="web" class="mb-16">
            <div class="flex items-center gap-4 mb-8">
                <div class="w-12 h-12 rounded-lg bg-gradient-to-r from-green-500 to-blue-500 flex items-center justify-center">
                    <i class="fas fa-code text-xl text-white"></i>
                </div>
                <div>
                    <h2 class="text-3xl font-bold"><?php echo t('categories.web.title'); ?></h2>
                    <p class="text-slate-400"><?php echo t('categories.web.subtitle'); ?></p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <?php 
                $web_categories = ['php', 'nodejs', 'python', 'java'];
                foreach ($web_categories as $slug):
                    if (!isset($active_categories[$slug])) continue;
                    $cat = $active_categories[$slug];
                ?>
                <a href="/offres/?category=<?php echo $slug; ?>" class="category-card block bg-slate-800/50 border border-slate-700 rounded-xl p-6 hover:border-green-500/50">
                    <div class="text-center">
                        <?php if (!empty($cat['image_url'])): ?>
                        <img src="<?php echo htmlspecialchars($cat['image_url']); ?>" 
                             alt="<?php echo htmlspecialchars($cat['name_key']); ?>" 
                             class="w-12 h-12 rounded-lg object-cover mx-auto mb-4">
                        <?php else: ?>
                        <div class="w-12 h-12 rounded-lg bg-gradient-to-r <?php echo getCategoryColor($slug); ?> flex items-center justify-center mx-auto mb-4">
                            <i class="<?php echo htmlspecialchars($cat['icon'] ?: 'fas fa-server'); ?> text-xl text-white"></i>
                        </div>
                        <?php endif; ?>
                        
                        <h3 class="font-bold text-lg mb-2"><?php echo t($cat['name_key']); ?></h3>
                        <p class="text-xs text-slate-500 mb-4"><?php echo $cat['product_count']; ?> produits</p>
                        <div class="inline-flex items-center gap-2 text-sm text-green-400 font-semibold">
                            <?php echo t('categories.discover'); ?>
                            <i class="fas fa-external-link-alt text-xs"></i>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Services Complémentaires -->
        <section class="bg-slate-800/30 border border-slate-700 rounded-2xl p-8">
            <div class="text-center mb-8">
                <h2 class="text-3xl font-bold mb-4"><?php echo t('categories.additional_services'); ?></h2>
                <p class="text-slate-400 max-w-2xl mx-auto"><?php echo t('categories.additional_description'); ?></p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="text-center p-6">
                    <div class="w-16 h-16 rounded-full bg-gradient-to-r from-yellow-500 to-orange-500 flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-database text-2xl text-white"></i>
                    </div>
                    <h3 class="font-bold text-lg mb-2"><?php echo t('categories.databases'); ?></h3>
                    <p class="text-sm text-slate-400"><?php echo t('categories.databases_desc'); ?></p>
                </div>

                <div class="text-center p-6">
                    <div class="w-16 h-16 rounded-full bg-gradient-to-r from-purple-500 to-pink-500 flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-cloud text-2xl text-white"></i>
                    </div>
                    <h3 class="font-bold text-lg mb-2"><?php echo t('categories.storage'); ?></h3>
                    <p class="text-sm text-slate-400"><?php echo t('categories.storage_desc'); ?></p>
                </div>

                <div class="text-center p-6">
                    <div class="w-16 h-16 rounded-full bg-gradient-to-r from-blue-500 to-cyan-500 flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-shield-alt text-2xl text-white"></i>
                    </div>
                    <h3 class="font-bold text-lg mb-2"><?php echo t('categories.security'); ?></h3>
                    <p class="text-sm text-slate-400"><?php echo t('categories.security_desc'); ?></p>
                </div>
            </div>
        </section>
    </div>

    <?php include $_SERVER['DOCUMENT_ROOT'] . '/inc/footer.php'; ?>
</body>
</html>
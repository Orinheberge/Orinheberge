-- ====================================================================
-- 1. CRÉATION DE LA TABLE UNIQUE CATEGORIES_PRODUCTS
-- ====================================================================
CREATE TABLE IF NOT EXISTS `categories_products` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT UNSIGNED NULL COMMENT 'ID du produit lié (Ajusté en INT UNSIGNED pour correspondre aux standards)',
  `category_slug` VARCHAR(50) NOT NULL COMMENT 'Slug de la catégorie (ex: minecraft, fivem)',
  `name_key` VARCHAR(100) NOT NULL COMMENT 'Clé de traduction pour le menu/titre (ex: cat.minecraft.name)',
  `icon` VARCHAR(100) NOT NULL DEFAULT 'fas fa-server' COMMENT 'Icône FontAwesome',
  `image_url` VARCHAR(255) NULL COMMENT 'Image d illustration de la catégorie',
  `sort_order` INT NOT NULL DEFAULT 0 COMMENT 'Ordre d affichage dans les onglets',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_cp_product` 
    FOREIGN KEY (`product_id`) 
    REFERENCES `products` (`id`) 
    ON DELETE CASCADE 
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ====================================================================
-- 2. INSERTION DES CATÉGORIES DE BASE (SANS PRODUITS INITIALEMENT LIES)
-- ====================================================================
INSERT INTO `categories_products` (`product_id`, `category_slug`, `name_key`, `icon`, `image_url`, `sort_order`) VALUES
(NULL, 'minecraft', 'cat.minecraft.name', 'fas fa-cube', 'https://www.4netplayers.com/images/minecraft/blog/teaser-image.jpg', 1),
(NULL, 'fivem', 'cat.fivem.name', 'fas fa-car', 'https://images.unsplash.com/photo-1542751371-adc38448a05e?q=80&w=600&auto=format&fit=crop', 2),
(NULL, 'hytale', 'cat.hytale.name', 'fas fa-gamepad', 'https://cdn.minestrator.com/blog/articles/155/thumbnail.webp', 3),
(NULL, 'php', 'cat.php.name', 'fas fa-code', 'https://images.unsplash.com/photo-1555066931-4365d14bab8c?q=80&w=600&auto=format&fit=crop', 4),
(NULL, 'python', 'cat.python.name', 'fab fa-python', 'https://images.unsplash.com/photo-1542831371-29b0f74f9713?q=80&w=600&auto=format&fit=crop', 5),
(NULL, 'nodejs', 'cat.nodejs.name', 'fab fa-node-js', 'https://images.unsplash.com/photo-1607799279861-4dd421887fb3?q=80&w=600&auto=format&fit=crop', 6),
(NULL, 'java', 'cat.java.name', 'fab fa-java', 'https://images.unsplash.com/photo-1607799279861-4dd421887fb3?q=80&w=600&auto=format&fit=crop', 7);

-- ====================================================================
-- 3. ASSOCIATIONS AUTOMATIQUES SUR LES PRODUITS EXISTANTS
-- ====================================================================
INSERT INTO `categories_products` (`product_id`, `category_slug`, `name_key`, `icon`, `image_url`, `sort_order`) 
SELECT 
    p.`id`, 
    LOWER(SUBSTRING_INDEX(p.`slug`, '-', 1)) AS cat_slug,
    CONCAT('cat.', LOWER(SUBSTRING_INDEX(p.`slug`, '-', 1)), '.name'),
    cp.`icon`,
    cp.`image_url`,
    cp.`sort_order`
FROM `products` p
JOIN `categories_products` cp ON cp.`category_slug` = LOWER(SUBSTRING_INDEX(p.`slug`, '-', 1))
WHERE cp.`product_id` IS NULL;
INSERT INTO `categories_products` ADD `is_active` TINYINT(1) NOT NULL DEFAULT 1 AFTER `sort_order`;

-- ====================================================================
-- 1. CRÉATION DE LA TABLE DES CATÉGORIES (GÉRABLE DEPUIS L'ADMIN)
-- ====================================================================
CREATE TABLE IF NOT EXISTS `categories` (
  `slug` VARCHAR(50) NOT NULL PRIMARY KEY COMMENT 'Identifiant unique (ex: minecraft, fivem)',
  `name_key` VARCHAR(100) NOT NULL COMMENT 'Clé de traduction pour le nom (ex: cat.minecraft.name)',
  `icon` VARCHAR(100) NOT NULL DEFAULT 'fas fa-server' COMMENT 'Classe FontAwesome (ex: fas fa-cube)',
  `image_url` VARCHAR(255) NULL COMMENT 'Lien de l image d illustration',
  `sort_order` INT NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ====================================================================
-- 2. CRÉATION DE LA TABLE DE LIAISON (MANY-TO-MANY)
-- ====================================================================
CREATE TABLE IF NOT EXISTS `categories_products` (
  `product_id` INT NOT NULL,
  `category_slug` VARCHAR(50) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`product_id`, `category_slug`),
  CONSTRAINT `fk_cp_product` 
    FOREIGN KEY (`product_id`) 
    REFERENCES `products` (`id`) 
    ON DELETE CASCADE 
    ON UPDATE CASCADE,
  CONSTRAINT `fk_cp_category` 
    FOREIGN KEY (`category_slug`) 
    REFERENCES `categories` (`slug`) 
    ON DELETE CASCADE 
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ====================================================================
-- 3. INSERTIION DES CATÉGORIES PAR DÉFAUT
-- ====================================================================
INSERT INTO `categories` (`slug`, `name_key`, `icon`, `image_url`, `sort_order`) VALUES
('minecraft', 'cat.minecraft.name', 'fas fa-cube', 'https://www.4netplayers.com/images/minecraft/blog/teaser-image.jpg', 1),
('fivem', 'cat.fivem.name', 'fas fa-car', 'https://images.unsplash.com/photo-1542751371-adc38448a05e?q=80&w=600&auto=format&fit=crop', 2),
('hytale', 'cat.hytale.name', 'fas fa-gamepad', 'https://cdn.minestrator.com/blog/articles/155/thumbnail.webp', 3),
('php', 'cat.php.name', 'fas fa-code', 'https://images.unsplash.com/photo-1555066931-4365d14bab8c?q=80&w=600&auto=format&fit=crop', 4),
('python', 'cat.python.name', 'fab fa-python', 'https://images.unsplash.com/photo-1542831371-29b0f74f9713?q=80&w=600&auto=format&fit=crop', 5),
('nodejs', 'cat.nodejs.name', 'fab fa-node-js', 'https://images.unsplash.com/photo-1607799279861-4dd421887fb3?q=80&w=600&auto=format&fit=crop', 6),
('java', 'cat.java.name', 'fab fa-java', 'https://images.unsplash.com/photo-1607799279861-4dd421887fb3?q=80&w=600&auto=format&fit=crop', 7);

-- ====================================================================
-- 4. ASSOCIATIONS AUTOMATIQUES SUR LES PRODUITS EXISTANTS
-- ====================================================================
INSERT INTO `categories_products` (`product_id`, `category_slug`) 
SELECT `id`, LOWER(SUBSTRING_INDEX(`slug`, '-', 1)) FROM `products`;
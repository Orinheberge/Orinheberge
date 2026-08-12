-- Migration : Historique des upgrades
CREATE TABLE IF NOT EXISTS `server_upgrades` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `order_uuid` VARCHAR(64) NOT NULL,
    `from_product_id` INT UNSIGNED NOT NULL,
    `to_product_id` INT UNSIGNED NOT NULL,
    `old_price` DECIMAL(10,2) NOT NULL,
    `new_price` DECIMAL(10,2) NOT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_user` (`user_id`),
    INDEX `idx_order` (`order_uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS pending_upgrades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pending_uuid VARCHAR(32) UNIQUE,
    user_id INT NOT NULL,
    order_uuid VARCHAR(64) NOT NULL,
    from_product_id INT,
    to_product_id INT,
    old_price DECIMAL(10,2),
    new_price DECIMAL(10,2),
    diff_amount DECIMAL(10,2),
    stripe_session_id VARCHAR(255),
    status ENUM('pending','completed','failed') DEFAULT 'pending',
    created_at DATETIME,
    completed_at DATETIME NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY idx_session (stripe_session_id)
);

-- ═══════════════════════════════════════════════════════════
-- Migration 024 : Ajout de updated_at à la table orders
-- ═══════════════════════════════════════════════════════════

-- 1. Ajouter la colonne updated_at (évite erreur si déjà existante)
ALTER TABLE `orders`
ADD COLUMN IF NOT EXISTS `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP 
    ON UPDATE CURRENT_TIMESTAMP 
    AFTER `created_at`;

-- 2. Initialiser updated_at avec created_at pour les enregistrements existants
UPDATE `orders` 
SET `updated_at` = `created_at` 
WHERE `updated_at` IS NULL;

-- 3. Ajouter les index (ignore si existent déjà)
ALTER TABLE `orders`
ADD INDEX `idx_updated_at` (`updated_at`);

ALTER TABLE `orders`
ADD INDEX `idx_user_updated` (`user_id`, `updated_at`);
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
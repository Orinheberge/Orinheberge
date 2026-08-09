-- Table chat_messages
CREATE TABLE IF NOT EXISTS `chat_messages` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `channel` VARCHAR(50) NOT NULL DEFAULT 'general',
    `message` TEXT NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_channel` (`channel`),
    INDEX `idx_created_at` (`created_at`),
    INDEX `idx_user` (`user_id`),
    CONSTRAINT `fk_chat_user` FOREIGN KEY (`user_id`) 
        REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table remember_tokens
CREATE TABLE IF NOT EXISTS `remember_tokens` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `token` VARCHAR(64) NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE INDEX `idx_token` (`token`),
    INDEX `idx_expires` (`expires_at`),
    INDEX `idx_user` (`user_id`),
    CONSTRAINT `fk_remember_user` FOREIGN KEY (`user_id`) 
        REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ajouter colonnes OAuth si pas existantes
ALTER TABLE `users`
ADD COLUMN IF NOT EXISTS `oauth_provider` ENUM('local','discord','google') DEFAULT 'local' AFTER `avatar`,
ADD COLUMN IF NOT EXISTS `oauth_id` VARCHAR(255) DEFAULT NULL AFTER `oauth_provider`,
ADD COLUMN IF NOT EXISTS `oauth_email` VARCHAR(255) DEFAULT NULL AFTER `oauth_id`,
ADD COLUMN IF NOT EXISTS `oauth_avatar` VARCHAR(512) DEFAULT NULL AFTER `oauth_email`,
ADD COLUMN IF NOT EXISTS `oauth_data` JSON DEFAULT NULL AFTER `oauth_avatar`,
ADD COLUMN IF NOT EXISTS `last_login` DATETIME DEFAULT NULL AFTER `oauth_data`,
ADD COLUMN IF NOT EXISTS `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP AFTER `last_login`,
ADD UNIQUE INDEX `idx_oauth` (`oauth_provider`, `oauth_id`);

-- Rendre password nullable pour OAuth
ALTER TABLE `users` MODIFY `password` VARCHAR(255) DEFAULT NULL;
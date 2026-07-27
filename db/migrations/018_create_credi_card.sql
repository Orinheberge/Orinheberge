CREATE TABLE IF NOT EXISTS `user_cards` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `card_number` TEXT NOT NULL,
  `card_holder` VARCHAR(255) NOT NULL,
  `card_expiry` VARCHAR(5) NOT NULL,
  `card_type` VARCHAR(20) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  CONSTRAINT `fk_user_cards_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `users` 
ADD COLUMN `stripe_customer_id` VARCHAR(255) DEFAULT NULL AFTER `avatar`;

CREATE TABLE IF NOT EXISTS `user_stripe_cards` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `payment_method_id` VARCHAR(255) NOT NULL,
  `card_brand` VARCHAR(50) DEFAULT NULL,
  `card_last4` VARCHAR(4) DEFAULT NULL,
  `card_exp_month` INT DEFAULT NULL,
  `card_exp_year` INT DEFAULT NULL,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_pm` (`payment_method_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `fk_user_stripe_cards_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `settings` (`key`, `value`) VALUES
('stripe_public_key', 'pk_live_51TYsYg2f2egcuUT4obSIMXsBBAVpzw0Gk18niYNgWQ5vhvV8nX5aAI6nZqEZ12RfHg1nmP2qjczVfPuX8Eb0ePzk00qDqtPro2'),
('stripe_secret_key', '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'),
('stripe_webhook_secret', 'whsec_ALogsxOzxXw6s0wEbJyf1TkslBIsGZpM')
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);

INSERT INTO extension_settings (extension_id, `key`, `value`) 
VALUES ((SELECT id FROM extensions WHERE slug='stripe'), 'webhook_secret', 'whsec_1dY9L8MHz1PVl9n4E5oon8XyQa8iTmBJ')
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);  
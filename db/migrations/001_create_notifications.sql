-- Migration: create notifications table
-- Exécuter dans phpMyAdmin ou via votre outil de migration

CREATE TABLE IF NOT EXISTS `notifications` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `message` TEXT NOT NULL,
  `link` VARCHAR(512) DEFAULT NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `type` VARCHAR(50) DEFAULT NULL,
  `meta` JSON DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX (`user_id`),
  INDEX (`is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

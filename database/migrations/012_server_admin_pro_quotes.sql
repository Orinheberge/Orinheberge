-- Migration 012 : gestion serveur admin pro + devis personnalises
-- Ajoute uniquement des colonnes/tables, sans supprimer les donnees existantes.

ALTER TABLE `orders`
  ADD COLUMN IF NOT EXISTS `deletion_reason` VARCHAR(255) DEFAULT NULL AFTER `suspension_until`,
  ADD COLUMN IF NOT EXISTS `deletion_requested_at` DATETIME DEFAULT NULL AFTER `deletion_reason`,
  ADD COLUMN IF NOT EXISTS `backup_requested_at` DATETIME DEFAULT NULL AFTER `deletion_requested_at`,
  ADD COLUMN IF NOT EXISTS `backup_uuid` VARCHAR(100) DEFAULT NULL AFTER `backup_requested_at`;

CREATE INDEX IF NOT EXISTS `idx_orders_pending_deletion` ON `orders` (`status`, `delete_after`);

CREATE TABLE IF NOT EXISTS `custom_quotes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED DEFAULT NULL,
  `email` VARCHAR(255) NOT NULL,
  `project_name` VARCHAR(150) NOT NULL,
  `egg_name` VARCHAR(120) DEFAULT NULL,
  `ram` INT UNSIGNED NOT NULL DEFAULT 2048,
  `disk` INT UNSIGNED NOT NULL DEFAULT 10000,
  `cpu` INT UNSIGNED NOT NULL DEFAULT 200,
  `players` INT UNSIGNED DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `uploaded_egg_path` VARCHAR(255) DEFAULT NULL,
  `status` VARCHAR(40) NOT NULL DEFAULT 'new',
  `admin_note` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_custom_quotes_status` (`status`, `created_at`),
  INDEX `idx_custom_quotes_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

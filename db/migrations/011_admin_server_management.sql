-- Migration 011 : champs utiles pour la gestion admin des serveurs
-- A executer apres la migration 009_server_lifecycle.sql

ALTER TABLE `orders`
  ADD COLUMN IF NOT EXISTS `admin_note` VARCHAR(500) DEFAULT NULL AFTER `created_by_admin`,
  ADD COLUMN IF NOT EXISTS `suspension_until` DATETIME DEFAULT NULL COMMENT 'Date indicative de fin de suspension temporaire' AFTER `admin_note`;

CREATE INDEX IF NOT EXISTS `idx_orders_suspension_until` ON `orders` (`status`, `suspension_until`);

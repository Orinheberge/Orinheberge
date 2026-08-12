-- Migration 009 : Cycle de vie des serveurs (suspension + suppression automatique)
-- À exécuter après toutes les migrations précédentes

-- Ajouter les colonnes de gestion du cycle de vie sur orders
ALTER TABLE `orders`
  ADD COLUMN IF NOT EXISTS `suspended_at`  DATETIME DEFAULT NULL AFTER `expires_at`,
  ADD COLUMN IF NOT EXISTS `delete_after`  DATETIME DEFAULT NULL COMMENT 'Date de suppression définitive (suspended_at + 15j)' AFTER `suspended_at`,
  ADD COLUMN IF NOT EXISTS `created_by_admin` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 si créé manuellement par un admin' AFTER `delete_after`;

-- Index pour le cron de suppression
CREATE INDEX IF NOT EXISTS `idx_orders_delete_after` ON `orders` (`status`, `delete_after`);
CREATE INDEX IF NOT EXISTS `idx_orders_suspended`    ON `orders` (`status`, `suspended_at`);

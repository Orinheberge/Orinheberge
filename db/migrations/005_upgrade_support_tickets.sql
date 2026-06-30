-- Migration: amélioration table support_tickets
ALTER TABLE `support_tickets`
  ADD COLUMN IF NOT EXISTS `priority` VARCHAR(20) NOT NULL DEFAULT 'normale' AFTER `ticket_type`,
  ADD COLUMN IF NOT EXISTS `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`;

-- Index sur updated_at pour le tri
CREATE INDEX IF NOT EXISTS idx_tickets_updated ON support_tickets (updated_at DESC);

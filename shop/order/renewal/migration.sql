-- Migration renouvellements OrinHeberge
-- À exécuter UNE SEULE FOIS dans phpMyAdmin

ALTER TABLE orders
    ADD COLUMN IF NOT EXISTS renewed_at DATETIME NULL DEFAULT NULL AFTER next_payment_date;

-- Index pour accélérer les requêtes cron
CREATE INDEX IF NOT EXISTS idx_orders_renewal
    ON orders (status, next_payment_date);

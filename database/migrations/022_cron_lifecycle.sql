-- 1. Ajouter auto_renew sur orders (par défaut activé pour les nouveaux serveurs)
ALTER TABLE orders 
  ADD COLUMN auto_renew TINYINT(1) NOT NULL DEFAULT 1 AFTER renewal_price,
  ADD COLUMN last_renewal_at DATETIME NULL AFTER next_payment_date,
  ADD COLUMN last_payment_intent_id VARCHAR(255) NULL AFTER last_renewal_at;

-- 2. Table de log des tentatives de renouvellement auto
CREATE TABLE IF NOT EXISTS renewal_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    user_id INT NOT NULL,
    status ENUM('succeeded','requires_action','failed') NOT NULL,
    reason VARCHAR(255) NULL,
    payment_intent_id VARCHAR(255) NULL,
    amount DECIMAL(10,2) NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_order (order_id),
    INDEX idx_user (user_id),
    INDEX idx_status_created (status, created_at)
);

-- 3. Table optionnelle d'historique des renouvellements
CREATE TABLE IF NOT EXISTS order_renewals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    user_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_intent_id VARCHAR(255) NULL,
    method ENUM('manual','auto','stripe_checkout') NOT NULL DEFAULT 'auto',
    created_at DATETIME NOT NULL,
    INDEX idx_order (order_id),
    INDEX idx_user (user_id)
);
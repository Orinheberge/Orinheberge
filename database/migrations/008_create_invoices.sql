-- Migration: create invoices table
-- Système de facturation — OrinHeberge

CREATE TABLE IF NOT EXISTS `invoices` (
  `id`           INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `invoice_id`   VARCHAR(20)      NOT NULL,          -- ex: INV-2026-00001
  `user_id`      INT UNSIGNED     NOT NULL,
  `order_id`     VARCHAR(100)     NOT NULL,          -- FK vers orders.order_id
  `service_name` VARCHAR(255)     NOT NULL,
  `amount`       DECIMAL(8,2)     NOT NULL DEFAULT 0.00,
  `type`         VARCHAR(30)      NOT NULL DEFAULT 'purchase', -- 'purchase' | 'renewal'
  `status`       VARCHAR(30)      NOT NULL DEFAULT 'paid',     -- 'paid' | 'pending' | 'refunded'
  `payment_method` VARCHAR(30)    DEFAULT NULL,                -- 'stripe' | 'paypal'
  `payment_ref`  VARCHAR(255)     DEFAULT NULL,                -- session_id Stripe ou PayPal
  `due_date`     DATE             DEFAULT NULL,
  `paid_at`      DATETIME         DEFAULT NULL,
  `created_at`   DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_id` (`invoice_id`),
  INDEX `user_id` (`user_id`),
  INDEX `order_id` (`order_id`),
  INDEX `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

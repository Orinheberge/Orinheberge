-- Migration: create base tables (users + orders)
-- À exécuter EN PREMIER, avant toutes les autres migrations

CREATE TABLE IF NOT EXISTS `users` (
  `id`             INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `firstname`      VARCHAR(100)     NOT NULL,
  `lastname`       VARCHAR(100)     NOT NULL,
  `pseudo`         VARCHAR(100)     DEFAULT NULL,
  `email`          VARCHAR(255)     NOT NULL,
  `password`       VARCHAR(255)     NOT NULL,
  `is_admin`       TINYINT(1)       NOT NULL DEFAULT 0,
  `avatar`         VARCHAR(512)     DEFAULT NULL,
  `panel_password` VARCHAR(255)     DEFAULT NULL,
  `created_at`     DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `orders` (
  `id`               INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `user_id`          INT UNSIGNED     NOT NULL,
  `order_id`         VARCHAR(100)     NOT NULL,
  `service_name`     VARCHAR(255)     NOT NULL,
  `ram`              INT UNSIGNED     NOT NULL DEFAULT 0,
  `disk`             INT UNSIGNED     NOT NULL DEFAULT 0,
  `cpu`              INT UNSIGNED     NOT NULL DEFAULT 0,
  `server_id`        INT UNSIGNED     DEFAULT NULL,
  `uuid`             VARCHAR(64)      DEFAULT NULL,
  `id_server_panel`  VARCHAR(100)     DEFAULT NULL,
  `status`           VARCHAR(50)      NOT NULL DEFAULT 'pending',
  `paypal_order_id`  VARCHAR(100)     DEFAULT NULL,
  `renewal_price`    DECIMAL(8,2)     NOT NULL DEFAULT 0.00,
  `amount`           DECIMAL(8,2)     DEFAULT NULL,
  `next_payment_date` DATE            DEFAULT NULL,
  `renewed_at`       DATETIME         DEFAULT NULL,
  `expires_at`       DATETIME         DEFAULT NULL,
  `created_at`       DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_id` (`order_id`),
  INDEX `user_id` (`user_id`),
  INDEX `status` (`status`),
  INDEX `idx_orders_renewal` (`status`, `next_payment_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migration 006 : Système dynamique Nodes / Eggs / Products / Extensions
-- Style Paymenter — tout gérable depuis l'admin, zéro hardcode

-- ─────────────────────────────────────────────────────────
-- 1. NODES  (nœuds Pterodactyl)
-- ─────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `nodes` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(100)  NOT NULL,
  `panel_node_id` INT UNSIGNED NOT NULL COMMENT 'ID du node sur le panel Pterodactyl',
  `location_id` INT UNSIGNED  NOT NULL DEFAULT 1,
  `fqdn`        VARCHAR(255)  DEFAULT NULL COMMENT 'FQDN ou IP du node',
  `is_active`   TINYINT(1)    NOT NULL DEFAULT 1,
  `created_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `panel_node_id` (`panel_node_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────
-- 2. EGGS  (types de serveurs / images Docker)
-- ─────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `eggs` (
  `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `name`         VARCHAR(100)  NOT NULL,
  `panel_egg_id` INT UNSIGNED  NOT NULL COMMENT 'ID egg sur le panel',
  `panel_nest_id` INT UNSIGNED NOT NULL COMMENT 'ID nest sur le panel',
  `docker_image` VARCHAR(512)  NOT NULL,
  `startup`      TEXT          NOT NULL,
  `env_vars`     JSON          DEFAULT NULL COMMENT 'Variables env par défaut (JSON)',
  `icon`         VARCHAR(50)   DEFAULT 'fas fa-server',
  `is_active`    TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────
-- 3. PRODUCTS  (offres/plans — remplace offers.php)
-- ─────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `products` (
  `id`           INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `slug`         VARCHAR(80)     NOT NULL COMMENT 'ex: minecraft-basic',
  `name`         VARCHAR(150)    NOT NULL,
  `description`  TEXT            DEFAULT NULL,
  `type`         ENUM('free','paid') NOT NULL DEFAULT 'paid',
  `price`        DECIMAL(8,2)    NOT NULL DEFAULT 0.00,
  `node_id`      INT UNSIGNED    NOT NULL COMMENT 'FK → nodes.id',
  `egg_id`       INT UNSIGNED    NOT NULL COMMENT 'FK → eggs.id',
  `ram`          INT UNSIGNED    NOT NULL DEFAULT 512  COMMENT 'MB',
  `disk`         INT UNSIGNED    NOT NULL DEFAULT 5000 COMMENT 'MB',
  `cpu`          INT UNSIGNED    NOT NULL DEFAULT 100  COMMENT '%',
  `databases`    TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `backups`      TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `allocations`  TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `env_override` JSON            DEFAULT NULL COMMENT 'Surcharge des env_vars de l\'egg',
  `is_active`    TINYINT(1)      NOT NULL DEFAULT 1,
  `sort_order`   SMALLINT        NOT NULL DEFAULT 0,
  `created_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  INDEX `node_id` (`node_id`),
  INDEX `egg_id`  (`egg_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────
-- 4. EXTENSIONS  (catalogue des extensions disponibles)
-- ─────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `extensions` (
  `id`          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `slug`        VARCHAR(60)   NOT NULL COMMENT 'ex: stripe, paypal, discord, smtp',
  `name`        VARCHAR(100)  NOT NULL,
  `description` VARCHAR(255)  DEFAULT NULL,
  `icon`        VARCHAR(80)   DEFAULT 'fas fa-plug',
  `is_enabled`  TINYINT(1)    NOT NULL DEFAULT 0,
  `created_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────
-- 5. EXTENSION_SETTINGS  (config clé/valeur par extension)
-- ─────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `extension_settings` (
  `extension_id` INT UNSIGNED  NOT NULL,
  `key`          VARCHAR(100)  NOT NULL,
  `value`        TEXT          DEFAULT NULL,
  `updated_at`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`extension_id`, `key`),
  CONSTRAINT `fk_ext_settings` FOREIGN KEY (`extension_id`) REFERENCES `extensions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────
-- 6. ORDERS — ajouter colonne product_id (nullable, compat ascendante)
-- ─────────────────────────────────────────────────────────
ALTER TABLE `orders`
  ADD COLUMN IF NOT EXISTS `product_id` INT UNSIGNED DEFAULT NULL AFTER `user_id`;

-- ─────────────────────────────────────────────────────────
-- 7. SEEDER : Extensions par défaut
-- ─────────────────────────────────────────────────────────
INSERT IGNORE INTO `extensions` (`slug`, `name`, `description`, `icon`, `is_enabled`) VALUES
  ('pterodactyl', 'Pterodactyl',  'Gestion automatique des serveurs de jeu',    'fas fa-dragon',      1),
  ('stripe',      'Stripe',       'Paiements par carte bancaire (Stripe)',       'fab fa-stripe-s',    0),
  ('paypal',      'PayPal',       'Paiements via PayPal.me',                     'fab fa-paypal',      0),
  ('discord',     'Discord',      'Notifications webhook Discord',               'fab fa-discord',     0),
  ('smtp',        'SMTP / Email', 'Envoi d\'emails transactionnels',             'fas fa-envelope',    0),
  ('promo',       'Codes promo',  'Réductions et codes promotionnels',           'fas fa-tag',         1);

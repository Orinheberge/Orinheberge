-- ─────────────────────────────────────────────────────────────────────────────
-- STRUCTURE DE LA BASE DE DONNÉES
-- ─────────────────────────────────────────────────────────────────────────────

-- Table principale des promotions
CREATE TABLE IF NOT EXISTS `promos` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `slug` VARCHAR(50) NOT NULL UNIQUE,          -- Correspond à la clé du tableau PHP (ex: 'valentin')
    `name` VARCHAR(100) NOT NULL,               -- Nom affiché de la promotion
    `code` VARCHAR(50) NOT NULL UNIQUE,         -- Code de réduction (ex: 'AMOUR14')
    `discount` DECIMAL(10,2) NOT NULL,          -- Valeur de la réduction
    `type` ENUM('fixed', 'percent') NOT NULL,   -- Type de réduction
    `start_date` DATE NOT NULL,                 -- Date de début de validité
    `end_date` DATE NOT NULL,                   -- Date de fin de validité
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,  -- Statut d'activation (1 = actif, 0 = inactif)
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table de liaison pour les restrictions de types d'offres (applies_to)
CREATE TABLE IF NOT EXISTS `promo_applies_to` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `promo_id` INT NOT NULL,
    `offer_type` VARCHAR(100) NOT NULL,         -- Type d'offre ciblé (ex: 'vps', 'minecraft')
    FOREIGN KEY (`promo_id`) REFERENCES `promos`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_promo_offer` (`promo_id`, `offer_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ─────────────────────────────────────────────────────────────────────────────
-- INSERTION DES DONNÉES (PROMOS 2026/2027)
-- ─────────────────────────────────────────────────────────────────────────────

INSERT INTO `promos` (`slug`, `name`, `code`, `discount`, `type`, `start_date`, `end_date`, `is_active`) VALUES
('valentin',    '❤️ Saint-Valentin',            'AMOUR14',     2.00,  'fixed',   '2026-02-14', '2026-02-14', 1),
('paques',      '🐣 Pâques',                    'PAQUES',      10.00, 'percent', '2026-04-05', '2026-04-06', 1),
('anniv',       '🎂 Anniversaire OrinHeberge', 'ANNIV1AN',    25.00, 'percent', '2026-06-01', '2026-06-01', 1),
('ete',         '☀️ Été 2026',                  'ETE2026',     50.00, 'percent', '2026-06-26', '2026-08-30', 1),
('bastille',    '🇫🇷 Fête Nationale',            'FRANCE14',    14.00, 'percent', '2026-07-14', '2026-07-14', 1),
('halloween',   '🎃 Halloween',                 'HALLOWEEN',   10.00, 'percent', '2026-10-31', '2026-10-31', 1),
('blackfriday', '🖤 Black Friday',              'BLACKFRIDAY', 30.00, 'percent', '2026-11-27', '2026-11-30', 1),
('noel',        '🎄 Offre de Noël',             'NOEL2026',    20.00, 'percent', '2026-12-20', '2026-12-26', 1),
('nouvel_an',   '🎆 Bonne Année',               'BONNEAN2027', 15.00, 'percent', '2026-12-31', '2027-01-02', 1);
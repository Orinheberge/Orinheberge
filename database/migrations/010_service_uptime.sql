-- ============================================================================
-- service_uptime.sql
-- Table de suivi du statut (uptime) des services affichés sur /status.php
-- ============================================================================

CREATE TABLE IF NOT EXISTS `service_uptime` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `service_name`  VARCHAR(100) NOT NULL,
    `check_date`    DATE NOT NULL,
    `is_online`     TINYINT(1) NOT NULL DEFAULT 1,
    `checked_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),

    -- Un seul enregistrement par service et par jour (évite les doublons
    -- si le cron de vérification tourne plusieurs fois dans la journée)
    UNIQUE KEY `uniq_service_date` (`service_name`, `check_date`),

    -- Index utilisé par la requête WHERE service_name IN (...) AND check_date >= ...
    KEY `idx_service_date` (`service_name`, `check_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Optionnel : quelques lignes de test pour vérifier l'affichage
-- (à supprimer/adapter — utile seulement en dev)
-- ----------------------------------------------------------------------------
-- INSERT INTO service_uptime (service_name, check_date, is_online) VALUES
--   ('Site Web',              CURDATE(), 1),
--   ('Panel de gestion',      CURDATE(), 1),
--   ('phpMyAdmin',            CURDATE(), 1),
--   ('Node OrinStone',        CURDATE(), 1),
--   ('Node DeepStone Global', CURDATE(), 0);

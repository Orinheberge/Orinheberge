-- ═══════════════════════════════════════════════════════════════
-- TABLE : maintenance
-- Description : Gestion des maintenances planifiées ou en cours
-- pour afficher un bandeau d'information aux utilisateurs
-- ═══════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `maintenance` (
  `id`                INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `title`             VARCHAR(255)     NOT NULL,
  `slug`              VARCHAR(100)     NOT NULL,
  `description`       TEXT             DEFAULT NULL,
  `description_html`  TEXT             DEFAULT NULL,
  
  -- Type et statut
  `type`              ENUM('planned', 'emergency', 'improvement', 'security') NOT NULL DEFAULT 'planned',
  `status`            ENUM('scheduled', 'in_progress', 'completed', 'cancelled', 'postponed') NOT NULL DEFAULT 'scheduled',
  `severity`          ENUM('info', 'warning', 'critical') NOT NULL DEFAULT 'info',
  
  -- Dates
  `start_date`        DATETIME         NOT NULL,
  `end_date`          DATETIME         NOT NULL,
  `actual_start`      DATETIME         DEFAULT NULL,
  `actual_end`        DATETIME         DEFAULT NULL,
  
  -- Portée de la maintenance
  `affects_all`       TINYINT(1)       NOT NULL DEFAULT 1,
  `affected_services` JSON             DEFAULT NULL COMMENT 'Liste des services impactés (ex: ["panel", "billing", "api"])',
  `affected_nodes`    JSON             DEFAULT NULL COMMENT 'Liste des IDs des nodes impactés',
  
  -- Affichage
  `is_public`         TINYINT(1)       NOT NULL DEFAULT 1 COMMENT 'Visible par les clients',
  `is_active`         TINYINT(1)       NOT NULL DEFAULT 1 COMMENT 'Affiché sur le site',
  `show_banner`       TINYINT(1)       NOT NULL DEFAULT 1 COMMENT 'Afficher un bandeau d''alerte',
  `block_access`      TINYINT(1)       NOT NULL DEFAULT 0 COMMENT 'Bloquer l''accès au site',
  
  -- Métadonnées
  `created_by`        INT UNSIGNED     DEFAULT NULL,
  `updated_by`        INT UNSIGNED     DEFAULT NULL,
  `created_at`        DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_slug` (`slug`),
  INDEX `idx_status` (`status`),
  INDEX `idx_type` (`type`),
  INDEX `idx_severity` (`severity`),
  INDEX `idx_dates` (`start_date`, `end_date`),
  INDEX `idx_active_public` (`is_active`, `is_public`, `start_date`, `end_date`),
  INDEX `idx_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═══════════════════════════════════════════════════════════════
-- TABLE : maintenance_updates
-- Description : Historique des mises à jour pendant une maintenance
-- (pour informer les clients en temps réel)
-- ═══════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `maintenance_updates` (
  `id`              INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `maintenance_id`  INT UNSIGNED     NOT NULL,
  `message`         TEXT             NOT NULL,
  `message_html`    TEXT             DEFAULT NULL,
  `status`          ENUM('info', 'investigating', 'monitoring', 'resolved') NOT NULL DEFAULT 'info',
  `is_public`       TINYINT(1)       NOT NULL DEFAULT 1,
  `created_by`      INT UNSIGNED     DEFAULT NULL,
  `created_at`      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  INDEX `idx_maintenance` (`maintenance_id`),
  INDEX `idx_created_at` (`created_at`),
  CONSTRAINT `fk_maintenance_updates_maintenance` 
    FOREIGN KEY (`maintenance_id`) REFERENCES `maintenance` (`id`) 
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═══════════════════════════════════════════════════════════════
-- TABLE : maintenance_subscriptions
-- Description : Abonnement des utilisateurs aux notifications
-- de maintenance
-- ═══════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `maintenance_subscriptions` (
  `id`              INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `user_id`         INT UNSIGNED     NOT NULL,
  `notify_email`    TINYINT(1)       NOT NULL DEFAULT 1,
  `notify_discord`  TINYINT(1)       NOT NULL DEFAULT 0,
  `created_at`      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_user` (`user_id`),
  CONSTRAINT `fk_maintenance_subs_user` 
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) 
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ═══════════════════════════════════════════════════════════════
-- DONNÉES D'EXEMPLE
-- ═══════════════════════════════════════════════════════════════

INSERT INTO `maintenance` 
  (`title`, `slug`, `description`, `type`, `status`, `severity`, 
   `start_date`, `end_date`, `affects_all`, `affected_services`, 
   `is_public`, `is_active`, `show_banner`, `block_access`, `created_by`)
VALUES 
  (
    'Maintenance planifiée - Mise à jour système',
    'maintenance-system-update-2026-07',
    'Nous effectuons une mise à jour de notre infrastructure pour améliorer les performances et la sécurité de nos services. Pendant cette période, certains services peuvent être temporairement indisponibles.',
    'planned',
    'scheduled',
    'warning',
    '2026-07-15 02:00:00',
    '2026-07-15 06:00:00',
    1,
    '["panel", "billing", "api"]',
    1, 1, 1, 0,
    1
  ),
  (
    'Amélioration réseau - Node Paris',
    'network-upgrade-paris-2026',
    'Migration vers un nouveau backbone réseau pour le node Paris afin d''améliorer la latence et la stabilité.',
    'improvement',
    'scheduled',
    'info',
    '2026-07-20 03:00:00',
    '2026-07-20 05:00:00',
    0,
    '["panel"]',
    1, 1, 1, 0,
    1
  );

-- ═══════════════════════════════════════════════════════════════
-- VUES UTILES
-- ═══════════════════════════════════════════════════════════════

-- Vue : Maintenances actuellement actives (en cours ou à venir)
CREATE OR REPLACE VIEW `v_active_maintenances` AS
SELECT 
  m.*,
  CASE 
    WHEN NOW() < m.start_date THEN 'upcoming'
    WHEN NOW() BETWEEN m.start_date AND m.end_date THEN 'ongoing'
    ELSE 'past'
  END AS `period`,
  TIMESTAMPDIFF(MINUTE, GREATEST(NOW(), m.start_date), m.end_date) AS `minutes_remaining`
FROM `maintenance` m
WHERE m.is_active = 1 
  AND m.is_public = 1
  AND m.status NOT IN ('cancelled')
  AND m.end_date >= NOW()
ORDER BY m.severity DESC, m.start_date ASC;

-- Vue : Maintenances à venir (prochaines 7 jours)
CREATE OR REPLACE VIEW `v_upcoming_maintenances` AS
SELECT *
FROM `maintenance`
WHERE is_active = 1 
  AND is_public = 1
  AND status = 'scheduled'
  AND start_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)
ORDER BY start_date ASC;

-- Vue : Maintenances en cours
CREATE OR REPLACE VIEW `v_ongoing_maintenances` AS
SELECT *
FROM `maintenance`
WHERE is_active = 1 
  AND is_public = 1
  AND status IN ('scheduled', 'in_progress')
  AND NOW() BETWEEN start_date AND end_date
ORDER BY severity DESC, start_date ASC;
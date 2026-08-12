-- Migration pour le système de cahiers des charges
-- Date: 2026-07-10
-- Compatible MySQL 5.7+ et MariaDB 10.2+

-- ─────────────────────────────────────────────────────────────
-- Table: cahier_charges
-- Stocke les cahiers des charges/projets
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `cahier_charges` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `titre` VARCHAR(255) NOT NULL COMMENT 'Titre du cahier des charges',
  `description` TEXT NOT NULL COMMENT 'Description détaillée du projet',
  `client_nom` VARCHAR(150) DEFAULT NULL COMMENT 'Nom du client',
  `client_email` VARCHAR(255) DEFAULT NULL COMMENT 'Email du client',
  `client_telephone` VARCHAR(50) DEFAULT NULL COMMENT 'Téléphone du client',
  `statut` ENUM('draft', 'in_progress', 'review', 'completed', 'archived') NOT NULL DEFAULT 'draft' COMMENT 'Statut du projet',
  `priorite` ENUM('low', 'medium', 'high', 'urgent') NOT NULL DEFAULT 'medium' COMMENT 'Priorité du projet',
  `budget` DECIMAL(10,2) DEFAULT NULL COMMENT 'Budget estimé en euros',
  `date_limite` DATE DEFAULT NULL COMMENT 'Date limite de livraison',
  `progression` TINYINT UNSIGNED DEFAULT 0 COMMENT 'Pourcentage d\'avancement (0-100)',
  `notes_admin` TEXT DEFAULT NULL COMMENT 'Notes internes de l\'admin',
  `created_by` INT UNSIGNED NOT NULL COMMENT 'ID de l\'admin créateur',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cahier_charges_statut` (`statut`),
  KEY `idx_cahier_charges_priorite` (`priorite`),
  KEY `idx_cahier_charges_date_limite` (`date_limite`),
  KEY `idx_cahier_charges_created_by` (`created_by`),
  KEY `idx_cahier_charges_created_at` (`created_at`),
  CONSTRAINT `fk_cahier_charges_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- Table: cahier_charges_files
-- Stocke les fichiers joints aux cahiers des charges
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `cahier_charges_files` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `cahier_id` INT UNSIGNED NOT NULL COMMENT 'ID du cahier des charges',
  `filename` VARCHAR(255) NOT NULL COMMENT 'Nom original du fichier',
  `filepath` VARCHAR(500) NOT NULL COMMENT 'Chemin du fichier sur le serveur',
  `filesize` BIGINT UNSIGNED NOT NULL COMMENT 'Taille du fichier en bytes',
  `filetype` VARCHAR(100) NOT NULL COMMENT 'Type MIME du fichier',
  `uploaded_by` INT UNSIGNED NOT NULL COMMENT 'ID de l\'admin uploader',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cahier_files_cahier_id` (`cahier_id`),
  KEY `idx_cahier_files_uploaded_by` (`uploaded_by`),
  CONSTRAINT `fk_cahier_files_cahier_id` FOREIGN KEY (`cahier_id`) REFERENCES `cahier_charges` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_cahier_files_uploaded_by` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- Table: cahier_charges_comments
-- Stocke les commentaires sur les cahiers des charges
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `cahier_charges_comments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `cahier_id` INT UNSIGNED NOT NULL COMMENT 'ID du cahier des charges',
  `user_id` INT UNSIGNED NOT NULL COMMENT 'ID de l\'utilisateur commentant',
  `commentaire` TEXT NOT NULL COMMENT 'Contenu du commentaire',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cahier_comments_cahier_id` (`cahier_id`),
  KEY `idx_cahier_comments_user_id` (`user_id`),
  KEY `idx_cahier_comments_created_at` (`created_at`),
  CONSTRAINT `fk_cahier_comments_cahier_id` FOREIGN KEY (`cahier_id`) REFERENCES `cahier_charges` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_cahier_comments_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────
-- Vues utiles (optionnel)
-- ─────────────────────────────────────────────────────────────

-- Vue pour les statistiques par statut
CREATE OR REPLACE VIEW `v_cahier_stats_by_status` AS
SELECT 
  statut,
  COUNT(*) as total,
  SUM(CASE WHEN priorite = 'urgent' THEN 1 ELSE 0 END) as urgent_count,
  AVG(progression) as avg_progression
FROM cahier_charges
GROUP BY statut;

-- Vue pour les cahiers en retard
CREATE OR REPLACE VIEW `v_cahier_overdue` AS
SELECT 
  id,
  titre,
  client_nom,
  date_limite,
  progression,
  DATEDIFF(CURDATE(), date_limite) as days_overdue
FROM cahier_charges
WHERE date_limite < CURDATE() 
  AND statut NOT IN ('completed', 'archived')
ORDER BY date_limite ASC;
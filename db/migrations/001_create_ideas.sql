-- Table: ideas (backlog d'idées à réaliser)
CREATE TABLE IF NOT EXISTS `ideas` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `titre` VARCHAR(255) NOT NULL COMMENT 'Titre de l\'idée',
  `description` TEXT DEFAULT NULL COMMENT 'Description / détails',
  `categorie` VARCHAR(100) DEFAULT NULL COMMENT 'Catégorie (ex: feature, bug, design, etc.)',
  `priorite` ENUM('low', 'medium', 'high', 'urgent') NOT NULL DEFAULT 'medium',
  `statut` ENUM('todo', 'in_progress', 'done', 'cancelled') NOT NULL DEFAULT 'todo',
  `created_by` INT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ideas_statut` (`statut`),
  KEY `idx_ideas_priorite` (`priorite`),
  KEY `idx_ideas_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
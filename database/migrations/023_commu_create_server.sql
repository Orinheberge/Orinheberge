-- =========================================================
-- Migration : commu_server.sql
-- Objectif : Ajouter la gestion des serveurs de communauté
-- =========================================================

-- 1. Table des serveurs
CREATE TABLE IF NOT EXISTS `commu_servers` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL COMMENT 'Nom du serveur',
    `description` TEXT NULL COMMENT 'Description courte du serveur',
    `icon` VARCHAR(255) NULL COMMENT 'Chemin ou URL de l''icône du serveur',
    `owner_id` INT UNSIGNED NOT NULL COMMENT 'ID du créateur (référence à la table users)',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_owner_id` (`owner_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Table des membres du serveur (liaison many-to-many)
CREATE TABLE IF NOT EXISTS `commu_server_members` (
    `server_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL COMMENT 'ID de l''utilisateur (référence à la table users)',
    `role` ENUM('owner', 'admin', 'moderator', 'member') DEFAULT 'member' COMMENT 'Rôle de l''utilisateur dans le serveur',
    `joined_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`server_id`, `user_id`),
    INDEX `idx_user_id` (`user_id`),
    CONSTRAINT `fk_member_server` 
        FOREIGN KEY (`server_id`) 
        REFERENCES `commu_servers` (`id`) 
        ON DELETE CASCADE,
    CONSTRAINT `fk_member_user` 
        FOREIGN KEY (`user_id`) 
        REFERENCES `users` (`id`) 
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Table des canaux (pour remplacer/étendre les canaux en dur actuels)
CREATE TABLE IF NOT EXISTS `commu_channels` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `server_id` INT UNSIGNED NOT NULL,
    `name` VARCHAR(100) NOT NULL COMMENT 'Nom du canal (ex: general, support)',
    `description` VARCHAR(255) NULL COMMENT 'Description du canal',
    `type` ENUM('text', 'voice', 'announcement') DEFAULT 'text' COMMENT 'Type de canal',
    `position` INT UNSIGNED DEFAULT 0 COMMENT 'Ordre d''affichage des canaux',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_server_id` (`server_id`),
    CONSTRAINT `fk_channel_server` 
        FOREIGN KEY (`server_id`) 
        REFERENCES `commu_servers` (`id`) 
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Trigger optionnel : Ajouter automatiquement le créateur comme membre 'owner'
DELIMITER //
CREATE TRIGGER `after_server_insert` 
AFTER INSERT ON `commu_servers`
FOR EACH ROW
BEGIN
    INSERT INTO `commu_server_members` (`server_id`, `user_id`, `role`)
    VALUES (NEW.id, NEW.owner_id, 'owner');
END;
//
DELIMITER ;
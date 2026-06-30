-- Migration: add is_admin flag to users
-- Exécuter dans phpMyAdmin ou via votre outil de migration

ALTER TABLE `users`
  ADD COLUMN `is_admin` TINYINT(1) NOT NULL DEFAULT 0 AFTER `password`;

-- Pour activer l'admin pour l'utilisateur id=1 par défaut :
-- UPDATE `users` SET `is_admin` = 1 WHERE `id` = 1;

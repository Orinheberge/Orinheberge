-- ====================================================================
-- CRÉATION DE LA TABLE DES TRADUCTIONS DE LA BOUTIQUE
-- ====================================================================
CREATE TABLE IF NOT EXISTS `lang_boutique` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `translation_key` VARCHAR(255) NOT NULL COMMENT 'Clé de traduction, ex: offer.mc_free.name',
  `fr` TEXT NOT NULL COMMENT 'Texte traduit en français',
  `en` TEXT NOT NULL COMMENT 'Texte traduit en anglais',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_lang_boutique_key` (`translation_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

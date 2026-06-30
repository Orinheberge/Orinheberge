-- Migration: table de configuration dynamique admin
CREATE TABLE IF NOT EXISTS `settings` (
  `key`        VARCHAR(100)  NOT NULL,
  `value`      TEXT          DEFAULT NULL,
  `updated_at` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Valeurs par défaut
INSERT IGNORE INTO `settings` (`key`, `value`) VALUES
  ('panel_url',       'https://panel.orinstone.deepstone.fr'),
  ('api_key_admin',   'ptla_YKix8PexQDCZ7nIeexST3NXC2sFwQAoefDtOQBvJkbx'),
  ('api_key_client',  'ptlc_MfJSOUID0bnTgFCmm5VvYMML2jKUUA5RFZ2n2MeZCSU'),
  ('phpmyadmin_url',  'https://php.orinstone.deepstone.fr'),
  ('site_name',       'OrinHeberge'),
  ('smtp_host',       ''),
  ('smtp_port',       '587'),
  ('smtp_user',       ''),
  ('smtp_pass',       ''),
  ('smtp_from',       'no-reply@deepstone.fr'),
  ('smtp_from_name',  'OrinHeberge');

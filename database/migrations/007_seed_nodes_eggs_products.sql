-- Migration 007 : Seeder — Nodes, Eggs et Products depuis les offers.php hardcodés
-- Exécuter APRÈS la migration 006

-- ─────────────────────────────────────────────────────────
-- NODES (à adapter selon vos vrais panel_node_id)
-- ─────────────────────────────────────────────────────────
INSERT IGNORE INTO `nodes` (`id`, `name`, `panel_node_id`, `location_id`, `fqdn`, `is_active`) VALUES
  (1, 'Node 1 — Web/Bot',  1, 1, 'node1.orinstone.deepstone.fr', 1),
  (2, 'Node 2 — Jeux',     2, 1, 'node2.orinstone.deepstone.fr', 1);

-- ─────────────────────────────────────────────────────────
-- EGGS (panel_egg_id = ID réel sur votre panel Pterodactyl)
-- ⚠ Vérifiez les IDs via /db/debug_eggs.php avant de lancer
-- ─────────────────────────────────────────────────────────
INSERT IGNORE INTO `eggs` (`id`, `name`, `panel_egg_id`, `panel_nest_id`, `docker_image`, `startup`, `env_vars`, `icon`) VALUES

(1, 'Minecraft (Paper/Vanilla)', 2, 1,
 'ghcr.io/pterodactyl/yolks:java_25',
 'java -Xms128M -XX:+UseG1GC -jar {{SERVER_JARFILE}} nogui',
 '{"SERVER_JARFILE":"server.jar","MINECRAFT_VERSION":"latest","BUILD_NUMBER":"latest"}',
 'fas fa-cube'),

(2, 'NodeJS', 27, 7,
 'ghcr.io/ptero-eggs/yolks:nodejs_25',
 'if [[ -d .git ]] && [[ {{AUTO_UPDATE}} == "1" ]]; then git pull; fi; /usr/local/bin/node /home/container/{{MAIN_FILE}}',
 '{"MAIN_FILE":"index.js","USER_UPLOAD":"1","AUTO_UPDATE":"0"}',
 'fab fa-node-js'),

(3, 'Python', 28, 8,
 'ghcr.io/ptero-eggs/yolks:python_3.13',
 'if [[ ! -z "{{PY_PACKAGES}}" ]]; then pip install -U --prefix .local {{PY_PACKAGES}}; fi; /usr/local/bin/python /home/container/{{PY_FILE}}',
 '{"PY_FILE":"index.py","AUTO_UPDATE":"0","PY_PACKAGES":"","USER_UPLOAD":"0","REQUIREMENTS_FILE":"requirements.txt"}',
 'fab fa-python'),

(4, 'PHP / Nginx', 18, 5,
 'ym0t/pterodactyl-nginx-egg:8.5-latest',
 './start-modules.sh',
 '{"PHP_VERSION":"8.4","WORDPRESS":"0","GIT_STATUS":"0","AUTOUPDATE_STATUS":"1","CLOUDFLARED_STATUS":"0","COMPOSER_STATUS":"0","CRON_STATUS":"0"}',
 'fas fa-code'),

(5, 'FiveM', 15, 5,
 'ghcr.io/pterodactyl/games:fivem',
 'bash /home/container/run.sh',
 '{"TXADMIN_PORT":"40120","FIVEM_VERSION":"latest"}',
 'fas fa-car'),

(6, 'Java (JAR)', 27, 7,
 'ghcr.io/ptero-eggs/yolks:java_25',
 'java -Dterminal.jline=false -Dterminal.ansi=true -jar {{JARFILE}}',
 '{"JARFILE":"server.jar","USER_UPLOAD":"1","AUTO_UPDATE":"0"}',
 'fab fa-java'),

(7, 'Hytale', 17, 6,
 'ghcr.io/pterodactyl/games:hytale',
 'java -Xms128M -jar Server/HytaleServer.jar --auth-mode ${HYTALE_AUTH_MODE} --assets Assets.zip --bind 0.0.0.0:${SERVER_PORT}',
 '{"SERVER_MEMORY":"6144","USE_AOT_CACHE":"1","HYTALE_AUTH_MODE":"authenticated","HYTALE_PATCHLINE":"release"}',
 'fas fa-gamepad');

-- ─────────────────────────────────────────────────────────
-- PRODUCTS — Offres GRATUITES (process_free)
-- ─────────────────────────────────────────────────────────


INSERT INTO `products` (`id`, `slug`, `name`, `description`, `type`, `price`, `node_id`, `egg_id`, `ram`, `disk`, `cpu`, `databases`, `backups`, `allocations`, `env_override`, `is_active`, `sort_order`, `created_at`) VALUES
(68, 'minecraft-free', 'Minecraft Free', '', 'free', 0.00, 2, 1, 6422, 5422, 200, 1, 10, 4, NULL, 1, 1, '2026-07-02 19:07:20'),
(69, 'terraria-free', 'Terraria Free', '', 'free', 0.00, 2, 23, 6422, 5422, 200, 1, 10, 5, NULL, 1, 2, '2026-07-02 19:12:28'),
(70, 'fivem-free', 'fiveM Free', '', 'free', 0.00, 2, 9, 6422, 5422, 200, 1, 10, 5, NULL, 1, 3, '2026-07-02 19:25:20'),
(71, 'hytale-free', 'Hytale Free', '', 'free', 0.00, 2, 7, 6422, 5422, 200, 1, 10, 1, NULL, 1, 5, '2026-07-02 19:29:36'),
(72, 'teamspeak3-free', 'Teamspeak3 Free', '', 'free', 0.00, 1, 70, 6422, 5422, 200, 1, 10, 5, NULL, 1, 5, '2026-07-02 19:46:57'),
(73, 'nodejs-free', 'NodeJS Free', '', 'free', 0.00, 1, 2, 6422, 5422, 200, 1, 10, 5, NULL, 1, 6, '2026-07-02 19:48:44'),
(74, 'java-free', 'Java Free', '', 'free', 0.00, 1, 39, 6422, 5422, 200, 1, 10, 5, NULL, 1, 7, '2026-07-02 20:02:15'),
(75, 'python-free', 'python Free', '', 'free', 0.00, 1, 3, 6422, 5422, 200, 1, 10, 5, NULL, 1, 8, '2026-07-02 20:07:35'),
(76, 'php-free', 'php Free', '', 'free', 0.00, 1, 19, 6422, 5422, 200, 1, 10, 5, NULL, 1, 9, '2026-07-02 20:28:03'),
(77, 'minecraft-basic', 'minecraft Basic', '', 'paid', 20.99, 2, 1, 12422, 12422, 800, 2, 100, 10, NULL, 1, 10, '2026-07-03 08:10:33'),
(80, 'hytale-basic', 'Hytale Basic', '', 'paid', 20.99, 2, 7, 12422, 12422, 800, 2, 100, 10, NULL, 1, 12, '2026-07-03 09:58:38'),
(81, 'fivem-basic', 'fiveM Basic', '', 'paid', 24.14, 2, 9, 12422, 12422, 800, 2, 100, 10, NULL, 1, 13, '2026-07-03 10:01:34'),
(83, 'teamspeak3-basic', 'Teamspeak3 Basic', '', 'paid', 20.99, 1, 70, 12422, 12422, 800, 2, 100, 10, NULL, 1, 14, '2026-07-03 12:27:30'),
(84, 'nodejs-basic', 'nodejs Basic', '', 'paid', 20.99, 1, 2, 12422, 12422, 800, 2, 100, 10, NULL, 1, 15, '2026-07-03 12:35:18'),
(85, 'python-basic', 'python Basic', '', 'paid', 20.99, 1, 3, 12422, 12422, 800, 2, 100, 10, NULL, 1, 16, '2026-07-05 12:40:03'),
(86, 'java-basic', 'Java Basic', '', 'paid', 1.49, 1, 39, 12422, 12422, 800, 2, 100, 10, NULL, 1, 17, '2026-07-05 12:41:26'),
(87, 'php-basic', 'PHP Basic', '', 'paid', 3.49, 1, 19, 12422, 12422, 800, 2, 100, 10, NULL, 1, 18, '2026-07-05 12:42:19'),
(88, 'terraria-basic', 'Terraria Basic', '', 'paid', 20.99, 1, 23, 12422, 12422, 800, 2, 100, 10, NULL, 1, 11, '2026-07-05 12:44:48'),
(91, 'azuriom-free', 'Azuriom Free', '', 'free', 0.00, 1, 61, 6422, 5422, 200, 1, 100, 5, NULL, 1, 19, '2026-07-06 11:36:57'),
(92, 'azuriom-basic', 'Azuriom Basic', '', 'paid', 4.49, 1, 61, 12422, 12422, 800, 2, 100, 10, NULL, 1, 20, '2026-07-06 11:44:46'),
(93, 'minecraft-medium', 'Minecraft Medium', '', 'paid', 2.50, 2, 1, 16422, 20422, 1000, 4, 200, 15, NULL, 1, 21, '2026-07-06 12:22:07'),
(94, 'terraria-medium', 'Terraria Medium', '', 'paid', 2.54, 2, 23, 16423, 16423, 1000, 4, 200, 15, NULL, 1, 22, '2026-07-16 20:36:08'),
(95, 'hytale-medium', 'Hytale Medium', '', 'paid', 4.52, 2, 7, 16424, 16424, 1000, 4, 200, 15, NULL, 1, 23, '2026-07-16 20:41:39'),
(96, 'fivem-medium', 'FiveM Medium', '', 'paid', 5.52, 2, 9, 16425, 16425, 1000, 4, 200, 15, NULL, 1, 24, '2026-07-16 20:47:19'),
(98, 'teamspeak-medium', 'teamspeak Medium', '', 'paid', 2.50, 1, 70, 16426, 16426, 1000, 4, 200, 15, NULL, 1, 25, '2026-07-16 20:53:00'),
(99, 'nodejs-medium', 'nodejs Medium', '', 'paid', 3.52, 1, 2, 16427, 16427, 1000, 4, 200, 15, NULL, 1, 26, '2026-07-16 20:55:56'),
(100, 'python-medium', 'python Medium', '', 'paid', 3.70, 1, 3, 16428, 16428, 1000, 4, 200, 15, NULL, 1, 27, '2026-07-16 21:00:19'),
(101, 'java-medium', 'Java Medium', '', 'paid', 3.80, 1, 39, 16429, 16429, 1000, 4, 200, 15, NULL, 1, 28, '2026-07-16 21:02:23'),
(102, 'php-medium', 'php Medium', '', 'paid', 4.54, 1, 19, 16430, 16430, 1000, 4, 200, 15, NULL, 1, 29, '2026-07-16 21:08:39'),
(103, 'azuriom-medium', 'Azuriom Medium', '', 'paid', 5.54, 1, 61, 16431, 16431, 1000, 4, 200, 15, NULL, 1, 30, '2026-07-16 21:10:56'),
(104, 'minecraft-premium', 'Minecraft Premium', '', 'paid', 7.58, 2, 1, 20422, 20422, 1500, 6, 200, 20, NULL, 1, 31, '2026-07-17 11:39:12'),
(106, 'terraria-premium', 'Terraria Premium', '', 'paid', 8.62, 2, 23, 20423, 20423, 1500, 6, 200, 20, NULL, 1, 32, '2026-07-17 11:42:28'),
(107, 'hytale-premium', 'Hytale Premium', '', 'paid', 9.54, 2, 7, 20424, 20424, 1500, 6, 200, 20, NULL, 1, 33, '2026-07-17 11:53:04'),
(108, 'fivem-premium', 'Fivem Premium', '', 'paid', 10.54, 2, 9, 20425, 20425, 1500, 6, 200, 20, NULL, 1, 34, '2026-07-17 12:04:49'),
(110, 'teamspeak-premium', 'Teamspeak Premium', '', 'paid', 4.55, 1, 70, 20426, 20426, 1500, 6, 200, 20, NULL, 1, 35, '2026-07-17 12:18:30'),
(111, 'nodejs-premium', 'Nodejs Premium', '', 'paid', 5.68, 1, 2, 20427, 20427, 1500, 6, 200, 20, NULL, 1, 36, '2026-07-17 12:31:16'),
(112, 'python-premium', 'Python Premium', '', 'paid', 6.54, 1, 3, 20428, 20428, 1500, 6, 200, 6, NULL, 1, 37, '2026-07-17 12:34:49'),
(113, 'java-premium', 'Java Premium', '', 'paid', 7.20, 1, 39, 20429, 20429, 1500, 6, 200, 20, NULL, 1, 38, '2026-07-17 12:41:34'),
(114, 'php-premium', 'Php Premium', '', 'paid', 12.58, 1, 19, 20430, 20430, 1500, 6, 200, 20, NULL, 1, 39, '2026-07-17 12:43:15'),
(115, 'azuriom-premium', 'Azuriom Premium', '', 'paid', 13.54, 1, 61, 20431, 20431, 1500, 6, 200, 20, NULL, 1, 40, '2026-07-17 12:52:39'),
(116, 'minecraft-mythic', 'Minecraft Mythic', '', 'paid', 8.57, 2, 1, 25422, 25422, 2000, 10, 200, 20, NULL, 1, 41, '2026-07-17 17:30:42'),
(117, 'terraria-mythic', 'Terraria Mythic', '', 'paid', 9.27, 2, 23, 25422, 25422, 2000, 10, 200, 20, NULL, 1, 42, '2026-07-17 18:16:41'),
(118, 'hytale-mythic', 'Hytale Mythic', '', 'paid', 10.10, 2, 7, 25422, 25422, 2000, 10, 200, 20, NULL, 1, 43, '2026-07-17 18:21:16'),
(119, 'fivem-mythic', 'Fivem Mythic', '', 'paid', 11.15, 2, 9, 25422, 25422, 2000, 10, 200, 20, NULL, 1, 44, '2026-07-17 18:25:46'),
(120, 'teamspeak-mythic', 'Teamspeak Mythic', '', 'paid', 6.18, 1, 70, 25422, 25422, 2000, 10, 200, 20, NULL, 1, 45, '2026-07-17 18:29:17'),
(121, 'nodejs-mythic', 'Nodejs Mythic', '', 'paid', 7.34, 1, 1, 25422, 25422, 2000, 10, 200, 20, NULL, 1, 46, '2026-07-17 18:32:05'),
(122, 'python-mythic', 'Python Mythic', '', 'paid', 8.40, 1, 3, 25422, 25422, 2000, 10, 200, 20, NULL, 1, 46, '2026-07-17 18:47:35'),
(123, 'java-mythic', 'Java Mythic', '', 'paid', 9.16, 1, 39, 25422, 25422, 2000, 10, 200, 20, NULL, 1, 47, '2026-07-19 03:29:01'),
(128, 'php-mythic', 'Php Mythic', '', 'paid', 13.17, 1, 19, 25422, 25422, 2000, 10, 200, 20, NULL, 1, 48, '2026-07-20 10:28:04'),
(129, 'azuriom-mythic', 'Azuriom Mythic', '', 'paid', 14.27, 1, 61, 25422, 25422, 2000, 10, 200, 20, NULL, 1, 48, '2026-07-20 10:34:52');


-- ─────────────────────────────────────────────────────────
-- EXTENSION SETTINGS — Pterodactyl (déjà dans settings, on crée le lien)
-- ─────────────────────────────────────────────────────────
INSERT IGNORE INTO `extension_settings` (`extension_id`, `key`, `value`)
SELECT e.id, 'panel_url',      s.value FROM extensions e, settings s WHERE e.slug='pterodactyl' AND s.key='panel_url'
UNION ALL
SELECT e.id, 'api_key_admin',  s.value FROM extensions e, settings s WHERE e.slug='pterodactyl' AND s.key='api_key_admin'
UNION ALL
SELECT e.id, 'api_key_client', s.value FROM extensions e, settings s WHERE e.slug='pterodactyl' AND s.key='api_key_client';

-- Extension Stripe — clés vides par défaut
INSERT IGNORE INTO `extension_settings` (`extension_id`, `key`, `value`)
SELECT e.id, 'secret_key',  '' FROM extensions e WHERE e.slug='stripe'
UNION ALL
SELECT e.id, 'public_key',  '' FROM extensions e WHERE e.slug='stripe';

-- Extension PayPal
INSERT IGNORE INTO `extension_settings` (`extension_id`, `key`, `value`)
SELECT e.id, 'username', 'metal544002009' FROM extensions e WHERE e.slug='paypal';

-- Extension Discord
INSERT IGNORE INTO `extension_settings` (`extension_id`, `key`, `value`)
SELECT e.id, 'webhook_url', '' FROM extensions e WHERE e.slug='discord';

-- Extension SMTP
INSERT IGNORE INTO `extension_settings` (`extension_id`, `key`, `value`)
SELECT e.id, 'host',      s.value FROM extensions e, settings s WHERE e.slug='smtp' AND s.key='smtp_host'
UNION ALL
SELECT e.id, 'port',      s.value FROM extensions e, settings s WHERE e.slug='smtp' AND s.key='smtp_port'
UNION ALL
SELECT e.id, 'user',      s.value FROM extensions e, settings s WHERE e.slug='smtp' AND s.key='smtp_user'
UNION ALL
SELECT e.id, 'pass',      s.value FROM extensions e, settings s WHERE e.slug='smtp' AND s.key='smtp_pass'
UNION ALL
SELECT e.id, 'from',      s.value FROM extensions e, settings s WHERE e.slug='smtp' AND s.key='smtp_from'
UNION ALL
SELECT e.id, 'from_name', s.value FROM extensions e, settings s WHERE e.slug='smtp' AND s.key='smtp_from_name';

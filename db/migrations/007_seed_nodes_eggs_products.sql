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
INSERT IGNORE INTO `products` (`slug`,`name`,`type`,`price`,`node_id`,`egg_id`,`ram`,`disk`,`cpu`,`databases`,`backups`,`allocations`,`sort_order`) VALUES
('minecraft-free',  'Minecraft Free',  'free', 0.00, 2, 1, 6144,  30000, 400, 1, 1, 1, 10),
('nodejs-free',     'NodeJS Free',     'free', 0.00, 1, 2, 2048,  10000, 200, 1, 1, 1, 20),
('python-free',     'Python Free',     'free', 0.00, 1, 3, 2048,  10000, 200, 1, 1, 1, 30),
('php-free',        'PHP Free',        'free', 0.00, 1, 4, 1024,  10000, 100, 1, 1, 1, 40),
('java-free',       'Java Free',       'free', 0.00, 1, 6, 2048,  10000, 200, 1, 1, 1, 50);

-- ─────────────────────────────────────────────────────────
-- PRODUCTS — Offres PAYANTES (shop/order)
-- ─────────────────────────────────────────────────────────
INSERT IGNORE INTO `products` (`slug`,`name`,`type`,`price`,`node_id`,`egg_id`,`ram`,`disk`,`cpu`,`databases`,`backups`,`allocations`,`sort_order`) VALUES
-- PHP
('php-basic',      'PHP Basic',          'paid',  1.99, 1, 4,  512,   5000,  100, 1, 1, 1, 100),
('php-medium',     'PHP Medium',         'paid',  4.99, 1, 4, 2048,  10000,  200, 2, 2, 2, 101),
('php-premium',    'PHP Premium',        'paid', 19.99, 1, 4, 8192,  30000,  500, 3, 5, 3, 102),
-- NodeJS
('nodejs-basic',   'NodeJS Basic',       'paid',  1.49, 1, 2,  512,   5000,  100, 1, 1, 1, 110),
('nodejs-medium',  'NodeJS Medium',      'paid',  2.99, 1, 2, 2048,  20000,  500, 2, 2, 2, 111),
('nodejs-premium', 'NodeJS Premium',     'paid',  5.99, 1, 2, 4096,  40000, 1000, 3, 5, 3, 112),
-- Python
('python-basic',   'Python Basic',       'paid',  2.49, 1, 3,  512,   5000,  100, 1, 1, 1, 120),
('python-medium',  'Python Medium',      'paid',  4.99, 1, 3, 2048,  20000,  500, 2, 2, 2, 121),
('python-premium', 'Python Premium',     'paid',  9.99, 1, 3, 4096,  40000, 1000, 3, 5, 3, 122),
-- Java
('java-basic',     'Java Basic',         'paid',  3.99, 1, 6,  512,   5000,  100, 1, 1, 1, 130),
('java-medium',    'Java Medium',        'paid',  7.99, 1, 6, 2048,  20000,  500, 2, 2, 2, 131),
('java-premium',   'Java Premium',       'paid', 15.99, 1, 6, 4096,  40000, 1000, 3, 5, 3, 132),
-- Minecraft
('minecraft-basic',   'Minecraft Basic',   'paid',  1.49, 2, 1, 4096,  20000,  400, 1, 1, 1, 140),
('minecraft-medium',  'Minecraft Medium',  'paid',  2.99, 2, 1, 8192,  50000,  800, 2, 3, 2, 141),
('minecraft-premium', 'Minecraft Premium', 'paid', 24.99, 2, 1,20480, 150000, 2000, 3, 5, 3, 142),
-- FiveM
('fivem-free',     'FiveM Free',         'free',  0.00, 2, 5, 3072,  15000,  300, 1, 1, 1, 150),
('fivem-basic',    'FiveM Basic',        'paid',  2.99, 2, 5, 4096,  20000,  400, 1, 1, 1, 151),
('fivem-medium',   'FiveM Medium',       'paid',  6.99, 2, 5, 8192,  50000,  800, 2, 3, 2, 152),
('fivem-premium',  'FiveM Premium',      'paid', 19.99, 2, 5,16384, 100000, 1500, 3, 5, 3, 153),
-- Hytale
('hytale-basic',   'Hytale Basic',       'paid',  7.99, 2, 7, 4096,  20000,  400, 1, 1, 1, 160),
('hytale-medium',  'Hytale Medium',      'paid', 14.99, 2, 7, 6144,  50000,  800, 2, 3, 2, 161),
('hytale-premium', 'Hytale Premium',     'paid', 29.99, 2, 7,10240, 100000, 1400, 3, 5, 3, 162);

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

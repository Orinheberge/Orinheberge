<?php
/**
 * inc/db.php — Connexion PDO centralisée + chargement config/settings
 * 
 * Tables gérées :
 *   - nodes       : serveurs physiques (Node 1 Web/Bot, Node 2 Jeux)
 *   - eggs        : types de serveurs / images Docker
 *   - products    : offres/plans commerciaux
 * 
 * Fournit :
 *   - $pdo : connexion PDO
 *   - $cfg[] : configuration depuis BDD
 *   - $panel_url, $api_key_admin, $api_key_client
 *   - $headers_admin, $headers_client
 *   - Fonctions utilitaires (produits, API Pterodactyl, création serveurs)
 */

if (isset($pdo)) return; // déjà chargé

// ─── Connexion PDO ───────────────────────────────────────────
$pdo = new PDO(
    'mysql:host=localhost;dbname=s43_orinheberge;charset=utf8mb4',
    'root', '1504',
    [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]
);

// ─── Chargement settings ─────────────────────────────────────
$cfg = [];
foreach ($pdo->query('SELECT `key`,`value` FROM settings') as $r) {
    $cfg[$r['key']] = $r['value'];
}

// ─── Chargement extension_settings (pterodactyl) ─────────────
$ext_row = $pdo->query("
    SELECT es.key, es.value 
    FROM extension_settings es 
    JOIN extensions e ON e.id = es.extension_id 
    WHERE e.slug = 'pterodactyl'
")->fetchAll();
foreach ($ext_row as $r) {
    if (!empty($r['value'])) $cfg[$r['key']] = $r['value'];
}

$panel_url      = $cfg['panel_url']      ?? '';
$api_key_admin  = $cfg['api_key_admin']  ?? '';
$api_key_client = $cfg['api_key_client'] ?? '';

$headers_admin = [
    "Authorization: Bearer $api_key_admin",
    "Accept: application/vnd.pterodactyl.v1+json",
    "Content-Type: application/json"
];
$headers_client = [
    "Authorization: Bearer $api_key_client",
    "Accept: application/vnd.pterodactyl.v1+json",
    "Content-Type: application/json"
];

// ────────────────────────────────────────────────────────────
// FONCTIONS PRODUITS (table `products` JOIN `nodes` JOIN `eggs`)
// ────────────────────────────────────────────────────────────

/**
 * Récupère un produit par son slug
 * Jointure avec nodes (node_id) et eggs (egg_id)
 * 
 * @return array|null Produit complet avec infos node/egg/env
 */
function getProductBySlug(PDO $pdo, string $slug): ?array {
    $stmt = $pdo->prepare('
        SELECT 
            p.id, p.slug, p.name, p.description, p.type, p.price,
            p.node_id, p.egg_id,
            p.ram, p.disk, p.cpu,
            p.databases, p.backups, p.allocations,
            p.env_override, p.is_active AS product_active,
            p.sort_order,
            
            n.id           AS node_db_id,
            n.name         AS node_name,
            n.panel_node_id,
            n.location_id,
            n.fqdn         AS node_fqdn,
            n.is_active    AS node_active,
            
            e.id           AS egg_db_id,
            e.name         AS egg_name,
            e.panel_egg_id,
            e.panel_nest_id,
            e.docker_image,
            e.startup      AS egg_startup,
            e.env_vars     AS egg_env_vars,
            e.icon         AS egg_icon,
            e.is_active    AS egg_active
        FROM products p
        JOIN nodes n ON n.id = p.node_id
        JOIN eggs  e ON e.id = p.egg_id
        WHERE p.slug = ? 
          AND p.is_active = 1 
          AND n.is_active = 1 
          AND e.is_active = 1
        LIMIT 1
    ');
    $stmt->execute([$slug]);
    $product = $stmt->fetch();
    if (!$product) return null;

    // Fusion des variables d'environnement : egg + override produit
    $env = json_decode($product['egg_env_vars'] ?? '{}', true) ?: [];
    if (!empty($product['env_override'])) {
        $override = json_decode($product['env_override'], true) ?: [];
        $env = array_merge($env, $override);
    }
    $product['env']     = $env;
    $product['startup'] = $product['egg_startup'];
    
    return $product;
}

/**
 * Récupère un produit par son ID
 */
function getProductById(PDO $pdo, int $id): ?array {
    $stmt = $pdo->prepare('
        SELECT p.*, 
               n.panel_node_id, n.location_id, n.name AS node_name,
               e.panel_egg_id, e.panel_nest_id, e.docker_image, 
               e.startup AS egg_startup, e.env_vars AS egg_env_vars
        FROM products p
        JOIN nodes n ON n.id = p.node_id
        JOIN eggs  e ON e.id = p.egg_id
        WHERE p.id = ? AND p.is_active = 1 AND n.is_active = 1 AND e.is_active = 1
        LIMIT 1
    ');
    $stmt->execute([$id]);
    $product = $stmt->fetch();
    if (!$product) return null;

    $env = json_decode($product['egg_env_vars'] ?? '{}', true) ?: [];
    if (!empty($product['env_override'])) {
        $override = json_decode($product['env_override'], true) ?: [];
        $env = array_merge($env, $override);
    }
    $product['env']     = $env;
    $product['startup'] = $product['egg_startup'];
    
    return $product;
}

/**
 * Liste tous les produits actifs (triés par sort_order)
 */
function getAllProducts(PDO $pdo): array {
    $stmt = $pdo->prepare('
        SELECT p.*, n.name AS node_name, e.name AS egg_name, e.icon AS egg_icon
        FROM products p
        JOIN nodes n ON n.id = p.node_id
        JOIN eggs  e ON e.id = p.egg_id
        WHERE p.is_active = 1 AND n.is_active = 1 AND e.is_active = 1
        ORDER BY p.sort_order ASC, p.name ASC
    ');
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Liste les produits par type (free / paid)
 */
function getProductsByType(PDO $pdo, string $type): array {
    $stmt = $pdo->prepare('
        SELECT p.*, n.name AS node_name, e.icon AS egg_icon
        FROM products p
        JOIN nodes n ON n.id = p.node_id
        JOIN eggs  e ON e.id = p.egg_id
        WHERE p.type = ? AND p.is_active = 1 AND n.is_active = 1 AND e.is_active = 1
        ORDER BY p.sort_order ASC, p.price ASC
    ');
    $stmt->execute([$type]);
    return $stmt->fetchAll();
}

// ────────────────────────────────────────────────────────────
// API PTERODACTYL
// ────────────────────────────────────────────────────────────

/**
 * Appel API Pterodactyl (Application API)
 */
function pterodactylApi(string $panel_url, array $headers, string $endpoint, ?array $data = null, string $method = 'GET'): mixed {
    $ch = curl_init($panel_url . '/api/application/' . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT        => 20,
    ]);
    if ($method === 'DELETE') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    } elseif ($method === 'POST' || $data !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    $res  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    // PHP 8.0+ : curl_close() n'a plus d'effet
    if (version_compare(PHP_VERSION, '8.0.0', '<')) {
        curl_close($ch);
    }
    
    if ($code === 204) return true;
    return $res ? json_decode($res, true) : null;
}

// ────────────────────────────────────────────────────────────
// UTILITAIRES
// ────────────────────────────────────────────────────────────

/**
 * Génère un mot de passe aléatoire (12 caractères)
 */
function generatePassword(int $length = 12): string {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
    $p = '';
    for ($i = 0; $i < $length; $i++) {
        $p .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $p;
}

/**
 * Crée ou récupère un utilisateur sur le panel Pterodactyl
 */
function getOrCreatePanelUser(string $panel_url, array $headers, array $user, PDO $pdo): array {
    $search = pterodactylApi($panel_url, $headers, 'users?filter[email]=' . urlencode($user['email']));
    if (!empty($search['data'][0]['attributes']['id'])) {
        return ['id' => (int) $search['data'][0]['attributes']['id'], 'pass' => null];
    }
    
    $pass    = generatePassword();
    $created = pterodactylApi($panel_url, $headers, 'users', [
        'email'      => $user['email'],
        'username'   => $user['pseudo'] ?? ('user' . $user['id']),
        'first_name' => $user['firstname'] ?? 'User',
        'last_name'  => $user['lastname']  ?? 'Account',
        'password'   => $pass,
    ]);
    
    $uid = $created['attributes']['id'] ?? null;
    if (!$uid) {
        error_log('Panel user creation error: ' . json_encode($created));
        die('<pre>PANEL USER ERROR: ' . htmlspecialchars(json_encode($created, JSON_PRETTY_PRINT)) . '</pre>');
    }
    
    $pdo->prepare('UPDATE users SET panel_password=? WHERE id=?')->execute([$pass, $user['id']]);
    return ['id' => (int) $uid, 'pass' => $pass];
}

// ─────────────────────────────────────────────────────────────
// GESTION DES NODES (table `nodes`)
// ─────────────────────────────────────────────────────────────

/**
 * 🔵 Détermine le node cible (ID BDD) pour un produit
 * 
 * Table `nodes` :
 *   id=1 → Node 1 — Web/Bot  (panel_node_id=1)
 *   id=2 → Node 2 — Jeux     (panel_node_id=2)
 * 
 * Règle :
 *   - Slugs listés (jeux) → Node 2 (Jeux)
 *   - Autres slugs        → Node 1 (Web/Bot)
 */
function getTargetNodeId(string $slug): int {
    $normalized = str_replace('-', '_', strtolower($slug));
    
    $forced_slugs = [
        'terraria_free', 'minecraft_free', 'hytale_free', 'fivem_free',
        'terraria_basic', 'minecraft_basic', 'fivem_basic',
        'hytale_medium', 'hytale_premium', 'hytale_mythic',
        'minecraft_medium', 'minecraft_premium', 'minecraft_mythic',
        'fivem_medium', 'fivem_premium', 'fivem_mythic',
        'terrania_medium', 'terraria_premium', 'terraria_mythic',
    ];
    
    return in_array($normalized, $forced_slugs) ? 2 : 1;
}

/**
 * 🔵 Récupère les infos d'un node depuis la table `nodes`
 * 
 * @return array|null ['id', 'name', 'panel_node_id', 'location_id', 'fqdn', 'is_active']
 */
function getNodeInfo(PDO $pdo, int $node_db_id): ?array {
    $stmt = $pdo->prepare("
        SELECT id, name, panel_node_id, location_id, fqdn, is_active
        FROM nodes 
        WHERE id = ? AND is_active = 1
        LIMIT 1
    ");
    $stmt->execute([$node_db_id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Liste tous les nodes actifs
 */
function getActiveNodes(PDO $pdo): array {
    $stmt = $pdo->prepare("
        SELECT id, name, panel_node_id, location_id, fqdn
        FROM nodes 
        WHERE is_active = 1
        ORDER BY id ASC
    ");
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Liste les nodes disponibles pour un produit donné (via product_nodes si existe, sinon node_id du produit)
 */
function getAvailableNodesForProduct(PDO $pdo, int $product_id): array {
    // Vérifie si la table product_nodes existe
    $tableExists = $pdo->query("SHOW TABLES LIKE 'product_nodes'")->fetch();
    
    if ($tableExists) {
        $stmt = $pdo->prepare("
            SELECT n.id, n.name, n.panel_node_id, n.location_id, n.fqdn
            FROM product_nodes pn
            JOIN nodes n ON n.id = pn.node_id
            WHERE pn.product_id = ? AND n.is_active = 1
            ORDER BY n.id
        ");
        $stmt->execute([$product_id]);
        $nodes = $stmt->fetchAll();
        if (!empty($nodes)) return $nodes;
    }
    
    // Fallback : utiliser le node_id du produit
    $stmt = $pdo->prepare("
        SELECT n.id, n.name, n.panel_node_id, n.location_id, n.fqdn
        FROM products p
        JOIN nodes n ON n.id = p.node_id
        WHERE p.id = ? AND n.is_active = 1
        LIMIT 1
    ");
    $stmt->execute([$product_id]);
    $node = $stmt->fetch();
    return $node ? [$node] : [];
}

// ─────────────────────────────────────────────────────────────
// VARIABLES D'ENVIRONNEMENT PAR DÉFAUT
// ─────────────────────────────────────────────────────────────

/**
 * 🔵 Ajoute les variables d'environnement manquantes par défaut
 * (FiveM, Hytale nécessitent certaines variables obligatoires)
 */
function getDefaultEnvVars(string $slug, array $env): array {
    $normalized = str_replace('-', '_', strtolower($slug));
    
    // FiveM : license requise
    if (str_starts_with($normalized, 'fivem')) {
        if (empty($env['FIVEM_LICENSE'])) {
            $env['FIVEM_LICENSE'] = '';
        }
    }
    
    // Hytale : variables requises
    if (str_starts_with($normalized, 'hytale')) {
        if (!isset($env['HYTALE_ACCEPT_EARLY_PLUGINS'])) $env['HYTALE_ACCEPT_EARLY_PLUGINS'] = 'false';
        if (!isset($env['DISABLE_SENTRY']))               $env['DISABLE_SENTRY']               = 'true';
        if (!isset($env['HYTALE_ALLOW_OP']))              $env['HYTALE_ALLOW_OP']              = 'true';
        if (!isset($env['INSTALL_SOURCEQUERY_PLUGIN']))   $env['INSTALL_SOURCEQUERY_PLUGIN']   = 'false';
    }
    
    return $env;
}

// ─────────────────────────────────────────────────────────────
// CRÉATION DE SERVEUR
// ─────────────────────────────────────────────────────────────

/**
 * 🔵 Crée un serveur sur le node correct directement
 * 
 * Logique :
 *   1. Détermine le node cible (1 ou 2) selon le slug
 *   2. Récupère panel_node_id + location_id depuis la BDD (table `nodes`)
 *   3. Crée le serveur sur Pterodactyl avec le bon node
 *   4. Retourne ['id', 'uuid', 'identifier']
 */
function createPanelServerWithAutoTransfer(string $panel_url, array $headers, array $product, int $panel_user_id, ?PDO $pdo = null): array {
    $slug = strtolower($product['slug'] ?? '');
    
    // 1. Déterminer le node cible (ID BDD : 1 ou 2)
    $target_node_db_id = getTargetNodeId($slug);
    
    // 2. Récupérer les infos du node depuis la table `nodes`
    $panel_node_id = null;
    $location_id   = (int) ($product['location_id'] ?? 1);
    
    if ($pdo) {
        $node_info = getNodeInfo($pdo, $target_node_db_id);
        if ($node_info) {
            $panel_node_id = (int) $node_info['panel_node_id'];
            $location_id   = (int) $node_info['location_id'];
            error_log("[NodeInfo] Node DB #{$target_node_db_id} ({$node_info['name']}) → Panel Node ID: {$panel_node_id}, Location: {$location_id}");
        }
    }
    
    // 3. Ajouter les variables d'environnement manquantes
    $env = getDefaultEnvVars($slug, $product['env'] ?? []);
    
    // 4. Construire le payload Pterodactyl
    $payload = [
        'name'         => $product['name'],
        'user'         => $panel_user_id,
        'egg'          => (int) $product['panel_egg_id'],
        'nest'         => (int) $product['panel_nest_id'],
        'docker_image' => $product['docker_image'],
        'startup'      => $product['startup'],
        'environment'  => $env,
        'limits' => [
            'memory' => (int) $product['ram'],
            'swap'   => 0,
            'disk'   => (int) $product['disk'],
            'io'     => 500,
            'cpu'    => (int) $product['cpu'],
        ],
        'feature_limits' => [
            'databases'   => (int) ($product['databases']   ?? 1),
            'allocations' => (int) ($product['allocations'] ?? 1),
            'backups'     => (int) ($product['backups']     ?? 1),
        ],
        'start_on_completion' => false,
    ];
    
    // 5. Spécifier le node de création
    if ($panel_node_id) {
        // Méthode recommandée : création directe sur le node
        $payload['node'] = $panel_node_id;
        error_log("[CreateServer] Création sur panel_node_id: {$panel_node_id}");
    } else {
        // Fallback : déploiement automatique via location
        $payload['deploy'] = [
            'locations'    => [$location_id],
            'dedicated_ip' => false,
            'port_range'   => [],
        ];
        error_log("[CreateServer] Fallback deploy sur location_id: {$location_id}");
    }
    
    error_log("[CreateServer] Création serveur '{$product['name']}' (slug: {$slug}) → Node DB: {$target_node_db_id}");
    
    // 6. Appel API Pterodactyl
    $server = pterodactylApi($panel_url, $headers, 'servers', $payload);

    if (!isset($server['attributes']['id'])) {
        error_log('Panel server creation error: ' . json_encode($server));
        die('<pre>SERVER ERROR: ' . htmlspecialchars(json_encode($server, JSON_PRETTY_PRINT)) . '</pre>');
    }

    $server_id  = (int) $server['attributes']['id'];
    $uuid       = $server['attributes']['uuid'];
    $identifier = $server['attributes']['identifier'];
    
    error_log("[CreateServer] ✅ Serveur {$server_id} créé avec succès (UUID: {$uuid})");

    return [
        'id'         => $server_id,
        'uuid'       => $uuid,
        'identifier' => $identifier,
    ];
}

/**
 * Alias pour compatibilité avec l'ancien code
 * Utilise la variable globale $pdo
 */
function createPanelServer(string $panel_url, array $headers, array $product, int $panel_user_id): array {
    global $pdo;
    return createPanelServerWithAutoTransfer($panel_url, $headers, $product, $panel_user_id, $pdo);
}
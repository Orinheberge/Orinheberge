<?php
/**
 * inc/db.php — Connexion PDO centralisée + chargement config/settings
 * Include une seule fois : require_once $_SERVER['DOCUMENT_ROOT'].'/inc/db.php';
 * Fournit : $pdo, $cfg[], $panel_url, $api_key_admin, $api_key_client, $headers_admin, $headers_client
 */

if (isset($pdo)) return; // déjà chargé

$pdo = new PDO(
    'mysql:host=localhost;dbname=s43_orinheberge;charset=utf8mb4',
    'root', '1504',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

// Charger settings
$cfg = [];
foreach ($pdo->query('SELECT `key`,`value` FROM settings') as $r) $cfg[$r['key']] = $r['value'];

// Charger aussi depuis extension_settings (pterodactyl)
$ext_row = $pdo->query("SELECT es.key, es.value FROM extension_settings es JOIN extensions e ON e.id=es.extension_id WHERE e.slug='pterodactyl'")->fetchAll();
foreach ($ext_row as $r) {
    if (!empty($r['value'])) $cfg[$r['key']] = $r['value']; // extension_settings a priorité
}

$panel_url      = $cfg['panel_url']      ?? '';
$api_key_admin  = $cfg['api_key_admin']  ?? '';
$api_key_client = $cfg['api_key_client'] ?? '';

$headers_admin  = [
    "Authorization: Bearer $api_key_admin",
    "Accept: application/vnd.pterodactyl.v1+json",
    "Content-Type: application/json"
];
$headers_client = [
    "Authorization: Bearer $api_key_client",
    "Accept: application/vnd.pterodactyl.v1+json",
    "Content-Type: application/json"
];

/**
 * Récupère un produit depuis la BDD (avec egg + node joints)
 * @param PDO $pdo
 * @param string $slug
 * @return array|null
 */
function getProductBySlug(PDO $pdo, string $slug): ?array {
    $stmt = $pdo->prepare('
        SELECT p.*,
               n.panel_node_id, n.location_id, n.name AS node_name,
               e.panel_egg_id, e.panel_nest_id, e.docker_image, e.startup AS egg_startup,
               e.env_vars AS egg_env_vars, e.name AS egg_name, e.icon AS egg_icon
        FROM products p
        JOIN nodes n ON n.id = p.node_id
        JOIN eggs  e ON e.id = p.egg_id
        WHERE p.slug = ? AND p.is_active = 1 AND n.is_active = 1 AND e.is_active = 1
        LIMIT 1
    ');
    $stmt->execute([$slug]);
    $product = $stmt->fetch();
    if (!$product) return null;

    // Fusionner env_vars de l'egg + env_override du produit
    $env = json_decode($product['egg_env_vars'] ?? '{}', true) ?: [];
    if (!empty($product['env_override'])) {
        $override = json_decode($product['env_override'], true) ?: [];
        $env = array_merge($env, $override);
    }
    $product['env'] = $env;

    // Startup : utiliser l'egg_startup
    $product['startup'] = $product['egg_startup'];

    return $product;
}

/**
 * Appel API Pterodactyl
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
    } elseif ($data !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    $res  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code === 204) return true;
    return $res ? json_decode($res, true) : null;
}

/**
 * Génère un mot de passe aléatoire
 */
function generatePassword(int $length = 12): string {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
    $p = '';
    for ($i = 0; $i < $length; $i++) $p .= $chars[random_int(0, strlen($chars) - 1)];
    return $p;
}

/**
 * Crée ou récupère un utilisateur panel
 */
function getOrCreatePanelUser(string $panel_url, array $headers, array $user, PDO $pdo): array {
    $search = pterodactylApi($panel_url, $headers, 'users?filter[email]=' . urlencode($user['email']));
    if (!empty($search['data'][0]['attributes']['id'])) {
        return ['id' => $search['data'][0]['attributes']['id'], 'pass' => null];
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
    return ['id' => $uid, 'pass' => $pass];
}

/**
 * Crée un serveur sur le panel depuis un produit BDD
 */
function createPanelServer(string $panel_url, array $headers, array $product, int $panel_user_id): array {
    $server = pterodactylApi($panel_url, $headers, 'servers', [
        'name'         => $product['name'],
        'user'         => $panel_user_id,
        'egg'          => $product['panel_egg_id'],
        'nest'         => $product['panel_nest_id'],
        'docker_image' => $product['docker_image'],
        'startup'      => $product['startup'],
        'environment'  => $product['env'],
        'deploy'       => [
            'locations'    => [$product['location_id'] ?? 1],
            'dedicated_ip' => false,
            'port_range'   => [],
        ],
        'limits' => [
            'memory' => $product['ram'],
            'swap'   => 0,
            'disk'   => $product['disk'],
            'io'     => 500,
            'cpu'    => $product['cpu'],
        ],
        'feature_limits' => [
            'databases'   => $product['databases']   ?? 1,
            'allocations' => $product['allocations'] ?? 1,
            'backups'     => $product['backups']     ?? 1,
        ],
        'start_on_completion' => false,
    ]);

    if (!isset($server['attributes']['id'])) {
        error_log('Panel server creation error: ' . json_encode($server));
        die('<pre>SERVER ERROR: ' . htmlspecialchars(json_encode($server, JSON_PRETTY_PRINT)) . '</pre>');
    }

    return [
        'id'         => $server['attributes']['id'],
        'uuid'       => $server['attributes']['uuid'],
        'identifier' => $server['attributes']['identifier'],
    ];
}

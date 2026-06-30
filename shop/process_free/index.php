<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: /login/login.php");
    exit();
}

/*
|--------------------------------------------------------------------------
| CONFIG
|--------------------------------------------------------------------------
*/

$panel_url = "https://panel.orinstone.deepstone.fr";
$api_key = "ptla_YKix8PexQDCZ7nIeexST3NXC2sFwQAoefDtOQBvJkbx";

$headers = [
    "Authorization: Bearer $api_key",
    "Content-Type: application/json",
    "Accept: application/vnd.pterodactyl.v1+json"
];

/*
|--------------------------------------------------------------------------
| DB
|--------------------------------------------------------------------------
*/

$pdo = new PDO(
    "mysql:host=127.0.0.1;port=3306;dbname=s43_orinheberge;charset=utf8mb4",
    "root",
    "1504",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

/*
|--------------------------------------------------------------------------
| PASSWORD GENERATOR
|--------------------------------------------------------------------------
*/

function generatePassword($length = 12) {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
    $pass = '';
    for ($i = 0; $i < $length; $i++) {
        $pass .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $pass;
}

/*
|--------------------------------------------------------------------------
| OFFRES
|--------------------------------------------------------------------------
*/
$offers = require __DIR__ . '/config/offers.php';



$type = $_GET['type'] ?? '';
if (!isset($offers[$type])) {
    die("Offre invalide");
}

$offer = $offers[$type];

/*
|--------------------------------------------------------------------------
| USER
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("SELECT * FROM users WHERE id=?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("User introuvable");
}

/*
|--------------------------------------------------------------------------
| LIMIT 3 SERVEURS / OFFRE
|--------------------------------------------------------------------------
*/

$check = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id=? AND service_name=?");
$check->execute([$_SESSION['user_id'], $offer['name']]);
$count = $check->fetchColumn();

if ($count >= 5) {
    die("❌ Limite 5 serveurs atteinte pour cette offre");
}

/*
|--------------------------------------------------------------------------
| CURL API
|--------------------------------------------------------------------------
*/

function api($url, $headers, $endpoint, $data = null) {
    $ch = curl_init($url . "/api/application/" . $endpoint);

    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false
    ]);

    if ($data) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $res = curl_exec($ch);
    curl_close($ch);

    return json_decode($res, true);
}



/*
|--------------------------------------------------------------------------
| CREATE OR FIND USER
|--------------------------------------------------------------------------
*/

$search = api($panel_url, $headers, "users?filter[email]=" . urlencode($user['email']));

if (!empty($search['data'][0]['attributes']['id'])) {
    $user_id = $search['data'][0]['attributes']['id'];
} else {
    $password = generatePassword();

    $created = api($panel_url, $headers, "users", [
        "email" => $user['email'],
        "username" => $user['username'] ?? "user".$user['id'],
        "first_name" => $user['firstname'] ?? "User",
        "last_name" => $user['lastname'] ?? "Account",
        "password" => $password
    ]);

    $user_id = $created['attributes']['id'] ?? null;

    if (!$user_id) {
        echo "<pre>";
        print_r($created);
        die("</pre>USER ERROR");
    }

    $pdo->prepare("UPDATE users SET panel_password=? WHERE id=?")
        ->execute([$password, $_SESSION['user_id']]);

    $_SESSION['panel_password'] = $password;
}

/*
|--------------------------------------------------------------------------
| CREATE SERVER
|--------------------------------------------------------------------------
*/

$server = api($panel_url, $headers, "servers", [
    "name" => $offer['name'],
    "user" => $user_id,
    "egg"  => $offer['egg'],
    "nest" => $offer['nest'],
    "docker_image" => $offer['image'],
    "startup" => $offer['startup'], 
    "environment" => $offer['env'],

    // Assignation directe au Node (1 ou 2 selon la configuration de l'offre)
    "node_id" => $offer['node'] ?? 1,

    "limits" => [
        "memory" => $offer['ram'],
        "swap" => 0,
        "disk" => $offer['disk'],
        "io" => 500,
        "cpu" => $offer['cpu']
    ],

    "feature_limits" => [
        "databases" => 1,
        "allocations" => 5,
        "backups" => 1
    ],
	"deploy" => [

        "locations" => [1],

        "dedicated_ip" => false,

        "port_range" => []

    ],

    // Demande à Pterodactyl de choisir une allocation automatique sur le nœud ciblé
    "allocation" => [
        "default" => null,
        "additional" => []
    ],
    
    // 🔒 SECURITE ANTI-CORRUPTION : Empêche le démarrage tant que le script d'installation n'a pas fini proprement
    "start_on_completion" => false 
]);

if (!isset($server['attributes']['id'])) {
    echo "<pre>";
    print_r($server);
    die("</pre>SERVER ERROR");
}

$server_id = $server['attributes']['id'];
$server_uuid = $server['attributes']['uuid']; 
$server_identifier = $server['attributes']['identifier']; 

/*
|--------------------------------------------------------------------------
| SAVE ORDER
|--------------------------------------------------------------------------
*/

$order_id = strtoupper(substr(md5(uniqid()), 0, 8));

$insert_stmt = $pdo->prepare("
    INSERT INTO orders (user_id, order_id, service_name, ram, disk, cpu, server_id, uuid, id_server_panel, created_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
");

$insert_stmt->execute([
    $_SESSION['user_id'],
    $order_id,
    $offer['name'],
    $offer['ram'],
    $offer['disk'],
    $offer['cpu'],
    $server_id,
    $server_uuid,        
    $server_identifier   
]);

/*
|--------------------------------------------------------------------------
| SUCCESS
|--------------------------------------------------------------------------
*/

$_SESSION['success_order_id'] = $order_id;
$_SESSION['success_email'] = $user['email'];
$_SESSION['success_server_id'] = $server_id;
$_SESSION['success_offer'] = $offer['name'];

if (isset($password)) {
    $_SESSION['success_panel_password'] = $password;
} else {
    $_SESSION['success_panel_password'] = $user['panel_password'] ?? null;
}

header("Location: /shop/success/?type=free");

/*
|--------------------------------------------------------------------------
| DISCORD WEBHOOK NOTIFICATION
|--------------------------------------------------------------------------
*/

$webhook_url = "https://discord.com/api/webhooks/1505677242527649872/jFoANIv3OKNtGMib4bViJ79ltRDsf0LJviq59yXwW5hrqZ0uTyU1Yx3nV88yy6rG2eA4";

// Structure du message stylisé (Embed Discord)
$webhook_data = [
    "username" => "Système de Commande",
    "embeds" => [
        [
            "title" => "📦 Nouvelle commande créée avec succès !",
            "color" => 3066993, // Couleur verte en décimal
            "fields" => [
                [
                    "name" => "ID de Commande",
                    "value" => "`" . $order_id . "`",
                    "inline" => true
                ],
                [
                    "name" => "Offre",
                    "value" => $offer['name'],
                    "inline" => true
                ],
                [
                    "name" => "Utilisateur (Email)",
                    "value" => $user['email'],
                    "inline" => false
                ],
                [
                    "name" => "Configuration",
                    "value" => "💾 " . $offer['disk'] . " MB | 🧠 " . $offer['ram'] . " MB | ⚡ CPU: " . $offer['cpu'] . "%",
                    "inline" => false
                ]
            ],
            "timestamp" => date("c")
        ]
    ]
];

// Envoi via cURL
$ch_discord = curl_init($webhook_url);
curl_setopt_array($ch_discord, [
    CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($webhook_data),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => true
]);

curl_exec($ch_discord);
curl_close($ch_discord);

exit();
<?php

/**
 * Appelle l'API Pterodactyl
 */
function api($url, $headers, $endpoint, $data = null) {
    $ch = curl_init($url . "/api/application/" . $endpoint);

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    if ($data) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $res = curl_exec($ch);
    return json_decode($res, true);
}

/**
 * Génère un mot de passe aléatoire
 */
function generatePassword($length = 12) {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
    $p = '';
    for ($i = 0; $i < $length; $i++) {
        $p .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $p;
}

/**
 * Crée ou récupère un utilisateur panel
 */
function getOrCreatePanelUser($panel_url, $headers, $user, $pdo) {
    $search = api($panel_url, $headers, "users?filter[email]=" . urlencode($user['email']));

    if (!empty($search['data'][0]['attributes']['id'])) {
        return ['id' => $search['data'][0]['attributes']['id'], 'pass' => null];
    }

    $pass = generatePassword();

    $created = api($panel_url, $headers, "users", [
        "email"      => $user['email'],
        "username"   => $user['username'] ?? "user" . $user['id'],
        "first_name" => $user['name']     ?? "User",
        "last_name"  => $user['lastname'] ?? "Account",
        "password"   => $pass
    ]);

    $user_id = $created['attributes']['id'] ?? null;

    if (!$user_id) {
        die("Panel user error");
    }

    $pdo->prepare("UPDATE users SET panel_password=? WHERE id=?")
        ->execute([$pass, $_SESSION['user_id']]);

    return ['id' => $user_id, 'pass' => $pass];
}

/**
 * Crée un serveur sur le panel (avec assignation du Node)
 */
function createPanelServer($panel_url, $headers, $offer, $user_id) {
    $server = api($panel_url, $headers, "servers", [
        "name"         => $offer['name'],
        "user"         => $user_id,
        "egg"          => $offer['egg'],
        "nest"         => $offer['nest'],
        "docker_image" => $offer['image'],
        "startup"      => $offer['startup'],
        "environment"  => $offer['env'],
        
        // Assignation directe au Node (1 ou 2 selon l'offre)
        "node_id"      => $offer['node'] ?? 1, 
        
        "limits" => [
            "memory" => $offer['ram'],
            "swap"   => 0,
            "disk"   => $offer['disk'],
            "io"     => 500,
            "cpu"    => $offer['cpu']
        ],
        "feature_limits" => [
            "databases"   => 1,
            "allocations" => 5,
            "backups"     => 1
        ],
        // Allocation automatique sur le nœud choisi
        "allocation" => [
            "default" => null,
            "additional" => []
        ],
        "start_on_completion" => true
    ]);

    if (!isset($server['attributes']['id'])) {
        echo "<pre>"; print_r($server); die("</pre>Server creation error");
    }

    return [
        'id'         => $server['attributes']['id'],
        'uuid'       => $server['attributes']['uuid'],
        'identifier' => $server['attributes']['identifier']
    ];
}
<?php
// Configuration de la base de données
$db_config = ['host' => 'localhost', 'name' => 's43_orinheberge', 'user' => 'root', 'pass' => '1504'];
try {
    $pdo = new PDO("mysql:host={$db_config['host']};dbname={$db_config['name']};charset=utf8mb4", $db_config['user'], $db_config['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (Exception $e) { 
    die("Erreur de connexion à la base de données."); 
}

// Liste des services avec leur méthode de vérification cible
$my_services = [
    'Site Web'              => ['host' => 'heberge.orinstone.deepstone.fr', 'type' => 'https'],
    'Panel de gestion'       => ['host' => 'panel.orinstone.deepstone.fr',   'type' => 'https'],
    'phpMyAdmin'            => ['host' => 'php.orinstone.deepstone.fr',     'type' => 'https'],
    'Node OrinStone'        => ['host' => 'node.orinstone.deepstone.fr',    'type' => 'node'],
    'Node DeepStone Global' => ['host' => 'node.deepstone.fr',          'type' => 'node']
];

$today = date('Y-m-d');

foreach ($my_services as $name => $info) {
    $is_online = 0;
    $host = $info['host'];

    if ($info['type'] === 'https') {
        // --- 1. SÉCURISÉ HTTPS (cURL) ---
        $url = "https://" . $host;
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_NOBODY, true); 
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Si le serveur répond avec un code HTTP valide
        if ($http_code >= 200 && $http_code < 405) {
            $is_online = 1;
        }
    } else {
        // --- 2. SANS HTTPS (Sockets) ---
        // On tente d'ouvrir une connexion sur le port 443 ou 80 pour valider que le serveur écoute
        $connection = @fsockopen($host, 443, $errno, $errstr, 4);
        if (is_resource($connection)) {
            $is_online = 1;
            fclose($connection);
        } else {
            $connection_alt = @fsockopen($host, 80, $errno, $errstr, 4);
            if (is_resource($connection_alt)) {
                $is_online = 1;
                fclose($connection_alt);
            }
        }
    }

    // Sauvegarde du résultat : si le service tombe une fois aujourd'hui, il reste marqué en incident (0)
    $stmt = $pdo->prepare("INSERT INTO service_uptime (service_name, check_date, is_online) 
                           VALUES (?, ?, ?) 
                           ON DUPLICATE KEY UPDATE is_online = LEAST(is_online, VALUES(is_online))");
    $stmt->execute([$name, $today, $is_online]);
}

echo "Vérification automatisée de l'infrastructure exécutée.";
<?php
/**
 * DEBUG — Liste tous les nests et eggs disponibles sur le panel Pterodactyl
 * Accès : https://heberge.orinstone.deepstone.fr/db/debug_eggs.php
 * SUPPRIMER CE FICHIER APRÈS UTILISATION
 */

$pdo = new PDO(
    "mysql:host=localhost;dbname=s43_orinheberge;charset=utf8mb4",
    "root", "1504",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$cfg = [];
foreach ($pdo->query('SELECT `key`, `value` FROM settings') as $row) $cfg[$row['key']] = $row['value'];
$panel_url = $cfg['panel_url'] ?? '';
$api_key   = $cfg['api_key_admin'] ?? '';

$headers = [
    "Authorization: Bearer $api_key",
    "Content-Type: application/json",
    "Accept: application/vnd.pterodactyl.v1+json"
];

function apiGet($url, $headers, $endpoint) {
    $ch = curl_init($url . "/api/application/" . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true);
}

// Récupérer tous les nests
$nests = apiGet($panel_url, $headers, "nests?include=eggs");

echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Debug Eggs</title>";
echo "<style>body{font-family:monospace;background:#0d0f14;color:#e5e7eb;padding:20px}";
echo "table{border-collapse:collapse;width:100%}th,td{border:1px solid #333;padding:8px 12px;text-align:left}";
echo "th{background:#1e2533;color:#38bdf8}tr:hover{background:#1a1f2e}.nest{background:#111827;color:#a78bfa;font-size:1.1em;padding:12px;margin:20px 0 0;border-left:3px solid #7c3aed}</style></head><body>";

echo "<h1 style='color:#38bdf8'>🔍 Nests & Eggs sur le Panel</h1>";
echo "<p style='color:#6b7280'>Panel : <b style='color:#f59e0b'>$panel_url</b></p>";

if (empty($nests['data'])) {
    echo "<p style='color:red'>❌ Impossible de récupérer les nests. Vérifie ta clé API admin.</p>";
    echo "<pre>" . json_encode($nests, JSON_PRETTY_PRINT) . "</pre>";
} else {
    foreach ($nests['data'] as $nest) {
        $nid   = $nest['attributes']['id'];
        $nname = htmlspecialchars($nest['attributes']['name']);
        echo "<div class='nest'>🗂 Nest ID: <b>$nid</b> — $nname</div>";
        echo "<table><tr><th>Egg ID</th><th>Egg Name</th><th>Docker Image</th><th>Startup</th></tr>";

        $eggs = $nest['attributes']['relationships']['eggs']['data'] ?? [];
        foreach ($eggs as $egg) {
            $eid     = $egg['attributes']['id'];
            $ename   = htmlspecialchars($egg['attributes']['name']);
            $eimage  = htmlspecialchars($egg['attributes']['docker_image']);
            $startup = htmlspecialchars(substr($egg['attributes']['startup'] ?? '', 0, 80)) . '...';
            echo "<tr><td><b style='color:#34d399'>$eid</b></td><td>$ename</td><td style='color:#6b7280;font-size:0.85em'>$eimage</td><td style='color:#6b7280;font-size:0.8em'>$startup</td></tr>";
        }
        echo "</table>";
    }
}

echo "<br><p style='color:#ef4444;font-weight:bold'>⚠️ SUPPRIME CE FICHIER APRÈS UTILISATION : /db/debug_eggs.php</p>";
echo "</body></html>";

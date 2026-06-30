<?php
// Exécution en ligne de commande (CLI) uniquement
if (php_sapi_name() !== 'cli') die('Accès refusé.');

$pdo = new PDO("mysql:host=localhost;dbname=s43_orinheberge;charset=utf8mb4", "root", "1504");

// Supprime ou annule les commandes 'pending' obsolètes de plus de 2 heures
$stmt = $pdo->prepare("DELETE FROM orders WHERE status = 'pending' AND created_at < NOW() - INTERVAL 2 HOUR");
$stmt->execute();

echo "Nettoyage effectué : " . $stmt->rowCount() . " commande(s) expirée(s) supprimée(s).\n";
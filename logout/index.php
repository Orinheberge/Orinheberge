<?php
session_start();

// On vide les données de session
$_SESSION = array();

// On détruit la session sur le serveur
session_destroy();

// Redirection vers la racine (index.php)
header("Location: /index.php");
exit();
?>
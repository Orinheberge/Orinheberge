<?php
/**
 * OrinHeberge — Déconnexion web (avec redirection)
 * URL : /logout/
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/db.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/LogoutService.php';

// Utiliser le service partagé
$logoutService = new LogoutService($pdo);
$result = $logoutService->performLogout();

// Déterminer la redirection
$redirect = $logoutService->getRedirectUrl();

// Empêcher la mise en cache de la redirection
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Redirection finale
header("Location: " . $redirect);
exit();
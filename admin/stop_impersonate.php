<?php
/**
 * stop_impersonate.php — Retour au compte admin après impersonation client
 */
session_start();

if (!empty($_SESSION['admin_impersonating'])) {
    $admin_id    = (int)$_SESSION['admin_impersonating'];
    $admin_pseudo = $_SESSION['admin_pseudo'] ?? 'Admin';

    // Restaurer la session admin
    $_SESSION['user_id']  = $admin_id;
    $_SESSION['username'] = $admin_pseudo;
    unset($_SESSION['admin_impersonating'], $_SESSION['admin_pseudo'], $_SESSION['avatar']);
}

header('Location: /admin/?view=clients');
exit();

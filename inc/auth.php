<?php
/**
 * OrinHeberge — Helpers d'authentification
 * À inclure en haut de chaque page protégée
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/lang.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/db.php';

// Variables globales disponibles partout
$is_logged_in = isset($_SESSION['user_id']);
$current_user = null;

if ($is_logged_in) {
    try {
        $stmt = $pdo->prepare("SELECT id, firstname, lastname, email, pseudo, avatar, is_admin FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $current_user = $stmt->fetch();
        
        if (!$current_user) {
            // Utilisateur supprimé, déconnecter
            session_destroy();
            $is_logged_in = false;
        } else {
            $_SESSION['username']   = $current_user['pseudo'] ?? $current_user['firstname'];
            $_SESSION['avatar']     = $current_user['avatar'];
            $_SESSION['is_admin']   = (bool)($current_user['is_admin'] ?? false);
        }
    } catch (Exception $e) {
        error_log('[Auth] Erreur récupération user : ' . $e->getMessage());
    }
}

/**
 * Force l'authentification (redirige si pas connecté)
 */
function requireAuth($redirectTo = '/login/') {
    global $is_logged_in;
    if (!$is_logged_in) {
        header('Location: ' . $redirectTo);
        exit;
    }
}

/**
 * Force l'admin (redirige si pas admin)
 */
function requireAdmin($redirectTo = '/') {
    requireAuth();
    if (empty($_SESSION['is_admin'])) {
        header('Location: ' . $redirectTo);
        exit;
    }
}

/**
 * Génère un token CSRF
 */
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Vérifie un token CSRF
 */
function csrf_verify($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
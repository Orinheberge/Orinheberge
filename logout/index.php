<?php
/**
 * OrinHeberge — Déconnexion sécurisée
 * Nettoie : session, cookies, remember_token, logs l'action
 */

// ═══════════════════════════════════════════
// 1. DÉMARRER LA SESSION POUR POUVOIR LA NETTOYER
// ═══════════════════════════════════════════
session_start();

// Récupérer l'ID utilisateur AVANT destruction (pour le log)
$userId = $_SESSION['user_id'] ?? null;
$username = $_SESSION['username'] ?? 'Inconnu';

// ═══════════════════════════════════════════
// 2. LOG DE DÉCONNEXION (optionnel)
// ═══════════════════════════════════════════
if ($userId) {
    try {
        require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/db.php';
        
        // Log dans l'historique de connexion
        $stmt = $pdo->prepare("
            INSERT INTO user_login_history 
            (user_id, ip_address, user_agent, auth_method, status, created_at)
            VALUES (?, ?, ?, 'logout', 'success', NOW())
        ");
        $stmt->execute([
            $userId,
            $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500)
        ]);
        
        error_log("[Logout] User ID $userId ($username) déconnecté | IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        
    } catch (Exception $e) {
        error_log('[Logout] Erreur log: ' . $e->getMessage());
    }
}

// ═══════════════════════════════════════════
// 3. SUPPRIMER LE COOKIE "REMEMBER ME"
// ═══════════════════════════════════════════
if (isset($_COOKIE['remember_token'])) {
    // Supprimer de la BDD
    if ($userId) {
        try {
            $pdo->prepare("DELETE FROM remember_tokens WHERE user_id = ?")
                ->execute([$userId]);
        } catch (Exception $e) {
            // Table peut ne pas exister
        }
    }
    
    // Supprimer le cookie côté client
    setcookie('remember_token', '', time() - 3600, '/', '', 
        isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 
        true
    );
    unset($_COOKIE['remember_token']);
}

// ═══════════════════════════════════════════
// 4. SUPPRIMER TOUS LES AUTRES COOKIES CUSTOM
// ═══════════════════════════════════════════
$cookiesToClear = ['csrf_token', 'theme', 'language']; // Ajoute tes cookies ici
foreach ($cookiesToClear as $cookieName) {
    if (isset($_COOKIE[$cookieName])) {
        setcookie($cookieName, '', time() - 3600, '/', '');
        unset($_COOKIE[$cookieName]);
    }
}

// ═══════════════════════════════════════════
// 5. DÉTRUIRE COMPLÈTEMENT LA SESSION
// ═══════════════════════════════════════════

// 5.1 Vider toutes les variables de session
$_SESSION = [];

// 5.2 Supprimer le cookie de session côté client
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), 
        '', 
        time() - 42000,
        $params["path"], 
        $params["domain"],
        $params["secure"], 
        $params["httponly"]
    );
}

// 5.3 Détruire la session sur le serveur
if (session_status() === PHP_SESSION_ACTIVE) {
    session_destroy();
}

// 5.4 Régénérer l'ID de session pour la prochaine visite
session_start();
session_regenerate_id(true);

// ═══════════════════════════════════════════
// 6. REDIRECTION
// ═══════════════════════════════════════════

// Déterminer la page de redirection
$redirect = '/';
$referer = $_SERVER['HTTP_REFERER'] ?? '';

// Si on vient d'une page admin, rediriger vers login
if (strpos($referer, '/admin/') !== false) {
    $redirect = '/login/';
}
// Si on vient d'une page client, rediriger vers home
elseif (strpos($referer, '/client/') !== false) {
    $redirect = '/';
}

// Ajouter un message flash via query string (optionnel)
$message = $_GET['msg'] ?? 'logout_success';
$redirect .= ($redirect === '/' ? '?msg=' . $message : '&msg=' . $message);

// Empêcher la mise en cache de la redirection
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Redirection finale
header("Location: " . $redirect);
exit();
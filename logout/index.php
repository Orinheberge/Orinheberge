<?php
/**
 * OrinHeberge — Déconnexion web (anti-boucle)
 */

// ═══════════════════════════════════════════
// 🛡️ PROTECTION ANTI-BOUCLE
// ═══════════════════════════════════════════
$attempts = (int)($_COOKIE['logout_attempts'] ?? 0);
if ($attempts > 2) {
    // Casser la boucle : forcer redirection vers login SANS message
    setcookie('logout_attempts', '', time() - 3600, '/');
    header("Location: /login/?forced=1");
    exit();
}
setcookie('logout_attempts', $attempts + 1, time() + 60, '/'); // Expire dans 1 min

// ═══════════════════════════════════════════
// 1. LOG + NETTOYAGE
// ═══════════════════════════════════════════
session_start();

$userId = $_SESSION['user_id'] ?? null;
$username = $_SESSION['username'] ?? 'Inconnu';

if ($userId) {
    try {
        require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/db.php';
        
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
        
        error_log("[Logout] User ID $userId ($username) | IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        
        // Supprimer remember token
        $pdo->prepare("DELETE FROM remember_tokens WHERE user_id = ?")->execute([$userId]);
        
        // Supprimer activité
        $pdo->prepare("DELETE FROM user_activity WHERE user_id = ?")->execute([$userId]);
        
    } catch (Throwable $e) {
        error_log('[Logout] Error: ' . $e->getMessage());
    }
}

// ═══════════════════════════════════════════
// 2. SUPPRIMER LES COOKIES
// ═══════════════════════════════════════════
$cookiesToClear = ['remember_token', 'csrf_token', 'theme', 'language', 'logout_attempts'];
foreach ($cookiesToClear as $cookieName) {
    if (isset($_COOKIE[$cookieName])) {
        setcookie($cookieName, '', time() - 3600, '/', '');
        setcookie($cookieName, '', time() - 3600, '/', '', true, true);
        unset($_COOKIE[$cookieName]);
    }
}

// ═══════════════════════════════════════════
// 3. DÉTRUIRE LA SESSION COMPLÈTE
// ═══════════════════════════════════════════
$_SESSION = [];

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

if (session_status() === PHP_SESSION_ACTIVE) {
    session_destroy();
    session_write_close(); // Force l'écriture
}

// ⚠️ PAS de session_start() ici ! On laisse la prochaine page créer une nouvelle session

// ═══════════════════════════════════════════
// 4. REDIRECTION FINALE (toujours vers /login/)
// ═══════════════════════════════════════════
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
header("Location: /login/?logout=success");
exit();
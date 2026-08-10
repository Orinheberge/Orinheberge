<?php
/**
 * OrinHeberge — Service de déconnexion (logique commune)
 * Utilisé par : /logout/ (web) et /api/auth/logout.php (JSON)
 */

class LogoutService {
    private $pdo;
    
    public function __construct($pdo = null) {
        $this->pdo = $pdo;
    }
    
    /**
     * Effectue la déconnexion complète
     * @return array Informations sur la déconnexion
     */
    public function performLogout() {
        // Démarrer session si pas déjà fait
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Récupérer infos user AVANT destruction (pour le log)
        $userId = $_SESSION['user_id'] ?? null;
        $username = $_SESSION['username'] ?? 'unknown';
        $wasLoggedIn = !empty($userId);
        
        $result = [
            'was_logged_in' => $wasLoggedIn,
            'user_id' => $userId,
            'username' => $username,
            'logged' => false,
            'remember_token_cleared' => false,
            'cookies_cleared' => []
        ];
        
        // ═══════════════════════════════════════════
        // 1. LOG DE DÉCONNEXION
        // ═══════════════════════════════════════════
        if ($userId && $this->pdo) {
            try {
                $stmt = $this->pdo->prepare("
                    INSERT INTO user_login_history 
                    (user_id, ip_address, user_agent, auth_method, status, created_at)
                    VALUES (?, ?, ?, 'logout', 'success', NOW())
                ");
                $stmt->execute([
                    $userId,
                    $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
                    substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500)
                ]);
                
                $result['logged'] = true;
                error_log("[Logout] User ID $userId ($username) déconnecté | IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
                
            } catch (Throwable $e) {
                error_log('[Logout] Erreur log: ' . $e->getMessage());
            }
        }
        
        // ═══════════════════════════════════════════
        // 2. SUPPRIMER LE COOKIE REMEMBER TOKEN
        // ═══════════════════════════════════════════
        if (isset($_COOKIE['remember_token'])) {
            if ($userId && $this->pdo) {
                try {
                    $this->pdo->prepare("DELETE FROM remember_tokens WHERE user_id = ?")
                        ->execute([$userId]);
                } catch (Throwable $e) {
                    // Table peut ne pas exister
                }
            }
            
            setcookie('remember_token', '', time() - 3600, '/', '', 
                isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 
                true
            );
            unset($_COOKIE['remember_token']);
            $result['remember_token_cleared'] = true;
        }
        
        // ═══════════════════════════════════════════
        // 3. SUPPRIMER AUTRES COOKIES CUSTOM
        // ═══════════════════════════════════════════
        $cookiesToClear = ['csrf_token', 'theme', 'language'];
        foreach ($cookiesToClear as $cookieName) {
            if (isset($_COOKIE[$cookieName])) {
                setcookie($cookieName, '', time() - 3600, '/', '');
                unset($_COOKIE[$cookieName]);
                $result['cookies_cleared'][] = $cookieName;
            }
        }
        
        // ═══════════════════════════════════════════
        // 4. DÉTRUIRE COMPLÈTEMENT LA SESSION
        // ═══════════════════════════════════════════
        
        // 4.1 Vider toutes les variables de session
        $_SESSION = [];
        
        // 4.2 Supprimer le cookie de session côté client
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
        
        // 4.3 Détruire la session sur le serveur
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        
        // 4.4 Régénérer l'ID de session pour la prochaine visite
        session_start();
        session_regenerate_id(true);
        
        return $result;
    }
    
    /**
     * Détermine la page de redirection intelligente
     */
    public function getRedirectUrl($referer = null, $fallbackMessage = 'logout_success') {
        $referer = $referer ?? ($_SERVER['HTTP_REFERER'] ?? '');
        
        $redirect = '/';
        
        // Si on vient d'une page admin, rediriger vers login
        if (strpos($referer, '/admin/') !== false) {
            $redirect = '/login/';
        }
        // Si on vient d'une page client, rediriger vers home
        elseif (strpos($referer, '/client/') !== false) {
            $redirect = '/';
        }
        
        // Ajouter un message flash via query string
        $message = $_GET['msg'] ?? $fallbackMessage;
        $separator = (strpos($redirect, '?') !== false) ? '&' : '?';
        $redirect .= $separator . 'msg=' . urlencode($message);
        
        return $redirect;
    }
}
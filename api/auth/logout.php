<?php
/**
 * OrinHeberge — API de déconnexion (JSON)
 * URL : /api/auth/logout.php
 * Méthode : POST uniquement
 * Utilisée par : AJAX depuis le chat, profil, etc.
 */

if (ob_get_level()) ob_end_clean();
ob_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Accepter uniquement POST (méthode sécurisée pour logout)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false, 
        'error' => 'method_not_allowed',
        'message' => 'Utilisez la méthode POST'
    ]);
    exit;
}

try {
    require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/db.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/LogoutService.php';
    
    // Utiliser le service partagé
    $logoutService = new LogoutService($pdo);
    $result = $logoutService->performLogout();
    
    // Réponse JSON
    if (ob_get_length()) ob_clean();
    echo json_encode([
        'success' => true,
        'message' => $result['was_logged_in'] 
            ? 'Déconnecté avec succès' 
            : 'Aucune session active',
        'was_logged_in' => $result['was_logged_in'],
        'user_id' => $result['user_id'],
        'logged' => $result['logged'],
        'remember_token_cleared' => $result['remember_token_cleared'],
        'cookies_cleared' => $result['cookies_cleared'],
        'redirect' => '/login/'
    ]);
    
} catch (Throwable $e) {
    if (ob_get_length()) ob_clean();
    http_response_code(500);
    error_log('[API Logout] Fatal: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'server_error',
        'message' => 'Erreur serveur lors de la déconnexion'
    ]);
}
exit;
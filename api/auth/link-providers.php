<?php
if (ob_get_level()) ob_end_clean();
header('Content-Type: application/json; charset=utf-8');

try {
    session_start();
    
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Non autorisé']);
        exit;
    }
    
    require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/db.php';
    require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/AuthService.php';
    
    $auth = new AuthService($pdo);
    $providers = $auth->getUserOAuthProviders($_SESSION['user_id']);
    
    echo json_encode([
        'success' => true,
        'providers' => $providers,
        'count' => count($providers)
    ]);
    
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
exit;
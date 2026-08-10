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
    
    $data = json_decode(file_get_contents('php://input'), true);
    $provider = $data['provider'] ?? '';
    
    if (!in_array($provider, ['discord', 'google'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Provider invalide']);
        exit;
    }
    
    $auth = new AuthService($pdo);
    $result = $auth->unlinkOAuthProvider($_SESSION['user_id'], $provider);
    
    echo json_encode($result);
    
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
exit;
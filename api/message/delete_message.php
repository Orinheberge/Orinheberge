<?php
if (ob_get_level()) ob_end_clean();
ob_start();

header('Content-Type: application/json; charset=utf-8');

try {
    session_start();
    
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Non autorisé']);
        exit;
    }
    
    require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/db.php';
    
    $data = json_decode(file_get_contents('php://input'), true);
    $messageId = (int)($data['message_id'] ?? 0);
    
    if (!$messageId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'ID message invalide']);
        exit;
    }
    
    // Vérifier que le message existe
    $stmt = $pdo->prepare("SELECT user_id FROM chat_messages WHERE id = ?");
    $stmt->execute([$messageId]);
    $message = $stmt->fetch();
    
    if (!$message) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Message introuvable']);
        exit;
    }
    
    // Vérifier permissions : auteur OU admin
    $isAuthor = $message['user_id'] == $_SESSION['user_id'];
    $isAdmin = !empty($_SESSION['is_admin']);
    
    if (!$isAuthor && !$isAdmin) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Permission refusée']);
        exit;
    }
    
    // Supprimer le message (cascade supprimera réactions et pins)
    $pdo->beginTransaction();
    
    try {
        $pdo->prepare("DELETE FROM message_reactions WHERE message_id = ?")->execute([$messageId]);
        $pdo->prepare("DELETE FROM pinned_messages WHERE message_id = ?")->execute([$messageId]);
        $pdo->prepare("DELETE FROM chat_messages WHERE id = ?")->execute([$messageId]);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message_id' => $messageId,
            'deleted_by_admin' => !$isAuthor && $isAdmin
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
    
} catch (Throwable $e) {
    if (ob_get_length()) ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
exit;
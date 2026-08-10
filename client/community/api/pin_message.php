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
    $action = $data['action'] ?? 'toggle'; // 'toggle', 'pin', 'unpin'
    
    if (!$messageId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'ID message invalide']);
        exit;
    }
    
    // Vérifier que le message existe et récupérer le canal
    $stmt = $pdo->prepare("SELECT id, channel FROM chat_messages WHERE id = ?");
    $stmt->execute([$messageId]);
    $message = $stmt->fetch();
    
    if (!$message) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Message introuvable']);
        exit;
    }
    
    // Vérifier permissions : auteur OU admin
    $stmt = $pdo->prepare("SELECT user_id FROM chat_messages WHERE id = ?");
    $stmt->execute([$messageId]);
    $msgAuthor = $stmt->fetch()['user_id'];
    
    $isAuthor = $msgAuthor == $_SESSION['user_id'];
    $isAdmin = !empty($_SESSION['is_admin']);
    
    if (!$isAuthor && !$isAdmin) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Permission refusée']);
        exit;
    }
    
    // Vérifier si déjà épinglé
    $stmt = $pdo->prepare("SELECT id FROM pinned_messages WHERE message_id = ?");
    $stmt->execute([$messageId]);
    $isPinned = $stmt->fetch();
    
    if ($action === 'toggle') {
        $action = $isPinned ? 'unpin' : 'pin';
    }
    
    if ($action === 'pin' && !$isPinned) {
        $pdo->prepare("
            INSERT INTO pinned_messages (message_id, channel, pinned_by, pinned_at)
            VALUES (?, ?, ?, NOW())
        ")->execute([$messageId, $message['channel'], $_SESSION['user_id']]);
        $result = 'pinned';
    } elseif ($action === 'unpin' && $isPinned) {
        $pdo->prepare("DELETE FROM pinned_messages WHERE message_id = ?")->execute([$messageId]);
        $result = 'unpinned';
    } else {
        $result = $isPinned ? 'already_pinned' : 'already_unpinned';
    }
    
    echo json_encode([
        'success' => true,
        'message_id' => $messageId,
        'result' => $result,
        'is_pinned' => $result === 'pinned' || $result === 'already_pinned'
    ]);
    
} catch (Throwable $e) {
    if (ob_get_length()) ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
exit;
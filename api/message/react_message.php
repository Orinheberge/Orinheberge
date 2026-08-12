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
    $emoji = trim($data['emoji'] ?? '');
    
    if (!$messageId || empty($emoji)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Paramètres manquants']);
        exit;
    }
    
    // Valider emoji (max 50 caractères, accepter unicode)
    if (mb_strlen($emoji) > 50) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Emoji invalide']);
        exit;
    }
    
    // Vérifier que le message existe
    $stmt = $pdo->prepare("SELECT id FROM chat_messages WHERE id = ?");
    $stmt->execute([$messageId]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Message introuvable']);
        exit;
    }
    
    // Toggle : si déjà réagi → retirer, sinon → ajouter
    $stmt = $pdo->prepare("
        SELECT id FROM message_reactions 
        WHERE message_id = ? AND user_id = ? AND emoji = ?
    ");
    $stmt->execute([$messageId, $_SESSION['user_id'], $emoji]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        // Retirer la réaction
        $pdo->prepare("DELETE FROM message_reactions WHERE id = ?")->execute([$existing['id']]);
        $action = 'removed';
    } else {
        // Ajouter la réaction
        $pdo->prepare("
            INSERT INTO message_reactions (message_id, user_id, emoji, created_at)
            VALUES (?, ?, ?, NOW())
        ")->execute([$messageId, $_SESSION['user_id'], $emoji]);
        $action = 'added';
    }
    
    // Récupérer toutes les réactions du message (pour update UI)
    $stmt = $pdo->prepare("
        SELECT emoji, COUNT(*) as count, 
               GROUP_CONCAT(u.pseudo ORDER BY u.pseudo SEPARATOR ', ') as users
        FROM message_reactions r
        JOIN users u ON r.user_id = u.id
        WHERE r.message_id = ?
        GROUP BY emoji
        ORDER BY count DESC
    ");
    $stmt->execute([$messageId]);
    $reactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'action' => $action,
        'message_id' => $messageId,
        'reactions' => $reactions
    ]);
    
} catch (Throwable $e) {
    if (ob_get_length()) ob_clean();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
exit;
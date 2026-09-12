<?php
if (ob_get_level()) ob_end_clean();
ob_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (ob_get_length()) ob_clean();
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'PHP Fatal: ' . $error['message'],
            'file' => basename($error['file']),
            'line' => $error['line']
        ]);
    }
});

try {
    session_start();
    
    if (!isset($_SESSION['user_id'])) {
        if (ob_get_length()) ob_clean();
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Non autorisé']);
        exit;
    }
    
    require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/db.php';
    
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        throw new Exception('PDO non initialisé');
    }
    
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);
    
    if (!$data) {
        throw new Exception('JSON invalide: ' . substr($rawInput, 0, 100));
    }
    
    $name = trim($data['name'] ?? '');
    $description = trim($data['description'] ?? '');
    $owner_id = (int)$_SESSION['user_id'];
    
    // Validation des données
    if (empty($name)) {
        if (ob_get_length()) ob_clean();
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Le nom du serveur est obligatoire']);
        exit;
    }
    
    if (mb_strlen($name) < 3 || mb_strlen($name) > 100) {
        if (ob_get_length()) ob_clean();
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Le nom doit contenir entre 3 et 100 caractères']);
        exit;
    }
    
    if (!empty($description) && mb_strlen($description) > 255) {
        if (ob_get_length()) ob_clean();
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'La description ne peut pas dépasser 255 caractères']);
        exit;
    }
    
    // Insertion en base de données
    // Le trigger SQL s'occupera automatiquement d'ajouter le créateur comme 'owner' dans commu_server_members
    $stmt = $pdo->prepare("
        INSERT INTO commu_servers (name, description, owner_id)
        VALUES (?, ?, ?)
    ");
    
    $stmt->execute([
        $name,
        !empty($description) ? $description : null,
        $owner_id
    ]);
    
    $serverId = (int)$pdo->lastInsertId();
    
    // Récupération des données du serveur créé pour les renvoyer au frontend
    $stmt = $pdo->prepare("
        SELECT id, name, description, icon, owner_id, created_at
        FROM commu_servers
        WHERE id = ?
    ");
    $stmt->execute([$serverId]);
    $newServer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$newServer) {
        throw new Exception('Serveur créé mais non récupérable (ID: ' . $serverId . ')');
    }
    
    if (ob_get_length()) ob_clean();
    echo json_encode([
        'success' => true, 
        'server' => $newServer
    ]);
    
} catch (Throwable $e) {
    if (ob_get_length()) ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ]);
}
exit;
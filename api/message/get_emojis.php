<?php
if (ob_get_level()) ob_end_clean();
ob_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=300'); // Cache 5min (emojis changent rarement)

try {
    $emojiDir = $_SERVER['DOCUMENT_ROOT'] . '/assets/img/emoji-orinheberge/';
    $emojis = [];
    
    if (is_dir($emojiDir)) {
        $files = scandir($emojiDir);
        foreach ($files as $file) {
            if (preg_match('/\.(png|jpg|jpeg|gif|webp)$/i', $file)) {
                $name = pathinfo($file, PATHINFO_FILENAME);
                // Nettoyer le nom pour le shortcode
                $cleanName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $name);
                $emojis[] = [
                    'name' => $name,
                    'url' => '/assets/img/emoji-orinheberge/' . rawurlencode($file),
                    'shortcode' => ':' . $cleanName . ':'
                ];
            }
        }
    } else {
        // Dossier inexistant : pas une erreur, juste vide
    }
    
    if (ob_get_length()) ob_clean();
    echo json_encode([
        'success' => true,
        'emojis' => $emojis,
        'count' => count($emojis),
        'dir_exists' => is_dir($emojiDir)
    ]);
    
} catch (Throwable $e) {
    if (ob_get_length()) ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
exit;
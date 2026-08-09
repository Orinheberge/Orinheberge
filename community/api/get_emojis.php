<?php
header('Content-Type: application/json');

$emojiDir = $_SERVER['DOCUMENT_ROOT'] . '/assets/emoji-orinheberge/';
$emojis = [];

if (is_dir($emojiDir)) {
    $files = scandir($emojiDir);
    foreach ($files as $file) {
        if (preg_match('/\.(png|jpg|jpeg|gif|webp)$/i', $file)) {
            $name = pathinfo($file, PATHINFO_FILENAME);
            $emojis[] = [
                'name' => $name,
                'url' => '/assets/emoji-orinheberge/' . $file,
                'shortcode' => ':' . $name . ':'
            ];
        }
    }
}

echo json_encode(['success' => true, 'emojis' => $emojis]);
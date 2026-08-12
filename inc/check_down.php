<?php
/**
 * OrinHeberge — Vérification mode maintenance (down/up)
 * Inclure APRÈS session_start() dans les pages publiques
 */

$downFile = $_SERVER['DOCUMENT_ROOT'] . '/storage/framework/down';
$secretFile = $_SERVER['DOCUMENT_ROOT'] . '/storage/framework/down-secret';

if (file_exists($downFile)) {
    $payload = json_decode(file_get_contents($downFile), true) ?: [];
    
    // 1. Vérifier si admin connecté → bypass automatique
    if (!empty($_SESSION['is_admin'])) {
        // Admin : on laisse passer
        return;
    }
    
    // 2. Vérifier le secret dans l'URL
    $secret = $payload['secret'] ?? (file_exists($secretFile) ? file_get_contents($secretFile) : null);
    if ($secret && isset($_GET['secret']) && $_GET['secret'] === $secret) {
        // Créer un cookie de bypass pour 1h
        setcookie('orin_bypass', hash('sha256', $secret), time() + 3600, '/');
        return;
    }
    
    // 3. Vérifier le cookie de bypass
    if (!empty($_COOKIE['orin_bypass']) && $secret && $_COOKIE['orin_bypass'] === hash('sha256', $secret)) {
        return;
    }
    
    // 4. Vérifier IP whitelist
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? '';
    $allowed = $payload['allowed'] ?? [];
    if (in_array($clientIp, $allowed)) {
        return;
    }
    
    // 5. Redirection custom
    if (!empty($payload['redirect'])) {
        header('Location: ' . $payload['redirect']);
        exit;
    }
    
    // 6. Afficher page maintenance
    http_response_code($payload['status'] ?? 503);
    header('Retry-After: ' . ($payload['retry'] ?? 60));
    
    $refresh = $payload['refresh'] ?? 0;
    if ($refresh > 0) {
        header("Refresh: $refresh");
    }
    
    $message = htmlspecialchars($payload['message'] ?? 'Site en maintenance');
    $retry = $payload['retry'] ?? 60;
    
    die('<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Maintenance — OrinHeberge</title>
' . ($refresh > 0 ? "<meta http-equiv=\"refresh\" content=\"$refresh\">" : '') . '
<style>
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;background:#0f172a;color:#e2e8f0;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;}
.container{max-width:500px;text-align:center;padding:40px;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);border-radius:20px;backdrop-filter:blur(14px);}
.icon{font-size:4rem;margin-bottom:20px;}
h1{font-size:2rem;margin-bottom:15px;color:white;}
p{color:#94a3b8;margin-bottom:30px;line-height:1.6;}
.retry{display:inline-block;padding:10px 20px;background:rgba(56,189,248,0.1);color:#38bdf8;border-radius:10px;font-size:0.9rem;font-weight:600;}
</style>
</head>
<body>
<div class="container">
    <div class="icon">🛠️</div>
    <h1>Maintenance en cours</h1>
    <p>' . $message . '</p>
    <div class="retry">Retour prévu dans ~' . $retry . 's</div>
</div>
</body>
</html>');
}
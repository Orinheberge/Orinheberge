<?php
// 🔍 DEBUG MODE
error_log('[OAuth Discord] === DÉBUT CALLBACK ===');
error_log('[OAuth Discord] GET params: ' . json_encode($_GET));
error_log('[OAuth Discord] URI: ' . $_SERVER['REQUEST_URI']);
error_log('[OAuth Discord] HTTPS: ' . ($_SERVER['HTTPS'] ?? 'off'));

// Démarrer la session AVANT tout
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
error_log('[OAuth Discord] Session ID: ' . session_id());
error_log('[OAuth Discord] Session data: ' . json_encode($_SESSION));

require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/AuthService.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/OAuthProvider.php';

// Vérifier si Discord a renvoyé une erreur
if (isset($_GET['error'])) {
    error_log('[OAuth Discord] ❌ Erreur Discord: ' . $_GET['error']);
    header('Location: /login/?error=oauth_cancelled&discord_error=' . $_GET['error']);
    exit;
}

$code  = $_GET['code']  ?? null;
$state = $_GET['state'] ?? null;

error_log('[OAuth Discord] Code reçu: ' . ($code ? substr($code, 0, 20) . '...' : 'NULL'));
error_log('[OAuth Discord] State reçu: ' . ($state ?? 'NULL'));

if (!$code || !$state) {
    error_log('[OAuth Discord] ❌ Code ou state manquant');
    error_log('[OAuth Discord] $_GET complet: ' . print_r($_GET, true));
    
    // Afficher une page debug au lieu de rediriger
    echo '<pre style="background:#111;color:#0f0;padding:20px;font-family:monospace;">';
    echo "<h2>🔍 DEBUG OAuth Discord</h2>";
    echo "<strong>GET params:</strong>\n";
    print_r($_GET);
    echo "\n<strong>Session:</strong>\n";
    print_r($_SESSION);
    echo "\n<strong>Server:</strong>\n";
    echo "REQUEST_URI: " . $_SERVER['REQUEST_URI'] . "\n";
    echo "QUERY_STRING: " . ($_SERVER['QUERY_STRING'] ?? 'none') . "\n";
    echo "HTTPS: " . ($_SERVER['HTTPS'] ?? 'off') . "\n";
    echo '</pre>';
    echo '<p><a href="/api/auth/discord.php">🔄 Réessayer</a> | <a href="/login/">Retour login</a></p>';
    exit;
}

// ... reste du code original ...
try {
    $config = require $_SERVER['DOCUMENT_ROOT'] . '/config/oauth.php';
    error_log('[OAuth Discord] Config chargée: ' . json_encode([
        'client_id' => substr($config['discord']['client_id'], 0, 10) . '...',
        'redirect_uri' => $config['discord']['redirect_uri']
    ]));
    
    $discord = new OAuthProvider('discord', $config['discord']);
    $oauthData = $discord->handleCallback($code, $state);
    
    error_log('[OAuth Discord] ✅ Données OAuth: ' . json_encode([
        'id' => $oauthData['id'] ?? null,
        'email' => $oauthData['email'] ?? null,
        'username' => $oauthData['username'] ?? null
    ]));
    
    $auth = new AuthService($pdo);
    $result = $auth->loginWithOAuth('discord', $oauthData);
    
    if ($result['success']) {
        error_log('[OAuth Discord] ✅ Login réussi, user ID: ' . $result['user']['id']);
        header('Location: /client/');
    } else {
        error_log('[OAuth Discord] ❌ Login échoué: ' . json_encode($result));
        header('Location: /login/?error=oauth_failed&reason=' . ($result['error'] ?? 'unknown'));
    }
} catch (Exception $e) {
    error_log('[OAuth Discord] ❌ Exception: ' . $e->getMessage());
    error_log('[OAuth Discord] Stack trace: ' . $e->getTraceAsString());
    header('Location: /login/?error=oauth_error');
}
exit;
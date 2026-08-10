<?php
/**
 * OrinHeberge — Callback OAuth Discord (v2 debug)
 */

// 🔍 DEBUG MODE
error_log('[OAuth Discord] === DÉBUT CALLBACK ===');
error_log('[OAuth Discord] GET params: ' . json_encode($_GET));
error_log('[OAuth Discord] URI: ' . $_SERVER['REQUEST_URI']);
error_log('[OAuth Discord] HTTPS: ' . ($_SERVER['HTTPS'] ?? 'off'));

// ⚠️ Session AVANT tout include (auth.php la démarre aussi, mais on force ici)
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 3600,
        'path' => '/',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
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
    header('Location: /login/?error=oauth_cancelled&discord_error=' . urlencode($_GET['error']));
    exit;
}

$code  = $_GET['code']  ?? null;
$state = $_GET['state'] ?? null;

error_log('[OAuth Discord] Code reçu: ' . ($code ? substr($code, 0, 20) . '...' : 'NULL'));
error_log('[OAuth Discord] State reçu: ' . ($state ?? 'NULL'));

if (!$code || !$state) {
    error_log('[OAuth Discord] ❌ Code ou state manquant');
    error_log('[OAuth Discord] $_GET complet: ' . print_r($_GET, true));
    
    // 🔍 Page debug visuelle (à retirer en prod)
    ?>
    <!DOCTYPE html>
    <html>
    <head><title>Debug OAuth</title></head>
    <body style="background:#111;color:#0f0;padding:20px;font-family:monospace;">
        <h2>🔍 DEBUG OAuth Discord</h2>
        <p><strong>Code:</strong> <?= $code ? '✅ présent (' . strlen($code) . ' chars)' : '❌ MANQUANT' ?></p>
        <p><strong>State:</strong> <?= $state ? '✅ présent' : '❌ MANQUANT' ?></p>
        
        <h3>GET params:</h3>
        <pre><?= htmlspecialchars(print_r($_GET, true)) ?></pre>
        
        <h3>Session:</h3>
        <pre><?= htmlspecialchars(print_r($_SESSION, true)) ?></pre>
        
        <h3>Server:</h3>
        <pre>
REQUEST_URI: <?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>
QUERY_STRING: <?= htmlspecialchars($_SERVER['QUERY_STRING'] ?? 'none') ?>
HTTPS: <?= htmlspecialchars($_SERVER['HTTPS'] ?? 'off') ?>
HTTP_HOST: <?= htmlspecialchars($_SERVER['HTTP_HOST']) ?>
        </pre>
        
        <p>
            <a href="/api/auth/discord.php" style="color:#38bdf8;">🔄 Réessayer</a> | 
            <a href="/login/" style="color:#38bdf8;">Retour login</a>
        </p>
    </body>
    </html>
    <?php
    exit;
}

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
        
        // Email de bienvenue si nouveau compte
        if (!empty($result['is_new']) && !empty($oauthData['email'])) {
            try {
                require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/smtp.php';
                $body = "Bonjour {$oauthData['firstname']},\n\n"
                      . "Votre compte OrinHeberge a été créé via Discord.\n\n"
                      . "À bientôt sur OrinHeberge !";
                @send_smtp_mail($oauthData['email'], '🎉 Bienvenue via Discord', $body);
            } catch (Throwable $e) {
                error_log('[OAuth Discord] Email error: ' . $e->getMessage());
            }
        }
        
        header('Location: /client/');
    } else {
        error_log('[OAuth Discord] ❌ Login échoué: ' . json_encode($result));
        header('Location: /login/?error=oauth_failed&reason=' . urlencode($result['error'] ?? 'unknown'));
    }
} catch (Exception $e) {
    error_log('[OAuth Discord] ❌ Exception: ' . $e->getMessage());
    error_log('[OAuth Discord] Stack trace: ' . $e->getTraceAsString());
    header('Location: /login/?error=oauth_error&msg=' . urlencode($e->getMessage()));
}
exit;
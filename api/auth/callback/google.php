<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/AuthService.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/OAuthProvider.php';

$config = require $_SERVER['DOCUMENT_ROOT'] . '/config/oauth.php';

if (isset($_GET['error'])) {
    header('Location: /login/?error=oauth_cancelled');
    exit;
}

$code  = $_GET['code']  ?? null;
$state = $_GET['state'] ?? null;

if (!$code || !$state) {
    header('Location: /login/?error=oauth_missing');
    exit;
}

try {
    $google = new OAuthProvider('google', $config['google']);
    $oauthData = $google->handleCallback($code, $state);

    $auth = new AuthService($pdo);
    $result = $auth->loginWithOAuth('google', $oauthData);

    if ($result['success']) {
        if (!empty($result['is_new']) && !empty($oauthData['email'])) {
            require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/smtp.php';
            $body = "Bonjour {$oauthData['firstname']},\n\n"
                  . "Votre compte OrinHeberge a été créé via Google.\n\n"
                  . "À bientôt sur OrinHeberge !";
            @send_smtp_mail($oauthData['email'], '🎉 Bienvenue via Google', $body);
        }

        header('Location: /client/');
    } else {
        header('Location: /login/?error=oauth_failed');
    }
} catch (Exception $e) {
    error_log('[OAuth Google] ' . $e->getMessage());
    header('Location: /login/?error=oauth_error');
}
exit;
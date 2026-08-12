<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/OAuthProvider.php';

$config = require $_SERVER['DOCUMENT_ROOT'] . '/config/oauth.php';
$google = new OAuthProvider('google', $config['google']);

header('Location: ' . $google->getAuthorizationUrl());
exit;
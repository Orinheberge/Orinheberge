<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/OAuthProvider.php';

$config = require $_SERVER['DOCUMENT_ROOT'] . '/config/oauth.php';
$discord = new OAuthProvider('discord', $config['discord']);

header('Location: ' . $discord->getAuthorizationUrl());
exit;
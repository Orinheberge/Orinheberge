<?php
/**
 * OrinHeberge — Configuration OAuth
 * ⚠️ À sécuriser en production (variables d'environnement)
 */

$baseUrl = 'https://heberge.orinstone.deepstone.fr';

return [
    'discord' => [
        'client_id'     => 'TON_CLIENT_ID_DISCORD',
        'client_secret' => 'TON_CLIENT_SECRET_DISCORD',
        'authorize_url' => 'https://discord.com/api/oauth2/authorize',
        'token_url'     => 'https://discord.com/api/oauth2/token',
        'user_info_url' => 'https://discord.com/api/users/@me',
        'scopes'        => ['identify', 'email'],
        'redirect_uri'  => "$baseUrl/api/auth/callback/discord.php",
    ],

    'google' => [
        'client_id'     => 'TON_CLIENT_ID_GOOGLE.apps.googleusercontent.com',
        'client_secret' => 'TON_CLIENT_SECRET_GOOGLE',
        'authorize_url' => 'https://accounts.google.com/o/oauth2/v2/auth',
        'token_url'     => 'https://oauth2.googleapis.com/token',
        'user_info_url' => 'https://www.googleapis.com/oauth2/v3/userinfo',
        'scopes'        => ['openid', 'email', 'profile'],
        'redirect_uri'  => "$baseUrl/api/auth/callback/google.php",
    ],
];
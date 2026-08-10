<?php
/**
 * OrinHeberge — Configuration OAuth
 * ⚠️ À sécuriser en production (variables d'environnement)
 */

$baseUrl = 'https://heberge.orinstone.deepstone.fr';

return [
    'discord' => [
        'client_id'     => '1534906425724370954',
        'client_secret' => 'u359vRUtwfZQeV7H7c-XHmO_fav9LeSV',
        'authorize_url' => 'https://discord.com/api/oauth2/authorize',
        'token_url'     => 'https://discord.com/api/oauth2/token',
        'user_info_url' => 'https://discord.com/api/users/@me',
        'scopes'        => ['identify', 'email','connections'],
        'redirect_uri'  => "https://heberge.orinstone.deepstone.fr/api/auth/callback/discord.php",
    ],

    'google' => [
        'client_id'     => '1078240315010-7nn5u5t952e3vogo6d9thsppqf624s59.apps.googleusercontent.com',
        'client_secret' => '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',
        'authorize_url' => 'https://accounts.google.com/o/oauth2/v2/auth',
        'token_url'     => 'https://oauth2.googleapis.com/token',
        'user_info_url' => 'https://www.googleapis.com/oauth2/v3/userinfo',
        'scopes'        => ['openid', 'email', 'profile'],
        'redirect_uri'  => "https://heberge.orinstone.deepstone.fr/api/auth/callback/google.php",
    ],
];
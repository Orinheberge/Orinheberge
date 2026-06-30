<?php

/*
|--------------------------------------------------------------------------
| Les appels Discord passent par un proxy sur le même domaine
| car le conteneur Pterodactyl ne peut pas atteindre discord.com
|--------------------------------------------------------------------------
*/

define('DISCORD_PROXY_URL',    'https://heberge.orinstone.deepstone.fr/shop/order/webhook/proxy.php');
define('DISCORD_PROXY_SECRET', 'orin_proxy_2026_secret');

/**
 * Envoie une notification Discord via le proxy
 */
function sendDiscordWebhook(
    string $url,
    string $order_id,
    string $offer_name,
    float  $price,
    string $user_email,
    ?string $server_uuid,
    ?string $server_identifier
): void {
    if (empty($url)) return;

    $json_data = json_encode([
        "username"   => "OrinHeberge - Commandes",
        "avatar_url" => "https://heberge.orinstone.deepstone.fr/favicon.png",
        "embeds"     => [[
            "title"       => "👑 Nouvelle commande",
            "type"        => "rich",
            "description" => "Un utilisateur a payé une offre Premium.",
            "timestamp"   => date("c"),
            "color"       => hexdec("f59e0b"),
            "footer"      => ["text" => "OrinHeberge Premium System"],
            "fields"      => [
                ["name" => "📦 Offre",           "value" => $offer_name,                                   "inline" => true],
                ["name" => "💰 Prix",            "value" => number_format($price, 2, '.', '') . "€",        "inline" => true],
                ["name" => "🔢 Commande ID",     "value" => "#" . $order_id,                               "inline" => true],
                ["name" => "🆔 Serveur (Panel)", "value" => "`" . ($server_identifier ?? 'N/A') . "`",     "inline" => true],
                ["name" => "🔑 UUID Serveur",    "value" => "`" . ($server_uuid ?? 'N/A') . "`",           "inline" => false],
                ["name" => "📧 Client",          "value" => $user_email,                                   "inline" => false],
            ]
        ]]
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    _sendViaProxy($url, $json_data);
}

/**
 * Envoie une notification Discord de renouvellement via le proxy
 */
function sendRenewalDiscord(
    string $webhook_url,
    string $order_id,
    string $service,
    string $email,
    string $due_date,
    float  $price,
    string $status
): void {
    if (empty($webhook_url)) return;

    $colors = [
        'expiring'  => hexdec("f59e0b"),
        'expired'   => hexdec("ef4444"),
        'renewed'   => hexdec("22c55e"),
        'suspended' => hexdec("6b7280"),
    ];
    $icons = [
        'expiring'  => '⚠️',
        'expired'   => '❌',
        'renewed'   => '✅',
        'suspended' => '🔒',
    ];
    $titles = [
        'expiring'  => 'Renouvellement à venir',
        'expired'   => 'Serveur expiré',
        'renewed'   => 'Renouvellement confirmé',
        'suspended' => 'Serveur suspendu',
    ];

    $json = json_encode([
        "username"   => "OrinHeberge - Renouvellements",
        "avatar_url" => "https://heberge.orinstone.deepstone.fr/favicon.png",
        "embeds"     => [[
            "title"     => ($icons[$status] ?? '🔔') . " " . ($titles[$status] ?? $status),
            "color"     => $colors[$status] ?? hexdec("6b7280"),
            "timestamp" => date("c"),
            "footer"    => ["text" => "OrinHeberge Renewal System"],
            "fields"    => [
                ["name" => "📦 Service",  "value" => $service,                                    "inline" => true],
                ["name" => "💰 Montant", "value" => number_format($price, 2, '.', '') . "€",      "inline" => true],
                ["name" => "📅 Échéance","value" => $due_date,                                    "inline" => true],
                ["name" => "🔢 Commande","value" => "#" . $order_id,                              "inline" => true],
                ["name" => "📧 Client",  "value" => $email,                                       "inline" => false],
            ]
        ]]
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    _sendViaProxy($webhook_url, $json);
}

/**
 * Fonction interne — envoie via le proxy
 */
function _sendViaProxy(string $discord_url, string $json_data): void {
    $ch = curl_init(DISCORD_PROXY_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $json_data,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'X-Proxy-Secret: ' . DISCORD_PROXY_SECRET,
            'X-Webhook-Url: '  . $discord_url,
        ],
    ]);
    curl_exec($ch);
}

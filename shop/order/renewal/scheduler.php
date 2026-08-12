<?php
/*
|--------------------------------------------------------------------------
| SCHEDULER — OrinHeberge
| Point d'entrée du cron Pterodactyl :
|   * * * * * php /home/container/www/shop/order/renewal/scheduler.php
| (toutes les minutes — le scheduler gère lui-même les fréquences)
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../../../../vendor/autoload.php';
require_once __DIR__ . '/../lib/renewal/renewal.php';

use GO\Scheduler;

$discord_webhook_url = "https://discord.com/api/webhooks/1505677242527649872/jFoANIv3OKNtGMib4bViJ79ltRDsf0LJviq59yXwW5hrqZ0uTyU1Yx3nV88yy6rG2eA4";
$base_url            = "https://heberge.orinstone.deepstone.fr";

$pdo = new PDO(
    "mysql:host=85.9.203.227;dbname=s43_orinheberge;charset=utf8mb4",
    "orinstone", "1504",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

$scheduler = new Scheduler();

/*
|--------------------------------------------------------------------------
| TÂCHE 1 — Rappels de renouvellement (chaque jour à 9h00)
|--------------------------------------------------------------------------
*/
$scheduler->call(function () use ($pdo, $discord_webhook_url, $base_url) {

    $expiring = getExpiringOrders($pdo, 7);

    foreach ($expiring as $order) {
        $days_left = (int)((strtotime($order['next_payment_date']) - time()) / 86400);

        if ($days_left !== 7 && $days_left !== 3 && $days_left !== 1) continue;

        $renew_url = $base_url . "/shop/order/renewal/?id=" . $order['id'];
        $due_date  = date("d/m/Y", strtotime($order['next_payment_date']));

        sendRenewalEmail(
            $order['email'],
            $order['name'] ?? 'Client',
            $order['service_name'],
            (float)$order['renewal_price'],
            $due_date,
            $renew_url
        );

        sendRenewalDiscord(
            $discord_webhook_url,
            $order['order_id'],
            $order['service_name'],
            $order['email'],
            $due_date,
            (float)$order['renewal_price'],
            'expiring'
        );

        echo "[RAPPEL J-{$days_left}] {$order['service_name']} — {$order['email']}\n";
    }

})->dailyAt('9:00')->then(function () {
    echo "[OK] Rappels renouvellement envoyés — " . date("Y-m-d H:i:s") . "\n";
}, true);

/*
|--------------------------------------------------------------------------
| TÂCHE 2 — Suspension des serveurs expirés (chaque jour à 10h00)
|--------------------------------------------------------------------------
*/
$scheduler->call(function () use ($pdo, $discord_webhook_url) {

    $expired = getExpiredOrders($pdo);

    foreach ($expired as $order) {
        $due_date = date("d/m/Y", strtotime($order['next_payment_date']));

        suspendOrder($pdo, $order['id']);

        sendRenewalDiscord(
            $discord_webhook_url,
            $order['order_id'],
            $order['service_name'],
            $order['email'],
            $due_date,
            (float)$order['renewal_price'],
            'suspended'
        );

        echo "[SUSPENDU] {$order['service_name']} — {$order['email']} — expiré le {$due_date}\n";
    }

})->dailyAt('10:00')->then(function () {
    echo "[OK] Suspensions traitées — " . date("Y-m-d H:i:s") . "\n";
}, true);

/*
|--------------------------------------------------------------------------
| TÂCHE 3 — Rappel urgent J-1 (chaque jour à 18h00)
|--------------------------------------------------------------------------
*/
$scheduler->call(function () use ($pdo, $discord_webhook_url, $base_url) {

    $expiring = getExpiringOrders($pdo, 1);

    foreach ($expiring as $order) {
        $due_date  = date("d/m/Y", strtotime($order['next_payment_date']));
        $renew_url = $base_url . "/shop/order/renewal/?id=" . $order['id'];

        // Email urgent
        sendRenewalEmail(
            $order['email'],
            $order['name'] ?? 'Client',
            $order['service_name'],
            (float)$order['renewal_price'],
            $due_date,
            $renew_url
        );

        // Discord urgent
        sendRenewalDiscord(
            $discord_webhook_url,
            $order['order_id'],
            $order['service_name'],
            $order['email'],
            $due_date,
            (float)$order['renewal_price'],
            'expiring'
        );

        echo "[URGENT J-1] {$order['service_name']} — {$order['email']}\n";
    }

})->dailyAt('18:00')->then(function () {
    echo "[OK] Rappels urgents J-1 envoyés — " . date("Y-m-d H:i:s") . "\n";
}, true);

/*
|--------------------------------------------------------------------------
| LANCEMENT
|--------------------------------------------------------------------------
*/
$scheduler->run();

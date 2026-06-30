<?php
/*
|--------------------------------------------------------------------------
| CRON — Renouvellements OrinHeberge
| Appelé par le crontab natif de l'egg Ym0T/pterodactyl-nginx-egg
|
| Usage :
|   php cron.php          → rappels J-7, J-3
|   php cron.php suspend  → suspension des expirés
|   php cron.php urgent   → rappels J-1
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../lib/renewal/renewal.php';
require_once __DIR__ . '/../webhook/discord.php';

$discord_webhook_url = "https://discord.com/api/webhooks/1505677242527649872/jFoANIv3OKNtGMib4bViJ79ltRDsf0LJviq59yXwW5hrqZ0uTyU1Yx3nV88yy6rG2eA4";
$base_url            = "https://heberge.orinstone.deepstone.fr";
$mode                = $argv[1] ?? 'reminders';

$pdo = new PDO(
    "mysql:host=85.9.203.227;dbname=s43_orinheberge;charset=utf8mb4",
    "orinstone", "1504",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

echo "[" . date("Y-m-d H:i:s") . "] CRON mode=$mode\n";

switch ($mode) {

    /*
    |------------------------------------------------------------------
    | RAPPELS J-7 et J-3
    |------------------------------------------------------------------
    */
    case 'reminders':
        $orders = getExpiringOrders($pdo, 7);
        $sent   = 0;

        foreach ($orders as $order) {
            $days_left = (int)((strtotime($order['next_payment_date']) - time()) / 86400);
            if ($days_left !== 7 && $days_left !== 3) continue;

            $due_date  = date("d/m/Y", strtotime($order['next_payment_date']));
            $renew_url = $base_url . "/shop/order/renewal/?id=" . $order['id'];

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
            $sent++;
        }

        echo "[OK] {$sent} rappel(s) envoyé(s)\n";
        break;

    /*
    |------------------------------------------------------------------
    | RAPPELS URGENTS J-1
    |------------------------------------------------------------------
    */
    case 'urgent':
        $orders = getExpiringOrders($pdo, 1);
        $sent   = 0;

        foreach ($orders as $order) {
            $days_left = (int)((strtotime($order['next_payment_date']) - time()) / 86400);
            if ($days_left !== 1) continue;

            $due_date  = date("d/m/Y", strtotime($order['next_payment_date']));
            $renew_url = $base_url . "/shop/order/renewal/?id=" . $order['id'];

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

            echo "[URGENT J-1] {$order['service_name']} — {$order['email']}\n";
            $sent++;
        }

        echo "[OK] {$sent} rappel(s) urgent(s) envoyé(s)\n";
        break;

    /*
    |------------------------------------------------------------------
    | SUSPENSION DES EXPIRÉS
    |------------------------------------------------------------------
    */
    case 'suspend':
        $expired   = getExpiredOrders($pdo);
        $suspended = 0;

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
            $suspended++;
        }

        echo "[OK] {$suspended} serveur(s) suspendu(s)\n";
        break;

    default:
        echo "[ERREUR] Mode inconnu : {$mode}\n";
        exit(1);
}

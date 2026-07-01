<?php
/*
|--------------------------------------------------------------------------
| CRON — Cycle de vie des serveurs OrinHeberge
|
| Modes :
|   php cron.php reminders  → rappels J-7, J-3
|   php cron.php urgent     → rappels J-1
|   php cron.php suspend    → suspension des expirés (J0)
|   php cron.php delete     → suppression définitive (suspended + 15j)
|--------------------------------------------------------------------------
| Crontab recommandé :
|   0  9  * * *  php /var/www/orinheberge/shop/order/renewal/cron.php reminders
|   0 18  * * *  php /var/www/orinheberge/shop/order/renewal/cron.php urgent
|   0 10  * * *  php /var/www/orinheberge/shop/order/renewal/cron.php suspend
|   0  2  * * *  php /var/www/orinheberge/shop/order/renewal/cron.php delete
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../lib/renewal/renewal.php';
require_once __DIR__ . '/../webhook/discord.php';

$discord_webhook_url = "https://discord.com/api/webhooks/1505677242527649872/jFoANIv3OKNtGMib4bViJ79ltRDsf0LJviq59yXwW5hrqZ0uTyU1Yx3nV88yy6rG2eA4";
$base_url            = "https://heberge.orinstone.deepstone.fr";
$mode                = $argv[1] ?? 'reminders';

$pdo = new PDO(
    "mysql:host=localhost;dbname=s43_orinheberge;charset=utf8mb4",
    "root", "1504",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

// Config panel
$cfg = [];
foreach ($pdo->query('SELECT `key`,`value` FROM settings') as $r) $cfg[$r['key']] = $r['value'];
$panel_url     = $cfg['panel_url']     ?? '';
$api_key_admin = $cfg['api_key_admin'] ?? '';
$headers_admin = ["Authorization: Bearer $api_key_admin","Accept: application/vnd.pterodactyl.v1+json","Content-Type: application/json"];

function panelPost(string $url, array $headers, string $ep): void {
    $ch = curl_init($url . '/api/application/' . $ep);
    curl_setopt_array($ch, [CURLOPT_HTTPHEADER=>$headers, CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>15, CURLOPT_SSL_VERIFYPEER=>false, CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>'{}']);
    curl_exec($ch); curl_close($ch);
}
function panelDelete(string $url, array $headers, string $ep): void {
    $ch = curl_init($url . '/api/application/' . $ep);
    curl_setopt_array($ch, [CURLOPT_HTTPHEADER=>$headers, CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>15, CURLOPT_SSL_VERIFYPEER=>false, CURLOPT_CUSTOMREQUEST=>'DELETE']);
    curl_exec($ch); curl_close($ch);
}

echo "[" . date("Y-m-d H:i:s") . "] CRON mode=$mode\n";

switch ($mode) {

    // ──────────────────────────────────────────────────────────────
    // RAPPELS J-7 et J-3
    // ──────────────────────────────────────────────────────────────
    case 'reminders':
        $orders = getExpiringOrders($pdo, 7);
        $sent   = 0;
        foreach ($orders as $order) {
            $days_left = (int)((strtotime($order['next_payment_date']) - time()) / 86400);
            if ($days_left !== 7 && $days_left !== 3) continue;
            $due_date  = date("d/m/Y", strtotime($order['next_payment_date']));
            $renew_url = $base_url . "/shop/order/renewal/?id=" . $order['id'];
            sendRenewalEmail($order['email'], $order['firstname'] ?? 'Client', $order['service_name'], (float)$order['renewal_price'], $due_date, $renew_url);
            sendRenewalDiscord($discord_webhook_url, $order['order_id'], $order['service_name'], $order['email'], $due_date, (float)$order['renewal_price'], 'expiring');
            echo "[RAPPEL J-{$days_left}] {$order['service_name']} — {$order['email']}\n";
            $sent++;
        }
        echo "[OK] {$sent} rappel(s) envoyé(s)\n";
        break;

    // ──────────────────────────────────────────────────────────────
    // RAPPELS URGENTS J-1
    // ──────────────────────────────────────────────────────────────
    case 'urgent':
        $orders = getExpiringOrders($pdo, 1);
        $sent   = 0;
        foreach ($orders as $order) {
            $days_left = (int)((strtotime($order['next_payment_date']) - time()) / 86400);
            if ($days_left > 1) continue;
            $due_date  = date("d/m/Y", strtotime($order['next_payment_date']));
            $renew_url = $base_url . "/shop/order/renewal/?id=" . $order['id'];
            sendRenewalEmail($order['email'], $order['firstname'] ?? 'Client', $order['service_name'], (float)$order['renewal_price'], $due_date, $renew_url);
            sendRenewalDiscord($discord_webhook_url, $order['order_id'], $order['service_name'], $order['email'], $due_date, (float)$order['renewal_price'], 'expiring');
            echo "[URGENT J-1] {$order['service_name']} — {$order['email']}\n";
            $sent++;
        }
        echo "[OK] {$sent} rappel(s) urgent(s)\n";
        break;

    // ──────────────────────────────────────────────────────────────
    // SUSPENSION DES EXPIRÉS
    // ──────────────────────────────────────────────────────────────
    case 'suspend':
        $expired   = getExpiredOrders($pdo);
        $suspended = 0;
        foreach ($expired as $order) {
            // Suspendre sur le panel
            if (!empty($order['server_id'])) {
                panelPost($panel_url, $headers_admin, "servers/{$order['server_id']}/suspend");
            }
            // Marquer suspendu + fixer date de suppression à J+15
            $pdo->prepare("
                UPDATE orders
                SET status='suspended',
                    suspended_at=NOW(),
                    delete_after=DATE_ADD(NOW(), INTERVAL 15 DAY)
                WHERE id=?
            ")->execute([$order['id']]);

            $due_date = date("d/m/Y", strtotime($order['next_payment_date']));
            sendRenewalDiscord($discord_webhook_url, $order['order_id'], $order['service_name'], $order['email'], $due_date, (float)$order['renewal_price'], 'suspended');
            echo "[SUSPENDU] {$order['service_name']} — {$order['email']} — expiré le {$due_date} — suppression le " . date("d/m/Y", strtotime('+15 days')) . "\n";
            $suspended++;
        }
        echo "[OK] {$suspended} serveur(s) suspendu(s)\n";
        break;

    // ──────────────────────────────────────────────────────────────
    // SUPPRESSION DÉFINITIVE (suspended depuis +15 jours)
    // ──────────────────────────────────────────────────────────────
    case 'unsuspend':
        $to_unsuspend = $pdo->query("
            SELECT o.*, u.email, u.firstname
            FROM orders o
            JOIN users u ON u.id = o.user_id
            WHERE o.status = 'suspended'
              AND o.suspension_until IS NOT NULL
              AND o.suspension_until <= NOW()
              AND (o.expires_at IS NULL OR o.expires_at > NOW())
        ")->fetchAll();

        $unsuspended = 0;
        foreach ($to_unsuspend as $order) {
            if (!empty($order['server_id'])) {
                panelPost($panel_url, $headers_admin, "servers/{$order['server_id']}/unsuspend");
            }
            $pdo->prepare("
                UPDATE orders
                SET status='paid',
                    suspended_at=NULL,
                    suspension_until=NULL,
                    delete_after=NULL
                WHERE id=?
            ")->execute([$order['id']]);

            echo "[REACTIVE] {$order['service_name']} — {$order['email']}\n";
            $unsuspended++;
        }
        echo "[OK] {$unsuspended} serveur(s) reactive(s)\n";
        break;

    case 'delete':
        $to_delete = $pdo->query("
            SELECT o.*, u.email, u.firstname
            FROM orders o
            JOIN users u ON u.id = o.user_id
            WHERE o.status = 'suspended'
              AND o.delete_after IS NOT NULL
              AND o.delete_after <= NOW()
        ")->fetchAll();

        $deleted = 0;
        foreach ($to_delete as $order) {
            // Supprimer sur le panel
            if (!empty($order['server_id'])) {
                panelDelete($panel_url, $headers_admin, "servers/{$order['server_id']}");
            }
            // Marquer supprimé en BDD (on garde la ligne pour l'historique)
            $pdo->prepare("UPDATE orders SET status='deleted' WHERE id=?")->execute([$order['id']]);

            $due_date = date("d/m/Y", strtotime($order['next_payment_date']));
            sendRenewalDiscord($discord_webhook_url, $order['order_id'], $order['service_name'], $order['email'], $due_date, (float)$order['renewal_price'], 'expired');
            echo "[SUPPRIMÉ] {$order['service_name']} — {$order['email']} — suspendu le " . date("d/m/Y", strtotime($order['suspended_at'])) . "\n";
            $deleted++;
        }
        echo "[OK] {$deleted} serveur(s) supprimé(s)\n";
        break;

    default:
        echo "[ERREUR] Mode inconnu : {$mode}\n";
        exit(1);
}

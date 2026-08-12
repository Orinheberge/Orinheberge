<?php
/*
|--------------------------------------------------------------------------
| CRON — Cycle de vie des serveurs OrinHeberge (v2 - Stripe)
|
| Modes :
|   php cron.php reminders    → rappels J-7, J-3
|   php cron.php urgent       → rappels J-1
|   php cron.php auto_renew   → prélèvement Stripe automatique (J0)
|   php cron.php suspend      → suspension des expirés non payés (J+1)
|   php cron.php unsuspend    → réactivation si renouvelé entre-temps
|   php cron.php delete       → suppression définitive (suspended + 15j)
|--------------------------------------------------------------------------
| Crontab recommandé :
|   0  9  * * *  php /var/www/orinheberge/shop/order/renewal/cron.php reminders
|   0 18  * * *  php /var/www/orinheberge/shop/order/renewal/cron.php urgent
|   0  1  * * *  php /var/www/orinheberge/shop/order/renewal/cron.php auto_renew
|   0 10  * * *  php /var/www/orinheberge/shop/order/renewal/cron.php suspend
|   0 11  * * *  php /var/www/orinheberge/shop/order/renewal/cron.php unsuspend
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

// ─── Config panel + stripe ───
$cfg = [];
foreach ($pdo->query('SELECT `key`,`value` FROM settings') as $r) $cfg[$r['key']] = $r['value'];
$panel_url     = $cfg['panel_url']     ?? '';
$api_key_admin = $cfg['api_key_admin'] ?? '';
$headers_admin = ["Authorization: Bearer $api_key_admin","Accept: application/vnd.pterodactyl.v1+json","Content-Type: application/json"];

// Clés Stripe depuis extension_settings
$stripe_secret_key = '';
try {
    $ext_cfg = [];
    $ext_rows = $pdo->query("SELECT e.slug, es.key, es.value FROM extension_settings es JOIN extensions e ON e.id = es.extension_id WHERE e.slug = 'stripe'")->fetchAll();
    foreach ($ext_rows as $r) $ext_cfg[$r['key']] = $r['value'];
    $stripe_secret_key = $ext_cfg['secret_key'] ?? '';
} catch (Exception $e) {
    fwrite(STDERR, "[Stripe] Erreur lecture settings: " . $e->getMessage() . "\n");
}

// ─── Helpers Pterodactyl ───
function panelPost(string $url, array $headers, string $ep): void {
    $ch = curl_init($url . '/api/application/' . $ep);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => '{}'
    ]);
    curl_exec($ch);
    curl_close($ch);
}

function panelDelete(string $url, array $headers, string $ep): void {
    $ch = curl_init($url . '/api/application/' . $ep);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_CUSTOMREQUEST  => 'DELETE'
    ]);
    curl_exec($ch);
    curl_close($ch);
}

// ─── Helpers Stripe ───
function stripeGet(string $endpoint, string $secret): ?array {
    $ch = curl_init("https://api.stripe.com/v1/$endpoint");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD        => $secret . ':',
        CURLOPT_TIMEOUT        => 15,
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ($code >= 200 && $code < 300) ? json_decode($resp, true) : null;
}

function stripePost(string $endpoint, array $data, string $secret): array {
    $ch = curl_init("https://api.stripe.com/v1/$endpoint");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_USERPWD        => $secret . ':',
        CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_POSTFIELDS     => http_build_query($data),
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $code, 'data' => json_decode($resp, true)];
}

/**
 * Récupère la méthode de paiement par défaut d'un customer Stripe
 */
function getCustomerDefaultPM(string $customer_id, string $secret): ?string {
    if (empty($customer_id)) return null;
    
    $customer = stripeGet("customers/" . urlencode($customer_id), $secret);
    if (!empty($customer['invoice_settings']['default_payment_method'])) {
        return $customer['invoice_settings']['default_payment_method'];
    }
    
    // Fallback : première carte attachée
    $pms = stripeGet("payment_methods?customer=" . urlencode($customer_id) . "&type=card&limit=1", $secret);
    return $pms['data'][0]['id'] ?? null;
}

/**
 * Tente un prélèvement off-session (sans interaction utilisateur)
 * Retourne : ['status' => 'succeeded'|'requires_action'|'failed', 'pi' => ..., 'error' => ...]
 */
function attemptOffSessionPayment(
    string $customer_id,
    string $payment_method_id,
    int $amount_cents,
    string $description,
    array $metadata,
    string $secret
): array {
    $result = stripePost('payment_intents', [
        'amount'                 => $amount_cents,
        'currency'               => 'eur',
        'customer'               => $customer_id,
        'payment_method'         => $payment_method_id,
        'off_session'            => 'true',
        'confirm'                => 'true',
        'description'            => $description,
        'receipt_email'          => $metadata['email'] ?? '',
        'metadata'               => $metadata,
    ], $secret);

    if ($result['code'] >= 200 && $result['code'] < 300) {
        return ['status' => $result['data']['status'], 'pi' => $result['data']];
    }

    // Erreur (carte déclinée, 3DS requis, etc.)
    $err_msg = $result['data']['error']['message'] ?? 'Erreur Stripe HTTP ' . $result['code'];
    $err_code = $result['data']['error']['code'] ?? '';
    return ['status' => 'failed', 'error' => $err_msg, 'error_code' => $err_code, 'pi' => $result['data'] ?? null];
}

/**
 * Renouvelle une commande en BDD (mise à jour dates + log)
 */
function renewOrderInDB(PDO $pdo, array $order, float $amount, ?string $pi_id): void {
    $pdo->prepare("
        UPDATE orders
        SET status='paid',
            next_payment_date = DATE_ADD(COALESCE(next_payment_date, NOW()), INTERVAL 1 MONTH),
            last_renewal_at   = NOW(),
            last_payment_intent_id = ?,
            updated_at = NOW()
        WHERE id = ?
    ")->execute([$pi_id, $order['id']]);

    try {
        $pdo->prepare("
            INSERT INTO order_renewals 
            (order_id, user_id, amount, payment_intent_id, method, created_at)
            VALUES (?, ?, ?, ?, 'auto', NOW())
        ")->execute([$order['id'], $order['user_id'], $amount, $pi_id]);
    } catch (Exception $e) {
        // Table optionnelle
    }
}

echo "[" . date("Y-m-d H:i:s") . "] CRON mode=$mode\n";

switch ($mode) {

    // ══════════════════════════════════════════════════════════════
    // RAPPELS J-7 et J-3
    // ══════════════════════════════════════════════════════════════
    case 'reminders':
        $orders = getExpiringOrders($pdo, 7);
        $sent = 0;
        foreach ($orders as $order) {
            $days_left = (int)((strtotime($order['next_payment_date']) - time()) / 86400);
            if ($days_left !== 7 && $days_left !== 3) continue;
            
            // Si auto_renew activé + customer stripe OK → ne pas spammer
            $auto_renew_ok = false;
            if (!empty($order['auto_renew']) && !empty($stripe_secret_key)) {
                try {
                    $u = $pdo->prepare("SELECT stripe_customer_id FROM users WHERE id = ? LIMIT 1");
                    $u->execute([$order['user_id']]);
                    $cid = $u->fetchColumn();
                    if ($cid && getCustomerDefaultPM($cid, $stripe_secret_key)) {
                        $auto_renew_ok = true;
                    }
                } catch (Exception $e) {}
            }
            
            $due_date  = date("d/m/Y", strtotime($order['next_payment_date']));
            $renew_url = $base_url . "/shop/order/renewal/?id=" . $order['id'];
            
            sendRenewalEmail(
                $order['email'], 
                $order['firstname'] ?? 'Client', 
                $order['service_name'], 
                (float)$order['renewal_price'], 
                $due_date, 
                $renew_url,
                $auto_renew_ok // Nouveau paramètre : "sera renouvelé automatiquement"
            );
            sendRenewalDiscord($discord_webhook_url, $order['order_id'], $order['service_name'], $order['email'], $due_date, (float)$order['renewal_price'], 'expiring');
            
            echo "[RAPPEL J-{$days_left}] {$order['service_name']} — {$order['email']}" . ($auto_renew_ok ? " [auto-renew]" : "") . "\n";
            $sent++;
        }
        echo "[OK] {$sent} rappel(s) envoyé(s)\n";
        break;

    // ══════════════════════════════════════════════════════════════
    // RAPPELS URGENTS J-1
    // ══════════════════════════════════════════════════════════════
    case 'urgent':
        $orders = getExpiringOrders($pdo, 1);
        $sent = 0;
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

    // ══════════════════════════════════════════════════════════════
    // RENOUVELLEMENT AUTOMATIQUE STRIPE (J0 - échéance atteinte)
    // ══════════════════════════════════════════════════════════════
    case 'auto_renew':
        if (empty($stripe_secret_key)) {
            echo "[SKIP] Stripe non configuré\n";
            break;
        }

        // Commandes arrivant à échéance aujourd'hui (ou en retard < 24h) avec auto_renew activé
        $stmt = $pdo->query("
            SELECT o.*, u.email, u.firstname, u.stripe_customer_id
            FROM orders o
            JOIN users u ON u.id = o.user_id
            WHERE o.status = 'paid'
              AND o.auto_renew = 1
              AND o.next_payment_date IS NOT NULL
              AND o.next_payment_date <= NOW()
              AND o.next_payment_date >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        $to_renew = $stmt->fetchAll();

        $success = 0;
        $failed  = 0;
        $action_required = 0;

        foreach ($to_renew as $order) {
            $customer_id = $order['stripe_customer_id'] ?? null;
            if (!$customer_id) {
                echo "[SKIP] {$order['service_name']} — pas de stripe_customer_id\n";
                continue;
            }

            // Récupérer la méthode de paiement par défaut
            $pm_id = getCustomerDefaultPM($customer_id, $stripe_secret_key);
            if (!$pm_id) {
                echo "[SKIP] {$order['service_name']} — aucune PM par défaut\n";
                // Loguer l'échec pour notifier l'utilisateur
                try {
                    $pdo->prepare("
                        INSERT INTO renewal_attempts (order_id, user_id, status, reason, created_at)
                        VALUES (?, ?, 'failed', 'no_payment_method', NOW())
                    ")->execute([$order['id'], $order['user_id']]);
                } catch (Exception $e) {}
                continue;
            }

            $amount       = (float)$order['renewal_price'];
            $amount_cents = (int)round($amount * 100);

            echo "[AUTO] Tentative {$order['service_name']} — {$amount}€ — PM: " . substr($pm_id, 0, 12) . "…\n";

            $result = attemptOffSessionPayment(
                $customer_id,
                $pm_id,
                $amount_cents,
                'Renouvellement : ' . $order['service_name'],
                [
                    'type'            => 'renewal',
                    'order_id'        => $order['id'],
                    'order_uuid'      => $order['uuid'] ?? '',
                    'user_id'         => $order['user_id'],
                    'service_name'    => $order['service_name'],
                ],
                $stripe_secret_key
            );

            // ── SUCCÈS ──
            if ($result['status'] === 'succeeded') {
                renewOrderInDB($pdo, $order, $amount, $result['pi']['id'] ?? null);
                
                // Si le serveur était suspendu, le réactiver
                if (!empty($order['server_id'])) {
                    panelPost($panel_url, $headers_admin, "servers/{$order['server_id']}/unsuspend");
                }
                
                sendRenewalDiscord($discord_webhook_url, $order['order_id'], $order['service_name'], $order['email'], date('d/m/Y'), $amount, 'auto_renewed');
                echo "  ✓ Réussi (PI: " . ($result['pi']['id'] ?? 'n/a') . ")\n";
                
                try {
                    $pdo->prepare("
                        INSERT INTO renewal_attempts (order_id, user_id, status, payment_intent_id, amount, created_at)
                        VALUES (?, ?, 'succeeded', ?, ?, NOW())
                    ")->execute([$order['id'], $order['user_id'], $result['pi']['id'], $amount]);
                } catch (Exception $e) {}
                
                $success++;
                continue;
            }

            // ── 3DS REQUIS ──
            if ($result['status'] === 'requires_action' || $result['status'] === 'requires_source_action') {
                // Le client doit confirmer manuellement
                $hosted_url = $result['pi']['next_action']['redirect_to_url']['url'] 
                           ?? ($base_url . "/shop/order/renewal/?id=" . $order['id'] . "&pi=" . ($result['pi']['id'] ?? ''));
                
                // Envoyer un email avec le lien de confirmation
                sendActionRequiredEmail($order['email'], $order['firstname'] ?? 'Client', $order['service_name'], $amount, $hosted_url);
                
                sendRenewalDiscord($discord_webhook_url, $order['order_id'], $order['service_name'], $order['email'], date('d/m/Y'), $amount, '3ds_required');
                echo "  ⚠ 3DS requis — email envoyé à {$order['email']}\n";
                
                try {
                    $pdo->prepare("
                        INSERT INTO renewal_attempts (order_id, user_id, status, payment_intent_id, amount, created_at)
                        VALUES (?, ?, 'requires_action', ?, ?, NOW())
                    ")->execute([$order['id'], $order['user_id'], $result['pi']['id'] ?? null, $amount]);
                } catch (Exception $e) {}
                
                $action_required++;
                continue;
            }

            // ── ÉCHEC ──
            $err_msg = $result['error'] ?? 'raison inconnue';
            echo "  ✗ Échec : {$err_msg}\n";
            
            try {
                $pdo->prepare("
                    INSERT INTO renewal_attempts (order_id, user_id, status, reason, payment_intent_id, amount, created_at)
                    VALUES (?, ?, 'failed', ?, ?, ?, NOW())
                ")->execute([$order['id'], $order['user_id'], $err_msg, $result['pi']['id'] ?? null, $amount]);
            } catch (Exception $e) {}
            
            // Email d'échec avec lien de renouvellement manuel
            $renew_url = $base_url . "/shop/order/renewal/?id=" . $order['id'];
            sendRenewalFailedEmail($order['email'], $order['firstname'] ?? 'Client', $order['service_name'], $amount, $err_msg, $renew_url);
            
            sendRenewalDiscord($discord_webhook_url, $order['order_id'], $order['service_name'], $order['email'], date('d/m/Y'), $amount, 'auto_renew_failed');
            $failed++;
        }
        
        echo "[OK] auto_renew: $success succès, $action_required 3DS requis, $failed échecs\n";
        break;

    // ══════════════════════════════════════════════════════════════
    // SUSPENSION DES EXPIRÉS (J+1 sans paiement)
    // ══════════════════════════════════════════════════════════════
    case 'suspend':
        $expired = getExpiredOrders($pdo);
        $suspended = 0;
        
        foreach ($expired as $order) {
            // Skip si un paiement est en cours de traitement (3DS)
            $has_pending_pi = false;
            try {
                $stmt = $pdo->prepare("
                    SELECT id FROM renewal_attempts
                    WHERE order_id = ? AND status = 'requires_action'
                      AND created_at >= DATE_SUB(NOW(), INTERVAL 48 HOUR)
                    LIMIT 1
                ");
                $stmt->execute([$order['id']]);
                $has_pending_pi = (bool)$stmt->fetchColumn();
            } catch (Exception $e) {}
            
            if ($has_pending_pi) {
                echo "[SKIP] {$order['service_name']} — 3DS en attente\n";
                continue;
            }

            // Suspendre sur le panel
            if (!empty($order['server_id'])) {
                panelPost($panel_url, $headers_admin, "servers/{$order['server_id']}/suspend");
            }
            
            // Marquer suspendu
            $pdo->prepare("
                UPDATE orders
                SET status='suspended',
                    suspended_at=NOW(),
                    delete_after=DATE_ADD(NOW(), INTERVAL 15 DAY)
                WHERE id=?
            ")->execute([$order['id']]);

            $due_date = date("d/m/Y", strtotime($order['next_payment_date']));
            sendRenewalDiscord($discord_webhook_url, $order['order_id'], $order['service_name'], $order['email'], $due_date, (float)$order['renewal_price'], 'suspended');
            
            // Email de suspension
            sendSuspensionEmail($order['email'], $order['firstname'] ?? 'Client', $order['service_name'], $due_date, $base_url . "/shop/order/renewal/?id=" . $order['id']);
            
            echo "[SUSPENDU] {$order['service_name']} — {$order['email']} — suppression le " . date("d/m/Y", strtotime('+15 days')) . "\n";
            $suspended++;
        }
        echo "[OK] {$suspended} serveur(s) suspendu(s)\n";
        break;

    // ══════════════════════════════════════════════════════════════
    // RÉACTIVATION (si renouvelé entre-temps, ex: après 3DS)
    // ══════════════════════════════════════════════════════════════
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
        echo "[OK] {$unsuspended} serveur(s) réactivé(s)\n";
        break;

    // ══════════════════════════════════════════════════════════════
    // SUPPRESSION DÉFINITIVE (suspended depuis +15 jours)
    // ══════════════════════════════════════════════════════════════
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
            if (!empty($order['server_id'])) {
                panelDelete($panel_url, $headers_admin, "servers/{$order['server_id']}");
            }
            $pdo->prepare("UPDATE orders SET status='deleted' WHERE id=?")->execute([$order['id']]);

            $due_date = date("d/m/Y", strtotime($order['next_payment_date']));
            sendRenewalDiscord($discord_webhook_url, $order['order_id'], $order['service_name'], $order['email'], $due_date, (float)$order['renewal_price'], 'expired');
            echo "[SUPPRIMÉ] {$order['service_name']} — {$order['email']}\n";
            $deleted++;
        }
        echo "[OK] {$deleted} serveur(s) supprimé(s)\n";
        break;

    default:
        echo "[ERREUR] Mode inconnu : {$mode}\n";
        exit(1);
}

// ══════════════════════════════════════════════════════════════
// Helpers d'envoi d'emails (stubs à implémenter)
// ══════════════════════════════════════════════════════════════
function sendActionRequiredEmail(string $email, string $name, string $service, float $amount, string $url): void {
    // TODO: email "Action requise : confirmez votre paiement 3DS"
    // Template suggéré :
    // "Bonjour $name, votre renouvellement de $service ($amount€) nécessite 
    //  une authentification 3D Secure. Cliquez ici pour confirmer : $url"
}

function sendRenewalFailedEmail(string $email, string $name, string $service, float $amount, string $reason, string $url): void {
    // TODO: email "Échec du prélèvement automatique"
    // Template suggéré :
    // "Nous n'avons pas pu prélever $amount€ pour $service. 
    //  Raison : $reason. Renouvelez manuellement : $url"
}

function sendSuspensionEmail(string $email, string $name, string $service, string $due_date, string $url): void {
    // TODO: email "Votre serveur a été suspendu"
}
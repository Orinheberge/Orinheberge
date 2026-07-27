<?php
/**
 * Webhook Stripe pour OrinHeberge
 * Gère la création automatique du serveur et de la facture après paiement réussi.
 * C'EST LA SEULE SOURCE DE VÉRITÉ pour le provisioning (voir success-handler.php).
 */
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/db.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/api/Facture.php'; // Votre fonction createInvoice()
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/smtp.php';        // Votre fonction send_order_confirmation_email()
require_once $_SERVER['DOCUMENT_ROOT'] . '/webhook/discord.php'; // Votre fonction sendDiscordWebhook()

// ⚠️ IMPORTANT : Incluez ici le fichier qui contient vos fonctions Pterodactyl
// (ex: require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/api/pterodactyl.php';)
// Assurez-vous que getOrCreatePanelUser() et createPanelServerWithAutoTransfer() sont disponibles.

// Récupérer les configurations
$ext_settings_raw = $pdo->query("SELECT e.slug, es.key, es.value FROM extension_settings es JOIN extensions e ON e.id = es.extension_id")->fetchAll();
$ext_cfg = [];
foreach ($ext_settings_raw as $r) $ext_cfg[$r['slug']][$r['key']] = $r['value'];

$stripe_secret_key = $ext_cfg['stripe']['secret_key'] ?? '';
$webhook_secret    = $ext_cfg['stripe']['webhook_secret'] ?? '';
$discord_webhook_url = $ext_cfg['discord']['webhook_url'] ?? '';
$panel_url = $ext_cfg['pterodactyl']['panel_url'] ?? '';
$api_key_admin = $ext_cfg['pterodactyl']['api_key_admin'] ?? '';

if (empty($stripe_secret_key) || empty($webhook_secret)) {
    http_response_code(500);
    exit('Configuration manquante');
}

\Stripe\Stripe::setApiKey($stripe_secret_key);

$payload    = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

try {
    $event = \Stripe\Webhook::constructEvent($payload, $sig_header, $webhook_secret);
} catch (\UnexpectedValueException $e) {
    http_response_code(400);
    exit();
} catch (\Stripe\Exception\SignatureVerificationException $e) {
    http_response_code(400);
    exit();
}

// ─── TRAITEMENT DU PAIEMENT RÉUSSI ─────────────────────────────
if ($event->type === 'payment_intent.succeeded') {
    $pi = $event->data->object;

    $order_id = $pi['metadata']['order_id'] ?? null;
    $user_id  = $pi['metadata']['user_id'] ?? null;
    $payment_intent_id = $pi['id'];

    if (!$order_id || !$user_id) {
        http_response_code(200);
        exit(); // Ignorer si pas de métadonnées
    }

    try {
        // 1. Vérifier si la commande n'est pas déjà traitée (Idempotence)
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_id = ? AND user_id = ? LIMIT 1");
        $stmt->execute([$order_id, $user_id]);
        $order = $stmt->fetch();

        if (!$order || $order['status'] === 'paid') {
            http_response_code(200);
            exit(); // Déjà traité, on arrête ici pour éviter les doublons
        }

        // 2. Récupérer l'utilisateur
        $user_stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $user_stmt->execute([$user_id]);
        $user = $user_stmt->fetch();
        if (!$user) {
            http_response_code(200);
            exit();
        }

        $username_display = !empty($user['pseudo']) ? $user['pseudo'] : $user['firstname'];
        $headers_admin = [
            "Authorization: Bearer $api_key_admin",
            "Accept: application/vnd.pterodactyl.v1+json",
            "Content-Type: application/json",
        ];

        // 3. Créer l'utilisateur et le serveur sur Pterodactyl
        $panelUser = getOrCreatePanelUser($panel_url, $headers_admin, $user, $pdo);
        $pass = $panelUser['pass'];

        $server_offer = [
            'name' => $order['service_name'],
            'ram' => $order['ram'],
            'disk' => $order['disk'],
            'cpu' => $order['cpu'],
            'panel_node_id' => 1 // Adaptez si vous stockez le node_id dans la commande
        ];

        $srv = createPanelServerWithAutoTransfer($panel_url, $headers_admin, $server_offer, $panelUser['id']);
        $new_order_id = strtoupper(substr(md5(uniqid('', true)), 0, 8));
        $next_pay = date("Y-m-01", strtotime("+1 month"));

        // 4. Enregistrer la nouvelle commande "paid" en BDD
        $pdo->prepare("
            INSERT INTO orders (user_id, product_id, order_id, service_name, ram, disk, cpu,
                server_id, uuid, id_server_panel, status, paypal_order_id,
                renewal_price, next_payment_date, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'paid', ?, ?, ?, NOW())
        ")->execute([
            $user_id, 0, $new_order_id, $order['service_name'],
            $order['ram'], $order['disk'], $order['cpu'],
            $srv['id'] ?? 0, $srv['uuid'] ?? '', $srv['identifier'] ?? '',
            $payment_intent_id, $order['renewal_price'], $next_pay
        ]);

        // 4bis. Relais one-shot du mot de passe panel vers success-handler.php
        //       (webhook.php ne redirige jamais l'utilisateur, il faut donc
        //       transmettre le mot de passe autrement que par la session)
        if ($pass) {
            $pdo->prepare("
                INSERT INTO pending_credentials (order_id, password, created_at)
                VALUES (?, ?, NOW())
                ON DUPLICATE KEY UPDATE password = VALUES(password), created_at = NOW()
            ")->execute([$new_order_id, $pass]);
        }

        // 5. Créer la facture payée
        $created_invoice = createInvoice($pdo, [
            'user_id' => $user_id,
            'order_id' => $new_order_id,
            'service_name' => $order['service_name'],
            'amount' => $order['renewal_price'],
            'type' => 'purchase',
            'status' => 'paid',
            'payment_method' => 'stripe',
            'payment_ref' => $payment_intent_id,
            'paid_at' => date('Y-m-d H:i:s'),
        ]);

        // 6. Envoyer les notifications
        send_order_confirmation_email(
            $pdo, $user['email'], $username_display,
            $new_order_id, $order['service_name'], $order['renewal_price'],
            $srv['identifier'] ?? '', $pass, $panel_url
        );

        if ($discord_webhook_url) {
            sendDiscordWebhook(
                $discord_webhook_url, $new_order_id, $order['service_name'],
                $order['renewal_price'], $user['email'], $srv['uuid'] ?? '', $srv['identifier'] ?? ''
            );
        }

        // 7. Nettoyer la commande "pending" initiale
        $pdo->prepare("DELETE FROM orders WHERE order_id = ? AND status = 'pending'")->execute([$order_id]);
        $pdo->prepare("DELETE FROM invoices WHERE order_id = ? AND status = 'pending'")->execute([$order_id]);

    } catch (Exception $e) {
        error_log('[Webhook] Erreur lors de la création du serveur: ' . $e->getMessage());
        // On retourne quand même 200 à Stripe pour qu'il n'essaie pas de re-envoyer indéfiniment
    }
}

// Toujours répondre 200 OK à Stripe
http_response_code(200);
exit();
?>
<?php
/**
 * Webhook Stripe pour OrinHeberge
 * Gère : payment_intent.succeeded, charge.refunded, payment_intent.payment_failed
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/db.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/api/Facture.php'; // Pour createInvoice()
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/smtp.php';       // Pour send_order_confirmation_email()
require_once $_SERVER['DOCUMENT_ROOT'] . '/webhook/discord.php'; // Pour sendDiscordWebhook()

// Récupérer les clés depuis extension_settings
$ext_settings_raw = $pdo->query("SELECT e.slug, es.key, es.value FROM extension_settings es JOIN extensions e ON e.id = es.extension_id")->fetchAll();
$ext_cfg = [];
foreach ($ext_settings_raw as $r) $ext_cfg[$r['slug']][$r['key']] = $r['value'];

$stripe_secret_key = $ext_cfg['stripe']['secret_key'] ?? '';
$webhook_secret    = $ext_cfg['stripe']['webhook_secret'] ?? '';
$discord_webhook_url = $ext_cfg['discord']['webhook_url'] ?? '';

if (empty($stripe_secret_key) || empty($webhook_secret)) {
    http_response_code(500);
    exit('Configuration Stripe manquante');
}

\Stripe\Stripe::setApiKey($stripe_secret_key);

// Vérification de la signature du webhook
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

switch ($event->type) {
    
    // ✅ Paiement réussi (Sécurité post-redirect)
    case 'payment_intent.succeeded':
        $pi = $event->data->object;
        $order_id = $pi['metadata']['order_id'] ?? null;
        $user_id  = $pi['metadata']['user_id'] ?? null;
        
        if (!$order_id || !$user_id) break;
        
        try {
            // Vérifier si la commande n'est pas déjà traitée (Idempotence)
            $stmt = $pdo->prepare("SELECT status FROM orders WHERE order_id = ? AND user_id = ? LIMIT 1");
            $stmt->execute([$order_id, $user_id]);
            $order = $stmt->fetch();
            
            if (!$order || $order['status'] === 'paid') break; // Déjà activé
            
            // Récupérer les infos utilisateur et produit
            $user_stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $user_stmt->execute([$user_id]);
            $user = $user_stmt->fetch();
            
            if (!$user) break;
            
            // --- EXÉCUTER LA LOGIQUE D'ACTIVATION ---
            // Note: Vous devrez peut-être adapter cette partie selon vos variables globales 
            // (panel_url, headers_admin, etc.) qui sont normalement dans order/index.php
            // Pour un webhook propre, il vaut mieux centraliser cette logique dans une fonction.
            
            require_once __DIR__ . '/lib/stripe/stripe.php'; // Si besoin de fonctions stripe locales
            
            // Exemple simplifié d'activation (à adapter avec votre logique complète)
            $username_display = !empty($user['pseudo']) ? $user['pseudo'] : $user['firstname'];
            
            // Création serveur Pterodactyl (nécessite vos fonctions getOrCreatePanelUser, etc.)
            // Assurez-vous que ces fonctions sont accessibles ici ou incluses
            if (function_exists('getOrCreatePanelUser') && function_exists('createPanelServerWithAutoTransfer')) {
                $panelUser = getOrCreatePanelUser($panel_url, $headers_admin, $user, $pdo);
                $pass      = $panelUser['pass'];
                
                // ... Logique de création de serveur ...
                // $srv = createPanelServerWithAutoTransfer(...);
                
                // Mise à jour commande en 'paid'
                $pdo->prepare("UPDATE orders SET status = 'paid', paypal_order_id = ? WHERE order_id = ?")
                    ->execute([$pi['id'], $order_id]);
                    
                // Création facture payée
                $created_invoice = createInvoice($pdo, [
                    'user_id'        => $user_id,
                    'order_id'       => $order_id,
                    'service_name'   => $order['service_name'],
                    'amount'         => $pi['amount'] / 100,
                    'type'           => 'purchase',
                    'status'         => 'paid',
                    'payment_method' => 'stripe',
                    'payment_ref'    => $pi['id'],
                    'paid_at'        => date('Y-m-d H:i:s'),
                ]);
                
                // Notifications
                send_order_confirmation_email($pdo, $user['email'], $username_display, $order_id, $order['service_name'], $pi['amount']/100, '', $pass ?? null, $panel_url);
                if ($discord_webhook_url) {
                    sendDiscordWebhook($discord_webhook_url, $order_id, $order['service_name'], $pi['amount']/100, $user['email'], '', '');
                }
            }
            
        } catch (Exception $e) {
            error_log('[Webhook] Erreur activation order #' . $order_id . ': ' . $e->getMessage());
        }
        break;

    //  Remboursement effectué via Stripe Dashboard
    case 'charge.refunded':
        $charge = $event->data->object;
        $pi_id  = $charge['payment_intent'] ?? null;
        
        if ($pi_id) {
            $stmt = $pdo->prepare("UPDATE orders SET status = 'refunded' WHERE paypal_order_id = ?");
            $stmt->execute([$pi_id]);
            
            // Optionnel: Notifier l'admin ou suspendre le serveur
            error_log("[Webhook] Remboursement détecté pour PI: {$pi_id}");
        }
        break;

    // ❌ Échec de paiement (ex: fonds insuffisants après tentative)
    case 'payment_intent.payment_failed':
        $pi = $event->data->object;
        $order_id = $pi['metadata']['order_id'] ?? null;
        
        if ($order_id) {
            error_log("[Webhook] Échec paiement pour order #{$order_id}: " . ($pi['last_payment_error']['message'] ?? 'Inconnu'));
            // Optionnel: Envoyer un email au client pour lui demander de réessayer
        }
        break;
}

// Toujours retourner 200 OK à Stripe
http_response_code(200);
exit();
?>
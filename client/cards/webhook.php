<?php
/**
 * Webhook Stripe pour OrinHeberge
 * Gère : setup_intent.succeeded, setup_intent.payment_failed, payment_method.detached
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/settings.php'; // Pour get_setting()

// Récupération dynamique des clés depuis la BDD
$stripe_secret_key = get_setting('stripe_secret_key');
$webhook_secret    = get_setting('stripe_webhook_secret');

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
    // Payload invalide
    http_response_code(400);
    exit();
} catch (\Stripe\Exception\SignatureVerificationException $e) {
    // Signature invalide
    http_response_code(400);
    exit();
}

// Connexion BDD locale
try {
    $pdo = new PDO('mysql:host=localhost;dbname=s43_orinheberge;charset=utf8mb4', 'root', '1504', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    // On retourne quand même 200 à Stripe pour éviter les retries infinies
    // mais on loggue l'erreur
    error_log('[Stripe Webhook] Erreur BDD: ' . $e->getMessage());
    http_response_code(200);
    exit();
}

switch ($event->type) {
    
    // ✅ Carte authentifiée avec succès (3D Secure validé par la banque)
    case 'setup_intent.succeeded':
        $setupIntent = $event->data->object;
        $paymentMethodId = $setupIntent->payment_method;
        $customerId      = $setupIntent->customer;
        
        try {
            // Récupérer le user_id associé au customer Stripe
            $stmt = $pdo->prepare('SELECT id FROM users WHERE stripe_customer_id = ? LIMIT 1');
            $stmt->execute([$customerId]);
            $user = $stmt->fetch();
            
            if (!$user) {
                error_log("[Stripe Webhook] Customer {$customerId} introuvable en BDD");
                break;
            }
            
            // Récupérer les détails de la carte depuis Stripe
            $pm = \Stripe\PaymentMethod::retrieve($paymentMethodId);
            
            // Sauvegarder/mettre à jour en base locale
            $stmt = $pdo->prepare('
                INSERT INTO user_stripe_cards 
                    (user_id, payment_method_id, card_brand, card_last4, card_exp_month, card_exp_year) 
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    card_brand = VALUES(card_brand),
                    card_last4 = VALUES(card_last4),
                    card_exp_month = VALUES(card_exp_month),
                    card_exp_year = VALUES(card_exp_year),
                    updated_at = NOW()
            ');
            
            $stmt->execute([
                $user['id'],
                $paymentMethodId,
                $pm->card->brand,
                $pm->card->last4,
                $pm->card->exp_month,
                $pm->card->exp_year
            ]);
            
            // Optionnel : Envoyer une notification au client
            // send_smtp_mail(... "Votre carte a été ajoutée avec succès" ...);
            
        } catch (Exception $e) {
            error_log('[Stripe Webhook setup_intent.succeeded] Erreur: ' . $e->getMessage());
        }
        break;

    // ❌ Échec de l'authentification 3D Secure ou carte refusée
    case 'setup_intent.payment_failed':
        $setupIntent = $event->data->object;
        $customerId  = $setupIntent->customer;
        $errorMsg    = $setupIntent->last_payment_error->message ?? 'Erreur inconnue';
        
        try {
            $stmt = $pdo->prepare('SELECT email, pseudo, firstname FROM users WHERE stripe_customer_id = ? LIMIT 1');
            $stmt->execute([$customerId]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Loguer l'échec pour audit
                error_log("[Stripe Webhook] Échec 3DS pour user #{$user['id']}: {$errorMsg}");
                
                // Optionnel : Notifier le client
                // send_smtp_mail($user['email'], "❌ Ajout de carte échoué", "...");
            }
        } catch (Exception $e) {
            error_log('[Stripe Webhook setup_intent.payment_failed] Erreur: ' . $e->getMessage());
        }
        break;

    // ️ Carte détachée/supprimée côté Stripe
    case 'payment_method.detached':
        $paymentMethodId = $event->data->object->id;
        
        try {
            $pdo->prepare('DELETE FROM user_stripe_cards WHERE payment_method_id = ?')
                ->execute([$paymentMethodId]);
        } catch (Exception $e) {
            error_log('[Stripe Webhook payment_method.detached] Erreur: ' . $e->getMessage());
        }
        break;

    // ️ Autres événements ignorés (pas pertinents pour la gestion de cartes)
    default:
        // Ne rien faire, retourner 200
        break;
}

// Toujours retourner 200 OK à Stripe pour confirmer la réception
http_response_code(200);
exit();
?>
<?php

/*
|--------------------------------------------------------------------------
| LIB RENOUVELLEMENT — OrinHeberge
|--------------------------------------------------------------------------
*/

/**
 * Récupère toutes les commandes qui arrivent à expiration
 * (dans les X jours à venir ou déjà expirées)
 */
function getExpiringOrders(PDO $pdo, int $days_before = 7): array {
    $stmt = $pdo->prepare("
        SELECT o.*, u.email, u.name, u.panel_password
        FROM orders o
        JOIN users u ON u.id = o.user_id
        WHERE o.status = 'paid'
        AND o.next_payment_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
        ORDER BY o.next_payment_date ASC
    ");
    $stmt->execute([$days_before]);
    return $stmt->fetchAll();
}

/**
 * Récupère les commandes expirées (non renouvelées)
 */
function getExpiredOrders(PDO $pdo): array {
    $stmt = $pdo->prepare("
        SELECT o.*, u.email, u.name
        FROM orders o
        JOIN users u ON u.id = o.user_id
        WHERE o.status = 'paid'
        AND o.next_payment_date < CURDATE()
        ORDER BY o.next_payment_date ASC
    ");
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Marque une commande comme suspendue (serveur à suspendre manuellement)
 */
function suspendOrder(PDO $pdo, int $order_row_id): void {
    $pdo->prepare("UPDATE orders SET status='suspended' WHERE id=?")
        ->execute([$order_row_id]);
}

/**
 * Renouvelle une commande après paiement confirmé
 */
function renewOrder(PDO $pdo, int $order_row_id, string $payment_ref): void {
    $next = date("Y-m-01", strtotime("+1 month"));
    $pdo->prepare("
        UPDATE orders
        SET status='paid',
            next_payment_date=?,
            paypal_order_id=?,
            renewed_at=NOW()
        WHERE id=?
    ")->execute([$next, $payment_ref, $order_row_id]);
}

/**
 * Envoie un email de rappel de renouvellement
 */
function sendRenewalEmail(string $to, string $name, string $service, float $price, string $due_date, string $renew_url): void {
    $subject = "⚠️ Renouvellement requis — " . $service . " — OrinHeberge";

    $body = "Bonjour " . $name . ",\n\n"
        . "Votre service \"" . $service . "\" arrive à expiration le " . $due_date . ".\n"
        . "Montant : " . number_format($price, 2, '.', '') . "€\n\n"
        . "Renouvelez maintenant : " . $renew_url . "\n\n"
        . "Sans renouvellement, votre serveur sera suspendu automatiquement à la date d'expiration.\n\n"
        . "— OrinHeberge";

    $headers = "From: deepstone@deepstone.fr\r\n"
             . "Content-Type: text/plain; charset=UTF-8\r\n";

    mail($to, $subject, $body, $headers);
}

/**
 * Envoie une notification Discord pour un renouvellement
 */
function sendRenewalDiscord(string $webhook_url, string $order_id, string $service, string $email, string $due_date, float $price, string $status): void {
    if (empty($webhook_url)) return;

    $colors = [
        'expiring'  => hexdec("f59e0b"), // Ambre — expire bientôt
        'expired'   => hexdec("ef4444"), // Rouge — expiré
        'renewed'   => hexdec("22c55e"), // Vert — renouvelé
        'suspended' => hexdec("6b7280"), // Gris — suspendu
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
                ["name" => "📦 Service",      "value" => $service,                                         "inline" => true],
                ["name" => "💰 Montant",      "value" => number_format($price, 2, '.', '') . "€",          "inline" => true],
                ["name" => "📅 Échéance",     "value" => $due_date,                                        "inline" => true],
                ["name" => "🔢 Commande",     "value" => "#" . $order_id,                                  "inline" => true],
                ["name" => "📧 Client",       "value" => $email,                                           "inline" => false],
            ]
        ]]
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    $ch = curl_init($webhook_url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $json,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    curl_exec($ch);
}

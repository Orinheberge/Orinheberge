<?php
/**
 * Crée une Stripe Checkout Session et retourne l'URL de paiement
 */
function createStripeSession(string $secret_key, array $offer, string $type, string $success_url, string $cancel_url): array {
    $ch = curl_init("https://api.stripe.com/v1/checkout/sessions");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_USERPWD        => $secret_key . ":",
        CURLOPT_POSTFIELDS     => http_build_query([
            "mode"                                           => "payment",
            "line_items[0][price_data][currency]"            => "eur",
            "line_items[0][price_data][unit_amount]"         => (int)($offer['price'] * 100),
            "line_items[0][price_data][product_data][name]"  => $offer['name'] . " - OrinHeberge",
            "line_items[0][quantity]"                        => 1,
            "success_url"                                    => $success_url,
            "cancel_url"                                     => $cancel_url,
            "locale"                                         => "fr",
        ]),
        CURLOPT_HTTPHEADER     => ["Content-Type: application/x-www-form-urlencoded"],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    $raw    = curl_exec($ch);
    $result = json_decode($raw, true);

    if (empty($result['url'])) {
        $err = $result['error']['message'] ?? json_encode($result);
        error_log('Stripe create session error: ' . $err);
        throw new Exception('Stripe error: ' . $err);
    }

    return [
        'session_id'   => $result['id'],
        'checkout_url' => $result['url'],
    ];
}

/**
 * Récupère une Stripe Checkout Session pour vérifier le statut
 */
function getStripeSession(string $secret_key, string $session_id): array {
    $ch = curl_init("https://api.stripe.com/v1/checkout/sessions/" . urlencode($session_id));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD        => $secret_key . ":",
        CURLOPT_HTTPHEADER     => ["Content-Type: application/x-www-form-urlencoded"],
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    $raw    = curl_exec($ch);
    $result = json_decode($raw, true);

    return is_array($result) ? $result : [];
}

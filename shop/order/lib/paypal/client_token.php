<?php
/**
 * Retourne un client_token PayPal pour initialiser le SDK v6
 */
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Non connecté']);
    exit();
}

require_once __DIR__ . '/paypal.php';

header('Content-Type: application/json');

$paypal_client_id = "ARI3WMG4qWvv3nbqAMZhoprWVhSnV4TbizwdSJGspS-wW7KEAdfGpFrLXQ4pbh_fo-IRIpK1DKdd-y4q";
$paypal_secret    = "EE98LC-AO1hA_3xmGi-JENzFYo_acJyW9LBwYpCrpO-1mURpWXbwTSy6aAxaXY18m8XmOPOf94qw04zm";

$token = getPayPalToken($paypal_client_id, $paypal_secret);

// Générer un client token pour le SDK frontend
$ch = curl_init("https://api-m.paypal.com/v1/identity/generate-token");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => [
        "Authorization: Bearer " . $token,
        "Content-Type: application/json",
        "Accept: application/json",
    ],
    CURLOPT_POSTFIELDS     => '{}',
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
]);

$raw    = curl_exec($ch);
$result = json_decode($raw, true);

echo json_encode([
    'client_token' => $result['client_token'] ?? null,
    'error'        => empty($result['client_token']) ? ($result['message'] ?? 'Token indisponible') : null
]);

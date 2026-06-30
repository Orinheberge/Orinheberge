<?php
/**
 * Crée une commande PayPal via l'API REST et retourne l'order_id au SDK frontend
 */
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Non connecté']);
    exit();
}

require_once __DIR__ . '/paypal.php';
// offers chargés via input JSON

header('Content-Type: application/json');

$paypal_client_id = "ARI3WMG4qWvv3nbqAMZhoprWVhSnV4TbizwdSJGspS-wW7KEAdfGpFrLXQ4pbh_fo-IRIpK1DKdd-y4q";
$paypal_secret    = "EE98LC-AO1hA_3xmGi-JENzFYo_acJyW9LBwYpCrpO-1mURpWXbwTSy6aAxaXY18m8XmOPOf94qw04zm";

$input = json_decode(file_get_contents('php://input'), true);
$plan  = strtolower(trim($input['plan'] ?? ''));
$price = $input['price'] ?? '0.00';
$name  = $input['name']  ?? 'Offre Premium';

$token = getPayPalToken($paypal_client_id, $paypal_secret);

$order = paypalRequest(
    "https://api-m.paypal.com/v2/checkout/orders",
    $token,
    [
        "intent" => "CAPTURE",
        "purchase_units" => [[
            "description" => $name . " - OrinHeberge",
            "amount"      => [
                "currency_code" => "EUR",
                "value"         => number_format((float)$price, 2, '.', '')
            ]
        ]]
    ]
);

if (!empty($order['id'])) {
    echo json_encode(['order_id' => $order['id']]);
} else {
    $err = $order['message'] ?? json_encode($order);
    echo json_encode(['error' => $err]);
}

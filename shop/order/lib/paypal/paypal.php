<?php
/**
 * Génère un lien PayPal.me avec le montant pré-rempli
 */
function getPaypalMeLink(string $paypalme_username, float $amount, string $currency = "EUR"): string {
    return "https://www.paypal.me/" . urlencode($paypalme_username) . "/" . number_format($amount, 2, '.', '') . $currency;
}

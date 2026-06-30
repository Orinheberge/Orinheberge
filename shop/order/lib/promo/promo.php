<?php

/*
|--------------------------------------------------------------------------
| LIB PROMOS — OrinHeberge
| Ajouter / modifier les promos ici
|--------------------------------------------------------------------------
*/

$promos = [
    // ─── SAINT VALENTIN ──────────────────────────────────────────────────
    "valentin" => [
        "name"       => "❤️ Saint-Valentin",
        "code"       => "AMOUR14",
        "discount"   => 2,
        "type"       => "fixed",     // 2€ de réduction
        "start"      => "2026-02-14",
        "end"        => "2026-02-14",
        "active"     => true,
        "applies_to" => [],
    ],

    // ─── PÂQUES ──────────────────────────────────────────────────────────
    "paques" => [
        "name"       => "🐣 Pâques",
        "code"       => "PAQUES",
        "discount"   => 10,
        "type"       => "percent",
        "start"      => "2026-04-05",
        "end"        => "2026-04-06",
        "active"     => true,
        "applies_to" => [],
    ],

    // ─── ANNIVERSAIRE ORINHEBERGE ────────────────────────────────────────
    "anniv" => [
        "name"       => "🎂 Anniversaire OrinHeberge",
        "code"       => "ANNIV1AN",
        "discount"   => 25,
        "type"       => "percent",
        "start"      => "2026-06-01",
        "end"        => "2026-06-01",
        "active"     => true,
        "applies_to" => [],
    ],

    // ─── ÉTÉ 2026 ────────────────────────────────────────────────────────
    "ete" => [
        "name"       => "☀️ Été 2026",
        "code"       => "ETE2026",
        "discount"   => 50,
        "type"       => "percent",
        "start"      => "2026-06-26",
        "end"        => "2026-08-30",
        "active"     => true,
        "applies_to" => [],
    ],

    // ─── 14 JUILLET ─────────────────────────────────────────────────────
    "bastille" => [
        "name"       => "🇫🇷 Fête Nationale",
        "code"       => "FRANCE14",
        "discount"   => 14,
        "type"       => "percent",
        "start"      => "2026-07-14",
        "end"        => "2026-07-14",
        "active"     => true,
        "applies_to" => [],
    ],

    // ─── HALLOWEEN ───────────────────────────────────────────────────────
    "halloween" => [
        "name"       => "🎃 Halloween",
        "code"       => "HALLOWEEN",
        "discount"   => 10,
        "type"       => "percent",
        "start"      => "2026-10-31",
        "end"        => "2026-10-31",
        "active"     => true,
        "applies_to" => [],
    ],

    // ─── BLACK FRIDAY ────────────────────────────────────────────────────
    "blackfriday" => [
        "name"       => "🖤 Black Friday",
        "code"       => "BLACKFRIDAY",
        "discount"   => 30,
        "type"       => "percent",
        "start"      => "2026-11-27",
        "end"        => "2026-11-30",
        "active"     => true,
        "applies_to" => [],
    ],

    // ─── NOËL ───────────────────────────────────────────────────────────
    "noel" => [
        "name"       => "🎄 Offre de Noël",
        "code"       => "NOEL2026",
        "discount"   => 20,
        "type"       => "percent",
        "start"      => "2026-12-20",
        "end"        => "2026-12-26",
        "active"     => true,
        "applies_to" => [],
    ],

    // ─── NOUVEL AN ──────────────────────────────────────────────────────
    "nouvel_an" => [
        "name"       => "🎆 Bonne Année",
        "code"       => "BONNEAN2027",
        "discount"   => 15,
        "type"       => "percent",
        "start"      => "2026-12-31",
        "end"        => "2027-01-02",
        "active"     => true,
        "applies_to" => [],
    ],
];

/*
|--------------------------------------------------------------------------
| FONCTIONS
|--------------------------------------------------------------------------
*/

/**
 * Retourne la promo active automatiquement (Sélectionne la plus avantageuse si conflit)
 */
function getActiveAutoPromo(array $promos): ?array {
    $today = date("Y-m-d");
    $best_promo = null;

    foreach ($promos as $key => $promo) {
        if (!$promo['active']) continue;
        if ($today >= $promo['start'] && $today <= $promo['end']) {
            if ($best_promo === null || $promo['discount'] > $best_promo['discount']) {
                $best_promo = array_merge($promo, ['key' => $key]);
            }
        }
    }
    return $best_promo;
}

/**
 * Vérifie un code promo saisi manuellement
 */
function checkPromoCode(array $promos, string $code, string $offer_type): ?array {
    // Remplacement des espaces insécables éventuels puis nettoyage global
    $code = preg_replace('/\s+/u', '', $code);
    $code = strtoupper(trim($code));
    $today = date("Y-m-d");

    foreach ($promos as $key => $promo) {
        if (!$promo['active']) continue;
        if (strtoupper(trim($promo['code'])) !== $code) continue;
        if ($today < $promo['start'] || $today > $promo['end']) continue;
        if (!empty($promo['applies_to']) && !in_array($offer_type, $promo['applies_to'])) continue;
        
        return array_merge($promo, ['key' => $key]);
    }
    return null;
}

/**
 * Calcule le prix après réduction
 */
function applyPromo(float $price, array $promo): array {
    if ($promo['type'] === 'percent') {
        $reduction    = round($price * $promo['discount'] / 100, 2);
        $final_price  = round($price - $reduction, 2);
    } else {
        $reduction    = min((float)$promo['discount'], $price);
        $final_price  = round($price - $reduction, 2);
    }

    return [
        'original_price' => $price,
        'reduction'      => $reduction,
        'final_price'    => max(0.50, $final_price),
        'label'          => $promo['type'] === 'percent'
                            ? "-" . $promo['discount'] . "%"
                            : "-" . number_format($promo['discount'], 2, '.', '') . "€",
    ];
}
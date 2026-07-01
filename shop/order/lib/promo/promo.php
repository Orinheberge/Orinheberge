<?php

/*
|--------------------------------------------------------------------------
| LIB PROMOS — OrinHeberge
| Ajouter / modifier les promos ici
|--------------------------------------------------------------------------
*/

function loadPromosFromDatabase(PDO $pdo): array {
    try {
        $check = $pdo->query("SHOW TABLES LIKE 'promos'");
        if (!$check || !$check->fetch()) {
            return [];
        }

        $stmt = $pdo->query('SELECT id, slug, name, code, discount, type, start_date, end_date, is_active FROM promos WHERE is_active = 1 ORDER BY id');
        $rows = $stmt->fetchAll();
        if (empty($rows)) {
            return [];
        }

        $promoIds = array_map(static fn($row) => (int)$row['id'], $rows);
        $placeholders = implode(',', array_fill(0, count($promoIds), '?'));

        $appliesStmt = $pdo->prepare('SELECT promo_id, offer_type FROM promo_applies_to WHERE promo_id IN (' . $placeholders . ')');
        $appliesStmt->execute($promoIds);
        $applies = [];
        foreach ($appliesStmt->fetchAll() as $row) {
            $applies[(int)$row['promo_id']][] = $row['offer_type'];
        }

        $promos = [];
        foreach ($rows as $row) {
            $promos[$row['slug']] = [
                'name' => $row['name'],
                'code' => $row['code'],
                'discount' => (float)$row['discount'],
                'type' => $row['type'],
                'start' => $row['start_date'],
                'end' => $row['end_date'],
                'active' => (bool)$row['is_active'],
                'applies_to' => $applies[(int)$row['id']] ?? [],
            ];
        }

        return $promos;
    } catch (PDOException $e) {
        return [];
    }
}

$promos = [];

if (file_exists(dirname(__DIR__, 4) . '/inc/db.php')) {
    require_once dirname(__DIR__, 4) . '/inc/db.php';
    if (isset($pdo)) {
        $promos = loadPromosFromDatabase($pdo);
    }
}

if ($promos === []) {
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
}

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
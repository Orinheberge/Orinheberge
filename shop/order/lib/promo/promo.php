<?php

/*
|--------------------------------------------------------------------------
| LIB PROMOS — OrinHeberge
| Gestion 100% via la base de données (admin panel)
|--------------------------------------------------------------------------
*/

function loadPromosFromDatabase(PDO $pdo): array {
    try {
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
                'name'       => $row['name'],
                'code'       => $row['code'],
                'discount'   => (float)$row['discount'],
                'type'       => $row['type'],
                'start'      => $row['start_date'],
                'end'        => $row['end_date'],
                'active'     => (bool)$row['is_active'],
                'applies_to' => $applies[(int)$row['id']] ?? [],
            ];
        }

        return $promos;
    } catch (PDOException $e) {
        // error_log('loadPromosFromDatabase: ' . $e->getMessage());
        return [];
    }
}

$promos = [];

$dbPath = $_SERVER['DOCUMENT_ROOT'] . '/inc/db.php';

if (file_exists($dbPath)) {
    require_once $dbPath;
}

if (isset($pdo) && $pdo instanceof PDO) {
    $promos = loadPromosFromDatabase($pdo);
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

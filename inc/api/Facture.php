<?php
/**
 * API Factures - /inc/api/facture.php
 * Fonctions utilitaires pour la gestion des factures
 */

/**
 * Génère un identifiant de facture unique (format: INV-YYYY-XXXXX)
 * Exemple: INV-2026-00001, INV-2026-00002, ...
 */
function generateInvoiceId(PDO $pdo): string {
    $year = date('Y');
    
    // Récupère le plus grand numéro pour l'année en cours
    $stmt = $pdo->prepare("
        SELECT invoice_id 
        FROM invoices 
        WHERE invoice_id LIKE ? 
        ORDER BY invoice_id DESC 
        LIMIT 1
    ");
    $stmt->execute(["INV-{$year}-%"]);
    $last = $stmt->fetchColumn();
    
    if ($last) {
        // Extrait le numéro séquentiel et l'incrémente
        $parts = explode('-', $last);
        $nextNum = (int)end($parts) + 1;
    } else {
        $nextNum = 1;
    }
    
    return sprintf('INV-%s-%05d', $year, $nextNum);
}

/**
 * Crée une facture dans la base de données
 *
 * @param PDO    $pdo             Connexion PDO
 * @param array  $data            Données de la facture :
 *   - user_id       (int)    ID de l'utilisateur (requis)
 *   - order_id      (string) ID de la commande (requis)
 *   - service_name  (string) Nom du service (requis)
 *   - amount        (float)  Montant TTC (requis)
 *   - type          (string) 'purchase' | 'renewal' (défaut: 'purchase')
 *   - status        (string) 'paid' | 'pending' | 'refunded' (défaut: 'pending')
 *   - payment_method(string) 'stripe' | 'paypal' | null
 *   - payment_ref   (string) Référence de paiement (session_id, etc.)
 *   - due_date      (string) Date d'échéance (Y-m-d)
 *   - paid_at       (string) Date de paiement (Y-m-d H:i:s)
 *
 * @return array|false  Retourne les données de la facture créée, ou false en cas d'erreur
 */
function createInvoice(PDO $pdo, array $data): array|false {
    // Validation des champs requis
    $required = ['user_id', 'order_id', 'service_name', 'amount'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || $data[$field] === '' || $data[$field] === null) {
            error_log("[createInvoice] Champ manquant: {$field}");
            return false;
        }
    }
    
    // Vérifie que l'utilisateur existe
    $userStmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
    $userStmt->execute([$data['user_id']]);
    if (!$userStmt->fetch()) {
        error_log("[createInvoice] Utilisateur introuvable: " . $data['user_id']);
        return false;
    }
    
    // Vérifie que la commande existe
    $orderStmt = $pdo->prepare("SELECT order_id FROM orders WHERE order_id = ?");
    $orderStmt->execute([$data['order_id']]);
    if (!$orderStmt->fetch()) {
        error_log("[createInvoice] Commande introuvable: " . $data['order_id']);
        return false;
    }
    
    // Génère un invoice_id unique
    $invoice_id = generateInvoiceId($pdo);
    
    // Valeurs par défaut
    $type           = $data['type']           ?? 'purchase';
    $status         = $data['status']         ?? 'pending';
    $payment_method = $data['payment_method'] ?? null;
    $payment_ref    = $data['payment_ref']    ?? null;
    $due_date       = $data['due_date']       ?? null;
    $paid_at        = $data['paid_at']        ?? null;
    $amount         = (float)$data['amount'];
    
    // Insertion en base
    try {
        $stmt = $pdo->prepare("
            INSERT INTO invoices (
                invoice_id, user_id, order_id, service_name,
                amount, type, status, payment_method,
                payment_ref, due_date, paid_at, created_at
            ) VALUES (
                :invoice_id, :user_id, :order_id, :service_name,
                :amount, :type, :status, :payment_method,
                :payment_ref, :due_date, :paid_at, NOW()
            )
        ");
        
        $stmt->execute([
            ':invoice_id'     => $invoice_id,
            ':user_id'        => (int)$data['user_id'],
            ':order_id'       => $data['order_id'],
            ':service_name'   => $data['service_name'],
            ':amount'         => $amount,
            ':type'           => $type,
            ':status'         => $status,
            ':payment_method' => $payment_method,
            ':payment_ref'    => $payment_ref,
            ':due_date'       => $due_date,
            ':paid_at'        => $paid_at,
        ]);
        
        $id = $pdo->lastInsertId();
        
        // Récupère la facture créée
        $fetch = $pdo->prepare("SELECT * FROM invoices WHERE id = ?");
        $fetch->execute([$id]);
        return $fetch->fetch(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("[createInvoice] Erreur SQL: " . $e->getMessage());
        return false;
    }
}

/**
 * Marque une facture comme payée
 *
 * @param PDO    $pdo
 * @param string $invoice_id     Identifiant de facture (ex: INV-2026-00001)
 * @param string $payment_method Méthode de paiement ('stripe' | 'paypal')
 * @param string|null $payment_ref Référence de paiement
 * @return bool
 */
function markInvoiceAsPaid(PDO $pdo, string $invoice_id, string $payment_method, ?string $payment_ref = null): bool {
    try {
        $stmt = $pdo->prepare("
            UPDATE invoices 
            SET status = 'paid',
                payment_method = :payment_method,
                payment_ref = :payment_ref,
                paid_at = NOW()
            WHERE invoice_id = :invoice_id AND status != 'paid'
        ");
        return $stmt->execute([
            ':invoice_id'     => $invoice_id,
            ':payment_method' => $payment_method,
            ':payment_ref'    => $payment_ref,
        ]);
    } catch (PDOException $e) {
        error_log("[markInvoiceAsPaid] Erreur: " . $e->getMessage());
        return false;
    }
}

/**
 * Récupère toutes les factures d'un utilisateur
 */
function getUserInvoices(PDO $pdo, int $user_id, ?string $status = null): array {
    $sql = "SELECT * FROM invoices WHERE user_id = ?";
    $params = [$user_id];
    
    if ($status !== null) {
        $sql .= " AND status = ?";
        $params[] = $status;
    }
    
    $sql .= " ORDER BY created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
} 

?>
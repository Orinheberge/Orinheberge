<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: /login/'); exit(); }

require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/db.php';

$invoice_id = trim($_GET['id'] ?? '');
if (!$invoice_id) { header('Location: /client/billing/'); exit(); }

$inv_stmt = $pdo->prepare("SELECT * FROM invoices WHERE invoice_id=? AND user_id=?");
$inv_stmt->execute([$invoice_id, $_SESSION['user_id']]);
$inv = $inv_stmt->fetch();
if (!$inv) { header('Location: /client/billing/'); exit(); }

$usr_stmt = $pdo->prepare("SELECT firstname, lastname, email FROM users WHERE id=?");
$usr_stmt->execute([$_SESSION['user_id']]);
$usr = $usr_stmt->fetch();

$ord_stmt = $pdo->prepare("SELECT * FROM orders WHERE order_id=?");
$ord_stmt->execute([$inv['order_id']]);
$order = $ord_stmt->fetch();

$type_label = match($inv['type']) {
    'renewal'  => 'Renouvellement mensuel',
    'purchase' => 'Achat initial',
    default    => ucfirst($inv['type']),
};
$pay_label = match($inv['payment_method'] ?? '') {
    'stripe' => 'Carte bancaire (Stripe)',
    'paypal' => 'PayPal.me',
    default  => 'Manuel / Autre',
};
$status_label = match($inv['status']) {
    'paid'     => ['label' => 'PAYÉE',      'color' => '#22c55e', 'bg' => '#052e16'],
    'pending'  => ['label' => 'EN COURS',   'color' => '#f97316', 'bg' => '#431407'],
    'refunded' => ['label' => 'REMBOURSÉE', 'color' => '#38bdf8', 'bg' => '#0c1a2e'],
    default    => ['label' => 'INCONNU',    'color' => '#9ca3af', 'bg' => '#111827'],
};
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facture <?php echo htmlspecialchars($inv['invoice_id']); ?> — OrinHeberge</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, 'Segoe UI', system-ui, sans-serif;
            background: #ffffff;
            color: #1a1a2e;
            font-size: 13px;
            line-height: 1.5;
        }
        .page {
            max-width: 720px;
            margin: 0 auto;
            padding: 48px 40px;
        }

        /* Header */
        .header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            border-bottom: 2px solid #e5e7eb;
            padding-bottom: 28px;
            margin-bottom: 28px;
        }
        .logo-block { display: flex; align-items: center; gap: 10px; }
        .logo-icon {
            width: 42px; height: 42px;
            background: #0ea5e9;
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
        }
        .logo-icon svg { width: 22px; height: 22px; fill: #fff; }
        .logo-name { font-size: 20px; font-weight: 900; color: #0f172a; letter-spacing: -0.5px; }
        .logo-sub  { font-size: 11px; color: #6b7280; margin-top: 2px; }
        .invoice-meta { text-align: right; }
        .invoice-number { font-size: 22px; font-weight: 900; color: #0f172a; font-family: monospace; }
        .invoice-date { font-size: 11px; color: #6b7280; margin-top: 4px; }
        .status-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 0.08em;
            margin-top: 8px;
            background: <?php echo $status_label['bg']; ?>;
            color: <?php echo $status_label['color']; ?>;
            border: 1px solid <?php echo $status_label['color']; ?>44;
        }

        /* Parties */
        .parties { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 28px; }
        .party-box {
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            padding: 16px 18px;
        }
        .party-label {
            font-size: 9px; font-weight: 800; letter-spacing: 0.12em;
            text-transform: uppercase; color: #9ca3af; margin-bottom: 8px;
        }
        .party-name { font-size: 14px; font-weight: 700; color: #0f172a; }
        .party-detail { font-size: 11px; color: #6b7280; margin-top: 3px; }
        .party-mono { font-family: monospace; font-size: 10px; color: #9ca3af; margin-top: 2px; }

        /* Table */
        .table-section { margin-bottom: 24px; }
        table { width: 100%; border-collapse: collapse; border-radius: 10px; overflow: hidden; border: 1px solid #e5e7eb; }
        thead tr { background: #f1f5f9; }
        thead th {
            padding: 10px 14px; text-align: left;
            font-size: 10px; font-weight: 700; letter-spacing: 0.08em;
            text-transform: uppercase; color: #6b7280;
        }
        thead th:last-child { text-align: right; }
        tbody td {
            padding: 14px 14px;
            border-top: 1px solid #f1f5f9;
            font-size: 13px; color: #374151;
        }
        tbody td:last-child { text-align: right; font-weight: 700; }
        .service-name { font-weight: 600; color: #0f172a; }
        .service-sub  { font-size: 10px; color: #9ca3af; margin-top: 3px; font-family: monospace; }
        td.qty { text-align: center; color: #9ca3af; }

        /* Totaux */
        .totals {
            display: flex; justify-content: flex-end; margin-bottom: 28px;
        }
        .totals-box { min-width: 240px; }
        .total-row {
            display: flex; justify-content: space-between;
            padding: 5px 0; font-size: 12px; color: #6b7280;
        }
        .total-row.total-final {
            border-top: 2px solid #e5e7eb;
            margin-top: 6px; padding-top: 10px;
            font-size: 16px; font-weight: 900; color: #0f172a;
        }

        /* Payment info */
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 28px; }
        .info-box {
            background: #f8fafc; border: 1px solid #e5e7eb;
            border-radius: 10px; padding: 14px 16px;
        }
        .info-label { font-size: 9px; font-weight: 800; letter-spacing: 0.12em; text-transform: uppercase; color: #9ca3af; margin-bottom: 6px; }
        .info-value { font-size: 12px; color: #374151; font-weight: 500; }
        .info-mono  { font-family: monospace; font-size: 10px; color: #9ca3af; margin-top: 3px; word-break: break-all; }

        /* Footer */
        .footer {
            border-top: 1px solid #e5e7eb; padding-top: 18px;
            text-align: center; font-size: 10px; color: #9ca3af;
        }
        .footer strong { color: #6b7280; }

        /* Print btn */
        .print-btn {
            position: fixed; top: 16px; right: 16px;
            background: #0ea5e9; color: #fff; border: none;
            padding: 8px 18px; border-radius: 8px;
            font-size: 12px; font-weight: 700; cursor: pointer;
            display: flex; align-items: center; gap: 6px;
            box-shadow: 0 4px 12px rgba(14,165,233,.4);
        }
        .print-btn:hover { background: #0284c7; }
        @media print {
            .print-btn { display: none !important; }
            body { background: #fff; }
            .page { padding: 20px; }
        }
    </style>
</head>
<body>

<button class="print-btn" onclick="window.print()">
    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
        <path d="M5 1a2 2 0 0 0-2 2v1h10V3a2 2 0 0 0-2-2H5zm6 8H5a1 1 0 0 0-1 1v3a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1v-3a1 1 0 0 0-1-1z"/>
        <path d="M0 7a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2h-1v-2a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v2H2a2 2 0 0 1-2-2V7zm2.5 1a.5.5 0 1 0 0-1 .5.5 0 0 0 0 1z"/>
    </svg>
    Imprimer / PDF
</button>

<div class="page">

    <!-- Header -->
    <div class="header">
        <div class="logo-block">
            <div class="logo-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                    <path d="M3 5a2 2 0 012-2h10a2 2 0 012 2v1H3V5zm0 3h14v2a2 2 0 01-2 2H5a2 2 0 01-2-2V8zm0 5h14v2a2 2 0 01-2 2H5a2 2 0 01-2-2v-2z"/>
                </svg>
            </div>
            <div>
                <div class="logo-name">OrinHeberge</div>
                <div class="logo-sub">Infrastructure OrinStone</div>
            </div>
        </div>
        <div class="invoice-meta">
            <div class="invoice-number"><?php echo htmlspecialchars($inv['invoice_id']); ?></div>
            <div class="invoice-date">Émise le <?php echo date('d/m/Y', strtotime($inv['created_at'])); ?></div>
            <?php if ($inv['paid_at']): ?>
            <div class="invoice-date">Payée le <?php echo date('d/m/Y', strtotime($inv['paid_at'])); ?></div>
            <?php endif; ?>
            <div class="status-badge"><?php echo $status_label['label']; ?></div>
        </div>
    </div>

    <!-- Parties -->
    <div class="parties">
        <div class="party-box">
            <div class="party-label">Émetteur</div>
            <div class="party-name">OrinHeberge</div>
            <div class="party-detail">Infrastructure OrinStone</div>
            <div class="party-detail">heberge.orinstone.deepstone.fr</div>
            <div class="party-mono">deepstone@deepstone.fr</div>
        </div>
        <div class="party-box">
            <div class="party-label">Facturé à</div>
            <div class="party-name"><?php echo htmlspecialchars(trim(($usr['firstname'] ?? '') . ' ' . ($usr['lastname'] ?? ''))); ?></div>
            <div class="party-detail"><?php echo htmlspecialchars($usr['email'] ?? ''); ?></div>
            <div class="party-mono">Client #<?php echo $_SESSION['user_id']; ?></div>
        </div>
    </div>

    <!-- Table détail -->
    <div class="table-section">
        <table>
            <thead>
                <tr>
                    <th style="width:55%">Description</th>
                    <th style="width:10%;text-align:center">Qté</th>
                    <th style="width:17%">P.U. HT</th>
                    <th style="width:18%">Total TTC</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <div class="service-name"><?php echo htmlspecialchars($inv['service_name']); ?></div>
                        <div class="service-sub"><?php echo $type_label; ?> · Commande #<?php echo htmlspecialchars($inv['order_id']); ?></div>
                    </td>
                    <td class="qty">1</td>
                    <td><?php echo number_format((float)$inv['amount'], 2, ',', ''); ?>&nbsp;€</td>
                    <td><?php echo number_format((float)$inv['amount'], 2, ',', ''); ?>&nbsp;€</td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Totaux -->
    <div class="totals">
        <div class="totals-box">
            <div class="total-row"><span>Sous-total HT</span><span><?php echo number_format((float)$inv['amount'], 2, ',', ''); ?>&nbsp;€</span></div>
            <div class="total-row"><span>TVA (0%)</span><span>0,00&nbsp;€</span></div>
            <div class="total-row total-final"><span>Total TTC</span><span><?php echo number_format((float)$inv['amount'], 2, ',', ''); ?>&nbsp;€</span></div>
        </div>
    </div>

    <!-- Informations paiement -->
    <div class="info-grid">
        <div class="info-box">
            <div class="info-label">Moyen de paiement</div>
            <div class="info-value"><?php echo $pay_label; ?></div>
            <?php if ($inv['payment_ref']): ?>
            <div class="info-mono"><?php echo htmlspecialchars(substr($inv['payment_ref'], 0, 50)); ?></div>
            <?php endif; ?>
        </div>
        <?php if ($order): ?>
        <div class="info-box">
            <div class="info-label">Serveur hébergé</div>
            <div class="info-value"><?php echo htmlspecialchars($order['service_name']); ?></div>
            <?php if ($order['id_server_panel']): ?>
            <div class="info-mono"><?php echo htmlspecialchars($order['id_server_panel']); ?></div>
            <?php endif; ?>
            <?php if ($order['next_payment_date']): ?>
            <div class="info-value" style="font-size:11px;margin-top:4px;color:#9ca3af;">Prochain paiement : <?php echo date('d/m/Y', strtotime($order['next_payment_date'])); ?></div>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="info-box">
            <div class="info-label">Référence commande</div>
            <div class="info-mono" style="font-size:12px;color:#374151;">#<?php echo htmlspecialchars($inv['order_id']); ?></div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <div class="footer">
        <strong>OrinHeberge</strong> — Infrastructure OrinStone · Tous droits réservés<br>
        Ce document est une facture électronique générée automatiquement. Conservez-la pour vos archives.<br>
        <strong><?php echo htmlspecialchars($inv['invoice_id']); ?></strong> · <?php echo date('d/m/Y H:i', strtotime($inv['created_at'])); ?> · Client #<?php echo $_SESSION['user_id']; ?>
    </div>

</div>

</body>
</html>

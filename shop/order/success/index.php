<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/lang.php';
if (!isset($_SESSION['user_id'])) { header('Location: /login/'); exit(); }

$order_id   = $_SESSION['success_order_id']       ?? '—';
$email      = $_SESSION['success_email']           ?? '—';
$offer      = $_SESSION['success_offer']           ?? '—';
$panel_pass = $_SESSION['success_panel_password']  ?? null;
$orders     = $_SESSION['success_orders']          ?? []; // multi-serveurs (bundle payant), vide sinon
$invoice_id = $_SESSION['success_invoice_id']      ?? null; // 🔵 AJOUT : ID de la facture

unset(
    $_SESSION['success_order_id'], $_SESSION['success_email'], $_SESSION['success_server_id'],
    $_SESSION['success_offer'], $_SESSION['success_panel_password'], $_SESSION['success_orders'],
    $_SESSION['success_invoice_id'] // 🔵 AJOUT : nettoyage
);
$is_logged_in = true;
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?php echo t('order.success.title'); ?></title>
  <link rel="icon" type="image/png" href="https://heberge.orinstone.deepstone.fr/favicon.ico">
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <link rel="manifest" href="/manifest.json">
  <style>
    body{background:#070a13;}
    .glass{background:rgba(255,255,255,0.03);backdrop-filter:blur(14px);border:1px solid rgba(255,255,255,0.06);}
    .gradient-text{background:linear-gradient(90deg,#38bdf8,#818cf8);-webkit-background-clip:text;-webkit-text-fill-color:transparent;}
    #mobileMenu{display:none;}#mobileMenu.active{display:block;}
  </style>
  <script>function toggleMenu(){document.getElementById('mobileMenu').classList.toggle('active');}</script>
</head>
<body class="text-gray-200 font-sans min-h-screen flex flex-col justify-between">

<?php $active_nav = ''; include $_SERVER['DOCUMENT_ROOT'] . '/inc/navbar.php'; ?>

<main class="flex-grow flex items-center justify-center px-4 py-16">
  <div class="glass p-10 rounded-2xl w-full max-w-lg text-center shadow-2xl">
    <div class="w-16 h-16 bg-green-500/10 border border-green-500/30 text-green-400 rounded-full flex items-center justify-center mx-auto mb-6 text-2xl">
      <i class="fas fa-check-circle"></i>
    </div>
    <h1 class="text-2xl font-black mb-1"><?php echo t('order.confirmed'); ?></h1>
    <p class="text-gray-400 mb-6"><?php echo t('order.confirmed_sub'); ?></p>

    <div class="bg-slate-900/50 border border-white/[0.03] p-4 rounded-xl text-left font-mono text-xs space-y-2 mb-6">
      <?php if (count($orders) > 1): ?>
        <p class="text-gray-400 not-italic"><?php echo count($orders); ?> serveurs ont été créés :</p>
        <?php foreach ($orders as $o): ?>
          <p><span class="text-amber-400 font-bold">●</span> #<?php echo htmlspecialchars($o['order_id'],ENT_QUOTES,'UTF-8'); ?> — <span class="text-sky-400"><?php echo htmlspecialchars($o['offer_name'],ENT_QUOTES,'UTF-8'); ?></span></p>
        <?php endforeach; ?>
      <?php else: ?>
      <p><span class="text-amber-400 font-bold">● <?php echo t('order.label_id'); ?></span> #<?php echo htmlspecialchars($order_id,ENT_QUOTES,'UTF-8'); ?></p>
      <p><span class="text-sky-400 font-bold">● <?php echo t('order.label_offer'); ?></span> <?php echo htmlspecialchars($offer,ENT_QUOTES,'UTF-8'); ?></p>
      <?php endif; ?>
      <p><span class="text-purple-400 font-bold">● <?php echo t('order.label_email'); ?></span> <?php echo htmlspecialchars($email,ENT_QUOTES,'UTF-8'); ?></p>
      
      <?php if ($invoice_id): ?>
      <p><span class="text-emerald-400 font-bold">● Facture</span> <span class="text-white"><?php echo htmlspecialchars($invoice_id,ENT_QUOTES,'UTF-8'); ?></span></p>
      <?php endif; ?>
      
      <?php if ($panel_pass): ?>
      <p class="text-yellow-300"><span class="font-bold"><?php echo t('order.label_pw'); ?></span> <?php echo htmlspecialchars($panel_pass,ENT_QUOTES,'UTF-8'); ?>
        <br><span class="text-gray-500 text-[10px]"><?php echo t('order.pw_note'); ?></span></p>
      <?php endif; ?>
    </div>

    <!-- 🔵 AJOUT : Bouton pour télécharger la facture -->
    <?php if ($invoice_id): ?>
    <a href="/client/billing/invoice/?id=<?php echo urlencode($invoice_id); ?>" 
       target="_blank"
       class="mb-6 flex items-center justify-center gap-2 bg-emerald-600/20 hover:bg-emerald-600/40 border border-emerald-500/30 text-emerald-400 hover:text-white px-5 py-3 rounded-xl font-bold transition text-sm w-full">
      <i class="fas fa-file-invoice"></i>
      Voir / Télécharger ma facture <?php echo htmlspecialchars($invoice_id, ENT_QUOTES, 'UTF-8'); ?>
    </a>
    <?php endif; ?>

    <div class="flex gap-3 justify-center flex-wrap">
      <a href="/client/servers/" class="bg-sky-600 hover:bg-sky-500 text-white px-6 py-3 rounded-xl font-bold transition text-sm flex items-center gap-2">
        <i class="fas fa-server"></i> <?php echo t('order.goto_servers'); ?>
      </a>
      <a href="https://panel.orinstone.deepstone.fr" target="_blank" class="bg-white/5 hover:bg-white/10 border border-white/10 text-white px-6 py-3 rounded-xl font-bold transition text-sm flex items-center gap-2">
        <i class="fas fa-cogs"></i> <?php echo t('order.goto_panel'); ?>
      </a>
    </div>
  </div>
</main>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/inc/footer.php'; ?>
</body>
</html>
<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/lang.php';
if (!isset($_SESSION['user_id'])) { header('Location: /login/'); exit(); }

$order_id  = $_SESSION['success_order_id']      ?? null;
$email     = $_SESSION['success_email']         ?? null;
$password  = $_SESSION['success_panel_password']?? null;
$server_id = $_SESSION['success_server_id']     ?? null;
$offer     = $_SESSION['success_offer']         ?? 'Offre Free';

if (!$order_id || !$email || !$server_id) { header('Location: /'); exit(); }

unset($_SESSION['success_order_id'],$_SESSION['success_email'],$_SESSION['success_panel_password'],$_SESSION['success_server_id'],$_SESSION['success_offer']);
$is_logged_in = true;
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?php echo t('free.success.title'); ?></title>
  <link rel="icon" type="image/png" href="https://heberge.orinstone.deepstone.fr/favicon.ico">
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <link rel="manifest" href="/manifest.json">
  <style>
    body{background:radial-gradient(circle at top left,#1e293b,#020617);}
    .glass{background:rgba(255,255,255,0.03);backdrop-filter:blur(10px);border:1px solid rgba(255,255,255,0.1);}
    .gradient-text{background:linear-gradient(90deg,#38bdf8,#818cf8);-webkit-background-clip:text;-webkit-text-fill-color:transparent;}
    #mobileMenu{display:none;}#mobileMenu.active{display:block;}
  </style>
  <script>function toggleMenu(){document.getElementById('mobileMenu').classList.toggle('active');}</script>
</head>
<body class="text-gray-200 font-sans min-h-screen flex flex-col justify-between">

<?php $active_nav = ''; include $_SERVER['DOCUMENT_ROOT'] . '/inc/navbar.php'; ?>

<main class="flex-grow flex items-center justify-center px-4 py-16">
  <div class="glass p-10 rounded-3xl w-full max-w-lg text-center shadow-2xl">
    <div class="w-20 h-20 bg-green-500/10 border border-green-500/30 text-green-400 rounded-full flex items-center justify-center mx-auto mb-6 text-3xl">
      <i class="fas fa-rocket"></i>
    </div>
    <h1 class="text-2xl font-black mb-1"><?php echo t('free.success.heading'); ?></h1>
    <p class="text-gray-400 mb-6"><?php echo t('free.success.sub'); ?></p>

    <div class="bg-white/[0.02] border border-white/[0.05] p-5 rounded-2xl text-left font-mono text-xs space-y-2.5 mb-8">
      <p><span class="text-amber-400 font-bold">● <?php echo t('order.label_id'); ?></span> #<?php echo htmlspecialchars($order_id,ENT_QUOTES,'UTF-8'); ?></p>
      <p><span class="text-sky-400 font-bold">● <?php echo t('order.label_offer'); ?></span> <?php echo htmlspecialchars($offer,ENT_QUOTES,'UTF-8'); ?></p>
      <p><span class="text-purple-400 font-bold">● <?php echo t('order.label_email'); ?></span> <?php echo htmlspecialchars($email,ENT_QUOTES,'UTF-8'); ?></p>
      <?php if ($password): ?>
      <div class="mt-3 pt-3 border-t border-white/[0.05]">
        <p class="text-yellow-300"><span class="font-bold"><?php echo t('order.label_pw'); ?></span><br>
          <span class="text-lg tracking-widest"><?php echo htmlspecialchars($password,ENT_QUOTES,'UTF-8'); ?></span></p>
        <p class="text-gray-500 text-[10px] mt-1"><?php echo t('order.pw_note'); ?></p>
      </div>
      <?php endif; ?>
    </div>

    <div class="flex gap-3 justify-center flex-wrap">
      <a href="/client/servers/" class="bg-sky-600 hover:bg-sky-500 text-white px-6 py-3 rounded-xl font-bold transition text-sm flex items-center gap-2">
        <i class="fas fa-server"></i> <?php echo t('order.goto_servers'); ?>
      </a>
      <a href="https://panel.orinstone.deepstone.fr" target="_blank" class="bg-white/5 hover:bg-white/10 border border-white/10 px-6 py-3 rounded-xl font-bold transition text-sm flex items-center gap-2">
        <i class="fas fa-cogs"></i> <?php echo t('order.goto_panel'); ?>
      </a>
    </div>
  </div>
</main>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/inc/footer.php'; ?>
</body>
</html>

<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/lang.php';
$is_logged_in = isset($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?php echo t('privacy.title'); ?></title>
  <link rel="icon" type="image/png" href="https://heberge.orinstone.deepstone.fr/favicon.ico">
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <link rel="manifest" href="/manifest.json">
  <style>
    body { background: radial-gradient(circle at top left, #1e293b, #020617); }
    .glass { background: rgba(255,255,255,0.03); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.1); }
    .gradient-text { background: linear-gradient(90deg,#38bdf8,#818cf8); -webkit-background-clip:text; -webkit-text-fill-color:transparent; }
    #mobileMenu { display: none; }
    #mobileMenu.active { display: block; }
  </style>
  <script>function toggleMenu(){document.getElementById('mobileMenu').classList.toggle('active');}</script>
</head>
<body class="min-h-screen text-gray-200 flex flex-col justify-between font-sans">

<?php $active_nav = ''; include $_SERVER['DOCUMENT_ROOT'] . '/inc/navbar.php'; ?>

<main class="flex-grow flex items-center justify-center p-6 my-8">
  <div class="w-full max-w-4xl glass p-8 rounded-3xl shadow-2xl space-y-6">
    <div class="text-center mb-4">
      <h1 class="text-3xl font-black gradient-text uppercase tracking-tighter mb-2"><?php echo t('privacy.heading'); ?></h1>
      <p class="text-gray-400 text-sm"><?php echo t('privacy.subtitle'); ?></p>
    </div>

    <div class="space-y-4 text-sm text-gray-300 leading-relaxed">
      <section class="bg-white/[0.01] p-5 rounded-2xl border border-white/5">
        <h2 class="text-base font-bold text-white mb-2"><i class="fas fa-database mr-2 text-sky-500"></i><?php echo t('privacy.s1.title'); ?></h2>
        <p><?php echo t('privacy.s1.text'); ?></p>
      </section>
      <section class="bg-white/[0.01] p-5 rounded-2xl border border-white/5">
        <h2 class="text-base font-bold text-white mb-2"><i class="fas fa-eye-slash mr-2 text-sky-500"></i><?php echo t('privacy.s2.title'); ?></h2>
        <p><?php echo th('privacy.s2.text'); ?></p>
      </section>
      <section class="bg-white/[0.01] p-5 rounded-2xl border border-white/5">
        <h2 class="text-base font-bold text-white mb-2"><i class="fas fa-shield-alt mr-2 text-sky-500"></i><?php echo t('privacy.s3.title'); ?></h2>
        <p><?php echo t('privacy.s3.text'); ?></p>
      </section>
      <section class="bg-white/[0.01] p-5 rounded-2xl border border-white/5">
        <h2 class="text-base font-bold text-white mb-2"><i class="fas fa-user-check mr-2 text-sky-500"></i><?php echo t('privacy.s4.title'); ?></h2>
        <p><?php echo t('privacy.s4.text'); ?></p>
      </section>
    </div>

    <div class="text-center pt-4">
      <button onclick="history.back();" class="text-gray-400 hover:text-sky-400 text-sm font-semibold transition">
        <i class="fas fa-arrow-left mr-2"></i><?php echo t('legal.back'); ?>
      </button>
    </div>
  </div>
</main>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/inc/footer.php'; ?>
</body>
</html>

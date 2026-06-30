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
  <title>Infrastructure | OrinStone Studio</title>
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

<main class="flex-grow flex items-center justify-center p-6 my-12">
  <div class="w-full max-w-5xl glass p-8 md:p-12 rounded-3xl shadow-2xl space-y-12">

    <div class="text-center space-y-3">
      <span class="text-xs font-bold uppercase tracking-widest text-sky-400 bg-sky-500/10 px-4 py-1.5 rounded-full border border-sky-500/20"><?php echo t('infra.badge'); ?></span>
      <h1 class="text-4xl md:text-5xl font-black text-white uppercase tracking-tighter">
        Orin<span class="gradient-text">Stone Studio</span>
      </h1>
      <p class="text-gray-400 text-sm md:text-base max-w-2xl mx-auto"><?php echo t('infra.subtitle'); ?></p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
      <div class="bg-white/[0.01] p-6 rounded-2xl border border-white/5 space-y-4 hover:border-sky-500/30 transition duration-300">
        <div class="w-12 h-12 rounded-xl bg-sky-500/10 border border-sky-500/20 flex items-center justify-center">
          <i class="fas fa-server text-xl text-sky-400"></i>
        </div>
        <h3 class="text-lg font-bold text-white tracking-wide"><?php echo t('infra.hw.title'); ?></h3>
        <p class="text-gray-400 text-sm leading-relaxed"><?php echo t('infra.hw.desc'); ?></p>
      </div>

      <div class="bg-white/[0.01] p-6 rounded-2xl border border-white/5 space-y-4 hover:border-indigo-500/30 transition duration-300">
        <div class="w-12 h-12 rounded-xl bg-indigo-500/10 border border-indigo-500/20 flex items-center justify-center">
          <i class="fas fa-shield-halved text-xl text-indigo-400"></i>
        </div>
        <h3 class="text-lg font-bold text-white tracking-wide"><?php echo t('infra.sec.title'); ?></h3>
        <p class="text-gray-400 text-sm leading-relaxed"><?php echo t('infra.sec.desc'); ?></p>
      </div>

      <div class="bg-white/[0.01] p-6 rounded-2xl border border-white/5 space-y-4 hover:border-purple-500/30 transition duration-300">
        <div class="w-12 h-12 rounded-xl bg-purple-500/10 border border-purple-500/20 flex items-center justify-center">
          <i class="fas fa-chart-line text-xl text-purple-400"></i>
        </div>
        <h3 class="text-lg font-bold text-white tracking-wide"><?php echo t('infra.up.title'); ?></h3>
        <p class="text-gray-400 text-sm leading-relaxed"><?php echo t('infra.up.desc'); ?></p>
      </div>
    </div>

    <div class="bg-white/[0.02] border border-white/5 p-6 rounded-2xl flex flex-col sm:flex-row items-center justify-between gap-6">
      <div class="flex items-center gap-4">
        <span class="relative flex h-3 w-3">
          <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
          <span class="relative inline-flex rounded-full h-3 w-3 bg-green-500"></span>
        </span>
        <div>
          <h4 class="text-sm font-bold text-white"><?php echo t('hero.status_ok'); ?></h4>
          <p class="text-xs text-gray-400">Monitoring en temps réel de notre infrastructure.</p>
        </div>
      </div>
      <a href="https://status.deepstone.fr/" target="_blank" class="w-full sm:w-auto bg-white/5 hover:bg-white/10 text-white border border-white/10 px-5 py-2.5 rounded-xl text-xs font-bold uppercase tracking-wider text-center transition">
        <?php echo t('footer.status'); ?>
      </a>
    </div>

    <div class="text-center pt-2">
      <button onclick="history.back();" class="text-gray-400 hover:text-sky-400 text-sm font-semibold transition">
        <i class="fas fa-arrow-left mr-2"></i><?php echo t('legal.back'); ?>
      </button>
    </div>
  </div>
</main>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/inc/footer.php'; ?>
</body>
</html>

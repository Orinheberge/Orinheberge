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
  <title><?php echo t('payment.title'); ?></title>
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
      <h1 class="text-3xl font-black gradient-text uppercase tracking-tighter mb-2"><?php echo t('payment.heading'); ?></h1>
      <p class="text-gray-400 text-sm"><?php echo t('payment.subtitle'); ?></p>
    </div>

    <div class="space-y-4 text-sm text-gray-300 leading-relaxed">
      
      <section class="bg-white/[0.01] p-5 rounded-2xl border border-white/5">
        <h2 class="text-base font-bold text-white mb-2">
          <i class="fas fa-file-contract mr-2 text-sky-500"></i>1. <?php echo t('payment.s1.title'); ?>
        </h2>
        <p><?php echo t('payment.s1.text'); ?></p>
      </section>

      <section class="bg-white/[0.01] p-5 rounded-2xl border border-white/5">
        <h2 class="text-base font-bold text-white mb-2">
          <i class="fas fa-credit-card mr-2 text-sky-500"></i>2. <?php echo t('payment.s2.title'); ?>
        </h2>
        <p class="mb-2"><?php echo t('payment.s2.intro'); ?></p>
        <ul class="list-disc list-inside space-y-1 pl-2 text-gray-400">
          <li>Carte bancaire (Visa, Mastercard, CB)</li>
          <li>PayPal</li>
          <li>Apple Pay</li>
          <li>Google Pay</li>
          <li>Revolut Pay</li>
        </ul>
        <p class="mt-3"><?php echo t('payment.s2.text'); ?></p>
      </section>

      <section class="bg-white/[0.01] p-5 rounded-2xl border border-white/5">
        <h2 class="text-base font-bold text-white mb-2">
          <i class="fas fa-shield-alt mr-2 text-sky-500"></i>3. <?php echo t('payment.s3.title'); ?>
        </h2>
        <p class="mb-2"><strong>3.1.</strong> <?php echo t('payment.s3.t1'); ?></p>
        <p class="mb-2"><strong>3.2.</strong> <?php echo t('payment.s3.t2'); ?></p>
        <p><strong>3.3.</strong> <?php echo t('payment.s3.t3'); ?></p>
      </section>

      <section class="bg-white/[0.01] p-5 rounded-2xl border border-white/5">
        <h2 class="text-base font-bold text-white mb-2">
          <i class="fas fa-receipt mr-2 text-sky-500"></i>4. <?php echo t('payment.s4.title'); ?>
        </h2>
        <p class="mb-2"><strong>4.1.</strong> <?php echo t('payment.s4.t1'); ?></p>
        <p class="mb-2"><strong>4.2.</strong> <?php echo t('payment.s4.t2'); ?></p>
        <p><strong>4.3.</strong> <?php echo t('payment.s4.t3'); ?></p>
      </section>

      <section class="bg-white/[0.01] p-5 rounded-2xl border border-white/5">
        <h2 class="text-base font-bold text-white mb-2">
          <i class="fas fa-bolt mr-2 text-sky-500"></i>5. <?php echo t('payment.s5.title'); ?>
        </h2>
        <p class="mb-2"><strong>5.1.</strong> <?php echo t('payment.s5.t1'); ?></p>
        <p><strong>5.2.</strong> <?php echo t('payment.s5.t2'); ?></p>
      </section>

      <section class="bg-white/[0.01] p-5 rounded-2xl border border-white/5">
        <h2 class="text-base font-bold text-white mb-2">
          <i class="fas fa-undo mr-2 text-sky-500"></i>6. <?php echo t('payment.s6.title'); ?>
        </h2>
        <p class="mb-2"><strong>6.1.</strong> <?php echo t('payment.s6.t1'); ?></p>
        <p class="mb-2"><strong>6.2.</strong> <?php echo t('payment.s6.t2'); ?></p>
        <p class="mb-2"><strong>6.3.</strong> <?php echo t('payment.s6.t3'); ?></p>
        <p><strong>6.4.</strong> <?php echo t('payment.s6.t4'); ?></p>
      </section>

      <section class="bg-white/[0.01] p-5 rounded-2xl border border-white/5">
        <h2 class="text-base font-bold text-white mb-2">
          <i class="fas fa-gavel mr-2 text-sky-500"></i>7. <?php echo t('payment.s7.title'); ?>
        </h2>
        <p class="mb-2"><strong>7.1.</strong> <?php echo t('payment.s7.t1'); ?></p>
        <p><strong>7.2.</strong> <?php echo t('payment.s7.t2'); ?></p>
      </section>

      <section class="bg-white/[0.01] p-5 rounded-2xl border border-white/5">
        <h2 class="text-base font-bold text-white mb-2">
          <i class="fas fa-exclamation-triangle mr-2 text-sky-500"></i>8. <?php echo t('payment.s8.title'); ?>
        </h2>
        <p><?php echo t('payment.s8.text'); ?></p>
      </section>

      <section class="bg-white/[0.01] p-5 rounded-2xl border border-white/5">
        <h2 class="text-base font-bold text-white mb-2">
          <i class="fas fa-user-lock mr-2 text-sky-500"></i>9. <?php echo t('payment.s9.title'); ?>
        </h2>
        <p><?php echo t('payment.s9.text'); ?></p>
      </section>

      <section class="bg-white/[0.01] p-5 rounded-2xl border border-white/5">
        <h2 class="text-base font-bold text-white mb-2">
          <i class="fas fa-envelope mr-2 text-sky-500"></i>10. <?php echo t('payment.s10.title'); ?>
        </h2>
        <p class="mb-2"><?php echo t('payment.s10.text'); ?></p>
        <ul class="list-none space-y-1 text-gray-400">
          <li><strong>Email :</strong> <a href="mailto:deepstone@deepstone.fr" class="text-sky-400 hover:underline">deepstone@deepstone.fr</a></li>
          <li><strong>Discord :</strong> <a href="https://heberge.orinstone.deepstone.fr/discord/" target="_blank" rel="noopener noreferrer" class="text-sky-400 hover:underline">Rejoindre notre Discord</a></li>
        </ul>
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
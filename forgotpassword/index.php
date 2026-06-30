<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/lang.php';

$db_config = ['host'=>'localhost','port'=>'3306','name'=>'s43_orinheberge','user'=>'root','pass'=>'1504'];
$is_logged_in = isset($_SESSION['user_id']);
$message = '';
$message_type = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    if ($email === '') {
        $message = t('forgot.err_empty');
        $message_type = 'error';
    } else {
        try {
            $dsn = "mysql:host={$db_config['host']};port={$db_config['port']};dbname={$db_config['name']};charset=utf8mb4";
            $pdo = new PDO($dsn, $db_config['user'], $db_config['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $stmt = $pdo->prepare("SELECT id, email FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                $message = t('forgot.msg_generic');
                $message_type = 'info';
            } else {
                $token   = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', time() + 3600);
                $pdo->prepare("INSERT INTO password_resets (user_id, token, expires_at, used, created_at) VALUES (?, ?, ?, 0, NOW())")
                    ->execute([$user['id'], $token, $expires]);

                $reset_link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http')
                    . '://' . $_SERVER['HTTP_HOST'] . '/resetpassword/?token=' . $token;

                require_once __DIR__ . '/../inc/smtp.php';
                $subject = 'Réinitialisation du mot de passe - OrinHeberge';
                $body    = '<p>Bonjour,</p><p>Cliquez sur le lien ci-dessous pour réinitialiser votre mot de passe :</p>'
                         . '<p><a href="' . htmlspecialchars($reset_link, ENT_QUOTES, 'UTF-8') . '" style="color:#38bdf8;font-weight:bold;">Réinitialiser mon mot de passe</a></p>'
                         . '<p>Ce lien expirera dans 1 heure.</p>';

                $sent = false;
                try { $sent = send_smtp_mail($user['email'], $subject, $body, 'OrinHeberge', $smtp_config['username'], $smtp_config); }
                catch (Exception $e) { $sent = false; }

                if ($sent) {
                    $message = t('forgot.msg_sent');
                    $message_type = 'success';
                } else {
                    $message = 'Impossible d\'envoyer l\'email. Lien de test : <a href="' . htmlspecialchars($reset_link, ENT_QUOTES, 'UTF-8') . '" class="underline font-bold text-sky-400">Réinitialiser</a>';
                    $message_type = 'info';
                }
            }
        } catch (Exception $e) {
            $message = t('forgot.err_server');
            $message_type = 'error';
        }
    }
}

$alert_class = [
    'info'    => 'bg-sky-500/10 border-sky-500/50 text-sky-400',
    'success' => 'bg-green-500/10 border-green-500/50 text-green-400',
    'error'   => 'bg-red-500/10 border-red-500/50 text-red-400',
];
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?php echo t('forgot.title'); ?></title>
  <link rel="icon" type="image/png" href="https://heberge.orinstone.deepstone.fr/favicon.ico">
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <link rel="manifest" href="/manifest.json">
  <style>
    body { background: radial-gradient(circle at top left, #1e293b, #020617); }
    .glass { background: rgba(255,255,255,0.03); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.1); }
    .gradient-text { background: linear-gradient(90deg,#38bdf8,#818cf8); -webkit-background-clip:text; -webkit-text-fill-color:transparent; }
    #mobileMenu { display:none; }
    #mobileMenu.active { display:block; }
  </style>
  <script>function toggleMenu(){document.getElementById('mobileMenu').classList.toggle('active');}</script>
</head>
<body class="min-h-screen text-gray-200 flex flex-col justify-between font-sans">

<?php $active_nav = ''; include $_SERVER['DOCUMENT_ROOT'] . '/inc/navbar.php'; ?>

<main class="flex-grow flex items-center justify-center p-6 my-8">
  <div class="glass w-full max-w-md p-8 rounded-3xl shadow-2xl">
    <div class="text-center mb-8">
      <h1 class="text-3xl font-black gradient-text uppercase tracking-tighter mb-2">OrinHeberge</h1>
      <p class="text-gray-400 text-sm"><?php echo t('forgot.heading'); ?></p>
      <p class="text-gray-500 text-xs mt-1"><?php echo t('forgot.subtitle'); ?></p>
    </div>

    <?php if ($message): ?>
      <div class="mb-6 p-4 rounded-xl text-center text-sm border <?php echo $alert_class[$message_type]; ?>"><?php echo $message; ?></div>
    <?php endif; ?>

    <form method="POST" class="space-y-5">
      <div>
        <label class="block text-xs font-bold uppercase tracking-widest text-gray-500 mb-2"><?php echo t('forgot.email'); ?></label>
        <input type="email" name="email" required placeholder="votre@email.com"
               class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 focus:outline-none focus:border-sky-500 transition text-white">
      </div>
      <button type="submit" class="w-full bg-sky-600 hover:bg-sky-500 py-4 rounded-xl font-black uppercase tracking-widest transition shadow-lg active:scale-95">
        <?php echo t('forgot.submit'); ?>
      </button>
    </form>

    <a href="/login/" class="block text-center mt-6 text-xs text-gray-600 hover:text-gray-400 transition">
      <i class="fas fa-arrow-left mr-2"></i><?php echo t('forgot.back'); ?>
    </a>
  </div>
</main>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/inc/footer.php'; ?>
</body>
</html>

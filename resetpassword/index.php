<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/lang.php';

$db_config = ['host'=>'localhost','port'=>'3306','name'=>'s43_orinheberge','user'=>'root','pass'=>'1504'];
$is_logged_in = isset($_SESSION['user_id']);
$token    = $_GET['token'] ?? $_POST['token'] ?? '';
$message  = '';
$message_type = 'info';
$showForm = true;
$pdo      = null;

try {
    $pdo = new PDO("mysql:host={$db_config['host']};port={$db_config['port']};dbname={$db_config['name']};charset=utf8mb4",
        $db_config['user'], $db_config['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (Exception $e) {
    $message = 'Erreur serveur.'; $showForm = false;
}

if ($pdo) {
    if (!$token) {
        $message = t('reset.err_missing'); $showForm = false;
    } else {
        $stmt = $pdo->prepare("SELECT pr.id, pr.user_id, pr.expires_at, pr.used FROM password_resets pr WHERE pr.token = ? LIMIT 1");
        $stmt->execute([$token]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row)             { $message = t('reset.err_token');   $showForm = false; }
        elseif ($row['used'])  { $message = t('reset.err_used');    $showForm = false; }
        elseif (strtotime($row['expires_at']) < time()) { $message = t('reset.err_expired'); $showForm = false; }
        elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $pw  = $_POST['password']         ?? '';
            $pw2 = $_POST['password_confirm'] ?? '';
            if (strlen($pw) < 8)  { $message = t('reset.err_length'); }
            elseif ($pw !== $pw2) { $message = t('reset.err_match'); }
            else {
                $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([password_hash($pw, PASSWORD_DEFAULT), $row['user_id']]);
                $pdo->prepare("UPDATE password_resets SET used = 1 WHERE id = ?")->execute([$row['id']]);
                $message = t('reset.success'); $showForm = false; $message_type = 'success';
            }
        }
    }
}

$alert_class = ['info'=>'bg-sky-500/10 border-sky-500/50 text-sky-400','success'=>'bg-green-500/10 border-green-500/50 text-green-400','error'=>'bg-red-500/10 border-red-500/50 text-red-400'];
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?php echo t('reset.title'); ?></title>
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
      <p class="text-gray-400 text-sm"><?php echo t('reset.heading'); ?></p>
    </div>

    <?php if ($message): ?>
      <div class="mb-6 p-4 rounded-xl text-center text-sm border <?php echo $alert_class[$message_type]; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <?php if ($showForm): ?>
    <form method="POST" class="space-y-5">
      <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
      <div>
        <label class="block text-xs font-bold uppercase tracking-widest text-gray-500 mb-2"><?php echo t('reset.new_pw'); ?></label>
        <input type="password" name="password" required placeholder="••••••••"
               class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 focus:outline-none focus:border-sky-500 transition text-white">
      </div>
      <div>
        <label class="block text-xs font-bold uppercase tracking-widest text-gray-500 mb-2"><?php echo t('reset.confirm_pw'); ?></label>
        <input type="password" name="password_confirm" required placeholder="••••••••"
               class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 focus:outline-none focus:border-sky-500 transition text-white">
      </div>
      <button type="submit" class="w-full bg-sky-600 hover:bg-sky-500 py-4 rounded-xl font-black uppercase tracking-widest transition shadow-lg active:scale-95">
        <?php echo t('reset.submit'); ?>
      </button>
    </form>
    <?php else: ?>
      <div class="text-center mt-4">
        <a href="/login/" class="bg-sky-600 hover:bg-sky-500 px-6 py-3 rounded-xl font-bold transition inline-block">
          <?php echo t('nav.login'); ?>
        </a>
      </div>
    <?php endif; ?>
  </div>
</main>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/inc/footer.php'; ?>
</body>
</html>

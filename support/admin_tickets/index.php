<?php
ini_set('display_errors',1); error_reporting(E_ALL);
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/lang.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != 1) { header('Location: /client/servers/'); exit('Accès interdit.'); }
$is_logged_in = true;

try {
    $pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=s43_orinheberge;charset=utf8mb4','root','1504',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
} catch (PDOException $e) { die(t('login.db_error')); }

$notification = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ticket_id = intval($_POST['ticket_id'] ?? 0);
    if (isset($_POST['action_reply'])) {
        $reply = trim($_POST['reply'] ?? '');
        $status = $_POST['status'] ?? 'Traité';
        if (!empty($reply)) {
            $pdo->prepare('UPDATE support_tickets SET reply=?, status=? WHERE id=?')->execute([$reply, $status, $ticket_id]);
            $notification = "<div class='bg-green-500/20 text-green-400 border border-green-500/30 p-4 rounded-xl mb-6 text-sm'>".t('admin.reply_saved')."</div>";
        } else {
            $notification = "<div class='bg-red-500/20 text-red-400 border border-red-500/30 p-4 rounded-xl mb-6 text-sm'>".t('admin.reply_empty')."</div>";
        }
    }
    if (isset($_POST['action_close'])) {
        $pdo->prepare("UPDATE support_tickets SET status='Fermé' WHERE id=?")->execute([$ticket_id]);
        $notification = "<div class='bg-amber-500/20 text-amber-400 border border-amber-500/30 p-4 rounded-xl mb-6 text-sm'>".t('admin.closed_ok')."</div>";
    }
}

$all_tickets = $pdo->query('SELECT * FROM support_tickets ORDER BY created_at DESC')->fetchAll();
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?php echo t('admin.title'); ?></title>
  <link rel="icon" type="image/png" href="https://heberge.orinstone.deepstone.fr/favicon.ico">
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <link rel="manifest" href="/manifest.json">
  <style>
    body{background:#0b0f19;}
    .glass{background:rgba(255,255,255,0.04);backdrop-filter:blur(14px);border:1px solid rgba(255,255,255,0.08);}
    .gradient-text{background:linear-gradient(90deg,#38bdf8,#818cf8);-webkit-background-clip:text;-webkit-text-fill-color:transparent;}
    .gradient-admin-text{background:linear-gradient(90deg,#f43f5e,#ec4899);-webkit-background-clip:text;-webkit-text-fill-color:transparent;}
    #mobileMenu{display:none;}#mobileMenu.active{display:block;}
  </style>
  <script>function toggleMenu(){document.getElementById('mobileMenu').classList.toggle('active');}</script>
</head>
<body class="text-gray-200 font-sans min-h-screen flex flex-col justify-between">

<?php $active_nav = 'support'; include $_SERVER['DOCUMENT_ROOT'] . '/inc/navbar.php'; ?>

<main class="max-w-7xl mx-auto px-4 sm:px-6 mb-16 w-full flex-grow pt-10">

  <?php echo $notification; ?>

  <div class="mb-8 flex flex-wrap justify-between items-center gap-4">
    <div>
      <h1 class="text-4xl font-black tracking-tight flex items-center gap-3">
        <span>🛠️</span> <span class="gradient-admin-text"><?php echo t('admin.heading'); ?></span>
      </h1>
      <p class="text-gray-400 text-sm mt-1">Liste globale de toutes les réclamations et rapports des utilisateurs.</p>
    </div>
    <div class="bg-rose-500/10 border border-rose-500/20 px-4 py-2 rounded-xl text-rose-400 text-xs font-mono font-bold flex items-center gap-2">
      <span class="w-2 h-2 rounded-full bg-rose-500 animate-pulse"></span> Mode Administrateur Actif
    </div>
  </div>

  <?php if (empty($all_tickets)): ?>
    <div class="glass p-8 rounded-2xl text-center text-gray-500 italic"><?php echo t('admin.no_ticket'); ?></div>
  <?php else: ?>
  <div class="space-y-6">
  <?php foreach ($all_tickets as $ticket):
    $sc = 'bg-sky-500/10 text-sky-400 border-sky-500/20';
    if ($ticket['status']==='Traité') $sc='bg-green-500/10 text-green-400 border-green-500/20';
    if ($ticket['status']==='Fermé')  $sc='bg-gray-500/10 text-gray-400 border-gray-500/20';
    $tc = ($ticket['ticket_type']==='Bug') ? 'text-red-400 bg-red-500/10 border-red-500/20' : 'text-amber-400 bg-amber-500/10 border-amber-500/20';
  ?>
  <div class="glass p-6 rounded-2xl border border-white/[0.05]">
    <div class="flex flex-wrap justify-between items-start gap-4 mb-4 pb-4 border-b border-white/[0.05]">
      <div>
        <div class="flex items-center gap-2 flex-wrap">
          <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider border <?php echo $tc; ?>"><?php echo htmlspecialchars($ticket['ticket_type']); ?></span>
          <h2 class="text-xl font-bold text-white"><?php echo htmlspecialchars($ticket['subject']); ?></h2>
        </div>
        <p class="text-xs text-gray-500 font-mono mt-1">User ID: #<?php echo $ticket['user_id']; ?> • <?php echo date('d/m/Y H:i', strtotime($ticket['created_at'])); ?></p>
      </div>
      <span class="px-3 py-1 rounded-full text-xs font-semibold uppercase border <?php echo $sc; ?>"><?php echo htmlspecialchars($ticket['status']); ?></span>
    </div>

    <div class="bg-white/[0.02] border border-white/[0.03] p-4 rounded-xl mb-4">
      <p class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-1.5"><i class="fas fa-user text-sky-400"></i> Message :</p>
      <p class="text-sm text-gray-300 whitespace-pre-wrap"><?php echo htmlspecialchars($ticket['message']); ?></p>
    </div>

    <?php if (!empty($ticket['reply'])): ?>
    <div class="bg-emerald-500/5 border border-emerald-500/10 p-4 rounded-xl mb-4">
      <p class="text-xs font-bold uppercase tracking-wider text-emerald-400 mb-1.5"><i class="fas fa-user-shield"></i> <?php echo ($lang==='en'?'Your current reply:':'Votre réponse actuelle :'); ?></p>
      <p class="text-sm text-emerald-200/90 whitespace-pre-wrap"><?php echo htmlspecialchars($ticket['reply']); ?></p>
    </div>
    <?php endif; ?>

    <form method="POST" class="mt-4 pt-4 border-t border-white/[0.03]">
      <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
      <div class="grid md:grid-cols-4 gap-4 items-end">
        <div class="md:col-span-2">
          <label class="block text-[10px] uppercase font-bold text-gray-400 mb-1"><?php echo ($lang==='en'?'Write / Edit reply':'Rédiger / Modifier la réponse'); ?></label>
          <textarea name="reply" rows="2" placeholder="<?php echo ($lang==='en'?'Type your reply...':'Tapez votre réponse ici...'); ?>"
                    class="w-full bg-white/5 border border-white/10 rounded-xl p-3 text-xs text-gray-200 focus:outline-none focus:border-rose-500 transition font-sans"><?php echo htmlspecialchars($ticket['reply']??''); ?></textarea>
        </div>
        <div>
          <label class="block text-[10px] uppercase font-bold text-gray-400 mb-1"><?php echo t('admin.status'); ?></label>
          <select name="status" class="w-full bg-white/5 border border-white/10 rounded-xl p-3 text-xs text-gray-200 focus:outline-none focus:border-rose-500 transition">
            <option value="Traité" <?php if($ticket['status']==='Traité')echo'selected'; ?> class="bg-[#0b0f19]">Traité</option>
            <option value="Ouvert" <?php if($ticket['status']==='Ouvert')echo'selected'; ?> class="bg-[#0b0f19]">Ouvert / En cours</option>
          </select>
        </div>
        <div class="flex gap-2">
          <button type="submit" name="action_reply" class="flex-grow bg-rose-600 hover:bg-rose-500 text-white font-bold text-xs py-3 px-4 rounded-xl transition flex items-center justify-center gap-1">
            <i class="fas fa-reply text-[10px]"></i> <?php echo t('admin.reply'); ?>
          </button>
          <?php if ($ticket['status']!=='Fermé'): ?>
          <button type="submit" name="action_close" onclick="return confirm('<?php echo ($lang==='en'?'Close this ticket permanently?':'Fermer définitivement ce ticket ?'); ?>');"
                  class="bg-gray-800 hover:bg-gray-700 text-gray-400 hover:text-white border border-white/10 font-bold text-xs py-3 px-3 rounded-xl transition" title="<?php echo t('admin.close'); ?>">
            <i class="fas fa-lock"></i>
          </button>
          <?php endif; ?>
        </div>
      </div>
    </form>
  </div>
  <?php endforeach; ?>
  </div>
  <?php endif; ?>
</main>

<div class="fixed bottom-6 right-6 z-50">
  <a href="https://heberge.orinstone.deepstone.fr/discord/" target="_blank" class="bg-[#5865F2] hover:bg-[#4752C4] transition text-white px-5 py-4 rounded-full font-bold flex items-center gap-2 shadow-2xl hover:scale-105 transform duration-200">
    <i class="fab fa-discord text-xl"></i>
    <span class="hidden sm:inline text-sm"><?php echo t('discord.help'); ?></span>
  </a>
</div>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/inc/footer.php'; ?>
</body>
</html>

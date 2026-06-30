<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/lang.php';

if (!isset($_SESSION['user_id'])) { header('Location: /login/'); exit(); }

$is_logged_in = true;

try {
    $pdo = new PDO('mysql:host=localhost;dbname=s43_orinheberge;charset=utf8mb4', 'root', '1504', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die(t('login.db_error'));
}

$notification = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ticket_type = trim($_POST['ticket_type'] ?? '');
    $subject     = trim($_POST['subject'] ?? '');
    $message     = trim($_POST['message'] ?? '');

    if (empty($ticket_type) || empty($subject) || empty($message)) {
        $notification = "<div class='bg-red-500/20 text-red-400 border border-red-500/30 p-4 rounded-xl mb-6 font-mono text-sm'>" . t('support.error_fields') . "</div>";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO support_tickets (user_id, ticket_type, subject, message, status) VALUES (?, ?, ?, ?, 'En attente')");
            $stmt->execute([$_SESSION['user_id'], $ticket_type, $subject, $message]);
            $notification = "<div class='bg-green-500/20 text-green-400 border border-green-500/30 p-4 rounded-xl mb-6 font-mono text-sm'>" . t('support.success_send') . "</div>";
        } catch (PDOException $e) {
            $notification = "<div class='bg-red-500/20 text-red-400 border border-red-500/30 p-4 rounded-xl mb-6 font-mono text-sm'>" . t('support.error_send') . "</div>";
        }
    }
}

$stmt = $pdo->prepare('SELECT * FROM support_tickets WHERE user_id = ? ORDER BY created_at DESC');
$stmt->execute([$_SESSION['user_id']]);
$user_tickets = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('support.title'); ?></title>
    <link rel="icon" type="image/png" href="https://heberge.orinstone.deepstone.fr/favicon.ico">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="manifest" href="/manifest.json">
    <style>
        body { background: #0b0f19; scroll-behavior: smooth; }
        .glass { background: rgba(255,255,255,0.04); backdrop-filter: blur(14px); border: 1px solid rgba(255,255,255,0.08); }
        .gradient-text { background: linear-gradient(90deg, #38bdf8, #818cf8); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        #mobileMenu { display: none; }
        #mobileMenu.active { display: block; }
        .custom-scrollbar::-webkit-scrollbar { width: 5px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: rgba(255,255,255,0.01); }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 10px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: rgba(56,189,248,0.3); }
    </style>
    <script>function toggleMenu(){ document.getElementById('mobileMenu').classList.toggle('active'); }</script>
</head>
<body class="text-gray-200 font-sans min-h-screen flex flex-col justify-between">

<div class="fixed bottom-6 right-6 z-50">
    <a href="https://heberge.orinstone.deepstone.fr/discord/" target="_blank" class="bg-[#5865F2] hover:bg-[#4752C4] transition text-white px-5 py-3.5 rounded-full font-bold flex items-center gap-2 shadow-2xl hover:scale-105 transform duration-200">
        <i class="fab fa-discord text-xl"></i>
        <span class="hidden sm:inline text-sm"><?php echo t('discord.help'); ?></span>
    </a>
</div>

<?php $active_nav = 'support'; include $_SERVER['DOCUMENT_ROOT'] . '/inc/navbar.php'; ?>

<main class="max-w-7xl mx-auto px-4 sm:px-6 mb-16 w-full flex-grow pt-10">

    <?php echo $notification; ?>

    <div class="grid lg:grid-cols-3 gap-8">

        <!-- Ticket form -->
        <div class="lg:col-span-2 glass p-6 sm:p-8 rounded-2xl border border-white/[0.05] shadow-2xl">
            <h2 class="text-2xl font-black text-white mb-2 flex items-center gap-2">
                <i class="fas fa-headset text-sky-400"></i> <?php echo t('support.open_ticket'); ?>
            </h2>
            <p class="text-gray-400 text-sm mb-6"><?php echo t('support.open_desc'); ?></p>

            <form action="" method="POST" class="space-y-5">
                <div>
                    <label class="block text-xs uppercase font-bold tracking-wider text-gray-400 mb-2"><?php echo t('support.type_label'); ?></label>
                    <select name="ticket_type" class="w-full bg-white/5 border border-white/10 rounded-xl p-3 text-sm text-gray-200 focus:outline-none focus:border-sky-500 transition focus:ring-1 focus:ring-sky-500">
                        <option value="Bug" class="bg-[#0b0f19]">Signaler un Bug / Problème technique</option>
                        <option value="Réclamation" class="bg-[#0b0f19]">Faire une Réclamation</option>
                        <option value="Autre" class="bg-[#0b0f19]">Autre demande</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs uppercase font-bold tracking-wider text-gray-400 mb-2"><?php echo t('support.subject_label'); ?></label>
                    <input type="text" name="subject" required placeholder="Ex: Mon serveur Minecraft ne démarre plus"
                           class="w-full bg-white/5 border border-white/10 rounded-xl p-3 text-sm text-gray-200 placeholder-gray-600 focus:outline-none focus:border-sky-500 transition focus:ring-1 focus:ring-sky-500">
                </div>

                <div>
                    <label class="block text-xs uppercase font-bold tracking-wider text-gray-400 mb-2">Description détaillée *</label>
                    <textarea name="message" rows="6" required placeholder="Décrivez précisément votre problème..."
                              class="w-full bg-white/5 border border-white/10 rounded-xl p-3 text-sm text-gray-200 placeholder-gray-600 focus:outline-none focus:border-sky-500 transition focus:ring-1 focus:ring-sky-500 font-sans"></textarea>
                </div>

                <button type="submit" class="w-full bg-sky-600 hover:bg-sky-500 text-white p-3 rounded-xl text-sm font-bold transition shadow-lg flex items-center justify-center gap-2">
                    <i class="fas fa-paper-plane text-xs"></i> <?php echo t('support.send'); ?>
                </button>
            </form>
        </div>

        <!-- Sidebar -->
        <div class="space-y-6">
            <?php if (isset($_SESSION['is_admin']) && $_SESSION['is_admin']): ?>
                <div class="glass p-5 rounded-2xl border border-rose-500/20 bg-rose-500/5">
                    <h3 class="text-sm font-bold text-rose-400 mb-1 flex items-center gap-1.5"><i class="fas fa-user-shield"></i> Espace Administrateur</h3>
                    <p class="text-[11px] text-gray-400 mb-3">Vous êtes connecté en tant que fondateur.</p>
                    <a href="/support/admin_tickets/" class="block text-center bg-rose-600 hover:bg-rose-500 text-white py-1.5 px-3 rounded-xl text-xs font-bold transition"><?php echo t('support.admin_access'); ?></a>
                </div>
            <?php endif; ?>

            <div class="glass p-6 rounded-2xl border border-white/[0.05] bg-gradient-to-br from-[#5865F2]/10 to-transparent">
                <h3 class="text-lg font-bold text-white mb-2 flex items-center gap-2"><i class="fab fa-discord text-[#5865F2]"></i> <?php echo t('support.discord_title'); ?></h3>
                <p class="text-xs text-gray-400 mb-4">Besoin d'une interaction rapide ? Notre communauté et l'équipe sont sur Discord.</p>
                <a href="https://discord.gg/rnM2fngc7Z" target="_blank" class="block text-center bg-[#5865F2] hover:bg-[#4752C4] text-white py-2 px-4 rounded-xl text-xs font-bold transition">Rejoindre le serveur</a>
            </div>

            <div class="glass p-6 rounded-2xl border border-white/[0.05]">
                <h3 class="text-lg font-bold text-white mb-4 flex items-center gap-2"><i class="fas fa-history text-purple-400"></i> Vos demandes</h3>
                <div class="space-y-3 max-h-[400px] overflow-y-auto pr-1 custom-scrollbar">
                    <?php if (empty($user_tickets)): ?>
                        <p class="text-xs text-gray-500 italic"><?php echo t('support.no_ticket'); ?></p>
                    <?php else: ?>
                        <?php foreach ($user_tickets as $ticket): ?>
                        <?php
                            $status_badge = 'bg-sky-500/10 text-sky-400 border-sky-500/20';
                            if ($ticket['status'] === 'Traité')  $status_badge = 'bg-green-500/10 text-green-400 border-green-500/20';
                            elseif ($ticket['status'] === 'Fermé') $status_badge = 'bg-gray-500/10 text-gray-400 border-gray-500/20';
                        ?>
                        <div class="bg-white/5 border border-white/[0.03] p-3 rounded-xl text-left text-xs">
                            <div class="flex justify-between items-start mb-1 gap-2">
                                <span class="font-bold text-gray-300 line-clamp-1"><?php echo htmlspecialchars($ticket['subject']); ?></span>
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold border shrink-0 <?php echo $status_badge; ?>">
                                    <?php echo htmlspecialchars($ticket['status'] ?? 'En attente'); ?>
                                </span>
                            </div>
                            <p class="text-gray-400 mt-1 text-[11px] line-clamp-2"><?php echo htmlspecialchars($ticket['message']); ?></p>
                            <?php if (!empty($ticket['reply'])): ?>
                                <div class="mt-2 p-2 bg-sky-500/10 border border-sky-500/20 rounded-lg text-sky-300 text-[11px]">
                                    <span class="font-bold block text-[9px] uppercase tracking-wider text-sky-400"><i class="fas fa-user-shield"></i> Réponse de l'assistance :</span>
                                    <?php echo htmlspecialchars($ticket['reply']); ?>
                                </div>
                            <?php endif; ?>
                            <div class="flex justify-between text-[10px] text-gray-500 font-mono mt-2 pt-1.5 border-t border-white/5">
                                <span><?php echo t('support.ticket_type'); ?> <?php echo htmlspecialchars($ticket['ticket_type']); ?></span>
                                <span><?php echo date('d/m H:i', strtotime($ticket['created_at'])); ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/inc/footer.php'; ?>
</body>
</html>

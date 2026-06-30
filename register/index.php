<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/lang.php';

$db_config = ['host'=>'localhost','port'=>'3306','name'=>'s43_orinheberge','user'=>'root','pass'=>'1504'];
$is_logged_in = isset($_SESSION['user_id']);
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo = new PDO("mysql:host={$db_config['host']};port={$db_config['port']};dbname={$db_config['name']};charset=utf8mb4", $db_config['user'], $db_config['pass']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (firstname, lastname, email, password) VALUES (?, ?, ?, ?)");
        $stmt->execute([htmlspecialchars($_POST['firstname']), htmlspecialchars($_POST['lastname']), $_POST['email'], $hash]);
        $message = "<div class='bg-green-500/10 border border-green-500/50 text-green-400 p-4 rounded-xl mb-6 text-center text-sm'>" . th('register.success') . "</div>";
    } catch (Exception $e) {
        $message = "<div class='bg-red-500/10 border border-red-500/50 text-red-400 p-4 rounded-xl mb-6 text-center text-sm'>" . t('register.error_dup') . "</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('register.title'); ?></title>
    <link rel="icon" type="image/png" href="https://heberge.orinstone.deepstone.fr/favicon.ico">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="manifest" href="/manifest.json">
    <style>
        body { background: radial-gradient(circle at top left, #1e293b, #020617); }
        .glass { background: rgba(255,255,255,0.03); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.1); }
        .gradient-text { background: linear-gradient(90deg, #38bdf8, #818cf8); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
    </style>
</head>
<body class="text-gray-200 flex flex-col justify-between min-h-screen font-sans">

    <main class="flex-grow flex items-center justify-center p-6 my-8">
        <div class="glass w-full max-w-lg p-10 rounded-3xl shadow-2xl">
            <div class="text-center mb-8">
                <h1 class="text-3xl font-black gradient-text uppercase tracking-tighter mb-2"><?php echo t('register.title'); ?></h1>
                <p class="text-gray-400 text-sm italic"><?php echo t('register.subtitle'); ?></p>
            </div>

            <?php echo $message; ?>

            <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div class="md:col-span-1">
                    <label class="block text-xs font-bold uppercase tracking-widest text-gray-500 mb-2"><?php echo t('register.firstname'); ?></label>
                    <input type="text" name="firstname" required class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 focus:outline-none focus:border-sky-500 transition text-white" placeholder="Jean">
                </div>
                <div class="md:col-span-1">
                    <label class="block text-xs font-bold uppercase tracking-widest text-gray-500 mb-2"><?php echo t('register.lastname'); ?></label>
                    <input type="text" name="lastname" required class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 focus:outline-none focus:border-sky-500 transition text-white" placeholder="Dupont">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-bold uppercase tracking-widest text-gray-500 mb-2"><?php echo t('register.email'); ?></label>
                    <input type="email" name="email" required class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 focus:outline-none focus:border-sky-500 transition text-white" placeholder="jean@exemple.fr">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-bold uppercase tracking-widest text-gray-500 mb-2"><?php echo t('register.password'); ?></label>
                    <input type="password" name="password" required class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 focus:outline-none focus:border-sky-500 transition text-white" placeholder="••••••••">
                </div>
                <button type="submit" class="md:col-span-2 bg-sky-600 hover:bg-sky-500 py-4 rounded-xl font-black uppercase tracking-widest transition shadow-lg shadow-sky-600/20 active:scale-95">
                    <?php echo t('register.submit'); ?>
                </button>
            </form>

            <div class="mt-8 pt-6 border-t border-white/5 text-center">
                <p class="text-gray-500 text-sm"><?php echo t('register.have_account'); ?> <a href="/login/" class="text-sky-400 font-bold hover:text-sky-300 transition"><?php echo t('register.login_link'); ?></a></p>
            </div>

            <a href="/" class="block text-center mt-6 text-xs text-gray-600 hover:text-gray-400 transition">
                <i class="fas fa-arrow-left mr-2"></i> <?php echo t('register.back'); ?>
            </a>
        </div>
    </main>

    <div class="fixed bottom-6 right-6 z-50">
        <a href="https://heberge.orinstone.deepstone.fr/discord/" target="_blank" class="bg-[#5865F2] hover:bg-[#4752C4] transition text-white px-5 py-3.5 rounded-full font-bold flex items-center gap-2 shadow-2xl hover:scale-105 transform duration-200">
            <i class="fab fa-discord text-xl"></i>
            <span class="hidden sm:inline text-sm"><?php echo t('discord.help'); ?></span>
        </a>
    </div>

    <?php include $_SERVER['DOCUMENT_ROOT'] . '/inc/footer.php'; ?>
</body>
</html>

<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/auth.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/inc/lang.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /login/');
    exit;
}

$active_nav = 'community';
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo t('chat.title'); ?> - OrinHeberge</title>
    <link rel="icon" type="image/png" href="https://heberge.orinstone.deepstone.fr/favicon.ico">
    
    <!-- ⚠️ OBLIGATOIRE : Tailwind + FontAwesome -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <link href="/inc/navbar.css?v=<?php echo filemtime($_SERVER['DOCUMENT_ROOT'] . '/inc/navbar.css'); ?>" rel="stylesheet">
    <link href="/inc/chat.css?v=<?php echo filemtime($_SERVER['DOCUMENT_ROOT'] . '/inc/chat.css'); ?>" rel="stylesheet">
</head>
<body class="bg-[#070a13] text-white min-h-screen">
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/inc/navbar.php'; ?>

    <div class="max-w-7xl mx-auto px-4 py-6">
        <div class="flex flex-col lg:flex-row gap-6 h-[calc(100vh-120px)]">
            
            <!-- 📋 Sidebar canaux -->
            <aside class="lg:w-64 shrink-0">
                <div class="bg-[#0b0f17] rounded-xl border border-white/5 p-4 h-full">
                    <h2 class="text-sm font-bold text-gray-400 uppercase tracking-wide mb-3">
                        <i class="fas fa-hashtag mr-2"></i><?php echo t('chat.channels'); ?>
                    </h2>
                    <div class="space-y-1">
                        <button class="channel-btn active w-full text-left px-3 py-2 rounded-lg bg-sky-600/20 text-sky-400 border border-sky-500/30 transition hover:bg-sky-600/30" data-channel="general">
                            <i class="fas fa-hashtag mr-2"></i><?php echo t('chat.channel_general'); ?>
                        </button>
                        <button class="channel-btn w-full text-left px-3 py-2 rounded-lg text-gray-400 hover:bg-white/5 hover:text-white transition" data-channel="support">
                            <i class="fas fa-headset mr-2"></i><?php echo t('chat.channel_support'); ?>
                        </button>
                        <button class="channel-btn w-full text-left px-3 py-2 rounded-lg text-gray-400 hover:bg-white/5 hover:text-white transition" data-channel="offtopic">
                            <i class="fas fa-comments mr-2"></i><?php echo t('chat.channel_offtopic'); ?>
                        </button>
                    </div>

                    <hr class="border-white/10 my-4">

                    <h2 class="text-sm font-bold text-gray-400 uppercase tracking-wide mb-3">
                        <i class="fas fa-users mr-2"></i><?php echo t('chat.online'); ?>
                    </h2>
                    <div id="onlineUsers" class="space-y-2 text-sm">
                        <!-- Rempli par JS -->
                    </div>
                </div>
            </aside>

            <!-- 💬 Zone de chat -->
            <main class="flex-1 flex flex-col bg-[#0b0f17] rounded-xl border border-white/5 overflow-hidden relative">
                
                <!-- Header canal -->
                <header class="px-6 py-4 border-b border-white/5 flex items-center justify-between">
                    <div>
                        <h1 class="text-xl font-bold flex items-center gap-2">
                            <i class="fas fa-hashtag text-sky-400"></i>
                            <span id="currentChannelName"><?php echo t('chat.channel_general'); ?></span>
                        </h1>
                        <p class="text-xs text-gray-500 mt-1" id="channelDescription">
                            <?php echo t('chat.channel_general_desc'); ?>
                        </p>
                    </div>
                    <div class="flex items-center gap-3">
                        <button id="emojiPickerBtn" class="text-gray-400 hover:text-sky-400 transition" title="Emojis">
                            <i class="far fa-smile text-xl"></i>
                        </button>
                    </div>
                </header>

                <!-- Messages -->
                <div id="messagesContainer" class="flex-1 overflow-y-auto p-6 space-y-4 custom-scrollbar">
                    <div class="text-center text-gray-500 py-8">
                        <i class="fas fa-spinner fa-spin text-2xl mb-2"></i>
                        <p><?php echo t('chat.loading'); ?></p>
                    </div>
                </div>

                <!-- Emoji Picker avec onglets -->
                <div id="emojiPicker" class="hidden absolute bottom-24 right-6 bg-[#11151d] border border-white/10 rounded-xl shadow-2xl p-4 z-50 w-96 max-h-[500px] overflow-hidden">
                    
                    <!-- Recherche -->
                    <div class="mb-3">
                        <div class="relative">
                            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 text-sm"></i>
                            <input type="text" id="emojiSearch" 
                                placeholder="<?php echo t('chat.search_emoji'); ?>" 
                                class="w-full bg-white/5 border border-white/10 rounded-lg pl-9 pr-3 py-2 text-sm text-white placeholder-gray-500 focus:outline-none focus:border-sky-500/50">
                        </div>
                    </div>
                    
                    <!-- Section Custom -->
                    <div id="customEmojisSection" class="mb-3">
                        <h3 class="text-xs font-bold text-gray-400 uppercase mb-2 flex items-center gap-1">
                            <i class="fas fa-star text-amber-400"></i>
                            <?php echo t('chat.custom_emojis'); ?>
                        </h3>
                        <div id="customEmojis" class="grid grid-cols-8 gap-1 max-h-24 overflow-y-auto custom-scrollbar pr-1">
                        </div>
                    </div>
                    
                    <hr class="border-white/10 my-3">
                    
                    <!-- Onglets catégories -->
                    <div class="mb-2">
                        <h3 class="text-xs font-bold text-gray-400 uppercase mb-2 flex items-center gap-1">
                            <i class="far fa-smile text-sky-400"></i>
                            <?php echo t('chat.standard_emojis'); ?>
                        </h3>
                        <div id="emojiCategoryTabs" class="flex gap-1 overflow-x-auto custom-scrollbar pb-2 mb-2">
                            <!-- Rempli par JS -->
                        </div>
                    </div>
                    
                    <!-- Grille emojis standards -->
                    <div id="standardEmojis" class="grid grid-cols-8 gap-1 max-h-48 overflow-y-auto custom-scrollbar pr-1">
                    </div>
                </div>

                <!-- Input message -->
                <footer class="p-4 border-t border-white/5">
                    <form id="messageForm" class="flex gap-3">
                        <div class="flex-1 relative">
                            <input 
                                type="text" 
                                id="messageInput" 
                                placeholder="<?php echo t('chat.placeholder'); ?>" 
                                class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 pr-12 text-white placeholder-gray-500 focus:outline-none focus:border-sky-500/50 transition"
                                autocomplete="off"
                                maxlength="2000"
                            >
                            <button type="button" id="emojiBtnInline" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-sky-400 transition">
                                <i class="far fa-smile"></i>
                            </button>
                        </div>
                        <button type="submit" class="bg-sky-600 hover:bg-sky-500 text-white px-6 py-3 rounded-xl font-semibold transition shadow-lg shadow-sky-900/20">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </form>
                </footer>
            </main>
        </div>
    </div>

    <script src="/inc/navbar.js?v=<?php echo filemtime($_SERVER['DOCUMENT_ROOT'] . '/inc/navbar.js'); ?>"></script>
    <script src="/inc/lang_switcher.js?v=<?php echo filemtime($_SERVER['DOCUMENT_ROOT'] . '/inc/lang_switcher.js'); ?>"></script>
    <script src="/inc/chat.js?v=<?php echo filemtime($_SERVER['DOCUMENT_ROOT'] . '/inc/chat.js'); ?>"></script>
</body>
</html>
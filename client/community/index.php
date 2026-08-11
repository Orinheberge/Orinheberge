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
    
    <!-- Tailwind + FontAwesome -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <link href="/assets/css/navbar.css?v=<?php echo filemtime($_SERVER['DOCUMENT_ROOT'] . '/inc/navbar.css'); ?>" rel="stylesheet">
    <link href="/assets/css/chat.css?v=<?php echo filemtime($_SERVER['DOCUMENT_ROOT'] . '/inc/chat.css'); ?>" rel="stylesheet">
    
    <style>
        /* ═══════════════════════════════════════════ */
        /* 🎨 STYLES CHAT ORINHEBERGE */
        /* ═══════════════════════════════════════════ */
        
        /* Message container */
        .chat-message {
            position: relative;
            padding: 8px 12px;
            border-radius: 8px;
            transition: background-color 0.2s;
        }

        .chat-message:hover {
            background-color: rgba(255, 255, 255, 0.02);
        }

        /* Menu d'actions au hover */
        .chat-message .message-actions {
            position: absolute;
            top: 4px;
            right: 8px;
            background: rgba(17, 21, 29, 0.95);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 4px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        }

        /* Messages épinglés */
        .pinned-message {
            background: linear-gradient(90deg, rgba(245, 158, 11, 0.05) 0%, transparent 100%);
            border-left: 3px solid rgba(245, 158, 11, 0.5);
        }

        /* Badges de réaction */
        .reaction-badge {
            cursor: pointer;
            transition: all 0.15s;
        }

        .reaction-badge:hover {
            transform: translateY(-2px);
        }

        /* Avatar */
        .chat-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            flex-shrink: 0;
        }

        /* Custom emoji dans messages */
        .chat-text img.custom-emoji {
            width: 24px;
            height: 24px;
            vertical-align: middle;
            margin: 0 2px;
        }

        /* Emoji picker items */
        .emoji-item {
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            border-radius: 6px;
            transition: all 0.15s;
            font-size: 1.4rem;
            user-select: none;
        }

        .emoji-item:hover {
            background-color: rgba(56, 189, 248, 0.15);
            transform: scale(1.25);
        }

        .emoji-item:active {
            transform: scale(0.95);
        }

        .emoji-item img {
            width: 28px;
            height: 28px;
            object-fit: contain;
        }

        /* Onglets emoji */
        .emoji-tab {
            white-space: nowrap;
            flex-shrink: 0;
        }

        .emoji-tab:hover {
            transform: translateY(-1px);
        }

        /* Scrollbar custom */
        .custom-scrollbar::-webkit-scrollbar { 
            width: 6px; 
            height: 6px;
        }
        .custom-scrollbar::-webkit-scrollbar-track { 
            background: transparent; 
        }
        .custom-scrollbar::-webkit-scrollbar-thumb { 
            background: rgba(255,255,255,0.1); 
            border-radius: 3px; 
        }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { 
            background: rgba(255,255,255,0.2); 
        }

        /* Barre messages épinglés */
        #pinnedMessagesBar {
            animation: slideDown 0.3s ease-out;
        }

        @keyframes slideDown {
            from { transform: translateY(-100%); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        /* Modal pinned */
        #pinnedModal {
            animation: fadeIn 0.2s ease-out;
        }

        /* Reaction picker modal */
        #reactionPickerModal {
            animation: fadeIn 0.15s ease-out;
        }

        .reaction-picker-container {
            animation: scaleIn 0.2s ease-out;
            transform-origin: center;
        }

        @keyframes scaleIn {
            from { 
                opacity: 0; 
                transform: scale(0.9); 
            }
            to { 
                opacity: 1; 
                transform: scale(1); 
            }
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .reaction-tab {
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            transition: all 0.15s;
        }

        .reaction-tab:hover {
            transform: translateY(-2px);
        }

        .reaction-emoji-btn {
            user-select: none;
            cursor: pointer;
            transition: all 0.1s;
        }

        .reaction-emoji-btn:active {
            transform: scale(0.9);
        }

        .reaction-emoji-btn img {
            pointer-events: none;
        }

        /* Animation suppression */
        .chat-message[style*="opacity: 0"] {
            pointer-events: none;
        }

        /* Mobile : picker pleine largeur */
        @media (max-width: 640px) {
            .reaction-picker-container {
                max-width: 100% !important;
                max-height: 80vh !important;
                border-radius: 16px !important;
            }
            
            .chat-message .message-actions {
                opacity: 1 !important;
                position: static;
                background: transparent;
                border: none;
                box-shadow: none;
                margin-top: 8px;
            }
        }

        /* Online users */
        #onlineUsers > div:hover {
            background: rgba(56, 189, 248, 0.05);
        }
    </style>
</head>
<body class="bg-[#070a13] text-white min-h-screen">
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/inc/navbar.php'; ?>

    <div class="max-w-7xl mx-auto px-4 py-6">
        <div class="flex flex-col lg:flex-row gap-6 h-[calc(100vh-120px)]">
            
            <!-- ═══════════════════════════════════════════ -->
            <!-- 📋 SIDEBAR CANAUX + USERS ONLINE -->
            <!-- ═══════════════════════════════════════════ -->
            <aside class="lg:w-64 shrink-0">
                <div class="bg-[#0b0f17] rounded-xl border border-white/5 p-4 h-full flex flex-col">
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

                    <h2 class="text-sm font-bold text-gray-400 uppercase tracking-wide mb-3 flex items-center justify-between">
                        <span>
                            <i class="fas fa-users mr-2"></i><?php echo t('chat.online'); ?>
                        </span>
                        <span id="onlineCount" class="text-xs bg-sky-500/20 text-sky-400 px-2 py-0.5 rounded-full">0</span>
                    </h2>
                    <div id="onlineUsers" class="space-y-1 text-sm flex-1 overflow-y-auto custom-scrollbar pr-1">
                        <!-- Rempli par JS -->
                    </div>
                </div>
            </aside>

            <!-- ═══════════════════════════════════════════ -->
            <!-- 💬 ZONE DE CHAT PRINCIPALE -->
            <!-- ═══════════════════════════════════════════ -->
            <main class="flex-1 flex flex-col bg-[#0b0f17] rounded-xl border border-white/5 overflow-hidden relative">
                
                <!-- Header canal -->
                <header class="px-6 py-4 border-b border-white/5 flex items-center justify-between shrink-0">
                    <div class="flex-1 min-w-0">
                        <h1 class="text-xl font-bold flex items-center gap-2">
                            <i class="fas fa-hashtag text-sky-400"></i>
                            <span id="currentChannelName"><?php echo t('chat.channel_general'); ?></span>
                        </h1>
                        <p class="text-xs text-gray-500 mt-1 truncate" id="channelDescription">
                            <?php echo t('chat.channel_general_desc'); ?>
                        </p>
                    </div>
                    <div class="flex items-center gap-2 ml-4">
                        <button id="pinnedMessagesBtn" class="text-gray-400 hover:text-amber-400 transition p-2 rounded-lg hover:bg-white/5" title="Messages épinglés">
                            <i class="fas fa-thumbtack text-lg"></i>
                            <span id="pinnedCount" class="hidden ml-1 text-xs bg-amber-500/20 text-amber-400 px-1.5 py-0.5 rounded-full"></span>
                        </button>
                        <button id="emojiPickerBtn" class="text-gray-400 hover:text-sky-400 transition p-2 rounded-lg hover:bg-white/5" title="Emojis">
                            <i class="far fa-smile text-xl"></i>
                        </button>
                    </div>
                </header>

                <!-- Barre messages épinglés (dynamique) -->
                <div id="pinnedMessagesBar" class="hidden bg-amber-500/5 border-b border-amber-500/20 px-4 py-2 shrink-0">
                    <!-- Rempli par JS -->
                </div>

                <!-- Zone messages -->
                <div id="messagesContainer" class="flex-1 overflow-y-auto p-6 space-y-2 custom-scrollbar">
                    <div class="text-center text-gray-500 py-8">
                        <i class="fas fa-spinner fa-spin text-2xl mb-2"></i>
                        <p><?php echo t('chat.loading'); ?></p>
                    </div>
                </div>

                <!-- Emoji Picker avec onglets -->
                <div id="emojiPicker" class="hidden absolute bottom-24 right-6 bg-[#11151d] border border-white/10 rounded-xl shadow-2xl p-4 z-50 w-96 max-h-[500px] overflow-hidden flex flex-col">
                    
                    <!-- Recherche -->
                    <div class="mb-3 shrink-0">
                        <div class="relative">
                            <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 text-sm"></i>
                            <input type="text" id="emojiSearch" 
                                placeholder="<?php echo t('chat.search_emoji'); ?>" 
                                class="w-full bg-white/5 border border-white/10 rounded-lg pl-9 pr-3 py-2 text-sm text-white placeholder-gray-500 focus:outline-none focus:border-sky-500/50"
                                autocomplete="off">
                        </div>
                    </div>
                    
                    <!-- Section Custom -->
                    <div id="customEmojisSection" class="mb-3 shrink-0">
                        <h3 class="text-xs font-bold text-gray-400 uppercase mb-2 flex items-center gap-1">
                            <i class="fas fa-star text-amber-400"></i>
                            <?php echo t('chat.custom_emojis'); ?>
                        </h3>
                        <div id="customEmojis" class="grid grid-cols-8 gap-1 max-h-24 overflow-y-auto custom-scrollbar pr-1">
                            <!-- Rempli par JS -->
                        </div>
                    </div>
                    
                    <hr class="border-white/10 my-3 shrink-0">
                    
                    <!-- Onglets catégories -->
                    <div class="mb-2 shrink-0">
                        <h3 class="text-xs font-bold text-gray-400 uppercase mb-2 flex items-center gap-1">
                            <i class="far fa-smile text-sky-400"></i>
                            <?php echo t('chat.standard_emojis'); ?>
                        </h3>
                        <div id="emojiCategoryTabs" class="flex gap-1 overflow-x-auto custom-scrollbar pb-2 mb-2">
                            <!-- Rempli par JS -->
                        </div>
                    </div>
                    
                    <!-- Grille emojis standards -->
                    <div id="standardEmojis" class="grid grid-cols-8 gap-1 flex-1 overflow-y-auto custom-scrollbar pr-1">
                        <!-- Rempli par JS -->
                    </div>
                </div>

                <!-- Input message -->
                <footer class="p-4 border-t border-white/5 shrink-0">
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
                        <button type="submit" class="bg-sky-600 hover:bg-sky-500 text-white px-6 py-3 rounded-xl font-semibold transition shadow-lg shadow-sky-900/20 active:scale-95">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </form>
                </footer>
            </main>
        </div>
    </div>

    <!-- Scripts -->
    <script src="/assets/js/navbar.js?v=<?php echo filemtime($_SERVER['DOCUMENT_ROOT'] . '/assets/js/navbar.js'); ?>"></script>
    <script src="/assets/js/lang_switcher.js?v=<?php echo filemtime($_SERVER['DOCUMENT_ROOT'] . '/assets/js/lang_switcher.js'); ?>"></script>
    <script src="/assets/js/chat.js?v=<?php echo filemtime($_SERVER['DOCUMENT_ROOT'] . '/assets/js/chat.js'); ?>"></script>
</body>
</html>
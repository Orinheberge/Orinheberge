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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="theme-color" content="#070a13">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title><?php echo t('chat.title'); ?> - OrinHeberge</title>
    <link rel="icon" type="image/png" href="https://heberge.orinstone.deepstone.fr/favicon.ico">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <link href="/inc/navbar.css?v=<?php echo filemtime($_SERVER['DOCUMENT_ROOT'] . '/inc/navbar.css'); ?>" rel="stylesheet">
    <link href="/inc/chat.css?v=<?php echo filemtime($_SERVER['DOCUMENT_ROOT'] . '/inc/chat.css'); ?>" rel="stylesheet">
    
    <style>
        /* ═══════════════════════════════════════════ */
        /* 🎨 STYLES CHAT ORINHEBERGE */
        /* ═══════════════════════════════════════════ */
        
        html, body {
            height: 100%;
            overflow: hidden;
        }
        
        body {
            padding-top: env(safe-area-inset-top);
            padding-bottom: env(safe-area-inset-bottom);
            padding-left: env(safe-area-inset-left);
            padding-right: env(safe-area-inset-right);
        }
        
        /* Layout principal : plein écran */
        .chat-layout {
            height: calc(100vh - 73px); /* navbar hauteur */
            height: calc(100dvh - 73px); /* dynamic viewport (mobile) */
        }
        
        @supports (height: 100dvh) {
            .chat-layout {
                height: calc(100dvh - 73px);
            }
        }
        
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

        /* Menu d'actions au hover (desktop only) */
        @media (min-width: 769px) {
            .chat-message .message-actions {
                position: absolute;
                top: 4px;
                right: 8px;
                background: rgba(17, 21, 29, 0.95);
                border: 1px solid rgba(255, 255, 255, 0.1);
                border-radius: 8px;
                padding: 4px;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
                opacity: 0;
                transition: opacity 0.2s;
            }
            
            .chat-message:hover .message-actions {
                opacity: 1;
            }
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
            -webkit-tap-highlight-color: transparent;
        }

        .emoji-item:hover {
            background-color: rgba(56, 189, 248, 0.15);
            transform: scale(1.25);
        }

        .emoji-item:active {
            transform: scale(0.95);
            background-color: rgba(56, 189, 248, 0.25);
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
            -webkit-tap-highlight-color: transparent;
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
            -webkit-tap-highlight-color: transparent;
        }

        .reaction-tab:hover {
            transform: translateY(-2px);
        }

        .reaction-emoji-btn {
            user-select: none;
            cursor: pointer;
            transition: all 0.1s;
            -webkit-tap-highlight-color: transparent;
        }

        .reaction-emoji-btn:active {
            transform: scale(0.9);
            background: rgba(255,255,255,0.1) !important;
        }

        .reaction-emoji-btn img {
            pointer-events: none;
        }

        /* Animation suppression */
        .chat-message[style*="opacity: 0"] {
            pointer-events: none;
        }

        /* Online users */
        #onlineUsers > div:hover {
            background: rgba(56, 189, 248, 0.05);
        }
        
        /* ═══════════════════════════════════════════ */
        /* 📱 MOBILE : DRAWER & RESPONSIVE */
        /* ═══════════════════════════════════════════ */
        
        /* Overlay sidebar mobile */
        #mobileSidebarOverlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(4px);
            z-index: 40;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s;
        }
        
        #mobileSidebarOverlay.active {
            opacity: 1;
            pointer-events: auto;
        }
        
        /* Sidebar mobile : drawer */
        @media (max-width: 1023px) {
            .chat-layout {
                height: calc(100dvh - 73px);
            }
            
            .chat-sidebar-mobile {
                position: fixed;
                top: 73px; /* sous la navbar */
                left: 0;
                bottom: 0;
                width: 280px;
                max-width: 85vw;
                z-index: 45;
                transform: translateX(-100%);
                transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                border-right: 1px solid rgba(255,255,255,0.05);
            }
            
            .chat-sidebar-mobile.open {
                transform: translateX(0);
            }
            
            /* Avatar plus petit sur mobile */
            .chat-avatar {
                width: 36px;
                height: 36px;
            }
            
            /* Messages : padding réduit */
            .chat-message {
                padding: 6px 8px;
            }
            
            /* Menu actions toujours visible sur mobile */
            .chat-message .message-actions {
                opacity: 1 !important;
                position: static !important;
                background: transparent !important;
                border: none !important;
                box-shadow: none !important;
                margin-top: 6px;
                padding: 0 !important;
            }
            
            /* Emoji picker pleine largeur */
            #emojiPicker {
                left: 8px !important;
                right: 8px !important;
                bottom: 80px !important;
                width: auto !important;
                max-width: calc(100vw - 16px);
                max-height: 70vh !important;
            }
            
            /* Header mobile plus compact */
            .chat-header-mobile {
                padding: 12px 16px !important;
            }
            
            /* Footer input : safe area + fixed quand clavier ouvert */
            .chat-footer-mobile {
                padding-bottom: calc(16px + env(safe-area-inset-bottom)) !important;
            }
            
            /* Bouton canaux mobile */
            .mobile-menu-btn {
                display: flex !important;
            }
            
            /* Zone messages : padding réduit */
            #messagesContainer {
                padding: 12px !important;
            }
        }
        
        @media (min-width: 1024px) {
            .mobile-menu-btn {
                display: none !important;
            }
            
            .chat-sidebar-mobile {
                position: static !important;
                transform: none !important;
                width: 256px !important;
            }
            
            #mobileSidebarOverlay {
                display: none !important;
            }
        }
        
        /* Swipe indicator */
        .swipe-indicator {
            width: 40px;
            height: 4px;
            background: rgba(255,255,255,0.2);
            border-radius: 2px;
            margin: 8px auto;
        }
        
        /* Bouton scroll-to-bottom */
        #scrollToBottomBtn {
            position: absolute;
            bottom: 90px;
            right: 20px;
            z-index: 30;
            opacity: 0;
            pointer-events: none;
            transition: all 0.3s;
            transform: translateY(10px);
        }
        
        #scrollToBottomBtn.visible {
            opacity: 1;
            pointer-events: auto;
            transform: translateY(0);
        }
        
        /* Typing indicator */
        .typing-dots span {
            display: inline-block;
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #64748b;
            animation: typing 1.4s infinite;
            margin: 0 1px;
        }
        
        .typing-dots span:nth-child(2) { animation-delay: 0.2s; }
        .typing-dots span:nth-child(3) { animation-delay: 0.4s; }
        
        @keyframes typing {
            0%, 60%, 100% { transform: translateY(0); opacity: 0.4; }
            30% { transform: translateY(-8px); opacity: 1; }
        }
        
        /* Touch feedback pour mobile */
        @media (hover: none) {
            .channel-btn:active,
            .reaction-badge:active,
            button:active {
                transform: scale(0.97);
            }
            
            .chat-message:active {
                background-color: rgba(255, 255, 255, 0.04);
            }
        }
    </style>
</head>
<body class="bg-[#070a13] text-white min-h-screen overflow-hidden">
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/inc/navbar.php'; ?>

    <!-- Overlay sidebar mobile -->
    <div id="mobileSidebarOverlay"></div>

    <div class="max-w-7xl mx-auto chat-layout flex flex-col lg:flex-row gap-0 lg:gap-6 lg:px-4 lg:py-6">
        
        <!-- ═══════════════════════════════════════════ -->
        <!-- 📋 SIDEBAR CANAUX + USERS ONLINE -->
        <!-- ═══════════════════════════════════════════ -->
        <aside id="chatSidebar" class="chat-sidebar-mobile lg:w-64 shrink-0 bg-[#0b0f17] lg:rounded-xl border-r lg:border border-white/5">
            <div class="p-4 h-full flex flex-col overflow-hidden">
                
                <!-- Header sidebar (mobile only) -->
                <div class="lg:hidden flex items-center justify-between mb-3 pb-3 border-b border-white/5">
                    <h2 class="text-sm font-bold text-white flex items-center gap-2">
                        <i class="fas fa-comments text-sky-400"></i>
                        Navigation
                    </h2>
                    <button id="closeSidebarBtn" class="w-8 h-8 rounded-lg bg-white/5 hover:bg-white/10 flex items-center justify-center text-gray-400 transition">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <!-- Swipe indicator mobile -->
                <div class="lg:hidden swipe-indicator"></div>
                
                <h2 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2 px-1">
                    <i class="fas fa-hashtag mr-1.5"></i><?php echo t('chat.channels'); ?>
                </h2>
                <div class="space-y-1 mb-4">
                    <button class="channel-btn active w-full text-left px-3 py-2.5 rounded-lg bg-sky-600/20 text-sky-400 border border-sky-500/30 transition hover:bg-sky-600/30 text-sm font-medium" data-channel="general">
                        <i class="fas fa-hashtag mr-2 text-xs"></i><?php echo t('chat.channel_general'); ?>
                    </button>
                    <button class="channel-btn w-full text-left px-3 py-2.5 rounded-lg text-gray-400 hover:bg-white/5 hover:text-white transition text-sm" data-channel="support">
                        <i class="fas fa-headset mr-2 text-xs"></i><?php echo t('chat.channel_support'); ?>
                    </button>
                    <button class="channel-btn w-full text-left px-3 py-2.5 rounded-lg text-gray-400 hover:bg-white/5 hover:text-white transition text-sm" data-channel="offtopic">
                        <i class="fas fa-comments mr-2 text-xs"></i><?php echo t('chat.channel_offtopic'); ?>
                    </button>
                </div>

                <hr class="border-white/10 mb-4">

                <div class="flex items-center justify-between mb-2 px-1">
                    <h2 class="text-xs font-bold text-gray-500 uppercase tracking-wider flex items-center gap-1.5">
                        <i class="fas fa-users"></i>
                        <span><?php echo t('chat.online'); ?></span>
                    </h2>
                    <span id="onlineCount" class="text-[10px] bg-sky-500/20 text-sky-400 px-2 py-0.5 rounded-full font-bold">0</span>
                </div>
                <div id="onlineUsers" class="space-y-0.5 text-sm flex-1 overflow-y-auto custom-scrollbar pr-1 -mx-1 px-1">
                    <!-- Rempli par JS -->
                </div>
            </div>
        </aside>

        <!-- ═══════════════════════════════════════════ -->
        <!-- 💬 ZONE DE CHAT PRINCIPALE -->
        <!-- ═══════════════════════════════════════════ -->
        <main class="flex-1 flex flex-col bg-[#0b0f17] lg:rounded-xl border-white/5 overflow-hidden relative min-w-0 h-full lg:border">
            
            <!-- Header canal -->
            <header class="chat-header-mobile px-4 lg:px-6 py-3 lg:py-4 border-b border-white/5 flex items-center justify-between shrink-0 bg-[#0b0f17]/95 backdrop-blur-md">
                
                <!-- Bouton menu mobile -->
                <button id="openSidebarBtn" class="mobile-menu-btn hidden w-10 h-10 rounded-lg bg-white/5 hover:bg-white/10 border border-white/10 items-center justify-center text-gray-300 transition mr-2 shrink-0">
                    <i class="fas fa-bars"></i>
                </button>
                
                <div class="flex-1 min-w-0">
                    <h1 class="text-base lg:text-xl font-bold flex items-center gap-2 truncate">
                        <i class="fas fa-hashtag text-sky-400 shrink-0"></i>
                        <span id="currentChannelName" class="truncate"><?php echo t('chat.channel_general'); ?></span>
                    </h1>
                    <p class="text-[11px] lg:text-xs text-gray-500 mt-0.5 truncate hidden sm:block" id="channelDescription">
                        <?php echo t('chat.channel_general_desc'); ?>
                    </p>
                </div>
                
                <div class="flex items-center gap-1 lg:gap-2 ml-2 shrink-0">
                    <button id="pinnedMessagesBtn" class="text-gray-400 hover:text-amber-400 transition w-9 h-9 lg:w-10 lg:h-10 rounded-lg hover:bg-white/5 flex items-center justify-center relative" title="Messages épinglés">
                        <i class="fas fa-thumbtack text-sm lg:text-base"></i>
                        <span id="pinnedCount" class="hidden absolute -top-1 -right-1 text-[9px] bg-amber-500 text-white min-w-[16px] h-4 px-1 rounded-full font-bold flex items-center justify-center"></span>
                    </button>
                    <button id="emojiPickerBtn" class="text-gray-400 hover:text-sky-400 transition w-9 h-9 lg:w-10 lg:h-10 rounded-lg hover:bg-white/5 flex items-center justify-center" title="Emojis">
                        <i class="far fa-smile text-base lg:text-xl"></i>
                    </button>
                </div>
            </header>

            <!-- Barre messages épinglés (dynamique) -->
            <div id="pinnedMessagesBar" class="hidden bg-amber-500/5 border-b border-amber-500/20 px-3 lg:px-4 py-2 shrink-0">
                <!-- Rempli par JS -->
            </div>

            <!-- Zone messages -->
            <div id="messagesContainer" class="flex-1 overflow-y-auto p-4 lg:p-6 space-y-2 custom-scrollbar">
                <div class="text-center text-gray-500 py-8">
                    <i class="fas fa-spinner fa-spin text-2xl mb-2"></i>
                    <p><?php echo t('chat.loading'); ?></p>
                </div>
            </div>
            
            <!-- Bouton scroll-to-bottom -->
            <button id="scrollToBottomBtn" class="w-10 h-10 rounded-full bg-sky-600 hover:bg-sky-500 text-white shadow-lg shadow-sky-900/40 flex items-center justify-center transition">
                <i class="fas fa-arrow-down"></i>
            </button>

            <!-- Emoji Picker avec onglets -->
            <div id="emojiPicker" class="hidden absolute bottom-24 right-6 bg-[#11151d] border border-white/10 rounded-xl shadow-2xl p-3 lg:p-4 z-50 w-[calc(100%-2rem)] lg:w-96 max-h-[60vh] lg:max-h-[500px] overflow-hidden flex flex-col">
                
                <!-- Recherche -->
                <div class="mb-2 lg:mb-3 shrink-0">
                    <div class="relative">
                        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 text-sm"></i>
                        <input type="text" id="emojiSearch" 
                            placeholder="<?php echo t('chat.search_emoji'); ?>" 
                            class="w-full bg-white/5 border border-white/10 rounded-lg pl-9 pr-3 py-2 text-sm text-white placeholder-gray-500 focus:outline-none focus:border-sky-500/50"
                            autocomplete="off">
                    </div>
                </div>
                
                <!-- Section Custom -->
                <div id="customEmojisSection" class="mb-2 lg:mb-3 shrink-0">
                    <h3 class="text-[10px] lg:text-xs font-bold text-gray-400 uppercase mb-1.5 flex items-center gap-1">
                        <i class="fas fa-star text-amber-400"></i>
                        <?php echo t('chat.custom_emojis'); ?>
                    </h3>
                    <div id="customEmojis" class="grid grid-cols-7 sm:grid-cols-8 gap-0.5 lg:gap-1 max-h-20 lg:max-h-24 overflow-y-auto custom-scrollbar pr-1">
                        <!-- Rempli par JS -->
                    </div>
                </div>
                
                <hr class="border-white/10 my-2 lg:my-3 shrink-0">
                
                <!-- Onglets catégories -->
                <div class="mb-2 shrink-0">
                    <h3 class="text-[10px] lg:text-xs font-bold text-gray-400 uppercase mb-1.5 flex items-center gap-1">
                        <i class="far fa-smile text-sky-400"></i>
                        <?php echo t('chat.standard_emojis'); ?>
                    </h3>
                    <div id="emojiCategoryTabs" class="flex gap-1 overflow-x-auto custom-scrollbar pb-1.5 mb-1">
                        <!-- Rempli par JS -->
                    </div>
                </div>
                
                <!-- Grille emojis standards -->
                <div id="standardEmojis" class="grid grid-cols-7 sm:grid-cols-8 gap-0.5 lg:gap-1 flex-1 overflow-y-auto custom-scrollbar pr-1">
                    <!-- Rempli par JS -->
                </div>
            </div>

            <!-- Input message -->
            <footer class="chat-footer-mobile p-3 lg:p-4 border-t border-white/5 shrink-0 bg-[#0b0f17]/95 backdrop-blur-md">
                <form id="messageForm" class="flex gap-2 lg:gap-3 items-end">
                    <div class="flex-1 relative">
                        <textarea 
                            id="messageInput"
                            rows="1"
                            placeholder="<?php echo t('chat.placeholder'); ?>" 
                            class="w-full bg-white/5 border border-white/10 rounded-xl px-3 lg:px-4 py-2.5 lg:py-3 pr-10 text-sm lg:text-base text-white placeholder-gray-500 focus:outline-none focus:border-sky-500/50 transition resize-none max-h-32"
                            autocomplete="off"
                            maxlength="2000"
                        ></textarea>
                        <button type="button" id="emojiBtnInline" class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-sky-400 transition w-8 h-8 flex items-center justify-center rounded-lg hover:bg-white/5">
                            <i class="far fa-smile"></i>
                        </button>
                    </div>
                    <button type="submit" id="sendBtn" class="bg-sky-600 hover:bg-sky-500 active:bg-sky-700 text-white w-11 h-11 lg:w-auto lg:px-6 lg:py-3 rounded-xl font-semibold transition shadow-lg shadow-sky-900/20 active:scale-95 flex items-center justify-center shrink-0">
                        <i class="fas fa-paper-plane lg:mr-2"></i>
                        <span class="hidden lg:inline">Envoyer</span>
                    </button>
                </form>
            </footer>
        </main>
    </div>

    <!-- Scripts -->
    <script src="/inc/navbar.js?v=<?php echo filemtime($_SERVER['DOCUMENT_ROOT'] . '/inc/navbar.js'); ?>"></script>
    <script src="/inc/lang_switcher.js?v=<?php echo filemtime($_SERVER['DOCUMENT_ROOT'] . '/inc/lang_switcher.js'); ?>"></script>
    <script src="/inc/chat.js?v=<?php echo filemtime($_SERVER['DOCUMENT_ROOT'] . '/inc/chat.js'); ?>"></script>
    
    <script>
    // ═══════════════════════════════════════════
    // 📱 GESTION SIDEBAR MOBILE
    // ═══════════════════════════════════════════
    (function() {
        const sidebar = document.getElementById('chatSidebar');
        const overlay = document.getElementById('mobileSidebarOverlay');
        const openBtn = document.getElementById('openSidebarBtn');
        const closeBtn = document.getElementById('closeSidebarBtn');
        
        function openSidebar() {
            sidebar.classList.add('open');
            overlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        
        function closeSidebar() {
            sidebar.classList.remove('open');
            overlay.classList.remove('active');
            document.body.style.overflow = '';
        }
        
        if (openBtn) openBtn.addEventListener('click', openSidebar);
        if (closeBtn) closeBtn.addEventListener('click', closeSidebar);
        if (overlay) overlay.addEventListener('click', closeSidebar);
        
        // Fermer sidebar quand on change de canal (mobile)
        document.querySelectorAll('.channel-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                if (window.innerWidth < 1024) {
                    setTimeout(closeSidebar, 150);
                }
            });
        });
        
        // Swipe to open (depuis le bord gauche)
        let touchStartX = 0;
        let touchEndX = 0;
        
        document.addEventListener('touchstart', (e) => {
            touchStartX = e.changedTouches[0].screenX;
        }, { passive: true });
        
        document.addEventListener('touchend', (e) => {
            touchEndX = e.changedTouches[0].screenX;
            handleSwipe();
        }, { passive: true });
        
        function handleSwipe() {
            const swipeDistance = touchEndX - touchStartX;
            // Swipe droite depuis bord gauche (< 30px du bord)
            if (touchStartX < 30 && swipeDistance > 80 && window.innerWidth < 1024) {
                openSidebar();
            }
            // Swipe gauche pour fermer
            if (sidebar.classList.contains('open') && swipeDistance < -80) {
                closeSidebar();
            }
        }
        
        // ESC pour fermer
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && sidebar.classList.contains('open')) {
                closeSidebar();
            }
        });
    })();
    
    // ═══════════════════════════════════════════
    // 📱 TEXTAREA AUTO-RESIZE
    // ═══════════════════════════════════════════
    (function() {
        const textarea = document.getElementById('messageInput');
        if (!textarea) return;
        
        function autoResize() {
            textarea.style.height = 'auto';
            textarea.style.height = Math.min(textarea.scrollHeight, 128) + 'px';
        }
        
        textarea.addEventListener('input', autoResize);
        
        // Envoyer avec Entrée (Shift+Entrée = nouvelle ligne)
        textarea.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                document.getElementById('messageForm').dispatchEvent(new Event('submit'));
            }
        });
    })();
    
    // ═══════════════════════════════════════════
    // ⬇️ SCROLL TO BOTTOM BUTTON
    // ═══════════════════════════════════════════
    (function() {
        const container = document.getElementById('messagesContainer');
        const btn = document.getElementById('scrollToBottomBtn');
        if (!container || !btn) return;
        
        container.addEventListener('scroll', () => {
            const distanceFromBottom = container.scrollHeight - container.scrollTop - container.clientHeight;
            if (distanceFromBottom > 200) {
                btn.classList.add('visible');
            } else {
                btn.classList.remove('visible');
            }
        });
        
        btn.addEventListener('click', () => {
            container.scrollTo({
                top: container.scrollHeight,
                behavior: 'smooth'
            });
        });
    })();
    
    // ═══════════════════════════════════════════
    // 📱 PRÉVENTION ZOOM DOUBLE-TAP
    // ═══════════════════════════════════════════
    (function() {
        let lastTouchEnd = 0;
        document.addEventListener('touchend', (e) => {
            const now = Date.now();
            if (now - lastTouchEnd <= 300) {
                e.preventDefault();
            }
            lastTouchEnd = now;
        }, { passive: false });
    })();
    
    // ═══════════════════════════════════════════
    // 📱 SCROLL AUTO VERS LE BAS (nouveau message)
    // ═══════════════════════════════════════════
    (function() {
        const container = document.getElementById('messagesContainer');
        if (!container) return;
        
        const observer = new MutationObserver((mutations) => {
            const distanceFromBottom = container.scrollHeight - container.scrollTop - container.clientHeight;
            // Auto-scroll seulement si on est déjà proche du bas
            if (distanceFromBottom < 150) {
                container.scrollTo({
                    top: container.scrollHeight,
                    behavior: 'smooth'
                });
            }
        });
        
        observer.observe(container, { childList: true, subtree: true });
    })();
    </script>
</body>
</html>

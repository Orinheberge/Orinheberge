<?php
// notifications include: outputs the bell button and polling JS for announcements
?>
<link href="/inc/notifications.css?v=<?php echo filemtime($_SERVER['DOCUMENT_ROOT'] . '/inc/notifications.css'); ?>" rel="stylesheet">
<div id="notificationRoot" class="relative">
    <button id="notifBtn" class="glass px-3 py-2 rounded-full text-xs flex items-center gap-2 hover:bg-white/5 transition relative">
        <i class="fas fa-bell"></i>
        <span id="notifBadge" class="absolute -top-1 -right-1 bg-rose-500 text-white text-[10px] px-1.5 py-0.5 rounded-full hidden min-w-[18px] text-center">0</span>
    </button>
    <div id="notifDropdown" class="hidden absolute right-0 mt-2 w-96 bg-[#071018] border border-white/5 rounded-xl shadow-2xl z-50 overflow-hidden">
        <div class="flex justify-between items-center px-4 py-3 border-b border-white/5 bg-white/[0.02]">
            <div class="flex items-center gap-2">
                <i class="fas fa-bullhorn text-rose-400 text-sm"></i>
                <strong class="text-sm text-white">Annonces</strong>
            </div>
            <button id="markAllRead" class="text-xs text-sky-400 hover:text-sky-300 hover:underline transition">
                <i class="fas fa-check-double mr-1"></i>Tout marquer lu
            </button>
        </div>
        <div id="notifList" class="space-y-1 max-h-96 overflow-auto p-2"></div>
        <div id="notifEmpty" class="text-gray-500 text-sm py-8 text-center hidden">
            <i class="fas fa-inbox text-2xl mb-2 block text-gray-600"></i>
            Aucune annonce
        </div>
    </div>
</div>



<script src='inc/notifications.js'></script>
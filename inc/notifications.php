<?php
// notifications include: outputs the bell button and polling JS for announcements
?>
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

<style>
.notif-item {
    padding: 0.75rem;
    border-radius: 0.5rem;
    transition: all 0.15s;
    border: 1px solid transparent;
    cursor: pointer;
}
.notif-item:hover {
    background: rgba(255,255,255,0.03);
    border-color: rgba(255,255,255,0.05);
}
.notif-item.unread {
    background: rgba(244,63,94,0.03);
    border-color: rgba(244,63,94,0.08);
}
.notif-item.unread:hover {
    background: rgba(244,63,94,0.05);
}
.notif-type-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.15rem 0.5rem;
    border-radius: 9999px;
    font-size: 0.65rem;
    font-weight: 600;
}
.notif-type-info { background: rgba(56,189,248,0.1); color: #38bdf8; border: 1px solid rgba(56,189,248,0.2); }
.notif-type-warning { background: rgba(249,115,22,0.1); color: #f97316; border: 1px solid rgba(249,115,22,0.2); }
.notif-type-success { background: rgba(34,197,94,0.1); color: #22c55e; border: 1px solid rgba(34,197,94,0.2); }
.notif-type-error { background: rgba(239,68,68,0.1); color: #ef4444; border: 1px solid rgba(239,68,68,0.2); }
.notif-type-announcement { background: rgba(244,63,94,0.1); color: #f43f5e; border: 1px solid rgba(244,63,94,0.2); }
</style>

<script>
(function(){
    const btn = document.getElementById('notifBtn');
    const dropdown = document.getElementById('notifDropdown');
    const badge = document.getElementById('notifBadge');
    const list = document.getElementById('notifList');
    const empty = document.getElementById('notifEmpty');
    const markAll = document.getElementById('markAllRead');

    if (!btn) return;

    const typeConfig = {
        info: { icon: 'fa-info-circle', label: 'Info' },
        warning: { icon: 'fa-exclamation-triangle', label: 'Attention' },
        success: { icon: 'fa-check-circle', label: 'Succès' },
        error: { icon: 'fa-times-circle', label: 'Erreur' },
        announcement: { icon: 'fa-bullhorn', label: 'Annonce' }
    };

    // Toggle dropdown
    btn.addEventListener('click', function(e){
        e.stopPropagation();
        dropdown.classList.toggle('hidden');
        if (!dropdown.classList.contains('hidden')) {
            loadList();
        }
    });

    // Close dropdown when clicking outside
    document.addEventListener('click', function(e){
        if (!dropdown.contains(e.target) && !btn.contains(e.target)) {
            dropdown.classList.add('hidden');
        }
    });

    // Mark all as read
    if (markAll) {
        markAll.addEventListener('click', async function(e){
            e.stopPropagation();
            try {
                await fetch('/shop/lib/notifications/api.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'action=mark_all'
                });
                updateCount();
                loadList();
            } catch(e) {
                console.error('Erreur mark_all:', e);
            }
        });
    }

    // Update unread count
    async function updateCount(){
        try {
            const res = await fetch('/shop/lib/notifications/api.php?action=count');
            const j = await res.json();
            const n = j.unread || 0;
            if (n > 0) {
                badge.textContent = n > 99 ? '99+' : n;
                badge.classList.remove('hidden');
            } else {
                badge.classList.add('hidden');
            }
        } catch(e) {
            console.error('Erreur updateCount:', e);
        }
    }

    // Load notifications list
    async function loadList(){
        try {
            const res = await fetch('/shop/lib/notifications/api.php?action=list&limit=20');
            const j = await res.json();
            list.innerHTML = '';
            
            if (j.notifications && j.notifications.length) {
                empty.classList.add('hidden');
                
                j.notifications.forEach(function(n){
                    const div = document.createElement('div');
                    div.className = 'notif-item' + (n.is_read == 0 ? ' unread' : '');
                    
                    const type = n.type || 'info';
                    const typeInfo = typeConfig[type] || typeConfig.info;
                    
                    // Header with type badge and date
                    const header = document.createElement('div');
                    header.className = 'flex items-center justify-between mb-1.5';
                    header.innerHTML = `
                        <span class="notif-type-badge notif-type-${type}">
                            <i class="fas ${typeInfo.icon} text-[9px]"></i>
                            ${typeInfo.label}
                        </span>
                        <span class="text-[10px] text-gray-500">${formatDate(n.created_at)}</span>
                    `;
                    
                    // Title
                    const title = document.createElement('div');
                    title.className = 'font-semibold text-white text-sm mb-1';
                    title.textContent = n.title;
                    
                    // Message
                    const message = document.createElement('div');
                    message.className = 'text-xs text-gray-400 line-clamp-2 mb-2';
                    message.textContent = n.message;
                    
                    // Actions
                    const actions = document.createElement('div');
                    actions.className = 'flex items-center justify-between';
                    
                    // Mark read button
                    const markBtn = document.createElement('button');
                    markBtn.className = 'text-[10px] text-sky-400 hover:text-sky-300 transition';
                    markBtn.innerHTML = n.is_read == 0 
                        ? '<i class="fas fa-check mr-1"></i>Marquer lu' 
                        : '<i class="fas fa-check-double mr-1"></i>Lu';
                    markBtn.onclick = async function(e){
                        e.stopPropagation();
                        try {
                            await fetch('/shop/lib/notifications/api.php', {
                                method: 'POST',
                                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                                body: 'action=mark_read&id=' + encodeURIComponent(n.id)
                            });
                            updateCount();
                            loadList();
                        } catch(e) {
                            console.error('Erreur mark_read:', e);
                        }
                    };
                    
                    // Link button (if exists)
                    if (n.link) {
                        const linkBtn = document.createElement('a');
                        linkBtn.href = n.link;
                        linkBtn.target = '_blank';
                        linkBtn.className = 'text-[10px] text-rose-400 hover:text-rose-300 transition';
                        linkBtn.innerHTML = '<i class="fas fa-external-link-alt mr-1"></i>Voir';
                        linkBtn.onclick = function(e){ e.stopPropagation(); };
                        actions.appendChild(linkBtn);
                    }
                    
                    actions.appendChild(markBtn);
                    
                    // Assemble
                    div.appendChild(header);
                    div.appendChild(title);
                    div.appendChild(message);
                    div.appendChild(actions);
                    
                    // Click on item to mark as read
                    div.onclick = async function(){
                        if (n.is_read == 0) {
                            try {
                                await fetch('/shop/lib/notifications/api.php', {
                                    method: 'POST',
                                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                                    body: 'action=mark_read&id=' + encodeURIComponent(n.id)
                                });
                                updateCount();
                                loadList();
                            } catch(e) {}
                        }
                        if (n.link) {
                            window.open(n.link, '_blank');
                        }
                    };
                    
                    list.appendChild(div);
                });
            } else {
                empty.classList.remove('hidden');
            }
        } catch(e) {
            console.error('Erreur loadList:', e);
        }
    }

    // Format date to relative time
    function formatDate(dateStr) {
        if (!dateStr) return '';
        const date = new Date(dateStr);
        const now = new Date();
        const diff = Math.floor((now - date) / 1000);
        
        if (diff < 60) return 'À l\'instant';
        if (diff < 3600) return Math.floor(diff / 60) + ' min';
        if (diff < 86400) return Math.floor(diff / 3600) + ' h';
        if (diff < 604800) return Math.floor(diff / 86400) + ' j';
        
        return date.toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit' });
    }

    // Initial load
    updateCount();
    
    // Polling every 15 seconds
    setInterval(updateCount, 15000);
})();
</script>
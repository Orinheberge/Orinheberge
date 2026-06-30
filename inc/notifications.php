<?php
// notifications include: outputs the bell button and polling JS
?>
<div id="notificationRoot" class="relative">
    <button id="notifBtn" class="glass px-3 py-2 rounded-full text-xs flex items-center gap-2 hover:bg-white/5 transition">
        <i class="fas fa-bell"></i>
        <span id="notifBadge" class="ml-1 bg-rose-500 text-white text-[10px] px-2 py-0.5 rounded-full hidden">0</span>
    </button>
    <div id="notifDropdown" class="hidden absolute right-0 mt-2 w-96 bg-[#071018] border border-white/5 rounded-xl shadow-lg z-50 p-3">
        <div class="flex justify-between items-center mb-2">
            <strong>Notifications</strong>
            <button id="markAllRead" class="text-xs text-sky-400 hover:underline">Marquer tout lu</button>
        </div>
        <div id="notifList" class="space-y-2 max-h-64 overflow-auto"></div>
        <div id="notifEmpty" class="text-gray-400 text-sm mt-2">Aucune notification.</div>
    </div>
</div>

<script>
(function(){
    const btn = document.getElementById('notifBtn');
    const dropdown = document.getElementById('notifDropdown');
    const badge = document.getElementById('notifBadge');
    const list = document.getElementById('notifList');
    const empty = document.getElementById('notifEmpty');
    const markAll = document.getElementById('markAllRead');

    if (!btn) return;

    btn.addEventListener('click', function(){
        dropdown.classList.toggle('hidden');
        if (!dropdown.classList.contains('hidden')) loadList();
    });

    if (markAll) markAll.addEventListener('click', async function(){
        await fetch('/shop/lib/notifications/api.php', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'action=mark_all'});
        updateCount(); loadList();
    });

    async function updateCount(){
        try{
            const res = await fetch('/shop/lib/notifications/api.php?action=count');
            const j = await res.json();
            const n = j.unread || 0;
            if (n > 0) { badge.textContent = n; badge.classList.remove('hidden'); } else { badge.classList.add('hidden'); }
        }catch(e){}
    }

    async function loadList(){
        try{
            const res = await fetch('/shop/lib/notifications/api.php?action=list&limit=20');
            const j = await res.json();
            list.innerHTML = '';
            if (j.notifications && j.notifications.length){
                empty.style.display = 'none';
                j.notifications.forEach(function(n){
                    const div = document.createElement('div');
                    div.className = 'p-2 bg-white/2 rounded-lg flex justify-between items-start';
                    const left = document.createElement('div');
                    left.innerHTML = '<div class="font-semibold">'+escapeHtml(n.title)+'</div><div class="text-sm text-gray-300">'+escapeHtml(n.message)+'</div><div class="text-xs text-gray-500 mt-1">'+escapeHtml(n.created_at)+'</div>';
                    const actions = document.createElement('div');
                    const markBtn = document.createElement('button');
                    markBtn.className = 'text-xs text-sky-400 ml-2';
                    markBtn.textContent = n.is_read==0 ? 'Marquer lu' : 'Lu';
                    markBtn.onclick = async function(){ await fetch('/shop/lib/notifications/api.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'action=mark_read&id='+encodeURIComponent(n.id)}); updateCount(); loadList(); };
                    actions.appendChild(markBtn);
                    div.appendChild(left); div.appendChild(actions); list.appendChild(div);
                });
            } else { empty.style.display = 'block'; }
        }catch(e){}
    }

    function escapeHtml(s){ if(!s) return ''; return s.replace(/[&<>"']/g, function(m){ return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]); }); }

    updateCount();
    setInterval(updateCount, 15000);
})();
</script>

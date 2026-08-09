/**
 * OrinHeberge — Chat Communautaire (v2 debug)
 */

'use strict';

const ChatApp = {
    currentChannel: 'general',
    lastMessageId: 0,
    pollInterval: null,
    customEmojis: [],
    isInitialized: false,
    
    // 📍 CHEMINS API (à ajuster si besoin)
    API: {
        SEND: '/community/api/send_message.php',
        GET:  '/community/api/get_messages.php',
        EMOJIS: '/community/api/get_emojis.php'
    },

    standardEmojis: ['😀','😃','😄','😁','😆','😅','🤣','😂','🙂','🙃','😉','😊','😇','🥰','😍','🤩','😘','','😚','','🥲','😋','😛','😜','🤪','😝','🤑','🤗','','','🤔','🤐','','😐','😑','😶','😏','😒','🙄','😬','🤥','😌','😔','😪','🤤','😴','😷','🤒','🤕','','','🤧','🥵','🥶','🥴','','','🤠','🥳','','😎','🤓','','😕','😟','','️','😮','😯','😲','😳','🥺','😦','😧','😨','😰','😥','😢','😭','😱','😖','😣','😞','😓','😩','😫','🥱','😤','😡','😠','🤬','👍','','','🙏','🤝','❤️','🔥','⭐','🎉','🚀','💯','👀','✨','💪','🎮','🎵','📚','🌟','💡','🎁'],

    async init() {
        console.log('[Chat] 🚀 Initialisation...');
        
        // Vérifier que les éléments DOM existent
        const requiredElements = ['messagesContainer', 'messageForm', 'messageInput', 'emojiPicker', 'customEmojis', 'standardEmojis'];
        const missingElements = requiredElements.filter(id => !document.getElementById(id));
        
        if (missingElements.length > 0) {
            console.error('[Chat] ❌ Éléments DOM manquants:', missingElements);
            return;
        }
        
        console.log('[Chat] ✅ Tous les éléments DOM présents');
        
        try {
            await this.loadCustomEmojis();
            await this.loadMessages();
            this.startPolling();
            this.bindEvents();
            this.renderEmojiPicker();
            this.isInitialized = true;
            console.log('[Chat] ✅ Initialisation terminée');
        } catch (error) {
            console.error('[Chat] ❌ Erreur initialisation:', error);
        }
    },

    async loadCustomEmojis() {
        console.log('[Chat] 📥 Chargement emojis custom depuis:', this.API.EMOJIS);
        try {
            const response = await fetch(this.API.EMOJIS);
            console.log('[Chat] 📥 Réponse emojis:', response.status, response.statusText);
            
            if (!response.ok) {
                console.warn('[Chat] ⚠️ Endpoint emojis indisponible (', response.status, ')');
                this.customEmojis = [];
                return;
            }
            
            const data = await response.json();
            console.log('[Chat] ✅', data.emojis?.length || 0, 'emojis custom chargés');
            
            if (data.success) {
                this.customEmojis = data.emojis || [];
            }
        } catch (error) {
            console.warn('[Chat] ⚠️ Erreur chargement emojis (non bloquant):', error.message);
            this.customEmojis = [];
        }
    },

    async loadMessages() {
    const url = `${this.API.GET}?channel=${this.currentChannel}&last_id=${this.lastMessageId}`;
    console.log('[Chat] 📥 Chargement messages:', url);
    
    try {
        const response = await fetch(url);
        console.log('[Chat] 📥 Réponse messages:', response.status);
        
        // 🔍 DEBUG : Récupérer le texte brut AVANT de parser en JSON
        const responseText = await response.text();
        console.log('[Chat] 📥 Contenu brut:', responseText.substring(0, 500));
        
        // Essayer de parser en JSON
        let data;
        try {
            data = JSON.parse(responseText);
        } catch (parseError) {
            console.error('[Chat] ❌ Réponse non-JSON reçue:');
            console.error(responseText);
            
            // Afficher l'erreur dans le chat
            this.showError(`
                <strong>Erreur API</strong><br>
                Le serveur renvoie du HTML au lieu de JSON.<br>
                <small>Vérifie la console (F12) pour voir le contenu.</small>
            `);
            return;
        }
        
        console.log('[Chat] ✅', data.messages?.length || 0, 'messages reçus');
        
        if (data.success && data.messages && data.messages.length > 0) {
            const container = document.getElementById('messagesContainer');
            
            if (this.lastMessageId === 0) {
                container.innerHTML = '';
            }
            
            data.messages.forEach(msg => {
                this.appendMessage(msg);
                this.lastMessageId = Math.max(this.lastMessageId, msg.id);
            });
            
            container.scrollTop = container.scrollHeight;
        } else if (this.lastMessageId === 0) {
            document.getElementById('messagesContainer').innerHTML = `
                <div class="text-center text-gray-500 py-12">
                    <i class="far fa-comments text-4xl mb-3"></i>
                    <p>Aucun message pour le moment.</p>
                    <p class="text-xs mt-2">Soyez le premier à dire bonjour ! 👋</p>
                </div>
            `;
        }
    } catch (error) {
        console.error('[Chat] ❌ Erreur fetch messages:', error);
        this.showError('Erreur de connexion');
    }
},

    appendMessage(msg) {
        const container = document.getElementById('messagesContainer');
        const div = document.createElement('div');
        div.className = 'chat-message';
        
        const avatarUrl = msg.avatar ? `/${msg.avatar}` : 'https://ui-avatars.com/api/?name=' + encodeURIComponent(msg.username) + '&background=0284c7&color=fff';
        const timestamp = new Date(msg.created_at).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
        const messageHtml = this.parseMessage(msg.message);
        
        div.innerHTML = `
            <img src="${avatarUrl}" alt="${this.escapeHtml(msg.username)}" class="chat-avatar" onerror="this.src='https://ui-avatars.com/api/?name=${encodeURIComponent(msg.username)}'">
            <div class="chat-content">
                <div class="chat-header">
                    <span class="chat-username">${this.escapeHtml(msg.username)}</span>
                    <span class="chat-timestamp">${timestamp}</span>
                </div>
                <div class="chat-text">${messageHtml}</div>
            </div>
        `;
        
        container.appendChild(div);
    },

    parseMessage(text) {
        let html = this.escapeHtml(text);
        
        this.customEmojis.forEach(emoji => {
            const regex = new RegExp(emoji.shortcode.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'g');
            html = html.replace(regex, `<img src="${emoji.url}" alt="${emoji.name}" class="custom-emoji" title=":${emoji.name}:">`);
        });
        
        html = html.replace(
            /(https?:\/\/[^\s]+)/g,
            '<a href="$1" target="_blank" rel="noopener noreferrer" class="text-sky-400 hover:underline">$1</a>'
        );
        
        html = html.replace(/\n/g, '<br>');
        
        return html;
    },

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    },

    showError(message) {
        const container = document.getElementById('messagesContainer');
        if (this.lastMessageId === 0) {
            container.innerHTML = `
                <div class="text-center text-red-400 py-8">
                    <i class="fas fa-exclamation-triangle text-2xl mb-2"></i>
                    <p>${message}</p>
                </div>
            `;
        }
    },

    startPolling() {
        console.log('[Chat] 🔄 Polling activé (toutes les 2s)');
        this.pollInterval = setInterval(() => this.loadMessages(), 2000);
    },

    stopPolling() {
        if (this.pollInterval) {
            clearInterval(this.pollInterval);
            this.pollInterval = null;
        }
    },

    bindEvents() {
        // Formulaire d'envoi
        document.getElementById('messageForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            await this.sendMessage();
        });

        // Entrée pour envoyer
        document.getElementById('messageInput').addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.sendMessage();
            }
        });

        // Changement de canal
        document.querySelectorAll('.channel-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                this.switchChannel(e.currentTarget.dataset.channel, e.currentTarget);
            });
        });

        // Emoji picker
        document.getElementById('emojiPickerBtn')?.addEventListener('click', (e) => {
            e.stopPropagation();
            this.toggleEmojiPicker();
        });

        document.getElementById('emojiBtnInline')?.addEventListener('click', (e) => {
            e.stopPropagation();
            this.toggleEmojiPicker();
        });

        // Fermer emoji picker au clic extérieur
        document.addEventListener('click', (e) => {
            const picker = document.getElementById('emojiPicker');
            if (picker && !picker.classList.contains('hidden') &&
                !picker.contains(e.target) &&
                !e.target.closest('#emojiPickerBtn') &&
                !e.target.closest('#emojiBtnInline')) {
                picker.classList.add('hidden');
            }
        });

        // Recherche d'emojis
        document.getElementById('emojiSearch')?.addEventListener('input', (e) => {
            this.filterEmojis(e.target.value);
        });

        console.log('[Chat] ✅ Event listeners attachés');
    },

    async sendMessage() {
        const input = document.getElementById('messageInput');
        const message = input.value.trim();
        
        if (!message) {
            console.log('[Chat] ⚠️ Message vide, ignoré');
            return;
        }
        
        console.log('[Chat] 📤 Envoi message:', message.substring(0, 50) + '...');
        
        try {
            const response = await fetch(this.API.SEND, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    message: message,
                    channel: this.currentChannel
                })
            });
            
            console.log('[Chat] 📤 Réponse send:', response.status);
            
            if (!response.ok) {
                const errorText = await response.text();
                console.error('[Chat] ❌ Erreur HTTP:', response.status, errorText);
                alert('Erreur lors de l\'envoi du message');
                return;
            }
            
            const data = await response.json();
            
            if (data.success) {
                input.value = '';
                this.appendMessage(data.message);
                this.lastMessageId = Math.max(this.lastMessageId, data.message.id);
                
                const container = document.getElementById('messagesContainer');
                container.scrollTop = container.scrollHeight;
                console.log('[Chat] ✅ Message envoyé');
            } else {
                console.error('[Chat] ❌ Erreur API:', data.error);
                alert(data.error || 'Erreur lors de l\'envoi');
            }
        } catch (error) {
            console.error('[Chat] ❌ Erreur fetch send:', error);
            alert('Erreur de connexion');
        }
    },

    switchChannel(channel, btnElement) {
        console.log('[Chat] 🔀 Changement canal:', channel);
        
        this.currentChannel = channel;
        this.lastMessageId = 0;
        
        // UI
        document.querySelectorAll('.channel-btn').forEach(btn => {
            btn.classList.remove('active', 'bg-sky-600/20', 'text-sky-400', 'border', 'border-sky-500/30');
            btn.classList.add('text-gray-400');
        });
        
        if (btnElement) {
            btnElement.classList.add('active', 'bg-sky-600/20', 'text-sky-400', 'border', 'border-sky-500/30');
            btnElement.classList.remove('text-gray-400');
        }
        
        document.getElementById('messagesContainer').innerHTML = `
            <div class="text-center text-gray-500 py-8">
                <i class="fas fa-spinner fa-spin text-2xl mb-2"></i>
                <p>Chargement...</p>
            </div>
        `;
        
        this.loadMessages();
    },

    toggleEmojiPicker() {
        const picker = document.getElementById('emojiPicker');
        picker.classList.toggle('hidden');
    },

    renderEmojiPicker() {
        // Emojis custom
        const customContainer = document.getElementById('customEmojis');
        customContainer.innerHTML = '';
        
        if (this.customEmojis.length === 0) {
            customContainer.innerHTML = '<p class="col-span-6 text-xs text-gray-500 text-center py-2">Aucun emoji custom</p>';
        } else {
            this.customEmojis.forEach(emoji => {
                const div = document.createElement('div');
                div.className = 'emoji-item';
                div.innerHTML = `<img src="${emoji.url}" alt="${emoji.name}" title=":${emoji.name}:">`;
                div.addEventListener('click', () => this.insertEmoji(emoji.shortcode));
                customContainer.appendChild(div);
            });
        }
        
        // Emojis standards
        const standardContainer = document.getElementById('standardEmojis');
        standardContainer.innerHTML = '';
        this.standardEmojis.forEach(emoji => {
            const div = document.createElement('div');
            div.className = 'emoji-item';
            div.textContent = emoji;
            div.addEventListener('click', () => this.insertEmoji(emoji));
            standardContainer.appendChild(div);
        });
    },

    insertEmoji(emoji) {
        const input = document.getElementById('messageInput');
        const start = input.selectionStart;
        const end = input.selectionEnd;
        const text = input.value;
        
        input.value = text.substring(0, start) + emoji + text.substring(end);
        input.focus();
        input.selectionStart = input.selectionEnd = start + emoji.length;
        
        document.getElementById('emojiPicker').classList.add('hidden');
    },

    filterEmojis(query) {
        const customContainer = document.getElementById('customEmojis');
        const items = customContainer.querySelectorAll('.emoji-item');
        
        items.forEach(item => {
            const img = item.querySelector('img');
            const name = img?.alt || '';
            item.style.display = name.toLowerCase().includes(query.toLowerCase()) ? 'flex' : 'none';
        });
    }
};

// Initialisation au chargement du DOM
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => ChatApp.init());
} else {
    ChatApp.init();
}

// Nettoyage quand on quitte la page
window.addEventListener('beforeunload', () => ChatApp.stopPolling());
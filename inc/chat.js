/**
 * OrinHeberge — Chat Communautaire
 * Système de chat en temps réel avec polling + emojis custom
 */

'use strict';

const ChatApp = {
    currentChannel: 'general',
    lastMessageId: 0,
    pollInterval: null,
    customEmojis: [],
    standardEmojis: ['😀', '😃', '😄', '😁', '😆', '😅', '🤣', '😂', '🙂', '🙃', '😉', '😊', '😇', '🥰', '😍', '🤩', '😘', '', '😚', '', '🥲', '😋', '😛', '😜', '🤪', '😝', '🤑', '🤗', '', '', '🤔', '🤐', '', '😐', '😑', '😶', '😏', '😒', '🙄', '😬', '🤥', '😌', '😔', '😪', '🤤', '😴', '😷', '🤒', '🤕', '', '', '🤧', '🥵', '🥶', '🥴', '', '', '🤠', '🥳', '', '😎', '🤓', '', '😕', '😟', '', '️', '😮', '😯', '😲', '😳', '🥺', '😦', '😧', '😨', '😰', '😥', '😢', '😭', '😱', '😖', '😣', '😞', '😓', '😩', '😫', '🥱', '😤', '😡', '😠', '🤬', '👍', '', '', '🙏', '🤝', '❤️', '🔥', '⭐', '🎉', '🚀'],

    init() {
        this.loadCustomEmojis();
        this.loadMessages();
        this.startPolling();
        this.bindEvents();
        this.renderEmojiPicker();
    },

    async loadCustomEmojis() {
        try {
            const response = await fetch('/community/api/get_emojis.php');
            const data = await response.json();
            if (data.success) {
                this.customEmojis = data.emojis;
            }
        } catch (error) {
            console.error('[Chat] Erreur chargement emojis:', error);
        }
    },

    async loadMessages() {
        try {
            const response = await fetch(`/community/api/get_messages.php?channel=${this.currentChannel}&last_id=${this.lastMessageId}`);
            const data = await response.json();
            
            if (data.success && data.messages.length > 0) {
                const container = document.getElementById('messagesContainer');
                
                // Premier chargement : vider le container
                if (this.lastMessageId === 0) {
                    container.innerHTML = '';
                }
                
                data.messages.forEach(msg => {
                    this.appendMessage(msg);
                    this.lastMessageId = Math.max(this.lastMessageId, msg.id);
                });
                
                // Scroll vers le bas
                container.scrollTop = container.scrollHeight;
            }
        } catch (error) {
            console.error('[Chat] Erreur chargement messages:', error);
        }
    },

    appendMessage(msg) {
        const container = document.getElementById('messagesContainer');
        const div = document.createElement('div');
        div.className = 'chat-message';
        
        const avatarUrl = msg.avatar ? `/${msg.avatar}` : '/assets/default-avatar.png';
        const timestamp = new Date(msg.created_at).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
        const messageHtml = this.parseMessage(msg.message);
        
        div.innerHTML = `
            <img src="${avatarUrl}" alt="${msg.username}" class="chat-avatar">
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
        // Échapper HTML
        let html = this.escapeHtml(text);
        
        // Remplacer les shortcodes d'emojis custom :emoji_name:
        this.customEmojis.forEach(emoji => {
            const regex = new RegExp(emoji.shortcode.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'g');
            html = html.replace(regex, `<img src="${emoji.url}" alt="${emoji.name}" class="custom-emoji" title="${emoji.name}">`);
        });
        
        // Convertir les URLs en liens
        html = html.replace(
            /(https?:\/\/[^\s]+)/g,
            '<a href="$1" target="_blank" rel="noopener noreferrer" class="text-sky-400 hover:underline">$1</a>'
        );
        
        // Convertir les sauts de ligne
        html = html.replace(/\n/g, '<br>');
        
        return html;
    },

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    },

    startPolling() {
        // Polling toutes les 2 secondes
        this.pollInterval = setInterval(() => {
            this.loadMessages();
        }, 2000);
    },

    stopPolling() {
        if (this.pollInterval) {
            clearInterval(this.pollInterval);
        }
    },

    bindEvents() {
        // Formulaire d'envoi
        document.getElementById('messageForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            await this.sendMessage();
        });

        // Changement de canal
        document.querySelectorAll('.channel-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                this.switchChannel(e.currentTarget.dataset.channel);
            });
        });

        // Emoji picker
        document.getElementById('emojiPickerBtn').addEventListener('click', () => {
            this.toggleEmojiPicker();
        });

        document.getElementById('emojiBtnInline').addEventListener('click', () => {
            this.toggleEmojiPicker();
        });

        // Fermer emoji picker au clic extérieur
        document.addEventListener('click', (e) => {
            const picker = document.getElementById('emojiPicker');
            if (!picker.contains(e.target) && 
                e.target.id !== 'emojiPickerBtn' && 
                e.target.id !== 'emojiBtnInline' &&
                !e.target.closest('#emojiPickerBtn') &&
                !e.target.closest('#emojiBtnInline')) {
                picker.classList.add('hidden');
            }
        });

        // Recherche d'emojis
        document.getElementById('emojiSearch').addEventListener('input', (e) => {
            this.filterEmojis(e.target.value);
        });
    },

    async sendMessage() {
        const input = document.getElementById('messageInput');
        const message = input.value.trim();
        
        if (!message) return;
        
        try {
            const response = await fetch('/community/api/send_message.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    message: message,
                    channel: this.currentChannel
                })
            });
            
            const data = await response.json();
            
            if (data.success) {
                input.value = '';
                this.appendMessage(data.message);
                this.lastMessageId = Math.max(this.lastMessageId, data.message.id);
                
                const container = document.getElementById('messagesContainer');
                container.scrollTop = container.scrollHeight;
            } else {
                alert(data.error || 'Erreur lors de l\'envoi');
            }
        } catch (error) {
            console.error('[Chat] Erreur envoi:', error);
            alert('Erreur de connexion');
        }
    },

    switchChannel(channel) {
        this.currentChannel = channel;
        this.lastMessageId = 0;
        
        // Mettre à jour UI
        document.querySelectorAll('.channel-btn').forEach(btn => {
            btn.classList.remove('active', 'bg-sky-600/20', 'text-sky-400', 'border', 'border-sky-500/30');
            btn.classList.add('text-gray-400');
        });
        
        const activeBtn = document.querySelector(`[data-channel="${channel}"]`);
        activeBtn.classList.add('active', 'bg-sky-600/20', 'text-sky-400', 'border', 'border-sky-500/30');
        activeBtn.classList.remove('text-gray-400');
        
        // Mettre à jour header
        const channelNames = {
            'general': 'Général',
            'support': 'Support',
            'offtopic': 'Hors sujet'
        };
        document.getElementById('currentChannelName').textContent = channelNames[channel] || channel;
        
        // Recharger messages
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
        this.customEmojis.forEach(emoji => {
            const div = document.createElement('div');
            div.className = 'emoji-item';
            div.innerHTML = `<img src="${emoji.url}" alt="${emoji.name}" title="${emoji.name}">`;
            div.addEventListener('click', () => {
                this.insertEmoji(emoji.shortcode);
            });
            customContainer.appendChild(div);
        });
        
        // Emojis standards
        const standardContainer = document.getElementById('standardEmojis');
        this.standardEmojis.forEach(emoji => {
            const div = document.createElement('div');
            div.className = 'emoji-item';
            div.textContent = emoji;
            div.addEventListener('click', () => {
                this.insertEmoji(emoji);
            });
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
        
        // Fermer le picker
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

// Initialisation
document.addEventListener('DOMContentLoaded', () => {
    ChatApp.init();
});
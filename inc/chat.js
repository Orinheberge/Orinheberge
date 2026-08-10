/**
 * OrinHeberge — Chat Communautaire (v3 complet)
 * Avec : messages temps réel, emojis custom + standards par catégorie, users online
 */

'use strict';

const ChatApp = {
    currentChannel: 'general',
    lastMessageId: 0,
    pollInterval: null,
    onlineInterval: null,
    customEmojis: [],
    isInitialized: false,
    activeEmojiCategory: 'smileys',
    
    // 📍 CHEMINS API
    API: {
        SEND:    '/client/community/api/send_message.php',
        GET:     '/client/community/api/get_messages.php',
        EMOJIS:  '/client/community/api/get_emojis.php',
        ONLINE:  '/client/community/api/get_online.php'
    },

    // ═══════════════════════════════════════════
    // 🎨 TOUS LES EMOJIS PAR CATÉGORIE
    // ═══════════════════════════════════════════
    emojiCategories: {
        smileys: {
            name: 'Smileys & Émotions',
            icon: 'far fa-smile',
            emojis: [
                '😀','😃','😄','😁','😆','😅','🤣','😂','🙂','🙃',
                '😉','😊','😇','🥰','😍','🤩','😘','😗','😚','😙',
                '🥲','😋','😛','😜','🤪','😝','🤑','🤗','🤭','🤫',
                '🤔','🤐','🤨','😐','😑','😶','😏','😒','🙄','😬',
                '🤥','😌','😔','😪','🤤','😴','😷','🤒','🤕','🤢',
                '🤮','🤧','🥵','🥶','🥴','😵','🤯','🤠','🥳','🥸',
                '😎','🤓','🧐','😕','😟','🙁','😮','😯','😲','😳',
                '🥺','😦','😧','😨','😰','😥','😢','😭','😱','😖',
                '😣','😞','😓','😩','😫','🥱','😤','😡','😠','🤬',
                '😈','👿','💀','☠️','💩','🤡','👹','👺','👻','👽',
                '👾','🤖','😺','😸','😹','😻','😼','😽','🙀','😿',
                '😾','🙈','🙉','🙊','💋','💌','💘','💝','💖','💗',
                '💓','💞','💕','💟','❣️','💔','❤️','🧡','💛','💚',
                '💙','💜','🤎','🖤','🤍','💯','💢','💥','💫','💦',
                '💨','🕳️','💣','💬','👁️‍🗨️','🗨️','🗯️','💭','💤'
            ]
        },
        
        gestures: {
            name: 'Personnes & Gestes',
            icon: 'far fa-hand-paper',
            emojis: [
                '👋','🤚','🖐️','✋','🖖','👌','🤌','🤏','✌️','🤞',
                '🤟','🤘','🤙','👈','👉','👆','🖕','👇','☝️','👍',
                '👎','✊','👊','🤛','🤜','👏','🙌','👐','🤲','🤝',
                '🙏','✍️','💅','🤳','💪','🦾','🦿','🦵','🦶','👂',
                '🦻','👃','🧠','🫀','🫁','🦷','🦴','👀','👁️','👅',
                '👄','👶','🧒','👦','👧','🧑','👱','👨','🧔','👩',
                '🧓','👴','👵','🙍','🙎','🙅','🙆','💁','🙋','🧏',
                '🙇','🤦','🤷','👮','🕵️','💂','🥷','👷','🤴','👸',
                '👳','👲','🧕','🤵','👰','🤰','🫃','🫄','🤱','👼',
                '🎅','🤶','🦸','🦹','🧙','🧚','🧛','🧜','🧝','🧞',
                '🧟','💆','💇','🚶','🧍','🧎','🏃','💃','🕺','🕴️',
                '👯','🧖','🧗','🤸','⛹️','🏋️','🚴','🚵','🤼','🤽',
                '🤾','🤺','⛷️','🏂','🏌️','🏇','🏄','🚣','🏊','🤹'
            ]
        },
        
        animals: {
            name: 'Animaux & Nature',
            icon: 'fas fa-paw',
            emojis: [
                '🐶','🐱','🐭','🐹','🐰','🦊','🐻','🐼','🐻‍❄️','🐨',
                '🐯','🦁','🐮','🐷','🐽','🐸','🐵','🙈','🙉','🙊',
                '🐒','🐔','🐧','🐦','🐤','🐣','🐥','🦆','🦅','🦉',
                '🦇','🐺','🐗','🐴','🦄','🐝','🪱','🐛','🦋','🐌',
                '🐞','🐜','🪰','🪲','🪳','🦟','🦗','🕷️','🕸️','🦂',
                '🐢','🐍','🦎','🦖','🦕','🐙','🦑','🦐','🦞','🦀',
                '🐡','🐠','🐟','🐬','🐳','🐋','🦈','🦭','🐊','🐅',
                '🐆','🦓','🦍','🦧','🐘','🦛','🦏','🐪','🐫','🦒',
                '🦘','🦬','🐃','🐂','🐄','🐎','🐖','🐏','🐑','🦙',
                '🐐','🦌','🐕','🐩','🦮','🐕‍🦺','🐈','🐈‍⬛','🪶','🐓',
                '🦃','🦤','🦚','🦜','🦢','🦩','🐇','🦝','🦨','🦡',
                '🦫','🦦','🦥','🐁','🐀','🐿️','🦔','🌵','🎄','🌲',
                '🌳','🌴','🪵','🌱','🌿','☘️','🍀','🎍','🪴','🎋',
                '🍃','🍂','🍁','🍄','🌾','💐','🌷','🌹','🥀','🪷',
                '🪻','🌺','🌸','🌼','🌻','🌞','🌝','🌛','🌜','🌚',
                '🌕','🌖','🌗','🌘','🌑','🌒','🌓','🌔','🌙','🌎',
                '🌍','🌏','🪐','💫','⭐','🌟','✨','⚡','☄️','💥',
                '🔥','🌪️','🌈','☀️','🌤️','⛅','🌥️','☁️','🌦️','🌧️',
                '⛈️','🌩️','🌨️','❄️','☃️','⛄','🌬️','💨','💧','💦',
                '🫧','☔','☂️','🌊','🌫️'
            ]
        },
        
        food: {
            name: 'Nourriture & Boissons',
            icon: 'fas fa-utensils',
            emojis: [
                '🍏','🍎','🍐','🍊','🍋','🍌','🍉','🍇','🍓','🫐',
                '🍈','🍒','🍑','🥭','🍍','🥥','🥝','🍅','🍆','🥑',
                '🥦','🥬','🥒','🌶️','🫑','🌽','🥕','🫒','🧄','🧅',
                '🥔','🍠','🥐','🥯','🍞','🥖','🥨','🧀','🥚','🍳',
                '🧈','🥞','🧇','🥓','🥩','🍗','🍖','🦴','🌭','🍔',
                '🍟','🍕','🫓','🥪','🥙','🧆','🌮','🌯','🫔','🥗',
                '🥘','🫕','🥫','🍝','🍜','🍲','🍛','🍣','🍱','🥟',
                '🦪','🍤','🍙','🍚','🍘','🍥','🥠','🥮','🍢','🍡',
                '🍧','🍨','🍦','🥧','🧁','🍰','🎂','🍮','🍭','🍬',
                '🍫','🍿','🍩','🍪','🌰','🥜','🍯','🥛','🍼','🫖',
                '☕','🍵','🧃','🥤','🧋','🍶','🍺','🍻','🥂','🍷',
                '🥃','🍸','🍹','🧉','🍾','🧊','🥄','🍴','🍽️','🥣',
                '🥡','🥢','🧂'
            ]
        },
        
        activities: {
            name: 'Activités & Voyages',
            icon: 'fas fa-futbol',
            emojis: [
                '⚽','🏀','🏈','⚾','🥎','🎾','🏐','🏉','🥏','🎱',
                '🪀','🏓','🏸','🏒','🏑','🥍','🏏','🪃','🥅','⛳',
                '🪁','🏹','🎣','🤿','🥊','🥋','🎽','🛹','🛼','🛷',
                '⛸️','🥌','🎿','⛷️','🏂','🪂','🏋️','🤼','🤸','🤾',
                '🤺','🏇','🧘','🏄','🏊','🤽','🚣','🧗','🚵','🚴',
                '🏆','🥇','🥈','🥉','🏅','🎖️','🏵️','🎗️','🎫','🎟️',
                '🎪','🎭','🎨','🎬','🎤','🎧','🎼','🎹','🥁','🪘',
                '🎷','🎺','🪗','🎸','🪕','🎻','🎲','♟️','🎯','🎳',
                '🎮','🎰','🧩','🚗','🚕','🚙','🚌','🚎','🏎️','🚓',
                '🚑','🚒','🚐','🛻','🚚','🚛','🚜','🦯','🦽','🦼',
                '🛴','🚲','🛵','🏍️','🛺','🚨','🚔','🚍','🚘','🚖',
                '🚡','🚠','🚟','🚃','🚋','🚞','🚝','🚄','🚅','🚈',
                '🚂','🚆','🚇','🚊','🚉','✈️','🛫','🛬','🛩️','💺',
                '🛰️','🚀','🛸','🚁','🛶','⛵','🚤','🛥️','🛳️','⛴️',
                '🚢','⚓','🪝','⛽','🚧','🚥','🚦','🚏','🗺️','🗿',
                '🗽','🗼','🏰','🏯','🏟️','🎡','🎢','🎠','⛲','⛱️',
                '🏖️','🏝️','🏜️','🌋','⛰️','🏔️','🗻','🏕️','🛖','🏠',
                '🏡','🏘️','🏚️','🏗️','🏭','🏢','🏬','🏣','🏤','🏥',
                '🏦','🏨','🏪','🏫','🏩','💒','🏛️','⛪','🕌','🕍',
                '🛕','🕋','⛩️','🛤️','🛣️','🗾','🎑','🏞️','🌅','🌄',
                '🌠','🎇','🎆','🌇','🌆','🏙️','🌃','🌌','🌉','🌁'
            ]
        },
        
        objects: {
            name: 'Objets',
            icon: 'fas fa-lightbulb',
            emojis: [
                '⌚','📱','📲','💻','⌨️','🖥️','🖨️','🖱️','🖲️','🕹️',
                '🗜️','💽','💾','💿','📀','📼','📷','📸','📹','🎥',
                '📽️','🎞️','📞','☎️','📟','📠','📺','📻','🎙️','🎚️',
                '🎛️','🧭','⏱️','⏲️','⏰','🕰️','⌛','⏳','📡','🔋',
                '🪫','🔌','💡','🔦','🕯️','🪔','🧯','🛢️','💸','💵',
                '💴','💶','💷','🪙','💰','💳','💎','⚖️','🪜','🧰',
                '🪛','🔧','🔨','⚒️','🛠️','⛏️','🪚','🔩','⚙️','🪤',
                '🧱','⛓️','🧲','🔫','💣','🧨','🪓','🔪','🗡️','⚔️',
                '🛡️','🚬','⚰️','🪦','⚱️','🏺','🔮','📿','🧿','🪬',
                '💈','⚗️','🔭','🔬','🕳️','🩹','🩺','🩻','🩼','💊',
                '💉','🩸','🧬','🦠','🧫','🧪','🌡️','🧹','🪠','🧺',
                '🧻','🚽','🚰','🚿','🛁','🛀','🧼','🪥','🪒','🧽',
                '🪣','🧴','🛎️','🔑','🗝️','🚪','🪑','🛋️','🛏️','🛌',
                '🧸','🪆','🖼️','🪞','🪟','🛍️','🛒','🎁','🎈','🎏',
                '🎀','🪄','🪅','🎊','🎉','🎎','🏮','🎐','🧧','✉️',
                '📩','📨','📧','💌','📥','📤','📦','🏷️','🪧','📪',
                '📫','📬','📭','📮','📯','📜','📃','📄','📑','🧾',
                '📊','📈','📉','🗒️','🗓️','📆','📅','🗑️','📇','🗃️',
                '🗳️','🗄️','📋','📁','📂','🗂️','🗞️','📰','📓','📔',
                '📒','📕','📗','📘','📙','📚','📖','🔖','🧷','🔗',
                '📎','🖇️','📐','📏','🧮','📌','📍','✂️','🖊️','🖋️',
                '✒️','🖌️','🖍️','📝','✏️','🔍','🔎','🔏','🔐','🔒',
                '🔓'
            ]
        },
        
        symbols: {
            name: 'Symboles',
            icon: 'fas fa-heart',
            emojis: [
                '❤️','🧡','💛','💚','💙','💜','🖤','🤍','🤎','💔',
                '❣️','💕','💞','💓','💗','💖','💘','💝','💟','☮️',
                '✝️','☪️','🕉️','☸️','✡️','🔯','🕎','☯️','☦️','🛐',
                '⛎','♈','♉','♊','♋','♌','♍','♎','♏','♐',
                '♑','♒','♓','🆔','⚛️','🉑','☢️','☣️','📴','📳',
                '🈶','🈚','🈸','🈺','🈷️','✴️','🆚','💮','🉐','㊙️',
                '㊗️','🈴','🈵','🈹','🈲','🅰️','🅱️','🆎','🆑','🅾️',
                '🆘','❌','⭕','🛑','⛔','📛','🚫','💯','💢','♨️',
                '🚷','🚯','🚳','🚱','🔞','📵','🚭','❗','❕','❓',
                '❔','‼️','⁉️','🔅','🔆','〽️','⚠️','🚸','🔱','⚜️',
                '🔰','♻️','✅','🈯','💹','❇️','✳️','❎','🌐','💠',
                'Ⓜ️','🌀','💤','🏧','🚾','♿','🅿️','🛗','🈳','🈂️',
                '🛂','🛃','🛄','🛅','🚹','🚺','🚼','⚧️','🚻','🚮',
                '🎦','📶','🈁','🔣','ℹ️','🔤','🔡','🔠','🆖','🆗',
                '🆙','🆒','🆕','🆓','0️⃣','1️⃣','2️⃣','3️⃣','4️⃣','5️⃣',
                '6️⃣','7️⃣','8️⃣','9️⃣','🔟','🔢','#️⃣','*️⃣','⏏️','▶️',
                '⏸️','⏯️','⏹️','⏺️','⏭️','⏮️','⏩','⏪','⏫','⏬',
                '◀️','🔼','🔽','➡️','⬅️','⬆️','⬇️','↗️','↘️','↙️',
                '↖️','↕️','↔️','↪️','↩️','⤴️','⤵️','🔀','🔁','🔂',
                '🔄','🔃','🎵','🎶','➕','➖','➗','✖️','🟰','♾️',
                '💲','💱','™️','©️','®️','🔚','🔙','🔛','🔝','🔜',
                '〰️','➰','➿','✔️','☑️','🔘','🔴','🟠','🟡','🟢',
                '🔵','🟣','⚫','⚪','🟤','🔺','🔻','🔸','🔹','🔶',
                '🔷','🔳','🔲','▪️','▫️','◾','◽','◼️','◻️','🟥',
                '🟧','🟨','🟩','🟦','🟪','⬛','⬜','🟫','🔈','🔇',
                '🔉','🔊','🔔','🔕','📣','📢','🃏','🀄','🎴'
            ]
        },
        
        flags: {
            name: 'Drapeaux',
            icon: 'fas fa-flag',
            emojis: [
                '🏁','🚩','🎌','🏴','🏳️','🏳️‍🌈','🏳️‍⚧️','🏴‍☠️','🇫🇷','🇧🇪',
                '🇨🇭','🇨🇦','🇬🇧','🇺🇸','🇩🇪','🇪🇸','🇮🇹','🇵🇹','🇳🇱','🇷🇺',
                '🇯🇵','🇰🇷','🇨🇳','🇮🇳','🇧🇷','🇲🇽','🇦🇷','🇦🇺','🇳🇿','🇿🇦',
                '🇲🇦','🇹🇳','🇩🇿','🇸🇳','🇨🇮','🇨🇲','🇳🇬','🇪🇬','🇹🇷','🇸🇦',
                '🇦🇪','🇮🇱','🇮🇷','🇵🇰','🇧🇩','🇹🇭','🇻🇳','🇮🇩','🇵🇭','🇲🇾',
                '🇸🇬','🇵🇱','🇸🇪','🇳🇴','🇩🇰','🇫🇮','🇮🇪','🇬🇷','🇷🇴','🇺🇦',
                '🇨🇿','🇭🇺','🇦🇹','🇧🇬','🇭🇷','🇷🇸','🇸🇰','🇸🇮','🇱🇺','🇲🇨',
                '🇪🇺','🇺🇳','🏴󠁧󠁢󠁥󠁮󠁧󠁿','🏴󠁧󠁢󠁳󠁣󠁴󠁿','🏴󠁧󠁢󠁷󠁬󠁳󠁿'
            ]
        }
    },

    // ═══════════════════════════════════════════
    // 🚀 INITIALISATION
    // ═══════════════════════════════════════════
    async init() {
        console.log('[Chat] 🚀 Initialisation...');
        
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
            this.startOnlinePolling();
            this.bindEvents();
            this.renderEmojiPicker();
            this.isInitialized = true;
            console.log('[Chat] ✅ Initialisation terminée');
        } catch (error) {
            console.error('[Chat] ❌ Erreur initialisation:', error);
        }
    },

    // ═══════════════════════════════════════════
    // 👥 UTILISATEURS EN LIGNE
    // ═══════════════════════════════════════════
    async loadOnlineUsers() {
        try {
            const response = await fetch(this.API.ONLINE);
            
            if (!response.ok) {
                console.warn('[Chat] ⚠️ Endpoint online indisponible:', response.status);
                return;
            }
            
            const data = await response.json();
            
            if (data.success) {
                this.renderOnlineUsers(data.users);
                console.log('[Chat] 👥', data.count, 'utilisateurs en ligne');
            }
        } catch (error) {
            console.warn('[Chat] ⚠️ Erreur chargement online:', error.message);
        }
    },

    renderOnlineUsers(users) {
        const container = document.getElementById('onlineUsers');
        if (!container) return;
        
        if (!users || users.length === 0) {
            container.innerHTML = '<p class="text-xs text-gray-500 italic">Personne en ligne</p>';
            return;
        }
        
        container.innerHTML = users.map(user => {
            const avatarUrl = user.avatar 
                ? `/${user.avatar}` 
                : `https://ui-avatars.com/api/?name=${encodeURIComponent(user.username)}&background=0284c7&color=fff&size=32`;
            
            const secondsAgo = parseInt(user.seconds_ago);
            let statusColor = 'bg-green-500';
            let statusText = 'En ligne';
            
            if (secondsAgo > 60) {
                statusColor = 'bg-yellow-500';
                statusText = 'Inactif';
            }
            
            const providerIcon = user.oauth_provider === 'discord' 
                ? '<i class="fab fa-discord text-[#5865F2] text-xs"></i>'
                : user.oauth_provider === 'google'
                ? '<i class="fab fa-google text-xs"></i>'
                : '';
            
            return `
                <div class="flex items-center gap-2 p-2 rounded-lg hover:bg-white/5 transition cursor-pointer group" title="${statusText}">
                    <div class="relative shrink-0">
                        <img src="${avatarUrl}" alt="${this.escapeHtml(user.username)}" 
                             class="w-8 h-8 rounded-full object-cover border border-white/10"
                             onerror="this.src='https://ui-avatars.com/api/?name=${encodeURIComponent(user.username)}&background=0284c7&color=fff&size=32'">
                        <span class="absolute -bottom-0.5 -right-0.5 w-3 h-3 ${statusColor} rounded-full border-2 border-[#0b0f17]"></span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="text-sm font-medium text-gray-200 truncate flex items-center gap-1">
                            ${this.escapeHtml(user.username)}
                            ${providerIcon}
                        </div>
                        <div class="text-xs text-gray-500 truncate">${this.formatTimeAgo(secondsAgo)}</div>
                    </div>
                </div>
            `;
        }).join('');
    },

    formatTimeAgo(seconds) {
        if (seconds < 10) return "À l'instant";
        if (seconds < 60) return `Il y a ${seconds}s`;
        const minutes = Math.floor(seconds / 60);
        if (minutes < 60) return `Il y a ${minutes}min`;
        const hours = Math.floor(minutes / 60);
        return `Il y a ${hours}h`;
    },

    startOnlinePolling() {
        console.log('[Chat] 👥 Polling utilisateurs en ligne activé (toutes les 30s)');
        this.loadOnlineUsers();
        this.onlineInterval = setInterval(() => this.loadOnlineUsers(), 30000);
    },

    stopOnlinePolling() {
        if (this.onlineInterval) {
            clearInterval(this.onlineInterval);
            this.onlineInterval = null;
        }
    },

    // ═══════════════════════════════════════════
    // 😎 EMOJIS CUSTOM
    // ═══════════════════════════════════════════
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

    // ═══════════════════════════════════════════
    // 💬 MESSAGES
    // ═══════════════════════════════════════════
    async loadMessages() {
        const url = `${this.API.GET}?channel=${this.currentChannel}&last_id=${this.lastMessageId}`;
        console.log('[Chat] 📥 Chargement messages:', url);
        
        try {
            const response = await fetch(url);
            console.log('[Chat] 📥 Réponse messages:', response.status);
            
            const responseText = await response.text();
            console.log('[Chat] 📥 Contenu brut:', responseText.substring(0, 500));
            
            let data;
            try {
                data = JSON.parse(responseText);
            } catch (parseError) {
                console.error('[Chat] ❌ Réponse non-JSON reçue:');
                console.error(responseText);
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

    // ═══════════════════════════════════════════
    // 🎯 EVENT LISTENERS
    // ═══════════════════════════════════════════
    bindEvents() {
        document.getElementById('messageForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            await this.sendMessage();
        });

        document.getElementById('messageInput').addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.sendMessage();
            }
        });

        document.querySelectorAll('.channel-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                this.switchChannel(e.currentTarget.dataset.channel, e.currentTarget);
            });
        });

        document.getElementById('emojiPickerBtn')?.addEventListener('click', (e) => {
            e.stopPropagation();
            this.toggleEmojiPicker();
        });

        document.getElementById('emojiBtnInline')?.addEventListener('click', (e) => {
            e.stopPropagation();
            this.toggleEmojiPicker();
        });

        document.addEventListener('click', (e) => {
            const picker = document.getElementById('emojiPicker');
            if (picker && !picker.classList.contains('hidden') &&
                !picker.contains(e.target) &&
                !e.target.closest('#emojiPickerBtn') &&
                !e.target.closest('#emojiBtnInline')) {
                picker.classList.add('hidden');
            }
        });

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

    // ═══════════════════════════════════════════
    // 🎨 EMOJI PICKER (AVEC ONGLETS)
    // ═══════════════════════════════════════════
    renderEmojiPicker() {
        // 1. Emojis custom
        const customContainer = document.getElementById('customEmojis');
        customContainer.innerHTML = '';
        
        if (this.customEmojis.length === 0) {
            customContainer.innerHTML = '<p class="col-span-8 text-xs text-gray-500 text-center py-2">Aucun emoji custom</p>';
            // Cacher la section si vide
            const customSection = document.getElementById('customEmojisSection');
            if (customSection) customSection.style.display = 'none';
        } else {
            this.customEmojis.forEach(emoji => {
                const div = document.createElement('div');
                div.className = 'emoji-item';
                div.innerHTML = `<img src="${emoji.url}" alt="${emoji.name}" title=":${emoji.name}:">`;
                div.addEventListener('click', () => this.insertEmoji(emoji.shortcode));
                customContainer.appendChild(div);
            });
        }
        
        // 2. Onglets de catégories
        this.renderCategoryTabs();
        
        // 3. Afficher catégorie active
        this.renderCategoryEmojis(this.activeEmojiCategory);
    },

    renderCategoryTabs() {
        const tabsContainer = document.getElementById('emojiCategoryTabs');
        if (!tabsContainer) return;
        
        tabsContainer.innerHTML = '';
        
        Object.keys(this.emojiCategories).forEach(key => {
            const cat = this.emojiCategories[key];
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = `emoji-tab px-2 py-1.5 rounded-lg text-xs transition whitespace-nowrap flex-shrink-0 ${
                key === this.activeEmojiCategory 
                    ? 'bg-sky-500/20 text-sky-400 border border-sky-500/30' 
                    : 'text-gray-400 hover:bg-white/5 border border-transparent'
            }`;
            btn.innerHTML = `<i class="${cat.icon} mr-1"></i><span class="hidden sm:inline">${cat.name.split(' ')[0]}</span>`;
            btn.title = cat.name;
            btn.addEventListener('click', () => {
                this.activeEmojiCategory = key;
                this.renderCategoryTabs();
                this.renderCategoryEmojis(key);
            });
            tabsContainer.appendChild(btn);
        });
    },

    renderCategoryEmojis(categoryKey) {
        const standardContainer = document.getElementById('standardEmojis');
        if (!standardContainer) return;
        
        standardContainer.innerHTML = '';
        const category = this.emojiCategories[categoryKey];
        
        if (!category) return;
        
        category.emojis.forEach(emoji => {
            const div = document.createElement('div');
            div.className = 'emoji-item';
            div.textContent = emoji;
            div.title = emoji;
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
        const standardContainer = document.getElementById('standardEmojis');
        
        // Filtrer customs
        const customItems = customContainer.querySelectorAll('.emoji-item');
        customItems.forEach(item => {
            const img = item.querySelector('img');
            const name = img?.alt || '';
            item.style.display = name.toLowerCase().includes(query.toLowerCase()) ? 'flex' : 'none';
        });
        
        // Reset recherche
        if (query.trim() === '') {
            this.renderCategoryEmojis(this.activeEmojiCategory);
            return;
        }
        
        // Filtrer toutes catégories
        standardContainer.innerHTML = '';
        const queryLower = query.toLowerCase();
        
        Object.values(this.emojiCategories).forEach(cat => {
            cat.emojis.forEach(emoji => {
                if (emoji.toLowerCase().includes(queryLower)) {
                    const div = document.createElement('div');
                    div.className = 'emoji-item';
                    div.textContent = emoji;
                    div.addEventListener('click', () => this.insertEmoji(emoji));
                    standardContainer.appendChild(div);
                }
            });
        });
    }
};

// ═══════════════════════════════════════════
// 🚀 DÉMARRAGE
// ═══════════════════════════════════════════
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => ChatApp.init());
} else {
    ChatApp.init();
}

window.addEventListener('beforeunload', () => {
    ChatApp.stopPolling();
    ChatApp.stopOnlinePolling();
});
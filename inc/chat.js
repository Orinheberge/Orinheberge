/**
 * ═══════════════════════════════════════════════════════════
 * OrinHeberge — Chat Communautaire (v4 finale)
 * ═══════════════════════════════════════════════════════════
 * Fonctionnalités :
 * - Messages temps réel (polling 2s)
 * - Emojis custom + standards (1000+ en 8 catégories)
 * - Réactions complètes (tous emojis)
 * - Messages épinglés (pin/unpin + modal)
 * - Suppression messages (auteur + admin)
 * - Utilisateurs en ligne (polling 30s)
 * - Multi-canaux
 * - Anti-spam (1 msg/seconde)
 * ═══════════════════════════════════════════════════════════
 */

'use strict';

const ChatApp = {
    // ═══════════════════════════════════════════
    // 📦 ÉTAT GLOBAL
    // ═══════════════════════════════════════════
    currentChannel: 'general',
    lastMessageId: 0,
    pollInterval: null,
    onlineInterval: null,
    customEmojis: [],
    isInitialized: false,
    activeEmojiCategory: 'smileys',
    
    // ═══════════════════════════════════════════
    // 📍 CHEMINS API
    // ═══════════════════════════════════════════
    API: {
        SEND:    '/client/community/api/send_message.php',
        GET:     '/client/community/api/get_messages.php',
        EMOJIS:  '/client/community/api/get_emojis.php',
        ONLINE:  '/client/community/api/get_online.php',
        DELETE:  '/client/community/api/delete_message.php',
        REACT:   '/client/community/api/react_message.php',
        PIN:     '/client/community/api/pin_message.php',
        PINNED:  '/client/community/api/get_pinned_messages.php'
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
        
        const requiredElements = ['messagesContainer', 'messageForm', 'messageInput'];
        const missingElements = requiredElements.filter(id => !document.getElementById(id));
        
        if (missingElements.length > 0) {
            console.error('[Chat] ❌ Éléments DOM manquants:', missingElements);
            return;
        }
        
        console.log('[Chat] ✅ Tous les éléments DOM présents');
        
        try {
            await this.loadCustomEmojis();
            await this.loadMessages();
            await this.loadPinnedMessages();
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
                
                // Mettre à jour le compteur
                const countEl = document.getElementById('onlineCount');
                if (countEl) countEl.textContent = data.count || 0;
                
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
            
            const secondsAgo = parseInt(user.seconds_ago || 0);
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
        console.log('[Chat] 👥 Polling utilisateurs en ligne (toutes les 30s)');
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
        console.log('[Chat] 📥 Chargement emojis custom...');
        try {
            const response = await fetch(this.API.EMOJIS);
            
            if (!response.ok) {
                console.warn('[Chat] ⚠️ Endpoint emojis indisponible:', response.status);
                this.customEmojis = [];
                return;
            }
            
            const data = await response.json();
            console.log('[Chat] ✅', data.emojis?.length || 0, 'emojis custom chargés');
            
            if (data.success) {
                this.customEmojis = data.emojis || [];
            }
        } catch (error) {
            console.warn('[Chat] ⚠️ Erreur chargement emojis:', error.message);
            this.customEmojis = [];
        }
    },

    // ═══════════════════════════════════════════
    // 💬 MESSAGES
    // ═══════════════════════════════════════════
    async loadMessages() {
        const url = `${this.API.GET}?channel=${this.currentChannel}&last_id=${this.lastMessageId}`;
        
        try {
            const response = await fetch(url);
            const responseText = await response.text();
            
            let data;
            try {
                data = JSON.parse(responseText);
            } catch (parseError) {
                console.error('[Chat] ❌ Réponse non-JSON');
                this.showError('Erreur serveur (réponse non-JSON)');
                return;
            }
            
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
        }
    },

    appendMessage(msg) {
        const container = document.getElementById('messagesContainer');
        const div = document.createElement('div');
        div.className = 'chat-message group flex gap-3' + (msg.is_pinned ? ' pinned-message' : '');
        div.dataset.messageId = msg.id;
        
        const avatarUrl = msg.avatar ? `/${msg.avatar}` : 'https://ui-avatars.com/api/?name=' + encodeURIComponent(msg.username) + '&background=0284c7&color=fff';
        const timestamp = new Date(msg.created_at).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
        const messageHtml = this.parseMessage(msg.message);
        
        const canDelete = msg.is_own || msg.is_admin;
        const canPin = msg.is_own || msg.is_admin;
        
        div.innerHTML = `
            <img src="${avatarUrl}" alt="${this.escapeHtml(msg.username)}" class="chat-avatar" onerror="this.src='https://ui-avatars.com/api/?name=${encodeURIComponent(msg.username)}'">
            <div class="chat-content flex-1 min-w-0">
                <div class="chat-header flex items-center gap-2 mb-1">
                    ${msg.is_pinned ? '<i class="fas fa-thumbtack text-amber-400 text-xs"></i>' : ''}
                    <span class="chat-username font-semibold text-sky-400">${this.escapeHtml(msg.username)}</span>
                    <span class="chat-timestamp text-xs text-gray-500">${timestamp}</span>
                    
                    <div class="message-actions ml-auto opacity-0 group-hover:opacity-100 transition flex items-center gap-1">
                        <button type="button" class="react-btn text-gray-500 hover:text-sky-400 transition p-1 rounded hover:bg-white/10" title="Réagir">
                            <i class="far fa-smile text-sm"></i>
                        </button>
                        ${canPin ? `
                            <button type="button" class="pin-btn text-gray-500 hover:text-amber-400 transition p-1 rounded hover:bg-white/10" title="${msg.is_pinned ? 'Désépingler' : 'Épingler'}">
                                <i class="fas fa-thumbtack text-sm"></i>
                            </button>
                        ` : ''}
                        ${canDelete ? `
                            <button type="button" class="delete-btn text-gray-500 hover:text-red-400 transition p-1 rounded hover:bg-white/10" title="Supprimer">
                                <i class="fas fa-trash text-sm"></i>
                            </button>
                        ` : ''}
                    </div>
                </div>
                <div class="chat-text text-gray-200 text-sm break-words">${messageHtml}</div>
                <div class="message-reactions flex flex-wrap gap-1 mt-2"></div>
            </div>
        `;
        
        container.appendChild(div);
        
        // Render réactions
        if (msg.reactions && msg.reactions.length > 0) {
            this.updateMessageReactions(msg.id, msg.reactions);
        } else {
            const reactionsContainer = div.querySelector('.message-reactions');
            const addBtn = document.createElement('button');
            addBtn.type = 'button';
            addBtn.className = 'reaction-badge opacity-0 group-hover:opacity-100 px-2 py-0.5 rounded-full text-xs bg-white/5 border border-white/10 hover:bg-white/10 transition';
            addBtn.innerHTML = '<i class="far fa-smile text-gray-400"></i>';
            addBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                this.showReactionPicker(msg.id, addBtn);
            });
            reactionsContainer.appendChild(addBtn);
        }
        
        // Event listeners
        div.querySelector('.react-btn')?.addEventListener('click', (e) => {
            e.stopPropagation();
            this.showReactionPicker(msg.id, e.currentTarget);
        });
        
        div.querySelector('.pin-btn')?.addEventListener('click', (e) => {
            e.stopPropagation();
            this.togglePin(msg.id);
        });
        
        div.querySelector('.delete-btn')?.addEventListener('click', (e) => {
            e.stopPropagation();
            this.deleteMessage(msg.id);
        });
    },

    parseMessage(text) {
        let html = this.escapeHtml(text);
        
        // Emojis custom
        this.customEmojis.forEach(emoji => {
            const regex = new RegExp(emoji.shortcode.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'g');
            html = html.replace(regex, `<img src="${emoji.url}" alt="${emoji.name}" class="custom-emoji" title=":${emoji.name}:">`);
        });
        
        // URLs
        html = html.replace(
            /(https?:\/\/[^\s]+)/g,
            '<a href="$1" target="_blank" rel="noopener noreferrer" class="text-sky-400 hover:underline">$1</a>'
        );
        
        // Sauts de ligne
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
        console.log('[Chat] 🔄 Polling messages (toutes les 2s)');
        this.pollInterval = setInterval(() => this.loadMessages(), 2000);
    },

    stopPolling() {
        if (this.pollInterval) {
            clearInterval(this.pollInterval);
            this.pollInterval = null;
        }
    },

    // ═══════════════════════════════════════════
    // 🗑️ SUPPRIMER MESSAGE
    // ═══════════════════════════════════════════
    async deleteMessage(messageId) {
        if (!confirm('Supprimer ce message ? Cette action est irréversible.')) return;
        
        try {
            const response = await fetch(this.API.DELETE, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ message_id: messageId })
            });
            
            const data = await response.json();
            
            if (data.success) {
                const msgEl = document.querySelector(`[data-message-id="${messageId}"]`);
                if (msgEl) {
                    msgEl.style.transition = 'all 0.3s';
                    msgEl.style.opacity = '0';
                    msgEl.style.transform = 'translateX(-20px)';
                    setTimeout(() => msgEl.remove(), 300);
                }
                console.log('[Chat] ✅ Message supprimé');
            } else {
                alert('Erreur: ' + (data.error || 'Inconnue'));
            }
        } catch (error) {
            console.error('[Chat] ❌ Erreur suppression:', error);
            alert('Erreur de connexion');
        }
    },

    // ═══════════════════════════════════════════
    // 😊 RÉACTIONS
    // ═══════════════════════════════════════════
    async toggleReaction(messageId, emoji) {
        try {
            const response = await fetch(this.API.REACT, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ message_id: messageId, emoji: emoji })
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.updateMessageReactions(messageId, data.reactions);
                console.log('[Chat] ✅ Réaction:', data.action);
            } else {
                console.error('[Chat] ❌ Erreur réaction:', data.error);
            }
        } catch (error) {
            console.error('[Chat] ❌ Erreur réaction:', error);
        }
    },

    updateMessageReactions(messageId, reactions) {
        const msgEl = document.querySelector(`[data-message-id="${messageId}"]`);
        if (!msgEl) return;
        
        let reactionsContainer = msgEl.querySelector('.message-reactions');
        if (!reactionsContainer) {
            reactionsContainer = document.createElement('div');
            reactionsContainer.className = 'message-reactions flex flex-wrap gap-1 mt-2';
            msgEl.querySelector('.chat-content').appendChild(reactionsContainer);
        }
        
        reactionsContainer.innerHTML = '';
        
        reactions.forEach(reaction => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'reaction-badge px-2 py-0.5 rounded-full text-xs bg-sky-500/10 border border-sky-500/30 hover:bg-sky-500/20 transition flex items-center gap-1';
            btn.title = reaction.users;
            
            // Afficher l'emoji (custom ou standard)
            let emojiDisplay = reaction.emoji;
            if (reaction.emoji.startsWith(':') && reaction.emoji.endsWith(':')) {
                const customEmoji = this.customEmojis.find(e => e.shortcode === reaction.emoji);
                if (customEmoji) {
                    emojiDisplay = `<img src="${customEmoji.url}" alt="${customEmoji.name}" class="w-4 h-4 inline-block">`;
                }
            }
            
            btn.innerHTML = `<span class="text-base">${emojiDisplay}</span><span class="text-gray-300">${reaction.count}</span>`;
            btn.addEventListener('click', () => this.toggleReaction(messageId, reaction.emoji));
            reactionsContainer.appendChild(btn);
        });
        
        // Bouton "+" pour ajouter
        const addBtn = document.createElement('button');
        addBtn.type = 'button';
        addBtn.className = 'reaction-badge px-2 py-0.5 rounded-full text-xs bg-white/5 border border-white/10 hover:bg-white/10 transition';
        addBtn.innerHTML = '<i class="far fa-smile text-gray-400"></i>';
        addBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            this.showReactionPicker(messageId, addBtn);
        });
        reactionsContainer.appendChild(addBtn);
    },

    // ═══════════════════════════════════════════
    // 🎯 PICKER DE RÉACTION (TOUS LES EMOJIS)
    // ═══════════════════════════════════════════
    showReactionPicker(messageId, anchorEl) {
        // Fermer s'il existe déjà
        let existing = document.getElementById('reactionPickerModal');
        if (existing) existing.remove();
        
        const modal = document.createElement('div');
        modal.id = 'reactionPickerModal';
        modal.className = 'fixed inset-0 bg-black/40 backdrop-blur-sm z-[90] flex items-center justify-center p-4';
        modal.innerHTML = `
            <div class="reaction-picker-container bg-[#11151d] border border-white/10 rounded-2xl shadow-2xl w-full max-w-md max-h-[500px] flex flex-col overflow-hidden">
                
                <!-- Header recherche -->
                <div class="p-3 border-b border-white/10 shrink-0">
                    <div class="relative">
                        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 text-sm"></i>
                        <input type="text" id="reactionSearch" 
                               placeholder="Rechercher un emoji..." 
                               class="w-full bg-white/5 border border-white/10 rounded-lg pl-9 pr-3 py-2 text-sm text-white placeholder-gray-500 focus:outline-none focus:border-sky-500/50"
                               autocomplete="off">
                    </div>
                </div>
                
                <!-- Section Custom -->
                ${this.customEmojis.length > 0 ? `
                    <div class="p-3 border-b border-white/10 shrink-0" id="reactionCustomSection">
                        <h4 class="text-xs font-bold text-gray-400 uppercase mb-2 flex items-center gap-1">
                            <i class="fas fa-star text-amber-400"></i>
                            OrinHeberge
                        </h4>
                        <div class="grid grid-cols-8 gap-1 max-h-24 overflow-y-auto custom-scrollbar pr-1" id="reactionCustomGrid">
                            ${this.customEmojis.map(emoji => `
                                <button type="button" 
                                        class="reaction-emoji-btn w-9 h-9 flex items-center justify-center rounded hover:bg-white/10 transition hover:scale-125"
                                        data-emoji="${this.escapeHtml(emoji.shortcode)}"
                                        title=":${this.escapeHtml(emoji.name)}:">
                                    <img src="${emoji.url}" alt="${this.escapeHtml(emoji.name)}" class="w-6 h-6 object-contain">
                                </button>
                            `).join('')}
                        </div>
                    </div>
                ` : ''}
                
                <!-- Onglets catégories -->
                <div class="flex gap-1 overflow-x-auto custom-scrollbar px-3 pt-3 pb-2 border-b border-white/10 shrink-0" id="reactionCategoryTabs">
                </div>
                
                <!-- Grille d'emojis -->
                <div class="flex-1 overflow-y-auto custom-scrollbar p-3">
                    <div id="reactionEmojiGrid" class="grid grid-cols-8 gap-1">
                    </div>
                </div>
                
                <!-- Footer -->
                <div class="p-2 border-t border-white/10 bg-white/[0.02] shrink-0">
                    <p class="text-xs text-gray-500 text-center">
                        <i class="fas fa-info-circle mr-1"></i>
                        Cliquez sur un emoji pour réagir
                    </p>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        let activeCategory = 'smileys';
        
        const renderTabs = () => {
            const tabsContainer = modal.querySelector('#reactionCategoryTabs');
            tabsContainer.innerHTML = '';
            
            Object.keys(this.emojiCategories).forEach(key => {
                const cat = this.emojiCategories[key];
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = `reaction-tab px-2 py-1.5 rounded-lg text-xs transition whitespace-nowrap flex-shrink-0 ${
                    key === activeCategory 
                        ? 'bg-sky-500/20 text-sky-400 border border-sky-500/30' 
                        : 'text-gray-400 hover:bg-white/5 border border-transparent'
                }`;
                btn.innerHTML = `<i class="${cat.icon}"></i>`;
                btn.title = cat.name;
                btn.addEventListener('click', () => {
                    activeCategory = key;
                    renderTabs();
                    renderGrid();
                });
                tabsContainer.appendChild(btn);
            });
        };
        
        const renderGrid = (filter = '') => {
            const grid = modal.querySelector('#reactionEmojiGrid');
            grid.innerHTML = '';
            
            const filterLower = filter.toLowerCase();
            
            if (filter) {
                let found = 0;
                Object.values(this.emojiCategories).forEach(cat => {
                    cat.emojis.forEach(emoji => {
                        if (emoji.toLowerCase().includes(filterLower) && found < 100) {
                            const btn = document.createElement('button');
                            btn.type = 'button';
                            btn.className = 'reaction-emoji-btn w-9 h-9 flex items-center justify-center rounded hover:bg-white/10 transition hover:scale-125 text-xl';
                            btn.textContent = emoji;
                            btn.title = emoji;
                            btn.addEventListener('click', () => {
                                this.toggleReaction(messageId, emoji);
                                modal.remove();
                            });
                            grid.appendChild(btn);
                            found++;
                        }
                    });
                });
                
                const customGrid = modal.querySelector('#reactionCustomGrid');
                if (customGrid) {
                    customGrid.querySelectorAll('.reaction-emoji-btn').forEach(btn => {
                        const title = btn.title || '';
                        btn.style.display = title.toLowerCase().includes(filterLower) ? 'flex' : 'none';
                    });
                }
                
                if (found === 0) {
                    grid.innerHTML = '<p class="col-span-8 text-center text-gray-500 text-sm py-4">Aucun emoji trouvé</p>';
                }
            } else {
                const category = this.emojiCategories[activeCategory];
                if (category) {
                    category.emojis.forEach(emoji => {
                        const btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'reaction-emoji-btn w-9 h-9 flex items-center justify-center rounded hover:bg-white/10 transition hover:scale-125 text-xl';
                        btn.textContent = emoji;
                        btn.title = emoji;
                        btn.addEventListener('click', () => {
                            this.toggleReaction(messageId, emoji);
                            modal.remove();
                        });
                        grid.appendChild(btn);
                    });
                }
                
                const customGrid = modal.querySelector('#reactionCustomGrid');
                if (customGrid) {
                    customGrid.querySelectorAll('.reaction-emoji-btn').forEach(btn => {
                        btn.style.display = 'flex';
                    });
                }
            }
        };
        
        renderTabs();
        renderGrid();
        
        // Event listeners customs
        modal.querySelectorAll('#reactionCustomGrid .reaction-emoji-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const shortcode = btn.dataset.emoji;
                this.toggleReaction(messageId, shortcode);
                modal.remove();
            });
        });
        
        // Recherche
        const searchInput = modal.querySelector('#reactionSearch');
        searchInput.addEventListener('input', (e) => {
            renderGrid(e.target.value);
        });
        
        setTimeout(() => searchInput.focus(), 100);
        
        // Fermer au clic extérieur
        modal.addEventListener('click', (e) => {
            if (e.target === modal) modal.remove();
        });
        
        // Fermer avec Échap
        const escHandler = (e) => {
            if (e.key === 'Escape') {
                modal.remove();
                document.removeEventListener('keydown', escHandler);
            }
        };
        document.addEventListener('keydown', escHandler);
    },

    // ═══════════════════════════════════════════
    // 📌 MESSAGES ÉPINGLÉS
    // ═══════════════════════════════════════════
    async togglePin(messageId) {
        try {
            const response = await fetch(this.API.PIN, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ message_id: messageId, action: 'toggle' })
            });
            
            const data = await response.json();
            
            if (data.success) {
                const msgEl = document.querySelector(`[data-message-id="${messageId}"]`);
                if (msgEl) {
                    if (data.is_pinned) {
                        msgEl.classList.add('pinned-message');
                        if (!msgEl.querySelector('.fa-thumbtack')) {
                            const header = msgEl.querySelector('.chat-header');
                            const icon = document.createElement('i');
                            icon.className = 'fas fa-thumbtack text-amber-400 text-xs';
                            header.insertBefore(icon, header.firstChild);
                        }
                    } else {
                        msgEl.classList.remove('pinned-message');
                        const pinIcon = msgEl.querySelector('.fa-thumbtack');
                        if (pinIcon) pinIcon.remove();
                    }
                }
                console.log('[Chat] ✅ Pin:', data.result);
                this.loadPinnedMessages();
            }
        } catch (error) {
            console.error('[Chat] ❌ Erreur pin:', error);
        }
    },

    async loadPinnedMessages() {
        try {
            const response = await fetch(`${this.API.PINNED}?channel=${this.currentChannel}`);
            const data = await response.json();
            
            if (data.success) {
                this.renderPinnedBar(data.pinned);
            }
        } catch (error) {
            console.warn('[Chat] Erreur chargement pinned:', error);
        }
    },

    renderPinnedBar(pinned) {
        let bar = document.getElementById('pinnedMessagesBar');
        if (!bar) return;
        
        const countBadge = document.getElementById('pinnedCount');
        
        if (!pinned || pinned.length === 0) {
            bar.classList.add('hidden');
            bar.innerHTML = '';
            if (countBadge) countBadge.classList.add('hidden');
            return;
        }
        
        if (countBadge) {
            countBadge.textContent = pinned.length;
            countBadge.classList.remove('hidden');
        }
        
        bar.classList.remove('hidden');
        const latestPin = pinned[0];
        bar.innerHTML = `
            <div class="flex items-center gap-2 text-xs">
                <i class="fas fa-thumbtack text-amber-400"></i>
                <span class="text-amber-400 font-bold truncate">
                    ${this.escapeHtml(latestPin.username)}: ${this.escapeHtml(latestPin.message.substring(0, 60))}${latestPin.message.length > 60 ? '...' : ''}
                </span>
                <button id="viewPinnedBtn" class="ml-auto text-amber-400 hover:text-amber-300 underline shrink-0">
                    Voir tout (${pinned.length})
                </button>
            </div>
        `;
        
        document.getElementById('viewPinnedBtn').addEventListener('click', () => {
            this.showPinnedModal(pinned);
        });
    },

    showPinnedModal(pinned) {
        let modal = document.getElementById('pinnedModal');
        if (modal) modal.remove();
        
        modal = document.createElement('div');
        modal.id = 'pinnedModal';
        modal.className = 'fixed inset-0 bg-black/60 backdrop-blur-sm z-[100] flex items-center justify-center p-4';
        modal.innerHTML = `
            <div class="bg-[#11151d] border border-white/10 rounded-2xl max-w-2xl w-full max-h-[80vh] overflow-hidden flex flex-col">
                <div class="p-4 border-b border-white/10 flex items-center justify-between shrink-0">
                    <h2 class="text-lg font-bold text-white flex items-center gap-2">
                        <i class="fas fa-thumbtack text-amber-400"></i>
                        Messages épinglés (${pinned.length})
                    </h2>
                    <button id="closePinnedModal" class="text-gray-400 hover:text-white transition">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                <div class="flex-1 overflow-y-auto p-4 space-y-3 custom-scrollbar">
                    ${pinned.map(p => `
                        <div class="bg-white/5 border border-white/10 rounded-xl p-3">
                            <div class="flex items-center gap-2 mb-2">
                                <img src="${p.avatar ? '/' + p.avatar : 'https://ui-avatars.com/api/?name=' + encodeURIComponent(p.username)}" 
                                     class="w-6 h-6 rounded-full" 
                                     onerror="this.src='https://ui-avatars.com/api/?name=${encodeURIComponent(p.username)}'">
                                <span class="font-bold text-sm text-white">${this.escapeHtml(p.username)}</span>
                                <span class="text-xs text-gray-500">${new Date(p.created_at).toLocaleString('fr-FR')}</span>
                            </div>
                            <div class="text-sm text-gray-300 mb-2 break-words">${this.parseMessage(p.message)}</div>
                            <div class="text-xs text-gray-500">
                                <i class="fas fa-thumbtack text-amber-400 mr-1"></i>
                                Épinglé par ${this.escapeHtml(p.pinned_by_username)}
                            </div>
                        </div>
                    `).join('')}
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        document.getElementById('closePinnedModal').addEventListener('click', () => modal.remove());
        modal.addEventListener('click', (e) => {
            if (e.target === modal) modal.remove();
        });
        
        const escHandler = (e) => {
            if (e.key === 'Escape') {
                modal.remove();
                document.removeEventListener('keydown', escHandler);
            }
        };
        document.addEventListener('keydown', escHandler);
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

        // Bouton messages épinglés dans le header
        document.getElementById('pinnedMessagesBtn')?.addEventListener('click', () => {
            this.loadPinnedMessages().then(() => {
                fetch(`${this.API.PINNED}?channel=${this.currentChannel}`)
                    .then(r => r.json())
                    .then(data => {
                        if (data.success && data.pinned && data.pinned.length > 0) {
                            this.showPinnedModal(data.pinned);
                        } else {
                            alert('Aucun message épinglé dans ce canal.');
                        }
                    });
            });
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
        
        try {
            const response = await fetch(this.API.SEND, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    message: message,
                    channel: this.currentChannel
                })
            });
            
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
        
        // Mettre à jour le header
        const channelNames = {
            'general': 'Général',
            'support': 'Support',
            'offtopic': 'Hors sujet'
        };
        const nameEl = document.getElementById('currentChannelName');
        if (nameEl) nameEl.textContent = channelNames[channel] || channel;
        
        this.loadMessages();
        this.loadPinnedMessages();
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
        if (customContainer) {
            customContainer.innerHTML = '';
            
            if (this.customEmojis.length === 0) {
                customContainer.innerHTML = '<p class="col-span-8 text-xs text-gray-500 text-center py-2">Aucun emoji custom</p>';
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
        }
        
        // 2. Onglets catégories
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
            btn.innerHTML = `<i class="${cat.icon}"></i>`;
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
        
        if (customContainer) {
            const customItems = customContainer.querySelectorAll('.emoji-item');
            customItems.forEach(item => {
                const img = item.querySelector('img');
                const name = img?.alt || '';
                item.style.display = name.toLowerCase().includes(query.toLowerCase()) ? 'flex' : 'none';
            });
        }
        
        if (query.trim() === '') {
            this.renderCategoryEmojis(this.activeEmojiCategory);
            return;
        }
        
        if (standardContainer) {
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

// Nettoyage à la fermeture
window.addEventListener('beforeunload', () => {
    ChatApp.stopPolling();
    ChatApp.stopOnlinePolling();
});
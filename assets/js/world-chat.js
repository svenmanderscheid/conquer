/* Touch-first chat. Conversation membership, read cursors and notification preferences live on the server. */
window.ConquerWorldChat = function (ctx) {
    'use strict';
    const {api, esc, date, getState, openSharedReport, openSharedLocation, openPublicProfile} = ctx, base = ctx.base || '';
    const root = document.querySelector('#world-chat');
    if (!root) return {update() {}, open() {}, close() {}, openPrivate() {}, openChannel() {}, refresh() {}, syncBadge() {}};
    root.dataset.i18nScope = '';
    const english = {
        title:'Chat', world:'World', alliance:'Alliance', private:'Private', open:'Open chat', close:'Close chat', window:'Chat window', channels:'Chat channels',
        notifications:'Notifications', all:'All messages', mentions:'Mentions only', off:'Muted', settings:'Notification settings', save:'Save', cancel:'Cancel',
        empty:'No messages yet. Start a conversation.', choose_private:'Choose a conversation.', private_hint:'Start a private chat from a player profile, alliance or mailbox.',
        loading:'Loading messages…', unavailable:'This chat is unavailable.', join:'Join an alliance to use alliance chat.', message:'Message', send:'Send', sending:'Sending…',
        placeholder:'Message {channel}…', private_placeholder:'Message {name}…', preview_label:'Open {channel} chat. Swipe left or right to switch channels.', unread:'Open chat, {count} unread messages',
        player:'Player {id}', profile:'Open profile of {name}', actions:'Message actions', reply:'Reply', replying:'Replying to {name}', clear_reply:'Cancel reply',
        quote_missing:'This message is no longer available.', mention:'Mention a player', mention_help:'Choose up to five participants. Mentions notify them according to their settings.', no_participants:'No other participants yet.', done:'Done',
        older:'Load older messages', loading_older:'Loading older messages…', pinned:'Pinned messages', pin:'Pin for alliance', unpin:'Unpin message',
        like:'Like', heart:'Love', laugh:'Laugh', cheer:'Celebrate', report:'Report message', report_title:'Report a message', reason:'Reason', details:'Details (optional)',
        harassment:'Harassment', spam:'Spam', cheating:'Cheating', inappropriate:'Inappropriate content', other:'Other', report_send:'Send report', reported:'Your report has been sent to the moderators.',
        block:'Block player', block_confirm:'Block {name}? You will no longer receive their private messages.', block_done:'Player blocked. Manage blocked players in Community.',
        report_view:'View report', location:'Shared location', location_view:'Show on world map', location_label:'Show shared location X {x}, Y {y} on the world map',
        latest:'Latest messages', new_messages:'{count} new messages', characters:'{count} of {max} characters', action_failed:'The action could not be completed. Please try again.', notification_hint:'Your choices apply on all your devices.', private_blocked:'Messaging is unavailable for this player.'
    };
    const t = (key, params = {}) => {
        const translated = window.ConquerLocale?.t('social_chat.' + key, params);
        return translated && translated !== 'social_chat.' + key ? translated : (english[key] || key).replace(/\{(\w+)\}/g, (_, name) => params[name] ?? '');
    };
    const order = ['world','alliance','private'], reactions = {like:'👍',heart:'♥',laugh:'😄',cheer:'🎉'};
    const channelIcon = (key, className = '') => {
        const shapes = {
            world:'<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c-3 3-4 6-4 9s1 6 4 9c3-3 4-6 4-9s-1-6-4-9Z"/>',
            alliance:'<path d="M12 3c-2 2-5 3-8 3v6c0 4 4 7 8 9 4-2 8-5 8-9V6c-3 0-6-1-8-3Z"/><path d="m12 7 1.5 3 3.5.5-2.5 2.5.5 3.5-3-1.5-3 1.5.5-3.5L7 10.5l3.5-.5Z"/>',
            private:'<path d="M5 4h10a3 3 0 0 1 3 3v4a3 3 0 0 1-3 3H9l-4 3v-3a3 3 0 0 1-3-3V7a3 3 0 0 1 3-3Z"/><path d="M18 8h1a3 3 0 0 1 3 3v5a3 3 0 0 1-3 3v3l-4-3h-4a3 3 0 0 1-3-3"/>'
        };
        return `<svg class="world-chat-channel-icon ${className}" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">${shapes[key] || shapes.world}</svg>`;
    };
    const scrollViews = new Map();
    const drafts = new Map(), history = new Map(), privateTabs = new Map(), readSent = new Map(), openingConversations = new Set(), operationReceipts = new Map();
    let world = 0, channel = 'world', privateTarget = 0, data = null, visible = false, expanded = false;
    let loading = false, sending = false, acting = false, loadingOlder = false, timer, error = '', stamp = '', panel = null, panelStamp = '';
    let pointerStart = null, suppressPreviewClick = false, generation = 0, reloadRequested = false;
    let renderedKey = '', privateStamp = '', panelOrigin = null;
    const activeKey = () => channel === 'private' ? `private:${privateTarget}` : channel;
    const scopedKey = () => `${world}:${activeKey()}`;
    const draft = () => {const key = scopedKey();if (!drafts.has(key)) drafts.set(key,{text:'',receipt:null,reply:null,mentions:[]});return drafts.get(key);};
    const canRead = () => visible && !document.hidden;
    const nearBottom = () => log.scrollHeight - log.clientHeight - log.scrollTop < 55;
    const mode = key => data?.preferences?.channels?.[key.split(':')[0]] || 'all';
    const rowsFor = key => key === channel && history.has(scopedKey()) ? history.get(scopedKey()).rows : data?.[key + '_chat'] || [];
    const unread = key => Number(data?.channel_unread?.[key] || 0);
    const hasConversation = () => channel !== 'private' || Boolean(privateTarget && Number(data?.private_player?.id)===privateTarget);
    const allowed = () => Boolean(data && hasConversation() && (channel !== 'alliance' || data.alliance));
    const canSend = () => allowed() && !(channel === 'private' && (data.private_blocked || data.can_send_private === false));
    const button = (key, attr, content) => `<button type="button" ${attr}>${content ?? esc(t(key))}</button>`;
    const context = () => ({world_id:world,expected_world_id:world,channel,...(channel === 'private' ? {player_id:privateTarget} : {})});
    const schedule = () => {clearTimeout(timer);if (canRead()) timer = setTimeout(load,10000);};
    root.innerHTML = `<button type="button" class="world-chat-preview" data-chat-open aria-label="${esc(t('open'))}">
        <span class="world-chat-preview-channel"><span class="world-chat-preview-emblem"><span class="world-chat-preview-icon" data-chat-preview-icon aria-hidden="true">${channelIcon('world')}</span><span class="world-chat-channel-pager" aria-hidden="true">${order.map(key=>`<span class="world-chat-channel-dot${key==='world'?' is-active':''}" data-chat-channel-dot="${key}"></span>`).join('')}</span></span><strong data-chat-preview-channel></strong><small>${esc(t('open'))}</small></span>
        <span class="world-chat-preview-messages" data-chat-preview-messages></span><span class="world-chat-preview-arrow" aria-hidden="true"><span>›</span></span></button>
        <section class="world-chat-window" aria-label="${esc(t('window'))}" hidden>
        <header class="world-chat-window-heading">${button('close','class="world-chat-back" data-chat-close aria-label="'+esc(t('close'))+'"','‹')}<div class="world-chat-heading-copy"><h2 data-chat-heading-title>${esc(t('title'))}</h2><small data-chat-heading-channel></small></div>${button('notifications','class="world-chat-mute" data-chat-mute aria-label="'+esc(t('notifications'))+'"','🔔')}${button('close','class="world-chat-close" data-chat-close aria-label="'+esc(t('close'))+'"','×')}</header>
        <nav class="world-chat-tabs" aria-label="${esc(t('channels'))}">${order.map(key => button(key,`data-chat-channel="${key}" aria-pressed="${key==='world'}"`,`${channelIcon(key,'world-chat-tab-icon')}<span class="world-chat-tab-label">${esc(t(key))}</span><span class="world-chat-badge" hidden></span>`)).join('')}</nav>
        <div class="world-chat-private-list" data-chat-private-list hidden></div><div class="world-chat-pins" data-chat-pins hidden></div>
        <div id="world-chat-body"><div class="world-chat-timeline"><div class="world-chat-log" role="log" aria-live="polite" aria-relevant="additions text"></div>
        ${button('latest','class="world-chat-latest" data-chat-latest hidden')}<section class="world-chat-tools" data-chat-tools tabindex="-1" aria-label="${esc(t('actions'))}" hidden></section></div><p class="world-chat-error" role="status" hidden></p>
        <form class="world-chat-compose"><div class="world-chat-draft-context" data-chat-draft-context hidden></div><label class="world-chat-label" for="world-chat-message">${esc(t('message'))}</label>${button('mention','class="world-chat-mention" data-chat-mentions aria-label="'+esc(t('mention'))+'"','@')}<div class="world-chat-input-wrap"><input id="world-chat-message" name="message" maxlength="200" autocomplete="off" required aria-describedby="world-chat-count" placeholder="${esc(t('message'))}"><span id="world-chat-count" class="world-chat-counter" data-chat-count>0 / 200</span></div><button type="submit">${esc(t('send'))}</button></form></div></section>`;
    const preview = root.querySelector('.world-chat-preview'), previewMessages = root.querySelector('[data-chat-preview-messages]');
    const log = root.querySelector('.world-chat-log'), form = root.querySelector('.world-chat-compose'), input = form.elements.message;
    const latest = root.querySelector('[data-chat-latest]'), counter = root.querySelector('[data-chat-count]');
    const errorBox = root.querySelector('.world-chat-error'), privateList = root.querySelector('[data-chat-private-list]'), tools = root.querySelector('[data-chat-tools]');
    const sharedLocation = message => {
        const match = String(message?.message || '').match(/· (?:Welt|World) (\d+) · X (\d+) \/ Y (\d+)$/);
        if (!match) return null;
        const location = {world:Number(match[1]),x:Number(match[2]),y:Number(match[3])};
        return location.world > 0 && location.x >= 0 && location.x < Number(getState()?.world?.map_profile?.width || 256) && location.y >= 0 && location.y < Number(getState()?.world?.map_profile?.height || 256) ? location : null;
    };
    const monsterIcon = (message, location) => {const monster = (getState()?.monsters || []).find(entry => Number(entry.coord_x) === location.x && Number(entry.coord_y) === location.y), definition = monster?.definition || {}, name = String(definition.name || message?.message || ''), art = /^(?:monsters\/)?[a-z0-9-]+$/.test(definition.art || '') ? definition.art : /skeleton|skelett/i.test(name) ? 'skeleton' : /golem/i.test(name) ? 'golem' : 'orc';return `${base}/assets/art/${art}.png`;};
    const locationLink = message => {const location = sharedLocation(message);return location ? `<button type="button" class="world-chat-location" data-chat-location data-world="${location.world}" data-x="${location.x}" data-y="${location.y}" aria-label="${esc(t('location_label',location))}"><img src="${esc(monsterIcon(message,location))}" alt=""><span><b>${esc(t('location'))}</b><small>${esc(t('location_view'))}</small></span><strong>X ${location.x} / Y ${location.y}</strong></button>` : '';};
    function syncBadge() {
        const badge = document.querySelector('#navigation [data-id="chat"] .chat-dock-badge');if (!badge) return;
        const count = order.reduce((sum,key) => sum + (mode(key) === 'off' ? 0 : unread(key)),0);
        badge.hidden = !count;badge.textContent = count > 99 ? '99+' : String(count);
        badge.parentElement.setAttribute('aria-label',count ? t('unread',{count}) : t('open'));
    }
    function messageDate(value) {
        if (!(value instanceof Date) && typeof value !== 'number' && (typeof value !== 'string' || !value.trim())) return null;
        try {
            const parsed = new Date(value instanceof Date || typeof value === 'number' ? value : date(value));
            return Number.isFinite(parsed.getTime()) ? parsed : null;
        } catch { return null; }
    }
    function formatMessageDate(value, options) {
        try {
            return window.ConquerLocale?.formatDate ? window.ConquerLocale.formatDate(value,options) : new Intl.DateTimeFormat(window.ConquerLocale?.locale || 'en',options).format(value);
        } catch { return new Intl.DateTimeFormat('en',options).format(value); }
    }
    function timelineHtml(rows) {
        const days = new Set();
        return rows.map(message => {
            const created = messageDate(message.created_at);
            let separator = '';
            if (created) {
                const key = `${created.getFullYear()}-${String(created.getMonth()+1).padStart(2,'0')}-${String(created.getDate()).padStart(2,'0')}`;
                if (!days.has(key)) {
                    days.add(key);
                    separator = `<div class="world-chat-day-separator"><time datetime="${key}" data-i18n-ignore>${esc(formatMessageDate(created,{year:'numeric',month:'long',day:'numeric'}))}</time></div>`;
                }
            }
            return separator + messageHtml(message);
        }).join('');
    }
    function messageHtml(message, compact = false) {
        const id = Number(message.id), player = Number(message.player_id), tag = channel === 'world' && message.alliance_tag ? `[${esc(message.alliance_tag)}] ` : '';
        const avatar = ['knight','archer','rider'].includes(message.avatar) ? message.avatar : 'knight', own = player === Number(data?.player_id);
        if (compact) return `<span class="world-chat-preview-message"><span class="world-chat-preview-copy" data-user-content><strong class="world-chat-preview-name">${tag}${esc(message.username)}:</strong> <span class="world-chat-preview-text">${esc(message.message)}</span></span></span>`;
        const created = messageDate(message.created_at), time = created ? `<time datetime="${esc(created.toISOString())}" title="${esc(formatMessageDate(created,{year:'numeric',month:'numeric',day:'numeric',hour:'2-digit',minute:'2-digit',second:'2-digit'}))}" data-i18n-ignore>${esc(formatMessageDate(created,{hour:'2-digit',minute:'2-digit'}))}</time>` : '<time aria-hidden="true"></time>';
        const quote = message.reply_to, counts = (message.reactions || []).filter(r => reactions[r.reaction]);
        const rankBadge = channel === 'alliance' ? (window.ConquerAllianceRanks?.badge(message.alliance_role) || '') : '';
        return `<article class="world-chat-message ${own?'mine':''}" data-message-id="${id}"><button type="button" class="world-chat-avatar-button" data-chat-profile="${player}" aria-label="${esc(t('profile',{name:message.username}))}"><img class="world-chat-avatar" src="${base}/assets/art/${avatar}.png" alt="">${rankBadge}</button><div class="world-chat-message-content"><header><strong class="world-chat-name" data-user-content>${tag}${esc(message.username)}</strong>${time}</header><div class="world-chat-bubble">${quote?`<blockquote data-user-content><strong>${esc(quote.username || '')}</strong><span>${esc(quote.message || t('quote_missing'))}</span></blockquote>`:''}<p data-user-content>${esc(message.message)}</p>${message.mentions?.length?`<div class="world-chat-mentioned" data-user-content>${message.mentions.map(p=>`<span>@${esc(p.username)}</span>`).join(' ')}</div>`:''}${locationLink(message)}${Number(message.shared_report_id)>0?button('report_view',`class="world-chat-report" data-chat-report="${Number(message.shared_report_id)}"`):''}</div>${counts.length?`<div class="world-chat-message-tools">${counts.map(r=>button(r.reaction,`class="world-chat-reaction" data-chat-react="${esc(r.reaction)}" data-id="${id}" aria-pressed="${Boolean(r.mine)}" aria-label="${esc(t(r.reaction))}"`,`${reactions[r.reaction]} ${Number(r.count)}`)).join('')}</div>`:''}</div>${button('actions',`class="world-chat-message-menu" data-chat-menu="${id}" aria-label="${esc(t('actions'))}"`,`${message.pinned?'⌖ ':''}•••`)}</article>`;
    }
    function drawPrivateList() {
        privateList.hidden = channel !== 'private';if (privateList.hidden) return;
        const entries = [...privateTabs.values()], signature = JSON.stringify([privateTarget,entries]);
        if (signature === privateStamp) return;
        const oldScroll = privateList.scrollLeft, focused = privateList.contains(document.activeElement) ? document.activeElement.closest('[data-chat-private]')?.dataset.chatPrivate : null;
        privateStamp = signature;
        privateList.innerHTML = entries.length ? entries.map(p=>{
            const avatar = ['knight','archer','rider'].includes(p.avatar) ? p.avatar : 'knight';
            return `<button type="button" data-chat-private="${Number(p.player_id)}" aria-pressed="${Number(p.player_id)===privateTarget}"><img class="world-chat-conversation-avatar" src="${base}/assets/art/${avatar}.png" alt=""><span class="world-chat-conversation-copy" data-user-content><strong>${esc(p.username)}</strong><small>${esc(p.last_message || '')}</small></span>${Number(p.unread)>0?`<b class="world-chat-conversation-badge">${Number(p.unread)>99?'99+':Number(p.unread)}</b>`:''}</button>`;
        }).join('') : `<p>${esc(t('private_hint'))}</p>`;
        privateList.scrollLeft = oldScroll;
        if(focused)privateList.querySelector(`[data-chat-private="${Number(focused)}"]`)?.focus({preventScroll:true});
        const active = privateList.querySelector('[aria-pressed="true"]');
        if(active){const a=active.getBoundingClientRect(),b=privateList.getBoundingClientRect();if(a.left<b.left)privateList.scrollLeft+=a.left-b.left;else if(a.right>b.right)privateList.scrollLeft+=a.right-b.right;}
    }
    function participants() {
        const candidates = new Map();
        for (const p of rowsFor(channel)) if (Number(p.player_id) !== Number(data?.player_id)) candidates.set(Number(p.player_id),{player_id:Number(p.player_id),username:p.username});
        if (data?.private_player && channel === 'private') candidates.set(Number(data.private_player.id),{player_id:Number(data.private_player.id),username:data.private_player.username});
        for (const p of draft().mentions) candidates.set(p.player_id,p);
        return [...candidates.values()];
    }
    function setPanel(next) {
        const previous = panel, returnFocus = previous && (tools.contains(document.activeElement) || document.activeElement === document.body);
        if(next && !previous)panelOrigin = document.activeElement;
        panel = next;panelStamp = '';drawPanel();updateLatest();
        if(next){const first=tools.querySelector('[data-chat-reply],select,input,button');(first || tools).focus({preventScroll:true});}
        else if(returnFocus){const replacement=previous?.message?root.querySelector(`[data-chat-menu="${Number(previous.message.id)}"]`):null;(panelOrigin?.isConnected?panelOrigin:replacement || input).focus({preventScroll:true});}
        if(!next)panelOrigin=null;
    }
    function drawPanel() {
        tools.hidden = !panel;log.inert = Boolean(panel);if(panel)root.dataset.chatPanel=panel.type;else delete root.dataset.chatPanel;if (!panel) {tools.innerHTML='';return;}
        const signature = JSON.stringify([panel,channel,data?.role,draft().mentions]);if (signature === panelStamp) {for (const control of tools.querySelectorAll('button,select,input,textarea')) control.disabled=acting;return;}panelStamp = signature;
        const end = button('cancel','data-chat-panel-close');
        if (panel.type === 'notifications') {
            tools.innerHTML = `<div class="world-chat-tools-heading"><strong>${esc(t('settings'))}</strong>${end}</div><p>${esc(t('notification_hint'))}</p><div class="world-chat-setting-grid">${order.map(key=>`<label>${esc(t(key))}<select data-chat-mode="${key}">${['all','mentions','off'].map(value=>`<option value="${value}" ${mode(key)===value?'selected':''}>${esc(t(value))}</option>`).join('')}</select></label>`).join('')}</div>${button('save','data-chat-save-settings')}`;
        } else if (panel.type === 'mentions') {
            const people = participants();tools.innerHTML = `<div class="world-chat-tools-heading"><strong>${esc(t('mention'))}</strong>${button('done','data-chat-panel-close')}</div><p>${esc(t('mention_help'))}</p><div class="world-chat-people">${people.length?people.map(p=>`<label><input type="checkbox" data-chat-mention="${p.player_id}" ${draft().mentions.some(m=>m.player_id===p.player_id)?'checked':''}><span data-user-content>${esc(p.username)}</span></label>`).join(''):`<p>${esc(t('no_participants'))}</p>`}</div>`;
        } else if (panel.type === 'report') {
            tools.innerHTML = `<div class="world-chat-tools-heading"><strong>${esc(t('report_title'))}</strong>${end}</div><label>${esc(t('reason'))}<select data-chat-reason>${['harassment','spam','cheating','inappropriate','other'].map(key=>`<option value="${key}">${esc(t(key))}</option>`).join('')}</select></label><label>${esc(t('details'))}<textarea data-chat-report-details maxlength="2000" rows="2"></textarea></label>${button('report_send','data-chat-report-send')}`;
        } else if (panel.type === 'block') {
            tools.innerHTML = `<p>${esc(t('block_confirm',{name:panel.message.username}))}</p><div class="world-chat-action-row">${button('block','class="world-chat-danger" data-chat-block-confirm')}${end}</div>`;
        } else {
            const m = panel.message, self = Number(m.player_id) === Number(data?.player_id), canPin = channel === 'alliance' && ['leader','vice_leader','officer'].includes(data?.role);
            tools.innerHTML = `<div class="world-chat-tools-heading"><strong data-user-content>${esc(m.username)}</strong>${end}</div><p class="world-chat-tool-quote" data-user-content>${esc(m.message)}</p><div class="world-chat-action-primary">${button('reply','data-chat-reply')}${canPin?button(m.pinned?'unpin':'pin','data-chat-pin'):''}</div><div class="world-chat-reactions">${Object.entries(reactions).map(([key,icon])=>button(key,`data-chat-react="${key}" data-id="${Number(m.id)}" aria-label="${esc(t(key))}"`,icon)).join('')}</div>${!self?`<div class="world-chat-action-secondary">${button('report','data-chat-report-open')}${button('block','data-chat-block-open')}</div>`:''}`;
        }
        for (const control of tools.querySelectorAll('button,select,input,textarea')) control.disabled = acting;
    }
    function drawDraft() {
        const box = root.querySelector('[data-chat-draft-context]'), d = draft();box.hidden = !d.reply && !d.mentions.length;
        box.innerHTML = (d.reply?`<span class="world-chat-reply-preview"><strong>${esc(t('replying',{name:d.reply.username}))}</strong><span data-user-content>${esc(d.reply.message)}</span>${button('clear_reply','data-chat-clear-reply aria-label="'+esc(t('clear_reply'))+'"','×')}</span>`:'') + (d.mentions.length?`<span class="world-chat-mention-chips" data-user-content>${d.mentions.map(p=>`<span>@${esc(p.username)}</span>`).join(' ')}</span>`:'');
    }
    function drawComposer() {
        input.disabled = !canSend() || sending;
        const send = form.querySelector('[type="submit"]');send.disabled = input.disabled || !input.value.trim();send.textContent=t(sending?'sending':'send');
        form.querySelector('[data-chat-mentions]').disabled=input.disabled;
        const length=input.value.length;counter.textContent=length+' / 200';counter.setAttribute('aria-label',t('characters',{count:length,max:200}));
    }
    function rememberScroll() {
        if(renderedKey!==scopedKey() || !expanded || !allowed())return;
        const previous=scrollViews.get(scopedKey())||{},follow=nearBottom();
        scrollViews.set(scopedKey(),{top:log.scrollTop,follow,seenId:follow&&!panel?Math.max(0,...rowsFor(channel).map(m=>Number(m.id))):previous.seenId||0});
    }
    function updateLatest() {
        const hide=!expanded||Boolean(panel)||!allowed()||nearBottom();latest.hidden=hide;if(hide)return;
        const seen=scrollViews.get(scopedKey())?.seenId||0,count=rowsFor(channel).filter(m=>Number(m.id)>seen).length;
        latest.textContent=count?t('new_messages',{count}):t('latest');
    }
    function draw() {
        root.dataset.activeChatChannel=channel;
        root.classList.toggle('is-open',expanded);root.querySelector('.world-chat-window').hidden = !expanded;preview.hidden = expanded;
        document.body.classList.toggle('world-chat-open',expanded && visible);syncBadge();drawPrivateList();drawPanel();drawDraft();
        root.querySelector('[data-chat-preview-channel]').textContent = t(channel);
        const previewIcon=root.querySelector('[data-chat-preview-icon]');
        if(previewIcon.dataset.channel!==channel){previewIcon.dataset.channel=channel;previewIcon.innerHTML=channelIcon(channel);}
        for(const dot of root.querySelectorAll('[data-chat-channel-dot]'))dot.classList.toggle('is-active',dot.dataset.chatChannelDot===channel);
        const rows = rowsFor(channel);previewMessages.innerHTML = rows.length ? rows.slice(-2).map(row=>messageHtml(row,true)).join('') : `<span class="world-chat-preview-empty">${esc(t(channel==='private'&&!privateTarget?'choose_private':'empty'))}</span>`;
        preview.setAttribute('aria-label',t('preview_label',{channel:t(channel)}));
        const heading = root.querySelector('[data-chat-heading-title]'), subtitle = root.querySelector('[data-chat-heading-channel]');
        const privateName=channel==='private'&&privateTarget ? privateTabs.get(privateTarget)?.username || data?.private_player?.username : null;
        heading.toggleAttribute('data-user-content',Boolean(privateName));heading.textContent=privateName || t(channel);
        subtitle.toggleAttribute('data-user-content',channel==='alliance'&&Boolean(data?.alliance?.name));subtitle.textContent=privateName?t('private'):channel==='alliance'&&data?.alliance?.name?data.alliance.name:t('title');
        for (const tab of root.querySelectorAll('[data-chat-channel]')) {const key=tab.dataset.chatChannel,count=unread(key);tab.setAttribute('aria-pressed',String(key===channel));const badge=tab.querySelector('.world-chat-badge');badge.hidden=!count;badge.textContent=count?(count>99?'99+':String(count)):'';}
        const mute = root.querySelector('[data-chat-mute]');mute.setAttribute('aria-pressed',String(mode(channel)==='off'));mute.textContent=mode(channel)==='off'?'🔕':'🔔';
        drawComposer();
        input.placeholder = channel==='private' && !privateTarget ? t('choose_private') : channel==='private' && !hasConversation() ? t('loading') : channel==='private' && !canSend() ? t('private_blocked') : channel==='private' ? t('private_placeholder',{name:privateTabs.get(privateTarget)?.username || t('private')}) : t('placeholder',{channel:t(channel)});
        const status=error || (channel==='private'&&hasConversation()&&(data?.private_blocked||data?.can_send_private===false)?t('private_blocked'):'');
        log.setAttribute('aria-label',t(channel));errorBox.textContent=status;errorBox.hidden=!status;
        const pins = root.querySelector('[data-chat-pins]');pins.hidden=channel!=='alliance'||!data?.pins?.length;const pinKey=JSON.stringify([channel,data?.pins]);if(pins.dataset.stamp!==pinKey){const opened=pins.querySelector('details')?.open;pins.dataset.stamp=pinKey;pins.innerHTML=pins.hidden?'':`<details ${opened?'open':''}><summary>${esc(t('pinned'))} · ${data.pins.length}</summary>${data.pins.map(m=>`<button type="button" data-chat-pinned="${Number(m.id)}"><strong data-user-content>${esc(m.username)}</strong><span data-user-content>${esc(m.message)}</span></button>`).join('')}</details>`;}
        const nextStamp = JSON.stringify([scopedKey(),window.ConquerLocale?.locale || 'en',Boolean(data),allowed(),Boolean(data?.private_blocked),data?.alliance?.id,rows,history.get(scopedKey())?.hasMore,loadingOlder]);if(nextStamp===stamp){updateLatest();return;}
        const saved=scrollViews.get(scopedKey()),contextChanged=renderedKey!==scopedKey();
        const restore=contextChanged||!expanded||!stamp,follow=restore ? saved?.follow??true : nearBottom(),scroll=restore ? saved?.top||0 : log.scrollTop;stamp=nextStamp;renderedKey=allowed()?scopedKey():'';
        const loadMore=history.get(scopedKey())?.hasMore?button(loadingOlder?'loading_older':'older',`class="world-chat-history" data-chat-older ${loadingOlder?'disabled':''}`):'';
        log.innerHTML=!data?`<p class="world-chat-empty">${esc(t('loading'))}</p>`:!allowed()?`<p class="world-chat-empty">${esc(t(channel==='alliance'?'join':channel==='private'?(privateTarget?'loading':'choose_private'):'unavailable'))}</p>`:loadMore+(rows.length?timelineHtml(rows):`<p class="world-chat-empty">${esc(t('empty'))}</p>`);
        log.scrollTop=follow?log.scrollHeight:scroll;rememberScroll();updateLatest();
    }
    function mergeRows(oldRows,newRows) {const rows=new Map(oldRows.map(m=>[Number(m.id),m]));for(const m of newRows)rows.set(Number(m.id),m);return [...rows.values()].sort((a,b)=>Number(a.id)-Number(b.id));}
    function currentAllianceRanks(rows) {
        // An absent list supports older response fixtures; an empty list is authoritative.
        if (!Array.isArray(data?.alliance_member_roles)) return rows;
        const roles = new Map(data.alliance_member_roles.map(member => [Number(member.player_id),member.role]));
        return rows.map(message => {
            const role = roles.get(Number(message.player_id)) || null;
            return {...message,alliance_role:role,alliance_role_level:window.ConquerAllianceRanks?.level(role) || 0};
        });
    }
    async function markRead() {
        if(!expanded||!canRead()||!allowed()||panel||loadingOlder||!nearBottom())return;
        const rows=rowsFor(channel),id=Math.max(0,...rows.map(m=>Number(m.id))),key=scopedKey(),snapshot=context(),version=generation;
        if(!id||id<=Number(data?.read_cursors?.[channel]||0)||id<=Number(readSent.get(key)||0))return;
        readSent.set(key,id);
        try {
            await api('community/social-action',{...snapshot,action:'chat.read',message_id:id,request_id:crypto.randomUUID()});
            if(version!==generation||key!==scopedKey())return;
            data.read_cursors={...data.read_cursors,[channel]:id};
            if(channel==='private'){const p=privateTabs.get(privateTarget);if(p)p.unread=0;}else data.channel_unread={...data.channel_unread,[channel]:0};
            draw();if(channel==='private')load();
        } catch {if(readSent.get(key)===id)readSent.delete(key);}
    }
    async function load() {
        if(!canRead())return;if(loading){reloadRequested=true;return;}reloadRequested=false;const version=generation,requestedKey=scopedKey(),requestedWorld=world,requestedChannel=channel,target=privateTarget;loading=true;
        try {
            const next=await api(`community/chat?world_id=${world}${channel==='private'&&target?'&player_id='+target:''}`);
            if(version!==generation||requestedKey!==scopedKey()||Number(next.world_id)!==world)return;
            const blockList=value=>(value?.blocks||[]).map(p=>Number(p.player_id)).sort((a,b)=>a-b).join(',');if(data&&blockList(data)!==blockList(next)){history.clear();const blocked=new Set((next.blocks||[]).map(p=>Number(p.player_id)));for(const [draftKey,d]of drafts)if(draftKey.startsWith(world+':')){d.mentions=d.mentions.filter(p=>!blocked.has(p.player_id));if(blocked.has(Number(d.reply?.player_id)))d.reply=null;d.receipt=null;}}
            if(data&&Number(data.alliance?.id||0)!==Number(next.alliance?.id||0)){const allianceKey=`${world}:alliance`;history.delete(allianceKey);readSent.delete(allianceKey);const d=drafts.get(allianceKey);if(d){d.reply=null;d.mentions=[];d.receipt=null;}}
            data=next;const selected=privateTabs.get(privateTarget);privateTabs.clear();for(const p of next.conversations||[])privateTabs.set(Number(p.player_id),p);if(selected&&!privateTabs.has(privateTarget))privateTabs.set(privateTarget,selected);
            for(const key of order){if(key==='private'&&requestedChannel!=='private')continue;const cacheKey=`${world}:${key==='private'?'private:'+target:key}`,rows=next[key+'_chat']||[],cached=history.get(cacheKey),merged=cached?.older?mergeRows(cached.rows,rows):rows;history.set(cacheKey,{rows:key==='alliance'?currentAllianceRanks(merged):merged,hasMore:cached?.older?cached.hasMore:rows.length>=50,older:cached?.older||false});}
            error='';draw();markRead();
        } catch(problem) {if(version===generation&&requestedKey===scopedKey()){error=problem.message||t('action_failed');draw();}}
        finally {loading=false;if((reloadRequested||version!==generation||requestedWorld!==world||requestedChannel!==channel||requestedKey!==scopedKey())&&canRead())load();else schedule();}
    }
    async function older() {
        if(loadingOlder)return;const key=scopedKey(),version=generation,snapshot=context(),allianceId=Number(data?.alliance?.id||0),cache=history.get(key);if(!cache?.hasMore)return;
        const current=()=>version===generation&&key===scopedKey()&&(snapshot.channel!=='alliance'||allianceId===Number(data?.alliance?.id||0));
        loadingOlder=true;draw();const previousHeight=log.scrollHeight,previousScroll=log.scrollTop;
        try {const next=await api(`community/history?world_id=${snapshot.world_id}&channel=${snapshot.channel}${snapshot.player_id?'&player_id='+snapshot.player_id:''}&before_id=${Number(cache.rows[0]?.id||0)}`);if(!current())return;const merged=mergeRows(next.messages||[],history.get(key)?.rows||[]);history.set(key,{rows:snapshot.channel==='alliance'?currentAllianceRanks(merged):merged,hasMore:Boolean(next.has_more),older:true});}
        catch(problem){if(current())error=problem.message||t('action_failed');}
        finally{loadingOlder=false;if(current()){draw();log.scrollTop=previousScroll+log.scrollHeight-previousHeight;}}
    }
    async function command(action,payload={},success='') {
        if(acting)return;const version=generation,key=scopedKey(),snapshot=context(),operation={...snapshot,action,...payload},receiptKey=JSON.stringify(operation);if(!operationReceipts.has(receiptKey))operationReceipts.set(receiptKey,crypto.randomUUID());acting=true;drawPanel();
        try {const result=await api('community/social-action',{...operation,request_id:operationReceipts.get(receiptKey)});operationReceipts.delete(receiptKey);if(version!==generation||key!==scopedKey())return;const updated=result?.result?.chat_message;if(updated){const cache=history.get(key);if(cache){const merged=mergeRows(cache.rows,[updated]);cache.rows=channel==='alliance'?currentAllianceRanks(merged):merged;}if(data?.[channel+'_chat'])data[channel+'_chat']=data[channel+'_chat'].map(m=>Number(m.id)===Number(updated.id)?updated:m);}if(action==='block.add')history.clear();draw();setPanel(null);await load();if(success){error=t(success);draw();}}
        catch(problem){if(version===generation&&key===scopedKey()){error=problem.message||t('action_failed');draw();}}
        finally{acting=false;drawPanel();}
    }
    function select(next,target=privateTarget) {if(!order.includes(next))return;rememberScroll();channel=next;if(next==='private')privateTarget=target;panel=null;panelStamp='';stamp='';error='';input.value=draft().text;draw();load();}
    function step(direction) {select(order[(order.indexOf(channel)+direction+order.length)%order.length]);}
    function open() {if(!visible)return;if(!expanded)ctx.onOpen?.();expanded=true;stamp='';draw();load();setTimeout(()=>root.querySelector(`[data-chat-channel="${channel}"]`)?.focus({preventScroll:true}),0);}
    function close(options={}) {const wasOpen=expanded;rememberScroll();expanded=false;panel=null;draw();if(wasOpen&&!options.navigating)ctx.onClose?.();document.querySelector('#navigation [data-id="chat"]')?.focus({preventScroll:true});}
    input.addEventListener('input',()=>{const d=draft();d.text=input.value;d.receipt=null;drawComposer();});
    log.addEventListener('scroll',()=>{rememberScroll();updateLatest();if(nearBottom())markRead();},{passive:true});
    root.addEventListener('change',event=>{
        const checkbox=event.target.closest('[data-chat-mention]');if(!checkbox)return;const d=draft(),id=Number(checkbox.dataset.chatMention),person=participants().find(p=>p.player_id===id);if(!person)return;
        if(checkbox.checked&&d.mentions.length<5)d.mentions.push(person);else d.mentions=d.mentions.filter(p=>p.player_id!==id);d.receipt=null;panelStamp='';drawPanel();drawDraft();
    });
    root.addEventListener('click',event=>{
        const target=event.target.closest('button');if(!target||target.disabled)return;
        const profile=target.dataset.chatProfile;if(profile){close({navigating:true});if(openPublicProfile)Promise.resolve(openPublicProfile(Number(profile))).catch(problem=>{error=problem.message||t('action_failed');open();});return;}
        if(target.hasAttribute('data-chat-location')){event.stopPropagation();if(!openSharedLocation)return;close({navigating:true});Promise.resolve(openSharedLocation({world:Number(target.dataset.world),x:Number(target.dataset.x),y:Number(target.dataset.y)})).catch(problem=>{error=problem.message||t('action_failed');open();});return;}
        if(target.dataset.chatReport){event.stopPropagation();if(!openSharedReport)return;target.disabled=true;Promise.resolve(openSharedReport(Number(target.dataset.chatReport))).catch(problem=>{error=problem.message||t('action_failed');draw();}).finally(()=>{if(target.isConnected)target.disabled=false;});return;}
        if(target.hasAttribute('data-chat-open')){if(suppressPreviewClick){suppressPreviewClick=false;return;}open();return;}
        if(target.hasAttribute('data-chat-close')){if(panel)setPanel(null);else close();return;}
        if(target.hasAttribute('data-chat-latest')){log.scrollTop=log.scrollHeight;rememberScroll();updateLatest();markRead();return;}
        if(target.dataset.chatChannel){select(target.dataset.chatChannel);return;}
        if(target.dataset.chatPrivate){select('private',Number(target.dataset.chatPrivate));return;}
        if(target.hasAttribute('data-chat-mute')){setPanel(panel?.type==='notifications'?null:{type:'notifications'});return;}
        if(target.hasAttribute('data-chat-panel-close')){setPanel(null);return;}
        if(target.hasAttribute('data-chat-mentions')){setPanel({type:'mentions'});return;}
        if(target.hasAttribute('data-chat-clear-reply')){draft().reply=null;draft().receipt=null;drawDraft();return;}
        if(target.hasAttribute('data-chat-save-settings')){command('preferences.save',{channels:Object.fromEntries([...tools.querySelectorAll('[data-chat-mode]')].map(s=>[s.dataset.chatMode,s.value]))});return;}
        if(target.hasAttribute('data-chat-older')){older();return;}
        if(target.dataset.chatMenu||target.dataset.chatPinned){const id=Number(target.dataset.chatMenu||target.dataset.chatPinned),message=rowsFor(channel).find(m=>Number(m.id)===id)||(data?.pins||[]).find(m=>Number(m.id)===id);if(message)setPanel({type:'message',message});return;}
        if(target.dataset.chatReact){const m=rowsFor(channel).find(m=>Number(m.id)===Number(target.dataset.id))||panel?.message;command('chat.react',{message_id:Number(target.dataset.id),reaction:target.dataset.chatReact,active:!m?.reactions?.some(r=>r.reaction===target.dataset.chatReact&&r.mine)});return;}
        if(target.hasAttribute('data-chat-reply')){draft().reply=panel.message;draft().receipt=null;setPanel(null);drawDraft();input.focus({preventScroll:true});return;}
        if(target.hasAttribute('data-chat-pin')){command('chat.pin',{message_id:Number(panel.message.id),active:!panel.message.pinned});return;}
        if(target.hasAttribute('data-chat-report-open')){setPanel({type:'report',message:panel.message});return;}
        if(target.hasAttribute('data-chat-block-open')){setPanel({type:'block',message:panel.message});return;}
        if(target.hasAttribute('data-chat-block-confirm')){command('block.add',{player_id:Number(panel.message.player_id)},'block_done');return;}
        if(target.hasAttribute('data-chat-report-send')){command('report.submit',{player_id:Number(panel.message.player_id),message_id:Number(panel.message.id),reason:tools.querySelector('[data-chat-reason]').value,details:tools.querySelector('[data-chat-report-details]').value},'reported');}
    });
    preview.addEventListener('pointerdown',event=>{pointerStart={x:event.clientX,y:event.clientY};});
    preview.addEventListener('pointerup',event=>{if(!pointerStart)return;const dx=event.clientX-pointerStart.x,dy=event.clientY-pointerStart.y;pointerStart=null;if(Math.abs(dx)>40&&Math.abs(dx)>Math.abs(dy)){suppressPreviewClick=true;setTimeout(()=>{suppressPreviewClick=false;},350);event.preventDefault();event.stopPropagation();step(dx<0?1:-1);}});
    for(const eventName of ['pointerdown','wheel'])root.addEventListener(eventName,event=>event.stopPropagation());
    root.addEventListener('keydown',event=>{if(expanded&&event.key==='Escape'){event.preventDefault();if(panel)setPanel(null);else close();}event.stopPropagation();});
    form.addEventListener('submit',async event=>{
        event.preventDefault();event.stopPropagation();if(sending||input.disabled||!input.value.trim()||!form.reportValidity())return;
        const key=scopedKey(),snapshot=context(),version=generation,d=draft(),text=input.value.trim();if(!d.receipt)d.receipt=crypto.randomUUID();sending=true;error='';draw();
        try {await api('community/action',{...snapshot,action:'chat.send',message:text,reply_to_id:d.reply?Number(d.reply.id):undefined,mention_ids:d.mentions.map(p=>p.player_id),request_id:d.receipt});d.text='';d.receipt=null;d.reply=null;d.mentions=[];if(version===generation&&key===scopedKey()){input.value='';stamp='';await load();}}
        catch(problem){if(version===generation&&key===scopedKey())error=problem.message||t('action_failed');}
        finally{sending=false;draw();if(version===generation&&key===scopedKey()&&visible&&expanded)input.focus({preventScroll:true});}
    });
    document.addEventListener('visibilitychange',()=>{if(canRead())load();else clearTimeout(timer);});
    function update(show) {
        const nextWorld=Number(getState()?.city?.world_id||0),changed=nextWorld!==world;
        if(changed){generation++;world=nextWorld;data=null;privateTarget=0;privateTabs.clear();history.clear();scrollViews.clear();privateStamp='';renderedKey='';readSent.clear();panel=null;stamp='';error='';input.value=draft().text;}
        const opening=!visible&&show;visible=Boolean(show&&world);root.hidden=!visible;document.body.classList.toggle('has-world-chat',visible);
        if(!visible){expanded=false;document.body.classList.remove('world-chat-open');clearTimeout(timer);}else{draw();if(opening||changed)load();}
    }
    function openPrivate(playerId,playerName) {
        const id=Number(playerId);if(!Number.isInteger(id)||id<1)return;const known=(data?.conversations||[]).some(p=>Number(p.player_id)===id);privateTabs.set(id,privateTabs.get(id)||{player_id:id,username:String(playerName||t('player',{id})),unread:0});select('private',id);open();
        const key=scopedKey(),version=generation;if(!known&&!openingConversations.has(key)){openingConversations.add(key);api('community/social-action',{...context(),action:'conversation.open',request_id:crypto.randomUUID()}).then(()=>{if(version===generation&&key===scopedKey())load();}).catch(problem=>{if(version===generation&&key===scopedKey()){error=problem.message||t('action_failed');draw();}}).finally(()=>openingConversations.delete(key));}
    }
    const openChannel = next => {select(next);open();};
    return {update,open,close,openPrivate,openChannel,refresh:load,syncBadge};
};

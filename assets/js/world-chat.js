/* Compact two-message preview with a full, touch-first chat window. */
window.ConquerWorldChat = function (ctx) {
    'use strict';
    const {api, esc, date, getState, navigate, openSharedReport, openSharedLocation} = ctx, base = ctx.base || '';
    const root = document.querySelector('#world-chat');
    if (!root) return {update() {}, open() {}, syncBadge() {}};
    const names = {world: 'Weltchat', alliance: 'Allianzchat', private: 'Privatchat'};
    const order = ['world', 'alliance', 'private'], privateTabs = new Map(), drafts = new Map();
    const seen = {world: null, alliance: null}, unread = {world: 0, alliance: 0};
    let world = 0, channel = 'world', privateTarget = 0, data = null;
    let visible = false, expanded = false, loading = false, sending = false;
    let timer, error = '', stamp = '', pointerStart = null, suppressPreviewClick = false;

    root.innerHTML = `<button type="button" class="world-chat-preview" data-chat-open aria-label="Chat öffnen">
        <span class="world-chat-preview-channel"><span class="world-chat-preview-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M5 5.5h14v10H9l-4 3v-13Z"/></svg></span><strong data-chat-preview-channel>Weltchat</strong><small>Chat öffnen</small></span>
        <span class="world-chat-preview-messages" data-chat-preview-messages></span>
        <span class="world-chat-preview-arrow" aria-hidden="true"><span>›</span></span>
    </button>
    <section class="world-chat-window" aria-label="Chatfenster" hidden>
        <header class="world-chat-window-heading"><button type="button" class="world-chat-back" data-chat-close aria-label="Chat schließen">‹</button><div class="world-chat-heading-copy"><h2>Chat</h2><small data-chat-heading-channel>Weltchat</small></div><button type="button" class="world-chat-mute" data-chat-mute aria-pressed="false">🔔 Aktiv</button><button type="button" class="world-chat-close" data-chat-close aria-label="Chat schließen">×</button></header>
        <nav class="world-chat-tabs" aria-label="Chatkanal">${order.map(key=>`<button type="button" data-chat-channel="${key}" aria-pressed="${key==='world'}">${names[key]}<span class="world-chat-badge" hidden></span></button>`).join('')}</nav>
        <div class="world-chat-private-list" data-chat-private-list hidden></div>
        <div id="world-chat-body"><div class="world-chat-log" role="log" aria-live="polite" aria-relevant="additions text"></div><p class="world-chat-error" role="status" hidden></p>
        <form class="world-chat-compose"><label class="world-chat-label" for="world-chat-message">Nachricht im Weltchat</label><input id="world-chat-message" name="message" maxlength="200" autocomplete="off" required placeholder="Nachricht an die Welt …"><button type="submit">Senden</button></form></div>
    </section>`;
    const preview = root.querySelector('.world-chat-preview'), previewMessages = root.querySelector('[data-chat-preview-messages]');
    const log = root.querySelector('.world-chat-log'), form = root.querySelector('form'), input = form.elements.message;
    const errorBox = root.querySelector('.world-chat-error'), privateList = root.querySelector('[data-chat-private-list]');
    const storageKey = key => `conquer.chat.muted.${world}.${key}`;
    const muted = key => {try{return localStorage.getItem(storageKey(key)) === '1';}catch{return false;}};
    const setMuted = (key,value) => {try{localStorage.setItem(storageKey(key),value?'1':'0');}catch{}};
    const activeKey = () => channel === 'private' && privateTarget ? `private:${privateTarget}` : channel;
    const rowsFor = key => data?.[(key === 'private' ? 'private' : key) + '_chat'] || [];
    const draft = () => {const key=activeKey();if(!drafts.has(key))drafts.set(key,{text:'',receipt:null});return drafts.get(key);};
    const canRead = () => visible && !document.hidden;
    const reportLink = message => Number(message.shared_report_id)>0?`<button type="button" class="world-chat-report" data-chat-report="${Number(message.shared_report_id)}" aria-label="Geteilten Kampfbericht ansehen"><span aria-hidden="true">⚔</span> Bericht ansehen</button>`:'';
    const sharedLocation=message=>{const match=String(message?.message||'').match(/· Welt (\d+) · X (\d+) \/ Y (\d+)$/);if(!match)return null;const location={world:Number(match[1]),x:Number(match[2]),y:Number(match[3])};return location.world>0&&location.x>=0&&location.x<=255&&location.y>=0&&location.y<=255?location:null;};
    const monsterIcon=(message,location)=>{const monster=(getState()?.monsters||[]).find(entry=>Number(entry.coord_x)===location.x&&Number(entry.coord_y)===location.y),definition=monster?.definition||{},name=String(definition.name||message?.message||''),art=/^(?:monsters\/)?[a-z0-9-]+$/.test(definition.art||'')?definition.art:/skeleton|skelett/i.test(name)?'skeleton':/golem/i.test(name)?'golem':'orc';return `${base}/assets/art/${art}.png`;};
    const locationLink=message=>{const location=sharedLocation(message);return location?`<button type="button" class="world-chat-location" data-chat-location data-world="${location.world}" data-x="${location.x}" data-y="${location.y}" aria-label="Geteiltes Ziel bei X ${location.x}, Y ${location.y} auf der Weltkarte zeigen"><img src="${esc(monsterIcon(message,location))}" alt=""><span><b>Monsterziel</b><small>Auf Weltkarte zeigen</small></span><strong>X ${location.x} / Y ${location.y}</strong></button>`:'';};
    const schedule = () => {clearTimeout(timer);if(canRead())timer=setTimeout(load,10000);};
    const totalUnread = () => Object.entries(unread).reduce((sum,[key,count])=>sum+(muted(key)?0:Number(count)||0),0);

    function syncBadge() {
        const badge=document.querySelector('#navigation [data-id="chat"] .chat-dock-badge');if(!badge)return;
        const count=totalUnread();badge.hidden=!count;badge.textContent=count>99?'99+':String(count);
        badge.parentElement.setAttribute('aria-label',`Chat öffnen${count?`, ${count} ungelesene ${count===1?'Nachricht':'Nachrichten'}`:''}`);
    }
    function messageHtml(message,compact=false) {
        const tag=channel==='world'&&message.alliance_tag?`[${esc(message.alliance_tag)}] `:'';
        const avatar=['knight','archer','rider'].includes(message.avatar)?message.avatar:'knight',own=Number(message.player_id)===Number(data?.player_id);
        if(compact)return `<span class="world-chat-preview-message"><img src="${base}/assets/art/${avatar}.png" alt=""><span class="world-chat-preview-copy"><strong>${tag}${esc(message.username)}</strong><span>${esc(message.message)}</span></span></span>`;
        return `<article class="world-chat-message ${own?'mine':''}"><img class="world-chat-avatar" src="${base}/assets/art/${avatar}.png" alt="Profilbild von ${esc(message.username)}"><div class="world-chat-message-content"><header><strong>${tag}${esc(message.username)}</strong><time title="${esc(new Date(date(message.created_at)).toLocaleString('de-DE'))}">${esc(new Date(date(message.created_at)).toLocaleTimeString('de-DE',{hour:'2-digit',minute:'2-digit'}))}</time></header><div class="world-chat-bubble"><p>${esc(message.message)}</p>${locationLink(message)}${reportLink(message)}</div></div></article>`;
    }
    function drawPreview() {
        root.querySelector('[data-chat-preview-channel]').textContent=names[channel];
        const rows=rowsFor(channel).slice(-2);
        previewMessages.innerHTML=rows.length?rows.map(row=>messageHtml(row,true)).join(''):`<span class="world-chat-preview-empty">${channel==='private'&&!privateTarget?'Privaten Chat auswählen':'Noch keine Nachrichten'}</span>`;
        preview.setAttribute('aria-label',`${names[channel]} öffnen. Nach links oder rechts wischen, um den Kanal zu wechseln.`);
    }
    function drawPrivateList() {
        if(channel!=='private'){privateList.hidden=true;return;}privateList.hidden=false;
        const entries=[...privateTabs.entries()];
        privateList.innerHTML=entries.length?entries.map(([id,name])=>`<button type="button" data-chat-private="${id}" aria-pressed="${id===privateTarget}"><span>${esc(name)}</span>${unread[`private:${id}`]?`<b>${unread[`private:${id}`]}</b>`:''}<small>${muted(`private:${id}`)?'🔕 Stumm':'🔔 Aktiv'}</small></button>`).join(''):'<p>Öffne einen Spieler über Profil, Allianz oder Post, um einen privaten Chat zu beginnen.</p>';
    }
    function draw() {
        root.classList.toggle('is-open',expanded);root.querySelector('.world-chat-window').hidden=!expanded;preview.hidden=expanded;
        document.body.classList.toggle('world-chat-open',expanded&&visible);drawPreview();drawPrivateList();syncBadge();
        root.querySelector('[data-chat-heading-channel]').textContent=channel==='private'&&data?.private_player?.username?data.private_player.username:names[channel];
        for(const button of root.querySelectorAll('[data-chat-channel]')){
            const key=button.dataset.chatChannel;button.setAttribute('aria-pressed',String(key===channel));
            const badge=button.querySelector('.world-chat-badge'),count=key==='private'?[...privateTabs.keys()].reduce((sum,id)=>sum+(unread[`private:${id}`]||0),0):(unread[key]||0);
            badge.hidden=!count;badge.textContent=count?(count>49?'50+':String(count)):'';
        }
        const key=activeKey(),muteButton=root.querySelector('[data-chat-mute]'),isMuted=muted(key);
        muteButton.setAttribute('aria-pressed',String(isMuted));muteButton.textContent=isMuted?'🔕 Stumm':'🔔 Aktiv';
        const allowed=Boolean(data&&(channel==='world'||channel==='alliance'&&data.alliance||channel==='private'&&privateTarget&&data.private_player));
        input.disabled=!allowed||sending;form.querySelector('button').disabled=!allowed||sending;
        input.placeholder=channel==='world'?'Nachricht an die Welt …':channel==='alliance'?'Nachricht an deine Allianz …':privateTarget?`Nachricht an ${privateTabs.get(privateTarget)||'Privat'} …`:'Privaten Chat auswählen …';
        root.querySelector('label').textContent=`Nachricht im ${names[channel]}`;log.setAttribute('aria-label',channel==='private'&&privateTarget?`Privater Chat mit ${privateTabs.get(privateTarget)||'Spieler'}`:names[channel]);
        errorBox.textContent=error;errorBox.hidden=!error;
        const rows=rowsFor(channel),nextStamp=JSON.stringify([world,channel,privateTarget,Boolean(data),data?.alliance?.id,rows]);if(nextStamp===stamp)return;
        const follow=!stamp||log.scrollHeight-log.clientHeight-log.scrollTop<55,scroll=log.scrollTop;stamp=nextStamp;
        log.innerHTML=!data?'<p class="world-chat-empty">Nachrichten werden geladen …</p>':!allowed?`<p class="world-chat-empty">${channel==='alliance'?'Tritt einer Allianz bei, um den Allianzchat zu verwenden.':channel==='private'?'Wähle oben eine private Unterhaltung aus.':'Dieser Chat ist nicht verfügbar.'}</p>`:rows.length?rows.map(row=>messageHtml(row)).join(''):`<p class="world-chat-empty">Im ${names[channel]} gibt es noch keine Nachrichten.</p>`;
        log.scrollTop=follow?log.scrollHeight:scroll;
    }
    async function load() {
        if(!canRead()||loading)return;const requestedWorld=world,requestedChannel=channel,requestedTarget=channel==='private'?privateTarget:0;loading=true;
        try{
            const next=await api('community/chat?world_id='+requestedWorld+(requestedTarget?'&player_id='+requestedTarget:''));
            if(requestedWorld!==world||requestedChannel!==channel||requestedTarget!==(channel==='private'?privateTarget:0)||Number(next.world_id)!==world)return;
            for(const key of ['world','alliance']){const rows=next[key+'_chat']||[];if(key==='alliance'&&data?.alliance?.id!==next.alliance?.id){seen[key]=null;unread[key]=0;}if(seen[key]!==null)unread[key]+=rows.filter(row=>Number(row.id)>seen[key]&&Number(row.player_id)!==Number(next.player_id)).length;seen[key]=Math.max(seen[key]||0,...rows.map(row=>Number(row.id)));}
            if(requestedTarget){const key=`private:${requestedTarget}`,rows=next.private_chat||[];if(seen[key]!=null)unread[key]=(unread[key]||0)+rows.filter(row=>Number(row.id)>seen[key]&&Number(row.player_id)!==Number(next.player_id)).length;seen[key]=Math.max(seen[key]||0,...rows.map(row=>Number(row.id)));}
            data=next;error='';if(expanded)unread[activeKey()]=0;stamp='';draw();
        }catch(problem){if(requestedWorld===world){error=problem.message;draw();}}
        finally{loading=false;if((requestedWorld!==world||requestedChannel!==channel||requestedTarget!==(channel==='private'?privateTarget:0))&&canRead())load();else schedule();}
    }
    function select(next) {if(!order.includes(next))return;channel=next;if(channel==='private'&&privateTarget&&Number(data?.private_player?.id)!==privateTarget)data=null;unread[activeKey()]=0;input.value=draft().text;error='';stamp='';draw();load();}
    function step(direction) {const index=order.indexOf(channel);select(order[(index+direction+order.length)%order.length]);}
    function open() {if(!visible)return;expanded=true;unread[activeKey()]=0;stamp='';draw();load();setTimeout(()=>root.querySelector(`[data-chat-channel="${channel}"]`)?.focus({preventScroll:true}),0);}
    function close() {expanded=false;draw();document.querySelector('#navigation [data-id="chat"]')?.focus({preventScroll:true});}

    input.addEventListener('input',()=>{const d=draft();d.text=input.value;d.receipt=null;});
    root.addEventListener('click',event=>{
        const location=event.target.closest('[data-chat-location]');if(location){event.stopPropagation();if(!openSharedLocation||location.disabled)return;location.disabled=true;close();Promise.resolve(openSharedLocation({world:Number(location.dataset.world),x:Number(location.dataset.x),y:Number(location.dataset.y)})).catch(problem=>{error=problem.message||'Das Ziel konnte nicht geöffnet werden.';draw();});return;}
        const report=event.target.closest('[data-chat-report]');if(report){event.stopPropagation();if(!openSharedReport||report.disabled)return;report.disabled=true;Promise.resolve(openSharedReport(Number(report.dataset.chatReport))).catch(problem=>{error=problem.message||'Der Bericht konnte nicht geöffnet werden.';draw();}).finally(()=>{if(report.isConnected)report.disabled=false;});return;}
        if(event.target.closest('[data-chat-open]')){if(suppressPreviewClick){suppressPreviewClick=false;return;}open();return;}if(event.target.closest('[data-chat-close]')){close();return;}
        const tab=event.target.closest('[data-chat-channel]');if(tab){select(tab.dataset.chatChannel);return;}
        const privateButton=event.target.closest('[data-chat-private]');if(privateButton){privateTarget=Number(privateButton.dataset.chatPrivate);channel='private';data=null;stamp='';input.value=draft().text;draw();load();return;}
        if(event.target.closest('[data-chat-mute]')){const key=activeKey();setMuted(key,!muted(key));draw();return;}if(event.target.closest('[data-chat-join]'))navigate('alliance');
    });
    preview.addEventListener('pointerdown',event=>{pointerStart={x:event.clientX,y:event.clientY};});
    preview.addEventListener('pointerup',event=>{if(!pointerStart)return;const dx=event.clientX-pointerStart.x,dy=event.clientY-pointerStart.y;pointerStart=null;if(Math.abs(dx)>40&&Math.abs(dx)>Math.abs(dy)){suppressPreviewClick=true;setTimeout(()=>{suppressPreviewClick=false;},350);event.preventDefault();event.stopPropagation();step(dx<0?1:-1);}});
    for(const eventName of ['pointerdown','wheel'])root.addEventListener(eventName,event=>event.stopPropagation());
    root.addEventListener('keydown',event=>{if(expanded&&event.key==='Escape'){event.preventDefault();close();}event.stopPropagation();});
    form.addEventListener('submit',async event=>{
        event.preventDefault();event.stopPropagation();if(sending||input.disabled||!input.value.trim()||!form.reportValidity())return;
        const requestedWorld=world,selected=channel,target=privateTarget,d=draft(),text=input.value.trim();if(!d.receipt)d.receipt=crypto.randomUUID();sending=true;error='';draw();
        try{await api('community/action',{action:'chat.send',channel:selected,...(selected==='private'?{player_id:target}:{}),message:text,world_id:requestedWorld,expected_world_id:requestedWorld,request_id:d.receipt});d.text='';d.receipt=null;if(requestedWorld===world&&selected===channel){input.value='';stamp='';}if(requestedWorld===world)await load();}
        catch(problem){if(requestedWorld===world)error=problem.message;}finally{sending=false;draw();if(requestedWorld===world&&selected===channel&&visible&&expanded)input.focus({preventScroll:true});}
    });
    document.addEventListener('visibilitychange',()=>{if(canRead())load();else clearTimeout(timer);});
    function update(show) {
        const nextWorld=Number(getState()?.city?.world_id||0),changed=nextWorld!==world;
        if(changed){world=nextWorld;data=null;stamp='';error='';for(const key of Object.keys(seen)){seen[key]=null;unread[key]=0;}input.value=draft().text;}
        const opening=!visible&&show;visible=Boolean(show&&world);root.hidden=!visible;document.body.classList.toggle('has-world-chat',visible);if(!visible){expanded=false;document.body.classList.remove('world-chat-open');clearTimeout(timer);}else{draw();if(opening||changed)load();}
    }
    function openPrivate(playerId,playerName) {
        const id=Number(playerId);if(!Number.isInteger(id)||id<1)return;privateTabs.set(id,String(playerName||`Spieler ${id}`));privateTarget=id;channel='private';seen[`private:${id}`]??=null;unread[`private:${id}`]??=0;data=null;stamp='';error='';input.value=draft().text;open();
    }
    return {update,open,close,openPrivate,syncBadge};
};

(() => {
    'use strict';
    const base = window.CONQUER_BASE;
    const $ = selector => document.querySelector(selector);
    const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const fmt = n => Math.floor(Number(n) || 0).toLocaleString('de-DE');
    const date = value => { if (!value) return Date.now(); const raw=String(value).replace(' ','T'); return Date.parse(/[zZ]|[+-]\d\d:\d\d$/.test(raw)?raw:raw+'Z'); };
    const duration = n => { const seconds=Math.max(0,Math.ceil(Number(n)||0));return seconds<60?`${seconds} Sek.`:seconds<3600?`${Math.floor(seconds/60)} Min. ${seconds%60} Sek.`:`${Math.floor(seconds/3600)} Std. ${Math.floor(seconds%3600/60)} Min.`; };
    const labels = {watch_tower:'Wachturm',castle:'Burg',wall:'Stadtmauer',farm:'Bauernhof',lumber_camp:'Sägewerk',quarry:'Steinbruch',gold_mine:'Goldmine',storage:'Lagerhaus',treasure_house:'Schatzkammer',barrack:'Kaserne',archery_range:'Schützenlager',stable:'Reiterhof',hospital:'Hospital',academy:'Akademie',trading_post:'Handelsposten',hall_of_alliance:'Allianzhalle'};
    const symbols = {watch_tower:'⌖',castle:'♜',wall:'▥',farm:'🌾',lumber_camp:'🪵',quarry:'🪨',gold_mine:'🪙',storage:'📦',treasure_house:'💎',barrack:'⚔',archery_range:'🏹',stable:'♞',hospital:'✚',academy:'✦',trading_post:'⚖',hall_of_alliance:'⚑'};
    const resourceIcons = {food:'🌾',lumber:'🪵',stone:'🪨',gold:'🪙'};
    const resourceNames = {food:'Nahrung',lumber:'Holz',stone:'Stein',gold:'Gold'};
    const descriptions = {watch_tower:'Ein Aussichtspunkt über deiner Stadt. Von hier gelangst du zur Weltkarte.',castle:'Das Herz deines Reichs. Eine größere Burg erlaubt höhere Gebäudestufen.',farm:'Versorgt deine Truppen mit Nahrung. Produziert auch, während du offline bist.',lumber_camp:'Holz für neue Gebäude und die Ausbildung deiner Armee.',quarry:'Stein macht aus einem kleinen Dorf eine standhafte Festung.',gold_mine:'Gold finanziert deine Forschung und wertvolle Verbesserungen.',barrack:'Bildet standhafte Infanterie für die vorderste Reihe aus.',archery_range:'Hier üben deine Bogenschützen Präzision und Fernkampf.',stable:'Hier trainieren Reiter und ihre treuen Pferde für den nächsten Einsatz.',academy:'Wissen ist Macht. Erforsche dauerhafte Verbesserungen für dein Reich.',wall:'Schützt deine Stadt und ist Voraussetzung für den nächsten Burgausbau.',storage:'Erweitert den Platz für deine produzierten Ressourcen.',hospital:'Ein Zufluchtsort für verwundete Truppen.',treasure_house:'Bewahrt die Schätze deines Königreichs.',trading_post:'Der Treffpunkt für Händler und Reisende.',hall_of_alliance:'Ein Ort für gemeinsame Pläne und Verbündete.'};
    const researchNames = {food_production:'Reiche Ernte',lumber_production:'Geschickte Holzfäller',wood_production:'Geschickte Holzfäller',stone_production:'Bessere Werkzeuge',gold_production:'Goldene Zeiten',infantry_hp:'Standhafte Infanterie',infantry_atk:'Geschärfte Klingen',infantry_def:'Starke Schilde',ranged_def:'Leichte Rüstungen',ranged_atk:'Präzise Pfeile',cavalry_def:'Gepanzerte Reiter',cavalry_atk:'Mächtiger Ansturm',ranged_hp:'Ausdauer der Schützen',cavalry_hp:'Starke Reittiere',construction_speed:'Flotte Baumeister',research_speed:'Wissensdurst',gathering_speed:'Fleißige Sammler'};
    const monsterArt = m => /^(?:monsters\/)?[a-z0-9-]+$/.test(m.definition?.art||'') ? m.definition.art : /skeleton/i.test(m.definition?.name) ? 'skeleton' : /golem/i.test(m.definition?.name) ? 'golem' : 'orc';
    const iconPaths = {treasures:'M3 10V7l3-4h12l3 4v13H3V10Zm0 0h18M10 8h4v5h-4V8Z',city:'M3 21V9h5V4l4-2 4 2v5h5v12H3Zm6 0v-6h6v6M8 9h8M5 12v2m14-2v2',world:'m3 5 6-2 6 2 6-2v16l-6 2-6-2-6 2V5Zm6-2v16m6-14v16',army:'m4 3 7 7-2 2-7-7 2-2Zm16 0-7 7 2 2 7-7-2-2ZM8 14l-5 5m13-5 5 5M5 13l6 6m2 0 6-6',research:'M3 4h7l2 2 2-2h7v15h-7l-2 2-2-2H3V4Zm9 2v15',reports:'M5 3h14v18H5V3Zm3 5h8m-8 4h8m-8 4h5'};
    const navs = {worlds:'Weltenauswahl',community:'Gemeinschaft',defense:'Verteidigung',events:'Weltereignisse',mastery:'Hunter-Talente',account:'Kontosicherheit',city:'Königreich',expeditions:'Feldzüge',dungeons:'Dungeons',world:'Weltkarte',land:'Landübersicht',alliance:'Allianz',army:'Truppen',research:'Forschung',quests:'Aufgaben',inventory:'Inventar',treasures:'Schatzkammer',reports:'Post',profile:'Profil',rankings:'Rangliste',arena:'Arena',market:'Shop',settings:'Optionen',help:'Anfangsguide',bugreport:'Bug melden'};
    const navIcons = {dungeons:'⚔',expeditions:'♜',land:'▦',alliance:'⚑',quests:'✓',inventory:'▣',profile:'♛',rankings:'♕',arena:'⚒',market:'⚖',settings:'⚙',help:'?',bugreport:'!'};
    const svg = key => `<span class="nav-emblem">${iconPaths[key] ? `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="${iconPaths[key]}"/></svg>` : `<span class="nav-symbol" aria-hidden="true">${navIcons[key] || '✦'}</span>`}</span>`;
    let state, kingdom, expeditions, market, current = Object.hasOwn(navs, location.hash.slice(1)) ? location.hash.slice(1) : 'city', filter = 'monsters', busy = false, polling = null, offset = 0, toastTimer, lastSignature = '', dialogTrigger=null, teleportSelection=null;
    const loadErrors = {};
    let armyTier=1;
    let playfield=current==='world'?'world':'city';
    const playfieldHost=$('#content'),panelHost=$('#panel-content'),panelDialog=$('#panel-dialog');
    const toastElement=$('#toast');
    let panelTrigger=null;
    let sceneHosts=null;
    let sceneTransitionToken=0,sceneTransitionTimer=0;
    const isPlayfield=tab=>tab==='city'||tab==='world';
    const now = () => Date.now() + offset;
    const commands=window.ConquerCommandReceipts({scope:()=>`${base}:${state?.city.player_id||state?.player.name}:${state?.city.world_id||window.CONQUER_WORLD||1}`});
    function commandRecovery(){
        if(!commands.pending())return;
        openDialog('<h2>Auftrag prüfen</h2><p>Die Antwort auf deinen letzten Auftrag fehlt. Setze denselben Auftrag sicher fort, bevor du einen weiteren startest. Bereits ausgeführte Aufträge werden nur bestätigt; ein noch nicht ausgeführter Auftrag wird dabei gestartet. Truppen werden nicht doppelt abgezogen.</p><button type="button" class="button" data-action="command-retry">Auftrag sicher fortsetzen</button>',{focusHeading:true});
    }
    function placeToast() { const host=$('#game-dialog').open?$('#game-dialog'):panelDialog.open?panelDialog:document.body;if(toastElement.parentElement!==host)host.append(toastElement); }
    function toast(message) { placeToast();toastElement.textContent=message;toastElement.classList.add('visible');clearTimeout(toastTimer);toastTimer=setTimeout(()=>toastElement.classList.remove('visible'),3200); }
    async function api(path, payload) {
        let response;
        const world=Number(state?.city.world_id||window.CONQUER_WORLD||1);
        if(payload)payload={...payload,expected_world_id:payload.expected_world_id??world};
        let command=null;
        if(payload){try{command=commands.prepare(path,payload);if(command)payload=command.body;}catch(e){commandRecovery();throw e;}}
        try { response = await fetch(base + '/api/' + path, {method:payload ? 'POST':'GET', credentials:'same-origin',cache:'no-store',signal:AbortSignal.timeout(20000),headers:payload ? {'Content-Type':'application/json','X-CSRF-Token':state?.player.csrf ?? '','X-World-ID':String(world)}:{'X-World-ID':String(world)},body:payload ? JSON.stringify(payload):undefined}); }
        catch(e){if(command)commandRecovery();throw new Error(e.name==='TimeoutError'?'Die Verbindung braucht länger als erwartet. Bitte prüfe deinen aktuellen Spielstand, bevor du eine Aktion wiederholst.':'Keine Verbindung zum Königreich. Bitte versuche es erneut.');}
        let body; try { body = await response.json(); } catch { if(command)commandRecovery();throw new Error('Der Server ist gerade nicht erreichbar. Bitte versuche es erneut.'); }
        if (!body.ok || !response.ok) { if(command){if(response.headers.get('X-Operation-Rejected')==='1'){commands.complete(command);if($('#game-dialog [data-action="command-retry"]'))$('#game-dialog').close();}else commandRecovery();}if (response.status === 401) location.href = base + '/'; if(body.error?.code==='WORLD_CHANGED'){location.reload();} const error=new Error(body.message || body.error?.message || (typeof body.error==='string'?body.error:null) || 'Aktion fehlgeschlagen.');error.code=body.error?.code||null;error.definite=response.status>=400&&response.status<500&&response.status!==408;throw error; }
        if(command)commands.complete(command);
        return body.data;
    }
    async function refresh(renderPage = true) {
        if (polling) return polling;
        polling = (async()=>{
            try {
                const center=playfield==='world'?window.ConquerWorld.getCenter():null;
                const results=await Promise.allSettled([api('game/state'+(center?`?map_x=${center.x}&map_y=${center.y}&map_radius=${center.radius||30}`:'')),api('kingdom/state'),api('expeditions/state'),api('market/state')]);
                if(results[0].status==='rejected')throw results[0].reason;
                state=results[0].value;offset=state.server_time*1000-Date.now();
                state.research_defs.forEach(node=>{researchNames[node.code]=window.ConquerResearch.title(node);});
                ['kingdom','expeditions','market'].forEach((name,i)=>{const r=results[i+1];if(!r)return;if(r.status==='fulfilled'){if(name==='kingdom')kingdom=r.value;if(name==='expeditions')expeditions=r.value;if(name==='market')market=r.value;delete loadErrors[name];}else{loadErrors[name]=r.reason.message;}});
                $('#save-state').textContent=Object.keys(loadErrors).length?'Ein Bereich ist derzeit nicht erreichbar':'Fortschritt gespeichert';
                $('#save-state').classList.toggle('error',Object.keys(loadErrors).length>0);
                document.body.classList.toggle('reduced-motion',Boolean(kingdom?.settings?.reduced_motion));
                renderHud();
                mailboxPanel.refresh();
                const signature=JSON.stringify([state.buildings,state.troops,state.build_queue,state.troop_queue,state.research,state.research_queue,state.research_duration_factor,state.research_defs.map(n=>canAfford(n.levels.find(l=>l.level===Number(state.research[n.code]||0)+1)?.resources||{})),state.marches,state.reports,state.monsters,state.charms,state.nodes,state.players,state.congress,state.shrines,state.land_progression,state.map_center,kingdom?.profile,kingdom?.march_skins,kingdom?.name_frames,kingdom?.theme_bundles,kingdom?.skin_bundles,kingdom?.alliance,kingdom?.inventory,kingdom?.quests,kingdom?.hospital,kingdom?.treasures,[kingdom?.trading?.rotation,kingdom?.trading?.offers,kingdom?.trading?.vip],[kingdom?.chests?.free_silver_remaining,kingdom?.chests?.free_silver_available,kingdom?.chests?.free_gold_available],kingdom?.rankings,kingdom?.arena,expeditions?.expeditions,market]);
                const editing=current!=='world'&&($('#content').dataset.dirty==='true'||($('#content').contains(document.activeElement)&&document.activeElement.matches('input,textarea,select')));
                if(renderPage&&!editing&&!$('#game-dialog').open&&signature!==lastSignature){render();lastSignature=signature;}
                panels.updateHospital();
                trainingPanel.update();
                inventoryOverview.update();
                monsterReports.update(state.reports);
                if(current==='help')beginnerGuide.render();
                beginnerGuide.maybeWelcome(current);
            } catch(e) {
                $('#save-state').textContent='Verbindung unterbrochen';$('#save-state').classList.add('error');
                if(!state)$('#content').innerHTML=`<div class="empty"><span class="empty-icon">♜</span><h3>Dein Reich ist kurz außer Reichweite.</h3><p>${esc(e.message)}</p><button class="button" data-action="retry">Erneut versuchen</button></div>`;
            }
        })();
        try{return await polling;}finally{polling=null;}
    }
    async function action(path, payload, message) {
        if (busy) return;
        const receipt=path==='kingdom/action'&&rewards.tracked(payload);
        if(receipt){payload=rewards.prepare(payload);if(!payload)return null;}
        busy=true; const buttons=[...document.querySelectorAll('#game-dialog button,#game-dialog input,#game-dialog select,#game-dialog textarea,#content .button')].filter(el=>!el.disabled); buttons.forEach(b=>b.disabled=true);
        try {
            if(polling)await polling;
            const result=await api(path,payload);if(!receipt)$('#game-dialog').close();delete $('#content').dataset.dirty;
            if(!receipt||!result?.result?.drops?.length)toast(result?.message||message||'Gespeichert.');
            if(path==='kingdom/action'&&result?.state)kingdom=result.state;if(path==='expeditions/action'&&result?.state)expeditions=result.state;
            await refresh(false);render();lastSignature='';$('#city-frame')?.contentWindow?.postMessage({type:'conquer:refresh'},location.origin);
            if(receipt)rewards.success(result,payload);
            return result;
        } catch(e){if(receipt)rewards.failure(e);toast(e.message);return null;}
        finally{busy=false;buttons.forEach(b=>{if(b.isConnected)b.disabled=false;});}
    }
    function openDialog(html,{focusHeading=false}={}) {
        const dialog=$('#game-dialog'),content=$('#dialog-content');
        if(!dialog.open)dialogTrigger=document.activeElement;
        delete dialog.dataset.building;delete dialog.dataset.buildingRecommendation;delete dialog.dataset.march;delete dialog.dataset.research;
        dialog.classList.remove('march-dialog','menu-dialog','shop-hub-dialog','has-popup-heading','combat-report-dialog','monster-report-dialog','reward-dialog','levelup-dialog-window');
        delete dialog.dataset.monsterReport;
        dialog.querySelector(':scope > .popup-heading')?.remove();
        content.innerHTML=html;
        const heading=content.querySelector('h2');
        if(heading){
            heading.id='dialog-title';heading.tabIndex=-1;dialog.setAttribute('aria-labelledby','dialog-title');
            if(!content.querySelector('.march-command')){
                if(heading.previousElementSibling?.classList.contains('card-icon'))heading.previousElementSibling.remove();
                const header=document.createElement('header');header.className='popup-heading';header.append(heading);
                dialog.insertBefore(header,content);dialog.classList.add('has-popup-heading');
            }
        }else dialog.removeAttribute('aria-labelledby');
        if(!dialog.open)dialog.showModal();placeToast();sendPreferences();dialog.scrollTop=0;
        const firstInput=dialog.querySelector('input:not([disabled]):not([type=radio]),select:not([disabled]),textarea:not([disabled])');
        ((focusHeading?heading:firstInput)||heading||$('.dialog-close')).focus({preventScroll:true});
    }
    function transitionScene(tab,commit) {
        const veil=$('#scene-transition');
        const reduced=document.body.classList.contains('reduced-motion')||matchMedia('(prefers-reduced-motion: reduce)').matches;
        if(!veil||reduced){commit();return;}
        const token=++sceneTransitionToken;
        clearTimeout(sceneTransitionTimer);
        veil.dataset.target=tab;
        veil.querySelector('img').src=`${base}/assets/art/hud/${tab}.svg`;
        veil.querySelector('.scene-transition-label').textContent=tab==='city'?'Zurück ins Dorf':'Auf zur Weltkarte';
        veil.classList.remove('is-covering','is-revealing');
        void veil.offsetWidth;
        veil.classList.add('is-active','is-covering');
        sceneTransitionTimer=setTimeout(()=>{
            if(token!==sceneTransitionToken)return;
            commit();
            const renderedAt=performance.now();
            let revealed=false,maxWait=0;
            const reveal=()=>{
                if(revealed||token!==sceneTransitionToken)return;
                revealed=true;clearTimeout(maxWait);
                if(token!==sceneTransitionToken)return;
                veil.classList.remove('is-covering');
                veil.classList.add('is-revealing');
                sceneTransitionTimer=setTimeout(()=>{
                    if(token!==sceneTransitionToken)return;
                    veil.classList.remove('is-active','is-revealing');
                    delete veil.dataset.target;
                },330);
            };
            const revealAfterMinimum=()=>{
                const remaining=Math.max(0,420-(performance.now()-renderedAt));
                sceneTransitionTimer=setTimeout(()=>requestAnimationFrame(()=>requestAnimationFrame(reveal)),remaining);
            };
            const frame=tab==='city'?$('#city-frame'):null;
            let frameReady=false;
            try{frameReady=Boolean(frame&&frame.contentWindow?.location.href!=='about:blank'&&frame.contentDocument?.readyState==='complete');}catch{}
            if(frame&&!frameReady){
                frame.addEventListener('load',revealAfterMinimum,{once:true});
                maxWait=setTimeout(reveal,1600);
            }else revealAfterMinimum();
        },260);
    }
    function navigate(tab,{focusTitle=true}={}) {
        if(!Object.hasOwn(navs,tab))return;
        if($('#scene-transition')?.classList.contains('is-active')&&tab===current)return;
        const changesScene=isPlayfield(tab)&&playfield!==tab;
        if(tab!=='world')teleportSelection=null;
        if($('#game-dialog').open)$('#game-dialog').close();
        if(!isPlayfield(tab)&&!panelDialog.open){const trigger=document.activeElement;panelTrigger={node:trigger,id:trigger?.id,tab:trigger?.closest('[data-id]')?.dataset.id};}
        delete $('#content').dataset.dirty;current=tab;if(isPlayfield(tab))playfield=tab;
        location.hash=tab;
        const commit=()=>{render();panelHost.scrollTop=0;if(!isPlayfield(tab)&&focusTitle)$('#page-title').focus({preventScroll:true});};
        if(changesScene)transitionScene(tab,commit);else commit();
        if(tab==='market'&&!kingdom?.trading)refresh();
    }
    function costHtml(cost,illustrated=false) { return `<div class="costs">${Object.entries(cost).filter(([k,v]) => resourceIcons[k] && v > 0).map(([k,v]) => `<span class="${state.city[k] < v ? 'insufficient':''}" title="${resourceNames[k]}">${illustrated?`<img class="research-cost-icon" src="${base}/assets/art/ui-resources/${k}.png" alt="${resourceNames[k]}">`:resourceIcons[k]} ${fmt(v)}</span>`).join('')}</div>`; }
    function canAfford(cost) { return Object.entries(cost).every(([k,v]) => !resourceIcons[k] || state.city[k] >= v); }
    function requirementResources(cost) {
        const entries=Object.entries(cost||{}).filter(([key,value])=>resourceIcons[key]&&Number(value)>0);
        if(!entries.length)return '';
        return `<section class="levelup-resource-list" aria-label="Benötigte Rohstoffe"><div class="levelup-section-title"><h3>Rohstoffe</h3><span>${entries.filter(([key,value])=>Number(state.city[key])>=Number(value)).length} / ${entries.length} bereit</span></div>${entries.map(([key,value])=>{const owned=Number(state.city[key]||0),needed=Number(value),met=owned>=needed;return `<div class="levelup-resource-row ${met?'is-ready':'is-missing'}"><span class="levelup-check" aria-hidden="true">${met?'✓':'!'}</span><img src="${base}/assets/art/ui-resources/${key}.png" alt=""><strong>${resourceNames[key]}</strong><span class="levelup-resource-values"><b>${fmt(owned)}</b><i>/</i>${fmt(needed)}</span></div>`;}).join('')}</section>`;
    }
    function levelupStat(label,current,next) {
        return `<div class="levelup-stat"><span>${esc(label)}</span><strong>${esc(current)}</strong>${next!==undefined&&next!==null?`<span class="levelup-stat-arrow" aria-hidden="true">→</span><b>${esc(next)}</b>`:''}</div>`;
    }
    function countdown(end) { return `<span data-end="${esc(end)}">${duration((date(end)-now())/1000)}</span>`; }
    function renderHud() {
        const compact=n=>kingdom?.settings?.compact_numbers||Number(n)>=1000000?Intl.NumberFormat('de-DE',{notation:'compact',maximumFractionDigits:1}).format(Number(n)||0):fmt(n);
        $('#resources').innerHTML = Object.keys(resourceIcons).map(k => `<button class="resource" data-action="resource" data-id="${k}" aria-label="${resourceNames[k]}: ${fmt(state.city[k])}, Details öffnen" title="${resourceNames[k]}: ${fmt(state.city[k])}"><span class="resource-icon" aria-hidden="true"><img src="${base}/assets/art/ui-resources/${k}.png" alt=""></span><span><strong>${compact(state.city[k])}</strong><small>${resourceNames[k]}</small></span></button>`).join('');
        $('#player-hud-name').textContent=kingdom?.profile?.display_name||state.player.name;
        $('#hud-power-value').textContent=fmt(kingdom?.profile?.power||state.city.power);
        window.ConquerNameFrames.syncSelf(kingdom?.name_frames,document,kingdom?.profile?.name_frame||kingdom?.profile?.city_skin||'default');
        $('#account-button .avatar img').src=base+'/assets/art/'+(['knight','archer','rider'].includes(kingdom?.profile?.avatar)?kingdom.profile.avatar:'knight')+'.png';
        overlay.update();trainingHud.update();vipPanel.updateHud();activeEffects.update();
        updateQuestBadge();
        mailboxPanel.badge();
    }
    function updateQuestBadge() {
        const button=$('#navigation [data-id="quests"]');if(!button)return;
        const ready=(kingdom?.quests||[]).filter(q=>q.completed&&!q.claimed).length;
        const badge=button.querySelector('.dock-badge');
        badge.textContent=fmt(ready);badge.hidden=ready===0;
        button.setAttribute('aria-label','Aufgaben öffnen'+(ready?`, ${fmt(ready)} ${ready===1?'Aufgabe':'Aufgaben'} abholbereit`:''));
    }
    function resourceDialog(resource){
        const building={food:'farm',lumber:'lumber_camp',stone:'quarry',gold:'gold_mine'}[resource];if(!building)return;
        openDialog(`<span class="card-icon">${resourceIcons[resource]}</span><h2>${resourceNames[resource]} für dein Reich</h2><p class="muted">Deine Gebäude produzieren auch während deiner Abwesenheit. Mehr Rohstoffe erhältst du durch Sammelzüge, Aufgaben, Vorratspakete und den Handelsposten.</p><div class="detail-row"><span>Aktueller Bestand</span><strong>${fmt(state.city[resource])}</strong></div><div class="detail-row"><span>Produktionslager inkl. Forschung</span><strong>${fmt(state.storage_caps?.[resource])}</strong></div><div class="detail-row"><span>Produktion pro Stunde</span><strong>${fmt(state.production_rates[building])}</strong></div><div class="detail-row"><span>${labels[building]}</span><strong>Stufe ${state.buildings[building].level}</strong></div><div class="button-row"><button class="button gold" data-action="building" data-id="${building}">Produktion ausbauen</button><button class="button secondary" data-action="dialog-tab" data-id="inventory">Vorräte öffnen</button></div>`);
    }
    function ensureSceneHosts() {
        if(sceneHosts?.root.isConnected)return sceneHosts;
        playfieldHost.replaceChildren();
        const root=document.createElement('div');root.className='playfield-scenes';
        const city=document.createElement('section');city.className='playfield-scene playfield-scene-city';city.dataset.scene='city';
        const world=document.createElement('section');world.className='playfield-scene playfield-scene-world';world.dataset.scene='world';
        root.append(city,world);playfieldHost.append(root);sceneHosts={root,city,world};
        return sceneHosts;
    }
    function activateScene(tab) {
        const hosts=ensureSceneHosts();
        for(const [name,host] of [['city',hosts.city],['world',hosts.world]]){
            const active=name===tab;host.classList.toggle('is-active',active);host.setAttribute('aria-hidden',String(!active));host.inert=!active;
        }
        return hosts;
    }
    function render() {
        if (!state) return;
        const hasPanel=!isPlayfield(current);
        // The scene stays mounted while the existing feature renderers use the active content host.
        playfieldHost.id=hasPanel?'playfield-content':'content';
        panelHost.id=hasPanel?'content':'panel-content';
        document.body.classList.toggle('world-mode',playfield==='world');
        document.body.classList.toggle('research-mode',current==='research');
        document.body.classList.toggle('focus-layout',['world','inventory','research'].includes(current));
        document.body.classList.toggle('city-mode',playfield==='city');
        document.body.classList.add('playfield-mode');
        document.body.classList.toggle('popup-mode',hasPanel);
        const focusedNav=document.activeElement?.closest('#navigation [data-id]')?.dataset.id;
        const sceneTab=playfield==='world'?'city':'world';
        const dock=[['quests','Aufgaben','quest','tab'],['inventory','Inventar','inventory','tab'],['reports','Post','reports','tab'],['chat','Chat','chat','chat-open'],['shop','Shop','shop','shop-open'],['alliance','Allianz','alliance','tab'],[sceneTab,sceneTab==='city'?'Dorf':'Welt',sceneTab,'tab']];
        $('#navigation').innerHTML=dock.map(([key,name,art,act])=>{
            const icon=art==='chat'?'<span class="dock-icon dock-glyph" aria-hidden="true">💬</span>':art==='shop'?`<img class="dock-icon" src="${base}/assets/art/items/pouch.svg" alt="">`:`<img class="dock-icon" src="${base}/assets/art/hud/${art}.svg" alt="">`;
            const badge=key==='quests'?'<span class="dock-badge" aria-hidden="true" hidden></span>':key==='chat'?'<span class="dock-badge chat-dock-badge" aria-hidden="true" hidden></span>':'';
            return `<button class="game-dock-item ${key===sceneTab?'hud-scene-switch':''} ${current===key?'current':''}" data-action="${act}" data-id="${key}" aria-label="${name} öffnen" ${current===key?'aria-current="page"':''}>${icon}<span class="dock-label">${name}</span>${badge}</button>`;
        }).join('');
        updateQuestBadge();
        mailboxPanel.badge();
        worldChat?.syncBadge();
        if(focusedNav)$('#navigation').querySelector(`[data-id="${CSS.escape(focusedNav)}"]`)?.focus({preventScroll:true});
        const inHospital=current==='army'&&panels.armyMode==='hospital';
        panelDialog.dataset.hospital=String(inHospital);
        $('#page-title').textContent=inHospital?'Hospital':navs[current];
        $('#panel-emblem').innerHTML=inHospital?'<span aria-hidden="true">✚</span>':svg(current);
        const scenes=activateScene(playfield);
        window.ConquerWorld?.setVisible(playfield==='world'&&!hasPanel);
        if(playfield==='city')renderCity(scenes.city);else renderWorld(scenes.world);
        if(current!=='land')landPanel.render(current);
        const panelScroll=panelHost.scrollTop;
        const native={army:renderArmy,research:renderResearch,reports:()=>mailboxPanel.render()};
        if(hasPanel){
            panelDialog.dataset.panel=current;
            if(!panelDialog.open){panelDialog.showModal();$('#page-title').focus({preventScroll:true});}
            if(current==='bugreport')bugReports.render();else if(current==='treasures')treasurePanel.render();else if(current==='market')tradingPanel.render();else if(current==='dungeons')dungeonPanel.render(current);else if(current==='community')communityPanel.render(current);else if(current==='defense')defensePanel.render();else if(landPanel.render(current)||worldPanel.render(current)||progressionPanel.render(current)){}else if(native[current])native[current]();else panels.render(current);
            if(current==='settings')$('#content').insertAdjacentHTML('afterbegin','<p><button class="button secondary" data-action="tab" data-id="account">Passwort & Wiederherstellung</button></p>');
        }
        if(current==='army'&&panels.armyMode!=='hospital'){$('#content > .subtabs')?.remove();$('#content').insertAdjacentHTML('afterbegin',panels.armyHeader());}
        if(playfield==='city'){const frame=$('#city-frame');if(!frame.dataset.bridgeReady){frame.dataset.bridgeReady='true';frame.addEventListener('load',sendPreferences);}sendPreferences();}
        if(hasPanel){
            panelHost.scrollTop=panelScroll;
        }else{
            if(panelDialog.open)panelDialog.close();
            panelHost.replaceChildren();
        }
        worldChat.update(!hasPanel&&isPlayfield(current));
        overlay.update();trainingHud.update();vipPanel.updateHud();
        placeToast();
    }
    function renderCity(host=playfieldHost) {
        if(!$('#city-frame'))host.innerHTML=`<div class="city-playfield"><iframe id="city-frame" class="city-frame" src="${base}/city/3d?embed=1" title="Dein Königreich – wähle ein Gebäude zum Ausbau"></iframe><button class="city-building-tool" data-action="buildings" aria-label="Gebäudeübersicht öffnen" title="Gebäude"><span aria-hidden="true">♜</span><small>Gebäude</small></button></div>`;
    }
    function compactGuide() {
        const next = state.buildings.castle.level < 2 ? ['Baue deine Burg auf Stufe 2 aus.','building','castle'] : state.trained_total < 20 ? ['Bilde deine ersten 20 Truppen aus.','tab','army'] : !state.reports.length ? ['Besiege einen Ork-Späher in deiner Nähe.','tab','world'] : !Object.keys(state.research).length ? ['Entdecke deine erste Forschung.','tab','research'] : ['Dein Reich ist bereit für neue Abenteuer.','tab','world'];
        return `<button class="compact-guide" data-action="${next[1]}" data-id="${next[2]}"><span>✦</span><span><small>DEIN NÄCHSTER SCHRITT</small><strong>${next[0]}</strong></span><span>→</span></button>`;
    }
    function queuePanel() {
        const queues = [...state.build_queue.map(q=>`${esc(labels[q.building_code])} wird ausgebaut · ${countdown(q.finishes_at)}`),...state.troop_queue.map(q=>`${q.count} Truppen in Ausbildung · ${countdown(q.finishes_at)}`),...state.research_queue.map(q=>`Forschung läuft · ${countdown(q.finishes_at)}`)];
        return queues.length ? `<div class="section-heading"><h2>Es tut sich etwas in deinem Reich</h2></div>${queues.map(q=>`<div class="activity">${q}</div>`).join('')}` : '';
    }
    function buildingFunction(code,troopCode) {
        if(['barrack','archery_range','stable'].includes(code)){trainingPanel.selectBuilding(code);if(troopCode)trainingPanel.selectTroop(Number(troopCode));panels.onClick('army-troops',{dataset:{}});return;}
        const tabs={academy:'research',hall_of_alliance:'alliance',trading_post:'market'};
        if(code==='castle'){villageMenu.open({kind:'home'});return;}
        if(code==='hospital'){panels.onClick('army-hospital',{dataset:{}});return;}
        if(code==='treasure_house'){navigate('treasures');return;}
        if(tabs[code])navigate(tabs[code]);else buildingDialog(code);
    }
    const buildingArt={watch_tower:'map/wall',castle:'map/castle',wall:'map/wall',farm:'map/farm',lumber_camp:'map/lumber',quarry:'map/quarry',gold_mine:'map/gold',trading_post:'buildings/trading_post',academy:'buildings/academy',hospital:'buildings/hospital',storage:'buildings/storage',treasure_house:'buildings/treasure_house',barrack:'buildings/barrack',archery_range:'buildings/archery_range',stable:'buildings/stable',hall_of_alliance:'buildings/hall_of_alliance'};
    const buildingImage=code=>code==='castle'&&kingdom?.profile?.city_skin&&kingdom.profile.city_skin!=='default'?window.ConquerCastleSkins.image(base,kingdom.profile.city_skin):`${base}/assets/art/buildings/${Object.hasOwn(buildingArt,code)?code:'castle'}-city-v2.png`;
    function buildingRequirements(requirements) {
        const entries=Object.entries(requirements||{}).map(([code,needed])=>({code,needed:Number(needed),current:Number(state.buildings[code]?.level||0)}));
        if(!entries.length)return '';
        const met=entries.filter(r=>r.current>=r.needed).length;
        return `<section class="research-requirements building-requirements" aria-label="Bauvoraussetzungen"><div class="research-requirements-heading"><h3>Voraussetzungen</h3><span>${met} / ${entries.length} erfüllt</span></div><div class="research-requirement-grid">${entries.map(r=>{const fulfilled=r.current>=r.needed,name=labels[r.code]||r.code;return `<button type="button" class="research-requirement ${fulfilled?'requirement-met':'requirement-missing'}" data-action="building" data-id="${esc(r.code)}" aria-label="${esc(name)}, Stufe ${r.needed} benötigt, aktuell Stufe ${r.current}. ${fulfilled?'Erfüllt':'Noch nicht erfüllt'}. Ausbaumenü öffnen"><img class="building-requirement-icon" src="${buildingImage(r.code)}" alt=""><span class="research-requirement-copy"><strong>${esc(name)}</strong><span>Benötigt: Stufe ${r.needed}</span><small><span aria-hidden="true">${fulfilled?'✓':'↑'}</span> ${fulfilled?'Erfüllt':'Ausbauen'} · aktuell Stufe ${r.current}</small></span><span class="building-requirement-arrow" aria-hidden="true">›</span></button>`;}).join('')}</div></section>`;
    }
    function buildingDialog(code,{recommended=false}={}) {
        const b = state.buildings[code], q = state.build_queue.find(q=>q.building_code===code),resource={farm:'food',lumber_camp:'lumber',quarry:'stone',gold_mine:'gold'}[code];
        const requirements = Object.entries(b.requirements).filter(([k,v]) => state.buildings[k].level < v);
        const locked = (b.item_requirements||[]).some(item=>!item.met) || requirements.length || state.build_queue.length >= (state.vip.level >= 4 ? 2 : 1) || !canAfford(b.cost) || b.level >= 30;
        const stats=[b.progression?levelupStat(b.progression.label,fmt(b.progression.current),b.level<30?fmt(b.progression.next):null):'',b.production?levelupStat('Produktionslager',fmt(state.storage_caps?.[resource])):'',b.production?levelupStat('Produktion je Stunde',fmt(b.production)):'' ].join('');
        const itemRows=(b.item_requirements||[]).map(item=>`<div class="levelup-item-row ${item.met?'is-ready':'is-missing'}"><span class="levelup-check" aria-hidden="true">${item.met?'✓':'!'}</span><strong>${esc(item.name)}</strong><span>${fmt(item.owned)} / ${fmt(item.count)}</span></div>`).join('');
        const queueBusy=!q&&state.build_queue.length?'<p class="levelup-warning">Alle Bauplätze sind gerade belegt.</p>':'';
        openDialog(`<h2>${q?'Ausbau läuft':b.level>=30?'Maximalstufe erreicht':'Stufe erhöhen'}</h2><div class="levelup-shell"><section class="levelup-overview"><div class="levelup-art"><img src="${buildingImage(code)}" alt=""><span>${esc(labels[code])}</span></div><div class="levelup-levels"><span>Stufe ${b.level}</span><i aria-hidden="true">➜</i><strong>${b.level<30?'Stufe '+(q?q.level_to:b.level+1):'Maximum'}</strong></div><p>${esc(descriptions[code])}</p><div class="levelup-stats">${stats||levelupStat('Gebäudestufe',b.level,b.level<30?b.level+1:null)}</div></section><section class="levelup-needs"><div class="levelup-section-title levelup-main-title"><h3>${q?'Aktiver Ausbau':'Voraussetzungen'}</h3><span>${recommended&&!q?'Empfohlen':''}</span></div>${q?`<div class="levelup-running"><strong>Stufe ${q.level_to} wird gebaut</strong><span>Noch ${countdown(q.finishes_at)}</span></div>`:`${requirementResources(b.cost)}${itemRows}${b.level<30?buildingRequirements(b.requirements):''}${queueBusy}`}</section><footer class="levelup-footer"><div class="levelup-time"><span>${q?'Restzeit':'Bauzeit'}</span><strong>${q?countdown(q.finishes_at):duration(b.seconds)}</strong></div>${q?`<button class="button levelup-secondary" data-action="cancel-build" data-id="${q.id}">Abbrechen</button><button class="button levelup-primary" data-action="queue-speedups" data-type="building" data-id="${q.id}">Beschleunigen</button>`:`<button class="button levelup-primary" data-action="upgrade" data-id="${code}" ${locked?'disabled':''}>${b.level>=30?'Vollständig ausgebaut':'Ausbau auf Stufe '+(b.level+1)+' starten'}</button>`}</footer></div>${['barrack','archery_range','stable','academy'].includes(code)?`<button class="levelup-link" data-action="${code==='academy'?'dialog-tab':'training-building'}" data-id="${code==='academy'?'research':code}">${code==='academy'?'Zur Forschung':'Zur Truppenausbildung'} →</button>`:''}`);
        $('#game-dialog').classList.add('levelup-dialog-window');
        $('#game-dialog').dataset.building = code;
        if(recommended&&!q)$('#game-dialog').dataset.buildingRecommendation='true';
        if(!q&&!canAfford(b.cost)){const missing=Object.entries(b.cost).filter(([key,value])=>resourceNames[key]&&Number(state.city[key])<Number(value)).map(([key,value])=>`${fmt(Number(value)-Number(state.city[key]))} ${resourceNames[key]}`);$('#dialog-content .button.gold')?.insertAdjacentHTML('beforebegin',`<p class="insufficient">Es fehlen noch ${missing.join(', ')}.</p>`);}
        const destinations={hospital:['army-hospital','', 'Hospital öffnen'],treasure_house:['treasures-tab','','Schatzkammer öffnen'],hall_of_alliance:['dialog-tab','alliance','Allianz öffnen'],trading_post:['dialog-tab','market','Zum Handelsposten']};
        if(destinations[code]){const [act,id,label]=destinations[code];$('#dialog-content .levelup-footer .levelup-primary')?.insertAdjacentHTML('beforebegin',`<button class="button levelup-destination" data-action="${act}" data-id="${id}">${label} →</button>`);}
    }
    function buildingsDialog(trigger) {
        const fromBuildSlot=trigger?.id==='hud-build'||trigger?.id==='hud-build-second';
        const cards=()=>Object.entries(state.buildings).map(([code,b])=>`<button class="quick-card building-quick-card wide" style="margin:8px 0" data-action="building" data-id="${code}"><img src="${buildingImage(code)}" alt=""><strong>${labels[code]}</strong><small style="margin-left:auto">Stufe ${b.level}</small></button>`).join('');
        if(!fromBuildSlot)return openDialog(`<h2>Deine Gebäude</h2><p class="muted">Jedes Gebäude erfüllt eine Aufgabe in deinem Reich.</p>${cards()}`);
        const queues=[...(state.build_queue||[]),...(state.plot_queue||[])].filter(row=>!Number(row.is_processed)).sort((a,b)=>(date(a.finishes_at)||Infinity)-(date(b.finishes_at)||Infinity));
        const slot=trigger?.id==='hud-build-second'?1:0,active=queues[slot];
        if(active?.building_code&&state.buildings[active.building_code])return buildingDialog(active.building_code);
        const recommended=window.ConquerBuildingOrder?.next(state);
        if(recommended&&state.buildings[recommended])return buildingDialog(recommended,{recommended:true});
        return openDialog(`<h2>Deine Gebäude</h2><p class="muted">Jedes Gebäude erfüllt eine Aufgabe in deinem Reich.</p>${cards()}`);
    }
    function renderArmy() {
        if(panels.armyMode==='hospital'){panels.renderHospital();return;}
        trainingPanel.render();
    }
    function unitName(t) { return t.name_de || t.name; }
    function trainDialog(code) {
        trainingPanel.selectTroop(Number(code));panels.onClick('army-troops',{dataset:{}});
    }
    function renderResearch(resetScroll=false) {
        const scroller=$('.rt-scroll'),scroll=resetScroll?{left:0,top:0}:{left:scroller?.scrollLeft||0,top:scroller?.scrollTop||0};
        const active=document.activeElement,focused=active?.closest('.rt-node')?.dataset.id,branch=active?.closest('.rt-branch')?.dataset.id;
        const searchFocused=active?.id==='research-search',scrollFocused=active===scroller;
        const host=$('#content'),style=getComputedStyle(host),viewport={width:host.clientWidth-parseFloat(style.paddingLeft)-parseFloat(style.paddingRight),height:host.clientHeight-parseFloat(style.paddingTop)-parseFloat(style.paddingBottom)};
        host.innerHTML=window.ConquerResearch.render({state,base,esc,fmt,duration,researchNames,costHtml,countdown,viewport});
        if($('.rt-scroll')){$('.rt-scroll').scrollLeft=scroll.left;$('.rt-scroll').scrollTop=scroll.top;}
        if(focused)document.querySelector(`.rt-node[data-id="${CSS.escape(focused)}"]`)?.focus({preventScroll:true});
        else if(branch)document.querySelector(`.rt-branch[data-id="${CSS.escape(branch)}"]`)?.focus({preventScroll:true});
        else if(searchFocused)$('#research-search')?.focus({preventScroll:true});
        else if(scrollFocused)$('.rt-scroll')?.focus({preventScroll:true});
        window.ConquerResearch.afterRender(host);
    }
    function researchDialog(code) {
        const n=state.research_defs.find(n=>n.code===code);if(!n)return;
        const lv=Number(state.research[code]||0),next=n.levels.find(l=>l.level===lv+1),benefit=window.ConquerResearch.bonus(n,next||n.levels.find(l=>l.level===lv));
        const bonusHtml=`<div class="detail-row"><span>${esc(benefit.label)}</span><strong>${esc(benefit.value)}</strong></div>${benefit.note?`<p class="muted">${esc(benefit.note)}</p>`:''}`;
        const title=window.ConquerResearch.title(n),art=window.ConquerResearch.nodeArt(n,base,esc);
        if(!next) {openDialog(`<h2>Forschung abgeschlossen</h2><div class="levelup-shell"><section class="levelup-overview"><div class="levelup-art research-art">${art}<span>${esc(title)}</span></div><div class="levelup-levels"><span>Stufe ${lv}</span><strong>Maximum</strong></div><div class="levelup-stats">${bonusHtml}</div></section><section class="levelup-needs"><div class="levelup-section-title levelup-main-title"><h3>Alle Stufen erforscht</h3><span>✓</span></div><p>Diese Forschung ist vollständig abgeschlossen.</p></section><footer class="levelup-footer"><button class="button levelup-primary" data-action="close-dialog">Zurück zum Forschungsbaum</button></footer></div>`);$('#game-dialog').classList.add('levelup-dialog-window');return;}
        const running=state.research_queue.find(q=>(q.research_code||q.code)===code);
        if(running){
            openDialog(`<h2>Forschung läuft</h2><div class="levelup-shell research-detail"><section class="levelup-overview"><div class="levelup-art research-art">${art}<span>${esc(title)}</span></div><div class="levelup-levels"><span>Stufe ${lv}</span><i aria-hidden="true">➜</i><strong>Stufe ${running.level_to}</strong></div><div class="levelup-stats">${bonusHtml}</div></section><section class="levelup-needs"><div class="levelup-section-title levelup-main-title"><h3>Aktive Forschung</h3><span>⚗</span></div><div class="levelup-running"><strong>Stufe ${running.level_to} wird erforscht</strong><span>Noch ${countdown(running.finishes_at)}</span></div></section><footer class="levelup-footer"><div class="levelup-time"><span>Restzeit</span><strong>${countdown(running.finishes_at)}</strong></div><button class="button levelup-primary" data-action="queue-speedups" data-type="research" data-id="${running.id}">Beschleunigen</button></footer></div>`);
            $('#game-dialog').classList.add('levelup-dialog-window');
            $('#game-dialog').dataset.research=code;return;
        }
        const missing=(next.requirements||[]).filter(r=>r.type==='academy'?state.buildings.academy.level<r.level:r.type==='research'?(state.research[r.code]||0)<r.level:false);
        const researchSeconds=Math.max(1,Math.ceil(next.time*(state.research_duration_factor??1)));
        openDialog(`<h2>Forschung verbessern</h2><div class="levelup-shell research-detail"><section class="levelup-overview"><div class="levelup-art research-art">${art}<span>${esc(title)}</span></div><div class="levelup-levels"><span>Stufe ${lv}</span><i aria-hidden="true">➜</i><strong>Stufe ${lv+1}</strong></div><div class="levelup-stats">${bonusHtml}</div></section><section class="levelup-needs"><div class="levelup-section-title levelup-main-title"><h3>Voraussetzungen</h3><span>${missing.length?'Offen':'Bereit'}</span></div>${requirementResources(next.resources)}${window.ConquerResearch.renderRequirements({requirements:next.requirements,state,base,esc,researchNames})}${state.research_queue.length?'<p class="levelup-warning">In deiner Akademie läuft bereits eine Forschung.</p>':''}</section><footer class="levelup-footer"><div class="levelup-time"><span>Forschungszeit</span><strong>${duration(researchSeconds)}</strong></div><button class="button levelup-primary" data-action="research" data-id="${code}" ${missing.length||state.research_queue.length||!canAfford(next.resources)?'disabled':''}>Forschung auf Stufe ${lv+1} starten</button></footer></div>`);
        $('#game-dialog').classList.add('levelup-dialog-window');
    }
    function revealFocusedResearch() {
        const target=panelHost.querySelector('.rt-focused');if(!target)return;
        const node=target.getBoundingClientRect(),body=panelHost.getBoundingClientRect();
        if(node.top<body.top||node.bottom>body.bottom)panelHost.scrollTop+=node.top-body.top-(panelHost.clientHeight-node.height)/2;
    }
    function renderWorld(host=playfieldHost) {
        state.city.city_skin=kingdom?.profile?.city_skin||'default';state.city.name_frame=kingdom?.profile?.name_frame||window.ConquerNameFrames.normalizeState(kingdom?.name_frames,state.city.city_skin).equipped;window.ConquerWorld.render({host,state,base,esc,monsterArt,now,teleport:teleportSelection,searchMap:query=>api('map/search'+(query?'?'+new URLSearchParams(query):'')),onMonsterAttack:id=>expeditionDialog(id,'monsters'),onMarchRecall:id=>action('march/recall',{march_id:id},'Die Truppen sind auf dem Heimweg.')});
    }
    async function locateMonsterReport(report) {
        const charm=report?.outcome==='attacker_wins'?report.details?.charm:null,x=Number(charm?.x??report?.target_x),y=Number(charm?.y??report?.target_y);
        if(!Number.isFinite(x)||!Number.isFinite(y))return;
        navigate('world',{focusTitle:false});window.ConquerWorld.focus(x,y);await refresh();
        window.ConquerWorld.locate(x,y,charm?['charms','monsters']:['monsters','charms'],charm?.id??report?.target_id??null);
    }
    function expeditionDialog(id,kind) { marchPanel.open(id,kind); }
    function renderReports() {
        $('#content').innerHTML = state.reports.length?state.reports.map(r=>`<div class="report"><span><strong>${r.outcome==='attacker_wins'?'✦ Sieg!':'⚔ Gefecht beendet'} · ${r.target_x}, ${r.target_y}</strong><small>${new Date(date(r.created_at)).toLocaleString('de-DE')}</small></span><button class="button secondary" data-action="report" data-id="${r.id}">Ansehen →</button></div>`).join(''):'<div class="empty"><h3>Deine Geschichte ist noch ungeschrieben.</h3><p>Nach deinem ersten Monsterkampf findest du hier das Ergebnis, deine Verwundeten und deine Beute.</p><button class="button" data-action="tab" data-id="world">Welt erkunden →</button></div>';
    }
    function reportDialog(id,page=0) {
        const r=state.reports.find(r=>Number(r.id)===Number(id));if(!r)return;
        if(['city','rally'].includes(r.details?.battle_kind)){combatReport.open(r);return;}
        if(window.ConquerMonsterReport.isMonster(r))return monsterReports.open(r);
        if(r.details?.type==='scout'){
            if(r.details.blocked){openDialog(`<h2>Spähbericht · ${esc(r.details.target_name)}</h2><p>${esc(r.details.reason||'Die Stadt ist vor Spähern geschützt.')}</p>`);return;}
            const d=r.details,army=rows=>Object.entries(rows||{}).map(([code,n])=>`<div class="detail-row"><span>${esc(unitName(state.troop_defs.find(t=>Number(t.code)===Number(code))||{name:code}))}</span><strong>${fmt(n)}</strong></div>`).join('')||'<p>Keine Truppen gesichtet.</p>';
            openDialog(`<h2>Spähbericht · ${esc(d.target_name)}</h2><div class="progression-content"><p>Aufklärung bei ${r.target_x}, ${r.target_y}. Die Werte zeigen den Zeitpunkt der Ankunft.</p><h3>Mauer</h3><p>${fmt(d.wall?.durability)} / ${fmt(d.wall?.durability_max)} HP</p><h3>Vorräte</h3>${costHtml(d.resources||{},true)}<h3>Geschützte Vorräte</h3>${costHtml(d.protected_resources||{},true)}<h3>Garnison</h3>${army(d.troops)}<h3>Verstärkungen</h3>${army(d.reinforcements)}<h3>Relikte</h3><ul>${(d.treasures||[]).map(t=>`<li>${esc(t.name||t.treasure_code)} · Stufe ${fmt(t.level)}</li>`).join('')||'<li>Keine ausgerüsteten Relikte.</li>'}</ul><h3>Meisterschaft</h3><ul>${(d.mastery?.nodes||[]).filter(n=>n.level>0).map(n=>`<li>${esc(n.name)} · ${n.level}</li>`).join('')||'<li>Keine Meisterschaftspunkte vergeben.</li>'}</ul></div>`);return;
        }
        const d=r.details||{},pvp=['city','rally'].includes(d.battle_kind),units=d.troops||[],size=innerHeight<540?1:innerHeight<700?2:3,pages=Math.max(1,Math.ceil(units.length/size));
        page=Math.max(0,Math.min(pages-1,Number(page)||0));
        const summary=`<section>${d.lord_xp>0?`<p class="notice">+${fmt(d.lord_xp)} Jagd-XP für deinen Hunter</p>`:''}<p class="muted">Gefecht bei ${r.target_x}, ${r.target_y}</p><div class="detail-row"><span>Gegner</span><strong>${esc(d.target_name||d.monster_name||'Monster')}</strong></div>${!pvp&&d.army_power!=null?`<div class="detail-row"><span>${d.type==='monster_rally'?'Rally-Macht':'Armeemacht'}</span><strong>${fmt(d.army_power)} / ${fmt(d.required_power)} benötigt</strong></div>`:`<div class="detail-row"><span>${pvp?'Angriffsstärke':d.type==='monster_rally'?'Schaden der Rally':'Schaden'}</span><strong>${fmt(d.attacker_damage)}</strong></div>`}<div class="detail-row"><span>${pvp?'Verteidigung':'Gegner-HP übrig'}</span><strong>${fmt(pvp?d.defender_strength:d.monster_hp_after)}</strong></div></section>`;
        const troops=`<section><h3>Deine Truppen</h3><div class="battle-units">${units.slice(page*size,(page+1)*size).map(t=>`<div class="battle-unit"><strong>${esc(unitName(state.troop_defs.find(u=>Number(u.code)===Number(t.code))||t))}</strong><small>${fmt(t.sent)} entsandt · ${fmt(t.survived)} überlebt<br>${fmt(t.injured)} verwundet${pvp?' · '+fmt(t.dead)+' gefallen':''}</small></div>`).join('')}</div>${pages>1?`<nav class="panel-pagination"><button class="button secondary" data-action="report-page" data-id="${r.id}" data-page="${page-1}" ${page===0?'disabled':''}>‹</button><span>Truppen ${page+1} / ${pages}</span><button class="button secondary" data-action="report-page" data-id="${r.id}" data-page="${page+1}" ${page+1===pages?'disabled':''}>›</button></nav>`:''}</section>`;
        const loot=d.resources_lost||d.loot||{};
        openDialog(`<h2>${r.outcome==='attacker_wins'?'Sieg für dein Reich':'Gefecht beendet'}</h2><div class="battle-report">${summary}${troops}<section class="battle-report-loot"><h3>${d.perspective==='defender'?'Verlorene Ressourcen':'Deine Beute'}</h3>${Object.keys(loot).length?costHtml(loot,true):'<p class="muted">Keine Ressourcen übertragen.</p>'}${(d.item_rewards||[]).map(item=>`<div class="detail-row"><span>${esc(item.name)}</span><strong>${fmt(item.count)}</strong></div>`).join('')}</section><p class="muted battle-report-note">${d.perspective==='defender'?'Überlebende Verteidiger bleiben in deiner Stadt. Verwundete findest du im Hospital.':'Überlebende Truppen und Beute kommen nach dem Rückmarsch an.'}</p></div>`);
    }
    function sendPreferences(){const frame=$('#city-frame')?.contentWindow;if(!frame)return;frame.postMessage({type:'conquer:preferences',reduced_motion:Boolean(kingdom?.settings?.reduced_motion),city_skin:kingdom?.profile?.city_skin||'default'},location.origin);frame.postMessage({type:'conquer:visibility',visible:!document.hidden&&current==='city'&&!$('#game-dialog').open&&!panelDialog.open},location.origin);}
    function menuDialog() {
        const groups=[['Königreich',['quests','army','research','treasures','mastery','market','defense']],['Gemeinsam',['land','dungeons','expeditions','community','events','rankings','arena']],['Mein Spiel',['settings','worlds','account','help','bugreport']]];
        openDialog('<h2>Spielmenü</h2><div class="menu-groups">'+groups.map(([title,keys])=>'<section><h3>'+title+'</h3><div class="menu-grid">'+keys.map(key=>'<button class="menu-link '+(current===key?'selected':'')+'" data-action="dialog-tab" data-id="'+key+'" '+(current===key?'aria-current="page"':'')+'>'+svg(key)+'<span>'+navs[key]+'</span></button>').join('')+'</div></section>').join('')+'</div>');
        $('#dialog-content .menu-grid').insertAdjacentHTML('beforeend','<button class="menu-link" data-action="vip-open"><span class="nav-emblem">♛</span><span>VIP</span></button>');
        $('#game-dialog').classList.add('menu-dialog');
    }
    function shopDialog() {
        tradingPanel.selectTab('merchant');
        navigate('market');
    }
    const reportShare=window.ConquerReportShare({api,toast,esc,getState:()=>state});
    const combatReport=window.ConquerCombatReport({base,esc,fmt,openDialog,toast,unitName,getState:()=>state,openReport:id=>reportDialog(id),shareReport:(text,id)=>reportShare.open(text,id)});
    const marchPanel=window.ConquerMarch({base,esc,fmt,openDialog,action,toast,getState:()=>state,getKingdom:()=>kingdom,getProfile:()=>kingdom?.profile,unitName,loadFormations:()=>defensePanel.refresh().then(s=>s.formations)});
    const rallyPanel=window.ConquerRallies({api,esc,fmt,date,duration,openDialog,action,toast,marchPanel,getState:()=>state});
    window.addEventListener('conquer-rally-updated',()=>rallyPanel.list());
    const congressPanel=window.ConquerCongress({base,esc,fmt,duration,openDialog,getState:()=>state,marchPanel,action,toast});
    const villageMenu=window.ConquerVillage({base,esc,fmt,getState:()=>state,getKingdom:()=>kingdom,openDialog,navigate,action,toast,marchPanel});
    window.addEventListener('conquer-village-menu',e=>villageMenu.open(e.detail));
    let worldChat;
    const panels=window.ConquerPanels({base,esc,fmt,date,duration,countdown,openDialog,action,api,navigate,refresh,toast,costHtml,now,labels,researchNames,render,sendPreferences,beginTeleport:item=>{const mode=item.teleport_mode,allianceCenters=mode==='alliance'?(kingdom?.alliance?.members||[]).filter(member=>Number.isFinite(Number(member.coord_x))&&Number.isFinite(Number(member.coord_y))).map(member=>({x:Number(member.coord_x),y:Number(member.coord_y)})):[];teleportSelection={item_code:Number(item.item_code),mode,origin_x:Number(state.city.coord_x),origin_y:Number(state.city.coord_y),alliance_centers:allianceCenters,max_distance:mode==='alliance'?12:null};navigate('world',{focusTitle:false});toast(mode==='alliance'?'Wähle einen Platz nahe einer verbündeten Stadt.':'Wähle einen freien Platz auf der Weltkarte.');},openSpeedups:(...args)=>queueSpeedups.show(...args),openPrivateChat:(id,name)=>{if($('#game-dialog').open)$('#game-dialog').close();worldChat?.openPrivate(id,name);navigate(playfield);},getState:()=>state,getKingdom:()=>kingdom,getExpeditions:()=>expeditions,getMarket:()=>market,getErrors:()=>loadErrors,renderGuide:()=>beginnerGuide.render()});
    const featureContext={base,esc,fmt,date,duration,countdown,openDialog,action,api,navigate,refresh,toast,costHtml,getState:()=>state,getKingdom:()=>kingdom,openShrine:(id,garrison=false)=>marchPanel.open(Number(id),garrison?'shrine-garrison':'shrine')};
    const rewards=window.ConquerRewards.create(featureContext);
    const monsterReports=window.ConquerMonsterReport.create({...featureContext,openReport:id=>reportDialog(id),shareReport:(text,id)=>reportShare.open(text,id),locateReport:locateMonsterReport});
    const openSharedReport=async shareId=>{
        const result=await api(`community/shared-report/${Number(shareId)}?world_id=${Number(state.city.world_id)}`),report=result.report;
        if(['city','rally'].includes(report?.details?.battle_kind)){combatReport.open(report);return;}
        if(window.ConquerMonsterReport.isMonster(report)){monsterReports.open(report);return;}
        throw new Error('Dieser geteilte Bericht wird nicht unterstützt.');
    };
    const overlay=window.ConquerOverlay({...featureContext,now,labels});
    const activeEffects=window.ConquerActiveEffects({...featureContext,now});
    const inventoryOverview=window.ConquerInventoryOverview.create(featureContext);
    const trainingPanel=window.ConquerTraining({...featureContext,now,unitName,getHost:()=>panelHost,armyHeader:()=>panels.armyHeader()});
    const queueSpeedups=window.ConquerQueueSpeedups({...featureContext,now,labels,researchNames,render,canUseTrainingSpeedups:()=>trainingPanel.canUseSpeedups()});
    const trainingHud=window.ConquerTrainingHud({getState:()=>state,now,refresh});
    const vipPanel=window.ConquerVip(featureContext);
    const treasurePanel=window.ConquerTreasures({...featureContext,now,onUpgrade:()=>buildingDialog('treasure_house'),navigate:tab=>{navigate(tab);if(tab==='inventory')panels.onClick('inventory-category',{dataset:{id:'other'}});}});
    const tradingPanel=window.ConquerTrading({...featureContext,now,getMarket:()=>market,onUpgrade:()=>buildingDialog('trading_post')});
    const mailboxPanel=window.ConquerMailbox({...featureContext,openPlayerReport:report=>combatReport.open(report),openMonsterReport:mail=>{
        const cached=state.reports.find(r=>Number(r.id)===Number(mail.source_id));
        const report=cached||{id:Number(mail.source_id),created_at:mail.created_at,outcome:mail.metadata.details?.outcome,target_x:mail.metadata.x,target_y:mail.metadata.y,details:mail.metadata.details,can_delete:true};
        if(!window.ConquerMonsterReport.isMonster(report))return false;
        monsterReports.open(report);return true;
    }});
    const communityPanel=window.ConquerCommunity({...featureContext,openSharedReport,openMailbox:()=>{navigate('reports');mailboxPanel.select('private');}});
    const dungeonPanel=window.ConquerDungeons({...featureContext,unitName});
    worldChat=window.ConquerWorldChat({...featureContext,openSharedReport});
    const defensePanel=window.ConquerDefense(featureContext);
    const progressionPanel=window.ConquerProgression(featureContext);
    const worldPanel=window.ConquerWorldPanel(featureContext);
    const landPanel=window.ConquerLand(featureContext);
    const beginnerGuide=window.ConquerBeginnerGuide({...featureContext,getHost:()=>panelHost,labels,buildingImage,buildingDialog,buildingFunction});
    const bugReports=window.ConquerBugReports(featureContext);
    document.addEventListener('submit',async e=>{const form=e.target.closest('form[data-form]');if(!form)return;e.preventDefault();if(busy||!form.reportValidity())return;try{if(await bugReports.onSubmit(form))return;if(mailboxPanel.onSubmit(form)||dungeonPanel.onSubmit(form)||communityPanel.onSubmit(form)||defensePanel.onSubmit(form))return;if(await landPanel.onSubmit(form))return;if(await progressionPanel.onSubmit(form))return;await panels.onSubmit(form);}catch(err){toast(err.message);}});
    document.addEventListener('input',e=>{if($('#content').contains(e.target)&&e.target.matches('input,textarea,select')&&!e.target.matches('[data-hospital-count]'))$('#content').dataset.dirty='true';});
    document.addEventListener('keydown',e=>{if(e.key==='Enter'&&e.target.id==='research-search'){e.preventDefault();window.ConquerResearch.setSearch(e.target.value);delete $('#content').dataset.dirty;renderResearch(true);}});
    window.addEventListener('message',e=>{const frame=$('#city-frame');if(e.origin!==location.origin||e.source!==frame?.contentWindow||!e.data)return;if(e.data.type==='conquer:building-panel'&&['treasures','market'].includes(e.data.tab)){if(e.data.tab==='treasures')treasurePanel.selectTab(e.data.panel);else tradingPanel.selectTab(e.data.panel);navigate(e.data.tab);return;}if(e.data.type==='conquer:village-menu')villageMenu.open({kind:'home'});if(e.data.type==='conquer:building-function'&&Object.hasOwn(state?.buildings||{},e.data.code))buildingFunction(e.data.code,e.data.troopCode);if(e.data.type==='conquer:building'&&Object.hasOwn(state?.buildings||{},e.data.code))buildingDialog(e.data.code);if(e.data.type==='conquer:navigate'&&Object.hasOwn(navs,e.data.tab))navigate(e.data.tab);});
    document.addEventListener('click',e=>{
        const b=e.target.closest('[data-action]'); if(!b || b.disabled) return;
        const {action:act,id,kind}=b.dataset;
        if(act==='command-retry'){const pending=commands.pending();if(pending)action(pending.path,pending.body,'Auftrag bestätigt.');return;}
        if(rewards.onClick(act))return;
        if(monsterReports.onClick(act,b))return;
        if(act==='gather-recall')return action('march/recall',{march_id:Number(id)},'Die Sammler sind auf dem Heimweg.');
        if(act==='teleport-cancel'){teleportSelection=null;renderWorld();toast('Zielwahl beendet.');return;}
        if(act==='teleport-confirm'&&teleportSelection){const selection=teleportSelection;(async()=>{const result=await action('kingdom/action',{action:'inventory.use',item_code:selection.item_code,target_x:Number(b.dataset.x),target_y:Number(b.dataset.y)});if(result){teleportSelection=null;renderWorld();}})();return;}
        if(act==='retry') return refresh(); if(!state)return;
        if(act==='close-dialog'){$('#game-dialog').close();return;}
        if(beginnerGuide.onClick(act,b))return;
        if(bugReports.onClick(act,b))return;
        if(overlay.onClick(act,b)||vipPanel.onClick(act,b))return;
        if(['army-hospital','army-troops','treasures-tab'].includes(act))$('#game-dialog').close();
        if(act==='treasures-tab')return navigate('treasures');
        if(queueSpeedups.onClick(act,b,e)||trainingPanel.onClick(act,b)||mailboxPanel.onClick(act,b)||dungeonPanel.onClick(act,b)||treasurePanel.onClick(act,b)||tradingPanel.onClick(act,b)||landPanel.onClick(act,b)||worldPanel.onClick(act,b)||communityPanel.onClick(act,b)||defensePanel.onClick(act,b)||progressionPanel.onClick(act,b)||congressPanel.onClick(act,b)||rallyPanel.onClick(act,b)||villageMenu.onClick(act,b)||marchPanel.onClick(act,b)||panels.onClick(act,b))return;
        if(act==='menu-more')return menuDialog();
        if(act==='chat-open')return worldChat?.open();
        if(act==='shop-open')return shopDialog();
        if(act==='shop-section'){
            $('#game-dialog').close();tradingPanel.selectTab(id);return navigate('market');
        }
        if(act==='return-playfield')return navigate(playfield);
        if(act==='research-branch'){if(window.ConquerResearch.selectBranch(id)){delete $('#content').dataset.dirty;renderResearch(true);}return;}
        if(act==='research-group'){window.ConquerResearch.setGroup(id);delete $('#content').dataset.dirty;renderResearch(true);return;}
        if(act==='research-search'||act==='research-clear'){window.ConquerResearch.setSearch(act==='research-clear'?'':$('#research-search')?.value||'');delete $('#content').dataset.dirty;renderResearch(true);return;}
        if(act==='research-focus'){$('#game-dialog').close();window.ConquerResearch.focus(id,state.research_defs);delete $('#content').dataset.dirty;if(current!=='research')navigate('research',{focusTitle:false});else renderResearch(true);revealFocusedResearch();return;}
        if(act==='army-tier'){trainingPanel.selectTier(Number(id));render();return;}
        if(act==='resource')return resourceDialog(id);
        if(act==='tab') return navigate(id);
        if(act==='dialog-tab') { $('#game-dialog').close(); return navigate(id); }
        if(act==='building') return buildingDialog(id);
        if(act==='buildings') return buildingsDialog(b);
        if(act==='upgrade') return action('city/upgrade-building',{building_code:id,expected_level:Number(state.buildings[id].level)},'Deine Baumeister legen los!');
        if(act==='cancel-build') return action('city/cancel-build/'+id,{},'Ausbau abgebrochen.');
        if(act==='train-dialog') return trainDialog(id);
        if(act==='train') return trainingPanel.submit();
        if(act==='training-building'){buildingFunction(id);return;}
        if(act==='research-dialog') return researchDialog(id);
        if(act==='research') return action('research/start',{code:id,level_to:Number(state.research[id]||0)+1},'Deine Gelehrten machen sich an die Arbeit.');
        if(act==='filter') {filter=id;return renderWorld();}
        if(act==='expedition') return expeditionDialog(id,kind);
        if(act==='report-page'){reportDialog(id,b.dataset.page);return;}
        if(act==='report') return reportDialog(id);
        if(act==='logout') { api('auth/logout',{}).then(()=>location.href=base+'/').catch(e=>toast(e.message)); }
    });
    $('.dialog-close').addEventListener('click',()=>$('#game-dialog').close());
    $('#game-dialog').addEventListener('close',()=>{placeToast();sendPreferences();if(dialogTrigger?.isConnected&&(!panelDialog.open||panelDialog.contains(dialogTrigger)))dialogTrigger.focus({preventScroll:true});else if(panelDialog.open&&!panelDialog.contains(document.activeElement))$('#page-title').focus({preventScroll:true});else if(!panelDialog.open)$('#navigation [aria-current="page"]')?.focus({preventScroll:true});dialogTrigger=null;});
    $('#game-dialog').addEventListener('click',e=>{if(e.target===$('#game-dialog')){const r=e.target.getBoundingClientRect();if(e.clientX<r.left||e.clientX>r.right||e.clientY<r.top||e.clientY>r.bottom)e.target.close();}});
    $('#lord-talent-button').addEventListener('click',()=>{if(state)navigate('mastery');});
    $('#account-button').addEventListener('click',()=>{if(state)navigate('profile');});

    panelDialog.addEventListener('cancel',e=>{e.preventDefault();navigate(playfield);});
    panelDialog.addEventListener('click',e=>{if(e.target!==panelDialog)return;const r=panelDialog.getBoundingClientRect();if(e.clientX<r.left||e.clientX>r.right||e.clientY<r.top||e.clientY>r.bottom)navigate(playfield);});
    panelDialog.addEventListener('close',()=>{sendPreferences();
        if(!isPlayfield(current))navigate(playfield);
        const trigger=panelTrigger;panelTrigger=null;
        const target=trigger?.node?.isConnected?trigger.node:trigger?.id?document.getElementById(trigger.id):trigger?.tab?$('#navigation')?.querySelector(`[data-id="${CSS.escape(trigger.tab)}"]`):null;
        (target||$('#main')).focus({preventScroll:true});
    });

    window.addEventListener('conquer-world-moved',()=>{if(current==='world'&&!busy&&!$('#game-dialog').open)refresh();});
    window.addEventListener('hashchange',()=>{const t=location.hash.slice(1);if(Object.hasOwn(navs,t)&&t!==current)navigate(t);});
    let panelResizeTimer,panelNeedsResize=false;
    function resizePanel(){
        clearTimeout(panelResizeTimer);
        panelResizeTimer=setTimeout(()=>{
            if(!panelNeedsResize||!state||busy||$('#game-dialog').open||!['research','inventory','treasures','profile','quests'].includes(current))return;
            if((panelHost.dataset.dirty&&current!=='inventory')||panelHost.contains(document.activeElement)&&document.activeElement.matches('input,textarea,select'))return;
            panelNeedsResize=false;render();
        },150);
    }
    window.addEventListener('resize',()=>{panelNeedsResize=true;resizePanel();});
    panelHost.addEventListener('focusout',resizePanel);
    $('#game-dialog').addEventListener('close',resizePanel);
    setInterval(()=>{queueSpeedups.update();activeEffects.update();treasurePanel.updateTime();tradingPanel.updateTime();panels.updateHospitalTime();document.querySelectorAll('[data-end]').forEach(el=>{el.textContent=duration((date(el.dataset.end)-now())/1000);});},1000);
    setInterval(async()=>{
        if(document.hidden||busy) return;
        const dialog=$('#game-dialog');
        if(!dialog.open) { refresh(); return; }
        const before=JSON.stringify([state?.buildings,state?.build_queue]);
        await refresh(false);
        marchPanel.update();queueSpeedups.update();
        if(dialog.open&&dialog.dataset.research&&!state.research_queue.some(q=>(q.research_code||q.code)===dialog.dataset.research))researchDialog(dialog.dataset.research);
        if(dialog.open && dialog.dataset.building && before!==JSON.stringify([state?.buildings,state?.build_queue])) buildingDialog(dialog.dataset.building,{recommended:dialog.dataset.buildingRecommendation==='true'});
    },5000);
    document.addEventListener('visibilitychange',()=>{sendPreferences();if(!document.hidden&&!busy)refresh();});
    const entry=location.hash.slice(1).split('?');if(['treasures','market'].includes(entry[0])&&entry[1]){current=entry[0];const section=new URLSearchParams(entry[1]).get('section');if(current==='treasures')treasurePanel.selectTab(section);else tradingPanel.selectTab(section);history.replaceState(null,'','#'+current);}
    refresh().then(async()=>{
        if(state){queueSpeedups.resume();rewards.resume();commandRecovery();}
        const startupParams=new URLSearchParams(location.search),mapX=Number(startupParams.get('map_x')),mapY=Number(startupParams.get('map_y'));
        if(startupParams.has('map_x')&&startupParams.has('map_y')&&Number.isFinite(mapX)&&Number.isFinite(mapY)&&state){
            const kinds=startupParams.get('map_kind')==='charms'?['charms','monsters']:['monsters','charms'],targetId=startupParams.get('map_id');
            const url=new URL(location.href);for(const key of ['map_x','map_y','map_kind','map_id'])url.searchParams.delete(key);history.replaceState(history.state,'',url);
            window.ConquerWorld.focus(mapX,mapY);await refresh();window.ConquerWorld.locate(mapX,mapY,kinds,targetId);return;
        }
        const reportId=startupParams.get('combat_report');
        if(!reportId||!/^\d+$/.test(reportId)||!state)return;
        const url=new URL(location.href);url.searchParams.delete('combat_report');history.replaceState(history.state,'',url);
        try{const result=await api('battle/report/'+reportId);const report=result.report;if(['city','rally'].includes(report?.details?.battle_kind))combatReport.open(report);}
        catch(error){toast(error.message);}
    });
})();

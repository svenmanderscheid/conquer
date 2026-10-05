/* Authenticated community panels. Text from other players is escaped at every display boundary. */
window.ConquerCommunity=function(ctx){
    'use strict';
    const {esc,fmt,date,countdown,api,toast,openDialog,refresh,openSharedReport,openSharedLocation,openStructureLocation,beginStructurePlacement}=ctx,base=ctx.base||'';
    const names={world:'Weltchat',alliance:'Allianzchat',mail:'Briefe',help:'Allianz-Hilfe',research:'Allianzforschung',buildings:'Allianzgebäude',members:'Mitglieder',diplomacy:'Diplomatie',shipments:'Lieferungen'};
    const resources={food:'Nahrung',lumber:'Holz',stone:'Stein',gold:'Gold'};
    const ranks=window.ConquerAllianceRanks,roleOrder=['member','veteran','officer','vice_leader','leader'];
    const rankText=(key,params={})=>window.ConquerLocale.t('alliance_rank.'+key,params);
    const roleLabel=role=>ranks.level(role)?`<span class="alliance-rank-label">${ranks.badge(role)}<span data-i18n="alliance_rank.role.${role}">${esc(ranks.name(role))}</span></span>`:'';
    const rank={member:1,veteran:2,officer:3,vice_leader:4,leader:5};
    const relation={ally:'Bündnis',nap:'Nichtangriffspakt',war:'Krieg'};
    const labels={castle:'Burg',wall:'Stadtmauer',farm:'Bauernhof',lumber_camp:'Sägewerk',quarry:'Steinbruch',gold_mine:'Goldmine',storage:'Lagerhaus',academy:'Akademie',barrack:'Kaserne',hospital:'Hospital',trading_post:'Handelsposten',hall_of_alliance:'Allianzhalle'};
    let selected='world',data=null,error='',loading=null,working=false,timer=null,readMail=null;
    const drafts={},pendingForms=new WeakSet(),chatSignatures=new WeakMap();
    const host=()=>document.querySelector('#content');
    const active=()=>Boolean(host()?.querySelector('.community-shell'));
    const world=()=>Number(ctx.getState()?.city?.world_id||1);
    const time=value=>new Date(date(value)).toLocaleString(window.ConquerLocale?.locale??'en',{day:'2-digit',month:'2-digit',hour:'2-digit',minute:'2-digit'});
    const button=(text,act,extra='',secondary=false)=>`<button class="button ${secondary?'secondary':''}" data-action="community-${act}" ${extra}>${esc(text)}</button>`;
    const empty=text=>`<p class="community-empty">${esc(text)}</p>`;
    const field=(label,body)=>`<label>${esc(label)}${body}</label>`;
    const players=(members=false)=>(members?data.members.map(m=>({id:m.player_id,username:m.username})):data.players).filter(p=>Number(p.id)!==data.player_id).map(p=>`<option data-user-content value="${Number(p.id)}">${esc(p.username)} · #${Number(p.id)}</option>`).join('');
    const resourceSelect=()=>`<select name="resource">${Object.entries(resources).map(([k,v])=>`<option value="${k}">${v}</option>`).join('')}</select>`;
    const form=(name,body,submit='Speichern',extra='')=>`<form class="community-form" data-form="community-${name}" ${extra}>${body}<button class="button gold" type="submit">${esc(submit)}</button><p class="community-form-error" role="alert"></p></form>`;
    const draftKey=f=>`${f.dataset.communityWorld||world()}:${f.dataset.form}${f.dataset.id?'-'+f.dataset.id:''}`;
    const locationLink=message=>{const match=String(message||'').match(/· Welt (\d+) · X (\d+) \/ Y (\d+)$/);if(!match)return '';const location={world:Number(match[1]),x:Number(match[2]),y:Number(match[3])};if(location.world<1||location.x<0||location.x>Number(ctx.getState()?.world?.map_profile?.width||256)-1||location.y<0||location.y>Number(ctx.getState()?.world?.map_profile?.height||256)-1)return '';const monster=(ctx.getState()?.monsters||[]).find(entry=>Number(entry.coord_x)===location.x&&Number(entry.coord_y)===location.y),definition=monster?.definition||{},name=String(definition.name||message),identity=`${definition.art||''} ${name}`,kind=/treasure goblin|schatzgoblin/i.test(identity)?'treasure-goblin-turquoise':/grumwald|frostgrimm|sandmaul|glutramm|magdar/i.test(identity)?'magdar':/green dragon|grüner drache/i.test(identity)?'green-dragon':/red dragon|roter drache/i.test(identity)?'red-dragon':/gold dragon|golddrache/i.test(identity)?'gold-dragon':/skeleton|skelett/i.test(identity)?'skeleton':/golem/i.test(identity)?'golem':/orc|ork/i.test(identity)?'orc':null,regional=identity.match(/grumwald|frostgrimm|sandmaul|glutramm/i),art=regional?`monsters/storybook-v2/${regional[0].toLowerCase()}`:kind?`monsters/2.5d/bright-v2/${kind}`:/^monsters\/[a-z0-9-]+$/.test(definition.art||'')?definition.art:'monsters/2.5d/bright-v2/orc';return `<button type="button" class="community-location-link" data-action="community-shared-location" data-world="${location.world}" data-x="${location.x}" data-y="${location.y}" aria-label="Geteiltes Ziel bei X ${location.x}, Y ${location.y} auf der Weltkarte zeigen"><img src="${esc(base+'/assets/art/'+art+'.png')}" alt=""><span><b>Monsterziel</b><small>Auf Weltkarte zeigen</small></span><strong>X ${location.x} / Y ${location.y}</strong></button>`;};
    function rememberForm(f){
        // Disabled controls are omitted by FormData; retain the pre-send snapshot.
        if(pendingForms.has(f))return;
        const values=Object.fromEntries(new FormData(f)),key=draftKey(f),previous=drafts[key];
        if(previous&&JSON.stringify(previous.values)!==JSON.stringify(values))delete f.dataset.requestId;
        drafts[key]={values,requestId:f.dataset.requestId};
    }
    function remember(){host()?.querySelectorAll('form[data-form^="community-"]').forEach(rememberForm);}
    function restore(){host()?.querySelectorAll('form[data-form^="community-"]').forEach(f=>{f.dataset.communityWorld=String(world());const draft=drafts[draftKey(f)];if(!draft)return;for(const [key,value]of Object.entries(draft.values)){const input=f.elements.namedItem(key);if(input)input.value=value;}if(draft.requestId)f.dataset.requestId=draft.requestId;});}
    for(const eventName of ['input','change'])document.addEventListener(eventName,event=>{const f=event.target.closest?.('form[data-form^="community-"]');if(f&&host()?.contains(f))rememberForm(f);});
    function render(tab){if(tab!=='alliance-tools')return false;if(data&&data.world_id!==world())data=null;remember();draw();load();return true;}
    function draw(){
        const h=host();if(!h)return;
        const oldChat=h.querySelector('.community-chat-log'),oldScroll=oldChat?.scrollTop||0,followChat=!oldChat||oldChat.scrollHeight-oldChat.clientHeight-oldScroll<50;
        const nav=`<nav class="community-tabs" aria-label="Gemeinschaft">${Object.entries(names).map(([key,name])=>`<button class="${selected===key?'active':''}" data-action="community-tab" data-id="${key}" aria-pressed="${selected===key}">${esc(name)}${key==='mail'&&data?.unread?` <span class="badge">${fmt(data.unread)}</span>`:''}</button>`).join('')}</nav>`;
        h.innerHTML=`<section class="community-shell">${nav}<div class="community-heading"><h2>${names[selected]}</h2>${button('Aktualisieren','reload','',true)}</div>${error?`<p class="community-error" role="alert">${esc(error)}</p>`:''}<div class="community-body">${data?body():empty('Die Gemeinschaft wird geladen …')}</div></section>`;restore();const log=h.querySelector('.community-chat-log');if(log)log.scrollTop=followChat?log.scrollHeight:oldScroll;
    }
    function body(){
        if(!data.alliance&&['alliance','help','research','buildings','members','diplomacy','shipments'].includes(selected))return `${empty('Tritt einer Allianz in dieser Welt bei, um diese Funktion zu nutzen.')}<button class="button" data-action="tab" data-id="alliance">Allianz finden</button>`;
        return ({world:()=>chat('world'),alliance:()=>chat('alliance'),mail,help,research,buildings,members,diplomacy,shipments})[selected]();
    }
    async function load(force=false){
        if(loading)return loading;if(!force&&data&&Date.now()-data.loadedAt<10000){schedule();return;}
        const editing=()=>host()?.contains(document.activeElement?.closest('input,textarea,select'))||host()?.dataset.dirty==='true';
        const requestedWorld=world();loading=(async()=>{try{const next=await api('community/state?world_id='+requestedWorld);if(requestedWorld!==world())return;data={...next,loadedAt:Date.now()};error='';if(active()){if(!updateChatLog()&&!working&&!editing()){remember();draw();}}}catch(e){if(requestedWorld!==world())return;error=e.message;if(active()&&!working&&!editing()){remember();draw();}}finally{loading=null;schedule();}})();return loading;
    }
    function schedule(){clearTimeout(timer);timer=setTimeout(()=>{if(active())load(true);},10000);}
    function chatRows(channel){
        const rows=data[channel+'_chat'];return rows.length?rows.map(m=>`<article class="community-message ${Number(m.player_id)===data.player_id?'mine':''}" data-community-message-id="${Number(m.id)}"><header><strong data-user-content>${m.alliance_tag?'['+esc(m.alliance_tag)+'] ':''}${esc(m.username)}</strong><time data-i18n-ignore>${esc(time(m.created_at))}</time></header><p data-user-content>${esc(m.message)}</p>${locationLink(m.message)}${Number(m.shared_report_id)>0?`<button type="button" class="community-report-link" data-action="community-shared-report" data-id="${Number(m.shared_report_id)}"><span aria-hidden="true">⚔</span> <span data-i18n="community.view_report">${esc(window.ConquerLocale.t('community.view_report'))}</span></button>`:''}</article>`).join(''):empty('Noch keine Nachrichten. Beginne ein Gespräch.');
    }
    function updateChatLog(){
        if(!data||Number(data.world_id)!==world())return false;
        const log=host()?.querySelector('.community-chat-log'),f=host()?.querySelector('form[data-form="community-chat"]');
        if(!log||!f||Number(f.dataset.communityWorld)!==world()||f.dataset.id!==selected||selected==='alliance'&&!data.alliance)return false;
        const signature=JSON.stringify(data[selected+'_chat']);if(chatSignatures.get(log)===signature)return true;
        const scroll=log.scrollTop,follow=log.scrollHeight-log.clientHeight-scroll<50,top=log.getBoundingClientRect().top;
        const anchor=[...log.querySelectorAll('[data-community-message-id]')].find(row=>row.getBoundingClientRect().bottom>top),anchorTop=anchor?.getBoundingClientRect().top;
        log.innerHTML=chatRows(selected);chatSignatures.set(log,signature);
        const replacement=anchor&&log.querySelector(`[data-community-message-id="${anchor.dataset.communityMessageId}"]`);
        log.scrollTop=follow?log.scrollHeight:replacement?scroll+replacement.getBoundingClientRect().top-anchorTop:scroll;
        return true;
    }
    function chat(channel){
        return `<div class="community-chat-log" aria-label="${esc(names[channel])}">${chatRows(channel)}</div>${form('chat',`<input type="hidden" name="channel" value="${channel}">${field('Deine Nachricht',`<textarea name="message" maxlength="200" rows="2" required placeholder="Bis zu 200 Zeichen"></textarea>`)}<small>Höchstens eine Chatnachricht alle drei Sekunden.</small>`,'Nachricht senden',`data-id="${channel}"`)}`;
    }
    function mail(){
        const rows=data.mail;return `${gifts()}<div class="community-columns"><section><h3>Posteingang & gesendete Briefe</h3><div class="community-list">${rows.length?rows.map(m=>{const incoming=Number(m.recipient_id)===data.player_id;return `<button class="community-mail-card ${incoming&&!m.read_at?'unread':''}" data-action="community-mail-open" data-id="${Number(m.id)}"><strong>${esc(m.subject)}</strong><span>${incoming?'Von':'An'} ${esc(incoming?m.sender_name:m.recipient_name)} · ${esc(time(m.created_at))}</span><small>${incoming&&!m.read_at?'Ungelesen':incoming?'Gelesen':m.read_at?'Vom Empfänger gelesen':'Zugestellt'}</small></button>`;}).join(''):empty('Dein Briefkasten ist leer.')}</div></section><section><h3>Brief schreiben</h3>${form('mail',`${field('Empfänger',`<select name="player_id" required><option value="">Spieler auswählen</option>${players()}</select>`)}${field('Betreff','<input name="subject" maxlength="100" required>')}${field('Nachricht','<textarea name="body" maxlength="4000" rows="7" required></textarea>')}<small>Privat zwischen dir und dem Empfänger. Bis zu 100 Briefe pro Tag.</small>`,'Brief senden')}</section></div>`;
    }
    function gifts(){return data.gifts?.length?`<section><h3>Geschenke der Spielleitung</h3><div class="community-grid">${data.gifts.map(g=>`<article class="community-card"><h3 data-user-content>${esc(g.title)}</h3><p class="community-gift-message">${esc(g.message)}</p><p>${Object.entries({...resources,gems:'Edelsteine'}).filter(([r])=>Number(g.rewards[r])>0).map(([r,label])=>fmt(g.rewards[r])+' '+esc(label)).join(' · ')}${Number(g.rewards.quantity)>0?' · '+fmt(g.rewards.quantity)+' × '+esc(g.item_name):''}</p><small>Bereits gutgeschrieben · ${esc(time(g.delivered_at))}</small></article>`).join('')}</div></section>`:'';}
    function help(){return `<p>Jedes Mitglied kann pro Auftrag einmal helfen: bis zu 30 Sekunden, insgesamt höchstens 30 % der bei der Anfrage verbleibenden Zeit. Pro Tag sind 30 Hilfen möglich.</p><div class="community-columns"><section><h3>Deine laufenden Aufträge</h3>${data.queues.length?data.queues.map(q=>`<article class="community-card"><strong>${esc(window.ConquerLocale.text(q.type==='building'?'Bau':'Forschung'))} · ${esc(window.ConquerLocale.text(labels[q.label]||q.label))}</strong><p>Noch ${countdown(q.finishes_at)}</p>${button('Hilfe anfordern','help-request',`data-id="${Number(q.id)}" data-type="${esc(q.type)}"`)}</article>`).join(''):empty('Du hast gerade keinen Bau- oder Forschungsauftrag.')}</section><section><h3>Hier wird Hilfe gebraucht</h3>${data.help_requests.length?data.help_requests.map(h=>`<article class="community-card"><strong><span data-user-content>${esc(h.username)}</span> · ${h.queue_type==='building'?'Bauauftrag':'Forschung'}</strong><p>${fmt(h.help_count)} / ${fmt(h.max_helps)} Hilfen · ${fmt(h.reduced_seconds)} Sek. gespart</p><p>Noch ${countdown(h.finishes_at)}</p>${Number(h.player_id)===data.player_id?'<small>Deine Anfrage</small>':button(Number(h.already_helped)?'Bereits geholfen':'Helfen','help-give',`data-id="${Number(h.id)}" ${Number(h.already_helped)?'disabled':''}`)}</article>`).join(''):empty('Alle laufenden Anfragen sind versorgt.')}</section></div>`;}
    const duration=s=>{s=Number(s)||0;const d=Math.floor(s/86400),h=Math.floor(s%86400/3600),m=Math.ceil(s%3600/60);return [d?d+' T.':'',h?h+' Std.':'',m&&d===0?m+' Min.':''].filter(Boolean).join(' ');};
    function research(){const can=rank[data.role]>=4,trees={battle:'Kampf',economy:'Wirtschaft',development:'Entwicklung',territory:'Territorium'};return `<p>Gemeinsame Forschung hat 20 Stufen je Technologie. Kosten und Dauer steigen stark an; nur ein Projekt kann gleichzeitig laufen.</p><div class="community-wallet">${Object.entries(resources).map(([r,n])=>`<span>${n}<strong>${fmt(data.treasury[r]||0)}</strong></span>`).join('')}</div>${form('donate',`${field('Ressource',resourceSelect())}${field('Betrag','<input name="amount" type="number" min="1" max="10000000" step="1" required>')}`,'In die Bündniskasse spenden')}${Object.entries(trees).map(([tree,title])=>`<section class="community-research-tree"><h3>${title}</h3><div class="community-grid">${Object.values(data.research.nodes).filter(n=>n.tree===tree).map(n=>`<article class="community-card"><h3>${esc(n.name)}</h3><p>Stufe ${fmt(n.level)} / ${fmt(n.max_level)} · ${esc(n.bonus_label.replace('{n}',n.bonus_current))}</p><p>${esc(n.description)}</p>${n.in_queue?`<p>Fertig in ${countdown(n.finishes_at)}</p>`:n.cost_next?`<p>${Object.entries(n.cost_next).map(([r,v])=>esc(resources[r])+': '+fmt(v)).join(' · ')}</p><small>Nächste Stufe: ${duration(n.duration_seconds)} · ${esc(n.bonus_label.replace('{n}',n.bonus_next))}</small>${can?button('Forschung starten','research',`data-id="${esc(n.code)}" ${data.research.active_code?'disabled':''}`):''}`:'<span class="badge">Vollständig erforscht</span>'}</article>`).join('')}</div></section>`).join('')}`;}
    function buildings(){
        const t=data.territory,can=rank[data.role]>=3,center=t.structures.find(s=>s.structure_type==='center'),outposts=t.structures.filter(s=>s.structure_type==='outpost'),complete=t.outpost_count>=t.outpost_limit;
        const location=center?`X ${fmt(center.coord_x)} · Y ${fmt(center.coord_y)}`:'Standort noch offen';
        return `<section class="alliance-territory" aria-label="Allianzgebiet">
            <header class="alliance-territory-intro">
                <span class="alliance-territory-emblem" aria-hidden="true"><img src="${esc(base+'/assets/art/hud/alliance.svg')}" alt=""></span>
                <span><small>Gemeinsames Gebiet</small><h3>Das Herz eurer Allianz</h3><p>Allianzgebäude schützen und stärken alle Mitglieder innerhalb ihrer Reichweite.</p></span>
            </header>
            <div class="alliance-building-grid">
                <article class="alliance-building-card is-center ${center?'is-built':'is-open'}">
                    <div class="alliance-building-art"><img src="${esc(base+'/assets/art/map/painted-v2/alliance-center.webp')}" alt="" loading="lazy"><span class="alliance-building-state">${center?'Errichtet':'Bereit zum Bau'}</span></div>
                    <div class="alliance-building-content"><div class="alliance-building-heading"><span><small>Hauptgebäude · 5 × 5 Felder</small><h3>Allianzzentrum</h3></span><strong>${fmt(t.center_radius)}<small>Radius</small></strong></div>
                    <p class="alliance-building-location">${location}</p>${center?`<button type="button" class="alliance-map-link" data-action="community-structure-show" data-id="${Number(center.id)}" data-kind="alliance_center" data-x="${Number(center.coord_x)}" data-y="${Number(center.coord_y)}">Auf Karte zeigen</button>`:''}
                    <ul class="alliance-bonus-list"><li><b>+5 %</b><span>Angriff & Verteidigung</span></li><li><b>+10 %</b><span>Produktion & Sammeltempo</span></li></ul></div>
                </article>
                <article class="alliance-building-card is-outpost ${complete?'is-complete':'is-open'}">
                    <div class="alliance-building-art"><img src="${esc(base+'/assets/art/map/alliance-outpost.svg')}" alt="" loading="lazy"><span class="alliance-building-state">${fmt(t.outpost_count)} / ${fmt(t.outpost_limit)} errichtet</span></div>
                    <div class="alliance-building-content"><div class="alliance-building-heading"><span><small>Erweiterung · 3 × 3 Felder</small><h3>Außenposten</h3></span><strong>${fmt(t.outpost_radius)}<small>Radius</small></strong></div>
                    <p class="alliance-building-location">${outposts.length?outposts.map(s=>`X ${fmt(s.coord_x)} · Y ${fmt(s.coord_y)}`).join(' · '):'Noch kein Außenposten errichtet'}</p>${outposts.map(s=>`<button type="button" class="alliance-map-link" data-action="community-structure-show" data-id="${Number(s.id)}" data-kind="outpost" data-x="${Number(s.coord_x)}" data-y="${Number(s.coord_y)}">Außenposten bei X ${fmt(s.coord_x)} · Y ${fmt(s.coord_y)} zeigen</button>`).join('')}
                    <ul class="alliance-bonus-list"><li><b>+5 %</b><span>Angriff, Leben & Verteidigung</span></li><li class="is-note"><b>i</b><span>Boni mehrerer Außenposten stapeln sich nicht.</span></li></ul></div>
                </article>
            </div>
            <p class="alliance-territory-note"><span aria-hidden="true">◇</span> Der Radius reicht vom Gebäude gleich weit nach oben, unten, links und rechts.</p>
            ${can?`<form class="community-form alliance-build-form" data-form="community-structure"><div><small>Neues Gebäude</small><h3>Gebiet erweitern</h3><p>Wähle ein Gebäude und platziere es anschließend auf einer vollständig freien Landfläche.</p></div>${field('Gebäude auswählen',`<select name="structure_type"><option value="center" ${center?'disabled':''}>Allianzzentrum · 5 × 5</option><option value="outpost" ${!center||complete?'disabled':''}>Außenposten · 3 × 3</option></select>`)}<button class="button gold" type="button" data-action="community-structure-place" ${center&&complete?'disabled':''}>${center&&complete?'Alle Bauplätze belegt':'Standort auf Weltkarte wählen'}</button><p class="community-form-error" role="alert"></p></form>`:`<p class="alliance-territory-note">Offiziere und die Allianzführung können neue Gebäude platzieren.</p>`}
        </section>`;
    }
    function members(){
        const actor=ranks.level(data.role);
        return `<p class="alliance-rank-current">${esc(rankText('current',{rank:ranks.label(data.role)}))}</p><details class="alliance-rank-overview"><summary>${esc(rankText('overview'))}</summary><ol>${roleOrder.map(role=>`<li>${roleLabel(role)}</li>`).join('')}</ol><p>${esc(rankText('permissions'))}</p><p>${esc(rankText('transfer_hint'))}</p></details><div class="community-grid alliance-rank-members">${data.members.map(m=>{
            // Server-provided options are authoritative; the local filter only removes invalid choices.
            const can=actor>=4&&ranks.level(m.role)>0&&actor>ranks.level(m.role)&&Number(m.player_id)!==Number(data.player_id);
            const choices=can&&Array.isArray(m.assignable_roles)?roleOrder.filter(role=>role!=='leader'&&ranks.level(role)<actor&&m.assignable_roles.includes(role)):[];
            return `<article class="community-card alliance-rank-member" data-player-id="${Number(m.player_id)}"><h3 data-user-content>${esc(m.username)}</h3><p>${roleLabel(m.role)}</p><p>(${fmt(m.coord_x)}, ${fmt(m.coord_y)})</p>${choices.length?form('role',`<input type="hidden" name="player_id" value="${Number(m.player_id)}">${field(rankText('select'),`<select name="role" required>${choices.map(role=>`<option value="${role}" data-i18n="alliance_rank.label.${role}" ${role===m.role?'selected':''}>${esc(ranks.label(role))}</option>`).join('')}</select>`)}`,rankText('save'),`data-id="${Number(m.player_id)}"`):''}</article>`;
        }).join('')}</div>`;
    }
    function diplomacy(){
        const can=rank[data.role]>=4;return `<p>Bündnisse und Nichtangriffspakte gelten nach Annahme durch beide Allianzen. Jede Allianz kann einen Vertrag beenden.</p><div class="community-grid">${data.treaties.length?data.treaties.map(t=>`<article class="community-card"><h3 data-user-content>[${esc(t.target_tag)}] ${esc(t.target_name)}</h3><p>${esc(relation[t.relation])}</p>${can?button('Vertrag beenden','treaty-end',`data-id="${Number(t.target_id)}"`,true):''}</article>`).join(''):empty('Es bestehen noch keine Verträge.')}</div><h3>Offene Angebote</h3>${data.proposals.length?data.proposals.map(p=>`<article class="community-card"><strong data-user-content>${esc(p.alliance_name)} → ${esc(p.target_name)}</strong><p>${esc(relation[p.relation])} · gültig bis ${esc(time(p.expires_at))}</p>${can?Number(p.target_id)===Number(data.alliance.id)?`${button('Annehmen','treaty-accept',`data-id="${Number(p.id)}"`)} ${button('Ablehnen','treaty-decline',`data-id="${Number(p.id)}"`,true)}`:button('Angebot zurückziehen','treaty-cancel',`data-id="${Number(p.id)}"`,true):''}</article>`).join(''):empty('Es liegen keine Angebote vor.')}${can?`<h3>Vertrag anbieten</h3>${form('treaty',`${field('Zielallianz',`<select name="alliance_id" required><option value="">Allianz auswählen</option>${data.alliances.map(a=>`<option data-user-content value="${Number(a.id)}">[${esc(a.tag)}] ${esc(a.name)}</option>`).join('')}</select>`)}${field('Vertrag','<select name="relation"><option value="nap">Nichtangriffspakt</option><option value="ally">Bündnis</option></select>')}`,'Angebot senden')}`:''}`;
    }
    function shipments(){return `<div class="community-columns"><section><h3>Ressourcen liefern</h3><p>Lieferungen brauchen 30 Sekunden pro Kartenfeld, mindestens eine Minute. Bis zu fünf Lieferungen können gleichzeitig unterwegs sein. Versandte Vorräte werden sofort abgezogen und bei Ankunft vollständig gutgeschrieben.</p>${form('shipment',`${field('Allianzmitglied',`<select name="player_id" required><option value="">Empfänger auswählen</option>${players(true)}</select>`)}${field('Ressource',resourceSelect())}${field('Menge','<input name="amount" type="number" min="1" max="1000000" step="1" required>')}`,'Lieferung versenden')}</section><section><h3>Lieferverlauf</h3>${data.shipments.length?data.shipments.map(s=>`<article class="community-card"><strong data-user-content>${esc(s.sender_name)} → ${esc(s.recipient_name)}</strong><p>${fmt(s.amount)} ${esc(resources[s.resource])}</p><p>${s.status==='travelling'?'Ankunft in '+countdown(s.arrives_at):s.status==='delivered'?'Zugestellt · '+esc(time(s.settled_at)):'Zurückerstattet · '+esc(time(s.settled_at))}</p></article>`).join(''):empty('Noch keine Lieferungen vorhanden.')}</section></div>`;}
    async function execute(payload,element){
        if(working)return;const formElement=element?.tagName==='FORM'?element:null,requestedWorld=world();
        if(formElement&&Number(formElement.dataset.communityWorld)!==requestedWorld)return;
        working=true;if(formElement)rememberForm(formElement);
        const request=element?.dataset.requestId||crypto.randomUUID();if(element)element.dataset.requestId=request;
        const submittedKey=formElement?draftKey(formElement):null;if(formElement){rememberForm(formElement);pendingForms.add(formElement);const inline=formElement.querySelector('.community-form-error');if(inline)inline.textContent='';}
        const elements=[...(element?.querySelectorAll('button,input,textarea,select')||[])];if(element?.tagName==='BUTTON')elements.push(element);const enabled=elements.filter(e=>!e.disabled);enabled.forEach(e=>e.disabled=true);
        try{
            const result=await api('community/action',{...payload,world_id:requestedWorld,request_id:request});
            if(element)delete element.dataset.requestId;
            if(formElement&&drafts[submittedKey]?.requestId===request){
                delete drafts[submittedKey];formElement.reset();
                // The same draft may have been remounted while the send was pending.
                host()?.querySelectorAll('form[data-form^="community-"]').forEach(f=>{if(draftKey(f)===submittedKey&&f.dataset.requestId===request){f.reset();delete f.dataset.requestId;const inline=f.querySelector('.community-form-error');if(inline)inline.textContent='';}});
            }
            if(requestedWorld!==world())return result;
            if(host()&&(!formElement||host().contains(formElement)))delete host().dataset.dirty;
            error='';toast(result.message);data=null;await load(true);await refresh(false);if(active()){remember();if(!updateChatLog())draw();}return result;
        }
        catch(e){if(requestedWorld!==world())return null;error=e.message;const shown=formElement&&[...(host()?.querySelectorAll('form[data-form^="community-"]')||[])].find(f=>draftKey(f)===submittedKey),inline=(shown||formElement)?.querySelector('.community-form-error');if(inline)inline.textContent=e.message;toast(e.message);return null;}
        finally{working=false;if(formElement)pendingForms.delete(formElement);enabled.forEach(e=>{if(e.isConnected)e.disabled=false;});}
    }
    function onClick(actionOrTarget,buttonTarget){
        const target=buttonTarget||actionOrTarget;if(!target?.dataset)return false;
        const a=target.dataset.action;if(!a?.startsWith('community-'))return false;
        if(a==='community-open'){selected=Object.hasOwn(names,target.dataset.id)?target.dataset.id:'help';ctx.navigate('alliance-tools');return true;}
        if(a==='community-tab'){if(!Object.hasOwn(names,target.dataset.id))return true;if(target.dataset.id==='mail'&&ctx.openMailbox){remember();ctx.openMailbox();return true;}remember();selected=target.dataset.id;delete host().dataset.dirty;draw();load();return true;}
        if(a==='community-reload'){remember();delete host().dataset.dirty;load(true);return true;}
        if(a==='community-shared-report'){if(openSharedReport)Promise.resolve(openSharedReport(Number(target.dataset.id))).catch(problem=>toast(problem.message||'Der Bericht konnte nicht geöffnet werden.'));return true;}
        if(a==='community-shared-location'){if(openSharedLocation)Promise.resolve(openSharedLocation({world:Number(target.dataset.world),x:Number(target.dataset.x),y:Number(target.dataset.y)})).catch(problem=>toast(problem.message||'Das Ziel konnte nicht geöffnet werden.'));return true;}
        if(a==='community-structure-show'){if(openStructureLocation)Promise.resolve(openStructureLocation({id:Number(target.dataset.id),kind:target.dataset.kind,x:Number(target.dataset.x),y:Number(target.dataset.y)})).catch(problem=>toast(problem.message||'Das Allianzgebäude konnte nicht geöffnet werden.'));return true;}
        if(a==='community-structure-place'){const form=target.closest('form'),type=form?.elements.structure_type?.value;if(type&&beginStructurePlacement)beginStructurePlacement(type);return true;}
        if(a==='community-mail-open'){
            const m=data?.mail.find(m=>Number(m.id)===Number(target.dataset.id));if(!m)return true;readMail=m.id;
            openDialog(`<h2 data-user-content>${esc(m.subject)}</h2><p class="muted" data-user-content>${esc(m.sender_name)} → ${esc(m.recipient_name)} · ${esc(time(m.created_at))}</p><div class="community-letter">${esc(m.body)}</div><div class="button-row">${button('Antworten','mail-reply',`data-id="${Number(Number(m.sender_id)===data.player_id?m.recipient_id:m.sender_id)}"`)}<button class="button secondary" data-action="close-dialog">Schließen</button></div>`);
            if(Number(m.recipient_id)===data.player_id&&!m.read_at)execute({action:'mail.read',mail_id:Number(m.id)},target);return true;
        }
        if(a==='community-mail-reply'){const m=data?.mail.find(m=>m.id===readMail);drafts[`${world()}:community-mail`]={values:{player_id:target.dataset.id,subject:('Re: '+(m?.subject||'')).slice(0,100),body:''}};selected='mail';document.querySelector('#game-dialog')?.close();draw();return true;}
        const id=Number(target.dataset.id),actions={
            'community-help-request':{action:'help.request',queue_id:id,queue_type:target.dataset.type},'community-help-give':{action:'help.give',help_id:id},
            'community-research':{action:'research.start',code:target.dataset.id},'community-treaty-accept':{action:'treaty.accept',proposal_id:id},
            'community-treaty-decline':{action:'treaty.decline',proposal_id:id},'community-treaty-cancel':{action:'treaty.cancel',proposal_id:id},'community-treaty-end':{action:'treaty.end',alliance_id:id}
        };if(actions[a])execute(actions[a],target);return true;
    }
    function onSubmit(f){
        if(!f.dataset.form?.startsWith('community-'))return false;const values=Object.fromEntries(new FormData(f));
        for(const key of ['player_id','alliance_id','amount','coord_x','coord_y'])if(key in values)values[key]=Number(values[key]);
        const type=f.dataset.form.slice(10),actions={chat:'chat.send',mail:'mail.send',role:'alliance.role',donate:'treasury.donate',structure:'structure.place',treaty:'treaty.propose',shipment:'shipment.send'};
        if(actions[type])execute({action:actions[type],...values},f);return true;
    }
    const placeStructure=(structureType,x,y)=>execute({action:'structure.place',structure_type:structureType,coord_x:Number(x),coord_y:Number(y)});
    return {render,onClick,onSubmit,placeStructure};
};

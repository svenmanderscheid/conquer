/* Shared Post window. All displayed text and all mutations cross the existing API boundary. */
window.ConquerMailbox=function(ctx){
    'use strict';
    const {api,esc,fmt,date,base,toast,openDialog,refresh}=ctx;
    const categories={war:'Krieg',alliance:'Allianz',system:'System',reports:'Berichte',starred:'Favoriten',private:'Privat'};
    const descriptions={war:'Angriffe, Verteidigung und Spähberichte',alliance:'Nachrichten und Geschenke deiner Allianz',system:'Neuigkeiten und Geschenke aus deinem Reich',reports:'Monsterkämpfe, Arena und Feldzüge',starred:'Deine aufbewahrten Nachrichten',private:'Dein persönlicher Briefwechsel',sent:'Deine gesendeten Briefe'};
    const rewardLabels={pending:'Belohnung abholbereit',claimed:'Abgeholt',credited:'Bereits gutgeschrieben',expired:'Nicht mehr abholbar'};
    const mailKind=r=>r.source==='battle'&&r.category==='reports'&&r.monster_name
        ?`${r.monster_name}${Number(r.monster_level)>0?' · Lv. '+Number(r.monster_level):''}`
        :({letter:r.category==='sent'?'Privater Brief · Gesendet':'Privater Brief',admin_gift:'Geschenk der Spielleitung',alliance_gift:'Allianzgeschenk',battle:r.category==='war'?'Kampfbericht':'Monsterkampf',arena:'Arenaduell',expedition:'Feldzugbelohnung',notification:r.category==='alliance'?'Allianzmitteilung':r.category==='war'?'Kriegsmeldung':'Systemmitteilung'})[r.source]||'Nachricht';
    const resourceNames={food:'Nahrung',lumber:'Holz',stone:'Stein',gold:'Gold',gems:'Edelsteine',lord_xp:'Hunter-XP'};
    let category='war',data=null,entries=[],loading=false,working=false,error='',sequence=0,detailSequence=0,detail=null,lastLoad=0,loadedWorld=0;
    const requests=new Map(),drafts=new Map();
    const host=()=>document.querySelector('#content');
    const active=()=>Boolean(host()?.querySelector('.mailbox-shell'));
    const world=()=>Number(ctx.getState()?.city?.world_id||1);
    const time=v=>new Date(date(v)).toLocaleString('de-DE',{day:'2-digit',month:'2-digit',year:'numeric',hour:'2-digit',minute:'2-digit'});
    const star=filled=>`<svg viewBox="0 0 24 24" aria-hidden="true" class="mail-star-icon ${filled?'filled':''}"><path d="m12 2 3 6.2 6.8 1-4.9 4.8 1.2 6.8-6.1-3.2-6.1 3.2 1.2-6.8-4.9-4.8 6.8-1Z"/></svg>`;
    const monsterIcon=r=>{
        const art=String(r.monster_art||r.metadata?.details?.monster_snapshot?.art||''),name=String(r.monster_name||r.metadata?.details?.monster_snapshot?.name||r.metadata?.details?.monster_name||'').toLowerCase();
        if(/^monsters\/[a-z-]+$/.test(art))return `${base}/assets/art/${art}.png`;
        const aliases={skeleton:['skeleton','skelett'],golem:['golem'],goblin:['goblin'],orc:['orc','ork']};
        const kind=Object.keys(aliases).find(key=>art===key||aliases[key].some(alias=>name.includes(alias)));
        return kind?`${base}/assets/art/map/life-${kind}.png`:`${base}/assets/art/orc.png`;
    };
    const icon=r=>r.source==='battle'&&r.category==='reports'?monsterIcon(r):`${base}/assets/art/hud/${r.source==='alliance_gift'?'inventory':r.category==='war'?'expeditions':r.category==='alliance'?'alliance':r.category==='system'?'quest':'reports'}.svg`;
    const btn=(label,act,extra='',style='')=>`<button type="button" class="mail-button ${style}" data-action="mailbox-${act}" ${extra}>${label}</button>`;
    function badge(){const b=document.querySelector('#navigation [data-id="reports"]');if(!b)return;let count=b.querySelector('.mail-dock-badge');if(!count){count=document.createElement('span');count.className='dock-badge mail-dock-badge';count.setAttribute('aria-hidden','true');b.append(count);}const n=loadedWorld===world()?Number(data?.unread||0):0;count.hidden=!n;count.textContent=n>99?'99+':fmt(n);b.setAttribute('aria-label',n?`Post öffnen · ${fmt(n)} ungelesen`:'Post öffnen');}
    function render(){
        if(loadedWorld!==world()){sequence++;detailSequence++;data=null;entries=[];error='';loading=false;loadedWorld=world();lastLoad=0;}
        draw();load(false);return true;
    }
    function draw(reset=false){
        if(!host())return;
        const old=host().querySelector('.mail-list'),scroll=reset?0:old?.scrollTop||0;
        const focused=host().contains(document.activeElement)?document.activeElement:null;
        const focusKey=focused?.dataset.action,focusId=focused?.dataset.id;
        const selected=category==='sent'?'private':category;
        const tabs=Object.entries(categories).map(([key,name])=>{const c=data?.counts?.[key],n=key==='starred'?c?.total:c?.unread;return `<button type="button" class="mail-tab" data-action="mailbox-tab" data-id="${key}" aria-pressed="${selected===key}" aria-label="${name}${n?' · '+fmt(n)+(key==='starred'?' gespeichert':' ungelesen'):''}"><span>${name}</span>${n?`<span class="mail-count ${key==='starred'?'saved':''}" aria-hidden="true">${n>99?'99+':fmt(n)}</span>`:''}</button>`;}).join('');
        const count=data?.counts?.[category]||{};
        host().innerHTML=`<section class="mailbox-shell" aria-label="Postfach"><nav class="mail-tabs" aria-label="Postbereiche">${tabs}</nav><div class="mail-toolbar"><div><strong>${esc(category==='sent'?'Gesendet':categories[category])} <small>${fmt(count.total||0)}</small></strong><p>${esc(descriptions[category])}</p></div><div class="mail-tools">${btn('✎ <span>Brief schreiben</span>','compose','','mail-compose-button')}${btn('↻','reload','aria-label="Post aktualisieren"','mail-reload')}</div></div>${selected==='private'?`<nav class="mail-folders" aria-label="Private Briefe">${btn('Posteingang','tab','data-id="private" aria-pressed="'+(category==='private')+'"')}${btn('Gesendet','tab','data-id="sent" aria-pressed="'+(category==='sent')+'"')}</nav>`:''}${error?`<p class="mail-error" role="alert">${esc(error)} ${btn('Erneut versuchen','reload')}</p>`:''}<div class="mail-list" tabindex="0" aria-label="Nachrichten in ${esc(categories[selected])}" aria-busy="${loading}">${entries.length?entries.map(card).join(''):`<div class="mail-empty"><img src="${base}/assets/art/hud/reports.svg" alt=""><h3>${loading?'Deine Post wird geladen …':'Hier ist noch keine Post'}</h3><p>${loading?'Einen Augenblick bitte.':category==='starred'?'Tippe auf den Stern einer Nachricht, um sie hier aufzubewahren.':'Neue Nachrichten erscheinen automatisch in diesem Bereich.'}</p></div>`}${data?.next?btn('Weitere Nachrichten laden','more','','mail-more'):''}</div><footer class="mail-footer">${btn('Gelesene löschen','delete-read',`title="Favoriten und offene Belohnungen bleiben erhalten" ${working||!count.total?'disabled':''}`,'mail-delete')}${btn('Alle lesen','read-all',`${working||!count.unread?'disabled':''}`)}${btn('Alle einsammeln','claim-all',`${working||!count.claimable?'disabled':''}`,'mail-collect')}<small>Für diesen Bereich · Favoriten und offene Belohnungen bleiben beim Löschen erhalten.</small></footer></section>`;
        const list=host().querySelector('.mail-list');list.scrollTop=scroll;list.addEventListener('scroll',()=>{if(data?.next&&!loading&&list.scrollHeight-list.scrollTop-list.clientHeight<100)load(true,true);},{passive:true});
        if(focusKey)host().querySelector(`[data-action="${CSS.escape(focusKey)}"]${focusId?'[data-id="'+CSS.escape(focusId)+'"]':''}`)?.focus({preventScroll:true});
        badge();
    }
    function card(r){
        const unread=!r.read_at,saved=Boolean(Number(r.starred)),reward=r.reward_status==='pending';
        const readLabel=unread?'Neu':'Gelesen';
        return `<article class="mail-card ${unread?'unread':'read'}" data-mail-id="${Number(r.id)}"><button type="button" class="mail-open" data-action="mailbox-open" data-id="${Number(r.id)}" aria-label="${esc(r.subject)} · ${esc(mailKind(r))} · ${readLabel}${reward?' · Belohnung abholbereit':''}"><span class="mail-art"><img src="${icon(r)}" alt="" loading="lazy"></span><span class="mail-copy"><strong>${esc(r.subject)}</strong><span class="mail-kind">${esc(mailKind(r))}</span><time>${esc(time(r.created_at))}</time></span></button><div class="mail-markers"><span class="mail-read-status" aria-hidden="true">${unread?'Neu':'✓ Gelesen'}</span>${reward?'<span class="mail-reward-dot" role="img" aria-label="Belohnung abholbereit" title="Belohnung abholbereit"></span>':''}${btn(star(saved),'star',`data-id="${Number(r.id)}" data-starred="${!saved}" aria-pressed="${saved}" aria-label="${saved?'Favorit entfernen':'Als Favorit speichern'}: ${esc(r.subject)}" ${working?'disabled':''}`,'mail-star')}</div></article>`;
    }
    async function load(force=false,more=false){
        if(loading||(!force&&Date.now()-lastLoad<10000)){badge();return;}
        const w=world(),c=category,seq=++sequence;loading=true;
        const keep=more?0:entries.length;let query=more?data?.next:null;
        if(active())draw();
        try{
            let next,rows=[];
            do{const params=new URLSearchParams({world_id:w,category:c,...query});next=await api('mailbox/state?'+params);if(seq!==sequence||w!==world()||c!==category)return;rows.push(...next.entries);query=next.next;}while(!more&&query&&rows.length<keep);
            if(seq!==sequence||w!==world()||c!==category)return;
            data=next;entries=more?[...entries,...rows.filter(r=>!entries.some(e=>Number(e.id)===Number(r.id)))]:rows;loadedWorld=w;lastLoad=Date.now();error='';
        }catch(e){if(seq===sequence)error=e.message;}
        finally{if(seq===sequence){loading=false;badge();if(active())draw();}}
    }
    async function execute(payload){
        if(working)return null;working=true;const w=world(),key=JSON.stringify({world_id:w,...payload});
        if(!requests.has(key))requests.set(key,crypto.randomUUID());
        if(active())draw();
        document.querySelectorAll('.mail-detail button,.mail-compose button').forEach(b=>b.disabled=true);
        try{
            const result=await api('community/action',{...payload,world_id:w,request_id:requests.get(key)});requests.delete(key);
            if(w!==world())return null;
            if(payload.action!=='mailbox.read')toast(result.message);
            error='';sequence++;loading=false;await load(true);if(payload.action==='mailbox.claim'||payload.action==='mailbox.claim_all')await refresh(false);
            return result;
        }catch(e){error=e.message;toast(e.message);return null;}
        finally{working=false;if(active())draw();document.querySelectorAll('.mail-detail button,.mail-compose button').forEach(b=>b.disabled=false);}
    }
    async function open(id){
        const seq=++detailSequence,w=world();
        try{const m=await api('mailbox/message?'+new URLSearchParams({id,world_id:w}));if(seq!==detailSequence||world()!==w||!active())return;detail=m;showDetail();if(!m.read_at){const result=await execute({action:'mailbox.read',mail_id:Number(id)});if(result&&detail===m)m.read_at=true;}}
        catch(e){toast(e.message);}
    }
    const rewardHtml=m=>{
        const r=m.metadata?.rewards||{},resources=r.resources||r;
        const rows=Object.entries(resourceNames).filter(([k])=>Number(resources[k])>0).map(([k,label])=>`<span>${esc(label)} <strong>${fmt(resources[k])}</strong></span>`);
        if(Number(r.item_code)>0)rows.push(`<span>${esc(m.metadata.item_name||'Gegenstand #'+r.item_code)} <strong>× ${fmt(r.quantity)}</strong></span>`);
        const items=Array.isArray(r.items)?r.items:Object.entries(r.items||{}).map(([item_code,quantity])=>({item_code,quantity}));
        for(const item of items)rows.push(`<span>${esc(item.name||'Gegenstand #'+item.item_code)} <strong>× ${fmt(item.quantity||item.count)}</strong></span>`);
        return rows.length?`<section class="mail-rewards"><h3>Belohnung</h3><div>${rows.join('')}</div><p>${esc(rewardLabels[m.reward_status]||'')}</p></section>`:'';
    };
    function battleHtml(m){
        const d=m.metadata?.details||{};
        if(m.source!=='battle'&&m.source!=='arena')return '';
        const troops=d.troops||[];
        const troopRows=Array.isArray(troops)?troops:Object.entries(troops).map(([code,sent])=>({code,sent}));
        const label=t=>ctx.getState()?.troop_defs?.find(u=>Number(u.code)===Number(t.code))?.name||t.name||'Truppen';
        const stats=Object.entries({army_power:'Armeemacht',required_power:'Benötigte Macht',attacker_damage:'Angriffsschaden',defender_strength:'Verteidigungsstärke',monster_hp_after:'Verbleibende Gegner-HP',lord_xp:'Hunter-XP',challenger_score:'Kampfkraft Herausforderer',opponent_score:'Kampfkraft Gegner'}).filter(([k])=>d[k]!=null).map(([k,v])=>`<div class="mail-stat"><span>${v}</span><strong>${fmt(d[k])}</strong></div>`).join('');
        const loot=d.resources_lost||d.loot||d.resources||{};
        const scoutExtra=m.metadata.scout?`${d.protected_resources?`<h3>Geschützte Vorräte</h3>${Object.entries(d.protected_resources).map(([k,v])=>`<div class="mail-stat"><span>${esc(resourceNames[k]||k)}</span><strong>${fmt(v)}</strong></div>`).join('')}`:''}${d.reinforcements?`<h3>Verstärkungen</h3>${Object.entries(d.reinforcements).map(([code,sent])=>`<p>${esc(label({code}))} · ${fmt(sent)}</p>`).join('')||'<p>Keine Verstärkungen gesichtet.</p>'}`:''}${d.treasures?`<h3>Relikte</h3>${d.treasures.map(t=>`<p>${esc(t.name||t.treasure_code)} · Stufe ${fmt(t.level)}</p>`).join('')||'<p>Keine ausgerüsteten Relikte.</p>'}`:''}${d.mastery?`<h3>Meisterschaft</h3>${(d.mastery.nodes||[]).filter(n=>n.level>0).map(n=>`<p>${esc(n.name)} · Stufe ${fmt(n.level)}</p>`).join('')||'<p>Keine Meisterschaftspunkte vergeben.</p>'}`:''}`:'';
        return `${d.reason?`<p>${esc(d.reason)}</p>`:''}${stats}${d.wall?`<p>Mauer: ${fmt(d.wall.durability)} / ${fmt(d.wall.durability_max)} HP</p>`:''}${troopRows.length?`<h3>${m.metadata.scout?'Gesichtete Truppen':'Truppen'}</h3><div class="mail-troops">${troopRows.map(t=>`<article><strong>${esc(label(t))}</strong><p>${fmt(t.sent)} ${m.metadata.scout?'gesichtet':'eingesetzt'}${t.survived!=null?' · '+fmt(t.survived)+' überlebt':''}${t.injured!=null?' · '+fmt(t.injured)+' verwundet':''}${t.dead!=null?' · '+fmt(t.dead)+' gefallen':''}</p></article>`).join('')}</div>`:''}${Object.keys(loot).length?`<h3>${m.metadata.scout?'Gesichtete Vorräte':d.resources_lost?'Verlorene Ressourcen':'Beute'}</h3><div class="mail-rewards"><div>${Object.entries(loot).map(([k,v])=>`<span>${esc(resourceNames[k]||k)} <strong>${fmt(v)}</strong></span>`).join('')}</div></div>`:''}${scoutExtra}${m.source==='arena'?'<p>Alle Truppen kehren unverletzt zurück.</p>':''}${(d.item_rewards||[]).map(i=>`<p>${esc(i.name)} × ${fmt(i.count)}</p>`).join('')}${m.source==='battle'&&!m.metadata.scout?'<p class="mail-note">Der Bericht zeigt das Ergebnis zum Kampfzeitpunkt. Truppen und Beute eines Angriffs kehren mit dem Rückmarsch zurück.</p>':''}`;
    }
    function showDetail(){
        const m=detail;if(!m)return;
        if(m.source==='battle'&&ctx.openMonsterReport?.(m))return;
        if(m.source==='battle'&&['city','rally'].includes(m.metadata?.details?.battle_kind)&&ctx.openPlayerReport){
            ctx.openPlayerReport({id:Number(m.source_id),created_at:m.created_at,target_x:m.metadata.x,target_y:m.metadata.y,outcome:m.metadata.details.outcome,details:m.metadata.details});return;
        }
        openDialog(`<h2>${esc(m.subject)}</h2><section class="mail-detail"><div class="mail-detail-scroll"><div class="mail-letter-heading"><img src="${icon(m)}" alt=""><div><strong>${esc(m.metadata.sender||'Dein Reich')}${m.metadata.recipient?' → '+esc(m.metadata.recipient):''}</strong><time>${esc(time(m.created_at))}</time></div></div><div class="mail-letter-body">${esc(m.body)}</div>${battleHtml(m)}${rewardHtml(m)}</div><footer class="mail-detail-actions">${btn('‹ Zur Post','back')}${btn(star(Boolean(Number(m.starred))),'detail-star',`data-id="${Number(m.id)}" data-starred="${!Number(m.starred)}" aria-pressed="${Boolean(Number(m.starred))}" aria-label="${Number(m.starred)?'Favorit entfernen':'Als Favorit speichern'}"`,'mail-star')}${m.source==='letter'?btn('Antworten','reply',`data-id="${Number(m.id)}"`):''}${m.reward_status==='pending'?btn('Abholen','claim',`data-id="${Number(m.id)}"`,'mail-collect'):''}</footer></section>`);
    }
    async function compose(reply=false){
        const w=world(),seq=++detailSequence;
        try{
            const community=await api('community/state?world_id='+w);if(w!==world()||seq!==detailSequence)return;
            if(reply&&detail)drafts.set(w,{player_id:String(detail.metadata.reply_id),subject:('Re: '+detail.subject).slice(0,100),body:''});
            const draft=drafts.get(w)||{};
            openDialog(`<h2>Brief schreiben</h2><form class="mail-compose" data-form="mailbox-compose"><div class="mail-compose-fields"><label>Empfänger<select name="player_id" required><option value="">Spieler auswählen</option>${community.players.map(p=>`<option value="${Number(p.id)}" ${String(p.id)===draft.player_id?'selected':''}>${esc(p.username)} · #${Number(p.id)}</option>`).join('')}</select></label><label>Betreff<input name="subject" maxlength="100" required value="${esc(draft.subject||'')}"></label><label>Nachricht<textarea name="body" maxlength="4000" rows="7" required>${esc(draft.body||'')}</textarea></label><small>Privat zwischen dir und dem Empfänger. Bis zu 100 Briefe pro Tag.</small></div><footer class="mail-detail-actions">${btn('‹ Zur Post','back')}<button type="submit" class="mail-button mail-collect">Brief senden</button></footer></form>`);
        }catch(e){toast(e.message);}
    }
    document.addEventListener('input',e=>{const f=e.target.closest('.mail-compose');if(f)drafts.set(world(),Object.fromEntries(new FormData(f)));});
    document.addEventListener('change',e=>{const f=e.target.closest('.mail-compose');if(f)drafts.set(world(),Object.fromEntries(new FormData(f)));});
    function onSubmit(f){
        if(f.dataset.form!=='mailbox-compose')return false;
        const values=Object.fromEntries(new FormData(f)),w=world();drafts.set(w,values);
        execute({action:'mail.send',...values,player_id:Number(values.player_id)}).then(result=>{if(!result||world()!==w)return;drafts.delete(w);document.querySelector('#game-dialog')?.close();category='sent';entries=[];lastLoad=0;draw(true);load(true);});return true;
    }
    function onClick(act,b){
        if(!act?.startsWith('mailbox-'))return false;
        const id=Number(b.dataset.id);
        if(act==='mailbox-back'){detailSequence++;document.querySelector('#game-dialog')?.close();return true;}
        if(working&&act!=='mailbox-tab')return true;
        if(act==='mailbox-tab'){
            if(!Object.hasOwn(categories,b.dataset.id)&&b.dataset.id!=='sent')return true;
            sequence++;detailSequence++;loading=false;category=b.dataset.id;entries=[];if(data)data={...data,next:null};error='';lastLoad=0;draw(true);load(true);
        }else if(act==='mailbox-reload')load(true);
        else if(act==='mailbox-more')load(true,true);
        else if(act==='mailbox-open')open(id);
        else if(act==='mailbox-compose')compose();
        else if(act==='mailbox-reply')compose(true);
        else if(act==='mailbox-star'||act==='mailbox-detail-star')execute({action:'mailbox.star',mail_id:id,starred:b.dataset.starred==='true'}).then(result=>{if(result&&act==='mailbox-detail-star'&&detail?.id==id&&document.querySelector('.mail-detail')){detail.starred=b.dataset.starred==='true'?1:0;showDetail();}});
        else if(act==='mailbox-claim')execute({action:'mailbox.claim',mail_id:id}).then(async result=>{if(result&&detail?.id==id&&document.querySelector('.mail-detail')){detail=await api('mailbox/message?'+new URLSearchParams({id,world_id:world()}));showDetail();}}).catch(e=>toast(e.message));
        else if(act==='mailbox-read-all'&&data?.snapshot)execute({action:'mailbox.read_all',category,snapshot:data.snapshot});
        else if(act==='mailbox-claim-all'&&data?.snapshot)execute({action:'mailbox.claim_all',category,snapshot:data.snapshot});
        else if(act==='mailbox-delete-read'&&data?.snapshot)execute({action:'mailbox.delete_read',category,snapshot:data.snapshot});
        return true;
    }
    return {render,onClick,onSubmit,refresh:()=>load(false),badge,select:id=>onClick('mailbox-tab',{dataset:{id}})};
};

window.ConquerCongress=function({base,esc,fmt,duration,openDialog,getState,marchPanel,action,toast}){
    let shrineId=null;
    const current=()=>shrineId===null?getState()?.congress:getState()?.shrines?.find(s=>Number(s.id)===shrineId);
    const stamp=s=>new Date(/(?:Z|[+-]\d\d:\d\d)$/.test(s||'')?s:String(s||'').replace(' ','T')+'Z');
    const eventActive=g=>!g.event||(g.event.active&&stamp(g.event.starts_at)<=Date.now()&&stamp(g.event.ends_at)>Date.now());
    const when=s=>new Intl.DateTimeFormat('de-DE',{weekday:'short',day:'2-digit',month:'2-digit',hour:'2-digit',minute:'2-digit',timeZoneName:'short'}).format(stamp(s));
    function open(){
        const g=current();if(!g){toast('Der Kongress wird noch geladen.');return;}
        const own=Number(g.alliance_id)>0&&Number(g.alliance_id)===Number(g.own_alliance_id);
        const status={neutral:'Unbesetzt',contested:'Wird gesichert',secured:'Unter Allianzführung'}[g.state]||'Unbesetzt';
        const remaining=g.contested_until?Math.max(0,(new Date(g.contested_until.replace(' ','T').replace(/(?<!Z)$/,'Z')).getTime()-Date.now())/1000):0;
        const img=matchMedia('(prefers-reduced-motion: reduce)').matches||document.body.classList.contains('reduced-motion')?'png':'webp';
        if(shrineId!==null){openShrine(g,img,status,remaining);return;}
        openDialog(`<h2>Kongress des Weltensees</h2><section class="congress-panel"><div class="congress-hero"><img src="${base}/assets/art/map/congress.${img}?v=zones1" alt="Schwebender Kongress über einer Felseninsel"><div><h3>${esc(status)}</h3><p>${esc(g.alliance_name?`[${g.alliance_tag||'–'}] ${g.alliance_name}`:'Noch keine Allianz führt den Kongress.')}</p><p>X ${Number(g.coord_x)} / Y ${Number(g.coord_y)}</p></div></div><div class="congress-stats"><div><small>Verteidiger</small><strong>${fmt(g.garrison_total??Object.values(g.npc_garrison||{}).reduce((a,b)=>a+Number(b),0))}</strong></div><div><small>${g.state==='contested'?'Noch halten':'Haltezeit'}</small><strong ${g.state==='contested'?`data-end="${esc(g.contested_until)}"`:''}>${duration(g.state==='contested'?remaining:Number(g.hold_seconds)||3600)}</strong></div><div><small>Deine Garnison</small><strong>${fmt(g.my_garrison?.total||0)}</strong></div></div><div class="congress-rules"><p>Gemeinsame Angriffe schwächen die Verteidiger dauerhaft.</p><p>Nach dem Sieg eine Stunde halten. Rivalen können angreifen; Truppenverluste sind möglich.</p>${!g.own_alliance_id?'<p>Für die Teilnahme brauchst du eine Allianz.</p>':own?'<p>Verstärke eure Garnison, um die Kontrolle zu verteidigen.</p>':''}</div><div class="congress-actions">${g.can_attack?'<button class="button danger" data-action="congress-attack">Angriff vorbereiten</button>':''}${g.can_garrison?'<button class="button gold" data-action="congress-garrison">Garnison verstärken</button>':''}${g.can_recall?'<button class="button secondary" data-action="congress-recall">Zurückrufen</button>':''}${!g.own_alliance_id?'<button class="button gold" data-action="dialog-tab" data-id="alliance">Allianz finden</button>':''}<button class="button secondary" data-action="share-coordinates" data-x="${Number(g.coord_x)}" data-y="${Number(g.coord_y)}">Koordinaten teilen</button></div></section>`);
    }
    function openShrine(g,img,status,remaining){
        const active=eventActive(g),event=g.event||{},element=['forest','ice','sand','lava'].includes(g.element)?g.element:'forest';
        const buttons=(g.can_attack&&active?'<button class="button danger" data-action="shrine-attack">Angriff vorbereiten</button>':'')+(g.can_garrison?'<button class="button gold" data-action="shrine-garrison">Verstärken</button>':'')+(g.can_recall?'<button class="button secondary" data-action="shrine-recall">Zurückrufen</button>':'');
        openDialog(`<h2>${esc(g.name)}</h2><section class="congress-panel shrine-panel" data-element="${element}"><div class="congress-hero"><img src="${base}/assets/art/map/shrine-${element}.${img}?v=shrines1" alt="${esc(g.name)}"><div><h3>${esc(status)}</h3><p>${esc(g.alliance_name?`[${g.alliance_tag||'–'}] ${g.alliance_name}`:'Noch keine Allianz hält diesen Schrein.')}</p><p>X ${Number(g.coord_x)} / Y ${Number(g.coord_y)}</p><span class="shrine-event-badge ${active?'is-active':''}">${active?'Event aktiv':'Friedenszeit'}</span></div></div><div class="congress-stats"><div><small>Verteidiger</small><strong>${fmt(g.garrison_total||0)}</strong></div><div><small>${g.state==='contested'?'Noch halten':'Haltezeit'}</small><strong ${g.state==='contested'?`data-end="${esc(g.contested_until)}"`:''}>${duration(g.state==='contested'?remaining:Number(g.hold_seconds)||3600)}</strong></div><div><small>Deine Garnison</small><strong>${fmt(g.my_garrison?.total||0)}</strong></div></div><div class="congress-rules"><strong>${esc(event.name||'Krieg der vier Schreine')}</strong><p class="shrine-event-time">${active?`Endet ${esc(when(event.ends_at))}`:event.next_starts_at?`Beginnt ${esc(when(event.next_starts_at))}`:'Der nächste Termin wird angekündigt.'}</p><p>${active?'Angriffe müssen vor Eventende eintreffen.':'Angriffe sind nur während des Schrein-Events möglich.'} Eine Stunde halten, um den Schrein zu sichern.</p><p>Besitz bleibt erhalten. Verstärkung und Rückruf sind jederzeit möglich.${!g.own_alliance_id?' Teilnahme nur mit Allianz.':''}</p></div><div class="congress-actions">${buttons}${!g.own_alliance_id?'<button class="button gold" data-action="dialog-tab" data-id="alliance">Allianz finden</button>':''}<button class="button secondary" data-action="share-coordinates" data-x="${Number(g.coord_x)}" data-y="${Number(g.coord_y)}">Koordinaten teilen</button></div></section>`);
    }
    function onClick(act,button){
        if(!act.startsWith('congress-')&&!act.startsWith('shrine-'))return false;
        const shrine=act.startsWith('shrine-');
        if(!shrine)shrineId=null;
        else if(button?.dataset.id)shrineId=Number(button.dataset.id);
        if(shrine){
            const g=current();if(!g){toast('Dieser Schrein ist noch nicht verfügbar.');return true;}
            if(act==='shrine-open')open();
            else if(act==='shrine-attack'||act==='shrine-garrison'){
                if(!(act==='shrine-attack'?g.can_attack&&eventActive(g):g.can_garrison)){open();return true;}
                marchPanel.open(g.id,act==='shrine-attack'?'shrine':'shrine-garrison',{target:g});
            }else if(act==='shrine-recall'&&g.can_recall)action(`shrines/${Number(g.id)}/recall`,{},'Deine Garnison kehrt nach Hause zurück.');
            return true;
        }
        const g=current();if(!g){toast('Der Kongress ist noch nicht verfügbar.');return true;}
        if(act==='congress-open')open();
        else if(act==='congress-attack'||act==='congress-garrison'){
            if(!(act==='congress-attack'?g.can_attack:g.can_garrison)){open();return true;}
            marchPanel.open(g.id,act==='congress-attack'?'congress':'congress-garrison',{target:g});
        }else if(act==='congress-recall'&&g.can_recall)action(`shrines/${Number(g.id)}/recall`,{},'Deine Garnison kehrt nach Hause zurück.');
        return true;
    }
    return {open,onClick};
};

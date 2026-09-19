/* Authenticated defense and army tools. Presets never reserve troops. */
window.ConquerDefense=function(ctx){
    'use strict';
    const {api,esc,fmt,duration,toast}=ctx;
    let data=null,section='wall',loading=null,busy=false;
    const host=()=>document.querySelector('#content');
    const root=()=>host()?.querySelector('.defense-panel');
    const resources={food:'Nahrung',lumber:'Holz',stone:'Stein',gold:'Gold'};
    const money=cost=>Object.entries(cost||{}).filter(([,v])=>Number(v)>0).map(([k,v])=>`${fmt(v)} ${resources[k]||k}`).join(' · ')||'Kostenlos';
    const button=(label,action,id='',extra='')=>`<button type="button" class="button secondary" data-action="defense-${action}" data-id="${esc(id)}" ${extra}>${esc(label)}</button>`;
    const timestamp=v=>v?new Date(String(v).replace(' ','T')+'Z').toLocaleString('de-DE'):'Unbegrenzt';
    const troopName=code=>data?.troops.find(t=>Number(t.code)===Number(code))?.name||String(code);
    const formTroops=()=>`<div class="defense-troop-grid">${data.troops.map(t=>`<label><span>${esc(t.name)} <small>T${Number(t.tier)} · ${fmt(t.available)} zu Hause</small></span><input type="number" inputmode="numeric" name="troop_${Number(t.code)}" min="0" max="${Number(data.limits.march_capacity)}" step="1" value="0" aria-label="Anzahl ${esc(t.name)}"></label>`).join('')}</div>`;
    async function load(){if(!loading)loading=api('defense/state').then(s=>{data=s;return s;}).finally(()=>loading=null);return loading;}
    function render(){
        if(root())return true;
        host().innerHTML='<section class="defense-panel" aria-busy="true"><p>Verteidigung wird geladen …</p></section>';
        load().then(()=>{if(root())paint();}).catch(e=>{if(root())root().innerHTML=`<p role="alert">${esc(e.message)}</p>${button('Erneut laden','refresh')}`;});return true;
    }
    function paint(){
        if(!root()||!data)return;
        root().removeAttribute('aria-busy');
        root().innerHTML=`<header class="section-heading"><h2>Stadtverteidigung & Armee</h2>${button('Aktualisieren','refresh')}</header><nav class="subtabs" aria-label="Verteidigungsbereiche">${Object.entries({wall:'Mauer & Schutz',support:'Späher & Verstärkung',formations:'Formationen',promotion:'Beförderung'}).map(([key,label])=>`<button type="button" class="subtab ${section===key?'active':''}" data-action="defense-tab" data-id="${key}" aria-pressed="${section===key}">${esc(label)}</button>`).join('')}</nav><div class="defense-body">${section==='wall'?wall():section==='support'?support():section==='formations'?formations():promotions()}</div>`;
    }
    function wall(){
        const w=data.wall,missing=w.durability_max-w.durability,shield=data.shield,until=[shield.expires_at,shield.beginner_until].filter(Boolean).sort().at(-1);
        return `<div class="defense-cards"><article class="panel"><h3>Stadtmauer · Stufe ${Number(w.level)}</h3><p><strong>${fmt(w.durability)} / ${fmt(w.durability_max)} HP</strong></p><progress value="${Number(w.durability)}" max="${Number(w.durability_max)}" aria-label="Mauerzustand"></progress><p>Verteidigungsbonus: ${Number(w.defense_buff).toLocaleString('de-DE')} %</p><p class="muted">Regeneriert 10 % pro Stunde. Ein verlorener Stadtangriff beschädigt die Mauer um 10 %. Bei einem Durchbruch wird die Stadt auf ein freies Feld versetzt.</p>${missing>0?`<form data-form="defense-wall"><label>HP reparieren<input type="number" name="hp_amount" min="1" max="${Number(missing)}" step="1" value="${Math.min(1000,missing)}" required></label><p>Je angefangene 1.000 HP: 100 Stein + 50 Holz.</p><button class="button gold" type="submit">Mauer reparieren</button></form>`:'<p>Die Mauer ist vollständig repariert.</p>'}</article><article class="panel"><h3>${shield.active?'Schutz aktiv':'Kein Schutzschild'}</h3><p>${shield.active?'Geschützt bis '+esc(timestamp(until)):'Andere Spieler können deine Stadt angreifen.'}</p><p class="muted">Schutzgegenstände aktivierst du im Inventar. Eigene Stadtangriffe und Spähaufträge heben den Schutz auf. Laufende ausgehende Angriffe verhindern einen neuen Schild.</p><button type="button" class="button secondary" data-action="tab" data-id="inventory">Zum Inventar</button>${data.anti_spy?.active?`<h3>Spähschutz aktiv</h3><p>Geschützt bis ${esc(timestamp(data.anti_spy.expires_at))}</p>`:""}<h3>Geschützte Ressourcen · ${(Number(data.protection_fraction)*100).toLocaleString('de-DE')} %</h3><p>${esc(money(data.protected_resources))}</p><p class="muted">Das Lager schützt eine feste Grundmenge. Forschung und ausgerüstete Relikte schützen zusätzlich einen Anteil vor Beutezügen.</p></article></div>`;
    }
    function support(){
        const list=data.reinforcements||[];
        return `<article class="panel"><h3>Späher oder Verstärkung aussenden</h3><p>Späher liefern einen Bericht über Mauer, Ressourcen, Truppen, Relikte und Meisterschaft. Verstärkungen verteidigen Städte deiner Allianz.</p><p class="muted">Ein Spähauftrag beendet deinen eigenen Schutz. Zielschutz und Allianzzugehörigkeit werden bei der Ankunft erneut geprüft.</p><form data-form="defense-dispatch"><div class="defense-fields"><label>Auftrag<select name="mission"><option value="scout">Spähen</option><option value="reinforce">Verstärken</option></select></label><label>Zielspieler-ID<input type="number" name="target_player_id" min="1" step="1" list="defense-targets" required placeholder="Spieler-ID"><datalist id="defense-targets">${data.targets.map(t=>`<option value="${Number(t.player_id)}">${esc(t.name)} · X ${Number(t.coord_x)} / Y ${Number(t.coord_y)}</option>`).join('')}</datalist></label></div><details><summary>Truppen für Verstärkung auswählen</summary><p>Späher benötigen keine Truppen.</p>${presetButtons('dispatch')}${formTroops()}</details><button class="button gold" type="submit">Auftrag starten</button></form></article><article class="panel"><h3>Stationierte Verstärkungen</h3>${list.length?list.map(r=>`<div class="defense-entry"><div><strong>${esc(r.sender_name)} → ${esc(r.target_name)}</strong><p>${Object.entries(r.troops).filter(([,n])=>Number(n)>0).map(([code,n])=>`${fmt(n)} ${esc(troopName(code))}`).join(' · ')||'Keine einsatzfähigen Truppen'}</p></div>${r.can_recall?button('Heimreise starten','recall',r.id):'<span class="badge">Verteidigt deine Stadt</span>'}</div>`).join(''):'<p>Zurzeit sind keine Verstärkungen stationiert.</p>'}<p class="muted">Ein Rückruf startet den Heimweg. Erst nach der Rückkehr sind die Truppen wieder in der Kaserne verfügbar.</p></article>`;
    }
    function presetButtons(){return data.formations.length?`<div class="button-row">${data.formations.map(f=>button(f.name||'Formation '+f.slot,'preset',f.slot)).join('')}</div>`:'';}
    function formations(){
        return `<article class="panel"><h3>Vier gespeicherte Formationen</h3><p class="muted">Gespeicherte Aufstellungen verändern keine Truppenbestände. Verfügbarkeit und Marschkapazität werden beim Aussenden geprüft.</p><div class="defense-presets">${data.formations.map(f=>`<div class="defense-entry"><span><strong>${Number(f.slot)} · ${esc(f.name||'Formation')}</strong><small>${fmt(Object.values(f.troops).reduce((a,n)=>a+Number(n),0))} Truppen</small></span><div class="button-row">${button('Bearbeiten','preset',f.slot)}${button('Löschen','delete',f.slot)}</div></div>`).join('')||'<p>Noch keine Formationen gespeichert.</p>'}</div><form data-form="defense-formation"><div class="defense-fields"><label>Speicherplatz<select name="slot">${[1,2,3,4].map(n=>`<option value="${n}">Formation ${n}</option>`).join('')}</select></label><label>Name<input name="name" maxlength="48" required placeholder="Zum Beispiel: Waldwache"></label></div>${formTroops()}<p>Maximal ${fmt(data.limits.march_capacity)} Truppen pro Formation.</p><button type="submit" class="button gold">Formation speichern</button></form></article>`;
    }
    function promotions(){
        const queue=data.promotions||[],busySlots=new Set([...(data.training_slots||[]),...queue.map(p=>Math.floor(Number(p.source_code)/100000)%10)]);
        return `<article class="panel"><h3>Veteranen zur nächsten Stufe befördern</h3><p>Ab Ausbildungsgebäude und Stadtzentrum Stufe 13. Die nächste Truppenstufe muss über ihre Gebäudevoraussetzungen freigeschaltet sein. Kosten: 70 % der Ausbildung; Zeit: 50 %. Forschungs- und Ausbildungsboni wirken mit.</p>${queue.map(p=>`<div class="defense-entry"><span><strong>${fmt(p.count)} ${esc(troopName(p.source_code))} → ${esc(troopName(p.target_code))}</strong><small>Fertig: ${esc(timestamp(p.finishes_at))}</small></span>${button('Abbrechen & erstatten','cancel-promotion',p.id)}</div>`).join('')}${data.training_busy?'<p class="muted">Ausbildung und Beförderung teilen sich den Platz ihres jeweiligen Gebäudes. Andere Truppenarten bleiben verfügbar.</p>':''}<div class="defense-promotion-list">${data.troops.filter(t=>t.promotion&&t.available>0).map(t=>{const p=t.promotion,disabled=busySlots.has(Number(t.type)||Math.floor(Number(t.code)/100000)%10)||!p.unlocked;return `<form data-form="defense-promotion" data-code="${Number(t.code)}"><div><strong>${esc(t.name)} → ${esc(p.name)}</strong><p>Je Truppe: ${esc(money(p.cost))} · ${esc(duration(p.duration_seconds))}</p>${!p.unlocked?`<small>Benötigt ${{barrack:'Kaserne',archery_range:'Schützenlager',stable:'Reiterhof'}[t.training_building]||'Ausbildungsgebäude'} Stufe ${Number(p.building_level)} und Stadtzentrum Stufe ${Number(p.castle_level)}.</small>`:''}</div><label>Anzahl<input name="count" type="number" min="1" max="${Math.min(Number(t.available),Number(p.max_count))}" step="1" value="1" required ${disabled?'disabled':''}></label><button class="button gold" type="submit" ${disabled?'disabled':''}>Befördern</button></form>`;}).join('')||'<p>Es sind keine beförderbaren Truppen in deiner Stadt.</p>'}</div></article>`;
    }
    async function mutate(payload){
        if(busy)return;busy=true;const controls=[...root()?.querySelectorAll('button,input,select')||[]].filter(e=>!e.disabled);controls.forEach(e=>e.disabled=true);
        try{const result=await api('defense/action',{city_id:Number(data.city_id),...payload});toast(result.message||'Gespeichert.');await load();if(root())paint();if(ctx.refresh)await ctx.refresh(false);}
        catch(e){toast(e.message);}finally{busy=false;controls.forEach(e=>{if(e.isConnected)e.disabled=false;});}
    }
    function onClick(act,b){
        if(!act.startsWith('defense-'))return false;
        const id=Number(b.dataset.id);
        if(act==='defense-tab'){section=b.dataset.id;paint();}
        if(act==='defense-refresh')load().then(()=>{if(root())paint();}).catch(e=>toast(e.message));
        if(act==='defense-recall')mutate({action:'reinforcement.recall',reinforcement_id:id});
        if(act==='defense-delete')mutate({action:'formation.delete',slot:id});
        if(act==='defense-cancel-promotion')mutate({action:'promotion.cancel',promotion_id:id});
        if(act==='defense-preset'){
            const p=data?.formations.find(f=>Number(f.slot)===id),f=root()?.querySelector('form[data-form="defense-formation"],form[data-form="defense-dispatch"]');
            if(p&&f){for(const input of f.querySelectorAll('[name^="troop_"]'))input.value=Number(p.troops[input.name.slice(6)]||0);if(f.elements.name)f.elements.name.value=p.name;if(f.elements.slot)f.elements.slot.value=p.slot;f.querySelector('input')?.focus();}
        }
        return true;
    }
    function onSubmit(form){
        if(!form.dataset.form?.startsWith('defense-'))return false;
        const f=new FormData(form),n=k=>Number(f.get(k)),troops=()=>Object.fromEntries([...f.entries()].filter(([k,v])=>k.startsWith('troop_')&&Number(v)>0).map(([k,v])=>[k.slice(6),Number(v)]));
        if(form.dataset.form==='defense-wall')mutate({action:'wall.repair',hp_amount:n('hp_amount')});
        if(form.dataset.form==='defense-dispatch')mutate({action:String(f.get('mission')),target_player_id:n('target_player_id'),troops:troops()});
        if(form.dataset.form==='defense-formation')mutate({action:'formation.save',slot:n('slot'),name:String(f.get('name')||''),troops:troops()});
        if(form.dataset.form==='defense-promotion')mutate({action:'promotion.start',troop_code:Number(form.dataset.code),count:n('count')});
        return true;
    }
    return {render,onClick,onSubmit,refresh:load,getFormations:()=>data?.formations||[]};
};

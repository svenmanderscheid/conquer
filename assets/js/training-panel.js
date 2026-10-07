/* Three schools, one server-authoritative training screen. */
window.ConquerTraining=function(ctx){
    'use strict';
    const {base,esc,fmt,getState,getKingdom,now,unitName,toast}=ctx;
    const schools={barrack:{type:1,name:'Kaserne',role:'Infanterie',icon:'⚔',art:'knight',description:'Standhafte Schilde für die vorderste Reihe.'},archery_range:{type:2,name:'Schützenlager',role:'Bogenschützen',icon:'🏹',art:'archer',description:'Präzise Pfeile aus der hinteren Reihe.'},stable:{type:3,name:'Reiterhof',role:'Kavallerie',icon:'♞',art:'rider',description:'Ein entschlossener Ansturm auf treuen Pferden.'}};
    for(const school of Object.values(schools))for(const field of ['name','role','description'])school[field]=window.ConquerLocale?.text(school[field])??school[field];
    const compact=n=>Math.abs(Number(n))>=10000?new Intl.NumberFormat(window.ConquerLocale?.locale??'en',{notation:'compact',maximumFractionDigits:1}).format(n):fmt(n);
    const statFormat=n=>new Intl.NumberFormat(window.ConquerLocale?.locale??'en',{maximumFractionDigits:1}).format(Number(n)||0);
    const roman=['I','II','III','IV','V','VI','VII','VIII','IX','X'];
    let building='barrack',tier=1,mode='train',pending=false,notice='',request=null,restored=false;
    const amounts=new Map(),seenJobs=new Map();
    try{const saved=sessionStorage.getItem('conquer-training-building');if(schools[saved])building=saved;sessionStorage.removeItem('conquer-training-building');}catch{}
    const root=()=>ctx.getHost().querySelector('.training-school');
    const def=()=>getState()?.troop_defs.find(t=>Number(t.type)===schools[building].type&&Number(t.tier)===tier);
    const schoolForCode=code=>getState()?.troop_defs.find(t=>Number(t.code)===Number(code))?.training_building||'barrack';
    const queueFor=code=>(getState()?.troop_queue||[]).find(q=>schoolForCode(q.troop_code)===code);
    const promotionFor=code=>(getState()?.training_promotions||[]).find(q=>schoolForCode(q.target_code)===code);
    const maximum=t=>Math.max(0,Math.min(Number(t?.training?.max_count)||0,...Object.entries(t?.training?.cost||{}).filter(([,value])=>Number(value)>0).map(([resource,value])=>Math.floor(Number(getState()?.city?.[resource]||0)/Number(value)))));
    const clock=end=>window.ConquerTrainingHud.formatTime(Math.max(0,(ctx.date(end)-now())/1000));
    const unlockRequirements=(t,state=getState())=>{
        const requirements=[];
        if(Number(state?.buildings?.[building]?.level||0)<Number(t.unlock_building||1))requirements.push({code:building,name:schools[building].name,current:Number(state?.buildings?.[building]?.level||0),required:Number(t.unlock_building||1),action:'building',image:'buildings/painted-v3/'+building+'.webp'});
        if(t.unlock_research){
            requirements.push({code:'academy',name:'Akademie',current:Number(state?.buildings?.academy?.level||0),required:Number(t.unlock_academy),action:'building',image:'buildings/painted-v3/academy.webp'});
            requirements.push({code:t.unlock_research,name:unitName(t)+' erforschen',current:Number(state?.research?.[t.unlock_research]||0),required:1,action:'research-dialog',image:'research/'+(['crossbow_man','sniper'].includes(t.unlock_research)?'characters-bow-v1':'characters-v10')+'/'+t.unlock_research+'.png'});
        }
        return requirements.map(r=>({...r,met:r.current>=r.required}));
    };
    const unlockMarkup=(t,state,compactView=false)=>{const requirements=unlockRequirements(t,state),next=requirements.find(r=>!r.met);return `<div class="training-unlock-heading"><strong>${compactView?'T'+tier+' freischalten':'Freischaltung von T'+tier}</strong><span>${compactView?'Voraussetzungen':'Erfülle alle Voraussetzungen'}</span></div><div class="training-unlock-requirements">${requirements.map(r=>`<button type="button" class="training-unlock-requirement ${r.met?'is-met':'is-missing'}" data-action="${r.action}" data-id="${r.code}" aria-label="${esc(r.name)}, aktuell Stufe ${r.current}, benötigt Stufe ${r.required}. Details öffnen"><img src="${base}/assets/art/${r.image}" alt=""><span class="training-unlock-copy"><strong>${esc(r.name)}</strong><small>${r.action==='research-dialog'?(r.met?'Erforscht':'Noch nicht erforscht'):'Stufe '+fmt(r.current)+' / '+fmt(r.required)}</small></span><span class="training-unlock-status" aria-hidden="true">${r.met?'✓':'!'}</span></button>`).join('')}</div>${!compactView&&next?`<button type="button" data-action="${next.action}" data-id="${next.code}">${next.action==='research-dialog'?'Zur Forschung':'Akademie ausbauen'} →</button>`:''}`;};
    const storeKey=()=>`conquer-training-request:${getState()?.city.world_id}:${getState()?.city.id}`;
    const writeRequest=value=>{request=value;try{value?sessionStorage.setItem(storeKey(),JSON.stringify(value)):sessionStorage.removeItem(storeKey());}catch{}};
    function restoreRequest(){if(restored)return;restored=true;try{const saved=JSON.parse(sessionStorage.getItem(storeKey())||'null');if(saved&&typeof saved.operation_key==='string'&&((saved.action==='inventory.use'&&saved.queue_type==='training')||(getState().troop_defs.some(t=>Number(t.code)===saved.troop_code)&&Number.isInteger(saved.count)))){request=saved;if(saved.troop_code){selectTroop(saved.troop_code);amounts.set(saved.troop_code,saved.count);}}}catch{}}
    function selectBuilding(code){if(!schools[code])return;building=code;const job=queueFor(code),unit=getState()?.troop_defs.find(t=>Number(t.code)===Number(job?.troop_code));if(unit)tier=Number(unit.tier);notice='';}
    function selectTroop(code){const unit=getState()?.troop_defs.find(t=>Number(t.code)===code);if(unit){building=unit.training_building;tier=Number(unit.tier);}}
    function render(){
        restoreRequest();
        const host=ctx.getHost(),t=def();if(!t)return;
        const school=schools[building],code=Number(t.code),key=building+':'+tier+':'+mode;
        if(!amounts.has(code))amounts.set(code,Math.max(1,maximum(t)));
        if(root()?.dataset.view===key){update();return;}
        const old=root();
        const focused=old?.contains(document.activeElement)?document.activeElement?.id:null;
        const statMax=key=>Math.max(1,...getState().troop_defs.map(unit=>Number(unit[key])||0));
        const previewStats=[['power','Stärke'],['attack','Angriff'],['defense','Verteidigung'],['march_speed','Tempo']].map(([key,label])=>[key,label,statMax(key)]);
        const troopArt=school.type===1?'guardian':school.type===2?'fire-archer-bow':'shadow-rider';
        const portraitArt=`characters/fantasy-troops-v3/${troopArt}-t${tier}-ui.webp`;
        const pageTitle=document.getElementById('page-title');if(pageTitle)pageTitle.textContent=window.ConquerLocale.t(({barrack:'english.37030144df0d515a',archery_range:'english.f9f458d58a965190',stable:'english.941825e78208a113'})[building]);
        host.innerHTML=`${ctx.armyHeader()}<section class="training-school" data-view="${key}" data-mode="${mode}" aria-label="${school.name}">
          <div class="training-workspace">
            <nav class="training-tier-picker" aria-label="Truppenstufe wählen"><div class="training-tier-heading"><strong>Truppenstufe</strong><span>T1–T5</span></div><div class="training-tiers">${getState().troop_defs.filter(x=>Number(x.type)===school.type).map(x=>`<button type="button" id="training-tier-${x.tier}" class="training-tier troop-tier-frame ${x.unlocked?'':'is-locked'}" data-tier="${x.tier}" data-troop-tier="${Number(x.tier)}" data-action="training-tier" data-id="${x.tier}" aria-pressed="${Number(x.tier)===tier}" aria-label="T${x.tier} ${esc(unitName(x))}${x.unlocked?'':', gesperrt'}"><img src="${base}/assets/art/characters/fantasy-troops-v3/${troopArt}-t${x.tier}-thumb.webp" alt=""><strong>T${Number(x.tier)}</strong><small>${x.unlocked?fmt(getState().troops[x.code]||0):'🔒'}</small></button>`).join('')}</div></nav>
            <div class="training-controls">
            <div class="training-detail-scroll">
              <div class="training-stats" ${mode==='stats'?'':'hidden'}>${[['power','Stärke'],['attack','Angriff'],['defense','Verteidigung'],['hp','Lebenspunkte'],['march_speed','Marschtempo'],['carry','Traglast']].map(([key,label])=>{const max=statMax(key);const bonus=Math.max(0,Number(t.stat_bonuses?.[key]||0));return `<div class="training-stat"><span>${label}</span><strong>${statFormat(t[key])}${bonus?` <em>+${fmt(bonus)}</em>`:''}</strong><progress max="${max}" value="${t[key]}" aria-label="${label}: ${t[key]}${bonus?`, Bonus plus ${bonus}`:''}"></progress></div>`}).join('')}<button type="button" class="training-stat-note" data-action="training-stat-help">ⓘ Grundwerte + aktive Boni</button></div>
              <div class="training-order" ${mode==='train'?'':'hidden'}>
                <div class="training-compose">
                  <div class="training-cost-grid" aria-label="Vorrat und Ausbildungskosten"><div class="training-cost-heading"><strong>Kosten für <b data-training-cost-count></b></strong><span>Bedarf / Vorrat</span></div>${Object.entries({food:'Nahrung',lumber:'Holz',stone:'Stein',gold:'Gold'}).map(([res,name])=>`<button type="button" class="training-cost" data-resource="${res}" data-action="training-resource" data-id="${res}"><img src="${window.ConquerItemArt?.resourceUrl(base,res) || `${base}/assets/art/ui-resources/${res}.png`}" alt=""><span>${name}</span><strong data-training-cost="${res}"></strong></button>`).join('')}</div>
                  <div class="training-quantity-heading"><strong>Menge</strong><span>Kapazität <b data-training-max></b></span></div><div class="training-quantity"><button type="button" data-action="training-step" data-id="-1" aria-label="Eine Einheit weniger">−</button><label class="training-count-label" for="train-count"><span>Anzahl</span><input id="train-count" type="number" inputmode="numeric" min="1" max="${t.training.max_count}" step="1" value="${amounts.get(code)||20}"></label><button type="button" data-action="training-step" data-id="1" aria-label="Eine Einheit mehr">+</button><button type="button" class="training-max" data-action="training-max" aria-label="Maximal leistbare Anzahl wählen">Max.</button><input type="range" id="training-range" min="1" max="${t.training.max_count}" value="${amounts.get(code)||20}" aria-label="Truppenanzahl"></div>
                  <div class="training-order-summary"><span>Machtzuwachs <b>+<span data-training-power></span></b></span><b data-training-duration hidden></b></div>
                </div>
                <div class="training-lock" data-training-lock></div>
                <div class="training-running" data-training-running></div>
                <div class="training-submit-row"><button type="button" id="train-confirm" class="training-submit" data-action="training-submit"><span>Ausbilden</span><strong data-training-button-time></strong></button><button type="button" class="training-stats-shortcut" data-action="training-mode" data-id="stats" aria-label="Einheitenwerte anzeigen" title="Einheitenwerte anzeigen">▥</button></div>
              </div>
              <div class="training-lock training-unconfirmed" data-training-request hidden><strong>Bestätigung fehlt</strong><span>Prüfe deinen gespeicherten Auftrag.</span><button type="button" data-action="training-retry">Auftrag prüfen</button></div>
              <p class="training-message" role="status" data-training-message></p>
            </div>
          </div><section class="training-showcase" aria-label="Einheitenansicht">
            <div class="training-unit-heading"><span class="training-role-icon" aria-hidden="true">${school.icon}</span><div><h3>${esc(unitName(t))}</h3><p>${school.name} · <span data-training-level></span></p></div><span class="training-rank">T${tier}</span><button type="button" class="training-upgrade" data-action="building" data-id="${building}" aria-label="${school.name} ausbauen" title="${school.name} ausbauen">↑</button></div>
            <div class="training-portrait"><img src="${base}/assets/art/${portraitArt}" alt="${esc(unitName(t))}"><section class="army-quick-actions" aria-label="Armee-Anpassung"><button type="button" class="button secondary" data-action="march-skins" aria-label="Marsch-Skins öffnen" title="Marsch-Skins öffnen"><span aria-hidden="true">✦</span><span class="training-skins-label">Marsch-Skins</span></button></section></div>
            <div class="training-preview-stats">${previewStats.map(([stat,label,max])=>{const bonus=Math.max(0,Number(t.stat_bonuses?.[stat]||0));return `<div class="training-preview-stat"><span>${label}</span><progress max="${max}" value="${t[stat]}" aria-label="${label}: ${t[stat]}${bonus?`, Bonus plus ${bonus}`:''}"></progress><strong>${fmt(t[stat])}${bonus?` <em>+${fmt(bonus)}</em>`:''}</strong></div>`}).join('')}</div><div class="training-stage-toolbar"><button type="button" class="training-manage" data-action="tab" data-id="defense" aria-label="Truppen verwalten" title="Truppen verwalten">⚑</button><span class="training-owned"><small>Bestand</small><strong data-training-owned></strong></span><button type="button" class="training-hospital-shortcut" data-action="army-hospital" aria-label="Hospital öffnen">✚</button><nav class="training-view-tabs" aria-label="Einheitendetails"><button type="button" data-action="training-mode" data-id="train" aria-pressed="${mode==='train'}">Ausbilden</button><button type="button" data-action="training-mode" data-id="stats" aria-pressed="${mode==='stats'}">Werte</button></nav></div>
          </section></div>
          <nav class="training-schools" aria-label="Ausbildungsgebäude">${Object.entries(schools).map(([key,s])=>`<button type="button" data-action="training-school" data-id="${key}" aria-pressed="${building===key}"><span aria-hidden="true">${s.icon}</span><strong>${s.role}</strong><small data-school-time="${key}"></small></button>`).join('')}</nav>
        </section>`;

        if(focused)document.getElementById(focused)?.focus({preventScroll:true});
        root().querySelector('#train-count').addEventListener('input',e=>setAmount(e.target.value,false));
        root().querySelector('#train-count').addEventListener('change',e=>setAmount(e.target.value,true));
        root().querySelector('#training-range').addEventListener('input',e=>setAmount(e.target.value,true));
        update();
        root().classList.add('has-illustration');
    }
    function setAmount(value,normalize){const t=def();let n=Number(value);if(normalize)n=Math.max(1,Math.min(t.training.max_count,Math.floor(n)||1));amounts.set(Number(t.code),n);if(normalize)root().querySelector('#train-count').value=String(n);root().querySelector('#training-range').value=String(n);update();}
    function update(){
        const state=getState();if(!state)return;
        for(const q of state.troop_queue||[])seenJobs.set(Number(q.id),{code:Number(q.troop_code),count:Number(q.count),before:Number(state.troops[q.troop_code]||0)});
        for(const [id,q] of seenJobs){if((state.troop_queue||[]).some(x=>Number(x.id)===id))continue;seenJobs.delete(id);if(Number(state.troops[q.code]||0)>=q.before+q.count){notice=`${fmt(q.count)} ${unitName(state.troop_defs.find(t=>Number(t.code)===q.code))} sind einsatzbereit.`;seenJobs.delete(id);toast(notice);}}
        const el=root(),t=def();if(!el||!t)return;
        const n=amounts.get(Number(t.code))??20,valid=Number.isInteger(n)&&n>=1&&n<=t.training.max_count;
        const cost=Object.fromEntries(Object.entries(t.training.cost).map(([k,v])=>[k,Math.ceil(v*Math.max(0,n||0))]));
        let affordable=true;for(const [k,v]of Object.entries(cost)){const item=el.querySelector(`[data-resource="${k}"]`);if(!item)continue;const enough=Number(state.city[k])>=v;affordable&&=enough;item.classList.toggle('insufficient',!enough);item.querySelector('strong').textContent=`${compact(v)} / ${compact(state.city[k])}`;item.setAttribute('aria-label',`${{food:'Nahrung',lumber:'Holz',stone:'Stein',gold:'Gold'}[k]}: ${fmt(state.city[k])} im Vorrat, ${fmt(v)} benötigt. Details anzeigen.`);}
        el.querySelector('[data-training-level]').textContent='Stufe '+state.buildings[building].level;
        el.querySelector('[data-training-owned]').textContent=fmt(state.troops[t.code]||0);
        for(const node of el.querySelectorAll('[data-training-max]'))node.textContent=fmt(t.training.max_count);
        el.querySelector('[data-training-cost-count]').textContent=fmt(Math.max(0,n||0));
        el.querySelector('#train-count').max=t.training.max_count;el.querySelector('#training-range').max=t.training.max_count;
        for(const unit of state.troop_defs.filter(x=>Number(x.type)===schools[building].type)){const tile=el.querySelector(`[data-action="training-tier"][data-tier="${unit.tier}"]`);if(tile){tile.querySelector('small').textContent=unit.unlocked?fmt(state.troops[unit.code]||0):'🔒';tile.classList.toggle('is-locked',!unit.unlocked);tile.setAttribute('aria-label',`T${unit.tier} ${unitName(unit)}${unit.unlocked?'':', gesperrt'}`);}}
        el.querySelector('[data-training-power]').textContent=fmt(t.power*Math.max(0,n||0));
        const seconds=Math.max(1,Math.ceil(t.time*Math.max(1,n||1)/t.training.speed_multiplier));
        el.querySelector('[data-training-duration]').textContent=ctx.duration(seconds);
        el.querySelector('[data-training-button-time]').textContent=window.ConquerTrainingHud.formatTime(seconds);
        const job=queueFor(building),promotion=promotionFor(building),running=job||promotion;
        el.dataset.orderState=request?'unconfirmed':running&&!t.unlocked?'running-locked':running?'running':!t.unlocked?'locked':'ready';
        const lock=el.querySelector('[data-training-lock]');lock.hidden=t.unlocked;
        if(!t.unlocked)lock.innerHTML=unlockMarkup(t,state);
        const active=el.querySelector('[data-training-running]');active.hidden=!running;
        if(running){
            const end=ctx.date(running.finishes_at),start=ctx.date(running.started_at),finished=end<=now(),signature=(promotion?'promotion:':'training:')+running.id;
            if(active.dataset.job!==signature){active.dataset.job=signature;active.innerHTML=`<div><strong>${promotion?'Beförderung':'Ausbildung'} läuft</strong><span>${fmt(running.count)} × ${esc(unitName(state.troop_defs.find(x=>Number(x.code)===Number(running.troop_code||running.target_code))))}</span><b data-training-clock></b></div><progress max="1" value="0" aria-label="Ausbildungsfortschritt"></progress><div class="training-running-unlock" data-training-running-unlock hidden></div>${job?`<div class="training-running-actions"><button type="button" class="training-speedup-open" data-action="training-speedups" data-id="${job.id}">Beschleunigen</button><button type="button" class="training-speedup-quick" data-action="training-speedups-quick" data-id="${job.id}" aria-label="QuickUse: passende Beschleuniger ohne Bestätigung verwenden" title="Passende Beschleuniger automatisch verwenden">QuickUse</button></div>`:''}`;}
            active.querySelector('[data-training-clock]').textContent=finished?'Abschluss wird bestätigt …':clock(running.finishes_at);
            active.querySelector('progress').value=Math.max(0,Math.min(1,(now()-start)/Math.max(1,end-start)));
            const runningUnlock=active.querySelector('[data-training-running-unlock]');runningUnlock.hidden=t.unlocked;if(!t.unlocked)runningUnlock.innerHTML=unlockMarkup(t,state,true);
            const speedupActions=active.querySelector('.training-running-actions');if(speedupActions)speedupActions.hidden=finished;active.querySelectorAll('.training-running-actions button').forEach(button=>button.disabled=pending);
        }else{active.replaceChildren();delete active.dataset.job;}
        const submit=el.querySelector('#train-confirm');submit.disabled=pending||!valid||!affordable||!t.unlocked||Boolean(running);
        submit.setAttribute('aria-busy',String(pending));submit.querySelector('span').textContent=pending?'Wird gespeichert …':running?'Gebäude beschäftigt':!t.unlocked?'Noch gesperrt':!valid?'Gültige Anzahl wählen':!affordable?'Rohstoffe fehlen':request?'Auftrag bestätigen':'Ausbilden';
        el.querySelector('[data-training-message]').textContent=request?'':notice;el.querySelector('[data-training-message]').title=notice;el.querySelector('[data-training-request]').hidden=!request;const retryButton=el.querySelector('[data-action="training-retry"]');retryButton.disabled=pending;retryButton.textContent=pending?'Wird geprüft …':'Auftrag prüfen';
        for(const [code,s]of Object.entries(schools)){const q=queueFor(code)||promotionFor(code);el.querySelector(`[data-school-time="${code}"]`).textContent=q?(ctx.date(q.finishes_at)<=now()?'Wird bestätigt':clock(q.finishes_at)):'Bereit';}
    }
    async function submit(retry=false){
        if(pending||(!retry&&root()?.querySelector('#train-confirm').disabled))return;
        const t=def(),count=amounts.get(Number(t.code))??20;
        if(!retry&&request&&(request.troop_code!==Number(t.code)||request.count!==count)){toast('Prüfe zuerst den noch unbestätigten Auftrag.');if(request.troop_code){selectTroop(request.troop_code);amounts.set(request.troop_code,request.count);}render();return;}
        if(!request)writeRequest({troop_code:Number(t.code),count,operation_key:crypto.randomUUID()});
        pending=true;notice='';update();
        try{const result=await ctx.api(request.action==='inventory.use'?'kingdom/action':'troops/train',request);writeRequest(null);document.querySelector('#game-dialog')?.close();notice=result.message;toast(notice);await ctx.refresh(false);render();}
        catch(e){notice=e.message;if(e.code&&e.code!=='TRAIN_UNCONFIRMED')writeRequest(null);await ctx.refresh(false);}
        finally{pending=false;update();}
    }
    function onClick(action,b){
        if(action==='training-stat-help'){ctx.openDialog('<h2>Truppenwerte</h2><p>Die Anzeige zeigt die Grundwerte einer Einheit. Forschung und aktive Boni wirken zusätzlich.</p><p>Tempo bezeichnet den Einheitenwert; die tatsächliche Marschzeit steht auf der Weltkarte.</p>');return true;}
        if(action==='training-resource'){const t=def(),n=amounts.get(Number(t.code))??20,res=b.dataset.id,names={food:'Nahrung',lumber:'Holz',stone:'Stein',gold:'Gold'};ctx.openDialog(`<h2>${names[res]}</h2><div class="detail-row"><span>Vorrat</span><strong>${fmt(getState().city[res])}</strong></div><div class="detail-row"><span>Bedarf für ${fmt(n)} Truppen</span><strong>${fmt(Math.ceil(t.training.cost[res]*n))}</strong></div>`);return true;}

        if(action==='training-school'){selectBuilding(b.dataset.id);amounts.delete(Number(def()?.code));render();return true;}
        if(action==='training-tier'){tier=Math.max(1,Math.min(5,Number(b.dataset.id)));amounts.delete(Number(def()?.code));notice='';render();return true;}
        if(action==='training-mode'){mode=b.dataset.id==='stats'?'stats':'train';render();return true;}
        if(action==='training-step'){setAmount((amounts.get(Number(def().code))??20)+Number(b.dataset.id),true);return true;}
        if(action==='training-max'){setAmount(Math.max(1,maximum(def())),true);return true;}
        if(action==='training-submit'){submit();return true;}
        if(action==='training-retry'){if(request)submit(true);return true;}
        return false;
    }
    setInterval(()=>{if(!document.hidden)update();},1000);
    return {render,update,onClick,submit,selectBuilding,selectTroop,selectTier:value=>tier=Math.max(1,Math.min(5,value)),canUseSpeedups(){restoreRequest();return !request&&!pending;}};
};

/* Resource-paid healing, shared timers, inventory speedups. */
window.ConquerHospital=function(ctx){
    'use strict';
    const {base,esc,fmt,date,troopLabel,button,action}=ctx,S=ctx.getState,K=ctx.getKingdom;
    const host=()=>document.querySelector('#content'),hospital=()=>K()?.hospital,units=()=>hospital()?.wounded||[];
    const resources={food:'Nahrung',lumber:'Holz',stone:'Stein',gold:'Gold'};
    const art={food:'production-food',lumber:'production-lumber',stone:'production-stone',gold:'production-gold'};
    const clock=n=>{n=Math.max(0,Math.ceil(n));return [Math.floor(n/3600),Math.floor(n/60)%60,n%60].map(v=>String(v).padStart(2,'0')).join(':');};
    const remaining=()=>Math.max(0,Math.ceil((date(hospital()?.active?.ends_at)-ctx.now())/1000));
    const image=(name)=>`<img src="${base}/assets/art/items/${name}.svg" alt="">`;
    let selection={},scope=null,signature='',lastBatch=null,healingPending=false;
    function reconcile(){
        const key=S()?.city?.world_id+':'+S()?.city?.id;
        if(scope!==key){scope=key;selection={};lastBatch=null;}
        // A finished batch opens the next waiting selection with sensible defaults.
        if(lastBatch&&!hospital()?.active)selection={};
        lastBatch=hospital()?.active?.batch_id||null;
        selection=Object.fromEntries(units().map(w=>[w.troop_code,Math.min(w.waiting_count,Math.max(0,selection[w.troop_code]??w.waiting_count))]));
    }
    function quote(){
        const cost=Object.fromEntries(Object.keys(resources).map(k=>[k,0]));let count=0,seconds=0;
        for(const w of units()){const n=selection[w.troop_code]||0;count+=n;seconds+=n*w.seconds_per_troop;for(const k in cost)cost[k]+=n*Number(w.resources[k]||0);}
        seconds=count?Math.max(1,Math.ceil(seconds)):0;
        return {cost,count,seconds,affordable:Object.entries(cost).every(([k,n])=>Number(S().city[k])>=n)};
    }
    function row(w){
        const code=Number(w.troop_code),unit=S().troop_defs.find(t=>Number(t.code)===code),name=troopLabel(w),n=selection[code],active=hospital().active;
        return `<article class="hospital-unit ${w.healing_count?'is-in-treatment':''}" data-hospital-unit="${code}"><div class="hospital-portrait troop-tier-frame" data-troop-tier="${Number(unit?.tier)||1}"><img src="${base}/assets/art/characters/tier-colors-v1/${({1:'infantry',2:'archer',3:'cavalry'})[unit?.type]||'infantry'}-t${Number(unit?.tier)||1}-thumb.webp" alt=""><span>T${Number(unit?.tier)||1}</span>${w.healing_count?'<b class="hospital-treatment-mark" aria-hidden="true">✚</b>':''}</div><div class="hospital-unit-body"><div class="hospital-unit-heading"><h3>${esc(name)}</h3><span>${fmt(w.count)} verwundet</span></div>${active?`<div class="hospital-treatment-label">${w.healing_count?`<strong>${fmt(w.healing_count)} in Behandlung</strong>`:''}${w.healing_count&&w.waiting_count?' <span aria-hidden="true">·</span> ':''}${w.waiting_count?`<span>${fmt(w.waiting_count)} warten</span>`:''}</div>`:`<div class="hospital-quantity"><input id="hospital-range-${code}" data-hospital-count="${code}" type="range" min="0" max="${w.waiting_count}" step="1" value="${n}" aria-label="${esc(name)} zum Heilen auswählen"><input id="hospital-number-${code}" data-hospital-count="${code}" type="number" inputmode="numeric" min="0" max="${w.waiting_count}" step="1" value="${n}" aria-label="Anzahl ${esc(name)} zum Heilen"></div>`}</div></article>`;
    }
    function render(){
        if(!hospital())return;reconcile();const h=hospital(),active=h.active;
        const scroll=host().querySelector('.hospital-list')?.scrollTop||0,focus=host().contains(document.activeElement)?document.activeElement.id:'';
        signature=JSON.stringify([scope,h]);
        host().innerHTML=`<section class="hospital-shell ${active?'is-healing':''}" aria-label="Hospital"><header class="hospital-summary"><div class="hospital-emblem" aria-hidden="true">✚</div><div class="hospital-capacity"><h2>${active?'Heilung läuft':'Verwundete'}</h2><div><strong>${fmt(h.used)}</strong><span> / ${fmt(h.capacity)} Plätze</span></div><progress max="${Math.max(h.capacity,h.used,1)}" value="${h.used}" aria-label="Belegte Hospitalplätze">${fmt(h.used)} / ${fmt(h.capacity)}</progress>${active?`<div class="hospital-active-note" role="status"><span aria-hidden="true">✚</span><strong>${fmt(active.count)} Truppen in Behandlung</strong><time data-hospital-summary-time></time></div>`:''}</div>${button('ⓘ','hospital-info','','secondary hospital-info','aria-label="Informationen zur Heilung"')}</header><div class="hospital-list" tabindex="0" aria-label="Verwundete Truppen auswählen">${units().length?units().map(row).join(''):`<div class="hospital-empty"><span aria-hidden="true">✚</span><h3>Alle Truppen sind gesund</h3><p>Verwundete Truppen werden hier versorgt.</p>${button('Zur Ausbildung','army-troops','','secondary')}</div>`}</div>${h.used?`<footer class="hospital-footer">${active?`<div class="hospital-running"><span><strong>${fmt(active.count)} Truppen</strong> in Behandlung</span><time data-hospital-time></time></div><progress class="hospital-treatment-progress" data-hospital-progress max="100" value="0" aria-label="Heilungsfortschritt"></progress><p class="hospital-feedback">Ressourcen bezahlt${h.waiting?' · '+fmt(h.waiting)+' Truppen warten':''}</p><div class="hospital-actions is-running">${button('Beschleunigen','hospital-speedups','','hospital-heal')}</div>`:`<div class="hospital-selection"><strong data-hospital-selected></strong>${button('Alle auswählen','hospital-select','','secondary small')}</div><div class="hospital-resources" data-hospital-resources></div><p class="hospital-feedback" data-hospital-feedback aria-live="polite"></p><div class="hospital-actions">${button('<span>Heilen</span><small data-hospital-duration></small>','hospital-heal','','hospital-heal')}</div>`}</footer>`:''}</section>`;
        host().querySelector('.hospital-list').scrollTop=scroll;if(focus)document.getElementById(focus)?.focus({preventScroll:true});update();
    }
    function update(){
        const shell=host()?.querySelector('.hospital-shell');if(!shell)return;
        const text=(selector,value)=>{const e=shell.querySelector(selector);if(e)e.textContent=value;};
        if(hospital().active){
            const n=remaining(),a=hospital().active;
            const time=n?clock(n):'Wird abgeschlossen …';
            text('[data-hospital-time]',time);text('[data-hospital-summary-time]',time);
            const bar=shell.querySelector('[data-hospital-progress]'),duration=Math.max(1,(date(a.ends_at)-date(a.started_at))/1000);if(bar)bar.value=Math.min(100,Math.max(0,100-n/duration*100));
            const speedup=shell.querySelector('[data-action="hospital-speedups"]');if(speedup&&!n)speedup.disabled=true;
            return;
        }
        const q=quote();text('[data-hospital-selected]',fmt(q.count)+' ausgewählt');text('[data-hospital-duration]',clock(q.seconds));
        const costs=shell.querySelector('[data-hospital-resources]');if(costs)costs.innerHTML=Object.entries(q.cost).filter(([,n])=>n>0).map(([k,n])=>`<div class="hospital-resource ${Number(S().city[k])<n?'is-missing':''}" aria-label="${resources[k]}: ${fmt(n)} benötigt, ${fmt(Math.floor(Number(S().city[k])))} vorhanden">${image(art[k])}<span><strong>${fmt(n)}<span class="hospital-resource-name"> ${resources[k]}</span></strong><small>${fmt(Math.floor(Number(S().city[k])))}<span class="hospital-stock-label"> vorhanden</span></small></span></div>`).join('');
        text('[data-hospital-feedback]',!q.count?'Wähle Truppen zum Heilen aus.':!q.affordable?'Nicht genügend Ressourcen.':'Heilen verbraucht Ressourcen und startet die Heilzeit.');
        const heal=shell.querySelector('[data-action="hospital-heal"]');if(heal){heal.disabled=healingPending||!q.count||!q.affordable;heal.toggleAttribute('aria-busy',healingPending);}
        const select=shell.querySelector('[data-action="hospital-select"]');if(select)select.textContent=q.count===hospital().waiting?'Auswahl leeren':'Alle auswählen';
        for(const w of units())shell.querySelectorAll(`[data-hospital-count="${w.troop_code}"]`).forEach(input=>{if(input!==document.activeElement)input.value=selection[w.troop_code];input.style.setProperty('--hospital-fill',(w.waiting_count?selection[w.troop_code]/w.waiting_count*100:0)+'%');});
    }
    function sync(){if(!host()?.querySelector('.hospital-shell'))return;reconcile();if(signature!==JSON.stringify([scope,hospital()]))render();else update();}
    function input(e){
        const el=e.target;if(!el.matches('[data-hospital-count]'))return;
        const w=units().find(w=>String(w.troop_code)===el.dataset.hospitalCount);if(!w)return;
        const n=Math.min(w.waiting_count,Math.max(0,Math.floor(Number(el.value)||0)));selection[w.troop_code]=n;if(e.type==='change'||el.value!=='')el.value=n;update();
    }
    document.addEventListener('input',input);document.addEventListener('change',input);
    function startHealing(quick=false){
        reconcile();
        if(hospital().active){if(quick)ctx.openHospital();return true;}
        if(quick)units().forEach(w=>selection[w.troop_code]=w.waiting_count);
        update();const q=quote();
        if(healingPending||!q.count)return true;
        if(!q.affordable){
            if(quick){ctx.openHospital();ctx.toast('Für die Heilung fehlen Rohstoffe. Passe die Auswahl im Hospital an.');}
            return true;
        }
        const troops=Object.fromEntries(Object.entries(selection).filter(([,n])=>n>0));
        // The shared command helper replaces the focused trigger's contents while it waits.
        // Keep the persistent HUD structure intact; the icon itself exposes the pending state.
        if(quick&&document.activeElement instanceof HTMLElement)document.activeElement.blur();
        healingPending=true;update();
        Promise.resolve(action('hospital/heal',{action:'hospital.heal',troops,expected_resources:q.cost,expected_seconds:q.seconds,operation_key:crypto.randomUUID(),expected_world_id:Number(S().city.world_id)},'Heilung gestartet.')).finally(()=>{healingPending=false;update();});
        return true;
    }
    function onClick(act){
        if(act==='hospital-select'){reconcile();const clear=quote().count===hospital().waiting;units().forEach(w=>selection[w.troop_code]=clear?0:w.waiting_count);update();return true;}
        if(act==='hospital-info'){ctx.openDialog(`<h2>Heilung im Hospital</h2><p>Wähle deine Verwundeten aus und starte die Heilung. Frühe Truppen werden besonders günstig und schnell versorgt; mit höheren Tiers steigen Aufwand und Heilzeit schrittweise.</p><p>Die angezeigten Ressourcen werden beim Start bezahlt. Heilungs- und allgemeine Beschleuniger verkürzen danach den laufenden Auftrag.</p>${button('Verstanden','close-dialog','','secondary wide')}`);return true;}
        if(act==='hospital-heal')return startHealing();
        if(act==='hospital-quick-heal')return startHealing(true);
        return false;
    }
    return {render,sync,onClick,updateTime:update};
};

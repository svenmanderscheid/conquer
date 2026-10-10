/* Authenticated defense and army tools. Presets never reserve troops. */
window.ConquerDefense=function(ctx){
    'use strict';
    const {api,esc,fmt,duration,toast}=ctx;
    const text=(key,fallback,parameters={})=>{const value=window.ConquerLocale?.t(key,parameters);return value&&value!==key?value:fallback.replace(/\{(\w+)\}/g,(match,name)=>parameters[name]??match);};
    const composition=window.ConquerFormationComposition;
    let data=null,section='wall',loading=null,busy=false;
    const host=()=>document.querySelector('#content');
    const root=()=>host()?.querySelector('.defense-panel');
    const resources={food:'Nahrung',lumber:'Holz',stone:'Stein',gold:'Gold'};
    const money=cost=>Object.entries(cost||{}).filter(([,v])=>Number(v)>0).map(([k,v])=>`${fmt(v)} ${window.ConquerLocale?.text(resources[k]||k)??resources[k]??k}`).join(' · ')||'Kostenlos';
    const button=(label,action,id='',extra='')=>`<button type="button" class="button secondary" data-action="defense-${action}" data-id="${esc(id)}" ${extra}>${esc(label)}</button>`;
    const timestamp=v=>v?new Date(String(v).replace(' ','T')+'Z').toLocaleString(window.ConquerLocale?.locale??'en'):'Unbegrenzt';
    const troopName=code=>{const name=data?.troops.find(t=>Number(t.code)===Number(code))?.name||String(code);return window.ConquerLocale?.text(name)||name;};
    const typeName=type=>text('formation.type.'+type,['','Infantry','Archers','Cavalry'][type]);
    const slotName=slot=>text('march.formation.default_name','Formation {slot}',{slot});
    const stocks=()=>Object.fromEntries(data.troops.map(t=>[t.code,Number(t.available)||0]));
    const countsOf=form=>Object.fromEntries([...form.querySelectorAll('[name^="troop_"]')].map(input=>[input.name.slice(6),Number(input.value)||0]));
    const typeArt=type=>`${ctx.base??window.CONQUER_BASE??''}/assets/art/characters/fantasy-troops-v3/${['','guardian','fire-archer-bow','shadow-rider'][type]}-t1-thumb.webp`;
    const mixBar=p=>`<div class="formation-mix-bar" aria-hidden="true">${composition.types.map(type=>`<i class="formation-type-${type}" style="flex-grow:${Number(p[type])||0}"></i>`).join('')}</div>`;
    const mixLabels=p=>`<div class="formation-mix-labels">${composition.types.map(type=>`<span><i class="formation-type-${type}"></i>${esc(typeName(type))} <b>${fmt(p[type]||0)} %</b></span>`).join('')}</div>`;
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
        bindEditor();
    }
    function wall(){
        const w=data.wall,missing=w.durability_max-w.durability,shield=data.shield,until=[shield.expires_at,shield.beginner_until].filter(Boolean).sort().at(-1);
        return `<div class="defense-cards"><article class="panel"><h3>Stadtmauer · Stufe ${Number(w.level)}</h3><p><strong>${fmt(w.durability)} / ${fmt(w.durability_max)} HP</strong></p><progress value="${Number(w.durability)}" max="${Number(w.durability_max)}" aria-label="Mauerzustand"></progress><p>Verteidigungsbonus: ${Number(w.defense_buff).toLocaleString(window.ConquerLocale?.locale??'en')} %</p><p class="muted">Regeneriert 10 % pro Stunde. Ein verlorener Stadtangriff beschädigt die Mauer um 10 %. Bei einem Durchbruch wird die Stadt auf ein freies Feld versetzt.</p>${missing>0?`<form data-form="defense-wall"><label>HP reparieren<input type="number" name="hp_amount" min="1" max="${Number(missing)}" step="1" value="${Math.min(1000,missing)}" required></label><p>Je angefangene 1.000 HP: 100 Stein + 50 Holz.</p><button class="button gold" type="submit">Mauer reparieren</button></form>`:'<p>Die Mauer ist vollständig repariert.</p>'}</article><article class="panel"><h3>${shield.active?'Schutz aktiv':'Kein Schutzschild'}</h3><p>${shield.active?'Geschützt bis '+esc(timestamp(until)):'Andere Spieler können deine Stadt angreifen.'}</p><p class="muted">Schutzgegenstände aktivierst du im Inventar. Eigene Stadtangriffe und Spähaufträge heben den Schutz auf. Laufende ausgehende Angriffe verhindern einen neuen Schild.</p><button type="button" class="button secondary" data-action="tab" data-id="inventory">Zum Inventar</button>${data.anti_spy?.active?`<h3>Spähschutz aktiv</h3><p>Geschützt bis ${esc(timestamp(data.anti_spy.expires_at))}</p>`:""}<h3>Geschützte Ressourcen · ${(Number(data.protection_fraction)*100).toLocaleString(window.ConquerLocale?.locale??'en')} %</h3><p>${esc(money(data.protected_resources))}</p><p class="muted">Das Lager schützt eine feste Grundmenge. Forschung und ausgerüstete Relikte schützen zusätzlich einen Anteil vor Beutezügen.</p></article></div>`;
    }
    function support(){
        const list=data.reinforcements||[];
        return `<article class="panel"><h3>Späher oder Verstärkung aussenden</h3><p>Späher liefern einen Bericht über Mauer, Ressourcen, Truppen, Relikte und Meisterschaft. Verstärkungen verteidigen Städte deiner Allianz.</p><p class="muted">Ein Spähauftrag beendet deinen eigenen Schutz. Zielschutz und Allianzzugehörigkeit werden bei der Ankunft erneut geprüft.</p><form data-form="defense-dispatch"><div class="defense-fields"><label>Auftrag<select name="mission"><option value="scout">Spähen</option><option value="reinforce">Verstärken</option></select></label><label>Zielspieler-ID<input type="number" name="target_player_id" min="1" step="1" list="defense-targets" required placeholder="Spieler-ID"><datalist id="defense-targets">${data.targets.map(t=>`<option value="${Number(t.player_id)}">${esc(t.name)} · X ${Number(t.coord_x)} / Y ${Number(t.coord_y)}</option>`).join('')}</datalist></label></div><details><summary>Truppen für Verstärkung auswählen</summary><p>Späher benötigen keine Truppen.</p>${presetButtons('dispatch')}${formTroops()}</details><button class="button gold" type="submit">Auftrag starten</button></form></article><article class="panel"><h3>Stationierte Verstärkungen</h3>${list.length?list.map(r=>`<div class="defense-entry"><div><strong>${esc(r.sender_name)} → ${esc(r.target_name)}</strong><p>${Object.entries(r.troops).filter(([,n])=>Number(n)>0).map(([code,n])=>`${fmt(n)} ${esc(troopName(code))}`).join(' · ')||'Keine einsatzfähigen Truppen'}</p></div>${r.can_recall?button('Heimreise starten','recall',r.id):'<span class="badge">Verteidigt deine Stadt</span>'}</div>`).join(''):'<p>Zurzeit sind keine Verstärkungen stationiert.</p>'}<p class="muted">Ein Rückruf startet den Heimweg. Erst nach der Rückkehr sind die Truppen wieder in der Kaserne verfügbar.</p></article>`;
    }
    function presetButtons(){return data.formations.length?`<div class="button-row">${data.formations.map(f=>button(f.name||slotName(f.slot),'preset',f.slot,'translate="no"')).join('')}</div>`:'';}
    function formations(){
        const slots=Array.from({length:Number(data.formation_slots)||6},(_,i)=>i+1),empty=slots.find(slot=>!data.formations.some(f=>Number(f.slot)===slot))||1;
        return `<section class="formation-workspace"><section class="formation-library"><header class="formation-intro"><h3>${esc(text('formation.saved_six','Six saved formations'))}</h3><p class="muted">${esc(text('formation.hint','Save troop amounts or a percentage mix. Saving never sends or reserves troops.'))}</p></header><div class="formation-library-legend">${composition.types.map(type=>`<span><i class="formation-type-${type}"></i>${esc(typeName(type))}</span>`).join('')}</div><div class="formation-saved-grid">${slots.map(slot=>{
            const saved=data.formations.find(f=>Number(f.slot)===slot),p=saved?.composition?.percentages||composition.percentages(data.troops,saved?.troops||{}),total=saved?.composition?.total||Object.values(saved?.troops||{}).reduce((sum,n)=>sum+Number(n),0);
            const name=saved?.name||slotName(slot),action=saved?'preset':'new';
            return `<article class="formation-saved-card ${saved?'':'is-empty'}" data-slot="${slot}"><button type="button" class="button secondary formation-slot-button" data-action="defense-${action}" data-id="${slot}" aria-pressed="false" aria-label="${esc(text(saved?'formation.edit':'formation.create',saved?'Edit':'Create formation')+' · '+slotName(slot)+' · '+name)}"><span class="formation-slot-number">${slot}</span><span class="formation-slot-info"><strong translate="no">${esc(name)}</strong><small>${esc(saved?text(saved.composition?'formation.target_count':'formation.troops_count',saved.composition?'Up to {count} troops':'{count} troops',{count:fmt(total)}):text('formation.empty','Empty slot'))}</small></span>${saved?`<span class="formation-slot-mix">${composition.types.map(type=>`<span title="${esc(typeName(type))}"><i class="formation-type-${type}"></i><span class="formation-sr-only">${esc(typeName(type))}</span>${fmt(p[type]||0)}%</span>`).join('')}</span>${mixBar(p)}`:'<span class="formation-slot-add" aria-hidden="true">+</span>'}</button></article>`;
        }).join('')}</div></section><form data-form="defense-formation" class="formation-editor" data-mode="percent">
            <header class="formation-editor-heading"><h3>${esc(text('formation.details','Formation details'))}</h3></header>
            <div class="formation-details-grid"><label>${esc(text('formation.slot','Save to slot'))}<select name="slot">${slots.map(slot=>`<option value="${slot}" ${slot===empty?'selected':''}>${esc(slotName(slot))}</option>`).join('')}</select></label><label>${esc(text('march.formation.name','Formation name'))}<input name="name" maxlength="48" required autocomplete="off" placeholder="${esc(text('formation.name_example','For example: Forest guard'))}"></label><div class="formation-total-field"><label>${esc(text('formation.total','Desired troop count'))}<input name="formation_total" type="number" min="1" max="${Number(data.limits.march_capacity)}" step="1" inputmode="numeric" value="${Number(data.limits.march_capacity)}" required></label>${button(text('formation.maximum','Max'),'formation-capacity','','aria-label="'+esc(text('formation.capacity','Use march capacity'))+'"')}</div></div>
            <section class="formation-distribution"><header class="formation-distribution-heading"><h4>${esc(text('formation.distribution','Troop distribution'))}</h4><div class="formation-mode-switch" role="group" aria-label="${esc(text('formation.mode','Composition mode'))}">${button(text('formation.mode_percent','Percentages'),'formation-mode','percent','aria-pressed="true"')}${button(text('formation.mode_amounts','Troop amounts'),'formation-mode','amounts','aria-pressed="false"')}</div></header>
                <div class="formation-percent-editor"><div class="formation-percent-grid">${composition.types.map(type=>`<section class="formation-percent-card"><header><img src="${typeArt(type)}" alt="" width="48" height="48"><strong>${esc(typeName(type))}</strong></header><div class="formation-percent-controls"><label class="formation-percent-value"><span class="formation-sr-only">${esc(text('formation.share','{type} percentage',{type:typeName(type)}))}</span><input type="number" inputmode="numeric" name="percent_${type}" min="0" max="100" step="1" value="${type===1?70:type===2?30:0}" required><b aria-hidden="true">%</b></label><label class="formation-range-label"><span class="formation-sr-only">${esc(text('formation.share','{type} percentage',{type:typeName(type)}))}</span><input type="range" min="0" max="100" step="1" value="${type===1?70:type===2?30:0}" data-percent-range="${type}"></label></div><small class="formation-type-preview" data-type-preview="${type}"></small></section>`).join('')}</div><div class="formation-percent-status" aria-live="polite"></div></div>
                <div class="formation-amount-editor" hidden>${formationTroops()}</div>
            </section><section class="formation-preview"><div class="formation-live-preview" aria-live="polite"></div><details class="formation-preview-details"><summary>${esc(text('formation.breakdown','Troop breakdown'))}</summary><p class="muted formation-ratio-hint">${esc(text('formation.ratio_hint','Highest available tiers first. If a troop type is missing, the army gets smaller and keeps your mix; 0% stays excluded.'))}</p><div class="formation-breakdown"></div></details></section>
            <footer class="formation-editor-footer"><span class="formation-save-note"></span>${button(text('formation.delete','Delete'),'delete','','hidden')}<button type="submit" class="button">${esc(text('march.formation.save','Save formation'))}</button></footer>
        </form></section>`;
    }
    function formationTroops(){return `<div class="formation-amount-grid">${composition.types.map(type=>`<section><h4>${esc(typeName(type))}</h4>${data.troops.filter(t=>composition.typeOf(t)===type).map(t=>`<label><span>${esc(troopName(t.code))}<small>T${Number(t.tier)} · ${esc(text('formation.available','{count} at home',{count:fmt(t.available)}))}</small></span><input type="number" inputmode="numeric" name="troop_${Number(t.code)}" min="0" max="${Number(data.limits.march_capacity)}" step="1" value="0" aria-label="${esc(text('formation.unit_amount','{name} count',{name:troopName(t.code)}))}"></label>`).join('')}</section>`).join('')}</div>`;}
    function editor(){return root()?.querySelector('form[data-form="defense-formation"]');}
    function focusEditor(form){const region=host(),surface=region.closest('dialog');if(surface)surface.scrollTop=0;const box=region.getBoundingClientRect(),scale=box.height/region.offsetHeight;region.scrollTop+=(form.getBoundingClientRect().top-box.top-8)/scale;form.elements.name.focus({preventScroll:true});}
    function selectedComposition(form){return {percentages:Object.fromEntries(composition.types.map(type=>[type,form.elements['percent_'+type].value===''?NaN:Number(form.elements['percent_'+type].value)])),total:Number(form.elements.formation_total.value)};}
    function updateEditor(){
        const form=editor();if(!form)return;
        const percent=form.dataset.mode==='percent',choice=selectedComposition(form),sum=Object.values(choice.percentages).reduce((a,n)=>a+n,0),percentValid=composition.types.every(type=>Number.isInteger(choice.percentages[type])&&choice.percentages[type]>=0&&choice.percentages[type]<=100)&&sum===100,valid=composition.valid(choice)&&choice.total<=Number(data.limits.march_capacity);
        form.querySelector('.formation-percent-editor').hidden=!percent;form.querySelector('.formation-amount-editor').hidden=percent;
        form.querySelectorAll('.formation-percent-editor input').forEach(input=>input.disabled=!percent);
        form.querySelector('.formation-total-field').hidden=!percent;form.elements.formation_total.disabled=!percent;form.querySelector('.formation-ratio-hint').hidden=!percent;
        form.querySelectorAll('.formation-amount-editor input').forEach(input=>input.disabled=percent);
        form.querySelectorAll('[data-action="defense-formation-mode"]').forEach(b=>b.setAttribute('aria-pressed',String(b.dataset.id===form.dataset.mode)));
        if(percent){const counts=valid?composition.allocate(data.troops,stocks(),Number(data.limits.march_capacity),choice):{};form.querySelectorAll('[name^="troop_"]').forEach(input=>input.value=counts[input.name.slice(6)]||0);}
        const counts=countsOf(form),total=Object.values(counts).reduce((a,n)=>a+n,0),byType=composition.totals(data.troops,counts),p=percent?choice.percentages:composition.percentages(data.troops,counts);
        const status=form.querySelector('.formation-percent-status');status.classList.toggle('is-invalid',!valid);status.textContent=text('formation.sum','Total: {sum} / 100%',{sum:fmt(Number.isFinite(sum)?sum:0)})+(!percentValid?' · '+text('formation.invalid_percentages','Enter whole percentages from 0 to 100, adding up to 100%.'):!valid?' · '+text('formation.invalid_total','Choose a troop count from 1 to {capacity}.',{capacity:fmt(data.limits.march_capacity)}):'');
        const available=composition.totals(data.troops,stocks());
        composition.types.forEach(type=>{form.querySelector(`[data-type-preview="${type}"]`).innerHTML=`<b>${esc(text('formation.selected_count','{count} selected',{count:fmt(byType[type])}))}</b><span>${esc(text('formation.available_count','{count} available',{count:fmt(available[type])}))}</span>`;form.querySelector(`[data-percent-range="${type}"]`).value=choice.percentages[type]||0;});
        const preview=form.querySelector('.formation-live-preview');preview.innerHTML=`<div class="formation-preview-total"><span>${esc(text('formation.current_army','Current army'))}</span><strong>${fmt(total)}</strong></div><small class="formation-preview-capacity">${esc(text('formation.capacity_count','March capacity: {count}',{count:fmt(data.limits.march_capacity)}))}</small><div class="formation-preview-mix">${mixBar(p)}${percent?'':mixLabels(p)}</div>${percent&&valid&&total<choice.total?`<p class="formation-shortage">${esc(text('formation.shortage','Only {actual} of {requested} troops fit this mix with your current stock.',{actual:fmt(total),requested:fmt(choice.total)}))}</p>`:''}`;
        form.querySelector('.formation-breakdown').innerHTML=composition.types.map(type=>`<section><h4>${esc(typeName(type))} · ${fmt(byType[type])}</h4>${data.troops.filter(t=>composition.typeOf(t)===type&&counts[t.code]>0).map(t=>`<p><span>T${Number(t.tier)} · ${esc(troopName(t.code))}</span><b>${fmt(counts[t.code])}</b></p>`).join('')||`<p class="muted">${esc(text('formation.none_selected','No troops selected'))}</p>`}</section>`).join('');
        const slot=Number(form.elements.slot.value),existing=data.formations.find(f=>Number(f.slot)===slot);
        root().querySelectorAll('.formation-saved-card').forEach(card=>{const selected=Number(card.dataset.slot)===slot;card.classList.toggle('is-selected',selected);card.querySelector('button').setAttribute('aria-pressed',String(selected));});
        const remove=form.querySelector('[data-action="defense-delete"]');remove.hidden=!existing;remove.dataset.id=slot;
        form.querySelector('.formation-save-note').textContent=existing?text('march.formation.replace_hint','This replaces the saved formation in slot {slot}.',{slot}):text('march.formation.empty_selected','Slot {slot} is empty',{slot});
        form.querySelector('[type="submit"]').textContent=existing?text('march.formation.replace','Replace formation'):text('march.formation.save','Save formation');
        form.querySelector('[type="submit"]').disabled=busy||(percent?!valid:!total||total>Number(data.limits.march_capacity));
    }
    function bindEditor(){const form=editor();if(!form)return;form.addEventListener('input',event=>{if(event.target.dataset.percentRange)form.elements['percent_'+event.target.dataset.percentRange].value=event.target.value;event.target.setCustomValidity?.('');updateEditor();});form.addEventListener('change',updateEditor);updateEditor();}
    function usePreset(p,form){
        const ratio=p.composition&&composition.valid(p.composition),counts=ratio?composition.allocate(data.troops,stocks(),Number(data.limits.march_capacity),p.composition):p.troops||{};
        for(const input of form.querySelectorAll('[name^="troop_"]'))input.value=Number(counts[input.name.slice(6)]||0);
        if(form===editor()){
            form.elements.name.value=p.name||slotName(p.slot);form.elements.slot.value=p.slot;form.dataset.mode=ratio?'percent':'amounts';
            const percentages=ratio?p.composition.percentages:composition.percentages(data.troops,counts);
            composition.types.forEach(type=>form.elements['percent_'+type].value=percentages[type]);
            form.elements.formation_total.value=ratio?p.composition.total:Math.max(1,Object.values(counts).reduce((a,n)=>a+Number(n),0));updateEditor();focusEditor(form);
        }
    }
    function promotions(){
        const queue=data.promotions||[],busySlots=new Set([...(data.training_slots||[]),...queue.map(p=>Math.floor(Number(p.source_code)/100000)%10)]);
        return `<article class="panel"><h3>Veteranen zur nächsten Stufe befördern</h3><p>Ab Ausbildungsgebäude und Stadtzentrum Stufe 13. Die nächste Truppenstufe muss in der Akademie erforscht sein. Kosten: 70 % der Ausbildung; Zeit: 50 %. Forschungs- und Ausbildungsboni wirken mit.</p>${queue.map(p=>`<div class="defense-entry"><span><strong>${fmt(p.count)} ${esc(troopName(p.source_code))} → ${esc(troopName(p.target_code))}</strong><small>Fertig: ${esc(timestamp(p.finishes_at))}</small></span>${button('Abbrechen & erstatten','cancel-promotion',p.id)}</div>`).join('')}${data.training_busy?'<p class="muted">Ausbildung und Beförderung teilen sich den Platz ihres jeweiligen Gebäudes. Andere Truppenarten bleiben verfügbar.</p>':''}<div class="defense-promotion-list">${data.troops.filter(t=>t.promotion&&t.available>0).map(t=>{const p=t.promotion,disabled=busySlots.has(Number(t.type)||Math.floor(Number(t.code)/100000)%10)||!p.unlocked;return `<form data-form="defense-promotion" data-code="${Number(t.code)}"><div><strong>${esc(t.name)} → ${esc(p.name)}</strong><p>Je Truppe: ${esc(money(p.cost))} · ${esc(duration(p.duration_seconds))}</p>${!p.unlocked?`<small>Benötigt ${{barrack:'Kaserne',archery_range:'Schützenlager',stable:'Reiterhof'}[t.training_building]||'Ausbildungsgebäude'} Stufe ${Number(p.building_level)} und Stadtzentrum Stufe ${Number(p.castle_level)}, Akademie Stufe ${Number(p.academy_level)} und die Forschung der Zieltruppe.</small>`:''}</div><label>Anzahl<input name="count" type="number" min="1" max="${Math.min(Number(t.available),Number(p.max_count))}" step="1" value="1" required ${disabled?'disabled':''}></label><button class="button gold" type="submit" ${disabled?'disabled':''}>Befördern</button></form>`;}).join('')||'<p>Es sind keine beförderbaren Truppen in deiner Stadt.</p>'}</div></article>`;
    }
    async function mutate(payload){
        if(busy)return;busy=true;const controls=[...root()?.querySelectorAll('button,input,select')||[]].filter(e=>!e.disabled);controls.forEach(e=>e.disabled=true);
        try{const result=await api('defense/action',{city_id:Number(data.city_id),...payload});toast(result.message||'Gespeichert.');await load();if(root())paint();if(ctx.refresh)await ctx.refresh(false);}
        catch(e){toast(e.message);}finally{busy=false;controls.forEach(e=>{if(e.isConnected)e.disabled=false;});updateEditor();}
    }
    function onClick(act,b){
        if(!act.startsWith('defense-'))return false;
        if(busy)return true;
        const id=Number(b.dataset.id);
        if(act==='defense-tab'){section=b.dataset.id;paint();}
        if(act==='defense-refresh')load().then(()=>{if(root())paint();}).catch(e=>toast(e.message));
        if(act==='defense-recall')mutate({action:'reinforcement.recall',reinforcement_id:id});
        if(act==='defense-delete')mutate({action:'formation.delete',slot:id});
        if(act==='defense-cancel-promotion')mutate({action:'promotion.cancel',promotion_id:id});
        if(act==='defense-preset'){
            const p=data?.formations.find(f=>Number(f.slot)===id),f=root()?.querySelector('form[data-form="defense-formation"],form[data-form="defense-dispatch"]');
            if(p&&f)usePreset(p,f);
        }
        if(act==='defense-new'){const form=editor();if(form){form.elements.slot.value=id;form.elements.name.value='';form.dataset.mode='percent';form.elements.percent_1.value=70;form.elements.percent_2.value=30;form.elements.percent_3.value=0;form.elements.formation_total.value=Number(data.limits.march_capacity);updateEditor();focusEditor(form);}}
        if(act==='defense-formation-capacity'){const form=editor();if(form){form.elements.formation_total.value=Number(data.limits.march_capacity);updateEditor();}}
        if(act==='defense-formation-mode'){const form=editor();if(form&&['percent','amounts'].includes(b.dataset.id)){if(b.dataset.id==='percent'){const counts=countsOf(form),total=Object.values(counts).reduce((a,n)=>a+n,0);if(total>0){const p=composition.percentages(data.troops,counts);composition.types.forEach(type=>form.elements['percent_'+type].value=p[type]);form.elements.formation_total.value=Math.min(total,Number(data.limits.march_capacity));}}form.dataset.mode=b.dataset.id;updateEditor();}}
        return true;
    }
    function onSubmit(form){
        if(!form.dataset.form?.startsWith('defense-'))return false;
        const f=new FormData(form),n=k=>Number(f.get(k)),troops=()=>Object.fromEntries([...f.entries()].filter(([k,v])=>k.startsWith('troop_')&&Number(v)>0).map(([k,v])=>[k.slice(6),Number(v)]));
        if(form.dataset.form==='defense-wall')mutate({action:'wall.repair',hp_amount:n('hp_amount')});
        if(form.dataset.form==='defense-dispatch')mutate({action:String(f.get('mission')),target_player_id:n('target_player_id'),troops:troops()});
        if(form.dataset.form==='defense-formation'){
            const name=String(f.get('name')||'').trim();if(!name){form.elements.name.setCustomValidity(text('march.formation.name_required','Enter a formation name.'));form.elements.name.reportValidity();return true;}
            const payload={action:'formation.save',slot:n('slot'),name};
            if(form.dataset.mode==='percent'){const choice=selectedComposition(form);if(!composition.valid(choice)||choice.total>Number(data.limits.march_capacity)){updateEditor();return true;}payload.composition=choice;}else payload.troops=troops();
            mutate(payload);
        }
        if(form.dataset.form==='defense-promotion')mutate({action:'promotion.start',troop_code:Number(form.dataset.code),count:n('count')});
        return true;
    }
    return {render,onClick,onSubmit,refresh:load,getFormations:()=>data?.formations||[]};
};

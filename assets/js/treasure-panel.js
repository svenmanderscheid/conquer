/* Reference equipment screen with a scrolling collection and world-specific presets. */
window.ConquerTreasures = function(ctx){
    'use strict';
    const {base,esc,fmt,getKingdom,action,navigate,toast}=ctx;
    const host=()=>document.querySelector('#content');
    const grades={normal:'Gewöhnlich',rare:'Selten',epic:'Episch',legendary:'Legendär',mythic:'Mythisch'};
    const bonusNames={all_attack:'Truppenangriff',all_defense:'Truppenverteidigung',all_hp:'Truppen-Lebenspunkte',cavalry_attack:'Kavallerieangriff',construction_speed:'Baugeschwindigkeit',food_production:'Nahrungsproduktion',gathering_speed:'Sammelgeschwindigkeit',gold_production:'Goldproduktion',hospital_capacity:'Lazarettkapazität',infantry_defense:'Infanterieverteidigung',infantry_hp:'Infanterie-Lebenspunkte',lumber_production:'Holzproduktion',march_capacity:'Marschkapazität',march_speed:'Marschgeschwindigkeit',ranged_attack:'Fernkampfangriff',research_speed:'Forschungsgeschwindigkeit',resource_protection:'Rohstoffschutz',stone_production:'Steinproduktion',training_speed:'Ausbildungsgeschwindigkeit',vs_monster_attack:'Angriff gegen Monster'};
    const treasurePresentation={60100001:['Magischer Dünger','manure.png'],60100002:['Amulett des Holzfällers','woodcutter.png'],60100003:['Steinamulett','stone-amulet.png'],60100004:['Klingender Geldbeutel','pouch.svg'],60100005:['Runenlederriemen','strap.svg'],60100006:['Federkappe','feather-cap.png'],60200001:['Eiserner Schild','iron-shield.png'],60200002:['Bogen des Jägers','hunters-bow.png'],60200003:['Kavalleriesporen','cavalry-spurs.png'],60200004:['Hammer des Baumeisters','builders-hammer.png'],60200005:['Foliant der Gelehrten','book.svg'],60200006:['Horn des Ausbilders','drillmasters-horn.png'],60300001:['Drachenschuppenschild','dragon-shield.png'],60300002:['Phönixfederbogen','phoenix-bow.png'],60300003:['Schattenklinge','shadow-blade.png'],60300004:['Stab des Erzmagiers','archmage-staff.png'],60300005:['Banner des Kriegsherrn','warlord-banner.png'],60300006:['Ernteidol','harvest-idol.png'],60400001:['Panzerhandschuh des Titanen','titan-gauntlet.png'],60400002:['Himmlischer Kompass','compass.svg'],60400003:['Auge des Orakels','oracles-eye.png'],60400004:['Blutmondtotem','blood-moon-totem.png'],60500001:['Krone der ewigen Flamme','prestige.svg'],60500002:['Schuppe der Weltschlange','serpent-scale.png']};
    let selectedSlot=1,selectedCode=null,selectedPreset=1,busy=false,detailOpen=false,tab='equipment',lastDrops=[];
    let resetRequested='';
    const data=()=>getKingdom()?.treasures;
    const chestData=()=>getKingdom()?.chests;
    const now=()=>ctx.now?ctx.now():Date.now();
    const stamp=v=>typeof v==='number'?v*1000:v?Date.parse(/[zZ]|[+-]\d\d:\d\d$/.test(v)?v:v.replace(' ','T')+'Z'):0;
    const remaining=v=>Math.max(0,Math.ceil((stamp(v)-now())/1000));
    const clock=v=>{const n=remaining(v);return [Math.floor(n/3600),Math.floor(n%3600/60),n%60].map(x=>String(x).padStart(2,'0')).join(':');};
    const items=()=>[...(data()?.items||[])].sort((a,b)=>Number(Boolean(b.is_unlocked))-Number(Boolean(a.is_unlocked))||num(b.level)-num(a.level)||num(a.treasure_code)-num(b.treasure_code));
    const name=i=>i.name_de||treasurePresentation[i.treasure_code]?.[0]||i.name||'Unbekanntes Relikt';
    const image=i=>base+'/assets/art/items/'+(i.icon||treasurePresentation[i.treasure_code]?.[1]||'compass.svg')+'?v='+encodeURIComponent(window.CONQUER_ITEM_ART_VERSION||'catalog3');
    const grade=i=>Object.hasOwn(grades,i.grade)?i.grade:'normal';
    const num=n=>Number(n||0);
    const value=n=>num(n).toLocaleString('de-DE',{maximumFractionDigits:2});
    const unit=key=>['march_capacity','hospital_capacity'].includes(key)?'':' %';
    const usable=i=>Boolean(i?.is_unlocked&&i.is_usable&&num(i.level)>0&&num(i.fragments)>0);
    const slotCount=()=>Math.max(0,Math.min(6,num(data()?.slots)));
    const onSlot=slot=>items().find(i=>num(i.equipped_slot)===slot);
    const button=(label,act,id='',cls='',extra='')=>`<button type="button" class="treasury-button ${cls}" data-action="treasury-${act}" data-id="${esc(id)}" ${extra}>${label}</button>`;
    function tile(i){return `<span class="treasury-tile grade-${grade(i)} ${i.is_unlocked?'':'is-locked'} ${i.icon_framed?'is-framed':''}"><span class="treasury-tile-level">${num(i.level)>0?'ST. '+fmt(i.level):'FRAGMENTE'}</span><img src="${image(i)}" alt="" loading="lazy"><span class="treasury-tile-count">${fmt(i.fragments)}<small> Fr.</small></span>${Number.isFinite(Number(i.stars))&&num(i.stars)>0?`<span class="treasury-tile-stars" aria-label="${num(i.stars)} Sterne">${'★'.repeat(Math.min(5,num(i.stars)))}</span>`:''}${i.equipped_slot?'<span class="treasury-tile-equipped" aria-label="Ausgerüstet">✓</span>':''}</span>`;}
    const presets=()=>data()?.presets||Array.from({length:5},(_,n)=>({slot:n+1,saved:false,items:Array(6).fill(null)}));
    const currentPreset=()=>presets().find(p=>num(p.slot)===selectedPreset);
    const presetMatches=p=>p?.saved&&Array.from({length:6},(_,n)=>num(onSlot(n+1)?.treasure_code)).every((code,n)=>code===num(p.items?.[n]));
    function render(){
        if(!data()){host().innerHTML='<div class="treasury-empty">Die Schatzkammer wird geladen.</div>';return;}
        if(tab==='chests'){renderChests();return;}
        const scroll=host().querySelector('.treasury-scroll')?.scrollTop||0;
        const bonusScroll=host().querySelector('.treasury-bonus-scroll')?.scrollTop||0;
        if(selectedSlot>slotCount())selectedSlot=Math.max(1,slotCount());
        const current=items().find(i=>num(i.treasure_code)===selectedCode);
        host().innerHTML=`<section class="treasury-shell treasury-reference" aria-label="Schatzkammer">${tabs()}<div class="treasury-workbench"><section class="treasury-library" aria-label="Alle Relikte"><div class="treasury-library-heading"><strong>Alle Relikte</strong><span>${items().length}</span></div><div class="treasury-scroll" tabindex="0" aria-label="Reliktsammlung, nach unten scrollen"><div class="treasury-reference-grid">${items().map(i=>`<button type="button" class="treasury-card ${num(i.treasure_code)===selectedCode?'is-selected':''}" data-action="treasury-select" data-id="${num(i.treasure_code)}" title="${esc(name(i))}" aria-label="${esc(name(i))}, ${grades[grade(i)]}, Stufe ${fmt(i.level)}, ${fmt(i.fragments)} Fragmente${i.equipped_slot?', Platz '+i.equipped_slot:''}" aria-pressed="${num(i.treasure_code)===selectedCode}">${tile(i)}</button>`).join('')}</div></div>${detailOpen&&current?`<div class="treasury-detail"><div class="treasury-detail-heading"><strong>Reliktdetails</strong>${button('×','detail-close','','', 'aria-label="Reliktdetails schließen"')}</div>${inspector(current)}</div>`:''}</section>${equipment()}</div></section>`;
        host().querySelector('.treasury-scroll').scrollTop=scroll;
        host().querySelector('.treasury-bonus-scroll').scrollTop=bonusScroll;
    }
    function tabs(){return `<nav class="treasury-main-tabs" aria-label="Schatzkammerbereiche">${button('Relikte','main-tab','equipment',tab==='equipment'?'active':'',`aria-pressed="${tab==='equipment'}"`)}${button('Schatztruhe','main-tab','chests',tab==='chests'?'active':'',`aria-pressed="${tab==='chests'}"`)}${button('St. '+fmt(data()?.house_level||0)+' ↑','upgrade','','treasury-house-upgrade','aria-label="Schatzkammer ausbauen"')}</nav>`;}
    function freeReady(type){
        const c=chestData();if(!c||busy||num(data()?.house_level)<1)return false;
        return type==='silver'?num(c.free_silver_remaining)>0&&remaining(c.free_silver_next_at)===0:remaining(c.free_gold_next_at)===0;
    }
    function renderChests(){
        const c=chestData();
        host().innerHTML=`<section class="treasury-shell treasury-reference" aria-label="Schatzkammer">${tabs()}<div class="treasury-chest-page">${c?`<div class="treasury-chest-intro"><strong>Tägliche Schätze</strong><span>Items, Beschleuniger, Boni und Reliktfragmente</span></div><div class="treasury-chest-cards">${['silver','gold'].map(type=>`<article class="treasury-chest-card chest-${type}"><div class="treasury-chest-art"><img src="${base}/assets/art/items/chest-${type}.svg?v=${encodeURIComponent(window.CONQUER_ITEM_ART_VERSION||'catalog3')}" alt="${type==='silver'?'Blaue Schatztruhe':'Goldene Schatztruhe'}"><span>${type==='silver'?fmt(c.free_silver_remaining)+' / 10':'1 ×'}</span></div><h3>${type==='silver'?'Blaue Schatztruhe':'Goldene Schatztruhe'}</h3><div class="treasury-chest-copy"><strong>${type==='silver'?'10 kostenlos pro Tag':'Alle 24 Stunden kostenlos'}</strong><p>${type==='silver'?'Zwischen zwei Öffnungen liegen 10 Minuten.':'Mit besseren Chancen auf wertvolle Beute.'}</p></div><div class="treasury-chest-clock"><small data-chest-label="${type}"></small><strong data-chest-clock="${type}" aria-live="off"></strong></div>${button('Kostenlos öffnen','chest-open',type,type==='silver'?'blue':'gold',freeReady(type)?'':'disabled')}</article>`).join('')}</div><div class="treasury-chest-footer"><span>${num(data()?.house_level)<1?'Baue zuerst deine Schatzkammer.':'Die Beute landet direkt in deinem Inventar oder deiner Reliktsammlung.'}</span>${lastDrops.length?`<div class="treasury-chest-rewards" aria-live="polite"><strong>Deine Beute</strong>${lastDrops.map(d=>{const i=d.type==='fragment'?items().find(i=>num(i.treasure_code)===num(d.treasure_code)):(getKingdom()?.inventory_catalog||[]).find(i=>num(i.item_code)===num(d.item_code));return `<span>+${fmt(d.quantity)} ${esc(d.name||i?.name_de||i?.name||'Gegenstand')}${d.type==='fragment'?' · Fragmente':''}</span>`;}).join('')}</div>`:''}</div>`:'<div class="treasury-empty">Schatztruhen werden geladen …</div>'}</div></section>`;
        updateTime();
    }
    function updateTime(){
        const c=chestData();if(!c||!host()?.querySelector('.treasury-chest-page'))return;
        for(const type of ['silver','gold']){
            const dailyEmpty=type==='silver'&&num(c.free_silver_remaining)<=0;
            const end=dailyEmpty?c.free_silver_resets_at:c[type==='silver'?'free_silver_next_at':'free_gold_next_at'];
            const label=host().querySelector('[data-chest-label="'+type+'"]'),timer=host().querySelector('[data-chest-clock="'+type+'"]'),b=host().querySelector('[data-action="treasury-chest-open"][data-id="'+type+'"]');
            if(label)label.textContent=dailyEmpty?'Tagesvorrat erneuert sich in':remaining(end)>0?'Nächste kostenlose Truhe in':'Bereit zum Öffnen';
            if(timer)timer.textContent=remaining(end)>0?clock(end):'Kostenlos';
            if(b)b.disabled=!freeReady(type);
        }
        if(c.free_silver_resets_at&&remaining(c.free_silver_resets_at)===0&&resetRequested!==c.free_silver_resets_at){resetRequested=c.free_silver_resets_at;ctx.refresh?.();}
    }
    async function openChest(type){
        if(!['silver','gold'].includes(type)||!freeReady(type))return;
        busy=true;render();
        try{const result=await action('kingdom/action',{action:'chest.free',chest_type:type},'Schatztruhe geöffnet.');lastDrops=result?.result?.drops||result?.drops||[];}
        catch(error){toast(error.message||'Die Schatztruhe konnte nicht geöffnet werden.');}
        finally{busy=false;if(host()?.querySelector('.treasury-shell'))render();}
    }
    function equipment(){
        const t=data(),levels=t.slot_unlock_levels||[1,1,5,10,20,25],p=currentPreset();
        return `<aside class="treasury-loadout" aria-label="Ausrüstung und Boni"><div class="treasury-preset-area"><div class="treasury-presets" aria-label="Fünf Ausrüstungsvorlagen">${presets().map(p=>button(String(p.slot),'preset',p.slot,(num(p.slot)===selectedPreset?'active ':'')+(p.saved?'is-saved':''),`aria-pressed="${num(p.slot)===selectedPreset}" aria-label="Vorlage ${p.slot}, ${p.saved?'gespeichert':'leer'}${presetMatches(p)?', aktiv':''}"`)).join('')}</div><div class="treasury-preset-status"><strong>Vorlage ${selectedPreset}</strong><span>${presetMatches(p)?'Aktiv':p?.saved?'Gespeichert':'Leer'}</span></div><div class="treasury-preset-actions">${button('Speichern','preset-save','','gold',busy?'disabled':'')}${button('Anlegen','preset-apply','','blue',busy||!p?.saved||presetMatches(p)?'disabled':'')}</div></div><div class="treasury-loadout-heading"><strong>Ausrüstung</strong><small>Schatzkammer St. ${fmt(t.house_level||0)}</small></div><div class="treasury-slots">${Array.from({length:6},(_,n)=>{const slot=n+1,i=onSlot(slot),locked=slot>slotCount();return `<button type="button" class="treasury-slot ${locked?'is-locked':i?'is-equipped':'is-empty'} ${slot===selectedSlot&&!locked?'is-selected':''}" data-action="treasury-slot" data-id="${slot}" ${locked?'disabled':''} aria-pressed="${slot===selectedSlot&&!locked}" aria-label="Platz ${slot}, ${locked?'gesperrt, Schatzkammer Stufe '+levels[n]:i?esc(name(i))+', ausgerüstet':'frei, Relikt wählen'}"><span class="treasury-slot-number">${slot}</span>${i?tile(i):`<span class="treasury-slot-placeholder" aria-hidden="true">${locked?'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v3"/></svg>':'+'}</span>`}<small>${locked?'St. '+fmt(levels[n]):i?'St. '+fmt(i.level):'Frei'}</small></button>`;}).join('')}</div><p class="treasury-slot-hint">Platz ${selectedSlot} wählen · links ein Relikt antippen</p>${bonuses()}</aside>`;
    }
    function inspector(i){
        const currentStats=i.is_unlocked?i.stats_at_level||{}:i.preview_stats||{},nextStats=i.is_unlocked?i.next_level_stats||{}:{},keys=Array.from(new Set([...Object.keys(currentStats),...Object.keys(nextStats)]));
        const target=onSlot(selectedSlot),equipped=num(i.equipped_slot)>0,eligible=usable(i)&&slotCount()>0;
        const maxLevel=num(i.level)>=num(i.max_level)&&num(i.max_level)>0,needed=num(i.fragments_next),fraction=needed?Math.min(100,num(i.fragments)/needed*100):100;
        const status=equipped?'Angelegt auf Platz '+i.equipped_slot:!i.is_unlocked?'Noch nicht freigeschaltet':!i.is_usable?'Sammlerrelikt · keine aktiven Boni':'Bereit zum Anlegen';
        return `<aside id="treasury-inspector" class="treasury-inspector" aria-labelledby="treasury-item-name"><div class="treasury-inspector-hero">${tile(i)}<div><small class="treasury-grade grade-${grade(i)}">${grades[grade(i)]}</small><h3 id="treasury-item-name">${esc(name(i))}</h3><span>${num(i.level)>0?'Stufe '+fmt(i.level)+' / '+fmt(i.max_level):'Nicht freigeschaltet'}</span></div></div><div class="treasury-fragments"><div><span>${maxLevel?'Maximale Stufe':'Fragmente für Stufe '+(num(i.level)+1)}</span><strong>${fmt(i.fragments)}${needed?' / '+fmt(needed):''}</strong></div><div class="treasury-progress" role="progressbar" aria-label="Reliktfragmente" aria-valuemin="0" aria-valuemax="${Math.max(1,needed||num(i.fragments))}" aria-valuenow="${Math.min(needed||num(i.fragments),num(i.fragments))}"><span style="width:${fraction}%"></span></div></div><div class="treasury-item-stats"><div class="treasury-stats-heading"><span>${i.is_unlocked?'Bonus auf dieser Stufe':'Bonus ab Stufe 1'}</span>${Object.keys(nextStats).length?'<small>→ nächste Stufe</small>':''}</div>${keys.length?keys.map(k=>`<div class="treasury-stat"><span>${esc(bonusNames[k]||k)}</span><strong>+${value(currentStats[k])}${unit(k)}${Object.hasOwn(nextStats,k)?` <em>→ ${value(nextStats[k])}${unit(k)}</em>`:''}</strong></div>`).join(''):'<p class="treasury-stat-empty">Dieses Relikt hat keine aktiven Boni.</p>'}</div><div class="treasury-inspector-footer"><p class="treasury-status ${equipped?'is-active':''}">${esc(status)}</p>${equipped?button('Ablegen · Platz '+i.equipped_slot,'unequip',i.treasure_code,'blue',busy?'disabled':''):eligible?`${target?`<p class="treasury-replace-note">Ersetzt ${esc(name(target))} auf Platz ${selectedSlot}.</p>`:''}${button(target?'Ersetzen · Platz '+selectedSlot:'Anlegen · Platz '+selectedSlot,'equip',i.treasure_code,'gold',busy?'disabled':'')}`:button('Fragmente finden','sources','','blue')}${!equipped&&usable(i)&&!slotCount()?'<small>Baue zuerst deine Schatzkammer.</small>':''}</div></aside>`;
    }
    function bonuses(){
        const all=Object.entries(data().bonuses||{}).filter(([,v])=>num(v)!==0);
        return `<section class="treasury-active-bonuses" aria-label="Aktive Reliktboni"><h3>Reliktboni</h3><div class="treasury-bonus-scroll" tabindex="0" aria-label="Alle aktiven Boni">${all.length?all.map(([key,v])=>`<div class="treasury-bonus-row"><span>${esc(bonusNames[key]||key)}</span><strong>+${value(v)}${unit(key)}</strong></div>`).join(''):'<div class="treasury-bonus-empty">Noch keine aktiven Boni.<small>Lege links ein freigeschaltetes Relikt an.</small></div>'}</div></section>`;
    }
    async function presetAction(operation){
        if(busy)return;
        if(operation==='apply'&&!currentPreset()?.saved)return;
        busy=true;render();
        try{await action('kingdom/action',{action:'treasure.preset_'+operation,preset:selectedPreset},operation==='save'?'Vorlage gespeichert.':'Vorlage angelegt.');}
        catch(error){toast(error.message||'Die Vorlage konnte nicht geändert werden.');}
        finally{busy=false;if(host()?.querySelector('.treasury-shell'))render();}
    }
    function focus(actionName,id){host().querySelector(`[data-action="treasury-${actionName}"][data-id="${id}"]`)?.focus({preventScroll:true});}
    async function mutate(operation,id){
        if(busy)return;
        const i=items().find(i=>num(i.treasure_code)===id);if(!i)return;
        if(operation==='equip'&&(!usable(i)||selectedSlot<1||selectedSlot>slotCount())){toast('Dieses Relikt kann hier noch nicht angelegt werden.');return;}
        if(operation==='unequip'&&!i.equipped_slot)return;
        busy=true;render();
        try{await action('kingdom/action',{action:'treasure.'+operation,treasure_code:id,...(operation==='equip'?{slot:selectedSlot}:{})},operation==='equip'?'Relikt angelegt.':'Relikt abgelegt.');}
        catch(error){toast(error.message||'Die Ausrüstung konnte nicht geändert werden.');}
        finally{busy=false;if(host()?.querySelector('.treasury-shell'))render();}
    }
    function onClick(act,b){
        if(!act.startsWith('treasury-'))return false;
        const id=b.dataset.id;if(b.disabled||busy)return true;
        if(act==='treasury-main-tab'&&['equipment','chests'].includes(id)){tab=id;render();focus('main-tab',id);}
        else if(act==='treasury-chest-open')void openChest(id);
        else if(act==='treasury-upgrade')ctx.onUpgrade?.();
        else if(act==='treasury-slot'){
            const slot=num(id);if(slot>=1&&slot<=slotCount()){selectedSlot=slot;const i=onSlot(slot);if(i){selectedCode=num(i.treasure_code);detailOpen=true;}render();focus('slot',slot);}
        }
        else if(act==='treasury-select'){selectedCode=num(id);detailOpen=true;render();host().querySelector('[data-action="treasury-detail-close"]')?.focus({preventScroll:true});}
        else if(act==='treasury-detail-close'){detailOpen=false;render();focus('select',selectedCode);}
        else if(act==='treasury-preset'){const n=num(id);if(n>=1&&n<=5){selectedPreset=n;render();focus('preset',n);}}
        else if(act==='treasury-preset-save')void presetAction('save');
        else if(act==='treasury-preset-apply')void presetAction('apply');
        else if(act==='treasury-equip'||act==='treasury-unequip')void mutate(act.slice(9),num(id));
        else if(act==='treasury-sources')navigate('inventory');
        return true;
    }
    return{render,onClick,updateTime,selectTab:value=>{if(['equipment','chests'].includes(value))tab=value;}};
};

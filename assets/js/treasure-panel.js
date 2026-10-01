/* Reference equipment screen with a scrolling collection and world-specific presets. */
window.ConquerTreasures = function(ctx){
    'use strict';
    const {base,esc,fmt,getKingdom,action,navigate,toast}=ctx;
    const host=()=>document.querySelector('#content');
    const grades={normal:'Gewöhnlich',rare:'Selten',epic:'Episch',legendary:'Legendär',mythic:'Mythisch'};
    const bonusNames={all_attack:'Truppenangriff',all_defense:'Truppenverteidigung',all_hp:'Truppen-Lebenspunkte',cavalry_attack:'Kavallerieangriff',cavalry_defense:'Kavallerieverteidigung',cavalry_hp:'Kavallerie-Lebenspunkte',cavalry_load:'Kavallerietraglast',cavalry_speed:'Kavalleriegeschwindigkeit',construction_speed:'Baugeschwindigkeit',food_gathering_speed:'Nahrung sammeln',food_production:'Nahrungsproduktion',food_protection_capacity:'Nahrungsschutz',food_storage_capacity:'Nahrungslager',gathering_speed:'Sammelgeschwindigkeit',gold_gathering_speed:'Gold sammeln',gold_production:'Goldproduktion',gold_protection_capacity:'Goldschutz',gold_storage_capacity:'Goldlager',hospital_capacity:'Lazarettkapazität',infantry_attack:'Infanterieangriff',infantry_defense:'Infanterieverteidigung',infantry_hp:'Infanterie-Lebenspunkte',infantry_load:'Infanterietraglast',infantry_speed:'Infanteriegeschwindigkeit',lumber_gathering_speed:'Holz sammeln',lumber_production:'Holzproduktion',lumber_protection_capacity:'Holzschutz',lumber_storage_capacity:'Holzlager',march_capacity:'Marschkapazität',march_speed:'Marschgeschwindigkeit',ranged_attack:'Fernkampfangriff',ranged_defense:'Fernkampfverteidigung',ranged_hp:'Fernkampf-Lebenspunkte',ranged_load:'Fernkampftraglast',ranged_speed:'Fernkampfgeschwindigkeit',research_speed:'Forschungsgeschwindigkeit',resource_production:'Rohstoffproduktion',resource_protection:'Rohstoffschutz',stone_gathering_speed:'Stein sammeln',stone_production:'Steinproduktion',stone_protection_capacity:'Steinschutz',stone_storage_capacity:'Steinlager',training_speed:'Ausbildungsgeschwindigkeit',vs_monster_attack:'Angriff gegen Monster'};
    const bonusDescriptions={
        all_attack:'Erhöht den Angriff aller eigenen Truppen.',all_defense:'Erhöht die Verteidigung aller eigenen Truppen.',all_hp:'Erhöht die Lebenspunkte aller eigenen Truppen.',
        cavalry_attack:'Erhöht den Angriff deiner Kavallerie.',construction_speed:'Verkürzt die Bauzeit neuer Gebäude und Ausbauten.',food_production:'Erhöht die Nahrungsproduktion deiner Stadt.',
        gathering_speed:'Erhöht die Sammelrate deiner Truppen auf Rohstofffeldern.',gold_production:'Erhöht die Goldproduktion deiner Stadt.',hospital_capacity:'Erhöht die Anzahl verwundeter Truppen, die dein Lazarett aufnehmen kann.',
        infantry_defense:'Erhöht die Verteidigung deiner Infanterie.',infantry_hp:'Erhöht die Lebenspunkte deiner Infanterie.',lumber_production:'Erhöht die Holzproduktion deiner Stadt.',
        march_capacity:'Erhöht die maximale Truppenanzahl pro Marsch.',march_speed:'Erhöht die Bewegungsgeschwindigkeit deiner Märsche.',ranged_attack:'Erhöht den Angriff deiner Fernkämpfer.',
        research_speed:'Verkürzt die Forschungszeit.',resource_protection:'Erhöht die Rohstoffmenge, die bei einem Angriff geschützt bleibt.',stone_production:'Erhöht die Steinproduktion deiner Stadt.',
        training_speed:'Verkürzt die Ausbildungszeit neuer Truppen.',vs_monster_attack:'Erhöht den Schaden deiner Truppen gegen Monster.'
    };
    const treasurePresentation={60100001:['Magischer Dünger','manure.png'],60100002:['Amulett des Holzfällers','woodcutter.png'],60100003:['Steinamulett','stone-amulet.png'],60100006:['Federkappe','feather-cap.png'],60200001:['Eiserner Schild','iron-shield.png'],60200002:['Bogen des Jägers','hunters-bow.png'],60200003:['Kavalleriesporen','cavalry-spurs.png'],60200004:['Hammer des Baumeisters','builders-hammer.png'],60200006:['Horn des Ausbilders','drillmasters-horn.png'],60300001:['Drachenschuppenschild','dragon-shield.png'],60300002:['Phönixfederbogen','phoenix-bow.png'],60300003:['Schattenklinge','shadow-blade.png'],60300004:['Stab des Erzmagiers','archmage-staff.png'],60300005:['Banner des Kriegsherrn','warlord-banner.png'],60300006:['Ernteidol','harvest-idol.png'],60400001:['Panzerhandschuh des Titanen','titan-gauntlet.png'],60400003:['Auge des Orakels','oracles-eye.png'],60400004:['Blutmondtotem','blood-moon-totem.png'],60500002:['Schuppe der Weltschlange','serpent-scale.png']};
    const relicPresentationV2={
        60100001:['Kornhorn der Ernte','rel-001-kornhorn-der-ernte.png','normal'],
        60100107:['Fass der Waldquelle','rel-002-fass-der-waldquelle.png','normal'],
        60100101:['Zeichen des Steinmetzen','rel-003-zeichen-des-steinmetzen.png','normal'],
        60100105:['Lampe der Tiefen','rel-004-lampe-der-tiefen.png','normal'],
        60100103:['Sichel der Kornpfade','rel-005-sichel-der-kornpfade.png','normal'],
        60100002:['Axt des Grünhains','rel-006-axt-des-gruenhains.png','normal'],
        60100003:['Meißel der Felsader','rel-007-meissel-der-felsader.png','normal'],
        60100102:['Spitzhacke der Goldspur','rel-008-spitzhacke-der-goldspur.png','normal'],
        60100104:['Klinge der Vorhut','rel-009-klinge-der-vorhut.png','normal'],
        60100108:['Bogen des Weitblicks','rel-010-bogen-des-weitblicks.png','normal'],
        60100106:['Speer des Sturmritts','rel-011-speer-des-sturmritts.png','normal'],
        60100109:['Schild der Eichenwacht','rel-012-schild-der-eichenwacht.png','normal'],
        60100006:['Kapuze des Falken','rel-013-kapuze-des-falken.png','normal'],
        60200003:['Sporn des Windreiters','rel-014-sporn-des-windreiters.png','normal'],
        60200102:['Ast des Vierfachen Segens','rel-015-ast-des-vierfachen-segens.png','rare'],
        60200106:['Pflugschar der Sommerflur','rel-016-pflugschar-der-sommerflur.png','rare'],
        60200107:['Säge des Flüsterwalds','rel-017-saege-des-fluesterwalds.png','rare'],
        60200104:['Runenmeißel der Tiefen','rel-018-runenmeissel-der-tiefen.png','rare'],
        60200105:['Sternenpicke','rel-019-sternenpicke.png','rare'],
        60300005:['Banner der Drei Heere','rel-020-banner-der-drei-heere.png','rare'],
        60200108:['Elixier des Löwenmuts','rel-021-elixier-des-loewenmuts.png','rare'],
        60200110:['Elixier der Eisenhaut','rel-022-elixier-der-eisenhaut.png','rare'],
        60200103:['Elixier des Lebensstroms','rel-023-elixier-des-lebensstroms.png','rare'],
        60200109:['Elixier des Windlaufs','rel-024-elixier-des-windlaufs.png','rare'],
        60200111:['Silberapfel der Eile','rel-025-silberapfel-der-eile.png','rare'],
        60200004:['Lastenhammer der Kolonne','rel-026-lastenhammer-der-kolonne.png','rare'],
        60200001:['Bastionsschild der Vorhut','rel-027-bastionsschild-der-vorhut.png','rare'],
        60200002:['Mondbogen der Waldwacht','rel-028-mondbogen-der-waldwacht.png','rare'],
        60200101:['Zaum des Nachtreiters','rel-029-zaum-des-nachtreiters.png','rare'],
        60300115:['Portalsphäre','rel-030-portalsphaere.png','epic'],
        60300116:['Amulett der Vier Quellen','rel-031-amulett-der-vier-quellen.png','epic'],
        60300006:['Sonnenähre','rel-032-sonnenaehre.png','epic'],
        60300109:['Goldblattbeil','rel-033-goldblattbeil.png','epic'],
        60300110:['Runenhammer der Hochmauer','rel-034-runenhammer-der-hochmauer.png','epic'],
        60300111:['Kronenpicke der Erzfürsten','rel-035-kronenpicke-der-erzfuersten.png','epic'],
        60300113:['Mal des Steinlöwen','rel-036-mal-des-steinloewen.png','epic'],
        60300119:['Pfeil des Sonnenfalken','rel-037-pfeil-des-sonnenfalken.png','epic'],
        60300120:['Lanze des Donnerhufs','rel-038-lanze-des-donnerhufs.png','epic'],
        60300003:['Klinge des Schwurschattens','rel-039-klinge-des-schwurschattens.png','epic'],
        60300102:['Panzer des Aschenwächters','rel-040-panzer-des-aschenwaechters.png','epic'],
        60400001:['Handschuh der Blutnacht','rel-041-handschuh-der-blutnacht.png','epic'],
        60300106:['Herzstein des Golems','rel-042-herzstein-des-golems.png','epic'],
        60300004:['Stab der Verdorrnis','rel-043-stab-der-verdorrnis.png','epic'],
        60300114:['Obsidianbastion','rel-044-obsidianbastion.png','epic'],
        60300118:['Schriftrolle der Erschöpfung','rel-045-schriftrolle-der-erschoepfung.png','epic'],
        60400004:['Nachtkern der Schweigenden','rel-046-nachtkern-der-schweigenden.png','epic'],
        60300112:['Brecher der Werkhallen','rel-047-brecher-der-werkhallen.png','epic'],
        60300101:['Amulett des Lebensquells','rel-048-amulett-des-lebensquells.png','epic'],
        60300107:['Vitalis-Smaragd','rel-049-vitalis-smaragd.png','epic'],
        60300117:['Kappe des Sternenweisen','rel-050-kappe-des-sternenweisen.png','epic'],
        60300108:['Gabe der Tiefenschmiede','rel-051-gabe-der-tiefenschmiede.png','epic'],
        60500002:['Schuppenpanzer des Himmelswurms','rel-052-schuppenpanzer-des-himmelswurms.png','epic'],
        60300103:['Reißzahn der Purpurflamme','rel-053-reisszahn-der-purpurflamme.png','epic'],
        60300104:['Phiole des Drachengeists','rel-054-phiole-des-drachengeists.png','epic'],
        60300105:['Glutblüte der Vorräte','rel-055-glutbluete-der-vorraete.png','epic'],
        60400108:['Seele des Unbezwungenen','rel-056-seele-des-unbezwungenen.png','legendary'],
        60400113:['Schwinge des Morgenritts','rel-057-schwinge-des-morgenritts.png','legendary'],
        60300001:['Schild des Firmaments','rel-058-schild-des-firmaments.png','legendary'],
        60400110:['Klinge der Letzten Dämmerung','rel-059-klinge-der-letzten-daemmerung.png','legendary'],
        60400107:['Frucht des Sternenhains','rel-060-frucht-des-sternenhains.png','legendary'],
        60400101:['Kelch der Erdgöttin','rel-061-kelch-der-erdgoettin.png','legendary'],
        60400104:['Kern der Weltenwurzel','rel-062-kern-der-weltenwurzel.png','legendary'],
        60200006:['Horn des Ersten Feldzugs','rel-063-horn-des-ersten-feldzugs.png','legendary'],
        60400105:['Herz des Polarsterns','rel-064-herz-des-polarsterns.png','legendary'],
        60400111:['Zügel des Heermeisters','rel-065-zuegel-des-heermeisters.png','legendary'],
        60400102:['Phiole des Kristallquells','rel-066-phiole-des-kristallquells.png','legendary'],
        60400112:['Siegel des Großen Baumeisters','rel-067-siegel-des-grossen-baumeisters.png','legendary'],
        60400106:['Speer des Herzensjägers','rel-068-speer-des-herzensjaegers.png','legendary'],
        60300002:['Bogen des Sternenwyrms','rel-069-bogen-des-sternenwyrms.png','legendary'],
        60400103:['Bollwerk des Wyrmbanns','rel-070-bollwerk-des-wyrmbanns.png','legendary'],
        60400109:['Prisma des Einklangs','rel-071-prisma-des-einklangs.png','legendary']
    };
    let selectedSlot=1,selectedCode=null,selectedPreset=1,busy=false,detailOpen=false,exchangeOpen=false,exchangeAmount=1,tab='equipment',lastDrops=[],mobileBonuses=false;
    let resetRequested='';
    const data=()=>getKingdom()?.treasures;
    const chestData=()=>getKingdom()?.chests;
    const now=()=>ctx.now?ctx.now():Date.now();
    const stamp=v=>typeof v==='number'?v*1000:v?Date.parse(/[zZ]|[+-]\d\d:\d\d$/.test(v)?v:v.replace(' ','T')+'Z'):0;
    const remaining=v=>Math.max(0,Math.ceil((stamp(v)-now())/1000));
    const clock=v=>{const n=remaining(v);return [Math.floor(n/3600),Math.floor(n%3600/60),n%60].map(x=>String(x).padStart(2,'0')).join(':');};
    const gradeRank={normal:0,rare:1,epic:2,legendary:3,mythic:4};
    const grade=i=>relicPresentationV2[i.treasure_code]?.[2]||(Object.hasOwn(grades,i.grade)?i.grade:'normal');
    const fragmentImage=g=>base+'/assets/art/items/fragment-'+(Object.hasOwn(grades,g)?g:'normal')+'.svg';
    const items=()=>[...(data()?.items||[])].sort((a,b)=>Number(Boolean(b.is_unlocked))-Number(Boolean(a.is_unlocked))||gradeRank[grade(a)]-gradeRank[grade(b)]||num(a.treasure_code)-num(b.treasure_code));
    const name=i=>{const value=relicPresentationV2[i.treasure_code]?.[0]||i.name_de||treasurePresentation[i.treasure_code]?.[0]||i.name||'Unbekanntes Relikt';return window.ConquerLocale?.text(value)??value;};
    const image=i=>relicPresentationV2[i.treasure_code]?base+'/assets/art/relics-v4-storybook/'+relicPresentationV2[i.treasure_code][1]+'?v=relics-v4-storybook':base+'/assets/art/items/'+(i.icon||treasurePresentation[i.treasure_code]?.[1]||'compass.svg')+'?v='+encodeURIComponent(window.CONQUER_ITEM_ART_VERSION||'catalog3');
    const num=n=>Number(n||0);
    const value=n=>num(n).toLocaleString(window.ConquerLocale?.locale??'en',{maximumFractionDigits:2});
    const unit=(key,kind='percent')=>kind==='flat'?'':' %';
    const usable=i=>Boolean(i?.is_unlocked&&i.is_usable&&num(i.level)>0);
    const slotCount=()=>Math.max(0,Math.min(6,num(data()?.slots)));
    const onSlot=slot=>items().find(i=>num(i.equipped_slot)===slot);
    const button=(label,act,id='',cls='',extra='')=>`<button type="button" class="treasury-button ${cls}" data-action="treasury-${act}" data-id="${esc(id)}" ${extra}>${label}</button>`;
    const effectStar=effect=>`<span class="treasury-effect-star" aria-label="${fmt(effect.parts)} von 5 Sternteilen">${Array.from({length:5},(_,n)=>`<i class="${n<num(effect.parts)?'is-filled':''}" aria-hidden="true"></i>`).join('')}</span>`;
    function tile(i,context='collection'){
        const unlocked=Boolean(i.is_unlocked),fragments=num(i.fragments),showProgress=context==='collection'&&!unlocked&&fragments>0;
        return `<span class="treasury-tile grade-${grade(i)} ${!relicPresentationV2[i.treasure_code]&&i.icon_framed?'is-framed':''} ${unlocked?'':'is-locked'}"><span class="treasury-tile-level">${unlocked?'ST. '+fmt(i.level):'🔒'}</span><img src="${image(i)}" alt="" loading="lazy">${showProgress?`<span class="treasury-tile-count"><img src="${fragmentImage(grade(i))}" alt="" aria-hidden="true"><strong>${fmt(fragments)}/10</strong></span>`:''}${context==='collection'&&Number.isFinite(Number(i.stars))&&num(i.stars)>0?`<span class="treasury-tile-stars" aria-label="${num(i.stars)} Sterne">${'★'.repeat(Math.min(5,num(i.stars)))}</span>`:''}${i.equipped_slot?'<span class="treasury-tile-equipped" aria-label="Ausgerüstet">✓</span>':''}</span>`;
    }
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
        host().innerHTML=`<section class="treasury-shell treasury-reference" aria-label="Schatzkammer">${tabs()}<div class="treasury-workbench"><section class="treasury-library" aria-label="Alle Relikte"><div class="treasury-library-heading"><strong>Alle Relikte</strong><span>${items().length}</span></div><div class="treasury-scroll" tabindex="0" aria-label="Reliktsammlung, nach unten scrollen"><div class="treasury-reference-grid">${items().map(i=>`<button type="button" class="treasury-card grade-${grade(i)} ${num(i.treasure_code)===selectedCode?'is-selected':''}" data-action="treasury-select" data-id="${num(i.treasure_code)}" title="${esc(name(i))}" aria-label="${esc(name(i))}, ${grades[grade(i)]}, Stufe ${fmt(i.level)}, ${fmt(i.fragments)} Fragmente${i.equipped_slot?', Platz '+i.equipped_slot:''}" aria-pressed="${num(i.treasure_code)===selectedCode}">${tile(i)}</button>`).join('')}</div></div>${detailOpen&&current?`<div class="treasury-detail"><div class="treasury-detail-heading"><strong>${grades[grade(current)]}es Relikt · ${esc(name(current))}</strong>${button('×','detail-close','','', 'aria-label="Reliktdetails schließen"')}</div>${inspector(current)}${exchangeOpen?fragmentExchange(current):''}</div>`:''}</section>${equipment()}</div></section>`;
        host().querySelector('.treasury-scroll').scrollTop=scroll;
        host().querySelector('.treasury-bonus-scroll').scrollTop=bonusScroll;
        const exchangeInput=host().querySelector('#treasury-exchange-amount');
        if(exchangeInput){
            const syncAmount=normalize=>{
                const available=num(data()?.universal_fragments?.[grade(current)]);
                const entered=Math.floor(Number(exchangeInput.value));
                exchangeAmount=Number.isFinite(entered)&&entered>=1?Math.min(entered,available):0;
                if(normalize){exchangeAmount=Math.max(1,Math.min(exchangeAmount||1,Math.max(1,available)));}
                if(normalize||entered>available)exchangeInput.value=String(exchangeAmount);
                host().querySelector('[data-exchange-result]')?.replaceChildren(document.createTextNode(fmt(exchangeAmount)));
                const submit=host().querySelector('[data-action="treasury-exchange"]');
                const minus=host().querySelector('[data-action="treasury-exchange-minus"]');
                const plus=host().querySelector('[data-action="treasury-exchange-plus"]');
                if(submit)submit.disabled=busy||exchangeAmount<1||exchangeAmount>available;
                if(minus)minus.disabled=exchangeAmount<=1;
                if(plus)plus.disabled=exchangeAmount>=available;
            };
            exchangeInput.addEventListener('input',()=>syncAmount(false));
            exchangeInput.addEventListener('change',()=>syncAmount(true));
        }
    }
    function tabs(){return `<nav class="treasury-main-tabs" aria-label="Schatzkammerbereiche">${button('Relikte','main-tab','equipment',tab==='equipment'?'active':'',`aria-pressed="${tab==='equipment'}"`)}${button('Schatztruhe','main-tab','chests',tab==='chests'?'active':'',`aria-pressed="${tab==='chests'}"`)}${button('St. '+fmt(data()?.house_level||0)+' ↑','upgrade','','treasury-house-upgrade','aria-label="Schatzkammer ausbauen"')}</nav>`;}
    function freeReady(type){
        const c=chestData();if(!c||busy||num(data()?.house_level)<1)return false;
        return type==='silver'?num(c.free_silver_remaining)>0&&remaining(c.free_silver_next_at)===0:remaining(c.free_gold_next_at)===0;
    }
    function renderChests(){
        const c=chestData();
        host().innerHTML=`<section class="treasury-shell treasury-reference" aria-label="Schatzkammer">${tabs()}<div class="treasury-chest-page">${c?`<div class="treasury-chest-intro"><strong>Tägliche Schätze</strong><span>Items, Beschleuniger, Boni und Reliktfragmente</span></div><div class="treasury-chest-cards">${['silver','gold'].map(type=>`<article class="treasury-chest-card chest-${type}"><div class="treasury-chest-art"><img src="${base}/assets/art/items/daily-chest-${type==='silver'?'blue':'gold'}-v1.png" alt="${type==='silver'?'Blaue Schatztruhe':'Goldene Schatztruhe'}"><span>${type==='silver'?fmt(c.free_silver_remaining)+' / 10':'1 ×'}</span></div><h3>${type==='silver'?'Blaue Schatztruhe':'Goldene Schatztruhe'}</h3><div class="treasury-chest-copy"><strong>${type==='silver'?'10 kostenlos pro Tag':'Alle 24 Stunden kostenlos'}</strong><p>${type==='silver'?'Zwischen zwei Öffnungen liegen 10 Minuten.':'Mit besseren Chancen auf wertvolle Beute.'}</p></div><div class="treasury-chest-clock"><small data-chest-label="${type}"></small><strong data-chest-clock="${type}" aria-live="off"></strong></div>${button('Kostenlos öffnen','chest-open',type,type==='silver'?'blue':'gold',freeReady(type)?'':'disabled')}</article>`).join('')}</div><div class="treasury-chest-footer"><span>${num(data()?.house_level)<1?'Baue zuerst deine Schatzkammer.':'Die Beute landet direkt in deinem Inventar oder deiner Reliktsammlung.'}</span>${lastDrops.length?`<div class="treasury-chest-rewards" aria-live="polite"><strong>Deine Beute</strong>${lastDrops.map(d=>{const i=d.type==='fragment'?items().find(i=>num(i.treasure_code)===num(d.treasure_code)):(getKingdom()?.inventory_catalog||[]).find(i=>num(i.item_code)===num(d.item_code));return `<span>+${fmt(d.quantity)} ${esc(d.name||i?.name_de||i?.name||'Gegenstand')}${d.type==='fragment'?' · Fragmente':''}</span>`;}).join('')}</div>`:''}</div>`:'<div class="treasury-empty">Schatztruhen werden geladen …</div>'}</div></section>`;
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
        return `<aside class="treasury-loadout" aria-label="Ausrüstung und Boni"><div class="treasury-preset-area"><div class="treasury-presets" aria-label="Fünf Ausrüstungsvorlagen">${presets().map(p=>button(String(p.slot),'preset',p.slot,(num(p.slot)===selectedPreset?'active ':'')+(p.saved?'is-saved':''),`aria-pressed="${num(p.slot)===selectedPreset}" aria-label="Vorlage ${p.slot}, ${p.saved?'gespeichert':'leer'}${presetMatches(p)?', aktiv':''}"`)).join('')}</div><div class="treasury-preset-status"><strong>Vorlage ${selectedPreset}</strong><span>${presetMatches(p)?'Aktiv':p?.saved?'Gespeichert':'Leer'}</span></div><div class="treasury-preset-actions">${button('Speichern','preset-save','','gold',busy?'disabled':'')}${button('Anlegen','preset-apply','','blue',busy||!p?.saved||presetMatches(p)?'disabled':'')}</div></div><div class="treasury-loadout-heading"><strong>Ausrüstung</strong><small>Schatzkammer St. ${fmt(t.house_level||0)}</small></div><div class="treasury-slots">${Array.from({length:6},(_,n)=>{const slot=n+1,i=onSlot(slot),locked=slot>slotCount();return `<button type="button" class="treasury-slot ${locked?'is-locked':i?'is-equipped':'is-empty'} ${slot===selectedSlot&&!locked?'is-selected':''}" data-action="treasury-slot" data-id="${slot}" ${locked?'disabled':''} aria-pressed="${slot===selectedSlot&&!locked}" aria-label="Platz ${slot}, ${locked?'gesperrt, Schatzkammer Stufe '+levels[n]:i?esc(name(i))+', ausgerüstet':'frei, Relikt wählen'}"><span class="treasury-slot-number">${slot}</span>${i?tile(i,'equipment'):`<span class="treasury-slot-placeholder" aria-hidden="true">${locked?'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v3"/></svg>':'+'}</span>`}<small>${locked?'St. '+fmt(levels[n]):i?esc(name(i)):'Frei'}</small></button>`;}).join('')}</div><p class="treasury-slot-hint">Platz ${selectedSlot} wählen · ein Relikt antippen</p>${button(mobileBonuses?'Boni ausblenden':'Aktive Boni anzeigen','bonuses-toggle','','treasury-bonuses-toggle',`aria-expanded="${mobileBonuses}"`)}<div class="treasury-mobile-bonuses ${mobileBonuses?'is-expanded':''}">${bonuses()}</div></aside>`;
    }
    function inspector(i){
        const target=onSlot(selectedSlot),equipped=num(i.equipped_slot)>0,eligible=usable(i)&&slotCount()>0;
        const effects=Array.isArray(i.effects)?i.effects:[],maxLevel=effects.length>0&&effects.every(effect=>num(effect.parts)>=5);
        const status=equipped?'Angelegt auf Platz '+i.equipped_slot:!i.is_unlocked?'Noch nicht freigeschaltet':!i.is_usable?'Sammlerrelikt · keine aktiven Boni':'Bereit zum Anlegen';
        const universal=num(data()?.universal_fragments?.[grade(i)]);
        const exchangeLabel=`<span class="treasury-fragment-exchange-copy"><img src="${fragmentImage(grade(i))}" alt="" aria-hidden="true"><span><strong>Puzzlefragmente umwandeln</strong><small>${fmt(universal)} verfügbar</small></span></span><b class="treasury-fragment-exchange-plus" aria-hidden="true">+</b>`;
        const effectRows=effects.map(effect=>{const done=num(effect.parts)>=5,cost=num(effect.upgrade_cost),canUpgrade=i.is_unlocked&&!done&&num(i.fragments)>=cost&&!busy;const upgrade=done?'<span class="treasury-effect-max">MAX</span>':button(`<span class="treasury-effect-cost"><img src="${fragmentImage(grade(i))}" alt=""><b>${fmt(cost)}</b></span><span>Aufwerten</span>`,'effect-upgrade',`${i.treasure_code}:${effect.index}`,'treasury-effect-upgrade',canUpgrade?'': 'disabled');return `<div class="treasury-effect-row"><div class="treasury-effect-main">${effectStar(effect)}<span><b>${esc(bonusNames[effect.type]||effect.label_de||effect.type)}</b><strong>${num(effect.current_value)>=0?'+':''}${value(effect.current_value)}${unit(effect.type,effect.unit)}${done?'':` <em>→ ${num(effect.next_value)>=0?'+':''}${value(effect.next_value)}${unit(effect.type,effect.unit)}</em>`}</strong></span>${upgrade}</div><div class="treasury-effect-master ${effect.master_unlocked?'is-unlocked':'is-locked'}"><i aria-hidden="true">${effect.master_unlocked?'✓':'🔒'}</i><span><b>${esc(bonusNames[effect.master_type]||effect.master_label_de||effect.master_type||effect.type)} ${num(effect.master_value)>=0?'+':''}${value(effect.master_value)}${unit(effect.master_type||effect.type,effect.master_unit)}</b><small>Meisterbonus</small></span></div></div>`;}).join('');
        return `<aside id="treasury-inspector" class="treasury-inspector" aria-labelledby="treasury-item-name"><div class="treasury-inspector-hero">${tile(i)}<div><small class="treasury-grade grade-${grade(i)}">${grades[grade(i)]}</small><h3 id="treasury-item-name">${esc(name(i))}</h3><span>${i.is_unlocked?'Relikt freigeschaltet':'Noch '+fmt(Math.max(0,10-num(i.fragments)))+' Fragmente bis zur Freischaltung'}</span></div></div><div class="treasury-fragments"><div><span>${maxLevel?'Alle Effekte gemeistert':'Verfügbare Reliktfragmente'}</span><strong>${fmt(i.fragments)}</strong></div>${button(exchangeLabel,'exchange-open',i.treasure_code,'treasury-fragment-exchange',maxLevel?'disabled':'' )}</div><button type="button" class="button secondary item-source-link" data-action="item-sources" data-treasure-code="${num(i.treasure_code)}">${esc(window.ConquerLocale?.t('sources.find_fragments')||'Find fragments')}</button><div class="treasury-effect-table"><div class="treasury-effect-head"><b>Relikt-Effekte</b><b>Meisterboni</b></div>${effectRows||'<p class="treasury-stat-empty">Dieses Relikt hat keine aktiven Boni.</p>'}</div><div class="treasury-inspector-footer"><p class="treasury-status ${equipped?'is-active':''}">${esc(status)}</p>${equipped?button('Ablegen · Platz '+i.equipped_slot,'unequip',i.treasure_code,'blue',busy?'disabled':''):eligible?`${target?`<p class="treasury-replace-note">Ersetzt ${esc(name(target))} auf Platz ${selectedSlot}.</p>`:''}${button(target?'Ersetzen · Platz '+selectedSlot:'Anlegen · Platz '+selectedSlot,'equip',i.treasure_code,'gold',busy?'disabled':'')}`:button('Puzzlefragmente umwandeln','exchange-open',i.treasure_code,'blue',maxLevel?'disabled':'')}${!equipped&&usable(i)&&!slotCount()?'<small>Baue zuerst deine Schatzkammer.</small>':''}</div></aside>`;
    }
    function fragmentExchange(i){
        const available=num(data()?.universal_fragments?.[grade(i)]);exchangeAmount=Math.max(1,Math.min(exchangeAmount,Math.max(1,available)));
        return `<section class="treasury-exchange" role="dialog" aria-modal="true" aria-label="Puzzlefragmente umwandeln"><div class="treasury-exchange-heading"><span><small>PUZZLEFRAGMENTE</small><strong>${esc(name(i))} erhalten</strong></span>${button('×','exchange-close','','','aria-label="Umwandlung schließen"')}</div><h3>Puzzlefragmente umwandeln</h3><p class="treasury-exchange-explanation">Wandle ${grades[grade(i)].toLowerCase()}e Puzzlefragmente im Verhältnis 1:1 in Fragmente dieses Relikts um. Zehn Fragmente schalten das Relikt frei. Weitere Fragmente wertest du gezielt bei den einzelnen Effekten auf.</p><div class="treasury-exchange-flow"><div class="treasury-exchange-item grade-${grade(i)}"><img src="${fragmentImage(grade(i))}" alt="${grades[grade(i)]}es Puzzlefragment"><strong>${fmt(available)}</strong><small>${grades[grade(i)]} · Puzzle</small></div><div class="treasury-exchange-center"><div class="treasury-exchange-stepper">${button('−','exchange-minus','','',exchangeAmount<=1?'disabled':'')}<input id="treasury-exchange-amount" data-transient-input type="number" inputmode="numeric" min="1" max="${Math.max(1,available)}" step="1" value="${exchangeAmount}" aria-label="Anzahl der umzuwandelnden Puzzlefragmente">${button('+','exchange-plus','','',exchangeAmount>=available?'disabled':'')}</div><span>1 : 1 · Puzzle- gegen Reliktfragment</span>${button('Umwandeln','exchange',i.treasure_code,'gold',available<exchangeAmount||busy?'disabled':'')}</div><div class="treasury-exchange-item grade-${grade(i)}"><img src="${image(i)}" alt=""><strong data-exchange-result>${fmt(exchangeAmount)}</strong><small>${esc(name(i))}</small></div></div><div class="treasury-exchange-sources"><button type="button" class="button secondary item-source-link" data-action="item-sources" data-treasure-code="${num(i.treasure_code)}">${esc(window.ConquerLocale?.t('sources.find_fragments')||'Find fragments')}</button></div></section>`;
    }
    function bonuses(){
        const all=Object.entries(data().bonuses||{}).filter(([,v])=>num(v)!==0);
        return `<section class="treasury-active-bonuses" aria-label="Treasure Boosts der angelegten Relikte"><h3>Treasure Boosts</h3><div class="treasury-bonus-scroll" tabindex="0" aria-label="Gesamtwerte aller angelegten Relikte">${all.length?all.map(([key,v])=>`<div class="treasury-bonus-row"><span>${esc(bonusNames[key]||key)}</span><strong>+${value(v)}${unit(key)}</strong></div>`).join(''):'<div class="treasury-bonus-empty">Noch keine Treasure Boosts.<small>Lege oben ein freigeschaltetes Relikt an.</small></div>'}</div></section>`;
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
    async function exchange(id){
        if(busy)return;busy=true;render();
        try{await action('kingdom/action',{action:'treasure.exchange_fragments',treasure_code:id,amount:exchangeAmount},'Fragmente umgetauscht.');exchangeOpen=false;exchangeAmount=1;ctx.mobilePages?.closed('relic-exchange');}
        catch(error){toast(error.message||'Die Fragmente konnten nicht umgetauscht werden.');}
        finally{busy=false;if(host()?.querySelector('.treasury-shell'))render();}
    }
    async function upgradeEffect(id){
        if(busy)return;
        const [code,index]=String(id).split(':').map(Number);
        const item=items().find(i=>num(i.treasure_code)===code),effect=item?.effects?.find(effect=>num(effect.index)===index);
        if(!item||!effect||num(effect.parts)>=5||num(item.fragments)<num(effect.upgrade_cost))return;
        busy=true;render();
        try{await action('kingdom/action',{action:'treasure.upgrade_effect',treasure_code:code,effect_index:index},num(effect.parts)===4?'Effekt gemeistert – Meisterbonus freigeschaltet.':'Relikt-Effekt aufgewertet.');}
        catch(error){toast(error.message||'Der Relikt-Effekt konnte nicht aufgewertet werden.');}
        finally{busy=false;if(host()?.querySelector('.treasury-shell'))render();}
    }
    function closeDetail(){detailOpen=false;exchangeOpen=false;render();focus('select',selectedCode);ctx.mobilePages?.closed('relic');}
    function closeExchange(){exchangeOpen=false;render();ctx.mobilePages?.closed('relic-exchange');}
    function detailNavigation(){ctx.mobilePages?.opened('relic',closeDetail);}
    function onClick(act,b){
        if(!act.startsWith('treasury-'))return false;
        const id=b.dataset.id;if(b.disabled||busy)return true;
        if(act==='treasury-main-tab'&&['equipment','chests'].includes(id)){tab=id;render();focus('main-tab',id);}
        else if(act==='treasury-bonuses-toggle'){mobileBonuses=!mobileBonuses;render();}
        else if(act==='treasury-chest-open')void openChest(id);
        else if(act==='treasury-upgrade')ctx.onUpgrade?.();
        else if(act==='treasury-slot'){
            const slot=num(id);if(slot>=1&&slot<=slotCount()){selectedSlot=slot;const i=onSlot(slot);if(i){selectedCode=num(i.treasure_code);detailOpen=true;detailNavigation();}render();focus('slot',slot);}
        }
        else if(act==='treasury-select'){selectedCode=num(id);detailOpen=true;detailNavigation();render();host().querySelector('[data-action="treasury-detail-close"]')?.focus({preventScroll:true});}
        else if(act==='treasury-detail-close')closeDetail();
        else if(act==='treasury-exchange-open'){exchangeOpen=true;exchangeAmount=1;ctx.mobilePages?.opened('relic-exchange',closeExchange);render();}
        else if(act==='treasury-exchange-close')closeExchange();
        else if(act==='treasury-exchange-minus'){exchangeAmount=Math.max(1,exchangeAmount-1);render();}
        else if(act==='treasury-exchange-plus'){const i=items().find(i=>num(i.treasure_code)===selectedCode);exchangeAmount=Math.min(num(data()?.universal_fragments?.[grade(i)]),exchangeAmount+1);render();}
        else if(act==='treasury-exchange')void exchange(num(id));
        else if(act==='treasury-effect-upgrade')void upgradeEffect(id);
        else if(act==='treasury-source'){exchangeOpen=false;detailOpen=false;if(id==='chests'){tab='chests';render();}else navigate(id);}
        else if(act==='treasury-preset'){const n=num(id);if(n>=1&&n<=5){selectedPreset=n;render();focus('preset',n);}}
        else if(act==='treasury-preset-save')void presetAction('save');
        else if(act==='treasury-preset-apply')void presetAction('apply');
        else if(act==='treasury-equip'||act==='treasury-unequip')void mutate(act.slice(9),num(id));
        else if(act==='treasury-sources')navigate('inventory');
        return true;
    }
    return{render,onClick,updateTime,selectTab:value=>{if(['equipment','chests'].includes(value))tab=value;}};
};

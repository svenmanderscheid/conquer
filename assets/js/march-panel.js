(() => {
    'use strict';
    window.ConquerMarch = function({base,esc,fmt,openDialog,action,toast,getState,getKingdom,getProfile,unitName,loadFormations,api,shareTarget}) {
        // Deliberately unavailable until the calculator is approved for release.
        const battlePreview=null;
        let cap=50000; const $=s=>document.querySelector(s);
        let encounter=null, rows=[], cardSignature='', rallyMinutes=5;
        let previewTimer=0,previewPending=false,previewKey='',previewAttempt='',previewResult=null,defaultCounts={};
        const compositionKey=()=>`conquer:march-choice:v1:${base}:${getState().city.player_id}:${getState().city.world_id}:${encounter.kind}`;
        function savedComposition(){
            try{const saved=JSON.parse(localStorage.getItem(compositionKey()));if(!saved||typeof saved!=='object'||Array.isArray(saved))return null;
                let remaining=cap;const counts={};for(const troop of rows){const value=Number(saved[troop.code]);if(!Number.isSafeInteger(value)||value<=0)continue;const count=Math.min(value,countOf(troop),remaining);if(count>0){counts[troop.code]=count;remaining-=count;}}
                return Object.keys(counts).length?counts:null;
            }catch{return null;}
        }
        function calculationKey(){const target=findTarget();return JSON.stringify([encounter.kind,encounter.id,selected(),target?.hp_current,getState().troop_defs.map(t=>[t.code,t.monster_power,t.monster_power_single_type,t.monster_rally_power,t.monster_rally_power_single_type])]);}
        function showPreflight(){
            const box=$('#march-preflight');if(!box)return;
            const hospital=getKingdom?.()?.hospital,free=hospital?Math.max(0,Number(hospital.capacity)-Number(hospital.used)):null;
            const side=previewResult?.attacker;
            const pvp=['players','rally','rally-join','node-attack'].includes(encounter.kind);
            const losses=side?`${fmt(side.wounded)} verwundet · ${fmt(side.dead)} gefallen`:(previewAttempt===previewKey&&!previewPending?'Rechner zum Prüfen öffnen':encounter.kind.startsWith('monster')?'Wird berechnet …':'Gegner unbekannt · Rechner nutzen');
            box.innerHTML=`<div><span>Verluste${previewResult&&!encounter.kind.startsWith('monster')?' (Beispiel)':''}</span><strong>${esc(losses)}</strong></div><div><span>Freie Hospitalplätze</span><strong>${free===null?'Noch unbekannt':fmt(free)}</strong></div>${side&&free!==null&&side.wounded>free?'<p class="march-preflight-danger">Hospital zu klein: Weitere Verwundete können fallen.</p>':''}<p class="${pvp?'march-preflight-danger':''}">${pvp?'Dein Stadtschutz endet beim Entsenden.':'Dein Stadtschutz bleibt bestehen.'}</p>${encounter.kind.includes('rally')?'<p>Berechnung nur für deinen Beitrag; Sammelzeit kommt zur Laufzeit hinzu.</p>':''}`;
        }
        function updatePreview(){
            if(!$('#march-preflight'))return;
            const key=calculationKey();
            if(key!==previewKey){previewKey=key;previewResult=null;clearTimeout(previewTimer);}
            showPreflight();
            if(!api||!encounter.kind.startsWith('monster')||previewResult||previewPending||previewAttempt===key||document.hidden||navigator.onLine===false||$('#march-confirm').disabled){if(!previewResult&&$('#march-confirm').disabled)$('#march-preflight strong').textContent='Gültige Auswahl erforderlich';return;}
            clearTimeout(previewTimer);
            previewTimer=setTimeout(async()=>{
                if(document.hidden||!$('#game-dialog').open||!$('#game-dialog').dataset.march||key!==calculationKey())return;
                previewPending=true;previewAttempt=key;const target=findTarget();
                try{const result=await api('march/preview',{kind:encounter.kind,target_id:Number(target.id),target_x:coord(target,'x'),target_y:coord(target,'y'),troops:selected()});if(key===previewKey){previewResult=result;showPreflight();}}
                catch{if(key===previewKey&&$('#march-preflight strong'))$('#march-preflight strong').textContent='Rechner zum Prüfen öffnen';}
                finally{previewPending=false;if(key!==previewKey&&$('#game-dialog').open)updatePreview();}
            },650);
        }
        const countOf=t=>Math.max(0,Number(getState().troops[t.code])||0);
        const selected=()=>Object.fromEntries(rows.map(t=>[t.code,Number($(`#march-unit-${t.code}`)?.value||0)]).filter(([,n])=>n!==0));
        const actionPoints=()=>Number(getProfile?.()?.action_points??getState().city?.action_points??0);
        const landmark=kind=>/^(congress|shrine)(-garrison)?$/.test(kind||'');
        const allowed=(kind,target)=>kind.endsWith('-garrison')?target?.can_garrison:target?.can_attack&&(!target.event||(target.event.active&&new Date(target.event.ends_at.replace(' ','T')+'Z')>Date.now()));
        const findTarget=()=>encounter?.kind.startsWith('shrine')?[...(getState().shrines||[]),...(getState().event_shrines||[])].find(t=>Number(t.id)===encounter.id):encounter?.kind.startsWith('congress')?getState().congress:encounter&&(encounter.kind.startsWith('monster')?getState().monsters:encounter.kind==='charms'?(getState().charms||[]):['nodes','node-attack'].includes(encounter.kind)?getState().nodes:(getState().players||[])).find(t=>Number(t.id)===encounter.id)||encounter?.target;
        const coord=(target,axis)=>Number(target?.[`coord_${axis}`]??target?.[axis]??0);
        const charmLabels={construction_speed:'Baugeschwindigkeit',construction:'Baugeschwindigkeit',research_speed:'Forschungsgeschwindigkeit',research:'Forschungsgeschwindigkeit',troop_hp:'Truppen-LP',troops_hp:'Truppen-LP',troop_attack:'Truppenangriff',troops_attack:'Truppenangriff',troop_defense:'Truppenverteidigung',troops_defense:'Truppenverteidigung',carry_capacity:'Traglast',carry:'Traglast',march_speed:'Marschtempo',gathering_speed:'Sammeltempo',gathering:'Sammeltempo'};
        const requestId=()=>globalThis.crypto?.randomUUID?.()||`charm_${Date.now().toString(36)}_${Math.random().toString(36).slice(2)}`;
        const expires=target=>{const raw=String(target?.expires_at||'').replace(' ','T');return Date.parse(/[zZ]|[+-]\d\d:\d\d$/.test(raw)?raw:raw+'Z');};
        const shortTime=seconds=>{seconds=Math.max(0,Math.round(Number(seconds)||0));const minutes=Math.floor(seconds/60),rest=seconds%60;return minutes?`${minutes}:${String(rest).padStart(2,'0')} Min.`:`${rest} Sek.`;};
        const missionSpeed=(troop,target)=>{
            const kind=encounter?.kind||'';
            let field='march_speed';
            if(kind==='nodes')field='gather_speed';
            else if(kind==='node-attack')field='field_attack_speed';
            else if(kind==='monsters')field='monster_march_speed';
            else if(kind==='monster-rally')field='monster_rally_speed';
            else if(kind==='charms')field='charm_march_speed';
            else if(kind==='players')field='pvp_march_speed';
            else if(kind==='rally'||kind==='rally-join')field='pvp_rally_speed';
            else if(landmark(kind))field=kind.endsWith('-garrison')||target?.alliance_id==null?'shrine_neutral_speed':'shrine_occupied_speed';
            return Number(troop[field]||troop.march_speed||troop.speed||65);
        };
        function setCounts(counts) { rows.forEach(t=>{const input=$(`#march-unit-${t.code}`);if(input)input.value=counts[t.code]||0;});update(); }
        function proportional(source,limit) {
            const total=source.reduce((sum,t)=>sum+countOf(t),0),budget=Math.min(limit,total);
            const shares=source.map(t=>{const exact=total?countOf(t)*budget/total:0;return {code:t.code,count:Math.floor(exact),fraction:exact%1};});
            let remaining=budget-shares.reduce((sum,t)=>sum+t.count,0);
            [...shares].sort((a,b)=>b.fraction-a.fraction||a.code-b.code).forEach(t=>{if(remaining>0){t.count++;remaining--;}});
            return Object.fromEntries(shares.map(t=>[t.code,t.count]));
        }
        function maximum(limit=cap) { return proportional(rows,limit); }
        const monsterRequiredPower=target=>Math.max(1,Number(target?.required_power_current??target?.required_power??target?.definition?.required_power??1));
        function monsterSelection(target,rally=false) {
            const required=monsterRequiredPower(target),singleField=rally?'monster_rally_power_single_type':'monster_power_single_type',mixedField=rally?'monster_rally_power':'monster_power';
            const candidates=[];
            const build=(source,field,requiredTypes=[])=>{
                const counts={},available=source.filter(t=>countOf(t)>0&&Number(t[field]??t.power??0)>0),seeded=new Set();
                let total=0,power=0;
                for(const type of requiredTypes){
                    const troop=available.filter(t=>Number(t.type)===type).sort((a,b)=>Number(b[field]??b.power??0)-Number(a[field]??a.power??0)||a.code-b.code)[0];
                    if(!troop||total>=cap)return null;
                    counts[troop.code]=(counts[troop.code]||0)+1;seeded.add(Number(type));total++;power+=Number(troop[field]??troop.power??0);
                }
                const sorted=[...available].sort((a,b)=>Number(b[field]??b.power??0)-Number(a[field]??a.power??0)||b.tier-a.tier||a.code-b.code);
                for(const troop of sorted){
                    if(power>=required||total>=cap)break;
                    const unitPower=Number(troop[field]??troop.power??0),stock=countOf(troop)-(counts[troop.code]||0),needed=Math.ceil((required-power)/unitPower),take=Math.min(stock,cap-total,needed);
                    if(take>0){counts[troop.code]=(counts[troop.code]||0)+take;total+=take;power+=take*unitPower;}
                }
                const speed=Math.min(...Object.keys(counts).map(code=>missionSpeed(available.find(t=>Number(t.code)===Number(code)),target)));
                return total?{counts,total,power,speed,reached:power>=required,types:seeded.size}:null;
            };
            const types=[...new Set(rows.map(t=>Number(t.type)))];
            types.forEach(type=>{const candidate=build(rows.filter(t=>Number(t.type)===type),singleField);if(candidate)candidates.push(candidate);});
            for(let mask=1;mask<(1<<types.length);mask++){
                const selectedTypes=types.filter((_,index)=>mask&(1<<index));
                if(selectedTypes.length<2)continue;
                const candidate=build(rows.filter(t=>selectedTypes.includes(Number(t.type))),mixedField,selectedTypes);
                if(candidate)candidates.push(candidate);
            }
            // Monster formations remain economical first.  If two formations
            // need equally many troops, prefer the faster one; cavalry thus
            // becomes the natural quick-response choice without forcing a
            // weaker army against a stronger monster.
            const reached=candidates.filter(candidate=>candidate.reached).sort((a,b)=>a.total-b.total||b.speed-a.speed||a.power-b.power);
            if(reached.length)return reached[0].counts;
            return candidates.sort((a,b)=>b.power-a.power||b.total-a.total)[0]?.counts||maximum(50);
        }
        function gatheringSelection(target) {
            let remaining=Math.max(0,Number(target?.resource_amount)||0),places=cap;
            const counts={},groups=new Map();
            rows.forEach(t=>{
                const carry=Math.max(0,Number(t.gather_carry??t.carry??0));
                if(carry>0&&countOf(t)>0){if(!groups.has(carry))groups.set(carry,[]);groups.get(carry).push(t);}
            });
            [...groups.entries()].sort((a,b)=>b[0]-a[0]).forEach(([carry,troops])=>{
                if(remaining<=0||places<=0)return;
                const available=troops.reduce((sum,t)=>sum+countOf(t),0);
                const wanted=Math.min(available,places,Math.ceil(remaining/carry));
                Object.assign(counts,proportional(troops,wanted));
                remaining-=wanted*carry;places-=wanted;
            });
            return Object.keys(counts).length?counts:maximum(Math.min(50,cap));
        }
        function charmCollector() {
            const available=rows.filter(t=>countOf(t)>0),cavalry=available.filter(t=>Number(t.type)===3);
            const fastest=(cavalry.length?cavalry:available).sort((a,b)=>missionSpeed(b,findTarget())-missionSpeed(a,findTarget())||a.code-b.code)[0];
            return fastest?{[fastest.code]:1}:{};
        }
        function open(id,kind,options={}) {
            clearTimeout(previewTimer);previewResult=null;previewKey='';previewAttempt='';
            encounter={id:Number(id),kind,...options,requestId:options.requestId||requestId()};
            const state=getState(),target=findTarget();cap=state.army_limits?.march_capacity||50000;
            if(!target){toast('Dieses Ziel ist nicht mehr verfügbar.');return;}
            // Map polling can replace its viewport while the command window is open.
            // Retain the selection; every calculation and dispatch validates it on the server.
            encounter.target=target;
            if(kind==='monsters'&&(target.monster_type==='rally'||target.definition?.type==='rally')){kind='monster-rally';encounter.kind=kind;}
            rows=state.troop_defs.filter(t=>Number(t.tier)===1||countOf(t)>0).sort((a,b)=>b.tier-a.tier||a.type-b.type);
            const shrine=kind.startsWith('shrine'),congress=landmark(kind),garrison=kind.endsWith('-garrison'),pvp=['players','rally','rally-join'].includes(kind),monster=kind.startsWith('monster'),charm=kind==='charms',monsterRally=kind==='monster-rally',fieldAttack=kind==='node-attack',combat=monster||pvp||congress||fieldAttack,resource={1:'food',2:'lumber',3:'stone',4:'gold',5:'crystal'}[target.object_type]||'food';
            encounter.actionPointCost=Number(target.definition?.action_point_cost||target.action_point_cost||0);
            const resourceName={food:'Nahrung',lumber:'Holz',stone:'Stein',gold:'Gold',crystal:'Kristalle'}[resource];
            const rawTitle=congress?target.name||'Kongress':pvp?target.display_name||target.username:monster?target.definition.name:charm?`${{normal:'Normaler',epic:'Epischer',legendary:'Legendärer'}[target.grade]||'Magischer'} Charm`:resourceName;
            const title=congress?rawTitle:pvp?rawTitle:monster?(/skeleton/i.test(rawTitle)?'Skeletttrupp':/golem/i.test(rawTitle)?'Steingolem':/orc/i.test(rawTitle)?'Orktrupp':rawTitle):charm?rawTitle:({food:'Getreidehof',lumber:'Holzfällerlager',stone:'Steinbruch',gold:'Goldmine',crystal:'Kristallader'}[resource]);
            const targetImage=congress?`${base}/assets/art/map/${shrine?'shrine-'+(['forest','ice','sand','lava'].includes(target.element)?target.element:'forest'):'congress'}.png?v=shrines1`:pvp?window.ConquerCastleSkins.image(base,target.city_skin):monster?`${base}/assets/art/${/^(?:monsters\/)?[a-z0-9-]+$/.test(target.definition?.art||'')?target.definition.art:/skeleton/i.test(rawTitle)?'skeleton':/golem/i.test(rawTitle)?'golem':'orc'}.png`:charm?`${base}/assets/art/map/runes-v1/${['normal','epic','legendary'].includes(target.grade)?target.grade:'normal'}-detail.webp`:`${base}/assets/art/map/${{food:'farm',lumber:'lumber',stone:'quarry',gold:'gold',crystal:'crystal'}[resource]}.svg`;
            const rewardNames={food:'Nahrung',lumber:'Holz',stone:'Stein',gold:'Gold'};
            const resourceRewards=Object.entries(target.definition?.resource_reward||{}).filter(([key,value])=>rewardNames[key]&&Number(value)>0).map(([key,value])=>({label:rewardNames[key],value:Number(value),art:`${base}/assets/art/ui-resources/${key}.png`}));
            const dropRewards=(target.definition?.drops||[]).filter(drop=>Number(drop.count??drop.quantity)>0).map(drop=>{const r=window.ConquerRewards.resolve(drop,getKingdom?.(),base);return {label:r.name,value:r.quantity,art:r.icon};});
            const gemDrop=target.definition?.gems_drop,gemReward=gemDrop&&Number(gemDrop.amount)>0?[{label:'Edelsteine',value:Number(gemDrop.amount),art:`${base}/assets/art/items/gems.svg`}]:[];
            const rewards=monster?[...resourceRewards,...dropRewards,...gemReward].slice(0,8):[];
            cardSignature='';rallyMinutes=5;
            openDialog(`<div class="march-command ${combat?'is-attack':'is-gather'} ${monsterRally?'is-monster-rally':''} ${charm?'is-charm-collect':''}" data-view="troops"><header class="march-command-heading"><h2>${congress?(garrison?'Garnison verstärken':shrine?'Schrein angreifen':'Kongress angreifen'):pvp?(kind==='rally'?'Rally starten':kind==='rally-join'?'Rally beitreten':'Solo-Angriff'):monsterRally?'Monster-Rally':monster?'Monsterangriff':charm?'Charm einsammeln':fieldAttack?'Sammler angreifen':'Rohstoffe sammeln'}</h2><span>Marsch</span><div class="march-detail-tabs" role="tablist" aria-label="Marschansicht"><button type="button" role="tab" data-action="march-view" data-id="troops" aria-selected="true" aria-controls="march-formation-panel">Truppen</button><button type="button" role="tab" data-action="march-view" data-id="target" aria-selected="false" aria-controls="march-target-panel">Ziel</button></div></header>
                <div class="march-layout"><aside class="march-target"><div class="march-target-heading"><h3>${esc(title)}</h3><div class="march-target-location"><span class="march-coordinates">${congress?(shrine?'Schrein':'Kongress'):pvp?'Burg '+(target.castle_level??'–'):charm?esc(charmLabels[target.stat_category]||target.stat_category||'Magischer Bonus'):'Lv. '+Number(target.definition?.level??target.level??1)} · X ${coord(target,'x')} / Y ${coord(target,'y')}</span><button type="button" class="march-share-target" data-action="march-share-target" aria-label="${esc(title)} in einem Chat teilen" title="Ziel teilen"><span aria-hidden="true">⇗</span><span>Teilen</span></button></div></div><div class="march-target-art ${monster?'':'resource-target'}"><img src="${targetImage}" alt=""></div><div class="march-target-stat"><span>${congress?'Verteidiger':pvp?'Burgstufe':monster?'Lebenspunkte':charm?'Bonus':'Vorrat'}</span><strong id="march-target-value">${charm?'+'+fmt(target.bonus_pct)+' %':pvp&&target.castle_level==null?'–':fmt(congress?target.garrison_total||0:pvp?target.castle_level:monster?target.hp_current:target.resource_amount)}</strong></div>${charm?`<p class="march-pvp-note">Der Charm wirkt nach dem Einsammeln ${esc(shortTime(target.effect_duration_seconds))}. Er muss bei Ankunft noch verfügbar sein.</p>`:''}${monster?`<div class="march-target-health" role="progressbar" aria-label="Monster-Lebenspunkte" aria-valuemin="0" aria-valuemax="${Number(target.hp_current)||1}" aria-valuenow="${Number(target.hp_current)||0}"><span></span></div>`:''}${congress?`<p class="march-pvp-note">${garrison?'Deine Truppen bleiben zur Verteidigung am Ziel, bis du sie zurückrufst.':'Gemeinsam die Besatzung schwächen. Nach dem Sieg eine Stunde halten. Truppenverluste sind möglich.'}</p>`:''}${fieldAttack?'<p class="march-pvp-note">Angriff auf die Sammler am Feld. Truppenverluste sind möglich. Ein Sieg übernimmt das Feld. Dein Stadtschutz endet beim Losschicken.</p>':''}${pvp?`<p class="march-pvp-note">Angriffe beenden deinen Schutz. Truppen können fallen; geschützte Städte und Allianzmitglieder sind keine gültigen Ziele.</p>`:''}${rewards.length?`<div class="march-rewards"><h4>Mögliche Beute</h4><div>${rewards.map(reward=>`<span title="${esc(reward.label)}: ${fmt(reward.value)}"><img src="${reward.art}" alt="${esc(reward.label)}"><strong>${fmt(reward.value)}</strong></span>`).join('')}</div></div>`:''}</aside>
                <section class="march-formation" id="march-formation-panel" aria-label="Truppenkomposition"><div class="march-toolbar"><label class="march-saved-formation">Formation<select id="march-saved-formation" aria-label="Gespeicherte Formation" disabled><option value="">Formationen werden geladen …</option></select></label><span id="march-available"></span></div>
                <div class="march-unit-list" tabindex="0" role="region" aria-label="Verfügbare Truppen">${rows.map(t=>`<div class="march-unit-row ${countOf(t)?'':'unavailable'}" data-unit="${t.code}" data-type="${t.type}"><div class="march-portrait troop-tier-frame" data-troop-tier="${Number(t.tier)}"><img src="${base}/assets/art/characters/tier-colors-v1/${['infantry','archer','cavalry'][Number(t.type)-1]}-t${t.tier}-thumb.webp" alt=""><span>T${t.tier}</span></div><div class="march-unit-name"><strong>${esc(unitName(t))}</strong><small id="march-stock-${t.code}">${fmt(countOf(t))} verfügbar</small></div><input class="march-range" type="range" min="0" max="${Math.min(cap,countOf(t))}" step="1" value="0" data-unit-range="${t.code}" aria-label="${esc(unitName(t))} mit Regler auswählen" ${countOf(t)?'':'disabled'}><div class="march-unit-amount"><input id="march-unit-${t.code}" class="number-control" type="number" inputmode="numeric" min="0" max="${Math.min(cap,countOf(t))}" step="1" value="0" aria-label="Anzahl ${esc(unitName(t))}" ${countOf(t)?'':'disabled'}><button type="button" class="march-row-max" data-action="march-unit-max" data-id="${t.code}" aria-label="Max ${esc(unitName(t))}" ${countOf(t)?'':'disabled'}>Max</button></div></div>`).join('')}</div></section>
                <aside class="march-army" aria-label="Ausgewählte Armee"><h3>Deine Auswahl</h3><div class="march-capacity"><span>Truppen</span><strong><span id="march-selected">0</span> / <span id="march-capacity">${fmt(cap)}</span></strong></div><div class="march-capacity-track" role="progressbar" aria-label="Marschkapazität" aria-valuemin="0" aria-valuemax="${cap}" aria-valuenow="0"><span></span></div><div id="march-selected-cards" class="march-selected-cards" aria-live="polite"></div><div class="march-summary"><div><span>${monster?'Armeemacht':combat?'Grundangriff':charm?'Sammeltrupp':'Traglast'}</span><strong id="march-strength">0</strong></div><div><span>Marschplätze</span><strong id="march-slots"></strong></div><div><span>Laufzeit</span><strong id="march-travel-time">–</strong></div><div><span>Aktionspunkte</span><strong id="march-action-points">${encounter.actionPointCost} / ${fmt(actionPoints())}</strong></div></div><div id="march-forecast" class="march-forecast" aria-live="polite"></div></aside></div>
                <footer class="march-footer"><div class="march-presets"><button type="button" class="button secondary" data-action="march-clear">Leeren</button><button type="button" class="button gold" data-action="march-max" aria-label="Maximale Truppen auswählen">Max</button></div>${kind==='rally'||monsterRally?'<button type="button" class="march-time-button" data-action="march-time-open" aria-haspopup="dialog"><span id="march-time-label">5 Min.</span></button>':''}<button type="button" class="button ${combat?'march-attack':'march-gather'}" id="march-confirm" data-action="march-send">${garrison?'Verstärken':kind==='rally'||monsterRally?'Rally starten':kind==='rally-join'?'Beitreten':combat?'Angreifen':charm?'Einsammeln':'Sammeln'} <span aria-hidden="true">→</span></button></footer></div>`);
            if(battlePreview&&['monsters','monster-rally','players','rally'].includes(kind)){
                const summary=document.createElement('section');summary.id='march-preflight';summary.className='march-preflight';summary.setAttribute('aria-label','Vor dem Angriff');$('.march-army').append(summary);
                const button=document.createElement('button');button.type='button';button.className='button march-preview-button';button.dataset.action='march-preview';button.disabled=true;button.textContent='Kampfrechner (bald verfügbar)';button.setAttribute('aria-label','Kampfrechner ist noch nicht verfügbar');
                $('.march-army').append(button);
            }
            $('#game-dialog').classList.add('march-dialog');$('#game-dialog').dataset.march='true';
            $('.march-target').id='march-target-panel';
            rows.forEach(t=>{
                $(`#march-unit-${t.code}`).addEventListener('input',update);
                $(`[data-unit-range="${t.code}"]`).addEventListener('input',e=>{$(`#march-unit-${t.code}`).value=e.target.value;update();});
            });
            defaultCounts=charm?charmCollector():kind==='nodes'?gatheringSelection(target):monster?monsterSelection(target,monsterRally):maximum(50);
            const remembered=savedComposition();setCounts(remembered||defaultCounts);
            if(remembered){const note=document.createElement('div');note.className='march-remembered';note.innerHTML='Letzte Auswahl angepasst. <button type="button" data-action="march-default">Standard wählen</button>';$('.march-army').prepend(note);}
            const currentEncounter=encounter;
            if(loadFormations)loadFormations().then(formations=>{
                const select=$('#march-saved-formation');if(!select||encounter!==currentEncounter)return;
                select.disabled=!formations.length;select.innerHTML=formations.length?'<option value="">Formation wählen</option>'+formations.map(f=>`<option value="${Number(f.slot)}">${esc(f.name||'Formation '+f.slot)}</option>`).join(''):'<option value="">Keine Formation gespeichert</option>';
                select.addEventListener('change',()=>{const f=formations.find(f=>Number(f.slot)===Number(select.value));if(!f)return;let left=cap;const selected={};rows.forEach(t=>{const count=Math.max(0,Math.min(Number(f.troops[t.code]||0),countOf(t),left));left-=count;selected[t.code]=count;});setCounts(selected);toast('Formation geladen und an verfügbare Truppen angepasst.');});
            }).catch(()=>{const select=$('#march-saved-formation');if(select&&encounter===currentEncounter)select.innerHTML='<option value="">Formationen nicht verfügbar</option>';});
            else $('#march-saved-formation').innerHTML='<option value="">Keine Formation gespeichert</option>';
        }
        function update() {
            if(!$('#game-dialog')?.open||!$('#game-dialog').dataset.march)return;
            const state=getState(),target=findTarget(),counts=selected(),total=Object.values(counts).reduce((a,b)=>a+b,0),available=rows.reduce((a,t)=>a+countOf(t),0);
            cap=state.army_limits?.march_capacity||50000;const baseSlots=state.army_limits?.march_slots||3,extraSlots=state.army_limits?.gather_march_slots||0;
            const gathering=state.marches.filter(m=>Number(m.march_type)===9).length;
            const slots=baseSlots+(encounter.kind==='nodes'?extraSlots:Math.min(extraSlots,gathering));
            const selectedTypes=new Set(rows.filter(t=>Number(counts[t.code])>0).map(t=>Number(t.type))),singleType=selectedTypes.size===1;
            let invalid=false,invalidUnit=null,attack=0,power=0,carry=0;
            rows.forEach(t=>{
                const n=Number(counts[t.code]||0),stock=countOf(t),input=$(`#march-unit-${t.code}`),range=$(`[data-unit-range="${t.code}"]`),bad=!Number.isSafeInteger(n)||n<0||n>stock;
                invalid ||= bad;if(bad&&!invalidUnit)invalidUnit=t;attack+=Math.max(0,n)*Number(t.attack||0);
                const rally=encounter.kind==='monster-rally',powerField=rally?(singleType?'monster_rally_power_single_type':'monster_rally_power'):(singleType?'monster_power_single_type':'monster_power');
                power+=Math.max(0,n)*Number(t[powerField]??t.power??0);carry+=Math.max(0,n)*Number(state.troop_defs.find(u=>u.code===t.code)?.gather_carry??t.carry??0);
                input.max=Math.min(stock,cap);input.disabled=stock===0&&n===0;input.setAttribute('aria-invalid',String(bad));
                range.max=Math.min(stock,cap);range.disabled=stock===0;range.value=Number.isFinite(n)?n:0;
                range.style.setProperty('--fill',`${stock?Math.min(100,Math.max(0,n/Math.min(stock,cap)*100)):0}%`);
                $(`#march-stock-${t.code}`).textContent=`${fmt(stock)} verfügbar`;
                $(`[data-unit="${t.code}"]`).classList.toggle('unavailable',stock===0);
                $(`[data-action="march-unit-max"][data-id="${t.code}"]`).disabled=stock===0;
            });
            const carryCapacity=Math.max(0,Math.min(Math.floor(carry+1e-8),Number(target?.resource_amount||0)));
            $('#march-available').textContent=`${fmt(available)} verfügbar`;
            $('#march-selected').textContent=fmt(total);
            $('#march-capacity').textContent=fmt(cap);
            const capacityBar=$('.march-capacity-track');capacityBar.setAttribute('aria-valuemax',cap);capacityBar.setAttribute('aria-valuenow',Math.max(0,Math.min(cap,Number.isFinite(total)?total:0)));capacityBar.querySelector('span').style.width=`${Math.max(0,Math.min(100,total/cap*100))||0}%`;capacityBar.classList.toggle('over-cap',total>cap);
            if(target)$('#march-target-value').textContent=encounter.kind==='charms'?`+${fmt(target.bonus_pct)} %`:['players','rally','rally-join'].includes(encounter.kind)&&target.castle_level==null?'–':fmt(landmark(encounter.kind)?target.garrison_total||0:encounter.kind.startsWith('monster')?target.hp_current:['nodes','node-attack'].includes(encounter.kind)?target.resource_amount:target.castle_level);
            const cards=rows.map(t=>({...t,count:Number(counts[t.code]),name:unitName(t)})).filter(t=>Number.isSafeInteger(t.count)&&t.count>0);
            const signature=JSON.stringify(cards.map(t=>[t.code,t.count,t.name]));
            if(signature!==cardSignature){cardSignature=signature;$('#march-selected-cards').innerHTML=cards.length?cards.map(t=>`<div class="march-selected-card troop-tier-frame" data-type="${t.type}" data-troop-tier="${Number(t.tier)}" data-selected-unit="${t.code}" title="${esc(t.name)} · T${t.tier}: ${fmt(t.count)}"><img src="${base}/assets/art/characters/tier-colors-v1/${['infantry','archer','cavalry'][Number(t.type)-1]}-t${t.tier}-thumb.webp" alt=""><span class="march-card-tier">T${t.tier}</span><span class="march-card-name">${esc(t.name)}</span><strong>${fmt(t.count)}</strong></div>`).join(''):'<p class="march-selection-empty">Keine Truppen gewählt</p>';}
            $('#march-strength').textContent=fmt(encounter.kind==='nodes'?carryCapacity:encounter.kind==='charms'?total:encounter.kind.startsWith('monster')?Math.round(power):attack);
            $('#march-slots').textContent=`${Math.max(0,slots-state.marches.length)} / ${slots} frei`;
            // Mission-specific server values already contain research, world, talents and the equipped skin exactly once.
            const selectedSpeeds=rows.filter(t=>Number(counts[t.code])>0).map(t=>missionSpeed(t,target));
            const distance=target?Math.hypot(coord(target,'x')-Number(state.city?.coord_x),coord(target,'y')-Number(state.city?.coord_y)):0;
            $('#march-travel-time').textContent=selectedSpeeds.length?shortTime(Math.max(5,Math.floor(distance*100/Math.min(...selectedSpeeds)))):'–';
            $('#march-action-points').textContent=`${encounter.actionPointCost||0} / ${fmt(actionPoints())}`;
            let message='',warning=false;
            if(!target){message='Ziel nicht mehr verfügbar.';warning=true;}
            else if(landmark(encounter.kind)&&!(allowed(encounter.kind,target))){message='Das Ziel ist aktuell nicht angreifbar. Öffne seine Übersicht erneut.';warning=true;}
            else if(encounter.kind==='node-attack'&&!target.can_attack){message='Diese Sammler sind nicht mehr angreifbar.';warning=true;}
            else if(encounter.kind==='nodes'&&(target.gatherer_march_id||Number(target.resource_amount)<=0)){message='Rohstofffeld belegt oder erschöpft.';warning=true;}
            else if(encounter.kind==='charms'&&(target.collectible===false||expires(target)<=Date.now())){message='Dieser Charm ist nicht mehr einsammelbar.';warning=true;}
            else if(invalid){message=`Menge ungültig: ${unitName(invalidUnit)}.`;warning=true;}
            else if(total>cap){message=`Maximal ${fmt(cap)} Truppen. Max verteilt passend.`;warning=true;}
            else if(state.marches.length>=slots){message='Alle Marschplätze sind belegt.';warning=true;}
            else if(total===0){message=available?'Wähle Truppen oder Max.':'Bilde zuerst Truppen aus.';}
            else if(encounter.kind.startsWith('monster')){
                const required=monsterRequiredPower(target),ratio=power/required;
                warning=encounter.kind!=='monster-rally'&&ratio<1;
                if(encounter.kind==='monster-rally')message=`Eigene Macht ${fmt(Math.round(power))} / Rally-Ziel ${fmt(required)} · ${rallyMinutes} Min. Sammelzeit; Mitglieder ergänzen die Macht.`;
                else message=`${fmt(Math.round(power))} / ${fmt(required)} Macht · ${ratio>=1.2?'deutlich überlegen':ratio>=1?'Siegesschwelle erreicht':ratio>=.8?'knapp unter der Siegesschwelle':'zu wenig Macht'}.`;
            } else if(encounter.kind==='charms')message=`${charmLabels[target.stat_category]||'Charm-Bonus'} +${fmt(target.bonus_pct)} % · Truppen sammeln ihn bei Ankunft einmalig ein.`; else if(encounter.kind==='node-attack')message='Feldangriff · Truppenverluste möglich. Bei Sieg sammeln deine Überlebenden weiter. Angriff beendet deinen Schutz.'; else if(landmark(encounter.kind))message=encounter.kind.endsWith('-garrison')?'Truppen bleiben als Garnison am Ziel.':'Allianzangriff · bleibende Verluste der Verteidiger, eigene Verluste möglich.'; else if(['players','rally','rally-join'].includes(encounter.kind))message=(encounter.kind==='rally'?'Rally: '+(rallyMinutes)+' Min. Sammelzeit. ':'')+'PvP: Truppenverluste möglich. Angriff beendet deinen Schutz.'; else message=`Bis zu ${fmt(carryCapacity)} ${resourceLabel(target.object_type)} · ${target.gather_rate?shortTime(Math.ceil(carryCapacity/target.gather_rate))+' Sammeln am Feld':'Sammeln am Feld'}.`;
            if(actionPoints()<Number(encounter.actionPointCost||0)){message='Nicht genügend Aktionspunkte.';warning=true;}
            $('#march-forecast').textContent=message;$('#march-forecast').classList.toggle('caution',warning);
            $('#march-confirm').disabled=!target||(encounter.kind==='node-attack'&&!target.can_attack)||(landmark(encounter.kind)&&!(allowed(encounter.kind,target)))||(encounter.kind==='charms'&&(target.collectible===false||expires(target)<=Date.now()))||invalid||total<1||total>cap||state.marches.length>=slots||actionPoints()<Number(encounter.actionPointCost||0)||(encounter.kind==='nodes'&&Boolean(target.gatherer_march_id||Number(target.resource_amount)<=0));
            updatePreview();
        }
        function resourceLabel(type){return {1:'Nahrung',2:'Holz',3:'Stein',4:'Gold',5:'Kristalle'}[type]||'Vorräte';}
        function onClick(act,button) {
            if(!act.startsWith('march-'))return false;
            if(act==='march-preview'){
                const troops=selected(),target=findTarget();
                if(!target||!Object.keys(troops).length||Object.values(troops).some(n=>!Number.isSafeInteger(n)||n<0)){toast('Wähle zuerst eine gültige Truppenzusammenstellung.');return true;}
                const key=calculationKey();battlePreview?.open({kind:encounter.kind,target,troops,onResult:result=>{if(key===calculationKey()){previewResult=result;showPreflight();}}});return true;
            }
            if(act==='march-share-target'){
                const target=findTarget();if(!target||!shareTarget)return true;
                const name=$('.march-target-heading h3')?.textContent?.trim()||'Ziel';
                shareTarget(`${name} · Welt ${Number(getState().city?.world_id)||1} · X ${coord(target,'x')} / Y ${coord(target,'y')}`);return true;
            }
            if(act==='march-time-open'){
                const picker=document.createElement('dialog');picker.className='march-time-dialog';
                picker.setAttribute('aria-labelledby','march-time-title');
                picker.innerHTML=`<header><h2 id="march-time-title">Rally-Zeit</h2><button type="button" data-action="march-time-close" aria-label="Schließen">×</button></header><div class="march-time-options">${[1,5,15,30].map(n=>`<button type="button" data-action="march-time-select" data-id="${n}" aria-pressed="${n===rallyMinutes}"><span class="march-time-check">${n===rallyMinutes?'✓':'○'}</span><strong>${n}</strong><span>MIN.</span></button>`).join('')}</div><button type="button" class="march-time-ok" data-action="march-time-confirm">OK</button>`;
                picker.dataset.minutes=String(rallyMinutes);document.body.append(picker);
                picker.addEventListener('close',()=>{picker.remove();$('[data-action="march-time-open"]')?.focus();});picker.showModal();
            }
            if(act==='march-time-select'){
                const picker=$('.march-time-dialog');picker.dataset.minutes=button.dataset.id;
                picker.querySelectorAll('[data-action="march-time-select"]').forEach(b=>{const active=b===button;b.setAttribute('aria-pressed',String(active));b.querySelector('.march-time-check').textContent=active?'✓':'○';});
            }
            if(act==='march-time-confirm'){rallyMinutes=Number($('.march-time-dialog').dataset.minutes);$('#march-time-label').textContent=rallyMinutes+' Min.';$('.march-time-dialog').close();update();}
            if(act==='march-time-close')$('.march-time-dialog').close();
            if(act==='march-view'){const next=button.dataset.id==='target'?'target':'troops';$('.march-command').dataset.view=next;document.querySelectorAll('[data-action="march-view"]').forEach(b=>b.setAttribute('aria-selected',String(b.dataset.id===next)));}
            if(act==='march-max')setCounts(maximum());
            if(act==='march-clear')setCounts({});
            if(act==='march-default'){setCounts(defaultCounts);$('.march-remembered')?.remove();}
            if(act==='march-unit-max'){
                const counts=selected(),code=Number(button.dataset.id),t=rows.find(t=>Number(t.code)===code);
                if(t){const other=Object.entries(counts).reduce((s,[k,n])=>s+(Number(k)===code?0:Math.max(0,Number.isSafeInteger(n)?n:0)),0);counts[code]=Math.min(countOf(t),Math.max(0,cap-other));setCounts(counts);}
            }
            if(act==='march-send'){
                update();if($('#march-confirm').disabled)return true;
                const target=findTarget(),rememberKey=compositionKey(),rememberTroops=selected();
                const kind=encounter.kind,path=landmark(kind)?`shrines/${Number(target.id)}/${kind.endsWith('-garrison')?'garrison':'attack'}`:({monsters:'march/dispatch','monster-rally':'rally/start-monster',nodes:'march/dispatch-gather','node-attack':'march/dispatch-field-attack',charms:'march/dispatch-charm',players:'march/dispatch-player',rally:'rally/start','rally-join':'rally/join'})[kind];
                action(path,{target_x:coord(target,'x'),target_y:coord(target,'y'),troops:rememberTroops,...(kind==='charms'?{charm_id:Number(target.id),request_id:encounter.requestId}:kind==='rally-join'?{rally_id:encounter.rally_id}:kind==='rally'?{target_player_id:Number(target.id),rally_minutes:Number(rallyMinutes),message:''}:kind==='monster-rally'?{rally_minutes:Number(rallyMinutes),message:''}:{})},['rally','monster-rally'].includes(kind)?'Deine Rally wurde gestartet.':kind==='charms'?'Deine Truppen sammeln den Charm ein.':'Deine Truppen ziehen los!').then(result=>{if(result){try{localStorage.setItem(rememberKey,JSON.stringify(rememberTroops));}catch{}}update();if(result&&['rally','monster-rally','rally-join'].includes(kind))window.dispatchEvent(new CustomEvent('conquer-rally-updated'));});
            }
            return true;
        }
        return {open,update,onClick};
    };
})();

(() => {
    'use strict';
    window.ConquerMarch = function({base,esc,fmt,openDialog,action,toast,getState,getKingdom,getProfile,unitName,loadFormations,api,shareTarget,now=()=>Date.now()}) {
        const tr=(key,fallback,parameters={})=>{
            const value=window.ConquerLocale?.t(key,parameters);
            return value&&value!==key?value:fallback.replace(/\{(\w+)\}/g,(match,name)=>parameters[name]??match);
        };
        const battlePreview=typeof window.ConquerBattlePreview==='function'&&api
            ? window.ConquerBattlePreview({api,esc,fmt,getState}) : null;
        function monsterPortrait(value,name) {
            // Legacy goblin definitions may omit art or carry the generic orc portrait.
            if(/treasure[ _-]+goblin|schatzgoblin/i.test(String(name||'')))return `${base}/assets/art/monsters/2.5d/bright-v2/treasure-goblin-turquoise.png`;
            const regional=`${value||''} ${name||''}`.match(/grumwald|frostgrimm|sandmaul|glutramm/i);
            if(regional)return `${base}/assets/art/monsters/storybook-v2/${regional[0].toLowerCase()}.png`;
            const art=String(value||'').replace(/\.png$/,'');
            const id=art.split('/').pop();
            const aliases={goblin:'treasure-goblin-turquoise','treasure-goblin':'treasure-goblin-turquoise'};
            const file=aliases[id]||id;
            if(['orc','skeleton','golem','treasure-goblin-turquoise','green-dragon','red-dragon','gold-dragon','magdar'].includes(file))return `${base}/assets/art/monsters/2.5d/bright-v2/${file}.png`;
            // Other historical bosses retain the artwork stored in their snapshot.
            if(/^(?:monsters\/)?[a-z0-9-]+$/.test(art))return `${base}/assets/art/${art}.png`;
            return `${base}/assets/art/monsters/2.5d/bright-v2/${/skeleton|skelett/i.test(name)?'skeleton':/golem/i.test(name)?'golem':'orc'}.png`;
        }
        let cap=50000; const $=s=>document.querySelector(s);
        let encounter=null, rows=[], cardSignature='', rallyMinutes=5;
        let previewTimer=0,previewPending=false,previewKey='',previewAttempt='',previewResult=null,defaultCounts={};
        let formationHistoryClosing=false;
        const formationSlots=[1,2,3,4,5,6];
        const formationName=slot=>tr('march.formation.default_name','Formation {slot}',{slot});
        function formationSelection(){
            const troops=selected(),entries=Object.entries(troops),total=entries.reduce((sum,[,n])=>sum+n,0);
            return entries.length&&total>0&&total<=selectionCapacity()&&entries.every(([code,n])=>Number.isSafeInteger(n)&&n>0&&n<=Number(getState().troops[code]||0))?troops:null;
        }
        function formationToolbar(){
            return `<div class="march-toolbar march-formations-toolbar"><div class="march-formation-slots" role="group" aria-label="${esc(tr('march.formation.slots','Saved formations'))}">${formationSlots.map(slot=>`<button type="button" data-action="march-formation-load" data-id="${slot}" aria-pressed="false" disabled>${slot}</button>`).join('')}</div><button type="button" id="march-formation-save" class="button secondary" data-action="march-formation-save" disabled>${esc(tr('march.formation.save','Save formation'))}</button><div class="march-formation-caption"><span id="march-formation-status" translate="no"></span><button type="button" data-action="march-formations-retry" hidden>${esc(tr('march.formation.retry','Retry'))}</button><span id="march-available"></span></div></div>`;
        }
        function updateFormationToolbar(){
            const state=encounter?.formations;if(!state||!$('#march-formation-save'))return;
            document.querySelectorAll('[data-action="march-formation-load"]').forEach(button=>{
                const slot=Number(button.dataset.id),saved=state.records.find(f=>Number(f.slot)===slot);
                button.disabled=state.loading||state.error;button.classList.toggle('is-filled',Boolean(saved));button.classList.toggle('is-empty',!saved);
                button.setAttribute('aria-pressed',String(state.slot===slot));
                const label=saved?tr('march.formation.load_slot','Load formation {slot}: {name}',{slot,name:saved.name||formationName(slot)}):tr('march.formation.empty_slot','Formation {slot}: empty slot',{slot});
                button.setAttribute('aria-label',label);button.title=label;button.setAttribute('translate','no');
            });
            const saved=state.records.find(f=>Number(f.slot)===state.slot);
            $('#march-formation-status').textContent=state.loading?tr('march.formation.loading','Loading formations…'):state.error?tr('march.formation.unavailable','Formations unavailable'):state.slot?saved?`${state.slot} · ${saved.name||formationName(state.slot)}`:tr('march.formation.empty_selected','Slot {slot} is empty',{slot:state.slot}):tr('march.formation.choose','Formations 1–6');
            $('[data-action="march-formations-retry"]').hidden=!state.error;
            $('#march-formation-save').disabled=formationHistoryClosing||state.loading||state.error||!api||!formationSelection();
        }
        async function fetchFormations(){
            const opened=encounter,state=opened.formations,worldId=getState().city.world_id,cityId=getState().city.id,parent=$('#game-dialog');
            const valid=()=>encounter===opened&&parent.open&&parent.dataset.march&&getState().city.world_id===worldId&&getState().city.id===cityId;
            state.loading=true;state.error=false;updateFormationToolbar();
            try{const result=loadFormations?await loadFormations():[];if(!valid())return;state.records=(Array.isArray(result)?result:[]).filter(f=>formationSlots.includes(Number(f.slot)));}
            catch{if(valid())state.error=true;}
            finally{if(valid()){state.loading=false;updateFormationToolbar();}}
        }
        function loadFormation(slot){
            const state=encounter.formations;if(state.loading||state.error||!formationSlots.includes(slot))return;
            state.slot=slot;const formation=state.records.find(f=>Number(f.slot)===slot);
            if(formation){cap=selectionCapacity();let left=cap;const counts=formation.composition&&window.ConquerFormationComposition?window.ConquerFormationComposition.allocate(rows,Object.fromEntries(rows.map(t=>[t.code,countOf(t)])),cap,formation.composition):{};if(!formation.composition)rows.forEach(t=>{const requested=Number(formation.troops?.[t.code]||0),count=Number.isSafeInteger(requested)?Math.max(0,Math.min(requested,countOf(t),left)):0;left-=count;counts[t.code]=count;});setCounts(counts);toast(tr('march.formation.loaded','Formation loaded and adjusted to your available troops and capacity.'));}
            updateFormationToolbar();
        }
        function saveFormationDialog(){
            const opened=encounter,formations=opened.formations,troops=formationSelection();
            if(formationHistoryClosing||!troops||formations.loading||formations.error||!api||$('.march-formation-dialog'))return;
            const worldId=getState().city.world_id,cityId=Number(getState().city.id),parent=$('#game-dialog');
            let slot=formations.slot||formationSlots.find(n=>!formations.records.some(f=>Number(f.slot)===n))||1,busy=false;
            const dialog=document.createElement('dialog');dialog.className='march-formation-dialog';dialog.setAttribute('aria-labelledby','march-formation-title');
            dialog.innerHTML=`<form><header><h2 id="march-formation-title">${esc(tr('march.formation.save','Save formation'))}</h2><button type="button" data-action="march-formation-close" aria-label="${esc(tr('march.formation.close','Close'))}">×</button></header><div class="march-formation-dialog-body"><p>${esc(tr('march.formation.save_hint','Save the selected {count} troops. This does not send or reserve troops.',{count:fmt(Object.values(troops).reduce((sum,n)=>sum+n,0))}))}</p><div class="march-formation-slots" role="group" aria-label="${esc(tr('march.formation.destination','Save to slot'))}">${formationSlots.map(n=>`<button type="button" data-action="march-formation-slot" data-id="${n}" aria-pressed="false">${n}</button>`).join('')}</div><label for="march-formation-name">${esc(tr('march.formation.name','Formation name'))}</label><input id="march-formation-name" name="name" type="text" maxlength="48" required autocomplete="off"><p id="march-formation-replace-note"></p><p id="march-formation-error" role="alert" hidden></p></div><footer><button type="button" class="button secondary" data-action="march-formation-close">${esc(tr('march.formation.cancel','Cancel'))}</button><button type="submit" class="button" id="march-formation-confirm">${esc(tr('march.formation.save','Save formation'))}</button></footer></form>`;
            const name=dialog.querySelector('#march-formation-name'),error=dialog.querySelector('#march-formation-error'),confirm=dialog.querySelector('#march-formation-confirm');
            const valid=()=>encounter===opened&&parent.open&&parent.dataset.march&&getState().city.world_id===worldId&&Number(getState().city.id)===cityId&&dialog.isConnected;
            function selectSlot(next){
                slot=next;const existing=formations.records.find(f=>Number(f.slot)===slot);name.value=existing?.name||formationName(slot);name.setCustomValidity('');
                dialog.querySelectorAll('[data-action="march-formation-slot"]').forEach(button=>{const filled=formations.records.some(f=>Number(f.slot)===Number(button.dataset.id));button.setAttribute('aria-pressed',String(Number(button.dataset.id)===slot));button.classList.toggle('is-filled',filled);button.classList.toggle('is-empty',!filled);button.setAttribute('aria-label',tr(filled?'march.formation.occupied_slot':'march.formation.empty_slot',filled?'Formation {slot}: occupied slot':'Formation {slot}: empty slot',{slot:button.dataset.id}));});
                dialog.querySelector('#march-formation-replace-note').textContent=existing?tr('march.formation.replace_hint','This replaces the saved formation in slot {slot}.',{slot}):tr('march.formation.empty_selected','Slot {slot} is empty',{slot});
                confirm.textContent=existing?tr('march.formation.replace','Replace formation'):tr('march.formation.save','Save formation');error.hidden=true;
            }
            const historyEntry={token:requestId(),url:location.href};
            history.pushState({...history.state,conquerFormation:historyEntry.token},'',location.href);
            // The nested editor owns Back without discarding the army in its parent.
            const onBack=event=>{
                if(history.state?.conquerFormation===historyEntry.token)return;
                if(location.href===historyEntry.url)event.stopImmediatePropagation();
                window.removeEventListener('popstate',onBack,true);formationHistoryClosing=false;dialog.close();updateFormationToolbar();
                if(encounter===opened&&parent.open)$('#march-formation-save')?.focus({preventScroll:true});
            };
            window.addEventListener('popstate',onBack,true);
            const close=()=>dialog.close();parent.addEventListener('close',close);
            dialog.addEventListener('close',()=>{
                parent.removeEventListener('close',close);dialog.remove();
                if(history.state?.conquerFormation===historyEntry.token){
                    if(location.href===historyEntry.url){formationHistoryClosing=true;updateFormationToolbar();history.back();}
                    else{window.removeEventListener('popstate',onBack,true);const state={...history.state};delete state.conquerFormation;history.replaceState(state,'',location.href);}
                }else window.removeEventListener('popstate',onBack,true);
                if(!formationHistoryClosing&&encounter===opened&&parent.open)$('#march-formation-save')?.focus({preventScroll:true});
            },{once:true});
            dialog.addEventListener('click',event=>{const button=event.target.closest('[data-action]');if(!button)return;event.stopPropagation();if(button.dataset.action==='march-formation-close')close();else if(button.dataset.action==='march-formation-slot'&&!busy)selectSlot(Number(button.dataset.id));});
            dialog.querySelector('form').addEventListener('submit',async event=>{
                event.preventDefault();event.stopPropagation();if(busy||!valid()||!name.reportValidity())return;
                const savedName=name.value.trim();if(!savedName){name.setCustomValidity(tr('march.formation.name_required','Enter a formation name.'));name.reportValidity();return;}
                const payload={action:'formation.save',city_id:cityId,slot,name:savedName,troops};
                busy=true;error.hidden=true;dialog.setAttribute('aria-busy','true');dialog.querySelectorAll('button,input').forEach(control=>control.disabled=true);
                try{await api('defense/action',payload);if(!valid())return;formations.records=formations.records.filter(f=>Number(f.slot)!==payload.slot);formations.records.push({slot:payload.slot,name:savedName,troops:{...troops}});formations.slot=payload.slot;updateFormationToolbar();close();toast(tr('march.formation.saved','Formation {slot} saved.',{slot:payload.slot}));}
                catch(e){if(valid()){error.textContent=e.message||tr('march.formation.save_failed','Saving failed. Please try again.');error.hidden=false;}}
                finally{busy=false;dialog.removeAttribute('aria-busy');dialog.querySelectorAll('button,input').forEach(control=>control.disabled=false);}
            });
            name.addEventListener('input',()=>name.setCustomValidity(''));
            document.body.append(dialog);selectSlot(slot);dialog.showModal();dialog.querySelector('[aria-pressed="true"]').focus({preventScroll:true});
        }
        const monsterJoin=()=>encounter?.kind==='rally-join'&&encounter.rally_target_kind==='monster';
        function selectionCapacity(state=getState()){
            const own=Math.max(0,Number(state.army_limits?.march_capacity??50000)||0),remaining=encounter?.rally_capacity_remaining;
            const rallyLimit=encounter?.kind==='rally-join'?remaining:['rally','monster-rally','shrine-rally','congress-rally','territory-rally'].includes(encounter?.kind)?state.buildings?.hall_of_alliance?.rally_capacity?.total:null;
            return rallyLimit!==null&&rallyLimit!==undefined&&Number.isFinite(Number(rallyLimit))?Math.min(own,Math.max(0,Math.floor(Number(rallyLimit)))):own;
        }
        function rallyLaunchTime(){
            const raw=String(encounter?.rally_launch_at||'').replace(' ','T');
            return raw?Date.parse(/(?:Z|[+-]\d\d:\d\d)$/i.test(raw)?raw:raw+'Z'):NaN;
        }
        function updateRallies(rallies){
            if(encounter?.kind!=='rally-join'||!Array.isArray(rallies))return;
            const rally=rallies.find(r=>Number(r.id)===Number(encounter.rally_id));
            if(!rally){encounter.rally_status='unavailable';update();return;}
            encounter.rally_status=rally.status;encounter.rally_launch_at=rally.launch_at;encounter.rally_target_kind=rally.target_kind||encounter.rally_target_kind;
            const active=(rally.participants||[]).filter(p=>['joining','pending','marching'].includes(p.status));
            const count=troops=>Object.values(troops||{}).reduce((sum,n)=>sum+Math.max(0,Number(n)||0),0);
            encounter.rally_capacity_remaining=rally.capacity!=null?Math.max(0,Number(rally.capacity)-count(rally.troops)-active.reduce((sum,p)=>sum+count(p.troops),0)):null;
            encounter.rally_already_joined=Number(rally.leader_player_id)===Number(getState().city?.player_id)||active.some(p=>Number(p.player_id)===Number(getState().city?.player_id));
            // Polling refreshes the limits without replacing a player's selection or scroll position.
            update();
        }
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
            const pvp=['players','neutral_villages','rally','rally-join','node-attack'].includes(encounter.kind)&&!monsterJoin();
            const losses=side?`${fmt(side.wounded)} verwundet · ${fmt(side.dead)} gefallen`:(previewAttempt===previewKey&&!previewPending?'Rechner zum Prüfen öffnen':encounter.kind.startsWith('monster')?'Wird berechnet …':'Gegner unbekannt · Rechner nutzen');
            box.innerHTML=`<div><span data-i18n="${previewResult?'battle.preview.losses_reference':'comfort.losses'}">${esc(tr(previewResult?'battle.preview.losses_reference':'comfort.losses',previewResult?'Losses (0% battle luck)':'Losses'))}</span><strong>${esc(losses)}</strong></div><div><span>Freie Hospitalplätze</span><strong>${free===null?'Noch unbekannt':fmt(free)}</strong></div>${side?`<p data-i18n="battle.preview.luck_notice">${esc(tr('battle.preview.luck_notice','Actual battle: −10% to +10% luck; losses may differ.'))}</p>`:''}${side&&free!==null&&side.wounded>free?'<p class="march-preflight-danger">Hospital zu klein: Weitere Verwundete können fallen.</p>':''}<p class="${pvp?'march-preflight-danger':''}">${pvp?'Dein Stadtschutz endet beim Entsenden.':'Dein Stadtschutz bleibt bestehen.'}</p>${encounter.kind.includes('rally')?'<p>Berechnung nur für deinen Beitrag; Sammelzeit kommt zur Laufzeit hinzu.</p>':''}${window.ConquerBossMechanic?.render(previewResult?.boss_mechanic||findTarget()?.definition?.boss_mechanic,{rally:encounter.kind.includes('rally')})||''}`;
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
                try{const result=await api('march/preview',{kind:encounter.kind,target_id:Number(target.id),target_x:coord(target,'x'),target_y:coord(target,'y'),troops:selected()});if(key===previewKey){previewResult=result;update();}}
                catch{if(key===previewKey&&$('#march-preflight strong'))$('#march-preflight strong').textContent='Rechner zum Prüfen öffnen';}
                finally{previewPending=false;if(key!==previewKey&&$('#game-dialog').open)updatePreview();}
            },650);
        }
        const countOf=t=>Math.max(0,Number(getState().troops[t.code])||0);
        const selected=()=>Object.fromEntries(rows.map(t=>[t.code,Number($(`#march-unit-${t.code}`)?.value||0)]).filter(([,n])=>n!==0));
        const actionPoints=()=>Number(getProfile?.()?.action_points??getState().city?.action_points??0);
        const landmarkRallyKind=kind=>['shrine-rally','congress-rally','territory-rally'].includes(kind);
        const landmark=kind=>/^(congress|shrine)(-garrison|-rally)?$/.test(kind||'')||kind==='alliance-center-garrison'||kind==='territory-rally';
        const allowed=(kind,target)=>kind.endsWith('-garrison')?target?.can_garrison:target?.can_attack&&(!target.event||(target.event.active&&new Date(target.event.ends_at.replace(' ','T')+'Z')>Date.now()));
        const findTarget=()=>encounter?.kind==='territory-rally'?encounter.target:encounter?.kind==='rally-join'?(encounter.target||(getState().players||[]).find(t=>Number(t.id)===encounter.id)):encounter?.kind==='alliance-center-garrison'?(getState().alliance_structures||[]).find(t=>Number(t.id)===encounter.id)||encounter?.target:encounter?.kind.startsWith('shrine')?([...(getState().shrines||[]),...(getState().event_shrines||[])].find(t=>Number(t.id)===encounter.id)||encounter?.target):encounter?.kind.startsWith('congress')?(getState().congress||encounter?.target):encounter&&(encounter.kind==='neutral_villages'?(getState().neutral_villages||[]):encounter.kind.startsWith('monster')?getState().monsters:encounter.kind==='charms'?(getState().charms||[]):['nodes','node-attack'].includes(encounter.kind)?getState().nodes:(getState().players||[])).find(t=>Number(t.id)===encounter.id)||encounter?.target;
        // NPC villages and player cities have independent IDs and level fields.
        const villageLevel=target=>encounter?.kind==='neutral_villages'?target?.level:target?.castle_level;
        const neutralAttackNote=()=>tr('march.neutral.attack_note','Troop losses are possible. Attacking ends your city protection.');
        const coord=(target,axis)=>Number(target?.[`coord_${axis}`]??target?.[axis]??0);
        const charmLabels={construction_speed:'Baugeschwindigkeit',construction:'Baugeschwindigkeit',research_speed:'Forschungsgeschwindigkeit',research:'Forschungsgeschwindigkeit',troop_hp:'Truppen-LP',troops_hp:'Truppen-LP',troop_attack:'Truppenangriff',troops_attack:'Truppenangriff',troop_defense:'Truppenverteidigung',troops_defense:'Truppenverteidigung',carry_capacity:'Traglast',carry:'Traglast',march_speed:'Marschtempo',gathering_speed:'Sammeltempo',gathering:'Sammeltempo'};
        const requestId=()=>globalThis.crypto?.randomUUID?.()||`charm_${Date.now().toString(36)}_${Math.random().toString(36).slice(2)}`;
        const expires=target=>{const raw=String(target?.expires_at||'').replace(' ','T');return Date.parse(/[zZ]|[+-]\d\d:\d\d$/.test(raw)?raw:raw+'Z');};
        const shortTime=seconds=>{seconds=Math.max(0,Math.round(Number(seconds)||0));const minutes=Math.floor(seconds/60),rest=seconds%60;return minutes?`${minutes}:${String(rest).padStart(2,'0')} ${window.ConquerLocale.t('time.minute_short')}`:`${rest} ${window.ConquerLocale.t('time.second_short')}`;};
        const clockTime=seconds=>{seconds=Math.max(0,Math.ceil(Number(seconds)||0));return `${Math.floor(seconds/60)}:${String(seconds%60).padStart(2,'0')}`;};
        const missionSpeed=(troop,target)=>{
            troop=getState().troop_defs.find(t=>Number(t.code)===Number(troop.code))||troop;
            const kind=encounter?.kind||'';
            let field='march_speed';
            if(kind==='nodes')field='gather_speed';
            else if(kind==='node-attack')field='field_attack_speed';
            else if(kind==='monsters')field='monster_march_speed';
            else if(kind==='monster-rally'||monsterJoin())field='monster_rally_speed';
            else if(kind==='charms')field='charm_march_speed';
            else if(kind==='players'||kind==='neutral_villages')field='pvp_march_speed';
            else if(kind==='rally'||kind==='rally-join'||landmarkRallyKind(kind))field='pvp_rally_speed';
            else if(landmark(kind))field=kind.endsWith('-garrison')||target?.alliance_id==null?'shrine_neutral_speed':'shrine_occupied_speed';
            if(['players','node-attack','rally'].includes(kind)||landmarkRallyKind(kind)||(landmark(kind)&&!kind.endsWith('-garrison')&&target?.alliance_id!=null)){
                const counts=selected();let totalPower=0,cavalryPower=0;
                for(const unit of getState().troop_defs){const p=Number(counts[unit.code]||0)*Number(unit.power||0);totalPower+=p;if(Number(unit.type)===3)cavalryPower+=p;}
                if(totalPower>0&&cavalryPower/totalPower>=.7)field=kind==='players'?'cavalry_pvp_march_speed':kind==='node-attack'?'cavalry_field_attack_speed':kind==='rally'||landmarkRallyKind(kind)?'cavalry_pvp_rally_speed':'cavalry_shrine_occupied_speed';
            }
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
            $('.march-formation-dialog')?.close();
            encounter={id:kind==='territory-rally'?String(id):Number(id),kind,...options,requestId:options.requestId||requestId(),formations:{records:[],loading:true,error:false,slot:0}};
            const state=getState(),target=findTarget();
            if(!target){toast('Dieses Ziel ist nicht mehr verfügbar.');return;}
            // Map polling can replace its viewport while the command window is open.
            // Retain the selection; every calculation and dispatch validates it on the server.
            encounter.target=target;
            if(kind==='monsters'&&(target.monster_type==='rally'||target.definition?.type==='rally')){kind='monster-rally';encounter.kind=kind;}
            cap=selectionCapacity(state);
            rows=state.troop_defs.filter(t=>Number(t.tier)===1||countOf(t)>0).sort((a,b)=>b.tier-a.tier||a.type-b.type);
            const territory=kind==='territory-rally',join=kind==='rally-join',joiningMonster=monsterJoin(),allianceCenter=kind==='alliance-center-garrison',shrine=kind.startsWith('shrine'),landmarkRally=landmarkRallyKind(kind),congress=landmark(kind),garrison=kind.endsWith('-garrison'),neutral=kind==='neutral_villages',pvp=['players','neutral_villages','rally','rally-join'].includes(kind),monster=kind.startsWith('monster'),charm=kind==='charms',monsterRally=kind==='monster-rally',fieldAttack=kind==='node-attack',combat=monster||pvp||congress||fieldAttack,resource={1:'food',2:'lumber',3:'stone',4:'gold',5:'crystal'}[target.object_type]||'food';
            const targetHp=Math.max(0,Number(target.hp_current)||0),targetMaxHp=Math.max(1,targetHp,Number(target.hp_max)||0);
            const baseAp=monster?Number(target.definition?.action_point_cost??target.action_point_cost??0):joiningMonster?Number(options.rally_ap_cost??0):0;
            encounter.actionPointCost=baseAp>0?Math.max(1,Math.ceil(baseAp*(1-Number(state.monster_ap_discount||0))-1e-8)):0;
            const resourceName={food:'Nahrung',lumber:'Holz',stone:'Stein',gold:'Gold',crystal:'Kristalle'}[resource];
            const rawTitle=allianceCenter?target.alliance_name||'Allianzzentrum':congress?target.name||'Kongress':neutral?target.name||tr('copy.cb08a82ce485ef5c','Free village'):pvp?target.display_name||target.username:monster?target.definition.name:charm?`${{normal:'Normaler',epic:'Epischer',legendary:'Legendärer'}[target.grade]||'Magischer'} Charm`:resourceName;
            const title=congress?rawTitle:pvp?rawTitle:monster?(/skeleton/i.test(rawTitle)?'Skeletttrupp':/golem/i.test(rawTitle)?'Steingolem':/orc/i.test(rawTitle)?'Orktrupp':rawTitle):charm?rawTitle:({food:'Getreidehof',lumber:'Holzfällerlager',stone:'Steinbruch',gold:'Goldmine',crystal:'Kristallader'}[resource]);
            const targetImage=territory?window.ConquerTerritoryArt.image(base,target):allianceCenter?`${base}/assets/art/map/painted-v2/alliance-center.webp`:congress?(shrine?`${base}/assets/art/map/painted-v2/shrine-${['forest','ice','sand','lava'].includes(target.element)?target.element:'forest'}.png?v=shrines1`:`${base}/assets/art/territory-v4/congress-forum.webp`):pvp?window.ConquerCastleSkins.image(base,target.city_skin):monster?monsterPortrait(target.definition?.art,rawTitle):charm?`${base}/assets/art/map/runes-v1/${['normal','epic','legendary'].includes(target.grade)?target.grade:'normal'}-detail.webp`:`${base}/assets/art/fantasy-village-v1/${{food:'world-farm-v8',lumber:'world-lumber-v8',stone:'world-quarry-v8',gold:'world-gold-v2',crystal:'world-crystal-v8'}[resource]}.png`;
            const rewardNames={food:'Nahrung',lumber:'Holz',stone:'Stein',gold:'Gold'};
            const resourceRewards=Object.entries(target.definition?.resource_reward||{}).filter(([key,value])=>rewardNames[key]&&Number(value)>0).map(([key,value])=>({label:rewardNames[key],value:Number(value),art:`${window.ConquerItemArt?.resourceUrl(base,key) || `${base}/assets/art/ui-resources/${key}.png`}`}));
            const dropRewards=[...(target.definition?.drops||[]),...(target.definition?.fragment_drops||[]),...(target.definition?.relic_drops||[])].filter(drop=>Number(drop.probability)>0&&Number(drop.count??drop.quantity)>0).map(drop=>{const r=window.ConquerRewards.resolve(drop,getKingdom?.(),base),min=drop.count_min??drop.quantity_min;return {label:['relic','fragment'].includes(r.type)?`${r.name} · ${r.kind}`:r.name,value:r.quantity,quantity_min:min,art:r.icon};});
            const gemDrop=target.definition?.gems_drop,gemReward=gemDrop&&Number(gemDrop.amount)>0?[{label:'Edelsteine',value:Number(gemDrop.amount),art:`${window.ConquerItemArt?.resourceUrl(base,'gems') || `${base}/assets/art/items/gems.svg`}`}]:[];
            const rewards=monster?[...resourceRewards,...dropRewards,...gemReward]:[];
            cardSignature='';rallyMinutes=5;
            openDialog(`<div class="march-command ${combat?'is-attack':'is-gather'} ${monsterRally?'is-monster-rally':''} ${charm?'is-charm-collect':''} ${join?'is-rally-join':''}" data-view="troops"><header class="march-command-heading"><h2>${congress?(landmarkRally?esc(tr('landmark.rally','Rally')):garrison?'Garnison verstärken':shrine?'Schrein angreifen':'Kongress angreifen'):pvp?(kind==='rally'?'Rally starten':join?esc(tr('rally.join.title','Join rally')):'Solo-Angriff'):monsterRally?'Monster-Rally':monster?'Monsterangriff':charm?'Charm einsammeln':fieldAttack?'Sammler angreifen':'Rohstoffe sammeln'}</h2><span>Marsch</span><div class="march-detail-tabs" role="tablist" aria-label="Marschansicht"><button type="button" role="tab" data-action="march-view" data-id="troops" aria-selected="true" aria-controls="march-formation-panel">Truppen</button><button type="button" role="tab" data-action="march-view" data-id="target" aria-selected="false" aria-controls="march-target-panel">Ziel</button></div></header>
                <div class="march-layout"><aside class="march-target"><div class="march-target-heading"><h3${territory?' translate="no"':pvp||allianceCenter?' data-user-content':''}>${esc(title)}</h3><div class="march-target-location"><span class="march-coordinates">${territory?esc({commune:'Commune',canton:'Shrine',crown:'Congress'}[target.kind]||''):congress?(shrine?'Schrein':'Kongress'):join?esc(tr('rally.join.leader_label','Rally leader')):neutral?esc(tr('common.level','Level'))+' '+(villageLevel(target)??'–'):pvp?'Burg '+(villageLevel(target)??'–'):charm?esc(charmLabels[target.stat_category]||target.stat_category||'Magischer Bonus'):'Lv. '+Number(target.definition?.level??target.level??1)} · X ${coord(target,'x')} / Y ${coord(target,'y')}</span><button type="button" class="march-share-target" data-action="march-share-target" aria-label="${esc(tr('march.share_target_label','Share {name} in a chat',{name:territory||pvp||allianceCenter?title:window.ConquerLocale.text(title)}))}" title="Ziel teilen"><span aria-hidden="true">⇗</span><span>Teilen</span></button></div></div><div class="march-target-art ${monster?'':'resource-target'}"><img src="${targetImage}" alt=""></div><div class="march-target-stat"><span>${join?esc(tr('rally.join.space','Space for your troops')):congress?'Verteidiger':neutral?esc(tr('common.level','Level')):pvp?'Burgstufe':monster?'Lebenspunkte':charm?'Bonus':'Vorrat'}</span><strong id="march-target-value">${territory?(target.owner_alliance_id?'–':fmt(target.npc_troops||0)):charm?'+'+fmt(target.bonus_pct)+' %':pvp&&villageLevel(target)==null?'–':fmt(congress?target.garrison_total||0:pvp?villageLevel(target):monster?target.hp_current:target.resource_amount)}</strong></div>${charm?`<p class="march-pvp-note">Der Charm wirkt nach dem Einsammeln ${esc(shortTime(target.effect_duration_seconds))}. Er muss bei Ankunft noch verfügbar sein.</p>`:''}${monster?`<div class="march-target-health" role="progressbar" aria-label="Monster-Lebenspunkte" aria-valuemin="0" aria-valuemax="${targetMaxHp}" aria-valuenow="${targetHp}"><span style="width:${100*targetHp/targetMaxHp}%"></span></div>`:''}${congress?`<p class="march-pvp-note">${territory?esc(tr('landmark.territory_rally_note','Rally to conquer this territory. Troop losses are possible. Starting ends your city protection.')):garrison?'Deine Truppen bleiben zur Verteidigung am Ziel, bis du sie zurückrufst.':'Gemeinsam die Besatzung schwächen. Nach dem Sieg eine Stunde halten. Truppenverluste sind möglich.'}</p>`:''}${fieldAttack?'<p class="march-pvp-note">Angriff auf die Sammler am Feld. Truppenverluste sind möglich. Ein Sieg übernimmt das Feld. Dein Stadtschutz endet beim Losschicken.</p>':''}${join?`<p class="march-pvp-note">${esc(tr('rally.join.travel_note','Your troops travel to the rally leader and must arrive before departure.'))} ${esc(joiningMonster?tr('rally.join.shield_kept','Your city protection stays active.'):tr('rally.join.shield_lost','Joining ends your city protection. Troop losses are possible.'))}</p>`:neutral?`<p class="march-pvp-note" data-i18n="march.neutral.attack_note">${esc(neutralAttackNote())}</p>`:pvp?'<p class="march-pvp-note">Angriffe beenden deinen Schutz. Truppen können fallen; geschützte Städte und Allianzmitglieder sind keine gültigen Ziele.</p>':''}${rewards.length?`<div class="march-rewards"><h4>Mögliche Beute</h4><div>${rewards.map(reward=>`<span title="${esc(reward.label)}: ${reward.quantity_min!=null&&Number(reward.quantity_min)!==Number(reward.value)?fmt(reward.quantity_min)+'–'+fmt(reward.value):fmt(reward.value)}"><img src="${reward.art}" alt="${esc(reward.label)}"><strong>${reward.quantity_min!=null&&Number(reward.quantity_min)!==Number(reward.value)?fmt(reward.quantity_min)+'–'+fmt(reward.value):fmt(reward.value)}</strong></span>`).join('')}</div></div>`:''}</aside>
                <section class="march-formation" id="march-formation-panel" aria-label="Truppenkomposition">${formationToolbar()}
                <div class="march-unit-list" tabindex="0" role="region" aria-label="Verfügbare Truppen">${rows.map(t=>`<div class="march-unit-row ${countOf(t)?'':'unavailable'}" data-unit="${t.code}" data-type="${t.type}"><div class="march-portrait troop-tier-frame" data-troop-tier="${Number(t.tier)}"><img src="${base}/assets/art/characters/fantasy-troops-v3/${['guardian','fire-archer-bow','shadow-rider'][Number(t.type)-1]}-t${t.tier}-thumb.webp" alt=""><span>T${t.tier}</span></div><div class="march-unit-name"><strong>${esc(unitName(t))}</strong><small id="march-stock-${t.code}">${fmt(countOf(t))} verfügbar</small></div><input class="march-range" type="range" min="0" max="${Math.min(cap,countOf(t))}" step="1" value="0" data-unit-range="${t.code}" aria-label="${esc(unitName(t))} mit Regler auswählen" ${countOf(t)?'':'disabled'}><div class="march-unit-amount"><input id="march-unit-${t.code}" class="number-control" type="number" inputmode="numeric" min="0" max="${Math.min(cap,countOf(t))}" step="1" value="0" aria-label="Anzahl ${esc(unitName(t))}" ${countOf(t)?'':'disabled'}><button type="button" class="march-row-max" data-action="march-unit-max" data-id="${t.code}" aria-label="Max ${esc(unitName(t))}" ${countOf(t)?'':'disabled'}>Max</button></div></div>`).join('')}</div></section>
                <aside class="march-army" aria-label="Ausgewählte Armee"><h3>Deine Auswahl</h3><div class="march-capacity"><span>Truppen</span><strong><span id="march-selected">0</span> / <span id="march-capacity">${fmt(cap)}</span></strong></div><div class="march-capacity-track" role="progressbar" aria-label="Marschkapazität" aria-valuemin="0" aria-valuemax="${cap}" aria-valuenow="0"><span></span></div><div id="march-selected-cards" class="march-selected-cards" aria-live="polite"></div><div class="march-summary"><div><span>${monster||joiningMonster?'Armeemacht':combat?'Grundangriff':charm?'Sammeltrupp':'Traglast'}</span><strong id="march-strength">0</strong></div><div><span>Marschplätze</span><strong id="march-slots"></strong></div><div><span>${join?esc(Number.isFinite(rallyLaunchTime())?tr('rally.join.travel_departure','Travel / departs in'):tr('rally.join.travel','To rally leader')):'Laufzeit'}</span>${join&&Number.isFinite(rallyLaunchTime())?'<strong><b id="march-travel-time">–</b> / <b id="march-rally-countdown">–</b></strong>':'<strong id="march-travel-time">–</strong>'}</div><div><span>${esc(tr('march.ap_cost_available','AP cost / available'))}</span><strong id="march-action-points">${encounter.actionPointCost} / ${fmt(actionPoints())}</strong></div></div><div id="march-forecast" class="march-forecast" aria-live="polite"></div></aside></div>
                <footer class="march-footer"><div class="march-presets">${join&&typeof encounter.onRallyBack==='function'?`<button type="button" class="button secondary march-rally-back" data-action="march-rally-back" aria-label="${esc(tr('rally.join.back','Back to rally'))}" title="${esc(tr('rally.join.back','Back to rally'))}">←</button>`:''}<button type="button" class="button secondary" data-action="march-clear">Leeren</button><button type="button" class="button gold" data-action="march-max" aria-label="Maximale Truppen auswählen">Max</button></div>${kind==='rally'||monsterRally||landmarkRally?'<button type="button" class="march-time-button" data-action="march-time-open" aria-haspopup="dialog"><span id="march-time-label">5 Min.</span></button>':''}<button type="button" class="button ${combat&&!join?'march-attack':'march-gather'}" id="march-confirm" data-action="march-send">${garrison?'Verstärken':kind==='rally'||monsterRally||landmarkRally?'Rally starten':join?esc(tr('rally.join.deploy','Deploy')):combat?'Angreifen':charm?'Einsammeln':'Sammeln'} <span id="march-button-ap"></span></button></footer></div>`);
            if(kind==='monsters'){
                $('.march-command').classList.add('is-monster-attack');
                $('#march-strength').parentElement.remove();
                $('.march-capacity').insertAdjacentHTML('beforebegin',`<section class="march-power" aria-live="polite" aria-atomic="true"><div class="march-power-values"><div><span data-i18n="march.power.available">${esc(tr('march.power.available','Available power'))}</span><strong id="march-strength">0</strong></div><div class="march-power-required"><span data-i18n="march.power.required">${esc(tr('march.power.required','Required power'))}</span><strong id="march-power-required">0</strong></div></div><div class="march-power-track" aria-hidden="true"><span></span></div><span id="march-power-status" class="march-power-status"></span></section>`);
            }
            if(battlePreview&&['monster-rally','players','rally'].includes(kind)){
                const summary=document.createElement('section');summary.id='march-preflight';summary.className='march-preflight';summary.setAttribute('aria-label','Vor dem Angriff');$('.march-army').append(summary);
                const button=document.createElement('button');button.type='button';button.className='button march-preview-button';button.dataset.action='march-preview';button.textContent='Kampfrechner';button.setAttribute('aria-label','Kampfrechner mit 0 Prozent Kampfglück öffnen');
                $('.march-army').append(button);
            }
            if(territory&&target.kind==='crown'){
                const objective=document.createElement('label');objective.className='march-pvp-note';
                objective.innerHTML=esc(tr('landmark.siege_objective','Siege objective'))+' <select id="march-territory-objective">'+['gate','arsenal','throne'].map(key=>'<option value="'+key+'">'+esc(tr('landmark.objective_'+key,{gate:'Gate',arsenal:'Arsenal',throne:'Throne'}[key]))+'</option>').join('')+'</select>';
                $('.march-target').append(objective);
            }
            if(joiningMonster)$('.march-army').insertAdjacentHTML('beforeend',window.ConquerBossMechanic?.render(encounter.rally_boss_mechanic,{rally:true})||'');
            $('#game-dialog').classList.add('march-dialog');$('#game-dialog').dataset.march='true';
            $('.march-target').id='march-target-panel';
            rows.forEach(t=>{
                $(`#march-unit-${t.code}`).addEventListener('input',update);
                $(`[data-unit-range="${t.code}"]`).addEventListener('input',e=>{$(`#march-unit-${t.code}`).value=e.target.value;update();});
            });
            defaultCounts=charm?charmCollector():kind==='nodes'?gatheringSelection(target):monster?monsterSelection(target,monsterRally):join?maximum():maximum(Math.min(50,cap));
            const remembered=kind==='nodes'?null:savedComposition();setCounts(remembered||defaultCounts);
            if(remembered){const note=document.createElement('div');note.className='march-remembered';note.innerHTML='Letzte Auswahl angepasst. <button type="button" data-action="march-default">Standard wählen</button>';$('.march-army').prepend(note);}
            fetchFormations();
        }
        function update() {
            if(!$('#game-dialog')?.open||!$('#game-dialog').dataset.march)return;
            const state=getState(),target=findTarget(),counts=selected(),total=Object.values(counts).reduce((a,b)=>a+b,0),available=rows.reduce((a,t)=>a+countOf(t),0);
            cap=selectionCapacity(state);const baseSlots=state.army_limits?.march_slots||3,extraSlots=state.army_limits?.gather_march_slots||0,huntSlots=state.army_limits?.hunt_march_slots||0;
            const gathering=state.marches.filter(m=>Number(m.march_type)===9).length;
            const hunting=state.marches.filter(m=>Number(m.march_type)===5).length;
            const slots=baseSlots+(encounter.kind==='nodes'?extraSlots:Math.min(extraSlots,gathering))+(encounter.kind==='monsters'?huntSlots:Math.min(huntSlots,hunting));
            const selectedTypes=new Set(rows.filter(t=>Number(counts[t.code])>0).map(t=>Number(t.type))),singleType=selectedTypes.size===1;
            const basePower=[0,0,0];for(const t of rows)basePower[Number(t.type)-1]+=Math.max(0,Number(counts[t.code]||0))*Number(t.power||0);
            const totalBasePower=basePower.reduce((a,n)=>a+n,0),shares=basePower.map(p=>totalBasePower?p/totalBasePower:0),formation=shares[0]>=.7?'infantry':shares[1]>=.7?'ranged':Math.min(...shares)>=.2?'combined':'none';
            let invalid=false,invalidUnit=null,attack=0,power=0,carry=0;
            rows.forEach(t=>{
                const n=Number(counts[t.code]||0),stock=countOf(t),input=$(`#march-unit-${t.code}`),range=$(`[data-unit-range="${t.code}"]`),bad=!Number.isSafeInteger(n)||n<0||n>stock;
                invalid ||= bad;if(bad&&!invalidUnit)invalidUnit=t;attack+=Math.max(0,n)*Number(t.attack||0);
                const rally=encounter.kind==='monster-rally'||monsterJoin(),powerField=rally?(singleType?'monster_rally_power_single_type':'monster_rally_power'):(singleType?'monster_power_single_type':'monster_power');
                const unitPower=!singleType?t[rally?'monster_rally_power_by_formation':'monster_power_by_formation']?.[formation]:undefined;
                power+=Math.max(0,n)*Number(unitPower??t[powerField]??t.power??0);carry+=Math.max(0,n)*Number(state.troop_defs.find(u=>u.code===t.code)?.gather_carry??t.carry??0);
                input.max=Math.min(stock,cap);input.disabled=stock===0&&n===0;input.setAttribute('aria-invalid',String(bad));
                range.max=Math.min(stock,cap);range.disabled=stock===0||cap===0;range.value=Number.isFinite(n)?n:0;
                range.style.setProperty('--fill',`${stock&&cap?Math.min(100,Math.max(0,n/Math.min(stock,cap)*100)):0}%`);
                $(`#march-stock-${t.code}`).textContent=`${fmt(stock)} verfügbar`;
                $(`[data-unit="${t.code}"]`).classList.toggle('unavailable',stock===0);
                $(`[data-action="march-unit-max"][data-id="${t.code}"]`).disabled=stock===0||cap===0;
            });
            const carryCapacity=Math.max(0,Math.min(Math.floor(carry+1e-8),Number(target?.resource_amount||0)));
            $('#march-available').textContent=`${fmt(available)} verfügbar`;
            $('#march-selected').textContent=fmt(total);
            $('#march-capacity').textContent=fmt(cap);
            const capacityBar=$('.march-capacity-track');capacityBar.setAttribute('aria-valuemax',cap);capacityBar.setAttribute('aria-valuenow',Math.max(0,Math.min(cap,Number.isFinite(total)?total:0)));capacityBar.querySelector('span').style.width=`${Math.max(0,Math.min(100,total/cap*100))||0}%`;capacityBar.classList.toggle('over-cap',total>cap);
            if(target)$('#march-target-value').textContent=encounter.kind==='territory-rally'?(target.owner_alliance_id?'–':fmt(target.npc_troops||0)):encounter.kind==='rally-join'?fmt(cap):encounter.kind==='charms'?`+${fmt(target.bonus_pct)} %`:['players','neutral_villages','rally','rally-join'].includes(encounter.kind)&&villageLevel(target)==null?'–':fmt(landmark(encounter.kind)?target.garrison_total||0:encounter.kind.startsWith('monster')?target.hp_current:['nodes','node-attack'].includes(encounter.kind)?target.resource_amount:villageLevel(target));
            const cards=rows.map(t=>({...t,count:Number(counts[t.code]),name:unitName(t)})).filter(t=>Number.isSafeInteger(t.count)&&t.count>0);
            const signature=JSON.stringify(cards.map(t=>[t.code,t.count,t.name]));
            if(signature!==cardSignature){cardSignature=signature;$('#march-selected-cards').innerHTML=cards.length?cards.map(t=>`<div class="march-selected-card troop-tier-frame" data-type="${t.type}" data-troop-tier="${Number(t.tier)}" data-selected-unit="${t.code}" title="${esc(t.name)} · T${t.tier}: ${fmt(t.count)}"><img src="${base}/assets/art/characters/fantasy-troops-v3/${['guardian','fire-archer-bow','shadow-rider'][Number(t.type)-1]}-t${t.tier}-thumb.webp" alt=""><span class="march-card-tier">T${t.tier}</span><span class="march-card-name">${esc(t.name)}</span><strong>${fmt(t.count)}</strong></div>`).join(''):'<p class="march-selection-empty">Keine Truppen gewählt</p>';}
            $('#march-strength').textContent=fmt(encounter.kind==='nodes'?carryCapacity:encounter.kind==='charms'?total:encounter.kind.startsWith('monster')||monsterJoin()?Math.round(power):attack);
            if(encounter.kind==='monsters'){
                const required=monsterRequiredPower(target),ratio=power/required,powerCard=$('.march-power');
                const status=!target?'unavailable':total===0?'empty':ratio>=1?'ready':'low';
                powerCard.dataset.status=status;
                $('#march-power-required').textContent=target?fmt(required):'–';
                $('.march-power-track span').style.width=`${target?Math.max(0,Math.min(100,ratio*100))||0:0}%`;
                const key=`march.power.${status}`,fallback={unavailable:'Target unavailable',empty:'No troops selected',ready:'Required power reached',low:'Not enough power'}[status];
                $('#march-power-status').dataset.i18n=key;
                $('#march-power-status').textContent=tr(key,fallback);
            }
            $('#march-slots').textContent=`${Math.max(0,slots-state.marches.length)} / ${slots} frei`;
            // Mission-specific server values already contain research, world, talents and the equipped skin exactly once.
            const selectedSpeeds=rows.filter(t=>Number(counts[t.code])>0).map(t=>missionSpeed(t,target));
            const distance=target?Math.hypot(coord(target,'x')-Number(state.city?.coord_x),coord(target,'y')-Number(state.city?.coord_y)):0;
            const travelSeconds=selectedSpeeds.length?Math.max(5,Math.floor(distance*100*Number(state.world?.map_profile?.travel_scale??1)/Math.max(1,Math.min(...selectedSpeeds)))):null;
            const join=encounter.kind==='rally-join',launch=rallyLaunchTime(),remainingSeconds=Number.isFinite(launch)?Math.floor(launch/1000)-Math.floor(now()/1000):null;
            const speedField=monsterJoin()?'monster_rally_speed':'pvp_rally_speed';
            const accurateJoinTravel=join&&[state.city?.coord_x,state.city?.coord_y,target?.coord_x??target?.x,target?.coord_y??target?.y].every(value=>value!==null&&value!==undefined&&Number.isFinite(Number(value)))&&rows.filter(t=>Number(counts[t.code])>0).every(t=>Number(state.troop_defs.find(unit=>Number(unit.code)===Number(t.code))?.[speedField])>0);
            const joinExpired=join&&remainingSeconds!==null&&remainingSeconds<=0,joinLate=join&&accurateJoinTravel&&travelSeconds!==null&&remainingSeconds!==null&&travelSeconds>remainingSeconds;
            const joinUnavailable=join&&((encounter.rally_status&&encounter.rally_status!=='gathering')||encounter.rally_already_joined);
            const duration=seconds=>window.ConquerLocale?.formatDuration?.(seconds)||shortTime(seconds);
            $('#march-travel-time').textContent=travelSeconds!==null?(join&&Number.isFinite(launch)?clockTime(travelSeconds):shortTime(travelSeconds)):'–';
            if($('#march-rally-countdown'))$('#march-rally-countdown').textContent=remainingSeconds===null?'–':clockTime(remainingSeconds);
            $('#march-action-points').textContent=`${fmt(encounter.actionPointCost||0)} / ${fmt(actionPoints())}`;
            if($('#march-button-ap'))$('#march-button-ap').textContent=encounter.actionPointCost>0?`· ⚡ ${fmt(encounter.actionPointCost)} AP`:'→';
            let message='',warning=false,forecastTranslation=null;
            if(!target){message='Ziel nicht mehr verfügbar.';warning=true;}
            else if(joinUnavailable){message=encounter.rally_already_joined?tr('rally.join.already_joined','You are already taking part in this rally.'):tr('rally.join.unavailable','This rally is no longer gathering troops. Return to its details.');warning=true;}
            else if(joinExpired){message=tr('rally.join.expired','The gathering time has ended. Return to the rally details.');warning=true;}
            else if(join&&cap===0){message=tr('rally.join.full','This rally has no troop capacity left.');warning=true;}
            else if(landmark(encounter.kind)&&!(allowed(encounter.kind,target))){message='Das Ziel ist aktuell nicht angreifbar. Öffne seine Übersicht erneut.';warning=true;}
            else if(encounter.kind==='node-attack'&&!target.can_attack){message='Diese Sammler sind nicht mehr angreifbar.';warning=true;}
            else if(encounter.kind==='nodes'&&(target.gatherer_march_id||Number(target.resource_amount)<=0)){message='Rohstofffeld belegt oder erschöpft.';warning=true;}
            else if(encounter.kind==='charms'&&(target.collectible===false||expires(target)<=Date.now())){message='Dieser Charm ist nicht mehr einsammelbar.';warning=true;}
            else if(invalid){message=`Menge ungültig: ${unitName(invalidUnit)}.`;warning=true;}
            else if(total>cap){message=join?tr('rally.join.capacity_changed','Room for {count} troops. Choose Max to adjust your selection.',{count:fmt(cap)}):`Maximal ${fmt(cap)} Truppen. Max verteilt passend.`;warning=true;}
            else if(state.marches.length>=slots){message='Alle Marschplätze sind belegt.';warning=true;}
            else if(total===0){message=available?'Wähle Truppen oder Max.':'Bilde zuerst Truppen aus.';}
            else if(joinLate){message=tr('rally.join.too_late','These troops would arrive after departure. Choose faster troops or another rally.');warning=true;}
            else if(join){message=(remainingSeconds===null?tr('rally.join.travel_note','Your troops travel to the rally leader and must arrive before departure.'):tr('rally.join.ready','Arrival in {travel} · {buffer} before departure.',{travel:duration(travelSeconds),buffer:duration(Math.max(0,remainingSeconds-travelSeconds))}))+' '+(monsterJoin()?tr('rally.join.shield_kept','Your city protection stays active.'):tr('rally.join.shield_lost','Joining ends your city protection. Troop losses are possible.'));}
            else if(encounter.kind.startsWith('monster')){
                const required=monsterRequiredPower(target),ratio=power/required;
                warning=encounter.kind!=='monster-rally'&&ratio<1;
                if(encounter.kind==='monster-rally'&&target.definition?.boss_mechanic){
                    const resolved=previewKey===calculationKey()?previewResult:null;
                    const key=resolved?'boss.common.forecast_resolved':'boss.common.forecast_pending',parameters={power:fmt(resolved?.army_power??Math.round(power)),required:fmt(resolved?.required_power??required),minutes:fmt(rallyMinutes)};
                    message=tr(key,resolved?'Your contribution: {power} / {required} power (boss skill included, 0% luck). Gathering: {minutes} min.':'Your power: {power} / base target {required}. Boss effects: see preview. Gathering: {minutes} min.',parameters);
                    forecastTranslation={key,parameters};
                }
                else if(encounter.kind==='monster-rally')message=`Eigene Macht ${fmt(Math.round(power))} / Rally-Ziel ${fmt(required)} · ${rallyMinutes} Min. Sammelzeit; Mitglieder ergänzen die Macht.`;
                // Solo monster power and its threshold are shown in the prominent power card.
            } else if(encounter.kind==='charms')message=`${charmLabels[target.stat_category]||'Charm-Bonus'} +${fmt(target.bonus_pct)} % · Truppen sammeln ihn bei Ankunft einmalig ein.`; else if(encounter.kind==='node-attack')message='Feldangriff · Truppenverluste möglich. Bei Sieg sammeln deine Überlebenden weiter. Angriff beendet deinen Schutz.'; else if(landmark(encounter.kind))message=encounter.kind.endsWith('-garrison')?'Truppen bleiben als Garnison am Ziel.':'Allianzangriff · bleibende Verluste der Verteidiger, eigene Verluste möglich.'; else if(encounter.kind==='neutral_villages'){message=neutralAttackNote();forecastTranslation={key:'march.neutral.attack_note',parameters:{}};} else if(['players','rally','rally-join'].includes(encounter.kind))message=(encounter.kind==='rally'?'Rally: '+(rallyMinutes)+' Min. Sammelzeit. ':'')+'PvP: Truppenverluste möglich. Angriff beendet deinen Schutz.'; else message=`Bis zu ${fmt(carryCapacity)} ${resourceLabel(target.object_type)} · ${target.gather_rate?shortTime(Math.ceil(carryCapacity/target.gather_rate))+' Sammeln am Feld':'Sammeln am Feld'}.`;
            if(actionPoints()<Number(encounter.actionPointCost||0)){message='Nicht genügend Aktionspunkte.';warning=true;forecastTranslation=null;}
            const forecast=$('#march-forecast');
            if(forecastTranslation){forecast.dataset.i18n=forecastTranslation.key;forecast.dataset.i18nParams=JSON.stringify(forecastTranslation.parameters);}
            else{delete forecast.dataset.i18n;delete forecast.dataset.i18nParams;}
            forecast.textContent=message;forecast.hidden=!message;forecast.classList.toggle('caution',warning);
            $('#march-confirm').disabled=Boolean(encounter.submitting)||joinUnavailable||joinExpired||joinLate||!target||(encounter.kind==='node-attack'&&!target.can_attack)||(landmark(encounter.kind)&&!(allowed(encounter.kind,target)))||(encounter.kind==='charms'&&(target.collectible===false||expires(target)<=Date.now()))||invalid||total<1||total>cap||state.marches.length>=slots||actionPoints()<Number(encounter.actionPointCost||0)||(encounter.kind==='nodes'&&Boolean(target.gatherer_march_id||Number(target.resource_amount)<=0));
            updateFormationToolbar();updatePreview();
        }
        function resourceLabel(type){return {1:'Nahrung',2:'Holz',3:'Stein',4:'Gold',5:'Kristalle'}[type]||'Vorräte';}
        function onClick(act,button) {
            if(!act.startsWith('march-'))return false;
            if(act==='march-formation-load'){loadFormation(Number(button.dataset.id));return true;}
            if(act==='march-formation-save'){saveFormationDialog();return true;}
            if(act==='march-formations-retry'){fetchFormations();return true;}
            if(act==='march-rally-back'){encounter?.onRallyBack?.();return true;}
            if(act==='march-preview'){
                const troops=selected(),target=findTarget();
                if(!target||!Object.keys(troops).length||Object.values(troops).some(n=>!Number.isSafeInteger(n)||n<0)){toast('Wähle zuerst eine gültige Truppenzusammenstellung.');return true;}
                const key=calculationKey();battlePreview?.open({kind:encounter.kind,target,troops,onResult:result=>{if(key===calculationKey()){previewResult=result;update();}}});return true;
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
                const submittedEncounter=encounter;submittedEncounter.submitting=true;$('#march-confirm').disabled=true;
                            const kind=encounter.kind,path=kind==='alliance-center-garrison'?'community/action':landmarkRallyKind(kind)?'rally/start-shrine':landmark(kind)?`shrines/${Number(target.id)}/${kind.endsWith('-garrison')?'garrison':'attack'}`:({monsters:'march/dispatch','monster-rally':'rally/start-monster',nodes:'march/dispatch-gather','node-attack':'march/dispatch-field-attack',charms:'march/dispatch-charm',players:'march/dispatch-player',neutral_villages:'march/dispatch-neutral-village',rally:'rally/start','rally-join':'rally/join'})[kind];
                const payload={target_x:coord(target,'x'),target_y:coord(target,'y'),troops:rememberTroops,...(kind==='alliance-center-garrison'?{action:'structure.garrison',structure_id:Number(target.id),world_id:Number(getState().city?.world_id),request_id:encounter.requestId}:kind==='charms'?{charm_id:Number(target.id),request_id:encounter.requestId}:kind==='rally-join'?{rally_id:encounter.rally_id}:kind==='rally'?{target_player_id:Number(target.id),rally_minutes:Number(rallyMinutes),message:''}:kind==='monster-rally'?{rally_minutes:Number(rallyMinutes),message:''}:landmarkRallyKind(kind)?{target_id:Number(target.id),rally_minutes:Number(rallyMinutes)}:{})};
                Promise.resolve().then(()=>kind==='territory-rally'?submittedEncounter.onTerritoryRally({troops:rememberTroops,rally_minutes:Number(rallyMinutes),objective:$('#march-territory-objective')?.value}):action(path,payload,['rally','monster-rally','shrine-rally','congress-rally'].includes(kind)?'Deine Rally wurde gestartet.':kind==='rally-join'?tr('rally.join.sent','Your troops are on their way to the rally leader.'):kind==='charms'?'Deine Truppen sammeln den Charm ein.':'Deine Truppen ziehen los!')).then(result=>{
                    if(result){try{localStorage.setItem(rememberKey,JSON.stringify(rememberTroops));}catch{}if(kind==='rally-join')submittedEncounter.rally_already_joined=true;}
                    if(result&&['rally','monster-rally','shrine-rally','congress-rally','territory-rally','rally-join'].includes(kind))window.dispatchEvent(new CustomEvent('conquer-rally-updated'));
                }).catch(error=>toast(error?.message||tr('rally.join.send_failed','The march could not be sent. Please try again.'))).finally(()=>{submittedEncounter.submitting=false;if(encounter===submittedEncounter)update();});
            }
            return true;
        }
        return {open,update,updateRallies,onClick};
    };
})();

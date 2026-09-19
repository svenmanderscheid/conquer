/* Weekly cooperative dungeons. Server state remains authoritative for troops, timers and rewards. */
window.ConquerDungeons=function(ctx){
    'use strict';
    const {api,esc,fmt,toast}=ctx,base=ctx.base||'';
    let data=null,error='',loading=null,loadingWorld=null,busy=false,timer=null,selected='overview',createCode=null,joinId=null,dataWorld=null;
    const drafts={};
    let forecastTimer=null,forecastVersion=0;
    const host=()=>document.querySelector('#content');
    const active=()=>Boolean(host()?.querySelector('.dungeon-shell'));
    const world=()=>Number(ctx.getState?.()?.city?.world_id||ctx.getState?.()?.world_id||1);
    const mine=r=>r.members.some(m=>Number(m.player_id)===Number(data.player_id));
    const ownActive=()=>data?.runs.find(r=>mine(r)&&['recruiting','running','decision'].includes(r.status));
    const uuid=()=>crypto.randomUUID?.()||'dungeon_'+Date.now()+'_'+Math.random().toString(36).slice(2);
    const label={attack:'Angreifer',defense:'Verteidiger',gather:'Sammler',hunter:'Jäger',cautious:'Vorsichtig',balanced:'Ausgewogen',risky:'Waghalsig',normal:'Normal',hard:'Schwer',recruiting:'Mitglieder gesucht',running:'Unterwegs',decision:'Entscheidung offen',completed:'Abgeschlossen',failed:'Gescheitert',cancelled:'Aufgelöst'};
    const roleBenefit={attack:'Mehr Schaden',defense:'Mehr Schutz',gather:'Mehr Beute',hunter:'Kürzere Reise'};
    const roleArt={attack:'attack.svg',defense:'defense.svg',gather:'gathering.svg',hunter:'hunters-bow.png'};
    const treasureArt={60100105:'treasures/shovel.png',60200001:'treasures/kite-shield.png',60200002:'treasures/long-bow.png',60400002:'compass.svg',60400102:'treasures/crystal-flask.png',60400108:'treasures/heroic-spirit.png'};
    const dungeonArtCodes=['ember_vault','frost_hollow','thorn_maze','sunken_temple','storm_spire','shadow_crypt'];
    const art=v=>`${base}/assets/art/dungeons/${dungeonArtCodes.includes(String(v))?String(v):'thorn_maze'}.webp`;
    const itemArt=v=>`${base}/assets/art/items/${treasureArt[Number(v)]||'compass.svg'}`;
    const roleIcon=r=>`<span class="dungeon-role-icon role-${esc(r)}" role="img" aria-label="${esc(label[r]||'Rolle')}" title="${esc((label[r]||'Rolle')+' · '+(roleBenefit[r]||''))}"><img src="${base}/assets/art/items/${roleArt[r]||'shield.svg'}" alt="" loading="lazy"></span>`;
    const button=(text,action,extra='',kind='secondary')=>`<button type="button" class="button ${kind}" data-action="dungeon-${action}" ${extra}>${esc(text)}</button>`;
    const stamp=v=>{const s=String(v||'');return /(?:Z|[+-]\d\d:?\d\d)$/.test(s)?s:s.replace(' ','T')+'Z';};
    const when=v=>v?new Date(stamp(v)).toLocaleString('de-DE',{day:'2-digit',month:'2-digit',hour:'2-digit',minute:'2-digit'}):'–';
    const remaining=v=>{const s=Math.max(0,Math.ceil((Date.parse(stamp(v))-Date.now())/1000));return s>3600?`${Math.ceil(s/3600)} Std.`:s>60?`${Math.ceil(s/60)} Min.`:`${s} Sek.`;};
    const formKey=f=>(f.dataset.world||world())+':'+f.dataset.form+':'+(f.dataset.id||'');
    function remember(){host()?.querySelectorAll('form[data-form^="dungeon-"]').forEach(f=>{if(f.dataset.dungeonSubmitted)return;const values={};for(const [k,v] of new FormData(f))values[k]=v;drafts[formKey(f)]={values,requestId:f.dataset.requestId};});}
    function restore(){host()?.querySelectorAll('form[data-form^="dungeon-"]').forEach(f=>{const d=drafts[formKey(f)];if(!d)return;for(const [k,v]of Object.entries(d.values)){const e=f.elements.namedItem(k);if(e)e.value=v;}if(d.requestId)f.dataset.requestId=d.requestId;});}
    async function load(force=false){
        if(loading){const pendingWorld=loadingWorld;await loading;if(pendingWorld!==world())return load(true);return data;}
        if(!force&&data&&dataWorld===world()&&Date.now()-data.loadedAt<8000){schedule();return data;}
        const requested=world();loadingWorld=requested;
        loading=(async()=>{try{
            const next=await api('dungeons/state');
            if(requested!==world()||Number(next.world_id)!==requested)return;
            data={...next,loadedAt:Date.now()};dataWorld=requested;error='';
            if(active()&&!busy)draw();
        }catch(e){if(requested===world()){error=e.message;if(active()&&!busy)draw();}}
        finally{loading=null;schedule();}})();
        return loading;
    }
    function schedule(){clearTimeout(timer);timer=setTimeout(()=>{if(active())load(true);},10000);}
    function render(tab){
        if(tab!=='dungeons')return false;
        const changedWorld=dataWorld!==world();if(changedWorld){data=null;error='';createCode=null;joinId=null;dataWorld=world();}
        if(changedWorld||!busy)draw();load();return true;
    }
    function draw(){
        const h=host();if(!h)return;remember();
        const scroll=h.scrollTop,focused=document.activeElement;
        const focusForm=focused?.closest('form[data-form]');
        const focus=focusForm&&focused.name?{form:formKey(focusForm),name:focused.name,value:focused.value}:null;
        const openDetails=[...h.querySelectorAll('details[open][data-detail]')].map(d=>d.dataset.detail);
        h.innerHTML=`<section class="dungeon-shell" aria-busy="${!data||busy}"><header class="dungeon-heading"><div><span class="dungeon-kicker">Gemeinsame Expedition</span><h2>Wöchentliche Dungeons</h2></div>${button('Aktualisieren','reload')}</header><nav class="dungeon-tabs" aria-label="Dungeonbereiche">${[['overview','Diese Woche'],['parties','Gruppen'],['reports','Berichte']].map(([k,n])=>`<button type="button" data-action="dungeon-tab" data-id="${k}" class="${selected===k?'active':''}" aria-pressed="${selected===k}">${n}</button>`).join('')}</nav>${error?`<p class="dungeon-error" role="alert">${esc(error)}</p>`:''}<div class="dungeon-body">${data?body():'<p class="dungeon-empty">Die Dungeonkarte wird geladen …</p>'}</div></section>`;
        restore();
        h.querySelectorAll('.dungeon-planner form').forEach(f=>{f.addEventListener('input',()=>syncPlanner(f));syncPlanner(f);});
        for(const d of h.querySelectorAll('details[data-detail]'))d.open=openDetails.includes(d.dataset.detail);
        if(focus)for(const f of h.querySelectorAll('form[data-form]'))if(formKey(f)===focus.form)[...f.elements].find(e=>e.name===focus.name&&(e.type!=='radio'||e.value===focus.value))?.focus({preventScroll:true});
        h.scrollTop=scroll;
    }
    function body(){const chosen=data.rotation.find(d=>d.dungeon_code===createCode);if(chosen&&!ownActive())return planner(chosen);const joining=data.runs.find(r=>Number(r.id)===joinId);if(joining&&joining.status==='recruiting'&&!ownActive()&&joining.members.length<4)return planner(joining.dungeon,joining);if(selected==='reports')return reports();if(selected==='parties')return parties();return overview();}
    function overview(){
        const own=ownActive();
        return `<section><div class="dungeon-intro"><div class="dungeon-intro-copy"><span class="dungeon-map-mark" aria-hidden="true">✦</span><p><strong>Gemeinsam mit 2–4 Spielern</strong><span class="dungeon-intro-detail"><br>Klassen frei kombinierbar. Mit allen vier Klassen vereint ihr mehr Schaden, Schutz und Beute sowie eine kürzere Reise.</span></p></div><div class="dungeon-intro-side"><small>Wochenwechsel<br><strong>${when(data.rotation_ends_at)}</strong></small><div class="dungeon-actions">${button('Offene Gruppen','groups','','gold')}${own?button('Deine Gruppe','run',`data-id="${own.id}"`):''}</div></div></div><div class="dungeon-grid">${data.rotation.map(d=>dungeonCard(d)).join('')}</div></section><details class="dungeon-preview" data-detail="next-week"><summary>Nächste Woche ansehen</summary><div class="dungeon-grid">${data.next_rotation.map(d=>dungeonCard(d,true)).join('')}</div></details>`;
    }
    function guidanceCard(g,difficulty='normal',stance='cautious',compact=false){
        if(!g)return '';
        const side=stance==='cautious'?'skip':'explore',count=Number(g.requirements?.[difficulty]?.[side]);
        if(!count)return '';
        const names={1:'Infanterie',2:'Fernkämpfer',3:'Kavallerie'};
        return `<section class="dungeon-guidance ${compact?'is-compact':''}" aria-label="Empfohlene Gruppenarmee"><header><span>Empfohlen · gesamte Gruppe</span><strong>≈ ${fmt(count)} T1</strong></header><div class="dungeon-formation">${g.mix.map(m=>`<div title="${names[m.type]}"><img src="${base}/assets/art/${['knight','archer','rider'][Number(m.type)-1]||'knight'}.png" alt="${names[m.type]||'Truppen'}"><span><strong>${fmt(m.percent)} %</strong><small>≈ ${fmt(Math.round(count*m.percent/100))}</small></span></div>`).join('')}</div><small>${label[difficulty]} · ${side==='explore'?'mit':'ohne'} Seitenkammer</small>${compact?'':`<details class="dungeon-guidance-help"><summary>ⓘ ${esc(g.profile_name)}</summary><p>${esc(g.reason)}</p><p>${esc(g.basis)} Höhere Truppenstufen und Boni können die nötige Anzahl senken. Die Empfehlung ist keine Erfolgsgarantie.</p></details>`}</section>`;
    }
    function plannerContext(form){
        const party=form.dataset.form==='dungeon-join'?data.runs.find(p=>Number(p.id)===Number(form.dataset.id)):null;
        const definition=party?.dungeon||data.rotation.find(d=>d.dungeon_code===form.dataset.id);
        const fields=new FormData(form);
        return {party,definition,difficulty:party?.difficulty||String(fields.get('difficulty')||'normal'),stance:party?.stance||String(fields.get('stance')||'balanced')};
    }
    function forecastCard(f){
        if(!f)return '';
        const state=['ready','risky','missing_members'].includes(f.status)?f.status:'risky';
        return `<div class="dungeon-forecast is-${state}"><span aria-hidden="true">${state==='ready'?'✓':state==='missing_members'?'⚑':'!'}</span><div><strong>${esc(f.label)}</strong><small>${fmt(f.total_troops)} Truppen · ${f.includes_side_room?'mit':'ohne'} Seitenkammer · Schätzung</small></div></div>`;
    }
    function paintForecast(form,output,forecast){
        if(!forecast)return;
        output.innerHTML=forecastCard(forecast);
        form.querySelector('[data-dungeon-validation]').textContent=forecast.label+' · Gruppe';
        form.querySelector('.dungeon-plan-footer').dataset.forecastState=forecast.status;
    }
    function groupRequirement(p){
        const count=p.dungeon?.guidance?.requirements?.[p.difficulty]?.[p.stance==='cautious'?'skip':'explore'];
        return count?`<div class="dungeon-group-requirement">⚑ Richtwert: <strong>≈ ${fmt(count)} T1</strong> für die gesamte Gruppe</div>`:'';
    }
    function updateForecast(form,valid){
        clearTimeout(forecastTimer);const version=++forecastVersion;
        delete form.querySelector('.dungeon-plan-footer').dataset.forecastState;
        const context=plannerContext(form),guidance=form.querySelector('[data-dungeon-guidance]'),output=form.querySelector('[data-dungeon-forecast]');
        if(!guidance||!output||!context.definition?.guidance)return;
        const mode=context.difficulty+':'+context.stance;
        if(guidance.dataset.mode!==mode){const open=guidance.querySelector('details')?.open;guidance.innerHTML=guidanceCard(context.definition.guidance,context.difficulty,context.stance);guidance.dataset.mode=mode;if(open)guidance.querySelector('details').open=true;}
        output.innerHTML=valid?'<small>Gruppenstärke wird geprüft …</small>':'<small>Ab 10 ausgewählten Truppen: Prognose für eure Gruppe.</small>';
        if(!valid||busy)return;
        const fields=new FormData(form),requested=world();
        const payload={action:'preview',dungeon_code:context.definition.dungeon_code,role:String(fields.get('role')),difficulty:context.difficulty,stance:context.stance,expected_world_id:requested,troops:Object.fromEntries([...fields].filter(([k,v])=>k.startsWith('troop_')&&Number(v)>0).map(([k,v])=>[k.slice(6),Number(v)]))};
        if(context.party)payload.run_id=Number(context.party.id);
        forecastTimer=setTimeout(async()=>{
            if(!form.isConnected||requested!==world()||busy)return;
            try{const result=await api('dungeons/action',payload);if(version===forecastVersion&&form.isConnected&&requested===world()&&!busy)paintForecast(form,output,result.forecast);}
            catch(e){if(version===forecastVersion&&form.isConnected&&requested===world()&&!busy)output.innerHTML=`<small>Prognose nicht verfügbar: ${esc(e.message)}</small>`;}
        },350);
    }
    function planner(d,party=null){
        const kind=party?'join':'create',id=party?party.id:d.dungeon_code;
        const absentRoles=['attack','defense','gather','hunter'].filter(r=>!party?.members.some(m=>m.role===r));
        const preferred=(party?absentRoles:[]).find(r=>data.eligible_roles.includes(r))||data.eligible_roles[0];
        return `<section class="dungeon-planner"><div class="dungeon-planner-top">${button('‹ Zurück','planning-close')}<span>${party?'Gruppe beitreten':'Gruppe gründen'}</span></div><header class="dungeon-plan-target"><img src="${art(d.dungeon_code)}" alt=""><div><h3>${esc(d.name)}</h3><small>◷ ${fmt(Math.round(d.base_duration/60))} Min. + Entscheidung${party?' · '+label[party.difficulty]:''}</small></div></header><form data-world="${world()}" data-form="dungeon-${kind}" data-id="${esc(String(id))}"><div class="dungeon-plan-columns"><section class="dungeon-plan-roles"><h4><b>1</b> Deine Rolle</h4><div class="dungeon-role-options">${['attack','defense','gather','hunter'].map(r=>{const eligible=data.eligible_roles.includes(r);return `<label class="dungeon-role-option role-${r} ${eligible?'':'is-locked'}"><input type="radio" name="role" value="${r}" ${r===preferred?'checked':''} ${eligible?'':'disabled'} required>${roleIcon(r)}<strong>${label[r]}</strong><small>${!eligible?'Talent fehlt':roleBenefit[r]}</small><span class="dungeon-choice-check" aria-hidden="true">${eligible?'✓':'🔒'}</span></label>`;}).join('')}</div>${party?`<div class="dungeon-plan-team"><small>Schon dabei · ${party.members.length}/4</small><div>${party.members.map(m=>`<span title="${esc(label[m.role])}">${roleIcon(m.role)}<strong>${esc(m.username)}</strong></span>`).join('')}</div></div>`:`<fieldset class="dungeon-difficulty"><legend>Schwierigkeit</legend>${['normal','hard'].map((v,i)=>`<label><input type="radio" name="difficulty" value="${v}" ${i===0?'checked':''}><span aria-hidden="true">${i?'⚔':'◇'}</span> ${label[v]}</label>`).join('')}</fieldset><details class="dungeon-plan-help" data-detail="tactics-${esc(String(id))}"><summary>Taktik einstellen</summary><label>Bei ausbleibender Abstimmung<select name="stance"><option value="cautious">Vorsichtig · Seitenkammer auslassen</option><option value="balanced" selected>Ausgewogen · Seitenkammer erkunden</option><option value="risky">Waghalsig · Seitenkammer erkunden</option></select></label></details>`}<details class="dungeon-plan-help" data-detail="rules-${kind}-${esc(String(id))}"><summary>ⓘ Rollen & Ablauf</summary><p>Ihr könnt mit 2–4 Spielern in jeder Klassenkombination starten, auch mit gleichen Klassen. Angreifer erhöhen den Schaden, Verteidiger den Schutz, Sammler die Beute und Jäger verkürzen die Reise. Mit allen vier Klassen nutzt ihr alle Vorteile. Ein Talentpunkt schaltet die jeweilige Rolle frei.</p><p>Deine Truppen und Talente bleiben bis zur Rückkehr gebunden. Die Expedition läuft auch offline. Eine optionale Entscheidung dauert 30 Minuten.</p>${!party?'<p>Schwer: stärkere Gegner und mehr Beute. Die Haltung legt fest, ob ihr bei Gleichstand oder fehlenden Stimmen die Seitenkammer erkundet.</p>':''}</details></section><section class="dungeon-plan-army"><div data-dungeon-guidance></div><h4><b>2</b> Deine Truppen <small>10–50.000</small></h4><div class="dungeon-presets" aria-label="Truppen schnell auswählen">${[['10','10'],['100','100'],['max','Max'],['clear','Leeren']].map(([v,n])=>button(n,'troops-preset',`data-value="${v}"`)).join('')}</div>${troopInputs()}<div data-dungeon-forecast aria-live="polite"></div></section></div><footer class="dungeon-plan-footer"><div class="dungeon-plan-total"><span aria-hidden="true">⚑</span><div><strong><span data-dungeon-total>0</span> Truppen</strong><small data-dungeon-validation>Mindestens 10 auswählen</small></div></div><button class="button gold" type="submit" disabled>${party?'Beitreten':'Gruppe gründen'} <span aria-hidden="true">→</span></button></footer><p class="dungeon-form-error" role="alert"></p></form></section>`;
    }
    function syncPlanner(form){
        const inputs=[...form.querySelectorAll('input[name^="troop_"]')],total=inputs.reduce((sum,e)=>sum+(Number(e.value)||0),0);
        const bad=inputs.some(e=>!e.validity.valid||!Number.isInteger(Number(e.value)));
        const role=form.querySelector('[name="role"]:checked:not(:disabled)');
        const reason=!role?'Zuerst einen Talentpunkt verteilen':bad?'Truppenmenge prüfen':total<10?'Mindestens 10 auswählen':total>50000?'Maximal 50.000 Truppen':label[role.value]+' · Teilnahme möglich';
        form.querySelector('[data-dungeon-total]').textContent=fmt(total);
        const note=form.querySelector('[data-dungeon-validation]');if(note.textContent!==reason)note.textContent=reason;
        form.querySelector('[type="submit"]').disabled=busy||!role||bad||total<10||total>50000;
        inputs.forEach(e=>e.closest('.dungeon-troop-row').classList.toggle('is-selected',Number(e.value)>0));
        updateForecast(form,!!role&&!bad&&total>=10&&total<=50000);
        return !!role&&!bad&&total>=10&&total<=50000;
    }
    function dungeonCard(d,preview=false){
        const own=ownActive(),minutes=Math.max(1,Math.round(Number(d.base_duration)/60));
        const unavailable=!data.eligible_roles.length;
        return `<article class="dungeon-card ${preview?'preview':''}"><div class="dungeon-card-art"><img src="${art(d.dungeon_code)}" alt="" loading="lazy"><span class="dungeon-duration" aria-label="${fmt(minutes)} Minuten Grundreise">◷ ${fmt(minutes)} Min.</span><span class="dungeon-tag">${esc(d.theme)}</span></div><div class="dungeon-card-copy"><h3>${esc(d.name)}</h3><div class="dungeon-reward"><img src="${itemArt(d.treasure_code)}" alt="" loading="lazy"><span><small>Reliktfragmente</small><strong>${esc(d.treasure_name||'Relikt')}</strong></span></div>${guidanceCard(d.guidance,'normal','cautious',true)}<div class="dungeon-role-strip" aria-label="Optionale Klassenboni">${['attack','defense','gather','hunter'].map(roleIcon).join('')}</div><small class="dungeon-card-note">Klassen frei kombinierbar</small><small class="dungeon-card-note">30 Min. Entscheidung · Chance auf ${esc(d.item_names?.join(' oder ')||'Gegenstände')}</small>${preview?'':own?button('Deine laufende Gruppe','run',`data-id="${own.id}"`):button('Gruppe gründen','create-open',`data-id="${esc(d.dungeon_code)}" ${unavailable?'disabled':''}`,'gold')}${!preview&&unavailable?'<small>Verteile zuerst einen Talentpunkt im Bereich Angriff, Verteidigung, Sammler oder Jäger.</small>':''}</div></article>`;
    }
    function parties(){
        const rows=data.runs.filter(r=>['recruiting','running','decision'].includes(r.status)).sort((a,b)=>Number(mine(b))-Number(mine(a))||Number(b.status==='recruiting')-Number(a.status==='recruiting'));
        return `<div class="dungeon-group-toolbar"><strong>${rows.filter(r=>r.status==='recruiting').length} offene Gruppen</strong>${button('+ Gruppe gründen','browse')}</div><div class="dungeon-party-list">${rows.map(p=>partyCard(p)).join('')||'<div class="dungeon-empty"><span aria-hidden="true">⚑</span><p>Noch keine Gruppe. Starte die erste!</p></div>'}</div>`;
    }
    function partyCard(p){
        const members=p.members,isMine=mine(p),classCount=new Set(members.map(m=>m.role)).size;
        const slots=[...members.map(m=>({member:m})),...Array.from({length:Math.max(0,4-members.length)},()=>({}))];
        const open=p.status==='recruiting',canJoin=open&&!isMine&&!ownActive()&&members.length<4&&data.eligible_roles.length;
        return `<article class="dungeon-party dungeon-group-card ${isMine?'is-own':''}" data-party="${p.id}"><header class="dungeon-group-heading"><img src="${art(p.dungeon.dungeon_code||p.dungeon_code)}" alt=""><div><small>${isMine?'Deine Gruppe · ':''}${label[p.status]}</small><h3>${esc(p.dungeon.name)}</h3><span>${label[p.difficulty]} · ${label[p.stance]}</span></div><strong>${members.length}/4</strong></header><div class="dungeon-members">${slots.map(({member:m})=>m?`<div class="dungeon-member role-${esc(m.role)}" title="${esc(label[m.role])}">${roleIcon(m.role)}<strong>${esc(m.username)}${Number(m.player_id)===Number(p.leader_player_id)?' ♛':''}</strong><small>${fmt(m.total_troops)}</small></div>`:`<div class="dungeon-member is-empty"><span class="dungeon-free-icon" aria-hidden="true">＋</span><strong>Frei</strong><small>${open?'Jede Klasse':'Unbesetzt'}</small></div>`).join('')}</div>${isMine?vote(p)+progress(p):''}${open?groupRequirement(p):''}${open&&p.army_forecast?forecastCard(p.army_forecast):''}<div class="dungeon-group-bottom"><span class="dungeon-group-status">${open?(members.length<2?'Weiterer Spieler benötigt':'✓ '+classCount+'/4 Klassenboni'):p.finishes_at?'◷ '+remaining(p.finishes_at):label[p.status]}</span>${canJoin?button('Beitreten →','join-open',`data-id="${p.id}"`,'gold'):isMine?actions(p,true,members.length>=4):'<small>'+(!open?'Läuft bereits':members.length>=4?'Gruppe voll':ownActive()?'Bereits in einer Gruppe':'Talentpunkt benötigt')+'</small>'}</div>${isMine&&p.readiness&&!p.army_forecast?`<details class="dungeon-plan-help" data-detail="readiness-${p.id}"><summary>ⓘ Gruppeneinschätzung</summary><p>${esc(p.readiness)} · ohne zusätzliche Seitenkammer</p></details>`:''}</article>`;
    }
    function troopInputs(){
        const defs=ctx.getState?.()?.troop_defs||[];
        const available=Object.entries(data.available_troops).filter(([,n])=>Number(n)>0);
        return `<div class="dungeon-troops">${available.map(([code,count])=>{const def=defs.find(t=>Number(t.code)===Number(code)),name=(def&&ctx.unitName?.(def))||def?.name||'Truppe '+code,image=['knight','archer','rider'][Number(def?.type)-1]||'knight';return `<label class="dungeon-troop-row"><img src="${base}/assets/art/${image}.png" alt="" loading="lazy"><span><strong>${esc(name)}</strong><small>${fmt(count)} verfügbar${def?.tier?' · T'+Number(def.tier):''}</small></span><input type="number" inputmode="numeric" name="troop_${Number(code)}" aria-label="Anzahl ${esc(name)}" min="0" max="${Math.min(50000,Number(count))}" step="1" value="0"></label>`;}).join('')||'<p class="dungeon-empty">Keine freien Truppen. Bilde zuerst Einheiten aus.</p>'}</div>`;
    }
    function actions(p){
        if(p.status!=='recruiting')return '';
        return `<div class="dungeon-actions">${p.can_start?button('Starten →','start',`data-id="${p.id}"`,'gold'):''}${p.can_cancel?button('Auflösen','cancel',`data-id="${p.id}"`,'danger'):p.can_leave?button('Verlassen','leave',`data-id="${p.id}"`):''}</div>`;
    }
    function vote(p){if(!p.can_vote)return '';const own=p.votes.find(v=>Number(v.player_id)===Number(data.player_id))?.choice;return `<section class="dungeon-vote"><h4>Der Weg teilt sich</h4><p>Seitenkammer erkunden: zusätzlicher Kampf, 25 % mehr Reisezeit und bessere Beute. Entscheidung in ${remaining(p.decision_deadline)}</p><div class="dungeon-actions">${button(own==='explore'?'Erkunden ✓':'Erkunden','vote',`data-id="${p.id}" data-choice="explore"`,'gold')}${button(own==='skip'?'Überspringen ✓':'Überspringen','vote',`data-id="${p.id}" data-choice="skip"`)}</div><small>Bei Gleichstand oder Enthaltung: ${p.stance==='cautious'?'Überspringen':'Erkunden'}. Deine Stimme kann bis zum Fristende geändert werden.</small></section>`;}
    function progress(p){if(!['running','decision'].includes(p.status))return '';const done=Number(p.progress.encounters_done)||0,total=Math.max(1,Number(p.progress.encounters_total)||1),n=Math.round(100*done/total);return `<div class="dungeon-progress"><div><span>${fmt(done)} von ${fmt(total)} Begegnungen</span><strong>${n} %</strong></div><div class="dungeon-journey" role="progressbar" aria-label="Expeditionsfortschritt" aria-valuemin="0" aria-valuemax="${total}" aria-valuenow="${done}">${Array.from({length:total},(_,i)=>`<span class="${i<done?'is-done':i===done?'is-current':''}"><i>${i<done?'✓':i+1}</i></span>`).join('')}</div><small>${p.finishes_at?'Voraussichtlich noch '+remaining(p.finishes_at):p.status==='decision'?'Entscheidung noch '+remaining(p.decision_deadline):'Erste Begegnung in '+remaining(p.decision_at)}</small></div>`;}
    function rewardText(r){if(!r.reward)return '<span class="dungeon-loot-item is-pending">Belohnung wird vorbereitet</span>';const x=r.reward,out=[];if(x.fragments)out.push(`<span class="dungeon-loot-item"><img src="${itemArt(x.treasure_code||r.dungeon?.treasure_code)}" alt=""><span><small>Reliktfragmente</small><strong>${fmt(x.fragments)} × ${esc(x.treasure_name||'Relikt')}</strong></span></span>`);if(x.item_quantity)out.push(`<span class="dungeon-loot-item"><span class="dungeon-loot-glyph" aria-hidden="true">✦</span><span><small>Gegenstand</small><strong>${fmt(x.item_quantity)} × ${esc(x.item_name||'Gegenstand')}</strong></span></span>`);return out.join('')||'<span class="dungeon-loot-item">Keine Beute</span>';}
    function battle(r){return `<details class="dungeon-battle" data-detail="battle-${r.id}"><summary>Kampfbericht (${fmt(r.battle_log.length)} Runden)</summary><div class="dungeon-battle-scroll"><table><thead><tr><th>Begegnung</th><th>Runde</th><th>Schaden</th><th>Erlitten</th><th>Gruppe HP</th><th>Gegner HP</th></tr></thead><tbody>${r.battle_log.map(x=>`<tr><td>${esc(x.name||x.encounter)}</td><td>${fmt(x.round)}</td><td>${fmt(x.damage_dealt)}</td><td>${fmt(x.damage_taken)}</td><td>${fmt(x.party_hp)}</td><td>${fmt(x.enemy_hp)}</td></tr>`).join('')}</tbody></table></div></details>`;}
    function reports(){const rows=data.runs.filter(r=>['completed','failed'].includes(r.status));return `<div class="dungeon-report-list">${rows.map(r=>`<article class="dungeon-report"><header class="dungeon-report-banner" style="--dungeon-art:url('${art(r.dungeon.dungeon_code||r.dungeon_code)}')"><div><span class="dungeon-tag">${label[r.status]}</span><h3>${esc(r.dungeon.name)}</h3><p>${r.status==='completed'?'Die Gruppe kehrte siegreich heim.':'Die Expedition ist gescheitert; deine Truppen sind zurück.'}</p></div><time>${when(r.completed_at)}</time></header><div class="dungeon-loot">${rewardText(r)}</div>${r.battle_log?.length?battle(r):''}<footer>${r.can_claim?button('Persönliche Beute abholen','claim',`data-id="${r.id}"`,'gold'):r.reward?'<small>✓ Beute abgeholt</small>':''}</footer></article>`).join('')||'<p class="dungeon-empty">Noch keine Expedition wurde abgeschlossen.</p>'}</div>`;}
    async function execute(payload,el){
        if(busy||!data||dataWorld!==world())return null;
        remember();busy=true;const requested=world();
        const controls=[...host().querySelectorAll('button,input,select')],enabled=controls.filter(x=>!x.disabled);
        enabled.forEach(x=>x.disabled=true);
        const request=el?.dataset.requestId||uuid();if(el)el.dataset.requestId=request;
        try{
            if(loading)await loading;if(requested!==world())return null;
            const result=await api('dungeons/action',{...payload,expected_world_id:requested,request_id:request});
            if(requested!==world())return result;
            if(el?.tagName==='FORM'){delete drafts[formKey(el)];el.dataset.dungeonSubmitted='true';}
            if(el)delete el.dataset.requestId;
            error='';if(result.state){data={...result.state,loadedAt:Date.now()};dataWorld=requested;}else{data=null;await load(true);}
            if(['create','join'].includes(payload.action)){selected='parties';createCode=null;joinId=null;}
            toast(result.message||'Dungeon aktualisiert.');
            if(ctx.refresh)await ctx.refresh(false);
            return result;
        }catch(e){if(requested===world()){error=e.message;toast(e.message);}return null;}
        finally{busy=false;enabled.forEach(x=>{if(x.isConnected)x.disabled=false;});if(active()){draw();if(!data||dataWorld!==world())load(true);}schedule();}
    }
    function onClick(action,target){
        if(!action?.startsWith('dungeon-'))return false;
        if(busy)return true;
        const a=action.slice(8);
        if(a==='troops-preset'){
            const form=target.closest('form'),inputs=[...form.querySelectorAll('input[name^="troop_"]')];
            const available=inputs.reduce((sum,e)=>sum+Number(e.max),0),limit=target.dataset.value==='clear'?0:target.dataset.value==='max'?50000:Number(target.dataset.value);
            const budget=Math.min(available,limit),shares=inputs.map(e=>({e,n:available?Math.floor(Number(e.max)*budget/available):0}));
            let left=budget-shares.reduce((sum,x)=>sum+x.n,0);
            for(const x of shares){if(left&&x.n<Number(x.e.max)){x.n++;left--;}x.e.value=x.n;}
            syncPlanner(form);remember();return true;
        }
        if(a==='planning-close'){remember();createCode=null;joinId=null;draw();host().scrollTop=0;return true;}
        if(a==='join-open'){remember();createCode=null;joinId=Number(target.dataset.id);draw();host().scrollTop=0;return true;}
        if(a==='browse'){remember();createCode=null;joinId=null;selected='overview';draw();host().scrollTop=0;return true;}
        if(a==='tab'){if(['overview','parties','reports'].includes(target.dataset.id)){remember();createCode=null;joinId=null;selected=target.dataset.id;draw();host().scrollTop=0;}return true;}
        if(a==='groups'){selected='parties';draw();host().scrollTop=0;return true;}
        if(a==='reload'){load(true);return true;}
        if(a==='create-open'){remember();joinId=null;createCode=target.dataset.id;draw();host().scrollTop=0;return true;}
        if(a==='create-close'){createCode=null;draw();return true;}
        if(a==='run'){selected='parties';draw();requestAnimationFrame(()=>host()?.querySelector(`[data-party="${Number(target.dataset.id)}"]`)?.scrollIntoView({block:'start'}));return true;}
        const id=Number(target.dataset.id);
        if(['start','leave','cancel','claim'].includes(a))execute({action:a,run_id:id},target);
        if(a==='vote')execute({action:'vote',run_id:id,choice:target.dataset.choice},target);
        return true;
    }
    function onSubmit(form){if(!form.dataset.form?.startsWith('dungeon-'))return false;if(busy)return true;if(!syncPlanner(form))return true;const f=new FormData(form),kind=form.dataset.form.slice(8),troops=Object.fromEntries([...f].filter(([k,v])=>k.startsWith('troop_')&&Number(v)>0).map(([k,v])=>[k.slice(6),Number(v)]));if(kind==='create')execute({action:'create',dungeon_code:String(form.dataset.id),role:String(f.get('role')),stance:String(f.get('stance')),difficulty:String(f.get('difficulty')),troops},form);if(kind==='join')execute({action:'join',run_id:Number(form.dataset.id),role:String(f.get('role')),troops},form);return true;}
    return {render,onClick,onSubmit,refresh:()=>load(true)};
};

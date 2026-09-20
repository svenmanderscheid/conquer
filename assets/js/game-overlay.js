/* Shared edge HUD. The scene remains interactive between the individual controls. */
window.ConquerBuildingOrder = Object.freeze({
    next(state) {
        const buildings = state?.buildings || {};
        const queued = new Set([...(state?.build_queue || []), ...(state?.plot_queue || [])].map(row => row?.building_code).filter(Boolean));
        const level = code => Number(buildings[code]?.level || 0);
        const readyForNextLevel = code => Object.entries(buildings[code]?.requirements || {}).every(([requiredCode, requiredLevel]) => level(requiredCode) >= Number(requiredLevel));
        const recommendStep = (code, targetLevel, visiting = new Set()) => {
            const building = buildings[code];
            if (!building || level(code) >= Math.min(30, Number(targetLevel) || 0) || visiting.has(code)) return null;
            const branch = new Set(visiting); branch.add(code);
            let blocked = false;
            for (const [requiredCode, requiredLevel] of Object.entries(building.requirements || {})) {
                if (level(requiredCode) >= Number(requiredLevel)) continue;
                const prerequisite = recommendStep(requiredCode, Number(requiredLevel), branch);
                if (prerequisite) return prerequisite;
                blocked = true;
            }
            return blocked || queued.has(code) ? null : code;
        };

        const castleLevel = level('castle');
        if (castleLevel < 30) {
            const nextCastleStep = recommendStep('castle', castleLevel + 1);
            if (nextCastleStep) return nextCastleStep;
        }

        const catchUpOrder = ['wall','farm','lumber_camp','quarry','gold_mine','storage','barrack','archery_range','stable','academy','hospital','watch_tower','trading_post','treasure_house','hall_of_alliance'];
        return catchUpOrder
            .filter(code => buildings[code] && level(code) < 30 && !queued.has(code) && readyForNextLevel(code))
            .sort((a, b) => level(a) - level(b) || catchUpOrder.indexOf(a) - catchUpOrder.indexOf(b))[0] || null;
    }
});

window.ConquerOverlay = function (ctx) {
    'use strict';
    const {getState, getKingdom, now, date, esc, fmt, openDialog, countdown} = ctx;
    const $ = id => document.getElementById(id);
    const clock = end => {
        const s = Math.max(0, Math.ceil((date(end) - now()) / 1000));
        return s >= 3600 ? `${Math.floor(s / 3600)}:${String(Math.floor(s % 3600 / 60)).padStart(2, '0')}:${String(s % 60).padStart(2, '0')}` : `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`;
    };
    function update() {
        const state = getState(), kingdom = getKingdom();
        if (!state) return;
        const profile = kingdom?.profile;
        const ap=Math.max(0,Number(profile?.action_points??0)),apMax=Math.max(1,Number(profile?.action_points_max||200));
        const energy=$('hud-energy'),energyTrack=energy.querySelector('[role="progressbar"]');
        energy.querySelector('strong').textContent = profile ? `${fmt(ap)} / ${fmt(apMax)}` : '… / …';
        energy.setAttribute('aria-label', profile ? `${fmt(ap)} von ${fmt(apMax)} Aktionspunkten. Profil öffnen` : 'Aktionspunkte werden geladen');
        energyTrack.setAttribute('aria-valuemax',String(apMax));energyTrack.setAttribute('aria-valuenow',String(ap));
        $('hud-energy-fill').style.width=`${Math.min(100,ap/apMax*100)}%`;
        $('hud-gems').querySelector('strong').textContent = profile ? fmt(profile.gems) : '…';
        const level = String(profile?.lord_level || state.lord?.level || 1);
        $('hud-player-level')?.remove();
        $('lord-hud-level').textContent=level;
        if ($('hud-power-value')) $('hud-power-value').textContent=fmt(profile?.power || state.city.power);
        $('account-button').title = `${profile?.display_name || state.player.name} · Hunter-Stufe ${level} · ${fmt(profile?.power || state.city.power)} Macht`;
        const hunter=$('lord-talent-button'),hunterTrack=hunter.querySelector('[role="progressbar"]'),hunterMax=Number(profile?.lord_level||1)>=Number(profile?.lord_max_level||60),hunterXp=Math.max(0,Number(profile?.lord_xp_into||0)),hunterNext=Math.max(1,Number(profile?.lord_xp_next||1));
        $('hud-hunter-xp').textContent=profile?(hunterMax?'Höchststufe':profile.lord_xp_next==null?'Fortschritt':`${fmt(hunterXp)} / ${fmt(hunterNext)} XP`):'… XP';
        hunterTrack.setAttribute('aria-valuemax',String(hunterMax?1:hunterNext));hunterTrack.setAttribute('aria-valuenow',String(hunterMax?1:hunterXp));
        $('hud-hunter-fill').style.width=`${hunterMax?100:Math.min(100,hunterXp/hunterNext*100)}%`;
        hunter.setAttribute('aria-label',profile?(hunterMax?`Hunter-Stufe ${level}, Höchststufe erreicht. Hunter-Talente öffnen`:`Hunter-Stufe ${level}, ${fmt(hunterXp)} von ${fmt(hunterNext)} Jagd-XP. Hunter-Talente öffnen`):'Hunter-Fortschritt wird geladen');
        const second=$('hud-build-second'),slots=Number(kingdom?.vip?.building_slots||state.vip?.building_slots||1),unlocked=slots>1;
        if(second){second.dataset.action=unlocked?'buildings':'vip-open';second.classList.toggle('is-locked',!unlocked);second.setAttribute('aria-label',unlocked?'Zweite Bauschleife öffnen':'Zweite Bauschleife wird mit VIP 4 freigeschaltet');}
        $('hud-march-status').textContent = `${state.marches?.length || 0} / ${(state.army_limits?.march_slots || 3)+(state.army_limits?.gather_march_slots || 0)}`;
        $('hud-marches')?.classList.toggle('has-activity',!!state.marches?.length);
        tick();
    }
    function job(id, row, {label, locked = false, loading = false}) {
        const button = $(id);
        if (!button) return;
        const end = row?.finishes_at ? date(row.finishes_at) : NaN;
        const start = row?.started_at ? date(row.started_at) : NaN;
        const timed = Number.isFinite(end);
        const status = row ? (timed && end <= now() ? 'finishing' : 'active') : locked ? 'locked' : loading ? 'loading' : 'idle';
        const stateText = {active:'Läuft',finishing:'Abschluss',locked:'Gesperrt',loading:'Lädt …',idle:'Bereit'}[status];
        const time = row ? (status === 'finishing' ? 'Wird bestätigt' : timed ? clock(row.finishes_at) : 'Zeit offen') : locked ? 'Ab VIP 4' : loading ? 'Bitte warten' : 'Auftrag starten';
        const progress = timed && Number.isFinite(start) && end > start ? Math.max(0,Math.min(1,(now()-start)/(end-start))) : null;
        button.dataset.jobState = status;
        button.classList.toggle('has-activity', Boolean(row));
        button.classList.toggle('is-locked', status === 'locked');
        button.querySelector('.hud-job-state').textContent = stateText;
        button.querySelector('.hud-job-time').textContent = time;
        const bar = button.querySelector('.hud-job-progress');
        bar.style.width = `${Math.round((progress ?? 0)*100)}%`;
        button.classList.toggle('is-indeterminate', Boolean(row) && progress === null);
        const definition = row?.research_code ? getState().research_defs?.find(n=>n.code===row.research_code) : null;
        const target = row?.building_code ? ctx.labels?.[row.building_code] || getState().buildings?.[row.building_code]?.name : definition ? window.ConquerResearch?.title?.(definition) || definition.name : '';
        const detail = row ? `${target ? target + '. ' : ''}${status === 'finishing' ? 'Abschluss wird vom Server bestätigt.' : timed ? 'Restzeit '+time+'.' : 'Restzeit derzeit nicht verfügbar.'}${progress !== null ? ' '+Math.round(progress*100)+' Prozent abgeschlossen.' : ''}` : time+'.';
        const description = `${label}: ${stateText}. ${detail} ${row ? 'Laufenden Ausbau öffnen.' : locked ? 'VIP öffnen.' : 'Empfohlenen Ausbau öffnen.'}`;
        button.title = description;
        button.setAttribute('aria-label', description);
    }
    function tick() {
        const state = getState();
        if (!state) return;
        const ordered = rows => (Array.isArray(rows) ? rows : []).filter(row=>!Number(row.is_processed)).sort((a,b)=>(date(a.finishes_at)||Infinity)-(date(b.finishes_at)||Infinity));
        const builds = ordered([...(state.build_queue||[]),...(state.plot_queue||[])]), research = ordered(state.research_queue);
        const slots = Number(getKingdom()?.vip?.building_slots || state.vip?.building_slots || 1);
        const hospital=getKingdom()?.hospital,healing=hospital?.active,healingButton=$('hud-healing');
        if(healingButton){
            healingButton.hidden=!hospital||Number(hospital.used||0)<=0;
            if(!healingButton.hidden){
                job('hud-healing',healing?{...healing,started_at:healing.started_at,finishes_at:healing.ends_at}:null,{label:'Heilung'});
                const count=Number(healing?.count||hospital.waiting||hospital.used||0),stateNode=healingButton.querySelector('.hud-job-state'),timeNode=healingButton.querySelector('.hud-job-time');
                healingButton.dataset.action=healing?'army-hospital':'hospital-quick-heal';
                healingButton.classList.toggle('is-quick-heal',!healing);
                if(healing){stateNode.textContent='In Behandlung';healingButton.setAttribute('aria-label',`${fmt(count)} Truppen in Behandlung. Restzeit ${timeNode.textContent}. Hospital öffnen und Heilung beschleunigen.`);}
                else{stateNode.textContent='Antippen & heilen';timeNode.textContent=`${fmt(count)} verwundet`;healingButton.setAttribute('aria-label',`${fmt(count)} verwundete Truppen. Antippen, um sofort alle zu heilen.`);}
                healingButton.title=healingButton.getAttribute('aria-label');
            }
        }
        job('hud-build', builds[0], {label:'Bauen I', loading:!Array.isArray(state.build_queue)});
        job('hud-build-second', builds[1], {label:'Bauen II', locked:slots<2, loading:!Array.isArray(state.build_queue)});
        if ($('hud-build-second')) $('hud-build-second').dataset.action = slots>1 || builds[1] ? 'buildings' : 'vip-open';
        job('hud-research', research[0], {label:'Forschung', loading:!Array.isArray(state.research_queue)});
        marchActivity();
    }
    function marchActivity(){
        const button=$('hud-marches');if(!button)return;
        let list=$('hud-march-activity');
        // The map's interactive list replaces this older summary when available.
        if(window.ConquerMarchHud){list?.remove();return;}
        if(!list){list=document.createElement('div');list.id='hud-march-activity';list.dataset.worldOnly='';list.setAttribute('role','group');list.setAttribute('aria-label','Deine aktiven Märsche');button.after(list);}
        const rows=getState()?.marches||[];list.hidden=!rows.length;
        const signature=JSON.stringify(rows.map(m=>[m.id,m.state,m.march_type,m.target_x,m.target_y]));
        if(list.dataset.signature!==signature){
            list.dataset.signature=signature;
            list.innerHTML=rows.map(m=>`<button type="button" data-action="hud-marches" data-march="${esc(m.id)}"><span class="hud-march-phase"></span><strong class="hud-march-time"></strong><small>X ${Number(m.target_x)} · Y ${Number(m.target_y)}</small></button>`).join('');
        }
        rows.forEach((m,i)=>{
            const item=list.children[i],phase=m.state==='returning'?'Rückkehr':m.state==='arrived'&&Number(m.march_type)===9?'Sammelt':m.state==='gathering'?'Rally sammelt':m.state==='resolving'?'Am Ziel':Number(m.march_type)===15?'Feldangriff':'Unterwegs';
            const end=m.state==='returning'?m.return_time:m.state==='arrived'?m.gathering_finishes_at:m.arrival_time;
            const time=end&&Number.isFinite(date(end))?(date(end)<=now()?'Wird bestätigt':clock(end)):'Am Ziel';
            item.querySelector('.hud-march-phase').textContent=phase;item.querySelector('.hud-march-time').textContent=time;
            item.dataset.phase=m.state==='arrived'?'gathering':m.state;
            item.setAttribute('aria-label',`${phase}, ${time}, X ${Number(m.target_x)}, Y ${Number(m.target_y)}. Märsche öffnen.`);
        });
        if(button.getClientRects().length){
            const box=button.getBoundingClientRect(),chat=document.querySelector('#world-chat')?.getBoundingClientRect();
            const landscape=matchMedia('(max-height:500px) and (orientation:landscape)').matches,top=landscape?box.top:box.bottom+6;
            list.style.left=`${landscape?box.right+8:box.left}px`;list.style.top=`${top}px`;
            list.style.maxHeight=`${Math.max(44,Math.min(innerHeight-70,chat?.top||innerHeight-70)-top-8)}px`;
        }
    }
    function marches() {
        const state = getState(), rows = state?.marches || [];
        const types = {5:'Monsterangriff',6:'Kristall',7:'Angriff',8:'Spähtrupp',9:'Sammelzug',10:'Verstärkung',15:'Feldangriff',attack:'Angriff',gather:'Sammelzug',scout:'Spähtrupp',rally:'Rally'};
        openDialog(`<h2>Deine Truppen unterwegs</h2><p class="muted">${rows.length} von ${fmt(state.army_limits?.march_slots || 3)} Marschplätzen belegt.</p>${rows.length ? rows.map(m => `<section class="panel"><h3>${esc(types[m.march_type] || 'Truppenmarsch')} · ${Number(m.target_x)} / ${Number(m.target_y)}</h3><p>${m.state === 'returning' ? 'Rückkehr' : m.state === 'resolving' ? 'Aktion wird abgeschlossen' : m.state === 'arrived' && Number(m.march_type)===9 ? 'Sammeln bis' : m.state==='gathering' ? 'Rally-Start' : 'Ankunft'}${m.state !== 'resolving' ? ': ' + countdown(m.state === 'returning' ? m.return_time : m.gathering_finishes_at || m.arrival_time) : ''}</p>${Number(m.march_type)===9&&['marching','arrived'].includes(m.state)?`<button type="button" class="button secondary" data-action="gather-recall" data-id="${Number(m.id)}">Sammler zurückrufen</button>`:''}</section>`).join('') : '<p class="notice">Deine Truppen sind in der Stadt. Wähle ein Ziel auf der Weltkarte, um einen Marsch vorzubereiten.</p>'}<button class="button gold wide" data-action="dialog-tab" data-id="army">Truppenübersicht öffnen</button>`);
    }
    function onClick(action) {if (action !== 'hud-marches') return false; marches(); return true;}
    const timer = setInterval(() => {if (!document.hidden) tick();}, 1000);
    return {update, onClick, destroy() {clearInterval(timer);}};
};

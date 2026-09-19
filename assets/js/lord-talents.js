/* Lord talent plans are local until one versioned, atomic apply succeeds. */
window.ConquerTalents = function(ctx) {
    'use strict';
    const {api,base,esc,fmt,getState,refresh,toast}=ctx;
    let state=null,plan={},branch='attack',selected='attack_0',generation=0,pending=false,retry=null,sheet=null,loading=null;
    const host=()=>document.querySelector('#content');
    const visible=()=>document.querySelector('#panel-dialog')?.dataset.panel==='mastery';
    const world=()=>Number(getState()?.world?.id||getState()?.world?.world_id||state?.world_id);
    const nodes=()=>state?.branches.flatMap(b=>b.nodes)||[];
    const rank=n=>Number(plan[n.code]||0);
    const spent=()=>Object.values(plan).reduce((a,n)=>a+Number(n),0);
    const canonical=p=>JSON.stringify(Object.fromEntries(Object.entries(p).filter(([,v])=>v>0).sort()));
    const dirty=()=>canonical(plan)!==canonical(state.ranks);
    const pct=v=>(v>0?'+':'')+(v*100).toLocaleString('de-DE',{maximumFractionDigits:1})+' %';
    const button=(label,action,id='',disabled=false,cls='')=>`<button type="button" class="talent-button ${cls}" data-action="talent-${action}" data-id="${esc(id)}" ${disabled?'disabled':''}>${label}</button>`;
    const art={attack:['sword','boot','helmet','heart','ram','flag','sword','flag','crown'],defense:['shield','chest','heart','bandage','hospital','hammer','shield','flag','crown'],gather:['wheat','chest','cart','hammer','boot','book','wheat','coin','crown'],hunter:['paw','boot','shield','bow','coin','flask','sword','flag','crown']};
    const branchArt={attack:'sword',defense:'shield',gather:'wheat',hunter:'bow'};
    const introductions={attack:'Führe deine Truppen zum Sieg.',defense:'Mach dein Dorf zur sicheren Heimat.',gather:'Sammle mehr und entwickle dein Dorf.',hunter:'Besiege Monster und erbeute mehr.'};
    const icon=key=>`<svg class="talent-art" viewBox="0 0 64 64" aria-hidden="true"><use href="${esc(base+'/assets/icons/talents.svg#'+key)}"></use></svg>`;
    const nodeIcon=n=>icon(art[n.code.split('_')[0]]?.[Number(n.code.split('_')[1])]||'crown');
    const controls=n=>`<div class="talent-rank-controls">${button(`<span aria-hidden="true">−</span><span class="talent-sr">Punkt entfernen: ${esc(n.name)}</span>`,'minus',n.code,pending||!canRemove(n),'rank-control')}<span class="talent-rank" aria-label="Rang ${rank(n)} von ${n.max_level}">${rank(n)}<span> / ${n.max_level}</span></span>${button(`<span aria-hidden="true">+</span><span class="talent-sr">Punkt vergeben: ${esc(n.name)}</span>`,'plus',n.code,pending||!!reason(n)||rank(n)>=n.max_level||spent()>=state.earned,'rank-control is-primary')}</div>`;
    function reason(n,p=plan) {
        const earlier=nodes().filter(x=>x.code.split('_')[0]===n.code.split('_')[0]&&x.tier<n.tier).reduce((s,x)=>s+(p[x.code]||0),0);
        if(state.lord.level<n.required_level)return `Benötigt Hunter-Level ${n.required_level}.`;
        if(earlier<n.required_points)return `Benötigt ${n.required_points} Punkte in den oberen Reihen (${earlier}/${n.required_points}).`;
        if(n.parents.length&&!n.parents.some(code=>(p[code]||0)>=3))return 'Ein verbundenes Vorgängertalent muss Rang 3 erreichen.';
        return '';
    }
    function valid(p) {return nodes().every(n=>!(p[n.code]>0)||!reason(n,p));}
    function canRemove(n) {return rank(n)>0&&valid({...plan,[n.code]:rank(n)-1});}
    function render() {
        if(host()?.querySelector('.lord-talents')&&state?.world_id===world())return true;
        load();return true;
    }
    async function load(force=false) {
        const startedWorld=world();
        host().innerHTML='<p role="status">Talente werden geladen …</p>';
        // Polls and viewport changes must not repeatedly supersede a slow response.
        if(!force&&loading&&Object.is(loading.world,startedWorld))return;
        const token=++generation;loading={token,world:startedWorld};
        try {
            const data=await api('progression/state');
            if(token!==generation||!visible()||(startedWorld&&(startedWorld!==world()||startedWorld!==Number(data.mastery.world_id))))return;
            state=data.mastery;plan={...state.ranks};retry=null;sheet=null;paint();
        } catch(e) {if(token===generation&&visible())host().innerHTML=`<p role="alert">${esc(e.message)}</p>${button('Erneut laden','reload')}`;}
        finally {if(loading?.token===token)loading=null;}
    }
    function connections(b) {
        // Grid centres stay proportional as the two tree lanes resize.
        const point=n=>({x:[24,50,76][n.column],y:n.tier*22});
        const paths=b.nodes.flatMap(n=>n.parents.map(code=>{
            const parent=b.nodes.find(p=>p.code===code),a=point(parent),z=point(n);
            const open=(plan[code]||0)>=3&&!reason(n);
            return `<path class="${open?'is-open':''}" d="M${a.x},${a.y+18} V${z.y-2} H${z.x} V${z.y}"/>`;
        })).join('');
        return `<svg class="talent-lines" viewBox="0 0 100 106" preserveAspectRatio="none" aria-hidden="true">${paths}</svg>`;
    }
    function tree(b) {
        return `<div class="talent-board" aria-label="Talentbaum ${esc(b.name)}">${connections(b)}${b.nodes.map(n=>{
            const why=reason(n),r=rank(n);
            return `<article class="talent-node ${why?'is-locked':''} ${r===n.max_level?'is-max':''} ${r?'is-learned':''} ${n.tier===4?'is-capstone':''}" style="--lane:${n.column===2?2:1};--tier:${n.tier+1}"><button type="button" data-action="talent-select" data-id="${n.code}" class="talent-node-info" aria-label="Details: ${esc(n.name)}, Rang ${r} von ${n.max_level}${why?', gesperrt':''}"><span class="talent-icon">${nodeIcon(n)}</span><span class="talent-node-copy"><strong>${esc(n.name)}</strong><span>${esc(n.label)}</span></span><span class="talent-info-mark" aria-hidden="true">i</span></button><div class="talent-node-bottom"><span class="talent-node-bonus ${why?'is-muted':''}">${why?'Gesperrt':r===n.max_level?'✓ '+pct(n.bonus*r):pct(n.bonus)+' / Rang'}</span>${controls(n)}</div></article>`;
        }).join('')}</div>`;
    }
    function details() {
        const n=nodes().find(n=>n.code===selected)||nodes()[0],r=rank(n),why=reason(n);
        const maxed=r===n.max_level,empty=spent()>=state.earned;
        return `<div class="talent-detail-heading"><span class="talent-detail-art">${nodeIcon(n)}</span><div><span class="talent-eyebrow">${n.tier===4?'Abschlusstalent':'Reihe '+(n.tier+1)}</span><h2 id="talent-sheet-title">${esc(n.name)}</h2></div></div><p>${esc(n.description)}</p><div class="talent-effect"><span>${esc(n.label)}</span><strong>${pct(n.bonus*r)} ${!maxed?`<span aria-label="nach dem nächsten Punkt">→ ${pct(n.bonus*(r+1))}</span>`:''}</strong></div>${n.code==='gather_8'?'<p>Auf Rang 5 erhältst du zusätzlich einen Marschplatz zum Sammeln.</p>':''}<p class="talent-feedback ${why?'is-locked':''}">${esc(why||(maxed?'Vollständig gelernt.':empty?'Alle verfügbaren Punkte sind verteilt.':'Ein weiterer Rang kostet 1 Punkt.'))}</p>${controls(n)}${r>0&&!canRemove(n)?'<p class="talent-fineprint">Entferne zuerst die verbundenen Talente darunter.</p>':''}<details class="talent-requirements"><summary>Alle Voraussetzungen</summary><ul><li>Hunter-Level ${n.required_level}</li>${n.required_points?`<li>${n.required_points} Punkte in oberen Reihen dieses Bereichs</li>`:'<li>Keine vorherigen Talente nötig</li>'}${n.parents.length?`<li>${n.parents.map(code=>esc(nodes().find(x=>x.code===code).name)).join(' oder ')} auf Rang 3</li>`:''}</ul></details>`;
    }
    function bonuses() {
        const sums=new Map();nodes().forEach(n=>{if(rank(n))sums.set(n.label,(sums.get(n.label)||0)+n.bonus*rank(n));});
        return `<h2 id="talent-sheet-title">Deine ${dirty()?'geplanten ':''}Boni</h2><p>Alle vier Bereiche zusammen.</p><ul class="talent-bonus-list">${[...sums].map(([name,value])=>`<li><span>${esc(name)}</span><strong>${pct(value)}</strong></li>`).join('')||'<li>Vergib deinen ersten Talentpunkt, um dein Dorf zu stärken.</li>'}${plan.gather_8===5?'<li><span>Zusätzlicher Sammelmarsch</span><strong>+1</strong></li>':''}</ul>`;
    }
    function options() {
        const wait=state.respec_available_at>Math.floor(Date.now()/1000);
        return `<h2 id="talent-sheet-title">Talentplan verwalten</h2><p>Du erhältst pro Hunter-Level einen Punkt. Monsterjagd bringt Jagd-XP für das nächste Level.</p><div class="talent-option"><strong>Änderungen verwerfen</strong><p>Kehre zu deinen gespeicherten Talenten zurück.</p>${button('Änderungen verwerfen','discard','',pending||!dirty())}</div><div class="talent-option"><strong>Neu verteilen</strong><p>${wait?'Wieder verfügbar: '+esc(new Date(state.respec_available_at*1000).toLocaleString('de-DE')):'Alle 24 Stunden kostenlos. Du kannst den neuen Plan vor dem Speichern ausprobieren.'}</p>${button('Alle Punkte zurücknehmen','reset','',pending||spent()===0)}</div><div class="talent-option"><strong>Gespeicherten Stand laden</strong><p>Aktualisiert die Anzeige und verwirft ungespeicherte Änderungen.</p>${button('Stand neu laden','reload','',pending)}</div>`;
    }
    function paint(focusAction='',focusId='') {
        if(!state||!visible())return;
        const scroll=host().querySelector('.talent-tree-scroll')?.scrollTop||0;
        const sheetScroll=host().querySelector('.talent-sheet-body')?.scrollTop||0;
        const b=state.branches.find(b=>b.code===branch)||state.branches[0];
        const l=state.lord,progress=l.level===l.max_level?100:Math.min(100,l.xp_into_level/Math.max(1,l.xp_next)*100),free=state.earned-spent();
        const respec=Object.entries(state.ranks).some(([k,v])=>(plan[k]||0)<v);
        const cooldown=respec&&state.respec_available_at>Math.floor(Date.now()/1000);
        const blocked=state.blocked_reason||(cooldown?'Neu verteilen ist ab '+new Date(state.respec_available_at*1000).toLocaleString('de-DE')+' wieder möglich.':'');
        host().innerHTML=`<section class="lord-talents branch-${b.code}"><div class="talent-workspace" ${sheet?'inert':''}><header class="talent-header"><div class="lord-emblem">${icon('crown')}<strong>${l.level}</strong></div><div class="talent-progress"><h2>Dein Hunter · Level ${l.level}</h2><span>Wähle deinen Weg.</span><div class="talent-xp" role="progressbar" aria-label="Jagd-XP" aria-valuemin="0" aria-valuemax="100" aria-valuenow="${Math.round(progress)}"><i style="width:${progress}%"></i></div><small>${l.level===l.max_level?'Höchstlevel erreicht':`${fmt(l.xp_into_level)} / ${fmt(l.xp_next)} Jagd-XP`}</small></div><div class="talent-points" role="status" aria-live="polite"><strong>${free}</strong><span>${free===1?'Punkt frei':'Punkte frei'}</span></div></header><nav class="talent-tabs" aria-label="Talentbereiche">${state.branches.map(x=>`<button type="button" data-action="talent-branch" data-id="${x.code}" class="talent-tab-${x.code} ${b.code===x.code?'is-active':''}" aria-pressed="${b.code===x.code}">${icon(branchArt[x.code])}<span>${esc(x.name)}</span><small>${x.nodes.reduce((sum,n)=>sum+rank(n),0)}<span class="talent-sr"> Punkte verteilt</span></small></button>`).join('')}</nav><div class="talent-tree-scroll" tabindex="0" aria-label="Talentbaum – zum Erkunden scrollen"><div class="talent-tree-intro"><p>${introductions[b.code]}</p><span>Mit + lernen · Name antippen für Details</span></div>${tree(b)}<p class="talent-tree-help">Folge den Verbindungen nach unten. Neue Reihen öffnen sich mit 5, 15, 25 und 35 Punkten in den Reihen darüber.</p></div>${blocked?`<p class="talent-blocked" role="status">${esc(blocked)}</p>`:''}<footer class="talent-footer"><div class="talent-footer-tools">${button('Deine Boni','bonuses')}${button('<span aria-hidden="true">•••</span><span class="talent-sr">Talentplan verwalten</span>','options','',false,'talent-options')}</div><span class="talent-save-state">${dirty()?'Noch nicht gespeichert':'✓ Gespeichert'}</span>${button(pending?'Speichert …':'Speichern','apply','',pending||!dirty()||!!blocked,'is-primary talent-save')}</footer></div>${sheet?`<div class="talent-sheet" role="dialog" aria-modal="true" aria-labelledby="talent-sheet-title" tabindex="-1"><div class="talent-sheet-card"><header>${button('‹ Zurück zum Baum','close-sheet','',pending)}</header><div class="talent-sheet-body">${sheet==='detail'?details():sheet==='bonuses'?bonuses():options()}</div></div></div>`:''}</section>`;
        host().querySelector('.talent-tree-scroll').scrollTop=scroll;
        const sheetEl=host().querySelector('.talent-sheet');
        if(sheetEl){
            sheetEl.querySelector('.talent-sheet-body').scrollTop=sheetScroll;
            sheetEl.addEventListener('keydown',e=>{
                if(e.key==='Escape'){e.preventDefault();e.stopPropagation();if(!pending){const previous=sheet;sheet=null;paint(previous==='detail'?'talent-select':'talent-'+previous,previous==='detail'?selected:'');}}
                if(e.key==='Tab'){
                    const items=[...sheetEl.querySelectorAll('button:not(:disabled),summary')],first=items[0],last=items.at(-1);
                    if(!items.length){e.preventDefault();return;}
                    if(e.shiftKey&&(document.activeElement===first||document.activeElement===sheetEl)){e.preventDefault();last.focus();}
                    else if(!e.shiftKey&&(document.activeElement===last||document.activeElement===sheetEl)){e.preventDefault();first.focus();}
                }
            });
        }
        const focusRoot=sheetEl||host();
        const focus=focusAction&&(focusRoot.querySelector(`[data-action="${focusAction}"][data-id="${focusId}"]:not(:disabled)`)||focusRoot.querySelector(`[data-action="talent-select"][data-id="${focusId}"]`));
        if(focus)focus.focus({preventScroll:true});else if(sheetEl)sheetEl.focus({preventScroll:true});
    }
    async function apply() {
        if(pending||!dirty())return;
        pending=true;const savedWorld=state.world_id,token=generation;
        const payload={action:'mastery.apply',ranks:{...plan},revision:state.revision,expected_world_id:savedWorld};
        const signature=JSON.stringify(payload);
        if(retry?.signature!==signature)retry={signature,key:globalThis.crypto?.randomUUID?.()||'talent_'+Date.now()+'_'+Math.random().toString(36).slice(2)};
        payload.operation_key=retry.key;paint();
        try {
            const result=await api('progression/action',payload);
            if(token===generation&&savedWorld===world()){state=result.mastery;plan={...state.ranks};retry=null;toast(result.message);}
            await refresh(false);
        } catch(e) {toast(e.message);}
        finally {pending=false;if(token===generation&&savedWorld===world())paint();}
    }
    function onClick(action,el) {
        if(!action.startsWith('talent-'))return false;
        const key=action.slice(7),code=el.dataset.id;
        if(key==='reload'){if(!pending)load(true);return true;}
        if(!state||pending)return true;
        if(key==='branch'){branch=code;selected=state.branches.find(b=>b.code===branch)?.nodes[0].code||selected;}
        if(key==='select'){selected=code;sheet='detail';}
        if(key==='bonuses'||key==='options')sheet=key;
        if(key==='close-sheet'){const previous=sheet;sheet=null;paint(previous==='detail'?'talent-select':'talent-'+previous,previous==='detail'?selected:'');return true;}
        const n=nodes().find(x=>x.code===code);
        if(key==='plus'&&n&&!reason(n)&&rank(n)<5&&spent()<state.earned)plan[code]=rank(n)+1;
        if(key==='minus'&&n&&canRemove(n))plan[code]=rank(n)-1;
        if(key==='discard'){plan={...state.ranks};sheet=null;}
        if(key==='reset'){plan={};sheet=null;}
        if(key==='apply'){apply();return true;}
        paint(action,code);
        if(key==='branch')host().querySelector('.talent-tree-scroll').scrollTop=0;
        return true;
    }
    return {render,onClick};
};

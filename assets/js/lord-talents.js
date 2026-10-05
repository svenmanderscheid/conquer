/* Talent plans stay local until one versioned, atomic server apply succeeds. */
window.ConquerTalents=function(ctx){
    'use strict';
    const {api,base,esc,fmt,getState,refresh,toast}=ctx;
    const tr=(key,p={})=>window.ConquerLocale.t('talents.'+key,p);
    const text=(n,field)=>n[field+'_key']?window.ConquerLocale.t(n[field+'_key']):window.ConquerLocale.text(n[field]);
    let state=null,plan={},branch='monster',selected='monster_9',generation=0,pending=false,retry=null,sheet=null,loading=null;
    const host=()=>document.querySelector('#content'),visible=()=>document.querySelector('#panel-dialog')?.dataset.panel==='mastery';
    const world=()=>Number(getState()?.world?.id||getState()?.world?.world_id||state?.world_id);
    const nodes=()=>state?.branches.flatMap(b=>b.nodes)||[],rank=n=>Number(plan[n.code]||0),spent=()=>Object.values(plan).reduce((a,n)=>a+Number(n),0);
    const pointSources=()=>tr('point_sources',{hunter:fmt(state.points_from_hunter??state.lord.level+2*Math.floor(state.lord.level/10)),vip:fmt(state.points_from_vip??0),total:fmt(state.earned)});
    const canonical=p=>JSON.stringify(Object.fromEntries(Object.entries(p).filter(([,v])=>v>0).sort())),dirty=()=>state.legacy_plan||canonical(plan)!==canonical(state.ranks);
    const value=(n,v)=>(v>0?'+':'')+Number(n.unit==='AP'?v:v*100).toLocaleString(window.ConquerLocale.locale,{maximumFractionDigits:1})+(n.unit==='AP'?' AP':' %');
    const button=(label,action,id='',disabled=false,cls='')=>`<button type="button" class="talent-button ${cls}" data-action="talent-${action}" data-id="${esc(id)}" ${disabled?'disabled':''}>${label}</button>`;
    const icon=path=>`<img class="talent-art" src="${esc(base+'/'+path)}" width="64" height="64" alt="" aria-hidden="true" decoding="async" draggable="false">`,nodeIcon=n=>icon(n.icon);
    function reachable(p=plan){
        const found=new Set(nodes().filter(n=>n.entry&&p[n.code]>0).map(n=>n.code));let changed;
        do{changed=false;for(const n of nodes())if(p[n.code]>0&&!found.has(n.code)&&n.parents.some(parent=>found.has(parent))){found.add(n.code);changed=true;}}while(changed);
        return found;
    }
    function reason(n,p=plan,connected=reachable(p)){
        if(state.lord.level<n.required_level)return tr('level_required',{level:n.required_level});
        if(!n.entry&&!connected.has(n.code)&&!n.parents.some(code=>connected.has(code)))return tr('route_required');
        return '';
    }
    const valid=p=>{const connected=reachable(p);return nodes().every(n=>!p[n.code]||!reason(n,p,connected));};
    const canRemove=n=>rank(n)>0&&valid({...plan,[n.code]:rank(n)-1});
    const controls=n=>`<div class="talent-rank-controls">${button(`<span aria-hidden="true">−</span><span class="talent-sr">${esc(tr('remove_point',{name:text(n,'name')}))}</span>`,'minus',n.code,pending||!canRemove(n),'rank-control')}<span class="talent-rank" aria-label="${esc(tr('rank',{rank:rank(n),max:n.max_level}))}">${rank(n)} / ${n.max_level}</span>${button(`<span aria-hidden="true">+</span><span class="talent-sr">${esc(tr('add_point',{name:text(n,'name')}))}</span>`,'plus',n.code,pending||!!reason(n)||rank(n)>=n.max_level||spent()>=state.earned,'rank-control is-primary')}</div>`;
    function render(){if(host()?.querySelector('.lord-talents')&&state?.world_id===world())return true;load();return true;}
    async function load(force=false){
        const startedWorld=world();if(!force&&loading&&Object.is(loading.world,startedWorld))return;
        host().innerHTML=`<p role="status">${esc(tr('loading'))}</p>`;
        const token=++generation;loading={token,world:startedWorld};
        try{
            const data=await api('progression/state');
            if(token!==generation||!visible()||(startedWorld&&(startedWorld!==world()||startedWorld!==Number(data.mastery.world_id))))return;
            state=data.mastery;plan={...state.ranks};retry=null;sheet=null;
            if(!state.branches.some(b=>b.code===branch))branch=state.branches[0].code;
            selected=state.branches.find(b=>b.code===branch).nodes.find(n=>n.entry).code;paint();
        }catch(e){if(token===generation&&visible())host().innerHTML=`<p role="alert">${esc(e.message)}</p>${button(esc(tr('reload')),'reload')}`;}
        finally{if(loading?.token===token)loading=null;}
    }
    const position=n=>({x:18+(n.x-14)*64/72,y:n.y});
    function tree(b){
        const connected=reachable(),paths=b.nodes.flatMap(n=>n.parents.map(code=>`<path data-parent="${esc(code)}" data-child="${esc(n.code)}" class="${connected.has(code)?connected.has(n.code)?'is-learned':'is-open':''}"/>`)).join('');
        return `<div class="talent-constellation"><svg class="talent-lines" aria-hidden="true">${paths}</svg>${b.nodes.map(n=>{
            const r=rank(n),why=reason(n,plan,connected),name=text(n,'name');
            return `<article class="talent-star ${n.waypoint?'is-waypoint':''} ${n.entry?'is-entry':''} ${n.required_level===40?'is-capstone':''} ${r?'is-learned':''} ${r===n.max_level?'is-max':''} ${why?'is-locked':''} ${selected===n.code?'is-selected':''}" data-code="${esc(n.code)}" style="left:${position(n).x}%;top:${n.y}%"><button type="button" class="talent-star-select" data-action="talent-select" data-id="${esc(n.code)}" aria-label="${esc(tr('node_details',{name,rank:tr('rank',{rank:r,max:n.max_level}),locked:why?tr('locked_suffix'):''}))}" aria-pressed="${selected===n.code}">${nodeIcon(n)}<span class="talent-star-rank">${r} / ${n.max_level}</span>${why?'<span class="talent-star-lock" aria-hidden="true">⌑</span>':''}</button><span class="talent-star-name">${n.entry?esc(tr('start'))+' · ':''}${esc(n.waypoint&&!n.entry?value(n,n.bonus):name)}</span></article>`;
        }).join('')}</div>`;
    }
    function drawLinks(){
        const board=host()?.querySelector('.talent-constellation');if(!board||!state)return;
        const b=state.branches.find(b=>b.code===branch),width=board.clientWidth,height=board.clientHeight;
        if(!b)return;
        board.querySelector('svg').setAttribute('viewBox',`0 0 ${width} ${height}`);
        for(const path of board.querySelectorAll('path')){
            const parent=b.nodes.find(n=>n.code===path.dataset.parent),child=b.nodes.find(n=>n.code===path.dataset.child),label=board.querySelector(`[data-code="${parent.code}"] .talent-star-name`);
            const a=position(parent),z=position(child),x=a.x*width/100,y=a.y*height/100+28+label.offsetHeight+8,x2=z.x*width/100,y2=z.y*height/100-(child.waypoint?23:30),middle=(y+y2)/2;
            path.setAttribute('d',`M${x},${y} C${x},${middle} ${x2},${middle} ${x2},${y2}`);
        }
    }
    function details(){
        const n=nodes().find(n=>n.code===selected);if(!n)return '';
        const r=rank(n),why=reason(n),maxed=r===n.max_level,empty=spent()>=state.earned,next=nodes().filter(node=>node.parents.includes(n.code));
        return `<div class="talent-detail-heading"><span class="talent-detail-art">${nodeIcon(n)}</span><div><span class="talent-eyebrow">${esc(tr(n.entry?'entry':n.waypoint?'waypoint':n.required_level===40?'master_star':'talent'))}</span><h2 id="talent-sheet-title">${esc(text(n,'name'))}</h2></div></div><p>${esc(text(n,'description'))}</p><div class="talent-effect"><span>${esc(text(n,'label'))}</span><strong>${esc(value(n,n.bonus*r))} ${!maxed?`<span aria-label="${esc(tr('next_rank'))}">→ ${esc(value(n,n.bonus*(r+1)))}</span>`:''}</strong></div>${n.additional_bonus?`<p>${esc(tr(n.additional_bonus.stat==='hunt_march_slots'?'extra_hunt_slot':'extra_gather_slot'))}</p>`:''}<p class="talent-feedback ${why?'is-locked':''}">${esc(why||(maxed?tr('fully_learned'):empty?tr('no_points'):tr('rank_cost')))}</p>${controls(n)}${r>0&&!canRemove(n)?`<p class="talent-fineprint">${esc(tr('refund_route'))}</p>`:''}<details class="talent-requirements"><summary>${esc(tr('requirements'))}</summary><ul><li>${esc(tr('level_required',{level:n.required_level}))}</li><li>${esc(n.entry?tr('entry_hint'):tr('parent_hint'))}</li>${n.parents.length?`<li>${esc(tr('parents',{names:n.parents.map(code=>text(nodes().find(x=>x.code===code),'name')).join(tr('or'))}))}</li>`:''}</ul></details>${next.length?`<p class="talent-next-routes">${esc(tr('next_nodes',{names:next.map(x=>text(x,'name')).join(', ')}))}</p>`:''}`;
    }
    function bonuses(){
        const sums=new Map();for(const n of nodes())if(rank(n)){const old=sums.get(n.stat)||{n,value:0};old.value+=n.bonus*rank(n);sums.set(n.stat,old);}
        return `<h2 id="talent-sheet-title">${esc(tr(dirty()?'planned_bonuses':'your_bonuses'))}</h2><p>${esc(tr('six_branches'))}</p>${state.legacy_plan?`<p class="talent-legacy-note">${esc(tr('legacy_notice'))}</p>`:''}<ul class="talent-bonus-list">${[...sums.values()].map(({n,value:v})=>`<li><span>${esc(text(n,'label'))}</span><strong>${esc(value(n,v))}</strong></li>`).join('')||`<li>${esc(tr('no_bonuses'))}</li>`}${nodes().filter(n=>n.additional_bonus&&rank(n)>=n.additional_bonus.required_rank).map(n=>`<li><span>${esc(tr(n.additional_bonus.stat==='hunt_march_slots'?'hunt_slot':'gather_slot'))}</span><strong>+1</strong></li>`).join('')}</ul>`;
    }
    function options(){
        const wait=state.respec_available_at>Math.floor(Date.now()/1000);
        return `<h2 id="talent-sheet-title">${esc(tr('manage_plan'))}</h2><p>${esc(tr('points_description'))}</p><p>${esc(pointSources())}</p><div class="talent-option"><strong>${esc(tr('discard'))}</strong><p>${esc(tr('discard_hint'))}</p>${button(esc(tr('discard')),'discard','',pending||!dirty())}</div><div class="talent-option"><strong>${esc(tr('redistribute'))}</strong><p>${esc(wait?tr('available_at',{time:new Date(state.respec_available_at*1000).toLocaleString(window.ConquerLocale.locale)}):tr('respec_hint'))}</p>${button(esc(tr('remove_all')),'reset','',pending||spent()===0)}</div><div class="talent-option"><strong>${esc(tr('load_saved'))}</strong><p>${esc(tr('reload_hint'))}</p>${button(esc(tr('reload')),'reload','',pending)}</div>`;
    }
    function paint(focusAction='',focusId=''){
        if(!state||!visible())return;
        const scroll=host().querySelector('.talent-tree-scroll')?.scrollTop||0,sheetScroll=host().querySelector('.talent-sheet-body')?.scrollTop||0,b=state.branches.find(b=>b.code===branch)||state.branches[0];branch=b.code;
        const l=state.lord,progress=l.level===l.max_level?100:Math.min(100,l.xp_into_level/Math.max(1,l.xp_next)*100),free=state.earned-spent(),respec=Object.entries(state.ranks).some(([k,v])=>(plan[k]||0)<v),cooldown=respec&&state.respec_available_at>Math.floor(Date.now()/1000);
        const blocked=state.blocked_reason||(cooldown?tr('available_at',{time:new Date(state.respec_available_at*1000).toLocaleString(window.ConquerLocale.locale)}):'');
        host().innerHTML=`<section class="lord-talents branch-${b.code}"><div class="talent-workspace" ${sheet?'inert':''}><header class="talent-header"><div class="lord-emblem"><svg class="talent-art" viewBox="0 0 64 64" aria-hidden="true"><use href="${esc(base+'/assets/icons/talents.svg#crown')}"></use></svg><strong>${l.level}</strong></div><div class="talent-progress"><h2>${esc(tr('hunter_title',{level:l.level}))}</h2><p class="talent-point-sources">${esc(pointSources())}</p><div class="talent-xp" role="progressbar" aria-label="${esc(tr('hunter_xp'))}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="${Math.round(progress)}"><i style="width:${progress}%"></i></div><small>${esc(l.level===l.max_level?tr('maximum_level'):tr('xp_progress',{current:fmt(l.xp_into_level),next:fmt(l.xp_next)}))}</small></div><div class="talent-points" role="status" aria-live="polite"><strong>${free}</strong><span>${esc(tr(free===1?'point_free':'points_free'))}</span></div></header><nav class="talent-tabs" aria-label="${esc(tr('branches'))}">${state.branches.map(x=>`<button type="button" data-action="talent-branch" data-id="${x.code}" class="${b.code===x.code?'is-active':''}" aria-pressed="${b.code===x.code}">${icon(x.icon)}<span>${esc(text(x,'name'))}</span><small>${x.nodes.reduce((sum,n)=>sum+rank(n),0)}<span class="talent-sr"> ${esc(tr('allocated'))}</span></small></button>`).join('')}</nav><div class="talent-tree-scroll" tabindex="0" aria-label="${esc(tr('explore'))}"><div class="talent-tree-intro"><h3>${esc(text(b,'name'))}</h3><p>${esc(text(b,'scope'))}</p><span>${esc(tr('lattice_rule'))}</span></div>${state.legacy_plan?`<p class="talent-legacy-note">${esc(tr('legacy_notice'))}</p>`:''}${tree(b)}<p class="talent-tree-help">${esc(tr('legend'))}</p></div>${blocked?`<p class="talent-blocked" role="status">${esc(window.ConquerLocale.text(blocked))}</p>`:''}<footer class="talent-footer"><div class="talent-footer-tools">${button(esc(tr('your_bonuses')),'bonuses')}${button(`<span aria-hidden="true">•••</span><span class="talent-sr">${esc(tr('manage_plan'))}</span>`,'options','',false,'talent-options')}</div><span class="talent-save-state">${esc(tr(dirty()?'unsaved':'saved'))}</span>${button(esc(tr(pending?'saving':'save')),'apply','',pending||!dirty()||!!blocked,'is-primary talent-save')}</footer></div>${sheet?`<div class="talent-sheet" role="dialog" aria-modal="true" aria-labelledby="talent-sheet-title" tabindex="-1"><div class="talent-sheet-card"><header>${button(esc(tr('back_to_tree')),'close-sheet','',pending)}</header><div class="talent-sheet-body">${sheet==='detail'?details():sheet==='bonuses'?bonuses():options()}</div></div></div>`:''}</section>`;
        host().querySelector('.talent-tree-scroll').scrollTop=scroll;requestAnimationFrame(drawLinks);
        const sheetEl=host().querySelector('.talent-sheet');
        if(sheetEl){sheetEl.querySelector('.talent-sheet-body').scrollTop=sheetScroll;sheetEl.addEventListener('keydown',e=>{
            if(e.key==='Escape'){e.preventDefault();e.stopPropagation();if(!pending){const previous=sheet;sheet=null;paint(previous==='detail'?'talent-select':'talent-'+previous,previous==='detail'?selected:'');}}
            if(e.key==='Tab'){const items=[...sheetEl.querySelectorAll('button:not(:disabled),summary')],first=items[0],last=items.at(-1);if(!items.length){e.preventDefault();return;}if(e.shiftKey&&(document.activeElement===first||document.activeElement===sheetEl)){e.preventDefault();last.focus();}else if(!e.shiftKey&&(document.activeElement===last||document.activeElement===sheetEl)){e.preventDefault();first.focus();}}
        });}
        const focusRoot=sheetEl||host(),focus=focusAction&&(focusRoot.querySelector(`[data-action="${focusAction}"][data-id="${focusId}"]:not(:disabled)`)||focusRoot.querySelector(`[data-action="talent-select"][data-id="${focusId}"]`));
        if(focus)focus.focus({preventScroll:true});else if(sheetEl)sheetEl.focus({preventScroll:true});
    }
    async function apply(){
        if(pending||!dirty())return;pending=true;const savedWorld=state.world_id,token=generation,payload={action:'mastery.apply',ranks:{...plan},revision:state.revision,expected_world_id:savedWorld},signature=JSON.stringify(payload);
        if(retry?.signature!==signature)retry={signature,key:globalThis.crypto?.randomUUID?.()||'talent_'+Date.now()+'_'+Math.random().toString(36).slice(2)};payload.operation_key=retry.key;paint();
        try{const result=await api('progression/action',payload);if(token===generation&&savedWorld===world()){state=result.mastery;plan={...state.ranks};retry=null;toast(result.message);}await refresh(false);}
        catch(e){toast(e.message);}finally{pending=false;if(token===generation&&savedWorld===world())paint();}
    }
    function onClick(action,el){
        if(!action.startsWith('talent-'))return false;const key=action.slice(7),code=el.dataset.id;
        if(key==='reload'){if(!pending)load(true);return true;}if(!state||pending)return true;
        if(key==='branch'){branch=code;selected=state.branches.find(b=>b.code===branch)?.nodes.find(n=>n.entry)?.code||selected;}
        if(key==='select'){selected=code;sheet='detail';}if(key==='bonuses'||key==='options')sheet=key;
        if(key==='close-sheet'){const previous=sheet;sheet=null;paint(previous==='detail'?'talent-select':'talent-'+previous,previous==='detail'?selected:'');return true;}
        const n=nodes().find(x=>x.code===code);
        if(key==='plus'&&n&&!reason(n)&&rank(n)<n.max_level&&spent()<state.earned)plan[code]=rank(n)+1;
        if(key==='minus'&&n&&canRemove(n))plan[code]=rank(n)-1;
        if(key==='discard'){plan={...state.ranks};sheet=null;}if(key==='reset'){plan={};sheet=null;}
        if(key==='apply'){apply();return true;}paint(action,code);if(key==='branch')host().querySelector('.talent-tree-scroll').scrollTop=0;return true;
    }
    new ResizeObserver(()=>{if(visible())drawLinks();}).observe(document.querySelector('#panel-dialog'));
    return {render,onClick};
};

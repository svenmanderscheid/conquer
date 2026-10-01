window.ConquerRallies = function({base='',api,esc,fmt,date,duration,openDialog,action,toast,marchPanel,getState,unitName=t=>t.name_de||t.name,now=()=>Date.now()}) {
    let rallies=[],current=null,participants=[],pending=false,requestVersion=0,listReads=0;
    const text=(key,fallback,params={})=>{const value=window.ConquerLocale?.t(key,params);return value&&value!==key?value:fallback.replace(/\{(\w+)\}/g,(_,name)=>params[name]??'');};
    const self=()=>Number(getState().player?.id||getState().city?.player_id);
    const activeMembers=r=>(r.participants||[]).filter(p=>!['cancelled','returned'].includes(p.status));
    const remaining=r=>Number(r.capacity)>0?Math.max(0,Number(r.capacity)-rallyTroops(r)):null;
    const joinState=r=>Number(r.leader_player_id)===self()?'leader':activeMembers(r).some(p=>Number(p.player_id)===self())?'joined':r.status!=='gathering'||(r.launch_at&&date(r.launch_at)<=now())?'closed':remaining(r)===0?'full':'open';
    const statusLabel=r=>text('rally.status.'+r.status,{gathering:'Gathering',marching:'Marching',returning:'Returning',complete:'Completed',completed:'Completed',cancelled:'Cancelled',unavailable:'No longer active'}[r.status]||r.status);
    const button=(name,act,id='',cls='secondary')=>`<button class="button ${cls}" data-action="${act}" data-id="${id}">${name}</button>`;
    const troopCount=p=>Object.values(p?.troops||{}).reduce((n,v)=>n+Number(v||0),0);
    const rallyTroops=r=>Number(r.troop_count??r.total_troops??r.joined_troops??(troopCount(r)+(r.participants||[]).filter(p=>p.status!=='cancelled'&&p.status!=='returned').reduce((sum,p)=>sum+troopCount(p),0)));
    const memberCount=r=>Number(r.member_count??((r.participant_count??(r.participants||[]).length)+(r.leader_player_id?1:0)));
    const statusClass=r=>['gathering','marching','returning'].includes(r.status)?r.status:'complete';
    const distance=(x,y)=>{const city=getState().city||{},value=Math.hypot(Number(x)-Number(city.coord_x),Number(y)-Number(city.coord_y));return Number.isFinite(value)?text('rally.distance','{count} fields away',{count:fmt(value.toFixed(value<10?1:0))}):'';};
    const avatar=p=>{const custom=String(p?.profile_image||'');if(/^assets\/uploads\/profile\/\d+-[a-f0-9]{32}\.jpg$/.test(custom))return `${base}/${custom}`;const name=['knight','archer','rider'].includes(p?.avatar)?p.avatar:'knight';return `${base}/assets/art/${name}.png`;};
    const monsterImage=r=>{
        const m=r.result?.monster||{},art=String(m.art||'').replace(/\.png$/,'');
        const regional=`${art} ${m.name||''}`.match(/grumwald|frostgrimm|sandmaul|glutramm/i);
        if(regional)return `${base}/assets/art/monsters/storybook-v2/${regional[0].toLowerCase()}.png`;
        const id=art.split('/').pop(),file=/treasure[ _-]+goblin|schatzgoblin/i.test(m.name||'')||['goblin','treasure-goblin'].includes(id)?'treasure-goblin-turquoise':id;
        if(['orc','skeleton','golem','treasure-goblin-turquoise','green-dragon','red-dragon','gold-dragon','magdar'].includes(file))return `${base}/assets/art/monsters/2.5d/bright-v2/${file}.png`;
        if(/^monsters\/[a-z0-9\/-]+$/.test(art)||/^[a-z0-9-]+$/.test(art))return `${base}/assets/art/${art}.png`;
        return `${base}/assets/art/monsters/2.5d/bright-v2/orc.png`;
    };
    function leaderCard(r,detail=false){
        const p=r.leader||{name:r.leader_name||r.leader_username,coord_x:r.origin_x,coord_y:r.origin_y};
        return `<section class="rally-side rally-side--leader"><small>${esc(text('rally.captain','Rally captain'))}</small>${detail?`<img class="rally-captain-avatar" src="${esc(avatar(p))}" alt="">`:''}<div><strong translate="no">${esc(p.name||text('rally.unknown_player','Unknown player'))}</strong>${detail?`<span class="rally-leader-meta">X ${fmt(p.coord_x??r.origin_x)} · Y ${fmt(p.coord_y??r.origin_y)}</span>`:''}</div></section>`;
    }
    function targetCard(r,detail=false){
        const monster=r.target_kind==='monster',territory=r.target_kind==='territory',m=r.result?.monster||{},p=r.target_player||{};
        const kind=monster?'monster':territory?'territory':'city',image=monster?monsterImage(r):territory?`${base}/assets/art/map/alliance-outpost.svg`:avatar(p);
        const name=monster?(m.name||r.target_name||text('rally.target.monster','Monster')):territory?(r.target_name||text('rally.target.territory','Alliance territory')):(p.name||r.target_name||text('rally.target.player','Player'));
        const label=monster?text('rally.level','Level {level}',{level:fmt(m.level||1)}):territory?text('rally.target.conquest','Conquest target'):text('rally.target.city','Player city');
        return `<section class="rally-side rally-side--target" data-target-kind="${kind}"><div class="rally-target-art"><img src="${esc(image)}" alt=""></div><div class="rally-target-copy"><small class="rally-target-kind">${esc(label)}</small><strong class="rally-target-name" ${monster?'':'translate="no"'}>${esc(name)}</strong><span class="rally-target-location">X ${fmt(r.target_x)} · Y ${fmt(r.target_y)}</span><span class="rally-target-distance">${esc(distance(r.target_x,r.target_y))}</span>${leaderCard(r,detail)}</div></section>`;
    }
    function membersLabel(r){const count=memberCount(r);return `<span class="rally-member-count">${esc(text(count===1?'rally.members_count_one':'rally.members_count',count===1?'{count} member':'{count} members',{count:fmt(count)}))}</span>`;}
    function troopRoster(p){
        const definitions=getState().troop_defs||[],byCode=new Map(definitions.map(t=>[Number(t.code),t]));
        const troops=Object.entries(p.troops||{}).map(([code,count])=>({code:Number(code),count:Number(count),definition:byCode.get(Number(code))})).filter(t=>t.count>0).sort((a,b)=>Number(b.definition?.tier||0)-Number(a.definition?.tier||0)||Number(a.definition?.type||0)-Number(b.definition?.type||0));
        if(!troops.length)return '<p class="rally-member-empty">'+esc(text('rally.member.empty','No troops reported'))+'</p>';
        return `<div class="rally-member-troops" aria-label="${esc(text('rally.member.deployed','Deployed troops'))}">${troops.map(t=>{const d=t.definition||{},type=Math.max(1,Math.min(3,Number(d.type)||1)),tier=Math.max(1,Number(d.tier)||1),name=unitName(d)||text('rally.unit','Unit {code}',{code:t.code});return `<span class="rally-troop troop-tier-frame" data-troop-tier="${tier}" title="${esc(name)} · T${tier}: ${fmt(t.count)}"><img src="${base}/assets/art/characters/fantasy-troops-v2/${['guardian','fire-archer','shadow-rider'][type-1]}-t${tier}-thumb.webp" alt=""><span><small>${esc(name)} · T${tier}</small><strong>${fmt(t.count)}</strong></span></span>`;}).join('')}</div>`;
    }
    function timing(r){
        const field={gathering:'launch_at',marching:'arrival_time',returning:'return_time'}[r.status];
        if(!field||!r[field])return '';
        const label=text('rally.timing.'+r.status,{gathering:'Starts in',marching:'Arrives in',returning:'Returns in'}[r.status]);
        return `<span class="rally-timing"><small>${esc(label)}</small><strong data-end="${esc(r[field])}">${duration(Math.max(0,(date(r[field])-now())/1000))}</strong></span>`;
    }
    function capacity(r,count=rallyTroops(r)){
        const limit=Number(r.capacity);if(!limit)return '';
        const safeCount=Math.max(0,Number(count)||0),percent=Math.min(100,safeCount/limit*100);
        return `<div class="rally-capacity"><div><span>${esc(text('rally.member.troops','Troops'))}</span><strong>${fmt(safeCount)} <small>/ ${fmt(limit)}</small></strong></div><div class="rally-capacity-track" role="progressbar" aria-label="${esc(text('rally.capacity','Rally troop capacity'))}" aria-valuemin="0" aria-valuemax="${limit}" aria-valuenow="${Math.min(safeCount,limit)}"><i style="width:${percent}%"></i></div></div>`;
    }
    function joinLabel(r){
        const state=joinState(r);
        return text('rally.join.'+state,{open:'Choose troops',leader:'Your rally',joined:'Already joined',closed:'Joining closed',full:'Rally full'}[state]);
    }
    function joinButton(r,compact=false){
        const open=joinState(r)==='open',label=joinLabel(r);
        return '<button type="button" class="button rally-join-button '+(open?'is-available':'secondary')+(compact?' is-compact':'')+'" data-action="rally-join" data-id="'+Number(r.id)+'" aria-label="'+esc(label)+'" title="'+esc(label)+'" '+(open?'':'disabled')+'>'+(open?'<span class="rally-join-plus" aria-hidden="true">+</span>':'')+'<span class="rally-join-label">'+esc(open&&compact?text('rally.join.short','Join'):label)+'</span></button>';
    }
    function teamPreview(r){
        const members=[{player_id:r.leader_player_id,profile:r.leader},...activeMembers(r).filter(p=>Number(p.player_id)!==Number(r.leader_player_id))];
        return '<div class="rally-team-preview"><div class="rally-team-avatars" aria-label="'+esc(text('rally.team','Team'))+'">'+members.slice(0,3).map(p=>'<img translate="no" src="'+esc(avatar(p.profile))+'" alt="'+esc(p.profile?.name||p.username||r.leader_name||'')+'" title="'+esc(p.profile?.name||p.username||r.leader_name||'')+'">').join('')+(members.length>3?'<span>+'+fmt(members.length-3)+'</span>':'')+'</div>'+membersLabel(r)+joinButton(r,true)+'</div>';
    }
    function memberCard(p,r,expanded){
        const captain=Number(p.player_id)===Number(r.leader_player_id),travelling=p.status==='joining',name=p.profile?.name||p.username||p.display_name||'',status=travelling?text('rally.member.en_route','On the way'):captain?text('rally.member.captain','Captain'):r.status==='marching'?text('rally.member.marching','Marching'):r.status==='returning'?text('rally.member.returning','Returning'):text('rally.member.arrived','Arrived');
        return '<details class="rally-member" data-player-id="'+Number(p.player_id)+'" '+(expanded?'open':'')+'><summary><img class="rally-member-avatar" src="'+esc(avatar(p.profile))+'" alt=""><span class="rally-member-name"><strong translate="no">'+esc(name)+'</strong><small>'+esc(status)+(travelling&&p.arrival_time?' · <span data-end="'+esc(p.arrival_time)+'">'+duration(Math.max(0,(date(p.arrival_time)-now())/1000))+'</span>':'')+'</small></span><span class="rally-member-total"><strong>'+fmt(troopCount(p))+'</strong><small>'+esc(text('rally.member.troops','Troops'))+'</small></span><span class="rally-member-chevron" aria-hidden="true">›</span></summary>'+troopRoster(p)+'</details>';
    }
    function joinHint(r){const left=remaining(r);return left===null?'':text('rally.free_capacity','{count} troop spaces available',{count:fmt(left)});}
    function normalize(r,members=r.participants||[]){
        participants=members.filter(p=>!['cancelled','returned'].includes(p.status));
        current={...r,participants};
        if(!participants.some(p=>Number(p.player_id)===Number(r.leader_player_id)))participants=[{player_id:r.leader_player_id,username:r.leader_name||r.leader_username,profile:r.leader,troops:r.troops||{}},...participants];
    }
    function requestContext(){
        const dialog=document.querySelector('#game-dialog'),content=dialog?.querySelector('#dialog-content')?.firstElementChild,wasOpen=dialog?.open,worldId=getState().city?.world_id,route=location.hash,version=++requestVersion;
        return ()=>version===requestVersion&&getState().city?.world_id===worldId&&location.hash===route&&(!wasOpen||dialog.open)&&dialog?.querySelector('#dialog-content')?.firstElementChild===content;
    }
    async function list(){const valid=requestContext();listReads++;try{const result=await api('rally/list');if(!valid())return;rallies=result.rallies||[];renderList();}catch(e){if(valid())toast(e.message);}finally{listReads--;}}
    function renderList(){
        const sorted=rallies.filter(r=>['gathering','marching','returning'].includes(r.status)).slice().sort((a,b)=>(joinState(a)==='open'?0:1)-(joinState(b)==='open'?0:1)||(date(a.launch_at)||0)-(date(b.launch_at)||0));
        const gathering=sorted.filter(r=>r.status==='gathering').length;
        openDialog('<h2>'+esc(text('rally.list_title','Alliance rallies'))+'</h2><div class="rally-browser"><div class="rally-list-heading"><h3>'+esc(text('rally.list_heading','Fight together'))+'</h3><span>'+esc(text('rally.gathering_count','{count} gathering',{count:fmt(gathering)}))+'</span></div><p class="rally-list-intro">'+esc(text('rally.list_hint','Tap a rally for details or + to choose your troops.'))+'</p><div class="window-list rally-list" tabindex="0">'+(sorted.length?sorted.map(r=>'<article class="rally-card rally-card--'+statusClass(r)+'" data-rally-id="'+Number(r.id)+'"><button type="button" class="rally-card-open" data-action="rally-detail" data-id="'+Number(r.id)+'" aria-label="'+esc(text('rally.open_details','View rally led by {name}',{name:r.leader?.name||r.leader_name||r.leader_username||''}))+'"></button><header><span class="rally-status">'+esc(statusLabel(r))+'</span>'+timing(r)+'</header><div class="rally-matchup">'+targetCard(r)+'</div><div class="rally-card-bottom">'+capacity(r)+teamPreview(r)+'</div></article>').join(''):'<div class="empty"><h3>'+esc(text('rally.empty_title','No active rallies'))+'</h3><p>'+esc(text('rally.empty_hint','Choose a rally monster or another player’s city on the world map to start a rally for your alliance.'))+'</p></div>')+'</div></div>');
    }
    async function detail(id,join=false){
        if(pending)return;pending=true;
        const valid=requestContext();
        try{const result=await api('rally/'+Number(id));if(!valid())return;normalize(result.rally,result.participants||[]);if(join&&joinState(current)==='open')openJoin();else renderDetail();}
        catch(e){toast(e.message);}finally{pending=false;}
    }
    function renderDetail(expanded=null){
        const r=current;if(!r)return;
        const leader=Number(r.leader_player_id)===self(),gathering=r.status==='gathering'&&(!r.launch_at||date(r.launch_at)>now());
        const bossRule=r.target_kind==='monster'?(r.result?.report?.boss_mechanic||r.result?.monster?.boss_mechanic):null;
        openDialog('<h2>'+esc(text('rally.detail_title','Rally team'))+'</h2><div class="rally-detail"><section class="rally-detail-hero rally-card--'+statusClass(r)+'"><span class="rally-status">'+esc(statusLabel(r))+'</span>'+timing(r)+'</section><div class="rally-detail-scroll" tabindex="0"><div class="rally-matchup rally-matchup--detail">'+targetCard(r,true)+'</div><div class="rally-detail-strength">'+capacity(r)+membersLabel(r)+'</div><h3 class="rally-members-title">'+esc(text('rally.members_title','Rally team'))+' <span>'+fmt(participants.length)+'</span></h3><div class="window-list rally-members">'+participants.map(p=>memberCard(p,r,expanded?expanded.has(String(p.player_id)):Number(p.player_id)===Number(r.leader_player_id))).join('')+'</div>'+(window.ConquerBossMechanic?.render(bossRule,{rally:true})||'')+'<p class="rally-rule">'+esc(text('rally.arrival_rule','Your troops must reach the leader before the rally starts. The leader can launch early.'))+'</p>'+(joinHint(r)?'<p class="rally-rule">'+esc(joinHint(r))+'</p>':'')+(r.result?.reason?'<p class="notice">'+esc(r.result.reason)+'</p>':'')+'</div><div class="button-row rally-detail-actions">'+button(esc(text('rally.hud.label','Rallies')),'rally-list')+(gathering&&leader?button(esc(text('rally.launch','Launch now')),'rally-launch',r.id,'danger')+button(esc(text('rally.cancel','Cancel')),'rally-cancel',r.id):joinButton(r))+'</div></div>');
    }
    function openJoin(){
        const r=current;if(!r||joinState(r)!=='open')return;
        const host=r.leader||{};
        marchPanel.open(r.leader_player_id,'rally-join',{
            rally_id:Number(r.id),rally_target_kind:r.target_kind||'city',rally_launch_at:r.launch_at,rally_status:r.status,rally_capacity_remaining:remaining(r),
            rally_boss_mechanic:r.target_kind==='monster'?r.result?.monster?.boss_mechanic:null,
            onRallyBack:()=>detail(r.id),
            target:{id:Number(r.leader_player_id),coord_x:Number(host.coord_x??r.origin_x),coord_y:Number(host.coord_y??r.origin_y),display_name:host.name||r.leader_name||r.leader_username,city_skin:host.city_skin,castle_level:host.castle_level??null}
        });
    }
    function sync(rows){
        const previous=JSON.stringify(rallies);rallies=rows||[];
        const dialog=document.querySelector('#game-dialog');if(!dialog?.open||pending||listReads)return;
        const list=dialog.querySelector('.rally-browser'),detailView=dialog.querySelector('.rally-detail');
        if(!list&&!detailView)return;
        const next=current&&rallies.find(r=>Number(r.id)===Number(current.id));
        const changed=list?previous!==JSON.stringify(rallies):next?JSON.stringify(current)!==JSON.stringify(next):current?.status!=='unavailable';
        if(!changed)return;
        const scroller=dialog.querySelector('.rally-list,.rally-detail-scroll'),top=scroller?.scrollTop||0,focus=document.activeElement;
        const focusAction=focus?.dataset.action,focusId=focus?.dataset.id;
        const focusMember=focus?.closest('.rally-member')?.dataset.playerId;
        const expanded=new Set([...dialog.querySelectorAll('.rally-member[open]')].map(el=>el.dataset.playerId));
        if(list)renderList();else{normalize(next||{...current,status:'unavailable'});renderDetail(expanded);}
        const updated=dialog.querySelector('.rally-list,.rally-detail-scroll');if(updated)updated.scrollTop=top;
        if(focusAction){const button=[...dialog.querySelectorAll('[data-action]')].find(el=>el.dataset.action===focusAction&&el.dataset.id===focusId);button?.focus({preventScroll:true});}
        else if(focusMember)dialog.querySelector('.rally-member[data-player-id="'+Number(focusMember)+'"]>summary')?.focus({preventScroll:true});
    }
    function updateTime(){
        const dialog=document.querySelector('#game-dialog');if(!dialog?.open)return;
        dialog.querySelectorAll('[data-action="rally-join"]').forEach(b=>{const r=dialog.querySelector('.rally-detail')&&Number(current?.id)===Number(b.dataset.id)?current:rallies.find(r=>Number(r.id)===Number(b.dataset.id));if(r){const open=joinState(r)==='open',label=joinLabel(r);b.disabled=!open;b.classList.toggle('is-available',open);b.setAttribute('aria-label',label);b.title=label;const caption=b.querySelector('.rally-join-label');if(caption)caption.textContent=open&&b.classList.contains('is-compact')?text('rally.join.short','Join'):label;const plus=b.querySelector('.rally-join-plus');if(plus)plus.hidden=!open;}});
        if(current?.launch_at&&date(current.launch_at)<=now())dialog.querySelectorAll('[data-action="rally-launch"],[data-action="rally-cancel"]').forEach(b=>b.disabled=true);
    }
    function onClick(act,b){
        if(act==='rally-list'){list();return true;}
        if(act==='rally-detail'){detail(b.dataset.id);return true;}
        if(act==='rally-join'){if(!b.disabled)detail(b.dataset.id,true);return true;}
        if(act==='rally-launch'||act==='rally-cancel'){
            if(pending||b.disabled)return true;pending=true;b.disabled=true;
            const valid=requestContext();
            action('rally/'+Number(b.dataset.id)+'/'+(act==='rally-launch'?'launch':'cancel'),{},act==='rally-launch'?text('rally.updated','Rally updated.'):text('rally.cancelled','Rally cancelled.')).then(result=>{if(result){window.dispatchEvent(new CustomEvent('conquer-rally-updated'));if(valid())list();}}).finally(()=>{pending=false;if(b.isConnected)b.disabled=false;});return true;
        }
        return false;
    }
    return {onClick,list,sync,updateTime};
};

// Scenery is decorative; occupied tiles and marching armies come from game state.
window.ConquerWorld = (() => {
  'use strict';
  const sceneryImages=new Map();
  function loadScenery(){for(const name of ['pine','oak','cherry','mountain','rocks']){if(sceneryImages.has(name))continue;const img=new Image();sceneryImages.set(name,img);img.onload=()=>{if(view?.el.isConnected){view.terrainStamp=null;view.cameraDirty=true;}};img.src=`${context.base}/assets/art/map/scenery-${name}.png?v=fantasy3d1`;}}
  const TILE = 44, SIZE = 256, MIN_ZOOM = .65, MAX_ZOOM = 1.8;
  const memory = {x:null,y:null,zoom:1,filter:'all',search:'',selected:null,cell:null,panel:null,searchCategory:'solo',searchLevels:{},searchRun:null};
  const searchCategories=[['solo','Monster','orc'],['rally','Rally','monsters/grumwald'],['food','Nahrung','farm'],['lumber','Holz','lumber'],['stone','Stein','quarry'],['gold','Gold','gold'],['gems','Kristalle','crystal']];
  let searchCatalog=null,searchCatalogRequest=null,searchSequence=0;
  const searchKey=()=>JSON.stringify([context.state.city.world_id,context.state.city.id,context.state.city.coord_x,context.state.city.coord_y,memory.searchCategory,memory.searchLevels[memory.searchCategory]]);
  function searchArtwork(key,art){return ['solo','rally'].includes(key)?`${context.base}/assets/art/${art}.png`:window.ConquerWorldEncounters?.image(context.base,art,true)||asset(art);}
  function searchMarkup(){return `<form class="atlas-object-search" aria-label="Objekte auf der Weltkarte suchen"><div class="atlas-search-categories" role="group" aria-label="Objekttyp auswählen">${searchCategories.map(([key,label,art])=>`<button type="button" class="atlas-search-category" data-search-category="${key}" aria-pressed="${memory.searchCategory===key}"><img src="${escape(searchArtwork(key,art))}" alt=""><span>${label}</span></button>`).join('')}</div><div class="atlas-search-controls"><div class="atlas-search-level-heading"><label for="atlas-object-level">Stufe</label><span class="atlas-search-level-bounds"></span></div><div class="atlas-search-level-row"><button type="button" data-atlas="level-down" aria-label="Stufe verringern">${icon('minus')}</button><input id="atlas-object-level" type="range" min="0" max="0" value="0" aria-label="Objektstufe"><output class="atlas-search-level-value" for="atlas-object-level">–</output><button type="button" data-atlas="level-up" aria-label="Stufe erhöhen">${icon('plus')}</button></div></div><p class="atlas-search-hint">Nächstes verfügbares Ziel deiner Stadt</p><p class="atlas-search-feedback" role="status" aria-live="polite"></p><button type="submit" class="atlas-object-search-submit">${icon('search')}<span>Nächstes Ziel</span></button></form>`;}
  function updateSearchControls(){
    if(!view)return;
    const levels=searchCatalog?.[memory.searchCategory]||[],level=memory.searchLevels[memory.searchCategory];
    if(levels.length&&!levels.includes(level))memory.searchLevels[memory.searchCategory]=levels[0];
    const index=Math.max(0,levels.indexOf(memory.searchLevels[memory.searchCategory])),busy=!!view.searchBusy;
    const range=view.el.querySelector('#atlas-object-level');range.max=String(Math.max(0,levels.length-1));range.value=String(index);range.disabled=busy||levels.length<2;
    range.setAttribute('aria-valuetext',levels.length?`Stufe ${levels[index]}`:'Stufen werden geladen');
    range.style.setProperty('--search-progress',`${levels.length>1?index/(levels.length-1)*100:0}%`);
    view.el.querySelector('.atlas-search-level-value').textContent=levels[index]??'–';
    view.el.querySelector('.atlas-search-level-bounds').textContent=levels.length?`${levels[0]} – ${levels.at(-1)}`:'Wird geladen …';
    view.el.querySelector('[data-atlas="level-down"]').disabled=busy||!levels.length||index===0;
    view.el.querySelector('[data-atlas="level-up"]').disabled=busy||!levels.length||index===levels.length-1;
    view.el.querySelectorAll('[data-search-category]').forEach(button=>{button.setAttribute('aria-pressed',String(button.dataset.searchCategory===memory.searchCategory));});
    const submit=view.el.querySelector('.atlas-object-search-submit');submit.disabled=busy||(!levels.length&&!!searchCatalogRequest);submit.querySelector('span').textContent=busy?'Sucht …':levels.length?(memory.searchRun?.key===searchKey()&&memory.searchRun.cursor?'Weiter suchen':'Nächstes Ziel'):'Erneut laden';
    const next=view.el.querySelector('[data-atlas="search-next"]');if(next){next.disabled=busy;next.lastElementChild.textContent=busy?'Sucht …':'Weiter';}
    view.el.querySelector('.atlas-object-search').setAttribute('aria-busy',String(busy));
  }
  function searchFeedback(message,error=false){view.el.querySelector('.atlas-search-feedback').textContent=message;view.el.querySelector('.atlas-search-feedback').classList.toggle('is-error',error);}
  async function loadSearchCatalog(){
    if(searchCatalog){updateSearchControls();return;}
    if(!context.searchMap){searchFeedback('Die Kartensuche ist gerade nicht erreichbar.',true);return;}
    if(!searchCatalogRequest)searchCatalogRequest=context.searchMap();
    const mounted=view;updateSearchControls();
    try{const result=await searchCatalogRequest;searchCatalog=result.categories;if(view===mounted&&view.el.isConnected)searchFeedback('');}
    catch(error){if(view===mounted&&view.el.isConnected)searchFeedback(error.message,true);}
    finally{searchCatalogRequest=null;if(view===mounted&&view.el.isConnected)updateSearchControls();}
  }
  function changeSearchLevel(index){const levels=searchCatalog?.[memory.searchCategory]||[];if(!levels.length)return;const level=levels[clamp(index,0,levels.length-1)];if(level!==memory.searchLevels[memory.searchCategory])memory.searchRun=null;memory.searchLevels[memory.searchCategory]=level;searchFeedback('');updateSearchControls();}
  function cancelSearch(){searchSequence++;if(view){view.searchBusy=false;updateSearchControls();}}
  async function searchObjects(){
    if(view.searchBusy)return;
    if(!searchCatalog){await loadSearchCatalog();return;}
    const mounted=view,world=context.state.city.world_id,sequence=++searchSequence,category=memory.searchCategory,level=memory.searchLevels[category];
    const key=searchKey(),cursor=memory.searchRun?.key===key?memory.searchRun.cursor:null;
    view.searchBusy=true;searchFeedback('');updateSearchControls();
    try{
      const result=await context.searchMap({category,level,...(cursor?{cursor}:{})});
      if(view!==mounted||!view.el.isConnected||sequence!==searchSequence||!['search','actions'].includes(memory.panel)||key!==searchKey())return;
      if(!result.target){memory.searchRun=null;if(memory.panel!=='search')openPanel('search',view.el.querySelector('[data-atlas="search"]'));searchFeedback(`Kein freies Ziel für ${searchCategories.find(c=>c[0]===category)[1]} auf Stufe ${level} gefunden. Wähle eine andere Stufe oder suche später erneut.`);return;}
      const {kind,data}=result.target;
      if(Number(result.world_id)!==Number(world))throw new Error('Die Welt hat sich geändert. Bitte öffne die Suche erneut.');
      memory.filter='all';memory.search='';memory.searchRun={key,cursor:result.cursor,targetKey:`${kind}:${data.id}`};
      context.state[kind]=[...(context.state[kind]||[]).filter(row=>Number(row.id)!==Number(data.id)),data];
      view.searchTarget={kind,data};
      view.targets=targetsFromState(context.state);view.targetMap=new Map(view.targets.map(t=>[t.key,t]));updateMarkers();
      select(`${kind}:${data.id}`,true);
    }catch(error){if(view===mounted&&view.el.isConnected&&sequence===searchSequence){if(memory.panel!=='search')openPanel('search',view.el.querySelector('[data-atlas="search"]'));searchFeedback(error.message,true);}}
    finally{if(view===mounted&&sequence===searchSequence){view.searchBusy=false;updateSearchControls();}}
  }
  let view = null, context = null, frameId = 0, notifyTimer = 0, sceneVisible = true;
  const seenImpacts=new Set();
  const clamp = (v,a,b) => Math.max(a,Math.min(b,v));
  const number = v => Number.isFinite(Number(v)) ? Number(v) : 0;
  const format = v => Math.round(number(v)).toLocaleString('de-DE');
  const hash = (x,y,s=0) => {let n=Math.imul(x+137,374761393)^Math.imul(y+419,668265263)^Math.imul(s+17,1274126177);n=Math.imul(n^(n>>>13),1274126177);return ((n^(n>>>16))>>>0)/4294967295;};
  const scale = () => TILE * memory.zoom;
  const project = (x,y) => [(number(x)-memory.x)*scale()+view.width/2,(number(y)-memory.y)*scale()+view.height/2];
  const unproject = (x,y) => [memory.x+(x-view.width/2)/scale(),memory.y+(y-view.height/2)/scale()];
  const stamp = t => {const value=String(t||'');return Date.parse(value.includes('T')?value:value.replace(' ','T')+'Z');};
  const escape = value => context.esc(String(value??''));
  const asset = name => `${context.base}/assets/art/map/${name}.svg`;
  const motionPreference=matchMedia('(prefers-reduced-motion: reduce)');
  motionPreference.addEventListener('change',()=>{if(view?.el.isConnected)render(context);});
  const castleArt=skin=>motionPreference.matches||document.body.classList.contains('reduced-motion')?window.ConquerCastleSkins.image(context.base,skin):window.ConquerCastleSkins.motionImage(context.base,skin);
  const motionReduced=()=>motionPreference.matches||document.body.classList.contains('reduced-motion')||document.hidden;
  // Explicit monster artwork (including regional bosses) takes precedence over generic animated units.
  const lifeKind=target=>target.kind==='nodes'&&window.ConquerWorldEncounters?.supports(target.resource?.art)?target.resource.art:target.kind==='monsters'&&!target.data.definition?.art&&window.ConquerWorldEncounters?.supports(target.artKey)?target.artKey:null;
  const targetImage=(target,still=motionReduced())=>window.ConquerWorldEncounters?.image(context.base,lifeKind(target),still,target.biome?.id)||target.art;
  const encounterImage=(target,still=motionReduced())=>`<img src="${escape(targetImage(target,still))}" ${lifeKind(target)?`data-life-kind="${lifeKind(target)}"`:''} alt="">`;
  let lastMotionReduced=motionReduced();
  function syncMotion(){const slow=motionReduced();if(slow===lastMotionReduced)return;lastMotionReduced=slow;if(view?.el.isConnected){view.targets=targetsFromState(context.state);view.targetMap=new Map(view.targets.map(target=>[target.key,target]));updateMarkers();updateShrineNavigation();view.detailStamp='';updateSidebar();}}
  function setMarchSkinSource(actor,source,kind='still'){
    if(!source||actor.dataset.skinSource===source)return;
    actor.dataset.skinSource=source;actor.dataset.skinSourceKind=kind;
    const image=actor.querySelector('.atlas-party-skin img');
    // Articulated WebP frames already contain the complete gait. Prevent the
    // legacy whole-image gait from being layered over them.
    image.style.animation=kind==='motion'?'none':'';image.src=source;
  }
  document.addEventListener('visibilitychange',()=>{if(view){view.clockFrame=undefined;view.clockCorrection=0;for(const actor of view.partyNodes.values()){actor.classList.remove('is-moving');if(actor.travel)actor.travel.observed=false;if(document.hidden&&actor.classList.contains('has-motion-animation'))setMarchSkinSource(actor,window.ConquerMarchSkins.image(context.base,actor.dataset.marchSkin),'still');}}syncMotion();setVisible(sceneVisible);});
  new MutationObserver(syncMotion).observe(document.body,{attributes:true,attributeFilter:['class']});
  const shrineElements={forest:{label:'Wald',color:'#79bd71',aliases:'Wald Smaragdwald forest SHRINE_FOREST'},ice:{label:'Eis',color:'#c0edf8',aliases:'Eis Frost Frostlande ice SHRINE_ICE'},sand:{label:'Sand',color:'#f3c878',aliases:'Sand Wüste Sonnendünen sand SHRINE_SAND'},lava:{label:'Lava',color:'#ff8a65',aliases:'Lava Feuer Aschenlande lava SHRINE_LAVA'}};
  const shrineElement = shrine => String(shrine.element||shrine.shrine_code||shrine.art_key||'').replace(/^SHRINE_/i,'').toLowerCase();
  const shrineOwner = target => target.data.alliance_tag?`[${target.data.alliance_tag}]`:target.data.alliance_name||'Frei';
  function shrineStatus(target,detailed=false){
    const event=target.data.event,owner=shrineOwner(target);if(!event)return owner;
    const state=event.active?'Umkämpft':'Geschlossen';if(!detailed)return `${owner} · ${state}`;
    const when=stamp(event.active?event.ends_at:event.next_starts_at),time=Number.isFinite(when)?new Date(when).toLocaleString('de-DE',{day:'2-digit',month:'2-digit',hour:'2-digit',minute:'2-digit'}):null;
    return `${owner} · ${event.name||'Schreinereignis'} · ${state}${time?` · ${event.active?'bis':'ab'} ${time}`:''}`;
  }
  const isVillage = target => target.kind==='home'||target.kind==='players';
  const isCompactTarget = target => isVillage(target)||target.kind==='monsters'||target.kind==='nodes'||target.kind==='charms'||target.kind==='alliance_center'||target.kind==='outpost';
  const isRegionalBoss = target => target.kind==='monsters'&&target.data.definition?.type==='rally'&&target.data.definition?.art?.startsWith('monsters/');
  // Feet in each original illustration, after object-fit:contain in the 3.6 x
  // 3.8 tile portrait. Wide Sandmaul has much more vertical transparent margin.
  const bossContactY={grumwald:.60,frostgrimm:.80,sandmaul:.14,glutramm:.42,daemmerhorn:.92};
  const footprint = target => target.kind==='congress'?7:target.kind==='alliance_center'?5:target.kind==='outpost'?3:isVillage(target)?3:target.kind==='cell'&&context?.teleport?4:target.kind==='shrine'?6:target.kind==='monsters'&&(target.data.definition?.type==='rally'||Number(target.data.definition?.footprint)===2)?2:1;
  // Villages use a compact 3 × 3 presentation without shrinking their castle art. Rally monsters occupy 2 × 2.
  // Shrines span anchor -2 through +3; even-sized footprints are centered half a tile past the anchor.
  // Even-sized footprints have their visual center half a tile past the anchor.
  const targetCenter = target => footprint(target)%2===0?[target.x+.5,target.y+.5]:[target.x,target.y];
  const placementFootprint = target => isVillage(target)?4:footprint(target);
  const placementCenter = target => isVillage(target)?[target.x+.5,target.y+.5]:targetCenter(target);
  const projectTarget = target => project(...targetCenter(target));
  const selectedTarget = () => memory.cell?{key:`cell:${memory.cell.x}:${memory.cell.y}`,kind:'cell',x:memory.cell.x,y:memory.cell.y,name:'Freies Feld',data:{}}:view.targetMap.get(memory.selected);
  function targetAt(x,y){
    // Older worlds can contain resources inside a later enlarged city footprint.
    // Keep that real target accessible until the server relocates the collision.
    return view.targets.filter(t=>Math.abs(targetCenter(t)[0]-x)<footprint(t)/2&&Math.abs(targetCenter(t)[1]-y)<footprint(t)/2).sort((a,b)=>footprint(a)-footprint(b)||Math.hypot(a.x-x,a.y-y)-Math.hypot(b.x-x,b.y-y))[0];
  }
  function teleportPlacement(x,y){
    const candidate={x:Math.round(x),y:Math.round(y)},center=[candidate.x+.5,candidate.y+.5];
    if(candidate.x<1||candidate.y<1||candidate.x>253||candidate.y>253)return {...candidate,valid:false,reason:'Der vollständige 4 × 4-Platz muss innerhalb der Welt liegen.'};
    const distance=Math.max(Math.abs(candidate.x-number(context.teleport.origin_x)),Math.abs(candidate.y-number(context.teleport.origin_y)));
    if(distance<4)return {...candidate,valid:false,reason:'Wähle einen Platz außerhalb deines jetzigen Dorfes.'};
    const centers=context.teleport.alliance_centers||[],alliance=context.teleport.mode==='alliance';
    if(alliance&&!centers.some(city=>Math.max(Math.abs(candidate.x-number(city.x)),Math.abs(candidate.y-number(city.y)))<=number(context.teleport.max_distance||12)))return {...candidate,valid:false,reason:'Dieser Platz liegt außerhalb des Allianzgebiets.'};
    const collision=view.targets.some(target=>target.kind!=='home'&&Math.abs(placementCenter(target)[0]-center[0])<(placementFootprint(target)+4)/2&&Math.abs(placementCenter(target)[1]-center[1])<(placementFootprint(target)+4)/2);
    if(collision)return {...candidate,valid:false,reason:'Der 4 × 4-Platz wird von einem anderen Ziel blockiert.'};
    for(let ty=candidate.y-1;ty<=candidate.y+2;ty++)for(let tx=candidate.x-1;tx<=candidate.x+2;tx++)if(window.ConquerLandscape.waterAt(tx,ty))return {...candidate,valid:false,reason:'Das Dorf kann nicht auf Wasser stehen.'};
    return {...candidate,valid:true,reason:'Freier 4 × 4-Platz. Ziehen zum Verschieben.'};
  }
  function chooseTeleportCell(x,y,focusAction=true){
    const placement=teleportPlacement(x,y);stopFollowing();memory.selected=null;memory.cell={x:clamp(placement.x,1,253),y:clamp(placement.y,1,253)};memory.panel='actions';view.returnFocus=view.viewport;updateMarkers();updateSidebar();paint();
    if(focusAction)view.el.querySelector('.atlas-target-actions button')?.focus({preventScroll:true});
  }
  function selectCell(x,y){
    x=Math.round(x);y=Math.round(y);if(x<0||y<0||x>=SIZE||y>=SIZE)return;
    if(context.teleport){chooseTeleportCell(x,y);return;}
    stopFollowing();
    const target=targetAt(x,y);if(target){select(target.key);return;}
    if(memory.panel==='actions'){clearSelection(false);view.viewport.focus({preventScroll:true});return;}
    memory.selected=null;memory.cell={x,y};memory.panel='actions';view.returnFocus=view.viewport;updateMarkers();updateSidebar();paint();view.el.querySelector('.atlas-target-actions button')?.focus({preventScroll:true});
  }
  const resources = {1:{name:'Getreidehof',kind:'food',word:'Nahrung',art:'farm'},2:{name:'Holzfällerlager',kind:'lumber',word:'Holz',art:'lumber'},3:{name:'Steinbruch',kind:'stone',word:'Stein',art:'quarry'},4:{name:'Goldmine',kind:'gold',word:'Gold',art:'gold'},5:{name:'Kristallader',kind:'gems',word:'Kristalle',art:'crystal'}};
  function nodeStatus(node){
    if(node.is_own_gathering&&node.gathering_finishes_at){
      const seconds=Math.max(0,Math.ceil((stamp(node.gathering_finishes_at)-context.now())/1000));
      if(!Number.isFinite(seconds))return 'Sammelt · Zeit offen';
      if(seconds===0)return 'Rückkehr wird bestätigt';
      const minutes=Math.floor(seconds/60),time=seconds>=3600?`${Math.floor(seconds/3600)}:${String(minutes%60).padStart(2,'0')}:${String(seconds%60).padStart(2,'0')}`:`${minutes}:${String(seconds%60).padStart(2,'0')}`;
      return `Sammelt · ${time}`;
    }
    return node.gatherer_march_id?`${node.can_attack?'Feindlich':'Allianz'} · ${node.gatherer_name||'Besetzt'}`:'';
  }
  function updateGatheringTimers(){
    for(const target of view.targets){
      if(target.kind!=='nodes')continue;const button=view.markerNodes.get(target.key);if(!button)continue;
      let badge=button.querySelector('.atlas-gathering-badge');
      if(!badge){badge=document.createElement('span');badge.className='atlas-gathering-badge';button.append(badge);}
      const text=nodeStatus(target.data);badge.hidden=!text;badge.textContent=text;
      badge.classList.toggle('is-own',!!target.data.is_own_gathering);badge.classList.toggle('is-enemy',!!target.data.can_attack);
      if(text)button.setAttribute('aria-label',`${target.name}, X ${target.x}, Y ${target.y}. ${text}`);
    }
    const selected=selectedTarget();
    if(selected?.kind==='nodes')view.el.querySelectorAll('.atlas-node-status').forEach(status=>{status.textContent=nodeStatus(selected.data);});
  }
  function icon(name) {
    const paths={home:'M3 11 12 3l9 8M5 10v11h5v-7h4v7h5V10',compass:'m16 8-3 5-5 3 3-5 5-3ZM12 2v2m0 16v2M2 12h2m16 0h2',search:'M10.5 18a7.5 7.5 0 1 0 0-15 7.5 7.5 0 0 0 0 15Zm5.5-2 6 6',close:'m6 6 12 12M6 18 18 6',plus:'M12 5v14M5 12h14',minus:'M5 12h14',flag:'M5 22V3m0 1c5-4 9 4 15 0v10c-6 4-10-4-15 0',sword:'m4 20 5-5m-3-3 6 6M9 15l3-7 9-5-5 9-7 3Z',resource:'m3 8 9-5 9 5v10l-9 5-9-5V8Zm0 0 9 5 9-5m-9 5v10',list:'M8 6h13M8 12h13M8 18h13M3 6h1M3 12h1M3 18h1',arrow:'M4 12h16m-7-7 7 7-7 7',profile:'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm-7 9c.7-4 3-6 7-6s6.3 2 7 6',debuff:'M7 3h10M9 3v4l-4 8a4 4 0 0 0 3.5 6h7a4 4 0 0 0 3.5-6l-4-8V3M8 15c2-2 6 2 8 0',scout:'M3 12s3.5-6 9-6 9 6 9 6-3.5 6-9 6-9-6-9-6Zm9 3a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z',skin:'m4 7 5-4c1 2 5 2 6 0l5 4-3 5-2-1v10H9V11l-2 1-3-5Z'};
    return `<svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="${paths[name]||paths.compass}"/></svg>`;
  }
  function monsterName(monster) {
    const original=monster.definition?.name||'Waldkreatur';
    if(/skeleton/i.test(original))return 'Skeletttrupp';
    if(/golem/i.test(original))return 'Steingolem';
    if(/orc/i.test(original))return 'Orktrupp';
    if(/goblin/i.test(original))return 'Schatzgoblin';
    return original;
  }
  const charmCategories={construction_speed:'Baugeschwindigkeit',construction:'Baugeschwindigkeit',research_speed:'Forschungsgeschwindigkeit',research:'Forschungsgeschwindigkeit',troop_hp:'Truppen-LP',troops_hp:'Truppen-LP',troop_attack:'Truppenangriff',troops_attack:'Truppenangriff',troop_defense:'Truppenverteidigung',troops_defense:'Truppenverteidigung',carry_capacity:'Traglast',carry:'Traglast',march_speed:'Marschtempo',gathering_speed:'Sammeltempo',gathering:'Sammeltempo'};
  const charmGrades={normal:'Normal',epic:'Episch',legendary:'Legendär'};
  const charmNames={construction_speed:'Bau-Charm',construction:'Bau-Charm',research_speed:'Forschungs-Charm',research:'Forschungs-Charm',troop_hp:'Lebenskraft-Charm',troops_hp:'Lebenskraft-Charm',troop_attack:'Angriffs-Charm',troops_attack:'Angriffs-Charm',troop_defense:'Verteidigungs-Charm',troops_defense:'Verteidigungs-Charm',carry_capacity:'Traglast-Charm',carry:'Traglast-Charm',march_speed:'Marschtempo-Charm',gathering_speed:'Sammeltempo-Charm',gathering:'Sammeltempo-Charm'};
  const charmName=charm=>charmNames[charm.stat_category]||'Magischer Charm';
  function monsterVisualKey(monster){
    const source=`${monster.definition?.art||''} ${monster.definition?.name||''} ${monster.definition?.title||''}`.toLowerCase();
    if(/daemmerhorn|dämmerhorn/.test(source))return 'daemmerhorn';if(/grumwald/.test(source))return 'grumwald';if(/frostgrimm/.test(source))return 'frostgrimm';if(/sandmaul/.test(source))return 'sandmaul';if(/glutramm/.test(source))return 'glutramm';
    if(/dragon/.test(source))return 'dragon';if(/deathkar/.test(source))return 'deathkar';if(/magdar/.test(source))return 'magdar';if(/goblin/.test(source))return 'goblin';
    if(/skeleton/.test(source))return 'skeleton';if(/golem/.test(source))return 'golem';return 'orc';
  }
  function targetsFromState(state) {
    const city=state.city;
    const targets=[{key:'home',kind:'home',id:city.id,x:number(city.coord_x),y:number(city.coord_y),name:city.name&&!/^(Deine Stadt|.*['’]s City)$/.test(city.name)?city.name:state.player?.name||'Deine Stadt',level:number(city.castle_level)||1,art:castleArt(city.city_skin),data:city}];
    for(const m of state.monsters||[])if(['solo','rally'].includes(m.definition?.type)){const sourceArt=context.monsterArt(m),artKey=monsterVisualKey(m);targets.push({key:`monsters:${m.id}`,kind:'monsters',id:m.id,x:number(m.coord_x),y:number(m.coord_y),name:monsterName(m)+(m.definition?.type==='rally'?' · Rally':''),level:number(m.definition?.level)||1,art:`${context.base}/assets/art/${sourceArt}.png`,artKey,data:m});}
    for(const n of state.nodes||[]){const r=resources[n.object_type]||resources[1];targets.push({key:`nodes:${n.id}`,kind:'nodes',id:n.id,x:number(n.coord_x),y:number(n.coord_y),name:r.name,level:number(n.level)||1,art:asset(r.art),resource:r,data:n});}
    for(const charm of state.charms||[]){const x=number(charm.x??charm.coord_x),y=number(charm.y??charm.coord_y);targets.push({key:`charms:${charm.id}`,kind:'charms',id:Number(charm.id),x,y,name:charmName(charm),level:{normal:1,epic:2,legendary:3}[charm.grade]||1,art:`${context.base}/assets/art/map/runes-v1/${['normal','epic','legendary'].includes(charm.grade)?charm.grade:'normal'}.webp`,data:{...charm,coord_x:x,coord_y:y}});}
    for(const p of state.players||[])targets.push({key:`players:${p.id}`,kind:'players',id:p.id,x:number(p.coord_x),y:number(p.coord_y),name:p.display_name||p.username||'Siedlung',level:number(p.castle_level)||1,art:castleArt(p.city_skin),data:p});
    if(state.congress){const g=state.congress;targets.push({key:"congress",kind:"congress",id:g.id,x:number(g.coord_x),y:number(g.coord_y),name:g.name||"Kongress",level:1,art:`${context.base}/assets/art/map/congress.${motionPreference.matches||document.body.classList.contains("reduced-motion")?"png":"webp"}?v=zones1`,data:g});}
    for(const g of state.shrines||[]){const element=shrineElement(g);if(!shrineElements[element]||!Number.isFinite(Number(g.coord_x))||!Number.isFinite(Number(g.coord_y)))continue;targets.push({key:`shrine:${g.id}`,kind:'shrine',id:g.id,element,x:number(g.coord_x),y:number(g.coord_y),name:g.name||`${shrineElements[element].label}schrein`,art:`${context.base}/assets/art/map/shrine-${element}.${motionPreference.matches||document.body.classList.contains('reduced-motion')?'png':'webp'}?v=shrines1`,data:g});}
    for(const g of state.alliance_structures||[]){const kind=g.structure_type==='center'?'alliance_center':'outpost';targets.push({key:`${kind}:${g.id}`,kind,id:g.id,x:number(g.coord_x),y:number(g.coord_y),name:g.name||(`${g.alliance_tag?'['+g.alliance_tag+'] ':''}${kind==='alliance_center'?'Allianzzentrum':'Außenposten'}`),level:1,art:asset(kind==='alliance_center'?'alliance-center':'alliance-outpost'),data:g});}
    for(const target of targets){const [x,y]=targetCenter(target);target.biome=window.ConquerLandscape.biomeAt(x,y);}
    return targets;
  }
  function matching(target) {
    if(target.kind==='home'||target.kind==='congress')return true;
    if(memory.filter!=='all'&&target.kind!==memory.filter&&target.resource?.kind!==memory.filter)return false;
    const haystack=`${target.name} ${target.resource?.word||''} ${target.kind==='charms'?'Charm Talisman '+(charmCategories[target.data.stat_category]||target.data.stat_category||''):''} ${target.kind==='shrine'?'Schrein shrine '+shrineElements[target.element].aliases:''} ${target.data.alliance_tag||''} ${target.data.alliance_name||''} ${target.x} ${target.y}`.toLocaleLowerCase('de-DE');
    return !memory.search||haystack.includes(memory.search.toLocaleLowerCase('de-DE'));
  }
  function render(options) {
    context=options;loadScenery();
    if(memory.x===null){memory.x=number(options.state.city.coord_x);memory.y=number(options.state.city.coord_y);}
    if(!view||!view.el.isConnected||view.host!==options.host)mount(options.host);
    const worldKey=options.state.world?.id||options.state.city.world_id;if(view.worldKey&&view.worldKey!==worldKey){stopFollowing();cancelSearch();view.searchTarget=null;memory.panel=null;}view.worldKey=worldKey;
    // A poll started before the search may still contain the previous viewport.
    if(view.searchTarget){const {kind,data}=view.searchTarget,center=options.state.map_center;
      if(center&&Math.abs(center.x-data.coord_x)<=1&&Math.abs(center.y-data.coord_y)<=1)view.searchTarget=null;
      else if(!(options.state[kind]||[]).some(row=>Number(row.id)===Number(data.id)))options.state[kind]=[...(options.state[kind]||[]),data];
    }
    view.targets=targetsFromState(options.state);view.targetMap=new Map(view.targets.map(t=>[t.key,t]));
    updateMarkers();updateShrineNavigation();updateSidebar();paint();
    if(sceneVisible&&!frameId)frameId=requestAnimationFrame(animate);
  }
  function mount(host) {
    if(view){view.observer?.disconnect();view.marchHud?.destroy();for(const effect of view.impacts||[]){effect.dispose?.();effect.node?.remove();}cancelAnimationFrame(frameId);frameId=0;}
    memory.panel=null;memory.selected=null;memory.cell=null;
    const panelClose='<button class="map-overlay-close" data-atlas="clear" aria-label="Kartenfenster schließen" title="Schließen">'+icon('close')+'</button>';
    host.innerHTML=`<section class="atlas-shell map-overlay-shell">
      <div class="atlas-viewport" tabindex="0" role="region" aria-label="Weltkarte mit quadratischen Feldern. Ziehen oder mit Pfeiltasten bewegen. Eingabe wählt das mittlere Feld. Plus und Minus zoomen, Pos1 führt zur eigenen Stadt."><canvas class="atlas-terrain" aria-hidden="true"></canvas><span class="atlas-biome-label" aria-hidden="true"></span><div class="atlas-cell-focus" hidden aria-hidden="true"><img class="atlas-teleport-city-preview" alt=""><span class="atlas-teleport-placement-state"></span><i class="atlas-teleport-drag-cue">↕ Ziehen</i></div><svg class="atlas-routes" aria-hidden="true"></svg><div class="atlas-markers"></div><div class="atlas-parties"></div><div class="atlas-vignette" aria-hidden="true"></div></div>
      <button class="map-overlay-search-toggle" data-atlas="search" aria-label="Kartensuche öffnen" aria-controls="atlas-search-panel" aria-expanded="false" title="Kartensuche"><span class="map-overlay-scroll" aria-hidden="true">${icon('search')}</span><i aria-hidden="true"></i></button>
      <div class="map-overlay-coordinate-toggle" role="group" aria-label="Kartennavigation">
        <button type="button" class="map-overlay-home" data-atlas="home" aria-label="Zum eigenen Dorf springen" title="Zum eigenen Dorf springen">${icon('compass')}</button>
        <button type="button" class="map-overlay-navigation-toggle" data-atlas="navigation" aria-label="Koordinaten und Weltübersicht öffnen" aria-controls="atlas-navigation-panel" aria-expanded="false" title="Koordinaten · Weltübersicht"><span class="atlas-coordinates"></span><span class="map-overlay-globe" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="m3 6 6-3 6 3 6-3v15l-6 3-6-3-6 3V6Zm6-3v15m6-12v15"/></svg></span></button>
      </div>
      <button class="map-overlay-backdrop" data-atlas="clear" aria-label="Kartenfenster schließen" tabindex="-1" hidden></button>
      <aside class="atlas-teleport-guide" role="status" hidden><img src="${context.base}/assets/art/items/teleport.svg" alt=""><span><strong>Stadt versetzen</strong><small>Platz antippen · Vorschau ziehen</small></span><button type="button" data-action="teleport-cancel">Abbrechen</button></aside>
      <section id="atlas-search-panel" class="map-overlay-panel map-overlay-search-panel" role="region" aria-label="Kartensuche in der Nähe" hidden><div class="map-overlay-heading"><h2>In der Nähe</h2><button class="map-search-collapse" data-atlas="clear" aria-label="Kartensuche einklappen" title="Einklappen"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 15 7-7 7 7"/></svg></button></div>${searchMarkup()}</section>
      <section id="atlas-navigation-panel" class="map-overlay-panel map-overlay-navigation-panel" role="dialog" aria-label="Koordinaten und Weltübersicht" hidden><div class="map-overlay-heading"><h2>Weltübersicht</h2>${panelClose}</div><form class="atlas-jump" aria-label="Zu Koordinaten springen"><label>X<input name="x" type="number" min="0" max="255" step="1" required aria-label="X-Koordinate" value="${Math.round(memory.x)}"></label><label>Y<input name="y" type="number" min="0" max="255" step="1" required aria-label="Y-Koordinate" value="${Math.round(memory.y)}"></label><button type="submit" title="Koordinaten ansteuern" aria-label="Zu diesen Koordinaten springen">${icon('arrow')}</button></form><div class="map-overlay-navigation-grid"><button class="atlas-minimap" aria-label="Weltübersicht. Klicken, um zu einer Region zu springen."><canvas width="1024" height="1024" aria-hidden="true"></canvas></button><div class="atlas-zoom"><button data-atlas="zoom-in" aria-label="Karte vergrößern" title="Vergrößern">${icon('plus')}</button><span class="atlas-zoom-value"></span><button data-atlas="zoom-out" aria-label="Karte verkleinern" title="Verkleinern">${icon('minus')}</button><button data-atlas="home" aria-label="Auf eigene Stadt zentrieren" title="Eigene Stadt">${icon('home')}</button></div></div><button class="atlas-land-overview" data-action="tab" data-id="land">▦ Landübersicht öffnen</button><div class="atlas-zone-jumps" aria-label="Weltregionen"><button data-atlas="zone" data-x="64" data-y="64">Wald</button><button data-atlas="zone" data-x="192" data-y="64">Eis</button><button data-atlas="zone" data-x="64" data-y="192">Sand</button><button data-atlas="zone" data-x="192" data-y="192">Lava</button><button data-atlas="zone" data-x="128" data-y="128" class="atlas-congress-jump">♛ Kongress · Weltensee</button></div><div class="map-overlay-state"><span class="atlas-status" aria-live="polite"></span><span class="atlas-march-count"></span></div></section>
      <div class="atlas-target-actions" role="group" aria-label="Zielaktionen" hidden></div>
      <section class="map-overlay-panel map-overlay-target-panel" role="dialog" aria-label="Zielinformationen" hidden><div class="atlas-detail"></div></section></section>`;
    const el=host.querySelector('.atlas-shell'),viewport=el.querySelector('.atlas-viewport');
    const atmosphere=document.createElement('canvas');atmosphere.className='atlas-atmosphere';atmosphere.setAttribute('aria-hidden','true');viewport.querySelector('.atlas-terrain').after(atmosphere);
    Object.assign(atmosphere.style,{position:'absolute',inset:'0',width:'100%',height:'100%',pointerEvents:'none',zIndex:'1'});
    const shrineNav=document.createElement('div');shrineNav.className='atlas-shrine-jumps atlas-zone-jumps';shrineNav.setAttribute('role','group');shrineNav.setAttribute('aria-label','Zu den vier Schreinen springen');shrineNav.hidden=true;el.querySelector('.atlas-zone-jumps').after(shrineNav);
    const creatures=document.createElement('div');creatures.className='atlas-markers atlas-creatures';el.querySelector('.atlas-parties').before(creatures);
    view={host,el,viewport,width:1,height:1,canvas:el.querySelector('.atlas-terrain'),ctx:el.querySelector('.atlas-terrain').getContext('2d'),mini:el.querySelector('.atlas-minimap canvas'),routes:el.querySelector('.atlas-routes'),markers:el.querySelector('.atlas-markers'),creatures,parties:el.querySelector('.atlas-parties'),markerNodes:new Map(),partyNodes:new Map(),impacts:[],shrineNav,shrineButtons:new Map(),targets:[],targetMap:new Map(),pointers:new Map(),drag:null,pinch:null,suppressUntil:0,detailStamp:'',nearStamp:'',lastAnimation:0,returnFocus:null};
    view.marchHud=window.ConquerMarchHud?.({host:el,getContext:()=>context,follow:followMarch,locate:locateMarchTarget,stop:stopFollowing,focus,getSelected:()=>view.followId||null,onLayout:()=>{view.followAnchor=null;}});
    el.querySelector('.atlas-object-search').addEventListener('submit',e=>{e.preventDefault();e.stopPropagation();searchObjects();});
    el.querySelector('#atlas-object-level').addEventListener('input',e=>changeSearchLevel(Number(e.target.value)));
    updateSearchControls();
    el.querySelector('.atlas-jump').addEventListener('submit',e=>{e.preventDefault();e.stopPropagation();if(!e.target.reportValidity())return;moveTo(number(e.target.elements.x.value),number(e.target.elements.y.value));clearSelection(false);viewport.focus({preventScroll:true});});
    el.addEventListener('click',handleClick);
    el.addEventListener('keydown',e=>{if(e.key==='Escape'&&memory.panel){e.preventDefault();e.stopPropagation();clearSelection();}});
    viewport.addEventListener('pointerdown',pointerDown);viewport.addEventListener('pointermove',pointerMove);viewport.addEventListener('pointerup',pointerUp);viewport.addEventListener('pointercancel',e=>pointerUp(e,true));
    viewport.addEventListener('click',e=>{if(performance.now()<view.suppressUntil&&!e.target.closest('.atlas-zoom,.atlas-minimap')){e.preventDefault();e.stopPropagation();}},true);
    viewport.addEventListener('wheel',e=>{e.preventDefault();const box=viewport.getBoundingClientRect();zoomTo(memory.zoom*(e.deltaY<0?1.12:1/1.12),e.clientX-box.left,e.clientY-box.top);},{passive:false});
    viewport.addEventListener('keydown',e=>{if(e.target!==viewport)return;const step=e.shiftKey?8:1;const keys={ArrowLeft:[-step,0],ArrowRight:[step,0],ArrowUp:[0,-step],ArrowDown:[0,step]};if(keys[e.key]){e.preventDefault();moveTo(memory.x+keys[e.key][0],memory.y+keys[e.key][1]);}else if(e.key==='Enter'||e.key===' '){e.preventDefault();selectCell(memory.x,memory.y);}else if(e.key==='+'||e.key==='='){e.preventDefault();zoomTo(memory.zoom*1.15);}else if(e.key==='-'){e.preventDefault();zoomTo(memory.zoom/1.15);}else if(e.key==='Home'){e.preventDefault();home();}else if(e.key==='Escape'){clearSelection();}});
    const mini=el.querySelector('.atlas-minimap');mini.addEventListener('pointerdown',e=>e.stopPropagation());mini.addEventListener('click',e=>{e.stopPropagation();if(e.detail===0)return;const box=view.mini.getBoundingClientRect();moveTo((e.clientX-box.left)/box.width*255,(e.clientY-box.top)/box.height*255);clearSelection();});mini.addEventListener('keydown',e=>{const shift={ArrowLeft:[-20,0],ArrowRight:[20,0],ArrowUp:[0,-20],ArrowDown:[0,20]}[e.key];if(shift){e.preventDefault();e.stopPropagation();moveTo(memory.x+shift[0],memory.y+shift[1]);}});
    view.atmosphere=atmosphere;view.atmosphereCtx=atmosphere.getContext('2d');view.ambienceTime=0;view.lastAmbience=null;
    view.observer=new ResizeObserver(()=>resize());view.observer.observe(viewport);resize();
  }
  function resize() {
    if(!view?.el.isConnected)return;
    view.width=Math.max(1,view.viewport.clientWidth);view.height=Math.max(1,view.viewport.clientHeight);
    // A 2x full-screen terrain canvas is expensive on phones and tablets and
    // brings little visible benefit while the map is moving. Keep desktop
    // sharp, but avoid rendering more than 2.25 physical pixels for each CSS
    // pixel's area on touch-sized viewports.
    const touchSized=view.width<900&&matchMedia('(pointer: coarse)').matches;
    const ratio=Math.min(window.devicePixelRatio||1,touchSized?1.5:2);view.terrainPad=view.width<700?96:144;view.canvas.width=Math.round((view.width+view.terrainPad*2)*ratio);view.canvas.height=Math.round((view.height+view.terrainPad*2)*ratio);view.ctx.setTransform(ratio,0,0,ratio,0,0);
    Object.assign(view.canvas.style,{width:`${view.width+view.terrainPad*2}px`,height:`${view.height+view.terrainPad*2}px`,left:`${-view.terrainPad}px`,top:`${-view.terrainPad}px`});
    view.terrainStamp=null;
    view.atmosphere.width=Math.round(view.width*ratio);view.atmosphere.height=Math.round(view.height*ratio);view.atmosphereCtx.setTransform(ratio,0,0,ratio,0,0);view.followAnchor=null;paint();notify();
  }
  function handleClick(e) {
    const button=e.target.closest('button');if(!button)return;
    if(button.dataset.searchCategory){cancelSearch();if(memory.searchCategory!==button.dataset.searchCategory)memory.searchRun=null;memory.searchCategory=button.dataset.searchCategory;searchFeedback('');updateSearchControls();button.scrollIntoView({block:'nearest',inline:'nearest',behavior:'instant'});return;}
    if(button.dataset.followMarch){if(performance.now()>=view.suppressUntil)followMarch(button.dataset.followMarch);return;}
    if(button.dataset.atlasFilter){memory.filter=button.dataset.atlasFilter;view.el.querySelectorAll('[data-atlas-filter]').forEach(b=>b.setAttribute('aria-pressed',String(b.dataset.atlasFilter===memory.filter)));updateMarkers();updateSidebar();paint();return;}
    if(button.dataset.atlasTarget){if(performance.now()<view.suppressUntil)return;select(button.dataset.atlasTarget,button.classList.contains('atlas-nearby-row'),true);return;}
    if(button.dataset.action&&button.closest('.atlas-target-actions,.atlas-detail')){clearSelection(false);return;}
    const action=button.dataset.atlas;
    if(action==='search-next'){searchObjects();return;}
    if(action==='details'){memory.panel='target';updateSidebar();paint();view.el.querySelector('.atlas-detail .atlas-close')?.focus({preventScroll:true});return;}
    if(action==='zone'){moveTo(number(button.dataset.x),number(button.dataset.y));clearSelection();return;}
    if(action==='shrine'){const target=view.targetMap.get(button.dataset.targetKey);if(target){moveTo(...targetCenter(target));clearSelection(false);view.viewport.focus({preventScroll:true});}return;}
    if(action==='level-down'||action==='level-up'){const levels=searchCatalog?.[memory.searchCategory]||[];changeSearchLevel(levels.indexOf(memory.searchLevels[memory.searchCategory])+(action==='level-up'?1:-1));return;}
    if(action==='zoom-in')zoomTo(memory.zoom*1.15);
    else if(action==='zoom-out')zoomTo(memory.zoom/1.15);
    else if(action==='home'){home();clearSelection();}
    else if(action==='clear')clearSelection();
    else if(action==='search'||action==='navigation')openPanel(action,button);
  }
  function home(){moveTo(number(context.state.city.coord_x),number(context.state.city.coord_y));}
  function setMapHistory(panel){
    const hasEntry=history.state?.conquerMapSearch||history.state?.conquerMapTarget;
    const state={...history.state,conquerMapSearch:panel==='search',conquerMapTarget:panel==='target'};
    history[hasEntry?'replaceState':'pushState'](state,'',location.href);view.mapHistoryUrl=location.href;
  }
  function releaseSearchHistory(){
    if(!(history.state?.conquerMapSearch||history.state?.conquerMapTarget)||view.releasingMapHistory)return;
    if(view.mapHistoryUrl&&view.mapHistoryUrl!==location.href){const state={...history.state};delete state.conquerMapSearch;delete state.conquerMapTarget;history.replaceState(state,'',location.href);return;}
    view.releasingMapHistory=true;history.back();
  }
  window.addEventListener('popstate',()=>{
    if(!view?.el.isConnected)return;view.releasingMapHistory=false;
    if(view.pendingMapAction){const pending=view.pendingMapAction;view.pendingMapAction=null;pending();return;}
    if((memory.panel==='search'&&!history.state?.conquerMapSearch)||(['actions','target'].includes(memory.panel)&&['nodes','monsters'].includes(selectedTarget()?.kind)&&!history.state?.conquerMapTarget))clearSelection(true,false);
    else if(history.state?.conquerMapSearch&&memory.panel!=='search')openPanel('search',view.el.querySelector('[data-atlas="search"]'));
  });
  function clearSelection(restoreFocus=true,releaseHistory=true){cancelSearch();if(releaseHistory)releaseSearchHistory();stopFollowing();memory.selected=null;memory.cell=null;memory.panel=null;updateMarkers();updateSidebar();paint();if(restoreFocus){const target=view.returnFocus?.isConnected?view.returnFocus:view.viewport;target.focus({preventScroll:true});}view.returnFocus=null;}
  document.addEventListener('pointerdown',e=>{
    if(!view?.el.isConnected||memory.panel!=='actions'||e.target.closest('.atlas-target-actions,.atlas-viewport'))return;
    clearSelection(false);
  });
  function openPanel(panel,trigger){
    if(view.releasingMapHistory){view.pendingMapAction=()=>openPanel(panel,trigger);return;}
    stopFollowing();if(memory.panel===panel){clearSelection();return;}cancelSearch();memory.selected=null;memory.cell=null;memory.panel=panel;view.returnFocus=trigger;updateMarkers();updateSidebar();paint();
    if(panel==='search'){setMapHistory('search');searchFeedback('');loadSearchCatalog();}
    view.el.querySelector(panel==='search'?'.atlas-search-category[aria-pressed="true"]':'#atlas-navigation-panel .map-overlay-close').focus({preventScroll:true});
  }
  function select(key,center=false,directMonster=false){
    if(view.releasingMapHistory){view.pendingMapAction=()=>select(key,center,directMonster);return;}
    const target=view.targetMap.get(key);if(!target)return;
    if(directMonster&&target.kind==='monsters'&&typeof context.onMonsterAttack==='function'){
      cancelSearch();stopFollowing();view.objectActionFraming=null;memory.selected=null;memory.cell=null;memory.panel=null;view.returnFocus=view.markerNodes.get(key);updateMarkers();updateSidebar();paint();context.onMonsterAttack(target.id);return;
    }
    if(memory.panel==='actions'&&memory.selected===key){clearSelection(false);view.viewport.focus({preventScroll:true});return;}if(['nodes','monsters'].includes(target.kind))setMapHistory('target');else releaseSearchHistory();cancelSearch();stopFollowing();view.objectActionFraming=null;memory.selected=key;memory.cell=null;memory.panel='actions';view.returnFocus=view.markerNodes.get(key);if(center||isCompactTarget(target))moveTo(...targetCenter(target));updateMarkers();updateSidebar();paint();view.el.querySelector('.atlas-target-actions button')?.focus({preventScroll:true});
  }
  function notify(){clearTimeout(notifyTimer);notifyTimer=setTimeout(()=>{if(view?.el.isConnected)window.dispatchEvent(new Event('conquer-world-moved'));},380);}
  function moveTo(x,y){stopFollowing();memory.x=clamp(x,0,255);memory.y=clamp(y,0,255);paint();updateSidebar();notify();}
  function zoomTo(value,anchorX=view.width/2,anchorY=view.height/2){const before=unproject(anchorX,anchorY);memory.zoom=clamp(value,MIN_ZOOM,MAX_ZOOM);memory.x=clamp(before[0]-(anchorX-view.width/2)/scale(),0,255);memory.y=clamp(before[1]-(anchorY-view.height/2)/scale(),0,255);paint();updateSidebar();notify();}
  function pointerDown(e){
    if(e.button!==0||e.target.closest('.atlas-zoom,.atlas-minimap'))return;
    view.viewport.focus({preventScroll:true});view.pointers.set(e.pointerId,{x:e.clientX,y:e.clientY});view.viewport.setPointerCapture(e.pointerId);
    if(view.pointers.size===1){const box=view.viewport.getBoundingClientRect(),point=unproject(e.clientX-box.left,e.clientY-box.top),teleport=!!(context.teleport&&memory.cell&&e.target.closest('.atlas-cell-focus'));view.drag={x:e.clientX,y:e.clientY,startX:e.clientX,startY:e.clientY,moved:false,teleport,offsetX:teleport?point[0]-memory.cell.x:0,offsetY:teleport?point[1]-memory.cell.y:0,march:e.target.closest('[data-follow-march]')?.dataset.followMarch,target:e.target.closest('[data-atlas-target]')?.dataset.atlasTarget};}
    if(view.pointers.size===2){const points=[...view.pointers.values()];view.pinch={distance:Math.hypot(points[0].x-points[1].x,points[0].y-points[1].y),zoom:memory.zoom};if(view.drag)view.drag.moved=true;}
  }
  function pointerMove(e){
    if(!view.pointers.has(e.pointerId))return;
    e.preventDefault();view.pointers.set(e.pointerId,{x:e.clientX,y:e.clientY});
    if(view.pointers.size===2&&view.pinch){const points=[...view.pointers.values()],box=view.viewport.getBoundingClientRect(),distance=Math.hypot(points[0].x-points[1].x,points[0].y-points[1].y);zoomTo(view.pinch.zoom*distance/Math.max(1,view.pinch.distance),(points[0].x+points[1].x)/2-box.left,(points[0].y+points[1].y)/2-box.top);return;}
    const drag=view.drag;if(!drag)return;if(Math.hypot(e.clientX-drag.startX,e.clientY-drag.startY)>5)drag.moved=true;
    if(drag.moved&&drag.teleport){const box=view.viewport.getBoundingClientRect(),point=unproject(e.clientX-box.left,e.clientY-box.top);view.viewport.classList.add('is-teleport-dragging');chooseTeleportCell(point[0]-drag.offsetX,point[1]-drag.offsetY,false);drag.x=e.clientX;drag.y=e.clientY;return;}
    if(drag.moved){stopFollowing();if(memory.panel==='actions'&&!context.teleport)clearSelection(false);view.viewport.classList.add('is-dragging');memory.x=clamp(memory.x-(e.clientX-drag.x)/scale(),0,255);memory.y=clamp(memory.y-(e.clientY-drag.y)/scale(),0,255);panBufferedScene();notify();}drag.x=e.clientX;drag.y=e.clientY;
  }
  function pointerUp(e,cancelled=false){
    if(!view.pointers.has(e.pointerId))return;
    const drag=view.drag;view.pointers.delete(e.pointerId);if(view.viewport.hasPointerCapture(e.pointerId))view.viewport.releasePointerCapture(e.pointerId);
    if(view.pointers.size===0){view.viewport.classList.remove('is-dragging','is-teleport-dragging');view.suppressUntil=performance.now()+(drag?.moved?400:100);if(drag?.moved&&!drag.teleport)paint();if(!cancelled&&!drag?.moved&&!drag?.teleport){const box=view.viewport.getBoundingClientRect();if(context.teleport)selectCell(...unproject(e.clientX-box.left,e.clientY-box.top));else if(drag?.march)followMarch(drag.march);else if(drag?.target)select(drag.target,false,true);else selectCell(...unproject(e.clientX-box.left,e.clientY-box.top));}view.drag=null;view.pinch=null;}
    else {const point=[...view.pointers.values()][0];view.drag={x:point.x,y:point.y,startX:point.x,startY:point.y,moved:true,target:null};view.pinch=null;}
  }
  function updateMonsterHealth(button,target){
    const current=Number(target.data.hp_current),maximum=Number(target.data.hp_max);
    const injured=target.kind==='monsters'&&Number.isFinite(current)&&Number.isFinite(maximum)&&current>0&&current<maximum;
    let health=button.querySelector('.atlas-monster-health');
    button.classList.toggle('is-injured',injured);
    if(!injured){health?.remove();return;}
    if(!health){health=document.createElement('span');health.className='atlas-monster-health';health.setAttribute('aria-hidden','true');health.innerHTML='<span class="atlas-monster-hp-values"></span><span class="atlas-monster-hp-track"><span></span></span>';button.append(health);}
    health.querySelector('.atlas-monster-hp-values').textContent=`${format(current)} / ${format(maximum)}`;
    health.querySelector('.atlas-monster-hp-track>span').style.width=`${Math.max(0,Math.min(100,current/maximum*100))}%`;
    health.dataset.current=String(current);health.dataset.max=String(maximum);
    button.setAttribute('aria-label',`${button.getAttribute('aria-label')}, Lebenspunkte ${format(current)} von ${format(maximum)}`);
  }
  function updateMarkers(){
    const present=new Set();
    for(const target of view.targets){present.add(target.key);let button=view.markerNodes.get(target.key);
      if(!button){button=document.createElement('button');button.className=`atlas-marker atlas-marker--${target.kind}`;button.dataset.atlasTarget=target.key;button.innerHTML='<span class="atlas-marker-ground"></span><span class="castle-skin-effect" aria-hidden="true"></span><img alt="" draggable="false"><span class="atlas-marker-level"></span><span class="atlas-marker-name"></span>';(target.kind==='monsters'?view.creatures:view.markers).append(button);view.markerNodes.set(target.key,button);}
      const img=button.querySelector('img'),kind=lifeKind(target);if(kind)img.dataset.lifeKind=kind;else delete img.dataset.lifeKind;const src=targetImage(target);if(img.getAttribute('src')!==src)img.src=src;if(isVillage(target)){const skin=window.ConquerCastleSkins.get(target.data.city_skin);button.dataset.skin=skin.id;button.dataset.rarity=skin.rarity;button.style.setProperty('--skin-aura',skin.effectColor);}
      if(target.kind==='charms')button.dataset.grade=['normal','epic','legendary'].includes(target.data.grade)?target.data.grade:'normal';else delete button.dataset.grade;
      if(target.kind==='charms'&&!button.querySelector('.charm-rune-effects')){
        const fx=document.createElement('span');fx.className='charm-rune-effects';fx.setAttribute('aria-hidden','true');
        fx.innerHTML='<i class="charm-rune-base"></i><i class="charm-rune-ring"></i><i class="charm-rune-spark"></i><i class="charm-rune-spark"></i><i class="charm-rune-spark"></i>';
        button.insertBefore(fx,img);button.style.setProperty('--rune-delay',`${-(Number(target.id)%19)/5}s`);
      }
      if((target.kind==='shrine'||target.kind==='congress')&&!button.querySelector('.atlas-landmark-effects')){
        const fx=document.createElement('span');fx.className='atlas-landmark-effects';fx.setAttribute('aria-hidden','true');
        fx.innerHTML='<i class="landmark-halo"></i><i class="landmark-orbit"></i><i class="landmark-beacon"></i>'+Array.from({length:6},(_,n)=>`<i class="landmark-spark" style="--spark:${n}"></i>`).join('');button.insertBefore(fx,img);
      }
      const levelNode=button.querySelector('.atlas-marker-level');levelNode.textContent=target.kind==='shrine'?shrineStatus(target):target.kind==='congress'?(target.data.alliance_tag?'['+target.data.alliance_tag+']':'Neutral'):target.kind==='home'?'Deine Stadt':target.kind==='charms'?'':['alliance_center','outpost'].includes(target.kind)?`[${target.data.alliance_tag}] · R ${target.data.radius}`:`Lv. ${target.level}`;levelNode.toggleAttribute('aria-hidden',target.kind==='charms');const nameNode=button.querySelector('.atlas-marker-name');nameNode.textContent=target.name;if(isVillage(target))window.ConquerNameFrames?.apply(nameNode,window.ConquerNameFrames.resolve(target.data,target.data.city_skin));
      button.setAttribute('aria-label',`${target.name}${target.kind==='shrine'?`, ${shrineStatus(target,true)}`:target.kind==='charms'?`, ${charmGrades[target.data.grade]||'magisch'}, ${charmCategories[target.data.stat_category]||target.data.stat_category||'Bonus'} plus ${format(target.data.bonus_pct)} Prozent` :`, Stufe ${target.level}`}, X ${target.x}, Y ${target.y}, ${footprint(target)} mal ${footprint(target)} Felder${target.data.gatherer_march_id?', derzeit besetzt':''}`);
      updateMonsterHealth(button,target);
      if(target.kind==='shrine'){button.dataset.shrine=target.element;button.classList.toggle('is-event-active',!!target.data.event?.active);button.title=shrineStatus(target,true);}
      button.classList.toggle('is-regional-boss',isRegionalBoss(target));
      if(target.kind==='monsters')button.dataset.monsterType=target.data.definition?.type==='rally'?'rally':'solo';
      if(isRegionalBoss(target)){
        button.dataset.boss=target.artKey;button.dataset.biome=target.data.definition.biome||'arcane';
        button.style.setProperty('--boss-phase',`${-(number(target.id)%17)*.37}s`);
        if(!button.querySelector('.atlas-boss-effects')){const fx=document.createElement('span');fx.className='atlas-boss-effects';fx.setAttribute('aria-hidden','true');fx.innerHTML='<i class="boss-shadow"></i>'+Array.from({length:4},(_,n)=>`<i class="boss-mote" style="--mote:${n}"></i>`).join('');button.insertBefore(fx,img);}
      }
      button.dataset.groundBiome=target.biome.id;
      button.dataset.footprint=String(footprint(target));button.dataset.x=String(target.x);button.dataset.y=String(target.y);
      button.setAttribute('aria-pressed',String(target.key===memory.selected));button.classList.toggle('is-selected',target.key===memory.selected);button.classList.toggle('is-occupied',!!target.data.gatherer_march_id);button.dataset.filtered=String(target.kind!=='shrine'&&!matching(target));
    }
    for(const [key,node]of view.markerNodes)if(!present.has(key)){node.remove();view.markerNodes.delete(key);}
    positionMarkers();updateFooter();updateGatheringTimers();
  }
  function updateShrineNavigation(){
    const targets=view.targets.filter(t=>t.kind==='shrine').sort((a,b)=>Object.keys(shrineElements).indexOf(a.element)-Object.keys(shrineElements).indexOf(b.element)),present=new Set();
    view.shrineNav.hidden=targets.length===0;
    for(const target of targets){present.add(target.key);let button=view.shrineButtons.get(target.key);
      if(!button){button=document.createElement('button');button.className='atlas-shrine-jump';button.dataset.atlas='shrine';button.dataset.targetKey=target.key;button.innerHTML='<img alt="" draggable="false"><span></span>';view.shrineNav.append(button);view.shrineButtons.set(target.key,button);}
      button.dataset.shrine=target.element;button.dataset.id=target.id;button.classList.toggle('is-event-active',!!target.data.event?.active);button.querySelector('span').textContent=shrineElements[target.element].label;
      const image=button.querySelector('img');if(image.getAttribute('src')!==target.art)image.src=target.art;
      button.setAttribute('aria-label',`Zum ${target.name}, X ${target.x}, Y ${target.y}`);button.title=`${target.name} · ${shrineStatus(target,true)}`;
    }
    for(const [key,node]of view.shrineButtons)if(!present.has(key)){node.remove();view.shrineButtons.delete(key);}
  }
  function positionMarkers(){
    view.el.classList.toggle('encounters-paused',motionReduced());
    for(const target of view.targets){const node=view.markerNodes.get(target.key);if(!node)continue;const [x,y]=projectTarget(target),tiles=footprint(target),margin=scale()*Math.max(tiles/2+1,isRegionalBoss(target)?3.2:lifeKind(target)?2.2:0);node.hidden=node.dataset.filtered==='true'||x<-margin||y<-margin||x>view.width+margin||y>view.height+margin;const img=node.querySelector('img');if(lifeKind(target)){const src=targetImage(target,node.hidden||motionReduced());if(img.getAttribute('src')!==src)img.src=src;}node.style.left=`${x}px`;node.style.top=`${y}px`;node.style.setProperty('--tile-size',`${scale()}px`);node.style.setProperty('--footprint',String(tiles));node.style.zIndex=String((isVillage(target)?0:10000)+Math.round(y)+300);}
    const target=selectedTarget(),focus=view.el.querySelector('.atlas-cell-focus');focus.hidden=!target;focus.classList.toggle('is-teleport-preview',!!(context.teleport&&target?.kind==='cell'));if(target){const [x,y]=projectTarget(target),size=scale()*footprint(target);focus.style.left=`${x}px`;focus.style.top=`${y}px`;focus.style.width=focus.style.height=`${size}px`;focus.dataset.x=String(target.x);focus.dataset.y=String(target.y);if(context.teleport&&target.kind==='cell'){const placement=teleportPlacement(target.x,target.y),preview=focus.querySelector('.atlas-teleport-city-preview'),state=focus.querySelector('.atlas-teleport-placement-state');focus.dataset.valid=String(placement.valid);preview.src=window.ConquerCastleSkins.image(context.base,context.state.city.city_skin||'default');state.textContent=placement.valid?`X ${target.x} · Y ${target.y}`:placement.reason;}}
  }
  function targetActions(target){
    if(!target)return '';
    const button=(label,symbol,attributes,tone='')=>`<button ${attributes} class="atlas-action ${tone}"><span class="atlas-action-disc">${icon(symbol)}</span><span>${label}</span></button>`;
    let actions='';
    if(context.teleport&&target.kind==='cell'){
      const placement=teleportPlacement(target.x,target.y);
      actions=button(placement.valid?'Hierher teleportieren':'Platz nicht verfügbar','home',`data-action="teleport-confirm" data-x="${target.x}" data-y="${target.y}" ${placement.valid?'':'disabled'}`,'is-city');
    }
    if(target.kind==='home')actions=button('Profil','profile','aria-label="Profil" data-action="dialog-tab" data-id="profile"')+button('Skin','skin','aria-label="Skin" data-action="city-skins"','is-skin')+button('Dorfübersicht','home','aria-label="Dorfübersicht" data-action="tab" data-id="city"','is-city');
    else if(target.kind==='players')actions=button('Profil','profile',`data-action="public-profile" data-id="${escape(target.id)}"`)+button('Solo-Attacke','sword',`data-action="village-attack" data-id="${escape(target.id)}"`,'is-attack')+button('Debuff','debuff',`data-action="village-debuff" data-id="${escape(target.id)}"`,'is-debuff')+button('Rally','flag',`data-action="village-rally" data-id="${escape(target.id)}"`,'is-rally')+button('Spähen','scout',`data-action="village-scout" data-id="${escape(target.id)}" data-x="${target.x}" data-y="${target.y}"`,'is-scout');
    else if(target.kind==='charms')actions='';
    else if(!actions)actions=target.kind==='shrine'?button('Schrein','home',`data-action="shrine-open" data-id="${escape(target.id)}"`):target.kind==='congress'?button('Kongress','home','data-action="congress-open"'):button('Details','list','data-atlas="details"');
    if(target.kind==='shrine'&&target.data.can_attack)actions+=button('Angreifen','sword',`data-action="shrine-attack" data-id="${escape(target.id)}"`,'is-attack');
    if(target.kind==='congress'&&target.data.can_attack)actions+=button('Angreifen','sword','data-action="congress-attack"','is-attack');
    if(target.kind==='monsters')actions+=button(target.data.definition?.type==='rally'?'Rally starten':'Angreifen','sword',`data-action="expedition" data-kind="monsters" data-id="${escape(target.id)}"`,'is-attack');
    if(target.kind==='charms')actions+=button('Einsammeln','debuff',`data-action="expedition" data-kind="charms" data-id="${escape(target.id)}"`,'is-gather');
    if(target.kind==='nodes'){const n=target.data;actions+=n.is_own_gathering?button('Zurückrufen','home',`data-action="gather-recall" data-id="${escape(n.gatherer_march_id)}"`):button(n.can_attack?'Angreifen':n.gatherer_march_id?'Allianz · besetzt':'Sammeln',n.can_attack?'sword':'resource',`data-action="expedition" data-kind="${n.can_attack?'node-attack':'nodes'}" data-id="${escape(target.id)}" ${n.gatherer_march_id&&!n.can_attack?'disabled':''}`,n.can_attack?'is-attack':'is-gather');}
    if(!isVillage(target))actions+=button('Teilen','flag',`aria-label="Koordinaten teilen" data-action="share-coordinates" data-x="${target.x}" data-y="${target.y}" data-name="${escape(target.name)}"`);
    if(memory.searchRun?.key===searchKey()&&memory.searchRun.targetKey===target.key)actions+=button('Weiter','search','data-atlas="search-next" aria-label="Weiter suchen"','is-search-next');
    if(['nodes','monsters'].includes(target.kind)){
      const node=target.kind==='nodes',data=target.data,current=Math.max(0,number(node?data.resource_amount:data.hp_current)),maximum=Math.max(1,number(node?data.resource_max:(data.hp_max??data.definition?.hp??number(data.definition?.stats?.hp)*number(data.definition?.amount)))||current);
      const status=node?nodeStatus(data):data.definition?.type==='rally'?'Gemeinsam im Rally angreifen':'Solo-Monster';
      return `<div class="atlas-encounter-header"><strong>${escape(target.name)}</strong><button data-atlas="clear" aria-label="Zielmenü schließen">${icon('close')}</button></div><div class="atlas-encounter-summary">${encounterImage(target)}<div><strong>Stufe ${target.level}</strong><small>X ${target.x} · Y ${target.y}</small><small>${footprint(target)} × ${footprint(target)} ${footprint(target)===1?'Feld':'Felder'}</small></div></div><div class="atlas-encounter-stock"><span>${node?'Vorrat':'Lebenspunkte'}</span><strong>${format(current)} / ${format(maximum)}</strong><progress aria-label="${node?'Verbleibender Vorrat':'Lebenspunkte'}" value="${Math.min(current,maximum)}" max="${maximum}"></progress></div><p class="atlas-encounter-status">${escape(status)}</p><div class="atlas-actions-buttons">${actions}</div>`;
    }
    if(isCompactTarget(target)){
      const summary=target.kind==='charms'
        ? `<small class="atlas-charm-summary">X ${target.x} · Y ${target.y}</small>`
        : `<small>Lv. ${target.level} · X ${target.x} · Y ${target.y}</small>`;
      const close=target.kind==='charms'?'':`<button data-atlas="clear" aria-label="Zielmenü schließen">${icon('close')}</button>`;
      return `<div class="atlas-village-banner"><strong>${escape(target.name)}</strong>${summary}${close}</div><div class="atlas-actions-buttons">${actions}</div>`;
    }
    return `<div class="atlas-actions-heading"><span>${escape(target.name)} · ${target.x}, ${target.y}</span><button data-atlas="clear" aria-label="Zielmenü schließen">${icon('close')}</button></div>${target.kind==='shrine'?`<small class="atlas-actions-status" title="${escape(shrineStatus(target,true))}">${escape(shrineStatus(target))}</small>`:''}<div class="atlas-actions-buttons">${actions}</div>`;
  }
  function positionObjectActions(menu,target){
    const framing=`${target.key}:${view.width}:${view.height}:${memory.zoom}`;
    const reframe=view.objectActionFraming!==framing;
    if(reframe){
      view.objectActionFraming=framing;
      if(view.width<=620&&view.height<=520){
        // Leave a clear central lane for the side menu on very short screens.
        const centerX=targetCenter(target)[0],screenX=view.width*(centerX<SIZE/2?.32:.68),cameraX=clamp(centerX+(view.width/2-screenX)/scale(),0,SIZE-1);
        if(Math.abs(memory.x-cameraX)>.01){moveTo(cameraX,memory.y);return;}
      }
    }
    const banner=menu.querySelector('.atlas-village-banner'),buttons=menu.querySelector('.atlas-actions-buttons');
    const viewport=view.viewport.getBoundingClientRect(),marker=view.markerNodes.get(target.key),art=marker.querySelector('img').getBoundingClientRect(),tile=marker.getBoundingClientRect();
    // Protect the illustration and its selected tile, including the lifted hover pose.
    const object={left:Math.min(art.left,tile.left)-viewport.left,right:Math.max(art.right,tile.right)-viewport.left,top:Math.min(art.top,tile.top)-viewport.top-3,bottom:Math.max(art.bottom,tile.bottom)-viewport.top};
    const width=menu.offsetWidth,bannerHeight=banner.offsetHeight,buttonsHeight=buttons.offsetHeight,gap=view.width<=620&&view.height<=620?8:12;
    if(reframe&&view.width<=420&&view.height<=620){
      // Short phones need room for the full label below the attack buttons.
      const hud=document.querySelector('.topbar')?.getBoundingClientRect(),chat=document.querySelector('.world-chat')?.getBoundingClientRect();
      const safeTop=Math.max(8,(hud?.bottom||0)-viewport.top+6),safeBottom=Math.min(view.height-8,chat&&chat.height>32?chat.top-viewport.top-6:view.height-8);
      const objectHeight=object.bottom-object.top,total=bannerHeight+buttonsHeight+objectHeight+gap*2;
      if(total<=safeBottom-safeTop){const desiredTop=safeTop+bannerHeight+gap+(safeBottom-safeTop-total)/2,shift=desiredTop-object.top;if(Math.abs(shift)>1){moveTo(memory.x,memory.y-shift/scale());return;}}
    }
    const [x,y]=projectTarget(target),left=clamp(x-width/2,8,Math.max(8,view.width-width-8));
    const stackHeight=bannerHeight+gap+buttonsHeight,sideTop=clamp(y-stackHeight/2,8,Math.max(8,view.height-stackHeight-8));
    const candidates=[
      {left,banner:object.top-gap-bannerHeight,buttons:object.bottom+gap},
      {left:object.right+gap,banner:sideTop,buttons:sideTop+bannerHeight+gap},
      {left:object.left-gap-width,banner:sideTop,buttons:sideTop+bannerHeight+gap},
      {left,banner:object.top-gap-stackHeight,buttons:object.top-gap-buttonsHeight},
      {left,banner:object.bottom+gap,buttons:object.bottom+gap+bannerHeight+gap}
    ];
    const obstacles=[...document.querySelectorAll('.topbar,.world-chat,#navigation,#hud-menu,.hud-edge-tools button,.map-overlay-search-toggle,.map-overlay-coordinate-toggle')].filter(el=>{const css=getComputedStyle(el);return !el.hidden&&css.display!=='none'&&css.visibility!=='hidden'&&Number(css.opacity)>0;}).map(el=>{const r=el.getBoundingClientRect();return{left:r.left-viewport.left,right:r.right-viewport.left,top:r.top-viewport.top,bottom:r.bottom-viewport.top};});
    // In landscape, slide a side menu below HUD buttons or above the chat dock.
    for(const side of candidates.slice(1,3))for(const obstacle of obstacles)for(const edge of [obstacle.bottom+8,obstacle.top-stackHeight-8]){
      const banner=clamp(edge,8,Math.max(8,view.height-stackHeight-8));
      candidates.push({left:side.left,banner,buttons:banner+bannerHeight+gap});
    }
    const overlap=(a,b)=>Math.max(0,Math.min(a.right,b.right)-Math.max(a.left,b.left))*Math.max(0,Math.min(a.bottom,b.bottom)-Math.max(a.top,b.top));
    const bounds={left:8,right:view.width-8,top:8,bottom:view.height-8};
    const score=c=>[ [c.banner,bannerHeight],[c.buttons,buttonsHeight] ].reduce((sum,[top,height])=>{
      const r={left:c.left,right:c.left+width,top,bottom:top+height};
      return sum+(width*height-overlap(r,bounds))*100+overlap(r,object)*100+obstacles.reduce((area,o)=>area+overlap(r,o),0);
    },0);
    const placement=candidates.reduce((best,c)=>score(c)<score(best)?c:best),top=Math.min(placement.banner,placement.buttons);
    menu.style.left=`${placement.left}px`;menu.style.top=`${top}px`;menu.style.height=`${Math.max(placement.banner+bannerHeight,placement.buttons+buttonsHeight)-top}px`;
    banner.style.top=`${placement.banner-top}px`;buttons.style.top=`${placement.buttons-top}px`;
  }
  function positionOwnVillageActions(menu,target){
    menu.style.removeProperty('height');
    const banner=menu.querySelector('.atlas-village-banner'),buttons=menu.querySelector('.atlas-actions-buttons');
    banner.style.removeProperty('top');buttons.style.removeProperty('top');
    const viewport=view.viewport.getBoundingClientRect(),marker=view.markerNodes.get(target.key),art=marker.querySelector('img').getBoundingClientRect();
    const width=menu.offsetWidth,height=menu.offsetHeight,gap=6,[x]=projectTarget(target);
    const topbar=document.querySelector('.topbar')?.getBoundingClientRect();
    const lower=[document.querySelector('.world-chat'),document.querySelector('#navigation'),document.querySelector('#hud-menu')]
      .filter(Boolean).filter(el=>{const css=getComputedStyle(el),rect=el.getBoundingClientRect();return !el.hidden&&css.display!=='none'&&css.visibility!=='hidden'&&Number(css.opacity)>0&&rect.top>viewport.top+view.height/2;})
      .map(el=>el.getBoundingClientRect().top-viewport.top-gap);
    const safeTop=Math.max(8,(topbar?.bottom||viewport.top)-viewport.top+gap),safeBottom=Math.min(view.height-8,...lower);
    const artBottom=art.bottom-viewport.top;
    const framing=`own:${target.key}:${view.width}:${view.height}:${memory.zoom}`;
    if(view.objectActionFraming!==framing&&artBottom+gap+height>safeBottom&&safeBottom-safeTop>=height+art.height*.7){
      view.objectActionFraming=framing;
      const desiredBottom=safeBottom-height-gap,shift=artBottom-desiredBottom;
      if(shift>1){moveTo(memory.x,memory.y+shift/scale());return;}
    }
    view.objectActionFraming=framing;
    menu.style.left=`${clamp(x-width/2,8,Math.max(8,view.width-width-8))}px`;
    menu.style.top=`${clamp(artBottom+gap,safeTop,Math.max(safeTop,safeBottom-height))}px`;
  }
  function positionEncounterActions(menu,target){
    menu.style.removeProperty('height');
    const viewport=view.viewport.getBoundingClientRect(),width=menu.offsetWidth,height=menu.offsetHeight,gap=10;
    const obstacles=[...document.querySelectorAll('.topbar,.world-chat,#navigation,#hud-menu,.hud-edge-tools button,.map-overlay-search-toggle,.map-overlay-coordinate-toggle')].filter(el=>{const css=getComputedStyle(el);return !el.hidden&&css.display!=='none'&&css.visibility!=='hidden'&&Number(css.opacity)>0;}).map(el=>{const r=el.getBoundingClientRect();return{left:r.left-viewport.left,right:r.right-viewport.left,top:r.top-viewport.top,bottom:r.bottom-viewport.top};});
    const topbar=document.querySelector('.topbar')?.getBoundingClientRect(),chat=document.querySelector('.world-chat')?.getBoundingClientRect();
    const safeTop=Math.max(8,(topbar?.bottom||0)-viewport.top+8),safeBottom=Math.min(view.height-8,chat&&chat.height>32?chat.top-viewport.top-8:view.height-8);
    const framing=`card:${target.key}:${view.width}:${view.height}:${memory.zoom}`;
    if(view.objectActionFraming!==framing){
      view.objectActionFraming=framing;
      const [x,y]=projectTarget(target),art=view.markerNodes.get(target.key).querySelector('img').getBoundingClientRect();
      // Small screens place the card beside the target; tall phones can stack it.
      const side=view.width<=620&&safeBottom-safeTop<height+art.height+gap*2;
      const shortLandscape=view.width>420&&view.width<=620&&view.height<=360;
      const desiredX=shortLandscape?48:side?Math.max(38,(view.width-width-gap)/2):view.width/2;
      const desiredY=shortLandscape?safeBottom-4:side?(safeTop+safeBottom)/2+art.height*.2:Math.max(safeTop+art.height,(safeTop+safeBottom-height)/2);
      if(view.width<=620&&(Math.abs(x-desiredX)>1||Math.abs(y-desiredY)>1)){moveTo(memory.x+(x-desiredX)/scale(),memory.y+(y-desiredY)/scale());return;}
    }
    const marker=view.markerNodes.get(target.key),art=marker.querySelector('img').getBoundingClientRect(),tile=marker.getBoundingClientRect();
    const object={left:Math.min(art.left,tile.left)-viewport.left,right:Math.max(art.right,tile.right)-viewport.left,top:Math.min(art.top,tile.top)-viewport.top,bottom:Math.max(art.bottom,tile.bottom)-viewport.top};
    const [x,y]=projectTarget(target),left=clamp(x-width/2,8,Math.max(8,view.width-width-8));
    const sideTop=clamp(y-height/2,safeTop,Math.max(safeTop,safeBottom-height));
    const candidates=[{left,top:object.bottom+gap},{left,top:object.top-height-gap},{left:object.right+gap,top:sideTop},{left:object.left-width-gap,top:sideTop}];
    for(const candidate of candidates.slice())for(const obstacle of obstacles){
      for(const top of [obstacle.bottom+gap,obstacle.top-height-gap])candidates.push({left:candidate.left,top});
      for(const left of [obstacle.right+gap,obstacle.left-width-gap])candidates.push({left,top:candidate.top});
    }
    const overlap=(a,b)=>Math.max(0,Math.min(a.right,b.right)-Math.max(a.left,b.left))*Math.max(0,Math.min(a.bottom,b.bottom)-Math.max(a.top,b.top));
    const bounds={left:8,right:view.width-8,top:8,bottom:view.height-8};
    const score=c=>{const rect={left:c.left,right:c.left+width,top:c.top,bottom:c.top+height};return(width*height-overlap(rect,bounds))*100+overlap(rect,object)*15+obstacles.reduce((sum,o)=>sum+overlap(rect,o)*30,0);};
    const best=candidates.reduce((a,b)=>score(a)<=score(b)?a:b);
    menu.style.left=`${clamp(best.left,8,Math.max(8,view.width-width-8))}px`;menu.style.top=`${clamp(best.top,8,Math.max(8,view.height-height-8))}px`;
  }
  function positionActions(){
    const menu=view.el.querySelector('.atlas-target-actions'),target=selectedTarget();if(menu.hidden||!target)return;
    menu.classList.toggle('is-village',isCompactTarget(target));menu.classList.toggle('is-own-village',target.kind==='home');menu.classList.toggle('is-simple-target',['monsters','nodes','charms'].includes(target.kind));menu.classList.toggle('is-empty-cell',target.kind==='cell');menu.classList.toggle('is-monster',target.kind==='monsters');menu.classList.toggle('is-resource',target.kind==='nodes');menu.classList.toggle('is-charm',target.kind==='charms');
    if(target.kind==='charms')menu.dataset.grade=['normal','epic','legendary'].includes(target.data.grade)?target.data.grade:'normal';else delete menu.dataset.grade;
    const encounter=['monsters','nodes'].includes(target.kind);menu.classList.toggle('is-encounter',encounter);
    if(encounter){menu.classList.remove('is-village');positionEncounterActions(menu,target);return;}
    if(target.kind==='home'){positionOwnVillageActions(menu,target);return;}
    if(target.kind==='charms'){positionObjectActions(menu,target);return;}
    menu.style.removeProperty('height');
    const [x,y]=projectTarget(target),width=menu.offsetWidth,height=menu.offsetHeight,viewport=view.viewport.getBoundingClientRect();
    const hudBottom=(document.querySelector('.topbar')?.getBoundingClientRect().bottom||viewport.top+82)-viewport.top;
    const rightRail=[...document.querySelectorAll('.hud-right-tools button,#navigation button')]
      .filter(button=>{const css=getComputedStyle(button),rect=button.getBoundingClientRect();return button.getClientRects().length&&css.visibility!=='hidden'&&css.display!=='none'&&Number(css.opacity)>0&&rect.left>viewport.left+view.width*.65&&rect.width<110;})
      .map(button=>button.getBoundingClientRect().left-viewport.left-10);
    const right=Math.min(view.width-8,...rightRail),maxLeft=Math.max(8,right-width);
    const top=Math.max(12,hudBottom+8),bottom=view.height-(parseFloat(getComputedStyle(view.el).getPropertyValue('--map-control-bottom'))||104)-65;
    if(isCompactTarget(target)){
      const villageTop=y-scale()*footprint(target)/2-52;
      menu.style.left=`${clamp(x-width/2,8,maxLeft)}px`;
      menu.style.top=`${clamp(villageTop,top,Math.max(top,bottom-height))}px`;
      return;
    }
    const below=y+scale()*footprint(target)/2+18;
    menu.style.left=`${clamp(x-width/2,8,maxLeft)}px`;
    menu.style.top=`${clamp(below+height<=bottom?below:y-scale()*footprint(target)/2-height-18,top,Math.max(top,bottom-height))}px`;
  }
  function details(target){
    const close=`<button class="atlas-close" data-atlas="clear" aria-label="Zielauswahl schließen">${icon('close')}</button>`;
    if(!target)return memory.selected?`${close}<div class="atlas-detail-empty"><h2>Ziel nicht verfügbar</h2><p>Dieses Ziel liegt nicht mehr im geladenen Gebiet.</p></div>`:'';
    if(target.kind==='cell')return `${close}<div class="atlas-target-heading atlas-empty-cell"><small>WELTKARTE · ${context.teleport?'4 × 4 STADTPLATZ':'1 × 1 FELD'}</small><h2>${context.teleport?'Neuer Standort':'Freies Feld'}</h2><span class="atlas-target-meta">X ${target.x} · Y ${target.y}</span></div><button class="atlas-primary" data-action="share-coordinates" data-x="${target.x}" data-y="${target.y}" data-name="Freies Feld">Koordinaten teilen ${icon('arrow')}</button>`;
    if(target.kind==='congress')return `${close}<div class="atlas-target-heading"><h2>Kongress</h2><p>${escape(target.data.alliance_name||'Noch keine Allianz herrscht über den Weltensee.')}</p></div><button class="atlas-primary" data-action="congress-open">Kongress öffnen</button>`;
    if(['alliance_center','outpost'].includes(target.kind)){const center=target.kind==='alliance_center';return `${close}<div class="atlas-target-heading"><small>ALLIANZGEBIET · RADIUS ${format(target.data.radius)}</small><h2>${escape(target.name)}</h2><p>[${escape(target.data.alliance_tag)}] ${escape(target.data.alliance_name)}</p><span class="atlas-target-meta">X ${target.x} · Y ${target.y}</span></div><p>${center?'+5 % Angriff und Verteidigung · +10 % Produktion und Sammeltempo':'+5 % Angriff, Leben und Verteidigung'}</p><button class="atlas-primary" data-action="share-coordinates" data-x="${target.x}" data-y="${target.y}" data-name="${escape(target.name)}">Koordinaten teilen</button>`;}
    if(target.kind==='shrine')return `${close}<div class="atlas-target-heading"><h2>${escape(target.name)}</h2><p>${escape(shrineStatus(target,true))}</p></div><button class="atlas-primary" data-action="shrine-open" data-id="${escape(target.id)}">Schrein öffnen</button>`;
    const distance=Math.hypot(target.x-number(context.state.city.coord_x),target.y-number(context.state.city.coord_y)).toFixed(1).replace('.',',');
    let eyebrow='GESCHÜTZTE STADT',body='',action='';
    if(target.kind==='home'){eyebrow='DEIN KÖNIGREICH';action='<button class="atlas-primary" data-action="tab" data-id="city">Stadt betreten '+icon('arrow')+'</button>';}
    else if(target.kind==='players'){body=target.data.alliance_tag?`<p>Allianz <strong>[${escape(target.data.alliance_tag)}]</strong></p>`:'';action=`<button class="atlas-primary" data-action="public-profile" data-id="${escape(target.id)}">Profil ansehen ${icon('arrow')}</button>`;}
    else if(target.kind==='monsters'){eyebrow='MONSTER';body=`<div class="atlas-stat-pair"><span>Lebenspunkte<strong>${format(target.data.hp_current)}</strong></span><span>Entfernung<strong>${distance} Felder</strong></span></div>`;action=`<button class="atlas-primary" data-action="expedition" data-kind="monsters" data-id="${escape(target.id)}">${target.data.definition?.type==='rally'?'Rally starten':'Angreifen'} ${icon('sword')}</button>`;}
    else if(target.kind==='charms'){eyebrow='KARTEN-CHARM';body=`<div class="atlas-stat-pair"><span>${escape(charmCategories[target.data.stat_category]||target.data.stat_category||'Bonus')}<strong>+${format(target.data.bonus_pct)} %</strong></span><span>Entfernung<strong>${distance} Felder</strong></span></div><p class="atlas-node-status">Wirkt nach dem Einsammeln ${format(target.data.effect_duration_seconds)} Sekunden.</p>`;action=`<button class="atlas-primary" data-action="expedition" data-kind="charms" data-id="${escape(target.id)}" ${target.data.collectible===false?'disabled':''}>Einsammeln ${icon('debuff')}</button>`;}
    else {const occupied=!!target.data.gatherer_march_id,attack=!!target.data.can_attack;eyebrow=target.resource.word.toLocaleUpperCase('de-DE');body=`<div class="atlas-stat-pair"><span>Vorrat<strong>${format(target.data.resource_amount)}</strong></span><span>Entfernung<strong>${distance} Felder</strong></span></div>`;action=`<button class="atlas-primary" data-action="expedition" data-kind="${attack?'node-attack':'nodes'}" data-id="${escape(target.id)}" ${occupied&&!attack?'disabled':''}>${attack?'Angreifen':occupied?'Derzeit besetzt':'Sammeln'} ${icon('resource')}</button>`;}
    if(target.kind==='nodes'){
      body+=`<p class="atlas-node-status">${escape(nodeStatus(target.data))}</p>`;
      if(target.data.is_own_gathering)action=`<button class="atlas-primary" data-action="gather-recall" data-id="${escape(target.data.gatherer_march_id)}">Sammler zurückrufen ${icon('home')}</button>`;
    }
    return `${close}<div class="atlas-target-portrait atlas-target-portrait--${target.kind}">${encounterImage(target)}</div><div class="atlas-target-heading"><small>${eyebrow}</small><h2>${escape(target.name)}</h2><span class="atlas-target-meta">Stufe ${target.level}<i></i>X ${target.x} · Y ${target.y}</span></div>${body}${action}`;
  }
  function updateSidebar(){
    if(!view)return;const target=selectedTarget();
    const guide=view.el.querySelector('.atlas-teleport-guide');guide.hidden=!context.teleport||memory.panel==='actions';view.el.classList.toggle('is-teleporting',!!context.teleport);
    if(context.teleport){const alliance=context.teleport.mode==='alliance';guide.querySelector('img').src=`${context.base}/assets/art/items/${alliance?'teleport-alliance.svg':'teleport.svg'}`;guide.querySelector('strong').textContent=alliance?'Allianz-Teleport':'Advanced-Teleport';guide.querySelector('small').textContent=alliance?'Nahe einer Stadt antippen · Vorschau ziehen':'Platz antippen · Dorf-Vorschau ziehen';}
    for(const panel of ['search','navigation','target'])view.el.querySelector(`.map-overlay-${panel}-panel`).hidden=memory.panel!==panel;
    document.body.classList.toggle('world-search-open',memory.panel==='search');
    view.el.querySelector('.map-overlay-backdrop').hidden=!memory.panel||['actions','search'].includes(memory.panel);
    const commands=view.el.querySelector('.atlas-target-actions');commands.hidden=memory.panel!=='actions'||!target;document.body.classList.toggle('world-target-open',!commands.hidden);document.body.classList.toggle('world-target-village',!commands.hidden&&!!target&&isCompactTarget(target));
    for(const panel of ['search','navigation'])view.el.querySelector(`[data-atlas="${panel}"]`).setAttribute('aria-expanded',String(memory.panel===panel));
    view.el.querySelector('.map-overlay-search-toggle').classList.toggle('is-filtered',memory.filter!=='all'||!!memory.search);
    const detailSignature=JSON.stringify([memory.selected,memory.cell,target?.data,context.teleport?.item_code||null,motionReduced(),memory.searchRun?.key===searchKey(),memory.searchRun?.targetKey]);if(detailSignature!==view.detailStamp){view.detailStamp=detailSignature;view.el.querySelector('.atlas-detail').innerHTML=details(target);commands.innerHTML=targetActions(target);updateSearchControls();}
  }
  function updateFooter(){if(!view)return;const visible=view.targets.filter(t=>t.kind!=='home'&&matching(t)).length;view.el.querySelector('.atlas-status').textContent=`${visible} Ziele im erkundeten Gebiet`;view.el.querySelector('.atlas-march-count').textContent=`${context.state.marches?.length||0} / ${context.state.army_limits?.march_slots||3} Märsche unterwegs`;}
  // Decorative terrain uses the same pre-rendered 3D language as settlements,
  // resources and monsters. The former canvas volcanoes, crystal spires and
  // flat relic drawings are intentionally not used here.
  function renderScenery3D(c,project,s,decorations){
    const filters={forest:'none',ice:'hue-rotate(12deg) saturate(.9) brightness(1.05)',sand:'sepia(.1) saturate(.94) brightness(1.03)',lava:'sepia(.08) saturate(.82) brightness(.91)'};
    for(const d of [...decorations].sort((a,b)=>a.y-b.y)){
      const biome=window.ConquerLandscape.biomeAt(d.x,d.y),variant=Number(d.variant)||0;
      let asset='rocks',height=d.size*1.08,contact=.78;
      if(d.kind==='tree'){
        asset=variant>.86?'cherry':variant<.58?'pine':'oak';
        height=d.size*1.9;contact=.87;
      }else if(d.kind==='hill'){
        // Ashlands receive grounded basalt groups rather than cartoon volcanoes.
        asset=biome.id==='lava'?'rocks':'mountain';height=d.size*(asset==='mountain'?1.76:1.28);contact=asset==='mountain'?.81:.76;
      }
      const image=sceneryImages.get(asset);if(!image?.complete||!image.naturalWidth)continue;
      const [x,y]=project(d.x,d.y),width=height*(image.naturalWidth/image.naturalHeight);
      if(x+width<0||x-width>view.width||y+height*.2<0||y-height>view.height)continue;
      c.save();
      c.fillStyle=biome.id==='lava'?'#493b3338':'#334b3f2d';c.beginPath();c.ellipse(x+height*.045,y+height*.018,width*.31,height*.075,-.12,0,Math.PI*2);c.fill();
      c.filter=filters[biome.id];c.drawImage(image,x-width/2,y-height*contact,width,height);c.restore();
    }
  }
  function terrain(){
    const signature=JSON.stringify([memory.x,memory.y,memory.zoom,view.width,view.height,lockedZone()?.bounds,context.teleport?.item_code||null,view.targets.map(t=>[t.key,t.x,t.y,footprint(t),matching(t),t.data.city_skin,t.data.name_frame,t.data.name_frame_id,t.artKey,isRegionalBoss(t)])]);
    if(signature===view.terrainStamp)return;view.terrainStamp=signature;
    const c=view.ctx,s=scale(),w=view.width,h=view.height;const [left,top]=unproject(0,0),[right,bottom]=unproject(w,h);
    c.clearRect(0,0,w,h);window.ConquerLandscape.ground(c,project,s,{left,top,right,bottom});
    const biome=window.ConquerLandscape.biomeAt(memory.x,memory.y);view.el.querySelector('.atlas-biome-label').textContent=Math.hypot(memory.x-128,memory.y-128)<22?'Weltensee · Kongress':biome.name;
    window.ConquerLandscape.water(c,project,s,{left,top,right,bottom});
    window.ConquerLandscape.bridges?.(c,project,s,{left,top,right,bottom});
    const occupied=(x,y,pad=.45)=>view.targets.some(t=>Math.abs(targetCenter(t)[0]-x)<footprint(t)/2+pad&&Math.abs(targetCenter(t)[1]-y)<footprint(t)/2+pad)||window.ConquerLandscape.waterAt(x,y);
    const decorations=[],clusterSpan=9;
    // Vegetation forms a few readable groves with broad clearings between
    // them. This keeps the illustrated world alive without filling every tile.
    for(let gy=Math.floor(top/clusterSpan)-1;gy<=Math.ceil(bottom/clusterSpan)+1;gy++)for(let gx=Math.floor(left/clusterSpan)-1;gx<=Math.ceil(right/clusterSpan)+1;gx++){
      const v=hash(gx,gy,13),wx=gx*clusterSpan+1+hash(gx,gy,14)*(clusterSpan-2),wy=gy*clusterSpan+1+hash(gx,gy,15)*(clusterSpan-2),weights=window.ConquerLandscape.biomeAt(wx,wy).weights;
      const treeDensity=.84+weights.forest*.2;
      if(v<.68*treeDensity){
        const count=6+Math.floor(hash(gx,gy,16)*5)+(weights.forest>.55?2:0);
        for(let k=0;k<count;k++){
          const angle=hash(gx+k*3,gy-k,17)*Math.PI*2,radius=.55+hash(gx-k,gy+k*5,18)*3.05;
          const tx=wx+Math.cos(angle)*radius,ty=wy+Math.sin(angle)*radius*.72;
          if(occupied(tx,ty,.62))continue;
          decorations.push({x:tx,y:ty,size:s*(.64+hash(gx+k,gy,19)*.34),variant:hash(gx,gy+k,20),kind:'tree'});
        }
      }else if(v>.89){
        const count=v>.975?2:1;
        for(let k=0;k<count;k++){
          const tx=wx+(k?1.25:-.25),ty=wy+(k?.55:0);
          if(!occupied(tx,ty,1.55))decorations.push({x:tx,y:ty,size:s*(1.2+hash(gx+k,gy,22)*.45),variant:hash(gx,gy+k,23),kind:'hill'});
        }
      }
    }
    // Small biome-specific props add storybook character between the large
    // landmarks. They are fixed in world space and keep generous clearance
    // around anything interactive, so silhouettes and tap targets stay clear.
    const detailSpan=6;
    for(let gy=Math.floor(top/detailSpan)-1;gy<=Math.ceil(bottom/detailSpan)+1;gy++)for(let gx=Math.floor(left/detailSpan)-1;gx<=Math.ceil(right/detailSpan)+1;gx++){
      const chance=hash(gx,gy,431);if(chance<.68)continue;
      const wx=gx*detailSpan+1+hash(gx,gy,432)*(detailSpan-2),wy=gy*detailSpan+1+hash(gx,gy,433)*(detailSpan-2);
      if(wx<.5||wy<.5||wx>254.5||wy>254.5||occupied(wx,wy,.72))continue;
      decorations.push({x:wx,y:wy,size:s*(.72+hash(gx,gy,434)*.28),variant:hash(gx,gy,435),kind:'detail'});
    }
    const [edgeX,edgeY]=project(-.5,-.5),[endX,endY]=project(255.5,255.5);
    // Tile boundaries are a close-range aid, not part of the landscape. At
    // normal zoom the painted terrain remains uninterrupted and calm.
    if(context.teleport||memory.zoom>=1.62){
      c.save();c.beginPath();c.rect(edgeX,edgeY,endX-edgeX,endY-edgeY);c.clip();
      c.beginPath();for(let x=Math.max(0,Math.floor(left));x<=Math.min(256,Math.ceil(right)+1);x++){const [px]=project(x-.5,0);c.moveTo(px,Math.max(0,edgeY));c.lineTo(px,Math.min(h,endY));}for(let y=Math.max(0,Math.floor(top));y<=Math.min(256,Math.ceil(bottom)+1);y++){const [,py]=project(0,y-.5);c.moveTo(Math.max(0,edgeX),py);c.lineTo(Math.min(w,endX),py);}c.lineWidth=context.teleport?1.25:1;c.strokeStyle=context.teleport?'#5c427052':'#70472f12';c.stroke();
      c.restore();
    }
    for(const t of view.targets){if(!['alliance_center','outpost'].includes(t.kind)||!matching(t))continue;const [x,y]=projectTarget(t),radius=number(t.data.radius)*s;c.save();c.beginPath();c.arc(x,y,radius,0,Math.PI*2);c.fillStyle=t.kind==='alliance_center'?'#71507918':'#c39a4b12';c.fill();c.strokeStyle=t.kind==='alliance_center'?'#8c69a9aa':'#d2ac5c99';c.lineWidth=Math.max(1,2*memory.zoom);c.setLineDash([8*memory.zoom,7*memory.zoom]);c.stroke();c.restore();}
    // Regional soil and foot shadows share the terrain pass. The selection
    // rectangle is shown only on selection, never baked beneath every resource.
    for(const t of view.targets){
      if(t.kind==='congress'||t.kind!=='shrine'&&!matching(t))continue;
      const [x,y]=projectTarget(t),[wx,wy]=targetCenter(t),point={x:wx,y:wy};
      if(x<-s*4||x>w+s*4||y<-s*4||y>h+s*4)continue;
      if(isVillage(t))window.ConquerLandscape.settlement(c,x,y,s,t.data.city_skin||'default',point);
      else window.ConquerLandscape.objectGround(c,x,y,s,{kind:t.kind,point,biome:t.biome,large:isRegionalBoss(t),contactY:isRegionalBoss(t)?bossContactY[t.artKey]:undefined});
    }
    renderScenery3D(c,project,s,decorations);
    worldEdgeClouds(c,edgeX,edgeY,endX,endY,w,h,s,{left,top,right,bottom});
    lockedZoneOverlay(c);
  }
  function worldEdgeClouds(c,left,top,right,bottom,width,height,s,bounds){
    if(left<=0&&top<=0&&right>=width&&bottom>=height)return;
    const sky='#dbe3d4',overlap=Math.max(25,s*1.06),sideCodes={left:11,right:23,top:37,bottom:53};
    c.save();
    c.fillStyle=sky;
    if(left>0)c.fillRect(0,0,left,height);
    if(top>0)c.fillRect(0,0,width,top);
    if(right<width)c.fillRect(right,0,width-right,height);
    if(bottom<height)c.fillRect(0,bottom,width,height-bottom);
    const puff=(x,y,rx,ry,rotation,alpha,kind='body')=>{
      c.save();c.translate(x,y);c.rotate(rotation);c.filter=`blur(${Math.max(5,s*(kind==='wisp'?.2:.15))}px)`;c.globalAlpha=alpha;
      c.fillStyle=kind==='shadow'?'#aeb9ad':kind==='highlight'?'#fffdf4':'#f0eee2';c.beginPath();c.ellipse(0,0,rx,ry,0,0,Math.PI*2);c.fill();c.restore();
    };
    const mistBand=(side,edge)=>{
      const vertical=side==='left'||side==='right',outward=side==='left'||side==='top'?-1:1,inside=edge-outward*overlap*1.45,outside=edge+outward*overlap*1.2;
      const gradient=vertical?c.createLinearGradient(inside,0,outside,0):c.createLinearGradient(0,inside,0,outside);
      gradient.addColorStop(0,'rgba(233,235,224,0)');gradient.addColorStop(.34,'rgba(239,238,226,.28)');gradient.addColorStop(.55,'rgba(237,237,225,.92)');gradient.addColorStop(.82,'rgba(226,231,216,.97)');gradient.addColorStop(1,'rgba(219,227,212,1)');
      c.fillStyle=gradient;if(vertical)c.fillRect(Math.min(inside,outside),0,Math.abs(outside-inside),height);else c.fillRect(0,Math.min(inside,outside),width,Math.abs(outside-inside));
    };
    const chain=(side,edge,worldStart,worldEnd)=>{
      const vertical=side==='left'||side==='right',outward=side==='left'||side==='top'?-1:1,worldStep=4.65,step=worldStep*s,code=sideCodes[side];mistBand(side,edge);
      for(let index=Math.floor(worldStart/worldStep)-2;index<=Math.ceil(worldEnd/worldStep)+2;index++){
        const worldCenter=(index+.5)*worldStep+(hash(index,code,401)-.5)*worldStep*.48,center=vertical?project(0,worldCenter)[1]:project(worldCenter,0)[0];
        const depth=overlap*(1.7+hash(index,code,402)*.8),across=edge+outward*depth*.18,tilt=(hash(index,code,408)-.5)*.24;
        if(vertical)puff(across-outward*overlap*.1,center+overlap*.18,depth*1.2,step*.57,tilt,.2,'shadow');
        else puff(center+overlap*.18,across-outward*overlap*.1,step*.57,depth*1.2,tilt,.2,'shadow');
        for(let lobe=0;lobe<9;lobe++){
          const amount=hash(index,lobe+code,403),alongOffset=(hash(index,lobe+code,404)-.5)*step*1.08,distance=overlap*(-.48+hash(index,lobe+code,405)*1.2),radius=overlap*(.7+hash(index,lobe+code,406)*.65),rotation=(hash(index,lobe+code,409)-.5)*.58;
          const rx=radius*(1.16+amount*.58),ry=radius*(.68+hash(index,lobe+code,410)*.4),opacity=.28+hash(index,lobe+code,411)*.22,kind=lobe%4===0?'highlight':'body';
          if(vertical)puff(edge+outward*distance,center+alongOffset,rx,ry,rotation,opacity,kind);
          else puff(center+alongOffset,edge+outward*distance,ry,rx,rotation,opacity,kind);
        }
        const wispDistance=overlap*(1.2+hash(index,code,412)*1.1),wispAlong=center+(hash(index,code,407)-.5)*step*.8;
        if(vertical)puff(edge+outward*wispDistance,wispAlong,overlap*1.7,overlap*.38,tilt,.16,'wisp');
        else puff(wispAlong,edge+outward*wispDistance,overlap*.38,overlap*1.7,tilt,.16,'wisp');
      }
    };
    if(left>0)chain('left',left,bounds.top,bounds.bottom);
    if(right<width)chain('right',right,bounds.top,bounds.bottom);
    if(top>0)chain('top',top,bounds.left,bounds.right);
    if(bottom<height)chain('bottom',bottom,bounds.left,bounds.right);
    const corner=(x,y,dx,dy,seed)=>{
      puff(x+dx*overlap*.18,y+dy*overlap*.18,overlap*4.35,overlap*3.8,(hash(seed,3,421)-.5)*.3,.52,'body');
      for(let i=0;i<7;i++){const px=x+(hash(seed,i,422)-.48)*overlap*3.7,py=y+(hash(seed,i,423)-.48)*overlap*3.7,radius=overlap*(1.05+hash(seed,i,424)*1.05);puff(px,py,radius*1.3,radius*.88,(hash(seed,i,425)-.5)*.55,.25+(i%3)*.08,i%3===0?'highlight':'body');}
    };
    if(left>0&&top>0)corner(left,top,-1,-1,61);
    if(right<width&&top>0)corner(right,top,1,-1,67);
    if(left>0&&bottom<height)corner(left,bottom,-1,1,71);
    if(right<width&&bottom<height)corner(right,bottom,1,1,73);
    c.restore();
  }
  function lockedZone(){
    return (context.state.land_progression?.zones||[]).filter(zone=>zone.open===false&&zone.bounds&&['x_min','y_min','x_max','y_max'].every(key=>Number.isFinite(Number(zone.bounds[key])))).sort((a,b)=>(Number(b.bounds.x_max)-Number(b.bounds.x_min))*(Number(b.bounds.y_max)-Number(b.bounds.y_min))-(Number(a.bounds.x_max)-Number(a.bounds.x_min))*(Number(a.bounds.y_max)-Number(a.bounds.y_min)))[0]||null;
  }
  function lockedZoneOverlay(c){
    const zone=lockedZone();if(!zone)return;const bounds=zone.bounds,[left,top]=project(Number(bounds.x_min)-.5,Number(bounds.y_min)-.5),[right,bottom]=project(Number(bounds.x_max)+.5,Number(bounds.y_max)+.5);
    const x=Math.max(0,left),y=Math.max(0,top),endX=Math.min(view.width,right),endY=Math.min(view.height,bottom),width=endX-x,height=endY-y;if(width<=0||height<=0)return;
    c.save();c.beginPath();c.rect(x,y,width,height);c.clip();c.fillStyle='#fff8df2e';c.fillRect(x,y,width,height);c.strokeStyle='#fff5cf52';c.lineWidth=2;const step=28;for(let offset=-height;offset<width+height;offset+=step){c.beginPath();c.moveTo(x+offset,y+height);c.lineTo(x+offset+height,y);c.stroke();}c.restore();
    c.save();c.strokeStyle='#f6e3ad99';c.lineWidth=3;c.strokeRect(left,top,right-left,bottom-top);if(width>120&&height>70){const label='Noch gesperrt',cx=x+width/2,cy=y+Math.min(height/2,70);c.font='700 13px Segoe UI, sans-serif';c.textAlign='center';c.textBaseline='middle';const labelWidth=c.measureText(label).width+24;c.fillStyle='#fff7dfdc';c.fillRect(cx-labelWidth/2,cy-15,labelWidth,30);c.strokeStyle='#8e744dcc';c.lineWidth=1;c.strokeRect(cx-labelWidth/2+.5,cy-14.5,labelWidth-1,29);c.fillStyle='#5e4b35';c.fillText(label,cx,cy);}c.restore();
  }
  function tree(c,x,y,size,variant){
    const sprite=sceneryImages.get(variant<.57?'pine':'oak');if(sprite?.complete&&sprite.naturalWidth){const height=size*1.9;c.drawImage(sprite,x-height*.5,y-height*.86,height,height);return;}
    if(x<-size||y<-size||x>view.width+size||y>view.height+size)return;
    c.fillStyle='#334b3f35';c.beginPath();c.ellipse(x+size*.15,y+size*.08,size*.4,size*.15,-.25,0,Math.PI*2);c.fill();c.fillStyle='#665c3d';c.fillRect(x-size*.035,y-size*.3,size*.07,size*.36);
    if(variant<.67){for(let i=0;i<3;i++){const width=size*(.36-i*.08),peak=y-size*(.62+i*.14),bottom=y-size*(.06+i*.24);c.fillStyle=['#365b43','#416d4a','#537d51'][i];c.beginPath();c.moveTo(x,peak);c.lineTo(x+width,bottom);c.quadraticCurveTo(x,bottom+size*.12,x-width,bottom);c.closePath();c.fill();c.fillStyle='#73925b55';c.beginPath();c.moveTo(x,peak);c.lineTo(x,bottom);c.lineTo(x-width,bottom);c.closePath();c.fill();}}
    else{for(const [dx,dy,r,color]of [[-.18,-.39,.28,'#476942'],[.18,-.38,.3,'#3b6544'],[0,-.65,.3,'#63834b'],[-.12,-.64,.19,'#81975a']]){c.fillStyle=color;c.beginPath();c.arc(x+size*dx,y+size*dy,size*r,0,Math.PI*2);c.fill();}}
  }
  function hill(c,x,y,size,mountain){
    const sprite=sceneryImages.get(mountain?'mountain':'rocks');if(sprite?.complete&&sprite.naturalWidth){const height=size*1.5;c.drawImage(sprite,x-height*.5,y-height*.79,height,height);return;}
    c.fillStyle='#536d4d35';c.beginPath();c.ellipse(x,y+size*.05,size*.6,size*.17,0,0,Math.PI*2);c.fill();
    if(mountain){c.fillStyle='#8d9a78';c.beginPath();c.moveTo(x-size*.5,y);c.lineTo(x-size*.05,y-size*.65);c.lineTo(x+size*.5,y);c.closePath();c.fill();c.fillStyle='#aeb294';c.beginPath();c.moveTo(x-size*.5,y);c.lineTo(x-size*.05,y-size*.65);c.lineTo(x+size*.04,y);c.closePath();c.fill();c.fillStyle='#d7d4b3';c.beginPath();c.moveTo(x-size*.05,y-size*.65);c.lineTo(x+size*.13,y-size*.41);c.lineTo(x,y-size*.45);c.lineTo(x-size*.17,y-size*.4);c.closePath();c.fill();}
    else{c.fillStyle='#79915b';c.beginPath();c.moveTo(x-size*.6,y);c.bezierCurveTo(x-size*.4,y-size*.5,x+size*.25,y-size*.6,x+size*.6,y);c.quadraticCurveTo(x,y+size*.12,x-size*.6,y);c.fill();c.strokeStyle='#b8be834e';c.lineWidth=2;c.beginPath();c.moveTo(x-size*.48,y-size*.04);c.quadraticCurveTo(x-size*.07,y-size*.63,x+size*.27,y-size*.18);c.stroke();}
  }
  function minimap(){
    // The 1024px overview is only needed while its panel is open, never on every drag frame.
    if(memory.panel!=='navigation')return;
    const c=view.mini.getContext('2d'),ratio=160/255;c.setTransform(view.mini.width/160,0,0,view.mini.height/160,0,0);c.clearRect(0,0,160,160);window.ConquerLandscape.ground(c,(x,y)=>[x*ratio,y*ratio],ratio,{left:0,top:0,right:256,bottom:256});
    window.ConquerLandscape.water(c,(x,y)=>[x*ratio,y*ratio],ratio,{left:0,top:0,right:256,bottom:256});
    const locked=lockedZone();if(locked){const b=locked.bounds,x=(Number(b.x_min)-.5)*ratio,y=(Number(b.y_min)-.5)*ratio,width=(Number(b.x_max)-Number(b.x_min)+1)*ratio,height=(Number(b.y_max)-Number(b.y_min)+1)*ratio;c.fillStyle='#fff5d43b';c.fillRect(x,y,width,height);c.strokeStyle='#fff0bd';c.lineWidth=1.2;c.strokeRect(x,y,width,height);}
    for(const t of view.targets){const [x,y]=targetCenter(t),size=Math.max(t.kind==='shrine'?3.4:2,footprint(t)*ratio);c.fillStyle=t.kind==='shrine'?shrineElements[t.element].color:t.kind==='congress'?'#fff0af':t.kind==='home'?'#f9db7b':t.kind==='players'?'#365071':t.kind==='monsters'?'#9d4f37':'#d5bb76';c.fillRect(x*ratio-size/2,y*ratio-size/2,size,size);if(t.kind==='shrine'){c.strokeStyle='#293e49';c.lineWidth=.8;c.strokeRect(x*ratio-size/2,y*ratio-size/2,size,size);}}
    c.font='bold 5px Segoe UI';c.textAlign='center';c.fillStyle='#223548';for(const [label,x,y]of [['WALD',35,32],['EIS',125,32],['SAND',35,130],['LAVA',125,130],['KONGRESS',80,72]]){c.strokeStyle='#f6edcf';c.lineWidth=1.2;c.strokeText(label,x,y);c.fillText(label,x,y);}
    const a=unproject(0,0),b=unproject(view.width,view.height);c.fillStyle='#f4e6ac15';c.strokeStyle='#fff1ba';c.lineWidth=1.5;c.fillRect(a[0]*ratio,a[1]*ratio,(b[0]-a[0])*ratio,(b[1]-a[1])*ratio);c.strokeRect(a[0]*ratio,a[1]*ratio,(b[0]-a[0])*ratio,(b[1]-a[1])*ratio);
  }
  function marches(){
    const state=context.state,homePoint=[number(state.city.coord_x),number(state.city.coord_y)],present=new Set(),lines=[];
    const now=context.now(),frame=performance.now();
    // Server timestamps have one-second precision. Ease small clock corrections instead
    // of making an army jump backwards at every poll; long absences use the new time.
    if(view.clockFrame!==undefined){const correction=view.clockNow+(frame-view.clockFrame)-now;view.clockCorrection=Math.abs(correction)<2500?correction:0;}
    else view.clockCorrection=0;
    view.clockNow=now+view.clockCorrection;view.clockFrame=frame;
    for(const march of state.marches||[]){
      if(['arrived','complete'].includes(march.state))continue;
      const key=String(march.id);present.add(key);
      const returning=march.state==='returning',target=view.targets.find(t=>t.x===number(march.target_x)&&t.y===number(march.target_y));
      // Routes still align when a destination is outside the loaded viewport.
      const offset=target?(targetCenter(target)[0]-target.x):(view.partyNodes.get(key)?.travel?.offset??((march.march_type==='rally'||number(march.target_type)===5)?.5:0));
      const destination=[number(march.target_x)+offset,number(march.target_y)+offset],origin=march.origin_x!==undefined?[number(march.origin_x),number(march.origin_y)]:homePoint;
      const duration=Math.max(1,stamp(march.arrival_time)-stamp(march.departure_time)),roundTrip=stamp(march.return_time)-stamp(march.departure_time);
      // Recall uses elapsed outbound time, with a two-second minimum on the
      // server. Gathering/settlement delays still return from the destination.
      const returnDuration=Math.max(1,Math.min(duration,Math.max(2000,roundTrip/2))),outboundDuration=clamp(roundTrip-returnDuration,0,duration);
      const turn=returning&&outboundDuration<duration?origin.map((v,i)=>v+(destination[i]-v)*outboundDuration/duration):destination;
      const from=returning?turn:origin,to=returning?origin:destination;
      const a=project(...from),b=project(...to);if(march.state!=='gathering')lines.push(`<line x1="${a[0]}" y1="${a[1]}" x2="${b[0]}" y2="${b[1]}" class="${returning?'atlas-route-return':'atlas-route-out'}"/>`);
      let actor=view.partyNodes.get(key);if(!actor){actor=document.createElement('div');actor.className='atlas-march-party';actor.dataset.marchId=key;actor.setAttribute('role','img');actor.innerHTML='<span class="atlas-party-units"></span><span class="atlas-party-skin" aria-hidden="true"><span class="atlas-march-trail">'+Array.from({length:6},(_,i)=>`<i style="--spark:${i}"></i>`).join('')+'</span><img alt=""></span><small></small>';actor.label=actor.querySelector('small');const skinImage=actor.querySelector('.atlas-party-skin img');skinImage.addEventListener('load',()=>{if(actor.dataset.skinSource&&skinImage.getAttribute('src')===actor.dataset.skinSource){actor.classList.add('is-skinned');actor.classList.remove('is-skin-fallback');}});skinImage.addEventListener('error',()=>{if(skinImage.getAttribute('src')!==actor.dataset.skinSource)return;if(actor.dataset.skinSourceKind==='motion'){actor.dataset.motionFailed=actor.dataset.skinSource;setMarchSkinSource(actor,window.ConquerMarchSkins.image(context.base,actor.dataset.marchSkin),'still');return;}actor.classList.remove('is-skinned');actor.classList.add('is-skin-fallback');});view.parties.append(actor);view.partyNodes.set(key,actor);}
      if(view.marchHud&&!actor.querySelector('.atlas-march-hit')){actor.setAttribute('role','group');const hit=document.createElement('button');hit.className='atlas-march-hit';hit.dataset.followMarch=key;hit.type='button';hit.setAttribute('aria-label',`Marsch ${key} mit der Kamera verfolgen`);actor.append(hit);}
      let troops=march.troops||march.troops_json||{};if(typeof troops==='string'){try{troops=JSON.parse(troops);}catch{troops={};}}
      const types=new Map();let total=0;for(const [code,count]of Object.entries(troops)){const n=number(count);if(n<=0)continue;total+=n;const type=number(state.troop_defs?.find(t=>number(t.code)===number(code))?.type);if(type>=1&&type<=3)types.set(type,(types.get(type)||0)+n);}
      const labels={1:'Infanterie',2:'Bogenschützen',3:'Kavallerie'},arts={1:'march-infantry',2:'march-archer',3:'march-cavalry'},composition=[...types].sort((a,b)=>a[0]-b[0]),signature=JSON.stringify(composition);
      if(actor.dataset.composition!==signature){actor.dataset.composition=signature;actor.querySelector('.atlas-party-units').innerHTML=composition.map(([type,count])=>`<span class="atlas-party-type atlas-party-type--${type}" data-type="${type}" data-count="${count}"><img src="${asset(arts[type])}" alt=""><img src="${asset(arts[type])}" alt=""></span>`).join('')||`<span class="atlas-party-unknown">${icon('flag')}</span>`;}
      // march_skin is the immutable server snapshot taken at dispatch. Never derive an active
      // march from the player's currently equipped skin: changing equipment affects only new marches.
      const skinId=typeof march.march_skin==='string'?march.march_skin.trim():'';
      const skinCatalog=window.ConquerMarchSkins,knownSkin=skinId&&skinCatalog?.ids?.includes(skinId),skin=knownSkin?skinCatalog.get(skinId):null;
      const articulated=!!skinCatalog?.hasMotion?.(skinId),flightLayout=!!skinCatalog?.hasFlightLayout?.(skinId),skinSource=skin?skinCatalog.image(context.base,skinId):'';
      if(actor.dataset.marchSkin!==skinId){
        actor.dataset.marchSkin=skinId;actor.dataset.skinSource='';actor.dataset.skinSourceKind='';actor.dataset.motionFailed='';actor.classList.remove('is-skinned','is-skin-fallback');
        const skinImage=actor.querySelector('.atlas-party-skin img');skinImage.style.animation='';skinImage.removeAttribute('src');
        if(skinSource){actor.style.setProperty('--march-accent',skin.effect_color||skin.effectColor||'#e4af38');setMarchSkinSource(actor,skinSource,'still');}else actor.style.removeProperty('--march-accent');
        actor.dataset.marchEffect=skinCatalog?.effect?.(skinId)?.kind||'dust';actor.dataset.locomotion=skinCatalog?.locomotion?.(skinId)||'walk';actor.style.setProperty('--march-phase',`${-(hash(number(march.id),3)*.8)}s`);
      }
      actor.classList.toggle('has-motion-animation',articulated);actor.classList.toggle('has-flight-animation',flightLayout);
      const end=returning?stamp(march.return_time):stamp(march.arrival_time),start=returning?end-returnDuration:stamp(march.departure_time);
      const old=actor.travel,arrival=stamp(march.arrival_time),attack=march.march_type==='rally'||[5,7,13,15].includes(number(march.march_type));
      if(old&&!old.returning&&returning&&now>=old.arrival)impact(old,frame);
      const label=march.state==='gathering'?'Rally sammelt':returning?'Rückkehr':march.march_type==='rally'?'Rally':Number(march.march_type)===9||march.march_type==='gather'?'Sammeltrupp':'Feldzug';
      actor.travel={from,to,offset,start,end,arrival,returning,state:march.state,attack,destination,skin,skinId,total,label,description:composition.map(([type,count])=>`${format(count)} ${labels[type]}`).join(', '),impactKey:`${state.world?.id||state.city.world_id||state.city.id}:${key}:${march.departure_time}`,observed:old?.observed||(!returning&&now<arrival),previous:old?.previous??null};
      actor.label.textContent='';
      actor.style.left='0px';actor.style.top='0px';actor.style.setProperty('--party-scale',String(clamp(memory.zoom,.7,1.35)));actor.classList.toggle('is-returning',returning);actor.classList.toggle('is-westbound',b[0]<a[0]);
    }
    for(const [key,node]of view.partyNodes)if(!present.has(key)){if(node.travel&&!node.travel.returning&&now>=node.travel.arrival&&now-node.travel.arrival<2000)impact(node.travel,frame);node.remove();view.partyNodes.delete(key);}const markup=lines.join('');if(view.routes.innerHTML!==markup)view.routes.innerHTML=markup;
    if(view.followId&&!(state.marches||[]).some(m=>String(m.id)===view.followId))stopFollowing(true);
    view.marchHud?.sync(state);
    moveMarches(frame);
  }
  function moveMarches(){
    // Pair wall-clock samples with the actual sampling instant. An RAF's
    // timestamp may precede terrain painting or decoding by hundreds of ms;
    // mixing it with Date.now() incorrectly looks like a server clock jump.
    const frame=performance.now();
    const dt=Math.min(100,Math.max(0,frame-(view.motionFrame??frame)));view.motionFrame=frame;
    const correction=view.clockCorrection||0;
    view.clockCorrection=correction-clamp(correction*(1-Math.exp(-dt/600)),-dt*.2,dt*.2);
    const now=context.now()+view.clockCorrection;view.clockNow=now;view.clockFrame=frame;
    updateFollowCamera(now,frame);
    for(const actor of view.partyNodes.values()){
      const t=actor.travel;if(!t)continue;
      const raw=t.state==='gathering'?0:t.state==='resolving'?1:clamp((now-t.start)/Math.max(1,t.end-t.start),0,1);
      // A rounded server clock must not briefly reverse a marching formation.
      const p=Number.isFinite(raw)?Math.max(raw,t.previous?.state===t.state?t.previous.p:0):0;
      if(!t.returning&&p<1&&t.state!=='gathering'&&!document.hidden)t.observed=true;
      if(!t.returning&&p>=1&&t.state!=='gathering')impact(t,frame);
      t.previous={state:t.state,p};
      const x=t.from[0]+(t.to[0]-t.from[0])*p,y=t.from[1]+(t.to[1]-t.from[1])*p,[px,py]=project(x,y);
      actor.worldPoint=[x,y];actor.classList.toggle('is-followed',view.followId===actor.dataset.marchId);actor.querySelector('.atlas-march-hit')?.setAttribute('aria-pressed',String(view.followId===actor.dataset.marchId));
      const visible=px>-140&&py>-140&&px<view.width+140&&py<view.height+140,moving=visible&&p<1&&t.state!=='gathering'&&!motionReduced();
      actor.hidden=!visible;actor.classList.toggle('is-moving',moving);
      if(actor.classList.contains('has-motion-animation')){
        const catalog=window.ConquerMarchSkins,motionSource=catalog.motionImage(context.base,t.skinId),useMotion=moving&&!actor.dataset.motionFailed;
        setMarchSkinSource(actor,useMotion?motionSource:catalog.image(context.base,t.skinId),useMotion?'motion':'still');
      }
      if(actor.classList.contains('has-flight-animation')){
        // Anticipation is cosmetic: the route, arrival time and followed point
        // remain authoritative. Banking stops immediately on an early recall.
        const remaining=(1-p)*Math.max(1,t.end-t.start),approach=moving&&t.attack&&!t.returning&&t.state==='marching'?clamp(1-remaining/850,0,1):0;
        const pose=actor.querySelector('.atlas-party-skin');pose.style.translate=`0 ${-Math.sin(approach*Math.PI)*20}px`;pose.style.rotate=`${(Math.sin(approach*Math.PI)*-10+Math.sin(approach*Math.PI*2)*3)*(actor.classList.contains('is-westbound')?-1:1)}deg`;
        actor.style.setProperty('--march-charge',String(approach*.8));
      }
      if(visible)actor.style.transform=`translate3d(${px}px,${py}px,0) translate(-50%,-65%) scale(${clamp(memory.zoom,.7,1.35)})`;
      if(!actor.biomePoint||Math.hypot(x-actor.biomePoint.x,y-actor.biomePoint.y)>.25){const biome=window.ConquerLandscape.biomeAt(x,y);actor.biomePoint={x,y};actor.dataset.marchBiome=biome.id;actor.style.setProperty('--march-soil',biome.id==='ice'?biome.treeLight:biome.road);}
      const label=p===1&&!t.returning?'Am Ziel':t.label,text=`${label} · ${format(t.total)}`;
      if(actor.label.textContent!==text){actor.label.textContent=text;actor.setAttribute('aria-label',`${t.skin?.name?`${t.skin.name}. `:''}${label}, ${format(t.total)} Truppen. ${t.description}`);}
    }
    for(const effect of [...view.impacts]){
      if(frame-effect.started>(effect.duration||1500)||motionReduced()){effect.dispose?.();effect.node.remove();view.impacts.splice(view.impacts.indexOf(effect),1);continue;}
      effect.paint?.(frame-effect.started);
      const [x,y]=project(...effect.point);effect.node.style.transform=`translate3d(${x}px,${y}px,0) scale(${clamp(memory.zoom,.7,1.35)})`;
    }
  }
  function impact(t,frame){
    if(!t.attack||!t.observed||seenImpacts.has(t.impactKey))return;
    seenImpacts.add(t.impactKey);if(seenImpacts.size>128)seenImpacts.delete(seenImpacts.values().next().value);
    const [x,y]=project(...t.destination);if(motionReduced()||x<-80||y<-80||x>view.width+80||y>view.height+80)return;
    if(view.impacts.length>=4){const expired=view.impacts.shift();expired.dispose?.();expired.node.remove();}
    const theme=window.ConquerMarchSkins?.effect?.(t.skinId)||{kind:'dust',symbol:'✦'},node=document.createElement('div');node.className='atlas-march-impact';node.dataset.marchEffect=theme.kind;node.setAttribute('aria-hidden','true');node.style.setProperty('--march-accent',t.skin?.effect_color||'#e4af38');
    const biome=window.ConquerLandscape.biomeAt(...t.destination).id;
    const effects=window.ConquerMarchEffects,art=effects?.create?.(t.skinId,{biome})||effects?.[t.skinId]?.({biome})||null;
    if(art){node.append(art.canvas);node.dataset.marchSkin=t.skinId;art.paint(0);}
    else node.innerHTML=`<span class="atlas-impact-ring"></span><span class="atlas-impact-ring is-echo"></span><span class="atlas-impact-emblem">${theme.symbol}</span>`+Array.from({length:12},(_,i)=>{const angle=i*Math.PI/6;return `<i style="--burst-x:${Math.cos(angle)*(48+i%3*15)}px;--burst-y:${Math.sin(angle)*(28+i%3*12)-22}px;--burst-turn:${i*47}deg"></i>`;}).join('');
    view.parties.append(node);view.impacts.push({node,point:t.destination,started:frame,duration:art?.duration||1500,paint:art?.paint,dispose:art?.dispose});
  }
  function paint(){if(!view?.el.isConnected)return;view.cameraDirty=false;shiftScenery(0,0);view.sceneCenter={x:memory.x,y:memory.y};const width=view.width,height=view.height;view.width+=view.terrainPad*2||0;view.height+=view.terrainPad*2||0;try{terrain();}finally{view.width=width;view.height=height;}atmosphere();positionMarkers();positionActions();minimap();marches();view.el.querySelector('.atlas-coordinates').textContent=`X ${Math.round(memory.x)} · Y ${Math.round(memory.y)}`;const jump=view.el.querySelector('.atlas-jump');if(!jump.contains(document.activeElement)){jump.elements.x.value=Math.round(memory.x);jump.elements.y.value=Math.round(memory.y);}view.el.querySelector('.atlas-zoom-value').textContent=`${Math.round(memory.zoom*100)}%`;view.el.querySelector('[data-atlas="zoom-in"]').disabled=memory.zoom>=MAX_ZOOM;view.el.querySelector('[data-atlas="zoom-out"]').disabled=memory.zoom<=MIN_ZOOM;updateFooter();}
  function shiftScenery(x,y){for(const layer of [view.canvas,view.markers,view.creatures,view.routes,view.el.querySelector('.atlas-cell-focus')])if(layer)layer.style.translate=`${x}px ${y}px`;}
  function panBufferedScene(){
    const center=view.sceneCenter||{x:memory.x,y:memory.y},dx=(center.x-memory.x)*scale(),dy=(center.y-memory.y)*scale();
    // The terrain canvas contains an off-screen border specifically so a drag
    // can reuse the last frame. Repaint only as that border is approached.
    if(Math.abs(dx)>view.terrainPad*.65||Math.abs(dy)>view.terrainPad*.65){paint();return;}
    shiftScenery(dx,dy);moveMarches();
  }
  function stopFollowing(restoreFocus=false){
    if(!view?.followId)return;view.followId=null;view.followAnchor=null;view.marchHud?.sync(context.state);view.cameraDirty=true;if(restoreFocus)view.viewport.focus({preventScroll:true});
  }
  function followMarch(id){
    const m=context.state.marches?.find(m=>String(m.id)===String(id));if(!m)return;
    clearSelection(false);view.followId=String(id);view.marchHud?.sync(context.state);view.followAnchor=view.marchHud?.anchor()||{x:view.width*.5,y:view.height*.45};view.followFrame=performance.now();
    const point=view.partyNodes.get(String(id))?.worldPoint||[number(m.target_x)+.5,number(m.target_y)+.5];
    if(Math.hypot(point[0]-memory.x,point[1]-memory.y)*scale()>Math.max(view.width,view.height)){memory.x=clamp(point[0],0,255);memory.y=clamp(point[1],0,255);paint();}
    view.followNotify={x:memory.x,y:memory.y};notify();view.el.querySelector('.world-march-card [data-march-command="close"]')?.focus({preventScroll:true});
  }
  function locateMarchTarget(march){
    const type=number(march.march_type),targetType=number(march.target_type),id=String(march.target_id);
    let key=type===9?`nodes:${id}`:type===6?`charms:${id}`:[13,14].includes(type)?`shrine:${id}`:targetType===3?`monsters:${id}`:targetType===2?`players:${id}`:null;
    if([13,14].includes(type)&&!view.targetMap.has(key)&&String(context.state.congress?.id)===id)key='congress';
    if(!view.targetMap.has(key)){const kinds=type===9?['nodes']:type===6?['charms']:[13,14].includes(type)?['shrine','congress']:targetType===3?['monsters']:targetType===2?['players']:[];key=view.targets.find(target=>kinds.includes(target.kind)&&target.x===number(march.target_x)&&target.y===number(march.target_y))?.key||key;}
    if(key&&view.targetMap.has(key)){select(key,true);return;}
    stopFollowing();focus(number(march.target_x)+.5,number(march.target_y)+.5);
  }
  function updateFollowCamera(now,frame){
    if(!view.followId||view.followPainting)return;
    const march=context.state.marches?.find(m=>String(m.id)===view.followId);if(!march){stopFollowing(true);return;}
    const travel=view.partyNodes.get(view.followId)?.travel;let point=[number(march.target_x)+.5,number(march.target_y)+.5];
    if(travel){const raw=travel.state==='gathering'?0:travel.state==='resolving'?1:clamp((now-travel.start)/Math.max(1,travel.end-travel.start),0,1),p=Math.max(Number.isFinite(raw)?raw:0,travel.previous?.state===travel.state?travel.previous.p:0);point=travel.from.map((v,i)=>v+(travel.to[i]-v)*p);}
    const anchor=view.followAnchor||(view.followAnchor=view.marchHud?.anchor()||{x:view.width*.5,y:view.height*.45}),dt=Math.min(50,Math.max(0,frame-(view.followFrame??frame)));view.followFrame=frame;
    const factor=motionReduced()?1:1-Math.exp(-dt/180),x=clamp(point[0]+(view.width/2-anchor.x)/scale(),0,255),y=clamp(point[1]+(view.height/2-anchor.y)/scale(),0,255);
    memory.x+=(x-memory.x)*factor;memory.y+=(y-memory.y)*factor;
    const dx=(view.sceneCenter.x-memory.x)*scale(),dy=(view.sceneCenter.y-memory.y)*scale();
    if(Math.abs(dx)>view.terrainPad*.65||Math.abs(dy)>view.terrainPad*.65){view.followPainting=true;try{paint();}finally{view.followPainting=false;}}
    else shiftScenery(dx,dy);
    if(view.followNotify&&Math.hypot(memory.x-view.followNotify.x,memory.y-view.followNotify.y)>3){view.followNotify={x:memory.x,y:memory.y};notify();}
  }
  function atmosphere(){
    if(!view?.atmosphereCtx)return;const c=view.atmosphereCtx;c.clearRect(0,0,view.width,view.height);
    const [left,top]=unproject(0,0),[right,bottom]=unproject(view.width,view.height);
    window.ConquerLandscape.ambience?.(c,project,scale(),{left,top,right,bottom},view.ambienceTime);
  }
  function animate(time){
    if(!view?.el.isConnected||!sceneVisible||document.hidden){frameId=0;view&&(view.lastAmbience=null);return;}frameId=requestAnimationFrame(animate);
    if(document.hidden){view.lastAmbience=null;return;}
    syncMotion();
    if(view.cameraDirty){paint();updateSidebar();}
    moveMarches(time);
    const slow=motionPreference.matches||document.body.classList.contains('reduced-motion');
    if(slow)view.lastAmbience=null;
    else if(view.lastAmbience===null||time-view.lastAmbience>=100){
      if(view.lastAmbience!==null)view.ambienceTime+=Math.min((time-view.lastAmbience)/1000,.25);
      view.lastAmbience=time;atmosphere();
    }
    if(time-view.lastAnimation<1000)return;view.lastAnimation=time;updateGatheringTimers();view.marchHud?.sync(context.state);
  }
  function getCenter(){return memory.x===null?null:{x:Math.round(memory.x),y:Math.round(memory.y),radius:clamp(Math.ceil(Math.max(view?.width||1,view?.height||1)/scale()/2+6),12,60)};}
  function focus(x,y){if(!view?.el.isConnected)return;moveTo(number(x),number(y));clearSelection(false);view.viewport.focus({preventScroll:true});}
  function locate(x,y,kinds=[],id=null){
    if(!view?.el.isConnected)return false;
    const wanted=Array.isArray(kinds)?kinds:[kinds],atLocation=view.targets.filter(target=>target.x===number(x)&&target.y===number(y));
    const target=(id==null?null:atLocation.find(entry=>String(entry.id)===String(id)&&(!wanted.length||wanted.includes(entry.kind))))
      ||wanted.map(kind=>atLocation.find(entry=>entry.kind===kind)).find(Boolean)
      ||atLocation[0];
    if(target){select(target.key,true);return true;}
    focus(x,y);return false;
  }
  function setVisible(visible){sceneVisible=Boolean(visible);if((!sceneVisible||document.hidden)&&frameId){cancelAnimationFrame(frameId);frameId=0;}else if(sceneVisible&&!document.hidden&&view?.el.isConnected&&!frameId){view.lastAnimation=performance.now();frameId=requestAnimationFrame(animate);}}
  return {render,getCenter,focus,locate,followMarch,setVisible};
})();

import {buildingActions,createBuildingMenus} from './building-menus.js?v=compact3';
const base=window.CONQUER_PLAY.base;
const $=id=>document.getElementById(id),dialog=$('upgrade-dialog');
const command=$('building-command');command.append(dialog);
const resourceIcons={food:'🌾',lumber:'🪵',stone:'🪨',gold:'🪙'};
const names={food:'Nahrung',lumber:'Holz',stone:'Stein',gold:'Gold'};
const fmt=n=>Math.floor(Number(n)).toLocaleString('de-DE');
let state=null,receivedAt=0,online=false,busy=false,loading=null,selected='castle',previousLevel=null,openReadyAt=0,appVisible=true;
const serverNow=()=>state?state.server_time+(performance.now()-receivedAt)/1000:0;
const utc=value=>Date.parse(value.replace(' ','T')+'Z')/1000;
function duration(seconds){const s=Math.max(0,Math.ceil(seconds));return s>=3600?`${Math.floor(s/3600)} Std. ${Math.floor(s%3600/60)} Min.`:s>=60?`${Math.floor(s/60)} Min. ${s%60} Sek.`:`${s} Sek.`;}
function text(tag,content,className){const el=document.createElement(tag);el.textContent=content;if(className)el.className=className;return el;}
async function api(path,body){
  const controller=new AbortController(),timeout=setTimeout(()=>controller.abort(),12000);
  try{
    const response=await fetch(base+path,{method:body?'POST':'GET',credentials:'same-origin',cache:'no-store',signal:controller.signal,headers:body?{'Content-Type':'application/json','X-CSRF-Token':state?.player.csrf??'','X-World-ID':String(window.CONQUER_PLAY.world)}:{'X-World-ID':String(window.CONQUER_PLAY.world)},body:body?JSON.stringify(body):undefined});
    const data=await response.json();
    if(!response.ok||!data.ok){const error=new Error(data.error?.message??'Der Spielstand konnte nicht geladen werden.');error.status=response.status;throw error;}
    return data.data;
  }finally{clearTimeout(timeout);}
}
function connection(message,ok){online=ok;$('connection').textContent=message;$('connection').classList.toggle('offline',!ok);$('retry').hidden=ok;}
async function refresh({fresh=false}={}){
  // A read started before a purchase cannot confirm that purchase's result.
  if(fresh&&loading)await loading;
  if(loading)return loading;
  loading=(async()=>{try{
    const next=await api('/api/city3d/state');state=next;receivedAt=performance.now();connection('Spielstand gespeichert · '+state.player.name,true);
    if(previousLevel!==null&&state.buildings.castle.level>previousLevel)$('action-message').textContent=`Fertig! Deine Festung hat Stufe ${state.buildings.castle.level} erreicht.`;
    previousLevel=state.buildings.castle.level;render();return true;
  }catch(error){connection(error.status===401?'Bitte erneut anmelden.':'Verbindung unterbrochen. Deine Aufträge bleiben gespeichert.',false);$('retry').textContent=error.status===401?'Anmelden':'Erneut verbinden';$('retry').onclick=error.status===401?()=>location.assign(base+'/'):()=>refresh();render();return false;}
  finally{loading=null;}})();return loading;
}
function queueFor(code){return state?.build_queue.find(q=>q.building_code===code);}
function render(){
  if(!state){$('start-upgrade').disabled=true;return;}
  $('resource-bar').replaceChildren(...Object.entries(names).map(([key,name])=>{const e=text('span','');e.title=name;e.setAttribute('aria-label',name+': '+fmt(state.resources[key]));const icon=text('i',resourceIcons[key]);icon.setAttribute('aria-hidden','true');e.append(icon,text('b',fmt(state.resources[key])));return e;}));
  $('castle-level').textContent=`FESTUNG · STUFE ${state.buildings.castle.level}`;
  window.dispatchEvent(new CustomEvent('conquer-hud-state',{detail:state}));
  const queue=queueFor('castle');
  window.CONQUER_CITY_STATE={level:state.buildings.castle.level,building:!!queue,city_skin:state.city?.city_skin};
  window.dispatchEvent(new CustomEvent('conquer-city-state',{detail:window.CONQUER_CITY_STATE}));
  // The server also lists buildings that do not have a label in this scene.
  for(const code of Object.keys(state.buildings)){const label=document.querySelector(`#label-${code} .building-level`);if(label)label.textContent=`Lv. ${state.buildings[code].level}`;}
  const watchTowerLabel=document.querySelector('#label-watch_tower .building-level');if(watchTowerLabel&&!state.buildings.watch_tower)watchTowerLabel.textContent='Vorschau';
  renderCommandHeading();
  if(!dialog.hidden)renderDialog();tick();
}
function renderCommandHeading(){
  const building=state?.buildings?.[selected],label=document.querySelector(`#label-${selected} .building-name`);
  $('building-command-name').textContent=building?.name??label?.childNodes[0]?.textContent?.trim()??'Gebäude';
  $('building-command-level').textContent=building?`Stufe ${building.level}`:selected==='watch_tower'?'Vorschau':'';
}
function renderDialog(){
  if(!state)return;
  const b=state.buildings[selected],queue=queueFor(selected);
  if(!b){
    $('upgrade-title').textContent='Wachturm';$('upgrade-benefit').textContent='Überblick über die Stadt und ihre Umgebung. Die Spielfunktion ist noch nicht angebunden.';
    $('upgrade-duration').textContent='Noch nicht ausbaubar.';
    for(const id of ['upgrade-costs','upgrade-requirements','upgrade-reason','prerequisite-actions'])$(id).replaceChildren();
    $('start-upgrade').disabled=true;$('start-upgrade').textContent='Noch nicht verfügbar';return;
  }
  $('upgrade-title').textContent=`${b.name} · Stufe ${b.level}${b.cost?' → '+(b.level+1):''}`;
  $('upgrade-benefit').textContent=`Gebäudemacht: ${fmt(b.power)}${b.cost?' → '+fmt(b.next_power):''}.`;
  $('upgrade-duration').textContent=b.cost?`Bauzeit: ${duration(b.seconds)}`:'Dieses Gebäude ist vollständig ausgebaut.';
  $('upgrade-costs').replaceChildren(...Object.entries(b.cost??{}).filter(([,n])=>n>0).map(([key,amount])=>{
    const e=text('div',names[key],`cost${!queue&&state.resources[key]<amount?' missing':''}`);e.append(text('strong',fmt(amount)),text('small',queue?'Bereits bezahlt':`Vorhanden: ${fmt(state.resources[key])}`));return e;
  }));
  for(const item of b.item_requirements??[]){const e=text('div',item.name,`cost${!queue&&!item.met?' missing':''}`);e.append(text('strong',fmt(item.count)),text('small',queue?'Bereits bezahlt':`Vorhanden: ${fmt(item.owned)}`));$('upgrade-costs').append(e);}
  $('upgrade-requirements').replaceChildren(...(b.cost?b.requirements:[]).map(req=>text('div',`${req.met?'✓':'○'} ${req.name} Stufe ${req.level} · aktuell ${req.current}`)));
  $('upgrade-reason').textContent=queue?'':b.reasons.filter(reason=>!reason.includes('muss Stufe')).join(' ');
  $('prerequisite-actions').replaceChildren(...b.requirements.filter(req=>!req.met&&b.cost).map(req=>{const button=text('button',`${req.name} ansehen`);button.onclick=()=>{open(req.code);window.dispatchEvent(new CustomEvent('conquer-focus-building',{detail:{code:req.code}}));showDetails(false);};return button;}));
  $('start-upgrade').disabled=busy||!online||!b.can_upgrade||performance.now()<openReadyAt;
  $('start-upgrade').textContent=busy?'Ausbau wird gespeichert …':!online?'Verbindung erforderlich':queue?'Ausbau läuft':b.cost?`Auf Stufe ${b.level+1} ausbauen`:'Höchste Stufe erreicht';
}
function tick(){
  if(!state)return;
  const queue=queueFor('castle')??state.build_queue[0],progress=$('build-progress');
  progress.hidden=!queue;
  if(queue){const left=utc(queue.finishes_at)-serverNow(),total=utc(queue.finishes_at)-utc(queue.started_at);progress.value=Math.min(1,Math.max(0,1-left/Math.max(1,total)));$('build-status').textContent=`${state.buildings[queue.building_code].name} → Stufe ${queue.level_to} · ${left>0?duration(left):'Abschluss wird bestätigt …'}`;}
  else $('build-status').textContent='Bauplatz frei · Bereit für deinen nächsten Ausbau.';
  if(!dialog.hidden){const q=queueFor(selected);if(q)$('upgrade-reason').textContent=`Ausbau gespeichert · ${utc(q.finishes_at)>serverNow()?duration(utc(q.finishes_at)-serverNow()):'Abschluss wird bestätigt …'}`;}
  renderActivities();
}
function renderActivities(){
  for(const code of Object.keys(state.buildings)){
    const list=document.querySelector(`#label-${code} .activity-list`),jobs=[];
    for(const q of state.build_queue.filter(q=>q.building_code===code))jobs.push({...q,kind:'build',label:`Ausbau → ${q.level_to}`});
    if(code==='academy')for(const q of state.research_queue??[])jobs.push({...q,kind:'research',label:`Forschung → ${q.level_to}`});
    if(['barrack','archery_range','stable'].includes(code))for(const q of (state.troop_queue??[]).filter(q=>(state.troop_defs.find(t=>Number(t.code)===Number(q.troop_code))?.training_building||'barrack')===code))jobs.push({...q,kind:'training',label:`Training · ${q.count} Truppen`});
    if(!list)continue;
    const signature=jobs.map(q=>`${q.kind}:${q.id}:${q.finishes_at}`).join('|');
    if(list.dataset.signature!==signature||!list.childNodes.length){
      list.dataset.signature=signature;list.replaceChildren();
      if(!jobs.length)list.append(text('span',code==='academy'?'Keine Forschung':['barrack','archery_range','stable'].includes(code)?'Kein Training':'Bauplatz frei','idle-activity'));
      for(const job of jobs){const row=text('div','',`activity ${job.kind}`);row.dataset.job=`${job.kind}:${job.id}`;const line=text('div','', 'activity-line');line.append(text('span',job.label),text('span','', 'remaining'));const bar=document.createElement('progress');bar.max=1;bar.setAttribute('aria-label',job.label);row.append(line,bar);list.append(row);}
    }
    for(const job of jobs){const row=[...list.children].find(r=>r.dataset.job===`${job.kind}:${job.id}`),left=utc(job.finishes_at)-serverNow(),total=utc(job.finishes_at)-utc(job.started_at);row.querySelector('.remaining').textContent=left>0?duration(left):'Abschluss …';row.querySelector('progress').value=Math.max(0,Math.min(1,1-left/Math.max(1,total)));}
  }
}
function open(code='castle'){
  // A tap that opens the dialog must never also purchase the upgrade beneath it.
  openReadyAt=performance.now()+400;setTimeout(()=>{if(!dialog.hidden)renderDialog();},450);
  selected=code;$('action-message').textContent='';
  const anchor=$('label-'+(document.getElementById('label-'+code)?code:'castle'));
  document.querySelectorAll('.building-label').forEach(el=>{el.classList.remove('details-open');el.classList.toggle('selected',el===anchor);el.querySelector('button').setAttribute('aria-expanded',String(el===anchor));});
  const [icon,label]=code==='treasure_house'?['◇','Relikte']:buildingActions[code]??['⌖','Übersicht'];
  $('building-function').querySelector('.action-disc').textContent=icon;$('building-function').title=label;$('building-function').setAttribute('aria-label',label);$('building-function').lastElementChild.textContent=label;
  const extra=$('building-extra-action')||document.createElement('button');extra.id='building-extra-action';
  extra.hidden=!['treasure_house','trading_post'].includes(code);
  extra.dataset.buildingPanel=code==='treasure_house'?'chests':'vip';
  extra.innerHTML=code==='treasure_house'?'<span class="action-disc">▣</span><span>Schatztruhen</span>':'<span class="action-disc">♛</span><span>VIP-Shop</span>';
  extra.setAttribute('aria-label',code==='treasure_house'?'Schatztruhen öffnen':'VIP-Shop öffnen');
  extra.onclick=()=>openBuildingPanel(extra.dataset.buildingPanel);
  command.querySelector('.quick-actions').append(extra);
  command.dataset.code=code;command.hidden=false;dialog.hidden=true;anchor.classList.remove('details-open');renderCommandHeading();renderDialog();if(!state)refresh();
  window.dispatchEvent(new CustomEvent('conquer-reveal-actions',{detail:{code}}));
}
window.addEventListener('conquer-building-select',event=>{const code=event.detail.id==='keep'?'castle':event.detail.id;if(code)open(code);});
document.querySelectorAll('[data-building-code]').forEach(button=>button.onclick=()=>{if(!command.hidden&&selected===button.dataset.buildingCode)close();else open(button.dataset.buildingCode);});
function close(){command.hidden=true;dialog.hidden=true;document.querySelectorAll('.building-label').forEach(el=>{el.classList.remove('selected','details-open');el.querySelector('button').setAttribute('aria-expanded','false');});window.dispatchEvent(new Event('conquer-building-deselect'));}
$('close-building-command').onclick=close;
const menus=createBuildingMenus({getState:()=>state,isConnected:()=>online,api,refresh,closeSelection:close,serverNow,base});
window.addEventListener('conquer-open-order',event=>{
  const {code,kind,troopCode}=event.detail??{};
  if(!state?.buildings[code])return;
  window.dispatchEvent(new CustomEvent('conquer-focus-building',{detail:{code}}));
  open(code);
  if(kind==='build'){if(!embeddedAction('upgrade'))showDetails(false);}
  else if(!embeddedAction('function',troopCode))menus.open(code,{troopCode});
});
window.addEventListener('conquer-map-tap',()=>{menus.close();close();});
function embeddedAction(kind,troopCode){
  if(!(window.CONQUER_PLAY.embedded||window.CONQUER_EMBED)||window.parent===window)return false;
  const code=selected;close();
  window.parent.postMessage(code==='watch_tower'&&kind==='function'?{type:'conquer:navigate',tab:'world'}:{type:kind==='function'?'conquer:building-function':'conquer:building',code,troopCode},location.origin);
  return true;
}
function openBuildingPanel(panel){
  const tab=selected==='treasure_house'?'treasures':'market';
  if((window.CONQUER_PLAY.embedded||window.CONQUER_EMBED)&&window.parent!==window){close();window.parent.postMessage({type:'conquer:building-panel',tab,panel},location.origin);}
  else location.assign(base+'/city#'+tab+(panel==='chests'?'?section=chests':panel==='vip'?'?section=vip':''));
}
$('building-function').onclick=()=>{if(['treasure_house','trading_post'].includes(selected))openBuildingPanel(selected==='treasure_house'?'equipment':'caravan');else if(!embeddedAction('function'))menus.open(selected);};
$('close-upgrade').onclick=()=>{dialog.hidden=true;command.classList.remove('details-open');};
function showDetails(info){dialog.classList.toggle('info-only',info);dialog.hidden=false;command.classList.add('details-open');openReadyAt=performance.now()+400;renderDialog();setTimeout(renderDialog,450);}
$('building-info').onclick=()=>{if(!embeddedAction('details'))showDetails(true);};
$('building-upgrade').onclick=()=>{if(!embeddedAction('upgrade'))showDetails(false);};
document.addEventListener('keydown',event=>{if(event.key==='Escape'){menus.close();close();}});
$('start-upgrade').onclick=async()=>{
  if(busy||!online||!state||performance.now()<openReadyAt)return;
  const code=selected,expected=state.buildings[code].level;busy=true;$('action-message').textContent='';renderDialog();
  try{if(loading)await loading;await api('/api/city3d/upgrade',{building_code:code,expected_level:expected});$('action-message').textContent='Ausbau gestartet. Du kannst das Fenster oder den Browser schließen.';}
  catch(error){$('action-message').textContent=error.status?error.message:'Keine Bestätigung erhalten. Wir prüfen den gespeicherten Stand; bitte nicht mehrfach starten.';}
  finally{await refresh();busy=false;render();}
};
$('retry').onclick=()=>refresh();
$('logout').onclick=async()=>{if(busy||!state)return;busy=true;$('logout').disabled=true;try{await api('/api/auth/logout',{});location.assign(base+'/');}catch{busy=false;$('logout').disabled=false;connection('Abmeldung fehlgeschlagen. Bitte erneut versuchen.',false);render();}};
setInterval(()=>{if(!document.hidden&&appVisible)tick();},1000);
window.ConquerPolling({delay:()=>state?.build_queue?.length?5000:15000,refresh:()=>{if(appVisible&&!busy)return refresh();}});
window.addEventListener('offline',()=>{connection('Offline · Dein gespeicherter Ausbau läuft weiter.',false);render();});
window.addEventListener('message',event=>{
  if(window.CONQUER_PLAY.embedded && event.origin===location.origin && event.source===window.parent && event.data?.type==='conquer:visibility'){
    const resume=!appVisible&&event.data.visible!==false;appVisible=event.data.visible!==false;
    if(resume&&!document.hidden&&!busy&&performance.now()-receivedAt>3000)refresh();
  }
  if(window.CONQUER_PLAY.embedded && event.origin===location.origin && event.source===window.parent && event.data?.type==='conquer:refresh' && !busy)refresh({fresh:true});
});
refresh();

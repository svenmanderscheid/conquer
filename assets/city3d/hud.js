const $=id=>document.getElementById(id),base=window.CONQUER_PLAY.base;
const panel=$('realm-panel'),content=$('realm-panel-content');
const tasksButton=document.querySelector('[data-hud-panel="tasks"]');
const troopTypes={1:'Infanterie',2:'Bogenschützen',3:'Kavallerie'};
const researchNames={food_production:'Reiche Ernte',lumber_production:'Geschickte Holzfäller',wood_production:'Geschickte Holzfäller',stone_production:'Bessere Werkzeuge',gold_production:'Goldene Zeiten',infantry_hp:'Standhafte Infanterie',infantry_atk:'Geschärfte Klingen',ranged_hp:'Ausdauer der Schützen',cavalry_hp:'Starke Reittiere',construction_speed:'Flotte Baumeister',research_speed:'Wissensdurst',gathering_speed:'Fleißige Sammler'};
let snapshot=null,active=null,receivedAt=0,orderList=null,orderEmpty=null;
const orderRows=new Map();
const fmt=value=>Number(value).toLocaleString('de-DE');
const utc=value=>Date.parse(String(value).replace(' ','T')+'Z')/1000;
const serverNow=()=>snapshot?Number(snapshot.server_time)+(performance.now()-receivedAt)/1000:0;
function node(tag,value,className){const el=document.createElement(tag);el.textContent=value;if(className)el.className=className;return el;}
function link(label,hash){const el=node('a',label);el.href=base+'/city#'+hash;el.className='realm-link';return el;}
function duration(seconds){const s=Math.max(0,Math.ceil(seconds));return s>=3600?`${Math.floor(s/3600)} Std. ${Math.floor(s%3600/60)} Min.`:s>=60?`${Math.floor(s/60)} Min. ${s%60} Sek.`:`${s} Sek.`;}
function openOrder(code,kind){panel.close();window.dispatchEvent(new CustomEvent('conquer-open-order',{detail:{code,kind}}));}
function currentOrders(){
  if(!snapshot)return [];
  return [
    ...(snapshot.build_queue??[]).map(q=>({...q,kind:'build',code:q.building_code,title:`${snapshot.buildings[q.building_code]?.name??'Gebäude'} → Stufe ${q.level_to}`,meta:'Ausbau'})),
    ...(snapshot.research_queue??[]).map(q=>({...q,kind:'research',code:'academy',title:`${researchNames[q.research_code]??'Forschung'} → Stufe ${q.level_to}`,meta:'Forschung · Akademie'})),
    ...(snapshot.troop_queue??[]).map(q=>{
      const troop=snapshot.troop_defs?.find(t=>Number(t.code)===Number(q.troop_code));
      return {...q,kind:'training',code:troop?.training_building||'barrack',title:`${fmt(q.count)} × ${troopTypes[troop?.type]??'Truppen'}${troop?' · Stufe '+troop.tier:''}`,meta:'Ausbildung · '+(snapshot.buildings[troop?.training_building||'barrack']?.name||'Kaserne')};
    }),
  ].sort((a,b)=>utc(a.finishes_at)-utc(b.finishes_at)||Number(a.id)-Number(b.id));
}
function updateOrderButton(){
  const count=currentOrders().length;
  tasksButton.lastElementChild.textContent=count?`Aufträge · ${count}`:'Aufträge';
  tasksButton.setAttribute('aria-label',snapshot?`Laufende Aufträge öffnen · ${count} ${count===1?'Auftrag':'Aufträge'}`:'Laufende Aufträge öffnen');
}
function tickOrders(){
  if(!panel.open||active!=='tasks'||!snapshot)return;
  for(const {job,remaining,progress} of orderRows.values()){
    const finish=utc(job.finishes_at),start=utc(job.started_at),left=finish-serverNow();
    remaining.textContent=left>0?duration(left):'Abschluss wird bestätigt …';
    progress.value=Math.max(0,Math.min(1,1-left/Math.max(1,finish-start)));
    progress.setAttribute('aria-valuetext',left>0?`${Math.round(progress.value*100)} % · ${duration(left)} verbleibend`:'Abschluss wird bestätigt');
  }
}
function syncOrders(){
  if(!orderList)return;
  const jobs=currentOrders(),keys=new Set(jobs.map(job=>`${job.kind}:${job.id}`));
  const focused=document.activeElement;
  orderEmpty.hidden=!!jobs.length;
  orderEmpty.textContent=snapshot?'Gerade laufen keine Aufträge. Starte einen Ausbau an einem Gebäude, eine Forschung in der Akademie oder eine Ausbildung in der Kaserne.':'Deine Aufträge werden geladen …';
  for(const [key,row] of orderRows){if(!keys.has(key)){row.element.remove();orderRows.delete(key);}}
  jobs.forEach((job,index)=>{
    const key=`${job.kind}:${job.id}`;
    let row=orderRows.get(key);
    if(!row){
      const element=node('article','',`order-card ${job.kind}`),heading=node('button','','order-heading');
      heading.type='button';
      const meta=node('div','','order-meta'),kind=node('span',''),remaining=node('span','');
      remaining.dataset.orderRemaining='';
      const progress=document.createElement('progress');progress.max=1;
      meta.append(kind,remaining);element.append(heading,meta,progress);
      row={element,heading,kind,remaining,progress,job};orderRows.set(key,row);
      heading.onclick=()=>openOrder(row.job.code,row.job.kind);
    }
    row.job=job;
    if(row.heading.textContent!==job.title)row.heading.textContent=job.title;
    row.heading.setAttribute('aria-label',`${job.title} · ${job.meta} ansehen`);
    row.kind.textContent=job.meta;
    row.progress.setAttribute('aria-label',`${job.title} · Fortschritt`);
    // Keep existing controls in place across polls so keyboard focus is stable.
    if(orderList.children[index]!==row.element)orderList.insertBefore(row.element,orderList.children[index]??null);
  });
  if(focused&&focused!==document.activeElement){
    if(focused.isConnected)focused.focus({preventScroll:true});
    else if(panel.open)$('realm-panel-close').focus({preventScroll:true});
  }
  tickOrders();
}
const missing={inventory:['Inventar','Hier findest du später Ressourcenpakete, Truhen und deine Speedups.','Das Inventar ist in dieser Stadtansicht noch nicht verfügbar.'],alliance:['Allianz','Hier werden Mitglieder, Allianz-Hilfe und gemeinsame Shrine-Events erreichbar sein.','Die Allianzverwaltung ist in dieser Stadtansicht noch nicht verfügbar.']};
function render(){
  content.replaceChildren();orderRows.clear();orderList=null;orderEmpty=null;$('profile-tools').hidden=active!=='profile';
  if(active==='tasks'){
    $('realm-panel-title').textContent='Laufende Aufträge';
    orderList=node('div','','order-list');orderEmpty=node('p','','order-empty');
    content.append(orderList,orderEmpty);syncOrders();
  }else if(active==='profile'){
    $('realm-panel-title').textContent='Dein Profil';
    content.append(node('h3',snapshot?.player.name??'Spielstand wird geladen …'));
    if(snapshot){
      const power=Object.values(snapshot.buildings).reduce((total,b)=>total+Number(b.power),0);
      content.append(node('p',`Festung Stufe ${snapshot.buildings.castle.level} · ${power.toLocaleString('de-DE')} Gebäudemacht`));
      content.append(node('p',snapshot.city.name));
    }
    const buildings=node('button','Gebäude finden');buildings.className='realm-link';buildings.onclick=()=>{
      content.replaceChildren();$('realm-panel-title').textContent='Gebäude finden';$('profile-tools').hidden=true;
      for(const [code,b] of Object.entries(snapshot?.buildings??{})){
        const button=node('button',b.name+' · Stufe '+b.level);button.className='realm-link';
        button.onclick=()=>{panel.close();window.dispatchEvent(new CustomEvent('conquer-focus-building',{detail:{code}}));};content.append(button);
      }
    };content.append(buildings);
    const troops=node('button','Truppen ansehen','realm-link');troops.type='button';troops.onclick=()=>openOrder('barrack','training');
    content.append(troops,link('Forschung öffnen','research'),link('Spielübersicht','city'));
  }else{
    const [title,description,status]=missing[active];$('realm-panel-title').textContent=title;
    content.append(node('p',status),node('p',description));
  }
}
function open(key){active=key;render();panel.showModal();tickOrders();}
$('profile-open').onclick=()=>open('profile');
document.querySelectorAll('[data-hud-panel]').forEach(el=>el.onclick=()=>open(el.dataset.hudPanel));
$('realm-panel-close').onclick=()=>panel.close();
const profileTools=$('profile-tools');for(const id of ['logout','metricsToggle','metrics'])profileTools.append($(id));
window.addEventListener('conquer-hud-state',event=>{
  // Re-rendering a stale snapshot after a failed request must not reset its clock.
  if(snapshot!==event.detail){receivedAt=performance.now();snapshot=event.detail;}
  const power=Object.values(snapshot.buildings).reduce((total,b)=>total+Number(b.power),0);
  $('hud-power').textContent=power.toLocaleString('de-DE')+' Gebäudemacht';
  updateOrderButton();
  if(panel.open&&active==='tasks')syncOrders();
});
updateOrderButton();
setInterval(tickOrders,500);

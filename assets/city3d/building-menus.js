export const buildingActions={
 castle:['♜','Stadtübersicht'],wall:['▥','Verteidigung'],farm:['🌾','Produktion'],lumber_camp:['🪵','Produktion'],quarry:['🪨','Produktion'],gold_mine:['🪙','Produktion'],storage:['▣','Vorräte'],treasure_house:['◇','Schätze'],barrack:['⚔','Trainieren'],archery_range:['🏹','Trainieren'],stable:['♞','Trainieren'],hospital:['✚','Heilung'],academy:['✦','Forschen'],trading_post:['⚖','Handel'],hall_of_alliance:['⚑','Allianz'],watch_tower:['⌖','Umgebung']
};
const resources={food:['🌾','Nahrung'],lumber:['🪵','Holz'],stone:['🪨','Stein'],gold:['🪙','Gold']};
const types={1:['Infanterie','knight'],2:['Bogenschützen','archer'],3:['Kavallerie','rider']};
const roman=['','I','II','III','IV','V','VI','VII','VIII','IX','X'];
const fmt=n=>Math.floor(Number(n)||0).toLocaleString('de-DE');
const esc=v=>String(v).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const duration=s=>{s=Math.max(0,Math.ceil(s));return s>=3600?`${Math.floor(s/3600)} Std. ${Math.floor(s%3600/60)} Min.`:s>=60?`${Math.floor(s/60)} Min. ${s%60} Sek.`:`${s} Sek.`;};
export function createBuildingMenus({getState,isConnected,api,refresh,closeSelection,serverNow,base}){
 const modal=document.createElement('dialog');modal.id='building-function-panel';modal.setAttribute('aria-labelledby','function-title');
 modal.innerHTML='<header class="function-head"><button type="button" data-back aria-label="Zurück zur Stadt">←</button><h2 id="function-title"></h2><span class="function-level"></span></header><div class="function-body"></div>';
 document.body.append(modal);const body=modal.querySelector('.function-body'),title=modal.querySelector('h2');
 let active=null,type=1,tier=1,amount=20,posting=false,uncertain=false,message='',readyAt=0,lastCompletedId=null,trainingView='train';
 const notice=document.createElement('aside');notice.className='training-notice';notice.hidden=true;
 notice.innerHTML='<span role="status"></span><button type="button" data-view-training>Training ansehen</button><button type="button" data-dismiss aria-label="Trainingsmeldung schließen">×</button>';
 document.body.append(notice);
 notice.querySelector('[data-dismiss]').onclick=()=>{notice.hidden=true;};
 notice.querySelector('[data-view-training]').onclick=()=>{notice.hidden=true;const troopCode=Number(notice.dataset.troopCode),unit=getState()?.troop_defs?.find(t=>Number(t.code)===troopCode);window.dispatchEvent(new CustomEvent('conquer-open-order',{detail:{code:unit?.training_building??'barrack',kind:'training',troopCode}}));};
 const end=()=>{modal.close();active=null;closeSelection();};modal.querySelector('[data-back]').onclick=end;
 modal.addEventListener('cancel',e=>{e.preventDefault();end();});
 modal.addEventListener('click',e=>{const r=modal.getBoundingClientRect();if(e.target===modal&&(e.clientX<r.left||e.clientX>r.right||e.clientY<r.top||e.clientY>r.bottom))end();});
 const def=()=>getState()?.troop_defs?.find(t=>Number(t.type)===type&&Number(t.tier)===tier);
 const unlocked=t=>Boolean(t?.unlocked);
 const utc=value=>Date.parse(value.replace(' ','T')+'Z')/1000;
 const unitName=code=>{const t=getState().troop_defs.find(t=>Number(t.code)===Number(code));return `${types[t?.type]?.[0]??'Truppen'} · Stufe ${roman[t?.tier]??'?'}`;};
 const jobName=q=>`${fmt(q.count)} × ${unitName(q.troop_code)}`;
 const completionTime=value=>new Date(utc(value)*1000).toLocaleString('de-DE',{day:'2-digit',month:'2-digit',hour:'2-digit',minute:'2-digit'});
 function observeCompletions(){
  if((window.CONQUER_PLAY?.embedded||window.CONQUER_EMBED)&&window.parent!==window)return;
  if(!isConnected())return;
  const completed=getState()?.recent_training??[];
  if(lastCompletedId!==null){
   const added=completed.filter(q=>Number(q.id)>lastCompletedId);
   if(added.length){notice.querySelector('[role=status]').textContent=`Ausbildung abgeschlossen: ${added.map(jobName).join('; ')}.`;notice.dataset.troopCode=String(added[0].troop_code);notice.hidden=false;}
  }
  lastCompletedId=Math.max(lastCompletedId??0,...completed.map(q=>Number(q.id)));
 }
 function maximum(t){return Math.max(0,Math.min(50000,...Object.keys(resources).filter(k=>t['need_'+k]>0).map(k=>Math.floor(Number(getState().resources[k])/t['need_'+k]))));}
 function showTrainingView(view){
  trainingView=view;
  body.querySelector('[data-training-main]').hidden=view!=='train';
  body.querySelector('[data-history-view]').hidden=view!=='history';
  for(const button of body.querySelectorAll('[data-training-view]'))button.setAttribute('aria-pressed',String(button.dataset.trainingView===view));
  title.textContent=view==='history'?'Kaserne · Verlauf':`${types[type][0]} · Stufe ${roman[tier]}`;
  modal.scrollTop=0;
 }
 function updateTraining(){
  if(active!=='barrack'||!modal.open||!def())return;
  const s=getState(),t=def(),max=maximum(t),whole=Number.isInteger(amount)&&amount>=1&&amount<=50000,valid=whole&&amount<=max;
  body.querySelector('[data-owned]').textContent=`In deiner Stadt: ${fmt(s.troops?.[t.code])}`;
  body.querySelector('[data-costs]').innerHTML=Object.entries(resources).map(([k,[icon,name]])=>`<div class="training-cost ${whole&&Number(s.resources[k])<t['need_'+k]*amount?'missing':''}" aria-label="${name}"><span aria-hidden="true">${icon}</span><div><small>${name}</small><strong>${whole?fmt(t['need_'+k]*amount):'–'} <span class="training-stock">/ ${fmt(s.resources[k])}</span></strong></div></div>`).join('');
  const range=body.querySelector('[data-range]'),number=body.querySelector('[data-amount]');range.max=Math.max(1,max);number.max=Math.max(1,max);range.value=Number.isFinite(amount)?amount:1;
  const reason=uncertain?'Bestätigung fehlt. Prüfe Auftrag und Verlauf; lade vor erneutem Training die Seite neu.':!navigator.onLine?'Du bist offline. Gespeichertes Training läuft weiter.':!isConnected()?'Bitte verbinde dich erneut, um Platz und Vorrat zu prüfen.':!unlocked(t)?`Benötigt Ausbildungsgebäude Stufe ${t.unlock_building} und Stadtzentrum Stufe ${t.unlock_castle}.`:s.troop_queue.length?'':max<1?'Es fehlen Ressourcen.':!valid?`Wähle eine ganze Zahl von 1 bis ${fmt(max)}.`:'';
  body.querySelector('[data-reason]').textContent=reason;
  const submit=body.querySelector('[data-train]');submit.disabled=posting||uncertain||!isConnected()||!valid||!unlocked(t)||s.troop_queue.length>0||performance.now()<readyAt||!navigator.onLine;
  submit.textContent=posting?'Training wird gespeichert …':s.troop_queue.length?'Ausbildungsplatz belegt':`Trainieren${whole?' · '+duration(t.time*amount):''}`;
  number.setAttribute('aria-invalid',String(!valid));
  body.querySelector('[data-maximum]').textContent=`Möglich: ${fmt(max)}`;
  for(const control of body.querySelectorAll('[data-amount],[data-range],[data-minus],[data-plus],[data-max],[data-tier],[data-type]'))control.disabled=posting;
  body.querySelector('[data-max]').disabled=posting||max<1;
  body.querySelector('[data-reload]').hidden=!uncertain;
  body.querySelector('[data-recheck]').hidden=isConnected();
  body.querySelector('[data-recheck]').disabled=posting||!navigator.onLine;
  body.querySelector('[data-message]').textContent=message;
  body.querySelector('[data-training-queue]').innerHTML=s.troop_queue.length?s.troop_queue.map(q=>{
   const left=utc(q.finishes_at)-serverNow(),total=utc(q.finishes_at)-utc(q.started_at),progress=Math.max(0,Math.min(1,1-left/Math.max(1,total)));
   return `<div class="training-job"><div class="training-job-heading"><strong>Ausbildung läuft</strong><span>${left>0?duration(left):'Abschluss wird bestätigt …'}</span></div><span>${esc(jobName(q))}</span><progress max="1" value="${progress}" aria-label="Ausbildungsfortschritt"></progress></div>`;
  }).join(''):'<div class="training-slot-free">⚔ Ausbildungsplatz 1 · frei</div>';
  const recent=s.recent_training??[],history=body.querySelector('[data-training-history]');
  const signature=recent.map(q=>q.id).join(',');
  if(history.dataset.signature!==signature){history.dataset.signature=signature;history.innerHTML=recent.length?'<h3>Zuletzt abgeschlossen</h3>'+recent.map(q=>`<div class="training-receipt"><strong>✓ ${esc(jobName(q))}</strong><small>${esc(completionTime(q.finishes_at))} · Bestand gutgeschrieben</small></div>`).join(''):'<p>Noch keine abgeschlossene Ausbildung.</p>';}
  for(const btn of body.querySelectorAll('[data-tier]')){const unit=s.troop_defs.find(d=>Number(d.type)===type&&Number(d.tier)===Number(btn.dataset.tier));btn.querySelector('small').textContent=fmt(s.troops?.[unit?.code]);btn.classList.toggle('locked',!unlocked(unit));btn.setAttribute('aria-label',`Truppenstufe ${btn.dataset.tier}${unlocked(unit)?'':', gesperrt'}`);}
 }
 function renderTraining(){
  const s=getState(),t=def();if(!t){body.textContent='Truppen werden geladen …';return;}
  const [name,art]=types[type];title.textContent=`${name} · Stufe ${roman[tier]}`;
  body.innerHTML=`<div class="training-tabs" role="group" aria-label="Kasernenansicht"><button type="button" data-training-view="train" aria-controls="training-main">Ausbilden</button><button type="button" data-training-view="history" aria-controls="training-history">Verlauf</button></div>
   <section data-training-main id="training-main" aria-label="Truppen ausbilden">
   <div data-training-queue></div>
   <div class="training-types" aria-label="Truppengattung">${Object.entries(types).map(([key,[label]])=>`<button type="button" data-type="${key}" aria-pressed="${Number(key)===type}">${esc(label)}</button>`).join('')}</div>
   <div class="training-layout"><div class="training-unit">
   <div class="training-hero"><img src="${base}/assets/art/${art}.png" alt="${name}"></div>
   <div class="training-unit-info"><p class="training-owned" data-owned></p><div class="training-stat"><span title="Basisangriff" aria-label="Basisangriff ${fmt(t.attack)}">⚔ ${fmt(t.attack)}</span><span title="Basislebenspunkte" aria-label="Basislebenspunkte ${fmt(t.hp)}">♥ ${fmt(t.hp)}</span><span title="Basisverteidigung" aria-label="Basisverteidigung ${fmt(t.defense)}">⛨ ${fmt(t.defense)}</span></div></div></div>
   <div class="training-form">
   <div class="training-tiers" aria-label="Truppenstufe">${s.troop_defs.filter(d=>Number(d.type)===type).map(d=>`<button type="button" data-tier="${d.tier}" aria-label="Truppenstufe ${d.tier}${unlocked(d)?'':', gesperrt'}" aria-pressed="${Number(d.tier)===tier}" class="${unlocked(d)?'':'locked'}"><span>⛨</span><b>${roman[d.tier]}</b><small>0</small></button>`).join('')}</div>
   <div class="training-cost-heading">Kosten / Vorrat</div><div class="training-costs" data-costs></div>
   <div class="training-maximum"><label for="training-amount">Anzahl</label><span data-maximum></span></div>
   <div class="training-quantity"><button type="button" data-minus aria-label="Eine Truppe weniger">−</button><input data-range type="range" min="1" max="50000" step="1" value="${Number.isFinite(amount)?amount:1}" aria-label="Truppenanzahl einstellen"><button type="button" data-plus aria-label="Eine Truppe mehr">+</button><input data-amount id="training-amount" type="number" min="1" max="50000" step="1" value="${Number.isFinite(amount)?amount:''}" aria-label="Truppenanzahl" aria-describedby="training-reason"><button type="button" data-max>Max.</button></div>
   <p data-reason id="training-reason" class="training-reason"></p>
   <button type="button" class="function-primary" data-train></button><p data-message role="status"></p>
   <div class="training-recovery"><button type="button" class="realm-link" data-recheck hidden>Spielstand aktualisieren</button><button type="button" class="realm-link" data-reload hidden>Seite neu laden</button></div>
   </div></div></section>
   <section data-history-view id="training-history" aria-label="Abgeschlossene Ausbildung" hidden><div data-training-history></div><p class="training-history-note">Nach Ablauf wird der gesamte Auftrag gutgeschrieben – auch wenn du die Stadt verlässt.</p></section>`;
  for(const button of body.querySelectorAll('[data-training-view]'))button.onclick=()=>showTrainingView(button.dataset.trainingView);
  showTrainingView(trainingView);
  for(const btn of body.querySelectorAll('[data-tier]'))btn.onclick=()=>{tier=Number(btn.dataset.tier);message='';renderTraining();body.querySelector(`[data-tier="${tier}"]`).focus({preventScroll:true});};
  for(const btn of body.querySelectorAll('[data-type]'))btn.onclick=()=>{type=Number(btn.dataset.type);message='';renderTraining();body.querySelector(`[data-type="${type}"]`).focus({preventScroll:true});};
  const number=body.querySelector('[data-amount]'),range=body.querySelector('[data-range]');
  number.oninput=()=>{amount=number.value===''?NaN:Number(number.value);updateTraining();};range.oninput=()=>{amount=Number(range.value);number.value=amount;updateTraining();};
  function change(delta){amount=Math.max(1,Math.min(Math.max(1,maximum(def())),(Number.isFinite(amount)?amount:1)+delta));number.value=amount;updateTraining();}
  body.querySelector('[data-minus]').onclick=()=>change(-1);body.querySelector('[data-plus]').onclick=()=>change(1);
  body.querySelector('[data-max]').onclick=()=>{amount=maximum(def());number.value=amount;updateTraining();};
  body.querySelector('[data-recheck]').onclick=async()=>{await refresh({fresh:true});updateTraining();};
  body.querySelector('[data-reload]').onclick=()=>location.reload();
  body.querySelector('[data-train]').onclick=async()=>{
   updateTraining();
   if(posting||uncertain||body.querySelector('[data-train]').disabled)return;
   const submitted=amount,code=def().code;posting=true;message='';updateTraining();
   try{await api('/api/troops/train',{troop_code:Number(code),count:submitted,barrack_slot:1});message=`Ausbildungsauftrag gespeichert: ${fmt(submitted)} × ${unitName(code)}.`;}
   catch(error){
    // A server failure can occur after saving, so it is also an uncertain outcome.
    if(!error.status||error.status>=500){uncertain=true;message='Der Auftrag wird nicht erneut gesendet.';}
    else message=error.status===401?'Bitte erneut anmelden.':error.message||'Training nicht möglich. Prüfe Ressourcen, Voraussetzungen und den Ausbildungsplatz.';
   }
   finally{await refresh({fresh:true});posting=false;updateTraining();}
  };
  updateTraining();
 }
 function renderOverview(){
  const s=getState(),b=s.buildings[active];title.textContent=buildingActions[active]?.[1]??'Gebäude';
  const row=(label,value)=>`<div class="function-row"><span>${esc(label)}</span><strong>${esc(value)}</strong></div>`;
  let html=`<h3>${esc(b?.name??'Wachturm')}</h3>`;
  const key={farm:'food',lumber_camp:'lumber',quarry:'stone',gold_mine:'gold'}[active];
  if(key){html+=row('Rohstoff',resources[key].join(' '))+row('Produktion pro Stunde',fmt(s.production_rates?.[active]))+row('Vorrat',fmt(s.resources[key]))+row('Produktionslager',fmt(s.storage_caps?.[key]))+'<p>Die Produktion läuft automatisch, auch während du offline bist.</p>';}
  else if(active==='storage'){html+=Object.entries(resources).map(([key,[icon,name]])=>row(icon+' '+name,fmt(s.resources[key])+' / '+fmt(s.storage_caps?.[key]))).join('')+'<p>Vorrat / Produktionslager.</p>';}
  else if(active==='castle'){html+=row('Gebäude',Object.keys(s.buildings).length)+row('Gebäudemacht',fmt(Object.values(s.buildings).reduce((v,b)=>v+Number(b.power),0)))+row('Truppen in der Stadt',fmt(Object.values(s.troops??{}).reduce((v,n)=>v+Number(n),0)))+row('Bauaufträge',s.build_queue.length);}
  else if(active==='wall'){html+=row('Mauerzustand',fmt(s.city.wall_hp_current)+' / '+fmt(s.city.wall_hp_max))+'<p>Beschädigte Mauern regenerieren sich mit der Zeit.</p>';}
  else if(active==='hospital'){html+='<p>Verwundete Truppen und laufende Heilung.</p><div data-hospital>Wird geladen …</div>';}
  else {html+='<p>'+({treasure_house:'Die Verwaltung deiner Schätze ist in dieser Stadtansicht noch nicht verfügbar.',trading_post:'Handelsaufträge sind in dieser Stadtansicht noch nicht verfügbar.',hall_of_alliance:'Die Allianzverwaltung ist in dieser Stadtansicht noch nicht verfügbar.',watch_tower:'Die Weltkarte zeigt Rohstofffelder und Monster in deiner Umgebung.'}[active]??'')+'</p>';}
  if(active==='watch_tower')html+=`<a class="function-primary" href="${base}/city#world">Weltkarte öffnen</a>`;
  body.innerHTML=html;
  if(active==='hospital')api('/api/hospital/status').then(data=>{if(active!=='hospital')return;const host=body.querySelector('[data-hospital]');host.innerHTML=row('Belegte Betten',fmt(data.used)+' / '+fmt(data.capacity))+(!data.wounded.length?'<p>Alle Truppen sind gesund.</p>':data.wounded.map(w=>{const t=s.troop_defs.find(t=>Number(t.code)===Number(w.troop_code));return row(fmt(w.count)+' × '+(types[t?.type]?.[0]??'Truppen'),[w.healing_count?fmt(w.healing_count)+' in Behandlung':'',w.waiting_count?fmt(w.waiting_count)+' warten':''].filter(Boolean).join(' · '));}).join(''));}).catch(()=>{if(active==='hospital')body.querySelector('[data-hospital]').textContent='Die Heilungsübersicht konnte nicht geladen werden. Bitte öffne sie erneut.';});
 }
 window.addEventListener('conquer-hud-state',()=>{observeCompletions();if(!modal.open)return;if(active==='barrack')updateTraining();else if(active!=='hospital')renderOverview();});
 setInterval(updateTraining,1000);
 return {open(code,{troopCode}={}){
  if(!getState())return;
  if(['barrack','archery_range','stable'].includes(code)){closeSelection();sessionStorage.setItem('conquer-training-building',code);location.assign(base+'/city#army');return;}
  if(code==='academy'){closeSelection();location.assign(base+'/city#research');return;}
  if(code==='barrack'){
   trainingView='train';
   const unit=getState().troop_defs.find(t=>Number(t.code)===Number(troopCode??getState().troop_queue[0]?.troop_code));
   if(unit){type=Number(unit.type);tier=Number(unit.tier);}
  }
  active=code;message='';readyAt=performance.now()+500;modal.classList.toggle('training-panel',code==='barrack');modal.querySelector('.function-level').textContent=getState().buildings[code]?'Lv. '+getState().buildings[code].level:'';
  if(!modal.open)modal.showModal();if(code==='barrack')renderTraining();else renderOverview();
 },close(){if(modal.open)end();}};
}

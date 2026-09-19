// Own marching armies, in the same ivory-and-wood interface as the world map.
window.ConquerMarchHud=function({host,getContext,follow,locate,stop,focus,getSelected,onLayout}){
  const root=document.createElement('aside');root.className='world-march-hud';root.setAttribute('aria-label','Deine Märsche');
  root.innerHTML='<button class="world-march-heading" aria-expanded="true"><span>Märsche</span><b></b><span aria-hidden="true">⌄</span></button><div class="world-march-list"></div>';
  const panel=document.createElement('section');panel.className='world-march-card';panel.hidden=true;panel.setAttribute('aria-label','Ausgewählter Marsch');
  panel.innerHTML='<header><img alt=""><div><strong></strong><span class="world-march-card-state"></span></div><button data-march-command="close" aria-label="Verfolgung beenden">×</button></header><div class="world-march-summary"></div><div class="world-march-commands"><button data-march-command="origin">⌂<span>Herkunft</span></button><button data-march-command="destination">⚑<span>Ziel</span></button><button data-march-command="details" aria-expanded="false">☷<span>Details</span></button><button data-march-command="recall">↶<span>Rückruf</span></button></div><div class="world-march-details" hidden></div><p class="world-march-feedback" role="status" hidden></p>';
  host.append(root,panel);const rows=new Map();let expanded=true,selected=null,lastState=null,detailOpen=false,pending=false,layoutDirty=true;
  const number=v=>Number(v)||0,clock=t=>{if(!t)return NaN;const s=String(t);return Date.parse(/[zZ]|[+-]\d\d:\d\d$/.test(s)?s:s.replace(' ','T')+'Z')},timeLeft=end=>{const ms=end-getContext().now();if(!Number.isFinite(ms))return 'Zeit offen';const s=Math.max(0,Math.ceil(ms/1000));return [Math.floor(s/3600),Math.floor(s/60)%60,s%60].map(n=>String(n).padStart(2,'0')).join(':')};
  const types={5:'Angriff',6:'Charm',7:'Angriff',8:'Späher',9:'Sammeltrupp',10:'Verstärkung',13:'Angriff',14:'Garnison',15:'Angriff'};
  const status=m=>m.state==='returning'?'Rückkehr':m.state==='arrived'&&number(m.march_type)===9?'Sammelt':m.state==='arrived'?'Stationiert':m.state==='gathering'?'Rally sammelt':m.state==='resolving'||getContext().now()>=clock(m.arrival_time)?'Am Ziel':number(m.march_type)===9?'Zur Mine':m.march_type==='rally'?'Rally':types[m.march_type]||'Marsch';
  const end=m=>clock(m.state==='returning'?m.return_time:m.state==='arrived'?m.gathering_finishes_at:m.arrival_time);
  function troops(m){let army=m.troops||m.troops_json||{};if(typeof army==='string'){try{army=JSON.parse(army)}catch{army={}}}return Object.entries(army).filter(([,n])=>number(n)>0)}
  const coordinates=m=>`X ${number(m.target_x)} · Y ${number(m.target_y)}`;
  const label=m=>`${status(m)} · ${coordinates(m)}`;
  const resourceNames={food:'Nahrung',lumber:'Holz',stone:'Stein',gold:'Gold',gems:'Edelsteine'};
  function targetResource(m){
    if(number(m.march_type)!==9)return null;
    if(resourceNames[m.target_resource])return m.target_resource;
    const node=lastState?.nodes?.find(node=>String(node.id)===String(m.target_id));
    return ({1:'food',2:'lumber',3:'stone',4:'gold',5:'gems'})[number(node?.object_type)]||null;
  }
  const image=m=>{const resource=targetResource(m);if(resource)return resource==='gems'?`${getContext().base}/assets/art/items/gems.svg`:`${getContext().base}/assets/art/ui-resources/${resource}.png`;return window.ConquerMarchSkins?.ids.includes(m.march_skin)?window.ConquerMarchSkins.image(getContext().base,m.march_skin):`${getContext().base}/assets/art/map/march-infantry.svg`};
  const imageLabel=m=>{const resource=targetResource(m);return resource?`${resourceNames[resource]} sammeln`:`${status(m)}: Truppensymbol`};
  const active=()=>lastState?.marches?.find(m=>String(m.id)===selected);
  const recallable=m=>m&&/^\d+$/.test(String(m.id))&&![13,14].includes(number(m.march_type))&&((m.state==='marching'&&end(m)>getContext().now())||(number(m.march_type)===9&&m.state==='arrived'));
  root.querySelector('.world-march-heading').onclick=()=>{expanded=!expanded;root.classList.toggle('is-collapsed',!expanded);root.querySelector('.world-march-heading').setAttribute('aria-expanded',String(expanded));layoutDirty=true;layout();};
  root.addEventListener('click',async e=>{
    const recall=e.target.closest('[data-march-recall]');
    if(recall){e.preventDefault();e.stopPropagation();const m=lastState?.marches?.find(m=>String(m.id)===recall.dataset.marchRecall);if(m)await recallMarch(m,recall);return;}
    const row=e.target.closest('[data-march-id]');if(row){const m=lastState?.marches?.find(m=>String(m.id)===row.dataset.marchId);if(m){if(locate)locate(m);else follow(row.dataset.marchId);}}
  });
  async function recallMarch(m,trigger){
    const ctx=getContext();if(pending||!recallable(m)||!ctx.onMarchRecall)return;
    pending=true;sync(lastState);try{await ctx.onMarchRecall(Number(m.id));}
    catch(error){const feedback=panel.querySelector('.world-march-feedback');feedback.textContent=error.message;feedback.hidden=false;}
    finally{pending=false;const current=active();if(current)renderCard(current);const updated=lastState?.marches?.find(item=>String(item.id)===String(m.id));if(updated)renderRow(updated);if(trigger?.isConnected)trigger.focus({preventScroll:true});}
  }
  panel.addEventListener('click',async e=>{
    const command=e.target.closest('[data-march-command]')?.dataset.marchCommand,m=active();if(!command||!m)return;
    if(command==='close'){stop(true);return;}
    if(command==='origin'||command==='destination'){const point=command==='origin'?[number(m.origin_x??lastState.city.coord_x)+.5,number(m.origin_y??lastState.city.coord_y)+.5]:[number(m.target_x)+.5,number(m.target_y)+.5];stop();focus(...point);return;}
    if(command==='details'){detailOpen=!detailOpen;renderCard(m);layoutDirty=true;layout();return;}
    if(command==='recall'&&!pending&&recallable(m)&&getContext().onMarchRecall){
      const restoreFocus=panel.contains(document.activeElement);await recallMarch(m);if(restoreFocus&&document.activeElement===document.body)panel.querySelector('[data-march-command="close"]').focus({preventScroll:true});
    }
  });
  for(const el of [root,panel]){el.addEventListener('pointerdown',e=>e.stopPropagation());el.addEventListener('keydown',e=>{if(e.key==='Escape'){e.preventDefault();e.stopPropagation();stop(true)}})}
  function renderCard(m){
    const ctx=getContext(),skin=window.ConquerMarchSkins?.ids.includes(m.march_skin)?window.ConquerMarchSkins.get(m.march_skin):null;
    const img=panel.querySelector('header img');if(img.getAttribute('src')!==image(m))img.src=image(m);
    panel.querySelector('header strong').textContent=skin?.name||'Dein Marsch';
    panel.querySelector('.world-march-card-state').textContent=`Kamera folgt · ${status(m)} · ${timeLeft(end(m))}`;
    panel.querySelector('.world-march-summary').textContent=`${troops(m).reduce((n,[,v])=>n+number(v),0).toLocaleString('de-DE')} Truppen · Ziel X ${number(m.target_x)} · Y ${number(m.target_y)}`;
    const recall=panel.querySelector('[data-march-command="recall"]');recall.hidden=!ctx.onMarchRecall||!recallable(m);if(recall.hidden&&document.activeElement===recall)panel.querySelector('[data-march-command="close"]').focus({preventScroll:true});recall.disabled=pending;recall.querySelector('span').textContent=pending?'Rückruf …':'Rückruf';
    panel.querySelector('[data-march-command="details"]').setAttribute('aria-expanded',String(detailOpen));
    const details=panel.querySelector('.world-march-details');details.hidden=!detailOpen;
    if(detailOpen){const esc=ctx.esc,defs=lastState.troop_defs||[];const html=`<dl><div><dt>Herkunft</dt><dd>X ${number(m.origin_x??lastState.city.coord_x)} · Y ${number(m.origin_y??lastState.city.coord_y)}</dd></div><div><dt>Ziel</dt><dd>X ${number(m.target_x)} · Y ${number(m.target_y)}</dd></div></dl>`+troops(m).map(([code,count])=>{const def=defs.find(t=>number(t.code)===number(code)),name=def?.name_de||def?.name||({1:'Infanterie',2:'Bogenschützen',3:'Kavallerie'}[def?.type])||'Truppen';return `<div class="world-march-unit"><span>${esc(name)}${def?.tier?' · Stufe '+number(def.tier):''}</span><b>${number(count).toLocaleString('de-DE')}</b></div>`}).join('');if(details.innerHTML!==html)details.innerHTML=html;}
  }
  function sync(state){
    lastState=state;const current=getSelected(),changed=current!==selected;selected=current;
    if(changed){detailOpen=false;panel.querySelector('.world-march-feedback').hidden=true;layoutDirty=true;}
    const marches=(state.marches||[]).filter(m=>!['complete','completed'].includes(m.state)),ids=new Set(marches.map(m=>String(m.id)));
    root.hidden=!marches.length;root.querySelector('.world-march-heading b').textContent=`${marches.length}/${number(state.army_limits?.march_slots||3)+number(state.army_limits?.gather_march_slots)}`;
    for(const m of marches){const id=String(m.id);let row=rows.get(id);if(!row){row=document.createElement('article');row.className='world-march-row';row.innerHTML='<button type="button" class="world-march-main"><span class="world-march-icon"><img></span><span class="world-march-copy"><strong></strong><small></small><time></time><i aria-hidden="true"><b></b></i></span></button><button type="button" class="world-march-recall" title="Trupp zurückrufen"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 7H5v-4M5.5 7.2A8 8 0 1 1 4 14"/></svg></button><span class="world-march-state-mark" aria-hidden="true">›</span>';row.querySelector('.world-march-main').dataset.marchId=id;row.querySelector('.world-march-recall').dataset.marchRecall=id;rows.set(id,row);root.querySelector('.world-march-list').append(row);layoutDirty=true;}renderRow(m);}
    for(const [id,row]of rows)if(!ids.has(id)){row.remove();rows.delete(id);layoutDirty=true;}
    const m=active();panel.hidden=!m;if(m)renderCard(m);if(layoutDirty)layout();
  }
  function renderRow(m){
    const id=String(m.id),row=rows.get(id);if(!row)return;
    const img=row.querySelector('img'),src=image(m);if(img.getAttribute('src')!==src)img.src=src;img.alt=imageLabel(m);
    row.querySelector('strong').textContent=status(m);row.querySelector('small').textContent=coordinates(m);row.querySelector('time').textContent=timeLeft(end(m));
    const main=row.querySelector('.world-march-main');main.setAttribute('aria-label',`${label(m)}, Restzeit ${timeLeft(end(m))}. Ziel auf der Karte fokussieren`);main.setAttribute('aria-pressed',String(selected===id));
    const recall=row.querySelector('.world-march-recall'),canRecall=!!getContext().onMarchRecall&&recallable(m);recall.hidden=!canRecall;recall.disabled=pending;recall.setAttribute('aria-label',number(m.march_type)===9&&m.state==='arrived'?'Sammeltrupp zurückrufen':'Trupp zurückrufen');
    row.querySelector('.world-march-state-mark').hidden=canRecall;
    const finish=end(m),begin=clock(m.state==='arrived'?m.arrival_time:m.departure_time),progress=Number.isFinite(finish)&&Number.isFinite(begin)?Math.max(0,Math.min(1,(getContext().now()-begin)/Math.max(1,finish-begin))):0;row.querySelector('i b').style.width=`${progress*100}%`;
  }
  function layout(){
    onLayout?.();
    layoutDirty=false;const viewport=host.getBoundingClientRect(),short=viewport.height<=520&&viewport.width>viewport.height;
    const army=document.querySelector('#hud-left-tools [data-world-only]')?.getBoundingClientRect()||document.querySelector('#hud-left-tools')?.getBoundingClientRect();
    const profile=document.querySelector('.topbar')?.getBoundingClientRect(),chat=document.querySelector('.world-chat')?.getBoundingClientRect();
    const top=Math.max(8,(profile?.bottom||viewport.top)-viewport.top+8),bottom=chat&&chat.height>36?Math.max(top+180,chat.top-viewport.top-10):viewport.height-105;
    root.style.left=short&&army?`${Math.max(army.right-viewport.left+8,76)}px`:'8px';root.style.top=`${Math.max(top,army?army.top-viewport.top:16)}px`;
    const rightEdge=viewport.width<700?Math.min(viewport.right-8,...[...document.querySelectorAll('.hud-right-tools button,#navigation button')].filter(b=>b.getClientRects().length).map(b=>b.getBoundingClientRect()).filter(r=>r.left>viewport.left+viewport.width*.65&&r.width<110).map(r=>r.left-8))-viewport.left:viewport.width-8;
    panel.style.width=`${Math.min(short?290:320,rightEdge-8)}px`;
    panel.style.left=short?`${Math.max(8,viewport.width-parseFloat(panel.style.width)-92)}px`:`${Math.max(8,(rightEdge+8-parseFloat(panel.style.width))/2)}px`;
    panel.style.top=short?`${Math.max(top,90)}px`:'auto';panel.style.bottom=short?'auto':`${Math.max(100,viewport.height-bottom)}px`;panel.style.maxHeight=`${Math.max(120,(short?viewport.height-95:bottom)-top)}px`;
    if(short){
      const tools=document.querySelector('.hud-right-tools')?.getBoundingClientRect(),dock=document.querySelector('#navigation')?.getBoundingClientRect();
      panel.style.left=`${Math.max(8,viewport.width-parseFloat(panel.style.width)-24)}px`;
      panel.style.top=`${Math.max(top,tools?tools.bottom-viewport.top+8:90)}px`;
      panel.style.maxHeight=`${Math.max(146,(dock?.top||viewport.bottom)-viewport.top-parseFloat(panel.style.top)-8)}px`;
    }
    if(!short&&viewport.width<700&&viewport.height<700)panel.style.maxHeight=`${Math.max(146,bottom-parseFloat(root.style.top)-92)}px`;
  }
  const observer=new ResizeObserver(()=>{layoutDirty=true;layout()});observer.observe(host);for(const el of document.querySelectorAll('.world-chat,.topbar,.hud-right-tools'))observer.observe(el);
  return {sync,layout,anchor(){layout();const box=host.getBoundingClientRect(),short=box.height<=520&&box.width>box.height,r=root.getBoundingClientRect(),p=panel.getBoundingClientRect();return {x:short?Math.max(100,p.left-box.left-60):Math.min(box.width-85,Math.max(box.width*.5,r.right-box.left+58)),y:short?Math.max(125,box.height*.55):Math.max(170,Math.min(box.height*.46,p.top-box.top-30))}},destroy(){observer.disconnect();root.remove();panel.remove()}};
};

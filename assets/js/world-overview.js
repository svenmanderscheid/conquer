/* Read-only atlas: selecting a canton never moves the live map until confirmed. */
window.ConquerWorldOverview = options => {
  'use strict';
  const {panel, getContext, getLux, getCenter, jump, retry, openTerritories, remember={}, esc}=options;
  const root=document.createElement('div');root.className='world-overview';root.hidden=true;
  const tr=(key,fallback,params={})=>window.ConquerLocale?.has(`overview.${key}`)?window.ConquerLocale.t(`overview.${key}`,params):fallback.replace(/\{(\w+)\}/g,(_,name)=>params[name]??'');
  const text=(key,fallback)=>`data-i18n="overview.${key}">${fallback}`;
  root.innerHTML=`<div class="wo-toolbar"><div class="wo-layers" role="group" aria-label="Kartenebenen"><button type="button" data-overview="cantons" aria-pressed="true" ${text('cantons','Kantone')}</button><button type="button" data-overview="ownership" aria-pressed="false" ${text('ownership','Besitz')}</button></div><button type="button" data-atlas="home" class="wo-home" ${text('home','⌂ Meine Stadt')}</button></div>
    <div class="wo-body"><section class="wo-map-column"><div class="wo-map" tabindex="0" role="group" aria-label="Kantonskarte. Kanton antippen, zum Verschieben ziehen, Plus und Minus zum Zoomen."><canvas aria-hidden="true"></canvas><div class="wo-labels"></div><span class="wo-north" aria-hidden="true">N ↑</span><div class="wo-map-tools"><button type="button" data-overview="zoom-in" aria-label="Übersicht vergrößern">+</button><button type="button" data-overview="zoom-out" aria-label="Übersicht verkleinern">−</button><button type="button" data-overview="fit" ${text('fit','Gesamte Karte')}</button></div><p class="wo-loading" role="status" ${text('loading','Karte wird geladen …')}</p></div><div class="wo-map-caption"><label><input type="checkbox" class="wo-communes"><span ${text('boundaries','Gemeindegrenzen')}</span></label><span class="wo-legend"><i class="wo-home-dot"></i><span ${text('city','Deine Stadt')}</span><i class="wo-view-dot"></i><span ${text('viewport','Kartenausschnitt')}</span></span><span class="wo-owner-legend" hidden><i class="wo-own-dot"></i><span ${text('own','Eigene Allianz')}</span><i class="wo-other-dot"></i><span ${text('other','Andere Allianz')}</span></span></div></section>
    <aside class="wo-sidebar"><label class="wo-search"><span ${text('find','Kanton finden')}</span><input type="search" placeholder="Name eingeben …" autocomplete="off" aria-label="Kanton finden"></label><div class="wo-canton-list" role="group" aria-label="Kantone"></div><p class="wo-empty" hidden role="status" ${text('empty','Kein Kanton gefunden.')}</p><div class="wo-selection"><div class="wo-selection-copy" aria-live="polite"><strong class="wo-selected-name" translate="no"></strong><span class="wo-selected-info"></span></div><button type="button" data-overview="go" disabled ${text('go','Auf der Weltkarte zeigen →')}</button></div></aside></div>
    <div class="wo-footer"><details class="wo-coordinates"><summary ${text('coordinates','Zu Koordinaten springen')}</summary></details><button type="button" data-overview="territories" ${text('territories','Allianzgebiete')}</button></div>`;
  panel.append(root);
  const q=selector=>root.querySelector(selector),map=q('.wo-map'),canvas=q('canvas'),ctx=canvas.getContext('2d'),labels=q('.wo-labels'),list=q('.wo-canton-list');
  let lux=null,worldKey=null,selected=null,layer='cantons',zoom=1,camera=null,projection=null,width=0,height=0,stamp='',frame=0,destroyed=false;
  let buttons=new Map(),labelButtons=new Map(),pointers=new Map(),gesture=null,moved=false;
  const jumpForm=panel.querySelector('.atlas-jump'),originalFormNext=jumpForm.nextSibling;
  const palette=()=>{const style=getComputedStyle(panel);return Object.fromEntries(['paper','card','ink','muted','line','frame','primary','window-head','green-soft','inset','gold-soft','orange','blue','red'].map(key=>[key,style.getPropertyValue(`--ui-${key}`).trim()]));};
  const schedule=()=>{if(!frame&&!destroyed)frame=requestAnimationFrame(()=>{frame=0;draw();});};
  const number=id=>String(Number(id)).padStart(2,'0');
  const profile=()=>getContext().state.world.map_profile;
  const current=()=>lux?.cantons.find(c=>c.id===selected);
  function reset(){zoom=1;camera=null;schedule();}
  function select(id,fromMap=false){
    if(!lux?.cantons.some(c=>c.id===id))return;selected=id;remember.key=worldKey;remember.selected=id;updateSelection();schedule();
    q('.wo-sidebar').classList.remove('is-searching');
    if(fromMap){q('input[type=search]').value='';filter();buttons.get(id)?.scrollIntoView({block:'nearest',inline:'nearest'});}
  }
  function filter(){const term=q('input[type=search]').value.trim().toLocaleLowerCase();let count=0;for(const c of lux?.cantons||[]){const button=buttons.get(c.id);button.hidden=!c.name.toLocaleLowerCase().includes(term);if(!button.hidden)count++;}q('.wo-empty').hidden=count>0;}
  function updateSelection(){
    const canton=current();for(const [id,button]of [...buttons,...labelButtons])button.setAttribute('aria-pressed',String(id===selected));
    q('[data-overview=go]').disabled=!canton;q('.wo-selected-name').textContent=canton?.name||'';
    q('.wo-selected-info').textContent=canton?tr('commune_count','{count} Gemeinden · X {x} / Y {y}',{count:lux.communes.filter(c=>c.canton===canton.id).length,x:canton.point[0],y:canton.point[1]}):'';
  }
  function rebuild(){
    labels.replaceChildren();list.replaceChildren();buttons=new Map();labelButtons=new Map();
    for(const canton of [...lux.cantons].sort((a,b)=>a.name.localeCompare(b.name))){
      const button=document.createElement('button');button.type='button';button.dataset.canton=canton.id;button.className='wo-canton';button.innerHTML=`<b translate="no">${number(canton.id)}</b><span translate="no">${esc(canton.name)}</span><small class="wo-canton-home" hidden>${tr('city','Deine Stadt')}</small>`;list.append(button);buttons.set(canton.id,button);
      const label=document.createElement('button');label.type='button';label.className='wo-map-label';label.dataset.canton=canton.id;label.setAttribute('aria-label',canton.name);label.setAttribute('translate','no');label.innerHTML=`<b>${number(canton.id)}</b><span>${esc(canton.name)}</span>`;labels.append(label);labelButtons.set(canton.id,label);
    }
    const center=getCenter();selected=remember.key===worldKey&&lux.cantons.some(c=>c.id===remember.selected)?remember.selected:lux.at(center.x,center.y)?.canton||lux.cantons[0]?.id;updateSelection();filter();
  }
  function update(){
    const active=getContext().state.world?.map_profile?.key==='luxembourg';root.hidden=!active;panel.classList.toggle('has-canton-overview',active);
    panel.setAttribute('aria-modal',String(active));
    if(!active){if(jumpForm.parentElement!==panel)panel.insertBefore(jumpForm,originalFormNext);return;}
    if(jumpForm.parentElement!==q('.wo-coordinates'))q('.wo-coordinates').append(jumpForm);
    const key=JSON.stringify([getContext().state.city.world_id,profile()]);if(key!==worldKey){worldKey=key;lux=null;selected=null;stamp='';reset();q('input[type=search]').value='';labels.replaceChildren();list.replaceChildren();buttons.clear();labelButtons.clear();ctx.clearRect(0,0,canvas.width,canvas.height);updateSelection();}
    const next=getLux();if(next&&next!==lux){lux=next;rebuild();}
    q('.wo-loading').hidden=!!lux;
    if(!lux)return;
    const city=getContext().state.city,home=lux.at(city.coord_x,city.coord_y)?.canton;
    for(const [id,button] of buttons)button.querySelector('.wo-canton-home').hidden=id!==home;
    updateSelection();
    // Polls update ownership/position without replacing focused controls or list scroll.
    const nextStamp=JSON.stringify([getCenter(),city.coord_x,city.coord_y,getContext().state.territory?.ownership,getContext().state.territory?.alliance_id]);
    if(nextStamp!==stamp){stamp=nextStamp;schedule();}
  }
  function draw(){
    if(root.hidden||panel.hidden||!lux)return;
    const bounds=map.getBoundingClientRect();width=bounds.width;height=bounds.height;if(width<1||height<1)return;
    const ratio=Math.min(devicePixelRatio||1,2),w=Math.round(width*ratio),h=Math.round(height*ratio);if(canvas.width!==w||canvas.height!==h){canvas.width=w;canvas.height=h;}
    ctx.setTransform(ratio,0,0,ratio,0,0);ctx.clearRect(0,0,width,height);
    const p=palette(),pw=profile().width,ph=profile().height,fit=Math.min((width-36)/pw,(height-80)/ph),s=fit*zoom;
    camera=camera||{x:pw/2,y:ph/2};camera.x=Math.max(0,Math.min(pw,camera.x));camera.y=Math.max(0,Math.min(ph,camera.y));
    const ox=width/2-camera.x*s,oy=(height-60)/2-camera.y*s;projection={s,ox,oy};
    const project=(x,y)=>[ox+x*s,oy+y*s];
    ctx.save();ctx.translate(ox,oy);ctx.scale(s,s);ctx.lineJoin='round';
    const colors=[p['green-soft'],p.paper,p['gold-soft'],p.inset];
    for(const canton of lux.cantons){ctx.fillStyle=colors[(Number(canton.id)-1)%colors.length];ctx.fill(canton.shape,'evenodd');}
    if(layer==='ownership'){
      const owners=new Map((getContext().state.territory?.ownership||[]).map(o=>[o.id,o])),alliance=Number(getContext().state.territory?.alliance_id||0);
      for(const commune of lux.communes){const owner=Number(owners.get(`commune:${commune.id}`)?.owner_alliance_id||0);if(!owner)continue;ctx.globalAlpha=.38;ctx.fillStyle=alliance&&owner===alliance?p.blue:p.red;ctx.fill(commune.shape,'evenodd');}ctx.globalAlpha=1;
    }
    if(q('.wo-communes').checked||layer==='ownership')for(const commune of lux.communes){ctx.strokeStyle=p.line;ctx.lineWidth=.65/s;ctx.stroke(commune.shape);}
    for(const canton of lux.cantons){ctx.strokeStyle=p.frame;ctx.lineWidth=1.3/s;ctx.stroke(canton.shape);}
    const chosen=current();if(chosen){ctx.fillStyle=p['window-head'];ctx.globalAlpha=layer==='ownership'?.12:.65;ctx.fill(chosen.shape,'evenodd');ctx.globalAlpha=1;ctx.strokeStyle=p.primary;ctx.lineWidth=3/s;ctx.stroke(chosen.shape);}
    ctx.restore();
    const center=getCenter(),[vx,vy]=project(center.left,center.top);ctx.strokeStyle=p.primary;ctx.lineWidth=1.5;ctx.setLineDash([4,3]);ctx.strokeRect(vx,vy,(center.right-center.left)*s,(center.bottom-center.top)*s);ctx.setLineDash([]);
    const city=getContext().state.city,[hx,hy]=project(city.coord_x,city.coord_y);ctx.beginPath();ctx.arc(hx,hy,6,0,Math.PI*2);ctx.fillStyle=p.orange;ctx.fill();ctx.strokeStyle=p.ink;ctx.lineWidth=1.5;ctx.stroke();
    const compact=height<310;map.classList.toggle('is-compact',compact);
    const placed=[{left:hx-8,right:hx+8,top:hy-8,bottom:hy+8}];
    for(const canton of [...lux.cantons].sort((a,b)=>a.point[1]-b.point[1])){
      const label=labelButtons.get(canton.id),[x,y]=project(...canton.point);label.hidden=x<0||y<0||x>width||y>height-42;if(label.hidden)continue;
      const lw=label.offsetWidth,lh=label.offsetHeight,candidates=[];
      // Fixed-size labels find the nearest free space; callout lines retain their location.
      for(let dy=-8;dy<=8;dy++)for(const dx of [0,-1,1,-2,2]){const left=Math.max(3,Math.min(width-lw-3,x-lw/2+dx*(lw/2+4))),top=Math.max(3,Math.min(height-lh-60,y-lh/2+dy*(lh+3)));candidates.push({left,top,cost:Math.hypot(left+lw/2-x,top+lh/2-y)});}
      candidates.sort((a,b)=>a.cost-b.cost);
      const free=({left,top})=>!placed.some(r=>left<r.right+3&&left+lw>r.left-3&&top<r.bottom+3&&top+lh>r.top-3);
      let spot=candidates.find(free);
      if(!spot||spot.cost>lh*1.5){
        for(let top=3;top<=height-lh-60;top+=3)for(let left=3;left<=width-lw-3;left+=3){const cost=Math.hypot(left+lw/2-x,top+lh/2-y);if((!spot||cost<spot.cost)&&free({left,top}))spot={left,top,cost};}
      }
      spot=spot||candidates[0];
      const {left,top}=spot;placed.push({left,right:left+lw,top,bottom:top+lh});label.style.left=`${left}px`;label.style.top=`${top}px`;
      if(x<left||x>left+lw||y<top||y>top+lh){ctx.strokeStyle=p.frame;ctx.lineWidth=1;ctx.beginPath();ctx.moveTo(x,y);ctx.lineTo(Math.max(left,Math.min(left+lw,x)),Math.max(top,Math.min(top+lh,y)));ctx.stroke();}
    }
    q('[data-overview=zoom-out]').disabled=zoom<=1;q('[data-overview=zoom-in]').disabled=zoom>=4;
  }
  function zoomTo(value,point={x:width/2,y:(height-60)/2}){
    if(!projection)return;const next=Math.max(1,Math.min(4,value)),factor=next/zoom;
    camera.x+=(point.x-width/2)/projection.s*(1-1/factor);camera.y+=(point.y-(height-60)/2)/projection.s*(1-1/factor);zoom=next;if(zoom===1)camera=null;schedule();
  }
  root.addEventListener('click',event=>{
    const button=event.target.closest('button');if(!button)return;
    if(button.dataset.canton){if(moved&&event.detail>0&&button.closest('.wo-map'))return;select(button.dataset.canton,!!button.closest('.wo-map'));return;}
    const action=button.dataset.overview;
    if(action==='territories'){openTerritories();return;}
    if(action==='retry'){q('.wo-loading').textContent=tr('loading','Karte wird geladen …');retry();return;}
    if(action==='go'&&current()){jump(...current().point);return;}
    if(action==='fit')reset();
    if(action==='zoom-in'||action==='zoom-out')zoomTo(zoom*(action==='zoom-in'?1.5:1/1.5));
    if(action==='cantons'||action==='ownership'){layer=action;root.querySelectorAll('.wo-layers button').forEach(b=>b.setAttribute('aria-pressed',String(b.dataset.overview===layer)));q('.wo-legend').hidden=layer==='ownership';q('.wo-owner-legend').hidden=layer!=='ownership';schedule();}
  });
  q('input[type=search]').addEventListener('input',()=>{q('.wo-sidebar').classList.add('is-searching');filter();});
  q('input[type=search]').addEventListener('focus',()=>q('.wo-sidebar').classList.add('is-searching'));
  q('.wo-sidebar').addEventListener('focusout',event=>{if(!event.relatedTarget?.closest('.wo-search,.wo-canton-list'))q('.wo-sidebar').classList.remove('is-searching');});
  q('input[type=search]').addEventListener('keydown',event=>{if(event.key==='Escape'&&q('.wo-sidebar').classList.contains('is-searching')){event.preventDefault();event.stopPropagation();q('.wo-sidebar').classList.remove('is-searching');q('[data-overview=go]').focus({preventScroll:true});}});
  q('.wo-communes').addEventListener('change',schedule);
  map.addEventListener('pointerdown',event=>{
    if(event.button!==0||event.target.closest('.wo-map-tools')||!projection)return;
    const p={x:event.clientX,y:event.clientY};pointers.set(event.pointerId,p);map.setPointerCapture(event.pointerId);
    if(pointers.size===1){moved=false;gesture={point:p,camera:{...camera},target:event.target.closest('[data-canton]')?.dataset.canton};}
    if(pointers.size===2){const [a,b]=[...pointers.values()];gesture={distance:Math.hypot(a.x-b.x,a.y-b.y),zoom};moved=true;}
  });
  map.addEventListener('pointermove',event=>{
    if(!pointers.has(event.pointerId)||!projection||!gesture)return;pointers.set(event.pointerId,{x:event.clientX,y:event.clientY});
    if(pointers.size===2&&gesture.distance){const [a,b]=[...pointers.values()],box=map.getBoundingClientRect();zoomTo(gesture.zoom*Math.hypot(a.x-b.x,a.y-b.y)/Math.max(1,gesture.distance),{x:(a.x+b.x)/2-box.left,y:(a.y+b.y)/2-box.top});return;}
    if(pointers.size!==1||!gesture.point)return;
    const dx=event.clientX-gesture.point.x,dy=event.clientY-gesture.point.y;if(Math.hypot(dx,dy)>6)moved=true;if(!moved)return;
    camera={x:gesture.camera.x-dx/projection.s,y:gesture.camera.y-dy/projection.s};schedule();
  });
  function endPointer(event){
    if(!pointers.has(event.pointerId))return;
    if(event.type==='pointerup'&&!moved&&pointers.size===1&&projection){const box=map.getBoundingClientRect(),canton=gesture?.target||lux.at((event.clientX-box.left-projection.ox)/projection.s,(event.clientY-box.top-projection.oy)/projection.s)?.canton;if(canton)select(canton,true);}
    pointers.delete(event.pointerId);if(map.hasPointerCapture(event.pointerId))map.releasePointerCapture(event.pointerId);gesture=null;
  }
  for(const name of ['pointerup','pointercancel','lostpointercapture'])map.addEventListener(name,endPointer);
  map.addEventListener('wheel',event=>{event.preventDefault();const box=map.getBoundingClientRect();zoomTo(zoom*(event.deltaY<0?1.15:1/1.15),{x:event.clientX-box.left,y:event.clientY-box.top});},{passive:false});
  map.addEventListener('keydown',event=>{if(event.target!==map)return;const delta={ArrowLeft:[-35,0],ArrowRight:[35,0],ArrowUp:[0,-35],ArrowDown:[0,35]}[event.key];if(delta&&projection){event.preventDefault();camera.x+=delta[0]/projection.s;camera.y+=delta[1]/projection.s;schedule();}else if(['+','=','-','Home'].includes(event.key)){event.preventDefault();event.key==='Home'?reset():zoomTo(zoom*(event.key==='-'?1/1.5:1.5));}});
  const observer=new ResizeObserver(schedule);observer.observe(map);
  document.fonts?.ready.then(schedule);
  const visibility=new MutationObserver(()=>{if(panel.hidden){pointers.clear();gesture=null;q('.wo-sidebar').classList.remove('is-searching');q('.wo-coordinates').open=false;}else{update();schedule();}});visibility.observe(panel,{attributes:true,attributeFilter:['hidden']});
  function trapFocus(event){if(event.key!=='Tab'||panel.hidden||root.hidden)return;const focusable=[...panel.querySelectorAll('button,input,summary,[tabindex="0"]')].filter(e=>!e.disabled&&e.getClientRects().length);const first=focusable[0],last=focusable.at(-1);if(event.shiftKey&&document.activeElement===first){event.preventDefault();last?.focus();}else if(!event.shiftKey&&document.activeElement===last){event.preventDefault();first?.focus();}}
  panel.addEventListener('keydown',trapFocus);
  return {update,draw:schedule,setError(){q('.wo-loading').removeAttribute('data-i18n');q('.wo-loading').innerHTML=`${esc(tr('error','Die Karte konnte nicht geladen werden.'))} <button type="button" data-overview="retry">${esc(tr('retry','Erneut laden'))}</button>`;},destroy(){destroyed=true;observer.disconnect();visibility.disconnect();cancelAnimationFrame(frame);panel.removeEventListener('keydown',trapFocus);}};
};

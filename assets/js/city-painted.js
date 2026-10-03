/* Approved village terrain, independent sprites and authoritative construction states. */
window.ConquerPaintedCity=(()=>{
 document.addEventListener('visibilitychange',()=>document.querySelectorAll('.painted-village').forEach(el=>el.classList.toggle('is-paused',document.hidden)));
 const places=[['castle',44,4,17,24],['academy',25,12,12,19],['treasure_house',64.5,14,14,18],['hospital',11.5,28,14,16],['hall_of_alliance',28.5,30,15,18],['trading_post',55.625,30.75,13.75,16.25],['storage',69.5,29,15,20],['watch_tower',89,34,6,16],['stable',6,49,16,14],['archery_range',13,63,14,12],['barrack',30,58,14,16],['farm',56,55,14,15],['lumber_camp',78,54,15,15],['gold_mine',54,72,11,11],['quarry',67,70,17,14],['wall',34,84,10,12]];
 const spriteName=code=>['castle','academy','treasure_house'].includes(code)?code+'_rounded':['hospital','hall_of_alliance','stable','archery_range','barrack'].includes(code)?code+'_aligned':code;
 const defaultCastleSprite=base=>`${base}/assets/art/village-layered-v2/runtime/castle_rounded.webp`;
 // The extended painting surrounds the existing 3:2 building coordinate area.
 const terrainFrame={x:106,y:99,width:1229,height:819};
 function constructionArtwork(base,code){
  const id='painted-construction-'+code,src=`${base}/assets/art/village-layered-v2/runtime/construction-scaffold-frame-1.webp`.replaceAll('&','&amp;').replaceAll('"','&quot;');
  // Follow the tool silhouette through the transparent gap beside the cap.
  // The hand is painted over its handle; no part of the face or scaffold moves.
  const tool='M184 218 199 190 213 190 243 207 242 226 229 244 217 244 213 241 210 243 191 241 194 232 184 226Z';
  const picture=`<image href="${src}" width="768" height="768"/>`;
  return `<svg class="painted-construction-art" viewBox="0 0 768 768" preserveAspectRatio="xMidYMax meet" aria-hidden="true" focusable="false"><defs><mask id="${id}-base" maskUnits="userSpaceOnUse" x="0" y="0" width="768" height="768"><rect width="768" height="768" fill="white"/><path d="${tool}" fill="black"/></mask><clipPath id="${id}-tool"><path d="${tool}"/></clipPath></defs><g class="painted-construction-hammer" style="transform-origin:199px 249px"><g clip-path="url(#${id}-tool)">${picture}</g></g><g mask="url(#${id}-base)">${picture}</g><g class="painted-construction-impact" transform="translate(232 290)"><g class="painted-construction-dust"><ellipse cx="-5" cy="0" rx="10" ry="6"/><ellipse cx="7" cy="-3" rx="8" ry="7"/></g><path class="painted-construction-chip painted-construction-chip--left" d="m-4-2 7-2-2 6Z"/><path class="painted-construction-chip painted-construction-chip--right" d="m1-1 6 1-3 5Z"/></g></svg>`;
 }
 const villageSkinAsset=url=>url+(url.includes('?')?'&':'?')+'village=3';
 const trainingBuildingFor=(state,job)=>{
  const definition=(state.troop_defs||[]).find(t=>Number(t.code)===Number(job.troop_code));
  return definition?.training_building||({1:'barrack',2:'archery_range',3:'stable'}[Number(job.barrack_slot)]??'barrack');
 };
 const completionKinds={train_complete:'training',research_complete:'research',heal_complete:'healing',build_complete:'building'};
 const readyIcons={training:'menu-icons/army.png',research:'menu-icons/research.png',healing:'items/healing.svg',building:'items/builders-hammer.png',chest:'items/daily-chest-gold-v1.png'};
 // Only confirmed server events can produce a completion marker. An expired
 // countdown still belongs to the running queue until the server settles it.
 function readiness({state,kingdom}){
  const notices=[];
  for(const [code] of places){
   if(Number(state?.buildings?.[code]?.level||0)<1)continue;
   const events=(state.building_completions||[]).filter(entry=>Number(entry.id)>0&&completionKinds[entry.type]&&entry.data?.building_code===code
    &&Number(entry.data.city_id)===Number(state.city?.id)&&Number(entry.data.world_id)===Number(state.city?.world_id));
   const chests=code==='treasure_house'?kingdom?.chests:null;
   const silver=chests?.free_silver_available===true&&Number(chests.free_silver_remaining)>0,gold=chests?.free_gold_available===true;
   if(silver||gold){notices.push({code,kind:'chest',ids:[],count:Number(silver)+Number(gold),icon:gold?readyIcons.chest:'items/daily-chest-blue-v1.png'});continue;}
   if(!events.length)continue;
   const event=events.find(entry=>entry.type!=='build_complete')||events[0],kind=completionKinds[event.type];
   const count=events.filter(entry=>entry.type===event.type).reduce((total,entry)=>total+Math.max(0,Number(entry.data.count)||0),0);
   notices.push({code,kind,ids:events.map(entry=>Number(entry.id)),count,icon:readyIcons[kind],data:event.data});
  }
  return notices;
 }
 function updateReadiness({host,base,state,kingdom,labels}){
  const scene=host.querySelector('.painted-village-scene');if(!scene)return;
  syncHeadroom(scene.closest('.painted-village'));
  let layer=scene.querySelector('.painted-building-notices');
  if(!layer){layer=document.createElement('div');layer.className='painted-building-notices';scene.append(layer);}
  const notices=readiness({state,kingdom}),active=new Set(notices.map(notice=>notice.code));
  for(const button of [...layer.children])if(!active.has(button.dataset.id))button.remove();
  for(const notice of notices){
   let button=layer.querySelector(`[data-id="${notice.code}"]`);
   if(!button){
    button=document.createElement('button');button.type='button';button.className='painted-building-ready';button.dataset.action='city-ready';button.dataset.id=notice.code;
    const [,x,y,w,h]=places.find(place=>place[0]===notice.code);
    button.style.left=(x+w/2)+'%';button.style.top=(y+h*.25)+'%';
    const art=document.createElement('img');art.alt='';art.draggable=false;
    const check=document.createElement('span');check.className='painted-ready-check';check.setAttribute('aria-hidden','true');check.textContent='✓';
    button.append(art,check);layer.append(button);
   }
   button.dataset.kind=notice.kind;
   const label=window.ConquerLocale?.t('city.ready.'+notice.kind,{building:labels[notice.code]||notice.code,count:window.ConquerLocale?.formatNumber(notice.count)??notice.count})||notice.kind;
   if(button.getAttribute('aria-label')!==label){button.setAttribute('aria-label',label);button.title=label;}
   const image=button.querySelector('img'),src=`${base}/assets/art/${notice.icon}`;
   if(image.getAttribute('src')!==src)image.src=src;
  }
 }
 function syncHeadroom(village){
  const scene=village.querySelector('.painted-village-scene'),scroll=village.querySelector('.painted-village-scroll');
  const resources=document.getElementById('resources');
  if(!scene||!scroll)return;
  // End scrolling at the painting's real lower edge; never expose a fallback
  // background or reserve more HUD space than the approved artwork contains.
  const ground=scene.parentElement;
  ground.style.setProperty('--painted-footroom',Math.floor(scene.offsetWidth*(1086-terrainFrame.y-terrainFrame.height)/terrainFrame.width)+'px');
  if(!resources)return;
  // Allow even the highest roof marker to move below the fixed resource bar.
  // This is real scrollable space, so markers always stay anchored to their roofs.
  const room=Math.max(0,Math.ceil(resources.getBoundingClientRect().bottom-village.getBoundingClientRect().top+12-scene.offsetHeight*.1+27));
  scroll.style.setProperty('--painted-headroom',room+'px');
 }
 window.addEventListener('resize',()=>requestAnimationFrame(()=>document.querySelectorAll('.painted-village').forEach(syncHeadroom)));
 function syncCastleSkin(button,base,skinId){
  const image=button?.querySelector('.painted-building-sprite');if(!image)return;
  const catalog=window.ConquerCastleSkins,requested=typeof skinId==='string'&&/^[a-z0-9-]{1,32}$/.test(skinId)?skinId:'default',id=requested||'default';
  const special=id!=='default',catalogKnowsSkin=Array.isArray(catalog?.ids)&&catalog.ids.includes(id);
  const still=special?villageSkinAsset(catalogKnowsSkin?catalog.image(base,id):`${base}/assets/art/map/castle-${id}.png`):defaultCastleSprite(base);
  const motion=special?villageSkinAsset(catalogKnowsSkin?catalog.motionImage(base,id):`${base}/assets/art/map/castle-${id}.webp`):still;
  const previous=image.dataset.villageCastleSkin||'default';
  image.dataset.villageCastleSkin=id;button.dataset.castleSkin=id;button.classList.toggle('has-castle-skin',special);
  if(!special&&previous==='default')return;
  if(!special){
   delete image.dataset.castleStill;delete image.dataset.castleMotion;image.removeAttribute('data-castle-motion');
   button.querySelector('.painted-motion')?.remove();button.classList.remove('has-painted-motion');
   image.onerror=null;image.onload=()=>{image.style.opacity='';};image.setAttribute('src',defaultCastleSprite(base));
   const mount=()=>mountBuildingMotion(image,'castle');if(image.complete&&image.naturalWidth)mount();else image.addEventListener('load',mount,{once:true});return;
  }
  if(previous===id&&[still,motion].includes(image.getAttribute('src')))return;
  button.querySelector('.painted-motion')?.remove();button.classList.remove('has-painted-motion');
  delete image.dataset.castleMotion;image.removeAttribute('data-castle-motion');image.dataset.castleStill=still;
  image.style.opacity='';image.onload=()=>{image.style.opacity='';};image.onerror=()=>{image.onerror=null;image.setAttribute('src',defaultCastleSprite(base));};image.setAttribute('src',still);
  if(document.body.classList.contains('reduced-motion')||motion===still)return;
  const animated=new Image();
  animated.onload=()=>{
   if(image.dataset.villageCastleSkin!==id)return;
   image.dataset.castleMotion=motion;image.setAttribute('data-castle-motion','');image.onerror=()=>{image.onerror=null;image.removeAttribute('data-castle-motion');image.setAttribute('src',still);};image.setAttribute('src',motion);
  };
  animated.src=motion;
 }
 // Coordinates are relative to the trimmed artwork, not the building hit target.
 const motionParts={
  castle:['flag',47,0,13,14],academy:['flag',43,0,26,14],
  hall_of_alliance:['cloth',40,38,15,25],trading_post:['cloth',12,42,72,18],
  watch_tower:['flag',45,0,37,16],stable:['breathe',15,51,24,29],
  archery_range:['sway',7,54,23,28],barrack:['flag',24,0,15,16],
  farm:['sway',61,44,30,43],lumber_camp:['wheel',68,54,13,26],
  gold_mine:['cart',38,58,17,23],quarry:['pendulum',61,28,13,33]
 };
 const motionAccents={
  castle:['warm',43,64],academy:['magic',73,49],treasure_house:['gold',68,80],
  hospital:['smoke',41,7],hall_of_alliance:['warm',55,69],trading_post:['warm',40,68],
  storage:['smoke',30,9],watch_tower:['warm',51,46],stable:['dust',26,83],
  archery_range:['dust',26,82],barrack:['dust',24,83],farm:['smoke',40,8],
  lumber_camp:['water',83,89],gold_mine:['warm',18,64],quarry:['dust',39,83]
 };
 function mountBuildingMotion(img,code){
  if(code==='castle'&&img.dataset.villageCastleSkin&&img.dataset.villageCastleSkin!=='default')return;
  if(!img.naturalWidth||img.parentElement.querySelector('.painted-motion'))return;
  const part=motionParts[code],accent=motionAccents[code],id='village-motion-'+code;
  const src=img.getAttribute('src').replaceAll('&','&amp;').replaceAll('"','&quot;');
  const picture=`<image href="${src}" width="100" height="100" preserveAspectRatio="none"/>`;
  let moving='',defs='',base=picture;
  // Rotating the circular mill wheel remains visually coherent. Moving a
  // rectangular crop cut from a finished painting exposes seams and deforms
  // roofs, flags, animals or ground, so those parts stay in the base artwork.
  if(part?.[0]==='wheel'){
   const [kind,x,y,w,h]=part;
   const shape=kind==='wheel'?`<ellipse cx="${x+w/2}" cy="${y+h/2}" rx="${w/2}" ry="${h/2}"/>`:`<rect x="${x}" y="${y}" width="${w}" height="${h}"/>`;
   defs=`<defs><mask id="${id}-base" maskUnits="userSpaceOnUse" x="0" y="0" width="100" height="100"><rect width="100" height="100" fill="white"/><g fill="black">${shape}</g></mask><clipPath id="${id}-part">${shape}</clipPath></defs>`;
   base=`<g mask="url(#${id}-base)">${picture}</g>`;
   // Small, anchored motions retain the painted silhouette and do not move hit targets.
   moving=`<g class="painted-moving painted-moving--${kind}" style="transform-origin:${x+w/2}px ${kind==='pendulum'?y:y+h}px"><g clip-path="url(#${id}-part)">${picture}</g></g>`;
   if(kind==='wheel')moving=`<g transform="translate(${x+w/2} ${y+h/2}) scale(1 ${h/w})"><g class="painted-moving painted-moving--wheel"><g transform="scale(1 ${w/h}) translate(${-x-w/2} ${-y-h/2})" clip-path="url(#${id}-part)">${picture}</g></g></g>`;
  }
  let fx='';
  if(accent){
   const [kind,x,y]=accent;
   fx=`<g transform="translate(${x} ${y})" class="painted-accent painted-accent--${kind}">${[0,1,2].map(i=>`<ellipse class="painted-particle" cx="${i*2}" cy="0" rx="${kind==='smoke'?3:1.4}" ry="${kind==='smoke'?2:1.4}" style="animation-delay:-${i*1.3}s"/>`).join('')}</g>`;
  }
  const svg=document.createElementNS('http://www.w3.org/2000/svg','svg');
  svg.classList.add('painted-motion');svg.setAttribute('aria-hidden','true');svg.setAttribute('focusable','false');
  svg.setAttribute('viewBox',`0 0 ${img.naturalWidth} ${img.naturalHeight}`);svg.setAttribute('preserveAspectRatio','xMidYMax meet');
  svg.innerHTML=`<g transform="scale(${img.naturalWidth/100} ${img.naturalHeight/100})">${defs}${base}${moving}${fx}</g>`;
  img.after(svg);img.parentElement.classList.add('has-painted-motion');
 }
 function mountRiverMotion(terrain){
  const scene=terrain.parentElement;
  if(!terrain.naturalWidth||scene.querySelector('.painted-river'))return;
  // One small sampling pass, no canvas rendering loop. Only clear water qualifies.
  const canvas=document.createElement('canvas');canvas.width=384;canvas.height=256;
  const ctx=canvas.getContext('2d',{willReadFrequently:true});if(!ctx)return;
  let pixels;try{ctx.drawImage(terrain,terrainFrame.x,terrainFrame.y,terrainFrame.width,terrainFrame.height,0,0,384,256);pixels=ctx.getImageData(0,0,384,256).data;}catch{return;}
  const water=(x,y)=>{const i=(y*384+x)*4;return pixels[i]<120&&pixels[i+1]>145&&pixels[i+2]>175&&pixels[i+2]>pixels[i]*1.5;};
  const layer=document.createElement('div');layer.className='painted-river';layer.setAttribute('aria-hidden','true');
  let count=0;
  for(let y=4;y<252;y+=8)for(let x=8;x<370;x+=14){
   if(x>58&&x<340&&y>42&&y<215)continue;
   let clear=true;for(let yy=y-2;yy<=y+2;yy++)for(let xx=x-3;xx<=x+11;xx++)if(!water(xx,yy))clear=false;
   if(!clear||count>=56)continue;
   const glint=document.createElement('i');glint.className='painted-river-glint';
   glint.style.cssText=`left:${x/384*100}%;top:${y/256*100}%;--flow-delay:-${count%9}s;--flow-time:${2.5+(count%4)*.5}s`;
   layer.append(glint);count++;
  }
  terrain.after(layer);
 }
 const buildingMenuIcons={
  castle:'<path class="icon-fill" d="M4 20V9h3V5h3v4h4V5h3v4h3v11Z"/><path d="M4 20V9h3V5h3v4h4V5h3v4h3v11M2 20h20M10 20v-5a2 2 0 0 1 4 0v5"/>',
  academy:'<path class="icon-fill" d="M9 3h6v2l-1 1v4l5 8a2 2 0 0 1-2 3H7a2 2 0 0 1-2-3l5-8V6L9 5Z"/><path d="M9 3h6M10 5v5l-5 8a2 2 0 0 0 2 3h10a2 2 0 0 0 2-3l-5-8V5M8 15h8M10 18h.01M14 17h.01"/>',
  treasure_house:'<path class="icon-fill" d="M4 10h16v10H4Z"/><path d="M4 10h16v10H4ZM3 10l2-5h14l2 5M9 10v10M15 10v10M10 14h4"/>',
  hospital:'<path class="icon-fill" d="M9 3h6v6h6v6h-6v6H9v-6H3V9h6Z"/><path d="M9 3h6v6h6v6h-6v6H9v-6H3V9h6Z"/>',
  hall_of_alliance:'<path class="icon-fill" d="M12 3 21 7v6c0 5-4 7-9 9-5-2-9-4-9-9V7Z"/><path d="M12 3 21 7v6c0 5-4 7-9 9-5-2-9-4-9-9V7ZM8 12h8M12 8v8"/>',
  trading_post:'<path class="icon-fill" d="M4 8h16v5H4Z"/><path d="M3 8h18l-2-4H5ZM5 13v7M19 13v7M3 20h18M9 13v3h6v-3"/>',
  storage:'<path class="icon-fill" d="m4 9 8-5 8 5v11H4Z"/><path d="m4 9 8-5 8 5v11H4ZM8 20v-6h8v6M7 10h10"/>',
  watch_tower:'<path class="icon-fill" d="M7 5h10l2 4-2 2v10H7V11L5 9Z"/><path d="M7 5h10l2 4-2 2v10H7V11L5 9h14M10 15h4M12 12v3"/>',
  stable:'<path class="icon-fill" d="M5 20V9l7-5 7 5v11Z"/><path d="M5 20V9l7-5 7 5v11M9 20v-6h6v6M8 10c2-2 6-2 8 0"/>',
  archery_range:'<path d="M7 3c6 3 6 15 0 18M7 3c-3 5-3 13 0 18M7 12h12M16 9l3 3-3 3"/>',
  barrack:'<path d="m5 4 14 16M19 4 5 20M4 3l4 1-3 3ZM20 3l-4 1 3 3ZM3 21l4-1M21 21l-4-1"/>',
  farm:'<path class="icon-fill" d="M12 21V8M12 12c-4 0-6-2-6-5 4 0 6 2 6 5Zm0 4c4 0 6-2 6-5-4 0-6 2-6 5Z"/><path d="M12 21V8M12 12c-4 0-6-2-6-5 4 0 6 2 6 5Zm0 4c4 0 6-2 6-5-4 0-6 2-6 5ZM12 8c0-3 1-5 3-6"/>',
  lumber_camp:'<path class="icon-fill" d="m14 3 7 7-3 3-7-7Z"/><path d="m14 3 7 7-3 3-7-7ZM13 8 4 21M3 18l4 3"/>',
  gold_mine:'<circle class="icon-fill" cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="8"/><path d="M14 8h-3a2 2 0 0 0 0 4h2a2 2 0 0 1 0 4H9M12 6v12"/>',
  quarry:'<path class="icon-fill" d="m4 18 4-9 6-4 6 13Z"/><path d="m4 18 4-9 6-4 6 13ZM3 18h18M8 9l5 4 3-6"/>',
  wall:'<path class="icon-fill" d="M4 20V8h4V5h3v3h4V5h3v3h2v12Z"/><path d="M4 20V8h4V5h3v3h4V5h3v3h2v12M2 20h20M9 20v-5h6v5"/>'
 };
 function render({host,base,state,kingdom,labels,countdown,citySkin}){
  if(!host.querySelector('.painted-village')){
   const art=`${base}/assets/art/village-layered-v2/runtime`;
   host.innerHTML=`<div class="painted-village"><div class="painted-village-scroll" tabindex="0" aria-label="Dorfansicht – mit der Maus ziehen oder wischen"><div class="painted-village-ground"><div class="painted-village-scene"><img src="${art}/terrain-extended.webp" alt="Dorfuntergrund ohne Gebäude" draggable="false">${places.map(([code,x,y,w,h])=>`<button type="button" class="painted-village-building" data-action="building" data-id="${code}" style="left:${x}%;top:${y}%;width:${w}%;height:${h}%">${code==='wall'?'':`<img class="painted-building-sprite" src="${art}/${spriteName(code)}.webp" alt="" draggable="false">`}<span class="painted-scaffold" aria-hidden="true"></span><small class="painted-build-status"></small><span class="painted-building-label"></span></button>`).join('')}</div></div></div><button class="city-building-tool" data-action="buildings" aria-label="Gebäudeübersicht öffnen">♜ <small>Gebäude</small></button></div>`;
   const terrain=host.querySelector('.painted-village-scene>img');
   const frame=terrainFrame;
   terrain.style.cssText=`left:${-frame.x/frame.width*100}%;top:${-frame.y/frame.height*100}%;width:${1448/frame.width*100}%;height:${1086/frame.height*100}%`;
   host.querySelector('.painted-village-ground').style.setProperty('--painted-terrain-image',`url("${art}/terrain-extended.webp")`);
   if(terrain.complete)mountRiverMotion(terrain);else terrain.addEventListener('load',()=>mountRiverMotion(terrain),{once:true});
   host.querySelectorAll('.painted-building-sprite').forEach(img=>{
    const mount=()=>mountBuildingMotion(img,img.parentElement.dataset.id);
    if(img.complete&&img.naturalWidth)mount();else img.addEventListener('load',mount,{once:true});
   });
   host.querySelector('.painted-village').classList.toggle('is-paused',document.hidden);
   const scroll=host.querySelector('.painted-village-scroll');requestAnimationFrame(()=>{scroll.scrollLeft=(scroll.scrollWidth-scroll.clientWidth)/2;});
   const menu=document.createElement('div');
   menu.className='painted-building-menu';menu.hidden=true;
   menu.innerHTML='<div class="painted-building-banner"><strong></strong><small></small></div><div class="painted-building-actions"><button type="button" data-action="building"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14.5 4.5 19 9l-3 3-1.7-1.7-7.6 7.6-2.6-2.6 7.6-7.6L10 6l3-3 1.5 1.5Z"/><path d="M4 20h7"/></svg><small>Ausbauen / Info</small></button><button type="button" data-action="training-building"><svg data-building-icon viewBox="0 0 24 24" aria-hidden="true"></svg><small>Öffnen</small></button><button type="button" class="painted-selection-close" aria-label="Gebäudeauswahl schließen"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg></button></div>';
   host.querySelector('.painted-village').append(menu);
   const clear=()=>{menu.hidden=true;scroll.querySelectorAll('[aria-pressed="true"]').forEach(b=>b.setAttribute('aria-pressed','false'));};
   scroll.addEventListener('click',e=>{
    const b=e.target.closest('.painted-village-building');
    if(!b){clear();return;}
    e.stopPropagation();clear();b.setAttribute('aria-pressed','true');
    menu.querySelector('strong').textContent=b.dataset.name;
    menu.querySelector('.painted-building-banner small').textContent=b.dataset.status;
    menu.querySelector('[data-building-icon]').innerHTML=buildingMenuIcons[b.dataset.id]??buildingMenuIcons.castle;
    menu.querySelector('[data-action="training-building"]').setAttribute('aria-label',b.dataset.name+' öffnen');
    menu.querySelectorAll('[data-action]').forEach(a=>a.dataset.id=b.dataset.id);
    menu.hidden=false;menu.dataset.scroll=scroll.scrollLeft+','+scroll.scrollTop;
    const r=b.getBoundingClientRect(),p=host.getBoundingClientRect();
    const resourceBottom=document.getElementById('resources')?.getBoundingClientRect().bottom??0;
    const safeTop=Math.max(74,resourceBottom-p.top+8);
    menu.style.left=Math.max(8,Math.min(p.width-menu.offsetWidth-8,r.left-p.left+r.width/2-menu.offsetWidth/2))+'px';
    const actions=menu.querySelector('.painted-building-actions'),actionBounds=actions.getBoundingClientRect();
    let safeBottom=Math.min(p.bottom,innerHeight)-8;
    for(const overlay of document.querySelectorAll('#world-chat,#navigation')){
     const bounds=overlay.getBoundingClientRect(),style=getComputedStyle(overlay);
     if(bounds.width&&bounds.height&&style.visibility!=='hidden'&&style.display!=='none'&&bounds.right>actionBounds.left&&bounds.left<actionBounds.right&&bounds.top>p.top+safeTop)safeBottom=Math.min(safeBottom,bounds.top-8);
    }
    const latestTop=safeBottom-p.top-actionBounds.height-40;
    const menuTop=Math.max(safeTop,Math.min(p.height-r.height-86,r.top-p.top,latestTop));
    menu.style.setProperty('--selected-actions-top',Math.max(40,Math.min(r.bottom-p.top-menuTop-30,safeBottom-p.top-menuTop-actionBounds.height))+'px');
    menu.style.top=menuTop+'px';
    // Keep the building commands between visible side tools on narrow screens.
    const row=actions.getBoundingClientRect(),half=row.width/2;
    let minCenter=p.left+half+8,maxCenter=p.right-half-8;
    for(const tools of document.querySelectorAll('.hud-edge-tools')){
     const bounds=tools.getBoundingClientRect(),style=getComputedStyle(tools);
     if(!bounds.width||!bounds.height||style.display==='none'||style.visibility==='hidden'||bounds.bottom<=row.top||bounds.top>=row.bottom)continue;
     if(bounds.left+ bounds.width/2<p.left+p.width/2)minCenter=Math.max(minCenter,bounds.right+half+8);
     else maxCenter=Math.min(maxCenter,bounds.left-half-8);
    }
    if(minCenter<=maxCenter)menu.style.left=Math.max(minCenter,Math.min(maxCenter,row.left+half))-p.left-menu.offsetWidth/2+'px';
   });
   menu.querySelector('.painted-selection-close').addEventListener('click',clear);
   menu.addEventListener('click',e=>{if(e.target.closest('[data-action]'))clear();});
   host.addEventListener('keydown',e=>{if(e.key==='Escape'){clear();}});
   scroll.addEventListener('scroll',()=>{if(menu.dataset.scroll!==scroll.scrollLeft+','+scroll.scrollTop)clear();},{passive:true});
   let drag=null,suppressClick=false;
   scroll.addEventListener('pointerdown',e=>{
    suppressClick=false;
    if(e.pointerType!=='mouse'||e.button!==0)return;
    drag={id:e.pointerId,x:e.clientX,y:e.clientY,left:scroll.scrollLeft,top:scroll.scrollTop,moved:false};
   });
   scroll.addEventListener('pointermove',e=>{
    if(!drag||drag.id!==e.pointerId)return;
    if(!(e.buttons&1)){drag=null;scroll.classList.remove('is-dragging');return;}
    const dx=e.clientX-drag.x,dy=e.clientY-drag.y;
    if(!drag.moved&&Math.hypot(dx,dy)<6)return;
    if(!drag.moved){drag.moved=true;scroll.setPointerCapture(e.pointerId);scroll.classList.add('is-dragging');}
    e.preventDefault();
    scroll.scrollLeft=drag.left-dx;scroll.scrollTop=drag.top-dy;
   });
   const finish=e=>{
    if(!drag||drag.id!==e.pointerId)return;
    suppressClick=drag.moved;drag=null;scroll.classList.remove('is-dragging');
    if(scroll.hasPointerCapture(e.pointerId))scroll.releasePointerCapture(e.pointerId);
   };
   scroll.addEventListener('pointerup',finish);
   scroll.addEventListener('pointercancel',finish);
   scroll.addEventListener('lostpointercapture',finish);
   scroll.addEventListener('pointerleave',e=>{if(drag&&!drag.moved)finish(e);});
   scroll.addEventListener('click',e=>{if(suppressClick&&e.detail!==0){e.preventDefault();e.stopImmediatePropagation();suppressClick=false;}},true);
  }
  syncCastleSkin(host.querySelector('[data-id="castle"]'),base,citySkin??state.city?.city_skin??'default');
  for(const [code]of places){
   const b=host.querySelector(`[data-id="${code}"]`),level=state.buildings?.[code]?.level??0;
   const queue=(state.build_queue||[]).find(q=>q.building_code===code&&!Number(q.is_processed));
   const training=['barrack','archery_range','stable'].includes(code)?(state.troop_queue||[]).find(q=>!Number(q.is_processed)&&trainingBuildingFor(state,q)===code):null;
   const status=queue?'Stufe '+level+' · Im Ausbau':training?`Stufe ${level} · ${training.count} Truppen in Ausbildung`:level>0?'Stufe '+level:'Freier Bauplatz';
   const text=`${labels[code]||code} · ${status}`;
   b.classList.toggle('is-building',Boolean(queue));
   const scaffold=b.querySelector('.painted-scaffold');
   if(queue&&!scaffold.firstChild){
    scaffold.innerHTML=constructionArtwork(base,code);
    scaffold.style.setProperty('--construction-delay',-(places.findIndex(place=>place[0]===code)%5)*.47+'s');
   }else if(!queue&&scaffold.firstChild)scaffold.replaceChildren();
   b.classList.toggle('is-training',Boolean(training));
   b.classList.toggle('is-empty',!queue&&level<=0&&code!=='wall');
   b.dataset.status=status;
   const badge=b.querySelector('.painted-build-status');
   const activity=queue||training;
   const signature=activity?(queue?'build:':'training:')+String(activity.id||'')+':'+String(activity.finishes_at||''):'';
   if(badge.dataset.queue!==signature){
    badge.dataset.queue=signature;
    badge.textContent=queue?'Im Ausbau':training?`Ausbildung · ${training.count}`:'';
    if(activity?.finishes_at&&countdown)badge.innerHTML=training
     ?`<span class="painted-training-heading"><b>Ausbildung</b><strong>${Number(training.count)||0} Truppen</strong></span><span class="painted-training-time"><small>Restzeit</small>${countdown(training.finishes_at)}</span>`
     :`<span class="painted-building-heading"><b>Ausbau</b><strong>Stufe ${Number(queue.level_to)||level+1}</strong></span><span class="painted-building-time"><small>Restzeit</small>${countdown(queue.finishes_at)}</span>`;
   }
   b.dataset.name=labels[code]||code;b.dataset.level=level;
    b.setAttribute('aria-label',text);b.title=text;b.querySelector('.painted-building-label').textContent=text;
  }
  const selected=host.querySelector('.painted-village-building[aria-pressed="true"]');
  if(selected)host.querySelector('.painted-building-banner small').textContent=selected.dataset.status;
  updateReadiness({host,base,state,kingdom,labels});
 }
 return {render,readiness,updateReadiness};
})();

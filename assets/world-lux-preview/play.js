import {FIELD_WIDTH,FIELD_HEIGHT,hash,parseRegions,contains,buildPopulation,resourceSite,checkCityPlacement,findCityPlacement} from './play-world.mjs?v=7';
import {createHydrology} from './hydrology.mjs?v=1';
const $=s=>document.querySelector(s), stage=$('#play-stage'), canvas=$('#play-canvas'), g=canvas.getContext('2d');
const escape=v=>String(v).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const nf=new Intl.NumberFormat('de-LU');
const mode=new URLSearchParams(location.search).get('population')==='live'?'live':'alpha';
const camera={x:349,y:649,unit:44},view={width:1,height:1,mode,grid:false,borders:true,borderMode:new URLSearchParams(location.search).get('borders')==='original'?'original':'game',originalOverlay:false,cityOverlay:false,tp:false};
let cantons,communes,model,art,land,geographies,hydrology,waterFocus=null,placement=null,ready=false,pending=0,pointers=new Map(),gesture=null,moved=false,ownHistory=false;
const populations=new Map();
const visibleButtons=new Map(),dialogs=[$('#world-overview'),$('#object-dialog'),$('#scale-dialog')];
const clamp=(v,a,b)=>Math.max(a,Math.min(b,v));
const project=(x,y)=>[(x-camera.x)*camera.unit+view.width/2,(y-camera.y)*camera.unit+view.height/2];
const unproject=(x,y)=>[(x-view.width/2)/camera.unit+camera.x,(y-view.height/2)/camera.unit+camera.y];
const image=key=>new Promise((resolve,reject)=>{const im=new Image();im.onload=()=>resolve(im);im.onerror=()=>reject(new Error(`Artwork missing: ${key}`));im.src=`art/${key}.webp`;});
function redraw(){if(!pending)pending=requestAnimationFrame(()=>{pending=0;if(ready)draw();});}
function resize(){const r=stage.getBoundingClientRect();view.width=r.width;view.height=r.height;const dpr=Math.min(devicePixelRatio||1,2);canvas.width=Math.round(r.width*dpr);canvas.height=Math.round(r.height*dpr);redraw();if($('#world-overview').open)drawOverview();}
function limit(){camera.x=clamp(camera.x,0,FIELD_WIDTH);camera.y=clamp(camera.y,0,FIELD_HEIGHT);}
function setZoom(value,anchor=[view.width/2,view.height/2]){const before=unproject(...anchor);camera.unit=clamp(value,22,64);const after=unproject(...anchor);camera.x+=before[0]-after[0];camera.y+=before[1]-after[1];limit();redraw();}
function home(){waterFocus=null;camera.x=model.home.x+(view.width>650?5:0);camera.y=model.home.y-1;camera.unit=view.width>650?44:32;if(view.tp)probeAt(camera.x,camera.y);redraw();}
function worldTransform(ctx){ctx.translate(view.width/2-camera.x*camera.unit,view.height/2-camera.y*camera.unit);ctx.scale(camera.unit,camera.unit);}
function feather(im){const c=document.createElement('canvas');c.width=c.height=768;const x=c.getContext('2d');x.drawImage(im,0,0,768,768);x.globalCompositeOperation='destination-in';for(const vertical of [false,true]){const f=x.createLinearGradient(0,0,vertical?0:128,vertical?128:0);f.addColorStop(0,'transparent');f.addColorStop(1,'#000');x.fillStyle=f;x.fillRect(0,0,768,768);}return c;}
function regionShape(region){const p=new Path2D();for(const r of region.rings){p.moveTo(...r[0]);for(const xy of r.slice(1))p.lineTo(...xy);p.closePath();}return p;}
function switchBorders(value){
 view.borderMode=value;({cantons,communes,land}=geographies[value]);
 document.querySelectorAll('[data-border-mode]').forEach(b=>b.setAttribute('aria-pressed',String(b.dataset.borderMode===value)));
 $('#original-overlay').disabled=value==='original';
 $('#border-description').textContent=value==='game'?'Ruhigere Grenzen, ganze Baufelder. Die Platzprobe nutzt diese Aufteilung.':'Die bisherigen Verwaltungsgrenzen mit kleinen Zacken und schmalen Ausläufern.';
 switchPopulation(view.mode);
}
function switchPopulation(value){
 const key=`${view.borderMode}:${value}`;if(!populations.has(key))populations.set(key,buildPopulation(cantons,communes,value,hydrology));model=populations.get(key);view.mode=value;
 for(const b of visibleButtons.values())b.remove();visibleButtons.clear();
 $('#population').value=value;$('#population-note').textContent=value==='alpha'?'20 Beispielstädte · in allen 12 Kantonen':'1.000 Beispielstädte · in allen 100 Gemeinden';
 $('#density-note').textContent=`${nf.format(model.cities.length)} Beispielstädte · keine echten Spieler`;
 $('#overview-count').textContent=`${nf.format(model.cities.length)} Städte`;
 const url=new URL(location.href);url.searchParams.set('population',value);url.searchParams.set('borders',view.borderMode);history.replaceState(history.state,'',url.pathname+url.search+url.hash);if(view.tp)probeAt(placement?.x??camera.x,placement?.y??camera.y);redraw();
}
function localObjects(bounds){
 const result=model.items.filter(o=>o.x>=bounds.left-6&&o.x<=bounds.right+6&&o.y>=bounds.top-3&&o.y<=bounds.bottom+7);
 // Stable field positions: moving the camera never rerolls a resource or monster.
 for(let cy=Math.floor(bounds.top/9)-1;cy<=Math.ceil(bounds.bottom/9);cy++)for(let cx=Math.floor(bounds.left/9)-1;cx<=Math.ceil(bounds.right/9);cx++){
  const site=resourceSite(cx,cy);if(model.fits(site.x,site.y,1,2))result.push(site);
 }
 return result;
}
function paintWater(bounds){
 g.save();worldTransform(g);drawWaters(g,hydrology.visible({left:bounds.left-1,top:bounds.top-1,right:bounds.right+1,bottom:bounds.bottom+1}));g.restore();
}
function riverRipples(river){
 const shape=new Path2D();let next=3,travelled=0,index=0;
 for(let i=1;i<river.points.length;i++){
  const a=river.points[i-1],b=river.points[i],length=Math.hypot(b[0]-a[0],b[1]-a[1]);if(!length)continue;
  const dx=(b[0]-a[0])/length,dy=(b[1]-a[1])/length;
  while(next<travelled+length){
   const t=(next-travelled)/length,offset=(index++%2?-.25:.25),x=a[0]+t*(b[0]-a[0])-dy*offset,y=a[1]+t*(b[1]-a[1])+dx*offset;
   shape.moveTo(x-dx*.3,y-dy*.3);shape.quadraticCurveTo(x-dy*.08,y+dx*.08,x+dx*.3,y+dy*.3);next+=6;
  }
  travelled+=length;
 }
 return shape;
}
function drawWaters(ctx,features,overviewScale=0){
 ctx.save();ctx.lineJoin='round';ctx.lineCap='round';
 if(!overviewScale){for(const [extra,color] of [[.65,'#769b74'],[.3,'#edd09a']]){ctx.strokeStyle=color;for(const lake of features.lakes){ctx.lineWidth=extra;ctx.stroke(lake.shape);}}}
 ctx.strokeStyle='#3793bd';for(const river of features.rivers){ctx.lineWidth=overviewScale?Math.max(.8/overviewScale,river.width):river.width;ctx.stroke(river.shape);}
 ctx.fillStyle=overviewScale?'#3793bd':'#72bfd1';for(const lake of features.lakes)ctx.fill(lake.shape,'evenodd');
 if(!overviewScale){ctx.strokeStyle='#72bfd1';for(const river of features.rivers){ctx.lineWidth=river.width*.84;ctx.stroke(river.shape);}}
 if(!overviewScale){ctx.strokeStyle='#fbf6ec';ctx.globalAlpha=.48;ctx.lineWidth=.025;for(const river of features.rivers)ctx.stroke(river.ripples);}
 ctx.restore();
}
function draw(){
 const dpr=canvas.width/view.width;g.setTransform(dpr,0,0,dpr,0,0);g.fillStyle='#e9dfcf';g.fillRect(0,0,view.width,view.height);
 const [left,top]=unproject(0,0),[right,bottom]=unproject(view.width,view.height),bounds={left,top,right,bottom};
 g.save();worldTransform(g);g.clip(land);g.setTransform(dpr,0,0,dpr,0,0);g.fillStyle='#b9c985';g.fillRect(0,0,view.width,view.height);
 const span=18,size=span*768/640;
 for(let y=Math.floor(top/span)-1;y<=Math.ceil(bottom/span);y++)for(let x=Math.floor(left/span)-1;x<=Math.ceil(right/span);x++){const p=project(x*span,y*span);g.drawImage(art.tiles[hash(x,y)%2],p[0],p[1],size*camera.unit,size*camera.unit);}
 if(view.grid){g.save();worldTransform(g);g.beginPath();for(let x=Math.max(0,Math.floor(left));x<=Math.min(FIELD_WIDTH,Math.ceil(right));x++){g.moveTo(x,top);g.lineTo(x,bottom);}for(let y=Math.max(0,Math.floor(top));y<=Math.min(FIELD_HEIGHT,Math.ceil(bottom));y++){g.moveTo(left,y);g.lineTo(right,y);}g.strokeStyle='#66533735';g.lineWidth=.7/camera.unit;g.stroke();g.restore();}
 if(view.borders){g.save();worldTransform(g);g.setLineDash([.22,.16]);g.lineWidth=1.4/camera.unit;g.strokeStyle='#2a72c978';for(const c of communes)if(c.bounds[0][0]<right&&c.bounds[1][0]>left&&c.bounds[0][1]<bottom&&c.bounds[1][1]>top)g.stroke(c.shape);g.setLineDash([]);g.lineWidth=2.5/camera.unit;g.strokeStyle='#5c4270a0';for(const c of cantons)g.stroke(c.shape);g.restore();}
 const objects=localObjects(bounds);
 for(let y=Math.floor(top/3)-1;y<=Math.ceil(bottom/3);y++)for(let x=Math.floor(left/3)-1;x<=Math.ceil(right/3);x++){
  const h=hash(x,y,891);if(h%100>52)continue;const px=x*3+.4+(h>>>8)%20/10,py=y*3+.4+(h>>>15)%20/10;
  if(model.waterAt(px,py)||objects.some(o=>Math.abs(px-o.x)<o.size/2+1.1&&Math.abs(py-o.y)<o.size/2+1.1))continue;
  const screen=project(px,py),s=camera.unit*(1.15+(h>>>19)%8/10);g.drawImage(h%2?art.oak:art.pine,screen[0]-s/2,screen[1]-s*.88,s,s);
 }
 // Real 4×4 city footprints are visible when the field overlay is enabled.
 if(view.grid){g.save();worldTransform(g);for(const o of objects){g.strokeStyle=o.self?'#c5a361':'#5c427061';g.lineWidth=1.5/camera.unit;g.strokeRect(o.x-o.size/2,o.y-o.size/2,o.size,o.size);}g.restore();}
 g.restore();
 paintWater(bounds);
 if(view.tp&&placement)drawPlacement();
 updateObjects(objects);
 const region=communes.find(c=>contains(c,camera.x,camera.y));if(waterFocus&&Math.hypot(camera.x-waterFocus.point[0],camera.y-waterFocus.point[1])>18)waterFocus=null;$('#location-name').textContent=waterFocus?`${waterFocus.name}${region?` · ${region.name}`:''}`:region?`${region.name} · Kanton ${cantons.find(c=>c.id===region.canton)?.name||''}`:'Am Rand des Großherzogtums';
 $('#coordinates').textContent=`X ${Math.round(camera.x)} · Y ${Math.round(camera.y)}`;$('#play-plus').disabled=camera.unit>=64;$('#play-minus').disabled=camera.unit<=22;
 drawMinimap();if($('#world-overview').open)drawOverview();
}
function updateObjects(objects){
 const active=new Set();
 for(const o of objects.sort((a,b)=>a.y-b.y)){
  const [x,y]=project(o.x,o.y),size=camera.unit*(o.type==='city'?4.5:o.type==='alliance'?5.2:o.type==='dungeon'?6:1.9);
  if(x<-size/2||x>view.width+size/2||y<-15||y>view.height+size*.85)continue;
  active.add(o.id);let button=visibleButtons.get(o.id);
  if(!button){button=document.createElement('button');button.type='button';button.className='world-object';button.dataset.type=o.type;button.dataset.object=o.id;button.setAttribute('aria-label',`${o.name}, ${o.type==='city'?'Spielerstadt':o.type==='resource'?'Rohstoffplatz':o.type==='monster'?'Monster':o.type==='dungeon'?'Kantonsdungeon':'Allianzhalle'}, Stufe ${o.level}`);button.innerHTML=`<img src="art/${o.art}.webp" alt="" draggable="false"><span class="object-caption">${o.type==='city'?`<span class="city-level">${o.level}</span><span class="city-name">${escape(o.name)}</span>`:`${escape(o.name)}${['resource','monster'].includes(o.type)?` · ${o.level}`:''}`}</span>`;button.addEventListener('click',e=>{if(!moved||e.detail===0){button._object=o;openObject(o);}});button._object=o;$('#play-markers').append(button);visibleButtons.set(o.id,button);}
  button.style.left=x+'px';button.style.top=y+'px';button.style.setProperty('--object-size',size+'px');button.style.zIndex=String(Math.round(y+size));
 }
 for(const [id,b]of visibleButtons)if(!active.has(id)){b.remove();visibleButtons.delete(id);}
}
function mapDrawing(c,w,h,large=false){
 if(w<40||h<40)return null;
 c.clearRect(0,0,w,h);const pad=large?18:5,k=Math.min((w-pad*2)/FIELD_WIDTH,(h-pad*2)/FIELD_HEIGHT),ox=(w-FIELD_WIDTH*k)/2,oy=(h-FIELD_HEIGHT*k)/2;
 c.save();c.translate(ox,oy);c.scale(k,k);c.fillStyle='#b9c985';c.fill(land);c.strokeStyle='#586d50';c.lineWidth=1/k;c.stroke(land);
 c.strokeStyle='#756080';c.lineWidth=(large?1.2:.4)/k;for(const p of cantons)c.stroke(p.shape);
 drawWaters(c,hydrology,k);
 if(large&&view.borderMode==='game'&&view.originalOverlay){c.save();c.setLineDash([4/k,3/k]);c.strokeStyle='#a07636c0';c.lineWidth=1/k;for(const p of geographies.original.cantons)c.stroke(p.shape);c.restore();}
 if(!large||view.cityOverlay){c.fillStyle='#24559c';const dot=large?(view.mode==='alpha'?2:clamp(k*4,.55,1.7)):.65;for(const o of model.cities){c.beginPath();c.arc(o.x,o.y,dot/k,0,Math.PI*2);c.fill();}}
 if(large&&!view.cityOverlay&&k>.25){c.font=`${12/k}px "Conquer UI"`;c.textAlign='center';c.textBaseline='middle';c.lineJoin='round';c.lineWidth=3/k;c.strokeStyle='#fbf6ec';c.fillStyle='#443549';for(const p of cantons){c.strokeText(p.name,...p.point);c.fillText(p.name,...p.point);}}
 if(large)$('#overview-legend').textContent=view.originalOverlay&&view.borderMode==='game'?'Gestrichelt: bisherige Grenzen':`${view.cityOverlay?'Punkte: Städte · ':''}Blau: Gewässer`;
 const [x,y]=unproject(0,0);c.strokeStyle='#c58b2d';c.lineWidth=(large?2:1.5)/k;c.strokeRect(x,y,view.width/camera.unit,view.height/camera.unit);
 c.fillStyle='#f5d475';c.beginPath();c.arc(model.home.x,model.home.y,(large?4:2)/k,0,Math.PI*2);c.fill();c.restore();
 return {k,ox,oy};
}
function drawMinimap(){const c=$('#minimap'),r=c.getBoundingClientRect();if(r.width<1||r.height<1)return;const dpr=Math.min(devicePixelRatio||1,2),w=Math.round(r.width*dpr),h=Math.round(r.height*dpr);if(c.width!==w||c.height!==h){c.width=w;c.height=h;}const x=c.getContext('2d');x.setTransform(dpr,0,0,dpr,0,0);mapDrawing(x,r.width,r.height);}
function drawPlacement(){
 const {x,y,ok}=placement;g.save();worldTransform(g);g.fillStyle=ok?'#586d5055':'#b43c3455';g.fillRect(x-2,y-2,4,4);
 g.strokeStyle=ok?'#3f523a':'#85342b';g.lineWidth=2/camera.unit;g.beginPath();for(let n=-2;n<=2;n++){g.moveTo(x+n,y-2);g.lineTo(x+n,y+2);g.moveTo(x-2,y+n);g.lineTo(x+2,y+n);}g.stroke();g.lineWidth=4/camera.unit;g.strokeRect(x-2,y-2,4,4);
 g.globalAlpha=.45;g.drawImage(art.castle,x-1.6,y-2.2,3.2,3.2);g.globalAlpha=1;g.fillStyle=ok?'#3f523a':'#85342b';g.fillRect(x-1.35,y+1.4,2.7,.6);g.fillStyle='#fffcf6';g.font='.35px "Conquer UI"';g.textAlign='center';g.fillText(ok?'4 × 4 · frei':'4 × 4 · gesperrt',x,y+1.84);g.restore();
}
function probeAt(x,y){
 placement=checkCityPlacement(model,cantons,communes,x,y);
 $('#tp-panel').dataset.valid=String(placement.ok);$('#tp-status').textContent=`${placement.ok?'✓ Freier Platz':'× Platz gesperrt'}${placement.canton?` · ${placement.canton}`:''}`;
 $('#tp-reason').textContent=placement.reason;$('#tp-panel .tp-hint').textContent=`X ${placement.x} / Y ${placement.y} · Nur Platzprobe. Karte antippen.`;
 redraw();
}
function nearestProbe(){
 const free=findCityPlacement(model,cantons,communes,camera.x,camera.y);
 if(free){probeAt(free.x,free.y);camera.x=free.x;camera.y=free.y+(view.width<=650?1.5:0);redraw();}
 else{$('#tp-status').textContent='Kein freier Platz in der Nähe';$('#tp-reason').textContent='Verschiebe die Karte oder besuche einen anderen Kanton.';}
}
let gridBeforeProbe=false;
function toggleProbe(force){
 const next=force??!view.tp;if(next===view.tp)return;
 view.tp=next;stage.classList.toggle('tp-active',next);$('#tp-panel').hidden=!next;$('#tp-toggle').setAttribute('aria-pressed',String(next));$('#play-markers').inert=next;
 if(next){gridBeforeProbe=view.grid;view.grid=true;probeAt(camera.x,camera.y);}else view.grid=gridBeforeProbe;
 $('#grid-toggle').checked=view.grid;redraw();
}
let overviewProjection;
function drawOverview(){if(!ready||!$('#world-overview').open)return;const c=$('#overview-canvas'),r=c.getBoundingClientRect(),dpr=Math.min(devicePixelRatio||1,2);if(r.width<1||r.height<1)return;c.width=Math.round(r.width*dpr);c.height=Math.round(r.height*dpr);const x=c.getContext('2d');x.setTransform(dpr,0,0,dpr,0,0);overviewProjection=mapDrawing(x,r.width,r.height,true);}
function showDialog(dialog){for(const d of dialogs)if(d!==dialog&&d.open)d.close();if(!dialog.open)dialog.showModal();if(dialog.id==='world-overview')requestAnimationFrame(drawOverview);}
function openHash(hash){if(location.hash!==`#${hash}`){history.pushState({playPreview:true},'',`#${hash}`);ownHistory=true;}renderHash();}
function closeDialog(){if(ownHistory){ownHistory=false;history.back();}else{history.replaceState(null,'',location.pathname+location.search);renderHash();}}
function objectContent(o){
 const type={city:'Spielerstadt',resource:'Rohstoffplatz',monster:'PvE-Monster',alliance:'Allianzgebäude',dungeon:'Kantonsdungeon'}[o.type];
 $('#object-kind').textContent=`${type} · X ${o.x} · Y ${o.y}`;$('#object-title').textContent=o.name;
 const text=o.type==='city'?`<p><strong>${o.self?'Deine Beispielstadt':escape(o.alliance)}</strong> · Stufe ${o.level}</p><p>Diese Stadt reserviert <strong>4 × 4 = 16 Felder</strong>. Die große Burg und ihr Namensschild bleiben auch in einer Welt in Luxemburgs Form sichtbar.</p><p>Schon die 20 Alpha-Städte verteilen sich über alle zwölf Kantone. In der Live-Vorschau sind alle 100 Gemeinden besiedelt. Mersch ist lediglich der Standort deiner Beispielstadt.</p>`:o.type==='alliance'?'<p>Ein gemeinsamer Stützpunkt zwischen den Städten. Hier würde euer Bund Nachschub, Garnisonen und gemeinsame Angriffe organisieren.</p>':o.type==='dungeon'?'<p>Unter Mersch verbinden verzauberte Wasserläufe die Hallen der drei Flüsse. Der Dungeon ist eine feste Landmarke eures Kantons.</p><p><a href="../dungeon-preview/#kanton-04">Geschichte und Beute im Dungeonatlas ansehen ↗</a></p>':o.type==='monster'?'<p>Eine Begegnung auf der normalen Weltkarte. Monster und ihre Lager beleben die Freiflächen zwischen euren Städten.</p>':'<p>Ein Rohstoffplatz für eure Versorgung. Einblendbare Felder zeigen die Abstände zu Städten und benachbarten Sammelplätzen.</p>';
 $('#object-content').innerHTML=`<img class="object-detail-art" src="art/${o.art}.webp" alt="">${text}<p class="fine-print">Beispielobjekt. Hier werden keine Truppen entsendet und keine Spielstände verändert.</p>`;showDialog($('#object-dialog'));
}
let selectedObject=null;
function openObject(o){selectedObject=o;openHash(`ort-${o.id}`);}
function renderHash(){if(!ready)return;const params=new URLSearchParams(location.search),desired=params.get('population')==='live'?'live':'alpha',borders=params.get('borders')==='original'?'original':'game';if(borders!==view.borderMode){view.mode=desired;switchBorders(borders);}else if(desired!==view.mode)switchPopulation(desired);const h=location.hash.slice(1);if(h==='welt'){showDialog($('#world-overview'));return;}if(h==='massstab'){showDialog($('#scale-dialog'));return;}if(h.startsWith('ort-')){const id=h.slice(4),o=model.items.find(o=>o.id===id)||(selectedObject?.id===id?selectedObject:null);if(o){objectContent(o);return;}}for(const d of dialogs)if(d.open)d.close();}
function bind(){
 $('#population').addEventListener('change',e=>switchPopulation(e.target.value));$('#grid-toggle').addEventListener('change',e=>{view.grid=e.target.checked;redraw();});$('#borders-toggle').addEventListener('change',e=>{view.borders=e.target.checked;redraw();});
 $('#play-plus').addEventListener('click',()=>setZoom(camera.unit*1.25));$('#play-minus').addEventListener('click',()=>setZoom(camera.unit/1.25));$('#play-home').addEventListener('click',home);
 document.querySelectorAll('[data-open-map]').forEach(b=>b.addEventListener('click',()=>openHash('welt')));$('#info-button').addEventListener('click',()=>openHash('massstab'));document.querySelectorAll('[data-close]').forEach(b=>b.addEventListener('click',closeDialog));
 $('#overview-home').addEventListener('click',()=>{home();closeDialog();});
 document.querySelectorAll('[data-border-mode]').forEach(b=>b.addEventListener('click',()=>switchBorders(b.dataset.borderMode)));
 $('#original-overlay').addEventListener('change',e=>{view.originalOverlay=e.target.checked;redraw();});
 $('#city-overlay').addEventListener('change',e=>{view.cityOverlay=e.target.checked;redraw();});
 $('#tp-toggle').addEventListener('click',()=>toggleProbe());$('#tp-close').addEventListener('click',()=>toggleProbe(false));$('#tp-find').addEventListener('click',nearestProbe);
 $('#overview-tp').addEventListener('click',()=>{closeDialog();toggleProbe(true);nearestProbe();});
 stage.addEventListener('click',e=>{if(!view.tp||moved||e.target.closest('button,input,label,#tp-panel'))return;const r=stage.getBoundingClientRect();probeAt(...unproject(e.clientX-r.left,e.clientY-r.top));});
 document.addEventListener('keydown',e=>{if(e.key==='Escape'&&view.tp&&!dialogs.some(d=>d.open)){e.preventDefault();toggleProbe(false);}});
 for(const d of dialogs){d.addEventListener('cancel',e=>{e.preventDefault();closeDialog();});d.addEventListener('click',e=>{if(e.target===d){const r=d.getBoundingClientRect();if(e.clientX<r.left||e.clientX>r.right||e.clientY<r.top||e.clientY>r.bottom)closeDialog();}});}
 $('#commune-jump').addEventListener('change',e=>{const water=hydrology.visits.find(v=>v.id===e.target.value),c=water||communes.find(c=>c.id===e.target.value);if(c){waterFocus=water||null;[camera.x,camera.y]=c.point;if(view.tp)probeAt(camera.x,camera.y);redraw();closeDialog();e.target.value='';}});
 $('#overview-canvas').addEventListener('click',e=>{if(!overviewProjection)return;const r=e.currentTarget.getBoundingClientRect(),{k,ox,oy}=overviewProjection,x=(e.clientX-r.left-ox)/k,y=(e.clientY-r.top-oy)/k;if(model.inLand(x,y)){camera.x=x;camera.y=y;redraw();closeDialog();}});
 window.addEventListener('popstate',()=>{ownHistory=Boolean(history.state?.playPreview);renderHash();});window.addEventListener('hashchange',renderHash);
 stage.addEventListener('wheel',e=>{if(e.target.closest('button,input,label'))return;e.preventDefault();const r=stage.getBoundingClientRect();setZoom(camera.unit*Math.exp(-e.deltaY*.0015),[e.clientX-r.left,e.clientY-r.top]);},{passive:false});
 stage.addEventListener('keydown',e=>{if(e.target!==stage)return;const direction={ArrowLeft:[-3,0],ArrowRight:[3,0],ArrowUp:[0,-3],ArrowDown:[0,3]};if(direction[e.key]){e.preventDefault();const step=direction[e.key].map(n=>n/(view.tp?3:1));camera.x+=step[0];camera.y+=step[1];if(view.tp)probeAt(placement.x+step[0],placement.y+step[1]);limit();redraw();}else if(['+','=','-','Home'].includes(e.key)){e.preventDefault();if(e.key==='Home')home();else setZoom(camera.unit*(e.key==='-'?.8:1.25));}});
 const pos=e=>{const r=stage.getBoundingClientRect();return[e.clientX-r.left,e.clientY-r.top];};
 const startGesture=()=>{const p=[...pointers.values()];gesture={p:p.map(v=>v.slice()),x:camera.x,y:camera.y,unit:camera.unit};if(p.length===2){gesture.distance=Math.hypot(p[0][0]-p[1][0],p[0][1]-p[1][1]);gesture.anchor=unproject((p[0][0]+p[1][0])/2,(p[0][1]+p[1][1])/2);}};
 stage.addEventListener('pointerdown',e=>{if(e.target.closest('input,label,.play-zoom,.play-options,.coordinate-bar,#minimap-button,#tp-panel'))return;moved=false;pointers.set(e.pointerId,pos(e));if(!e.target.closest('.world-object'))stage.setPointerCapture(e.pointerId);startGesture();});
 stage.addEventListener('pointermove',e=>{if(!pointers.has(e.pointerId)||!gesture)return;pointers.set(e.pointerId,pos(e));const p=[...pointers.values()];if(p.length===2&&gesture.distance){camera.unit=clamp(gesture.unit*Math.hypot(p[0][0]-p[1][0],p[0][1]-p[1][1])/gesture.distance,22,64);camera.x=gesture.anchor[0]-((p[0][0]+p[1][0])/2-view.width/2)/camera.unit;camera.y=gesture.anchor[1]-((p[0][1]+p[1][1])/2-view.height/2)/camera.unit;moved=true;}else{const dx=p[0][0]-gesture.p[0][0],dy=p[0][1]-gesture.p[0][1];if(Math.hypot(dx,dy)>5){moved=true;stage.setPointerCapture(e.pointerId);}camera.x=gesture.x-dx/camera.unit;camera.y=gesture.y-dy/camera.unit;}stage.classList.toggle('dragging',moved);limit();redraw();});
 const end=e=>{pointers.delete(e.pointerId);if(pointers.size)startGesture();else{gesture=null;stage.classList.remove('dragging');}};stage.addEventListener('pointerup',end);stage.addEventListener('pointercancel',end);stage.addEventListener('lostpointercapture',end);
 document.addEventListener('visibilitychange',()=>{pointers.clear();gesture=null;stage.classList.remove('dragging');if(!document.hidden)redraw();});
 new ResizeObserver(resize).observe(stage);new ResizeObserver(drawOverview).observe($('#overview-stage'));
 new ResizeObserver(entries=>stage.style.setProperty('--location-height',`${entries[0].borderBoxSize?.[0]?.blockSize||$('.location-card').getBoundingClientRect().height}px`)).observe($('.location-card'));
}
async function init(){try{
 const [regions,waterData]=await Promise.all([Promise.all([['original','geography.json'],['game','game-geography.json']].map(async([key,url])=>{const res=await fetch(url);if(!res.ok)throw new Error('Geography unavailable');const data=await res.json(),cs=parseRegions(data.cantons),ms=parseRegions(data.communes),shape=new Path2D();for(const c of cs){c.shape=regionShape(c);shape.addPath(c.shape);}for(const c of ms)c.shape=regionShape(c);return[key,{cantons:cs,communes:ms,land:shape}];})),fetch('game-hydrology.json?v=2').then(r=>{if(!r.ok)throw new Error('Hydrology unavailable');return r.json();})]);
 geographies=Object.fromEntries(regions);hydrology=createHydrology(waterData);
 for(const river of hydrology.rivers){const p=new Path2D();p.moveTo(...river.points[0]);for(const xy of river.points.slice(1))p.lineTo(...xy);river.shape=p;river.ripples=riverRipples(river);}for(const lake of hydrology.lakes)lake.shape=regionShape(lake);
 ({cantons,communes,land}=geographies[view.borderMode]);
 const keys=['tile-0','tile-1','oak','pine','castle','water','fire','alliance','farm','lumber','quarry','gold','crystal','orc','golem','shrine'];art=Object.fromEntries(await Promise.all(keys.map(async k=>[k,await image(k)])));art.tiles=[feather(art['tile-0']),feather(art['tile-1'])];
 for(const [label,places] of [['Flüsse & Seen',hydrology.visits],['Gemeinden',[...communes].sort((a,b)=>a.name.localeCompare(b.name,'de'))]]){const group=document.createElement('optgroup');group.label=label;for(const c of places){const o=document.createElement('option');o.value=c.id;o.textContent=c.name;group.append(o);}$('#commune-jump').append(group);}
 switchBorders(view.borderMode);bind();ready=true;resize();home();renderHash();$('#play-loading').hidden=true;
}catch(error){$('#play-loading').textContent='Die Spielansicht konnte nicht geladen werden. Bitte lade die Seite erneut.';console.error('World play preview:',error);}}
init();

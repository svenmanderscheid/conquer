import {treesIn,overlaps} from './scenery.js';
import {buildMotionFrames,motionFrame,matchDragonSize} from './monster-motion.js?v=10';
const canvas=document.querySelector('#map'),ctx=canvas.getContext('2d');
const camera={x:768,y:512,zoom:1},limits={min:.18,max:2.5};
const items=[
 {id:'castle',name:'Burg',x:560,y:515,w:250,h:250,src:'../art/fantasy-village-v1/world-castle-v2.png',description:'Deine Burg mit weichem Grasübergang als eigenes Kartenobjekt.'},
 {id:'gold',name:'Goldmine',x:840,y:580,w:205,h:205,src:'../art/fantasy-village-v1/world-gold-v2.png',description:'Eine unabhängig platzierte Rohstoffstelle ohne harte Bodenplatte. Hier wird noch kein Gold gesammelt.'},
 {id:'orc',name:'Ork',x:760,y:315,w:145,h:145,src:'../art/monsters/2.5d/bright-v2/orc.png',description:'Ein separat auswählbares Monster. Dieser Test startet keinen Angriff.'}
];
items[2].monster=true;
const resources=[['quarry','Steinbruch'],['crystal','Kristallmine'],['lumber','Holzfällerlager'],['farm','Kornfarm']];
resources.forEach(([id,name],n)=>items.push({id,name,x:340+n*280,y:800,w:205,h:205,src:`../art/fantasy-village-v1/world-${id}-v8.png`,description:'Neu gezeichnet im Stil der Goldmine, ohne harte Bodenplatte. Kein Sammelauftrag.'}));
const monsters=[['skeleton','Skelett'],['golem','Golem'],['treasure-goblin','Schatzgoblin'],['green-dragon','Grüner Drache'],['red-dragon','Roter Drache'],['gold-dragon','Golddrache'],['magdar','Magdar']];
monsters.forEach(([id,name],n)=>items.push({id,name,x:340+(n%4)*280,y:n<4?1080:1370,w:n<3?140:190,h:n<3?140:190,monster:true,src:`../art/monsters/2.5d/bright-v2/${id}.png`,description:id.includes('dragon')?'Animierte Bildvorschau mit ruhiger Flügelbewegung. Kein Angriff.':id==='magdar'?'Animierte Bildvorschau mit Hammerschwung und Atembewegung. Kein Angriff.':'Animierte Bildvorschau mit deutlicher Atembewegung. Kein Angriff.'}));
const hammerWielder=items.find(item=>item.id==='magdar');
hammerWielder.w=hammerWielder.h=219;
let allies=[],treeImages=[],visibleTrees=[],animationTime=0,timer=null;
const motion=matchMedia('(prefers-reduced-motion: reduce)');let animate=!motion.matches;
const background=document.createElement('canvas');let backgroundKey='';
function objects(){return [...items,...allies];}
function bounds(){return {left:camera.x-width/2/camera.zoom,top:camera.y-height/2/camera.zoom,right:camera.x+width/2/camera.zoom,bottom:camera.y+height/2/camera.zoom};}
function visible(i){const b=bounds();return i.x+i.w/2>b.left&&i.x-i.w/2<b.right&&i.y>b.top&&i.y-i.h<b.bottom;}
function syncAnimation(){clearInterval(timer);timer=null;document.querySelector('#animation').textContent=animate?'Animation pausieren':'Animation starten';document.querySelector('#animation').setAttribute('aria-pressed',String(animate));if(animate&&!document.hidden)timer=setInterval(()=>{if(ready&&objects().some(visible)){animationTime+=1/30;invalidate();}},1000/30);invalidate();}
document.addEventListener('visibilitychange',syncAnimation);motion.addEventListener('change',()=>{animate=!motion.matches;syncAnimation();});
document.querySelector('#animation').onclick=()=>{animate=!animate;syncAnimation();};
document.querySelector('#alliance').onclick=()=>{allies=allies.length?[]:[1,2].map(n=>({...items[0],id:'ally-'+n,name:'Allianzburg '+n,x:items[0].x-n*245}));backgroundKey='';document.querySelector('#alliance').setAttribute('aria-pressed',String(!!allies.length));invalidate();};
document.querySelector('#objects').replaceChildren(...items.map(item=>{const button=document.createElement('button');button.dataset.object=item.id;button.textContent=item.name;return button;}));
let width=0,height=0,selected=null,ready=false,pending=false,frames=0,terrain;
const tileSize=768,tileWidth=640,tileHeight=640,overlap=128;
const terrainFiles=['world-tile-0.png','world-tile-1.png'];
function tileIndex(x,y){let h=Math.imul(x,374761393)^Math.imul(y,668265263)^1979;h=Math.imul(h^(h>>>13),1274126177);return (h^(h>>>16))>>>0;}
function variantAt(x,y){return tileIndex(x,y)%terrainFiles.length;}
// Cache feathered edges once. Neighbouring tiles overlap without rotating scenery.
function prepareTerrain(image){const tile=document.createElement('canvas');tile.width=tileSize;tile.height=tileSize;const g=tile.getContext('2d');g.drawImage(image,0,0,tileSize,tileSize);g.globalCompositeOperation='destination-in';for(const vertical of [false,true]){const fade=g.createLinearGradient(0,0,vertical?0:overlap,vertical?overlap:0);for(let n=0;n<=16;n++){const t=n/16;fade.addColorStop(t,`rgba(0,0,0,${t*t*(3-2*t)})`);}g.fillStyle=fade;g.fillRect(0,0,tileSize,tileSize);}return tile;}
function groundEllipse(x,y,rx,ry,color){ctx.save();ctx.translate(x,y);ctx.scale(rx,ry);const gradient=ctx.createRadialGradient(0,0,0,0,0,1);gradient.addColorStop(0,color);gradient.addColorStop(.45,color);gradient.addColorStop(1,'rgba(0,0,0,0)');ctx.fillStyle=gradient;ctx.fillRect(-1,-1,2,2);ctx.restore();}
function drawObject(item){
 // Contact points account for transparent padding in the existing sprite files.
 const base=item.y-item.h*(item.monster?(item.groundPadding??.11):item.id==='castle'||item.id.startsWith('ally-')?.07:item.id==='gold'?.22:.17);
 const rx=item.w*(item.monster?.27:.36);
 if(!item.monster)groundEllipse(item.x,base-4,rx*1.35,rx*.5,'rgba(190,176,105,.28)');
 groundEllipse(item.x+5,base-3,rx,rx*.28,'rgba(59,73,37,.22)');
 if(selected===item.id){ctx.beginPath();ctx.ellipse(item.x,base,rx+6,rx*.3,0,0,Math.PI*2);ctx.strokeStyle=accent;ctx.lineWidth=3/camera.zoom;ctx.stroke();}
 const t=animationTime+item.x*.013,breath=item.monster&&!motion.matches&&!item.motionFrames?Math.sin(t*(item.id==='golem'?1.4:2.2))*.035:0;
 ctx.save();ctx.translate(item.x,base);ctx.scale(1-breath*.35,1+breath);ctx.filter=item.id==='orc'?'saturate(0.82)':'none';ctx.drawImage(motionFrame(item,t,motion.matches),-item.w/2,item.y-item.h-base,item.w,item.h);ctx.restore();
 if(item.id==='castle'||item.id.startsWith('ally-')){
  const glow=motion.matches?.12:.19+.08*Math.sin(t*3.2)+.035*Math.sin(t*7.1);
  for(const [x,y] of [[.36,.41],[.50,.41],[.62,.445],[.23,.575],[.685,.623]])groundEllipse(item.x+(x-.5)*item.w,item.y+(y-1)*item.h,item.w*.016,item.h*.027,`rgba(255,212,105,${glow})`);
 }
 if(!item.monster&&item.id!=='castle'&&!item.id.startsWith('ally-')){ctx.save();
  for(let n=0;n<3;n++){const phase=(t*.45+n/3)%1,x=item.x+Math.sin(n*5)*item.w*.14,y=base-item.h*.27-phase*20;ctx.globalAlpha=Math.sin(phase*Math.PI)*.65;ctx.fillStyle=item.id==='crystal'?'#b2efff':item.id==='gold'?'#ffe79e':'#d6c798';ctx.beginPath();ctx.arc(x,y,item.id==='crystal'?2.5:2+n*.4,0,Math.PI*2);ctx.fill();}
 ctx.restore();}
}
const pointers=new Map();let gesture=null,moved=false;
const css=getComputedStyle(document.body),accent=css.getPropertyValue('--ui-primary').trim();
const load=src=>new Promise((resolve,reject)=>{const img=new Image();img.onload=()=>resolve(img);img.onerror=()=>reject(new Error('Grafik konnte nicht geladen werden: '+src));img.src=src;});
function invalidate(){if(!pending){pending=true;requestAnimationFrame(()=>{pending=false;draw();});}}
function resize(){const r=canvas.getBoundingClientRect();width=r.width;height=r.height;const dpr=Math.min(devicePixelRatio||1,2);canvas.width=Math.round(width*dpr);canvas.height=Math.round(height*dpr);ctx.setTransform(dpr,0,0,dpr,0,0);invalidate();}
function world(p){return{x:camera.x+(p.x-width/2)/camera.zoom,y:camera.y+(p.y-height/2)/camera.zoom};}
function draw(){frames++;ctx.clearRect(0,0,width,height);if(!ready)return;
 const key=[camera.x,camera.y,camera.zoom,canvas.width,canvas.height,allies.length].join(':');
 if(backgroundKey===key){ctx.save();ctx.setTransform(1,0,0,1,0,0);ctx.drawImage(background,0,0);ctx.restore();}
 ctx.save();ctx.translate(width/2,height/2);ctx.scale(camera.zoom,camera.zoom);ctx.translate(-camera.x,-camera.y);
 if(backgroundKey!==key){
 const tw=tileWidth,th=tileHeight,left=camera.x-width/2/camera.zoom,top=camera.y-height/2/camera.zoom;
 for(let y=Math.floor(top/th)-1;y<=Math.floor((top+height/camera.zoom)/th);y++)for(let x=Math.floor(left/tw)-1;x<=Math.floor((left+width/camera.zoom)/tw);x++){
  ctx.drawImage(terrain[variantAt(x,y)],x*tw,y*th,tileSize,tileSize);
 }
 visibleTrees=treesIn(bounds(),objects());
 for(const tree of visibleTrees.sort((a,b)=>a.y-b.y))ctx.drawImage(treeImages[tree.kind],tree.x-tree.w/2,tree.y-tree.h,tree.w,tree.h);
 background.width=canvas.width;background.height=canvas.height;background.getContext('2d').drawImage(canvas,0,0);backgroundKey=key;
 }
 for(const item of objects().filter(visible).sort((a,b)=>a.y-b.y))drawObject(item);
 ctx.restore();document.querySelector('#scale').textContent=Math.round(camera.zoom*100)+' %';}
function zoom(next,p={x:width/2,y:height/2}){const before=world(p);camera.zoom=Math.max(limits.min,Math.min(limits.max,next));camera.x=before.x-(p.x-width/2)/camera.zoom;camera.y=before.y-(p.y-height/2)/camera.zoom;invalidate();}
function select(id,focus=false){selected=id;const item=objects().find(i=>i.id===id);document.querySelector('#detail').hidden=!item;if(item){document.querySelector('#name').textContent=item.name;document.querySelector('#description').textContent=item.description;if(focus){camera.x=item.x;camera.y=item.y-100;camera.zoom=Math.max(.8,camera.zoom);}}document.querySelectorAll('[data-object]').forEach(b=>b.setAttribute('aria-pressed',String(b.dataset.object===id)));invalidate();}
function reset(){camera.x=730;camera.y=800;camera.zoom=Math.max(limits.min,Math.min(1,width/1350,height/1330));select(null);invalidate();}
function point(e){const r=canvas.getBoundingClientRect();return{x:e.clientX-r.left,y:e.clientY-r.top};}
function baseline(){const a=[...pointers.values()];gesture={points:a.map(p=>({...p})),camera:{...camera}};}
canvas.addEventListener('pointerdown',e=>{if(e.button!==0)return;canvas.setPointerCapture(e.pointerId);if(!pointers.size)moved=false;pointers.set(e.pointerId,point(e));if(pointers.size>1)moved=true;baseline();});
canvas.addEventListener('pointermove',e=>{if(!pointers.has(e.pointerId))return;pointers.set(e.pointerId,point(e));const a=[...pointers.values()],b=gesture.points,c=gesture.camera;if(a.length===1){const dx=a[0].x-b[0].x,dy=a[0].y-b[0].y;if(Math.hypot(dx,dy)>6)moved=true;camera.x=c.x-dx/c.zoom;camera.y=c.y-dy/c.zoom;}else{const midpoint=pts=>({x:(pts[0].x+pts[1].x)/2,y:(pts[0].y+pts[1].y)/2}),dist=pts=>Math.hypot(pts[0].x-pts[1].x,pts[0].y-pts[1].y);const start=midpoint(b),now=midpoint(a);camera.zoom=Math.max(limits.min,Math.min(limits.max,c.zoom*dist(a)/Math.max(1,dist(b))));camera.x=c.x+(start.x-width/2)/c.zoom-(now.x-width/2)/camera.zoom;camera.y=c.y+(start.y-height/2)/c.zoom-(now.y-height/2)/camera.zoom;}invalidate();});
function end(e){if(!pointers.has(e.pointerId))return;const tap=e.type==='pointerup'&&!moved&&pointers.size===1,p=world(point(e));pointers.delete(e.pointerId);if(tap){const hit=objects().sort((a,b)=>b.y-a.y).find(i=>Math.abs(p.x-i.x)<=Math.max(i.w/2,22/camera.zoom)&&Math.abs(p.y-(i.y-i.h/2))<=Math.max(i.h/2,22/camera.zoom));select(hit?.id??null);}if(pointers.size)baseline();else gesture=null;}
canvas.addEventListener('pointerup',end);canvas.addEventListener('pointercancel',end);canvas.addEventListener('lostpointercapture',end);
window.addEventListener('blur',()=>{pointers.clear();gesture=null;});
canvas.addEventListener('wheel',e=>{e.preventDefault();zoom(camera.zoom*Math.exp(-e.deltaY*.0015),point(e));},{passive:false});
document.querySelector('#in').onclick=()=>zoom(camera.zoom*1.2);document.querySelector('#out').onclick=()=>zoom(camera.zoom/1.2);document.querySelector('#reset').onclick=reset;document.querySelector('#close').onclick=()=>select(null);
document.querySelectorAll('[data-object]').forEach(b=>b.onclick=()=>select(b.dataset.object,true));
document.addEventListener('keydown',e=>{if(e.key==='Escape')select(null);if(e.target!==canvas)return;const delta={ArrowLeft:[-80,0],ArrowRight:[80,0],ArrowUp:[0,-80],ArrowDown:[0,80]}[e.key];if(delta){e.preventDefault();camera.x+=delta[0]/camera.zoom;camera.y+=delta[1]/camera.zoom;invalidate();}if(e.key==='+'||e.key==='=')zoom(camera.zoom*1.2);if(e.key==='-')zoom(camera.zoom/1.2);});
new ResizeObserver(resize).observe(canvas);
Promise.all([Promise.all(terrainFiles.map(name=>load('../art/fantasy-village-v1/'+name).then(prepareTerrain))),...items.map(async i=>{i.image=await load(i.src);matchDragonSize(i);i.motionFrames=await buildMotionFrames(i.image,i.id);}),Promise.all(['oak','pine'].map(type=>load(`../art/fantasy-village-v1/world-tree-${type}-v7.png`))).then(images=>{treeImages=images;})]).then(([ground])=>{terrain=ground;ready=true;document.querySelector('#loading').hidden=true;resize();reset();syncAnimation();}).catch(error=>{document.querySelector('#loading').textContent=error.message;});
window.worldPreview={variantAt,state:()=>({ready,selected,camera:{...camera},frames,animate,animationTime,treeCount:visibleTrees.length,treeOverlaps:visibleTrees.filter(t=>objects().some(o=>overlaps(t,o))).length,allies:allies.length,terrainVariants:terrain?.length||0,objects:items.map(({id,x,y,w,h})=>({id,x,y,w,h})),width,height})};

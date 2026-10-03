import {buildMotionFrames,motionFrame,matchDragonSize} from './world-sprite-motion.js?v=2';
import {workLayers,drawWork} from './world-resource-motion.js?v=1';

// Presentation only: never changes server coordinates, footprints or water rules.
const artRoot='fantasy-village-v1/';
const files={castle:'world-castle-v2.png',gold:'world-gold-v2.png',farm:'world-farm-v8.png',lumber:'world-lumber-v8.png',quarry:'world-quarry-v8.png',crystal:'world-crystal-v8.png'};
const monsterIds=new Set(['orc','skeleton','golem','treasure-goblin','green-dragon','red-dragon','gold-dragon','magdar']);
const cache=new Map();let tiles=[],trees=[],base='',invalidate=()=>{},pauseMotionBuild=()=>false;
const hitBoundsCache=new Map(),hitBindings=new WeakMap();
const drawBindings=new WeakMap();
const hash=(x,y)=>{let h=Math.imul(x,374761393)^Math.imul(y,668265263)^1979;h=Math.imul(h^(h>>>13),1274126177);return(h^(h>>>16))>>>0;};
const load=src=>new Promise((resolve,reject)=>{const image=new Image();image.onload=()=>resolve(image);image.onerror=()=>reject(new Error('World artwork unavailable: '+src));image.src=src;});
function alphaBounds(image){
 const width=image.naturalWidth,height=image.naturalHeight,c=document.createElement('canvas');c.width=width;c.height=height;
 const g=c.getContext('2d',{willReadFrequently:true});g.drawImage(image,0,0);
 const pixels=g.getImageData(0,0,width,height).data;let left=width,top=height,right=0,bottom=0;
 // Include even faint edge pixels; only entirely transparent margins may go.
 for(let y=0;y<height;y++)for(let x=0;x<width;x++)if(pixels[(y*width+x)*4+3]>0){left=Math.min(left,x);top=Math.min(top,y);right=Math.max(right,x+1);bottom=Math.max(bottom,y+1);}
 return right>left&&bottom>top?{width,height,left,top,right,bottom}:{width,height,left:width/2,right:width/2,top:height/2,bottom:height/2};
}
function bindHitBounds(button,target,img){
 let binding=hitBindings.get(img);
 if(!binding){
  binding={button,enabled:false,src:null};hitBindings.set(img,binding);
  img.addEventListener('load',()=>updateHitBounds(img,binding));
 }
 binding.enabled=target.kind==='monsters'||target.kind==='nodes';
 updateHitBounds(img,binding);
}
function updateHitBounds(img,binding){
 const {button}=binding,src=binding.enabled?img.getAttribute('src'):null;
 if(src===binding.src)return;binding.src=src;
 button.style.removeProperty('--sprite-hit-clip');delete button.dataset.spriteHitBounds;
 if(!src)return;
 button.dataset.spriteHitBounds='pending';
 if(!hitBoundsCache.has(src))hitBoundsCache.set(src,load(src).then(alphaBounds).catch(()=>null));
 hitBoundsCache.get(src).then(bounds=>{
  if(!binding.enabled||binding.src!==src||img.getAttribute('src')!==src)return;
  delete button.dataset.spriteHitBounds;if(!bounds)return;
  // object-fit:contain may letterbox a square portrait inside a taller box.
  const style=getComputedStyle(img),width=parseFloat(style.width)||bounds.width,height=parseFloat(style.height)||bounds.height;
  const ratio=Math.min(width/bounds.width,height/bounds.height),x=(width-bounds.width*ratio)/2,y=(height-bounds.height*ratio)/2;
  const inset=[(y+bounds.top*ratio)/height,(width-x-bounds.right*ratio)/width,(height-y-bounds.bottom*ratio)/height,(x+bounds.left*ratio)/width];
  // Keep this on the button: draw() copies img inline geometry to the canvas.
  button.style.setProperty('--sprite-hit-clip',`inset(${inset.map(n=>`${Math.max(0,n)*100}%`).join(' ')})`);
  button.dataset.spriteHitBounds='ready';
 });
}
export function key(target){
 if(['home','players'].includes(target.kind))return window.ConquerCastleSkins.get(target.data.city_skin).id==='default'?'castle':null;
 if(target.kind==='nodes')return files[target.resource?.art]?target.resource.art:null;
 if(target.kind!=='monsters')return null;
 const name=(target.art||'').split('/').pop().replace(/\.png.*$/,'').replace('-turquoise','');
 // Regional bosses and special skins keep their own identity and animation.
 return monsterIds.has(name)?name:null;
}
export function source(target){const id=key(target);return id?`${base}/assets/art/${files[id]?artRoot+files[id]:'monsters/2.5d/bright-v2/'+id+'.png'}`:null;}
function feather(image){
 const c=document.createElement('canvas');c.width=c.height=768;const g=c.getContext('2d');g.drawImage(image,0,0,768,768);g.globalCompositeOperation='destination-in';
 for(const vertical of [false,true]){const fade=g.createLinearGradient(0,0,vertical?0:128,vertical?128:0);for(let n=0;n<=16;n++){const t=n/16;fade.addColorStop(t,`rgba(0,0,0,${t*t*(3-2*t)})`);}g.fillStyle=fade;g.fillRect(0,0,768,768);}return c;
}
export async function init(root,onReady,shouldPauseMotion=()=>false){
 base=root;invalidate=onReady;pauseMotionBuild=shouldPauseMotion;
 const results=await Promise.allSettled([
  Promise.all([0,1].map(n=>load(`${base}/assets/art/${artRoot}world-tile-${n}.png`))).then(images=>{tiles=images.map(feather);}),
  Promise.all(['oak','pine'].map(n=>load(`${base}/assets/art/${artRoot}world-tree-${n}-v7.png`))).then(images=>{trees=images;})
 ]);
 for(const result of results)if(result.status==='rejected')console.warn(result.reason);
 invalidate();
}
export function ground(c,project,s,bounds){
 if(!tiles.length)return false;
 const span=9,size=span*768/640;
 // 640 px spacing + 128 px feather, always upright; world coordinates fix the hash.
 for(let y=Math.floor(bounds.top/span)-1;y<=Math.floor(bounds.bottom/span);y++)for(let x=Math.floor(bounds.left/span)-1;x<=Math.floor(bounds.right/span);x++){
  const [px,py]=project(x*span,y*span);c.drawImage(tiles[hash(x,y)%2],px,py,size*s,size*s);
 }return true;
}
export function decorations(bounds,occupied){
 const result=[],span=3.1;
 for(let gy=Math.floor(bounds.top/span)-1;gy<=Math.ceil(bounds.bottom/span)+1;gy++)for(let gx=Math.floor(bounds.left/span)-1;gx<=Math.ceil(bounds.right/span)+1;gx++){
  const seed=hash(gx,gy);if(seed%100>48)continue;
  for(let n=0;n<(seed%7===0?3:1);n++){
   const size=(100+(seed>>>10)%36)/71,x=gx*span+(35+(seed>>>8)%125+n*44)/71,y=gy*span+(40+(seed>>>17)%110+n%2*28)/71;
   if(x<0||y<0||x>(bounds.worldWidth||256)-1||y>(bounds.worldHeight||256)-1||occupied(x,y,size*.6)||occupied(x,y-size*.65,size*.6))continue;
   result.push({x,y,size,kind:(seed+n)%2});
  }
 }return result.sort((a,b)=>a.y-b.y);
}
export function scenery(c,project,s,bounds,occupied){
 if(!trees.length)return false;
 for(const tree of decorations(bounds,occupied)){const [x,y]=project(tree.x,tree.y),size=tree.size*s;c.drawImage(trees[tree.kind],x-size/2,y-size*.91,size,size);}return true;
}
function itemFor(target){
 const id=key(target);if(!id)return null;if(cache.has(id))return cache.get(id);
 // Compensate the orc's transparent padding to match skeleton/golem silhouettes.
 const item={id,w:id==='castle'?250:files[id]?205:id==='magdar'?219:id==='orc'?145:140,h:140,groundPadding:id==='castle'?.07:id==='gold'?.22:files[id]?.17:.11};
 item.h=item.w;cache.set(id,item);
 load(source(target)).then(image=>{item.image=image;matchDragonSize(item);invalidate();}).catch(error=>{item.failed=true;console.warn(error);});return item;
}
// One visual hierarchy; authoritative footprints and coordinates remain separate.
const mapSize=(item,target)=>item.id==='castle'?3.65:files[item.id]?1.75:item.id==='magdar'?2.6:item.id.includes('dragon')?item.w/70*(target.data.definition?.type==='rally'?.9:.65):item.id==='orc'?1.4:1.35;
export function bind(button,target){
 const id=key(target),img=button.querySelector('img');
 if(!id){if(button.dataset.painted){delete button.dataset.painted;img.style.cssText='';button.querySelector('.atlas-painted-motion')?.remove();}bindHitBounds(button,target,img);return;}
 button.dataset.painted=id;const item=itemFor(target),size=mapSize(item,target);
 button.style.setProperty('--painted-size',String(size));button.style.setProperty('--painted-padding',String(item.groundPadding));
 // Preserve img geometry for the target-menu placement calculations.
 img.style.width=img.style.height=`calc(var(--tile-size,44px) * ${size})`;
 img.style.marginLeft=`calc(var(--tile-size,44px) * ${-size/2})`;
 img.style.bottom=`calc(var(--tile-size,44px) * ${-size*item.groundPadding})`;
 bindHitBounds(button,target,img);
}
function ellipse(g,x,y,rx,ry,color){g.save();g.translate(x,y);g.scale(rx,ry);const fade=g.createRadialGradient(0,0,0,0,0,1);fade.addColorStop(0,color);fade.addColorStop(.45,color);fade.addColorStop(1,'transparent');g.fillStyle=fade;g.fillRect(-1,-1,2,2);g.restore();}
export function draw(button,target,time,reduced){
 const item=itemFor(target);if(!item?.image||button.hidden)return;
 const working=target.kind==='nodes'&&Boolean(target.data.gatherer_march_id);
 const layers=working?workLayers(item.id,base,invalidate):null;
 if(!reduced&&(!files[item.id]||item.id==='castle')&&!item.framesRequested){item.framesRequested=true;buildMotionFrames(item.image,item.id,{shouldPause:()=>pauseMotionBuild()}).then(frames=>{item.motionFrames=frames;invalidate();}).catch(error=>console.warn(error));}
 let binding=drawBindings.get(button);
 if(!binding||!binding.canvas.isConnected){
  const img=button.querySelector('img'),canvas=document.createElement('canvas');canvas.className='atlas-painted-motion';canvas.setAttribute('aria-hidden','true');img.after(canvas);
  binding={img,canvas,ctx:canvas.getContext('2d'),geometry:null};drawBindings.set(button,binding);
 }
 const {canvas,img,ctx:g}=binding;
 // Small encounters need small backing stores, rather than 192px for every sprite.
 const resolution=Math.min(192,Math.max(80,Math.ceil(mapSize(item,target)*(button.mapTileSize||44))));
 if(canvas.width!==resolution){canvas.width=canvas.height=resolution;canvas.paintStamp=null;}
 if(img.style.opacity!=='0')img.style.opacity='0';
 const geometry=img.style.cssText;
 if(binding.geometry!==geometry){binding.geometry=geometry;canvas.style.cssText=geometry;canvas.style.opacity='1';}
 const animated=!reduced&&(!files[item.id]||item.id==='castle'||working);
 const stamp=`${item.id}:${working}:${layers?.ready}:${!!item.motionFrames}:${animated?Math.floor(time*24):'still'}`;if(canvas.paintStamp===stamp)return;canvas.paintStamp=stamp;
 const workingState=String(working),readyState=String(working&&(item.id==='crystal'||!!layers?.ready));
 if(canvas.dataset.working!==workingState)canvas.dataset.working=workingState;
 if(canvas.dataset.workReady!==readyState)canvas.dataset.workReady=readyState;
 g.setTransform(resolution/192,0,0,resolution/192,0,0);
 const id=item.id,t=reduced?0:time+(Number(target.id)||0)*.013;
 g.clearRect(0,0,192,192);const breath=!files[id]&&!item.motionFrames&&!reduced?Math.sin(t*(id==='golem'?1.4:2.2))*.035:0;
 g.save();const foot=192*(1-item.groundPadding);g.translate(96,foot);g.scale(1-breath*.35,1+breath);g.drawImage(motionFrame(item,t,reduced),-96,-foot,192,192);g.restore();
 if(working)drawWork(g,id,layers,item.image,t);
 if(id==='castle')for(const [x,y] of [[.36,.41],[.50,.41],[.62,.445],[.23,.575],[.685,.623]])ellipse(g,x*192,y*192,3,5,`rgba(255,212,105,${reduced?.12:.19+.08*Math.sin(t*3.2)})`);
}
export function shadow(c,x,y,s,target,tiles){
 const id=key(target);if(!id)return false;
 const item=itemFor(target),size=mapSize(item,target);
 const foot=y+tiles*s/2;ellipse(c,x+2,foot-2,size*s*.3,size*s*.08,'rgba(59,73,37,.22)');return true;
}
export function visible(target,x,y,tile,tiles,width,height){
 const item=itemFor(target);if(!item)return false;
 const size=mapSize(item,target)*tile,bottom=y+tiles*tile/2+size*item.groundPadding;
 return x+size/2>=0&&x-size/2<=width&&bottom>=0&&bottom-size<=height;
}

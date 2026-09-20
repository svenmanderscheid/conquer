import * as T from './vendor/three.module.js';
import { buildFortress } from './fortress-cartoon.js?v=dragonsteel7';
import { buildTestCity } from './city.js';
import { buildVillageLandscape } from './village-landscape.js?v=plots1';
import { buildDistricts } from './full-city.js?v=finished-village5';
import { buildStorybookCore } from './storybook-core.js?v=finished-village5';
import { buildStorybookMill } from './storybook-mill.js?v=finished-village5';
import { buildCityLife } from './city-life.js?v=finished-village5';
import { enrichVillage } from './village-details.js?v=fantasy-village1';
import {placeVillageBuilding,villageOverview,villageBuildings,villageBoundary,villageTerrace,villageRoads,villageLandmarks,villageBuildingHeightScale,villageElevationAt} from './village-layout.js?v=finished-village5';
import { storybookMaterials as storyM,addStorybookOutline,createVillageGroundMaterial } from './storybook-style.js?v=fantasy-village1';
const start=performance.now(), host=document.querySelector('#world'), loading=document.querySelector('#loading');
const mobileGraphics=matchMedia('(pointer: coarse)').matches;
let renderer;
try{
 renderer=new T.WebGLRenderer({antialias:true,alpha:false,powerPreference:mobileGraphics?'low-power':'default'});
}catch(primaryError){
 try{renderer=new T.WebGLRenderer({antialias:false,alpha:false,powerPreference:'low-power'});}
 catch(fallbackError){loading.textContent='Chrome hat die 3D-Grafik vorübergehend blockiert. Bitte diesen Tab schließen, erneut öffnen und die Seite neu laden.';throw new AggregateError([primaryError,fallbackError],'WebGL context creation failed');}
}
// Large desktop displays otherwise allocate a very large colour, depth and
// shadow buffer at once. The restrained cap keeps integrated GPUs stable while
// preserving the illustrated look; smaller screens may retain a little more AA.
const pixelRatioLimit=mobileGraphics?1.2:innerWidth>=1600?1.2:1.45;
renderer.setPixelRatio(Math.min(devicePixelRatio,pixelRatioLimit));renderer.shadowMap.enabled=true;renderer.shadowMap.type=T.PCFSoftShadowMap;renderer.outputColorSpace=T.SRGBColorSpace;renderer.toneMapping=T.ACESFilmicToneMapping;renderer.toneMappingExposure=1.12;host.appendChild(renderer.domElement);
const scene=new T.Scene();scene.background=new T.Color('#d4ddcc');scene.fog=new T.Fog('#d4ddcc',180,320);
const camera=new T.OrthographicCamera(-10,10,10,-10,.1,400),target=new T.Vector3(0,.7,0),offset=new T.Vector3(42,54,66);let zoom=1;
scene.add(new T.HemisphereLight('#e8f5ff','#736943',2.55));
const sun=new T.DirectionalLight('#fff1cb',2.55);sun.position.set(-12,20,8);sun.castShadow=true;sun.shadow.mapSize.set(1024,1024);Object.assign(sun.shadow.camera,{left:-27,right:27,top:27,bottom:-27,near:.5,far:65});sun.shadow.bias=-.0004;sun.shadow.normalBias=.035;scene.add(sun);
const mats={};for(const [n,c] of Object.entries({stone:'#d6bd91',stone2:'#b99f7c',stone3:'#e7cca0',wood:'#563727',wood2:'#875537',copper:'#bd643a',copper2:'#df8752',teal:'#216664',bronze:'#d79c4c',grass:'#859b57',earth:'#9d7853',path:'#d1b384',leaf:'#426949',leaf2:'#69874b',dark:'#302b27',water:'#51a0a3',wheat:'#d8ab50'}))mats[n]=new T.MeshStandardMaterial({color:c,roughness:n==='bronze'?.45:.9,metalness:n==='bronze'?.28:0,flatShading:true});
const geoCache=new Map();function boxGeo(w,h,d){const key=[w,h,d].join(',');if(!geoCache.has(key))geoCache.set(key,new T.BoxGeometry(w,h,d));return geoCache.get(key);}
function mesh(g,mat,parent,x=0,y=0,z=0){const o=new T.Mesh(g,typeof mat==='string'?mats[mat]:mat);o.position.set(x,y,z);o.castShadow=true;o.receiveShadow=true;parent.add(o);return o;}
function box(parent,w,h,d,x,y,z,mat='stone'){return mesh(boxGeo(w,h,d),mat,parent,x,y,z);}
function cyl(parent,r,h,x,y,z,mat='wood',seg=10){return mesh(new T.CylinderGeometry(r,r,h,seg),mat,parent,x,y,z);}
function group(x,y,z,parent=scene){const g=new T.Group();g.position.set(x,y,z);parent.add(g);return g;}
let seed=343;function rand(){seed=(seed*1664525+1013904223)>>>0;return seed/4294967296;}
function emblem(parent,x,y,z,r=.25){const o=mesh(new T.TorusGeometry(r,.047,5,22,Math.PI*1.7),'bronze',parent,x,y,z);o.rotation.z=.45;return o;}
function roof(parent,w,d,base,height,x=0,z=0){const sh=new T.Shape();sh.moveTo(-w/2,0);sh.lineTo(0,height);sh.lineTo(w/2,0);sh.closePath();const g=new T.ExtrudeGeometry(sh,{depth:d,bevelEnabled:false});g.translate(0,0,-d/2);mesh(g,'copper',parent,x,base,z);const angle=Math.atan2(height,w/2),length=Math.hypot(height,w/2);for(const side of [-1,1]){for(let i=0;i<5;i++){const f=(i+.5)/5;const strip=box(parent,length/5*.97,.055,d+.07,x+side*w/2*f,base+height*(1-f)+.04,z,i%2?'copper':'copper2');strip.rotation.z=-side*angle;}for(let i=0;i<5;i++){const rib=box(parent,length,.09,.09,x+side*w/4,base+height/2+.08,z-d/2+i*d/4,'wood2');rib.rotation.z=-side*angle;}}box(parent,.18,.18,d+.22,x,base+height+.08,z,'wood');}
function stoneWall(parent,w,h,d,x,y,z){box(parent,w,h,d,x,y+h/2,z,'stone2');for(let row=0;row<Math.floor(h/.36);row++){for(let col=0;col<Math.floor(w/.47);col++){box(parent,.435,.31,.065,x-w/2+.25+col*.47+(row%2)*.06,y+.19+row*.36,z+d/2+.025,['stone','stone3','stone2'][Math.floor(rand()*3)]);}}}
const flags=[];function flag(parent,x,y,z,w=.65,h=1){cyl(parent,.038,h+.45,x,y+h/2,z,'wood');const g=new T.PlaneGeometry(w,h,10,7);g.translate(w/2,-h/2,0);const mat=mats.teal.clone();mat.side=T.DoubleSide;const f=mesh(g,mat,parent,x,y+h,z);f.castShadow=false;flags.push({mesh:f,original:g.attributes.position.array.slice()});const mark=emblem(parent,x+w*.45,y+h*.58,z+.035,.16);return f;}
// Island, roads and water channel.
const land=mesh(new T.CylinderGeometry(8.8,8.45,.65,10),'earth',scene,0,-.38,0);land.rotation.y=.13;const turf=mesh(new T.CylinderGeometry(8.72,8.72,.12,10),'grass',scene,0,.015,0);turf.rotation.y=.13;
function meadowTexture(){
  const canvas=document.createElement('canvas');canvas.width=canvas.height=512;const context=canvas.getContext('2d');let meadowSeed=27091;
  const meadowRand=()=>{meadowSeed=(meadowSeed*1664525+1013904223)>>>0;return meadowSeed/4294967296;};
  context.fillStyle='#c9d7af';context.fillRect(0,0,512,512);
  for(let i=0;i<95;i++){const x=meadowRand()*512,y=meadowRand()*512,rx=14+meadowRand()*65,ry=8+meadowRand()*28;context.globalAlpha=.035+meadowRand()*.07;context.fillStyle=i%3?'#789451':'#eef0ca';context.beginPath();context.ellipse(x,y,rx,ry,meadowRand()*Math.PI,0,Math.PI*2);context.fill();}
  context.globalAlpha=1;const texture=new T.CanvasTexture(canvas);texture.wrapS=texture.wrapT=T.RepeatWrapping;texture.repeat.set(4,4);texture.colorSpace=T.SRGBColorSpace;texture.anisotropy=4;return texture;
}
const meadow=mesh(new T.PlaneGeometry(200,200),new T.MeshStandardMaterial({map:meadowTexture(),color:'#f5f4dc',roughness:1}),scene,0,-.85,0);meadow.rotation.x=-Math.PI/2;meadow.receiveShadow=true;meadow.castShadow=false;
box(scene,2,.025,14,0,.092,0,'path');box(scene,11,.03,1.6,0,.1,1.6,'path');
const river=box(scene,1.18,.035,11,-5.2,.12,1,'water');const ripples=[];for(let i=0;i<17;i++){const r=box(scene,.3+rand()*.4,.015,.035,-5.5+rand()*.6,.15,-4+i*.58,'stone3');r.castShadow=false;r.material=new T.MeshBasicMaterial({color:'#b4ded4',transparent:true,opacity:.55});ripples.push(r);}
for(let i=0;i<22;i++)for(const side of [-1,1]){const r=mesh(new T.DodecahedronGeometry(.2+rand()*.1),'stone2',scene,-5.2+side*.72,.12,-4.6+i*.48);r.scale.set(1,.5,1);}
const keep=buildFortress({parent:scene,flag,emblem}); const gameBuildings=[],labelAnchors=[];
// Animated mill: architecture and wheel are actual separate 3D meshes.
const {root:mill,wheel}=buildStorybookMill({parent:scene});
// Bridge, mill yard and a restrained wheat patch.
for(let i=0;i<8;i++)box(scene,.2,.12,1.7,-5.94+i*.21,.29,5.3,'wood2');for(const z of [4.53,6.06])for(const x of [-5.95,-4.48])box(scene,.09,.66,.09,x,.57,z,'wood');
for(let x=0;x<8;x++)for(let z=0;z<4;z++){const a=-2.7+x*.24,b=4.3+z*.27;const stalk=cyl(scene,.035,.43,a,.36,b,'wheat',5);mesh(new T.ConeGeometry(.085,.23,5),'wheat',scene,a,.62,b);}
// Crooked trunks and off-centre, painted crowns echo the illustrated village.
const treeTrunkGeo=new T.TubeGeometry(new T.CatmullRomCurve3([
  new T.Vector3(0,0,0),new T.Vector3(.07,.34,-.02),
  new T.Vector3(-.06,.68,.04),new T.Vector3(.04,1.03,0)
]),7,.105,7,false);
const crownRound=new T.DodecahedronGeometry(.62,1),crownTall=new T.SphereGeometry(.58,10,7);
const rootGeo=new T.ConeGeometry(.13,.48,5);
function tree(x,z,size,y=.12){
  const g=group(x,y,z);g.scale.setScalar(size);g.userData.landscapeTree=true;
  const twist=Math.abs(Math.round(x*7+z*11)),pine=twist%3===0;
  const trunk=mesh(treeTrunkGeo,storyM.wood,g,0,.02,0);trunk.rotation.y=(twist%7-.3)*.12;addStorybookOutline(trunk,.022);
  for(const a of [-1.05,.18,1.35]){const root=mesh(rootGeo,storyM.wood,g,Math.sin(a)*.13,.19,Math.cos(a)*.13);root.rotation.z=Math.sin(a)*1.15;root.rotation.x=Math.cos(a)*1.15;root.scale.set(.75,1,.75);}
  const crowns=pine
    ? [[-.05,1.02,.02,.78,.48,.72,storyM.leaf], [.11,1.37,-.04,.65,.45,.61,storyM.leafLight],[-.09,1.67,.03,.47,.4,.45,storyM.leaf]]
    : [[-.28,1.2,.03,.75,.67,.7,storyM.leaf],[.3,1.26,-.08,.68,.76,.66,storyM.leafLight],[.02,1.62,.04,.72,.62,.65,storyM.leaf],[-.05,1.15,.32,.5,.52,.45,storyM.leafLight]];
  crowns.forEach(([cx,cy,cz,sx,sy,sz,material],index)=>{const crown=mesh(pine?crownTall:crownRound,material,g,cx,cy,cz);crown.scale.set(sx,sy,sz);crown.rotation.set((index%2-.5)*.12,(twist+index)*.37,(index-1)*.08);if(index===0)addStorybookOutline(crown,.035);});
  if(!pine&&twist%4===0)for(const [fx,fy,fz] of [[-.4,1.35,.42],[.34,1.55,.29]])mesh(new T.SphereGeometry(.065,7,5),storyM.red,g,fx,fy,fz);
}
for(const [x,z,s] of [[-6,-3,1.3],[-3.8,-5.8,1.4],[.3,-6.3,1.1],[4.9,-4.6,1.4],[6.4,-2,1],[6,2.6,1.25],[3.7,5.6,.9],[-1,6.8,.8],[-7,1.9,.75]])tree(x,z,s);
for(let i=0;i<46;i++){const a=rand()*Math.PI*2,r=6.5+rand()*1.5,x=Math.cos(a)*r,z=Math.sin(a)*r;const rock=mesh(new T.DodecahedronGeometry(.13+rand()*.28),i%3?'stone2':'stone',scene,x,.14,z);rock.scale.set(1,.65+rand(),.8);rock.rotation.set(rand(),rand(),rand());}
// Repeated paving slabs give the square a human scale.
for(let i=0;i<30;i++)box(scene,.34,.025,.28,-.6+(i%3)*.43,.13,-4+Math.floor(i/3)*.75,'stone2');
const smoke=[];for(let i=0;i<8;i++){const mat=new T.MeshStandardMaterial({color:'#e5dfc9',transparent:true,opacity:.3,depthWrite:false,flatShading:true});const p=mesh(new T.IcosahedronGeometry(.16,0),mat,mill,.7,3.9+i*.18,-.5);p.castShadow=false;smoke.push(p);}
const testCity=buildTestCity({scene,box,mesh,roof,flag,mats});
let cityMode=true,overview=true;
const selection=mesh(new T.RingGeometry(2.2,2.26,64),new T.MeshBasicMaterial({color:'#e9bb67',side:T.DoubleSide,transparent:true,opacity:.9}),scene,1.5,.17,-2.45);selection.rotation.x=-Math.PI/2;selection.visible=false;
const buildingData={keep:{title:'Festung des Grenzlands',text:'Runde Türme, geschwungene blaue Dächer und warme Fenster. Die neue Cartoon-Festung bleibt echtes 3D – mit weichen Formen und dunklen Konturen.',position:[1.5,-2.45]},mill:{title:'Die Wassermühle',text:'Das Rad dreht sich als eigenes Bauteil. Wasser, Rauch und Banner bewegen sich unabhängig voneinander.',position:[-3,2.5]}};
Object.assign(buildingData,testCity.data);
let selected=null;
function select(id,notify=true){if(!buildingData[id])return;selected=id;const d=buildingData[id];if(id==='wall'&&notify){target.set(d.position[0],.6,d.position[1]);setZoom(1);}document.querySelector('#name').textContent=d.title;document.querySelector('#description').textContent=d.text;selection.position.set(d.position[0],.17+(window.CONQUER_PLAY?villageElevationAt(d.position[0],d.position[1]):0),d.position[1]);selection.visible=true;if(notify)window.dispatchEvent(new CustomEvent('conquer-building-select',{detail:{id}}));document.querySelectorAll('[data-building]').forEach(b=>b.classList.toggle('active',b.dataset.building===id));}
document.querySelectorAll('[data-building]').forEach(b=>b.onclick=()=>select(b.dataset.building));
function resize(){
  const w=host.clientWidth,h=host.clientHeight;if(!w||!h)return;
  const aspect=w/h,fullOverview=overview&&(cityMode||window.CONQUER_PLAY||window.CONQUER_EMBED);
  // A phone explores a readable district; fitting the entire city's width makes
  // every building tiny. Landscape keeps the broad, edge-to-edge village view.
  const span=fullOverview
    ? (window.CONQUER_PLAY?Math.max(32,aspect*62):Math.max(58,aspect*34))
    : (aspect<.8?21:Math.max(23,aspect*15));
  camera.left=-span/2;camera.right=span/2;camera.top=span/aspect/2;camera.bottom=-span/aspect/2;
  camera.zoom=zoom;camera.position.copy(target).add(offset);camera.lookAt(target);camera.updateProjectionMatrix();
  renderer.setSize(w,h);
}
document.querySelector('#detail').onclick=()=>{cityMode=false;testCity.root.visible=false;document.querySelector('#cityMode').textContent='Große Stadt';overview=false;select('keep',false);target.set(1.5,1.6,-2.45);setZoom(host.clientWidth/host.clientHeight<.8?2.1:1.4);};
// The playable city's reference overview is the furthest zoom-out for every
// input method. Keep the wider range only for the separate model study.
function setZoom(n){zoom=T.MathUtils.clamp(n,window.CONQUER_PLAY?1:.28,5);resize();}
document.querySelector('#zoomIn').onclick=()=>setZoom(zoom*1.18);document.querySelector('#zoomOut').onclick=()=>setZoom(zoom/1.18);document.querySelector('#reset').onclick=()=>{overview=true;target.set(0,.7,0);setZoom(1);};
const motionPreference=matchMedia('(prefers-reduced-motion: reduce)');let paused=motionPreference.matches,appReduced=false,appVisible=true;const pauseButton=document.querySelector('#pause');function pauseUI(){pauseButton.textContent=paused?'▶':'Ⅱ';pauseButton.setAttribute('aria-pressed',String(paused));pauseButton.setAttribute('aria-label',paused?'Animation fortsetzen':'Animation pausieren');}pauseUI();pauseButton.onclick=()=>{paused=!paused;pauseUI();};
motionPreference.addEventListener('change',()=>{paused=motionPreference.matches||appReduced;pauseUI();});
window.addEventListener('message',event=>{
 if(window.parent===window||event.source!==window.parent||event.origin!==location.origin)return;
 if(event.data?.type==='conquer:preferences'&&appReduced!==Boolean(event.data.reduced_motion)){appReduced=Boolean(event.data.reduced_motion);paused=motionPreference.matches||appReduced;pauseUI();}
 if(event.data?.type==='conquer:visibility'){appVisible=event.data.visible!==false;syncFrames();}
});
document.querySelector('#cityMode').onclick=()=>{cityMode=!cityMode;testCity.root.visible=cityMode;overview=true;selection.visible=false;target.set(0,.7,0);setZoom(1);document.querySelector('#cityMode').textContent=cityMode?'Kleine Szene':'Große Stadt';document.querySelector('#name').textContent=cityMode?'Die Stadt lebt.':'Festung & Wassermühle';document.querySelector('#description').textContent=cityMode?'22 Gebäude und 60 marschierende Einheiten. Erkunde die Viertel und prüfe, ob die Bewegung flüssig bleibt.':'Zwei Gebäude zum direkten Vergleich mit der großen Stadt.';};
document.querySelector('#metricsToggle').onclick=()=>{const m=document.querySelector('#metrics');m.hidden=!m.hidden;document.querySelector('#metricsToggle').setAttribute('aria-expanded',String(!m.hidden));};
const canvas=renderer.domElement,points=new Map();let pointerStart=null,pinch=0,moved=false,suppressLabelClick=false;const raycaster=new T.Raycaster();
function pointerDown(e){e.currentTarget.setPointerCapture?.(e.pointerId);points.set(e.pointerId,{x:e.clientX,y:e.clientY});pointerStart={x:e.clientX,y:e.clientY};if(points.size===1)moved=false;if(points.size===2){const p=[...points.values()];pinch=Math.hypot(p[0].x-p[1].x,p[0].y-p[1].y);}}
function pointerMove(e){const previous=points.get(e.pointerId);if(!previous)return;points.set(e.pointerId,{x:e.clientX,y:e.clientY});if(points.size===2){const p=[...points.values()],distance=Math.hypot(p[0].x-p[1].x,p[0].y-p[1].y);if(pinch>0)setZoom(zoom*distance/pinch);pinch=distance;moved=true;e.preventDefault();return;}if(pointerStart&&Math.hypot(e.clientX-pointerStart.x,e.clientY-pointerStart.y)>5)moved=true;if(moved){const scale=(camera.right-camera.left)/zoom/host.clientWidth,dx=e.clientX-previous.x,dy=e.clientY-previous.y;target.x=T.MathUtils.clamp(target.x-dx*scale*.84-dy*scale*.5,-65,75);target.z=T.MathUtils.clamp(target.z+dx*scale*.54-dy*scale*.8,-65,99);resize();e.preventDefault();}}
function pointerUp(e){const wasSingle=points.size===1,isCanvas=e.currentTarget===canvas;points.delete(e.pointerId);if(moved&&!isCanvas){suppressLabelClick=true;e.preventDefault();}if(!moved&&wasSingle&&isCanvas){const rect=canvas.getBoundingClientRect();raycaster.setFromCamera(new T.Vector2((e.clientX-rect.left)/rect.width*2-1,-(e.clientY-rect.top)/rect.height*2+1),camera);const hits=raycaster.intersectObjects(cityMode?[keep,mill,...gameBuildings,...testCity.buildings]:[keep,mill,...gameBuildings],true).filter(hit=>{for(let object=hit.object;object;object=object.parent)if(!object.visible)return false;return true;});const hit=(window.CONQUER_PLAY&&zoom>=.65?hits.find(hit=>{let o=hit.object;while(o&&!o.userData.building)o=o.parent;return o?.userData.building!=='wall';}):null)??hits[0];if(hit){let obj=hit.object;while(obj&&!obj.userData.building)obj=obj.parent;if(obj)select(obj.userData.building);}else if(window.CONQUER_PLAY){window.dispatchEvent(new Event('conquer-map-tap'));}}if(points.size===0){pinch=0;pointerStart=null;}}
function pointerCancel(e){points.delete(e.pointerId);moved=true;pinch=0;if(points.size===0)pointerStart=null;}
function bindGestures(surface){surface.addEventListener('pointerdown',pointerDown);surface.addEventListener('pointermove',pointerMove,{passive:false});surface.addEventListener('pointerup',pointerUp);surface.addEventListener('pointercancel',pointerCancel);}
bindGestures(canvas);document.querySelectorAll('.building-name').forEach(label=>{bindGestures(label);label.addEventListener('click',e=>{if(!suppressLabelClick)return;suppressLabelClick=false;e.preventDefault();e.stopImmediatePropagation();},true);});
canvas.addEventListener('wheel',e=>{e.preventDefault();setZoom(zoom*Math.exp(-e.deltaY*.001));},{passive:false});
let graphicsSuspended=false;
canvas.addEventListener('webglcontextlost',e=>{e.preventDefault();graphicsSuspended=true;syncFrames();loading.textContent='Die Grafikverbindung wird wiederhergestellt …';loading.classList.remove('hidden');});
canvas.addEventListener('webglcontextrestored',()=>{graphicsSuspended=false;loading.classList.add('hidden');resize();syncFrames();});
window.addEventListener('resize',resize);new ResizeObserver(resize).observe(host);resize();
let prev=performance.now(),clock=0,frames=0,measure=prev,fps=0,firstFrame=null,cityLife=null,villageLandscape=null,farmRoot=null;
let sceneFrame=0;
function syncFrames(){
 if(document.hidden||!appVisible||graphicsSuspended){cancelAnimationFrame(sceneFrame);sceneFrame=0;return;}
 if(!sceneFrame){prev=performance.now();measure=prev;frames=0;sceneFrame=requestAnimationFrame(frame);}
}
document.addEventListener('visibilitychange',syncFrames);
function frame(now){sceneFrame=0;if(document.hidden||!appVisible||graphicsSuspended)return;sceneFrame=requestAnimationFrame(frame);const interval=paused?100:mobileGraphics?1000/30:1000/60;if(now-prev<interval-1)return;const dt=Math.min((now-prev)/1000,.08);prev=now;if(!paused){clock+=dt;if(cityMode)testCity.animate(clock);cityLife?.animate(clock);keep.userData.animateCastle(clock);villageLandscape?.update?.(clock);farmRoot?.userData.animateFarm?.(clock,{reducedMotion:motionPreference.matches||appReduced});wheel.rotation.z=clock*.55;for(const {mesh:f,original} of flags){const a=f.geometry.attributes.position;for(let i=0;i<a.count;i++){const x=original[i*3];a.setZ(i,Math.sin(clock*2.7+x*6+original[i*3+1]*1.5)*.09*x/.65);}a.needsUpdate=true;f.geometry.computeVertexNormals();}smoke.forEach((p,i)=>{const phase=(clock*.2+i/8)%1;p.position.set(.7+phase*.65,3.8+phase*1.5,-.5+phase*.2);p.scale.setScalar(.6+phase*1.3);p.material.opacity=.25*(1-phase);});ripples.forEach((r,i)=>{r.position.z=-4+(i*.58+clock*.6)%9.8;});}renderer.render(scene,camera);positionLabels();frames++;if(firstFrame===null){firstFrame=now-start;loading.classList.add('hidden');}if(now-measure>1000){fps=Math.round(frames*1000/(now-measure));frames=0;measure=now;const people=cityLife?.peopleCount??0;document.querySelector('#metrics').textContent=`${window.CONQUER_PLAY?Object.keys(villageBuildings).length:(cityMode?22:2)} Gebäude · ${window.CONQUER_PLAY?people:(cityMode?60:0)} Personen · ${fps} Bilder/s · ${renderer.info.render.triangles.toLocaleString('de-DE')} Dreiecke · ${renderer.info.render.calls} Zeichenaufrufe · erster Frame ${Math.round(firstFrame)} ms`;}}
syncFrames();
// Read-only diagnostics for the bounded feasibility test.
window.conquer3D={getState:()=>({framePending:Boolean(sceneFrame),frameLimit:paused?10:mobileGraphics?30:60,selected,paused,zoom,skin:keep.userData.castleSkin,skinEffect:keep.userData.castleEffectState(),assetVersion:window.CONQUER_PLAY?.assetVersion??null,layout:window.CONQUER_PLAY?villageBuildings:null,boundary:window.CONQUER_PLAY?villageBoundary:null,terrace:window.CONQUER_PLAY?villageTerrace:null,roadSurfaces:window.CONQUER_PLAY?Object.fromEntries(villageRoads.map(road=>[road.id,road.surface??'earth'])):null,landmarks:window.CONQUER_PLAY?villageLandmarks:null,buildingHeightScale:villageBuildingHeightScale,pixelRatio:renderer.getPixelRatio(),shadowMapSize:sun.shadow.mapSize.x,graphicsSuspended,viewport:{width:host.clientWidth,height:host.clientHeight,worldWidth:(camera.right-camera.left)/zoom,worldHeight:(camera.top-camera.bottom)/zoom,target:{x:target.x,y:target.y,z:target.z}},ready:firstFrame!==null,fps,cityMode,buildings:window.CONQUER_PLAY?Object.keys(villageBuildings).length:(cityMode?22:2),units:cityMode?60:0,firstFrameMs:firstFrame,triangles:renderer.info.render.triangles,drawCalls:renderer.info.render.calls,wheelAngle:wheel.rotation.z,farmLife:farmRoot?.userData.farmLife?.getState?.()??null})};








if(window.CONQUER_PLAY){
  cityMode=false;testCity.root.visible=false;overview=Boolean(window.CONQUER_EMBED);target.set(0,.6,0);setZoom(window.CONQUER_EMBED?1:(host.clientWidth/host.clientHeight<.8?1.25:1));
  const scaffold=group(0,0,0,keep);
  for(const x of [-1.05,1.05]){
    box(scaffold,.11,2.6,.11,x,1.42,2.4,'wood');
    for(const y of [.65,1.4,2.2])box(scaffold,.13,.1,.8,x,y,2.25,'wood2');
  }
  for(const y of [1.4,2.2])box(scaffold,2.45,.1,.65,0,y,2.3,'wood2');
  scaffold.visible=false;
  const completed=emblem(keep,0,2.64,2.8,.2);completed.visible=false;
  function updateConstruction(state){scaffold.visible=state.building;completed.visible=state.level>=2;}
  window.addEventListener('conquer-city-state',event=>updateConstruction(event.detail));
  if(window.CONQUER_CITY_STATE)updateConstruction(window.CONQUER_CITY_STATE);
}



// Project the complete ground footprint: a z-offset alone drifts sideways in
// an isometric camera. The gate is bounded locally, not by the whole city wall.
function buildingFootprint(root,code){
  root.updateWorldMatrix(true,true);
  const bounds=code==='castle'?new T.Box3().setFromObject(root.userData.castleModel()):code==='wall'?new T.Box3(new T.Vector3(-4,.1,-1.2).add(root.position),new T.Vector3(4,.1,1.3).add(root.position)):new T.Box3().setFromObject(root);
  const y=root.position.y+.08;
  return [[bounds.min.x,bounds.min.z],[bounds.max.x,bounds.min.z],[bounds.max.x,bounds.max.z],[bounds.min.x,bounds.max.z]].map(([x,z])=>new T.Vector3(x,y,z));
}
function projectActions(anchor,w,h){
  const corners=anchor.footprint.map(point=>point.clone().project(camera));
  const xs=corners.map(p=>(p.x*.5+.5)*w),ys=corners.map(p=>(-p.y*.5+.5)*h);
  return {x:(Math.min(...xs)+Math.max(...xs))/2,y:Math.max(...ys)+6};
}
function positionLabels(){
  if(!window.CONQUER_PLAY)return;
  const w=host.clientWidth,h=host.clientHeight;
  // The embedded resource bar is hidden; reserve the main app's header instead.
  const top=Math.max(document.querySelector('#resource-bar').getBoundingClientRect().bottom+8,window.CONQUER_PLAY.embedded?(w>900?60:100):0),placed=[];
  const command=document.getElementById('building-command');
  if(!command.hidden){
    const anchor=labelAnchors.find(a=>a.code===command.dataset.code);
    if(anchor){
      const {x,y}=projectActions(anchor,w,h);
      const actionScale=T.MathUtils.clamp(4.3*w*camera.zoom/(camera.right-camera.left)/108,.7,1);
      command.style.setProperty('--action-scale',actionScale);
      const dialog=document.getElementById('upgrade-dialog');
      const half=Math.max(command.offsetWidth,dialog.hidden?0:dialog.offsetWidth)/2;
      const compactLandscape=w>h&&h<500;
      const rightInset=window.CONQUER_PLAY.embedded&&w<=600&&h>w?72:8;
      // The app's quest card and chat occupy the left/centre in short landscape.
      const minX=compactLandscape?w-half-8:half+8;
      const cx=Math.max(minX,Math.min(w-half-rightInset,x));
      const actionsHeight=command.querySelector('.building-command-shell')?.offsetHeight??command.querySelector('.quick-actions').offsetHeight;
      const detailsOpen=!dialog.hidden;
      // Keep a scrolling cost dialog reachable when rotating a small screen,
      // even if the selected building's projected anchor moves out of view.
      const dialogSpace=Math.min(240,Math.max(95,h-top-actionsHeight-22));
      const actionY=detailsOpen
        ?Math.max(top,Math.min(y,h-8-actionsHeight-6-dialogSpace))
        :compactLandscape?Math.max(top,Math.min(Math.max(y,185),h-64-actionsHeight)):y;
      command.style.left=cx+'px';command.style.top=actionY+'px';
      command.style.visibility=detailsOpen||(y>=top&&y<(compactLandscape?h:h-145)&&x>=0&&x<=w)?'visible':'hidden';
      dialog.style.maxHeight=Math.max(95,h-(detailsOpen?8:110)-actionY-actionsHeight-6)+'px';
      placed.push({l:cx-half,r:cx+half,t:actionY,b:Math.min(h-8,actionY+command.offsetHeight)});
    }
  }
  const anchors=[...labelAnchors].sort((a,b)=>Number(document.getElementById('label-'+b.code)?.classList.contains('selected'))-Number(document.getElementById('label-'+a.code)?.classList.contains('selected')));
  for(const anchor of anchors){
    const point=anchor.position.clone().project(camera),el=document.getElementById('label-'+anchor.code);if(!el)continue;
    const ox=(point.x*.5+.5)*w,oy=(-point.y*.5+.5)*h;
    const active=el.classList.contains('selected'),hasJob=!!el.querySelector('.activity');
    const visible=point.z<1&&ox>=0&&ox<=w&&oy>=top-30&&oy<=h-40&&(zoom>=.7||active||hasJob);el.style.visibility=visible?'visible':'hidden';if(!visible)continue;
    const width=el.offsetWidth,height=el.offsetHeight;
    let x=Math.max(width/2+8,Math.min(w-width/2-8,ox)),y=Math.max(top+height,Math.min(h-110,oy));
    const rect=(x,y)=>({l:x-width/2,r:x+width/2,t:y-height,b:y});
    const hits=(a,b)=>a.l<b.r+6&&a.r>b.l-6&&a.t<b.b+6&&a.b>b.t-6;
    for(const previous of placed){
      if(!hits(rect(x,y),previous))continue;
      const choices=[[previous.l-width/2-8,y],[previous.r+width/2+8,y],[x,previous.t-8],[x,previous.b+height+8]];
      const valid=choices.filter(([cx,cy])=>cx-width/2>=8&&cx+width/2<=w-8&&cy-height>=top&&cy<=h-110&&!placed.some(p=>hits(rect(cx,cy),p)));
      valid.sort((a,b)=>Math.hypot(a[0]-ox,a[1]-oy)-Math.hypot(b[0]-ox,b[1]-oy));
      if(valid.length)[x,y]=valid[0];
    }
    if(!active&&!hasJob&&(placed.some(p=>hits(rect(x,y),p))||Math.hypot(x-ox,y-oy)>85)){el.style.visibility='hidden';continue;}
    el.style.left=x+'px';el.style.top=y+'px';placed.push(rect(x,y));
  }
}
if(window.CONQUER_PLAY){
  const academy=group(-3.7,.12,-2.5);academy.userData.building='academy';buildStorybookCore(academy,'academy');
  const barrack=group(3.6,.12,3.1);barrack.userData.building='barrack';buildStorybookCore(barrack,'barrack');
  gameBuildings.push(academy,barrack);
  buildingData.academy={title:'Akademie',text:'Forschung in deiner Stadt.',position:[-3.7,-2.5]};
  buildingData.barrack={title:'Kaserne',text:'Training deiner Truppen.',position:[3.6,3.1]};
  labelAnchors.push({code:'castle',position:new T.Vector3(1.5,7.2,-2.45)},{code:'academy',position:new T.Vector3(-3.7,4.1,-2.5)},{code:'barrack',position:new T.Vector3(3.6,3,3.1)});
  land.scale.set((villageBoundary.wallX+5.7)/8.72,1,(villageBoundary.wallZ+6)/8.72);turf.scale.copy(land.scale);
  // Retire the small study's loose paths, wheat, stones and trees before laying out the districts.
  for(const object of scene.children){
    if(object.userData.landscapeTree)object.visible=false;
    if(object.isMesh&&object!==land&&object!==turf&&object!==selection&&!(object.geometry.type==='PlaneGeometry'&&object.geometry.parameters.width===200))object.visible=false;
  }
  Object.assign(sun.shadow.camera,{left:-58,right:58,top:58,bottom:-58,far:125});sun.shadow.camera.updateProjectionMatrix();
  for(const [code,root] of [['castle',keep],['academy',academy],['barrack',barrack],['lumber_camp',mill]])placeVillageBuilding(root,code);mill.userData.building='lumber_camp';
  labelAnchors.length=0;
  const existing=[['castle','Festung',keep,9.3,'keep'],['academy','Akademie',academy,4.9,'academy'],['barrack','Kaserne',barrack,4.1,'barrack'],['lumber_camp','Holzfällerlager',mill,4.3,'lumber_camp']];
  for(const [code,name,root,height,id] of existing){buildingData[id]={title:name,text:'Dein Stadtgebäude.',position:[root.position.x,root.position.z]};const top=new T.Box3().setFromObject(root).max.y+.65;labelAnchors.push({code,position:new T.Vector3(root.position.x,top,root.position.z),footprint:buildingFootprint(root,code)});}
  const districts=buildDistricts({scene,box,mesh,cyl,group,roof,flag,tree,mats});
  farmRoot=districts.find(d=>d.code==='farm')?.root;
  villageLandscape=buildVillageLandscape({scene,groundMaterial:createVillageGroundMaterial()});
  land.visible=turf.visible=false;
  enrichVillage({scene,buildings:[['castle',keep],['academy',academy],['barrack',barrack],['lumber_camp',mill],...districts.map(({code,root})=>[code,root])]});
  cityLife=buildCityLife({scene});
  // The perimeter is a landmark, with a visible walkway for the guards.
  // Building picking already prefers the building behind a wall segment.
  districts.find(d=>d.code==='wall').root.traverse(object=>{
    if(!object.isMesh)return;
    object.material=object.material===mats.stone2?storyM.stone:storyM.cream;
    object.castShadow=true;
  });
  window.addEventListener('conquer-building-deselect',()=>{selected=null;selection.visible=false;});
  window.addEventListener('conquer-reveal-actions',event=>{
    const id=event.detail.code==='castle'?'keep':event.detail.code,d=buildingData[id];
    if(d){selected=id;const anchor=labelAnchors.find(a=>a.code===event.detail.code),{x,y}=projectActions(anchor,host.clientWidth,host.clientHeight);
      if(y>host.clientHeight-270||y<150||x<70||x>host.clientWidth-70){target.set(d.position[0],.6,d.position[1]);resize();}
    }
  });

  for(const {code,name,root,height} of districts){gameBuildings.push(root);buildingData[code]={title:name,text:'Dein Stadtgebäude.',position:[root.position.x,root.position.z]};const top=code==='wall'?height:new T.Box3().setFromObject(root).max.y+.6;labelAnchors.push({code,position:new T.Vector3(root.position.x,top,root.position.z),footprint:buildingFootprint(root,code)});}
  function resetVillageView(){const portrait=host.clientWidth/host.clientHeight<.8;overview=true;target.set(villageOverview.x,.6,portrait?1:villageOverview.z);setZoom(1);}
  resetVillageView();
  document.querySelector('#reset').onclick=resetVillageView;
  window.addEventListener('conquer-focus-building',event=>{const id=event.detail.code==='castle'?'keep':event.detail.code,d=buildingData[id];if(d){target.set(d.position[0],.6,d.position[1]);setZoom(id==='keep'?.95:1.2);select(id);}});
}


// Embedded pages use the parent profile, so an older building-state response
// cannot undo a skin just selected in the app. Standalone pages use the API.
function applyCastleSkin(skin){
  if(typeof skin!=='string'||!keep.userData.setCastleSkin(skin))return;
  keep.userData.animateCastle(clock);
  const anchor=labelAnchors.find(anchor=>anchor.code==='castle');
  if(anchor){
    keep.updateWorldMatrix(true,true);
    const top=new T.Box3().setFromObject(keep.userData.castleModel()).max.y+.65;
    anchor.position.set(keep.position.x,top,keep.position.z);
    anchor.footprint=buildingFootprint(keep,'castle');
  }
}
window.addEventListener('conquer-city-state',event=>{
  if(!window.CONQUER_EMBED&&!window.CONQUER_PLAY?.embedded)applyCastleSkin(event.detail.city_skin);
});
window.addEventListener('message',event=>{
  if(!(window.CONQUER_EMBED||window.CONQUER_PLAY?.embedded)||event.origin!==location.origin||event.source!==window.parent)return;
  if(event.data?.type==='conquer:preferences'){
    applyCastleSkin(event.data.city_skin);
  }
});
if(!window.CONQUER_EMBED&&!window.CONQUER_PLAY?.embedded)applyCastleSkin(window.CONQUER_CITY_STATE?.city_skin);

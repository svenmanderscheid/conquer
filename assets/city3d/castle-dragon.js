import * as T from './vendor/three.module.js';
import {createStorybookMaterial,addStorybookOutline} from './storybook-style.js?v=storybook5';
import {GLTFLoader} from './vendor/loaders/GLTFLoader.js';
import {createDragonsteelCastleDragon} from './dragonsteel-castle-flight.js?v=dragonsteel7';

// Drachenstahl-Zitadelle: the gold-white T10 faction translated into a broad,
// readable castle silhouette. The architecture stays still; banners, crystal
// beacons and the dragon breath share one lightweight animation group.
export function buildDragonCastle(skin){
 if(skin!=='dragon')return null;
 const root=new T.Group();root.name='castle-skin-dragon';
 Object.assign(root.userData,{building:'keep',skin:'dragon',animationCount:3,skipEpicDetails:true,assetReady:false});
 const palette={shadow:'#55443b',stone:'#eadfc9',stoneLight:'#fff5dc',stoneShade:'#c7b89d',gold:'#d9a74e',goldLight:'#ffe49a',goldShade:'#936635',orange:'#d65c2d',orangeLight:'#f48a42',blue:'#2c86d7',blueLight:'#86ddff',wood:'#725039'};
 const materials=new Map();
 const material=(name,options={})=>{const key=name+JSON.stringify(options);if(!materials.has(key))materials.set(key,createStorybookMaterial(palette[name]||name,options));return materials.get(key);};
 const fixed=new T.Group();fixed.name='dragonsteel-architecture';root.add(fixed);const loose=[];
 function part(geometry,name,x,y,z,{owner=fixed,rotation=[0,0,0],scale=[1,1,1]}={}){const item=new T.Mesh(geometry,material(name));item.position.set(x,y,z);item.rotation.set(...rotation);item.scale.set(...scale);item.castShadow=item.receiveShadow=true;owner.add(item);if(owner===fixed)loose.push(item);return item;}
 const box=(name,x,y,z,w,h,d,opts)=>part(new T.BoxGeometry(1,1,1),name,x,y,z,{...opts,scale:[w,h,d]});
 const cyl=(name,x,y,z,r,h,opts={})=>part(new T.CylinderGeometry(r*.91,r,h,12),name,x,y,z,opts);
 const cone=(name,x,y,z,r,h,opts={})=>part(new T.ConeGeometry(r,h,10),name,x,y,z,opts);
 const ball=(name,x,y,z,w,h=w,d=w,opts={})=>part(new T.SphereGeometry(1,12,8),name,x,y,z,{...opts,scale:[w,h,d]});
 function line(name,points,r=.045,owner=fixed){return part(new T.TubeGeometry(new T.CatmullRomCurve3(points.map(p=>new T.Vector3(...p))),Math.max(8,points.length*4),r,6,false),name,0,0,0,{owner});}
 function plate(name,points,x,y,z,depth=.1,owner=fixed){const shape=new T.Shape();points.forEach(([px,py],i)=>i?shape.lineTo(px,py):shape.moveTo(px,py));shape.closePath();const geometry=new T.ExtrudeGeometry(shape,{depth,bevelEnabled:true,bevelSize:.025,bevelThickness:.025,bevelSegments:1});geometry.translate(0,0,-depth/2);return part(geometry,name,x,y,z,{owner});}
 function crystal(x,y,z,s=.18,owner=fixed){const gem=part(new T.OctahedronGeometry(1,0),'blue',x,y,z,{owner,scale:[s,s*1.55,s*.72]});gem.rotation.z=Math.PI/4;return gem;}
 function roof(x,y,z,r,h){cyl('goldShade',x,y-.04,z,r*1.09,.14);cone('gold',x,y+h/2,z,r,h);for(let i=0;i<8;i++){const a=i*Math.PI/4;line('goldLight',[[x,y,z],[x+Math.sin(a)*r*.87,y+.08,z+Math.cos(a)*r*.87]],.035);}cone('goldLight',x,y+h+.15,z,.09,.34);}
 function tower(x,z,r,h,roofScale=1){cyl('shadow',x,.22,z,r*1.06,.34);cyl('stoneShade',x,.48,z,r,.25);cyl('stone',x,.55+h/2,z,r,h);cyl('goldShade',x,.62+h,z,r*1.08,.16);for(let i=0;i<8;i++){const a=i*Math.PI/4;if(i%2===0)box('stoneLight',x+Math.sin(a)*r*.94,.89+h,z+Math.cos(a)*r*.94,.23,.52,.23);}roof(x,1.08+h,z,r*1.28*roofScale,.9*roofScale);for(const side of[-1,1])box('gold',x+side*r*.74,.56+h*.57,z+r*.72,.13,h*.56,.12);crystal(x,.72+h*.66,z+r*.9,.14);}

 // Broad stepped foundation leaves the southern approach clear.
 cyl('shadow',0,.16,-.08,3.08,.30);cyl('stoneShade',0,.35,-.08,2.88,.26);
 for(let i=0;i<18;i++){const a=.43+i*(Math.PI*2-.86)/17,r=2.78;box(i%3?'stone':'stoneLight',Math.sin(a)*r,.46,Math.cos(a)*r-.08,.48,.32,.42,{rotation:[0,a,0]});}

 // Four chunky outer towers and the layered keep mirror the approved Meshy
 // concept without copying its noisy micro-geometry.
 tower(-2.02,.08,.63,1.78,.90);tower(2.02,.08,.63,1.78,.90);tower(-1.52,-1.52,.55,1.66,.82);tower(1.52,-1.52,.55,1.66,.82);
 cyl('stone',0,1.40,-.55,1.46,2.18);cyl('goldShade',0,2.48,-.55,1.55,.18);
 for(let i=0;i<10;i++){const a=i*Math.PI/5;box('stoneLight',Math.sin(a)*1.38,2.72,Math.cos(a)*1.38-.55,.32,.48,.34,{rotation:[0,a,0]});}
 roof(0,2.84,-.55,1.62,1.18);cyl('stoneLight',0,3.34,-.55,.77,1.10);cyl('goldShade',0,3.88,-.55,.84,.14);roof(0,3.99,-.55,1.02,1.15);crystal(0,5.38,-.55,.22);

 // Front gate, dragon mask and deep blue diamond identify the skin even at
 // the normal city camera distance.
 box('stoneShade',0,1.08,1.42,1.62,1.74,.45);
 plate('shadow',[[-.58,0],[-.58,.74],[-.42,1.18],[0,1.42],[.42,1.18],[.58,.74],[.58,0]],0,.36,1.69,.12);
 plate('wood',[[-.43,0],[-.43,.70],[-.30,1.00],[0,1.16],[.30,1.00],[.43,.70],[.43,0]],0,.38,1.77,.08);
 for(const side of[-1,1])line('gold',[[side*.46,.44,1.85],[side*.46,1.26,1.85],[side*.25,1.48,1.85]],.055);
 ball('stoneLight',0,2.02,1.62,.62,.43,.30);ball('gold',0,1.88,1.88,.42,.25,.18);
 for(const side of[-1,1]){cone('gold',side*.38,2.33,1.60,.13,.64,{rotation:[0,0,-side*.55]});cone('stoneLight',side*.24,1.78,2.02,.065,.24,{rotation:[Math.PI/2,0,0]});ball('blue',side*.20,2.04,1.93,.065,.052,.035);}
 crystal(0,2.38,1.91,.18);for(let i=0;i<6;i++)box(i%2?'stoneLight':'stone',0,.18+i*.09,2.12+i*.17,1.23+i*.10,.13,.38);
 for(const side of[-1,1]){line('gold',[[side*.72,1.73,1.32],[side*1.18,2.25,.76],[side*1.48,2.70,.04]],.085);crystal(side*1.08,2.33,.78,.13);line('goldLight',[[side*.48,3.18,.26],[side*.77,3.58,-.17],[side*.82,4.02,-.55]],.055);}

 // Merge fixed architecture by pigment for a bounded mobile draw-call cost.
 const batches=new Map();fixed.updateMatrixWorld(true);
 for(const item of loose){const geometry=item.geometry.index?item.geometry.toNonIndexed():item.geometry.clone();geometry.applyMatrix4(item.matrixWorld);const key=item.material.uuid;if(!batches.has(key))batches.set(key,{surface:item.material,parts:[]});batches.get(key).parts.push(geometry);}
 fixed.clear();let fixedTriangles=0;
 for(const {surface,parts} of batches.values()){
  const geometry=new T.BufferGeometry(),count=parts.reduce((n,g)=>n+g.attributes.position.count,0);fixedTriangles+=count/3;
  for(const [name,size] of [['position',3],['normal',3],['uv',2]]){const values=new Float32Array(count*size);let offset=0;for(const g of parts){const a=g.attributes[name];if(a)values.set(a.array,offset);offset+=g.attributes.position.count*size;}geometry.setAttribute(name,new T.BufferAttribute(values,size));}
  parts.forEach(g=>g.dispose());geometry.computeBoundingSphere();const merged=new T.Mesh(geometry,surface);merged.castShadow=merged.receiveShadow=true;fixed.add(merged);addStorybookOutline(merged,.021);
 }

 const motion=new T.Group();motion.name='dragonsteel-living-banner';motion.userData.animatedPart=true;root.add(motion);const banners=[];
 function banner(x,y,z,side=1){const group=new T.Group();group.position.set(x,y,z);motion.add(group);part(new T.CylinderGeometry(.025,.025,1.58,7),'gold',0,.70,0,{owner:group});ball('goldLight',0,1.52,0,.07,.07,.07,{owner:group});const segments=[];for(let i=0;i<4;i++){const cloth=box(i===0?'orangeLight':'orange',side*(.14+i*.19),1.25-i*.055,.025,.38,.31,.035,{owner:group});cloth.geometry.translate(side*.5,0,0);segments.push(cloth);}banners.push({group,segments,side});}
 banner(-2.48,1.62,.58,1);banner(2.48,1.62,.58,-1);
 const beacons=[];for(const [x,y,z,s] of [[0,5.38,-.55,.23],[-1.08,2.33,.78,.14],[1.08,2.33,.78,.14]]){const glow=new T.Mesh(new T.OctahedronGeometry(1,0),new T.MeshBasicMaterial({color:palette.blueLight,transparent:true,opacity:.45,depthWrite:false,blending:T.AdditiveBlending}));glow.position.set(x,y,z);glow.scale.set(s,s*1.55,s*.72);glow.raycast=()=>{};motion.add(glow);beacons.push(glow);}
 const breath=[];for(let i=0;i<3;i++){const puff=new T.Mesh(new T.OctahedronGeometry(.13+i*.025,0),new T.MeshBasicMaterial({color:i===2?palette.goldLight:palette.blueLight,transparent:true,opacity:.5,depthWrite:false,blending:T.AdditiveBlending}));puff.raycast=()=>{};motion.add(puff);breath.push(puff);}
 const skyDragon=createDragonsteelCastleDragon();root.add(skyDragon);
 let phase=0;
 root.userData.animate=time=>{phase=time;banners.forEach(({segments,side},b)=>segments.forEach((cloth,i)=>{cloth.rotation.y=side*(.08+Math.sin(time*2.1+i*.62+b*.8)*(.07+i*.035));cloth.rotation.z=Math.sin(time*1.45+i*.4+b)*.025;}));beacons.forEach((gem,i)=>{const pulse=.88+(Math.sin(time*2.2+i*1.8)+1)*.16;gem.scale.multiplyScalar(pulse/(gem.userData.lastPulse||1));gem.userData.lastPulse=pulse;gem.material.opacity=.34+pulse*.17;});breath.forEach((puff,i)=>{const cycle=(time*.20+i/3)%1,a=cycle*Math.PI*.65;puff.position.set(Math.sin(time*1.1+i)*.07,2.0+Math.sin(a)*.28,2.04+cycle*.72);puff.scale.setScalar(.45+Math.sin(cycle*Math.PI)*.9);puff.material.opacity=.62*(1-cycle);});skyDragon.userData.animateFlight(time,false);};
 root.userData.dragonMotionState=()=>({phase,wingBeat:banners[0].segments[2].rotation.y,emberScale:beacons[0].userData.lastPulse||1,smoke:breath.length});
 root.userData.modelStats={drawCalls:batches.size+8,triangles:Math.round(fixedTriangles),pieces:loose.length};root.userData.epicDetails={theme:'dragon',pieces:loose.length,batches:batches.size};
 if(typeof window!=='undefined'){
  const url=new URL('../art/castles/dragonsteel-castle-mobile.glb',import.meta.url).href;
  root.userData.readyPromise=new Promise(resolve=>new GLTFLoader().load(url,gltf=>{
   const model=gltf.scene;model.name='dragonsteel-meshy-castle';
   model.traverse(object=>{if(!object.isMesh)return;object.castShadow=object.receiveShadow=true;const source=object.material;object.material=new T.MeshToonMaterial({map:source.map||null,color:source.color||new T.Color('#ffffff'),transparent:source.transparent,opacity:source.opacity,alphaTest:source.alphaTest,side:source.side});});
   // Premium landmark sizing: a broader footprint keeps the skin readable in
   // the full-city camera while the extra vertical stretch makes its towers
   // rise clearly above the neighbouring roofs.
   let bounds=new T.Box3().setFromObject(model),extent=bounds.getSize(new T.Vector3()),scale=Math.min(6.95/extent.x,6.55/extent.y,6.15/extent.z);model.scale.setScalar(scale);model.scale.y*=1.20;bounds.setFromObject(model);const center=bounds.getCenter(new T.Vector3());model.position.set(-center.x,-bounds.min.y,-center.z);
   fixed.visible=false;motion.visible=false;root.add(model);root.userData.assetReady=true;resolve(model);
  },undefined,()=>resolve(null)));
 }
 root.userData.animate(0);return root;
}

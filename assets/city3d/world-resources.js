import * as T from './vendor/three.module.js';
import {storybookMaterials as M,addStorybookOutline} from './storybook-style.js?v=storybook5';
import {buildStorybookProduction} from './storybook-production.js?v=farm-life7';
import {buildWorldScenery} from './world-scenery-models.js';

// Dedicated compact map compositions. The city's buildings and paths stay owned
// by its scene. All motion here remains within the resource site's own yard.
export const worldResourceKinds=['farm','lumber','quarry','gold','crystal'];
export function buildWorldResource(kind,{biome=null}={}){
 if(!worldResourceKinds.includes(kind))return null;
 const root=new T.Group(),updates=[];root.name='world-resource-'+kind;
 const mesh=(parent,geometry,colour,x,y,z,ink=true)=>{const obj=new T.Mesh(geometry,M[colour]);obj.position.set(x,y,z);parent.add(obj);if(ink)addStorybookOutline(obj,.028);return obj;};
 const box=(parent,colour,x,y,z,w,h,d)=>mesh(parent,new T.BoxGeometry(w,h,d),colour,x,y,z);
 const ball=(parent,colour,x,y,z,sx,sy,sz)=>{const obj=mesh(parent,new T.SphereGeometry(1,9,6),colour,x,y,z,false);obj.scale.set(sx,sy,sz);return obj;};
 const cylinder=(parent,colour,x,y,z,r,h)=>mesh(parent,new T.CylinderGeometry(r,r,h,10),colour,x,y,z);
 // A quiet irregular earth patch ties fields, tools and buildings together.
 const patch=mesh(root,new T.CylinderGeometry(2.9,3,.12,9),'timber',0,.02,.3,false);patch.scale.z=.88;patch.rotation.y=.15;
 // Regional map renders get their ground from the terrain renderer. Keep this
 // invisible volume for identical camera framing, without baking in a pedestal.
 patch.visible=!biome;
 for(const [x,z,s] of [[-2.6,.5,.2],[2.3,1.6,.22],[-1.8,-1.5,.16],[1.8,-1.8,.19]])if(!biome)ball(root,'leafLight',x,.1,z,s,.08,s*.8);
 function worker(x,z,colour,tool,{mining=false}={}){
  const person=new T.Group();person.position.set(x,.1,z);person.scale.setScalar(.68);root.add(person);
  for(const side of [-1,1])ball(person,'wood',side*.13,.12,.08,.14,.12,.21);
  mesh(person,new T.CylinderGeometry(.23,.3,.48,8),colour,0,.49,0);
  ball(person,'cream',0,.98,0,.33,.31,.3);
  for(const side of [-1,1])ball(person,'dark',side*.1,1,.287,.03,.042,.02);
  if(mining){
   ball(person,'gold',0,1.2,0,.35,.19,.32);cylinder(person,'gold',0,1.19,.02,.37,.065);
   ball(person,'wood',0,1.22,.31,.115,.105,.04);ball(person,'glow',0,1.22,.35,.072,.068,.025);
  }else{cylinder(person,'wheat',0,1.21,0,.42,.06);mesh(person,new T.ConeGeometry(.29,.22,9),'wheat',0,1.32,0);}
  const arm=new T.Group();arm.position.set(.25,.71,.06);person.add(arm);
  ball(arm,colour,0,-.1,0,.095,.22,.095);ball(arm,'cream',0,-.28,.03,.105,.105,.1);
  box(arm,'wood',.07,-.23,.1,.065,.95,.065);
  box(arm,tool==='rake'?'timber':'stone',.07,.24,.1,.48,.1,.13);
  if(tool==='rake')for(let i=0;i<4;i++)box(arm,'wood',-.1+i*.11,.15,.1,.045,.18,.045);
  updates.push(t=>{
   const cycle=((t/2+x*.13)%1+1)%1,ease=v=>{v=Math.max(0,Math.min(1,v));return v*v*(3-2*v);};
   // Lift slowly, strike/rake with intent, then rest before the next stroke.
   const stroke=ease(cycle/.46)*(1-ease((cycle-.55)/.18));
   // The miner faces the rock: raise, strike forwards, then hold at contact.
   arm.rotation.x=mining?1.65-1.95*stroke:-.32-stroke*.85;
   person.rotation.x=stroke*.045;person.rotation.z=Math.sin(t*Math.PI/2+x)*.012;
  });
  return person;
 }
 if(['farm','quarry','gold'].includes(kind)){
  const building=new T.Group();buildStorybookProduction(building,kind==='gold'?'gold_mine':kind);root.add(building);
  building.scale.setScalar(kind==='farm'?.76:.86);building.position.set(kind==='farm'?-.65:0,.07,kind==='farm'?-.38:-.28);
  if(kind==='farm'){
   updates.push(t=>building.userData.animateFarm?.(t,{paused:false,reducedMotion:false,loopSeconds:4}));
   // Broad turning sails remain legible at the normal map zoom.
   const mill=new T.Group();mill.position.set(1.48,.1,-.68);root.add(mill);
   mesh(mill,new T.CylinderGeometry(.38,.61,2.6,10),'cream',0,1.3,0);
   mesh(mill,new T.ConeGeometry(.73,.86,10),'orange',0,2.91,0);
   box(mill,'wood',0,.42,.57,.32,.68,.07);box(mill,'blue',0,1.65,.42,.27,.36,.05);
   const sails=new T.Group();sails.position.set(0,2.25,.64);mill.add(sails);
   for(let i=0;i<4;i++){
    const blade=new T.Group();blade.rotation.z=i*Math.PI/2;sails.add(blade);
    box(blade,'wood',0,.63,0,.1,1.5,.09);box(blade,'trim',.16,.92,.01,.38,.75,.065);
    for(let n=0;n<3;n++)box(blade,'timber',.16,.66+n*.23,.055,.39,.04,.04);
   }
   ball(sails,'wood',0,0,.12,.16,.16,.12);updates.push(t=>{sails.rotation.z=-t*Math.PI/2;});
   worker(1.38,1.78,'leaf','rake');
  }else worker(-1.8,1.3,kind==='gold'?'blue':'orange','pick');
 }else if(kind==='lumber'){
  // Oversized cut ends, open timber shed and a slowly turning saw wheel.
  for(const x of [-1.65,.4])for(const z of [-1.05,.65])box(root,'wood',x,.9,z,.17,1.7,.17);
  for(const side of [-1,1]){const roof=box(root,'orange',-.62,1.98,-.2,1.34,.17,2.1);roof.position.x+=side*.51;roof.rotation.z=-side*.34;}
  for(let i=0;i<5;i++){
   const x=-1.46+(i%3)*.62,y=.34+Math.floor(i/3)*.5;
   const log=cylinder(root,'wood',x,y,.26,.29,2.1);log.rotation.x=Math.PI/2;
   const end=cylinder(root,'wheat',x,y,1.33,.245,.03);end.rotation.x=Math.PI/2;
   const ring=mesh(root,new T.TorusGeometry(.14,.021,5,12),'timber',x,y,1.35,false);
  }
  for(const [x,z,scale] of [[1.55,-1.1,.95],[2.1,.2,.68]]){
   const tree=buildWorldScenery(biome==='ice'?'pine':'oak');tree.position.set(x,.05,z);tree.scale.setScalar(scale);root.add(tree);
   if(biome)regionalTree(tree,biome);
  }
  box(root,'timber',.67,.72,1.12,.22,1.2,.23);
  const saw=mesh(root,new T.CylinderGeometry(.46,.46,.1,14),'stone',.67,1.04,1.28);saw.rotation.x=Math.PI/2;
  for(let i=0;i<8;i++){const tooth=box(saw,'trim',Math.cos(i*Math.PI/4)*.43,0,Math.sin(i*Math.PI/4)*.43,.12,.14,.12);tooth.rotation.y=-i*Math.PI/4;}
  updates.push(t=>{saw.rotation.y=t*Math.PI/2;});worker(-.2,1.92,'red','pick');
 }else{
  // A rising, asymmetric fan of ruby crystals. Slender chamfered prisms and
  // painted rose highlights give the seam a jewel-like, storybook silhouette.
  const rockGeometry=new T.SphereGeometry(1,11,7),vertices=rockGeometry.attributes.position;
  for(let i=0;i<vertices.count;i++){
   const x=vertices.getX(i),y=vertices.getY(i),z=vertices.getZ(i);
   const bulge=1+.09*Math.sin(x*4+z*3)+.06*Math.cos(z*5-y*3);
   vertices.setXYZ(i,x*bulge+Math.max(y,0)*.12,Math.max(-.65,y)*(1+.07*z),z*bulge);
  }
  rockGeometry.computeVertexNormals();
  for(const [x,y,z,sx,sy,sz,turn] of [[.05,.21,.04,1.14,.35,.84,.1],[-.8,.16,.49,.59,.27,.43,-.35],[.9,.14,.22,.48,.23,.5,.5],[-.48,.16,-.59,.61,.27,.42,.3],[.3,.09,.81,.6,.16,.32,-.2]]){
   const rock=mesh(root,rockGeometry,'stone',x,y,z,false);rock.scale.set(sx,sy,sz);rock.rotation.y=turn;addStorybookOutline(rock,.017);
  }
  const crystalMaterials=['#d34260','#9b2f49','#ed7b8a','#f5b2b7'].map(colour=>tinted(M.red,colour));
  const gleamMaterial=tinted(M.trim,'#ffe3d3');
  function ruby(x,y,z,radius,height,tilt,turn,{gleam=false}={}){
   const positions=[[],[],[],[]],geometry=new T.BufferGeometry(),profile=[];
   // Two points at each hexagon corner bevel the edge without thin wire lines.
   const corner=i=>new T.Vector2(Math.cos(i/6*Math.PI*2+.12),Math.sin(i/6*Math.PI*2+.12));
   for(let i=0;i<6;i++)profile.push(corner(i).lerp(corner(i-1),.055),corner(i).lerp(corner(i+1),.055));
   const ring=(i,scale,h)=>{const p=profile[i%12];return[p.x*radius*scale,h,p.y*radius*scale];};
   for(let i=0;i<12;i++){
    const face=Math.floor(i/2),colour=i%2===0?(face<3?3:0):[2,0,1,1,0,2][face];
    for(const [low,high] of [[[.58,0],[.98,height*.13]],[[.98,height*.13],[.87,height*.7]]]){
     const a=ring(i,...low),b=ring(i+1,...low),c=ring(i,...high),d=ring(i+1,...high);
     positions[colour].push(...a,...c,...b,...b,...c,...d);
    }
    positions[colour].push(...ring(i,.87,height*.7),radius*.08,height,-radius*.06,...ring(i+1,.87,height*.7));
   }
   let start=0;positions.forEach((face,colour)=>{geometry.addGroup(start,face.length/3,colour);start+=face.length/3;});
   geometry.setAttribute('position',new T.Float32BufferAttribute(positions.flat(),3));geometry.computeVertexNormals();
   const growth=new T.Group();growth.position.set(x,y,z);growth.rotation.y=turn;growth.rotation.z=tilt;root.add(growth);
   const crystal=new T.Mesh(geometry,crystalMaterials);growth.add(crystal);addStorybookOutline(crystal,.012);
   if(gleam){
    // An illustrated tapered reflection, flush with a broad face. No glass/PBR.
    const onFace=(along,up)=>{
     const scale=T.MathUtils.lerp(.98,.87,(up-.13)/.57),a=new T.Vector3(...ring(1,scale,height*up)),b=new T.Vector3(...ring(2,scale,height*up));
     return a.lerp(b,along).add(new T.Vector3(.007,0,.007)).toArray();
    };
    const highlight=new T.BufferGeometry();highlight.setAttribute('position',new T.Float32BufferAttribute([...onFace(.12,.26),...onFace(.13,.65),...onFace(.27,.61)],3));highlight.computeVertexNormals();growth.add(new T.Mesh(highlight,gleamMaterial));
   }
  }
  ruby(.08,.29,-.22,.4,2.63,-.1,.15,{gleam:true});
  ruby(-.48,.22,-.25,.3,1.87,.3,-.25);
  ruby(-.75,.2,.23,.27,1.32,.53,.1);
  ruby(.55,.21,.03,.31,1.73,-.34,.3,{gleam:true});
  ruby(.18,.17,.56,.25,1.17,-.24,1.4);
  ruby(-.51,.16,.59,.18,.7,.38,-.4);
  ruby(.69,.12,.62,.16,.53,-.5,.6);
  const miner=worker(-1.48,.98,'purple','pick',{mining:true});miner.rotation.y=1.92;miner.scale.setScalar(.6);
  // A small wicker basket sits beside the seam, below the crystals' silhouette.
  const basket=new T.Group();basket.position.set(1.13,0,.79);basket.scale.setScalar(.75);root.add(basket);
  mesh(basket,new T.CylinderGeometry(.36,.26,.34,12),'timber',0,.19,0);
  mesh(basket,new T.CylinderGeometry(.31,.31,.025,12),'wood',0,.365,0,false);
  const rim=mesh(basket,new T.TorusGeometry(.33,.025,5,14),'wheat',0,.38,0,false);rim.rotation.x=Math.PI/2;
  mesh(basket,new T.TorusGeometry(.3,.025,5,14,Math.PI),'wood',0,.36,0,false);
  const chips=new T.InstancedMesh(new T.OctahedronGeometry(1),crystalMaterials[0],3),chip=new T.Object3D();
  for(const [i,x,z,s] of [[0,-.12,-.03,.13],[1,.14,.09,.12],[2,.04,-.14,.13]]){chip.position.set(x,.4,z);chip.rotation.set(.3*i,.7*i,.5);chip.scale.set(s,s*1.4,s);chip.updateMatrix();chips.setMatrixAt(i,chip.matrix);}
  basket.add(chips);
 }
 if(biome)regionalSurfaces(root,biome);
 root.userData.animateWorldResource=t=>{const time=((t%4)+4)%4;for(const update of updates)update(time);};
 root.userData.animateWorldResource(0);return root;
}

function tinted(source,color){
 const material=source.clone();material.color.set(color);
 material.onBeforeCompile=source.onBeforeCompile;material.customProgramCacheKey=source.customProgramCacheKey;
 return material;
}
function regionalTree(tree,biome){
 const palette={forest:['#498047','#74a452'],ice:['#5e9588','#b3cfc5'],sand:['#8b9a60','#a5b77b'],lava:['#59483f','#806354']}[biome];
 let index=0;
 tree.traverse(part=>{
  if(!part.isMesh)return;
  const foliage=['IcosahedronGeometry','ConeGeometry'].includes(part.geometry.type);
  if(foliage){
   if(biome==='lava'){part.visible=false;return;}
   part.material=tinted(part.material,palette[index++%2]);
   if(biome==='sand')part.scale.multiplyScalar(.67);
  }else if(biome==='lava')part.material=tinted(part.material,palette[index++%2]);
 });
}
function regionalSurfaces(root,biome){
 const stone={forest:'#b7aea1',ice:'#a5b6b5',sand:'#bd8b65',lava:'#8f807e'}[biome];
 if(!stone)return;
 const materials=new Map(),snow=[];root.updateMatrixWorld(true);
 root.traverse(part=>{
  if(!part.isMesh||!part.visible||Array.isArray(part.material))return;
  const source=part.material,shape=part.geometry.parameters;
  // The production model's broad foundation plates would recreate a cut-out
  // island. Steps and structural footings stay; the map paints the surrounding soil.
  if(part.geometry.type==='BoxGeometry'&&shape.width>3&&shape.depth>2&&shape.height<.3){part.visible=false;return;}
  if(source===M.stone||source===M.patch){
   if(!materials.has(source))materials.set(source,tinted(source,stone));part.material=materials.get(source);
  }
  // Snow rests only on upper rock/roof/foliage faces. Walls, tools, wheat and
  // role colours stay readable. This geometry is baked into small map sprites.
  if(biome==='ice'&&(source===M.orange||source===M.stone||['ConeGeometry','IcosahedronGeometry'].includes(part.geometry.type)&&part.material!==M.wheat&&part.material!==M.purple&&part.material!==M.teal)){
   const geometry=part.geometry.index?part.geometry.toNonIndexed():part.geometry.clone(),p=geometry.attributes.position,n=geometry.attributes.normal,normalMatrix=new T.Matrix3().getNormalMatrix(part.matrixWorld),positions=[];
   for(let i=0;i<p.count;i+=3){
    const normal=new T.Vector3().fromBufferAttribute(n,i).applyMatrix3(normalMatrix).normalize();if(normal.y<.55)continue;
    for(let j=0;j<3;j++){const v=new T.Vector3().fromBufferAttribute(p,i+j).addScaledVector(new T.Vector3().fromBufferAttribute(n,i+j),.024);positions.push(...v.toArray());}
   }
   geometry.dispose();
   if(positions.length){const cap=new T.BufferGeometry();cap.setAttribute('position',new T.Float32BufferAttribute(positions,3));cap.computeVertexNormals();snow.push([part,cap]);}
  }
 });
 const snowMaterial=tinted(M.stone,'#edf2e7');
 for(const [part,geometry]of snow)part.add(new T.Mesh(geometry,snowMaterial));
}

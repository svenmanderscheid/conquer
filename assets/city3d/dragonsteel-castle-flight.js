import * as T from './vendor/three.module.js';
import {createStorybookMaterial,addStorybookOutline} from './storybook-style.js?v=storybook5';
import {GLTFLoader} from './vendor/loaders/GLTFLoader.js';

// Lightweight companion for the Dragonsteel castle.  The orbit is deliberately
// broad enough to disappear behind the towers so the creature belongs to the
// scene instead of reading like a badge floating in front of it.
export function createDragonsteelCastleDragon(){
 const flight=new T.Group();flight.name='dragonsteel-castle-dragon-flight';flight.userData.cosmeticEffect=true;flight.raycast=()=>{};
 const dragon=new T.Group();dragon.name='dragonsteel-sky-dragon';dragon.scale.setScalar(.48);flight.add(dragon);
 const mat={
  ivory:createStorybookMaterial('#f7ecd2'),ivoryShade:createStorybookMaterial('#c9bca2'),gold:createStorybookMaterial('#e2ad42'),
  goldLight:createStorybookMaterial('#ffe39a'),orange:createStorybookMaterial('#d85f31',{side:T.DoubleSide}),
  blue:createStorybookMaterial('#3a98e6'),dark:createStorybookMaterial('#503d35')
 };
 const mesh=(geometry,material,parent=dragon,outline=0)=>{const item=new T.Mesh(geometry,material);item.castShadow=true;item.receiveShadow=true;item.raycast=()=>{};parent.add(item);if(outline)addStorybookOutline(item,outline);return item;};
 const ball=(x,y,z,sx,sy,sz,material=mat.ivory,parent=dragon,outline=.022)=>{const item=mesh(new T.SphereGeometry(1,12,8),material,parent,outline);item.position.set(x,y,z);item.scale.set(sx,sy,sz);return item;};
 const cone=(x,y,z,r,h,material=mat.gold,parent=dragon)=>{const item=mesh(new T.ConeGeometry(r,h,8),material,parent,.018);item.position.set(x,y,z);return item;};
 const tube=(points,r,material,parent=dragon)=>mesh(new T.TubeGeometry(new T.CatmullRomCurve3(points.map(p=>new T.Vector3(...p))),10,r,6,false),material,parent);

 // Plump, readable chibi silhouette with short armoured legs.
 ball(0,.04,.02,.52,.42,.72);ball(0,.19,.63,.48,.42,.46);ball(0,.07,1.00,.39,.31,.43,mat.ivory);
 ball(-.17,.12,1.34,.08,.07,.12,mat.dark,dragon,0);ball(.17,.12,1.34,.08,.07,.12,mat.dark,dragon,0);
 ball(0,-.05,1.38,.09,.055,.045,mat.goldLight,dragon,0);
 for(const side of[-1,1]){
  const horn=cone(side*.31,.48,.78,.11,.47,mat.gold);horn.rotation.z=-side*.72;
  const ear=cone(side*.42,.24,.88,.10,.32,mat.ivoryShade);ear.rotation.z=-side*1.02;
  ball(side*.35,.02,.94,.12,.12,.18,mat.gold);
  const foot=ball(side*.32,-.34,.18,.18,.12,.30,mat.gold,dragon,.018);foot.rotation.x=.24;
 }
 // Crown crystal and gold neck armour make the tiny creature legible at city distance.
 const crystal=mesh(new T.OctahedronGeometry(.18,0),mat.blue,dragon,.02);crystal.position.set(0,.62,.66);crystal.scale.set(.72,1.25,.72);crystal.rotation.z=Math.PI/4;
 tube([[-.40,.22,.42],[0,.35,.50],[.40,.22,.42]],.065,mat.gold);

 // Jointed tail with a blue crystal tip.
 const tail=new T.Group();tail.position.set(0,.07,-.48);dragon.add(tail);
 tube([[0,0,.10],[0,.02,-.42],[.18,.05,-.88],[.04,.08,-1.28]],.11,mat.ivoryShade,tail);
 const tailGem=mesh(new T.OctahedronGeometry(.20,0),mat.blue,tail,.018);tailGem.position.set(.04,.08,-1.39);tailGem.scale.set(.62,.62,1.30);

 // Broad painted wings: ivory leading bones, warm orange membranes and gold claws.
 const wings=[];
 for(const side of[-1,1]){
  const wing=new T.Group();wing.position.set(side*.28,.25,.20);dragon.add(wing);wings.push({wing,side});
  const geometry=new T.BufferGeometry();geometry.setAttribute('position',new T.Float32BufferAttribute([
   0,0,0, side*1.28,.02,-.18, side*.93,.01,-1.05,
   0,0,0, side*.93,.01,-1.05, side*.36,.01,-.68
  ],3));geometry.computeVertexNormals();mesh(geometry,mat.orange,wing);
  tube([[0,.03,0],[side*1.28,.05,-.18]],.055,mat.ivory,wing);
  tube([[side*1.28,.05,-.18],[side*.93,.04,-1.05]],.045,mat.gold,wing);
  tube([[side*.93,.04,-1.05],[side*.36,.04,-.68]],.035,mat.goldLight,wing);
  const claw=cone(side*1.33,.05,-.20,.07,.26,mat.goldLight,wing);claw.rotation.z=-side*Math.PI/2;
 }

 const flightDuration=28;
 const skyPoint=angle=>new T.Vector3(Math.cos(angle)*3.45,6.16+Math.sin(angle*2)*.12,Math.sin(angle)*2.72-.28);
 const skyHeading=angle=>Math.atan2(-Math.sin(angle)*3.55,Math.cos(angle)*2.82);
 flight.userData.animateFlight=(time,reduced=false)=>{
  const phase=reduced?0:(time%flightDuration+flightDuration)%flightDuration;
  const angle=phase/flightDuration*Math.PI*2,point=skyPoint(angle);
  flight.position.copy(point);
  flight.rotation.set(0,skyHeading(angle),reduced?0:-.10);
  flight.scale.setScalar(1);
  const wingBeat=reduced?0:Math.sin(time*4.1);
  dragon.position.y=reduced?0:Math.sin(time*2.05)*.035;
  dragon.rotation.set(0,0,0);
  wings.forEach(({wing,side})=>{wing.rotation.z=side*(.16+wingBeat*.31);});
  tail.rotation.y=reduced?0:Math.sin(time*2.4)*.14;
  tail.rotation.x=reduced?0:Math.sin(time*1.4)*.045;
  const model=flight.userData.model;
  if(model){
   const base=model.userData.flightBasePosition;model.position.copy(base);
   model.position.y+=reduced?0:Math.sin(time*2.05)*.035;
   model.rotation.x=.20;
   model.rotation.y=0;
   model.rotation.z=reduced?0:Math.sin(time*2.05)*.018;
  }
  flight.userData.motionState={state:'flying',phase,grounded:0,roar:0};
 };
 // Show the lightweight handmade creature immediately, then exchange only its
 // appearance for the approved Meshy dragon. The orbit remains deterministic
 // and cheap; this avoids forcing humanoid motion clips onto a quadruped.
 if(typeof window!=='undefined'){
  const url=new URL('../art/castles/dragonsteel-sky-dragon-v1.glb',import.meta.url).href;
  flight.userData.readyPromise=new Promise(resolve=>new GLTFLoader().load(url,gltf=>{
   const model=gltf.scene;model.name='dragonsteel-meshy-sky-dragon';model.raycast=()=>{};
   model.traverse(object=>{
    object.raycast=()=>{};
    if(!object.isMesh)return;
    object.castShadow=true;object.receiveShadow=false;
    const source=object.material;
    object.material=new T.MeshToonMaterial({
     map:source.map||null,normalMap:source.normalMap||null,color:source.color||new T.Color('#ffffff'),
     transparent:source.transparent,opacity:source.opacity,alphaTest:source.alphaTest,side:source.side
    });
   });
   let bounds=new T.Box3().setFromObject(model),extent=bounds.getSize(new T.Vector3());
   const scale=1.95/Math.max(extent.y,.001);model.scale.setScalar(scale);
   bounds.setFromObject(model);const center=bounds.getCenter(new T.Vector3());
   model.position.set(-center.x,-center.y,-center.z);model.rotation.x=.20;model.userData.flightBasePosition=model.position.clone();
   dragon.visible=false;flight.add(model);flight.userData.assetReady=true;flight.userData.model=model;resolve(model);
  },undefined,()=>resolve(null)));
 }
 flight.userData.animateFlight(0,false);
 return flight;
}

import * as T from './vendor/three.module.js';
import {storybookMaterials as M,addStorybookOutline} from './storybook-style.js?v=storybook5';

// Small, self-contained activity for the storybook farm. The scene owns the
// clock, so callers can pause this together with the rest of the village.
const prefersReducedMotion=()=>typeof matchMedia==='function'&&matchMedia('(prefers-reduced-motion: reduce)').matches;

export function attachFarmLife(root,{reducedMotion=prefersReducedMotion()}={}){
 if(root.userData.farmLife)return root.userData.farmLife;
 const life=new T.Group();life.name='farm-life';root.add(life);
 const box=(w,h,d,x,y,z,material,ink=false,parent=life)=>{
  const mesh=new T.Mesh(new T.BoxGeometry(w,h,d),material);mesh.position.set(x,y,z);mesh.castShadow=mesh.receiveShadow=true;parent.add(mesh);if(ink)addStorybookOutline(mesh,.018);return mesh;
 };
 const dummy=new T.Object3D();

 // A single framed vegetable bed fits in the open right-hand yard, leaving
 // the field fence, both farm workers and the front approach clear.
 const frameRows=[
  [1.27,.18,1.95,.82,.09,.08], [1.27,.18,2.44,.82,.09,.08],
  [.9,.18,2.195,.08,.09,.57], [1.64,.18,2.195,.08,.09,.57]
 ];
 const frames=new T.InstancedMesh(new T.BoxGeometry(1,1,1),M.timber,frameRows.length);
 frameRows.forEach(([x,y,z,sx,sy,sz],index)=>{dummy.position.set(x,y,z);dummy.scale.set(sx,sy,sz);dummy.updateMatrix();frames.setMatrixAt(index,dummy.matrix);});
 frames.instanceMatrix.needsUpdate=true;frames.castShadow=frames.receiveShadow=true;life.add(frames);
 const soil=box(.7,.035,.42,1.27,.145,2.195,M.patch,false);
 const cropRows=[];
 for(let row=0;row<3;row++)for(let col=0;col<3;col++)cropRows.push([.99+col*.28,.3+(row%2)*.025,2.04+row*.16,.085,.15,.09,(row+col)%2]);
 const crops=new T.InstancedMesh(new T.SphereGeometry(1,7,5),M.leafLight,cropRows.length);
 cropRows.forEach(([x,y,z,sx,sy,sz],index)=>{dummy.position.set(x,y,z);dummy.scale.set(sx,sy,sz);dummy.rotation.set(0,(index%3-.5)*.18,0);dummy.updateMatrix();crops.setMatrixAt(index,dummy.matrix);});
 crops.instanceMatrix.needsUpdate=true;crops.castShadow=crops.receiveShadow=true;life.add(crops);
 soil.renderOrder=0;

 // Moving ears overlay the existing, denser wheat patch. Two instanced draws
 // keep the visible wind motion inexpensive even at close range.
 const wheatRows=[];
 for(let row=0;row<3;row++)for(let col=0;col<5;col++)wheatRows.push({x:-2.03+col*.37,z:2.12+row*.31,phase:row*.78+col*.47,height:.58+((row+col)%3)*.055});
 const stalks=new T.InstancedMesh(new T.CylinderGeometry(.018,.026,1,5),M.gold,wheatRows.length);
 const ears=new T.InstancedMesh(new T.SphereGeometry(1,6,4),M.wheat,wheatRows.length);
 stalks.instanceMatrix.setUsage(T.DynamicDrawUsage);ears.instanceMatrix.setUsage(T.DynamicDrawUsage);
 stalks.castShadow=stalks.receiveShadow=ears.castShadow=ears.receiveShadow=true;life.add(stalks,ears);

 // A little roof vane is a readable animated silhouette without adding a new
 // building mass. The flag is deliberately small and set behind the dormer.
 const vane=new T.Group();vane.position.set(.78,3.38,-.38);life.add(vane);
 box(.055,.58,.055,0,.29,0,M.wood,false,vane);
 box(.46,.15,.04,.24,.52,0,M.orange,true,vane);
 box(.1,.08,.08,-.1,.52,0,M.gold,false,vane);

 // Both hens remain in the open yard: well away from the field route at +z
 // and from the worker positions at (-1.7,1.5) and (2.5,2.4).
 const chickens=[{x:.92,z:1.52,phase:.1},{x:1.55,z:1.57,phase:1.8}];
 const henBodies=new T.InstancedMesh(new T.SphereGeometry(1,10,7),M.cream,chickens.length);
 const henHeads=new T.InstancedMesh(new T.SphereGeometry(1,9,7),M.cream,chickens.length);
 const henBeaks=new T.InstancedMesh(new T.ConeGeometry(.045,.1,4),M.gold,chickens.length);
 const henLegs=new T.InstancedMesh(new T.BoxGeometry(1,1,1),M.orange,chickens.length*2);
 [henBodies,henHeads,henBeaks,henLegs].forEach(batch=>batch.instanceMatrix.setUsage(T.DynamicDrawUsage));
 [henBodies,henHeads,henBeaks,henLegs].forEach(batch=>{batch.castShadow=batch.receiveShadow=true;life.add(batch);});

 let paused=false,reduced=Boolean(reducedMotion);
 const wave=(time,frequency,phase,loopSeconds)=>Math.sin(time*(loopSeconds?Math.max(1,Math.round(frequency*loopSeconds/(2*Math.PI)))*2*Math.PI/loopSeconds:frequency)+phase);
 function writeWheat(time,still=false,loopSeconds=0){
  wheatRows.forEach((item,index)=>{
   const sway=still?0:wave(time,1.35,item.phase,loopSeconds)*.09;
   dummy.position.set(item.x+wave(time,1.35,item.phase,loopSeconds)*.018,item.height*.5,item.z);
   dummy.rotation.set(0,0,sway);dummy.scale.set(1,item.height,1);dummy.updateMatrix();stalks.setMatrixAt(index,dummy.matrix);
   dummy.position.set(item.x+wave(time,1.35,item.phase,loopSeconds)*.052,item.height+.03,item.z);
   dummy.rotation.set(0,0,sway*.55);dummy.scale.set(.068,.14,.055);dummy.updateMatrix();ears.setMatrixAt(index,dummy.matrix);
  });
  stalks.instanceMatrix.needsUpdate=true;ears.instanceMatrix.needsUpdate=true;
 }
 function writeChickens(time,still=false,loopSeconds=0){
  chickens.forEach(({x,z,phase},index)=>{
   const peck=still?0:Math.max(0,wave(time,1.55,phase,loopSeconds));
   const yaw=(.22+wave(time,.53,phase,loopSeconds)*.18)*(index?-.82:1);
   const px=x+(still?0:wave(time,.7,phase,loopSeconds)*.035),pz=z+(still?0:wave(time,.61,phase+Math.PI/2,loopSeconds)*.025),py=.16+(still?0:wave(time,3.1,phase,loopSeconds)*.012);
   dummy.position.set(px,py,pz);dummy.rotation.set(0,yaw,0);dummy.scale.set(.18,.13,.19);dummy.updateMatrix();henBodies.setMatrixAt(index,dummy.matrix);
   dummy.position.set(px+.13,py+.1-peck*.025,pz+.1);dummy.rotation.set(peck*.52,yaw,0);dummy.scale.set(.105,.105,.105);dummy.updateMatrix();henHeads.setMatrixAt(index,dummy.matrix);
   dummy.position.set(px+.205,py+.09-peck*.02,pz+.158);dummy.rotation.set(Math.PI/2,yaw,0);dummy.scale.set(1,1,1);dummy.updateMatrix();henBeaks.setMatrixAt(index,dummy.matrix);
   for(const side of [-1,1]){dummy.position.set(px-side*.045,py-.09,pz+.02);dummy.rotation.set(0,yaw,0);dummy.scale.set(.018,.16,.018);dummy.updateMatrix();henLegs.setMatrixAt(index*2+(side+1)/2,dummy.matrix);}
  });
  henBodies.instanceMatrix.needsUpdate=true;henHeads.instanceMatrix.needsUpdate=true;henBeaks.instanceMatrix.needsUpdate=true;henLegs.instanceMatrix.needsUpdate=true;
 }
 function restPose(){
  vane.rotation.set(0,0,0);writeChickens(0,true);writeWheat(0,true);
 }
 const controller={
  root:life,
  getState:()=>({vaneAngle:vane.rotation.y,firstStalkX:stalks.instanceMatrix.array[12],firstHenHeadY:henHeads.instanceMatrix.array[13]}),
  setPaused(value){paused=Boolean(value);if(paused)restPose();},
  setReducedMotion(value){reduced=Boolean(value);if(reduced)restPose();},
  update(timeSeconds,{paused:framePaused=false,reducedMotion:frameReduced,loopSeconds=0}={}){
   const still=paused||framePaused||(frameReduced??reduced);
   if(still){restPose();return;}
   const time=Number.isFinite(timeSeconds)?timeSeconds:0;
   vane.rotation.y=wave(time,.72,0,loopSeconds)*.13;
   writeChickens(time,false,loopSeconds);
   writeWheat(time,false,loopSeconds);
  }
 };
 restPose();root.userData.farmLife=controller;
 // Scene integration stays one small optional call and preserves the existing
 // production-builder return contract.
 root.userData.animateFarm=(timeSeconds,options)=>controller.update(timeSeconds,options);
 return controller;
}

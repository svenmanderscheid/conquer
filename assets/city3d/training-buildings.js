import * as T from './vendor/three.module.js';
import {storybookMaterials as M,addStorybookOutline} from './storybook-style.js?v=storybook5';

export function buildTrainingBuilding(root,code){
 if(!['archery_range','stable'].includes(code))return false;
 const archer=code==='archery_range',roofColor=archer?M.leaf:M.blue;
 function mesh(g,m,x,y,z,ink=true){const o=new T.Mesh(g,m);o.position.set(x,y,z);o.castShadow=o.receiveShadow=true;root.add(o);if(ink)addStorybookOutline(o,.03);return o;}
 function box(w,h,d,x,y,z,m){return mesh(new T.BoxGeometry(w,h,d),m,x,y,z);}
 function ball(x,y,z,sx,sy,sz,m){const o=mesh(new T.SphereGeometry(1,14,10),m,x,y,z);o.scale.set(sx,sy,sz);return o;}
 function arch(x,z,w,h,m){const s=new T.Shape();s.moveTo(-w/2,0);s.lineTo(w/2,0);s.lineTo(w/2,h-w/2);s.absarc(0,h-w/2,w/2,0,Math.PI);s.closePath();return mesh(new T.ExtrudeGeometry(s,{depth:.08,bevelEnabled:true,bevelSize:.04,bevelThickness:.03,bevelSegments:2}),m,x,.2,z);}
 function roof(w,d,h,x,y,z){const s=new T.Shape();s.moveTo(-w/2,0);s.quadraticCurveTo(-w*.35,h*.38,0,h);s.quadraticCurveTo(w*.35,h*.38,w/2,0);s.quadraticCurveTo(0,-.13,-w/2,0);const g=new T.ExtrudeGeometry(s,{depth:d,bevelEnabled:true,bevelSize:.06,bevelThickness:.07,bevelSegments:2,curveSegments:8});g.translate(0,0,-d/2);mesh(g,roofColor,x,y,z);box(w+.05,.12,.14,x,y,z+d/2+.06,M.wood);}
 function post(x,z,h=.9){box(.13,h,.13,x,h/2+.12,z,M.wood);ball(x,h+.15,z,.1,.065,.1,M.gold);}
 box(3.6,.18,2.75,0,.15,-.42,M.stone);box(3.3,1.9,2.5,0,1.14,-.42,M.cream);roof(3.95,2.95,1.17,0,2.11,-.42);
 for(const side of [-1,1]){box(.19,1.94,.16,side*1.54,1.14,.89,M.wood);arch(side*.94,.88,.42,.79,M.glow);}
 arch(0,.865,1.15,1.55,M.wood);arch(0,.97,.85,1.35,archer?M.timber:M.dark);
 // Wide practice court with a clear centre entrance.
 const court=mesh(new T.CylinderGeometry(1,1,.1,40),M.timber,0,.11,1.95,false);court.scale.set(2.35,1,1.5);
 for(const side of [-1,1]){for(const z of [1.2,2.1,3])post(side*2.1,z);box(.1,.12,1.8,side*2.1,.54,2.1,M.wood);}
 if(archer){
  // One oversized bow and arrow can be read from the city camera; the two
  // broad target faces make the practice court distinct from the barracks.
  const s=new T.Shape();s.moveTo(-.65,.55);s.lineTo(.65,.55);s.lineTo(.57,-.42);s.quadraticCurveTo(0,-.76,-.57,-.42);s.closePath();mesh(new T.ExtrudeGeometry(s,{depth:.065,bevelEnabled:false}),M.leaf,0,2.54,1.13);
  const bow=new T.CatmullRomCurve3([new T.Vector3(-.28,3.09,1.24),new T.Vector3(.27,2.54,1.24),new T.Vector3(-.28,1.99,1.24)]);mesh(new T.TubeGeometry(bow,18,.074,7,false),M.gold,0,0,0);box(.035,1.1,.035,-.28,2.54,1.24,M.trim);
  box(1.12,.062,.045,.04,2.54,1.3,M.trim);const arrowTip=mesh(new T.ConeGeometry(.135,.25,3),M.gold,.64,2.54,1.3,false);arrowTip.rotation.z=-Math.PI/2;
  for(const [x,z]of [[-1.38,2.7],[1.38,2.7]]){post(x,z,1.2);for(const [r,m,dz]of [[.59,M.wood,0],[.51,M.trim,.05],[.37,M.red,.08],[.23,M.trim,.11],[.11,M.red,.14]]){const disc=mesh(new T.CylinderGeometry(r,r,.055,24),m,x,1.3,z+dz,false);disc.rotation.x=Math.PI/2;}box(.045,.045,.43,x+.04,1.27,z+.37,M.wood);}
  roof(1.2,1.1,.47,-1.94,1.28,-.3);for(let i=0;i<3;i++)box(.045,1.13,.045,-1.8+i*.18,.77,.33,M.wood);
 }else{
  // A chunky wooden horse-head crest gives the gable a unique silhouette.
  // The horseshoe moves onto the stall so neither symbol hides the entrance.
  const horse=new T.Shape();horse.moveTo(-.34,-.59);horse.quadraticCurveTo(-.54,-.12,-.33,.38);horse.lineTo(-.41,.73);horse.lineTo(-.13,.6);horse.lineTo(.04,.77);horse.lineTo(.16,.44);horse.lineTo(.48,.23);horse.quadraticCurveTo(.67,-.04,.43,-.19);horse.lineTo(.17,-.12);horse.lineTo(.21,-.59);horse.closePath();
  mesh(new T.ExtrudeGeometry(horse,{depth:.095,bevelEnabled:true,bevelSize:.04,bevelThickness:.025,bevelSegments:2,curveSegments:8}),M.timber,0,2.62,1.17);
  ball(.12,2.93,1.31,.051,.06,.025,M.dark);box(.075,.57,.04,-.3,2.56,1.31,M.gold);
  const sign=mesh(new T.TorusGeometry(.25,.065,7,24,Math.PI*1.55),M.gold,2.04,1.58,.99);sign.rotation.z=-Math.PI*.275;
  roof(1.3,2.2,.65,2.03,1.35,-.24);box(.12,1.23,.12,2.6,.78,.73,M.wood);
  for(const [x,z]of [[-1.43,2.45],[1.26,2.35]]){
   const pony=new T.Group();pony.position.set(x,0,z);root.add(pony);
   const part=(px,py,pz,sx,sy,sz,m)=>{const o=new T.Mesh(new T.SphereGeometry(1,12,8),m);o.position.set(px,py,pz);o.scale.set(sx,sy,sz);pony.add(o);addStorybookOutline(o,.02);o.castShadow=true;};
   part(0,.65,0,.28,.3,.42,M.timber);for(const side of [-1,1])for(const pz of [-.24,.24])part(side*.18,.29,pz,.08,.25,.1,M.wood);
   part(0,1,.29,.19,.35,.19,M.timber);part(0,1.2,.45,.23,.22,.26,M.timber);part(0,1.12,.65,.2,.12,.14,M.wood);
   for(const side of [-1,1]){part(side*.14,1.48,.38,.055,.17,.065,M.timber);part(side*.17,1.27,.6,.025,.03,.018,M.dark);}
   part(0,.83,-.03,.3,.08,.31,M.blue);part(0,1.4,.36,.2,.1,.12,M.wood);
   // Three-quarter profiles expose the muzzle, saddle and all four legs.
   pony.rotation.y=x>0?-.95:.9;pony.scale.setScalar(1.14);
  }
  ball(2.06,.39,1,.4,.26,.31,M.wheat);box(.71,.35,.4,-1.95,.36,.76,M.wood);
 }
 // Fence posts, target rings and pony details share geometry/material batches.
 root.updateMatrixWorld(true);
 const groups=new Map(),inverse=root.matrixWorld.clone().invert();
 root.traverse(object=>{if(!object.isMesh||!['BoxGeometry','SphereGeometry','CylinderGeometry','TorusGeometry'].includes(object.geometry.type))return;const key=object.geometry.type+JSON.stringify(object.geometry.parameters)+object.material.uuid;if(!groups.has(key))groups.set(key,[]);groups.get(key).push({object,matrix:inverse.clone().multiply(object.matrixWorld)});});
 for(const parts of groups.values())if(parts.length>1){const first=parts[0].object,batch=new T.InstancedMesh(first.geometry,first.material,parts.length);batch.castShadow=first.castShadow;batch.receiveShadow=first.receiveShadow;if(first.userData.storybookInk){batch.userData.storybookInk=true;batch.raycast=()=>{};}parts.forEach(({object,matrix},i)=>{batch.setMatrixAt(i,matrix);object.removeFromParent();});root.add(batch);}
 root.userData.storybookComplete=true;return true;
}

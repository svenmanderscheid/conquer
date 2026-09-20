import * as T from './vendor/three.module.js';
import {storybookMaterials as M,addStorybookOutline} from './storybook-style.js?v=storybook5';

export function buildTrainingBuilding(root,code){
 if(!['archery_range','stable'].includes(code))return false;
 const archer=code==='archery_range',roofColor=archer?M.leaf:M.blue;
 function mesh(g,m,x,y,z,ink=true){const o=new T.Mesh(g,m);o.position.set(x,y,z);o.castShadow=o.receiveShadow=true;root.add(o);if(ink)addStorybookOutline(o,.03);return o;}
 function box(w,h,d,x,y,z,m){return mesh(new T.BoxGeometry(w,h,d),m,x,y,z,Math.min(w,h,d)>.14);}
 function beam(ax,ay,az,bx,by,bz,width,m=M.wood){const a=new T.Vector3(ax,ay,az),b=new T.Vector3(bx,by,bz),o=mesh(new T.CylinderGeometry(width,width,a.distanceTo(b),8),m,...a.clone().add(b).multiplyScalar(.5).toArray(),false);o.quaternion.setFromUnitVectors(new T.Vector3(0,1,0),b.sub(a).normalize());return o;}
 function ball(x,y,z,sx,sy,sz,m){const o=mesh(new T.SphereGeometry(1,14,10),m,x,y,z);o.scale.set(sx,sy,sz);return o;}
 function arch(x,z,w,h,m){const s=new T.Shape();s.moveTo(-w/2,0);s.lineTo(w/2,0);s.lineTo(w/2,h-w/2);s.absarc(0,h-w/2,w/2,0,Math.PI);s.closePath();return mesh(new T.ExtrudeGeometry(s,{depth:.08,bevelEnabled:true,bevelSize:.04,bevelThickness:.03,bevelSegments:2}),m,x,.2,z);}
 function roof(w,d,h,x,y,z){
  const s=new T.Shape();s.moveTo(-w/2,0);s.bezierCurveTo(-w*.37,h*.08,-w*.27,h*.96,0,h);s.bezierCurveTo(w*.27,h*.96,w*.37,h*.08,w/2,0);s.quadraticCurveTo(0,-.14,-w/2,0);
  const g=new T.ExtrudeGeometry(s,{depth:d,bevelEnabled:true,bevelSize:.07,bevelThickness:.07,bevelSegments:2,curveSegments:10});g.translate(0,0,-d/2);mesh(g,roofColor,x,y,z);
  // Broad curved eaves and a single ridge keep the painted roof readable.
  const edge=new T.CatmullRomCurve3([new T.Vector3(-w/2,0,0),new T.Vector3(-w*.3,h*.55,0),new T.Vector3(0,h,0),new T.Vector3(w*.3,h*.55,0),new T.Vector3(w/2,0,0)]);
  mesh(new T.TubeGeometry(edge,24,.055,6,false),M.wood,x,y,z+d/2+.08,false);
  beam(x,y+h+.055,z-d/2-.09,x,y+h+.055,z+d/2+.1,.06,archer?M.leafLight:M.blueDark);
 }
 function post(x,z,h=.9){box(.13,h,.13,x,h/2+.12,z,M.wood);ball(x,h+.15,z,.1,.065,.1,M.gold);}
 const wallHeight=archer?1.65:1.95,eave=wallHeight+.22;
 box(3.6,.18,2.75,0,.15,-.42,M.stone);box(3.3,wallHeight,2.5,0,wallHeight/2+.22,-.42,M.cream);roof(4.02,3.05,archer?1.05:1.3,0,eave,-.42);
 for(const side of [-1,1]){
  box(.18,wallHeight,.18,side*1.54,wallHeight/2+.22,.88,M.wood);
  arch(side*1.02,.865,.47,.88,M.wood);arch(side*1.02,.97,.31,.72,M.glow);
  box(.64,.12,.27,side*1.02,.26,1.04,M.timber);
  // Half-timbering continues around the sides visible from the city camera.
  box(.13,.13,2.48,side*1.67,.61,-.42,M.wood);
  beam(side*1.68,.65,-1.53,side*1.68,wallHeight+.1,-.3,.065);
 }
 box(3.3,.16,.17,0,eave-.1,.89,M.wood);
 arch(0,.87,1.28,1.56,M.stone);arch(0,.98,1.03,1.43,archer?M.timber:M.dark);
 if(archer){box(.06,1.07,.04,0,.76,1.09,M.wood);for(const y of [.53,1.02])box(.89,.075,.065,0,y,1.11,M.wood);ball(.27,.89,1.16,.048,.048,.03,M.gold);}
 else{for(const side of [-1,1]){box(.39,.7,.13,side*.27,.57,1.11,M.timber);beam(side*.46,.27,1.2,side*.08,.89,1.2,.035,M.wheat);}}
 // Wide practice court with a clear centre entrance.
 const court=mesh(new T.CylinderGeometry(1,1,.06,32),M.timber,0,.08,1.95,false);court.scale.set(2.55,1,1.68);court.castShadow=false;
 for(const side of [-1,1]){for(const z of [1.2,2.1,3])post(side*2.32,z);for(const y of [.45,.78])box(.1,.1,1.85,side*2.32,y,2.1,M.wood);}
 if(archer){
  // One oversized bow and arrow can be read from the city camera; the two
  // broad target faces make the practice court distinct from the barracks.
  const s=new T.Shape();s.moveTo(-.65,.55);s.lineTo(.65,.55);s.lineTo(.57,-.42);s.quadraticCurveTo(0,-.76,-.57,-.42);s.closePath();mesh(new T.ExtrudeGeometry(s,{depth:.065,bevelEnabled:false}),M.wood,0,2.54,1.23);
  const bow=new T.CatmullRomCurve3([new T.Vector3(-.28,3.09,1.36),new T.Vector3(.27,2.54,1.36),new T.Vector3(-.28,1.99,1.36)]);mesh(new T.TubeGeometry(bow,18,.074,7,false),M.gold,0,0,0);box(.035,1.1,.035,-.28,2.54,1.36,M.trim);
  box(1.12,.062,.045,.04,2.54,1.43,M.trim);const arrowTip=mesh(new T.ConeGeometry(.135,.25,3),M.gold,.64,2.54,1.43,false);arrowTip.rotation.z=-Math.PI/2;
  for(const [x,z]of [[-1.42,2.7],[1.42,2.45]]){
   for(const side of [-1,1])beam(x+side*.35,.17,z+.18,x+side*.1,1.55,z,.065);
   for(const [r,m,dz]of [[.63,M.timber,0],[.54,M.trim,.055],[.39,M.red,.085],[.25,M.trim,.115],[.12,M.red,.145]]){const disc=mesh(new T.CylinderGeometry(r,r,.055,24),m,x,1.33,z+dz,false);disc.rotation.x=Math.PI/2;}
   beam(x+.08,1.35,z+.18,x+.18,1.45,z+.71,.027);box(.11,.12,.045,x+.18,1.45,z+.68,M.trim);
  }
  // A squat lookout distinguishes the range from the broad stable barn.
  for(const x of [-2.12,-1.28])for(const z of [-1.18,-.24])box(.15,2.72,.15,x,1.56,z,M.wood);
  box(1.22,.18,1.3,-1.7,2.34,-.71,M.timber);roof(1.57,1.53,.79,-1.7,3.08,-.71);
  for(const x of [-2.18,-1.22])box(.1,.13,1.2,x,2.77,-.71,M.wood);
  box(1.06,.13,.1,-1.7,2.77,-.07,M.wood);
  for(const x of [-2.03,-1.7,-1.37])box(.06,.42,.06,x,2.56,-.07,M.timber);
  beam(-2.12,.42,-.21,-1.28,2.2,-.21,.065);
  box(.64,.49,.54,1.94,.38,.24,M.timber);for(let i=0;i<3;i++){beam(1.76+i*.15,.52,.24,1.68+i*.18,1.36,.24,.026);box(.08,.16,.055,1.68+i*.18,1.28,.24,M.trim);}
 }else{
  // A chunky wooden horse-head crest gives the gable a unique silhouette.
  // The horseshoe moves onto the stall so neither symbol hides the entrance.
  const horse=new T.Shape();horse.moveTo(-.34,-.59);horse.quadraticCurveTo(-.54,-.12,-.33,.38);horse.lineTo(-.41,.73);horse.lineTo(-.13,.6);horse.lineTo(.04,.77);horse.lineTo(.16,.44);horse.lineTo(.48,.23);horse.quadraticCurveTo(.67,-.04,.43,-.19);horse.lineTo(.17,-.12);horse.lineTo(.21,-.59);horse.closePath();
  mesh(new T.ExtrudeGeometry(horse,{depth:.095,bevelEnabled:true,bevelSize:.04,bevelThickness:.025,bevelSegments:2,curveSegments:8}),M.gold,0,2.74,1.27);
  ball(.12,3.05,1.41,.051,.06,.025,M.dark);box(.075,.57,.04,-.3,2.68,1.41,M.wood);
  // Two open side stalls sit under one deep blue canopy; a squat louvred
  // cupola rises above the ridge without hiding the horse-head crest.
  roof(1.5,2.65,.55,2.13,1.45,-.34);
  for(const z of [-1.47,-.3,.8]){box(.14,1.33,.14,2.8,.81,z,M.wood);box(1.12,.48,.12,2.15,.47,z,M.timber);}
  for(const z of [-.95,.28]){ball(2.13,.42,z,.47,.24,.41,M.wheat);box(.08,.43,.8,2.13,.44,z,M.timber);}
  const sign=mesh(new T.TorusGeometry(.26,.07,7,24,Math.PI*1.55),M.gold,2.14,1.74,1.13);sign.rotation.z=-Math.PI*.275;
  box(.92,.65,.76,0,3.61,-.58,M.cream);roof(1.23,1.03,.48,0,3.92,-.58);
  const loft=arch(0,-.175,.54,.41,M.wood);loft.position.y=3.49;
  for(const x of [-.2,0,.2])box(.045,.35,.045,x,3.67,-.055,M.timber);
  for(const [x,z]of [[-1.43,2.45],[1.26,2.35]]){
   const pony=new T.Group();pony.position.set(x,0,z);root.add(pony);
   const part=(px,py,pz,sx,sy,sz,m)=>{const o=new T.Mesh(new T.SphereGeometry(1,12,8),m);o.position.set(px,py,pz);o.scale.set(sx,sy,sz);pony.add(o);addStorybookOutline(o,.02);o.castShadow=true;};
   part(0,.65,0,.28,.3,.42,M.timber);for(const side of [-1,1])for(const pz of [-.24,.24])part(side*.18,.29,pz,.08,.25,.1,M.wood);
   part(0,1,.29,.19,.35,.19,M.timber);part(0,1.2,.45,.23,.22,.26,M.timber);part(0,1.12,.65,.2,.12,.14,M.wood);
   for(const side of [-1,1]){part(side*.14,1.48,.38,.055,.17,.065,M.timber);part(side*.17,1.27,.6,.025,.03,.018,M.dark);}
   part(0,.85,-.03,.31,.11,.33,M.blue);part(0,.95,-.03,.22,.07,.23,M.wood);part(0,1.4,.36,.2,.1,.12,M.wood);
   part(0,1.09,.1,.13,.34,.13,M.wood);part(0,.58,-.43,.1,.28,.12,M.wood);
   for(const side of [-1,1])part(side*.12,1.5,.4,.026,.1,.035,M.wheat);
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

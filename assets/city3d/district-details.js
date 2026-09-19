import * as T from './vendor/three.module.js';
import { storybookMaterials, addStorybookOutline } from './storybook-style.js?v=storybook5';

const materials={...storybookMaterials,fruit:storybookMaterials.orange};
const archShape=new T.Shape();
archShape.moveTo(-.5,-.5);archShape.lineTo(.5,-.5);archShape.lineTo(.5,0);archShape.absarc(0,0,.5,0,Math.PI);archShape.closePath();
const geometries={box:new T.BoxGeometry(1,1,1),ball:new T.SphereGeometry(1,16,10),cylinder:new T.CylinderGeometry(1,1,1,24),cone:new T.ConeGeometry(1,1,24),ring:new T.TorusGeometry(1,.065,8,32),arch:new T.ExtrudeGeometry(archShape,{depth:1,bevelEnabled:false,curveSegments:10})};

// Small architectural parts are instanced per building and material. They remain
// selectable with their parent without adding one draw call for every tile.
export function detailDistrict(root,code){
 const batches=new Map(),dummy=new T.Object3D();
 function part(shape,color,x,y,z,sx,sy,sz,rx=0,ry=0,rz=0){
   const key=shape+color;if(!batches.has(key))batches.set(key,{shape,color,matrices:[]});
   dummy.position.set(x,y,z);dummy.scale.set(sx,sy,sz);dummy.rotation.set(rx,ry,rz);dummy.updateMatrix();batches.get(key).matrices.push(dummy.matrix.clone());
 }
 const box=(color,x,y,z,w,h,d,rz=0)=>part('box',color,x,y,z,w,h,d,0,0,rz);
 const cyl=(color,x,y,z,r,h)=>part('cylinder',color,x,y,z,r,h,r);
 function stairs(x,z,w=1.7,count=4){for(let i=0;i<count;i++)box('trim',x,.07+(count-i)*.065,z+i*.24,w,.12+(count-i)*.13,.3);}
 function window(x,y,z,w=.4,h=.68){
   part('arch','dark',x,y,z,w+.105,h+.115,.06);
   part('arch','glow',x,y,z+.065,w,h,.025);
   box('stone',x,y-h/2-.075,z+.04,w+.16,.09,.17);
 }
 function crate(x,y,z,s=.65){box('timber',x,y,z,s,s,s);for(const d of [-1,1]){box('wood',x+d*s*.39,y,z+.01,.07,s+.04,s+.04);box('wood',x,y+d*s*.39,z+.01,s+.04,.065,s+.04);}box('wood',x,y,z+s/2+.025,s*.95,.07,.06,.75);}
 function barrel(x,z){cyl('timber',x,.55,z,.29,.85);for(const y of [.25,.65,.92])cyl('dark',x,y,z,.305,.05);}
 function planter(x,z,w=1.3){box('cream',x,.3,z,w,.42,.55);for(let i=0;i<5;i++)part('ball','leaf',x-w*.38+i*w*.19,.6,z,.23,.29,.25);for(let i=0;i<5;i++)part('ball',i%2?'gold':'red',x-w*.35+i*w*.18,.84,z,.07,.075,.07);}
 function lamp(x,z){cyl('wood',x,1,z,.055,1.8);box('gold',x,1.93,z,.32,.09,.32);box('trim',x,1.73,z,.19,.32,.19);part('cone','blue',x,2.07,z,.25,.23,.25);}
 function tiles(w,d,base,h,color,x=0,z=0){
   const slope=Math.atan2(h,w/2),length=Math.hypot(w/2,h),rows=3;
   for(const side of [-1,1])for(let row=0;row<rows;row++){
     const f=(row+.5)/rows;box(color,x+side*w/2*f,base+h*(1-f)+.075,z,length/rows*.98,.045,d+.025,-side*slope);
   }
   box('dark',x,base+h+.105,z,.075,.075,d+.13);
 }
 function masonry(w,z,h,x=0){for(let row=0;row<Math.floor(h/.35);row++)for(let col=0;col<Math.floor(w/.46);col++){
   if((row+col)%3===0)box(row%2?'trim':'stone',x-w/2+.24+col*.46,.4+row*.35,z,.41,.22,.065);
 }}
 function fence(x,z,w){for(let i=0;i<=Math.ceil(w/.55);i++)box('timber',x-w/2+i*w/Math.ceil(w/.55),.55,z,.1,.9,.1);for(const y of [.36,.77])box('wood',x,y,z,w,.085,.1);}
 function portico(x,z,w,h=1.8){for(const side of [-1,1]){cyl('trim',x+side*w*.43,h/2,z,.13,h);cyl('gold',x+side*w*.43,h-.05,z,.18,.14);cyl('cream',x+side*w*.43,.22,z,.23,.22);}box('trim',x,h+.12,z,w+.4,.25,.75);}

 if(code==='hospital'){
   box('cream',0,.09,1,6,.16,5);tiles(3.2,2.4,1.85,.85,'teal');
   for(const side of [-1,1]){window(side*.85,1.16,1.1,.4,.65);portico(side*1.85,1.65,1.4,1.6);planter(side*2.2,2.7);}
   stairs(0,2.05,1.6);cyl('cream',0,.35,3.1,.6,.4);cyl('teal',0,.59,3.1,.46,.1);
   box('teal',0,1.06,3.1,.19,.8,.16);box('teal',0,1.14,3.1,.62,.18,.16);
   lamp(-2.7,2.25);lamp(2.7,2.25);
 }else if(code==='storage'){
   box('stone',0,.07,.2,5.8,.13,4.8);tiles(3.6,2.1,1.85,.85,'orange');
   for(const x of [-.9,.9]){for(const y of [.7,1.55,2.4,3.12])cyl('gold',x,y,-1.2,.66,.1);for(let i=0;i<9;i++)box('timber',x+.69,.4+i*.32,-1.2,.1,.07,.72);for(const z of [-1.58,-.82])box('wood',x+.69,1.75,z,.07,2.9,.07);}
   for(const x of [-.43,-.22,0,.22,.43])box('wood',x,.75,1.08,.035,1.1,.035);
   portico(0,1.65,2.8,1.5);for(let i=0;i<6;i++)box(i%2?'trim':'teal',-1.35+i*.54,1.82,1.65,.54,.11,1.15);
   crate(-2.1,.48,1);crate(-2.1,1.15,1);barrel(-1.75,2);barrel(1.7,2);stairs(0,2.3,1.7,2);
 }else if(code==='treasure_house'){
   box('cream',0,.09,.25,5.9,.18,4.9);cyl('trim',0,.32,0,1.45,.24);
   for(let i=0;i<12;i++){const a=i/12*Math.PI*2;part('box','trim',Math.sin(a)*1.17,1.1,Math.cos(a)*1.17,.18,1.6,.16,0,a,0);}
   for(const y of [.55,1.8,2.1])cyl('gold',0,y,0,1.2,.08);
   part('ring','gold',0,2.1,0,1.31,1.31,1.31,Math.PI/2);
   cyl('gold',0,3.5,0,.12,.35);part('ball','gold',0,3.75,0,.2,.2,.2);
   for(const side of [-1,1]){window(side*1.65,1.05,.64,.35,.6);planter(side*2.1,1.85);}
   portico(0,1.55,1.65,1.85);stairs(0,1.95,2.1,5);lamp(-1.55,2.65);lamp(1.55,2.65);
 }else if(code==='hall_of_alliance'){
   box('cream',0,.08,.4,5.8,.16,5.2);tiles(4,2.9,2.3,1.5,'blue');masonry(3.6,1.22,2);
   for(const side of [-1,1]){window(side*.9,1.45,1.28,.5,.9);box('cream',side*2.15,1,0,1.05,1.8,2.5);box('trim',side*2.15,1.98,0,1.2,.2,2.65);for(let i=0;i<4;i++)box('trim',side*2.15,2.25,-1.03+i*.69,1.16,.4,.32);window(side*2.15,1.2,1.29,.37,.65);planter(side*2.25,2.25);}
   portico(0,1.75,2.9,2.4);stairs(0,2.1,2.6,5);lamp(-1.8,3);lamp(1.8,3);
 }else if(code==='trading_post'){
   box('cream',0,.07,.5,6.4,.14,5.8);
   // Alternating canvas gores read as a market pavilion from every direction.
   for(let i=0;i<12;i++){
     const geo=new T.ConeGeometry(1.68,1.42,4,1,true,i*Math.PI/6,Math.PI/6),m=new T.Mesh(geo,materials[i%2?'trim':'red']);m.position.set(0,2.3,0);m.castShadow=true;root.add(m);
   }
   for(let i=0;i<8;i++){const a=i/8*Math.PI*2;cyl('timber',Math.sin(a)*1.18,1.02,Math.cos(a)*1.18,.07,1.7);}
   for(const side of [-1,1]){
     for(let i=0;i<5;i++)box(i%2?'trim':'teal',side*1.8-.6+i*.3,1.7,1.45,.3,.12,1.3);
     for(const x of [-.65,.65])cyl('timber',side*1.8+x,.94,1.8,.045,1.5);
     box('timber',side*1.8,.65,1.5,1.45,.7,.9);
     for(let i=0;i<12;i++)part('ball',i%3?'fruit':'leaf',side*1.8-.48+(i%4)*.3,1.05,1.2+Math.floor(i/4)*.25,.13,.15,.13);
     barrel(side*2.6,-.4);crate(side*2.4,.5,-1.4);
   }
   stairs(0,2,1.2,2);
 }else if(code==='watch_tower'){
   // Only the main silhouette pieces need outlines. Posts, window inserts and
   // architectural accents remain in their normal instanced material batches.
   for(const child of [...root.children])root.remove(child);
   function outlined(geometry,color,x,y,z){
     const mesh=new T.Mesh(geometry,materials[color]);mesh.position.set(x,y,z);mesh.castShadow=mesh.receiveShadow=true;root.add(mesh);addStorybookOutline(mesh,.03);return mesh;
   }
   outlined(new T.CylinderGeometry(1.25,1.35,.24,32),'stone',0,.15,0);
   outlined(new T.CylinderGeometry(.79,.88,3,32),'cream',0,1.76,0);
   for(const y of [.43,1.36,2.38])cyl('stone',0,y,0,.88,.085);
   window(-.31,1.95,.775,.2,.59);window(.31,1.95,.775,.2,.59);
   outlined(new T.CylinderGeometry(1.06,1.09,.23,32),'trim',0,3.27,0);
   for(let i=0;i<4;i++){const a=i*Math.PI/2+Math.PI/4;cyl('wood',Math.sin(a)*.79,3.91,Math.cos(a)*.79,.105,1.18);}
   const bellProfile=[[0,0],[.46,0],[.46,.07],[.32,.15],[.27,.38],[.14,.52],[0,.54]].map(([r,y])=>new T.Vector2(r,y));
   outlined(new T.LatheGeometry(bellProfile,24),'gold',0,3.64,0);
   part('ball','dark',0,3.61,0,.095,.14,.095);cyl('wood',0,4.26,0,.045,.2);
   outlined(new T.CylinderGeometry(1.09,1.04,.17,32),'trim',0,4.54,0);
   const roofProfile=[[0,0],[1.28,0],[1.32,.08],[1.07,.27],[.72,.57],[.28,.94],[.055,1.1],[0,1.14]].map(([r,y])=>new T.Vector2(r,y));
   outlined(new T.LatheGeometry(roofProfile,32),'teal',0,4.64,0);
   part('ball','gold',0,5.84,0,.08,.1,.08);
   for(const [x,y,z] of [[-.42,.82,.76],[.46,2.69,.69]])box('patch',x,y,z,.22,.12,.07);
   stairs(0,1,1.5,4);window(0,.93,.866,.32,.56);
 }else if(code==='quarry'){
   box('stone',0,.08,.5,5.7,.15,5.1);
   for(let i=0;i<7;i++)box('timber',-1.9,.17+i*.24,.95-i*.29,.7,.22,.35);
   for(let i=0;i<5;i++)crate(2.05,.45,-1.2+i*.43,.4);
   for(const x of [-1.65,1.6])fence(x,-1.75,1.4);
   for(let i=0;i<4;i++)box('trim',-.7+i*.5,.32,2.3,.43,.3,.5);
   for(const y of [1.5,2.25,3])box('wood',1.55,y,0,.35,.07,.33);
   part('ring','dark',1.55,3.35,.17,.28,.28,.28);part('ring','dark',-.8,3.35,.17,.2,.2,.2);
   box('wood',-.8,1.42,0,.7,.1,.7);lamp(-2.15,2.3);
 }else if(code==='wall'){
   for(const side of [-1,1]){for(const y of [.4,1.2,2.05])cyl('trim',side*3.62,y,0,.67,.1);window(side*3.62,1.9,.64,.2,.6);lamp(side*2.65,.95);}
   box('wood',0,2.4,0,4.9,.16,.13);for(let i=0;i<11;i++)box('dark',-2.2+i*.44,2.3,0,.045,.3,.045);
 }
 for(const {shape,color,matrices} of batches.values()){
   const batch=new T.InstancedMesh(geometries[shape],materials[color],matrices.length);
   matrices.forEach((matrix,i)=>batch.setMatrixAt(i,matrix));batch.computeBoundingSphere();batch.castShadow=batch.receiveShadow=true;root.add(batch);
 }
}

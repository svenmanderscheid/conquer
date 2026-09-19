import * as T from './vendor/three.module.js';
import { storybookMaterials as paints, addStorybookOutline } from './storybook-style.js?v=storybook5';

// Large, readable shapes follow the illustrated village. The scene owns the
// building root, its position, selection code and projected menu anchors.
export function buildStorybookCore(root,code){
 if(code!=='academy'&&code!=='barrack')return root;
 const batches=new Map(),dummy=new T.Object3D();
 const units={box:new T.BoxGeometry(1,1,1),ball:new T.SphereGeometry(1,12,8),cylinder:new T.CylinderGeometry(1,1,1,16),cone:new T.ConeGeometry(1,1,12)};
 function add(geometry,color,x=0,y=0,z=0,outline=true,owner=root){
  const mesh=new T.Mesh(geometry,paints[color]);mesh.position.set(x,y,z);mesh.castShadow=mesh.receiveShadow=true;owner.add(mesh);
  if(outline)addStorybookOutline(mesh,.025);return mesh;
 }
 function stamp(shape,color,x,y,z,sx,sy,sz,rx=0,ry=0,rz=0){
  const key=shape+color;if(!batches.has(key))batches.set(key,{shape,color,matrices:[]});
  dummy.position.set(x,y,z);dummy.scale.set(sx,sy,sz);dummy.rotation.set(rx,ry,rz);dummy.updateMatrix();batches.get(key).matrices.push(dummy.matrix.clone());
 }
 function roundedBox(w,h,d,x,y,z,color='cream',radius=.09){
  const r=Math.min(radius,w/3,h/3),shape=new T.Shape();
  shape.moveTo(-w/2+r,-h/2);shape.lineTo(w/2-r,-h/2);shape.quadraticCurveTo(w/2,-h/2,w/2,-h/2+r);
  shape.lineTo(w/2,h/2-r);shape.quadraticCurveTo(w/2,h/2,w/2-r,h/2);shape.lineTo(-w/2+r,h/2);
  shape.quadraticCurveTo(-w/2,h/2,-w/2,h/2-r);shape.lineTo(-w/2,-h/2+r);shape.quadraticCurveTo(-w/2,-h/2,-w/2+r,-h/2);
  const geometry=new T.ExtrudeGeometry(shape,{depth:Math.max(.01,d-.05),bevelEnabled:true,bevelSize:.018,bevelThickness:.025,bevelSegments:2,curveSegments:5});
  geometry.translate(0,0,-(d-.05)/2);return add(geometry,color,x,y,z);
 }
 function arch(w,h,depth,x,y,z,color='glow',outline=true){
  const r=w/2,shape=new T.Shape();shape.moveTo(-r,0);shape.lineTo(r,0);shape.lineTo(r,h-r);shape.absarc(0,h-r,r,0,Math.PI);shape.closePath();
  const geometry=new T.ExtrudeGeometry(shape,{depth,bevelEnabled:false,curveSegments:12});return add(geometry,color,x,y,z,outline);
 }
 function window(x,y,z,w=.32,h=.56){
  arch(w+.12,h+.12,.055,x,y-.045,z,'dark',false);arch(w,h,.025,x,y,z+.064,'glow',false);
 }
 function door(x,z,w=1,h=1.35){
  arch(w+.26,h+.15,.09,x,.12,z,'patch');arch(w,h,.065,x,.15,z+.11,'wood');
  for(const dx of [-.3,-.15,0,.15,.3]){
   const px=dx*w,top=h-w/2+Math.sqrt(Math.max(0,w*w/4-px*px));stamp('box','timber',x+px,.15+top/2,z+.182,.022,top-.06,.025);
  }
  stamp('box','dark',x,.67,z+.2,w*.91,.06,.05);stamp('ball','gold',x+w*.25,.71,z+.235,.035,.035,.025);
 }
 function cylinder(r,h,x,y,z,color='cream',outline=true){return add(new T.CylinderGeometry(r*.985,r,h,32),color,x,y,z,outline);}
 function pointedRoof(radius,height,x,y,z,color,bend=0){
  const rings=[[0,1,0],[.05,1.045,0],[.2,.8,0],[.43,.54,.1],[.7,.3,.45],[.91,.11,.83],[1,.015,1]],segments=32,positions=[],indices=[];
  for(const [v,r,shift] of rings)for(let i=0;i<segments;i++){
   const angle=i/segments*Math.PI*2;positions.push(Math.sin(angle)*radius*r+bend*shift,height*v,Math.cos(angle)*radius*r);
  }
  for(let row=0;row<rings.length-1;row++)for(let i=0;i<segments;i++){
   const a=row*segments+i,b=row*segments+(i+1)%segments,c=a+segments,d=b+segments;indices.push(a,b,c,b,d,c);
  }
  const geometry=new T.BufferGeometry();geometry.setAttribute('position',new T.Float32BufferAttribute(positions,3));geometry.setIndex(indices);geometry.computeVertexNormals();
  add(geometry,color,x,y,z);
  for(const [v,r,shift] of [rings[2],rings[3]]){
   const band=add(new T.TorusGeometry(radius*r+.014,.024,5,32),color==='purple'?'purpleDark':'redDark',x+bend*shift,y+height*v,z,false);band.rotation.x=Math.PI/2;
  }
 }
 function hipRoof(w,d,h,x,y,z){
  const profile=[[0,1,1],[.08,1.025,1.025],[.4,.9,.83],[.75,.73,.46],[1,.56,.075]],count=40,positions=[],indices=[];
  for(const [v,sw,sd] of profile)for(let i=0;i<count;i++){
   const a=i/count*Math.PI*2,c=Math.cos(a),s=Math.sin(a);positions.push(Math.sign(c)*Math.sqrt(Math.abs(c))*w*.5*sw,v*h,Math.sign(s)*Math.sqrt(Math.abs(s))*d*.5*sd);
  }
  for(let row=0;row<profile.length-1;row++)for(let i=0;i<count;i++){
   const a=row*count+i,b=row*count+(i+1)%count,c=a+count,d=b+count;indices.push(a,c,b,b,c,d);
  }
  const top=positions.length/3;positions.push(0,h,0);for(let i=0;i<count;i++)indices.push(top,(profile.length-1)*count+(i+1)%count,(profile.length-1)*count+i);
  const geometry=new T.BufferGeometry();geometry.setAttribute('position',new T.Float32BufferAttribute(positions,3));geometry.setIndex(indices);geometry.computeVertexNormals();add(geometry,'red',x,y,z);
  const [v,sw,sd]=profile[2],points=[];
  for(let i=0;i<=count;i++){const a=i/count*Math.PI*2,c=Math.cos(a),s=Math.sin(a);points.push(new T.Vector3(Math.sign(c)*Math.sqrt(Math.abs(c))*w*.5*sw,v*h+.01,Math.sign(s)*Math.sqrt(Math.abs(s))*d*.5*sd));}
  add(new T.TubeGeometry(new T.CatmullRomCurve3(points),64,.018,5,false),'redDark',x,y,z,false);
 }
 function shrub(x,z,size=.42){
  for(let i=0;i<3;i++)stamp('ball',i===1?'leafLight':'leaf',x+(i-1)*size*.42,.22+size*.4+(i===1?.1:0),z+(i%2)*.08,size*.62,size*.75,size*.6);
 }
 function flag(x,y,z,color){
  cylinder(.036,.57,x,y+.28,z,'wood');const shape=new T.Shape();shape.moveTo(0,.48);shape.bezierCurveTo(.2,.53,.28,.33,.55,.4);shape.quadraticCurveTo(.38,.15,0,.24);shape.closePath();
  add(new T.ExtrudeGeometry(shape,{depth:.025,bevelEnabled:false,curveSegments:8}),color,x,y,z);
 }
 function masonry(radius){
  for(const [angle,y] of [[-.8,.5],[.8,.95],[1.8,1.6],[-1.6,1.95],[2.7,.6]]){
   const stone=roundedBox(.28,.12,.042,Math.sin(angle)*radius,y,Math.cos(angle)*radius,'patch',.04);stone.rotation.y=angle;
  }
 }
 if(code==='academy'){
  cylinder(1.14,.21,0,.17,0,'stone');cylinder(.97,2.36,0,1.38,0);cylinder(1.01,.12,0,2.54,0,'trim');
  pointedRoof(1.29,1.87,0,2.58,0,'purple',-.19);masonry(.98);
  for(const side of [-1,1]){
   roundedBox(1.08,1.43,1.3,side*1.21,.86,.05,'cream',.12);
   pointedRoof(.86,.81,side*1.21,1.59,.05,'purple',side*.04);window(side*1.24,.72,.73,.3,.56);
   shrub(side*1.82,1.09,.49);
  }
  door(-.16,.955,.84,1.4);window(.4,1.68,.905,.29,.55);window(-.65,1.64,.735,.25,.54);
  roundedBox(1.32,.15,.49,-.16,.13,1.51,'stone');roundedBox(1.07,.14,.37,-.16,.27,1.29,'trim');
  // A shallow reading balcony makes the front feel like an active academy
  // while remaining inside the existing stair/entrance footprint.
  roundedBox(1.18,.12,.35,-.12,1.55,1.05,'trim',.05);
  roundedBox(1.01,.075,.12,-.12,1.72,1.19,'wood',.03);
  for(const x of [-.49,-.16,.17,.5]){
   stamp('box','wood',x,1.9,1.19,.045,.35,.045);
   stamp('ball','gold',x,2.1,1.19,.038,.038,.038);
  }
  // A broad, framed spell-window reads as a magical observatory even from
  // the isometric camera.  The small inner star keeps the surface calm.
  const runeFrame=cylinder(.31,.044,-.12,2.3,.988,'gold',false);runeFrame.rotation.x=Math.PI/2;
  const runeFace=cylinder(.23,.047,-.12,2.3,1.013,'purple',false);runeFace.rotation.x=Math.PI/2;
  for(let i=0;i<4;i++){
   stamp('box','glow',-.12,2.3,1.045,.045,.16,.02,0,0,i*Math.PI/2);
  }
  // A proper crescent moon, visible on the banner from the fixed camera.
  const banner=new T.Shape();banner.moveTo(-.36,0);banner.lineTo(.36,0);banner.lineTo(.33,-.91);banner.lineTo(0,-1.12);banner.lineTo(-.33,-.91);banner.closePath();
  add(new T.ExtrudeGeometry(banner,{depth:.035,bevelEnabled:false}),'purple',.4,2.64,1.02);
  roundedBox(.89,.09,.11,.4,2.68,1.04,'wood',.035);
  const moon=new T.Shape();moon.moveTo(.12,.23);moon.bezierCurveTo(-.42,.3,-.42,-.33,.14,-.22);moon.bezierCurveTo(-.1,-.16,-.16,.12,.12,.23);moon.closePath();
  add(new T.ExtrudeGeometry(moon,{depth:.02,bevelEnabled:false,curveSegments:14}),'glow',.45,2.13,1.068,false);
  // The crooked staff is a magical landmark, without an orbital mechanism.
  const staffCurve=new T.CatmullRomCurve3([new T.Vector3(1.89,.3,.93),new T.Vector3(1.89,1.52,.93),new T.Vector3(2.05,1.86,.93),new T.Vector3(1.85,2.16,.93),new T.Vector3(1.75,2.41,.93)]);
  add(new T.TubeGeometry(staffCurve,20,.09,8,false),'timber');add(new T.SphereGeometry(.35,16,12),'blueLight',1.79,2.55,.94);
  add(new T.SphereGeometry(.095,10,8),'trim',1.71,2.68,1.2,false);
  // A large open spellbook communicates research at normal zoom. Its two
  // cream pages form a shallow V and stay beside, rather than across, the door.
  roundedBox(.49,.72,.39,-1.47,.52,1.42,'timber',.06);
  roundedBox(.81,.1,.65,-1.47,.92,1.42,'wood',.035).rotation.x=.22;
  for(const side of [-1,1]){
   const cover=roundedBox(.58,.075,.75,-1.47+side*.29,1.01,1.42,'purple',.025);cover.rotation.set(.22,0,side*.17);
   const page=roundedBox(.52,.095,.68,-1.47+side*.28,1.085,1.42,'trim',.025);page.rotation.set(.22,0,side*.17);
   for(const z of [1.31,1.46])stamp('box','timber',-1.47+side*.28,1.143-(z-1.42)*.22,z,.29,.014,.025,.22,0,side*.17);
  }
  stamp('box','gold',-1.47,1.092,1.46,.07,.035,.77,.22,0,0);
  shrub(-.97,-1.09,.45);shrub(1.29,-.88,.42);
 }else{
  roundedBox(3.42,1.64,2.34,0,.99,0,'cream',.12);
  roundedBox(3.57,.19,2.43,0,.23,0,'stone',.12);
  for(const side of [-1,1]){
   cylinder(.32,2.43,side*1.34,1.46,-.87);pointedRoof(.45,.54,side*1.34,2.68,-.87,'red');
   flag(side*1.34,3.02,-.87,'red');window(side*1.34,2.1,-.535,.15,.32);
  }
  hipRoof(3.97,2.96,1.14,0,1.82,0);
  // Broad front porch and red heraldry give the entrance the same silhouette
  // as the artwork while keeping the full training court clickable.
  roundedBox(1.39,1.77,.3,0,1.12,1.22,'trim',.11);door(0,1.403,.87,1.34);
  const canopyShape=new T.Shape();canopyShape.moveTo(-.8,0);canopyShape.bezierCurveTo(-.7,.32,-.61,.66,-.48,.71);canopyShape.lineTo(.5,.71);canopyShape.bezierCurveTo(.68,.57,.7,.16,.82,0);canopyShape.quadraticCurveTo(.3,-.1,0,.05);canopyShape.quadraticCurveTo(-.3,.12,-.8,0);
  const canopyGeo=new T.ExtrudeGeometry(canopyShape,{depth:.82,bevelEnabled:true,bevelThickness:.035,bevelSize:.025,bevelSegments:2,curveSegments:10});
  add(canopyGeo,'red',0,2.02,.77);
  const shield=new T.Shape();shield.moveTo(-.35,.4);shield.lineTo(.35,.4);shield.lineTo(.32,-.15);shield.quadraticCurveTo(.14,-.41,0,-.46);shield.quadraticCurveTo(-.14,-.41,-.32,-.15);shield.closePath();
  const crest=add(new T.ExtrudeGeometry(shield,{depth:.06,bevelEnabled:false,curveSegments:8}),'redDark',0,2.42,1.635);crest.scale.set(1.55,1.55,1);
  for(const side of [-1,1]){
   const sword=new T.Group();sword.position.set(side*.015,2.46,1.711);sword.rotation.z=side*.75;root.add(sword);
   const blade=new T.Shape();blade.moveTo(-.067,-.35);blade.lineTo(.067,-.35);blade.lineTo(.067,.37);blade.lineTo(0,.56);blade.lineTo(-.067,.37);blade.closePath();
   add(new T.ExtrudeGeometry(blade,{depth:.015,bevelEnabled:false}),'trim',0,0,0,false,sword);
   const guard=new T.Mesh(new T.BoxGeometry(.37,.075,.04),paints.gold);guard.position.set(0,-.29,.016);sword.add(guard);
   const handle=new T.Mesh(new T.BoxGeometry(.075,.21,.045),paints.gold);handle.position.set(0,-.44,.016);sword.add(handle);
  }
  // The narrow upper gallery and two large shields make this a fortified
  // training house, not merely a red-roofed cottage.  They sit on the façade
  // and leave the established practice court and path clear.
  roundedBox(2.12,.12,.31,0,1.56,1.36,'wood',.04);
  for(const x of [-.91,-.45,0,.45,.91]){
   stamp('box','timber',x,1.78,1.49,.05,.37,.05);
   stamp('ball','gold',x,1.99,1.49,.038,.038,.038);
  }
  for(const side of [-1,1]){
   const guardShield=add(new T.ExtrudeGeometry(shield,{depth:.04,bevelEnabled:false,curveSegments:8}),'red',side*.85,1.78,1.54);
   guardShield.scale.setScalar(.58);
   const boss=cylinder(.052,.055,side*.85,1.76,1.578,'gold',false);boss.rotation.x=Math.PI/2;
  }
  for(const side of [-1,1]){window(side*1.19,.79,1.183,.29,.57);shrub(side*1.79,-1.21,.37);}
  for(const [x,y,z] of [[-1.4,.45,1.194],[1.45,1.53,1.19],[-.97,1.42,1.194]])stamp('box','patch',x,y,z,.22,.115,.04);
  const court=add(new T.CylinderGeometry(1,1,.07,40),'timber',0,.115,2.62,false);court.scale.set(2.49,1,1.65);court.castShadow=false;
  for(const side of [-1,1]){
   for(let i=0;i<7;i++){
    const z=.17+i*.43;stamp('cylinder','wood',side*2.4,.56,z,.075,.84,.075);stamp('cone','wheat',side*2.4,1.035,z,.075,.18,.075);
   }
   stamp('box','timber',side*2.4,.54,1.47,.1,.09,2.83);
  }
  // Shield and sword practice identifies infantry; round archery targets
  // belong to the green-roofed range, which keeps the districts easy to tell apart.
  function trainingShield(x,z){
   const stand=roundedBox(.1,.99,.11,x,.61,z,'wood',.04);stand.rotation.z=.09;
   add(new T.ExtrudeGeometry(shield,{depth:.06,bevelEnabled:false,curveSegments:8}),'red',x,1.14,z);
   stamp('box','gold',x,1.15,z+.08,.075,.59,.03);
   stamp('box','gold',x,1.25,z+.09,.49,.065,.03);
   stamp('ball','gold',x,1.2,z+.14,.11,.11,.055);
  }
  trainingShield(1.41,3.51);trainingShield(2.04,2.71);
  for(const [x,z] of [[-1.46,3.54],[-.64,3.82]]){
   stamp('cylinder','wood',x,.5,z,.067,.79,.067);stamp('cylinder','timber',x,.85,z,.15,.34,.15);
   stamp('cylinder','timber',x,1.13,z,.17,.19,.17);stamp('box','wood',x,.82,z,.66,.11,.13);
  }
  for(let i=0;i<3;i++){
   const x=.67+i*.2;stamp('cylinder','wood',x,.61,2.85,.027,.96,.027);stamp('cone','stone',x,1.2,2.85,.072,.23,.072);
  }
  stamp('box','timber',.87,.3,2.85,.81,.14,.46);stamp('box','wood',.87,.77,2.85,.73,.06,.08);
  // A compact armour rack gives the court a robust, functional anchor.  Its
  // repeated shafts and caps stay in the existing instanced batches.
  for(const x of [-1.81,-1.55,-1.29]){
   stamp('cylinder','wood',x,.58,2.65,.034,.94,.034);
   stamp('cone','trim',x,1.15,2.65,.075,.23,.075);
  }
  stamp('box','timber',-1.55,.31,2.65,.8,.13,.36);
  stamp('box','wood',-1.55,.76,2.65,.7,.06,.08);
  roundedBox(.42,.4,.42,-1.94,.35,1.93,'timber',.055);stamp('box','wood',-1.94,.35,2.154,.035,.36,.025);
 }
 for(const {shape,color,matrices} of batches.values()){
  const mesh=new T.InstancedMesh(units[shape],paints[color],matrices.length);matrices.forEach((matrix,i)=>mesh.setMatrixAt(i,matrix));mesh.computeBoundingSphere();mesh.castShadow=mesh.receiveShadow=true;root.add(mesh);
 }
 return root;
}

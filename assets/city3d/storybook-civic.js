import * as T from './vendor/three.module.js';
import { storybookMaterials as m, addStorybookOutline } from './storybook-style.js?v=storybook5';

// The civic buildings are intentionally self-contained.  buildDistricts can call
// this before its legacy cases; each model keeps the familiar 4.3 by 3.8 plot
// while making its public purpose legible at the usual city camera distance.
const geometries={
 box:new T.BoxGeometry(1,1,1),
 cylinder:new T.CylinderGeometry(1,1,1,14),
 ball:new T.SphereGeometry(1,12,8),
 ring:new T.TorusGeometry(1,.065,8,20)
};

function archShape(width,height){
 const r=width/2,shape=new T.Shape();
 shape.moveTo(-r,0);shape.lineTo(r,0);shape.lineTo(r,height-r);
 shape.absarc(0,height-r,r,0,Math.PI,false);shape.closePath();
 return shape;
}

function roofShape(width,eave,rise,thickness=.13){
 const half=width/2,shape=new T.Shape();
 shape.moveTo(-half,eave);shape.bezierCurveTo(-half*.72,eave,-half*.35,eave+rise,0,eave+rise);
 shape.bezierCurveTo(half*.35,eave+rise,half*.72,eave,half,eave);shape.lineTo(half,eave-thickness);
 shape.bezierCurveTo(half*.68,eave-thickness,half*.31,eave+rise-thickness,0,eave+rise-thickness);
 shape.bezierCurveTo(-half*.31,eave+rise-thickness,-half*.68,eave-thickness,-half,eave-thickness);shape.closePath();
 return shape;
}

export function buildStorybookCivic(root,code){
 if(!['hospital','storage','treasure_house','hall_of_alliance','trading_post','watch_tower'].includes(code))return false;
 const batches=new Map(),dummy=new T.Object3D();
 const add=(geometry,color,x=0,y=0,z=0,outline=.025)=>{
  const mesh=new T.Mesh(geometry,m[color]);mesh.position.set(x,y,z);mesh.castShadow=mesh.receiveShadow=true;
  if(outline)addStorybookOutline(mesh,outline);root.add(mesh);return mesh;
 };
 const box=(color,x,y,z,w,h,d,outline=.022)=>add(new T.BoxGeometry(w,h,d),color,x,y,z,outline);
 const cyl=(color,x,y,z,r,h,outline=.022)=>add(new T.CylinderGeometry(r,r,h,16),color,x,y,z,outline);
 const ball=(color,x,y,z,sx,sy,sz,outline=.018)=>{const o=add(geometries.ball,color,x,y,z,outline);o.scale.set(sx,sy,sz);return o;};
 const part=(shape,color,x,y,z,sx,sy,sz,rx=0,ry=0,rz=0)=>{
  const key=shape+color;if(!batches.has(key))batches.set(key,{shape,color,matrices:[]});
  dummy.position.set(x,y,z);dummy.scale.set(sx,sy,sz);dummy.rotation.set(rx,ry,rz);dummy.updateMatrix();batches.get(key).matrices.push(dummy.matrix.clone());
 };
 const arch=(color,x,y,z,w,h,depth=.08,outline=.02)=>add(new T.ExtrudeGeometry(archShape(w,h),{depth,bevelEnabled:false,curveSegments:12}),color,x,y,z,outline);
 const foundation=(w=4.3,d=3.8)=>{box('stone',0,.1,0,w,.2,d,.025);box('trim',0,.23,0,w-.22,.12,d-.22,.018);};
 const roof=(color,w,d,eave,rise,x=0,z=0)=>{
  const geo=new T.ExtrudeGeometry(roofShape(w,eave,rise),{depth:d,bevelEnabled:true,bevelThickness:.035,bevelSize:.035,bevelSegments:2,curveSegments:12});
  geo.translate(0,0,-d/2);add(geo,color,x,0,z,.03);
  // One clean painted roof plane reads better than rigid seams that float above
  // the curved profile.  The small ridge remains aligned to the roof apex.
  part('box','dark',x,eave+rise+.035,z,.075,.07,d+.1);
 };
 const window=(x,y,z,w=.34,h=.56,color='glow')=>{
  arch('dark',x,y,z,w+.13,h+.12,.055,.014);arch(color,x,y+.035,z+.06,w,h,.035,0);
  box('wood',x,y+h*.46,z+.104,.042,h*.72,.026,.008);
 };
 const sideWindow=(side,y,z,w=.28,h=.5,wallX=1.94,color='glow')=>{
  const x=side*wallX,turn=side*Math.PI/2;
  const frame=arch('dark',x,y,z,w+.13,h+.12,.06,.014);frame.rotation.y=turn;
  const pane=arch(color,x+side*.045,y+.035,z,w,h,.038,0);pane.rotation.y=turn;
  box('trim',x+side*.07,y-.015,z,.08,.1,w+.16,.01);
 };
 const gable=(color,x,y,z,w,h,depth=.08,outline=.02)=>{
  const shape=new T.Shape();shape.moveTo(-w/2,0);shape.lineTo(0,h);shape.lineTo(w/2,0);shape.closePath();
  return add(new T.ExtrudeGeometry(shape,{depth,bevelEnabled:true,bevelSize:.025,bevelThickness:.025,bevelSegments:2}),color,x,y,z,outline);
 };
 const door=(x=0,z=1.58,w=.8,h=1.36)=>{
  arch('dark',x,.26,z,w+ .14,h+.12,.08,.02);arch('wood',x,.29,z+.06,w,h,.045,.012);
  for(const dx of [-w*.23,0,w*.23])part('box','timber',x+dx,.29+h*.43,z+.112,.035,h*.72,.025);
  ball('gold',x+w*.25,.29+h*.5,z+.14,.055,.055,.025,0);
 };
 const stairs=(z=2,w=1.5,count=3)=>{for(let i=0;i<count;i++)box('trim',0,.25+i*.11,z-i*.16,w-i*.1,.18,.34,.014);};
 const planter=(x,z,herbs=false)=>{
  box('cream',x,.43,z,.86,.42,.43,.014);box('wood',x,.67,z,.94,.07,.5,.008);
  for(let i=0;i<4;i++){const px=x-.28+i*.19;ball('leaf',px,.73,z,.16,.21,.16,0);if(herbs)ball(i%2?'teal':'glow',px,.88,z+.03,.042,.07,.042,0);}
 };
 const lantern=(x,z)=>{
  cyl('wood',x,.92,z,.052,1.5,.012);box('dark',x,1.66,z,.28,.32,.28,.014);box('glow',x,1.65,z+.15,.16,.19,.025,0);
  add(new T.ConeGeometry(.2,.16,4),'wood',x,1.91,z,.014).rotation.y=Math.PI/4;
 };
 const banner=(x,z,color='blue',h=1.12)=>{
  cyl('wood',x,h/2+.18,z,.04,h,.01);box('gold',x,h+.24,z,.13,.09,.13,.012);
  const flag=box(color,x+.22,h*.67+.22,z,.42,h*.48,.045,.016);flag.rotation.z=-.07;
 };
 const rolledBanner=(x,z,color='blue',h=.82)=>{
  box(color,x,1.72,z,.46,h,.045,.014);
  for(const y of [1.72-h/2,1.72+h/2]){const roll=add(new T.CylinderGeometry(.055,.055,.57,12),'wood',x,y,z+.055,.01);roll.rotation.z=Math.PI/2;}
  for(const dx of [-.2,.2])ball('gold',x+dx,1.72-h/2-.06,z+.06,.04,.06,.03,0);
 };
 const crate=(x,z,s=.46)=>{
  box('timber',x,.31,z,s,s,s,.013);for(const dx of [-1,1])part('box','wood',x+dx*s*.38,.31,z+.24,.06,s+.03,.028);for(const dz of [-1,1])part('box','wood',x,.31,z+dz*s*.38,s+.03,.06,.028);
 };
 const barrel=(x,z)=>{cyl('timber',x,.38,z,.24,.62,.013);for(const y of [.17,.39,.61])cyl('dark',x,y,z,.253,.035,0);};
 const canvasWedge=(start,end,color)=>{
  const middle=(start+end)/2,frontness=angle=>Math.max(0,Math.cos(angle));
  // The front edge rises above the counters while the rear and side hems retain
  // the low friendly scallop of a market tent.
  const edge=angle=>[Math.sin(angle)*2.03,2.02+frontness(angle)*.38,Math.cos(angle)*1.87];
  const left=edge(start),right=edge(end),hem=[Math.sin(middle)*2.03,1.88+frontness(middle)*.52,Math.cos(middle)*1.87];
  const geometry=new T.BufferGeometry();
  geometry.setAttribute('position',new T.Float32BufferAttribute([0,3.42,0,...left,...hem,...right],3));
  geometry.setIndex([0,1,2,0,2,3]);geometry.computeVertexNormals();
  return add(geometry,color,0,0,0,0);
 };
 const stripedAwning=(x,z,y=1.98)=>{
  for(let i=0;i<3;i++){const cloth=box(i%2?'trim':'red',x-.44+i*.44,y,z,.46,.055,.78,.01);cloth.rotation.x=.28;}
  for(const dx of [-.62,.62]){const brace=box('wood',x+dx,y-.22,z-.18,.055,.52,.055,.008);brace.rotation.x=-.28;}
 };

 if(code==='hospital'){
  // A true T-plan: a tall healing nave faces the road while two deliberately
  // lower treatment wings make the footprint unmistakable at city zoom.
  foundation(6.15,4.65);
  box('cream',0,1.32,-.38,3.5,2.2,3.86,.03);roof('teal',3.86,4.18,2.4,1.2,0,-.38);
  for(const side of [-1,1]){
   box('cream',side*2.15,.92,-.42,1.72,1.42,2.5,.026);roof('teal',1.92,2.72,1.62,.62,side*2.15,-.42);
   sideWindow(side,1.0,-.65,.31,.56,3.02);window(side*2.15,.92,.83,.28,.48);
  }
  // The oversized cross sits on the free gable, above a clear central entrance.
  box('trim',0,2.8,1.78,1.34,1.46,.07,.012);box('teal',0,2.8,1.84,.22,1.12,.055,.01);box('teal',0,2.8,1.84,.88,.22,.055,.01);
  door(0,1.68,.92,1.45);stairs(2.26,1.7,4);window(-1.13,1.14,1.67,.34,.62);window(1.13,1.14,1.67,.34,.62);
  for(const side of [-1,1]){planter(side*2.46,1.5,true);lantern(side*2.9,1.0);}
  ball('teal',0,3.72,-.38,.13,.18,.13,.012);ball('glow',0,3.96,-.38,.08,.08,.08,.01);
 }else if(code==='storage'){
  foundation();
  // Tall grain bins give the warehouse a recognisable silhouette behind the
  // loading shed. The loading apron and both front worker lanes remain clear.
  for(const x of [-1.28,1.28]){
   cyl('cream',x,1.72,-1.86,.59,2.94,.022);
   for(const y of [.5,1.46,2.51])part('cylinder','timber',x,y,-1.86,.62,.1,.62);
   add(new T.ConeGeometry(.7,.6,12),'orange',x,3.46,-1.86,.025);
  }
  box('cream',0,1.04,-.26,3.98,1.55,2.65,.03);roof('orange',4.26,3.02,1.77,1.03,0,-.26);
  // High loading doors, visible sacks and a hoist distinguish a working granary.
  box('wood',0,1.15,1.14,1.5,1.52,.1,.016);for(const x of [-.48,0,.48])box('timber',x,1.15,1.2,.09,1.34,.04,.008);
  box('timber',0,2.05,1.19,1.64,.13,.12,.01);for(const x of [-1.52,1.52])box('timber',x,1.1,1.15,.18,1.66,.16,.014);
  for(const x of [-1.4,-1.15,-.9,.9,1.15,1.4]){ball('wheat',x,.47,1.54,.19,.3,.17,.012);part('box','timber',x,.68,1.54,.035,.25,.035);}
  crate(-1.78,1.38,.52);crate(-1.55,.95,.4);barrel(1.72,1.46);barrel(1.43,1.72);stairs(1.75,1.6,2);
  // A proper upper loading hatch fills the gable, while the crane and hanging
  // grain sack sit on the camera-facing right side rather than behind the shed.
  arch('dark',0,1.98,1.3,.66,.78,.065,.016);arch('timber',0,2.02,1.35,.54,.65,.035,.008);
  for(const x of [-.18,.18])box('wood',x,2.34,1.4,.045,.55,.025,.006);
  box('wood',1.82,1.7,1.88,.12,2.84,.12,.012);box('timber',1.4,3.04,1.88,1.16,.12,.14,.012);
  const hoist=add(new T.CylinderGeometry(.17,.17,.06,12),'dark',1.15,2.88,1.88,.008);hoist.rotation.z=Math.PI/2;
  const brace=box('timber',1.62,2.28,1.88,.11,1.28,.11,.008);brace.rotation.z=-.5;
  part('box','dark',1.15,1.91,1.88,.024,1.86,.024);ball('wheat',1.15,1.0,1.88,.27,.38,.23,.012);
  for(const side of [-1,1])sideWindow(side,1.05,-.48,.25,.45,2.02);
  barrel(-2.14,.92);barrel(-2.14,1.38);for(const [x,z] of [[-2.36,.75],[-1.88,.66]])ball('wheat',x,.43,z,.23,.32,.2,.012);
  window(-1.3,1.02,1.15,.26,.44);window(1.3,1.02,1.15,.26,.44);
 }else if(code==='treasure_house'){
  // Three round volumes form one quiet gold landmark instead of a bare drum.
  foundation(5.8,4.45);
  cyl('stone',0,.25,-.18,1.58,.28,.03);cyl('cream',0,1.38,-.18,1.45,2.08,.035);cyl('trim',0,2.42,-.18,1.54,.17,.018);
  const dome=add(new T.SphereGeometry(1.5,24,12,0,Math.PI*2,0,Math.PI/2),'gold',0,2.48,-.18,.032);dome.scale.y=.8;
  const domeBand=add(geometries.ring,'teal',0,2.7,-.18,.012);domeBand.rotation.x=Math.PI/2;domeBand.scale.set(1.34,1.34,1.34);
  ball('teal',0,3.72,-.18,.11,.13,.11,.01);ball('gold',0,3.98,-.18,.17,.17,.17,.014);
  for(const side of [-1,1]){
   cyl('cream',side*2.08,.92,-.15,.76,1.42,.025);cyl('trim',side*2.08,1.65,-.15,.82,.12,.014);
   const turretDome=add(new T.SphereGeometry(.8,16,8,0,Math.PI*2,0,Math.PI/2),'gold',side*2.08,1.71,-.15,.02);turretDome.scale.y=.62;
   window(side*2.08,.96,.66,.28,.5,'gold');sideWindow(side,.94,-.15,.25,.46,2.82,'gold');
  }
  door(0,1.48,.88,1.38);stairs(2.04,1.72,4);window(-.95,1.26,1.32,.27,.5,'gold');window(.95,1.26,1.32,.27,.5,'gold');
  const seal=add(geometries.ring,'gold',0,1.46,1.82,.014);seal.scale.set(.55,.55,.55);ball('purple',0,1.46,1.87,.22,.25,.03,.01);
  // A large coffer above the vault door makes this a treasury, rather than
  // another domed temple. Broad bands and a lock read from the city camera.
  box('trim',0,2.39,1.49,1.5,.18,.62,.018);
  box('wood',0,2.68,1.56,1.22,.42,.56,.018);
  const lid=add(new T.CylinderGeometry(.3,.3,1.22,12,1,false,0,Math.PI),'timber',0,2.89,1.56,.018);lid.rotation.z=Math.PI/2;
  for(const x of [-.43,.43]){
   part('box','gold',x,2.68,1.86,.11,.44,.035);
   const band=add(new T.TorusGeometry(.305,.055,6,12,Math.PI),'gold',x,2.89,1.56,0);band.rotation.y=Math.PI/2;
  }
  part('box','gold',0,2.74,1.89,.22,.28,.06);part('box','dark',0,2.76,1.93,.04,.1,.025);
 }else if(code==='hall_of_alliance'){
  // A broad council house with two six-sided shoulder towers is deliberately
  // unlike the ordinary gabled service buildings.
  foundation(6.75,4.5);
  // The recessed central roof deliberately stops before the tower roofs begin.
  // Their six-sided caps now read as two separate shoulders, never as white
  // wedges cutting through one large blue plane.
  box('cream',0,1.3,-.22,4.0,2.15,3.36,.03);roof('blue',3.85,3.72,2.35,1.36,0,-.22);
  for(const side of [-1,1]){
   const tower=add(new T.CylinderGeometry(.62,.68,1.72,6),'cream',side*2.65,1.16,-.18,.03);
   add(new T.CylinderGeometry(.72,.76,.13,6),'trim',side*2.65,2.08,-.18,.018);
   const towerRoof=add(new T.ConeGeometry(.7,.62,6),'blue',side*2.65,2.46,-.18,.028);towerRoof.rotation.y=Math.PI/6;
   window(side*2.65,.92,.52,.24,.46);sideWindow(side,1.08,-.52,.22,.43,3.3);
  }
  // The portico is wide but leaves its middle completely open to the door.
  for(const side of [-1,1]){cyl('trim',side*1.05,1.32,1.82,.2,2.1,.018);cyl('gold',side*1.05,2.4,1.82,.24,.1,.012);rolledBanner(side*1.78,1.76,side<0?'blue':'purple',.96);}
  box('trim',0,2.38,1.8,2.9,.28,.62,.018);gable('trim',0,2.46,1.82,2.9,1.18,.14,.02);gable('blue',0,2.58,1.98,2.3,.72,.055,.014);
  door(0,2.04,.96,1.52);stairs(2.4,2.28,4);window(-1.5,1.34,1.48,.34,.64);window(1.5,1.34,1.48,.34,.64);
  // Two allied houses share one crest, echoing the blue and violet banners.
  for(const [x,color] of [[-.19,'blue'],[.19,'purple']]){
   const shape=new T.Shape();shape.moveTo(-.25,.28);shape.quadraticCurveTo(0,.37,.25,.28);shape.lineTo(.22,-.06);shape.quadraticCurveTo(.17,-.23,0,-.35);shape.quadraticCurveTo(-.17,-.23,-.22,-.06);shape.closePath();
   const crest=add(new T.ExtrudeGeometry(shape,{depth:.06,bevelEnabled:true,bevelSize:.025,bevelThickness:.02,bevelSegments:1}),'gold',x,2.98,2.1,.014);
   const face=add(new T.ShapeGeometry(shape),color,x,3,2.21,0);face.scale.set(.75,.75,1);
  }
  for(const x of [-2.92,2.92])lantern(x,1.55);
 }else if(code==='trading_post'){
  // Three low, offset stalls replace the former single umbrella.  Their open
  // middle lane remains clear from back to front for merchants and visitors.
  // A low, chamfered warm-earth court joins the three stalls without reading
  // as a huge pale rectangular slab beside the village road.
  const court=new T.Shape();
  [[-3.12,2.08],[-2.58,2.62],[1.95,2.58],[3.12,1.9],[3.18,-1.6],[2.5,-2.62],[-1.82,-2.68],[-3.12,-1.86]].forEach(([x,z],index)=>{if(index)court.lineTo(x,z);else court.moveTo(x,z);});court.closePath();
  const courtGeo=new T.ExtrudeGeometry(court,{depth:.1,bevelEnabled:true,bevelSize:.06,bevelThickness:.035,bevelSegments:2,curveSegments:8});courtGeo.rotateX(-Math.PI/2);add(courtGeo,'timber',0,.15,0,.014);
  function marketStall(x,z,wares){
   box('timber',x,.72,z,1.56,.94,.82,.018);box('orange',x,1.22,z+.08,1.78,.13,.96,.012);
   for(const side of [-1,1])cyl('wood',x+side*.67,1.55,z-.17,.055,1.34,.01);
   // Dark frame, cross brace and board seams are batched: the face reads as
   // timber construction rather than a single featureless brown cuboid.
   for(const dx of [-.48,0,.48])part('box','wood',x+dx,.72,z+.43,.045,.78,.042);
   for(const y of [.4,1.02])part('box','wood',x,y,z+.43,1.45,.07,.042);
   part('box','wood',x,.72,z+.455,.07,1.04,.04,0,0,.78);
   for(let i=0;i<3;i++){
    const color=i%2?'trim':'red',piece=box(color,x-.56+i*.56,2.12,z-.02,.58,.055,.98,.012);piece.rotation.x=.26;
    // A flattened little roll gives the front canopy edge a soft droop.
    ball(color,x-.56+i*.56,1.97,z+.47,.31,.09,.085,.006);
   }
   if(wares==='vegetable'){
    for(let i=0;i<4;i++)ball(i%2?'orange':'leaf',x-.42+i*.28,1.43,z+.28,.14,.17,.14,.008);
    ball('red',x+.37,1.47,z+.28,.13,.14,.13,.008);cyl('wheat',x-.3,1.42,z+.22,.18,.2,.01);
   }else if(wares==='bread'){
    for(const [dx,dz] of [[-.38,.22],[-.08,.28],[.24,.2]])ball('wheat',x+dx,1.45,z+dz,.24,.12,.16,.008);
    for(const dx of [.48,.67])ball('timber',x+dx,1.42,z+.22,.16,.24,.14,.008);
    box('cream',x-.12,1.34,z+.24,.86,.09,.34,.008);
   }else{
    for(const [dx,color] of [[-.38,'purple'],[0,'teal'],[.38,'red']]){
     const roll=add(new T.CylinderGeometry(.18,.18,.56,12),color,x+dx,1.47,z+.25,.012);roll.rotation.z=Math.PI/2;
     const end=add(new T.CylinderGeometry(.19,.19,.025,12),'gold',x+dx-.295,1.47,z+.25,.006);end.rotation.z=Math.PI/2;
    }
   }
  }
  marketStall(-2.05,1.22,'vegetable');marketStall(2.05,.98,'bread');marketStall(-2.0,-1.35,'cloth');
  // Only the three outer steps lead into the courtyard; x[-.55,.55] stays free.
  for(const [x,z] of [[-2.05,2.0],[2.05,1.76],[-2.0,-.56]]){const step=box('trim',x,.23,z,1.16,.18,.38,.012);step.rotation.x=.05;}
  crate(2.72,-1.6,.42);barrel(2.62,-.98);
 }else if(code==='watch_tower'){
  foundation(3.7,3.5);
  cyl('stone',0,.23,0,1.28,.34,.03);cyl('trim',0,.46,0,1.1,.16,.018);cyl('cream',0,1.94,0,1.06,3.2,.035);
  // Four broad stepped consoles support the gallery; they replace noisy little
  // battlements and are large enough to read at normal city zoom.
  for(const [x,z] of [[-.94,-.72],[.94,-.72],[-.94,.72],[.94,.72]])box('trim',x,.68,z,.54,.42,.54,.018);
  for(const y of [.68,1.62,2.55])cyl('trim',0,y,0,1.11,.1,.012);
  // The solid shaft ends below this floor.  Bell, rails and posts therefore
  // remain genuinely open from the normal front-right camera.
  cyl('trim',0,3.62,0,1.24,.2,.018);for(let i=0;i<4;i++){const a=Math.PI/4+i*Math.PI/2;cyl('wood',Math.sin(a)*.9,4.06,Math.cos(a)*.9,.08,.76,.012);}
  const bell=add(new T.LatheGeometry([[0,0],[.42,0],[.42,.07],[.3,.15],[.25,.4],[.1,.53],[0,.55]].map(([r,y])=>new T.Vector2(r,y)),20),'gold',0,3.89,0,.018);ball('dark',0,3.87,0,.08,.13,.08,0);
  for(const [x,z,w,d] of [[0,.98,1.55,.08],[-.98,0,.08,1.55],[.98,0,.08,1.55]])box('wood',x,4.07,z,w,.075,d,.008);
  cyl('trim',0,4.47,0,1.12,.16,.018);const top=add(new T.ConeGeometry(1.31,.66,20),'teal',0,4.8,0,.032);top.scale.y=.95;
  cyl('gold',0,5.26,0,.08,.18,.01);ball('gold',0,5.43,0,.1,.1,.1,.012);
  window(0,1.0,1.06,.3,.54);window(-.37,2.09,.98,.19,.48);window(.37,2.09,.98,.19,.48);stairs(1.52,1.42,4);
 }

 for(const {shape,color,matrices} of batches.values()){
  const batch=new T.InstancedMesh(geometries[shape],m[color],matrices.length);
  matrices.forEach((matrix,index)=>batch.setMatrixAt(index,matrix));batch.instanceMatrix.needsUpdate=true;
  batch.castShadow=batch.receiveShadow=true;batch.computeBoundingSphere();root.add(batch);
 }
 return true;
}

import * as T from './vendor/three.module.js';
import { storybookMaterials as m, addStorybookOutline } from './storybook-style.js?v=storybook5';
import {attachFarmLife} from './farm-life.js?v=farm-life7';

// Compact, fully modelled translations of the farm and mine in village2.png.
// The caller owns the building root, placement, picking metadata and lifecycle.
const geometries={
 box:new T.BoxGeometry(1,1,1),
 cylinder:new T.CylinderGeometry(1,1,1,12),
 grain:new T.SphereGeometry(1,6,4),
 rock:new T.DodecahedronGeometry(1,0)
};

export function buildStorybookProduction(root,code){
 if(code!=='farm'&&code!=='quarry'&&code!=='gold_mine')return false;
 const batches=new Map(),dummy=new T.Object3D();
 function mesh(geometry,color,x=0,y=0,z=0,outline=.025){
  const object=new T.Mesh(geometry,m[color]);
  object.position.set(x,y,z);object.castShadow=object.receiveShadow=true;
  if(outline)addStorybookOutline(object,outline);
  root.add(object);return object;
 }
 function box(color,x,y,z,w,h,d,rz=0){
  const object=mesh(new T.BoxGeometry(w,h,d),color,x,y,z);object.rotation.z=rz;return object;
 }
 function cylinder(color,x,y,z,r,h,rx=0,rz=0){
  const object=mesh(new T.CylinderGeometry(r,r,h,12),color,x,y,z);object.rotation.set(rx,0,rz);return object;
 }
 function part(shape,color,x,y,z,sx,sy,sz,rx=0,ry=0,rz=0){
  const key=shape+color;
  if(!batches.has(key))batches.set(key,{shape,color,matrices:[]});
  dummy.position.set(x,y,z);dummy.scale.set(sx,sy,sz);dummy.rotation.set(rx,ry,rz);dummy.updateMatrix();
  batches.get(key).matrices.push(dummy.matrix.clone());
 }
 function archShape(w,h){
  const shape=new T.Shape(),radius=w/2;
  shape.moveTo(-radius,0);shape.lineTo(radius,0);shape.lineTo(radius,h-radius);
  shape.absarc(0,h-radius,radius,0,Math.PI,false);shape.closePath();return shape;
 }
 function arch(color,x,y,z,w,h,depth=.08){
  return mesh(new T.ExtrudeGeometry(archShape(w,h),{depth,bevelEnabled:false,curveSegments:12}),color,x,y,z);
 }
 function window(x,y,z,w=.35,h=.56){
  arch('dark',x,y,z,w+.13,h+.1,.055);arch('glow',x,y+.04,z+.06,w,h,.045);
  box('wood',x,y+h*.45,z+.12,.045,h*.73,.03);
 }
 function fence(x1,z1,x2,z2,count){
  const length=Math.hypot(x2-x1,z2-z1),angle=-Math.atan2(z2-z1,x2-x1);
  for(let i=0;i<=count;i++){
   const t=i/count;part('box','wood',x1+(x2-x1)*t,.4,z1+(z2-z1)*t,.12,.74,.12);
   part('grain','timber',x1+(x2-x1)*t,.78,z1+(z2-z1)*t,.075,.07,.075);
  }
  for(const y of [.3,.6])part('box','timber',(x1+x2)/2,y,(z1+z2)/2,length,.105,.1,0,angle);
 }
 function roofProfile(half,eave,rise,thickness=0){
  const shape=new T.Shape();
  shape.moveTo(-half,eave);
  shape.bezierCurveTo(-half*.68,eave+.06,-half*.42,eave+rise,0,eave+rise);
  shape.bezierCurveTo(half*.42,eave+rise,half*.68,eave+.06,half,eave);
  if(thickness){
   shape.lineTo(half,eave-thickness);
   shape.bezierCurveTo(half*.68,eave+.06-thickness,half*.42,eave+rise-thickness,0,eave+rise-thickness);
   shape.bezierCurveTo(-half*.42,eave+rise-thickness,-half*.68,eave+.06-thickness,-half,eave-thickness);
  }
  shape.closePath();return shape;
 }
 function longRoof(x,z,length,half,eave,rise){
  const geometry=new T.ExtrudeGeometry(roofProfile(half,eave,rise,.15),{depth:length,bevelEnabled:true,bevelThickness:.035,bevelSize:.035,bevelSegments:2,curveSegments:12});
  geometry.rotateY(Math.PI/2);geometry.translate(-length/2,0,0);
  mesh(geometry,'orange',x,0,z,.028);
  const gable=new T.ExtrudeGeometry(roofProfile(half-.11,eave-.1,rise-.13),{depth:length-.23,bevelEnabled:false,curveSegments:12});
  gable.rotateY(Math.PI/2);gable.translate(-(length-.23)/2,0,0);
  mesh(gable,'cream',x,0,z);
 }

 if(code==='farm'){
  // Warm plaster, one broad curved roof, a little front porch and a roof dormer.
  box('stone',-.55,.16,0,3.45,.25,2.32);
  box('cream',-.55,.98,0,3.3,1.64,2.18);
  longRoof(-.55,-.02,3.65,1.33,1.92,1.02);
  // The offset rear-left hay tower gives the farm a second, taller silhouette
  // without intruding into the working field or the clear front-right access.
  box('stone',-1.7,.16,-.72,1.38,.24,1.28);
  box('cream',-1.7,1.17,-.72,1.25,1.9,1.14);
  longRoof(-1.7,-.72,1.34,.66,2.12,1.12);
  window(-1.7,1.16,-.1,.27,.46);
  for(const side of [-1,1])box('timber',-1.7+side*.49,1.12,-.105,.11,1.58,.06);
  box('wood',-1.7,1.87,-.095,1.08,.11,.07);
  box('trim',.23,2.88,-.5,.44,.86,.45);
  box('trim',.23,3.34,-.5,.58,.17,.59);
  box('dark',.23,3.44,-.5,.35,.045,.36);

  arch('wood',-.45,.17,1.13,.94,1.48,.1);
  arch('timber',-.45,.2,1.235,.73,1.26,.04);
  for(const x of [-.65,-.45,-.25])part('box','wood',x,.78,1.285,.028,1.02,.025);
  part('grain','dark',-.2,.78,1.315,.055,.055,.035);
  for(const x of [-1.91,.97])box('stone',x,.91,1.115,.2,1.48,.12);
  window(-1.39,.9,1.12,.34,.53);

  // A low porch gives the entrance a welcoming, readable front at +z. Its
  // oversized braces echo the rounded roof rather than turning into a flat box.
  box('stone',-.45,.2,1.52,1.38,.18,.52);
  for(const side of [-1,1]){
   box('wood',-.45+side*.61,.61,1.55,.12,.82,.13);
   const brace=box('timber',-.45+side*.39,.94,1.54,.65,.12,.12);brace.rotation.z=-side*.56;
  }
  const porchRoof=box('orange',-.45,1.2,1.51,1.58,.16,.64);porchRoof.rotation.x=.16;
  for(const x of [-.92,-.45,.02])box('orangeDark',x,1.15,1.84,.055,.13,.035);

  // A small pointed dormer sits forward on the curved roof, as in the artwork.
  const dormerShape=new T.Shape();
  dormerShape.moveTo(-.33,0);dormerShape.lineTo(.33,0);dormerShape.lineTo(.33,.38);dormerShape.lineTo(0,.67);dormerShape.lineTo(-.33,.38);dormerShape.closePath();
  mesh(new T.ExtrudeGeometry(dormerShape,{depth:.45,bevelEnabled:false}),'cream',-1.15,2.17,.64);
  for(const side of [-1,1])box('orangeDark',-1.15+side*.2,2.66,.93,.53,.115,.58,-side*.72);
  window(-1.15,2.24,1.105,.23,.35);

  // An oversized carved sheaf sits against the front roof slope. Its three
  // broad ears identify food production even when the field is foreshortened.
  // All the repeated seeds use the field's existing instance batches.
  for(const stem of [-1,0,1]){
   const x=.18+stem*.21,lean=-stem*.19;
   part('box','gold',x,2.37,1.19,.052,.73,.045,0,0,lean);
   for(let seed=0;seed<3;seed++)for(const side of [-1,1]){
    part('grain','wheat',x+side*.079+stem*seed*.018,2.44+seed*.135,1.2,.105,.145,.058,0,0,-side*.58);
   }
   part('grain','wheat',x+stem*.06,2.88,1.2,.065,.13,.057);
  }
  part('box','wood',.18,2.15,1.215,.48,.10,.085);

  // The right-hand lean-to keeps the two big hay bales easy to identify.
  for(const x of [1.29,2.6])for(const z of [.05,1.14])box('wood',x,.81,z,.13,1.46,.13);
  box('timber',1.945,1.55,.61,1.57,.18,1.5);
  const canopy=box('orange',1.945,1.7,.59,1.64,.15,1.59);canopy.rotation.x=.14;
  for(const x of [1.65,2.24]){
   const hay=mesh(new T.CylinderGeometry(.36,.36,.51,10),'wheat',x,.46,.94);
   hay.rotation.z=Math.PI/2;
   for(const dx of [-.18,.18])cylinder('orangeDark',x+dx,.46,.94,.365,.04,0,Math.PI/2);
  }

  // Repeated wheat heads, stalks and fence pieces are batched into instances.
  box('wood',-1.01,.09,2.4,2.82,.13,1.47);
  box('timber',-1.01,.16,2.4,2.69,.04,1.34);
  for(let row=0;row<4;row++)for(let col=0;col<9;col++){
   const x=-2.14+col*.282,z=1.9+row*.31,h=.62+((row+col)%3)*.055;
   part('cylinder','gold',x,.22+h/2,z,.024,h,.024);
   for(let ear=0;ear<3;ear++)for(const side of [-1,1])part('grain','wheat',x+side*.049,.4+h*.47+ear*.105,z,.063,.11,.052,0,0,-side*.45);
   part('grain','wheat',x,.46+h,z,.046,.105,.045);
  }
  fence(-2.51,1.62,-2.51,3.2,4);fence(-2.51,3.2,.48,3.2,6);fence(.48,2.27,.48,3.2,2);
  // Sparse stone patches keep the wall readable at city zoom.
  for(const [x,z] of [[-1.58,1.125],[-.98,1.125],[.35,1.125]])part('box','stone',x,.38,z,.32,.18,.055);
 }else if(code==='quarry'){
  // The quarry is an open working face: three broad, stepped shelves sit at
  // the rear, leaving the +z loading apron and worker approach unobstructed.
  box('stone',0,.09,0,4.18,.17,3.66);
  // The rear is a short quarry face, not a staircase. Three chunky faceted
  // rock masses overlap across the back and keep the working apron at +z open.
  const rockMass=(color,x,y,z,sx,sy,sz,ry=0)=>{
   const rock=mesh(new T.DodecahedronGeometry(1,0),color,x,y,z,.03);
   rock.scale.set(sx,sy,sz);rock.rotation.set(.04,ry,.08);return rock;
  };
  rockMass('patch',-1.05,.92,-1.18,1.18,.92,.73,.29);
  rockMass('stone',.18,1.25,-1.36,1.12,1.25,.76,-.22);
  rockMass('patch',1.31,.73,-1.14,.82,.73,.66,.19);
  // Two broad, uneven cut benches interrupt the rock face. Their irregular
  // outlines read as broken quarry shelves instead of clean rectangular steps.
  const benches=[
   {color:'stone',z:-.74,depth:.48,points:[[-1.98,.14],[-1.82,.46],[-1.25,.57],[-.63,.43],[-.08,.53],[.57,.39],[1.32,.46],[1.73,.2],[1.79,.1],[-1.98,.1]]},
   {color:'trim',z:-.89,depth:.3,points:[[-1.42,.6],[-1.18,.87],[-.54,.98],[.02,.84],[.55,.97],[1.04,.75],[.94,.6],[-1.42,.6]]}
  ];
  for(const bench of benches){
   const shape=new T.Shape(bench.points.map(([x,y])=>new T.Vector2(x,y)));
   mesh(new T.ExtrudeGeometry(shape,{depth:bench.depth,bevelEnabled:true,bevelThickness:.05,bevelSize:.06,bevelSegments:1,curveSegments:5}),bench.color,0,0,bench.z,.022);
  }
  // Broad colour facets make the quarry readable at city distance while the
  // rock silhouettes remain deliberately simple and hand-painted.
  const faces=[
   {color:'trim',points:[[-1.85,.5],[-1.6,1.47],[-1.08,1.72],[-.79,.95],[-1.08,.47]]},
   {color:'patch',points:[[-.47,.85],[-.14,2.15],[.46,2.37],[.78,1.35],[.36,.83]]},
   {color:'trim',points:[[.78,.48],[1.12,1.38],[1.67,1.15],[1.72,.52],[1.37,.35]]}
  ];
  for(const face of faces){
   const shape=new T.Shape(face.points.map(([x,y])=>new T.Vector2(x,y)));
   mesh(new T.ShapeGeometry(shape),face.color,0,0,-.61,0);
  }
  // The building is turned toward its northern access road in the village, so
  // the southern camera sees this rear edge. A low timber safety rail, a bold
  // crossed-tool marker and a few finished blocks make that side intentional.
  box('timber',0,.18,-1.72,3.72,.16,.42);
  for(const x of [-1.58,0,1.58])box('wood',x,.69,-1.84,.14,1.18,.14);
  for(const y of [.48,.91])box('timber',0,y,-1.86,3.34,.1,.11);
  const rearPickLeft=box('wood',-.12,1.48,-2.1,.1,1.25,.09,.68);
  const rearPickRight=box('wood',.12,1.48,-2.11,.1,1.25,.09,-.68);
  rearPickLeft.rotation.y=rearPickRight.rotation.y=Math.PI;
  for(const [x,y,turn] of [[-.53,1.94,.68],[.53,1.94,-.68]]){
   const head=box('dark',x,y,-2.13,.58,.16,.12,turn);head.rotation.y=Math.PI;
  }
  for(const [x,y,z,w,h,d] of [[-1.45,.31,-1.52,.62,.42,.5],[-.79,.27,-1.56,.52,.34,.44],[1.35,.29,-1.55,.66,.38,.52]]){
   part('box','stone',x,y,z,w,h,d);part('box','trim',x,y+h*.5+.012,z,w*.86,.025,d*.8);
  }
  for(const [x,y,z,sx,sy,sz,ry] of [
   [-1.63,.34,.86,.42,.34,.39,.25],[.96,.37,.95,.48,.37,.43,-.25],
   [1.55,.3,.56,.3,.25,.32,.16]
  ])part('rock','stone',x,y,z,sx,sy,sz,0,ry,.1);

  // Tall A-frame crane: a single iconic silhouette with rope and a cut stone.
  for(const side of [-1,1]){
   const leg=box('wood',1.39+side*.44,1.63,.33,.17,2.76,.18);leg.rotation.z=-side*.19;
   const brace=box('timber',1.39+side*.25,1.29,.33,.88,.14,.14);brace.rotation.z=-side*.92;
  }
  box('timber',1.39,2.91,.33,1.44,.23,.28);
  box('wood',.55,2.86,.33,1.38,.17,.2);
  cylinder('dark',.01,2.86,.33,.22,.14,0,Math.PI/2);
  cylinder('timber',.01,2.86,.33,.12,.19,0,Math.PI/2);
  cylinder('dark',-.43,1.86,.33,.028,1.88);
  const hook=mesh(new T.TorusGeometry(.12,.034,6,12,Math.PI*1.48),'dark',-.43,.89,.33,.018);hook.rotation.z=.12;
  // A broad squared load and the finished blocks distinguish this stone yard
  // from the adjacent ore mine. The front loading apron stays open.
  box('stone',-.43,.65,.33,.74,.48,.62);
  for(const side of [-1,1]){
   part('box','wood',-.43+side*.23,.65,.33,.065,.5,.64);
   part('box','wood',-.43+side*.12,.98,.33,.045,.3,.055,0,0,-side*.75);
  }
  box('stone',-.43,.24,.33,.84,.12,.84);
  for(const [x,y,z,w,h,d] of [[-1.49,.39,.02,.83,.46,.68],[-1.38,.84,-.02,.73,.40,.62],[-1.3,.32,.91,.7,.3,.56]]){
   part('box','stone',x,y,z,w,h,d);
   part('box','trim',x,y+h*.5+.009,z,w*.89,.025,d*.83);
  }

  // Rails point from the quarry mouth to a small handcart at the front.
  for(const side of [-1,1])box('dark',side*.36,.17,1.17,.055,.055,1.58);
  for(let i=0;i<5;i++)part('box','wood',0,.15,.53+i*.33,.94,.07,.12);
  box('dark',0,.46,1.34,.94,.16,.68);
  box('patch',0,.68,1.34,.86,.38,.61);
  for(const side of [-1,1])for(const z of [1.03,1.64]){
   cylinder('dark',side*.49,.3,z,.18,.09,0,Math.PI/2);
   cylinder('trim',side*.542,.3,z,.10,.035,0,Math.PI/2);
  }
  for(const [x,y,z,s] of [[-.15,1.02,1.24,.22],[.17,1.0,1.43,.19],[.02,1.07,1.55,.17]])part('rock','stone',x,y,z,s,s,s,0,x*2,.12);
 }else{
  // This extruded silhouette has an actual open arch: the black back wall sits
  // behind the timber frame, so the tunnel retains depth when the view moves.
  const rockFace=new T.Shape();
  rockFace.moveTo(-2.34,.12);rockFace.lineTo(-2.44,.7);rockFace.lineTo(-2.12,1.52);
  rockFace.lineTo(-1.87,2.48);rockFace.lineTo(-1.18,3.26);rockFace.lineTo(-.55,3.32);
  rockFace.lineTo(-.08,3.62);rockFace.lineTo(.73,3.45);rockFace.lineTo(1.29,2.87);
  rockFace.lineTo(1.82,2.55);rockFace.lineTo(2.22,1.64);rockFace.lineTo(2.39,.39);
  rockFace.lineTo(2.18,.12);rockFace.lineTo(.76,.12);rockFace.lineTo(.76,1.52);
  rockFace.bezierCurveTo(.76,2.54,-.76,2.54,-.76,1.52);rockFace.lineTo(-.76,.12);rockFace.closePath();
  mesh(new T.ExtrudeGeometry(rockFace,{depth:1.55,bevelEnabled:true,bevelThickness:.08,bevelSize:.08,bevelSegments:1,curveSegments:10}),'stone',0,0,-.94,.028);
  arch('dark',0,.1,-1.04,1.66,2.34,.055);
  // Line the real opening instead of covering its front with a flat panel.
  // The inward-facing vault and floor keep oblique views inside the dark mine.
  const vaultCurve=new T.CubicBezierCurve(
   new T.Vector2(-.76,1.52),new T.Vector2(-.76,2.54),
   new T.Vector2(.76,2.54),new T.Vector2(.76,1.52)
  );
  // The rock extrusion's .08 bevel also narrows its inner walls. Offset the
  // lining inward beyond that bevel, so grey stone cannot cover the dark vault.
  const vaultPoints=vaultCurve.getPoints(20).map((point,i)=>{
   const tangent=vaultCurve.getTangent(i/20);
   return point.add(new T.Vector2(tangent.y,-tangent.x).multiplyScalar(.095));
  });
  const contour=[new T.Vector2(-.665,.145),...vaultPoints,new T.Vector2(.665,.145)];
  const liningPositions=[],liningIndices=[];
  for(const p of contour)liningPositions.push(p.x,p.y,.63,p.x,p.y,-1.0);
  for(let i=0;i<contour.length-1;i++){
   const a=i*2;liningIndices.push(a,a+1,a+3,a,a+3,a+2);
  }
  const lining=new T.BufferGeometry();
  lining.setAttribute('position',new T.Float32BufferAttribute(liningPositions,3));
  lining.setIndex(liningIndices);lining.computeVertexNormals();
  mesh(lining,'dark',0,0,0,0);
  mesh(new T.BoxGeometry(1.49,.06,2.1),'dark',0,.125,0,0);

  // Broad facets and boulders make one grey outcrop, without a pile of tiny dots.
  const facets=[
   {color:'patch',points:[[-2.12,.8],[-1.72,2.48],[-1.14,2.91],[-.87,2.45],[-1.15,1.19]]},
   {color:'trim',points:[[-1.65,2.6],[-1.18,3.25],[-.55,3.31],[-.73,2.82],[-1.1,2.42]]},
   {color:'patch',points:[[.55,2.61],[.66,3.4],[1.24,2.87],[1.66,2.54],[1.3,1.52],[1.08,1.97]]}
  ];
  for(const facet of facets){
   const shape=new T.Shape(facet.points.map(([x,y])=>new T.Vector2(x,y)));
   mesh(new T.ShapeGeometry(shape),facet.color,0,0,.705,0);
  }
  // Rear-facing reinforcement and exposed ore keep the rotated mine attractive
  // from the standard village camera without pretending there is a second door.
  for(const side of [-1,1]){
   box('wood',side*1.28,1.28,-1.07,.19,2.18,.18);
   const brace=box('timber',side*.86,1.8,-1.1,1.12,.15,.15,-side*.64);brace.rotation.y=Math.PI;
  }
  box('timber',0,2.38,-1.08,2.76,.2,.2);
  for(const [x,y,sx,sy,turn] of [[-1.72,1.72,.35,.24,.2],[-.83,2.72,.31,.22,-.35],[.7,2.92,.28,.2,.28],[1.62,1.93,.34,.25,-.2]]){
   part('rock','gold',x,y,-1.13,sx,sy,.18,0,turn,.12);
  }
  box('timber',1.45,.16,-1.66,1.42,.14,.82);
  for(const [x,z,size] of [[1.13,-1.67,.26],[1.48,-1.58,.3],[1.78,-1.73,.22]])part('rock','gold',x,.42,z,size,size*.78,size,0,x*.4,.15);
  for(const x of [-1.72,-1.2]){
   box('wood',x,.35,-1.58,.45,.62,.52);box('timber',x,.67,-1.58,.48,.08,.55);
  }
  // Broad veins stay on the free upper shoulders: lower seams disappear
  // behind the entrance lintel and lantern from the high three-quarter camera.
  // Their raised ore ends remain readable from above as well as from the front.
  const oreSeams=[
   [[-1.83,2.26],[-1.62,2.60],[-1.35,2.73],[-1.16,3.09],[-.81,3.27],[-.61,3.24],[-.77,2.97],[-1.00,2.86],[-1.29,2.53],[-1.58,2.42],[-1.72,2.20]],
   [[.53,3.15],[.65,3.28],[.99,3.02],[1.20,2.78],[1.53,2.59],[1.52,2.39],[1.32,2.52],[1.08,2.67],[.87,2.96],[.61,3.08]]
  ];
  for(const points of oreSeams){
   const shape=new T.Shape(points.map(([x,y])=>new T.Vector2(x,y)));
   mesh(new T.ExtrudeGeometry(shape,{depth:.055,bevelEnabled:true,bevelSize:.018,bevelThickness:.015,bevelSegments:1}),'gold',0,0,.725,.014);
  }
  part('rock','gold',-.72,3.28,.65,.32,.24,.30,0,.2,.14);
  part('rock','gold',1.40,2.60,.69,.27,.24,.28,0,-.2,.2);
  for(const [x,y,z,sx,sy,sz] of [[-1.99,.45,.75,.51,.52,.5],[-1.38,.32,1.03,.42,.32,.41],[1.68,.51,.86,.58,.59,.56],[2.15,.28,.45,.38,.32,.4]])part('rock','stone',x,y,z,sx,sy,sz,0,x*.2);
  for(const side of [-1,1])box('wood',side*.87,1.27,.92,.28,2.36,.33);
  box('timber',0,2.49,.94,2.37,.34,.48,-.045);
  for(const side of [-1,1]){
   box('timber',side*.67,2.1,.98,.18,.72,.25,-side*.65);
   part('grain','dark',side*.92,2.49,1.197,.057,.057,.025);
  }
  // The front timber collar, warning lantern and support wedges make the open
  // mine entrance read as an active workplace even in the wide city view.
  for(const side of [-1,1]){
   box('wood',side*.92,1.31,1.04,.25,2.46,.22);
   const wedge=box('timber',side*.58,2.04,1.13,.96,.17,.18);wedge.rotation.z=-side*.58;
  }
  box('wood',0,2.51,1.04,2.2,.28,.28);
  box('stone',0,.19,1.17,1.9,.16,.56);
  box('dark',-1.17,1.44,1.15,.31,.46,.25);
  box('glow',-1.17,1.45,1.287,.19,.27,.022);
  mesh(new T.ConeGeometry(.23,.15,4),'wood',-1.17,1.72,1.15).rotation.y=Math.PI/4;

  // Short track and squat open wagon with four big wheels.
  for(let i=0;i<7;i++)part('box','wood',0,.13,.72+i*.4,1.38,.1,.14);
  for(const side of [-1,1])box('patch',side*.46,.22,1.93,.1,.1,2.78);
  box('dark',0,.53,2.23,1.13,.17,1.0);
  box('patch',0,.77,2.23,1.07,.49,.96);
  box('dark',0,1.025,2.23,.88,.025,.77);
  for(const side of [-1,1]){
   box('stone',side*.54,.99,2.23,.09,.12,1.07);
   box('stone',0,.99,2.23+side*.5,1.15,.12,.09);
   for(const z of [1.9,2.56]){
    cylinder('dark',side*.61,.39,z,.255,.12,0,Math.PI/2);
    cylinder('patch',side*.688,.39,z,.145,.055,0,Math.PI/2);
   }
  }
  for(const [x,y,z,size] of [[-.23,1.12,2.06,.21],[.19,1.1,2.35,.23],[.17,1.17,2.0,.18],[-1.3,.37,1.56,.34],[1.16,.33,1.62,.3],[-.23,2.89,.63,.25]])part('rock','gold',x,y,z,size,size,size,0,x*2,.2);

  // A warm lantern and a small bucket hoist reproduce the reference landmarks.
  box('wood',-1.22,1.91,.98,.59,.1,.12);
  cylinder('dark',-1.47,1.65,1.03,.025,.43);
  box('dark',-1.47,1.32,1.03,.33,.47,.28);
  box('glow',-1.47,1.33,1.181,.22,.31,.025);
  box('glow',-1.646,1.33,1.03,.025,.31,.18);
  box('dark',-1.47,1.33,1.2,.037,.34,.022);
  mesh(new T.ConeGeometry(.25,.18,4),'wood',-1.47,1.62,1.03).rotation.y=Math.PI/4;
  box('wood',2.12,1.47,-.12,.2,2.7,.2);
  box('timber',2.24,2.89,-.06,.93,.2,.26,.06);
  box('wood',2.22,2.58,-.06,.12,.65,.14,-.61);
  cylinder('dark',2.44,2.21,-.06,.03,1.18);
  const bucket=mesh(new T.CylinderGeometry(.27,.23,.43,12),'timber',2.44,1.52,-.06);
  for(const y of [1.36,1.68])cylinder('wood',2.44,y,-.06,.28,.05);
  cylinder('dark',2.44,1.742,-.06,.21,.022);
  bucket.rotation.z=-.06;
 }

 for(const {shape,color,matrices} of batches.values()){
  const batch=new T.InstancedMesh(geometries[shape],m[color],matrices.length);
  matrices.forEach((matrix,i)=>batch.setMatrixAt(i,matrix));
  batch.instanceMatrix.needsUpdate=true;batch.castShadow=batch.receiveShadow=true;batch.computeBoundingSphere();root.add(batch);
 }
 if(code==='farm')attachFarmLife(root);
 return true;
}

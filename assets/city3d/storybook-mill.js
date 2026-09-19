import * as T from './vendor/three.module.js';
import {storybookMaterials as M,addStorybookOutline} from './storybook-style.js?v=storybook5';

// A timber watermill, with a separate wheel that the scene continues to animate.
export function buildStorybookMill({parent}){
 const root=new T.Group();root.position.set(-3,.1,2.5);root.userData.building='mill';parent.add(root);
 function add(geometry,material,x,y,z,ink=true,owner=root){const m=new T.Mesh(geometry,material);m.position.set(x,y,z);m.castShadow=m.receiveShadow=true;owner.add(m);if(ink)addStorybookOutline(m);return m;}
 const box=(w,h,d,x,y,z,material=M.wood,ink=true,owner=root)=>add(new T.BoxGeometry(w,h,d),material,x,y,z,ink,owner);
 const cylinder=(r,h,x,y,z,material=M.wood,owner=root)=>add(new T.CylinderGeometry(r,r,h,20),material,x,y,z,false,owner);
 function gable(w,h,d,x,y,z,material,ink=true){
  const shape=new T.Shape();shape.moveTo(-w/2,0);shape.bezierCurveTo(-w*.38,.03,-w*.22,h*.94,0,h);shape.bezierCurveTo(w*.22,h*.94,w*.38,.03,w/2,0);shape.closePath();
  const geometry=new T.ExtrudeGeometry(shape,{depth:d,bevelEnabled:true,bevelSize:.035,bevelThickness:.035,bevelSegments:2,curveSegments:10});geometry.translate(0,0,-d/2);
  return add(geometry,material,x,y,z,ink);
 }
 function arch(w,h,x,y,z,material){const s=new T.Shape(),r=w/2;s.moveTo(-r,0);s.lineTo(r,0);s.lineTo(r,h-r);s.absarc(0,h-r,r,0,Math.PI,false);s.closePath();return add(new T.ExtrudeGeometry(s,{depth:.05,bevelEnabled:false,curveSegments:10}),material,x,y,z,false);}
 box(5.8,.17,5.1,0,.08,.5,M.stone,false);
 box(2.75,1.9,2.3,0,1.15,0,M.cream);
 box(2.9,.28,2.45,0,.3,0,M.stone);
 gable(3.25,1.15,2.8,0,2.1,0,M.timber);
 gable(2.66,.86,.06,0,2.14,1.43,M.cream,false);
 // A single dark ridge follows the gable's long z axis. It sits just above the
 // curved roof crest instead of cutting through the roof as vertical ribs did.
 box(.18,.14,2.95,0,3.28,0,M.dark,false);
 // Clear, dark timber framing reads from both the front and the wheel side.
 for(const x of [-1.29,0,1.29])box(.15,1.93,.13,x,1.19,1.19,M.wood,false);
 for(const y of [.4,1.1,2.08])box(2.8,.13,.14,0,y,1.19,M.wood,false);
 for(const z of [-1.02,0,1.02])box(.14,1.9,.15,1.43,1.18,z,M.wood,false);
 for(const y of [.4,1.13,2.08])box(.14,.13,2.35,1.43,y,0,M.wood,false);
 for(const side of [-1,1]){const beam=box(1.72,.13,.13,side*.66,2.64,1.51,M.wood,false);beam.rotation.z=-side*.57;}
 box(.14,.98,.13,0,2.62,1.52,M.wood,false);
 arch(.81,1.38,.55,.25,1.22,M.dark);arch(.65,1.23,.55,.3,1.285,M.timber);
 for(const x of [.35,.55,.75])box(.025,1.0,.025,x,.81,1.35,M.wood,false);
 add(new T.SphereGeometry(.05,8,6),M.gold,.75,.8,1.39,false);
 arch(.53,.79,-.66,1.04,1.23,M.dark);arch(.38,.63,-.66,1.1,1.29,M.glow);
 box(.032,.61,.03,-.66,1.4,1.36,M.wood,false);
 // Front steps and a tiny canopy make the +z entrance visible above the yard.
 box(1.05,.15,.62,.55,.16,1.7,M.stone,false);
 box(.82,.13,.4,.55,.29,1.9,M.trim,false);
 for(const side of [-1,1])box(.1,.55,.11,.55+side*.5,.68,1.43,M.wood,false);
 const awning=box(1.28,.13,.48,.55,1.18,1.48,M.orange);awning.rotation.x=.18;
 for(const side of [-1,1]){const brace=box(.54,.09,.1,.55+side*.28,.94,1.43,M.timber,false);brace.rotation.z=-side*.7;}
 box(.54,1.12,.58,.7,3.08,-.5,M.patch);box(.66,.18,.7,.7,3.67,-.5,M.stone);
 box(.38,.05,.41,.7,3.77,-.5,M.dark,false);
 const wheel=new T.Group();wheel.position.set(2.02,1.22,-.15);wheel.rotation.y=Math.PI/2;root.add(wheel);
 for(const z of [-.24,.24]){
  add(new T.TorusGeometry(.97,.12,8,28),M.wood,0,0,z,true,wheel);
  add(new T.TorusGeometry(.76,.065,6,28),M.timber,0,0,z,false,wheel);
  for(let i=0;i<4;i++){const spoke=box(1.85,.11,.1,0,0,z,M.timber,false,wheel);spoke.rotation.z=i*Math.PI/4;}
 }
 const axle=cylinder(.15,.9,0,0,0,M.dark,wheel);axle.rotation.x=Math.PI/2;
 for(let i=0;i<12;i++){const a=i*Math.PI/6,b=box(.32,.14,.64,Math.cos(a)*.97,Math.sin(a)*.97,0,M.timber,false,wheel);b.rotation.z=a+Math.PI/2;}
 // The second, smaller ring and clear axle caps make the working wheel feel
 // deliberately built rather than a decorative circle.
 add(new T.TorusGeometry(.49,.045,6,20),M.dark,0,0,.29,false,wheel);
 for(const z of [-.33,.33])add(new T.CylinderGeometry(.19,.19,.07,12),M.gold,0,0,z,false,wheel).rotation.x=Math.PI/2;
 // The short open channel makes the wheel's purpose visible in the outer district.
 box(1.25,.035,3.8,2.05,.2,.08,M.water,false);
 for(const x of [1.32,2.78])box(.22,.21,3.9,x,.25,.08,M.stone);
 box(1.65,.13,1.52,2.04,2.48,-.15,M.timber);
 for(const z of [-.85,.55])box(.15,2.15,.15,2.65,1.31,z,M.wood);
 for(let i=0;i<4;i++)box(1.67,.04,.025,2.04,2.57,-.75+i*.4,M.wood,false);
 // The deliberately uneven left-hand lumber shelter is large enough to read
 // from the overview. Its thick posts stop before the worker lane at x=-3.05.
 for(const x of [-2.24,-.62]){
  box(.24,1.88,.22,x,.99,1.52,M.wood,false);
  box(.34,.16,.32,x,.16,1.52,M.stone,false);
 }
 box(2.06,.16,.78,-1.43,1.77,1.52,M.timber);
 gable(2.26,.66,1.12,-1.43,1.76,1.52,M.orange);
 // The open-sided saw bench and broad silver blade communicate lumber work,
 // rather than a second grain mill. Raising the shelter reveals the machinery
 // from the city camera without extending into the worker's left-hand lane.
 box(1.83,.14,.72,-1.43,.77,1.67,M.timber,false);
 for(const x of [-2.12,-.76])box(.15,.65,.55,x,.41,1.67,M.wood,false);
 const saw=add(new T.CylinderGeometry(.43,.43,.07,20),M.stone,-1.3,1.22,1.86,true);saw.rotation.x=Math.PI/2;
 const toothDummy=new T.Object3D(),teeth=new T.InstancedMesh(new T.BoxGeometry(.13,.075,.065),M.trim,12);
 for(let i=0;i<12;i++){
  const a=i*Math.PI/6;
  toothDummy.position.set(-1.3+Math.cos(a)*.46,1.22+Math.sin(a)*.46,1.86);
  toothDummy.rotation.set(0,0,a+Math.PI/2);toothDummy.updateMatrix();teeth.setMatrixAt(i,toothDummy.matrix);
 }
 teeth.castShadow=teeth.receiveShadow=true;teeth.computeBoundingSphere();root.add(teeth);
 const sawHub=cylinder(.1,.1,-1.3,1.22,1.925,M.dark);sawHub.rotation.x=Math.PI/2;
 // Repeated cut logs are batched: warm round ends, dark bark and simple rings.
 // Three oversized bundles are clearer than a scatter of individual logs.
 const positions=[[-1.46,.42,1.25],[-1.46,1.12,1.67],[-1.46,.42,2.12]];
 const dummy=new T.Object3D();
 for(const [radius,length,material,endOffset] of [[.28,1.76,M.wood,0],[.235,.04,M.wheat,.89]]){
  const count=positions.length*(endOffset?2:1),batch=new T.InstancedMesh(new T.CylinderGeometry(radius,radius,length,16),material,count);let index=0;
  for(const [x,y,z] of positions)for(const offset of endOffset?[-endOffset,endOffset]:[0]){dummy.position.set(x+offset,y,z);dummy.rotation.set(0,0,Math.PI/2);dummy.updateMatrix();batch.setMatrixAt(index++,dummy.matrix);}
  batch.castShadow=batch.receiveShadow=true;batch.computeBoundingSphere();root.add(batch);
 }
 box(.6,.55,.6,-.5,.48,2.15,M.timber);for(const y of [.28,.63])box(.63,.065,.63,-.5,y,2.15,M.wood,false);
 // An oversized axe is fixed to the open cream gable. Unlike the old tiny
 // millstone medallion, its silhouette also identifies wood production afar.
 const axe=new T.Group();axe.position.set(.14,2.67,1.59);axe.rotation.z=-.55;root.add(axe);
 box(.11,.9,.09,0,-.03,0,M.wood,false,axe);
 const bladeShape=new T.Shape();bladeShape.moveTo(-.09,.15);bladeShape.lineTo(.16,.19);bladeShape.quadraticCurveTo(.44,.19,.48,.38);bladeShape.quadraticCurveTo(.25,.48,.13,.42);bladeShape.lineTo(-.09,.38);bladeShape.closePath();
 add(new T.ExtrudeGeometry(bladeShape,{depth:.08,bevelEnabled:true,bevelSize:.025,bevelThickness:.01,bevelSegments:1,curveSegments:6}),M.stone,0,0,0,true,axe);
 return {root,wheel};
}

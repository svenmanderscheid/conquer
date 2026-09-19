import * as T from './vendor/three.module.js';
import {createStorybookMaterial,addStorybookOutline,storybookColors} from './storybook-style.js';

// Sculpted silhouettes and front-facing facial planes keep the little actors
// readable at map scale. Geometry carries its dimensions so ink stays uniform.
// Use the material factory: cloning a Toon material loses its pigment hook.
const palette={
 skin:'#96b655',skinLight:'#b2cc73',skinDark:'#607f3c',ear:'#728b43',
 leather:'#70472f',leatherLight:'#a57548',cloth:'#92643e',wood:'#a47a47',
 bone:'#e8dfc6',boneLight:'#fff3d5',boneShade:'#b7aa8a',ink:storybookColors.dark,
 plum:'#864bb2',plumLight:'#ac6bc6',metal:'#8eafb2',metalLight:'#d2ded2',
 stone:'#8297a0',stoneLight:'#aabbb8',stoneDark:'#536c75',moss:'#679349',
 mossLight:'#8db053',rune:'#6de6d4',runeDark:'#2d9992',gold:'#e4af38',
 goblin:'#89ad58',goblinLight:'#b7ce7b',goblinDark:'#587e43',
 red:'#bf673f',redLight:'#dd9b53',sack:'#ac8650',sackLight:'#d1ac6b'
};
const paint=Object.fromEntries(Object.entries(palette).map(([name,color])=>
 [name,['ink','rune','runeDark'].includes(name)?new T.MeshBasicMaterial({color}):createStorybookMaterial(color)]
));
const sphere=new T.SphereGeometry(1,20,14);
const inkWidth=.036;
function mesh(parent,geometry,color,x=0,y=0,z=0,outline=true){
 const object=new T.Mesh(geometry,paint[color]||color);object.position.set(x,y,z);
 object.castShadow=object.receiveShadow=true;parent.add(object);
 if(outline)addStorybookOutline(object,inkWidth);
 return object;
}
function ball(parent,color,x,y,z,w,h,d,outline=true){
 return mesh(parent,sphere.clone().scale(w/2,h/2,d/2),color,x,y,z,outline);
}
function group(parent,x=0,y=0,z=0){const object=new T.Group();object.position.set(x,y,z);parent.add(object);return object;}
function roundedGeometry(w,h,d,r=.12){
 r=Math.min(r,w/2-.001,h/2-.001,d/2-.001);
 const geometry=new T.BoxGeometry(w,h,d,12,12,12),p=geometry.attributes.position,n=geometry.attributes.normal;
 const inner=new T.Vector3(w/2-r,h/2-r,d/2-r),point=new T.Vector3(),core=new T.Vector3(),normal=new T.Vector3();
 for(let i=0;i<p.count;i++){
  point.fromBufferAttribute(p,i);core.copy(point).clamp(inner.clone().negate(),inner);
  normal.copy(point).sub(core).normalize();point.copy(core).addScaledVector(normal,r);
  p.setXYZ(i,point.x,point.y,point.z);n.setXYZ(i,normal.x,normal.y,normal.z);
 }
 return geometry;
}
function box(parent,color,x,y,z,w,h,d,r=.12,outline=true){
 return mesh(parent,roundedGeometry(w,h,d,r),color,x,y,z,outline);
}
function slab(parent,color,points,z,depth=.08,bevel=.025,outline=true){
 const shape=new T.Shape();points.forEach(([x,y],i)=>i?shape.lineTo(x,y):shape.moveTo(x,y));shape.closePath();
 const geometry=new T.ExtrudeGeometry(shape,{depth,bevelEnabled:bevel>0,bevelSize:bevel,bevelThickness:bevel,bevelSegments:3,steps:1});
 geometry.translate(0,0,z);return mesh(parent,geometry,color,0,0,0,outline);
}
function tube(parent,color,points,r=.025,outline=false){
 const curve=new T.CatmullRomCurve3(points.map(p=>new T.Vector3(...p)));
 return mesh(parent,new T.TubeGeometry(curve,Math.max(8,points.length*5),r,7,false),color,0,0,0,outline);
}
function cylinder(parent,color,x,y,z,top,bottom,height,rotation=0,outline=true){
 const part=mesh(parent,new T.CylinderGeometry(top,bottom,height,12),color,x,y,z,outline);part.rotation.z=rotation;return part;
}
function bone(parent,a,b,r=.1){
 const A=new T.Vector3(...a),B=new T.Vector3(...b),center=A.clone().add(B).multiplyScalar(.5);
 const shaft=mesh(parent,new T.CapsuleGeometry(r,A.distanceTo(B),4,10),'bone',...center.toArray());
 shaft.quaternion.setFromUnitVectors(new T.Vector3(0,1,0),B.sub(A).normalize());return shaft;
}
function contact(root,w=1.8,d=1){
 // Feathered concentric layers avoid the old solid green pedestal.
 for(let i=0;i<4;i++){
  const material=new T.MeshBasicMaterial({color:'#493b33',transparent:true,opacity:.025+i*.011,depthWrite:false});
  const object=mesh(root,new T.CircleGeometry(1,40),material,0,.009+i*.001,0,false);
  object.rotation.x=-Math.PI/2;object.scale.set(w*(1-i*.15)/2,d*(1-i*.15)/2,1);
 }
}
function eyes(parent,{x=.31,y=.14,z=.61,w=.35,h=.36,pupil=.11,slant=.14}={}){
 const lids=[];
 for(const side of [-1,1]){
  const eye=group(parent,side*x,y,z);
  const white=ball(eye,'boneLight',0,0,0,w,h,.08);
  // Pupils lie in front of the sclera instead of intersecting it.
  ball(eye,'ink',.022,-.006,.059,pupil,h*.65,.027,false);
  ball(eye,'boneLight',.041,.047,.076,.038,.051,.014,false);
  const brow=box(parent,'ink',side*x,y+h*.49,z+.025,w*1.16,.115,.1,.035,false);
  brow.rotation.z=side*slant;
  lids.push(white);
 }
 return lids;
}
function tusk(parent,x,y,z,height=.3){
 const geometry=new T.ConeGeometry(.105,height,12);geometry.translate(0,height/2,0);
 const tooth=mesh(parent,geometry,'boneLight',x,y,z);tooth.rotation.z=x>0?-.14:.14;return tooth;
}
function ears(parent,color,inner,x,y,z,size=1){
 for(const side of [-1,1]){
  const ear=group(parent,side*x,y,z);ear.scale.x=side;
  slab(ear,color,[[0,-.2*size],[.34*size,-.1*size],[.62*size,.34*size],[.12*size,.2*size]],0,.15,.035);
  slab(ear,inner,[[.11,-.08*size],[.29*size,-.015*size],[.44*size,.19*size],[.15,.11*size]],.165,.015,.015,false);
 }
}
function foot(root,color,x,z=0,w=.48){
 const f=box(root,color,x,.18,z+.12,w,.34,.66,.13);
 box(root,'leather',x,.073,z+.15,w+.025,.1,.67,.035);return f;
}
function arm(parent,x,y,z,color,handColor=color){
 const pivot=group(parent,x,y,z);
 ball(pivot,color,0,-.19,0,.4,.53,.4);
 ball(pivot,color,.03,-.46,.08,.32,.38,.34);
 const hand=group(pivot,.035,-.66,.15);
 box(hand,handColor,0,0,0,.38,.34,.38,.12);
 ball(hand,handColor,.16,.07,.13,.17,.2,.18);
 return {pivot,hand};
}
function bindIdle(root,{torso,head,arms=[],cloth=null,phase=0,weight=1,eyes:eyeParts=[],style='watch'}){
 const base=torso.position.y,headY=head.position.y,bodyRest=torso.rotation.clone(),headRest=head.rotation.clone();
 const rests=arms.map(p=>({x:p.rotation.x,z:p.rotation.z}));
 const ease=x=>{x=Math.max(0,Math.min(1,x));return x*x*(3-2*x);};
 const gesture=(u,start,rise,hold,fall)=>ease((u-start)/rise)*(1-ease((u-start-rise-hold)/fall));
 root.userData.animate=(seconds=0)=>{
  const u=((seconds/4+phase/7)%1+1)%1,t=u*Math.PI*2,breath=Math.sin(t);
  const look=gesture(u,.12,.13,.13,.2),settle=gesture(u,.62,.1,.03,.17);
  // Feet stay planted. Chest expansion, a held glance and delayed equipment
  // movement replace the synchronized whole-body rocking of the old pose.
  torso.position.y=base+breath*.012*weight;
  torso.scale.set(1+breath*.006*weight,1+breath*.009*weight,1+breath*.014*weight);
  torso.rotation.x=bodyRest.x+breath*.012*weight+settle*.022*weight;
  torso.rotation.z=bodyRest.z+Math.sin(t-.4)*.008*weight;
  head.rotation.y=headRest.y+(look*.24-settle*.10)*weight;
  head.rotation.x=headRest.x+(style==='sneak'?settle*.10:-look*.025)+Math.sin(t-.3)*.012*weight;
  head.rotation.z=headRest.z+(look*.025-settle*.035)*weight;head.position.y=headY+Math.sin(t-.35)*.007*weight;
  arms.forEach((p,i)=>{const response=gesture(u,.2+i*.055,.15,.08,.24);p.rotation.x=rests[i].x+Math.sin(t-.6-i*.45)*.026*weight+(i===0?-1:1)*response*(style==='guard'?.14:.09)*weight;p.rotation.z=rests[i].z+Math.sin(t-.8)*.012*weight;});
  if(cloth){cloth.rotation.z=Math.sin(t-.9)*.055;cloth.rotation.y=Math.sin(t-.55)*.04;}
  const blink=gesture(u,.56,.025,0,.045);
  eyeParts.forEach(eye=>eye.parent.scale.y=1-blink*.94);
 };
 root.userData.designVersion=3;
}

function makeOrc(){
 const root=new T.Group();root.name='world-monster-orc';contact(root,2.5,1.2);
 for(const side of [-1,1]){
  ball(root,'skinDark',side*.36,.48,0,.4,.59,.4);
  foot(root,'skin',side*.39,.08,.56);
  for(const offset of [-.13,.02,.17])tube(root,'skinDark',[[side*.39+offset,.22,.37],[side*.39+offset,.15,.435]],.013);
 }
 const torso=group(root,0,.91,0);
 torso.rotation.set(.025,-.13,-.018);
 box(torso,'skin',0,.37,0,1.22,.93,.7,.26);
 for(const side of [-1,1])ball(torso,'skinLight',side*.255,.54,.34,.5,.34,.18,false);
 tube(torso,'skinDark',[[0,.7,.431],[0,.4,.431]],.015);
 slab(torso,'cloth',[[-.51,.16],[.5,.16],[.52,-.2],[.24,-.31],[.1,-.22],[-.1,-.4],[-.27,-.26],[-.44,-.32]],.23,.12,.035);
 box(torso,'leather',0,.16,.045,1.15,.2,.8,.055);
 const strap=box(torso,'leather',.26,.56,.365,.18,.77,.07,.025);strap.rotation.z=-.38;
 ball(torso,'leather',.69,.73,-.01,.57,.31,.63);
 ball(torso,'metal',.70,.84,.06,.38,.10,.44,false);
 box(torso,'gold',0,.15,.474,.3,.25,.085,.03);
 box(torso,'leather',0,.15,.53,.16,.13,.025,.01,false);
 const left=arm(torso,-.73,.66,0,'skin'),right=arm(torso,.73,.66,.015,'skin');
 left.pivot.rotation.z=-.3;left.pivot.rotation.x=-.35;
 right.pivot.rotation.z=.13;
 for(const a of [left,right]){
  cylinder(a.pivot,'leather',.026,-.45,.1,.19,.19,.22);
  cylinder(a.pivot,'leatherLight',.026,-.43,.1,.197,.197,.047,0,false);
 }
 // Raised, oversized club stays outside the head silhouette.
 const club=group(left.hand,-.07,.01,.16);club.rotation.z=.65;
 cylinder(club,'wood',0,.43,0,.1,.08,1.06);
 const clubHead=box(club,'wood',-.03,.98,0,.47,.79,.4,.13);clubHead.rotation.z=.03;
 for(const y of [.67,1.14])cylinder(club,'leather',-.03,y,0,.245,.23,.1);
 tube(club,'leather',[[0,.7,.23],[-.03,.93,.23],[.02,1.18,.21]],.017);
 for(const [x,y,z,rz]of [[-.25,.95,.03,Math.PI/2],[.05,1.22,.2,.3],[.19,.84,.1,-.9]]){
  const spike=mesh(club,new T.ConeGeometry(.105,.2,5),'boneShade',x,y,z);spike.rotation.z=rz;
 }
 const head=group(torso,0,1.46,0);
 head.rotation.set(-.035,.11,.025);
 box(head,'skin',0,0,0,1.61,1.30,1.12,.51);
 box(head,'skinLight',0,-.34,.42,1.25,.53,.5,.2,false);
 ears(head,'skin','ear',.71,.02,-.04,.92);
 const eyeParts=eyes(head,{x:.335,y:.1,z:.567,w:.37,h:.36,pupil:.14,slant:.22});
 ball(head,'skinDark',0,-.16,.675,.43,.29,.32);
 for(const side of [-1,1])ball(head,'ink',side*.112,-.21,.802,.085,.049,.027,false);
 tube(head,'ink',[[-.47,-.41,.675],[-.26,-.37,.723],[0,-.36,.75],[.26,-.37,.723],[.47,-.41,.675]],.031);
 for(const side of [-1,1])tusk(head,side*.4,-.43,.72,.31);
 // A small scar and gold ear hoop give character without adding busy texture.
 tube(head,'skinDark',[[.58,.21,.532],[.56,-.01,.571]],.022);
 const hoop=mesh(head,new T.TorusGeometry(.11,.029,8,20),'gold',1.03,-.15,.12);hoop.rotation.y=.3;
 bindIdle(root,{torso,head,arms:[left.pivot,right.pivot],phase:.15,eyes:eyeParts,style:'guard'});return root;
}

function makeSkeleton(){
 const root=new T.Group();root.name='world-monster-skeleton';contact(root,2,1.1);
 for(const side of [-1,1]){
  bone(root,[side*.24,.77,0],[side*.3,.3,.03],.092);
  ball(root,'boneLight',side*.29,.4,.04,.24,.23,.24);
  box(root,'bone',side*.31,.16,.16,.39,.24,.54,.095);
  for(const off of [-.1,0,.1])tube(root,'boneShade',[[side*.31+off,.21,.34],[side*.31+off,.14,.41]],.012);
 }
 const torso=group(root,0,.92,0);
 torso.rotation.set(.045,.12,.035);
 box(torso,'boneShade',0,-.015,0,.62,.32,.36,.12);
 bone(torso,[0,.1,-.07],[0,.82,-.07],.105);
 for(const [y,width]of [[.26,.38],[.45,.44],[.64,.44]]){
  for(const side of [-1,1])tube(torso,'boneLight',[[side*.07,y+.06,-.1],[side*width,y+.065,0],[side*width,y,.22],[side*.09,y-.075,.29]],.063,true);
 }
 bone(torso,[0,.8,0],[-.5,.77,0],.08);bone(torso,[0,.8,0],[.5,.77,0],.08);
 function skeletalArm(x,angle){
  const pivot=group(torso,x,.74,0);pivot.rotation.z=angle;
  bone(pivot,[0,0,0],[0,-.32,.015],.083);
  ball(pivot,'boneLight',0,-.33,.03,.21,.19,.2);
  bone(pivot,[0,-.36,.03],[.035,-.63,.15],.078);
  const hand=group(pivot,.035,-.68,.16);box(hand,'bone',0,0,0,.29,.24,.25,.065);
  for(const x of [-.08,.02,.12])box(hand,'boneLight',x,-.02,.136,.05,.18,.06,.017,false);
  return {pivot,hand};
 }
 const left=skeletalArm(-.58,-.28),right=skeletalArm(.58,.25);left.pivot.rotation.x=-.2;
 const sword=group(left.hand,-.07,0,.16);sword.rotation.z=.68;
 cylinder(sword,'leather',0,.05,0,.073,.073,.34);
 ball(sword,'gold',0,-.13,0,.18,.17,.18);
 box(sword,'metal',0,.26,0,.61,.14,.15,.04);
 slab(sword,'metal',[[-.135,.33],[.135,.33],[.145,.99],[.09,1.04],[.145,1.09],[.1,1.35],[0,1.57],[-.135,1.33]],-.04,.085,.012);
 slab(sword,'metalLight',[[0,.36],[.13,.36],[.12,1.33],[0,1.52]],.06,.013,.003,false);
 const shield=group(right.hand,.02,.12,.09);shield.rotation.y=-.12;
 const rim=cylinder(shield,'leatherLight',0,0,0,.38,.38,.12);rim.rotation.x=Math.PI/2;
 const front=cylinder(shield,'wood',0,0,.087,.32,.32,.075);front.rotation.x=Math.PI/2;
 tube(shield,'leather',[[-.14,-.28,.135],[-.14,.28,.135]],.015);
 tube(shield,'leather',[[.13,-.28,.135],[.13,.28,.135]],.015);
 ball(shield,'metal',0,0,.17,.22,.22,.12);
 const scarf=group(torso,0,.9,0);
 const ring=mesh(scarf,new T.TorusGeometry(.32,.12,10,28),'plum',0,0,0);ring.rotation.x=Math.PI/2;
 box(scarf,'plumLight',0,-.05,.27,.66,.18,.13,.07);
 const tail=group(scarf,.32,-.03,-.04);
 slab(tail,'plum',[[0,0],[.28,.02],[.56,-.13],[1.03,-.18],[.89,-.38],[1.02,-.52],[.52,-.49],[.1,-.23]],0,.065,.025);
 tube(tail,'plumLight',[[.14,-.075,.09],[.54,-.25,.09],[.85,-.28,.09]],.04);
 const head=group(torso,0,1.54,.015);
 head.rotation.set(.02,-.13,-.07);
 box(head,'bone',0,.09,0,1.42,1.2,.96,.46);
 for(const side of [-1,1]){
  ball(head,'boneLight',side*.52,-.18,.34,.34,.3,.29,false);
  const socket=ball(head,'ink',side*.3,.095,.502,.47,.51,.095,false);socket.rotation.z=side*.11;
  tube(head,'boneShade',[[side*.52,.2,.474],[side*.4,.35,.485],[side*.18,.33,.509]],.027);
 }
 slab(head,'ink',[[-.075,-.21],[0,-.075],[.075,-.21],[.04,-.26],[-.04,-.26]],.503,.025,.005,false);
 box(head,'ink',0,-.39,.387,.75,.27,.2,.06,false);
 for(const x of [-.285,-.095,.095,.285])box(head,'boneLight',x,-.32,.511,.15,.17,.12,.03);
 box(head,'boneShade',0,-.58,.17,.97,.21,.67,.085);
 for(const x of [-.285,-.095,.095,.285])box(head,'boneLight',x,-.49,.509,.15,.12,.1,.026);
 tube(head,'boneShade',[[.2,.65,.23],[.15,.48,.456],[.26,.38,.471],[.21,.25,.5]],.018);
 bindIdle(root,{torso,head,arms:[left.pivot,right.pivot],cloth:tail,phase:1.1,weight:.8,style:'guard'});return root;
}

function stoneBlock(parent,color,x,y,z,w,h,d,r=.13,rz=0){
 const geometry=roundedGeometry(w,h,d,r);
 if(['stone','stoneLight','stoneDark'].includes(color)&&w>.3&&h>.3){
  // Broad bends vary the rock edges; no per-vertex jitter or noisy surface.
  const vertices=geometry.attributes.position;
  for(let i=0;i<vertices.count;i++){
   const px=vertices.getX(i),py=vertices.getY(i),pz=vertices.getZ(i);
   vertices.setXYZ(i,px*(1+.065*Math.sin(py*3.3+pz*1.7)),py+.025*Math.sin(px*4+pz*2.4),pz+.023*Math.sin(px*3+py*2));
  }
  geometry.computeVertexNormals();
 }
 const p=mesh(parent,geometry,color,x,y,z);p.rotation.z=rz;return p;
}
function makeGolem(){
 const root=new T.Group();root.name='world-monster-golem';contact(root,3.2,1.4);
 for(const side of [-1,1]){
  stoneBlock(root,'stoneDark',side*.5,.57,-.02,.55,.62,.6,.13,side*.05);
  stoneBlock(root,'stone',side*.55,.2,.17,.83,.39,.95,.13,-side*.05);
  tube(root,'stoneDark',[[side*.55-.18,.32,.66],[side*.55-.18,.12,.66]],.023);
 }
 const torso=group(root,0,1.26,0);
 torso.rotation.set(.045,-.09,-.02);
 stoneBlock(torso,'stoneDark',0,-.2,-.04,1.13,.88,.78,.17);
 slab(torso,'stone',[[-.85,.63],[.84,.63],[.78,.12],[.49,-.44],[-.44,-.44],[-.76,.1]],-.31,.8,.11);
 stoneBlock(torso,'stoneLight',0,.3,.47,1.19,.61,.14,.05);
 const rune=group(torso,0,.25,.59);
 slab(rune,'stoneDark',[[0,.25],[.23,0],[0,-.28],[-.23,0]],0,.03,.015,false);
 tube(rune,'rune',[[0,.18,.05],[-.12,0,.05],[0,-.17,.05],[.12,0,.05],[0,.18,.05]],.035);
 ball(rune,'rune',0,0,.055,.075,.09,.03,false);
 const arms=[];
 for(const side of [-1,1]){
  const arm=group(torso,side*1.02,.53,0);arm.rotation.z=side*.11;
  stoneBlock(arm,'stone',0,-.03,0,.81,.77,.83,.14,-side*.13);
  stoneBlock(arm,'stoneDark',side*.035,-.57,.015,.43,.57,.53,.11);
  stoneBlock(arm,'stone',side*.09,-.94,.18,.86,.77,.83,.16,side*.06);
  stoneBlock(arm,'stoneLight',side*.075,-.91,.597,.66,.37,.12,.04);
  for(const x of [-.2,.02,.24])tube(arm,'stoneDark',[[x,-.79,.666],[x,-1.08,.64]],.016);
  arms.push(arm);
 }
 const head=group(torso,0,1.23,.02);
 head.rotation.set(.03,.12,.035);
 slab(head,'stone',[[-.76,-.37],[-.86,.19],[-.65,.49],[-.19,.56],[.17,.49],[.73,.52],[.84,.16],[.74,-.43],[.17,-.5],[-.54,-.46]],-.47,.94,.075);
 stoneBlock(head,'stoneLight',0,-.26,.39,1.45,.42,.51,.1);
 for(const side of [-1,1]){
  stoneBlock(head,'stoneDark',side*.35,.025,.577,.55,.25,.045,.015,side*.1);
  stoneBlock(head,'rune',side*.35,.025,.611,.39,.09,.025,.009,side*.1);
  stoneBlock(head,'stoneLight',side*.36,.23,.563,.71,.22,.14,.055,side*.1);
 }
 stoneBlock(head,'stone',0,-.095,.66,.23,.32,.26,.06);
 tube(head,'stoneDark',[[-.46,-.36,.661],[-.15,-.39,.665],[.24,-.39,.665],[.47,-.34,.652]],.026);
 tube(head,'stoneDark',[[.44,.51,.32],[.33,.4,.56],[.41,.31,.578]],.022);
 tube(torso,'stoneDark',[[.62,.56,.57],[.45,.44,.58],[.53,.34,.58]],.021);
 for(const [parent,x,y,z,w]of [[head,-.53,.53,.01,.56],[head,-.32,.56,.08,.34],[arms[0],-.17,.39,.12,.55],[arms[0],.18,.39,-.1,.4]]){
  ball(parent,'moss',x,y,z,w,.23,.43);
  ball(parent,'mossLight',x-.07,y+.07,z+.07,w*.55,.08,.28,false);
 }
 for(const [x,y,z]of [[-.65,.5,.36],[-.55,.46,.5],[-.4,.46,.49]])ball(head,'moss',x,y,z,.19,.17,.16,false);
 const sprig=group(arms[0],-.16,.43,.12);
 for(const side of [-1,1]){
  const leaf=ball(sprig,'mossLight',side*.065,.055,0,.18,.075,.12,false);leaf.rotation.z=side*.55;
 }
 bindIdle(root,{torso,head,arms,phase:2.1,weight:.55});return root;
}

function makeGoblin(){
 const root=new T.Group();root.name='world-monster-goblin';contact(root,2.6,1.25);
 for(const side of [-1,1]){
  ball(root,'goblinDark',side*.27,.47,0,.32,.51,.33);
  foot(root,'leatherLight',side*.29,0,.44);
  box(root,'leather',side*.29,.33,-.01,.39,.22,.39,.07);
 }
 const torso=group(root,0,.87,0);
 torso.rotation.set(.10,-.17,-.045);
 box(torso,'red',0,.4,-.02,.89,.88,.63,.24);
 for(const side of [-1,1]){
  slab(torso,'redLight',[[side*.05,.79],[side*.34,.7],[side*.3,.07],[side*.03,.12]],.27,.05,.02);
 }
 box(torso,'leather',0,.2,.01,.92,.16,.68,.045);
 box(torso,'gold',0,.2,.379,.22,.21,.075,.025);
 box(torso,'leather',0,.2,.426,.1,.09,.025,.005,false);
 // Treasure sack remains outside the head and torso instead of disappearing
 // behind the actor when viewed from the map camera.
 const sack=group(torso,.75,.04,-.24);
 sack.scale.setScalar(.88);sack.rotation.z=-.1;
 ball(sack,'sack',0,.47,0,.94,1.18,.72);
 ball(sack,'sackLight',-.055,.51,.282,.68,.87,.13,false);
 cylinder(sack,'sack',0,1.09,0,.27,.13,.28);
 const cord=mesh(sack,new T.TorusGeometry(.18,.035,8,20),'leather',0,1.04,0);cord.rotation.x=Math.PI/2;
 tube(sack,'leatherLight',[[-.19,1.16,.07],[-.3,.84,.305],[-.29,.27,.29]],.026);
 for(const [x,y,z]of [[-.18,1.21,.04],[.02,1.25,.035],[.18,1.19,.03]]){
  const coin=cylinder(sack,'gold',x,y,z,.12,.12,.05);coin.rotation.x=Math.PI/2;
 }
 box(sack,'cloth',.17,.3,.345,.24,.24,.035,.02);
 for(const y of [.22,.31,.4])tube(sack,'boneShade',[[.045,y,.37],[.1,y+.015,.37]],.012);
 const left=arm(torso,-.55,.65,0,'goblin'),right=arm(torso,.52,.67,.12,'goblin');
 left.pivot.rotation.z=-.35;left.pivot.rotation.x=-.5;right.pivot.rotation.z=.18;
 for(const a of [left,right])cylinder(a.pivot,'leather',.025,-.43,.095,.172,.172,.2);
 const dagger=group(left.hand,-.03,.04,.15);dagger.rotation.z=.72;
 cylinder(dagger,'leather',0,0,0,.065,.065,.26);
 box(dagger,'gold',0,.18,0,.37,.09,.12,.035);
 slab(dagger,'metal',[[ -.095,.23],[.095,.23],[.14,.61],[0,.93],[-.08,.55]],-.035,.075,.013);
 slab(dagger,'metalLight',[[0,.24],[.085,.25],[.12,.6],[0,.9]],.052,.011,.004,false);
 const head=group(torso,-.05,1.36,.04);
 head.rotation.set(-.07,.20,.075);
 box(head,'goblin',0,.045,0,1.29,1.14,.88,.40);
 box(head,'goblinLight',0,-.25,.36,1.06,.52,.34,.2,false);
 ears(head,'goblin','goblinDark',.55,.08,-.05,1.13);
 const eyeParts=eyes(head,{x:.27,y:.12,z:.481,w:.33,h:.32,pupil:.13,slant:.14});
 // Raised eyebrow and one lifted mouth corner separate him from the orc.
 tube(head,'goblinDark',[[.12,.38,.444],[.32,.48,.384],[.47,.42,.344]],.026);
 const nose=ball(head,'goblinDark',0,-.105,.6,.33,.33,.47);nose.rotation.x=.1;
 tube(head,'ink',[[-.4,-.31,.536],[-.2,-.39,.57],[.03,-.38,.584],[.32,-.27,.549]],.027);
 for(const [x,y]of [[-.22,-.345],[.08,-.347]])box(head,'boneLight',x,y,.604,.135,.14,.06,.025);
 const earring=mesh(head,new T.TorusGeometry(.095,.025,8,20),'gold',-.94,-.1,.115);earring.rotation.y=-.2;
 // Small swept crest is a readable accent, not a second spherical cap.
 for(const [x,angle,h]of [[-.18,-.25,.28],[0,.03,.34],[.16,.27,.27]]){
  const tuft=mesh(head,new T.ConeGeometry(.14,h,5),'goblinDark',x,.64,-.09);tuft.rotation.z=angle;
 }
 bindIdle(root,{torso,head,arms:[left.pivot,right.pivot],phase:3.25,weight:1.05,eyes:eyeParts,style:'sneak'});return root;
}

export function buildWorldMonster(kind){
 switch(String(kind||'').toLowerCase()){
  case 'orc':return makeOrc();
  case 'skeleton':return makeSkeleton();
  case 'golem':return makeGolem();
  case 'goblin':return makeGoblin();
  default:throw new Error('Unknown world monster: '+kind);
 }
}

import * as T from './vendor/three.module.js';
import {createStorybookMaterial,addStorybookOutline} from './storybook-style.js';

// A real articulated model, rendered offline into small transparent flight loops.
// No WebGL scene or model geometry is loaded by the 2D world map.
export function buildMarchPhoenix(){
 const root=new T.Group(),bird=new T.Group();root.add(bird);
 const colors={red:'#a32d36',orange:'#f17b2e',gold:'#e6b044',sun:'#ffe48b',dark:'#493b33',ivory:'#fff1d2',obsidian:'#282634',bronze:'#9a6d35',ruby:'#ed4930'};
 const materials=Object.fromEntries(Object.entries(colors).map(([k,c])=>[k,createStorybookMaterial(c)]));
 const sphere=new T.SphereGeometry(1,16,10);
 function mesh(parent,geometry,color,position=[0,0,0],scale=[1,1,1],outline=true){const m=new T.Mesh(geometry,materials[color]);m.position.set(...position);m.scale.set(...scale);parent.add(m);if(outline)addStorybookOutline(m,.018);return m;}
 function ball(parent,color,x,y,z,sx,sy=sx,sz=sx){return mesh(parent,sphere,color,[x,y,z],[sx,sy,sz]);}
 function line(parent,color,points,width=.035){const curve=new T.CatmullRomCurve3(points.map(p=>new T.Vector3(...p)));return mesh(parent,new T.TubeGeometry(curve,Math.max(8,points.length*4),width,6,false),color,[0,0,0],[1,1,1],false);}
 function jewel(parent,x,y,z,size=.13){const gold=mesh(parent,new T.OctahedronGeometry(size*1.4),'gold',[x,y,z],[1,.65,1.25]);mesh(gold,new T.OctahedronGeometry(size),'ruby',[0,.06,0],[1,.8,1.2],false);return gold;}
 function feather(parent,color,length,width,position,rotation=0){
  const shape=new T.Shape();shape.moveTo(-width*.28,0);shape.bezierCurveTo(-width*.7,length*.35,-width*.4,length*.8,0,length);shape.bezierCurveTo(width*.58,length*.68,width*.63,length*.22,width*.28,0);shape.closePath();
  const geo=new T.ExtrudeGeometry(shape,{depth:.06,bevelEnabled:true,bevelSize:.045,bevelThickness:.035,bevelSegments:2,steps:1,curveSegments:8});
  const m=mesh(parent,geo,color,position);m.rotation.set(-Math.PI/2,0,rotation);
  // Broad dark vane, gilded quill and a flame tip survive the normal map zoom.
  const inset=new T.Shape();inset.moveTo(0,length*.12);inset.quadraticCurveTo(-width*.28,length*.49,0,length*.83);inset.quadraticCurveTo(width*.22,length*.46,0,length*.12);
  mesh(m,new T.ShapeGeometry(inset,8),color==='red'?'obsidian':'red',[0,0,.109],[1,1,1],false);
  line(m,'sun',[[0,length*.09,.119],[.014,length*.4,.121],[0,length*.88,.119]],.011);
  return m;
 }
 ball(bird,'red',0,0,0,.43,.42,.82);ball(bird,'gold',0,.02,.46,.34,.38,.55);
 // Obsidian barding and gold ribs echo the castle's black plinth and red towers.
 ball(bird,'obsidian',0,.27,-.10,.37,.20,.66);
 for(const s of [-1,1]){
  line(bird,'gold',[[s*.19,.39,.43],[s*.35,.38,.12],[s*.29,.33,-.53]],.048);
  for(let i=0;i<3;i++)line(bird,'gold',[[s*.06,.46,.25-i*.24],[s*.28,.41,.23-i*.24],[s*.37,.23,.17-i*.24]],.027);
 }
 jewel(bird,0,.49,.28,.17);
 const halo=new T.Group();halo.position.set(0,.29,.12);halo.rotation.x=.12;bird.add(halo);
 mesh(halo,new T.TorusGeometry(.68,.044,8,48),'gold');mesh(halo,new T.TorusGeometry(.58,.018,6,48),'bronze');
 for(let i=0;i<9;i++){const a=i*Math.PI/8;const ray=mesh(halo,new T.ConeGeometry(.055,i===4?.24:.15,5),'sun',[Math.cos(a)*.77,Math.sin(a)*.77,0]);ray.rotation.z=a-Math.PI/2;}
 const head=new T.Group();head.position.set(0,.36,.93);bird.add(head);
 ball(head,'gold',0,0,0,.34,.34,.36);ball(head,'sun',0,-.09,.20,.26,.20,.24);
 ball(head,'obsidian',0,.22,-.09,.26,.19,.30);line(head,'gold',[[-.23,.22,.15],[0,.38,.18],[.23,.22,.15]],.052);jewel(head,0,.39,.09,.095);
 const beak=mesh(head,new T.ConeGeometry(.145,.44,8),'bronze',[0,-.07,.51]);beak.rotation.x=Math.PI/2+.20;
 line(head,'sun',[[0,.025,.34],[0,-.015,.60],[0,-.14,.67]],.028);
 for(const s of [-1,1]){
  ball(head,'dark',s*.272,.057,.15,.077,.104,.11);
  ball(head,'sun',s*.319,.063,.178,.030,.052,.064);
  ball(head,'ivory',s*.334,.088,.204,.012,.018,.018);
  const brow=ball(head,'red',s*.267,.17,.13,.075,.045,.13);brow.rotation.z=s*.2;
  const foot=new T.Group();foot.position.set(s*.21,-.30,-.12);bird.add(foot);
  ball(foot,'orange',0,-.09,-.13,.065,.10,.23);
  for(let i=0;i<3;i++)ball(foot,'gold',(i-1)*.065,-.15,-.30,.037,.05,.16);
 }
 for(let i=0;i<5;i++){const crest=feather(head,i===2?'gold':'red',.55+(i===2?.28:0),.16,[(i-2)*.095,.24,-.18],(i-2)*.24);crest.rotation.x=-.5;}
 const wings=[];
 for(const s of [-1,1]){
  const shoulder=new T.Group();shoulder.position.set(s*.28,.12,.20);bird.add(shoulder);
  ball(shoulder,'red',s*.50,0,-.09,.66,.18,.42);
  ball(shoulder,'gold',s*.37,.11,.04,.43,.14,.34);ball(shoulder,'obsidian',s*.37,.21,.025,.34,.085,.255);jewel(shoulder,s*.36,.30,.045,.13);
  line(shoulder,'gold',[[s*.06,.21,.12],[s*.45,.23,.18],[s*.89,.13,-.02]],.048);
  const outer=new T.Group();outer.position.set(s*.94,0,-.19);shoulder.add(outer);
  ball(outer,'orange',s*.34,0,-.14,.55,.12,.28);
  for(let i=0;i<9;i++){
   const x=s*(.035+i*.145);feather(outer,i%3?'gold':'orange',.86+i*.057,.25,[x,.025,-.11],-s*(.37+i*.10));
   feather(outer,i%2?'red':'gold',.43+i*.018,.17,[x,.12,-.03],-s*(.37+i*.10));
  }
  for(let i=0;i<6;i++)feather(shoulder,i%2?'red':'gold',.81,.26,[s*(.18+i*.145),.12,-.12],-s*.18);
  line(outer,'gold',[[s*.02,.15,-.01],[s*.6,.13,.02],[s*1.25,.08,-.16]],.040);
  wings.push({s,shoulder,outer});
 }
 const tails=[];
 for(let i=0;i<5;i++){
  const base=new T.Group();base.position.set((i-2)*.15,-.04,-.64);base.rotation.y=(i-2)*.20;bird.add(base);
  const chain=[];let parent=base;
  for(let j=0;j<3;j++){
   const joint=new T.Group();if(j)joint.position.z=-(i===2?.74:.62);parent.add(joint);
   feather(joint,j===2?'gold':i===2?'gold':'red',j===2?1.04:.88,j===2?.30:.32,[0,.02,0]);
   chain.push(joint);parent=joint;
  }
  tails.push({base,chain,i});
 }
 root.userData.animateMarch=(time=0)=>{
  const phase=time/1.2*Math.PI*2;
  bird.position.y=Math.sin(phase-.3)*.085;bird.rotation.x=Math.sin(phase)*.045;
  head.rotation.x=Math.sin(phase-.4)*.06;halo.rotation.z=Math.sin(phase)*.04;
  for(const {s,shoulder,outer}of wings){shoulder.rotation.z=s*(.15+Math.sin(phase)*.52);shoulder.rotation.y=s*(-.10+Math.cos(phase)*.10);outer.rotation.z=s*(.10+Math.sin(phase-.65)*.28);}
  for(const {base,chain,i}of tails){base.rotation.y=(i-2)*.20+Math.sin(phase-i*.4)*.09;for(let j=0;j<chain.length;j++)chain[j].rotation.x=Math.sin(phase-j*.8-i*.35)*.19;}
 };
 root.userData.animateMarch(0);return root;
}

// Heavy emerald dragon for the Dragon Fortress set. The broad bat wings,
// horned head and articulated tail keep its silhouette distinct from the
// feathered phoenix even at the normal world-map size.
export function buildMarchDragon(){
 const root=new T.Group(),dragon=new T.Group();root.add(dragon);
 const colors={emerald:'#34785c',emeraldLight:'#62a977',emeraldDark:'#245142',basalt:'#393a3b',charcoal:'#292c2b',bronze:'#a8793f',bronzeLight:'#d2a45c',amber:'#f0a43c',glow:'#ffd67a',ivory:'#f2dfbd',dark:'#493b33'};
 const materials=Object.fromEntries(Object.entries(colors).map(([key,color])=>[key,createStorybookMaterial(color)]));
 // Wing membranes need to remain visible from both sides while banking.
 materials.emeraldDark=createStorybookMaterial(colors.emeraldDark,{side:T.DoubleSide});
 const sphere=new T.SphereGeometry(1,16,10);
 function mesh(parent,geometry,color,position=[0,0,0],scale=[1,1,1],outline=true){const m=new T.Mesh(geometry,materials[color]);m.position.set(...position);m.scale.set(...scale);parent.add(m);if(outline)addStorybookOutline(m,.02);return m;}
 function ball(parent,color,x,y,z,sx,sy=sx,sz=sx){return mesh(parent,sphere,color,[x,y,z],[sx,sy,sz]);}
 function line(parent,color,points,width=.035,outline=false){const curve=new T.CatmullRomCurve3(points.map(point=>new T.Vector3(...point)));return mesh(parent,new T.TubeGeometry(curve,Math.max(8,points.length*5),width,7,false),color,[0,0,0],[1,1,1],outline);}
 function cone(parent,color,position,scale,rotation=[0,0,0]){const part=mesh(parent,new T.ConeGeometry(1,1,7),color,position,scale);part.rotation.set(...rotation);return part;}
 function gem(parent,x,y,z,size=.1){const setting=mesh(parent,new T.OctahedronGeometry(size*1.42),'bronze',[x,y,z],[1,.72,1.2]);mesh(setting,new T.OctahedronGeometry(size),'amber',[0,.055,0],[1,.82,1.15],false);return setting;}

 // A compact, stout body reads as a dragon instead of a long flying snake.
 ball(dragon,'emerald',0,.02,-.02,.48,.42,.92);
 ball(dragon,'emeraldLight',0,-.20,.18,.34,.18,.65,false);
 ball(dragon,'basalt',0,.25,-.04,.44,.20,.69);
 // Aged bronze saddle plates visually link the creature to the fortress.
 for(const z of [-.42,-.05,.32]){
  const plate=mesh(dragon,new T.TorusGeometry(.34,.048,7,24,.94),'bronze',[0,.34,z],[1,.72,1]);plate.rotation.set(Math.PI/2,0,.32);
 }
 line(dragon,'bronzeLight',[[-.37,.32,.45],[-.42,.39,.02],[-.34,.32,-.54]],.044);
 line(dragon,'bronzeLight',[[.37,.32,.45],[.42,.39,.02],[.34,.32,-.54]],.044);
 gem(dragon,0,.51,.22,.14);

 const neck=new T.Group();neck.position.set(0,.10,.67);dragon.add(neck);
 ball(neck,'emerald',0,.08,.22,.36,.34,.48);
 ball(neck,'emeraldLight',0,-.15,.28,.27,.13,.38,false);
 const head=new T.Group();head.position.set(0,.17,.53);neck.add(head);
 ball(head,'emerald',0,0,0,.42,.34,.41);
 ball(head,'emeraldLight',0,-.12,.31,.37,.21,.37);
 ball(head,'basalt',0,.25,-.05,.37,.17,.31);
 // Oversized muzzle, brows and horns make the face readable at 104 x 84 px.
 ball(head,'basalt',0,-.10,.48,.33,.17,.27);
 for(const side of [-1,1]){
  ball(head,'charcoal',side*.29,.055,.25,.095,.09,.11);
  ball(head,'amber',side*.326,.07,.292,.047,.055,.052,false);
  ball(head,'glow',side*.34,.09,.317,.018,.024,.021,false);
  const brow=ball(head,'bronze',side*.27,.17,.23,.17,.055,.12);brow.rotation.z=side*.18;
  const horn=cone(head,'ivory',[side*.29,.26,-.19],[.105,.53,.105],[.34,0,-side*.33]);horn.rotation.x=-.38;
  const cheek=cone(head,'bronze',[side*.39,-.04,.07],[.07,.25,.07],[0,0,-side*1.18]);
  cheek.rotation.x=.28;
 }
 for(const side of [-1,1])ball(head,'charcoal',side*.16,-.18,.66,.034,.025,.038,false);
 const noseHorn=cone(head,'bronze',[0,.06,.70],[.075,.29,.075],[Math.PI/2-.15,0,0]);noseHorn.rotation.x=Math.PI/2-.15;
 line(head,'ivory',[[-.22,-.19,.58],[0,-.24,.69],[.22,-.19,.58]],.018);
 // Crown-like central horns echo the Dragon Fortress without becoming a logo.
 for(const [x,y,z,scale,lean]of [[-.16,.33,-.12,.75,-.16],[0,.39,-.19,1,0],[.16,.33,-.12,.75,.16]]){
  const horn=cone(head,'bronzeLight',[x,y,z],[.075,.38*scale,.075]);horn.rotation.z=lean;
 }

 const wings=[];
 for(const side of [-1,1]){
  const wing=new T.Group();wing.position.set(side*.30,.20,.28);wing.scale.x=side;dragon.add(wing);
  ball(wing,'basalt',.28,.02,-.06,.44,.18,.34);
  // The membrane is a single calm color field framed by four thick fingers.
  const shape=new T.Shape();shape.moveTo(.08,0);shape.lineTo(.72,-.04);shape.lineTo(1.34,-.22);shape.lineTo(2.33,-.69);shape.lineTo(1.94,.05);shape.lineTo(2.42,.48);shape.lineTo(1.40,.39);shape.lineTo(.72,.28);shape.closePath();
  const membrane=mesh(wing,new T.ExtrudeGeometry(shape,{depth:.055,bevelEnabled:true,bevelSize:.025,bevelThickness:.02,bevelSegments:1,steps:1}),'emeraldDark',[0,-.055,0]);membrane.rotation.x=-Math.PI/2;
  const fingers=[[[.04,.04,0],[.74,.05,-.04],[2.34,.05,-.70]],[[.08,.07,.02],[.79,.07,.13],[1.95,.07,.02]],[[.08,.08,.04],[.70,.08,.25],[2.40,.08,.46]],[[.05,.075,.04],[.55,.075,.34],[1.39,.075,.38]]];
  for(const points of fingers)line(wing,'bronze',points,.043,true);
  line(wing,'emeraldLight',[[.44,.075,-.01],[1.00,.075,-.02],[1.78,.075,-.34]],.025);
  const claw=cone(wing,'ivory',[2.39,.055,-.70],[.055,.23,.055]);claw.rotation.z=-1.05;
  gem(wing,.31,.22,-.02,.11);
  wings.push({side,wing,membrane});
 }

 // Short hind legs and broad claws preserve the toy-like proportions.
 const legs=[];
 for(const side of [-1,1]){
  const leg=new T.Group();leg.position.set(side*.33,-.22,-.34);dragon.add(leg);
  ball(leg,'basalt',0,0,0,.18,.24,.26);ball(leg,'emerald',side*.06,-.21,-.08,.13,.25,.15);
  const foot=ball(leg,'bronze',side*.08,-.41,.08,.20,.09,.26);
  for(let i=0;i<3;i++){const claw=cone(foot,'ivory',[(i-1)*.11,-.02,.25],[.035,.18,.035]);claw.rotation.x=Math.PI/2;}
  legs.push({side,leg});
 }

 // Jointed tapering tail with a basalt spade. Every link receives a delayed
 // sway so the loop remains fluid without requiring runtime bones or shaders.
 const tail=[],tailRoot=new T.Group();tailRoot.position.set(0,.03,-.79);dragon.add(tailRoot);let parent=tailRoot;
 for(let i=0;i<5;i++){
  const joint=new T.Group();if(i)joint.position.z=-(.40-i*.035);parent.add(joint);
  ball(joint,i<2?'emerald':'emeraldDark',0,0,-.18,.25-i*.032,.20-i*.026,.34-i*.027);
  if(i<4){const spike=cone(joint,i%2?'bronze':'basalt',[0,.19-i*.02,-.16],[.055,.26-i*.022,.055]);spike.rotation.x=-.20;}
  tail.push(joint);parent=joint;
 }
 const spade=mesh(parent,new T.OctahedronGeometry(.24),'basalt',[0,0,-.51],[.70,.38,1.25]);spade.rotation.x=.24;

 root.userData.motionPeriod=1.6;
 root.userData.creature='dragon';
 root.userData.animateMarch=(time=0)=>{
  const phase=time/root.userData.motionPeriod*Math.PI*2,flap=Math.sin(phase);
  dragon.position.y=Math.sin(phase-.24)*.075;
  dragon.rotation.x=-.015+Math.sin(phase-.55)*.035;
  neck.rotation.x=Math.sin(phase-.35)*.045;
  head.rotation.x=Math.sin(phase-.85)*.055;
  for(const {side,wing}of wings){wing.rotation.z=side*(-.05+flap*.43);wing.rotation.y=side*(.08+Math.cos(phase)*.07);wing.rotation.x=Math.sin(phase-.45)*.055;}
  for(const {side,leg}of legs){leg.rotation.x=.10+Math.sin(phase+side*.35)*.07;leg.rotation.z=side*(.06+Math.cos(phase)*.035);}
  for(let i=0;i<tail.length;i++){tail[i].rotation.y=Math.sin(phase-i*.48)*(.10+i*.018);tail[i].rotation.x=Math.cos(phase-i*.38)*(.035+i*.008);}
 };
 root.userData.animateMarch(0);return root;
}

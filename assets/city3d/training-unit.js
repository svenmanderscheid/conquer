import * as T from './vendor/three.module.js';
import {storybookMaterials as M,createStorybookMaterial,addStorybookOutline} from './storybook-style.js?v=storybook5';

// Shared miniature figures: broad silhouettes, matte pigments and oversized gear.
const skin=createStorybookMaterial('#eab48b'),hair=createStorybookMaterial('#a46c38');
export function buildTrainingUnit(type=1,tier=1){
 const root=new T.Group(),person=new T.Group();root.add(person);
 const cloth=type===2?M.leaf:type===3?M.teal:M.blue,metal=tier>=9?M.gold:M.stone;
 const parts=[];
 function mesh(geo,mat,x,y,z,sx=1,sy=1,sz=1,parent=person,ink=true){const m=new T.Mesh(geo,mat);m.position.set(x,y,z);m.scale.set(sx,sy,sz);parent.add(m);m.castShadow=true;if(ink)addStorybookOutline(m,.022);parts.push(m);return m;}
 const ball=(mat,x,y,z,sx,sy,sz,parent=person,ink=true)=>mesh(new T.SphereGeometry(1,16,12),mat,x,y,z,sx,sy,sz,parent,ink);
 const box=(mat,x,y,z,w,h,d,parent=person,ink=true)=>mesh(new T.BoxGeometry(w,h,d),mat,x,y,z,1,1,1,parent,ink);
 function tube(points,mat,r=.04,parent=person){return mesh(new T.TubeGeometry(new T.CatmullRomCurve3(points.map(p=>new T.Vector3(...p))),18,r,7,false),mat,0,0,0,1,1,1,parent);}
 let pony=null;
 if(type===3){
  pony=new T.Group();pony.name='training-mount';root.add(pony);person.position.set(0,.82,-.12);
  ball(M.timber,0,.67,0,.46,.46,.69,pony);ball(M.wood,0,.81,-.34,.39,.31,.3,pony);
  for(const x of [-.29,.29])for(const z of [-.4,.39]){ball(M.timber,x,.31,z,.14,.29,.16,pony);ball(M.dark,x,.08,z+.035,.16,.09,.2,pony);}
  ball(M.timber,0,1.16,.49,.3,.46,.31,pony);ball(M.timber,0,1.44,.72,.36,.37,.35,pony);ball(M.wood,0,1.29,1,.32,.19,.22,pony);
  for(const side of [-1,1]){ball(M.timber,side*.22,1.8,.64,.1,.23,.1,pony);ball(M.dark,side*.24,1.5,.96,.055,.065,.03,pony,false);ball(M.dark,side*.16,1.33,1.2,.043,.032,.02,pony,false);}
  for(let i=0;i<4;i++)ball(M.wood,(i%2?-.03:.03),1.65-i*.14,.5-i*.09,.27,.16,.14,pony);
  tube([[0,.94,-.53],[.14,.79,-.82],[.17,.37,-.87]],M.wood,.11,pony);
  ball(cloth,0,.82,-.03,.49,.14,.51,pony);box(M.wood,0,1,-.14,.67,.12,.54,pony);
  tube([[-.27,1.3,1],[-.34,1.5,.69],[0,1.73,.48],[.34,1.5,.69],[.27,1.3,1]],M.gold,.035,pony);
  if(tier>=5)ball(metal,0,1.67,.89,.31,.16,.28,pony);
  if(tier>=7){for(const side of [-1,1])ball(metal,side*.39,.85,.1,.075,.3,.38,pony);}
 }
 const body=ball(cloth,0,.79,0,.39,.44,.29);
 for(const side of [-1,1]){
  const leg=ball(M.dark,side*.19,.32,.015,.16,.25,.17);leg.rotation.z=type===3?side*.35:0;
  ball(tier>=6?metal:M.wood,side*.2,.13,.12,.21,.14,.29);
 }
 ball(M.wood,0,.63,0,.4,.09,.3);box(tier>=4?M.gold:M.trim,0,.63,.308,.16,.13,.05);
 if(tier>=4){ball(metal,0,.91,.08,.4,.3,.27);ball(cloth,0,.87,.32,.25,.25,.036);}
 if(tier>=6){const cape=box(cloth,0,.78,-.32,.65,.76,.06);cape.rotation.x=-.14;}
 // A head close to half the figure's height, with readable eyes and nose.
 ball(type===2?cloth:M.dark,0,1.47,-.035,.5,.51,.43);
 ball(skin,0,1.47,.135,.41,.4,.32);
 ball(skin,0,1.41,.472,.085,.09,.065,person,false);
 for(const side of [-1,1]){
  ball(M.trim,side*.165,1.53,.417,.105,.088,.036,person,false);
  ball(type===2?M.wood:M.blueDark,side*.165,1.527,.449,.052,.064,.019,person,false);
  ball(M.dark,side*.161,1.529,.464,.027,.045,.012,person,false);
  ball(M.trim,side*.15,1.55,.476,.011,.017,.006,person,false);
  const brow=box(M.wood,side*.167,1.638,.42,.18,.045,.041);brow.rotation.z=side*.15;
 }
 tube([[-.1,1.285,.424],[0,1.265,.45],[.1,1.286,.424]],M.wood,.018);
 if(type===3){for(const s of [-1,1]){const mustache=ball(M.wood,s*.115,1.34,.437,.135,.045,.065);mustache.rotation.z=s*.19;}}
 if(type===2){
  for(let i=0;i<4;i++){const fringe=ball(hair,-.24+i*.15,1.77,.34,.12,.16,.105);fringe.rotation.z=-.25+i*.1;}
  if(tier>=4){const band=mesh(new T.TorusGeometry(.436,.045,6,28,Math.PI),metal,0,1.48,.13);band.rotation.z=0;}
  const feather=ball(tier>=7?M.gold:M.trim,.37,1.91,-.02,.065,.25,.035);feather.rotation.z=-.35;
 }else if(tier>=2){
  mesh(new T.SphereGeometry(.51,20,12,0,Math.PI*2,0,Math.PI/2),tier>=7?metal:tier>=4?M.stone:M.wood,0,1.54,-.015,1,.86,.87);
  const rim=mesh(new T.TorusGeometry(.48,.048,6,28),metal,0,1.56,0,1,1,.9);rim.rotation.x=Math.PI/2;
  if(tier>=5)box(metal,0,1.56,.457,.085,.35,.055);
  if(tier>=7){for(const side of [-1,1])ball(metal,side*.385,1.36,.21,.09,.22,.22);}
  if(tier>=8){const crest=ball(tier>=9?M.gold:M.red,0,1.99,-.06,.067,.24,.31);crest.rotation.x=.15;}
 }
 const armL=new T.Group(),armR=new T.Group();armL.position.set(-.39,1.02,0);armR.position.set(.39,1.02,0);person.add(armL,armR);
 for(const arm of [armL,armR]){ball(cloth,0,-.08,0,.15,.24,.16,arm);ball(skin,0,-.32,.08,.14,.14,.14,arm);if(tier>=3)ball(tier>=4?metal:M.wood,0,.015,0,.2,.16,.21,arm);}
 if(type===1){
  armL.rotation.x=-.28;armR.rotation.x=-.2;armR.rotation.z=-.16;
  const shield=new T.Group();shield.position.set(0,-.22,.28);armL.add(shield);
  ball(tier>=3?metal:M.wood,0,0,0,.41,.5,.075,shield);
  ball(tier>=4?M.blue:M.timber,0,0,.058,.34,.42,.03,shield);
  ball(tier>=8?M.gold:M.stone,0,0,.108,.11,.13,.07,shield);
  if(tier>=6){box(M.gold,0,0,.095,.05,.65,.025,shield);box(M.gold,0,.1,.098,.5,.05,.025,shield);}
  const sword=new T.Group();sword.position.set(.04,-.32,.1);sword.rotation.x=-.45;armR.add(sword);
  box(M.wood,0,0,0,.08,.2,.08,sword);box(M.gold,0,.1,0,.34,.075,.12,sword);
  const blade=new T.Shape();blade.moveTo(-.085,.14);blade.lineTo(.085,.14);blade.lineTo(.075,.64+tier*.015);blade.lineTo(0,.82+tier*.015);blade.lineTo(-.075,.64+tier*.015);blade.closePath();mesh(new T.ExtrudeGeometry(blade,{depth:.06,bevelEnabled:true,bevelSize:.01,bevelThickness:.01,bevelSegments:1}),tier>=9?M.gold:M.stone,0,0,0,1,1,1,sword);
 }else if(type===2){
  armL.rotation.x=-1.0;armL.rotation.z=.32;armR.rotation.x=-.7;armR.rotation.z=-.3;
  const bow=new T.Group();bow.position.set(0,-.32,.15);bow.rotation.x=1;armL.add(bow);
  tube([[0,-.58,0],[.2,-.33,0],[.28,0,0],[.2,.33,0],[0,.58,0]],tier>=8?M.gold:M.wood,.045,bow);
  tube([[0,-.58,0],[-.12,0,0],[0,.58,0]],M.trim,.012,bow);
  box(M.timber,0,0,.015,.75,.026,.026,bow);const tip=mesh(new T.ConeGeometry(.055,.13,5),M.stone,.44,0,.015,1,1,1,bow);tip.rotation.z=-Math.PI/2;
  mesh(new T.CylinderGeometry(.14,.11,.6,10),M.wood,.33,1,-.32,1,1,1).rotation.z=-.35;
  for(let i=0;i<3;i++)box(M.trim,.28+i*.06,1.42,-.3,.025,.35,.025);
 }else{
  armL.rotation.x=-.6;armR.rotation.x=-.15;armR.rotation.z=-.2;
  box(M.timber,.1,.07,.15,.055,1.7,.055,armR);mesh(new T.ConeGeometry(.115,.36,6),tier>=8?M.gold:M.stone,.1,1.06,.15,1,1,1,armR);
  if(tier>=5){const flag=box(M.blue,.26,.68,.15,.32,.22,.035,armR);flag.rotation.z=-.1;}
 }
 root.rotation.y=-.25;
 return {root,animate(t,reduced=false){if(reduced)return;person.position.y=(type===3?.82:0)+Math.sin(t*1.8)*.018;person.rotation.z=Math.sin(t*1.1)*.015;armR.rotation.x=(type===1?-.2:type===2?-.7:-.15)+Math.sin(t*1.7)*.035;if(pony)pony.rotation.z=Math.sin(t*1.2)*.012;},dispose(){const geometry=new Set();root.traverse(o=>{if(o.isMesh)geometry.add(o.geometry);});geometry.forEach(g=>g.dispose());}};
}

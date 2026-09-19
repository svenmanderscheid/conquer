import * as T from './vendor/three.module.js';
import {storybookColors as C} from './storybook-style.js';

function seamNormals(g,rows,sides){const n=g.attributes.normal;for(let r=0;r<rows;r++){const a=r*(sides+1),b=a+sides,v=new T.Vector3(n.getX(a)+n.getX(b),n.getY(a)+n.getY(b),n.getZ(a)+n.getZ(b)).normalize();n.setXYZ(a,v.x,v.y,v.z);n.setXYZ(b,v.x,v.y,v.z);}return g;}

// Authored cross-sections and swept locks. All points are in the family's bind
// space; the builder assigns the resulting surface to the existing rig.
function loft(rings,segments=24){
 const p=[],uv=[],idx=[];
 for(let row=0;row<rings.length;row++){
  const [y,rx,rz,cx=0,cz=0,power=1]=rings[row];
  for(let i=0;i<=segments;i++){const a=i/segments*Math.PI*2,s=Math.sin(a),c=Math.cos(a);p.push(cx+Math.sign(s)*Math.pow(Math.abs(s),power)*rx,y,cz+Math.sign(c)*Math.pow(Math.abs(c),power)*rz);uv.push(i/segments,row/(rings.length-1));}
 }
 for(let row=0;row<rings.length-1;row++)for(let i=0;i<segments;i++){const a=row*(segments+1)+i,b=a+segments+1;idx.push(a,a+1,b,b,a+1,b+1);}
 const g=new T.BufferGeometry();g.setAttribute('position',new T.Float32BufferAttribute(p,3));g.setAttribute('uv',new T.Float32BufferAttribute(uv,2));g.setIndex(idx);g.computeVertexNormals();return seamNormals(g,rings.length,segments);
}
function lock(points,width,depth){
 const curve=new T.CatmullRomCurve3(points.map(p=>new T.Vector3(...p))),p=[],uv=[],idx=[],steps=12,sides=10;
 for(let j=0;j<=steps;j++){
  const t=j/steps,c=curve.getPoint(t),tangent=curve.getTangent(t),side=new T.Vector3(tangent.y,-tangent.x,0).normalize();
  const profile=Math.pow(Math.sin(Math.PI*(.15+.85*t)),.8)*(1-t*.4),w=Math.max(.0007,width*profile),d=Math.max(.0005,depth*profile);
  for(let i=0;i<=sides;i++){const a=i/sides*Math.PI*2,v=c.clone().addScaledVector(side,Math.cos(a)*w);v.z+=Math.sin(a)*d;p.push(v.x,v.y,v.z);uv.push(i/sides,t);}
 }
 for(let j=0;j<steps;j++)for(let i=0;i<sides;i++){const a=j*(sides+1)+i,b=a+sides+1;idx.push(a,b,a+1,a+1,b,b+1);}
 const g=new T.BufferGeometry();g.setAttribute('position',new T.Float32BufferAttribute(p,3));g.setAttribute('uv',new T.Float32BufferAttribute(uv,2));g.setIndex(idx);g.computeVertexNormals();return seamNormals(g,steps+1,sides);
}

export function sculptInfantry({add,ball,band,shape},cloth){
 const skin='#efbc94',hair=C.trim;
 const surface=(bone,color,rings,n=24)=>add(loft(rings,n),bone,color,0,0,0);
 const strand=(points,w,d,color=hair)=>add(lock(points,w,d),'head',color,0,0,0);
 // Fitted jacket: narrow waist, broad shoulders, cream front facing.
 surface('chest',cloth,[[.64,.205,.14],[.7,.225,.16],[.88,.28,.18],[1.01,.3,.16],[1.075,.18,.12],[1.09,.12,.1]]);
 shape('chest',C.trim,[[-.027,.71],[.025,.71],[.026,1.025],[-.027,1.025]],.178,.014);
 // Two curved coat tails, cut away at the front instead of a cylindrical skirt.
 for(const side of [-1,1]){
  const points=[[side*.025,.65],[side*.222,.66],[side*.317,.425],[side*.10,.385],[side*.038,.44]];
  shape('hips',C.trim,points,.085,.13);
  shape('hips',cloth,[[side*.04,.65],[side*.215,.65],[side*.288,.447],[side*.108,.421],[side*.06,.46]],.228,.012);
 }
 surface('hips',C.wood,[[.642,.234,.18],[.66,.241,.19],[.717,.236,.185],[.727,.23,.18]]);
 shape('hips',C.gold,[[-.074,.635],[.074,.635],[.074,.734],[-.074,.734]],.206,.035);
 shape('hips',C.wood,[[-.039,.66],[.039,.66],[.039,.71],[-.039,.71]],.247,.008);
 for(const [s,k]of [[-1,'L'],[1,'R']]){
  surface('thigh'+k,C.dark,[[.23,.078,.082,s*.19],[.3,.091,.085,s*.183],[.46,.1,.09,s*.16],[.53,.07,.07,s*.16]],16);
  // Boot has an ankle, heel and broad toe rather than a flattened sphere.
  surface('foot'+k,C.dark,[[.012,.112,.19,s*.19,.075,.65],[.045,.12,.2,s*.19,.075,.65],[.066,.12,.2,s*.19,.075,.65]],20);
  surface('foot'+k,C.wood,[[.05,.113,.185,s*.19,.075,.65],[.095,.118,.19,s*.19,.075,.7],[.16,.098,.153,s*.19,.049,.8],[.205,.082,.102,s*.19,0],[.25,.079,.085,s*.19,-.005]],20);
  surface('shin'+k,C.wood,[[.18,.08,.082,s*.19],[.26,.084,.086,s*.18],[.335,.096,.09,s*.17]],16);
  surface('shin'+k,C.timber,[[.284,.101,.098,s*.18],[.324,.115,.107,s*.18],[.359,.116,.11,s*.17],[.374,.106,.102,s*.17]],16);
  surface('upper'+k,cloth,[[.785,.073,.082,s*.39],[.88,.105,.1,s*.37],[.99,.125,.12,s*.32],[1.039,.1,.1,s*.30]],16);
  ball('upper'+k,C.wood,s*.335,1.005,.015,.29,.18,.27);
  ball('upper'+k,C.stone,s*.335,1.04,.025,.28,.14,.265);
  surface('lower'+k,C.wood,[[.62,.072,.078,s*.39,.06],[.72,.085,.087,s*.39,.035],[.81,.077,.08,s*.39,.018]],16);
  band('lower'+k,C.timber,s*.39,.765,.03,.092,.086,.052);
  ball('hand'+k,C.wood,s*.39,.612,.08,.205,.185,.195);
  ball('hand'+k,C.timber,s*.32,.64,.15,.082,.115,.09,-s*.3);
 }
 // A shaped jaw, cheeks and broad forehead, with a gently flattened facial plane.
 surface('head',skin,[[1.13,.06,.075,0,.045],[1.16,.18,.145,0,.035],[1.21,.277,.215,0,.02],[1.30,.35,.258,0,.018,.85],[1.41,.378,.28,0,.008,.8],[1.55,.369,.279,0,-.01,.85],[1.68,.319,.25,0,-.02],[1.75,.22,.19,0,-.02],[1.79,.015,.02,0,-.02]],32);
 for(const s of [-1,1]){
  const ear=new T.BufferGeometry();ear.setAttribute('position',new T.Float32BufferAttribute([s*.31,1.43,.04,s*.51,1.48,.005,s*.41,1.31,.02,s*.34,1.32,.09,s*.385,1.39,.115,s*.375,1.38,-.055],3));
  const faces=[0,1,4,1,2,4,2,3,4,3,0,4,1,0,5,2,1,5,3,2,5,0,3,5];ear.setIndex(s<0?faces:faces.reduce((a,_,i)=>{if(i%3===0)a.push(faces[i],faces[i+2],faces[i+1]);return a;},[]));ear.setAttribute('uv',new T.Float32BufferAttribute(Array(12).fill(0),2));ear.computeVertexNormals();add(ear,'head',skin,0,0,0);
  shape('head','#d99069',[[s*.35,1.405],[s*.463,1.443],[s*.402,1.353]],.075,.006);
  // Strong eyes and inward-slanting brows carry expression at thumbnail size.
  ball('head',C.dark,s*.156,1.398,.287,.083,.149,.025,-s*.06);
  ball('head',C.trim,s*.146,1.435,.305,.021,.028,.008);
  ball('head',C.wood,s*.155,1.505,.293,.169,.038,.027,s*.27);
  ball('head','#df9875',s*.265,1.293,.233,.098,.045,.018);
 }
 ball('head',skin,0,1.331,.291,.062,.059,.043);
 ball('head',C.wood,.015,1.226,.255,.065,.012,.01,.09);
 // Close-fitting back volume, then individually directed locks with tapered ends.
 add(new T.SphereGeometry(1,24,12,0,Math.PI*2,0,Math.PI*.61),'head','#e7d8bd',0,1.48,-.073,.399,.362,.302);
 for(const s of [-1,1]){
  strand([[s*.19,1.71,-.09],[s*.34,1.59,-.14],[s*.44,1.44,-.13],[s*.40,1.23,-.11]],.125,.13);
  strand([[s*.27,1.51,-.19],[s*.31,1.33,-.23],[s*.37,1.18,-.2],[s*.3,1.10,-.18]],.105,.105,'#eadbc2');
  strand([[s*.11,1.41,-.31],[s*.19,1.24,-.3],[s*.26,1.15,-.27]],.115,.09);
 }
 strand([[-.07,1.72,-.04],[-.12,1.9,-.02],[.04,2.00,-.01],[.015,2.07,-.025]],.09,.075);
 strand([[.12,1.73,.01],[.27,1.86,.005],[.39,1.89,.0]],.095,.075);
 strand([[-.13,1.71,.04],[-.33,1.77,.07],[-.46,1.8,.04]],.12,.085);
 strand([[-.19,1.77,.18],[-.32,1.66,.26],[-.43,1.53,.19],[-.48,1.57,.13]],.142,.105);
 strand([[-.03,1.82,.19],[-.15,1.73,.30],[-.25,1.57,.31],[-.29,1.49,.28]],.152,.12);
 strand([[.12,1.80,.17],[.16,1.68,.30],[.10,1.53,.32],[.015,1.47,.30]],.15,.105);
 strand([[.23,1.78,.11],[.34,1.65,.24],[.34,1.50,.235],[.29,1.39,.23]],.13,.10);
 // Folded scarf wraps neck and ends in two freely moving tails.
 surface('chest',C.orangeDark,[[1.01,.19,.145],[1.035,.238,.175],[1.12,.21,.15],[1.15,.17,.115]],24);
 add(lock([[-.22,1.12,.06],[-.12,1.044,.195],[.075,1.029,.206],[.215,1.123,.12]],.079,.048),'chest',C.orange,0,0,0);
 add(lock([[-.20,1.08,.06],[0,1.105,.18],[.22,1.14,.07]],.059,.049),'chest',C.orange,0,0,0);
 shape('scarf',C.orangeDark,[[-.17,1.11],[-.31,1.02],[-.41,.79],[-.55,.68],[-.46,.69],[-.5,.56],[-.28,.75]],-.22,.035);
 shape('scarf',C.orange,[[-.18,1.12],[-.32,.94],[-.51,.77],[-.61,.76],[-.5,.65],[-.29,.84]],-.26,.04);
 // Faceted blade and chunky guard, angled away from the face in bind pose.
 const sword=new T.Shape();sword.moveTo(-.082,0);sword.lineTo(-.084,.40);sword.lineTo(0,.61);sword.lineTo(.084,.40);sword.lineTo(.082,0);sword.closePath();
 const blade=new T.ExtrudeGeometry(sword,{depth:.062,bevelEnabled:true,bevelSize:.012,bevelThickness:.012,bevelSegments:1});
 add(blade,'handR',C.gold,.47,.76,.17,1,1,1,-.48);
 add(new T.BoxGeometry(.046,.45,.009),'handR',C.glow,.58,.965,.249,1,1,1,-.48);
 add(new T.CylinderGeometry(.044,.046,.25,12),'handR',C.wood,.407,.643,.17,1,1,1,-.48);
 add(new T.BoxGeometry(.29,.068,.105),'handR',C.gold,.465,.752,.17,1,1,1,-.48);
 ball('handR',C.gold,.35,.533,.17,.09,.09,.095);
 // Shield: thick wood rim, blue inset, four ivory petals and central boss.
 ball('handL',C.wood,-.46,.69,.239,.51,.6,.11);
 ball('handL',C.gold,-.46,.69,.288,.495,.582,.07);
 ball('handL',C.blue,-.46,.69,.323,.418,.502,.041);
 for(const [dx,dy]of [[0,.16],[0,-.16],[.13,0],[-.13,0]]){
  const x=-.46+dx,y=.69+dy;
  shape('handL',C.trim,[[x,y+.066],[x+.036,y],[x,y-.066],[x-.036,y]],.35,.007);
 }
 ball('handL',C.wood,-.46,.69,.368,.147,.15,.036);
 ball('handL',C.gold,-.46,.69,.39,.115,.12,.041);
}

import * as T from './vendor/three.module.js';
import {storybookMaterials as M} from './storybook-style.js';

// These small models are also the source of the map's transparent scenery sprites.
// Keep the same painted lighting response as the castles and village buildings.
const colors={pine:'#397655',pineLight:'#6b995c',pineDark:'#275b45',oak:'#73a04e',oakLight:'#a4b95f',oakDark:'#477641',bark:'#78553b',barkLight:'#aa7c50',rock:'#949a99',rockLight:'#bec3b9',rockDark:'#697b7d',snow:'#e4efdb',pink:'#ea9eb1',pinkLight:'#ffd1cc',pinkDark:'#c77593',grass:'#829956'};
const materials=Object.fromEntries(Object.entries(colors).map(([name,color])=>{
 const material=M.leaf.clone();material.color.set(color);
 material.onBeforeCompile=M.leaf.onBeforeCompile;material.customProgramCacheKey=M.leaf.customProgramCacheKey;
 return [name,material];
}));
function mesh(parent,geometry,color,x,y,z,sx=1,sy=1,sz=1){
 const part=new T.Mesh(geometry,materials[color]);part.position.set(x,y,z);part.scale.set(sx,sy,sz);parent.add(part);return part;
}
function branch(parent,a,b,r=.09){
 const start=new T.Vector3(...a),end=new T.Vector3(...b),delta=end.clone().sub(start);
 const part=mesh(parent,new T.CylinderGeometry(r*.65,r,delta.length(),7),'bark',...start.clone().add(end).multiplyScalar(.5).toArray());
 part.quaternion.setFromUnitVectors(new T.Vector3(0,1,0),delta.normalize());return part;
}
function stone(parent,x,y,z,scale=.3,color='rock'){
 const part=mesh(parent,new T.DodecahedronGeometry(1,0),color,x,y,z,scale,scale*.65,scale*.8);part.rotation.set(.15,x*1.3,z+.2);return part;
}
function tuft(parent,x,z,scale=.15){
 for(let i=0;i<3;i++){
  const part=mesh(parent,new T.ConeGeometry(scale*.27,scale*1.8,5),'grass',x+(i-1)*scale*.18,scale*.65,z,1,1,.8);
  part.rotation.z=(i-1)*.35;
 }
}
function tree(kind){
 const root=new T.Group();
 branch(root,[0,.02,0],[.07,2.3,.02],kind==='pine'?.14:.19);
 // Roots and exposed limbs give the crowns a solid connection to the ground.
 for(let i=0;i<5;i++){const angle=i*Math.PI*2/5;branch(root,[.02,.2,0],[Math.cos(angle)*.34,.025,Math.sin(angle)*.34],.065);}
 if(kind==='pine'){
  for(let tier=0;tier<4;tier++){
   const y=1.05+tier*.48,r=.98-tier*.18;
   const skirt=mesh(root,new T.ConeGeometry(r,1.15,9,1),'pine',0,y+.25,0,1,1,.91);skirt.rotation.y=tier*.37;
   const top=mesh(root,new T.ConeGeometry(r*.8,.83,9,1),'pineLight',-.07,y+.42,.025,1,1,.93);top.rotation.y=tier*.37;
   for(let i=0;i<4;i++){const angle=i*Math.PI/2+tier*.4;branch(root,[0,y-.12,0],[Math.cos(angle)*r*.76,y-.19,Math.sin(angle)*r*.76],.045);}
  }
 }else{
  const cherry=kind==='cherry';
  const crowns=[[-.68,1.88,.08,.66],[.55,2.05,.15,.75],[-.12,2.6,-.16,.76],[-.25,2.05,.64,.69],[.46,2.55,.35,.57],[-.65,2.37,-.22,.52],[.1,2.13,-.59,.66]];
  crowns.forEach(([x,y,z,r],i)=>{
   branch(root,[.04,1.05,0],[x,y-.15,z],.08);
   const color=cherry?(i%3===0?'pinkDark':i%3===1?'pink':'pinkLight'):(i%3===0?'oakDark':i%3===1?'oak':'oakLight');
   const crown=mesh(root,new T.IcosahedronGeometry(r,1),color,x,y,z,1, .83,1);crown.rotation.y=i*.7;
   if(i%2===0)mesh(root,new T.IcosahedronGeometry(r*.55,1),cherry?'pinkLight':'oakLight',x-.15,y+r*.4,z+.1,1,.66,1);
  });
  if(cherry)for(let i=0;i<6;i++){const angle=i*2.4;mesh(root,new T.DodecahedronGeometry(.055),'pink',Math.cos(angle)*(.3+i*.07),.035,Math.sin(angle)*(.3+i*.06),1,.35,1);}
 }
 stone(root,.32,.055,-.21,.14);tuft(root,-.32,.18,.16);tuft(root,.23,.3,.12);
 return root;
}
function crag(parent,x,z,height,radius,rotation=0){
 const group=new T.Group();group.position.set(x,0,z);group.rotation.y=rotation;parent.add(group);
 // Uneven rings create a fully volumetric ridge, with a separate snow crown.
 const count=7,angles=Array.from({length:count},(_,i)=>i*Math.PI*2/count),radii=[1,.92,.58,.34,.06],levels=[0,.2,.57,.81,1];
 function section(first,last,color){
  const positions=[],indices=[];
  for(let ring=first;ring<=last;ring++)for(let i=0;i<count;i++){
   const angle=angles[i],variation=1+Math.sin(i*2.1)*.19;
   positions.push(Math.cos(angle)*radius*radii[ring]*variation+height*.08*levels[ring],height*levels[ring]+(ring>0&&ring<4?Math.sin(i*1.8)*height*.055:0),Math.sin(angle)*radius*radii[ring]);
  }
  for(let ring=0;ring<last-first;ring++)for(let i=0;i<count;i++){
   const a=ring*count+i,b=ring*count+(i+1)%count,c=a+count,d=b+count;indices.push(a,c,b,b,c,d);
  }
  if(last===4)for(let i=1;i<count-1;i++)indices.push((last-first)*count,(last-first)*count+i+1,(last-first)*count+i);
  const geo=new T.BufferGeometry();geo.setAttribute('position',new T.Float32BufferAttribute(positions,3));geo.setIndex(indices);geo.computeVertexNormals();
  const part=mesh(group,geo,color,0,0,0);part.material=part.material.clone();part.material.flatShading=true;
  part.material.onBeforeCompile=materials[color].onBeforeCompile;part.material.customProgramCacheKey=materials[color].customProgramCacheKey;
 }
 section(0,3,'rock');section(3,4,'snow');
 return group;
}
export function buildWorldScenery(kind){
 if(['pine','oak','cherry'].includes(kind))return tree(kind);
 const root=new T.Group();
 if(kind==='mountain'){
  crag(root,-.32,-.08,3.1,1.05,.25);crag(root,.67,.16,2.04,.72,-.45);crag(root,-1.05,.32,1.3,.55,.6);
  // Exposed ledges break up the broad cliff faces without photographic texture.
  const ledges=[[-.65,.53,.69,.38],[-.46,.92,.49,.3],[-.24,1.31,.36,.23],[.7,.53,.67,.28],[.65,.83,.57,.22]];
  for(const [x,y,z,s] of ledges){const ledge=stone(root,x,y,z,s,'rockLight');ledge.scale.y*=.55;ledge.rotation.z=.13;}
  for(const [x,z,s] of [[-.7,.85,.37],[.03,.78,.48],[.64,.67,.34],[1.14,.45,.32],[-1.29,.61,.22],[-.32,1,.18]])stone(root,x,s*.38,z,s,x>0?'rockDark':'rockLight');
  tuft(root,-1.15,.85,.15);tuft(root,.76,.92,.14);
 }else if(kind==='rocks'){
  stone(root,-.23,.38,0,.7,'rock');stone(root,.48,.23,.14,.43,'rockLight');stone(root,-.62,.12,.48,.27,'rockDark');stone(root,.05,.1,.65,.23,'rockLight');
  tuft(root,-.64,.11,.19);tuft(root,.58,.53,.16);
 }else throw new Error(`Unknown world scenery: ${kind}`);
 return root;
}

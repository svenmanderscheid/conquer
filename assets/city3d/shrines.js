import * as T from './vendor/three.module.js';
import {storybookMaterials as M,addStorybookOutline} from './storybook-style.js?v=storybook3';

// Compact elemental siblings: natural platforms, oversized symbols and the
// same painted toon materials as the playable village. Small repeats instance.
export const SHRINE_BIOMES=['forest','ice','sand','lava'];
export function buildShrine(biome='forest') {
  if(!SHRINE_BIOMES.includes(biome))return null;
  const root=new T.Group();root.name=`shrine-${biome}`;root.userData.biome=biome;
  const g={rock:new T.IcosahedronGeometry(1,1),ball:new T.SphereGeometry(1,12,8),cyl:new T.CylinderGeometry(1,1,1,12),gem:new T.OctahedronGeometry(1),box:new T.BoxGeometry(1,1,1)};
  const copies=new Map(),temporary=new Set(),animations=[];let pieces=0;
  const materials={...M};
  materials.flame=M.glow.clone();materials.flame.color.set('#f49a35');
  function tint(name,source,color){materials[name]=M[source].clone();materials[name].color.set(color);return name;}
  tint('ice','blueLight','#91cddd');tint('snow','trim','#edf0e4');tint('basalt','stone','#574d59');tint('sandstone','cream','#dcb276');tint('sand','wheat','#d3b879');tint('moss','leaf','#648d54');
  const matrix=new T.Matrix4(),position=new T.Vector3(),rotation=new T.Euler(),quaternion=new T.Quaternion(),scale=new T.Vector3();
  function transform(x,y,z,w,h,d,rx,ry,rz){return matrix.compose(position.set(x,y,z),quaternion.setFromEuler(rotation.set(rx,ry,rz)),scale.set(w,h,d)).clone();}
  function piece(form,color,x,y,z,w,h=w,d=w,rx=0,ry=0,rz=0,outline=false,parent=root){
    const geometry=typeof form==='string'?g[form]:form,material=materials[color],m=transform(x,y,z,w,h,d,rx,ry,rz);pieces++;
    if(outline||parent!==root){const mesh=new T.Mesh(geometry,material);mesh.applyMatrix4(m);mesh.castShadow=true;mesh.receiveShadow=true;parent.add(mesh);if(outline)addStorybookOutline(mesh,.032);return mesh;}
    const key=geometry.uuid+color;if(!copies.has(key))copies.set(key,{geometry,material,matrices:[]});copies.get(key).matrices.push(m);return null;
  }
  const rock=(c,x,y,z,w,h=w,d=w,ry=0,outline=false,parent=root)=>piece('rock',c,x,y,z,w,h,d,0,ry,0,outline,parent);
  const cyl=(c,x,y,z,r,h,outline=false,parent=root)=>piece('cyl',c,x,y,z,r,h,r,0,0,0,outline,parent);
  const gem=(c,x,y,z,w,h=w,d=w,ry=0,rz=0,parent=root)=>piece('gem',c,x,y,z,w,h,d,0,ry,rz,false,parent);
  function tube(c,points,r=.07,outline=false,parent=root){const geometry=new T.TubeGeometry(new T.CatmullRomCurve3(points.map(v=>new T.Vector3(...v))),Math.max(12,points.length*5),r,7,false);temporary.add(geometry);return piece(geometry,c,0,0,0,1,1,1,0,0,0,outline,parent);}
  function torus(c,x,y,z,r,t=.07,rx=Math.PI/2,outline=false,parent=root){const geometry=new T.TorusGeometry(r,t,7,32);temporary.add(geometry);return piece(geometry,c,x,y,z,1,1,1,rx,0,0,outline,parent);}
  function moving(name,x,y,z,build,update){const group=new T.Group();group.name=name;group.position.set(x,y,z);root.add(group);build(group);animations.push(t=>update(group,t*Math.PI/2));return group;}
  function symbol(c,x,y,z,shape){tube(c,shape.map(([dx,dy])=>[x+dx,y+dy,z]),.045);}
  const floor={forest:'moss',ice:'snow',sand:'sand',lava:'basalt'}[biome],stone={forest:'stone',ice:'snow',sand:'sandstone',lava:'basalt'}[biome],accent={forest:'leafLight',ice:'ice',sand:'gold',lava:'orange'}[biome];
  // Low organic base sits directly on the terrain; no oversized display plinth.
  rock(stone,0,.16,0,2.09,.30,1.77,.13,true);rock(floor,.03,.35,-.03,1.98,.14,1.66,-.08);
  for(let i=0;i<9;i++){const a=i*2.399,r=1.77+(i%3)*.11;rock(stone,Math.sin(a)*r,.20,Math.cos(a)*r*.82,.19+(i%2)*.06,.20,.23,a);}
  // Only a few irregular approach stones, with open space between them.
  for(const[x,z,w,angle]of[[-.15,1.88,.31,-.18],[.13,1.55,.35,.23],[-.05,1.23,.39,-.10]])rock(stone,x,.32,z,w,.095,.24,angle);
  function standingStone(x,z,h,lean=0){piece('rock',stone,x,.44+h/2,z,.33,h*.59,.29,0,.3,lean,true);symbol(accent,x,.61+h*.40,z+.30,[[-.085,-.14],[0,.06],[.085,-.14],[0,-.07],[0,.22]]);}
  if(biome==='forest'){
    for(const[x,z,h,lean]of[[-1.38,-.69,1.52,-.12],[1.28,-.88,1.70,.11],[.09,-1.27,1.21,-.08],[-1.63,.36,.97,.17],[1.56,.34,.87,-.16]])standingStone(x,z,h,lean);
    // The hollow twisted trunk splits into two asymmetrical arms around its heart.
    tube('wood',[[0,.36,-.08],[-.28,1.02,-.10],[-.35,1.71,-.17],[.05,2.40,-.20],[.24,2.84,-.31]],.24,true);
    tube('timber',[[.14,.42,.05],[.44,1.00,-.05],[.39,1.62,-.11],[.05,2.12,-.13]],.18,true);
    for(const[x,y,z]of[[-1.11,2.37,-.23],[1.07,2.67,-.44],[.42,3.08,-.73]]){
      tube('wood',[[0,1.75,-.16],[x*.54,y-.20,z*.6],[x,y,z]],.13);
      rock('leaf',x,y+.11,z,.62,.39,.53,.23,true);rock('leafLight',x-.14,y+.31,z+.10,.41,.22,.35,.61);
      rock('moss',x+.27,y+.16,z-.10,.36,.27,.32,-.41);
    }
    for(const[x,z]of[[-1.02,.32],[1.02,.46],[.18,1.04],[-.80,-.57],[.77,-.73]])tube('wood',[[0,.83,-.08],[x*.50,.49,z*.47],[x,.40,z]],.10);
    for(const[x,z]of[[-1.40,.89],[1.32,.72],[-.89,-1.02]]){rock('leaf',x,.51,z,.24,.14,.24,.2);cyl('trim',x,.58,z,.035,.19);rock('red',x,.70,z,.15,.07,.12);}
    moving('living-heart',.05,1.49,.10,parent=>{gem('leafLight',0,0,0,.21,.32,.16,0,0,parent);gem('glow',0,.03,.14,.07,.12,.04,0,0,parent);},(p,t)=>{p.position.y=1.49+Math.sin(t)*.07;p.rotation.y=Math.sin(t)*.24;});
  }else if(biome==='ice'){
    for(const[x,z,h,lean]of[[-1.31,-.79,1.93,-.10],[1.23,-.80,2.03,.10],[-1.63,.42,.93,.11],[1.54,.41,1.02,-.12]])standingStone(x,z,h,lean);
    // Thick broken arch frames the crystal, avoiding a thin spiky silhouette.
    tube('snow',[[-1.33,1.78,-.79],[-1.04,2.38,-.86],[-.33,2.70,-.89],[.39,2.74,-.87],[1.02,2.39,-.83],[1.27,1.85,-.79]],.22,true);
    tube('ice',[[-1.22,1.89,-.52],[-.83,2.32,-.57],[-.26,2.49,-.61],[.31,2.51,-.59],[.92,2.26,-.55],[1.16,1.94,-.52]],.06);
    cyl('stone',0,.53,.04,.79,.29,true);cyl('ice',0,.73,.04,.66,.14);
    for(let i=0;i<6;i++){const a=i*Math.PI/3;gem('ice',Math.sin(a)*.64,.93,.04+Math.cos(a)*.64,.13,.24,.13,a,.15);}
    moving('floating-frost-crystal',0,1.54,.02,parent=>{gem('ice',0,0,0,.43,.76,.38,0,0,parent);gem('trim',-.08,.10,.25,.16,.45,.10,-.15,0,parent);},(p,t)=>{p.position.y=1.54+Math.sin(t)*.10;p.rotation.y=t;});
    for(const[x,z]of[[-1.4,.76],[1.17,.82],[.77,-1.30]]){gem('ice',x,.64,z,.17,.40,.15,.2,.2);rock('snow',x,.42,z,.33,.12,.29);}
  }else if(biome==='sand'){
    for(const s of[-1,1]){
      piece('box','sandstone',s*1.11,1.14,-.47,.50,1.58,.50,0,0,-s*.045,true);
      rock('sandstone',s*1.11,.54,-.47,.49,.18,.42,0,true);rock('trim',s*1.11,1.91,-.47,.46,.17,.40,.05,true);
      symbol('orangeDark',s*1.11,1.28,-.17,[[-.10,-.25],[.11,-.02],[-.11,.13],[.10,.32]]);
      standingStone(s*1.57,.60,.71,s*.14);
    }
    tube('sandstone',[[-1.34,2.04,-.48],[-.84,2.16,-.49],[.08,2.20,-.49],[.87,2.15,-.49],[1.34,2.01,-.48]],.25,true);
    cyl('sandstone',0,.65,.15,.75,.44,true);rock('trim',0,.91,.15,.88,.17,.74,0,true);
    moving('turning-sun',0,2.68,-.39,parent=>{
      piece('cyl','gold',0,0,0,.48,.20,.48,Math.PI/2,0,0,true,parent);torus('orangeDark',0,0,.12,.36,.045,0,false,parent);
      for(let i=0;i<8;i++){const a=i*Math.PI/4;gem('gold',Math.sin(a)*.65,Math.cos(a)*.65,0,.13,.23,.10,0,-a,parent);}
      rock('glow',0,0,.135,.16,.16,.035,0,false,parent);
    },(p,t)=>{p.rotation.z=t;});
    for(const[x,z,w]of[[-1.48,.99,.25],[1.63,-.87,.22],[.69,-1.27,.29]]){rock('sandstone',x,.42,z,w,.17,w*.8,.4);rock('orangeDark',x-.07,.42,z+.08,w*.4,.09,w*.4);}
  }else{
    for(const[x,z,h,lean]of[[-1.29,-.69,1.46,-.15],[1.24,-.73,1.53,.15],[0,-1.19,1.14,-.06],[-1.66,.38,.93,.17],[1.61,.36,.87,-.16]])standingStone(x,z,h,lean);
    // Heavy open bowl, broad horns and oversized flame remain readable at 3 tiles.
    cyl('basalt',0,.63,.05,.82,.44,true);rock('dark',0,.92,.05,1.08,.35,.84,0,true);
    torus('orangeDark',0,1.17,.05,.77,.15,Math.PI/2,true);cyl('redDark',0,1.11,.05,.70,.07);
    for(const s of[-1,1])tube('basalt',[[s*.83,.93,.05],[s*1.05,1.43,.04],[s*.91,1.90,-.05]],.16,true);
    moving('altar-flame',0,1.15,.05,parent=>{
      // Curved tapered tongues use bevelled extrusions, with two broad tones.
      const shape=new T.Shape();shape.moveTo(-.43,0);shape.bezierCurveTo(-.85,.75,-.21,1.0,-.13,1.62);shape.bezierCurveTo(.21,1.33,.51,1.04,.35,.74);shape.bezierCurveTo(.67,.97,.63,.50,.49,.22);shape.quadraticCurveTo(.18,-.12,-.43,0);
      const flame=new T.ExtrudeGeometry(shape,{depth:.21,bevelEnabled:true,bevelSize:.07,bevelThickness:.07,bevelSegments:2,steps:1,curveSegments:7});temporary.add(flame);piece(flame,'flame',0,0,-.13,1,1,1,0,0,0,true,parent);
      gem('glow',-.06,.46,.16,.24,.55,.06,0,-.09,parent);
    },(p,t)=>{p.scale.set(1+Math.sin(t*2+.6)*.035,1+Math.sin(t*2)*.08,1);p.rotation.z=Math.sin(t)*.035;});
    for(const[x,z]of[[-1.24,.92],[1.07,1.0],[.69,-1.20]]){rock('basalt',x,.44,z,.25,.17,.21,.5);gem('orange',x+.04,.53,z+.05,.08,.10,.05);}
  }
  for(const{geometry,material,matrices}of copies.values()){const mesh=new T.InstancedMesh(geometry,material,matrices.length);matrices.forEach((m,i)=>mesh.setMatrixAt(i,m));mesh.instanceMatrix.needsUpdate=true;mesh.castShadow=true;mesh.receiveShadow=true;root.add(mesh);}
  let drawCalls=0,triangles=0,instances=0;root.traverse(o=>{if(o.isMesh){drawCalls++;const count=o.isInstancedMesh?o.count:1;instances+=count;triangles+=(o.geometry.index?.count??o.geometry.attributes.position.count)/3*count;}});
  root.userData.modelStats={pieces,drawCalls,triangles,instances};root.userData.animate=seconds=>animations.forEach(fn=>fn(seconds));root.userData.animate(0);return root;
}

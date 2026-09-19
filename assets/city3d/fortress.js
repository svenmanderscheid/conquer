import * as T from './vendor/three.module.js';

// Hand-authored architectural model. Static repeated parts are instanced by
// geometry/material after construction; animated cloth remains independent.
export function buildFortress({parent, flag, emblem}) {
  const root=new T.Group();parent.add(root);root.position.set(1.5,.1,-2.45);root.userData.building='keep';
  let seed=621;const random=()=>((seed=(seed*1664525+1013904223)>>>0)/4294967296);
  const material=(color,roughness=.85,metalness=0)=>new T.MeshStandardMaterial({color,roughness,metalness,flatShading:true});
  const stone=['#b9a687','#cdbb9b','#c1af91','#d5c2a1','#aa997f'].map(c=>material(c));
  const copper=['#aa623b','#b16a40','#b87549','#a56240','#648679'].map(c=>material(c,.58,.3));
  const mortar=material('#746e5e'),wood=material('#493426'),grain=material('#70503a'),iron=material('#323c39',.48,.55),gold=material('#c49651',.4,.55),dark=material('#242e2b'),teal=material('#245c59');
  const glass=material('#d6a75b',.4);glass.emissive.set('#b97524');glass.emissiveIntensity=.3;
  const groups=new Map();const unit=new T.BoxGeometry(1,1,1);
  const shape=new T.Shape();shape.moveTo(-.5,-.5);shape.lineTo(.5,-.5);shape.lineTo(.5,.5);shape.lineTo(-.5,.5);shape.closePath();
  const bevel=new T.ExtrudeGeometry(shape,{depth:.92,bevelEnabled:true,bevelThickness:.04,bevelSize:.045,bevelSegments:1,steps:1});bevel.translate(0,0,-.46);
  const dummy=new T.Object3D();
  function part(g,m,x,y,z,sx=1,sy=1,sz=1,rx=0,ry=0,rz=0){
    const key=g.uuid+m.uuid;if(!groups.has(key))groups.set(key,{g,m,transforms:[]});
    dummy.position.set(x,y,z);dummy.rotation.set(rx,ry,rz);dummy.scale.set(sx,sy,sz);dummy.updateMatrix();groups.get(key).transforms.push(dummy.matrix.clone());
  }
  const block=(w,h,d,x,y,z,m=stone[1],rz=0)=>part(bevel,m,x,y,z,w,h,d,0,0,rz);
  const beam=(w,h,d,x,y,z,m=wood,rz=0)=>part(unit,m,x,y,z,w,h,d,0,0,rz);
  function wall(w,h,d,x,y,z){
    beam(w,h,d,x,y+h/2,z,mortar);
    // Each visible face has staggered, individually weathered masonry.
    for(let row=0;row<Math.ceil(h/.34);row++){
      const bh=Math.min(.34,h-row*.34);
      for(const face of [0,1,2,3]){const len=face%2?d:w;let at=-len/2;
        while(at<len/2-.02){const bw=Math.min(.39+random()*.27,len/2-at);const mid=at+bw/2;const mat=stone[Math.floor(random()*stone.length)];
          if(face%2===0)block(bw-.025,bh-.027,.095,x+mid,y+row*.34+bh/2,z+(face===0?1:-1)*(d/2+.01),mat,(random()-.5)*.015);
          else block(.095,bh-.027,bw-.025,x+(face===1?1:-1)*(w/2+.01),y+row*.34+bh/2,z+mid,mat);
          at+=bw;
        }
      }
    }
  }
  function roof(w,d,y,h,x,z){
    const sh=new T.Shape();sh.moveTo(-w/2,0);sh.lineTo(0,h);sh.lineTo(w/2,0);sh.closePath();
    const geo=new T.ExtrudeGeometry(sh,{depth:d,bevelEnabled:false});geo.translate(0,0,-d/2);part(geo,wood,x,y,z);
    const angle=Math.atan2(h,w/2),slope=Math.hypot(h,w/2),rows=6,cols=Math.ceil(d/.4);
    for(const side of [-1,1])for(let row=0;row<rows;row++)for(let col=0;col<cols;col++){
      const f=(row+.5)/rows;const mat=copper[random()<.035?4:Math.floor(random()*4)];
      part(bevel,mat,x+side*w/2*f,y+h*(1-f)+.055+row*.007,z-d/2+(col+.5)*d/cols,slope/rows*1.06,.055,d/cols-.016,0,0,-side*angle);
    }
    for(const side of [-1,1]){
      for(const end of [-1,1])beam(slope+.12,.12,.14,x+side*w/4,y+h/2,z+end*(d/2+.05),wood,-side*angle);
      beam(.13,.16,d+.2,x+side*w/2,y,z,wood);
    }
    beam(.17,.18,d+.32,x,y+h+.08,z,gold);
  }
  function window(x,y,z,w=.36,h=.68){
    block(w+.2,h+.22,.15,x,y,z,stone[0]);beam(w,h,.05,x,y,z+.1,dark);beam(w-.08,h-.09,.04,x,y,z+.13,glass);
    beam(.045,h,.06,x,y,z+.17,iron);beam(w,.045,.06,x,y,z+.17,iron);block(w+.27,.12,.27,x,y-h/2-.12,z+.02);
  }
  // A substantial stepped plinth, tapered buttresses and unequal tower masses.
  block(5.2,.32,4.2,0,.16,0,stone[4]);block(4.95,.24,3.95,0,.42,0,stone[0]);
  wall(3.55,2.65,2.65,0,.54,0);
  beam(3.8,.22,2.92,0,2.9,0);roof(4.05,3.12,3.08,1.18,0,0);
  for(const x of [-1.68,1.68]){
    block(.64,.3,.78,x,.67,1.25,stone[4]);block(.46,1.86,.52,x,1.65,1.27,stone[0]);block(.55,.18,.63,x,2.66,1.27);
  }
  // Tall offset watchtower, enclosed gallery and steep patinated copper crown.
  const tx=.83,tz=-.74;
  wall(1.85,4.75,1.9,tx,.55,tz);block(2.13,.2,2.18,tx,4.46,tz,stone[4]);
  beam(2.17,.17,2.22,tx,4.67,tz);beam(2.12,.85,2.17,tx,5.12,tz,wood);
  for(const x of [tx-.86,tx,tx+.86]){beam(.13,.91,.16,x,5.13,tz+1.12);window(x,5.14,tz+1.1,.4,.5);}
  for(const z of [tz-.85,tz,tz+.85]){beam(.15,.86,.13,tx+1.1,5.13,z);beam(.045,.54,.35,tx+1.18,5.16,z,dark);}
  roof(2.6,2.65,5.62,1.35,tx,tz);block(2.3,.14,2.35,tx,5.57,tz,stone[4]);
  for(const x of [tx-.75,tx+.75])beam(.13,.5,.18,x,4.48,tz+1.02,wood,x<tx?-.45:.45);
  // An ancient broken ring crowns the ridge, readable at city scale.
  const crest=emblem(root,tx,7.2,tz+.75,.37);crest.material=gold;
  beam(.1,.57,.1,tx,6.98,tz+.75,iron);
  flag(root,tx+1.3,4.55,tz+.86,.75,1.15);
  // Lower stone bastion breaks symmetry and gives the building a defensive role.
  const bx=-1.88,bz=-.15;
  wall(1.48,2.8,1.85,bx,.55,bz);block(1.75,.22,2.12,bx,3.4,bz,stone[4]);block(1.62,.11,1.96,bx,3.56,bz,stone[1]);
  beam(1.3,.05,1.65,bx,3.62,bz,mortar);
  for(const side of [-1,1])for(let i=0;i<3;i++)block(.39,.4,.39,bx+side*.64,3.8,bz-.75+i*.75,stone[i]);
  for(const side of [-1,1])block(.4,.4,.38,bx,3.8,bz+side*.8,stone[1]);
  window(bx,2.48,bz+.98,.2,.66);
  // Deep arch portal with wedge-shaped voussoirs and a recessed plank door.
  const dz=1.51,archY=1.77,r=.66;
  const door=new T.Shape();door.moveTo(-r,.61);door.lineTo(r,.61);door.lineTo(r,archY);door.absarc(0,archY,r,0,Math.PI,false);door.closePath();
  part(new T.ExtrudeGeometry(door,{depth:.09,bevelEnabled:false}),dark,0,0,dz);
  for(let i=0;i<8;i++){const x=-.57+i*.164;const top=archY+Math.sqrt(Math.max(0,r*r-x*x));beam(.15,top-.64,.07,x,(top+.64)/2,dz+.11,i%3===0?grain:wood);}
  for(const side of [-1,1])for(let i=0;i<4;i++)block(.28,.3,.37,side*.8,.76+i*.3,dz+.04,stone[(i+2)%5]);
  for(let i=0;i<11;i++){const a=(i+.5)*Math.PI/11;block(.25,.3,.39,Math.cos(a)*.8,archY+Math.sin(a)*.8,dz+.04,stone[i%5],a-Math.PI/2);}
  for(const y of [.95,1.49]){beam(1.16,.08,.08,0,y,dz+.17,iron);for(const x of [-.5,-.2,.2,.5])block(.038,.038,.04,x,y,dz+.23,gold);}
  for(const x of [-.12,.12]){const ring=new T.TorusGeometry(.066,.013,5,12);part(ring,gold,x,1.26,dz+.24);}
  // Upper gable emblem and timber braces.
  const seal=emblem(root,0,3.5,1.62,.31);seal.material=gold;
  for(const x of [-1.17,1.17]){window(x,2.28,1.38,.33,.61);beam(.14,.8,.16,x,2.85,1.47,wood,x<0?-.58:.58);}
  for(let i=0;i<5;i++)block(1.82+i*.2,.17,.38,0,.52-i*.1,1.89+i*.29,stone[i%3]);
  // Warm lanterns, roof dormer, loading canopy and small props at the foundation.
  for(const x of [-1.02,1.02]){
    beam(.06,.55,.08,x,1.62,1.68,iron);beam(.23,.06,.1,x,1.91,1.75,iron);
    block(.19,.3,.2,x,1.72,1.8,glass);block(.25,.07,.26,x,1.91,1.8,iron);block(.25,.07,.26,x,1.53,1.8,iron);
  }
  wall(.72,.6,.54,-.68,3.62,.53);roof(.95,.82,4.22,.45,-.68,.57);window(-.68,3.94,.86,.26,.36);
  beam(.14,1.9,.14,2.19,1.43,.7);beam(.14,1.9,.14,2.19,1.43,-.5);
  part(unit,wood,2,2.39,.1,.96,.13,1.7,0,0,-.25);
  for(let i=0;i<5;i++)part(bevel,copper[i%4],2,2.49,.1-.66+i*.33,1,.06,.32,0,0,-.25);
  for(let i=0;i<3;i++){block(.37,.4,.39,2.12,.76+i*.4,-.35,grain);beam(.4,.05,.42,2.12,.84+i*.4,-.35,iron);}
  // Sparse dark seams on wood give grain without external image dependencies.
  for(const x of [-1.65,1.65])for(let i=0;i<3;i++)beam(.018,.7,.018,x+i*.025,2.36,1.57,grain);
  for(const {g,m,transforms} of groups.values()){
    const batch=new T.InstancedMesh(g,m,transforms.length);transforms.forEach((matrix,i)=>batch.setMatrixAt(i,matrix));batch.castShadow=true;batch.receiveShadow=true;batch.computeBoundingSphere();root.add(batch);
  }
  return root;
}


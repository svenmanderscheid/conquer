import * as T from './vendor/three.module.js';
import {storybookMaterials as M,addStorybookOutline} from './storybook-style.js?v=storybook5';
import {attachCastleSkins} from './castle-skin-controller.js?v=dragonsteel7';

// The illustrated village castle: four round towers, one broad blue crown,
// pale stone and a welcoming gate. Keep the silhouette readable from the map.
export function buildFortress({parent,flag,emblem}){
  const root=new T.Group();root.position.set(1.5,.1,-2.45);root.userData.building='keep';parent.add(root);
  const batches=new Map(),dummy=new T.Object3D();
  const box=new T.BoxGeometry(1,1,1),sphere=new T.SphereGeometry(1,12,8);
  function add(geometry,material,x=0,y=0,z=0,outline=true,owner=root){
    const mesh=new T.Mesh(geometry,material);mesh.position.set(x,y,z);mesh.castShadow=true;mesh.receiveShadow=true;owner.add(mesh);
    if(outline)addStorybookOutline(mesh,.032);return mesh;
  }
  function stamp(geometry,material,x,y,z,sx=1,sy=1,sz=1,ry=0,rz=0){
    const key=geometry.uuid+material.uuid;if(!batches.has(key))batches.set(key,{geometry,material,matrices:[]});
    dummy.position.set(x,y,z);dummy.scale.set(sx,sy,sz);dummy.rotation.set(0,ry,rz);dummy.updateMatrix();batches.get(key).matrices.push(dummy.matrix.clone());
  }
  function softBox(w,h,d){
    const r=Math.min(.085,w/5,h/5),s=new T.Shape();
    s.moveTo(-w/2+r,-h/2);s.lineTo(w/2-r,-h/2);s.quadraticCurveTo(w/2,-h/2,w/2,-h/2+r);
    s.lineTo(w/2,h/2-r);s.quadraticCurveTo(w/2,h/2,w/2-r,h/2);s.lineTo(-w/2+r,h/2);
    s.quadraticCurveTo(-w/2,h/2,-w/2,h/2-r);s.lineTo(-w/2,-h/2+r);s.quadraticCurveTo(-w/2,-h/2,-w/2+r,-h/2);
    const geometry=new T.ExtrudeGeometry(s,{depth:Math.max(.01,d-.05),bevelEnabled:true,bevelThickness:.025,bevelSize:.02,bevelSegments:2,curveSegments:4});geometry.translate(0,0,-(d-.05)/2);return geometry;
  }
  const cylinder=(r,h,x,y,z,material=M.stone,outline=true)=>add(new T.CylinderGeometry(r*.99,r,h,32),material,x,y,z,outline);
  function archShape(w,h){const r=w/2,s=new T.Shape();s.moveTo(-r,0);s.lineTo(r,0);s.lineTo(r,h-r);s.absarc(0,h-r,r,0,Math.PI,false);s.closePath();return s;}
  function arch(w,h,d,x,y,z,material,outline=true,owner=root){
    return add(new T.ExtrudeGeometry(archShape(w,h),{depth:d,bevelEnabled:true,bevelThickness:.014,bevelSize:.016,bevelSegments:2,curveSegments:12}),material,x,y,z,outline,owner);
  }
  function window(x,y,z,angle=0,scale=1){
    const holder=new T.Group();holder.position.set(x,y,z);holder.rotation.y=angle;holder.scale.setScalar(scale);root.add(holder);
    arch(.4,.73,.025,0,-.035,-.02,M.cream,false,holder);
    arch(.31,.64,.022,0,0,.016,M.dark,false,holder);
    arch(.19,.48,.02,0,.065,.047,M.glow,false,holder);
  }
  function roof(r,h,x,y,z,withFlag=false){
    const profile=[[0,0],[r,0],[r*1.025,.08],[r*.98,.19],[r*.79,h*.32],[r*.55,h*.57],[r*.29,h*.81],[r*.065,h*.98],[0,h]].map(([a,b])=>new T.Vector2(a,b));
    add(new T.LatheGeometry(profile,32),M.blue,x,y,z);
    cylinder(r,.085,x,y+.035,z,M.blueDark,false);
    // Only two broad blue courses, as on the painted reference.
    for(const [rr,yy] of [[.79,.32],[.55,.57]]){
      const seam=add(new T.TorusGeometry(r*rr,.019,5,32),M.blueDark,x,y+h*yy,z,false);seam.rotation.x=Math.PI/2;
    }
    add(new T.SphereGeometry(.075,10,7),M.gold,x,y+h+.025,z,false);
    if(withFlag){
      const pennant=flag(root,x,y+h+.04,z,.52,.33);pennant.material.color.copy(M.blue.color);
    }
  }
  function stonePatch(cx,cz,r,angle,y,w=.34){
    stamp(box,M.patch,cx+Math.sin(angle)*(r+.015),y,cz+Math.cos(angle)*(r+.015),w,.17,.045,angle);
  }
  function tower(x,z,h,roofH){
    cylinder(.91,.28,x,.43,z,M.patch);cylinder(.77,h,x,.54+h/2,z);
    cylinder(.83,.16,x,.66,z,M.cream,false);cylinder(.83,.18,x,.57+h,z,M.cream);
    // A few large accents leave the light stone surface calm.
    for(const [a,y] of [[.1,1.03],[1.1,1.7],[-.8,2.2],[2.45,1.22]])if(y<h-.15)stonePatch(x,z,.77,a,y,.3);
    window(x,.54+h*.58,z+.783);window(x+.783,.54+h*.58,z,Math.PI/2);
    roof(.97,roofH,x,.68+h,z,true);
  }

  // Low circular stone terrace, with an open central approach.
  cylinder(3.32,.34,0,.16,0,M.patch);cylinder(3.2,.12,0,.38,0,M.cream);
  const edgeStone=softBox(.64,.34,.42);
  for(let i=0;i<22;i++){
    const a=i*Math.PI*2/22;if(Math.abs(Math.sin(a))<.3&&Math.cos(a)>0)continue;
    stamp(edgeStone,i%4===0?M.patch:M.stone,Math.sin(a)*3.1,.23,Math.cos(a)*3.1,1,1,1,a);
  }

  // A broad round curtain wall and oversized, well-spaced crenellations.
  cylinder(2.53,2.37,0,1.645,0,M.stone);cylinder(2.61,.22,0,2.85,0,M.cream);
  const merlon=softBox(.5,.51,.44);
  for(let i=0;i<18;i++){
    const a=(i+.5)*Math.PI*2/18;stamp(merlon,M.cream,Math.sin(a)*2.42,3.13,Math.cos(a)*2.42,1,1,1,a);
  }
  for(const [a,y] of [[-.48,1.05],[.55,1.53],[.96,2.23],[-1.15,1.78],[1.76,1.14],[-2.1,2.1],[2.66,1.6]])stonePatch(0,0,2.53,a,y,.41);

  // Four easily recognisable towers surround the taller central keep.
  tower(-1.92,-1.42,3.65,1.13);tower(1.92,-1.42,3.72,1.16);
  roof(2.09,.79,0,2.9,-.22);
  cylinder(1.17,1.78,0,3.77,-.43,M.cream);cylinder(1.24,.16,0,4.61,-.43,M.trim);
  for(const angle of [-.63,.18,1.05,1.95])window(Math.sin(angle)*1.184,3.52,-.43+Math.cos(angle)*1.184,angle,1.12);
  for(const [a,y] of [[-.98,3.3],[.65,4.29],[2.48,3.44]])stonePatch(0,-.43,1.17,a,y,.28);
  roof(1.51,1.65,0,4.71,-.43,true);
  tower(-2.02,1.15,2.66,1.05);tower(2.02,1.15,2.78,1.08);

  // The front gallery gives the round keep a clear ceremonial face at map
  // distance.  It deliberately sits over the gate rather than widening the
  // existing terrace or crowding the path below.
  add(softBox(1.42,.15,.46),M.trim,0,3.04,2.39);
  add(softBox(1.17,.09,.18),M.wood,0,3.15,2.58,false);
  for(const x of [-.5,-.17,.17,.5]){
    stamp(box,M.wood,x,3.36,2.58,.055,.36,.055);
    stamp(sphere,M.gold,x,3.57,2.58,.045,.045,.045);
  }
  // Keep using the scene-supplied crest hook, so city skins can replace its
  // shape without changing selection, flag or upgrade behaviour.
  const gateCrest=emblem(root,0,3.47,2.61,.27);if(gateCrest)gateCrest.material=M.gold;

  // Round arch, chunky keystones, and a simple timber double door.
  arch(1.67,2.15,.15,0,.45,2.4,M.patch);
  arch(1.29,1.91,.12,0,.46,2.56,M.dark,false);
  arch(1.08,1.72,.055,0,.48,2.7,M.timber,false);
  for(const x of [-.35,-.18,0,.18,.35]){
    const h=1.18+Math.sqrt(Math.max(0,.54*.54-x*x));stamp(box,M.wood,x,.49+h/2,2.77,.021,h,.028);
  }
  const jamb=softBox(.24,.36,.2);
  for(const side of [-1,1])for(let row=0;row<4;row++)stamp(jamb,M.cream,side*.74,.63+row*.34,2.62);
  const wedge=softBox(.27,.29,.2);
  for(let i=0;i<7;i++){
    const a=(i+.5)*Math.PI/7;stamp(wedge,i===3?M.trim:M.cream,Math.cos(a)*.76,1.78+Math.sin(a)*.76,2.62,1,1,1,0,a-Math.PI/2);
  }
  for(const x of [-.13,.13])stamp(box,M.gold,x,1.28,2.79,.045,.13,.045);

  // The two large blue banners are the gate's clearest colour accent.
  const banner=new T.Shape();banner.moveTo(-.23,0);banner.lineTo(.23,0);banner.lineTo(.23,-1.04);banner.lineTo(0,-1.23);banner.lineTo(-.23,-1.04);banner.closePath();
  for(const x of [-1.16,1.16]){
    add(new T.ExtrudeGeometry(banner,{depth:.045,bevelEnabled:false}),M.gold,x,2.9,2.48);
    const cloth=add(new T.ShapeGeometry(banner),M.blue,x,2.86,2.53,false);cloth.scale.set(.77,.91,1);
    add(softBox(.67,.09,.12),M.wood,x,2.96,2.5,false);
    for(const dx of [-.31,.31])stamp(sphere,M.gold,x+dx,2.96,2.5,.075,.075,.075);
  }
  // Wide steps connect directly to the existing southbound city path.
  for(let i=0;i<5;i++)add(softBox(1.45+i*.11,.13,.39),i%2?M.stone:M.cream,0,.44-i*.078,2.78+i*.25);
  for(const side of [-1,1]){
    const rail=add(softBox(.18,.37,1.31),M.stone,side*.91,.33,3.28);rail.rotation.x=.29;
  }
  // Quiet, rounded greenery gives the stone silhouette a soft foundation.
  for(const [x,z,s] of [[-2.82,.63,.5],[2.91,.57,.5],[-2.53,-1.96,.45],[2.61,-1.95,.44],[-1.67,2.63,.4],[1.74,2.66,.41]]){
    for(let j=0;j<3;j++)stamp(sphere,j===1?M.leafLight:M.leaf,x+(j-1)*.22,.48+(j===1?.14:0),z+(j%2)*.13,s*.7,s*(j===1?1:.78),s*.72);
  }
  for(const {geometry,material,matrices} of batches.values()){
    const batch=new T.InstancedMesh(geometry,material,matrices.length);matrices.forEach((matrix,i)=>batch.setMatrixAt(i,matrix));batch.castShadow=batch.receiveShadow=true;batch.computeBoundingSphere();root.add(batch);
  }
  attachCastleSkins(root);
  return root;
}

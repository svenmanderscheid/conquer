import * as T from './vendor/three.module.js';
import {storybookMaterials as M, addStorybookOutline} from './storybook-style.js?v=storybook5';
import {villageBoundary,villageBoundaryPoint,villageTerrace,villageTerracePoint} from './village-layout.js?v=finished-village5';

// Scenic frame only: the playable city and its authored routes stay untouched.
export function buildVillageLandscape({scene,groundMaterial}) {
 const root=new T.Group();root.name='village-river-and-cliffs';scene.add(root);
 const {wallX,wallZ}=villageBoundary;
 let seed=9713;const random=()=>{seed=(seed*1664525+1013904223)>>>0;return seed/4294967296;};
 function tinted(source,color){const material=source.clone();material.color.set(color);material.onBeforeCompile=source.onBeforeCompile;material.customProgramCacheKey=source.customProgramCacheKey;return material;}
 const grass=tinted(M.leafLight,'#7f9b50');
 const bank=tinted(M.cream,'#c49a62');
 const terraceStone=tinted(M.stone,'#e8dfcf');
 terraceStone.side=T.DoubleSide;terraceStone.needsUpdate=true;
 const rock=tinted(M.patch,'#aeb5a0');
 const rockLight=tinted(M.stone,'#d0d1b9');
 const water=new T.MeshBasicMaterial({color:'#57b6d7',side:T.DoubleSide});
 const deep=new T.MeshBasicMaterial({color:'#3793bd',transparent:true,opacity:.24,side:T.DoubleSide});
 const foam=new T.MeshBasicMaterial({color:'#c5eff0',transparent:true,opacity:.48,depthWrite:false,side:T.DoubleSide});
 function point(a,offset){
  // Broad headlands and shallow coves; the bridge approach remains straight.
  const bridge=Math.max(0,Math.sin(a))**16;
  const outer=Math.max(0,Math.min(1,(offset-5)/10));
  const wobble=(1-bridge)*(.8+Math.sin(a*3+.4)*1.15+Math.sin(a*7-.3)*.65+outer*Math.sin(a*4+1.7)*1.1);
  const [baseX,baseZ]=villageBoundaryPoint(a/(Math.PI*2),offset),length=Math.hypot(baseX,baseZ)||1;
  return [baseX+baseX/length*wobble,baseZ+baseZ/length*wobble];
 }
 function ribbon(inner,outer,y,material){
  const positions=[],indices=[],uv=[];const n=160;
  for(let i=0;i<=n;i++){const a=i/n*Math.PI*2;for(const offset of [inner,outer]){const [x,z]=point(a,offset);positions.push(x,y,z);uv.push(x/20,z/20);}if(i<n){const k=i*2;indices.push(k,k+2,k+1,k+1,k+2,k+3);}}
  const geo=new T.BufferGeometry();geo.setAttribute('position',new T.Float32BufferAttribute(positions,3));geo.setAttribute('uv',new T.Float32BufferAttribute(uv,2));geo.setIndex(indices);geo.computeVertexNormals();const mesh=new T.Mesh(geo,material);mesh.receiveShadow=true;root.add(mesh);return mesh;
 }
 // Replace the old ten-sided disc with a shoreline that shares every edge
 // with its banks. The flat playable ground and all resident routes stay put.
 const shore=new T.Shape();
 for(let i=0;i<160;i++){const [x,z]=point(i/160*Math.PI*2,3.6);if(i)shore.lineTo(x,-z);else shore.moveTo(x,-z);}shore.closePath();
 const islandGeo=new T.ShapeGeometry(shore,32);islandGeo.rotateX(-Math.PI/2);
 const island=new T.Mesh(islandGeo,groundMaterial||grass);island.position.y=.075;island.receiveShadow=true;root.add(island);
 // The northern civic quarter sits on a real second level. A broad grass top,
 // warm earthen cliff and central wedge ramp keep the height change readable.
 const terracePoints=[];
 for(let i=0;i<128;i++)terracePoints.push(villageTerracePoint(i/128));
 const terraceShape=new T.Shape();terracePoints.forEach(([x,z],i)=>i?terraceShape.lineTo(x,-z):terraceShape.moveTo(x,-z));terraceShape.closePath();
 const terraceTopGeo=new T.ShapeGeometry(terraceShape,32);terraceTopGeo.rotateX(-Math.PI/2);
 const terraceTop=new T.Mesh(terraceTopGeo,groundMaterial||grass);terraceTop.position.y=villageTerrace.height;terraceTop.receiveShadow=true;root.add(terraceTop);
 const cliffPositions=[],cliffIndices=[];
 terracePoints.forEach(([x,z],i)=>{cliffPositions.push(x,.07,z,x,villageTerrace.height,z);const k=i*2,next=((i+1)%terracePoints.length)*2;cliffIndices.push(k,next,k+1,k+1,next,next+1);});
 const cliffGeo=new T.BufferGeometry();cliffGeo.setAttribute('position',new T.Float32BufferAttribute(cliffPositions,3));cliffGeo.setIndex(cliffIndices);cliffGeo.computeVertexNormals();
 const cliff=new T.Mesh(cliffGeo,terraceStone);cliff.castShadow=cliff.receiveShadow=true;root.add(cliff);
 const rampFront=villageTerrace.rampEndZ,rampBack=villageTerrace.centerZ+villageTerrace.halfZ,rampWidth=villageTerrace.rampHalfWidth*2;
 const rampSide=new T.Mesh(new T.BufferGeometry().setFromPoints([]),terraceStone);
 const rampPositions=[-rampWidth/2,.07,rampBack, rampWidth/2,.07,rampBack, -rampWidth/2,.07,rampFront, rampWidth/2,.07,rampFront, -rampWidth/2,villageTerrace.height,rampBack, rampWidth/2,villageTerrace.height,rampBack];
 const rampGeo=new T.BufferGeometry();rampGeo.setAttribute('position',new T.Float32BufferAttribute(rampPositions,3));rampGeo.setIndex([0,2,4,0,4,1,1,4,5,1,5,3,2,3,5,2,5,4]);rampGeo.computeVertexNormals();rampSide.geometry.dispose();rampSide.geometry=rampGeo;rampSide.castShadow=rampSide.receiveShadow=true;root.add(rampSide);
 const rampTopGeo=new T.BufferGeometry();rampTopGeo.setAttribute('position',new T.Float32BufferAttribute([-rampWidth/2,villageTerrace.height+.015,rampBack,rampWidth/2,villageTerrace.height+.015,rampBack,-rampWidth/2,.09,rampFront,rampWidth/2,.09,rampFront],3));rampTopGeo.setIndex([0,2,1,1,2,3]);rampTopGeo.computeVertexNormals();const rampTop=new T.Mesh(rampTopGeo,groundMaterial||grass);rampTop.receiveShadow=true;root.add(rampTop);
 // A low inner fortification gives the raised district its own castle identity.
 // Three front segments stay open for the central ramp.
 const terraceWall=[],terraceCaps=[],terraceTeeth=[],terraceSegments=72,terraceGate=Math.round(terraceSegments/4);
 for(let i=0;i<terraceSegments;i++){
  if(Math.abs(i-terraceGate)<=1)continue;
  const [ax,az]=villageTerracePoint(i/terraceSegments),[bx,bz]=villageTerracePoint((i+1)/terraceSegments),length=Math.hypot(bx-ax,bz-az),rotation=-Math.atan2(bz-az,bx-ax),x=(ax+bx)/2,z=(az+bz)/2;
  terraceWall.push([x,villageTerrace.height+.33,z,length+.05,.66,.48,0,rotation]);
  terraceCaps.push([x,villageTerrace.height+.7,z,length+.08,.16,.68,0,rotation]);
  if(i%2===0)terraceTeeth.push([x,villageTerrace.height+.96,z,Math.min(.72,length*.42),.46,.68,0,rotation]);
 }
 batch(new T.BoxGeometry(1,1,1),M.stone,terraceWall);batch(new T.BoxGeometry(1,1,1),M.cream,terraceCaps);batch(new T.BoxGeometry(1,1,1),M.stone,terraceTeeth);
 function slopedBank(inner,outer,top,bottom,material){
  const positions=[],uv=[],indices=[];
  for(let i=0;i<=160;i++){
   const a=i/160*Math.PI*2;
   for(const [radius,y] of [[inner,top],[outer,bottom]]){const [x,z]=point(a,radius);positions.push(x,y,z);uv.push(i/20,radius);}
   if(i<160){const k=i*2;indices.push(k,k+2,k+1,k+1,k+2,k+3);}
  }
  const geometry=new T.BufferGeometry();geometry.setAttribute('position',new T.Float32BufferAttribute(positions,3));geometry.setAttribute('uv',new T.Float32BufferAttribute(uv,2));geometry.setIndex(indices);geometry.computeVertexNormals();
  const lip=new T.Mesh(geometry,material);lip.receiveShadow=true;root.add(lip);
 }
 slopedBank(3.6,4.5,.075,-.12,grass);
 slopedBank(4.5,5.6,-.12,-.34,bank);
 // A calm turquoise river, with a soft earth lip and open country beyond.
 ribbon(5,13,-.35,water);
 ribbon(6.8,10.9,-.343,deep);
 ribbon(12.7,15.2,.025,bank);
 ribbon(14.8,165,.04,grass);
 // Stone bridge aligns exactly with the south gate and the existing main road.
 const deck=new T.Mesh(new T.BoxGeometry(6.5,.3,19.5),M.stone);deck.position.set(0,-.01,wallZ+9);deck.receiveShadow=true;root.add(deck);addStorybookOutline(deck,.035);
 for(const x of [-3.35,3.35]){
  const rail=new T.Mesh(new T.BoxGeometry(.48,.55,19.6),M.stone);rail.position.set(x,.4,wallZ+9);root.add(rail);
  for(const z of [wallZ,wallZ+6,wallZ+12,wallZ+18]){const post=new T.Mesh(new T.BoxGeometry(.78,.94,.78),M.cream);post.position.set(x,.47,z);root.add(post);addStorybookOutline(post,.025);}
 }
 function batch(geo,material,items){const mesh=new T.InstancedMesh(geo,material,items.length);const o=new T.Object3D();items.forEach((v,i)=>{o.position.set(v[0],v[1],v[2]);o.scale.set(v[3],v[4],v[5]);o.rotation.set(v[6]||0,v[7]||0,v[8]||0);o.updateMatrix();mesh.setMatrixAt(i,o.matrix);});mesh.castShadow=true;mesh.receiveShadow=true;mesh.computeBoundingSphere();root.add(mesh);return mesh;}
 // Low rock clusters and reeds sit outside the wall, never on the town paths.
 // Repeated shoreline parts cost seven batches, irrespective of cluster count.
 const shoreRocks=[],shoreCaps=[],reeds=[],reedHeads=[],rushLeaves=[],shallows=[],lilies=[];
 for(let i=0;i<19;i++){
  const a=i/19*Math.PI*2+.1;
  if(Math.sin(a)>.9&&Math.abs(Math.cos(a))<.16)continue;
  for(let j=0;j<3;j++){
   const angle=a+(j-1)*.018,[x,z]=point(angle,4.8+j*.26),s=.65+random()*.75;
   shoreRocks.push([x,-.08,z,s,.35+random()*.3,s*.78,.1,a+j,.05]);
   if(j===1)shoreCaps.push([x-.08,.17,z-.08,s*.76,.15,s*.63,0,a,.08]);
  }
  const [x,z]=point(a+.035,5.5);
  shallows.push([x,-.325,z,2.8+random(),1.2,1,-Math.PI/2,0,-a]);
  if(i%3===0){
   for(let j=0;j<5;j++){
    const px=x+(random()-.5)*1.2,pz=z+(random()-.5)*1.1,h=.65+random()*.5;
    reeds.push([px,-.25+h*.5,pz,.045,h,.045,0,a,.08]);
    reedHeads.push([px+.04,h-.24,pz,.075,.27,.075,0,a,.08]);
    for(const side of [-1,1])rushLeaves.push([px+side*.12,.03,pz,.1,.66,.07,0,a,side*.35]);
   }
   const [lx,lz]=point(a+.07,7.1);
   for(let j=0;j<3;j++)lilies.push([lx+j*.62,-.318,lz+(j%2)*.55,.42,.32,1,-Math.PI/2,0,a+j]);
  }
 }
 batch(new T.DodecahedronGeometry(1,0),rock,shoreRocks);batch(new T.DodecahedronGeometry(1,0),rockLight,shoreCaps);
 batch(new T.CylinderGeometry(1,1,1,5),M.leaf,reeds);batch(new T.CylinderGeometry(1,1,1,6),M.wood,reedHeads);
 batch(new T.SphereGeometry(1,5,3),M.leafLight,rushLeaves);
 const shallowMaterial=new T.MeshBasicMaterial({color:'#8dd0dc',transparent:true,opacity:.36,depthWrite:false,side:T.DoubleSide});
 batch(new T.CircleGeometry(1,14),shallowMaterial,shallows).castShadow=false;
 batch(new T.CircleGeometry(1,10,.18,Math.PI*2-.36),M.leaf,lilies).castShadow=false;
 // Broad rounded crags frame the back and left; the lower/eastern fields remain open.
 const crags=[],caps=[],trunks=[],leaves=[],lightLeaves=[],roots=[];
 for(let i=0;i<28;i++){
  const a=Math.PI*.89+i/27*Math.PI*.96;
  const radius=wallX+20+random()*6;const x=Math.cos(a)*radius,z=Math.sin(a)*(radius-2);
  const w=3.2+random()*3.8,h=3+random()*7,d=3+random()*3;
  crags.push([x,h*.43-.6,z,w,h*.63,d,random()*.13,random()*3,random()*.12]);
  caps.push([x-.3,h*.91-.5,z-.2,w*.83,h*.31,d*.86,0,random()*3,.08]);
 }
 const boulder=new T.IcosahedronGeometry(1,1);batch(boulder,rock,crags);batch(boulder,rockLight,caps);
 for(let i=0;i<38;i++){
  const a=i/38*Math.PI*2;const r=wallX+20+random()*16;let x=Math.cos(a)*r,z=Math.sin(a)*(r+2);
  // Keep production plots and the south road comfortably clear.
  if(z>wallZ-5&&Math.abs(x)<7)continue;
  const s=.8+random()*.8,lean=(random()-.5)*.35;
  trunks.push([x,-.04,z,s,s,s,0,a,lean]);
  for(let k=0;k<3;k++){const b=a+k*2.1;roots.push([x+Math.cos(b)*.35*s,.12,z+Math.sin(b)*.35*s,.17*s,.85*s,.18*s,Math.sin(b)*.8,0,Math.cos(b)*.8]);}
  for(let k=0;k<3;k++){const item=[x+Math.sin(k*2+a)*.8*s,2.4*s+(k===1?.65:0)*s,z+Math.cos(k*2+a)*.55*s,(1.2+k*.14)*s,(1.1+k*.14)*s,1.15*s,0,a,.12];(k===1?lightLeaves:leaves).push(item);}
 }
 const crookedTrunk=new T.TubeGeometry(new T.CatmullRomCurve3([new T.Vector3(0,0,0),new T.Vector3(-.16,.65,.08),new T.Vector3(.13,1.32,-.06),new T.Vector3(.23,2.3,.04)]),6,.24,7,false);
 batch(crookedTrunk,M.wood,trunks);batch(new T.CylinderGeometry(.45,1,1,6),M.wood,roots);
 batch(new T.IcosahedronGeometry(1,2),M.leaf,leaves);batch(new T.IcosahedronGeometry(1,2),M.leafLight,lightLeaves);
 // A few broad, slow current strokes: no dense texture or glittering surface.
 const strokes=[];const rippleGeo=new T.PlaneGeometry(1,1);
 for(let i=0;i<22;i++){const a=i/22*Math.PI*2+.08;const [x,z]=point(a,8+random()*2);if(z>wallZ-4&&Math.abs(x)<5)continue;strokes.push({x,z,a,width:1.1+random()*1.7,height:.07+random()*.08,phase:random()*6.28});}
 const current=batch(rippleGeo,foam,strokes.map(s=>[s.x,-.327,s.z,s.width,s.height,1,-Math.PI/2,0,-s.a]));current.castShadow=false;current.frustumCulled=false;
 const strokeTransform=new T.Object3D();
 root.userData.layout={shape:villageBoundary.shape,terrace:villageTerrace,innerWater:[wallX+5,wallZ+5],outerWater:[wallX+13,wallZ+13],southBridge:{x:0,z:wallZ+9,width:6.5,length:19.5},sceneryOnly:true};
 return {root,update(t){strokes.forEach(({x,z,a,width,height,phase},i)=>{strokeTransform.position.set(x+Math.sin(t*.22+phase)*.35,-.327,z+Math.cos(t*.22+phase)*.2);strokeTransform.rotation.set(-Math.PI/2,0,-a);strokeTransform.scale.set(width,height,1);strokeTransform.updateMatrix();current.setMatrixAt(i,strokeTransform.matrix);});current.instanceMatrix.needsUpdate=true;}};
}

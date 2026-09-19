import * as T from './vendor/three.module.js';
import {buildTrainingBuilding} from './training-buildings.js';
import { detailDistrict } from './district-details.js?v=storybook5';
import { buildStorybookProduction } from './storybook-production.js?v=finished-village5';
import {buildStorybookCivic} from './storybook-civic.js?v=finished-village5';
import { storybookMaterials as M,addStorybookOutline,shadeStorybookRoot } from './storybook-style.js?v=storybook5';
import {villageBuildings,villageRoads,villageBoundary,villageBoundaryPoint,villageElevationAt,villageLandmarks,placeVillageBuilding,isVillageGardenSpot} from './village-layout.js?v=finished-village5';

export let villageRoadMaterial;
export const districts=Object.entries({hospital:'Krankenhaus',archery_range:'Schützenlager',stable:'Reiterhof',storage:'Lagerhaus',treasure_house:'Schatzkammer',hall_of_alliance:'Allianzhalle',trading_post:'Handelsposten',farm:'Bauernhof',quarry:'Steinbruch',gold_mine:'Goldmine',wall:'Stadtmauer',watch_tower:'Wachturm'}).map(([code,name])=>[code,name,villageBuildings[code].x,villageBuildings[code].z]);
export function buildDistricts({scene,box,mesh,cyl,group,roof,flag,tree,mats}){
 const roots=[];
 const green=M.teal;
 const gold=M.gold;
 const domeGeo=new T.SphereGeometry(1.3,24,12,0,Math.PI*2,0,Math.PI/2);
 const bushGeo=new T.SphereGeometry(.45,12,8);
 const leaf=M.leaf;
 const white=M.cream;
 const hospitalRoof=M.teal;
 const navy=M.blue;
 const grain=M.orange;
 function recolorRoof(g,material){g.traverse(o=>{if(o.isMesh&&(o.material===mats.copper||o.material===mats.copper2))o.material=material;});}
 // Broad roof planes and archways share the reference's simple silhouettes.
 roof=(parent,w,d,base,height,x=0,z=0)=>{
   const shape=new T.Shape();shape.moveTo(-w/2,0);shape.lineTo(0,height);shape.lineTo(w/2,0);shape.closePath();
   const geometry=new T.ExtrudeGeometry(shape,{depth:d,bevelEnabled:true,bevelSize:.035,bevelThickness:.035,bevelSegments:2});geometry.translate(0,0,-d/2);
   mesh(geometry,'copper',parent,x,base,z);
 };
 function door(g,x=0,z=1.22){
   const shape=new T.Shape();shape.moveTo(-.31,0);shape.lineTo(.31,0);shape.lineTo(.31,.7);shape.absarc(0,.7,.31,0,Math.PI,false);shape.closePath();
   mesh(new T.ExtrudeGeometry(shape,{depth:.08,bevelEnabled:false}),M.wood,g,x,.18,z);
 }
 function house(g,w=2.7,d=2.3){box(g,w,1.7,d,0,1,0,'stone');roof(g,w+.4,d+.3,1.85,.85);door(g,0,d/2+.04);}
 function tower(g,x,z,h=3.3){cyl(g,.64,h,x,h/2,z,'stone',24);cyl(g,.8,.22,x,h,z,'stone3',24);mesh(new T.ConeGeometry(.88,.9,24),green,g,x,h+.55,z);}
 for(const [code,name,x,z] of districts){
   const g=group(x,.12,z);placeVillageBuilding(g,code);g.userData.building=code;roots.push({code,name,root:g,height:4});
   if(buildTrainingBuilding(g,code))continue;
   if(buildStorybookCivic(g,code)){g.userData.storybookComplete=true;continue;}
   if(buildStorybookProduction(g,code)){g.userData.storybookComplete=true;if(code==='gold_mine')roots.at(-1).height=4.4;continue;}
   box(g,4.3,.13,3.8,0,.06,0,'stone2');
   if(code==='wall'){
     // Open main gateway; all perimeter segments share this building's state and selection.
     const {wallX,wallZ,segments:wallSegments}=villageBoundary;
     for(const side of [-1,1])tower(g,side*3.62,0,3.2);
     box(g,6.8,.65,1,0,2.8,0,'stone3');
     for(let i=0;i<9;i++)box(g,.42,.45,1.05,-2.8+i*.7,3.32,0,'stone');
     flag(g,-2.9,1.4,.7,.7,1);flag(g,2.9,1.4,.7,.7,1);
     const walls=[],caps=[],walkways=[],teeth=[],dummy=new T.Object3D();
     const gateIndex=Math.round(wallSegments/4);
     for(let i=0;i<wallSegments;i++){
       if(i===gateIndex-1||i===gateIndex)continue;
       const [ax,az]=villageBoundaryPoint(i/wallSegments),[bx,bz]=villageBoundaryPoint((i+1)/wallSegments);
       const length=Math.hypot(bx-ax,bz-az),rotation=-Math.atan2(bz-az,bx-ax);
       walls.push([(ax+bx)/2-x,1.02,(az+bz)/2-z,length+.04,2.04,.65,rotation]);
       const cx=(ax+bx)/2-x,cz=(az+bz)/2-z,nx=Math.sin(rotation),nz=Math.cos(rotation);
       caps.push([cx,2.12,cz,length+.1,.22,1.16,rotation]);
       walkways.push([cx,2.265,cz,length+.04,.08,.62,rotation]);
       const count=Math.ceil(length/.9);
       for(let j=0;j<count;j++){
         const t=(j+.5)/count,tx=ax+(bx-ax)*t-x,tz=az+(bz-az)*t-z;
         for(const edge of [-1,1])teeth.push([tx+nx*edge*.45,2.47,tz+nz*edge*.45,.43,.52,.24,rotation]);
       }
       if(i%8===0)tower(g,ax-x,az-z,2.6);
     }
     function instances(rows,material){const batch=new T.InstancedMesh(new T.BoxGeometry(1,1,1),material,rows.length);rows.forEach(([px,py,pz,sx,sy,sz,angle],i)=>{dummy.position.set(px,py,pz);dummy.scale.set(sx,sy,sz);dummy.rotation.set(0,angle,0);dummy.updateMatrix();batch.setMatrixAt(i,dummy.matrix);});batch.castShadow=batch.receiveShadow=true;batch.computeBoundingSphere();g.add(batch);}
     instances(walls,mats.stone);instances(caps,mats.stone3);instances(walkways,mats.stone2);instances(teeth,mats.stone3);
   }else if(code==='watch_tower'){
     tower(g,0,0,4.2);cyl(g,.33,.5,0,3.75,.65,gold,16);roots.at(-1).height=5.4;
   }else if(code==='treasure_house'){
     cyl(g,1.15,2,0,1.1,0,'stone',24);mesh(domeGeo,gold,g,0,2.1,0);door(g);
     for(const side of [-1,1])tower(g,side*1.65,0,1.9);
   }else if(code==='quarry'){
     // An open terraced stone yard and crane, distinct from the mine tunnel.
     for(let i=0;i<3;i++)box(g,3.3-i*.65,.6,1.7-i*.22,0,.4+i*.58,-.45-i*.3,'stone2');
     for(let i=0;i<5;i++)box(g,.52,.42,.5,(i%3-1)*.67,.35+Math.floor(i/3)*.44,1.2,'stone3');
     box(g,.22,3.5,.24,1.55,1.75,0,'wood');box(g,2.8,.2,.22,.35,3.4,0,'wood2');
     cyl(g,.035,1.5,-.8,2.6,0,'dark',8);box(g,.5,.4,.5,-.8,1.68,0,'stone3');
     const brace=box(g,.16,2,.18,.95,2.5,0,'wood2');brace.rotation.z=-.7;
   }else if(code==='trading_post'){
     cyl(g,1.2,1.5,0,.9,0,'stone',24);mesh(new T.ConeGeometry(1.65,1.4,24),green,g,0,2.3,0);
     for(const side of [-1,1]){box(g,1.1,.65,.65,side*1.3,.5,1.1,'wood2');box(g,1.3,.12,.9,side*1.3,1.5,1.1,'copper2');}
     flag(g,0,2.8,0,.5,.65);
   }else{
     if(code==='hospital'){
       house(g,2.8,2.1);recolorRoof(g,hospitalRoof);
       for(const side of [-1,1]){box(g,1.35,1.25,1.9,side*1.7,.78,.35,white);box(g,1.5,.22,2,side*1.7,1.5,.35,hospitalRoof);}
       box(g,.27,1,.13,0,2.05,1.28,green);box(g,.94,.28,.13,0,2.05,1.3,green);
       const canopy=mesh(new T.SphereGeometry(.72,20,10,0,Math.PI*2,0,Math.PI/2),hospitalRoof,g,0,1.5,1.3);canopy.scale.z=.7;
       for(const side of [-1,1])mesh(bushGeo,leaf,g,side*2.3,.45,1.3);
     }else if(code==='storage'){
       // Low granary shed with twin tall silos, loading doors and stacked crates.
       house(g,3.2,1.8);recolorRoof(g,grain);
       for(const px of [-.9,.9]){cyl(g,.64,3,px,1.65,-1.2,'stone2',24);mesh(new T.SphereGeometry(.68,20,10,0,Math.PI*2,0,Math.PI/2),grain,g,px,3.15,-1.2);}
       box(g,1.25,1.15,.1,0,.75,1,'wood2');for(const px of [-.5,.5])box(g,.06,1.1,.12,px,.76,1.05,'wood');
       for(let i=0;i<4;i++)box(g,.6,.55,.6,1.8,.4+(i%2)*.55,.5+Math.floor(i/2)*.65,'wood2');
     }else if(code==='hall_of_alliance'){
       box(g,3.6,2.15,2.4,0,1.2,0,white);roof(g,4,2.9,2.3,1.5);recolorRoof(g,navy);door(g);
       for(const px of [-1.3,1.3]){cyl(g,.16,2.3,px,1.2,1.5,'stone3',16);flag(g,px,1.3,1.65,.65,1.1);}
       box(g,1.5,.16,.8,0,.14,1.65,'stone3');
       const shield=mesh(new T.SphereGeometry(.4,16,10),gold,g,0,2.8,1.52);shield.scale.set(.85,1.05,.12);
       roots.at(-1).height=4.4;
     }
   }
 }
 const pigments=new Map(Object.entries({stone:M.cream,stone2:M.stone,stone3:M.trim,wood:M.wood,wood2:M.timber,copper:M.orange,copper2:M.orange,bronze:M.gold,dark:M.dark,teal:M.teal,wheat:M.wheat}).map(([key,value])=>[mats[key],value]));
 for(const {code,root} of roots){
   if(code==='farm'||code==='gold_mine')continue;
   if(!root.userData.storybookComplete)detailDistrict(root,code);
   const outlined=[];
   root.traverse(object=>{
     if(!object.isMesh||object.userData.storybookInk)return;
     if(pigments.has(object.material))object.material=pigments.get(object.material);
     if(object.isInstancedMesh||object.userData.storybookOutline)return;
     object.geometry.computeBoundingBox();const size=object.geometry.boundingBox.getSize(new T.Vector3());
     if(Math.max(size.x,size.y,size.z)>=.8&&Math.min(size.x,size.y,size.z)>=.14)outlined.push(object);
   });
   shadeStorybookRoot(root);
   // The fading perimeter cannot depth-mask an inverted hull reliably.
   if(code!=='wall')for(const object of outlined)addStorybookOutline(object,.025);
 }
 const lookout=roots.find(r=>r.code==='watch_tower');lookout.height=6.1;
 // A seamless painted texture gives the roads grain and embedded cobbles.
 function paintedRoadTexture(){
   const canvas=document.createElement('canvas');canvas.width=canvas.height=256;const context=canvas.getContext('2d');let textureSeed=8317;
   const textureRand=()=>{textureSeed=(textureSeed*1664525+1013904223)>>>0;return textureSeed/4294967296;};
   context.fillStyle='#d7b777';context.fillRect(0,0,256,256);
   for(let i=0;i<32;i++){const x=textureRand()*256,y=textureRand()*256,r=10+textureRand()*32;context.globalAlpha=.045+textureRand()*.075;context.fillStyle=i%3?'#b98f54':'#f0d79b';context.beginPath();context.ellipse(x,y,r,r*(.35+textureRand()*.45),textureRand()*Math.PI,0,Math.PI*2);context.fill();}
   context.globalAlpha=1;
   for(let i=0;i<22;i++){const x=textureRand()*256,y=textureRand()*256,w=7+textureRand()*13,h=4+textureRand()*7,angle=textureRand()*.5-.25;context.save();context.translate(x,y);context.rotate(angle);context.globalAlpha=.28+textureRand()*.22;context.fillStyle=['#c39f68','#ead096','#a98558'][i%3];context.beginPath();context.ellipse(0,0,w,h,0,0,Math.PI*2);context.fill();context.restore();}
   for(let i=0;i<70;i++){context.globalAlpha=.025+textureRand()*.04;context.fillStyle=i%2?'#f6dfaa':'#765a3d';context.fillRect(textureRand()*256,textureRand()*256,1+textureRand()*1.3,1+textureRand()*1.3);}
   context.globalAlpha=1;const texture=new T.CanvasTexture(canvas);texture.wrapS=texture.wrapT=T.RepeatWrapping;texture.colorSpace=T.SRGBColorSpace;texture.anisotropy=4;return texture;
 }
 function paintedPavingTexture(){
   const canvas=document.createElement('canvas');canvas.width=canvas.height=256;const context=canvas.getContext('2d');let seed=22591;
   const rand=()=>{seed=(seed*1664525+1013904223)>>>0;return seed/4294967296;};
   context.fillStyle='#c9b99a';context.fillRect(0,0,256,256);
   for(let row=-1;row<10;row++)for(let column=-1;column<7;column++){
     const x=column*42+(row%2)*20+(rand()-.5)*7,y=row*29+(rand()-.5)*5,w=17+rand()*6,h=10+rand()*4;
     context.save();context.translate(x,y);context.rotate((rand()-.5)*.18);context.fillStyle=['#ddd0b5','#b8a88a','#cbbd9f','#e5d8be'][Math.floor(rand()*4)];context.strokeStyle='rgba(91,72,55,.26)';context.lineWidth=2.2;context.beginPath();context.ellipse(0,0,w,h,0,0,Math.PI*2);context.fill();context.stroke();context.restore();
   }
   context.globalAlpha=.08;for(let i=0;i<70;i++){context.fillStyle=i%2?'#fff7df':'#65513e';context.fillRect(rand()*256,rand()*256,1+rand()*2,1+rand()*2);}context.globalAlpha=1;
   const texture=new T.CanvasTexture(canvas);texture.wrapS=texture.wrapT=T.RepeatWrapping;texture.colorSpace=T.SRGBColorSpace;texture.anisotropy=4;return texture;
 }
 const roadTexture=paintedRoadTexture(),roadMat=new T.MeshStandardMaterial({map:roadTexture,bumpMap:roadTexture,bumpScale:.035,roughness:1,color:'#fff1cf'}),pavingTexture=paintedPavingTexture(),pavingMat=new T.MeshStandardMaterial({map:pavingTexture,bumpMap:pavingTexture,bumpScale:.045,roughness:1,color:'#fff8e8'}),edges=[],pavingDummy=new T.Object3D();let pathIndex=0;
 villageRoadMaterial=roadMat;
 function path(points,width,surface='earth'){
   const curve=new T.CatmullRomCurve3(points.map(([x,z])=>new T.Vector3(x,.18+villageElevationAt(x,z),z))),length=curve.getLength(),segments=Math.max(32,Math.ceil(length*1.5)),positions=[],uv=[],indices=[];
   for(let i=0;i<=segments;i++){
     const t=i/segments,p=curve.getPoint(t),tangent=curve.getTangent(t),planar=Math.hypot(tangent.x,tangent.z)||1,nx=-tangent.z/planar,nz=tangent.x/planar;
     positions.push(p.x+nx*width,p.y,p.z+nz*width,p.x-nx*width,p.y,p.z-nz*width);uv.push(t*length/4.4,0,t*length/4.4,1);
     if(i<segments){const k=i*2;indices.push(k,k+2,k+1,k+1,k+2,k+3);}
   }
   const geom=new T.BufferGeometry();geom.setAttribute('position',new T.Float32BufferAttribute(positions,3));geom.setAttribute('uv',new T.Float32BufferAttribute(uv,2));geom.setIndex(indices);geom.computeVertexNormals();
   const road=mesh(geom,surface==='stone'?pavingMat:roadMat,scene);road.receiveShadow=true;road.castShadow=false;
   const count=Math.max(2,Math.floor(length/1.05));
   for(let i=1;i<count;i++){
     if(i%3===0){const t=i/count,p=curve.getPoint(t),tangent=curve.getTangent(t).normalize(),angle=-Math.atan2(tangent.z,tangent.x);for(const side of [-1,1]){const offset=width+.15+((i+pathIndex)%3)*.035;edges.push([p.x-tangent.z*offset*side,p.y+.06,p.z+tangent.x*offset*side,.27+(i%3)*.035,.13,.19+(i%2)*.04,angle+(i%3-1)*.12]);}}
   }
   pathIndex++;
 }
 // Sweeping streets connect distinct neighbourhoods and each front entrance.
 for(const road of villageRoads)path(road.points,road.width,road.surface);
 // Small planted gardens use only free lawn, even when the authored layout moves.
 const gardenSpots=[[-36,-8],[-34,9],[-16,-15],[17,-15],[32,4],[23,25],[-31,25],[-7,34],[36,18],[-35,-26]].filter(([x,z])=>isVillageGardenSpot(x,z,2.7)).slice(0,4);
 for(const [x,z] of gardenSpots){
   const garden=group(x,.14+villageElevationAt(x,z),z);
   const lawn=mesh(new T.CylinderGeometry(2.6,2.8,.06,18),M.leafLight,garden);lawn.scale.z=.72;lawn.receiveShadow=true;
   for(const [dx,dz] of [[-1.7,-.7],[1.5,-1.1],[1.8,1]]){
     const shrub=mesh(bushGeo,leaf,garden,dx,.38,dz);shrub.scale.set(1.5,1.3,1.4);
   }
   box(garden,1.8,.14,.58,0,.6,1.25,M.timber);
   for(const side of [-1,1])box(garden,.16,.55,.45,side*.66,.3,1.25,M.wood);
 }

 function pavingBatch(rows,material,geometry){const batch=new T.InstancedMesh(geometry,material,rows.length);rows.forEach((row,i)=>{const [x,y,z,sx,sy,sz,rotation]=row;pavingDummy.position.set(x,y,z);pavingDummy.scale.set(sx,sy,sz);pavingDummy.rotation.set(0,rotation,0);pavingDummy.updateMatrix();batch.setMatrixAt(i,pavingDummy.matrix);});batch.instanceMatrix.needsUpdate=true;batch.receiveShadow=true;batch.castShadow=false;batch.computeBoundingSphere();scene.add(batch);}
 pavingBatch(edges,M.stone,new T.DodecahedronGeometry(1,0));
 const {x:fountainX,z:fountainZ}=villageLandmarks.fountain,fountainElevation=villageElevationAt(fountainX,fountainZ),fountain=group(fountainX,.15+fountainElevation,fountainZ);
 cyl(fountain,3.05,.1,0,.03,0,pavingMat,36);cyl(fountain,1.42,.38,0,.27,0,M.stone,32);cyl(fountain,1.16,.055,0,.48,0,M.water,32);cyl(fountain,.27,.9,0,.78,0,M.cream,20);cyl(fountain,.72,.15,0,1.22,0,M.stone,24);cyl(fountain,.1,.38,0,1.46,0,M.water,12);
 // Two quiet benches and four lanterns turn the fountain into a useful civic square.
 for(const side of [-1,1]){const bench=group(fountainX+side*6,.15+fountainElevation,fountainZ);bench.rotation.y=side<0?Math.PI/2:-Math.PI/2;box(bench,1.7,.14,.56,0,.52,0,M.timber);box(bench,1.7,.62,.12,0,.76,-.22,M.wood);for(const x of [-.62,.62])box(bench,.13,.52,.44,x,.26,0,M.wood);}
 for(const [x,z] of [[-6,-12],[6,-12],[-6,-20],[6,-20]]){cyl(scene,.07,2,x,1.2+fountainElevation,z,M.wood,10);mesh(new T.SphereGeometry(.22,12,8),M.glow,scene,x,2.28+fountainElevation,z);}
 // Loose pairs and gaps replace the evenly spaced ring of identical trees.
 // Every trunk stays beyond the wall and away from the south gate approach.
 for(let i=0;i<40;i++){
   if(i%7===3||i%7===4)continue;
   const progress=i/40+Math.sin(i*2.7)*.0035;
   if(Math.abs(progress-.25)<.035)continue;
   const margin=1.65+(i%3)*.27;
   const [x,z]=villageBoundaryPoint(progress,margin);tree(x,z,1.1+(i%4)*.24,.12+villageElevationAt(x,z));
 }
 for(const [x,z,s] of [[-36,-17,1.4],[-15,-16,1.2],[15,-16,1.25],[-13,-36,1.35],[13,-36,1.45],[36,-25,1.35],[-36,4,1.25],[-8,19,1.2],[8,36,1.3],[36,22,1.2]])if(isVillageGardenSpot(x,z,1.2))tree(x,z,s,.12+villageElevationAt(x,z));
 for(const [x,z] of [[-9,-21],[-29,11],[-11,19],[15,-24],[29,-17],[5,32],[-27,24],[28,19]]){
   if(!isVillageGardenSpot(x,z,2.2))continue;
   for(let i=0;i<3;i++)mesh(bushGeo,leaf,scene,x+i*.6,.43,z);
   const elevation=villageElevationAt(x,z),bench=group(x,.15+elevation,z+1);box(bench,1.4,.12,.5,0,.5,0,'wood2');for(const side of [-1,1])box(bench,.12,.5,.45,side*.5,.25,0,'wood');
   cyl(scene,.06,1.8,x+1.6,.95+elevation,z,'wood',10);mesh(new T.SphereGeometry(.19,12,8),gold,scene,x+1.6,1.95+elevation,z);
 }
 return roots;
}

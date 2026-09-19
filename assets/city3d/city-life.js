import * as T from './vendor/three.module.js';
import {createCharacter} from './character-family.js';
import {storybookMaterials as M,addStorybookOutline} from './storybook-style.js?v=storybook5';
import {villageBuildings,villageRoads,villageBoundary,villageBoundaryPoint,villageElevationAt,isVillageGardenSpot} from './village-layout.js?v=finished-village5';

// Lightweight street life for the playable city: pedestrians follow the roads,
// workers stay near their trade, and three guards patrol safe wall sections.
export function buildCityLife({scene}){
 const animated=[],walkers=[],guards=[],butterflies=[];
 const root=new T.Group();root.name='city-life';scene.add(root);
 const unitBox=new T.BoxGeometry(1,1,1),unitBall=new T.SphereGeometry(1,12,8),unitCylinder=new T.CylinderGeometry(1,1,1,7),dummy=new T.Object3D();
 let scenerySeed=7411;const sceneryRand=()=>{scenerySeed=(scenerySeed*1664525+1013904223)>>>0;return scenerySeed/4294967296;};
 function part(parent,geometry,material,x,y,z,outline=false){const object=new T.Mesh(geometry,material);object.position.set(x,y,z);object.castShadow=object.receiveShadow=true;parent.add(object);if(outline)addStorybookOutline(object,.018);return object;}
 function box(parent,w,h,d,x,y,z,material,outline=false){return part(parent,new T.BoxGeometry(w,h,d),material,x,y,z,outline);}
 function person(x,z,{shirt=M.blue,hat=null,scale=1,role='walk'}={}){
   const familyRole=hat==='guard'?'guard':role==='walk'?'citizen':'worker';
   const tool=role==='farm'?'rake':['mine','wood','market'].includes(role)?'carry':'hammer';
   const unit=createCharacter({role:familyRole,cloth:'#'+shirt.color.getHexString(),tool,phase:animated.length*.37});
   const g=unit.root;g.position.set(x,.17+villageElevationAt(x,z),z);g.scale.setScalar(scale*.78);root.add(g);
   unit.setAnimation(role==='walk'?'walk':tool==='carry'?'idle':'work');
   const actor={g,unit,role,rightArm:unit.bones.handR,leftArm:unit.bones.handL,phase:animated.length*.83};
   animated.push(actor);return actor;
 }
 function streetRoute(points){
   const curve=new T.CatmullRomCurve3(points.map(([x,z])=>new T.Vector3(x,.17+villageElevationAt(x,z),z)));
   // Follow the same smooth curve as the visible road, returning at the door.
   return {length:curve.getLength(),getPoint(t){const phase=((t%1)+1)%1;return curve.getPointAt(phase<=.5?phase*2:2-phase*2);}};
 }
 const routes=villageRoads.filter(road=>road.points.length>2).map(road=>streetRoute(road.points));
 const shirts=[M.red,M.blue,M.teal,M.purple,M.orange,M.blueLight,M.timber,M.leaf];
 for(let i=0;i<12;i++){
   const actor=person(0,0,{shirt:shirts[i%shirts.length],hat:i%4===0?'cap':null,scale:.88+(i%3)*.06});
   const route=routes[i%routes.length];walkers.push({actor,route,offset:i*.137,speed:(1.1+(i%3)*.12)/(route.length*2),lane:(i%3-1)*.12});
 }
 // Workers remain beside their building and clearly repeat a job motion.
 const jobs=[
   {code:'farm',x:-1.7,z:1.5,shirt:M.orange,hat:'straw',kind:'farm',turn:-.7},
   {code:'farm',x:2.5,z:2.4,shirt:M.teal,hat:'straw',kind:'farm',turn:2.5},
   {code:'quarry',x:-2.5,z:1.8,shirt:M.red,hat:'cap',kind:'hammer',turn:.4},
   {code:'quarry',x:2.5,z:1.8,shirt:M.blue,hat:'cap',kind:'hammer',turn:-1.4},
   {code:'gold_mine',x:2.5,z:2.4,shirt:M.orange,hat:'cap',kind:'mine',turn:2.7},
   {code:'lumber_camp',x:-3.35,z:1.6,shirt:M.teal,hat:'cap',kind:'wood',turn:-1},
   {code:'trading_post',x:3.4,z:2.55,shirt:M.purple,hat:null,kind:'market',turn:2.9},
 ];
 for(const job of jobs){
   const site=villageBuildings[job.code],actor=person(site.x+job.x*site.scale,site.z+job.z*site.scale,{shirt:job.shirt,hat:job.hat,scale:.94,role:job.kind});actor.g.rotation.y=job.turn;
 }
 // Three guards patrol separate wall sections and never cross the open south gate.
 const gateSegment=villageBoundary.segments/4;
 const patrols=[[gateSegment-8,gateSegment-2],[gateSegment+2,gateSegment+8],[gateSegment+13,gateSegment+21]];
 patrols.forEach(([start,end],index)=>{const actor=person(0,0,{shirt:M.blue,hat:'guard',scale:1});guards.push({actor,start,end,phase:index*.37,speed:.035+index*.004});});

 // Broad decorative clusters fill empty lawns without blocking the streets.
 const flowerRows=[[-35,-14],[-19,-14],[19,-14],[33,-25],[-20,5],[-17,18],[-4,25],[18,26],[25,9],[23,-8],[-14,-22],[-31,20],[7,34]].filter(([x,z])=>isVillageGardenSpot(x,z,1));
 for(let tries=0;flowerRows.length<24&&tries<320;tries++){
   const x=(sceneryRand()-.5)*(villageBoundary.wallX*2-5),z=(sceneryRand()-.5)*(villageBoundary.wallZ*2-5);
   if(isVillageGardenSpot(x,z,.95))flowerRows.push([x,z]);
 }
 const flowerMaterials=[M.red,M.purple,M.glow],stems=[],blooms=[],stones=[],grassTufts=[],darkGrassTufts=[],mushroomStems=[],redCaps=[],goldCaps=[];
 for(const [cx,cz] of flowerRows)for(let i=0;i<5;i++){
   const x=cx+(i%3)*.34,z=cz+Math.floor(i/3)*.38;
   const elevation=villageElevationAt(x,z);stems.push([x,.29+elevation,z,.035,.35,.035]);blooms.push([x,.53+elevation,z,.11,.08,.11,i%flowerMaterials.length]);
 }
 for(const [x,z,s] of [[-18,20,.45],[-4,-18,.55],[11,-24,.65],[22,11,.5],[29,-2,.55],[-30,18,.58],[-20,-20,.44],[6,7,.4]])if(isVillageGardenSpot(x,z,.7))stones.push([x,.2+villageElevationAt(x,z),z,s,.38,s*.8]);
 for(let tries=0;stones.length<22&&tries<240;tries++){
   const x=(sceneryRand()-.5)*(villageBoundary.wallX*2-6),z=(sceneryRand()-.5)*(villageBoundary.wallZ*2-6),s=.22+sceneryRand()*.32;
   if(isVillageGardenSpot(x,z,.55))stones.push([x,.16+villageElevationAt(x,z),z,s,.28+sceneryRand()*.2,s*(.65+sceneryRand()*.25),sceneryRand()*2]);
 }
 for(const [x,z] of [[-11,4],[-5,17],[3,24],[12,3],[19,10],[27,-3],[-29,8],[-18,-17],[10,-19],[29,22]])if(isVillageGardenSpot(x,z,.6))for(let i=0;i<4;i++)grassTufts.push([x+i*.18,.28+villageElevationAt(x,z),z+(i%2)*.12,.055,.42,.055,(i-1.5)*.16]);
 for(let tries=0;grassTufts.length+darkGrassTufts.length<150&&tries<1200;tries++){
   const x=(sceneryRand()-.5)*(villageBoundary.wallX*2-4),z=(sceneryRand()-.5)*(villageBoundary.wallZ*2-4);
   if(!isVillageGardenSpot(x,z,.34))continue;
   const tuft=[x,.2+villageElevationAt(x,z),z,.035+sceneryRand()*.035,.25+sceneryRand()*.34,.035+sceneryRand()*.025,(sceneryRand()-.5)*.5];
   (sceneryRand()>.68?darkGrassTufts:grassTufts).push(tuft);
 }
 for(let cluster=0;cluster<9;cluster++){
   let x=0,z=0,found=false;
   for(let tries=0;tries<100&&!found;tries++){x=(sceneryRand()-.5)*(villageBoundary.wallX*2-7);z=(sceneryRand()-.5)*(villageBoundary.wallZ*2-7);found=isVillageGardenSpot(x,z,.55);}
   if(!found)continue;
   for(let i=0;i<2+(cluster%2);i++){
     const px=x+(sceneryRand()-.5)*.45,pz=z+(sceneryRand()-.5)*.4,h=.14+sceneryRand()*.12;
     const elevation=villageElevationAt(px,pz);mushroomStems.push([px,.16+h*.5+elevation,pz,.035,h,.035]);
     (cluster%3===0?goldCaps:redCaps).push([px,.18+h+elevation,pz,.12+sceneryRand()*.05,.065,.12+sceneryRand()*.05]);
   }
 }
 function instances(geometry,material,rows,withColor=false){const batch=new T.InstancedMesh(geometry,material,rows.length);rows.forEach((row,i)=>{const [x,y,z,sx,sy,sz,rotation=0]=row;dummy.position.set(x,y,z);dummy.scale.set(sx,sy,sz);dummy.rotation.set(0,rotation,0);dummy.updateMatrix();batch.setMatrixAt(i,dummy.matrix);if(withColor)batch.setColorAt(i,flowerMaterials[row[6]].color);});batch.castShadow=batch.receiveShadow=true;batch.computeBoundingSphere();root.add(batch);return batch;}
 instances(unitBox,M.leaf,stems);instances(unitBall,M.glow,blooms,true);instances(new T.DodecahedronGeometry(1,0),M.patch,stones);instances(new T.ConeGeometry(1,1,5),M.leafLight,grassTufts);instances(new T.ConeGeometry(1,1,5),M.leaf,darkGrassTufts);instances(unitCylinder,M.cream,mushroomStems);instances(unitBall,M.red,redCaps);instances(unitBall,M.glow,goldCaps);
 // A few useful props make the open lawns feel occupied.
 for(const [x,z,r] of [[-12,2,.4],[11,6,-.8],[12,27,.2],[-20,23,1.1]]){
   if(!isVillageGardenSpot(x,z,1.2))continue;
   const cart=new T.Group();cart.position.set(x,.18+villageElevationAt(x,z),z);cart.rotation.y=r;root.add(cart);box(cart,1.1,.2,.65,0,.55,0,M.timber,true);box(cart,.08,.08,1.25,-.85,.43,0,M.wood);
   for(const side of [-1,1]){const wheel=part(cart,new T.TorusGeometry(.28,.06,6,16),M.dark,side*.43,.35,.37,false);wheel.rotation.y=Math.PI/2;}
 }
 for(const [x,z] of [[-6,20],[12,11],[21,-7],[-23,4]]){
   if(!isVillageGardenSpot(x,z,.8))continue;
   const sign=new T.Group();sign.position.set(x,.16+villageElevationAt(x,z),z);root.add(sign);box(sign,.1,1.05,.1,0,.52,0,M.wood);box(sign,.78,.38,.1,.22,1.02,0,M.timber,true);
 }
 // Butterflies add small, readable motion over the flower beds.
 for(let i=0;i<Math.min(7,flowerRows.length);i++){const g=new T.Group();root.add(g);const left=box(g,.16,.025,.11,-.09,0,0,i%2?M.purple:M.orange),right=box(g,.16,.025,.11,.09,0,0,i%2?M.purple:M.orange);butterflies.push({g,left,right,cx:flowerRows[i][0],cz:flowerRows[i][1],phase:i*.9});}

 function wallWalkPoint(segment){
   const {segments}=villageBoundary,total=((segment%segments)+segments)%segments,index=Math.floor(total),t=total-index;
   const [ax,az]=villageBoundaryPoint(index/segments),[bx,bz]=villageBoundaryPoint((index+1)/segments);
   return new T.Vector3(T.MathUtils.lerp(ax,bx,t),2.425,T.MathUtils.lerp(az,bz,t));
 }

 function animate(time){
   for(const {actor,route,offset,speed,lane} of walkers){
     const t=(offset+time*speed)%1,p=route.getPoint(t),ahead=route.getPoint((t+.001)%1),dx=ahead.x-p.x,dz=ahead.z-p.z,length=Math.hypot(dx,dz)||1,step=Math.sin(time*6.2+actor.phase);
     p.x+=dz/length*lane;p.z-=dx/length*lane;actor.g.position.copy(p);actor.g.rotation.y=Math.atan2(dx,dz);
   }
   for(const actor of animated)actor.unit.animate(time);
   for(const {actor,start,end,phase,speed} of guards){
     const raw=(phase+time*speed)%2,t=raw<=1?raw:2-raw,segment=start+(end-start)*t,dir=raw<=1?1:-1,p=wallWalkPoint(segment),ahead=wallWalkPoint(segment+dir*.04),dx=ahead.x-p.x,dz=ahead.z-p.z;
     actor.g.position.copy(p);actor.g.rotation.y=Math.atan2(dx,dz);
   }
   butterflies.forEach(({g,left,right,cx,cz,phase})=>{const a=time*.65+phase,r=.55+.16*Math.sin(time+phase);g.position.set(cx+Math.cos(a)*r,.65+villageElevationAt(cx,cz)+Math.sin(time*2.3+phase)*.16,cz+Math.sin(a)*r);const flap=.25+Math.abs(Math.sin(time*9+phase))*.8;left.rotation.z=flap;right.rotation.z=-flap;g.rotation.y=-a;});
 }
 return {root,animate,peopleCount:animated.length,characters:animated.map(a=>a.unit)};
}

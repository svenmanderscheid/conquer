// One layout for buildings, roads and residents. Coordinates are scene-local,
// independent of saved building levels.
export const villageBoundary={wallX:44,wallZ:42,cornerRadius:12,segments:88,shape:'rounded-rectangle'};
export const villageOverview={x:0,z:-3};
export const villageTerrace={centerX:0,centerZ:-17,halfX:40,halfZ:22,cornerRadius:9,height:2.25,rampHalfWidth:4,rampEndZ:14};
export const villageLandmarks={
 fountain:{x:0,z:-16,radius:3.35},
};

// Clockwise rounded rectangle, beginning at the middle of the eastern wall.
// Keeping the perimeter in one shared function makes wall, guards, shoreline
// and decorative planting agree exactly. A positive offset expands the shape.
export function villageBoundaryPoint(progress,offset=0){
 const {wallX,wallZ,cornerRadius}=villageBoundary,hx=wallX+offset,hz=wallZ+offset,r=cornerRadius+offset;
 const horizontal=hx-r,vertical=hz-r,quarter=Math.PI*r/2,perimeter=4*(horizontal+vertical)+Math.PI*2*r;
 let distance=(((progress%1)+1)%1)*perimeter;
 const line=(length,fromX,fromZ,toX,toZ)=>{const t=distance/length;return [fromX+(toX-fromX)*t,fromZ+(toZ-fromZ)*t];};
 if(distance<vertical)return line(vertical,hx,0,hx,vertical);distance-=vertical;
 if(distance<quarter){const a=distance/r;return [horizontal+r*Math.cos(a),vertical+r*Math.sin(a)];}distance-=quarter;
 if(distance<2*horizontal)return line(2*horizontal,horizontal,hz,-horizontal,hz);distance-=2*horizontal;
 if(distance<quarter){const a=Math.PI/2+distance/r;return [-horizontal+r*Math.cos(a),vertical+r*Math.sin(a)];}distance-=quarter;
 if(distance<2*vertical)return line(2*vertical,-hx,vertical,-hx,-vertical);distance-=2*vertical;
 if(distance<quarter){const a=Math.PI+distance/r;return [-horizontal+r*Math.cos(a),-vertical+r*Math.sin(a)];}distance-=quarter;
 if(distance<2*horizontal)return line(2*horizontal,-horizontal,-hz,horizontal,-hz);distance-=2*horizontal;
 if(distance<quarter){const a=Math.PI*1.5+distance/r;return [horizontal+r*Math.cos(a),-vertical+r*Math.sin(a)];}distance-=quarter;
 return line(vertical,hx,-vertical,hx,0);
}

export function isInsideVillageBoundary(x,z,margin=0){
 const {wallX,wallZ,cornerRadius}=villageBoundary,hx=wallX-margin,hz=wallZ-margin,r=Math.max(.1,cornerRadius-margin);
 if(Math.abs(x)>hx||Math.abs(z)>hz)return false;
 const dx=Math.max(0,Math.abs(x)-(hx-r)),dz=Math.max(0,Math.abs(z)-(hz-r));
 return dx*dx+dz*dz<=r*r;
}

export function isInsideVillageTerrace(x,z,margin=0){
 const {centerX,centerZ,halfX,halfZ,cornerRadius}=villageTerrace,hx=halfX-margin,hz=halfZ-margin,r=Math.max(.1,cornerRadius-margin);
 const localX=Math.abs(x-centerX),localZ=Math.abs(z-centerZ);
 if(localX>hx||localZ>hz)return false;
 const dx=Math.max(0,localX-(hx-r)),dz=Math.max(0,localZ-(hz-r));
 return dx*dx+dz*dz<=r*r;
}

export function villageTerracePoint(progress){
 const {centerX,centerZ,halfX:hx,halfZ:hz,cornerRadius:r}=villageTerrace,horizontal=hx-r,vertical=hz-r,quarter=Math.PI*r/2,perimeter=4*(horizontal+vertical)+Math.PI*2*r;
 let distance=(((progress%1)+1)%1)*perimeter;
 const line=(length,fromX,fromZ,toX,toZ)=>{const t=distance/length;return [centerX+fromX+(toX-fromX)*t,centerZ+fromZ+(toZ-fromZ)*t];};
 if(distance<vertical)return line(vertical,hx,0,hx,vertical);distance-=vertical;
 if(distance<quarter){const a=distance/r;return [centerX+horizontal+r*Math.cos(a),centerZ+vertical+r*Math.sin(a)];}distance-=quarter;
 if(distance<2*horizontal)return line(2*horizontal,horizontal,hz,-horizontal,hz);distance-=2*horizontal;
 if(distance<quarter){const a=Math.PI/2+distance/r;return [centerX-horizontal+r*Math.cos(a),centerZ+vertical+r*Math.sin(a)];}distance-=quarter;
 if(distance<2*vertical)return line(2*vertical,-hx,vertical,-hx,-vertical);distance-=2*vertical;
 if(distance<quarter){const a=Math.PI+distance/r;return [centerX-horizontal+r*Math.cos(a),centerZ-vertical+r*Math.sin(a)];}distance-=quarter;
 if(distance<2*horizontal)return line(2*horizontal,-horizontal,-hz,horizontal,-hz);distance-=2*horizontal;
 if(distance<quarter){const a=Math.PI*1.5+distance/r;return [centerX+horizontal+r*Math.cos(a),centerZ-vertical+r*Math.sin(a)];}distance-=quarter;
 return line(vertical,hx,-vertical,hx,0);
}

export function villageElevationAt(x,z){
 const {height,rampHalfWidth,rampEndZ}=villageTerrace;
 if(isInsideVillageTerrace(x,z))return height;
 const terraceFront=villageTerrace.centerZ+villageTerrace.halfZ;
 if(Math.abs(x)<=rampHalfWidth&&z>terraceFront&&z<rampEndZ)return height*(rampEndZ-z)/(rampEndZ-terraceFront);
 return 0;
}
// The illustrated reference uses taller, more confident silhouettes than a
// purely uniform 3D scale. Ground footprints stay untouched for roads/routes.
export const villageBuildingHeightScale=1.12;
export const villageBuildings={
 // Upper town: civic and service buildings occupy the raised northern terrace.
 castle:{x:0,z:-28,scale:1.45,radius:6},
 academy:{x:-23,z:-28,scale:1.65,radius:4.9},
 treasure_house:{x:23,z:-28,scale:1.6,radius:5.3},
 hospital:{x:-28,z:-9,scale:1.55,radius:5.5},
 hall_of_alliance:{x:-10,z:-9,scale:1.55,radius:5.8},
 trading_post:{x:9,z:-9,scale:1.7,radius:6.5},
 storage:{x:28,z:-9,scale:1.55,radius:4.7},
 watch_tower:{x:35,z:-18,scale:1.4,radius:3.2,rotation:-.22},
 // Lower town: a western military yard and eastern production quarter flank
 // the straight route from the gate to the central terrace ramp.
 stable:{x:-28,z:10,scale:1.4,radius:5.5},
 archery_range:{x:-34,z:27,scale:1.4,radius:5.4},
 barrack:{x:-13,z:28,scale:1.6,radius:6.8},
 farm:{x:13,z:15,scale:1.4,radius:5.5},
 gold_mine:{x:13,z:30,scale:1.4,radius:4.8,rotation:Math.PI},
 lumber_camp:{x:30,z:15,scale:1.3,radius:5.4,rotation:0},
 quarry:{x:30,z:30,scale:1.4,radius:4.9,rotation:Math.PI},
 wall:{x:0,z:villageBoundary.wallZ,scale:1,radius:4}
};

export const villageRoads=[
 // The gate road divides the lower town and becomes the broad central ramp.
 {id:'gate',width:1.4,points:[[0,44],[0,36],[0,28],[0,20],[0,14]]},
 {id:'terrace-ramp',surface:'stone',width:1.55,points:[[0,14],[0,10],[0,5],[0,0],[0,-1.5]]},
 // The paved upper avenue opens into two gentle arms around the fountain.
 // All façades face their row and no route passes through a neighbour.
 {id:'upper-avenue',surface:'stone',width:1.2,points:[[0,-1.5],[0,-7],[0,-11.5]]},
 {id:'plaza-west',surface:'stone',width:1.05,points:[[0,-11.5],[-3.9,-13.5],[-4,-17.7],[0,-20]]},
 {id:'plaza-east',surface:'stone',width:1.05,points:[[0,-11.5],[3.9,-13.5],[4,-17.7],[0,-20]]},
 {id:'academy',surface:'stone',width:1,points:[[0,-20],[-8,-20],[-16,-21],[-23,-22]]},
 {id:'castle',surface:'stone',width:1.15,points:[[0,-20],[0,-22]]},
 {id:'treasury',surface:'stone',width:1,points:[[0,-20],[8,-20],[16,-21],[23,-22]]},
 {id:'hospital',surface:'stone',width:1,points:[[0,-3],[-9,-3],[-18,-3],[-28,-3]]},
 {id:'alliance',surface:'stone',width:1,points:[[0,-3],[-5,-3],[-10,-3]]},
 {id:'market',surface:'stone',width:1.1,points:[[0,-3],[5,-3],[9,-2.5]]},
 {id:'storage',surface:'stone',width:1,points:[[9,-2.5],[18,-3],[28,-3]]},
 {id:'watch',surface:'stone',width:1,points:[[28,-3],[34,-7],[35,-13.5]]},
 // Lower military and production lanes stay on their own sides of the gate road.
 {id:'stable',width:1.05,points:[[0,20],[-8,19],[-18,16],[-28,15.1]]},
 // Approach every court from the open front; residents share these curves.
 {id:'garrison',width:1,points:[[-18,16],[-22,22],[-23,29],[-23,35.5]]},
 {id:'archers',width:1,points:[[-23,35.5],[-29,35],[-34,32]]},
 {id:'infantry',width:1,points:[[-23,35.5],[-18,36],[-13,35.2]]},
 {id:'production',width:1.05,points:[[0,20],[7,20],[13,20],[21,21],[30,20]]},
 {id:'gold',width:1,points:[[13,20],[13,25]]},
 {id:'quarry',width:1,points:[[30,20],[30,25]]},
 {id:'production-south',width:1,points:[[13,25],[20,34],[30,25]]},
 {id:'bridge',width:1.25,points:[[0,44],[0,51],[0,60],[0,66]]},
];

export function placeVillageBuilding(root,code){
 const p=villageBuildings[code],height=code==='wall'?1:villageBuildingHeightScale;
 root.position.set(p.x,(code==='castle'||code==='lumber_camp'?.1:.12)+villageElevationAt(p.x,p.z),p.z);
 root.rotation.y=p.rotation??0;
 root.scale.set(p.scale,p.scale*height,p.scale);
}

// Ground props leave generous room around both entrances and the walking lanes.
export function isVillageGardenSpot(x,z,padding=1){
 if(!isInsideVillageBoundary(x,z,padding))return false;
 const localHeights=[[x,z],[x-padding,z],[x+padding,z],[x,z-padding],[x,z+padding]].map(([px,pz])=>villageElevationAt(px,pz));
 if(Math.max(...localHeights)-Math.min(...localHeights)>.15)return false;
 if(Object.values(villageLandmarks).some(landmark=>Math.hypot(x-landmark.x,z-landmark.z)<landmark.radius+padding))return false;
 if(Object.entries(villageBuildings).some(([code,b])=>code!=='wall'&&Math.hypot(x-b.x,z-b.z)<b.radius+padding))return false;
 for(const road of villageRoads)for(let i=1;i<road.points.length;i++){
  const [ax,az]=road.points[i-1],[bx,bz]=road.points[i],dx=bx-ax,dz=bz-az;
  const t=Math.max(0,Math.min(1,((x-ax)*dx+(z-az)*dz)/(dx*dx+dz*dz||1)));
  if(Math.hypot(x-ax-t*dx,z-az-t*dz)<road.width+padding+.6)return false;
 }
 return true;
}

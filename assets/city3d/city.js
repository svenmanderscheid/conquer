import * as T from './vendor/three.module.js';

// Representative visual load, with shared geometry and instanced marching units.
// No combat simulation, pathfinding, persistence or networking is implied.
export function buildTestCity({scene,box,mesh,roof,flag,mats}) {
  const root=new T.Group();scene.add(root);
  const buildings=[],data={};
  mesh(new T.CylinderGeometry(20.5,20.1,.62,48),'earth',root,0,-.4,0);
  mesh(new T.CylinderGeometry(20.45,20.45,.09,48),'grass',root,0,-.045,0);
  for(const radius of [10.05,16.6]){
    const ring=mesh(new T.RingGeometry(radius-.55,radius+.55,96),'path',root,0,.105,0);ring.rotation.x=-Math.PI/2;
  }
  for(const angle of [0,Math.PI/2]){const path=box(root,1.1,.025,38,0,.095,0,'path');path.rotation.y=angle;}
  const labels=['Kaserne','Wohnhaus','Werkstatt','Lagerhaus','Akademie','Wohnhaus','Hospital','Wohnhaus','Schmiede','Lagerhaus','Kaserne','Wohnhaus','Werkstatt','Wohnhaus','Akademie','Lagerhaus','Hospital','Wohnhaus','Schmiede','Wohnhaus'];
  const prototypes=[];
  // Four reusable silhouettes keep this a load test rather than twenty final assets.
  for(let style=0;style<4;style++){
    const p=new T.Group();
    box(p,2.8,.23,2.65,0,.14,0,'stone2');box(p,2.4,1.55,2.1,0,.99,0,'stone');
    for(const x of [-1.15,1.15])box(p,.15,1.65,2.2,x,1.03,0,'wood');
    box(p,2.5,.14,2.2,0,1.74,0,'wood');
    roof(p,2.9,2.7,1.85,.85+style*.1);
    box(p,.58,1.04,.09,0,.77,1.1,'wood2');
    for(const x of [-.8,.8]){box(p,.32,.42,.08,x,1.19,1.1,'dark');box(p,.36,.055,.12,x,1.19,1.16,'wood2');}
    if(style===1){box(p,.38,.96,.4,.75,2.52,-.55,'stone2');box(p,.48,.12,.5,.75,3.04,-.55,'stone');}
    if(style===2){box(p,.95,2.9,1,-.75,1.6,-.5,'stone2');roof(p,1.2,1.25,3.07,.7,-.75,-.5);}
    if(style===3){box(p,2.2,.12,.8,0,1.52,1.42,'teal');for(const x of [-1,1])box(p,.08,1.45,.08,x,.75,1.72,'wood');}
    prototypes.push(p);
  }
  for(let i=0;i<20;i++){
    const angle=(i+.5)*Math.PI*2/20,r=13.35;
    const building=prototypes[i%4].clone(true);building.position.set(Math.sin(angle)*r,.08,Math.cos(angle)*r);building.rotation.y=angle+Math.PI;
    const id=`city-${i}`;building.userData.building=id;root.add(building);buildings.push(building);
    data[id]={title:`${labels[i]} · Viertel ${i+1}`,text:'Gebäude der größeren Teststadt. Vereinfachtes Modell für den Belastungstest; Gestaltung und Spielfunktion sind noch nicht final.',position:[building.position.x,building.position.z]};
    if(i%4===0)flag(building,1.2,1.1,1.2,.55,.9);
    if(i%5===0){box(building,.8,.5,.6,1.6,.27,0,'wood2');box(building,.85,.07,.66,1.6,.34,0,'wood');}
  }
  // A broken perimeter with four entrances provides readable city boundaries.
  for(let i=0;i<48;i++){
    if(i%12===0)continue;
    const a=i*Math.PI*2/48,r=19.1,g=new T.Group();g.position.set(Math.sin(a)*r,0,Math.cos(a)*r);g.rotation.y=a;root.add(g);
    box(g,2.45,1.05,.4,0,.53,0,'stone2');box(g,2.5,.13,.53,0,1.11,0,'stone');
    for(const x of [-.9,0,.9])box(g,.35,.3,.52,x,1.3,0,'stone');
  }
  // Merge static surfaces per selectable building and material. Keep animated
  // PlaneGeometry flags independent so their vertex animation still works.
  root.updateWorldMatrix(true,true);
  const batches=new Map(),originals=[];
  root.traverse(o=>{
    if(!o.isMesh||o.geometry.type==='PlaneGeometry')return;
    let owner=o.parent;while(owner!==root&&!owner.userData.building)owner=owner.parent;
    const key=owner.uuid+o.material.uuid;
    if(!batches.has(key))batches.set(key,{owner,material:o.material,parts:[]});
    const local=new T.Matrix4().copy(owner.matrixWorld).invert().multiply(o.matrixWorld);
    const geometry=o.geometry.index?o.geometry.toNonIndexed():o.geometry.clone();geometry.applyMatrix4(local);
    batches.get(key).parts.push(geometry);originals.push(o);
  });
  for(const o of originals)o.removeFromParent();
  for(const {owner,material,parts} of batches.values()){
    const length=parts.reduce((sum,g)=>sum+g.attributes.position.array.length,0);
    const positions=new Float32Array(length),normals=new Float32Array(length);let offset=0;
    for(const g of parts){positions.set(g.attributes.position.array,offset);normals.set(g.attributes.normal.array,offset);offset+=g.attributes.position.array.length;g.dispose();}
    const geometry=new T.BufferGeometry();geometry.setAttribute('position',new T.BufferAttribute(positions,3));geometry.setAttribute('normal',new T.BufferAttribute(normals,3));geometry.computeBoundingSphere();
    const merged=new T.Mesh(geometry,material);merged.castShadow=true;merged.receiveShadow=true;owner.add(merged);
  }
  const count=60,dummy=new T.Object3D();
  const unitParts=[
    {geometry:new T.BoxGeometry(.18,.26,.13),material:mats.teal,offset:[0,.43,0],kind:'body'},
    {geometry:new T.IcosahedronGeometry(.105,1),material:mats.stone3,offset:[0,.64,0],kind:'head'},
    {geometry:new T.ConeGeometry(.12,.13,6),material:mats.stone2,offset:[0,.75,0],kind:'helmet'},
    ...[-1,1].map(side=>({geometry:new T.BoxGeometry(.065,.24,.08),material:mats.wood,offset:[side*.056,.18,0],kind:'leg',side})),
    ...[-1,1].map(side=>({geometry:new T.BoxGeometry(.055,.23,.065),material:mats.stone2,offset:[side*.13,.43,0],kind:'arm',side}))
  ];
  for(const p of unitParts){p.batch=new T.InstancedMesh(p.geometry,p.material,count);p.batch.instanceMatrix.setUsage(T.DynamicDrawUsage);p.batch.castShadow=true;p.batch.frustumCulled=false;root.add(p.batch);}
  function animate(time){
    for(let i=0;i<count;i++){
      const lane=i%2,radius=lane?16.6:10.05,direction=lane?-1:1,a=Math.floor(i/2)/(count/2)*Math.PI*2+time*.055*direction;
      const x=Math.sin(a)*radius,z=Math.cos(a)*radius,yaw=a+(direction>0?Math.PI/2:-Math.PI/2),step=Math.sin(time*6+i*.8);
      for(const p of unitParts){
        const [ox,oy,oz]=p.offset;dummy.position.set(x+Math.cos(yaw)*ox+Math.sin(yaw)*oz,.1+oy+Math.abs(step)*.012,z-Math.sin(yaw)*ox+Math.cos(yaw)*oz);
        dummy.rotation.set(0,yaw,0);if(p.kind==='leg'||p.kind==='arm')dummy.rotateX(step*p.side*(p.kind==='leg'?.48:-.35));dummy.updateMatrix();p.batch.setMatrixAt(i,dummy.matrix);
      }
    }
    for(const p of unitParts)p.batch.instanceMatrix.needsUpdate=true;
  }
  animate(0);
  return {root,buildings,data,animate,unitCount:count,buildingCount:22};
}


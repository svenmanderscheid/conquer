// Scoped repair for the reviewed Meshy knight; never overwrites the source export.
const fs=require('fs'),path=require('path');
const source=path.resolve(__dirname,'../artifacts/knight-review/original.glb');
const output=path.resolve(__dirname,'../artifacts/knight-review/knight-rigid-equipment.glb');
const b=fs.readFileSync(source),fixed=Buffer.from(b),j=JSON.parse(b.subarray(20,20+b.readUInt32LE(12))),base=28+b.readUInt32LE(12);
const sizes={5121:1,5123:2,5125:4,5126:4},dims={SCALAR:1,VEC2:2,VEC3:3,VEC4:4,MAT4:16},methods={5121:'readUInt8',5123:'readUInt16LE',5125:'readUInt32LE',5126:'readFloatLE'};
function offset(a,k,c){const v=j.bufferViews[a.bufferView];return base+(v.byteOffset||0)+(a.byteOffset||0)+k*(v.byteStride||dims[a.type]*sizes[a.componentType])+c*sizes[a.componentType];}
function read(id){const a=j.accessors[id];return Array.from({length:a.count},(_,k)=>Array.from({length:dims[a.type]},(_,c)=>b[methods[a.componentType]](offset(a,k,c))));}
const primitive=j.meshes[0].primitives[0],p=read(primitive.attributes.POSITION),ji=read(primitive.attributes.JOINTS_0),w=read(primitive.attributes.WEIGHTS_0),ja=j.accessors[primitive.attributes.JOINTS_0],wa=j.accessors[primitive.attributes.WEIGHTS_0];
if(ja.componentType!==5121||wa.componentType!==5126)throw Error('Unexpected source layout');
const groups={sword:[],shield:[]},skin=j.skins[0];
const boneIndex=name=>skin.joints.findIndex(i=>j.nodes[i].name===name);
const target={sword:boneIndex('RightHand'),shield:boneIndex('LeftHand')};
if(Object.values(target).some(i=>i<0))throw Error('Missing hands');
(async()=>{
 const {equipmentRegion}=await import('../artifacts/knight-review/equipment-regions.mjs');
 p.forEach(([x,y,z],i)=>{const part=equipmentRegion(x,y,z);if(!part)return;groups[part].push(i);for(let c=0;c<4;c++){fixed.writeUInt8(c?0:target[part],offset(ja,i,c));fixed.writeFloatLE(c?0:1,offset(wa,i,c));}});
 if(groups.sword.length<100||groups.shield.length<100)throw Error('Unexpected selection');
 const T=await import('../assets/city3d/vendor/three.module.js');
 const nodes=j.nodes.map(n=>{const o=new T.Object3D();o.name=n.name;if(n.translation)o.position.fromArray(n.translation);if(n.rotation)o.quaternion.fromArray(n.rotation);if(n.scale)o.scale.fromArray(n.scale);return o;});
 j.nodes.forEach((n,i)=>(n.children||[]).forEach(k=>nodes[i].add(nodes[k])));const scene=new T.Scene();for(const i of j.scenes[j.scene||0].nodes)scene.add(nodes[i]);
 const inverse=read(skin.inverseBindMatrices).map(a=>new T.Matrix4().fromArray(a));
 const report={sourceBytes:b.length,triangles:j.accessors[primitive.indices].count/3,bones:skin.joints.length,selected:Object.fromEntries(Object.entries(groups).map(([k,g])=>[k,g.length])),animations:[]};
 for(const animation of j.animations){
  const tracks=animation.channels.map(c=>{const s=animation.samplers[c.sampler],times=read(s.input).flat(),values=read(s.output).flat(),prop={translation:'position',rotation:'quaternion',scale:'scale'}[c.target.path],Cls=c.target.path==='rotation'?T.QuaternionKeyframeTrack:T.VectorKeyframeTrack;return new Cls(nodes[c.target.node].uuid+'.'+prop,times,values,s.interpolation==='STEP'?T.InterpolateDiscrete:T.InterpolateLinear);});
  const mixer=new T.AnimationMixer(scene),clip=new T.AnimationClip(animation.name,-1,tracks);mixer.clipAction(clip).play();
  const stats={name:animation.name,duration:clip.duration,originalMaxRelativeDeformation:0,fixedMaxRelativeDeformation:0};
  for(let f=0;f<=40;f++){mixer.setTime(clip.duration*f/41);scene.updateMatrixWorld(true);const mats=skin.joints.map((n,i)=>new T.Matrix4().multiplyMatrices(nodes[n].matrixWorld,inverse[i]));
   const deform=(i,part,rigid)=>{const v=new T.Vector3().fromArray(p[i]);if(rigid)return v.applyMatrix4(mats[target[part]]);const out=new T.Vector3();for(let c=0;c<4;c++)out.addScaledVector(v.clone().applyMatrix4(mats[ji[i][c]]),w[i][c]);return out;};
   for(const [part,g] of Object.entries(groups)){const sample=Array.from({length:16},(_,k)=>g[Math.floor(k*(g.length-1)/15)]);for(let k=1;k<sample.length;k++){const a=sample[0],z=sample[k],d=new T.Vector3().fromArray(p[a]).distanceTo(new T.Vector3().fromArray(p[z]));if(d<.03)continue;for(const rigid of [false,true]){const err=Math.abs(deform(a,part,rigid).distanceTo(deform(z,part,rigid))/d-1);const key=rigid?'fixedMaxRelativeDeformation':'originalMaxRelativeDeformation';stats[key]=Math.max(stats[key],err);}}}
  }mixer.stopAllAction();mixer.uncacheRoot(scene);if(stats.fixedMaxRelativeDeformation>1e-4)throw Error('Rigid equipment still deforms');report.animations.push(stats);
 }
 fs.writeFileSync(output,fixed);fs.writeFileSync(path.join(path.dirname(output),'repair-report.json'),JSON.stringify(report,null,2));console.log(JSON.stringify(report,null,2));
})().catch(e=>{console.error(e);process.exitCode=1;});

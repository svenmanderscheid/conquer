import * as T from './vendor/three.module.js';
import {GLTFLoader} from './vendor/loaders/GLTFLoader.js';

const loader=new GLTFLoader();
const archerUrl=new URL('../art/characters/archer-t10-rigged-mobile.glb',import.meta.url).href;
const riderUrl=new URL('../art/characters/cavalry-t10-rigged-mobile.glb',import.meta.url).href;
const horseUrl=new URL('../art/characters/cavalry-t10-horse-rigged-mobile.glb',import.meta.url).href;
const position=o=>o.getWorldPosition(new T.Vector3());
const vector=(x,y,z)=>new T.Vector3(x,y,z);

function soften(root){
 root.traverse(object=>{
  if(!object.isMesh)return;
  const materials=Array.isArray(object.material)?object.material:[object.material];
  for(const material of materials){if('metalness'in material)material.metalness=0;if('roughness'in material)material.roughness=1;}
  object.castShadow=true;
 });
}

function normalize(root,height){
 root.updateMatrixWorld(true);
 const box=new T.Box3().setFromObject(root),size=box.getSize(new T.Vector3()),center=box.getCenter(new T.Vector3());
 const scale=height/Math.max(.001,size.y);
 root.scale.multiplyScalar(scale);
 root.position.set(-center.x*scale,-box.min.y*scale,-center.z*scale);
 root.updateMatrixWorld(true);
}

function captureBones(root){
 const bones=[];root.traverse(o=>{if(o.isBone)bones.push([o,o.position.clone(),o.quaternion.clone()]);});return bones;
}

function restoreBones(bones){for(const [bone,p,q]of bones){bone.position.copy(p);bone.quaternion.copy(q);}}

function aim(root,bone,child,target){
 root.updateMatrixWorld(true);
 const start=position(bone),from=position(child).sub(start).normalize(),to=target.clone().sub(start).normalize();
 const world=bone.getWorldQuaternion(new T.Quaternion());
 world.premultiply(new T.Quaternion().setFromUnitVectors(from,to));
 bone.quaternion.copy(bone.parent.getWorldQuaternion(new T.Quaternion()).invert().multiply(world));
 root.updateMatrixWorld(true);
}

function twoBone(root,a,b,c,target,pole){
 const start=position(a),middle=position(b),end=position(c),l1=middle.distanceTo(start),l2=end.distanceTo(middle);
 const direction=target.clone().sub(start),distance=Math.min(direction.length(),l1+l2-.001);direction.normalize();
 const along=(l1*l1-l2*l2+distance*distance)/(2*distance),height=Math.sqrt(Math.max(0,l1*l1-along*along));
 const bend=pole.clone().sub(start);bend.addScaledVector(direction,-bend.dot(direction)).normalize();
 const elbow=start.clone().addScaledVector(direction,along).addScaledVector(bend,height);
 aim(root,a,b,elbow);aim(root,b,c,start.clone().addScaledVector(direction,distance));
}

function disposeRoot(root){
 root.traverse(o=>{if(!o.isMesh)return;o.geometry?.dispose();const materials=Array.isArray(o.material)?o.material:[o.material];for(const material of materials){for(const value of Object.values(material))if(value?.isTexture)value.dispose();material.dispose();}});
}

async function loadRested(url,height){
 const gltf=await loader.loadAsync(url),root=gltf.scene;soften(root);
 const mixer=new T.AnimationMixer(root),clip=gltf.animations.find(a=>a.name==='restpose');
 if(clip){const action=mixer.clipAction(clip);action.play();mixer.setTime(0);root.updateMatrixWorld(true);action.stop();}
 normalize(root,height);return {root,bones:captureBones(root)};
}

export async function createT10Archer(){
 const {root,bones}=await loadRested(archerUrl,1.7),bone=name=>root.getObjectByName(name);
 const shoulderY=(position(bone('LeftArm')).y+position(bone('RightArm')).y)/2;
 return {root,animate(time,reduced=false){
  const facing=root.rotation.y;root.rotation.y=0;
  restoreBones(bones);root.updateMatrixWorld(true);
  const breath=reduced?0:Math.sin(time*Math.PI*.5);
  bone('Spine').rotateX(.008*breath);bone('Head').rotateZ(.012+.004*breath);root.updateMatrixWorld(true);
  twoBone(root,bone('LeftArm'),bone('LeftForeArm'),bone('LeftHand'),vector(.29,shoulderY-.34+.003*breath,.09),vector(.36,shoulderY-.18,-.04));
  twoBone(root,bone('RightArm'),bone('RightForeArm'),bone('RightHand'),vector(-.28,shoulderY-.35+.003*breath,.10),vector(-.36,shoulderY-.18,-.04));
  root.rotation.y=facing;root.updateMatrixWorld(true);
 },dispose(){disposeRoot(root);}};
}

export async function createT10Cavalry(){
 const [{root:rider,bones:riderBones},{root:horse,bones:horseBones}]=await Promise.all([loadRested(riderUrl,.92),loadRested(horseUrl,1.22)]);
 const root=new T.Group();root.add(horse,rider);
 const hip=rider.getObjectByName('Hips');rider.updateMatrixWorld(true);const hipPosition=position(hip);
 rider.position.add(vector(-hipPosition.x,1.00-hipPosition.y,-.06-hipPosition.z));rider.updateMatrixWorld(true);
 const bone=name=>rider.getObjectByName(name),horseBone=name=>horse.getObjectByName(name);
 const riderBase=rider.position.clone();
 return {root,animate(time,reduced=false){
  const facing=root.rotation.y;root.rotation.y=0;
  restoreBones(horseBones);restoreBones(riderBones);rider.position.copy(riderBase);root.updateMatrixWorld(true);
  const breath=reduced?0:Math.sin(time*1.35),sway=reduced?0:Math.sin(time*.72);
  const stirrupY=.69;
  twoBone(root,bone('LeftUpLeg'),bone('LeftLeg'),bone('LeftFoot'),vector(.28,stirrupY,.04),vector(.47,.82,.34));
  twoBone(root,bone('RightUpLeg'),bone('RightLeg'),bone('RightFoot'),vector(-.28,stirrupY,.04),vector(-.47,.82,.34));
  const shoulderY=(position(bone('LeftArm')).y+position(bone('RightArm')).y)/2;
  twoBone(root,bone('LeftArm'),bone('LeftForeArm'),bone('LeftHand'),vector(.16,shoulderY-.34,.20),vector(.34,shoulderY-.18,.04));
  twoBone(root,bone('RightArm'),bone('RightForeArm'),bone('RightHand'),vector(-.16,shoulderY-.34,.20),vector(-.34,shoulderY-.18,.04));
  bone('Spine').rotateX(-.035+.006*breath);bone('Head').rotateZ(.006*sway);
  const head=horseBone('head'),tail=horseBone('tail1');if(head)head.rotateX(.018*breath);if(tail)tail.rotateZ(.035*sway);
  rider.position.y=riderBase.y+(reduced?0:.006*breath);root.rotation.y=facing;root.updateMatrixWorld(true);
 },dispose(){disposeRoot(rider);disposeRoot(horse);}};
}

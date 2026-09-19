import * as T from './vendor/three.module.js';
import {storybookColors as C,createStorybookMaterial} from './storybook-style.js';
import {sculptInfantry} from './infantry-sculpt.js?v=infantry2';

// One coordinate system, skeleton and clip library for every human role.
// Geometry is shared between instances; each actor owns only its pose/skeleton.
const joints=[
 ['hips',null,0,.53,0],['chest','hips',0,.88,0],['head','chest',0,1.36,0],
 ['upperL','chest',-.32,1.02,0],['lowerL','upperL',-.39,.79,.02],['handL','lowerL',-.39,.62,.07],
 ['upperR','chest',.32,1.02,0],['lowerR','upperR',.39,.79,.02],['handR','lowerR',.39,.62,.07],
 ['thighL','hips',-.16,.51,0],['shinL','thighL',-.16,.29,0],['footL','shinL',-.16,.1,.06],
 ['thighR','hips',.16,.51,0],['shinR','thighR',.16,.29,0],['footR','shinR',.16,.1,.06],
 ['scarf','chest',-.18,1.08,-.18]
];
const index=Object.fromEntries(joints.map((j,i)=>[j[0],i]));
const cache=new Map();
const surface=createStorybookMaterial('#ffffff',{vertexColors:true});
const infantrySurface=createStorybookMaterial('#ffffff',{vertexColors:true,bumpScale:0,bumpMap:null,map:null});
const ink=new T.MeshBasicMaterial({color:C.dark,side:T.BackSide});
ink.onBeforeCompile=s=>{s.vertexShader='attribute float inkAmount;\n'+s.vertexShader;s.vertexShader=s.vertexShader.replace('#include <begin_vertex>','vec3 transformed = position + normal * 0.009 * inkAmount;');};
ink.customProgramCacheKey=()=> 'character-ink-1';

function buildGeometry(role,cloth,tool){
 const positions=[],normals=[],colors=[],skinIndices=[],weights=[],uvs=[],inkAmounts=[];
 function add(geometry,bone,color,x,y,z,sx=1,sy=1,sz=1,rz=0){
  const transformed=geometry.index?geometry.toNonIndexed():geometry.clone();geometry.dispose();
  transformed.scale(sx,sy,sz);transformed.rotateZ(rz);transformed.translate(x,y,z);
  const p=transformed.attributes.position,n=transformed.attributes.normal,c=new T.Color(color);
  for(let i=0;i<p.count;i++){positions.push(p.getX(i),p.getY(i),p.getZ(i));normals.push(n.getX(i),n.getY(i),n.getZ(i));colors.push(c.r,c.g,c.b);skinIndices.push(index[bone],0,0,0);weights.push(1,0,0,0);}
  transformed.computeBoundingBox();const bounds=transformed.boundingBox,size=bounds.getSize(new T.Vector3());
  const faceDetail=role==='infantry'&&bone==='head'&&bounds.min.z>.20&&size.x<.23&&size.y<.23;
  const uv=transformed.attributes.uv;for(let i=0;i<p.count;i++){uvs.push(uv?.getX(i)||0,uv?.getY(i)||0);inkAmounts.push(faceDetail||bone==='head'&&y<1.6&&z>.25?0:1);}
  transformed.dispose();
 }
 const ball=(bone,c,x,y,z,w,h,d,rz=0)=>add(new T.SphereGeometry(1,w>.5?16:12,h>.5?10:8),bone,c,x,y,z,w/2,h/2,d/2,rz);
 const band=(bone,c,x,y,z,top,bottom,h)=>add(new T.CylinderGeometry(top,bottom,h,10),bone,c,x,y,z);
 function shape(bone,c,points,z,depth){const s=new T.Shape();points.forEach(([x,y],i)=>i?s.lineTo(x,y):s.moveTo(x,y));s.closePath();add(new T.ExtrudeGeometry(s,{depth,bevelEnabled:true,bevelSize:.015,bevelThickness:.015,bevelSegments:1,steps:1}),bone,c,0,0,z);}
 const military=role==='infantry'||role==='guard',skin='#edbd95',hair=military?C.trim:C.wood;
 if(role==='infantry')sculptInfantry({add,ball,band,shape},cloth);
 else {
 ball('chest',cloth,0,.85,0,.59,.5,.38);
 band('hips',cloth,0,.57,0,.23,.31,.25);
 band('hips',C.wood,0,.65,0,.251,.251,.09);
 ball('hips',C.gold,0,.65,.249,.135,.11,.055);
 ball('hips',C.wood,0,.65,.278,.066,.054,.018);
 for(const [side,suffix] of [[-1,'L'],[1,'R']]){
  ball('thigh'+suffix,C.dark,side*.16,.4,0,.2,.27,.22);
  ball('shin'+suffix,C.wood,side*.16,.23,0,.225,.28,.24);
  band('shin'+suffix,C.timber,side*.16,.31,0,.129,.12,.095);
  ball('foot'+suffix,C.dark,side*.16,.06,.09,.28,.1,.38);
  ball('foot'+suffix,C.wood,side*.16,.12,.1,.27,.18,.37);
  ball('upper'+suffix,cloth,side*.35,.92,0,.235,.28,.25,-side*.22);
  ball('lower'+suffix,military?C.wood:skin,side*.39,.74,.035,.17,.22,.19);
  ball('hand'+suffix,military?C.wood:skin,side*.39,.61,.08,.21,.21,.2);
  ball('hand'+suffix,military?C.timber:skin,side*.31,.63,.15,.09,.1,.09);
  if(military)ball('upper'+suffix,C.stone,side*.33,1.04,0,.27,.17,.29);
 }
 ball('head',skin,0,1.43,.015,.78,.7,.63);
 for(const side of [-1,1]){
  shape('head',skin,[[side*.31,1.48],[side*.51,1.49],[side*.41,1.32],[side*.32,1.34]],-.02,.12);
  ball('head',C.dark,side*.145,1.45,.31,.072,.128,.025);
  ball('head',C.trim,side*.135,1.477,.325,.018,.025,.008);
  ball('head',hair,side*.145,1.555,.293,.15,.037,.027,side*.18);
  ball('head','#de956f',side*.235,1.35,.279,.091,.04,.016);
 }
 ball('head',skin,0,1.38,.33,.075,.076,.06);
 ball('head',C.wood,0,1.284,.28,.078,.019,.016);
 add(new T.SphereGeometry(1,12,8,0,Math.PI*2,0,Math.PI*.57),'head',hair,0,1.49,-.03,.407,.38,.343);
 for(const [x,y,w,h,r] of [[-.29,1.62,.22,.32,-.65],[-.12,1.71,.24,.36,-.35],[.09,1.68,.24,.39,.35],[.29,1.59,.19,.28,.4]])ball('head',hair,x,y,.22,w,h,.22,r);
 ball('head',hair,-.08,1.86,-.025,.21,.23,.19,-.55);
 if(military){
  band('chest',C.orange,0,1.075,0,.22,.26,.12);
  ball('chest',C.orange,0,1.035,.14,.44,.17,.21,.08);
  shape('scarf',C.orange,[[-.17,1.07],[-.28,.72],[-.46,.66],[-.37,.86],[-.38,1.08]],-.26,.04);
  // Sword follows the hand, shield follows the other hand, never the torso.
  band('handR',C.wood,.43,.65,.15,.045,.045,.29);
  ball('handR',C.gold,.43,.8,.15,.29,.07,.11);
  shape('handR',C.gold,[[.34,.84],[.35,1.19],[.43,1.35],[.52,1.19],[.52,.84]],.12,.055);
  shape('handR',C.glow,[[.43,.86],[.43,1.3],[.49,1.18],[.49,.86]],.181,.008);
  ball('handL',C.gold,-.46,.7,.245,.48,.57,.095);
  ball('handL',C.blue,-.46,.7,.294,.404,.49,.043);
  shape('handL',C.trim,[[-.46,.9],[-.4,.72],[-.46,.5],[-.52,.72]],.32,.01);
  ball('handL',C.gold,-.46,.7,.349,.12,.12,.054);
 }
 if(role==='guard'){
  add(new T.SphereGeometry(1,12,7,0,Math.PI*2,0,Math.PI/2),'head',C.stone,0,1.57,-.025,.426,.32,.36);
  band('head',C.blue,0,1.59,-.025,.43,.43,.055);
  ball('head',C.blue,0,1.88,-.06,.11,.22,.27);
 }
 if(role==='worker'){
  band('head',C.wheat,0,1.72,-.02,.51,.51,.048);
  band('head',C.wheat,0,1.82,-.02,.19,.31,.19);
  band('head',C.wood,0,1.745,-.02,.293,.31,.058);
  shape('chest',C.timber,[[-.19,1.0],[.19,1.0],[.24,.47],[-.24,.47]],.201,.025);
  if(tool==='carry')ball('handR',C.timber,.26,.62,.3,.4,.3,.35);
  else{
   band('handR',C.wood,.4,.6,.17,.025,.025,.6);
   if(tool==='rake'){
    ball('handR',C.dark,.4,.33,.17,.36,.06,.1);
    for(const x of [.27,.36,.45,.54])ball('handR',C.dark,x,.29,.17,.025,.13,.04);
   }else ball('handR',C.dark,.4,.92,.17,.29,.15,.17);
  }
 }
 }
 const geometry=new T.BufferGeometry();
 geometry.setAttribute('position',new T.Float32BufferAttribute(positions,3));geometry.setAttribute('normal',new T.Float32BufferAttribute(normals,3));
 geometry.setAttribute('inkAmount',new T.Float32BufferAttribute(inkAmounts,1));geometry.setAttribute('uv',new T.Float32BufferAttribute(uvs,2));geometry.setAttribute('color',new T.Float32BufferAttribute(colors,3));geometry.setAttribute('skinIndex',new T.Uint16BufferAttribute(skinIndices,4));geometry.setAttribute('skinWeight',new T.Float32BufferAttribute(weights,4));
 // Weld identical vertices (including joint, UV and ink boundary) for mobile memory.
 const attributes=Object.entries(geometry.attributes),unique=new Map(),indices=[],values=Object.fromEntries(attributes.map(([name])=>[name,[]]));
 for(let i=0;i<geometry.attributes.position.count;i++){
  const signature=attributes.flatMap(([,a])=>Array.from(a.array.subarray(i*a.itemSize,(i+1)*a.itemSize))).join(',');
  let vertex=unique.get(signature);if(vertex===undefined){vertex=unique.size;unique.set(signature,vertex);for(const [name,a] of attributes)for(let n=0;n<a.itemSize;n++)values[name].push(a.array[i*a.itemSize+n]);}indices.push(vertex);
 }
 for(const [name,a] of attributes)geometry.setAttribute(name,new T.BufferAttribute(new a.array.constructor(values[name]),a.itemSize));
 geometry.setIndex(indices);geometry.computeBoundingSphere();return geometry;
}

function clip(name,duration,poses){
 const tracks=[];
 for(const [bone,axis,values] of poses){const data=[];for(const value of values){const e=new T.Euler();e[axis]=value;data.push(...new T.Quaternion().setFromEuler(e).toArray());}tracks.push(new T.QuaternionKeyframeTrack(bone+'.quaternion',values.map((_,i)=>i*duration/(values.length-1)),data));}
 return new T.AnimationClip(name,duration,tracks);
}
export const characterClips=[
 clip('idle',3,[['chest','x',[0,.025,0,-.015,0]],['head','y',[0,.09,0,-.06,0]],['scarf','x',[0,.09,0,-.06,0]]]),
 clip('walk',.85,[['thighL','x',[.48,0,-.48,0,.48]],['thighR','x',[-.48,0,.48,0,-.48]],['shinL','x',[0,.42,0,0,0]],['shinR','x',[0,0,0,.42,0]],['upperL','x',[-.28,0,.28,0,-.28]],['upperR','x',[.28,0,-.28,0,.28]],['scarf','x',[.08,-.12,.08,-.12,.08]]]),
 clip('attack',1,[['upperR','x',[-.3,-1.8,-1.1,.2,-.3]],['lowerR','x',[0,-.55,-.35,0,0]],['chest','y',[0,.25,-.3,-.1,0]],['upperL','x',[-.2,-.6,-.6,-.3,-.2]]]),
 clip('work',1.6,[['upperR','x',[-.3,-1.3,-.8,-.3]],['lowerR','x',[-.1,-.55,-.3,-.1]],['upperL','x',[-.2,-.7,-.6,-.2]],['chest','x',[0,.12,.2,0]],['head','x',[0,.1,.15,0]]])
];
const infantryClips=characterClips.map(source=>{
 const c=source.clone();
 if(c.name==='idle'){
  const pose=clip('stance',3,[['upperR','z',[-.14,-.16,-.14]],['upperL','x',[-.18,-.21,-.18]],['lowerR','x',[-.09,-.12,-.09]]]);
  c.tracks.push(...pose.tracks);
  for(const track of c.tracks){const tilt=track.name==='head.quaternion'?.055:track.name==='chest.quaternion'?-.025:0;if(!tilt)continue;const q=new T.Quaternion().setFromEuler(new T.Euler(0,0,tilt));for(let i=0;i<track.values.length;i+=4){new T.Quaternion().fromArray(track.values,i).multiply(q).toArray(track.values,i);}}
 }
 return c;
});

export function createCharacter({role='infantry',cloth=C.blue,phase=0,tool='hammer'}={}){
 if(!['infantry','guard','worker','citizen'].includes(role))throw new Error('Unknown character role: '+role);
 const key=role+cloth+tool;if(!cache.has(key))cache.set(key,buildGeometry(role,cloth,tool));
 const root=new T.Group();root.name='character-'+role;
 const bones=joints.map(([name])=>{const b=new T.Bone();b.name=name;return b;});
 joints.forEach(([,parent,x,y,z],i)=>{const p=parent?joints[index[parent]]:null;bones[i].position.set(x-(p?.[2]||0),y-(p?.[3]||0),z-(p?.[4]||0));(parent?bones[index[parent]]:root).add(bones[i]);});
 root.updateMatrixWorld(true);const skeleton=new T.Skeleton(bones);
 const mesh=new T.SkinnedMesh(cache.get(key),role==='infantry'?infantrySurface:surface);mesh.name='body';mesh.castShadow=true;mesh.receiveShadow=true;root.add(mesh);mesh.bind(skeleton);
 const outline=new T.SkinnedMesh(mesh.geometry,ink);outline.name='outline';outline.raycast=()=>{};root.add(outline);outline.bind(skeleton);outline.userData.storybookInk=true;
 // Animated bounds stay conservatively large; feet/gear cannot be culled mid-step.
 for(const m of [mesh,outline]){m.boundingSphere=new T.Sphere(new T.Vector3(0,1,0),1.7);m.boundingBox=new T.Box3(new T.Vector3(-1,-.25,-1),new T.Vector3(1,2.2,1));}
 const clips=role==='infantry'?infantryClips:characterClips;
 const mixer=new T.AnimationMixer(root),actions=Object.fromEntries(clips.map(c=>[c.name,mixer.clipAction(c)]));
 let current=null,last=null;
 function setAnimation(name,immediate=false){if(!actions[name])throw new Error('Unknown animation '+name);if(current===name)return;const previous=current;current=name;if(immediate)mixer.stopAllAction();actions[name].reset().play();actions[name].time=phase%actions[name].getClip().duration;if(previous&&!immediate){actions[previous].fadeOut(.16);actions[name].fadeIn(.16);}if(immediate)mixer.update(0);}
 setAnimation('idle');mixer.update(phase%3);
 return {root,skeleton,mesh,bones:Object.fromEntries(bones.map(b=>[b.name,b])),clips,setAnimation,
  animate(time,reducedMotion=false){if(reducedMotion){mixer.setTime(0);last=time;return;}const dt=last===null?0:Math.max(0,Math.min(.1,time-last));last=time;mixer.update(dt);},
  dispose(){mixer.stopAllAction();mixer.uncacheRoot(root);skeleton.dispose();root.removeFromParent();},
  // Geometry and materials live in this module's shared cache, not per actor.
  get animation(){return current;}
 };
}

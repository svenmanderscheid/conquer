import * as T from './vendor/three.module.js';

const ease=p=>{p=T.MathUtils.clamp(p,0,1);return p*p*(3-2*p);};
const guardAmount=cycle=>cycle<2.6?0:cycle<3.25?ease((cycle-2.6)/.65):cycle<4.35?1:cycle<5.25?1-ease((cycle-4.35)/.9):0;

// A calm guard idle for the training portrait. It deliberately uses small
// local rotations so the oversized sword and shield keep a clean silhouette.
export function createKnightShowcaseMotion(skeleton){
 const names=['Spine01','Spine02','Head','LeftArm','LeftForeArm','LeftHand','RightArm','RightForeArm','RightHand'];
 const bones=Object.fromEntries(names.map(name=>[name,skeleton.bones.find(bone=>bone.name===name)]));
 for(const name of names)if(!bones[name])throw new Error(`Conquer knight is missing ${name}`);
 const hips=skeleton.bones.find(bone=>bone.name==='Hips');
 if(!hips)throw new Error('Conquer knight is missing Hips');
 const rest=Object.fromEntries(names.map(name=>[name,bones[name].quaternion.clone()]));
 const hipsRest=hips.position.clone();
 const rotate=(name,x,y,z)=>bones[name].quaternion.copy(rest[name]).multiply(new T.Quaternion().setFromEuler(new T.Euler(x,y,z,'XYZ')));

 return (time,reduced=false)=>{
  for(const name of names)bones[name].quaternion.copy(rest[name]);
  hips.position.copy(hipsRest);
  if(reduced)return;
  const breath=Math.sin(time*1.65),look=Math.sin(time*.48),brace=guardAmount(time%6.8);
  hips.position.y+=breath*.006-brace*.022;
  rotate('Spine01',breath*.012-brace*.025,brace*.035,look*.009);
  rotate('Spine02',breath*.018-brace*.035,look*.012+brace*.055,-look*.01);
  rotate('Head',-breath*.01+brace*.018,-look*.045-brace*.06,look*.012);
  // Readable infantry guard drill: settle, brace shield, ready sword, hold, release.
  rotate('LeftArm',-.15*brace,.03*brace,.23*brace);
  rotate('LeftForeArm',-.22*brace,.02*brace,.32*brace);
  rotate('LeftHand',.02*brace,.05*brace,.1*brace);
  rotate('RightArm',.14*brace,-.12*brace,-.26*brace);
  rotate('RightForeArm',.24*brace,.04*brace,-.36*brace);
  rotate('RightHand',.02*brace,-.1*brace,-.15*brace);
 };
}

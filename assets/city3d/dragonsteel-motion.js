import * as THREE from './vendor/three.module.js';

// Deterministic poses: every frame starts from bind transforms, never from
// the previous animation frame. Reduced motion uses the same ready stance.
export function createDragonsteelMotion(root, clips) {
  const bones = [];
  root.traverse(b => {if(b.isBone) bones.push({bone:b, position:b.position.clone(), rotation:b.quaternion.clone()});});
  const byName = new Map(bones.map(b=>[b.bone.name,b.bone]));
  const mixer = new THREE.AnimationMixer(root);
  const clip = (clips.find(c=>c.name==='retarget_clip') || clips[0])?.clone();
  if(clip) for(const track of clip.tracks) {
    const width=track.getValueSize(), n=track.times.length;
    if(n<2)continue;
    // Close the source cycle without a hard seam.
    const first=Array.from(track.values.slice(0,width));
    for(let i=0;i<n;i++) {
      const tail=(track.times[i]/clip.duration-.85)/.15;
      if(tail<=0)continue;
      const weight=THREE.MathUtils.smoothstep(tail,0,1);
      if(width===4 && track.name.endsWith('.quaternion')) {
        const q=new THREE.Quaternion().fromArray(track.values,i*width);
        q.slerp(new THREE.Quaternion().fromArray(first),weight).toArray(track.values,i*width);
      } else for(let c=0;c<width;c++) track.values[i*width+c]=THREE.MathUtils.lerp(track.values[i*width+c],first[c],weight);
    }
  }
  const action=clip?mixer.clipAction(clip):null;
  const q=new THREE.Quaternion(), e=new THREE.Euler();
  const turn=(name,x=0,y=0,z=0)=>{const bone=byName.get(name);if(bone)bone.quaternion.multiply(q.setFromEuler(e.set(x,y,z)));};
  let running=false;
  return (time,reduced=false,mode='idle') => {
    if(mode==='run'&&!reduced&&action) {
      if(!running){action.reset().play();running=true;}
      mixer.setTime(time % clip.duration);
      return;
    }
    if(running){action.stop();running=false;}
    bones.forEach(({bone,position,rotation})=>{bone.position.copy(position);bone.quaternion.copy(rotation);});
    const t=reduced?0:time;
    const breath=Math.sin(t*1.8);
    // Bring the upper arms out of the rigging A-pose. Unequal elbow angles
    // reflect the different weight of sword and shield; feet remain planted.
    turn('RightArm',.06,0,.38);
    turn('LeftArm',-.04,0,-.32);
    turn('RightForeArm',-.2,0,.02);
    turn('LeftForeArm',-.32,0,-.04);
    turn('Spine',breath*.008,-.025+Math.sin(t*.38)*.008,.018);
    turn('Head',-.025+Math.sin(t*.9)*.007,.025+Math.sin(t*.32)*.025,-.018);
    turn('RightShoulder',0,0,breath*.006);
    turn('LeftShoulder',0,0,-breath*.006);
    if(mode==='combat'&&!reduced) {
      const phase=(t%3.6)/3.6;
      const ready=Math.sin(Math.PI*THREE.MathUtils.clamp(phase/.65,0,1))**2;
      const strike=Math.sin(Math.PI*THREE.MathUtils.clamp((phase-.3)/.35,0,1))**2;
      turn('RightArm',-.5*ready+.7*strike,-.22*strike,-.25*ready);
      turn('RightForeArm',-.55*ready+.35*strike,0,0);
      turn('LeftForeArm',-.32*ready,0,0);
      turn('Spine',.035*strike,-.12*ready+.2*strike,0);
    } else if(!reduced) {
      const phase=(t%12)/12;
      const guard=Math.sin(Math.PI*THREE.MathUtils.clamp((phase-.6)/.28,0,1))**2;
      turn('LeftForeArm',-.07*guard,0,0);
      turn('RightForeArm',-.035*guard,0,0);
    }
  };
}

import * as T from './vendor/three.module.js';

// Runtime finishing pass for the approved Meshy knight. Call after mixer.update().
export function createKnightMotionProfile(root, skeleton) {
  root.updateMatrixWorld(true);
  const rootInverse = root.getWorldQuaternion(new T.Quaternion()).invert();
  const feet = ['LeftFoot', 'RightFoot'].map(name => {
    const bone = skeleton.bones.find(candidate => candidate.name === name);
    if (!bone) throw new Error(`Conquer knight is missing ${name}`);
    const rest = rootInverse.clone().multiply(bone.getWorldQuaternion(new T.Quaternion()));
    const toe = bone.children.find(candidate => /ToeBase/.test(candidate.name));
    const forward = toe
      ? toe.getWorldPosition(new T.Vector3()).sub(bone.getWorldPosition(new T.Vector3())).applyQuaternion(rootInverse)
      : new T.Vector3(0, 0, 1);
    const inward = T.MathUtils.clamp(-Math.atan2(forward.x, forward.z) * .8, -Math.PI / 15, Math.PI / 15);
    const alignedRest = new T.Quaternion().setFromAxisAngle(new T.Vector3(0, 1, 0), inward).multiply(rest);
    return {bone, rest, alignedRest, toe, toeRest: toe?.quaternion.clone()};
  });
  const strengths = {Shoulder: .72, Arm: .78, ForeArm: .86, Hand: .92};
  const arms = [];
  for (const side of ['Left', 'Right']) for (const part of Object.keys(strengths)) {
    const bone = skeleton.bones.find(candidate => candidate.name === side + part);
    if (!bone) throw new Error(`Conquer knight is missing ${side + part}`);
    arms.push({bone, rest: bone.quaternion.clone(), strength: strengths[part]});
  }

  return function applyKnightMotionProfile() {
    for (const {bone, rest, strength} of arms) bone.quaternion.slerp(rest, strength);
    root.updateMatrixWorld(true);
    const rootQ = root.getWorldQuaternion(new T.Quaternion());
    for (const {bone, rest, alignedRest, toe, toeRest} of feet) {
      const current = rootQ.clone().invert().multiply(bone.getWorldQuaternion(new T.Quaternion()));
      const delta = current.multiply(rest.clone().invert());
      const angles = new T.Euler().setFromQuaternion(delta, 'YXZ');
      const pitch = T.MathUtils.clamp(angles.x * (angles.x < 0 ? 1.55 : 1.12), -.52, .47);
      const target = rootQ.clone()
        .multiply(new T.Quaternion().setFromAxisAngle(new T.Vector3(1, 0, 0), pitch))
        .multiply(alignedRest);
      bone.quaternion.copy(bone.parent.getWorldQuaternion(new T.Quaternion()).invert().multiply(target));
      if (toe) toe.quaternion.copy(toeRest);
      bone.updateMatrixWorld(true);
    }
  };
}

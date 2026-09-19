import * as THREE from './vendor/three.module.js';
import {GLTFLoader} from './vendor/loaders/GLTFLoader.js';
import {createDragonsteelMotion} from './dragonsteel-motion.js';
export async function createDragonsteelKnight() {
const loader = new GLTFLoader();
const asset = name => new URL('../art/characters/infantry-t10-dragonsteel-' + name + (name==='running'?'':'-mobile') + '.glb', import.meta.url).href;
const [bodyFile, motionFile, shieldFile] = await Promise.all(['rigged','running','shield'].map(name => loader.loadAsync(asset(name))));
const knight = bodyFile.scene;

knight.traverse((part) => {
  if (!part.isMesh) return;
  part.castShadow = true;
  part.receiveShadow = true;
  const materials = Array.isArray(part.material) ? part.material : [part.material];
  for (const material of materials) {
    if (material.map) material.map.colorSpace = THREE.SRGBColorSpace;
    material.roughness = Math.max(material.roughness ?? .8, .58);
    material.metalness = Math.min(material.metalness ?? .15, .42);
  }
});

function prepareEquipment(source, scale) {
  const object = source.scene;
  object.scale.setScalar(scale);
  object.traverse((part) => {
    if (!part.isMesh) return;
    part.castShadow = true;
    part.receiveShadow = true;
    const materials = Array.isArray(part.material) ? part.material : [part.material];
    for (const material of materials) {
      if (material.map) material.map.colorSpace = THREE.SRGBColorSpace;
      material.roughness = Math.max(material.roughness ?? .7, .5);
      material.metalness = Math.min(material.metalness ?? .3, .5);
    }
  });
  return object;
}

function outlined(mesh, color = 0x422a22, scale = 1.055) {
  const outline = mesh.clone();
  outline.material = new THREE.MeshBasicMaterial({ color, side: THREE.BackSide });
  outline.scale.multiplyScalar(scale);
  outline.renderOrder = -1;
  mesh.add(outline);
  return mesh;
}

function createStorybookSword() {
  const sword = new THREE.Group();
  sword.name = 'T10DragonsteelSword';
  const silver = new THREE.MeshStandardMaterial({ color: 0xf4f0df, roughness: .5, metalness: .38 });
  const shade = new THREE.MeshStandardMaterial({ color: 0xaeb9c8, roughness: .62, metalness: .28 });
  const gold = new THREE.MeshStandardMaterial({ color: 0xd6a344, roughness: .55, metalness: .34 });
  const leather = new THREE.MeshStandardMaterial({ color: 0x573528, roughness: .9, metalness: 0 });
  const crystal = new THREE.MeshStandardMaterial({ color: 0x168dff, emissive: 0x0757c0, emissiveIntensity: .5, roughness: .5, metalness: .08 });

  const bladeShape = new THREE.Shape();
  bladeShape.moveTo(-.075, .18);
  bladeShape.lineTo(-.09, .67);
  bladeShape.lineTo(0, .84);
  bladeShape.lineTo(.09, .67);
  bladeShape.lineTo(.075, .18);
  bladeShape.closePath();
  const blade = outlined(new THREE.Mesh(bladeShapeGeometry(bladeShape, .042), silver), 0x422a22, 1.018);
  blade.name = 'DragonsteelBlade';
  blade.userData.noReceive = true;
  sword.add(blade);

  const ridgeShape = new THREE.Shape();
  ridgeShape.moveTo(-.018, .24);
  ridgeShape.lineTo(0, .75);
  ridgeShape.lineTo(.018, .24);
  ridgeShape.closePath();
  const ridge = new THREE.Mesh(bladeShapeGeometry(ridgeShape, .048), shade);
  ridge.userData.noReceive = true;
  sword.add(ridge);

  const runeShape = new THREE.Shape();
  runeShape.moveTo(0, .24);
  runeShape.lineTo(-.027, .61);
  runeShape.lineTo(0, .74);
  runeShape.lineTo(.027, .61);
  runeShape.closePath();
  const rune = new THREE.Mesh(bladeShapeGeometry(runeShape, .054), crystal);
  rune.userData.noReceive = true;
  sword.add(rune);

  const guardShape = new THREE.Shape();
  guardShape.moveTo(-.17, .23);
  guardShape.lineTo(-.15, .13);
  guardShape.lineTo(0, .105);
  guardShape.lineTo(.15, .13);
  guardShape.lineTo(.17, .23);
  guardShape.lineTo(.12, .21);
  guardShape.lineTo(.105, .175);
  guardShape.lineTo(0, .155);
  guardShape.lineTo(-.105, .175);
  guardShape.lineTo(-.12, .21);
  guardShape.closePath();
  const guard = outlined(new THREE.Mesh(bladeShapeGeometry(guardShape, .045), gold));
  sword.add(guard);

  for (const side of [-1, 1]) {
    const cap = outlined(new THREE.Mesh(new THREE.OctahedronGeometry(.052, 0), gold), 0x422a22, 1.07);
    cap.position.set(side * .148, .205, 0);
    cap.scale.set(.42, .55, .52);
    sword.add(cap);
  }

  const grip = outlined(new THREE.Mesh(new THREE.CylinderGeometry(.035, .04, .24, 10), leather), 0x422a22, 1.08);
  grip.position.y = .005;
  sword.add(grip);
  for (let y = -.075; y <= .085; y += .055) {
    const wrap = new THREE.Mesh(new THREE.TorusGeometry(.041, .008, 5, 10), gold);
    wrap.rotation.x = Math.PI / 2;
    wrap.position.y = y;
    sword.add(wrap);
  }

  const pommel = outlined(new THREE.Mesh(new THREE.OctahedronGeometry(.075, 0), gold), 0x422a22, 1.08);
  pommel.position.y = -.16;
  sword.add(pommel);
  const gem = new THREE.Mesh(new THREE.OctahedronGeometry(.044, 0), crystal);
  gem.position.set(0, .175, .055);
  gem.scale.set(1, 1.28, .55);
  sword.add(gem);

  const edgeLight = new THREE.MeshStandardMaterial({ color: 0xf1f4ed, roughness: .6, metalness: .2 });
  const edgeShade = new THREE.MeshStandardMaterial({ color: 0x8d9eb5, roughness: .65, metalness: .2 });
  const ice = new THREE.MeshStandardMaterial({ color: 0x82e5ff, emissive: 0x157bac, emissiveIntensity: .3, roughness: .5 });
  const sapphire = new THREE.MeshStandardMaterial({ color: 0x2455c8, emissive: 0x102f86, emissiveIntensity: .3, roughness: .55 });
  const goldShade = new THREE.MeshStandardMaterial({ color: 0x8d5e2e, roughness: .8 });
  const goldLight = new THREE.MeshStandardMaterial({ color: 0xf0cf7b, roughness: .65, metalness: .18 });

  function facet(points, material, face, z) {
    const shape = new THREE.Shape();
    points.forEach(([x, y], i) => i ? shape.lineTo(x, y) : shape.moveTo(x, y));
    shape.closePath();
    const panel = new THREE.Mesh(new THREE.ShapeGeometry(shape), material);
    panel.position.z = face * z;
    if (face < 0) panel.rotation.y = Math.PI;
    panel.userData.noReceive = true;
    sword.add(panel);
  }
  function inlay(points, material, face, radius = .003) {
    const curve = new THREE.CatmullRomCurve3(points.map(([x, y]) => new THREE.Vector3(x, y, face * .041)));
    const line = new THREE.Mesh(new THREE.TubeGeometry(curve, 12, radius, 5, false), material);
    line.userData.noReceive = true;
    sword.add(line);
  }
  for (const face of [-1, 1]) {
    // Broad bevels and split crystal facets remain readable at game scale.
    facet([[-.075,.205],[-.09,.67],[0,.835],[-.058,.658],[-.047,.225]], edgeLight, face, .0365);
    facet([[.075,.205],[.09,.67],[0,.835],[.058,.658],[.047,.225]], edgeShade, face, .0365);
    facet([[0,.255],[-.027,.61],[0,.735],[0,.56]], ice, face, .041);
    facet([[0,.255],[0,.56],[.027,.61]], sapphire, face, .0415);
    facet([[0,.56],[0,.735],[.027,.61]], crystal, face, .0415);
    facet([[-.01,.62],[0,.711],[.008,.635],[0,.66]], edgeLight, face, .042);
    for (const side of [-1, 1]) {
      inlay([[side*.035,.141],[side*.087,.145],[side*.13,.17],[side*.144,.213]], goldShade, face, .004);
      inlay([[side*.042,.154],[side*.089,.159],[side*.119,.177]], goldLight, face, .0025);
      const rivet = new THREE.Mesh(new THREE.SphereGeometry(.009, 8, 6), goldLight);
      rivet.scale.z = .5;
      rivet.position.set(side*.105, .15, face*.043);
      sword.add(rivet);
    }
    const setting = new THREE.Mesh(new THREE.OctahedronGeometry(.055), goldLight);
    setting.position.set(0, .175, face*.05);
    setting.scale.set(1, 1.3, .36);
    sword.add(setting);
    const centreGem = gem.clone();
    centreGem.position.z = face*.067;
    sword.add(centreGem);
    const pommelGem = new THREE.Mesh(new THREE.OctahedronGeometry(.038), crystal);
    pommelGem.position.set(0, -.16, face*.049);
    pommelGem.scale.set(.72, 1.15, .4);
    sword.add(pommelGem);
    // Small paired chevrons at the blade root echo the armour's gold motifs.
    inlay([[-.043,.267],[0,.224],[.043,.267]], gold, face, .0035);

    // Reference: bright forged cutting bevels frame a long, narrow sapphire.
    // Layer the blade shoulder and guard instead of adding unrelated curls.
    facet([[-.077,.235],[-.09,.67],[0,.835],[-.072,.665],[-.06,.245]], edgeLight, face, .0425);
    facet([[.077,.235],[.09,.67],[0,.835],[.072,.665],[.06,.245]], silver, face, .0425);
    facet([[-.049,.262],[-.057,.635],[0,.784],[-.039,.628],[-.035,.28]], shade, face, .043);
    facet([[.049,.262],[.057,.635],[0,.784],[.039,.628],[.035,.28]], edgeLight, face, .043);
    facet([[0,.27],[-.018,.594],[0,.71],[0,.578]], ice, face, .047);
    facet([[0,.27],[0,.578],[.018,.594]], sapphire, face, .047);
    facet([[0,.578],[0,.71],[.018,.594]], crystal, face, .047);
    facet([[-.006,.607],[0,.685],[.005,.611],[0,.634]], edgeLight, face, .048);

    // Raised silver ricasso, bordered with gold, joins blade and gemstone.
    facet([[-.072,.185],[-.06,.283],[0,.248],[.06,.283],[.072,.185],[0,.153]], goldShade, face, .049);
    facet([[-.061,.19],[-.049,.265],[0,.233],[.049,.265],[.061,.19],[0,.168]], goldLight, face, .051);
    facet([[-.045,.198],[-.039,.247],[0,.218],[.039,.247],[.045,.198],[0,.18]], silver, face, .053);

    for (const side of [-1, 1]) {
      const wing = [[side*.052,.154],[side*.136,.135],[side*.17,.23],[side*.13,.216],[side*.113,.18]];
      facet(wing, goldLight, face, .046);
      facet([[side*.075,.155],[side*.129,.149],[side*.15,.206],[side*.133,.195],[side*.12,.167]], gold, face, .048);
      facet([[side*.096,.153],[side*.124,.151],[side*.135,.18],[side*.119,.171]], goldShade, face, .049);
      const stud = new THREE.Mesh(new THREE.OctahedronGeometry(.011), silver);
      stud.position.set(side*.138, .207, face*.055);
      stud.scale.z = .45;
      sword.add(stud);
    }

    // Diamond bezel with separate gemstone planes, echoing the breastplate.
    facet([[0,.117],[-.055,.176],[0,.242],[.055,.176]], goldShade, face, .078);
    facet([[0,.125],[-.047,.176],[0,.233],[.047,.176]], goldLight, face, .080);
    facet([[0,.136],[-.034,.176],[0,.218]], ice, face, .082);
    facet([[0,.136],[0,.218],[.034,.176]], sapphire, face, .082);
    facet([[0,.16],[-.034,.176],[0,.218]], crystal, face, .083);
    facet([[0,.16],[0,.218],[.034,.176]], ice, face, .083);
  }
  sword.traverse((part) => {
    if (!part.isMesh) return;
    part.castShadow = true;
    part.receiveShadow = !part.userData.noReceive;
  });
  return sword;
}

function bladeShapeGeometry(shape, depth) {
  const geometry = new THREE.ExtrudeGeometry(shape, {
    depth,
    bevelEnabled: true,
    bevelSegments: 2,
    bevelSize: .012,
    bevelThickness: .012,
  });
  geometry.translate(0, 0, -depth / 2);
  return geometry;
}

const rightHand = knight.getObjectByName('RightHand');
const leftHand = knight.getObjectByName('LeftHand');
if (!rightHand || !leftHand) throw new Error('Handknochen wurden im Meshy-Rig nicht gefunden.');

knight.updateMatrixWorld(true);

function createPalmSocket(hand, distanceAlongPalm) {
  const socket = new THREE.Group();
  socket.name = `${hand.name}EquipmentSocket`;
  socket.position.set(0, distanceAlongPalm, 0);
  const bindWorldRotation = new THREE.Quaternion();
  hand.getWorldQuaternion(bindWorldRotation);
  socket.quaternion.copy(bindWorldRotation).invert();
  hand.add(socket);
  return socket;
}

// Each hand bone begins at the wrist. Halfway to Hand_End is the palm centre,
// so both props now pivot from the grip instead of orbiting beside the hand.
const swordSocket = createPalmSocket(rightHand, .075);
const shieldSocket = createPalmSocket(leftHand, .075);

// Replace both open hands with a matched pair of armoured gloves.
// Keep the source GLB intact; the replacement glove shares the hand transform.
knight.traverse((mesh) => {
  if (!mesh.isSkinnedMesh || !mesh.geometry.index) return;
  const geometry = mesh.geometry.clone();
  const position = geometry.attributes.position;
  const joints = geometry.attributes.skinIndex;
  const weights = geometry.attributes.skinWeight;
  const hands = [rightHand, leftHand].map((hand) => ({
    index: mesh.skeleton.bones.indexOf(hand),
    endIndex: mesh.skeleton.bones.findIndex((bone) => bone.name === `${hand.name}_End`),
    inverse: hand.matrixWorld.clone().invert().multiply(mesh.matrixWorld),
  }));
  const point = new THREE.Vector3();
  const removed = new Uint8Array(position.count);
  for (let i = 0; i < position.count; i++) {
    for (const hand of hands) {
      let influence = 0;
      for (let c = 0; c < 4; c++) {
        const joint = joints.getComponent(i, c);
        if (joint === hand.index || joint === hand.endIndex) influence += weights.getComponent(i, c);
      }
      point.fromBufferAttribute(position, i).applyMatrix4(hand.inverse);
      if (influence > .12 && point.y > .022) removed[i] = 1;
    }
  }
  const indices = [];
  for (let i = 0; i < geometry.index.count; i += 3) {
    const a = geometry.index.getX(i), b = geometry.index.getX(i + 1), c = geometry.index.getX(i + 2);
    if (!(removed[a] || removed[b] || removed[c])) indices.push(a, b, c);
  }
  geometry.setIndex(indices);
  mesh.geometry = geometry;
});

const sword = createStorybookSword();
sword.scale.setScalar(.68);
// The reference sword is authored around its grip, keeping the accepted
// palm attachment independent of blade shape and length.
const swordGripPivot = new THREE.Group();
swordGripPivot.name = 'RightHandSwordGrip';
swordGripPivot.position.set(0, 0, 0);
swordGripPivot.rotation.set(.95, -.08, .38);
sword.position.set(0, 0, 0);
swordGripPivot.add(sword);

// Meshy's rig has no individual finger bones and leaves this hand open. A
// compact armoured grip closes visually around the hilt and covers that gap.
const gripGauntlet = new THREE.Group();
gripGauntlet.name = 'ClosedSwordGrip';
const gripSilver = new THREE.MeshStandardMaterial({ color: 0xc4c5c0, roughness: .68, metalness: .22 });
const gripGold = new THREE.MeshStandardMaterial({ color: 0xb69358, roughness: .7, metalness: .18 });
const gripLeather = new THREE.MeshStandardMaterial({ color: 0x504741, roughness: .95 });
// A continuous leather fist carries small overlapping armour plates. Finger
// divisions are shallow seams, not four disconnected white tubes.
const palmPlate = new THREE.Mesh(new THREE.SphereGeometry(1, 16, 12), gripSilver);
palmPlate.scale.set(.043, .046, .032);
palmPlate.position.set(0, -.002, -.006);
gripGauntlet.add(palmPlate);
for (const [i, y] of [-.031, -.011, .009, .029].entries()) {
  const finger = new THREE.Mesh(new THREE.SphereGeometry(1, 12, 8), gripLeather);
  finger.scale.set(.033 - Math.abs(i - 1.5) * .003, .013, .017);
  finger.position.set(-.003, y, .019);
  gripGauntlet.add(finger);
  const knuckle = new THREE.Mesh(new THREE.SphereGeometry(1, 10, 8), gripSilver);
  knuckle.scale.set(.029, .0115, .012);
  knuckle.position.set(-.004, y, .03);
  gripGauntlet.add(knuckle);
}
const thumb = new THREE.Mesh(new THREE.CapsuleGeometry(.013, .024, 5, 10), gripLeather);
thumb.position.set(.023, .015, .026);
thumb.rotation.z = -.95;
gripGauntlet.add(thumb);
const thumbPlate = new THREE.Mesh(new THREE.SphereGeometry(1, 12, 8), gripSilver);
thumbPlate.scale.set(.016, .022, .011);
thumbPlate.position.set(.025, .02, .033);
thumbPlate.rotation.z = -.95;
gripGauntlet.add(thumbPlate);
const backPlate = outlined(new THREE.Mesh(new THREE.SphereGeometry(1, 8, 6), gripSilver), 0x493b34, 1.035);
backPlate.scale.set(.044, .045, .017);
backPlate.position.set(0, 0, -.031);
gripGauntlet.add(backPlate);
const plateBorder = new THREE.Mesh(new THREE.SphereGeometry(1, 8, 6), gripGold);
plateBorder.scale.set(.047, .048, .014);
plateBorder.position.set(0, 0, -.03);
gripGauntlet.add(plateBorder);
const backInlay = new THREE.Mesh(new THREE.OctahedronGeometry(.016), gripGold);
backInlay.scale.set(1, 1.45, .22);
backInlay.position.set(0, .003, -.047);
gripGauntlet.add(backInlay);

const wristCuff = new THREE.Mesh(new THREE.CylinderGeometry(.038, .043, .055, 10), gripSilver);
wristCuff.position.y = .037;
rightHand.add(wristCuff);
const cuffTrim = new THREE.Mesh(new THREE.TorusGeometry(.04, .0035, 5, 12), gripGold);
cuffTrim.rotation.x = Math.PI / 2;
cuffTrim.position.y = .014;
rightHand.add(cuffTrim);
gripGauntlet.traverse((part) => { if (part.isMesh) part.castShadow = true; });
swordGripPivot.add(gripGauntlet);
swordSocket.add(swordGripPivot);

// Share geometry and materials so both gloves remain identical in detail,
// size and colour. Mirror the thumb for the left hand; keep its own wrist
// attachment separate from the shield's animation stabilisation.
const leftGloveSocket = createPalmSocket(leftHand, .075);
leftGloveSocket.name = 'LeftHandGloveSocket';
const leftGripPivot = new THREE.Group();
leftGripPivot.rotation.set(.95, .08, -.38);
const leftGauntlet = gripGauntlet.clone(true);
leftGauntlet.name = 'ClosedShieldGrip';
leftGauntlet.scale.x = -1;
leftGripPivot.add(leftGauntlet);
leftGloveSocket.add(leftGripPivot);
leftHand.add(wristCuff.clone(), cuffTrim.clone());

const shield = prepareEquipment(shieldFile, .28);
// The generated shield includes a sculpted fist on its rear. Remove that
// protrusion so it cannot appear beside the character's actual left glove.
shield.updateMatrixWorld(true);
shield.traverse((mesh) => {
  if (!mesh.isMesh || !mesh.geometry.index) return;
  const geometry = mesh.geometry.clone();
  const point = new THREE.Vector3();
  const toShield = shield.matrixWorld.clone().invert().multiply(mesh.matrixWorld);
  const remove = new Uint8Array(geometry.attributes.position.count);
  for (let i = 0; i < remove.length; i++) {
    point.fromBufferAttribute(geometry.attributes.position, i).applyMatrix4(toShield);
    remove[i] = point.z < -.13 && point.x < .22 && point.x > -.68 && point.y > -.42 && point.y < .3 ? 1 : 0;
  }
  const indices = [];
  for (let i = 0; i < geometry.index.count; i += 3) {
    const a = geometry.index.getX(i), b = geometry.index.getX(i + 1), c = geometry.index.getX(i + 2);
    if (!(remove[a] || remove[b] || remove[c])) indices.push(a, b, c);
  }
  geometry.setIndex(indices);
  mesh.geometry = geometry;
});
shield.position.set(.015, -.02, .08);
shield.rotation.set(0, .13, 0);
shieldSocket.add(shield);

const shieldSocketBindRotation = shieldSocket.quaternion.clone();
const handWorldRotation = new THREE.Quaternion();
const naturalWorldRotation = new THREE.Quaternion();
const inverseHandRotation = new THREE.Quaternion();
const uprightWorldRotation = new THREE.Quaternion();

function stabilizeSocket(socket, hand, bindRotation, strength) {
  hand.getWorldQuaternion(handWorldRotation);
  naturalWorldRotation.copy(handWorldRotation).multiply(bindRotation);
  naturalWorldRotation.slerp(uprightWorldRotation, strength);
  inverseHandRotation.copy(handWorldRotation).invert();
  socket.quaternion.copy(inverseHandRotation).multiply(naturalWorldRotation);
}


const motion = createDragonsteelMotion(knight, motionFile.animations);
return {root:knight, animate(time,reduced=false,mode='idle') {
 motion(time,reduced,mode);
 knight.updateMatrixWorld(true);
 knight.getWorldQuaternion(uprightWorldRotation);
 stabilizeSocket(shieldSocket,leftHand,shieldSocketBindRotation,.28);
}, dispose() {
 const geometries=new Set(), materials=new Set(), textures=new Set();
 knight.traverse(o=>{if(!o.isMesh)return;geometries.add(o.geometry);for(const m of (Array.isArray(o.material)?o.material:[o.material])){materials.add(m);for(const v of Object.values(m))if(v?.isTexture)textures.add(v);}});
 geometries.forEach(g=>g.dispose());materials.forEach(m=>m.dispose());textures.forEach(t=>t.dispose());
}};
}

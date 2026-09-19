const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..');
const input = path.join(root, 'artifacts/knight-review/knight-rigid-equipment.glb');
const texture = path.join(root, 'artifacts/knight-review/texture-mouth.png');
const outputs = [
  path.join(root, 'artifacts/knight-review/conquer-knight.glb'),
  path.join(root, 'assets/city3d/conquer-knight.glb'),
];

const source = fs.readFileSync(input);
if (source.toString('ascii', 0, 4) !== 'glTF') throw new Error('Input is not a GLB file');

const jsonLength = source.readUInt32LE(12);
const jsonType = source.readUInt32LE(16);
if (jsonType !== 0x4e4f534a) throw new Error('Missing JSON chunk');
const json = JSON.parse(source.subarray(20, 20 + jsonLength).toString('utf8').trim());
const binaryHeader = 20 + jsonLength;
const binaryLength = source.readUInt32LE(binaryHeader);
const binaryType = source.readUInt32LE(binaryHeader + 4);
if (binaryType !== 0x004e4942) throw new Error('Missing BIN chunk');
const binary = source.subarray(binaryHeader + 8, binaryHeader + 8 + binaryLength);

const image = json.images?.[0];
if (!image || image.mimeType !== 'image/png') throw new Error('Base-colour PNG not found');
const imageView = json.bufferViews[image.bufferView];
const oldStart = imageView.byteOffset || 0;
const nextStart = Math.min(...json.bufferViews
  .map(view => view.byteOffset || 0)
  .filter(offset => offset > oldStart));
const replacement = fs.readFileSync(texture);
const replacementPadded = Buffer.alloc(Math.ceil(replacement.length / 4) * 4);
replacement.copy(replacementPadded);

const rebuiltBinary = Buffer.concat([
  binary.subarray(0, oldStart),
  replacementPadded,
  binary.subarray(nextStart),
]);
const delta = replacementPadded.length - (nextStart - oldStart);
imageView.byteLength = replacement.length;
for (const view of json.bufferViews) {
  if ((view.byteOffset || 0) >= nextStart) view.byteOffset = (view.byteOffset || 0) + delta;
}
json.buffers[0].byteLength = rebuiltBinary.length;

// Meshy starts both looping clips at 1/15 s. Three.js holds that first pose from
// t=0 until the first key, which reads as a hitch whenever the clip wraps.
const normalizedInputs = new Set();
const loopStarts = {};
for (const animation of json.animations || []) {
  if (!['Walking', 'Running'].includes(animation.name)) continue;
  for (const sampler of animation.samplers) {
    if (normalizedInputs.has(sampler.input)) continue;
    normalizedInputs.add(sampler.input);
    const accessor = json.accessors[sampler.input];
    if (accessor.componentType !== 5126 || accessor.type !== 'SCALAR') {
      throw new Error(`Unsupported animation time accessor ${sampler.input}`);
    }
    const view = json.bufferViews[accessor.bufferView];
    const offset = (view.byteOffset || 0) + (accessor.byteOffset || 0);
    const first = rebuiltBinary.readFloatLE(offset);
    loopStarts[animation.name] = first;
    for (let i = 0; i < accessor.count; i++) {
      const keyOffset = offset + i * 4;
      rebuiltBinary.writeFloatLE(rebuiltBinary.readFloatLE(keyOffset) - first, keyOffset);
    }
    if (accessor.min) accessor.min[0] -= first;
    if (accessor.max) accessor.max[0] -= first;
  }
}

const jsonRaw = Buffer.from(JSON.stringify(json));
const jsonPadded = Buffer.alloc(Math.ceil(jsonRaw.length / 4) * 4, 0x20);
jsonRaw.copy(jsonPadded);
const header = Buffer.alloc(12);
header.write('glTF', 0, 'ascii');
header.writeUInt32LE(2, 4);
header.writeUInt32LE(12 + 8 + jsonPadded.length + 8 + rebuiltBinary.length, 8);
const jsonHeader = Buffer.alloc(8);
jsonHeader.writeUInt32LE(jsonPadded.length, 0);
jsonHeader.writeUInt32LE(0x4e4f534a, 4);
const binaryChunkHeader = Buffer.alloc(8);
binaryChunkHeader.writeUInt32LE(rebuiltBinary.length, 0);
binaryChunkHeader.writeUInt32LE(0x004e4942, 4);
const result = Buffer.concat([header, jsonHeader, jsonPadded, binaryChunkHeader, rebuiltBinary]);

for (const output of outputs) fs.writeFileSync(output, result);
console.log(JSON.stringify({input, texture, outputs, bytes: result.length, textureBytes: replacement.length, delta, loopStarts}, null, 2));

const fs=require('fs'),path=require('path');
const input=path.resolve(process.argv[2]||'artifacts/knight-review/original.glb');
const output=path.resolve(process.argv[3]||'artifacts/knight-review/texture-base.png');
const b=fs.readFileSync(input),jsonLength=b.readUInt32LE(12),j=JSON.parse(b.subarray(20,20+jsonLength));
const binaryStart=28+jsonLength,image=j.images[0],view=j.bufferViews[image.bufferView];
fs.writeFileSync(output,b.subarray(binaryStart+(view.byteOffset||0),binaryStart+(view.byteOffset||0)+view.byteLength));
console.log(JSON.stringify({output,bytes:view.byteLength,mimeType:image.mimeType}));

const fs=require('fs'),path=require('path'),sharp=require('sharp');
const root=path.resolve('assets/art/characters');
(async()=>{
 for(const name of ['rigged','shield']) {
  const input=fs.readFileSync(path.join(root,`infantry-t10-dragonsteel-${name}.glb`));
  const n=input.readUInt32LE(12),json=JSON.parse(input.subarray(20,20+n)),bin=input.subarray(28+n);
  const replacements=new Map();
  for(const image of json.images||[]) {
   const view=json.bufferViews[image.bufferView];
   replacements.set(image.bufferView,await sharp(bin.subarray(view.byteOffset||0,(view.byteOffset||0)+view.byteLength)).resize({width:1024,height:1024,fit:'inside',withoutEnlargement:true}).jpeg({quality:88}).toBuffer());
   image.mimeType='image/jpeg';
  }
  for(const texture of json.textures||[])if(texture.extensions?.EXT_texture_webp){texture.source=texture.extensions.EXT_texture_webp.source;delete texture.extensions.EXT_texture_webp;}
  for(const key of ['extensionsUsed','extensionsRequired'])if(json[key])json[key]=json[key].filter(x=>x!=='EXT_texture_webp');
  let offset=0;const chunks=[];
  json.bufferViews.forEach((v,i)=>{const data=replacements.get(i)||bin.subarray(v.byteOffset||0,(v.byteOffset||0)+v.byteLength);v.byteOffset=offset;v.byteLength=data.length;chunks.push(data);offset+=data.length;const pad=(4-offset%4)%4;if(pad){chunks.push(Buffer.alloc(pad));offset+=pad;}});
  json.buffers[0].byteLength=offset;
  const raw=Buffer.from(JSON.stringify(json)),j=Buffer.concat([raw,Buffer.alloc((4-raw.length%4)%4,32)]),binary=Buffer.concat(chunks);
  const header=Buffer.alloc(20);header.write('glTF');header.writeUInt32LE(2,4);header.writeUInt32LE(28+j.length+binary.length,8);header.writeUInt32LE(j.length,12);header.writeUInt32LE(0x4e4f534a,16);
  const bh=Buffer.alloc(8);bh.writeUInt32LE(binary.length);bh.writeUInt32LE(0x004e4942,4);
  const dest=path.join(root,`infantry-t10-dragonsteel-${name}-mobile.glb`);fs.writeFileSync(dest,Buffer.concat([header,j,bh,binary]));console.log(name,input.length,'=>',fs.statSync(dest).size);
 }
 await sharp(path.join(root,'infantry-t10-thumb-v2.png')).webp({quality:88}).toFile(path.join(root,'infantry-t10-thumb-v2.webp'));
})();

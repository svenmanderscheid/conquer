const fs=require('fs'),path=require('path'),sharp=require('sharp');
const root=path.resolve('assets/art/characters');
const jobs=[
 ['archer-t10-rigged-source-v1.glb','archer-t10-rigged-mobile.glb'],
 ['cavalry-t10-rigged-source-v1.glb','cavalry-t10-rigged-mobile.glb'],
 ['cavalry-t10-horse-rigged-v1.glb','cavalry-t10-horse-rigged-mobile.glb'],
];

async function optimize(source,destination){
 const input=fs.readFileSync(path.join(root,source));
 const jsonLength=input.readUInt32LE(12),json=JSON.parse(input.subarray(20,20+jsonLength));
 const binHeader=20+jsonLength,binLength=input.readUInt32LE(binHeader),bin=input.subarray(binHeader+8,binHeader+8+binLength);
 const replacements=new Map();
 for(const image of json.images||[]){
  if(image.bufferView===undefined)continue;
  const view=json.bufferViews[image.bufferView],start=view.byteOffset||0;
  replacements.set(image.bufferView,await sharp(bin.subarray(start,start+view.byteLength)).resize({width:1024,height:1024,fit:'inside',withoutEnlargement:true}).jpeg({quality:88}).toBuffer());
  image.mimeType='image/jpeg';
 }
 for(const texture of json.textures||[])if(texture.extensions?.EXT_texture_webp){texture.source=texture.extensions.EXT_texture_webp.source;delete texture.extensions.EXT_texture_webp;}
 for(const key of ['extensionsUsed','extensionsRequired'])if(json[key])json[key]=json[key].filter(value=>value!=='EXT_texture_webp');
 let offset=0;const chunks=[];
 json.bufferViews.forEach((view,index)=>{const start=view.byteOffset||0,data=replacements.get(index)||bin.subarray(start,start+view.byteLength);view.byteOffset=offset;view.byteLength=data.length;chunks.push(data);offset+=data.length;const padding=(4-offset%4)%4;if(padding){chunks.push(Buffer.alloc(padding));offset+=padding;}});
 json.buffers[0].byteLength=offset;
 const raw=Buffer.from(JSON.stringify(json)),jsonChunk=Buffer.concat([raw,Buffer.alloc((4-raw.length%4)%4,32)]),binary=Buffer.concat(chunks);
 const header=Buffer.alloc(20);header.write('glTF');header.writeUInt32LE(2,4);header.writeUInt32LE(28+jsonChunk.length+binary.length,8);header.writeUInt32LE(jsonChunk.length,12);header.writeUInt32LE(0x4e4f534a,16);
 const binaryHeader=Buffer.alloc(8);binaryHeader.writeUInt32LE(binary.length);binaryHeader.writeUInt32LE(0x004e4942,4);
 fs.writeFileSync(path.join(root,destination),Buffer.concat([header,jsonChunk,binaryHeader,binary]));
 console.log(`${source}: ${input.length} => ${fs.statSync(path.join(root,destination)).size}`);
}

(async()=>{for(const job of jobs)await optimize(...job);})().catch(error=>{console.error(error);process.exitCode=1;});

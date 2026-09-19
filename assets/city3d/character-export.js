// Minimal glTF 2.0 writer for the family contract: indexed or non-indexed mesh,
// vertex pigments, one skin, quaternion clips. No runtime or external service.
export function characterGLB(character){
 const g=character.mesh.geometry,bones=character.skeleton.bones,parts=[];
 const doc={asset:{version:'2.0',generator:'Conquer character family'},scene:0,scenes:[{nodes:[0]}],
  nodes:[{name:character.root.name,children:[1,...bones.map((b,i)=>b.parent?.isBone?null:i+2).filter(x=>x!==null)]},{name:'Character',mesh:0,skin:0}],
  meshes:[],materials:[{name:'Storybook pigments',pbrMetallicRoughness:{baseColorFactor:[1,1,1,1],metallicFactor:0,roughnessFactor:1},extras:{conquerShader:'storybook-toon',outlineWidth:.009}}],
  skins:[],animations:[],accessors:[],bufferViews:[],buffers:[{byteLength:0}]};
 let length=0;
 function accessor(array,type,componentType,bounds=false){
  const padding=(4-length%4)%4;if(padding){parts.push(new Uint8Array(padding));length+=padding;}
  const bytes=new Uint8Array(array.buffer,array.byteOffset,array.byteLength);parts.push(bytes);
  const view=doc.bufferViews.push({buffer:0,byteOffset:length,byteLength:bytes.length})-1;length+=bytes.length;
  const size={SCALAR:1,VEC2:2,VEC3:3,VEC4:4,MAT4:16}[type],a={bufferView:view,componentType,count:array.length/size,type};
  if(bounds){a.min=Array(size).fill(Infinity);a.max=Array(size).fill(-Infinity);for(let i=0;i<array.length;i++){const n=i%size;a.min[n]=Math.min(a.min[n],array[i]);a.max[n]=Math.max(a.max[n],array[i]);}}
  return doc.accessors.push(a)-1;
 }
 const attrs={};for(const [name,semantic,type] of [['position','POSITION','VEC3'],['normal','NORMAL','VEC3'],['color','COLOR_0','VEC3'],['uv','TEXCOORD_0','VEC2'],['skinIndex','JOINTS_0','VEC4'],['skinWeight','WEIGHTS_0','VEC4']])attrs[semantic]=accessor(g.attributes[name].array,type,name==='skinIndex'?5123:5126,name==='position');
 const primitive={attributes:attrs,material:0,mode:4};if(g.index)primitive.indices=accessor(g.index.array,'SCALAR',g.index.array instanceof Uint32Array?5125:5123);
 doc.meshes.push({primitives:[primitive]});
 bones.forEach(b=>{const children=b.children.filter(c=>c.isBone).map(c=>bones.indexOf(c)+2);doc.nodes.push({name:b.name,translation:b.position.toArray(),...(children.length?{children}:{})});});
 doc.skins.push({joints:bones.map((_,i)=>i+2),skeleton:2,inverseBindMatrices:accessor(new Float32Array(character.skeleton.boneInverses.flatMap(m=>m.toArray())),'MAT4',5126)});
 for(const clip of character.clips){const a={name:clip.name,samplers:[],channels:[]};for(const track of clip.tracks){const name=track.name.split('.')[0],node=bones.findIndex(b=>b.name===name)+2;if(node<2)throw new Error('Missing animation bone '+name);a.channels.push({sampler:a.samplers.length,target:{node,path:'rotation'}});a.samplers.push({input:accessor(track.times,'SCALAR',5126,true),output:accessor(track.values,'VEC4',5126),interpolation:'LINEAR'});}doc.animations.push(a);}
 doc.buffers[0].byteLength=length;
 const json=new TextEncoder().encode(JSON.stringify(doc)),jsonSize=Math.ceil(json.length/4)*4,binSize=Math.ceil(length/4)*4;
 const result=new ArrayBuffer(12+8+jsonSize+8+binSize),view=new DataView(result),bytes=new Uint8Array(result);
 view.setUint32(0,0x46546c67,true);view.setUint32(4,2,true);view.setUint32(8,result.byteLength,true);
 view.setUint32(12,jsonSize,true);view.setUint32(16,0x4e4f534a,true);bytes.fill(32,20,20+jsonSize);bytes.set(json,20);
 const binStart=20+jsonSize;view.setUint32(binStart,binSize,true);view.setUint32(binStart+4,0x004e4942,true);
 let offset=binStart+8;for(const p of parts){bytes.set(p,offset);offset+=p.length;}return result;
}

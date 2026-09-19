import * as T from './vendor/three.module.js';

// Shared pigments and broad cel-shaded surfaces inspired by our village artwork.
const ramp=new T.DataTexture(new Uint8Array([90,170,235]),3,1,T.RedFormat);
ramp.minFilter=ramp.magFilter=T.NearestFilter;ramp.needsUpdate=true;
export const storybookColors={
 stone:'#e9e1d2',cream:'#f6ecd8',trim:'#fff6df',patch:'#b7aea1',dark:'#493b33',
 blue:'#2a72c9',blueLight:'#438fe4',blueDark:'#24559c',purple:'#8c4ac4',purpleDark:'#603393',
 red:'#d6473f',redDark:'#a33031',orange:'#ed8a31',orangeDark:'#b85a25',
 wood:'#70472f',timber:'#aa743f',gold:'#e4af38',glow:'#ffe07a',
 teal:'#3a9b93',leaf:'#498047',leafLight:'#74a452',wheat:'#edc24b',water:'#57b6d7'
};
function pigment(material){
 // The landscape's bright physical lights otherwise wash the painted colours out.
 material.onBeforeCompile=shader=>{shader.fragmentShader=shader.fragmentShader.replace('#include <opaque_fragment>','outgoingLight *= 0.55;\n#include <opaque_fragment>');};
 material.customProgramCacheKey=()=> 'storybook-pigment-5';
 return material;
}
const paintedMaps=new Map();
function paintedMap(key){
 if(paintedMaps.has(key))return paintedMaps.get(key);
 const canvas=document.createElement('canvas');canvas.width=canvas.height=96;const context=canvas.getContext('2d');let seed=[...key].reduce((sum,char)=>sum+char.charCodeAt(0)*17,913);
 const random=()=>{seed=(seed*1664525+1013904223)>>>0;return seed/4294967296;};
 context.fillStyle='#fffaf0';context.fillRect(0,0,96,96);
 for(let i=0;i<18;i++){const x=random()*96,y=random()*96,rx=10+random()*25,ry=6+random()*16;context.globalAlpha=.018+random()*.04;context.fillStyle=i%3?'#806b56':'#fffdf8';context.beginPath();context.ellipse(x,y,rx,ry,random()*Math.PI,0,Math.PI*2);context.fill();}
 for(let i=0;i<55;i++){context.globalAlpha=.012+random()*.025;context.fillStyle=i%2?'#5d4b3c':'#ffffff';context.fillRect(random()*96,random()*96,1+random(),1+random());}
 context.globalAlpha=1;const texture=new T.CanvasTexture(canvas);texture.wrapS=texture.wrapT=T.RepeatWrapping;texture.repeat.set(1.5,1.5);texture.colorSpace=T.SRGBColorSpace;texture.anisotropy=4;paintedMaps.set(key,texture);return texture;
}
function paintedMaterial(key,color,options={}){const surface=paintedMap(key);return pigment(new T.MeshToonMaterial({color,gradientMap:ramp,map:surface,bumpMap:surface,bumpScale:.004,...options}));}

let villageGroundMaterial;
export function createVillageGroundMaterial(){
 if(villageGroundMaterial)return villageGroundMaterial;
 const size=512,canvas=document.createElement('canvas');canvas.width=canvas.height=size;
 const context=canvas.getContext('2d');let seed=18273;
 const random=()=>{seed=(seed*1664525+1013904223)>>>0;return seed/4294967296;};
 context.fillStyle='#718e45';context.fillRect(0,0,size,size);
 function wrapped(draw,x,y,r){
  for(const ox of [-size,0,size])for(const oy of [-size,0,size])draw(x+ox,y+oy,r);
 }
 // Large, soft colour islands keep the surface readable from the city camera.
 for(let i=0;i<54;i++){
  const x=random()*size,y=random()*size,rx=24+random()*72,ry=14+random()*42,angle=random()*Math.PI;
  context.globalAlpha=.06+random()*.1;context.fillStyle=['#9daf5e','#4e7038','#b7a963','#809a4e'][i%4];
  wrapped((px,py)=>{context.beginPath();context.ellipse(px,py,rx,ry,angle,0,Math.PI*2);context.fill();},x,y,Math.max(rx,ry));
 }
 // Sparse two-stroke grass marks read as hand-painted detail, not noise.
 context.lineCap='round';
 for(let i=0;i<210;i++){
  const x=random()*size,y=random()*size,h=2+random()*4;
  context.globalAlpha=.1+random()*.12;context.strokeStyle=i%3?'#3f6537':'#c8c675';context.lineWidth=.7+random()*.7;
  wrapped((px,py)=>{context.beginPath();context.moveTo(px,py+h);context.quadraticCurveTo(px-2,py+h*.45,px-1,py);context.moveTo(px,py+h);context.quadraticCurveTo(px+2,py+h*.4,px+1.5,py+.3);context.stroke();},x,y,h+3);
 }
 context.globalAlpha=1;
 const texture=new T.CanvasTexture(canvas);texture.wrapS=texture.wrapT=T.RepeatWrapping;texture.repeat.set(3.25,3.25);texture.colorSpace=T.SRGBColorSpace;texture.anisotropy=4;
 villageGroundMaterial=new T.MeshToonMaterial({color:'#d8ddb0',gradientMap:ramp,map:texture,bumpMap:texture,bumpScale:.012});
 return villageGroundMaterial;
}
// Owned materials for themed models; the small painted pigment maps are shared.
export function createStorybookMaterial(color,options={}){return paintedMaterial(`custom-${new T.Color(color).getHexString()}`,color,options);}
export const storybookMaterials=Object.fromEntries(Object.entries(storybookColors).map(([key,color])=>[key,paintedMaterial(key,color)]));
storybookMaterials.glow=new T.MeshBasicMaterial({color:storybookColors.glow});
const inkCache=new Map();
export function addStorybookOutline(mesh,width=.026){
 if(mesh.isInstancedMesh||mesh.userData.storybookOutline)return mesh;
 const key=width.toFixed(4);
 if(!inkCache.has(key)){
  const material=new T.MeshBasicMaterial({color:storybookColors.dark,side:T.BackSide});
  material.onBeforeCompile=shader=>{shader.vertexShader=shader.vertexShader.replace('#include <begin_vertex>',`vec3 transformed = vec3(position) + normal * ${key};`);};
  material.customProgramCacheKey=()=>`storybook-ink-${key}`;
  inkCache.set(key,material);
 }
 const outline=new T.Mesh(mesh.geometry,inkCache.get(key));
 outline.castShadow=false;outline.receiveShadow=false;outline.raycast=()=>{};
 outline.userData.storybookInk=true;mesh.add(outline);mesh.userData.storybookOutline=true;
 return mesh;
}

const materialCache=new Map();
// Preserve batching and animation references while unifying older district parts.
export function shadeStorybookRoot(root){
 root.traverse(object=>{
  if(!object.isMesh||object.userData.storybookInk)return;
  const convert=source=>{
   if(!source?.color||source.isMeshToonMaterial||source.isMeshBasicMaterial)return source;
   if(!materialCache.has(source.uuid)){
    const material=paintedMaterial(`legacy-${source.color.getHexString()}`,source.color,{transparent:source.transparent,opacity:source.opacity,side:source.side});
    materialCache.set(source.uuid,material);
   }
   return materialCache.get(source.uuid);
  };
  object.material=Array.isArray(object.material)?object.material.map(convert):convert(object.material);
 });
}

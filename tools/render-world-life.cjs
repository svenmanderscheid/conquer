'use strict';
// Render the actual Storybook farm and world-monster models as transparent map
// sprites. Models stay code-generated: this utility never derives art from an
// existing bitmap.
const fs=require('fs'),path=require('path'),http=require('http'),os=require('os'),assert=require('assert'),{execFileSync}=require('child_process');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const root=path.resolve(__dirname,'..'),outputDir=path.join(root,'assets','art','map');
const resourceKinds=['farm','lumber','quarry','gold','crystal'];
const kinds=[...resourceKinds,'orc','skeleton','golem','goblin'],requested=process.argv.find(arg=>arg.startsWith('--kinds='))?.slice(8).split(',');
const selected=(requested??kinds).filter(kind=>kinds.includes(kind));
const requestedBiomes=process.argv.find(arg=>arg.startsWith('--biomes='))?.slice(9).split(',');
if(requestedBiomes?.some(b=>!['forest','ice','sand','lava'].includes(b)))throw new Error('Unknown resource biome');
const jobs=selected.flatMap(kind=>resourceKinds.includes(kind)?(requestedBiomes||[null,'forest','ice','sand','lava']).map(biome=>({kind,biome,name:kind+(biome?'-'+biome:'')})):[{kind,biome:null,name:kind}]);
if(!selected.length)throw new Error('Use --kinds=farm,lumber,quarry,gold,crystal,orc,skeleton,golem,goblin');
const frameCount=80,frameDuration=50,size=256;
const fixture=`<!doctype html><html><body style="margin:0;background:transparent"><script type="module">
import * as T from '/assets/city3d/vendor/three.module.js';
import {buildWorldResource,worldResourceKinds} from '/assets/city3d/world-resources.js';
const scene=new T.Scene(),renderer=new T.WebGLRenderer({antialias:true,alpha:true,preserveDrawingBuffer:true});
renderer.setSize(${size},${size});renderer.setClearColor(0x000000,0);renderer.outputColorSpace=T.SRGBColorSpace;
renderer.shadowMap.enabled=true;renderer.shadowMap.type=T.PCFSoftShadowMap;
document.body.append(renderer.domElement);
scene.add(new T.HemisphereLight('#e8f5ff','#736943',2.45));
const sun=new T.DirectionalLight('#fff1cb',3.6);sun.position.set(-12,20,14);scene.add(sun);
sun.castShadow=true;sun.shadow.mapSize.set(512,512);Object.assign(sun.shadow.camera,{left:-7,right:7,top:7,bottom:-7,near:.1,far:60});sun.shadow.bias=-.0002;sun.shadow.normalBias=.025;
const camera=new T.OrthographicCamera(-2,2,2,-2,.1,400);let current=null,currentKind='';
// Sample forward at 20 fps. All source rigs have a periodic four-second cycle;
// the endpoint is validated separately and never encoded as a duplicate pause.
const loopTime=frame=>frame/${frameCount}*4;
function animate(root,kind,time){
 if(worldResourceKinds.includes(kind))root.userData.animateWorldResource?.(time);
 else root.userData.animate?.(time);
}
function boundsFor(root,kind){
 const all=new T.Box3();
 for(let frame=0;frame<${frameCount};frame++){
  animate(root,kind,loopTime(frame,kind));root.updateMatrixWorld(true);
  root.traverse(object=>{if(object.isInstancedMesh)object.computeBoundingBox?.();});
  all.union(new T.Box3().setFromObject(root));
 }
 return all;
}
function fit(bounds,kind,biome){
 const center=bounds.getCenter(new T.Vector3());
 // Buildings keep the village camera; creatures show their face and equipment
 // from a gentler three-quarter angle, as in the game's character portraits.
 camera.position.copy(center).add(worldResourceKinds.includes(kind)?new T.Vector3(84,108,132):new T.Vector3(38,64,160));camera.lookAt(center);camera.updateMatrixWorld();
 const projected=[];
 for(const x of [bounds.min.x,bounds.max.x])for(const y of [bounds.min.y,bounds.max.y])for(const z of [bounds.min.z,bounds.max.z])projected.push(new T.Vector3(x,y,z).applyMatrix4(camera.matrixWorldInverse));
 const minX=Math.min(...projected.map(point=>point.x)),maxX=Math.max(...projected.map(point=>point.x)),minY=Math.min(...projected.map(point=>point.y)),maxY=Math.max(...projected.map(point=>point.y));
 const padding=worldResourceKinds.includes(kind)?1.075:1.055,size=Math.max(maxX-minX,maxY-minY)*padding;
 camera.left=(minX+maxX-size)/2;camera.right=camera.left+size;camera.bottom=(minY+maxY-size)/2;camera.top=camera.bottom+size;camera.updateProjectionMatrix();
 if(biome){
  // These open compositions previously occupied far fewer pixels than the
  // farm. Enlarge the art around its ground anchor, keeping the one-tile hit area
  // and terrain contact fixed. The source stays 256px for mobile memory limits.
  const scale={lumber:1.2,quarry:1.25,crystal:1.35}[kind]||1;
  const anchor=new T.Vector3(0,.02,.3).applyMatrix4(camera.matrixWorldInverse);
  camera.left=anchor.x+(camera.left-anchor.x)/scale;camera.right=anchor.x+(camera.right-anchor.x)/scale;
  camera.bottom=anchor.y+(camera.bottom-anchor.y)/scale;camera.top=anchor.y+(camera.top-anchor.y)/scale;camera.updateProjectionMatrix();
 }
}
async function make(kind,biome){
 if(current){scene.remove(current);current=null;}
 renderer.shadowMap.enabled=!!biome;
 // High daylight keeps contact shadows inside the compact sprite's framing.
 // Long tree shadows would otherwise end at a visible square image boundary.
 sun.position.set(...(biome?[-7,42,9]:[-12,20,14]));
 let model;
 if(worldResourceKinds.includes(kind)){
  model=buildWorldResource(kind,{biome});
 }else{
  const {buildWorldMonster}=await import('/assets/city3d/world-monsters.js');model=buildWorldMonster(kind);
 }
 if(!model?.isObject3D)throw new Error('No renderable model for '+kind);
 current=model;currentKind=kind+':'+biome;scene.add(current);fit(boundsFor(current,kind),kind,biome);
 if(biome){
  current.traverse(part=>{if(part.isMesh&&part.material.side!==T.BackSide)part.castShadow=true;});
  // Only the actual shadow is visible. The ground plane itself has zero alpha,
  // so workers, props and building feet sit on the live regional terrain.
  const ground=new T.Mesh(new T.PlaneGeometry(20,20),new T.ShadowMaterial({color:'#493b33',opacity:.24}));
  ground.rotation.x=-Math.PI/2;ground.position.y=-.04;ground.receiveShadow=true;current.add(ground);
 }
}
window.renderLife=async(kind,time=0,biome=null)=>{
 if(kind+':'+biome!==currentKind)await make(kind,biome);
 animate(current,kind,time);current.updateMatrixWorld(true);renderer.render(scene,camera);
 return {png:renderer.domElement.toDataURL('image/png'),drawCalls:renderer.info.render.calls,triangles:renderer.info.render.triangles};
};window.ready=true;
</script></body></html>`;
function serve(){return http.createServer((req,res)=>{
 const name=decodeURIComponent(new URL(req.url,'http://localhost').pathname);
 if(name==='/'){res.setHeader('Content-Type','text/html; charset=utf-8');res.end(fixture);return;}
 if(name==='/favicon.ico'){res.writeHead(204);res.end();return;}
 const file=path.resolve(root,'.'+name);
 if(!file.startsWith(root+path.sep)||!fs.existsSync(file)){res.writeHead(404);res.end();return;}
 res.setHeader('Content-Type',file.endsWith('.js')?'text/javascript':file.endsWith('.png')?'image/png':'application/octet-stream');res.end(fs.readFileSync(file));
});}
function writeDataUrl(file,dataUrl){fs.writeFileSync(file,Buffer.from(dataUrl.slice(dataUrl.indexOf(',')+1),'base64'));}
function encodeWebp(frames,output){
 const python=process.env.PYTHON_IMAGE_RUNTIME||'C:/Users/svenm/.cache/codex-runtimes/codex-primary-runtime/dependencies/python/python.exe';
 const code="from PIL import Image; from pathlib import Path; import sys; frames=[Image.open(p).convert('RGBA') for p in sorted(Path(sys.argv[1]).glob('*.png'))]; assert len(frames)==80 and all(f.size==(256,256) for f in frames); assert any(frames[0].tobytes()!=f.tobytes() for f in frames[1:]), 'no motion frames'; frames[0].save(sys.argv[2],save_all=True,append_images=frames[1:],duration=50,loop=0,quality=80,method=5,lossless=False); im=Image.open(sys.argv[2]); assert 40<=im.n_frames<=80 and im.size==(256,256) and im.mode=='RGBA'; print(f'{im.n_frames} encoded frames, {im.size[0]}px RGBA, 20 fps')";
 return execFileSync(python,['-c',code,frames,output],{encoding:'utf8'}).trim();
}
(async()=>{
 fs.mkdirSync(outputDir,{recursive:true});const server=serve();await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));let browser;
 try{
  browser=await chromium.launch({headless:true,...(process.env.PLAYWRIGHT_CHANNEL?{channel:process.env.PLAYWRIGHT_CHANNEL}:{channel:'chrome'})});
  const page=await browser.newPage({viewport:{width:size,height:size}}),errors=[];page.on('pageerror',error=>errors.push(error.message));page.on('console',message=>{if(['error','warning'].includes(message.type()))errors.push(message.text());});
  await page.goto(`http://127.0.0.1:${server.address().port}`);await page.waitForFunction(()=>window.ready);
  for(const {kind,biome,name} of jobs){
   // Warm the shadow/material pass when switching from a transparent model to
   // a regional scene, before capturing the still pose or animation frames.
   await page.evaluate(({kind,biome})=>window.renderLife(kind,0,biome),{kind,biome});
   const still=await page.evaluate(({kind,biome})=>window.renderLife(kind,0,biome),{kind,biome}),png=path.join(outputDir,`life-${name}.png`);writeDataUrl(png,still.png);
   const frameDir=fs.mkdtempSync(path.join(os.tmpdir(),`conquer-life-${kind}-`));
   for(let frame=0;frame<frameCount;frame++){
    const file=path.join(frameDir,`${String(frame).padStart(2,'0')}.png`);
    const time=frame/frameCount*4;
    const image=await page.evaluate(({kind,time,biome})=>window.renderLife(kind,time,biome),{kind,time,biome});writeDataUrl(file,image.png);
   }
   const endpoint=await page.evaluate(({kind,biome})=>window.renderLife(kind,4,biome),{kind,biome});
   assert.ok(endpoint.png===still.png,'source rig must close its four-second cycle: '+name);
   const webp=path.join(outputDir,`life-${name}.webp`),encoded=encodeWebp(frameDir,webp);
   assert.ok(fs.statSync(png).size>1024,`empty PNG for ${kind}`);assert.ok(fs.statSync(webp).size>1024,`empty WebP for ${kind}`);
   console.log(`${name}: ${still.drawCalls} draw calls, ${still.triangles} triangles; ${encoded}; ${path.relative(root,png)} ${Math.round(fs.statSync(png).size/1024)} KB; ${path.relative(root,webp)} ${Math.round(fs.statSync(webp).size/1024)} KB`);
  }
  assert.deepStrictEqual(errors,[],'renderer warnings/errors');
 }finally{if(browser)await browser.close();await new Promise(resolve=>server.close(resolve));}
})().catch(error=>{console.error(error);process.exitCode=1;});

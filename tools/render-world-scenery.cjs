'use strict';
// Rebuild transparent map sprites from the same Three.js scenery models.
const fs=require('fs'),path=require('path'),http=require('http');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const root=path.resolve(__dirname,'..');
const fixture=`<!doctype html><html><body style="margin:0"><script type="module">
import * as T from '/assets/city3d/vendor/three.module.js';
import {buildWorldScenery} from '/assets/city3d/world-scenery-models.js';
const scene=new T.Scene(),renderer=new T.WebGLRenderer({antialias:true,alpha:true,preserveDrawingBuffer:true});
renderer.setSize(384,384);renderer.setClearColor(0,0);renderer.outputColorSpace=T.SRGBColorSpace;
document.body.append(renderer.domElement);
scene.add(new T.HemisphereLight('#e8f5ff','#736943',2.25));
const sun=new T.DirectionalLight('#fff1cb',3.5);sun.position.set(-12,20,14);scene.add(sun);
const camera=new T.OrthographicCamera(-2,2,2,-2,.1,400);let current;
window.renderScenery=kind=>{
 if(current)scene.remove(current);current=buildWorldScenery(kind);scene.add(current);
 const bounds=new T.Box3().setFromObject(current),center=bounds.getCenter(new T.Vector3());
 camera.position.copy(center).add(new T.Vector3(84,108,132));camera.lookAt(center);camera.updateMatrixWorld();
 const points=[];for(const x of [bounds.min.x,bounds.max.x])for(const y of [bounds.min.y,bounds.max.y])for(const z of [bounds.min.z,bounds.max.z])points.push(new T.Vector3(x,y,z).applyMatrix4(camera.matrixWorldInverse));
 const xs=points.map(p=>p.x),ys=points.map(p=>p.y),size=Math.max(Math.max(...xs)-Math.min(...xs),Math.max(...ys)-Math.min(...ys))*1.06;
 const cx=(Math.max(...xs)+Math.min(...xs))/2,cy=(Math.max(...ys)+Math.min(...ys))/2;
 camera.left=cx-size/2;camera.right=cx+size/2;camera.top=cy+size/2;camera.bottom=cy-size/2;camera.updateProjectionMatrix();
 renderer.render(scene,camera);return {png:renderer.domElement.toDataURL('image/png'),drawCalls:renderer.info.render.calls,triangles:renderer.info.render.triangles};
};window.ready=true;
</script></body></html>`;
(async()=>{
 const server=http.createServer((req,res)=>{
  const name=decodeURIComponent(new URL(req.url,'http://localhost').pathname);
  if(name==='/'){res.setHeader('Content-Type','text/html');res.end(fixture);return;}
  const file=path.resolve(root,'.'+name);
  if(!file.startsWith(root+path.sep)||!fs.existsSync(file)){res.writeHead(404);res.end();return;}
  res.setHeader('Content-Type',file.endsWith('.js')?'text/javascript':'application/octet-stream');res.end(fs.readFileSync(file));
 });
 await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));let browser;
 try{
  browser=await chromium.launch({headless:true,channel:process.env.PLAYWRIGHT_CHANNEL||'chrome'});
  const page=await browser.newPage();page.on('pageerror',error=>console.error(error));
  await page.goto(`http://127.0.0.1:${server.address().port}`);await page.waitForFunction(()=>window.ready);
  for(const kind of ['pine','oak','mountain','rocks','cherry']){
   const rendered=await page.evaluate(kind=>window.renderScenery(kind),kind);
   const output=path.join(root,'assets/art/map',`scenery-${kind}.png`);fs.mkdirSync(path.dirname(output),{recursive:true});
   fs.writeFileSync(output,Buffer.from(rendered.png.split(',')[1],'base64'));
   console.log(`${kind}: ${rendered.drawCalls} draw calls, ${rendered.triangles} triangles → ${output}`);
  }
 }finally{if(browser)await browser.close();await new Promise(resolve=>server.close(resolve));}
})().catch(error=>{console.error(error);process.exitCode=1;});

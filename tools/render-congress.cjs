'use strict';
// Isolated transparent landmark artwork. No application state is read or changed.
const fs=require('fs'),path=require('path'),http=require('http'),os=require('os'),{execFileSync}=require('child_process');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const root=path.resolve(__dirname,'..');
const fixture=`<!doctype html><html><body style="margin:0"><script type="module">
import * as T from '/assets/city3d/vendor/three.module.js';
import {buildCongress} from '/assets/city3d/congress.js';
const scene=new T.Scene(),renderer=new T.WebGLRenderer({antialias:true,alpha:true,preserveDrawingBuffer:true});
renderer.setClearColor(0,0);renderer.outputColorSpace=T.SRGBColorSpace;document.body.append(renderer.domElement);
scene.add(new T.HemisphereLight('#e8f5ff','#736943',2.25));
const sun=new T.DirectionalLight('#fff1cb',3.5);sun.position.set(-12,20,14);scene.add(sun);
const camera=new T.OrthographicCamera(-5.5,5.5,5.5,-5.5,.1,100);camera.position.set(12,12.0,21);camera.lookAt(0,1.75,0);
const congress=buildCongress();scene.add(congress);
window.renderCongress=(time=0,animated=false)=>{congress.userData.animate(time);renderer.setSize(animated?480:640,animated?480:640);renderer.render(scene,camera);return{png:renderer.domElement.toDataURL('image/png'),drawCalls:renderer.info.render.calls,triangles:renderer.info.render.triangles};};
window.ready=true;
</script></body></html>`;
(async()=>{
 const server=http.createServer((req,res)=>{const name=decodeURIComponent(new URL(req.url,'http://localhost').pathname);if(name==='/'){res.setHeader('Content-Type','text/html');res.end(fixture);return;}const file=path.resolve(root,'.'+name);if(!file.startsWith(root+path.sep)||!fs.existsSync(file)){res.writeHead(404);res.end();return;}res.setHeader('Content-Type',file.endsWith('.js')?'text/javascript':'application/octet-stream');res.end(fs.readFileSync(file));});
 await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));let browser;
 try{
  browser=await chromium.launch({headless:true,...(process.env.PLAYWRIGHT_CHANNEL?{channel:process.env.PLAYWRIGHT_CHANNEL}:{})});const page=await browser.newPage();page.on('pageerror',e=>console.error(e));await page.goto(`http://127.0.0.1:${server.address().port}`);await page.waitForFunction(()=>window.ready);
  const still=await page.evaluate(()=>window.renderCongress()),png=path.join(root,'assets/art/map/congress.png');fs.writeFileSync(png,Buffer.from(still.png.split(',')[1],'base64'));console.log(JSON.stringify({png,drawCalls:still.drawCalls,triangles:still.triangles}));
  const frames=fs.mkdtempSync(path.join(os.tmpdir(),'conquer-congress-'));
  for(let frame=0;frame<32;frame++){const output=await page.evaluate(frame=>window.renderCongress(frame/8,true),frame);fs.writeFileSync(path.join(frames,String(frame).padStart(2,'0')+'.png'),Buffer.from(output.png.split(',')[1],'base64'));}
  const python=process.env.PYTHON_IMAGE_RUNTIME||'C:/Users/svenm/.cache/codex-runtimes/codex-primary-runtime/dependencies/python/python.exe',webp=path.join(root,'assets/art/map/congress.webp');
  const result=execFileSync(python,['-c',"from PIL import Image; from pathlib import Path; import sys; frames=[Image.open(p).convert('RGBA') for p in sorted(Path(sys.argv[1]).glob('*.png'))]; assert len(frames)==32; frames[0].save(sys.argv[2],save_all=True,append_images=frames[1:],duration=125,loop=0,quality=85,method=4); im=Image.open(sys.argv[2]); assert im.n_frames==32; assert im.size==(480,480); assert frames[0].tobytes()!=frames[8].tobytes(); print('PASS 32 distinct-motion frames, 480x480, four-second loop')",frames,webp],{encoding:'utf8'});console.log(result.trim());console.log(`${webp}: ${Math.round(fs.statSync(webp).size/1024)} KB`);
 }finally{if(browser)await browser.close();await new Promise(resolve=>server.close(resolve));}
})().catch(error=>{console.error(error);process.exitCode=1;});

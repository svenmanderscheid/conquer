'use strict';
// Render the real village models into transparent map/skin-picker portraits.
// PLAYWRIGHT_MODULE and PLAYWRIGHT_CHANNEL may select an installed runtime.
const fs=require('fs'),path=require('path'),http=require('http'),os=require('os'),{execFileSync}=require('child_process');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const root=path.resolve(__dirname,'..');
const fixture=`<!doctype html><html><body style="margin:0"><script type="module">
import * as T from '/assets/city3d/vendor/three.module.js';
import {CASTLE_SKIN_IDS} from '/assets/city3d/castle-effects.js?v=collection5';
import {buildFortress} from '/assets/city3d/fortress-cartoon.js';
import {storybookMaterials as M} from '/assets/city3d/storybook-style.js?v=storybook1';
const scene=new T.Scene(),renderer=new T.WebGLRenderer({antialias:true,alpha:true,preserveDrawingBuffer:true});
renderer.setSize(640,640);renderer.setClearColor(0,0);renderer.outputColorSpace=T.SRGBColorSpace;
document.body.append(renderer.domElement);
scene.add(new T.HemisphereLight('#e8f5ff','#736943',2.25));
const sun=new T.DirectionalLight('#fff1cb',3.5);sun.position.set(-12,20,14);scene.add(sun);
const camera=new T.OrthographicCamera(-4.9,4.9,4.9,-4.9,.1,100);camera.position.set(12,15.5,19);camera.lookAt(0,2.8,0);
const flag=(parent,x,y,z,w,h)=>{const pole=new T.Mesh(new T.CylinderGeometry(.026,.026,h+.35,8),M.wood);pole.position.set(x,y+h/2,z);parent.add(pole);const cloth=new T.Mesh(new T.PlaneGeometry(w,h),M.blue.clone());cloth.material.side=T.DoubleSide;cloth.position.set(x+w/2,y+h*.6,z);parent.add(cloth);return cloth;};
const castle=buildFortress({parent:scene,flag,emblem:()=>null});castle.position.set(0,0,0);
window.skinIds=CASTLE_SKIN_IDS;
window.renderSkin=async(skin,time=0,animated=false)=>{castle.userData.setCastleSkin(skin);await castle.userData.castleModel()?.userData.readyPromise;castle.userData.animateCastle(time);castle.traverse(o=>{if(o.userData.cosmeticEffect)o.visible=animated;});const size=animated?(skin==='dragon'?420:480):640;renderer.setSize(size,size);renderer.render(scene,camera);return {png:renderer.domElement.toDataURL('image/png'),drawCalls:renderer.info.render.calls,triangles:renderer.info.render.triangles};};
window.ready=true;
</script></body></html>`;
(async()=>{
 const server=http.createServer((req,res)=>{
  const name=decodeURIComponent(new URL(req.url,'http://localhost').pathname);
  if(name==='/'){res.setHeader('Content-Type','text/html');res.end(fixture);return;}
  const file=path.resolve(root,'.'+name);
  if(!file.startsWith(root+path.sep)||!fs.existsSync(file)){res.writeHead(404);res.end();return;}
  res.setHeader('Content-Type',file.endsWith('.js')?'text/javascript':'application/octet-stream');res.end(fs.readFileSync(file));
 });
 await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));
 let browser;
 try{
  browser=await chromium.launch({headless:true,...(process.env.PLAYWRIGHT_CHANNEL?{channel:process.env.PLAYWRIGHT_CHANNEL}:{})});
  const page=await browser.newPage();page.on('pageerror',e=>console.error(e));
  await page.goto(`http://127.0.0.1:${server.address().port}`);await page.waitForFunction(()=>window.ready);
  const requested=process.argv.find(arg=>arg.startsWith('--skins='))?.slice(8).split(','),skinIds=(await page.evaluate(()=>window.skinIds)).filter(skin=>!requested||requested.includes(skin)),temp=fs.mkdtempSync(path.join(os.tmpdir(),'conquer-skin-motion-'));
  for(const skin of skinIds){
   const rendered=await page.evaluate(skin=>window.renderSkin(skin),skin);
   const output=path.join(root,'assets/art/map',`castle-${skin}.png`);
   fs.writeFileSync(output,Buffer.from(rendered.png.split(',')[1],'base64'));
   console.log(`${skin}: ${rendered.drawCalls} draw calls, ${rendered.triangles} triangles → ${output}`);
   if(skin!=='default'&&!process.argv.includes('--stills-only')){
    const frames=path.join(temp,skin);fs.mkdirSync(frames);
    const frameCount=skin==='dragon'?48:32,framesPerSecond=skin==='dragon'?6:8;
    for(let frame=0;frame<frameCount;frame++){
     const result=await page.evaluate(({skin,frame,framesPerSecond})=>window.renderSkin(skin,frame/framesPerSecond,true),{skin,frame,framesPerSecond});
     fs.writeFileSync(path.join(frames,String(frame).padStart(2,'0')+'.png'),Buffer.from(result.png.split(',')[1],'base64'));
    }
    const python=process.env.PYTHON_IMAGE_RUNTIME||'C:/Users/svenm/.cache/codex-runtimes/codex-primary-runtime/dependencies/python/python.exe';
    const animatedOutput=path.join(root,'assets/art/map',`castle-${skin}.webp`);
    execFileSync(python,['-c',"from PIL import Image; from pathlib import Path; import sys; frames=[Image.open(p).convert('RGBA') for p in sorted(Path(sys.argv[1]).glob('*.png'))]; frames[0].save(sys.argv[2],save_all=True,append_images=frames[1:],duration=int(sys.argv[3]),loop=0,quality=80,method=4)",frames,animatedOutput,String(Math.round(1000/framesPerSecond))]);
    console.log(`  animated: ${Math.round(fs.statSync(animatedOutput).size/1024)} KB`);
   }
  }
 }finally{if(browser)await browser.close();await new Promise(resolve=>server.close(resolve));}
})().catch(error=>{console.error(error);process.exitCode=1;});

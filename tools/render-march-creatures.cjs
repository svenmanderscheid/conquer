'use strict';
const fs=require('fs'),path=require('path'),http=require('http'),os=require('os'),assert=require('assert/strict'),{execFileSync}=require('child_process');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const root=path.resolve(__dirname,'..'),out=path.join(root,'assets/art/marches'),size=384;
const creatures=Object.freeze({
 phoenix:{builder:'buildMarchPhoenix',frames:36,period:1.2},
 dragon:{builder:'buildMarchDragon',frames:48,period:1.6}
});
const requested=process.argv.find(arg=>arg.startsWith('--skin='))?.slice(7),selected=requested?[requested]:Object.keys(creatures);
for(const id of selected)assert(creatures[id],`Unknown march creature: ${id}`);
const html=`<!doctype html><body><script type="module">
import * as T from '/assets/city3d/vendor/three.module.js';import {buildMarchPhoenix,buildMarchDragon} from '/assets/city3d/march-creatures.js';
const builders={buildMarchPhoenix,buildMarchDragon},renderer=new T.WebGLRenderer({antialias:true,alpha:true,preserveDrawingBuffer:true});renderer.setSize(${size},${size});renderer.setClearColor(0,0);renderer.outputColorSpace=T.SRGBColorSpace;
const scene=new T.Scene();scene.add(new T.HemisphereLight('#e8f5ff','#736943',2.45));const sun=new T.DirectionalLight('#fff1cb',3.6);sun.position.set(-12,20,14);scene.add(sun);
const camera=new T.OrthographicCamera(-3,3,3,-3,.1,100);camera.position.set(-9,8,11);camera.lookAt(0,0,-.55);camera.updateMatrixWorld();let creature=null;
window.selectCreature=(builder,frames,period)=>{if(creature)scene.remove(creature);creature=builders[builder]();scene.add(creature);
 // Fit every moving vertex in camera space, rather than a wasteful world AABB.
 const bounds=new T.Box3(),point=new T.Vector3();for(let frame=0;frame<frames;frame++){creature.userData.animateMarch(frame/frames*period);creature.updateMatrixWorld(true);creature.traverse(object=>{if(!object.isMesh||object.userData.storybookInk)return;const positions=object.geometry.attributes.position;for(let i=0;i<positions.count;i++)bounds.expandByPoint(point.fromBufferAttribute(positions,i).applyMatrix4(object.matrixWorld).applyMatrix4(camera.matrixWorldInverse));});}
 const center=bounds.getCenter(new T.Vector3()),span=Math.max(bounds.max.x-bounds.min.x,bounds.max.y-bounds.min.y)*1.09;camera.left=center.x-span/2;camera.right=center.x+span/2;camera.bottom=center.y-span/2;camera.top=center.y+span/2;camera.updateProjectionMatrix();return {motionPeriod:creature.userData.motionPeriod||null,creature:creature.userData.creature||null};};
window.renderFrame=time=>{creature.userData.animateMarch(time);renderer.render(scene,camera);return renderer.domElement.toDataURL('image/png')};window.ready=true;
</script>`;
(async()=>{fs.mkdirSync(out,{recursive:true});const server=http.createServer((req,res)=>{const name=decodeURIComponent(new URL(req.url,'http://localhost').pathname);if(name==='/'){res.setHeader('Content-Type','text/html');res.end(html);return;}if(name==='/favicon.ico'){res.writeHead(204);res.end();return;}const file=path.resolve(root,'.'+name);if(!file.startsWith(root+path.sep)||!fs.existsSync(file)){res.writeHead(404);res.end();return;}res.setHeader('Content-Type','text/javascript');res.end(fs.readFileSync(file));});await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));let browser;
try{browser=await chromium.launch({headless:true,channel:process.env.PLAYWRIGHT_CHANNEL||'chrome'});const page=await browser.newPage(),errors=[];page.on('pageerror',error=>errors.push(error.message));await page.goto('http://127.0.0.1:'+server.address().port);await page.waitForFunction(()=>window.ready);
 const python=process.env.PYTHON_IMAGE_RUNTIME||'C:/Users/svenm/.cache/codex-runtimes/codex-primary-runtime/dependencies/python/python.exe';
 for(const id of selected){const definition=creatures[id],meta=await page.evaluate(({builder,frames,period})=>selectCreature(builder,frames,period),definition);if(id==='dragon'){assert.equal(meta.creature,'dragon');assert.equal(meta.motionPeriod,definition.period);}
  const dir=fs.mkdtempSync(path.join(os.tmpdir(),`conquer-${id}-`));for(let frame=0;frame<definition.frames;frame++){const png=await page.evaluate(time=>renderFrame(time),frame/definition.frames*definition.period);fs.writeFileSync(path.join(dir,String(frame).padStart(2,'0')+'.png'),Buffer.from(png.split(',')[1],'base64'));}
  const first=await page.evaluate(()=>renderFrame(0)),loop=await page.evaluate(period=>renderFrame(period),definition.period);assert.equal(first,loop,`${id} flight rig loops without pose jumps`);
  const script="from PIL import Image; from pathlib import Path; import sys; f=[Image.open(p).convert('RGBA') for p in sorted(Path(sys.argv[1]).glob('*.png'))]; expected=int(sys.argv[3]); assert len(f)==expected; f[0].save(sys.argv[2]+'/flight-'+sys.argv[4]+'.png'); durations=([33,33,34]*((expected+2)//3))[:expected]; f[0].save(sys.argv[2]+'/flight-'+sys.argv[4]+'.webp',save_all=True,append_images=f[1:],duration=durations,loop=0,quality=86,method=5); sheet=Image.new('RGB',(1536,384),'#aa9d87'); picks=[0,expected//4,expected//2,expected*3//4]; [sheet.paste(f[i],(n*384,0),f[i]) for n,i in enumerate(picks)]; sheet.save(sys.argv[2]+'/flight-'+sys.argv[4]+'-poses.jpg'); print(str(expected)+' frames, 384px, seamless '+sys.argv[4]+' loop')";
  console.log(execFileSync(python,['-c',script,dir,out,String(definition.frames),id],{encoding:'utf8'}).trim());console.log(`${id}: ${Math.round(fs.statSync(path.join(out,`flight-${id}.webp`)).size/1024)} KB`);
 }
 assert.deepEqual(errors,[]);
}finally{if(browser)await browser.close();server.closeAllConnections?.();await new Promise(resolve=>server.close(resolve));}})().catch(error=>{console.error(error);process.exitCode=1});

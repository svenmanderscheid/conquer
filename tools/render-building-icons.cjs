'use strict';
// Export the actual city models; no second set of invented building geometry.
const fs=require('fs'),path=require('path'),net=require('net'),{spawn}=require('child_process');
const {chromium}=require('playwright');
const root=path.resolve(__dirname,'..');
const names={castle:'Festung',academy:'Akademie',barrack:'Kaserne',archery_range:'Schützenlager',stable:'Reiterhof',hospital:'Hospital',storage:'Lagerhaus',treasure_house:'Schatzkammer',hall_of_alliance:'Allianzhalle',trading_post:'Handelsposten',farm:'Bauernhof',lumber_camp:'Holzfällerlager',quarry:'Steinbruch',gold_mine:'Goldmine',wall:'Stadtmauer',watch_tower:'Wachturm'};
const exporter=`
const iconRenderer=new T.WebGLRenderer({antialias:true,alpha:true,preserveDrawingBuffer:true});
iconRenderer.setSize(512,512);iconRenderer.outputColorSpace=T.SRGBColorSpace;iconRenderer.toneMapping=T.ACESFilmicToneMapping;iconRenderer.toneMappingExposure=1.3;
window.exportBuildingIcon=code=>{
 const object=code==='castle'?keep:code==='lumber_camp'?mill:gameBuildings.find(o=>o.userData.building===code);
 if(!object)throw Error('Missing city model: '+code);
 const originalParent=object.parent,position=object.position.clone();
 const portrait=new T.Scene();portrait.add(new T.HemisphereLight('#e8f5ff','#736943',2.55));
 const light=new T.DirectionalLight('#fff1cb',2.55);light.position.set(-12,20,8);portrait.add(light);
 const out=iconRenderer;
 try{
  portrait.add(object);object.position.set(0,0,0);object.updateMatrixWorld(true);
  const bounds=new T.Box3().setFromObject(object),center=bounds.getCenter(new T.Vector3());
  const cam=new T.OrthographicCamera(-10,10,10,-10,.1,1000);cam.position.copy(center).add(new T.Vector3(42,38,66));cam.lookAt(center);cam.updateMatrixWorld();
  const pts=[];for(const x of [bounds.min.x,bounds.max.x])for(const y of [bounds.min.y,bounds.max.y])for(const z of [bounds.min.z,bounds.max.z])pts.push(new T.Vector3(x,y,z).applyMatrix4(cam.matrixWorldInverse));
  const xs=pts.map(p=>p.x),ys=pts.map(p=>p.y),size=Math.max(Math.max(...xs)-Math.min(...xs),Math.max(...ys)-Math.min(...ys))*1.02;
  const cx=(Math.max(...xs)+Math.min(...xs))/2,cy=(Math.max(...ys)+Math.min(...ys))/2;
  cam.left=cx-size/2;cam.right=cx+size/2;cam.top=cy+size/2;cam.bottom=cy-size/2;cam.updateProjectionMatrix();out.render(portrait,cam);
  return out.domElement.toDataURL('image/png');
 }finally{originalParent.add(object);object.position.copy(position);object.updateMatrixWorld(true);}
};`;
(async()=>{
 const port=await new Promise(resolve=>{const s=net.createServer();s.listen(0,'127.0.0.1',()=>{const p=s.address().port;s.close(()=>resolve(p));});});
 const child=spawn('C:/xampp/php/php.exe',[path.join(root,'tools/preview-feature-fixture.php'),'--port='+port,'--hud'],{cwd:root,windowsHide:true,stdio:['pipe','pipe','pipe']});
 let browser;
 try{
  await new Promise((resolve,reject)=>{let log='';const timer=setTimeout(()=>reject(Error(log)),60000);child.stdout.on('data',b=>{log+=b;if(log.includes('Synthetic preview ready')){clearTimeout(timer);resolve();}});child.stderr.on('data',b=>log+=b);child.on('error',reject);});
  browser=await chromium.launch({headless:true,channel:'chrome'});const page=await browser.newPage({viewport:{width:1280,height:900}});
  const errors=[];page.on('pageerror',e=>errors.push(e.message));
  await page.route('**/assets/city3d/scene.js*',async route=>{const response=await route.fetch();await route.fulfill({response,body:(await response.text())+'\n'+exporter});});
  const base='http://127.0.0.1:'+port;await page.goto(base);await page.locator('[data-mode="login"]').click();await page.locator('[name="username"]').fill('PreviewPlayer');await page.locator('[name="password"]').fill('PreviewFixture!2026');await Promise.all([page.waitForURL('**/city'),page.locator('#auth-submit').click()]);
  await page.locator('#city-frame').waitFor();const frame=await (await page.locator('#city-frame').elementHandle()).contentFrame();await frame.waitForURL('**/city/3d*');await frame.waitForFunction(()=>window.exportBuildingIcon&&window.conquer3D?.getState().ready);
  const dest=path.join(root,'assets/art/buildings');fs.mkdirSync(dest,{recursive:true});
  for(const code of Object.keys(names)){const png=await frame.evaluate(code=>window.exportBuildingIcon(code),code);fs.writeFileSync(path.join(dest,code+'-city-v2.png'),Buffer.from(png.split(',')[1],'base64'));console.log('Rendered '+code);}
  const out=path.join(root,'artifacts/building-icons');fs.mkdirSync(out,{recursive:true});await page.screenshot({path:path.join(out,'city-overview.png')});
  await frame.evaluate(()=>window.dispatchEvent(new CustomEvent('conquer-focus-building',{detail:{code:'academy'}})));await page.screenshot({path:path.join(out,'city-academy.png')});
  for(const [width,height] of [[1280,900],[390,844],[320,740],[844,390]]){
   await page.setViewportSize({width,height});await frame.evaluate(()=>parent.postMessage({type:'conquer:building',code:'academy'},location.origin));await page.locator('#game-dialog[open]').waitFor();await page.screenshot({path:path.join(out,'academy-'+width+'.png')});await page.locator('#game-dialog').press('Escape');
  }
  console.log('Browser errors:',JSON.stringify(errors));if(errors.length)process.exitCode=1;
 }finally{await browser?.close();child.stdin.end();child.kill();child.stdout.destroy();child.stderr.destroy();child.unref();}
})().catch(e=>{console.error(e);process.exitCode=1;});

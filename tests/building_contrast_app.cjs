'use strict';
// Focused building-dialog contrast regression against a disposable PHP fixture.
// Run: node tests/building_contrast_app.cjs (PLAYWRIGHT_MODULE may point to Playwright).
const fs=require('fs'),path=require('path'),net=require('net'),assert=require('assert');
const {spawn}=require('child_process');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const root=path.resolve(__dirname,'..'),output=path.join(root,'artifacts','contrast-fixes');
fs.mkdirSync(output,{recursive:true});

async function fixture(){
 const port=await new Promise(resolve=>{const server=net.createServer();server.listen(0,'127.0.0.1',()=>{const port=server.address().port;server.close(()=>resolve(port));});});
 const child=spawn(process.env.PHP_BINARY||'C:/xampp/php/php.exe',[path.join(root,'tools/preview-feature-fixture.php'),'--port='+port,'--hud'],{cwd:root,stdio:['pipe','pipe','pipe'],windowsHide:true});
 let log='';const ready=new Promise((resolve,reject)=>{const timer=setTimeout(()=>reject(new Error('Fixture startup timeout: '+log)),60000);child.stdout.on('data',chunk=>{log+=chunk;if(log.includes('Synthetic preview ready')){clearTimeout(timer);resolve();}});child.stderr.on('data',chunk=>{log+=chunk;});child.once('error',reject);child.once('exit',code=>{clearTimeout(timer);reject(new Error('Fixture stopped ('+code+'): '+log));});});
 return{child,ready,base:'http://127.0.0.1:'+port};
}

function channel(value){value/=255;return value<=.04045?value/12.92:Math.pow((value+.055)/1.055,2.4);}
function ratio(a,b){const la=.2126*channel(a[0])+.7152*channel(a[1])+.0722*channel(a[2]),lb=.2126*channel(b[0])+.7152*channel(b[1])+.0722*channel(b[2]);return (Math.max(la,lb)+.05)/(Math.min(la,lb)+.05);}
function hex(value){return [1,3,5].map(i=>parseInt(value.slice(i,i+2),16));}
// This is the reported failure class: muted dark ink over navy, then disabled opacity.
assert(ratio(hex('#334155'),hex('#213e57'))<4.5,'Regression oracle rejects the former dark-on-navy pair');
assert(ratio(hex('#8f99a6'),hex('#213e57'))<4.5,'Regression oracle rejects its disabled-opacity result');

(async()=>{
 const app=await fixture();let browser,mode='blocked';const errors=[],badAssets=[],writes=[],report=[];
 try{
  await app.ready;browser=await chromium.launch({headless:true,...(process.env.BROWSER_EXECUTABLE_PATH?{executablePath:process.env.BROWSER_EXECUTABLE_PATH}:{})});
  const page=await browser.newPage({viewport:{width:1280,height:800}});page.setDefaultTimeout(20000);
  page.on('pageerror',error=>errors.push(error.message));page.on('response',response=>{if(response.url().includes('/assets/')&&response.status()>=400)badAssets.push(response.status()+' '+response.url());});
  page.on('request',request=>{if(request.url().includes('/api/')&&request.method()!=='GET')writes.push(request.method()+' '+request.url());});
  await page.route('**/api/game/state*',async route=>{const response=await route.fetch(),json=await response.json(),s=json.data,b=s.buildings.hall_of_alliance;
   Object.assign(b,{level:1,cost:{food:6800,lumber:6800,stone:6800,gold:4080},seconds:120,requirements:{castle:2,barrack:2},item_requirements:[],progression:{label:'Rallygröße',current:5,next:10}});
   Object.assign(s.buildings.castle,{level:2});Object.assign(s.buildings.barrack,{level:2});
   Object.assign(s.city,{food:mode==='blocked'?1879:99999,lumber:mode==='blocked'?3844:99999,stone:99999,gold:99999});
   s.build_queue=mode==='active'?[{id:991,building_code:'hall_of_alliance',level_to:2,started_at:new Date(Date.now()-60000).toISOString().slice(0,19).replace('T',' '),finishes_at:new Date(Date.now()+600000).toISOString().slice(0,19).replace('T',' ')}]:[];
   await route.fulfill({response,json});
  });
  await page.goto(app.base,{waitUntil:'networkidle'});await page.locator('[data-mode="login"]').click();await page.locator('[name="username"]').fill('PreviewPlayer');await page.locator('[name="password"]').fill('PreviewFixture!2026');await Promise.all([page.waitForURL('**/city'),page.locator('#auth-submit').click()]);

  async function open(code='hall_of_alliance'){
   const frame=page.frames().find(candidate=>candidate.url().includes('/city/3d'));
   assert(frame,'Real embedded city iframe is present');
   await frame.evaluate(code=>parent.postMessage({type:'conquer:building',code},location.origin),code);
   await page.locator('#game-dialog[open][data-building="'+code+'"]').waitFor();await page.evaluate(()=>document.fonts.ready);
  }
  async function contrast(selector){return page.locator(selector).evaluateAll(elements=>{
   const parse=value=>{const m=value.match(/[\d.]+/g)?.map(Number)||[0,0,0,1];return[m[0],m[1],m[2],m[3]??1];},blend=(top,bottom,alpha=top[3])=>top.slice(0,3).map((v,i)=>v*alpha+bottom[i]*(1-alpha)),lum=c=>{const f=v=>(v/=255)<=.04045?v/12.92:((v+.055)/1.055)**2.4;return.2126*f(c[0])+.7152*f(c[1])+.0722*f(c[2]);};
   return elements.filter(el=>{const r=el.getBoundingClientRect(),s=getComputedStyle(el);return r.width&&r.height&&s.visibility!=='hidden'&&s.display!=='none'&&el.textContent.trim();}).map(el=>{let bg=[255,255,255],chain=[],node=el;while(node){chain.unshift(node);node=node.parentElement;}const gradients=[];for(const item of chain){const style=getComputedStyle(item),image=style.backgroundImage;if(image!=='none')gradients.push({element:item.id||item.className||item.tagName,image});const c=parse(style.backgroundColor);bg=blend(c,bg,c[3]);}const style=getComputedStyle(el),fg=parse(style.color);let opacity=1;for(let node=el;node;node=node.parentElement)opacity*=Number(getComputedStyle(node).opacity)||1;const painted=blend(fg,bg,fg[3]*opacity),l1=lum(painted),l2=lum(bg);return{text:el.textContent.trim().replace(/\s+/g,' '),ratio:(Math.max(l1,l2)+.05)/(Math.min(l1,l2)+.05),color:style.color,background:bg.map(Math.round),opacity,gradients};});
  });}
  async function inspect(size){
   await page.setViewportSize({width:size[0],height:size[1]});mode='blocked';await page.reload({waitUntil:'networkidle'});await open();
   const dialog=page.locator('#game-dialog'),overview=dialog.locator('.levelup-overview'),upgrade=dialog.locator('[data-action="upgrade"]');
   assert.equal((await dialog.locator('.popup-heading h2').innerText()).trim(),'Stufe erhöhen');
   assert.equal((await overview.locator('.levelup-art>span').innerText()).trim(),'Allianzhalle');
   assert.deepEqual((await overview.locator('.levelup-levels').innerText()).split(/\s+/),['Stufe','1','➜','Stufe','2']);
   assert.deepEqual(await overview.locator('.levelup-stat :is(strong,b)').allInnerTexts(),['5','10']);
   assert.equal((await dialog.locator('.building-requirements .research-requirements-heading span').innerText()).trim(),'2 / 2 erfüllt');assert.equal(await dialog.locator('.building-requirements .requirement-met').count(),2);
   const costs=(await dialog.locator('.levelup-resource-row').allInnerTexts()).map(x=>x.trim().replace(/\s+/g,' '));assert.deepEqual(costs,['! Nahrung 1.879/6.800','! Holz 3.844/6.800','✓ Stein 99.999/6.800','✓ Gold 99.999/4.080']);
   assert.equal(await dialog.locator('.levelup-resource-row.is-missing').count(),2);
   assert(await upgrade.isDisabled());assert.equal((await upgrade.innerText()).trim(),'Ausbau auf Stufe 2 starten');await upgrade.scrollIntoViewIfNeeded();
   const reachable=await upgrade.evaluate(el=>{const r=el.getBoundingClientRect();return{left:r.left,right:r.right,top:r.top,bottom:r.bottom,height:r.height,innerWidth,innerHeight};});assert(reachable.left>=0&&reachable.right<=reachable.innerWidth+1&&reachable.top>=0&&reachable.bottom<=reachable.innerHeight+1,'Disabled upgrade remains scroll-reachable at '+size.join('x')+': '+JSON.stringify(reachable));
   const close=await dialog.locator('.dialog-close').evaluate(el=>{const r=el.getBoundingClientRect();return{left:r.left,right:r.right,top:r.top,bottom:r.bottom,height:r.height};});assert(close.left>=0&&close.right<=size[0]+1&&close.top>=0&&close.bottom<=size[1]+1&&close.height>0,'Close/scroll control remains visible at '+size.join('x'));
   const samples=await contrast('.levelup-levels span,.levelup-levels strong,.levelup-stat>span:first-child,.levelup-stat strong,.levelup-stat b,.levelup-resource-row strong,.levelup-resource-values,#game-dialog [data-action="upgrade"]');
   assert(samples.length>=12,'All requested dialog text samples exist');for(const sample of samples){assert.deepEqual(sample.gradients,[],`${size.join('x')} text background chain has an unmeasured gradient: ${sample.text}`);assert(sample.ratio>=4.5,`${size.join('x')} contrast ${sample.ratio.toFixed(2)}: ${sample.text} (${sample.color} on ${sample.background})`);}
   report.push({size:size.join('x'),samples});if(size[0]===390)await page.screenshot({path:path.join(output,'alliance-upgrade-390.png')});
  }
  for(const size of [[1280,800],[390,844],[320,568],[844,390]])await inspect(size);
  for(const scenario of ['affordable','active']){mode=scenario;await page.reload({waitUntil:'networkidle'});await open();if(scenario==='affordable'){const button=page.locator('#game-dialog [data-action="upgrade"]');assert(!await button.isDisabled());assert.equal((await button.innerText()).trim(),'Ausbau auf Stufe 2 starten');}else{assert.equal((await page.locator('#game-dialog .popup-heading h2').textContent()).trim(),'Ausbau läuft');assert.match(await page.locator('#dialog-content').innerText(),/Stufe 2 wird gebaut/);}report.push({scenario});}
  mode='affordable';await page.reload({waitUntil:'networkidle'});await page.locator('#hud-build').click();await page.locator('#game-dialog[open][data-building]').waitFor();assert.equal((await page.locator('#game-dialog .levelup-main-title>span').textContent()).trim(),'Empfohlen');report.push({suggestedBuilding:await page.locator('#game-dialog').getAttribute('data-building')});await page.locator('#game-dialog .dialog-close').click();
  mode='active';await page.reload({waitUntil:'networkidle'});await page.locator('#hud-build').click();await page.locator('#game-dialog[open][data-building="hall_of_alliance"]').waitFor();assert.equal((await page.locator('#game-dialog .popup-heading h2').textContent()).trim(),'Ausbau läuft');report.push({activeBuilding:'hall_of_alliance'});await page.locator('#game-dialog .dialog-close').click();
  mode='affordable';await page.reload({waitUntil:'networkidle'});
  const buildingCodes=await page.evaluate(async()=>Object.keys((await(await fetch('api/game/state')).json()).data.buildings));
  for(const code of buildingCodes){await open(code);const samples=await contrast('.levelup-levels span,.levelup-levels strong,.levelup-stat>span:first-child,.levelup-stat strong,.levelup-stat b,.levelup-resource-row strong,.levelup-resource-values,#game-dialog .levelup-footer button');for(const sample of samples){assert.deepEqual(sample.gradients,[],`${code} text background chain has an unmeasured gradient: ${sample.text}`);assert(sample.ratio>=4.5,`${code} contrast ${sample.ratio.toFixed(2)}: ${sample.text}`);}}
  report.push({buildingDialogs:buildingCodes});
  assert.deepEqual(errors,[],'No browser errors');assert.deepEqual(badAssets,[],'No missing assets');assert.deepEqual(writes,[],'Fixture UI inspection performs no API writes');
  fs.writeFileSync(path.join(output,'building-contrast-report.json'),JSON.stringify(report,null,2));console.log(`PASS building contrast: ${report[0].samples.length} text samples × 4 viewports, affordable and active states; ${output}`);
 }finally{if(browser)await browser.close();if(app.child.exitCode===null){app.child.stdin.end('\n');await new Promise(resolve=>app.child.once('exit',resolve));}}
})().catch(error=>{console.error(error);process.exitCode=1;});

'use strict';
require('./fixtures/browser_locale.cjs')('de'); // This suite asserts the explicit German UI.
// Focused building-dialog contrast regression against a disposable PHP fixture.
// Run: node tests/building_contrast_app.cjs (PLAYWRIGHT_MODULE may point to Playwright).
const fs=require('fs'),path=require('path'),net=require('net'),assert=require('assert');
const {spawn}=require('child_process');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const root=path.resolve(__dirname,'..'),output=process.env.UPGRADE_TEST_OUTPUT||path.join(root,'artifacts','vivid-rollout','research-upgrades');
fs.mkdirSync(output,{recursive:true});

async function fixture(){
 const port=await new Promise(resolve=>{const server=net.createServer();server.listen(0,'127.0.0.1',()=>{const port=server.address().port;server.close(()=>resolve(port));});});
 const child=spawn(process.env.PHP_BINARY||'C:/xampp/php/php.exe',[path.join(root,'tools/preview-feature-fixture.php'),'--port='+port,'--hud','--appearance'],{cwd:root,stdio:['pipe','pipe','pipe'],windowsHide:true});
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
 const app=await fixture();let browser,page,mode='blocked';const errors=[],badAssets=[],writes=[],report=[];
 try{
  await app.ready;browser=await chromium.launch({headless:true,...(process.env.BROWSER_EXECUTABLE_PATH?{executablePath:process.env.BROWSER_EXECUTABLE_PATH}:{})});
  page=await browser.newPage({viewport:{width:1280,height:800}});page.setDefaultTimeout(20000);
  page.on('pageerror',error=>errors.push(error.message));page.on('response',response=>{if(response.url().includes('/assets/')&&response.status()>=400)badAssets.push(response.status()+' '+response.url());});
  page.on('request',request=>{if(request.url().includes('/api/')&&request.method()!=='GET')writes.push(request.method()+' '+request.url());});
  await page.route('**/api/game/state*',async route=>{const response=await route.fetch(),json=await response.json(),s=json.data,b=s.buildings.hall_of_alliance;
   Object.assign(b,{level:1,cost:{food:6800,lumber:6800,stone:6800,gold:4080},seconds:120,requirements:{castle:2,barrack:2},item_requirements:[],rally_capacity:{base:50000,next_base:75000,research_bonus:0.4,total:70000,next_total:105000,levels:[]}});
   Object.assign(s.buildings.castle,{level:2});Object.assign(s.buildings.barrack,{level:2});
   Object.assign(s.city,{food:mode==='blocked'?1879:99999,lumber:mode==='blocked'?3844:99999,stone:99999,gold:99999});
   if(mode==='prerequisites'){b.requirements={barrack:2,castle:4};s.buildings.castle.level=2;}
   if(mode==='complete')b.level=30;
   s.build_queue=mode==='active'?[{id:991,building_code:'hall_of_alliance',level_to:2,started_at:new Date(Date.now()-60000).toISOString().slice(0,19).replace('T',' '),finishes_at:new Date(Date.now()+600000).toISOString().slice(0,19).replace('T',' ')}]:[];
   if(mode.startsWith('queue-')){
    const lowVip=mode==='queue-vip3';s.vip.level=lowVip?3:20;s.vip.building_slots=lowVip?1:2;
    s.build_queue=(mode==='queue-full'?['farm','quarry']:['farm']).map((code,i)=>({id:991+i,building_code:code,level_to:2,started_at:new Date().toISOString(),finishes_at:new Date(Date.now()+600000).toISOString()}));
    if(mode==='queue-items')b.item_requirements=[{name:'Allianzabzeichen',owned:0,count:10,met:false}];
   }
   await route.fulfill({response,json});
  });
  await page.goto(app.base,{waitUntil:'domcontentloaded'});
  const csrf=await page.locator('[name="csrf"]').first().inputValue();
  await page.request.post(app.base+'/auth/local',{form:{csrf,mode:'login',identifier:'PreviewPlayer',password:'PreviewFixture!2026'}});
  await page.goto(app.base+'/city',{waitUntil:'domcontentloaded'});

  let viewSequence=0;
  async function open(code='hall_of_alliance'){
   // A fresh URL makes this one full navigation, even after the previous
   // dialog leaves the address at #city; same-document navigation keeps it open.
   await page.goto(app.base+'/city?visual_check='+(++viewSequence)+'#city',{waitUntil:'domcontentloaded'});
   await page.locator('.painted-village').waitFor();
   await page.locator('#app-start').waitFor({state:'detached'});
   await page.locator('#scene-transition').waitFor({state:'hidden'});
   const building=page.locator('.painted-village-building[data-id="'+code+'"]');
   await building.evaluate(el=>el.scrollIntoView({block:'center',inline:'center'}));
   const position=await building.evaluate(el=>{const r=el.getBoundingClientRect();for(const fy of [.5,.7,.3,.9,.1])for(const fx of [.5,.7,.3,.9,.1]){const x=r.width*fx,y=r.height*fy;if(el.contains(document.elementFromPoint(r.x+x,r.y+y)))return{x,y};}return null;});
   assert(position,'Building reachable by touch: '+code);await building.click({position});
   await page.locator('.painted-building-menu [data-action="building"][data-id="'+code+'"]').click();
   await page.locator('#game-dialog[open][data-building="'+code+'"]').waitFor();await page.evaluate(()=>document.fonts.ready);
  }
  async function contrast(selector){return page.locator(selector).evaluateAll(elements=>{
   const canvas=document.createElement('canvas');canvas.width=canvas.height=1;
   const ctx=canvas.getContext('2d',{willReadFrequently:true}),colours=new Map();
   const parse=value=>{
    if(!CSS.supports('color',value))throw new Error('Unsupported contrast colour: '+value);
    if(!colours.has(value)){ctx.clearRect(0,0,1,1);ctx.fillStyle=value;ctx.fillRect(0,0,1,1);const [r,g,b,a]=ctx.getImageData(0,0,1,1).data;colours.set(value,[r,g,b,a/255]);}
    return colours.get(value);
   };
   const over=(top,bottom)=>{const alpha=top[3]+bottom[3]*(1-top[3]);return alpha?[...top.slice(0,3).map((v,i)=>(v*top[3]+bottom[i]*bottom[3]*(1-top[3]))/alpha),alpha]:[0,0,0,0];};
   const lum=c=>{const f=v=>(v/=255)<=.04045?v/12.92:((v+.055)/1.055)**2.4;return.2126*f(c[0])+.7152*f(c[1])+.0722*f(c[2]);};
   const unique=values=>[...new Map(values.map(value=>[value.flat().map(n=>n.toFixed(4)).join(','),value])).values()];
   const layers=value=>{const result=[];let depth=0,start=0;for(let i=0;i<value.length;i++){if(value[i]==='(')depth++;else if(value[i]===')')depth--;else if(value[i]===','&&depth===0){result.push(value.slice(start,i).trim());start=i+1;}}result.push(value.slice(start).trim());return result;};
   return elements.filter(el=>{const r=el.getBoundingClientRect(),s=getComputedStyle(el);return r.width&&r.height&&s.visibility!=='hidden'&&s.display!=='none'&&el.textContent.trim();}).map(el=>{
    const style=getComputedStyle(el),gradients=[],unmeasured=[];let pairs=[[parse(style.color),[0,0,0,0]]],opacity=1;
    for(let node=el;node;node=node.parentElement){
     const css=getComputedStyle(node),nodeOpacity=Number(css.opacity);opacity*=nodeOpacity;
     if(pairs.some(pair=>pair.some(colour=>colour[3]<.999))){
      let surfaces=[parse(css.backgroundColor)];
      // CSS lists the uppermost image first. Build each possible stop surface
      // from the bottom up, compositing translucent stops over lower layers.
      for(const layer of layers(css.backgroundImage).reverse()){
       if(layer==='none')continue;
       const stops=/^(?:repeating-)?(?:linear|radial)-gradient\(/.test(layer)?layer.match(/(?:rgba?|color|oklab|oklch|lab|lch)\([^)]+\)/g):null;
       if(!stops||stops.length<2){unmeasured.push({element:node.id||node.className||node.tagName,image:layer});continue;}
       gradients.push({element:node.id||node.className||node.tagName,image:layer,stops});
       surfaces=unique(stops.flatMap(stop=>surfaces.map(surface=>over(parse(stop),surface))));
      }
      if(css.backgroundBlendMode.split(',').some(mode=>mode.trim()!=='normal'))unmeasured.push({element:node.id||node.tagName,blendMode:css.backgroundBlendMode});
      pairs=unique(pairs.flatMap(pair=>surfaces.map(surface=>pair.map(colour=>over(colour,surface)))));
     }
     // Opacity applies to the composed text/background group, not only ink.
     pairs=pairs.map(pair=>pair.map(colour=>[...colour.slice(0,3),colour[3]*nodeOpacity]));
    }
    const measured=pairs.map(pair=>pair.map(colour=>over(colour,[255,255,255,1]))).map(([foreground,background])=>{const a=lum(foreground),b=lum(background);return{ratio:(Math.max(a,b)+.05)/(Math.min(a,b)+.05),foreground,background};}).sort((a,b)=>a.ratio-b.ratio);
    const worst=measured[0];
    return{text:el.textContent.trim().replace(/\s+/g,' '),ratio:worst.ratio,color:style.color,background:worst.background.slice(0,3).map(Math.round),opacity,gradients,unmeasured,surfaces:measured.length};
   });
  });}
  // Prove that gradients are evaluated rather than waived: the light stop,
  // a translucent upper layer and group opacity must each reveal low contrast.
  await page.evaluate(()=>{
   const oracle=document.createElement('div');oracle.id='building-contrast-oracle';oracle.style.cssText='position:fixed;inset:0;z-index:999999;background:white';
   oracle.innerHTML='<div data-case="green" style="color:#203b18;background:linear-gradient(#b6e084,#82c34a,#72b641)">Green action</div><div data-case="stop" style="color:white;background:linear-gradient(black,#999)">Light stop</div><div data-case="layers" style="color:white;background:linear-gradient(#ffffff80,#ffffff80),linear-gradient(black,black)">Layered action</div><div data-case="opacity" style="color:white;background:linear-gradient(black,black);opacity:.5">Faded group</div>';
   document.body.append(oracle);
  });
  const oracles=await contrast('#building-contrast-oracle>div');
  assert(oracles[0].ratio>=4.5&&oracles[0].gradients.length===1,'Green gradient measures every stop');
  assert(oracles[1].ratio<4.5&&oracles[1].surfaces===2,'Light gradient stop fails contrast');
  assert(oracles[2].ratio<4.5&&oracles[2].gradients.length===2,'Translucent upper gradient composites over the lower gradient');
  assert(oracles[3].ratio<4.5,'Group opacity composites both text and background');
  for(const oracle of oracles)assert.deepEqual(oracle.unmeasured,[],'Oracle background fully measured');
  await page.locator('#building-contrast-oracle').evaluate(el=>el.remove());
  async function inspect(size){
   await page.setViewportSize({width:size[0],height:size[1]});mode='blocked';await open();
   const dialog=page.locator('#game-dialog'),overview=dialog.locator('.levelup-overview'),upgrade=dialog.locator('[data-action="upgrade"]');
   assert.equal((await dialog.locator('.popup-heading h2').innerText()).trim(),'Stufe erhöhen');
   assert.equal((await overview.locator('.levelup-art>span').innerText()).trim(),'Allianzhalle');
   assert.deepEqual((await overview.locator('.levelup-levels').innerText()).split(/\s+/),['Stufe','1','➜','Stufe','2']);
   assert.deepEqual(await overview.locator('.levelup-stat :is(strong,b)').allInnerTexts(),['50.000','75.000','70.000','105.000']);
   assert.equal((await dialog.locator('.building-requirements .research-requirements-heading span').innerText()).trim(),'2 / 2 erfüllt');assert.equal(await dialog.locator('.building-requirements .requirement-met').count(),2);
   const costs=(await dialog.locator('.levelup-resource-row').allInnerTexts()).map(x=>x.trim().replace(/\s+/g,' '));assert.deepEqual(costs,['! Nahrung 1.879/6.800 Es fehlen 4.921','! Holz 3.844/6.800 Es fehlen 2.956','✓ Stein 99.999/6.800 Vorhanden','✓ Gold 99.999/4.080 Vorhanden']);
   assert.equal(await dialog.locator('.levelup-resource-row.is-missing').count(),2);
   assert(await upgrade.isDisabled());assert.equal((await upgrade.innerText()).trim(),'Ausbau auf Stufe 2 starten');await upgrade.scrollIntoViewIfNeeded();
   const reachable=await upgrade.evaluate(el=>{const r=el.getBoundingClientRect();return{left:r.left,right:r.right,top:r.top,bottom:r.bottom,height:r.height,innerWidth,innerHeight};});assert(reachable.left>=0&&reachable.right<=reachable.innerWidth+1&&reachable.top>=0&&reachable.bottom<=reachable.innerHeight+1,'Disabled upgrade remains scroll-reachable at '+size.join('x')+': '+JSON.stringify(reachable));
   const close=await dialog.locator('.dialog-close:visible,.mobile-page-back:visible').first().evaluate(el=>{const r=el.getBoundingClientRect();return{left:r.left,right:r.right,top:r.top,bottom:r.bottom,height:r.height};});assert(close.left>=0&&close.right<=size[0]+1&&close.top>=0&&close.bottom<=size[1]+1&&close.height>0,'Close/scroll control remains visible at '+size.join('x'));
   const samples=await contrast('#game-dialog .popup-heading h2,.levelup-levels span,.levelup-levels strong,.levelup-stat>span:first-child,.levelup-stat strong,.levelup-stat b,.levelup-resource-row strong,.levelup-resource-values,.requirements-overview strong,.requirement-status,.requirement-levels b,.requirement-card-footer strong,#game-dialog [data-action="upgrade"]');
   assert(samples.length>=12,'All requested dialog text samples exist');for(const sample of samples){assert.deepEqual(sample.unmeasured,[],`${size.join('x')} text background chain has an unmeasured gradient: ${sample.text}`);assert(sample.ratio>=4.5,`${size.join('x')} contrast ${sample.ratio.toFixed(2)}: ${sample.text} (${sample.color} on ${sample.background})`);}
   const scroll=dialog.locator('.levelup-scroll');assert.equal(await scroll.count(),1,'Upgrade content shares one scroll area');
   assert(await overview.evaluate(el=>el.querySelector('.levelup-stats').getBoundingClientRect().bottom<=el.getBoundingClientRect().bottom+1),'Improvement values stay inside the overview surface');
   await scroll.evaluate(el=>el.scrollTop=el.scrollHeight);assert(await upgrade.isVisible(),'Upgrade stays visible while requirements scroll');
   const footer=await dialog.locator('.levelup-footer').boundingBox();assert(footer.y+footer.height<=size[1]+1,'Action bar stays on screen');
   assert(await upgrade.evaluate(el=>el.getBoundingClientRect().height>=44),'Upgrade touch target is at least 44px');
   await scroll.evaluate(el=>el.scrollTop=0);
   report.push({size:size.join('x'),samples});await page.screenshot({path:path.join(output,'alliance-upgrade-'+size.join('x')+'.png')});
  }
  for(const size of [[1280,800],[390,844],[320,568],[844,390],[568,320]])await inspect(size);
  for(const size of [[1280,800],[390,844],[320,568],[844,390],[568,320]]){
   await page.setViewportSize({width:size[0],height:size[1]});mode='prerequisites';await open();
   const cards=page.locator('.building-requirements .requirement-card');
   assert.equal(await cards.first().getAttribute('data-id'),'castle','Unmet upgrade precedes the fulfilled barracks');
   assert.match(await cards.first().innerText(),/Aktuell\s+Stufe 2\s+→\s+Benötigt\s+Stufe 4/);
   assert.match(await cards.first().innerText(),/Noch 2 Stufen nötig/);
   assert.equal(await cards.first().locator('[role=progressbar]').getAttribute('aria-valuenow'),'2');
   assert.equal(await cards.first().locator('[role=progressbar]').getAttribute('aria-valuemax'),'4');
   assert.equal(await cards.last().locator('[role=progressbar]').getAttribute('aria-valuenow'),'2');
   assert.match(await page.locator('.requirements-overview').innerText(),/Noch 1 Voraussetzung offen/);
   assert(await page.locator('[data-action=upgrade]').isDisabled());
   await cards.first().scrollIntoViewIfNeeded();
   assert(await cards.evaluateAll(elements=>elements.every(el=>el.scrollWidth<=el.clientWidth+1)),'Requirement copy fits each card');
   const samples=await contrast('.requirement-status,.requirement-levels b,.requirement-card-footer strong,.requirements-overview strong');
   for(const sample of samples){assert.deepEqual(sample.unmeasured,[],'Prerequisite background is measured: '+sample.text);assert(sample.ratio>=4.5,'Prerequisite contrast: '+sample.text);}
   await page.screenshot({path:path.join(output,'building-prerequisites-'+size.join('x')+'.png')});
   await cards.first().click();assert.equal(await page.locator('#game-dialog').getAttribute('data-building'),'castle','Requirement opens its actual upgrade menu');
  }
  for(const size of [[1280,800],[390,844],[844,390]]){
   await page.setViewportSize({width:size[0],height:size[1]});
   for(const scenario of ['queue-free','queue-full','queue-vip3','queue-items']){
    mode=scenario;await open();
    assert.equal(await page.locator('#game-dialog .levelup-warning').count(),['queue-full','queue-vip3'].includes(scenario)?1:0,scenario+' capacity warning');
    assert.equal(await page.locator('#game-dialog [data-action="upgrade"]').isDisabled(),scenario!=='queue-free',scenario+' upgrade eligibility');
    if(scenario==='queue-items')assert.equal(await page.locator('#game-dialog .levelup-item-row.is-missing').count(),1);
    report.push({size:size.join('x'),scenario});
   }
  }
  for(const scenario of ['affordable','active']){mode=scenario;await open();if(scenario==='affordable'){const button=page.locator('#game-dialog [data-action="upgrade"]');assert(!await button.isDisabled());assert.equal((await button.innerText()).trim(),'Ausbau auf Stufe 2 starten');}else{assert.equal((await page.locator('#game-dialog .popup-heading h2').textContent()).trim(),'Ausbau läuft');assert.match(await page.locator('#dialog-content').innerText(),/Stufe 2 wird gebaut/);}report.push({scenario});}
  mode='affordable';await page.goto(app.base+'/city?visual_check='+(++viewSequence)+'#city',{waitUntil:'domcontentloaded'});await page.locator('#hud-build').click();await page.locator('#game-dialog[open][data-building]').waitFor();assert.equal((await page.locator('#game-dialog .levelup-main-title>span').textContent()).trim(),'Empfohlen');report.push({suggestedBuilding:await page.locator('#game-dialog').getAttribute('data-building')});await page.locator("#game-dialog .dialog-close:visible, #game-dialog .mobile-page-back:visible").first().click();await page.locator('#app-start').waitFor({state:'detached'});
  mode='active';await page.goto(app.base+'/city?visual_check='+(++viewSequence)+'#city',{waitUntil:'domcontentloaded'});await page.locator('#hud-build').click();await page.locator('#game-dialog[open][data-building="hall_of_alliance"]').waitFor();assert.equal((await page.locator('#game-dialog .popup-heading h2').textContent()).trim(),'Ausbau läuft');report.push({activeBuilding:'hall_of_alliance'});await page.locator("#game-dialog .dialog-close:visible, #game-dialog .mobile-page-back:visible").first().click();await page.locator('#app-start').waitFor({state:'detached'});
  mode='affordable';await page.locator('#app-start').waitFor({state:'detached'});
  const buildingCodes=await page.evaluate(async()=>Object.keys((await(await fetch('api/game/state')).json()).data.buildings));
  for(const code of buildingCodes){await open(code);const samples=await contrast('.levelup-levels span,.levelup-levels strong,.levelup-stat>span:first-child,.levelup-stat strong,.levelup-stat b,.levelup-resource-row strong,.levelup-resource-values,#game-dialog .levelup-footer button');for(const sample of samples){assert.deepEqual(sample.unmeasured,[],`${code} text background chain has an unmeasured gradient: ${sample.text}`);assert(sample.ratio>=4.5,`${code} contrast ${sample.ratio.toFixed(2)}: ${sample.text}`);}}
  report.push({buildingDialogs:buildingCodes});
  mode='complete';await open();assert(await page.locator('#game-dialog [data-action="upgrade"]').isDisabled(),'Maximum-level buildings cannot upgrade');assert.equal(await page.locator('#game-dialog .levelup-stat b').count(),0,'Maximum-level building shows no next progression value');assert((await page.locator('#game-dialog .levelup-levels').innerText()).includes('Maximum'));report.push({scenario:'complete'});
  assert.deepEqual(errors,[],'No browser errors');assert.deepEqual(badAssets,[],'No missing assets');assert.deepEqual(writes,[],'Fixture UI inspection performs no API writes');
  fs.writeFileSync(path.join(output,'building-contrast-report.json'),JSON.stringify(report,null,2));console.log(`PASS building contrast: ${report[0].samples.length} text samples × 5 viewports, affordable and active states; ${output}`);
 }catch(error){console.error('Building contrast failure before cleanup:',error);if(page&&!page.isClosed()){await page.screenshot({path:path.join(output,'failure.png')}).catch(()=>{});fs.writeFileSync(path.join(output,'failure.html'),await page.content().catch(()=>''));}throw error;
 }finally{if(page)await page.unrouteAll({behavior:'ignoreErrors'});if(browser)await browser.close();if(app.child.exitCode===null){app.child.stdin.end('\n');await new Promise(resolve=>app.child.once('exit',resolve));}}
})().catch(error=>{console.error(error);process.exitCode=1;});

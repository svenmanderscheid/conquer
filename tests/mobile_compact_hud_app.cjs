'use strict';
// Actual city/world with the disposable HUD/chat fixture, never a player account.
const assert=require('assert/strict'),fs=require('fs'),path=require('path'),os=require('os'),net=require('net'),{spawn}=require('child_process');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const root=path.resolve(__dirname,'..'),phase=process.env.MOBILE_HUD_PHASE||'after';
const output=process.env.MOBILE_HUD_OUTPUT?path.resolve(root,process.env.MOBILE_HUD_OUTPUT):fs.mkdtempSync(path.join(os.tmpdir(),'conquer-mobile-hud-'));
fs.mkdirSync(output,{recursive:true});
let base=process.env.MOBILE_HUD_FIXTURE_URL;
async function startFixture(){
 const server=net.createServer();await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));const port=server.address().port;await new Promise(resolve=>server.close(resolve));base='http://127.0.0.1:'+port;
 const fixture=spawn(process.env.PHP_BINARY||'C:/xampp/php/php.exe',['tools/preview-feature-fixture.php','--port='+port,'--hud','--chat'],{cwd:root,stdio:['pipe','pipe','pipe'],windowsHide:true});
 let log='';fixture.stdout.on('data',data=>log+=data);fixture.stderr.on('data',data=>log+=data);
 await new Promise((resolve,reject)=>{const timer=setTimeout(()=>{clearInterval(poll);reject(Error(log||'Preview fixture timeout'));},60000),poll=setInterval(()=>{if(log.includes('Synthetic preview ready')){clearTimeout(timer);clearInterval(poll);resolve();}else if(fixture.exitCode!==null){clearTimeout(timer);clearInterval(poll);reject(Error(log));}},100);fixture.on('error',reject);});return fixture;
}
function measureHud(){
 const visible=node=>node&&node.getClientRects().length&&getComputedStyle(node).visibility!=='hidden'&&getComputedStyle(node).display!=='none';
 const rect=node=>{const r=node.getBoundingClientRect();return {x:r.x,y:r.y,right:r.right,bottom:r.bottom,width:r.width,height:r.height};};
 const selectors=['.topbar','.hud-profile','#account-button','.hud-power','#hud-energy','#hud-gems','#resources','#navigation','#world-chat','#hud-left-tools','.hud-right-tools','.map-overlay-search-toggle','.map-overlay-coordinate-toggle'];
 const boxes=Object.fromEntries(selectors.map(selector=>[selector,document.querySelector(selector)]).filter(([,node])=>visible(node)).map(([selector,node])=>[selector,rect(node)]));
 const upper=Math.max(0,...['.hud-profile','#resources','#hud-gems'].map(selector=>boxes[selector]?.bottom||0));
 const lower=Math.min(innerHeight,...['#world-chat','#navigation'].map(selector=>boxes[selector]?.y??innerHeight));
 const controls=[...document.querySelectorAll('.topbar button,.hud-edge-tools button,#navigation button,#world-chat button,.map-overlay-search-toggle,.map-overlay-coordinate-toggle button')].filter(visible).map(node=>{const box=rect(node),hit=document.elementFromPoint(box.x+box.width/2,box.y+box.height/2);return {id:node.id||node.dataset.id||node.className,label:node.getAttribute('aria-label')||node.textContent.trim(),reachable:node===hit||node.contains(hit),hit:hit?.outerHTML.slice(0,350),...box};});
 return {width:innerWidth,height:innerHeight,mode:document.body.className,boxes,controls,centerHeight:lower-upper,centerFraction:(lower-upper)/innerHeight};
}
function measureNumbers(){return [...document.querySelectorAll('.hud-compact-power strong,.hud-energy-compact,.hud-gems-compact,#resources .hud-value-compact')].filter(node=>node.getClientRects().length).map(node=>{const text=node.getBoundingClientRect(),button=node.closest('button').getBoundingClientRect(),value=node.closest('#resources strong');return {text:node.textContent,...(value?{width:value.clientWidth,contentWidth:value.scrollWidth}:{}),fits:text.left>=button.left&&text.right<=button.right&&text.top>=button.top&&text.bottom<=button.bottom&&(!value||value.scrollWidth<=value.clientWidth+1)};});}
(async()=>{
 const fixture=base?null:await startFixture();assert(/^http:\/\/127\.0\.0\.1:\d+$/.test(base),'A disposable local preview is required');
 const browser=await chromium.launch({headless:true,executablePath:process.env.BROWSER_EXECUTABLE_PATH||'C:/Program Files/Google/Chrome/Application/chrome.exe'});
 let page;const results=[];
 try{
  page=await browser.newPage({viewport:{width:390,height:844},hasTouch:true,locale:'de-DE'});page.setDefaultTimeout(20000);page.setDefaultNavigationTimeout(45000);
  const errors=[],missingAssets=[];page.on('pageerror',error=>errors.push(error.message));page.on('response',response=>{if(response.status()>=400&&new URL(response.url()).pathname.startsWith('/assets/'))missingAssets.push(response.url());});
  let stressProfile=false;
  await page.route('**/api/kingdom/state*',async route=>{const response=await route.fetch(),json=await response.json();if(stressProfile&&json.ok)Object.assign(json.data.profile,{display_name:'Commander Alexandria Nightingale',power:123456789,gems:77777777,action_points:123456,action_points_max:200000});await route.fulfill({response,json});});
  await page.route('**/api/game/state*',async route=>{
   const response=await route.fetch(),json=await response.json();
   if(stressProfile&&json.ok){Object.assign(json.data.city,{food:9999,lumber:99999,stone:999949,gold:987654321});json.data.active_effects=[{id:'mobile-bonus',kind:'bonus',source:'charm',grade:'epic',stat_category:'construction',bonus_pct:5,activated_at:'2026-01-01 00:00:00',expires_at:'2030-01-01 00:00:00'},{id:'mobile-debuff',kind:'debuff',source:'buff',grade:'normal',stat_category:'research',bonus_pct:-15,expires_at:'2030-01-01 00:00:00'}];}
   await route.fulfill({response,json});
  });
  await page.goto(base+'/?zugang=login',{waitUntil:'domcontentloaded'});await page.locator('[name=identifier], [name=username]').fill('PreviewPlayer');await page.locator('[name=password]').fill('PreviewFixture!2026');
  await Promise.all([page.waitForURL('**/city'),page.locator('form[action$="/auth/local"] button[type=submit]').click()]);
  const closePanels=async()=>{
   for(let n=0;n<4;n++){
    const selector=await page.locator('#game-dialog').evaluate(node=>node.open)?'#game-dialog':await page.locator('#panel-dialog').evaluate(node=>node.open)?'#panel-dialog':null;
    if(!selector)return;
    await page.locator(selector+' .dialog-close:visible, '+selector+' .panel-close:visible, '+selector+' .mobile-page-back:visible').first().tap();
    await page.waitForTimeout(100);
   }
   assert.equal(await page.locator('dialog[open]').count(),0,'Back returns to the playfield');
  };
  const opensPanel=async selector=>{await page.locator(selector).tap();await page.locator('dialog[open]').first().waitFor();await closePanels();};
  const sizes=process.env.MOBILE_HUD_SIZES?process.env.MOBILE_HUD_SIZES.split(',').map(value=>value.split('x').map(Number)):[[390,844],[320,568],[844,390],[568,320],[480,320],[1280,800]];
  for(const [width,height] of sizes)for(const mode of ['city','world']){
   await page.setViewportSize({width,height});await page.goto(base+'/city#'+mode,{waitUntil:'domcontentloaded'});
   await page.locator(mode==='city'?'.painted-village-scene':'.atlas-viewport').first().waitFor();await page.locator('#resources .resource').first().waitFor();await page.locator('#world-chat:not([hidden])').waitFor();await page.waitForFunction(()=>!document.querySelector('.scene-transition.is-active'));await page.locator('#app-start').waitFor({state:'detached'});
   await page.evaluate(()=>document.fonts.ready);await page.screenshot({path:path.join(output,`${phase}-${width}x${height}-${mode}.png`)});
   const result={scene:mode,...await page.evaluate(measureHud)};results.push(result);console.log(`${phase} ${width}x${height} ${mode}: center ${result.centerHeight.toFixed(1)}px (${(result.centerFraction*100).toFixed(1)}%)`);
   if(phase==='before')continue;
   const compact=width<=700||(width<=1100&&height<=520),overlap=(a,b)=>a.x<b.right-1&&a.right>b.x+1&&a.y<b.bottom-1&&a.bottom>b.y+1;
   assert.equal(await page.locator('#navigation').count(),1,'One shared city/world navigation');
   assert.equal(await page.locator('#navigation button:visible').count(),compact?5:7,'Five clear mobile destinations; seven on desktop');
   for(const item of result.controls){
    assert(item.width>=43.5&&item.height>=43.5,'44px touch target: '+JSON.stringify(item));
    assert(item.x>=-.5&&item.y>=-.5&&item.right<=width+.5&&item.bottom<=height+.5,'Control within viewport: '+JSON.stringify(item));
    assert(item.label,'Accessible control description: '+item.id);assert(item.reachable,'Unobstructed touch target: '+JSON.stringify(item));
   }
   for(let i=0;i<result.controls.length;i++)for(let j=i+1;j<result.controls.length;j++)assert(!overlap(result.controls[i],result.controls[j]),'HUD controls overlap: '+result.controls[i].id+' / '+result.controls[j].id);
   if(compact){
    const mail=result.controls.find(control=>control.id==='hud-mail'),chat=result.boxes['#world-chat'];
    assert(mail&&mail.bottom<=chat.y&&chat.y-mail.bottom<=12,'Mail sits directly above the chat without covering it');
    assert(result.boxes['.hud-profile'].height<=48,'Mobile profile uses one compact touch row');
    assert(result.boxes['#resources'].bottom<=102,'Profile and resources leave the field below 102px');
    assert(result.boxes['#world-chat'].height<=45,'Collapsed mobile chat uses one touch row');
    assert.equal(await page.locator('.hud-power').isVisible(),false,'Power is incorporated in the mobile account action');
    assert.equal(await page.locator('.hud-compact-power').isVisible(),true,'Power remains visible on mobile');
    assert.equal(await page.locator('#hud-report').isVisible(),true,'Bug reporting remains available on the main screen');
    if(['idle','locked'].includes(await page.locator('#hud-build-second').getAttribute('data-job-state')))assert.equal(await page.locator('#hud-build-second').isVisible(),false,'Inactive secondary build slot does not occupy the field');
    const baseline={'390x844':537,'320x568':261,'844x390':155,'568x320':86}[width+'x'+height];
    if(baseline!==undefined)assert(result.centerHeight>=baseline+(height>width?70:25),'The central playfield is substantially taller than before');
    const nav=await page.locator('#navigation button').evaluateAll(nodes=>nodes.map(node=>node.getBoundingClientRect().height));assert(nav.every(value=>value<=56),'Mobile navigation has a flat, compact height');
   }else{
    assert.equal(await page.locator('.hud-power').isVisible(),true,'Desktop retains the expanded profile');
    assert.equal(await page.locator('.hud-compact-power').isVisible(),false,'Desktop has no duplicate power value');
    const scale=width>=1101&&height>=600?1.15:1;
    assert(Math.abs(result.boxes['#navigation'].width-Math.min(720*scale,width-12*scale))<2,'Desktop navigation uses the larger interface size');
    assert(result.centerHeight>=height-320,'The larger desktop interface preserves the central playfield');
   }
   // Reach the complete views with actual taps. No reward, purchase or production action is triggered.
   for(const selector of ['#account-button','#hud-energy','#hud-vip-button','#lord-talent-button','#hud-gems'])await opensPanel(selector);
   for(const resource of ['food','lumber','stone','gold'])await opensPanel('#resources [data-id="'+resource+'"]');
   await opensPanel('#hud-report');
   if(compact)await opensPanel('#hud-mail');
   if(compact){await page.locator('#navigation [data-id="shop"]').tap();await page.locator('#panel-dialog[data-panel="market"]').waitFor();await closePanels();}
   await page.locator('#hud-menu').tap();await page.locator('.menu-groups').waitFor();
   assert.equal(await page.locator('.menu-groups [data-id="market"]').count(),1,'Market remains reachable from the menu');
   assert.equal(await page.locator('.menu-groups [data-action="bug-report-open"], .menu-groups [data-id="bugreport"]').count(),0,'Reporting is only available on the main screen');
   assert.equal(await page.locator('.menu-groups [data-action="vip-open"]').count(),1,'VIP and the second builder remain available');
   if(width===390&&mode==='world')await page.screenshot({path:path.join(output,'after-390x844-menu.png')});await closePanels();
   await page.locator('.world-chat-preview').tap();await page.locator('#world-chat-body').waitFor();
   assert.equal(await page.locator('#world-chat-message').isVisible(),true,'Full chat composer remains reachable');
   assert.equal(await page.locator('[data-chat-channel="alliance"]').isVisible(),true,'Alliance chat remains available');
   if(width===390&&mode==='world')await page.screenshot({path:path.join(output,'after-390x844-chat.png')});
   await page.locator('[data-chat-close]:visible').first().tap();await page.locator('.world-chat-preview').waitFor();
   if(mode==='world'){
    await page.locator('.map-overlay-search-toggle').tap();await page.locator('#atlas-search-panel').waitFor();await page.locator('#atlas-search-panel [data-atlas="clear"]').tap();
    await page.locator('.map-overlay-navigation-toggle').tap();await page.locator('#atlas-navigation-panel').waitFor();await page.locator('#atlas-navigation-panel [data-atlas="clear"]').tap();
   }
   assert.equal(await page.locator('dialog[open]').count(),0,'Every tested entry returns to the field');
   assert(await page.locator('body').evaluate((node,scene)=>node.classList.contains(scene+'-mode'),mode),'Returning preserves the selected city/world scene');
  }
  if(phase!=='before'){
   stressProfile=true;
   for(const [width,height] of [[320,568],[390,844],[480,320],[568,320],[844,390]]){
    await page.setViewportSize({width,height});await page.goto(base+'/city#city');await page.reload();await page.locator('.painted-village-scene').waitFor();await page.waitForFunction(()=>!document.querySelector('.scene-transition.is-active'));
    await page.waitForFunction(()=>document.querySelector('#player-hud-name')?.textContent==='Commander Alexandria Nightingale');await page.locator('#hud-bonuses').waitFor();await page.locator('#hud-debuffs').waitFor();await page.locator('#app-start').waitFor({state:'detached'});
    await page.screenshot({path:path.join(output,`after-${width}x${height}-long-profile-effects.png`)});
    const stress=await page.evaluate(measureHud);
    for(const item of stress.controls){assert(item.width>=43.5&&item.height>=43.5&&item.x>=-.5&&item.right<=width+.5,'Large values preserve touch controls: '+JSON.stringify(item));assert(item.reachable,'Large values preserve hit target: '+JSON.stringify(item));}
    const numbers=await page.evaluate(measureNumbers);assert(numbers.length>=7&&numbers.every(value=>value.fits),'Exact 9999 and compact large values fit: '+JSON.stringify({width,height,numbers}));
    assert.match(await page.locator('#account-button').getAttribute('aria-label'),/123,456,789/,'Accessible profile retains exact power');
    assert.match(await page.locator('#hud-gems').getAttribute('aria-label'),/77,777,777/,'Accessible currency retains exact balance');
    assert.match(await page.locator('#hud-energy').getAttribute('aria-label'),/123,456.*200,000/,'Accessible energy retains its maximum');
    assert.match(await page.locator('#resources [data-id="food"]').getAttribute('aria-label'),/9,999/,'Accessible resources retain exact totals');
    for(const id of ['hud-bonuses','hud-debuffs']){
     await page.locator('#'+id).tap();await page.locator('#active-effects-drawer').waitFor();
     const drawer=await page.locator('#active-effects-drawer').evaluate(node=>{const r=node.getBoundingClientRect(),hit=document.elementFromPoint(r.x+r.width/2,r.y+r.height/2);return {inside:r.x>=0&&r.y>=0&&r.right<=innerWidth&&r.bottom<=innerHeight,reachable:node===hit||node.contains(hit)};});
     assert(drawer.inside&&drawer.reachable,'Effects drawer fits the compact HUD: '+JSON.stringify({width,height,id,drawer}));await page.locator('#'+id).tap();await page.locator('#active-effects-drawer').waitFor({state:'hidden'});
    }
   }
   for(const locale of ['de','fr']){
    await page.setViewportSize({width:320,height:568});await Promise.all([page.waitForNavigation({waitUntil:'domcontentloaded'}),page.evaluate(locale=>ConquerLocale.setLocale(locale),locale)]);await page.locator('.painted-village-scene').waitFor();await page.locator('#hud-bonuses').waitFor();
    await page.waitForFunction(locale=>document.documentElement.lang===locale,locale);await page.locator('#app-start').waitFor({state:'detached'});
    const numbers=await page.evaluate(measureNumbers);
    assert(numbers.length>=7,'Localized compact values are present');assert(numbers.every(value=>value.fits),'Localized values fit: '+JSON.stringify({locale,numbers}));
    const localized=await page.evaluate(measureHud);for(const item of localized.controls)assert(item.reachable,'Localized touch control reachable: '+JSON.stringify({locale,item}));
    assert.equal(await page.locator('#player-hud-name').innerText(),'Commander Alexandria Nightingale','Player name is never translated');
    await page.screenshot({path:path.join(output,`after-320x568-large-values-${locale}.png`)});
   }
  }
  assert.deepEqual(errors,[],'No browser exceptions');assert.deepEqual(missingAssets,[],'All requested assets loaded');
  fs.writeFileSync(path.join(output,phase+'-geometry.json'),JSON.stringify(results,null,2));console.log('PASS HUD capture '+output);
 }catch(error){if(page){await page.screenshot({path:path.join(output,phase+'-failure.png')}).catch(()=>{});fs.writeFileSync(path.join(output,phase+'-failure.json'),JSON.stringify({message:error.message,url:page.url(),geometry:await page.evaluate(measureHud).catch(()=>null),completed:results},null,2));}throw error;}
 finally{if(page)await page.unrouteAll({behavior:'ignoreErrors'});await browser.close();if(fixture&&fixture.exitCode===null){fixture.stdin.write('\n');await new Promise(resolve=>fixture.exitCode!==null?resolve():fixture.once('exit',resolve));}}
})().catch(error=>{console.error(error);console.error('Screenshots: '+output);process.exitCode=1;});

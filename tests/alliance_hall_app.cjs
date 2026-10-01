'use strict';
// Real main app and disposable database; read-response scenarios never alter game data.
const fs=require('node:fs'),path=require('node:path'),net=require('node:net'),assert=require('node:assert/strict');
const {spawn}=require('node:child_process');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const root=path.resolve(__dirname,'..'),output=process.env.ALLIANCE_HALL_TEST_OUTPUT||path.join(root,'artifacts','alliance-hall');
fs.mkdirSync(output,{recursive:true});
async function fixture(){
 const port=await new Promise(resolve=>{const server=net.createServer();server.listen(0,'127.0.0.1',()=>{const port=server.address().port;server.close(()=>resolve(port));});});
 const child=spawn(process.env.PHP_BINARY||'C:/xampp/php/php.exe',[path.join(root,'tools/preview-feature-fixture.php'),'--port='+port,'--hud','--appearance'],{cwd:root,stdio:['pipe','pipe','pipe'],windowsHide:true});
 let log='';const ready=new Promise((resolve,reject)=>{const timer=setTimeout(()=>reject(Error('Fixture timeout: '+log)),60000);child.stdout.on('data',part=>{log+=part;if(log.includes('Synthetic preview ready')){clearTimeout(timer);resolve();}});child.stderr.on('data',part=>log+=part);child.once('error',reject);child.once('exit',code=>{clearTimeout(timer);reject(Error('Fixture stopped '+code+': '+log));});});
 return{child,ready,base:'http://127.0.0.1:'+port};
}
(async()=>{
 const app=await fixture(),errors=[],missingAssets=[],writes=[],report=[];let browser,page,mode='actual',sequence=0;
 try{
  await app.ready;browser=await chromium.launch({headless:true,...(process.env.BROWSER_EXECUTABLE_PATH?{executablePath:process.env.BROWSER_EXECUTABLE_PATH}:{})});
  page=await browser.newPage({viewport:{width:1280,height:800},locale:'de-DE',hasTouch:true});page.setDefaultTimeout(20000);
  page.on('pageerror',e=>errors.push(e.message));
  page.on('response',r=>{if(r.url().includes('/assets/')&&r.status()>=400)missingAssets.push(r.status()+' '+r.url());});
  page.on('request',r=>{if(r.url().includes('/api/')&&r.method()!=='GET')writes.push(r.method()+' '+r.url());});
  await page.goto(app.base,{waitUntil:'networkidle'});
  const csrf=await page.locator('[name="csrf"]').first().inputValue();
  const login=await page.request.post(app.base+'/auth/local',{form:{csrf,mode:'login',identifier:'PreviewPlayer',password:'PreviewFixture!2026'}});assert(login.ok());
  await page.goto(app.base+'/city#city',{waitUntil:'networkidle'});
  const actual=(await(await page.request.get(app.base+'/api/game/state')).json()).data.buildings.hall_of_alliance;
  assert(actual.rally_capacity,'Live API supplies Hall capacity');assert.equal(actual.rally_capacity.levels.length,30);
  assert.equal(await page.evaluate(()=>window.ConquerLocale.locale),'en','German browser retains default English');
  async function open(){
   await page.goto(app.base+'/city?hall_check='+(++sequence)+'#city',{waitUntil:'networkidle'});
   const building=page.locator('.painted-village-building[data-id="hall_of_alliance"]');await building.waitFor();await building.evaluate(el=>el.scrollIntoView({block:'center',inline:'center'}));
   const position=await building.evaluate(el=>{const r=el.getBoundingClientRect();for(const fy of [.5,.7,.3,.9,.1])for(const fx of [.5,.7,.3,.9,.1]){const x=r.width*fx,y=r.height*fy;if(el.contains(document.elementFromPoint(r.x+x,r.y+y)))return{x,y};}return null;});
   assert(position,'Hall is touch reachable');await building.click({position});await page.locator('.painted-building-menu [data-action="building"][data-id="hall_of_alliance"]').click();
   await page.locator('#game-dialog[open][data-building="hall_of_alliance"] .hall-capacity').waitFor();await page.evaluate(()=>document.fonts.ready);
  }
  const shown=async name=>(await page.locator('[data-hall-capacity-stat="'+name+'"] .levelup-stat :is(strong,b)').allTextContents()).map(v=>Number(v.replace(/\D/g,'')));
  await open();assert.deepEqual(await shown('base'),[actual.rally_capacity.base,actual.rally_capacity.next_base]);assert.deepEqual(await shown('total'),[actual.rally_capacity.total,actual.rally_capacity.next_total]);
  report.push({scenario:'live-api',level:actual.level,capacity:actual.rally_capacity});
  await page.route('**/api/game/state*',async route=>{
   const response=await route.fetch(),json=await response.json();if(mode!=='actual'){
    const s=json.data,b=s.buildings.hall_of_alliance,max=mode==='maximum',bonus=(mode==='boosted'||max)?0.4:0;
    Object.assign(b,{level:max?30:29,cost:{food:100,lumber:100,stone:100,gold:100},seconds:120,requirements:{castle:30,barrack:30},item_requirements:[]});
    b.rally_capacity={base:max?2500000:1900000,next_base:max?null:2500000,research_bonus:bonus,total:max?3500000:bonus?2660000:1900000,next_total:max?null:bonus?3500000:2500000,levels:actual.rally_capacity.levels.map(row=>({...row,total:Math.round(row.base*(1+bonus))}))};
    s.buildings.castle.level=30;s.buildings.barrack.level=30;
    s.build_queue=mode==='running'?[{id:991,building_code:'hall_of_alliance',level_to:30,started_at:new Date().toISOString(),finishes_at:new Date(Date.now()+600000).toISOString()}]:[];
   }await route.fulfill({response,json});
  });
  mode='boosted';
  for(const locale of ['en','de','fr']){
   await page.evaluate(value=>{localStorage.setItem('conquer.locale',value);document.cookie='conquer_locale='+value+'; Path=/; SameSite=Lax';},locale);
   const messages=JSON.parse(fs.readFileSync(path.join(root,'data/i18n',locale+'.json'),'utf8'));
   for(const [width,height] of [[1280,800],[390,844],[320,568],[844,390],[568,320]]){
    await page.setViewportSize({width,height});await open();
    assert.equal((await page.locator('.hall-capacity>h3').innerText()).trim(),messages['hall.capacity.title']);
    assert.deepEqual(await shown('base'),[1900000,2500000]);assert.deepEqual(await shown('total'),[2660000,3500000]);assert.match(await page.locator('[data-hall-capacity-stat="bonus"]').innerText(),/40/);
    assert.equal((await page.locator('.hall-capacity-hint').innerText()).trim(),messages['hall.capacity.scope']);
    const summary=page.locator('.hall-capacity-levels>summary');await summary.scrollIntoViewIfNeeded();assert(await summary.evaluate(el=>el.getBoundingClientRect().height>=44),'Level disclosure has a 44px touch target');await summary.click();
    assert.equal(await page.locator('.hall-capacity-levels tbody tr').count(),30);assert.equal(await page.locator('.hall-capacity-levels tbody tr[aria-current="true"] th').innerText(),`29\n${messages['upgrade.effect.current']}`);
    const last=page.locator('.hall-capacity-levels tbody tr').last();await last.scrollIntoViewIfNeeded();
    assert.deepEqual((await last.locator('td').allInnerTexts()).map(v=>Number(v.replace(/\D/g,''))),[2500000,3500000]);
    const layout=await page.locator('#game-dialog').evaluate(d=>{const r=d.getBoundingClientRect(),scroll=d.querySelector('.levelup-scroll'),footer=d.querySelector('.levelup-footer').getBoundingClientRect(),table=d.querySelector('.hall-capacity-levels table');return{inside:r.left>=0&&r.top>=0&&r.right<=innerWidth+1&&r.bottom<=innerHeight+1,overflow:d.scrollWidth>d.clientWidth+2||scroll.scrollWidth>scroll.clientWidth+2||table.scrollWidth>table.clientWidth+2,footer:footer.top>=0&&footer.bottom<=innerHeight+1};});
    assert.deepEqual(layout,{inside:true,overflow:false,footer:true},locale+' '+width+'x'+height);
    for(const selector of ['.levelup-footer .levelup-primary','.dialog-close:visible,.mobile-page-back:visible']){
     const target=page.locator('#game-dialog').locator(selector).first();assert(await target.evaluate((el,primary)=>{const r=el.getBoundingClientRect();return r.height>0&&(!primary||r.height>=44)&&r.top>=0&&r.bottom<=innerHeight+1&&el.contains(document.elementFromPoint(r.x+r.width/2,r.y+r.height/2));},selector.includes('levelup-primary')),'Reachable fixed action '+selector);
    }
    await page.locator('.levelup-scroll').evaluate(el=>el.scrollTop=0);await page.screenshot({path:path.join(output,`hall-${locale}-${width}x${height}.png`)});report.push({locale,width,height,...layout});
   }
  }
  await page.evaluate(()=>{localStorage.setItem('conquer.locale','en');document.cookie='conquer_locale=en; Path=/; SameSite=Lax';});await page.setViewportSize({width:390,height:844});
  mode='maximum';await open();assert.deepEqual(await shown('base'),[2500000]);assert.deepEqual(await shown('total'),[3500000]);assert.equal(await page.locator('.hall-capacity .levelup-stat b').count(),0);assert(await page.locator('[data-action="upgrade"]').isDisabled());report.push({scenario:'maximum'});
  mode='running';await open();assert.deepEqual(await shown('base'),[1900000,2500000]);assert.deepEqual(await shown('total'),[1900000,2500000]);assert(await page.locator('[data-action="queue-speedups"]').isVisible());
  const summary=page.locator('.hall-capacity-levels>summary');await summary.scrollIntoViewIfNeeded();await summary.click();await summary.focus();const beforeScroll=await page.locator('.levelup-scroll').evaluate(el=>el.scrollTop);
  mode='boosted';await page.waitForFunction(()=>document.querySelector('[data-hall-capacity-stat="total"] strong')?.textContent.replace(/\D/g,'')==='2660000',{},{timeout:25000});
  assert(await page.locator('.hall-capacity-levels').evaluate(el=>el.open),'Refresh preserves expanded level table');assert(await summary.evaluate(el=>document.activeElement===el),'Refresh preserves disclosure focus');assert(Math.abs(await page.locator('.levelup-scroll').evaluate(el=>el.scrollTop)-beforeScroll)<3,'Refresh preserves reading position');report.push({scenario:'running-and-refresh'});
  assert.deepEqual(errors,[]);assert.deepEqual(missingAssets,[]);assert.deepEqual(writes,[]);
  fs.writeFileSync(path.join(output,'report.json'),JSON.stringify(report,null,2));console.log('PASS Hall live capacity, EN default, 15 localized touch layouts, all 30 levels, maximum, ongoing upgrade and preserved refresh. '+output);
 }catch(error){if(page&&!page.isClosed()){await page.screenshot({path:path.join(output,'failure.png')}).catch(()=>{});fs.writeFileSync(path.join(output,'failure.html'),await page.content().catch(()=>''));}throw error;}
 finally{if(page)await page.unrouteAll({behavior:'ignoreErrors'});if(browser)await browser.close();if(app.child.exitCode===null){app.child.stdin.end('\n');await new Promise(resolve=>app.child.once('exit',resolve));}}
})().catch(error=>{console.error(error);process.exitCode=1;});

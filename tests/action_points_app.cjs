'use strict';
// Real main app, synthetic account and automatically removed fixture database.
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const net=require('node:net'),{spawn}=require('node:child_process');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const out=path.resolve(__dirname,'../artifacts/action-points');fs.mkdirSync(out,{recursive:true});
(async()=>{
 let fixture,browser,fixtureOutput='';
 try{
  const port=await new Promise(resolve=>{const server=net.createServer();server.listen(0,'127.0.0.1',()=>{const port=server.address().port;server.close(()=>resolve(port));});});
  fixture=spawn(process.env.PHP_BINARY||'C:/xampp/php/php.exe',[path.resolve(__dirname,'../tools/preview-feature-fixture.php'),'--port='+port,'--action-points','--appearance'],{cwd:path.resolve(__dirname,'..'),stdio:['pipe','pipe','pipe'],windowsHide:true});
  await new Promise((resolve,reject)=>{
   const timer=setTimeout(()=>reject(Error('Fixture startup timed out: '+fixtureOutput)),60000);
   fixture.stdout.on('data',data=>{fixtureOutput+=data;if(fixtureOutput.includes('Synthetic preview ready')){clearTimeout(timer);resolve();}});
   fixture.stderr.on('data',data=>{fixtureOutput+=data;});
   fixture.once('exit',code=>{clearTimeout(timer);reject(Error('Fixture exited '+code+': '+fixtureOutput));});
  });
  browser=await chromium.launch({headless:true,executablePath:process.env.BROWSER_EXECUTABLE_PATH||'C:/Program Files/Google/Chrome/Application/chrome.exe'});
  const page=await browser.newPage({viewport:{width:1280,height:800},hasTouch:true});
  const errors=[];page.on('pageerror',error=>errors.push(error.message));page.setDefaultTimeout(20000);
  const base='http://127.0.0.1:'+port;
  await page.goto(base+'/?zugang=login');
  await page.locator('[name=identifier], [name=username]').fill('PreviewPlayer');await page.locator('[name=password]').fill('PreviewFixture!2026');
  await Promise.all([page.waitForURL('**/city'),page.locator('form[action$="/auth/local"] button[type=submit]').click()]);
  await page.goto(base+'/city#city');
  await page.waitForFunction(()=>document.querySelector('#hud-energy strong')?.textContent==='200 / 210');
  assert.equal(await page.locator('html').getAttribute('lang'),'en');
  async function inventory(code){
   if(!await page.locator('#panel-dialog[data-panel=inventory][open]').count())await page.locator('#navigation [data-id=inventory]').tap();
   await page.locator('[data-action=inventory-category][data-id=other]').tap();
   await page.locator(`[data-action=inventory-item][data-id="${code}"]`).tap();
   await page.waitForFunction(()=>document.querySelector('#inventory-details .inventory-use-hint')?.textContent===ConquerLocale.t('ap.refill_all_hint'));
   const amount=code===10204001?100:50;
   const expected=await page.evaluate(amount=>ConquerLocale.t('ap.refill_description',{amount:String(amount)}),amount);
   assert.equal(await page.locator('#inventory-details .inventory-effect p').textContent(),expected);
   assert.equal(await page.locator('#inventory-details button[type=submit]').isEnabled(),true);
  }
  async function closeArea(){
   const dialog=page.locator(await page.locator('#game-dialog[open]').count()?'#game-dialog[open]':'#panel-dialog[open]');
   await dialog.locator('.mobile-page-back:visible,.panel-close:visible,.dialog-close:visible').first().tap();
  }
  async function consume(code,quantity,all=false){
   await inventory(code);
   if(!all)await page.locator('#inventory-quantity').fill(String(quantity));
   const response=page.waitForResponse(r=>r.url().endsWith('/api/kingdom/action')&&r.request().method()==='POST'&&r.request().postDataJSON()?.action==='inventory.use');
   await page.locator(all?'#inventory-details [data-action=inventory-use-all]':'#inventory-details button[type=submit]').tap();
   const json=await(await response).json();assert.equal(json.ok,true,JSON.stringify(json));
   await page.waitForFunction(balance=>document.querySelector('#hud-energy strong')?.textContent===`${balance} / 210`,json.data.state.profile.action_points);
   const remaining=json.data.state.inventory.find(item=>Number(item.item_code)===code)?.quantity||0;
   await page.waitForFunction(({code,remaining})=>{
    const form=document.querySelector('#inventory-details form');
    return !remaining?!document.querySelector(`[data-action=inventory-item][data-id="${code}"]`):Number(form?.dataset.id)===code&&Number(form.querySelector('[name=quantity]')?.max)===remaining&&!form.querySelector('[aria-busy=true]');
   },{code,remaining});
   return json.data;
  }
  const single=await consume(10204001,1);
  assert.equal(single.result.amount,100);assert.equal(single.state.profile.action_points,300);assert.equal(single.state.profile.action_points_max,210);
  await page.waitForFunction(()=>document.querySelector('#hud-energy strong')?.textContent==='300 / 210');
  for(const [width,height] of [[1280,800],[390,844],[844,390]]){
   await page.setViewportSize({width,height});
   await inventory(10204001);
   const track=page.locator('#hud-energy [role=progressbar]');
   assert.equal(await track.getAttribute('aria-valuenow'),'210');assert.equal(await track.getAttribute('aria-valuetext'),'300 / 210');
   assert.equal(await page.locator('#hud-energy-fill').evaluate(el=>el.style.width),'100%');
   const bounds=await page.locator('#panel-dialog').boundingBox();assert(bounds.x>=-1&&bounds.y>=-1&&bounds.x+bounds.width<=width+1&&bounds.y+bounds.height<=height+1);
   await page.locator('#inventory-details [data-action=inventory-use-all]').scrollIntoViewIfNeeded();
   await page.screenshot({path:path.join(out,`${width}x${height}-inventory.png`)});
   await closeArea();
   await page.locator('#hud-energy').tap();
   await page.locator('.lok-profile.is-own').waitFor();
   assert.equal(await page.locator('.ap-progress').getAttribute('aria-valuetext'),'300 / 210');
   assert.equal(await page.locator('.ap-progress').getAttribute('aria-valuenow'),'210');
   await page.screenshot({path:path.join(out,`${width}x${height}-profile.png`)});
   await closeArea();
  }
  const selected=await consume(10204001,2);
  assert.equal(selected.result.quantity,2);assert.equal(selected.result.amount,200);assert.equal(selected.state.profile.action_points,500);
  const all=await consume(10104001,6,true);
  assert.equal(all.result.quantity,6);assert.equal(all.result.amount,300);assert.equal(all.state.profile.action_points,800);
  for(const locale of ['de','fr']){
   await Promise.all([page.waitForEvent('load'),page.evaluate(locale=>ConquerLocale.setLocale(locale),locale)]);
   await page.waitForFunction(locale=>ConquerLocale.locale===locale&&document.querySelector('#hud-energy strong')?.textContent==='800 / 210',locale);
   await inventory(10204001);
   assert.equal(await page.locator('html').getAttribute('lang'),locale);
   await page.screenshot({path:path.join(out,`${locale}-inventory.png`)});
  }
  await page.reload();
  await page.waitForFunction(()=>document.querySelector('#hud-energy strong')?.textContent==='800 / 210');
  assert.deepEqual(errors,[]);
  console.log('PASS: AP single/selected/all refills, 300/210 HUD and profile, reload persistence, EN/DE/FR, desktop/portrait/landscape.');
 }finally{
  if(browser)await browser.close();
  if(fixture&&fixture.exitCode===null){fixture.stdin.end('\n');await new Promise(resolve=>fixture.once('exit',resolve));}
 }
})().catch(error=>{console.error(error);process.exitCode=1;});

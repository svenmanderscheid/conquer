'use strict';
// Main-app regression: disposable database, synthetic read fixtures, intercepted dispatches.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const net = require('node:net');
const {spawn} = require('node:child_process');
const {chromium} = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const root = path.resolve(__dirname, '..');
const out = path.resolve(process.env.NPC_MARCH_OUTPUT || path.join(root, 'output/playwright/neutral-village-march'));
fs.mkdirSync(out, {recursive:true});

(async () => {
 const port = await new Promise(resolve => { const server=net.createServer(); server.listen(0,'127.0.0.1',()=>{const p=server.address().port;server.close(()=>resolve(p));}); });
 const fixture = spawn(process.env.PHP_BINARY || 'C:/xampp/php/php.exe', [path.join(root,'tools/preview-feature-fixture.php'),'--port='+port,'--appearance'], {cwd:root,stdio:['pipe','pipe','pipe'],windowsHide:true});
 const base='http://127.0.0.1:'+port, errors=[],unexpectedWrites=[],dispatches=[],checks=[];
 const npc={id:910001,name:'Willow NPC Village',coord_x:70,coord_y:65,level:6,city_skin:'default'};
 const collision={id:npc.id,username:'Wrong player target',display_name:'Wrong player target',coord_x:83,coord_y:68,castle_level:23,city_skin:'default'};
 let log='',browser,page,scenario={present:true,collision:false,revision:0};
 try {
  await new Promise((resolve,reject)=>{const timeout=setTimeout(()=>reject(Error(log||'Preview fixture timeout')),60000);fixture.stdout.on('data',d=>{log+=d;if(log.includes('Synthetic preview ready')){clearTimeout(timeout);resolve();}});fixture.stderr.on('data',d=>log+=d);fixture.on('error',reject);fixture.on('exit',()=>{clearTimeout(timeout);reject(Error(log));});});
  browser=await chromium.launch({headless:true,...(process.env.PLAYWRIGHT_EXECUTABLE_PATH?{executablePath:process.env.PLAYWRIGHT_EXECUTABLE_PATH}:{channel:'chrome'}),args:['--use-angle=swiftshader','--enable-unsafe-swiftshader']});
  const context=await browser.newContext({viewport:{width:1280,height:800},hasTouch:true,locale:'en-US'});
  // Expose the existing controller only to exercise a stale action after its NPC
  // disappears; normal opening and sending below use the actual map controls.
  await context.addInitScript(()=>{
   let factory;
   Object.defineProperty(window,'ConquerMarch',{configurable:true,get:()=>factory,set:original=>{factory=options=>{window.__npcTestState=options.getState;return window.__npcTestMarch=original(options);};}});
  });
  page=await context.newPage();page.setDefaultTimeout(20000);
  page.on('pageerror',error=>errors.push(error.message));
  await page.route('**/api/**',async route=>{
   const request=route.request(),url=new URL(request.url());
   if(url.pathname==='/api/game/state'){
    const response=await route.fetch(),json=await response.json();
    assert(json.data,'Game state has data');
    json.data.neutral_villages=scenario.present?[npc]:[];
    json.data.players=scenario.collision?[collision]:[];
    json.data.npc_test_revision=scenario.revision;
    await route.fulfill({response,json});return;
   }
   if(request.method()!=='GET'){
    if(url.pathname==='/api/march/dispatch-neutral-village'){
     dispatches.push({path:url.pathname,body:request.postDataJSON()});
     // Keep the request in flight long enough to verify the double-click guard.
     await new Promise(resolve=>setTimeout(resolve,100));
     await route.fulfill({status:200,contentType:'application/json',body:JSON.stringify({ok:true,data:{march_id:700001}})});return;
    }
    unexpectedWrites.push({path:url.pathname,body:request.postData()});
    await route.fulfill({status:400,contentType:'application/json',body:JSON.stringify({ok:false,error:'Unexpected write blocked by NPC regression test'})});return;
   }
   await route.continue();
  });
  // Optional fail-first run against a saved baseline without changing workspace code.
  if(process.env.NPC_MARCH_SOURCE)await page.route('**/assets/js/march-panel.js*',route=>route.fulfill({status:200,contentType:'text/javascript',body:fs.readFileSync(process.env.NPC_MARCH_SOURCE,'utf8')}));
  await page.goto(base+'/?zugang=login');
  await page.locator('[name=identifier], [name=username]').fill('PreviewPlayer');
  await page.locator('[name=password]').fill('PreviewFixture!2026');
  await Promise.all([page.waitForURL('**/city'),page.locator('form[action$="/auth/local"] button[type=submit]').click()]);
  await page.goto(base+'/city#city');await page.locator('#hud-menu').waitFor();
  await page.waitForFunction(()=>window.__npcTestState?.()?.npc_test_revision===0);
  await page.screenshot({path:path.join(out,'1280x800-city.png')});
  await page.locator('#navigation .hud-scene-switch[data-id=world]').click();

  async function refreshState(){
   scenario.revision++;
   await page.evaluate(()=>window.dispatchEvent(new Event('online')));
   await page.waitForFunction(revision=>window.__npcTestState?.()?.npc_test_revision===revision,scenario.revision);
  }
  async function openNpc(){
   await page.evaluate(n=>ConquerWorld.focus(n.coord_x,n.coord_y),npc);
   const marker=page.locator('[data-atlas-target="neutral_villages:'+npc.id+'"]');await marker.waitFor();
   await page.waitForFunction(id=>{const el=document.querySelector('[data-atlas-target="neutral_villages:'+id+'"]');if(!el)return false;const r=el.getBoundingClientRect();return el.contains(document.elementFromPoint(r.x+r.width/2,r.y+r.height/2));},npc.id);
   await marker.click();
   await page.locator('.atlas-target-actions [data-action=neutral-village-attack]').click();
   await page.locator('#game-dialog[open] .march-command').waitFor();
   assert.equal(await page.locator('.march-target-heading h3').textContent(),npc.name);
   assert.equal(await page.locator('#march-target-value').textContent(),String(npc.level));
   assert.match(await page.locator('.march-pvp-note').textContent(),/Troop losses|troop losses/);
   assert.match(await page.locator('.march-coordinates').textContent(),/X 70 \/ Y 65/);
   assert(await page.locator('.march-command').evaluate(el=>el.classList.contains('is-attack')),'NPC opens combat composition');
   assert(await page.locator('#march-confirm').evaluate(el=>el.classList.contains('march-attack')),'NPC has attack action');
   assert.equal(await page.locator('[data-action=march-preview]').count(),0,'NPC has no unsupported battle preview');
  }
  await openNpc();
  assert.equal(await page.evaluate(id=>window.__npcTestState().players.some(p=>Number(p.id)===id),npc.id),false,'First target has no matching player');
  await page.keyboard.press('Escape');await page.locator('#game-dialog').waitFor({state:'hidden'});
  scenario.collision=true;await refreshState();await openNpc();
  await page.locator('[data-action=march-clear]').click();
  await page.locator('#march-unit-50100101').fill('17');
  await page.locator('#march-unit-50200101').fill('9');
  const originalTravel=await page.locator('#march-travel-time').textContent();
  for(const [width,height] of [[1280,800],[390,844],[844,390],[320,568],[568,320]]){
   await page.setViewportSize({width,height});await page.evaluate(()=>document.fonts.ready);
   const metrics=await page.locator('#game-dialog').evaluate(dialog=>{
    const r=dialog.getBoundingClientRect(),send=dialog.querySelector('#march-confirm'),b=send.getBoundingClientRect();
    return{width:innerWidth,height:innerHeight,fits:r.x>=-1&&r.y>=-1&&r.right<=innerWidth+1&&r.bottom<=innerHeight+1,overflow:dialog.scrollWidth-dialog.clientWidth,sendReachable:send.contains(document.elementFromPoint(b.x+b.width/2,b.y+b.height/2))};
   });
   assert(metrics.fits&&metrics.overflow<=1,JSON.stringify(metrics));assert(metrics.sendReachable,'Attack is reachable '+JSON.stringify(metrics));
   assert.equal(await page.locator('#march-unit-50100101').inputValue(),'17');
   assert.equal(await page.locator('#march-unit-50200101').inputValue(),'9');
   const targetTab=page.locator('[data-action=march-view][data-id=target]');
   if(await targetTab.isVisible())await targetTab.click();
   await page.locator('.march-target-art img').evaluate(image=>image.decode());
   assert.match(await page.locator('.march-target-art img').getAttribute('src'),/castle/,'NPC uses castle artwork');
   assert.equal(await page.locator('.march-target-heading h3').textContent(),npc.name);
   await page.screenshot({path:path.join(out,width+'x'+height+'-target.png')});
   const troopTab=page.locator('[data-action=march-view][data-id=troops]');
   if(await troopTab.isVisible())await troopTab.click();
   await page.screenshot({path:path.join(out,width+'x'+height+'-troops.png')});checks.push(metrics);
  }
  // A loaded viewport may omit the selected NPC, while an unrelated player still
  // has the same numeric ID. The open command must retain its actual NPC target.
  scenario.present=false;await refreshState();
  assert.equal(await page.locator('#march-travel-time').textContent(),originalTravel,'Viewport refresh retains NPC coordinates for travel');
  assert.equal(await page.locator('#march-confirm').isEnabled(),true);
  await page.locator('#march-confirm').evaluate(button=>{button.click();button.click();});
  await page.locator('#game-dialog').waitFor({state:'hidden'});
  assert.equal(dispatches.length,1,'Exactly one dispatch is sent');
  assert.equal(dispatches[0].path,'/api/march/dispatch-neutral-village');
  assert.equal(dispatches[0].body.target_x,npc.coord_x);assert.equal(dispatches[0].body.target_y,npc.coord_y);
  assert.deepEqual(dispatches[0].body.troops,{'50100101':17,'50200101':9});
  await page.evaluate(id=>window.__npcTestMarch.open(id,'neutral_villages'),npc.id);
  assert.equal(await page.locator('#game-dialog').isVisible(),false,'Missing NPC never resolves to a player with the same ID');
  assert.deepEqual(errors,[]);assert.deepEqual(unexpectedWrites,[]);
  const result={checks,dispatches,errors,unexpectedWrites,output:out};fs.writeFileSync(path.join(out,'checks.json'),JSON.stringify(result,null,2));console.log(JSON.stringify(result,null,2));
 }catch(error){fs.writeFileSync(path.join(out,'failure.json'),JSON.stringify({error:error.message,errors,unexpectedWrites,dispatches},null,2));if(page)await page.screenshot({path:path.join(out,'failure.png')}).catch(()=>{});throw error;}
 finally{if(page)await page.unrouteAll({behavior:'ignoreErrors'});if(browser)await browser.close();if(fixture.exitCode===null){fixture.stdin.write('\n');await new Promise(resolve=>fixture.exitCode!==null?resolve():fixture.once('exit',resolve));}}
})().catch(error=>{console.error(error);process.exitCode=1;});

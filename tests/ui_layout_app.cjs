'use strict';
require('./fixtures/browser_locale.cjs')('de'); // This suite asserts the explicit German UI.
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),net=require('node:net'),{spawn}=require('node:child_process');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const root=path.resolve(__dirname,'..'),out=path.join(root,'artifacts/ui-layout');fs.mkdirSync(out,{recursive:true});
(async()=>{
 const port=await new Promise(resolve=>{const s=net.createServer();s.listen(0,'127.0.0.1',()=>{const p=s.address().port;s.close(()=>resolve(p));});});
 const fixture=spawn('C:/xampp/php/php.exe',[path.join(root,'tools/preview-feature-fixture.php'),'--port='+port,'--chat','--hud','--appearance'],{cwd:root,stdio:['pipe','pipe','pipe'],windowsHide:true});
 let browser,log='';
 try{
  await new Promise((resolve,reject)=>{const t=setTimeout(()=>reject(Error(log)),60000);fixture.stdout.on('data',d=>{log+=d;if(log.includes('Synthetic preview ready')){clearTimeout(t);resolve();}});fixture.stderr.on('data',d=>log+=d);fixture.once('exit',code=>{clearTimeout(t);reject(Error('fixture '+code+' '+log));});});
  browser=await chromium.launch({headless:true,channel:'chrome'});
  const context=await browser.newContext({viewport:{width:1440,height:1000},hasTouch:true}),page=await context.newPage(),errors=[];const base='http://127.0.0.1:'+port;
  page.on('pageerror',e=>errors.push(e.message));
  await page.goto(base);await page.locator('[name=identifier]').fill('PreviewPlayer');await page.locator('[name=password]').fill('PreviewFixture!2026');
  await Promise.all([page.waitForURL('**/city'),page.locator('form[action$="/auth/local"] button').click()]);
  await page.goto(base+'/admin/login');await page.locator("[name=identifier], [name=username]").fill('PreviewAdmin');await page.locator('[name=password]').fill('PreviewFixture!2026');
  await Promise.all([page.waitForURL(base+'/admin'),page.getByRole('button',{name:'Anmelden',exact:true}).click()]);
  await page.goto(base+'/admin/layout');await page.waitForFunction(()=>document.querySelector('#layout-status').textContent.includes('Vorschau bereit'));
  const frame=page.frameLocator('#layout-frame');await frame.locator('#uok-layout-layer').waitFor();
  await page.locator('#layout-y').fill('-30');await page.locator('[name=reason]').fill('Mobiltest Navigation');
  await page.locator('#layout-save').click();await page.waitForFunction(()=>document.querySelector('#layout-status').textContent.includes('für alle Spieler gespeichert'));
  await page.reload();await page.waitForFunction(()=>document.querySelector('#layout-status').textContent.includes('Vorschau bereit'));
  assert.equal(await page.locator('#layout-y').inputValue(),'-30','saved value survives reload');
  const player=await context.newPage();await player.goto(base+'/city');await player.locator('#navigation .game-dock-item').first().waitFor();await player.waitForTimeout(200);
  await player.setViewportSize({width:390,height:844});await player.waitForTimeout(200);
  assert.notEqual(await player.locator('#navigation').evaluate(el=>el.style.translate),'','saved layout applies outside editor');
  assert.equal(await player.locator('#uok-layout-layer').count(),0,'normal game has no editor overlay');await player.close();
  // Drag changes the bounded offsets without triggering a game action.
  const handle=frame.locator('[data-layout-element=navigation]'),rect=await handle.boundingBox();
  await page.mouse.move(rect.x+rect.width/2,rect.y+rect.height/2);await page.mouse.down();await page.mouse.move(rect.x+rect.width/2,rect.y+rect.height/2-20,{steps:4});await page.mouse.up();
  assert.equal(await page.locator('#layout-y').inputValue(),'-50','drag updates offset');
  await page.keyboard.press('Control+z');await page.waitForTimeout(120);assert.equal(await page.locator('#layout-y').inputValue(),'-30','undo from iframe');
  await page.keyboard.press('Control+Shift+z');await page.waitForTimeout(120);assert.equal(await page.locator('#layout-y').inputValue(),'-50','redo from iframe');
  await page.keyboard.press('Escape');await page.waitForTimeout(120);assert.equal(await frame.locator('.layout-outline.is-selected').count(),0,'Escape clears iframe selection');
  assert.equal(await page.locator('#layout-element').inputValue(),'');
  await page.locator('#layout-element').selectOption('navigation');
  for(const profile of ['portrait','landscape','desktop']){
   await page.locator('#layout-profile').selectOption(profile);
   for(const element of ['navigation','chat','resources']){
    await page.locator('#layout-element').selectOption(element);await page.locator('#layout-width').fill('150');await page.locator('#layout-x').fill('200');await page.locator('#layout-y').fill('200');
   }
   await page.waitForTimeout(250);
   const geometry=await frame.locator('body').evaluate(()=>Object.fromEntries(['#resources','#navigation','.world-chat:not(.is-open)'].map(s=>{const r=document.querySelector(s).getBoundingClientRect();return[s,{left:r.left,right:r.right,top:r.top,bottom:r.bottom,width:innerWidth,height:innerHeight}];})));
   for(const [name,r]of Object.entries(geometry))assert(r.left>=-1&&r.right<=r.width+1&&r.top>=-1&&r.bottom<=r.height+1,profile+' bounds '+name+' '+JSON.stringify(r));
   assert(geometry['.world-chat:not(.is-open)'].bottom<=geometry['#navigation'].top-7,'chat stays above dock');
   await page.screenshot({path:path.join(out,profile+'-editor.png'),fullPage:true});
   await frame.locator('body').screenshot({path:path.join(out,profile+'-preview.png')});
   await page.locator('#layout-reset-profile').click();
  }
  await page.locator('#layout-profile').selectOption('portrait');await page.locator('#layout-size').selectOption('320x568');await page.locator('#layout-element').selectOption('navigation');await page.locator('#layout-width').fill('50');await page.waitForTimeout(200);
  const targets=await frame.locator('#navigation .game-dock-item:visible').evaluateAll(els=>els.map(el=>{const r=el.getBoundingClientRect();return{left:r.left,right:r.right,width:r.width};}));
  assert.equal(targets.length,5,'compact navigation exposes five primary destinations');
  assert(targets.every(r=>r.left>=0&&r.right<=320&&r.width>=43.5),'all visible targets fit at 320px');
  await page.locator('#layout-profile').selectOption('portrait');await page.locator('#layout-reset-profile').click();await page.locator('[name=reason]').fill('Standard wiederherstellen');await page.locator('#layout-save').click();await page.waitForFunction(()=>document.querySelector('#layout-status').textContent.includes('für alle Spieler gespeichert'));
  // Expanded editor: widget scaling, handles, undo/redo, panels and custom screens.
  await page.locator('#layout-profile').selectOption('desktop');await page.locator('#layout-element').selectOption('gems');await page.waitForTimeout(120);
  const gemBefore=await frame.locator('#hud-gems').boundingBox();await page.locator('#layout-width').fill('150');await page.waitForTimeout(120);const gemAfter=await frame.locator('#hud-gems').boundingBox();assert(gemAfter.width>gemBefore.width*1.4,'widget content scales');
  await page.locator('#layout-undo').click();assert.equal(await page.locator('#layout-width').inputValue(),'100');await page.locator('#layout-redo').click();assert.equal(await page.locator('#layout-width').inputValue(),'150');
  await page.locator('#layout-anchor').selectOption('center');await page.waitForTimeout(120);
  const sizeHandle=frame.locator('[data-layout-element=gems] [data-resize=se]');await sizeHandle.scrollIntoViewIfNeeded();const h=await sizeHandle.boundingBox();await page.mouse.move(h.x+h.width/2,h.y+h.height/2);await page.mouse.down();await page.mouse.move(h.x+h.width/2+25,h.y+h.height/2+25,{steps:5});await page.mouse.up();assert(Number(await page.locator('#layout-width').inputValue())>150,'resize handle changes dimensions');
  // Touch-accessible multiselect, modifier click, group movement and one-step undo.
  await page.locator('#layout-multi').click();await page.locator('#layout-element').selectOption('account');await page.locator('#layout-multi').click();await page.waitForTimeout(150);
  assert.equal(await frame.locator('.layout-outline.is-selected').count(),2);
  const gemMove=frame.locator('[data-layout-element=gems] .layout-move');await gemMove.click({modifiers:['Control']});await page.waitForTimeout(100);assert.equal(await frame.locator('.layout-outline.is-selected').count(),1,'ctrl-click removes member');
  await gemMove.click({modifiers:['Shift']});await page.waitForTimeout(100);assert.equal(await frame.locator('.layout-outline.is-selected').count(),2,'shift-click adds member');
  const beforeGroup=await frame.locator('body').evaluate(()=>Object.fromEntries(['#hud-gems','#account-button'].map(s=>{const r=document.querySelector(s).getBoundingClientRect();return[s,{x:r.x,y:r.y}];})));
  await gemMove.scrollIntoViewIfNeeded();const groupBox=await gemMove.boundingBox();await page.mouse.move(groupBox.x+groupBox.width/2,groupBox.y+groupBox.height/2);await page.mouse.down();await page.mouse.move(groupBox.x+groupBox.width/2+15,groupBox.y+groupBox.height/2+15,{steps:3});await page.mouse.up();await page.waitForTimeout(150);
  const afterGroup=await frame.locator('body').evaluate(()=>Object.fromEntries(['#hud-gems','#account-button'].map(s=>{const r=document.querySelector(s).getBoundingClientRect();return[s,{x:r.x,y:r.y}];})));
  for(const k of Object.keys(beforeGroup)){assert(afterGroup[k].x>beforeGroup[k].x+5,'group member moved '+k);}
  await page.keyboard.press('Control+z');await page.waitForTimeout(150);
  for(const k of Object.keys(beforeGroup)){const x=await frame.locator(k).evaluate(el=>el.getBoundingClientRect().x);assert(Math.abs(x-beforeGroup[k].x)<1,'group undo '+k);}
  await gemMove.scrollIntoViewIfNeeded();const cancelBox=await gemMove.boundingBox();await page.mouse.move(cancelBox.x+cancelBox.width/2,cancelBox.y+cancelBox.height/2);await page.mouse.down();await page.mouse.move(cancelBox.x+cancelBox.width/2+10,cancelBox.y+cancelBox.height/2+10,{steps:3});await page.keyboard.press('Escape');await page.mouse.up();await page.waitForTimeout(150);
  assert.equal(await frame.locator('.layout-outline.is-selected').count(),0,'cancel drag deselects');
  for(const k of Object.keys(beforeGroup)){const x=await frame.locator(k).evaluate(el=>el.getBoundingClientRect().x);assert(Math.abs(x-beforeGroup[k].x)<1,'Escape restores group '+k);}
  await page.locator('#layout-redo').click();await page.waitForTimeout(150);for(const k of Object.keys(afterGroup)){const x=await frame.locator(k).evaluate(el=>el.getBoundingClientRect().x);assert(Math.abs(x-afterGroup[k].x)<1,'cancel preserves redo '+k);}await page.locator('#layout-undo').click();
  await page.locator('#layout-deselect').click();assert.equal(await page.locator('#layout-element').inputValue(),'');
  await page.locator('#layout-element').selectOption('panel_inventory');await frame.locator('#panel-dialog[open]').waitFor();await page.waitForTimeout(150);await page.locator('#layout-width').fill('80');await page.locator('#layout-height').fill('80');await page.waitForTimeout(150);
  const panelHandle=frame.locator('[data-layout-element=panel_inventory] .layout-move');assert(await panelHandle.isVisible(),'dialog has accessible editor overlay');await panelHandle.scrollIntoViewIfNeeded();const panelBox=await panelHandle.boundingBox();await page.mouse.move(panelBox.x+panelBox.width/2,panelBox.y+panelBox.height/2);await page.mouse.down();await page.mouse.move(panelBox.x+panelBox.width/2+20,panelBox.y+panelBox.height/2+15,{steps:3});await page.mouse.up();assert.notEqual(await page.locator('#layout-x').inputValue(),'0','modal window can be dragged');
  await page.screenshot({path:path.join(out,'window-editor.png'),fullPage:true});
  await page.keyboard.press('Escape');await page.waitForTimeout(120);assert.equal(await frame.locator('.layout-outline.is-selected').count(),0);assert.equal(await frame.locator('#panel-dialog[open]').count(),1,'Escape deselects without closing edited window');
  await page.locator('#layout-element').selectOption('panel_inventory');await page.locator('#layout-element').press('Control+z');await page.waitForTimeout(120);assert.equal(await page.locator('#layout-x').inputValue(),'0','undo also works in parent');
  await page.locator('#layout-screen').selectOption('world');await frame.locator('.atlas-shell').waitFor();await page.locator('#layout-element').selectOption('map_search');await page.locator('#layout-width').fill('130');await page.waitForTimeout(150);assert(await frame.locator('[data-layout-element=map_search]').isVisible(),'world controls editable');
  await page.locator('#layout-screen-width').fill('820');await page.locator('#layout-screen-height').fill('1180');await page.locator('#layout-custom-size').click();assert.equal(await page.locator('#layout-profile').inputValue(),'portrait');assert.equal(await frame.locator('body').evaluate(()=>innerWidth),820);
  await page.locator('#layout-screen').selectOption('city');await page.locator('#layout-profile').selectOption('desktop');await page.locator('#layout-reset-profile').click();if(await page.locator('#layout-discard').isEnabled())await page.locator('#layout-discard').click();
  await page.locator('#layout-profile').selectOption('desktop');await page.locator('#layout-element').selectOption('gems');await page.locator('#layout-width').fill('140');
  const downloadEvent=page.waitForEvent('download');await page.locator('#layout-export').click();const download=await downloadEvent,exported=JSON.parse(fs.readFileSync(await download.path(),'utf8'));assert.equal(exported.profiles.desktop.gems.width,140);
  await page.locator('#layout-reset-element').click();await page.locator('#layout-import-file').setInputFiles({name:'layout.json',mimeType:'application/json',buffer:Buffer.from(JSON.stringify(exported))});await page.waitForFunction(()=>document.querySelector('#layout-status').textContent.includes('Layout importiert'));assert.equal(await page.locator('#layout-width').inputValue(),'140');
  await page.locator('#layout-import-file').setInputFiles({name:'bad.json',mimeType:'application/json',buffer:Buffer.from('{"application":"bad"}')});await page.waitForFunction(()=>document.querySelector('#layout-status').textContent.includes('bisherige Layout'));assert.equal(await page.locator('#layout-width').inputValue(),'140');
  await page.locator('#layout-copy-target').selectOption('landscape');await page.locator('#layout-copy').click();await page.locator('#layout-profile').selectOption('landscape');assert.equal(await page.locator('#layout-width').inputValue(),'140','copy includes hidden elements');
  await page.locator('#layout-element').selectOption('profile');await page.locator('#layout-width').fill('110');await page.locator('#layout-element').selectOption('account');await page.locator('#layout-x').fill('40');await page.waitForTimeout(150);
  const nested=await frame.locator('#account-button').evaluate(el=>{const r=el.getBoundingClientRect();return r.left>=0&&r.right<=innerWidth&&r.top>=0&&r.bottom<=innerHeight;});assert(nested,'child inside changed group stays bounded');
  await page.locator('#layout-discard').click();await page.locator('#layout-profile').selectOption('portrait');await page.locator('#layout-element').selectOption('gems');
  const denied=await context.request.post(base+'/admin/layout-data',{form:{csrf_token:'wrong'}});assert.equal(denied.status(),403);
  const anonymous=await browser.newContext();const unauthorized=await anonymous.request.post(base+'/admin/layout-data',{form:{csrf_token:'wrong'},maxRedirects:0});assert.equal(unauthorized.status(),302);await anonymous.close();
  for(const [width,height]of [[390,844],[844,390]]){await page.setViewportSize({width,height});await page.waitForTimeout(150);assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'editor page fits');await page.screenshot({path:path.join(out,`editor-${width}x${height}.png`),fullPage:true});}
  assert.deepEqual(errors,[]);console.log('PASS layout editor: save/reload/reset, three profiles, bounded HUD, CSRF/auth, mobile editor');
 }catch(error){if(browser)for(const ctx of browser.contexts())for(const p of ctx.pages()){await p.screenshot({path:path.join(out,'failure.png'),fullPage:true}).catch(()=>{});for(const f of p.frames())if(f.url().includes('layout_preview'))console.log(await f.evaluate(()=>{const e=document.querySelector('[data-layout-element=panel_inventory] .layout-move');if(!e)return{};const r=e.getBoundingClientRect(),l=document.querySelector('#uok-layout-layer');return{rect:r.toJSON(),hit:document.elementFromPoint(r.x+r.width/2,r.y+20)?.outerHTML.slice(0,350),parent:l.parentElement.tagName,pop:l.matches(':popover-open'),styles:[e,l].map(x=>({pointer:getComputedStyle(x).pointerEvents,bg:getComputedStyle(x).background,display:getComputedStyle(x).display})),dialog:document.querySelector('#panel-dialog').outerHTML.slice(0,300)};}));}throw error;}
 finally{if(browser)await browser.close();if(fixture.exitCode===null){fixture.stdin.end('\n');await new Promise(resolve=>fixture.once('exit',resolve));}}
})().catch(e=>{console.error(e);process.exitCode=1;});

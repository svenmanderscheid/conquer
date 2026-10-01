'use strict';
// Real composer, localization and application styles; all writes stay in this isolated fixture.
// PLAYWRIGHT_MODULE may point to the bundled Playwright module; Chrome is used by default.
const fs=require('fs'),path=require('path'),assert=require('node:assert/strict');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const root=path.resolve(__dirname,'..'),out=path.join(root,'artifacts/march-formations');
fs.mkdirSync(out,{recursive:true});
const catalogs=Object.fromEntries(['en','de','fr'].map(locale=>[locale,JSON.parse(fs.readFileSync(path.join(root,'data/i18n',locale+'.json'),'utf8'))]));
const styles=[...fs.readFileSync(path.join(root,'views/game.php'),'utf8').matchAll(/assets\/css\/([a-z0-9-]+)\.css/g)].map(match=>match[1]);
const scripts=['localization','mobile-pages','castle-skins','reward-dialog','march-panel'];
const html=`<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
${styles.map(name=>'<link rel="stylesheet" href="/assets/css/'+name+'.css">').join('')}
</head><body class="mobile-game world-mode"><dialog id="panel-dialog"><div class="page-heading"></div></dialog><dialog id="game-dialog"><button class="dialog-close" aria-label="Close">×</button><div id="dialog-content"></div></dialog>
<script>window.CONQUER_BASE='';window.CONQUER_I18N=${JSON.stringify({locale:'en',catalogs}).replaceAll('<','\\u003c')};</script>
${scripts.map(name=>'<script src="/assets/js/'+name+'.js"></script>').join('')}
<script>
const defs=[];
for(let tier=1;tier<=5;tier++)for(let type=1;type<=3;type++)defs.push({code:50100000+type*100+tier,type,tier,name:['Infantry','Archers','Cavalry'][type-1]+' T'+tier,attack:10*tier,gather_carry:10,march_speed:50,pvp_march_speed:50,pvp_rally_speed:50,monster_rally_speed:100,monster_power:10*tier,monster_power_single_type:12*tier,monster_rally_power:11*tier,monster_rally_power_single_type:13*tier});
window.state={player:{id:7},city:{id:7,player_id:7,world_id:1,coord_x:20,coord_y:20,action_points:200},world:{map_profile:{travel_scale:1}},troop_defs:defs,troops:Object.fromEntries(defs.map(t=>[t.code,500])),army_limits:{march_capacity:1200,march_slots:3},marches:[],players:[],monsters:[{id:99,coord_x:21,coord_y:20,hp_current:1000,required_power:121,definition:{name:'Frostgrimm',art:'monsters/frostgrimm',type:'rally',level:1,action_point_cost:25,stats:{hp:1000,attack:10,defense:5}}}],nodes:[{id:99,coord_x:21,coord_y:20,object_type:1,resource_amount:10000,gather_rate:10,can_attack:true,gatherer_march_id:null}]};
const initialFormations=[{slot:1,name:'Forschung <guard>',troops:{50100105:900,50100205:800,50100305:600,999:999}},{slot:3,name:'Third watch',troops:{50100101:47}},{slot:6,name:'Sixth watch',troops:{50100305:86}}];
window.formations=structuredClone(initialFormations);window.sent=[];window.saved=[];window.notices=[];window.reads=0;window.failRead=false;window.failSave=false;window.delayRead=false;window.delaySave=false;
const esc=value=>String(value??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
window.mobile=ConquerMobilePages({navigate(){},getRoute:()=> 'world',getPlayfield:()=> 'world',closeChat(){}});
function openDialog(content){
 const dialog=document.querySelector('#game-dialog'),body=document.querySelector('#dialog-content');
 delete dialog.dataset.march;dialog.classList.remove('march-dialog','has-popup-heading');dialog.querySelector(':scope > .popup-heading')?.remove();body.innerHTML=content;
 const heading=body.querySelector('h2');if(heading){heading.id='dialog-title';heading.tabIndex=-1;dialog.setAttribute('aria-labelledby','dialog-title');}
 mobile.syncDialog();if(!dialog.open){dialog.showModal();mobile.opened('dialog');}dialog.scrollTop=0;heading?.focus({preventScroll:true});ConquerLocale.apply(dialog);
}
async function loadFormations(){reads++;const snapshot=structuredClone(formations),failure=failRead;if(delayRead){delayRead=false;await new Promise(resolve=>window.releaseRead=resolve);}if(failure)throw new Error('Cannot load formations');return snapshot;}
async function api(endpoint,payload){
 if(endpoint!=='defense/action'||payload?.action!=='formation.save')throw new Error('Unexpected fixture API: '+endpoint);
 saved.push({path:endpoint,payload:structuredClone(payload)});const failure=failSave;
 if(delaySave){delaySave=false;await new Promise(resolve=>window.releaseSave=resolve);}if(failure)throw new Error('Connection unavailable');
 formations=formations.filter(f=>f.slot!==payload.slot);formations.push({slot:payload.slot,name:payload.name,troops:structuredClone(payload.troops)});
 return {message:'Saved',slot:payload.slot};
}
window.march=ConquerMarch({base:'',api,loadFormations,esc,fmt:n=>Number(n||0).toLocaleString('en-US'),unitName:t=>t.name,getState:()=>state,getKingdom:()=>({hospital:{capacity:10000,used:0}}),getProfile:()=>({action_points:200}),openDialog,toast:message=>notices.push(message),action:async(path,payload)=>{sent.push({path,payload});return {};}});
document.addEventListener('click',event=>{const button=event.target.closest('[data-action]');if(button)march.onClick(button.dataset.action,button);});
document.querySelector('.dialog-close').onclick=()=>document.querySelector('#game-dialog').close();
document.querySelector('#game-dialog').addEventListener('close',()=>{if(!document.querySelector('#game-dialog').open)mobile.closed('dialog');});
window.openMarch=(kind='players')=>{localStorage.removeItem('conquer:march-choice:v1::7:1:'+kind);march.open(99,kind,{rally_id:42,rally_target_kind:'monster',rally_capacity_remaining:700,rally_launch_at:'2099-01-01 12:00:00',rally_status:'gathering',target:{id:99,coord_x:21,coord_y:20,display_name:'Forschung',castle_level:8}});};
window.reset=()=>{formations=structuredClone(initialFormations);sent=[];saved=[];notices=[];failRead=false;failSave=false;delayRead=false;delaySave=false;state.city.world_id=1;state.army_limits.march_capacity=1200;state.troops=Object.fromEntries(defs.map(t=>[t.code,500]));};
</script></body></html>`;

async function reachable(page,selector,label,minHeight=44){
 const result=await page.locator(selector).evaluate((element,minHeight)=>{const r=element.getBoundingClientRect(),hit=document.elementFromPoint(r.x+r.width/2,r.y+r.height/2);return {okay:r.width>0&&r.height>=minHeight-1&&r.x>=0&&r.y>=0&&r.right<=innerWidth+1&&r.bottom<=innerHeight+1&&element.contains(hit),rect:{x:r.x,y:r.y,width:r.width,height:r.height},hit:hit?.outerHTML.slice(0,180)};},minHeight);
 assert(result.okay,label+' '+JSON.stringify(result));
}
const values=page=>page.locator('.march-unit-amount input').evaluateAll(inputs=>Object.fromEntries(inputs.map(input=>[input.id.replace('march-unit-',''),Number(input.value)]).filter(([,count])=>count>0)));

// Interaction and responsive assertions follow the public controls used by players.
const slot=id=>'[data-action="march-formation-load"][data-id="'+id+'"]';
const destination=id=>'.march-formation-dialog [data-action="march-formation-slot"][data-id="'+id+'"]';
async function open(page,kind='players'){
 await page.evaluate(kind=>openMarch(kind),kind);
 await page.waitForFunction(()=>!document.querySelector('[data-action="march-formation-load"][data-id="1"]')?.disabled);
 await page.evaluate(()=>document.fonts.ready);
}
const settle=page=>page.evaluate(()=>new Promise(requestAnimationFrame));

(async()=>{
 const browser=await chromium.launch({headless:true,channel:process.env.PLAYWRIGHT_CHANNEL||'chrome'});
 const errors=[],failures=[],layouts=[];let checks=0,page;
 try{
  page=await browser.newPage({viewport:{width:1280,height:800},locale:'de-DE',hasTouch:true});page.setDefaultTimeout(6000);page.on('pageerror',error=>errors.push(error.message));
  await page.route('https://march-formations.fixture/**',route=>{const url=new URL(route.request().url());if(url.pathname==='/')return route.fulfill({contentType:'text/html',body:html});const file=path.resolve(root,'.'+url.pathname);if(!file.startsWith(root+path.sep)||!fs.existsSync(file))return route.fulfill({status:404});return route.fulfill({path:file});});
  await page.goto('https://march-formations.fixture/');await open(page);
  assert.equal(await page.locator('html').getAttribute('lang'),'en','English remains default with a German browser');
  assert.equal(await page.locator('[data-action="march-formation-load"]').count(),6,'all six positions are visible');
  assert.equal(await page.locator('#march-saved-formation').count(),0,'no hidden drop-down required');
  assert.equal((await page.locator('#march-formation-save').textContent()).trim(),'Save formation');
  await page.locator(slot(1)).click();assert.deepEqual(await values(page),{50100105:500,50100205:500,50100305:200},'saved formation clamps troop stocks and total capacity');
  assert.equal(await page.locator(slot(1)).getAttribute('aria-pressed'),'true');
  const first=await values(page);await page.locator(slot(2)).click();assert.deepEqual(await values(page),first,'choosing an empty position preserves the army');assert.equal(await page.locator(slot(2)).getAttribute('aria-pressed'),'true');
  await page.locator(slot(6)).click();assert.deepEqual(await values(page),{50100305:86},'sixth position loads with one click');checks++;

  await open(page,'rally-join');await page.locator(slot(1)).click();assert.deepEqual(await values(page),{50100105:500,50100205:200},'rally joining respects its remaining capacity');checks++;
  await open(page);await page.locator(slot(3)).click();await page.locator(slot(2)).click();
  const army=await values(page);await page.locator('#march-formation-save').click();
  assert.equal(await page.locator('.march-formation-dialog').count(),1);
  assert.equal(await page.locator(destination(2)).getAttribute('aria-pressed'),'true','selected empty position is the default destination');
  await page.locator('#march-formation-name').fill('New guard <safe>');
  await page.locator(destination(1)).click();assert.deepEqual(await values(page),army,'save destination does not load another army');
  assert.match(await page.locator('#march-formation-confirm').textContent(),/Replace formation/i,'overwrite has an explicit action');
  assert.equal(await page.locator('#march-formation-name').inputValue(),'Forschung <guard>','existing custom name stays untranslated and escaped');
  await page.locator('[data-action="march-formation-close"]').last().click();await page.waitForSelector('.march-formation-dialog',{state:'detached'});
  assert.equal(await page.evaluate(()=>saved.length),0,'cancel never saves');assert.deepEqual(await values(page),army,'cancel retains current selection');checks++;

  await page.locator('#march-formation-save').click();await page.locator(destination(2)).click();await page.locator('#march-formation-name').fill('New guard <safe>');
  await page.locator('#march-formation-confirm').click();await page.waitForSelector('.march-formation-dialog',{state:'detached'});
  assert.deepEqual(await page.evaluate(()=>saved[0]),{path:'defense/action',payload:{action:'formation.save',city_id:7,slot:2,name:'New guard <safe>',troops:army}},'save uses authenticated defense endpoint with selected army');
  assert.equal(await page.evaluate(()=>sent.length),0,'saving does not dispatch a march');assert.equal(await page.locator('#game-dialog').evaluate(el=>el.open),true,'composer stays open after save');
  await page.locator(slot(6)).click();await page.locator(slot(2)).click();assert.deepEqual(await values(page),army,'newly saved army is immediately available');
  await page.locator('#march-formation-save').click();assert.match(await page.locator('#march-formation-confirm').textContent(),/Replace formation/i);
  await page.locator('#march-formation-name').fill('Updated guard');await page.locator('#march-formation-confirm').click();await page.waitForSelector('.march-formation-dialog',{state:'detached'});
  assert.equal(await page.evaluate(()=>saved.at(-1).payload.name),'Updated guard');checks++;

  // Failures retain the player's work and allow one deliberate retry.
  await page.locator(slot(4)).click();await page.locator('#march-formation-save').click();await page.locator('#march-formation-name').fill('Retry this guard');await page.evaluate(()=>failSave=true);
  await page.locator('#march-formation-confirm').click();await page.waitForFunction(()=>!document.querySelector('#march-formation-confirm').disabled);
  assert.equal(await page.locator('#march-formation-name').inputValue(),'Retry this guard');assert.equal(await page.locator('.march-formation-dialog').evaluate(el=>el.open),true);
  assert.equal(await page.locator('#march-formation-error').textContent(),'Connection unavailable');assert(await page.locator('#march-formation-error').isVisible(),'save error is explained inline');
  const failed=await page.evaluate(()=>saved.length);await page.evaluate(()=>{failSave=false;delaySave=true;});await page.locator('#march-formation-confirm').click();
  await page.waitForFunction(()=>typeof releaseSave==='function');assert(await page.locator('#march-formation-confirm').isDisabled());
  await page.evaluate(()=>document.querySelector('.march-formation-dialog form').dispatchEvent(new Event('submit',{bubbles:true,cancelable:true})));
  assert.equal(await page.evaluate(()=>saved.length),failed+1,'repeated submit cannot create a second request');await page.evaluate(()=>releaseSave());await page.waitForSelector('.march-formation-dialog',{state:'detached'});checks++;

  await page.locator('[data-action="march-clear"]').click();assert(await page.locator('#march-formation-save').isDisabled(),'empty army cannot be saved');
  await page.locator('.march-unit-amount input').first().fill('501');assert(await page.locator('#march-formation-save').isDisabled(),'army above available troop stock cannot be saved');
  await page.locator(slot(6)).click();await page.locator('#march-formation-save').click();await page.keyboard.press('Escape');await page.waitForSelector('.march-formation-dialog',{state:'detached'});assert(await page.locator('#game-dialog').evaluate(el=>el.open),'escape closes only nested editor');checks++;

  // Late read/save completions cannot touch another encounter, world or closed window.
  await page.evaluate(()=>{delayRead=true;releaseRead=null;openMarch();});await page.waitForFunction(()=>typeof releaseRead==='function');
  await open(page,'rally');await page.locator(slot(6)).click();const fresh=await values(page);await page.evaluate(()=>releaseRead());await settle(page);assert.deepEqual(await values(page),fresh,'late previous read leaves new encounter untouched');
  await page.locator(slot(5)).click();await page.locator('#march-formation-save').click();await page.locator('#march-formation-name').fill('Old encounter guard');await page.evaluate(()=>{delaySave=true;releaseSave=null;});await page.locator('#march-formation-confirm').click();await page.waitForFunction(()=>typeof releaseSave==='function');
  await open(page);await page.locator(slot(3)).click();const newer=await values(page);await page.evaluate(()=>releaseSave());await settle(page);assert.deepEqual(await values(page),newer,'late save leaves new encounter selection untouched');assert.equal(await page.locator('.march-formation-dialog').count(),0,'old editor does not reopen');
  await page.locator('#march-formation-save').click();await page.evaluate(()=>document.querySelector('#game-dialog').close());await page.waitForSelector('.march-formation-dialog',{state:'detached'});
  await page.evaluate(()=>{delayRead=true;releaseRead=null;openMarch();state.city.world_id=2;});await page.waitForFunction(()=>typeof releaseRead==='function');await page.evaluate(()=>releaseRead());await settle(page);assert(await page.locator('#march-formation-save').isDisabled(),'late read from another world cannot enable saving');
  await page.evaluate(()=>{state.city.world_id=1;});checks++;

  await page.evaluate(()=>{reset();failRead=true;openMarch();});await settle(page);assert(await page.locator('#march-formation-save').isDisabled(),'failed loading cannot accidentally overwrite an unknown slot');
  assert(await page.locator('[data-action="march-formations-retry"]').isVisible(),'failed loading offers a retry');await page.evaluate(()=>{failRead=false;formations=[];});await page.locator('[data-action="march-formations-retry"]').click();await page.waitForFunction(()=>!document.querySelector('[data-action="march-formation-load"][data-id="6"]').disabled);assert.equal(await page.locator('[data-action="march-formation-load"]').count(),6,'six empty slots remain available');
  const unsaved=await values(page);await page.locator(slot(6)).click();assert.deepEqual(await values(page),unsaved);await page.locator('#march-formation-save').click();assert.equal(await page.locator(destination(6)).getAttribute('aria-pressed'),'true');await page.keyboard.press('Escape');checks++;

  for(const viewport of [{width:1280,height:800},{width:390,height:844},{width:320,height:568},{width:844,height:390},{width:568,height:320}]){
   const label=viewport.width+'x'+viewport.height;
   try{
    await page.setViewportSize(viewport);await page.evaluate(()=>reset());
    for(const kind of ['players','rally','rally-join']){
     await open(page,kind);await page.locator(slot(3)).click();
     for(let n=1;n<=6;n++)await reachable(page,slot(n),label+' '+kind+' slot '+n);
     await reachable(page,'#march-formation-save',label+' '+kind+' save');await reachable(page,'#march-confirm',label+' '+kind+' dispatch');
     const metrics=await page.locator('#game-dialog').evaluate(dialog=>{const r=dialog.getBoundingClientRect(),list=dialog.querySelector('.march-unit-list'),lr=list.getBoundingClientRect();return {fits:r.x>=-1&&r.y>=-1&&r.right<=innerWidth+1&&r.bottom<=innerHeight+1,overflow:dialog.scrollWidth-dialog.clientWidth,listHeight:lr.height,visibleRows:[...list.children].filter(el=>{const q=el.getBoundingClientRect();return q.top>=lr.top&&q.bottom<=lr.bottom;}).length};});
     assert(metrics.fits&&metrics.overflow<=1,label+' '+kind+' '+JSON.stringify(metrics));assert(metrics.visibleRows>=2,label+' usable troop list '+JSON.stringify(metrics));
     const headerY=await page.locator(slot(1)).evaluate(el=>el.getBoundingClientRect().y);await page.locator('.march-unit-row').last().scrollIntoViewIfNeeded();assert.equal(await page.locator(slot(1)).evaluate(el=>el.getBoundingClientRect().y),headerY,'formations stay visible when troop list scrolls');
     await page.locator('.march-unit-list').evaluate(el=>el.scrollTop=0);layouts.push({viewport,kind,...metrics});
    }
    await open(page);await page.locator(slot(6)).click();await page.screenshot({path:path.join(out,label+'-formations.png')});
    await page.locator('#march-formation-save').click();
    for(let n=1;n<=6;n++)await reachable(page,destination(n),label+' save destination '+n);
    await reachable(page,'#march-formation-confirm',label+' save confirmation');
    const labelContrast=await page.locator('.march-formation-dialog label').evaluate(el=>{
     const rgb=value=>value.match(/[\d.]+/g).map(Number),luma=color=>{const c=rgb(color).slice(0,3).map(n=>{n/=255;return n<=.04045?n/12.92:Math.pow((n+.055)/1.055,2.4);});return c[0]*.2126+c[1]*.7152+c[2]*.0722;};
     let parent=el,background;while(parent){background=getComputedStyle(parent).backgroundColor;const rgba=rgb(background);if(rgba.length===3||rgba[3]>0)break;parent=parent.parentElement;}
     const foreground=getComputedStyle(el).color,a=luma(foreground),b=luma(background);return {foreground,background,ratio:(Math.max(a,b)+.05)/(Math.min(a,b)+.05)};
    });assert(labelContrast.ratio>=4.5,label+' readable formation-name label '+JSON.stringify(labelContrast));
    const editor=await page.locator('.march-formation-dialog').evaluate(el=>{const r=el.getBoundingClientRect();return {fits:r.x>=0&&r.y>=0&&r.right<=innerWidth+1&&r.bottom<=innerHeight+1,overflow:el.scrollWidth-el.clientWidth};});assert(editor.fits&&editor.overflow<=1,label+' save editor '+JSON.stringify(editor));
    await page.screenshot({path:path.join(out,label+'-save.png')});await page.keyboard.press('Escape');
    console.log('PASS '+label+' attack/rally/join controls and save dialog');checks++;
   }catch(error){failures.push(label+': '+error.message);await page.screenshot({path:path.join(out,label+'-failure.png')});await page.evaluate(()=>document.querySelector('.march-formation-dialog')?.close());console.log('FAIL '+label+': '+error.message);}
  }
  for(const locale of ['de','fr']){
   await page.setViewportSize({width:390,height:844});await page.evaluate(locale=>{reset();ConquerLocale.setLocale(locale);},locale);await open(page);await page.locator(slot(1)).click();
   assert.equal((await page.locator('#march-formation-save').textContent()).trim(),catalogs[locale]['march.formation.save'],locale+' save translation');assert.equal(await page.locator('#march-formation-status').textContent(),'1 · Forschung <guard>','custom name remains unchanged in '+locale);
   assert(await page.locator('#march-formation-save').evaluate(el=>el.scrollWidth<=el.clientWidth+1&&el.scrollHeight<=el.clientHeight+1),locale+' save label fits its button');
   await reachable(page,'#march-formation-save',locale+' translated mobile save');await page.screenshot({path:path.join(out,'390x844-formations-'+locale+'.png')});
   await page.locator('#march-formation-save').click();assert.equal((await page.locator('#march-formation-confirm').textContent()).trim(),catalogs[locale]['march.formation.replace']);await reachable(page,'#march-formation-confirm',locale+' translated replacement');await page.keyboard.press('Escape');checks++;
  }
  await page.evaluate(()=>{reset();ConquerLocale.setLocale('en');});await page.setViewportSize({width:390,height:844});await open(page);await page.locator(slot(3)).click();
  const beforeNavigation=await values(page);
  for(const dismiss of ['browser-back','escape','cancel']){
   const historyBefore=await page.evaluate(()=>JSON.stringify(history.state));await page.locator('#march-formation-save').click();
   assert.notEqual(await page.evaluate(()=>JSON.stringify(history.state)),historyBefore,'save editor owns its browser history entry');
   // Orientation changes must not make Back dismiss the parent march window.
   await page.setViewportSize(dismiss==='browser-back'?{width:844,height:390}:{width:390,height:844});
   if(dismiss==='browser-back')await page.goBack();else if(dismiss==='escape')await page.keyboard.press('Escape');else await page.locator('[data-action="march-formation-close"]').last().click();
   await page.waitForSelector('.march-formation-dialog',{state:'detached'});await page.waitForFunction(before=>JSON.stringify(history.state)===before,historyBefore);
   assert.equal(await page.locator('#game-dialog').evaluate(el=>el.open),true,dismiss+' keeps composer open');assert.deepEqual(await values(page),beforeNavigation,dismiss+' preserves troop selection');
  }
  assert.equal(await page.evaluate(()=>saved.length),0,'Back, Escape and Cancel never save');checks++;
  await page.locator('#march-formation-save').click();await page.evaluate(()=>{document.documentElement.style.setProperty('--mobile-page-height','350px');document.documentElement.style.setProperty('--mobile-page-top','0px');});
  await page.locator('#march-formation-name').scrollIntoViewIfNeeded();await page.locator('#march-formation-name').fill('Keyboard-visible guard');
  await reachable(page,'#march-formation-confirm','save stays reachable above the on-screen keyboard');
  const keyboard=await page.locator('.march-formation-dialog').evaluate(el=>{const dialog=el.getBoundingClientRect(),confirm=el.querySelector('#march-formation-confirm').getBoundingClientRect(),input=el.querySelector('#march-formation-name').getBoundingClientRect();return {top:dialog.top,bottom:dialog.bottom,confirmBottom:confirm.bottom,inputTop:input.top,inputBottom:input.bottom};});
  assert(keyboard.top>=0&&keyboard.bottom<=351&&keyboard.confirmBottom<=351&&keyboard.inputTop>=0&&keyboard.inputBottom<=351,'save dialog fits visible350px keyboard viewport '+JSON.stringify(keyboard));
  await page.screenshot({path:path.join(out,'390x844-keyboard.png')});await page.keyboard.press('Escape');await page.waitForSelector('.march-formation-dialog',{state:'detached'});await page.evaluate(()=>{document.documentElement.style.setProperty('--mobile-page-height',innerHeight+'px');document.documentElement.style.setProperty('--mobile-page-top','0px');});checks++;
  assert.equal(await page.evaluate(()=>sent.length),0,'no save/load interaction dispatches troops');assert.deepEqual(errors,[],'no browser errors');assert.deepEqual(failures,[],'responsive layouts');
  fs.writeFileSync(path.join(out,'result.json'),JSON.stringify({checks,layouts,errors,failures},null,2));console.log('PASS '+checks+' formation behavior/layout groups. '+out);
 }catch(error){if(page)await page.screenshot({path:path.join(out,'failure.png')}).catch(()=>{});throw error;}
 finally{await browser.close();}
})().catch(error=>{console.error(error);process.exitCode=1;});

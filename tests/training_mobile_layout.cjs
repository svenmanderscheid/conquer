'use strict';
// Real app in a disposable database: navigation and layout only, no gameplay writes.
const fs=require('fs'),path=require('path'),assert=require('assert/strict'),net=require('net');
const {spawn}=require('child_process');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const root=path.resolve(__dirname,'..'),out=path.join(root,'artifacts/training-mobile');
fs.mkdirSync(out,{recursive:true});
(async()=>{
 const port=await new Promise(resolve=>{const s=net.createServer();s.listen(0,'127.0.0.1',()=>{const p=s.address().port;s.close(()=>resolve(p));});});
 const child=spawn(process.env.PHP_BINARY||'C:/xampp/php/php.exe',['tools/preview-feature-fixture.php','--port='+port,'--hud','--training','--mailbox'],{cwd:root,stdio:['pipe','pipe','pipe'],windowsHide:true});
 let browser,log='';const base='http://127.0.0.1:'+port;
 try{
  await new Promise((resolve,reject)=>{const timer=setTimeout(()=>reject(Error(log||'Fixture timeout')),60000);child.stdout.on('data',c=>{log+=c;if(log.includes('Synthetic preview ready')){clearTimeout(timer);resolve();}});child.stderr.on('data',c=>log+=c);child.on('exit',code=>{clearTimeout(timer);reject(Error('Fixture '+code+': '+log));});});
  browser=await chromium.launch({headless:true,channel:'chrome'});
  const page=await browser.newPage({viewport:{width:390,height:844},hasTouch:true});
  const errors=[];page.on('pageerror',e=>errors.push(e.message));page.setDefaultTimeout(15000);page.setDefaultNavigationTimeout(45000);
  await page.goto(base);const csrf=await page.locator('[name=csrf]').first().inputValue();
  await page.request.post(base+'/auth/local',{form:{csrf,mode:'login',identifier:'PreviewPlayer',password:'PreviewFixture!2026'}});
  await page.goto(base+'/city#city');await page.locator('#hud-research:not([data-job-state=loading])').waitFor();
  const route=async tab=>{await page.evaluate(tab=>{location.hash=tab;},tab);await page.locator(`#panel-dialog[open][data-panel="${tab}"]`).waitFor();await page.waitForTimeout(160);};
  const frame=async selector=>page.locator(selector).evaluate(e=>{const r=e.getBoundingClientRect();return {x:r.x,y:r.y,w:r.width,h:r.height,overflow:e.scrollWidth>e.clientWidth+2};});

  let state='ready';
  const baseline=await (await page.request.get(base+'/api/game/state')).json();
  await page.route('**/api/game/state',async route=>{
   const json=structuredClone(baseline),s=json.data;
   s.troop_queue=[];
   if(state.includes('locked')){for(const b of Object.values(s.buildings))b.level=1;s.research={};for(const t of s.troop_defs)t.unlocked=Number(t.tier)===1;}
   if(state.includes('running')){const utc=n=>new Date(Date.now()+n).toISOString().slice(0,19).replace('T',' ');s.troop_queue=s.troop_defs.filter(t=>Number(t.tier)===1).map((t,i)=>({id:990+i,city_id:s.city.id,troop_code:t.code,count:250,started_at:utc(-60000),finishes_at:utc(600000)}));}
   await route.fulfill({json});
  });
  const failures=[];
  for(state of ['ready','stats','locked','running','running-locked']){
   await page.evaluate(()=>location.hash='army');await page.reload();await page.locator('.training-school').waitFor();
   for(const school of ['barrack','archery_range','stable']){
    await page.locator('[data-action=training-school][data-id='+school+']').click();
    if(state.includes('locked'))await page.locator('[data-action=training-tier][data-id="2"]').click();
    if(state==='stats')await page.locator('.training-view-tabs [data-action=training-mode][data-id=stats]').first().click();
    for(const [width,height] of [[390,844],[320,568],[844,390],[568,320],[1280,800]]){
     await page.setViewportSize({width,height});await page.waitForTimeout(160);
     const issues=await page.locator('.training-school').evaluate(root=>{
      const visible=e=>e.getClientRects().length&&getComputedStyle(e).visibility!=='hidden';
      const box=e=>e.getBoundingClientRect(),problems=[],footer=box(root.querySelector('.training-schools'));
      const overlap=(a,b)=>Math.min(a.right,b.right)-Math.max(a.left,b.left)>1&&Math.min(a.bottom,b.bottom)-Math.max(a.top,b.top)>1;
      for(const selector of ['.training-workspace','.training-unit-heading','.training-stage-toolbar','.training-cost-grid','.training-quantity','.training-schools']){
       const elements=[...root.querySelector(selector).children].filter(visible);
       for(let i=0;i<elements.length;i++)for(let j=i+1;j<elements.length;j++)if(overlap(box(elements[i]),box(elements[j])))problems.push('overlap '+selector+': '+elements[i].className+' / '+elements[j].className);
      }
      const panel=box(root.closest('dialog'));
      for(const e of root.querySelectorAll('button,input,.training-running,.training-lock,.training-stats')){
       if(!visible(e))continue;const r=box(e);
       if(r.left<panel.left-1||r.right>panel.right+1||r.top<panel.top-1||r.bottom>panel.bottom+1)problems.push('outside '+e.className);
       if(!e.closest('.training-schools')&&r.bottom>footer.top+1)problems.push('footer '+e.className);
       if(e.matches('button,input')){const hit=document.elementFromPoint(r.x+r.width/2,r.y+r.height/2);if(hit&&!e.contains(hit))problems.push('covered '+e.className);}
      }
      return problems;
     });
     if(issues.length)failures.push({state,school,width,height,issues});
     if(school==='archery_range')await page.screenshot({path:path.join(out,state+'-'+width+'x'+height+'.png')});
    }
   }
   console.log('Checked',state);
  }
  await page.unrouteAll({behavior:'ignoreErrors'});
  assert.deepEqual(errors,[]);assert.deepEqual(failures,[]);console.log('PASS: 75 training layouts, three schools and five states, without covered controls or overlapping information.');
  for(const size of ['390x844','320x568','844x390','568x320','1280x800'])fs.copyFileSync(path.join(out,'ready-'+size+'.png'),path.join(root,'artifacts/mobile-pages/army-'+size+'.png'));
 }finally{await browser?.close();child.stdin.write('\n');await new Promise(resolve=>{child.once('exit',resolve);setTimeout(resolve,10000).unref();});}
})().catch(e=>{console.error(e);process.exitCode=1;});


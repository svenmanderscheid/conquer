'use strict';
// Real /city, a disposable account and database, and the real server apply endpoint.
const fs=require('fs'),path=require('path'),net=require('net'),assert=require('assert'),{spawn}=require('child_process');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const root=path.resolve(__dirname,'..'),output=path.join(root,'output/playwright/hunter-constellations');fs.mkdirSync(output,{recursive:true});
async function fixture(){
 const port=await new Promise(resolve=>{const s=net.createServer();s.listen(0,'127.0.0.1',()=>{const p=s.address().port;s.close(()=>resolve(p));});});
 const child=spawn(process.env.PHP_BINARY||'C:/xampp/php/php.exe',[path.join(root,'tools/preview-feature-fixture.php'),'--port='+port,'--talents','--speed-bonuses','--appearance'],{cwd:root,stdio:['pipe','pipe','pipe'],windowsHide:true});
 let log='';const ready=new Promise((resolve,reject)=>{const t=setTimeout(()=>reject(Error(log||'Fixture timeout')),60000);child.stdout.on('data',c=>{log+=c;if(log.includes('Synthetic preview ready')){clearTimeout(t);resolve();}});child.stderr.on('data',c=>log+=c);child.once('error',reject);child.once('exit',code=>{clearTimeout(t);reject(Error('Fixture exit '+code+' '+log));});});
 return{child,ready,base:'http://127.0.0.1:'+port};
}
(async()=>{const test=await fixture();let browser;try{
 await test.ready;browser=await chromium.launch({headless:true,channel:process.env.PLAYWRIGHT_CHANNEL||'chrome'});
 const page=await browser.newPage({viewport:{width:1280,height:800},hasTouch:true}),errors=[],report=[];page.on('pageerror',e=>errors.push(e.message));page.setDefaultTimeout(15000);
 await page.goto(test.base+'/?zugang=login',{waitUntil:'domcontentloaded',timeout:60000});await page.locator('[name="identifier"],[name="username"]').first().fill('PreviewPlayer');await page.locator('[name="password"]').fill('PreviewFixture!2026');
 await Promise.all([page.waitForURL('**/city',{timeout:60000,waitUntil:'domcontentloaded'}),page.locator('form[action$="/auth/local"] button[type="submit"],#auth-submit').first().click()]);await page.locator('#app-start').waitFor({state:'detached'});
 await page.waitForFunction(()=>document.querySelector('#player-hud-name')?.textContent.includes('PreviewPlayer'));
 const api=route=>page.evaluate(async route=>(await fetch((window.CONQUER_BASE||'')+'/api/'+route).then(r=>r.json())).data,route);
 const before=(await api('kingdom/state')).profile;
 async function close(){for(let i=0;i<4&&await page.locator('dialog[open]').count();i++)await page.keyboard.press('Escape');}
 async function open(){await close();await page.locator('#hud-menu').click();await page.locator('#game-dialog [data-action="dialog-tab"][data-id="mastery"]').click();await page.locator('.talent-tabs button').first().waitFor();}
 async function select(code){await page.locator('[data-action="talent-select"][data-id="'+code+'"]').click();await page.locator('.talent-sheet').waitFor();}
 async function learn(code){await page.locator('[data-action="talent-add"][data-id="'+code+'"]').click();assert.equal(await page.locator('.talent-sheet').count(),0,'symbol adds directly '+code);}
 let applies=0;page.on('request',r=>{if(r.method()==='POST'&&r.url().endsWith('/api/progression/action'))applies++;});
 await open();assert.equal(await page.locator('.talent-tabs button').count(),6);
 for(const b of ['infantry','archer','cavalry','monster','combat','gathering']){await page.locator('[data-action="talent-branch"][data-id="'+b+'"]').click();assert.equal(await page.locator('.talent-star').count(),13);assert.equal(await page.locator('.talent-star:not(.is-locked)').count(),1,'entry only '+b);assert.equal(await page.locator('.talent-lines path').count(),18);}
 await page.locator('[data-action="talent-branch"][data-id="monster"]').click();await page.locator('[data-action="talent-add"][data-id="monster_0"]').click();assert(await page.locator('[data-action="talent-plus"]').isDisabled());await page.keyboard.press('Escape');
 await learn('monster_9');await learn('monster_0');await learn('monster_0');
 assert.equal(await page.locator('.talent-star[data-code="monster_0"] .talent-star-rank').textContent(),'2 / 5','each symbol click adds one draft rank');
 assert.equal((await api('progression/state')).mastery.spent,0,'clicks do not persist');assert.equal(applies,0);
 await page.locator('[data-action="talent-undo"]').click();assert.equal(await page.locator('.talent-points strong').textContent(),'69');assert(await page.locator('[data-action="talent-undo"]').isDisabled());assert(await page.locator('[data-action="talent-apply"]').isDisabled());assert.equal(applies,0,'reset is local');
 await learn('monster_9');assert.equal(await page.locator('.talent-star:not(.is-locked)').count(),4,'one entry opens three routes');
 await page.locator('[data-action="talent-add"][data-id="monster_9"]').click();assert((await page.locator('.talent-effect').textContent()).includes('+10 AP'));assert(await page.locator('[data-action="talent-plus"]').isDisabled(),'waypoint has one rank');assert.equal(await page.locator('.talent-rank').textContent(),'1 / 1');await page.keyboard.press('Escape');
 await learn('monster_0');await learn('monster_10');await learn('monster_3');assert.equal(await page.locator('.talent-points strong').textContent(),'65');
 await select('monster_9');assert(await page.locator('[data-action="talent-minus"]').isDisabled(),'cannot sever the root');await page.keyboard.press('Escape');
 await learn('monster_1');await select('monster_10');await page.locator('[data-action="talent-minus"]').click();await page.keyboard.press('Escape');await select('monster_0');assert.equal(await page.locator('[data-action="talent-minus"]').isDisabled(),false,'alternate route remains');await page.keyboard.press('Escape');
 await page.locator('[data-action="talent-branch"][data-id="cavalry"]').click();await page.locator('[data-action="talent-branch"][data-id="monster"]').click();assert.equal(await page.locator('.talent-points strong').textContent(),'65','draft survives tab change');
 const save=page.locator('[data-action="talent-apply"]');await save.click();await page.waitForFunction(()=>document.querySelector('[data-action="talent-apply"]')?.disabled&&document.querySelector('.talent-save-state')?.textContent.includes('Saved'));
 const saved=(await api('progression/state')).mastery;assert.equal(saved.spent,4);assert.equal(saved.ranks.monster_9,1);assert.equal(saved.ranks.monster_0,1);assert.equal(saved.ranks.monster_3,1);
 const after=(await api('kingdom/state')).profile;assert.equal(after.action_points_max,before.action_points_max+10);assert.equal(after.action_points,before.action_points,'entry does not refill AP');
 await open();assert.equal(await page.locator('.talent-points strong').textContent(),'65','real handler persisted plan');
 await learn('monster_0');await page.locator('[data-action="talent-branch"][data-id="infantry"]').click();await learn('infantry_9');await page.locator('[data-action="talent-undo"]').click();
 await page.locator('[data-action="talent-branch"][data-id="monster"]').click();assert.equal(await page.locator('.talent-star[data-code="monster_0"] .talent-star-rank').textContent(),'1 / 5','reset preserves saved ranks');assert.equal(await page.locator('.talent-points strong').textContent(),'65','reset discards drafts in every branch');assert.equal(applies,1);assert.deepEqual((await api('progression/state')).mastery.ranks,saved.ranks);
 for(const locale of ['en','de','fr']){
  await close();if(await page.evaluate(()=>ConquerLocale.locale)!==locale){await Promise.all([page.waitForEvent('load',{timeout:60000}),page.evaluate(locale=>ConquerLocale.setLocale(locale),locale)]);await page.waitForFunction(locale=>window.ConquerLocale?.locale===locale,locale);await page.locator('#app-start').waitFor({state:'detached'});}
  for(const [width,height] of [[1280,800],[390,844],[320,568],[844,390],[568,320]]){
   await page.setViewportSize({width,height});await open();
   for(const b of ['infantry','archer','cavalry','monster','combat','gathering']){
    await page.locator('[data-action="talent-branch"][data-id="'+b+'"]').click();
    await page.waitForFunction(()=>[...document.querySelectorAll('.talent-tree-scroll img')].every(i=>i.complete&&i.naturalWidth)&&[...document.querySelectorAll('.talent-lines path')].every(p=>Boolean(p.getAttribute('d'))));
    const metric=await page.locator('.talent-tree-scroll').evaluate(el=>({overflow:el.scrollWidth>el.clientWidth+1,pageOverflow:document.documentElement.scrollWidth>innerWidth+1,missing:[...el.querySelectorAll('img')].some(i=>!i.complete||!i.naturalWidth),paths:[...el.querySelectorAll('path')].every(p=>Boolean(p.getAttribute('d')))}));
    assert.deepEqual(metric,{overflow:false,pageOverflow:false,missing:false,paths:true},locale+' '+b+' '+width+'x'+height+' '+JSON.stringify(metric));
    const top=(await save.boundingBox()).y;await page.locator('.talent-tree-scroll').evaluate(e=>e.scrollTop=e.scrollHeight);assert.equal((await save.boundingBox()).y,top,'fixed save');
    const reset=await page.locator('[data-action="talent-undo"]').boundingBox();assert(reset&&reset.x>=0&&reset.y>=0&&reset.x+reset.width<=width&&reset.y+reset.height<=height&&reset.height>=44,'reset remains reachable '+locale+' '+width+'x'+height);
    await select(b+'_8');assert.equal(await page.locator('.talent-sheet [data-action="talent-plus"]').isDisabled(),true,'unconnected master remains locked');await page.keyboard.press('Escape');assert.equal(await page.locator('#panel-dialog').evaluate(d=>d.open),true);
    await page.locator('.talent-tree-scroll').evaluate(e=>e.scrollTop=0);if(b==='monster'){
     await page.screenshot({path:path.join(output,locale+'-'+width+'x'+height+'.png')});await learn('monster_0');
     for(const action of ['undo','apply']){const target=page.locator('[data-action="talent-'+action+'"]');assert.equal(await target.isDisabled(),false);assert(await target.evaluate(button=>{const r=button.getBoundingClientRect();return r.x>=0&&r.y>=0&&r.right<=innerWidth&&r.bottom<=innerHeight&&r.height>=44&&button.contains(document.elementFromPoint(r.x+r.width/2,r.y+r.height/2));}),'draft '+action+' is reachable');}
     const style=await save.evaluate(button=>{const s=getComputedStyle(button);return{color:s.color,font:s.fontFamily};});assert.equal(style.color,'rgb(32, 59, 24)');assert(style.font.includes('Bree Serif'),'save uses the shared main-action typography');
     await page.screenshot({path:path.join(output,locale+'-'+width+'x'+height+'-unsaved.png')});await page.locator('[data-action="talent-undo"]').click();
    }report.push({locale,b,width,height,...metric});
   }
  }
 }
 assert.equal(applies,1,'viewport checks only change local drafts');assert.deepEqual(errors,[]);fs.writeFileSync(path.join(output,'report.json'),JSON.stringify({saved,applies,layouts:report,errors},null,2));console.log('PASS actual /city: direct symbol allocation, local reset with saved ranks intact, six constellations, one-rank paths, alternate routes, real save/AP reserve and 90 language/branch/viewport combinations. '+output);
}finally{await browser?.close();if(test.child.exitCode===null)await new Promise(resolve=>{test.child.once('exit',resolve);test.child.stdin.end('\n');});}})().catch(e=>{console.error(e);process.exitCode=1;});

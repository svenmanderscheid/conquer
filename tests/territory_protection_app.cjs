'use strict';
// Run against --territory --territory-protection on a disposable local database.
const assert=require('assert'),fs=require('fs'),path=require('path');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const base=process.env.TERRITORY_FIXTURE_URL||'http://127.0.0.1:18948';assert(/^http:\/\/127\.0\.0\.1:\d+$/.test(base));
const out=path.resolve(__dirname,'../output/playwright/territory-protection');fs.mkdirSync(out,{recursive:true});
(async()=>{const browser=await chromium.launch({headless:true,channel:'chrome'});try{
 const page=await browser.newPage({hasTouch:true}),errors=[];page.on('pageerror',e=>errors.push(e.message));page.setDefaultTimeout(20000);
 await page.goto(base+'/?zugang=login');await page.locator('[name="identifier"],[name="username"]').fill('PreviewPlayer');await page.locator('[name="password"]').fill('PreviewFixture!2026');
 await Promise.all([page.waitForURL('**/city'),page.locator('form[action$="/auth/local"] button[type="submit"]').click()]);await page.waitForFunction(()=>document.querySelector('#player-hud-name')?.textContent.includes('PreviewPlayer'));
 await page.evaluate(()=>{const render=ConquerWorld.render;window.qaClockOffset=0;ConquerWorld.render=options=>{const realNow=options.qaRealNow||options.now;options.qaRealNow=realNow;options.now=()=>realNow()+window.qaClockOffset;window.qaMapOptions=options;return render(options);};});
 const payload=await (await page.request.get(base+'/api/territory/state?world_id=1')).json();assert(payload.ok);const targets=payload.data.targets;
 const commune=targets.find(t=>t.kind==='commune'&&Number(t.owner_alliance_id)===2),shrine=targets.find(t=>t.kind==='canton'&&Number(t.owner_alliance_id)===2),neutral=targets.find(t=>t.kind==='commune'&&!t.owner_alliance_id),neutralShrine=targets.find(t=>t.kind==='canton'&&!t.owner_alliance_id);
 assert(commune&&shrine&&neutral&&neutralShrine);assert.equal(commune.protection.state,'protected');
 async function select(target){await page.evaluate(t=>ConquerWorld.focus(t.x,t.y),target);const marker=page.locator(`[data-atlas-target="territory:${target.id}"]`);await marker.waitFor();await page.waitForTimeout(180);if(page.viewportSize().width<=390)await marker.tap();else await marker.click();try{await page.waitForSelector('.atlas-target-actions:not([hidden])');}catch(error){await page.screenshot({path:path.join(out,'selection-failure.png')});console.log('Selection failure',target.id,await marker.boundingBox(),errors);throw error;}return marker;}
 for(const size of [{width:1280,height:800},{width:390,height:844},{width:320,height:568},{width:844,height:390},{width:568,height:320}].filter(size=>!process.env.PROTECTION_VIEWPORT||size.width===Number(process.env.PROTECTION_VIEWPORT))){
  await page.setViewportSize(size);await page.locator('#navigation [data-id="world"]').click();await page.waitForSelector('.atlas-shell.is-luxembourg');
  for(const target of [commune,shrine,neutral,neutralShrine]){
   const marker=await select(target),timed=!!target.owner_alliance_id;
   assert.equal(await marker.locator('.atlas-landmark-protection').count(),timed?1:0);assert.equal(await marker.locator('.atlas-protection-badge').count(),timed?1:0);
   const text=await page.locator('.atlas-conquest-status').innerText();assert.match(text,timed?/Protected · \d{2}:\d{2}:\d{2}/:/Neutral · attack anytime/);
   if(target.kind==='canton')assert.match(text,/Communes controlled: \d+\/\d+ required/);
   const layout=await page.locator('.atlas-target-actions').evaluate(menu=>{const r=menu.getBoundingClientRect(),marker=document.querySelector('.atlas-marker.is-selected'),boxes=[marker.querySelector('img'),...marker.querySelectorAll('.atlas-landmark-protection,.atlas-protection-badge')].map(e=>e.getBoundingClientRect());return {visible:r.left>=-1&&r.top>=-1&&r.right<=innerWidth+1&&r.bottom<=innerHeight+1,clear:boxes.every(b=>Math.min(r.right,b.right)<=Math.max(r.left,b.left)+1||Math.min(r.bottom,b.bottom)<=Math.max(r.top,b.top)+1),artVisible:boxes.every(b=>b.left>=-1&&b.top>=-1&&b.right<=innerWidth+1&&b.bottom<=innerHeight+1),hit:[...menu.querySelectorAll('button')].every(e=>{const b=e.getBoundingClientRect();return b.height>=44&&e.contains(document.elementFromPoint(b.x+b.width/2,b.y+b.height/2));})};});
   if(!Object.values(layout).every(Boolean)){await page.screenshot({path:path.join(out,'layout-failure.png')});console.log(await marker.evaluate(m=>[m.querySelector('img'),...m.querySelectorAll('.atlas-landmark-protection,.atlas-protection-badge')].map(e=>({class:e.className,rect:e.getBoundingClientRect().toJSON()}))));}
   assert(Object.values(layout).every(Boolean),JSON.stringify({size,target:target.kind,layout}));
   if(timed){assert.match(await marker.getAttribute('aria-label'),/Protected/);assert.equal(await marker.locator('.atlas-landmark-protection').evaluate(e=>getComputedStyle(e).pointerEvents),'none');assert(await marker.locator('.atlas-protection-badge').evaluate(badge=>{const b=badge.getBoundingClientRect();return [...document.querySelectorAll('.topbar,.hud-edge-tools button,#navigation,.world-chat')].filter(e=>{const c=getComputedStyle(e);return !e.hidden&&c.display!=='none'&&c.visibility!=='hidden';}).every(e=>{const r=e.getBoundingClientRect();return Math.min(b.right,r.right)<=Math.max(b.left,r.left)+1||Math.min(b.bottom,r.bottom)<=Math.max(b.top,r.top)+1;});}),'shield timer remains clear of HUD controls');}
   await page.screenshot({path:path.join(out,`${target.kind}-${timed?'protected':'neutral'}-${size.width}x${size.height}.png`)});
   await page.locator('.atlas-target-actions [data-atlas="clear"]').tap();
  }
  await page.locator('#navigation [data-id="city"]').click();await page.waitForSelector('.painted-village-building');await page.screenshot({path:path.join(out,`city-${size.width}x${size.height}.png`)});
  assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));console.log(`PASS shield, countdown, majority, touch and main city ${size.width}x${size.height}`);
 }
 await page.setViewportSize({width:390,height:844});await page.locator('#navigation [data-id="world"]').click();let marker=await select(commune);
 const before=await marker.locator('.atlas-protection-badge').innerText();await page.waitForTimeout(2100);assert.notEqual(await marker.locator('.atlas-protection-badge').innerText(),before,'countdown ticks without opening details');
 await page.locator('.atlas-target-actions [data-action="landmark-rally"]').tap();await page.waitForSelector('.landmark-info');assert.equal(await page.locator('[data-form="territory-army"]').count(),0,'protected target still uses fresh server eligibility');await page.locator('.landmark-info [data-action="close-dialog"]').tap();
 const setClock=async value=>{await page.evaluate(value=>{const baseNow=window.qaMapOptions.now()-window.qaClockOffset;window.qaClockOffset=value-baseNow;ConquerWorld.render(window.qaMapOptions);},value);await page.waitForTimeout(150);};
 await select(commune);const start=Date.parse(commune.next_window.starts_at),end=Date.parse(commune.next_window.ends_at);
 await setClock(start);assert.equal(await marker.locator('.atlas-landmark-protection').count(),0);assert.match(await page.locator('.atlas-conquest-status').innerText(),/Attack window open/);
 await setClock(end);assert.equal(await marker.locator('.atlas-landmark-protection').count(),1);assert.match(await marker.locator('.atlas-protection-badge').innerText(),/Protected · 23:00:/);
 await page.emulateMedia({reducedMotion:'reduce'});assert.equal(await marker.locator('.atlas-landmark-protection').evaluate(e=>getComputedStyle(e).animationName),'none');
 await page.locator('.atlas-target-actions [data-atlas="clear"]').tap();
 await page.evaluate(()=>document.addEventListener('pointerup',()=>{const until=performance.now()+170;while(performance.now()<until){}},{once:true}));
 await select(commune);await page.waitForTimeout(300);assert(await page.locator('.atlas-target-actions').isVisible(),'one touch stays selected after a compatibility click delayed by expensive rendering');
 assert.deepEqual(errors,[]);console.log('PASS live countdown, server lock, exact opening/closing, reduced motion and browser errors');
}finally{await browser.close();}})().catch(error=>{console.error(error);process.exit(1);});

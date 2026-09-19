'use strict';
// Run with tools/preview-feature-fixture.php --port=18964 --effects --hud --chat.
const fs=require('fs'),path=require('path'),os=require('os'),assert=require('assert/strict');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const base=process.env.EFFECT_FIXTURE_URL||'http://127.0.0.1:18964';
assert(/^http:\/\/127\.0\.0\.1:\d+$/.test(base));
const output=fs.mkdtempSync(path.join(os.tmpdir(),'conquer-active-effects-'));
(async()=>{
 const browser=await chromium.launch({headless:true,channel:process.env.PLAYWRIGHT_CHANNEL||'chrome'});
 try{
  const page=await browser.newPage({viewport:{width:1280,height:800},hasTouch:true}),errors=[],failures=[];
  page.on('pageerror',e=>errors.push(e.message));page.setDefaultTimeout(20000);
  let mode='normal';
  await page.route('**/api/game/state*',async route=>{
   const response=await route.fetch(),json=await response.json();
   if(json.data?.active_effects){
    if(mode==='debuff-only')json.data.active_effects=json.data.active_effects.filter(e=>e.kind==='debuff');
    if(mode==='expiring')json.data.active_effects=json.data.active_effects.map(e=>({...e,expires_at:new Date((json.data.server_time+2)*1000).toISOString()}));
    if(mode==='many')json.data.active_effects=Array.from({length:20},(_,i)=>({...json.data.active_effects.find(e=>e.kind==='bonus'),id:'test:'+i}));
   }
   await route.fulfill({response,json});
  });
  await page.goto(base);await page.locator('[data-mode="login"]').click();
  await page.locator('[name="username"]').fill('PreviewPlayer');await page.locator('[name="password"]').fill('PreviewFixture!2026');
  await Promise.all([page.waitForURL('**/city'),page.locator('#auth-submit').click()]);
  const up=page.locator('#hud-bonuses'),down=page.locator('#hud-debuffs'),drawer=page.locator('#active-effects-drawer'),dialog=page.locator('#game-dialog');
  await up.waitFor();await down.waitFor();
  assert.match(await up.getAttribute('aria-label'),/\(2\)/);assert.match(await down.getAttribute('aria-label'),/\(1\)/);
  for(const [width,height] of [[1280,800],[820,720],[390,844],[320,568],[568,320],[844,390]]){
   await page.setViewportSize({width,height});
   const layout=await page.evaluate(()=>{
    const bad=[],up=document.querySelector('#hud-bonuses'),down=document.querySelector('#hud-debuffs');
    const a=up.getBoundingClientRect(),b=down.getBoundingClientRect();
    if(a.x!==b.x||a.bottom>b.top)bad.push('Debuff is not below the bonus arrow');
    const visible=e=>{const r=e.getBoundingClientRect();return r.width&&r.height&&getComputedStyle(e).visibility!=='hidden';};
    const other=[...document.querySelectorAll('.topbar button:not(.hud-effect-button),#hud-left-tools button,.hud-right-tools button')].filter(visible);
    for(const e of [up,down]){const r=e.getBoundingClientRect();
     if(r.x<0||r.y<0||r.right>innerWidth||r.bottom>innerHeight||r.width<44||r.height<44)bad.push(e.id+' outside viewport or too small');
     for(const n of other){const s=n.getBoundingClientRect();if(r.left<s.right-1&&r.right>s.left+1&&r.top<s.bottom-1&&r.bottom>s.top+1)bad.push(e.id+' overlaps '+(n.id||n.getAttribute('aria-label')));}
    }
    return bad;
   });
   if(layout.length)failures.push({width,height,layout});
   await page.screenshot({path:path.join(output,`${width}x${height}-hud.png`)});
   await up.tap();await drawer.waitFor();await page.locator('.active-effect').first().waitFor();
   assert.equal(await dialog.getAttribute('open'),null,'Effects unfold beside the HUD without opening a dialog');
   assert.equal(await up.getAttribute('aria-expanded'),'true');
   await page.waitForFunction(()=>[...document.querySelectorAll('.active-effect img')].every(img=>img.complete&&img.naturalWidth>0));
   assert.equal(await page.locator('.active-effect').count(),2);
   assert.match(await drawer.innerText(),/Baugeschwindigkeit \+5 %/);
   assert.match(await drawer.innerText(),/Goldproduktion \+20 %/);
   assert.match(await page.locator('.active-effect time').first().innerText(),/^\d+:\d{2}:\d{2}$/);
   const panelLayout=await drawer.evaluate(e=>{const r=e.getBoundingClientRect(),row=e.querySelector('.active-effect')?.getBoundingClientRect(),hits=[[r.left+r.width/2,r.top+18],[row?.left+row?.width/2,row?.top+row?.height/2]].map(([x,y])=>document.elementFromPoint(x,y));return {rect:{left:r.left,top:r.top,right:r.right,bottom:r.bottom,width:r.width,height:r.height},viewport:{width:innerWidth,height:innerHeight},inside:r.left>=0&&r.top>=0&&r.right<=innerWidth&&r.bottom<=innerHeight,front:hits.every(hit=>e.contains(hit)),compact:r.width<=304&&r.height<=264,overflow:e.scrollWidth>e.clientWidth+2,images:[...e.querySelectorAll('img')].every(img=>img.complete&&img.naturalWidth>0)};});
   assert(panelLayout.inside&&panelLayout.front&&panelLayout.compact&&!panelLayout.overflow&&panelLayout.images,JSON.stringify({width,height,panelLayout}));
   await page.screenshot({path:path.join(output,`${width}x${height}-bonuses.png`)});
   const firstTime=await page.locator('.active-effect time').first().innerText();
   await page.waitForFunction(t=>document.querySelector('.active-effect time').textContent!==t,firstTime);
   await up.tap();assert(await drawer.isHidden());
   await down.tap();assert.match(await drawer.innerText(),/Forschungsgeschwindigkeit -15 %/);
   await page.screenshot({path:path.join(output,`${width}x${height}-debuffs.png`)});
   await page.keyboard.press('Escape');await drawer.waitFor({state:'hidden'});
  }
  assert.deepEqual(failures,[]);
  console.log('PASS inline effect drawer and arrow placement in six viewports');
  await page.setViewportSize({width:390,height:844});
  mode='debuff-only';await page.waitForFunction(()=>document.querySelector('#hud-bonuses').hidden);
  assert(await down.isVisible());
  await page.screenshot({path:path.join(output,'debuff-only.png')});
  mode='many';await up.waitFor();await up.tap();
  await page.waitForFunction(()=>document.querySelectorAll('.active-effect').length===20);
  assert(await page.locator('.active-effects-list').evaluate(e=>e.scrollHeight>e.clientHeight));
  await page.locator('.active-effects-list').evaluate(e=>e.scrollTop=100);
  await page.waitForTimeout(1200);assert(await page.locator('.active-effects-list').evaluate(e=>e.scrollTop>=100));
  await page.keyboard.press('Escape');await drawer.waitFor({state:'hidden'});
  mode='expiring';await page.waitForFunction(()=>document.querySelector('#hud-bonuses').getAttribute('aria-label').includes('(2)'));
  await up.tap();await page.waitForFunction(()=>document.querySelector('#active-effects-drawer').hidden);
  assert(await up.isHidden());assert(await down.isHidden());
  mode='normal';await up.waitFor();
  await page.locator('#navigation [data-id="world"]').click();await up.tap();assert.equal(await page.locator('.active-effect').count(),2);assert.equal(await dialog.getAttribute('open'),null);
  assert.deepEqual(errors,[]);assert.deepEqual(failures,[]);
  console.log('PASS live effect snapshot, touch targets, bonus/debuff separation, countdown, expiry, scrolling, Escape, city/world and six viewports. Screenshots: '+output);
 } finally {for(const context of browser.contexts())for(const page of context.pages())await page.unrouteAll({behavior:'ignoreErrors'});await browser.close();}
})().catch(error=>{console.error(error);console.error('Screenshots: '+output);process.exitCode=1;});

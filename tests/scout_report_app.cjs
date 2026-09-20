'use strict';
// Run against tools/preview-feature-fixture.php --scout-reports --port=18983.
const fs=require('fs'),path=require('path'),assert=require('assert/strict');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'C:/Users/svenm/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const base=process.env.SCOUT_REPORT_URL||'http://127.0.0.1:18983';
assert(/^http:\/\/127\.0\.0\.1:\d+$/.test(base),'Disposable preview required');
const output=path.resolve('artifacts/scout-report');fs.mkdirSync(output,{recursive:true});
(async()=>{
 const browser=await chromium.launch({headless:true,executablePath:'C:/Program Files/Google/Chrome/Application/chrome.exe'});
 try{
  const context=await browser.newContext({viewport:{width:1280,height:900},hasTouch:true});
  await context.addInitScript(()=>{if(location.pathname.endsWith('/city')&&!location.hash)history.replaceState(null,'','#world');});
  const page=await context.newPage(),errors=[];page.on('pageerror',e=>errors.push(e.message));page.setDefaultTimeout(20000);
  await page.goto(base+'/?zugang=login');await page.locator('[name="username"]').fill('PreviewPlayer');await page.locator('[name="password"]').fill('PreviewFixture!2026');
  await Promise.all([page.waitForURL(url=>url.pathname==='/city'),page.locator('#auth-submit').click()]);
  await page.locator('#navigation [data-id="reports"]').click();await page.locator('.mail-open').first().waitFor();
  const open=async index=>{await page.locator('.mail-open').nth(index).click();await page.locator('.scout-report').waitFor();await page.waitForFunction(()=>!document.querySelector('[data-action="mailbox-detail-star"]')?.disabled);};
  const close=async()=>{await page.locator('[data-action="mailbox-back"]').click();await page.waitForFunction(()=>!document.querySelector('#game-dialog').open&&!history.state?.conquerScoutReport);};
  await open(0);
  assert.match(await page.locator('.sr-target').innerText(),/275\.842\.667/);
  assert.equal(await page.locator('.sr-resource>b').first().innerText(),'677.075.717');
  assert.equal(await page.locator('.sr-troops .sr-troop').count(),8);
  assert.match(await page.locator('.sr-troops .sr-total').innerText(),/4\.142\.286/);
  assert.equal(await page.locator('.sr-reinforcements .sr-troop').count(),2);
  assert.equal(await page.locator('.sr-relic img').count(),6);
  assert.match(await page.locator('.sr-relic').first().innerText(),/Stufe 7/,'Target level must not come from the viewer');
  assert.match(await page.locator('.sr-mastery .sr-total').innerText(),/49/);
  for(const [width,height]of [[1280,900],[390,844],[320,568],[568,320],[844,390]]){
   await page.setViewportSize({width,height});await page.locator('.sr-scroll').evaluate(e=>e.scrollTop=0);
   await page.screenshot({path:path.join(output,`${width}x${height}-top.png`)});
   const issues=await page.evaluate(()=>{
    const issues=[],root=document.querySelector('.scout-report'),scroll=root.querySelector('.sr-scroll');
    for(const el of [root,scroll,...root.querySelectorAll('.sr-section,.sr-resource,.sr-overview')])if(el.scrollWidth>el.clientWidth+1)issues.push('Horizontal overflow: '+el.className);
    if(scroll.clientHeight<100)issues.push('Too little reading space');
    for(const el of document.querySelectorAll('#game-dialog .dialog-close,.sr-footer button')){
     const r=el.getBoundingClientRect(),hit=document.elementFromPoint(r.x+r.width/2,r.y+r.height/2);
     if(r.x<0||r.y<0||r.right>innerWidth||r.bottom>innerHeight)issues.push('Action outside viewport');
     if(hit&&!el.contains(hit))issues.push('Action covered');
    }
    return issues;
   });assert.deepEqual(issues,[],`${width}×${height}`);
   for(const key of ['troops','mastery','equipment','bonuses']){await page.locator('.sr-'+key).evaluate(el=>el.parentElement.scrollTop=el.offsetTop-el.parentElement.offsetTop);await page.screenshot({path:path.join(output,`${width}x${height}-${key}.png`)});}
  }
  await page.waitForFunction(()=>[...document.querySelectorAll('.scout-report img')].every(img=>img.complete&&img.naturalWidth>0));
  const scroll=await page.locator('.sr-scroll').evaluate(e=>e.scrollTop);await page.locator('[data-action="mailbox-detail-star"]').click();
  await page.waitForFunction(()=>document.querySelector('[data-action="mailbox-detail-star"]')?.getAttribute('aria-pressed')==='true');
  assert.equal(await page.locator('.sr-scroll').evaluate(e=>e.scrollTop),scroll,'Favorite preserves scroll');
  await close();await open(1);assert.match(await page.locator('.sr-troops').innerText(),/Keine Truppen/);assert.equal(await page.locator('.sr-slot-empty').count(),6);assert.equal(await page.locator('.sr-resource>b').first().innerText(),'0');await close();
  await open(2);assert.match(await page.locator('.sr-target').innerText(),/<img src=x/);assert.equal(await page.locator('.scout-report img[src="x"]').count(),0);assert.equal(await page.locator('.sr-resource>b').first().innerText(),'—');assert.match(await page.locator('.sr-equipment').innerText(),/nicht erfasst/);await close();
  await open(3);assert.match(await page.locator('.sr-blocked').innerText(),/Spähschutz aktiv/);assert.equal(await page.locator('.sr-resource,.sr-troop,.sr-relic').count(),0);await page.keyboard.press('Escape');await page.waitForFunction(()=>!document.querySelector('#game-dialog').open&&!history.state?.conquerScoutReport);
  await open(0);await page.goBack();await page.waitForFunction(()=>!document.querySelector('#game-dialog').open);assert.equal(await page.locator('#panel-dialog').evaluate(e=>e.open),true,'Browser back returns to Post');
  await page.locator('.mail-open').nth(4).click();await page.locator('.mail-detail').waitFor();assert.equal(await page.locator('.scout-report').count(),0,'Defender gets only a notification');assert.doesNotMatch(await page.locator('.mail-detail').innerText(),/275\.842\.667/);await close();
  await page.evaluate(()=>{const b=document.createElement('button');b.id='scout-shortcut-test';b.dataset.action='report';b.dataset.id='1';b.textContent='Bericht öffnen';document.querySelector('#content').prepend(b);});
  await page.locator('#scout-shortcut-test').click();await page.locator('.scout-report').waitFor();assert.equal(await page.locator('.sr-resource>b').first().innerText(),'677.075.717','Direct report uses the same layout');await close();
  assert.deepEqual(errors,[]);console.log('PASS scout report: 5 screen sizes, historical values, resource protection, full/empty/old/blocked reports, portraits, relics, favorites, scroll, escaping, Escape, browser back, defender privacy and direct report. '+output);
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});

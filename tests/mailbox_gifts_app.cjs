'use strict';
// Read-only presentation fixtures in the actual app; no gifts are issued to players.
const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const base=process.env.MAILBOX_FIXTURE_URL||'http://127.0.0.1:18964';
assert(/^http:\/\/127\.0\.0\.1:\d+$/.test(base),'Disposable local preview required');
const output=path.resolve(__dirname,'../output/playwright/mailbox-gifts');
fs.mkdirSync(output,{recursive:true});
const sizes=[[1280,800],[390,844],[320,568],[844,390],[568,320]];
const amounts=[20,12,8,6,4,3,3];
const codes=[10103001,10103002,10103003,10103004,10103005,10103021,10103022];
const headings={en:'Your gift',de:'Dein Geschenk',fr:'Votre cadeau'};
(async()=>{
 const browser=await chromium.launch({headless:true,executablePath:process.env.BROWSER_EXECUTABLE_PATH||'C:/Program Files/Google/Chrome/Application/chrome.exe'});
 const results=[];
 try{
  for(const locale of ['en','de','fr']){
   const context=await browser.newContext({viewport:{width:1280,height:800},hasTouch:true});
   await context.addInitScript(locale=>{localStorage.setItem('conquer.locale',locale);document.cookie='conquer_locale='+locale+'; Path=/; SameSite=Lax';},locale);
   const page=await context.newPage(),errors=[];page.on('pageerror',e=>errors.push(e.message));
   await page.goto(base+'/?zugang=login');
   await page.locator('[name=identifier], [name=username]').fill('PreviewPlayer');
   await page.locator('[name=password]').fill('PreviewFixture!2026');
   await Promise.all([page.waitForURL('**/city'),page.locator('form[action$="/auth/local"] button[type=submit]').click()]);
   await page.locator('#navigation [data-id=reports]').click();
   await page.locator('[data-action=mailbox-tab][data-id=system]').click();
   await page.locator('.mail-card').filter({hasText:'Ein Dankeschön aus dem Königreich'}).waitFor();
   let variant='multiple';
   await page.route('**/api/mailbox/message?*',async route=>{
    const response=await route.fetch(),json=await response.json();
    if(json.data.source==='admin_gift'){
     json.data.subject='Happy Research Day';json.data.body='';
     json.data.metadata.rewards=variant==='single'?{gems:100,item_code:10103021,quantity:6}:variant==='unknown'?{items:[{item_code:2147483000,quantity:2,name:'<script>bad()</script>'},{item_code:2147483001,quantity:3}]}:{food:500000,lumber:500000,stone:500000,gold:500000,items:codes.map((item_code,i)=>({item_code,quantity:amounts[i],name:'Beschleuniger'}))};
    }
    await route.fulfill({response,json});
   });
   for(const [width,height]of sizes){
    await page.setViewportSize({width,height});
    await page.locator('.mail-card').filter({hasText:'Ein Dankeschön aus dem Königreich'}).locator('.mail-open').click();
    await page.locator('.mail-reward-grid').waitFor();
    await page.waitForFunction(()=>[...document.querySelectorAll('.mail-reward-grid img')].every(img=>img.complete&&img.naturalWidth>0));
    assert.equal(await page.locator('.mail-reward-receipt h3').innerText(),headings[locale]);
    assert.equal(await page.locator('.mail-letter-body').count(),0,'Empty body adds no gap');
    assert.equal(await page.locator('.mail-reward-card').count(),11);
    for(let i=0;i<codes.length;i++){
     const tile=page.locator(`[data-reward-code="${codes[i]}"]`);
     assert.equal(await tile.getAttribute('data-reward-quantity'),String(amounts[i]));
     assert.ok((await tile.locator('.speedup-stamp').innerText()).length,'Speedup duration is visible');
     assert.notEqual(await tile.locator('.mail-reward-name').innerText(),'Beschleuniger','Catalog provides specific item name');
    }
    assert.equal(await page.locator('[data-action=mailbox-claim]').count(),0,'Credited gift cannot be claimed again');
    await page.screenshot({path:path.join(output,`${locale}-${width}x${height}.png`)});
    assert.deepEqual(await page.evaluate(()=>{
     const bad=[],dialog=document.querySelector('#game-dialog'),scroll=document.querySelector('.mail-detail-scroll');
     if(scroll.scrollWidth>scroll.clientWidth+1)bad.push('Horizontal overflow');
     if(scroll.clientHeight<60)bad.push('Scrollable content too short');
     if(scroll.scrollHeight>scroll.clientHeight&&getComputedStyle(scroll).overflowY!=='auto')bad.push('Scrolling disabled');
     for(const button of dialog.querySelectorAll('button')){
      if(!button.checkVisibility())continue;
      const r=button.getBoundingClientRect();if(r.height<44){const style=getComputedStyle(button);bad.push('Touch target too short: '+button.className+' '+r.height+' css '+style.height+' transform '+style.transform+' zoom '+style.zoom+' parent '+getComputedStyle(dialog).transform);}
      if(r.left<0||r.top<0||r.right>innerWidth+1||r.bottom>innerHeight+1)bad.push('Action outside viewport');
      const hit=document.elementFromPoint(r.x+r.width/2,r.y+r.height/2);if(!button.contains(hit))bad.push('Action covered: '+button.className+' by '+hit?.className);
     }
     return bad;
    }),[],`${locale} ${width}×${height}`);
    await page.screenshot({path:path.join(output,`${locale}-${width}x${height}.png`)});
    // Touch scrolling keeps the last reward reachable without moving the footer.
    await page.locator('.mail-detail-scroll').evaluate(el=>el.scrollTop=el.scrollHeight);
    const last=await page.locator('.mail-reward-card').last().boundingBox(),viewport=await page.locator('.mail-detail-scroll').boundingBox();
    assert.ok(last.y+last.height<=viewport.y+viewport.height+1);
    await page.waitForFunction(()=>!document.querySelector('[data-action=mailbox-back]').disabled);
    await page.locator('[data-action=mailbox-back]').click();
    results.push({locale,width,height,rewards:11});
   }
   for(variant of ['single','unknown']){
    await page.locator('.mail-card').filter({hasText:'Ein Dankeschön aus dem Königreich'}).locator('.mail-open').click();
    await page.locator('.mail-reward-grid').waitFor();
    assert.equal(await page.locator('.mail-reward-card').count(),2);
    if(variant==='single')assert.equal(await page.locator('[data-reward-code="10103021"]').getAttribute('data-reward-quantity'),'6');
    else{assert.equal(await page.locator('.mail-reward-grid script').count(),0);assert.equal(await page.locator('.mail-reward-name').first().innerText(),'<script>bad()</script>');assert.ok(!(await page.locator('.mail-reward-name').last().innerText()).includes('mail.reward'));}
    await page.waitForFunction(()=>!document.querySelector('[data-action=mailbox-back]').disabled);
    await page.locator('[data-action=mailbox-back]').click();
   }
   assert.deepEqual(errors,[]);await context.close();
  }
  fs.writeFileSync(path.join(output,'report.json'),JSON.stringify({results},null,2));
  console.log(`PASS gifts: ${results.length} actual-app layouts, catalog art and duration, exact quantities, scrolling, fixed touch actions, translations, single-item rewards and escaped fallback names. ${output}`);
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});

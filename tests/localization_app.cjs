'use strict';
// Read-only checks against tools/preview-feature-fixture.php --appearance --hud --chat.
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const base=process.env.LOCALIZATION_URL||'http://127.0.0.1:18987';assert(/^http:\/\/127\.0\.0\.1:\d+$/.test(base));
const output=path.resolve(__dirname,'../artifacts/localization');fs.mkdirSync(output,{recursive:true});
(async()=>{
 const browser=await chromium.launch({headless:true,channel:process.env.PLAYWRIGHT_CHANNEL||'chrome'});
 try{
  const page=await browser.newPage({viewport:{width:1280,height:800},locale:'de-DE'}),errors=[];page.on('pageerror',e=>errors.push(e.message));
  await page.goto(base);if(await page.locator('[data-mode="login"]').count())await page.goto(new URL('?zugang=login', page.url()).href);
  await page.locator('[name="identifier"], [name="username"]').fill('PreviewPlayer');await page.locator('[name="password"]').fill('PreviewFixture!2026');
  await Promise.all([page.waitForURL('**/city'),page.locator('button[type="submit"], #auth-submit').first().click()]);
  await page.waitForSelector('.painted-village');
  assert.equal(await page.locator('html').getAttribute('lang'),'en','English is the primary language even in a German browser');
  for(const lang of ['en','fr']){
   await Promise.all([page.waitForNavigation({waitUntil:'domcontentloaded'}),page.evaluate(lang=>ConquerLocale.setLocale(lang),lang)]);
   await page.locator('#hud-research:not([data-job-state="loading"])').waitFor({state:'attached'});
   await page.waitForFunction(lang=>document.documentElement.lang===lang&&Boolean(window.ConquerLocale),lang);
   for(const section of ['help','settings','inventory','treasures','research','army','profile','quests','market','defense','events','mastery','account','community','alliance','expeditions','dungeons','land','reports','rankings','worlds']){
    await page.goto(base+'/city#'+section);await page.waitForSelector('#panel-dialog[open]');await page.waitForTimeout(250);
    if(section==='worlds')await page.locator('.world-selector').waitFor();
    if(section==='market')await page.waitForFunction(()=>document.querySelectorAll('.shop-merchant-card').length===8&&!document.querySelector('.trading-empty'));
    assert.equal(await page.locator('#player-hud-name').textContent(),'PreviewPlayer');
    const text=await page.locator('#panel-dialog').innerText();
    assert(!text.includes('{p0}')&&!text.includes('copy.'),`${lang} ${section}: unresolved translation`);
    if(lang==='en'&&section==='army')assert(!(text.includes('Kaserne')||text.includes('Train Infanterie')),'training copy is fully English');
    if(section==='expeditions')assert(text.includes(lang==='en'?'600 Gold':'600 Or'),'expedition reward uses the selected resource language');
    if(section==='worlds')assert(text.includes(lang==='en'?'Castle level 12':'Niveau du château 12'),'world castle level is editorial copy: '+text);
    if(section==='settings')assert.equal(await page.locator('#panel-dialog [data-locale-select]').inputValue(),lang);
    if(section==='help')assert(text.includes(lang==='en'?'Your kingdom begins with you':'Votre royaume commence avec vous'));
   }
   for(const section of ['help','settings','research','inventory','army','worlds','market','expeditions'])for(const [width,height]of [[1280,800],[390,844],[320,568],[844,390],[568,320]]){
    await page.setViewportSize({width,height});await page.goto(base+'/city#'+section);await page.waitForSelector('#panel-dialog[open]');await page.evaluate(()=>document.fonts.ready);
    if(section==='worlds')await page.locator('.world-selector').waitFor();
    if(section==='market')await page.waitForFunction(()=>document.querySelectorAll('.shop-merchant-card').length===8&&!document.querySelector('.trading-empty'));
    const issues=await page.evaluate(()=>{
     const p=document.querySelector('#panel-dialog'),r=p.getBoundingClientRect(),close=p.querySelector('.panel-close').getBoundingClientRect();return [
      ...(r.left < -1||r.right>innerWidth+1||r.top < -1||r.bottom>innerHeight+1?['dialog outside viewport']:[]),
      ...(p.scrollWidth>p.clientWidth+2?['horizontal overflow']:[]),
      ...(close.right>innerWidth+1||close.bottom>innerHeight+1?['close button unreachable']:[])
     ];
    });assert.deepEqual(issues,[],`${lang} ${section} ${width}x${height}`);
    await page.screenshot({path:path.join(output,`${section}-${lang}-${width}.png`)});
    if(section==='market')for(const id of ['crystals','vip','caravan','merchant']){
     const tab=page.locator(`[data-action="trading-tab"][data-id="${id}"]`);
     await tab.scrollIntoViewIfNeeded();await tab.click();
     await page.waitForFunction(id=>document.querySelector(`.trading-tabs [data-id="${id}"]`)?.getAttribute('aria-pressed')==='true',id);
     await page.evaluate(()=>new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve))));
     const geometry=await tab.evaluate(button=>{const r=button.getBoundingClientRect(),strip=button.parentElement.getBoundingClientRect(),style=getComputedStyle(button);return {fits:button.scrollWidth<=button.clientWidth+1,height:r.height,visible:r.left>=strip.left-1&&r.right<=strip.right+1,ellipsis:style.textOverflow==='ellipsis'};});
     assert(geometry.fits&&geometry.height>=44&&geometry.visible&&!geometry.ellipsis,`${lang} market ${id} ${width}: `+JSON.stringify(geometry));
     if(id==='crystals'&&width===320)await page.screenshot({path:path.join(output,`market-${lang}-${width}-crystals.png`)});
    }
   }
   // The shared chat preview uses the current channel label and preserves messages.
   await page.goto(base+'/city#city');
   // The channel label is synchronous; message loading is an authenticated request.
   await page.locator('[data-chat-preview-messages] .world-chat-preview-message').first().waitFor({state:'attached'});
   assert.equal(await page.locator('[data-chat-preview-channel]').textContent(),lang==='en'?'World':'Monde');
   const messageText=await page.locator('[data-chat-preview-messages]').textContent();
   assert(messageText.includes('Rund um den Wald gibt es noch freie Rohstofffelder.'),'chat messages remain in the author’s language: '+messageText);
   await page.screenshot({path:path.join(output,`city-${lang}.png`)});
   await page.setViewportSize({width:1280,height:800});
   await page.locator('.painted-village').waitFor();
   assert.equal(await page.locator('html').getAttribute('lang'),lang);
   assert.equal(await page.locator('#city-frame').count(),0,'single painted city has no embedded HUD');
   await page.screenshot({path:path.join(output,`city-painted-${lang}-overview.png`)});
  }
  assert.deepEqual(errors,[]);console.log('PASS 21 game panels in EN/FR, untouched player identity, language selector, 80 responsive views and painted city overview.');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});

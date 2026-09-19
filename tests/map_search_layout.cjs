'use strict';
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright'),assert=require('assert'),path=require('path');
const base=process.env.MAP_SEARCH_FIXTURE_URL||'http://127.0.0.1:18959';assert(/^http:\/\/127\.0\.0\.1:\d+$/.test(base));
(async()=>{const browser=await chromium.launch({headless:true,channel:'chrome'});try{
 const page=await browser.newPage({viewport:{width:390,height:844}});page.on('pageerror',e=>console.error(e.stack));
 await page.goto(base);await page.locator('[data-mode="login"]').click();await page.locator('[name="username"]').fill('PreviewPlayer');await page.locator('[name="password"]').fill('PreviewFixture!2026');await Promise.all([page.waitForURL('**/city'),page.locator('#auth-submit').click()]);
 await page.waitForFunction(()=>document.querySelector('#player-hud-name')?.textContent.includes('PreviewPlayer'));await page.locator('#navigation [data-id="world"]').click();
 await page.locator('[data-atlas="search"]').click();await page.waitForFunction(()=>document.querySelector('#atlas-object-level').max!=='0');await page.locator('[data-search-category="food"]').click();await page.locator('#atlas-object-level').fill('1');await page.locator('.atlas-object-search-submit').click();await page.locator('.atlas-target-actions [data-atlas="search-next"]').waitFor();
 for(const [width,height] of [[1280,800],[390,844],[320,568],[844,390],[568,320]]){
  await page.setViewportSize({width,height});await page.waitForTimeout(200);
  const boxes=await page.locator('.atlas-target-actions .atlas-action').evaluateAll(buttons=>buttons.map(button=>{const r=button.getBoundingClientRect(),label=button.lastElementChild,l=label.getBoundingClientRect();return {text:label.textContent,button:{top:r.top,bottom:r.bottom,left:r.left,right:r.right},label:{top:l.top,bottom:l.bottom,left:l.left,right:l.right},styles:[...button.children].map(el=>{const s=getComputedStyle(el);return {height:s.height,minHeight:s.minHeight,margin:s.margin,padding:s.padding,lineHeight:s.lineHeight,position:s.position,transform:s.transform};})};}));
  for(const box of boxes)assert(box.label.top>=box.button.top&&box.label.bottom<=box.button.bottom&&box.label.left>=box.button.left&&box.label.right<=box.button.right,`${width}x${height}: label inside button ${JSON.stringify(box)}`);
  await page.screenshot({path:path.resolve(__dirname,`../artifacts/map-search/${width}x${height}-result.png`)});console.log(`PASS ${width}x${height}: every action label fits its button`);
 }
}finally{await browser.close();}})().catch(e=>{console.error(e);process.exitCode=1;});

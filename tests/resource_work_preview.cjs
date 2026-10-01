// Read-only visual check of the standalone work gallery; no game session needed.
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const output=path.resolve(__dirname,'../artifacts/resource-work');fs.mkdirSync(output,{recursive:true});
(async()=>{const browser=await chromium.launch({headless:true,channel:'msedge'}),errors=[];
 try{const page=await browser.newPage();page.on('pageerror',e=>errors.push(e.message));
  await page.goto('http://localhost/conquer/assets/world-preview/work.html');
  await page.waitForFunction(()=>document.querySelectorAll('canvas[data-work-ready="true"]').length===5);
  for(const [width,height]of [[1280,800],[390,844],[320,568],[844,390]]){
   await page.setViewportSize({width,height});await page.waitForTimeout(100);
   assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'no overflow');
   await page.screenshot({path:path.join(output,`${width}x${height}.png`),fullPage:true});
  }
  await page.locator('#motion').click();await page.waitForTimeout(100);
  const frames=()=>page.locator('canvas').evaluateAll(nodes=>nodes.map(c=>c.toDataURL()));
  const still=await frames();await page.waitForTimeout(1100);assert.deepEqual(await frames(),still,'reduced work stable');
  await page.locator('#work').click();await page.waitForTimeout(100);
  assert.equal(await page.locator('canvas[data-working="false"]').count(),5);
  assert.notDeepEqual(await frames(),still,'work layers removed, even when reduced');
  await page.locator('#motion').click();const free=await frames();await page.waitForTimeout(1100);assert.deepEqual(await frames(),free,'all five idle resources stay still');
  assert.deepEqual(errors,[]);console.log('PASS work gallery: four viewports, assets, reduced/idle transitions, no errors');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});

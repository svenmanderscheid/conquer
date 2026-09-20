'use strict';
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const base=process.argv[2];
assert(/^http:\/\/127\.0\.0\.1:\d+\/conquer$/.test(base),'Disposable local fixture required');
const out=path.resolve(__dirname,'../artifacts/alpha-keys-admin');fs.mkdirSync(out,{recursive:true});
(async()=>{
 const browser=await chromium.launch({headless:true,channel:process.env.PLAYWRIGHT_CHANNEL||'chrome'});
 const context=await browser.newContext({viewport:{width:1280,height:900},hasTouch:true});
 const page=await context.newPage(),errors=[];page.on('pageerror',e=>errors.push(e.message));
 page.setDefaultTimeout(15000);
 const login=async name=>{await page.goto(base+'/admin/login');await page.locator('[name=username]').fill(name);await page.locator('[name=password]').fill('Fixture-Alpha-2026!');await page.getByRole('button',{name:'Anmelden',exact:true}).click();await page.waitForURL(base+'/admin');};
 const formData=()=>page.locator('.alpha-create form').evaluate(f=>Object.fromEntries(new FormData(f)));
 const post=async(data,action='alpha-key-create')=>context.request.post(base+'/admin/action/'+action,{form:data,maxRedirects:0});
 try{
  await page.goto(base+'/admin/alpha-keys');assert.equal(new URL(page.url()).pathname,'/conquer/admin/login','anonymous overview requires login');
  const anonymous=await context.request.post(base+'/admin/action/alpha-key-create',{form:{label:'Unauthorized'},maxRedirects:0});assert.equal(anonymous.status(),302);
  await login('AlphaAdmin');await page.locator('#admin-nav a').filter({hasText:'Alpha-Keys'}).click();await page.waitForURL('**/admin/alpha-keys?world_id=1');
  assert.equal(await page.locator('.world-picker').count(),0,'keys are global');
  assert(await page.locator('#admin-nav a.active').evaluate(e=>getComputedStyle(e).color===getComputedStyle(document.body).color),'active navigation retains dark readable text');
  const form=page.locator('.alpha-create form');
  await form.locator('[name=label]').fill('Browser Testgruppe');await form.locator('[name=quantity]').fill('2');await form.locator('[name=max_uses]').fill('3');await form.locator('[name=expires_at]').fill('2035-12-31T23:59');
  const creation=await formData();
  assert.equal((await post({...creation,csrf_token:'forged'})).status(),403,'forged CSRF rejected');
  assert.equal((await context.request.get(base+'/admin/action/alpha-key-create',{maxRedirects:0})).status(),405,'GET cannot create keys');
  await form.getByRole('button',{name:'Alpha-Keys erstellen',exact:true}).click();await page.waitForURL(base+'/admin/alpha-keys');
  await page.locator('#alpha-issued-keys').waitFor();const keyText=await page.locator('#alpha-issued-keys').inputValue();const keys=keyText.split('\n');assert.equal(keys.length,2);assert(keys.every(k=>/^(?:[A-F0-9]{4}-){5}[A-F0-9]{4}$/.test(k)));
  assert.match(await page.locator('.notice.success').innerText(),/2 Alpha-Keys/);
  const response=await context.request.get(base+'/admin/alpha-keys');assert.match(response.headers()['cache-control'],/no-store/);const reloadedHtml=await response.text();assert(!keys.some(key=>reloadedHtml.includes(key)));
  // Real clipboard readback, then unavailable-clipboard fallback for LAN/mobile browsers.
  await context.grantPermissions(['clipboard-read','clipboard-write']);
  await page.locator('[data-copy-alpha]').click();await page.waitForFunction(()=>document.querySelector('[data-alpha-copy-status]').textContent.startsWith('Kopiert.'));
  assert.equal((await page.evaluate(()=>navigator.clipboard.readText())).replaceAll('\r\n','\n'),keyText,'copy button copies all issued keys');
  await page.evaluate(()=>Object.defineProperty(navigator,'clipboard',{configurable:true,value:undefined}));await page.locator('[data-copy-alpha]').click();assert.match(await page.locator('[data-alpha-copy-status]').innerText(),/markiert/);
  assert.equal(await page.locator('#alpha-issued-keys').evaluate(e=>e.selectionEnd-e.selectionStart),keyText.length,'fallback selects complete keys');
  const first=page.locator('.alpha-key-row').filter({hasText:'Browser Testgruppe'}).first();await first.locator('summary').click();
  for(const [width,height] of [[1280,900],[390,844],[320,700],[844,390],[568,320]]){
   await page.setViewportSize({width,height});await page.evaluate(()=>document.fonts.ready);
   assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+2),false,`no page overflow at ${width}x${height}`);
   for(const locator of [page.locator('[data-copy-alpha]'),form.locator('[type=submit]'),first.locator('button[type=submit]')]){
    await locator.scrollIntoViewIfNeeded();const box=await locator.boundingBox();assert(box.x>=0&&box.x+box.width<=width+1&&box.height>=40,'actions remain reachable and touch sized');
   }
   await first.screenshot({path:path.join(out,`key-details-${width}x${height}.png`)});
   await page.evaluate(()=>window.scrollTo(0,0));await page.screenshot({path:path.join(out,`alpha-keys-${width}x${height}.png`)});
  }
  await page.setViewportSize({width:390,height:844});await page.reload();assert.equal(await page.locator('#alpha-issued-keys').count(),0,'reload cannot recover secret');
  assert.equal((await post(creation)).status(),303);await page.goto(base+'/admin/alpha-keys');assert.match(await page.locator('.notice.success').innerText(),/keine weiteren Keys/);assert.equal(await page.locator('#alpha-issued-keys').count(),0);
  assert.equal(await page.locator('.alpha-key-row').filter({hasText:'Browser Testgruppe'}).count(),2,'replayed submission did not create more keys');
  const invalid=await formData();assert.equal((await post({...invalid,label:'Entwurf behalten',expires_at:'2027-02-30T12:00'})).status(),303);await page.goto(base+'/admin/alpha-keys');assert.match(await page.locator('.notice.error').innerText(),/gültiges Ablaufdatum/);assert.equal(await form.locator('[name=label]').inputValue(),'Entwurf behalten');
  const revoke=page.locator('.alpha-key-row').filter({hasText:'Browser Testgruppe'}).first();const id=await revoke.getAttribute('data-alpha-key-id');await revoke.locator('summary').click();await revoke.locator('[name=reason]').fill('Einladung zurückgezogen');await revoke.getByRole('button',{name:'Key jetzt sperren'}).click();await page.waitForURL(base+'/admin/alpha-keys');await page.locator('.notice.success').waitFor();
  assert.equal(await page.locator(`[data-alpha-key-id="${id}"] .alpha-key-status`).innerText(),'Gesperrt');
  await page.locator('.toolbar [name=q]').fill('Browser Testgruppe');await page.locator('.toolbar [name=status]').selectOption('revoked');await page.getByRole('button',{name:'Filtern',exact:true}).click();await page.waitForURL('**/alpha-keys?q=Browser+Testgruppe&status=revoked');await page.locator('.alpha-key-row').first().waitFor();assert.equal(await page.locator('.alpha-key-row').count(),1);
  await page.locator('.toolbar [name=q]').fill('Seitentest');await page.locator('.toolbar [name=status]').selectOption('');await page.getByRole('button',{name:'Filtern',exact:true}).click();await page.waitForURL('**/alpha-keys?q=Seitentest&status=');await page.locator('.pagination').getByRole('link',{name:'Weiter →'}).click();await page.waitForURL('**/alpha-keys?q=Seitentest&status=&page=2');assert.equal(await page.locator('.alpha-key-row').count(),3);
  await page.locator('.mobile-menu').click();await page.locator('#admin-nav a').filter({hasText:'Alpha-Keys'}).click();await page.waitForURL('**/admin/alpha-keys?world_id=1');
  const validPayload=await formData();
  await page.goto(base+'/admin/logout');await login('AlphaModerator');await page.goto(base+'/admin/alpha-keys');assert(await form.locator('button[type=submit]').isDisabled());assert.equal(await page.locator('.alpha-key-revoke').count(),0);assert.equal(await page.locator('#alpha-issued-keys').count(),0);
  const moderator=await formData();assert.equal((await post({...validPayload,csrf_token:moderator.csrf_token})).status(),403,'moderator cannot forge creation');assert.equal((await post({...moderator,key_id:id},'alpha-key-revoke')).status(),403,'moderator cannot forge revocation');
  assert.deepEqual(errors,[],'no browser errors');console.log('ALL ALPHA KEY BROWSER CHECKS PASSED · '+out);
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});

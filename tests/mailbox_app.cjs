'use strict';
// Full application against tools/preview-feature-fixture.php --port=18964 --mailbox --hud --chat.
const fs=require('fs'),path=require('path'),os=require('os'),assert=require('assert/strict');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const base=process.env.MAILBOX_FIXTURE_URL||'http://127.0.0.1:18964';
assert(/^http:\/\/127\.0\.0\.1:\d+$/.test(base),'Disposable local preview required');
const output=fs.mkdtempSync(path.join(os.tmpdir(),'conquer-mailbox-app-'));
(async()=>{
 const browser=await chromium.launch({headless:true,executablePath:process.env.BROWSER_EXECUTABLE_PATH||'C:/Program Files/Google/Chrome/Application/chrome.exe'});
 try{
  const page=await browser.newPage({viewport:{width:1280,height:800},hasTouch:true}),errors=[];page.setDefaultTimeout(30000);page.on('pageerror',e=>errors.push(e.stack||e.message));
  if(process.env.MAILBOX_DEBUG)page.on('response',async r=>{if(r.url().includes('/api/mailbox/state')){const j=await r.json();console.log(r.status(),r.url(),j.data?.entries?.length??j);}});
  await page.goto(base);await page.locator('[data-mode="login"]').click();await page.locator('[name="username"]').fill('PreviewPlayer');await page.locator('[name="password"]').fill('PreviewFixture!2026');
  await Promise.all([page.waitForURL('**/city'),page.locator('#auth-submit').click()]);
  const dock=page.locator('#navigation [data-id="reports"]');await dock.waitFor();await page.locator('.mail-dock-badge:not([hidden])').waitFor();
  await dock.click();await page.locator('.mail-card').first().waitFor();assert.equal(await page.locator('#page-title').textContent(),'Post');
  const tab=async id=>{await page.locator(`[data-action="mailbox-tab"][data-id="${id}"]`).first().click();await page.waitForFunction(id=>document.querySelector('.mail-toolbar strong')?.textContent?.startsWith(({war:'Krieg',alliance:'Allianz',system:'System',reports:'Berichte',starred:'Favoriten',private:'Privat',sent:'Gesendet'})[id])&&document.querySelector('.mail-list')?.getAttribute('aria-busy')==='false',id);};
  for(const [width,height] of [[1280,800],[390,844],[320,568],[568,320],[844,390]]){
   await page.setViewportSize({width,height});
   for(const category of ['war','alliance','system','reports','starred','private']){
    await tab(category);
    const bad=await page.evaluate(()=>{
     const list=document.querySelector('.mail-list'),root=document.querySelector('.mailbox-shell'),bad=[];
     if(root.scrollWidth>root.clientWidth+1)bad.push('mailbox horizontal overflow');
     if(list.clientHeight<60)bad.push('message list too short: '+list.clientHeight);
     const tabs=[...root.querySelectorAll('.mail-tab')],ys=tabs.map(t=>t.getBoundingClientRect().top);
     if(Math.max(...ys)-Math.min(...ys)>1)bad.push('tabs must stay in one row');
     for(const t of tabs){const label=t.querySelector('span');if(label.scrollWidth>label.clientWidth+1)bad.push('tab label clipped: '+label.textContent);}
     if(root.querySelector('.mail-preview'))bad.push('content preview is still visible');
     for(const row of root.querySelectorAll('.mail-card')){if(row.querySelector('.mail-copy').children.length!==3)bad.push('message must have exactly three text lines');if(row.getBoundingClientRect().height>78)bad.push('message row is not compact');const status=row.querySelector('.mail-read-status')?.textContent.trim();if(row.classList.contains('unread')&&status!=='Neu')bad.push('unread message has no visible Neu status');if(row.classList.contains('read')&&status!=='✓ Gelesen')bad.push('read message has no visible Gelesen status');}
     for(const el of document.querySelectorAll('.mail-tabs button,.mail-footer button,.panel-close')){
      const r=el.getBoundingClientRect();if(r.left<0||r.top<0||r.right>innerWidth+1||r.bottom>innerHeight+1)bad.push(el.textContent+' outside screen');
      const top=document.elementFromPoint(r.x+r.width/2,r.y+r.height/2);if(top&&!el.contains(top)&&top!==el)bad.push(el.textContent+' covered by '+top.className);
     }return bad;
    });assert.deepEqual(bad,[],`${width}×${height} ${category}`);
    if(['war','system','alliance','reports'].includes(category))await page.screenshot({path:path.join(output,`${width}x${height}-${category}.png`)});
   }
  }
  await page.setViewportSize({width:390,height:844});await tab('system');
  await page.waitForFunction(()=>document.querySelectorAll('.mail-card').length===50);await page.evaluate(()=>{const l=document.querySelector('.mail-list');l.scrollTop=l.scrollHeight});await page.waitForFunction(()=>document.querySelectorAll('.mail-card').length===59);
  await page.locator('.mail-list').evaluate(e=>e.scrollTop=260);
  const originalScroll=await page.locator('.mail-list').evaluate(e=>e.scrollTop);await page.locator('[data-action="mailbox-reload"]').click();await page.waitForTimeout(700);assert.equal(await page.locator('.mail-list').evaluate(e=>e.scrollTop),originalScroll,'refresh preserves loaded list and scroll');
  await tab('reports');const monsterMail=page.locator('.mail-card').filter({hasText:'Angriff: Sieg gegen Ork-Späher Lv. 8'});await monsterMail.waitFor();assert.equal((await monsterMail.locator('.mail-kind').innerText()).trim(),'Ork-Späher · Lv. 8','monster target remains visible in the compact second line');assert.match(await monsterMail.locator('.mail-art img').getAttribute('src'),/\/assets\/art\/map\/life-orc\.png$/,'monster report uses the monster artwork instead of the mail envelope');
  await tab('war');const starButton=page.locator('.mail-card').first().locator('[data-action="mailbox-star"]');await starButton.click();await page.waitForFunction(()=>document.querySelector('.mail-card .mail-star')?.getAttribute('aria-pressed')==='true');
  await tab('starred');assert.equal(await page.locator('.mail-card').count(),1);await page.locator('.mail-open').click();await page.locator('.mail-detail').waitFor();await page.waitForFunction(()=>!document.querySelector('.mail-detail button')?.disabled);await page.locator('[data-action="mailbox-back"]').click();assert.equal((await page.locator('.mail-card.read .mail-read-status').innerText()).trim(),'✓ Gelesen','opening a message makes its read state immediately visible');
  await page.locator('[data-action="mailbox-delete-read"]').click();await page.waitForTimeout(600);assert.equal(await page.locator('.mail-card').count(),1,'favorites protected from deletion');
  await tab('alliance');assert.equal(await page.locator('.mail-reward-dot').count(),1);assert.equal(await page.locator('[data-action="mailbox-claim-all"]').innerText(),'Alle einsammeln');
  await page.locator('[data-action="mailbox-read-all"]').click();await page.waitForFunction(()=>document.querySelector('[data-action="mailbox-read-all"]')?.disabled&&!document.querySelector('[data-action="mailbox-claim-all"]')?.disabled);assert.equal(await page.locator('.mail-reward-dot').count(),1,'read all preserves reward dot');
  const claimRequests=[];let dropped=false;
  await page.route('**/api/community/action',async route=>{const payload=route.request().postDataJSON();if(payload.action==='mailbox.claim_all'){claimRequests.push(payload.request_id);if(!dropped){dropped=true;await route.fetch();await route.abort('failed');return;}}await route.continue();});
  await page.locator('[data-action="mailbox-claim-all"]').click();await page.waitForFunction(()=>document.querySelector('.mail-error')?.textContent.includes('Keine Verbindung')&&!document.querySelector('[data-action="mailbox-claim-all"]')?.disabled);await page.locator('[data-action="mailbox-claim-all"]').click();await page.waitForFunction(()=>!document.querySelector('.mail-reward-dot')&&document.querySelector('[data-action="mailbox-claim-all"]')?.disabled&&!document.querySelector('.mail-star')?.disabled);assert.equal(claimRequests.length,2);assert.equal(claimRequests[0],claimRequests[1],'uncertain bulk claim retries the same receipt');await page.unroute('**/api/community/action');
  await page.locator('.mail-card').filter({hasText:'Geschenk deiner Allianz'}).locator('.mail-open').click();await page.locator('.mail-detail').waitFor();assert.equal(await page.locator('.mail-rewards p').innerText(),'Abgeholt');await page.locator('[data-action="mailbox-back"]').click();
  await tab('private');await page.locator('.mail-open').first().click();await page.locator('.mail-detail').waitFor();await page.waitForFunction(()=>!document.querySelector('[data-action="mailbox-reply"]')?.disabled);await page.locator('[data-action="mailbox-reply"]').click();await page.locator('.mail-compose').waitFor();assert.equal(await page.locator('.mail-compose select').inputValue(),'2');assert.match(await page.locator('.mail-compose input[name="subject"]').inputValue(),/^Re: /);
  await page.locator('.mail-compose textarea').fill('Entwurf für später <script>alert(1)</script>');await page.locator('[data-action="mailbox-back"]').click();await page.locator('[data-action="mailbox-compose"]').click();await page.locator('.mail-compose').waitFor();assert.equal(await page.locator('.mail-compose textarea').inputValue(),'Entwurf für später <script>alert(1)</script>');
  for(const [width,height]of [[320,568],[568,320]]){await page.setViewportSize({width,height});const box=await page.locator('.mail-compose button[type="submit"]').boundingBox();assert.ok(box.y>=0&&box.y+box.height<=height);await page.screenshot({path:path.join(output,`${width}x${height}-compose.png`)});}
  await page.locator('.mail-compose button[type="submit"]').click();await page.waitForFunction(()=>document.querySelector('.mail-toolbar strong')?.textContent.startsWith('Gesendet')&&!document.querySelector('#game-dialog')?.open);await page.locator('.mail-card').filter({hasText:'Re: Treffen'}).waitFor();
  assert.equal(await page.locator('.mailbox-shell script').count(),0);
  await page.locator('.panel-close').click();await page.setViewportSize({width:390,height:844});await dock.click();await page.locator('.mailbox-shell').waitFor();await page.goBack();await page.waitForFunction(()=>!document.querySelector('#panel-dialog').open);
  assert.deepEqual(errors,[]);console.log('PASS full Post application: 30 responsive category layouts, pagination, scroll, favorites, protected deletion, reward claim, private reply/draft/send, escaping and browser back. '+output);
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
